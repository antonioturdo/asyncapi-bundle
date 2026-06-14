<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude('var')
    ->exclude('vendor')
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@PER-CS' => true,
        '@PHP82Migration' => true,
        'array_syntax' => ['syntax' => 'short'],
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'no_unused_imports' => true,
        'fully_qualified_strict_types' => true,
        'native_function_invocation' => ['include' => ['@compiler_optimized']],
        'no_superfluous_phpdoc_tags' => true,
        'php_unit_test_case_static_method_calls' => ['call_type' => 'self'],
    ])
    ->setRiskyAllowed(true)
    ->setFinder($finder)
;
