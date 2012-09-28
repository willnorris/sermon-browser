<?php
/**
* Class that stores and parses Bible passages
* 
* @package SermonBrowser
* @subpackage passage
* @author Mark Barnes
*/
class mbsb_passages extends mbsb_mpspss_template {
	
	/**
	* True if the object contains passages, false otherwise
	* 
	* @var boolean
	*/
	public $present;
	
	/**
	* The passages in a human-friendly format
	* 
	* (e.g. "Matthew 5:1-12, Genes 3:16, 25")
	* @var string
	*/
	private $formatted;
	
	/**
	* An array of the individual passage objects that make up this group of passages
	* 
	* @var array
	*/
	private $passages;

	/**
	* Constructs the object
	* 
	* There are two ways of constructing an object:
	* 	(1) Pass a single string, which contains a human-friendly reference to be parsed and then stored
	* 	(2) Pass two strings, which are in machine-readable format, which are simple stored
	* 
	* @param string $start
	* @param mixed $end
	*/
	public function __construct ($start, $end = null) {
		if ($end === null)
			// This is a human-friendly reference (e.g. 'John 3:16; Rev 22')
			$verses = $this->parse_passages ($start);
		else {
			// This is in a machine readable format (e.g '44003016')
			foreach ($start as $s)
				if ($result = $this->convert_raw_format_to_array ($s))
					$verses [$result['index']]['start'] = $result ['result'];
			foreach ($end as $s)
				if ($result = $this->convert_raw_format_to_array ($s))
					$verses [$result['index']]['end'] = $result ['result'];
		}
		if (isset ($verses) && !is_wp_error($verses)) {
			ksort ($verses);
			foreach ($verses as $v)
				if (isset($v['start']) && isset($v['end']))
					$passages[] = new mbsb_single_passage ($v['start'], $v['end']);
		}
		if (isset($passages)) {
			$this->passages = $passages;
			$this->present = true;
		} else
			$this->present = false;
		$this->formatted = $this->get_formatted();
		$this->type = 'passages';
		$this->id = '';
	}
	
	/**
	* Converts the raw passage data into an associative array
	* 
	* The array has two keys, 'index' (its order), and 'result'. 'result' is an array with the keys 'book', 'chapter', and 'verse'
	* 
	* @param boolean|array - false on failure, the array on success
	*/
	private function convert_raw_format_to_array ($s) {
		if (strlen($s) === 13 && substr($s, 8, 1) == '.' && ($left = substr($s, 0, 7)) && ((integer)$left == $left) && ($right = substr($s, 9, 4)) && ((integer)$right == $right))
			return array ('index' => (integer)$right, 'result' => array ('book' => (integer)substr($s, 0, 2), 'chapter' => (integer)substr ($s, 2, 3), 'verse' => (integer)substr ($s, 5, 3)));
		else
			return false;
	}
	
	/**
	* Returns the parsed passages formatted for easy reading
	* 
	* @return string
	*/
	public function get_formatted() {
		if ($this->formatted !== null)
			return $this->formatted;
		if ($this->passages) {
			$output = '';
			foreach ($this->passages as $index => $p) {
				if (!isset($this->passages[$index-1]) || $this->passages[$index-1]->start['book'] != $p->start['book']) {
					if (isset($this->passages[$index-1]))
						$output .= '; ';
					$output .= $p->get_formatted();
				} elseif ($this->passages[$index-1]->start['chapter'] != $p->start['chapter'])
					$output .= __(',', MBSB).' '.$p->get_formatted(true, false);
				else
					$output .= __(',', MBSB).' '.$p->get_formatted(true, true);
			}
			return $output;
		}
	}
	
	/**
	* Returns a HTML formatted list of passages, with the booknames wrapped in links that filter the sermons on the admin sermons page
	* 
	* @return string
	*/
	public function get_admin_link() {
		return '<a href="'.admin_url('edit.php?post_type=mbsb_sermon&book=').'">'.esc_html($this->get_formatted()).'</a>';
	}
	
	public function get_passage_objects() {
		return $this->passages;
	}
	
	/**
	* Parses a string that could contain one or more Bible References
	* 
	* @param string $passage
	* @return array - A indexed array of references. Each reference is an associative array (keys are 'raw', 'start' and 'end'). 'raw' is the raw input for one reference. 'start' and 'end' are associative arrays with the keys 'book', 'chapter' and 'verse'
	*/
	private function parse_passages($raw_passages) {
		$passage = str_replace (__(',', MBSB), __(';', MBSB), $raw_passages);
		$passages = explode(__(';', MBSB), $passage);
		$processed = array();
		$count = 0;
		if (is_array($passages)) {
			foreach ($passages as $passage) {
				$passage = trim($passage);
				if ($passage != '') {
					$startend = explode ('-', $passage);
					$parsed_start = $this->parse_one (trim($startend[0]));
					// Deal with passages that only specify the book name. Assume whole book wanted.
					if ($parsed_start['chapter'] == 'wholebook') {
						$parsed_start['chapter'] = $parsed_start['verse'] = 1;
						$parsed_end['book'] = $parsed_start['book'];
						$chapters = array (1 => 50, 2 => 40, 3 => 27, 4 => 36, 5 => 34, 6 => 24, 7 => 21, 8 => 4, 9 => 31, 10 => 24, 11 => 22, 12 => 25, 13 => 29, 14 => 36, 15 => 10, 16 => 13, 17 => 10, 18 => 42, 19 => 150, 20 => 31, 21 => 12, 22 => 8, 23 => 66, 24 => 52, 25 => 5, 26 => 48, 27 => 12, 28 => 14, 29 => 3, 30 => 9, 31 => 1, 32 => 4, 33 => 7, 34 => 3, 35 => 3, 36 => 3, 37 => 2, 38 => 14, 39 => 4, 40 => 28, 41 => 16, 42 => 24, 43 => 21, 44 => 28, 45 => 16, 46 => 16, 47 => 13, 48 => 6, 49 => 6, 50 => 4, 51 => 4, 52 => 5, 53 => 3, 54 => 6, 55 => 4, 56 => 3, 57 => 1, 58 => 13, 59 => 5, 60 => 5, 61 => 3, 62 => 5, 63 => 1, 64 => 1, 65 => 1, 66 => 22);
						$parsed_end['chapter'] = $chapters[$parsed_start['book']];
						$parsed_end['verse'] = 'last';
					} else {
						// Deal with passages where the bookname is assumed from previous reference (e.g. Ex. 13:12, 15-19)
						if ($count != 0 && $parsed_start['book'] == '' && $parsed_start['chapter'] != '') {
							$parsed_start['book'] = $processed[$count-1]['end']['book'];
							if ($parsed_start['verse'] == '' && $processed[$count-1]['end']['verse'] != '') {
								$parsed_start['verse'] = $parsed_start['chapter'];
								$parsed_start['chapter'] = $processed[$count-1]['end']['chapter'];
							}
						}
						if (count($startend) === 1) {
							$parsed_end = $parsed_start; // Starting point only specified (e.g. Ex. 13:12 or Gen. 12)
							if ($parsed_start['verse'] == '')
								$parsed_end['verse'] = 'last'; // Single chapter (e.g. Gen. 12)
						} else
							$parsed_end = $this->parse_one (trim($startend[1]), $parsed_start); // Verse range (e.g. Ex. 13:12-15)
					}
					if ($parsed_start['book'] == '')
						$processed[$count] = new WP_Error(1, ('Could not determine Bible book for '.$passage));
					else {
						$single_chapter_books = array (31, 57, 63, 64); // Obadiah, Philemon, 2 John and 3 John
						if ($parsed_start['verse'] == '')
							if (in_array($parsed_start['book'], $single_chapter_books)) {
								$parsed_start['verse'] = $parsed_start['chapter'];
								$parsed_end['verse'] = $parsed_end['chapter'];
								$parsed_start['chapter'] = $parsed_end['chapter'] = 1;
							} else
								$parsed_start['verse'] = 1;
						if ($parsed_end['verse'] == 'last') {
							if (!isset($verses_per_chapter))
								$verses_per_chapter = $this->verses_per_chapter ();
							$parsed_end['verse'] = $verses_per_chapter[$parsed_end['book']][$parsed_end['chapter']];
						}
						if ($parsed_start['chapter'] < 1 | $parsed_start['verse'] < 1 | $parsed_end['chapter'] < 1 | $parsed_end['verse'] < 1)
							$processed[$count] = new WP_Error(2, 'Could not parse chapter and verse for '.$passage);
						elseif ($parsed_end['book'] < $parsed_start['book'] || ($parsed_end['book'] == $parsed_start['book'] && $parsed_end['chapter'] < $parsed_start['chapter']) || (($parsed_end['book'] == $parsed_start['book'] && $parsed_end['chapter'] == $parsed_start['chapter'] && $parsed_end['verse'] < $parsed_start['verse'])))
							$processed[$count] = new WP_Error(3, 'The end is before the start in '.$passage);
						else
							$processed[$count] = array('start' => $parsed_start, 'end' => $parsed_end); //Return parsed result.
					}
					$count++;
				}
			}
		}
		if (!empty($processed))
			return $processed;
		else
	    	return new WP_Error(4, 'No Bible references could be parsed in '.$raw_passages);
	}
	
	/**
	* Parses a single Bible reference
	* 
	* @param string $passage
	* @param mixed $previous - set to FALSE if we're parsing the first part of a range, or to an associative array (keys are 'chapter' and 'verse') if we're pasing the second part of a range
	* @return array - associative array (keys are 'book', 'chapter' and 'verse') of integers (although 'chapter' can also be set to 'wholebook')
	*/
	private function parse_one ($passage, $previous = FALSE) {
		$books = $this->bible_books();
		if (preg_match('/[A-Za-z]/', $passage) !== 0) {
			//Search through arrays looking for bookname
			foreach ($books as $book_name => $book_index) {
				if ($book_name != 'mbsb_index') {
					$len = strlen($book_name);
					if ($len <= strlen($passage) && substr_compare($passage, $book_name, 0, $len, TRUE) === 0 && preg_match('/[^A-Za-z]/', substr($passage, $len, 1)) !== 0) {
						$passage = trim(ltrim(substr($passage, $len), '.'));
						$chapterverse = $this->parse_chapter_verse ($passage, $previous);
						return array('book' => $book_index, 'chapter' => $chapterverse['chapter'], 'verse' => $chapterverse['verse']); //Bookname found, return result
					}
					elseif (strcasecmp($passage, $book_name) === 0)
						return array('book' => $book_index, 'chapter' => 'wholebook'); //Bookname found, no reference supplied, whole book assumed
				}
			}
			return array('book' => '', 'chapter' => '', 'verse' => ''); //Bookname not found, but alpha characters remain. Return blank.
		}
		$passage = trim(ltrim($passage, '.'));
		$chapterverse = $this->parse_chapter_verse ($passage, $previous);
		if ($previous === FALSE) 
			return array('book' => '', 'chapter' => $chapterverse['chapter'], 'verse' => $chapterverse['verse']); // Assume bookname implied by previous reference (e.g. Ex. 13:12, 19)
		else
			return array('book' => $previous['book'], 'chapter' => $chapterverse['chapter'], 'verse' => $chapterverse['verse']); // Assume bookname implied by first part of current reference (e.g. Ex. 13:12-19)
	}
	
	/**
	* Parses the numerical part of a Bible reference
	* 
	* @param string $passage
	* @param mixed $previous - set to FALSE if we're parsing the first part of a range, or to an associative array (keys are 'chapter' and 'verse') if we're pasing the second part of a range
	* @return array - associative array (keys are 'chapter' and 'verse') of integers
	*/
	private function parse_chapter_verse ($passage, $previous) {
		$period = strpos($passage, '.');
		$colon = strpos($passage, __(':', MBSB));
		if ($period === FALSE && $colon === FALSE)
			if ($previous)
				if ($previous['verse'] == '')
					return (array('chapter' => (int)$passage, 'verse' => 'last')); // Single number found, second part, no verse specified, therefore return several chapters (e.g. Ex. 13-19)
				else
					return (array('chapter' => $previous['chapter'], 'verse' => (int)$passage)); // Single number found, second part, therefore assume chapter from previous (e.g. Ex. 13:12-19)
			else
				return (array('chapter' => (int)$passage, 'verse' => '')); // Single number found, first part, therefore just return chapter (e.g. Ex. 13)
		else {
			if ($colon === FALSE)
				$colon = $period;
			$chapter = substr($passage, 0, $colon);
			$verse = substr($passage, $colon+1);
			if ($verse == '' && $previous)
				return (array('chapter' => $previous['chapter'], 'verse' => (int)$chapter)); // Single number found, second part, therefore assume chapter from previous (e.g. Ex. 13:12-19)
			else
				return (array('chapter' => (int)$chapter, 'verse' => (int)$verse)); // Two numbers found, return chapter and verse (e.g. Ex. 13:12)
		}
	}
	
	/**
	* Returns an array of valid Bible book names with alternative abbreviated forms and an index
	* 
	* Can be persistently cached by caching plugins.
	* 
	* @return array
	*/
	public static function bible_books() {
		$mbsb_bible_books = wp_cache_get ('mbsb_bible_books', get_bloginfo('language'));
		if (!$mbsb_bible_books){
			$books = array(__('Genesis, Gen, Gn', MBSB), __('Exodus, Exod, Ex', MBSB), __('Leviticus, Lev, Lv', MBSB), __('Numbers, Num, Nm', MBSB), __('Deuteronomy, Deut, Dt', MBSB), __('Joshua, Josh, Jo', MBSB), __('Judges, Judg, Jgs', MBSB), __('Ruth, Ru', MBSB), __('1 Samuel, 1 Sam, 1Sam, 1Sm, 1 Sm', MBSB), __('2 Samuel, 2 Sam, 2Sam, 2 Sm, 2Sm', MBSB), __('1 Kings, 1 Kgs, 1Kgs', MBSB), __('2 Kings, 2 Kgs, 2Kgs', MBSB), __('1 Chronicles, 1 Chron, 1Chron, 1 Chr, 1Chr', MBSB), __('2 Chronicles, 2 Chron, 2Chron, 2 Chr, 2Chr',MBSB), __('Ezra, Ezr', MBSB), __('Nehemiah, Neh', MBSB), __('Esther, Est', MBSB), __('Job, Jb', MBSB), __('Psalm, Psalms, Pss, Psa, Ps', MBSB), __('Proverbs, Prov, Prv', MBSB), __('Ecclesiastes, Eccles, Eccl', MBSB), __('Song of Solomon, Song of Songs, Song of Sol, Songs, Sg', MBSB), __('Isaiah, Isa, Is', MBSB), __('Jeremiah, Jer', MBSB), __('Lamentations, Lam', MBSB), __('Ezekiel, Ezek, Ezk, Ez', MBSB), __('Daniel, Dan, Dn', MBSB), __('Hosea, Hos', MBSB), __('Joel, Jl', MBSB), __('Amos, Am', MBSB), __('Obadiah, Obad, Ob', MBSB), __('Jonah, Jon', MBSB), __('Micah, Mic, Mi', MBSB), __('Nahum, Nah, Na', MBSB), __('Habakkuk, Hab, Hb', MBSB), __('Zephaniah, Zeph, Zep', MBSB), __('Haggai, Hag, Hg', MBSB), __('Zechariah, Zech, Zec', MBSB), __('Malachi, Mal', MBSB), __('Matthew, Matt, Mt', MBSB), __('Mark, Mk', MBSB), __('Luke, Lk', MBSB), __('John, Jn', MBSB), __('Acts', MBSB), __('Romans, Rom', MBSB), __('1 Corinthians, 1 Cor, 1Cor', MBSB), __('2 Corinthians, 2 Cor, 2Cor', MBSB), __('Galatians, Gal', MBSB), __('Ephesians, Eph', MBSB), __('Philippians, Phil', MBSB), __('Colossians, Col', MBSB), __('1 Thessalonians, 1 Thess, 1Thess, 1 Thes, 1Thes, 1 Th, 1Th', MBSB), __('2 Thessalonians, 2 Thess, 2Thess, 2 Thes, 2Thes, 2 Th, 2Th', MBSB), __('1 Timothy, 1 Tim, 1Tim, 1 Ti, 1Ti, 1 Tm, 1Tm', MBSB), __('2 Timothy, 2 Tim, 2Tim, 2 Ti, 2Ti, 2 Tm, 2Tm', MBSB), __('Titus, Tit, Ti', MBSB), __('Philemon, Philem, Phlm', MBSB), __('Hebrews, Heb', MBSB), __('James, Jas', MBSB), __('1 Peter, 1Peter, 1 Pet, 1Pet, 1 Pt, 1Pt', MBSB), __('2 Peter, 2Peter, 2 Pet, 2Pet, 2 Pt, 2Pt', MBSB), __('1 John, 1John, 1 Jn, 1Jn', MBSB), __('2 John, 2John, 2 Jn, 2Jn', MBSB), __('3 John, 3John, 3 Jn, 3Jn', MBSB), __('Jude', MBSB), __('Revelation, Rev, Rv', MBSB));
			foreach ($books as $num => $names) {
				$num++;
				$names = explode (',', $names);
				$mbsb_bible_books['mbsb_index'][$num] = trim($names[0]);
				foreach ($names as $name)
					$mbsb_bible_books[trim($name)] = $num;
			}
			wp_cache_set ('mbsb_bible_books', $mbsb_bible_books, get_bloginfo('language'));
		}
		return $mbsb_bible_books;
	}

	/**
	* Returns a multi-dimensional array containing the number of verses in each chapter of the Bible.
	* 
	* @return array
	*/
	private function verses_per_chapter () {
		$a = wp_cache_get ('mbsb_verses_per_chapter');
		if (!$a) {
			$a[1]=array(1=>31,2=>25,3=>24,4=>26,5=>32,6=>22,7=>24,8=>22,9=>29,10=>32,11=>32,12=>20,13=>18,14=>24,15=>21,16=>16,17=>27,18=>33,19=>38,20=>18,21=>34,22=>24,23=>20,24=>67,25=>34,26=>35,27=>46,28=>22,29=>35,30=>43,31=>55,32=>32,33=>20,34=>31,35=>29,36=>43,37=>36,38=>30,39=>23,40=>23,41=>57,42=>38,43=>34,44=>34,45=>28,46=>34,47=>31,48=>22,49=>33,50=>26);
			$a[2]=array(1=>22,2=>25,3=>22,4=>31,5=>23,6=>30,7=>25,8=>32,9=>35,10=>29,11=>10,12=>51,13=>22,14=>31,15=>27,16=>36,17=>16,18=>27,19=>25,20=>26,21=>36,22=>31,23=>33,24=>18,25=>40,26=>37,27=>21,28=>43,29=>46,30=>38,31=>18,32=>35,33=>23,34=>35,35=>35,36=>38,37=>29,38=>31,39=>43,40=>38);
			$a[3]=array(1=>17,2=>16,3=>17,4=>35,5=>19,6=>30,7=>38,8=>36,9=>24,10=>20,11=>47,12=>8,13=>59,14=>57,15=>33,16=>34,17=>16,18=>30,19=>37,20=>27,21=>24,22=>33,23=>44,24=>23,25=>55,26=>46,27=>34);
			$a[4]=array(1=>54,2=>34,3=>51,4=>49,5=>31,6=>27,7=>89,8=>26,9=>23,10=>36,11=>35,12=>16,13=>33,14=>45,15=>41,16=>50,17=>13,18=>32,19=>22,20=>29,21=>35,22=>41,23=>30,24=>25,25=>18,26=>65,27=>23,28=>31,29=>40,30=>16,31=>54,32=>42,33=>56,34=>29,35=>34,36=>13);
			$a[5]=array(1=>46,2=>37,3=>29,4=>49,5=>33,6=>25,7=>26,8=>20,9=>29,10=>22,11=>32,12=>32,13=>18,14=>29,15=>23,16=>22,17=>20,18=>22,19=>21,20=>20,21=>23,22=>30,23=>25,24=>22,25=>19,26=>19,27=>26,28=>68,29=>29,30=>20,31=>30,32=>52,33=>29,34=>12);
			$a[6]=array(1=>18,2=>24,3=>17,4=>24,5=>15,6=>27,7=>26,8=>35,9=>27,10=>43,11=>23,12=>24,13=>33,14=>15,15=>63,16=>10,17=>18,18=>28,19=>51,20=>9,21=>45,22=>34,23=>16,24=>33);
			$a[7]=array(1=>36,2=>23,3=>31,4=>24,5=>31,6=>40,7=>25,8=>35,9=>57,10=>18,11=>40,12=>15,13=>25,14=>20,15=>20,16=>31,17=>13,18=>31,19=>30,20=>48,21=>25);
			$a[8]=array(1=>22,2=>23,3=>18,4=>22);
			$a[9]=array(1=>28,2=>36,3=>21,4=>22,5=>12,6=>21,7=>17,8=>22,9=>27,10=>27,11=>15,12=>25,13=>23,14=>52,15=>35,16=>23,17=>58,18=>30,19=>24,20=>42,21=>15,22=>23,23=>29,24=>22,25=>44,26=>25,27=>12,28=>25,29=>11,30=>31,31=>13);
			$a[10]=array(1=>27,2=>32,3=>39,4=>12,5=>25,6=>23,7=>29,8=>18,9=>13,10=>19,11=>27,12=>31,13=>39,14=>33,15=>37,16=>23,17=>29,18=>33,19=>43,20=>26,21=>22,22=>51,23=>39,24=>25);
			$a[11]=array(1=>53,2=>46,3=>28,4=>34,5=>18,6=>38,7=>51,8=>66,9=>28,10=>29,11=>43,12=>33,13=>34,14=>31,15=>34,16=>34,17=>24,18=>46,19=>21,20=>43,21=>29,22=>53);
			$a[12]=array(1=>18,2=>25,3=>27,4=>44,5=>27,6=>33,7=>20,8=>29,9=>37,10=>36,11=>21,12=>21,13=>25,14=>29,15=>38,16=>20,17=>41,18=>37,19=>37,20=>21,21=>26,22=>20,23=>37,24=>20,25=>30);
			$a[13]=array(1=>54,2=>55,3=>24,4=>43,5=>26,6=>81,7=>40,8=>40,9=>44,10=>14,11=>47,12=>40,13=>14,14=>17,15=>29,16=>43,17=>27,18=>18,19=>18,20=>8,21=>30,22=>19,23=>32,24=>31,25=>31,26=>32,27=>34,28=>21,29=>30);
			$a[14]=array(1=>17,2=>18,3=>17,4=>22,5=>14,6=>42,7=>22,8=>18,9=>31,10=>19,11=>23,12=>16,13=>22,14=>15,15=>19,16=>14,17=>19,18=>34,19=>11,20=>37,21=>20,22=>12,23=>21,24=>27,25=>28,26=>23,27=>9,28=>27,29=>36,30=>27,31=>21,32=>33,33=>25,34=>33,35=>27,36=>23);
			$a[15]=array(1=>11,2=>70,3=>13,4=>24,5=>17,6=>22,7=>28,8=>36,9=>15,10=>44);
			$a[16]=array(1=>11,2=>20,3=>32,4=>23,5=>19,6=>19,7=>73,8=>18,9=>38,10=>39,11=>36,12=>47,13=>31);
			$a[17]=array(1=>22,2=>23,3=>15,4=>17,5=>14,6=>14,7=>10,8=>17,9=>32,10=>3);
			$a[18]=array(1=>22,2=>13,3=>26,4=>21,5=>27,6=>30,7=>21,8=>22,9=>35,10=>22,11=>20,12=>25,13=>28,14=>22,15=>35,16=>22,17=>16,18=>21,19=>29,20=>29,21=>34,22=>30,23=>17,24=>25,25=>6,26=>14,27=>23,28=>28,29=>25,30=>31,31=>40,32=>22,33=>33,34=>37,35=>16,36=>33,37=>24,38=>41,39=>30,40=>24,41=>34,42=>17);
			$a[19]=array(1=>6,2=>12,3=>8,4=>8,5=>12,6=>10,7=>17,8=>9,9=>20,10=>18,11=>7,12=>8,13=>6,14=>7,15=>5,16=>11,17=>15,18=>50,19=>14,20=>9,21=>13,22=>31,23=>6,24=>10,25=>22,26=>12,27=>14,28=>9,29=>11,30=>12,31=>24,32=>11,33=>22,34=>22,35=>28,36=>12,37=>40,38=>22,39=>13,40=>17,41=>13,42=>11,43=>5,44=>26,45=>17,46=>11,47=>9,48=>14,49=>20,50=>23,51=>19,52=>9,53=>6,54=>7,55=>23,56=>13,57=>11,58=>11,59=>17,60=>12,61=>8,62=>12,63=>11,64=>10,65=>13,66=>20,67=>7,68=>35,69=>36,70=>5,71=>24,72=>20,73=>28,74=>23,75=>10,76=>12,77=>20,78=>72,79=>13,80=>19,81=>16,82=>8,83=>18,84=>12,85=>13,86=>17,87=>7,88=>18,89=>52,90=>17,91=>16,92=>15,93=>5,94=>23,95=>11,96=>13,97=>12,98=>9,99=>9,100=>5,101=>8,102=>28,103=>22,104=>35,105=>45,106=>48,107=>43,108=>13,109=>31,110=>7,111=>10,112=>10,113=>9,114=>8,115=>18,116=>19,117=>2,118=>29,119=>176,120=>7,121=>8,122=>9,123=>4,124=>8,125=>5,126=>6,127=>5,128=>6,129=>8,130=>8,131=>3,132=>18,133=>3,134=>3,135=>21,136=>26,137=>9,138=>8,139=>24,140=>13,141=>10,142=>7,143=>12,144=>15,145=>21,146=>10,147=>20,148=>14,149=>9,150=>6);
			$a[20]=array(1=>33,2=>22,3=>35,4=>27,5=>23,6=>35,7=>27,8=>36,9=>18,10=>32,11=>31,12=>28,13=>25,14=>35,15=>33,16=>33,17=>28,18=>24,19=>29,20=>30,21=>31,22=>29,23=>35,24=>34,25=>28,26=>28,27=>27,28=>28,29=>27,30=>33,31=>31);
			$a[21]=array(1=>18,2=>26,3=>22,4=>16,5=>20,6=>12,7=>29,8=>17,9=>18,10=>20,11=>10,12=>14);
			$a[22]=array(1=>17,2=>17,3=>11,4=>16,5=>16,6=>13,7=>13,8=>14);
			$a[23]=array(1=>31,2=>22,3=>26,4=>6,5=>30,6=>13,7=>25,8=>22,9=>21,10=>34,11=>16,12=>6,13=>22,14=>32,15=>9,16=>14,17=>14,18=>7,19=>25,20=>6,21=>17,22=>25,23=>18,24=>23,25=>12,26=>21,27=>13,28=>29,29=>24,30=>33,31=>9,32=>20,33=>24,34=>17,35=>10,36=>22,37=>38,38=>22,39=>8,40=>31,41=>29,42=>25,43=>28,44=>28,45=>25,46=>13,47=>15,48=>22,49=>26,50=>11,51=>23,52=>15,53=>12,54=>17,55=>13,56=>12,57=>21,58=>14,59=>21,60=>22,61=>11,62=>12,63=>19,64=>12,65=>25,66=>24);
			$a[24]=array(1=>19,2=>37,3=>25,4=>31,5=>31,6=>30,7=>34,8=>22,9=>26,10=>25,11=>23,12=>17,13=>27,14=>22,15=>21,16=>21,17=>27,18=>23,19=>15,20=>18,21=>14,22=>30,23=>40,24=>10,25=>38,26=>24,27=>22,28=>17,29=>32,30=>24,31=>40,32=>44,33=>26,34=>22,35=>19,36=>32,37=>21,38=>28,39=>18,40=>16,41=>18,42=>22,43=>13,44=>30,45=>5,46=>28,47=>7,48=>47,49=>39,50=>46,51=>64,52=>34);
			$a[25]=array(1=>22,2=>22,3=>66,4=>22,5=>22);
			$a[26]=array(1=>28,2=>10,3=>27,4=>17,5=>17,6=>14,7=>27,8=>18,9=>11,10=>22,11=>25,12=>28,13=>23,14=>23,15=>8,16=>63,17=>24,18=>32,19=>14,20=>49,21=>32,22=>31,23=>49,24=>27,25=>17,26=>21,27=>36,28=>26,29=>21,30=>26,31=>18,32=>32,33=>33,34=>31,35=>15,36=>38,37=>28,38=>23,39=>29,40=>49,41=>26,42=>20,43=>27,44=>31,45=>25,46=>24,47=>23,48=>35);
			$a[27]=array(1=>21,2=>49,3=>30,4=>37,5=>31,6=>28,7=>28,8=>27,9=>27,10=>21,11=>45,12=>13);
			$a[28]=array(1=>11,2=>23,3=>5,4=>19,5=>15,6=>11,7=>16,8=>14,9=>17,10=>15,11=>12,12=>14,13=>16,14=>9);
			$a[29]=array(1=>20,2=>32,3=>21);
			$a[30]=array(1=>15,2=>16,3=>15,4=>13,5=>27,6=>14,7=>17,8=>14,9=>15);
			$a[31]=array(1=>21);
			$a[32]=array(1=>17,2=>10,3=>10,4=>11);
			$a[33]=array(1=>16,2=>13,3=>12,4=>13,5=>15,6=>16,7=>20);
			$a[34]=array(1=>15,2=>13,3=>19);
			$a[35]=array(1=>17,2=>20,3=>19);
			$a[36]=array(1=>18,2=>15,3=>20);
			$a[37]=array(1=>15,2=>23);
			$a[38]=array(1=>21,2=>13,3=>10,4=>14,5=>11,6=>15,7=>14,8=>23,9=>17,10=>12,11=>17,12=>14,13=>9,14=>21);
			$a[39]=array(1=>14,2=>17,3=>18,4=>6);
			$a[40]=array(1=>25,2=>23,3=>17,4=>25,5=>48,6=>34,7=>29,8=>34,9=>38,10=>42,11=>30,12=>50,13=>58,14=>36,15=>39,16=>28,17=>27,18=>35,19=>30,20=>34,21=>46,22=>46,23=>39,24=>51,25=>46,26=>75,27=>66,28=>20);
			$a[41]=array(1=>45,2=>28,3=>35,4=>41,5=>43,6=>56,7=>37,8=>38,9=>50,10=>52,11=>33,12=>44,13=>37,14=>72,15=>47,16=>20);
			$a[42]=array(1=>80,2=>52,3=>38,4=>44,5=>39,6=>49,7=>50,8=>56,9=>62,10=>42,11=>54,12=>59,13=>35,14=>35,15=>32,16=>31,17=>37,18=>43,19=>48,20=>47,21=>38,22=>71,23=>56,24=>53);
			$a[43]=array(1=>51,2=>25,3=>36,4=>54,5=>47,6=>71,7=>53,8=>59,9=>41,10=>42,11=>57,12=>50,13=>38,14=>31,15=>27,16=>33,17=>26,18=>40,19=>42,20=>31,21=>25);
			$a[44]=array(1=>26,2=>47,3=>26,4=>37,5=>42,6=>15,7=>60,8=>40,9=>43,10=>48,11=>30,12=>25,13=>52,14=>28,15=>41,16=>40,17=>34,18=>28,19=>41,20=>38,21=>40,22=>30,23=>35,24=>27,25=>27,26=>32,27=>44,28=>31);
			$a[45]=array(1=>32,2=>29,3=>31,4=>25,5=>21,6=>23,7=>25,8=>39,9=>33,10=>21,11=>36,12=>21,13=>14,14=>23,15=>33,16=>27);
			$a[46]=array(1=>31,2=>16,3=>23,4=>21,5=>13,6=>20,7=>40,8=>13,9=>27,10=>33,11=>34,12=>31,13=>13,14=>40,15=>58,16=>24);
			$a[47]=array(1=>24,2=>17,3=>18,4=>18,5=>21,6=>18,7=>16,8=>24,9=>15,10=>18,11=>33,12=>21,13=>14);
			$a[48]=array(1=>24,2=>21,3=>29,4=>31,5=>26,6=>18);
			$a[49]=array(1=>23,2=>22,3=>21,4=>32,5=>33,6=>24);
			$a[50]=array(1=>30,2=>30,3=>21,4=>23);
			$a[51]=array(1=>29,2=>23,3=>25,4=>18);
			$a[52]=array(1=>10,2=>20,3=>13,4=>18,5=>28);
			$a[53]=array(1=>12,2=>17,3=>18);
			$a[54]=array(1=>20,2=>15,3=>16,4=>16,5=>25,6=>21);
			$a[55]=array(1=>18,2=>26,3=>17,4=>22);
			$a[56]=array(1=>16,2=>15,3=>15);
			$a[57]=array(1=>25);
			$a[58]=array(1=>14,2=>18,3=>19,4=>16,5=>14,6=>20,7=>28,8=>13,9=>28,10=>39,11=>40,12=>29,13=>25);
			$a[59]=array(1=>27,2=>26,3=>18,4=>17,5=>20);
			$a[60]=array(1=>25,2=>25,3=>22,4=>19,5=>14);
			$a[61]=array(1=>21,2=>22,3=>18);
			$a[62]=array(1=>10,2=>29,3=>24,4=>21,5=>21);
			$a[63]=array(1=>13);
			$a[64]=array(1=>14);
			$a[65]=array(1=>25);
			$a[66]=array(1=>20,2=>29,3=>22,4=>11,5=>14,6=>17,7=>17,8=>13,9=>21,10=>11,11=>19,12=>17,13=>18,14=>20,15=>8,16=>21,17=>18,18=>24,19=>21,20=>15,21=>27,22=>21);
			wp_cache_set ('mbsb_verses_per_chapter', $a);
		}
		return $a;
	}

	/**
	* Returns the frontend output for all the passages
	* 
	* @return atring
	*/
	public function get_output () {
		if ($this->passages) {
			if (mbsb_get_option('allow_user_to_change_bible') && !mbsb_get_option ('use_embedded_bible_'.get_locale())) {
				$output = $this->do_div (mbsb_get_bible_list_dropdown(), 'bible_dropdown');
			} else
				$output = '';
			$preferred_version = mbsb_get_preferred_version();
			$output .= $this->do_div ($this->get_text_output($preferred_version), 'text');
			return $this->do_div ($output, 'wrap');
		}
	}
	
	/**
	* Returns the Bible text output for all the passages
	* 
	* @param string $version - the Bible version to use (optional, defaults to the version specified in options)
	* @return string
	*/
	public function get_text_output($version = '') {
		if ($version == '')
			$version = mbsb_get_preferred_version();
		$bible = mbsb_get_bible_details($version);
		$output = '';
		$c = count ($this->passages);
		foreach ($this->passages as $index => $p) {
			if ($c > 1 && $version != 'esv')
				$output .= $this->do_div($p->formatted, "heading_{$index}", 'passage_heading');
			$output .= $this->do_div ($p->get_bible_text($version), "body_{$index}", "passage_body {$bible['service']} {$bible['service']}_{$version}");
		}
		if (mbsb_get_option ('use_embedded_bible_'.get_locale())) {
			$text = esc_html(sprintf(__('Powered by %s', MBSB), 'SermonBrowser'));
			$output .= $this->do_div('<a href="http://www.sermonbrowser.com/"><img src="'.mbsb_plugins_url('images/powered-by.png').'" alt="'.$text.'" title="'.$text.'"/>', 'powered_by', 'powered_by sermonbrowser');
		} elseif ($bible['service'] == 'biblia') 
			$output .= $this->do_div('<a href="http://biblia.com/"><img src="http://api.biblia.com/docs/media/PoweredByBiblia.png" alt="'.sprintf(__('Powered by %s', MBSB), 'Biblia.com').'"/><a href="http://www.sermonbrowser.com/"><img src="'.mbsb_plugins_url('images/powered-by.png').'" alt="'.sprintf(__('Powered by %s', MBSB), 'SermonBrowser').'"/>', 'powered_by', 'powered_by sermonbrowser');
		elseif ($bible['service'] == 'biblesearch') 
			$output .= $this->do_div(sprintf(__('Powered by %s and %s.', MBSB), '<a href="http://bibles.org/">BibleSearch</a>', '<a href="http://www.sermonbrowser.com">SermonBrowser</a>'), 'powered_by', 'powered_by '.$bible['service']);
		elseif ($bible ['service'] == 'esv')
			$output .= $this->do_div (sprintf(__('Powered by %s and the %s API.', MBSB), '<a href="http://www.sermonbrowser.com">SermonBrowser</a>', '<a href="http://www.esv.org/">ESV</a>'), 'powered_by', 'powered_by esv');
		return $output;
	}
}
?>