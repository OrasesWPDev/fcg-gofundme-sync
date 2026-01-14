/**
 * FCG GoFundMe Pro Sync Admin JavaScript
 *
 * @package FCG_GoFundMe_Sync
 */

(function($) {
    'use strict';

    // Sync Now button (meta box)
    $(document).on('click', '.fcg-sync-now-btn', function() {
        var $btn = $(this);
        var $spinner = $btn.siblings('.spinner');
        var postId = $btn.data('post-id');

        $btn.prop('disabled', true);
        $spinner.addClass('is-active');

        $.post(fcgGfmAdmin.ajaxUrl, {
            action: 'fcg_gfm_sync_now',
            nonce: fcgGfmAdmin.nonce,
            post_id: postId
        })
        .done(function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('Sync failed: ' + response.data);
            }
        })
        .fail(function() {
            alert('Sync request failed');
        })
        .always(function() {
            $btn.prop('disabled', false);
            $spinner.removeClass('is-active');
        });
    });

    // Sync All button (settings page)
    $('#fcg-sync-all').on('click', function() {
        var $btn = $(this);
        var $spinner = $btn.siblings('.spinner');

        $btn.prop('disabled', true);
        $spinner.addClass('is-active');

        $.post(fcgGfmAdmin.ajaxUrl, {
            action: 'fcg_gfm_sync_now',
            nonce: fcgGfmAdmin.nonce,
            post_id: 0
        })
        .done(function(response) {
            if (response.success) {
                alert(response.data.message);
                location.reload();
            } else {
                alert('Sync failed: ' + response.data);
            }
        })
        .fail(function() {
            alert('Sync request failed');
        })
        .always(function() {
            $btn.prop('disabled', false);
            $spinner.removeClass('is-active');
        });
    });

})(jQuery);
