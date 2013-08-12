<?php
/**
* Include file that contains various helper functions used throughout SermonBrowser
* that provide fairly generic funtionality, such as getting and setting WordPress options.
* 
* @package SermonBrowser
* @subpackage Common
* @author Mark Barnes <mark@sermonbrowser.com>
*/

// Temporary filter whilst the Options Page is being built.
//add_filter ('option_sermon_browser_2', 'mbsb_default_options');
add_filter ('default_option_sermon_browser_2', 'mbsb_default_options');

/**
* Supplies the default SermonBrowser options
* 
* Temporarily filters option_sermon_browser_2 and default_option_sermon_browser_2
* 
* @param array $all_options
* @return array
*/
function mbsb_default_options($all_options=array() ) {
	//General Options
	$all_options ['sermons_slug']   = _x('sermons', MBSB, 'sermons default slug');
	$all_options ['series_slug']    = _x('series', MBSB, 'series default slug');
	$all_options ['preachers_slug'] = _x('preachers', MBSB, 'preachers default slug');
	$all_options ['services_slug']  = _x('services', MBSB, 'services default slug');
	$all_options ['legacy_upload_folder'] = 'wp-content/uploads/sermons/';
	//Media Player Options
	$all_options ['audio_shortcode'] = '[audio src="%URL%"]';
	$all_options ['video_shortcode'] = '[video src="%URL%"]';
	//Layout Options
	$all_options ['frontend_sermon_sections'] = array ('main', 'media', 'preacher', 'series', 'passages');
	$all_options ['hide_media_heading'] = false;
	$all_options ['sermon_image_pos'] = 'alignright';
	$all_options ['preacher_image_pos'] = 'alignright';
	$all_options ['series_image_pos'] = 'alignright';
	$all_options ['add_download_links'] = true;
	$all_options ['sermon_image_size'] = array ('width' => '230', 'height' => '129', 'crop' => false);
	$all_options ['preacher_image_size'] = array ('width' => '150', 'height' => '150', 'crop' => true);
	$all_options ['series_image_size'] = array ('width' => '150', 'height' => '150', 'crop' => true);
	$all_options ['excerpt_length'] = 55;
	$all_options ['show_statistics_on_sermon_page'] = true;
	//Bible Version Options
	$all_options ['bible_version_'.get_locale()] = 'esv';
	$all_options ['use_embedded_bible_'.get_locale()] = false;
	$all_options ['allow_user_to_change_bible'] = true;
	//Bible API Keys
	$all_options ['biblia_api_key'] = '';
	$all_options ['biblesearch_api_key'] = '';
	$all_options ['esv_api_key'] = 'IP';
	//Podcast Feed Options
	$all_options ['podcast_feed_title'] = '';
	$all_options ['podcast_feed_description'] = '';
	$all_options ['podcast_feed_author'] = '';
	$all_options ['podcast_feed_summary'] = '';
	$all_options ['podcast_feed_owner_name'] = '';
	$all_options ['podcast_feed_owner_email'] = '';
	$all_options ['podcast_feed_image'] = '';
	$all_options ['podcast_feed_category'] = 'Religion & Spirituality/Christianity';
	//Advanced options (not currently shown on Options screen)
	$all_options ['inactive_bibles'] = array ('emphbbl', 'elberfelder', 'ostervald', 'bibelselskap', 'croatia', 'newvulgate', 'esperanto', 'manxgaelic', 'aleppo', 'turkish', 'afrikaans', 'amharic', 'scotsgaelic', 'bohairic', 'georgian', 'schlachter', 'rv1858', 'danish', 'tamajaq', 'peshitta', 'coptic', 'chamorro', 'kabyle', 'ukranian', 'turkish', 'martin', 'makarij', 'nkb', 'kms', 'bkr', 'vulgate', 'sagradas', 'modernhebrew', 'easternarmenian', 'estonian', 'albanian', 'wolof', 'pyharaamattu', 'finnish1776', 'zhuromsky', 'gothic', 'sahidic', 'moderngreek', 'breton', 'westernarmenian', 'uma', 'elberfelder1905', 'latvian', 'xhosa', 'swedish', 'riveduta', 'basque', 'judson', 'lithuanian', 'giovanni', 'thai', 'tischendorf', 'tagalog', 'pyharaamattu1933', 'vietnamese', 'web', 'hnv');
	$all_options ['inactive_bible_languages'] = array('kor', 'rum');
	$all_options ['hide_other_language_bibles'] = false;
	$all_options ['add_all_types_to_admin_bar'] = false;
	$all_options ['embedded_bible_parameters'] = array ('width' => '100%', 'height' => '600', 'layout' => 'normal', 'historyButtons' => true, 'navigationBox' => true, 'resourcePicker' => true, 'shareButton' => true, 'textSizeButton' =>true);
	//Options still to be implemented
	$all_options ['service_image_pos'] = 'alignright';
	$all_options ['service_image_size'] = array ('width' => '150', 'height' => '150', 'crop' => true);
	$all_options ['color_bar'] = 'black';
	$all_options ['append_passage_to_title_in_feed'] = true;
	return $all_options;
	
	/*
	Filters available:
	=======================
	mbsb_theme
	mbsb_attachment_row_actions
	mbsb_get_option_*
	mbsb_add_media_types
	mbsb_preaching_central_bibles
	mbsb_language_code_table
	mbsb_equivalent_bibles
	
	Actions available:
	==================
	mbsb_add_edit_sermon_javascript
	mbsb_add_edit_sermon_jQuery
	mbsb_frontend_jQuery
	*/
}

/**
* Gets a SermonBrowser option
* 
* @param string $option - the name of the option
* @param mixed $default - the default value if the option does not exist
* @return mixed
*/
function mbsb_get_option ($option, $default = null) {
	$all_options = get_option ('sermon_browser_2');
	if ( isset($all_options[$option]) and $all_options[$option] )
		$return = $all_options[$option];
	else
		if ($default === null)
			$return = mbsb_get_default_option($option);
		else
			$return = $default;
	return apply_filters ("mbsb_get_option_{$option}", $return);
}

/**
* Gets a Sermon Browser 1 option (previous plugin version)
*
* @param string $option - the name of the SB1 option
* @return mixed - returns null if the option does not exist
*/
function mbsb_get_sb1_option ($option) {
	$sb1_options = unserialize( base64_decode( get_option('sermonbrowser_options') ) );
	if ( $sb1_options and isset($sb1_options[$option]) )
		return $sb1_options[$option];
	else
		return null;
}

/**
* Gets a SermonBrowser default option
*
* @param string $option - the name of the option
* @return mixed
*/
function mbsb_get_default_option ($option) {
	$default_options = mbsb_default_options();
	if (isset($default_options[$option])) {
		return $default_options[$option];
	}
	else {
		return null;
	}
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

/**
* Case insensitive version of in_array
* 
* @param mixed $needle
* @param array $haystack
* @return boolean
*/
function in_array_ic ($needle, $haystack) {
	return in_array(strtolower($needle), array_change_key_case($haystack));
}

/**
* Case insensitive version of array_key_exists
* 
* @param mixed $key
* @param array $search
* @return boolean
*/
function array_key_exists_ic ($key, $search) {
	return array_key_exists (strtolower($key), array_change_key_case($search));
}

/**
* Returns WordPress install path.  
* Does the same thing as native WordPress get_home_path(), but that function was not defined early enough to use it.
*
* @return string
*/
function mbsb_get_home_path() {
	$home = get_option( 'home' );
	$siteurl = get_option( 'siteurl' );
	if ( ! empty( $home ) && 0 !== strcasecmp( $home, $siteurl ) ) {
		$wp_path_rel_to_home = str_ireplace( $home, '', $siteurl ); /* $siteurl - $home */
		$pos = strripos( str_replace( '\\', '/', $_SERVER['SCRIPT_FILENAME'] ), trailingslashit( $wp_path_rel_to_home ) );
		$home_path = substr( $_SERVER['SCRIPT_FILENAME'], 0, $pos );
		$home_path = trailingslashit( $home_path );
	} else {
		$home_path = ABSPATH;
	}
	return $home_path;
}
?>