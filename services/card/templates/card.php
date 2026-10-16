<?php

/**
 * The business card: one phone screen, a front and a back. A standalone
 * document — no wp_head()/wp_footer(), so no theme stack — that prints only
 * the keys it names, each escaped for where it lands.
 */

defined('ABSPATH') || exit;

$card = (array) ntdst_page_data('card', []);
$page = (array) ntdst_page_data('page', []);
$labels = (array) ($page['labels'] ?? []);
$company = (array) ($card['company'] ?? []);

$name = (string) ($card['name'] ?? '');
$companyName = (string) ($company['name'] ?? '');
$title = $companyName === '' ? $name : $name . ' · ' . $companyName;
$email = (string) ($card['email'] ?? '');
$phone = (string) ($card['phone'] ?? '');
$dialable = (string) preg_replace('/[^0-9+]/', '', $phone);
$photo = esc_url((string) ($card['photo'] ?? ''), ['https', 'http']);
$logo = esc_url((string) ($company['logo'] ?? ''), ['https', 'http']);
$companyUrl = esc_url((string) ($company['url'] ?? ''), ['https', 'http']);
$companyHost = (string) preg_replace('/^www\./i', '', (string) wp_parse_url($companyUrl, PHP_URL_HOST));
$label = static fn(string $key): string => (string) ($labels[$key] ?? '');
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr((string) ($page['lang'] ?? '')); ?>">
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#f4f4f2">
    <title><?php echo esc_html($title); ?></title>
    <link rel="stylesheet" href="<?php echo esc_url((string) ($page['css'] ?? '')); ?>">
    <script src="<?php echo esc_url((string) ($page['js'] ?? '')); ?>" defer></script>
</head>
<body>

<main
    class="card"
    id="card"
    data-share-url="<?php echo esc_url((string) ($page['share'] ?? '')); ?>"
    data-share-title="<?php echo esc_attr($title); ?>"
    data-copied="<?php echo esc_attr($label('copy_link')); ?>"
>

    <header class="head">
        <h1 class="name"><?php echo esc_html($name); ?></h1>
        <?php if (($card['title'] ?? '') !== '') : ?>
            <span class="micro"><?php echo esc_html((string) $card['title']); ?></span>
        <?php endif; ?>
    </header>

    <?php if ($photo !== '') : ?>
        <div class="photo"><img src="<?php echo $photo; ?>" alt="<?php echo esc_attr($name); ?>" decoding="async"></div>
    <?php endif; ?>

    <footer class="foot">
        <?php if ($email !== '' || $dialable !== '') : ?>
            <div class="contact">
                <?php if ($email !== '') : ?>
                    <a href="<?php echo esc_url('mailto:' . $email, ['mailto']); ?>"><?php echo esc_html($email); ?></a>
                <?php endif; ?>
                <?php if ($dialable !== '') : ?>
                    <a href="<?php echo esc_url('tel:' . $dialable, ['tel']); ?>"><?php echo esc_html($phone); ?></a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="actions">
            <a class="btn btn--solid" id="save" href="<?php echo esc_url((string) ($page['vcard'] ?? '')); ?>" download="<?php echo esc_attr((string) ($page['vcard_name'] ?? '')); ?>"><?php echo esc_html($label('save')); ?></a>
            <button class="btn btn--icon" id="share" type="button" aria-label="<?php echo esc_attr($label('share')); ?>">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 15V3m0 0L7.5 7.5M12 3l4.5 4.5M5 12v8h14v-8"/></svg>
            </button>
            <button class="btn btn--icon" id="flip" type="button" aria-label="<?php echo esc_attr($label('back')); ?>" aria-expanded="false" aria-controls="back">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h2v2h-2zM18 14h2v2h-2zM14 18h2v2h-2zM18 18h2v2h-2z"/></svg>
            </button>
        </div>
    </footer>

    <div class="ink" aria-hidden="true"><i></i><i></i><i></i></div>

    <section class="back" id="back" aria-label="<?php echo esc_attr($label('back')); ?>" aria-hidden="true">
        <div class="back-top">
            <span class="micro"><?php echo esc_html($name); ?></span>
        </div>

        <div class="back-mid">
            <?php if (is_string($page['qr'] ?? null)) : ?>
                <div class="qr" role="img" aria-label="<?php echo esc_attr($label('save')); ?>">
                    <?php echo $page['qr']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the encoder's own SVG: module geometry, none of the payload's text. ?>
                </div>
            <?php endif; ?>
            <?php if ($logo !== '') : ?>
                <img class="logo" src="<?php echo $logo; ?>" alt="<?php echo esc_attr($companyName); ?>">
            <?php else : ?>
                <p class="company"><?php echo esc_html($companyName); ?></p>
            <?php endif; ?>
            <?php if ($companyUrl !== '' && $companyHost !== '') : ?>
                <a class="micro" href="<?php echo $companyUrl; ?>"><?php echo esc_html($companyHost); ?></a>
            <?php endif; ?>
        </div>

        <div class="back-foot">
            <button class="btn" id="unflip" type="button"><?php echo esc_html($label('front')); ?></button>
        </div>
    </section>

</main>

</body>
</html>
