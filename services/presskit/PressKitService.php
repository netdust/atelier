<?php

declare(strict_types=1);

namespace Atelier\PressKit;

use Atelier\Grants\GrantRepository;
use Atelier\Profile\ProfileModel;
use Atelier\Support\NoCache;
use WP_Post;
use WP_Query;

defined('ABSPATH') || exit;

/**
 * A press kit per production at /{base}/{slug}/ and the company's at /{base}/, in the language of its
 * URL, rendered by the site's builder template for kits, and each kit's zip at …/download/. A kit that
 * is not public opens with a personal `?token=`. Every refusal is the site's own 404, so a kit's
 * existence, its public state and a token's fate are never told apart.
 */
class PressKitService
{
    private const NOINDEX = 'X-Robots-Tag: noindex';

    private const LOG = 'presskit';

    private const TEMP_PREFIX = 'atelier-presskit-';

    private const CHUNK_BYTES = 8192;

    private const TOKEN = 'token';

    /** The query vars core's rewrite writes for a kit route; public, so a request can name them itself. */
    private const ROUTE_VARS = ['ntdst_page', 'ntdst_p_slug'];

    /** A kit is read: HEAD is GET without the body (RFC 9110 §9.3.2). */
    private const READS = ['GET', 'HEAD'];

    /**
     * `base` is one word, or one per language; the order is the rewrite order — the literal routes
     * first, or `/{base}/:slug/` would answer `/{base}/download/`.
     */
    private const ROUTES = [
        'company'             => '',
        'company_download'    => '/download',
        'production'          => '/:slug',
        'production_download' => '/:slug/download',
    ];

    private const DEFAULTS = [
        'base'   => 'press',
        'labels' => ['name' => 'Press kit'],
        // The zip's folders, the company kit's logo row and an empty zip's README: each one text, or one per language.
        'words'  => [
            'photos'    => 'Photos',
            'logos'     => 'Logos',
            'documents' => 'Documents',
            'logo'      => 'Logo',
            'readme'    => 'This kit has no files yet.',
        ],
    ];

    /** @var array<string, mixed> */
    private array $config;

    /** This request's answer, remembered with the main query it was decided for. */
    private ?array $decision = null;

    public function __construct(
        private readonly PressKitModel $model,
        private readonly GrantRepository $grants,
    ) {
        $this->init();
    }

    /** `PressKitService` would derive `press_kit`; the folder and the override key say `presskit`. */
    public static function metadata(): array
    {
        return ['name' => 'presskit'];
    }

    /** A kit page: the theme's page template, where the builder renders the kit template; false refuses. */
    public function render(): string|false
    {
        $decision = $this->decision();
        $page = $this->pageTemplate();

        if ($decision === null || !$decision['admitted'] || $page === null) {
            return false;
        }

        $kit = $decision['kit'];
        add_filter('document_title_parts', static fn(array $parts): array => ['title' => $kit['title']] + $parts);
        add_filter('ntdst/baseline/seo/meta', static fn(array $tags): array => self::head($tags, $kit));
        $this->emitHeaders([self::NOINDEX, ...NoCache::headers()]);
        $this->recordUse($decision['grant']);

        return $page;
    }

    /**
     * baseline's head tags, naming the kit. WordPress queried no post here — under /fr/ it queried
     * Polylang's language archive, whose term would give the canonical and the description.
     *
     * @param list<string> $tags
     * @return list<string>
     */
    private static function head(array $tags, array $kit): array
    {
        $derived = '/rel="canonical"|name="description"|property="og:(?:url|title|description)"/';
        $kept = array_filter($tags, static fn(mixed $tag): bool => !is_string($tag) || preg_match($derived, $tag) !== 1);

        return [
            ...array_values($kept),
            '<link rel="canonical" href="' . esc_url($kit['url']) . '">',
            '<meta property="og:url" content="' . esc_url($kit['url']) . '">',
            '<meta property="og:title" content="' . esc_attr($kit['title']) . '">',
        ];
    }

    /**
     * A kit's zip, built in a temp file outside every web-served directory and streamed in chunks. null
     * once sent — core then ends the request; false refuses, as the page does. Only a sent zip counts.
     */
    public function download(): ?false
    {
        $decision = $this->decision();

        if ($decision === null || !$decision['admitted']) {
            return false;
        }

        $path = $this->tempFile();

        if ($path === null) {
            return false;
        }

        // finally covers every return and throw; the shutdown function a client that leaves mid-stream.
        register_shutdown_function(static fn() => self::remove($path));

        try {
            return $this->stream($decision['kit'], $decision['grant'], $path);
        } finally {
            self::remove($path);
        }
    }

    /**
     * Which kit route this request is, its kit, whether it opens and the grant that opened it — decided
     * once per main query: YOOtheme asks at pre_get_posts, before the route's callback runs, and both
     * must agree. null when the URL is no kit route.
     *
     * @return array{route: string, kit: ?array, admitted: bool, grant: ?WP_Post}|null
     */
    public function decision(?WP_Query $query = null): ?array
    {
        $query ??= $GLOBALS['wp_query'];

        if (($this->decision['query'] ?? null) !== $query) {
            $this->decision = ['query' => $query, 'answer' => $this->decide($query)];
        }

        return $this->decision['answer'];
    }

    /**
     * A kit URL in the trailing-slash form WordPress would 301 to.
     *
     * @param array<string, string> $params
     */
    public function url(string $tail, array $params = [], ?string $lang = null): string
    {
        return trailingslashit(ntdst_pages()->url($this->paths($tail), $params, $lang));
    }

    /**
     * A personal link to the kit `$dutchId` holds (R-5): its page in `$lang` when that copy is
     * published, else the Dutch page, opened by `$token` — with the kit's title there. null when the
     * record holds no published kit.
     *
     * @return array{url: string, title: string}|null
     */
    public function link(string $postType, int $dutchId, string $lang, string $token): ?array
    {
        $record = $this->model->inLanguage($postType, $dutchId, $lang);

        if ($record === null || $token === '') {
            return null;
        }

        $page = $postType === ProfileModel::POST_TYPE
            ? $this->url(self::ROUTES['company'], [], $record['lang'] ?: null)
            : $this->url(self::ROUTES['production'], ['slug' => $record['slug']], $record['lang'] ?: null);

        return ['url' => add_query_arg(self::TOKEN, rawurlencode($token), $page), 'title' => $record['title']];
    }

    /**
     * Recognised by the round trip through core's own url(): the URL this route builds for the request's
     * slug is the URL requested, or this is no request to it. A route var the request named itself
     * overrides the rewrite's: core's dispatcher then passes the request through and the kit's own
     * headers are never sent, so that request is none of ours either. A kit is only read: any other
     * verb is refused before a token is looked at.
     *
     * @return array{route: string, kit: ?array, admitted: bool, grant: ?WP_Post}|null
     */
    private function decide(WP_Query $query): ?array
    {
        // No page route matched this URL, or the request wrote a route var itself: none of ours, no round trip to make.
        if ($query->get('ntdst_page') === '' || self::namesRouteVars()) {
            return null;
        }

        $slug = $query->get('ntdst_p_slug');
        $slug = is_scalar($slug) ? (string) $slug : '';
        $requested = trailingslashit(home_url((string) ($GLOBALS['wp']->request ?? '')));

        foreach (self::ROUTES as $route => $tail) {
            $params = str_contains($tail, ':slug') ? ['slug' => $slug] : [];

            if (($params === [] || $slug !== '') && $this->url($tail, $params) === $requested) {
                if (!in_array(self::method(), self::READS, true)) {
                    return ['route' => $route, 'kit' => null, 'admitted' => false, 'grant' => null];
                }

                $kit = $this->kit($route, $params);
                $opens = $kit === null ? false : $this->gate($kit);

                // The holder's zip opens with the same grant: its stored token, never the request's query.
                if ($opens instanceof WP_Post) {
                    $token = rawurlencode((string) ($opens->fields['token'] ?? ''));
                    $kit['download_url'] = add_query_arg(self::TOKEN, $token, $kit['download_url']);
                }

                return [
                    'route'    => $route,
                    'kit'      => $kit,
                    'admitted' => $opens !== false,
                    'grant'    => $opens instanceof WP_Post ? $opens : null,
                ];
            }
        }

        return null;
    }

    /** Whether the query string or the body names a route var, which WordPress then reads over the rewrite's. */
    private static function namesRouteVars(): bool
    {
        foreach (self::ROUTE_VARS as $var) {
            // phpcs:ignore WordPress.Security.NonceVerification -- only whether the key is present; nothing is read or acted on.
            if (isset($_GET[$var]) || isset($_POST[$var])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Who a published kit opens for: anyone when it is public (true); otherwise only the live grant its
     * `?token=` names (that grant), issued for this kit's Dutch record, so one link opens it in every
     * language; else no one (false). The token is handed to verify() as sent — it unslashes it once.
     */
    private function gate(array $kit): bool|WP_Post
    {
        if ($kit['public'] === true) {
            return true;
        }

        // phpcs:ignore WordPress.Security -- a credential, compared byte-exact by verify(); this raw request value is never altered or output.
        $token = $_GET[self::TOKEN] ?? '';

        if (!is_string($token) || $token === '') {
            return false;
        }

        return $this->grants->verify($token, (string) $kit['post_type'], (int) $kit['dutch_id']) ?? false;
    }

    /**
     * The route's kit in this request's language, with its page and download URLs.
     *
     * @param array<string, string> $params
     * @return array<string, mixed>|null
     */
    private function kit(string $route, array $params): ?array
    {
        $lang = (string) ntdst_pages()->language();
        $page = str_replace('_download', '', $route);
        $kit = $page === 'company'
            ? $this->model->forCompany($lang, $this->word('logo', $lang))
            : $this->model->forProduction($params['slug'], $lang);

        return $kit === null ? null : $kit + [
            'url'          => $this->url(self::ROUTES[$page], $params),
            'download_url' => $this->url(self::ROUTES[$page . '_download'], $params),
        ];
    }

    /** The zip built and sent; false when it cannot be, before a byte of it leaves. */
    private function stream(array $kit, ?WP_Post $grant, string $path): ?false
    {
        $lang = (string) ntdst_pages()->language();
        $archive = KitArchiveBuilder::build($path, $this->collections($kit, $lang), $this->word('readme', $lang));

        if ($archive === null) {
            ntdst_log(self::LOG)->error('Press kit zip could not be opened for writing.', ['kit' => $kit['dutch_id']]);

            return false;
        }

        foreach ($archive['skipped'] as $attachment => $reason) {
            ntdst_log(self::LOG)->warning("Press kit file skipped: attachment $attachment $reason.", ['kit' => $kit['dutch_id']]);
        }

        if ($this->responseStarted()) {
            ntdst_log(self::LOG)->error('Press kit zip not sent: output had started, so it would arrive corrupt.', ['kit' => $kit['dutch_id']]);

            return false;
        }

        $size = filesize($path);
        $handle = fopen($path, 'rb');

        // A zip holds at least its README: an empty file is a failed build, never a download.
        if ($size === false || $size === 0 || $handle === false) {
            ntdst_log(self::LOG)->error('Press kit zip is empty or could not be read back.', ['kit' => $kit['dutch_id']]);

            return false;
        }

        $this->count($kit);
        $this->recordUse($grant);

        $name = (new ArchiveNameSanitizer())->sanitize($kit['title'] . '.zip');
        $this->emitHeaders([
            ...\NTDST_Response::downloadHeaders($size, $name, 'application/zip'),
            ...NoCache::headers(),
            self::NOINDEX,
        ]);
        $this->emitFile($handle);

        return null;
    }

    /** A HEAD is answered as a GET, the same zip built so its length agrees, but it downloads nothing. */
    private function count(array $kit): void
    {
        if (self::isHead()) {
            return;
        }

        $counted = $this->model->countDownload($kit);

        if (is_wp_error($counted)) {
            ntdst_log(self::LOG)->error('Press kit download not counted: ' . $counted->get_error_message(), ['kit' => $kit['dutch_id']]);
        }
    }

    /** A token's use, where the kit is served; a HEAD — a link checker, a mail scanner — is none. */
    private function recordUse(?WP_Post $grant): void
    {
        if ($grant !== null && !self::isHead()) {
            $this->grants->recordAccess($grant);
        }
    }

    private static function isHead(): bool
    {
        return self::method() === 'HEAD';
    }

    private static function method(): string
    {
        return strtoupper(sanitize_key(wp_unslash((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'))));
    }

    /**
     * The kit's files as the builder reads them, each from its attachment id — never from the request —
     * a photo as its original upload, not WordPress's scaled copy.
     *
     * @return list<array{label: string, entries: list<array{ref: string, label: string, file: string, credit: string}>}>
     */
    private function collections(array $kit, string $lang): array
    {
        $collections = [];

        foreach (['photos', 'logos', 'documents'] as $key) {
            $entries = [];

            foreach ($kit[$key] as $row) {
                $file = wp_get_original_image_path($row['attachment']) ?: get_attached_file($row['attachment']);
                $file = is_string($file) ? $file : '';
                $entries[] = [
                    'ref'    => (string) $row['attachment'],
                    'label'  => (string) ($row['name'] ?? ''),
                    'file'   => $file,
                    'credit' => (string) ($row['credit'] ?? ''),
                ];
            }

            if ($entries !== []) {
                $collections[] = ['label' => $this->word($key, $lang), 'entries' => $entries];
            }
        }

        return $collections;
    }

    /**
     * An empty file for the zip, checked where tempnam() put it: it falls back to the system's temp
     * directory unasked, and get_temp_dir()'s own last resort is WP_CONTENT_DIR, inside the webroot.
     * Within uploads, the content directory or WordPress itself, it is removed and nothing is built.
     */
    private function tempFile(): ?string
    {
        $path = tempnam($this->tempDirectory(), self::TEMP_PREFIX);

        if (!is_string($path) || $path === '') {
            return null;
        }

        foreach ([(string) (wp_get_upload_dir()['basedir'] ?? ''), WP_CONTENT_DIR, ABSPATH] as $served) {
            if ($served !== '' && self::isWithin(dirname($path), $served)) {
                self::remove($path);
                ntdst_log(self::LOG)->error('Refusing to build a press kit zip in a web-served directory.', ['directory' => dirname($path)]);

                return null;
            }
        }

        return $path;
    }

    protected function tempDirectory(): string
    {
        return get_temp_dir();
    }

    /** The directory itself or one below it — separator-aware: /x/uploads-public is not inside /x/uploads. */
    private static function isWithin(string $path, string $parent): bool
    {
        $path = rtrim(realpath($path) ?: $path, '/\\');
        $parent = rtrim(realpath($parent) ?: $parent, '/\\');

        return str_starts_with($path . DIRECTORY_SEPARATOR, $parent . DIRECTORY_SEPARATOR);
    }

    private static function remove(string $path): void
    {
        if (is_file($path)) {
            unlink($path);
        }
    }

    /**
     * Bytes already written would arrive ahead of the zip and corrupt it. headers_sent() alone misses
     * them: PHP's own output buffer is open here, holding a notice or a theme's whitespace unsent.
     */
    private function responseStarted(): bool
    {
        if ($this->headersSent()) {
            return true;
        }

        foreach (ob_get_status(true) as $buffer) {
            if ((int) ($buffer['buffer_used'] ?? 0) > 0) {
                return true;
            }
        }

        return false;
    }

    protected function headersSent(): bool
    {
        return headers_sent();
    }

    /**
     * In chunks: a kit of a few hundred megabytes is never held in memory whole.
     *
     * @param resource $handle
     */
    private function emitFile($handle): void
    {
        while (($chunk = fread($handle, self::CHUNK_BYTES)) !== false && $chunk !== '') {
            $this->emitBody($chunk);
        }

        fclose($handle);
    }

    /** A configured word in `$lang`, or the first language's where it has none. */
    private function word(string $key, string $lang): string
    {
        $word = $this->config['words'][$key];

        return (string) (is_array($word) ? ($word[$lang] ?? reset($word)) : $word);
    }

    /** The theme's page template; null when the theme has none, and the kit refuses. */
    protected function pageTemplate(): ?string
    {
        return \NTDST_Template_Loader::locate('page');
    }

    /** @return string|array<string, string> the route pattern, one per language when the base is */
    private function paths(string $tail): string|array
    {
        $base = $this->config['base'];

        return is_array($base)
            ? array_map(static fn($word): string => '/' . $word . $tail, $base)
            : '/' . $base . $tail;
    }

    /**
     * Each line replaces a header of its name: baseline's public caching, sent earlier on template_redirect.
     *
     * @param list<string> $headers complete header lines
     */
    protected function emitHeaders(array $headers): void
    {
        foreach ($headers as $header) {
            header($header);
        }
    }

    protected function emitBody(string $body): void
    {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a complete non-HTML body, escaped by its builder.
        echo $body;
    }

    /** One init callback, in ROUTES order. */
    private function init(): void
    {
        $this->config = apply_filters('ntdst/service/presskit/config', self::DEFAULTS) + self::DEFAULTS;
        $this->config['words'] = (array) $this->config['words'] + self::DEFAULTS['words'];

        add_action('init', function (): void {
            ntdst_pages()
                ->path($this->paths(self::ROUTES['company']), fn(): string|false => $this->render())
                ->path($this->paths(self::ROUTES['company_download']), fn(): ?false => $this->download())
                ->path($this->paths(self::ROUTES['production']), fn(): string|false => $this->render())
                ->path($this->paths(self::ROUTES['production_download']), fn(): ?false => $this->download());
        });

        add_action('after_setup_theme', function (): void {
            if (!function_exists('YOOtheme\app')) {
                return;
            }

            require_once __DIR__ . '/yootheme.php';
            register_presskit_template($this->config);
        }, 30);
    }
}
