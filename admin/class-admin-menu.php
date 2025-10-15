<?php

namespace CouponImporter\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class AdminMenu {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_pages'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        
        // Adicionar handler AJAX para toggle de provedor
        add_action('wp_ajax_ci7k_toggle_provider', array($this, 'ajax_toggle_provider'));
    }

    public function ajax_toggle_provider() {
        check_ajax_referer('ci7k_import_nonce', '_ajax_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permissão negada', '7k-coupons-importer')));
        }
        
        $provider = sanitize_text_field($_POST['provider']);
        $enabled = intval($_POST['enabled']);
        
        if (!in_array($provider, array('rakuten', 'awin'))) {
            wp_send_json_error(array('message' => __('Provedor inválido', '7k-coupons-importer')));
        }
        
        update_option("ci7k_{$provider}_enabled", $enabled);
        
        wp_send_json_success(array(
            'message' => $enabled ? 
                __('Provedor ativado com sucesso!', '7k-coupons-importer') : 
                __('Provedor desativado com sucesso!', '7k-coupons-importer'),
            'enabled' => $enabled
        ));
    }

    public function add_admin_pages() {
        add_menu_page(
            __('7K Coupons Importer', '7k-coupons-importer'),
            __('7K Coupons', '7k-coupons-importer'),
            'manage_options',
            'ci7k-dashboard',
            array($this, 'render_dashboard'),
            'dashicons-tickets-alt',
            30
        );

        add_submenu_page(
            'ci7k-dashboard',
            __('Dashboard', '7k-coupons-importer'),
            __('Dashboard', '7k-coupons-importer'),
            'manage_options',
            'ci7k-dashboard',
            array($this, 'render_dashboard')
        );

        add_submenu_page(
            'ci7k-dashboard',
            __('Importar', '7k-coupons-importer'),
            __('Importar', '7k-coupons-importer'),
            'manage_options',
            'ci7k-import',
            array($this, 'render_import')
        );

        add_submenu_page(
            'ci7k-dashboard',
            __('Curadoria', '7k-coupons-importer'),
            __('Curadoria', '7k-coupons-importer'),
            'manage_options',
            'ci7k-curation',
            array($this, 'render_curation')
        );

        add_submenu_page(
            'ci7k-dashboard',
            __('Provedores', '7k-coupons-importer'),
            __('Provedores', '7k-coupons-importer'),
            'manage_options',
            'ci7k-providers',
            array($this, 'render_providers')
        );

        add_submenu_page(
            'ci7k-dashboard',
            __('Logs', '7k-coupons-importer'),
            __('Logs', '7k-coupons-importer'),
            'manage_options',
            'ci7k-logs',
            array($this, 'render_logs')
        );

        add_submenu_page(
            'ci7k-dashboard',
            __('Configurações', '7k-coupons-importer'),
            __('Configurações', '7k-coupons-importer'),
            'manage_options',
            'ci7k-settings',
            array($this, 'render_settings')
        );

        // Remover página de correções do menu (será movida para configurações)
        // add_submenu_page(
        //     'ci7k-dashboard',
        //     __('Correções', '7k-coupons-importer'),
        //     __('Correções', '7k-coupons-importer'),
        //     'manage_options',
        //     'ci7k-fixes',
        //     array($this, 'render_fixes')
        // );
    }

    public function enqueue_assets($hook) {
        if (strpos($hook, 'ci7k-') === false) {
            return;
        }

        wp_enqueue_style('ci7k-admin', CI7K_PLUGIN_URL . 'assets/css/admin.css', array(), CI7K_VERSION);
        wp_enqueue_script('ci7k-admin', CI7K_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), CI7K_VERSION, true);

        wp_localize_script('ci7k-admin', 'ci7k_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ci7k_nonce'),
            'strings' => array(
                'confirm_delete' => __('Tem certeza que deseja remover este cupom?', '7k-coupons-importer'),
                'confirm_bulk' => __('Tem certeza que deseja executar esta ação em massa?', '7k-coupons-importer'),
                'processing' => __('Processando...', '7k-coupons-importer'),
                'error' => __('Erro ao processar requisição', '7k-coupons-importer'),
                'success' => __('Operação concluída com sucesso', '7k-coupons-importer')
            )
        ));
    }

    public function render_dashboard() {
        require_once CI7K_PLUGIN_DIR . 'admin/views/dashboard.php';
    }

    public function render_curation() {
        // Obter a instância da CurationAdmin do Core
        $core = \CouponImporter\Core::get_instance();
        $curation = new \CouponImporter\Admin\CurationAdmin();
        $curation->render();
    }

    public function render_import() {
        require_once CI7K_PLUGIN_DIR . 'admin/views/import.php';
    }

    public function render_providers() {
        require_once CI7K_PLUGIN_DIR . 'admin/views/providers.php';
    }

    public function render_settings() {
        $settings = new Settings();
        $settings->render();
    }

    public function render_logs() {
        require_once CI7K_PLUGIN_DIR . 'admin/views/logs.php';
    }

    public function render_fixes() {
        // Processar ação de correção se solicitada
        if (isset($_POST['fix_all_coupons']) && wp_verify_nonce($_POST['_wpnonce'], 'ci7k_fix_coupons')) {
            $core = \CouponImporter\Core::get_instance();
            $mapper = $core->get_mapper();
            $fixed_count = $mapper->fix_all_published_coupons();
            
            echo '<div class="notice notice-success"><p>';
            printf(__('%d cupons foram corrigidos com sucesso!', '7k-coupons-importer'), $fixed_count);
            echo '</p></div>';
        }

        require_once CI7K_PLUGIN_DIR . 'admin/views/fixes.php';
    }
}