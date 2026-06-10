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

	public function enqueue_styles() {
		wp_enqueue_style(
			$this->plugin_name,
			plugin_dir_url( __FILE__ ) . 'css/csc-md-public.css',
			array(),
			$this->version,
			'all'
		);
	}

	public function enqueue_scripts() {
		wp_enqueue_script(
			$this->plugin_name,
			plugin_dir_url( __FILE__ ) . 'js/csc-md-public.js',
			array( 'jquery' ),
			$this->version,
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
