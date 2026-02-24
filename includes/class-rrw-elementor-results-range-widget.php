<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Elementor widget: Results Range (WPML)
 * - Calculates from WP main query
 * - Registers widget texts in WPML String Translation per widget instance (Elementor widget ID)
 * - Optional AJAX update (JetSmartFilters "Apply on change value")
 */
class RRW_Elementor_Results_Range_Widget extends \Elementor\Widget_Base {

	public function get_name() { return 'rrw_results_range'; }

	public function get_title() { return __('Results Range', 'results-range-wpml'); }

	public function get_icon() { return 'eicon-counter'; }

	public function get_categories() { return [ 'general' ]; }

	public function get_script_depends() {
		return [ 'rrw-results-range' ];
	}

	protected function register_controls() {

		$this->start_controls_section( 'section_content', [
			'label' => __( 'Content', 'results-range-wpml' ),
			'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
		] );

		$this->add_control( 'label_template', [
			'label'       => __( 'Label template', 'results-range-wpml' ),
			'type'        => \Elementor\Controls_Manager::TEXT,
			'default'     => 'Toont {start}-{end} van {total} {results_label}',
			'description' => __( 'Available: {start}, {end}, {total}, {results_label}', 'results-range-wpml' ),
		] );

		$this->add_control( 'hide_when_empty', [
			'label'        => __( 'Hide when no results', 'results-range-wpml' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'label_on'     => __( 'Yes', 'results-range-wpml' ),
			'label_off'    => __( 'No', 'results-range-wpml' ),
			'return_value' => 'yes',
			'default'      => 'yes',
		] );

		$this->add_control( 'query_id', [
			'label'       => __( 'Query ID (optional)', 'results-range-wpml' ),
			'type'        => \Elementor\Controls_Manager::TEXT,
			'default'     => '',
			'description' => __( 'Optional: used to target the correct Jet listing on pages with multiple listings (data-query-id).', 'results-range-wpml' ),
		] );

		$this->add_control( 'enable_ajax_updates', [
			'label'        => __( 'Update on filtering (AJAX)', 'results-range-wpml' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'label_on'     => __( 'Yes', 'results-range-wpml' ),
			'label_off'    => __( 'No', 'results-range-wpml' ),
			'return_value' => 'yes',
			'default'      => 'yes',
			'description'  => __( 'Recalculates the label after JetSmartFilters / JetEngine AJAX updates (e.g. Apply on change value).', 'results-range-wpml' ),
		] );

		$this->add_control( 'ajax_fallback_mode', [
			'label'       => __( 'If total is unknown after AJAX', 'results-range-wpml' ),
			'type'        => \Elementor\Controls_Manager::SELECT,
			'options'     => [
				'keep'       => __( 'Keep total and update range (best effort)', 'results-range-wpml' ),
				'omit_total' => __( 'Omit total', 'results-range-wpml' ),
				'visible'    => __( 'Use visible count as total', 'results-range-wpml' ),
			],
			'default'     => 'keep',
			'condition'   => [ 'enable_ajax_updates' => 'yes' ],
		] );

		$this->add_control( 'ajax_template', [
			'label'       => __( 'AJAX template', 'results-range-wpml' ),
			'type'        => \Elementor\Controls_Manager::TEXT,
			'default'     => 'Toont {start}-{end} {results_label}',
			'description' => __( 'Used after filtering when total cannot be reliably determined. Tags: {start}, {end}, {visible}, {results_label}', 'results-range-wpml' ),
			'condition'   => [ 'enable_ajax_updates' => 'yes' ],
		] );

		$this->add_control( 'single_page_mode', [
			'label'       => __( 'When only 1 page', 'results-range-wpml' ),
			'type'        => \Elementor\Controls_Manager::SELECT,
			'options'     => [
				'compact' => __( 'Show compact (recommended)', 'results-range-wpml' ),
				'full'    => __( 'Show full range (start-end of total)', 'results-range-wpml' ),
				'hide'    => __( 'Hide label', 'results-range-wpml' ),
			],
			'default'     => 'compact',
		] );

		$this->add_control( 'compact_template', [
			'label'       => __( 'Compact template', 'results-range-wpml' ),
			'type'        => \Elementor\Controls_Manager::TEXT,
			'default'     => 'Toont {total} {results_label}',
			'description' => __( 'Used when single-page mode is Compact. Tags: {total}, {results_label}', 'results-range-wpml' ),
			'condition'   => [
				'single_page_mode' => 'compact',
			],
		] );

		$this->add_control( 'results_label_singular', [
			'label'   => __( 'Result label (singular)', 'results-range-wpml' ),
			'type'    => \Elementor\Controls_Manager::TEXT,
			'default' => 'bericht',
		] );

		$this->add_control( 'results_label_plural', [
			'label'   => __( 'Result label (plural)', 'results-range-wpml' ),
			'type'    => \Elementor\Controls_Manager::TEXT,
			'default' => 'berichten',
		] );

		$this->add_control( 'wrapper_tag', [
			'label'   => __( 'Wrapper HTML tag', 'results-range-wpml' ),
			'type'    => \Elementor\Controls_Manager::SELECT,
			'options' => [
				'div'  => 'div',
				'span' => 'span',
				'p'    => 'p',
			],
			'default' => 'div',
		] );

		$this->end_controls_section();

		// Style
		$this->start_controls_section( 'section_style', [
			'label' => __( 'Style', 'results-range-wpml' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		] );

		$this->add_control( 'text_color', [
			'label'     => __( 'Text color', 'results-range-wpml' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => [
				'{{WRAPPER}} .rrw-results-range' => 'color: {{VALUE}};',
			],
		] );

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name'     => 'typography',
				'selector' => '{{WRAPPER}} .rrw-results-range',
			]
		);

		$this->add_responsive_control( 'text_align', [
			'label'     => __( 'Alignment', 'results-range-wpml' ),
			'type'      => \Elementor\Controls_Manager::CHOOSE,
			'options'   => [
				'left'   => [ 'title' => __( 'Left', 'results-range-wpml' ),   'icon' => 'eicon-text-align-left' ],
				'center' => [ 'title' => __( 'Center', 'results-range-wpml' ), 'icon' => 'eicon-text-align-center' ],
				'right'  => [ 'title' => __( 'Right', 'results-range-wpml' ),  'icon' => 'eicon-text-align-right' ],
			],
			'selectors' => [
				'{{WRAPPER}} .rrw-results-range' => 'text-align: {{VALUE}};',
			],
		] );

		$this->add_responsive_control( 'padding', [
			'label'      => __( 'Padding', 'results-range-wpml' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => [ 'px', 'em', '%' ],
			'selectors'  => [
				'{{WRAPPER}} .rrw-results-range' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			],
		] );

		$this->add_responsive_control( 'margin', [
			'label'      => __( 'Margin', 'results-range-wpml' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => [ 'px', 'em', '%' ],
			'selectors'  => [
				'{{WRAPPER}} .rrw-results-range' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			],
		] );

		$this->end_controls_section();
	}

	protected function render() {
		global $wp_query;
		if ( ! $wp_query instanceof \WP_Query ) return;

		$settings = $this->get_settings_for_display();

		$total = (int) $wp_query->found_posts;
		if ( $total <= 0 && ( $settings['hide_when_empty'] ?? 'yes' ) === 'yes' ) return;

		$per_page = (int) $wp_query->get( 'posts_per_page' );
		if ( $per_page <= 0 ) $per_page = (int) get_option( 'posts_per_page', 10 );

		$paged = (int) get_query_var( 'paged' );
		if ( $paged <= 0 ) $paged = 1;

		if ( $total > 0 ) {
			$start = ( ( $paged - 1 ) * $per_page ) + 1;
			$end   = min( $start + $per_page - 1, $total );
		} else {
			$start = 0;
			$end   = 0;
		}

		$single_page = ( $total > 0 && $total <= $per_page );
		$mode = $settings['single_page_mode'] ?? 'compact';

		// WPML string keys per widget instance
		$wid = $this->get_id();
		$key_label   = 'rrw_' . $wid . '_label_template';
		$key_compact = 'rrw_' . $wid . '_compact_template';
		$key_sing    = 'rrw_' . $wid . '_results_label_singular';
		$key_plur    = 'rrw_' . $wid . '_results_label_plural';
		$key_ajax    = 'rrw_' . $wid . '_ajax_template';

		RRW_Core::wpml_register( $key_label,   (string) ( $settings['label_template'] ?? '' ) );
		RRW_Core::wpml_register( $key_compact, (string) ( $settings['compact_template'] ?? '' ) );
		RRW_Core::wpml_register( $key_sing,    (string) ( $settings['results_label_singular'] ?? '' ) );
		RRW_Core::wpml_register( $key_plur,    (string) ( $settings['results_label_plural'] ?? '' ) );
		RRW_Core::wpml_register( $key_ajax,    (string) ( $settings['ajax_template'] ?? '' ) );

		$label_template   = RRW_Core::wpml_translate( $key_label,   (string) ( $settings['label_template'] ?? '' ) );
		$compact_template = RRW_Core::wpml_translate( $key_compact, (string) ( $settings['compact_template'] ?? '' ) );
		$label_singular   = RRW_Core::wpml_translate( $key_sing,    (string) ( $settings['results_label_singular'] ?? 'bericht' ) );
		$label_plural     = RRW_Core::wpml_translate( $key_plur,    (string) ( $settings['results_label_plural'] ?? 'berichten' ) );
		$ajax_template    = RRW_Core::wpml_translate( $key_ajax,    (string) ( $settings['ajax_template'] ?? 'Toont {start}-{end} {results_label}' ) );

		$results_label = ( $total === 1 ) ? $label_singular : $label_plural;

		if ( $single_page && $mode === 'hide' ) return;

		if ( $single_page && $mode === 'compact' ) {
			$template = $compact_template !== '' ? $compact_template : 'Toont {total} {results_label}';
			$text = strtr( $template, [
				'{total}'         => (string) $total,
				'{results_label}' => (string) $results_label,
			] );
		} else {
			$template = $label_template !== '' ? $label_template : 'Toont {start}-{end} van {total} {results_label}';
			$text = strtr( $template, [
				'{start}'         => (string) $start,
				'{end}'           => (string) $end,
				'{total}'         => (string) $total,
				'{results_label}' => (string) $results_label,
			] );
		}

		$tag = in_array( ( $settings['wrapper_tag'] ?? 'div' ), [ 'div', 'span', 'p' ], true ) ? $settings['wrapper_tag'] : 'div';

		$attrs = [
			'data-rrw'            => '1',
			'data-per_page'       => (string) (int) $per_page,
			'data-total_initial'  => (string) (int) $total,
			'data-query_id'       => (string) ( $settings['query_id'] ?? '' ),
			'data-enable_ajax'    => (string) ( $settings['enable_ajax_updates'] ?? 'yes' ),
			'data-ajax_fallback'  => (string) ( $settings['ajax_fallback_mode'] ?? 'omit_total' ),
			'data-ajax_template'  => (string) $ajax_template,
			'data-label_template'=> (string) $label_template,
			'data-results_label'  => (string) $results_label,
		];

		$attr_str = '';
		foreach ( $attrs as $k => $v ) {
			$attr_str .= ' ' . esc_attr( $k ) . '="' . esc_attr( $v ) . '"';
		}

		echo '<' . esc_attr( $tag ) . ' class="rrw-results-range"' . $attr_str . '>';
		echo wp_kses_post( $text );
		echo '</' . esc_attr( $tag ) . '>';
	}
}
