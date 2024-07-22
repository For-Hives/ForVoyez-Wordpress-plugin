(function ($) {
    'use strict';

    let isLoading = false;

    $(document).ready(function () {
        // Handle "Details" button click
        $('div').on('click', '.see-more-button', function (e) {
            e.preventDefault();
            let $item = $(this).closest('li');
            toggleImageDetails($item);
        });

        // Handle analyze button click
        $('ul').on('click', '.analyze-button', function (e) {
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

        // Select all functionality
        $('#forvoyez-select-all').on('change', function () {
            $('input[type="checkbox"]').prop('checked', $(this).is(':checked'));
        });

        // Bulk analyze functionality
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
    });

    function toggleImageDetails($item) {
        let $image = $item.find('img');
        let $details = $item.find('.details-view');
        console.log($details);
        let $seeMoreButtonImages = $item.find('#see-more-button-images');
        let $seeMoreButtonDetails = $item.find('#see-more-button-details');

        if ($details.hasClass('hidden')) {
            // swap hidden & (flex flex-col items-start justify-center gap-2)
            $image.addClass('hidden');
            $image.removeClass('flex flex-col items-start justify-center gap-2');
            $details.addClass('flex flex-col items-start justify-center gap-2');
            $details.removeClass('hidden');
            // swap text image, mode image // mode details
            $seeMoreButtonImages.addClass('hidden');
            $seeMoreButtonImages.removeClass('inline-flex');
            $seeMoreButtonDetails.addClass('inline-flex');
            $seeMoreButtonDetails.removeClass('hidden');
        } else {
            // swap hidden & (flex flex-col items-start justify-center gap-2)
            $image.addClass('flex flex-col items-start justify-center gap-2');
            $image.removeClass('hidden');
            $details.addClass('hidden');
            $details.removeClass('flex flex-col items-start justify-center gap-2');
            // swap text image, mode image // mode details
            $seeMoreButtonImages.addClass('inline-flex');
            $seeMoreButtonImages.removeClass('hidden');
            $seeMoreButtonDetails.addClass('hidden');
            $seeMoreButtonDetails.removeClass('inline-flex');
        }
    }

    function analyzeImage(imageId, isNotificationActivated = true) {
        let $imageItem = $(`li[data-image-id="${imageId}"]`);
        let $loader = $imageItem.find('.absolute.inset-0.flex.items-center.justify-center');

        $loader.removeClass('hidden');

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
                    $loader.addClass('hidden');
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    console.log('AJAX request failed for image ' + imageId + ': ' + textStatus);
                    if (isNotificationActivated) {
                        showErrorNotification('AJAX request failed: ' + textStatus, 'ajax_error', imageId);
                    }
                    $loader.addClass('hidden');
                    resolve(false);
                }
            });
        });
    }

    function showErrorNotification(message, code, imageId) {
        let fullMessage = `Error processing image ${imageId}: ${message}`;
        let detailedMessage = `Error code: ${code}`;

        showNotification(fullMessage, 'error', 5000);
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
                $('ul[role="list"]').html($response.find('ul[role="list"]').html());
                $('.pagination').html($response.find('.pagination').html());
                $('.forvoyez-displayed-images').html($response.find('.forvoyez-displayed-images').html());
                $('.forvoyez-filters').html($response.find('.forvoyez-filters').html());
                history.pushState(null, '', url);
            },
            error: function () {
                showNotification('Failed to load images', 'error');
            }
        });
    }

    function markImageAsAnalyzed(imageId, metadata) {
        let $imageItem = $(`li[data-image-id="${imageId}"]`);
        $imageItem.addClass('opacity-50');
        $imageItem.find('.analyze-button').remove();

        // Update missing metadata indicators
        let $metadataIcons = $imageItem.find('.absolute.top-0.right-0');
        $metadataIcons.empty();
        if (!metadata.alt_text || !metadata.title || !metadata.caption) {
            if (!metadata.alt_text) {
                $metadataIcons.append(`<span class="bg-red-500 text-white rounded-full p-1" title="Missing Alt Text">...</span>`);
            }
            if (!metadata.title) {
                $metadataIcons.append(`<span class="bg-red-500 text-white rounded-full p-1" title="Missing Title">...</span>`);
            }
            if (!metadata.caption) {
                $metadataIcons.append(`<span class="bg-red-500 text-white rounded-full p-1" title="Missing Caption">...</span>`);
            }
        }

        // Update image details
        let $details = $imageItem.find('.details-view');
        $details.html(`
            <p class="text-sm text-gray-500"><strong>Title:</strong> ${metadata.title || 'Not set'}</p>
            <p class="text-sm text-gray-500"><strong>Alt Text:</strong> ${metadata.alt_text || 'Not set'}</p>
            <p class="text-sm text-gray-500"><strong>Caption:</strong> ${metadata.caption || 'Not set'}</p>
        `);
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

        setTimeout(() => {
            notification.classList.add('opacity-0');
            setTimeout(() => {
                document.body.removeChild(notification);
            }, 300);
        }, duration);
    }

    window.showNotification = showNotification;
})(jQuery);