<?php

$finder = PhpCsFixer\Finder::create()
    ->name('spoof')
    ->name('.php-cs-fixer.php')
    ->in(__DIR__.'/src/');

$config = new PhpCsFixer\Config();

return $config
    ->setRules([
        '@PSR2' => true,
        '@Symfony' => true,
        '@PhpCsFixer' => true,
        'phpdoc_order' => true,
        'ordered_class_elements' => true,
        'multiline_whitespace_before_semicolons' => false,
        'no_superfluous_phpdoc_tags' => false,
        'phpdoc_annotation_without_dot' => false,
        'phpdoc_types_order' => [
            'null_adjustment' => 'always_last',
        ],
        'yoda_style' => false,
        'ternary_to_null_coalescing' => true,
        'array_syntax' => ['syntax' => 'short'],
        'php_unit_test_class_requires_covers' => false,
    ])
    ->setFinder($finder);
