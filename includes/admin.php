<?php
/**
* Include file called when is_admin() is true
* 
* @package SermonBrowser
* @subpackage Admin
* @author Mark Barnes <mark@sermonbrowser.com>
*/
add_action ('admin_init', 'mbsb_admin_init');
if (!empty($GLOBALS['pagenow']) and ((($GLOBALS['pagenow'] == 'admin.php') and ($_GET['page'] == 'sermon-browser_options')) or ($GLOBALS['pagenow'] == 'options.php'))) {
	add_action ('admin_init', 'mbsb_options_page_init');
}

/**
* Runs on the admin_init action.
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
	add_action ('wp_ajax_mbsb_attach_legacy', 'mbsb_ajax_attach_legacy');
	add_action ('wp_ajax_mbsb_remove_attachment', 'mbsb_ajax_mbsb_remove_attachment');
	add_action ('wp_ajax_mbsb_get_bible_text', 'mbsb_ajax_mbsb_get_bible_text');
	add_action ('wp_ajax_nopriv_mbsb_get_bible_text', 'mbsb_ajax_mbsb_get_bible_text');
	add_action ('wp_ajax_mbsb_get_preacher_details', 'mbsb_ajax_mbsb_get_preacher_details');
	add_action ('wp_ajax_nopriv_mbsb_get_preacher_details', 'mbsb_ajax_mbsb_get_preacher_details');
	add_action ('wp_ajax_mbsb_get_service_details', 'mbsb_ajax_mbsb_get_service_details');
	add_action ('wp_ajax_nopriv_mbsb_get_service_details', 'mbsb_ajax_mbsb_get_service_details');
	add_action ('wp_ajax_mbsb_get_series_details', 'mbsb_ajax_mbsb_get_series_details');
	add_action ('wp_ajax_nopriv_mbsb_get_series_details', 'mbsb_ajax_mbsb_get_series_details');
	add_action ('wp_ajax_mbsb_jqueryFileTree', 'mbsb_ajax_jqueryFileTree');
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
	if (isset($_GET['showtab']))
		$_GET['tab'] = $_GET['showtab'];
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
	if (substr($screen->post_type, 0, 5) == 'mbsb_') {
		add_filter ("get_user_option_meta-box-order_{$screen->post_type}", 'mbsb_set_default_metabox_sort_order', 10, 3);
		wp_enqueue_script('mbsb_jqueryFileTree_js', mbsb_plugins_url('lib/jqueryFileTree/jqueryFileTree.js'), array('jquery'), '1.01.01');
		wp_enqueue_style('mbsb_jqueryFileTree_css', mbsb_plugins_url('lib/jqueryFileTree/jqueryFileTree.css'), false, '1.01.01');
	}
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
				$title =_draft_or_post_title ($sermon->get_id());
				$output[] = '<strong>'.(current_user_can ('edit_post', $sermon->get_id()) ? ("<a href=\"".get_edit_post_link ($sermon->get_id())."\">{$title}</a>") : $title).'</strong>, '.get_the_time (__('Y/m/d'), $sermon->get_id());
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
* Triggered by adding a 'showtab=xxxx' parameter when calling the media uploader
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
* @param string $where
* @return string
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
* Handles the mbsb_ajax_attach_legacy AJAX request, which adds a legacy attachment
*/
function mbsb_ajax_attach_legacy() {
	if (!check_ajax_referer ('mbsb_handle_legacy'))
		die ('Suspicious behaviour blocked');
	$sermon = new mbsb_sermon($_POST['post_id']);
	add_filter ('mbsb_attachment_row_actions', 'mbsb_add_admin_attachment_row_actions');
	$result = $sermon->attachments->add_legacy_attachment ($_POST['attachment']);
	if ($result === null)
		echo mbsb_single_media_attachment::get_json_attachment_row(false, __('That file was not found.', MBSB));
	elseif ($result === FALSE)
		echo mbsb_single_media_attachment::get_json_attachment_row(false, __('There was an error attaching that file to the sermon.', MBSB));
	else
		echo $result->get_json_attachment_row();
	die();
}

/**
* Handles the mbsb_jqueryFileTree AJAX request, the connector script for the legacy file picker
*/
function mbsb_ajax_jqueryFileTree() {
	if (!check_ajax_referer ("mbsb_jqueryFileTree"))
		die ('Suspicious behaviour blocked');
	$root = trailingslashit(mbsb_get_home_path()).mbsb_get_option('legacy_upload_folder');
	$_POST['dir'] = urldecode($_POST['dir']);
	if( file_exists($root . $_POST['dir']) ) {
		$files = scandir($root . $_POST['dir']);
		natcasesort($files);
		if( count($files) > 2 ) { /* The 2 accounts for . and .. */
			echo "<ul class=\"jqueryFileTree\" style=\"display: none;\">";
			// All dirs
			foreach( $files as $file ) {
				if( file_exists($root . $_POST['dir'] . $file) && $file != '.' && $file != '..' && is_dir($root . $_POST['dir'] . $file) ) {
					echo "<li class=\"directory collapsed\"><a href=\"#\" rel=\"" . htmlentities($_POST['dir'] . $file) . "/\">" . htmlentities($file) . "</a></li>";
				}
			}
			// All files
			foreach( $files as $file ) {
				if( file_exists($root . $_POST['dir'] . $file) && $file != '.' && $file != '..' && !is_dir($root . $_POST['dir'] . $file) ) {
					$ext = preg_replace('/^.*\./', '', $file);
					echo "<li class=\"file ext_$ext\"><a href=\"#\" rel=\"" . htmlentities($_POST['dir'] . $file) . "\">" . htmlentities($file) . "</a></li>";
				}
			}
			echo "</ul>";	
		}
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
					'embed' => array ('label' => __('Enter an embed code', MBSB), 'div' => '<div id="embed-select" style="display:none"><input type="text" name="mbsb_input_embed" id="mbsb_input_embed" size="60"/><input type="button" value="'.__('Attach', MBSB).'" class="button-secondary" id="mbsb_attach_embed_button" name="mbsb_attach_embed_button"></div>'),
					'legacy' => array ('label' => __('Choose a file from legacy upload folder', MBSB), 'div' => '<div id="legacy-select" style="display:none"><input type="button" value="'.__('Select file', MBSB).'" class="button-secondary" id="mbsb_attach_legacy_button" name="mbsb_attach_legacy_button"></div>')
					);
	$types = apply_filters ('mbsb_add_media_types', $types);
	foreach ($types as $type => $data)
		echo "<option ".($type == 'none' ? 'selected="selected" ' : '')."value=\"{$type}\">{$data['label']}&nbsp;</option>";
	echo '</select></td><td>';
	foreach ($types as $type => $data)
		echo $data ['div'];
	echo '</td></tr>';
	echo '</table>';
	// temp test for jqueryfiletree
	echo '<style type="text/css">#legacy_file_tree {width: 200px; height: 400px; border-top: solid 1px #BBB; border-left: solid 1px #BBB; border-right: solid 1px #FFF; border-bottom: solid 1px #FFF; border-right: solid 1px #FFF; background: #FFF; overflow: scroll; padding: 5px;}</style>', "\n";
	echo '<div style="display:none;" id="legacy_file_tree"></div>', "\n";
	// end temp test for jqueryfiletree
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
	add_submenu_page('sermon-browser', __('Files', MBSB), __('Files', MBSB), 'upload_files', 'sermon-browser_files', 'mbsb_files');
	add_submenu_page('sermon-browser', __('Options', MBSB), __('Options', MBSB), 'manage_options', 'sermon-browser_options', 'mbsb_options_admin_page');
	add_submenu_page('sermon-browser', __('Templates', MBSB), __('Templates', MBSB), 'manage_options', 'sermon-browser_templates', 'mbsb_templates');
	add_submenu_page('sermon-browser', __('Import', MBSB), __('Import', MBSB), 'edit_plugins', 'sermon-browser_import', 'mbsb_import_admin_page');
	add_submenu_page('sermon-browser', __('Uninstall', MBSB), __('Uninstall', MBSB), 'edit_plugins', 'sermon-browser_uninstall', 'mbsb_uninstall_admin_page');
	add_submenu_page('sermon-browser', __('Help', MBSB), __('Help', MBSB), 'publish_posts', 'sermon-browser_help', 'mbsb_help');
	add_submenu_page('sermon-browser', __('Pray for Japan', MBSB), __('Pray for Japan', MBSB), 'publish_posts', 'sermon-browser_japan', 'mbsb_japan');
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
* @param integer $media_post_id - the post id of the media item
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
	elseif ($option == 'meta-box-order_mbsb_service' && isset($_GET['iframe']) && $_GET['iframe'] == 'true')
		return array ('advanced' => '', 'normal' => 'mbsb_service_details,commentstatusdiv,commentsdiv,postimagediv,submitdiv');
	elseif ($option == 'meta-box-order_mbsb_service' && empty($result))
		return array ('advanced' => '', 'normal' => 'mbsb_service_details,commentstatusdiv,commentsdiv', 'side' => 'submitdiv,postimagediv');
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
	echo "#adminmenuback, #adminmenuwrap, #screen-meta-links, #wpadminbar, #footer, #wpfooter {display:none}";
	echo "#wpcontent, .auto-fold #wpcontent {margin-left:15px}";
	echo "#wpbody-content {padding-bottom:0}";
	echo "html.wp-toolbar {padding-top:0}";
	echo "</style>\r\n";
    echo "<script type=\"text/javascript\">jQuery(document).ready(function(\$) { \$('#publish').click(function() {var name = \$('#title').val(); parent.add_new_select('{$_GET['post_type']}', name, '{$post->ID}'); });});</script>";
}

/**
* Filters mbsb_attachment_row_actions
* 
* Returns the HTML of the attachment link in the media library table on the edit sermons page.
* 
* @param string $existing_actions
* @return string
*/
function mbsb_add_admin_attachment_row_actions($existing_actions) {
	return '<a class="unattach" href="#">'.__('Unattach', MBSB).'</a>';
}

/**
* Handles the AJAX call requesting Bible text
*/
function mbsb_ajax_mbsb_get_bible_text() {
	$sermon = new mbsb_sermon ($_POST['post_id']);
	$text = $sermon->passages->get_text_output($_POST['version']);
	// Hack to avoid document.write after the page has loaded
	$script_start = stripos($text, '<script>');
	$script_end = stripos ($text, '</script>', $script_start);
	if ($script_start && $script_end)
		$text = str_replace(array('<noscript>', '</noscript>'), '', substr($text, 0, $script_start).substr($text, $script_end+9));
	echo $text;
	die();
}

/**
* Handles the AJAX call requesting more details of the preacher
*/
function mbsb_ajax_mbsb_get_preacher_details() {
	$sermon = new mbsb_sermon ($_POST['post_id']);
	if ($sermon->preacher->present)
		echo $sermon->preacher->get_output();
	die();
}

/**
* Handles the AJAX call requesting more details of the series
*/
function mbsb_ajax_mbsb_get_series_details() {
	$sermon = new mbsb_sermon ($_POST['post_id']);
	if ($sermon->series->present)
		echo $sermon->series->get_output();
	die();
}

/**
* Handles the AJAX call requesting more details of the service
*/
function mbsb_ajax_mbsb_get_service_details() {
	$sermon = new mbsb_sermon ($_POST['post_id']);
	if ($sermon->service->present)
		echo $sermon->service->get_output();
	die();
}

/**
* Supplies updated messages when a custom post type is saved
* 
* Filters post_updated_messages
* 
* @param array - the existing messages
* @return string
*/
function mbsb_post_updated_messages($messages) {
	$post_ID = (int)$_GET['post'];
	$post = get_post ($post_ID);
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

/**
* Display Import page
*/
function mbsb_import_admin_page() {
	if (isset($_POST['import']))
		mbsb_import_from_SB1();
	global $wpdb;
?>
	<div class="wrap">
		<div id="icon-sermon-browser" class="icon32 icon32-mbsb-import"><br /></div>
		<h2><?php _e('Sermon Browser Import', MBSB); ?></h2>
		<p>
		<?php _e('Sermon Browser 2 can import sermons, series, preachers, and services from Sermon Browser 1.  
		When you import data from SB1, your SB1 data will remain untouched in the database, in case you would like to run SB1 in the future.  
		To remove SB1 data after you import, activate SB1 and choose Uninstall from the SB1 menu.', MBSB); ?>
		</p>
		<p>
		<?php _e('There is no undo for this import function.  However, you can Uninstall SB2, which will remove all SB2 data from the database.  
		Uninstalling will remove imported data as well as any data that you have manually entered into SB2.', MBSB); ?>
		</p>
		<p>
		<?php _e('We recommend that you back up your database before using this import feature.', MBSB); ?>
		</p>
		<hr />
<?php
	$import_count = array();
	$tables = array('sb_sermons', 'sb_series', 'sb_preachers', 'sb_services');
	foreach ($tables as $table) {
		$table_name = $wpdb->prefix.$table;
		if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") == $table_name) {
			$import_count[$table] = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" );
		}
		else
			$import_count[$table] = 0;
	}
	if ( array_values($import_count) === array(0,0,0,0) ) {
?>
		<p>
		<?php _e('There is not any SB1 data found in your database.', MBSB); ?>
		</p>
<?php
	}
	else {
?>
		<p>
		<?php _e('The following SB1 data has been found in your database:', MBSB); ?>
		</p>
		<ul>
			<li><?php echo $import_count['sb_sermons'].' '.__('Sermons', MBSB); ?></li>
			<li><?php echo $import_count['sb_series'].' '.__('Series', MBSB); ?></li>
			<li><?php echo $import_count['sb_preachers'].' '.__('Preachers', MBSB); ?></li>
			<li><?php echo $import_count['sb_services'].' '.__('Services', MBSB); ?></li>
		</ul>

		<form method="post">
		<p class="submit">
			<input type="submit" name="import" value="<?php esc_attr_e('Import data from SB1', MBSB); ?>" onclick="return confirm('<?php esc_attr_e('Do you REALLY want to import data from SB1?', MBSB); ?>')" />
		</p>
		</form>
<?php
	}
?>
	</div><!-- /.wrap -->
<?php
}

/**
* Import data from SB1
*/
function mbsb_import_from_SB1() {
	global $wpdb;
	// Get currently logged in user ID, used as the author of the imported posts
	$current_user_id = wp_get_current_user()->ID;
	// Import Series
	$count_series_imported = 0;
	$count_series_duplicate = 0;
	$count_series_restored = 0;
	$count_series_error = 0;
	$series_xref = array();
	$series_sb1_db = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sb_series", OBJECT_K);
	if ($wpdb->num_rows > 0) {
		foreach ($series_sb1_db as $series_sb1) {
			$series_sb2 = get_page_by_title($series_sb1->name, OBJECT, 'mbsb_series');
			if ($series_sb2 === NULL) {
				// add new series to SB2
				$new_series = array(
					'post_title'    => $series_sb1->name,
					'post_author'   => $current_user_id,
					'post_status'   => 'publish',
					'post_type'     => 'mbsb_series'
				);
				$sb2_series_id = wp_insert_post($new_series);
				if ( $sb2_series_id ) {
					$count_series_imported++;
					$series_xref[$series_sb1->id] = $sb2_series_id;
				}
				else {
					$count_series_error++;
					$series_xref[$series_sb1->id] = 0;
				}
			}
			else {
				// series already exists
				if ($series_sb2->post_status == 'trash') {
					// If series is in the trash, move it out of the trash.
					wp_publish_post($series_sb2->ID);
					$count_series_restored++;
				}
				else {
					// skip import, use existing series
					$count_series_duplicate++;
				}
				$series_xref[$series_sb1->id] = $series_sb2->ID;
			}
		}
	}
	// Import Services
	$count_services_imported = 0;
	$count_services_duplicate = 0;
	$count_services_restored = 0;
	$count_services_error = 0;
	$services_xref = array();
	$services_sb1_db = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sb_services", OBJECT_K);
	if ($wpdb->num_rows > 0) {
		foreach ($services_sb1_db as $service_sb1) {
			$service_sb2 = get_page_by_title($service_sb1->name, OBJECT, 'mbsb_service');
			if ($service_sb2 === NULL) {
				// add new series to SB2
				$new_service = array(
					'post_title'   => $service_sb1->name,
					'post_author'  => $current_user_id,
					'post_status'  => 'publish',
					'post_type'    => 'mbsb_service'
				);
				$sb2_service_id = wp_insert_post($new_service);
				if ( $sb2_service_id ) {
					$count_services_imported++;
					$services_xref[$service_sb1->id] = $sb2_service_id;
					// Add service metadata
					$seconds = strtotime ('1 January 1970 '.trim($service_sb1->time).' UTC');
					if ($seconds and $seconds != '-1')
						update_post_meta ($sb2_service_id, 'mbsb_service_time', $seconds);
				}
				else {
					$count_services_error++;
					$services_xref[$service_sb1->id] = 0;
				}
			}
			else {
				// service already exists
				if ($service_sb2->post_status == 'trash') {
					// If service is in the trash, move it out of the trash.
					wp_publish_post($service_sb2->ID);
					$count_services_restored++;
				}
				else {
					// skip import, use existing series
					$count_services_duplicate++;
				}
				$services_xref[$service_sb1->id] = $service_sb2->ID;
			}
		}
	}
	// Import Preachers
	$count_preachers_imported = 0;
	$count_preachers_duplicate = 0;
	$count_preachers_restored = 0;
	$count_preachers_error = 0;
	$preacher_image_skipped = false;
	$preachers_xref = array();
	$preachers_sb1_db = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sb_preachers", OBJECT_K);
	if ($wpdb->num_rows > 0) {
		foreach ($preachers_sb1_db as $preacher_sb1) {
			$preacher_sb2 = get_page_by_title($preacher_sb1->name, OBJECT, 'mbsb_preacher');
			if ($preacher_sb2 === NULL) {
				// add new preacher to SB2
				$new_preacher = array(
					'post_title'   => $preacher_sb1->name,
					'post_author'  => $current_user_id,
					'post_status'  => 'publish',
					'post_type'    => 'mbsb_preacher',
					'post_content' => $preacher_sb1->description
				);
				$sb2_preacher_id = wp_insert_post($new_preacher);
				if ( $sb2_preacher_id ) {
					$count_preachers_imported++;
					$preachers_xref[$preacher_sb1->id] = $sb2_preacher_id;
					if ($preacher_sb1->image != '')
						$preacher_image_skipped = true;
				}
				else {
					$count_preachers_error++;
					$preachers_xref[$preacher_sb1->id] = 0;
				}
			}
			else {
				// preacher already exists
				if ($preacher_sb2->post_status == 'trash') {
					// If preacher is in the trash, move it out of the trash.
					wp_publish_post($preacher_sb2->ID);
					$count_preachers_restored++;
				}
				else {
					// skip import, use existing preacher
					$count_preachers_duplicate++;
				}
				$preachers_xref[$preacher_sb1->id] = $preacher_sb2->ID;
			}
		}
	}
	// Import Sermons
	$count_sermons_imported = 0;
	$count_sermons_duplicate = 0;
	$count_sermons_restored = 0;
	$count_sermons_error = 0;
	$count_tags = 0;
	$count_attachments = 0;
	$sermons_xref = array();
	$sermons_sb1_db = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sb_sermons", OBJECT_K);
	if ($wpdb->num_rows > 0) {
		foreach ($sermons_sb1_db as $sermon_sb1) {
			$sermons_sb2 = mbsb_get_sermons_by_title($sermon_sb1->title);
			$duplicate_sermon_found = false;
			if ( is_array($sermons_sb2) )
				foreach ($sermons_sb2 as $sermon_sb2) {
					if ( substr($sermon_sb2->post_date, 0, 10) == substr($sermon_sb1->datetime, 0, 10) and $sermon_sb2->post_status != 'trash' ) {
						$duplicate_sermon_found = true;
						$sermons_xref[$sermon_sb1->id] = $sermon_sb2->ID;
					}
				}
			if ( $sermons_sb2 === NULL or !$duplicate_sermon_found ) {
				// add new sermon to SB2
				$new_sermon = array(
					'post_title'   => $sermon_sb1->title,
					'post_author'  => $current_user_id,
					'post_status'  => 'publish',
					'post_type'    => 'mbsb_sermon',
					'post_content' => $sermon_sb1->description,
					'post_date'    => $sermon_sb1->datetime
				);
				$sb2_sermon_id = wp_insert_post($new_sermon);
				if ( $sb2_sermon_id ) {
					$count_sermons_imported++;
					$sb2_sermon_object = new mbsb_sermon($sb2_sermon_id);
					$sermons_xref[$sermon_sb1->id] = $sb2_sermon_id;
					// Add series data
					if ( $sermon_sb1->series_id )
						if ( $series_xref[$sermon_sb1->series_id] )
							$sb2_sermon_object->update_series( $series_xref[$sermon_sb1->series_id] );
					// Add service data
					if ( $sermon_sb1->service_id )
						if ( $services_xref[$sermon_sb1->service_id] )
							$sb2_sermon_object->update_service( $services_xref[$sermon_sb1->service_id] );
					// Add preacher data
					if ( $sermon_sb1->preacher_id )
						if ( $preachers_xref[$sermon_sb1->preacher_id] )
							$sb2_sermon_object->update_preacher( $preachers_xref[$sermon_sb1->preacher_id] );
					// Add tag data
					$sb1_tag_db = $wpdb->get_results( "SELECT sermons_tags.*, tags.name FROM {$wpdb->prefix}sb_sermons_tags as sermons_tags LEFT JOIN {$wpdb->prefix}sb_tags as tags ON sermons_tags.tag_id=tags.id WHERE sermons_tags.sermon_id={$sermon_sb1->id}" );
					if ( $wpdb->num_rows > 0 ) {
						foreach ($sb1_tag_db as $tag) {
							if ($tag->name)
								wp_set_post_tags( $sb2_sermon_id, $tag->name, true );
						}
					}
					// Bible Passages
					$start = unserialize($sermon_sb1->start);
					$end = unserialize($sermon_sb1->end);
					$passages = array();
					$bible_passage_count = count($start);
					for ($i = 0; $i < $bible_passage_count; $i++) {
						if ( $start[$i] and $end[$i] )
							$passages[] = "{$start[$i]['book']} {$start[$i]['chapter']}:{$start[$i]['verse']}-{$end[$i]['book']} {$end[$i]['chapter']}:{$end[$i]['verse']}";
					}
					$passages_string = implode(';',$passages);
					if ($passages_string)
						$sb2_sermon_object->update_passages($passages_string);
					// Media Attachments
					$sb1_attachments = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}sb_stuff WHERE sermon_id={$sermon_sb1->id}" );
					if ( $wpdb->num_rows > 0) {
						$sb1_upload_folder = mbsb_get_sb1_option('upload_dir');
						if ($sb1_upload_folder != null)
							mbsb_update_option( 'legacy_upload_folder', trailingslashit(ltrim($sb1_upload_folder, '/')) );
						foreach ($sb1_attachments as $sb1_attachment) {
							if ($sb1_attachment->type == 'url') {
								$sb2_sermon_object->attachments->add_url_attachment( $sb1_attachment->name );
								$count_attachments++;
							}
							elseif ($sb1_attachment->type == 'file') {
								$sb2_sermon_object->attachments->add_legacy_attachment( $sb1_attachment->name );
								$count_attachments++;
							}
							elseif ($sb1_attachment->type == 'code') {
								$sb2_sermon_object->attachments->add_embed_attachment( base64_decode($sb1_attachment->name) );
								$count_attachments++;
							}
						}
					}
				}
				else {
					$count_sermons_error++;
					$sermons_xref[$sermon_sb1->id] = 0;
				}
			}
			else {
				// sermon already exists
				// skip import, use existing sermon
				$count_sermons_duplicate++;
			}
		}
	}
	// Output results
?>
	<div id="message" class="updated fade">
		<h3>Import Results</h3>
		<p><ul>
			<li><?php echo $count_sermons_imported, ' ', __('sermons imported.', MBSB); ?></li>
			<li><?php echo $count_sermons_duplicate, ' ', __('duplicate sermons skipped.', MBSB); ?></li>
			<li><?php echo $count_sermons_error, ' ', __('sermons not imported due to error.', MBSB); ?></li>
		</ul></p>
		<p><ul>
			<li><?php echo $count_attachments, ' ', __('attachments imported.', MBSB); ?></li>
		</ul></p>
		<p><ul>
			<li><?php echo $count_series_imported, ' ', __('series imported.', MBSB); ?></li>
			<li><?php echo $count_series_duplicate, ' ', __('duplicate series skipped.', MBSB); ?></li>
			<li><?php echo $count_series_restored, ' ', __('series restored from the trash.', MBSB); ?></li>
			<li><?php echo $count_series_error, ' ', __('series not imported due to error.', MBSB); ?></li>
		</ul></p>
		<p><ul>
			<li><?php echo $count_services_imported, ' ', __('services imported.', MBSB); ?></li>
			<li><?php echo $count_services_duplicate, ' ', __('duplicate services skipped.', MBSB); ?></li>
			<li><?php echo $count_services_restored, ' ', __('services restored from the trash.', MBSB); ?></li>
			<li><?php echo $count_services_error, ' ', __('services not imported due to error.', MBSB); ?></li>
		</ul></p>
		<p><ul>
			<li><?php echo $count_preachers_imported, ' ', __('preachers imported.', MBSB); ?>
				<?php if ($preacher_image_skipped) echo ' ', __('Note: Images attached to preachers in SB1 have not been imported into SB2.', MBSB); ?></li>
			<li><?php echo $count_preachers_duplicate, ' ', __('duplicate preachers skipped.', MBSB); ?></li>
			<li><?php echo $count_preachers_restored, ' ', __('preachers restored from the trash.', MBSB); ?></li>
			<li><?php echo $count_preachers_error, ' ', __('preachers not imported due to error.', MBSB); ?></li>
		</ul></p>
	</div>
<?php
}

/**
* Retrieves an array of objects containing all sermons with a certain title
*
* Based on the core function get_page_by_title, but will return multiple results in an array
*
* @param string title to search for
* @return array of objects
* @return null if no sermons found
*/
function mbsb_get_sermons_by_title($sermon_title) {
	global $wpdb;
	//Query all columns so as not to use get_post()
	$results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $wpdb->posts WHERE post_title = %s AND post_type = 'mbsb_sermon' AND post_status = 'publish'", $sermon_title ) );
	if ($results) {
		$output = array();
		foreach ( $results as $post ) {
			$output[] = $post;
		}
		return $output;
	}
	return null;
}

/**
* Display uninstall screen and perform uninstall if requested
*/
function mbsb_uninstall_admin_page() {
	if (isset($_POST['uninstall']))
		mbsb_uninstall();
?>
	<div class="wrap">
		<div id="icon-sermon-browser" class="icon32 icon32-mbsb-uninstall"><br /></div>
		<h2><?php _e('Sermon Browser Uninstall', MBSB); ?></h2>
		
		<form method="post">
		<p>
		<?php printf(__('Clicking the Uninstall button below will remove ALL Sermon Browser 2 data (sermons, preachers, series, etc.) 
		from the database and will deactivate the Sermon Browser 2 plugin.  You will NOT be able to undo this action.  
		If you only want to temporarily disable Sermon Browser, just deactivate it from the %sPlugins page%s.', MBSB), 
		'<a href="'.get_admin_url(null, 'plugins.php').'">', '</a>'); ?>
		</p>
		<p>
		<?php _e('Note: As this is a development release of Sermon Browser 2, we strongly recommend that you backup your WordPress 
		database before clicking the Uninstall button, just in case something weird happens.', MBSB); ?>
		</p>
		<p>
		<?php _e('Note: This Uninstall button only affects Sermon Browser 2.  Sermon Browser 1 data, if present, will remain.', MBSB); ?>
		</p>
		<p>
		<?php _e('Note: The Uninstall button does not remove any files.  Your media files will remain on the server and 
		will remain in the WordPress media library.', MBSB); ?>
		</p>
		<p>
		<?php _e('Currently in the database, you have:', MBSB); ?>
			<ul>
				<li><?php echo ($count = wp_count_posts('mbsb_sermon', 'readable')) ? $count->publish : 0; ?> Sermons</li>
				<li><?php echo ($count = wp_count_posts('mbsb_series', 'readable')) ? $count->publish : 0; ?> Series</li>
				<li><?php echo ($count = wp_count_posts('mbsb_preacher', 'readable')) ? $count->publish : 0; ?> Preachers</li>
				<li><?php echo ($count = wp_count_posts('mbsb_service', 'readable')) ? $count->publish : 0; ?> Services</li>
			</ul>
		</p>
		<p class="submit">
			<input type="submit" name="uninstall" value="<?php esc_attr_e('Uninstall', MBSB); ?>" onclick="return confirm('<?php esc_attr_e('Do you REALLY want to delete all data?', MBSB); ?>')" />
		</p>
		</form>
	</div><!-- /.wrap -->
	<script>
		jQuery("form").submit(function() {
			var yes = confirm("<?php _e('Are you REALLY REALLY sure you want to remove Sermon Browser?', MBSB); ?>");
			if(!yes) return false;
		});
	</script>
<?php
} // end mbsb_uninstall_admin_page

/**
* Uninstall plugin.
*
* Removes all custom post types from database (sermons, series, preachers, services)
* Removes plugin options from database
* Deactivates plugin
*/
function mbsb_uninstall() {
	// Delete custom post types
	$series_plural = get_posts( array('post_type' => 'mbsb_series'));
	foreach ($series_plural as $series)
		wp_delete_post($series->ID, true);
	$services = get_posts( array('post_type' => 'mbsb_service'));
	foreach ($services as $service)
		wp_delete_post($service->ID, true);
	$preachers = get_posts( array('post_type' => 'mbsb_preacher'));
	foreach ($preachers as $preacher)
		wp_delete_post($preacher->ID, true);
	$sermons = get_posts( array('post_type' => 'mbsb_sermon'));
	foreach ($sermons as $sermon)
		wp_delete_post($sermon->ID, true);
	// Delete options
	delete_option('sermon_browser_2');
	// Deactivate plugin
	deactivate_plugins( mbsb_plugin_basename() );
	// Output message
	wp_die( sprintf( __('Sermon Browser 2 has been deactivated and uninstalled.', MBSB).'<br /><br />'.__('Go back to the WordPress %sPlugins page%s.', MBSB), '<a href="'.get_admin_url(null, 'plugins.php').'">', '</a>') );
}

/**
* Display the options admin page
*/
function mbsb_options_admin_page() {
?>
	<div class="wrap">
		<div id="icon-sermon-browser" class="icon32 icon32-mbsb-options"><br /></div>
		<h2><?php _e('Sermon Browser Options', MBSB); ?></h2>
		<?php settings_errors(); ?>
		
		<form action="options.php" method="post">
		<?php settings_fields('sermon_browser_2'); ?>
		<?php do_settings_sections('sermon-browser/options'); ?>
		<p class="submit">
			<input name="sermon_browser_2[submit]" type="submit" class="button-primary" value="<?php esc_attr_e('Save Changes', MBSB); ?>" />
			<input name="sermon_browser_2[reset]" type="submit" class="button-secondary" value="<?php esc_attr_e('Reset to Defaults', MBSB); ?>" 
			onclick="if(!confirm('<?php esc_attr_e('Are you sure you want to reset all Sermon Browser options to defaults?', MBSB); ?>')){return false;}" />
		</p>
		</form>
	</div><!-- /.wrap -->
<?php
} // end mbsb_options_admin_page

/**
* Initialize the options admin page
*/
function mbsb_options_page_init() {
	add_option('sermon_browser_2');		//Ensure option exists in database.  If option already exists, add_option() does nothing.
	register_setting('sermon_browser_2', 'sermon_browser_2', 'mbsb_options_validate');
	add_settings_section('mbsb_media_player_options_section', __('Media Player Options', MBSB), 'mbsb_media_player_options_fn', 'sermon-browser/options');
	add_settings_field('mbsb_audio_shortcode', __('Audio Shortcode', MBSB), 'mbsb_audio_shortcode_fn', 'sermon-browser/options', 'mbsb_media_player_options_section');
	add_settings_field('mbsb_video_shortcode', __('Video Shortcode', MBSB), 'mbsb_video_shortcode_fn', 'sermon-browser/options', 'mbsb_media_player_options_section');
	add_settings_field('mbsb_legacy_upload_folder', __('Upload Folder (Legacy)', MBSB), 'mbsb_legacy_upload_folder_fn', 'sermon-browser/options', 'mbsb_media_player_options_section');
	add_settings_section('mbsb_layout_options_section', __('Layout Options', MBSB), 'mbsb_layout_options_fn', 'sermon-browser/options');
	add_settings_field('mbsb_frontend_sermon_sections', __('Frontend Sermon Sections', MBSB), 'mbsb_frontend_sermon_sections_fn', 'sermon-browser/options', 'mbsb_layout_options_section');
	add_settings_field('mbsb_hide_media_heading', __('Hide "Media" heading?', MBSB), 'mbsb_hide_media_heading_fn', 'sermon-browser/options', 'mbsb_layout_options_section');
	add_settings_field('mbsb_sermon_image_pos', __('Sermon Image Position', MBSB), 'mbsb_image_pos_fn', 'sermon-browser/options', 'mbsb_layout_options_section', array('sermon'));
	add_settings_field('mbsb_preacher_image_pos', __('Preacher Image Position', MBSB), 'mbsb_image_pos_fn', 'sermon-browser/options', 'mbsb_layout_options_section', array('preacher'));
	add_settings_field('mbsb_series_image_pos', __('Series Image Position', MBSB), 'mbsb_image_pos_fn', 'sermon-browser/options', 'mbsb_layout_options_section', array('series'));
	//add_settings_field('mbsb_service_image_pos', __('Service Image Position', MBSB), 'mbsb_image_pos_fn', 'sermon-browser/options', 'mbsb_layout_options_section', array('service'));
	add_settings_field('mbsb_add_download_links', __('Add download links?', MBSB), 'mbsb_add_download_links_fn', 'sermon-browser/options', 'mbsb_layout_options_section');
	add_settings_field('mbsb_sermon_image_size', __('Sermon Image Size', MBSB), 'mbsb_image_size_fn', 'sermon-browser/options', 'mbsb_layout_options_section', array('sermon'));
	add_settings_field('mbsb_preacher_image_size', __('Preacher Image Size', MBSB), 'mbsb_image_size_fn', 'sermon-browser/options', 'mbsb_layout_options_section', array('preacher'));
	add_settings_field('mbsb_series_image_size', __('Series Image Size', MBSB), 'mbsb_image_size_fn', 'sermon-browser/options', 'mbsb_layout_options_section', array('series'));
	//add_settings_field('mbsb_service_image_size', __('Service Image Size', MBSB), 'mbsb_image_size_fn', 'sermon-browser/options', 'mbsb_layout_options_section', array('service'));
	add_settings_field('mbsb_excerpt_length', __('Excerpt Length', MBSB), 'mbsb_excerpt_length_fn', 'sermon-browser/options', 'mbsb_layout_options_section');
	add_settings_field('mbsb_show_statistics_on_sermon_page', __('Show statistics on sermon page?', MBSB), 'mbsb_show_statistics_on_sermon_page_fn', 'sermon-browser/options', 'mbsb_layout_options_section');
	add_settings_section('mbsb_bible_version_options_section', __('Bible Version Options', MBSB), 'mbsb_bible_version_options_fn', 'sermon-browser/options');
	add_settings_field('mbsb_bible_version', __('Bible Version', MBSB), 'mbsb_bible_version_fn', 'sermon-browser/options', 'mbsb_bible_version_options_section');
	add_settings_field('mbsb_use_embedded_bible', __('Use embedded Bible?', MBSB), 'mbsb_use_embedded_bible_fn', 'sermon-browser/options', 'mbsb_bible_version_options_section');
	add_settings_field('mbsb_allow_user_to_change_bible', __('Allow user to change Bible version?', MBSB), 'mbsb_allow_user_to_change_bible_fn', 'sermon-browser/options', 'mbsb_bible_version_options_section');
/* Functions do not exist yet.  (Ben Miller 6/27/2013)
	add_settings_field('mbsb_inactive_bibles', __('Inactive Bibles', MBSB), 'mbsb_inactive_bibles_fn', 'sermon-browser/options', 'mbsb_bible_version_options_section');
	add_settings_field('mbsb_inactive_bible_languages', __('Inactive Bible Languages', MBSB), 'mbsb_inactive_bible_languages_fn', 'sermon-browser/options', 'mbsb_bible_version_options_section');
	add_settings_field('mbsb_hide_other_language_bibles', __('Hide other language Bibles?', MBSB), 'mbsb_hide_other_lanugage_bibles_fn', 'sermon-browser/options', 'mbsb_bible_version_options_section');
	add_settings_field('mbsb_embedded_bible_parameters', __('Embedded Bible Parameters', MBSB), 'mbsb_embedded_bible_parameters_fn', 'sermon-browser/options', 'mbsb_bible_version_options_section');
*/
	add_settings_section('mbsb_bible_api_keys_section', __('Bible API Keys', MBSB), 'mbsb_bible_api_keys_fn', 'sermon-browser/options');
	add_settings_field('mbsb_biblia_api_key', __('Biblia API Key', MBSB), 'mbsb_biblia_api_key_fn', 'sermon-browser/options', 'mbsb_bible_api_keys_section');
	add_settings_field('mbsb_biblesearch_api_key', __('Biblesearch API Key', MBSB), 'mbsb_biblesearch_api_key_fn', 'sermon-browser/options', 'mbsb_bible_api_keys_section');
	add_settings_field('mbsb_esv_api_key', __('ESV API Key', MBSB), 'mbsb_esv_api_key_fn', 'sermon-browser/options', 'mbsb_bible_api_keys_section');	
}

/**
* Validate option input from options admin page
*
* Function grabs the existing settings, then checks the input for valid data.  Any setting input that isn't valid will keep the old setting.
* Settings not on the current options screen will not be lost.
*/
function mbsb_options_validate($input) {
	// Check for reset button press, return defaults
	if (isset($input['reset']))
		return mbsb_default_options();
	// Get current options from database to use as starting point
	$all_options = get_option('sermon_browser_2', mbsb_default_options() );
	// Validate and save each option from the form
	$all_options['audio_shortcode'] = $input['audio_shortcode'];
	$all_options['video_shortcode'] = $input['video_shortcode'];
	$all_options['legacy_upload_folder'] = trailingslashit(ltrim($input['legacy_upload_folder'], '/'));
	$sections = mbsb_list_frontend_sections();
	$visible_sections = array();
	foreach ($sections as $section) {
		if (isset($input['frontend_sermon_sections_'.$section]))
			array_push($visible_sections, $section);
	}
	$all_options['frontend_sermon_sections'] = $visible_sections;
	if (isset($input['hide_media_heading']))
		$all_options['hide_media_heading'] = true;
	else
		$all_options['hide_media_heading'] = false;
	$image_classes = array('alignright', 'alignleft', 'aligncenter', 'alignnone');
	foreach ( array('sermon', 'preacher', 'series') as $imagetype ) {
		if (array_search($input[$imagetype.'_image_pos'], $image_classes))
			$all_options[$imagetype.'_image_pos'] = $input[$imagetype.'_image_pos'];
	}
	if (isset($input['add_download_links']))
		$all_options['add_download_links'] = true;
	else
		$all_options['add_download_links'] = false;
	foreach ( array('sermon', 'preacher', 'series') as $imagetype ) {
		if ( ($input[$imagetype.'_image_size_width'] == (int) $input[$imagetype.'_image_size_width']) and ((int) $input[$imagetype.'_image_size_width'] > 0) )
			$all_options[$imagetype.'_image_size']['width'] = (int) $input[$imagetype.'_image_size_width'];
		if ( ($input[$imagetype.'_image_size_height'] == (int) $input[$imagetype.'_image_size_height']) and ((int) $input[$imagetype.'_image_size_height'] > 0) )
			$all_options[$imagetype.'_image_size']['height'] = (int) $input[$imagetype.'_image_size_height'];
		if (isset($input[$imagetype.'_image_size_crop']))
			$all_options[$imagetype.'_image_size']['crop'] = true;
		else
			$all_options[$imagetype.'_image_size']['crop'] = false;
	}
	if ( $input['excerpt_length'] == (int) $input['excerpt_length'] )
		$all_options['excerpt_length'] = (int) $input['excerpt_length'];
	if (isset($input['show_statistics_on_sermon_page']))
		$all_options['show_statistics_on_sermon_page'] = true;
	else
		$all_options['show_statistics_on_sermon_page'] = false;
	$locale = get_locale();
	if (isset($input['bible_version_'.$locale]))
		$all_options['bible_version_'.$locale] = $input['bible_version_'.$locale];
	if (isset($input['use_embedded_bible_'.$locale]))
		$all_options['use_embedded_bible_'.$locale] = true;
	else
		$all_options['use_embedded_bible_'.$locale] = false;
	if (isset($input['allow_user_to_change_bible']))
		$all_options['allow_user_to_change_bible'] = true;
	else
		$all_options['allow_user_to_change_bible'] = false;
	$all_options['biblia_api_key'] = $input['biblia_api_key'];
	$all_options['biblesearch_api_key'] = $input['biblesearch_api_key'];
	$all_options['esv_api_key'] = $input['esv_api_key'];
	return $all_options;
}

/**
* Defines the section description area for the Media Player Options section on the Options page
*/
function mbsb_media_player_options_fn() {
	echo '<p>';
	_e('With the default shortcode settings, Sermon Browser works with the WordPress built-in media player (WordPress 3.6 and later).  You can use a different media player plugin by changing the shortcode settings. Enter "%URL%" to obtain the path to the media file.', MBSB);
	echo '</p>';
}

/**
* Audio Shortcode setting input field
*/
function mbsb_audio_shortcode_fn() {
	$default_audio_shortcode = mbsb_get_default_option('audio_shortcode');
	$audio_shortcode = mbsb_get_option('audio_shortcode', $default_audio_shortcode);
	echo '<input id="mbsb_audio_shortcode" name="sermon_browser_2[audio_shortcode]" size="40" type="text" value="'.esc_attr($audio_shortcode).'" /> '.__('Default:', MBSB).' <span class="mbsb_default_option">'.$default_audio_shortcode."</span>\n";
}

/**
* Video Shortcode setting input field
*/
function mbsb_video_shortcode_fn() {
	$default_video_shortcode = mbsb_get_default_option('video_shortcode');
	$video_shortcode = mbsb_get_option('video_shortcode', $default_video_shortcode);
	echo '<input id="mbsb_video_shortcode" name="sermon_browser_2[video_shortcode]" size="40" type="text" value="'.esc_attr($video_shortcode).'" /> '.__('Default:', MBSB).' <span class="mbsb_default_option">'.$default_video_shortcode."</span>\n";
}

/**
* Legacy Upload Folder setting input field
*/
function mbsb_legacy_upload_folder_fn() {
	$default_legacy_upload_folder = mbsb_get_default_option('legacy_upload_folder');
	$legacy_upload_folder = mbsb_get_option('legacy_upload_folder', $default_legacy_upload_folder);
	echo '<input id="mbsb_legacy_upload_folder" name="sermon_browser_2[legacy_upload_folder]" size="40" type="text" value="'.esc_attr($legacy_upload_folder).'" /> '.__('Default:', MBSB).' <span class="mbsb_default_option">'.$default_legacy_upload_folder."</span>\n";
}

/**
* Defines the section description area for the Bible API Keys section on the Options page
*/
function mbsb_bible_api_keys_fn() {
	// This is where an explanation would go for the API Keys section.
}

/**
* Biblia API Key setting input field
*/
function mbsb_biblia_api_key_fn() {
	$default_biblia_api_key = mbsb_get_default_option('biblia_api_key');
	$biblia_api_key = mbsb_get_option('biblia_api_key', $default_biblia_api_key);
	echo '<input id="mbsb_biblia_api_key" name="sermon_browser_2[biblia_api_key]" size="40" type="text" value="'.esc_attr($biblia_api_key).'" />'."\n";
	echo '<a href="http://api.biblia.com/docs/API_Keys">biblia.com</a>';
}

/**
* Bible Search API Key setting input field
*/
function mbsb_biblesearch_api_key_fn() {
	$default_biblesearch_api_key = mbsb_get_default_option('biblesearch_api_key');
	$biblesearch_api_key = mbsb_get_option('biblesearch_api_key', $default_biblesearch_api_key);
	echo '<input id="mbsb_biblesearch_api_key" name="sermon_browser_2[biblesearch_api_key]" size="40" type="text" value="'.esc_attr($biblesearch_api_key).'" />'."\n";
	echo '<a href="http://bibles.org/pages/api/signup">biblesearch.org</a>';
}

/**
* ESV API Key setting input field
*/
function mbsb_esv_api_key_fn() {
	$default_esv_api_key = mbsb_get_default_option('esv_api_key');
	$esv_api_key = mbsb_get_option('esv_api_key', $default_esv_api_key);
	echo '<input id="mbsb_esv_api_key" name="sermon_browser_2[esv_api_key]" size="40" type="text" value="'.esc_attr($esv_api_key).'" />'."\n";
	echo __('Default value is', MBSB), ' <strong>', $default_esv_api_key, "</strong><br />\n";
	echo __('If you get a message saying that you have exceeded your quote of ESV lookups (5000 per day), and you suspect that it is because of other websites on your shared server, you can request an API Key from ESV.', MBSB);
	echo ' <a href="http://www.esvapi.org/signup">esvapi.org</a>';
}

/**
* Excerpt length setting input field
*/
function mbsb_excerpt_length_fn() {
	$default_excerpt_length = mbsb_get_default_option('excerpt_length');
	$excerpt_length = mbsb_get_option('excerpt_length', $default_excerpt_length);
	echo '<input id="mbsb_excerpt_length" name="sermon_browser_2[excerpt_length]" size="4" type="text" value="'.esc_attr($excerpt_length).'" />'."\n";
}

/**
* Defines the section description area for the Layout Options section on the Options page
*/
function mbsb_layout_options_fn() {
	// This is where an explanation would go for the Layout Options section.
}

/**
* Frontend Sermon Sections setting input field
*/
function mbsb_frontend_sermon_sections_fn() {
	$default_frontend_sermon_sections = mbsb_get_default_option('frontend_sermon_sections');
	$frontend_sermon_sections = mbsb_get_option('frontend_sermon_sections', $default_frontend_sermon_sections);
	$sections = mbsb_list_frontend_sections();
	$output = '';
	foreach ($sections as $section) {
		if (array_search($section, $frontend_sermon_sections)===false) {
			$checked = '';
		}
		else {
			$checked = 'checked="checked"';
		}
		$output .= '<input id="mbsb_frontend_sermon_sections_'.$section.'" name="sermon_browser_2[frontend_sermon_sections_'.$section.']" type="checkbox" value="true" '.$checked.' />';
		$output .= '<label for="mbsb_frontend_sermon_sections_'.$section.'"> '.__(ucfirst($section), MBSB)."</label><br />\n";
	}
	echo $output;
}

/**
* Image Size setting input fields
*/
function mbsb_image_size_fn($args) {
	$image_type = $args[0];
	$default_image_size = mbsb_get_default_option($image_type.'_image_size');
	$image_size = mbsb_get_option($image_type.'_image_size', $default_image_size);
	$checked = ($image_size['crop']) ? 'checked="checked"' : '';
	$output  = '<label for="mbsb_'.$image_type.'_image_size_width">'.__('Width:',MBSB).'</label><input id="mbsb_'.$image_type.'_image_size_width" name="sermon_browser_2['.$image_type.'_image_size_width]" type="text" size="4" value="'.esc_attr($image_size['width']).'" />'." \n";
	$output .= '<label for="mbsb_'.$image_type.'_image_size_height">'.__('Height:',MBSB).'</label><input id="mbsb_'.$image_type.'_image_size_height" name="sermon_browser_2['.$image_type.'_image_size_height]" type="text" size="4" value="'.esc_attr($image_size['height']).'" />'." \n";
	$output .= '<label for="mbsb_'.$image_type.'_image_size_crop">'.__('Crop?',MBSB).'</label><input id="mbsb_'.$image_type.'_image_size_crop" name="sermon_browser_2['.$image_type.'_image_size_crop]" type="checkbox" value="true" '.$checked.' />'." \n";
	echo $output;
}

/**
* Hide Media Heading setting input field
*/
function mbsb_hide_media_heading_fn() {
	$default_hide_media_heading = mbsb_get_default_option('hide_media_heading');
	$hide_media_heading = mbsb_get_option('hide_media_heading', $default_hide_media_heading);
	$checked = ($hide_media_heading) ? 'checked="checked"' : '';
	echo '<input id="mbsb_hide_media_heading" name="sermon_browser_2[hide_media_heading]" type="checkbox" value="true" '.$checked." />\n";
}

/**
* Show Statistics on Sermon Page setting input field
*/
function mbsb_show_statistics_on_sermon_page_fn() {
	$default_show_statistics_on_sermon_page = mbsb_get_default_option('show_statistics_on_sermon_page');
	$show_statistics_on_sermon_page = mbsb_get_option('show_statistics_on_sermon_page', $default_show_statistics_on_sermon_page);
	$checked = ($show_statistics_on_sermon_page) ? 'checked="checked"' : '';
	echo '<input id="mbsb_show_statistics_on_sermon_page" name="sermon_browser_2[show_statistics_on_sermon_page]" type="checkbox" value="true" '.$checked." />\n";
}

/**
* Use Embedded Bible setting input field
*/
function mbsb_use_embedded_bible_fn() {
	$locale = get_locale();
	$default_use_embedded_bible = mbsb_get_default_option('use_embedded_bible_'.$locale);
	$use_embedded_bible = mbsb_get_option('use_embedded_bible_'.$locale, $default_use_embedded_bible);
	$checked = ($use_embedded_bible) ? 'checked="checked"' : '';
	echo '<input id="mbsb_use_embedded_bible_'.$locale.'" name="sermon_browser_2[use_embedded_bible_'.$locale.']" type="checkbox" value="true" '.$checked." />\n";
}

/**
* Allow User to Change Bible setting input field
*/
function mbsb_allow_user_to_change_bible_fn() {
	$default_allow_user_to_change_bible = mbsb_get_default_option('allow_user_to_change_bible');
	$allow_user_to_change_bible = mbsb_get_option('allow_user_to_change_bible');
	$checked = ($allow_user_to_change_bible) ? 'checked="checked"' : '';
	echo '<input id="mbsb_allow_user_to_change_bible" name="sermon_browser_2[allow_user_to_change_bible]" type="checkbox" value="true" '.$checked." />\n";
}

/**
* Image Position setting input field
*/
function mbsb_image_pos_fn($args) {
	$image_type = $args[0];
	$default_image_pos = mbsb_get_default_option($image_type.'_image_pos');
	$image_pos = mbsb_get_option($image_type.'_image_pos', $default_image_pos);
	$positions = array(__('Right', MBSB) => 'alignright', __('Left', MBSB) => 'alignleft', __('Center', MBSB) => 'aligncenter', __('Not Aligned', MBSB) => 'alignnone');
	$output = '<select id="mbsb_'.$image_type.'_image_pos" name="sermon_browser_2['.$image_type.'_image_pos]">'."\n";
	foreach ($positions as $position => $style) {
		$selected = selected($image_pos, $style, false);
		$output .= "<option value='$style' $selected>$position</option>\n";
	}
	$output .= "</select>\n";
	echo $output;
}

/**
* Bible Version setting input field
*/
function mbsb_bible_version_fn() {
	$bibles = new mbsb_online_bibles();
	$locale = get_locale();
	$default_mbsb_bible_version = mbsb_get_default_option('bible_version_'.$locale);
	$mbsb_bible_version = mbsb_get_option('bible_version_'.$locale, $default_mbsb_bible_version);
	$output = $bibles->get_bible_list_dropdown($mbsb_bible_version, 'mbsb_bible_version_'.$locale, 'sermon_browser_2[bible_version_'.$locale.']')."\n";
	echo $output;
}

/**
* Add Download Links setting input field
*/
function mbsb_add_download_links_fn() {
	$default_add_download_links = mbsb_get_default_option('add_download_links');
	$add_download_links = mbsb_get_option('add_download_links', $default_add_download_links);
	$checked = ($add_download_links) ? 'checked="checked"' : '';
	echo '<input id="mbsb_add_download_links" name="sermon_browser_2[add_download_links]" type="checkbox" value="true" '.$checked." />\n";
}

/**
* Defines the section description area for the Bible Version Options section on the Options page
*/
function mbsb_bible_version_options_fn() {
	$output = "<p>\n";
	$output .= __('The Bible Version and Use Embedded Bible parameters are saved per locale.', MBSB)."\n";
	$output .= __('Current locale:', MBSB).get_locale()."\n";
	$output .= "</p>\n";
	echo $output;
}

?>