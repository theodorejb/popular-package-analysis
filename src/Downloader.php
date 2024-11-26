<?php

namespace PackageAnalyzer;

use Exception;
use Generator;

class Downloader
{
    public function getTopPackages(int $start, int $end): Generator
    {
        $perPage = 15;
        $page = intdiv($start, $perPage);
        $id = $page * $perPage;

        while (true) {
            $page++;
            $url = "https://packagist.org/explore/popular.json?page={$page}";
            $json = json_decode(file_get_contents($url));

            foreach ($json->packages as $package) {
                yield $id => $package->name;
                $id++;

                if ($id >= $end) {
                    return;
                }
            }
        }
    }

    public function downloadPackage(string $name, string $downloadDir, string $sourceDir): void
    {
        $zipball = "{$downloadDir}/{$name}.zip";

        if (file_exists($zipball)) {
            return;
        }

        $url = "https://packagist.org/packages/{$name}.json";
        $json = json_decode(file_get_contents($url), true);
        $versions = $json['package']['versions'];

        if (isset($versions['dev-master'])) {
            $version = 'dev-master';
        } else if (isset($versions['dev-main'])) {
            $version = 'dev-main';
        } else {
            // Pick latest version.
            $version = array_key_first($versions);
        }

        $package = $versions[$version];

        if ($package['dist'] === null) {
            echo "Skipping $name due to missing dist\n";
            return;
        }

        $dist = $package['dist']['url'];

        echo "Downloading {$version}...\n";
        $dir = dirname($zipball);

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        exec("curl $dist -o $zipball --location --silent --show-error", $execOutput, $execRetval);

        if ($execRetval !== 0) {
            throw new Exception("Download error: " . var_export($execOutput, true));
        }

        self::extract($zipball, "{$sourceDir}/{$name}");
    }

    private static function extract(string $zipball, string $targetDir): void
    {
        if (is_dir($targetDir)) {
            echo "Deleting existing $targetDir\n";
            self::rmDir($targetDir);
        }

        mkdir($targetDir, 0777, true);
        $cmd = 'tar -xf ' . escapeshellarg($zipball) . ' -C ' . escapeshellarg($targetDir);
        exec($cmd, $execOutput, $execRetval);

        if ($execRetval !== 0) {
            throw new Exception("Failed to extract package: " . var_export($execOutput, true));
        }

        self::renameFirstChildToTarget($targetDir);
    }

    private static function renameFirstChildToTarget(string $targetDir): void
    {
        $child = self::getFirstChildDir($targetDir);
        $parentDir = dirname($targetDir);
        $tempPath = $parentDir . DIRECTORY_SEPARATOR . $child;
        $result = rename($targetDir . DIRECTORY_SEPARATOR . $child, $tempPath);

        if ($result === false) {
            throw new Exception("Failed to rename $child to $tempPath");
        }

        self::rmDir($targetDir);
        $result = rename($tempPath, $targetDir);

        if ($result === false) {
            throw new Exception("Failed to rename $tempPath to $targetDir");
        }
    }

    private static function getFirstChildDir(string $parentPath): string
    {
        $dir = new \DirectoryIterator($parentPath);

        foreach ($dir as $fileInfo) {
            if ($fileInfo->isDir() && !$fileInfo->isDot()) {
                return $fileInfo->getFilename();
            }
        }

        throw new Exception('Failed to find child directory');
    }

    private static function rmDir(string $dir): void
    {
        $cmd = PHP_OS_FAMILY === 'Windows' ? 'rmdir /s /q' : 'rm -rf';
        exec($cmd . ' ' . escapeshellarg($dir), $execOutput, $execRetval);

        if ($execRetval !== 0) {
            throw new Exception("Failed to remove directory: " . var_export($execOutput, true));
        }
    }
}
