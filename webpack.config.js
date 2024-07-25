const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const WooCommerceDependencyExtractionWebpackPlugin = require('@woocommerce/dependency-extraction-webpack-plugin');
const path = require('path');

const wcDepMap = {
	'@woocommerce/blocks-registry': ['wc', 'wcBlocksRegistry'],
	'@woocommerce/settings'       : ['wc', 'wcSettings']
};

const wcHandleMap = {
	'@woocommerce/blocks-registry': 'wc-blocks-registry',
	'@woocommerce/settings'       : 'wc-settings'
};

const requestToExternal = (request) => {
	if (wcDepMap[request]) {
		return wcDepMap[request];
	}
};

const requestToHandle = (request) => {
	if (wcHandleMap[request]) {
		return wcHandleMap[request];
	}
};

// Export configuration.
module.exports = {
	...defaultConfig,
	entry: {
		'payplug': '/resources/js/frontend/wc-payplug-blocks.js',
		'bancontact': '/resources/js/frontend/wc-payplug-bancontact-blocks.js',
		'american_express': '/resources/js/frontend/wc-payplug-american_express-blocks.js',
		'satispay': '/resources/js/frontend/wc-payplug-satispay-blocks.js',
		'sofort': '/resources/js/frontend/wc-payplug-sofort-blocks.js',
		'oney_x3_with_fees': '/resources/js/frontend/wc-payplug-oney_x3_with_fees-blocks.js',
	},
	output: {
		path: path.resolve( __dirname, 'assets/js/blocks/' ),
		filename: 'wc-payplug-[name]-blocks.js',
	},
	plugins: [
		...defaultConfig.plugins.filter(
			(plugin) =>
				plugin.constructor.name !== 'DependencyExtractionWebpackPlugin'
		),
		new WooCommerceDependencyExtractionWebpackPlugin({
			requestToExternal,
			requestToHandle
		})
	]
};
