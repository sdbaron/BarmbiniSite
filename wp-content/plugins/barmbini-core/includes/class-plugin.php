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
		$this->register_address_shortcode_module();
		$this->register_latest_news_module();
		$this->register_promotion_module();
		$this->register_account_menu_module();
		$this->register_cache_maintenance_module();
		$this->register_homepage_layout_module();
		$this->register_top_product_categories_module();
		$this->register_account_module();
		$this->register_notifications_module();
		$this->register_privacy_module();
		$this->register_security_module();
		$this->register_contact_form_honeypot_module();
		$this->register_address_settings_module();
	}

	protected function register_catalog_module() {
		$breadcrumbs     = new Barmbini_Core_Catalog_Breadcrumbs();
		$category_display = new Barmbini_Core_Catalog_Category_Display();
		$catalog_hooks   = new Barmbini_Core_Catalog_Hooks( $breadcrumbs, $category_display );

		$this->loader->add_action( 'wp', $catalog_hooks, 'register_runtime_hooks' );
		$this->loader->add_action( 'wp_enqueue_scripts', $catalog_hooks, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $catalog_hooks, 'enqueue_global_styles' );
		$this->loader->add_filter( 'woocommerce_get_breadcrumb', $breadcrumbs, 'inject_sortiment_crumb' );
		$this->loader->add_filter( 'woocommerce_subcategory_count_html', $catalog_hooks, 'remove_subcategory_count' );
		$this->loader->add_action( 'woocommerce_after_subcategory_title', $category_display, 'render_subcategory_description', 10, 1 );
	}

	protected function register_footer_menu_module() {
		$footer_menu = new Barmbini_Core_Footer_Menu();

		$footer_menu->register();
	}

	protected function register_address_shortcode_module() {
		$address_shortcode = new Barmbini_Core_Address_Shortcode();
		$address_shortcode->register();

		add_action( 'widgets_init', array( $this, 'register_address_widget' ) );
	}

	/**
	 * Registriert das Adressblock-Widget (Callback für widgets_init).
	 */
	public function register_address_widget() {
		register_widget( 'Barmbini_Core_Address_Widget' );
	}

	protected function register_latest_news_module() {
		$latest_news = new Barmbini_Core_Latest_News_Shortcode();
		$latest_news->register();
	}

	/**
	 * Registriert den CPT "Aktion" und den Shortcode [barmbini_promotion].
	 *
	 * Der CPT setzt seine Hooks (init, add_meta_boxes, save_post) selbst
	 * und wird bewusst nicht über den Loader registriert.
	 */
	protected function register_promotion_module() {
		$post_type = new Barmbini_Core_Promotion_Post_Type();
		$post_type->register();

		$shortcode = new Barmbini_Core_Promotion_Shortcode();
		$shortcode->register();
	}

	/**
	 * Registriert den dynamischen Menüeintrag "Ihr Konto" / "Anmelden".
	 *
	 * @return void
	 */
	protected function register_account_menu_module() {
		$account_menu = new Barmbini_Core_Account_Menu();
		$account_menu->register();
	}

	/**
	 * Registriert den periodischen Cache-Refresh (WP Fastest Cache).
	 *
	 * @return void
	 */
	protected function register_cache_maintenance_module() {
		$maintenance = new Barmbini_Core_Cache_Maintenance();
		$maintenance->register();
	}

	/**
	 * Registriert das Startseiten-Layout (CSS für den Startseiten-Hero).
	 *
	 * @return void
	 */
	protected function register_homepage_layout_module() {
		$layout = new Barmbini_Core_Homepage_Layout();
		$layout->register();
	}

	/**
	 * Registriert den Shortcode [barmbini_top_product_categories].
	 *
	 * @return void
	 */
	protected function register_top_product_categories_module() {
		$shortcode = new Barmbini_Core_Top_Product_Categories_Shortcode();
		$shortcode->register();
	}

	protected function register_account_module() {
		$settings         = new Barmbini_Core_Subscription_Settings();
		$consent_recorder = new Barmbini_Core_Consent_Recorder();
		$queue_repository = new Barmbini_Core_Queue_Repository();
		$account_endpoint = new Barmbini_Core_Account_Endpoint( $settings, $consent_recorder, $queue_repository );

		$this->loader->add_action( 'init', $account_endpoint, 'register_endpoint' );
		$this->loader->add_action( 'init', $account_endpoint, 'register_registration_features' );
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
		$this->loader->add_action( 'init', $event_collector, 'schedule_action_notifier' );
		$this->loader->add_action( 'barmbini_core_action_start_notifier', $event_collector, 'handle_scheduled_action_starts' );
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

	/**
	 * Registriert das Security-Modul (REST-API-Härtung).
	 *
	 * Sperrt die Benutzer-Endpoints der REST-API für nicht berechtigte
	 * Personen, damit Benutzernamen nicht öffentlich preisgegeben werden.
	 *
	 * @return void
	 */
	protected function register_security_module() {
		$rest_hardening = new Barmbini_Core_Rest_Api_Hardening();
		$rest_hardening->register();
	}

	/**
	 * Registriert den CF7-Honeypot (Spam-Schutz Option A).
	 *
	 * @return void
	 */
	protected function register_contact_form_honeypot_module() {
		$honeypot = new Barmbini_Core_Contact_Form_Honeypot();
		$honeypot->register();
	}

	/**
	 * Registriert die Adress-Einstellungsseite (Einstellungen > Barmbini Adresse).
	 *
	 * @return void
	 */
	protected function register_address_settings_module() {
		$settings = new Barmbini_Core_Address_Settings();
		$settings->register();
	}

	public function run() {
		$this->loader->run();
	}
}