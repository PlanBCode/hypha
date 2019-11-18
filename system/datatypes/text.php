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

		const FIELD_NAME_PAGE_NAME = 'textPagename';
		const FIELD_NAME_LANGUAGE = 'textLanguage';
		const FIELD_NAME_PRIVATE = 'textPrivate';
		const FIELD_NAME_CONTENT = 'textContent';
		const FIELD_NAME_VERSION = 'version';

		const PATH_EDIT = 'edit';
		const PATH_TRANSLATE = 'translate';
		const PATH_REVERT = 'revert';
		const PATH_DELETE = 'delete';

		const CMD_SAVE = 'edit';
		const CMD_TRANSLATE = 'translate';
		const CMD_REVERT = 'revert';
		const CMD_DELETE = 'delete';

		public function __construct($pageListNode, RequestContext $O_O) {
			parent::__construct($pageListNode, $O_O);
			$this->xml = new Xml(get_called_class(), Xml::multiLingualOn, Xml::versionsOn);
			$this->xml->loadFromFile('data/pages/' . $pageListNode->getAttribute('id'));
		}

		public function process(HyphaRequest $request) {
			$this->html->writeToElement('pagename', showPagename($this->pagename) . ' ' . asterisk($this->privateFlag));

			switch ([$request->getView(), $request->getCommand()]) {
				case [null,                 null]:                return $this->indexView($request);
				case [null,                 self::CMD_REVERT]:    return $this->revertAction($request);
				case [null,                 self::CMD_DELETE]:    return $this->deleteAction($request);
				case [self::PATH_EDIT,      null]:                return $this->editView($request);
				case [self::PATH_EDIT,      self::CMD_SAVE]:      return $this->editAction($request);
				case [self::PATH_TRANSLATE, null]:                return $this->translateView($request);
				case [self::PATH_TRANSLATE, self::CMD_TRANSLATE]: return $this->translateAction($request);
			}

			return '404';
		}

		private function indexView(HyphaRequest $request) {
			// setup page name and language list for the selected page
			$this->html->writeToElement('langList', hypha_indexLanguages($this->pageListNode, $this->language));

			// show content, and only allow access to previous revisions for logged in clients
			$version = isUser() && $request->getPostValue(self::FIELD_NAME_VERSION);
			$this->html->writeToElement('main', $this->getContent($version));

			// setup addition widgets when client is logged in
			if (isUser()) {
				// show a drop down list of revisions
				$this->html->writeToElement('versionList', versionSelector($this));

				// if a revision is selected, show a 'revert' command button
				if ($version) {
					$commands = $this->html->find('#pageCommands');
					$commands->append($this->makeActionButton(__('revert'), self::PATH_REVERT, self::CMD_REVERT));
				}
				// if the latest revision is selected, show 'edit' and 'translate' command buttons
				else {
					$commands = $this->html->find('#pageCommands');
					$commands->append($this->makeActionButton(__('edit'), self::PATH_EDIT));
					$commands->append($this->makeActionButton(__('translate'), self::PATH_TRANSLATE));

					if (isAdmin()) {
						$path = $this->language . '/' . $this->pagename;
						$commands->append(makeButton(__('delete'), 'if(confirm(\'' . __('sure-to-delete') . '\'))' . makeAction($path, self::CMD_DELETE, '')));
					}
				}
			}
		}

		/**
		 * @param array $values
		 *
		 * @return WymHTMLForm
		 */
		private function createEditForm(array $values = []) {
			$html = <<<EOF
				<div class="section">
					<label for="[[titleFieldName]]">[[title]]</label>
					<input type="text" id="[[titleFieldName]]" name="[[titleFieldName]]" onblur="validatePagename(this);" onkeyup="validatePagename(this);" />
					<input type="checkbox" id="[[privateFieldName]]" name="[[privateFieldName]]" />
					<label for="[[privateFieldName]]">[[private]]</label>
				</div>
				<editor name="[[contentFieldName]]"/>
EOF;
			$vars = [
				'title' => __('title'),
				'titleFieldName' => self::FIELD_NAME_PAGE_NAME,
				'private' => __('private-page'),
				'privateFieldName' => self::FIELD_NAME_PRIVATE,
				'contentFieldName' => self::FIELD_NAME_CONTENT,
			];

			$html = hypha_substitute($html, $vars);

			return new WymHTMLForm($html, $values);
		}

		private function editView(HyphaRequest $request) {
			if (!isUser()) {
				notify('error', __('login-to-perform-action'));

				return ['redirect', $this->constructFullPath($this->pagename)];
			}

			// create form
			$formData = [
				self::FIELD_NAME_PAGE_NAME => showPagename($this->pagename),
				self::FIELD_NAME_PRIVATE => $this->privateFlag,
				self::FIELD_NAME_CONTENT => $this->getContent(),
			];
			$form = $this->createEditForm($formData);

			return $this->editViewRender($request, $form);
		}

		private function editViewRender(HyphaRequest $request, HTMLForm $form) {
			// update the form dom so that error can be displayed, if there are any
			$form->updateDom();

			$this->html->find('#main')->append($form);

			$commands = $this->html->find('#pageCommands');
			$commands->append($this->makeActionButton(__('save'), self::PATH_EDIT, self::CMD_SAVE));
			$commands->append($this->makeActionButton(__('cancel'), ''));

			return null;
		}

		private function editAction(HyphaRequest $request) {
			if (!isUser()) {
				notify('error', __('login-to-preform-action'));

				return ['redirect', $this->constructFullPath($this->pagename)];
			}

			// create form
			$form = $this->createEditForm($request->getPostData());

			// validate
			$form->validateRequiredField(self::FIELD_NAME_PAGE_NAME);

			// process form if it was posted
			if (!empty($form->errors)) {
				return $this->editViewRender($request, $form);
			}

			$pagename = validatePagename($form->dataFor(self::FIELD_NAME_PAGE_NAME));
			$private = $form->dataFor(self::FIELD_NAME_PRIVATE, false);
			$content = wikify_html($form->dataFor(self::FIELD_NAME_CONTENT));

			$error = $this->savePage($content, $pagename, null, $private);

			if ($error !== false) {
				notify('error', $error);
				return $this->editViewRender($request, $form);
			}
			notify('success', ucfirst(__('page-successfully-updated')));

			return ['redirect', $this->constructFullPath($pagename)];
		}

		private function translateView(HyphaRequest $request) {
			if (!isUser()) {
				notify('error', __('login-to-preform-action'));

				return ['redirect', $this->constructFullPath($this->pagename)];
			}

			$formData = [
				self::FIELD_NAME_PAGE_NAME => showPagename($this->pagename),
				self::FIELD_NAME_CONTENT => $this->getContent(),
			];

			// create form
			$form = $this->createTranslationForm($formData);
			$form->updateDom();

			$this->html->find('#main')->append($form);
			return null;
		}

		private function translateAction(HyphaRequest $request) {
			if (!isUser()) {
				notify('error', __('login-to-preform-action'));

				return ['redirect', $this->constructFullPath($this->pagename)];
			}

			// create form
			$form = $this->createTranslationForm($request->getPostData());

			// validate
			$form->validateRequiredField(self::FIELD_NAME_PAGE_NAME);
			// TODO: validate that the language is a valid one
			$form->validateRequiredField(self::FIELD_NAME_LANGUAGE);

			// process form if it was posted
			if (!empty($form->errors)) {
				// update the form dom so that error can be displayed, if there are any
				$form->updateDom();

				$this->html->find('#main')->append($form);
				return null;
			}

			$language = $form->dataFor(self::FIELD_NAME_LANGUAGE);
			$pagename = validatePagename($form->dataFor(self::FIELD_NAME_PAGE_NAME));
			$content = wikify_html($form->dataFor(self::FIELD_NAME_CONTENT));

			$error = $this->savePage($content, $pagename, $language);

			if ($error) {
				notify('error', $error);
				$form->updateDom();
				$this->html->find('#main')->append($form);
				return null;
			}
			notify('success', ucfirst(__('page-successfully-updated')));

			return ['redirect', $this->constructFullPath($pagename, $language)];
		}

		private function revertAction(HyphaRequest $request) {
			if (!isUser()) {
				notify('error', __('login-to-preform-action'));

				return ['redirect', $this->constructFullPath($this->pagename)];
			}

			$version = $request->getPostValue(self::FIELD_NAME_VERSION);

			$hyphaUser = $this->O_O->getUser();
			$this->xml->lockAndReload();
			storeWikiContent($this->xml->documentElement, $this->language, $this->getContent($version), $hyphaUser->getAttribute('username'));
			$this->xml->saveAndUnlock();
			writeToDigest($hyphaUser->getAttribute('fullname').__('reverted-page').'<a href="'.$this->language.'/'.$this->pagename.'">'.$this->language.'/'.$this->pagename.'</a>', 'page update', $this->pageListNode->getAttribute('id'));

			notify('success', ucfirst(__('page-successfully-updated')));
			return ['redirect', $this->constructFullPath($this->pagename)];
		}

		private function deleteAction(HyphaRequest $request) {
			if (!isAdmin()) return notify('error', __('login-as-admin-to-delete'));

			$this->deletePage();

			notify('success', ucfirst(__('page-successfully-deleted')));
			return ['redirect', $request->getRootUrl()];
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
			$commands = $this->html->find('#pageCommands');
			$commands->append($this->makeActionButton(__('cancel')));
			$commands->append($this->makeActionButton(__('save'), self::PATH_TRANSLATE, self::CMD_TRANSLATE));

			return new WymHTMLForm($elem, $data);
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
			$error = false;
			if ($updateHyphaXml) {
				global $hyphaXml;
				$hyphaXml->lockAndReload();
				// After reloading, our page list node might
				// have changed, so find it in the newly loaded
				// XML. This seems a bit dodgy, though...
				$this->replacePageListNode(hypha_getPage($this->language, $this->pagename));
				$error = hypha_setPage($this->pageListNode, $language, $pagename, $privateFlag);
				$hyphaXml->saveAndUnlock();
			}

			if ($error === false) {
				$this->xml->lockAndReload();
				storeWikiContent($this->xml->documentElement, $language, $content, $this->O_O->getUser()->getAttribute('username'));
				$this->xml->saveAndUnlock();
			}
			return $error;
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

			return makeButton($label, $_action);
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
	}
