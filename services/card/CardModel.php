<?php

declare(strict_types=1);

namespace Atelier\Card;

use Atelier\Models\PersonRepository;
use Atelier\Profile\ProfileRepository;

defined('ABSPATH') || exit;

/**
 * A card's view model: one core-team person's card fields and the company.
 * Only the card fields are read — the private email and phone never are.
 */
final class CardModel
{
    public function __construct(
        private readonly PersonRepository $people,
        private readonly ProfileRepository $profiles,
    ) {
    }

    /** @return array<string, mixed>|null null unless a published, named, Dutch core-team person has the slug */
    public function forSlug(string $slug): ?array
    {
        $slug = sanitize_title($slug);
        $person = $slug === '' ? null : $this->people->findBySlug($slug);

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

        return [
            'slug'      => $person->post_name,
            'name'      => $name,
            'given'     => $given,
            'family'    => $family,
            'title'     => $text('card_title'),
            'email'     => is_email($email) ? $email : '',
            'phone'     => $text('card_phone'),
            'share_url' => $text('card_share_url'),
            'photo'     => get_the_post_thumbnail_url($person, 'large') ?: null,
            'company'   => $this->company(),
        ];
    }

    /** R-1: the given name is the first word, the family name the rest; one word stays a family name. */
    private static function split(string $name): array
    {
        $parts = preg_split('/\s+/', $name, 2) ?: [''];

        return count($parts) === 2 ? [$parts[0], $parts[1]] : ['', $parts[0]];
    }

    /** @return array{name: string, logo: ?string, url: string} */
    private function company(): array
    {
        $profile = $this->profiles->current(ProfileRepository::translatedLanguage());
        $logo = $profile['logo'] ?? null;

        return [
            'name' => (string) ($profile['name'] ?? ''),
            'logo' => $logo === null ? null : (wp_get_attachment_url($logo) ?: null),
            'url'  => (string) ($profile['url'] ?? home_url('/')),
        ];
    }
}
