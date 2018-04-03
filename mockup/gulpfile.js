// https://hackernoon.com/how-to-automate-all-the-things-with-gulp-b21a3fc96885

var gulp = require('gulp');
var sass = require('gulp-sass');
var webserver = require('gulp-webserver');

gulp.task('sass', function(){
  return gulp.src('scss/*.scss')
    .pipe(sass()) // Using gulp-sass
    .pipe(gulp.dest('../data'))
});

gulp.task('serve', ['inject'], function () {
  return gulp.src(paths.tmp)
    .pipe(webserver({
      port: 3000,
      livereload: true
    }));
});