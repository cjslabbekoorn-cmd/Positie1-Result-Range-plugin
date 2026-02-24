<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Lightweight GitHub Releases updater (no external library).
 *
 * - Works with public repos out of the box.
 * - For private repos you can define P1_GITHUB_TOKEN in wp-config.php.
 *
 * Expects a release asset like: results-range-wpml-{version}.zip
 */
class RRW_GitHub_Updater {

    const TRANSIENT_KEY = 'p1_rrw_gh_release';

    public static function init() : void {
        add_filter( 'pre_set_site_transient_update_plugins', [ __CLASS__, 'inject_update' ] );
        add_filter( 'plugins_api', [ __CLASS__, 'plugins_api' ], 10, 3 );
    }

    protected static function plugin_basename() : string {
        return plugin_basename( RRW_PATH . 'results-range-wpml.php' );
    }

    protected static function slug() : string {
        return 'results-range-wpml';
    }

    protected static function repo() : string {
        return (string) RRW_GH_REPO;
    }

    protected static function github_headers() : array {
        $headers = [
            'Accept'     => 'application/vnd.github+json',
            'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url( '/' ),
        ];

        // Optional: token for private repos.
        if ( defined( 'P1_GITHUB_TOKEN' ) && P1_GITHUB_TOKEN ) {
            $headers['Authorization'] = 'Bearer ' . P1_GITHUB_TOKEN;
        }

        return $headers;
    }

    protected static function fetch_latest_release() {
        $cached = get_site_transient( self::TRANSIENT_KEY );
        if ( is_array( $cached ) ) {
            return $cached;
        }

        $url = 'https://api.github.com/repos/' . rawurlencode( self::repo() ) . '/releases/latest';

        $res = wp_remote_get( $url, [
            'timeout' => 12,
            'headers' => self::github_headers(),
        ] );

        if ( is_wp_error( $res ) ) {
            return null;
        }

        $code = (int) wp_remote_retrieve_response_code( $res );
        if ( $code < 200 || $code >= 300 ) {
            return null;
        }

        $data = json_decode( (string) wp_remote_retrieve_body( $res ), true );
        if ( ! is_array( $data ) || empty( $data['tag_name'] ) ) {
            return null;
        }

        // Cache for 10 minutes.
        set_site_transient( self::TRANSIENT_KEY, $data, 10 * MINUTE_IN_SECONDS );

        return $data;
    }

    protected static function normalize_version( string $tag ) : string {
        $tag = trim( $tag );
        return ltrim( $tag, 'vV' );
    }

    protected static function find_asset_url( array $release ) : ?string {
        if ( empty( $release['assets'] ) || ! is_array( $release['assets'] ) ) {
            return null;
        }

        $prefix = (string) RRW_GH_ASSET_PREFIX;

        foreach ( $release['assets'] as $asset ) {
            if ( empty( $asset['name'] ) || empty( $asset['browser_download_url'] ) ) continue;

            $name = (string) $asset['name'];
            if ( strpos( $name, $prefix ) === 0 && substr( $name, -4 ) === '.zip' ) {
                return (string) $asset['browser_download_url'];
            }
        }

        return null;
    }

    public static function inject_update( $transient ) {
        if ( ! is_object( $transient ) ) {
            return $transient;
        }

        $release = self::fetch_latest_release();
        if ( ! $release ) {
            return $transient;
        }

        $new_version = self::normalize_version( (string) $release['tag_name'] );

        if ( version_compare( $new_version, RRW_VERSION, '<=' ) ) {
            return $transient;
        }

        $package = self::find_asset_url( $release );
        if ( ! $package && ! empty( $release['zipball_url'] ) ) {
            // Fallback to zipball (less ideal, but avoids hard failure)
            $package = (string) $release['zipball_url'];
        }

        if ( ! $package ) {
            return $transient;
        }

        $item = (object) [
            'slug'        => self::slug(),
            'plugin'      => self::plugin_basename(),
            'new_version' => $new_version,
            'url'         => 'https://github.com/' . self::repo(),
            'package'     => $package,
        ];

        $transient->response[ self::plugin_basename() ] = $item;

        return $transient;
    }

    public static function plugins_api( $result, $action, $args ) {
        if ( $action !== 'plugin_information' ) return $result;
        if ( empty( $args->slug ) || $args->slug !== self::slug() ) return $result;

        $release = self::fetch_latest_release();

        $info = new stdClass();
        $info->name          = 'Results Range WPML';
        $info->slug          = self::slug();
        $info->version       = $release ? self::normalize_version( (string) $release['tag_name'] ) : RRW_VERSION;
        $info->author        = 'Positie1';
        $info->homepage      = 'https://github.com/' . self::repo();
        $info->download_link = $release ? ( self::find_asset_url( $release ) ?: '' ) : '';
        $info->sections      = [
            'description' => __( 'Elementor widget that outputs a results range (start–end of total) based on the WordPress main query, with WPML String Translation support.', 'results-range-wpml' ),
        ];

        if ( $release && ! empty( $release['body'] ) ) {
            $info->sections['changelog'] = wp_kses_post( (string) $release['body'] );
        }

        return $info;
    }
}
