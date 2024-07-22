jQuery(document).ready(function ($) {
    let $modal = $('.forvoyez-api-settings-modal');
    let $apiKeyInput = $('.forvoyez-api-key-input');
    let $toggleVisibility = $('.forvoyez-toggle-visibility');

    $toggleVisibility.on('click', function() {
        if ($apiKeyInput.attr('type') === 'password') {
            $apiKeyInput.attr('type', 'text');
            $toggleVisibility.html(`
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M3.707 2.293a1 1 0 00-1.414 1.414l14 14a1 1 0 001.414-1.414l-1.473-1.473A10.014 10.014 0 0019.542 10C18.268 5.943 14.478 3 10 3a9.958 9.958 0 00-4.512 1.074l-1.78-1.781zm4.261 4.26l1.514 1.515a2.003 2.003 0 012.45 2.45l1.514 1.514a4 4 0 00-5.478-5.478z" clip-rule="evenodd" />
                    <path d="M12.454 16.697L9.75 13.992a4 4 0 01-3.742-3.741L2.335 6.578A9.98 9.98 0 00.458 10c1.274 4.057 5.065 7 9.542 7 .847 0 1.669-.105 2.454-.303z" />
                </svg>
            `);
        } else {
            $apiKeyInput.attr('type', 'password');
            $toggleVisibility.html(`
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                    <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                </svg>
            `);
        }
    });

    $('.forvoyez-open-api-settings, .forvoyez-open-settings').on('click', function () {
        $modal.removeClass('hidden');
    });

    $('.forvoyez-close-modal').on('click', function() {
        $modal.addClass('hidden');
    });

    $('.forvoyez-save-api-key').on('click', function () {
        let apiKey = $apiKeyInput.val();

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
                    $modal.addClass('hidden');
                    $apiKeyInput.val(apiKey);
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