<?php

/**
 * The file that defines the core plugin class
 *
 * @link       https://quantumverse.dev/abubakar
 * @since      1.0.0
 *
 * @package    Csc_Md
 * @subpackage Csc_Md/includes
 */

class Csc_Md {

	protected $loader;
	protected $plugin_name;
	protected $version;

	public function __construct() {
		$this->version     = defined( 'CSC_MD_VERSION' ) ? CSC_MD_VERSION : '1.0.0';
		$this->plugin_name = 'csc-md';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
	}

	private function load_dependencies() {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-csc-md-loader.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-csc-md-i18n.php';

		// CSC custom classes
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-csc-organisations.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-csc-registration.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-csc-dashboard.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/data-csc-locations.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-csc-directory.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-csc-profile.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-csc-settings.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-csc-forum.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-csc-newsletters.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-csc-terms.php';

		// Dev / admin tools
		if ( is_admin() ) {
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-csc-seeder.php';
		}

		// Integrations & import
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-csc-hubspot.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-csc-email-queue.php';

		// Admin
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-csc-md-admin.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-csc-member-admin.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-csc-integrations.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-csc-import.php';

		// Public
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-csc-md-public.php';

		$this->loader = new Csc_Md_Loader();
	}

	private function set_locale() {
		$plugin_i18n = new Csc_Md_i18n();
		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );
	}

	private function define_admin_hooks() {
		$plugin_admin = new Csc_Md_Admin( $this->get_plugin_name(), $this->get_version() );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );

		// CSC member admin panel
		$member_admin = new Csc_Member_Admin();
		$member_admin->register_hooks( $this->loader );

		// Integrations settings page
		$integrations = new Csc_Integrations();
		$integrations->register_hooks( $this->loader );

		// Bulk import page
		$import = new Csc_Import();
		$import->register_hooks( $this->loader );
	}

	private function define_public_hooks() {
		// One-time routine to sync page shortcodes if pages were created before shortcodes existed
		$this->loader->add_action( 'init', $this, 'sync_page_content', 99 );
		$plugin_public = new Csc_Md_Public( $this->get_plugin_name(), $this->get_version() );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );

		// Organisations CPT
		$organisations = new Csc_Organisations();
		$organisations->register_hooks( $this->loader );

		// Registration & Login shortcodes
		$registration = new Csc_Registration();
		$registration->register_hooks( $this->loader );

		// Dashboard & portal access guard
		$dashboard = new Csc_Dashboard();
		$dashboard->register_hooks( $this->loader );

		// Member Directory
		$directory = new Csc_Directory();
		$directory->register_hooks();

		// Update Account / Profile
		$profile = new Csc_Profile();
		$profile->register_hooks();

		// Settings
		$settings = new Csc_Settings();
		$settings->register_hooks();

		// Forum
		$forum = new Csc_Forum();
		$forum->register_hooks();

		// Newsletters & Resources
		$newsletters = new Csc_Newsletters();
		$newsletters->register_hooks();

		// Terms of Use
		$terms = new Csc_Terms();
		$terms->register_hooks();

		// Email queue cron
		$email_queue = new Csc_Email_Queue();
		$email_queue->register_hooks();

		// Test data seeder (admin only)
		if ( is_admin() ) {
			$seeder = new Csc_Seeder();
			$seeder->register_hooks();
		}
	}

	/**
	 * Ensure portal pages have the correct shortcode content.
	 * Runs once per version bump via a stored option.
	 */
	public function sync_page_content() {
		$synced_version = get_option( 'csc_md_pages_synced', '' );
		if ( $synced_version === $this->version ) {
			return;
		}

		$map = array(
			'members-login'           => '[csc_login]',
			'join-csc'                => '[csc_join]',
			'member-dashboard'        => '[csc_dashboard]',
			'member-directory'        => '[csc_directory]',
			'update-account'          => '[csc_update_account]',
			'member-settings'         => '[csc_settings]',
			'terms-of-use'            => '[csc_terms]',
			'member-forum'            => '[csc_forum]',
			'member-newsletters'      => '[csc_newsletters]',
			'members-forgot-password' => '[csc_forgot_password]',
			'members-set-password'    => '[csc_set_password]',
		);

		foreach ( $map as $slug => $shortcode ) {
			$page = get_page_by_path( $slug );
			if ( ! $page ) {
				continue;
			}
			if ( trim( $page->post_content ) !== $shortcode ) {
				wp_update_post( array(
					'ID'           => $page->ID,
					'post_content' => $shortcode,
				) );
			}
			if ( get_post_meta( $page->ID, '_wp_page_template', true ) !== 'page-csc-portal.php' ) {
				update_post_meta( $page->ID, '_wp_page_template', 'page-csc-portal.php' );
			}
		}

		update_option( 'csc_md_pages_synced', $this->version );
	}

	public function run() {
		$this->loader->run();
	}

	public function get_plugin_name() {
		return $this->plugin_name;
	}

	public function get_loader() {
		return $this->loader;
	}

	public function get_version() {
		return $this->version;
	}
}
