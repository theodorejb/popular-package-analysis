<?php

namespace PackageAnalyzer;

use Generator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class Analyzer
{
    public function getPhpFiles(string $directory): Generator
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        /** @var \DirectoryIterator $file */
        foreach ($iterator as $file) {
            $path = $file->getPathname();

            if (preg_match('/\.php$/', $path)) {
                yield $path;
            }
        }
    }
}
