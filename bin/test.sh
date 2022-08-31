#!/bin/bash



# Change to the expected directory
cd "$(dirname "$0")"
cd ..

# Enable nicer messaging for build status
YELLOW_BOLD='\033[1;33m';
COLOR_RESET='\033[0m';
status () {
	echo -e "\n${YELLOW_BOLD}$1${COLOR_RESET}\n"
}
try
	vendor/bin/phpcs --colors --report=code ./src/ --standard=PHPCompatibility --runtime-set testVersion 5.3 --report-file=/home/runner/work/payplug-woocommerce/report.txt
catch
	echo "Error in $__EXCEPTION_SOURCE__ at line: $__EXCEPTION_LINE__!"
status "ERROR: Cannot build plugin zip with dirty working tree."
ls

