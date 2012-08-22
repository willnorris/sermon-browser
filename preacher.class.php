<?php
class mbsb_preacher {

	public function __construct ($post_id) {
		$post = get_post ($post_id);
		if (empty($post) || $post->post_type != 'mbsb_preachers')
			return new WP_Error('NO_PREACHER_WITH_THAT_ID');
		$properties = array ('ID' => 'id', 'post_status' => 'status', 'post_content' => 'description', 'post_name' => 'slug', 'post_title' => 'name');
		foreach ($properties as $k => $v)
			$this->$v = $post->$k;
	}

}
?>