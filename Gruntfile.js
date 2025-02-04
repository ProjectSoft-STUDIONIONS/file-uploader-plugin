module.exports = function(grunt) {
	var fs = require('fs'),
		chalk = require('chalk'),
		PACK = grunt.file.readJSON('package.json');
	
	var gc = {
		fontvers: `${PACK.version}`,
		default: [
			"clean:all",
			"concat",
			"uglify",
			"less",
			"autoprefixer",
			"group_css_media_queries",
			"replace",
			"cssmin",
			"copy",
		]
	};
	NpmImportPlugin = require("less-plugin-npm-import");
	require('load-grunt-tasks')(grunt);
	require('time-grunt')(grunt);
	grunt.initConfig({
		globalConfig : gc,
		pkg : PACK,
		clean: {
			options: {
				force: true
			},
			all: [
				'test/',
				'tests/'
			]
		},
		concat: {
			options: {
				separator: "\n",
			},
			appjs: {
				src: [
					'bower_components/jquery/dist/jquery.js',
					'bower_components/js-cookie/src/js.cookie.js',
					'bower_components/bootstrap/dist/js/bootstrap.js'

				],
				dest: 'js/appjs.js'
			},
			main: {
				src: [
					'src/js/main.js'
				],
				dest: 'js/main.js'
			},
		},
		uglify: {
			options: {
				sourceMap: false,
				compress: {
					drop_console: false
				},
				output: {
					ascii_only: true
				}
			},
			app: {
				files: [
					{
						expand: true,
						flatten : true,
						src: [
							'js/appjs.js',
							'js/main.js',
						],
						dest: 'js',
						filter: 'isFile',
						rename: function (dst, src) {
							return dst + '/' + src.replace('.js', '.min.js');
						}
					}
				]
			},
		},
		less: {
			css: {
				options : {
					compress: false,
					ieCompat: false,
					plugins: [
						new NpmImportPlugin({prefix: '~'})
					],
					modifyVars: {
						'icon-font-path': '/wp-content/plugins/file-uploader-plugin/fonts/',
					}
				},
				files : {
					'css/main.css' : [
						'src/less/main.less'
					],
				}
			},
		},
		autoprefixer:{
			options: {
				browsers: [
					"last 4 version"
				],
				cascade: true
			},
			css: {
				files: {
					'css/main.css' : [
						'css/main.css'
					],
				}
			},
		},
		group_css_media_queries: {
			group: {
				files: {
					'css/main.css': ['css/main.css'],
				}
			},
		},
		replace: {
			css: {
				options: {
					patterns: [
						{
							match: /\/\*.+?\*\//gs,
							replacement: ''
						},
						{
							match: /\r?\n\s+\r?\n/g,
							replacement: '\n'
						}
					]
				},
				files: [
					{
						expand: true,
						flatten : true,
						src: [
							'css/main.css'
						],
						dest: 'css/',
						filter: 'isFile'
					},
				]
			},
		},
		cssmin: {
			options: {
				mergeIntoShorthands: false,
				roundingPrecision: -1
			},
			minify: {
				files: {
					'css/main.min.css' : ['css/main.css'],
				}
			},
		},
		copy: {
			fonts: {
				expand: true,
				cwd: 'bower_components/bootstrap/dist/fonts',
				src: [
					'**'
				],
				dest: 'fonts/',
			},
		},
	});
	grunt.registerTask('default',	gc.default);
	grunt.registerTask('speed',	gc.speed);
};
