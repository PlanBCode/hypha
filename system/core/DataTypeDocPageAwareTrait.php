<?php

require_once __DIR__ . '/DocPage.php';

trait DataTypeDocPageAwareTrait {
	/** @var DocPage */
	private $rootDocPage;

	/** @var array */
	private $docPagesMtx = [
		'id' => [],
		'name' => [],
	];

	/**
	 * @return Xml
	 */
	abstract protected function getXml();

	/**
	 * @return array
	 */
	abstract protected function getDataStructure();

	/**
	 * @param bool $force
	 *
	 * @return bool Indication whether or not the structure has just been created
	 */
	protected function ensureStructure($force = false) {
		if ($this->rootDocPage instanceof DocPage && !$force) {
			return false;
		}
		$this->getXml()->lockAndReload();

		/** @var HyphaDomElement $documentElement */
		$documentElement = $this->getXml()->documentElement;
		$new = $documentElement->children()->count() === 0;
		if (!$new) {
			// build structure from XML
			$this->rootDocPage = DocPage::build($this->getXml());
			$this->getXml()->unlock();

			return $new;
		}

		$build = function (HyphaDomElement $doc, array $structure) use (&$build) {
			foreach ($structure as $name => $children) {
				$doc->append($build($doc->getOrCreate($name), $children));
			}
			return $doc;
		};
		$build($documentElement, $this->getDataStructure());

		// build structure from XML
		$this->rootDocPage = DocPage::build($this->getXml());
		$this->getXml()->saveAndUnlock();

		return $new;
	}

	protected function lockAndReload() {
		$this->getXml()->lockAndReload();
		$this->rootDocPage = DocPage::build($this->getXml());
		$this->resetDocPagesMtx();
	}

	protected function unlock() {
		$this->getXml()->unlock();
	}

	protected function saveAndUnlock() {
		$this->getXml()->saveAndUnlock();
	}

	/**
	 * @param string $name
	 * @return null|DocPage
	 */
	protected function getDocPageByName($name) {
		$docPagesMtx = $this->getDocPagesMtx('name');
		if (array_key_exists($name, $docPagesMtx)) {
			return $docPagesMtx[$name];
		}

		return null;
	}

	/**
	 * @param string $id
	 * @return null|DocPage
	 */
	protected function getDocPageById($id) {
		$docPagesMtx = $this->getDocPagesMtx('id');
		if (array_key_exists($id, $docPagesMtx)) {
			return $docPagesMtx[$id];
		}

		return null;
	}

	protected function resetDocPagesMtx() {
		$this->docPagesMtx = ['id' => [], 'name' => []];
	}

	/**
	 * @param string $type
	 * @return DocPage[]
	 */
	protected function getDocPagesMtx($type) {
		if (empty($this->docPagesMtx['name'])) {
			$registerDocPages = function (DocPage $docPage) use (&$registerDocPages) {
				if ($docPage->getId()) {
					$this->docPagesMtx['id'][$docPage->getId()] = $docPage;
				}
				$this->docPagesMtx['name'][$docPage->getName()] = $docPage;
				foreach ($docPage->getChildren() as $children) {
					if ($children instanceof DocPage) {
						$children = [$children];
					}
					foreach ($children as $child) {
						if ($child instanceof DocPage) {
							$registerDocPages($child);
						}
					}
				}
			};
			$registerDocPages($this->rootDocPage);
		}

		return $this->docPagesMtx[$type];
	}

	/**
	 * @param string $docPageName
	 * @param string $attr
	 * @return null|string
	 */
	protected function getAttr($docPageName, $attr) {
		$docPage = $this->getDocPageByName($docPageName);
		if ($docPage instanceof DocPage) {
			return $docPage->getAttr($attr);
		}

		return null;
	}

	/**
	 * @param string $container
	 * @param string $key
	 * @return DocPage[]
	 */
	protected function getContainerItems($container, $key) {
		$items = $this->getDocPageByName($container)->getChildren();
		if (empty($items)) {
			return [];
		}
		$items = $items[$key];
		if ($items instanceof DocPage) {
			$items = [$items];
		}

		return $items;
	}
}
