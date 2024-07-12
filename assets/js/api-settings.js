jQuery(document).ready(function ($) {
    let $modal = $('#forvoyez-api-settings-modal');
    let $apiKeyInput = $('#forvoyez_api_key');
    let $toggleVisibility = $('#forvoyez-toggle-visibility');

    $toggleVisibility.on('click', function() {
        if ($apiKeyInput.attr('type') === 'password') {
            $apiKeyInput.attr('type', 'text');
            $toggleVisibility.removeClass('dashicons-visibility').addClass('dashicons-hidden');
        } else {
            $apiKeyInput.attr('type', 'password');
            $toggleVisibility.removeClass('dashicons-hidden').addClass('dashicons-visibility');
        }
    });

    $('#forvoyez-open-api-settings, #forvoyez-open-settings').on('click', function () {
        $modal.show();
    });

    $('#forvoyez-close-modal').on('click', function() {
        $modal.hide();
    });

    $('#forvoyez-save-api-key').on('click', function () {
        let apiKey = $('#forvoyez_api_key').val();

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
                    window.showNotification('API key saved successfully', 'success');
                    $modal.hide();
                    // Update the input field with the new value
                    $('#forvoyez_api_key').val(apiKey);
                } else {
                    window.showNotification('Failed to save API key: ' + response.data, 'error');
                }
            },
            error: function() {
                window.showNotification('Failed to save API key', 'error');
            }
        });
    });
});