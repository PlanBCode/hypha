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

		/** @var null|string */
		private $csrfToken;

		/** @var HyphaSession */
		private $session;

		/**
		 * Collection of various data files to be used in this
		 * request.
		 * @var StdClass
		 */
		public $data;

		/**
		 * @param HyphaRequest $hyphaRequest
		 */
		public function __construct(HyphaRequest $hyphaRequest) {
			$this->hyphaRequest = $hyphaRequest;
			$this->session = new HyphaSession($hyphaRequest);
			$userid = $this->session->get('hyphaLogin');
			if ($userid)
				$this->hyphaUser = hypha_getUserById($userid);
			else
				$this->hyphaUser = null;
			$this->csrfToken = isset($_COOKIE['hyphaCsrfToken']) ? $_COOKIE['hyphaCsrfToken'] : null;

			$theme = $this->getThemeName();
			$this->data = new StdClass();
			$this->data->themeHtml = new HyphaFile('data/themes/' . $theme . '/hypha.html');
			$this->data->themeCss = new HyphaFile('data/themes/' . $theme . '/hypha.css');
			$this->data->digest = new HyphaFile('data/digest');
			$this->data->stats = new HyphaFile('data/hypha.stats');
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
			if (!$this->csrfToken)
				$this->regenerateCsrfToken();

			return $this->csrfToken;
		}

		/*
			Function: regenerateCsrfToken

			Regenerate the CSRF token. Should be called when a new
			session starts, such as during login.
		*/
		public function regenerateCsrfToken() {
			$this->csrfToken = bin2hex(openssl_random_pseudo_bytes(8));
			// Store the token in a cookie that is limited
			// to our root path, secure (not sent over HTTP)
			// if appropriate, and *only* sent on http(s)
			// requests (not accessible to scripts).
			//
			// Storing it in a cookie instead of in the
			// session removes the need for creating a
			// session for site visitors.
			//
			// Cookies are well-protected by the browser, so
			// it is not possible for other sites to get
			// access to this cookie to do an CSRF attack.
			setcookie('hyphaCsrfToken', $this->csrfToken,
			          /* expire */ 0,
			          /* path */ $this->getRequest()->getRootUrlPath(),
				  /* domain */ "",
				  /* secure */ $this->getRequest()->isSecure(),
				  /* http_only */ true);
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

		/**
		 * Returns the name of the theme being previewed, or
		 * null if none.
		 * @return null|string
		 */
		public function getPreviewThemeName() {
			$sess = $this->getSession();
			return $sess->get('previewTheme', null);
		}

		/**
		 * Sets the name of the theme being previewed. Pass
		 * null to stop previewing.
		 * @param null|string name
		 */
		public function setPreviewThemeName($name) {
			$sess = $this->getSession();
			$sess->lockAndReload();
			if ($name)
				$sess->set('previewTheme', $name);
			else
				$sess->remove('previewTheme');
			$sess->writeAndUnlock();
		}

		/**
		 * Returns the name of the theme effectively being used
		 * (configured or previewed)
		 * @return string
		 */
		public function getThemeName() {
			$theme = $this->getPreviewThemeName();
			if (null === $theme) {
				$theme = hypha_getNormalTheme();
			}
			return $theme;
		}

		/**
		 * Returns the current session.
		 */
		public function getSession() {
			return $this->session;
		}
	}
