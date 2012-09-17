<?php
/**
* Class that stores and processes the preacher custom post type
* 
* @package SermonBrowser
* @subpackage preacher
* @author Mark Barnes
*/
class mbsb_preacher {

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
	
	/**
	* Returns the preacher's description
	* 
	* @param boolean $raw - if true returns the description as stored, if false filters it through the_content
	*/
	public function get_description($raw = false) {
		if ($raw)
			return $this->description;
		else {
			if ($content_filter = has_filter('the_content', 'mbsb_provide_content'))
				remove_filter ('the_content', 'mbsb_provide_content', $content_filter);
			$description = apply_filters ('the_content', $this->description);
			if ($content_filter)
				add_filter ('the_content', 'mbsb_provide_content', $content_filter);
			return $description;
		}
	}
	
	/**
	* Returns the URL of the current preacher
	* 
	* @return string
	*/
	public function get_url() {
		return get_permalink ($this->id);
	}
	
	/**
	* Returns the number of sermons preached by the current preacher
	* 
	* @uses WP_Query
	* @return integer
	*/
	public function get_sermon_count() {
		$query = new WP_Query(array ('post_type' => 'mbsb_sermon', 'meta_key' => 'preacher', 'meta_value' => $this->id));
		return $query->found_posts;
	}
	
	/**
	* Returns the number of series the current preacher has contributed to
	* 
	* @uses WP_Query
	* @return integer
	*/
	public function get_series_count() {
		add_filter ('posts_join_paged', array ($this, 'query_join_series'));
		add_filter ('posts_groupby', array ($this, 'query_groupby_series'));
		$posts = $this->get_sermon_count();
		remove_filter ('posts_join_paged', array ($this, 'query_join_series'));
		remove_filter ('posts_groupby', array ($this, 'query_groupby_series'));
		return $posts;
	}
	
    /**
    * Adds SQL for the JOIN when querying on series
    * 
    * Filters posts_join_paged
    * 
    * @param string $join
    * @return string
    */
    public function query_join_series ($join) {
		return $join.mbsb_join_string('series');
    }
    
    /**
    * Adds SQL for the GROUP BY when querying on series
    * 
    * Filters posts_groupby
    * 
    * @param string $groupby
    * @return string
    */
    public function query_groupby_series ($groupby) {
		return "series_postmeta.meta_value";
    }
	
	/**
	* Gets the excerpt for the current preacher (if provided), or computes one to the required length
	* 
	* @param integer $excerpt_length
	*/
	private function get_excerpt($excerpt_length = null) {
		if ($excerpt_length === null)
			$excerpt_length = mbsb_get_option ('excerpt_length');
		if ($this->excerpt)
			return $this->excerpt;
		else {
			return wp_trim_words($this->get_description(), $excerpt_length, '&hellip; (<a href="'.$this->get_url().'" id="read_more_preacher">'.__('read more', MBSB).')</a>');
		}
	}
	
	/**
	* Helper function, that wraps text in a div
	* 
	* Used when creating the major sections of the frontend
	* A class is added, and the class name appended with the sermon id is used to provide a unique id
	* 
	* @param string $content - the HTML to be wrapped in the div
	* @param string $div_type - a descriptor that is used in the class and id
	* @return string
	*/
	private function do_div ($content, $div_type, $class='') {
		if ($class == '')
			$class = "sermon_{$div_type}";
		return "<div id=\"preacher_{$this->id}_{$div_type}\" class=\"{$class}\">{$content}</div>";
	}

}
?>