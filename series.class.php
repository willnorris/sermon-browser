<?php
class mbsb_series {

	public function __construct ($post_id) {
		$post = get_post ($post_id);
		$properties = array ('ID' => 'id', 'post_status' => 'status', 'post_content' => 'description', 'post_name' => 'slug', 'post_title' => 'name');
		foreach ($properties as $k => $v)
			if (empty($post) || $post->post_type != 'mbsb_series')
				$this->$v = null;
			else
				$this->$v = $post->$k;
	}

}
?>