<?php
/**
* Include file called when is_admin() is true
* 
* @package SermonBrowser
* @subpackage admin
* @author Mark Barnes
*/
add_action ('admin_init', 'mbsb_admin_init');

/**
* Runs on the admin_init action.
* 
*/
function mbsb_admin_init () {
	// All admin pages
	$date = @filemtime(mbsb_plugin_dir_path('css/admin-style.css'));
	wp_register_style ('mbsb_admin_style', mbsb_plugins_url('css/admin-style.css'), array(), $date);
	wp_register_style ('mbsb_jquery_ui', mbsb_plugins_url('css/jquery-ui-1.8.23.custom.css'), array(), '1.8.23');
	add_action ('admin_print_styles', 'mbsb_admin_print_styles');
	add_action ('delete_post', 'mbsb_handle_media_deletion');
	add_filter ('admin_body_class', 'mbsb_admin_body_class');
	add_filter ('gettext', 'mbsb_do_custom_translations', 1, 3);
	// Single admin pages only
	add_action ('load-edit.php', 'mbsb_onload_edit_page');
	add_action ('load-post.php', 'mbsb_onload_post_page');
	add_action ('load-post-new.php', 'mbsb_onload_post_page');
	add_action ('load-upload.php', 'mbsb_onload_upload_page');
	add_action ('load-media-upload.php', 'mbsb_media_upload_actions');
	add_action ('load-async-upload.php', 'mbsb_media_upload_actions');
	// Ajax API calls
	add_action ('wp_ajax_mbsb_attachment_insert', 'mbsb_ajax_attachment_insert');
	add_action ('wp_ajax_mbsb_attach_url_embed', 'mbsb_ajax_attach_url_embed');
	add_action ('wp_ajax_mbsb_remove_attachment', 'mbsb_ajax_mbsb_remove_attachment');
	add_action ('wp_ajax_mbsb_get_bible_text', 'mbsb_ajax_mbsb_get_bible_text');
	add_action ('wp_ajax_nopriv_mbsb_get_bible_text', 'mbsb_ajax_mbsb_get_bible_text');
	add_action ('wp_ajax_mbsb_get_preacher_details', 'mbsb_ajax_mbsb_get_preacher_details');
	add_action ('wp_ajax_nopriv_mbsb_get_preacher_details', 'mbsb_ajax_mbsb_get_preacher_details');
	add_action ('wp_ajax_mbsb_get_service_details', 'mbsb_ajax_mbsb_get_service_details');
	add_action ('wp_ajax_nopriv_mbsb_get_service_details', 'mbsb_ajax_mbsb_get_service_details');
	add_action ('wp_ajax_mbsb_get_series_details', 'mbsb_ajax_mbsb_get_series_details');
	add_action ('wp_ajax_nopriv_mbsb_get_series_details', 'mbsb_ajax_mbsb_get_series_details');
	// Quick editing a custom post_type?
	if (isset($_POST['action']) && $_POST['action'] == 'inline-save' && substr($_POST['post_type'], 0, 5) == 'mbsb_') {
		$mbsb_post_type = substr($_POST['post_type'], 5);
		if (function_exists("mbsb_add_{$mbsb_post_type}_columns"))
			add_filter ("manage_mbsb_{$mbsb_post_type}_posts_columns", "mbsb_add_{$mbsb_post_type}_columns");
	}
	// Saving a sermon?
	if (isset($_POST['mbsb_date']) && isset($_POST['post_type']) && $_POST['post_type'] == 'mbsb_sermon')
		add_filter ('wp_insert_post_data', 'mbsb_sermon_insert_post_modify_date_time');
	//Displaying in a thickbox?
	if (isset($_GET['iframe']) && $_GET['iframe'] == 'true' && isset($_GET['post_type']) && substr($_GET['post_type'], 0, 5) == 'mbsb_')
		add_action ('admin_head', 'mbsb_tb_iframe_admin_head');
}

/**
* Runs on the admin_print_styles action.
* 
* Enqueues the mbsb_admin_style stylesheet 
*/
function mbsb_admin_print_styles () {
	global $post;
	if (isset ($post->ID) && isset ($post->post_type) && $post->post_type == 'mbsb_sermon')
		echo "<script type=\"text/javascript\">var mbsb_sermon_id=".esc_html($post->ID).";</script>\r\n";
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
		add_action ('manage_posts_custom_column', 'mbsb_output_custom_columns', 10, 2);
		add_action ('manage_pages_custom_column', 'mbsb_output_custom_columns', 10, 2);
		if (isset($_GET['s']))
			add_filter ('posts_search', 'mbsb_edit_posts_search');
		add_action ('admin_head', create_function ('', "echo '<style type=\"text/css\">table.fixed {table-layout:auto;} table.fixed th.column-tags, table.fixed td.column-tags {width:auto;}</style>';"));
	}
}

/**
* Runs on the load-post.php action (i.e. when editing or creating a post)
* 
* 
*/
function mbsb_onload_post_page () {
	$screen = get_current_screen();
	if (substr($screen->post_type, 0, 5) == 'mbsb_')
		add_filter ("get_user_option_meta-box-order_{$screen->post_type}", 'mbsb_set_default_metabox_sort_order', 10, 3);
	add_action ('admin_enqueue_scripts', 'mbsb_add_javascript_and_styles_to_admin_pages');
	if (isset($_GET['message']))
		add_filter ('post_updated_messages', 'mbsb_post_updated_messages');
}

/**
* Runs on the load-upload.php action (i.e. when the Media Library page is loaded)
* 
* Makes sure the necessary filters are added to ensure the Attached To column is correct.
*/
function mbsb_onload_upload_page () {
	add_action ('manage_media_columns', 'mbsb_manage_media_columns', 10, 2);
	add_action ('manage_media_custom_column', 'mbsb_output_custom_media_columns', 10, 2);
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
	if ($post_type == 'mbsb_sermon') {
		$sermon = new mbsb_sermon($post_id);
		if ($column == 'sermon_date')
			echo date(get_option('date_format'), $sermon->timestamp);
		else
			echo str_replace(', ', '<br/>', $sermon->edit_php_cell ($post_type, $column));
	}
	if (substr($post_type, 0, 5) == 'mbsb_') {
		if ($column == 'image')
			if (current_user_can('edit_post', $post_id)) {
				$thumbnail = get_post_thumbnail_id($post_id);
				if ($thumbnail)
					echo "<a href=\"".get_edit_post_link ($thumbnail)."\">".wp_get_attachment_image ($thumbnail, array(100, 100)).'</a>';
			} else
				echo get_the_post_thumbnail($post_id, array(100, 100));
		elseif ($column == 'num_sermons') {
			$object = new $post_type ($post_id);
			echo $object->get_sermon_count();
		}
	}
}

/**
* Runs on the manage_media_column action
* 
* Filters the list of columns to be displayed in the Media Library table
* 
* @param array $columns - the current columns
* @param boolean $detached - true if the post type is detached
* @return array
*/
function mbsb_manage_media_columns ($columns, $detached) {
	if (isset($columns['parent']))
		unset($columns['parent']);
	if (!is_array($columns))
		return $columns;
	$new_c = array();
	foreach ($columns as $k => $c) {
		$new_c[$k] = $c;
		if ($k == 'author')
			$new_c['attached_to'] = __('Attached to', MBSB);
	}
	if (!isset($new_c['attached_to']))
		$new_c['attached_to'] = __('Attached to', MBSB);
	return $new_c;
}

/**
* Outputs the custom attachments column in the Media Library table
* 
* @param string $column_name
* @param integer $post_id
*/
function mbsb_output_custom_media_columns ($column_name, $post_id) {
	if ($column_name == 'attached_to') {
		$sermons = mbsb_get_sermons_from_media_id ($post_id);
		if ($sermons) {
			$output = array();
			foreach ($sermons as $sermon) {
				$title =_draft_or_post_title ($sermon->id);
				$output[] = '<strong>'.(current_user_can ('edit_post', $sermon->id) ? ("<a href=\"".get_edit_post_link ($sermon->id)."\">{$title}</a>") : $title).'</strong>, '.get_the_time (__('Y/m/d'), $sermon->id);
			}
		}
		$post = get_post($post_id);
		if ($post->post_parent > 0) {
			$parent = get_post($post->post_parent);
			if ($parent && $parent->post_type != 'mbsb_sermon') {
				$title =_draft_or_post_title ($post->post_parent);
				$output[] = '<strong>'.(current_user_can ('edit_post', $post->post_parent) ? ("<a href=\"".get_edit_post_link ($post->post_parent)."\">{$title}</a>") : $title).'</strong>, '.get_the_time (__('Y/m/d'), $post);
			}
		} else {
			$output[] = __( '(Unattached)' ).(current_user_can ('edit_post', $post_id) ? ("<br/><a class=\"hide-if-no-js\" onclick=\"findPosts.open( 'media[]','{$post->ID}' ); return false;\" href=\"#the-list\">".__('Attach').'</a>') : '');
		}
		if (isset($output))
			echo implode ('<br/>', $output);
	}
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
	if ($screen->base == 'post' && $screen->id == 'mbsb_sermon') {
		wp_enqueue_style ('thickbox');
		wp_enqueue_script('mbsb_add_edit_sermon', home_url("?mbsb_script=add-edit-sermons&locale=".get_locale()), array ('jquery', 'thickbox', 'media-upload'), @filemtime(mbsb_plugin_dir_path('js/add-edit-sermons.php')));
	}
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
* Ensures the media upload box always includes the 'insert into post' button
* 
* Filters 'get_media_item_args'
* @link http://fullrefresh.com/2012/03/09/wp-custom-post-types-ui-and-insert-into-post/
* 
* @param array $args
* @return array
*/
function mbsb_force_insert_post_on_media_popup ($args) {
	if (isset ($_GET['post_id']) && get_post_type ($_GET['post_id']) == 'mbsb_sermon')
		$args ['send'] = true;
	return $args;
}

/**
* Optionally removes all but one tab from the media uploader
* 
* Triggered by adding a 'tab=xxxx' parameter when calling the media uploader
* 
* @param array $tabs
* @return array
*/
function mbsb_filter_media_upload_tabs ($tabs) {
	if (isset($_GET['showtab']))
		return array ($_GET['showtab'] => $tabs[$_GET['showtab']]);
	else
		return $tabs;
}

/**
* Filters manage_mbsb_sermon_posts_columns (i.e. the names of additional columns when sermons are displayed in admin)
* 
* Adds the new columns required.
* 
* @param array $columns
* @return array
*/
function mbsb_add_sermon_columns($columns) {
	$new_columns ['cb'] = $columns['cb'];
	$new_columns ['title'] = $columns ['title'];
	$new_columns ['passages'] = __('Bible passages', MBSB);
	$new_columns ['preacher'] = __('Preacher', MBSB);
	$new_columns ['service'] = __('Service', MBSB);
	$new_columns ['series'] = _x('Series', 'Singular', MBSB);
	$new_columns ['media'] = __('Media', MBSB);
	$new_columns ['tags'] = __('Tags', MBSB);
	$new_columns ['stats'] = __('Stats', MBSB);
	if (post_type_supports ('mbsb_sermon', 'comments'))
		$new_columns ['comments'] = $columns ['comments'];
	$new_columns ['sermon_date'] = $columns ['date'];
	return $new_columns;
}

/**
* Filters manage_edit-mbsb_sermon_sortable_columns (i.e. the list of sortable columns when sermons are displayed in admin)
* 
* Indicates which columns are sortable.
* 
* @param array $columns
* @return array
*/
function mbsb_make_sermon_columns_sortable ($columns) {
	$columns ['passages'] = 'passages';
	$columns ['preacher'] = 'preacher';
	$columns ['service'] = 'service';
	$columns ['series'] = 'series';
	$columns ['stats'] = 'stats';
	$columns ['sermon_date'] = 'sermon_date';
	return $columns;
}

/**
* Filters manage_mbsb_series_posts_columns (i.e. the names of additional columns when series are displayed in admin)
* 
* Adds the new columns required.
* 
* @param array $columns
* @return array
*/
function mbsb_add_series_columns($columns) {
	$new_columns ['cb'] = $columns['cb'];
	$new_columns ['title'] = $columns ['title'];
	$new_columns ['num_sermons'] = __('Sermons', MBSB);
	$new_columns ['image'] = __('Image', MBSB);
	if (post_type_supports ('mbsb_series', 'comments'))
		$new_columns ['comments'] = $columns ['comments'];
	return $new_columns;
}

/**
* Filters manage_mbsb_preacher_posts_columns (i.e. the names of additional columns when preachers are displayed in admin)
* 
* Adds the new columns required.
* 
* @param array $columns
* @return array
*/
function mbsb_add_preacher_columns($columns) {
	$new_columns ['cb'] = $columns['cb'];
	$new_columns ['title'] = $columns ['title'];
	$new_columns ['num_sermons'] = __('Sermons', MBSB);
	$new_columns ['image'] = __('Image', MBSB);
	if (post_type_supports ('mbsb_preacher', 'comments'))
		$new_columns ['comments'] = $columns ['comments'];
	return $new_columns;
}

/**
* Filters manage_mbsb_service_posts_columns (i.e. the names of additional columns when services are displayed in admin)
* 
* Adds the new columns required.
* 
* @param array $columns
* @return array
*/
function mbsb_add_service_columns($columns) {
	$new_columns ['cb'] = $columns['cb'];
	$new_columns ['title'] = $columns ['title'];
	$new_columns ['num_sermons'] = __('Sermons', MBSB);
	$new_columns ['image'] = __('Image', MBSB);
	if (post_type_supports ('mbsb_service', 'comments'))
		$new_columns ['comments'] = $columns ['comments'];
	return $new_columns;
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
	if ($_GET['post_type'] == 'mbsb_sermon') {
		if ((isset($_GET['orderby']) && $_GET['orderby'] == 'preacher') || isset($_GET['preacher']) || isset($_GET['s']))
			$join .= mbsb_join_preacher ('');
		if ((isset($_GET['orderby']) && $_GET['orderby'] == 'service') || isset($_GET['service']) || isset($_GET['s']))
			$join .= mbsb_join_service ('');
		if ((isset($_GET['orderby']) && $_GET['orderby'] == 'series') || isset($_GET['series']) || isset($_GET['s']))
			$join .= mbsb_join_series ('');
		if (isset($_GET['book']))
			$join .= mbsb_join_book ('');
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
		if ($_GET['post_type'] == 'mbsb_sermon') {
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
	if ($_GET['post_type'] == 'mbsb_sermon') {
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
	if ($_GET['post_type'] == 'mbsb_sermon') {
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
	if ($_GET['post_type'] == 'mbsb_sermon') {
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
		if ($_GET['post_type'] == 'mbsb_sermon')
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
* Handles the wp_ajax_mbsb_attachment_insert AJAX request, which adds a media library attachment
*/
function mbsb_ajax_attachment_insert() {
	global $wpdb;
	if (!check_ajax_referer ("mbsb_attachment_insert"))
		die ('Suspicious behaviour blocked');
	add_filter ('posts_where_paged', 'mbsb_add_guid_to_where');
	$attachment = get_posts (array ('numberposts' => 1, 'post_type' => 'attachment', 'post_status' => null, 'suppress_filters' => false));
	remove_filter ('posts_where_paged', 'mbsb_add_guid_to_where');
	$attachment = $attachment[0];
	$sermon = new mbsb_sermon($_POST['post_id']);
	add_filter ('mbsb_attachment_row_actions', 'mbsb_add_admin_attachment_row_actions');
	if ($result = $sermon->attachments->add_library_attachment ($attachment->ID))
		echo $result->get_json_attachment_row();
	elseif ($result === NULL)
		echo mbsb_single_media_attachment::get_json_attachment_row(false, sprintf(__('%s is already attached to this sermon.', MBSB), $attachment->post_title));
	else
		echo mbsb_single_media_attachment::get_json_attachment_row(false, sprintf(__('There was an error attaching %s to the sermon.', MBSB), "<strong>{$attachment->post_title}</strong>"));
	die();
}

/**
* Provides the SQL code required to find library attachment from the URL
* 
* Designed to be used by the posts_where_paged filter
* 
* @param mixed $where
*/
function mbsb_add_guid_to_where ($where) {
	return "{$where} AND guid='{$_POST['url']}'";
}

/**
* Handles the mbsb_ajax_attach_url_embed AJAX request, which adds a URL or embed attachment
*/
function mbsb_ajax_attach_url_embed() {
	if (!check_ajax_referer ("mbsb_handle_url_embed"))
		die ('Suspicious behaviour blocked');
	$sermon = new mbsb_sermon($_POST['post_id']);
	add_filter ('mbsb_attachment_row_actions', 'mbsb_add_admin_attachment_row_actions');
	if ($_POST['type'] == 'embed') {
		$result = $sermon->attachments->add_embed_attachment ($_POST['attachment']);
		if ($result === null)
			echo mbsb_single_media_attachment::get_json_attachment_row(false, __('That code is not acceptable.', MBSB));
		elseif ($result === FALSE)
			echo mbsb_single_media_attachment::get_json_attachment_row(false, __('There was an error attaching that embed code to the sermon.', MBSB));
		else
			echo $result->get_json_attachment_row();
	} elseif ($_POST['type'] == 'url') {
		if (strtolower(substr($_POST['attachment'], 0, 4)) != 'http')
			$_POST['attachment'] = "http://{$_POST['attachment']}";
		$result = $sermon->attachments->add_url_attachment ($_POST['attachment']);
		if ($result === null)
			echo mbsb_single_media_attachment::get_json_attachment_row(false, __('That does not appear to be a valid URL.', MBSB));
		elseif ($result === FALSE)
			echo mbsb_single_media_attachment::get_json_attachment_row(false, __('There was an error attaching that URL to the sermon.', MBSB));
		else
			echo $result->get_json_attachment_row();
	}
	die();
}

/**
* Handles the AJAX request for unattaching a media attachment
*/
function mbsb_ajax_mbsb_remove_attachment() {
	if (!check_ajax_referer ("mbsb_remove_attachment"))
		die ('Suspicious behaviour blocked');
	$result = delete_metadata_by_mid('post', $_POST['attachment_id']);
	if ($result)
		echo json_encode(array('result' => 'success', 'row_id' => $_POST['attachment_id']));
	else
		echo json_encode (array('result' => 'failure', 'message' => __('The attachment could not be deleted.', MBSB)));
	die();
}

/**
* Adds the metaboxes needed when editing/creating a sermon.
* 
* Also removes unwanted metaboxes and adds required styles and javascripts.
*/
function mbsb_sermon_meta_boxes () {
	add_meta_box ('mbsb_sermon_media', __('Media', MBSB), 'mbsb_sermon_media_meta_box', 'mbsb_sermon', 'normal', 'high');
	add_meta_box ('mbsb_sermon_details', __('Details', MBSB), 'mbsb_sermon_details_meta_box', 'mbsb_sermon', 'normal', 'high');
	add_meta_box ('mbsb_description', __('Description', MBSB), 'mbsb_sermon_editor_box', 'mbsb_sermon', 'normal', 'default');
	remove_meta_box ('slugdiv', 'mbsb_sermon', 'normal');
	wp_enqueue_script ('jquery-ui-datepicker');
	wp_enqueue_style('mbsb_jquery_ui');
	add_filter ('mbsb_attachment_row_actions', 'mbsb_add_admin_attachment_row_actions');
}

/**
* Outputs the main sermons details metabox when editing/creating a sermon
*/
function mbsb_sermon_details_meta_box() {
	global $post;
	//Todo: Pre-populate fields with defaults
	wp_nonce_field (__FUNCTION__, 'details_nonce', true);
	$sermon = new mbsb_sermon ($post->ID);
	$screen = get_current_screen();
	echo '<table class="sermon_details">';
	echo '<tr><th scope="row"><label for="mbsb_preacher">'.__('Preacher', MBSB).':</label></th><td><select id="mbsb_preacher" name="mbsb_preacher">'.mbsb_return_select_list('preacher', $sermon->preacher->get_id(), array ('new_preacher' => __('Add a new preacher', MBSB))).'</select></td></tr>';
	echo '<tr><th scope="row"><label for="mbsb_series">'.__('Series', MBSB).':</label></th><td><select id="mbsb_series" name="mbsb_series">'.mbsb_return_select_list('series', $sermon->series->get_id(), array ('new_series' => __('Add a new series', MBSB))).'</select></td></tr>';
	echo '<tr><th scope="row"><label for="mbsb_service">'.__('Service', MBSB).':</label></th><td><select id="mbsb_service" name="mbsb_service">'.mbsb_return_select_list('service', $sermon->service->get_id(), array ('new_service' => __('Add a new service', MBSB))).'</select></td></tr>';
	echo '<tr><th scope="row"><label for="mbsb_date">'.__('Date', MBSB).':</label></th><td><span class="time_input"><input id="mbsb_date" name="mbsb_date" type="text" class="add-date-picker" value="'.$sermon->date.'"/></td><td><label for="mbsb_date">'.__('Time', MBSB).':</label></td><td><input id="mbsb_time" name="mbsb_time" type="text" value="'.($screen->action == 'add' ? $sermon->service->get_time() : $sermon->time).'"/></span> <input type="checkbox" id="mbsb_override_time" name="mbsb_override_time"'.($sermon->override_time ? ' checked="checked"' : '').'/> <label for="mbsb_override_time" style="font-weight:normal">'.__('Override default time', MBSB).'</label></td></tr>';
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
	$types = array ('none' => array ('label' => '', 'div' => ''),
					'upload' => array ('label' => __('Upload a new file', MBSB), 'div' => '<div id="upload-select" style="display:none"><input type="button" value="'.__('Select file', MBSB).'" class="button-secondary" id="mbsb_upload_media_button" name="mbsb_upload_media_button"></div>'),
					'insert' => array ('label' => __('Insert from the Media Library', MBSB), 'div' => '<div id="insert-select" style="display:none"><input type="button" value="'.__('Insert item', MBSB).'" class="button-secondary" id="mbsb_insert_media_button" name="mbsb_insert_media_button"></div>'),
					'url' => array ('label' => __('Enter an external URL', MBSB), 'div' => '<div id="url-select" style="display:none"><input type="text" name="mbsb_input_url" id="mbsb_input_url" size="30"/><input type="button" value="'.__('Attach', MBSB).'" class="button-secondary" id="mbsb_attach_url_button" name="mbsb_attach_url_button"></div>'),
					'embed' => array ('label' => __('Enter an embed code', MBSB), 'div' => '<div id="embed-select" style="display:none"><input type="text" name="mbsb_input_embed" id="mbsb_input_embed" size="60"/><input type="button" value="'.__('Attach', MBSB).'" class="button-secondary" id="mbsb_attach_embed_button" name="mbsb_attach_embed_button"></div>')
					);
	$types = apply_filters ('mbsb_add_media_types', $types);
	foreach ($types as $type => $data)
		echo "<option ".($type == 'none' ? 'selected="selected" ' : '')."value=\"{$type}\">{$data['label']}&nbsp;</option>";
	echo '</select></td><td>';
	foreach ($types as $type => $data)
		echo $data ['div'];
	echo '</td></tr>';
	echo '</table>';
	echo '<table id="mbsb_attached_files" cellspacing="0" class="wp-list-table widefat fixed media">';
	$sermon = new mbsb_sermon($post->ID);
	$attachments = $sermon->attachments->get_attachments(true);
	if ($attachments)
		foreach ($attachments as $attachment)
			echo $attachment->get_admin_attachment_row ();
	echo '</table>';
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
* Adds the metabox needed when editing/creating a service.
*/
function mbsb_service_meta_boxes () {
	add_meta_box ('mbsb_service_details', __('Details and description', MBSB), 'mbsb_service_details_meta_box', 'mbsb_service', 'normal', 'high');
	remove_meta_box ('slugdiv', 'mbsb_service', 'normal');
}

/**
* Adds the service details metabox
*/
function mbsb_service_details_meta_box () {
	global $post;
	wp_nonce_field (__FUNCTION__, 'details_nonce', true);
	$service = new mbsb_service ($post->ID);
	echo '<table class="series_details">';
	echo '<tr><th scope="row"><label for="mbsb_service_time">'.__('Service time', MBSB).':</label></th>';
	echo '<td><input type="text" id="mbsb_service_time" name="mbsb_service_time" value="'.$service->get_time().'"/></td>';
	echo '<td>'.__('e.g. <b>13:45</b>, or <b>6pm</b>.').'</td></tr>';
	echo '</table>';
	wp_editor ($post->post_content, 'content', array ('media_buttons' => false, 'textarea_rows' => 5));
}

/**
* Add Sermons menu and sub-menus in admin
*/
function mbsb_add_admin_menu() {
	add_menu_page(__('Sermons', MBSB), __('Sermons', MBSB), 'publish_posts', 'sermon-browser', 'sb_manage_sermons', mbsb_plugins_url('images/icon-16-color.png', __FILE__), 21);
	add_submenu_page('sermon-browser', __('Files', MBSB), __('Files', MBSB), 'upload_files', 'sermon-browser/files.php', 'sb_files');
	add_submenu_page('sermon-browser', __('Options', MBSB), __('Options', MBSB), 'manage_options', 'sermon-browser/options.php', 'sb_options');
	add_submenu_page('sermon-browser', __('Templates', MBSB), __('Templates', MBSB), 'manage_options', 'sermon-browser/templates.php', 'sb_templates');
	add_submenu_page('sermon-browser', __('Uninstall', MBSB), __('Uninstall', MBSB), 'edit_plugins', 'sermon-browser/uninstall.php', 'sb_uninstall');
	add_submenu_page('sermon-browser', __('Help', MBSB), __('Help', MBSB), 'publish_posts', 'sermon-browser/help.php', 'sb_help');
	add_submenu_page('sermon-browser', __('Pray for Japan', MBSB), __('Pray for Japan', MBSB), 'publish_posts', 'sermon-browser/japan.php', 'sb_japan');
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
	if (!empty($_POST) && isset($_POST['action']) && $_POST['action'] == 'editpost' && $_POST['post_type'] == 'mbsb_sermon' && !(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) && check_admin_referer ('mbsb_sermon_details_meta_box', 'details_nonce')) {
		$sermon = new mbsb_sermon ($post_id);
		$sermon->update_preacher ($_POST['mbsb_preacher']);
		$sermon->update_series ($_POST['mbsb_series']);
		$sermon->update_service ($_POST['mbsb_service']);
		$sermon->update_override_time (isset ($_POST['mbsb_override_time']));
		$sermon->update_passages ($_POST['mbsb_passages']);
	} elseif (!empty($_POST) && isset($_POST['action']) && $_POST['action'] == 'editpost' && $_POST['post_type'] == 'mbsb_service' && !(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) && check_admin_referer ('mbsb_service_details_meta_box', 'details_nonce')) {
		$service = new mbsb_service($post_id);
		$service->set_time($_POST['mbsb_service_time']);
	}
}

/**
* Returns a row for the attached media table, containing a message rather than a successful result
* 
* @param string $message
* @return string
*/
function mbsb_do_media_row_message ($message) {
	return '<tr><td><div class="message">'.$message.'</div></td></tr>';
}

/**
* Returns an array of sermon objects that have a particular media item attached
* 
* @param integer $post_id - the post id of the media item
* @return boolean|array - False on failure, an array of sermon objects on success.
*/
function mbsb_get_sermons_from_media_id ($post_id) {
	$meta_value = serialize(array ('type' => 'library', 'post_id' => (string)$post_id));
	$sermons = query_posts (array ('post_type' => 'mbsb_sermon', 'meta_query' => array (array('key' => 'attachments', 'value' => $meta_value))));
	wp_reset_query();
	if ($sermons) {
		foreach ($sermons as &$s)
			$s = new mbsb_sermon($s->ID);
		return $sermons;
	} else
		return false;
}

/**
* Returns an array of sermon objects that have a particular media item attached
* 
* @param integer $post_id - the post id of the media item
* @return boolean|array - False on failure, an array of sermon objects on success.
*/
function mbsb_unattach_media_item_from_all_sermons ($media_post_id) {
	$meta_value = serialize(array ('type' => 'library', 'post_id' => (string)$media_post_id));
	$meta_ids = mbsb_get_meta_ids_by_value ($meta_value);
	if ($meta_ids)
		foreach ($meta_ids as $meta_id)
			delete_metadata_by_mid('post', $meta_id);
}

/**
* Runs on the delete_post action and removes deleted media items from their sermons.
* 
* @param integer $post_id
*/
function mbsb_handle_media_deletion ($post_id) {
	$post = get_post ($post_id);
	if ($post->post_type == 'attachment')
		mbsb_unattach_media_item_from_all_sermons ($post_id);
}


/**
* Filters admin_body_class so that we can apply CSS styling to particular post_types
* 
* @param string $class
* @return string
*/
function mbsb_admin_body_class ($class) {
	$screen = get_current_screen();
	return "{$class} {$screen->base}_{$screen->id}";
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
	if ($text == 'Insert into Post' && ((isset($_GET['referer']) && $_GET['referer'] == 'mbsb_sermon') || strpos(wp_get_referer(), 'referer=mbsb_sermon')))
		return __('Attach to sermon', MBSB);
	if ($text == 'Publish' && isset($_GET['post_type']) && (substr($_GET['post_type'], 0, 5) == 'mbsb_'))
		return __('Save', MBSB);
	if ($text == 'Publish' && isset($_GET['post'])) {
		$screen = get_current_screen();
		if (substr($screen->post_type, 0, 5) == 'mbsb_')
			return __('Save', MBSB);
	}
	return $translated_text;
}

/**
* Sets the default sort order for metaboxes
* 
* Used as a filter for get_user_option_meta-box-order_*
* 
* @param array $result - the existing sort order
* @param string $option - the option being requested
* @param WP_User $user - the current user
* @return array
*/
function mbsb_set_default_metabox_sort_order ($result, $option, $user) {
	if ($option == 'meta-box-order_mbsb_sermon' && empty($result))
		return array ('advanced' => '', 'normal' => 'mbsb_sermon_details,mbsb_sermon_media,mbsb_description,commentstatusdiv,commentsdiv', 'side' => 'submitdiv,tagsdiv-post_tag,postimagediv');
	elseif ($option == 'meta-box-order_mbsb_service' && empty($result))
		return array ('advanced' => '', 'normal' => 'mbsb_service_details,commentstatusdiv,commentsdiv', 'side' => 'submitdiv,postimagediv');
	elseif ($option == 'meta-box-order_mbsb_service' && isset($_GET['iframe']) && $_GET['iframe'] == 'true')
		return array ('advanced' => '', 'normal' => 'mbsb_service_details,commentstatusdiv,commentsdiv,postimagediv,submitdiv');
	elseif (($option == 'meta-box-order_mbsb_preacher' || $option == 'meta-box-order_mbsb_series') && isset($_GET['iframe']) && $_GET['iframe'] == 'true')
		return array ('advanced' => '', 'normal' => 'postimagediv,commentstatusdiv,submitdiv', 'side' => '');
	else
		return $result;
}

/**
* Adds CSS to the <head> section to remove unwanted information
* 
* Is called only when adding preachers/series/services in thickbox popups
* 
*/
function mbsb_tb_iframe_admin_head() {
	global $post;
	echo "<style type=\"text/css\">";
	echo "#adminmenuback, #adminmenuwrap, #screen-meta-links, #wpadminbar, #footer {display:none}";
	echo "#wpcontent {margin-left:15px}";
	echo "html.wp-toolbar {padding-top:0}";
	echo "</style>\r\n";
    echo "<script type=\"text/javascript\">jQuery(document).ready(function(\$) { \$('#publish').click(function() {var name = \$('#title').val(); parent.add_new_select('{$_GET['post_type']}', name, '{$post->ID}'); });});</script>";
}

/**
* Filters mbsb_attachment_row_actions
* 
* Returns the HTML of the attachment link in the media library table on the edit sermons page.
* 
* @param string $actions
* @return string
*/
function mbsb_add_admin_attachment_row_actions($existing_actions) {
	return '<a class="unattach" href="#">'.__('Unattach', MBSB).'</a>';
}

function mbsb_ajax_mbsb_get_bible_text() {
	$sermon = new mbsb_sermon ($_POST['post_id']);
	$text = $sermon->passages->get_text_output($_POST['version']);
	echo $text;
	die();
}

function mbsb_ajax_mbsb_get_preacher_details() {
	$sermon = new mbsb_sermon ($_POST['post_id']);
	if ($sermon->preacher->present)
		echo $sermon->preacher->get_output();
	die();
}

function mbsb_ajax_mbsb_get_series_details() {
	$sermon = new mbsb_sermon ($_POST['post_id']);
	if ($sermon->series->present)
		echo $sermon->series->get_output();
	die();
}

function mbsb_ajax_mbsb_get_service_details() {
	$sermon = new mbsb_sermon ($_POST['post_id']);
	if ($sermon->service->present)
		echo $sermon->service->get_output();
	die();
}

function mbsb_post_updated_messages($messages) {
	$post_ID = (int)$_GET['post'];
	$post = get_post ($post_id);
	if ($post->post_type == 'mbsb_sermon')
		$messages['mbsb_sermon'] = array(
			 0 => '', // Unused. Messages start at index 1.
			 1 => sprintf (__('Sermon updated. <a href="%s">View sermon</a>', MBSB), esc_url (get_permalink ($post_ID))),
			 2 => __('Custom field updated.'),
			 3 => __('Custom field deleted.'),
			 4 => __('Sermon updated.', MBSB),
			 5 => isset($_GET['revision']) ? sprintf( __('Sermon restored to revision from %s', MBSB), wp_post_revision_title ((int) $_GET['revision'], false)) : false,
			 6 => sprintf( __('Sermon published. <a href="%s">View sermon</a>', MBSB), esc_url (get_permalink ($post_ID))),
			 7 => __('Sermon saved.', MBSB),
			 8 => sprintf( __('Sermon submitted. <a target="_blank" href="%s">Preview sermon</a>', MBSB), esc_url (add_query_arg ('preview', 'true', get_permalink ($post_ID)))),
			 9 => sprintf( __('Sermon scheduled for: <strong>%1$s</strong>. <a target="_blank" href="%2$s">Preview sermon</a>'), date_i18n (__('M j, Y @ G:i'), strtotime ($post->post_date)), esc_url (get_permalink($post_ID))),
			10 => sprintf( __('Sermon draft updated. <a target="_blank" href="%s">Preview sermon</a>'), esc_url (add_query_arg ('preview', 'true', get_permalink ($post_ID)))),
		);
	elseif ($post->post_type == 'mbsb_series')
		$messages['mbsb_series'] = array(
			 0 => '', // Unused. Messages start at index 1.
			 1 => sprintf (__('Series updated. <a href="%s">View series</a>', MBSB), esc_url (get_permalink ($post_ID))),
			 2 => __('Custom field updated.'),
			 3 => __('Custom field deleted.'),
			 4 => __('Series updated.', MBSB),
			 5 => isset($_GET['revision']) ? sprintf( __('Series restored to revision from %s', MBSB), wp_post_revision_title ((int) $_GET['revision'], false)) : false,
			 6 => sprintf( __('Series published. <a href="%s">View series</a>', MBSB), esc_url (get_permalink ($post_ID))),
			 7 => __('Series saved.', MBSB),
			 8 => sprintf( __('Series submitted. <a target="_blank" href="%s">Preview series</a>', MBSB), esc_url (add_query_arg ('preview', 'true', get_permalink ($post_ID)))),
			 9 => sprintf( __('Series scheduled for: <strong>%1$s</strong>. <a target="_blank" href="%2$s">Preview series</a>'), date_i18n (__('M j, Y @ G:i'), strtotime ($post->post_date)), esc_url (get_permalink($post_ID))),
			10 => sprintf( __('Series draft updated. <a target="_blank" href="%s">Preview series</a>'), esc_url (add_query_arg ('preview', 'true', get_permalink ($post_ID)))),
		);
	elseif ($post->post_type == 'mbsb_preacher')
		$messages['mbsb_preacher'] = array(
			 0 => '', // Unused. Messages start at index 1.
			 1 => sprintf (__('Preacher updated. <a href="%s">View series</a>', MBSB), esc_url (get_permalink ($post_ID))),
			 2 => __('Custom field updated.'),
			 3 => __('Custom field deleted.'),
			 4 => __('Preacher updated.', MBSB),
			 5 => isset($_GET['revision']) ? sprintf( __('Preacher restored to revision from %s', MBSB), wp_post_revision_title ((int) $_GET['revision'], false)) : false,
			 6 => sprintf( __('Preacher published. <a href="%s">View preacher</a>', MBSB), esc_url (get_permalink ($post_ID))),
			 7 => __('Preacher saved.', MBSB),
			 8 => sprintf( __('Preacher submitted. <a target="_blank" href="%s">Preview preacher</a>', MBSB), esc_url (add_query_arg ('preview', 'true', get_permalink ($post_ID)))),
			 9 => sprintf( __('Preacher scheduled for: <strong>%1$s</strong>. <a target="_blank" href="%2$s">Preview preacher</a>'), date_i18n (__('M j, Y @ G:i'), strtotime ($post->post_date)), esc_url (get_permalink($post_ID))),
			10 => sprintf( __('Preacher draft updated. <a target="_blank" href="%s">Preview preacher</a>'), esc_url (add_query_arg ('preview', 'true', get_permalink ($post_ID)))),
		);
	elseif ($post->post_type == 'mbsb_service')
		$messages['mbsb_service'] = array(
			 0 => '', // Unused. Messages start at index 1.
			 1 => sprintf (__('Service updated. <a href="%s">View series</a>', MBSB), esc_url (get_permalink ($post_ID))),
			 2 => __('Custom field updated.'),
			 3 => __('Custom field deleted.'),
			 4 => __('Service updated.', MBSB),
			 5 => isset($_GET['revision']) ? sprintf( __('Service restored to revision from %s', MBSB), wp_post_revision_title ((int) $_GET['revision'], false)) : false,
			 6 => sprintf( __('Service published. <a href="%s">View service</a>', MBSB), esc_url (get_permalink ($post_ID))),
			 7 => __('Service saved.', MBSB),
			 8 => sprintf( __('Service submitted. <a target="_blank" href="%s">Preview service</a>', MBSB), esc_url (add_query_arg ('preview', 'true', get_permalink ($post_ID)))),
			 9 => sprintf( __('Service scheduled for: <strong>%1$s</strong>. <a target="_blank" href="%2$s">Preview service</a>'), date_i18n (__('M j, Y @ G:i'), strtotime ($post->post_date)), esc_url (get_permalink($post_ID))),
			10 => sprintf( __('Service draft updated. <a target="_blank" href="%s">Preview service</a>'), esc_url (add_query_arg ('preview', 'true', get_permalink ($post_ID)))),
		);
	return $messages;
}
?>