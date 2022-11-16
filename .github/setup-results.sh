#!/bin/bash
#
# run PHPUNIT tests, send to codecov

set -e
set +x

# Passed params
DB=$1
PHP_VERSION=$2

# Build a config string for PHPUnit
CONFIG="--verbose --configuration .github/phpunit.xml"

# Running PHPUnit tests
vendor/bin/phpunit ${CONFIG}

