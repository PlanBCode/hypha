<?php
/**
*	This is a sample PHP file for the image_upload and site_links upload WYMEdtior Plugins. It's sample code doesn't work out the box.
*	You get the following parameters:
*		$_POST['thumbnailSize'] - either 'small' ,'medium', 'large' or 'actual'. 
*		$_FILES['uploadedfile'] - The uploaded file.
**/

// Move the uploaded file

// Create a resized version in the cache

$return = array(
	'original_filename'	=> $originalFileName,	// The original filename, this will be put in as the "alt" text
	'downloadUrl'		=> $downloadUrl,		// The URL to the original file to be downloaded. This is not used by image_upload, but by site_links
	'thumbUrl'			=> $thumbUrl,			// The URL to be used, it's called a Thumb URL because you may have used $_POST['thumbnailSize'] to resize it. This is used by site_links but not image_upload
);
echo json_encode($return);