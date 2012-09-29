<?php
/**
* classes/mpspss_templates.php
* 
* Contains the mbsb_mpspss_template class
* 
* @author Mark Barnes <mark@sermonbrowser.com>
* @package SermonBrowser
* @subpackage Templates
*/

/**
* Class that provides basic functionality to be extended by the media, passage, sermon, preacher, series and services classes
* 
* This class should never be called directly, but only extended
* 
* @package SermonBrowser
* @subpackage Templates
*/
class mbsb_mpspss_template {

	/**
	* True if the object contains passages, false otherwise
	* 
	* @var boolean
	*/
	public $present;


	/**
	* Warning function to prevent the class being called directly
	*/
	public function __construct() {
		wp_die ('The mbsb_spss_template class should not be called directly, but only extended by other classes.');
	}
	
	/**
	* Helper function, that wraps text in a div
	* 
	* Used when creating the major sections of the frontend
	* A class is added, and the class name appended with the sermon id is used to provide a unique id
	* 
	* @param string $content - the HTML to be wrapped in the div
	* @param string $div_type - a descriptor that is used in the id (and optionally the class)
	* @param string $class - the class of this div
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
	* @param string $class - the class of this div
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
}
?>