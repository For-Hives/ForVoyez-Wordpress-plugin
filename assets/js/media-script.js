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
			$.ajax({
				url: forvoyezData.ajaxurl,
				type: 'POST',
				data: {
					action: 'forvoyez_analyze_media_image', // Important: Use the correct action
					image_id: imageId,
					nonce: forvoyezData.verifyAjaxRequestNonce,
				},
				success: function (response) {
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
									(response.data ? response.data.message : 'Unknown error')
							)
							.addClass('error')
							.removeClass('loading success')
					}
				},
				error: function (xhr, status, error) {
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
				},
			})
		})

		// Handle media modal - when media modal is opened
		$(document).on('click', '.media-modal .attachment', function () {
			// Make sure we update credits on attachment selection too
			setTimeout(refreshCredits, 500)
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
	 * Update image metadata in the media library UI
	 * @param {number} imageId - ID of the image
	 * @param {object} metadata - New metadata for the image
	 */
	function updateImageMetadataUI(imageId, metadata) {
		// Log for debugging
		console.log(
			'Updating UI for image ID:',
			imageId,
			'with metadata:',
			metadata
		)

		// Different selectors for different contexts
		updateAttachmentEditorFields(metadata)
		updateMediaModalFields(metadata)
		updateMediaGridFields(imageId, metadata)
		updateMediaEditFields(imageId, metadata)
		updateStatusIndicators(imageId, metadata)

		// Trigger WordPress media library refresh (without page reload)
		if (wp && wp.media && wp.media.frame) {
			try {
				// Try to refresh the media library frame if it exists
				wp.media.frame.content
					.get()
					.collection.props.set({ ignore: +new Date() })
			} catch (e) {
				console.log('Could not refresh media library:', e)
			}
		}
	}

	/**
	 * Update fields in the attachment editor (sidebar)
	 */
	function updateAttachmentEditorFields(metadata) {
		// Update alt text field
		const $altTextField = $('#attachment-details-alt-text')
		if ($altTextField.length) {
			$altTextField.val(metadata.alt_text)
			console.log('Updated attachment editor alt text field')
		}

		// Update title field
		const $titleField = $('#attachment-details-title')
		if ($titleField.length) {
			$titleField.val(metadata.title)
			console.log('Updated attachment editor title field')
		}

		// Update caption field
		const $captionField = $('#attachment-details-caption')
		if ($captionField.length) {
			$captionField.val(metadata.caption)
			console.log('Updated attachment editor caption field')
		}
	}

	/**
	 * Update fields in media modal (popup)
	 */
	function updateMediaModalFields(metadata) {
		// Media modal alt text
		const $modalAltText = $(
			'.media-sidebar .setting[data-setting="alt"] input, .compat-field-_wp_attachment_image_alt input'
		)
		if ($modalAltText.length) {
			$modalAltText.val(metadata.alt_text).trigger('change')
			console.log('Updated media modal alt text field')
		}

		// Media modal title
		const $modalTitle = $(
			'.media-sidebar .setting[data-setting="title"] input, .media-sidebar input[name="post_title"]'
		)
		if ($modalTitle.length) {
			$modalTitle.val(metadata.title).trigger('change')
			console.log('Updated media modal title field')
		}

		// Media modal caption
		const $modalCaption = $(
			'.media-sidebar .setting[data-setting="caption"] textarea, .media-sidebar textarea[name="excerpt"]'
		)
		if ($modalCaption.length) {
			$modalCaption.val(metadata.caption).trigger('change')
			console.log('Updated media modal caption field')
		}
	}

	/**
	 * Update fields in media grid
	 */
	function updateMediaGridFields(imageId, metadata) {
		// Find the specific attachment in grid view
		const $gridItem = $(`.attachment[data-id="${imageId}"]`)
		if ($gridItem.length) {
			// Update title if visible
			$gridItem.find('.filename').text(metadata.title)
			console.log('Updated grid item for image ID:', imageId)
		}
	}

	/**
	 * Update fields in media edit screen
	 */
	function updateMediaEditFields(imageId, metadata) {
		// Media edit screen (post.php?post=X&action=edit)
		if (
			window.location.href.includes('post.php') &&
			window.location.href.includes('action=edit')
		) {
			// Edit screen title
			const $editTitle = $('#title')
			if ($editTitle.length) {
				$editTitle.val(metadata.title)
				console.log('Updated edit screen title field')
			}

			// Edit screen excerpt/caption
			const $editCaption = $('#excerpt')
			if ($editCaption.length) {
				$editCaption.val(metadata.caption)
				console.log('Updated edit screen caption field')
			}
		}
	}

	/**
	 * Update status indicators
	 */
	function updateStatusIndicators(imageId, metadata) {
		// First, try to find the status container by data-image-id
		const $statusContainer = $(
			`.forvoyez-metadata-status[data-image-id="${imageId}"]`
		)

		if ($statusContainer.length) {
			// Alt text status
			if (metadata.alt_text) {
				$statusContainer
					.find('.alt-status')
					.html(
						'<span class="dashicons dashicons-yes-alt" style="color: #4CAF50;"></span>'
					)
					.attr('title', 'Has alt text')
			}

			// Title status
			if (metadata.title) {
				$statusContainer
					.find('.title-status')
					.html(
						'<span class="dashicons dashicons-yes-alt" style="color: #4CAF50;"></span>'
					)
					.attr('title', 'Has title')
			}

			// Caption status
			if (metadata.caption) {
				$statusContainer
					.find('.caption-status')
					.html(
						'<span class="dashicons dashicons-yes-alt" style="color: #4CAF50;"></span>'
					)
					.attr('title', 'Has caption')
			}

			console.log('Updated status indicators for image ID:', imageId)
		}
	}
})(jQuery)
