<?php

	class HyphaRequest {
		/** TODO[LRM]: allow registration of system pages, then inject system pages in constructor */
		const HYPHA_SYSTEM_PAGE_FILES = 'files';
		const HYPHA_SYSTEM_PAGE_IMAGES = 'images';
		const HYPHA_SYSTEM_PAGE_INDEX = 'index';
		const HYPHA_SYSTEM_PAGE_SETTINGS = 'settings';
		const HYPHA_SYSTEM_PAGE_UPLOAD = 'upload';
		const HYPHA_SYSTEM_PAGE_CHOOSER = 'chooser';

		/** @var string */
		private $rootPath;

		/** @var string */
		private $requestQuery;

		/** @var array */
		private $isoLangList;

		/** @var array */
		private $requestParts;

		/** @var null|string */
		private $language;

		/** @var null|string */
		private $systemPage;

		/** @var null|string */
		private $pageName;

		/** @var array */
		private $args = [];

		/**
		 * @param string $rootPath
		 * @param array $isoLangList Associative array containing connecting iso639 language codes with their native name (and english translation)
		 */
		public function __construct($rootPath, array $isoLangList) {
			$this->rootPath = $rootPath;

			$this->requestQuery = substr($_SERVER['REQUEST_URI'], strlen($this->rootPath));
			// Strip off any query parameters
			if (($paramPos = strpos($this->requestQuery, '?')) && $paramPos !== false){
				$this->requestQuery = substr($this->requestQuery, 0, $paramPos);
			}

			$this->isoLangList = $isoLangList;
			$this->processRequest();
		}

		private function processRequest() {
			$this->requestParts = $requestParts = array_filter(explode('/', $this->requestQuery));

			$firstPart = reset($requestParts);
			if ($firstPart !== false && array_key_exists($firstPart, $this->isoLangList)) {
				$this->language = array_shift($requestParts);
			}

			$systemPage = reset($requestParts);
			if ($this->language === null && $systemPage !== false && in_array($systemPage, HyphaRequest::getSystemPages())) {
				$this->systemPage = array_shift($requestParts);
			}

			$pageType = reset($requestParts);
			if ($pageType !== false && $this->systemPage === null && $this->language === null) {
				array_shift($requestParts);
			}

			$pageName = reset($requestParts);
			if ($pageName !== false && $this->systemPage === null) {
				$this->pageName = array_shift($requestParts);
			}

			$this->args = $requestParts;
		}

		public static function getSystemPages() {
			return [
				HyphaRequest::HYPHA_SYSTEM_PAGE_FILES,
				HyphaRequest::HYPHA_SYSTEM_PAGE_IMAGES,
				HyphaRequest::HYPHA_SYSTEM_PAGE_INDEX,
				HyphaRequest::HYPHA_SYSTEM_PAGE_SETTINGS,
				HyphaRequest::HYPHA_SYSTEM_PAGE_UPLOAD,
				HyphaRequest::HYPHA_SYSTEM_PAGE_CHOOSER,
			];
		}

		public function getRootUrl() {
			$scheme = $this->getScheme();
			$https = 'https' === $scheme;
			$host = $this->getHost();
			$port = $this->getPort();

			$hasAltPort = (!$https && $port !== 80) || ($https && $port !== 443);
			$altPort = $hasAltPort ? ':' . $port : '';

			return sprintf('%s://%s%s%s', $scheme, $host, $altPort, $this->rootPath);
		}

		public function getScheme() {
			return $_SERVER['REQUEST_SCHEME'];
		}

		public function getHost() {
			return $_SERVER['HTTP_HOST'];
		}

		public function getPort() {
			return $_SERVER['SERVER_PORT'];
		}

		/**
		 * @param bool $excludeLanguage
		 *
		 * @return string
		 */
		public function getRequestQuery($excludeLanguage = true) {
			return implode('/', $this->getRequestParts($excludeLanguage));
		}

		/**
		 * @param bool $excludeLanguage
		 *
		 * @return array
		 */
		public function getRequestParts($excludeLanguage = true) {
			$requestParts = $this->requestParts;

			if ($excludeLanguage && reset($requestParts) === $this->language) {
				array_shift($requestParts);
			}

			return $requestParts;
		}

		/**
		 * @return null|string
		 */
		public function getLanguage() {
			return $this->language;
		}

		/**
		 * @return bool
		 */
		public function isSystemPage() {
			return $this->systemPage !== null;
		}

		/**
		 * @return string|null
		 */
		public function getSystemPage() {
			return $this->systemPage;
		}

		/**
		 * @return string|null
		 */
		public function getPageName() {
			return $this->pageName;
		}

		/**
		 * @return array
		 */
		public function getArgs() {
			return $this->args;
		}
	}
