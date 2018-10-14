<?php
/*
        Module: default_datatype



	File page features.
 */
require_once __DIR__ . '/../core/WymHTMLForm.php';

use DOMWrap\NodeList;

/*
	Class: default_datatype
*/

class default_datatype extends Page {

	const FIELD_NAME_DESCRIPTION = 'description';
	const FIELD_NAME_PRIVATE = 'private';
	const FIELD_NAME_PAGE_NAME = 'page_name';

	const FORM_CMD_LIST_EDIT = 'edit';

	/** @var Xml */
	private $xml;

	/**
	 * @param DOMElement $pageListNode
	 * @param array $args
	 */
	public function __construct(DOMElement $pageListNode, $args) {
		global $O_O;
		parent::__construct($pageListNode, $args);
		$this->xml = new Xml('file', Xml::multiLingualOff, Xml::versionsOff);
		$this->xml->loadFromFile('data/pages/' . $pageListNode->getAttribute('id'));
		$this->language = $O_O->getContentLanguage();
	}

	/**
	 * @return HyphaDomElement|DOMElement
	 */
	private function getDoc() {
		return $this->xml->documentElement;
	}

	public function build() {
		$this->html->writeToElement('pagename', showPagename($this->pagename).' '.asterisk($this->privateFlag));

		$firstArgument = $this->getArg(0);

		switch ($firstArgument) {
			default:
			case null:
				return $this->indexAction();
			case 'edit':
				return $this->editAction();
		}
	}

	public function indexAction() {
		// add edit button for registered users
		if (isUser()) {
			$commands = $this->findBySelector('#pageCommands');
			$commands->append($this->makeActionButton(__('edit'), 'edit'));
		}

		// display page name and description
		/** @var HyphaDomElement $description */
		$description = $this->getDoc()->getOrCreate('description');
		$html = $description->getHtml();
		$this->html->writeToElement('main', $html);

		return null;
	}

	private function editAction() {
		// throw error if edit is requested without client logged in
		if (!isUser()) {
			notify('error', __('login-to-edit'));

			return null;
		}

		// check if form is posted and get form data
		$formPosted = $this->isPosted(self::FORM_CMD_LIST_EDIT);
		if ($formPosted) {
			$formData = $_POST;
		} else {
			$formData = [
				self::FIELD_NAME_PAGE_NAME => showPagename($this->pagename),
				self::FIELD_NAME_PRIVATE => $this->privateFlag,
				self::FIELD_NAME_DESCRIPTION => $this->getDoc()->getOrCreate('description'),
			];
		}

		// create form
		$form = $this->createEditForm($formData);

		// process form if it was posted
		if ($formPosted) {
			if (empty($form->errors)) {
				global $hyphaXml;

				$pagename = validatePagename($form->dataFor(self::FIELD_NAME_PAGE_NAME));
				$private = $form->dataFor(self::FIELD_NAME_PRIVATE, false);

				$hyphaXml->lockAndReload();
				// After reloading, our page list node might
				// have changed, so find it in the newly loaded
				// XML. This seems a bit dodgy, though...
				$this->replacePageListNode(hypha_getPage($this->language, $this->pagename));
				// check if pagename and/or privateFlag have changed
				if ($pagename != $this->pagename || $private != $this->privateFlag) {
					hypha_setPage($this->pageListNode, $this->language, $pagename, $private);
					$hyphaXml->saveAndUnlock();
				} else {
					$hyphaXml->unlock();
				}

				$this->xml->lockAndReload();
				/** @var HyphaDomElement $description */
				$description = $this->getDoc()->getOrCreate('description');
				$description->setHtml(wikify_html($form->dataFor(self::FIELD_NAME_DESCRIPTION)));
				$this->xml->saveAndUnlock();

				notify('success', ucfirst(__('ml-successfully-updated')));
				return ['redirect', $this->constructFullPath($pagename)];
			}
		}

		// update the form dom so that error can be displayed, if there are any
		$form->updateDom();

		$this->findBySelector('#main')->append($form->elem->children());

		return null;
	}

	/**
	 * @param array $data
	 *
	 * @return WymHTMLForm
	 */
	private function createEditForm(array $data = []) {
		$title = __('title');
		$pageNameFieldName = self::FIELD_NAME_PAGE_NAME;
		$privatePage = __('private-page');
		$privateFieldName = self::FIELD_NAME_PRIVATE;
		$description = __('description');
		$descriptionFieldName = self::FIELD_NAME_DESCRIPTION;
		$html = <<<EOF
			<div class="section" style="padding:5px; margin-bottom:5px; position:relative;">
				<strong><label for="$pageNameFieldName">$title</label></strong> <input type="text" id="$pageNameFieldName" name="$pageNameFieldName" />
				<strong><label for="$privateFieldName"> $privatePage </label></strong><input type="checkbox" name="$privateFieldName" id="$privateFieldName" />
			</div>
			<div class="section" style="padding:5px; margin-bottom:5px; position:relative;">
				<strong><label for="$descriptionFieldName"> $description </label></strong><editor name="$descriptionFieldName"></editor>
			</div>
EOF;
		/** @var HyphaDomElement $form */
		$form = $this->html->createElement('form');
		/** @var \DOMWrap\Element $elem */
		$elem = $form->html($html);

		// buttons
		$commands = $this->findBySelector('#pageCommands');
		$commands->append($this->makeActionButton(__('cancel')));
		$commands->append($this->makeActionButton(__('save'), 'edit', self::FORM_CMD_LIST_EDIT));

		return $this->createForm($elem, $data);
	}

	/**
	 * @todo [LRM]: move so it can be used throughout Hypha
	 * @param DOMElement $elem
	 * @param array $data
	 *
	 * @return WymHTMLForm
	 */
	private function createForm(DOMElement $elem, array $data = []) {
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
	private function makeActionButton($label, $path = null, $command = null, $argument = null) {
		$path = $this->language . '/' . $this->pagename . ($path ? '/' . $path : '');
		$_action = makeAction($path, ($command ? $command : ''), ($argument ? $argument : ''));

		return makeButton(__($label), $_action);
	}

	/**
	 * @todo [LRM]: move so it can be used throughout Hypha
	 * @param string $selector
	 *
	 * @return NodeList
	 */
	private function findBySelector($selector) {
		return $this->html->find($selector);
	}

	/**
	 * @todo [LRM]: move so it can be used throughout Hypha
	 * @param string $path
	 * @param null|string $language
	 *
	 * @return string
	 */
	private function constructFullPath($path, $language = null) {
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
	private function isPosted($command = null) {
		return 'POST' == $_SERVER['REQUEST_METHOD'] && (null == $command || $command == $_POST['command']);
	}
}
