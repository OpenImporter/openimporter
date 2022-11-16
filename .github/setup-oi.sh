#!/bin/bash

set -e
set -x

DB=$1
PHP_VERSION=$2

# Refresh package list upfront
sudo apt-get update -qq

# Install GNU coreutils
# sudo apt-get install coreutils -qq > /dev/null

# Phpunit and support
# script: phpunit --bootstrap importer/OpenImporter/Tests/bootstrap.php importer/OpenImporter/Tests/
# composer config --file=composer2.json && composer install --no-interaction --quiet
composer install --no-interaction --quiet
if [[ "$PHP_VERSION" =~ ^8 ]]
then
	composer remove phpunit/phpunit --dev
	composer require phpunit/phpunit:^9.0 --dev --update-with-all-dependencies --ignore-platform-reqs
fi
