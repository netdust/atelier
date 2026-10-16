<?php

declare(strict_types=1);

namespace Atelier\PressKit;

use Atelier\Support\NoCache;
use WP_Query;

defined('ABSPATH') || exit;

/**
 * A press kit per production at /{base}/{slug}/ and the company's at /{base}/, in the language of its
 * URL, rendered by the site's builder template for kits. Every refusal is the site's own 404, so a
 * kit's existence and its public state are never told apart.
 */
class PressKitService
{
    private const NOINDEX = 'X-Robots-Tag: noindex';

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
    ];

    /** @var array<string, mixed> */
    private array $config;

    /** This request's answer, remembered with the main query it was decided for. */
    private ?array $decision = null;

    public function __construct(private readonly PressKitModel $model)
    {
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

    /** Whether a kit opens: a public kit does. */
    public function gate(array $kit): bool
    {
        return $kit['public'] === true;
    }

    /**
     * Which kit route this request is, its kit and whether it opens — decided once per main query:
     * YOOtheme asks at pre_get_posts, before the route's callback runs, and both must agree.
     * null when the URL is no kit route.
     *
     * @return array{route: string, kit: ?array, admitted: bool}|null
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
     * Recognised by the round trip through core's own url(): the URL this route builds for the request's
     * slug is the URL requested, or this is no request to it.
     *
     * @return array{route: string, kit: ?array, admitted: bool}|null
     */
    private function decide(WP_Query $query): ?array
    {
        // No page route matched this URL, so it is none of ours: no round trip to make.
        if ($query->get('ntdst_page') === '') {
            return null;
        }

        $slug = $query->get('ntdst_p_slug');
        $slug = is_scalar($slug) ? (string) $slug : '';
        $requested = trailingslashit(home_url((string) ($GLOBALS['wp']->request ?? '')));

        foreach (self::ROUTES as $route => $tail) {
            $params = str_contains($tail, ':slug') ? ['slug' => $slug] : [];

            if (($params === [] || $slug !== '') && $this->url($tail, $params) === $requested) {
                $kit = $this->kit($route, $params);

                return ['route' => $route, 'kit' => $kit, 'admitted' => $kit !== null && $this->gate($kit)];
            }
        }

        return null;
    }

    /**
     * The page route's kit in this request's language, with its own URLs; null on a download route.
     *
     * @param array<string, string> $params
     * @return array<string, mixed>|null
     */
    private function kit(string $route, array $params): ?array
    {
        $lang = (string) ntdst_pages()->language();
        $kit = match ($route) {
            'company'    => $this->model->forCompany($lang),
            'production' => $this->model->forProduction($params['slug'], $lang),
            default      => null,
        };

        return $kit === null ? null : $kit + [
            'url'          => $this->url(self::ROUTES[$route], $params),
            'download_url' => $this->url(self::ROUTES[$route . '_download'], $params),
        ];
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

    /**
     * One init callback, in ROUTES order: the downloads refuse until they are built, so their rules
     * already sit where the order needs them.
     */
    private function init(): void
    {
        $this->config = apply_filters('ntdst/service/presskit/config', self::DEFAULTS) + self::DEFAULTS;

        add_action('init', function (): void {
            ntdst_pages()
                ->path($this->paths(self::ROUTES['company']), fn(): string|false => $this->render())
                ->path($this->paths(self::ROUTES['company_download']), static fn(): bool => false)
                ->path($this->paths(self::ROUTES['production']), fn(): string|false => $this->render())
                ->path($this->paths(self::ROUTES['production_download']), static fn(): bool => false);
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
