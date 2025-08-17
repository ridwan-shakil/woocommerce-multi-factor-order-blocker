<?php

namespace RS\OrderBlocker;

if (!defined('ABSPATH')) exit;

// Load core
foreach (
    [
        'class-settings.php',
        'class-checker.php',
        'class-assets.php',
        'class-incomplete-orders-tracker.php',
        'class-blocked-users.php'
    ] as $f
) {
    include plugin_dir_path(__FILE__) . $f;
}

// Export failed orders data in CSV format
add_action('admin_init', ['RS\\OrderBlocker\\Admin\\FailedOrdersPage', 'maybe_export_csv']);

final class Plugin {
    public function __construct() {
        add_action('admin_enqueue_scripts', [$this, 'i']);
        add_action('admin_init', [$this, 'r']);
        add_action('admin_notices', [$this, 'n']);
        add_action('plugins_loaded', [$this, 'a']);
        add_action('admin_menu', [$this, 'm']);
        add_action('admin_head', [$this, 'block_default_admin_notices']);
        add_action('wp_footer', ['\\RS\\OrderBlocker\\Settings', 'render_abandon_popup_offer']);
    }

    public function i($hook) {
        $p = $_GET['page'] ?? '';
        $files = plugin_dir_url(__DIR__) . 'assets/css/';
        if ($hook === 'toplevel_page_rs-order-blocker' || in_array($p, ['rs-order-blocker-settings', 'rs-failed-orders', 'rs-blocked-users'])) {
            wp_enqueue_style('ob-ui', $files . 'admin-license.css', [], RS_ORDER_BLOCKER_VERSION);
            wp_enqueue_style('ob-alert', $files . 'admin-license-alert.css', [], RS_ORDER_BLOCKER_VERSION);
        }
    }


    /**
     * Hide default admin notices on specific plugin pages, except plugin's own custom notices.
     */
    function block_default_admin_notices() {
        $screen = get_current_screen();
        if (! $screen) {
            return;
        }

        $target_screens = array(
            'toplevel_page_rs-order-blocker',
            'order-blocker_page_rs-failed-orders',
            'order-blocker_page_rs-fraud-analytics',
            'order-blocker_page_rs-order-blocker-settings',
            'order-blocker_page_rs-blocked-users',
        );

        if (in_array($screen->id, $target_screens, true)) {
            echo '<style>
            .notice:not(.rs-notice),
            .update-nag {
                display: none !important;
            }
        </style>';
        }
    }



    public function r() {
        if (!is_admin()) return;
        if (get_option('rs_ob_redirect_to_license', 0)) {
            delete_option('rs_ob_redirect_to_license');
            wp_safe_redirect(admin_url('admin.php?page=rs-order-blocker'));
            exit;
        }
    }

    public function n() {
        if (class_exists('WooCommerce')) return;
        $this->s_notice('WooCommerce is required.', 'install-plugin_woocommerce', 'woocommerce');
    }

    private function s_notice($txt, $nonce, $slug) {
        $url = file_exists(WP_PLUGIN_DIR . "/woocommerce/woocommerce.php")
            ? wp_nonce_url(admin_url('plugins.php?action=activate&plugin=woocommerce'), $nonce)
            : wp_nonce_url(admin_url('update.php?action=install-plugin&plugin=' . $slug), $nonce);
        echo "<div class='notice notice-error is-dismissible'><p><strong>Order Blocker:</strong> " . esc_html($txt) . " </p><p><a href='" . esc_url($url) . "' class='button button-primary'>" . (strpos($url, 'activate') ? 'Activate' : 'Install') . " WooCommerce</a></p></div>";
    }

    public function a() {
        $this->h();
        if ($this->v()) {
            foreach ($this->L() as $c) new $c();
        }
    }

    public function m() {
        add_menu_page('Order Blocker', 'Order Blocker', 'manage_options', 'rs-order-blocker', [$this, 'p'], 'dashicons-table-col-before', 25);
        add_submenu_page('rs-order-blocker', 'License', 'License', 'manage_options', 'rs-order-blocker', [$this, 'p']);
        if (!$this->v()) {
            foreach (
                [
                    'Order Blocker Settings' => 'rs-order-blocker-settings',
                    'Failed Orders' => 'rs-failed-orders',
                    'Blocked Users' => 'rs-blocked-users'
                ] as $t => $s
            ) {
                add_submenu_page('rs-order-blocker', $t, $t, 'manage_options', $s, [$this, 'd']);
            }
        }
    }

    public function p() {
        $this->u(function ($k, $s) {
            $expires = get_option('rs_ob_license_expires');
?>
            <div class="license-page" oncontextmenu="return false;" ondragstart="return false;" onselectstart="return false;">
                <div class="wrap ob-license-wrapper">
                    <div class="rs_ob_license_head">
                        <h1></h1>
                    </div>
                    <div class="license-heading">
                        <h1 class="ob-license-title"><?php esc_html_e("Plugin License Activation", "wcorder-blocker") ?></h1>
                    </div>

                    <?php if ($s !== 'valid'): ?>
                        <form method="post" class="ob-license-form">
                            <?php wp_nonce_field('rs_license_action', 'rs_license_nonce'); ?>
                            <label for="rsk"><?php esc_html_e("Enter Your License Key:", "wcorder-blocker") ?></label>
                            <input name="rsk" id="rsk" type="text" value="" class="ob-license-input" placeholder="XXXX-XXXX-XXXX-XXXX" autocomplete="off" required />
                            <button type="submit" class="ob-license-btn"><?php esc_html_e("Save & Activate", "wcorder-blocker") ?></button>
                        </form>
                    <?php endif; ?>

                    <?php if ($k): ?>
                        <form method="post" class="ob-license-remove-form" onsubmit="return confirm('Are you sure you want to remove this license?');">
                            <div class="rs_ob_license_btns">
                                <input type="hidden" name="rsk_clear" value="1" />
                                <button type="submit" class="button-secondary"><?php esc_html_e('Remove License', 'wcorder-blocker') ?></button>
                            </div>
                        </form>
                    <?php endif; ?>


                    <?php if ($s): ?>
                        <div class="ob-license-status <?php echo ($s === 'valid' ? 'ob-valid' : 'ob-invalid'); ?>">
                            <p>
                                <?php esc_html_e("License status:", "wcorder-blocker"); ?>
                                <strong>
                                    <?php
                                    echo esc_html(
                                        $s === 'valid'
                                            ? __('Active', 'wcorder-blocker')
                                            : ($s === 'expired' ? __('Expired', 'wcorder-blocker') : ucfirst($s))
                                    );
                                    ?>
                                </strong>

                                <?php
                                $expiry_timestamp = get_option('rs_ob_license_expires');
                                if ($s === 'valid'):
                                ?>
                                    <!-- <span class="ob-expiry">
                                        (<?php printf(__('expires on %s', 'wcorder-blocker'), esc_html(date_i18n(get_option('date_format'), $expiry_timestamp))); ?>)
                                    </span> -->
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        <?php
        });
    }

    public function d() {
        ?>
        <div class="rs-license-blocker-wrap">
            <div class="rs-license-box">
                <h2><?php esc_html_e("🔒 License Required", 'wcorder-blocker') ?></h2>
                <p><?php esc_html_e("Please activate your license key to unlock all features.", "wcorder-blocker") ?></p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=rs-order-blocker')); ?>" class="rs-license-btn"><?php esc_html_e("Activate License", "wcorder-blocker") ?></a>
            </div>
        </div>
<?php
    }

    private function u($cb) {
        if (!current_user_can('manage_options')) return;

        if (isset($_POST['rsk']) && check_admin_referer('rs_license_action', 'rs_license_nonce')) {
            $n = sanitize_text_field($_POST['rsk']);
            $o = get_option(self::e('a2V5'), '');
            if ($n !== $o) update_option(self::e('c3RhdHVz'), '');
            update_option(self::e('a2V5'), $n);
            $this->q();
        }

        $cb(
            get_option(self::e('a2V5'), ''),
            get_option(self::e('c3RhdHVz'), '')
        );

        if (isset($_POST['rsk_clear'])) {
            delete_option(self::e('a2V5'));
            delete_option(self::e('c3RhdHVz'));
            delete_option('rs_ob_already_activated');
            delete_option('rs_ob_license_expires');
            wp_safe_redirect(admin_url('admin.php?page=rs-order-blocker'));
            exit;
        }
    }

    private function q() {
        $k = get_option(self::e('a2V5'));
        if (!$k) return;

        if (get_option('rs_ob_already_activated')) {
            $this->f();
            return;
        }

        $res = wp_remote_get('https://webersayaf.com/wp-json/lmfwc/v2/licenses/activate/' . rawurlencode($k), [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode('ck_0a6d6486d87afb3db83e74c7703cc6180fe5d69b:cs_e128e7da177fdd8e2575bf08bda1097f5e7e0fbc')
            ],
            'timeout' => 30
        ]);

        if (is_wp_error($res)) {
            update_option(self::e('c3RhdHVz'), 'invalid');
            return;
        }

        $body = json_decode(wp_remote_retrieve_body($res), true);

        if (!empty($body['success']) && $body['success']) {
            update_option('rs_ob_already_activated', true);
            if (!empty($body['data']['expiresAt'])) {
                update_option('rs_ob_license_expires', strtotime($body['data']['expiresAt']));
            }
            $this->f();
        } else {
            update_option(self::e('c3RhdHVz'), 'invalid');
        }
    }

    private function f() {
        $k = get_option(self::e('a2V5'));
        if (!$k) return;

        $res = wp_remote_get('https://webersayaf.com/wp-json/lmfwc/v2/licenses/validate/' . rawurlencode($k), [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode('ck_0a6d6486d87afb3db83e74c7703cc6180fe5d69b:cs_e128e7da177fdd8e2575bf08bda1097f5e7e0fbc')
            ],
            'timeout' => 30
        ]);

        if (is_wp_error($res)) return;

        $body = json_decode(wp_remote_retrieve_body($res), true);
        $valid = !empty($body['success']) && $body['success'] === true;
        update_option(self::e('c3RhdHVz'), $valid ? 'valid' : 'invalid');
        update_option(self::e('bGFzdA=='), time());
    }

    private function h() {
        if (!is_admin()) return;

        $last = get_option(self::e('bGFzdA=='), 0);
        if (time() - intval($last) > WEEK_IN_SECONDS) {
            $this->f();
            update_option(self::e('bGFzdA=='), time());
        }
    }

    private function v() {
        return get_option(self::e('c3RhdHVz')) === 'valid';
    }

    private static function e($t) {
        return base64_decode($t);
    }

    private function L() {
        return array_map(function ($x) {
            return str_replace('::', '\\', base64_decode($x));
        }, [
            'UlM6Ok9yZGVyQmxvY2tlcjo6U2V0dGluZ3M=',
            'UlM6Ok9yZGVyQmxvY2tlcjo6QXNzZXRz',
            'UlM6Ok9yZGVyQmxvY2tlcjo6Q2hlY2tlcg==',
            'UlM6Ok9yZGVyQmxvY2tlcjo6VHJhY2tlcg==',
            'UlM6Ok9yZGVyQmxvY2tlcjo6QmxvY2tlZFVzZXJz'
        ]);
    }
}

new Plugin();
