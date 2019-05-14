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

		const CMD_SAVE = 'edit';
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

			return ['404'];
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
		}

		private function notifyAndReturnRedirect($msg, $page) {
			notify('error', $msg);

			return ['redirect', $this->constructFullPath($page)];
		}

		/**
		 * @param array $values
		 *
		 * @return WymHTMLForm
		 */
		private function createEditForm(array $values = []) {
			$html = <<<EOF
				<form name="form3">
					<div class="section">
						<label for="[[titleFieldName]]">[[title]]</label>
						<input type="text" id="[[titleFieldName]]" name="[[titleFieldName]]" onblur="validatePagename(this);" onkeyup="validatePagename(this);" />
						<input type="checkbox" id="[[privateFieldName]]" name="[[privateFieldName]]" />
						<label for="[[privateFieldName]]">[[private]]</label>
					</div>
					<editor name="[[contentFieldName]]"/>
				</form>
EOF;
			$vars = [
				'title' => __('title'),
				'titleFieldName' => self::FIELD_NAME_PAGE_NAME,
				'private' => __('private-page'),
				'privateFieldName' => self::FIELD_NAME_PRIVATE,
				'contentFieldName' => self::FIELD_NAME_CONTENT,
			];

			$html = hypha_substitute($html, $vars);

			return hypha_createForm($html, $values);
		}

		private function editView(HyphaRequest $request) {
			if (!isUser()) {
				notify('error', __('art-login-preform-action'));

				return ['redirect', $this->constructFullPath($this->pagename)];
			}

			// create form
			$formData = [
				self::FIELD_NAME_PAGE_NAME => showPagename($this->pagename),
				self::FIELD_NAME_PRIVATE => $this->privateFlag,
				self::FIELD_NAME_CONTENT => $this->getContent(),
			];
			$form = $this->createEditForm($formData);

			$main = $this->html->find('#main');
			$main->addForm($form);
			$request->getRelativePagePath();
//			$form = hypha_getDefaultForm($this->html);
			// TODO [LRM]: remove exit!
//			exit('<pre>' . print_r($form->html(), true) . '</pre>' . "\n");

			$commands = $this->html->find('#pageCommands');
			$commands->addButton($form->elem->html(), __('save'), $request->getRelativePagePath() . '/' . self::PATH_EDIT, self::CMD_SAVE);
			$commands->addButton(hypha_getDefaultForm(), __('cancel'), $request->getRelativePagePath());

			return null;
		}

		private function editAction(HyphaRequest $request) {
			if ($this->checkCommand(self::CMD_SAVE)) {
				// create form
				$form = $this->createEditForm($request->getPostData());

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

		private function translateView(HyphaRequest $request) {
			$formData = [
				self::FIELD_NAME_PAGE_NAME => showPagename($this->pagename),
				self::FIELD_NAME_CONTENT => $this->getContent(),
			];

			// create form
			$form = $this->createTranslationForm($formData);

			$this->findBySelector('#main')->append($form->elem->children());
			return null;
		}

		private function translateAction(HyphaRequest $request) {
			if ($this->checkCommand(self::CMD_TRANSLATE)) {
				// create form
				$form = $this->createTranslationForm($request->getPostData());

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

		private function revertAction(HyphaRequest $request) {
			if ($this->checkCommand(self::CMD_REVERT)) {
				$version = $request->getPostValue(self::FIELD_NAME_VERSION);

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

		private function deleteAction(HyphaRequest $request) {
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
		
		
		// SERVICE METHODS

		public function summary() {
			$result = new DOMDocument('1.0', 'UTF-8');
			$result->loadXML('<root></root>');

			$content = getWikiContentNode($this->xml->documentElement, $this->language, '');
			$clone = $result->importNode($content, true);
			truncateHtmlNode($clone, 500);
			$result->documentElement->appendChild($clone);
			return $result;
		}

		public function searchRelevance($pattern) {
			$content = getWikiContent($this->xml->documentElement, $this->language, '');
			$searchResult = searchPatternInText($pattern, $content);
			return $searchResult["relevance"];
		}

		public function searchSummary($pattern, $result) {
			$content = getWikiContent($this->xml->documentElement, $this->language, '');
			$searchResult = searchPatternInText($pattern, $content);
			
			$summary = new DOMDocument('1.0', 'UTF-8');
			$summary->loadXML('<root></root>');
			foreach($searchResult["hitlist"] as $pos) {
				$snippet = truncateHtmlNode($fulltext, 80, $pos-40);
				$div = $summary->createElement('div');
				$div->appendChild($summary->importNode($snippet, true));
				$summary->documentElement->appendChild($div);
			}
			return $summary;
		}

		public static function indexHeaderRow() {
			return [
				"pagename" => [
					"heading" => __('index-pagename')
					"type" => "string",
				],
				"author" => [
					"heading" => __('index-author')
					"type" => "string",
				],
				"lastrevision" => [
					"heading" => __('index-timestamp')
					"type" => "timestamp",
				],
			]
		}
		
		public function indexDataRow() {
			return [
				"pagename" => showPagename($this->pagename),
				"author" => $this->xml->getAttribute('author'),
				"lastrevision" => date('j-m-y, H:i', ltrim($this->xml->getAttribute('xml:id'), 't'))
			];
		}
		
		// TODO: make recursive and offload to dom-wrapper
		// TODO: inmplement 3rd argument for a negative offset
		function truncateHtmlNode($node, $length) {
			$remove = false;
			$content = '';
			$delete = array();

			foreach($node->childNodes as $child) {
				if ($remove) {
					$delete[] = $child;
				} else {
					$content.= $child->nodeValue;
					if (!$remove && strlen($content) >= $length) $remove = true;
				}
			}

			foreach ($delete as $child) {
				$child->parentNode->removeChild($child);
			}
		}
	}
