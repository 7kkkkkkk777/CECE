jQuery(document).ready(function($) {
    'use strict';

    function showNotice(message, type) {
        var noticeClass = type === 'error' ? 'notice-error' : 'notice-success';
        var $notice = $('<div class="notice ' + noticeClass + ' is-dismissible ci7k-notice"><p>' + message + '</p></div>');
        $('.couponis7k-wrap').prepend($notice);

        setTimeout(function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }

    $('.ci7k-approve-coupon').on('click', function() {
        var $btn = $(this);
        var couponId = $btn.data('id');

        $btn.prop('disabled', true).text(ci7k_ajax.strings.processing);

        $.ajax({
            url: ci7k_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'ci7k_approve_coupon',
                nonce: ci7k_ajax.nonce,
                coupon_id: couponId
            },
            success: function(response) {
                if (response.success) {
                    showNotice(response.data.message, 'success');
                    location.reload();
                } else {
                    showNotice(response.data.message, 'error');
                    $btn.prop('disabled', false).text('Aprovar');
                }
            },
            error: function() {
                showNotice(ci7k_ajax.strings.error, 'error');
                $btn.prop('disabled', false).text('Aprovar');
            }
        });
    });

    $('.ci7k-reject-coupon').on('click', function() {
        var $btn = $(this);
        var couponId = $btn.data('id');

        $btn.prop('disabled', true).text(ci7k_ajax.strings.processing);

        $.ajax({
            url: ci7k_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'ci7k_reject_coupon',
                nonce: ci7k_ajax.nonce,
                coupon_id: couponId
            },
            success: function(response) {
                if (response.success) {
                    showNotice(response.data.message, 'success');
                    location.reload();
                } else {
                    showNotice(response.data.message, 'error');
                    $btn.prop('disabled', false).text('Rejeitar');
                }
            },
            error: function() {
                showNotice(ci7k_ajax.strings.error, 'error');
                $btn.prop('disabled', false).text('Rejeitar');
            }
        });
    });

    $('.ci7k-publish-coupon').on('click', function() {
        var $btn = $(this);
        var couponId = $btn.data('id');

        $btn.prop('disabled', true).text(ci7k_ajax.strings.processing);

        $.ajax({
            url: ci7k_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'ci7k_publish_coupon',
                nonce: ci7k_ajax.nonce,
                coupon_id: couponId
            },
            success: function(response) {
                if (response.success) {
                    showNotice(response.data.message, 'success');
                    location.reload();
                } else {
                    showNotice(response.data.message, 'error');
                    $btn.prop('disabled', false).text('Publicar');
                }
            },
            error: function() {
                showNotice(ci7k_ajax.strings.error, 'error');
                $btn.prop('disabled', false).text('Publicar');
            }
        });
    });

    $('.ci7k-rewrite-title').on('click', function() {
        var $btn = $(this);
        var couponId = $btn.data('id');

        $btn.prop('disabled', true).text(ci7k_ajax.strings.processing);

        $.ajax({
            url: ci7k_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'ci7k_rewrite_title',
                nonce: ci7k_ajax.nonce,
                coupon_id: couponId
            },
            success: function(response) {
                if (response.success) {
                    showNotice(response.data.message, 'success');
                    var $card = $btn.closest('.couponis7k-coupon-card');
                    $card.find('.couponis7k-coupon-title').text(response.data.new_title);
                    $btn.prop('disabled', false).text('Reescrever Título (IA)');
                } else {
                    showNotice(response.data.message, 'error');
                    $btn.prop('disabled', false).text('Reescrever Título (IA)');
                }
            },
            error: function() {
                showNotice(ci7k_ajax.strings.error, 'error');
                $btn.prop('disabled', false).text('Reescrever Título (IA)');
            }
        });
    });

    $('.ci7k-rewrite-description').on('click', function() {
        var $btn = $(this);
        var couponId = $btn.data('id');

        $btn.prop('disabled', true).text(ci7k_ajax.strings.processing);

        $.ajax({
            url: ci7k_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'ci7k_rewrite_description',
                nonce: ci7k_ajax.nonce,
                coupon_id: couponId
            },
            success: function(response) {
                if (response.success) {
                    showNotice(response.data.message, 'success');
                    var $card = $btn.closest('.couponis7k-coupon-card');
                    $card.find('.couponis7k-coupon-description').text(response.data.new_description);
                    $btn.prop('disabled', false).text('Reescrever Descrição (IA)');
                } else {
                    showNotice(response.data.message, 'error');
                    $btn.prop('disabled', false).text('Reescrever Descrição (IA)');
                }
            },
            error: function() {
                showNotice(ci7k_ajax.strings.error, 'error');
                $btn.prop('disabled', false).text('Reescrever Descrição (IA)');
            }
        });
    });

    $('.ci7k-delete-coupon').on('click', function() {
        if (!confirm(ci7k_ajax.strings.confirm_delete)) {
            return;
        }

        var $btn = $(this);
        var couponId = $btn.data('id');

        $btn.prop('disabled', true).text(ci7k_ajax.strings.processing);

        $.ajax({
            url: ci7k_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'ci7k_delete_coupon',
                nonce: ci7k_ajax.nonce,
                coupon_id: couponId
            },
            success: function(response) {
                if (response.success) {
                    showNotice(response.data.message, 'success');
                    $btn.closest('.couponis7k-coupon-card').fadeOut(function() {
                        $(this).remove();
                    });
                } else {
                    showNotice(response.data.message, 'error');
                    $btn.prop('disabled', false).text('Remover');
                }
            },
            error: function() {
                showNotice(ci7k_ajax.strings.error, 'error');
                $btn.prop('disabled', false).text('Remover');
            }
        });
    });

    $('#apply-bulk-action').on('click', function() {
        var action = $('#bulk-action-selector').val();

        if (!action) {
            showNotice('Selecione uma ação', 'error');
            return;
        }

        var selectedCoupons = [];
        $('.coupon-select:checked').each(function() {
            selectedCoupons.push($(this).val());
        });

        if (selectedCoupons.length === 0) {
            showNotice('Selecione pelo menos um cupom', 'error');
            return;
        }

        if (!confirm(ci7k_ajax.strings.confirm_bulk)) {
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true).text(ci7k_ajax.strings.processing);

        $.ajax({
            url: ci7k_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'ci7k_bulk_action',
                nonce: ci7k_ajax.nonce,
                action_type: action,
                coupon_ids: selectedCoupons
            },
            success: function(response) {
                if (response.success) {
                    showNotice(response.data.message, 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    showNotice(response.data.message, 'error');
                    $btn.prop('disabled', false).text('Aplicar');
                }
            },
            error: function() {
                showNotice(ci7k_ajax.strings.error, 'error');
                $btn.prop('disabled', false).text('Aplicar');
            }
        });
    });

    $('#select-all-coupons').on('change', function() {
        $('.coupon-select').prop('checked', $(this).prop('checked'));
    });
});
