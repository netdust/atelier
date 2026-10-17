<?php

declare(strict_types=1);

namespace Atelier\Card;

use Atelier\Support\Asset;
use Atelier\Support\NoCache;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

defined('ABSPATH') || exit;

/**
 * A business card per core-team member at /card/{slug}/ — a standalone page
 * outside the theme, harvested from daan, installable as that person's app.
 * /card/ is the chooser. Any other slug is refused alike, so the 404 never
 * tells whether a person exists.
 */
class CardService
{
    public const PATH = '/card';

    private const NOINDEX = 'X-Robots-Tag: noindex, nofollow';

    /** The two standalone pages load only their own same-origin files, and the QR is inline SVG. */
    private const CSP = "Content-Security-Policy: default-src 'self'; img-src 'self' data:; base-uri 'none'; "
        . "form-action 'none'; frame-ancestors 'none'";

    /** The card's paper (card.css --paper): the installed app's splash and bars. */
    private const PAPER = '#f4f4f2';

    /** R-3: atelier's neutral icons, for a site without a Site Icon. */
    private const ICONS = [
        'icon-192.png'          => [192, 'any'],
        'icon-512.png'          => [512, 'any'],
        'icon-maskable-512.png' => [512, 'maskable'],
    ];

    /** SVG user units; CSS scales the code. */
    private const QR_SIZE = 300;

    /** ISO/IEC 18004's quiet zone of four modules: on the dark back the paper drawn here is the only one. */
    private const QR_MARGIN = 4;

    private const DEFAULTS = [
        'labels' => [
            'front'     => 'Front',
            'back'      => 'Back',
            'save'      => 'Save contact',
            'share'     => 'Share',
            'copy_link' => 'Link copied',
            'not_you'   => 'Not you?',
        ],
    ];

    private array $config;

    public function __construct(private readonly CardModel $model)
    {
        $this->init();
    }

    /** /card/ — the core team, each a link to their card; card.js remembers the choice. No team, no chooser. */
    public function renderChooser(): string|false
    {
        $team = $this->model->team();

        if ($team === []) {
            return false;
        }

        $people = array_map(static fn(array $person): array => $person + ['url' => self::url($person['slug'])], $team);
        $page = [
            'company' => $this->model->company()['name'] ?: get_bloginfo('name'),
            'lang'    => self::language(),
            'css'     => Asset::url(__FILE__, 'assets/card.css'),
            'js'      => Asset::url(__FILE__, 'assets/card.js'),
        ];
        $template = \NTDST_Template_Loader::page('chooser', ['people' => $people, 'page' => $page], [__DIR__ . '/templates']);

        if ($template === null) {
            return false;
        }

        $this->emitHeaders([self::NOINDEX, ...NoCache::headers(), self::CSP]);

        return $template;
    }

    /** The route: the card's template, or false — core's refusal, a 404. */
    public function renderCard(array $params): string|false
    {
        $card = $this->model->forSlug((string) ($params['slug'] ?? ''));

        if ($card === null) {
            return false;
        }

        $template = \NTDST_Template_Loader::page('card', ['card' => $card, 'page' => $this->page($card)], [__DIR__ . '/templates']);

        if ($template === null) {
            return false;
        }

        $this->emitHeaders([self::NOINDEX, ...NoCache::headers(), self::CSP]);

        return $template;
    }

    /** /card/{slug}/contact.vcf — the full vCard, saved as a file. */
    public function streamVCard(array $params): ?false
    {
        return $this->streamRecord($params, false, 'attachment');
    }

    /** /card/{slug}/share.vcf — the compact vCard, inline so a phone offers "Add contact". */
    public function streamShareVCard(array $params): ?false
    {
        return $this->streamRecord($params, true, 'inline');
    }

    /** null once the record is sent — core then ends the request; false refuses, as the card does. */
    private function streamRecord(array $params, bool $compact, string $disposition): ?false
    {
        $card = $this->model->forSlug((string) ($params['slug'] ?? ''));

        if ($card === null) {
            return false;
        }

        $body = VCardBuilder::build($card, $compact);

        // Not ntdst_response()->download(): it exits, and these seams are what the tests capture.
        $this->emitHeaders([
            ...\NTDST_Response::downloadHeaders(strlen($body), self::vcardFilename($card['name']), 'text/vcard; charset=utf-8', $disposition),
            ...NoCache::headers(),
            self::NOINDEX,
        ]);
        $this->emitBody($body);

        return null;
    }

    /** /card/{slug}/manifest.webmanifest — this person's app: it opens at their card and stays in it (P5). */
    public function streamManifest(array $params): ?false
    {
        $card = $this->model->forSlug((string) ($params['slug'] ?? ''));

        if ($card === null) {
            return false;
        }

        $start = self::scope($card['slug']);

        $this->emitFile('application/manifest+json; charset=utf-8', (string) wp_json_encode([
            'id'               => $start,
            'name'             => $card['name'],
            'short_name'       => $card['name'],
            'start_url'        => $start,
            'scope'            => $start,
            'display'          => 'standalone',
            'orientation'      => 'portrait',
            'background_color' => self::PAPER,
            'theme_color'      => self::PAPER,
            'icons'            => $this->icons(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return null;
    }

    /**
     * /card/{slug}/sw/ — the offline worker, handed this card's own URLs; the cache name changes whenever
     * they do. Served below the scope it claims, so Service-Worker-Allowed widens it to the card.
     */
    public function streamServiceWorker(array $params): ?false
    {
        $card = $this->model->forSlug((string) ($params['slug'] ?? ''));

        if ($card === null) {
            return false;
        }

        $urls = $this->offlineUrls($card);
        $script = (string) file_get_contents(__DIR__ . '/assets/card-sw.js');
        // A colon never occurs in a slug, so one card's prefix is never the start of another's.
        $prefix = 'atelier-card:' . $card['slug'] . ':';
        $worker = ['prefix' => $prefix, 'cache' => $prefix . substr(md5(implode("\n", $urls) . $script), 0, 12), 'urls' => $urls];

        $this->emitFile(
            'text/javascript; charset=utf-8',
            'const CARD = ' . wp_json_encode($worker, JSON_UNESCAPED_SLASHES) . ";\n" . $script,
            'Service-Worker-Allowed: ' . self::scope($card['slug']),
        );

        return null;
    }

    /** The QR's inline <svg>, or null when the encoder throws — the back then shows no code. */
    private static function qrSvg(string $payload): ?string
    {
        try {
            $renderer = new ImageRenderer(new RendererStyle(self::QR_SIZE, self::QR_MARGIN), new SvgImageBackEnd());
            $svg = (new Writer($renderer))->writeString($payload, 'UTF-8');
        } catch (\Throwable) {
            return null;
        }

        // The writer emits a standalone document; its XML prolog is invalid inside an HTML body.
        $start = strpos($svg, '<svg');

        return $start === false ? null : substr($svg, $start);
    }

    /** The stem is sanitised before `.vcf` is joined: sanitize_file_name() would mangle an inner extension. */
    private static function vcardFilename(string $name): string
    {
        $stem = sanitize_file_name($name);
        $filename = str_replace(["\r", "\n", "\0", '"'], '', sanitize_file_name(($stem === '' ? 'contact' : $stem) . '.vcf'));

        return str_ends_with($filename, '.vcf') && $filename !== '.vcf' ? $filename : 'contact.vcf';
    }

    /**
     * What the template needs beside the card: labels, the share link, the QR — the compact vCard, so a phone
     * camera offers "add contact", and a tap on it opens that record — the install, its assets and language.
     */
    private function page(array $card): array
    {
        return [
            'labels'     => $this->config['labels'],
            'share'      => $card['share_url'] !== '' ? $card['share_url'] : self::url($card['slug']),
            'qr'         => self::qrSvg(VCardBuilder::build($card, true)),
            'qr_vcard'   => self::url($card['slug'], 'share.vcf'),
            'vcard'      => self::url($card['slug'], 'contact.vcf'),
            'vcard_name' => self::vcardFilename($card['name']),
            'manifest'   => self::url($card['slug'], 'manifest.webmanifest'),
            'touch_icon' => $this->touchIcon(),
            'worker'     => self::url($card['slug'], 'sw'),
            'scope'      => self::scope($card['slug']),
            'chooser'    => home_url(trailingslashit(self::PATH)),
            'lang'       => self::language(),
            'css'        => Asset::url(__FILE__, 'assets/card.css'),
            'js'         => Asset::url(__FILE__, 'assets/card.js'),
        ];
    }

    /** A card URL in the trailing-slash form WordPress would 301 to: a worker fetch refuses a redirect. */
    private static function url(string $slug, string $file = ''): string
    {
        return home_url(trailingslashit(self::PATH . '/' . $slug . ($file === '' ? '' : '/' . $file)));
    }

    /** The card's path: the installed app's start and scope, and the worker's. */
    private static function scope(string $slug): string
    {
        return wp_make_link_relative(self::url($slug));
    }

    /**
     * R-3: the Site Icon when one is set, else atelier's neutral icons.
     *
     * @return list<array<string, string>>
     */
    private function icons(): array
    {
        if (has_site_icon()) {
            return [self::icon(get_site_icon_url(192), 192, 'any'), self::icon(get_site_icon_url(512), 512, 'any')];
        }

        $icons = [];

        foreach (self::ICONS as $file => [$size, $purpose]) {
            $icons[] = self::icon(Asset::url(__FILE__, 'assets/icons/' . $file), $size, $purpose);
        }

        return $icons;
    }

    /** @return array<string, string> */
    private static function icon(string $src, int $size, string $purpose): array
    {
        $type = wp_check_filetype((string) wp_parse_url($src, PHP_URL_PATH))['type'];

        return ['src' => $src, 'sizes' => "{$size}x{$size}", 'purpose' => $purpose] + ($type ? ['type' => $type] : []);
    }

    /** R-3: iOS takes the home-screen icon from the page, never from the manifest. */
    private function touchIcon(): string
    {
        return has_site_icon() ? get_site_icon_url(180) : Asset::url(__FILE__, 'assets/icons/apple-touch-icon.png');
    }

    /** @return list<string> the card page and what it needs to draw itself — this card's own URLs, same-origin only */
    private function offlineUrls(array $card): array
    {
        $urls = [
            self::url($card['slug']),
            Asset::url(__FILE__, 'assets/card.css'),
            Asset::url(__FILE__, 'assets/card.js'),
            (string) $card['photo'],
            (string) $card['company']['logo'],
            $this->touchIcon(),
            ...array_column($this->icons(), 'src'),
            self::url($card['slug'], 'contact.vcf'),
            self::url($card['slug'], 'share.vcf'),
        ];
        $host = wp_parse_url(home_url(), PHP_URL_HOST);

        return array_values(array_filter($urls, static fn(string $url): bool => $url !== '' && wp_parse_url($url, PHP_URL_HOST) === $host));
    }

    /**
     * The document's `lang`: the default language's LOCALE, whatever the URL prefix (R-2). Not
     * Languages::defaultFor(), which answers which record to read, by Polylang slug.
     */
    private static function language(): string
    {
        $locale = function_exists('pll_default_language') ? pll_default_language('locale') : false;

        return str_replace('_', '-', is_string($locale) && $locale !== '' ? $locale : get_locale());
    }

    /** A whole file the browser fetches itself — never cached stale, never indexed. */
    private function emitFile(string $type, string $body, string ...$headers): void
    {
        $this->emitHeaders([
            'Content-Type: ' . $type,
            'Content-Length: ' . strlen($body),
            ...NoCache::headers(),
            self::NOINDEX,
            ...$headers,
        ]);
        $this->emitBody($body);
    }

    /**
     * Each line replaces a header of its name. Baseline's cache_headers and core's route dispatcher both run on
     * template_redirect at 10, baseline registered first, so its public max-age and Expires are the ones replaced.
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
     * Templates resolve through page()'s $extraPaths, never the theme's: a theme cannot override the card
     * (plan departure 1).
     */
    private function init(): void
    {
        $config = apply_filters('ntdst/service/card/config', self::DEFAULTS);
        $config['labels'] = array_merge(self::DEFAULTS['labels'], (array) ($config['labels'] ?? []));
        $this->config = $config;

        add_action('init', function (): void {
            ntdst_pages()
                ->path(self::PATH, fn() => $this->renderChooser())
                ->path(self::PATH . '/:slug', fn(array $params) => $this->renderCard($params))
                ->path(self::PATH . '/:slug/contact.vcf', fn(array $params) => $this->streamVCard($params))
                ->path(self::PATH . '/:slug/share.vcf', fn(array $params) => $this->streamShareVCard($params))
                ->path(self::PATH . '/:slug/manifest.webmanifest', fn(array $params) => $this->streamManifest($params))
                ->path(self::PATH . '/:slug/sw', fn(array $params) => $this->streamServiceWorker($params));
        });
    }
}
