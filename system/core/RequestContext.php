<?php

	class RequestContext {
		/** @var HyphaRequest */
		private $hyphaRequest;

		/** @var HyphaDomElement|null */
		private $hyphaUser;

		/** @var string */
		private $defaultLanguage;

		/**
		 * @param HyphaRequest $hyphaRequest
		 * @param string $defaultLanguage
		 */
		public function __construct(HyphaRequest $hyphaRequest, $defaultLanguage) {
			$this->hyphaRequest = $hyphaRequest;
			$this->defaultLanguage = $defaultLanguage;
			$user = isset($_SESSION['hyphaLogin']) ? hypha_getUserById($_SESSION['hyphaLogin']) : false;
			$this->hyphaUser = $user ?: null;
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
			return $this->hyphaRequest->getLanguage() ?: $this->defaultLanguage;
		}

		/**
		 * @return string
		 */
		public function getInterfaceLanguage() {
			return $this->hyphaUser instanceof HyphaDomElement ? $this->hyphaUser->getAttribute('language') : $this->getContentLanguage();
		}

		/**
		 * @return array
		 */
		protected function getDictionaryLanguageOptions() {
			return [
				$this->getInterfaceLanguage(),
				$this->getContentLanguage(),
				$this->defaultLanguage,
				'en',
			];
		}

		/**
		 * @return array
		 */
		public function getDictionary() {
			$rootDir = $this->getRootPath();
			foreach ($this->getDictionaryLanguageOptions() as $lang) {
				$file = $rootDir . '/system/languages/' . $lang . '.php';
				if (file_exists($file)) {
					return include($file);
				}
			}

			return [];
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
