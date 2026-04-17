<?php
/**
 * Elementor Widget Registration
 *
 * Wraps the plugin's shortcodes as native Elementor widgets so they
 * appear in the Elementor panel and can be dragged onto pages without
 * manually typing shortcode tags.
 *
 * Widgets registered:
 *  - Microfix: Weekly Sessions
 *  - Microfix: Member Dashboard
 *  - Microfix: Episodes Grid
 *  - Microfix: Programs Grid
 *  - Microfix: Membership Status
 *
 * @package MicrofixAudioPlatform
 */

defined( 'ABSPATH' ) || exit;

// Only hook if Elementor is active.
add_action( 'elementor/widgets/register', 'microfix_register_elementor_widgets' );

function microfix_register_elementor_widgets( $widgets_manager ): void {
	if ( ! class_exists( '\Elementor\Widget_Base' ) ) return;

	$widgets_manager->register( new Microfix_Elementor_Weekly_Sessions() );
	$widgets_manager->register( new Microfix_Elementor_Member_Dashboard() );
	$widgets_manager->register( new Microfix_Elementor_Episodes_Grid() );
	$widgets_manager->register( new Microfix_Elementor_Programs_Grid() );
	$widgets_manager->register( new Microfix_Elementor_Membership_Status() );
}

// ─────────────────────────────────────────────────────────────────────────────
// Base class for shortcode-backed widgets
// ─────────────────────────────────────────────────────────────────────────────

abstract class Microfix_Elementor_Widget_Base extends \Elementor\Widget_Base {
	public function get_categories() {
		return [ 'microfix' ];
	}
}

// Register widget category.
add_action( 'elementor/elements/categories_registered', function ( $elements_manager ) {
	$elements_manager->add_category( 'microfix', [
		'title' => __( 'Microfix Platform', 'microfix-audio-platform' ),
		'icon'  => 'eicon-headphones',
	] );
} );

// ─────────────────────────────────────────────────────────────────────────────
// Widget: Weekly Sessions
// ─────────────────────────────────────────────────────────────────────────────

class Microfix_Elementor_Weekly_Sessions extends Microfix_Elementor_Widget_Base {

	public function get_name()  { return 'microfix_weekly_sessions'; }
	public function get_title() { return __( 'Weekly Sessions', 'microfix-audio-platform' ); }
	public function get_icon()  { return 'eicon-calendar'; }

	protected function register_controls(): void {
		$this->start_controls_section( 'content_section', [
			'label' => __( 'Settings', 'microfix-audio-platform' ),
			'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
		] );

		$this->add_control( 'title', [
			'label'   => __( 'Section Title', 'microfix-audio-platform' ),
			'type'    => \Elementor\Controls_Manager::TEXT,
			'default' => __( 'Your Weekly Sessions', 'microfix-audio-platform' ),
		] );

		$this->add_control( 'subtitle', [
			'label'   => __( 'Subtitle', 'microfix-audio-platform' ),
			'type'    => \Elementor\Controls_Manager::TEXT,
			'default' => __( 'New episodes every Tuesday at 5am EST', 'microfix-audio-platform' ),
		] );

		$this->add_control( 'limit', [
			'label'   => __( 'Max Cards', 'microfix-audio-platform' ),
			'type'    => \Elementor\Controls_Manager::NUMBER,
			'default' => 3,
			'min'     => 1,
			'max'     => 6,
		] );

		$this->end_controls_section();
	}

	protected function render(): void {
		$s = $this->get_settings_for_display();
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo do_shortcode( sprintf(
			'[mfx_weekly_sessions title="%s" subtitle="%s" limit="%d"]',
			esc_attr( $s['title'] ),
			esc_attr( $s['subtitle'] ),
			(int) $s['limit']
		) );
	}
}

// ─────────────────────────────────────────────────────────────────────────────
// Widget: Member Dashboard
// ─────────────────────────────────────────────────────────────────────────────

class Microfix_Elementor_Member_Dashboard extends Microfix_Elementor_Widget_Base {

	public function get_name()  { return 'microfix_member_dashboard'; }
	public function get_title() { return __( 'Member Dashboard', 'microfix-audio-platform' ); }
	public function get_icon()  { return 'eicon-dashboard'; }

	protected function register_controls(): void {
		// No additional controls needed — dashboard is fully dynamic.
		$this->start_controls_section( 'content_section', [
			'label' => __( 'Info', 'microfix-audio-platform' ),
			'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
		] );
		$this->add_control( 'info_notice', [
			'type'            => \Elementor\Controls_Manager::RAW_HTML,
			'raw'             => '<p style="font-size:13px;line-height:1.5">' . __( 'This widget renders the full member dashboard with dynamic content. It requires the user to be logged in. Use it on a members-only page protected by MemberPress.', 'microfix-audio-platform' ) . '</p>',
			'content_classes' => 'elementor-descriptor',
		] );
		$this->end_controls_section();
	}

	protected function render(): void {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo do_shortcode( '[mfx_member_dashboard]' );
	}
}

// ─────────────────────────────────────────────────────────────────────────────
// Widget: Episodes Grid
// ─────────────────────────────────────────────────────────────────────────────

class Microfix_Elementor_Episodes_Grid extends Microfix_Elementor_Widget_Base {

	public function get_name()  { return 'microfix_episodes_grid'; }
	public function get_title() { return __( 'Episodes Grid', 'microfix-audio-platform' ); }
	public function get_icon()  { return 'eicon-gallery-grid'; }

	protected function register_controls(): void {
		$this->start_controls_section( 'content_section', [
			'label' => __( 'Settings', 'microfix-audio-platform' ),
			'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
		] );

		// Build program options list.
		$programs = get_posts( [ 'post_type' => 'program', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ] );
		$options  = [ '0' => __( 'All Programs', 'microfix-audio-platform' ) ];
		foreach ( $programs as $p ) {
			$options[ $p->ID ] = $p->post_title;
		}

		$this->add_control( 'program_id', [
			'label'   => __( 'Filter by Program', 'microfix-audio-platform' ),
			'type'    => \Elementor\Controls_Manager::SELECT,
			'options' => $options,
			'default' => '0',
		] );

		$this->add_control( 'columns', [
			'label'   => __( 'Columns', 'microfix-audio-platform' ),
			'type'    => \Elementor\Controls_Manager::NUMBER,
			'default' => 3,
			'min'     => 1,
			'max'     => 4,
		] );

		$this->end_controls_section();
	}

	protected function render(): void {
		$s = $this->get_settings_for_display();
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo do_shortcode( sprintf(
			'[mfx_episodes_grid program_id="%d" columns="%d"]',
			(int) $s['program_id'],
			(int) $s['columns']
		) );
	}
}

// ─────────────────────────────────────────────────────────────────────────────
// Widget: Programs Grid
// ─────────────────────────────────────────────────────────────────────────────

class Microfix_Elementor_Programs_Grid extends Microfix_Elementor_Widget_Base {

	public function get_name()  { return 'microfix_programs_grid'; }
	public function get_title() { return __( 'Programs Grid', 'microfix-audio-platform' ); }
	public function get_icon()  { return 'eicon-apps'; }

	protected function register_controls(): void {
		$this->start_controls_section( 'content_section', [
			'label' => __( 'Settings', 'microfix-audio-platform' ),
			'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
		] );
		$this->add_control( 'columns', [
			'label'   => __( 'Columns', 'microfix-audio-platform' ),
			'type'    => \Elementor\Controls_Manager::NUMBER,
			'default' => 3,
			'min'     => 1,
			'max'     => 4,
		] );
		$this->end_controls_section();
	}

	protected function render(): void {
		$s = $this->get_settings_for_display();
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo do_shortcode( '[mfx_programs_grid columns="' . (int) $s['columns'] . '"]' );
	}
}

// ─────────────────────────────────────────────────────────────────────────────
// Widget: Membership Status
// ─────────────────────────────────────────────────────────────────────────────

class Microfix_Elementor_Membership_Status extends Microfix_Elementor_Widget_Base {

	public function get_name()  { return 'microfix_membership_status'; }
	public function get_title() { return __( 'Membership Status', 'microfix-audio-platform' ); }
	public function get_icon()  { return 'eicon-person'; }

	protected function register_controls(): void {
		$this->start_controls_section( 'content_section', [
			'label' => __( 'Info', 'microfix-audio-platform' ),
			'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
		] );
		$this->add_control( 'note', [
			'type'            => \Elementor\Controls_Manager::RAW_HTML,
			'raw'             => '<p style="font-size:13px">' . __( 'Shows active/inactive membership status for the current user.', 'microfix-audio-platform' ) . '</p>',
			'content_classes' => 'elementor-descriptor',
		] );
		$this->end_controls_section();
	}

	protected function render(): void {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo do_shortcode( '[mfx_membership_status]' );
	}
}
