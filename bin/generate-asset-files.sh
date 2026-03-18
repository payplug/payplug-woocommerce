#!/bin/bash

# Exit if any command fails
set -e

echo "get version from package.json..."
version=$(grep '"version"' ./package.json | head -1 | awk -F: '{ print $2 }' | sed 's/[", ]//g')


echo ${version}

# Run the build
echo "Installing dependencies..."
npm ci

echo "Generating dist dir..."
rm -rf ./assets/dist
mkdir ./assets/dist ./assets/dist/js ./assets/dist/css ./assets/dist/img

echo "Copying assets..."
cp ./node_modules/payplug-ui-plugins-bo/js/app.js ./assets/dist/js/app-${version}.js
cp ./node_modules/payplug-ui-plugins-bo/js/chunk-vendors.js ./assets/dist/js/chunk-vendors-${version}.js
cp ./node_modules/payplug-ui-plugins-bo/css/app.css ./assets/dist/css/app-${version}.css
cp ./node_modules/payplug-ui-plugins-bo/img/* ./assets/dist/img/

echo "Done."
