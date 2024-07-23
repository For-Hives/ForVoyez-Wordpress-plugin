<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->exclude('vendor')
    ->exclude('node_modules');

$config = new PhpCsFixer\Config();
return $config->setRules([
    '@PSR2' => true,
    'array_syntax' => ['syntax' => 'short'],
    'no_unused_imports' => true,
    'ordered_imports' => true,
    'no_extra_blank_lines' => true,
    'blank_line_before_statement' => true,
    'method_chaining_indentation' => true,
])
    ->setFinder($finder);