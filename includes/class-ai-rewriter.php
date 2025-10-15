<?php

namespace CouponImporter;

if (!defined('ABSPATH')) {
    exit;
}

class AIRewriter {

    private $logger;

    public function __construct() {
        $this->logger = new Logger();
    }

    public function rewrite_title($original_title, $context = array()) {
        $settings = get_option('ci7k_settings', array());
        $provider = isset($settings['ai_provider']) ? $settings['ai_provider'] : 'openai';

        if (!isset($settings['ai_rewrite_enabled']) || !$settings['ai_rewrite_enabled']) {
            return $original_title;
        }

        try {
            if ($provider === 'openai') {
                return $this->rewrite_with_openai($original_title, 'title', $context);
            } elseif ($provider === 'gemini') {
                return $this->rewrite_with_gemini($original_title, 'title', $context);
            }
        } catch (\Exception $e) {
            $this->logger->log('error', sprintf(__('Erro ao reescrever título com IA: %s', '7k-coupons-importer'), $e->getMessage()), array('severity' => 'warning'));
            return $original_title;
        }

        return $original_title;
    }

    public function rewrite_description($original_description, $context = array()) {
        $settings = get_option('ci7k_settings', array());
        $provider = isset($settings['ai_provider']) ? $settings['ai_provider'] : 'openai';

        if (!isset($settings['ai_rewrite_enabled']) || !$settings['ai_rewrite_enabled']) {
            return $original_description;
        }

        try {
            if ($provider === 'openai') {
                return $this->rewrite_with_openai($original_description, 'description', $context);
            } elseif ($provider === 'gemini') {
                return $this->rewrite_with_gemini($original_description, 'description', $context);
            }
        } catch (\Exception $e) {
            $this->logger->log('error', sprintf(__('Erro ao reescrever descrição com IA: %s', '7k-coupons-importer'), $e->getMessage()), array('severity' => 'warning'));
            return $original_description;
        }

        return $original_description;
    }

    private function rewrite_with_openai($text, $type, $context) {
        $settings = get_option('ci7k_settings', array());
        $api_key = isset($settings['openai_api_key']) ? $settings['openai_api_key'] : '';
        $model = isset($settings['openai_model']) ? $settings['openai_model'] : 'gpt-3.5-turbo';

        if (empty($api_key)) {
            throw new \Exception(__('OpenAI API Key não configurada', '7k-coupons-importer'));
        }

        $prompt = $this->build_prompt($text, $type, $context);

        $body = array(
            'model' => $model,
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => 'Você é um especialista em marketing de afiliados e copywriting para cupons de desconto. Seu trabalho é reescrever títulos e descrições de cupons de forma clara, atraente e profissional em português brasileiro.'
                ),
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            'temperature' => 0.7,
            'max_tokens' => $type === 'title' ? 100 : 300
        );

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($body)
        ));

        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            $body = wp_remote_retrieve_body($response);
            throw new \Exception(sprintf(__('OpenAI API retornou erro %d: %s', '7k-coupons-importer'), $response_code, $body));
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (!isset($data['choices'][0]['message']['content'])) {
            throw new \Exception(__('Resposta inválida da OpenAI API', '7k-coupons-importer'));
        }

        $rewritten = trim($data['choices'][0]['message']['content']);

        $this->logger->log('ai_rewrite', sprintf(__('Texto reescrito com OpenAI (%s)', '7k-coupons-importer'), $type), array('provider' => 'openai'));

        return $rewritten;
    }

    private function rewrite_with_gemini($text, $type, $context) {
        $settings = get_option('ci7k_settings', array());
        $api_key = isset($settings['gemini_api_key']) ? $settings['gemini_api_key'] : '';
        $model = isset($settings['gemini_model']) ? $settings['gemini_model'] : 'gemini-pro';

        if (empty($api_key)) {
            throw new \Exception(__('Gemini API Key não configurada', '7k-coupons-importer'));
        }

        $prompt = $this->build_prompt($text, $type, $context);

        $system_instruction = 'Você é um especialista em marketing de afiliados e copywriting para cupons de desconto. Seu trabalho é reescrever títulos e descrições de cupons de forma clara, atraente e profissional em português brasileiro.';

        $body = array(
            'contents' => array(
                array(
                    'parts' => array(
                        array('text' => $system_instruction . "\n\n" . $prompt)
                    )
                )
            ),
            'generationConfig' => array(
                'temperature' => 0.7,
                'maxOutputTokens' => $type === 'title' ? 100 : 300
            )
        );

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$api_key}";

        $response = wp_remote_post($url, array(
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($body)
        ));

        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            $body = wp_remote_retrieve_body($response);
            throw new \Exception(sprintf(__('Gemini API retornou erro %d: %s', '7k-coupons-importer'), $response_code, $body));
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            throw new \Exception(__('Resposta inválida da Gemini API', '7k-coupons-importer'));
        }

        $rewritten = trim($data['candidates'][0]['content']['parts'][0]['text']);

        $this->logger->log('ai_rewrite', sprintf(__('Texto reescrito com Gemini (%s)', '7k-coupons-importer'), $type), array('provider' => 'gemini'));

        return $rewritten;
    }

    private function build_prompt($text, $type, $context) {
        if ($type === 'title') {
            $prompt = "Reescreva o seguinte título de cupom de desconto de forma mais atraente, clara e profissional. ";
            $prompt .= "O título deve ser direto, mencionar o benefício principal e ter no máximo 60 caracteres. ";
            $prompt .= "Não use emojis. Não use aspas no resultado.\n\n";
            $prompt .= "Título original: {$text}\n\n";
            $prompt .= "Retorne APENAS o novo título, sem explicações.";
        } else {
            $prompt = "Reescreva a seguinte descrição de cupom de desconto de forma mais atraente e profissional. ";
            $prompt .= "A descrição deve ser clara, destacar os benefícios, incluir calls-to-action quando apropriado e ter entre 100-200 caracteres. ";
            $prompt .= "Mantenha informações importantes como restrições, validade e termos. Não use emojis.\n\n";
            $prompt .= "Descrição original: {$text}\n\n";
            $prompt .= "Retorne APENAS a nova descrição, sem explicações.";
        }

        if (!empty($context)) {
            if (isset($context['advertiser'])) {
                $prompt .= "\nLoja: " . $context['advertiser'];
            }
            if (isset($context['discount'])) {
                $prompt .= "\nDesconto: " . $context['discount'];
            }
            if (isset($context['code']) && !empty($context['code'])) {
                $prompt .= "\nCódigo: " . $context['code'];
            }
        }

        return $prompt;
    }

    public function test_connection($provider = null) {
        $settings = get_option('ci7k_settings', array());

        if (!$provider) {
            $provider = isset($settings['ai_provider']) ? $settings['ai_provider'] : 'openai';
        }

        try {
            $test_text = "Teste de conexão com API de IA";

            if ($provider === 'openai') {
                $result = $this->rewrite_with_openai($test_text, 'title', array());
            } elseif ($provider === 'gemini') {
                $result = $this->rewrite_with_gemini($test_text, 'title', array());
            } else {
                return false;
            }

            return !empty($result);

        } catch (\Exception $e) {
            return false;
        }
    }
}
