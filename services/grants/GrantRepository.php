<?php

declare(strict_types=1);

namespace Atelier\Grants;

use WP_Error;
use WP_Post;

defined('ABSPATH') || exit;

/**
 * The one place `access_grant` data is read or written: mint, verify, issue, revoke, record. Harvested
 * from daan's AccessGrantService. Who may issue or revoke is the caller's question, asked before it
 * calls; whether a token opens a kit is answered here, and only here.
 */
final class GrantRepository
{
    private const STATUS_ACTIVE = 'active';

    private const STATUS_REVOKED = 'revoked';

    /** 256 bits: the keyspace a brute force is priced against (Threat model, attack 1). */
    private const TOKEN_BYTES = 32;

    /** A second row holding one token is broken state, not a collision; the cap keeps it bounded. */
    private const MAX_TOKEN_CANDIDATES = 25;

    private const MAX_GRANTS_PER_CONTENT = 100;

    private const LOCK_WAIT_SECONDS = 5;

    private const LOG = 'access-grant';

    /**
     * 64 lowercase hex characters from the platform's CSPRNG. random_bytes()'s exception is never
     * caught here: a weaker token is worse than none.
     */
    public static function mint(): string
    {
        return bin2hex(random_bytes(self::TOKEN_BYTES));
    }

    /**
     * The live grant `$token` opens `$contentType` `$contentId` with, or null — for every refusal
     * alike, and it never throws, so a refusal's shape tells nothing. The token arrives as the request
     * sent it: unslashed once here and never sanitised, since a sanitiser deletes bytes and widens
     * what matches. The query only narrows (its collation ignores case and trailing spaces); the
     * byte comparison decides, on a fresh read of the row.
     */
    public function verify(string $token, string $contentType, int $contentId): ?WP_Post
    {
        $presented = wp_unslash($token);

        if ($presented === '' || $contentType === '' || $contentId <= 0) {
            return null;
        }

        try {
            $model = ntdst_data()->get(GrantModel::POST_TYPE);
            $candidates = $model
                ->where('post_status', 'publish')
                ->where('token', $presented)
                ->limit(self::MAX_TOKEN_CANDIDATES)
                ->get();

            foreach ($candidates as $candidate) {
                $grant = (int) ($candidate['id'] ?? 0) > 0 ? $model->find((int) $candidate['id']) : null;

                if ($grant instanceof WP_Post && self::admits($grant, $presented, $contentType, $contentId)) {
                    return $grant;
                }
            }
        } catch (\Throwable $e) {
            ntdst_log(self::LOG)->error('Access grant check failed; refused.', [
                'content_type' => $contentType,
                'content_id'   => $contentId,
                'reason'       => $e->getMessage(),
            ]);

            return null;
        }

        ntdst_log(self::LOG)->warning('Access grant refused.', ['content_type' => $contentType, 'content_id' => $contentId]);

        return null;
    }

    /**
     * One live grant for `$email` on `$contentType` `$contentId`, in `$lang` (the site's default when
     * null). One mailbox is one grant, so the address is kept in lower case (Ruling M-1). Issuing again
     * while one is live returns it with its token — a second token would be one nobody revokes — in the
     * `$lang` named this time, if one is (Ruling M-2). The address and the kit are held while that is
     * checked and written, so two editors issuing together mint once; without the hold, nothing is.
     */
    public function issue(string $email, string $contentType, int $contentId, ?string $lang = null): WP_Post|WP_Error
    {
        $typed = trim(wp_unslash($email));
        $address = sanitize_email($typed);

        // Exactly what was typed, or nothing: sanitize_email() can turn junk into another valid address.
        if ($address === '' || $address !== $typed || !is_email($address)) {
            return new WP_Error('invalid_recipient', 'A deliverable recipient address is required.', ['status' => 400]);
        }

        $address = strtolower($address);

        if ($contentType === '' || sanitize_key($contentType) !== $contentType || $contentId <= 0) {
            return new WP_Error('invalid_content', 'A grant names the type and the id of what it opens.', ['status' => 400]);
        }

        $named = $lang !== null;
        $lang = self::language($lang);

        if ($lang === null) {
            return new WP_Error('invalid_language', 'A grant is issued in a language the site has.', ['status' => 400]);
        }

        $lock = $this->hold($address, $contentType, $contentId);

        if ($lock === null) {
            return new WP_Error('issue_contended', 'Could not issue an access link right now.', ['status' => 503]);
        }

        try {
            $live = $this->live($address, $contentType, $contentId);

            if ($live === null) {
                return $this->create($address, $contentType, $contentId, $lang);
            }

            return $named ? $this->inLanguage($live, $lang) : $live;
        } finally {
            $this->release($lock);
        }
    }

    /**
     * The grant, revoked. The record stays, as who was let in and when. A grant already not live is
     * returned as it is, writing nothing: a second click is no error.
     */
    public function revoke(int $grantId): WP_Post|WP_Error
    {
        if ($grantId <= 0) {
            return new WP_Error('invalid_grant', 'A grant id is required.', ['status' => 400]);
        }

        $model = ntdst_data()->get(GrantModel::POST_TYPE);
        $grant = $model->find($grantId, 'any');

        if (is_wp_error($grant) || ($grant->fields['status'] ?? '') !== self::STATUS_ACTIVE) {
            return $grant;
        }

        return $model->update($grantId, ['status' => self::STATUS_REVOKED]);
    }

    /**
     * One use of a grant verify() admitted. It decides nothing, and never fails the request: an unrecorded
     * use is logged. Its log lines name the grant and the kit, never the address, the token or an IP (D4).
     */
    public function recordAccess(WP_Post $grant): void
    {
        $fields = (array) ($grant->fields ?? []);

        try {
            $written = ntdst_data()->get(GrantModel::POST_TYPE)->updateMetaBatch((int) $grant->ID, [
                'access_count'  => absint($fields['access_count'] ?? 0) + 1,
                'last_accessed' => current_time('mysql', true),
            ]);
            $reason = $written ? '' : 'the meta write was refused';
        } catch (\Throwable $e) {
            $written = false;
            $reason = $e->getMessage();
        }

        if (!$written) {
            ntdst_log(self::LOG)->error('Access grant use could not be recorded.', ['grant' => (int) $grant->ID, 'reason' => $reason]);

            return;
        }

        ntdst_log(self::LOG)->info('Access grant used.', [
            'grant'        => (int) $grant->ID,
            'content_type' => (string) ($fields['content_type'] ?? ''),
            'content_id'   => (int) ($fields['content_id'] ?? 0),
        ]);
    }

    /** The four questions, each answered yes: this token byte for byte, issued, live, bound to this kit. */
    private static function admits(WP_Post $grant, string $presented, string $contentType, int $contentId): bool
    {
        $fields = (array) $grant->fields;
        $stored = (string) ($fields['token'] ?? '');
        $boundType = (string) ($fields['content_type'] ?? '');
        $boundId = (int) ($fields['content_id'] ?? 0);

        return $stored !== ''
            && hash_equals($stored, $presented)
            && $grant->post_status === 'publish'
            && (string) ($fields['status'] ?? '') === self::STATUS_ACTIVE
            && $boundType !== ''
            && $boundId > 0
            && $boundType === $contentType
            && $boundId === $contentId;
    }

    /** A language the site has, its default when none is named; null for any other. */
    private static function language(?string $lang): ?string
    {
        $languages = function_exists('pll_languages_list') ? array_map('strval', (array) pll_languages_list()) : [];

        if ($languages === []) {
            return $lang === null || $lang === '' ? '' : null;
        }

        $lang ??= (string) pll_default_language();

        return in_array($lang, $languages, true) ? $lang : null;
    }

    /** The live grant for an address on a kit — decided on each row, as the collation cannot tell case apart. */
    private function live(string $address, string $contentType, int $contentId): ?WP_Post
    {
        $model = ntdst_data()->get(GrantModel::POST_TYPE);
        $candidates = $model
            ->where('post_status', 'publish')
            ->where('recipient_email', $address)
            ->where('content_type', $contentType)
            ->where('content_id', $contentId)
            ->limit(self::MAX_GRANTS_PER_CONTENT)
            ->get();

        foreach ($candidates as $candidate) {
            $grant = (int) ($candidate['id'] ?? 0) > 0 ? $model->find((int) $candidate['id']) : null;
            $fields = $grant instanceof WP_Post ? (array) $grant->fields : [];

            if (
                ($fields['status'] ?? '') === self::STATUS_ACTIVE
                && ($fields['recipient_email'] ?? '') === $address
                && ($fields['content_type'] ?? '') === $contentType
                && (int) ($fields['content_id'] ?? 0) === $contentId
            ) {
                return $grant;
            }
        }

        return null;
    }

    /** The live grant, its link now in `$lang`; the token stays. */
    private function inLanguage(WP_Post $grant, string $lang): WP_Post|WP_Error
    {
        if (($grant->fields['lang'] ?? '') === $lang) {
            return $grant;
        }

        $model = ntdst_data()->get(GrantModel::POST_TYPE);
        $written = $model->updateMeta((int) $grant->ID, 'lang', $lang);

        return is_wp_error($written) ? $written : $model->find((int) $grant->ID);
    }

    private function create(string $address, string $contentType, int $contentId, string $lang): WP_Post|WP_Error
    {
        try {
            $token = self::mint();
        } catch (\Throwable) {
            return new WP_Error('mint_failed', 'Could not mint an access token.', ['status' => 500]);
        }

        // The title names the kit only: the address stays in protected meta.
        return ntdst_data()->get(GrantModel::POST_TYPE)->create([
            'title'           => $contentType . ' #' . $contentId,
            'post_status'     => 'publish',
            'token'           => $token,
            'recipient_email' => $address,
            'content_type'    => $contentType,
            'content_id'      => $contentId,
            'status'          => self::STATUS_ACTIVE,
            'access_count'    => 0,
            'lang'            => $lang,
        ]);
    }

    /**
     * A MySQL advisory lock on one address and kit — not a cache key, which is per process without a
     * persistent object cache. Named for the install too: DDEV shares one server between sites.
     * MySQL drops it with the connection, so a worker that dies mid-issue wedges nothing.
     */
    private function hold(string $address, string $contentType, int $contentId): ?string
    {
        global $wpdb;

        $name = 'atelier_grant_' . md5($wpdb->dbname . $wpdb->prefix . '|' . $contentType . '|' . $contentId . '|' . $address);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- an advisory lock: no table, nothing to cache.
        $held = $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, %d)', $name, self::LOCK_WAIT_SECONDS));

        return (string) $held === '1' ? $name : null;
    }

    private function release(string $name): void
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- an advisory lock: no table, nothing to cache.
        $wpdb->query($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $name));
    }
}
