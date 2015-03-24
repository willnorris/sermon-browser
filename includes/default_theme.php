<?php
/**
* Filters and actions that comprise the default theme
*
* @package SermonBrowser
* @subpackage Theme_Default
* @author Mark Barnes <mark@sermonbrowser.com>
*/

add_filter ('mbsb_display_sermons', 'mbsb_default_theme_display_sermons');

/**
* Returns the sermon archive page
*
* @param string $content
* @return string
*/
function mbsb_default_theme_display_sermons ($content) {
	$output = mbsb_get_sermon_filters();
	return $output;
}
?>
