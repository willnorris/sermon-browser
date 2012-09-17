<?php
/**
* Class that stores and processes the preacher custom post type
* 
* @package SermonBrowser
* @subpackage preacher
* @author Mark Barnes
*/
class mbsb_preacher extends mbsb_pss_template {

	/**
	* Initiates the object and populates its properties
	* 
	* @param integer $post_id
	* @return mbsb_preacher
	*/
	public function __construct ($post_id) {
		$post = get_post ($post_id);
		$properties = array ('ID' => 'id', 'post_status' => 'status', 'post_content' => 'description', 'post_name' => 'slug', 'post_title' => 'name', 'post_excerpt' => 'excerpt');
		foreach ($properties as $k => $v)
			if (empty($post) || $post->post_type != 'mbsb_preacher')
				$this->$v = null;
			else
				$this->$v = $post->$k;
		$this->type = substr($post->post_type, 5);
	}
		
	/**
	* Outputs the preacher details
	* 
	* @param integer $excerpt_length - the maximum number of words to use in the description (0 = unlimited)
	* @return string
	*/
	public function get_output($excerpt_length = 0) {
		global $post;
		$showing_preacher_page = (isset($post->ID) && ($post->ID == $this->id));
		$output = '';
		if (mbsb_get_option('preacher_image') != 'none' && has_post_thumbnail($this->id))
			$output .= $this->do_div (get_the_post_thumbnail($this->id, 'mbsb_preacher', array ('class' => mbsb_get_option('preacher_image'))), 'preacher_image');
		$description = ($excerpt_length == 0) ? $this->get_description() : '<p>'.$this->get_excerpt($excerpt_length).'</p>';
		$output .= $this->do_div ($description, 'description');
		if (mbsb_get_option('show_statistics_on_sermon_page')) {
			$output .= '<p class="preacher_statistics"><strong>'.__('See more', MBSB).'</strong>: ';
			$stats = sprintf(__('%1s sermons in %2s series ', MBSB), $this->get_sermon_count(), $this->get_series_count());
			if ($showing_preacher_page)
				$output .= $stats;
			else
				$output .= '<a href="'.$this->get_url().'">'.$stats.'</a>';
			$output .= '</p>';
			
		}
		return $this->do_div ($output, 'preacher');
	}
}
?>