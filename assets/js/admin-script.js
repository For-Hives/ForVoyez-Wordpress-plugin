(function ($) {
    'use strict';

    let allDetailsVisible = false;
    let isLoading = false;

    $(document).ready(function () {
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

        // Handle "See More" button click
        $('.forvoyez-image-grid').on('click', '.forvoyez-see-more', function (e) {
            e.preventDefault();
            let $item = $(this).closest('.forvoyez-image-item');
            toggleImageDetails($item);
        });

        // Handle analyze button click
        $('.forvoyez-image-grid').on('click', '.forvoyez-analyze-button', function (e) {
            e.preventDefault();
            let imageId = $(this).closest('.forvoyez-image-item').data('image-id');
            analyzeImage(imageId);
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
            let imageId = $(this).closest('.forvoyez-image-item').data('image-id');
            analyzeImage(imageId);
        });

        attachEventHandlers();
        updateImageCount();

        // Handle pagination clicks
        $(document).on('click', '.page-numbers', function (e) {
            e.preventDefault();
            let url = $(this).attr('href');
            loadImages(url);
        });

        // Handle filter form submission
        $('.forvoyez-filters form').on('submit', function (e) {
            e.preventDefault();
            let url = $(this).attr('action') + '?' + $(this).serialize();
            loadImages(url);
        });

        // Select all functionality
        $('#forvoyez-select-all').on('change', function () {
            $('.forvoyez-image-checkbox').prop('checked', $(this).is(':checked'));
        });

        // Bulk analyze functionality
        $('#forvoyez-bulk-analyze').on('click', function () {
            let selectedImages = $('.forvoyez-image-checkbox:checked').map(function () {
                return $(this).val();
            }).get();

            if (selectedImages.length === 0) {
                showNotification('Please select at least one image to analyze', 'info');
                return;
            }

            analyzeBulkImages(selectedImages);
        });
    });

    function analyzeImage(imageId, isNotificationActivated = true) {
        return new Promise((resolve) => {
            let $imageItem = $('.forvoyez-image-item[data-image-id="' + imageId + '"]');
            let $loader = $imageItem.find('.forvoyez-loader');

            $loader.css('display', 'flex');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'forvoyez_analyze_image',
                    image_id: imageId,
                    nonce: forvoyezData.nonce
                },
                success: function (response) {
                    console.log('AJAX request succeeded for image ' + imageId);
                    console.log(response);
                    if (response.success) {
                        if (isNotificationActivated) {
                            showNotification('Metadata updated successfully for image ' + imageId, 'success');
                        }
                        markImageAsAnalyzed(imageId, response.data.metadata);
                        resolve(true);
                    } else {
                        let errorMessage = 'Unknown error occurred';
                        let errorCode = 'unknown_error';

                        if (response.data) {
                            errorMessage = response.data.message;
                            errorCode = response.data.code || 'unknown_error';
                        } else if (response.error && response.error.message) {
                            errorMessage = response.error.message;
                            errorCode = response.error.code || 'unknown_error';
                        }

                        if (isNotificationActivated) {
                            showErrorNotification(errorMessage, errorCode, imageId);
                        }
                        resolve(false);
                    }
                    $loader.hide();
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    console.log('AJAX request failed for image ' + imageId + ': ' + textStatus);
                    if (isNotificationActivated) {
                        showErrorNotification('AJAX request failed: ' + textStatus, 'ajax_error', imageId);
                    }
                    $loader.hide();
                    resolve(false);
                }
            });
        });
    }

    function showErrorNotification(message, code, imageId) {
        let fullMessage = `Error processing image ${imageId}: ${message}`;
        let detailedMessage = `Error code: ${code}`;

        // Show error notification
        showNotification(fullMessage, 'error', 500000);

        // Log detailed error to console
        console.error(fullMessage, detailedMessage);
    }

    function analyzeBulkImages(imageIds) {
        const totalImages = imageIds.length;
        let processedCount = 0;
        let failedCount = 0;

        showNotification(`Starting analysis of ${totalImages} images...`, 'info', 0);

        function updateProgress() {
            const progress = Math.round(((processedCount + failedCount) / totalImages) * 100);
            showNotification(`Processing: ${progress}% complete. Successful: ${processedCount}, Failed: ${failedCount}`, 'info', 0);
        }

        function processSingleImage(imageId) {
            return new Promise((resolve) => {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'forvoyez_analyze_single_image',
                        image_id: imageId,
                        nonce: forvoyezData.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            processedCount++;
                            markImageAsAnalyzed(imageId, response.data.metadata);
                        } else {
                            failedCount++;
                            showErrorNotification(response.data.error.message, response.data.error.code, imageId);
                        }
                        updateProgress();
                        resolve();
                    },
                    error: function() {
                        failedCount++;
                        showErrorNotification('AJAX request failed', 'ajax_error', imageId);
                        updateProgress();
                        resolve();
                    }
                });
            });
        }

        const batchSize = 7; // Process 7 images at a time

        async function processAllImages() {
            for (let i = 0; i < totalImages; i += batchSize) {
                const batch = imageIds.slice(i, Math.min(i + batchSize, totalImages));
                await Promise.all(batch.map(imageId => processSingleImage(imageId)));
            }
            showNotification(`Analysis complete. Successful: ${processedCount}, Failed: ${failedCount}`, 'success', 5000);
        }

        processAllImages();
    }

    function loadImages(url) {
        $.ajax({
            url: url,
            type: 'GET',
            success: function (response) {
                let $response = $(response);
                $('.forvoyez-image-grid').html($response.find('.forvoyez-image-grid').html());
                $('.forvoyez-filters').html($response.find('.forvoyez-filters').html());
                $('.pagination').html($response.find('.pagination').html());

                // Update displayed images count
                let displayedImages = $response.find('.forvoyez-image-grid .forvoyez-image-item').length;
                $('.forvoyez-displayed-images strong').text(displayedImages);

                history.pushState(null, '', url);

                // Reattach event handlers
                attachEventHandlers();
            },
            error: function () {
                showNotification('Failed to load images', 'error');
            }
        });
    }

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

        let $grid = $('.forvoyez-image-grid');
        let offset = parseInt($grid.data('offset'), 10);
        let limit = count || 1;

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
        // Pagination clicks
        $('.pagination .page-numbers').on('click', function (e) {
            e.preventDefault();
            let url = $(this).attr('href');
            loadImages(url);
        });

        // Filter form submission
        $('.forvoyez-filters form').on('submit', function (e) {
            e.preventDefault();
            let url = $(this).attr('action') + '?' + $(this).serialize();
            loadImages(url);
        });

        $('.forvoyez-see-more').off('click').on('click', function (e) {
            e.stopPropagation();
            toggleImageDetails($(this).closest('.forvoyez-image-item'));
        });

        $('.forvoyez-analyze-button').off('click').on('click', function (e) {
            e.stopPropagation();
            let imageId = $(this).closest('.forvoyez-image-item').data('image-id');
            analyzeImage(imageId);
        });
    }

    function toggleImageDetails($item) {
        let $details = $item.find('.forvoyez-image-details');
        let $seeMoreButton = $item.find('.forvoyez-see-more');

        if ($details.is(':visible')) {
            $details.slideUp();
            $seeMoreButton.find('.dashicons').removeClass('dashicons-hidden').addClass('dashicons-visibility');
        } else {
            $details.slideDown();
            $seeMoreButton.find('.dashicons').removeClass('dashicons-visibility').addClass('dashicons-hidden');
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

    function markImageAsAnalyzed(imageId, metadata) {
        let $imageItem = $('.forvoyez-image-item[data-image-id="' + imageId + '"]');
        $imageItem.addClass('forvoyez-analyzed');
        $imageItem.find('.forvoyez-analyze-button').remove();
        $imageItem.append('<div class="forvoyez-checkmark"><span class="dashicons dashicons-yes-alt"></span></div>');

        // Update metadata icons
        let $metadataIcons = $imageItem.find('.forvoyez-metadata-icons');
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
        let $details = $imageItem.find('.forvoyez-image-details');
        $details.find('p:contains("Title:")').html('<strong>Title:</strong> ' + (metadata.title || 'Not set'));
        $details.find('p:contains("Alt Text:")').html('<strong>Alt Text:</strong> ' + (metadata.alt_text || 'Not set'));
        $details.find('p:contains("Caption:")').html('<strong>Caption:</strong> ' + (metadata.caption || 'Not set'));
    }

    function updateImageCount() {
        let remainingImages = $('.forvoyez-image-item').length;
        $('.forvoyez-image-count').text(remainingImages);

        if (remainingImages === 0) {
            showNotification('All images have been processed!', 'success');
            $('.forvoyez-image-grid').html('<p>All images have been processed. Great job!</p>');
        } else if (remainingImages < 21) {
            loadMoreImages(21 - remainingImages);
        }
    }

    let currentNotification = null;

    function showNotification(message, type, duration = 3000, detailedMessage = '') {
        if (currentNotification) {
            currentNotification.remove();
        }

        let notificationHtml = `
        <div class="forvoyez-notification ${type}">
            <div class="notification-main">${message}</div>
            ${detailedMessage ? `<div class="notification-details">${detailedMessage}</div>` : ''}
        </div>
    `;

        let $notification = $(notificationHtml);
        $('body').append($notification);
        currentNotification = $notification;

        setTimeout(function () {
            $notification.addClass('show');
        }, 10);

        if (duration > 0) {
            setTimeout(function () {
                $notification.removeClass('show');
                setTimeout(function () {
                    $notification.remove();
                    if (currentNotification === $notification) {
                        currentNotification = null;
                    }
                }, 300);
            }, duration);
        }
    }

    // make the function global
    window.showNotification = showNotification;
})(jQuery);