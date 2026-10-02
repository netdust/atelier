<?php

declare(strict_types=1);

namespace Atelier\Profile;

use ntdst\baseline\services\polylang\Translation\PlusRequest;
use WP_Post;

defined('ABSPATH') || exit;

/**
 * The company profile: one record per site, opened from one menu entry.
 * The record is created on the first visit by someone who may edit posts
 * (or by WP-CLI on deploy) — never on a front-end request.
 */
final class ProfileService
{
    public const MENU_SLUG = 'company-profile';

    private const DEFAULTS = [
        'labels' => ['name' => 'Profile', 'singular_name' => 'Profile'],
    ];

    private array $config;

    public function __construct()
    {
        $this->init();
    }

    /** The profile's id, created — published, in the default language — when none exists and the caller may. */
    public static function ensure(): ?int
    {
        $repository = ntdst_get(ProfileRepository::class);
        $id = $repository->dutchId();

        if ($id !== null) {
            return $id;
        }

        if (!current_user_can('edit_posts') && !(defined('WP_CLI') && WP_CLI)) {
            return null;
        }

        $created = $repository->createDutch(get_bloginfo('name'));

        if (is_wp_error($created)) {
            ntdst_log('atelier')->error('company profile not created: ' . $created->get_error_message());

            return null;
        }

        return $created;
    }

    public function menu(): void
    {
        $label = $this->config['labels']['name'];

        add_menu_page($label, $label, 'edit_posts', self::MENU_SLUG, '__return_null', 'dashicons-id-alt');
    }

    /** The menu page's load hook: opens the record's edit screen. No nonce: an idempotent create, behind edit_posts. */
    public function open(): void
    {
        if (!current_user_can('edit_posts')) {
            return;
        }

        $id = self::ensure();

        if ($id !== null) {
            $this->redirectTo($id);
        }
    }

    /** load-post-new.php: a second profile is refused; Polylang's "+" still makes a copy. */
    public function refuseSecond(): void
    {
        if (($GLOBALS['typenow'] ?? '') !== ProfileModel::POST_TYPE || $this->isPlus()) {
            return;
        }

        $id = self::ensure();

        if ($id === null) {
            wp_die(esc_html__('The profile could not be opened.', 'atelier'), '', ['response' => 403]);
        }

        $this->redirectTo($id);
    }

    /** pre_trash_post: the Dutch record stays; its copies may go. */
    public function keepDutch(mixed $check, WP_Post $post): mixed
    {
        if ($post->post_type === ProfileModel::POST_TYPE && (int) $post->ID === ntdst_get(ProfileRepository::class)->dutchId()) {
            return false;
        }

        return $check;
    }

    /** A type Polylang does not translate has no copies, so nothing on it is a "+". */
    private function isPlus(): bool
    {
        return ProfileRepository::translatedLanguage() !== null && PlusRequest::fromGlobals(ProfileModel::POST_TYPE) !== null;
    }

    private function redirectTo(int $id): never
    {
        wp_safe_redirect(admin_url('post.php?post=' . $id . '&action=edit'));
        exit;
    }

    private function init(): void
    {
        $this->config = apply_filters('ntdst/service/profile/config', self::DEFAULTS);

        ProfileModel::register($this->config);

        add_action('admin_menu', [$this, 'menu']);
        add_action('load-toplevel_page_' . self::MENU_SLUG, [$this, 'open']);
        add_filter('pre_trash_post', [$this, 'keepDutch'], 10, 2);
        add_action('load-post-new.php', [$this, 'refuseSecond']);
    }
}
