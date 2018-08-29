<?php
/*
	Class: helpPage
	handles hypha help for actions

	See Also:
	<Page>
*/
	class helpPage extends Page {
		function __construct($args) {
			parent::__construct('', $args);
			//hypha/helpuser
			//registerCommandCallback('getButtonInfo', Array($this, 'infobutton'));
			//registerCommandCallback('helppagename', Array($this, 'showhelpPageName'));
			//registerCommandCallback('helpmenu', Array($this, 'generalmenuhelp'));
			//echo 'construct ';
        }

		function build() {
//			global $isoLangList, $html;
//			if ($view=='register') $html->pagename =  'registration';
//			elseif ($this->hypha->login) $html->pagename = 'settings';
//			else return notify('error', __('login-to-view'));
			//echo ' build ';
			//echo ' 0='. $this->getArg(0);
			//echo ' 1='. $this->getArg(1);

			switch ($this->getArg(0)) {
				//help/user/<username>
				case 'userhelp':
					$this->showhelpuser($this->getArg(1)); break; // get info about user identiefied by name in arg(1)
				//help/pagenamehelp/<pagetype>
				case 'pagenamehelp':
					$this->pagenamehelp($this->getArg(1)); break; //get info about a specific pagetype
				case 'menuhelp':
					$this->showhelpMenu($this->getArg(1)); break;
				case 'helpuser':
					$this->showhelpuser($this->getArg(1)); break;
				case 'button':
					$this->infobutton($this->getArg(1)); break;
				default:
				       $this->showhelpindex($this->getArg(1));break;
			}
		}

		function showhelpUser($userName) {
		global $uiLangList;
		global $isoLangList;
		//echo 'showHelpUser';
		//$this->html->writeToElement('status', 'informatie over deze user'.$userName);
				ob_start();
?>
<div class="help">
<a href="javascript:hypha('help','helpuser','');">helpuser test</a>
Hier meer uitleg over de help
tzt vervangen door get-help user uit een xml taal bestand<br>
</div>
<?php
	$this->html->writeToElement('main', ob_get_clean());
	 }

	 function userhelp() {
	global $uiLangList;
	global $isoLangList;
	//echo ' generaluserhelp';
	$this->html->writeToElement('main', 'Informatie over de users in Hypha:');
	ob_start();
?>
<div class="help">
pagasna naam Hier meer uitleg over de help
tzt vervangen door get-help user uit een xml taal bestand<br>
</div>
<?php
			$this->html->writeToElement('main', ob_get_clean());
	 }

	 function infobutton($button) {
	 // test function for ajax
	 // returns dummy message to test ajax call
	 //echo ' infobutton';
	 //$this->html->writeToElement('main','<span> info for button'. $button. '</span>' );
	 return 'helpPage button'. $button;
	}

	function pagenamehelp($pagetype) {
	global $uiLangList;
	global $isoLangList;
	//echo 'pagenamehelp';
	$this->html->writeToElement('main', 'Informatie over een bepaald datatype Pagina type: '.$pagetype);
	ob_start();
?>
<div class="help">
pagasna naam Hier meer uitleg over de help
tzt vervangen door get-help user uit een xml taal bestand<br>
</div>
<?php
			$this->html->writeToElement('main', ob_get_clean());
 }

	 function showhelpPageName() {
	 global $uiLangList;
	 global $isoLangList;
	 //echo 'generalpagenamehelp';
	 $this->html->writeToElement('main', 'Hulp bij het maken van een pagina naam');
	 ob_start();
?>
<div class="help">
pagasna naam Hier meer uitleg over de help
tzt vervangen door get-help user uit een xml taal bestand<br>
</div>
<?php
		 $this->html->writeToElement('main', ob_get_clean());
}

	function showhelpMenu($menuItem) {
	global $uiLangList;
	global $isoLangList;
	//echo 'showHelpmenu';
	$this->html->writeToElement('main', 'Hulp bij het over een menu item '.$menuItem);
	ob_start();
?>
<div class="help">
Hoe maak ik een menu
Hier meer uitleg over de help
tzt vervangen door get-help user uit een xml taal bestand<br>
</div>
<?php
		$this->html->writeToElement('main', ob_get_clean());
}

function generalmenuhelp() {
global $uiLangList;
global $isoLangList;
//echo 'generalhelpmenu';
		$this->html->writeToElement('main', 'Hulp bij het aanmaken van een menu');
		ob_start();
?>
<div class="help">
Hoe maak ik een menu
Hier meer uitleg over de help
tzt vervangen door get-help user uit een xml taal bestand<br>
</div>
<?php
		$this->html->writeToElement('main', ob_get_clean());
}


function showhelpindex($language){
	global $uiLangList;
	global $isoLangList;
	//echo 'functie showHelpindex language='.$language;
  $this->html->writeToElement('main',hypha_helpindex($language));
}
}
