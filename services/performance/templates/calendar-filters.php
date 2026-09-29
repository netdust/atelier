<?php

/**
 * `[atelier_calendar_filters]` — a GET form to the current page. Every value
 * it echoes already passed `CalendarFilter::fromQuery()`'s allow-list
 * (Threat model M1); this template's own job is M4 — escape every output
 * regardless, since a value's origin is not a reason to trust it at render
 * time.
 *
 * @var array<string, string> $args['atts']
 * @var array<string, string> $args['selected']
 * @var array{productions: array<string, string>, countries: list<string>, months: list<string>, audiences: array<string, string>} $args['options']
 * @var string $args['action']
 * @var string $args['feed_url']
 * @var string $args['toggle_url']
 * @var string $args['toggle_label']
 * @var bool $args['is_empty']
 */

defined('ABSPATH') || exit;

$atts = $args['atts'];
$selected = $args['selected'];
$options = $args['options'];
?>
<form method="get" action="<?php echo esc_url($args['action']); ?>" class="atelier-calendar-filters">
    <?php if (($selected['past'] ?? '') === '1') : ?>
        <input type="hidden" name="past" value="1">
    <?php endif; ?>

    <div class="atelier-calendar-filters__field">
        <label for="atelier-calendar-filter-show"><?php echo esc_html($atts['show_label']); ?></label>
        <select id="atelier-calendar-filter-show" name="show">
            <option value=""><?php echo esc_html($atts['all']); ?></option>
            <?php foreach ($options['productions'] as $slug => $title) : ?>
                <option value="<?php echo esc_attr($slug); ?>"<?php selected($slug, $selected['show'] ?? ''); ?>>
                    <?php echo esc_html($title); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="atelier-calendar-filters__field">
        <label for="atelier-calendar-filter-audience"><?php echo esc_html($atts['audience_label']); ?></label>
        <select id="atelier-calendar-filter-audience" name="audience">
            <option value=""><?php echo esc_html($atts['all']); ?></option>
            <?php foreach ($options['audiences'] as $value => $label) : ?>
                <option value="<?php echo esc_attr($value); ?>"<?php selected($value, $selected['audience'] ?? ''); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="atelier-calendar-filters__field">
        <label for="atelier-calendar-filter-country"><?php echo esc_html($atts['country_label']); ?></label>
        <select id="atelier-calendar-filter-country" name="country">
            <option value=""><?php echo esc_html($atts['all']); ?></option>
            <?php foreach ($options['countries'] as $country) : ?>
                <option value="<?php echo esc_attr($country); ?>"<?php selected($country, $selected['country'] ?? ''); ?>>
                    <?php echo esc_html($country); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="atelier-calendar-filters__field">
        <label for="atelier-calendar-filter-month"><?php echo esc_html($atts['month_label']); ?></label>
        <select id="atelier-calendar-filter-month" name="month">
            <option value=""><?php echo esc_html($atts['all']); ?></option>
            <?php foreach ($options['months'] as $month) : ?>
                <option value="<?php echo esc_attr($month); ?>"<?php selected($month, $selected['month'] ?? ''); ?>>
                    <?php echo esc_html(wp_date('F Y', strtotime($month . '-01'))); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <button type="submit"><?php echo esc_html($atts['submit']); ?></button>
</form>

<p class="atelier-calendar-filters__toggle">
    <a href="<?php echo esc_url($args['toggle_url']); ?>"><?php echo esc_html($args['toggle_label']); ?></a>
</p>

<?php if ($args['is_empty']) : ?>
    <p class="atelier-calendar-filters__empty"><?php echo esc_html($atts['empty']); ?></p>
<?php endif; ?>

<p class="atelier-calendar-filters__feed">
    <a href="<?php echo esc_url($args['feed_url']); ?>"><?php echo esc_html($atts['feed']); ?></a>
</p>
