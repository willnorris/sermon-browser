<?php
/**
* classes/single_passage.php
*
* Contains the mbsb_single_passage class
*
* @author Mark Barnes <mark@sermonbrowser.com>
* @package SermonBrowser
* @subpackage Passages
*/

/**
* Class that stores and handles a single passage
* It should only be called by the mbsb_passages class
*
* @package SermonBrowser
* @subpackage Passages
* @author Mark Barnes <mark@sermonbrowser.com>
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
	* @param boolean $ignore_first_chapter = true if the first chapter should not be outputted
	* @return string - the formatted reference
	*/
	public function get_formatted ($ignore_first_book = false, $ignore_first_chapter = false) {
		if (isset($this->formatted) && !$ignore_first_book && !$ignore_first_chapter) {
			return $this->formatted;
		}
		$bible_books = mbsb_passages::bible_books();
		if ($ignore_first_book) {
			$start_book = '';
		} else {
			$start_book = $bible_books['mbsb_index'][$this->start['book']];
		}
		if ($ignore_first_chapter) {
			$start_chapter = '';
		} else {
			$start_chapter = "{$this->start['chapter']}".__(':', MBSB);
		}
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
		if ($preferred_version == '') {
			$preferred_version = mbsb_get_preferred_version();
		}
		if (mbsb_get_option('use_embedded_bible_'.get_locale())) {
			$biblia_book_num = ($this->start['book'] >= 39 ? $this->start['book']+21 : $this->start['book']);
			$output = '<biblia:bible id="bible-'.rand(10000,99999)."\" resource=\"{$preferred_version}\" startingReference=\"bible.{$biblia_book_num}.{$this->start['chapter']}.{$this->start['verse']}\"";
			foreach ((array)mbsb_get_option('embedded_bible_parameters') AS $param => $value) {
				if ($value === false) {
					$output .= " {$param}=\"false\"";
				} else if ($value !== true) {
					$output .= " {$param}=\"{$value}\"";
				}
			}
			$output .= "></biblia:bible>";
			return $output;
		} else {
			$bibles = new mbsb_online_bibles();
			$bible = $bibles->get_bible_details($preferred_version);
			if (!$bible) {
				return false;
			} else {
				if ($bible['service'] == 'biblia') {
					$url = "http://api.biblia.com/v1/bible/content/{$preferred_version}.html?fulltext=false&redLetter=false&formatting=character&key=".mbsb_get_api_key('biblia')."&passage=".urlencode($this->formatted);
					$bible_text = mbsb_cached_download ($url);
					if ($bible_text['response']['code'] == '200') {
						return $bible_text['body'];
					}
				} else if ($bible['service'] == 'esv') {
					$url = "http://www.esvapi.org/v2/rest/passageQuery?include-footnotes=false&include-headings=false&include-short-copyright=false&key=".mbsb_get_api_key('esv')."&passage=".urlencode($this->formatted);
					$bible_text = mbsb_cached_download ($url);
					if ($bible_text['response']['code'] == '200') {
						return $bible_text['body'];
					}
				} else if ($bible['service'] == 'biblesearch') {
					$url = "http://bibles.org/passages.xml?q[]=".urlencode($this->formatted)."&version=".$preferred_version;
					$response = mbsb_cached_download ($url, 604800, mbsb_get_api_key('biblesearch'));
					if (is_wp_error($response) || $response['response']['code'] != '200') {
						return false;
					}
					$response = new SimpleXMLElement($response['body']);
					if (!isset($response->result->passages->passage->path)) {
						return false;
					}
					$bible_text = mbsb_cached_download ('http://bibles.org/'.$response->result->passages->passage->path, 604800, mbsb_get_api_key('biblesearch'));
					if ($bible_text['response']['code'] == '200') {
						$bible_text = new SimpleXMLElement($bible_text['body']);
						if (isset($bible_text->verse)) {
							$output = '';
							foreach ($bible_text->verse as $verse) {
								$output .= $verse->text;
							}
						}
						if (isset($bible_text->meta->fums)) {
							$output .= (string)$bible_text->meta->fums;
						}
						return $output;
					}
				} else if ($bible['service'] == 'preaching_central') {
					$url = "http://api.preachingcentral.com/bible.php?passage=".urlencode($this->formatted)."&version=".$preferred_version;
					$bible_text = mbsb_cached_download ($url);
					if ($bible_text['response']['code'] == '200') {
						return $this->xml_to_html($bible_text['body'], $bible['service']);
					}
				} else if ($bible['service'] == 'netbible') {
					$url = "http://labs.bible.org/api/?formatting=para&type=xml&passage=".urlencode($this->formatted);
					$bible_text = mbsb_cached_download ($url);
					if ($bible_text['response']['code'] == '200') {
						return $this->xml_to_html($bible_text['body'], $bible['service']);
					}
				}
			}
		}
	}

	/**
	* Converts the XML returned by a Bible API service into HTML
	*
	* @param string $xml
	* @param string $service
	* @return string
	*/
	private static function xml_to_html ($xml, $service) {
		if ($service == 'preaching_central') {
			$xml = new SimpleXMLElement($xml);
			if (isset($xml->range->item)) {
				$output = array();
				$previous_chapter = 0;
				foreach ($xml->range->item as $item) {
					if ($item->chapter != $previous_chapter) {
						if ($item->verse == 1) {
							$output[] = ($previous_chapter == 0 ? '' : '</p><p>')."<span class=\"chapter_num\">{$item->chapter}</span> {$item->text}";
						} else {
							$output[] = "<sup>{$item->verse}</sup>{$item->text}";
						}
						$previous_chapter = $item->chapter;
					} else {
						$output[] = "<sup>{$item->verse}</sup>{$item->text}";
					}
				}
				return '<p>'.implode (' ', $output).'</p>';
			}
		} else if ($service == 'netbible') {
			$xml = new SimpleXMLElement($xml);
			if (isset($xml->item)) {
				$output = array();
				$previous_chapter = 0;
				foreach ($xml->item as $item) {
					if ($item->chapter != $previous_chapter) {
						if ($item->verse == 1) {
							$insert = "<span class=\"chapter_num\">{$item->chapter}</span> ";
						} else {
							$insert = "<sup>{$item->verse}</sup>";
						}
						$previous_chapter = $item->chapter;
					} else {
						$insert = "<sup>{$item->verse}</sup>";
					}
					if (strpos($item->text, '<p') === FALSE) {
						$output[] = "{$insert}{$item->text}";
					} else {
						$pos = strpos($item->text, '>', strpos ($item->text, '<p'));
						$output[] = substr($item->text, 0, $pos+1).$insert.substr($item->text, $pos+1);
					}
				}
				return implode (' ', $output);
			}
		}
	}
}
?>
