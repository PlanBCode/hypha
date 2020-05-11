<?php
/*
	Class: indexpage
	Shows index page for various types of data.
*/
	class indexpage extends HyphaSystemPage {
		const PATH_IMAGES = 'images';
		const PATH_FILES = 'files';

		function __construct(RequestContext $O_O) {
			parent::__construct($O_O);
		}

		public function process(HyphaRequest $request) {
			$this->html->writeToElement('langList', hypha_indexLanguages('', ''));
			switch ([$request->getView(), $request->getCommand()]) {
				case [self::PATH_IMAGES,        null]:   return $this->imageIndexView($request);
				case [self::PATH_FILES,         null]:   return $this->fileIndexView($request);
				default:                                 return $this->pageIndexView($request);
			};
		}
		public function imageIndexView(HyphaRequest $request) {
			$this->html->writeToElement('pagename', __('image-index'));
			$this->html->writeToElement('main', 'image index is not yet implemented');
		}

		public function fileIndexView(HyphaRequest $request) {
			$this->html->writeToElement('pagename', __('file-index'));
			$this->html->writeToElement('main', 'file index is not yet implemented');
		}

		public function pageIndexView(HyphaRequest $request) {
			$language = $request->getArg(0);
			if ($language === null)
				$language = $this->O_O->getContentLanguage();
			$isoLangList = Language::getIsoList();
			if (!array_key_exists($language, $isoLangList))
				return '404';
			$languageName = $isoLangList[$language];
			$languageName = substr($languageName, 0, strpos($languageName, ' ('));

			// get list of available pages and sort alphabetically
			foreach(hypha_getPageList() as $page) {
				$lang = hypha_pageGetLanguage($page, $language);
				if ($lang) if ($this->O_O->isUser() || ($page->getAttribute('private')!='on')) {
					$pageList[] = $lang->getAttribute('name').($page->getAttribute('private')=='on' ? '&#;' : '');
					$pageListDatatype[$lang->getAttribute('name')] = $page->getAttribute('type');
				}
			}
			if ($pageList) array_multisort(array_map('strtolower', $pageList), $pageList);

			// add capitals
			$capital = 'A';
			$first = true;
			if ($pageList) foreach($pageList as $pagename) {
				while($capital < strtoupper($pagename[0])) $capital++;
				if (strtoupper($pagename[0]) == $capital) {
					if (!$first) {
						$htmlList[] = '</div>';
					}
					$htmlList[] = '<div class="letter-wrapper">';
					$htmlList[] = '<div class="letter">'.$capital.'</div>';
					$capital++;
					$first = false;
				}
				$privatePos = strpos($pagename, '&#;');
				if ($privatePos) $pagename = substr($pagename, 0, $privatePos);
				$htmlList[] = '<div class="index-item type_'.$pageListDatatype[$pagename].' '.($privatePos ? 'is-private' : 'is-public').'"><a href="'.$language.'/'.$pagename.'">'.showPagename($pagename).'</a></div>';
			}

			$html = '<div class="index">';
			foreach($htmlList as $htmlLine) $html.= $htmlLine;
			$html.= '</div>';

			$this->html->writeToElement('pagename', __('page-index').': '.$languageName);
			$this->html->writeToElement('main', $html);
	}
}
