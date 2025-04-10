/* global forvoyezData, jQuery */

;(function ($) {
	'use strict'

	let $configApiKeyInput
	/**
	 * Credit management functions for ForVoyez admin
	 */

	// Add to existing global variables
	let creditsInfo = {
		credits: null,
		isSubscribed: false,
		planName: '',
		lowCreditsThreshold: 10,
		isLoading: true,
	}

	$(document).ready(function () {
		$configApiKeyInput = $('#forvoyez-api-key')

		// API Settings Modal
		$('.forvoyez-toggle-visibility').on('click', function () {
			toggleApiKeyVisibility($configApiKeyInput, $(this))
		})

		function toggleApiKeyVisibility($input, $button) {
			if ($input.attr('type') === 'password') {
				$input.attr('type', 'text')
				$button.html(getVisibilityIcon('text'))
			} else {
				$input.attr('type', 'password')
				$button.html(getVisibilityIcon('password'))
			}
		}

		$('.forvoyez-save-api-key').on('click', function () {
			saveApiKey($configApiKeyInput.val())
		})

		$('.forvoyez-save-additional-settings').on('click', function (e) {
			e.preventDefault()
			saveAdditionalSettings()
		})

		$('#forvoyez-select-all').on('change', function () {
			$('input[type="checkbox"][data-forvoyez-image-checkbox]').prop(
				'checked',
				$(this).is(':checked')
			)
		})

		$('#forvoyez-auto-analyze').on('change', function () {
			var $toggle = $(this)
			var isEnabled = $toggle.data('enabled') === true
			console.log('isEnabled', isEnabled)

			// Toggle the dot color
			$('#forvoyez-auto-analyze-dot').toggleClass('-left-1 -right-1')
			$('#forvoyez-auto-analyze-body').toggleClass('bg-gray-300 bg-green-400')

			// Disable the toggle while the AJAX request is in progress
			$toggle.prop('disabled', true)
			console.log('isEnabled', isEnabled)
			$.ajax({
				url: forvoyezData.ajaxurl,
				type: 'POST',
				data: {
					action: 'forvoyez_toggle_auto_analyze',
					enabled: !isEnabled,
					nonce: forvoyezData.toggleAutoAnalyzeNonce,
				},
				success: function (response) {
					if (response.success) {
						// Update the toggle state
						$toggle.data('enabled', !isEnabled)
						// Show success message
						showNotification(response.data.message, 'success')
					} else {
						// Revert the toggle state
						$toggle.prop('checked', isEnabled)
						// Show error message
						showNotification(response.data.message, 'error')
					}
				},
				error: function (jqXHR, textStatus, errorThrown) {
					console.error('AJAX error:', textStatus, errorThrown)
					// Revert the toggle state
					$toggle.prop('checked', isEnabled)
					// Show error message
					showNotification('An error occurred. Please try again.', 'error')
				},
				complete: function () {
					$toggle.prop('disabled', false) // Re-enable the toggle
				},
			})
		})

		$(document).on(
			'change',
			'input[type="checkbox"][data-forvoyez-image-checkbox]',
			function () {
				let allChecked =
					$('input[type="checkbox"][data-forvoyez-image-checkbox]').length ===
					$('input[type="checkbox"][data-forvoyez-image-checkbox]:checked')
						.length
				$('#forvoyez-select-all').prop('checked', allChecked)
			}
		)

		$('#forvoyez-bulk-analyze').on('click', function () {
			let selectedImages = $(
				'input[type="checkbox"][data-forvoyez-image-checkbox]:checked'
			)
				.map(function () {
					return $(this).val()
				})
				.get()

			if (selectedImages.length === 0) {
				showNotification('Please select at least one image to analyze', 'info')
				return
			}

			confirmAndAnalyze('selected', selectedImages)
		})

		updateImageCounts()

		$(
			'#forvoyez-analyze-missing, #forvoyez-analyze-missing-alt, #forvoyez-analyze-all'
		).on('click', function (e) {
			e.preventDefault()
			let button = $(this)
			let type = ''

			if (button.attr('id') === 'forvoyez-analyze-missing') {
				type = 'missing_all'
			} else if (button.attr('id') === 'forvoyez-analyze-missing-alt') {
				type = 'missing_alt'
			} else if (button.attr('id') === 'forvoyez-analyze-all') {
				type = 'all'
			}

			$.ajax({
				url: forvoyezData.ajaxurl,
				type: 'POST',
				data: {
					action: 'forvoyez_get_image_ids',
					nonce: forvoyezData.getImageIdsNonce,
					type: type,
				},
				success: function (response) {
					if (response.success) {
						confirmAndAnalyze(type, response.data.image_ids)
					} else {
						showNotification('Error: ' + response.data.message, 'error', 5000)
					}
				},
				error: function () {
					showNotification('Error: Failed to fetch image IDs', 'error', 5000)
				},
			})
		})

		// Initialize event listeners
		initializeEventListeners()
	})

	/**
	 * Initialize credit display elements with loading state
	 */
	function initCreditDisplay() {
		// Initialize loading placeholders
		$('.forvoyez-credit-count').each(function () {
			if ($(this).text() === '' || $(this).text() === '0') {
				$(this).html('<span class="animate-pulse">...</span>')
				$(this).addClass('bg-gray-200 text-gray-600')
			}
		})

		$('.forvoyez-credits-status').each(function () {
			if ($(this).text() === '' || $(this).html() === '') {
				$(this).html(
					'<span class="inline-flex items-center text-gray-500"><span class="animate-pulse">Loading subscription status...</span></span>'
				)
			}
		})
	}

	function initializeEventListeners() {
		// Initial load
		loadImages()

		refreshCredits()

		$('#forvoyez-filter-form')
			.find('input[type="checkbox"]')
			.on('change', function () {
				currentPage = 1
				loadImages()
			})

		if ($('body').hasClass('forvoyez-admin-page')) {
			createCreditsWidget()
		}

		// Filter form submission
		$('#forvoyez-filter-form').on('submit', function (e) {
			e.preventDefault()
			loadImages()
		})

		// Items per page change event
		$('#forvoyez-per-page').on('change', loadImages)

		// Pagination click event
		$(document).on(
			'click',
			'.forvoyez-pagination .pagination-link',
			function (e) {
				e.preventDefault()
				let page = $(this).data('page')
				loadImages(page)
			}
		)

		// See more button click event
		$(document).on('click', '.see-more-button', function (e) {
			e.preventDefault()
			let $item = $(this).closest('li')
			toggleImageDetails($item)
		})

		// Analyze button click event
		$(document).on('click', '.analyze-button', function (e) {
			e.preventDefault()
			let imageId = $(this).closest('li').data('image-id')
			$(this).prop('disabled', true)
			analyzeImage(imageId)
				.then(() => {
					$(this).prop('disabled', false)
				})
				.catch(error => {
					console.error('Error analyzing image:', error)
					$(this).prop('disabled', false)
				})
		})
	}

	let $container = $('#forvoyez-images-container')
	let $filterForm = $('#forvoyez-filter-form')
	let currentPage = 1
	let perPage = 25

	/**
	 * Create floating credits widget
	 */
	function createCreditsWidget() {
		// Only create if it doesn't already exist
		if ($('.forvoyez-credits-widget').length === 0) {
			const widget = $(`
            <div class="forvoyez-credits-widget fixed bottom-4 right-4 bg-white p-3 pb-2 rounded-lg shadow-lg border border-gray-200 z-30">
                <div class="flex items-center space-x-2">
                    <div class="text-gray-700 font-medium">ForVoyez Credits:</div>
                    <span class="forvoyez-credit-count px-2 py-1 inline-flex text-xs leading-4 font-semibold rounded-full bg-gray-200 text-gray-600"><span class="animate-pulse">...</span></span>
                </div>
                <div class="mt-1 text-xs text-gray-500 forvoyez-credits-status">
                    <span class="animate-pulse">Loading subscription status...</span>
                </div>
                <div class="mt-1 text-xs text-red-600 forvoyez-credit-warning hidden">
                    Low credits! Please <a href="https://forvoyez.com/app/plans" target="_blank" class="underline hover:text-red-800">recharge now</a>.
                </div>
            </div>
        `)

			$('body').append(widget)
		}
	}

	function loadImages(page = currentPage) {
		let data = {
			action: 'forvoyez_load_images',
			nonce: forvoyezData.loadImagesNonce,
			paged: page,
			per_page: perPage,
			filters: $filterForm.serializeArray(),
		}

		// If perPage is -1 (All), set it to a very large number
		if (perPage === '-1') {
			data.per_page = 999999 // Use a very large number instead of -1
		}

		$.post(forvoyezData.ajaxurl, data, function (response) {
			if (response.success) {
				$container.html(response.data.html)
				$('#forvoyez-pagination').html(response.data.pagination_html)
				$('#forvoyez-image-counter').text(
					'Displaying ' +
						response.data.displayed_images +
						' of ' +
						response.data.total_images +
						' images'
				)
				currentPage = response.data.current_page
			}
		})
	}

	$filterForm.on('submit', function (e) {
		e.preventDefault()
		currentPage = 1
		loadImages(currentPage)
	})

	$filterForm.find('input[type="checkbox"]').on('change', function () {
		currentPage = 1
		loadImages(currentPage)
	})

	$(document).on('click', '.pagination-link', function (e) {
		e.preventDefault()
		let page = $(this).data('page')
		loadImages(page)
	})

	$('#forvoyez-per-page').on('change', function () {
		perPage = $(this).val()
		currentPage = 1
		loadImages(currentPage)
	})

	// Initial load
	loadImages(currentPage)

	function saveApiKey(apiKey) {
		$.ajax({
			url: forvoyezData.ajaxurl,
			type: 'POST',
			data: {
				action: 'forvoyez_save_api_key',
				api_key: apiKey,
				nonce: forvoyezData.saveApiKeyNonce,
			},
			success: function (response) {
				if (response.success) {
					showNotification('API key saved successfully', 'success')
					$configApiKeyInput.val(apiKey)
				} else {
					showNotification('Failed to save API key: ' + response.data, 'error')
				}
			},
			error: function () {
				showNotification('Failed to save API key', 'error')
			},
		})
	}

	function saveAdditionalSettings() {
		const context = $('#forvoyez-context').val()
		const language = $('#forvoyez-language').val()

		$.ajax({
			url: forvoyezData.ajaxurl,
			type: 'POST',
			data: {
				action: 'forvoyez_save_context',
				nonce: forvoyezData.saveContextNonce,
				context: context,
			},
			success: function (response) {
				if (response.success) {
					showNotification('Context saved successfully', 'success')
				} else {
					showNotification('Failed to save context: ' + response.data, 'error')
				}
			},
			error: function () {
				showNotification('Failed to save context', 'error')
			},
		})

		$.ajax({
			url: forvoyezData.ajaxurl,
			type: 'POST',
			data: {
				action: 'forvoyez_save_language',
				nonce: forvoyezData.saveLanguageNonce,
				language: language,
			},
			success: function (response) {
				if (response.success) {
					showNotification('Language saved successfully', 'success')
				} else {
					showNotification('Failed to save language: ' + response.data, 'error')
				}
			},
			error: function () {
				showNotification('Failed to save language', 'error')
			},
		})
	}

	function toggleImageDetails($item) {
		let $image = $item.find('img')
		let $details = $item.find('.details-view')
		let $seeMoreButtonImages = $item.find('#see-more-button-images')
		let $seeMoreButtonDetails = $item.find('#see-more-button-details')

		if ($details.hasClass('hidden')) {
			$image
				.addClass('hidden')
				.removeClass('flex flex-col items-start justify-start gap-2')
			$details
				.addClass('flex flex-col items-start justify-start gap-2')
				.removeClass('hidden')
			$seeMoreButtonImages.addClass('hidden').removeClass('inline-flex')
			$seeMoreButtonDetails.addClass('inline-flex').removeClass('hidden')
		} else {
			$image
				.addClass('flex flex-col items-start justify-start gap-2')
				.removeClass('hidden')
			$details
				.addClass('hidden')
				.removeClass('flex flex-col items-start justify-start gap-2')
			$seeMoreButtonImages.addClass('inline-flex').removeClass('hidden')
			$seeMoreButtonDetails.addClass('hidden').removeClass('inline-flex')
		}
	}

	function analyzeImage(imageId, isNotificationActivated = true) {
		let $imageItem = $(`li[data-image-id="${imageId}"]`)
		let $loader = $imageItem.find('.loader')

		$loader.removeClass('hidden')
		showLoader()

		return new Promise(resolve => {
			$.ajax({
				url: forvoyezData.ajaxurl,
				type: 'POST',
				data: {
					action: 'forvoyez_analyze_image',
					image_id: imageId,
					nonce: forvoyezData.verifyAjaxRequestNonce,
				},
				success: function (response) {
					if (response.success) {
						if (isNotificationActivated) {
							showNotification(
								'Metadata updated successfully for image ' + imageId,
								'success'
							)
						}
						markImageAsAnalyzed(imageId, response.data.metadata)
						// Add these lines to update credits
						refreshCredits()
						$(document).trigger('forvoyez:image-analyzed')
						resolve(true)
					} else {
						let errorMessage = response.data
							? response.data.message
							: 'Unknown error occurred'
						let errorCode = response.data
							? response.data.code || 'unknown_error'
							: 'unknown_error'
						if (isNotificationActivated) {
							showErrorNotification(errorMessage, errorCode, imageId)
						}
						resolve(false)
					}
				},
				error: function (jqXHR, textStatus) {
					if (isNotificationActivated) {
						showErrorNotification(
							'AJAX request failed: ' + textStatus,
							'ajax_error',
							imageId
						)
					}
					resolve(false)
				},
				complete: function () {
					$loader.addClass('hidden')
					refreshCredits()
					hideLoader()
				},
			})
		})
	}

	function analyzeBulkImages(imageIds) {
		const totalImages = imageIds.length
		let processedCount = 0
		let failedCount = 0

		showLoader()
		showNotification(`Starting analysis of ${totalImages} images...`, 'info', 0)

		function updateProgress() {
			const progress = Math.round(
				((processedCount + failedCount) / totalImages) * 100
			)
			showNotification(
				`Processing: ${progress}% complete. Successful: ${processedCount}, Failed: ${failedCount}`,
				'info',
				0
			)

			refreshCredits()

			// Update progress bar
			$('#forvoyez-progress-container').removeClass('hidden')
			$('#forvoyez-progress-bar').css('width', progress + '%')
			$('#forvoyez-progress-bar-count')
				.removeClass('hidden')
				.text(progress + '%')
		}

		function processBatch(batch) {
			return new Promise(resolve => {
				$.ajax({
					url: forvoyezData.ajaxurl,
					type: 'POST',
					data: {
						action: 'forvoyez_process_image_batch',
						image_ids: batch,
						nonce: forvoyezData.verifyAjaxRequestNonce,
					},
					success: function (response) {
						if (response.success) {
							response.data.results.forEach(result => {
								if (result.success) {
									processedCount++
									markImageAsAnalyzed(result.id, result.metadata)
								} else {
									failedCount++
									showErrorNotification(result.message, result.code, result.id)
								}
							})
						} else {
							batch.forEach(imageId => {
								failedCount++
								showErrorNotification(
									'Batch processing failed',
									'batch_error',
									imageId
								)
							})
						}
						updateProgress()
						refreshCredits()
						resolve()
					},
					error: function () {
						batch.forEach(imageId => {
							failedCount++
							showErrorNotification(
								'AJAX request failed',
								'ajax_error',
								imageId
							)
						})
						updateProgress()
						refreshCredits()
						resolve()
					},
				})
			})
		}

		const batchSize = 7

		async function processAllImages() {
			for (let i = 0; i < totalImages; i += batchSize) {
				const batch = imageIds.slice(i, Math.min(i + batchSize, totalImages))
				await processBatch(batch)
			}
			showNotification(
				`Analysis complete. Successful: ${processedCount}, Failed: ${failedCount}`,
				'success',
				5000
			)
			$('#forvoyez-progress-container').addClass('hidden')
			$('#forvoyez-progress-bar-count').addClass('hidden')
			updateImageCounts()
			refreshCredits()
			$(document).trigger('forvoyez:batch-completed')
			hideLoader()
		}

		processAllImages()
	}

	function showLoader() {
		$('#forvoyez-loader').removeClass('hidden')
	}

	function hideLoader() {
		$('#forvoyez-loader').addClass('hidden')
	}

	function markImageAsAnalyzed(imageId, metadata) {
		let $imageItem = $(`li[data-image-id="${imageId}"]`)
		$imageItem.addClass('opacity-50')
		$imageItem.find('.analyze-button').prop('disabled', true)

		// Update metadata icons
		let $metadataIcons = $imageItem.find('.metadata-icons')
		$metadataIcons
			.find('.alt-missing')
			.toggleClass('hidden', !!metadata.alt_text)
		$metadataIcons
			.find('.title-missing')
			.toggleClass('hidden', !!metadata.title)
		$metadataIcons
			.find('.caption-missing')
			.toggleClass('hidden', !!metadata.caption)
		$metadataIcons
			.find('.all-complete')
			.toggleClass(
				'hidden',
				!(metadata.alt_text && metadata.title && metadata.caption)
			)

		// Update image details
		let $details = $imageItem.find('.details-view')
		$details.find('.title-content').text(metadata.title || 'Not set')
		$details.find('.alt-content').text(metadata.alt_text || 'Not set')
		$details.find('.caption-content').text(metadata.caption || 'Not set')

		// Hide loader
		$imageItem.find('.loader').addClass('hidden')
	}

	let currentNotification = null

	function showNotification(message, type = 'info', duration = 3000) {
		// Remove the current notification if it exists
		if (currentNotification) {
			currentNotification.remove()
		}

		const notification = document.createElement('div')
		notification.className = `fixed bottom-4 right-4 p-4 rounded-lg shadow-lg transition-opacity z-40 duration-300 opacity-0 ${
			type === 'success'
				? 'bg-green-500'
				: type === 'error'
					? 'bg-red-500'
					: 'bg-blue-500'
		} text-white`
		notification.textContent = message

		document.body.appendChild(notification)
		currentNotification = notification

		// Fade in
		setTimeout(() => {
			notification.classList.remove('opacity-0')
		}, 10)

		if (duration > 0) {
			setTimeout(() => {
				fadeOutNotification(notification)
			}, duration)
		}
	}

	function fadeOutNotification(notification) {
		notification.classList.add('opacity-0')
		setTimeout(() => {
			if (document.body.contains(notification)) {
				document.body.removeChild(notification)
			}
			if (currentNotification === notification) {
				currentNotification = null
			}
		}, 300)
	}

	function showErrorNotification(message, code, imageId) {
		let fullMessage = `Error processing image ${imageId}: ${message}`
		let detailedMessage = `Error code: ${code}`

		showNotification(fullMessage, 'error', 5000)
		console.error(fullMessage, detailedMessage)
	}

	function getVisibilityIcon(type) {
		return type === 'password'
			? '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M10 12a2 2 0 100-4 2 2 0 000 4z" /><path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" /></svg>'
			: '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3.707 2.293a1 1 0 00-1.414 1.414l14 14a1 1 0 001.414-1.414l-1.473-1.473A10.014 10.014 0 0019.542 10C18.268 5.943 14.478 3 10 3a9.958 9.958 0 00-4.512 1.074l-1.78-1.781zm4.261 4.26l1.514 1.515a2.003 2.003 0 012.45 2.45l1.514 1.514a4 4 0 00-5.478-5.478z" clip-rule="evenodd" /><path d="M12.454 16.697L9.75 13.992a4 4 0 01-3.742-3.741L2.335 6.578A9.98 9.98 0 00.458 10c1.274 4.057 5.065 7 9.542 7 .847 0 1.669-.105 2.454-.303z" /></svg>'
	}

	function updateButtonState(buttonId, count) {
		const $button = $(`#${buttonId}`)
		const isDisabled = count === 0
		$button.prop('disabled', isDisabled)
		$button.toggleClass(
			'bg-gray-700 hover:bg-gray-900 cursor-not-allowed',
			isDisabled
		)
		$button.toggleClass(
			'bg-blue-500 hover:bg-blue-700 cursor-pointer',
			!isDisabled
		)
	}

	function updateImageCounts() {
		const types = ['all', 'missing_alt', 'missing_all']
		const buttonIds = [
			'forvoyez-analyze-all',
			'forvoyez-analyze-missing-alt',
			'forvoyez-analyze-missing',
		]
		const countIds = [
			'forvoyez-all-count',
			'forvoyez-missing-alt-count',
			'forvoyez-missing-count',
		]

		types.forEach((type, index) => {
			$.ajax({
				url: forvoyezData.ajaxurl,
				type: 'POST',
				data: {
					action: 'forvoyez_get_image_ids',
					nonce: forvoyezData.getImageIdsNonce,
					type: type,
				},
				success: function (response) {
					if (response.success) {
						const count = response.data.count
						$(`#${countIds[index]}`).text(count)
						updateButtonState(buttonIds[index], count)
					}
				},
				error: function (jqXHR, textStatus, errorThrown) {
					console.error(
						`Error fetching ${type} image count:`,
						textStatus,
						errorThrown
					)
					showNotification(`Failed to fetch ${type} image count`, 'error')
				},
			})
		})
	}

	function showConfirmModal(message, onConfirm) {
		$('#forvoyez-confirm-message').html(message)
		$('#forvoyez-confirm-modal').removeClass('hidden')

		$('#forvoyez-confirm-action')
			.off('click')
			.on('click', function () {
				$('#forvoyez-confirm-modal').addClass('hidden')
				onConfirm()
			})

		$('#forvoyez-cancel-action')
			.off('click')
			.on('click', function () {
				$('#forvoyez-confirm-modal').addClass('hidden')
			})
	}

	/**
	 * Refresh credits from server
	 */
	function refreshCredits() {
		// Set loading state
		creditsInfo.isLoading = true
		updateCreditsDisplay()

		$.ajax({
			url: forvoyezData.ajaxurl,
			type: 'POST',
			data: {
				action: 'forvoyez_get_credits',
				nonce: forvoyezData.verifyAjaxRequestNonce,
			},
			success: function (response) {
				if (response.success) {
					creditsInfo = {
						credits: response.data.credits,
						isSubscribed: response.data.is_subscribed,
						planName: response.data.plan_name || '',
						status: response.data.status || '',
						hasLowCredits: response.data.has_low_credits || false,
						isLoading: false,
					}
				} else {
					creditsInfo.isLoading = false
					creditsInfo.hasError = true
					creditsInfo.errorMessage =
						response.data.message || 'Failed to load credits'
					console.error(
						'Failed to get credits information:',
						response.data.message
					)
				}
				updateCreditsDisplay()
			},
			error: function (xhr, status, error) {
				creditsInfo.isLoading = false
				creditsInfo.hasError = true
				creditsInfo.errorMessage = 'Server error: ' + status
				console.error('AJAX error:', error)
				updateCreditsDisplay()
			},
		})
	}

	/**
	 * Update all credit display elements based on current state
	 */
	function updateCreditsDisplay() {
		// Handle loading state
		if (creditsInfo.isLoading) {
			$('.forvoyez-credit-count').each(function () {
				$(this).html('<span class="animate-pulse">...</span>')
				$(this)
					.removeClass(
						'bg-green-100 bg-yellow-100 bg-red-100 text-green-800 text-yellow-800 text-red-800'
					)
					.addClass('bg-gray-200 text-gray-600')
			})

			$('.forvoyez-credits-status').each(function () {
				$(this).html(
					'<span class="inline-flex items-center text-gray-500"><span class="animate-pulse">Loading subscription status...</span></span>'
				)
			})

			$('.forvoyez-credit-warning').addClass('hidden')
			return
		}

		// Handle error state
		if (creditsInfo.hasError) {
			$('.forvoyez-credit-count').each(function () {
				$(this).text('?')
				$(this)
					.removeClass(
						'bg-green-100 bg-yellow-100 bg-gray-200 text-green-800 text-yellow-800 text-gray-600'
					)
					.addClass('bg-red-100 text-red-800')
			})

			$('.forvoyez-credits-status').each(function () {
				$(this).html(
					'<span class="inline-flex items-center text-red-500">Error loading data</span>'
				)
			})

			$('.forvoyez-credit-warning')
				.removeClass('hidden')
				.html(
					'Unable to load credit information. Please refresh the page or check your API key configuration.'
				)
			return
		}

		// Normal state - update credit count
		$('.forvoyez-credit-count').text(creditsInfo.credits)

		// Update styling based on credit level
		if (creditsInfo.credits > 20) {
			$('.forvoyez-credit-count')
				.removeClass(
					'bg-yellow-100 bg-red-100 bg-gray-200 text-yellow-800 text-red-800 text-gray-600'
				)
				.addClass('bg-green-100 text-green-800')
		} else if (creditsInfo.credits > 5) {
			$('.forvoyez-credit-count')
				.removeClass(
					'bg-green-100 bg-red-100 bg-gray-200 text-green-800 text-red-800 text-gray-600'
				)
				.addClass('bg-yellow-100 text-yellow-800')
		} else {
			$('.forvoyez-credit-count')
				.removeClass(
					'bg-green-100 bg-yellow-100 bg-gray-200 text-green-800 text-yellow-800 text-gray-600'
				)
				.addClass('bg-red-100 text-red-800')
		}

		// Update subscription status
		if ($('.forvoyez-credits-status').length) {
			let statusHtml = ''
			if (creditsInfo.isSubscribed) {
				statusHtml =
					'<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Active</span>'
				if (creditsInfo.planName) {
					statusHtml += ' - ' + creditsInfo.planName
				}
			}
			$('.forvoyez-credits-status').html(statusHtml)
		}

		// Show/hide low credits warning
		if (creditsInfo.credits < 10) {
			$('.forvoyez-credit-warning')
				.removeClass('hidden')
				.html(
					'Low credits! Please <a href="https://forvoyez.com/app/plans" target="_blank" class="underline hover:text-red-800">recharge now</a>.'
				)
		} else {
			$('.forvoyez-credit-warning').addClass('hidden')
		}
	}

	function confirmAndAnalyze(type, imageIds) {
		const count = imageIds.length
		let message, actionDescription

		// Définir le type d'action
		switch (type) {
			case 'selected':
				actionDescription = `analyze ${count} selected image(s)`
				break
			case 'missing_all':
				actionDescription = `analyze ${count} image(s) with missing alt text, title, or caption`
				break
			case 'missing_alt':
				actionDescription = `analyze ${count} image(s) with missing alt text`
				break
			case 'all':
				actionDescription = `analyze all ${count} image(s)`
				break
		}

		if (creditsInfo.credits !== undefined) {
			let creditsWarning = ''
			if (count > creditsInfo.credits) {
				creditsWarning = `<p class="mt-2 text-xs text-red-500 font-bold">Warning: You only have ${creditsInfo.credits} credits, but this operation requires ${count} credits.</p>`
			}

			message = `
            <p>Are you sure you want to ${actionDescription}?</p>
            <p class="mt-2 text-xs text-gray-500 italic">Cost: ${count} ForVoyez credit${count !== 1 ? 's' : ''}</p>
            <p class="text-xs text-gray-500 italic">You currently have ${creditsInfo.credits} credit${creditsInfo.credits !== 1 ? 's' : ''} remaining.</p>
            ${creditsWarning}
        `

			showConfirmModal(message, function () {
				analyzeBulkImages(imageIds)
			})
			// Also refresh credits in case they're stale
			refreshCredits()
			return
		}

		// Get current token info
		$.ajax({
			url: forvoyezData.ajaxurl,
			type: 'POST',
			data: {
				action: 'forvoyez_get_credits',
				nonce: forvoyezData.verifyAjaxRequestNonce,
			},
			success: function (response) {
				if (response.success) {
					const credits = response.data.credits

					switch (type) {
						case 'selected':
							actionDescription = `analyze ${count} selected image(s)`
							break
						case 'missing_all':
							actionDescription = `analyze ${count} image(s) with missing alt text, title, or caption`
							break
						case 'missing_alt':
							actionDescription = `analyze ${count} image(s) with missing alt text`
							break
						case 'all':
							actionDescription = `analyze all ${count} image(s)`
							break
					}

					let creditsWarning = ''
					if (count > credits) {
						creditsWarning = `<p class="mt-2 text-xs text-red-500 font-bold">Warning: You only have ${credits} credits, but this operation requires ${count} credits.</p>`
					}

					message = `
					<p>Are you sure you want to ${actionDescription}?</p>
					<p class="mt-2 text-xs text-gray-500 italic">Cost: ${count} ForVoyez credit${count !== 1 ? 's' : ''}</p>
					<p class="text-xs text-gray-500 italic">You currently have ${credits} credit${credits !== 1 ? 's' : ''} remaining.</p>
					${creditsWarning}
					`

					showConfirmModal(message, function () {
						analyzeBulkImages(imageIds)
					})
				} else {
					// Fallback to original confirmation if we can't get credits
					let actionDescription
					switch (type) {
						case 'selected':
							actionDescription = `analyze ${count} selected image(s)`
							break
						case 'missing_all':
							actionDescription = `analyze ${count} image(s) with missing alt text, title, or caption`
							break
						case 'missing_alt':
							actionDescription = `analyze ${count} image(s) with missing alt text`
							break
						case 'all':
							actionDescription = `analyze all ${count} image(s)`
							break
					}

					message = `
					<p>Are you sure you want to ${actionDescription}?</p>
					<p class="mt-2 text-xs text-gray-500 italic">Cost: ${count} ForVoyez credit${count !== 1 ? 's' : ''}</p>
					`

					showConfirmModal(message, function () {
						analyzeBulkImages(imageIds)
					})
				}
			},
			error: function () {
				// Fallback to original confirmation if error
				let actionDescription
				switch (type) {
					case 'selected':
						actionDescription = `analyze ${count} selected image(s)`
						break
					case 'missing_all':
						actionDescription = `analyze ${count} image(s) with missing alt text, title, or caption`
						break
					case 'missing_alt':
						actionDescription = `analyze ${count} image(s) with missing alt text`
						break
					case 'all':
						actionDescription = `analyze all ${count} image(s)`
						break
				}

				message = `
				<p>Are you sure you want to ${actionDescription}?</p>
				<p class="mt-2 text-xs text-gray-500 italic">Cost: ${count} ForVoyez credit${count !== 1 ? 's' : ''}</p>
				`

				showConfirmModal(message, function () {
					analyzeBulkImages(imageIds)
				})
			},
		})
	}

	window.showNotification = showNotification
})(jQuery)
