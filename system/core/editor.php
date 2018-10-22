<?php
	include_once('events.php');
	include_once('document.php');
	include_once('language.php');

	/*
		Title: Editor

		This chapter describes how the WYMeditor is integrated in hypha.

		A new <editor> element is introduced which can be used throughout the HTML document. The function loadEditor should be called just before the documente is sent to the client. It takes care of everything that needs to be done to hook this element to WYMeditor.
	*/
	$jquerySource = 'http://code.jquery.com/jquery-1.7.1.min.js';
	$jqueryuiSource = 'https://code.jquery.com/ui/1.11.4/jquery-ui.min.js';

	/*
		Function: loadEditor
		Adds a WYDIWYG editor when needed.

		Parameters:
		$html - instance of <HTMLDocument>
	*/

	registerPostProcessingFunction('loadEditor');
	function loadEditor($html) {
		global $O_O;
		global $jquerySource;
		global $jqueryuiSource;

		$rootUrl = $O_O->getRequest()->getRootUrl();
		$language = $O_O->getContentLanguage();

		$wymeditorSources = [
			$rootUrl.'system/wymeditor/jquery.wymeditor.min.js',
			$rootUrl.'system/wymeditor/plugins/embed/jquery.wymeditor.embed.js',
		];

		// only add the editor code when the document contains an <editor> element
		if ($html->getElementsByTagName('editor')->length) {
			$html->linkScript($jquerySource);
			foreach ($wymeditorSources as $src)
				$html->linkScript($src);
			$html->linkStyle($rootUrl.'system/wymeditor/skins/default/skin.css');

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
				Property: dialogHtml
				Contains generic markup for the dialog popup window
			*/
			dialogHtml: "<!DOCTYPE html PUBLIC '-//W3C//DTD XHTML 1.0 Strict//EN' 'http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd'>\n"
				+ "<html><head>\n"
				+ "<link rel='stylesheet' type='text/css' media='screen' href='<?=$rootUrl?>data/hypha.css' />\n"
				+ "<link rel='stylesheet' type='text/css' href='//code.jquery.com/ui/1.11.4/themes/smoothness/jquery-ui.css' />\n"
				+ "<title>" + WYMeditor.DIALOG_TITLE + "</title>\n"
				+ "<script type='text/javascript' src='<?=$jquerySource?>'><\/script>\n"
				+ "<script type='text/javascript' src='<?=$jqueryuiSource?>'><\/script>\n"
<?php foreach ($wymeditorSources as $src) { ?>
				+ "<script type='text/javascript' src='<?=$src?>'><\/script>\n"
<?php } ?>
				+ "<script type='text/javascript'>\n"
				+ "function uploadResponse(response) {\n"
				+ "	if (response.charAt(0) == '~') alert(response.substring(1));\n"
				+ "	else {\n"
				+ "		window.opener.WYMeditor.INSTANCES[0].insert('<img id=\"hyphaInsertImage\" alt=\"' + jQuery(document.body).find('input[name=wymAlt]').val() + '\" title=\"' + jQuery(document.body).find('input[name=wymTitle]').val() + '\"/>');\n"
				+ "		jQuery(window.opener.WYMeditor.INSTANCES[0]._doc.body).find('#hyphaInsertImage').attr('src', '<?=$rootUrl?>' + response);\n"
				+ "		jQuery(window.opener.WYMeditor.INSTANCES[0]._doc.body).find('#hyphaInsertImage').removeAttr('id');\n"
				+ " }\n"
				+ "	window.close();\n"
				+ "}\n"
				+ "<\/script>\n"
				+ "</head>\n"
				+ WYMeditor.DIALOG_BODY
				+ "</html>\n",
			/*
				Property: dialogLinkHtml
				Contains markup for the add/edit link dialog
			*/
			dialogLinkHtml: "<body class='wym_dialog wym_dialog_link' onload='window.opener.postInitDialog(window.opener.WYMeditor.INSTANCES[0], this)'><form class='wymImageForm'><fieldset>\n"
				+ "<input type='hidden' class='wym_dialog_type' value='Link' />\n"
				+ "<legend><?=__('link')?></legend>\n"

				// radio selector for link type: local page or url?
				+ "<div class='row'><label><?=__('link-type')?></label><input type='radio' class='linkType' id='selectLinkSourceLocal' name='linkType' value='local' checked='checked' /><?=__('local-page')?> <input type='radio' class='linkType' id='selectLinkSourceUrl' name='linkType' value='url' /><?=__('web-page')?></div>\n"

				// form for local page
				+ "<div class='row wymLinkLocal'>&nbsp;</div>\n"
				+ "<div class='row wymLinkLocal'><label><?=__('page-title')?></label><input type='text' class='wymHypharef' id='localLinkName' name='wymHypharef' value='' size='40'/></div>\n"
				+ "<div class='row wymLinkLocal'>&nbsp;</div>\n"

				// form for url
				+ "<div class='row wymLinkUrl' style='display:none;'><label><?=__('url')?></label><input type='text' class='wymUrl' id='urlLinkUrl' name='wymUrl' value='http://' size='40'/></div>\n"
				+ "<div class='row wymLinkUrl' style='display:none;'><label><?=__('title')?></label><input type='text' class='wymTitle' id='urlLinkTitle' name='wymTitle' value='' size='40'/></div>\n"
				+ "<div class='row wymLinkUrl' style='display:none;'><label><?=__('target')?></label><input type='radio' class='wymLinkTarget' id='urlLinkTargetCurrent' name='wymLinkTarget' value='_self' checked='checked' /><?=__('current-tab')?> <input type='radio' class='wymLinkTarget' id='urlLinkTargetNew' name='wymLinkTarget' value='_blank' /><?=__('new-tab')?></div>\n"

				// submit button
				+ "<div class='row row-indent'><input class='wymSubmitLink' type='button' value='<?=__('submit')?>' /><input class='wym_cancel' type='button' value='<?=__('cancel')?>' /></div>\n"
				+ "</fieldset></form></body>\n",
			/*
				Property: dialogImageHtml
				Contains markup for the image dialog
			*/
			dialogImageHtml:  "<body class='wym_dialog wym_dialog_image' onload='window.opener.postInitDialog(window.opener.WYMeditor.INSTANCES[0], this)'><form class='wymImageForm'><fieldset>\n"
				+ "<input type='hidden' class='wym_dialog_type' value='" + WYMeditor.DIALOG_IMAGE + "' />\n"
				+ "<legend><?=__('image')?></legend>\n"
				+ "<div class='row'><label><?=__('image-source')?></label><input type='radio' class='imageSource selectImageSourceFile' name='imageSource' value='file' checked='checked' /><?=__('file')?> <input type='radio' class='imageSource selectImageSourceUrl' name='imageSource' value='url' /><?=__('web')?></div>\n"
				+ "<div class='row wymImageFile'><label><?=__('upload-file')?></label><input type='file' class='wymFile' name='wymFile' value='' size='30' /></div>\n"
				+ "<div class='row wymImageUrl' style='display:none;'><label><?=__('image-url')?></label><input type='text' name='wymUrl' value='http://' size='40'/></div>\n"
				+ "<div class='row'><label><?=__('alternative-text')?></label><input type='text' name='wymAlt' value='' size='40' /></div>\n"
				+ "<div class='row'><label><?=__('title')?></label><input type='text' name='wymTitle' value='' size='40' /></div>\n"
				+ "<div class='row row-indent'><input class='wymSubmitImage' type='button' value='<?=__('submit')?>' /><input class='wym_cancel' type='button' value='<?=__('cancel')?>' /></div>\n"
				+ "<div class='row row-indent feedback'></div>\n"
				+ "</fieldset>\n"
				+ "<iframe id='uploadTarget' name='uploadTarget' src='' style='width:0;height:0;border:0px solid #fff;'></iframe>\n"
				+ "</form></body>\n",

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

				// set focus to the editor
				jQuery(wym._iframe).focus();

				// add event listener to update the editor content before it gets submitted as POST data
				$(document).ready(function() {
					postProcessingList[postProcessingList.length] = function() {
						for (i in WYMeditor.INSTANCES) WYMeditor.INSTANCES[i].update();
					};
				});
			}
		});
	});

	/*
		Function: postInitDialog
		Gets executed when the dialog is ready

		Parameters:
		wym - the WYMeditor instance
		wdw - the dialog's window
	*/
	function postInitDialog(wym, wdw) {
		var body = wdw.document.body;
		var selection = wym._iframe.contentWindow.getSelection();
		var selObj = (selection && $(selection.anchorNode).parent()[0].tagName.toLowerCase()=='a') ? $(selection.anchorNode).parent() : false;

		// if existing link is edited retrieve link parameters
		if (selObj) {
			var href = selObj.attr('href').replace('<?=$rootUrl?>', '');
			var match = /^<?=$language?>\/([^\/]*)$/.exec(href);
			if (match !== null) {
				wdw.$('#selectLinkSourceLocal').attr('checked', 'checked');
				jQuery(body).filter('.wym_dialog_link').find('.wymLinkUrl').css("display", "none");
				jQuery(body).filter('.wym_dialog_link').find('.wymLinkLocal').css("display", "block");
				pagename = match[1];
				wdw.$('#localLinkName').val(pagename);
			}
			else {
				wdw.$('#selectLinkSourceUrl').attr('checked', 'checked');
				jQuery(body).filter('.wym_dialog_link').find('.wymLinkUrl').css("display", "block");
				jQuery(body).filter('.wym_dialog_link').find('.wymLinkLocal').css("display", "none");
				wdw.$('#urlLinkUrl').val(selObj.attr('href'));
				wdw.$('#urlLinkTitle').val(selObj.attr('title'));
				if (selObj.attr('onclick')) wdw.$('#urlLinkTargetNew').attr('checked', 'checked');
				else wdw.$('#urlLinkTargetCurrent').attr('checked', 'checked');
			}
		}
		else wdw.$('#localLinkName').val(selection.toString());

		wdw.$('#localLinkName').autocomplete({source:"<?=$rootUrl?>chooser/<?=$language?>/"});

		// FIXME: add if statement to see if we'll have link dialog...
		jQuery(body).find('input[name=wymHypharef]').focus();

		// add link type selector callback
		jQuery(body).filter('.wym_dialog_link').find('.linkType').change(function() {
			switch($(this).val()) {
				case 'local':
					jQuery(body).filter('.wym_dialog_link').find('.wymLinkUrl').css("display", "none");
					jQuery(body).filter('.wym_dialog_link').find('.wymLinkLocal').css("display", "block");
					jQuery(body).find('input[name=wymHypharef]').focus();
					break;
				case 'url':
					jQuery(body).filter('.wym_dialog_link').find('.wymLinkLocal').css("display", "none");
					jQuery(body).filter('.wym_dialog_link').find('.wymLinkUrl').css("display", "block");
					jQuery(body).find('input[name=wymUrl]').focus();
					break;
			}
		});

		// add link submit callback
		jQuery(body).find('.wymSubmitLink').click(function() {
			switch(jQuery(body).find('input:radio[name=linkType]:checked').val()) {
				case 'local':
					var page = jQuery(body).find('input[name=wymHypharef]').val();
					var href = '<?=$rootUrl?><?=$language?>/' + page.replace(/\s/g, '_');
					if (selObj) selObj.replaceWith('<a href="' + href + '">' + page + '</a>');
					else wym.link({href:href});
					break;
				case 'url':
					var href = jQuery(body).find('input[name=wymUrl]').val();
					if (selObj) selObj.replaceWith('<a id="hyphaInsertLink" title="' + jQuery(body).find('input[name=wymTitle]').val() + '">' + (selObj.html() ? selObj.html() : href) + '</a>');
					else wym.link({href:href, title:jQuery(body).find('input[name=wymTitle]').val(), id:"hyphaInsertLink"});
					if (href.indexOf(':')==-1) href = 'http://' + href;
					jQuery(wym._doc.body).find('#hyphaInsertLink').attr('href', href);
					if (jQuery(body).find('input:radio[name=wymLinkTarget]:checked').val()=='_blank') jQuery(wym._doc.body).find('#hyphaInsertLink').attr('onclick', 'window.open(\'' + href + '\',\'new\',\'\');return false');
					jQuery(wym._doc.body).find('#hyphaInsertLink').removeAttr('id');
			}
			wdw.close();
		});

		// add image source selector callback
		jQuery(body).filter('.wym_dialog_image').find('.imageSource').change(function() {
			switch($(this).val()) {
				case 'file':
					jQuery(body).filter('.wym_dialog_image').find('.wymImageFile').css("display", "block");
					jQuery(body).filter('.wym_dialog_image').find('.wymImageUrl').css("display", "none");
					jQuery(body).find('input[name=wymFile]').focus();
					break;
				case 'url':
					jQuery(body).filter('.wym_dialog_image').find('.wymImageFile').css("display", "none");
					jQuery(body).filter('.wym_dialog_image').find('.wymImageUrl').css("display", "block");
					jQuery(body).find('input[name=wymUrl]').focus();
					break;
			}
		});

		// add image submit callback
		jQuery(body).find('.wymSubmitImage').click(function() {
			switch(jQuery(body).find('input:radio[name=imageSource]:checked').val()) {
				case 'url':
					wym.insert('<img id="hyphaInsertImage" alt="' + jQuery(body).find('input[name=wymAlt]').val() + '" title="' + jQuery(body).find('input[name=wymTitle]').val() + '"/>');
					jQuery(wym._doc.body).find('#hyphaInsertImage').attr('src', jQuery(body).find('input[name=wymUrl]').val());
					jQuery(wym._doc.body).find('#hyphaInsertImage').removeAttr('id');
					wdw.close();
					break;
				case 'file':
					jQuery(body).find('.feedback').html('<?=__('uploading')?>...');
					jQuery(body).find('.wymImageForm').attr('action', '<?=$rootUrl?>upload/image/');
					jQuery(body).find('.wymImageForm').attr('method', 'post');
					jQuery(body).find('.wymImageForm').attr('enctype', 'multipart/form-data');
					jQuery(body).find('.wymImageForm').attr('target', 'uploadTarget');
					jQuery(body).find('.wymImageForm').submit();
					break;
			}
		});

		// add close dialog box callback
		jQuery(body).find('.wym_cancel').click(function() {
			wdw.close();
		});
	}

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
