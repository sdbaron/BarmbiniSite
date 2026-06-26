<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Barmbini_Core_Plugin {
	protected static $instance = null;

	protected $loader;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	protected function __construct() {
		$this->loader = new Barmbini_Core_Loader();
		$this->register_catalog_module();
		$this->register_footer_menu_module();
		$this->register_account_module();
		$this->register_notifications_module();
		$this->register_privacy_module();
	}

	protected function register_catalog_module() {
		$breadcrumbs     = new Barmbini_Core_Catalog_Breadcrumbs();
		$category_display = new Barmbini_Core_Catalog_Category_Display();
		$catalog_hooks   = new Barmbini_Core_Catalog_Hooks( $breadcrumbs, $category_display );

		$this->loader->add_action( 'wp', $catalog_hooks, 'register_runtime_hooks' );
		$this->loader->add_action( 'wp_enqueue_scripts', $catalog_hooks, 'enqueue_styles' );
		$this->loader->add_filter( 'woocommerce_get_breadcrumb', $breadcrumbs, 'inject_sortiment_crumb' );
		$this->loader->add_filter( 'woocommerce_subcategory_count_html', $catalog_hooks, 'remove_subcategory_count' );
		$this->loader->add_action( 'woocommerce_after_subcategory_title', $category_display, 'render_subcategory_description', 10, 1 );
	}

	protected function register_footer_menu_module() {
		$footer_menu = new Barmbini_Core_Footer_Menu();

		$footer_menu->register();
	}

	protected function register_account_module() {
		$settings         = new Barmbini_Core_Subscription_Settings();
		$consent_recorder = new Barmbini_Core_Consent_Recorder();
		$queue_repository = new Barmbini_Core_Queue_Repository();
		$account_endpoint = new Barmbini_Core_Account_Endpoint( $settings, $consent_recorder, $queue_repository );

		$this->loader->add_action( 'init', $account_endpoint, 'register_endpoint' );
		$this->loader->add_filter( 'woocommerce_account_menu_items', $account_endpoint, 'add_menu_item' );
		$this->loader->add_action( 'woocommerce_account_abonnements_endpoint', $account_endpoint, 'render_content' );
		$this->loader->add_action( 'template_redirect', $account_endpoint, 'handle_form_submission' );
		$this->loader->add_action( 'wp_enqueue_scripts', $account_endpoint, 'enqueue_styles' );
	}

	protected function register_notifications_module() {
		$settings            = new Barmbini_Core_Subscription_Settings();
		$log_repository      = new Barmbini_Core_Log_Repository();
		$queue_repository    = new Barmbini_Core_Queue_Repository();
		$unsubscribe_service = new Barmbini_Core_Unsubscribe_Service( $settings, $queue_repository );
		$delivery_service    = new Barmbini_Core_Delivery_Service( $settings, $log_repository, $unsubscribe_service, $queue_repository );
		$digest_scheduler    = new Barmbini_Core_Digest_Scheduler( $queue_repository, $settings, $delivery_service );
		$event_collector     = new Barmbini_Core_Event_Collector( $settings, $delivery_service );
		$admin_menu          = new Barmbini_Core_Admin_Menu( $settings, $log_repository, $queue_repository );

		$this->loader->add_action( 'init', $unsubscribe_service, 'handle_request' );
		$this->loader->add_filter( 'cron_schedules', $digest_scheduler, 'register_schedules' );
		$this->loader->add_action( 'init', $digest_scheduler, 'schedule_events' );
		$this->loader->add_action( 'transition_post_status', $event_collector, 'handle_transition_post_status', 10, 3 );
		$this->loader->add_action( 'save_post_product', $event_collector, 'handle_product_save', 20, 3 );
		$this->loader->add_action( 'woocommerce_scheduled_sales', $event_collector, 'handle_scheduled_sales' );
		$this->loader->add_action( 'barmbini_core_daily_digest', $digest_scheduler, 'run_daily_digest' );
		$this->loader->add_action( 'barmbini_core_weekly_digest', $digest_scheduler, 'run_weekly_digest' );
		$this->loader->add_action( 'admin_menu', $admin_menu, 'register_pages' );
	}

	protected function register_privacy_module() {
		$settings         = new Barmbini_Core_Subscription_Settings();
		$log_repository   = new Barmbini_Core_Log_Repository();
		$queue_repository = new Barmbini_Core_Queue_Repository();
		$privacy_exporter = new Barmbini_Core_Privacy_Exporter( $settings, $log_repository, $queue_repository );

		$this->loader->add_filter( 'wp_privacy_personal_data_exporters', $privacy_exporter, 'register_exporter' );
		$this->loader->add_filter( 'wp_privacy_personal_data_erasers', $privacy_exporter, 'register_eraser' );
	}

	public function run() {
		$this->loader->run();
	}
}