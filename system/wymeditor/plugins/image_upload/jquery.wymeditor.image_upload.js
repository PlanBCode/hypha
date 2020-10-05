/*jslint evil: true */
/**
    WYMeditor.image_upload
    ====================
	
    A plugin to add an upload field to the image selector.
	
	
	Todo:
		- 
	
	by Patabugen ( patabugen.co.uk )
*/
WYMeditor.editor.prototype.image_upload = function() {
    var wym = this;
	var uploadUrl = wym._options.dialogImageUploadUrl;
	// Check the options
	if (uploadUrl == undefined) {
		WYMeditor.console.warn(
			"You should define the WYMeditor option dialogImageUploadUrl for the image_upload."
		);
		// With no upload URL we cannot upload files
		return;
	}

	var d = WYMeditor.DIALOGS['InsertImage'];
	var orig = d.initialize;
	d.initialize = function(wDialog) {
		orig.call(this, wDialog);
		var wym = this,
			doc = wDialog.document,
			options = wym._options;

		var oldSubmitLabel = jQuery("form#image_upload_form .submit", doc).val();
		// WYMEditor automatically locks onto any form here, so remove the binding.
		jQuery("form#image_upload_form", doc).unbind("submit");
		jQuery("form#image_upload_form", doc).iframePostForm({
			iframeID: "image_upload_iframe",
			json: "true",
			post: function(response){
				jQuery("form#image_upload_form .submit", doc).val(jQuery("#image_upload_uploading_label", doc).val() + "...");
			},
			complete: function(response){
				response = response[0];
				if (response.error){
					alert(response.error);
				} else {
					jQuery(options.srcSelector, doc).val(response.thumbUrl);
					jQuery(options.altSelector, doc).val(response.original_filename);
				}
				jQuery("form#image_upload_form .submit", doc).val(oldSubmitLabel);
			}
		})
	};

	// Put together the whole dialog script
	wym._options.dialogImageHtml = String() +
        '<body class="wym_dialog wym_dialog_image">' +
            // We have to put this in a new form, so we don't break the old one
            '<form id="image_upload_form" method="post" enctype="multipart/form-data" action="' + uploadUrl + '">' +
                '<fieldset>' +
                    '<legend>{Upload} {Image}</legend>' +
                    '<div class="row">' +
                        '<label>{Upload}</label>' +
                        '<input type="file" name="uploadedfile" />' +
                    '</div>' +
                    '<div class="row">' +
                        '<label>{Size}</label>' +
                        '<select id="image_upload_size" name="thumbnailSize">' +
                            '<option value="actual">{Actual}</option>' +
                            '<option value="small">{Small}</option>' +
                            '<option value="medium">{Medium}</option>' +
                            '<option value="large">{Large}</option>' +
                    '</div>' +
                    '<div class="row row-indent">' +
                        // We use a hidden value here so we can get a proper translation
                        '<input type="hidden" id="image_upload_uploading_label" value="{Uploading}" />' +
                        '<input type="submit" class="submit" ' + 'value="{Upload}" />' +
                    '</div>' +
                 '</fieldset>' +
            '</form>' +
            '<form class="wym_dialog_submit">' +
                '<fieldset>' +
                    '<input type="hidden" class="wym_dialog_type" ' + 'value="' + WYMeditor.DIALOG_IMAGE + '" />' +
                    '<legend>{Image}</legend>' +
                    '<div class="row">' +
                        '<label>{URL}</label>' +
                        '<input type="text" class="wym_src" value="" ' + 'size="40" autofocus="autofocus" />' +
                    '</div>' +
                    '<div class="row">' +
                        '<label>{Alternative_Text}</label>' +
                        '<input type="text" class="wym_alt" value="" size="40" />' +
                    '</div>' +
                    '<div class="row">' +
                        '<label>{Title}</label>' +
                        '<input type="text" class="wym_title" value="" size="40" />' +
                    '</div>' +
                    '<div class="row row-indent">' +
                        '<input class="wym_submit" type="submit" ' + 'value="{Submit}" />' +
                        '<input class="wym_cancel" type="button" ' + 'value="{Cancel}" />' +
                    '</div>' +
                '</fieldset>' +
            '</form>' +
        '</body>';
};
