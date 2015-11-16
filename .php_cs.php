<?php

$finder = \Symfony\CS\Finder\DefaultFinder::create()
    ->exclude('vendor')
    ->in(__DIR__);

$fixers = [
    '-concat_without_spaces',
    '-empty_return',
    '-multiline_array_trailing_comma',
    '-phpdoc_short_description',
    '-single_quote',
    '-trim_array_spaces',
    '-operators_spaces',
    '-unary_operators_spaces',
    '-unalign_equals',
    '-unalign_double_arrow',
    'align_double_arrow',
    'newline_after_open_tag',
    'ordered_use',
    'phpdoc_order'
];

return \Symfony\CS\Config\Config::create()
    ->level(Symfony\CS\FixerInterface::PSR2_LEVEL)
    ->fixers($fixers)
    ->finder($finder);
