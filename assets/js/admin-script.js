(function ($) {
    'use strict';

    let allDetailsVisible = false;

    $(document).ready(function () {
        // Image item click handling
        $('.forvoyez-image-item').on('click', function (e) {
            e.preventDefault();
            toggleImageDetails($(this));
        });

        // See More click handling
        $('.forvoyez-see-more').on('click', function (e) {
            e.stopPropagation();
            toggleImageDetails($(this).closest('.forvoyez-image-item'));
        });

        // Close details when clicking outside
        $(document).on('click', function (e) {
            if (!$(e.target).closest('.forvoyez-image-item').length && !$(e.target).closest('#forvoyez-toggle-menu').length) {
                closeAllImageDetails();
            }
        });

        // Toggle all details visibility
        $('#forvoyez-toggle-menu').on('click', function () {
            allDetailsVisible = !allDetailsVisible;
            toggleAllImageDetails(allDetailsVisible);
            $(this).text(allDetailsVisible ? 'Hide All Details' : 'Show All Details');
        });

        // Handle global visibility toggles for specific metadata
        $('#toggle-alt, #toggle-title, #toggle-caption').on('change', function () {
            let type = this.id.replace('toggle-', '');
            let isChecked = $(this).prop('checked');
            toggleVisibility(type, isChecked);
        });

        // Handle analyze button click
        $('.forvoyez-analyze-button').on('click', function (e) {
            e.stopPropagation(); // Prevent triggering the image item click
            var imageId = $(this).closest('.forvoyez-image-item').data('image-id');
            analyzeImage(imageId);
        });
    });

    function analyzeImage(imageId) {
        // TODO: Implement the actual analysis logic here
        console.log('Analyzing image with ID:', imageId);

        // Example of how you might call an AJAX function to your WordPress backend
        $.ajax({
            url: ajaxurl, // This is a global variable in WordPress admin
            type: 'POST',
            data: {
                action: 'forvoyez_analyze_image',
                image_id: imageId,
                nonce: forvoyezData.nonce // Assume you've localized this data
            },
            success: function(response) {
                if (response.success) {
                    console.log('Analysis complete:', response.data);
                    // TODO: Update the UI with the analysis results
                } else {
                    console.error('Analysis failed:', response.data);
                    // TODO: Show error message to user
                }
            },
            error: function() {
                console.error('AJAX request failed');
                // TODO: Show error message to user
            }
        });
    }

    function toggleAllImageDetails(show) {
        $('.forvoyez-image-item').each(function() {
            let $item = $(this);
            let $details = $item.find('.forvoyez-image-details');
            let $seeMore = $item.find('.forvoyez-see-more');
            let $seeMoreText = $seeMore.find('.see-more-text');
            let $hideDetailsText = $seeMore.find('.hide-details-text');

            if (show) {
                $details.slideDown();
                $item.addClass('details-visible');
                $seeMore.find('.dashicons').removeClass('dashicons-visibility').addClass('dashicons-hidden');
                $seeMoreText.hide();
                $hideDetailsText.show();
            } else {
                $details.slideUp();
                $item.removeClass('details-visible');
                $seeMore.find('.dashicons').removeClass('dashicons-hidden').addClass('dashicons-visibility');
                $seeMoreText.show();
                $hideDetailsText.hide();
            }
        });
    }

    function toggleImageDetails($item) {
        let $details = $item.find('.forvoyez-image-details');
        let $seeMore = $item.find('.forvoyez-see-more');
        let $seeMoreText = $seeMore.find('.see-more-text');
        let $hideDetailsText = $seeMore.find('.hide-details-text');

        if ($item.hasClass('details-visible')) {
            $details.slideUp();
            $item.removeClass('details-visible');
            $seeMore.find('.dashicons').removeClass('dashicons-hidden').addClass('dashicons-visibility');
            $seeMoreText.show();
            $hideDetailsText.hide();
        } else {
            $details.slideDown();
            $item.addClass('details-visible');
            $seeMore.find('.dashicons').removeClass('dashicons-visibility').addClass('dashicons-hidden');
            $seeMoreText.hide();
            $hideDetailsText.show();
        }
    }

    function closeAllImageDetails() {
        allDetailsVisible = false;
        toggleAllImageDetails(false);
        $('#forvoyez-toggle-menu').text('Show All Details');
    }

    function toggleVisibility(type, show) {
        $('.forvoyez-image-item').each(function () {
            let $item = $(this);
            let $details = $item.find('.forvoyez-image-details');
            let $p = $details.find('p:contains("' + capitalizeFirstLetter(type) + ':")');

            if (show) {
                $p.show();
            } else {
                $p.hide();
            }

            // Update the metadata icons
            let $icon = $item.find('.forvoyez-metadata-icons .dashicons-' + getIconClass(type));
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
})(jQuery);