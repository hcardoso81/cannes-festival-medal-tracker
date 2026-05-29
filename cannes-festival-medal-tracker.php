<?php
/**
 * Plugin Name: Cannes Festival Medal Tracker
 * Description: Import, manage and display festival medal standings by country.
 * Version: 1.0.0
 * Author: Hernan Cardoso
 * Author URI: https://www.linkedin.com/in/cardosohernan/
 * Text Domain: cannes-festival-medal-tracker
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('FMB_VERSION', '1.0.0');
define('FMB_PATH', plugin_dir_path(__FILE__));
define('FMB_URL', plugin_dir_url(__FILE__));
define('FMB_BASENAME', plugin_basename(__FILE__));
define('FMB_TEXT_DOMAIN', 'cannes-festival-medal-tracker');

require_once FMB_PATH . 'autoload.php';

if (file_exists(FMB_PATH . 'vendor/autoload.php')) {
    require_once FMB_PATH . 'vendor/autoload.php';
}

register_activation_hook(__FILE__, [FestivalMedalTracker\Infrastructure\WordPress\DatabaseInstaller::class, 'activate']);

FestivalMedalTracker\Bootstrap\Plugin::boot();
