/**
 * ForVoyez Media Library Integration
 * Handles image analysis directly in the WordPress Media Library
 */
;(function ($) {
	'use strict'

	// Credit management variables
	let creditsInfo = {
		credits: null,
		isSubscribed: false,
		planName: '',
		isLoading: true,
	}

	// Initialize on document ready
	$(document).ready(function () {
		// Initialize and load credits info
		refreshCredits()

		// Add event handler for "Analyze with ForVoyez" button
		$(document).on('click', '.forvoyez-analyze-button', function (e) {
			e.preventDefault()
			const imageId = $(this).data('image-id')
			const $button = $(this)
			const $statusArea = $button.siblings('.forvoyez-status')

			// Disable button and show loading state
			$button.prop('disabled', true).text('Analyzing...').addClass('opacity-50')
			$statusArea
				.html('<span class="forvoyez-spinner"></span> Analyzing image...')
				.addClass('loading')
				.removeClass('success error')

			// Analyze the image
			analyzeImage(imageId)
				.then(function (response) {
					// Re-enable button
					$button
						.prop('disabled', false)
						.text('Analyze with ForVoyez')
						.removeClass('opacity-50')

					if (response.success) {
						// Update UI directly with new metadata
						updateImageMetadataUI(imageId, response.data.metadata)

						// Show success message
						$statusArea
							.html(
								'<span class="dashicons dashicons-yes"></span> Analysis complete!'
							)
							.addClass('success')
							.removeClass('loading error')

						// Refresh credits
						refreshCredits()
					} else {
						// Show error message
						$statusArea
							.html(
								'<span class="dashicons dashicons-warning"></span> Error: ' +
									response.data.message
							)
							.addClass('error')
							.removeClass('loading success')
					}
				})
				.catch(function (error) {
					// Re-enable button
					$button
						.prop('disabled', false)
						.text('Analyze with ForVoyez')
						.removeClass('opacity-50')

					// Show error message
					$statusArea
						.html(
							'<span class="dashicons dashicons-warning"></span> Error: ' +
								error
						)
						.addClass('error')
						.removeClass('loading success')
				})
		})
	})

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
						isLoading: false,
					}
				} else {
					creditsInfo.isLoading = false
					creditsInfo.hasError = true
				}
				updateCreditsDisplay()
			},
			error: function () {
				creditsInfo.isLoading = false
				creditsInfo.hasError = true
				updateCreditsDisplay()
			},
		})
	}

	/**
	 * Update all credit display elements
	 */
	function updateCreditsDisplay() {
		// Handle loading state
		if (creditsInfo.isLoading) {
			$('.forvoyez-credit-count').html(
				'<span class="forvoyez-animate-pulse">...</span>'
			)
			return
		}

		// Handle error state
		if (creditsInfo.hasError) {
			$('.forvoyez-credit-count').html('?')
			return
		}

		// Normal state - update credit display
		$('.forvoyez-credit-count').text(creditsInfo.credits)

		// Update credit count class based on value
		$('.forvoyez-credit-count').each(function () {
			if (creditsInfo.credits > 20) {
				$(this)
					.addClass('credits-normal')
					.removeClass('credits-warning credits-danger')
			} else if (creditsInfo.credits > 5) {
				$(this)
					.addClass('credits-warning')
					.removeClass('credits-normal credits-danger')
			} else {
				$(this)
					.addClass('credits-danger')
					.removeClass('credits-normal credits-warning')
			}
		})
	}

	/**
	 * Analyze image with ForVoyez API
	 * @param {number} imageId - ID of the image to analyze
	 * @returns {Promise} - Promise that resolves with the analysis result
	 */
	function analyzeImage(imageId) {
		return new Promise(function (resolve, reject) {
			$.ajax({
				url: forvoyezData.ajaxurl,
				type: 'POST',
				data: {
					action: 'forvoyez_analyze_image',
					image_id: imageId,
					nonce: forvoyezData.verifyAjaxRequestNonce,
				},
				success: function (response) {
					resolve(response)
				},
				error: function (xhr, status, error) {
					reject(error)
				},
			})
		})
	}

	/**
	 * Update image metadata in the media library UI
	 * @param {number} imageId - ID of the image
	 * @param {object} metadata - New metadata for the image
	 */
	function updateImageMetadataUI(imageId, metadata) {
		// Update alt text field if present
		const $altTextField = $(
			'#attachment-details-alt-text, .compat-field-_wp_attachment_image_alt input'
		)
		if ($altTextField.length) {
			$altTextField.val(metadata.alt_text)
		}

		// Update title field if present
		const $titleField = $('#attachment-details-title, input[name="post_title"]')
		if ($titleField.length) {
			$titleField.val(metadata.title)
		}

		// Update caption field if present
		const $captionField = $(
			'#attachment-details-caption, textarea[name="excerpt"]'
		)
		if ($captionField.length) {
			$captionField.val(metadata.caption)
		}

		// Update status indicators if present
		const $statusIndicators = $(
			`.forvoyez-metadata-item[data-image-id="${imageId}"]`
		)
		if ($statusIndicators.length) {
			// Alt text status
			if (metadata.alt_text) {
				$statusIndicators
					.find('.alt-status')
					.html(
						'<span class="dashicons dashicons-yes-alt" style="color: #4CAF50;"></span>'
					)
					.attr('title', 'Has alt text')
			}

			// Title status
			if (metadata.title) {
				$statusIndicators
					.find('.title-status')
					.html(
						'<span class="dashicons dashicons-yes-alt" style="color: #4CAF50;"></span>'
					)
					.attr('title', 'Has title')
			}

			// Caption status
			if (metadata.caption) {
				$statusIndicators
					.find('.caption-status')
					.html(
						'<span class="dashicons dashicons-yes-alt" style="color: #4CAF50;"></span>'
					)
					.attr('title', 'Has caption')
			}
		}
	}
})(jQuery)
