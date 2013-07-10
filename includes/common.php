<?php
/**
* Include file that contains various filters and functions used throughout SermonBrowser
* that provide functionality specific to SermonBrowser.
* 
* @package SermonBrowser
* @subpackage Common
* @author Mark Barnes <mark@sermonbrowser.com>
*/

/**
* Returns the HTML for a dropdown list of titles of a specified custom post type
* 
* @param string $custom_post_type - the custom post type required
* @param string $selected - the post_id of the custom post that should be pre-selected
* @param array $additions - an array of additional items to be added to the list, with the key as the id, and the value as the text
* @return string - the resulting HTML
*/
function mbsb_return_select_list ($custom_post_type, $selected = '', $additions = array()) {
	$posts = get_posts (array ('orderby' => 'title', 'order' => 'ASC', 'post_type' => "mbsb_{$custom_post_type}", 'numberposts' => -1, 'posts_per_page' => -1));
	$output = "<option value=\"0\">&nbsp;</option>";
	if (is_array($posts)) {
		foreach ($posts as $post) {
			if ($selected != '' && $post->ID == $selected)
				$insert = ' selected="selected"';
			else
				$insert = '';
			$output .= "<option value=\"{$post->ID}\"{$insert}>".esc_html($post->post_title)."&nbsp;</option>";
		}
	}
	if (!empty($additions) && is_array($additions)) {
		foreach ($additions as $id => $text) {
			if ($selected != '' && $id == $selected)
				$insert = ' selected="selected"';
			else
				$insert = '';
			$output .= "<option value=\"{$id}\"{$insert}>".esc_html($text)."&nbsp;</option>";
		}
	}
	if (!isset($output))
		return false;
	else
		return $output;
}

/**
* Ensures the correct date/time is applied when sermons are saved.
* 
* Filters wp_insert_post_data
* 
* @param array $data - the data about to be saved
* @return array - the modified data with the correct date/time
*/
function mbsb_sermon_insert_post_modify_date_time ($data) {
	if ($data['post_type'] == 'mbsb_sermon')
		if (isset($_POST['mbsb_date']) && $data['post_status'] != 'auto-draft') {
			if (isset($_POST['mbsb_override_time']) && $_POST['mbsb_override_time'] == 'on' && isset($_POST['mbsb_time']))
				$data['post_date'] = $data ['post_modified'] = "{$_POST['mbsb_date']} {$_POST['mbsb_time']}:00";
			else {
				$service = new mbsb_service($_POST['mbsb_service']);
				$data['post_date'] = $data ['post_modified'] =  "{$_POST['mbsb_date']} ".$service->get_time();
			}
			if (isset($_POST['mbsb_date']) && isset($_POST['mbsb_time']))
				$data['post_date_gmt'] = $data['post_modified_gmt'] = date ('Y-m-d H:i:s', mysql2date ('U', $data['post_date'])-(get_option('gmt_offset')*60*60));
		}
	return $data;
}

/**
* Returns an array of meta_ids that match a particular value
* 
* @param string $value
* @return array
*/
function mbsb_get_meta_ids_by_value ($value) {
	global $wpdb;
	return $wpdb->get_col($wpdb->prepare("SELECT meta_id FROM {$wpdb->prefix}postmeta WHERE meta_value=%s", $value));
}

/**
* Prevents sermon/series/preacher/services being deleted incorrectly
* 
* Does not allow series/preacher/services to be deleted if they are in use
* Filters user_has_cap
* 
* @param array $allcaps
* @param array $caps
* @param array $args
* @return array
*/
function mbsb_prevent_cpt_deletions ($allcaps, $caps, $args) {
	global $wpdb;
	if (isset($args[0]) && isset($args[2]) && ($args[0] == 'delete_post' || $args[0] == 'delete_page')) {
		$post = get_post ($args[2]);
		if ($post->post_status == 'publish' && substr($post->post_type, 0, 5) == 'mbsb_' && $post->post_type != 'mbsb_sermon') {
			//Prevent deletion of data used in existing sermons
			$type = substr($post->post_type, 5);
			if (query_posts (array ('post_type' => 'mbsb_sermon', 'meta_query' => array (array('key' => $type, 'value' => $args[2])))))
				$allcaps[$caps[0]] = false;
		}
	}
	return $allcaps;
}

/**
* Returns a the SQL to JOIN preachers metadata to a sermons query
* 
* Designed to filter posts_join_paged
* 
* @param string $join
* @param string $join_type
* @return string
*/
function mbsb_join_preacher ($join, $join_type = 'INNER') {
	global $wpdb;
	return $join." {$join_type} JOIN {$wpdb->prefix}postmeta AS preachers_postmeta ON ({$wpdb->prefix}posts.ID = preachers_postmeta.post_id) INNER JOIN {$wpdb->prefix}posts AS preachers ON (preachers.ID = preachers_postmeta.meta_value AND preachers.post_type = 'mbsb_preacher')";
}

/**
* Returns a the SQL to JOIN service metadata to a sermons query
* 
* Designed to filter posts_join_paged
* 
* @param string $join
* @param string $join_type
* @return string
*/
function mbsb_join_service ($join, $join_type = 'INNER') {
	global $wpdb;
	return $join." {$join_type} JOIN {$wpdb->prefix}postmeta AS services_postmeta ON ({$wpdb->prefix}posts.ID = services_postmeta.post_id) INNER JOIN {$wpdb->prefix}posts AS services ON (services.ID = services_postmeta.meta_value AND services.post_type = 'mbsb_service')";
}

/**
* Returns a the SQL to JOIN series metadata to a sermons query
* 
* Designed to filter posts_join_paged
* 
* @param string $join
* @param string $join_type
* @return string
*/
function mbsb_join_series ($join, $join_type = 'INNER') {
	global $wpdb;
	return $join." {$join_type} JOIN {$wpdb->prefix}postmeta AS series_postmeta ON ({$wpdb->prefix}posts.ID = series_postmeta.post_id) INNER JOIN {$wpdb->prefix}posts AS series ON (series.ID = series_postmeta.meta_value AND series.post_type = 'mbsb_series')";
}

/**
* Returns a the SQL to JOIN book metadata to a sermons query
* 
* Designed to filter posts_join_paged
* 
* @param string $join
* @param string $join_type
* @return string
*/
function mbsb_join_book ($join, $join_type = 'INNER') {
	global $wpdb;
	return $join." {$join_type} JOIN {$wpdb->prefix}postmeta AS book_postmeta ON ({$wpdb->prefix}posts.ID = book_postmeta.post_ID AND book_postmeta.meta_key IN ('passage_start', 'passage_end'))";
}

/**
* Downloads a file after first checking the cache.
* 
* It does not use transient caching, as we will still use an out of date cache if the page is unreachable.
* 
* @param string $url
* @param integer $max_cached_time
* @param string $user_name
* @param string $password
*/
function mbsb_cached_download ($url, $max_cached_time = 604800, $user_name = '', $password = '') { // 1 week
	$option_name = 'mbsb_cache_'.md5($url.$user_name.$password);
	$cached = get_option ($option_name);
	if ($cached && (($cached['time']+$max_cached_time) > time()))
		return $cached ['data'];
	else {
		$args = array();
		if ($user_name || $password)
			$args['headers'] = array ('Authorization' => 'Basic '.base64_encode ("{$user_name}:{$password}"));
		$download = wp_remote_get ($url, $args);
		if (is_wp_error ($download) || $download['response']['code'] != 200) {
			if ($cached) {
				$cached ['time'] = time() - $max_cached_time + min($max_cached_time, 21600); // Use out-of-date cache for no more than 6 more hours, or the specified cache time
				update_option ($option_name, $cached);
				return $cached ['data'];
			}
		} else
			update_option ($option_name, array ('data' => $download, 'time' => time()));
		return $download;
	}
} 

/**
* Returns the preferred Bible version
* 
* @return string
*/
function mbsb_get_preferred_version() {
	if (mbsb_get_option ('allow_user_to_change_bible') && !mbsb_get_option ('use_embedded_bible_'.get_locale()) && isset($_COOKIE['sermon_browser_bible']))
		return $_COOKIE['sermon_browser_bible'];
    else
		return mbsb_get_option ('bible_version_'.get_locale());
}

/**
* Gets an Bible API key
* 
* @param string $service
* @return string
*/
function mbsb_get_api_key($service) {
	return mbsb_get_option("{$service}_api_key");
}

function mbsb_get_sermon_count_by ($type, $sermon_ids = '') {
	global $wpdb;
	if (is_array($sermon_ids))
		 $sermon_ids = 'AND sermons.ID IN ('.implode (', ', $sermon_ids).')';
	if (in_array($type, array('series', 'service', 'preacher')))
		return $wpdb->get_results("SELECT {$type}.ID as id, {$type}.post_title AS name, COUNT(*) AS count FROM {$wpdb->prefix}posts AS sermons INNER JOIN {$wpdb->prefix}postmeta INNER JOIN {$wpdb->prefix}posts AS {$type} WHERE post_id=sermons.ID AND {$type}.post_type='mbsb_{$type}' AND {$wpdb->prefix}postmeta.meta_key='{$type}' AND {$type}.ID={$wpdb->prefix}postmeta.meta_value {$sermon_ids} GROUP BY {$type}.ID ORDER BY name");
	elseif ($type == 'tag')
		return $wpdb->get_results("SELECT tag.term_id as id, tag.name AS name, COUNT(*) AS count FROM {$wpdb->prefix}posts AS sermons INNER JOIN {$wpdb->prefix}term_relationships as term_rel INNER JOIN {$wpdb->prefix}terms AS tag INNER JOIN {$wpdb->prefix}term_taxonomy AS term_tax WHERE term_rel.term_taxonomy_id=term_tax.term_taxonomy_id AND term_rel.object_id=sermons.ID AND term_tax.term_id=tag.term_id {$sermon_ids} GROUP BY tag.term_id ORDER BY name");
	elseif ($type == 'book')
		return $wpdb->get_results("SELECT LEFT({$wpdb->prefix}postmeta.meta_value,2) as id, LEFT({$wpdb->prefix}postmeta.meta_value,2) AS name, COUNT(DISTINCT sermons.ID) AS count FROM {$wpdb->prefix}posts AS sermons INNER JOIN {$wpdb->prefix}postmeta WHERE post_id=sermons.ID AND ({$wpdb->prefix}postmeta.meta_key='passage_start' OR {$wpdb->prefix}postmeta.meta_key='passage_end') {$sermon_ids} GROUP BY id");
	elseif ($type == 'year')
		return $wpdb->get_results("SELECT ID as id, YEAR(post_date) AS name, COUNT(*) AS count FROM {$wpdb->prefix}posts AS sermons WHERE 1=1 {$sermon_ids} GROUP BY name");
}

function mbsb_get_bible_book_name ($book_id) {
	$books = mbsb_passages::bible_books();
	if (isset($books['mbsb_index'][$book_id]))
		return $books['mbsb_index'][$book_id];
	else
		return false;
}

/**
* Returns a list of all possible frontend sections
*/
function mbsb_list_frontend_sections() {
	return array('main', 'media', 'preacher', 'series', 'passages');
}
?>