<?php
/**
 * Custom Post Types: Program & Episode
 *
 * @package MicrofixAudioPlatform
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', 'microfix_register_post_types' );

/**
 * Register Program and Episode custom post types.
 */
function microfix_register_post_types(): void {
	microfix_register_program_cpt();
	microfix_register_episode_cpt();
	microfix_register_acf_fields();
}

// ─── Program CPT ──────────────────────────────────────────────────────────────

function microfix_register_program_cpt(): void {
	$labels = [
		'name'                  => _x( 'Programs', 'Post type general name', 'microfix-audio-platform' ),
		'singular_name'         => _x( 'Program', 'Post type singular name', 'microfix-audio-platform' ),
		'menu_name'             => __( 'Programs', 'microfix-audio-platform' ),
		'add_new'               => __( 'Add New', 'microfix-audio-platform' ),
		'add_new_item'          => __( 'Add New Program', 'microfix-audio-platform' ),
		'edit_item'             => __( 'Edit Program', 'microfix-audio-platform' ),
		'new_item'              => __( 'New Program', 'microfix-audio-platform' ),
		'view_item'             => __( 'View Program', 'microfix-audio-platform' ),
		'search_items'          => __( 'Search Programs', 'microfix-audio-platform' ),
		'not_found'             => __( 'No programs found', 'microfix-audio-platform' ),
		'not_found_in_trash'    => __( 'No programs found in Trash', 'microfix-audio-platform' ),
		'all_items'             => __( 'All Programs', 'microfix-audio-platform' ),
	];

	$args = [
		'labels'             => $labels,
		'public'             => true,
		'publicly_queryable' => true,
		'show_ui'            => true,
		'show_in_menu'       => true,
		'show_in_rest'       => true,
		'query_var'          => true,
		'rewrite'            => [ 'slug' => 'programs' ],
		'capability_type'    => 'post',
		'has_archive'        => true,
		'hierarchical'       => false,
		'menu_position'      => 20,
		'menu_icon'          => 'dashicons-playlist-audio',
		'supports'           => [ 'title', 'editor', 'thumbnail', 'excerpt' ],
	];

	register_post_type( 'program', $args );
}

// ─── Episode CPT ──────────────────────────────────────────────────────────────

function microfix_register_episode_cpt(): void {
	$labels = [
		'name'                  => _x( 'Episodes', 'Post type general name', 'microfix-audio-platform' ),
		'singular_name'         => _x( 'Episode', 'Post type singular name', 'microfix-audio-platform' ),
		'menu_name'             => __( 'Episodes', 'microfix-audio-platform' ),
		'add_new'               => __( 'Add New', 'microfix-audio-platform' ),
		'add_new_item'          => __( 'Add New Episode', 'microfix-audio-platform' ),
		'edit_item'             => __( 'Edit Episode', 'microfix-audio-platform' ),
		'new_item'              => __( 'New Episode', 'microfix-audio-platform' ),
		'view_item'             => __( 'View Episode', 'microfix-audio-platform' ),
		'search_items'          => __( 'Search Episodes', 'microfix-audio-platform' ),
		'not_found'             => __( 'No episodes found', 'microfix-audio-platform' ),
		'not_found_in_trash'    => __( 'No episodes found in Trash', 'microfix-audio-platform' ),
		'all_items'             => __( 'All Episodes', 'microfix-audio-platform' ),
	];

	$args = [
		'labels'             => $labels,
		'public'             => true,
		'publicly_queryable' => true,
		'show_ui'            => true,
		'show_in_menu'       => true,
		'show_in_rest'       => true,
		'query_var'          => true,
		'rewrite'            => [ 'slug' => 'episodes' ],
		'capability_type'    => 'post',
		'has_archive'        => true,
		'hierarchical'       => false,
		'menu_position'      => 21,
		'menu_icon'          => 'dashicons-format-audio',
		'supports'           => [ 'title', 'editor', 'thumbnail', 'excerpt' ],
	];

	register_post_type( 'episode', $args );
}

// ─── ACF Field Group Registration ─────────────────────────────────────────────

/**
 * Programmatically register ACF fields for Episode.
 * Requires ACF Pro to be active.
 */
function microfix_register_acf_fields(): void {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group( [
		'key'      => 'group_microfix_episode',
		'title'    => 'Episode Details',
		'fields'   => [
			[
				'key'           => 'field_microfix_program',
				'label'         => 'Program',
				'name'          => 'program',
				'type'          => 'post_object',
				'post_type'     => [ 'program' ],
				'return_format' => 'id',
				'required'      => 1,
				'instructions'  => 'Select the parent Program for this episode.',
			],
			[
				'key'           => 'field_microfix_audio_file',
				'label'         => 'Audio File',
				'name'          => 'audio_file',
				'type'          => 'file',
				'return_format' => 'id',
				'mime_types'    => 'audio/mpeg, audio/ogg, audio/wav, audio/mp4, audio/aac',
				'instructions'  => 'Upload the audio file. It will be served via secure stream.',
			],
			[
				'key'          => 'field_microfix_video_file',
				'label'        => 'Video File (Self-Hosted)',
				'name'         => 'video_file',
				'type'         => 'file',
				'return_format' => 'id',
				'mime_types'   => 'video/mp4, video/webm, video/ogg',
				'instructions' => 'Upload a self-hosted video. It will be served via secure stream.',
			],
			[
				'key'          => 'field_microfix_video_url',
				'label'        => 'External Video URL (YouTube / Vimeo)',
				'name'         => 'video_url',
				'type'         => 'url',
				'instructions' => 'Optional: external video URL. Used only if no self-hosted video is uploaded.',
			],
			[
				'key'           => 'field_microfix_unlock_date',
				'label'         => 'Unlock Date',
				'name'          => 'unlock_date',
				'type'          => 'date_picker',
				'display_format' => 'F j, Y',
				'return_format' => 'Y-m-d',
				'instructions'  => 'Leave blank to unlock immediately. Set a future date for drip content.',
			],
			[
				'key'          => 'field_microfix_duration',
				'label'        => 'Duration',
				'name'         => 'duration',
				'type'         => 'text',
				'placeholder'  => 'e.g. 12:34',
				'instructions' => 'Human-readable duration shown in the episode grid.',
			],
			[
				'key'          => 'field_microfix_is_featured',
				'label'        => 'Featured Episode',
				'name'         => 'is_featured',
				'type'         => 'true_false',
				'ui'           => 1,
				'instructions' => 'Mark this episode to appear in [featured_episode] shortcode.',
			],
			[
				'key'          => 'field_microfix_content_type',
				'label'        => 'Content Type',
				'name'         => 'content_type',
				'type'         => 'select',
				'choices'      => [
					'audio' => 'Audio',
					'video' => 'Video',
				],
				'default_value' => 'audio',
				'return_format' => 'value',
				'instructions'  => 'Is this an audio or video episode?',
			],
		],
		'location' => [
			[
				[
					'param'    => 'post_type',
					'operator' => '==',
					'value'    => 'episode',
				],
			],
		],
		'menu_order'            => 0,
		'position'              => 'normal',
		'style'                 => 'default',
		'label_placement'       => 'top',
		'instruction_placement' => 'label',
		'active'                => true,
	] );

	// ── Program ACF Fields ──────────────────────────────────────────────────────

	acf_add_local_field_group( [
		'key'    => 'group_microfix_program',
		'title'  => 'Program Details',
		'fields' => [
			[
				'key'          => 'field_microfix_program_description',
				'label'        => 'Short Description',
				'name'         => 'program_description',
				'type'         => 'textarea',
				'rows'         => 3,
				'instructions' => 'Brief description shown in program listings.',
			],
			[
				'key'          => 'field_microfix_program_order',
				'label'        => 'Display Order',
				'name'         => 'program_order',
				'type'         => 'number',
				'default_value' => 0,
				'instructions' => 'Lower numbers display first.',
			],
		],
		'location' => [
			[
				[
					'param'    => 'post_type',
					'operator' => '==',
					'value'    => 'program',
				],
			],
		],
		'position' => 'normal',
		'active'   => true,
	] );
}
