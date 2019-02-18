<?php
/*
	Class: textpage
	handles html pages

	See Also:
	<Page>
*/
	class textpage extends Page {
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

		const CMD_EDIT = 'edit';
		const CMD_TRANSLATE = 'translate';
		const CMD_REVERT = 'revert';
		const CMD_DELETE = 'delete';

		public function __construct($pageListNode, RequestContext $O_O) {
			parent::__construct($pageListNode, $O_O);
			$this->language = $O_O->getContentLanguage();
			$this->hyphaUser = $O_O->getUser();
			$this->xml = new Xml(get_called_class(), Xml::multiLingualOn, Xml::versionsOn);
			$this->xml->loadFromFile('data/pages/' . $pageListNode->getAttribute('id'));
		}

		public function build(){
			return $this->process();
		}

		public function process() {
			$this->html->writeToElement('pagename', showPagename($this->pagename) . ' ' . asterisk($this->privateFlag));

			$request = $this->O_O->getRequest();

			switch ($request->getArgs()[0]) {
				default:
					return ['404'];
				case null:
					return $this->indexView();
				case self::PATH_EDIT:
					if ($request->isPost()) {
						return $this->editAction();
					}
					return $this->editView();
				case self::PATH_TRANSLATE:
					if ($request->isPost()) {
						return $this->translateAction();
					}
					return $this->translateView();
				case self::PATH_REVERT:
					if ($request->isPost()) {
						return $this->revertAction();
					}
				case self::PATH_DELETE:
					if ($request->isPost()) {
						return $this->deleteAction();
					}
			}

			return null;
		}

		private function indexView() {
			// setup page name and language list for the selected page
			$this->html->writeToElement('langList', hypha_indexLanguages($this->pageListNode, $this->language));

			// show content, and only allow access to previous revisions for logged in clients
			$version = isUser() && $this->O_O->getRequest()->getPostValue(self::FIELD_NAME_VERSION);
			$this->html->writeToElement('main', $this->getContent($version));

			// setup addition widgets when client is logged in
			if (isUser()) {
				// show a drop down list of revisions
				$this->html->writeToElement('versionList', versionSelector($this));

				// if a revision is selected, show a 'revert' command button
				if ($version) {
					$commands = $this->findBySelector('#pageCommands');
					$commands->append($this->makeActionButton(__('revert'), self::PATH_REVERT, self::CMD_REVERT));
				}
				// if the latest revision is selected, show 'edit' and 'translate' command buttons
				else {
					$commands = $this->findBySelector('#pageCommands');
					$commands->append($this->makeActionButton(__('edit'), self::PATH_EDIT));
					$commands->append($this->makeActionButton(__('translate'), self::PATH_TRANSLATE));

					if (isAdmin()) {
						$path = $this->language . '/' . $this->pagename . '/' . self::PATH_DELETE;
						$commands->append(makeButton(__('delete'), 'if(confirm(\'' . __('sure-to-delete') . '\'))' . makeAction($path, self::CMD_DELETE, '')));
					}
				}
			}

			//return redirect('sdfasdf');
		}

		private function notifyAndReturnRedirect($msg, $page) {
			notify('error', $msg);

			return ['redirect', $this->constructFullPath($page)];
		}

		private function editView() {
			if (!isUser()) {
				return ['errors' => ['art-login-preform-action']];
			}
			if (!isUser()) {
				notify('error', __('art-login-preform-action'));

				return ['redirect', $this->constructFullPath($this->pagename)];
			}
			if (!isUser()) {
				return $this->notifyAndReturnRedirect(__('art-login-preform-action'), $this->pagename);
			}
			$formData = [
				self::FIELD_NAME_PAGE_NAME => showPagename($this->pagename),
				self::FIELD_NAME_PRIVATE => $this->privateFlag,
				self::FIELD_NAME_CONTENT => $this->getContent(),
			];

			// create form
			$form = $this->createEditForm($formData);
			$form->updateDom();

			$this->findBySelector('#main')->append($form->elem->children());
			return null;
		}

		private function editAction() {
			if ($this->checkCommand(self::CMD_EDIT)) {
				// create form
				$form = $this->createEditForm($this->O_O->getRequest()->getPostData());

				// process form if it was posted
				if (empty($form->errors)) {
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
			} else {
				return ['404'];
			}
		}

		private function translateView() {
			$formData = [
				self::FIELD_NAME_PAGE_NAME => showPagename($this->pagename),
				self::FIELD_NAME_CONTENT => $this->getContent(),
			];

			// create form
			$form = $this->createTranslationForm($formData);

			$this->findBySelector('#main')->append($form->elem->children());
			return null;
		}

		private function translateAction() {
			if ($this->checkCommand(self::CMD_TRANSLATE)) {
				// create form
				$form = $this->createTranslationForm($this->O_O->getRequest()->getPostData());

				// process form if it was posted
				if (empty($form->errors)) {
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
			} else {
				return ['404'];
			}
		}

		private function revertAction() {
			if ($this->checkCommand(self::CMD_REVERT)) {
				$version = $this->O_O->getRequest()->getPostValue(self::FIELD_NAME_VERSION);

				$hyphaUser = $this->hyphaUser;
				$this->xml->lockAndReload();
				storeWikiContent($this->xml->documentElement, $this->language, $this->getContent($version), $hyphaUser->getAttribute('username'));
				$this->xml->saveAndUnlock();
				writeToDigest($hyphaUser->getAttribute('fullname').__('reverted-page').'<a href="'.$this->language.'/'.$this->pagename.'">'.$this->language.'/'.$this->pagename.'</a>', 'page update', $this->pageListNode->getAttribute('id'));

				notify('success', ucfirst(__('page-successfully-updated')));
				return ['redirect', $this->constructFullPath($this->pagename)];
			} else {
				return ['404'];
			}
		}

		private function deleteAction() {
			if (!isAdmin()) {
				return ['errors' => ['art-insufficient-rights-to-preform-action']];
			}
			if ($this->checkCommand(self::CMD_DELETE)) {
				global $hyphaUrl;

				$this->deletePage();

				notify('success', ucfirst(__('page-successfully-deleted')));
				return ['redirect', $hyphaUrl];
			} else {
				return ['404'];
			}

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
			$commands->append($this->makeActionButton(__('save'), self::PATH_EDIT, self::CMD_EDIT));

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
			$commands->append($this->makeActionButton(__('save'), self::PATH_TRANSLATE, self::CMD_TRANSLATE));

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
		 * @todo [LRM]: move so it can be used throughout Hypha
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
		 * @todo [LRM]: move so it can be used throughout Hypha
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
		 * @todo [LRM]: move so it can be used throughout Hypha
		 * @param string $selector
		 *
		 * @return \DOMWrap\NodeList
		 */
		protected function findBySelector($selector) {
			return $this->html->find($selector);
		}

		/**
		 * @todo [LRM]: move so it can be used throughout Hypha
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
		 * @todo [LRM]: move so it can be used throughout Hypha
		 * @param null|string $command
		 *
		 * @return bool
		 */
		protected function checkCommand($command) {
			return $command === $this->O_O->getRequest()->getPostValue('command');
		}
	}
