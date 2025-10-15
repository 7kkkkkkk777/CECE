<?php

namespace CouponImporter;

if (!defined('ABSPATH')) {
    exit;
}

class Core {

    private static $instance = null;
    private $cpt;
    private $logger;
    private $queue;
    private $mapper;
    private $ai_rewriter;
    private $admin;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    private function load_dependencies() {
        require_once CI7K_PLUGIN_DIR . 'includes/class-cpt.php';
        require_once CI7K_PLUGIN_DIR . 'includes/class-logger.php';
        require_once CI7K_PLUGIN_DIR . 'includes/class-queue.php';
        require_once CI7K_PLUGIN_DIR . 'includes/class-mapper.php';
        require_once CI7K_PLUGIN_DIR . 'includes/class-ai-rewriter.php';
        require_once CI7K_PLUGIN_DIR . 'includes/providers/class-provider-interface.php';
        require_once CI7K_PLUGIN_DIR . 'includes/providers/class-rakuten.php';
        require_once CI7K_PLUGIN_DIR . 'includes/providers/class-awin.php';

        if (is_admin()) {
            require_once CI7K_PLUGIN_DIR . 'admin/class-admin-menu.php';
            require_once CI7K_PLUGIN_DIR . 'admin/class-settings.php';
            require_once CI7K_PLUGIN_DIR . 'admin/class-curation-admin.php';
        }

        $this->cpt = new CPT();
        $this->logger = new Logger();
        $this->queue = new Queue();
        $this->mapper = new Mapper();
        $this->ai_rewriter = new AIRewriter();

        if (is_admin()) {
            $this->admin = new \CouponImporter\Admin\AdminMenu();
            // Instanciar CurationAdmin para registrar hooks AJAX
            new \CouponImporter\Admin\CurationAdmin();
        }
    }

    private function init_hooks() {
        add_action('init', array($this, 'load_textdomain'));
        add_action('ci7k_import_cron', array($this, 'run_scheduled_import'));
        add_action('ci7k_process_queue', array($this, 'process_queue'));
    }

    public function load_textdomain() {
        load_plugin_textdomain('7k-coupons-importer', false, dirname(plugin_basename(CI7K_PLUGIN_FILE)) . '/languages');
    }

    public function run_scheduled_import() {
        $providers = array('rakuten', 'awin');

        foreach ($providers as $provider_name) {
            $settings = ci7k_get_provider_settings($provider_name);

            if (empty($settings) || !isset($settings['enable_cron']) || !$settings['enable_cron']) {
                continue;
            }

            try {
                $provider_class = $this->get_provider_instance($provider_name);
                if (!$provider_class) {
                    continue;
                }

                $coupons = $provider_class->get_coupons($settings);

                foreach ($coupons as $coupon_data) {
                    $this->import_coupon($coupon_data, $provider_name);
                }

                $this->logger->log('import', sprintf(__('Importação automática concluída: %d cupons do %s', '7k-coupons-importer'), count($coupons), $provider_name));

            } catch (\Exception $e) {
                $this->logger->log('error', sprintf(__('Erro na importação automática %s: %s', '7k-coupons-importer'), $provider_name, $e->getMessage()), array('provider' => $provider_name));
            }
        }
    }

    public function import_coupon($coupon_data, $provider_name) {
        $external_id = isset($coupon_data['external_id']) ? $coupon_data['external_id'] : '';

        if (empty($external_id)) {
            $this->logger->log('error', 'Cupom sem external_id, pulando importação');
            return false;
        }

        $existing = get_posts(array(
            'post_type' => 'imported_coupon',
            'meta_key' => '_ci7k_external_id',
            'meta_value' => $external_id,
            'posts_per_page' => 1,
            'post_status' => 'any'
        ));

        if (!empty($existing)) {
            $this->logger->log('info', sprintf('Cupom já existe: %s (ID: %d)', $coupon_data['title'], $existing[0]->ID));
            return $existing[0]->ID;
        }

        $post_data = array(
            'post_type' => 'imported_coupon',
            'post_title' => $coupon_data['title'],
            'post_content' => isset($coupon_data['description']) ? $coupon_data['description'] : '',
            'post_status' => 'publish',
            'meta_input' => array(
                '_ci7k_provider' => $provider_name,
                '_ci7k_external_id' => $external_id,
                '_ci7k_status' => 'pending',
                '_ci7k_code' => isset($coupon_data['code']) ? $coupon_data['code'] : '',
                '_ci7k_link' => isset($coupon_data['link']) ? $coupon_data['link'] : '',
                '_ci7k_deeplink' => isset($coupon_data['deeplink']) ? $coupon_data['deeplink'] : '',
                '_ci7k_expiration' => isset($coupon_data['expiration']) ? $coupon_data['expiration'] : '',
                '_ci7k_advertiser' => isset($coupon_data['advertiser']) ? $coupon_data['advertiser'] : '',
                '_ci7k_advertiser_id' => isset($coupon_data['advertiser_id']) ? $coupon_data['advertiser_id'] : '',
                '_ci7k_coupon_type' => isset($coupon_data['coupon_type']) ? $coupon_data['coupon_type'] : 3,
                '_ci7k_is_exclusive' => isset($coupon_data['is_exclusive']) ? $coupon_data['is_exclusive'] : 0,
                '_ci7k_discount' => isset($coupon_data['discount']) ? $coupon_data['discount'] : '',
                '_ci7k_imported_at' => current_time('mysql')
            )
        );

        $post_id = wp_insert_post($post_data);

        if (is_wp_error($post_id)) {
            $this->logger->log('error', sprintf('Erro ao importar cupom: %s', $post_id->get_error_message()));
            return false;
        }

        if (isset($coupon_data['tags']) && is_array($coupon_data['tags'])) {
            update_post_meta($post_id, '_ci7k_tags', $coupon_data['tags']);
        }

        if (isset($coupon_data['category']) && is_array($coupon_data['category'])) {
            update_post_meta($post_id, '_ci7k_categories', $coupon_data['category']);
        }

        $this->logger->log('import', sprintf('Cupom importado com sucesso: %s (ID: %d, External ID: %s)', $coupon_data['title'], $post_id, $external_id));

        return $post_id;
    }

    public function get_provider_instance($provider_name) {
        $class_map = array(
            'rakuten' => '\CouponImporter\Providers\Rakuten',
            'awin' => '\CouponImporter\Providers\Awin'
        );

        if (!isset($class_map[$provider_name])) {
            return null;
        }

        $class_name = $class_map[$provider_name];
        if (!class_exists($class_name)) {
            return null;
        }

        return new $class_name();
    }

    public function process_queue() {
        $this->queue->process();
    }

    public function get_logger() {
        return $this->logger;
    }

    public function get_queue() {
        return $this->queue;
    }

    public function get_mapper() {
        return $this->mapper;
    }

    public function get_ai_rewriter() {
        return $this->ai_rewriter;
    }
}