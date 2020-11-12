<?php
/*
Plugin Name:        WP Contest
Plugin URI:         http://genero.fi
Description:        ...
Version:            0.1.0
Author:             Genero
Author URI:         http://genero.fi/
License:            MIT License
License URI:        http://opensource.org/licenses/MIT
*/
namespace GeneroWP\Contest;

use Puc_v4_Factory;
use PostTypes\PostType;
use GeneroWP\Common\Singleton;
use GeneroWP\Common\Assets;
use GeneroWP\Contest\Rest\Rate;

if (!defined('ABSPATH')) {
    exit;
}

if (file_exists($composer = __DIR__ . '/vendor/autoload.php')) {
    require_once $composer;
}

class Plugin
{
    use Singleton;
    use Assets;

    public $version = '0.1.0';
    public $plugin_name = 'wp-contest';
    public $plugin_path;
    public $plugin_url;
    public $github_url = 'https://github.com/generoi/wp-contest';

    public function __construct()
    {
        $this->plugin_path = plugin_dir_path(__FILE__);
        $this->plugin_url = plugin_dir_url(__FILE__);

        register_deactivation_hook(__FILE__, [__CLASS__, 'deactivate']);

        Puc_v4_Factory::buildUpdateChecker($this->github_url, __FILE__, $this->plugin_name);

        add_action('plugins_loaded', [$this, 'init']);
    }

    public function init(): void
    {
        new Rate();

        $this->registerPostType();

        add_action('wp_enqueue_scripts', [$this, 'registerAssets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function registerPostType(): void
    {
        $type = new PostType('contestant', [
            'has_archive' => false,
            'show_in_rest' => true,
            'supports' => ['title'],
            'public' => true,
            'show_ui' => true, // @todo
            'show_in_menu' => true, // @todo
            'publicly_queryable' => false,
        ]);
        $type->icon('dashicons-cover-image');
        $type->columns()
            ->add(['votes' => 'Votes'])
            ->order(['votes' => 2])
            ->sortable(['votes' => ['votes', true]])
            ->populate('votes', function ($column, $post_id) {
                // for now displays the rating but should actually show vote count
                echo (new Contestant($post_id))->getTotalRating();
            });
        $type->register();
    }

    public function registerAssets(): void
    {
        $this->registerScript("{$this->plugin_name}/js", 'dist/main.js', ['jquery'], true);
        $this->registerStyle("{$this->plugin_name}/css", 'dist/main.css');
    }

    public function enqueueAssets(): void
    {
        $this->enqueueScript("{$this->plugin_name}/js");
        $this->enqueueStyle("{$this->plugin_name}/css");
    }

    public static function deactivate(): void
    {
    }
}

Plugin::getInstance();
