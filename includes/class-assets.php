<?php

namespace RS\OrderBlocker;

if (!defined('ABSPATH')) exit;


class Assets {

    public function __construct() {
        // Frontend scripts 
        add_action('wp_enqueue_scripts', [$this, 'frontend_styles']);
        add_action('wp_enqueue_scripts', [$this, 'Discount_offer_popup_assets']);
        // Admin Scripts 
        add_action('admin_enqueue_scripts', [$this, 'admin_styles']);
        add_action('admin_enqueue_scripts', [$this, 'fraud_analetics_page_script']);
        add_action('wp_ajax_rs_get_refusal_chart_data', [$this,'rs_handle_chart_ajax']);
    }


    public function frontend_styles() {
        // if (!is_checkout()) return;
        wp_enqueue_style('rs-blocker-style', plugin_dir_url(__DIR__) . 'assets/css/frontend.css', [], RS_ORDER_BLOCKER_VERSION);

        wp_enqueue_script('rs-order-blocker-script', plugin_dir_url(__DIR__) . 'assets/js/frontend.js', ['jquery'], RS_ORDER_BLOCKER_VERSION, true);
    }

    public function admin_styles($hook) {
        // Admin Faild orders page css 
        if (!isset($_GET['page']) || $_GET['page'] == 'rs-failed-orders') {
            wp_enqueue_style('rs-faild-orders-page-css', plugin_dir_url(__DIR__) . 'assets/css/admin-faild-orders-page.css', [], RS_ORDER_BLOCKER_VERSION);
        }
        // Admin settings page css 
        if (strpos($hook, 'rs-order-blocker') === false) return;
        wp_enqueue_style('rs-blocker-admin', plugin_dir_url(__DIR__) . 'assets/css/admin-settings.css', [], RS_ORDER_BLOCKER_VERSION);
    }


    public function fraud_analetics_page_script($hook) {
        if (!isset($_GET['page']) || $_GET['page'] !== 'rs-fraud-analytics') return;
        // custom assets
        wp_enqueue_style('rs-fraud-analetics-css', plugin_dir_url(__DIR__) . 'assets/css/admin-fraud-analetics.css', [], RS_ORDER_BLOCKER_VERSION);

        wp_register_script('rs-fraud-analetics-js', plugin_dir_url(__DIR__) . 'assets/js/admin-fraud-analetics-page.js', ['jquery'], RS_ORDER_BLOCKER_VERSION, true);


        // map assets cdn link
        wp_enqueue_style('leaflet-css', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', [], RS_ORDER_BLOCKER_VERSION);
        wp_enqueue_script('leaflet-js', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', [], RS_ORDER_BLOCKER_VERSION, true);



        // Jquery plugin for months selection Filter : Order Refusals graph
        // wp_enqueue_script('jquery-monthpicker', 'https://cdn.jsdelivr.net/npm/monthpicker@5.0.2/dist/monthpicker.min.js', ['jquery'],  null, true);

        // wp_enqueue_style('jquery-monthpicker-css', 'https://cdn.jsdelivr.net/npm/monthpicker@5.0.2/dist/monthpicker.min.css', [], null);
    }


// Fraud analetics page : Chart filter Ajax call 
function rs_handle_chart_ajax() {
    check_ajax_referer( 'rs_chart_data' );

    $from = sanitize_text_field( $_POST['from'] ?? '' );
    $to   = sanitize_text_field( $_POST['to']   ?? '' );

    if ( ! $from || ! $to ) {
        wp_send_json_error( ['message' => 'Invalid range'] );
    }

    $chart = \RS\OrderBlocker\Admin\FraudAnalyticsPage::get_refusal_chart_data( $from, $to );
    wp_send_json_success( $chart );
}









    

    public function Discount_offer_popup_assets() {
        global $post;

        if (!isset($post)) {
            $post = get_post();
        }

        $current_url = home_url(add_query_arg([], $_SERVER['REQUEST_URI']));
        $current_url = strtolower(trailingslashit($current_url));

        $should_load = false;

        // Detect standard WooCommerce cart/checkout
        if (function_exists('is_cart') && is_cart()) {
            $should_load = true;
        }

        if (function_exists('is_checkout') && is_checkout()) {
            $should_load = true;
        }

        // Detect Gutenberg blocks or shortcodes
        if (is_singular() && isset($post->post_content)) {
            $content = $post->post_content;

            if (
                has_shortcode($content, 'woocommerce_cart') ||
                has_shortcode($content, 'woocommerce_checkout') ||
                strpos($content, 'wp:woocommerce/cart') !== false ||
                strpos($content, 'wp:woocommerce/checkout') !== false
            ) {
                $should_load = true;
            }

            // ✅ Detect CartFlows checkout step page
            if (has_shortcode($content, 'cartflows_step') || strpos($content, 'wp:cartflows/step') !== false) {
                $should_load = true;
            }
        }

        // URL fallback
        if (
            strpos($current_url, '/cart/') !== false ||
            strpos($current_url, '/checkout/') !== false ||
            strpos($current_url, 'cartflows-step') !== false
        ) {
            $should_load = true;
        }

        // Allow filter override
        $should_load = apply_filters('rs_popup_should_load_script', $should_load, $post);

        // Load assets if needed
        if ($should_load) {
            wp_enqueue_style(
                'rs-discount-popup-css',
                plugin_dir_url(__DIR__) . 'assets/css/frontend-discount-popup.css',
                [],
                RS_ORDER_BLOCKER_VERSION
            );

            wp_enqueue_script(
                'rs-discount-popup-js',
                plugin_dir_url(__DIR__) . 'assets/js/frontend-discount-popup.js',
                ['jquery'],
                RS_ORDER_BLOCKER_VERSION,
                true
            );

            wp_localize_script('rs-discount-popup-js', 'rsPopupData', [
                'cart_url'     => trailingslashit(wc_get_cart_url()),
                'checkout_url' => trailingslashit(wc_get_checkout_url()),
                'referrer'     => isset($_SERVER['HTTP_REFERER']) ? esc_url_raw($_SERVER['HTTP_REFERER']) : ''
            ]);
        }
    }
}
