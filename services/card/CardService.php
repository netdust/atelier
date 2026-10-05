<?php

declare(strict_types=1);

namespace Atelier\Card;

defined('ABSPATH') || exit;

/**
 * A business card per core-team member at /card/{slug}/ — a standalone page
 * outside the theme, harvested from daan. Any other slug is refused alike,
 * so the 404 never tells whether a person exists.
 */
class CardService
{
    public const PATH = '/card';

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

        $this->emitHeaders(['X-Robots-Tag: noindex, nofollow']);

        return $template;
    }

    /** What the template needs beside the card: labels, its own URL, its assets and language. */
    private function page(array $card): array
    {
        return [
            'labels' => $this->config['labels'],
            'url'    => home_url(trailingslashit(self::PATH . '/' . $card['slug'])),
            'lang'   => self::language(),
            'css'    => $this->assetUrl('card.css'),
            'js'     => $this->assetUrl('card.js'),
        ];
    }

    /** The card is in the site's default language, whatever the URL prefix (R-2). */
    private static function language(): string
    {
        $locale = function_exists('pll_default_language') ? pll_default_language('locale') : false;

        return str_replace('_', '-', is_string($locale) && $locale !== '' ? $locale : get_locale());
    }

    /** Versioned by filemtime() so an edit invalidates itself. */
    private function assetUrl(string $file): string
    {
        return add_query_arg('ver', (string) filemtime(__DIR__ . '/assets/' . $file), plugins_url('assets/' . $file, __FILE__));
    }

    /** @param list<string> $headers complete header lines */
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

    private function init(): void
    {
        $config = apply_filters('ntdst/service/card/config', self::DEFAULTS);
        $config['labels'] = array_merge(self::DEFAULTS['labels'], (array) ($config['labels'] ?? []));
        $this->config = $config;

        add_action('init', function (): void {
            ntdst_pages()->path(self::PATH . '/:slug', fn(array $params) => $this->renderCard($params));
        });
    }
}
