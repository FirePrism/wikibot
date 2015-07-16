<?php
	error_reporting(E_ALL);

	// you might want to set up some kind of password to prevent others from using your bot if they know the URL
	if (!isset($_GET['foo']) || $_GET['foo'] != "bar") {
		die();
	}
	
	// set max execution time
	set_time_limit(1800);
	include 'functions.php';
	include 'registry.class.php';
	include 'login.class.php';
	include 'page.class.php';
	include 'topMissing.class.php';
	include 'typos.class.php';
	include 'externalLinks.class.php';
	
	// set file to save the cookies
	Registry::set('cookies', 'cookies.txt');
	// all valid language codes
	Registry::set('languages', array('en', 'cs', 'da', 'de', 'es', 'fi', 'fr', 'hu', 'it', 'ja', 'ko', 'nl', 'no', 'pl', 'pt', 'pt-br', 'ro', 'ru', 'sv', 'tr', 'zh-hans', 'zh-hant'));
	
	// login into the wiki
	$login = new Login('user', 'password');
	$token = $login->login();
	$login->login($token);	
	
	// if the language code is valid
	if (isset($_GET['lang']) && in_array($_GET['lang'], Registry::get('languages'))) {
		$lang = $_GET['lang'];
		if (isset($_GET['action'])) {
			// only consider translations
			if ($_GET['action'] == 'missingPages' && lang != 'en') {
				// get the missing pages string
				$missingPages = TopMissing::getFormatedTopMissingPages($lang);
				// go to the WantedPages side
				$page = new page('Team_Fortress_Wiki:Reports/WantedPages/'.$lang);
				// and update it
				$page->write($missingPages, 'Updated most wanted pages in /'.$lang);
			} else if ($_GET['action'] == 'typos') {
				// get the typos string
				$typos = Typos::getFormatedTypoList($lang);
				// use "Pages with typos" instead of "Pages with typos/en"
				if ($lang == 'en') {
					$page = new page('Team Fortress Wiki:Reports/Pages with typos');
				} else {
					$page = new page('Team Fortress Wiki:Reports/Pages with typos/'.$lang);
				}
				// update the typo-page
				$page->write($typos, 'Updated typos in /'.$lang);
			}
		}
	}
	if (isset($_GET['action']) && $_GET['action'] == "updateTrendingTopics") {
		topMissing::updateTrendingTopics();
	} else	if (isset($_GET['action']) && $_GET['action'] == "addBrokenLink") {
		$brokenLink = ExternalLinks::addBrokenLink();
		if (!empty($brokenLink)) {
			$brokenLinkPage = new Page("Team_Fortress_Wiki:Reports/BrokenLinks");
			$brokenLinkPage->append($brokenLink, "Added broken external link");
		}
	} else if (isset($_GET['action']) && $_GET['action'] == "removeFixedLinks") {
		$clean = ExternalLinks::removeFixedLinks();
		$brokenLinkPage = new Page("Team_Fortress_Wiki:Reports/BrokenLinks");
		$brokenLinkPage->write($clean, "Removed fixed external links");
	}
?>