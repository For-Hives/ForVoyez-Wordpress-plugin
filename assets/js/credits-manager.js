/**
 * ForVoyez Credits Manager
 *
 * Handles the display and real-time updates of the ForVoyez credits information.
 */
;(function ($) {
	'use strict'

	// Store credits information
	let credits = 0
	let isSubscribed = false
	let planName = ''
	let creditsStatusElement = null
	let creditCountElement = null
	let creditWarningElement = null

	// Initialize the credits manager
	function init() {
		// Find the credit display elements if they exist
		creditsStatusElement = $('.forvoyez-credits-status')
		creditCountElement = $('.forvoyez-credit-count')
		creditWarningElement = $('.forvoyez-credit-warning')

		// Load initial credits
		refreshCredits()

		// Add event listeners for operations that consume credits
		setupEventListeners()
	}

	// Refresh credits from the server
	function refreshCredits() {
		// Only make the request if we're on an admin page
		if (!forvoyezData || typeof forvoyezData.ajaxurl === 'undefined') {
			return
		}

		$.ajax({
			url: forvoyezData.ajaxurl,
			type: 'POST',
			data: {
				action: 'forvoyez_get_credits',
				nonce: forvoyezData.verifyAjaxRequestNonce,
			},
			success: function (response) {
				if (response.success) {
					updateCreditsDisplay(response.data)
				} else {
					console.error(
						'Failed to get credits information:',
						response.data.message
					)
				}
			},
			error: function (xhr, status, error) {
				console.error('AJAX error:', error)
			},
		})
	}

	// Update the UI with the current credits
	function updateCreditsDisplay(data) {
		// Update stored values
		credits = data.credits
		isSubscribed = data.is_subscribed
		planName = data.plan_name || ''

		// Update the credit count elements
		if (creditCountElement && creditCountElement.length) {
			creditCountElement.text(credits)

			// Update the styling based on credit level
			if (credits > 20) {
				creditCountElement
					.removeClass('bg-yellow-100 bg-red-100 text-yellow-800 text-red-800')
					.addClass('bg-green-100 text-green-800')
			} else if (credits > 5) {
				creditCountElement
					.removeClass('bg-green-100 bg-red-100 text-green-800 text-red-800')
					.addClass('bg-yellow-100 text-yellow-800')
			} else {
				creditCountElement
					.removeClass(
						'bg-green-100 bg-yellow-100 text-green-800 text-yellow-800'
					)
					.addClass('bg-red-100 text-red-800')
			}
		}

		// Update the subscription status
		if (creditsStatusElement && creditsStatusElement.length) {
			let statusHtml = ''

			if (isSubscribed) {
				statusHtml =
					'<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Actif</span>'
				if (planName) {
					statusHtml += ' - ' + planName
				}
			} else {
				statusHtml =
					'<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">Aucun abonnement actif</span>'
			}

			creditsStatusElement.html(statusHtml)
		}

		// Update the warning message
		if (creditWarningElement && creditWarningElement.length) {
			if (credits < 10) {
				creditWarningElement.removeClass('hidden')
			} else {
				creditWarningElement.addClass('hidden')
			}
		}

		// Update any global credit displays
		updateGlobalCreditDisplay()
	}

	// Update global credit display in header
	function updateGlobalCreditDisplay() {
		const globalCreditElement = $('.forvoyez-global-credit-count')
		if (globalCreditElement.length) {
			globalCreditElement.text(credits)

			// Update styling based on credit level
			if (credits > 20) {
				globalCreditElement
					.removeClass('bg-yellow-100 bg-red-100 text-yellow-800 text-red-800')
					.addClass('bg-green-100 text-green-800')
			} else if (credits > 5) {
				globalCreditElement
					.removeClass('bg-green-100 bg-red-100 text-green-800 text-red-800')
					.addClass('bg-yellow-100 text-yellow-800')
			} else {
				globalCreditElement
					.removeClass(
						'bg-green-100 bg-yellow-100 text-green-800 text-yellow-800'
					)
					.addClass('bg-red-100 text-red-800')
			}
		}
	}

	// Setup event listeners for operations that consume credits
	function setupEventListeners() {
		// Individual image analysis
		$(document).on('forvoyez:image-analyzed', function () {
			refreshCredits()
		})

		// Batch analysis completion
		$(document).on('forvoyez:batch-completed', function () {
			refreshCredits()
		})

		// After analyzing a single image
		$(document).on('forvoyez:single-image-analyzed', function () {
			refreshCredits()
		})

		// After bulk analysis
		$(document).on('forvoyez:bulk-analysis-completed', function () {
			refreshCredits()
		})
	}

	// Create a floating credits display widget
	function createCreditsWidget() {
		// Only create if it doesn't already exist
		if ($('.forvoyez-credits-widget').length === 0) {
			const widget = $(`
                <div class="forvoyez-credits-widget fixed bottom-4 right-4 bg-white p-3 rounded-lg shadow-lg border border-gray-200 z-30">
                    <div class="flex items-center space-x-2">
                        <div class="text-gray-700 font-medium">Crédits ForVoyez:</div>
                        <span class="forvoyez-global-credit-count px-2 py-1 inline-flex text-xs leading-4 font-semibold rounded-full bg-green-100 text-green-800">${credits}</span>
                    </div>
                    <div class="mt-1 text-xs text-gray-500">
                        ${isSubscribed ? 'Abonnement: ' + planName : 'Aucun abonnement actif'}
                    </div>
                    <div class="mt-1 text-xs text-red-600 ${credits < 10 ? '' : 'hidden'}">
                        Crédits faibles! Veuillez recharger.
                    </div>
                </div>
            `)

			$('body').append(widget)

			// Update the widget with current credits
			updateGlobalCreditDisplay()
		}
	}

	// Public functions
	return {
		init: init,
		refreshCredits: refreshCredits,
		createCreditsWidget: createCreditsWidget,
	}
})(jQuery)

// Initialize when document is ready
jQuery(document).ready(function () {
	// Initialize the credits manager
	window.ForVoyezCredits = window.ForVoyezCredits || {}
	jQuery.extend(window.ForVoyezCredits, ForVoyezCreditsManager)

	// Start the credits manager
	if (window.ForVoyezCredits) {
		window.ForVoyezCredits.init()

		// Create the credits widget if we're on a ForVoyez admin page
		if (jQuery('body').hasClass('toplevel_page_auto-alt-text-for-images')) {
			window.ForVoyezCredits.createCreditsWidget()
		}
	}
})
