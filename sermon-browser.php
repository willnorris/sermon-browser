<?php
/*
Plugin Name: Sermon Browser
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
* @author Mark Barnes
*/

/**
* mbsb is used as a pseudo-namespace throughout the plugin to prevent classes with other plugins.
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
	global $wp_rewrite;
	$wp_rewrite->flush_rules();
}

/**
* Runs on the 'plugins_loaded' action
* 
* Contains functions that for performance or other reasons need to be run as soon as possible.
*/
function mbsb_plugins_loaded() {
	if (isset ($_GET['mbsb_script']))
		require ('js/scripts.php');
}

/**
* Runs on the init action.
* 
* Registers custom post types.
* Sets up most WordPress hooks and filters. 
*/
function mbsb_init () {
	mbsb_register_custom_post_types();
	add_action ('save_post', 'mbsb_save_post', 10, 2);
	add_action ('admin_menu', 'mbsb_add_admin_menu');
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
					'supports' => array ('title', 'thumbnail', 'comments'), // No 'editor' support because we'll add it in a positionable box later
					'taxonomies' => array ('post_tag'),
					'has_archive' => true,
					'query_var' => 'sermon',
					'register_meta_box_cb' => 'mbsb_sermon_meta_boxes',
					'rewrite' => array('slug' => '/'.__('sermons', MBSB), 'with_front' => false)); //Todo: Slug should be dynamic in the future
	register_post_type ('mbsb_sermon', $args);
	//Series post type	
	$args = array (	'label' => __('Series', MBSB),
					'labels' => mbsb_generate_post_label (_x('Series', 'Plural', MBSB), _x('Series', 'Singular', MBSB)),
					'description' => __('Stores a description and image for each series', MBSB),
					'public' => true,
					'show_ui' => true,
					'show_in_menu' => 'sermon-browser',
					'supports' => array ('title', 'thumbnail', 'comments', 'editor'),
					'has_archive' => true,
					'rewrite' => array('slug' => '/'.__('series', MBSB), 'with_front' => false)); //Todo: Slug should be dynamic in the future
	register_post_type ('mbsb_series', $args);
	//Preachers post type
	$args = array (	'label' => __('Preachers', MBSB),
					'labels' => mbsb_generate_post_label (__('Preachers', MBSB), __('Preacher', MBSB)),
					'description' => __('Stores a description and image for each preacher', MBSB),
					'public' => true,
					'show_ui' => true,
					'show_in_menu' => 'sermon-browser',
					'supports' => array ('title', 'thumbnail', 'comments', 'editor'),
					'has_archive' => true,
					'rewrite' => array('slug' => '/'.__('preachers', MBSB), 'with_front' => false)); //Todo: Slug should be dynamic in the future
	register_post_type ('mbsb_preacher', $args);
	//Services post type
	$args = array (	'label' => __('Services', MBSB),
					'labels' => mbsb_generate_post_label (__('Services', MBSB), __('Service', MBSB)),
					'description' => __('Stores a description and time for each service', MBSB),
					'public' => true,
					'show_ui' => true,
					'show_in_menu' => 'sermon-browser',
					'supports' => array ('title', 'thumbnail', 'comments'),
					'has_archive' => true,
					'register_meta_box_cb' => 'mbsb_service_meta_boxes',
					'rewrite' => array('slug' => '/'.__('services', MBSB), 'with_front' => false)); //Todo: Slug should be dynamic in the future
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

?>