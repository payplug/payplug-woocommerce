#!/bin/bash

# Exit if any command fails
set -e

# Change to the expected directory
cd "$(dirname "$0")"
cd ..

vendor/bin/phpcs --colors -s ./src/ --standard=PHPCompatibility --runtime-set testVersion 5.3
echo "ERROR: Cannot build plugin zip with dirty working tree."
