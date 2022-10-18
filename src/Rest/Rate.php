<?php

namespace GeneroWP\Contest\Rest;

use GeneroWP\Contest\Contestant;
use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Response;
use WP_REST_Request;

class Rate extends WP_REST_Controller
{
    protected $namespace;

    const TRANSIENT_MODIFIED = 'wp_contest_all_modified';

    public function __construct()
    {
        $this->namespace = 'wp-contest/v1';
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        register_rest_route($this->namespace, '/votes', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [$this, 'getAllVotes'],
        ]);

        register_rest_route($this->namespace, '/vote/(?P<id>\d+)', [
            [
                'methods' => [WP_REST_Server::CREATABLE],
                'permission_callback' => '__return_true',
                'callback' => [$this, 'addVote'],
            ],
            [
                'methods' => [WP_REST_Server::READABLE],
                'permission_callback' => '__return_true',
                'callback' => [$this, 'getVotes'],
            ],
            [
                'methods' => [WP_REST_Server::DELETABLE],
                'permission_callback' => '__return_true',
                'callback' => [$this, 'removeVote'],
            ],
        ]);
    }

    public function getAllVotes(): WP_REST_Response
    {
        $lastModified = (int) get_transient(self::TRANSIENT_MODIFIED);
        if (!$this->hasHeaderModifiedSince($lastModified)) {
            header('HTTP/1.0 304 Not Modified');
            exit;
        }

        $posts = get_posts([
            'post_type' => 'contestant',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
        ]);

        $result = [];
        foreach ($posts as $postId) {
            $contestant = new Contestant($postId);
            $result[] = [
                'id' => $postId,
                'rating' => $contestant->getTotalRating(),
            ];
        }

        return new WP_REST_Response([
            'ratings' => $result,
        ]);
    }

    public function addVote(WP_REST_Request $request): WP_REST_Response
    {
        $contestant = new Contestant($request['id']);
        if ($contestant->hasRated()) {
            return new WP_REST_Response(null, 400);
        }

        $contestant->addRating(1);
        $this->clearPageCache($request->get_header('referer'));
        $this->onUpdate();

        return new WP_REST_Response([
            'rating' => $contestant->getTotalRating(),
        ], 201);
    }

    public function removeVote(WP_REST_Request $request): WP_REST_Response
    {
        $contestant = new Contestant($request['id']);
        if (!$contestant->hasRated()) {
            return new WP_REST_Response(null, 404);
        }

        $contestant->removeRating();
        $this->clearPageCache($request->get_header('referer'));
        $this->onUpdate();

        return new WP_REST_Response([
            'rating' => $contestant->getTotalRating(),
        ], 200);
    }

    public function getVotes(WP_REST_Request $request): WP_REST_Response
    {
        $contestant = new Contestant($request['id']);

        return new WP_REST_Response([
            'rating' => $contestant->getTotalRating(),
        ], 200);
    }

    protected function onUpdate(): void
    {
        set_transient(self::TRANSIENT_MODIFIED, time());
    }

    /**
     * Clear the page cache of the referring page.
     */
    protected function clearPageCache(string $referer): bool
    {
        $refererHost = parse_url($referer, PHP_URL_HOST);
        $serverHost = $_SERVER['HTTP_HOST'];
        if ($refererHost !== $serverHost) {
            return false;
        }

        if (function_exists('wpsc_delete_url_cache')) {
            return wpsc_delete_url_cache($referer);
        }

        if (defined('KINSTAMU_DISABLE_AUTOPURGE') && KINSTAMU_DISABLE_AUTOPURGE === true) {
            return false;
        }

        if (defined('KINSTAMU_VERSION')) {
            $purgeRequest = apply_filters('KinstaCache/purgeThrottled', [
                'single|rating' => str_replace(['http://', 'https://'], '', $referer),
            ]);
            $response = wp_remote_post('https://localhost/kinsta-clear-cache/v2/immediate', [
                'sslverify' => false,
                'timeout' => 5,
                'body' => $purgeRequest,
            ]);

            error_log('Clearing: ' . $referer);

            return !is_wp_error($response);
        }

        return false;
    }

    protected function hasHeaderModifiedSince(int $lastModified): bool
    {
        if (!isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
            return true;
        }

        $modifiedSince = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']);
        if ($modifiedSince > $lastModified) {
            return false;
        }
        return true;
    }

}
