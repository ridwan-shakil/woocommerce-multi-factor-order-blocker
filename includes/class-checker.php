<?php

namespace RS\OrderBlocker;

if (!defined('ABSPATH')) exit;

class Checker {

    public function __construct() {
        //  Set device_id cookie early for all visitors
        add_action('init', [$this, 'maybe_set_device_cookie']);

        // Hook into checkout process to block duplicate orders
        add_action('woocommerce_checkout_process', [$this, 'check_block']);

        // Save IP and device info when order is placed
        add_action('woocommerce_checkout_update_order_meta', [$this, 'save_meta']);
        add_action('woocommerce_checkout_order_created', [$this, 'save_meta']);

        // Support CartFlows AJAX checkout
        add_action('wp_ajax_wc_cartflows_submit_checkout', [$this, 'check_block_ajax']);
        add_action('wp_ajax_nopriv_wc_cartflows_submit_checkout', [$this, 'check_block_ajax']);

        // Support Guttenbarg Checkout page here
        //code here
    }

    //  Always ensure device_id is set
    public function maybe_set_device_cookie() {
        if (is_admin()) return;

        if (empty($_COOKIE['device_id'])) {
            $new_id = bin2hex(random_bytes(10));

            // Set to expire in 30 days by default (you can use your custom logic)
            setcookie('device_id', $new_id, time() + (30 * DAY_IN_SECONDS), '/');

            // Make it available in the current request
            $_COOKIE['device_id'] = $new_id;
        }
    }


    private function get_settings() {
        return get_option('rs_order_blocker_settings', []);
    }

    /**
     * Main logic to block order based on phone, IP, or device cookie
     */
    public function check_block() {
        $settings = $this->get_settings();

        if (empty($settings['enable_blocking'])) return;

        $email = sanitize_email($_POST['billing_email'] ?? '');
        $phone = sanitize_text_field($_POST['billing_phone'] ?? '');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown_ip';
        $device_id = $_COOKIE['device_id'] ?? '';

        $args = [
            'limit' => -1,
            'status' => ['pending', 'processing', 'on-hold'],
        ];

        // ✅ Invalid phone check
        if ($phone && !preg_match('/^01[3-9]\d{8}$/', $phone)) {
            $msg = esc_html($settings['invalid_phone_alert'] ?? '');
            $alert_msg = !empty($msg) ? $msg : 'দুঃখিত, আপনি একটি ভুল নাম্বার লিখেছেন। দয়া করে ১১ ডিজিটের সঠিক নাম্বারটি লিখুন:';

            // style settings
            $text_color = esc_attr($settings['popup_text_color'] ?? '#000');
            $bg_color   = esc_attr($settings['popup_bg_color'] ?? '#fff');
            $font       = esc_attr($settings['popup_font_family'] ?? 'Segoe UI');
            $font_size  = esc_attr($settings['popup_font_size'] ?? '16');
            $padding    = esc_attr($settings['popup_padding'] ?? '20');
            $margin     = esc_attr($settings['popup_margin'] ?? '10');
            $box_shadow = esc_attr($settings['popup_box_shadow'] ?? '2px 2px 6px rgba(0,0,0,0.1)');

            $styled_notice = "<div class='rs-block-msg rs-invalid-phone-alert'
                style='position: relative;
                background: $bg_color;
                color: $text_color;
                font-family: $font;
                font-size: {$font_size}px;
                padding: {$padding}px;
                margin: {$margin}px;
                box-shadow: $box_shadow;
                border-radius: 10px;
                text-align: center;'>
                    <div class='rs-close-btn'>&times;</div>
                    <p> $alert_msg </p>
                </div>";

            wc_add_notice($styled_notice, 'error');
            return;
        }

        // ✅ Get blocking duration
        $days    = intval($settings['cookie_expire_days'] ?? 0);
        $hours   = intval($settings['cookie_expire_hours'] ?? 0);
        $minutes = intval($settings['cookie_expire_minutes'] ?? 0);
        $block_duration = ($days * 86400) + ($hours * 3600) + ($minutes * 60);
        $current_time = time();

        $orders = wc_get_orders($args);
        foreach ($orders as $order) {
            $order_id = $order->get_id();
            $order_phone = $order->get_billing_phone();
            $order_ip = get_post_meta($order_id, '_user_ip', true);
            $order_device = get_post_meta($order_id, '_device_id', true);
            $set_time = intval(get_post_meta($order_id, '_device_set_time', true));

            // ✅ Fallback to order created time if _device_set_time is missing
            if (!$set_time) {
                $set_time = strtotime($order->get_date_created());
            }

            if (empty($order_phone) && empty($order_ip) && empty($order_device)) {
                continue; // 🚫 Skip orders without any matching info
            }

            // ✅ Skip if this order is older than the block duration
            if (($current_time - $set_time) > $block_duration) {
                continue;
            }

            // ✅ Block if any match found
            if (
                ($phone && $order_phone && $phone === $order_phone) ||
                ($ip && $order_ip && $ip === $order_ip) ||
                ($device_id && $order_device && $device_id === $order_device)
            ) {
                wc_add_notice($this->get_popup_html($settings), 'error');
                return;
            }
        }
    }


    /**
     * Duplicate of check_block but for CartFlows AJAX submission
     */
    public function check_block_ajax() {
        $settings = $this->get_settings();

        if (empty($settings['enable_blocking'])) {
            wp_send_json_success(); // Allow the order
            return;
        }

        $email     = sanitize_email($_POST['billing_email'] ?? '');
        $phone     = sanitize_text_field($_POST['billing_phone'] ?? '');
        $ip        = $_SERVER['REMOTE_ADDR'] ?? 'unknown_ip';
        $device_id = $_COOKIE['device_id'] ?? '';

        $args = [
            'limit'  => -1,
            'status' => ['pending', 'processing', 'on-hold'],
        ];

        // ✅ Get blocking duration
        $days    = intval($settings['cookie_expire_days'] ?? 0);
        $hours   = intval($settings['cookie_expire_hours'] ?? 0);
        $minutes = intval($settings['cookie_expire_minutes'] ?? 0);
        $block_duration = ($days * 86400) + ($hours * 3600) + ($minutes * 60);
        $current_time = time();

        $orders = wc_get_orders($args);
        foreach ($orders as $order) {
            $order_id     = $order->get_id();
            $order_phone  = $order->get_billing_phone();
            $order_ip     = get_post_meta($order_id, '_user_ip', true);
            $order_device = get_post_meta($order_id, '_device_id', true);
            $set_time     = intval(get_post_meta($order_id, '_device_set_time', true));

            // ✅ Fallback to order created time if set_time is not saved
            if (!$set_time) {
                $set_time = strtotime($order->get_date_created());
            }

            if (empty($order_phone) && empty($order_ip) && empty($order_device)) {
                continue; // 🚫 Skip orders without any matching info
            }

            // ✅ Skip if block duration has passed
            if (($current_time - $set_time) > $block_duration) {
                continue;
            }

            // ✅ Match phone, IP, or device ID
            if (
                ($phone && $order_phone && $phone === $order_phone) ||
                ($ip && $order_ip && $ip === $order_ip) ||
                ($device_id && $order_device && $device_id === $order_device)
            ) {
                wp_send_json_error(['messages' => $this->get_popup_html($settings)]);
                return;
            }
        }

        wp_send_json_success(); // ✅ All checks passed
    }



    /**
     * Save metadata when order is created
     */
    public function save_meta($order_id) {
        $settings = $this->get_settings();
        if (empty($settings['enable_blocking'])) return;

        update_post_meta($order_id, '_user_ip', sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        update_post_meta($order_id, '_device_id', sanitize_text_field($_COOKIE['device_id'] ?? 'unknown'));
        update_post_meta($order_id, '_device_set_time', time());
    }

    /**
     * Render the custom styled popup HTML
     */
    private function get_popup_html($opts) {
        $msg = esc_html($opts['popup_message'] ?? 'দুঃখিত, আপনার অলরেডি একটি অর্ডার সাবমিট করা আছে। আরেকটি অর্ডার করতে দয়া করে নীচের নাম্বারে যোগাযোগ করুন:');
        $show_call = !empty($opts['enable_call_button']);
        $show_wa   = !empty($opts['enable_whatsapp_button']);

        $call_text = esc_html($opts['call_btn_text'] ?? '📞 Call Us');
        $call      = esc_attr($opts['call_number'] ?? '');
        $wa_text   = esc_html($opts['whatsapp_btn_text'] ?? '💬 WhatsApp');
        $wa        = esc_attr($opts['whatsapp_number'] ?? '');

        $text_color     = esc_attr($opts['popup_text_color'] ?? 'white');
        $bg_color       = esc_attr($opts['popup_bg_color'] ?? 'red');
        $btn_text_color = esc_attr($opts['popup_button_text_color'] ?? 'black');
        $btn_color      = esc_attr($opts['popup_button_color'] ?? 'white');
        $font           = esc_attr($opts['popup_font_family'] ?? 'Segoe UI');
        $font_size      = esc_attr($opts['popup_font_size'] ?? '14');
        $padding        = esc_attr($opts['popup_padding'] ?? '20');
        $margin         = esc_attr($opts['popup_margin'] ?? '10');
        $box_shadow     = esc_attr($opts['popup_box_shadow'] ?? '2px 2px 6px rgba(0,0,0,0.1)');

        $buttons = '';
        if ($show_call) {
            $buttons .= "<a href='tel:$call' class='rs-call' style='background: $btn_color; color: $btn_text_color;'>$call_text</a>";
        }
        if ($show_wa) {
            $buttons .= "<a href='https://wa.me/$wa' class='rs-wa' style='background: $btn_color; color: $btn_text_color;'>$wa_text</a>";
        }

        return "<div class='rs-block-msg' style='position: relative; background: $bg_color; color: $text_color; font-family: $font; font-size: {$font_size}px; padding: {$padding}px; margin: {$margin}px; box-shadow: $box_shadow; border-radius: 10px; text-align: center;'>
            <div class='rs-close-btn'>&times;</div>
            <p>{$msg}</p>
            <div class='rs-btns'>$buttons</div>
        </div>";
    }
}
//Dev: ridwansweb@gmail.com
