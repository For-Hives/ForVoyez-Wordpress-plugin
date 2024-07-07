jQuery(document).ready(function ($) {
    $('#forvoyez-open-api-settings').on('click', function () {
        $('#forvoyez-api-settings-modal').show();
    });

    $('#forvoyez-save-api-key').on('click', function () {
        var apiKey = $('#forvoyez_api_key').val();
        // Implement AJAX call to save API key
    });
});