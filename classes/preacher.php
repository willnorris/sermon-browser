<?php
/**
* classes/preacher.php
* 
* Contains the mbsb_preacher class
* 
* @author Mark Barnes <mark@sermonbrowser.com>
* @package SermonBrowser
* @subpackage Preacher
*/

/**
* Class that stores and processes the preacher custom post type
* 
* @package SermonBrowser
* @subpackage Preacher
* @author Mark Barnes <mark@sermonbrowser.com>
*/
class mbsb_preacher extends mbsb_pss_template {

	/**
	* Initiates the object and populates its properties
	* 
	* @param integer $post_id
	*/
	public function __construct ($post_id) {
		$post = get_post ($post_id);
		$this->populate_initial_properties($post);
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
		if (mbsb_get_option('preacher_image_pos') != 'none' && has_post_thumbnail($this->id))
			$output .= $this->do_div (get_the_post_thumbnail($this->id, 'mbsb_preacher', array ('class' => mbsb_get_option('preacher_image_pos'))), 'preacher_image');
		$description = ($excerpt_length == 0) ? $this->get_description() : '<p>'.$this->get_excerpt($excerpt_length).'</p>';
		$output .= $this->do_div ($description, 'description');
		if (mbsb_get_option('show_sermon_counts_on_sermon_page') && !$showing_preacher_page) {
			$num_sermons = $this->get_sermon_count();
			if ($num_sermons > 1) {
				$output .= '<p class="preacher_statistics"><strong>'.__('See more', MBSB).'</strong>: ';
				$stats = sprintf(__('%1s sermons by %2s in %3s series ', MBSB), $num_sermons, esc_html($this->get_name()), $this->get_series_count());
				$output .= '<a href="'.$this->get_url().'">'.$stats.'</a>';
				$output .= '</p>';
			}
		}
		if (defined('DOING_AJAX'))
			return $output;
		else
			return $this->do_div ($output, 'preacher');
	}
}
?>