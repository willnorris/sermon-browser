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