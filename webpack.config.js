/**
 * External dependencies
 */
const path = require( 'path' );
const { CleanWebpackPlugin } = require( 'clean-webpack-plugin' );
const MiniCssExtractPlugin = require( 'mini-css-extract-plugin' );

/**
 * WordPress dependencies
 */
const DependencyExtractionWebpackPlugin = require( '@wordpress/dependency-extraction-webpack-plugin' );
const { defaultRequestToExternal, defaultRequestToHandle } = require( '@wordpress/dependency-extraction-webpack-plugin/lib/util' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

const isProduction = process.env.NODE_ENV === 'production';

module.exports = {
	...defaultConfig,
	entry: {
		'./js/dist/block-editor': './js/src/block-editor/index.js',
		'./js/dist/edit-block': './js/src/edit-block/index.js',
		'./css/dist/blocks.editor': './css/src/editor.scss',
		'./css/dist/edit-block': './css/src/edit-block.scss',
	},
	output: {
		path: path.resolve( __dirname ),
		filename: '[name].js',
		// Dynamic `import()` calls (notably from the icon library
		// registry in js/src/common/icons/libraries.js) produce
		// separate chunks. Park them under js/dist/ — same folder as
		// the entry bundles — so the plugin root doesn't get
		// polluted with stray icons-*.js files.
		//
		// The runtime publicPath that pairs with this is set in each
		// entry's index.js by reading document.currentScript.src and
		// stripping the "js/dist/<entry>.js" tail, leaving the plugin
		// folder URL as the public path. The browser then fetches
		// each chunk from `${pluginUrl}/js/dist/[name].chunk.js`.
		chunkFilename: 'js/dist/[name].chunk.js',
	},
	watch: false,
	mode: isProduction ? 'production' : 'development',
	module: {
		rules: [
			{
				test: /\.js$/,
				exclude: /(node_modules|bower_components)/,
				use: {
					loader: 'babel-loader',
				},
			},
			{
				test: /css\/src\/[^_].*\.scss$/,
				use: [
					{
						loader: MiniCssExtractPlugin.loader,
					},
					'css-loader',
					'postcss-loader',
					{
						loader: 'sass-loader',
					},
				],
			},
		],
	},
	plugins: [
		new CleanWebpackPlugin( {
			cleanOnceBeforeBuildPatterns: [ 'js/dist', 'css/dist' ],
		} ),
		new MiniCssExtractPlugin( {
			filename: '[name].css',
		} ),
		new DependencyExtractionWebpackPlugin( {
			useDefaults: false,
			requestToHandle: ( request ) => {
				switch ( request ) {
					case '@wordpress/dom-ready':
					case '@wordpress/i18n':
					case '@wordpress/server-side-render':
					case '@wordpress/url':
						return undefined;

					default:
						return defaultRequestToHandle( request );
				}
			},
			requestToExternal: ( request ) => {
				switch ( request ) {
					case '@wordpress/dom-ready':
					case '@wordpress/i18n':
					case '@wordpress/server-side-render':
					case '@wordpress/url':
						return undefined;

					default:
						return defaultRequestToExternal( request );
				}
			},
		} ),
	],
};
