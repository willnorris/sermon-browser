<?php
/**
* Include file called on the plugins_loaded action
*
* Dynamically creates an appropriate CSS file for frontend styling.
*
* @package SermonBrowser
* @subpackage Frontend
* @author Mark Barnes <mark@sermonbrowser.com>
*/
header ('Cache-Control: max-age=290304000, public');
header ('Expires: '.gmdate('D, d M Y H:i:s \G\M\T', time()+290304000));
header ('Content-type: text/css');
$date = @filemtime(__FILE__);
if ($date) {
	header ('Last-Modified: '.gmdate('D, d M Y H:i:s \G\M\T', $date));
}
$color_black = 'black';
$color_white = 'white';
$font_headings = 'sans-serif';
$font_body = 'serif';
?>
.mbsb_hide {
	display: none;
}
div.sermon_media_list {
	clear:both;
}
span.title_passage {
	font-size: 75%;
}
div.sermon_media_item {
	margin: 12px 0;
}
div.mbsb_collapsible_heading {
	width:100%;
	display:table;
	background-color: <?php echo $color_black; ?>;
	color: <?php echo $color_white; ?>;
	padding: 0.5em;
	margin-bottom: 1em;
	font-weight: bold;
	font-family: <?php echo $font_headings; ?>;
	font-size: 125%;
}
div.mbsb_collapsible_heading a {
	color: <?php echo $color_white; ?>;
	text-decoration:none;
}
div.mbsb_collapsible_heading div.alignright {
	margin-left:0;
	margin-top:0;
}
div.mbsb_collapsible_heading div.alignleft {
	margin-right:0;
	margin-top:0;
}
.mbsb_textright {
	text-align:right;
}
.mbsb_textleft {
	text-align:left;
}
div.series_next_previous {
	display: table;
	margin-bottom: 1em;
	width: 100%;
}
div.passage_heading {
	font-family: <?php echo $font_headings; ?>;
	font-weight: bold;
}
#passages_bible_loader {
	margin: 0 10px 0 0;
	padding: 0;
	border: none;
	height: 16px;
	float: left;
}
#passages_bible_loader img {
	margin: 0 10px 0 0;
	padding: 0;
	border: none;
}
#passages_bible_dropdown {
	display:table;
	width:100%;
}
#passages_wrap #bible_dropdown {
	float:right;
	margin-bottom: 0.5em;
}
#passages_text div.biblesearch {
	margin-bottom: 1em;
}
#passages_text div.biblesearch span.divineName {
	font-variant: small-caps;
}
#passages_text div.biblesearch_BCN span.divineName {
	text-transform: lowercase;
}
#passages_text div.biblesearch_BCN span.divineName span.divineCaps {
	text-transform: none;
}
#passages_text div.preaching_central span.chapter_num, #passages_text div.netbible span.chapter_num {
	font-weight: bold;
	font-size: 175%;
}
#passages_text div.lang_ara, #passages_text div.lang_heb {
	direction: rtl;
	font-size: 150%;
	line-height: 150%;
}
#passages_powered_by {
	margin: 0;
	padding: 0;
	border: none;
	float:right;
}
#passages_powered_by img {
	margin: 0;
	padding: 0;
	border: none;
}
#sermon_filter_form select {
	margin: 0 1em 0 0;
}
#sermon_filter_form #sermon_filter {
	float:left;
}
#sermon_filter_form #sermon_control {
	float:right;
}
