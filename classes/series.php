<?php
/**
* Class that stores and processes the series custom post type
* 
* @package SermonBrowser
* @subpackage preacher
* @author Mark Barnes
*/
class mbsb_series extends mbsb_pss_template {

	/**
	* True if the object contains a series, false otherwise
	* 
	* @var boolean
	*/
	public $present;

	/**
	* Initiates the object and populates its properties
	* 
	* @param integer $post_id
	* @return mbsb_series
	*/
	public function __construct ($post_id) {
		$post = get_post ($post_id);
		if ($post && $post_id !== false && $post->post_type == 'mbsb_series') {
			$properties = array ('ID' => 'id', 'post_status' => 'status', 'post_content' => 'description', 'post_name' => 'slug', 'post_title' => 'name', 'post_excerpt' => 'excerpt');
			foreach ($properties as $k => $v)
				if (empty($post) || $post->post_type != 'mbsb_series')
					$this->$v = null;
				else
					$this->$v = $post->$k;
			$this->type = substr($post->post_type, 5);
			$this->present = true;
		} else
			$this->present = false;
	}

	/**
	* Outputs the series details
	* 
	* @param integer $excerpt_length - the maximum number of words to use in the description (0 = unlimited)
	* @return string
	*/
	public function get_output($excerpt_length = 0) {
		global $post;
		$showing_series_page = (isset($post->ID) && ($post->ID == $this->id));
		$output = '';
		if (mbsb_get_option('series_image') != 'none' && has_post_thumbnail($this->id))
			$output .= $this->do_div (get_the_post_thumbnail($this->id, 'mbsb_series', array ('class' => mbsb_get_option('series_image'))), 'series_image');
		$description = ($excerpt_length == 0) ? $this->get_description() : '<p>'.$this->get_excerpt($excerpt_length).'</p>';
		$output .= $this->do_div ($description, 'description');
		if (mbsb_get_option('show_statistics_on_sermon_page')) {
			if (defined('DOING_AJAX') || $post->post_type == 'mbsb_sermon') {
				$next_previous = '';
				$next = $this->get_next();
				if ($next)
					$next_previous .= $this->do_div ($next->get_linked_name(true).' &#9654;', 'next_sermon_in_series', 'alignright mbsb_textright');
				$previous = $this->get_previous();
				if ($previous)
					$next_previous .= $this->do_div ('&#9664; '.$previous->get_linked_name(true), 'previous_sermon_in_series', 'alignleft mbsb_textleft');
				if ($next_previous)
					$output .= $this->do_div ($next_previous, 'next_previous');
			}
		}
		return $this->do_div ($output, 'series');
	}

}
?>