<?php
/**
 * Plugin Name: Barmbini Core
 * Description: Projektspezifische Fachlogik fuer Sozialkaufhaus Barmbini.
 * Version: 0.1.0
 * Author: Barmbini
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BARMBINI_CORE_VERSION', '0.1.0' );
define( 'BARMBINI_CORE_FILE', __FILE__ );
define( 'BARMBINI_CORE_PATH', plugin_dir_path( __FILE__ ) );
define( 'BARMBINI_CORE_URL', plugin_dir_url( __FILE__ ) );

require_once BARMBINI_CORE_PATH . 'includes/class-loader.php';
require_once BARMBINI_CORE_PATH . 'includes/class-activator.php';
require_once BARMBINI_CORE_PATH . 'includes/class-deactivator.php';
require_once BARMBINI_CORE_PATH . 'includes/account/class-subscription-settings.php';
require_once BARMBINI_CORE_PATH . 'includes/account/class-account-endpoint.php';
require_once BARMBINI_CORE_PATH . 'includes/catalog/class-breadcrumbs.php';
require_once BARMBINI_CORE_PATH . 'includes/catalog/class-category-display.php';
require_once BARMBINI_CORE_PATH . 'includes/catalog/class-catalog-hooks.php';
require_once BARMBINI_CORE_PATH . 'includes/notifications/class-log-repository.php';
require_once BARMBINI_CORE_PATH . 'includes/notifications/class-queue-repository.php';
require_once BARMBINI_CORE_PATH . 'includes/notifications/class-unsubscribe-service.php';
require_once BARMBINI_CORE_PATH . 'includes/notifications/class-delivery-service.php';
require_once BARMBINI_CORE_PATH . 'includes/notifications/class-digest-scheduler.php';
require_once BARMBINI_CORE_PATH . 'includes/notifications/class-event-collector.php';
require_once BARMBINI_CORE_PATH . 'includes/admin/class-admin-menu.php';
require_once BARMBINI_CORE_PATH . 'includes/privacy/class-consent-recorder.php';
require_once BARMBINI_CORE_PATH . 'includes/privacy/class-privacy-exporter.php';
require_once BARMBINI_CORE_PATH . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'Barmbini_Core_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Barmbini_Core_Deactivator', 'deactivate' ) );

function barmbini_core() {
	return Barmbini_Core_Plugin::instance();
}

barmbini_core()->run();