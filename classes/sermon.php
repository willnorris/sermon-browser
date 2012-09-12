<?php
/**
* Class that stores and processes the sermon custom post type
* 
* @package preacher
*/
class mbsb_sermon {
	
	/**
	* Initiates the object and populates its properties
	* 
	* @param integer $post_id
	* @return mbsb_sermon
	*/
	public function __construct ($post_id) {
		$post = get_post ($post_id);
		if (empty($post) || $post->post_type != 'mbsb_sermons')
			return new WP_Error('NO_SERMON_WITH_THAT_ID');
		$properties = array ('ID' => 'id', 'comment_count' => 'comment_count', 'comment_status' => 'comment_status', 'ping_status' => 'ping_status', 'post_status' => 'status', 'post_content' => 'description', 'post_name' => 'slug', 'post_title' => 'title');
		foreach ($properties as $k => $v)
			$this->$v = $post->$k;
		$this->timestamp = strtotime($post->post_date);
		$this->date = date ('Y-m-d', $this->timestamp);
		$this->time = date ('H:i', $this->timestamp);
		$this->preacher = new mbsb_preacher (get_post_meta ($this->id, 'preacher', true));
		$this->service = new mbsb_service (get_post_meta ($this->id, 'service', true));
		$this->series = new mbsb_series (get_post_meta ($this->id, 'series', true));
		$this->override_time = $this->get_misc_meta ('override_time');
		$this->passages = get_post_meta ($this->id, 'passages_object', true);
	}
	
	/**
	* Returns a formatted string of the passages used for this sermon, ready for HTML output
	* 
	* Various HTML additions can specified via the $link_type paramenter. Accepted values of link_type are:
	* 	'admin_link'
	* 
	* @return string
	*/
	public function get_formatted_passages($link_type = '') {
		if (is_object($this->passages) and is_a($this->passages, 'mbsb_passage'))
			if ($link_type == '')
				return $this->passages->get_formatted();
			elseif ($link_type = 'admin_link') {
				return $this->passages->get_admin_link();
			}
		else
			return '';
	}
	
	/**
	* Returns an array of media attachments (i.e. post objects) of the current sermon
	* 
	* @param bool $most_recent_first
	* @return mixed - false on failure, array on success
	*/
	public function get_attachments($most_recent_first = false) {
		global $wpdb;
		$dir = $most_recent_first ? 'DESC' : 'ASC';
		$meta_ids = $wpdb->get_col($wpdb->prepare("SELECT meta_id FROM {$wpdb->prefix}postmeta WHERE post_id=%s AND meta_key='attachments' ORDER BY meta_id {$dir}", $this->id));
		if ($meta_ids) {
			$attachments = array();
			foreach ($meta_ids as $meta_id)
				$attachments[] = new mbsb_media_attachment($meta_id);
			return $attachments;
		} else
			return false;
	}
	
	/**
	* Returns a simple list of the media items (titles separated by <br/> tags)
	* 
	* @param bool $admin - true if edit links to be added
	* @return string
	*/
	public function get_simple_media_list($admin = false) {
		$attachments = $this->get_attachments();
		if ($attachments) {
			$output = array();
			foreach ($attachments as $attachment)
				if ($admin && ($library_id = $attachment->get_library_id()))
					$output[] = '<strong>'.(current_user_can ('edit_post', $library_id) ? ("<a href=\"".get_edit_post_link ($library_id)."\">{$attachment->get_title()}</a>") : $attachment->get_title()).'</strong> ('.$attachment->get_friendly_mime_type().')';
				elseif ($attachment->get_type() != 'embed')
					$output[] = $attachment->get_title().' ('.$attachment->get_friendly_mime_type().')';
				else
					$output[] = $attachment->get_title();
			return implode('</br>', $output);
		}
		else
			return __('No media attached', MBSB);
	}
	
	/**
	* Adds an attachment to a sermon
	* 
	* @param integer $attachment_id - the post ID of the attachment
	* @return mixed False for failure. Null if the file is already attached. The media_attachment object if successful. 
	*/
	public function add_library_attachment ($attachment_id) {
		$existing_meta = get_post_meta ($this->id, 'attachments');
		foreach ($existing_meta as $em)
			if ($em['type'] == 'library' && $em['post_id'] == $attachment_id)
				return null;
		$metadata = array ('type' => 'library', 'post_id' => $attachment_id);
		if ($meta_id = add_post_meta ($this->id, 'attachments', $metadata))
			return new mbsb_media_attachment($meta_id);
		else
			return false;
	}
	
	/**
	* Adds a URL attachment to a sermon
	*  
	* @param string $url
	* @return mixed False for failure. Null if the URL is not valid. The media_attachment object if successful.
	*/
	public function add_url_attachment ($url) {
		$headers = wp_remote_head ($url, array ('redirection' => 5));
		if (is_wp_error($headers) || $headers['response']['code'] != 200)
			return null;
		else {
			$content_type = isset($headers['headers']['content-type']) ? $headers['headers']['content-type'] : '';
			if (($a = strpos($content_type, ';')) !== FALSE)
				$content_type = substr($content_type, 0, $a);
			$metadata = array ('type' => 'url', 'url' => $url, 'size' => (isset($headers['headers']['content-length']) ? $headers['headers']['content-length'] : 0), 'mime_type' => $content_type, 'date_time' => time());
			if ($meta_id = add_post_meta ($this->id, 'attachments', $metadata))
				return new mbsb_media_attachment($meta_id);
			else
				return false;
		}
	}
	
	/**
	* Adds an embed to a sermon
	*  
	* @param string $embed
	* @return mixed Null for invalid code. False for failure. The media_attachment object if successful. 
	*/
	public function add_embed_attachment ($embed) {
		global $allowedposttags;
		$old_allowedposttags = $allowedposttags;
		$allowedposttags['object'] = array('height' => array(), 'width' => array(), 'classid' => array(), 'codebase' => array());
		$allowedposttags['param'] = array('name' => array(), 'value' => array());
		$allowedposttags['embed'] = array('src' => array(), 'type' => array(), 'allowfullscreen' => array(), 'allowscriptaccess' => array(), 'height' => array(), 'width' => array(), 'allowfullscreen' => array());
		$allowedposttags['iframe'] = array('height' => array(), 'width' => array(), 'src' => array(), 'frameborder' => array(), 'allowfullscreen' => array());
		$embed = wp_kses_post($embed);
		$allowedposttags = $old_allowedposttags;
		if (trim($embed) == '')
			return null;
		$metadata = array ('type' => 'embed', 'code' => $embed, 'date_time' => time());
		if ($meta_id = add_post_meta ($this->id, 'attachments', $metadata))
			return new mbsb_media_attachment($meta_id);
		else
			return false;
	}

	/**
	* Updates the time override metadata for this sermon
	* 
	* @param boolean $override
	* @return mixed - meta_id on success, false on failure
	*/
	public function update_override_time ($override) {
		return $this->update_misc_meta ('override_time', $override);
	}
	
	/**
	* Updates the preacher for this sermon
	* 
	* @param integer $preacher_id
	* @return mixed - meta_id on success, false on failure
	*/
	public function update_preacher ($preacher_id) {
		if ($result = update_post_meta ($this->id, 'preacher', $preacher_id)) {
			$preacher = new mbsb_preacher ($preacher_id);
			$this->preacher_id = $preacher_id;
			$this->preacher_name = $preacher->name;
		}
		return $result;
	}
	
	/**
	* Updates the series for this sermon
	* 
	* @param integer $series_id
	* @return mixed - meta_id on success, false on failure
	*/
	public function update_series ($series_id) {
		if ($result = update_post_meta ($this->id, 'series', $series_id)) {
			$series = new mbsb_series ($series_id);
			$this->series_id = $series_id;
			$this->series_name = $series->name;
		}
		return $result;
	}
	
	/**
	* Updates the service for this sermon
	* 
	* @param integer $service_id
	* @return mixed - meta_id on success, false on failure
	*/
	public function update_service ($service_id) {
		if ($result = update_post_meta ($this->id, 'service', $service_id)) {
			$this->service = new mbsb_service ($service_id);
		}
		return $result;
	}
	
	/**
	* Updates the Bible passages for this sermon
	* 
	* @param string - an unparsed string of the Bible passages
	* @return mixed - true on success, WP_Error on failure
	*/
	public function update_passages ($passages_raw) {
		$new_passages = new mbsb_passage($passages_raw);
		if ($error = $new_passages->is_error())
			return $error;
		else {
			$existing_passages = get_post_meta ($this->id, 'passages_object', true);
			if ($existing_passages != $new_passages) {
				update_post_meta ($this->id, 'passages_object', $new_passages);
				$processed = $new_passages->get_processed();
				$type = array ('start', 'end');
				foreach ($type as $t)
					delete_post_meta($this->id, "passage_{$t}");
				foreach ($processed as $index => $p)
					if (!is_wp_error($p['error']))
						foreach ($type as $t)
							add_post_meta ($this->id, "passage_{$t}", $this->passage_array_to_metadata_string($p[$t], $index));
			}
			$this->passages = $new_passages;
			return true;
		}
	}
	
	/**
	* Outputs a single cell on the custom posts edit page
	* 
	* @param string $post_type
	* @param string $column
	* @return string
	*/
	public function edit_php_cell ($post_type, $column) {
		if (substr($post_type, 0, 5) != 'mbsb_')
			$post_type = 'mbsb_'.$post_type;
		if ($column == 'preacher')
			return '<a href="'.get_edit_post_link($this->preacher->id).'">'.esc_html($this->preacher->name).'</a>';
		elseif ($column == 'service')
			return '<a href="'.get_edit_post_link($this->service->id).'">'.esc_html($this->service->name).'</a>';
		elseif ($column == 'series')
			return '<a href="'.get_edit_post_link($this->series->id).'">'.esc_html($this->series->name).'</a>';
		elseif ($column == 'passages')
			return $this->get_formatted_passages('admin_link');
		elseif ($column == 'media')
			return $this->get_simple_media_list(true);
		else
			return 'Unknown type';
	}
	
	/**
	* Converts a passage array into a string suitable for storing as metadata
	* 
	* @param array $passage - an associative array with the keys 'book', 'chapter' and 'verse'
	* @param integer $index - the index of this passage (when a sermon as multiple passages, each must have a unique index)
	* @return string
	*/
	private function passage_array_to_metadata_string ($passage, $index = 0) {
		$values = $this->passage_metadata_structure();
		$return = '';
		foreach ($values as $v => $length)
			$return .= str_pad($passage[$v], $length, '0', STR_PAD_LEFT);
		$return .= '.'.str_pad ($index, 4, '0', STR_PAD_LEFT);
		return $return;
	}
		
	/** 
	* Converts a metadata passage string into a passage array
	* 
	* @param string $raw_passage - the string stored in the metadata
	* @return array - an associative array with the keys 'passage' (itself an associative array) and 'index'
	*/
	private function passage_metadata_string_to_array ($raw_passage) {
		$passage = intval($raw_passage);
		$values = $this->passage_metadata_structure();
		$start = 0;
		foreach ($values as $v => $length) {
			$return[$v] = (int)substr($passage, $start, $length);
			$start = $start + $length;
		}
		return array ('passage' => $return, 'index' => (int)($raw_passage - $passage)*10000);
	}
	
	/**
	* Returns the structure and field length of the passage array
	* 
	* @return array
	*/
	private function passage_metadata_structure () {
		return array ('book' => 2, 'chapter' => 3, 'verse' => 3); //The values in the array specify the maximum number of digits for each value
	}

	/**
	* Updates the 'misc' metadata values
	* 
	* To reduce the number of rows required in the wp_postmeta table, all data which will not need to be queried is stored in the 'misc' key
	* 
	* @param string $meta_key - the 'meta key' name
	* @param mixed $value
	* @return mixed - meta_id on success, false on failure
	*/
	private function update_misc_meta ($meta_key, $value) {
		$misc = get_post_meta ('misc', true);
		$misc[$meta_key] = $value;
		return update_post_meta ($this->id, 'misc', $misc);
	}

	/**
	* Returns the 'misc' metadata values
	* 
	* To reduce the number of rows required in the wp_postmeta table, all data which will not need to be queried is stored in the 'misc' key
	* 
	* @param string $meta_key - the 'meta key' name
	* @return mixed - the requested data on success, null on failure
	*/
	private function get_misc_meta ($meta_key) {
		$misc = get_post_meta ($this->id, 'misc', true);
		if (isset ($misc[$meta_key]))
			return $misc[$meta_key];
		else
			return null;
	}
}
?>