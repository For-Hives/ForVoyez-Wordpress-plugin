(function($) {
    $(document).on('click', '.forvoyez-analyze-button', function() {
        var $button = $(this);
        var imageId = $button.data('image-id');

        $button.prop('disabled', true).text('Analyzing...');
        $button.after('<p>Analysis may take a few seconds...' +
            '<br>Refresh the page after analysis to see updated metadata.</p>');

        $.ajax({
            url: forvoyezData.ajaxurl,
            type: 'POST',
            data: {
                action: 'forvoyez_analyze_image',
                image_id: imageId,
                nonce: forvoyezData.verifyAjaxRequestNonce
            },
            success: function(response) {
                if (response.success) {
                    $button.prop('disabled', true).text('Analyzed !');
                }
            },
            error: function() {
                alert('An error occurred while analyzing the image.');
            },
            complete: function() {
                $button.prop('disabled', true).text('Analyzed !');
                $button.siblings('p').remove();
                $button.after('<p>Analysis complete. Refresh the page to see updated metadata.</p>');
            }
        });
    });
})(jQuery);