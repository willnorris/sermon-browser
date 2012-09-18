<?php
/**
* Class that provides basic functionality to be extended by the sermon, preacher, series and services classes
* 
* This class should never be called directly, but only extended
* 
* @package SermonBrowser
* @subpackage spss_template
* @author Mark Barnes
*/

class mbsb_spss_template {
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
	*  Returns the name of the current preacher/series/service
	* 
	* @return string
	*/
	public function get_name() {
		return get_the_title ($this->id);
	}

	public function get_linked_name($add_detail_to_title_attr = false) {
		if ($add_detail_to_title_attr) {
			$passages = $this->get_formatted_passages();
			if ($passages)
				$detail = esc_html(sprintf(__('%1s on %2s'), $this->preacher->get_name(), $passages));
			else
				$detail = $this->preacher->get_name();
			return '<a title="'.$detail.'" href="'.$this->get_url().'">'.esc_html($this->title).'</a>';
		} else
			return '<a href="'.$this->get_url().'">'.esc_html($this->get_name()).'</a>';
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
		if ($this->id)
			$id = "{$this->id}_";
		else
			$id = '';
		return "<div id=\"{$this->type}_{$id}{$div_type}\" class=\"{$class}\">{$content}</div>";
	}

	/**
	* Helper function, that wraps text in a heading div, adding a triangle on the right
	* 
	* Used when creating the major sections of the frontend
	* A class is added, and the class name appended with the sermon id is used to provide a unique id
	* 
	* @param string $content - the HTML to be wrapped in the div
	* @param string $div_type - a descriptor that is used in the class and id
	* @return string
	*/
	protected function do_heading ($content, $div_type, $class='') {
		if ($class == '')
			$class = "sermon_{$div_type} mbsb_collapsible_heading";
		else
			$class = "{$class} mbsb_collapsible_heading";
		$content = $this->do_div ($content, "{$div_type}_text", 'alignleft').$this->do_div ('&#9660;', "{$div_type}_pointer", 'alignright');
		return $this->do_div ($content, $div_type, $class);
	}

	public function get_previous () {
		$previous = $this->get_adjacent (true);
		if ($previous)
			return new mbsb_sermon($previous->ID);
		else
			return false;
	}
	
	public function get_next () {
		$next = $this->get_adjacent (false);
		if ($next)
			return new mbsb_sermon($next->ID);
		else
			return false;
	}

	protected function get_adjacent ($direction) {
		$next_previous = $direction ? 'previous' : 'next';
		$adjacent = get_adjacent_post(false, '', $direction);
		return $adjacent;
	}
	
}

?>

