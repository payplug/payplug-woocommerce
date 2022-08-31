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

status "ERROR: Cannot build plugin zip with dirty working tree."
ls
vendor/bin/phpcs --colors -s ./src/ --standard=PHPCompatibility --runtime-set testVersion 5.3
