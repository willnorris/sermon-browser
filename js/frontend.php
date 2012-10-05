<?php
/**
* Include file called when requested on the plugins_loaded action
* 
* Outputs javascript for the frontend, then dies.
* It's a PHP file so that we can internationalise it, etc.
* 
* @package SermonBrowser
* @subpackage Javascript
* @author Mark Barnes <mark@sermonbrowser.com>
*/
header ('Cache-Control: max-age=31536000, public');
header ('Expires: '.gmdate('D, d M Y H:i:s \G\M\T', time()+31536000)); 
header ('Content-type: text/javascript; charset=utf-8');
$date = @filemtime(__FILE__);
if ($date)
	header ('Last-Modified: '.gmdate('D, d M Y H:i:s \G\M\T', $date));
?>
var mbsb_ajaxurl = '<?php echo admin_url( 'admin-ajax.php', 'relative' ); ?>';

// Sets a cookie
function mbsbSetCookie(cookieName, value, numDays) {
	var expiryDate = new Date();
	expiryDate.setDate(expiryDate.getDate() + numDays);
	var cookieValue = cookieName + "=" + escape(value) + ((numDays==null) ? "" : "; expires=" + expiryDate.toUTCString()) + "; path=<?php echo esc_js(COOKIEPATH) ?>";
	document.cookie = cookieValue;
}
//Gets a cookie
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
	// Hide hidden sections
	$('select.mbsb_hide').hide();
	hideableSections = new Array("sermon_media_list", "preacher_preacher", "series_series", "service_service", "passages_wrap");
	$.each (hideableSections, function (key, value) {
		var display = mbsbGetCookie('sermon_browser_section_'+value);
		if (display == 'hide') {
			$('div.'+value).hide();
			var a = $('div.'+value).prev().find('a.heading_pointer').html('&#9654;');
		}
	});
	//Listen for the Bible dropdown to be changed
	$('#bible_dropdown').change(function() {
		var version = $(this).val();
		mbsbSetCookie('sermon_browser_bible', version, 365);
		$('#passages_bible_loader').html('<img src="<?php echo admin_url('images/loading.gif');?>" alt="<?php _e('Requesting', MBSB);?>&hellip;"/><?php _e('Requesting', MBSB);?>&hellip;');
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
	// Listen for collapsible headings to be clicked
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
	//Listen for a 'read more' to be clicked
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
	//Listen for the 'filter by' menu to be changed
	$('#filter_filter_by_dropdown').change(function() {
		var filter = $(this).val();
		$('select.mbsb_hide').hide();
		$('#filter_'+filter+'_dropdown').show();
	});
	
	<?php do_action ('mbsb_frontend_jQuery'); ?>
});