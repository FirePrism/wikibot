<?php 
	// this class finds missing translations
	class TopMissing {
		// returns all missing pages for a language in wiki code
		public static function getFormatedTopMissingPages($lang) {
			$result = "";
			// if the language is valid
			if (in_array($lang, Registry::get('languages')) && $lang != "en") {
				// get the array containing all missing languages for this language
				$pages = self::getTopMissingPages($lang);
				// for each element of the array
				foreach ($pages as $page => $numLinks) {
					// mark recent additions with the "NEW" tag
					if ($numLinks == -1) {
						$result .= "# [[".$page."]] '''NEW!''' ([[".$page."/".$lang."|create]])\n";
					// show the number of links for the other pages
					} else {
						$result .= "# [[".$page."]] ('''".$numLinks."''' [[Special:WhatLinksHere/".$page."/".$lang."|links]]) ([[".$page."/".$lang."|create]])\n";
					}
				}
			} else {
				throw new Exception('Invalid language code '.$lang);
			}
			return $result;
		}
		
		// returns and all missing pages for a language ordered by the number of links
		private static function getTopMissingPages($lang) {
			$missing = array();
			$recent = array();
			// get all missing pages for the language
			$pages = topMissing::filterPages(self::getAllMissingPages($lang));
			// add recent additions and mark them (#links = -1)
			foreach ($pages as $page) {
				foreach(Registry::get('recentAdditions') as $line) {
					if (trim($page) == trim($line)) {
						// if the page is a element of the recent additions array
						if(($key = array_search($page, $pages)) !== false) {
							$recent[$page] = -1;
						}
					}
				}
			}
			
			// add most linked pages
			foreach ($pages as $pageName) {
				$page = new page($pageName."/".$lang);
				//get the total number of links to that page
				$numLinks = $page->getNumLinks();
				// if there are pages linking to that page
				if ($numLinks > 0) {
					// if the page is not already added as recent addition
					if (!isset($recent[$pageName])) {
						// add it with the number of ingoing links
						$missing[$pageName] = $numLinks;						
					}
				}
			}
			// sort descending
			arsort($missing);
			// show the recent additions at the top
			return array_merge($recent, $missing);
		}
		
		// returns all missing pages for a language (unfiltered)
		private static function getAllMissingPages($lang)  {
			// get all missing pages from the wiki
			$page = new page('Team_Fortress_Wiki:Reports/Missing_translations/'.$lang.'&section=1');
			$pages = $page->getTextarea();
			
			// only the name of the page is important
			$pattern = '#\(\[\[(.*?)/'.$lang.'\|create\]\]\)#';
			preg_match_all($pattern, $pages, $matches);
			return $matches[1];
		}
		
		// removes unimportant pages like disambiguations etc.
		private static function filterPages($pages) {
			// remove specific predefined pages (WebAPI...)
			foreach($pages as $page) {
				// for each page that shall be ignored
				foreach(Registry::get('missingPagesExceptions') as $line) {
					// remove
					$line = trim(str_replace(chr(65279), '', $line));
					// exclude several pages with one entry in the missingExceptions.txt
					// "WebAPI" exlcudes WebAPI/GetBadges, WebAPI/GetHeroes etc.
					if (startsWith($page, trim($line))) {
						// remove the entry from the input array
						if(($key = array_search($page, $pages)) !== false) {
							unset($pages[$key]);
						}
					}
				}
			}
			// remove disambiguation pages since they often make no sense on translated pages
			foreach($pages as $page) {
				foreach(Registry::get('disambiguations') as $line) {
					if ($page == trim($line)) {
						if(($key = array_search($page, $pages)) !== false) {
							unset($pages[$key]);
						}
					}
				}
			}
			return $pages;
		}
		
		public static function updateTrendingTopics() {
			$page = new Page('Template:Trending topics');
			$content = $page->getTextarea();
			preg_match_all("#\[\[(.*?)\{#", $content, $matches);
			
			$output = "\n";
			foreach ($matches[1] as $entry) {
				$output .= $entry."\n";
			}
			file_put_contents('cache/recentAdditions.txt', $output);
		}
	}
?>