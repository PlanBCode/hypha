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

		/*
			Function: getOrGenerateCsrfToken

			Returns the CsrfToken to be used for all
			subsequent POST requests. If there is no token
			yet, it will be generated automatically.
		*/
		public function getOrGenerateCsrfToken() {
			if (!isset($_SESSION['hyphaCsrfToken'])) {
				session_start();
				$this->regenerateCsrfToken();
				session_write_close();
			}

			return $_SESSION['hyphaCsrfToken'];
		}

		/*
			Function: regenerateCsrfToken

			Regenerate the CSRF token. Should be called when a new
			session starts, such as during login. Should be called
			while the session is already open (e.g. between
			session_start() and session_write_close()).
		*/
		public function regenerateCsrfToken() {
			$_SESSION['hyphaCsrfToken'] = bin2hex(openssl_random_pseudo_bytes(8));
		}

		/*
			Function: csrfValid

			Returns whether the request is a POST request
			that contains a valid CSRF token in the request
			parameters. When this returns false, a POST
			request should not be processed.
		*/
		public function validCsrfToken() {
			$received = $this->getRequest()->getPostValue('csrfToken');
			$expected = $this->getOrGenerateCsrfToken();
			return $received === $expected;
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
