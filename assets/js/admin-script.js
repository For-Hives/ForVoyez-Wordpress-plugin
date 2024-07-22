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
            $(this).prop('disabled', true);
            analyzeImage(imageId)
                .then(() => {
                    $(this).prop('disabled', false);
                })
                .catch((error) => {
                    console.error('Error analyzing image:', error);
                    $(this).prop('disabled', false);
                });
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

        // Handle filter form submission
        $('.forvoyez-filters form').on('submit', function(e) {
            e.preventDefault();
            var url = $(this).attr('action') + '?' + $(this).serialize();
            loadImages(url);
        });

        // Handle pagination clicks
        $(document).on('click', '.pagination a', function(e) {
            e.preventDefault();
            var url = $(this).attr('href');
            loadImages(url);
        });

        // Handle per page change
        $('select[name="per_page"]').on('change', function() {
            var form = $(this).closest('form');
            var url = form.attr('action') + '?' + form.serialize();
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
        let $imageItem = $('.forvoyez-image-item[data-image-id="' + imageId + '"]');
        let $loader = $imageItem.find('.forvoyez-loader');

        $loader.css('display', 'flex');

        return new Promise((resolve) => {
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

        function processBatch(batch) {
            return new Promise((resolve) => {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'forvoyez_process_image_batch',
                        image_ids: batch,
                        nonce: forvoyezData.nonce
                    },
                    success: function (response) {
                        if (response.success) {
                            response.data.results.forEach(result => {
                                if (result.success) {
                                    processedCount++;
                                    markImageAsAnalyzed(result.id, result.metadata);
                                } else {
                                    failedCount++;
                                    showErrorNotification(result.message, result.code, result.id);
                                }
                            });
                        } else {
                            batch.forEach(imageId => {
                                failedCount++;
                                showErrorNotification('Batch processing failed', 'batch_error', imageId);
                            });
                        }
                        updateProgress();
                        resolve();
                    },
                    error: function () {
                        batch.forEach(imageId => {
                            failedCount++;
                            showErrorNotification('AJAX request failed', 'ajax_error', imageId);
                        });
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
                await processBatch(batch);
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
                var $response = $(response);
                $('.forvoyez-image-grid').html($response.find('.forvoyez-image-grid').html());
                $('.pagination').html($response.find('.pagination').html());
                $('.forvoyez-displayed-images').html($response.find('.forvoyez-displayed-images').html());
                $('.forvoyez-filters').html($response.find('.forvoyez-filters').html());
                history.pushState(null, '', url);
            },
            error: function () {
                alert('Failed to load images');
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
        $('.pagination .page-numbers').off('click').on('click', function (e) {
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

        if ($details.hasClass('hidden')) {
            $details.removeClass('hidden');
            $seeMoreButton.find('svg').replaceWith(`
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
            </svg>
        `);
        } else {
            $details.addClass('hidden');
            $seeMoreButton.find('svg').replaceWith(`
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
            </svg>
        `);
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
        $imageItem.addClass('opacity-50');
        $imageItem.find('.forvoyez-analyze-button').remove();
        $imageItem.append(`
        <div class="absolute top-2 right-2 bg-green-500 rounded-full p-1">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
        </div>
    `);

        // Update metadata icons
        let $metadataIcons = $imageItem.find('.forvoyez-metadata-icons');
        $metadataIcons.empty();
        if (!metadata.alt_text) {
            $metadataIcons.append(`
            <span class="text-red-500 mr-1" title="Missing Alt Text">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </span>
        `);
        }
        if (!metadata.title) {
            $metadataIcons.append(`
            <span class="text-red-500 mr-1" title="Missing Title">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
            </span>
        `);
        }
        if (!metadata.caption) {
            $metadataIcons.append(`
            <span class="text-red-500" title="Missing Caption">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                </svg>
            </span>
        `);
        }

        // Update image details
        let $details = $imageItem.find('.forvoyez-image-details');
        $details.find('p:contains("Title:")').html('<strong class="font-semibold">Title:</strong> ' + (metadata.title || 'Not set'));
        $details.find('p:contains("Alt Text:")').html('<strong class="font-semibold">Alt Text:</strong> ' + (metadata.alt_text || 'Not set'));
        $details.find('p:contains("Caption:")').html('<strong class="font-semibold">Caption:</strong> ' + (metadata.caption || 'Not set'));
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

    function showNotification(message, type = 'info', duration = 3000) {
        const notification = document.createElement('div');
        notification.className = `fixed bottom-4 right-4 p-4 rounded-lg shadow-lg transition-opacity duration-300 opacity-0 ${
            type === 'success' ? 'bg-green-500' :
                type === 'error' ? 'bg-red-500' :
                    'bg-blue-500'
        } text-white`;
        notification.textContent = message;

        document.body.appendChild(notification);

        // Fade in
        setTimeout(() => {
            notification.classList.remove('opacity-0');
        }, 10);

        // Fade out and remove
        setTimeout(() => {
            notification.classList.add('opacity-0');
            setTimeout(() => {
                document.body.removeChild(notification);
            }, 300);
        }, duration);
    }

    // make the function global
    window.showNotification = showNotification;
})(jQuery);