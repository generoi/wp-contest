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

    public function __construct()
    {
        $this->namespace = 'wp-contest/v1';
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes()
    {
        register_rest_route($this->namespace, '/vote/(?P<id>\d+)', [
            [
                'methods' => [WP_REST_Server::CREATABLE],
                'callback' => [$this, 'addVote'],
            ],
            [
                'methods' => [WP_REST_Server::READABLE],
                'callback' => [$this, 'getVotes'],
            ],
            [
                'methods' => [WP_REST_Server::DELETABLE],
                'callback' => [$this, 'removeVote'],
            ],
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

        if (!function_exists('wpsc_delete_url_cache')) {
            return false;
        }

        return wpsc_delete_url_cache($referer);
    }
}
