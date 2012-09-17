<?php
/**
* Class that handles media attachments
* 
* @package SermonBrowser
* @subpackage media_attachments
* @author Mark Barnes
*/
class mbsb_media_attachment {
	
	private $type, $meta_id, $data;

	/**
	* Initiates the object and populates its properties
	* 
	* @param integer - the meta_id
	* @return mbsb_media_attachment
	*/
	public function __construct ($meta_id) {
		$data = get_metadata_by_mid('post', $meta_id);
		$data = $data->meta_value;
		$this->type = $data ['type'];
		unset ($data['type']);
		if ($this->type == 'library') {
			$this->data = get_post ($data['post_id']);
			if (!$this->data)
				delete_metadata_by_mid ('post', $meta_id);
		} else
			$this->data = $data;
		$this->meta_id = $meta_id;
	}
	
	/**
	* Returns the type of attachment ('library', 'url' or 'embed')
	* 
	*/
	public function get_type() {
		return $this->type;
	}
	
	/**
	* Returns the id of the attachment
	* 
	*/
	public function get_id() {
		return $this->meta_id;
	}
	
	/**
	* Returns JSON encoded data, ready for javascript to insert into the media table when editing a sermon
	* 
	* @param boolean $success - true if this is a success message, false if it is a failure message
	* @param string $message - the message
	* @return string - the JSON encoded result
	*/
	public function get_json_attachment_row($success = true, $message = '') {
		if ($success)
			return json_encode(array('result' => 'success', 'code' => $this->get_admin_attachment_row ('mbsb_hide'), 'row_id' => $this->meta_id));
		else
			return json_encode(array('result' => 'failure', 'code' => mbsb_do_media_row_message ($message), 'row_id' => 'error_'.time()));
	}
	
	/**
	* Returns a row, ready to be inserted in a table displaying a list of media items
	* 
	* @param string $class - CSS class(es) to be added to the row
	* @return string
	*/
	public function get_admin_attachment_row ($class = '') {
		$function_name = "add_admin_{$this->type}_attachment_row";
		return $this->{$function_name} ($class);
	}
	
	/**
	* Returns the embed code
	* 
	* @return boolean|string - False on failure, the embed code on success.
	*/
	public function get_embed_code () {
		if ($this->type != 'embed')
			return false;
		else
			return $this->data['code'];
	}
	
	/**
	* Returns the URL of the attachment file
	* 
	* @return boolean|string - False on failure, the URL on success
	*/
	public function get_url() {
		if ($this->type == 'url')
			return $this->data['url'];
		elseif ($this->type == 'library')
			return $this->data->guid;
		else
			return false;
	}
	
	/**
	* Returns a media player for the current attachment
	* 
	* If not media player is configured for this type of attachment, returns a HTML link to the attached file.
	* 
	* @return string
	*/
	public function get_media_player() {
		if ($this->type == 'embed')
			return $this->get_embed_code();
		else {
			$mime_type = substr($this->get_mime_type(), 0, 5);
			if ($mime_type == 'audio' || $mime_type == 'video')
				return do_shortcode (str_replace('%URL%', $this->get_url(), mbsb_get_option("{$mime_type}_shortcode")));
			else
				return $this->get_attachment_link();
		}
	}
	
	public function get_attachment_link() {
		if ($this->type == 'embed')
			return false;
		else
			return '<a href="'.$this->get_url().'">'.esc_html($this->get_title()).'</a>';
			
	}
	
	/**
	* Returns the id of the library attachment
	* 
	* @return boolean|integer - False on failure, the post_id on success.
	*/
	public function get_library_id () {
		if ($this->type != 'library')
			return false;
		else
			return $this->data->ID;
	}
	
	/**
	* Returns an appropriate title for the attachment
	* 
	* Returns the attachment's post_title for library items, or uses the URL or embed code to calculate an appropriate title.
	* 
	* @return string
	*/
	public function get_title () {
		if ($this->type == 'library')
			return $this->data->post_title;
		elseif ($this->type == 'url') {
			$title = rtrim($this->data['url'], '/');
			$title = substr($title, strrpos($title, '/')+1);
			return mbsb_shorten_string($title, 30);
		} elseif ($this->type == 'embed') {
			$parse = new DOMDocument('4.0', 'utf-8');
			@$parse->loadHTML ($this->get_embed_code());
			$elements = array('iframe' => 'src', 'embed' => 'src', 'params' => 'value');
			foreach ($elements as $element => $attribute) {
				$found_elements = $parse->getElementsByTagName ($element);
				foreach ($found_elements as $f) {
					if (filter_var($f->getAttribute($attribute), FILTER_VALIDATE_URL)) {
						$url = $f->getAttribute($attribute);
						break;
					}
				}
				if (isset($url))
					break;
			}
			if (!isset($url))
				return __('Embed code', MBSB);
			$url = parse_url($url);
			$url['host'] = strtolower ($url['host']);
			if (substr($url['host'], 0, 4) == 'www.')
				$url['host'] = substr ($url['host'], 4);
			if (substr($url['host'], -4) == '.com') {
				$a = substr ($url['host'], 0, -4);
				if (strpos($a, '.') === false)
					$url['host'] = $a;
			}
			return __('Embed code', MBSB).' ('.$url['host'].')';
		}
	}
	
	/**
	* Returns a shortened form of the URL attachment for use when space is tight
	* 
	* @return string
	*/
	public function get_short_url () {
		if ($this->type == 'url') {
			$address = substr($this->data['url'], strpos($this->data['url'], '//')+2);
			$short_address = substr($address, 0, strpos($address, '/')+1).'…/'.basename($this->data['url']);
			return (strlen($short_address) > strlen($address)) ? $address : $short_address;
		} else
			return false;
	}
	
	/**
	* Returns the mime type of the attachment
	*/
	public function get_mime_type() {
		if ($this->type == 'url')
			return $this->data['mime_type'];
		elseif ($this->type == 'library')
			return $this->data->post_mime_type;
		elseif ($this->type == 'embed')
			return __('embed', MBSB);
	}
	
	/**
	* Returns a friendly name for the mime type of the attachment
	*/
	public function get_friendly_mime_type() {
		$mime_type = $this->get_mime_type();
		if ($mime_type == 'application/pdf')
			return __('PDF', MBSB);
		elseif ($mime_type == 'text/html')
			return __('link', MBSB);
		$sub = substr($mime_type, 0, 6);
		if ($sub == 'audio/')
			return __('audio', MBSB);
		elseif ($sub == 'image/')
			return __('image', MBSB);
		else
			return $mime_type;
	}

	/**
	* Returns a library attachment row, ready to be inserted in a table displaying a list of media items
	* 
	* @param string $class - CSS class(es) to be added to the row
	* @return string
	*/
	private function add_admin_library_attachment_row ($class = '') {
		$attachment = $this->data;
		$filename = get_attached_file ($attachment->ID);
		$insert = $class ? "  class=\"{$class}\"" : '';
		$actions = apply_filters ('mbsb_attachment_row_actions', '');
		$output  = "<tr><td id=\"row_{$this->meta_id}\"{$insert} style=\"width:100%\"><h3>".esc_html($attachment->post_title).'</h3>';
		if ($actions)
			$output .= "<span class=\"attachment_actions\" id=\"unattach_row_{$this->meta_id}\">{$actions}</span>";
		$output .= wp_get_attachment_image ($attachment->ID, array(46,60), true, array ('class' => 'thumbnail'));
		$output .= '<table class="mbsb_media_detail"><tr><th scope="row">'.__('Filename', MBSB).':</th><td>'.esc_html(basename($attachment->guid)).'</td></tr>';
		$output .= '<tr><th scope="row">'.__('File size', MBSB).':</th><td>'.mbsb_format_bytes(filesize($filename)).'</td></tr>';
		$output .= '<tr><th scope="row">'.__('Upload date', MBSB).':</th><td>'.mysql2date (get_option('date_format'), $attachment->post_date).'</td></tr></table>';
		$output .= '</td></tr>';
		return $output;
	}

	/**
	* Returns a URL attachment row, ready to be inserted in a table displaying a list of media items
	* 
	* @param string $class - CSS class(es) to be added to the row
	* @return string
	*/
	private function add_admin_url_attachment_row ($class = '') {
		$url_array = $this->data;
		$insert = $class ? "  class=\"{$class}\"" : '';
		$actions = apply_filters ('mbsb_attachment_row_actions', '');
		$short_address = $this->get_short_url();
		$title = $this->get_title();
		$output  = "<tr><td id=\"row_{$this->meta_id}\"{$insert} style=\"width:100%\"><h3>".esc_html($title).'</h3>';
		if ($actions)
			$output .= "<span class=\"attachment_actions\" id=\"unattach_row_{$this->meta_id}\">{$actions}</span>";
		$output .= "<img class=\"attachment-46x60 thumbnail\" width=\"46\" height=\"60\" alt=\"".esc_html($title).'" title="'.esc_html($title).'" src="'.wp_mime_type_icon ($this->get_mime_type()).'">';
		$output .= '<table class="mbsb_media_detail"><tr><th scope="row">'.__('URL', MBSB).':</th><td><span title="'.esc_html($url_array['url']).'">'.esc_html($short_address).'</span></td></tr>';
		if ($url_array['size'] && $this->get_mime_type() != 'text/html')
			$output .= '<tr><th scope="row">'.__('File size', MBSB).':</th><td>'.mbsb_format_bytes($url_array['size']).'</td></tr>';
		$output .= '<tr><th scope="row">'.__('Attachment date', MBSB).':</th><td>'.mysql2date (get_option('date_format'), $url_array['date_time']).'</td></tr></table>';
		$output .= '</td></tr>';
		return $output;
	}

	/**
	* Returns an embed attachment row, ready to be inserted in a table displaying a list of media items
	* 
	* @param boolean $hide - hides the row using CSS classes
	* @return string
	*/
	private function add_admin_embed_attachment_row ($class = '') {
		$embed_array = $this->data;
		$insert = $class ? "  class=\"{$class}\"" : '';
		$actions = apply_filters ('mbsb_attachment_row_actions', '');
		$title = $this->get_title();
		$output  = "<tr><td id=\"row_{$this->meta_id}\"{$insert} style=\"width:100%\"><h3>".esc_html($title).'</h3>';
		if ($actions)
			$output .= "<span class=\"attachment_actions\" id=\"unattach_row_{$this->meta_id}\">{$actions}</span>";
		$output .= "<img class=\"attachment-46x60 thumbnail\" width=\"46\" height=\"60\" alt=\"".esc_html($title).'" title="'.esc_html($title).'" src="'.wp_mime_type_icon ('interactive').'">';
		$output .= '<table class="mbsb_media_detail"><tr><th scope="row">'.__('Code', MBSB).':</th><td>'.esc_html($embed_array['code']).'</td></tr>';
		$output .= '<tr><th scope="row">'.__('Attachment date', MBSB).':</th><td>'.mysql2date (get_option('date_format'), $embed_array['date_time']).'</td></tr></table>';
		$output .= '</td></tr>';
		return $output;
	}
}
?>