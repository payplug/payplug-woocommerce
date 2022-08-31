#!/bin/bash

# Exit if any command fails
set -e

# Change to the expected directory
cd "$(dirname "$0")"
cd ..

# Enable nicer messaging for build status
YELLOW_BOLD='\033[1;33m';
COLOR_RESET='\033[0m';
status () {
	echo -e "\n${YELLOW_BOLD}$1${COLOR_RESET}\n"
}

# Installing dependencies
status "Installing dependencies..."
composer install --ignore-platform-reqs
vendor/bin/phpcs --config-set installed_paths vendor/phpcompatibility/php-compatibility

vendor/bin/phpcs --colors -s --report=summary  ./src/ --standard=PHPCompatibility --runtime-set testVersion 5.3
