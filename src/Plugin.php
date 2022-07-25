<?php

namespace GeneroWP\Contest;

use GeneroWP\Contest\Rest\Rate;
use PostTypes\PostType;

class Plugin
{
    public $plugin_path;
    public $plugin_url;

    protected static Plugin $instance;

    public static function getInstance(): self
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct()
    {
        $this->plugin_path = plugin_dir_path(__FILE__);
        $this->plugin_url = plugin_dir_url(__FILE__);

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
        wp_register_script(
            'wp-contest/js',
            $this->plugin_url . 'dist/main.js',
            ['jquery'],
            filemtime($this->plugin_path . 'dist/main.js'),
            true
        );

        wp_register_style(
            'wp-contest/css',
            $this->plugin_url . 'dist/main.css',
            [],
            filemtime($this->plugin_path . 'dist/main.css'),
            true
        );
    }

    public function enqueueAssets(): void
    {
        wp_enqueue_script('wp-contest/js');
        wp_enqueue_style('wp-contest/css');
    }
}
