<?php

/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @link       https://quantumverse.dev/abubakar
 * @since      1.0.0
 *
 * @package    Csc_Md
 * @subpackage Csc_Md/includes
 */

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.0.0
 * @package    Csc_Md
 * @subpackage Csc_Md/includes
 * @author     Abu Bakar <abubakar@quantumverse.dev>
 */
class Csc_Md_i18n {


	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    1.0.0
	 */
	public function load_plugin_textdomain() {

		load_plugin_textdomain(
			'csc-md',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);

	}



}
