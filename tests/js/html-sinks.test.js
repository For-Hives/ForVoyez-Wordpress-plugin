/**
 * Values from the API must reach the admin pages as text, never as HTML.
 *
 * The real scripts run against a small fake of jQuery that records every value
 * written through a jQuery HTML method. TestForvoyezJsHtmlSinks.php runs this
 * file with Node, so it needs no npm package and runs in the PHPUnit CI job.
 */
const assert = require('node:assert')
const fs = require('node:fs')
const path = require('node:path')
const { test } = require('node:test')
const vm = require('node:vm')

const TRAP = '<img src=x onerror=alert(1)>'
const HTML_METHODS = new Set([
	'after',
	'append',
	'before',
	'html',
	'prepend',
	'replaceWith',
	'wrap',
])

// Plural strings of the scripts (forms for 0, 1 and 2 items in PHP).
const PLURAL = /^(Are you sure|Cost:|Displaying|Starting analysis|You currently)/

/**
 * Translations that are all markup: every string of the scripts becomes TRAP.
 */
const TRAP_TRANSLATIONS = new Proxy(
	{},
	{
		get(target, text) {
			if (typeof text !== 'string') return undefined
			return PLURAL.test(text) ? [TRAP, TRAP, TRAP] : TRAP
		},
	}
)

function loadScript(file, translations = {}) {
	const page = {
		ajaxCalls: [],
		clickHandlers: {},
		htmlWrites: [],
		readyCallbacks: [],
		textWrites: [],
	}

	function recordWrite(method, args) {
		for (const arg of args) {
			if (typeof arg === 'string') {
				if (HTML_METHODS.has(method)) page.htmlWrites.push(arg)
				if (method === 'text') page.textWrites.push(arg)
			} else if (arg && arg.nodeType === 3) {
				page.textWrites.push(arg.textContent)
			}
		}
	}

	function element(selector) {
		return new Proxy(function () {}, {
			get(target, method) {
				if (method === 'length') return 1
				if (method === Symbol.toPrimitive) return () => ''
				if (typeof method === 'symbol' || method === 'then') return undefined
				return (...args) => {
					recordWrite(method, args)
					if (method === 'ready') page.readyCallbacks.push(args[0])
					if (method === 'each') args[0].call(element(), 0, element())
					if (method === 'on' && typeof args[1] === 'string') {
						page.clickHandlers[args[1]] = args[args.length - 1]
					} else if (method === 'on' && typeof selector === 'string') {
						page.clickHandlers[selector] = args[args.length - 1]
					}
					return element()
				}
			},
		})
	}

	function jQuery(selector, props) {
		// $('<div>' + value) parses value as HTML.
		if (typeof selector === 'string' && selector.startsWith('<')) {
			page.htmlWrites.push(selector)
		}
		// $('<span>', { text }) sets the text with .text().
		if (props && typeof props.text === 'string') {
			page.textWrites.push(props.text)
		}
		return element(selector)
	}
	jQuery.ajax = options => page.ajaxCalls.push(options)
	jQuery.post = () => element()

	const context = {
		console: { error() {}, log() {}, warn() {} },
		document: {
			createElement() {
				return {
					set innerHTML(html) {
						this.value = html.replace(/&lt;/g, '<').replace(/&gt;/g, '>')
					},
				}
			},
			createTextNode: textContent => ({ nodeType: 3, textContent }),
		},
		forvoyezData: { urls: {} },
		jQuery,
		setTimeout() {},
		window: {
			forvoyezL10n: translations,
			location: { href: 'upload.php' },
		},
		wp: {},
	}
	vm.createContext(context)
	// i18n.js loads first, as its WordPress dependency.
	for (const script of ['i18n.js', file]) {
		const code = fs.readFileSync(
			path.join(__dirname, '../../assets/js', script),
			'utf8'
		)
		vm.runInContext(code, context, { filename: script })
	}
	page.readyCallbacks.forEach(callback => callback())

	page.lastAjax = action =>
		page.ajaxCalls.filter(call => call.data.action === action).pop()

	return page
}

function assertTrapWrittenAsTextOnly(page, expectedText) {
	const asHtml = page.htmlWrites.filter(value => value.includes('onerror'))
	assert.deepStrictEqual(asHtml, [], 'API value written as HTML')
	assert.ok(
		page.textWrites.some(value => value.includes(expectedText)),
		'API value missing from the page'
	)
}

test('the credits widget writes the plan name as text', () => {
	const page = loadScript('admin-script.js')

	page.lastAjax('forvoyez_get_credits').success({
		data: {
			credits: 42,
			has_low_credits: false,
			is_subscribed: true,
			plan_name: TRAP,
			status: TRAP,
		},
		success: true,
	})

	assertTrapWrittenAsTextOnly(page, ' - ' + TRAP)
})

for (const [name, message] of [
	['raw', TRAP],
	['HTML-escaped', '&lt;img src=x onerror=alert(1)&gt;'],
]) {
	test(`the media library writes a ${name} analysis error as text`, () => {
		const page = loadScript('media-script.js')

		page.clickHandlers['.forvoyez-analyze-button'].call(null, {
			preventDefault() {},
		})
		page
			.lastAjax('forvoyez_analyze_media_image')
			.success({ data: { message }, success: false })

		assertTrapWrittenAsTextOnly(page, ' Error: ' + TRAP)
	})
}

test('the media library writes a server error as text', () => {
	const page = loadScript('media-script.js')

	page.clickHandlers['.forvoyez-analyze-button'].call(null, {
		preventDefault() {},
	})
	page.lastAjax('forvoyez_analyze_media_image').error(null, 'error', TRAP)

	assertTrapWrittenAsTextOnly(page, ' Error: ' + TRAP)
})

test('the admin scripts write their translations as text', () => {
	const page = loadScript('admin-script.js', TRAP_TRANSLATIONS)

	// Credits widget: low credits (the warning with its link), then an error.
	page.lastAjax('forvoyez_get_credits').success({
		data: { credits: 3, is_subscribed: true, plan_name: 'Pro' },
		success: true,
	})
	page.lastAjax('forvoyez_get_credits').success({
		data: { message: 'x' },
		success: false,
	})

	// "Analyze all": the confirmation modal and the start notification.
	page.clickHandlers[
		'#forvoyez-analyze-missing, #forvoyez-analyze-missing-alt, #forvoyez-analyze-all'
	].call(null, { preventDefault() {} })
	page
		.lastAjax('forvoyez_get_image_ids')
		.success({ data: { image_ids: [1, 2, 3] }, success: true })

	assertTrapWrittenAsTextOnly(page, TRAP)
	assert.ok(page.textWrites.filter(value => value === TRAP).length > 5)
})

test('the media library writes its translations as text', () => {
	const page = loadScript('media-script.js', TRAP_TRANSLATIONS)

	page.clickHandlers['.forvoyez-analyze-button'].call(null, {
		preventDefault() {},
	})
	page.lastAjax('forvoyez_analyze_media_image').success({
		data: { metadata: { alt_text: 'a', caption: 'c', title: 't' } },
		success: true,
	})

	assertTrapWrittenAsTextOnly(page, ' ' + TRAP)
})

test('placeholders follow the translation, numbered or not', () => {
	const context = {
		window: {
			forvoyezL10n: {
				'Error processing image %1$d: %2$s': 'Bild %1$d: %2$s',
				'Processing: %1$d%% complete. Successful: %2$d, Failed: %3$d':
					'%2$d gut, %3$d schlecht (%1$d %%)',
			},
		},
	}
	vm.createContext(context)
	vm.runInContext(
		fs.readFileSync(path.join(__dirname, '../../assets/js/i18n.js'), 'utf8'),
		context
	)
	const { format, n, t } = context.window.forvoyezI18n

	assert.strictEqual(
		format(t('Error processing image %1$d: %2$s'), 12, 'x'),
		'Bild 12: x'
	)
	assert.strictEqual(
		format(
			t('Processing: %1$d%% complete. Successful: %2$d, Failed: %3$d'),
			40,
			2,
			1
		),
		'2 gut, 1 schlecht (40 %)'
	)
	// Untranslated: English, with its own plural rule.
	assert.strictEqual(format(n('%d image', '%d images', 1), 1), '1 image')
	assert.strictEqual(format(n('%d image', '%d images', 0), 0), '0 images')
})
