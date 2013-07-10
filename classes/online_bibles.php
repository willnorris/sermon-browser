<?php
/**
* classes/online_bibles.php
* 
* Contains the mbsb_online_bibles class
* 
* @author Mark Barnes <mark@sermonbrowser.com>
* @package SermonBrowser
* @subpackage OnlineBibles
*/

/**
* Class that provides all interaction with the list of online Bibles
* 
* @package SermonBrowser
* @subpackage OnlineBibles
* @author Mark Barnes <mark@sermonbrowser.com>
*/
class mbsb_online_bibles {

	/**
	* An array of the online Bibles currently available
	* 
	* @var array
	*/
	private $bibles;

	/**
	* Creates the object, and populates the list of available Bibles
	*/
	public function __construct() {
		$this->bibles = get_transient ('mbsb_bible_list_'.get_locale());
		if (!$this->bibles) {
			///esvapi.org
			$this->add_bible ('esv', 'English Standard Version', 'eng', 'esv');
			//NET Bible API
			$this->add_bible ('net', 'NET Bible', 'eng', 'netbible');
			//api.biblia.com
			if ($api_key = mbsb_get_api_key('biblia')) {
				$bibles_xml = mbsb_cached_download('http://api.biblia.com/v1/bible/find?key='.$api_key, 604800);
				if ($bibles_xml['response']['code'] == 200) {
					$bibles_xml = json_decode($bibles_xml['body']);
					if (isset($bibles_xml->bibles)) {
						$bibles_xml = $bibles_xml->bibles;
						foreach ($bibles_xml as $bible) {
							$bible->title = trim(str_replace ('With Morphology', '', $bible->title));
							if (strtolower(substr($bible->title, 0, 4)) == 'the ')
								$bible->title = substr($bible->title, 4);
							$this->add_bible ((string)$bible->bible, (string)$bible->title, (string)$bible->languages[0], 'biblia');
						}
					}
				}
			}
			//biblesearch.americanbible.org
			if ($api_key = mbsb_get_api_key('biblesearch')) {
				$bibles_xml = mbsb_cached_download('http://bibles.org/versions.xml', 604800, $api_key);
				if ($bibles_xml['response']['code'] == 200) {
					$bibles_xml = new SimpleXMLElement($bibles_xml['body']);
					if (isset($bibles_xml->version)) {
						$bibles_xml = $bibles_xml->version;
						foreach ($bibles_xml as $bible)
							$this->add_bible ((string)$bible->id, (string)$bible->name, (string)$bible->lang, 'biblesearch');
						//Some Bibles are missing from the list, and need to be added manually
						$this->add_bible ('BCN', 'Beibl Cymraeg Newydd (Argraffiad Diwygiedig)', 'cym', 'biblesearch');
						$this->add_bible ('BNET', 'Beibl.net', 'cym', 'biblesearch');
						$this->add_bible ('BWM', 'William Morgan Bible', 'cym', 'biblesearch');
					}
				}
			}
			//Preaching Central
			$bibles_xml = mbsb_cached_download('http://api.preachingcentral.com/bible-versions.php', 604800);
			if ($bibles_xml['response']['code'] == 200) {
				$bibles_xml = new SimpleXMLElement($bibles_xml['body']);
				if (isset($bibles_xml->version)) {
					foreach ($bibles_xml->version as $bible)
						$this->add_bible ((string)$bible->code, (string)$bible->name, (string)$bible->language, 'preaching_central');
				}
			}
			uasort ($this->bibles, array($this, 'bible_sort'));
			$this->inactivate_equivalent_bibles();
			set_transient ('mbsb_bible_list_'.get_locale(), $this->bibles, 604800);
		}
	}
	
	/**
	* Adds a Bible to the object, if it (or an equivalent) has not already been added
	* 
	* @param string $code
	* @param string $name
	* @param string $language
	* @param string $service
	*/
	private function add_bible ($code, $name, $language, $service) {
		if (strlen($language) == 2) {
			$language = $this->convert_language_code ($language);
		} elseif (strlen($language) > 3)
			$language = substr($language, 0, 3);
		$inactive = in_array_ic($code, mbsb_get_option('inactive_bibles')) || in_array_ic ($language, mbsb_get_option ('inactive_bible_languages')) || (mbsb_get_option('hide_other_language_bibles') && $language != $this->convert_language_code(substr(get_locale(), 0, 2)) || array_key_exists_ic ($code, $this->bibles));
		$this->bibles[$code] = array ('name' => $name, 'language_code' => $language, 'service' => $service, 'active' => !$inactive);
	}
	
	/**
	* Filterable function that marks certain Bibles as equivalent to one another, despite having different IDs
	* 
	* Avoids unnecessary duplication in the list of Bibles. It is already assumed that versions with the same id are already equivalent
	* 
	* @return array
	*/
	private function equivalent_bibles() {
		$equiv ['kjv1900_biblia'] = 'kjv';
		$equiv ['KJVAPOC_biblia'] = 'kjv';
		$equiv ['KJVA_biblesearch'] = 'kjv';
		$equiv ['akjv_preaching_central'] = 'kjv';
		$equiv ['byz_biblia'] = 'textusreceptus';
		$equiv ['elzevir_biblia'] = 'textusreceptus';
		$equiv ['scrmorph_biblia'] = 'textusreceptus';
		$equiv ['scr_biblia'] = 'textusreceptus';
		$equiv ['tr1894mr_biblia'] = 'textusreceptus';
		$equiv ['stephens_biblia'] = 'textusreceptus';
		$equiv ['textus-parsed_preaching_central'] = 'textusreceptus';
		$equiv ['byzantine-parsed_preaching_central'] = 'textusreceptus';
		$equiv ['textusreceptus_preaching_central'] = 'textusreceptus';
		$equiv ['byzantine_preaching_central'] = 'textusreceptus';
		$equiv ['bhs_preaching_central'] = 'bhs';
		$equiv ['wlc-novowels_preaching_central'] = 'bhs';
		$equiv ['aleppo_preaching_central'] = 'bhs';
		$equiv ['bhs-novowels_preaching_central'] = 'bhs';
		$equiv ['wlc_preaching_central'] = 'bhs';
		$equiv ['wlc2_preaching_central'] = 'bhs';
		$equiv ['wh1881mr_biblia'] = 'westcotthort';
		$equiv ['westcott_preaching_central'] = 'westcotthort';
		$equiv ['westcott-parsed_preaching_central'] = 'westcotthort';
		$equiv ['smith-vandyke_preaching_central'] = 'smithvandyke';
		$equiv ['ARVANDYKE_biblia'] = 'smithvandyke';
		$equiv ['lxx-noaccents_preaching_central'] = 'greeklxx';
		$equiv ['lxx_preaching_central'] = 'greeklxx';
		$equiv ['lxx-parsed_preaching_central'] = 'greeklxx';
		$equiv ['lxx-parsed-noaccents_preaching_central'] = 'greeklxx';
		$equiv ['lsg_biblia'] = 'louissegond';
		$equiv ['ls1910_preaching_central'] = 'louissegond';
		$equiv ['AMP_biblesearch'] = 'amplified';
		$equiv ['amplified_preaching_central'] = 'amplified';
		$equiv ['bb-sbb-rusbt_biblia'] = 'synodal';
		$equiv ['synodal_preaching_central'] = 'synodal';
		$equiv ['svv_biblia'] = 'statenvertaling';
		$equiv ['statenvertaling_preaching_central'] = 'statenvertaling';
		return apply_filters ('mbsb_equivalent_bibles', $equiv);
	}
	
	/**
	* Inactivates Bibles that have an equivalent, to remove duplicates
	*/
	private function inactivate_equivalent_bibles () {
		$equivalents = $this->equivalent_bibles();
		foreach ($equivalents as $bible => $common_name)
			if (!isset($e_index[$common_name])) {
				$common_versions = array_keys ($equivalents, $common_name);
				$this_version_active = false;
				foreach ($common_versions as &$c) {
					$c = substr($c, 0, strpos($c, '_'));
					if ($this_version_active && isset($this->bibles[$c]['active']) && $this->bibles[$c]['active'])
						$this->bibles[$c]['active'] = false;
					elseif (!$this_version_active && isset($this->bibles[$c]['active']) && $this->bibles[$c]['active'])
						$this_version_active = true;
				}
			}
	}
	
	/**
	* Converts an ISO639-1 language code into an ISO639-2 language code
	* 
	* Can be filtered with mbsb_language_code_table
	* 
	* @param string $language
	* @return string
	*/
	private function convert_language_code ($language) {
		$codes = array ('en' => 'eng', 'ar' => 'ara', 'el' => 'grc', 'it' => 'ita', 'eo' => 'epo', 'fr' => 'fra', 'fi' => 'fin', 'ru' => 'rus', 'nl' => 'nld', 'pt' => 'por');
		$codes = apply_filters ('mbsb_language_code_table', $codes);
		if (isset($codes[$language]))
			return $codes[$language];
		else
			return $language;

	}

	/**
	* Returns the HTML for the Bible dropdown list
	* 
	* @param string $selected_version - the Bible version currently selected
	* @param string $id - the HTML "id" used for the select box.  The default ('bible_dropdown') should be used for use in the frontend
	* @param string $name - the HTML "name" used for the select box.  The default ('') will omit the name parameter
	* @return string
	*/
	public function get_bible_list_dropdown($selected_version = '', $id='bible_dropdown', $name='') {
		if ($name)
			$name = 'name="'.$name.'"';
		if ($selected_version == '')
			$selected_version = mbsb_get_preferred_version();
		$local_bibles = array();
		$other_bibles = array ('<optgroup label="'.__('Other languages', MBSB).'">');
		foreach ($this->bibles as $code => $bible)
			if ($bible['active']) {
				if ($code == $selected_version)
					$insert = ' selected="selected"';
				else
					$insert = '';
				if ($this->convert_language_code(substr(get_locale(), 0, 2)) == $bible['language_code'])
					$local_bibles[] = "<option{$insert} value=\"{$code}\">{$bible['name']}</option>";
				else
					$other_bibles[] = "<option{$insert} value=\"{$code}\">".$this->language_from_code($bible['language_code']).": {$bible['name']}</option>";
			}
		$other_bibles[] = '</optgroup>';
		if (mbsb_get_option('hide_other_language_bibles'))
			$bibles = $local_bibles;
		else
			$bibles = array_merge ($local_bibles, $other_bibles);
		return  "<select id=\"$id\" $name >".implode('', $bibles).'</select><div id="passages_bible_loader"></div>';
	}

	/**
	* Returns the details of a Bible version
	* 
	* @param string $version
	* @return array
	*/
	public function get_bible_details($version) {
		if (isset($this->bibles[$version]))
			return $this->bibles[$version];
		else
			return false;
	}

	/**
	* Sorts a Bible list
	* 
	* Designed for use with the uasort function
	* 
	* @param array $a
	* @param array $b
	* @return integer
	*/
	public function bible_sort ($a, $b) {
		$a['language_name'] = $this->language_from_code ($a['language_code']);
		$b['language_name'] = $this->language_from_code ($b['language_code']);
		if (($a['name'] == $b['name']) && ($a['language_name'] == $b['language_name']))
			return 0;
		elseif ($a['language_name'] == $b['language_name'])
			return ($a['name'] > $b['name']) ? 1 : -1;
		else
			return ($a['language_name'] > $b['language_name']) ? 1 : -1;
	}
		
	/**
	* Returns a language given a language code
	* 
	* @param string $code
	* @return string
	*/
	private function language_from_code ($code) {
		if (!($languages = wp_cache_get ('mbsb_get_languages', get_locale()))) {
			$languages = array ('ara' => __('Arabic', MBSB), 'cym' => __('Welsh'), 'grc' => __('Greek', MBSB), 'eng' => __('English', MBSB), 'epo' => __('Esperanto', MBSB), 'fin' => __('Finnish', MBSB), 'fra' => __('French', MBSB), 'gla' => __('Gaelic'), 'ita' => __('Italian', MBSB), 'nld' => __('Dutch', MBSB), 'por' => __('Portuguese', MBSB), 'rus' => __('Russian', MBSB), 'spa' => __('Spanish', MBSB), 'zho' => __('Chinese', MBSB), 'deu' => __('German', MBSB), 'heb' => __('Hebrew', MBSB));
			$languages = apply_filters ('mbsb_language_codes', $languages);
			wp_cache_set ('mbsb_get_languages', get_locale());
		}
		if (isset($languages[$code]))
			return $languages[$code];
		else
			return $code;
	}
}
?>