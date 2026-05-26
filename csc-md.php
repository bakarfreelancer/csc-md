<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://quantumverse.dev/abubakar
 * @since             1.0.0
 * @package           Csc_Md
 *
 * @wordpress-plugin
 * Plugin Name:       CSC Member Directory
 * Plugin URI:        https://quantumverse.dev
 * Description:       Plugin to manage the member directory and other custom things.
 * Version:           1.2.0
 * Author:            Abu Bakar
 * Author URI:        https://quantumverse.dev/abubakar/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       csc-md
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'CSC_MD_VERSION', '1.2.0' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-csc-md-activator.php
 */
function activate_csc_md() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-csc-md-activator.php';
	Csc_Md_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-csc-md-deactivator.php
 */
function deactivate_csc_md() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-csc-md-deactivator.php';
	Csc_Md_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_csc_md' );
register_deactivation_hook( __FILE__, 'deactivate_csc_md' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-csc-md.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_csc_md() {

	$plugin = new Csc_Md();
	$plugin->run();

}
run_csc_md();
