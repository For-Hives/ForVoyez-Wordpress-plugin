import {showNotification} from "./admin-script.js";

jQuery(document).ready(function ($) {
    var $modal = $('#forvoyez-api-settings-modal');

    $('#forvoyez-open-api-settings, #forvoyez-open-settings').on('click', function () {
        $modal.show();
    });

    $('#forvoyez-close-modal').on('click', function() {
        $modal.hide();
    });

    $('#forvoyez-save-api-key').on('click', function () {
        var apiKey = $('#forvoyez_api_key').val();

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'forvoyez_save_api_key',
                api_key: apiKey,
                nonce: forvoyezData.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotification('API key saved successfully', 'success');
                    $modal.hide();
                } else {
                    showNotification('Failed to save API key: ' + response.data, 'error');
                }
            },
            error: function() {
                showNotification('Failed to save API key', 'error');
            }
        });
    });
});