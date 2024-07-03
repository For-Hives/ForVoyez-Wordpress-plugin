(function ($) {
    'use strict';

    $(document).ready(function () {
        $('.forvoyez-image-item').on('click', function (e) {
            e.preventDefault();
            var $item = $(this);
            var $details = $item.find('.forvoyez-image-details');
            var $seeMore = $item.find('.forvoyez-see-more');
            var $seeMoreText = $seeMore.find('.see-more-text');
            var $hideDetailsText = $seeMore.find('.hide-details-text');

            if ($item.hasClass('details-visible')) {
                $details.slideUp();
                $item.removeClass('details-visible');
                $seeMore.find('.dashicons').removeClass('dashicons-hidden').addClass('dashicons-visibility');
                $seeMoreText.show();
                $hideDetailsText.hide();
            } else {
                $('.forvoyez-image-item').removeClass('details-visible');
                $('.forvoyez-image-details').slideUp();
                $('.forvoyez-see-more .dashicons').removeClass('dashicons-hidden').addClass('dashicons-visibility');
                $('.forvoyez-see-more .see-more-text').show();
                $('.forvoyez-see-more .hide-details-text').hide();

                $details.slideDown();
                $item.addClass('details-visible');
                $seeMore.find('.dashicons').removeClass('dashicons-visibility').addClass('dashicons-hidden');
                $seeMoreText.hide();
                $hideDetailsText.show();
            }
        });

        // Stop propagation for "See More" click to prevent immediate closing
        $('.forvoyez-see-more').on('click', function (e) {
            e.stopPropagation();
            $(this).closest('.forvoyez-image-item').click();
        });

        // Close details when clicking outside
        $(document).on('click', function (e) {
            if (!$(e.target).closest('.forvoyez-image-item').length) {
                $('.forvoyez-image-item').removeClass('details-visible');
                $('.forvoyez-image-details').slideUp();
                $('.forvoyez-see-more .dashicons').removeClass('dashicons-hidden').addClass('dashicons-visibility');
                $('.forvoyez-see-more .see-more-text').show();
                $('.forvoyez-see-more .hide-details-text').hide();
            }
        });
    });

    javascriptCopy(function ($) {
        'use strict';

        $(document).ready(function () {
            // Existing code...

            // Toggle menu visibility
            $('#forvoyez-toggle-menu').on('click', function () {
                $('#forvoyez-visibility-menu').toggle();
            });

            // Handle global visibility toggles
            $('#toggle-alt, #toggle-title, #toggle-caption').on('change', function () {
                var type = this.id.replace('toggle-', '');
                var isChecked = $(this).prop('checked');
                toggleVisibility(type, isChecked);
            });

            function toggleVisibility(type, show) {
                $('.forvoyez-image-item').each(function () {
                    var $item = $(this);
                    var $details = $item.find('.forvoyez-image-details');
                    var $p = $details.find('p:contains("' + capitalizeFirstLetter(type) + ':")');

                    if (show) {
                        $p.show();
                    } else {
                        $p.hide();
                    }

                    // Update the metadata icons
                    var $icon = $item.find('.forvoyez-metadata-icons .dashicons-' + getIconClass(type));
                    if (show) {
                        $icon.show();
                    } else {
                        $icon.hide();
                    }
                });
            }

            function capitalizeFirstLetter(string) {
                return string.charAt(0).toUpperCase() + string.slice(1);
            }

            function getIconClass(type) {
                switch (type) {
                    case 'alt':
                        return 'editor-textcolor';
                    case 'title':
                        return 'heading';
                    case 'caption':
                        return 'editor-quote';
                    default:
                        return '';
                }
            }

            // Close menu when clicking outside
            $(document).on('click', function (e) {
                if (!$(e.target).closest('.forvoyez-global-controls').length) {
                    $('#forvoyez-visibility-menu').hide();
                }
            });
        });
    });
})(jQuery);