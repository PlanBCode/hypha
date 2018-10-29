/*jslint evil: true */
/**
    WYMeditor.site_links
    ====================

    A plugin to add a dropdown of links to the Links dialog, especially for
	making it easier to link to your own site (or any other predefined set).
	You can also specify an dialogLinkUploadURL to allow your users to
	upload files which they can then link to for download.
	
	This plugin will attempt to inject a dropdown box into the dialogLinkHtml
	which will pre-fill the existing text box with a URL.
	
	You can specify the source of the links in two days:
	 - dialogLinkSiteLinks option when you create WYMEditor, pass it an array like so:
	      .wymeditor({
			dialogLinkSiteLinks: [
				{item: 'Page Name', url: '/some-page'},
				{item: 'Page Name', url: '/some-page'},
			]
		  });
	alternativly
	 - dialogLinkSiteUrl option when you create WYMeditor is a URL which returns JSON in
		the same format as above.
	
	Todo:
		- Make this more flexible (currently it overwrites dialogLinkHTml with a default)

	
	by Patabugen ( patabugen.co.uk )
*/

WYMeditor.editor.prototype.site_links = function() {
    var wym = this;
	var links = wym._options.dialogLinkSiteLinks;
	var linksUrl = wym._options.dialogLinkSiteUrl;
	var uploadUrl = wym._options.dialogLinkUploadUrl;
	
	if (linksUrl != undefined) {
		jQuery.ajax({
			url: linksUrl,
			dataType: 'json',
			async: false,
			success: function(data, status, xhr) {
				links = data;
			},
			error: function(xhr, status, error) {
				WYMeditor.console.warn(
					"There was a problem getting your site_links from the URL'" + linksUrl + "' the error was: " + error
				);
			}
		});
	}
	// Check the options
	if (links == undefined) {
		WYMeditor.console.warn(
			"You should define the WYMeditor option dialogLinkSiteLinks for the site_links."
		);
		// With no links it's best to do nothing than show an empty dropdown
		return;
	}
	if (links.length == 0) {
		WYMeditor.console.warn(
			"WYMeditor option dialogLinkSiteLinks contains no links for the site_links plugin. Not showing select."
		);
		// With no links it's best to do nothing than show an empty dropdown
		return;
	}
	
	// Build the new Select
	var select = '<select class="wym_href_site_links"><option val=""> - </option>';
	$.each(links, function(index, item) {
		select += '<option value="' + item.url + '">' + item.name + '</option>';
	});
	select += '</select>';

	// See if we want an upload form too
	var uploadHTML = '';
	if (uploadUrl != undefined) {
		// Write some JS to Ajaxify the form and put the response where we want it.

		uploadHTML = String() +
			// We have to put this in a new form, so we don't break the old one
			'<form id="link_upload_form" method="post" enctype="multipart/form-data" action="' + uploadUrl + '">' +
				'<fieldset>' +
					'<legend>{Upload} {File}</legend>' +
					'<div class="row">' +
						'<label>{Upload}</label>' +
						'<input type="file" name="uploadedfile" />' +
					'</div>' +
					'<div class="row row-indent">' +
						// We use a hidden value here so we can get a proper translation
						'<input type="hidden" id="link_upload_uploading_label" value="{Uploading}" />' +
						'<input type="submit" class="submit" ' +
							'value="{Upload}" />' +
					'</div>' +
				 '</fieldset>' +
			'</form>';
	}

	var d = WYMeditor.DIALOGS['CreateLink'];
	var orig = d.initialize;
	d.initialize = function(wDialog) {
		orig.call(this, wDialog);

		jQuery(function(){
			jQuery("select.wym_href_site_links").live("change", function(){
				jQuery(".wym_href").val(jQuery(this).val());
			});
		});
		var oldSubmitLabel = jQuery("form#link_upload_form .submit").val();
		// WYMEditor automatically locks onto any form here, so remove the binding.
		jQuery("form#link_upload_form").unbind("submit");
		jQuery("form#link_upload_form").iframePostForm({
			iframeID: "link_upload_iframe",
			json: "true",
			post: function(response){
				jQuery("form#link_upload_form .submit").val(jQuery("#link_upload_uploading_label").val() + "...");
			},
			complete: function(response){
				response = response[0];
				jQuery(".wym_href").val(response.downloadUrl);
				jQuery(".wym_title").val(response.original_filename);
				jQuery("form#link_upload_form .submit").val(oldSubmitLabel);
			}
		});
	};

	// Put together the whole dialog script
	wym._options.dialogLinkHtml = String() +
		'<body class="wym_dialog wym_dialog_link" ' +
				' onload="WYMeditor.INIT_DIALOG(' + WYMeditor.INDEX + ')">' +
			uploadHTML +
			'<form class="wym_dialog_submit">' +
				'<fieldset>' +
					'<input type="hidden" class="wym_dialog_type" ' +
						'value="' + WYMeditor.DIALOG_LINK + '" />' +
					'<div class="row">' +
						'<label>{Preset}</label>' +
						select + 
					'</div>' +
					'<legend>{Link}</legend>' +
					'<div class="row">' +
						'<label>{URL}</label>' +
						'<input type="text" class="wym_href" value="" ' +
							'size="40" autofocus="autofocus" />' +
					'</div>' +
					'<div class="row">' +
						'<label>{Title}</label>' +
						'<input type="text" class="wym_title" value="" ' +
							'size="40" />' +
					'</div>' +
					'<div class="row">' +
						'<label>{Relationship}</label>' +
						'<input type="text" class="wym_rel" value="" ' +
							'size="40" />' +
					'</div>' +
					'<div class="row row-indent">' +
						'<input class="wym_submit" type="submit" ' +
							'value="{Submit}" />' +
						'<input class="wym_cancel" type="button" ' +
							'value="{Cancel}" />' +
					'</div>' +
				'</fieldset>' +
			'</form>' +
		'</body>';
};
