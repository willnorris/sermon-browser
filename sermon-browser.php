<?php
/*
Plugin Name: Sermon Browser
Plugin URI: http://www.sermonbrowser.com/
Description: Upload sermons to your website, where they can be searched, listened to, and downloaded. Easy to use with comprehensive help and tutorials.
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

define ('MBSB', 'MBSB');

//Register hooks
register_activation_hook(__FILE__, 'mbsb_activate');

//Add 'standard' actions. Most actions are added in mbsb_init or mbsb_admin_init
add_action ('plugins_loaded', 'mbsb_plugins_loaded');
add_action ('init', 'mbsb_init');
add_action ('admin_init', 'mbsb_admin_init');

//Make sure classes autoload
spl_autoload_register('mbsb_autoload_classes');

/**
* Autoloads the classes when required
* 
* @param string $class_name
*/
function mbsb_autoload_classes ($class_name) {
	if (substr($class_name, 0, 5) == 'mbsb_')
		require (substr($class_name, 5).'.class.php');
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
	if (isset($_POST['mbsb_date']) && isset($_POST['post_type']) && $_POST['post_type'] == 'mbsb_sermons')
		mbsb_make_sure_date_time_is_saved();
	if (isset ($_GET['mbsb_script']))
		require ('scripts.php');
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
	// Is the user quick editing a custom post_type?
	if (isset($_POST['action']) && $_POST['action'] == 'inline-save' && substr($_POST['post_type'], 0, 5) == 'mbsb_') {
		$mbsb_post_type = substr($_POST['post_type'], 5);
		if (function_exists("mbsb_add_{$mbsb_post_type}_columns"))
			add_filter ("manage_mbsb_{$mbsb_post_type}_posts_columns", "mbsb_add_{$mbsb_post_type}_columns");
	}
}

/**
* Runs on the admin_init action.
* 
* Registers admin styles.
* Sets up most admin hooks and filters.
*  
*/
function mbsb_admin_init () {
	$date = @filemtime(mbsb_plugin_dir_path('css/admin-style.php'));
	wp_register_style ('mbsb_admin_style', plugins_url('css/admin-style.php', __FILE__), $date);
	wp_register_style ('mbsb_jquery_ui', plugins_url('css/jquery-ui-1.8.23.custom.css', __FILE__), '');
	add_action ('admin_print_styles', 'mbsb_admin_print_styles');
	add_action ('load-edit.php', 'mbsb_onload_edit_page');
	add_action ('manage_posts_custom_column', 'mbsb_output_custom_columns', 10, 2);
	add_action ('admin_enqueue_scripts', 'mbsb_add_javascript_and_styles_to_admin_pages');
	add_action ('load-media-upload.php', 'mbsb_media_upload_actions');
	add_action ('load-async-upload.php', 'mbsb_media_upload_actions');
	add_action ('wp_ajax_mbsb_attachment_insert', 'mbsb_ajax_attachment_insert');
	add_action ('wp_ajax_mbsb_attach_url_embed', 'mbsb_ajax_attach_url_embed');
}

function mbsb_ajax_attachment_insert() {
	global $wpdb;
	if (!check_ajax_referer ("mbsb_attachment_insert_{$_POST['post_id']}"))
		die ('Suspicious behaviour blocked');
	add_filter ('posts_where_paged', 'mbsb_add_guid_to_where');
	$attachment = get_posts (array ('numberposts' => 1, 'post_type' => 'attachment', 'post_status' => null, 'suppress_filters' => false));
	remove_filter ('posts_where_paged', 'mbsb_add_guid_to_where');
	$attachment = $attachment[0];
	$sermon = new mbsb_sermon($_POST['post_id']);
	if ($result = $sermon->add_attachment ($attachment->ID))
		echo mbsb_add_media_row ($attachment, true);
	elseif ($result === NULL)
		echo '<tr><td colspan="2"><div class="message">'.__('That file is already attached to that sermon.', MBSB).'</div></td></tr>';
	else
		_e('There was an error attaching that file to the sermon.', MBSB);
	die();
}

function mbsb_add_guid_to_where ($where) {
	return "{$where} AND guid='{$_POST['url']}'";
}

function mbsb_ajax_attach_url_embed() {
	global $wpdb;
	if (!check_ajax_referer ("mbsb_handle_url_embed_{$_POST['post_id']}"))
		die ('Suspicious behaviour blocked');
	$sermon = new mbsb_sermon($_POST['post_id']);
	if ($_POST['type'] == 'embed') {
		$sermon->add_embed ($_POST['attachment']);
	} elseif ($_POST['type'] == 'url') {
		$result = $sermon->add_url ($_POST['attachment']);
		echo mbsb_add_url_row ($result);
	}
	die();
}

/**
* Runs on the load-media-upload.php and load-async-upload.php actions
* 
* Adds additional filters required by the media uploader.
*/
function mbsb_media_upload_actions() {
	add_filter ('get_media_item_args', 'mbsb_force_insert_post_on_media_popup');
	add_filter ('media_upload_tabs', 'mbsb_filter_media_upload_tabs');
	add_filter ('gettext', 'mbsb_do_custom_translations', 1, 3);
}

/**
* Adds the necessary javascript and styles to admin pages
* 
* Called by the admin_enqueue_scripts action.
* Currently handles the media uploading on the sermon page.
*/
function mbsb_add_javascript_and_styles_to_admin_pages() {
	global $post;
	$screen = get_current_screen();
	if ($screen->base == 'post' && $screen->id == 'mbsb_sermons') {
		wp_enqueue_style ('thickbox');
		wp_enqueue_script('mbsb_script_sermon_upload', home_url("?mbsb_script&amp;name=sermon_upload&amp;post_id={$post->ID}"), array ('thickbox', 'media-upload'), @filemtime(mbsb_plugin_dir_path('scripts.php')));
	}
}

/**
* Ensures the media upload box always includes the 'insert into post' button
* 
* Filters 'get_media_item_args'
* @link http://fullrefresh.com/2012/03/09/wp-custom-post-types-ui-and-insert-into-post/
* 
* @param array $args
* @return array
*/
function mbsb_force_insert_post_on_media_popup ($args) {
	if (isset ($_GET['post_id']) && get_post_type ($_GET['post_id']) == 'mbsb_sermons')
		$args ['send'] = true;
	return $args;
}

/**
* Optionally removes all by one tab from the media uploader
* 
* Triggered by adding a 'tab=xxxx' parameter when calling the media uploader
* 
* @param array $tabs
* @return array
*/
function mbsb_filter_media_upload_tabs ($tabs) {
	if (isset($_GET['tab']))
		return array ($_GET['tab'] => $tabs[$_GET['tab']]);
	else
		return $tabs;
}

/**
* Runs on the admin_print_styles action.
* 
* Enqueues the mbsb_admin_style stylesheet 
*/
function mbsb_admin_print_styles () {
	wp_enqueue_style ('mbsb_admin_style');	
}

/**
* Runs on the load-edit.php action (i.e. when the edit posts page is loaded)
* 
* Makes sure the necessary filters are added when editing custom post types, to ensure columns are added and can be filtered and sorted.
*/
function mbsb_onload_edit_page () {
	if (isset($_GET['post_type']) && substr($_GET['post_type'],0,5) == 'mbsb_') {
		$mbsb_post_type = substr($_GET['post_type'], 5);
		if (function_exists ("mbsb_add_{$mbsb_post_type}_columns")) {
			add_filter ("manage_mbsb_{$mbsb_post_type}_posts_columns", "mbsb_add_{$mbsb_post_type}_columns");
			if (function_exists ("mbsb_make_{$mbsb_post_type}_columns_sortable"))
				add_filter ("manage_edit-mbsb_{$mbsb_post_type}_sortable_columns", "mbsb_make_{$mbsb_post_type}_columns_sortable");
		}
		add_filter ('posts_join_paged', 'mbsb_edit_posts_join');
		add_filter ('posts_orderby', 'mbsb_edit_posts_sort');
		add_filter ('posts_fields', 'mbsb_edit_posts_fields');
		add_filter ('posts_where_paged', 'mbsb_edit_posts_where');
		add_filter ('posts_groupby', 'mbsb_edit_posts_groupby');
		if (isset($_GET['s']))
			add_filter ('posts_search', 'mbsb_edit_posts_search');
	}
}

/**
* Filters manage_mbsb_sermons_posts_columns (i.e. the names of additional columns when sermons are displayed in admin)
* 
* Adds the new columns required.
* 
* @param array $columns
* @return array
*/
function mbsb_add_sermons_columns($columns) {
	$new_columns ['cb'] = $columns['cb'];
	$new_columns ['title'] = $columns ['title'];
	$new_columns ['passages'] = __('Bible passages', MBSB);
	$new_columns ['preacher'] = __('Preacher', MBSB);
	$new_columns ['service'] = __('Service', MBSB);
	$new_columns ['series'] = _x('Series', 'Singular', MBSB);
	$new_columns ['media'] = __('Media', MBSB);
	$new_columns ['tags'] = __('Tags', MBSB);
	$new_columns ['stats'] = __('Stats', MBSB);
	if (post_type_supports ('mbsb_sermons', 'comments'))
		$new_columns ['comments'] = $columns ['comments'];
	$new_columns ['sermon_date'] = $columns ['date'];
	return $new_columns;
}

/**
* Filters manage_edit-mbsb_sermons_sortable_columns (i.e. the list of sortable columns when sermons are displayed in admin)
* 
* Indicates which columns are sortable.
* 
* @param array $columns
* @return array
*/
function mbsb_make_sermons_columns_sortable ($columns) {
	$columns ['passages'] = 'passages';
	$columns ['preacher'] = 'preacher';
	$columns ['service'] = 'service';
	$columns ['series'] = 'series';
	$columns ['stats'] = 'stats';
	$columns ['sermon_date'] = 'sermon_date';
	return $columns;
}

/**
* Filters posts_join_paged when custom post types are displayed in admin and a sort order is specified.
* 
* Adds SQL to WP_Query to ensure the correct metadata is added to the query.
* 
* @param string $join
* @return string
*/
function mbsb_edit_posts_join ($join) {
	global $wpdb;
	if ($_GET['post_type'] == 'mbsb_sermons') {
		if ((isset($_GET['orderby']) && $_GET['orderby'] == 'preacher') || isset($_GET['preacher']) || isset($_GET['s']))
			$join .= " INNER JOIN {$wpdb->prefix}postmeta AS preachers_postmeta ON ({$wpdb->prefix}posts.ID = preachers_postmeta.post_id) INNER JOIN {$wpdb->prefix}posts AS preachers ON (preachers.ID = preachers_postmeta.meta_value AND preachers.post_type = 'mbsb_preachers')";
		if ((isset($_GET['orderby']) && $_GET['orderby'] == 'service') || isset($_GET['service']) || isset($_GET['s']))
			$join .= " INNER JOIN {$wpdb->prefix}postmeta AS services_postmeta ON ({$wpdb->prefix}posts.ID = services_postmeta.post_id) INNER JOIN {$wpdb->prefix}posts AS services ON (services.ID = services_postmeta.meta_value AND services.post_type = 'mbsb_services')";
		if ((isset($_GET['orderby']) && $_GET['orderby'] == 'series') || isset($_GET['series']) || isset($_GET['s']))
			$join .= " INNER JOIN {$wpdb->prefix}postmeta AS series_postmeta ON ({$wpdb->prefix}posts.ID = series_postmeta.post_id) INNER JOIN {$wpdb->prefix}posts AS series ON (series.ID = series_postmeta.meta_value AND series.post_type = 'mbsb_series')";
		if (isset($_GET['book']))
			$join .= " INNER JOIN {$wpdb->prefix}postmeta AS book_postmeta ON ({$wpdb->prefix}posts.ID = book_postmeta.post_ID AND book_postmeta.meta_key IN ('passage_start', 'passage_end'))";
	}
	return $join;
}

/**
* Filters posts_orderby when custom post types are displayed in admin and a sort order is specified.
* 
* Adds SQL to WP_Query to ensure the correct 'ORDER BY' data is added to the query.
* 
* @param string $orderby
* @return string
*/
function mbsb_edit_posts_sort($orderby) {
	global $wpdb;
	if (isset($_GET['orderby'])) {
		if ($_GET['post_type'] == 'mbsb_sermons') {
			if ($_GET['orderby'] == 'preacher')
				return "preachers.post_title ".$wpdb->escape($_GET["order"]);
			elseif ($_GET['orderby'] == 'service')
				return "services.post_title ".$wpdb->escape($_GET["order"]);
			elseif ($_GET['orderby'] == 'series')
				return "series.post_title ".$wpdb->escape($_GET["order"]);
			elseif ($_GET['orderby'] == 'passages')
				return "passage_sort ".$wpdb->escape($_GET["order"]);
		}
	}
	return $orderby;
}

/**
* Filters posts_fields when custom post types are displayed in admin and a sort order is specified.
* 
* Adds SQL to WP_Query to ensure that all fields needed for sorting are available.
* 
* @param string $select
* @return string
*/
function mbsb_edit_posts_fields ($select) {
	global $wpdb;
	if ($_GET['post_type'] == 'mbsb_sermons') {
		if ((isset($_GET['orderby']) && $_GET['orderby'] == 'passages'))
			$select .= ", (SELECT meta_value FROM {$wpdb->prefix}postmeta AS pm WHERE wp_posts.ID=pm.post_id AND pm.meta_key='passage_start' ORDER BY RIGHT(pm.meta_value, 4) LIMIT 1) AS passage_sort";
	}
	return $select;
}

/**
* Filters posts_where_paged when custom post types are displayed in admin and a filter is specified.
* 
* Adds SQL to WP_Query to ensure the correct 'WHERE' data is added to the query.
* 
* @param string $where
* @return string
*/
function mbsb_edit_posts_where($where) {
	global $wpdb;
	if ($_GET['post_type'] == 'mbsb_sermons') {
		if (isset($_GET['preacher']))
			$where .= " AND preachers.ID=".$wpdb->escape($_GET["preacher"]);
		if (isset($_GET['series']))
			$where .= " AND series.ID=".$wpdb->escape($_GET["series"]);
		if (isset($_GET['service']))
			$where .= " AND services.ID=".$wpdb->escape($_GET["service"]);
		if (isset($_GET['book']))
			$where .= " AND CONVERT(LEFT(book_postmeta.meta_value,2), UNSIGNED)=".$wpdb->escape($_GET["book"]);
	}
	return $where;
}

/**
* Filters posts_groupby when custom post types are displayed in admin and certain filters are specified.
* 
* Adds SQL to WP_Query to ensure the correct 'GROUP BY' data is added to the query.
* 
* @param string $groupby
* @return string
*/
function mbsb_edit_posts_groupby($groupby) {
	global $wpdb;
	if ($_GET['post_type'] == 'mbsb_sermons') {
		if (isset($_GET['book']))
			$groupby .= "{$wpdb->prefix}posts.ID";
	}
	return $groupby;
}

/**
* Filters post_search (the search criteria) to add additional fields for searching custom post types in admin.
* 
* It's a bit of a hack. A patch has been submitted to make this more reliable.
* @link http://core.trac.wordpress.org/ticket/21803
* 
* @param string $search
* @return string
*/
function mbsb_edit_posts_search ($search) {
	global $wpdb;
	if (isset($_GET['s'])) {
		if ($_GET['post_type'] == 'mbsb_sermons')
			$new_search_fields = array ('preachers.post_title', 'services.post_title', 'series.post_title');
		if (isset($new_search_fields)) {
			$search = rtrim ($search, ' )').')';
			$term = '%'.$wpdb->escape($_GET['s']).'%';
			foreach ($new_search_fields as &$s)
				$s = "({$s} LIKE '{$term}')";
			$search .= " OR ".implode( ' OR ', $new_search_fields  ).")) ";
		}
	}
	return $search;
}

/**
* Runs on the manage_posts_custom_column action
* 
* Outputs the data for each cell of the custom columns when displaying a list of custom posts in admin
* 
* @param string $column - the name of the column
* @param integer $post_id - the post_id of that cell
*/
function mbsb_output_custom_columns($column, $post_id) {
	$post_type = get_post_type ($post_id);
	if ($post_type == 'mbsb_sermons') {
		$sermon = new mbsb_sermon($post_id);
		if ($column == 'sermon_date')
			echo date(get_option('date_format'), $sermon->timestamp);
		else
			echo str_replace(', ', '<br/>', $sermon->admin_filter_link ($post_type, $column));
	}
}

/**
* Registers the various custom post types and taxonomies
*/
function mbsb_register_custom_post_types() {
	$sermons_slug = '/'.__('sermons', MBSB);
	//Sermons post type
	$args = array (	'label' => __('Sermons', MBSB),
					'labels' => mbsb_generate_post_label (__('Sermons', MBSB), __('Sermon', MBSB)),
					'description' => __('Information about each sermon is stored here', MBSB),
					'public' => true,
					'show_ui' => true,
					'show_in_menu' => 'sermon-browser',
					'supports' => array ('title', 'comments'), // We will add 'editor' support later, so it can be positioned correctly
					'taxonomies' => array ('post_tag'),
					'has_archive' => true,
					'register_meta_box_cb' => 'mbsb_sermons_meta_boxes',
					'rewrite' => array('slug' => $sermons_slug, 'with_front' => false)); //Todo: Slug should be dynamic in the future
	register_post_type ('mbsb_sermons', $args);
	//Series post type	
	$args = array (	'label' => __('Series', MBSB),
					'labels' => mbsb_generate_post_label (_x('Series', 'Plural', MBSB), _x('Series', 'Singular', MBSB)),
					'description' => __('Stores a description and image for each series', MBSB),
					'public' => true,
					'show_ui' => true,
					'show_in_menu' => 'sermon-browser',
					'supports' => array ('title', 'thumbnail', 'comments'),
					'has_archive' => true,
					'rewrite' => array('slug' => $sermons_slug.'/'.__('series', MBSB), 'with_front' => false)); //Todo: Slug should be dynamic in the future
	register_post_type ('mbsb_series', $args);
	//Preachers post type
	$args = array (	'label' => __('Preachers', MBSB),
					'labels' => mbsb_generate_post_label (__('Preachers', MBSB), __('Preacher', MBSB)),
					'description' => __('Stores a description and image for each preacher', MBSB),
					'public' => true,
					'show_ui' => true,
					'show_in_menu' => 'sermon-browser',
					'supports' => array ('title', 'thumbnail', 'comments'),
					'has_archive' => true,
					'rewrite' => array('slug' => $sermons_slug.'/'.__('preachers', MBSB), 'with_front' => false)); //Todo: Slug should be dynamic in the future
	register_post_type ('mbsb_preachers', $args);
	//Services post type
	$args = array (	'label' => __('Services', MBSB),
					'labels' => mbsb_generate_post_label (__('Services', MBSB), __('Service', MBSB)),
					'description' => __('Stores a description and time for each service', MBSB),
					'public' => true,
					'show_ui' => true,
					'show_in_menu' => 'sermon-browser',
					'supports' => array ('title', 'editor', 'author', 'thumbnail', 'comments'),
					'has_archive' => true,
					'rewrite' => array('slug' => $sermons_slug.'/'.__('services', MBSB), 'with_front' => false)); //Todo: Slug should be dynamic in the future
	register_post_type ('mbsb_services', $args);
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
* Adds the metaboxes needed when editing/creating a sermon.
* 
* Also removes unwanted metaboxes and adds required styles and javascripts.
*/
function mbsb_sermons_meta_boxes () {
	add_meta_box ('mbsb_sermon_save', __('Save', MBSB), 'mbsb_sermon_save_meta_box', 'mbsb_sermons', 'side', 'high');
	add_meta_box ('mbsb_sermon_media', __('Media', MBSB), 'mbsb_sermon_media_meta_box', 'mbsb_sermons', 'normal', 'high');
	add_meta_box ('mbsb_sermon_details', __('Details', MBSB), 'mbsb_sermon_details_meta_box', 'mbsb_sermons', 'normal', 'high');
	add_meta_box ('mbsb_description', __('Description', MBSB), 'mbsb_sermon_editor_box', 'mbsb_sermons', 'normal', 'default');
	remove_meta_box ('submitdiv', 'mbsb_sermons', 'side');
	add_filter ('screen_options_show_screen', create_function ('', 'return false;'));
	wp_enqueue_script ('jquery-ui-datepicker');
	wp_enqueue_style('mbsb_jquery_ui');
	add_action ('admin_footer', 'mbsb_add_edit_sermons_javascript');
}

/**
* Outputs the main sermons details metabox when editing/creating a sermon
*/
function mbsb_sermon_details_meta_box() {
	global $post;
	//Todo: Pre-populate fields with defaults
	wp_nonce_field (__FUNCTION__, 'details_nonce', true);
	$sermon = new mbsb_sermon ($post->ID);
	echo '<table class="sermon_details">';
	echo '<tr><th scope="row"><label for="mbsb_preacher">'.__('Preacher', MBSB).':</label></th><td><select id="mbsb_preacher" name="mbsb_preacher">'.mbsb_return_select_list('preachers', $sermon->preacher_id).'</select></td></tr>';
	echo '<tr><th scope="row"><label for="mbsb_series">'.__('Series', MBSB).':</label></th><td><select id="mbsb_series" name="mbsb_series">'.mbsb_return_select_list('series', $sermon->series_id).'</select></td></tr>';
	echo '<tr><th scope="row"><label for="mbsb_service">'.__('Service', MBSB).':</label></th><td><select id="mbsb_service" name="mbsb_service">'.mbsb_return_select_list('services', $sermon->service_id).'</select></td></tr>';
	echo '<tr><th scope="row"><label for="mbsb_date">'.__('Date', MBSB).':</label></th><td><span class="time_input"><input id="mbsb_date" name="mbsb_date" type="text" class="add-date-picker" value="'.$sermon->date.'"/></td><td><label for="mbsb_date">'.__('Time', MBSB).':</label></td><td><input id="mbsb_time" name="mbsb_time" type="text" value="'.$sermon->time.'"/></span> <input type="checkbox" id="mbsb_override_time" name="mbsb_override_time"'.($sermon->override_time ? ' checked="checked"' : '').'/> <label for="mbsb_override_time" style="font-weight:normal">'.__('Override default time', MBSB).'</label></td></tr>';
	echo '<tr><th scope="row"><label for="mbsb_passages">'.__('Bible passages', MBSB).':</label></th><td colspan="3"><input id="mbsb_passages" name="mbsb_passages" type="text" value="'.$sermon->get_formatted_passages().'"/></td></tr>';
	echo '</table>';
}

/**
* Outputs the media metabox when editing/creating a sermon
*/
function mbsb_sermon_media_meta_box() {
	global $post;
	wp_nonce_field (__FUNCTION__, 'media_nonce', true);
	echo '<table class="sermon_media">';
	echo '<tr><th scope="row"><label for="mbsb_new_media_type">'.__('Add media', MBSB).':</label></th><td><select id="mbsb_new_media_type" name="mbsb_new_media_type">';
	$types = array ('none' => '', 'upload' => __('Upload a new file', MBSB), 'insert' => __('Insert from the Media Library', MBSB), 'url' => __('Enter an external URL', MBSB), 'embed' => __('Enter an embed code'));
	foreach ($types as $type => $label)
		echo "<option ".($type == 'none' ? 'selected="selected" ' : '')."value=\"{$type}\">{$label}&nbsp;</option>";
	echo '</select></td><td>';
	echo '<div id="upload-select" style="display:none"><input type="button" value="'.__('Select file', MBSB).'" class="button-secondary" id="mbsb_upload_media_button" name="mbsb_upload_media_button"></div>';
	echo '<div id="insert-select" style="display:none"><input type="button" value="'.__('Insert item', MBSB).'" class="button-secondary" id="mbsb_insert_media_button" name="mbsb_insert_media_button"></div>';
	echo '<div id="url-select" style="display:none"><input type="text" name="mbsb_input_url" id="mbsb_input_url" size="30"/><input type="button" value="'.__('Attach', MBSB).'" class="button-secondary" id="mbsb_attach_url_button" name="mbsb_attach_url_button"></div>';
	echo '<div id="embed-select" style="display:none"><input type="text" name="mbsb_input_embed" id="mbsb_input_embed" size="60"/><input type="button" value="'.__('Attach', MBSB).'" class="button-secondary" id="mbsb_attach_embed_button" name="mbsb_attach_embed_button"></div>';
	echo '</td></tr>';
	echo '</table>';
	echo '<table id="mbsb_attached_files" cellspacing="0" class="wp-list-table widefat fixed media">';
	echo '<tr id="mbsb_media_table_header"><th colspan="2" scope="col">'.__('Attached media', MBSB).'</th></tr>';
	$sermon = new mbsb_sermon($post->ID);
	$has_media = false;
	$attachments = $sermon->get_attachments();
	if ($attachments) {
		$has_media = true;
		foreach ($attachments as $attachment)
			echo mbsb_add_media_row ($attachment);
	}
	$urls = $sermon->get_urls();
	if ($urls) {
		$has_media = true;
		foreach ($urls as $url)
			echo mbsb_add_url_row ($url);
	}
	if (!$has_media)
		echo '<tr id = "mbsb_attached_files_no_media"><td colspan="2">'.__('No media is currently attached to this sermon', MBSB).'</td></tr>';
	echo '</table>';
}

/**
* Adds required jvascript for the edit sermons page.
* 
* Designed to be called via the admin_footer action.
* 
*/
function mbsb_add_edit_sermons_javascript() {
	echo "<script type=\"text/javascript\">jQuery(document).ready(function(){jQuery('.add-date-picker').datepicker({dateFormat : 'yy-mm-dd'});});</script>\r\n";
	echo "<script type=\"text/javascript\">var override = document.getElementById('mbsb_override_time'); override.change( function () {if override.val() > 0 { var mbsb_time = document.getElementById('mbsb_time'); mbsb_time.Disabled = true; mbsb_time.style.backgroundColor='grey';} else {document.getElementById('mbsb_time').disabled = false;}})";
}

/**
* Specifies the settings for the editor (description) box when creating/editing sermons
* 
* @param mixed $post
*/
function mbsb_sermon_editor_box ($post) {
	wp_editor ($post->post_content, 'content', array ('media_buttons' => false, 'textarea_rows' => 5));
}

/**
* Returns the HTML for a dropdown list of titles of a specified custom post type
* 
* @param string $custom_post_type - the custom post type required
* @param string $selected - the post_id of the custom post that should be pre-selected
* @return string - the resulting HTML
*/
function mbsb_return_select_list ($custom_post_type, $selected = '') {
	$posts = get_posts (array ('orderby' => 'title', 'order' => 'ASC', 'post_type' => "mbsb_{$custom_post_type}"));
	if (is_array($posts)) {
		$output = '';
		foreach ($posts as $post) {
			if ($selected != '' && $post->ID == $selected)
				$insert = ' selected="selected"';
			else
				$insert = '';
			$output .= "<option value=\"{$post->ID}\"{$insert}>".esc_html($post->post_title)."</option>";
		}
	} else
		$output = false;
	return $output;
}

/**
* Outputs the save/publish metabox
* 
* Code adapted from meta-boxes.php
* @todo - check this code with WordPress 3.3
*/
function mbsb_sermon_save_meta_box() {
	global $post;
	$post_type = $post->post_type;
	$post_type_object = get_post_type_object($post_type);
?>
<div class="submitbox" id="submitpost">

<div id="minor-publishing" style="border-bottom: none; box-shadow: inherit">

<div id="minor-publishing-actions">
<div id="save-action">
<?php if ( 'publish' != $post->post_status && 'future' != $post->post_status && 'pending' != $post->post_status ) { ?>
<input <?php if ( 'private' == $post->post_status ) { ?>style="display:none"<?php } ?> type="submit" name="save" id="save-post" value="<?php esc_attr_e('Save Draft'); ?>" tabindex="4" class="button button-highlighted" />
<?php } ?>
<img src="<?php echo esc_url( admin_url( 'images/wpspin_light.gif' ) ); ?>" class="ajax-loading" id="draft-ajax-loading" alt="" />
</div>
<div id="preview-action">
<?php
if ( 'publish' == $post->post_status ) {
	$preview_link = esc_url( get_permalink( $post->ID ) );
	$preview_button = __( 'Preview Changes' );
} else {
	$preview_link = get_permalink( $post->ID );
	if ( is_ssl() )
		$preview_link = str_replace( 'http://', 'https://', $preview_link );
	$preview_link = esc_url( apply_filters( 'preview_post_link', add_query_arg( 'preview', 'true', $preview_link ) ) );
	$preview_button = __( 'Preview' );
}
?>
<a class="preview button" href="<?php echo $preview_link; ?>" target="wp-preview" id="post-preview" tabindex="4"><?php echo $preview_button; ?></a>
<input type="hidden" name="wp-preview" id="wp-preview" value="" />
</div>
<div class="clear"></div>
</div><?php // /minor-publishing-actions ?>

<div class="clear"></div>
</div>

<div id="major-publishing-actions" style="border-top: none">
<?php do_action('post_submitbox_start'); ?>
<div id="delete-action">
<?php
if ( current_user_can( "delete_post", $post->ID ) ) {
	if ( !EMPTY_TRASH_DAYS )
		$delete_text = __('Delete Permanently');
	else
		$delete_text = __('Move to Trash');
	?>
<a class="submitdelete deletion" href="<?php echo get_delete_post_link($post->ID); ?>"><?php echo $delete_text; ?></a><?php
} ?>
</div>

<div id="publishing-action">
<img src="<?php echo esc_url( admin_url( 'images/wpspin_light.gif' ) ); ?>" class="ajax-loading" id="ajax-loading" alt="" />
<?php
if ( !in_array( $post->post_status, array('publish', 'future', 'private') ) || 0 == $post->ID ) { ?>
		<input name="original_publish" type="hidden" id="original_publish" value="<?php esc_attr_e('Publish') ?>" />
		<?php submit_button( __( 'Save' ), 'primary', 'publish', false, array( 'tabindex' => '5', 'accesskey' => 'p' ) ); ?>
<?php
} else { ?>
		<input name="original_publish" type="hidden" id="original_publish" value="<?php esc_attr_e('Update') ?>" />
		<input name="save" type="submit" class="button-primary" id="publish" tabindex="5" accesskey="p" value="<?php esc_attr_e('Update') ?>" />
<?php
} ?>
</div>
<div class="clear"></div>
</div>
</div>	
<?php
}

/**
* Add Sermons menu and sub-menus in admin
*/
function mbsb_add_admin_menu() {
	add_menu_page(__('Sermons', MBSB), __('Sermons', MBSB), 'publish_posts', 'sermon-browser', 'sb_manage_sermons', plugins_url('images/icon-16-color.png', __FILE__), 21);
	add_submenu_page('sermon-browser', __('Files', MBSB), __('Files', MBSB), 'upload_files', 'sermon-browser/files.php', 'sb_files');
	add_submenu_page('sermon-browser', __('Options', MBSB), __('Options', MBSB), 'manage_options', 'sermon-browser/options.php', 'sb_options');
	add_submenu_page('sermon-browser', __('Templates', MBSB), __('Templates', MBSB), 'manage_options', 'sermon-browser/templates.php', 'sb_templates');
	add_submenu_page('sermon-browser', __('Uninstall', MBSB), __('Uninstall', MBSB), 'edit_plugins', 'sermon-browser/uninstall.php', 'sb_uninstall');
	add_submenu_page('sermon-browser', __('Help', MBSB), __('Help', MBSB), 'publish_posts', 'sermon-browser/help.php', 'sb_help');
	add_submenu_page('sermon-browser', __('Pray for Japan', MBSB), __('Pray for Japan', MBSB), 'publish_posts', 'sermon-browser/japan.php', 'sb_japan');
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
* Saves metadata when a custom post type is saved.
* 
* Called by the save_post action.
* 
* @param integer $post_id
* @param object $post
*/
function mbsb_save_post ($post_id, $post) {
	if (!empty($_POST) && isset($_POST['action']) && $_POST['action'] == 'editpost' && $_POST['post_type'] == 'mbsb_sermons' && !(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) && check_admin_referer ('mbsb_sermon_details_meta_box', 'details_nonce')) {
		$sermon = new mbsb_sermon ($post_id);
		$sermon->update_preacher ($_POST['mbsb_preacher']);
		$sermon->update_series ($_POST['mbsb_series']);
		$sermon->update_service ($_POST['mbsb_service']);
		$sermon->update_override_time (isset ($_POST['mbsb_override_time']));
		$sermon->update_passages ($_POST['mbsb_passages']);
	}
}

/**
* Makes sure the date and time are saved when editing a custom post
* 
* Works by adding expected $_POST data from custom data.
*/
function mbsb_make_sure_date_time_is_saved () {
	$_POST['aa'] = substr ($_POST['mbsb_date'], 0, 4);
	$_POST['mm'] = substr ($_POST['mbsb_date'], 5, 2);
	$_POST['jj'] = substr ($_POST['mbsb_date'], 8, 2);
	$_POST['hh'] = substr ($_POST['mbsb_time'], 0, strpos($_POST['mbsb_time'], ':'));
	$_POST['mn'] = substr ($_POST['mbsb_time'], strpos($_POST['mbsb_time'], ':')+1);
	$_POST['ss'] = 0;
}

/**
* Provides a way of changing arbitary text without buffering.
* 
* Called by the gettext filter.
* 
* @param string $translated_text
* @param string $text
* @param string $domain
* @return string
*/
function mbsb_do_custom_translations ($translated_text, $text, $domain) {
	if ((strpos(wp_get_referer(), 'referer=mbsb_sermons') || (isset($_GET['referer']) && $_GET['referer'] == 'mbsb_sermons'))&& $text == 'Insert into Post')
		$translated_text = __("Attach to sermon", MBSB);
	return $translated_text;
}

/**
* Returns a row, ready to be inserted in a table displaying a list of media items
* 
* @param mixed $attachment - the post_id of the attachment, or the entire post object
* @param boolean $hide - hides the row using CSS classes
* @return string
*/
function mbsb_add_media_row ($attachment, $hide=false) {
	if (!is_object($attachment))
		$attachment = get_post ($attachment);
	$filename = get_attached_file ($attachment->ID);
	$insert = $hide ? '  class="media_row_hide"' : '';
	$output  = '<tr'.$insert.'><th colspan="2"><strong>'.esc_html($attachment->post_title).'</strong></th></tr>';
	$output .= '<tr'.$insert.'><td width="46">'.wp_get_attachment_image ($attachment->ID, array(46,60), true).'</td>';
	$output .= '<td><table class="mbsb_media_detail"><tr><th scope="row">'.__('Filename', MBSB).':</th><td>'.esc_html(basename($attachment->guid)).'</td></tr>';
	$output .= '<tr><th scope="row">'.__('File size', MBSB).':</th><td>'.mbsb_format_bytes(filesize($filename)).'</td></tr>';
	$output .= '<tr><th scope="row">'.__('Upload date', MBSB).':</th><td>'.mysql2date (get_option('date_format'), $attachment->post_date).'</td></tr></table></td></tr>';
	return $output;
}

function mbsb_add_url_row ($url_array, $hide=false) {
	$insert = $hide ? '  class="media_row_hide"' : '';
	$address = substr($url_array['url'], strpos($url_array['url'], '//')+2);
	$short_address = substr($address, 0, strpos($address, '/')+1).'…/'.basename($url_array['url']);
	if (strlen($short_address) > strlen($address)) {
		$short_address = $address;
		$insert2 = '';
	} else
		$insert2 = ' title="'.esc_html($url_array['url']).'"';
	$title = substr($url_array['url'], strrpos($url_array['url'], '/')+1);
	$output  = '<tr'.$insert.'><th colspan="2"><strong>'.esc_html($title).'</strong></th></tr>';
	$output .= '<tr'.$insert.'><td width="46"><img class="attachment-46x60" width="46" height="60" alt="'.esc_html($title).'" title="'.esc_html($title).'" src="'.wp_mime_type_icon ($url_array['mime_type']).'"></td>';
	$output .= '<td><table class="mbsb_media_detail"><tr><th scope="row">'.__('URL', MBSB).':</th><td><span'.$insert2.'>'.esc_html($short_address).'</span></td></tr>';
	if ($url_array['size'] && $url_array['mime_type'] != 'text/html')
		$output .= '<tr><th scope="row">'.__('File size', MBSB).':</th><td>'.mbsb_format_bytes($url_array['size']).'</td></tr>';
	$output .= '<tr><th scope="row">'.__('Attachment date', MBSB).':</th><td>'.mysql2date (get_option('date_format'), $url_array['date_time']).'</td></tr></table></td></tr>';
	return $output;
}

function mbsb_format_bytes ($bytes) {
	if ($bytes < 1100)
		return number_format($bytes, 0).' '.__('bytes', MBSB);
	elseif ($bytes < 1024000)
		return number_format($bytes/1024, 1).' '.__('kB', MBSB);
	elseif ($bytes < 1024000000)
		return number_format($bytes/1000000, 2).' '.__('MB', MBSB);
	elseif ($bytes < 1024000000000)
		return number_format($bytes/1000000000, 2).' '.__('GB', MBSB);
}
?>