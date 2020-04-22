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

		public function tagIndexView(HyphaRequest $request) {
			$lang = $request->getArg(0);
			$label = $request->getArg(1);

			$tag = HyphaTags::findTagByLabel($lang, $label);
			if ($tag === null)
				return '404';

			/** @var HyphaDomElement $main */
			$main = $this->html->find('#main');

			/** @var HyphaDomElement $pagename */
			$pagename = $this->html->find('#pagename');
			$tagprefixSpan = $this->html->createElement('span');
			$tagprefixSpan->setText('Index for tag: ');
			$tagprefixSpan->setAttribute('class', 'tag_prefix');
			$tagSpan = $this->html->createElement('span');
			$tagSpan->setAttribute('class', 'selected_tag');
			$tagSpan->setText($label);
			$pagename->append($tagprefixSpan);
			$pagename->append($tagSpan);

			$includePrivate = isUser();
			$pages = $this->getPages($tag, $includePrivate);
			$pages = $this->sortPages($pages);

			$pagesList = $this->renderPages($pages);
			$main->append($pagesList);
		}

		private function sortPages($pages) {
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

		private function getPages(HyphaTag $tag, $includePrivate = false) {
			$pageTypes = []; // All pagetypes
			$pageNodes = HyphaTags::findPagesWithTags($tag, $pageTypes, $includePrivate);
			$pages = [];
			foreach ($pageNodes as $node) {
				$pages[] = createPageInstance($this->O_O, $node);
			}
			return $pages;
		}

		private function renderPages($pages) {
			/** @var HyphaDomElement $ul */
			$ul = $this->html->createElement('ul');
			$ul->addClass('tagindex');
			foreach ($pages as $page) {
				$li = $this->renderPage($page);
				$ul->append($li);
			}
			return $ul;
		}

		private function renderPage(HyphaDatatypePage $page) {
			$li = $this->html->createElement('li');
			$li->addClass('tagindex-item');
			$li->addClass('type_' . get_class($page));
			$li->addClass($page->privateFlag ? 'is-private' : 'is-public');

			$a = $this->html->createElement('a')->appendTo($li);
			$a->setAttribute('href', $page->language.'/'.$page->pagename);

			$page->renderExcerpt($a);

			return $li;
		}
	}