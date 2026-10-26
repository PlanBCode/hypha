# What Is Hypha

Hypha is a lightweight CMS for small to medium-sized communities and project teams, offering the possibility to
create a basic website that is easy to set up and maintain.
It is open source and doesn't rely on proprietary third party code, making it a safe tool that can be self-hosted.
Hypha is written in php.

The saying goes: "If you can't open it you don't own it." We'd like to add however: "It's no good if you have to
use a screwdriver to simply turn it on."

Hypha's core philosophy is about empowering communities by offering its members intuitive tools rather than
learning curves, all while sticking to the ethical standards embedded in open source software. For this reason
Hypha is not 'packed with features' but in stead focuses on features that will cater to the basic needs of a
community and can be understood by average users of the web. In this philosophy every button that is used by 10% of
a community but scares off 90% is a bug, rather than a feature.

## Homepage

The hypha homepage can be found at [hypha.net](http://hypha.net).

## Prerequisites

Hypha needs Apache running, with php version 8.2 or above (older versions might
also work, but any changes to the code should not require a higher PHP
version).

## Basic Installation

The easiest way to install hypha is probably to follow the instructions on
(http://hypha.net/hypha.php)[http://hypha.net/hypha.php]. This script packages a fully self-contained copy of hypha
in a single monolithic php file. If you drop this file on a server and call it from a browser it will extract all
necessary files and configure your site according to a few settings to have to enter. The script guides you through the
installation process step by step.

Your url should look like this `http://<address-of-your-apache>/index.php`

## Advanced Install Though Git

1. Pull in a git clone, either by downloading a [zip file](https://github.com/PlanBCode/hypha/archive/master.zip)
   and extracting it in your web-folder of choice, or by
   issuing a `git clone https://github.com/PlanBCode/hypha.git .` from the commandline in that folder.
2. Give the user that the web server runs as (often `www-data` or `apache`) write access to the folder `data/`
   only, e.g. `chgrp -R www-data data && chmod -R g+w data`. The installer also checks that it can write to the
   installation folder itself; grant that for the duration of the installation only (e.g. `chgrp www-data . &&
   chmod g+w .`) and take it away afterwards (`chmod g-w .`). Do not use `chmod -R 777`: it lets every local user
   and every process on the server change the code and read the data.
3. Navigate to your web-folder in a browser to open `index.php` provided by hypha, and follow instructions from there.
   Your url should look like this `http://<address-of-your-apache>/index.php`.

## Web Server Configuration

Hypha keeps all site data, including user accounts and password hashes, in `data/`, below the web root. The
`.htaccess` file routes every request for `data/` through `index.php`, which only serves the theme files. This only
works when Apache reads `.htaccess` (`AllowOverride FileInfo` or `All` for the Hypha folder) and has `mod_rewrite`
enabled. With `AllowOverride None`, `data/hypha.xml` can be downloaded by anyone.

As a second line of defence, also deny `data/` in the virtual host, except for the themes:

    <LocationMatch "^/data/(?!themes/)">
        Require all denied
    </LocationMatch>

Adjust the path when Hypha is not installed at the root of the site. Do not use `<Directory .../data>` with
`Require all denied`: Apache checks it before the rewrite in `.htaccess` runs, so it also blocks the theme
stylesheets.
