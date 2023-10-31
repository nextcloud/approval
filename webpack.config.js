const path = require('path')
const webpackConfig = require('@nextcloud/webpack-vue-config')
const ESLintPlugin = require('eslint-webpack-plugin')
const StyleLintPlugin = require('stylelint-webpack-plugin')

const buildMode = process.env.NODE_ENV
const isDev = buildMode === 'development'
webpackConfig.devtool = isDev ? 'cheap-source-map' : 'source-map'

webpackConfig.stats = {
	colors: true,
	modules: false,
}

const appId = 'approval'
webpackConfig.entry = {
	adminSettings: { import: path.join(__dirname, 'src', 'adminSettings.js'), filename: appId + '-adminSettings.js' },
	filesplugin: { import: path.join(__dirname, 'src', 'filesplugin.js'), filename: appId + '-filesplugin.js' },
	dashboardPending: { import: path.join(__dirname, 'src', 'dashboardPending.js'), filename: appId + '-dashboardPending.js' },
	init: { import: path.join(__dirname, 'src', 'init.js'), filename: appId + '-init.js' },
}

webpackConfig.plugins.push(
	new ESLintPlugin({
		extensions: ['js', 'vue'],
		files: 'src',
		failOnError: !isDev,
	})
)
webpackConfig.plugins.push(
	new StyleLintPlugin({
		files: 'src/**/*.{css,scss,vue}',
		failOnError: !isDev,
	}),
)

webpackConfig.module.rules.push({
	test: /\.svg$/i,
	type: 'asset/source',
})

module.exports = webpackConfig
