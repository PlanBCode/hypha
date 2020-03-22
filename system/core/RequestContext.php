<?php

	class RequestContext {
		/** @var HyphaRequest */
		private $hyphaRequest;

		/** @var HyphaDomElement|null */
		private $hyphaUser;

		/** @var string */
		private $fallbackLanguage;

		/** @var string */
		private $fallbackInterfaceLanguage;

		/** @var array */
		private $dictionary;

		/**
		 * @param HyphaRequest $hyphaRequest
		 */
		public function __construct(HyphaRequest $hyphaRequest) {
			$this->hyphaRequest = $hyphaRequest;
			$user = isset($_SESSION['hyphaLogin']) ? hypha_getUserById($_SESSION['hyphaLogin']) : false;
			$this->hyphaUser = $user ?: null;
		}

		/**
		 * @param string $fallbackLanguage
		 */
		public function setFallbackLanguage($fallbackLanguage) {
			$this->fallbackLanguage = $fallbackLanguage;
		}

		/**
		 * @return string
		 */
		protected function getFallbackLanguage() {
			if ($this->fallbackLanguage === null) {
				$this->fallbackLanguage = hypha_getDefaultLanguage();
			}
			return $this->fallbackLanguage;
		}

		/**
		 * @param string $fallbackInterfaceLanguage
		 */
		public function setFallbackInterfaceLanguage($fallbackInterfaceLanguage) {
			$this->fallbackInterfaceLanguage = $fallbackInterfaceLanguage;
		}

		/**
		 * @return string
		 */
		protected function getFallbackInterfaceLanguage() {
			if ($this->fallbackInterfaceLanguage === null) {
				$this->fallbackInterfaceLanguage = hypha_getDefaultInterfaceLanguage();
			}
			return $this->fallbackInterfaceLanguage;
		}

		/**
		 * @return HyphaDomElement|null
		 */
		public function getUser() {
			return $this->hyphaUser;
		}

		/**
		 * @return bool
		 */
		public function isUser() {
			return $this->hyphaUser instanceof HyphaDomElement;
		}

		/**
		 * @return bool
		 */
		function isAdmin() {
			return $this->isUser() && $this->hyphaUser->getAttribute('rights') === 'admin';
		}

		/**
		 * @return string
		 */
		public function getContentLanguage() {
			return $this->hyphaRequest->getLanguage() ?: $this->getFallbackLanguage();
		}

		/**
		 * @return string
		 * @throws Exception
		 */
		public function getInterfaceLanguage() {
			$languageOptions = [];
			if ($this->hyphaUser instanceof HyphaDomElement) {
				$languageOptions[] = $this->hyphaUser->getAttribute('language');
			}
			if (array_key_exists('HTTP_ACCEPT_LANGUAGE', $_SERVER)) {
				$languageOptions[] = strtolower(substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2));
			}
			$languageOptions[] = $this->getContentLanguage();
			$languageOptions[] = $this->getFallbackInterfaceLanguage();
			foreach ($languageOptions as $languageOption) if (in_array($languageOption, Language::getInterfaceLanguageList())) {
				return $languageOption;
			}

			throw new \Exception('Cannot determine the interface language');
		}

		/**
		 * @return array
		 * @throws Exception
		 */
		public function getDictionary() {
			if (null === $this->dictionary) {
				$this->dictionary = Language::getDictionaryByLanguage($this->getInterfaceLanguage());
			}

			return $this->dictionary;
		}

		/**
		 * @return HyphaRequest
		 */
		public function getRequest() {
			return $this->hyphaRequest;
		}

		public function getRootPath() {
			return dirname(dirname(__DIR__));
		}
	}
