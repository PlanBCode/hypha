<?php

/**
 * Pagelist macro, renders a list of pages based on specified filter
 * criteria.
 *
 * Parameters:
 *  - tag:
 *    The label of a tag, only pages with this tag will be shown.  Can
 *    be prefixed with a language (e.g. tag="en/taglabel") to select a
 *    tag from another language, otherwise the current content language
 *    is used to find the tag.
 *  - pagetypes:
 *    A comma separated list of pagetypes, only pages with these types
 *    will be shown.
 *  - languages:
 *    A comma separated list of 2-letter language codes, only pages
 *    available in any of these languages will be shown.
 *  - include-private:
 *    When set to "yes" and when a user is logged in, private pages are
 *    included. Otherwise (including when omitted), only public pages
 *    are shown.
 *  - sort:
 *    The sort order. Can be one of "date" or "title". Can be prefixed
 *    by - to reverse sort order (e.g. sort="-date" to sort by
 *    descending date).
 *  - limit:
 *    Show at most this many pages.
 *
 * Example usage:
 *
 *     <macro name="pagelist" tag="en/tag_label" pagetypes="peer_reviewed_article" languages="en" include-private="yes" sort="-date" limit="10" />
 */
class PagelistMacro extends HyphaMacro {
	public function invoke() {
		$pages = $this->getPages();
		$pages = $this->sortPages($pages);
		$pages = $this->paginatePages($pages);

		return $this->renderPages($pages);
	}

	private function paginatePages(array $pages) {
		// Allow limiting the number of pages (for now just by a
		// macro option, in the future maybe by e.g. GET
		// parameters too).
		// TODO: Better checking of option values
		$limit = intval($this->macro_tag->getAttribute("limit"));
		if ($limit)
			return array_slice($pages, 0, $limit);

		return $pages;
	}

	private function sortPages($pages) {
		$keyfuncs = [
			'date' => function($page) {
				$date = $page->getSortDateTime();
				return ($date ? $date->getTimestamp() : 0);
			},
			'title' => function($page) {
				return $page->getTitle();
			}
		];

		$sortattr = $this->macro_tag->getAttribute('sort');
		if ($sortattr) {
			$order = SORT_ASC;
			if ($sortattr[0] == '-') {
				$order = SORT_DESC;
				$sortattr = substr($sortattr, 1);
			}

			if (!array_key_exists($sortattr, $keyfuncs))
				throw new UnexpectedValueException("Invalid sort key: $sortattr. Valid options are: " . implode(', ', array_keys($keyfuncs)));
			$keyfunc = $keyfuncs[$sortattr];

			// This does a parallel sorting of the $keys and $pages
			// arrays, using $keys as the sort value (only looking
			// at $pages if two pages have the same key).
			$keys = array_map($keyfunc, $pages);
			array_multisort($keys, $order,  SORT_NATURAL | SORT_FLAG_CASE, $pages);
		}
		return $pages;
	}

	private function getPages() {
		// Allow filtering by tag
		$tagattr = $this->macro_tag->getAttribute("tag");
		$tag = null;
		if ($tagattr) {
			$split = explode('/', $tagattr, 2);
			if (count($split) == 1) {
				$lang = $this->O_O->getContentLanguage();
				$label = $split[0];
			} else {
				list($lang, $label) = $split;
			}
			$tag = HyphaTags::findTagByLabel($lang, $label);
			if (!$tag)
				throw new UnexpectedValueException("Invalid tag in language $lang: $label");
		}

		// Include private pages only when logged in and enabled
		// TODO: Better checking of option values (and maybe
		// allow Yes/true/TrUe/etc.)
		$includePrivate = isUser() && ($this->macro_tag->getAttribute("include-private") == "yes");

		// Allow filtering by page type
		$pageTypes = [];
		$pageTypesAttr = $this->macro_tag->getAttribute("pagetypes");
		if ($pageTypesAttr) {
			$pageTypes = explode(',', $pageTypesAttr);
			$validTypes = hypha_getDataTypes();
			foreach ($pageTypes as $type) {
				if (!array_key_exists($type, $validTypes))
					throw new UnexpectedValueException("Invalid page type: $type. Valid options are: " . implode(', ', array_keys($validTypes)));
			}
		}

		// Allow filtering by language
		$languages = []; // All languages
		$langAttr = $this->macro_tag->getAttribute("languages");
		if ($langAttr) {
			$languages = explode(',', $langAttr);
			// Allow all language codes, but only show the
			// used languages in the error message.
			$validLangs = Language::getIsoList();
			$usedLangs = hypha_getUsedContentLanguages();
			foreach ($languages as $lang) {
				if (!array_key_exists($lang, $validLangs))
					throw new UnexpectedValueException("Invalid language: $lang. Valid options are: " . implode(', ', $usedLangs));
			}
		}

		$pageNodes = hypha_findPages([
			'tags' => [$tag],
			'page_types' => $pageTypes,
			'include_private' => $includePrivate,
			'languages' => $languages,
		]);
		$pages = [];
		foreach ($pageNodes as $node) {
			$pages[] = createPageInstance($this->O_O, $node);
		}
		return $pages;
	}

	private function renderPages($pages) {
		/** @var HyphaDomElement $ul */
		$ul = $this->doc->createElement('ul');
		$this->copyAttributesTo($ul);
		if (!$ul->hasAttribute('class'))
			$ul->addClass('pagelist');

		foreach ($pages as $page) {
			$li = $this->renderPage($page);
			$ul->append($li);
		}
		return $ul;
	}

	private function renderPage(HyphaDatatypePage $page) {
		$li = $this->doc->createElement('li');
		$li->addClass('pagelist-item');
		$li->addClass('type_' . get_class($page));
		$li->addClass($page->privateFlag ? 'is-private' : 'is-public');

		$a = $this->doc->createElement('a')->appendTo($li);
		$a->setAttribute('href', $page->language.'/'.$page->pagename);

		$page->renderExcerpt($a);

		return $li;
	}
}

return PagelistMacro::class;
