<?php
/**
* classes/service.php
*
* Contains the mbsb_service class
*
* @author Mark Barnes <mark@sermonbrowser.com>
* @package SermonBrowser
* @subpackage Service
*/

/**
* Class that stores and processes the service custom post type
*
* @package SermonBrowser
* @subpackage Service
* @author Mark Barnes <mark@sermonbrowser.com>
*/
class mbsb_service extends mbsb_pss_template {

	/**
	* The time of the service (stored as the number of seconds past midnight)
	*
	* @var integer
	*/
	private $time;

	/**
	* Initiates the object and populates its properties
	*
	* @param integer $post_id
	* @return mbsb_service
	*/
	public function __construct ($post_id) {
		$post = get_post ($post_id);
		$this->populate_initial_properties($post);
		if ($this->present)
			$this->time = (int)get_post_meta ($post_id, 'mbsb_service_time', true);
	}

	/**
	* Returns a the service time as a formatted string
	*
	* @param string $format - uses PHP date formats
	* @return string
	*/
	public function get_time($format = 'H:i') {
		return gmdate ($format, $this->time);
	}

	/**
	* Stores the service time in the service metadata
	*
	* @param string $time - the time to be stored as a human-friendly string (e.g. 6pm, or 12:45)
	* @return boolean - true on success, false on failure
	*/
	public function set_time($time) {
		if (($seconds = strtotime ('1 January 1970 '.trim($time).' UTC')) && update_post_meta ($this->id, 'mbsb_service_time', $seconds)) {
			$this->time = $seconds;
			return true;
		}
		return false;
	}
}
?>
