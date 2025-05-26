const path = require("path");
const mix = require("laravel-mix");
require("dotenv").config();

// The theme name should be located in your .env file
const THEME_NAME = "chuneycutt-com",
    PROXY = process.env.PROXY,
    THEME_DIRECTORY = `./`,
    ASSETS_DIR = `./assets`,
    BUILD_DIR = `${ASSETS_DIR}/build`,
    BUILD_MESSAGE = `Now building the ${THEME_NAME} theme...`;

// Set the path to where all public assets should be compiled to.
mix
    .webpackConfig({
        devtool: "source-map",
        output: {
            chunkFilename: "chunks/[name].js?ver=[chunkhash]?id=[hash]",
            // [hash] is a "unique hash generated for every build"
            // [chunkhash] is "based on each chunks' content"
            publicPath: "/wp-content/themes/chuneycutt-com/assets/build/",
        },
        stats: {
            children: true,
        },
    })
    .setPublicPath(
        path.normalize("./assets/build")
    )
    .js(`${ASSETS_DIR}/js/main.js`, "main.js");

// Make jquery module available
mix.autoload({
    jquery: ["$", "window.jQuery"],
});

// Compile CSS
mix
    .sass(`./${ASSETS_DIR}/scss/style.scss`, "style.css")
    .sourceMaps(true, "source-map");

// Mix Options

mix.options({
    processCssUrls: false,
});

// Browsersync Settings
mix.browserSync({
    proxy: 'https://chuneycutt.test',
    files: [
        `${BUILD_DIR}/style.css`,
        `${BUILD_DIR}/main.js`,
        `./**/*.+(html|php)`,
    ],
    notify: {
        styles: {
            top: "auto",
            bottom: "0",
        },
    },
});

// Minify assets in production
console.log(BUILD_MESSAGE);
mix.minify([`${BUILD_DIR}/style.css`, `${BUILD_DIR}/main.js`]).version([]);

