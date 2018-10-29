<?php

class DocPage {
	/** @var HyphaDomElement */
	private $doc;

	/** @var Xml */
	private $xml;

	/** @var null|DocPage */
	private $parent;

	/** @var DocPage[] */
	private $children = [];

	/**
	 * @param HyphaDomElement $doc
	 */
	public function __construct(HyphaDomElement $doc) {
		$this->doc = $doc;
	}

	/**
	 * @param Xml $xml
	 * @return DocPage
	 */
	public static function build(Xml $xml) {
		$build = function (HyphaDomElement $doc) use (&$build) {
			$pageDoc = new DocPage($doc);
			foreach ($doc->children() as $key => $child) {
				$pageDoc->setChild($build($child));
			}

			return $pageDoc;
		};

		/** @var HyphaDomElement $documentElement */
		$documentElement = $xml->documentElement;

		$pageDoc = $build($documentElement);
		$pageDoc->setXml($xml);

		return $pageDoc;
	}

	/**
	 * @return HyphaDomElement
	 */
	public function getDoc() {
		return $this->doc;
	}

	/**
	 * @return DocPage
	 */
	private function getRoot() {
		if ($this->parent instanceof DocPage) {
			return $this->parent->getRoot();
		}

		return $this;
	}

	/**
	 * @return Xml
	 */
	public function getXml() {
		return $this->getRoot()->xml;
	}

	/**
	 * @param Xml $xml
	 */
	public function setXml(Xml $xml) {
		$this->getRoot()->xml = $xml;
	}

	/**
	 * @return string
	 */
	public function getName() {
		return $this->doc->nodeName;
	}

	/**
	 * @return null|string
	 */
	public function getId() {
		$id = $this->doc->getId();
		if ($id === '') {
			$id = null;
		}
		return $id;
	}

	/**
	 * @return string
	 */
	public function getHtml() {
		return $this->doc->html();
	}

	/**
	 * @param string $html
	 * @param bool $wikify
	 */
	public function setHtml($html, $wikify = false) {
		if ($wikify && $html != '') {
			$html = wikify_html($html);
		}
		$this->doc->html($html);
	}

	/**
	 * @return string
	 */
	public function getText() {
		return $this->doc->text();
	}

	/**
	 * @param string $text
	 */
	public function setText($text) {
		$this->doc->text($text);
	}

	/**
	 * @param string $name
	 * @return string|null
	 */
	public function getAttr($name) {
		return $this->doc->attr($name);
	}

	/**
	 * @param string $name
	 * @param string $value
	 */
	public function setAttr($name, $value) {
		$this->doc->attr($name, $value);
	}

	/**
	 * @param string $name
	 */
	public function setAttrToNow($name) {
		$this->setAttr($name, 't' . time());
	}

	/**
	 * @return DocPage|null
	 */
	public function getParent() {
		return $this->parent;
	}

	/**
	 * @return bool
	 */
	public function hasChildren() {
		return !empty($this->children);
	}

	/**
	 * @return DocPage[]
	 */
	public function getChildren() {
		return $this->children;
	}

	/**
	 * @param string $name
	 * @return DocPage|null
	 */
	public function getChild($name) {
		if (isset($this->children[$name])) {
			return $this->children[$name];
		}

		return null;
	}

	/**
	 * @param DocPage $child
	 * @param bool $append
	 */
	public function setChild(DocPage $child, $append = false) {
		if (!isset($this->children[$child->getName()])) {
			$this->children[$child->getName()] = $child;
		} else {
			if ($this->children[$child->getName()] instanceof DocPage) {
				$this->children[$child->getName()] = [$this->children[$child->getName()]];
			}
			$this->children[$child->getName()][] = $child;
		}
		$child->parent = $this;
		if ($append) {
			$this->doc->append($child->doc);
		}
	}

	/**
	 * @param DocPage $child
	 * @param bool $append
	 */
	public function addChild(DocPage $child, $append = false) {
		if (!isset($this->children[$child->getName()])) {
			$this->children[$child->getName()] = [];
		}
		$this->setChild($child, $append);
	}

	/**
	 * @param string $name
	 * @param bool $generateId
	 * @return DocPage
	 */
	public function createChild($name, $generateId = true) {
		/** @var HyphaDomElement $childDoc */
		$childDoc = $this->getXml()->createElement($name);
		if ($generateId) {
			$childDoc->generateId();
		}
		$child = new DocPage($childDoc);
		$this->addChild($child, true);

		return $child;
	}
}
