<?php 
// this class finds broken external links
class ExternalLinks {
	// returns all missing pages for a language in wiki code
	public static function getHttpCode($url) {
		$url = htmlspecialchars_decode($url);
		$handle = curl_init($url);
		curl_setopt($handle,  CURLOPT_RETURNTRANSFER, TRUE);
		// Get the HTML or whatever is linked in $url
		$response = curl_exec($handle);
		// Get status code
		$httpCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);
		// Close the handle
		curl_close($handle);
		// Return the status code
		return $httpCode;
	}
	
	public static function getLinksOfPage($page) {
		$text = $page->getTextarea();
		$pattern = "~\[https?://(.*?)( |\])~i";
		preg_match_all($pattern, $text, $matches);
		
		$result = array();
		foreach ($matches[0] as $match) {
			// Remove first and last character ( [] )
			$result[] = substr($match, 1, -1);
		}
		return $result;
	}
	
	public static function getOldList() {
		$page = new Page("Team_Fortress_Wiki:Reports/BrokenLinks");
		$text = $page->getTextarea();
		$text = urldecode($text);
		
		
		// get each line
		$ex = explode("\n", $text);
		$pages = array();
				
		// for each line of the old page
		foreach ($ex as $key => $value) {
			// check whether the line is a link to a page
			if (substr(trim($value), 0, 2) == "* " || substr(trim($value), 0, 2) == "*[") {
				$links = array();
				$i = $key+1;
				// select all of it's links (=all lines until next page)
				while (isset($ex[$i]) && substr(trim($ex[$i]), 0, 2) == "**") {
						// remove error code (which is put in brackets)
						$ex2 = explode("(", $ex[$i]);
						// remove the "** " before the link then add it
						$links[] = trim($ex2[0], '* ');
						$i++;
				}
				// remove "* [[" and "]]" from the page-line so we get the name of the page
				$newKey = trim($value, '*[] ');
				// add everything to the link array (pages[page] = link)
				$pages[$newKey] = $links;
			}				
		}	
		return $pages;	
	}
	
	public static function removeFixedLinks() {
		$list = self::getOldList();				
		$newList = $list;
		
		foreach ($list as $page => $links) {
			// Get content of current page to see if the link is still on the page
			$currentPage = new Page($page);
			$text = $currentPage->getTextarea();
			// For each broken link on the page
			foreach ($links as $link) {
				// Check if it's still there
				if (strpos($text, $link) === false) {
					// If not, delete it from the list
					if(($index = array_search($link, $links)) !== false) {
						unset($newList[$page][$index]);
					}
				}
			}
			// Also remove the ones that are no longer broken
			foreach ($links as $key => $link) {
				$code = self::getHttpCode($link);
				// Check if it's broken (400 and 500 errors)
				if ($code < 400 || $code >= 600) {
					// If not, delete it from the list
					if(($index = array_search($link, $links)) !== false) {
						unset($newList[$page][$index]);
					}
				// Otherwise get the new error code
				} else {
					// But only if it's linked on the page
					if (array_key_exists($key, $newList[$page])) {
						$newList[$page][$key] = array($link, $code);
					}
				}
			}
			// Remove pages where all broken links have been fixed or are online again
			if (empty($newList[$page])) {
				unset($newList[$page]);
			}			
		}
		
		// Generate the text output
		$output = "";
		foreach ($newList as $page => $links) {
			$output .= "* [[".$page."]]\n";
			foreach ($links as $link) {
				$output .= "** ".$link[0]." (Error ".$link[1].": ".self::getCodeMessage($link[1]).")\n";
			}
		}
		return $output;
	}
	
	public static function addBrokenLink() {
		$page = Page::getRandomPage();
		$links = self::getLinksOfPage($page);
		$output = "";
		foreach ($links as $link) {
			$code = self::getHttpCode($link);
			// Check if it's broken (400 and 500 errors) ignore 503 (service unavailable)
			if ($code >= 400 && $code <= 599 && $code != 503) {
				$output .= "** ".$link." (Error ".$code.": ".self::getCodeMessage($code).")\n";
			}
		}
		if (!empty($output)) {
			$output = "*[[".$page->getName()."]]\n".$output;
		}
		// Remove last \n
		if (substr(trim($value), -2) == "\n") {
			$output = substr(trim($value), 0, -2);
		}
		return $output;
	}
	
	public static function getCodeMessage($code) {
		$messages = array();
		$messages[400] = "Bad Request";
		$messages[401] = "Unauthorized";
		$messages[402] = "Payment Required";
		$messages[403] = "Forbidden";
		$messages[404] = "Not Found";
		$messages[405] = "Method Not Allowed";
		$messages[406] = "Not Acceptable";
		$messages[407] = "Proxy Authentication Required";
		$messages[408] = "Request Time-out";
		$messages[409] = "Conflict";
		$messages[410] = "Gone";
		$messages[411] = "Length Required";
		$messages[412] = "Precondition Failed";
		$messages[413] = "Request Entity Too Large";
		$messages[414] = "Request-URL Too Long";
		$messages[415] = "Unsupported Media Type";
		$messages[416] = "Requested range not satisfiable";
		$messages[417] = "Expectation Failed";
		$messages[429] = "Too Many Requests";
		$messages[431] = "Request Header Fields Too Large";
		
		$messages[500] = "Internal Server Error";
		$messages[501] = "Not Implemented";
		$messages[502] = "Bad Gateway";
		$messages[503] = "Service Unavailable";
		$messages[504] = "Gateway Time-out";
		$messages[505] = "HTTP Version not supported";
		$messages[506] = "Variant Also Negotiates";
		$messages[507] = "Insufficient Storage";
		$messages[508] = "Loop Detected";
		$messages[509] = "Bandwidth Limit Exceeded";
		$messages[510] = "Not Extended";
		
		if (array_key_exists($code, $messages)) {
			return $messages[$code];
		} else {
			return "";
		}
	}
}
?>