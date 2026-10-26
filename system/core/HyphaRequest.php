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
		const HYPHA_SYSTEM_PAGE_TAG_INDEX = 'tag';
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

		/** @var null|string */
		private $configuredRootUrl = null;

		/**
		 * @param null|string $rootUrlPath If it is not given it will be determined.
		 * @param null|string $relativeUrlPath If it is not given it will be determined.
		 */
		public function __construct($rootUrlPath = null, $relativeUrlPath = null) {
			$this->isoLangList = Language::getIsoList();

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
				HyphaRequest::HYPHA_SYSTEM_PAGE_TAG_INDEX,
				HyphaRequest::HYPHA_SYSTEM_PAGE_SETTINGS,
				HyphaRequest::HYPHA_SYSTEM_PAGE_UPLOAD,
				HyphaRequest::HYPHA_SYSTEM_PAGE_CHOOSER,
				HyphaRequest::HYPHA_SYSTEM_PAGE_HELP,
			];
		}

		/**
		 * Sets the absolute root url configured by the site
		 * operator (e.g. "https://www.example.org/"). When set,
		 * getRootUrl() returns it instead of building the url from
		 * the Host header, which is chosen by the client and
		 * would otherwise end up in links that are mailed to
		 * users (e.g. account recovery and invitations).
		 *
		 * @param null|string $url Empty or null to not use one.
		 */
		/**
		 * Checks and normalises the address of a site as entered
		 * by an administrator, e.g. "https://www.example.org" or
		 * "https://example.org/hypha/". Only http(s) with a host,
		 * an optional port and an optional path is accepted; no
		 * user, query or fragment. The result ends in a slash.
		 *
		 * @param string $url
		 * @return string|false|null The normalised url, null for
		 *         an empty value, false when it is not valid.
		 */
		public static function normaliseRootUrl($url) {
			$url = trim((string)$url);
			if ($url === '')
				return null;
			if (!preg_match('#^https?://[^/?\#@\s]+(/[^?\#\s]*)?$#i', $url) || !filter_var($url, FILTER_VALIDATE_URL))
				return false;
			return rtrim($url, '/') . '/';
		}

		public function setConfiguredRootUrl($url) {
			if (!$url) {
				$this->configuredRootUrl = null;
				return;
			}
			$this->configuredRootUrl = rtrim($url, '/') . '/';
		}

		public function getRootUrl() {
			if (null !== $this->configuredRootUrl)
				return $this->configuredRootUrl;

			$scheme = $this->getScheme();
			$host = $this->getHost();

			return sprintf('%s://%s%s', $scheme, $host, $this->rootUrlPath);
		}

		/**
		 * Returns the path component of the root url, i.e. the
		 * part after the hostname. This always starts and ends
		 * with a slash (which might be the same when running
		 * in the root of the domain).
		 */
		public function getRootUrlPath() {
			return $this->rootUrlPath;
		}

		public function getScheme() {
			// Apache 2.4+ has REQUEST_SCHEME
			if (array_key_exists('REQUEST_SCHEME', $_SERVER))
				return $_SERVER['REQUEST_SCHEME'];
			if ($this->isSecure())
				return 'https';
			return 'http';
		}

		public function getHost() {
			// The Host header is chosen by the client. Accept
			// only a syntactically valid host name or IP
			// address with an optional port, so no markup or
			// path can end up in generated urls. Note that this
			// does not stop another, valid host name; configure
			// baseUrl in hypha.xml for that.
			$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
			if (preg_match('/^(?:[A-Za-z0-9](?:[A-Za-z0-9.-]*[A-Za-z0-9])?|\[[0-9A-Fa-f:.]+\])(?::[0-9]{1,5})?$/D', $host))
				return $host;
			return $_SERVER['SERVER_NAME'];
		}

		public function getPort() {
			return $_SERVER['SERVER_PORT'];
		}

		public function isSecure() {
			return array_key_exists('HTTPS', $_SERVER) && $_SERVER['HTTPS'] === 'on';
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
		 * @param string|int $key
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
