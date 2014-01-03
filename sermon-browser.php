<?php
/*
Plugin Name: Sermon Browser 2
Plugin URI: http://www.sermonbrowser.com/
Description: Upload video or audio sermons to your website, where they can be searched, listened to, and downloaded. Easy to use with comprehensive help and tutorials.
Author: Mark Barnes
Version: 2.0 alpha
Author URI: http://www.4-14.org.uk/

Copyright (c) 2008-2012 Mark Barnes

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details: <http://www.gnu.org/licenses/>
*/

/**
* This file sets up SermonBrowser by adding core actions and including the required files.
* 
* @package SermonBrowser
* @subpackage Common
* @author Mark Barnes <mark@sermonbrowser.com>
*/

/**
* mbsb is used as a pseudo-namespace throughout the plugin to prevent clashes with other plugins.
* This constant is used as the domain in gettext calls.
*/
define ('MBSB', 'MBSB');

// Make sure the necessary files are included
if (is_admin())
	require mbsb_plugin_dir_path ('includes/admin.php');
else
	require mbsb_plugin_dir_path ('includes/frontend.php');

require mbsb_plugin_dir_path ('includes/common.php');
require mbsb_plugin_dir_path ('includes/helper_functions.php');
require mbsb_plugin_dir_path ('includes/widgets.php');

//Register activation hook
register_activation_hook(__FILE__, 'mbsb_activate');

//Add 'standard' actions. Most actions are added in mbsb_init or mbsb_admin_init
add_action ('plugins_loaded', 'mbsb_plugins_loaded');
add_action ('init', 'mbsb_init');

//Make sure classes autoload
spl_autoload_register('mbsb_autoload_classes');

/**
* Autoloads the classes when required
* 
* @param string $class_name
*/
function mbsb_autoload_classes ($class_name) {
	if (substr($class_name, 0, 5) == 'mbsb_')
		require ('classes/'.substr($class_name, 5).'.php');
}

/**
* Runs when the plugin is activated
*/
function mbsb_activate () {
	mbsb_register_custom_post_types();
	global $wp_rewrite;
	$wp_rewrite->flush_rules();
}

/**
* Runs on the 'plugins_loaded' action
* 
* Contains functions that for performance or other reasons need to be run as soon as possible.
*/
function mbsb_plugins_loaded() {
	if (isset ($_GET['mbsb_script'])) {
		if (mbsb_get_option('use_embedded_bible_'.get_locale()))
			add_action ('mbsb_frontend_jQuery', create_function ('', 'echo "logos.biblia.init();\r\n";'));
		require ("js/{$_GET['mbsb_script']}.php");
		die();
	}
}

/**
* Runs on the init action.
* 
* Registers custom post types.
* Sets up most WordPress hooks and filters. 
*/
function mbsb_init () {
	mbsb_register_custom_post_types();
	if (delete_transient('mbsb_flush_rules'))
		flush_rewrite_rules();
	mbsb_register_image_sizes();
	add_action ('save_post', 'mbsb_save_post', 10, 2);
	add_action ('admin_menu', 'mbsb_add_admin_menu');
	add_action ('admin_bar_menu', 'mbsb_admin_bar_menu');
	add_filter ('user_has_cap', 'mbsb_prevent_cpt_deletions', 10, 3);
}

/**
* Registers the various custom post types and taxonomies
*/
function mbsb_register_custom_post_types() {
	//Sermons post type
	$args = array (	'label' => __('Sermons', MBSB),
					'labels' => mbsb_generate_post_label (__('Sermons', MBSB), __('Sermon', MBSB)),
					'description' => __('Information about each sermon is stored here', MBSB),
					'public' => true,
					'show_ui' => true,
					'show_in_menu' => 'sermon-browser',
					'show_in_admin_bar' => true,
					'supports' => array ('title', 'thumbnail', 'comments', 'custom-fields'), // No 'editor' support because we'll add it in a positionable box later
					'taxonomies' => array ('post_tag'),
					'has_archive' => true,
					'query_var' => 'sermon',
					'register_meta_box_cb' => 'mbsb_sermon_meta_boxes',
					'rewrite' => array('slug' => mbsb_get_option('sermons_slug'), 'with_front' => false),
				);
	register_post_type ('mbsb_sermon', $args);
	//Series post type	
	$args = array (	'label' => __('Series', MBSB),
					'labels' => mbsb_generate_post_label (_x('Series', 'Plural', MBSB), _x('Series', 'Singular', MBSB)),
					'description' => __('Stores a description and image for each series', MBSB),
					'public' => true,
					'show_ui' => true,
					'show_in_menu' => 'sermon-browser',
					'show_in_admin_bar' => true,
					'capability_type' => 'page',
					'hierarchical' => true,
					'supports' => array ('title', 'thumbnail', 'comments', 'editor', 'custom-fields'),
					'has_archive' => true,
					'rewrite' => array('slug' => mbsb_get_option('series_slug'), 'with_front' => false),
					'map_meta_cap' => true,
				);
	register_post_type ('mbsb_series', $args);
	//Preachers post type
	$args = array (	'label' => __('Preachers', MBSB),
					'labels' => mbsb_generate_post_label (__('Preachers', MBSB), __('Preacher', MBSB)),
					'description' => __('Stores a description and image for each preacher', MBSB),
					'public' => true,
					'show_ui' => true,
					'show_in_menu' => 'sermon-browser',
					'show_in_admin_bar' => true,
					'capability_type' => 'page',
					'hierarchical' => true,
					'supports' => array ('title', 'thumbnail', 'comments', 'editor', 'custom-fields'),
					'has_archive' => true,
					'rewrite' => array('slug' => mbsb_get_option('preachers_slug'), 'with_front' => false),
					'map_meta_cap' => true,
				);
	register_post_type ('mbsb_preacher', $args);
	//Services post type
	$args = array (	'label' => __('Services', MBSB),
					'labels' => mbsb_generate_post_label (__('Services', MBSB), __('Service', MBSB)),
					'description' => __('Stores a description and time for each service', MBSB),
					'public' => true,
					'show_ui' => true,
					'show_in_menu' => 'sermon-browser',
					'show_in_admin_bar' => true,
					'capability_type' => 'page',
					'hierarchical' => true,
					'supports' => array ('title', 'thumbnail', 'comments', 'custom-fields'),
					'has_archive' => true,
					'register_meta_box_cb' => 'mbsb_service_meta_boxes',
					'rewrite' => array('slug' => mbsb_get_option('services_slug'), 'with_front' => false),
					'map_meta_cap' => true,
				);
	register_post_type ('mbsb_service', $args);
}

/**
* Generates an array of labels for custom post types
*
* @param string $plural - the plural form of the word used in the labels
* @param string $singular - the singular form of the word used in the labels
* @return array - the label array
*/
function mbsb_generate_post_label ($plural, $singular) {
	return array('name' => $plural, 'singular_name' => $singular, 'add_new' => __('Add New',MBSB), 'add_new_item' => sprintf(__('Add New %s', MBSB), $singular), 'edit_item' => sprintf(__('Edit %s', MBSB), $singular), 'new_item' => sprintf(__('New %s', MBSB), $singular), 'view_item' => sprintf(__('View %s', MBSB), $singular), 'search_items' => sprintf(__('Search %s', MBSB), $plural), 'not_found' => sprintf(__('No %s Found', MBSB), $plural), 'not_found_in_trash' => sprintf(__('No %s Found in Trash', MBSB), $plural), 'parent_item_colon' => sprintf(__('Parent %s:', MBSB), $singular), 'menu_name' => $plural);
}

/**
* Generates an array of labels for custom taxonomies
*
* @param string $plural - the plural form of the word used in the labels
* @param string $singular - the singular form of the word used in the labels
* @return array - the label array
*/
function mbsb_generate_taxonomy_label ($plural, $singular) {
	return array('name' => $plural, 'singular_name' => $singular, 'search_items' => sprintf(__('Search %s', MBSB), $plural), 'popular_items' => sprintf(__('Popular %s', MBSB), $plural), 'all_items' => sprintf(__('All %s', MBSB), $plural), 'parent_item' => sprintf(__('Parent %s', MBSB), $singular), 'edit_item' => sprintf(__('Edit %s', MBSB), $singular), 'update_item' => sprintf(__('Update %s', MBSB), $singular), 'add_new_item' => sprintf(__('Add New %s', MBSB), $singular), 'new_item_name' => sprintf(__('New %s Name', MBSB), $singular), 'separate_items_with_commas' => sprintf(__('Separate %s with commas', MBSB), $plural), 'add_or_remove_items' => sprintf(__('Add or remove %s', MBSB), $plural), 'choose_from_most_used' => sprintf(__('Choose from the most used %s', MBSB), $plural));
}

/**
* Registers the standard image size for each custom post type
*/
function mbsb_register_image_sizes() {
	$cpts = array ('sermon', 'series', 'preacher', 'service');
	foreach ($cpts as $c) {
		$size = mbsb_get_option ("{$c}_image_size");
		add_image_size ("mbsb_{$c}", $size['width'], $size['height'], $size['crop']);
	}
}

/** 
* Returns the path to the plugin, or to a specified file or folder within it
* 
* @param string $relative_path - a file or folder within the plugin
* @return string
*/
function mbsb_plugin_dir_path ($relative_path = '') {
	return plugin_dir_path(__FILE__).$relative_path;
}

/**
* Returns the URL of the plugin, or to a specified path within it
* 
* @param string $path
* @return string
*/
function mbsb_plugins_url ($path = '') {
	return plugins_url($path, __FILE__);
}

/**
* Returns the plugin basename
*
* @return string
*/
function mbsb_plugin_basename() {
	return plugin_basename(__FILE__);
}

/**
* Adds various items to the admin menu bar
*/
function mbsb_admin_bar_menu() {
	global $wp_admin_bar;
	if (current_user_can ('edit_pages')) {
		if (mbsb_get_option('add_all_types_to_admin_bar')) {
			$wp_admin_bar->add_node(array('id' => 'mbsb-menu', 'title'=> __('Sermons', MBSB)));
			$wp_admin_bar->add_node(array('parent' => 'mbsb-menu', 'id' => 'mbsb-sermon', 'title' => __('Sermons', MBSB), 'href' => admin_url('edit.php?post_type=mbsb_sermon')));
			$wp_admin_bar->add_node(array('parent' => 'mbsb-menu', 'id' => 'mbsb-series', 'title' => __('Series', MBSB), 'href' => admin_url('edit.php?post_type=mbsb_series')));
			$wp_admin_bar->add_node(array('parent' => 'mbsb-menu', 'id' => 'mbsb-preacher', 'title' => __('Preachers', MBSB), 'href' => admin_url('edit.php?post_type=mbsb_preacher')));
			$wp_admin_bar->add_node(array('parent' => 'mbsb-menu', 'id' => 'mbsb-services', 'title' => __('Services', MBSB), 'href' => admin_url('edit.php?post_type=mbsb_service')));
		} else {
			$wp_admin_bar->add_node(array('parent' => 'new-content', 'id' => 'mbsb-add-sermon', 'title' => __('Sermon', MBSB), 'href' => admin_url('post-new.php?post_type=mbsb_sermon')));
			if (!is_admin())
				$wp_admin_bar->add_node(array('parent' => 'site-name', 'id' => 'mbsb-sermons', 'title' => __('Sermons', MBSB), 'href' => admin_url('edit.php?post_type=mbsb_sermon')));
		}
	}
}
?>
