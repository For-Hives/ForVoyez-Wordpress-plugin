(function($) {
    'use strict';

    $(document).ready(function() {
        $('.forvoyez-image-item').on('click', function(e) {
            e.preventDefault();
            var $item = $(this);
            var $details = $item.find('.forvoyez-image-details');

            if ($details.is(':visible')) {
                $details.slideUp();
            } else {
                $('.forvoyez-image-details').slideUp(); // Hide any open details
                $details.slideDown();
            }
        });

        // Stop propagation for "See More" click to prevent immediate closing
        $('.forvoyez-see-more').on('click', function(e) {
            e.stopPropagation();
            $(this).closest('.forvoyez-image-item').click();
        });

        // Close details when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.forvoyez-image-item').length) {
                $('.forvoyez-image-details').slideUp();
            }
        });
    });

})(jQuery);