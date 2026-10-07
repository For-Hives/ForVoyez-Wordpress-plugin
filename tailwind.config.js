/**
 * Tailwind CSS (v3) configuration for the plugin admin page.
 *
 * Builds assets/css/admin-tailwind.css with `npm run build:css`.
 *
 * @type {import('tailwindcss').Config}
 */
module.exports = {
	content: [
		'./templates/**/*.php',
		'./includes/**/*.php',
		'./assets/js/admin-script.js',
	],
	// False positive from the `$container` variable in admin-script.js.
	blocklist: ['container'],
	theme: {
		extend: {
			colors: {
				'forvoyez-primary': '#4a90e2',
				'forvoyez-secondary': '#50e3c2',
			},
		},
	},
	plugins: [],
}
