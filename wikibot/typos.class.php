<?php
	// this class finds typos on pages
	class Typos {
		// gets all pages that contain typos and creates the wiki-code
		public static function getFormatedTypoList($lang) {
			$result = "";
			
			// if the language is valid
			if (in_array($lang, Registry::get('languages'))) {
				// get the array with all pages that contain typos
				$list = self::getNewTypoList($lang);
				foreach ($list as $page => $typos) {
					// add the page
					$result .= "* [[".$page."]]\n";
					foreach ($typos as $key => $typo) {
						// add all typos of that page
						$result .= "** ".$typo."\n";
					}
				}
			} else {
				throw new Exception('Invalid language code '.$lang);
			}
			
			return $result;
		}
		
		// collects all new pages with typos while keeping old, unfixed pages
		private static function getNewTypoList($lang) {
			// get previous list of typos
			if ($lang == 'en') {
				$page = new page('Team Fortress Wiki:Reports/Pages with typos');
			} else {
				$page = new page('Team Fortress Wiki:Reports/Pages with typos/'.$lang);				
			}
			$oldList = $page->getTextarea();

			// get list of typo exceptions
			$exceptions = self::getExceptions();
			
			// get each line
			$ex = explode("\n", $oldList);
			$oldPages = array();
			
			// for each line of the old page
			foreach ($ex as $key => $value) {
				// check whether the line is a link to a page
				if (substr(trim($value), 0, 2) == "* ") {
					$typos = array();
					$i = $key+1;
					// remove "* [[" and "]]" from the page-line so we get the name of the page
					$newKey = substr($value, 4);
					$newKey = substr($newKey, 0, strlen($newKey)-2);
					// select all of it's typos (=all lines until next page)
					while (isset($ex[$i]) && substr(trim($ex[$i]), 0, 2) == "**") {
						// Get the acutal typo ('''typo''' -> corrent) => typo
						$actualTypo = explode("'''", $ex[$i])[1];
						// If this typo is not marked as exception
						if (!isset($exceptions[$newKey]) || !in_array($actualTypo, $exceptions[$newKey])) {
							// remove the "** " before the typo then add it
							$typos[] = substr($ex[$i], 3);
						}
						$i++;
					}
					// Only if there are some typos that not on the exception list
					if (!empty($typos)) {
						// add everything to the typo array (oldPages[page] = typos)
						$oldPages[$newKey] = $typos;
					}
				}
			}

			// get all new typos
			$newPages = self::getNewTypos($lang);
			
			// delete pages that have been fixed in the meantime from the list
			foreach ($oldPages as $page => $value) {
				if (array_key_exists($page, $newPages)) {
					unset($oldPages[$page]);
				}
			}
			
			// delete pages that contain no typos
			foreach ($newPages as $page => $value) {
				if (empty($newPages[$page])) {
					unset($newPages[$page]);
				}
			}
			
			// merge the old pages with the new ones and return them
			return array_merge($newPages, $oldPages);
		}
		
		// gets all new typos
		private static function getNewTypos($lang) {
			// get all recently changed pages pagess
			$pages = Page::getRecentChanges($lang);

			// get list of typo exceptions
			$exceptions = self::getExceptions();
			
			$list = array();
			// for every recently changed page
			foreach ($pages as $key => $page) {
				// check whether the page contains a typo
				$typosList = self::checkPage($page);
				// if the page contains a typo
				if (!empty($typosList)) {
					$formatTypos = array();
					// for every typo found on that page
					foreach($typosList as $wrong => $correct) {
						// If this typo is not marked as exception
						if (!isset($exceptions[$page]) || !in_array($wrong, $exceptions[$page])) {
							// add new typos at the end of the list for this page
							$formatTypos[] = "'''".$wrong."''' -> ".$correct;
						}	
					}
					$list[$page] = $formatTypos;
				} else {
					// otherwise save that the page contains no typo (used to determine if a page has been fixed)
					$list[$page] = array();
				}
			}
			return $list;
		}
		
		// checks a given page for a typo
		private static function checkPage($pageName) {
			// split at / so we get the language-code
			$ex = explode('/', $pageName);
			// the language code is the last part of the page name
			$lang = end($ex);
			// if the page ends without a language code it's an english page
			if (!in_array($lang, Registry::get('languages'))) {
				$lang = 'en';
			}
			
			$page = new page($pageName);
			// get the text of the page
			$text = $page->getTextarea();
			//prepare the result string
			$result = "* [[".$pageName."]]";
			$typos = array();
			$previous = null;
			// for each possible typo
			foreach (Registry::get('typos_'.$lang) as $wrong => $correct) {
				// check whether the typo is present on the page and get its position
				$pos = strpos($text, $wrong);
				// if the typo occured
				if ($pos) {
					// get the character before the typo
					$prefix = substr($text,$pos-1,1);
					// get the character after the typo
					$suffix = substr($text,$pos+strlen($wrong),1);
					// list of characters that separate words
					$punctuation = array(' ', '.', ',', ':', ';', '"', '\'', '[', ']', '{', '}');
					// ignore typos that are a suffix or prefix of a word
					// example: "daed" does not mean "dead" when included in "daedalus" 
					if (in_array($prefix, $punctuation) && in_array($suffix, $punctuation)) {
						// override found typos that are a prefix of the current typo
						if ($previous != null && strpos(trim($wrong), trim($previous)) !== false) {
							unset($typos[$previous]);
						}
						$previous = $wrong;
						$typos[$wrong] = $correct;						
					}
				}
			}
			
			return $typos;
		}
		
		private static function getExceptions() {
			$page = new Page("User talk:BOTzement");
			$text = $page->getTextarea();
			$pattern = "~Start of typo exceptions(.*)End of typo exceptions~is";
			preg_match($pattern, $text, $matches);
				
			// get each line
			$ex = explode("\n", $matches[1]);
			$exceptions = array();
				
			// for each line of the old page
			foreach ($ex as $key => $value) {
				// check whether the line is a link to a page
				if (substr(trim($value), 0, 2) == "* " || substr(trim($value), 0, 2) == "*[") {
					$typos = array();
					$i = $key+1;
					// select all of it's typos (=all lines until next page)
					while (isset($ex[$i]) && substr(trim($ex[$i]), 0, 2) == "**") {
						// remove comment (which is put in brackets)
						$ex2 = explode("(", $ex[$i]);
						// remove the "** " before the typo then add it
						$typos[] = trim($ex2[0], '* ');
						$i++;
					}
					// remove "* [[" and "]]" from the page-line so we get the name of the page
					$newKey = trim($value, '*[] ');
					// add everything to the typo array (oldPages[wrong] = correct)
					$exceptions[$newKey] = $typos;
				}				
			}
			
			return $exceptions;
		}
	}
?>