<?php
/**
 * Placeholder images: local video thumbnails, downloaded only by WP-Cron,
 * and bundled generic images (map, video, audio, post, other embeds).
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 * SPDX-FileCopyrightText: 2026 Marcus Quinn
 * Additional terms (GPL-3.0 section 7(b)): ATTRIBUTION.txt
 * Provenance: wpas-647035161c3dc0ef. Copies, even of part, keep these lines.
 *
 * @package SEOProCookieConsent
 */

if (!defined('ABSPATH')) {
    exit;
}

final class SEOProCookieConsent_Thumbnails {

    const HOOK = 'seoprocookieconsent_thumbnail';
    const PREFIX = 'seoprocookieconsent_thumbnail_';
    const DIRECTORY = 'seoprocookieconsent-thumbnails';

    /**
     * Bundled image kind by service (assets/images/{kind}-placeholder.svg).
     * Other held services, including custom ones, get the generic embed.
     */
    const KINDS = array(
        'google-maps'     => 'map',
        'youtube'         => 'video',
        'vimeo'           => 'video',
        'spotify'         => 'audio',
        'soundcloud'      => 'audio',
        'x-embeds'        => 'post',
        'instagram'       => 'post',
        'facebook-embeds' => 'post',
    );

    /**
     * Key in bundled() for services without their own kind.
     */
    const FALLBACK = '*';

    /**
     * Bundled placeholder images by service: generic pictures shipped with
     * the plugin, so nothing reaches the service before consent.
     *
     * @return array<string, string> Service ID (or FALLBACK) => image URL.
     */
    public static function bundled() {
        $images = array();
        foreach (self::KINDS as $service => $kind) {
            $images[$service] = self::image($kind);
        }
        $images[self::FALLBACK] = self::image('embed');
        return $images;
    }

    /**
     * URL of one bundled placeholder image.
     *
     * @param string $kind map, video, audio, post or embed.
     * @return string
     */
    private static function image($kind) {
        return SEOPROCOOKIECONSENT_URL . 'assets/images/' . $kind . '-placeholder.svg';
    }

    /**
     * The image kind for a service.
     *
     * @param string $service Service ID.
     * @return string
     */
    private static function kind($service) {
        $kinds = self::KINDS;
        return isset($kinds[$service]) ? $kinds[$service] : 'embed';
    }

    /**
     * The placeholder image: a local video thumbnail if cached, else the
     * bundled image for the service's kind.
     *
     * @param string $service Service ID.
     * @param string $src Embed URL.
     * @return string
     */
    public static function url($service, $src) {
        $cached = self::cached($service, $src);
        return '' !== $cached ? $cached : self::image(self::kind($service));
    }

    /**
     * Read a local video thumbnail or queue it, never contacting a video
     * service.
     *
     * @param string $service Service ID.
     * @param string $src Embed URL.
     * @return string
     */
    private static function cached($service, $src) {
        $id = 'video' === self::kind($service) ? self::video_id($service, $src) : '';
        if ('' === $id) {
            return '';
        }
        $key    = self::PREFIX . $service . '_' . $id;
        $record = get_option($key, array());
        $upload = wp_upload_dir(null, false);
        $file   = $service . '-' . $id . '.jpg';
        if (is_array($record) && !empty($record['ready']) && is_file($upload['basedir'] . '/' . self::DIRECTORY . '/' . $file)) {
            // Uploads mapped to an external CDN must not leak a pre-consent visit.
            $url = $upload['baseurl'] . '/' . self::DIRECTORY . '/' . $file;
            return wp_parse_url($url, PHP_URL_HOST) === wp_parse_url(home_url(), PHP_URL_HOST) ? $url : '';
        }
        if ((!is_array($record) || empty($record['retry']) || $record['retry'] < time()) && !wp_next_scheduled(self::HOOK, array($service, $id))) {
            wp_schedule_single_event(time() + 10, self::HOOK, array($service, $id));
        }
        return '';
    }

    /**
     * The video's ID in a YouTube or Vimeo player address.
     *
     * @param string $service Service ID.
     * @param string $src Embed URL.
     * @return string The ID, or "".
     */
    private static function video_id($service, $src) {
        $hosts = array(
            'youtube' => array('www.youtube.com', 'youtube.com', 'www.youtube-nocookie.com', 'youtube-nocookie.com'),
            'vimeo'   => array('player.vimeo.com'),
        );
        $paths = array(
            'youtube' => '#^/embed/([A-Za-z0-9_-]{11})/?$#D',
            'vimeo'   => '#^/video/(\d{1,12})/?$#D',
        );
        $parts = wp_parse_url(0 === strpos($src, '//') ? 'https:' . $src : $src);
        if (!isset($hosts[$service]) || !is_array($parts) || !isset($parts['host'], $parts['path'])
            || !in_array(strtolower($parts['host']), $hosts[$service], true)
            || !preg_match($paths[$service], $parts['path'], $match)) {
            return '';
        }
        return $match[1];
    }

    /**
     * Download a bounded JPEG in cron; failures are retried after a day.
     *
     * @param string $service Service ID.
     * @param string $id Video ID.
     */
    public static function fetch($service, $id) {
        if (!wp_doing_cron() || !SEOProCookieConsent_Consent::enabled()
            || !in_array($service, array('youtube', 'vimeo'), true)
            || !preg_match('youtube' === $service ? '/^[A-Za-z0-9_-]{11}$/D' : '/^\d{1,12}$/D', $id)) {
            return;
        }
        $key    = self::PREFIX . $service . '_' . $id;
        $record = get_option($key, array());
        if (is_array($record) && !empty($record['retry']) && $record['retry'] > time()) {
            return;
        }
        // Persist before I/O: concurrent requests and failures cannot hammer providers.
        update_option($key, array('retry' => time() + DAY_IN_SECONDS), false);
        $url = 'vimeo' === $service ? self::vimeo_image($id) : 'https://i.ytimg.com/vi/' . $id . '/hqdefault.jpg';
        if ('' === $url) {
            return;
        }
        $body   = self::request($url, 2 * MB_IN_BYTES);
        $image  = '' !== $body ? @getimagesizefromstring($body) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- malformed remote images are an expected cache miss.
        $upload = wp_upload_dir(null, false);
        $dir    = $upload['basedir'] . '/' . self::DIRECTORY;
        if (false === $image || 'image/jpeg' !== $image['mime'] || $upload['error'] || !wp_mkdir_p($dir)) {
            return;
        }
        $file = $dir . '/' . $service . '-' . $id . '.jpg';
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- bounded, validated JPEG in the site's uploads directory; no credentials required.
        if (false !== file_put_contents($file, $body, LOCK_EX)) {
            update_option($key, array('ready' => true), false);
        }
    }

    /**
     * A Vimeo video's thumbnail address, from its public API.
     *
     * @param string $id Video ID.
     * @return string The address, or "".
     */
    private static function vimeo_image($id) {
        $data = json_decode(self::request('https://vimeo.com/api/v2/video/' . $id . '.json', 65536), true);
        $url  = is_array($data) && isset($data[0]['thumbnail_large']) ? $data[0]['thumbnail_large'] : '';
        // Only Vimeo's image CDN, never an arbitrary oEmbed/API-supplied host.
        if (!is_string($url) || !preg_match('#^https://i\.vimeocdn\.com/video/[A-Za-z0-9_./-]+(?:\?[A-Za-z0-9_%=&.-]+)?$#D', $url)) {
            return '';
        }
        return $url;
    }

    /**
     * Fetch without redirects, cookies, or visitor data, only from fixed providers.
     *
     * @param string $url Provider URL.
     * @param int    $limit Maximum response size.
     * @return string
     */
    private static function request($url, $limit) {
        $response = wp_remote_get($url, array('timeout' => 3, 'redirection' => 0, 'reject_unsafe_urls' => true, 'limit_response_size' => $limit));
        return !is_wp_error($response) && 200 === wp_remote_retrieve_response_code($response) ? wp_remote_retrieve_body($response) : '';
    }
}
