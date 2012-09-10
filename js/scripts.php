<?php
header ('Cache-Control: max-age=290304000, public');
header ('Expires: '.gmdate('D, d M Y H:i:s \G\M\T', time()+290304000));
header ('Content-type: text/javascript; charset=utf-8');
$date = @filemtime(__FILE__);
if ($date)
	header ('Last-Modified: '.gmdate('D, d M Y H:i:s \G\M\T', $date));
if (!isset($_GET['name']))
	wp_die ('Script name not specified');
if ($_GET['name'] == 'sermon_upload') {
?>
function mbsb_hide_all() {
	$('#upload-select').hide();
	$('#insert-select').hide();
	$('#url-select').hide();
	$('#embed-select').hide();
}
function mbsb_handle_upload_insert_click() {
	var orig_send_to_editor = window.send_to_editor;
	window.send_to_editor = function(html) {
		var attachment_url = $('img',html).attr('src');
		if($(attachment_url).length == 0) {
			attachment_url = $(html).attr('href');
		};
		var data = {
			action: 'mbsb_attachment_insert',
			url: attachment_url,
			_wpnonce: '<?php echo wp_create_nonce("mbsb_attachment_insert_{$_GET['post_id']}") ?>',
			post_id: <?php echo $_GET['post_id']; ?>
		};
		$.post(ajaxurl, data, function(response) {
			response = JSON.parse (response);
			row_id = response.row_id; 
			$('#mbsb_attached_files').prepend(response.code);
			$('#row_'+row_id).show(1200);
		});
		tb_remove();
		window.send_to_editor = orig_send_to_editor;
	};
}
function mbsb_handle_url_embed (type) {
	var data = {
		action: 'mbsb_attach_url_embed',
		type: type,
		attachment: $('#mbsb_input_'+type).val(),
		_wpnonce: '<?php echo wp_create_nonce("mbsb_handle_url_embed_{$_GET['post_id']}") ?>',
		post_id: <?php echo $_GET['post_id']; ?>
	};
	$.post(ajaxurl, data, function(response) {
		if (type == 'url') {
			$('#mbsb_attach_url_button').val('<?php _e ('Attach', MBSB)?>');
			$('#mbsb_attach_url_button').removeAttr('disabled');
		};
		response = JSON.parse (response);
		row_id = response.row_id; 
		$('#mbsb_attached_files').prepend(response.code);
		$('#row_'+row_id).show(1200);
	});
}
jQuery(document).ready(function($) {
	$('#mbsb_new_media_type').val('none');
	$('#mbsb_new_media_type').change(function() {
		mbsb_hide_all();
		$('#'+$(this).val()+'-select').show();
	});
	$('#mbsb_upload_media_button').click(function() {
		mbsb_handle_upload_insert_click();
		tb_show('<?php _e('Upload a file for this sermon', MBSB);?>', 'media-upload.php?referer=mbsb_sermons&post_id=<?php echo $_GET['post_id']; ?>&tab=type&TB_iframe=true', false);
		return false;
	});
	$('#mbsb_insert_media_button').click(function() {
		mbsb_handle_upload_insert_click();
		tb_show('<?php _e('Attach an existing file to this sermon', MBSB);?>', 'media-upload.php?referer=mbsb_sermons&post_id=<?php echo $_GET['post_id']; ?>&tab=library&TB_iframe=true', false);
		return false;
	});
	$('#mbsb_attach_url_button').click(function() {
		$('#mbsb_attach_url_button').val('<?php _e ('Please wait', MBSB)?>');
		$('#mbsb_attach_url_button').attr('disabled', 'disabled');
		mbsb_handle_url_embed ('url');
		return false;
	});
	$('#mbsb_attach_embed_button').click(function() {
		mbsb_handle_url_embed ('embed');
		return false;
	});
	$('table#mbsb_attached_files').on('click', 'a.unattach', function (e) {
		var data = {
			action: 'mbsb_remove_attachment',
			attachment_id:  $(this).parent().attr('id').slice(13),
			_wpnonce: '<?php echo wp_create_nonce("mbsb_remove_attachment_{$_GET['post_id']}") ?>',
			post_id: <?php echo $_GET['post_id']; ?>
		};
		$.post(ajaxurl, data, function(response) {
			response = JSON.parse (response);
			if (response.result == 'success') {
				row_id = response.row_id; 
				$('#row_'+row_id).hide(600);
			} else {
				$(this).after('response.message');
			};
		});
		e.preventDefault();
	});
	$('table#mbsb_attached_files').on('mouseenter', 'tr', function (e) {
		row_id = $(this).children('td').attr('id');
		$('#'+row_id+' a.unattach').fadeIn(200);
	});
	$('table#mbsb_attached_files').on('mouseleave', 'tr', function (e) {
		row_id = $(this).children('td').attr('id');
		$('#'+row_id+' a.unattach').fadeOut(200);
	});
});
<?php
}
die;
?>