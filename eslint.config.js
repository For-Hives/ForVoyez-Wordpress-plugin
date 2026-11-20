const js = require('@eslint/js')
const prettierRecommended = require('eslint-plugin-prettier/recommended')
const globals = require('globals')

module.exports = [
	// Composer and build output: linting vendor/ makes `npm run lint` hang.
	{ ignores: ['vendor/', 'release/', 'assets/css/', 'node_modules/'] },
	js.configs.recommended,
	prettierRecommended,
	{
		languageOptions: {
			ecmaVersion: 'latest',
			sourceType: 'module',
			globals: {
				...globals.browser,
				...globals.node,
				// Provided by WordPress on the admin pages (forvoyezData: wp_localize_script).
				forvoyezData: 'readonly',
				jQuery: 'readonly',
				wp: 'readonly',
			},
		},
		rules: {
			'prettier/prettier': 'error',
		},
	},
	// CommonJS config files.
	{
		files: ['eslint.config.js', '.prettierrc.js', 'tailwind.config.js'],
		languageOptions: { sourceType: 'commonjs' },
	},
]
