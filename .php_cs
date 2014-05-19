<?php
printf("Executing php_cs!!!\n\n");

$rootDir = __DIR__;

$finder = Symfony\CS\Finder\DefaultFinder::create()
    ->in($rootDir)
    ->ignoreDotFiles(true)
    ->filter(function (SplFileInfo $file) use ($rootDir) {
        $path = $file->getRealPath();

        switch (true) {
            case (strrpos($path, $rootDir . '/test/Bootstrap.php')):
            case (strrpos($path, $rootDir . '/vendor/')):
                return false;
            default:
                return true;
        }
    });

return Symfony\CS\Config\Config::create()
    ->fixers(array(
        'controls_spaces',
        'braces',
        'elseif',
        'eof_ending',
        'function_declaration',
        'include',
        'indentation',
        'linefeed',
        'php_closing_tag',
        'short_tag',
        'trailing_spaces',
        'unused_use',
        'visibility',
    ))
    ->finder($finder);
