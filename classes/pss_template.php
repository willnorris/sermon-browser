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
class mbsb_pss_template extends mbsb_spss_template {

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
		add_filter ('posts_join_paged', 'mbsb_join_series');
		add_filter ('posts_groupby', array ($this, 'query_groupby_series'));
		$posts = $this->get_sermon_count();
		remove_filter ('posts_join_paged', 'mbsb_join_series');
		remove_filter ('posts_groupby', array ($this, 'query_groupby_series'));
		return $posts;
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
    
	public function adjacent_join_series ($join) {
		global $wpdb;
		return $join." INNER JOIN {$wpdb->prefix}postmeta AS series_postmeta ON (p.ID = series_postmeta.post_id) INNER JOIN {$wpdb->prefix}posts AS series ON (series.ID = series_postmeta.meta_value AND series.post_type = 'mbsb_series')";
	}
	
	protected function get_adjacent ($direction) {
		$next_previous = $direction ? 'previous' : 'next';
		add_filter ("get_{$next_previous}_post_join", array ($this, "adjacent_join_{$this->type}"));
		$adjacent = get_adjacent_post(false, '', $direction);
		remove_filter ("get_{$next_previous}_post_join", array ($this, "adjacent_join_{$this->type}"));
		return $adjacent;
	}
}
?>