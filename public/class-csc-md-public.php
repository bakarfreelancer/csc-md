<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://quantumverse.dev/abubakar
 * @since      1.0.0
 *
 * @package    Csc_Md
 * @subpackage Csc_Md/public
 */
class Csc_Md_Public {

	private $plugin_name;
	private $version;

	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	/**
	 * Enqueue plugin stylesheets.
	 * Uses filemtime() as version so browsers always pick up changes during development.
	 */
	public function enqueue_styles() {
		$css_path = plugin_dir_path( __FILE__ ) . 'css/csc-md-public.css';
		wp_enqueue_style(
			$this->plugin_name,
			plugin_dir_url( __FILE__ ) . 'css/csc-md-public.css',
			array(),
			file_exists( $css_path ) ? filemtime( $css_path ) : $this->version,
			'all'
		);
	}

	/**
	 * Enqueue plugin scripts and pass AJAX data + nonces to JS.
	 * Uses filemtime() as version so browsers always pick up changes during development.
	 */
	public function enqueue_scripts() {
		$js_path = plugin_dir_path( __FILE__ ) . 'js/csc-md-public.js';
		wp_enqueue_script(
			$this->plugin_name,
			plugin_dir_url( __FILE__ ) . 'js/csc-md-public.js',
			array( 'jquery' ),
			file_exists( $js_path ) ? filemtime( $js_path ) : $this->version,
			true
		);

		wp_localize_script(
			$this->plugin_name,
			'cscAjax',
			array(
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'publicNonce'   => wp_create_nonce( 'csc_public_nonce' ),
				'loginNonce'    => wp_create_nonce( 'csc_login_action' ),
				'registerNonce' => wp_create_nonce( 'csc_register_action' ),
			)
		);
	}
}
