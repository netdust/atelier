<?php

/**
 * The chooser at /card/: the core team, each a link to their card — names,
 * photos and links, nothing else. card.js remembers the choice, so a later
 * visit opens that card. Standalone, like the card.
 */

defined('ABSPATH') || exit;

$page = (array) ntdst_page_data('page', []);
$people = (array) ntdst_page_data('people', []);
$company = (string) ($page['company'] ?? '');
?>
<!DOCTYPE html>
<html class="is-chooser" lang="<?php echo esc_attr((string) ($page['lang'] ?? '')); ?>">
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#f4f4f2">
    <title><?php echo esc_html($company); ?></title>
    <link rel="stylesheet" href="<?php echo esc_url((string) ($page['css'] ?? '')); ?>">
    <script src="<?php echo esc_url((string) ($page['js'] ?? '')); ?>" defer></script>
</head>
<body>

<main class="chooser" id="chooser">
    <h1 class="name"><?php echo esc_html($company); ?></h1>

    <ul class="team">
        <?php foreach ($people as $person) : ?>
            <?php
            $name = (string) ($person['name'] ?? '');
            $photo = esc_url((string) ($person['photo'] ?? ''), ['https', 'http']);
            ?>
            <li>
                <a href="<?php echo esc_url((string) ($person['url'] ?? '')); ?>" data-slug="<?php echo esc_attr((string) ($person['slug'] ?? '')); ?>">
                    <?php if ($photo !== '') : ?>
                        <img src="<?php echo $photo; ?>" alt="" decoding="async">
                    <?php else : ?>
                        <span class="initial" aria-hidden="true"><?php echo esc_html(mb_substr($name, 0, 1)); ?></span>
                    <?php endif; ?>
                    <span><?php echo esc_html($name); ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</main>

</body>
</html>
