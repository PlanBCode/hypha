<?php

/**
 * Pagelist macro, renders a list of pages based on specified filter
 * criteria.
 *
 * Parameters:
 *  - tags:
 *    A comma separated list of tags, only items with one of these tags
 *    will be shown. Each item is the label of a tag, optionally
 *    prefixed with a language (e.g. tag="en/taglabel") to select a tag
 *    from another language, otherwise the current content language is
 *    used to find the tag.
 *  - exclude_tags:
 *    A comma separated list of tags, only items without any of these
 *    tags will be shown. Syntax is the same as the "tags" attribute. If
 *    an item matches both tags and exclude_tags, it is excluded.
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
 *  - date-from:
 *    Only show pages with a (sort) date on or after the given date.
 *    Given date should be in YYYY-MM-DD format.
 *  - date-until:
 *    Only show pages with a (sort) date on or before the given date.
 *    Given date should be in YYYY-MM-DD format.
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

	private function lookupTags($tagsattr) {
		$tags = [];
		if ($tagsattr) {
			foreach (explode(',', $tagsattr) as $single) {
				$split = explode('/', $single, 2);
				if (count($split) == 1) {
					$lang = $this->O_O->getContentLanguage();
					$label = $split[0];
				} else {
					list($lang, $label) = $split;
				}
				$tag = HyphaTags::findTagByLabel($lang, $label);
				if (!$tag)
					throw new UnexpectedValueException("Invalid tag in language $lang: $label");
				$tags[] = $tag;
			}
		}
		return $tags;
	}

	private function getPages() {
		// Allow filtering by tag
		$tagsattr = $this->macro_tag->getAttribute("tags");
		$tags = self::lookupTags($tagsattr);

		$excludeTagsattr = $this->macro_tag->getAttribute("exclude-tags");
		$excludeTags = $this->lookupTags($excludeTagsattr);

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
			'tags' => $tags,
			'exclude_tags' => $excludeTags,
			'page_types' => $pageTypes,
			'include_private' => $includePrivate,
			'languages' => $languages,
		]);

		// These use ! to set the time part to midnight (in the
		// default timezone), rather than using the current
		// time.
		$dateFrom = $this->getDateTimeAttribute('date-from', '!Y-m-d', 'YYYY-MM-DD');
		$dateUntil = $this->getDateTimeAttribute('date-until', '!Y-m-d', 'YYYY-MM-DD');
		// Make sure that timestamps *on* the given date compare
		// less (so we don't have to clear the time part of all
		// sort dates).
		if ($dateUntil) $dateUntil->modify('tomorrow');

		$pages = [];
		foreach ($pageNodes as $node) {
			$page = createPageInstance($this->O_O, $node);
			if ($dateFrom || $dateUntil) {
				$datetime = $page->getSortDateTime();
				if (!$datetime || ($dateFrom && $datetime < $dateFrom) || ($dateUntil && $datetime >= $dateUntil)) {
					continue;
				}
			}
			$pages[] = $page;
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
