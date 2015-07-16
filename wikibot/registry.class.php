<?php
	/**
	* Registry class to pass global variables between classes.
	*/
	abstract class Registry {
		/**
		 * Object registry provides storage for shared objects
		 *
		 * @var array
		 */
		private static $registry = array();
		
		/**
		 * Adds a new variable to the Registry.
		 *
		 * @param string $key
		 *        	Name of the variable
		 * @param mixed $value
		 *        	Value of the variable
		 * @throws Exception
		 * @return bool
		 */
		public static function set($key, $value) {
			if (!self::has($key)) {
				self::$registry[$key] = $value;
				return true;
			} else {
				throw new Exception('Unable to set variable "'.$key.'". It was already set.');
			}
		}
		
		/**
		 * Tests if given $key exists in registry
		 *
		 * @param string $key        	
		 * @return bool
		 */
		public static function has($key) {
			if (isset(self::$registry[$key])) {
				return true;
			}
			
			return false;
		}
		
		/**
		 * Returns the value of the specified $key in the Registry.
		 *
		 * @param string $key
		 *        	Name of the variable
		 * @return mixed Value of the specified $key
		 */
		public static function get($key) {
			if (self::has($key)) {
				return self::$registry[$key];
			// read disambiguations file on first request
			} else if ($key == 'disambiguations') {
				self::set('disambiguations', file('cache/disambiguations.txt'));
				return self::get('disambiguations');
			// read exceptions for missing pages file on first request
			} else if ($key == 'missingPagesExceptions') {
				self::set('missingPagesExceptions', file('cache/missingPagesExceptions.txt'));
				return self::get('missingPagesExceptions');
			// read exceptions for number of links file on first request
			} else if ($key == 'numLinkExceptions') {
				self::set('numLinkExceptions', file('cache/numLinkExceptions.txt'));
				return self::get('numLinkExceptions');
			// read recent additions file on first request
			} else if ($key == 'recentAdditions') {
				self::set('recentAdditions', file('cache/recentAdditions.txt'));
				return self::get('recentAdditions');
			// if we want to read a typo file for a specific language
			} else if (startsWith($key, 'typos')) {
				// get all allowed language codes
				foreach (self::get('languages') as $language) {
					// remove 'typos_' so only the language code is left
					$lang = substr($key, 6);
					// if the language code is valid
					if ($lang == $language) {
						// if there is a typo file for this language
						if (file_exists('cache/typos_'.$lang.'.txt')) {
							// read the entire file into an array
							$typos = file('cache/typos_'.$lang.'.txt');
							$result = array();
							// for each line (=each typo)
							foreach ($typos as $typo) {
								// separate the wrong word from the correct one
								$ex = explode('->', $typo);
								// if we already have a correction for this typo (i.e. the file contains multiple lines that start with the same typo)
								if (array_key_exists($ex[0], $result)) {
									// add the new correction to the old one ([wrong] = "correct1, correct2")
									$result[$ex[0]] .= ", ".trim($ex[1]);
								} else {
									// create a new entry with the correct word ([wrong] = "correct")
									$result[$ex[0]] = trim($ex[1]);
								}
							}
							self::set('typos_'.$lang, $result);
							return $result;
						} else {
							throw new Exception('File not found: typos_'.$lang.'.txt');
						}
					}
				}
			}
			return null;
		}
		
		/**
		 * Returns the whole Registry as an array.
		 *
		 * @return array Whole Registry
		 */
		public static function getAll() {
			return self::$registry;
		}
		
		/**
		 * Removes a variable from the Registry.
		 *
		 * @param string $key
		 *        	Name of the variable
		 * @return bool
		 */
		public static function remove($key) {
			if (self::has($key)) {
				unset(self::$registry[$key]);
				return true;
			}
			return false;
		}
		
		/**
		 * Removes all variables from the Registry.
		 *
		 * @return void
		 */
		public static function removeAll() {
			self::$registry = array();
			return;
		}
	}
?>