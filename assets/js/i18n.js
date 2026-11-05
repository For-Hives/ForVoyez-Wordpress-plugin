/**
 * Translations of the ForVoyez admin scripts.
 *
 * forvoyezL10n comes from PHP (wp_localize_script, see
 * forvoyez_get_js_translations()): each English string maps to its
 * translation, and the singular of a plural maps to its forms for 0, 1 and 2
 * items. A missing string shows the English text.
 *
 * The results are plain text: write them with .text(), textContent or
 * createTextNode, never as HTML.
 */
;(function (window) {
	'use strict'

	const strings = window.forvoyezL10n || {}

	/**
	 * Translate a string.
	 *
	 * @param {string} text English string, listed in forvoyez_get_js_translations().
	 * @return {string} The translation, or the English string.
	 */
	function t(text) {
		const translation = strings[text]
		return typeof translation === 'string' && translation !== ''
			? translation
			: text
	}

	/**
	 * Translate a string with a singular and a plural form.
	 *
	 * @param {string} single English singular.
	 * @param {string} plural English plural.
	 * @param {number} count  Number the string is about.
	 * @return {string} The form for count.
	 */
	function n(single, plural, count) {
		const forms = strings[single]
		const index = count === 0 ? 0 : count === 1 ? 1 : 2
		if (Array.isArray(forms) && forms[index]) {
			return forms[index]
		}
		return count === 1 ? single : plural
	}

	/**
	 * Replace the placeholders of a translated string, like sprintf().
	 *
	 * Handles %s, %d, numbered %1$s / %2$d (translations may reorder them)
	 * and %% for a percent sign.
	 *
	 * @param {string} text   Translated string.
	 * @param {...*}   values Values of the placeholders.
	 * @return {string} The string with its values.
	 */
	function format(text, ...values) {
		let next = 0
		return String(text).replace(
			/%(?:(\d+)\$)?([sd%])/g,
			(match, position, type) => {
				if (type === '%') {
					return '%'
				}
				const value = position ? values[position - 1] : values[next++]
				if (type === 'd') {
					const number = Math.trunc(Number(value))
					return String(Number.isFinite(number) ? number : 0)
				}
				return String(value ?? '')
			}
		)
	}

	window.forvoyezI18n = { format, n, t }
})(window)
