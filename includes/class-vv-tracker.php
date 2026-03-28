<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class VV_Tracker {

    /**
     * Track a visitor on the front end
     */
    public static function track_visitor() {
        // Don't track admin, AJAX, cron, REST, or logged-in admins
        if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || defined( 'REST_REQUEST' ) ) {
            return;
        }

        // Skip bots
        if ( self::is_bot() ) {
            return;
        }

        // Skip logged-in admins (optional)
        if ( current_user_can( 'manage_options' ) ) {
            return;
        }

        $ip = self::get_visitor_ip();
        if ( ! $ip || ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
            return;
        }

        global $wpdb;
        $table = VV_Database::get_table_name();

        // Check if IP already exists
        $existing = $wpdb->get_row(
            $wpdb->prepare( "SELECT id, visit_count FROM {$table} WHERE ip_address = %s", $ip )
        );

        $page_visited = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( $_SERVER['REQUEST_URI'] ) : '';
        $referer = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( $_SERVER['HTTP_REFERER'] ) : '';
        $user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ) : '';

        if ( $existing ) {
            // Update visit count and last visit
            $wpdb->update(
                $table,
                array(
                    'visit_count' => $existing->visit_count + 1,
                    'last_visit'  => current_time( 'mysql' ),
                    'page_visited' => $page_visited,
                    'user_agent'  => $user_agent,
                ),
                array( 'id' => $existing->id ),
                array( '%d', '%s', '%s', '%s' ),
                array( '%d' )
            );
        } else {
            // Perform reverse DNS
            $rdns = self::reverse_dns( $ip );

            // Geolocation
            $geo = self::geolocate( $ip );

            $wpdb->insert(
                $table,
                array(
                    'ip_address'   => $ip,
                    'reverse_dns'  => $rdns,
                    'city'         => $geo['city'],
                    'region'       => $geo['region'],
                    'country'      => $geo['country'],
                    'country_code' => $geo['country_code'],
                    'isp'          => $geo['isp'],
                    'org'          => $geo['org'],
                    'user_agent'   => $user_agent,
                    'page_visited' => $page_visited,
                    'referer'      => $referer,
                    'comment'      => '',
                    'visit_count'  => 1,
                    'first_visit'  => current_time( 'mysql' ),
                    'last_visit'   => current_time( 'mysql' ),
                ),
                array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
            );
        }
    }

    /**
     * Get the real visitor IP, handling proxies
     */
    public static function get_visitor_ip() {
        $headers = array(
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR',
        );

        foreach ( $headers as $header ) {
            if ( ! empty( $_SERVER[ $header ] ) ) {
                $ips = explode( ',', $_SERVER[ $header ] );
                $ip = trim( $ips[0] );
                if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                    return $ip;
                }
            }
        }

        return '';
    }

    /**
     * Perform reverse DNS lookup
     */
    public static function reverse_dns( $ip ) {
        $host = @gethostbyaddr( $ip );
        if ( $host && $host !== $ip ) {
            return sanitize_text_field( $host );
        }
        return '';
    }

    /**
     * Geolocate IP using ip-api.com (free, no key required, 45 req/min)
     */
    public static function geolocate( $ip ) {
        $default = array(
            'city'         => '',
            'region'       => '',
            'country'      => '',
            'country_code' => '',
            'isp'          => '',
            'org'          => '',
        );

        // Check transient cache first
        $cache_key = 'vv_geo_' . md5( $ip );
        $cached = get_transient( $cache_key );
        if ( $cached !== false ) {
            return $cached;
        }

        $response = wp_remote_get(
            "http://ip-api.com/json/{$ip}?fields=status,city,regionName,country,countryCode,isp,org",
            array( 'timeout' => 5 )
        );

        if ( is_wp_error( $response ) ) {
            return $default;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! $body || ( isset( $body['status'] ) && $body['status'] === 'fail' ) ) {
            return $default;
        }

        $result = array(
            'city'         => sanitize_text_field( $body['city'] ?? '' ),
            'region'       => sanitize_text_field( $body['regionName'] ?? '' ),
            'country'      => sanitize_text_field( $body['country'] ?? '' ),
            'country_code' => sanitize_text_field( $body['countryCode'] ?? '' ),
            'isp'          => sanitize_text_field( $body['isp'] ?? '' ),
            'org'          => sanitize_text_field( $body['org'] ?? '' ),
        );

        // Cache for 24 hours
        set_transient( $cache_key, $result, DAY_IN_SECONDS );

        return $result;
    }

    /**
     * Simple bot detection
     */
    private static function is_bot() {
        if ( empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
            return true;
        }

        $ua = strtolower( $_SERVER['HTTP_USER_AGENT'] );
        $bots = array(
            'bot', 'crawl', 'spider', 'slurp', 'mediapartners',
            'wget', 'curl', 'python', 'java', 'libwww', 'lwp-trivial',
            'httpclient', 'phpcrawl', 'msnbot', 'bingbot', 'googlebot',
            'yandex', 'baidu', 'duckduckbot', 'semrush', 'ahrefs',
            'dotbot', 'rogerbot', 'facebookexternalhit', 'twitterbot',
            'linkedinbot', 'whatsapp', 'telegrambot', 'applebot',
            'pingdom', 'uptimerobot', 'monitoring', 'headless',
        );

        foreach ( $bots as $bot ) {
            if ( strpos( $ua, $bot ) !== false ) {
                return true;
            }
        }

        return false;
    }
}
