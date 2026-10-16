<?php

declare(strict_types=1);

namespace Atelier\Profile;

use Atelier\Support\Languages;
use ntdst\baseline\services\polylang\Translation\PlusRequest;
use WP_Post;

defined('ABSPATH') || exit;

/**
 * The company profile: one record per site, opened from one menu entry.
 * The record is created on the first visit by someone who may edit others'
 * posts of the type (or by WP-CLI on deploy) — never on a front-end request.
 */
final class ProfileService
{
    private const MENU_SLUG = 'company-profile';

    private const DEFAULTS = [
        'labels' => ['name' => 'Profile', 'singular_name' => 'Profile'],
    ];

    private array $config;

    /** The Dutch profile, read once per request: `[$profile]`, null until read. */
    private ?array $dutch = null;

    public function __construct(private readonly ProfileRepository $repository)
    {
        $this->init();
    }

    /** The profile's id, created — published, in the default language — when none exists and the caller may. */
    public static function ensure(): ?int
    {
        $service = ntdst_get(self::class);
        $id = $service->repository->dutchId();

        if ($id !== null) {
            return $id;
        }

        $cli = defined('WP_CLI') && WP_CLI;

        if (!current_user_can(self::capability()) && !$cli) {
            return null;
        }

        $created = $service->repository->createDutch(get_bloginfo('name'));

        if (is_wp_error($created)) {
            $reason = 'company profile not created: ' . $created->get_error_message();
            ntdst_log('atelier')->error($reason);

            // The deploy hook's only output: WP_CLI::error() prints the reason and exits 1.
            if ($cli) {
                \WP_CLI::error($reason);
            }

            return null;
        }

        return $created;
    }

    /** Editors and up: the record is the site's Organization, not one author's post. */
    private static function capability(): string
    {
        return get_post_type_object(ProfileModel::POST_TYPE)?->cap->edit_others_posts ?? 'edit_others_posts';
    }

    public function menu(): void
    {
        $label = $this->config['labels']['name'];

        add_menu_page($label, $label, self::capability(), self::MENU_SLUG, '__return_null', 'dashicons-id-alt');
    }

    /** The menu page's load hook: opens the record's edit screen. No nonce: an idempotent create, behind the capability. */
    public function open(): void
    {
        if (!current_user_can(self::capability())) {
            return;
        }

        $this->redirectTo(self::ensure() ?? self::refuse());
    }

    /** load-post-new.php: a second profile is refused; Polylang's "+" still makes a copy. */
    public function refuseSecond(): void
    {
        if (($GLOBALS['typenow'] ?? '') !== ProfileModel::POST_TYPE || $this->isPlus()) {
            return;
        }

        $this->redirectTo(self::ensure() ?? self::refuse());
    }

    private static function refuse(): never
    {
        wp_die(esc_html__('The profile could not be opened.', 'atelier'), '', ['response' => 403]);
    }

    /** pre_trash_post and pre_delete_post: the Dutch record stays; its copies may go. */
    public function keepDutch(mixed $check, WP_Post $post): mixed
    {
        if ($post->post_type === ProfileModel::POST_TYPE && (int) $post->ID === $this->repository->dutchId()) {
            return false;
        }

        return $check;
    }

    /** ntdst/baseline/schema/main_entity, inside wp_head: the Dutch profile fills baseline's Organization (R-1). */
    public function fillEntity(array $entity): array
    {
        $profile = $this->dutchProfile();

        return $profile === null ? $entity : array_merge($entity, self::organization($profile));
    }

    /** ntdst/baseline/schema/website: built from the unfiltered entity, so the page names one Organization. */
    public function fillWebsite(array $website): array
    {
        if (!is_array($website['author'] ?? null)) {
            return $website;
        }

        $website['author'] = $website['copyrightHolder'] = $this->fillEntity($website['author']);

        return $website;
    }

    /** ntdst/baseline/seo/meta: the press photo, when the page brings no og:image of its own. */
    public function fillOgImage(array $tags): array
    {
        // Read as SeoService reads it (default true): a site with og_tags off gets no lone og:image.
        if (!(apply_filters('ntdst/baseline/seo/config', [])['og_tags'] ?? true)) {
            return $tags;
        }

        foreach ($tags as $tag) {
            if (is_string($tag) && str_contains($tag, 'property="og:image"')) {
                return $tags;
            }
        }

        $url = self::fileUrl($this->dutchProfile()['press_photo'] ?? null);

        if ($url !== '') {
            $tags[] = '<meta property="og:image" content="' . esc_url($url) . '">';
        }

        return $tags;
    }

    /** @return array<string, mixed> the profile's filled values as Organization properties; an empty one is left out */
    private static function organization(array $profile): array
    {
        $filled = static fn(mixed $value): bool => $value !== '' && $value !== [];
        $address = array_filter([
            'streetAddress'   => $profile['address']['street'],
            'postalCode'      => $profile['address']['postcode'],
            'addressLocality' => $profile['address']['city'],
            'addressCountry'  => $profile['address']['country'],
        ], $filled);

        return array_filter([
            'name'        => $profile['name'],
            'logo'        => self::fileUrl($profile['logo']),
            'description' => wp_strip_all_tags($profile['bio_short']),
            'sameAs'      => array_values($profile['social']),
            'address'     => $address === [] ? [] : ['@type' => 'PostalAddress'] + $address,
            // The company's own address; a contact row names a person and stays off the public page.
            'email'       => $profile['email'],
        ], $filled);
    }

    private static function fileUrl(?int $attachment): string
    {
        return $attachment === null ? '' : (string) wp_get_attachment_url($attachment);
    }

    /** @return array<string, mixed>|null */
    private function dutchProfile(): ?array
    {
        $this->dutch ??= [$this->repository->current(Languages::defaultFor(ProfileModel::POST_TYPE))];

        return $this->dutch[0];
    }

    /** A write to any profile record, or its meta, ends the remembered read. */
    public function forget(int $id): void
    {
        if (get_post_type($id) === ProfileModel::POST_TYPE) {
            $this->dutch = null;
        }
    }

    public function forgetMeta(mixed $metaIds, int $id): void
    {
        $this->forget($id);
    }

    /** A type Polylang does not translate has no copies, so nothing on it is a "+". */
    private function isPlus(): bool
    {
        return Languages::defaultFor(ProfileModel::POST_TYPE) !== null && PlusRequest::fromGlobals(ProfileModel::POST_TYPE) !== null;
    }

    private function redirectTo(int $id): never
    {
        ntdst_response()->redirect(admin_url('post.php?post=' . $id . '&action=edit'));
    }

    private function init(): void
    {
        $this->config = apply_filters('ntdst/service/profile/config', self::DEFAULTS);

        ProfileModel::register($this->config);

        add_action('admin_menu', [$this, 'menu']);
        add_action('load-toplevel_page_' . self::MENU_SLUG, [$this, 'open']);
        add_filter('pre_trash_post', [$this, 'keepDutch'], 10, 2);
        add_filter('pre_delete_post', [$this, 'keepDutch'], 10, 2);
        add_action('load-post-new.php', [$this, 'refuseSecond']);
        add_filter('ntdst/baseline/schema/main_entity', [$this, 'fillEntity']);
        add_filter('ntdst/baseline/schema/website', [$this, 'fillWebsite']);
        add_filter('ntdst/baseline/seo/meta', [$this, 'fillOgImage']);
        add_action('save_post', [$this, 'forget']);
        add_action('delete_post', [$this, 'forget']);
        foreach (['added_post_meta', 'updated_post_meta', 'deleted_post_meta'] as $hook) {
            add_action($hook, [$this, 'forgetMeta'], 10, 2);
        }

        add_action('after_setup_theme', function (): void {
            if (!function_exists('YOOtheme\app')) {
                return;
            }

            require_once __DIR__ . '/yootheme.php';
            register_profile_source($this->config);
        }, 30);
    }
}
