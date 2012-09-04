<?php
class mbsb_sermon {
	
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
	
	public function update_override_time ($override) {
		return $this->update_misc_meta ('override_time', $override);
	}
	
	public function update_preacher ($preacher_id) {
		if ($result = update_post_meta ($this->id, 'preacher', $preacher_id)) {
			$preacher = new mbsb_preacher ($preacher_id);
			$this->preacher_id = $preacher_id;
			$this->preacher_name = $preacher->name;
		}
		return $result;
	}
	
	public function update_series ($series_id) {
		if ($result = update_post_meta ($this->id, 'series', $series_id)) {
			$series = new mbsb_series ($series_id);
			$this->series_id = $series_id;
			$this->series_name = $series->name;
		}
		return $result;
	}
	
	public function update_service ($service_id) {
		if ($result = update_post_meta ($this->id, 'service', $service_id)) {
			$service = new mbsb_service ($service_id);
			$this->service_id = $service_id;
			$this->service_name = $service->name;
		}
		return $result;
	}
	
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
		}
		$this->passages = $new_passages;
	}
	
	private function passage_array_to_metadata_string ($passage, $index = 0) {
		$values = $this->passage_metadata_structure();
		$return = '';
		foreach ($values as $v => $length)
			$return .= str_pad($passage[$v], $length, '0', STR_PAD_LEFT);
		$return .= '.'.str_pad ($index, 4, '0', STR_PAD_LEFT);
		return $return;
	}
		
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
	
	private function passage_metadata_structure () {
		return array ('book' => 2, 'chapter' => 3, 'verse' => 3); //The values in the array specify the maximum number of digits for each value
	}

	private function update_misc_meta ($meta_key, $value) {
		$misc = get_post_meta ('misc', true);
		$misc[$meta_key] = $value;
		return update_post_meta ($this->id, 'misc', $misc);
	}

	public function get_misc_meta ($meta_key) {
		$misc = get_post_meta ($this->id, 'misc', true);
		if (isset ($misc[$meta_key]))
			return $misc[$meta_key];
		else
			return null;
	}

}
?>