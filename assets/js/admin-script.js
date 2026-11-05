;(function ($) {
	'use strict'

	// assets/js/i18n.js: strings are listed in forvoyez_get_js_translations().
	const { format, n, t } = window.forvoyezI18n

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
					showNotification(t('An error occurred. Please try again.'), 'error')
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
				showNotification(
					t('Please select at least one image to analyze'),
					'info'
				)
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
						showNotification(
							format(t('Error: %s'), response.data.message),
							'error',
							5000
						)
					}
				},
				error: function () {
					showNotification(t('Error: Failed to fetch image IDs'), 'error', 5000)
				},
			})
		})

		// Initialize event listeners
		initializeEventListeners()
	})

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
                    <div class="forvoyez-credits-label text-gray-700 font-medium"></div>
                    <span class="forvoyez-credit-count px-2 py-1 inline-flex text-xs leading-4 font-semibold rounded-full bg-gray-200 text-gray-600"><span class="animate-pulse">...</span></span>
                </div>
                <div class="mt-1 text-xs text-gray-500 forvoyez-credits-status">
                    <span class="forvoyez-credits-loading animate-pulse"></span>
                </div>
                <div class="mt-1 text-xs text-red-600 forvoyez-credit-warning hidden"></div>
            </div>
        `)
			widget.find('.forvoyez-credits-label').text(t('ForVoyez Credits:'))
			widget
				.find('.forvoyez-credits-loading')
				.text(t('Loading subscription status...'))

			$('body').append(widget)
		}
	}

	/**
	 * Low credits message of the credits widget.
	 *
	 * The recharge URL comes from PHP (forvoyezData.urls, built with
	 * forvoyez_get_tracked_url). No referrer is sent, so the click does not
	 * tell ForVoyez which site it comes from.
	 */
	function buildLowCreditsWarning() {
		const urls = forvoyezData.urls || {}
		const $link = $('<a>', {
			href: urls.recharge,
			target: '_blank',
			rel: 'noopener noreferrer',
			class: 'underline hover:text-red-800',
			text: t('recharge now'),
		})

		// The link takes the place of %s; the text around it stays text.
		const parts = t('Low credits! Please %s.').split('%s')
		const $warning = $('<span>').append(document.createTextNode(parts[0]))
		if (parts.length > 1) {
			$warning.append($link, document.createTextNode(parts.slice(1).join('')))
		}
		return $warning
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
					format(
						n(
							'Displaying %1$d of %2$d image',
							'Displaying %1$d of %2$d images',
							Number(response.data.total_images)
						),
						response.data.displayed_images,
						response.data.total_images
					)
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
					showNotification(t('API key saved successfully'), 'success')
					$configApiKeyInput.val(apiKey)
				} else {
					showNotification(
						format(t('Failed to save API key: %s'), response.data),
						'error'
					)
				}
			},
			error: function () {
				showNotification(t('Failed to save API key'), 'error')
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
					showNotification(t('Context saved successfully'), 'success')
				} else {
					showNotification(
						format(t('Failed to save context: %s'), response.data),
						'error'
					)
				}
			},
			error: function () {
				showNotification(t('Failed to save context'), 'error')
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
					showNotification(t('Language saved successfully'), 'success')
				} else {
					showNotification(
						format(t('Failed to save language: %s'), response.data),
						'error'
					)
				}
			},
			error: function () {
				showNotification(t('Failed to save language'), 'error')
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
								format(
									t('Metadata updated successfully for image %d'),
									imageId
								),
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
							: t('Unknown error occurred')
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
							format(t('AJAX request failed: %s'), textStatus),
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
		showNotification(
			format(
				n(
					'Starting analysis of %d image...',
					'Starting analysis of %d images...',
					totalImages
				),
				totalImages
			),
			'info',
			0
		)

		function updateProgress() {
			const progress = Math.round(
				((processedCount + failedCount) / totalImages) * 100
			)
			showNotification(
				format(
					t('Processing: %1$d%% complete. Successful: %2$d, Failed: %3$d'),
					progress,
					processedCount,
					failedCount
				),
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
							// Use the server error (e.g. missing_api_key) when there is one
							const data = response.data
							const message =
								(data && data.message) ||
								(typeof data === 'string' && data) ||
								t('Batch processing failed')
							const code = (data && data.code) || 'batch_error'
							batch.forEach(imageId => {
								failedCount++
								showErrorNotification(message, code, imageId)
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
								t('AJAX request failed'),
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
				format(
					t('Analysis complete. Successful: %1$d, Failed: %2$d'),
					processedCount,
					failedCount
				),
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
		$details.find('.title-content').text(metadata.title || t('Not set'))
		$details.find('.alt-content').text(metadata.alt_text || t('Not set'))
		$details.find('.caption-content').text(metadata.caption || t('Not set'))

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
		// server messages come HTML-escaped (esc_html__): decode the entities so
		// apostrophes show as text, then set them as text (never as HTML)
		const decoder = document.createElement('textarea')
		decoder.innerHTML = String(message ?? '')
		notification.textContent = decoder.value

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
		let fullMessage = format(
			t('Error processing image %1$d: %2$s'),
			imageId,
			message
		)
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
					showNotification(t('Failed to fetch the image count'), 'error')
				},
			})
		})
	}

	/**
	 * @param {jQuery[]} content Paragraphs of the message, built as text.
	 * @param {Function} onConfirm Called when the user confirms.
	 */
	function showConfirmModal(content, onConfirm) {
		$('#forvoyez-confirm-message').empty().append(content)
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
				$(this)
					.empty()
					.append(
						$('<span>', {
							class: 'inline-flex items-center text-gray-500',
						}).append(
							$('<span>', {
								class: 'animate-pulse',
								text: t('Loading subscription status...'),
							})
						)
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
				$(this)
					.empty()
					.append(
						$('<span>', {
							class: 'inline-flex items-center text-red-500',
							text: t('Error loading data'),
						})
					)
			})

			$('.forvoyez-credit-warning')
				.removeClass('hidden')
				.text(
					t(
						'Unable to load credit information. Please refresh the page or check your API key configuration.'
					)
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
		$('.forvoyez-credits-status').each(function () {
			const $status = $(this).empty()
			if (creditsInfo.isSubscribed) {
				$status.append(
					$('<span>', {
						class:
							'px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800',
						text: t('Active'),
					})
				)
				// The plan name comes from the API: text, never HTML
				if (creditsInfo.planName) {
					$status.append(document.createTextNode(' - ' + creditsInfo.planName))
				}
			}
		})

		// Show/hide low credits warning
		if (creditsInfo.credits < 10) {
			$('.forvoyez-credit-warning')
				.removeClass('hidden')
				.empty()
				.append(buildLowCreditsWarning())
		} else {
			$('.forvoyez-credit-warning').addClass('hidden')
		}
	}

	/**
	 * Question of the confirmation modal for an analysis of count images.
	 */
	function confirmQuestion(type, count) {
		switch (type) {
			case 'missing_all':
				return n(
					'Are you sure you want to analyze %d image with missing alt text, title, or caption?',
					'Are you sure you want to analyze %d images with missing alt text, title, or caption?',
					count
				)
			case 'missing_alt':
				return n(
					'Are you sure you want to analyze %d image with missing alt text?',
					'Are you sure you want to analyze %d images with missing alt text?',
					count
				)
			case 'all':
				return n(
					'Are you sure you want to analyze %d image?',
					'Are you sure you want to analyze all %d images?',
					count
				)
			default:
				return n(
					'Are you sure you want to analyze %d selected image?',
					'Are you sure you want to analyze %d selected images?',
					count
				)
		}
	}

	function confirmAndAnalyze(type, imageIds) {
		const count = imageIds.length
		const credits = Number(creditsInfo.credits)
		// Unknown before the first credits answer, or when the last one failed.
		// A refresh in progress keeps the last known count.
		const knowsCredits =
			!creditsInfo.hasError &&
			creditsInfo.credits !== null &&
			creditsInfo.credits !== '' &&
			Number.isFinite(credits)

		const content = [
			$('<p>').text(format(confirmQuestion(type, count), count)),
			$('<p>', { class: 'mt-2 text-xs text-gray-500 italic' }).text(
				format(
					n('Cost: %d ForVoyez credit', 'Cost: %d ForVoyez credits', count),
					count
				)
			),
		]

		if (knowsCredits) {
			content.push(
				$('<p>', { class: 'text-xs text-gray-500 italic' }).text(
					format(
						n(
							'You currently have %d credit remaining.',
							'You currently have %d credits remaining.',
							credits
						),
						credits
					)
				)
			)
			if (count > credits) {
				content.push(
					$('<p>', { class: 'mt-2 text-xs text-red-500 font-bold' }).text(
						format(
							t(
								'Warning: You only have %1$d credits, but this operation requires %2$d credits.'
							),
							credits,
							count
						)
					)
				)
			}
		}

		showConfirmModal(content, function () {
			analyzeBulkImages(imageIds)
		})
		// Also refresh credits in case they're stale
		refreshCredits()
	}

	window.showNotification = showNotification
})(jQuery)
