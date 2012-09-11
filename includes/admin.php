<?php
/**
* File is called when is_admin() is true
* 
* @package admin
*/
add_action ('admin_init', 'mbsb_admin_init');

/**
* Runs on the admin_init action.
* 
*/
function mbsb_admin_init () {
	$date = @filemtime(mbsb_plugin_dir_path('css/admin-style.php'));
	wp_register_style ('mbsb_admin_style', mbsb_plugins_url('css/admin-style.php'), $date);
	wp_register_style ('mbsb_jquery_ui', mbsb_plugins_url('css/jquery-ui-1.8.23.custom.css'), '');
	add_action ('admin_print_styles', 'mbsb_admin_print_styles');
	add_action ('load-edit.php', 'mbsb_onload_edit_page');
	add_action ('manage_posts_custom_column', 'mbsb_output_custom_columns', 10, 2);
	add_action ('admin_enqueue_scripts', 'mbsb_add_javascript_and_styles_to_admin_pages');
	add_action ('load-media-upload.php', 'mbsb_media_upload_actions');
	add_action ('load-async-upload.php', 'mbsb_media_upload_actions');
	add_action ('wp_ajax_mbsb_attachment_insert', 'mbsb_ajax_attachment_insert');
	add_action ('wp_ajax_mbsb_attach_url_embed', 'mbsb_ajax_attach_url_embed');
	add_action ('wp_ajax_mbsb_remove_attachment', 'mbsb_ajax_mbsb_remove_attachment');
	// Is the user quick editing a custom post_type?
	if (isset($_POST['action']) && $_POST['action'] == 'inline-save' && substr($_POST['post_type'], 0, 5) == 'mbsb_') {
		$mbsb_post_type = substr($_POST['post_type'], 5);
		if (function_exists("mbsb_add_{$mbsb_post_type}_columns"))
			add_filter ("manage_mbsb_{$mbsb_post_type}_posts_columns", "mbsb_add_{$mbsb_post_type}_columns");
	}
	if (isset($_POST['mbsb_date']) && isset($_POST['post_type']) && $_POST['post_type'] == 'mbsb_sermons') {
		add_filter ('wp_insert_post_data', 'mbsb_sermon_insert_post_modify_date_time');
	}
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
		add_action ('admin_head', create_function ('', "echo '<style type=\"text/css\">table.fixed {table-layout:auto;} table.fixed th.column-tags, table.fixed td.column-tags {width:auto;}</style>';"));
	}
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
		wp_enqueue_script('mbsb_script_sermon_upload', home_url("?mbsb_script&amp;name=sermon_upload&amp;post_id={$post->ID}"), array ('thickbox', 'media-upload'), @filemtime(mbsb_plugin_dir_path('js/scripts.php')));
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
	if (isset ($_GET['post_id']) && get_post_type ($_GET['post_id']) == 'mbsb_sermons')
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
	if (isset($_GET['tab']))
		return array ($_GET['tab'] => $tabs[$_GET['tab']]);
	else
		return $tabs;
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
* Handles the wp_ajax_mbsb_attachment_insert AJAX request, which adds a media library attachment
*/
function mbsb_ajax_attachment_insert() {
	global $wpdb;
	if (!check_ajax_referer ("mbsb_attachment_insert_{$_POST['post_id']}"))
		die ('Suspicious behaviour blocked');
	add_filter ('posts_where_paged', 'mbsb_add_guid_to_where');
	$attachment = get_posts (array ('numberposts' => 1, 'post_type' => 'attachment', 'post_status' => null, 'suppress_filters' => false));
	remove_filter ('posts_where_paged', 'mbsb_add_guid_to_where');
	$attachment = $attachment[0];
	$sermon = new mbsb_sermon($_POST['post_id']);
	add_filter ('mbsb_attachment_row_actions', 'mbsb_add_admin_attachment_row_actions');
	if ($result = $sermon->add_library_attachment ($attachment->ID))
		echo $result->get_json_attachment_row();
	elseif ($result === NULL)
		echo mbsb_media_attachment::get_json_attachment_row(false, sprintf(__('%s is already attached to this sermon.', MBSB), $attachment->post_title));
	else
		echo mbsb_media_attachment::get_json_attachment_row(false, sprintf(__('There was an error attaching %s to the sermon.', MBSB), $attachment->post_title));
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
	if (!check_ajax_referer ("mbsb_handle_url_embed_{$_POST['post_id']}"))
		die ('Suspicious behaviour blocked');
	$sermon = new mbsb_sermon($_POST['post_id']);
	add_filter ('mbsb_attachment_row_actions', 'mbsb_add_admin_attachment_row_actions');
	if ($_POST['type'] == 'embed') {
		$result = $sermon->add_embed_attachment ($_POST['attachment']);
		if ($result === null)
			echo mbsb_media_attachment::get_json_attachment_row(false, __('That code is not acceptable.', MBSB));
		elseif ($result === FALSE)
			echo mbsb_media_attachment::get_json_attachment_row(false, __('There was an error attaching that embed code to the sermon.', MBSB));
		else
			echo $result->get_json_attachment_row();
	} elseif ($_POST['type'] == 'url') {
		if (strtolower(substr($_POST['attachment'], 0, 4)) != 'http')
			$_POST['attachment'] = "http://{$_POST['attachment']}";
		$result = $sermon->add_url_attachment ($_POST['attachment']);
		if ($result === null)
			echo mbsb_media_attachment::get_json_attachment_row(false, __('That does not appear to be a valid URL.', MBSB));
		elseif ($result === FALSE)
			echo mbsb_media_attachment::get_json_attachment_row(false, __('There was an error attaching that URL to the sermon.', MBSB));
		else
			echo $result->get_json_attachment_row();
	}
	die();
}

function mbsb_ajax_mbsb_remove_attachment() {
	if (!check_ajax_referer ("mbsb_remove_attachment_{$_POST['post_id']}"))
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
function mbsb_sermons_meta_boxes () {
	add_meta_box ('mbsb_sermon_save', __('Save', MBSB), 'mbsb_sermon_save_meta_box', 'mbsb_sermons', 'side', 'high');
	add_meta_box ('mbsb_sermon_media', __('Media', MBSB), 'mbsb_sermon_media_meta_box', 'mbsb_sermons', 'normal', 'high');
	add_meta_box ('mbsb_sermon_details', __('Details', MBSB), 'mbsb_sermon_details_meta_box', 'mbsb_sermons', 'normal', 'high');
	add_meta_box ('mbsb_description', __('Description', MBSB), 'mbsb_sermon_editor_box', 'mbsb_sermons', 'normal', 'default');
	remove_meta_box ('submitdiv', 'mbsb_sermons', 'side');
	add_filter ('screen_options_show_screen', create_function ('', 'return false;'));
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
	echo '<tr><th scope="row"><label for="mbsb_preacher">'.__('Preacher', MBSB).':</label></th><td><select id="mbsb_preacher" name="mbsb_preacher">'.mbsb_return_select_list('preachers', $sermon->preacher->id).'</select></td></tr>';
	echo '<tr><th scope="row"><label for="mbsb_series">'.__('Series', MBSB).':</label></th><td><select id="mbsb_series" name="mbsb_series">'.mbsb_return_select_list('series', $sermon->series->id).'</select></td></tr>';
	echo '<tr><th scope="row"><label for="mbsb_service">'.__('Service', MBSB).':</label></th><td><select id="mbsb_service" name="mbsb_service">'.mbsb_return_select_list('services', $sermon->service->id).'</select></td></tr>';
	echo '<tr><th scope="row"><label for="mbsb_date">'.__('Date', MBSB).':</label></th><td><span class="time_input"><input id="mbsb_date" name="mbsb_date" type="text" class="add-date-picker" value="'.$sermon->date.'"/></td><td><label for="mbsb_date">'.__('Time', MBSB).':</label></td><td><input id="mbsb_time" name="mbsb_time" type="text" value="'.($screen->action == 'add' ? $sermon->service->get_service_time() : $sermon->time).'"/></span> <input type="checkbox" id="mbsb_override_time" name="mbsb_override_time"'.($sermon->override_time ? ' checked="checked"' : '').'/> <label for="mbsb_override_time" style="font-weight:normal">'.__('Override default time', MBSB).'</label></td></tr>';
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
	$sermon = new mbsb_sermon($post->ID);
	$attachments = $sermon->get_attachments(true);
	if ($attachments)
		foreach ($attachments as $attachment)
			echo $attachment->get_attachment_row ();
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
* Outputs the save/publish metabox
* 
* Code adapted from meta-boxes.php
* @todo - check this code with WordPress 3.3
*/
function mbsb_sermon_save_meta_box() {
	global $post;
	$post_type = $post->post_type;
	$post_type_object = get_post_type_object($post_type);
	require mbsb_plugin_dir_path('includes/meta-box-save.php');
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
* Returns a row for the attached media table, containing a message rather than a successful result
* 
* @param string $message
* @return string
*/
function mbsb_do_media_row_message ($message) {
	return '<tr><td><div class="message">'.$message.'</div></td></tr>';
}
?>