<?php

use PackageAnalyzer\Downloader;

require 'vendor/autoload.php';

if ($argc < 3) {
    echo "Usage: download.php min-package max-package\n";
    exit(1);
}

$minPackage = (int) $argv[1];
$maxPackage = (int) $argv[2];
$downloader = new Downloader();

foreach ($downloader->getTopPackages($minPackage, $maxPackage) as $i => $package) {
    echo "[$i] $package\n";
    $downloader->downloadPackage($package, __DIR__ . '/zipballs', __DIR__ . '/sources');
}
