<?php
/**
* Class that stores and processes the service custom post type
* 
* @package preacher
*/
class mbsb_service {
	
	private $time;
	
	/**
	* Initiates the object and populates its properties
	* 
	* @param integer $post_id
	* @return mbsb_service
	*/
	public function __construct ($post_id) {
		$post = get_post ($post_id);
		$properties = array ('ID' => 'id', 'post_status' => 'status', 'post_content' => 'description', 'post_name' => 'slug', 'post_title' => 'name');
		foreach ($properties as $k => $v)
			if (empty($post) || $post->post_type != 'mbsb_service')
				$this->$v = null;
			else
				$this->$v = $post->$k;
		$this->time = (int)get_post_meta ($post_id, 'mbsb_service_time', true);
	}
	
	public function display_time($format = 'H:i') {
		return date ($format, $this->time);
	}
	
	public function set_time($time) {
		if (($seconds = strtotime ('1 January 1970 '.trim($time).' UTC')) && update_post_meta ($this->id, 'mbsb_service_time', $seconds)) {
			$this->time = $seconds;
			return true;
		}
		return false;
	}
}
?>