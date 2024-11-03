<?php

/*
Plugin Name:    Easy Plugin Updater
Plugin URI:     https://github.com/sayandey18/easy-plugin-updater/
Description:    Lightweight library that enables seamless updates for plugins from an external URL, bypassing the WordPress repository.
Version:        1.0.0
Requires PHP:   7.4
Author:         Sayan Dey
Author URI:     https://profiles.wordpress.org/sayandey18/
License:        GPL v2 or later
License URI:    http://www.gnu.org/licenses/gpl-2.0.txt
Plugin URI:     https://wp.serverhome.biz/easy-plugin-updater/
Text Domain:    easy-plugin-updater
Domain Path:    /languages
*/

/**
 * If this file is called directly, abort.
 */
if ( !defined( 'WPINC' ) ) {
	die;
}

/**
 * Define required plugin's required constant.
 * 
 * @since 1.0.0
 */
define( 'EPUP_PLUGIN_VERSION', '1.0.0' );
define( 'EPUP_PLUGIN_SLUG', plugin_basename( __DIR__ ) );
define( 'EPUP_PLUGIN_FILE', plugin_basename( __FILE__ ) );
define( 'EPUP_UPDATER_API_URL', 'https://wp.serverhome.biz/' );

/**
 * Include the main plugin updater class.
 * 
 * @since 1.0.0
 */
require_once plugin_dir_path( __FILE__ ) . 'includes/class-easy-plugin-updater.php';

/**
 * Initialize the plugin for WordPress use.
 * 
 * @since 1.0.0
 */
if ( !function_exists( 'epup_plugin_execute' ) ) {
    function epup_plugin_execute() {
        // Instantiate classes
        $updater = new EasyPluginUpdater();
    
        // Run necessary methods
        $updater->epup_update();
    }
}

epup_plugin_execute();
