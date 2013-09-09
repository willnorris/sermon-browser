<?php
/**
* classes/media_attachments.php
* 
* Contains the mbsb_media_attachments class
* 
* @author Mark Barnes <mark@sermonbrowser.com>
* @package SermonBrowser
* @subpackage MediaAttachments
*/

/**
* mbsb_media_attachments class
* 
* Retrieves all media attachments from post metadata, provides methods used to access all media (as a block), and an array of mbsb_single_media_attachment objects so individual items can be accessed individually.
* 
* @package SermonBrowser
* @subpackage MediaAttachments
*/
class mbsb_media_attachments extends mbsb_mpspss_template {
	
	/**
	* An array of mbsb_single_media_attachment objects
	* 
	* @var array
	*/
	private $attachments;
	
	/**
	* The post ID of the sermon the media is attached to
	* 
	* @var integer
	*/
	private $sermon_id;
	
	/**
	* Creates the object
	* 
	* Queries the postmeta table for attachments
	* 
	* @param integer $post_id - the post_id of the sermon
	*/
	function __construct($post_id) {
		global $wpdb;
		$meta_ids = $wpdb->get_col($wpdb->prepare("SELECT meta_id FROM {$wpdb->prefix}postmeta WHERE post_id=%s AND meta_key='attachments' ORDER BY meta_id", $post_id));
		if ($meta_ids) {
			$attachments = array();
			foreach ($meta_ids as $meta_id) {
				$single = new mbsb_single_media_attachment($meta_id);
				if ($single->present)
					$attachments[] = $single;
			}
			$this->present = !empty($attachments);
		}
		else
			$this->present = false;
		if ($this->present) {
			$this->attachments = $attachments;
			$this->id = 0;
			$this->type = 'attachments';
		}
		$this->sermon_id = $post_id;
	}
	
	/**
	* Returns an array of attachments
	* 
	* @param boolean $most_recent_first - true if the attachments are to be ordered in descending meta ID order, false otherwise
	* @return array
	*/
	public function get_attachments($most_recent_first = false) {
		if ($this->present) {
			if ($most_recent_first && is_array($this->attachments))
				return array_reverse ($this->attachments);
			else
				return $this->attachments;
		} else
			return false;
	}

	/**
	* Returns a simple list of the media items for the frontend
	* 
	* @return string
	*/
	public function get_frontend_list() {
		$attachments = $this->get_attachments();
		if ($attachments) {
			$output = '';
			foreach ($attachments as $attachment)
				$output .= $this->do_div($attachment->get_media_player(), $attachment->get_type().'_'.$attachment->get_id(), 'sermon_media_item sermon_media_item_'.$attachment->get_type());
			return $output;
		}
		else
			return __('No media attached', MBSB);
	}
	
	/** Returns enclosure tags for podcast feed
	*
	* @return string
	*/
	public function get_podcast_enclosures($podcast_type) {
		$output = '';
		$attachments = $this->attachments;
		if ($this->attachments) {
			foreach ($this->attachments as $attachment) {
				$type = $attachment->get_type();
				if ($type == 'library' or $type == 'url' or $type == 'legacy') {
					$url = $attachment->get_url();
					$filesize = $attachment->get_filesize();
					if ( !$filesize )
						$filesize = '0';
					$mime_type = $attachment->get_mime_type();
					$media_type = explode('/', $mime_type);
					$media_type = $media_type[0];
					if ( $podcast_type == 'all' or $podcast_type == $media_type )
						$output .= '	<enclosure url="'.esc_attr($url).'" length="'.esc_attr($filesize).'" type="'.esc_attr($mime_type).'" />'."\n";
				}
			}
		}
		return $output;
	}

	/**
	* Returns a simple list of the media items (titles separated by <br/> tags), with admin edit links
	* 
	* @return string
	*/
	public function get_admin_cell() {
		$attachments = $this->get_attachments();
		if ($attachments) {
			$output = array();
			foreach ($attachments as $attachment)
				if ($library_id = $attachment->get_library_id()) {
					$output[] = '<strong>'.(current_user_can ('edit_post', $library_id) ? ("<a href=\"".get_edit_post_link ($library_id)."\">{$attachment->get_name()}</a>") : $attachment->get_name()).'</strong> ('.$attachment->get_friendly_mime_type().')';
				} elseif ($attachment->get_type() != 'embed')
					$output[] = $attachment->get_name().' ('.$attachment->get_friendly_mime_type().')';
				else
					$output[] = $attachment->get_name();
			return implode('</br>', $output);
		}
		else
			return __('No media attached', MBSB);
	}
	
	/**
	* Adds a library attachment to a sermon
	* @var mbsb_single_media_attachment
	* 
	* @param integer $library_id - the post ID of the library attachment
	* @return mixed False for failure. Null if the file is already attached. The media_attachment object if successful. 
	*/
	public function add_library_attachment ($library_id) {
		$existing_meta = get_post_meta ($this->sermon_id, 'attachments');
		if ($existing_meta)
			foreach ($existing_meta as $em)
				if ($em['type'] == 'library' && $em['post_id'] == $library_id)
					return null;
		$metadata = array ('type' => 'library', 'post_id' => $library_id);
		if ($meta_id = add_post_meta ($this->sermon_id, 'attachments', $metadata))
			return new mbsb_single_media_attachment($meta_id);
		else
			return false;
	}
	
	/**
	* Adds a URL attachment to a sermon
	*  
	* @param string $url
	* @return mixed False for failure. Null if the URL is not valid. The media_attachment object if successful.
	*/
	public function add_url_attachment ($url) {
		$headers = wp_remote_head ($url, array ('redirection' => 5));
		if (is_wp_error($headers) || $headers['response']['code'] != 200)
			return null;
		else {
			$content_type = isset($headers['headers']['content-type']) ? $headers['headers']['content-type'] : '';
			if (($a = strpos($content_type, ';')) !== FALSE)
				$content_type = substr($content_type, 0, $a);
			$metadata = array ('type' => 'url', 'url' => $url, 'size' => (isset($headers['headers']['content-length']) ? $headers['headers']['content-length'] : 0), 'mime_type' => $content_type, 'date_time' => time());
			if ($meta_id = add_post_meta ($this->sermon_id, 'attachments', $metadata))
				return new mbsb_single_media_attachment($meta_id);
			else
				return false;
		}
	}
	
	/**
	* Adds a Legacy file attachment to a sermon
	*
	* @param string $filename
	* @return mixed False for failure.  Null if the file is not found.  The media_attachment object if successful.
	*/
	public function add_legacy_attachment ($filename) {
		$legacy_upload_folder = mbsb_get_option('legacy_upload_folder');
		$absolute_file_path = trailingslashit(path_join(get_home_path(), $legacy_upload_folder)).$filename;
		if (!is_file($absolute_file_path))
			return null;
		else {
			$url = site_url($legacy_upload_folder.$filename);
			$headers = wp_remote_head ($url, array ('redirection' => 5));
			if (is_wp_error($headers) || $headers['response']['code'] != 200)
				return null;
			else {
				$content_type = isset($headers['headers']['content-type']) ? $headers['headers']['content-type'] : '';
				if (($a = strpos($content_type, ';')) !== FALSE)
					$content_type = substr($content_type, 0, $a);
				$metadata = array('type' => 'legacy', 'filename' => $filename, 
					'mime_type' => $content_type, 
					'date_time' => time());
				if ($meta_id = add_post_meta ($this->sermon_id, 'attachments', $metadata))
					return new mbsb_single_media_attachment($meta_id);
				else
					return false;
			}
		}
	}
	
	/**
	* Adds an embed to a sermon
	*  
	* @param string $embed
	* @return mixed Null for invalid code. False for failure. The media_attachment object if successful. 
	*/
	public function add_embed_attachment ($embed) {
		global $allowedposttags;
		$old_allowedposttags = $allowedposttags;
		$allowedposttags['object'] = array('height' => array(), 'width' => array(), 'classid' => array(), 'codebase' => array());
		$allowedposttags['param'] = array('name' => array(), 'value' => array());
		$allowedposttags['embed'] = array('src' => array(), 'type' => array(), 'allowfullscreen' => array(), 'allowscriptaccess' => array(), 'height' => array(), 'width' => array(), 'allowfullscreen' => array());
		$allowedposttags['iframe'] = array('height' => array(), 'width' => array(), 'src' => array(), 'frameborder' => array(), 'allowfullscreen' => array());
		$embed = wp_kses_post($embed);
		$allowedposttags = $old_allowedposttags;
		if (trim($embed) == '')
			return null;
		$metadata = array ('type' => 'embed', 'code' => $embed, 'date_time' => time());
		if ($meta_id = add_post_meta ($this->sermon_id, 'attachments', $metadata))
			return new mbsb_single_media_attachment($meta_id);
		else
			return false;
	}



}
?>