<?php
header ('Cache-Control: max-age=290304000, public');
header ('Expires: '.gmdate('D, d M Y H:i:s \G\M\T', time()+290304000));
header ('Content-type: text/javascript');
$date = @filemtime(__FILE__);
if ($date)
	header ('Last-Modified: '.gmdate('D, d M Y H:i:s \G\M\T', $date));
if (!isset($_GET['name']))
	wp_die ('Script name not specified');
if ($_GET['name'] == 'sermon_upload') {
?>
function hide_all() {
	$('#upload-select').hide();
	$('#insert-select').hide();
	$('#url-select').hide();
	$('#embed-select').hide();
}
jQuery(document).ready(function($) {
	var orig_send_to_editor = window.send_to_editor;
	hide_all();
	$('#mbsb_new_media_type').change(function() {
		hide_all();
		$('#'+$(this).val()+'-select').show();
	});
	$('#mbsb_upload_media_button').click(function() {
		window.send_to_editor = function(html) {
			var attachment_url = $('img',html).attr('src');
			if($(attachment_url).length == 0) {
				attachment_url = $(html).attr('href');
			};
			$('#mbsb_media_1_attachment').val(attachment_url);
			tb_remove();
			window.send_to_editor = orig_send_to_editor;
		};
		tb_show('<?php _e('Upload a file for this sermon', MBSB);?>', 'media-upload.php?referer=mbsb_sermons&post_id=386&tab=type&TB_iframe=true', false);
		return false;
	});
	$('#mbsb_insert_media_button').click(function() {
		window.send_to_editor = function(html) {
			var attachment_url = $('img',html).attr('src');
			if($(attachment_url).length == 0) {
				attachment_url = $(html).attr('href');
			};
			$('#mbsb_media_1_attachment').val(attachment_url);
			tb_remove();
			window.send_to_editor = orig_send_to_editor;
		};
		tb_show('<?php _e('Attach an existing file to this sermon', MBSB);?>', 'media-upload.php?referer=mbsb_sermons&post_id=386&tab=library&TB_iframe=true', false);
		return false;
	});
});
<?php
}
die;
?>