<?php

declare( strict_types=1 );

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['htl_test_options']       = array();
$GLOBALS['htl_test_post_types']    = array();
$GLOBALS['htl_test_post_statuses'] = array();
$GLOBALS['htl_test_conditions']    = array();
$GLOBALS['htl_test_taxonomies']    = array();

function add_action() {}
function add_filter() {}
function apply_filters( $hook, $value ) {
	return $value;
}
function __( $text, $domain = null ) {
	return $text;
}
function absint( $value ) {
	return abs( (int) $value );
}
function get_option( $key, $fallback = false ) {
	return array_key_exists( $key, $GLOBALS['htl_test_options'] ) ? $GLOBALS['htl_test_options'][ $key ] : $fallback;
}
function get_post_type( $post_id ) {
	return $GLOBALS['htl_test_post_types'][ (int) $post_id ] ?? false;
}
function get_post_status( $post_id ) {
	return $GLOBALS['htl_test_post_statuses'][ (int) $post_id ] ?? false;
}
function post_type_exists( $post_type ) {
	return in_array( $post_type, array( 'post', 'page', 'book' ), true );
}
function sanitize_key( $value ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
}
function sanitize_title( $value ) {
	return sanitize_key( $value );
}
function wp_unslash( $value ) {
	return $value;
}
function is_object_in_taxonomy( $post_type, $taxonomy ) {
	return $GLOBALS['htl_test_taxonomies'][ $post_type . ':' . $taxonomy ] ?? false;
}
function has_category( $category, $post_id ) {
	return $GLOBALS['htl_test_taxonomies'][ 'category:' . (int) $post_id . ':' . $category ] ?? false;
}

function htl_test_condition( $condition ) {
	return ! empty( $GLOBALS['htl_test_conditions'][ $condition ] );
}
function is_404() {
	return htl_test_condition( '404' );
}
function is_search() {
	return htl_test_condition( 'search' );
}
function is_author() {
	return htl_test_condition( 'author' );
}
function is_date() {
	return htl_test_condition( 'date' );
}
function is_tag() {
	return htl_test_condition( 'tag' );
}
function is_category() {
	return htl_test_condition( 'category' );
}
function is_home() {
	return htl_test_condition( 'home' );
}

require_once dirname( __DIR__ ) . '/includes/class-htl-post-type.php';
require_once dirname( __DIR__ ) . '/includes/class-htl-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-htl-metabox.php';
require_once dirname( __DIR__ ) . '/includes/class-htl-renderer.php';
