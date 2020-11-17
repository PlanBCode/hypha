<?php
	// TODO:
	// - implement error notification if new tag exists
	// - implement tag management page: edit, translate, remove or merge tags
	// - implement tagcontainer pagetype
	class HyphaTags {
		static function findTagByLabel($lang, $label) {
			/** @var HyphaDomElement $hyphaXml */
			global $hyphaXml;
			$path = 'hypha/tagList/tag/language[@id=' . xpath_encode($lang) . ' and @label=' . xpath_encode($label) . ']';
			$node = $hyphaXml->findXPath($path)->parent()->first();
			return ($node ? new HyphaTag($node) : null);
		}

		static function findPagesWithTags(HyphaTag $tag, array $pageTypes=[], bool $includePrivate=false, $skip=0, $limit=0) {
			/** @var HyphaDomElement $hyphaXml */
			global $hyphaXml;

			$pageFilters = '';
			if ($pageTypes) {
				$filterFunc = function($type) { return "@type=" . xpath_encode($type); };
				$attrFilters = array_map($filterFunc, $pageTypes);
				$pageFilters .= "[" . implode(" or ", $attrFilters) . "]";
			}
			if ($includePrivate !== true) {
				$pageFilters .= "[@private='off']";
			}

			// TODO: skip and limit might not be useful
			// without sorting, but xpath 1.0 does not seem
			// to support sorting.
			$positionFilters = '';
			if ($skip > 0) {
				// Note: position() is 1-based
				$positionFilters .= '[position() >= ' . ($skip + 1) . ']';
			}
			if ($limit > 0) {
				// This refers to the position *after*
				// applying skip, because it is in a
				// different [] predicate block.
				$positionFilters .= '[position() < ' . ($limit + 1) . ']';
			}

			$tagFilter = '@id=' . xpath_encode($tag->getId());
			$xpath = "hypha/pageList/page${pageFilters}[child::tag[${tagFilter}]]${positionFilters}";
			$pages = $hyphaXml->findXPath($xpath);

			return $pages;
		}

		/**
		 * Return the tags assigned to the given page list node
		 *
		 * @return list<HyphaTag>
		 */
		static function tagsForPageListNode(HyphaDomElement $pageListNode) {
			$idFilters = [];
			foreach ($pageListNode->findXPath('./tag/@id') as $idAttr)
				$idFilters[] = '@xml:id=' . xpath_encode($idAttr->value);
			$tags = [];
			if (!empty($idFilters)) {
				$path = '/hypha/tagList/tag[' . implode(" or ", $idFilters) . "]";

				foreach ($pageListNode->document()->findXPath($path) as $tagNode)
					$tags[$tagNode->getId()] = new HyphaTag($tagNode);
			}
			return $tags;
		}
	}

	class HyphaTag {
		private $node;
		function __construct(HyphaDomElement $node) {
			$this->node = $node;
		}

		function getId() {
			return $this->node->getId();
		}
	}

	function tagList(HyphaDomElement $pageListNode, $lang) {
		/** @var HyphaDomElement $hyphaXml */
		global $hyphaXml;

		$selectedTagIds = [];

		/** @var HyphaDomElement $tagEl */
		// Build list of active tags for given page
		foreach ($pageListNode->findXPath('tag') as $tagEl) $selectedTagIds[] = $tagEl->getAttribute('id');

		$selectedTags = [];
		$deselectedTags = [];

		// Divide list of all tags stored in hypha.xml in a selected tag list and a deselected tag list
		foreach ($hyphaXml->findXPath('hypha/tagList/tag/language[@id=' . xpath_encode($lang) . ']') as $tagLang) {
			/** @var HyphaDomElement $tagLang */
			$id = $tagLang->parent()->getId();
			$tag = ['label' => $tagLang->getAttribute('label'), 'description' => $tagLang->nodeValue, 'lang' => $tagLang->getAttribute('id')];
			if (in_array($id, $selectedTagIds)) $selectedTags[$id] = $tag;
			else $deselectedTags[$id] = $tag;
		}

		// Construct html output
		$html = '';
		if (!empty($selectedTagIds) || isUser()) {
			uasort($selectedTags, function($a, $b) { return strnatcasecmp($a['label'], $b['label']); });

			$html.= '<span class="prefix">' . __('tags') . ': </span>';
			$html.= '<div class="selectedTags">';
			foreach ($selectedTags as $id => $tag) {
				$html.= '<div class="tagSel_'.htmlspecialchars($id).'" class="tag">';

				$tag_index_url = HyphaRequest::HYPHA_SYSTEM_PAGE_TAG_INDEX . '/' . $tag['lang'] . '/' . $tag['label'];
				$html.= '<a href="'.htmlspecialchars($tag_index_url).'">';
				$html.= '<span title="'.htmlspecialchars($tag['description']).'">'.htmlspecialchars($tag['label']).'</span>';
				$html.= '</a>';
				if (isUser()) $html.= ' <span class="delete-tag" title="'.__('remove-tag').'" onclick="deselectTag('.htmlspecialchars(json_encode($id)).');">⨯</span>';
				$html.= '</div>';
			}
			$html.= '</div>';
			if ((isUser() && !empty($deselectedTags)) || isAdmin()) {
				uasort($deselectedTags, function($a, $b) { return strnatcasecmp($a['label'], $b['label']); });

				$html.= '<select class="remainingTags" onchange="selectTag(this, this.value);">';
				$html.= '<option value="_add_" selected="selected" disabled="disabled">'.__('add').'...</option>';
				foreach ($deselectedTags as $id => $tag) {
					$html.= '<option id="tagRem_'.htmlspecialchars($id).'" value="'.htmlspecialchars($id).'">'.htmlspecialchars($tag['label']).'</option>';
				}
				if (isAdmin()) $html.= '<option value="">'.__('new').'...</option>';
				$html.= '</select>';
			}
		}
		return $html;
	}

	/*
		Function: hypha_indexTags
		returns list of tags for the given page

		Parameters:
		$pageListNode - DOMElement containing page settings
		$lang - language for tag index
	*/
	function hypha_indexTags($pageListNode, $lang) {
		global $hyphaHtml;
		$formId = $hyphaHtml->getDefaultForm()->getId();

		if (!$pageListNode || !$lang) return '';

		$language = hypha_pageGetLanguage($pageListNode, $lang);
		$url = $language->getAttribute('id').'/'.$language->getAttribute('name');

		ob_start();
?>
<script>
	function selectTag(select, id) {
		if (!id) newTag(select);
		else {
			hypha(<?=json_encode($url)?>, 'tagSelect', id, document.getElementById(<?=json_encode($formId)?>), function(response) {document.getElementById('tagList').innerHTML = response;});
			document.getElementById('tagList').innerHTML = 'loading...';
		}
	}
	function deselectTag(id) {
		hypha(<?=json_encode($url)?>, 'tagDeselect', id, document.getElementById(<?=json_encode($formId)?>), function(response) {document.getElementById('tagList').innerHTML = response;});
		document.getElementById('tagList').innerHTML = 'loading...';
	}
	function newTag(select) {
		// Revert back to initial value, so you can choose "new" again
		select.options[0].selected = true;
		var name = prompt(<?=json_encode(__('enter-tag-label'))?>, '');
		if (name) {
			var description = '';
			while (description === '') {
				// null when user clicks cancel
				var description = prompt(<?=json_encode(__('enter-tag-description'))?>, '');
			}

			if (description) {
				var json = JSON.stringify({name: name, description: description});
				hypha(<?=json_encode($url)?>, 'tagNew', json, document.getElementById(<?=json_encode($formId)?>), function(response) {document.getElementById('tagList').innerHTML = response;});
				document.getElementById('tagList').innerHTML = 'loading...';
			}
		}
	}
</script>
<?php
		$hyphaHtml->writeScript(ob_get_clean());

		return tagList($pageListNode, $lang);
	}

	registerCommandCallback('tagSelect', 'tagSelectCallback');
	registerCommandCallback('tagDeselect', 'tagDeselectCallback');
	registerCommandCallback('tagNew', 'tagNewCallback');

	function tagSelectCallback($id) {
		global $hyphaXml;
		global $O_O;

		$lang = $O_O->getContentLanguage();

		$hyphaXml->lockAndReload();
		$pageListNode = hypha_getPage($lang, $O_O->getRequest()->getPageName());
		$newtag = $hyphaXml->createElement('tag');
		$newtag->setAttribute('id', $id);
		$pageListNode->appendChild($newtag);
		$hyphaXml->saveAndUnlock();

		echo tagList($pageListNode, $lang);
		exit;
	}

	function tagDeselectCallback($id) {
		global $hyphaXml;
		global $O_O;
		$lang = $O_O->getContentLanguage();

		$hyphaXml->lockAndReload();
		$pageListNode = hypha_getPage($lang, $O_O->getRequest()->getPageName());
		foreach($pageListNode->getElementsByTagName('tag') as $tag) if ($tag->getAttribute('id')==$id) $pageListNode->removeChild($tag);
		$hyphaXml->saveAndUnlock();

		echo tagList($pageListNode, $lang);
		exit;
	}

	function tagNewCallback($json) {
		global $hyphaXml;
		global $O_O;

		$hyphaXml->lockAndReload();

		$lang = $O_O->getContentLanguage();
		$pageListNode = hypha_getPage($lang, $O_O->getRequest()->getPageName());

		$json = json_decode($json, true);


		// Check if tagList exists
		$tagList = $hyphaXml->findXPath('hypha/tagList');
		if ($tagList->count() === 0) {
			$tagList = $hyphaXml->createElement('tagList');
			$hyphaXml->documentElement->appendChild($tagList);
		}

		// Check if tag exists
		$path = 'hypha/tagList/tag/language[@id=' . xpath_encode($lang) . ' and @label=' . xpath_encode($json['name']) . ']';
		$tagExists = $hyphaXml->findXPath($path)->count() > 0;

		// Create new tag, add to tagList and page in hypha.xml
		if (!$tagExists) {
			$newTagName = $json['name'];
			$newTagDescription = $json['description'];

			$newTag = $hyphaXml->createElement('tag');
			$newTag->generateId();
			$newTagLanguage = $hyphaXml->createElement('language', $newTagDescription);
			$newTagLanguage->setAttribute('id', $lang);
			$newTagLanguage->setAttribute('label', $newTagName);
			$newTag->appendChild($newTagLanguage);
			$tagList->appendChild($newTag);

			$newTagRef = $hyphaXml->createElement('tag');
			$newTagRef->setAttribute('id', $newTag->getId());
			$pageListNode->appendChild($newTagRef);

			$hyphaXml->saveAndUnlock();
		} else {
			$hyphaXml->unlock();
		}

		// TODO: Replace this with a return value that lets the
		// caller know we want to return a response (but still
		// allow any postprocessing or other cleanup).
		echo tagList($pageListNode, $lang);
		exit;
	}
