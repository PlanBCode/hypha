<?php
	/**
	 * Helper class for uploaded images. This allows manipulating
	 * images stored in the data/images directory. No metadata is
	 * stored in the XML for these images, they are just referenced
	 * by their id.
	 */
	class HyphaImage {
		private $filename;
		const ROOT_PATH = 'data/images/';
		const ROOT_URL = 'images/';

		/**
		 * Create a HyphaImage for an existing image with the
		 * given filename.
		 *
		 * To import a new image, see importUploadedImage().
		 */
		function __construct($filename) {
			$this->filename = $filename;
		}

		/**
		 * Return the url to this image resized to the given
		 * size.
		 * If the requested size is not available yet, it is
		 * created immediately. If no width and height are
		 * passed, the original filename is returned.
		 */
		function getUrl($width = 0, $height = 0) {
			return self::ROOT_URL . $this->getFilename($width, $height);
		}

		/**
		 * Return the path to this image resized to the given
		 * size. This is the path within the hypha root
		 * directory.
		 * If the requested size is not available yet, it is
		 * created immediately. If no width and height are
		 * passed, the original filename is returned.
		 */
		function getPath($width = 0, $height = 0) {
			return self::ROOT_PATH . $this->getFilename($width, $height);
		}

		/**
		 * Return the filename of this image resized to the
		 * given size.
		 * If the requested size is not available yet, it is
		 * created immediately. If no width and height are
		 * passed, the original filename is returned.
		 */
		function getFilename($width = 0, $height = 0) {
			if ($width == 0 && $height == 0)
				return $this->filename;

			// Create a filename based on the original
			// filename, but including the size and the type
			// of resize performed (only "crop" supported
			// now), and always use the JPG extension.
			$filename = substr_replace($this->filename, '', -4);
			$filename .= '_' . $width . 'x' . $height;
			$filename .= '_crop';
			$filename .= '.jpg';

			if (!file_exists(self::ROOT_PATH . $filename))
				$this->resizeTo($width, $height, self::ROOT_PATH . $filename);

			return $filename;
		}

		/**
		 * Take an uploaded image file, move it into the data
		 * directory and return the corresponding HyphaImage
		 * instance.
		 *
		 * If an error occurs a translated error message is
		 * returned.
		 */
		static function importUploadedImage($fileinfo, $max_size = 4194304 /* 4M */ ) {
			if ($fileinfo['size'] > $max_size) return __('file-too-big-must-be-less-than', ['upload-max-filesize' => $max_size . 'bytes']);

			switch ($fileinfo['error']) {
				case UPLOAD_ERR_INI_SIZE:
					return __('file-too-big-must-be-less-than', ['upload-max-filesize' => ini_get('upload_max_filesize')]);
				case UPLOAD_ERR_FORM_SIZE:
					return __('file-bigger-than-field-max-size');
				case UPLOAD_ERR_PARTIAL:
					return __('file-partially-uploaded');
				case UPLOAD_ERR_OK:
					break;
				default:
					return __('error-uploading-file', ['error' => $fileinfo['error']]);
			}

			// Check it's a valid image file
			$imginfo = getimagesize($fileinfo['tmp_name']);
			switch ($imginfo[2]) {
				case IMAGETYPE_JPEG:
					$extension = "jpg";
					$image = @imagecreatefromjpeg($fileinfo['tmp_name']);
					break;
				case IMAGETYPE_PNG:
					$extension = "png";
					$image = imagecreatefrompng($fileinfo['tmp_name']);
					break;
				default:
					return __('image-type-must-be-one-of', ['allowed-filetypes' => 'jpg, png']);
			}
			if ($image === false)
				return __('failed-to-process-image') . error_get_last();
			imagedestroy($image);

			// Generate a filename and create the file using
			// fopen. This ensure that the file is actually
			// created by us, ruling out any race
			// conditions.
			do {
				$image = new HyphaImage(uniqid() . '.' . $extension);
				$filename = $image->getPath();
				$fileHandle = @fopen($filename, 'x');
				// If fopen failed, but the file does
				// not exist, error out (to prevent
				// looping infinitely)
				if ($fileHandle === false && !file_exists($filename))
					return __('failed-to-process-image') . error_get_last();
			} while ($fileHandle === false);
			fclose($fileHandle);

			move_uploaded_file($fileinfo['tmp_name'], $filename);
			return $image;
		}

		/**
		 * Create a resized version of this image and store it
		 * in the given destination filename.
		 */
		private function resizeTo($width, $height, $destination) {
			if (substr($this->filename, -3) == "png")
				$image = imagecreatefrompng($this->getPath());
			else
				$image = imagecreatefromjpeg($this->getPath());

			$orig_w = imagesx($image);
			$orig_h = imagesy($image);

			// Use the full source width, and scale
			// the height proportionally
			$src_w = $orig_w;
			$src_h = $height / ($width / $src_w);

			// If the resulting height is larger
			// than the source height, use the full
			// height and scale the width instead.
			if ($src_h > $orig_h) {
				$src_h = $orig_h;
				$src_w = $width / ($height / $src_h);
			}

			$result = imagecreatetruecolor($width, $height);
			$src_x = ($orig_w - $src_w) / 2;
			$src_y = ($orig_h - $src_h) / 2;
			imagecopyresampled($result, $image, 0, 0, $src_x, $src_y, $width, $height, $src_w, $src_h);
			imagejpeg($result, $destination, 90);
			imagedestroy($result);
			imagedestroy($image);
		}
	}
