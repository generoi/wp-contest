<?php

namespace GeneroWP\Contest;

use GeneroWP\Common\Singleton;

class Contestant
{
    public $postId;
    public $ip;

    const META_RATING = 'contest_rating';
    const META_RATING_IPS = 'contest_rating_ips';

    public function __construct(int $postId, string $ip = null)
    {
        $this->postId = $postId;
        $this->ip = $ip ?: $this->getUserIp() ?: 'unknown';
    }

    public function getTotalRating(): int
    {
        return get_post_meta($this->postId, self::META_RATING, true) ?: 0;
    }

    public function getUserRating(): int
    {
        $ratings = get_post_meta($this->postId, self::META_RATING_IPS, true) ?: [];
        return $ratings[$this->ip] ?? 0;
    }

    public function hasRated(): bool
    {
        return $this->getUserRating() > 0;
    }

    public function addRating(int $rating): int
    {
        $newRating = $this->getTotalRating() + $rating;
        // Race condition but we dont care for now
        $this->updateRating($newRating);
        $this->recordUserRating($rating);

        return $newRating;
    }

    public function removeRating(): int
    {
        $newRating = $this->getTotalRating() - $this->getUserRating();
        // Race condition but we dont care for now
        $this->updateRating($newRating);
        $this->forgetUserRating();
        return $newRating;
    }

    protected function recordUserRating(int $rating): void
    {
        $allRatings = $this->getAllRatings();
        update_post_meta($this->postId, self::META_RATING_IPS, array_merge($allRatings, [
            $this->ip => $rating,
        ]));
    }

    protected function forgetUserRating(): void
    {
        $allRatings = $this->getAllRatings();
        update_post_meta($this->postId, self::META_RATING_IPS, array_diff_key($allRatings, [
            $this->ip => 0,
        ]));
    }

    protected function getAllRatings(): array
    {
        return get_post_meta($this->postId, self::META_RATING_IPS, true) ?: [];
    }

    protected function updateRating(int $rating): void
    {
        update_post_meta($this->postId, self::META_RATING, $rating);
    }

    protected function getUserIp(): ?string
    {
        foreach ([
            'REMOTE_ADDR',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED'
        ] as $key) {
            if (!isset($_SERVER[$key])) {
                continue;
            }

            $ips = array_map('trim', explode(',', $_SERVER[$key]));
            foreach ($ips as $ip) {
                if ($this->isValidIp($ip)) {
                    return wp_privacy_anonymize_ip($ip);
                }
            }
        }

        return null;
    }

    protected function isValidIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }
}
