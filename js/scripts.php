<?php
/**
* Include file called when requested on the plugins_loaded action
* 
* Outputs customised javascript, then dies.
* 
* @package SermonBrowser
* @subpackage scripts
* @author Mark Barnes
*/
header ('Cache-Control: max-age=290304000, public');
header ('Expires: '.gmdate('D, d M Y H:i:s \G\M\T', time()+290304000));
header ('Content-type: text/javascript; charset=utf-8');
$date = @filemtime(__FILE__);
if ($date)
	header ('Last-Modified: '.gmdate('D, d M Y H:i:s \G\M\T', $date));
if (!isset($_GET['name']))
	wp_die ('Script name not specified');
if ($_GET['name'] == 'main_admin_script') {
	if ($_GET['post_type'] == 'sermon') {
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
}

function add_new_select (post_type, option_name, option_id) {
	var addition = '<option selected="selected" value="'+option_id+'">'+option_name+'</option>';
	jQuery('#mbsb_'+post_type).append(addition);
	jQuery('#mbsb_'+post_type).val(option_id);
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
<?php do_action ('mbsb_admin_javascript'); ?>

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
		do_action ('mbsb_admin_jQuery_document_ready');
?>
});
<?php
	}
} elseif ($_GET['name'] == 'add_new_option') {
?>
jQuery(document).ready(function($) {
	$('#publish').click(function() {
		var name = $('#title').val();
		parent.add_new_select(mbsb_post_type, name, mbsb_sermon_id);
	});
});
<?php
} elseif ($_GET['name'] == 'frontend_script') {
?>
var mbsb_ajaxurl = '<?php echo admin_url( 'admin-ajax.php', 'relative' ); ?>';

function mbsbSetCookie(cookieName, value, numDays) {
	var expiryDate = new Date();
	expiryDate.setDate(expiryDate.getDate() + numDays);
	var cookieValue = cookieName + "=" + escape(value) + ((numDays==null) ? "" : "; expires=" + expiryDate.toUTCString()) + "; path=<?php echo esc_js(COOKIEPATH) ?>";
	document.cookie = cookieValue;
}
function mbsbGetCookie(cookieName) {
    var nameEQ = cookieName + "=";
    var ca = document.cookie.split(';');
    for(var i = 0; i < ca.length; i++) {
        var c = ca[i];
        while (c.charAt(0) == ' ')
        	c = c.substring(1, c.length);
        if (c.indexOf(nameEQ) == 0)
        	return c.substring(nameEQ.length, c.length);
    }
    return null;
}
/**
* The main jQuery function that runs when the document is ready
*/
jQuery(document).ready(function($) {
	hideableSections = new Array("sermon_media_list", "preacher_preacher", "series_series", "service_service", "passages_wrap");
	$.each (hideableSections, function (key, value) {
		var display = mbsbGetCookie('sermon_browser_section_'+value);
		if (display == 'hide') {
			$('div.'+value).hide();
			var a = $('div.'+value).prev().find('a.heading_pointer').html('&#9654;');
		}
	});
	$('#bible_dropdown').change(function() {
		var version = $(this).val();
		mbsbSetCookie('sermon_browser_bible', version, 365);
		$('#passages_bible_loader').html('<img src="<?php echo admin_url('images/loading.gif');?>" alt="<?php _e('Loading', MBSB);?>&hellip;"/><?php _e('Requesting', MBSB);?>&hellip;');
		var data = {
			action: 'mbsb_get_bible_text',
			version: version,
			post_id: mbsb_sermon_id
		};
		$.post(mbsb_ajaxurl, data, function(response) {
			$('#passages_text').fadeOut('slow', function() {
				$(this).html(response)
			}).fadeIn('slow');
			$('#passages_bible_loader').fadeOut('slow', function () {
				$(this).html('');
				$(this).show();
			});
		});
	});
	$('div.mbsb_collapsible_heading').on('click', 'a.heading_pointer', function (e) {
		var button = $(this);
		var to_collapse = $(this).parents('div.mbsb_collapsible_heading').next();
		if (to_collapse.is(':hidden')) {
			mbsbSetCookie('sermon_browser_section_'+to_collapse.attr('class'), 'show', 365);
			to_collapse.slideDown('slow', function () {
				button.html('&#9660;');
			});
		} else {
			mbsbSetCookie('sermon_browser_section_'+to_collapse.attr('class'), 'hide', 365);
			to_collapse.slideUp('slow', function () {
				button.html('&#9654;')
			});
		}
		e.preventDefault();
	});
	$('div.sermon_sermon').on('click', 'a.read_more', function (e) {
		var section_type = $(this).attr('id').substr(10);
		var data = {
			action: 'mbsb_get_'+section_type+'_details',
			post_id: mbsb_sermon_id
		};
		$.post(mbsb_ajaxurl, data, function(response) {
			$('div.'+section_type+'_'+section_type).fadeOut('slow', function() {
				$(this).html(response);
			}).fadeIn('slow');
		});
		e.preventDefault();
	});

});
<?php
}
die();
?>