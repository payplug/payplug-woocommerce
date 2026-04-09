<?php

$finder = PhpCsFixer\Finder::create()
	->in([
		__DIR__ . '/src',
		__DIR__ . '/tests',
	])
	->append([
		__DIR__ . '/payplug.php',
		__DIR__ . '/payplug-config.php',
		__DIR__ . '/uninstall.php',
		__DIR__ . '/woocommerce-compat.php',
	])
	->name('*.php')
	->ignoreDotFiles(true)
	->ignoreVCS(true);

return (new PhpCsFixer\Config())
	->setRiskyAllowed(true)
	->setRules([
		// PSR-2 base — safe for PHP 5.6+
		'@PSR2' => true,

		// Syntax — PHP 5.4+, safe
		'array_syntax'                            => ['syntax' => 'short'],
		'trailing_comma_in_multiline'             => ['elements' => ['arrays']],
		'no_whitespace_before_comma_in_array'     => true,
		'whitespace_after_comma_in_array'         => true,
		'trim_array_spaces'                       => true,
		'normalize_index_brace'                   => true,

		// Imports
		'no_unused_imports'                       => true,
		'no_leading_import_slash'                 => true,
		'ordered_imports'                         => ['sort_algorithm' => 'alpha'],
		'single_import_per_statement'             => true,

		// Spacing & operators
		'binary_operator_spaces'                  => ['default' => 'single_space'],
		'unary_operator_spaces'                   => true,
		'ternary_operator_spaces'                 => true,
		'concat_space'                            => ['spacing' => 'one'],
		'object_operator_without_whitespace'      => true,
		'no_spaces_around_offset'                 => true,
		'cast_spaces'                             => ['space' => 'single'],
		'standardize_not_equals'                  => true,

		// Blank lines & structure
		'blank_line_after_namespace'              => true,
		'blank_line_after_opening_tag'            => true,
		'blank_line_before_statement'             => ['statements' => ['return']],
		'no_blank_lines_after_class_opening'      => true,
		'no_blank_lines_after_phpdoc'             => true,
		'no_extra_blank_lines'                    => ['tokens' => ['extra', 'throw', 'use']],
		'single_blank_line_before_namespace'      => true,
		'class_attributes_separation'             => ['elements' => ['method' => 'one']],

		// Strings & casts
		'single_quote'                            => true,
		'lowercase_cast'                          => true,
		'short_scalar_cast'                       => true,
		'no_short_bool_cast'                      => true,
		'magic_constant_casing'                   => true,
		'native_function_casing'                  => true,

		// Statements
		'no_empty_statement'                      => true,
		'no_singleline_whitespace_before_semicolons' => true,
		'space_after_semicolon'                   => true,
		'no_unneeded_control_parentheses'         => true,
		'include'                                 => true,
		'self_accessor'                           => true,
		'yoda_style'                              => false,

		// PHPDoc
		'phpdoc_align'                            => ['align' => 'left'],
		'phpdoc_indent'                           => true,
		'phpdoc_no_access'                        => true,
		'phpdoc_no_package'                       => true,
		'phpdoc_no_useless_inheritdoc'            => true,
		'phpdoc_order'                            => true,
		'phpdoc_scalar'                           => true,
		'phpdoc_separation'                       => true,
		'phpdoc_single_line_var_spacing'          => true,
		'phpdoc_trim'                             => true,
		'phpdoc_types'                            => true,
		'phpdoc_var_without_name'                 => true,
		'no_empty_phpdoc'                         => true,

		// Explicitly OFF — PHP 7.0+ only
		'declare_strict_types'                    => false,
		'void_return'                             => false,

		// Explicitly OFF — PHP 7.1+ only
		'nullable_type_declaration_for_default_null_value' => false,

		// Explicitly OFF — PHP 7.4+ only
		'use_arrow_functions'                     => false,

		// Explicitly OFF — PHP 8.0+ only
		'modernize_strpos'                        => false,
	])
	->setFinder($finder);
