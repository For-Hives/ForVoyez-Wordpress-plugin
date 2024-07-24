<?php

$finder = PhpCsFixer\Finder::create()
	->in(__DIR__)
	->exclude('vendor')
	->exclude('node_modules');

$config = new PhpCsFixer\Config();
return $config
	->setRules([
		'@PSR2' => true,
		'array_syntax' => ['syntax' => 'short'],
		'no_unused_imports' => true,
		'ordered_imports' => true,
		'no_extra_blank_lines' => true,
		'blank_line_before_statement' => true,
		'method_chaining_indentation' => true,
		'single_quote' => true,
		'trailing_comma_in_multiline' => true,
		'braces' => [
			'position_after_functions_and_oop_constructs' => 'same',
		],
		'indentation_type' => true,
		'binary_operator_spaces' => [
			'default' => 'single_space',
		],
		'cast_spaces' => ['space' => 'single'],
		'concat_space' => ['spacing' => 'one'],
		'declare_equal_normalize' => ['space' => 'single'],
	])
	->setIndent("\t")
	->setLineEnding("\n")
	->setFinder($finder);
