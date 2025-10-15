<?php

if (!defined('ABSPATH')) {
    exit;
}

function coupon_importer_parse_date($date_string) {
    if (empty($date_string)) {
        return '';
    }

    $timestamp = strtotime($date_string);
    if (!$timestamp) {
        return '';
    }

    return date('Y-m-d H:i:s', $timestamp);
}

function coupon_importer_sanitize_data($data) {
    if (!is_array($data)) {
        return $data;
    }

    $sanitized = array();
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            $sanitized[$key] = coupon_importer_sanitize_data($value);
        } elseif (is_string($value)) {
            $sanitized[$key] = sanitize_text_field($value);
        } else {
            $sanitized[$key] = $value;
        }
    }

    return $sanitized;
}

function ci7k_get_option($key, $default = null) {
    $options = get_option('ci7k_settings', array());
    return isset($options[$key]) ? $options[$key] : $default;
}

function ci7k_update_option($key, $value) {
    $options = get_option('ci7k_settings', array());
    $options[$key] = $value;
    return update_option('ci7k_settings', $options);
}

function ci7k_get_provider_settings($provider_name) {
    $key = 'ci7k_provider_' . sanitize_key($provider_name);
    return get_option($key, array());
}

function ci7k_update_provider_settings($provider_name, $settings) {
    $key = 'ci7k_provider_' . sanitize_key($provider_name);
    return update_option($key, $settings);
}

function ci7k_format_date_for_display($date) {
    if (empty($date)) {
        return __('N/A', '7k-coupons-importer');
    }

    $timestamp = is_numeric($date) ? $date : strtotime($date);
    if (!$timestamp) {
        return $date;
    }

    return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $timestamp);
}

function ci7k_time_ago($time) {
    $time = is_numeric($time) ? $time : strtotime($time);
    $time_diff = time() - $time;

    if ($time_diff < 60) {
        return sprintf(_n('%s segundo atrás', '%s segundos atrás', $time_diff, '7k-coupons-importer'), $time_diff);
    }

    $time_diff = round($time_diff / 60);
    if ($time_diff < 60) {
        return sprintf(_n('%s minuto atrás', '%s minutos atrás', $time_diff, '7k-coupons-importer'), $time_diff);
    }

    $time_diff = round($time_diff / 60);
    if ($time_diff < 24) {
        return sprintf(_n('%s hora atrás', '%s horas atrás', $time_diff, '7k-coupons-importer'), $time_diff);
    }

    $time_diff = round($time_diff / 24);
    return sprintf(_n('%s dia atrás', '%s dias atrás', $time_diff, '7k-coupons-importer'), $time_diff);
}

function ci7k_get_coupon_status_label($status) {
    $labels = array(
        'pending' => __('Pendente', '7k-coupons-importer'),
        'approved' => __('Aprovado', '7k-coupons-importer'),
        'rejected' => __('Rejeitado', '7k-coupons-importer'),
        'published' => __('Publicado', '7k-coupons-importer'),
        'ignored' => __('Ignorado', '7k-coupons-importer')
    );

    return isset($labels[$status]) ? $labels[$status] : $status;
}

function ci7k_get_coupon_type_label($ctype) {
    return $ctype == 1 ? __('Cupom', '7k-coupons-importer') : __('Oferta', '7k-coupons-importer');
}
