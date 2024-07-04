(function ($) {
    'use strict';

    let allDetailsVisible = false;
    let isLoading = false;

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

        attachEventHandlers();
        updateImageCount();

        // Handle pagination clicks
        $(document).on('click', '.page-numbers', function(e) {
            e.preventDefault();
            var url = $(this).attr('href');
            loadImages(url);
        });

        // Handle filter form submission
        $('.forvoyez-filters form').on('submit', function(e) {
            e.preventDefault();
            var url = $(this).attr('action') + '?' + $(this).serialize();
            loadImages(url);
        });

        function loadImages(url) {
            $.ajax({
                url: url,
                type: 'GET',
                success: function(response) {
                    $('.forvoyez-image-grid').html($(response).find('.forvoyez-image-grid').html());
                    $('.forvoyez-filters').html($(response).find('.forvoyez-filters').html());
                    history.pushState(null, '', url);
                },
                error: function() {
                    showNotification('Failed to load images', 'error');
                }
            });
        }
    });

    function toggleAllImageDetails(show) {
        $('.forvoyez-image-item').each(function () {
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

    function loadMoreImages(count) {
        if (isLoading) return;
        isLoading = true;

        var $grid = $('.forvoyez-image-grid');
        var offset = parseInt($grid.data('offset'), 10);
        var limit = count || 1;

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'forvoyez_load_more_images',
                offset: offset,
                limit: limit,
                nonce: forvoyezData.nonce
            },
            success: function (response) {
                isLoading = false;
                if (response.success && response.data.html) {
                    $grid.append(response.data.html);
                    $grid.data('offset', offset + response.data.count);
                    attachEventHandlers();
                    updateImageCount();
                } else if (response.success && response.data.count === 0) {
                    showNotification('No more images to load', 'info');
                }
            },
            error: function () {
                isLoading = false;
                showNotification('Failed to load more images', 'error');
            }
        });
    }

    function attachEventHandlers() {
        $('.forvoyez-image-item').off('click').on('click', function (e) {
            e.preventDefault();
            toggleImageDetails($(this));
        });

        $('.forvoyez-see-more').off('click').on('click', function (e) {
            e.stopPropagation();
            toggleImageDetails($(this).closest('.forvoyez-image-item'));
        });

        $('.forvoyez-analyze-button').off('click').on('click', function (e) {
            e.stopPropagation();
            var imageId = $(this).closest('.forvoyez-image-item').data('image-id');
            analyzeImage(imageId);
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

    function analyzeImage(imageId) {
        var $imageItem = $('.forvoyez-image-item[data-image-id="' + imageId + '"]');
        var $loader = $imageItem.find('.forvoyez-loader');

        $loader.css('display', 'flex');

        // Simulate API call with setTimeout
        setTimeout(function () {
            var fakeApiResponse = {
                alt_text: 'New alt text for image ' + imageId,
                title: 'New title for image ' + imageId,
                caption: 'New caption for image ' + imageId
            };
            updateImageMetadata(imageId, fakeApiResponse);
            $loader.hide();
        }, 2000); // Simulate 2 second delay
    }

    function updateImageMetadata(imageId, metadata) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'forvoyez_update_image_metadata',
                image_id: imageId,
                metadata: metadata,
                nonce: forvoyezData.nonce
            },
            success: function (response) {
                if (response.success) {
                    showNotification('Metadata updated successfully', 'success');
                    markImageAsAnalyzed(imageId, metadata);
                } else {
                    showNotification('Metadata update failed: ' + response.data, 'error');
                }
            },
            error: function () {
                showNotification('AJAX request failed', 'error');
            }
        });
    }

    function markImageAsAnalyzed(imageId, metadata) {
        var $imageItem = $('.forvoyez-image-item[data-image-id="' + imageId + '"]');
        $imageItem.addClass('forvoyez-analyzed');
        $imageItem.find('.forvoyez-analyze-button').remove();
        $imageItem.append('<div class="forvoyez-checkmark"><span class="dashicons dashicons-yes-alt"></span></div>');

        // Update metadata icons
        var $metadataIcons = $imageItem.find('.forvoyez-metadata-icons');
        $metadataIcons.empty();
        if (!metadata.alt_text) {
            $metadataIcons.append('<span class="dashicons dashicons-editor-textcolor" title="Missing Alt Text"></span>');
        }
        if (!metadata.title) {
            $metadataIcons.append('<span class="dashicons dashicons-heading" title="Missing Title"></span>');
        }
        if (!metadata.caption) {
            $metadataIcons.append('<span class="dashicons dashicons-editor-quote" title="Missing Caption"></span>');
        }

        // Update image details
        var $details = $imageItem.find('.forvoyez-image-details');
        $details.find('p:contains("Title:")').html('<strong>Title:</strong> ' + (metadata.title || 'Not set'));
        $details.find('p:contains("Alt Text:")').html('<strong>Alt Text:</strong> ' + (metadata.alt_text || 'Not set'));
        $details.find('p:contains("Caption:")').html('<strong>Caption:</strong> ' + (metadata.caption || 'Not set'));
    }

    function updateImageCount() {
        var remainingImages = $('.forvoyez-image-item').length;
        $('.forvoyez-image-count').text(remainingImages);

        if (remainingImages === 0) {
            showNotification('All images have been processed!', 'success');
            $('.forvoyez-image-grid').html('<p>All images have been processed. Great job!</p>');
        } else if (remainingImages < 21) {
            loadMoreImages(21 - remainingImages);
        }
    }

    function showNotification(message, type) {
        var $notification = $('<div class="forvoyez-notification ' + type + '">' + message + '</div>');
        $('body').append($notification);

        setTimeout(function () {
            $notification.css('opacity', '1');
        }, 10);

        setTimeout(function () {
            $notification.css('opacity', '0');
            setTimeout(function () {
                $notification.remove();
            }, 300);
        }, 3000);
    }


})(jQuery);