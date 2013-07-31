<?php
/**
* Include file called when requested on the plugins_loaded action
* 
* Outputs javascript for the add/edit sermons page, then dies.
* It's a PHP file so that we can internationalise it and add nonces
* 
* @package SermonBrowser
* @subpackage Javascript
* @author Mark Barnes <mark@sermonbrowser.com>
*/
header ('Cache-Control: max-age=43200, public'); // 12 hours, to make sure that nonces remain current
header ('Expires: '.gmdate('D, d M Y H:i:s \G\M\T', time()+43200)); 
header ('Content-type: text/javascript; charset=utf-8');
$date = @filemtime(__FILE__);
if ($date)
	header ('Last-Modified: '.gmdate('D, d M Y H:i:s \G\M\T', $date));
?>
var mbsb_orig_time;
var orig_send_to_editor;
/**
* Enable/Disable time field as required
*/
function lock_time_field() {
	if (jQuery('#mbsb_override_time').prop('checked')) {
		jQuery('#mbsb_time').removeAttr('disabled');
		jQuery('#mbsb_time').css('background-color', 'white');
		jQuery('#mbsb_time').val(mbsb_orig_time);
	} else {
		jQuery('#mbsb_time').attr('disabled', 'disabled');
		jQuery('#mbsb_time').css('background-color', '#F5F5F5');
		mbsb_orig_time = jQuery('#mbsb_time').val();
		jQuery('#mbsb_time').val('');
	}
}
/**
* Hide all attach inputs until required
*/
function mbsb_hide_all() {
	jQuery('#upload-select').hide();
	jQuery('#insert-select').hide();
	jQuery('#url-select').hide();
	jQuery('#embed-select').hide();
	jQuery('#legacy-select').hide();
}

function add_new_select (post_type, option_name, option_id) {
	var addition = '<option selected="selected" value="'+option_id+'">'+option_name+'</option>';
	jQuery('#'+post_type).append(addition);
	jQuery('#'+post_type).val(option_id);
	tb_remove();
}

/**
* Handle an upload/library attachment
*/
function mbsb_handle_upload_insert_click() {
	orig_send_to_editor = window.send_to_editor;
	window.send_to_editor = function(html) {
		var attachment_url = jQuery('img',html).attr('src');
		if(jQuery(attachment_url).length == 0) {
			attachment_url = jQuery(html).attr('href');
		};
		var data = {
			action: 'mbsb_attachment_insert',
			url: attachment_url,
			_wpnonce: '<?php echo wp_create_nonce('mbsb_attachment_insert') ?>',
			post_id: mbsb_sermon_id
		};
		jQuery.post(ajaxurl, data, function(response) {
			response = JSON.parse (response);
			row_id = response.row_id; 
			jQuery('#mbsb_attached_files').prepend(response.code);
			jQuery('#row_'+row_id).show(1200);
		});
		tb_remove();
		window.send_to_editor = orig_send_to_editor;
	};
}
/**
* Handle a URL/embed attachment
*/
function mbsb_handle_url_embed (type) {
	var data = {
		action: 'mbsb_attach_url_embed',
		type: type,
		attachment: jQuery('#mbsb_input_'+type).val(),
		_wpnonce: '<?php echo wp_create_nonce("mbsb_handle_url_embed") ?>',
		post_id: mbsb_sermon_id
	};
	jQuery.post(ajaxurl, data, function(response) {
		if (type == 'url') {
			jQuery('#mbsb_attach_url_button').val('<?php _e ('Attach', MBSB)?>');
			jQuery('#mbsb_attach_url_button').removeAttr('disabled');
		};
		response = JSON.parse (response);
		row_id = response.row_id; 
		jQuery('#mbsb_attached_files').prepend(response.code);
		jQuery('#row_'+row_id).show(1200);
	});
}
/**
* Handle a legacy attachment
*/
function mbsb_handle_legacy (file) {
	var data = {
		action: 'mbsb_attach_legacy',
		attachment: file,
		_wpnonce: '<?php echo wp_create_nonce("mbsb_handle_legacy") ?>',
		post_id: mbsb_sermon_id
	};
	jQuery.post(ajaxurl, data, function(response) {
		response = JSON.parse (response);
		row_id = response.row_id; 
		jQuery('#mbsb_attached_files').prepend(response.code);
		jQuery('#row_'+row_id).show(1200);
	});
}
<?php do_action ('mbsb_add_edit_sermon_javascript'); ?>

/**
* The main jQuery function that runs when the document is ready
*/
jQuery(document).ready(function($) {
	mbsb_orig_time = $('#mbsb_time').val();
	lock_time_field();
	$('#mbsb_new_media_type').val('none');
	//Watch for changes to the time override checkbox
	$('#mbsb_override_time').change(function() {
		lock_time_field();
	});
	//Watch for changes to the media type dropdown and show fields as required
	$('#mbsb_new_media_type').change(function() {
		mbsb_hide_all();
		$('#'+$(this).val()+'-select').show();
	});
	//Watch for the upload button being clicked
	$('#mbsb_upload_media_button').click(function() {
		mbsb_handle_upload_insert_click();
		tb_show('<?php _e('Upload a file for this sermon', MBSB);?>', 'media-upload.php?referer=mbsb_sermon&post_id='+mbsb_sermon_id+'&showtab=type&TB_iframe=true', false);
		return false;
	});
	//Watch for the library button being clicked
	$('#mbsb_insert_media_button').click(function() {
		mbsb_handle_upload_insert_click();
		tb_show('<?php _e('Attach an existing file to this sermon', MBSB);?>', 'media-upload.php?referer=mbsb_sermon&post_id='+mbsb_sermon_id+'&showtab=library&TB_iframe=true', false);
		return false;
	});
	//Watch for the URL button being clicked
	$('#mbsb_attach_url_button').click(function() {
		$('#mbsb_attach_url_button').val('<?php _e ('Please wait', MBSB)?>');
		$('#mbsb_attach_url_button').attr('disabled', 'disabled');
		mbsb_handle_url_embed ('url');
		return false;
	});
	//Watch for the embed button being clicked
	$('#mbsb_attach_embed_button').click(function() {
		mbsb_handle_url_embed ('embed');
		return false;
	});
	//Watch for the legacy button being clicked
	$('#mbsb_attach_legacy_button').click(function() {
		tb_show('<?php _e('Choose a file from the legacy upload folder for this sermon', MBSB);?>', '#TB_inline?inlineId=legacy_file_tree', false);
		return false;
	});	
	//Watch for the unattach button being clicked
	$('table#mbsb_attached_files').on('click', 'a.unattach', function (e) {
		var data = {
			action: 'mbsb_remove_attachment',
			attachment_id:  $(this).parent().attr('id').slice(13),
			_wpnonce: '<?php echo wp_create_nonce("mbsb_remove_attachment") ?>',
			post_id: mbsb_sermon_id
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
	//Display the unattach link on mouseenter
	$('table#mbsb_attached_files').on('mouseenter', 'tr', function (e) {
		row_id = $(this).children('td').attr('id');
		$('#'+row_id+' a.unattach').fadeIn(200);
	});
	//Hide the unattach link on mouseleave
	$('table#mbsb_attached_files').on('mouseleave', 'tr', function (e) {
		row_id = $(this).children('td').attr('id');
		$('#'+row_id+' a.unattach').fadeOut(200);
	});
	//Add the date picker
	$('.add-date-picker').datepicker({
		dateFormat : 'yy-mm-dd'
	});
	//Add the File Tree
	$('#legacy_file_tree').fileTree({ 
			root: '',
			script: ajaxurl,
			_wpnonce: '<?php echo wp_create_nonce('mbsb_jqueryFileTree') ?>',
			action: 'mbsb_jqueryFileTree',
			multiFolder: false
		},
		function(file) {
			if ( confirm('<?php _e('Would you like to attach file ', MBSB) ?>'+file+' ?') ) {
				tb_remove();  // close thickbox
				mbsb_handle_legacy(file);
			}
		}
	);
<?php
		$post_types = array ('preacher' => esc_js(__('Add a new preacher', MBSB)), 'series' => esc_js(__('Add a new series', MBSB)), 'service' => esc_js(__('Add a new service', MBSB)));
		foreach ($post_types as $post_type => $add_message) {
?>
	//Watch for the 'Add a new <?php echo $post_type; ?>' option
	$('#mbsb_<?php echo $post_type; ?>').change(function() {
		if ($(this).val() == 'new_<?php echo $post_type; ?>') {
			tb_show('<?php echo $add_message;?>', 'post-new.php?post_type=mbsb_<?php echo $post_type; ?>&iframe=true&TB_iframe', false);
		}
	});
<?php
		}
		do_action ('mbsb_add_edit_sermon_jQuery');
?>
});