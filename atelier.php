<?php

/**
 * Atelier
 * Version: 0.1.0
 *
 * The creative-sector domain layer for atelier296 clients, between
 * ntdst-baseline and a site's own plugin. Each service lives in its own
 * folder under services/; code two services share lives in shared/.
 *
 * mu-plugins load alphabetically and atelierloader runs before
 * ntdst-coreloader, so nothing here may call the framework at load.
 * The site's plugin-config lists the services it boots.
 */

defined('ABSPATH') || exit;
