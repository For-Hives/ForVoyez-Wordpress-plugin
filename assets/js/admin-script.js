(function ($) {
    'use strict';

    $(document).ready(function () {
        let $modal = $('.forvoyez-api-settings-modal');
        let $apiKeyInput = $('.forvoyez-api-key-input');
        let $toggleVisibility = $('.forvoyez-toggle-visibility');

        // API Settings Modal
        $toggleVisibility.on('click', function () {
            if ($apiKeyInput.attr('type') === 'password') {
                $apiKeyInput.attr('type', 'text');
                $toggleVisibility.html(getVisibilityIcon('text'));
            } else {
                $apiKeyInput.attr('type', 'password');
                $toggleVisibility.html(getVisibilityIcon('password'));
            }
        });

        $('#forvoyez-open-settings, .forvoyez-open-api-settings').on('click', function () {
            $modal.removeClass('hidden');
        });

        $('.forvoyez-close-modal').on('click', function () {
            $modal.addClass('hidden');
        });

        $('.forvoyez-save-api-key').on('click', function () {
            let apiKey = $apiKeyInput.val();

            $.ajax({
                url: forvoyezData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'forvoyez_save_api_key',
                    api_key: apiKey,
                    nonce: forvoyezData.nonce
                },
                success: function (response) {
                    if (response.success) {
                        showNotification('API key saved successfully', 'success');
                        $modal.addClass('hidden');
                        $apiKeyInput.val(apiKey);
                    } else {
                        showNotification('Failed to save API key: ' + response.data, 'error');
                    }
                },
                error: function () {
                    showNotification('Failed to save API key', 'error');
                }
            });
        });

        $('.forvoyez-filters form').on('submit', function (e) {
            e.preventDefault();
            var url = $(this).attr('action') + '?' + $(this).serialize();
            loadImages(url);
        });

        $(document).on('click', '.pagination a', function (e) {
            e.preventDefault();
            var url = $(this).attr('href');
            loadImages(url);
        });

        $('select[name="per_page"]').on('change', function () {
            var form = $(this).closest('form');
            var url = form.attr('action') + '?' + form.serialize();
            loadImages(url);
        });
    });

    function toggleImageDetails($item) {
        let $image = $item.find('img');
        let $details = $item.find('.details-view');
        let $seeMoreButtonImages = $item.find('#see-more-button-images');
        let $seeMoreButtonDetails = $item.find('#see-more-button-details');

        if ($details.hasClass('hidden')) {
            $image.addClass('hidden').removeClass('flex flex-col items-start justify-center gap-2');
            $details.addClass('flex flex-col items-start justify-center gap-2').removeClass('hidden');
            $seeMoreButtonImages.addClass('hidden').removeClass('inline-flex');
            $seeMoreButtonDetails.addClass('inline-flex').removeClass('hidden');
        } else {
            $image.addClass('flex flex-col items-start justify-center gap-2').removeClass('hidden');
            $details.addClass('hidden').removeClass('flex flex-col items-start justify-center gap-2');
            $seeMoreButtonImages.addClass('inline-flex').removeClass('hidden');
            $seeMoreButtonDetails.addClass('hidden').removeClass('inline-flex');
        }
    }

    function analyzeImage(imageId, isNotificationActivated = true) {
        let $imageItem = $(`li[data-image-id="${imageId}"]`);
        let $loader = $imageItem.find('.loader');

        $loader.removeClass('hidden');

        return new Promise((resolve) => {
            $.ajax({
                url: forvoyezData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'forvoyez_analyze_image',
                    image_id: imageId,
                    nonce: forvoyezData.nonce
                },
                success: function (response) {
                    if (response.success) {
                        if (isNotificationActivated) {
                            showNotification('Metadata updated successfully for image ' + imageId, 'success');
                        }
                        markImageAsAnalyzed(imageId, response.data.metadata);
                        resolve(true);
                    } else {
                        let errorMessage = response.data ? response.data.message : 'Unknown error occurred';
                        let errorCode = response.data ? (response.data.code || 'unknown_error') : 'unknown_error';
                        if (isNotificationActivated) {
                            showErrorNotification(errorMessage, errorCode, imageId);
                        }
                        $loader.addClass('hidden');
                        resolve(false);
                    }
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    if (isNotificationActivated) {
                        showErrorNotification('AJAX request failed: ' + textStatus, 'ajax_error', imageId);
                    }
                    $loader.addClass('hidden');
                    resolve(false);
                }
            });
        });
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
                    url: forvoyezData.ajaxurl,
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

        const batchSize = 7;

        async function processAllImages() {
            for (let i = 0; i < totalImages; i += batchSize) {
                const batch = imageIds.slice(i, Math.min(i + batchSize, totalImages));
                await processBatch(batch);
            }
            showNotification(`Analysis complete. Successful: ${processedCount}, Failed: ${failedCount}`, 'success', 5000);
        }

        processAllImages();
    }

    function loadImages(page = 1) {
        const perPage = $('#forvoyez-per-page').val();
        const filters = $('#forvoyez-filter-form').serializeArray();

        $('#forvoyez-loader').removeClass('hidden');

        $.ajax({
            url: forvoyezData.ajaxurl,
            type: 'POST',
            data: {
                action: 'forvoyez_load_images',
                nonce: forvoyezData.nonce,
                paged: page,
                per_page: perPage,
                filters: filters
            },
            success: function (response) {
                if (response.success) {
                    $('#forvoyez-images-container').html(response.data.html);
                    initializeEventListeners();
                } else {
                    showNotification('Failed to load images', 'error');
                }
            },
            error: function () {
                showNotification('Failed to load images', 'error');
            },
            complete: function () {
                $('#forvoyez-loader').addClass('hidden');
            }
        });
    }

    function initializeEventListeners() {
        $('.forvoyez-pagination .pagination-link').on('click', function (e) {
            e.preventDefault();
            var page = $(this).data('page');
            loadImages(page);
        });

        // Initial load
        loadImages();

        // Filter form submission
        $('#forvoyez-filter-form').on('submit', function (e) {
            e.preventDefault();
            loadImages();
        });

        // Items per page change event
        $('#forvoyez-per-page').on('change', function () {
            loadImages();
        });

        // Re-initialize other event listeners for newly loaded content
        $('div').on('click', '.see-more-button', function (e) {
            e.preventDefault();
            let $item = $(this).closest('li');
            toggleImageDetails($item);
        });

        $('div').on('click', '.analyze-button', function (e) {
            e.preventDefault();
            let imageId = $(this).closest('li').data('image-id');
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
    }

    $('#forvoyez-select-all').on('change', function () {
        $('input[type="checkbox"]').prop('checked', $(this).is(':checked'));
    });

    $('#forvoyez-bulk-analyze').on('click', function () {
        let selectedImages = $('input[type="checkbox"]:checked').map(function () {
            return $(this).val();
        }).get();

        if (selectedImages.length === 0) {
            showNotification('Please select at least one image to analyze', 'info');
            return;
        }

        analyzeBulkImages(selectedImages);
    });


    function markImageAsAnalyzed(imageId, metadata) {
        let $imageItem = $(`li[data-image-id="${imageId}"]`);
        $imageItem.addClass('opacity-50');
        $imageItem.find('.analyze-button').prop('disabled', true);

        // Update metadata icons
        let $metadataIcons = $imageItem.find('.metadata-icons');
        $metadataIcons.find('.alt-missing').toggleClass('hidden', !!metadata.alt_text);
        $metadataIcons.find('.title-missing').toggleClass('hidden', !!metadata.title);
        $metadataIcons.find('.caption-missing').toggleClass('hidden', !!metadata.caption);
        $metadataIcons.find('.all-complete').toggleClass('hidden', !(metadata.alt_text && metadata.title && metadata.caption));

        // Update image details
        let $details = $imageItem.find('.details-view');
        $details.find('.title-content').text(metadata.title || 'Not set');
        $details.find('.alt-content').text(metadata.alt_text || 'Not set');
        $details.find('.caption-content').text(metadata.caption || 'Not set');

        // Hide loader
        $imageItem.find('.loader').addClass('hidden');
    }

    function showNotification(message, type = 'info', duration = 3000) {
        const notification = document.createElement('div');
        notification.className = `fixed bottom-4 right-4 p-4 rounded-lg shadow-lg transition-opacity duration-300 opacity-0 ${
            type === 'success' ? 'bg-green-500' :
                type === 'error' ? 'bg-red-500' :
                    'bg-blue-500'
        } text-white`;
        notification.textContent = message;

        document.body.appendChild(notification);

        setTimeout(() => {
            notification.classList.remove('opacity-0');
        }, 10);

        if (duration > 0) {
            setTimeout(() => {
                notification.classList.add('opacity-0');
                setTimeout(() => {
                    document.body.removeChild(notification);
                }, 300);
            }, duration);
        }
    }

    function showErrorNotification(message, code, imageId) {
        let fullMessage = `Error processing image ${imageId}: ${message}`;
        let detailedMessage = `Error code: ${code}`;

        showNotification(fullMessage, 'error', 5000);
        console.error(fullMessage, detailedMessage);
    }

    function getVisibilityIcon(type) {
        return type === 'password'
            ? '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M10 12a2 2 0 100-4 2 2 0 000 4z" /><path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" /></svg>'
            : '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3.707 2.293a1 1 0 00-1.414 1.414l14 14a1 1 0 001.414-1.414l-1.473-1.473A10.014 10.014 0 0019.542 10C18.268 5.943 14.478 3 10 3a9.958 9.958 0 00-4.512 1.074l-1.78-1.781zm4.261 4.26l1.514 1.515a2.003 2.003 0 012.45 2.45l1.514 1.514a4 4 0 00-5.478-5.478z" clip-rule="evenodd" /><path d="M12.454 16.697L9.75 13.992a4 4 0 01-3.742-3.741L2.335 6.578A9.98 9.98 0 00.458 10c1.274 4.057 5.065 7 9.542 7 .847 0 1.669-.105 2.454-.303z" /></svg>';
    }

    // Initialize event listeners for the initial page load
    initializeEventListeners();

    window.showNotification = showNotification;
})(jQuery);