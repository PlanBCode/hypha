# Site Links Plugin

## About
This plugin replaces the Link Dialog with one which has a dropdown list list of links and/or an upload file feature.

The list of links can either be hard-coded in your WYMEditor config, or got by Ajax. The file will be uploaded and the form filled in with the results from the server.

Note that this plugin does not have good error handling if your file upload does not go to planned. If you rename *jquery.iframe-post-form.js* to something else it'll fail to be included and you'll see your scripts output in the window.

## Installing
Before you follow the details below, you'll need to follow the generic plugin installation details: [https://github.com/wymeditor/wymeditor/wiki/Plugins](../../../../index.php.com/wymeditor/wymeditor/wiki/Plugins)

In addition to enabling the plugin, you will need to add a *dialogImageUploadUrl* option to your wymeditor() call, the value of this is where the file upload will be send.

### Hard Coded Link List
Simply specify in your options a list of link objects in the following format:
```
#!JavaScript
$('.editors').wymeditor({
	dialogLinkSiteLinks: [
		{item: 'Page Name', url: '/some-page'},
		{item: 'Page Name', url: '/some-page'},
	]
	});
```

### Ajax-Loaded Links
I would recommend you use this method if your list could be modified after page load. The URL you specify simply returns JSON in the same format as had you used *dialogLinkSiteLinks*. Note that if your list returns nothing, no dropdown will be shown (rather than showing an empty one).

If you specify both dialogLinkSiteUrl and dialogLinkSiteLinks then the latter will be replaced by the ajax (unless there is an error with the Ajax, in which case the former will be used and an error printed to the console).
```
#!JavaScript
$('.editors').wymeditor({
	dialogLinkSiteUrl: '/ajax/get_pages.json'
});
```

### File Uploads
This feature is so you can link to a file from one of your pages. You can use the same upload script as for the image_upload plugin if you wush.
```
#!JavaScript
$('.editors').wymeditor({
	dialogLinkUploadUrl: '/ajax/upload_file'
});
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