<?php
/*
Plugin Name:        WP Contest
Plugin URI:         http://genero.fi
Description:        ...
Version:            0.2.0
Author:             Genero
Author URI:         http://genero.fi/
License:            MIT License
License URI:        http://opensource.org/licenses/MIT
*/

use GeneroWP\Contest\Plugin;

defined('ABSPATH') or die();

if (file_exists($composer = __DIR__ . '/vendor/autoload.php')) {
    require_once $composer;
}

Plugin::getInstance();
