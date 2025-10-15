<?php
/**
 * Rakuten Provider Class - Full Production Version
 * Implements Rakuten Coupon API v1.0 with full filtering and XML parsing
 */

namespace CouponImporter\Providers;

if (!defined('ABSPATH')) exit;

class Rakuten {

    private $api_base_url = 'https://api.linksynergy.com';
    private $logger;

    public function __construct() {
        $this->logger = new \CouponImporter\Logger();
    }

    public function get_name() {
        return __('Rakuten Advertising', '7k-coupons-importer');
    }

    public function get_settings_fields() {
        return array(
            'bearer_token' => array(
                'label' => __('Bearer Token', '7k-coupons-importer'),
                'type' => 'text',
                'required' => true,
                'description' => __('Token de autenticação Bearer obtido na Rakuten', '7k-coupons-importer')
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
            ),
            'category_filter' => array(
                'label' => __('Categorias', '7k-coupons-importer'),
                'type' => 'text',
                'description' => __('IDs de categorias separados por | (OR condition)')
            ),
            'promotion_filter' => array(
                'label' => __('Tipos de Promoção', '7k-coupons-importer'),
                'type' => 'text',
                'description' => __('IDs de tipos de promoção separados por | (OR condition)')
            ),
            'network_filter' => array(
                'label' => __('Redes', '7k-coupons-importer'),
                'type' => 'text',
                'description' => __('IDs de redes separados por | (OR condition)')
            ),
            'advertiser_filter' => array(
                'label' => __('Anunciantes (MID)', '7k-coupons-importer'),
                'type' => 'text',
                'description' => __('IDs de anunciantes separados por | (OR condition)')
            ),
        );
    }

    public function validate_settings($settings) {
        return !empty($settings['bearer_token']);
    }

    public function test_connection($settings) {
        $response = $this->make_api_request('/coupon/1.0', $settings, array('resultsperpage' => 1));
        return !is_wp_error($response);
    }

    public function get_coupons($settings, $limit = null) {
        $limit = $limit ?: (isset($settings['import_limit']) ? intval($settings['import_limit']) : 50);
        $all_coupons = array();
        $page = 1;
        $page_size = min(100, $limit);

        while (count($all_coupons) < $limit) {
            $params = array(
                'resultsperpage' => $page_size,
                'pagenumber' => $page
            );

            if (!empty($settings['category_filter'])) $params['category'] = $settings['category_filter'];
            if (!empty($settings['promotion_filter'])) $params['promotiontype'] = $settings['promotion_filter'];
            if (!empty($settings['network_filter'])) $params['network'] = $settings['network_filter'];
            if (!empty($settings['advertiser_filter'])) $params['mid'] = $settings['advertiser_filter'];

            $response = $this->make_api_request('/coupon/1.0', $settings, $params);

            if (is_wp_error($response)) {
                $this->logger->log('error', 'Erro na API: ' . $response->get_error_message());
                break;
            }
            
            if (empty($response['link'])) {
                $this->logger->log('info', 'Nenhum cupom encontrado na página ' . $page);
                break;
            }

            $page_coupons = $this->parse_coupons($response['link']);
            
            if (empty($page_coupons)) {
                $this->logger->log('info', 'Nenhum cupom válido encontrado na página ' . $page);
                break;
            }

            $all_coupons = array_merge($all_coupons, $page_coupons);
            
            $this->logger->log('info', sprintf('Página %d: %d cupons encontrados. Total: %d', $page, count($page_coupons), count($all_coupons)));

            if (count($page_coupons) < $page_size) {
                $this->logger->log('info', 'Última página alcançada (menos cupons que o esperado)');
                break;
            }

            if (count($all_coupons) >= $limit) {
                break;
            }

            $page++;
            
            if ($page > 10) {
                $this->logger->log('warning', 'Limite de 10 páginas alcançado');
                break;
            }
            
            usleep(200000);
        }

        $final_coupons = array_slice($all_coupons, 0, $limit);
        
        $this->logger->log('info', sprintf('Importação finalizada: %d cupons de %d solicitados', count($final_coupons), $limit));

        return $final_coupons;
    }

    private function make_api_request($endpoint, $settings, $params = array()) {
        $url = $this->api_base_url . $endpoint;
        if (!empty($params)) $url = add_query_arg($params, $url);

        $args = array(
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Bearer ' . $settings['bearer_token'],
                'Accept' => 'application/xml',
                'User-Agent' => 'WordPress/7K-Coupons-Importer'
            )
        );

        $start_time = microtime(true);
        $response = wp_remote_get($url, $args);
        $response_time = (microtime(true) - $start_time) * 1000;
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        $this->logger->log_api_request('rakuten', $endpoint, $response_code, $response_time);

        if (is_wp_error($response)) return $response;
        if ($response_code !== 200) return new \WP_Error('api_error', "API returned status $response_code");

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA);
        if (!$xml) return new \WP_Error('xml_error', 'Invalid XML response');

        $json = json_encode($xml);
        return json_decode($json, true);
    }

    private function parse_coupons($links) {
        $parsed_coupons = array();
        if ($this->is_assoc($links)) $links = array($links);

        foreach ($links as $link) {
            $external_id = $this->generate_external_id($link);
            
            $coupon = array(
                'title' => $link['offerdescription'] ?? '',
                'description' => $this->build_description($link),
                'link' => $link['clickurl'] ?? '',
                'code' => $link['couponcode'] ?? '',
                'advertiser' => $link['advertisername'] ?? '',
                'advertiser_id' => $link['advertiserid'] ?? '',
                'start_date' => isset($link['offerstartdate']) ? $this->format_date($link['offerstartdate']) : '',
                'expiration' => isset($link['offerenddate']) ? $this->format_date($link['offerenddate']) : '',
                'discount' => $this->extract_discount($link),
                'promotion_type' => $link['promotiontype'] ?? '',
                'network' => $link['network'] ?? '',
                'category' => $this->extract_categories($link),
                'tags' => $this->extract_tags($link),
                'coupon_type' => $this->determine_coupon_type($link),
                'is_exclusive' => $this->is_exclusive_offer($link),
                'deeplink' => $link['clickurl'] ?? '',
                'external_id' => $external_id
            );

            $coupon = coupon_importer_sanitize_data($coupon);

            if (!empty($coupon['link']) && (!empty($coupon['code']) || !empty($coupon['link']))) {
                // Log do external_id gerado para debug
                $this->logger->log('debug', sprintf('Cupom processado: %s | External ID: %s', 
                    substr($coupon['title'], 0, 50), $external_id));
                $parsed_coupons[] = $coupon;
            }
        }

        return $parsed_coupons;
    }

    private function generate_external_id($link) {
        // Primeiro, tenta usar IDs únicos da API
        $advertiser_id = $link['advertiserid'] ?? '';
        $link_id = $link['linkid'] ?? '';
        $offer_id = $link['offerid'] ?? '';
        
        // Se temos IDs únicos, usa eles
        if (!empty($link_id)) {
            return 'rakuten_' . $link_id;
        }
        
        if (!empty($offer_id)) {
            return 'rakuten_' . $offer_id;
        }
        
        if (!empty($advertiser_id) && !empty($link_id)) {
            return 'rakuten_' . $advertiser_id . '_' . $link_id;
        }
        
        if (!empty($advertiser_id) && !empty($offer_id)) {
            return 'rakuten_' . $advertiser_id . '_' . $offer_id;
        }
        
        // Se não temos IDs únicos, cria um baseado no conteúdo
        $title = $link['offerdescription'] ?? '';
        $advertiser = $link['advertisername'] ?? '';
        $coupon_code = $link['couponcode'] ?? '';
        $click_url = $link['clickurl'] ?? '';
        
        // Cria um hash único baseado no conteúdo do cupom
        $unique_content = $title . '|' . $advertiser . '|' . $coupon_code . '|' . $click_url;
        $hash = md5($unique_content);
        
        return 'rakuten_' . $hash;
    }

    private function build_description($link) {
        $description_parts = array();

        if (!empty($link['offerdescription'])) {
            $description_parts[] = $link['offerdescription'];
        }

        if (!empty($link['couponrestriction'])) {
            $description_parts[] = "Restrições: " . $link['couponrestriction'];
        }

        if (!empty($link['terms'])) {
            $description_parts[] = "Termos: " . $link['terms'];
        }

        if (!empty($link['salediscount'])) {
            $description_parts[] = "Desconto: " . $link['salediscount'];
        }

        return !empty($description_parts) ? implode("\n\n", $description_parts) : 'Oferta disponível';
    }

    private function extract_discount($link) {
        if (!empty($link['salediscount'])) {
            return $link['salediscount'];
        }

        if (!empty($link['couponcode']) && !empty($link['offerdescription'])) {
            $desc = $link['offerdescription'];
            if (preg_match('/(\d+)%\s*(off|desconto|discount)/i', $desc, $matches)) {
                return $matches[1] . '% OFF';
            }
            if (preg_match('/R\$\s*(\d+(?:,\d{2})?)\s*(off|desconto|discount)/i', $desc, $matches)) {
                return 'R$ ' . $matches[1] . ' OFF';
            }
        }

        return '';
    }

    private function extract_categories($link) {
        $categories = array();

        if (isset($link['categories']['category'])) {
            $cats = (array)$link['categories']['category'];
            foreach ($cats as $cat) {
                if (is_string($cat)) {
                    $categories[] = $cat;
                } elseif (is_array($cat) && isset($cat['@content'])) {
                    $categories[] = $cat['@content'];
                }
            }
        }

        return $categories;
    }

    private function extract_tags($link) {
        $tags = array();

        $categories = $this->extract_categories($link);
        $tags = array_merge($tags, $categories);

        if (!empty($link['promotiontype'])) {
            $tags[] = $link['promotiontype'];
        }

        if (!empty($link['network'])) {
            $tags[] = $link['network'];
        }

        if (!empty($link['couponcode'])) {
            $tags[] = 'cupom';
        } else {
            $tags[] = 'oferta';
        }

        return array_unique($tags);
    }

    private function determine_coupon_type($link) {
        if (!empty($link['couponcode'])) {
            return 1;
        }
        return 3;
    }

    private function is_exclusive_offer($link) {
        $exclusive_indicators = array('exclusive', 'exclusivo', 'especial', 'vip');

        $text_to_check = strtolower(
            ($link['offerdescription'] ?? '') . ' ' .
            ($link['couponrestriction'] ?? '') . ' ' .
            ($link['promotiontype'] ?? '')
        );

        foreach ($exclusive_indicators as $indicator) {
            if (strpos($text_to_check, $indicator) !== false) {
                return 1;
            }
        }

        return 0;
    }

    private function format_date($date_str) {
        $timestamp = strtotime($date_str);
        return $timestamp ? date('Y-m-d', $timestamp) : '';
    }

    public function get_categories($settings) {
        return array();
    }

    public function get_advertisers($settings) {
        $coupons = $this->get_coupons($settings, 100);
        $advertisers = array();
        $seen_ids = array();

        foreach ($coupons as $c) {
            $adv_id = $c['advertiser'] ?? '';
            if ($adv_id && !in_array($adv_id, $seen_ids)) {
                $seen_ids[] = $adv_id;
                $advertisers[] = array('id'=>$adv_id,'name'=>$adv_id);
            }
        }

        usort($advertisers, function($a,$b){return strcmp($a['name'],$b['name']);});
        return $advertisers;
    }

    private function is_assoc($arr) {
        if (!is_array($arr)) return false;
        return array_keys($arr) !== range(0, count($arr) - 1);
    }
}