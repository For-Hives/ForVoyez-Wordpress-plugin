/**
 * ForVoyez Media Library Integration
 * Handles image analysis directly in the WordPress Media Library
 */
;(function ($) {
	'use strict'

	// assets/js/i18n.js: strings are listed in forvoyez_get_js_translations().
	const { format, t } = window.forvoyezI18n

	// Credit management variables
	let creditsInfo = {
		credits: null,
		isSubscribed: false,
		planName: '',
		isLoading: true,
	}

	// Detect current page context
	const isPostPage = window.location.href.includes('post.php')
	const isUploadPage = window.location.href.includes('upload.php')

	// Initialize on document ready
	$(document).ready(function () {
		console.log('ForVoyez Media Script initialized. Context:', {
			isPostPage: isPostPage,
			isUploadPage: isUploadPage,
		})

		// Initialize and load credits info
		refreshCredits()

		// Add event handler for "Analyze with ForVoyez" button
		$(document).on('click', '.forvoyez-analyze-button', function (e) {
			e.preventDefault()
			const imageId = $(this).data('image-id')
			const $button = $(this)
			const $statusArea = $button.siblings('.forvoyez-status')

			console.log('Analyze button clicked for image ID:', imageId)

			// Disable button and show loading state
			$button
				.prop('disabled', true)
				.text(t('Analyzing...'))
				.addClass('opacity-50')
			$statusArea
				.empty()
				.append(
					'<span class="forvoyez-spinner"></span>',
					document.createTextNode(' ' + t('Analyzing image...'))
				)
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
					console.log('Analysis response:', response)

					// Re-enable button
					$button
						.prop('disabled', false)
						.text(t('Analyze with ForVoyez'))
						.removeClass('opacity-50')

					if (response.success) {
						// Update UI directly with new metadata
						updateImageMetadataUI(imageId, response.data.metadata)

						// Show success message
						$statusArea
							.empty()
							.append(
								'<span class="dashicons dashicons-yes"></span>',
								document.createTextNode(
									' ' + t('Analysis complete! Metadata has been updated.')
								)
							)
							.addClass('success')
							.removeClass('loading error')

						// Refresh credits
						refreshCredits()
					} else {
						// Show error message
						showStatusError(
							$statusArea,
							response.data ? response.data.message : t('Unknown error')
						)
					}
				},
				error: function (xhr, status, error) {
					console.error('AJAX error:', error)

					// Re-enable button
					$button
						.prop('disabled', false)
						.text(t('Analyze with ForVoyez'))
						.removeClass('opacity-50')

					// Show error message
					showStatusError($statusArea, error)
				},
			})
		})

		// Handle media modal - when media modal is opened
		if (wp && wp.media && wp.media.frame) {
			$(document).on('click', '.media-modal .attachment', function () {
				// Make sure we update credits on attachment selection too
				setTimeout(refreshCredits, 500)
			})
		}
	})

	/**
	 * Show an analysis error in a status area.
	 *
	 * Server messages come HTML-escaped (esc_html__): decode the entities so
	 * apostrophes show as text, then set them as text (never as HTML).
	 */
	function showStatusError($statusArea, message) {
		const decoder = document.createElement('textarea')
		decoder.innerHTML = String(message ?? '')

		$statusArea
			.empty()
			.append(
				'<span class="dashicons dashicons-warning"></span>',
				document.createTextNode(' ' + format(t('Error: %s'), decoder.value))
			)
			.addClass('error')
			.removeClass('loading success')
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

		// For post.php page (attachment edit page), use different logic
		if (isPostPage) {
			updatePostPageFields(metadata)
		} else {
			// Different selectors for different contexts
			updateAttachmentEditorFields(metadata)
			updateMediaModalFields(metadata)
			updateMediaGridFields(imageId, metadata)
		}

		// Always update status indicators
		updateStatusIndicators(imageId, metadata)

		// Force update specific fields in all contexts
		forceUpdateSpecificFields(metadata)

		// Only try to refresh media library if we're in the right context
		if (!isPostPage && wp && wp.media && wp.media.frame) {
			try {
				// Only try this if we're in a context where it makes sense
				if (typeof wp.media.frame.content.get === 'function') {
					const content = wp.media.frame.content.get()
					if (content && content.collection && content.collection.props) {
						content.collection.props.set({ ignore: +new Date() })
					}
				}
			} catch (e) {
				console.log('Could not refresh media library:', e)
			}
		}
	}

	/**
	 * Update fields specifically on post.php page
	 */
	function updatePostPageFields(metadata) {
		console.log('Updating post page fields')

		// Title field
		const $titleField = $('#title')
		if ($titleField.length) {
			$titleField.val(metadata.title)
			$titleField.trigger('change')
			console.log('Updated post title field')
		}

		// Alt text field
		const $altTextField = $('#attachment_alt')
		if ($altTextField.length) {
			$altTextField.val(metadata.alt_text)
			$altTextField.trigger('change')
			console.log('Updated post alt text field')
		}

		// Caption field
		const $captionField = $('#attachment_caption')
		if ($captionField.length) {
			$captionField.val(metadata.caption)
			$captionField.trigger('change')
			console.log('Updated post caption field')
		}
	}

	/**
	 * Force update specific fields in all contexts
	 */
	function forceUpdateSpecificFields(metadata) {
		// Specific selectors based on context
		const selectors = isPostPage
			? {
					alt: ['#attachment_alt'],
					title: ['#title'],
					caption: ['#attachment_caption'],
				}
			: {
					alt: [
						'#attachment-details-alt-text',
						'#attachment-details-two-column-alt-text',
						'.setting[data-setting="alt"] textarea',
						'.setting[data-setting="alt"] input',
						'.compat-field-_wp_attachment_image_alt input',
					],
					title: [
						'#attachment-details-title',
						'#attachment-details-two-column-title',
						'.setting[data-setting="title"] input',
						'input[name="post_title"]',
					],
					caption: [
						'#attachment-details-caption',
						'#attachment-details-two-column-caption',
						'.setting[data-setting="caption"] textarea',
						'textarea[name="excerpt"]',
					],
				}

		// Force update alt text fields
		selectors.alt.forEach(selector => {
			const $field = $(selector)
			if ($field.length) {
				$field.val(metadata.alt_text)
				$field.trigger('change').trigger('input')
				console.log('Forced update of alt text field:', selector)
			}
		})

		// Force update title fields
		selectors.title.forEach(selector => {
			const $field = $(selector)
			if ($field.length) {
				$field.val(metadata.title)
				$field.trigger('change').trigger('input')
				console.log('Forced update of title field:', selector)
			}
		})

		// Force update caption fields
		selectors.caption.forEach(selector => {
			const $field = $(selector)
			if ($field.length) {
				$field.val(metadata.caption)
				$field.trigger('change').trigger('input')
				console.log('Forced update of caption field:', selector)
			}
		})

		// If we're in the media modal and not on post.php, update the model data
		if (!isPostPage && wp && wp.media && wp.media.frame) {
			try {
				if (typeof wp.media.frame.state === 'function') {
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
	 * Update status indicators
	 */
	function updateStatusIndicators(imageId, metadata) {
		// First, try to find the status container by data-image-id
		const $statusContainer = $(
			`.forvoyez-metadata-status[data-image-id="${imageId}"]`
		)

		if ($statusContainer.length) {
			// Only the analyzed fields change: a missing one keeps its warning.
			;['alt', 'title', 'caption'].forEach(field => {
				const value = field === 'alt' ? metadata.alt_text : metadata[field]
				if (value) {
					setMetadataStatus(
						$statusContainer.find(`.${field}-status`),
						field,
						true
					)
				}
			})

			console.log('Updated status indicators for image ID:', imageId)
		}
	}

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

	/**
	 * Texts of the status of one metadata field (alt, title or caption).
	 */
	function metadataStatusTexts(field) {
		switch (field) {
			case 'alt':
				return {
					has: t('Has alt text'),
					label: t('Alt text'),
					missing: t('Missing alt text'),
					status: t('Alt Text Status'),
				}
			case 'title':
				return {
					has: t('Has title'),
					label: t('Title'),
					missing: t('Missing title'),
					status: t('Title Status'),
				}
			default:
				return {
					has: t('Has caption'),
					label: t('Caption'),
					missing: t('Missing caption'),
					status: t('Caption Status'),
				}
		}
	}

	/**
	 * Show whether a metadata field is filled: icon, label and tooltip.
	 */
	function setMetadataStatus($item, field, isSet) {
		const texts = metadataStatusTexts(field)
		$item
			.empty()
			.append(
				$('<span>', {
					class: isSet
						? 'dashicons dashicons-yes-alt'
						: 'dashicons dashicons-warning',
					style: isSet ? 'color: #4CAF50;' : 'color: #FF9800;',
				}),
				document.createTextNode(' '),
				$('<span>', { class: 'status-label', text: texts.label })
			)
			.attr('title', isSet ? texts.has : texts.missing)
	}

	/**
	 * The ForVoyez section of the attachment details: status, button, result.
	 */
	function buildForVoyezSection(imageId, sectionClass, $heading) {
		const $status = $('<div>', {
			class: 'forvoyez-metadata-status',
			'data-image-id': imageId,
		})
		;['alt', 'title', 'caption'].forEach(field => {
			const $item = $('<div>', {
				class: `forvoyez-metadata-item ${field}-status`,
			})
			setMetadataStatus($item, field, true)
			$item.attr('title', metadataStatusTexts(field).status)
			$status.append($item)
		})

		return $('<div>', { class: sectionClass }).append(
			$heading.text(t('ForVoyez Analysis')),
			$status,
			$('<div>', {
				class: 'forvoyez-description',
				text: t('Generate optimized alt text, title, and caption with AI.'),
			}),
			$('<button>', {
				type: 'button',
				class: 'button forvoyez-analyze-button',
				'data-image-id': imageId,
				text: t('Analyze with ForVoyez'),
			}),
			$('<div>', { class: 'forvoyez-status' })
		)
	}

	function insertForVoyezButtonInDetail($container) {
		if ($container.find('.forvoyez-analyze-button').length) return

		var imageId = $container.find('.attachment-details').data('id')
		if (!imageId) return

		$container
			.find('.attachment-details')
			.append(
				buildForVoyezSection(
					imageId,
					'attachment-forvoyez-section setting',
					$('<span>', { class: 'name' })
				)
			)

		updateStatusIconsForAttachment(imageId)
	}

	function insertForVoyezButtonInSidebar(attachment) {
		if ($('.media-sidebar .forvoyez-analyze-button').length) return

		var imageId = attachment.id

		$('.media-sidebar').append(
			buildForVoyezSection(
				imageId,
				'attachment-forvoyez-section attachment-info',
				$('<h3>')
			)
		)

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

			setMetadataStatus(
				$container.find('.alt-status'),
				'alt',
				!!attachment.get('alt')
			)
			setMetadataStatus(
				$container.find('.title-status'),
				'title',
				!!attachment.get('title')
			)
			setMetadataStatus(
				$container.find('.caption-status'),
				'caption',
				!!attachment.get('caption')
			)
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
