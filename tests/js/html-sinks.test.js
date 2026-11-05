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

function loadScript(file) {
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

	function element() {
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
					}
					return element()
				}
			},
		})
	}

	function jQuery(selector) {
		// $('<div>' + value) parses value as HTML.
		if (typeof selector === 'string' && selector.startsWith('<')) {
			page.htmlWrites.push(selector)
		}
		return element()
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
		forvoyezData: { messages: {}, urls: {} },
		jQuery,
		setTimeout() {},
		window: { location: { href: 'upload.php' } },
		wp: {},
	}
	const code = fs.readFileSync(
		path.join(__dirname, '../../assets/js', file),
		'utf8'
	)
	vm.runInNewContext(code, context, { filename: file })
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
