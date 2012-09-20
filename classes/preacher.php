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
	* True if the object contains a preacher, false otherwise
	* 
	* @var boolean
	*/
	public $present;

	/**
	* Initiates the object and populates its properties
	* 
	* @param integer $post_id
	* @return mbsb_preacher
	*/
	public function __construct ($post_id) {
		$post = get_post ($post_id);
		if ($post && $post_id !== false && $post->post_type == 'mbsb_preacher') {
			$properties = array ('ID' => 'id', 'post_status' => 'status', 'post_content' => 'description', 'post_name' => 'slug', 'post_title' => 'name', 'post_excerpt' => 'excerpt');
			foreach ($properties as $k => $v)
				if (empty($post) || $post->post_type != 'mbsb_preacher')
					$this->$v = null;
				else
					$this->$v = $post->$k;
			$this->type = substr($post->post_type, 5);
			$this->present = true;
		} else
			$this->present = false;
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
			$num_sermons = $this->get_sermon_count();
			if ($num_sermons > 1) {
				$output .= '<p class="preacher_statistics"><strong>'.__('See more', MBSB).'</strong>: ';
				$stats = sprintf(__('%1s sermons by %2s in %3s series ', MBSB), $num_sermons, esc_html($this->get_name()), $this->get_series_count());
				if ($showing_preacher_page)
					$output .= $stats;
				else
					$output .= '<a href="'.$this->get_url().'">'.$stats.'</a>';
				$output .= '</p>';
			}
			
		}
		return $this->do_div ($output, 'preacher');
	}
}
?>