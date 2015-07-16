<?php
	// this class represents a single wiki page
	class Page {
		private $name;
		
		public function __construct($name) {
			$this->name = str_replace(' ', '_', $name);
		}
		
		// send the data to the wiki
		private static function request($url) {
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.9) Gecko/20071025 Firefox/2.0.0.9');
			curl_setopt($ch, CURLOPT_URL, ($url));
			curl_setopt($ch, CURLOPT_ENCODING, 'UTF-8');
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_COOKIEFILE, Registry::get('cookies'));
			curl_setopt($ch, CURLOPT_COOKIEJAR, Registry::get('cookies'));
			curl_setopt($ch, CURLOPT_POST, 0);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
						
			return curl_exec($ch);
		}
		
		public static function getRandomPage() {
			$page = self::request("http://wiki.teamfortress.com/w/index.php?title=Special:Random&action=edit");
			$pattern = "#<title>Editing (.*?) - Official TF2 Wiki \| Official Team Fortress Wiki</title>#i";
			preg_match($pattern, $page, $matches);
			$result = new Page($matches[1]); 
			return $result;
		}
		
		// writes given text to the page
		public function write($content, $summary) {
			//Remove trailing \n
			$content = rtrim($content);
			
			// get the edit token
			$url = 'http://wiki.teamfortress.com/w/api.php?action=query&format=xml';
			$params = "&prop=info&intoken=edit&titles=".$this->name;
			$data = Login::httpRequest($url, $params);
			$xml = simplexml_load_string($data);
			$result = $xml->xpath('/api/query/pages/page[@edittoken]')[0];
			
			// get post data
			$wpEdittime = str_replace(array('-', ':', 'T', 'Z'), "", $result["touched"]);
			$wpStarttime = str_replace(array('-', ':', 'T', 'Z'), "", $result["starttimestamp"]);
			$wpEditToken = urlencode($result["edittoken"]);
			
			// set content, summary etc.
			$url = 'http://wiki.teamfortress.com/w/api.php?action=edit&format=xml&bot=true';
			$params = "&title=".$this->name."&summary=".urlencode($summary)."&text=".urlencode($content)."&basetimestamp=".$wpStarttime."&token=".$wpEditToken;
			
			// edit the page
			Login::httpRequest($url, $params);			
		}
		
		public function append($content, $summary) {
			$oldText = $this->getTextarea();
			$this->write($oldText."\n".$content, $summary);
		}
		
		// returns the id of the latest edit
		public static function getLatestRevision() {
			// got to recent changes
			$url = 'http://wiki.teamfortress.com/w/index.php?title=Special:RecentChanges&hidebots=0&limit=1';
			$page = self::request($url);
			
			// pick first edit
			$pattern = '#<div class="mw-changeslist">(.*?)diff=([0-9]+)#s';
			preg_match($pattern, $page, $matches);
			
			// return the id
			return $matches[2];
		}
		
		// returns all recent changes for a language that have been done yesterday
		public static function getRecentChanges($lang) {
			// only for valid languages
			if (!in_array($lang, Registry::get('languages'))) {
				return array();
			}
			// get all recent changes for that language (ignore minor changes)
			$url = 'http://wiki.teamfortress.com/w/index.php?title=Special:RecentChangesLinked&limit=500&hideminor=0&target=Team_Fortress_Wiki%3AReports%2FAll_articles%2F'.$lang;
			$page = self::request($url);

			// get the date of one day ago
			$yesterday = date('d F Y', strtotime("-1 days"));
			// remove leading zero (for days 01 to 09
			$yesterday = ltrim($yesterday, '0');
			
			// search for it in the changes
			$pattern = '#<h4>'.$yesterday.'</h4>(.*?)<h4>#s';
			preg_match($pattern, $page, $matches);
			
			// get the page names of all pages that have been changed yesterday
			$pattern = '#title="([^"]*?)" tabindex#';
			preg_match_all($pattern, $matches[1], $pageNames);
			
			// and return them
			return array_unique($pageNames[1]);			
		}
		
		// returns the content of the page that can actually be edited
		public function getTextarea() {
			// go to the editing site of the wiki page
			$url = 'http://wiki.teamfortress.com/w/index.php?title='.$this->name.'&action=edit';
			$page = self::request($url);
			
			// get the content of the textarea
			$pattern = '#name="wpTextbox1">(.*?)</textarea><div class#s';
			preg_match($pattern, $page, $matches);
			// return empty string if the page is protected etc.
			if (isset($matches[1])) {
				return $matches[1];				
			} else {
				return "";
			}
		}
		
		// gets the content of a page by revision id instead of page name
		public static function getTextareaByRevision($revision) {
			$url = 'http://wiki.teamfortress.com/w/?diff='.$revision.'&action=edit';
			$page = self::request($url);
			
			$pattern = '#name="wpTextbox1">(.*?)</textarea><div class#s';
			//$pattern = '#name="wpTextbox1">(.*?)</textarea><div class=\'editOptions\'>#s';	class="editoptions" needed?
			preg_match($pattern, $page, $matches);
			if (isset($matches[1])) {
				return $matches[1];				
			} else {
				return false;
			}
		}
		
		// returns the number of links that link to the page
		public function getNumLinks() {
			// go to correct WhatLinksHere page
			$url = 'http://wiki.teamfortress.com/w/index.php?title=Special:WhatLinksHere/'.$this->name.'&limit=500';
			$page = self::request($url);
			
			// get the name of all pages that link to this page
			$pattern = '#<li><a href="(.*?)" title="(.*?)">(.*?)</a>#s';
			preg_match_all($pattern, $page, $matches);
			
			// ignore links from specific pages
			// i.e Team Fortress Wiki:Reports/Missing translations
			foreach($matches[3] as $matchesKey => $value) {
				foreach(Registry::get('numLinkExceptions') as $linesKey => $line) {
					if (startsWith(trim($value), trim($line))) {
						if(($key = array_search($value, $matches[3])) !== false) {
							unset($matches[3][$key]);
						}
					}
				}
			}
			
			return count($matches[3]);
		}
		
		public function getHTML() {
			// go to the page of the wiki page
			$url = 'http://wiki.teamfortress.com/w/index.php?title='.$this->name;
			$page = self::request($url);
			
			// get the content of the page
			$pattern = '#class="mw-content-ltr"><p>(.*?)</p>#s';
			preg_match($pattern, $page, $matches);
			
			// return empty string if the page is protected etc.
			if (isset($matches[1])) {
				return $matches[1];
			} else {
				return "";
			}
		}
		
		// returns the name of the page
		public function getName() {
			return $this->name;
		}
	}
?>