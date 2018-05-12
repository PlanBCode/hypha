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
		 * @return string
		 */
		protected function getDictionaryLanguage() {
			$interfaceLanguage = $this->getInterfaceLanguage();

			return file_exists('system/languages/' . $interfaceLanguage . '.php') ? $interfaceLanguage : 'en';
		}

		/**
		 * @return array
		 */
		public function getDictionary() {
			return include('system/languages/' . $this->getDictionaryLanguage() . '.php');
		}

		/**
		 * @return bool
		 */
		public function isSystemPage() {
			return $this->hyphaRequest->isSystemPage();
		}

		/**
		 * @return string|null
		 */
		public function getSystemPage() {
			return $this->hyphaRequest->getSystemPage();
		}

		/**
		 * @return string|null
		 */
		public function getPageName() {
			return $this->hyphaRequest->getPageName();
		}

		/**
		 * @return array
		 */
		public function getArgs() {
			return $this->hyphaRequest->getArgs();
		}
	}
