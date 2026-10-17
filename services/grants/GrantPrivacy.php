<?php

declare(strict_types=1);

namespace Atelier\Grants;

use WP_Post;

defined('ABSPATH') || exit;

/**
 * WordPress's personal-data tools (Tools → Export and Erase Personal Data) for access grants: a grant
 * holds the address it was issued to. The export lists a mailbox's grants and never a token; the erase
 * revokes each and anonymises its address, keeping the record with no one named. Both page through
 * the grants as WordPress's own exporters page through comments.
 */
final class GrantPrivacy
{
    private const KEY = 'atelier-access-grants';

    private const PER_PAGE = 50;

    public function __construct(private readonly GrantRepository $grants)
    {
    }

    public function register(): void
    {
        add_filter('wp_privacy_personal_data_exporters', function (array $exporters): array {
            $exporters[self::KEY] = ['exporter_friendly_name' => __('Access links', 'atelier'), 'callback' => $this->export(...)];

            return $exporters;
        });
        add_filter('wp_privacy_personal_data_erasers', function (array $erasers): array {
            $erasers[self::KEY] = ['eraser_friendly_name' => __('Access links', 'atelier'), 'callback' => $this->erase(...)];

            return $erasers;
        });
    }

    /** @return array{data: list<array<string, mixed>>, done: bool} */
    private function export(string $email, int $page = 1): array
    {
        $found = $this->grants->forRecipient($email, max(1, $page), self::PER_PAGE);

        return ['data' => array_map(self::item(...), $found['grants']), 'done' => !$found['more']];
    }

    /**
     * Always the first page: an erased grant no longer carries the address, so the next call finds
     * the rest. A batch that erased none is the last, or a write that keeps failing would loop.
     *
     * @return array{items_removed: bool, items_retained: bool, messages: list<string>, done: bool}
     */
    private function erase(string $email, int $page = 1): array
    {
        $found = $this->grants->forRecipient($email, 1, self::PER_PAGE);
        $removed = 0;
        $messages = [];

        foreach ($found['grants'] as $grant) {
            $erased = $this->grants->anonymise((int) $grant->ID);

            if (is_wp_error($erased)) {
                $messages[] = sprintf(__('Access link %1$d kept its address: %2$s', 'atelier'), (int) $grant->ID, $erased->get_error_message());

                continue;
            }

            $removed++;
        }

        return [
            'items_removed'  => $removed > 0,
            'items_retained' => $messages !== [],
            'messages'       => $messages,
            'done'           => !$found['more'] || $removed === 0,
        ];
    }

    /** @return array<string, mixed> one grant as an export item: what it opened, and how it was used */
    private static function item(WP_Post $grant): array
    {
        $fields = (array) $grant->fields;
        $content = absint($fields['content_id'] ?? 0);
        $value = static fn(string $name, mixed $value): array => ['name' => $name, 'value' => (string) $value];

        return [
            'group_id'    => self::KEY,
            'group_label' => __('Access links', 'atelier'),
            'item_id'     => self::KEY . '-' . (int) $grant->ID,
            'data'        => [
                // 0 would be the global post's title.
                $value(__('Opens', 'atelier'), $content > 0 ? get_the_title($content) : ''),
                $value(__('Language', 'atelier'), $fields['lang'] ?? ''),
                $value(__('Created (UTC)', 'atelier'), $grant->post_date_gmt),
                $value(__('Last sent (UTC)', 'atelier'), $fields['last_sent'] ?? ''),
                $value(__('Uses', 'atelier'), absint($fields['access_count'] ?? 0)),
                $value(__('Status', 'atelier'), $fields['status'] ?? ''),
            ],
        ];
    }
}
