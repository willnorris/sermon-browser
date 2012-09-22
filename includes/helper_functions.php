<?php
/**
* Include file that contains various helper functions used throughout SermonBrowser
* that provide fairly generic funtionality, such as getting and setting WordPress options.
* 
* @package SermonBrowser
* @subpackage helper_functions
* @author Mark Barnes
*/

// Temporary filter whilst the Options Page is being built.
add_filter ('option_sermon_browser_2', 'mbsb_default_options');
add_filter ('default_option_sermon_browser_2', 'mbsb_default_options');

function mbsb_default_options($all_options) {
	//Standard options
	$all_options ['audio_shortcode'] = '[mejsaudio src="%URL%"]';
	$all_options ['video_shortcode'] = '[mejsvideo src="%URL%"]';
	$all_options ['bible_version_en_US'] = 'asv';
	$all_options ['allow_user_to_change_bible'] = true;
	//Advanced options
	include ('api_keys.php');
	$all_options ['ignored_biblia_bibles'] = array ('emphbbl', 'kjv', 'KJVAPOC', 'scrmorph', 'wh1881mr');
	$all_options ['ignored_biblesearch_bibles'] = array('KJV', 'KJVA');
	$all_options ['hide_other_language_bibles'] = false;
	$all_options ['add_all_types_to_admin_bar'] = false;
	//Standard template options
	$all_options ['frontend_sermon_sections'] = array ('main', 'media', 'preacher', 'series', 'passages');
	$all_options ['hide_media_heading'] = false;
	$all_options ['sermon_image'] = 'alignright';
	$all_options ['preacher_image'] = 'alignright';
	$all_options ['series_image'] = 'alignright';
	$all_options ['service_image'] = 'alignright';
	$all_options ['color_bar'] = 'black';
	$all_options ['add_download_links'] = true;
	//Advanced template options
	$all_options ['sermon_image_size'] = array ('width' => '230', 'height' => '129', 'crop' => false);
	$all_options ['preacher_image_size'] = array ('width' => '150', 'height' => '150', 'crop' => true);
	$all_options ['series_image_size'] = array ('width' => '150', 'height' => '150', 'crop' => true);
	$all_options ['service_image_size'] = array ('width' => '150', 'height' => '150', 'crop' => true);
	$all_options ['excerpt_length'] = 55;
	$all_options ['show_statistics_on_sermon_page'] = true;
	//Options still to be implemented
	$all_options ['append_passage_to_title_in_feed'] = true;
	return $all_options;
	
	/*
	Filters available:
	=======================
	mbsb_attachment_row_actions
	mbsb_get_option_*
	mbsb_add_media_types
	
	Actions available:
	==================
	mbsb_admin_javascript
	mbsb_admin_jQuery_document_ready
	*/
}

/**
* Gets a SermonBrowser option
* 
* @param string $option_name - the name of the option
* @param mixed $default - the default value if the option does not exist
* @param return mixed
*/
function mbsb_get_option ($option, $default = false) {
	$all_options = get_option ('sermon_browser_2');
	if (isset ($all_options[$option]))
		$return = $all_options[$option];
	else
		$return = $default;
	return apply_filters ("mbsb_get_option_{$option}", $return);
}

/**
* Updates a SermonBrowser option
* 
* @param string $option
* @param mixed $new_value
* @return boolean - true on success, false on failure
*/
function mbsb_update_option ($option, $new_value) {
	$all_options = get_option ('sermon_browser_2');
	$all_options [$option] = $new_value;
	return update_option ('sermon_browser_2', $all_options);
}

/**
* Deletes a SermonBrowser option
* 
* @param string $option
* @return boolean - true on success, false on failure
*/
function mbsb_delete_option ($option) {
	$all_options = get_option ('sermon_browser_2');
	if (!$all_options)
		return false;
	unset ($all_options [$option]);
	return update_option ('sermon_browser_2');
}

/**
* Shortens a text string so that it is less than a maximum length
* 
* Attempts to shorten by removing whole words, either in the middle, or at the end.
* 
* @param string $string
* @param integer $max_length
* @return string
*/
function mbsb_shorten_string ($string, $max_length = 30) {
	if (strlen($string) <= $max_length)
		return $string;
	$offset = min((integer)($max_length/4), 10);
	$break_characters = array ('-', '+', ' ', '_');
	$left_array = $right_array = array();
	foreach ($break_characters as $b) {
		$left_array[] = ($a = strpos($string, $b, $offset)) ? $a : 999;
		$left_array[] = ($a = strpos($string, htmlentities($b), $offset)) ? $a : 999;
		$right_array[] = ($a = strrpos($string, $b, -$offset)) ? $a : 999;
		$right_array[] = ($a = strrpos($string, htmlentities($b), -$offset)) ? $a : 999;
	}
	$new_string = ($left = substr($string, 0, min($left_array))).'…'.substr($string, min($right_array)+1);
	if (strlen($new_string) > $max_length)
		return substr($left, 0, $max_length-1).'…';
	else
		return $new_string;
}

/**
* Returns a nicely formatted byte-size string, complete with appropriate units (e.g. 12345678 becomes 12.34MB)
* 
* @param integer $bytes
* @return string
*/
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