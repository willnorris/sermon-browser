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
	* True if the object is populated, false otherwise
	* 
	* @var boolean
	*/
	public $present;
	
	/**
	* The post_id of this post
	* 
	* @var integer
	*/
	protected $id;
	
	/**
	* The post_status of the post (e.g. publish, pending, draft, auto-draft, future, private, inherit or trash)
	* 
	* @var string
	*/
	protected $status;
	
	/**
	* The description of the preacher/series/sermon
	* 
	* @var string
	*/
	protected $description;
	
	/**
	* The slug of the preacher/series/sermon
	* 
	* @var string
	*/
	protected $slug;
	
	/**
	* The name of the preacher/series/sermon
	* 
	* @var string
	*/
	protected $name;
	
	/**
	* The type of object this is (e.g. 'sermon', 'series', etc.)
	* 
	* @var string
	*/
	protected $type;
	
	/**
	* Warning function to prevent the class being called directly
	*/
	public function __construct() {
		wp_die ('The mbsb_spss_template class should not be called directly, but only extended by other classes.');
	}
	
	/**
	* Populates the initial properties for all objects
	* 
	* @param stdClass $post
	*/
	protected function populate_initial_properties($post) {
		if (empty($post) || $post->post_type != get_class($this)) {
			$this->present = false;
		} else {
			$properties = array ('ID' => 'id', 'post_status' => 'status', 'post_content' => 'description', 'post_name' => 'slug', 'post_title' => 'name', 'post_excerpt' => 'excerpt');
			foreach ($properties as $k => $v)
				$this->$v = $post->$k;
			$this->type = substr($post->post_type, 5);
			$this->present = true;
		}
	}

	/**
	* Returns the post's description
	* 
	* @param boolean $raw - if true returns the description as stored, if false filters it through the_content
	* @return string
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
	* Returns the post id
	* 
	* @return boolean|integer - false on failure, the id on success
	*/
	public function get_id() {
		if (isset($this->id) && $this->id !== null)
			return $this->id;
		else
			return false;
	}
	
	/**
	* Returns the type of object
	* 
	* e.g. sermon, preacher, media_attachment, etc.
	* 
	* @return string
	*/
	public function get_type() {
		return substr(get_class(), 5);
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
			return wp_trim_words($this->get_description(), $excerpt_length, '&hellip; (<a class="read_more" href="'.$this->get_url()."\" id=\"read_more_{$this->type}\">".__('read more', MBSB).')</a>');
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
		$content = $this->do_div ($content, "{$div_type}_text", 'alignleft').$this->do_div ('<a id="heading_pointer_link_'.$div_type.'" class="heading_pointer" href="#">&#9660;</a>', "{$div_type}_pointer", 'alignright');
		return $this->do_div ($content, $div_type, $class);
	}

	/**
	* Gets the previous sermon
	* 
	* @return stdClass
	*/
	public function get_previous () {
		$previous = $this->get_adjacent (true);
		if ($previous)
			return new mbsb_sermon($previous->ID);
		else
			return false;
	}
	
	/**
	* Gets the next sermon
	* 
	* @return stdClass
	*/
	public function get_next () {
		$next = $this->get_adjacent (false);
		if ($next)
			return new mbsb_sermon($next->ID);
		else
			return false;
	}

	/**
	* Gets the adjacent sermon
	* 
	* @param string $direction
	*/
	protected function get_adjacent ($direction) {
		$next_previous = $direction ? 'previous' : 'next';
		$adjacent = get_adjacent_post(false, '', $direction);
		return $adjacent;
	}
	
}
?>