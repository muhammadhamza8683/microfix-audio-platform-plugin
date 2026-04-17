<?php
/**
 * Custom Post Types & Taxonomy
 *
 * Registers:
 *  - post type: program
 *  - post type: episode
 *  - taxonomy:  episode_category  (shared by episodes, like "Communication", "Self-Discovery" etc.)
 *
 * NOTE: ACF fields are NOT registered here. They are created manually
 * via the ACF dashboard. See README.md for the full list of required fields.
 *
 * @package MicrofixAudioPlatform
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', 'microfix_register_post_types' );

function microfix_register_post_types(): void {
	microfix_register_program_cpt();
	microfix_register_episode_cpt();
	microfix_register_episode_category_taxonomy();
}

// ─── Program CPT ──────────────────────────────────────────────────────────────

function microfix_register_program_cpt(): void {
	register_post_type( 'program', [
		'labels' => [
			'name'               => __( 'Programs', 'microfix-audio-platform' ),
			'singular_name'      => __( 'Program', 'microfix-audio-platform' ),
			'add_new_item'       => __( 'Add New Program', 'microfix-audio-platform' ),
			'edit_item'          => __( 'Edit Program', 'microfix-audio-platform' ),
			'all_items'          => __( 'All Programs', 'microfix-audio-platform' ),
			'search_items'       => __( 'Search Programs', 'microfix-audio-platform' ),
			'not_found'          => __( 'No programs found', 'microfix-audio-platform' ),
		],
		'public'             => true,
		'publicly_queryable' => true,
		'show_ui'            => true,
		'show_in_menu'       => true,
		'show_in_rest'       => true,
		'rewrite'            => [ 'slug' => 'programs' ],
		'capability_type'    => 'post',
		'has_archive'        => true,
		'hierarchical'       => false,
		'menu_position'      => 20,
		'menu_icon'          => 'dashicons-playlist-audio',
		'supports'           => [ 'title', 'editor', 'thumbnail', 'excerpt' ],
	] );
}

// ─── Episode CPT ──────────────────────────────────────────────────────────────

function microfix_register_episode_cpt(): void {
	register_post_type( 'episode', [
		'labels' => [
			'name'               => __( 'Episodes', 'microfix-audio-platform' ),
			'singular_name'      => __( 'Episode', 'microfix-audio-platform' ),
			'add_new_item'       => __( 'Add New Episode', 'microfix-audio-platform' ),
			'edit_item'          => __( 'Edit Episode', 'microfix-audio-platform' ),
			'all_items'          => __( 'All Episodes', 'microfix-audio-platform' ),
			'search_items'       => __( 'Search Episodes', 'microfix-audio-platform' ),
			'not_found'          => __( 'No episodes found', 'microfix-audio-platform' ),
		],
		'public'             => true,
		'publicly_queryable' => true,
		'show_ui'            => true,
		'show_in_menu'       => true,
		'show_in_rest'       => true,
		'rewrite'            => [ 'slug' => 'episodes' ],
		'capability_type'    => 'post',
		'has_archive'        => true,
		'hierarchical'       => false,
		'menu_position'      => 21,
		'menu_icon'          => 'dashicons-format-audio',
		'supports'           => [ 'title', 'editor', 'thumbnail', 'excerpt' ],
	] );
}

// ─── Episode Category Taxonomy ────────────────────────────────────────────────

function microfix_register_episode_category_taxonomy(): void {
	register_taxonomy( 'episode_category', 'episode', [
		'labels' => [
			'name'              => __( 'Categories', 'microfix-audio-platform' ),
			'singular_name'     => __( 'Category', 'microfix-audio-platform' ),
			'search_items'      => __( 'Search Categories', 'microfix-audio-platform' ),
			'all_items'         => __( 'All Categories', 'microfix-audio-platform' ),
			'edit_item'         => __( 'Edit Category', 'microfix-audio-platform' ),
			'update_item'       => __( 'Update Category', 'microfix-audio-platform' ),
			'add_new_item'      => __( 'Add New Category', 'microfix-audio-platform' ),
			'new_item_name'     => __( 'New Category Name', 'microfix-audio-platform' ),
			'menu_name'         => __( 'Categories', 'microfix-audio-platform' ),
			'not_found'         => __( 'No categories found', 'microfix-audio-platform' ),
		],
		'hierarchical'      => true,   // acts like post categories
		'public'            => true,
		'show_ui'           => true,
		'show_in_rest'      => true,
		'show_admin_column' => true,   // shows in episode list table
		'rewrite'           => [ 'slug' => 'episode-category' ],
		'query_var'         => true,
	] );
}
