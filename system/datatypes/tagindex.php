<?php
/*
	Class: tagindexpage
	Shows an index page for articles based on their tags
*/
	class tagindexpage extends HyphaSystemPage {
		function __construct(RequestContext $O_O) {
			parent::__construct($O_O);
		}

		public function process(HyphaRequest $request) {
			return $this->tagIndexView($request);
		}

		protected function tagIndexView(HyphaRequest $request) {
			$lang = $request->getArg(0);
			// TODO: This urldecode should really be done by
			// HyphaRequest (#274)
			$label = urldecode($request->getArg(1));

			$tag = HyphaTags::findTagByLabel($lang, $label);
			if ($tag === null)
				return '404';

			/** @var HyphaDomElement $main */
			$main = $this->html->getElementById('main');

			/** @var HyphaDomElement $pagename */
			$pagename = $this->html->getElementById('pagename');
			$tagprefixSpan = $pagename->createChild('span');
			$tagprefixSpan->setText('Index for tag: ');
			$tagprefixSpan->setAttribute('class', 'tag_prefix');
			$tagSpan = $pagename->createChild('span');
			$tagSpan->setAttribute('class', 'selected_tag');
			$tagSpan->setText($label);

			$includePrivate = isUser();
			$pages = $this->getPages($tag, $includePrivate);
			$pages = $this->sortPages($pages);

			$pagesList = $this->renderPages($pages);
			$main->appendChild($pagesList);
		}

		protected function sortPages($pages) {
			// TODO: This should cache sort dates
			$compare = function($a, $b) {
				$aDate = $a->getSortDateTime();
				$bDate = $b->getSortDateTime();
				// TODO Can we better handle null here?
				return ($bDate ? $bDate->getTimestamp() : 0) - ($aDate ? $aDate->getTimestamp() : 0);
			};
			usort($pages, $compare);
			return $pages;
		}

		protected function getPages(HyphaTag $tag, $includePrivate = false) {
			$pageTypes = []; // All pagetypes
			$pageNodes = hypha_findPages([
				'tags' => [$tag],
				'page_types' => $pageTypes,
				'include_private' => $includePrivate,
			]);
			$pages = [];
			foreach ($pageNodes as $node) {
				$page = createPageInstance($this->O_O, $node);
				if ($page)
					$pages[] = $page;
			}
			return $pages;
		}

		protected function renderPages($pages) {
			/** @var HyphaDomElement $ul */
			$ul = $this->html->createElement('ul');
			$ul->addClass('tagindex');
			foreach ($pages as $page) {
				$li = $this->renderPage($page);
				$ul->appendChild($li);
			}
			return $ul;
		}

		protected function renderPage(HyphaDatatypePage $page) {
			$li = $this->html->createElement('li');
			$li->addClass('tagindex-item');
			$li->addClass('type_' . get_class($page));
			$li->addClass($page->privateFlag ? 'is-private' : 'is-public');

			$a = $li->createChild('a');
			$a->setAttribute('href', $page->language.'/'.$page->pagename);

			$page->renderExcerpt($a);

			return $li;
		}
	}
