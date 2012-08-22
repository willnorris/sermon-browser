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
		if (is_wp_error($preacher))
			$this->preacher_name = null;
		else
			$this->preacher_name = $preacher->name;
		$this->service_id = get_post_meta ($this->id, 'service', true);
		$service = new mbsb_service ($this->service_id);
		if (is_wp_error($service))
			$this->service_name = null;
		else
			$this->service_name = $service->name;
		$this->series_id = get_post_meta ($this->id, 'series', true);
		$series = new mbsb_series ($this->series_id);
		if (is_wp_error($series))
			$this->series_name = null;
		else
			$this->series_name = $series->name;
		$this->override_time = $this->get_misc_meta ('override_time');
	}
	
	public function update_override_time ($override) {
		return $this->update_misc_meta ('override_time', $override);
	}
	
	public function update_preacher ($preacher) {
		return update_post_meta ($this->id, 'preacher', $preacher);
	}
	
	public function update_series ($series) {
		return update_post_meta ($this->id, 'series', $series);
	}
	
	public function update_service ($service) {
		return update_post_meta ($this->id, 'service', $service);
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