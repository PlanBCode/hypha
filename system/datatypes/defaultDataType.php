<?php

require_once __DIR__ . '/../core/WymHTMLForm.php';
require_once __DIR__ . '/../core/DataTypeDocPageAwareTrait.php';
require_once __DIR__ . '/../core/EventTrait.php';

use DOMWrap\NodeList;

abstract class defaultDataType extends Page {
	use DataTypeDocPageAwareTrait;
	use EventTrait;

	/** @var Xml */
	private $xml;

	/** @var DOMElement */
	private $hyphaUser;

	/**
	 * @param DOMElement $pageListNode
	 * @param array $args
	 */
	public function __construct(DOMElement $pageListNode, $args) {
		global $hyphaLanguage, $hyphaUser;
		parent::__construct($pageListNode, $args);
		$this->xml = new Xml($this->getType(), Xml::multiLingualOff, Xml::versionsOff);
		$this->xml->loadFromFile('data/pages/' . $pageListNode->getAttribute('id'));
		$this->language = $hyphaLanguage;
		$this->hyphaUser = $hyphaUser;
	}

	/**
	 * @return Xml
	 */
	protected function getXml() {
		return $this->xml;
	}

	/**
	 * @return DOMElement
	 */
	protected function getHyphaUser() {
		return $this->hyphaUser;
	}

	/**
	 * @return string
	 */
	protected function getType() {
		return get_called_class();
	}

	/**
	 * @return string
	 */
	abstract protected function getTitle();

	/**
	 * @return void
	 */
	abstract protected function beforeProcessRequest();

	/**
	 * @return null|array
	 */
	abstract protected function processRequest();

	/**
	 * @return array|null
	 */
	public function build() {
		$this->ensureStructure();
		$this->html->writeToElement('pagename', $this->getTitle() . ' ' . asterisk($this->privateFlag));

		$this->beforeProcessRequest();
		return $this->processRequest();
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
	 * @return NodeList
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
