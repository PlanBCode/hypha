<?php
	/*
		Title: Base

		This chapter describes the hypha's global variabes and the available functions to access the system settings file 'data/hypha.xml'. This file contains all data needed to make a functional website: user accounts, available pages, header and footer et cetera.
	*/

	/*
		Variable: $hyphaUrl
		location of index.php, e.g. 'www.dom.ain/wiki'. This variable is set in 'index.php'

		Variable: $hyphaQuery
		page request, e.g. 'en/home/edit' or 'settings/username'. This variable is set in 'index.php'

		Variable: $hyphaXml
		<Xml> object with hypha system data from the file 'data/hypha.xml'. This variable is set in 'index.php' by invoking <loadHypha>.

		Variable: $hyphaHtml
		<HTMLDocument> object which eventually will be served to the client. This variable is set in 'index.php'

		Variable: $hyphaUser
		DOMElement object with user data from the userList element in 'data/hypha.xml' when user is logged in, or null when no user is logged in. This variable is set in 'index.php' by invoking <loadUser>.

		Variable: $hyphaPage
		<Page> object for the requested page. This variable is set in 'index.php' by invoking <loadPage>.

		Variable: $hyphaPageTypes
		array containing available datatypes. The array is filled by the pagetype scripts in 'system/datatypes/' which are loaded in 'index.php'.

		Variable: $hyphaLanguage
		hypha language, e.g. 'en'. This is the language of the content that is served, not to be mistaken for the user interface language. This variable is set in 'index.php' by invoking <loadPage>.

		Variable: $hyphaDictionary
		array containing user interface translations. This array is by invoking <loadUser> which in turn calls <setLanguage>.

		Variable: $isoLangList
		array containing language codes and their full name accoring to ISO639-1 standard , e.g. en -> English. This array is loaded in 'system/core/language.php'

		Variable: $uiLangList
		array containing language codes for available user interface languages. This array is loaded in 'index.php'.
	*/

	include_once ('database.php');
	include_once ('language.php');
	include_once ('crypto.php');

	if (!is_file('data/hypha.xml')) die('serious error: missing system file hypha.xml');
	$hyphaXml = new Xml('project', Xml::multiLingualOff, Xml::versionsOff);
	$hyphaXml->loadFromFile('data/hypha.xml');

	/*
		Class: Hypha

		This class contains some global values for the Hypha system. It is
		never instantiated, it just collects static variables and methods.
	*/
	class Hypha {
		public static $data;
	}

	Hypha::$data = new StdClass();
	Hypha::$data->css = new HyphaFile('data/hypha.css');
	Hypha::$data->html = new HyphaFile('data/hypha.html');
	Hypha::$data->digest = new HyphaFile('data/digest');
	Hypha::$data->stats = new HyphaFile('data/hypha.stats');

	/*
		Function: hypha_getEmail
		returns hypha email attribute
	*/
	function hypha_getEmail() {
		global $hyphaXml;
		return $hyphaXml->documentElement->getAttribute('email');
	}

	/*
		Function: hypha_setEmail
		sets hypha email attribute

		Parameters:
		$string - new email value
	*/
	function hypha_setEmail($string) {
		global $hyphaXml;
		$hyphaXml->requireLock();
		$hyphaXml->documentElement->setAttribute('email', $string);
	}

	/*
		Function: hypha_getDefaultLanguage
		returns hypha defaultLanguage attribute
	*/
	function hypha_getDefaultLanguage() {
		global $hyphaXml;
		return $hyphaXml->documentElement->getAttribute('defaultLanguage');
	}

	/*
		Function: hypha_setDefaultLanguage
		sets hypha defaultLanguage attribute

		Parameters:
		$string - new defaultLanguage value
	*/
	function hypha_setDefaultLanguage($string) {
		global $hyphaXml;
		$hyphaXml->requireLock();
		$hyphaXml->documentElement->setAttribute('defaultLanguage', $string);
	}

	/*
		Function: hypha_getDefaultPage
		returns hypha defaultPage attribute
	*/
	function hypha_getDefaultPage() {
		global $hyphaXml;
		return $hyphaXml->documentElement->getAttribute('defaultPage');
	}

	/*
		Function: hypha_setDefaultPage
		sets hypha defaultPage attribute

		Parameters:
		$string - new defaultPage value
	*/
	function hypha_setDefaultPage($string) {
		global $hyphaXml;
		$hyphaXml->requireLock();
		$hyphaXml->documentElement->setAttribute('defaultPage', $string);
	}

	/*
		Function: hypha_getDigestInterval
		returns hypha digestInterval attribute
	*/
	function hypha_getDigestInterval() {
		global $hyphaXml;
		return $hyphaXml->documentElement->getAttribute('digestInterval');
	}

	/*
		Function: hypha_setDigestInterval
		sets hypha digestInterval attribute

		Parameters:
		$string - new digestInterval value
	*/
	function hypha_setDigestInterval($string) {
		global $hyphaXml;
		$hyphaXml->requireLock();
		$hyphaXml->documentElement->setAttribute('digestInterval', $string);
	}

	/*
		Function: hypha_getLastDigestTime
		returns hypha lastDigestTime attribute
	*/
	function hypha_getLastDigestTime() {
		global $hyphaXml;
		return $hyphaXml->documentElement->getAttribute('lastDigestTime');
	}

	/*
		Function: hypha_setLastDigestTime
		sets hypha lastDigestTime attribute

		Parameters:
		$string - new lastDigestTime value
	*/
	function hypha_setLastDigestTime($string) {
		global $hyphaXml;
		$hyphaXml->requireLock();
		$hyphaXml->documentElement->setAttribute('lastDigestTime', $string);
	}

	/*
		Function: hypha_getTitle
		returns hypha title string
	*/
	function hypha_getTitle() {
		global $hyphaXml;
		return getInnerHtml($hyphaXml->getElementsByTagName('title')->Item(0));
	}

	/*
		Function: hypha_setTitle
		sets hypha title string

		Parameters:
		$string - new title string
	*/
	function hypha_setTitle($string) {
		global $hyphaXml;
		$hyphaXml->requireLock();
		setInnerHtml($hyphaXml->getElementsByTagName('title')->Item(0), $string);
	}

	/*
		Function: hypha_getHtml
		returns hypha base html file contents
	*/
	function hypha_getHtml() {
		return Hypha::$data->html->read();
	}

	/*
		Function: hypha_setHtml
		writes hypha base html file contents

		Parameters:
		$contents - html file contents
	*/
	function hypha_setHtml($contents) {
		Hypha::$data->html->writeWithLock($contents);
	}

	/*
		Function: hypha_getCss
		returns hypha base css file contents
	*/
	function hypha_getCss() {
		return Hypha::$data->css->read();
	}

	/*
		Function: hypha_setCss
		sets hypha base css file contents

		Parameters:
		$contents - css file contents
	*/
	function hypha_setCss($contents) {
		Hypha::$data->css->writeWithLock($contents);
	}

	/*
		Function: hypha_getHeader
		returns hypha header html
	*/
	function hypha_getHeader() {
		global $hyphaXml;
		return getInnerHtml($hyphaXml->getElementsByTagName('header')->Item(0));
	}

	/*
		Function: hypha_setHeader
		sets hypha header html

		Parameters:
		$html - new header html
	*/
	function hypha_setHeader($html) {
		global $hyphaXml;
		$hyphaXml->requireLock();
		setInnerHtml($hyphaXml->getElementsByTagName('header')->Item(0), $html);
	}

	/*
		Function: hypha_getFooter
		returns hypha footer html
	*/
	function hypha_getFooter() {
		global $hyphaXml;
		return getInnerHtml($hyphaXml->getElementsByTagName('footer')->Item(0));
	}

	/*
		Function: hypha_setFooter
		sets hypha footer html

		Parameters:
		$html - new footer html
	*/
	function hypha_setFooter($html) {
		global $hyphaXml;
		$hyphaXml->requireLock();
		setInnerHtml($hyphaXml->getElementsByTagName('footer')->Item(0), $html);
	}

	/*
		Function: hypha_getMenu
		returns hypha menu html
	*/
	function hypha_getMenu() {
		global $hyphaXml;
		return getInnerHtml($hyphaXml->getElementsByTagName('menu')->Item(0));
	}


	/*
		Function: hypha_setMenu
		sets hypha menu html

		Parameters:
		$html - new menu html
	*/
	function hypha_setMenu($html) {
		global $hyphaXml;
		$hyphaXml->requireLock();
		setInnerHtml($hyphaXml->getElementsByTagName('menu')->Item(0), $html);
	}
	/*
	Function: hypha_getReview
	returns hypha list of pages to be reviewed

	Parameters
	$language
*/
function hypha_getReview($language) {
	global $hyphaXml;
	$review = getInnerHtml($hyphaXml->getElementsByTagName('review')->Item(0));
	$review .= hypha_reviewPages($language);
	return $review;
}
	/*
		Function: hypha_getUserlist
		returns hypha userlist DOMElement
	*/
	function hypha_getUserList() {
		global $hyphaXml;
		return $hyphaXml->getElementsByTagName('userList')->Item(0)->getElementsByTagName('user');
	}

	/*
		Function: hypha_getUserById
		returns a DOMElement containing user account settings

		Parameters:
		$id - identifier for the user
	*/
	function hypha_getUserById($id) {
		foreach(hypha_getUserList() as $user) if ($user->getAttribute('id') == $id) return $user;
		return false;
	}

	/*
		Function: hypha_getUserByName
		returns a DOMElement containing user account settings

		Parameters:
		$username - identifier for the user
	*/
	function hypha_getUserByName($username) {
		foreach(hypha_getUserList() as $user) if (strtolower($user->getAttribute('username')) == strtolower($username)) return $user;
		return false;
	}

	/*
		Function: hypha_getUserByEmail
		returns a DOMElement containing user account settings

		Parameters:
		$username - identifier for the user
	*/
	function hypha_getUserByEmail($email) {
		foreach(hypha_getUserList() as $user) if ($user->getAttribute('email') == $email) return $user;
		return false;
	}

	/*
		Function: hypha_addUser
		adds a new user to the userlist. Returns false on success, error message on failure.

		Parameters:
		$username - should be unique. This name is used as loginnname.
		$password
		$fullname
		$email
		$language - language preference for user interface
		$rights - 'admin' for admin rights, empty for normal user
	*/
	function hypha_addUser($username, $password, $fullname, $email, $language, $rights) {
		global $hyphaXml;
		$hyphaXml->requireLock();
		if ((!$username || !hypha_getUserByName($username))
		    && (!$email || !hypha_getUserByEmail($email))) {
			$newUser = $hyphaXml->createElement('user');
			$newUser->setAttribute('id', uniqid());
			$hyphaXml->getElementsByTagName('userList')->Item(0)->appendChild($newUser);
			return hypha_setUser($newUser, $username, $password, $fullname, $email, $language, $rights);
		} else {
			return __('user-exists');
		}
	}

	/*
		Function: hypha_setUser
		updates user settings. Parameters which are left empty are not updated. Returns false on success, error message on failure.

		Parameters:
		$user - DOMElement containing user settings
		$username - should be unique. This name is used as loginnname.
		$password
		$fullname
		$email
		$language - language preference for user interface
		$rights - 'admin' for admin rights, empty for normal user
	*/
	function hypha_setUser($user, $username, $password, $fullname, $email, $language, $rights) {
		global $hyphaXml;
		$hyphaXml->requireLock();
		if ($username && hypha_getUserByName($username)
		    && hypha_getUserByName($username) != $user && !isAdmin()) {
			return __('user-exists');
		} else if (($username == 'hypha')
		           || (strpos($username, 'newUser_') !== false)) {
			return __('cant-use-that-name');
		} else {
			if ($username) $user->setAttribute('username', $username);
			if ($password) $user->setAttribute('password', hashPassword($password));
			if ($fullname) $user->setAttribute('fullname', $fullname);
			if ($email) $user->setAttribute('email', $email);
			if ($language) $user->setAttribute('language', $language);
			if ($rights) $user->setAttribute('rights', $rights);
			return false;
		}
	}

	/*
		Function: hypha_addUserRegistrationKey
		sets extra key attribute for registration or recovering account.

		Parameters:
		$user - DOMElement containing user settings
		$key
	*/
	function hypha_addUserRegistrationKey($user, $key) {
		global $hyphaXml;
		$hyphaXml->requireLock();
		$user->setAttribute('key', $key);
	}

	/*
		Function: hypha_removeUserRegistrationKey
		remove key attribute.

		Parameters:
		$user - DOMElement containing user settings
		$key
	*/
	function hypha_removeUserRegistrationKey($user) {
		global $hyphaXml;
		$hyphaXml->requireLock();
		$user->removeAttribute('key');
	}
	/*
		Function: hypha_getPageList
		returns hypha pagelist DOMElement
	*/
	function hypha_getPageList() {
		global $hyphaXml;
		return $hyphaXml->getElementsByTagName('pageList')->Item(0)->getElementsByTagName('page');
	}

	function hypha_getPage($language, $name) {
		foreach(hypha_getPageList() as $page) {
			foreach($page->getElementsByTagName('language') as $lang) {
				if(($lang->getAttribute('id')==$language) && (strtolower($lang->getAttribute('name'))==strtolower($name))) return $page;
			}
		}
		return false;
	}

	function hypha_getPageById($id) {
		foreach(hypha_getPageList() as $page) {
			if($page->getAttribute('id')==$id) return $page;
		}
		return false;
	}

	function hypha_pageGetLanguage($page, $language) {
		foreach($page->getElementsByTagName('language') as $lang) {
			if($lang->getAttribute('id')==$language) return $lang;
		}
		return false;
	}

	/*
		Function: hypha_addPage
		adds a new page to the pagelist. Returns false on success, error message on failure.

		Parameters:
		$type - page type, e.g. 'text'
		$language - page language, e.g. 'en'
		$name - page name, e.g. 'home'
		$private - 'on' for private page (can be accessed only by registered users), empty or 'off' for public page
	*/
	function hypha_addPage($type, $language, $name, $private) {
		global $hyphaXml;
		$hyphaXml->requireLock();
		if (hypha_getPage($language, $name)) return __('page-exists');
		$id = uniqid();
		$newPage = $hyphaXml->createElement('page');
		$newPage->setAttribute('id', $id);
		$newPage->setAttribute('type', $type);
		$hyphaXml->getElementsByTagName('pageList')->Item(0)->appendChild($newPage);
		return hypha_setPage($newPage, $language, $name, $private);
	}

	function hypha_deletePage($id) {
		global $hyphaXml;
		$hyphaXml->requireLock();
		$targetPage = hypha_getPageById($id);
		if ($targetPage instanceof HyphaDomElement) {
			$hyphaXml->getElementsByTagName('pageList')->Item(0)->removeChild($targetPage);
		}
	}

	/*
		Function: hypha_setPage
		updates page settings. Parameters which are left empty are not updated. Returns false on success, error message on failure.

		Parameters:
		$page - DOMElement containing page settings
		$language - page language
		$name - page name
		$private - 'on' for private page (can be accessed only by registered users), empty or 'off' for public page
	*/
	function hypha_setPage($page, $language, $name, $private) {
		global $hyphaXml;
		$hyphaXml->requireLock();

		// look for language entry
		// update name if not conflicting with another page or return error message.
		$langFound = false;
		foreach($page->getElementsByTagName('language') as $lang) if ($lang->getAttribute('id')==$language) {
			if ($name && ($name!=$lang->getAttribute('name'))) {
				$try = hypha_getPage($page->getAttribute('language'), $name);
				if (!$try || $try===$page) $lang->setAttribute('name', $name);
				else return __('page-name-conflict');
			}
			$langFound = true;
		}

		// create new translation entry if language doesn't exist yet for this page. Return error message if no page name is given or if given language/name combination already exists.
		if ($language && !$langFound) {
			if(!$name) return __('no-page-name');
			elseif (hypha_getPage($language, $name)) return __('page-name-conflict');
			else {
				$newLanguage = $hyphaXml->createElement('language');
				$newLanguage->setAttribute('id', $language ? $language : hypha_getDefaultLanguage());
				$newLanguage->setAttribute('name', $name);
				$page->appendChild($newLanguage);
			}
		}
		$private = in_array($private, ['true', '1', 'on']) ? 'on' : 'off';
		if ($private!=$page->getAttribute('private')) $page->setAttribute('private', $private);

		return false;
	}

	/*
		Function: hypha_make_absolute
		If the url is relative, make it absolute by prefixing $hyphaUrl.
	*/
	function hypha_make_absolute($url) {
		global $hyphaUrl;
		// This regex is based on RFC3986 that defines URI
		// formats. It matches all urls that are already
		// absolute by matching:
		//  - Uris starting with a scheme (e.g. "http:")
		//  - Uris starting with a / (which refer to the server
		//    root, not hypha root.
		// This incorrectly identifies paths that have a scheme
		// but are still relative (e.g. http:foo/bar,
		// path-rootless in the RFC) as absolute, but those are
		// not really used in practice anyway.
		// TODO: Absolute paths without a hostname should get
		// the hostname from $hyphaUrl prepended, to make these
		// urls work in non-HTTP contexts (e.g. e-mail).
		if (preg_match("#^([a-zA-Z][a-zA-Z0-9+.-]*:|/)#", $url))
			return $url;
		return $hyphaUrl . $url;
	}

	/*
		Function: hypha_make_relative
		If the url is an url within this hypha installation,
		make it a relative to $hyphaUrl.
	*/
	function hypha_make_relative($url) {
		global $hyphaUrl;
		$len = strlen($hyphaUrl);
		if (substr($url, 0, $len) == $hyphaUrl)
			$url = substr($url, $len);
		return $url;
	}

	/*
		Function: hypha_getAndClearDigest
		returns hypha digest file contents, and empties it
	*/
	function hypha_getAndClearDigest() {
		$contents = Hypha::$data->digest->lockAndRead();
		Hypha::$data->digest->writeAndUnlock('');
		return $contents;
	}

	/*
		Function: hypha_addDigest
		Adds contents to the hypha digest file.
	*/
	function hypha_addDigest($contents) {
		$old = Hypha::$data->digest->lockAndRead();
		Hypha::$data->digest->writeAndUnlock($old . $contents);
	}

	/*
		Function: hypha_getStats
		returns hypha stats for the given timestamp
	*/
	function hypha_getStats($timestamp) {
		$contents = Hypha::$data->stats->read();
		if (!$contents)
			return 0;
		$stats = json_decode($contents, true);
		if (!array_key_exists($timestamp, $stats))
			return 0;
		return $stats[$timestamp];
	}

	/*
		Function: hypha_incrementStats
		Increment the page view counter for the given timestamp
	*/
	function hypha_incrementStats($timestamp) {
		$contents = Hypha::$data->stats->lockAndRead();
		if (!$contents)
			$stats = Array();
		else
			$stats = json_decode($contents, true);

		if (!array_key_exists($timestamp, $stats))
			$stats[$timestamp] = 0;

		$stats[$timestamp]++;
		Hypha::$data->stats->writeAndUnlock(json_encode($stats));
	}

	/*
		Function: hypha_indexLanguages
		returns list of available translations for the given page. If no page is given, an index for all available languages if given

		Parameters:
		$page - DOMElement containing page settings
		$language - page language
	*/
	function hypha_indexLanguages($page, $language, $_defaultPage = HyphaRequest::HYPHA_SYSTEM_PAGE_INDEX.'/') {
		if ($_defaultPage) echo '650 default' . $_defaultPage;
		$langList = array();
		foreach(hypha_getPageList() as $_page) foreach($_page->getElementsByTagName('language') as $_lang) {
			if (!in_array($_lang->getAttribute('id'), $langList)) $langList[] = $_lang->getAttribute('id');
		}
		if (count($langList)) asort($langList);

		$pageLangList = array();
		if ($page) foreach($page->getElementsByTagName('language') as $_lang) {
			$pageLangList[$_lang->getAttribute('id')] = $_lang->getAttribute('name');
		}

		$index = '<span class="prefix">' . __('languages') . ': </span>';
		foreach($langList as $lang) {
			if ($lang == $language) $index.= '<span class="language selected">'.$lang.'</span>';
			elseif (!$page || array_key_exists($lang, $pageLangList)) {
				if ($page) {
					$index.= '<span class="language"><a href="'.$lang.'/'.$pageLangList[$lang].'">'.$lang.'</a></span>';
				}
				else $index.= '<span class="language"><a href="'.$_defaultPage.$lang.'">'.$lang.'</a></span>';
			}
			else $index.= '<span class="language disabled">'.$lang.'</span>';
		}
		return $index;
	}

	/*
		Function: hypha_indexPages
		returns alphabetical overview of all pages in the given language

		Parameters:
		$language
	*/
	function hypha_indexPages($language) {
		// get list of available pages and sort alphabetically
		foreach(hypha_getPageList() as $page) {
			$lang = hypha_pageGetLanguage($page, $language);
			if ($lang) if (isUser() || ($page->getAttribute('private')!='on')) $pageList[] = $lang->getAttribute('name').($page->getAttribute('private')=='on' ? '&#;' : '');
//      $pageList[] = $lang->getAttribute('name')." (".$lang->getAttribute('id').")".($page->getAttribute('private')=='on' ? '&#;' : '');
	}
		if ($pageList) array_multisort(array_map('strtolower', $pageList), $pageList);

		// add capitals
		$capital = 'A';
		if ($pageList) foreach($pageList as $pagename) {
			while($capital < strtoupper($pagename[0])) $capital++;
			if (strtoupper($pagename[0]) == $capital) {
				$htmlList[] = '<div style="text-align:center;">- '.$capital.' -</div>';
				$capital++;
			}
			$privatePos = strpos($pagename, '&#;');
			if ($privatePos) $pagename = substr($pagename, 0, $privatePos);
			//$closed = strpos($pagename,"(");
			//if ($closed) $pagenameLink = substr(pagename,0,$closed);
			//$htmlList[] = '<a href="'.$language.'/'.$pagenamelink.'">'.showPagename($pagename).'</a>'.asterisk($privatePos).'<br/>';
$htmlList[] = '<a href="'.$language.'/'.$pagename.'">'.showPagename($pagename).'</a>'.asterisk($privatePos).'<br/>';		}

		// output list in a maximum of 3 colunms with a minimum of 10 lines per column
		$lines = count($htmlList);
		$columns = min($lines/10, 3);
		$i = 0;
		$html = "<h5>".__('index')."</h5>";
		$html .= '<table><tr>';
		for ($column=1; $column<$columns+1; $column++) {
			$html.= '<td>';
			while($i<$lines && $i<$lines*$column/$columns) $html.= $htmlList[$i++];
			$html.= '</td>';
		}
		$html.= '</tr></table>';
		return $html;
	}

	function hypha_indexImages() {
		return 'image index is not yet implemented';
	}

	function hypha_indexFiles() {
		return 'file index is not yet implemented';
	}

			/*
			Function: hypha_reviewPages
			returns alphabetical overview of all pages that need te be reviewed
			Parameters:
			$language
		*/
		function hypha_reviewPages($language) {
			// get list of available pages that need to be reviewed and sort alphabetically

			if (!isUser()) return '';//'log in to get list of review pages';
			$lines = 0;
			foreach(hypha_getPageList() as $page) {
				if ($page->getAttribute('type')=='peer_reviewed_article') {
					$lang = hypha_pageGetLanguage($page, $language);
					if ($lang) {
					 $pageList[] = $language.'/'.$lang->getAttribute('name');
					 $lines +=1;
					}
				}
			}

			if ($pageList) array_multisort(array_map('strtolower', $pageList), $pageList);
			$html = '<span class="reviewtitle">'.$lines.' '.__('reviewtitle').'</span><br>';
			foreach($pageList as $pagename) {
				$spagename = preg_replace('(../)','',$pagename);
				$html.='<a id="'.$spagename.'" href="'.$pagename.'" name="'.$spagename.'">'.$spagename.'</a>';
			}
			return $html;
		} //end review pages
