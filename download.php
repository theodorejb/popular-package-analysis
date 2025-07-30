<?php

use PackageAnalyzer\Downloader;

require 'vendor/autoload.php';

if ($argc < 3) {
    echo "Usage: download.php min-package max-package\n";
    exit(1);
}

$minPackage = (int) $argv[1];
$maxPackage = (int) $argv[2];

$extractPhpOnlyWith7zip = isset($argv[3]) && $argv[3] === '-p';
$downloader = new Downloader($extractPhpOnlyWith7zip);

foreach ($downloader->getTopPackages($minPackage, $maxPackage) as $i => $package) {
    echo "[$i] $package\n";
    $downloader->downloadPackage($package, __DIR__ . '/zipballs', __DIR__ . '/sources');
}
