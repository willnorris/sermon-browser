<?php
/**
* Include file, called when is_admin() is false
* 
* @package SermonBrowser
* @subpackage Frontend
* @author Mark Barnes <mark@sermonbrowser.com>
*/

add_action ('init', 'mbsb_frontend_init');
require (apply_filters ('mbsb_theme', mbsb_plugin_dir_path('includes/default_theme.php')));
/**
* Runs on the init action
* 
* Sets up most of the required features.
* 
*/
function mbsb_frontend_init() {
	add_action ('wp_head', 'mbsb_enqueue_frontend_scripts_and_styles');
	add_filter ('the_content', 'mbsb_provide_content');
	add_filter ('the_title', 'mbsb_filter_titles', 10, 2);
	add_filter ('the_author', 'mbsb_filter_author');
	add_filter ('author_link', 'mbsb_filter_author_link', 10, 3);
	add_shortcode ('sermons', 'mbsb_display_sermons');
	add_shortcode ('series', 'mbsb_display_series');
	add_shortcode ('services', 'mbsb_display_services');
	add_shortcode ('preachers', 'mbsb_display_preachers');
}

/**
* Filters the_content for sermon browser custom post types, and provides all output
* 
* @param mixed $content
*/
function mbsb_provide_content($content) {
	global $post;
	if (substr($post->post_type, 0, 5) == 'mbsb_')
		if ($post->post_type == 'mbsb_sermon') {
			$sermon = new mbsb_sermon($post->ID);
			return $sermon->get_frontend_output();
		}
	return $content;
}

/**
* Filters the_title for SermonBrowser custom post types
* 
* @param string $title
* @param integer $id
* @return string
*/
function mbsb_filter_titles ($title, $id) {
	$post = get_post ($id);
	if ($post->post_type == 'mbsb_sermon') {
		$sermon = new mbsb_sermon($id);
		if ($sermon->passages->present)
			return $title.' <span class="title_passage">('.$sermon->get_formatted_passages().')</span>';
	}
	return $title;
}

/**
* Filters the_author for SermonBrowser custom post types
* 
* @param string $author
* @return string
*/
function mbsb_filter_author ($author) {
	global $post;
	if (isset($post->post_type) && $post->post_type == 'mbsb_sermon') {
		$sermon = new mbsb_sermon($post->ID);
		if ($sermon->preacher->present)
			return $sermon->preacher->get_name();
	}
	return $author;
	
}

/**
* Filters the author link to make sure that sermon authors point to the preacher URL
* 
* @param string $link
* @param integer $author_id
* @param string $author_nicename
* @return string
*/
function mbsb_filter_author_link ($link, $author_id, $author_nicename) {
	global $post;
	if (isset($post->post_type) && $post->post_type == 'mbsb_sermon') {
		$sermon = new mbsb_sermon($post->ID);
		if ($sermon->preacher->present)
			return $sermon->preacher->get_url();
	}
	return $link;
}

/**
* Enqueues scripts and styles for the frontend
*/
function mbsb_enqueue_frontend_scripts_and_styles() {
	global $post;
	if (isset ($post->ID) && isset ($post->post_type) && $post->post_type == 'mbsb_sermon') {
		echo "<script type=\"text/javascript\">var mbsb_sermon_id=".esc_html($post->ID).";</script>\r\n";
		if (mbsb_get_option('allow_user_to_change_bible') || mbsb_get_option('bible_version_'.get_locale()) == 'esv')
			wp_enqueue_style ('mbsb_esv_style', mbsb_plugins_url('css/esv.css'));
		if (mbsb_get_option('use_embedded_bible_'.get_locale()))
			wp_enqueue_script('mbsb_biblia_embedded', 'http://biblia.com/api/logos.biblia.js');
	}
	$date = @filemtime(mbsb_plugin_dir_path('css/frontend-style.php'));
	wp_enqueue_style ('mbsb_frontend_style', mbsb_plugins_url('css/frontend-style.php'), array(), $date);
	wp_enqueue_script ('mbsb_frontend_script', home_url("?mbsb_script=frontend&locale=".get_locale()), array ('jquery'), @filemtime(mbsb_plugin_dir_path('js/frontend.php')).(mbsb_get_option('use_embedded_bible_'.get_locale()) ? '-biblia' : ''));
}

/**
* Display the list of sermons. Can be added to a theme, or called using the [sermons] shortcode
*/
function mbsb_display_sermons ($atts, $content) {
	return apply_filters ('mbsb_display_sermons', $content);	
}

/**
* Display the list of series. Can be added to a theme, or called using the [sermon-series] shortcode
*/
function mbsb_display_series($atts, $content) {
	return apply_filters ('mbsb_display_series', $content);	
}

/**
* Display the list of services. Can be added to a theme, or called using the [services] shortcode
*/
function mbsb_display_services($atts, $content) {
	return apply_filters ('mbsb_display_services', $content);	
}

/**
* Display the list of preachers. Can be added to a theme, or called using the [preachers] shortcode
*/
function mbsb_display_preachers($atts, $content) {
	return apply_filters ('mbsb_display_preachers', $content);	
}

function mbsb_setup_frontend_queries() {
	add_filter ('posts_join_paged', 'mbsb_frontend_sermons_standard_join');
	add_filter ('posts_where_paged', 'mbsb_frontend_sermons_standard_where');
}

function mbsb_reset_frontend_queries() {
	remove_filter ('posts_join_paged', 'mbsb_frontend_sermons_standard_join');
	remove_filter ('posts_where_paged', 'mbsb_frontend_sermons_standard_where');
}

function mbsb_get_sermon_filters() {
	global $wpdb;
	mbsb_setup_frontend_queries();
	$query = new WP_Query();
	$sermon_ids = $query->query(array ('post_type' => 'mbsb_sermon', 'no_paging' => true, 'fields' => 'ids'));
	mbsb_reset_frontend_queries();
	if ($sermon_ids) {
		$preachers = mbsb_get_sermon_count_by ('preacher', $sermon_ids);
		$series = mbsb_get_sermon_count_by ('series', $sermon_ids);
		$services = mbsb_get_sermon_count_by ('service', $sermon_ids);
		$tags = mbsb_get_sermon_count_by ('tag', $sermon_ids);
		$years = mbsb_get_sermon_count_by ('year', $sermon_ids);
		$books = mbsb_get_sermon_count_by ('book', $sermon_ids);
		$filter_by = array (array('id' => '', 'name' => ''), array('id' => 'preacher', 'name' => __('Preacher')), array('id' => 'series', 'name' => __('Series')), array('id' => 'service', 'name' => __('Service')), array('id' => 'tag', 'name' => __('Tags')), array('id' => 'book', 'name' => __('Passage')), array('id' => 'year', 'name' => __('Date')));
		$output = '<form id="sermon_filter_form">';
		$output .= '<table id="sermon_filter">';
		$output .= '<tr><td><label for="filter_by">'.__('Filter by:', MBSB).'</label></td></tr><tr><td>'.mbsb_do_filter_dropdown('filter_by', $filter_by).'</td>';
		$output .= '<td class="mbsb_hide" id="preacher_dropdown">'.mbsb_do_filter_dropdown ('preacher', $preachers).'</td>';
		$output .= '<td class="mbsb_hide" id="series_dropdown">'.mbsb_do_filter_dropdown ('series', $series).'</td>';
		$output .= '<td class="mbsb_hide" id="tag_dropdown">'.mbsb_do_filter_dropdown ('tags', $tags).'</td>';
		$output .= '<td class="mbsb_hide" id="service_dropdown">'.mbsb_do_filter_dropdown ('service', $services).'</td>';
		$output .= '<td class="mbsb_hide" id="book_dropdown">'.mbsb_do_filter_dropdown ('book', $books).'</td>';
		$output .= '<td class="mbsb_hide" id="year_dropdown">'.mbsb_do_filter_dropdown ('year', $years).'</td>';
		$output .= '</tr></table>';
		$output .= '</form>';
		return $output;
	}
}

function mbsb_do_filter_dropdown ($field_name, $values) {
	if ($values) {
		$output = "<select id=\"filter_{$field_name}\" name=\"{$field_name}\">";
		foreach ($values as $v) {
			$v = (object)$v;
			if ($field_name == 'book')
				$v->name = mbsb_get_bible_book_name($v->name);
			$output .= '<option value="'.esc_html($v->id).'">'.esc_html($v->name).(isset($v->count) ? ' ('.$v->count.')' : '').'</option>';
		}
		return $output .'</select>';
	}
}

/**
* Filters posts_join_paged when the sermons page is displayed in the frontend
* 
* Adds SQL to WP_Query to ensure the correct metadata is added to the query.
* 
* @param string $join
* @return string
*/
function mbsb_frontend_sermons_standard_join ($join) {
	if (isset($_POST['preacher']))
		$join .= mbsb_join_preacher ('');
	if (isset($_POST['series']))
		$join .= mbsb_join_service ('');
	if (isset($_POST['service']))
		$join .= mbsb_join_series ('');
	if (isset($_POST['book']))
		$join .= mbsb_join_book ('');
	return $join;
}

/**
* Filters posts_where_paged when the sermons page is displayed in the frontend
* 
* Adds SQL to WP_Query to ensure the correct 'WHERE' data is added to the query.
* 
* @param string $where
* @return string
*/
function mbsb_frontend_sermons_standard_where($where) {
	global $wpdb;
	if (isset($_POST['preacher']))
		$where .= " AND preachers.ID=".$wpdb->escape($_POST["preacher"]);
	if (isset($_POST['series']))
		$where .= " AND series.ID=".$wpdb->escape($_POST["series"]);
	if (isset($_POST['service']))
		$where .= " AND services.ID=".$wpdb->escape($_POST["service"]);
	if (isset($_POST['book']))
		$where .= " AND CONVERT(LEFT(book_postmeta.meta_value,2), UNSIGNED)=".$wpdb->escape($_POST["book"]);
	if (isset($_POST['year']))
		$where .= " AND YEAR(post_date) =".$wpdb->escape($_POST["year"]);
	return $where;
}
?>