<?php
/**
* Class that provides basic functionality to be extended by the preacher, series and services classes
* 
* This class should never be called directly, but only extended
* 
* @package SermonBrowser
* @subpackage pss_template
* @author Mark Barnes
*/
class mbsb_pss_template {

	/**
	* Warning function to prevent the class being called directly
	*/
	public function __construct() {
		wp_die ('The '.get_class($this).' should not be called directly, but only extended by other classes.');
	}

	/**
	* Returns the preacher/series/service description
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
	* Returns the URL of the current preacher/series/service
	* 
	* @return string
	*/
	public function get_url() {
		return get_permalink ($this->id);
	}
	
	/**
	* Returns the number of sermons preached by the current preacher/in the current series/at the current service
	* 
	* @uses WP_Query
	* @return integer
	*/
	public function get_sermon_count() {
		$query = new WP_Query(array ('post_type' => 'mbsb_sermon', 'meta_key' => $this->type, 'meta_value' => $this->id));
		return $query->found_posts;
	}

	/**
	* Returns the number of series the current preacher/service has contributed to/includes
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
	* Gets the excerpt for the current preacher/series/service (if provided), or computes one to the required length
	* 
	* @param integer $excerpt_length
	*/
	protected function get_excerpt($excerpt_length = null) {
		if ($excerpt_length === null)
			$excerpt_length = mbsb_get_option ('excerpt_length');
		if ($this->excerpt)
			return $this->excerpt;
		else {
			return wp_trim_words($this->get_description(), $excerpt_length, '&hellip; (<a href="'.$this->get_url()."\" id=\"read_more_{$this->type}\">".__('read more', MBSB).')</a>');
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
	protected function do_div ($content, $div_type, $class='') {
		if ($class == '')
			$class = "{$this->type}_{$div_type}";
		return "<div id=\"{$this->type}_{$this->id}_{$div_type}\" class=\"{$class}\">{$content}</div>";
	}
}
?>
