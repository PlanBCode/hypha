<?php
	include_once('events.php');
	include_once('document.php');
	include_once('language.php');

	/*
		Title: Editor

		This chapter describes how the WYMeditor is integrated in hypha.

		A new <editor> element is introduced which can be used throughout the HTML document. The function loadEditor should be called just before the documente is sent to the client. It takes care of everything that needs to be done to hook this element to WYMeditor.
	*/

	/*
		Function: loadEditor
		Adds a WYDIWYG editor when needed.

		Parameters:
		$html - instance of <HTMLDocument>
	*/

	registerPostProcessingFunction('loadEditor');
	function loadEditor($html) {
		global $O_O;
		$rootUrl = $O_O->getRequest()->getRootUrl();
		$language = $O_O->getContentLanguage();

		$wymeditorSources = [
			$rootUrl.'system/wymeditor/jquery.wymeditor.min.js',
			$rootUrl.'system/wymeditor/lang/'.$language.'.js',
			$rootUrl.'system/wymeditor/skins/default/skin.js',
			$rootUrl.'system/wymeditor/plugins/jquery.iframe-post-form.js',
			$rootUrl.'system/wymeditor/plugins/embed/jquery.wymeditor.embed.js',
			$rootUrl.'system/wymeditor/plugins/image_upload/jquery.wymeditor.image_upload.js',
			$rootUrl.'system/wymeditor/plugins/site_links/jquery.wymeditor.site_links.js',
		];

		// only add the editor code when the document contains an <editor> element
		if ($html->getElementsByTagName('editor')->length) {
			foreach ($wymeditorSources as $src)
				$html->linkScript($src);
			$html->linkStyle($rootUrl.'system/wymeditor/skins/default/skin.css');

			// prepare the site links
			$siteLinks = [];
			$pageList = [];
			foreach(hypha_getPageList() as $page) {
				$lang = hypha_pageGetLanguage($page, $language);
				if ($lang && (isUser() || ($page->getAttribute('private')!='on'))) {
					$pageList[] = $lang->getAttribute('name').($page->getAttribute('private')=='on' ? '&#;' : '');
				}
			}
			if ($pageList) {
				array_multisort(array_map('strtolower', $pageList), $pageList);
				foreach($pageList as $pagename) {
					$privatePos = strpos($pagename, '&#;');
					if ($privatePos) $pagename = substr($pagename, 0, $privatePos);
					$siteLinks[] = [
						'name' => showPagename($pagename).' '.asterisk($privatePos),
						'url' => $language . '/' . $pagename,
					];
				}
			}

			/*
				Section: jQuery wymeditor
				A chunck of javascript is added to construct the WYMeditor and handle its callbacks. Both the editor and its integration into hypha depend on jQuery.
			*/
			ob_start();
?>
	<script>
	jQuery(function() {
		jQuery('.wymeditor').wymeditor({
			/*
				Property: iframeBasePath
				Basic WYMeditor markup to start from
			*/
			iframeBasePath: "<?=$rootUrl?>system/wymeditor/iframe/pretty/",
			/*
				Property: boxHtml
				Customizes the XHTML structure of WYMeditor.
				"CONTAINERS" has been moved from "wym_area_right" to "wym_area_top":
			*/
			boxHtml: "<div class='wym_box'>\n"
				+ "<div class='wym_area_top'>" + WYMeditor.TOOLS + WYMeditor.CONTAINERS + "</div>\n"
				+ "<div class='wym_area_main'>" + WYMeditor.HTML + WYMeditor.IFRAME + WYMeditor.STATUS + "</div>\n"
				+ "</div>\n",
			/*
				Custom property: isFocusSet
				Indication to set focus at the first editor
			*/
			isFocusSet: false,
			/*
				Property: lang
				Basic WYMeditor language, used for replacing translation tags
			*/
			lang: '<?=$language;?>',
			/*
				Property: containersItems
				Path to the WYMeditor core
			*/
			containersItems: [
				{'name': 'P', 'title': 'Paragraph', 'css': 'wym_containers_p'},
				// {'name': 'H1', 'title': 'Heading_1', 'css': 'wym_containers_h1'},
				{'name': 'H2', 'title': 'Heading_2', 'css': 'wym_containers_h2'},
				{'name': 'H3', 'title': 'Heading_3', 'css': 'wym_containers_h3'},
				{'name': 'H4', 'title': 'Heading_4', 'css': 'wym_containers_h4'},
				{'name': 'H5', 'title': 'Heading_5', 'css': 'wym_containers_h5'},
				{'name': 'H6', 'title': 'Heading_6', 'css': 'wym_containers_h6'},
				{'name': 'PRE', 'title': 'Preformatted', 'css': 'wym_containers_pre'},
				{'name': 'BLOCKQUOTE', 'title': 'Blockquote', 'css': 'wym_containers_blockquote'},
				{'name': 'TH', 'title': 'Table_Header', 'css': 'wym_containers_th'}
			],
			/*
				Property: dialogImageUploadUrl
				Used in the "image_upload" plugin; URL to script to process the image upload
			*/
			dialogImageUploadUrl: '<?=$rootUrl;?>upload/image',
			/*
				Property: dialogLinkSiteLinks
				Used in the "site_links" plugin; Contains an array with site links; pages and links to images
			*/
			dialogLinkSiteLinks: <?=json_encode($siteLinks);?>,
			/*
				Property: dialogLinkUploadUrl
				Used in the "site_links" plugin; URL to script to process the file upload
			*/
			// dialogLinkUploadUrl: '<?=$rootUrl;?>upload/file',
			/*
				Function: postInit
				Gets called when WYMeditor instance is ready

				Parameters:
				wym - the WYMeditor instance
			*/
			postInit: function(wym) {
				// remove classes box
				jQuery(wym._box).find(wym._options.classesSelector).remove();

				// rename 'Containers' into 'Styles' as most people will recognize them as such
				jQuery(wym._box).find(wym._options.containersSelector).css("width", "94px").css("margin-top", "4px").find(WYMeditor.H2).html("style >");

				// align Containers/Styles dropdown menu next to the tools box
				jQuery(wym._box).find(".wym_area_top .wym_section").css("float", "left").css("margin-right", "5px");

				// adjust the editor's height
				jQuery(wym._box).find(wym._options.iframeSelector).css('height', '250px');

				// set focus to the first editor
				if (!this.isFocusSet) {
					$(wym._iframe).focus();
					this.isFocusSet = true;
				}

				// initiate the "image_upload" plugin
				wym.image_upload();

				// initiate the "site_links" plugin
				wym.site_links();

				// call the update function when the form is submitted
				let $form = $(wym.element).closest('form');
				$form.submit(function(e) {
					wym.update();
				});
			}
		});
	});

	</script>
<?php
			// add hypha tweaks to WYMeditor to the custom javascript section in the HTML head section
			$html->writeScript(ob_get_clean());

			// replace all editor elements with proper wymeditor tagname/class combination
			while($html->getElementsByTagName('editor')->Item(0)) {
				$editorElement = $html->getElementsByTagName('editor')->Item(0);
				$wymeditorElement = $editorElement->ownerDocument->createElement('textarea');
				$parentElement = $editorElement->parentNode;
				$parentElement->insertBefore($wymeditorElement, $editorElement);

				$childNodes = $editorElement->childNodes;
				while ($childNodes->length > 0) $wymeditorElement->appendChild($childNodes->item(0));

				$attributes = $editorElement->attributes;
				while ($attributes->length > 0) $wymeditorElement->setAttributeNode($attributes->item(0));

				$wymeditorElement->setAttribute('class', 'wymeditor');

				$parentElement->removeChild($editorElement);
			}
		}
	}
