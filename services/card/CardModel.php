<?php

declare(strict_types=1);

namespace Atelier\Card;

use Atelier\Models\PersonRepository;
use Atelier\Profile\ProfileModel;
use Atelier\Profile\ProfileRepository;
use Atelier\Support\Languages;
use Atelier\Support\WebUrl;

defined('ABSPATH') || exit;

/**
 * A card's view model: one core-team person's card fields and the company.
 * Only the card fields are read — the private email and phone never are.
 */
final class CardModel
{
    private const SHARE_URL_MAX_LENGTH = 300;

    public function __construct(
        private readonly PersonRepository $people,
        private readonly ProfileRepository $profiles,
    ) {
    }

    /**
     * Only the canonical slug: `/card/JO-ROETS/` or `/card/jo-roets./` is refused like any unknown one.
     *
     * @return array<string, mixed>|null null unless a published, named, Dutch core-team person has the slug
     */
    public function forSlug(string $slug): ?array
    {
        $person = $slug === '' || sanitize_title($slug) !== $slug ? null : $this->people->findBySlug($slug);

        if ($person === null || empty($person->fields['core_team'])) {
            return null;
        }

        $f = (array) $person->fields;
        $text = static fn(string $key): string => is_scalar($f[$key] ?? null) ? trim((string) $f[$key]) : '';
        $name = trim((string) $person->post_title);

        if ($name === '') {
            return null;
        }

        [$given, $family] = self::split($name);
        $email = $text('card_email');
        $shareUrl = $text('card_share_url');

        return [
            'slug'      => $person->post_name,
            'name'      => $name,
            'given'     => $given,
            'family'    => $family,
            'title'     => $text('card_title'),
            'email'     => is_email($email) ? $email : '',
            'phone'     => $text('card_phone'),
            'share_url' => self::isShareableUrl($shareUrl) ? $shareUrl : '',
            'photo'     => get_the_post_thumbnail_url($person, 'large') ?: null,
            'company'   => $this->company(),
        ];
    }

    /**
     * Who has a card: the people forSlug() answers, for the chooser. Their names and photos only.
     *
     * @return list<array{slug: string, name: string, photo: ?string}>
     */
    public function team(): array
    {
        $team = [];

        foreach ($this->people->coreTeam() as $row) {
            $name = trim((string) $row['title']);

            if ($name !== '') {
                $team[] = ['slug' => (string) $row['slug'], 'name' => $name, 'photo' => ($row['thumbnail']['url'] ?? null) ?: null];
            }
        }

        return $team;
    }

    /** R-1: the given name is the first word, the family name the rest; one word stays a family name. */
    private static function split(string $name): array
    {
        $parts = preg_split('/\s+/', $name, 2) ?: [''];

        return count($parts) === 2 ? [$parts[0], $parts[1]] : ['', $parts[0]];
    }

    /** A web URL, at most 300, and already what esc_url_raw() makes of it — else no share link. */
    private static function isShareableUrl(string $url): bool
    {
        return strlen($url) <= self::SHARE_URL_MAX_LENGTH && WebUrl::is($url) && esc_url_raw($url) === $url;
    }

    /** @return array{name: string, logo: ?string, url: string} */
    public function company(): array
    {
        $profile = $this->profiles->current(Languages::defaultFor(ProfileModel::POST_TYPE));
        $logo = $profile['logo'] ?? null;

        return [
            'name' => (string) ($profile['name'] ?? ''),
            'logo' => $logo === null ? null : (wp_get_attachment_url($logo) ?: null),
            'url'  => (string) ($profile['url'] ?? home_url('/')),
        ];
    }
}
