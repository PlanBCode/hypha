<?php
/*
	Class: webpagina2
	handles html pages

	See Also:
	<Page>
*/
	class webpagina2 extends Page {
		/** @var Xml */
		public $xml;

		/** @var DOMElement */
		private $hyphaUser;

		const FIELD_NAME_PAGE_NAME = 'textPagename';
		const FIELD_NAME_LANGUAGE = 'textLanguage';
		const FIELD_NAME_PRIVATE = 'textPrivate';
		const FIELD_NAME_CONTENT = 'textContent';
		const FIELD_NAME_VERSION = 'version';

		const PATH_EDIT = 'edit';
		const PATH_TRANSLATE = 'translate';
		const PATH_REVERT = 'revert';
		const PATH_DELETE = 'delete';

		const FORM_CMD_EDIT = 'edit';
		const FORM_CMD_TRANSLATE = 'translate';
		const FORM_CMD_REVERT = 'revert';
		const FORM_CMD_DELETE = 'delete';

		public function __construct($pageListNode, $args) {
			global $hyphaUser, $hyphaContentLanguage;
			parent::__construct($pageListNode, $args);
			$this->language = $hyphaContentLanguage;
			$this->hyphaUser = $hyphaUser;
			$this->xml = new Xml(get_called_class(), Xml::multiLingualOn, Xml::versionsOn);
			$this->xml->loadFromFile('data/pages/'.$pageListNode->getAttribute('id'));
		}

		function build() {
			$this->html->writeToElement('pagename', showPagename($this->pagename) . ' ' . asterisk($this->privateFlag));

			// By default a user can preform any action, non users can only visit the index
			$valid = isUser() || null === $this->getArg(0);

			// Only admins can delete a page
			if (self::PATH_DELETE === $this->getArg(0) && !isAdmin()) {
				$valid = false;
			}
			if (!$valid) {
				$msg = isUser() ? 'art-insufficient-rights-to-preform-action' : 'art-login-preform-action';
				notify('error', __($msg));

				return ['redirect', $this->constructFullPath($this->pagename)];
			}

			$version = isset($_POST[self::FIELD_NAME_VERSION]) ? $_POST[self::FIELD_NAME_VERSION] : null;
			unset($_POST[self::FIELD_NAME_VERSION]);

			switch ($this->getArg(0)) {
				case null:
					return $this->indexAction();
				case self::PATH_EDIT:
					return $this->editAction();
				case self::FORM_CMD_TRANSLATE:
					return $this->translateAction();
				case self::FORM_CMD_REVERT:
					return $this->revertAction($version);
				case self::PATH_DELETE:
					return $this->deleteAction();
			}

			return null;
		}

		private function indexAction() {
			// setup page name and language list for the selected page
			$this->html->writeToElement('langList', hypha_indexLanguages($this->pageListNode, $this->language));

			// show content, and only allow access to previous revisions for logged in clients
            $version = isUser() && isset($_POST['version']) ? $_POST['version'] : '';
			$this->html->writeToElement('main', $this->getContent($version));

			// setup addition widgets when client is logged in
			if (isUser()) {
				// show a drop down list of revisions
				$this->html->writeToElement('versionList', versionSelector($this));

				// if a revision is selected, show a 'revert' command button
				if (isset($_POST['version']) && $_POST['version']!='') {
					$commands = $this->findBySelector('#pageCommands');
					$commands->append($this->makeActionButton(__(self::PATH_REVERT), self::PATH_REVERT, self::FORM_CMD_REVERT));
				}
				// if the latest revision is selected, show 'edit' and 'translate' command buttons
				else {
					$commands = $this->findBySelector('#pageCommands');
					$commands->append($this->makeActionButton(__(self::PATH_EDIT), self::PATH_EDIT));
					$commands->append($this->makeActionButton(__(self::PATH_TRANSLATE), self::PATH_TRANSLATE));

					if (isAdmin()) {
						$path = $this->language . '/' . $this->pagename . '/' . self::PATH_DELETE;
						$commands->append(makeButton(__(self::PATH_DELETE), 'if(confirm(\'' . __('sure-to-delete') . '\'))' . makeAction($path, self::FORM_CMD_DELETE, '')));
					}
				}
			}
		}

		private function editAction() {
			// check if form is posted and get form data
			$formPosted = $this->isPosted(self::FORM_CMD_EDIT);
			if ($formPosted) {
				$formData = $_POST;
			} else {
				$formData = [
					self::FIELD_NAME_PAGE_NAME => showPagename($this->pagename),
					self::FIELD_NAME_PRIVATE => $this->privateFlag,
					self::FIELD_NAME_CONTENT => $this->getContent(),
				];
			}

			// create form
			$form = $this->createEditForm($formData);

			// process form if it was posted
			if ($formPosted && empty($form->errors)) {
				$pagename = validatePagename($form->dataFor(self::FIELD_NAME_PAGE_NAME));
				$private = $form->dataFor(self::FIELD_NAME_PRIVATE, false);
				$content = wikify_html($form->dataFor(self::FIELD_NAME_CONTENT));

				$this->savePage($content, $pagename, null, $private);

				notify('success', ucfirst(__('page-successfully-updated')));
				return ['redirect', $this->constructFullPath($pagename)];
			}

			// update the form dom so that error can be displayed, if there are any
			$form->updateDom();

			$this->findBySelector('#main')->append($form->elem->children());
			return null;
		}

		private function translateAction() {
			// check if form is posted and get form data
			$formPosted = $this->isPosted(self::FORM_CMD_TRANSLATE);
			if ($formPosted) {
				$formData = $_POST;
			} else {
				$formData = [
					self::FIELD_NAME_PAGE_NAME => showPagename($this->pagename),
					self::FIELD_NAME_CONTENT => $this->getContent(),
				];
			}

			// create form
			$form = $this->createTranslationForm($formData);

			// process form if it was posted
			if ($formPosted && empty($form->errors)) {
				$language = $form->dataFor(self::FIELD_NAME_LANGUAGE);
				$pagename = validatePagename($form->dataFor(self::FIELD_NAME_PAGE_NAME));
				$content = wikify_html($form->dataFor(self::FIELD_NAME_CONTENT));

				$this->savePage($content, $pagename, $language);

				notify('success', ucfirst(__('page-successfully-updated')));
				return ['redirect', $this->constructFullPath($pagename, $language)];
			}

			// update the form dom so that error can be displayed, if there are any
			$form->updateDom();

			$this->findBySelector('#main')->append($form->elem->children());
			return null;
		}

		private function revertAction($version) {
			// check if form is posted and get form data
			$formPosted = $this->isPosted(self::FORM_CMD_REVERT);
			if (!$formPosted) {
				return ['redirect', $this->constructFullPath($this->pagename)];
			}

			$hyphaUser = $this->hyphaUser;
			$this->xml->lockAndReload();
			storeWikiContent($this->xml->documentElement, $this->language, $this->getContent($version), $hyphaUser->getAttribute('username'));
			$this->xml->saveAndUnlock();
			writeToDigest($hyphaUser->getAttribute('fullname').__('reverted-page').'<a href="'.$this->language.'/'.$this->pagename.'">'.$this->language.'/'.$this->pagename.'</a>', 'page update', $this->pageListNode->getAttribute('id'));

			notify('success', ucfirst(__('page-successfully-updated')));
			return ['redirect', $this->constructFullPath($this->pagename)];
		}

		private function deleteAction() {
			// check if form is posted and get form data
			$formPosted = $this->isPosted(self::FORM_CMD_DELETE);
			if (!$formPosted) {
				return ['redirect', $this->constructFullPath($this->pagename)];
			}

			global $hyphaUrl;

			$this->deletePage();

			notify('success', ucfirst(__('page-successfully-deleted')));
			return ['redirect', $hyphaUrl];
		}

		/**
		 * @param array $data
		 *
		 * @return WymHTMLForm
		 */
		private function createEditForm(array $data = []) {
			$title = __('title');
			$titleFieldName = self::FIELD_NAME_PAGE_NAME;
			$private = __('private-page');
			$privateFieldName = self::FIELD_NAME_PRIVATE;
			$contentFieldName = self::FIELD_NAME_CONTENT;
			$html = <<<EOF
			<div class="section" style="padding:5px; margin-bottom:5px; position:relative;">
                <strong><label for="$titleFieldName">$title</label></strong> <input type="text" id="$titleFieldName" name="$titleFieldName" onblur="validatePagename(this);" onkeyup="validatePagename(this);" />
                <input type="checkbox" id="$privateFieldName" name="$privateFieldName" /><label for="$privateFieldName">$private</label>
            </div>
            <editor name="$contentFieldName"></editor>
EOF;
			/** @var HyphaDomElement $form */
			$form = $this->html->createElement('form');
			/** @var \DOMWrap\Element $elem */
			$elem = $form->html($html);

			// buttons
			$commands = $this->findBySelector('#pageCommands');
			$commands->append($this->makeActionButton(__('cancel')));
			$commands->append($this->makeActionButton(__('save'), self::PATH_EDIT, self::FORM_CMD_EDIT));

			return $this->createForm($elem, $data);
		}

		/**
		 * @param array $data
		 *
		 * @return WymHTMLForm
		 */
		private function createTranslationForm(array $data = []) {
			$language = __('language');
			$languageFieldName = self::FIELD_NAME_LANGUAGE;
			$selectedLanguage = isset($data[self::FIELD_NAME_LANGUAGE]) ? $data[self::FIELD_NAME_LANGUAGE] : null;
			$languageOptionList = languageOptionList($selectedLanguage, $this->language);
			$title = __('title');
			$titleFieldName = self::FIELD_NAME_PAGE_NAME;
			$contentFieldName = self::FIELD_NAME_CONTENT;
			$html = <<<EOF
			<div class="section" style="padding:5px; margin-bottom:5px; position:relative;">
                <strong><label for="$languageFieldName">$language</label></strong> <select id="$languageFieldName" name="$languageFieldName">$languageOptionList</select>
            </div>
			<div class="section" style="padding:5px; margin-bottom:5px; position:relative;">
                <strong><label for="$titleFieldName">$title</label></strong> <input type="text" id="$titleFieldName" name="$titleFieldName" onblur="validatePagename(this);" onkeyup="validatePagename(this);" />
            </div>
            <editor name="$contentFieldName"></editor>
EOF;
			/** @var HyphaDomElement $form */
			$form = $this->html->createElement('form');
			/** @var \DOMWrap\Element $elem */
			$elem = $form->html($html);

			// buttons
			$commands = $this->findBySelector('#pageCommands');
			$commands->append($this->makeActionButton(__('cancel')));
			$commands->append($this->makeActionButton(__('save'), self::PATH_TRANSLATE, self::FORM_CMD_TRANSLATE));

			return $this->createForm($elem, $data);
		}

		private function savePage($content, $pagename = null, $language = null, $privateFlag = null) {
			$updateHyphaXml = false;
			foreach (['pagename', 'language', 'privateFlag'] as $argument) {
				if (null === $$argument) {
					$$argument = $this->{$argument};
				} elseif ($$argument !== $this->{$argument}) {
					$updateHyphaXml = true;
				}
			}
			if ($updateHyphaXml) {
				global $hyphaXml;
				$hyphaXml->lockAndReload();
				// After reloading, our page list node might
				// have changed, so find it in the newly loaded
				// XML. This seems a bit dodgy, though...
				$this->replacePageListNode(hypha_getPage($this->language, $this->pagename));
				hypha_setPage($this->pageListNode, $language, $pagename, $privateFlag);
				$hyphaXml->saveAndUnlock();
			}

			// unfortunately wymeditor can't handle relative urls so we'll add the baseUrl before editing and remove it afterwards
			$this->xml->lockAndReload();
			storeWikiContent($this->xml->documentElement, $language, $content, $this->hyphaUser->getAttribute('username'));
			$this->xml->saveAndUnlock();
		}

		public function digest($timestamp) {
			// iterate over all available translations of the page
			/** @var HyphaDomElement[] $langList */
			$langList = $this->xml->getElementsByTagName('language');
			$message = '';
			foreach($langList as $lang) if (ltrim(getCurrentVersionNode($lang)->getAttribute('xml:id'), 't') > $timestamp) {
				$language = $lang->getAttribute('xml:id');
				$pagename = $this->pagename;

				$lastVersion = getVersionBefore($lang, $timestamp);
				$message.= '<hr />';
				$message.= '<div style="font-size: 14pt; font-weight:bold;">'.$pagename.' ('.$language.') - '.($lastVersion ? 'update (last version '.date('j-m-y, H:i', ltrim($lastVersion, 't')).')' : 'new '.(count($langList) > 1 ? 'translation' : 'page')).'</div>';
				$node = $this->xml->documentElement;
				$message.= $lastVersion ? htmlDiff(getWikiContent($node, $language, $lastVersion), getWikiContent($node, $language, '')) : getWikiContent($node, $language, '');
			}

			return $message;
		}

		protected function getContent($version = '') {
            return getWikiContent($this->xml->documentElement, $this->language, $version);
		}

		/**
		 * @param DOMElement $elem
		 * @param array $data
		 *
		 * @return WymHTMLForm
		 */
		protected function createForm(DOMElement $elem, array $data = []) {
			$form = new WymHTMLForm($elem);
			$form->setData($data);

			return $form;
		}

		/**
		 * @param string $label
		 * @param null|string $path
		 * @param null|string $command
		 * @param null|string $argument
		 *
		 * @return string
		 */
		protected function makeActionButton($label, $path = null, $command = null, $argument = null) {
			$path = $this->language . '/' . $this->pagename . ($path ? '/' . $path : '');
			$_action = makeAction($path, ($command ? $command : ''), ($argument ? $argument : ''));

			return makeButton(__($label), $_action);
		}

		/**
		 * @param string $selector
		 *
		 * @return \DOMWrap\NodeList
		 */
		protected function findBySelector($selector) {
			return $this->html->find($selector);
		}

		/**
		 * @param string $path
		 * @param null|string $language
		 *
		 * @return string
		 */
		protected function constructFullPath($path, $language = null) {
			global $hyphaUrl;
			$language = null == $language ? $this->language : $language;
			$path = '' == $path ? '' : '/' . $path;

			return $hyphaUrl . $language . $path;
		}

		/**
		 * @param null|string $command
		 *
		 * @return bool
		 */
		protected function isPosted($command = null) {
			return 'POST' == $_SERVER['REQUEST_METHOD'] && (null == $command || $command == $_POST['command']);
		}
	}
