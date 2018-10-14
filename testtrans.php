<?php
	class MissingTranslation {
		private static $dictionary = [];

		/** @var array */
		private static $missingTranslations = [
			'unsorted' => [],
			'file' => [],
			'lang' => [],
			'key' => [],
		];

		public static function loadDictionaries($path) {
			foreach (scandir($path) as $file) {
				$info = pathinfo($path . $file);
				if ('php' === $info['extension']) {
					$lang = $info['filename'];
					$translations = include($path . $file);
					self::setDictionary($lang, $translations);
				}
			}
		}

		public static function setDictionary($lang, array $translations) {
			self::$dictionary[$lang] = array_combine(array_keys($translations), array_keys($translations));
		}

		public static function findMissingTranslations($path) {
			foreach (scandir($path) as $file) {
				if (in_array($file, ['.', '..'])) {
					continue;
				}

				if (is_dir($path . $file)) {
					self::findMissingTranslations($path . $file . '/');
					continue;
				}

				$content = file_get_contents($path . $file);
				$matchCount = preg_match_all("/__\([\'\"](.*?)[\'\"]\)/", $content, $msgIds);
				if (!$matchCount) {
					continue;
				}

				$keys = array_combine(array_values($msgIds[1]), array_values($msgIds[1]));

				foreach(self::$dictionary as $lang => $dictKeys) {
					foreach (array_diff($keys, $dictKeys) as $missingKey) {
						$filePath = substr($path . $file, strlen(__DIR__ . '/'));
						$missingTranslation = new self($filePath, $lang, $missingKey);
						self::addMissingTranslation($missingTranslation);
					}
				}
			}
		}

		public static function addMissingTranslation(MissingTranslation $missingTranslation) {
			self::$missingTranslations['unsorted']['unsorted'][] = $missingTranslation;
			self::$missingTranslations['file'][$missingTranslation->file][] = $missingTranslation;
			self::$missingTranslations['lang'][$missingTranslation->lang][] = $missingTranslation;
			self::$missingTranslations['key'][$missingTranslation->key][] = $missingTranslation;
		}

		/**
		 * @return MissingTranslation[]
		 */
		public static function getMissingTranslations($sort = 'unsorted') {
			$missingTranslations = self::$missingTranslations[$sort];
			if ($sort === 'unsorted') {
				return $missingTranslations;
			}

			ksort($missingTranslations);

			return $missingTranslations;
		}

		public $file;
		public $lang;
		public $key;

		public function __construct($file, $lang, $key) {
			$this->file = $file;
			$this->lang = $lang;
			$this->key = $key;
		}
	}

	MissingTranslation::loadDictionaries(__DIR__ . '/system/languages/');
	MissingTranslation::findMissingTranslations(__DIR__ . '/system/');

	$sort = isset($argv[1]) ? $argv[1] : 'unsorted';
	$sortOptions = ['unsorted', 'key', 'lang', 'file'];
	$validSortOptionGiven = in_array($sort, $sortOptions);
	if ($validSortOptionGiven) {
		$missingTranslations = MissingTranslation::getMissingTranslations($sort);

		foreach ($missingTranslations as $sort => $missingTranslationPerSortValue) {
			echo $sort.PHP_EOL;
			foreach ($missingTranslationPerSortValue as $missingTranslation) {
				echo 'missing key "'.$missingTranslation->key.'" from '.$missingTranslation->file.' in "'.$missingTranslation->lang.'" dictionary'.PHP_EOL;
			}
			echo PHP_EOL;
		}
	} else {
		echo('invalid sort value given; "' . $sort . '".' . PHP_EOL);
	}

	if (!isset($argv[1]) || !$validSortOptionGiven) {
		echo PHP_EOL . 'you can supply a sorting argument to this function, options are; ' . implode(', ', $sortOptions) . PHP_EOL;
	}
