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

		public static function getDatatypeName() {
			return str_replace('_', ' ', get_called_class());
		}

		protected function deletePage() {
			global $hyphaXml, $hyphaUser;
			$id = $this->pageListNode->getAttribute('id');
			$hyphaXml->lockAndReload();
			hypha_deletePage($id);
			$hyphaXml->saveAndUnlock();

			$file = 'data/pages/' . $id;
			if (file_exists($file)) {
				unlink($file);
			}

			writeToDigest($hyphaUser->getAttribute('fullname').__('deleted-page').$this->language.'/'.$this->pagename, 'page delete');
		}

		protected function replacePageListNode($node) {
			global $O_O;
			$this->pageListNode = $node;
			$this->privateFlag = in_array($node->getAttribute('private'), ['true', '1', 'on']);
			$language = hypha_pageGetLanguage($node, $O_O->getContentLanguage());
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
		global $hyphaContentLanguage;
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
		html+= "<span onclick=\"position(event,'maak nieuwe pagina','en',this)\" class=\"hyphaInfoButton\">i</span>";
		// TODO [LRM]: find better way to set default new page type.
		html+= '<tr><th><?=__('type')?></th><td><select id="newPageType" name="newPageType">' + '<?php foreach($types as $type => $datatypeName) echo '<option value="'.$type.'"'.($type=='textpage' ? 'selected="selected"' : '').'>'.$datatypeName.'</option>'; ?>' + '</select></td></tr>';
		html+= '<tr><th><?=__('pagename')?></th><td><input type="text" id="newPagename" value="<?=$pagename?>" onblur="validatePagename(this);" onkeyup="validatePagename(this); document.getElementById(\'newPageSubmit\').disabled = this.value ? false : true;"/></td></tr>';
		html+= '<tr><td></td><td><input type="checkbox" id="newPagePrivate" name="newPagePrivate"/> <?=__('private-page')?></td></tr>';
		html+= '<tr><td></td><td><input type="button" class="button" value="<?=__('cancel')?>" onclick="document.getElementById(\'popup\').style.visibility=\'hidden\';" />';
		html+= '<input type="submit" id="newPageSubmit" class="button editButton" value="<?=__('create')?>" <?= $pagename ? '' : 'disabled="true"' ?> onclick="hypha(\'<?=$hyphaContentLanguage?>/\' + document.getElementById(\'newPagename\').value + \'/edit\', \'newPage\', document.getElementById(\'newPagename\').value);" /></td></tr></table>';
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
		Function: loadPage
		loads all html needed for page pagename/view

		Parameters:
		HyphaRequest $hyphaRequest

		See Also:
		<buildhtml>
	*/
	function loadPage(RequestContext $O_O) {
		global $isoLangList, $hyphaHtml, $hyphaPage, $hyphaUrl;

		$request = $O_O->getRequest();
		$args = $request->getArgs();

		if (!$request->isSystemPage()) {
			// fetch the requested page
			$_name = $request->getPageName();
			if ($_name === null) {
				$_name = hypha_getDefaultPage();
			}
			$_node = hypha_getPage($O_O->getContentLanguage(), $_name);

			$isPrivate = in_array($_node->getAttribute('private'), ['true', '1', 'on']);
			if ($_node && (!$isPrivate || isUser())) {
				$_type = $_node->getAttribute('type');
				$hyphaPage = new $_type($_node, $args);

				// write stats
				if (!isUser())
					hypha_incrementStats(hypha_getLastDigestTime() + hypha_getDigestInterval());
			} else {
				http_response_code(404);
				if ($isPrivate && !isUser()) {
					notify('error', __('login-to-view'));
				} else {
					notify('error', __('no-page'));
				}
				if (isUser()) $hyphaHtml->writeToElement('main', '<span class="right""><input type="button" class="button" value="' . __('create') . '" onclick="newPage();"></span>');
				$hyphaPage = false;
			}

			return;
		}

		// Make accessing args easier by making sure it always
		// has sufficient elements.
		while (count($args) < 1)
			array_push($args, null);

		switch ($request->getSystemPage()) {
			case HyphaRequest::HYPHA_SYSTEM_PAGE_FILES:
				serveFile('data/files/' . $args[0], 'data/files');
				exit;
			case HyphaRequest::HYPHA_SYSTEM_PAGE_IMAGES:
				serveFile('data/images/' . $args[0], 'data/images');
				exit;
			case HyphaRequest::HYPHA_SYSTEM_PAGE_INDEX:
				$hyphaHtml->writeToElement('langList', hypha_indexLanguages('', ''));
				switch ($args[0]) {
					case 'images':
						$hyphaHtml->writeToElement('pagename', __('image-index'));
						$hyphaHtml->writeToElement('main', hypha_indexImages());
						break;
					case 'files':
						$hyphaHtml->writeToElement('pagename', __('file-index'));
						$hyphaHtml->writeToElement('main', hypha_indexFiles());
						break;
					default:
						$languageName = $isoLangList[$O_O->getContentLanguage()];
						$languageName = substr($languageName, 0, strpos($languageName, ' ('));
						$hyphaHtml->writeToElement('pagename', __('page-index').': '.$languageName);
						$hyphaHtml->writeToElement('main', hypha_indexPages($O_O->getContentLanguage()));
						break;
				}
				break;
			case HyphaRequest::HYPHA_SYSTEM_PAGE_HELP: //bz help
				if ($args[0] == 'help') {
					echo getButtonInfo($args);
					exit;
				} else {
					$hyphaHtml->find('#pagename')->text( __('help-pagetitle'));  //"help-pagetitle" => "Help index",
					$hyphaPage = new helpPage($args);
					//echo "pages: 222<br>\n";
					if (count($args) == 2) if (hypha_isLanguage($args[1])) {//echo " -pages.php 222- " . $args[1];
						$hyphaHtml->writeToElement('langList', hypha_helpLanguages('',$args[1]));
					} else {
						$contentLanguage = $O_O->getContentLanguage();
						$hyphaHtml->writeToElement('langList', hypha_helpLanguages('',$contentLanguage));
					}
				}
				break;

			case HyphaRequest::HYPHA_SYSTEM_PAGE_SETTINGS:
				if (isUser() || $args[0]=='register') {
					$hyphaPage = new settingspage($args);
				}
				else {
					header('Location: '.$hyphaUrl.hypha_getDefaultLanguage().'/'.hypha_getDefaultPage());
					exit;
				}
				break;
			case HyphaRequest::HYPHA_SYSTEM_PAGE_UPLOAD:
				if ($args[0]=='image') {
					$return = null;
					if(!$_FILES['uploadedfile']) $response = __('too-big-file').ini_get('upload_max_filesize');
					else {
						$ext = strtolower(substr(strrchr($_FILES['uploadedfile']['name'], '.'), 1));
						@$size = getimagesize($_FILES['uploadedfile']['tmp_name']);
						if(!$size || !in_array($ext, array('jpg','jpeg','png','gif','bmp'))) $response = __('invalid-image-file').ini_get('upload_max_filesize');
						else {
							$maxSize = [1120, 800];
							$needResize = $size[0] > $maxSize[0] || $size[1] > $maxSize[1];
							$filename = uniqid() . '.' . $ext;
							$destinations = ['org' => 'data/images/org/' . $filename, 'img' => 'data/images/' . $filename];
							$destinationPaths = ['org' => 'images/org/' . $filename, 'img' => 'images/' . $filename];
							if ($needResize && !file_exists('data/images/org/')) {
								mkdir('data/images/org/', 0777, true);
							}
							$orgUrlIndex = $needResize ? 'org' : 'img';
							if (move_uploaded_file($_FILES['uploadedfile']['tmp_name'], $destinations[$orgUrlIndex])) {
								$response = 'images/' . $filename . '?' . 'w=' . $size[0] . '&h=' . $size[1];
								if ($needResize) {
									if (true !== image_resize($destinations[$orgUrlIndex], $destinations['img'], $maxSize[0], $maxSize[1])) {
										$response = __('server-error-resize-image');
									}
								}
								$return = [[
									'original_filename' => $_FILES['uploadedfile']['name'],             // The original filename, this will be put in as the "alt" text
									'downloadUrl'       => $hyphaUrl . $destinationPaths[$orgUrlIndex], // The URL to the original file to be downloaded. This is not used by image_upload, but by site_links
									'thumbUrl'          => $hyphaUrl . $destinationPaths['img'],        // The URL to be used, it's called a Thumb URL because you may have used $_POST['thumbnailSize'] to resize it. This is used by site_links but not image_upload
								]];
							} else {
								$response = __('server-error');
							}
						}
					}
					if ($return !== null) {
						echo json_encode($return);
					} else {
						echo '<script language="javascript" type="text/javascript">window.top.window.uploadResponse(\''.$response.'\');</script>';
					}
				}
				exit;
			case HyphaRequest::HYPHA_SYSTEM_PAGE_CHOOSER:
				$query = $_GET['term'];
				$pageList = array();
				foreach(hypha_getPageList() as $page) {
					foreach($page->getElementsByTagName('language') as $language) {
						if ($language->getAttribute('id')==$args[0]) {
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
		}
	}

	/*
		Function: newPage

		Handles the newPage command by creating a new page and redirecting to its edit interface.
	*/
	registerCommandCallback('newPage', 'newPage');
	function newPage($newName) {
		global $hyphaXml, $hyphaUrl, $hyphaContentLanguage;


		$newName = validatePagename($newName);
		if (isUser()) {
			$hyphaXml->lockAndReload();
			$error = hypha_addPage($_POST['newPageType'], $hyphaContentLanguage, $newName, isset($_POST['newPagePrivate']));
			$hyphaXml->saveAndUnlock();
			if ($error) {
				notify('error', $error);
				return 'reload';
			} else {
				return ['redirect', $hyphaUrl . $hyphaContentLanguage . '/' . $newName . '/edit'];
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
		doc - A DomWrap\Element to process
	*/
	function dewikify($element) {
		foreach ($element->findXPath("//a[@href]") as $node) {
			dewikify_link($node);
		}
	}

	function dewikify_link($node) {
		global $hyphaXml, $isoLangList;
		global $hyphaContentLanguage;
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
		$language = hypha_pageGetLanguage($page, $hyphaContentLanguage);
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
		if ($language->getAttribute('id') != $hyphaContentLanguage) {
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
		// Wikify a separate element instead of the entire
		// document, since loading html into a document adds
		// <html><body>.
		$elem = $doc->createElement('root');
		$elem->html($html);
		wikify($elem);

		// TODO: This should probably not use getInnerHtml,
		// probably just return the element instead.
		return getInnerHtml($elem);
	}

	/*
		Function: wikify
		Convert urls in page links into hypha page ids, and make
		all other urls relative.

		Parameters:
		doc - A DomWrap\Element to process
	*/
	function wikify($elem) {
		foreach ($elem->findXPath("//*[@href] | //*[@src]") as $node) {
			// Make all urls relative
			foreach (['href', 'src'] as $attr) {
				if ($node->hasAttribute($attr))
					$node->setAttribute($attr, hypha_make_relative($node->getAttribute($attr)));
			}
			// Additionally wikify links (to pages)
			if ($node->tagName == 'a')
				wikify_link($node);
		}
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

	function image_resize($src, $dst, $width, $height) {
		list($w, $h) = getimagesize($src);

		$type = strtolower(substr(strrchr($src,"."),1));
		if ($type == 'jpeg') $type = 'jpg';
		switch ($type) {
			case 'bmp': $img = imagecreatefromwbmp($src); break;
			case 'gif': $img = imagecreatefromgif($src); break;
			case 'jpg': $img = imagecreatefromjpeg($src); break;
			case 'png': $img = imagecreatefrompng($src); break;
			default : return "Unsupported picture type!";
		}

		// resize
		$ratio = min($width/$w, $height/$h);
		$width = $w * $ratio;
		$height = $h * $ratio;

		$new = imagecreatetruecolor($width, $height);

		// preserve transparency
		if ($type == 'gif' || $type == 'png') {
			imagecolortransparent($new, imagecolorallocatealpha($new, 0, 0, 0, 127));
			imagealphablending($new, false);
			imagesavealpha($new, true);
		}

		imagecopyresampled($new, $img, 0, 0, 0, 0, $width, $height, $w, $h);

		switch($type){
			case 'bmp': imagewbmp($new, $dst); break;
			case 'gif': imagegif($new, $dst); break;
			case 'jpg': imagejpeg($new, $dst); break;
			case 'png': imagepng($new, $dst); break;
		}
		return true;
	}

	function hypha_isLanguage($id) {
		$pageLangList = array("nl","en","de"); // replace by hypha language list
		return in_array($id,$pageLangList,true);
	}
