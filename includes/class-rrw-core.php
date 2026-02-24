<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RRW_Core {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_enqueue_scripts', [ $this, 'register_assets' ] );
		add_action( 'elementor/widgets/register', [ $this, 'register_elementor_widget' ] );
	}

	public function register_assets() {
		wp_register_script(
			'rrw-results-range',
			RRW_URL . 'assets/js/frontend.js',
			[],
			RRW_VERSION,
			true
		);
	}

	public function register_elementor_widget( $widgets_manager ) {
		if ( ! did_action( 'elementor/loaded' ) ) return;

		require_once RRW_PATH . 'includes/class-rrw-elementor-results-range-widget.php';
		$widgets_manager->register( new \RRW_Elementor_Results_Range_Widget() );
	}

	/**
	 * WPML helpers (safe no-ops when WPML String Translation isn't active).
	 */
	public static function wpml_register( $name, $value ) {
		if ( ! is_string( $name ) || $name === '' ) return;
		if ( ! is_string( $value ) ) $value = (string) $value;

		if ( has_action( 'wpml_register_single_string' ) ) {
			do_action( 'wpml_register_single_string', 'results-range-wpml', $name, $value );
		}
	}

	public static function wpml_translate( $name, $value ) {
		if ( ! is_string( $value ) ) $value = (string) $value;

		$translated = apply_filters( 'wpml_translate_single_string', $value, 'results-range-wpml', $name );
		return is_string( $translated ) ? $translated : $value;
	}
}
