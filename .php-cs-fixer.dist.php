<?php

/**
 * PHP-CS-Fixer configuration: PER Coding Style 3.0, indented with tabs as in WordPress.
 *
 * Run from the repo root with `php-cs-fixer fix`. Formatting commits go in .git-blame-ignore-revs.
 */

$finder = PhpCsFixer\Finder::create()->in(__DIR__ . '/src');

return (new PhpCsFixer\Config())
	->setIndent("\t")
	->setRules([
		'@PER-CS3x0' => true,

		'array_syntax' => ['syntax' => 'short'],
		'binary_operator_spaces' => ['default' => 'single_space', 'operators' => ['=>' => 'align_single_space_minimal']],
		'no_extra_blank_lines' => ['tokens' => ['curly_brace_block', 'extra', 'parenthesis_brace_block', 'square_brace_block']],
		'single_line_comment_spacing' => true,
		'trim_array_spaces' => true,
		'unary_operator_spaces' => true,

		// Docblocks: tag columns aligned across the whole block, so @param tags stay together
		'no_empty_phpdoc' => true,
		'phpdoc_align' => ['align' => 'vertical', 'tags' => ['param', 'return', 'throws', 'var']],
		'phpdoc_indent' => true,
		'phpdoc_scalar' => true,
		'phpdoc_separation' => true,
		'phpdoc_trim' => true,
	])
	->setFinder($finder);
