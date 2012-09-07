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
		$this->preacher_id = get_post_meta ($this->id, 'preacher', true);
		$preacher = new mbsb_preacher ($this->preacher_id);
		$this->preacher_name = $preacher->name;
		$this->service_id = get_post_meta ($this->id, 'service', true);
		$service = new mbsb_service ($this->service_id);
		$this->service_name = $service->name;
		$this->series_id = get_post_meta ($this->id, 'series', true);
		$series = new mbsb_series ($this->series_id);
		$this->series_name = $series->name;
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
		$attachments = get_post_meta ($this->id, 'attachments');
		if ($attachments) {
			foreach ($attachments as &$attachment)
				if ($attachment['type'] == 'library')
					$attachment ['data'] = get_post ($attachment['post_id']);
			if ($most_recent_first)
				return array_reverse($attachments);
			else
				return $attachments;
		} else
			return false;
	}
	
	/**
	* Adds an attachment to a sermon
	* 
	* @param integer $attachment_id - the post ID of the attachment
	* @return mixed False for failure. Null if the file is already attached. True for success. 
	*/
	public function add_library_attachment ($attachment_id) {
		$existing_meta = get_post_meta ($this->id, 'attachments');
		foreach ($existing_meta as $em)
			if ($em['type'] == 'library' && $em['post_id'] == $attachment_id)
				return null;
		return add_post_meta ($this->id, 'attachments', array ('type' => 'library', 'post_id' => $attachment_id));
	}
	
	/**
	* Adds a URL attachment to a sermon
	*  
	* @param string $url
	* @return mixed False for failure. Null if the URL is not valid. The attachment data on success. 
	*/
	public function add_url_attachment ($url) {
		$headers = wp_remote_head ($url, array ('redirection' => 5));
		if ($headers['response']['code'] != 200)
			return null;
		else {
			$content_type = isset($headers['headers']['content-type']) ? $headers['headers']['content-type'] : '';
			if (($a = strpos($content_type, ';')) !== FALSE)
				$content_type = substr($content_type, 0, $a);
			$data = array ('type' => 'url', 'url' => $url, 'size' => (isset($headers['headers']['content-length']) ? $headers['headers']['content-length'] : 0), 'mime_type' => $content_type, 'date_time' => time());
			if (add_post_meta ($this->id, 'attachments', $data))
				return $data;
			else
				return false;
		}
	}
	
	/**
	* Adds an embed to a sermon
	*  
	* @param string $embed
	* @return mixed Null for invalid code. False for failure. The embed data on success. 
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
		$data = array ('type' => 'embed', 'code' => $embed, 'date_time' => time());
		if (add_post_meta ($this->id, 'attachments', $data))
			return $data;
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
			$service = new mbsb_service ($service_id);
			$this->service_id = $service_id;
			$this->service_name = $service->name;
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
	
	public function admin_filter_link ($post_type, $type) {
		if (substr($post_type, 0, 5) != 'mbsb_')
			$post_type = 'mbsb_'.$post_type;
		if ($type == 'preacher')
			return '<a href="'.admin_url("edit.php?post_type={$post_type}&{$type}={$this->preacher_id}").'">'.esc_html($this->preacher_name).'</a>';
		elseif ($type == 'service')
			return '<a href="'.admin_url("edit.php?post_type={$post_type}&{$type}={$this->service_id}").'">'.esc_html($this->service_name).'</a>';
		elseif ($type == 'series')
			return '<a href="'.admin_url("edit.php?post_type={$post_type}&{$type}={$this->series_id}").'">'.esc_html($this->series_name).'</a>';
		elseif ($type == 'passages')
			return $this->get_formatted_passages('admin_link');
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