<?php
	/**
	 * Returns a string from the selected dictionary.
	 * @param string $msgid identifier used as a key to fetch the corresponding string from the dictionary.
	 * @param null|array $args arguments to be interpolated into translated string
	 * @return string
	 */
	function __($msgid, $args = null) {
		return Language::translate($msgid, $args);
	}

	class Language {
		/** @var array */
		private static $dictionaries = [];

		/** @var array */
		private static $interfaceLanguages = [];

		/**
		 * @return array Associative array containing connecting iso639 language codes with their native name (and english translation)
		 */
		public static function getIsoList() {
			return json_decode(file_get_contents('system/languages/languages.json'), true);
		}

		/**
		 * Returns an html option list with languages
		 *
		 * @param string $select which option should be preselected
		 * @param string $omit which language should be omitted
		 * @return string
		 */
		public static function getLanguageOptionList($select, $omit) {
			$isoLangList = self::getIsoList();
			$langList = hypha_getUsedContentLanguages();

			$htmlOptgroup = '<optgroup label="[[optgroupLabel]]">[[options]]</optgroup>';
			$htmlOption = '<option value="[[value]]"[[selected]]>[[display]]</option>';
			$inUse = '';
			foreach ($langList as $code) if ($code != $omit) {
				$inUse.= hypha_substitute($htmlOption, [
					'value' => htmlspecialchars($code),
					'selected' => $code === $select ? ' selected' : '',
					'display' => htmlspecialchars($code . ': ' . $isoLangList[$code]),
				]);
			}
			$html = hypha_substitute($htmlOptgroup, [
				'optgroupLabel' => 'in use',
				'options' => $inUse,
			]);

			$new = '';
			foreach($isoLangList as $code => $langName) if (!in_array($code, $langList) && $code!=$omit ) {
				$new.= hypha_substitute($htmlOption, [
					'value' => htmlspecialchars($code),
					'selected' => $code === $select ? ' selected' : '',
					'display' => htmlspecialchars($code . ': ' . $langName),
				]);
			}
			$html .= hypha_substitute($htmlOptgroup, [
				'optgroupLabel' => 'add new',
				'options' => $new,
			]);

			return $html;
		}

		public static function getInterfaceLanguageList() {
			if (empty(self::$interfaceLanguages)) {
				foreach (scandir(self::getDictionaryRootPath() . '/') as $file) if (substr($file, -4) == '.php') {
					self::$interfaceLanguages[] = basename($file, '.php');
				}
			}

			return self::$interfaceLanguages;
		}

		/**
		 * Returns an html option list with interface languages
		 *
		 * @param string $select which option should be preselected
		 * @param string $omit which language should be omitted
		 * @return string
		 */
		public static function getInterfaceLanguageOptionList($select, $omit) {
			$isoLangList = self::getIsoList();

			$html = '';
			foreach(self::getInterfaceLanguageList() as $code) if ($code != $omit) {
				$html.= '<option value="'.htmlspecialchars($code).'"'.($code==$select ? ' selected="selected"' : '').'>'.htmlspecialchars($code.(array_key_exists($code, $isoLangList) ? ': ' . $isoLangList[$code] : '')).'</option>';
			}

			return $html;
		}

		/**
		 * Returns a string from the selected dictionary.
		 *
		 * @param string $msgid identifier used as a key to fetch the corresponding string from the dictionary.
		 * @param null|array $args
		 * @return string
		 */
		public static function translate($msgid, $args = null) {
			global $O_O;

			try {
				$hyphaDictionary = $O_O->getDictionary();
			} catch (\Exception $e) {
				return $msgid;
			}

			$msg = array_key_exists($msgid, $hyphaDictionary) ? $hyphaDictionary[$msgid] : $msgid;
			if ($args) $msg = hypha_substitute($msg, $args);

			return $msg;
		}

		/**
		 * @param string $language
		 * @return null|array
		 */
		public static function getDictionaryByLanguage($language) {
			if (!array_key_exists($language, self::$dictionaries)) {
				$file = self::getDictionaryRootPath() . '/' . $language . '.php';
				self::$dictionaries[$language] = file_exists($file) ? include($file) : null;
			}
			return self::$dictionaries[$language];
		}

		/**
		 * @return string
		 */
		protected static function getDictionaryRootPath() {
			global $O_O;
			return $O_O->getRootPath() . '/system/languages';
		}
	}
