# Image Upload Plugin

## About
This plugin replaces the Insert Image Dialog with one which has an Upload feature. The form will submit (using an ajax-style iFrame) to your server-side script which will return some JSON with file's details, which are then put into the form.

Note that this plugin does not have good error handling if your file upload does not go to planned. If you rename *jquery.iframe-post-form.js* to something else it'll fail to be included and you'll see your scripts output in the window.

## Installing
Before you follow the details below, you'll need to follow the generic plugin installation details: [https://github.com/wymeditor/wymeditor/wiki/Plugins](../../../../index.php.com/wymeditor/wymeditor/wiki/Plugins)

In addition to enabling the plugin, you will need to add a *dialogImageUploadUrl* option to your wymeditor() call, the value of this is where the file upload will be send.

```
#!JavaScript
$('.editors').wymeditor({
	dialogImageUploadUrl:	'/data/image_upload.php',  // URL to where the image should be uploaded.
	postInit: function(wym) {
		wym.image_upload(); // This is the line you added after following the generic plugin installation details above.
	}
}});
```

This file should process the upload and return a JSON string like this:

```
#!JavaScript
{
	original_filename:	'',
	downloadUrl:		'',
	thumbUrl:			'',
}
```

* **original_filename** The original filename, this will be put in as the "alt" text
* **downloadUrl** The URL to the original file to be downloaded. This is not used by image_upload, but by site_links
* **thumbUrl** The URL to be used, it's called a Thumb URL because you may have used $_POST['thumbnailSize'] to resize it. This is used by site_links but not image_upload

see image_upload.php.sample for some psudocode in PHP.

## Adding Strings to Languge File

You will want to add the following strings to your language file ( the default is *wymeditor/lang/en.js ). These strings cover both this plugin and the image_upload one.

    Upload:           'Upload',
    Uploading:        'Uploading',
	Size:             'Size',
	Actual:           'Actual',
	Small:            'Small',
	Medium:           'Medium',
	Large:            'Large',
    File:             'File',