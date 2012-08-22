<?php
class mbsb_service {

	public function __construct ($post_id) {
		$post = get_post ($post_id);
		if (empty($post) || $post->post_type != 'mbsb_services')
			return new WP_Error('NO_SERVICE_WITH_THAT_ID');
		$properties = array ('ID' => 'id', 'post_status' => 'status', 'post_content' => 'description', 'post_name' => 'slug', 'post_title' => 'name');
		foreach ($properties as $k => $v)
			$this->$v = $post->$k;
	}

}
?>