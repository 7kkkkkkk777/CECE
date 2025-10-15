<?php

namespace CouponImporter\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class Settings {

    public function __construct() {
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_ajax_ci7k_test_ai_connection', array($this, 'ajax_test_ai_connection'));
    }

    public function register_settings() {
        register_setting('ci7k_settings_group', 'ci7k_settings', array($this, 'sanitize_settings'));
    }

    public function sanitize_settings($input) {
        $sanitized = array();

        if (isset($input['ai_provider'])) {
            $sanitized['ai_provider'] = sanitize_text_field($input['ai_provider']);
        }

        if (isset($input['openai_api_key'])) {
            $sanitized['openai_api_key'] = sanitize_text_field($input['openai_api_key']);
        }

        if (isset($input['openai_model'])) {
            $sanitized['openai_model'] = sanitize_text_field($input['openai_model']);
        }

        if (isset($input['gemini_api_key'])) {
            $sanitized['gemini_api_key'] = sanitize_text_field($input['gemini_api_key']);
        }

        if (isset($input['gemini_model'])) {
            $sanitized['gemini_model'] = sanitize_text_field($input['gemini_model']);
        }

        $sanitized['auto_publish'] = isset($input['auto_publish']) ? 1 : 0;
        $sanitized['require_approval'] = isset($input['require_approval']) ? 1 : 0;
        $sanitized['ai_rewrite_enabled'] = isset($input['ai_rewrite_enabled']) ? 1 : 0;
        $sanitized['delete_on_publish'] = isset($input['delete_on_publish']) ? 1 : 0;

        return $sanitized;
    }

    public function render() {
        if (isset($_POST['ci7k_settings_submit'])) {
            check_admin_referer('ci7k_settings_nonce');

            $settings = array();

            if (isset($_POST['ai_provider'])) {
                $settings['ai_provider'] = sanitize_text_field($_POST['ai_provider']);
            }

            if (isset($_POST['openai_api_key'])) {
                $settings['openai_api_key'] = sanitize_text_field($_POST['openai_api_key']);
            }

            if (isset($_POST['openai_model'])) {
                $settings['openai_model'] = sanitize_text_field($_POST['openai_model']);
            }

            if (isset($_POST['gemini_api_key'])) {
                $settings['gemini_api_key'] = sanitize_text_field($_POST['gemini_api_key']);
            }

            if (isset($_POST['gemini_model'])) {
                $settings['gemini_model'] = sanitize_text_field($_POST['gemini_model']);
            }

            $settings['auto_publish'] = isset($_POST['auto_publish']) ? 1 : 0;
            $settings['require_approval'] = isset($_POST['require_approval']) ? 1 : 0;
            $settings['ai_rewrite_enabled'] = isset($_POST['ai_rewrite_enabled']) ? 1 : 0;
            $settings['delete_on_publish'] = isset($_POST['delete_on_publish']) ? 1 : 0;

            update_option('ci7k_settings', $settings);

            echo '<div class="notice notice-success"><p>' . __('Configurações salvas com sucesso!', '7k-coupons-importer') . '</p></div>';
        }

        $settings = get_option('ci7k_settings', array());

        require_once CI7K_PLUGIN_DIR . 'admin/views/settings.php';
    }

    public function ajax_test_ai_connection() {
        check_ajax_referer('ci7k_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permissão negada', '7k-coupons-importer')));
        }

        $provider = isset($_POST['provider']) ? sanitize_text_field($_POST['provider']) : '';

        if (empty($provider)) {
            wp_send_json_error(array('message' => __('Provedor não especificado', '7k-coupons-importer')));
        }

        $core = \CouponImporter\Core::get_instance();
        $ai = $core->get_ai_rewriter();

        $result = $ai->test_connection($provider);

        if ($result) {
            wp_send_json_success(array('message' => __('Conexão estabelecida com sucesso!', '7k-coupons-importer')));
        } else {
            wp_send_json_error(array('message' => __('Falha ao conectar com a API', '7k-coupons-importer')));
        }
    }
}
