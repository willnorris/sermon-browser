<?php
/**
* classes/sermon.php
* 
* Contains the mbsb_sermon class
* 
* @author Mark Barnes <mark@sermonbrowser.com>
* @package SermonBrowser
* @subpackage Sermon
*/

/**
* Class that stores and processes the sermon custom post type
* 
* @package SermonBrowser
* @subpackage Sermon
* @author Mark Barnes <mark@sermonbrowser.com>
*/
class mbsb_sermon extends mbsb_spss_template {
	
	/**
	* True if the object contains a sermon, false otherwise
	* 
	* @var boolean
	*/
	public $present;

	/**
	* Media attachments
	* 
	* @var mbsb_media_attachments
	*/
	public $attachments;
	
	/**
	* Initiates the object and populates its properties
	* 
	* @param integer $post_id
	* @return mbsb_sermon
	*/
	public function __construct ($post_id = null) {
		if ($post_id === null)
			$post_id == get_the_ID();
		$post = get_post ($post_id);
		$this->populate_initial_properties($post);
		if ($this->present) {
			$this->timestamp = strtotime($post->post_date);
			$this->date = date ('Y-m-d', $this->timestamp);
			$this->time = date ('H:i', $this->timestamp);
			$this->override_time = $this->get_misc_meta ('override_time');
			$this->preacher = new mbsb_preacher (get_post_meta ($this->id, 'preacher', true));
			$this->service = new mbsb_service (get_post_meta ($this->id, 'service', true));
			$this->series = new mbsb_series (get_post_meta ($this->id, 'series', true));
			$this->passages = new mbsb_passages(get_post_meta ($this->id, 'passage_start'), get_post_meta ($this->id, 'passage_end'));
			$this->attachments = new mbsb_media_attachments($this->id);
		}
	}
	
	/**
	* Returns a formatted string of the passages used for this sermon, ready for HTML output
	* 
	* Various HTML additions can specified via the $link_type paramenter. Accepted values of link_type are:
	* 	'admin_link'
	* 
	* @param $link_type
	* @return string
	*/
	public function get_formatted_passages($link_type = '') {
		if (is_object($this->passages) and is_a($this->passages, 'mbsb_passages'))
			if ($link_type == '')
				return $this->passages->get_formatted();
			elseif ($link_type = 'admin_link') {
				return $this->passages->get_admin_link();
			}
		else
			return '';
	}
	
	/**
	* Returns the sermon's description
	* 
	* @param boolean $raw - if true returns the description as stored, if false filters it through the_content
	*/
	public function get_description($raw = false) {
		if ($raw)
			return $this->description;
		else {
			if ($content_filter = has_filter('the_content', 'mbsb_provide_content'))
				remove_filter ('the_content', 'mbsb_provide_content', $content_filter);
			$description = apply_filters ('the_content', $this->description);
			if ($content_filter)
				add_filter ('the_content', 'mbsb_provide_content', $content_filter);
			return $description;
		}
	}
	
	/**
	* Returns the entire frontend output for the sermon
	* 
	* @return string
	*/
	public function get_frontend_output() {
		$sections = mbsb_get_option('frontend_sermon_sections');
		$output = '';
		foreach ($sections as $section)
			$output .= $this->get_frontend_section ($section);
		return $this->do_div($output, 'sermon');
	}
	
	/**
	* Returns a single frontend section
	* 
	* @param string $section - the section type (i.e. 'media', 'preacher', 'series', 'passages')
	*/
	private function get_frontend_section ($section) {
		$output = '';
		if ($section == 'main')
			$output = $this->get_main_output();
		elseif ($section == 'media' && $this->attachments->get_attachments()) {
			if (!mbsb_get_option('hide_media_heading'))
				$output .= $this->do_heading (__('Media', MBSB).':', 'media_attachments');
			$output .= $this->do_div ($this->attachments->get_frontend_list(), 'media_list');
		}elseif ($section == 'preacher' && $this->preacher->present) {
			$output = $this->do_heading (__('Preacher', MBSB).': <a href="'.$this->preacher->get_url().'">'.esc_html($this->preacher->get_name()).'</a>', 'preacher_name');
			$output .= $this->preacher->get_output(mbsb_get_option('excerpt_length'));
		}elseif ($section == 'series' && $this->series->present) {
			$output = $this->do_heading (__('Series', MBSB).': <a href="'.$this->series->get_url().'">'.esc_html($this->series->get_name()).'</a>', 'series_name');
			$output .= $this->series->get_output(mbsb_get_option('excerpt_length'));
		}elseif ($section == 'passages' && $this->passages->present) {
			$output = $this->do_heading (__('Bible Passages', MBSB).': '.$this->get_formatted_passages(), 'passages_title');
			$output .= $this->passages->get_output();
		}
		return $output;
	}
	
	/**
	* Returns the HTML of the name of the current sermon, linked to its frontend page
	* 
	* @return string
	*/
	public function get_linked_name() {
		$passages = $this->get_formatted_passages();
		if ($passages)
			$detail = esc_html(sprintf(__('%1s on %2s'), $this->preacher->get_name(), $passages));
		else
			$detail = $this->preacher->get_name();
		return '<a title="'.$detail.'" href="'.$this->get_url().'">'.esc_html($this->name).'</a>';
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
		$new_passages = new mbsb_passages($passages_raw);
		$type = array ('start', 'end');
		foreach ($type as $t)
			delete_post_meta($this->id, "passage_{$t}");
		if ($new_passages->get_formatted()) {
			$passage_objects = $new_passages->get_passage_objects();
			foreach ($passage_objects as $index => $p)
				foreach ($type as $t)
					add_post_meta ($this->id, "passage_{$t}", $this->passage_array_to_metadata_string($p->$t, $index));
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
		if ($column == 'preacher' && $this->preacher->present)
			return '<a href="'.get_edit_post_link($this->preacher->id).'">'.esc_html($this->preacher->get_name()).'</a>';
		elseif ($column == 'service' && $this->service->present)
			return '<a href="'.get_edit_post_link($this->service->id).'">'.esc_html($this->service->get_name()).'</a>';
		elseif ($column == 'series' && $this->series->present)
			return '<a href="'.get_edit_post_link($this->series->id).'">'.esc_html($this->series->get_name()).'</a>';
		elseif ($column == 'passages')
			return $this->get_formatted_passages('admin_link');
		elseif ($column == 'media')
			return $this->attachments->get_admin_cell();
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
	
	/**
	* Returns the output of the 'main' section of the frontend
	* 
	* @return string
	*/
	private function get_main_output() {
		$output = '';
		if (mbsb_get_option('sermon_image_pos') != 'none' && $this->has_thumbnail())
			$output .= $this->do_div ($this->get_thumbnail(array ('class' => mbsb_get_option('sermon_image_pos'))), 'sermon_image');
		$output .= $this->do_div ($this->get_description(), 'description');
		return $this->do_div ($output, 'main');
	}
	
	/**
	* Returns true if a post thumbnail can be derived for this sermons
	* 
	* Attempts to use the featured image of the sermon, series, preacher and service, in that order.
	* 
	* @return boolean
	* @todo Look for image attachments
	*/
	private function has_thumbnail() {
		return (has_post_thumbnail() || ($this->series->present && has_post_thumbnail($this->series->id)) || ($this->preacher->present && has_post_thumbnail($this->preacher->id)) || ($this->service->present && has_post_thumbnail($this->service->id)));
	}
	
	/**
	* Returns the HTML code for the image appropriate to this sermon
	* 
	* Attempts to use the featured image of the sermon, series, preacher and service, in that order.
	* 
	* @uses get_the_post_thumbnail()
	* @param string|array $attr Optional. Query string or array of attributes.
 	*/
	public function get_thumbnail($attr) {
		if (has_post_thumbnail($this->id))
			return get_the_post_thumbnail($this->id, 'mbsb_sermon', $attr);
		elseif (has_post_thumbnail($this->series->id))
			return get_the_post_thumbnail($this->series->id, 'mbsb_sermon', $attr);
		elseif (has_post_thumbnail($this->preacher->id))
			return get_the_post_thumbnail($this->preacher->id, 'mbsb_sermon', $attr);
		elseif (has_post_thumbnail($this->service->id))
			return get_the_post_thumbnail($this->service->id, 'mbsb_sermon', $attr);
	}
}
?>