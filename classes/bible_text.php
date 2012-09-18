<?php
/**
* Class that retrieves Bible text from online APIs
* 
* @package SermonBrowser
* @subpackage bible_text
* @author Mark Barnes
*/
class mbsb_bible_text {
	
	private $data, $passages;
	
	public function __construct (mbsb_passage $passages) {
		$this->data = $passages;
		$processed = $passages->get_processed();
		foreach ($processed as $p)
			if (!$p['error'])
				$this->passages[] = urlencode($p['formatted']);
	}

	private function get_bible_list() {
		$bibles = get_transient ('mbsb_bible_list_'.get_locale());
		$bibles = false;
		if (!$bibles) {
			$biblia_bibles = mbsb_cached_download('http://api.biblia.com/v1/bible/find?key='.$this->get_api_key('biblia'));
			$biblia_bibles = json_decode($biblia_bibles['body']);
			if (isset($biblia_bibles->bibles)) {
				$biblia_ignore = mbsb_get_option ('ignored_biblia_bibles');
				$biblia_bibles = $biblia_bibles->bibles;
				foreach ($biblia_bibles as $bible) {
					$bible->title = trim(str_replace ('With Morphology', '', $bible->title));
					if (strtolower(substr($bible->title, 0, 4)) == 'the ')
						$bible->title = substr($bible->title, 4);
					if (!in_array($bible->bible, $biblia_ignore))
						$bibles[$bible->bible] = array ('name' => $bible->title, 'language_code' => $bible->languages[0], 'language_name' => $this->language_from_code($bible->languages[0]), 'service' => 'biblia');
				}
			}
			uasort($bibles, array ($this, 'bible_sort'));
			set_transient ('mbsb_bible_list_'.get_locale(), $bibles, 604800);
		}
		return $bibles;
	}
	
	private function get_preferred_version() {
		return mbsb_get_option ('bible_version_'.get_locale());
	}
	
	private function update_preferred_version($version) {
		return mbsb_update_option ('bible_version_'.get_locale(), $version);
	}
	
	private function get_api_key($service) {
		if ($service == 'biblia')
			return mbsb_get_option('biblia_api_key');
	}
	
	private function get_bible_details($version) {
		$bibles = $this->get_bible_list();
		if (isset($bibles[$version]))
			return $bibles[$version];
		else
			return false;
	}

	public function bible_sort ($a, $b) {
		if (($a['name'] == $b['name']) && ($a['language_name'] == $b['language_name']))
			return 0;
		elseif ($a['language_name'] == $b['language_name'])
			return ($a['name'] > $b['name']) ? 1 : -1;
		else
			return ($a['language_name'] > $b['language_name']) ? 1 : -1;
	}
	
	public function do_bible_list_dropdown($preferred_version = '') {
		$bibles = $this->get_bible_list();
		if ($preferred_version == '')
			$preferred_version = $this->get_preferred_version();
		$local_bibles = array();
		$other_bibles = array ('<optgroup label="'.__('Other languages', MBSB).'">');
		foreach ($bibles as $bible) {
			if ($bible['code'] == $preferred_version)
				$insert = ' selected="selected"';
			else
				$insert = '';
			if (strpos(get_locale(), "{$bible['language_code']}_") === 0)
				$local_bibles[] = "<option{$insert} value=\"{$bible['code']}-{$bible['service']}\">{$bible['name']}</option>";
			else
				$other_bibles[] = "<option{$insert} value=\"{$bible['code']}-{$bible['service']}\">{$bible['language_name']}: {$bible['name']}</option>";
		}
		$other_bibles[] = '</optgroup>';
		if (mbsb_get_option('hide_other_language_bibles'))
			$bibles = $local_bibles;
		else
			$bibles = array_merge ($local_bibles, $other_bibles);
		return  "<select id=\"bible_dropdown\">".implode('', $bibles).'</select>';
	}
	
	public function get_bible_text($preferred_version = '') {
		if ($preferred_version == '')
			$preferred_version = $this->get_preferred_version();
		$bible = $this->get_bible_details($preferred_version);
		$output = '';
		if (!$bible)
			return false;
		else {
			foreach ($this->passages as $p)
				if ($bible['service'] == 'biblia') {
					$url = "http://api.biblia.com/v1/bible/content/{$preferred_version}.html?fulltext=true&key=".$this->get_api_key('biblia')."&passage=".$p;
					$bible_text = mbsb_cached_download ($url);
					$output .= $bible_text['body'];
				}
		}
		return $output;
	}
	
	private function language_from_code ($code) {
		$languages = array ('ar' => __('Arabic', MBSB), 'el' => __('Greek', MBSB), 'en' => __('English', MBSB), 'eo' => __('Esperanto', MBSB), 'fi' => __('Finnish'), 'fr' => __('French', MBSB), 'it' => 'Italian', 'nl' => __('Dutch'), 'pt' => 'Portuguese', 'ru' => 'Russian');
		if (isset($languages[$code]))
			return $languages[$code];
		else
			return $code;
	}
	
}

?>
