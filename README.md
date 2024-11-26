Analysis of popular composer packages
-------------------------------------

Tools for downloading the most popular Composer packages and analyzing the code.

## Usage

1. Clone the repository.
2. Run `composer install`.
3. Run `php download.php 0 1000` to download and extract the top 1000 Composer packages.
4. Create an analysis script for your use case and run it.

The `zipballs/` directory contains downloaded archives, while `sources/` contains the extracted
sources.
