<?php
/**
* Class that handles media attachments
* 
* @package media_attachments
*/
class mbsb_media_attachment {
	
	private $type, $data;

	/**
	* Initiates the object and populates its properties
	* 
	* @param array The raw data stored in the metadata table
	* @return mbsb_media_attachment
	*/
	public function __construct ($data) {
		$this->type = $data ['type'];
		unset ($data['type']);
		if ($this->type == 'library')
			$this->data = get_post ($data['post_id']);
		else
			$this->data = $data;
	}

	/**
	* Returns a row, ready to be inserted in a table displaying a list of media items
	* 
	* @param boolean $hide - hides the row using CSS classes
	* @return string
	*/
	public function return_attachment_row ($hide=false) {
		$function_name = "add_{$this->type}_attachment_row";
		return $this->{$function_name} ($hide);
	}

	/**
	* Echoes a row into a table displaying a list of media items
	* 
	* @param boolean $hide - hides the row using CSS classes
	* @return string
	*/
	public function echo_attachment_row ($hide=false) {
		echo $this->return_attachment_row ($hide);
	}

	/**
	* Returns a library attachment row, ready to be inserted in a table displaying a list of media items
	* 
	* @param boolean $hide - hides the row using CSS classes
	* @return string
	*/
	private function add_library_attachment_row ($hide=false) {
		$attachment = $this->data;
		$filename = get_attached_file ($attachment->ID);
		$insert = $hide ? '  class="media_row_hide"' : '';
		$output  = '<tr'.$insert.'><th colspan="2"><strong>'.esc_html($attachment->post_title).'</strong></th></tr>';
		$output .= '<tr'.$insert.'><td width="46">'.wp_get_attachment_image ($attachment->ID, array(46,60), true).'</td>';
		$output .= '<td><table class="mbsb_media_detail"><tr><th scope="row">'.__('Filename', MBSB).':</th><td>'.esc_html(basename($attachment->guid)).'</td></tr>';
		$output .= '<tr><th scope="row">'.__('File size', MBSB).':</th><td>'.mbsb_format_bytes(filesize($filename)).'</td></tr>';
		$output .= '<tr><th scope="row">'.__('Upload date', MBSB).':</th><td>'.mysql2date (get_option('date_format'), $attachment->post_date).'</td></tr></table></td></tr>';
		return $output;
	}

	/**
	* Returns a URL attachment row, ready to be inserted in a table displaying a list of media items
	* 
	* @param boolean $hide - hides the row using CSS classes
	* @return string
	*/
	private function add_url_attachment_row ($hide=false) {
		$url_array = $this->data;
		$insert = $hide ? '  class="media_row_hide"' : '';
		$address = substr($url_array['url'], strpos($url_array['url'], '//')+2);
		$short_address = substr($address, 0, strpos($address, '/')+1).'…/'.basename($url_array['url']);
		if (strlen($short_address) > strlen($address)) {
			$short_address = $address;
			$insert2 = '';
		} else
			$insert2 = ' title="'.esc_html($url_array['url']).'"';
		$title = substr($url_array['url'], strrpos($url_array['url'], '/')+1);
		$output  = '<tr'.$insert.'><th colspan="2"><strong>'.esc_html($title).'</strong></th></tr>';
		$output .= '<tr'.$insert.'><td width="46"><img class="attachment-46x60" width="46" height="60" alt="'.esc_html($title).'" title="'.esc_html($title).'" src="'.wp_mime_type_icon ($url_array['mime_type']).'"></td>';
		$output .= '<td><table class="mbsb_media_detail"><tr><th scope="row">'.__('URL', MBSB).':</th><td><span'.$insert2.'>'.esc_html($short_address).'</span></td></tr>';
		if ($url_array['size'] && $url_array['mime_type'] != 'text/html')
			$output .= '<tr><th scope="row">'.__('File size', MBSB).':</th><td>'.mbsb_format_bytes($url_array['size']).'</td></tr>';
		$output .= '<tr><th scope="row">'.__('Attachment date', MBSB).':</th><td>'.mysql2date (get_option('date_format'), $url_array['date_time']).'</td></tr></table></td></tr>';
		return $output;
	}

	/**
	* Returns an embed attachment row, ready to be inserted in a table displaying a list of media items
	* 
	* @param boolean $hide - hides the row using CSS classes
	* @return string
	*/
	private function add_embed_attachment_row ($hide=false) {
		$embed_array = $this->data;
		$insert = $hide ? '  class="media_row_hide"' : '';
		$title = __('Embed code');
		$output  = '<tr'.$insert.'><th colspan="2"><strong>'.esc_html($title).'</strong></th></tr>';
		$output .= '<tr'.$insert.'><td width="46"><img class="attachment-46x60" width="46" height="60" alt="'.esc_html($title).'" title="'.esc_html($title).'" src="'.wp_mime_type_icon ('interactive').'"></td>';
		$output .= '<td><table class="mbsb_media_detail"><tr><th scope="row">'.__('Code', MBSB).':</th><td>'.esc_html($embed_array['code']).'</td></tr>';
		$output .= '<tr><th scope="row">'.__('Attachment date', MBSB).':</th><td>'.mysql2date (get_option('date_format'), $embed_array['date_time']).'</td></tr></table></td></tr>';
		return $output;
	}
}
?>