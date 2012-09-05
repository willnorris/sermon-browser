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
* Currently this function makes sure the time and date are saved when editing a sermon
*/
function mbsb_plugins_loaded() {
	if (isset($_POST['mbsb_date']) && isset($_POST['post_type']) && $_POST['post_type'] == 'mbsb_sermons')
		mbsb_make_sure_date_time_is_saved();
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
* Makes sure the necessary filters are added when editing custom post types, to ensure columns are added and are sortable.
*/
function mbsb_onload_edit_page () {
	if (isset($_GET['post_type']) && substr($_GET['post_type'],0,5) == 'mbsb_') {
		$mbsb_post_type = substr($_GET['post_type'], 5);
		if (function_exists ("mbsb_add_{$mbsb_post_type}_columns")) {
			add_filter ("manage_mbsb_{$mbsb_post_type}_posts_columns", "mbsb_add_{$mbsb_post_type}_columns");
			if (function_exists ("mbsb_make_{$mbsb_post_type}_columns_sortable"))
				add_filter ("manage_edit-mbsb_{$mbsb_post_type}_sortable_columns", "mbsb_make_{$mbsb_post_type}_columns_sortable");
				
		}
		if (isset($_GET['orderby'])) {
			add_filter ('posts_join_paged', 'mbsb_edit_posts_join');
			add_filter ('posts_orderby', 'mbsb_edit_posts_sort');
			add_filter ('posts_fields', 'mbsb_edit_posts_fields');
		}
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
	if (isset($_GET['orderby']) && isset($_GET['post_type'])) {
		if ($_GET['post_type'] == 'mbsb_sermons') {
			if ($_GET['orderby'] == 'preacher')
				return "{$join} INNER JOIN {$wpdb->prefix}postmeta ON ({$wpdb->prefix}posts.ID = {$wpdb->prefix}postmeta.post_id) INNER JOIN {$wpdb->prefix}posts AS preachers ON (preachers.ID = {$wpdb->prefix}postmeta.meta_value AND preachers.post_type = 'mbsb_preachers')";
			elseif ($_GET['orderby'] == 'service')
				return "{$join} INNER JOIN {$wpdb->prefix}postmeta ON ({$wpdb->prefix}posts.ID = {$wpdb->prefix}postmeta.post_id) INNER JOIN {$wpdb->prefix}posts AS services ON (services.ID = {$wpdb->prefix}postmeta.meta_value AND services.post_type = 'mbsb_services')";
			elseif ($_GET['orderby'] == 'series')
				return "{$join} INNER JOIN {$wpdb->prefix}postmeta ON ({$wpdb->prefix}posts.ID = {$wpdb->prefix}postmeta.post_id) INNER JOIN {$wpdb->prefix}posts AS series ON (series.ID = {$wpdb->prefix}postmeta.meta_value AND series.post_type = 'mbsb_series')";
		}
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
	if (isset($_GET['orderby']) && isset($_GET['post_type'])) {
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
* @param string $where
* @return string
*/
function mbsb_edit_posts_fields ($where) {
	global $wpdb;
	if (isset($_GET['orderby']) && isset($_GET['post_type'])) {
		if ($_GET['post_type'] == 'mbsb_sermons') {
			if ($_GET['orderby'] == 'passages')
				return $where.", (SELECT meta_value FROM {$wpdb->prefix}postmeta AS pm WHERE wp_posts.ID=pm.post_id AND pm.meta_key='passage_start' ORDER BY RIGHT(pm.meta_value, 4) LIMIT 1) AS passage_sort";
		}
	}
	return $where;
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
					'supports' => array ('title', 'thumbnail', 'comments'), // We will add 'editor' support later, so it can be positioned correctly
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
	wp_enqueue_script('jquery-ui-datepicker');
	wp_enqueue_style('mbsb_jquery_ui');
	add_action ('admin_footer', 'mbsb_add_date_picker_code');
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
	echo '<input type="hidden" name="hidden_aa" id="hidden_aa" value="'.date ('Y', $sermon->timestamp).'"/><input type="hidden" name="hidden_mm" id="hidden_mm" value="'.date ('m', $sermon->timestamp).'"/><input type="hidden" name="hidden_jj" id="hidden_jj" value="'.date ('d', $sermon->timestamp).'"/><input type="hidden" name="hidden_hh" id="hidden_hh" value="'.date ('H', $sermon->timestamp).'"/><input type="hidden" name="hidden_mn" id="hidden_mb" value="'.date ('i', $sermon->timestamp).'"/>';
}

/**
* Outputs the media metabox when editing/creating a sermon
*/
function mbsb_sermon_media_meta_box() {
	global $post;
	wp_nonce_field (__FUNCTION__, 'media_nonce', true);
	echo '<table class="sermon_media">';
	echo '<tr><th scope="row"><label for="mbsb_media_1_type">'.__('Media Type', MBSB).':</label></th><td><select id="mbsb_media_1_type" name="mbsb_media_1_type"><option value="file">Uploaded file</option><option value="embed">Embedded HTML</option><option value="url">External URL</option></select></td></tr>';
	echo '</table>';
}

/**
* Adds jQuery datepicker code.
* 
* Designed to be called via the admin_footer action.
* 
*/
function mbsb_add_date_picker_code() {
	echo  "<script type=\"text/javascript\">jQuery(document).ready(function(){jQuery('.add-date-picker').datepicker({dateFormat : 'yy-mm-dd'});});</script>";
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
?>