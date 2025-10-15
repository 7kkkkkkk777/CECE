<?php if (!defined('ABSPATH')) exit; ?>

<div class="wrap couponis7k-wrap">
    <h1><?php _e('Configurações', '7k-coupons-importer'); ?></h1>

    <?php
    // Processar salvamento das configurações
    if (isset($_POST['ci7k_settings_submit'])) {
        check_admin_referer('ci7k_settings_nonce');
        
        $settings = array(
            'ai_provider' => sanitize_text_field($_POST['ai_provider'] ?? 'openai'),
            'openai_api_key' => sanitize_text_field($_POST['openai_api_key'] ?? ''),
            'openai_model' => sanitize_text_field($_POST['openai_model'] ?? 'gpt-3.5-turbo'),
            'gemini_api_key' => sanitize_text_field($_POST['gemini_api_key'] ?? ''),
            'gemini_model' => sanitize_text_field($_POST['gemini_model'] ?? 'gemini-pro'),
            'ai_rewrite_enabled' => isset($_POST['ai_rewrite_enabled']) ? 1 : 0,
            'auto_publish' => isset($_POST['auto_publish']) ? 1 : 0,
            'require_approval' => isset($_POST['require_approval']) ? 1 : 0,
            'delete_on_publish' => isset($_POST['delete_on_publish']) ? 1 : 0,
            'logs_enabled' => isset($_POST['logs_enabled']) ? 1 : 0,
            'debug_log_enabled' => isset($_POST['debug_log_enabled']) ? 1 : 0,
            'auto_publish_cron_interval' => sanitize_text_field($_POST['auto_publish_cron_interval'] ?? 'hourly'),
            'auto_publish_limit' => intval($_POST['auto_publish_limit'] ?? 10),
            'openai_title_prompt' => wp_kses_post($_POST['openai_title_prompt'] ?? ''),
            'openai_description_prompt' => wp_kses_post($_POST['openai_description_prompt'] ?? ''),
            'gemini_title_prompt' => wp_kses_post($_POST['gemini_title_prompt'] ?? ''),
            'gemini_description_prompt' => wp_kses_post($_POST['gemini_description_prompt'] ?? '')
        );
        
        // Validar prompts
        $prompt_fields = array('openai_title_prompt', 'openai_description_prompt', 'gemini_title_prompt', 'gemini_description_prompt');
        foreach ($prompt_fields as $field) {
            if (!empty($settings[$field]) && strpos($settings[$field], '%s') === false) {
                ci7k_admin_notice(sprintf(__('O prompt "%s" deve conter o marcador %%s para inserir a lista de cupons.', '7k-coupons-importer'), $field), 'error');
                $settings[$field] = '';
            }
        }
        
        update_option('ci7k_settings', $settings);
        ci7k_admin_notice(__('Configurações salvas com sucesso!', '7k-coupons-importer'));
    }
    
    // Processar correções
    if (isset($_POST['ci7k_fix_submit'])) {
        check_admin_referer('ci7k_fixes_nonce');
        
        $fix_action = sanitize_text_field($_POST['fix_action']);
        
        switch ($fix_action) {
            case 'reset_pending':
                $updated = $wpdb->update(
                    $wpdb->postmeta,
                    array('meta_value' => 'pending'),
                    array('meta_key' => '_ci7k_status', 'meta_value' => 'processing')
                );
                ci7k_admin_notice(sprintf(__('%d cupons resetados para pendente.', '7k-coupons-importer'), $updated));
                break;
                
            case 'clean_duplicates':
                $duplicates = $wpdb->get_results("
                    SELECT p1.ID 
                    FROM {$wpdb->posts} p1
                    INNER JOIN {$wpdb->postmeta} pm1 ON p1.ID = pm1.post_id AND pm1.meta_key = '_ci7k_external_id'
                    INNER JOIN {$wpdb->postmeta} pm2 ON pm1.meta_value = pm2.meta_value AND pm2.meta_key = '_ci7k_external_id'
                    INNER JOIN {$wpdb->posts} p2 ON pm2.post_id = p2.ID
                    WHERE p1.post_type = 'imported_coupon' 
                    AND p2.post_type = 'imported_coupon'
                    AND p1.ID > p2.ID
                ");
                
                $deleted = 0;
                foreach ($duplicates as $duplicate) {
                    wp_delete_post($duplicate->ID, true);
                    $deleted++;
                }
                ci7k_admin_notice(sprintf(__('%d cupons duplicados removidos.', '7k-coupons-importer'), $deleted));
                break;
                
            case 'clean_expired':
                $expired = get_posts(array(
                    'post_type' => 'imported_coupon',
                    'posts_per_page' => -1,
                    'meta_query' => array(
                        array(
                            'key' => '_ci7k_expiration',
                            'value' => current_time('Y-m-d'),
                            'compare' => '<',
                            'type' => 'DATE'
                        )
                    )
                ));
                
                $deleted = 0;
                foreach ($expired as $coupon) {
                    wp_delete_post($coupon->ID, true);
                    $deleted++;
                }
                ci7k_admin_notice(sprintf(__('%d cupons expirados removidos.', '7k-coupons-importer'), $deleted));
                break;
        }
    }

    $settings = get_option('ci7k_settings', array());
    ?>

    <div class="couponis7k-tabs">
        <nav class="nav-tab-wrapper">
            <a href="#general" class="nav-tab nav-tab-active"><?php _e('Geral', '7k-coupons-importer'); ?></a>
            <a href="#ai-prompts" class="nav-tab"><?php _e('Prompts de IA', '7k-coupons-importer'); ?></a>
            <a href="#automation" class="nav-tab"><?php _e('Automação', '7k-coupons-importer'); ?></a>
            <a href="#fixes" class="nav-tab"><?php _e('Correções', '7k-coupons-importer'); ?></a>
        </nav>

        <form method="post" action="">
            <?php wp_nonce_field('ci7k_settings_nonce'); ?>

            <!-- Aba Geral -->
            <div id="general" class="tab-content active">
                <h2><?php _e('Configurações Gerais', '7k-coupons-importer'); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th><?php _e('Provedor de IA', '7k-coupons-importer'); ?></th>
                        <td>
                            <select name="ai_provider">
                                <option value="openai" <?php selected($settings['ai_provider'] ?? '', 'openai'); ?>>OpenAI (GPT)</option>
                                <option value="gemini" <?php selected($settings['ai_provider'] ?? '', 'gemini'); ?>>Google Gemini</option>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th><?php _e('OpenAI API Key', '7k-coupons-importer'); ?></th>
                        <td>
                            <input type="password" name="openai_api_key" value="<?php echo esc_attr($settings['openai_api_key'] ?? ''); ?>" class="regular-text">
                            <p class="description"><?php _e('Obtenha sua chave em https://platform.openai.com/api-keys', '7k-coupons-importer'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th><?php _e('OpenAI Model', '7k-coupons-importer'); ?></th>
                        <td>
                            <input type="text" name="openai_model" value="<?php echo esc_attr($settings['openai_model'] ?? 'gpt-3.5-turbo'); ?>" class="regular-text">
                        </td>
                    </tr>

                    <tr>
                        <th><?php _e('Gemini API Key', '7k-coupons-importer'); ?></th>
                        <td>
                            <input type="password" name="gemini_api_key" value="<?php echo esc_attr($settings['gemini_api_key'] ?? ''); ?>" class="regular-text">
                            <p class="description"><?php _e('Obtenha sua chave em https://makersuite.google.com/app/apikey', '7k-coupons-importer'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th><?php _e('Gemini Model', '7k-coupons-importer'); ?></th>
                        <td>
                            <input type="text" name="gemini_model" value="<?php echo esc_attr($settings['gemini_model'] ?? 'gemini-pro'); ?>" class="regular-text">
                        </td>
                    </tr>

                    <tr>
                        <th><?php _e('Sistema', '7k-coupons-importer'); ?></th>
                        <td>
                            <label><input type="checkbox" name="logs_enabled" value="1" <?php checked($settings['logs_enabled'] ?? 1, 1); ?>> <?php _e('Habilitar logs do sistema', '7k-coupons-importer'); ?></label><br>
                            <label><input type="checkbox" name="debug_log_enabled" value="1" <?php checked($settings['debug_log_enabled'] ?? 0, 1); ?>> <?php _e('Habilitar debug.log detalhado', '7k-coupons-importer'); ?></label>
                        </td>
                    </tr>

                    <tr>
                        <th><?php _e('Opções', '7k-coupons-importer'); ?></th>
                        <td>
                            <label><input type="checkbox" name="ai_rewrite_enabled" value="1" <?php checked($settings['ai_rewrite_enabled'] ?? 0, 1); ?>> <?php _e('Habilitar reescrita com IA', '7k-coupons-importer'); ?></label><br>
                            <label><input type="checkbox" name="require_approval" value="1" <?php checked($settings['require_approval'] ?? 1, 1); ?>> <?php _e('Requer aprovação manual', '7k-coupons-importer'); ?></label><br>
                            <label><input type="checkbox" name="delete_on_publish" value="1" <?php checked($settings['delete_on_publish'] ?? 0, 1); ?>> <?php _e('Remover cupom importado após publicação', '7k-coupons-importer'); ?></label>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Aba Prompts de IA -->
            <div id="ai-prompts" class="tab-content">
                <h2><?php _e('Prompts para Reescrita com IA', '7k-coupons-importer'); ?></h2>
                <p class="description"><?php _e('Configure os prompts que serão enviados para a IA. Use %s onde a lista de cupons deve ser inserida.', '7k-coupons-importer'); ?></p>
                
                <table class="form-table">
                    <tr>
                        <th><?php _e('OpenAI - Prompt para Títulos', '7k-coupons-importer'); ?></th>
                        <td>
                            <textarea name="openai_title_prompt" rows="4" class="large-text"><?php echo esc_textarea($settings['openai_title_prompt'] ?? 'Reescreva de forma natural e profissional a seguinte lista %s e me devolva apenas a lista reescrita, sem comentários, explicações ou numeração.'); ?></textarea>
                            <p class="description"><?php _e('Prompt usado para reescrever títulos com OpenAI. Deve conter %s.', '7k-coupons-importer'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th><?php _e('OpenAI - Prompt para Descrições', '7k-coupons-importer'); ?></th>
                        <td>
                            <textarea name="openai_description_prompt" rows="4" class="large-text"><?php echo esc_textarea($settings['openai_description_prompt'] ?? 'Reescreva de forma natural e profissional a seguinte lista %s e me devolva apenas a lista reescrita, sem comentários, explicações ou numeração.'); ?></textarea>
                            <p class="description"><?php _e('Prompt usado para reescrever descrições com OpenAI. Deve conter %s.', '7k-coupons-importer'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th><?php _e('Gemini - Prompt para Títulos', '7k-coupons-importer'); ?></th>
                        <td>
                            <textarea name="gemini_title_prompt" rows="4" class="large-text"><?php echo esc_textarea($settings['gemini_title_prompt'] ?? 'Reescreva de forma natural e profissional a seguinte lista %s e me devolva apenas a lista reescrita, sem comentários, explicações ou numeração.'); ?></textarea>
                            <p class="description"><?php _e('Prompt usado para reescrever títulos com Gemini. Deve conter %s.', '7k-coupons-importer'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th><?php _e('Gemini - Prompt para Descrições', '7k-coupons-importer'); ?></th>
                        <td>
                            <textarea name="gemini_description_prompt" rows="4" class="large-text"><?php echo esc_textarea($settings['gemini_description_prompt'] ?? 'Reescreva de forma natural e profissional a seguinte lista %s e me devolva apenas a lista reescrita, sem comentários, explicações ou numeração.'); ?></textarea>
                            <p class="description"><?php _e('Prompt usado para reescrever descrições com Gemini. Deve conter %s.', '7k-coupons-importer'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Aba Automação -->
            <div id="automation" class="tab-content">
                <h2><?php _e('Publicação Automática', '7k-coupons-importer'); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th><?php _e('Publicação Automática', '7k-coupons-importer'); ?></th>
                        <td>
                            <label><input type="checkbox" name="auto_publish" value="1" <?php checked($settings['auto_publish'] ?? 0, 1); ?>> <?php _e('Publicar automaticamente cupons aprovados', '7k-coupons-importer'); ?></label>
                            <p class="description"><?php _e('Quando ativado, cupons aprovados serão publicados automaticamente no intervalo configurado.', '7k-coupons-importer'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th><?php _e('Intervalo do Cron', '7k-coupons-importer'); ?></th>
                        <td>
                            <select name="auto_publish_cron_interval">
                                <option value="every_15_minutes" <?php selected($settings['auto_publish_cron_interval'] ?? 'hourly', 'every_15_minutes'); ?>><?php _e('A cada 15 minutos', '7k-coupons-importer'); ?></option>
                                <option value="every_30_minutes" <?php selected($settings['auto_publish_cron_interval'] ?? 'hourly', 'every_30_minutes'); ?>><?php _e('A cada 30 minutos', '7k-coupons-importer'); ?></option>
                                <option value="hourly" <?php selected($settings['auto_publish_cron_interval'] ?? 'hourly', 'hourly'); ?>><?php _e('A cada hora', '7k-coupons-importer'); ?></option>
                                <option value="twicedaily" <?php selected($settings['auto_publish_cron_interval'] ?? 'hourly', 'twicedaily'); ?>><?php _e('Duas vezes por dia', '7k-coupons-importer'); ?></option>
                                <option value="daily" <?php selected($settings['auto_publish_cron_interval'] ?? 'hourly', 'daily'); ?>><?php _e('Diariamente', '7k-coupons-importer'); ?></option>
                            </select>
                            <p class="description"><?php _e('Frequência com que o sistema verificará cupons aprovados para publicar.', '7k-coupons-importer'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th><?php _e('Limite por Execução', '7k-coupons-importer'); ?></th>
                        <td>
                            <input type="number" name="auto_publish_limit" value="<?php echo esc_attr($settings['auto_publish_limit'] ?? 10); ?>" min="1" max="100" class="small-text">
                            <p class="description"><?php _e('Número máximo de cupons a serem publicados em cada execução do cron.', '7k-coupons-importer'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Aba Correções -->
            <div id="fixes" class="tab-content">
                <h2><?php _e('Ferramentas de Correção', '7k-coupons-importer'); ?></h2>
                <p class="description"><?php _e('Use essas ferramentas para corrigir problemas comuns no banco de dados.', '7k-coupons-importer'); ?></p>
            </div>

            <p class="submit">
                <input type="submit" name="ci7k_settings_submit" class="button-primary" value="<?php _e('Salvar Configurações', '7k-coupons-importer'); ?>">
            </p>
        </form>

        <!-- Formulário separado para correções -->
        <form method="post" action="" id="fixes-form" style="display: none;">
            <?php wp_nonce_field('ci7k_fixes_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th><?php _e('Resetar Status', '7k-coupons-importer'); ?></th>
                    <td>
                        <button type="submit" name="ci7k_fix_submit" value="reset_pending" onclick="return confirm('<?php _e('Tem certeza? Isso resetará todos os cupons em processamento para pendente.', '7k-coupons-importer'); ?>')" class="button">
                            <?php _e('Resetar cupons "processando" para "pendente"', '7k-coupons-importer'); ?>
                        </button>
                        <input type="hidden" name="fix_action" value="reset_pending">
                        <p class="description"><?php _e('Útil quando cupons ficam travados no status "processando".', '7k-coupons-importer'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th><?php _e('Limpar Duplicados', '7k-coupons-importer'); ?></th>
                    <td>
                        <button type="submit" name="ci7k_fix_submit" value="clean_duplicates" onclick="return confirm('<?php _e('Tem certeza? Isso removerá cupons duplicados permanentemente.', '7k-coupons-importer'); ?>')" class="button">
                            <?php _e('Remover cupons duplicados', '7k-coupons-importer'); ?>
                        </button>
                        <input type="hidden" name="fix_action" value="clean_duplicates">
                        <p class="description"><?php _e('Remove cupons com mesmo external_id, mantendo apenas o mais antigo.', '7k-coupons-importer'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th><?php _e('Limpar Expirados', '7k-coupons-importer'); ?></th>
                    <td>
                        <button type="submit" name="ci7k_fix_submit" value="clean_expired" onclick="return confirm('<?php _e('Tem certeza? Isso removerá todos os cupons expirados permanentemente.', '7k-coupons-importer'); ?>')" class="button">
                            <?php _e('Remover cupons expirados', '7k-coupons-importer'); ?>
                        </button>
                        <input type="hidden" name="fix_action" value="clean_expired">
                        <p class="description"><?php _e('Remove cupons com data de expiração anterior à data atual.', '7k-coupons-importer'); ?></p>
                    </td>
                </tr>
            </table>
        </form>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Sistema de abas
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        
        var target = $(this).attr('href');
        
        // Atualizar abas
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        // Atualizar conteúdo
        $('.tab-content').removeClass('active');
        $(target).addClass('active');
        
        // Mostrar/esconder formulário de correções
        if (target === '#fixes') {
            $('#fixes-form').show();
        } else {
            $('#fixes-form').hide();
        }
    });
});
</script>

<style>
.couponis7k-tabs .nav-tab-wrapper {
    margin-bottom: 20px;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

.form-table th {
    width: 200px;
}

.form-table textarea {
    width: 100%;
}

.form-table .description {
    margin-top: 5px;
    font-style: italic;
}

#fixes-form {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #ddd;
}

#fixes-form .button {
    margin-right: 10px;
}
</style>