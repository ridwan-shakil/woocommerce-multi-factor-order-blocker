<?php

/**
 * Plugin Name: WooCommerce Multi Factor Order Blocker
 * Description: Blocks multiple WooCommerce orders from the same device, IP, or phone number until previous orders are completed. Includes customizable popup alerts, CartFlows support, and an admin settings panel.
 * Version: 1.0.1
 * Author: Weber Sayaf
 * Author URI: https://webersayaf.com/portfolio
 * Plugin URI: https://webersayaf.com/portfolio
 * Text Domain: wcorder-blocker
 * Domain Path: /languages
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// namespace RS\OrderBlocker;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define plugin version constant

define('RS_ORDER_BLOCKER_VERSION',  "1.0.1");

// Load core plugin class
require_once plugin_dir_path(__FILE__) . 'includes/class-plugin.php';



// Add "Settings" link to plugin action links
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
    $settings_url  = admin_url('admin.php?page=rs-order-blocker');
    $settings_link = '<a href="' . esc_url($settings_url) . '">' . esc_html__('Settings', 'wcorder-blocker') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
});

/**
 * Runs on plugin activation
 * Creates default settings if not already present
 */
register_activation_hook(__FILE__, function () {
    $option_name  = 'rs_order_blocker_settings';
    $default_opts = (new \RS\OrderBlocker\Settings())->get_default_options();

    if (!get_option($option_name)) {
        add_option($option_name, $default_opts);
    }
    //For redirection upon activation
    update_option('rs_ob_redirect_to_license', true);
});


/**
 * Create table on plugin activation to store "incomplete orders"
 */
register_activation_hook(__FILE__, function () {
    global $wpdb;

    $table_name = $wpdb->prefix . 'wc_order_blocker_incomplete_orders';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table_name} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        session_id VARCHAR(191) NOT NULL,
        status ENUM('cart', 'checkout', 'payment_failed') NOT NULL,
        customer_name VARCHAR(191),
        email VARCHAR(191),
        mobile VARCHAR(50),
        cart_contents LONGTEXT,
        total DECIMAL(10,2) DEFAULT 0.00,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        INDEX (session_id),
        INDEX (email),
        INDEX (status)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
});


/**
 * Plugin Update Checker
 * This will check for updates from the specified GitHub repository.
 * Note: This requires the plugin-update-checker library to be included.
 */
require 'plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$myUpdateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/ridwan-shakil/woocommerce-multi-factor-order-blocker',
    __FILE__,
    'woocommerce-multi-factor-order-blocker'
);

//Set the branch that contains the stable release.
$myUpdateChecker->setBranch('main');




/**
 * Plugin uninstall hook (optional fallback)
 * Note: This hook won't run if uninstall.php is present.
 */
register_uninstall_hook(__FILE__, 'rs_order_blocker_uninstall');

function rs_order_blocker_uninstall() {
    // This function will not execute if uninstall.php exists,
    // but it's defined here as a fallback.
}
