const fs = require('fs');
const config = JSON.parse(fs.readFileSync('./gulp-config.json').toString('utf8'));
const path = require('path');
const required = {};

require('dotenv-safe').config({
  allowEmptyValues: true,
});

config.require.forEach((v, k) => {
  required[k] = require(v);
});

const {src, dest, watch, parallel, series} = require('gulp');
const ap = require('autoprefixer');
const bs = require('browser-sync').create();
const cc = require('gulp-cached');
const el = require('gulp-eslint');
const gb = require('node-sass-glob-importer');
const ms = require('merge-stream')();
const rn = require('gulp-rename');
const sm = require('gulp-sourcemaps');
const sl = require('gulp-stylelint');
const ss = require('gulp-sass');
const pb = require('gulp-plumber');
const ps = require('gulp-postcss');
const uy = require('gulp-uglify');

const css = () => {
  config.paths.forEach((elem) => {
    ms.add(src(path.join(elem, 'scss/**/*.scss'))
      .pipe(pb())
      .pipe(sm.init())
      .pipe(ss({
        outputStyle: 'compressed',
        importer: gb(),
      }).on('error', ss.logError))
      .pipe(ps([ap({
        grid: true,
      })]))
      .pipe(sm.write())
      .pipe(dest(path.join(elem, 'css/')))
      .pipe(bs.reload({stream: true})));
  });

  return ms;
};

const js = () => {
  config.paths.forEach((elem) => {
    ms.add(src(path.join(elem, 'js/**/*.js'))
      .pipe(pb())
      .pipe(sm.init())
      .pipe(uy())
      .pipe(rn({suffix: '.min'}))
      .pipe(sm.write())
      .pipe(dest(path.join(elem, 'js/')))
      .pipe(bs.reload()));
  });

  return ms;
};

const lint = () => {
  config.paths.forEach((elem) => {
    ms.add(src(path.join(elem, 'scss/**/*.scss'))
      .pipe(cc('stylelint'))
      .pipe(sl({
        failAfterError: true,
        reportOutputDir: './reports/lint',
        reporters: [
          {formatter: 'string', console: true}
        ]
      })));
    ms.add(src(path.join(elem, 'js/**/*.js'))
      .pipe(el())
      .pipe(el.format('compact'))
      .pipe(el.failAfterError()));
  });

  return ms;
};

const serve = (done) => {
  bs.init({
    https: config.browserSync.https,
    proxy: {
      target: process.env.DRUSH_OPTIONS_URI,
      proxyReq: [
        proxyReq => {
          // Disable Drupal page cache.
          proxyReq.setHeader('Cache-Control', 'no-cache, must-revalidate, max-age=0');
          proxyReq.setHeader('Age', '0');
        }
      ]
    },
    notify: config.browserSync.notify || true,
    open: config.browserSync.open || true
  });
  done();
};

const watcher = () => {
  config.paths.forEach((elem) => {
    watch(
      [path.join(elem, 'scss/**/*.scss')],
      {ignoreInitial: false},
      series(css)
    );
  });
  config.paths.forEach((elem) => {
    watch(
      [path.join(elem, 'js/**/*.js')],
      {ignoreInitial: false},
      series(js)
    );
  });
};

exports.lint = lint;
exports["watch:silent"] = watcher;
exports["watch:serve"] = parallel(serve, watcher);
exports["build:dev"] = series(css, js);
exports["build:prod"] = series(lint, css, js);
exports.default = exports["build:prod"];
