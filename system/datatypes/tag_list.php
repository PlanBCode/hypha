<?php

/*
 * Module: tag_list
 *
 * Displays pages with tags
 */

use DOMWrap\NodeList;

/*
 * Class: tag_list
 */
class tag_list extends Page {
	/** @var Xml */
	private $xml;

	const FIELD_NAME_PAGE_NAME = 'textPagename';
	const FIELD_NAME_PRIVATE = 'textPrivate';
	const FIELD_NAME_TAG_LIST = 'tag_list';
	const FIELD_NAME_HEADER = 'header';
	const FIELD_NAME_FORMULA = 'formula';
	const FIELD_NAME_LIMIT = 'limit';
	const FIELD_NAME_FOOTER = 'footer';

	const FIELD_VALIDATION_LIMIT_MIN = 1;
	const FIELD_VALIDATION_LIMIT_MAX = 50;


	const PATH_EDIT = 'edit';

	const CMD_SAVE = 'save';
	const CMD_DELETE = 'delete';

	public static function getDatatypeName() {
		return __('datatype.name.tag_list');
	}

	/**
	 * @param DOMElement $pageListNode
	 * @param RequestContext $O_O
	 */
	public function __construct(DomElement $pageListNode, RequestContext $O_O) {
		parent::__construct($pageListNode, $O_O);
		$this->xml = new Xml(get_called_class(), Xml::multiLingualOff, Xml::versionsOff);
		$this->xml->loadFromFile('data/pages/' . $pageListNode->getAttribute('id'));
	}

	/**
	 * @param HyphaRequest $request
	 * @return array|string|null
	 */
	public function process(HyphaRequest $request) {
		$this->html->writeToElement('pagename', showPagename($this->pagename) . ' ' . asterisk($this->privateFlag));

		switch ([$request->getView(), $request->getCommand()]) {
			case [null,            null]:             return $this->defaultView($request);
			case [null,            self::CMD_DELETE]: return $this->deleteAction($request);
			case [self::PATH_EDIT, null]:             return $this->editView($request);
			case [self::PATH_EDIT, self::CMD_SAVE]:   return $this->editAction($request);
		}

		return '404';
	}

	/**
	 * Displays the article.
	 *
	 * @param HyphaRequest $request
	 * @return null
	 */
	public function defaultView(HyphaRequest $request) {
		// add buttons for registered users
		if (isUser()) {
			/** @var HyphaDomElement $commands */
			$commands = $this->html->find('#pageCommands');
			/** @var HyphaDomElement $commandsAtEnd */
			$commands->append($this->makeActionButton(__('edit'), self::PATH_EDIT));

			if (isAdmin()) {
				$path = $this->language . '/' . $this->pagename;
				$commands->append(makeButton(__('delete'), 'if(confirm(\'' . __('sure-to-delete') . '\'))' . makeAction($path, self::CMD_DELETE, '')));
			}
		}

		/** @var HyphaDomElement $main */
		$main = $this->html->find('#main');

		/** @var HyphaDomElement $container */
		$container = $this->xml->documentElement->getOrCreate(self::FIELD_NAME_TAG_LIST);

		// header
		/** @var HyphaDomElement $div */
		$div = $this->html->createElement('div');
		$div->setAttribute('class', 'header');
		$div->append($container->getOrCreate(self::FIELD_NAME_HEADER)->html());
		$main->append($div);

		/** @var HyphaDomElement $formulaEl */
		$formulaEl = $container->getOrCreate(self::FIELD_NAME_FORMULA);
		$formula = $formulaEl->text();
		if ($formula) {
			$limit = $formulaEl->getAttribute(self::FIELD_NAME_LIMIT);
			if (!$limit) {
				$limit = self::FIELD_VALIDATION_LIMIT_MAX;
			}

			// the magic everyone came to see
			/** @var HyphaDomElement $div */
			$div = $this->html->createElement('div');
			$div->setAttribute('class', 'result');
			$div->append($this->getPages($formula, $limit, isUser()));
			$main->append($div);
		}

		// footer
		/** @var HyphaDomElement $div */
		$div = $this->html->createElement('div');
		$div->setAttribute('class', 'footer');
		$div->append($container->getOrCreate(self::FIELD_NAME_FOOTER)->html());
		$main->append($div);

		return null;
	}

	/**
	 * Deletes the article.
	 *
	 * @param HyphaRequest $request
	 * @return array
	 */
	public function deleteAction(HyphaRequest $request) {
		if (!isAdmin()) {
			return ['errors' => ['art-insufficient-rights-to-perform-action']];
		}

		$this->deletePage();

		notify('success', ucfirst(__('page-successfully-deleted')));
		return ['redirect', $request->getRootUrl()];
	}

	/**
	 * Displays the form to edit the article.
	 *
	 * @param HyphaRequest $request
	 * @return array|null
	 */
	public function editView(HyphaRequest $request) {
		if (!isUser()) {
			notify('error', __('login-to-perform-action'));
			return ['redirect', $this->constructFullPath($this->pagename)];
		}

		/** @var HyphaDomElement $container */
		$container = $this->xml->documentElement->getOrCreate(self::FIELD_NAME_TAG_LIST);

		/** @var HyphaDomElement $formulaElement */
		$formulaElement = $container->getOrCreate(self::FIELD_NAME_FORMULA);
		$limit = $formulaElement->getAttribute('limit');

		// create form
		$formData = [
			self::FIELD_NAME_PAGE_NAME => showPagename($this->pagename),
			self::FIELD_NAME_PRIVATE => $this->privateFlag,
			self::FIELD_NAME_HEADER => $container->getOrCreate(self::FIELD_NAME_HEADER)->html(),
			self::FIELD_NAME_FORMULA => $formulaElement->text(),
			self::FIELD_NAME_LIMIT => $limit ? $limit : self::FIELD_VALIDATION_LIMIT_MAX,
			self::FIELD_NAME_FOOTER => $container->getOrCreate(self::FIELD_NAME_FOOTER)->html(),
		];

		$form = $this->createEditForm($formData);

		return $this->editViewRender($request, $form);
	}

	/**
	 * Appends edit form to #main and adds buttons
	 *
	 * @param HyphaRequest $request
	 * @param WymHTMLForm $form
	 * @return null
	 */
	private function editViewRender(HyphaRequest $request, WymHTMLForm $form) {
		// update the form dom so that error can be displayed, if there are any
		$form->updateDom();

		/** @var HyphaDomElement $main */
		$main = $this->html->find('#main');
		$main->append($form);

		/** @var HyphaDomElement $commands */
		$commands = $this->html->find('#pageCommands');
		$commands->append($this->makeActionButton(__('art-cancel')));
		$commands->append($this->makeActionButton(__('art-save'), self::PATH_EDIT, self::CMD_SAVE));
		/** @var HyphaDomElement $commandsAtEnd */
		$commandsAtEnd = $this->html->find('#pageEndCommands');
		$commandsAtEnd->append($this->makeActionButton(__('art-cancel')));
		$commandsAtEnd->append($this->makeActionButton(__('art-save'), self::PATH_EDIT, self::CMD_SAVE));

		return null;
	}

	/**
	 * Updates the article with the posted data.
	 *
	 * @param HyphaRequest $request
	 * @return array|null
	 */
	public function editAction(HyphaRequest $request) {
		if (!isUser()) {
			notify('error', __('login-to-perform-action'));
			return ['redirect', $this->constructFullPath($this->pagename)];
		}

		// create form
		$form = $this->createEditForm($request->getPostData());

		// validate form
		$form->validateRequiredField(self::FIELD_NAME_PAGE_NAME);
		$limit = $form->dataFor(self::FIELD_NAME_LIMIT);
		if (!ctype_digit($limit)) {
			$form->errors[self::FIELD_NAME_LIMIT] = __('field-validation-invalid-integer');
		}
		$limit = (int)$limit;
		if ($limit < self::FIELD_VALIDATION_LIMIT_MIN) {
			$form->errors[self::FIELD_NAME_LIMIT] = __('field-validation-number-lower-then-minimum');
		}
		if ($limit > self::FIELD_VALIDATION_LIMIT_MAX) {
			$form->errors[self::FIELD_NAME_LIMIT] = __('field-validation-number-higher-then-maximum');
		}

		// process form if there are no errors
		if (!empty($form->errors)) {
			return $this->editViewRender($request, $form);
		}

		$pagename = validatePagename($form->dataFor(self::FIELD_NAME_PAGE_NAME));
		$privateFlag = $form->dataFor(self::FIELD_NAME_PRIVATE, false);

		$this->savePage($pagename, $privateFlag);

		$this->xml->lockAndReload();

		/** @var HyphaDomElement $container */
		$container = $this->xml->documentElement->getOrCreate(self::FIELD_NAME_TAG_LIST);

		$container->getOrCreate(self::FIELD_NAME_HEADER)->setHtml($form->dataFor(self::FIELD_NAME_HEADER));
		$container->getOrCreate(self::FIELD_NAME_FOOTER)->setHtml($form->dataFor(self::FIELD_NAME_FOOTER));
		/** @var HyphaDomElement $formulaElement */
		$formulaElement = $container->getOrCreate(self::FIELD_NAME_FORMULA);
		$formulaElement->setText($form->dataFor(self::FIELD_NAME_FORMULA));
		$formulaElement->setAttribute(self::FIELD_NAME_LIMIT, $limit);

		$this->xml->saveAndUnlock();

		notify('success', ucfirst(__('art-successfully-updated')));
		return ['redirect', $this->constructFullPath($pagename)];
	}

	/**
	 * @param array $values
	 *
	 * @return WymHTMLForm
	 */
	private function createEditForm(array $values = []) {
		$html = <<<EOF
			<div class="section" style="padding:5px; margin-bottom:5px; position:relative;">
				<div class="input-wrapper field_type_text field_name_[[field-name-title]]">
					<strong><label for="[[field-name-title]]">[[title]]</label></strong><br><input type="text" id="[[field-name-title]]" name="[[field-name-title]]" onblur="validatePagename(this);" onkeyup="validatePagename(this);" />
					<input type="checkbox" id="[[field-name-private]]" name="[[field-name-private]]" />
					<label for="[[field-name-private]]">[[private]]</label>
				</div>
			</div>
			<div class="section" style="padding:5px; margin-bottom:5px; position:relative;">
				<div class="input-wrapper field_type_editor field_name_[[field-name-header]]">
					<strong><label for="[[field-name-header]]">[[header]]</label></strong>[[info-button-header]]<br><editor name="[[field-name-header]]"></editor>
				</div>
			</div>
			<div class="section" style="padding:5px; margin-bottom:5px; position:relative;">
				<div class="input-wrapper field_type_text field_name_[[field-name-formula]]">
					<strong><label for="[[field-name-formula]]">[[formula]]</label></strong>[[info-button-formula]]<br><input type="text" id="[[field-name-formula]]" name="[[field-name-formula]]" />
				</div>
				<div class="input-wrapper field_type_text field_name_[[field-name-limit]]">
					<strong><label for="[[field-name-limit]]">[[limit]]</label></strong>[[info-button-limit]]<br><input type="number" id="[[field-name-limit]]" min="[[field-validation-limit-min]]" max="[[field-validation-limit-max]]" name="[[field-name-limit]]" />
				</div>
			</div>
			<div class="section" style="padding:5px; margin-bottom:5px; position:relative;">
				<div class="input-wrapper field_type_editor field_name_[[field-name-footer]]">
					<strong><label for="[[field-name-footer]]">[[footer]]</label></strong>[[info-button-footer]]<br><editor name="[[field-name-footer]]"></editor>
				</div>
			</div>
EOF;
		$vars = [
			'title' => __('title'),
			'field-name-title' => self::FIELD_NAME_PAGE_NAME,
			'private' => __('private-page'),
			'field-name-private' => self::FIELD_NAME_PRIVATE,
			'header' => __('tag-list-header'),
			'field-name-header' => self::FIELD_NAME_HEADER,
			'info-button-header' => makeInfoButton('help-tag-list-header'),
			'formula' => __('tag-list-formula'),
			'info-button-formula' => makeInfoButton('help-tag-list-formula'),
			'field-name-formula' => self::FIELD_NAME_FORMULA,
			'limit' => __('tag-list-limit'),
			'info-button-limit' => makeInfoButton('help-tag-list-limit'),
			'field-name-limit' => self::FIELD_NAME_LIMIT,
			'field-validation-limit-min' => self::FIELD_VALIDATION_LIMIT_MIN,
			'field-validation-limit-max' => self::FIELD_VALIDATION_LIMIT_MAX,
			'footer' => __('tag-list-footer'),
			'field-name-footer' => self::FIELD_NAME_FOOTER,
			'info-button-footer' => makeInfoButton('help-tag-list-footer'),
		];

		$html = hypha_substitute($html, $vars);

		return new WymHTMLForm($html, $values);
	}

	/**
	 * @todo [LRM]: move so it can be used throughout Hypha
	 * @param string $label
	 * @param null|string $path
	 * @param null|string $command
	 * @param null|string|array $argument
	 * @return string
	 */
	private function makeActionButton($label, $path = null, $command = null, $argument = null) {
		if (is_array($argument)) {
			$argument = json_encode($argument);
		}
		$path = $this->language . '/' . $this->pagename . ($path ? '/' . $path : '');
		$_action = makeAction($path, ($command ? $command : ''), ($argument ? $argument : ''));

		return makeButton($label, $_action);
	}

	/**
	 * @todo [LRM]: move so it can be used throughout Hypha
	 * @param string $path
	 * @param null|string $language
	 * @return string
	 */
	private function constructFullPath($path, $language = null) {
		global $hyphaUrl;
		$language = null == $language ? $this->language : $language;
		$path = '' == $path ? '' : '/' . $path;

		return $hyphaUrl . $language . $path;
	}

	private function savePage($pagename, $privateFlag) {
		$updateHyphaXml = false;
		foreach (['pagename', 'privateFlag'] as $argument) {
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
			hypha_setPage($this->pageListNode, $this->language, $pagename, $privateFlag);
			$hyphaXml->saveAndUnlock();
		}
	}

	private function getPages($formula, $limit, $includePrivate = false) {
		global $hyphaXml;

		/** @var HyphaDomElement[] $pages */
		$pages = [];

		// Check if tagList exists
		/** @var \DOMWrap\NodeList $tagList */
		$tagList = $hyphaXml->findXPath('hypha/tagList');
		if ($tagList->count() === 0) {
			return $pages;
		}

		$tagIds = [];
		foreach ($hyphaXml->findXPath('hypha/tagList/tag/language[@label=' . xpath_encode($formula) . ']') as $tagLang) {
			/** @var HyphaDomElement $tagLang */
			$tagIds[] = $tagLang->parent()->getId();
		}

		$pages = [];
		foreach ($tagIds as $tagId) {
			/** @var \DOMWrap\NodeList|HyphaDomElement[] $tagInPages */
			$tagInPages = $hyphaXml->findXPath('hypha/pageList/page/tag[@id=' . xpath_encode($tagId) . ']');
			foreach ($tagInPages as $tagInPage) {
				$page = $tagInPage->parent();
				if ($page->getAttribute('private') === 'on' && !$includePrivate) {
					continue;
				}
				$pages[$page->getAttribute('id')] = $page;
				if (count($pages) >= $limit) {
					break 2;
				}
			}
		}

		/** @var HyphaDomElement $ul */
		$ul = $this->html->createElement('ul');
		foreach ($pages as $page) {
			$html = <<<EOF
			<li>
				<a href="[[link]]" class="result-item is-[[private-public]]">
					<div class="featured_image"><img src="[[featured-image-src]]"></div>
					<div class="title">[[title]]</div>
					<div class="excerpt">[[excerpt]]</div>
				</a>
			</li>
EOF;
			/** @var \DOMWrap\NodeList|HyphaDomElement $pageLang */
			$pageLang = $page->find('language[id="'.$this->language.'"]');
			if ($pageLang->count() === 0) {
				$pageLang = $page->find('language');
			}

			$type = $page->getAttribute('type');
			$hyphaPage = new $type($page, $this->O_O);

			$title = method_exists($hyphaPage, 'getTitle') ? $hyphaPage->getTitle() : showPagename($hyphaPage->pagename);
			$featured_image = method_exists($hyphaPage, 'getFeaturedImage') ? $hyphaPage->getFeaturedImage() : '';
			$excerpt = method_exists($hyphaPage, 'getExcerpt') ? $hyphaPage->getExcerpt() : '';

			$vars = [
				'link' => $hyphaPage->language.'/'.$hyphaPage->pagename,
				'private-public' => $hyphaPage->privateFlag ? 'private' : 'public',
				'featured-image-src' => $featured_image,
				'title' => $title,
				'excerpt' => $excerpt,
			];

			$html = hypha_substitute($html, $vars);
			$ul->append($html);
		}

		return $ul;
	}
}
