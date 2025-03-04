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
					action: 'forvoyez_analyze_media_image', // Use the correct action
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
								'<span class="dashicons dashicons-yes"></span> Analysis complete! Metadata has been updated.'
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

		// Force update specific fields in all contexts
		forceUpdateSpecificFields(metadata)

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
	 * Force update specific fields in all contexts
	 */
	function forceUpdateSpecificFields(metadata) {
		// Force update Alternative Text fields
		const altTextFields = [
			'#attachment-details-alt-text',
			'#attachment-details-two-column-alt-text',
			'.setting[data-setting="alt"] textarea',
			'.setting[data-setting="alt"] input',
			'.compat-field-_wp_attachment_image_alt input',
		]

		altTextFields.forEach(selector => {
			const $field = $(selector)
			if ($field.length) {
				$field.val(metadata.alt_text)
				$field.trigger('change').trigger('input')
				console.log('Forced update of alt text field:', selector)
			}
		})

		// Force update Title fields
		const titleFields = [
			'#attachment-details-title',
			'#attachment-details-two-column-title',
			'.setting[data-setting="title"] input',
			'input[name="post_title"]',
		]

		titleFields.forEach(selector => {
			const $field = $(selector)
			if ($field.length) {
				$field.val(metadata.title)
				$field.trigger('change').trigger('input')
				console.log('Forced update of title field:', selector)
			}
		})

		// Force update Caption fields
		const captionFields = [
			'#attachment-details-caption',
			'#attachment-details-two-column-caption',
			'.setting[data-setting="caption"] textarea',
			'textarea[name="excerpt"]',
		]

		captionFields.forEach(selector => {
			const $field = $(selector)
			if ($field.length) {
				$field.val(metadata.caption)
				$field.trigger('change').trigger('input')
				console.log('Forced update of caption field:', selector)
			}
		})

		// If we're in the media modal, also manually update the model data
		if (wp && wp.media && wp.media.frame) {
			try {
				const selection = wp.media.frame.state().get('selection')
				if (selection && selection.length) {
					const attachment = selection.first()
					if (attachment) {
						// Update the model data directly
						attachment.set('alt', metadata.alt_text)
						attachment.set('title', metadata.title)
						attachment.set('caption', metadata.caption)
						console.log('Updated attachment model data')
					}
				}
			} catch (e) {
				console.log('Could not update attachment model:', e)
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
			$altTextField.trigger('change')
			console.log('Updated attachment editor alt text field')
		}

		// Update title field
		const $titleField = $('#attachment-details-title')
		if ($titleField.length) {
			$titleField.val(metadata.title)
			$titleField.trigger('change')
			console.log('Updated attachment editor title field')
		}

		// Update caption field
		const $captionField = $('#attachment-details-caption')
		if ($captionField.length) {
			$captionField.val(metadata.caption)
			$captionField.trigger('change')
			console.log('Updated attachment editor caption field')
		}
	}

	/**
	 * Update fields in media modal (popup)
	 */
	function updateMediaModalFields(metadata) {
		// Media modal alt text
		const $modalAltText = $(
			'.media-sidebar .setting[data-setting="alt"] input, .compat-field-_wp_attachment_image_alt input, .setting[data-setting="alt"] textarea'
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
				$editTitle.trigger('change')
				console.log('Updated edit screen title field')
			}

			// Edit screen excerpt/caption
			const $editCaption = $('#excerpt')
			if ($editCaption.length) {
				$editCaption.val(metadata.caption)
				$editCaption.trigger('change')
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
						'<span class="dashicons dashicons-yes-alt" style="color: #4CAF50;"></span> <span class="status-label">Alt text</span>'
					)
					.attr('title', 'Has alt text')
			}

			// Title status
			if (metadata.title) {
				$statusContainer
					.find('.title-status')
					.html(
						'<span class="dashicons dashicons-yes-alt" style="color: #4CAF50;"></span> <span class="status-label">Title</span>'
					)
					.attr('title', 'Has title')
			}

			// Caption status
			if (metadata.caption) {
				$statusContainer
					.find('.caption-status')
					.html(
						'<span class="dashicons dashicons-yes-alt" style="color: #4CAF50;"></span> <span class="status-label">Caption</span>'
					)
					.attr('title', 'Has caption')
			}

			console.log('Updated status indicators for image ID:', imageId)
		}
	}
})(
	// Code to handle the update
	jQuery
)(function ($) {
	'use strict'

	function injectForVoyezIntoMediaModal() {
		if (!wp || !wp.media || !wp.media.frame) return

		wp.media.frame.on('ready', function () {
			addForVoyezButtons()
		})

		wp.media.frame.on('selection:toggle', function () {
			setTimeout(addForVoyezButtons, 100)
		})

		wp.media.frame.on('content:activate', function () {
			setTimeout(addForVoyezButtons, 100)
		})

		wp.media.frame.on('edit:attachment', function () {
			setTimeout(addForVoyezButtons, 100)
		})
	}

	function addForVoyezButtons() {
		if (
			wp.media.frame &&
			wp.media.frame.content &&
			wp.media.frame.content.get
		) {
			var content = wp.media.frame.content.get()
			if (!content) return

			if (content.$el && content.$el.find('.attachment-details').length) {
				insertForVoyezButtonInDetail(content.$el)
			}

			var selection = wp.media.frame.state().get('selection')
			if (selection && selection.length === 1) {
				var attachment = selection.first()
				if (attachment) {
					insertForVoyezButtonInSidebar(attachment)
				}
			}
		}
	}

	function insertForVoyezButtonInDetail($container) {
		if ($container.find('.forvoyez-analyze-button').length) return

		var imageId = $container.find('.attachment-details').data('id')
		if (!imageId) return

		var $forVoyezSection = $(`
            <div class="attachment-forvoyez-section setting">
                <span class="name">ForVoyez Analysis</span>
                <div class="forvoyez-metadata-status" data-image-id="${imageId}">
                    <div class="forvoyez-metadata-item alt-status" title="Alt Text Status">
                        <span class="dashicons dashicons-yes-alt" style="color: #4CAF50;"></span> 
                        <span class="status-label">Alt text</span>
                    </div>
                    <div class="forvoyez-metadata-item title-status" title="Title Status">
                        <span class="dashicons dashicons-yes-alt" style="color: #4CAF50;"></span> 
                        <span class="status-label">Title</span>
                    </div>
                    <div class="forvoyez-metadata-item caption-status" title="Caption Status">
                        <span class="dashicons dashicons-yes-alt" style="color: #4CAF50;"></span> 
                        <span class="status-label">Caption</span>
                    </div>
                </div>
                <div class="forvoyez-description">
                    Generate optimized alt text, title, and caption with AI.
                </div>
                <button type="button" class="button forvoyez-analyze-button" data-image-id="${imageId}">Analyze with ForVoyez</button>
                <div class="forvoyez-status"></div>
            </div>
        `)

		$container.find('.attachment-details').append($forVoyezSection)

		updateStatusIconsForAttachment(imageId)
	}

	function insertForVoyezButtonInSidebar(attachment) {
		if ($('.media-sidebar .forvoyez-analyze-button').length) return

		var imageId = attachment.id

		var $forVoyezSection = $(`
            <div class="attachment-forvoyez-section attachment-info">
                <h3>ForVoyez Analysis</h3>
                <div class="forvoyez-metadata-status" data-image-id="${imageId}">
                    <div class="forvoyez-metadata-item alt-status" title="Alt Text Status">
                        <span class="dashicons dashicons-yes-alt" style="color: #4CAF50;"></span> 
                        <span class="status-label">Alt text</span>
                    </div>
                    <div class="forvoyez-metadata-item title-status" title="Title Status">
                        <span class="dashicons dashicons-yes-alt" style="color: #4CAF50;"></span> 
                        <span class="status-label">Title</span>
                    </div>
                    <div class="forvoyez-metadata-item caption-status" title="Caption Status">
                        <span class="dashicons dashicons-yes-alt" style="color: #4CAF50;"></span> 
                        <span class="status-label">Caption</span>
                    </div>
                </div>
                <div class="forvoyez-description">
                    Generate optimized alt text, title, and caption with AI.
                </div>
                <button type="button" class="button forvoyez-analyze-button" data-image-id="${imageId}">Analyze with ForVoyez</button>
                <div class="forvoyez-status"></div>
            </div>
        `)

		$('.media-sidebar').append($forVoyezSection)

		updateStatusIconsForAttachment(imageId)
	}

	function updateStatusIconsForAttachment(imageId) {
		var attachment = wp.media.attachment(imageId)
		if (!attachment) return

		attachment.fetch().done(function () {
			var $container = $(
				`.forvoyez-metadata-status[data-image-id="${imageId}"]`
			)
			if (!$container.length) return

			var alt = attachment.get('alt') || ''
			var title = attachment.get('title') || ''
			var caption = attachment.get('caption') || ''

			// Alt text status
			if (alt) {
				$container
					.find('.alt-status')
					.html(
						'<span class="dashicons dashicons-yes-alt" style="color: #4CAF50;"></span> <span class="status-label">Alt text</span>'
					)
					.attr('title', 'Has alt text')
			} else {
				$container
					.find('.alt-status')
					.html(
						'<span class="dashicons dashicons-warning" style="color: #FF9800;"></span> <span class="status-label">Alt text</span>'
					)
					.attr('title', 'Missing alt text')
			}

			// Title status
			if (title) {
				$container
					.find('.title-status')
					.html(
						'<span class="dashicons dashicons-yes-alt" style="color: #4CAF50;"></span> <span class="status-label">Title</span>'
					)
					.attr('title', 'Has title')
			} else {
				$container
					.find('.title-status')
					.html(
						'<span class="dashicons dashicons-warning" style="color: #FF9800;"></span> <span class="status-label">Title</span>'
					)
					.attr('title', 'Missing title')
			}

			// Caption status
			if (caption) {
				$container
					.find('.caption-status')
					.html(
						'<span class="dashicons dashicons-yes-alt" style="color: #4CAF50;"></span> <span class="status-label">Caption</span>'
					)
					.attr('title', 'Has caption')
			} else {
				$container
					.find('.caption-status')
					.html(
						'<span class="dashicons dashicons-warning" style="color: #FF9800;"></span> <span class="status-label">Caption</span>'
					)
					.attr('title', 'Missing caption')
			}
		})
	}

	$(document).ready(function () {
		$('<style>')
			.text(
				`
            .attachment-forvoyez-section {
                margin-top: 16px;
                padding-top: 16px;
                border-top: 1px solid #ddd;
            }
            .attachment-forvoyez-section .forvoyez-metadata-status {
                display: flex;
                flex-wrap: wrap;
                gap: 12px;
                margin: 8px 0;
            }
            .attachment-forvoyez-section .forvoyez-metadata-item {
                display: inline-flex;
                align-items: center;
                gap: 4px;
            }
            .attachment-forvoyez-section .status-label {
                font-size: 11px;
                color: #666;
            }
            .attachment-forvoyez-section .forvoyez-description {
                font-size: 12px;
                color: #666;
                margin: 8px 0;
                line-height: 1.4;
            }
            .attachment-forvoyez-section .forvoyez-status {
                margin-top: 5px;
                font-size: 12px;
            }
            .attachment-forvoyez-section .forvoyez-status.success {
                color: #4CAF50;
            }
            .attachment-forvoyez-section .forvoyez-status.error {
                color: #F44336;
            }
            .attachment-forvoyez-section .forvoyez-status.loading {
                color: #2196F3;
            }
        `
			)
			.appendTo('head')

		injectForVoyezIntoMediaModal()
	})
})(jQuery)
