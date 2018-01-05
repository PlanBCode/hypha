<?php
	include_once('events.php');

	/*
		Title: Pages

		This chapter describes the way pages are registered.
	*/

	/*
		Class: Page
		abstract class for handling a certain kind of data
	*/
	abstract class Page {
		public $pageListNode, $html, $language, $pagename, $args, $privateFlag;
		function __construct($node, $args) {
			global $hyphaHtml;
			$this->html = $hyphaHtml;
			$this->args = $args;
			if ($node)
				$this->replacePageListNode($node);
		}

		protected function replacePageListNode($node) {
			global $hyphaLanguage;
			$this->pageListNode = $node;
			$this->privateFlag = ($node->getAttribute('private') == 'on' ? true : false);
			$language = hypha_pageGetLanguage($node, $hyphaLanguage);
			$this->language = $language->getAttribute('id');
			$this->pagename = $language->getAttribute('name');
		}

		/**
		 * Return the given url argument, or null if it is not
		 * present.
		 */
		protected function getArg($index) {
			if (array_key_exists($index, $this->args))
				return $this->args[$index];
			return null;
		}

		abstract function build();
	}

	/*
		Function: addNewPageRoutine
		adds javascript for adding new pages

		Parameters:
		html - main document html object
		query - page query containing language and pagename
		types - array with available page types (i.e. 'text', 'blog' etc)
	*/
	function addNewPageRoutine($html, $query, $types) {
		global $hyphaLanguage;
		// If a pagename is specified that does not exist yet,
		// prefill that page name
		if (count($query) >= 2 && !hypha_getPage($query[0], $query[1]))
			$pagename = $query[1];
		else
			$pagename = '';

		ob_start();
?>
<script>
	function validatePagename(obj) {
		var pos = obj.selectionStart;
		var val = obj.value.replace(/\s+/g, ' ').replace(/^\s|[^\d\w\s\.-]/gi, '');
		if (val.length < pos) pos = val.length;
		obj.value = val;
		obj.setSelectionRange(pos, pos);
	}
	function newPage() {
		html = '<table class="section"><tr><th colspan="2"><?=__('create-new-page')?></td><tr>';
		html+= '<tr><th><?=__('type')?></th><td><select id="newPageType" name="newPageType">' + '<?php foreach($types as $type) echo '<option value="'.$type.'"'.($type=='textPage' ? 'selected="selected"' : '').'>'.$type.'</option>'; ?>' + '</select></td></tr>';
		html+= '<tr><th><?=__('name')?></th><td><input type="text" id="newPagename" value="<?=$pagename?>" onblur="validatePagename(this);" onkeyup="validatePagename(this); document.getElementById(\'newPageSubmit\').disabled = this.value ? false : true;"/></td></tr>';
		html+= '<tr><td></td><td><input type="checkbox" id="newPagePrivate" name="newPagePrivate"/> <?=__('private-page')?></td></tr>';
		html+= '<tr><td></td><td><input type="button" class="button" value="<?=__('cancel')?>" onclick="document.getElementById(\'popup\').style.visibility=\'hidden\';" />';
		html+= '<input type="submit" id="newPageSubmit" class="button editButton" value="<?=__('create')?>" <?= $pagename ? '' : 'disabled="true"' ?> onclick="hypha(\'<?=$hyphaLanguage?>/\' + document.getElementById(\'newPagename\').value + \'/edit\', \'newPage\', document.getElementById(\'newPagename\').value);" /></td></tr></table>';
		document.getElementById('popup').innerHTML = html;
		document.getElementById('popup').style.left = document.getElementById('hyphaCommands').offsetLeft + 'px';
		document.getElementById('popup').style.top = (document.getElementById('hyphaCommands').offsetTop + 25) + 'px';
		document.getElementById('popup').style.visibility = 'visible';
		document.getElementById('newPagename').focus();
	}
</script>
<?php
		$html->writeScript(ob_get_clean());
	}

	/*
		Function: loadLanguage
		Pulls the language from the url.

		Parameters:
		args - array with arguments from the page url, below the hypha root url.

		Returns the same array, with any language removed
	*/
	function loadLanguage($args) {
		global $isoLangList, $hyphaLanguage;
		// set wiki language. we want to store this in a session variable, so we don't loose language when an image or the settingspage are requested
		if (count($args) > 0 && array_key_exists($args[0], $isoLangList)) {
			$hyphaLanguage = $args[0];
			array_shift($args);
		} else {
			$hyphaLanguage = hypha_getDefaultLanguage();
		}

		if (!isset($_SESSION['hyphaLanguage']) || $hyphaLanguage != $_SESSION['hyphaLanguage']) {
			session_start();
			$_SESSION['hyphaLanguage'] = $hyphaLanguage;
			session_write_close();
		}
		return $args;
	}

	/*
		Function: loadPage
		loads all html needed for page pagename/view

		Parameters:
		args - array with arguments from the page url, below the
		hypha root url. Any language component should already be
		removed by loadLanguage().

		See Also:
		<buildhtml>
	*/
	function loadPage($args) {
		global $isoLangList, $hyphaHtml, $hyphaPageTypes, $hyphaPage, $hyphaLanguage, $hyphaUrl, $hyphaXml;

		// Make accessing args easier by making sure it always
		// has sufficient elements.
		while (count($args) < 2)
			array_push($args, null);


		switch ($args[0]) {
			case 'files':
				serveFile('data/files/' . $args[1], 'data/files');
				exit;
			case 'images':
				serveFile('data/images/' . $args[1], 'data/images');
				exit;
			case 'index':
				$hyphaHtml->writeToElement('langList', hypha_indexLanguages('', ''));
				switch($args[1]) {
					case 'images':
						$hyphaHtml->writeToElement('pagename', __('image-index'));
						$hyphaHtml->writeToElement('main', hypha_indexImages());
						break;
					case 'files':
						$hyphaHtml->writeToElement('pagename', __('file-index'));
						$hyphaHtml->writeToElement('main', hypha_indexFiles());
						break;
					default:
						$languageName = $isoLangList[$hyphaLanguage];
						$languageName = substr($languageName, 0, strpos($languageName, ' ('));
						$hyphaHtml->writeToElement('pagename', __('page-index').': '.$languageName);
						$hyphaHtml->writeToElement('main', hypha_indexPages($hyphaLanguage));
						break;
				}
				break;
			case 'settings':
				if (isUser() || $args[1]=='register') {
					$hyphaPage = new settingspage(array_slice($args, 1));
				}
				else {
					header('Location: '.$hyphaUrl.hypha_getDefaultLanguage().'/'.hypha_getDefaultPage());
					exit;
				}
				break;
			case 'upload':
				if ($args[1]=='image') {
					if(!$_FILES['wymFile']) $response = __('too-big-file').ini_get('upload_max_filesize');
					else {
						$ext = strtolower(substr(strrchr($_FILES['wymFile']['name'], '.'), 1));
						@$size = getimagesize($_FILES['wymFile']['tmp_name']);
						if(!$size || !in_array($ext, array('jpg','jpeg','png','gif','bmp'))) $response = __('invalid-image-file').ini_get('upload_max_filesize');
						else {
							$filename = uniqid().'.'.$ext;
							if(!move_uploaded_file($_FILES['wymFile']['tmp_name'], 'data/images/'.$filename)) $response = __('server-error');
							else $response = 'images/'.$filename;
						}
					}
					echo '<script language="javascript" type="text/javascript">window.top.window.uploadResponse(\''.$response.'\');</script>';
				}
				exit;
			case 'chooser':
				$query = $_GET['term'];
				$pageList = array();
				foreach(hypha_getPageList() as $page) {
					foreach($page->getElementsByTagName('language') as $language) {
						if ($language->getAttribute('id')==$args[1]) {
							if (isUser() || ($page->getAttribute('private')!='on')) if(strpos(strtolower($language->getAttribute('name')), strtolower($query))!==false) $pageList[] = showPagename($language->getAttribute('name'));
						}
					}
				}
				asort($pageList);
				$html = '[';
				foreach($pageList as $page) $html.= '"'.$page.'",';
				$html = rtrim($html, ",");
				$html.= ']';
				echo $html;
				exit;
				break;
			default:
				// fetch the requested page
				$_name = $args[0] ? $args[0] : hypha_getDefaultPage();
				$_node = hypha_getPage($hyphaLanguage, $_name);

				if ($_node) {
					$_type = $_node->getAttribute('type');
					$hyphaPage = new $_type($_node, array_slice($args, 1));

					// write stats
					if (!isUser())
						hypha_incrementStats(hypha_getLastDigestTime()+hypha_getDigestInterval());
				}
				else {
					http_response_code(404);
					notify('error', __('no-page'));
					if(isUser()) $hyphaHtml->writeToElement('main', '<span class="right""><input type="button" class="button" value="'.__('create').'" onclick="newPage();"></span>');
					$hyphaPage = false;
				}
		}
	}

	/*
		Function: newPage

		Handles the newPage command by creating a new page and redirecting to its edit interface.
	*/
	registerCommandCallback('newPage', 'newPage');
	function newPage($newName) {
		global $hyphaXml, $hyphaUrl, $hyphaLanguage;


		$newName = validatePagename($newName);
		if (isUser()) {
			$hyphaXml->lockAndReload();
			$error = hypha_addPage($_POST['newPageType'], $hyphaLanguage, $newName, isset($_POST['newPagePrivate']));
			$hyphaXml->saveAndUnlock();
			if ($error) {
				notify('error', $error);
				return 'reload';
			} else {
				return ['redirect', $hyphaUrl . $hyphaLanguage . '/' . $newName . '/edit'];
			}
		}
	}

	/*
		Function: validatePageName
		sanitizes pagenames

		converts whitespace sequences to a single spaces, removes non allowed characters and converts spaces to underscores inorder to generate unified pagenames that yield friendly urls, e.g. dom.ain/en/my_page in stead of dom.ain/en/my%20page

		Parameters:
		name - pagename
	*/
	function validatePagename($name) {
		return preg_replace('/\s/', '_', preg_replace('/^\s|\s$|[^\d\w\s\.-_]/i', '', preg_replace('/\s+/', ' ', $name)));
	}

	/*
		Function: showPageName
		convert underscores back into spaces for display

		Parameters:
		name - pagename
	*/
	function showPagename($name) {
		return preg_replace('/_/', ' ', $name);
	}

	/*
		Function: wikify
		Convert links containing page ids back to working links,
		adding some extra attributes to the HTML node.

		Parameters:
		doc - A DomWrap\Document to process
	*/
	function dewikify($doc) {
		foreach ($doc->findXPath("//a[@href]") as $node) {
			dewikify_link($node);
		}
	}

	function dewikify_link($node) {
		global $hyphaXml, $isoLangList;
		global $hyphaLanguage;
		global $hyphaPage;

		$uri = $node->getAttribute('href');

		$path = explode('/', $uri, 2);
		$parts = explode(':', $path[0], 2);
		if (count($parts) != 2 || $parts[0] != 'hypha')
			return;

		$page = hypha_getPageById($parts[1]);
		if (!$page)
			return;

		// Find out what language to use (current language,
		// default language, or any language offered by the
		// page).
		$language = hypha_pageGetLanguage($page, $hyphaLanguage);
		if (!$language) $language = hypha_pageGetLanguage($page, hypha_getDefaultLanguage());
		if (!$language) $language = $page->getElementsByTagName('language')->Item(0);
		if (!$language)
			return;

		// Use the page name (in the appropriate language) as
		// the link text
		if ($node->text() == '')
			$node->text(showPagename($language->getAttribute('name')));

		// Check permissions, replace by a span with just the
		// pagename if the link would lead to an inaccessible
		// page
		if(!isUser() && $page->getAttribute('private') == 'on') {
			$span = $page->createElement('span');
			$span->text($node->text());
			$node->replaceWith($span);
			return;
		}

		// Add appropriate class and/or title attributes
		if ($language->getAttribute('id') != $hyphaLanguage) {
			if (!$node->getAttribute('title'))
				$node->setAttribute('title', __('page-in-other-language'));
			$node->addClass('otherLanguageLink');
		} else if ($hyphaPage && $language->getAttribute('name') == $hyphaPage->pagename) {
			$node->addClass('currentPageLink');
		}

		// Generate and set the url
		$url = $language->getAttribute('id').'/'.urlencode($language->getAttribute('name'));
		if (count($path) > 1)
			$url .= '/' . $path[1];
		$node->setAttribute('href', $url);
	}

	/* Like wikify, but accepts a HTML string instead of a document */
	function wikify_html($html) {
		$doc = new DomWrap\Document();
		$doc->html($html);
		wikify($doc);

		return $doc->documentElement->html();
	}

	/*
		Function: wikify
		Convert urls in page links into hypha page ids, and make
		all other urls relative.

		Parameters:
		doc - A DomWrap\Document to process
	*/
	function wikify($doc) {
		foreach ($doc->findXPath("//*[@href] | //*[@src]") as $node) {
			// Make all urls relative
			foreach (['href', 'src'] as $attr) {
				if ($node->hasAttribute($attr))
					$node->setAttribute($attr, hypha_make_relative($node->getAttribute($attr)));
			}
			// Additionally wikify links (to pages)
			if ($node->tagName == 'a')
				wikify_link($node);
		}

		return $doc->documentElement->html();
	}

	function wikify_link($node) {
		global $hyphaXml, $isoLangList;

		$path = explode('/', $node->getAttribute('href'), 3);
		if (count($path) < 2)
			return;

		$language = $path[0];
		$pagename = $path[1];

		// Prevent mangling urls to other files, such as images or downloads
		if (!array_key_exists($language, $isoLangList))
			return;

		$page = hypha_getPage($language, $pagename);

		if (!$page) {
			$hyphaXml->lockAndReload();
			// recheck just in case it got added in the
			// meanwhile
			$page = hypha_getPage($language, $pagename);
			if (!$page) {
				$error = hypha_addPage('textpage', $language, $pagename, '');
				$hyphaXml->saveAndUnlock();
				if ($error) notify('error', $error);
				$page = hypha_getPage($language, $pagename);
			} else {
				$hyphaXml->unlock();
			}
		}

		// If creating the page failed, leave the link unchanged
		if (!$page)
			return;

		// Clean up the node from stuff dewikify may have added.
		$node->removeClass('otherLanguageLink');
		$node->removeClass('currentPageLink');
		if ($node->getAttribute('class') == '')
			$node->removeAttribute('class');
		if ($node->getAttribute('title') == __('page-in-other-language'))
			$node->removeAttribute('title');

		$uri = 'hypha:' . $page->getAttribute('id');
		if (count($path) > 2)
			$uri .= '/' . $path[2];
		$node->setAttribute('href', $uri);

		// Clear out the page name in the link text, but keep
		// any custom link name
		$lang = hypha_pageGetLanguage($page, $language);
		if ($node->text() == showPagename($lang->getAttribute('name')))
			$node->text('');
	}

	/*
		Function: versionSelector
		generate html select element with available revisions for given page

		Parameters:
		page - page node from hypha pagelist
	*/
	function versionSelector($page) {
		if ($page->xml->hasVersions()) {
			$_action = makeAction($page->language.'/'.$page->pagename, '', 'version');
			$html = __('version').': '.'<select class="version" name="version" onchange="'.$_action.'">';

			$history = array();
			foreach($page->xml->getElementById($page->language)->getElementsByTagName('version') as $v) {
				$timeStamp = $v->getAttribute('xml:id');
				$history[$timeStamp] = date('j-m-y, H:i', ltrim($timeStamp, 't')).', '.$v->getAttribute('author');
			}
			if (!empty($history)) {
				krsort($history);
				reset($history);
				$current = key($history);
				foreach($history as $id => $tag)
					if ($id!=$current) $html.='<option value="'.$id.'"'.((isset($_POST['version']) && $id == $_POST['version']) ? ' selected="selected"' : '').'>'.$tag.'</option>';
					else $html.='<option value=""'.(!isset($_POST['version']) ? ' selected="selected"' : '').'>'.$tag.'</option>';
			}
			$html.= '</select>';
		}
		else $html = __('no-versions');
		return $html;
	}
