<?php
/**
* Class that stores and handles a single passage
* It should only be called by the mbsb_passages class
* 
* @package SermonBrowser
* @subpackage passage
* @author Mark Barnes
*/

class mbsb_single_passage {
	
	/**
	* Initiates the object and populates its properties
	* 
	* @param array $start - an associative array (with the keys 'book', 'chapter' and 'verse'
	* @param array $end - as above
	*/
	public function __construct ($start, $end) {
		$this->start = $start;
		$this->end = $end;
		$this->formatted = $this->get_formatted();
	}

	/**
	* Returns a formatted Bible reference (e.g. John 3:1-16, not John 3:1-John 3:16)
	* 
	* @param boolean $ignore_first_book = true if the first book name should not be outputted
	* @param boolean $ignore_first)chapter = true if the first chapter should not be outputted
	* @return string - the formatted reference
	*/
	public function get_formatted ($ignore_first_book = false, $ignore_first_chapter = false) {
		if (isset($this->formatted) && !$ignore_first_book && !$ignore_first_chapter)
			return $this->formatted;
		$bible_books = mbsb_passages::bible_books();
		if ($ignore_first_book)
			$start_book = '';
		else
			$start_book = $bible_books['mbsb_index'][$this->start['book']];
		if ($ignore_first_chapter)
			$start_chapter = '';
		else
			$start_chapter = "{$this->start['chapter']}:";
		$end_book = $bible_books['mbsb_index'][$this->end['book']];
		if ($this->start['book'] == $this->end['book']) {
			if ($this->start['chapter'] == $this->end['chapter']) {
				if ($this->start['verse'] == $this->end['verse']) {
					$reference = "{$start_book} {$start_chapter}{$this->start['verse']}";
				} else {
					$reference = "{$start_book} {$start_chapter}{$this->start['verse']}-{$this->end['verse']}";
				}
			} else {
				 $reference = "{$start_book} {$start_chapter}{$this->start['verse']}-{$this->end['chapter']}".__(':', MBSB)."{$this->end['verse']}";
			}
		} else {
			$reference =  "{$start_book} {$start_chapter}{$this->start['verse']} - {$end_book} {$this->end['chapter']}".__(':', MBSB)."{$this->end['verse']}";
		}
		return trim($reference);
	}
	
	/**
	* Returns the Bible text for this passage
	* 
	* @param string $preferred_version
	* @return boolean|string - false on failure, the text on success
	*/
	public function get_bible_text($preferred_version = '') {
		if ($preferred_version == '')
			$preferred_version = mbsb_get_preferred_version();
		$bible = mbsb_get_bible_details($preferred_version);
		if (!$bible)
			return false;
		else {
			if ($bible['service'] == 'biblia') {
				$url = "http://api.biblia.com/v1/bible/content/{$preferred_version}.html?fulltext=false&redLetter=false&formatting=character&key=".mbsb_get_api_key('biblia')."&passage=".urlencode($this->formatted);
				$bible_text = mbsb_cached_download ($url);
				if ($bible_text['response']['code'] == '200')
					return $bible_text['body'];
			} elseif ($bible['service'] == 'biblesearch') {
				$url = "http://bibles.org/passages.xml?q[]=".urlencode($this->formatted)."&version=".$preferred_version;
				$response = mbsb_cached_download ($url, 604800, mbsb_get_api_key('biblesearch'));
				if (is_wp_error($response) || $response['response']['code'] != '200')
					return false;
				$response = new SimpleXMLElement($response['body']);
				if (!isset($response->result->passages->passage->path))
					return false;
				$bible_text = mbsb_cached_download ('http://bibles.org/'.$response->result->passages->passage->path, 604800, mbsb_get_api_key('biblesearch'));
				if ($bible_text['response']['code'] == '200') {
					$bible_text = new SimpleXMLElement($bible_text['body']);
					if (isset($bible_text->verse)) {
						$output = '';
						foreach ($bible_text->verse as $verse)
							$output .= $verse->text;
					}
					return $output;
				}
			}
		}
	}
}
?>