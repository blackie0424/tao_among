<?php

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR2' => true,
        'array_syntax' => ['syntax' => 'short'],
    ])
    ->setFinder(
        PhpCsFixer\Finder::create()->in(__DIR__)
    );
