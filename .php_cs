<?php
/*
 * This document has been generated with
 * https://mlocati.github.io/php-cs-fixer-configurator/#version:2.16.3|configurator
 * you can change this configuration by importing this file.
 */
return PhpCsFixer\Config::create()
    ->setRules([
        '@PhpCsFixer' => true,
    ])
    ->setFinder(PhpCsFixer\Finder::create()
        ->exclude('vendor')
        ->in(__DIR__)
    )
;