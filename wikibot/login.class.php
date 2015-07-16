<?php
	// this class is used to login into the wiki
	class Login {
		private $user;
		private $pass;
		
		public function __construct($user, $pass) {
			$this->user = $user;
			$this->pass = $pass;
		}
		
		// send the post data to the wiki
		public static function httpRequest($url, $post = "") {			
			$ch = curl_init ();
			curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.9) Gecko/20071025 Firefox/2.0.0.9');
			curl_setopt($ch, CURLOPT_URL, ($url));
			curl_setopt($ch, CURLOPT_ENCODING, 'UTF-8');
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_COOKIEFILE, Registry::get('cookies'));
			curl_setopt($ch, CURLOPT_COOKIEJAR, Registry::get('cookies'));
			if (!empty($post)) {
				curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
			}
			$xml = curl_exec($ch);

			if (!$xml) {
				die('Error getting data from server ('.$url.'):'.curl_error($ch));
			}
			curl_close($ch);
			
			return $xml;
		}
		
		// log in into the wiki
		public function login($token = '') {
			// check if already logged in
			$url = 'http://wiki.teamfortress.com/w/api.php?action=query&format=xml&meta=userinfo';
			$data = self::httpRequest($url);
			$xml = simplexml_load_string($data);
			$result = $xml->xpath("/api/query/userinfo[@id]");
			if ($result[0]['id'] != 0) {
				return;
			}
			
			$url = 'http://wiki.teamfortress.com/w/api.php?action=login&format=xml';
			
			$params = "action=login&lgname=$this->user&lgpassword=$this->pass";
			if (!empty($token )) {
				$params .= "&lgtoken=$token";
			}
			
			$data = self::httpRequest($url, $params);

			if (empty($data )) {
				die('No data received from server. Check that API is enabled.');
			}
			
			$xml = simplexml_load_string($data);
			
			if (!empty($token)) {
				$expr = "/api/login[@result='Success']";
				$result = $xml->xpath($expr);
			
				if (!count($result)) {
					die("Login failed");
				}
			} else {
				$expr = '/api/login[@token]';
				$result = $xml->xpath($expr);
			
				if (!count($result)) {
					die('Login token not found in XML');
				}
			}
			
			return $result[0]->attributes()->token;
		}
	}
?>