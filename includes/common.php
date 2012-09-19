<?php
/**
* Include file that contains various filters and functions used throughout SermonBrowser
* that provide functionality specific to SermonBrowser.
* 
* @package SermonBrowser
* @subpackage helper_functions
* @author Mark Barnes
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
* @return string
*/
function mbsb_join_preacher ($join) {
	global $wpdb;
	return $join." INNER JOIN {$wpdb->prefix}postmeta AS preachers_postmeta ON ({$wpdb->prefix}posts.ID = preachers_postmeta.post_id) INNER JOIN {$wpdb->prefix}posts AS preachers ON (preachers.ID = preachers_postmeta.meta_value AND preachers.post_type = 'mbsb_preacher')";
}

/**
* Returns a the SQL to JOIN service metadata to a sermons query
* 
* Designed to filter posts_join_paged
* 
* @param string $join
* @return string
*/
function mbsb_join_service ($join) {
	global $wpdb;
	return $join." INNER JOIN {$wpdb->prefix}postmeta AS services_postmeta ON ({$wpdb->prefix}posts.ID = services_postmeta.post_id) INNER JOIN {$wpdb->prefix}posts AS services ON (services.ID = services_postmeta.meta_value AND services.post_type = 'mbsb_service')";
}

/**
* Returns a the SQL to JOIN series metadata to a sermons query
* 
* Designed to filter posts_join_paged
* 
* @param string $join
* @return string
*/
function mbsb_join_series ($join) {
	global $wpdb;
	return $join." INNER JOIN {$wpdb->prefix}postmeta AS series_postmeta ON ({$wpdb->prefix}posts.ID = series_postmeta.post_id) INNER JOIN {$wpdb->prefix}posts AS series ON (series.ID = series_postmeta.meta_value AND series.post_type = 'mbsb_series')";
}

/**
* Returns a the SQL to JOIN book metadata to a sermons query
* 
* Designed to filter posts_join_paged
* 
* @param string $join
* @return string
*/
function mbsb_join_book ($join) {
	global $wpdb;
	return $join." INNER JOIN {$wpdb->prefix}postmeta AS book_postmeta ON ({$wpdb->prefix}posts.ID = book_postmeta.post_ID AND book_postmeta.meta_key IN ('passage_start', 'passage_end'))";
}

/**
* Downloads a file after first checking the cache.
* 
* It does not use transient caching, as we will still use an out of date cache if the page is unreachable.
* 
* @param mixed $url
* @param mixed $cached_time
*/
function mbsb_cached_download ($url, $cached_time = 604800) { // 1 week
	$option_name = 'mbsb_cache_'.md5($url);
	$cached = get_option ($option_name);
	if ($cached && (($cached['time']+$cached_time) > time()))
		return $cached ['data'];
	else {
		$download = wp_remote_get ($url);
		if (is_wp_error ($download) || $download['response']['code'] != 200) {
			if ($cached) {
				$cached ['time'] = time() - $cached_time + min($cached_time, 21600); // Use out-of-date cache for no more than 6 more hours, or the specified cache time
				update_option ($option_name, $cached);
				return $cached ['data'];
			}
		} else
			update_option ($option_name, array ('data' => $download, 'time' => time()));
		return $download;
	}
} 

function mbsb_get_preferred_version() {
	if (mbsb_get_option ('allow_user_to_change_bible') && isset($_COOKIE['sermon_browser_bible']))
		return $_COOKIE['sermon_browser_bible'];
    else
		return mbsb_get_option ('bible_version_'.get_locale());
}

function mbsb_get_bible_list_dropdown($preferred_version = '') {
	$bibles = mbsb_get_bible_list();
	if ($preferred_version == '')
		$preferred_version = mbsb_get_preferred_version();
	$local_bibles = array();
	$other_bibles = array ('<optgroup label="'.__('Other languages', MBSB).'">');
	foreach ($bibles as $code => $bible) {
		if ($code == $preferred_version)
			$insert = ' selected="selected"';
		else
			$insert = '';
		if (strpos(get_locale(), "{$bible['language_code']}_") === 0)
			$local_bibles[] = "<option{$insert} value=\"{$code}\">{$bible['name']}</option>";
		else
			$other_bibles[] = "<option{$insert} value=\"{$code}\">{$bible['language_name']}: {$bible['name']}</option>";
	}
	$other_bibles[] = '</optgroup>';
	if (mbsb_get_option('hide_other_language_bibles'))
		$bibles = $local_bibles;
	else
		$bibles = array_merge ($local_bibles, $other_bibles);
	return  "<select id=\"bible_dropdown\">".implode('', $bibles).'</select>';
}


function mbsb_get_bible_list() {
	$bibles = get_transient ('mbsb_bible_list_'.get_locale());
	$bibles = false;
	if (!$bibles) {
		$biblia_bibles = mbsb_cached_download('http://api.biblia.com/v1/bible/find?key='.mbsb_get_api_key('biblia'));
		$biblia_bibles = json_decode($biblia_bibles['body']);
		if (isset($biblia_bibles->bibles)) {
			$biblia_ignore = mbsb_get_option ('ignored_biblia_bibles');
			$biblia_bibles = $biblia_bibles->bibles;
			foreach ($biblia_bibles as $bible) {
				$bible->title = trim(str_replace ('With Morphology', '', $bible->title));
				if (strtolower(substr($bible->title, 0, 4)) == 'the ')
					$bible->title = substr($bible->title, 4);
				if (!in_array($bible->bible, $biblia_ignore))
					$bibles[$bible->bible] = array ('name' => $bible->title, 'language_code' => $bible->languages[0], 'language_name' => mbsb_bible_language_from_code($bible->languages[0]), 'service' => 'biblia');
			}
		}
		uasort($bibles, 'mbsb_bible_sort');
		set_transient ('mbsb_bible_list_'.get_locale(), $bibles, 604800);
	}
	return $bibles;
}

function mbsb_get_api_key($service) {
	if ($service == 'biblia')
		return mbsb_get_option('biblia_api_key');
}

function mbsb_get_bible_details($version) {
	$bibles = mbsb_get_bible_list();
	if (isset($bibles[$version]))
		return $bibles[$version];
	else
		return false;
}

function mbsb_bible_sort ($a, $b) {
	if (($a['name'] == $b['name']) && ($a['language_name'] == $b['language_name']))
		return 0;
	elseif ($a['language_name'] == $b['language_name'])
		return ($a['name'] > $b['name']) ? 1 : -1;
	else
		return ($a['language_name'] > $b['language_name']) ? 1 : -1;
}
	
function mbsb_bible_language_from_code ($code) {
	$languages = array ('ar' => __('Arabic', MBSB), 'el' => __('Greek', MBSB), 'en' => __('English', MBSB), 'eo' => __('Esperanto', MBSB), 'fi' => __('Finnish'), 'fr' => __('French', MBSB), 'it' => 'Italian', 'nl' => __('Dutch'), 'pt' => 'Portuguese', 'ru' => 'Russian');
	if (isset($languages[$code]))
		return $languages[$code];
	else
		return $code;
}
?>