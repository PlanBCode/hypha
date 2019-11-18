<?php

	/**
	 * HyphaRequest will breakdown the request and determine
	 * - rootUrlPath
	 * - relativeUrlPath
	 * - language
	 * - pageName
	 * - arguments
	 */
	class HyphaRequest {
		/** TODO[LRM]: allow registration of system pages, then inject system pages in constructor */
		const HYPHA_SYSTEM_PAGE_FILES = 'files';
		const HYPHA_SYSTEM_PAGE_IMAGES = 'images';
		const HYPHA_SYSTEM_PAGE_INDEX = 'index';
		const HYPHA_SYSTEM_PAGE_SETTINGS = 'settings';
		const HYPHA_SYSTEM_PAGE_UPLOAD = 'upload';
		const HYPHA_SYSTEM_PAGE_CHOOSER = 'chooser';
		const HYPHA_SYSTEM_PAGE_HELP = 'help';

		/** @var string */
		private $rootUrlPath;

		/** @var string */
		private $relativeUrlPath;

		/** @var array */
		private $isoLangList;

		/** @var array */
		private $relativeUrlPathParts;

		/** @var null|string */
		private $language;

		/** @var null|string */
		private $systemPage;

		/** @var null|string */
		private $pageName;

		/** @var array */
		private $args = [];

		/**
		 * @param array $isoLangList Associative array containing connecting iso639 language codes with their native name (and english translation)
		 * @param null|string $rootUrlPath If it is not given it will be determined.
		 * @param null|string $relativeUrlPath If it is not given it will be determined.
		 */
		public function __construct(array $isoLangList, $rootUrlPath = null, $relativeUrlPath = null) {
			$this->isoLangList = $isoLangList;

			/**
			 * in case of hypha instance in the root
			 * request uri: http://www.my-org.net/en/my-page?foo=bar
			 * $_SERVER['SCRIPT_NAME'] is expected to be "/index.php"
			 * $_SERVER['REQUEST_URI'] is expected to be "/en/my-page?foo=bar"
			 * $rootUrlPath will be "/"
			 * $relativeUrlPath will be "en/my-page"
			 */
			/**
			 * in case of hypha instance in sub directory
			 * request uri: http://www.my-org.net/my-hypha/en/my-page?foo=bar
			 * $_SERVER['SCRIPT_NAME'] is expected to be "/my-hypha/index.php"
			 * $_SERVER['REQUEST_URI'] is expected to be "/my-hypha/en/my-page?foo=bar"
			 * $rootUrlPath will be "/my-hypha/"
			 * $relativeUrlPath will be "en/my-page"
			 */
			if (null === $rootUrlPath) {
				$rootUrlPath = dirname($_SERVER['SCRIPT_NAME']);
				// On Windows, dirname returns \ when it strips the last path component (it
				// leaves all other (back)slashes as-is)
				if ($rootUrlPath === '\\')
					$rootUrlPath = '/';
				$rootUrlPath .= substr($rootUrlPath, -1) === '/' ? '' : '/';
			}
			$this->rootUrlPath = $rootUrlPath;

			if (null === $relativeUrlPath) {
				$relativeUrlPath = substr($_SERVER['REQUEST_URI'], strlen($this->rootUrlPath));
				// Strip off any query parameters
				if (($paramPos = strpos($relativeUrlPath, '?')) && $paramPos !== false){
					$relativeUrlPath = substr($relativeUrlPath, 0, $paramPos);
				}
			}
			$this->relativeUrlPath = $relativeUrlPath;

			$this->processRequest();
		}

		private function processRequest() {
			$this->relativeUrlPathParts = $relativeUrlPathParts = array_filter(explode('/', $this->relativeUrlPath));

			$firstPart = reset($relativeUrlPathParts);
			if ($firstPart !== false && array_key_exists($firstPart, $this->isoLangList)) {
				$this->language = array_shift($relativeUrlPathParts);
			}

			$systemPage = reset($relativeUrlPathParts);
			if ($this->language === null && $systemPage !== false && in_array($systemPage, HyphaRequest::getSystemPages())) {
				$this->systemPage = array_shift($relativeUrlPathParts);
			}

			$pageType = reset($relativeUrlPathParts);
			if ($pageType !== false && $this->systemPage === null && $this->language === null) {
				array_shift($relativeUrlPathParts);
			}

			$pageName = reset($relativeUrlPathParts);
			if ($pageName !== false && $this->systemPage === null) {
				$this->pageName = array_shift($relativeUrlPathParts);
			}

			$this->args = $relativeUrlPathParts;
		}

		public static function getSystemPages() {
			return [
				HyphaRequest::HYPHA_SYSTEM_PAGE_FILES,
				HyphaRequest::HYPHA_SYSTEM_PAGE_IMAGES,
				HyphaRequest::HYPHA_SYSTEM_PAGE_INDEX,
				HyphaRequest::HYPHA_SYSTEM_PAGE_SETTINGS,
				HyphaRequest::HYPHA_SYSTEM_PAGE_UPLOAD,
				HyphaRequest::HYPHA_SYSTEM_PAGE_CHOOSER,
				HyphaRequest::HYPHA_SYSTEM_PAGE_HELP,
			];
		}

		public function getRootUrl() {
			$scheme = $this->getScheme();
			$host = $this->getHost();

			return sprintf('%s://%s%s', $scheme, $host, $this->rootUrlPath);
		}

		public function getScheme() {
			// Apache 2.4+ has REQUEST_SCHEME
			if (array_key_exists('REQUEST_SCHEME', $_SERVER))
				return $_SERVER['REQUEST_SCHEME'];
			if (array_key_exists('HTTPS', $_SERVER) && $_SERVER['HTTPS'] == 'on')
				return 'https';
			return 'http';
		}

		public function getHost() {
			return $_SERVER['HTTP_HOST'];
		}

		public function getPort() {
			return $_SERVER['SERVER_PORT'];
		}

		/**
		 * Returns the relative URL path, with or without the language.
		 *
		 * @param bool $excludeLanguage
		 *
		 * @return string
		 */
		public function getRelativeUrlPath($excludeLanguage = true) {
			return implode('/', $this->getRelativeUrlPathParts($excludeLanguage));
		}

		/**
		 * Returns the initial relative URL path of the page, no view / arguments.
		 *
		 * @return string
		 */
		public function getRelativePageUrlPath() {
			return implode('/', array_filter([$this->getLanguage(), $this->getPageName()]));
		}

		/**
		 * Returns the relative URL path parts, with or without the language.
		 *
		 * @param bool $excludeLanguage
		 *
		 * @return array
		 */
		public function getRelativeUrlPathParts($excludeLanguage = true) {
			$relativeUrlPathParts = $this->relativeUrlPathParts;

			if ($excludeLanguage && reset($relativeUrlPathParts) === $this->language) {
				array_shift($relativeUrlPathParts);
			}

			return $relativeUrlPathParts;
		}

		public function getView() {
			return count($this->args) >= 1 ? $this->args[0] : null;
		}

		public function getCommand() {
			return $this->getPostValue('command');
		}

		/**
		 * Return the language if it could be found
		 *
		 * @return null|string
		 */
		public function getLanguage() {
			return $this->language;
		}

		/**
		 * Indicates if the current request is a system page
		 *
		 * @return bool
		 */
		public function isSystemPage() {
			return $this->systemPage !== null;
		}

		/**
		 * Returns the system page if the current request is a system page
		 *
		 * @return string|null
		 */
		public function getSystemPage() {
			return $this->systemPage;
		}

		/**
		 * Returns the page name if the current request is not a system page
		 *
		 * @return string|null
		 */
		public function getPageName() {
			return $this->pageName;
		}

		/**
		 * Returns the request arguments
		 *
		 * @return array
		 */
		public function getArgs() {
			return $this->args;
		}

		/**
		 * Returns a request argument
		 *
		 * @param string $key
		 * @param null|mixed $default
		 *
		 * @return null|mixed
		 */
		public function getArg($key, $default = null) {
			return array_key_exists($key, $this->args) ? $this->args[$key] : $default;
		}

		/**
		 * @return bool
		 */
		public function isPost() {
			return 'POST' == $_SERVER['REQUEST_METHOD'];
		}

		/**
		 * @return array
		 */
		public function getPostData() {
			return $_POST;
		}

		/**
		 * @param string $key
		 * @param null|mixed $default
		 *
		 * @return null|mixed
		 */
		public function getPostValue($key, $default = null) {
			$postData = $this->getPostData();
			return array_key_exists($key, $postData) ? $postData[$key] : $default;
		}
	}
