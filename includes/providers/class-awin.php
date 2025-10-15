<?php
/**
 * Awin Provider Class
 * Implements Awin API, parsing data, mapping to CPT
 */

namespace CouponImporter\Providers;

if (!defined('ABSPATH')) {
    exit;
}

class Awin {

    private $api_base_url = 'https://api.awin.com';
    private $logger;

    public function __construct() {
        $this->logger = new \CouponImporter\Logger();
    }

    public function get_name() {
        return __('Awin', '7k-coupons-importer');
    }

    public function get_settings_fields() {
        return array(
            'api_token' => array(
                'label' => __('API Token', '7k-coupons-importer'),
                'type' => 'text',
                'required' => true,
                'description' => __('Seu token de API do Awin (obtido em Account > API Credentials)', '7k-coupons-importer')
            ),
            'publisher_id' => array(
                'label' => __('Publisher ID', '7k-coupons-importer'),
                'type' => 'text',
                'required' => true,
                'description' => __('Seu ID de publisher no Awin', '7k-coupons-importer')
            ),
            'enable_cron' => array(
                'label' => __('Importação Automática', '7k-coupons-importer'),
                'type' => 'checkbox',
                'description' => __('Habilitar importação automática via cron', '7k-coupons-importer')
            ),
            'import_limit' => array(
                'label' => __('Limite de Importação', '7k-coupons-importer'),
                'type' => 'number',
                'default' => 50,
                'description' => __('Número máximo de cupons por importação', '7k-coupons-importer')
            )
        );
    }

    public function validate_settings($settings) {
        if (empty($settings['api_token'])) {
            return false;
        }

        if (empty($settings['publisher_id'])) {
            return false;
        }

        return true;
    }

    public function test_connection($settings) {
        try {
            $endpoint = '/publishers/' . $settings['publisher_id'] . '/programmes';
            $params = array(
                'relationship' => 'joined'
            );
            $response = $this->make_api_request($endpoint, $settings, $params);

            if (is_wp_error($response)) {
                $this->logger->log('error', 'Awin test connection failed: ' . $response->get_error_message());
                return false;
            }

            return true;
        } catch (Exception $e) {
            $this->logger->log('error', 'Awin test connection exception: ' . $e->getMessage());
            return false;
        }
    }

    public function get_coupons($settings, $limit = null) {
        $limit = $limit ?: (isset($settings['import_limit']) ? intval($settings['import_limit']) : 50);
        $all_promotions = array();

        $this->logger->log('info', sprintf('Awin: Iniciando busca de cupons, limite: %d', $limit));

        $endpoint = '/publishers/' . $settings['publisher_id'] . '/offers';
        $params = array(
            'type' => 'voucher'
        );

        $response = $this->make_api_request($endpoint, $settings, $params);

        if (is_wp_error($response)) {
            $this->logger->log('error', 'Awin API error: ' . $response->get_error_message());
            throw new \Exception($response->get_error_message());
        }

        if (!isset($response) || !is_array($response) || empty($response)) {
            $this->logger->log('info', 'Awin: Nenhuma promoção encontrada');
            return array();
        }

        $all_promotions = $this->parse_coupons($response);

        if (count($all_promotions) > $limit) {
            $all_promotions = array_slice($all_promotions, 0, $limit);
        }

        $this->logger->log('info', sprintf('Awin: %d cupons processados', count($all_promotions)));

        return $all_promotions;
    }

    private function make_api_request($endpoint, $settings, $params = array()) {
        $start_time = microtime(true);

        $url = $this->api_base_url . $endpoint;

        if (!empty($params)) {
            $url = add_query_arg($params, $url);
        }

        $args = array(
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Bearer ' . trim($settings['api_token']),
                'User-Agent' => 'WordPress/7K-Coupons-Importer',
                'Accept' => 'application/json'
            )
        );

        $this->logger->log('debug', sprintf('Awin API Request: %s', $url));

        $response = wp_remote_get($url, $args);
        $response_time = (microtime(true) - $start_time) * 1000;

        if (is_wp_error($response)) {
            $error_msg = $response->get_error_message();
            $this->logger->log_api_request('awin', $endpoint, 0, $response_time, $error_msg);
            $this->logger->log('error', sprintf('Awin request error: %s', $error_msg));
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        $this->logger->log_api_request('awin', $endpoint, $response_code, $response_time);

        if ($response_code !== 200) {
            $error_message = sprintf('API returned status code: %d. Response: %s', $response_code, substr($body, 0, 500));
            $this->logger->log('error', 'Awin API Error: ' . $error_message);
            return new \WP_Error('api_error', $error_message);
        }

        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->log('error', 'Awin JSON decode error: ' . json_last_error_msg());
            return new \WP_Error('json_error', 'Invalid JSON response: ' . json_last_error_msg());
        }

        return $data;
    }

    private function parse_coupons($api_coupons) {
        $parsed_coupons = array();

        foreach ($api_coupons as $api_coupon) {
            $parsed_coupon = $this->parse_single_coupon($api_coupon);
            if ($parsed_coupon) {
                $parsed_coupons[] = $parsed_coupon;
            }
        }

        return $parsed_coupons;
    }

    private function parse_single_coupon($api_coupon) {
        $coupon = array();

        $coupon['title'] = isset($api_coupon['title']) ? $api_coupon['title'] : '';
        $coupon['description'] = $this->build_description($api_coupon);
        $coupon['link'] = isset($api_coupon['url']) ? $api_coupon['url'] : '';

        $coupon['code'] = isset($api_coupon['code']) ? $api_coupon['code'] : '';

        $coupon['advertiser'] = '';
        $coupon['advertiser_id'] = '';
        if (isset($api_coupon['advertiser']['name'])) {
            $coupon['advertiser'] = $api_coupon['advertiser']['name'];
        }
        if (isset($api_coupon['advertiser']['id'])) {
            $coupon['advertiser_id'] = $api_coupon['advertiser']['id'];
        }

        $coupon['start_date'] = '';
        if (isset($api_coupon['startDate'])) {
            $coupon['start_date'] = coupon_importer_parse_date($api_coupon['startDate']);
        }
        $coupon['expiration'] = '';
        if (isset($api_coupon['endDate'])) {
            $coupon['expiration'] = coupon_importer_parse_date($api_coupon['endDate']);
        }

        $coupon['discount'] = $this->extract_discount($api_coupon);

        $coupon['promotion_type'] = isset($api_coupon['type']) ? $api_coupon['type'] : '';

        $coupon['tags'] = array();
        $coupon['category'] = array();
        if (isset($api_coupon['categories']) && is_array($api_coupon['categories'])) {
            foreach ($api_coupon['categories'] as $category) {
                if (isset($category['name'])) {
                    $coupon['tags'][] = $category['name'];
                    $coupon['category'][] = $category['name'];
                }
            }
        }

        $coupon['coupon_type'] = $this->determine_coupon_type($api_coupon);
        $coupon['is_exclusive'] = $this->is_exclusive_offer($api_coupon);
        $coupon['deeplink'] = $coupon['link'];

        $coupon['external_id'] = $this->generate_external_id($api_coupon);

        if ($coupon['is_exclusive'] && !in_array('exclusive', $coupon['tags'])) {
            $coupon['tags'][] = 'exclusive';
        }

        if (!empty($coupon['code'])) {
            $coupon['tags'][] = 'cupom';
        } else {
            $coupon['tags'][] = 'oferta';
        }

        $coupon = coupon_importer_sanitize_data($coupon);

        if (empty($coupon['title']) || empty($coupon['link'])) {
            return null;
        }

        return $coupon;
    }

    private function generate_external_id($api_coupon) {
        if (isset($api_coupon['id'])) {
            return 'awin_' . $api_coupon['id'];
        }

        $advertiser_id = isset($api_coupon['advertiser']['id']) ? $api_coupon['advertiser']['id'] : '';
        $title = isset($api_coupon['title']) ? $api_coupon['title'] : '';

        if (!empty($advertiser_id) && !empty($title)) {
            return 'awin_' . $advertiser_id . '_' . substr(md5($title), 0, 8);
        }

        return 'awin_' . md5(serialize($api_coupon));
    }

    private function build_description($api_coupon) {
        $description_parts = array();

        if (!empty($api_coupon['description'])) {
            $description_parts[] = $api_coupon['description'];
        }

        if (isset($api_coupon['terms']) && !empty($api_coupon['terms'])) {
            $description_parts[] = "Termos: " . $api_coupon['terms'];
        }

        if (isset($api_coupon['restrictions']) && !empty($api_coupon['restrictions'])) {
            $description_parts[] = "Restrições: " . $api_coupon['restrictions'];
        }

        return !empty($description_parts) ? implode("\n\n", $description_parts) : '';
    }

    private function extract_discount($api_coupon) {
        if (!empty($api_coupon['discount'])) {
            return $api_coupon['discount'];
        }

        if (!empty($api_coupon['discountAmount'])) {
            return $api_coupon['discountAmount'];
        }

        if (!empty($api_coupon['discountPercentage'])) {
            return $api_coupon['discountPercentage'] . '% OFF';
        }

        $text = ($api_coupon['title'] ?? '') . ' ' . ($api_coupon['description'] ?? '');

        if (preg_match('/(\d+)%\s*(off|desconto|discount)/i', $text, $matches)) {
            return $matches[1] . '% OFF';
        }

        if (preg_match('/£(\d+(?:\.\d{2})?)\s*(off|desconto|discount)/i', $text, $matches)) {
            return '£' . $matches[1] . ' OFF';
        }

        if (preg_match('/\$(\d+(?:\.\d{2})?)\s*(off|desconto|discount)/i', $text, $matches)) {
            return '$' . $matches[1] . ' OFF';
        }

        return '';
    }

    private function determine_coupon_type($api_coupon) {
        if (!empty($api_coupon['code'])) {
            return 1;
        }
        return 3;
    }

    private function is_exclusive_offer($api_coupon) {
        if (isset($api_coupon['exclusive']) && $api_coupon['exclusive']) {
            return 1;
        }

        $exclusive_indicators = array('exclusive', 'exclusivo', 'especial', 'vip', 'limited');

        $text_to_check = strtolower(
            ($api_coupon['title'] ?? '') . ' ' .
            ($api_coupon['description'] ?? '') . ' ' .
            ($api_coupon['type'] ?? '')
        );

        foreach ($exclusive_indicators as $indicator) {
            if (strpos($text_to_check, $indicator) !== false) {
                return 1;
            }
        }

        return 0;
    }

    public function get_advertisers($settings) {
        $endpoint = '/publishers/' . $settings['publisher_id'] . '/programmes';
        $params = array(
            'relationship' => 'joined'
        );

        $response = $this->make_api_request($endpoint, $settings, $params);

        if (is_wp_error($response)) {
            $this->logger->log('error', 'Awin get_advertisers error: ' . $response->get_error_message());
            return array();
        }

        $programmes = isset($response['programmes']) ? $response['programmes'] : (is_array($response) ? $response : array());

        if (!is_array($programmes)) {
            return array();
        }

        $advertisers = array();
        foreach ($programmes as $programme) {
            if (isset($programme['advertiserId']) && isset($programme['advertiserName'])) {
                $advertisers[] = array(
                    'id' => $programme['advertiserId'],
                    'name' => $programme['advertiserName']
                );
            }
        }

        return $advertisers;
    }

    public function get_categories($settings) {
        return array(
            array('id' => 'fashion', 'name' => 'Fashion'),
            array('id' => 'electronics', 'name' => 'Electronics'),
            array('id' => 'home', 'name' => 'Home & Garden'),
            array('id' => 'travel', 'name' => 'Travel'),
            array('id' => 'health', 'name' => 'Health & Beauty'),
            array('id' => 'sports', 'name' => 'Sports & Outdoors'),
            array('id' => 'books', 'name' => 'Books & Media'),
            array('id' => 'food', 'name' => 'Food & Drink'),
        );
    }

    public function get_commission_groups($settings) {
        $endpoint = '/' . $settings['publisher_id'] . '/commissiongroups';
        $response = $this->make_api_request($endpoint, $settings);

        if (is_wp_error($response)) {
            return array();
        }

        return isset($response['commissionGroups']) ? $response['commissionGroups'] : array();
    }
}
