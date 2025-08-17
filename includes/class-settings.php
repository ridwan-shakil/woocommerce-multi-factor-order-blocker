<?php

namespace RS\OrderBlocker;

require_once plugin_dir_path(__FILE__) . 'admin/class-failed-orders-table.php';

require_once plugin_dir_path(__FILE__) . 'admin/class-failed-orders-page.php';

require_once plugin_dir_path(__FILE__) . 'admin/class-fraud-analytics-page.php';

use RS\OrderBlocker\Admin\FailedOrdersPage;


if (!defined('ABSPATH')) exit;

class Settings {

    private $option_group = 'rs_order_blocker_settings_group';
    private $option_name  = 'rs_order_blocker_settings';
    private $default_opts = [];

    public function __construct() {
        // Default settings
        $this->default_opts = [
            'enable_blocking'     => 1,
            'enable_call_button'     => 1,
            'enable_whatsapp_button' => 1,
            'popup_message'       => 'দুঃখিত, আপনার অলরেডি একটি অর্ডার সাবমিট করা আছে। আরেকটি অর্ডার করতে দয়া করে নীচের নাম্বারে যোগাযোগ করুন:',
            'cookie_expire_days'    => 0,
            'cookie_expire_hours'   => 0,
            'cookie_expire_minutes' => 30,
            'call_number'        => '017********',
            'whatsapp_number'    => '017********',
            'call_btn_text'      => '📞 Call Us',
            'whatsapp_btn_text'      => '💬 WhatsApp',
            'popup_text_color'    => '#ffffff',
            'popup_bg_color'      => '#ff0000',
            'popup_button_text_color'  => '#000000',
            'popup_button_color'  => '#ffffff',
            'popup_font_family'   => 'Segoe UI',
            'popup_font_size'     => '16',
            'popup_padding'       => '20',
            'popup_margin'        => '10',
            'popup_box_shadow'    => '2px 2px 6px #131313ff',
            'invalid_phone_alert' => 'দুঃখিত, আপনি একটি ভুল নাম্বার লিখেছেন। দয়া করে ১১ ডিজিটের সঠিক নাম্বারটি লিখুন:',
            'show_abandon_popup'    => 0,
            'abandon_popup_message' => 'Wait! Don’t leave — here’s a special offer!',
            'abandon_popup_coupon'  => 'SAVE10',
            'abandon_popup_logo'    => '',
            'abandon_popup_text_color'    => '#ffffff',
            'abandon_popup_bg_color'      => '#00ff6676',
            'abandon_popup_font_family'   => 'Segoe UI',
            'abandon_popup_font_size'     => '16',
            'abandon_popup_padding'       => '20',
            'abandon_popup_box_shadow'    => '2px 2px 6px #00000076',

        ];

        // Hook into admin
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    // Add submenu page under WooCommerce
    public function add_settings_page() {

        // add_submenu_page(
        //     'rs-order-blocker',
        //     __('Fraud Analytics', 'wcorder-blocker'),
        //     __('Fraud Analytics', 'wcorder-blocker'),
        //     'manage_woocommerce',
        //     'rs-fraud-analytics',
        //     [\RS\OrderBlocker\Admin\FraudAnalyticsPage::class, 'render']
        // );

        // // Add export action for product risk list table
        // add_action('admin_init', function () {
        //     if (
        //         isset($_GET['page'], $_GET['action']) &&
        //         $_GET['page'] === 'rs-fraud-analytics' &&
        //         $_GET['action'] === 'export_product_risk'
        //     ) {
        //         // ✅ Load the class manually BEFORE calling it
        //         require_once plugin_dir_path(__FILE__) . '/admin/class-product-risk-list-table.php';

        //         \RS\OrderBlocker\Admin\Product_Risk_List_Table::handle_export();
        //     }
        // });

        // add_action('admin_init', function () {
        //     if (
        //         isset($_GET['page'], $_GET['action']) &&
        //         $_GET['page'] === 'rs-fraud-analytics' &&
        //         $_GET['action'] === 'export_customer_risk'
        //     ) {
        //         require_once plugin_dir_path(__FILE__) . 'admin/class-customer-risk-list-table.php';
        //         \RS\OrderBlocker\Admin\Customer_Risk_List_Table::handle_export();
        //     }
        // });




        add_submenu_page(
            'rs-order-blocker',
            __('Order Blocker Settings', 'wcorder-blocker'),
            __('Order Blocker Settings', 'wcorder-blocker'),
            'manage_woocommerce',
            'rs-order-blocker-settings',
            [$this, 'render_settings_page'],
        );

        add_submenu_page(
            'rs-order-blocker',
            __('Incomplete Orders', 'wcorder-blocker'),
            __('Incomplete Orders', 'wcorder-blocker'),
            'manage_woocommerce',
            'rs-failed-orders',
            [FailedOrdersPage::class, 'render']
        );
    }

    // Render the settings form
    public function render_settings_page() {
?>
        <div class="wrap rs-admin-wrapper">
            <h1></h1>
            <!-- place for notices  -->
            <h1>🛡️ WooCommerce Multi Factor Order Blocker</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields($this->option_group);
                do_settings_sections('rs-order-blocker');
                submit_button('Save Settings');
                ?>
            </form>
        </div>
        <?php
    }



    // Register settings and UI fields
    public function register_settings() {
        // Register the setting with default values and sanitize callback
        register_setting($this->option_group, $this->option_name, [
            'default' => $this->default_opts,
            'sanitize_callback' => [$this, 'sanitize_options']
        ]);

        // Section for blocking configuration
        add_settings_section('rs_block_section', 'Blocking Rules :', function () {
            echo '<h4>After placing an order user can\'t place another order for below period of time:</h4>';
        }, 'rs-order-blocker');

        // Time limit fields
        $this->add_number_field('cookie_expire_days', 'Block Orders For (Days)', 0, 30, 'rs_block_section');
        $this->add_number_field('cookie_expire_hours', 'Block Orders For (Hours)', 0, 23, 'rs_block_section');
        $this->add_number_field('cookie_expire_minutes', 'Block Orders For (Minutes)', 0, 59, 'rs_block_section');

        // Textarea for popup message
        $this->add_field('popup_message', 'Block Order Alert Text', function () {
            $opts = get_option($this->option_name, []);
            $default_msg = $this->default_opts['popup_message'];
        ?>
            <textarea name="<?= $this->option_name ?>[popup_message]" rows="3" cols="50"
                placeholder="<?= esc_attr($default_msg) ?>"><?= esc_textarea($opts['popup_message'] ?? '') ?></textarea>
            <p class="description"> <?php esc_html_e("Leave blank to use default message.", "wcorder-blocker") ?> </p>
        <?php
        });

        // Textarea for invalid phone number alert
        $this->add_wrong_number_textarea_field('invalid_phone_alert', 'Invalid Phone Alert Text');

        // Toggle switches
        $this->add_toggle_field('enable_blocking', 'Enable Blocking', 'Block duplicate orders by Phone, IP, and Device ID');
        $this->add_toggle_field('enable_call_button', 'Enable Call Button');
        $this->add_toggle_field('enable_whatsapp_button', 'Enable WhatsApp Button');

        // Contact fields
        $this->add_text_field('call_number', 'Phone Number', 'e.g. 017********');
        $this->add_text_field('whatsapp_number', 'WhatsApp Number', 'e.g. 017********');
        $this->add_text_field('call_btn_text', 'Call Button Text', 'e.g. 📞 Call Us');
        $this->add_text_field('whatsapp_btn_text', 'WhatsApp Button Text', 'e.g. 💬 WhatsApp');


        // -------------------Design section-------------------
        add_settings_section('popup_design_section', 'Order Blocker Popup Design :', function () {
            echo '<p>Customize the popup design, style and layout.</p>';
        }, 'rs-order-blocker');

        // Color pickers
        $this->add_color_field('popup_text_color', 'Popup Text Color', '#ffffff');
        $this->add_color_field('popup_bg_color', 'Popup Background Color', '#ff0000');
        $this->add_color_field('popup_button_text_color', 'Popup Button Text Color', '#ffffff');
        $this->add_color_field('popup_button_color', 'Popup Button Color', '#000000');

        // Additional design fields
        $this->add_text_field('popup_font_family', 'Font Family', 'e.g. Segoe UI, Arial');
        $this->add_number_field('popup_font_size', 'Font Size (px)', 10, 30);
        $this->add_number_field('popup_padding', 'Popup Padding (px)', 0, 100);
        $this->add_number_field('popup_margin', 'Popup Margin (px)', 0, 100);
        $this->add_text_field('popup_box_shadow', 'Popup Box Shadow (CSS)', 'e.g. 2px 2px 6px black');


        // -------------Discount offer popup section--------------
        add_settings_section('abandon_popup_section', __('Discount Offer Popup :', 'wcorder-blocker'), function () {
            echo '<p>' . esc_html__('Show a coupon popup when a user tries to leave the cart or checkout page.', 'wcorder-blocker') . '</p>';
        }, 'rs-order-blocker');

        $this->add_toggle_field('show_abandon_popup', __('Enable Exit Popup Offer', 'wcorder-blocker'), __('Show this offer when user tries to leave the page.', 'wcorder-blocker'));
        $this->add_text_field('abandon_popup_message', __('Popup Message', 'wcorder-blocker'), 'e.g. Wait! Here’s a special offer just for you');
        $this->add_text_field('abandon_popup_coupon', __('Coupon Code', 'wcorder-blocker'), 'e.g. SAVE10');
        $this->add_text_field('abandon_popup_logo', __('Popup Image/Logo URL', 'wcorder-blocker'), 'Paste image URL (media or hosted)');

        // Color pickers
        $this->add_color_field('abandon_popup_text_color', 'Popup Text Color', '#ffffff');
        $this->add_color_field('abandon_popup_bg_color', 'Popup Background Color', '#0e852eff');

        // Additional design fields
        $this->add_text_field('abandon_popup_font_family', 'Font Family', 'e.g. Segoe UI, Arial');
        $this->add_number_field('abandon_popup_font_size', 'Font Size (px)', 10, 30);
        $this->add_number_field('abandon_popup_padding', 'Popup Padding (px)', 0, 100);
        $this->add_text_field('abandon_popup_box_shadow', 'Popup Box Shadow (CSS)', 'e.g. 2px 2px 6px black');
    }

    // Sanitize and validate settings
    public function sanitize_options($input) {
        $output = [];
        foreach ($this->default_opts as $key => $default) {
            if (in_array($key, ['enable_blocking', 'enable_call_button', 'enable_whatsapp_button'])) {
                $output[$key] = isset($input[$key]) ? 1 : 0;
            } elseif (in_array($key, ['cookie_expire_days', 'cookie_expire_hours', 'cookie_expire_minutes', 'popup_font_size', 'popup_padding', 'popup_margin'])) {
                $output[$key] = isset($input[$key]) ? intval($input[$key]) : intval($default);
            } else {
                $output[$key] = isset($input[$key]) ? sanitize_text_field($input[$key]) : $default;
            }
        }
        return $output;
    }

    public function get_default_options() {
        return $this->default_opts;
    }

    // Helper to add textarea for invalid phone message
    private function add_wrong_number_textarea_field($id, $label) {
        $this->add_field($id, $label, function () use ($id) {
            $opts = get_option($this->option_name, []);
        ?>
            <textarea name="<?= $this->option_name ?>[<?= $id ?>]" rows="3" cols="50"> <?= esc_textarea($opts[$id] ?? '') ?> </textarea>
            <p class="description"><?php esc_html_e("Leave blank to use default message.", "wcorder-blocker") ?> </p>
        <?php
        }, 'popup_design_section');
    }

    // Toggle field UI
    private function add_toggle_field($id, $label, $description = '', $section = null) {
        $this->add_field($id, $label, function () use ($id, $description) {
            $opts = get_option($this->option_name, []);
            $default = isset($this->default_opts[$id]) ? $this->default_opts[$id] : 0;
            $is_enabled = isset($opts[$id]) ? (bool)$opts[$id] : (bool)$default;
            $checked = $is_enabled ? 'checked' : '';
        ?>
            <label class="rs-toggle-switch">
                <input type="checkbox" name="<?= esc_attr($this->option_name) ?>[<?= esc_attr($id) ?>]" value="1" <?= $checked ?> />
                <span class="rs-slider"></span>
            </label>
            <?php if ($description): ?>
                <span style="margin-left:10px;"> <?= esc_html($description) ?> </span>
            <?php endif; ?>
        <?php
        }, $section);
    }

    // Main wrapper for all fields
    private function add_field($id, $title, $callback, $section = null) {
        if (!$section) {
            if (strpos($id, 'popup_') === 0) {
                $section = 'popup_design_section';
            } elseif (strpos($id, 'abandon_') === 0 || strpos($id, 'show_abandon') === 0) {
                $section = 'abandon_popup_section';
            } else {
                $section = 'rs_block_section';
            }
        }

        add_settings_field($id, $title, $callback, 'rs-order-blocker', $section);
    }


    // Text field
    private function add_text_field($id, $label, $placeholder = '', $section = null) {
        $this->add_field($id, $label, function () use ($id, $placeholder) {
            $opts = get_option($this->option_name, []);
            $default = $this->default_opts[$id] ?? '';
            $value = $opts[$id] ?? $default;
        ?>
            <input type="text" name="<?= esc_attr($this->option_name) ?>[<?= esc_attr($id) ?>]"
                value="<?= esc_attr($value) ?>" placeholder="<?= esc_attr($placeholder) ?>">
        <?php
        }, $section);
    }

    // Color field
    private function add_color_field($id, $label, $default_color, $section = null) {
        $this->add_field($id, $label, function () use ($id, $default_color) {
            $opts = get_option($this->option_name, []);
            $value = isset($opts[$id]) && trim($opts[$id]) !== '' ? $opts[$id] : $default_color;
        ?>
            <input type="color" name="<?= esc_attr($this->option_name) ?>[<?= esc_attr($id) ?>]"
                value="<?= esc_attr($value) ?>">
        <?php
        }, $section);
    }

    // Number field
    private function add_number_field($id, $label, $min = 0, $max = 100, $section = null) {
        $this->add_field($id, $label, function () use ($id, $min, $max) {
            $opts = get_option($this->option_name, []);
            $default = $this->default_opts[$id] ?? 0;
            $value = isset($opts[$id]) ? $opts[$id] : $default;
        ?>
            <input type="number" name="<?= esc_attr($this->option_name) ?>[<?= esc_attr($id) ?>]"
                value="<?= esc_attr($value) ?>" min="<?= esc_attr($min) ?>" max="<?= esc_attr($max) ?>">
        <?php
        }, $section);
    }


    // Discount offer popup on cart & checkout page 
    public static function render_abandon_popup_offer() {
        if (is_admin() || (!is_cart() && !is_checkout())) {
            return;
        }

        $opts = get_option('rs_order_blocker_settings', []);

        if (empty($opts['show_abandon_popup'])) return;

        $msg     = esc_html($opts['abandon_popup_message'] ?? 'Wait! Don’t leave — here’s a special offer!');
        $coupon  = esc_html($opts['abandon_popup_coupon'] ?? 'SAVE10');
        $logo    = esc_url($opts['abandon_popup_logo'] ?? '');

        // Styling
        $text_color    = esc_attr($opts['abandon_popup_text_color'] ?? '#ffffff');
        $bg_color      = esc_attr($opts['abandon_popup_bg_color'] ?? '#00ff9954');
        $font_family   = esc_attr($opts['abandon_popup_font_family'] ?? 'Segoe UI');
        $font_size     = intval($opts['abandon_popup_font_size'] ?? 16);
        $padding       = intval($opts['abandon_popup_padding'] ?? 20);
        $box_shadow    = esc_attr($opts['abandon_popup_box_shadow'] ?? '2px 2px 6px #141414ff');

        ?>
        <style>
            #rs-popup {
                background: <?php echo esc_attr($bg_color); ?>;
                color: <?php echo esc_attr($text_color); ?>;
                font-family: <?php echo esc_attr($font_family); ?>;
                font-size: <?php echo absint($font_size); ?>px;
                padding: <?php echo absint($padding); ?>px;
                box-shadow: <?php echo esc_attr($box_shadow); ?>;
                width: 90%;
                max-width: 400px;
                margin: 100px auto;
                text-align: center;
                border-radius: 8px;
            }
        </style>

        <div id="rs-popup-wrapper">
            <div id="rs-popup">
                <?php if ($logo): ?>
                    <img src="<?php echo $logo; ?>" alt="Offer">
                <?php endif; ?>

                <div class="rs-popup-msg"><?php esc_html_e($msg); ?></div>

                <div class="rs-popup-code" style="margin: 10px 0; font-size: 20px; font-weight: bold;">
                    <?php esc_html_e($coupon); ?>
                </div>

                <button class="rs-copy-btn"><?php esc_html_e('Copy Coupon', 'wcorder-blocker'); ?></button>
            </div>

        </div>
<?php
    }
}
