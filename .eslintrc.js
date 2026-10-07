module.exports = {
	root: true,
	env: {
		browser: true,
		es2021: true,
		node: true,
	},
	extends: ['eslint:recommended', 'plugin:prettier/recommended'],
	// Composer and build output: linting vendor/ makes `npm run lint` hang.
	ignorePatterns: ['vendor/', 'release/', 'assets/css/'],
	// Provided by WordPress on the admin pages (forvoyezData: wp_localize_script).
	globals: {
		forvoyezData: 'readonly',
		jQuery: 'readonly',
		wp: 'readonly',
	},
	parserOptions: {
		ecmaVersion: 'latest',
		sourceType: 'module',
	},
	rules: {
		'prettier/prettier': 'error',
	},
}
