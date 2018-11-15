<?php
/*
	Class: searchPage
	handles hypha search requests

	See Also:
	<Page>
*/
	class searchPage extends Page {
		function __construct($args) {
			parent::__construct('', $args);
			//hypha/search/<searchtext>
			registerCommandCallback('search', Array($this, 'showsearchresult'));
		}

		function build() {
			switch ($this->getArg(0)) {
				//search/text/<pattern>
				case 'text':
					$this->showsearchresult($this->getArg(1)); break;
				default:
					$this->showsearchhelp($this->getArg(1));break;
			}
		}

		function showsearchresult($pattern) {
			// $O_O->isUser determines if the private pages are shown.
			// only loggedin users can see the private pages as well
			global $uiLangList;
			global $isoLangList;
			global $O_O;
			$pattern = str_replace('_'," ",$pattern);
			if ($O_O->isUser()) {
				$includePrivatePages = true;
			} else {
				$includePrivatePages = false;
			}
			$context = 100; // determines the length of the context around the pattern in case text is found
			$response = $this->hyphaSearchPattern($pattern,$includePrivatePages,$context);
			if ($response) {
				$this->html->writeToElement('main', $response);
			}
			else {
				$this->html->writeToElement('main', __('nosearchresult'));
			}
		}

		function showsearchhelp($pattern) {
			// the command should contain a type and pattern
			// e.g. /search/test/welcome
			// if the type is missing this function is called and gives this message
			// due to the fact that search is always activated via the search button this function is seldom activated
		global $uiLangList;
		global $isoLangList;
		$response = "Error Internal error:illegal call to search, specify search type";
		$this->html->writeToElement('main', $response);
}


		/*
		* Function:
		* hyphaSearchPattern()
		* search pages for pattern
		* Parameters:
		* Pattern - search string
		* private - true   on  = include private pages (user is logged on)
		*           false  off = exclude private pages
		* Return:
		* html formatted list of found file references
		* clickable titel
		* text fragment with search pattern in red
		*
		*/

		function hyphaSearchPattern($spattern,$bprivate=false, $icontextlength=100)
		{
		$hyphaData = "./data/";
		$hyphaPages = "./data/pages/";
		if (strlen($spattern) == 0) return "No pattern specified";
		$context = max ($icontextlength,strlen($spattern));
		$_htmlResult =  "";
		$dom = new DOMDocument();
		$dom->load($hyphaData . 'hypha.xml');
		$pglstnr = 0;
		foreach($dom->getElementsByTagName('pageList') as $pagelist)
		 {
			$pglstnr++;
			$pgnr=0;
			foreach($pagelist->getElementsByTagName('page') as $page)
			{
				$pgnr++;
				$lgnr=0;
				$_pageId      = $page->getAttribute('id');
				$_pageType    = $page->getAttribute('type');
				$_pagePrivate = $page->getAttribute('private');
				if ($_pagePrivate === "off" || $bprivate )
				 {
					foreach($page->getElementsByTagName('language') as $language)
					{
						$lgnr++;
						$_pageLanguageId   	= $language->getAttribute('id');
						$_pageLanguageName 	= $language->getAttribute('name');
						$_pageLanguageTitle = $language->getAttribute('title');
						// search the source for the pattern
						$_fileName = $hyphaPages . $_pageId;
						$_htmlResult .= $this->searchSource($_fileName,$spattern,$_pageType,$_pageLanguageId,$_pageLanguageName,$context);
					} // "   EINDE language ==============================\n";
				}
			} //"EINDE page ==================================\n";
		}
		return $_htmlResult;
		}


		function searchAction($lines,$pattern,$icontextlenght)
		{
		// search the lines for a occurence of pattern
		$result = false;
		$context = $icontextlenght - strlen($pattern);
		$mystring = $lines;
		$findme = $pattern;
		$pos = strpos($mystring,$findme);
		// note our use of ===, simply == would not work
		if (!($pos === false))
		{
			$result =  $this->formatsearchresult($mystring,$pattern,$pos,$context);
		}
		return $result;
		}

		function formatsearchresult($mystring,$findme,$pos,$context)
		{
		//formatsearchresult
		// part   before     middle   after
		// length context/2           context/2
		//        bbbbbbbb   pattern  aaaaaaaa

		$lp = strlen($findme);
		$hc = round($context/2);
		$ll = strlen($mystring);
		$startafter = $pos + $lp;
		if ($pos < ($hc))
		{
			$startbefore = 0;
			$lengthbefore = $pos;
			$lengthafter = $context - $pos;
		}
		elseif ($pos > $ll - $hc)
		{
			$lengthafter = $ll - ($pos + $lp);
			$lengthbefore = max(0,$context - $lengthafter);
			$startbefore = max(0,$pos - $lengthbefore);
		}
		else
		{
			$startbefore  = $pos - $hc;
			$lengthbefore = $hc;
			$lengthafter  = $context - $lengthbefore;
		}
		$beforestring = substr($mystring,$startbefore,$lengthbefore);
		$middlestring = '<span class="searchpattern" style = "color: red; font-style: bold">'. $findme . '</span>';
		$afterstring  = substr($mystring,$startafter,$lengthafter);
		$result = ":" . $beforestring . $middlestring . $afterstring;
		return $result;
		}

		function searchPeerReviewedArticle($filename,$pattern,$pageLanguageId,$icontextlength)
		{
		// $pagelanguageId not used.
		// get the text
		$searchResult = __('search-no-content-found');
		if (!file_exists($filename)) return $searchResult;  "System Error file " . $filename . "(" . $pageLanguageId . ") not found";
		$texts     = new DOMDocument();
		$texts->load($filename);
		foreach ($texts->getElementsByTagName('article') as $article)
		{
			foreach ($article->getElementsByTagName('content') as $content)
			{
				foreach ($content->getElementsByTagName('text') as $textContent)
				{
					$lines = $textContent->nodeValue;
					$searchResult = $this->searchAction($lines,$pattern,$icontextlength);
				}
			}
		}
		return $searchResult;
		}

		function searchTextPage($filename,$pattern,$pageLanguage,$icontextlength)
		{
		$searchResult = __('search-no-content-found');
		// get the text
		if (!file_exists($filename)) return $searchResult;   //"System Error file " . $filename . "(" . $pageLanguage . ") not found";
		$texts     = new DOMDocument();
		$texts->load($filename);

		foreach ($texts->getElementsByTagName('language') as $text)
		{
			$_textLanguage = $text->getAttribute('xml:id');
			if ($pageLanguage == $_textLanguage)
			{
				$versionlist = array();
				foreach ($text->getElementsByTagName('version') as $version)
				{
					$versionlist[]= $version->getAttribute('xml:id');
				}
				rsort($versionlist); // the latest version on top
				foreach ($text->getElementsByTagName('version') as $version)
				{
					if ($versionlist[0] == $version->getAttribute('xml:id'))
					{
						$lines = $version->nodeValue;
						$searchResult = $this->searchAction($lines,$pattern,$icontextlength);
					}
				}
			}
		}
		return $searchResult;
		}


		function searchSource($filename,$pattern,$pageType,$_pageLanguageId,$_pageLanguageName,$icontextlength)
		{
		$lhtmlResult = "";
		$lhtmlPage   = "";
		switch ($pageType)
		{
			case 'peer_reviewed_article':
				$lhtmlResult = $this->searchPeerReviewedArticle($filename,$pattern,$_pageLanguageId,$icontextlength);
			break;
			case 'textpage':
				$lhtmlResult = $this->searchTextPage($filename,$pattern,$_pageLanguageId,$icontextlength);
			break;
			default: $lhtmlResult = "";
		}
		if (!$lhtmlResult == "")
		{
			// echo "\n 178 htmlResult=" . $lhtmlResult ."\n";
			$lhtmlPage = $this->render($lhtmlResult,$_pageLanguageId,$_pageLanguageName);
			// echo "\n" . $lhtmlPage . "\n";
		}
		return $lhtmlPage;
		}

		function render($sResult,$LanguageId,$pageName)
		{
		global $hyphaData;
		// format of search result
		$htmlMain  = "<div class=\"search-result\">\n";
		$htmlMain .= "<div class=\"search-link\">\n";
		$htmlMain .= "<a href=\"".$hyphaData.$LanguageId."/" . $pageName ."\"> ".$pageName." </a>\n";
		$htmlMain .= "</div>\n";
		$htmlMain .= "<div class=\"search-context\">\n" . $sResult . " ...\n</div>\n";
		$htmlMain .="</div><br>\n";
		// echo "\n 190 htmlResultaat = " . var_dump($sResult) . "\n";
		return $htmlMain;
		}
}
