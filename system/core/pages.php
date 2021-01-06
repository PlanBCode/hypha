<?php
	include_once('events.php');

	/*
		Title: Pages

		This chapter describes the way views and pages are registered.
	*/

	/*
		Class: HyphaPage
		abstract class for a page that can process a request.
	*/
	abstract class HyphaPage {
		public $html, $language, $pagename, $O_O, $args, $privateFlag;

		function __construct(RequestContext $O_O) {
			global $hyphaHtml;
			$this->html = $hyphaHtml;
			$this->O_O = $O_O;
			// TODO: Remove args (and let subclasses talk to the request instead)
			$this->args = $O_O->getRequest()->getArgs();
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

		abstract function process(HyphaRequest $request);
	}

	/*
		Class: HyphaSystemPage
		abstract class for a system page that has no backing datatype.
	*/
	abstract class HyphaSystemPage extends HyphaPage {
	}

	/*
		Class: HyphaDatatypePage
		abstract class for a page backed by datatype and xml file.
	*/
	abstract class HyphaDatatypePage extends HyphaPage {
		const INDEX_TABLE_COLUMNS_TITLE = 'title';
		const INDEX_TABLE_COLUMNS_AUTHOR = 'author';
		const INDEX_TABLE_COLUMNS_DATE = 'date';
		const INDEX_TABLE_COLUMNS_STATUS = 'status';

		public $pageListNode, $language, $pagename, $privateFlag;

		function __construct(HyphaDomElement $node, RequestContext $O_O) {
			parent::__construct($O_O);
			$this->replacePageListNode($node);
		}

		/**
		 * @return string
		 */
		public static function getDatatypeName() {
			return str_replace('_', ' ', get_called_class());
		}

		/**
		 * @return array
		 */
		public static function getIndexTableColumns() {
			return [
				__(self::INDEX_TABLE_COLUMNS_TITLE),
				__(self::INDEX_TABLE_COLUMNS_AUTHOR),
				__(self::INDEX_TABLE_COLUMNS_DATE),
			];
		}

		/**
		 * @return array
		 */
		public function getIndexData() {
			$id = $this->pageListNode->getAttribute('id');
			$date = $this->getSortDateTime();
			$dataMtx = [
				__(self::INDEX_TABLE_COLUMNS_TITLE) => [
					'class' => 'type_'.get_class($this).' '.($this->privateFlag ? 'is-private' : 'is-public'),
					'sort' => preg_replace("/[^A-Za-z0-9]/", '', $this->getTitle()).'_'.$id,
					'value' => '<a href="'.$this->language.'/'.$this->pagename.'">'.$this->getTitle().'</a>',
				],
				__(self::INDEX_TABLE_COLUMNS_AUTHOR) => $this->getAuthor(),
				__(self::INDEX_TABLE_COLUMNS_DATE) => [
					'sort' => $date ? $date->format('YmdHis') : '',
					'value' => $date ? $date->format('Y-m-d') : '',
				],
			];
			return array_intersect_key($dataMtx, array_fill_keys(self::getIndexTableColumns(), ''));
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

		protected function replacePageListNode(HyphaDomElement $node) {
			global $O_O;
			$this->pageListNode = $node;
			$this->privateFlag = in_array($node->getAttribute('private'), ['true', '1', 'on']);
			$language = hypha_pageGetLanguage($node, $O_O->getContentLanguage());
			$this->language = $language->getAttribute('id');
			$this->pagename = $language->getAttribute('name');
		}

		/*
		 * Returns the title to be used for e.g. linking to this
		 * page.
		 */
		public function getTitle() {
			// TODO: Implement a meaningful title in all
			// subclasses and make this function abstract?
			return showPagename($this->pagename);
		}

		/**
		 * @return null|string
		 */
		public function getAuthor() {
			$v = $this->getLatestVersion();
			return $v ? $v->getAttribute('author') : null;
		}

		/**
		 * @return null|string
		 */
		public function getLatestVersion() {
			if ($this->xml->hasVersions() && $this->xml->getElementById($this->language)) {
				$history = [];
				foreach($this->xml->getElementById($this->language)->getElementsByTagName('version') as $v) {
					$timestamp = $v->getAttribute('xml:id');
					$history[$timestamp] = $v;
				}
				if (!empty($history)) {
					krsort($history);
					return reset($history);
				}
			}
			return null;
		}

		public function renderSingleLine(HyphaDomElement $container) {
			$h2 = $container->document()->createElement('h2');
			$h2->setText($this->getTitle());
			$h2->addClass('title');
			$h2->appendTo($container);
		}

		public function renderExcerpt(HyphaDomElement $container) {
			// Default to just the single line version
			$this->renderSingleLine($container);
		}

		/*
		 * Returns a date typically used for sorting these pages
		 * (i.e. the publish date or last update date).
		 *
		 * Returns a DateTime object or null if no timestamp is
		 * available.
		 */
		abstract public function getSortDateTime();
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
		var val = obj.value.replace(/\s+/g, ' ').replace(/^\s|[^\d\w\s\-]/gi, '');
		if (val.length < pos) pos = val.length;
		obj.value = val;
		obj.setSelectionRange(pos, pos);
	}
	function newPage() {
<?php
		$popup = <<<EOF
			<table class="section">
				<tr>
					<th colspan="2">[[create-new-page]]<br/>[[instruction-new-page]]</th>
				</tr>
				<tr>
					<th>[[type]]</th>
					<td><select id="newPageType" name="newPageType">[[pagetype-options]]</select>[[help-page-type]]</td>
				</tr>
				<tr>
					<th>[[pagename]]</th>
					<td><input type="text" id="newPagename" value="[[pagename-value]]" onblur="validatePagename(this);" onkeyup="validatePagename(this); document.getElementById('newPageSubmit').disabled = this.value ? false : true;"/>[[help-page-name]]</td>
				</tr>
				<tr>
					<th>[[language]]</th>
					<td><select id="newPageLanguage" name="newPageLanguage">[[language-options]]</select></td>
				</tr>
				<tr>
					<td></td>
					<td><input type="checkbox" id="newPagePrivate" name="newPagePrivate"/>[[private-page]][[help-private]]</td>
				</tr>
				<tr>
					<td></td>
					<td>
						<input type="button" class="button" value="[[cancel]]" onclick="document.getElementById('popup').style.display='none';" />
						<input type="submit" id="newPageSubmit" class="button editButton" value="[[create]]" [[submit-disabled]] onclick="hypha([[content-language-js]] + '/' + document.getElementById('newPagename').value + '/edit', 'newPage', document.getElementById('newPagename').value, $(this).closest('form'));" />
					</td>
				</tr>
			</table>
EOF;
		$pagetype_options = $html->create('<dummy/>');
		foreach($types as $type => $datatypeName) {
			$option = $html->create('<option/>')->setAttr('value', $type)->setText($datatypeName);
			if ($type==hypha_getDefaultNewPageType())
				$option->setAttr('selected', 'selected');
			$pagetype_options->append($option);
		}
		$vars = [
			'create-new-page' => __('create-new-page'),
			'instruction-new-page' => __('instruction-new-page'),
			'type' => __('type'),
			'pagename' => __('pagename'),
			'private-page' => __('private-page'),
			'cancel' => __('cancel'),
			'create' => __('create'),
			'language' => __('language'),
			'help-page-type' => makeInfoButton('help-page-type'),
			'help-page-name' => makeInfoButton('help-page-name'),
			'help-private' => makeInfoButton('help-private-page'),
			'pagetype-options' => $pagetype_options->getHtml(),
			'language-options' => Language::getLanguageOptionList($hyphaContentLanguage, ''),
			'pagename-value' => $pagename,
			'submit-disabled' => $pagename ? 'disabled="disabled"' : '',
			'content-language-js' => htmlspecialchars(json_encode($hyphaContentLanguage)),
		];
?>
		popup = <?= json_encode(hypha_substitute($popup, $vars)) ?>;

		document.getElementById('popup').innerHTML = popup;
		document.getElementById('popup').style.display = 'block';
		document.getElementById('newPagename').focus();
	}
</script>
<?php
		$html->writeScript(ob_get_clean());
	}

	/*
		Function: createPageInstance
		Creates a page object (appropriate subclass of
		HyphaDatatypePage, e.g. textpage) instance from the
		given page node.

		Parameters:
		RequestContext $O_O
		HyphaDomElement $node - The page node from $hyphaXml
	*/
	function createPageInstance(RequestContext $O_O, HyphaDomElement $node) {
		$type = $node->getAttribute('type');
		return new $type($node, $O_O);
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
		global $hyphaHtml, $hyphaPage, $hyphaUrl;

		$request = $O_O->getRequest();
		$args = $request->getArgs();

		if (!$request->isSystemPage()) {
			// fetch the requested page
			$_name = $request->getPageName();
			if ($_name === null) {
				$_name = hypha_getDefaultPage();
			}
			$_node = hypha_getPage($O_O->getContentLanguage(), $_name);

			$isPrivate = $_node && in_array($_node->getAttribute('private'), ['true', '1', 'on']);
			if ($_node && (!$isPrivate || isUser())) {
				$hyphaPage = createPageInstance($O_O, $_node);

				// add tag list to all non-system pages
				$hyphaHtml->writeToElement('tagList', hypha_indexTags($_node, $hyphaPage->language));

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
				$hyphaPage = new indexpage($O_O);
				break;
			case HyphaRequest::HYPHA_SYSTEM_PAGE_TAG_INDEX:
				$hyphaPage = new tagindexpage($O_O);
				break;
			case HyphaRequest::HYPHA_SYSTEM_PAGE_HELP:
				$subject = isset($args[0]) ? urldecode($args[0]) : 'undefined';
				$helpLanguage = isset($args[1]) ? $args[1] : $O_O->getInterfaceLanguage();
				echo hypha_searchHelp($O_O, $subject, $helpLanguage);
				exit;
			case HyphaRequest::HYPHA_SYSTEM_PAGE_SETTINGS:
				$hyphaPage = new settingspage($O_O);
				break;
			case HyphaRequest::HYPHA_SYSTEM_PAGE_UPLOAD:
				if ($args[0]=='image') {
					$return = null;
					if(!$_FILES['uploadedfile']) $response = __('too-big-file', ['upload-max-filesize' => ini_get('upload_max_filesize')]);
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
					if ($return === null)
						$return = [['error' => $response]];
					echo json_encode($return);
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
		global $O_O, $hyphaXml;

		$private = $O_O->getRequest()->getPostValue('newPagePrivate') !== null;
		$type = $O_O->getRequest()->getPostValue('newPageType');
		$language = $O_O->getRequest()->getPostValue('newPageLanguage', $O_O->getContentLanguage());

		$newName = validatePagename($newName);
		if (isUser()) {
			$hyphaXml->lockAndReload();
			$error = hypha_addPage($type, $language, $newName, $private);
			$hyphaXml->saveAndUnlock();
			if ($error) {
				notify('error', $error);
				return 'reload';
			}

			return ['redirect', $O_O->getRequest()->getRootUrl() . $language . '/' . $newName . '/edit'];
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
		$name = preg_replace('/\s+/', ' ', $name); // Collapse multiple spaces
		$name = preg_replace('/^\s|\s$|[^\s\d\w\-_]/i', '', $name); //remove spaces from start and remove everythin g but alphanumeric _ - and space
		return preg_replace('/\s/', '_', $name); // replace spaces with underscores
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

	/* Like dewikify, but accepts a HTML string instead of a document */
	function dewikify_html($html) {
		$doc = new DomWrap\Document();
		// Dewikify a separate element instead of the entire
		// document, since loading html into a document adds
		// <html><body>.
		$elem = $doc->createElement('root');
		$elem->html($html);
		dewikify($elem);

		// TODO: This should probably not use getInnerHtml,
		// probably just return the element instead.
		return getInnerHtml($elem);
	}

	/*
		Function: wikify
		Convert links containing page ids back to working links,
		adding some extra attributes to the HTML node.

		Parameters:
		doc - A DomWrap\Element to process
	*/
	function dewikify($element) {
		foreach ($element->findXPath(".//a[@href]") as $node) {
			dewikify_link($node);
		}
	}

	function dewikify_link($node) {
		global $hyphaXml;
		global $hyphaContentLanguage;
		global $hyphaPage;
		$isoLangList = Language::getIsoList();

		$href = $node->getAttribute('href');
		// This matches a url of the form hypha:123abc/subpath#anchor
		// and splits that into the page id (123abc), followed
		// by an optional subpath and/or anchor. The page id
		// will be replaced by the real url, the rest will be
		// kept as-is.
		//
		// Note that pipes are used to enclose the regex rather
		// than slashes, so we can use slashes without escaping
		// them.
		if (!preg_match('|^hypha:([^/#]+)(.*)$|', $href, $matches))
			return;
		list($all, $id, $extra) = $matches;

		$page = hypha_getPageById($id);
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
		if ($node->html() == '')
			$node->text(showPagename($language->getAttribute('name')));

		// Check permissions, replace by a span with just the
		// pagename if the link would lead to an inaccessible
		// page
		if(!isUser() && $page->getAttribute('private') == 'on') {
			$span = $node->document()->createElement('span');
			$span->html($node->html());
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
		$url .= $extra;
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
		foreach ($elem->findXPath(".//*[@href] | .//*[@src]") as $node) {
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
		global $hyphaXml;
		$isoLangList = Language::getIsoList();

		$href = $node->getAttribute('href');
		// This parses a query string of the form en/pagename/subpath#anchor
		// and splits that into the language and pagename,
		// followed by an optional subpath and/or anchor. The
		// language and pagename will be replaced by the page
		// id, the rest will be kept as-is.
		//
		// Note that pipes are used to enclose the regex rather
		// than slashes, so we can use slashes without escaping
		// them.
		if (!preg_match('|^([^/#]+)/([^/#]+)(.*)$|', $href, $matches))
			return;
		list($all, $language, $pagename, $extra) = $matches;

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

		$uri = 'hypha:' . $page->getAttribute('id') . $extra;
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
	function versionSelector($page, $xml) {
		if ($xml->hasVersions()) {
			$_action = makeAction($page->language.'/'.$page->pagename, '', 'version');
			$html = __('version').': '.'<select class="version" name="version" onchange="'.$_action.'">';

			$history = array();
			foreach($xml->getElementById($page->language)->getElementsByTagName('version') as $v) {
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

	function hypha_searchHelp(RequestContext $O_O, $subject, $lang = 'en') {
		$options = [$lang, $O_O->getInterfaceLanguage(), $O_O->getContentLanguage()];
		foreach ($options as $lang) {
			$dict = Language::getDictionaryByLanguage($lang);
			if (null !== $dict && array_key_exists($subject, $dict)) {
				return nl2br($dict[$subject]);
			}
		}
		return 'Subject: "' . htmlspecialchars($subject) . '" not found';
	}

	function hypha_findPages($filters) {
		/** @var HyphaDomElement $hyphaXml */
		global $hyphaXml;

		$pageFilters = '';

		$pageTypes = array_key_exists('page_types', $filters) ? $filters['page_types'] : null;
		if ($pageTypes) {
			$pageTypes = $filters['page_types'];
			$filterFunc = function($type) { return "@type=" . xpath_encode($type); };
			$attrFilters = array_map($filterFunc, $pageTypes);
			$pageFilters .= "[" . implode(" or ", $attrFilters) . "]";
		}

		$includePrivate = array_key_exists('include_private', $filters) ? $filters['include_private'] : false;
		if ($includePrivate !== true) {
			$pageFilters .= "[@private='off']";
		}

		// TODO: skip and limit might not be useful
		// without sorting, but xpath 1.0 does not seem
		// to support sorting.
		$positionFilters = '';
		$skip = array_key_exists('skip', $filters) ? $filters['skip'] : 0;
		$limit = array_key_exists('limit', $filters) ? $filters['limit'] : 0;
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

		$tagFilters = '';
		$tags = array_key_exists('tags', $filters) ? $filters['tags'] : null;
		if ($tags) {
			$filterFunc = function($tag) { return "@id=" . xpath_encode($tag->getId()); };
			$attrFilters = array_map($filterFunc, $tags);
			$tagFilters .= "[child::tag[" . implode(" or ", $attrFilters) . "]]";
		}
		$excludeTags = array_key_exists('exclude_tags', $filters) ? $filters['exclude_tags'] : null;
		if ($excludeTags) {
			$filterFunc = function($tag) { return "@id=" . xpath_encode($tag->getId()); };
			$attrFilters = array_map($filterFunc, $excludeTags);
			$tagFilters .= "[not(child::tag[" . implode(" or ", $attrFilters) . "])]";
		}

		$langFilters = '';
		$languages = array_key_exists('languages', $filters) ? $filters['languages'] : null;
		if ($languages) {
			$filterFunc = function($lang) { return "@id=" . xpath_encode($lang); };
			$attrFilters = array_map($filterFunc, $languages);
			$langFilters = '[child::language[' . implode(' or ', $attrFilters) . ']]';
		}

		$xpath = "hypha/pageList/page${pageFilters}{$tagFilters}${langFilters}${positionFilters}";
		$pages = $hyphaXml->findXPath($xpath);

		return $pages;
	}
