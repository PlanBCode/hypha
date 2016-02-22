<?php
/*
	Class: textpage
	handles html pages

	See Also:
	<Page>
*/
	$hyphaPageTypes[] = 'textpage';

	class textpage extends Page {
		public $xml;

		function __construct($pageListNode, $view) {
			parent::__construct($pageListNode, $view);
			$this->xml = new Xml('textpage', Xml::multiLingualOn, Xml::versionsOn);
			$this->xml->loadFromFile('data/pages/'.$pageListNode->getAttribute('id'));

			registerCommandCallback('textSave', Array($this, 'save'));
			registerCommandCallback('textRevert', Array($this, 'revert'));
		}

		function build() {
			switch ($this->view) {
				case 'edit':
					$this->edit();
					break;
				case 'translate':
					$this->translate();
					break;
				default: $this->show();
			}
		}

		function show() {
			// throw error if private page is requested without client logged in
			if (!isset($_SESSION['hyphaLogin']) && $this->privateFlag) return notify('error', __('login-to-view'));

			// setup page name and language list for the selected page
			$this->html->writeToElement('pagename', showPagename($this->pagename).' '.asterisk($this->privateFlag));
			$this->html->writeToElement('langList', hypha_indexLanguages($this->pageListNode, $this->language));

			// show content, and only allow access to previous revisions for logged in clients
			$this->html->writeToElement('main', getWikiContent($this->xml->documentElement, $this->language, isset($_SESSION['hyphaLogin']) ? $_POST['version'] : ''));

			// setup addition widgets when client is logged in
			if (isset($_SESSION['hyphaLogin'])) {
				// show a drop down list of revisions
				$this->html->writeToElement('versionList', versionSelector($this));

				// if a revision is selected, show a 'revert' commmand button
				if ($_POST['version']) {
					$_action = makeAction($this->language.'/'.$this->pagename, 'textRevert', '');
					$_button = makeButton(__('revert'), $_action);
					$this->html->writeToElement('pageCommands', $_button);
				}
				// if the latest revision is selected, show 'edit' and 'translate' command buttons
				else {
					$_action = makeAction($this->language.'/'.$this->pagename.'/edit', '', 'version');
					$_button = makeButton(__('edit'), $_action);
					$this->html->writeToElement('pageCommands', $_button);

					$_action = makeAction($this->language.'/'.$this->pagename.'/translate', '', 'version');
					$_button = makeButton(__('translate'), $_action);
					$this->html->writeToElement('pageCommands', $_button);
				}
			}
		}

		function edit() {
			// throw error if edit is requested without client logged in
			if (!isset($_SESSION['hyphaLogin'])) return notify('error', __('login-to-edit'));

			// setup page name and language list
			$this->html->writeToElement('pagename', __('editPage').' `'.showPagename($this->pagename).'`'.asterisk($this->privateFlag));
			$this->html->writeToElement('langList', $this->language);

			// show editor and fields to edit pagename and private status
			ob_start();?>
<div class="section" style="padding:5px; margin-bottom:5px; position:relative;">
<b><?=__('title')?></b> <input type="text" name="textPagename" value="<?=showPagename($this->pagename)?>" onblur="validatePagename(this);" onkeyup="validatePagename(this);" />
<input type="checkbox" name="textPrivate"<?php if ($this->privateFlag=='on') echo ' checked="checked"' ?> /><?=__('private-page')?>
</div>
<editor name="textContent"><?=getWikiContent($this->xml->documentElement, $this->language, '')?></editor>
<?php			$this->html->writeToElement('main', ob_get_clean());

			// show 'cancel' button
			$_action = makeAction($this->language.'/'.$this->pagename, '', '');
			$_button = makeButton(__('cancel'), $_action);
			$this->html->writeToElement('pageCommands', $_button);

			// show 'save' button
			$_action = makeAction($this->language.'/'.$this->pagename, 'textSave', '');
			$_button = makeButton(__('save'), $_action);
			$this->html->writeToElement('pageCommands', $_button);
		}

		function translate() {
			// throw error if translation is requested without client logged in
			if (!isset($_SESSION['hyphaLogin'])) return notify('error', __('login-to-edit'));

			$this->html->writeToElement('pagename', __('translate-page').' `'.showPagename($this->pagename).'`'.asterisk($this->privateFlag));
			$this->html->writeToElement('langList', $this->language);

			ob_start();?>
<div class="section" style="padding:5px; margin-bottom:5px;">
<input type="hidden" name="textPrivate" value="<?php echo $this->privateFlag=='on' ? 'on' : 'off'?>" />
<b><?=__('language')?></b> <select name="textLanguage"><?=languageOptionList(null, $this->language)?></select>
&nbsp;<b><?=__('title')?></b> <input type="text" name="textPagename" value="<?=showPagename($this->pagename)?>" onblur="validatePagename(this);" onkeyup="validatePagename(this);" />
</div>
<editor name="textContent"><?=getWikiContent($this->xml->documentElement, $this->language, '')?></editor>
<?php			$this->html->writeToElement('main', ob_get_clean());

			// show 'cancel' button
			$_action = makeAction($this->language.'/'.$this->pagename, '', '');
			$_button = makeButton(__('cancel'), $_action);
			$this->html->writeToElement('pageCommands', $_button);

			// show 'save' button
			$_action = makeAction($this->language.'/'.$this->pagename, 'textSave', '');
			$_button = makeButton(__('save'), $_action, 'saveButton');
			$this->html->writeToElement('pageCommands', $_button);
		}

		function save($arg) {
			global $hyphaUrl, $hyphaUser;
			$pagename = validatePagename($_POST['textPagename']);
			$language = $_POST['textLanguage'] ? $_POST['textLanguage'] : $this->language;
			$private = $_POST['textPrivate'];
			// check if pagename, privateFlag or language (in case of a new translation) have changed
			if ($language!=$this->language || $pagename!=$this->pagename || $private!=$this->privateFlag) {
				hypha_setPage($this->pageListNode, $language, $pagename, ($private=='on' ? 'on' : 'off'));
			}
			// unfortunately wymeditor can't handle relative urls so we'll add the baseUrl before editing and remove it afterwards
			storeWikiContent($this->xml->documentElement, $language, wikify($_POST['textContent']), $hyphaUser->getAttribute('username'));
			unset($_POST['version']);
			writeToDigest($hyphaUser->getAttribute('fullname').__('changed-page').'<a href="'.$this->language.'/'.$this->pagename.'">'.$this->language.'/'.$this->pagename.'</a>', 'page update', $this->pageListNode->getAttribute('id'));
			// check for new page name
			if ($language!=$this->language || $pagename!=$this->pagename) header('Location: '.$hyphaUrl.$language.'/'.$pagename);
			else return 'reload';
		}

		function revert($version) {
			global $hyphaUser;
			storeWikiContent($this->xml->documentElement, $this->language, getWikiContent($this->xml->documentElement, $this->language, $_POST['version']), $hyphaUser->getAttribute('username'));
			writeToDigest($hyphaUser->getAttribute('fullname').__('reverted-page').'<a href="'.$this->language.'/'.$this->pagename.'">'.$this->language.'/'.$this->pagename.'</a>', 'page update', $this->pageListNode->getAttribute('id'));
			unset($_POST['version']);
		}

		function digest($timestamp) {
			// iterate over all available translations of the page
			$langList = $this->xml->getElementsByTagName('language');
			foreach($langList as $lang) if (ltrim(getCurrentVersionNode($lang)->getAttribute('xml:id'), 't') > $timestamp) {
				$language = $lang->getAttribute('xml:id');
				$pagename = $this->pagename;

				$lastVersion = getVersionBefore($lang, $timestamp);
				$message.= '<hr />';
				$message.= '<div style="font-size: 14pt; font-weight:bold;">'.$pagename.' ('.$language.') - '.($lastVersion ? 'update (last version '.date('j-m-y, H:i', ltrim($lastVersion, 't')).')' : 'new '.(count($langList) > 1 ? 'translation' : 'page')).'</div>';
				$node = $this->xml->documentElement;
				$message.= $lastVersion ? htmlDiff(getWikiContent($node, $language, $lastVersion), getWikiContent($node, $language, '')) : getWikiContent($node, $language, '');
			}
			return $message;
		}
	}
?>
