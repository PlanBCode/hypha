<?php
	include_once('events.php');
	include_once('document.php');
	include_once('language.php');

	/*
		Title: Bowser

		Integrate browser detection in hypha.
	*/
	/*
		Function: loadEditor
		Adds a WYDIWYG editor when needed.

		Parameters:
		$html - instance of <HTMLDocument>
	*/

	registerPostProcessingFunction('loadBowser');
	function loadBowser($html) {
		global $hyphaUrl;

		$html->linkScript($hyphaUrl . 'system/bowser/bowser.min.js');

		/*
			Section: jQuery wymeditor
			A chunck of javascript is added to construct the WYMeditor and handle its callbacks. Both the editor and its integration into hypha depend on jQuery.
		*/
        ob_start();
?>
	<script>
		if (bowser.name === 'msie' && !bowser.check({msie: "9.0"})) {
			document.addEventListener('DOMContentLoaded', function showBrowserNotSupportedPopup() {
				var el = document.getElementById('popup');
				if (el !== null) {
					html = '<table class="section"><tr><th colspan="2"><?=__('browser')?> <?=__('not-supported')?></td><tr>';
					html+= '<tr><th><?=__('browser')?></th><td>' + bowser.name + '</td></tr>';
					html+= '<tr><th><?=__('version')?></th><td>' + bowser.version + '</td></tr>';
					html+= '<tr><td colspan="2"><?=__('not-supported')?></td></tr>';
					el.innerHTML = html;
					el.style.left = document.getElementById('hyphaCommands').offsetLeft + 'px';
					el.style.top = (document.getElementById('hyphaCommands').offsetTop + 25) + 'px';
					el.style.visibility = 'visible';
				}
			});
		}
	</script>
<?php
		// add hypha tweaks to WYMeditor to the custom javascript section in the HTML head section
		$html->writeScript(ob_get_clean());
	}
