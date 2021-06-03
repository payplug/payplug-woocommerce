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

# Make sure there are no changes in the working tree.  Release builds should be
# traceable to a particular commit and reliably reproducible.  (This is not
# totally true at the moment because we download nightly vendor scripts).
changed=
if ! git diff --exit-code > /dev/null; then
	changed="file(s) modified"
elif ! git diff --cached --exit-code > /dev/null; then
	changed="file(s) staged"
fi
if [ ! -z "$changed" ]; then
	git status
	echo "ERROR: Cannot build plugin zip with dirty working tree."
	echo "       Commit your changes and try again."
	exit 1
fi

branch="$(git rev-parse --abbrev-ref HEAD)"
if [ "$branch" != 'master' ]; then
	echo "WARNING: You should probably be running this script against the"
	echo "         'master' branch (current: '$branch')"
	echo
	sleep 2
fi

# Remove ignored files to reset repository to pristine condition. Previous test
# ensures that changed files abort the plugin build.
status "Cleaning working directory..."
git clean -xdf

# Run the build
status "Installing dependencies..."
npm install
composer install --prefer-dist --no-dev -o
status "Generating build..."
npm run build:css

# Remove any existing zip file
rm -f payplug-woocommerce.zip

# Generate the plugin zip file
status "Creating archive..."
cd ../
zip -r payplug-woocommerce.zip \
	payplug-woocommerce* \
	--exclude=payplug-woocommerce/.git* \
	--exclude=payplug-woocommerce/.distignore \
	--exclude=payplug-woocommerce/.editorconfig \
	--exclude=payplug-woocommerce/.gitattributes \
	--exclude=payplug-woocommerce/.gitignore \
	--exclude=payplug-woocommerce/.travis.yml \
	--exclude=payplug-woocommerce/composer.* \
	--exclude=payplug-woocommerce/package.json \
	--exclude=payplug-woocommerce/package-lock.json \
	--exclude=payplug-woocommerce/phpcs.xml.dist \
	--exclude=payplug-woocommerce/phpunit.xml.dist \
	--exclude=payplug-woocommerce/bin* \
	--exclude=payplug-woocommerce/node_modules* \
	--exclude=payplug-woocommerce/test* \
	--exclude=payplug-woocommerce/vendor/composer/installers* \
	--exclude=payplug-woocommerce/assets/scss*

status "Done."
