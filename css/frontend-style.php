<?php
/**
* Include file called on the plugins_loaded action
* 
* Dynamically creates an appropriate CSS file for frontend styling.
* 
* @package SermonBrowser
* @subpackage frontend_style
* @author Mark Barnes
*/
header ('Cache-Control: max-age=290304000, public');
header ('Expires: '.gmdate('D, d M Y H:i:s \G\M\T', time()+290304000));
header ('Content-type: text/css');
$date = @filemtime(__FILE__);
if ($date)
	header ('Last-Modified: '.gmdate('D, d M Y H:i:s \G\M\T', $date));
$color_black = 'black';
$color_white = 'white';
$font_headings = 'sans-serif';
$font_body = 'serif';
?>
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