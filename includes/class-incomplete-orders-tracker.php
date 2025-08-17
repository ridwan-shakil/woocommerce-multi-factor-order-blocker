<?php

namespace RS\OrderBlocker;

if (!defined('ABSPATH')) {
    exit;
}

class Tracker {

    protected $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'wc_order_blocker_incomplete_orders';

        add_action('wp', [$this, 'maybe_track_cart_or_checkout']);
        add_action('wp_enqueue_scripts', [$this, 'get_checkout_info_script']);
        add_action('wp_ajax_rs_capture_checkout_data', [$this, 'rs_capture_checkout_data']);
        add_action('wp_ajax_nopriv_rs_capture_checkout_data', [$this, 'rs_capture_checkout_data']);

        add_action('woocommerce_checkout_order_processed', [$this, 'cleanup_on_order_success'], 10, 3);
        add_action('woocommerce_payment_complete', [$this, 'cleanup_on_order_success'], 10, 1);
        add_action('woocommerce_thankyou', [$this, 'cleanup_on_order_success'], 10, 1);
    }

    /**
     * Helper: unique visitor/session key
     */
    protected function get_visitor_key() {
        return WC()->session ? WC()->session->get_customer_id() : '';
    }


    /**
     * Track cart/checkout visit
     */
    public function maybe_track_cart_or_checkout() {
        if (is_admin() || !function_exists('WC')) {
            return;
        }

        $wc   = WC();
        $cart = $wc->cart;
        if (empty($cart) || !$cart->get_cart_contents_count()) {
            return;
        }

        $status = (is_cart() ? 'cart' : (is_checkout() ? 'checkout' : null));
        if (!$status) return;

        $visitor_key    = $this->get_visitor_key();
        $cart_items     = $cart->get_cart_contents();
        $cart_total     = $cart->get_total('edit');
        $customer_email = $wc->session->get('customer_email');
        $customer_phone = $wc->session->get('billing_phone');

        global $wpdb;

        // --- lookup (email → phone → session)
        $existing_id = null;
        if ($customer_email) {
            $existing_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$this->table} WHERE email = %s",
                $customer_email
            ));
        }
        if (!$existing_id && $customer_phone) {
            $existing_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$this->table} WHERE mobile = %s",
                $customer_phone
            ));
        }
        if (!$existing_id && $visitor_key) {
            $existing_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$this->table} WHERE session_id = %s",
                $visitor_key
            ));
        }

        $data = [
            'session_id'    => $visitor_key,
            'status'        => $status,
            'cart_contents' => maybe_serialize($cart_items),
            'total'         => floatval($cart_total),
            'updated_at'    => current_time('mysql'),
        ];

        if (!empty($customer_email)) $data['email']  = $customer_email;
        if (!empty($customer_phone)) $data['mobile'] = $customer_phone;

        if ($existing_id) {
            $wpdb->update($this->table, $data, ['id' => $existing_id]);
        } else {
            $data['created_at'] = current_time('mysql');
            $wpdb->insert($this->table, $data);
        }
    }


    /**
     * Enqueue JS to capture checkout data
     */
    public function get_checkout_info_script() {
        wp_enqueue_script(
            'rs-get-checkout-data-js',
            plugin_dir_url(__DIR__) . 'assets/js/incomplete-order-checkout-data.js',
            ['jquery'],
            RS_ORDER_BLOCKER_VERSION,
            true
        );

        wp_localize_script('rs-get-checkout-data-js', 'rs_checkout_capture', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('rs_checkout_capture_nonce'),
        ]);
    }


    /**
     * Capture checkout data via AJAX
     */
    public function rs_capture_checkout_data() {
        check_ajax_referer('rs_checkout_capture_nonce', 'nonce');

        $email       = sanitize_email($_POST['email'] ?? '');
        $name        = sanitize_text_field($_POST['name'] ?? '');
        $phone       = sanitize_text_field($_POST['phone'] ?? '');
        $visitor_key = $this->get_visitor_key();

        if (!$email && !$name && !$phone) {
            wp_send_json_error('Missing data');
        }

        global $wpdb;

        // --- lookup (email → phone → session)
        $existing_id = null;
        if ($email) {
            $existing_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$this->table} WHERE email = %s",
                $email
            ));
        }
        if (!$existing_id && $phone) {
            $existing_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$this->table} WHERE mobile = %s",
                $phone
            ));
        }
        if (!$existing_id && $visitor_key) {
            $existing_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$this->table} WHERE session_id = %s",
                $visitor_key
            ));
        }

        $data = [
            'session_id' => $visitor_key,
            'updated_at' => current_time('mysql'),
        ];

        // only add non-empty fields
        if (!empty($email))  $data['email']         = $email;
        if (!empty($name))   $data['customer_name'] = $name;
        if (!empty($phone))  $data['mobile']        = $phone;

        if ($existing_id) {
            $wpdb->update($this->table, $data, ['id' => $existing_id]);
        } else {
            $data['created_at'] = current_time('mysql');
            $wpdb->insert($this->table, $data);
        }

        wp_send_json_success('Captured');
    }


    /**
     * Cleanup on successful order
     */
    public function cleanup_on_order_success($order_id, $posted_data = null, $order_obj = null) {
        if (!$order_id) return;
        $order = $order_obj instanceof \WC_Order ? $order_obj : wc_get_order($order_id);
        if (!$order || !is_a($order, 'WC_Order')) return;

        $email      = $order->get_billing_email();
        $phone      = $order->get_billing_phone();
        $visitor_key = $this->get_visitor_key();

        global $wpdb;
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table} WHERE email = %s OR mobile = %s OR session_id = %s",
                $email,
                $phone,
                $visitor_key
            )
        );
    }
}
