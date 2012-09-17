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
	$posts = get_posts (array ('orderby' => 'title', 'order' => 'ASC', 'post_type' => "mbsb_{$custom_post_type}", 'numberposts' => 999, 'posts_per_page' => 999));
	if (is_array($posts)) {
		$output = '';
		foreach ($posts as $post) {
			if ($selected != '' && $post->ID == $selected)
				$insert = ' selected="selected"';
			else
				$insert = '';
			$output .= "<option value=\"{$post->ID}\"{$insert}>".esc_html($post->post_title)."&nbsp;</option>";
		}
	}
	if (!empty($additions) && is_array($additions))
		foreach ($additions as $id => $text) {
			if ($selected != '' && $id == $selected)
				$insert = ' selected="selected"';
			else
				$insert = '';
			$output .= "<option value=\"{$id}\"{$insert}>".esc_html($text)."&nbsp;</option>";
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
* Ensures there is always at least one sermon/series/preacher/service
* Does not allow series/preacher/services to be deleted if they are in use
* Filters user_has_cap
* 
* Cannot use wp_count_posts() because of a WordPress bug
* @link https://core.trac.wordpress.org/ticket/21879
* 
* @param array $allcaps
* @param array $caps
* @param array $args
* @return array
*/
function mbsb_prevent_cpt_deletions ($allcaps, $caps, $args) {
	global $wpdb;
	if (isset($args[0]) && isset($args[2]) && $args[0] == 'delete_post') {
		$post = get_post ($args[2]);
		if ($post->post_status == 'publish' && substr($post->post_type, 0, 5) == 'mbsb_') {
			$query = "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type = %s";
			$num_posts = $wpdb->get_var ($wpdb->prepare ($query, $post->post_type));
			if ($num_posts < 2)
				$allcaps[$caps[0]] = false;
		}
		if ($post->post_type != 'mbsb_sermon') {
			$type = substr($post->post_type, 5);
			if (query_posts (array ('post_type' => 'mbsb_sermon', 'meta_query' => array (array('key' => $type, 'value' => $args[2])))))
				$allcaps[$caps[0]] = false;
		}
	}
	return $allcaps;
}
?>