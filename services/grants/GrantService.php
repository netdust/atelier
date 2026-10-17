<?php

declare(strict_types=1);

namespace Atelier\Grants;

use Atelier\PressKit\PressKitModel;
use Atelier\PressKit\PressKitService;
use Atelier\Profile\ProfileModel;
use Atelier\Profile\ProfileRepository;
use Atelier\Support\Asset;
use Atelier\Support\Languages;
use WP_Error;
use WP_Post;
use WP_Post_Type;
use WP_REST_Request;
use WP_REST_Response;

defined('ABSPATH') || exit;

/**
 * A shut press kit's personal links, managed from its record's edit screen: a panel lists them, and
 * issues, mails and revokes them through `atelier/v1/grants/{type}/…`. Harvested from daan's
 * AccessGrantService operator surface. The routes decide — the capability, the kit, the cap, and
 * WordPress's own cookie nonce before them; the panel's script only asks.
 */
class GrantService
{
    private const NAMESPACE = 'atelier/v1';

    private const HANDLE = 'atelier-grants';

    private const BOX = 'atelier_grants';

    /** The panel's columns, each a key of a row and of the labels. */
    private const COLUMNS = ['address', 'lang', 'created', 'sent', 'accesses', 'status'];

    /** What the script says after an answer; the rest of the labels are drawn by PHP. */
    private const SCRIPT_LABELS = ['issued', 'rejected', 'sent_count', 'failed', 'revoked_done', 'confirm_revoke', 'confirm_send_all'];

    private const DEFAULTS = [
        // Addresses one issue, or grants one send, may name (Threat model, attack 8; daan's 50).
        'max_batch_recipients' => 50,
        'labels'               => [
            'panel'            => 'Personal links',
            'address'          => 'Address',
            'lang'             => 'Language',
            'created'          => 'Created',
            'sent'             => 'Last sent',
            'accesses'         => 'Opened',
            'status'           => 'Status',
            'active'           => 'Active',
            'revoked'          => 'Revoked',
            'not_yet'          => 'not yet',
            'empty'            => 'No personal links for this press kit yet.',
            'unpublished'      => 'Publish first: a personal link opens a published press kit only.',
            'protected'        => 'Remove the password first: a personal link opens no password-protected press kit.',
            'addresses'        => 'Email addresses, separated by a comma or a new line',
            'issue'            => 'Create links',
            'send'             => 'Send',
            'send_all'         => 'Send all active links',
            'revoke'           => 'Revoke',
            'issued'           => 'Links created: %d.',
            'rejected'         => 'Not an address: %s.',
            'sent_count'       => 'Mails sent: %d.',
            'failed'           => 'Not sent: %d.',
            'revoked_done'     => 'Link revoked.',
            'confirm_revoke'   => 'Revoke this link? It stops working at once.',
            'confirm_send_all' => 'Mail every active link?',
            'unknown_kit'      => 'This press kit is not published.',
            'unknown_grant'    => 'No such link for this press kit.',
            'empty_batch'      => 'Enter at least one address.',
            'batch_too_large'  => 'At most %d at a time.',
        ],
        // {title}, {link} and {site}; one text, or one per language (R-2).
        'mail'                 => [
            'subject' => 'Press kit: {title}',
            'body'    => "Hello,\n\nHere is your personal link to the press kit of {title}:\n\n{link}\n\n"
                . "The link is meant for you, so please do not forward it.\n\n{site}\n",
        ],
    ];

    /** @var array<string, mixed> */
    private array $config;

    public function __construct(
        private readonly GrantRepository $grants,
        private readonly PressKitModel $kits,
        private readonly PressKitService $links,
        private readonly ProfileRepository $profiles,
    ) {
        $this->init();
    }

    /** `GrantService` would derive `grant`; the folder and the override key say `grants`. */
    public static function metadata(): array
    {
        return ['name' => 'grants'];
    }

    /**
     * R-1: whoever may edit others' grants — editors and administrators, under WordPress's own map,
     * read off the type and never written as a literal. Closed when the type or its capability is missing.
     */
    private static function mayManage(): bool
    {
        $type = get_post_type_object(GrantModel::POST_TYPE);
        $capability = $type instanceof WP_Post_Type ? ($type->cap->edit_others_posts ?? null) : null;

        return is_string($capability) && $capability !== '' && current_user_can($capability);
    }

    /**
     * One link per address on the list, each through the repository's issue() and its identity rule.
     * A refusal about one address is named back; one about the request stops it.
     */
    private function issue(string $type, WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $kit = $this->kits->holder($type, absint($request->get_param('post_id')));

        if ($kit === null) {
            return $this->refusal('unknown_kit', 404);
        }

        $addresses = self::addresses((string) $request->get_param('recipient_emails'));
        $refused = $this->batch(count($addresses));

        if ($refused !== null) {
            return $refused;
        }

        // Validated against the site's languages by the route; absent is "not chosen".
        $lang = $request->get_param('lang');
        $issued = [];
        $rejected = [];

        foreach ($addresses as $address) {
            $grant = $this->grants->issue($address, $type, $kit, is_string($lang) ? $lang : null);

            if ($grant instanceof WP_Post) {
                $issued[] = (int) $grant->ID;

                continue;
            }

            if ((int) (((array) $grant->get_error_data())['status'] ?? 500) !== 400) {
                return $grant;
            }

            $rejected[] = $address;
        }

        return new WP_REST_Response(['issued' => $issued, 'rejected' => $rejected, 'grants' => $this->rows($type, $kit)]);
    }

    /** Each named grant mailed to the address it was issued to — never one the request names. */
    private function send(string $type, WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $kit = $this->kits->holder($type, absint($request->get_param('post_id')));

        if ($kit === null) {
            return $this->refusal('unknown_kit', 404);
        }

        $ids = array_values(array_unique(array_filter(array_map('absint', (array) $request->get_param('grant_ids')))));
        $refused = $this->batch(count($ids));

        if ($refused !== null) {
            return $refused;
        }

        $sent = [];
        $failed = [];

        foreach ($ids as $id) {
            $grant = $this->grants->liveFor($id, $type, $kit);

            if ($grant === null || !$this->mail($type, $kit, $grant)) {
                $failed[] = $id;

                continue;
            }

            $sent[] = $id;
            $marked = $this->grants->markSent($grant);

            if (is_wp_error($marked)) {
                ntdst_log('access-grant')->error('Access link mailed; its send not recorded.', ['grant' => $id, 'reason' => $marked->get_error_message()]);
            }
        }

        return new WP_REST_Response(['sent' => $sent, 'failed' => $failed, 'grants' => $this->rows($type, $kit)]);
    }

    /**
     * The named kit's own link, revoked. The kit need not be published: shutting a link is always
     * safe, and a draft kit's links stay revocable.
     */
    private function revoke(string $type, WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $kit = $this->kits->dutchOf($type, absint($request->get_param('post_id')));
        $grant = $kit === null ? null : $this->grants->boundFor(absint($request->get_param('grant_id')), $type, $kit);

        if ($grant === null) {
            return $this->refusal('unknown_grant', 404);
        }

        $revoked = $this->grants->revoke((int) $grant->ID);

        if (is_wp_error($revoked)) {
            return $revoked;
        }

        return new WP_REST_Response(['revoked' => (int) $revoked->ID, 'grants' => $this->rows($type, $kit)]);
    }

    /** None, or more than the cap: refused before the first write or mail. */
    private function batch(int $count): ?WP_Error
    {
        $cap = (int) $this->config['max_batch_recipients'];

        if ($count === 0) {
            return $this->refusal('empty_batch', 400);
        }

        return $count > $cap ? $this->refusal('batch_too_large', 400, $cap) : null;
    }

    /**
     * R-2, R-5: the mail in the grant's language, its link the kit's page there when that copy is
     * published. Plain text, said so in its own header: `{title}` is entity-decoded into the body, so a
     * site's content-type filter must not make it HTML. The subject one line, whatever a title holds. A
     * reply goes to the company's own address, when its profile has one.
     */
    private function mail(string $type, int $kit, WP_Post $grant): bool
    {
        $fields = (array) $grant->fields;
        $lang = (string) ($fields['lang'] ?? '');
        $link = $this->links->link($type, $kit, $lang, (string) ($fields['token'] ?? ''));

        if ($link === null) {
            return false;
        }

        $words = [
            '{title}' => self::plain($link['title']),
            '{link}'  => $link['url'],
            '{site}'  => self::plain((string) get_bloginfo('name')),
        ];
        $subject = trim((string) preg_replace('/\s+/u', ' ', strtr($this->mailText('subject', $lang), $words)));

        $body = strtr($this->mailText('body', $lang), $words);

        return wp_mail((string) ($fields['recipient_email'] ?? ''), $subject, $body, $this->mailHeaders());
    }

    /** @return list<string> */
    private function mailHeaders(): array
    {
        $headers = ['Content-Type: text/plain; charset=UTF-8'];
        $replyTo = (string) ($this->profiles->current(Languages::defaultFor(ProfileModel::POST_TYPE))['email'] ?? '');

        // is_email() refuses a line break inside an address; the check stays here, where the header is written.
        if (is_email($replyTo) && strpbrk($replyTo, "\r\n") === false) {
            $headers[] = 'Reply-To: ' . $replyTo;
        }

        return $headers;
    }

    /** A configured mail text in `$lang`, or the first language's where it has none. */
    private function mailText(string $key, string $lang): string
    {
        $text = $this->config['mail'][$key];

        return (string) (is_array($text) ? ($text[$lang] ?? reset($text)) : $text);
    }

    private static function plain(string $text): string
    {
        return html_entity_decode(wp_strip_all_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * A pasted list, split on `;`, `,` and new lines. Duplicates stay: issue() answers a repeat with
     * the grant it already gave.
     *
     * @return list<string>
     */
    private static function addresses(string $pasted): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[;,\r\n]+/', $pasted) ?: []), 'strlen'));
    }

    /**
     * The kit's grants as the panel shows them: data only, every value drawn as text where it lands.
     *
     * @return list<array{id: int, address: string, lang: string, created: string, sent: string, accesses: int, status: string, live: bool}>
     */
    private function rows(string $type, int $kit): array
    {
        $rows = [];

        foreach ($this->grants->forContent($type, $kit) as $grant) {
            $fields = (array) $grant->fields;
            $live = GrantRepository::isLive($grant);
            $rows[] = [
                'id'       => (int) $grant->ID,
                'address'  => (string) ($fields['recipient_email'] ?? ''),
                'lang'     => strtoupper((string) ($fields['lang'] ?? '')),
                'created'  => self::when((string) $grant->post_date_gmt),
                'sent'     => self::when((string) ($fields['last_sent'] ?? '')) ?: $this->label('not_yet'),
                'accesses' => absint($fields['access_count'] ?? 0),
                'status'   => $this->label($live ? 'active' : 'revoked'),
                'live'     => $live,
            ];
        }

        return $rows;
    }

    /** A stored UTC time in the site's own format and zone; '' when there is none. */
    private static function when(string $utc): string
    {
        $time = $utc === '' ? false : strtotime($utc . ' UTC');

        return $time === false || $time <= 0 ? '' : (string) wp_date(get_option('date_format') . ' ' . get_option('time_format'), $time);
    }

    private function refusal(string $code, int $status, int|string ...$values): WP_Error
    {
        return new WP_Error($code, sprintf($this->label($code), ...$values), ['status' => $status]);
    }

    private function label(string $key): string
    {
        return (string) $this->config['labels'][$key];
    }

    /** The box, only for whoever may manage grants: its rows are recipients' addresses. */
    private function addPanel(string $type, mixed $post): void
    {
        if (!in_array($type, PressKitModel::TYPES, true) || !$post instanceof WP_Post || !self::mayManage()) {
            return;
        }

        // WordPress prints a box title as given (template.php:1384).
        $title = esc_html($this->label('panel'));

        add_meta_box(self::BOX, $title, fn(WP_Post $post) => $this->renderPanel($type, $post), $type, 'normal', 'default');
    }

    /** Every value escaped where it lands; the token never printed. */
    private function renderPanel(string $type, WP_Post $post): void
    {
        if (!self::mayManage()) {
            return;
        }

        $kit = $this->kits->holder($type, (int) $post->ID);

        if ($kit === null) {
            $note = $this->kits->isProtected($type, (int) $post->ID) ? 'protected' : 'unpublished';
            echo '<p class="description">' . esc_html($this->label($note)) . '</p>';

            return;
        }

        $rows = $this->rows($type, $kit);
        $live = array_filter($rows, static fn(array $row): bool => $row['live']);

        echo '<div class="atelier-grants" data-type="' . esc_attr($type) . '" data-kit="' . esc_attr((string) $kit) . '">';
        echo '<table class="widefat striped atelier-grants__table"><thead><tr>';

        foreach (self::COLUMNS as $column) {
            echo '<th scope="col">' . esc_html($this->label($column)) . '</th>';
        }

        echo '<td></td></tr></thead><tbody class="atelier-grants__rows">';

        foreach ($rows as $row) {
            $this->renderRow($row);
        }

        echo '</tbody></table>';
        echo '<p class="atelier-grants__empty"' . ($rows === [] ? '' : ' hidden') . '>' . esc_html($this->label('empty')) . '</p>';
        // The script draws every row it gets back from this one.
        echo '<template class="atelier-grants__template">';
        $this->renderRow(array_fill_keys(self::COLUMNS, '') + ['id' => 0, 'live' => true]);
        echo '</template>';
        $this->renderIssue($type, $live !== []);
        echo '<p class="atelier-grants__notice" role="status"></p></div>';
    }

    /** @param array<string, mixed> $row */
    private function renderRow(array $row): void
    {
        $hidden = $row['live'] ? '' : ' hidden';

        echo '<tr data-grant="' . esc_attr((string) $row['id']) . '">';

        foreach (self::COLUMNS as $column) {
            echo '<td class="atelier-grants__' . esc_attr($column) . '">' . esc_html((string) $row[$column]) . '</td>';
        }

        echo '<td class="atelier-grants__actions">'
            . '<button type="button" class="button button-small atelier-grants__send"' . $hidden . '>' . esc_html($this->label('send')) . '</button> '
            . '<button type="button" class="button-link button-link-delete atelier-grants__revoke"' . $hidden . '>'
            . esc_html($this->label('revoke')) . '</button></td></tr>';
    }

    /** The addresses, the link's language (R-2: the site's default chosen), and the two actions. */
    private function renderIssue(string $type, bool $hasLive): void
    {
        $default = Languages::defaultFor($type);

        echo '<p><label for="atelier-grants-addresses">' . esc_html($this->label('addresses')) . '</label>'
            . '<textarea id="atelier-grants-addresses" class="large-text atelier-grants__addresses" rows="2" autocomplete="off"></textarea></p><p>';

        if (Languages::codes() !== []) {
            echo '<label for="atelier-grants-lang">' . esc_html($this->label('lang')) . '</label> '
                . '<select id="atelier-grants-lang" class="atelier-grants__language">';

            foreach (Languages::codes() as $code) {
                echo '<option value="' . esc_attr($code) . '"' . selected($code, $default, false) . '>' . esc_html(strtoupper($code)) . '</option>';
            }

            echo '</select> ';
        }

        echo '<button type="button" class="button button-primary atelier-grants__issue">' . esc_html($this->label('issue')) . '</button> '
            . '<button type="button" class="button atelier-grants__send-all"' . ($hasLive ? '' : ' hidden') . '>'
            . esc_html($this->label('send_all')) . '</button></p>';
    }

    /** On a kit's edit screen, for a grant manager only; wp.apiFetch carries WordPress's own nonce. */
    private function enqueue(string $hook): void
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;

        if ($hook !== 'post.php' || !$screen instanceof \WP_Screen || !in_array($screen->post_type, PressKitModel::TYPES, true) || !self::mayManage()) {
            return;
        }

        wp_enqueue_script(self::HANDLE, Asset::url(__FILE__, 'assets/grants.js'), ['wp-api-fetch'], null, true);
        wp_enqueue_style(self::HANDLE, Asset::url(__FILE__, 'assets/grants.css'), [], null);
        wp_localize_script(self::HANDLE, 'atelierGrants', [
            'namespace' => self::NAMESPACE,
            'labels'    => array_intersect_key($this->config['labels'], array_flip(self::SCRIPT_LABELS)),
        ]);
    }

    /**
     * A route's parameters, typed so WordPress refuses a malformed request before the handler runs;
     * a link's language is one the site has, or none.
     *
     * @return array<string, array<string, mixed>>
     */
    private static function args(string $action): array
    {
        $kit = ['post_id' => ['type' => 'integer', 'required' => true]];

        return match ($action) {
            'issue'  => $kit + [
                'recipient_emails' => ['type' => 'string', 'required' => true],
                'lang'             => ['type' => 'string', 'validate_callback' => static fn(mixed $lang): bool => in_array($lang, Languages::codes(), true)],
            ],
            'send'   => $kit + ['grant_ids' => ['type' => 'array', 'items' => ['type' => 'integer'], 'required' => true]],
            default  => $kit + ['grant_id' => ['type' => 'integer', 'required' => true]],
        };
    }

    private function init(): void
    {
        $this->config = apply_filters('ntdst/service/grants/config', self::DEFAULTS) + self::DEFAULTS;
        $this->config['labels'] = (array) $this->config['labels'] + self::DEFAULTS['labels'];
        $this->config['mail'] = (array) $this->config['mail'] + self::DEFAULTS['mail'];

        // The one registrar of the records behind a kit's personal links, and of their addresses in
        // WordPress's personal-data tools.
        GrantModel::register();
        (new GrantPrivacy($this->grants))->register();

        // Behind the grant type's capability (R-1). A browser's request also needs WordPress's cookie
        // nonce: without it WordPress has made the caller a visitor before the permission is asked.
        $permission = static fn(): bool => self::mayManage();
        $handlers = ['issue' => $this->issue(...), 'send' => $this->send(...), 'revoke' => $this->revoke(...)];

        foreach (PressKitModel::TYPES as $type) {
            foreach ($handlers as $action => $handler) {
                ntdst_rest(self::NAMESPACE)->post(
                    "/grants/$type/$action",
                    static fn(WP_REST_Request $request): WP_REST_Response|WP_Error => $handler($type, $request),
                    ['permission' => $permission, 'args' => self::args($action)],
                );
            }
        }

        add_action('add_meta_boxes', fn(string $type, mixed $post) => $this->addPanel($type, $post), 10, 2);
        add_action('admin_enqueue_scripts', fn(string $hook) => $this->enqueue($hook));
    }
}
