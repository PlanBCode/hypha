<?php
	/*
		Title: Database

		Hypha uses a flat file XML format to store most data. This chapter describes a number of classes and functions for content storage and retrieval.
	*/

	/*

		Section: Structure

		Most content can be stored in a human readable form as XML documents.

		*Translations* Translations are stored as <language> elements with an ISO639-1 code as id.

		*Authors* Authors of various documents and revisions are stored by their unique login name. Currently hypha allows users to change their login (as long as it stays a unique name). However this will not update all recordings of contribution to documents.

		*Versions* Versions are stored in separate <version> elements with a timestamp id as children of language elements. A patch/diff algorithm is used to compress older versions to save storage space.
	*/

	/*
		Class: Xml

		Extension of the php DOMDocument class
		- A constructor function which defaults to UTF-8 and a <hypha> root element
		- formatted XML with linebreaks and indentation to facilitate reading by humans
		- A property 'filename' which is used as default when saving
	*/
	require_once 'various.php';
	require_once dirname(__FILE__).'/../php-dom-wrapper/All.php';

	class Xml extends DOMWrap\Document {
		public $file;
		const versionsOn = true;
		const versionsOff = false;
		const multiLingualOn = true;
		const multiLingualOff = false;

		/*
			Constructor:
			Initializes the object
		*/
		function __construct($type=null, $multiLingual=false, $versions=false) {
			parent::__construct('1.0', 'UTF-8');
			$this->preserveWhiteSpace = false;
			$this->formatOutput = true;
			// When no type is passed, just create an empty
			// document
			if ($type !== null) {
				$rootElement = $this->createElement('hypha');
				$rootElement->setAttribute('type', $type);
				$rootElement->setAttribute('multiLingual', $multiLingual ? 'on' : 'off');
				$rootElement->setAttribute('versions', $versions ? 'on' : 'off');
				$rootElement->setAttribute('schemaVersion', 1);
				$this->appendChild($rootElement);
			}
			$this->registerNodeClass('DOMElement', 'HyphaDomElement');
		}

		/*
			Function: loadFromFile
			Fills the object with content from a file and
			stores the filename. If the file does not exist,
			or is empty, nothing happens (though the
			filename is still stored, so the file can be
			created through lockAndReload later).

			Parameters:
			$filename - filename with (relative) path
		*/
		function loadFromFile($filename) {
			$this->file = new HyphaFile($filename);
			if (!file_exists($filename))
				return;

			$contents = $this->file->read();

			if ($contents)
				parent::loadXml($contents);
		}

		/*
		        Function: lockAndReload

			Acquires a write-lock on the file and reloads
			the content from the file. This might block
			while waiting for the lock to become available.

			If the file does not exist, it is created as an
			empty file. If the file is empty, the loading is
			skipped.

			After calling this, either unlock(), or
			SaveToFileAndUnlock() should be called to
			unlock.
		*/
		function lockAndReload() {
			$contents = $this->file->lockAndRead();

			if ($contents)
				parent::loadXml($contents);
		}

		/*
		        Function: unlock()

			Release the write lock, without writing any
			changes. Should only be called when the lock is
			actually held.
		*/
		function unlock() {
			$this->file->unlock();
		}

		/*
		        Function: saveAndUnlock

			Save the object to disk, using its filename, and
			then releases the write lock. Should only be
			called when the lock is actually held.
		*/
		function saveAndUnlock() {
			$this->file->writeAndUnlock(parent::saveXml());
		}

		function isMultiLingual() {
			return $this->documentElement->getAttribute('multiLingual') =='on' ? true : false;
		}

		function hasVersions() {
			return $this->documentElement->getAttribute('versions') =='on' ? true : false;
		}

		function requireLock() {
			if (!$this->file->isLocked())
				throw new LogicException('File ' . $this->filename . ' should be locked');
		}
	}

	/*
		Class: HyphaDomElement

		Extension of the DOMWrap\Element class (which again
		extends the PHP DOMElement class). This adds a few extra helper methods.
	*/
	class HyphaDomElement extends DOMWrap\Element {
		private function getIdAttribute() {
			// DomDocument allows marking attributes as id
			// attributes, so getElementById can use them.
			// By default, the xml:id attribute is marked as
			// such (even though the docs imply that there
			// is no default).
			//
			// This marking can be done using
			// setIdAttribute, but that must then be called
			// on *all* elements individually (and there
			// does not seem to be a good point to hook this
			// in, since constructors and methods like
			// DomDocument::createElement are not actually
			// called when parsing XML/HTML).
			//
			// The alternative is to use and validate using
			// a DTD that marks attributes as id, which then
			// applies to the entire document. This happens
			// automatically when using loadHTML, making
			// getElementById working for HTML documents as
			// expcted.
			//
			// Since there is no way to query the used id
			// attribute(s), but we want to be able to
			// manipulate them in a generic way here, we
			// instead look at the dtd
			$doctype = $this->ownerDocument->doctype;
			if ($doctype && $doctype->name == 'html')
				return 'id';
			return 'xml:id';
		}

		/*
		        Function: generateId

			Generate a new id for this element, that is guaranteed to be
			unique within the xml:id attributes in this
			document.
		*/
		function generateId() {
			do {
				$id = 'id' . uniqid();
			} while ($this->document()->getElementById($id));
			$this->setAttribute($this->getIdAttribute(), $id);
		}

		/*
			Function: getId

			Returns the value of the property that is used
			as the id attribute for this element.
		*/
		function getId() {
			return $this->getAttribute($this->getIdAttribute());
		}

		/*
			Function: setId

			Sets the value of the property that is used
			as the id attribute for this element.
		*/
		function setId($id) {
			return $this->setAttribute($this->getIdAttribute(), $id);
		}

		/*
			Function: hasId

			Checks if the property that is used as the id
			attribute for this element is present.
		*/
		function hasId() {
			return $this->hasAttribute($this->getIdAttribute());
		}

		/*
		        Function: getOrCreate

			Gets the child with the the given tag name
			(non-recursively).  If it does not exist, a new
			node is created and returned.

			If multiple nodes with the given tag name exist,
			the first one is returned.
		 */
		function getOrCreate($tag) {
			$result = $this->get($tag);

			if (!$result) {
				$result = $this->document()->createElement($tag);
				$this->append($result);
			}
			return $result;
		}

		/*
		        Function: get

			Gets the child with the the given tag name
			(non-recursively). If it does not exist, null is
			returned.

			If multiple nodes with the given tag name exist,
			the first one is returned.
		 */
		function get($tag) {
			return $this->find($tag, 'child::')->first();
		}
	}

	/*
		Section: Node access

		Various functions to access the various content nodes, versions and languages in a hypha Xml document
	*/

	/*
		Function: getLangNode
		Returns the child node of a wiki node for its content in a given language.

		Parameters:
		$node - the content node
		$language - 2 alpha ISO639-1 language id

		Returns:
		The <language> subnode for the given language. If no child node for the given language exists a new subnode will be created. If the node is not multilingual the node itself will be returned.
	*/
	function getLangNode($node, $language) {
		if ($node->getAttribute('multiLingual')=='on') {
			foreach($node->getElementsByTagName('language') as $langNode) if ($langNode->getAttribute('xml:id') == $language) return $langNode;
			$langNode = $node->ownerDocument->createElement('language', '');
			$langNode->setAttribute('xml:id', $language);
			$node->appendChild($langNode);
			return $langNode;
		}
		else return $node;
	}

	/*
		Function: getWikiContentNode
		Returns content from a versioned multilingual (wiki)node as DOMNode

		Parameters:
		$node - XML content node
		$language - ISO639-1 language id
		$version - version timestamp
	*/
	function getWikiContentNode($node, $language, $version) {
		$dom = new DOMDocument('1.0', 'UTF-8');
		$rootElement = $dom->createElement('hypha');
		setInnerHtml($rootElement, getWikiContent($node, $language, $version));
		$dom->appendChild($rootElement);
		return $rootElement;
	}

	/*
		Function: getWikiContent
		Returns content from a versioned multilingual node as string

		Parameters:
		$node - XML content node
		$language - ISO639-1 language id
		$version - version timestamp
	*/
	function getWikiContent($node, $language, $version) {
		$langNode = getLangNode($node, $language);
		if ($node->getAttribute('versions')=='on') {
			$versionList = array();
			foreach($langNode->getElementsByTagName('version') as $v) $versionList[$v->getAttribute('xml:id')] = $v;
			if ($versionList) {
				krsort($versionList);
				$content = getInnerHtml(reset($versionList));
				if ($version) {
					if ($version<0) for ($i=$version;$i<0;$i++) $content = patch($content, next($versionList)->text());
					else while(key($versionList)>$version) $content = patch($content, next($versionList)->text());
				}
				return $content;
			}
			else return null;
		}
		else return getInnerHtml($langNode);
	}

	/*
		Function: storeWikiContentNode
		Store DOMNode structured content in a versioned multilingual (wiki)node

		Parameters:
		$node - XML content node
		$language - ISO639-1 language id
		$contentNode - DOMNode containing strict XHTML content
		$author - author id
	*/
	function storeWikiContentNode($node, $language, $contentNode, $author) {
		storeWikiContent($node, $language, getinnerHtml($contentNode), $author);
	}

	/*
		Function: storeWikiContent
		Store string structured content in a versioned multilingual (wiki)node

		Parameters:
		$node - XML content node
		$language - ISO639-1 language id
		$content - string containing strict XHTML content
		$author - author id
	*/
	function storeWikiContent($node, $language, $content, $author) {
		$node->ownerDocument->requireLock();
		if (getWikiContent($node, $language, '') === $content)
			return null;
		$langNode = getLangNode($node, $language);
		if ($node->getAttribute('versions')=='on') {
			$currentVersionNode = getCurrentVersionNode($langNode);
			// append new content to version list
			$timeStamp = time();
			if ($currentVersionNode && $currentVersionNode->getAttribute('xml:id') == 't'.$timeStamp)
				$timeStamp++;
			$newNode = $node->ownerDocument->createElement('version', '');
			$newNode->setAttribute('xml:id', 't'.$timeStamp);
			$newNode->setAttribute('author', $author);
			setInnerHtml($newNode, $content);
			$langNode->appendChild($newNode);

			// update current version into patch
			if ($currentVersionNode) {
				$diff = diff(getInnerHtml($newNode), getInnerHtml($currentVersionNode));
				$currentVersionNode->text($diff);
			}
		} else {
			setInnerHtml($langNode, $content);
		}
	}

	/*
		Function: getCurrentVersionNode
		Returns DOM node that contains the most recently written version of a wiki content

		Parameters:
		$node - XML content node
	*/
	function getCurrentVersionNode($node) {
//		if ($node->documentElement->getAttribute('versions')=='on') {
			$versionList = array();
			foreach($node->getElementsByTagName('version') as $v) $versionList[$v->getAttribute('xml:id')] = $v;
			if ($versionList) {
				krsort($versionList);
				return reset($versionList);
			}
			else return null;
//		}
//		else return $node;
	}

	/*
		Function: getVersionBefore
		Returns id of DOM node that contains the last available version before a certain timestamp

		Parameters:
		$node -
		$timestamp -
	*/
	function getVersionBefore($node, $timestamp) {
//		if ($node->documentElement->getAttribute('versions')=='on') {
			$versionList = array();
			foreach($node->getElementsByTagName('version') as $v) $versionList[$v->getAttribute('xml:id')] = $v;
			if ($versionList) {
				krsort($versionList);
				foreach ($versionList as $id => $node) {
					if (ltrim($id, 't') < $timestamp)
						return $id;
				}
			}
			else return null;
//		}
//		else return $node;
	}

	/*
		Function: versionsOptionList
		Returns an HTML-optionlist of available versions within a given language

		Parameters:
		$node - XML content node
		$version - active version (gets selected attribute in the option list)
	*/
/*	function versionsOptionList($node, $version) {
		foreach($node->getElementsByTagName('version') as $v) {
			$timeStamp = $v->getAttribute('xml:id');
			$history[$timeStamp] = date('j-m-y, H:i', ltrim($timeStamp, 't')).', '.$v->getAttribute('author');
		}
		if ($history) {
			krsort($history);
			reset($history);
			$current = key($history);
			foreach($history as $id => $tag)
				if ($id!=$current) $html.='<option value="'.$id.'"'.($id==$version ? ' selected="selected"' : '').'>'.$tag.'</option>';
				else $html.='<option value=""'.(!$version ? ' selected="selected"' : '').'>'.$tag.'</option>';
			return $html;
		}
	}
*/
	/*
		Section: Versioning

		functions for storing, retrieving and comparing versions. Adaptation of Walter Tichy's algorithm
	*/

	/*
		Function: diff
		Creates a string containing instructions to convert one string into another

		Parameters:
		$source -
		$target -
	*/
	function diff($source, $target) {
		$s = explode(' ', $source);
		$t = explode(' ', $target);
		$copy = array();
		$insert = array();
		$diff = '';
		while($t) {
			$pos = 0;
			while(isset($s[$pos])) if ($s[$pos]==$t[0]) {
				$offset = $pos;
				$length = 0;
				while(isset($t[$length]) && ($s[$offset+$length]==$t[$length])) $length++;
				if (!isset($copy['length']) || $length>$copy['length']) $copy = array("offset" => $offset, "length" => $length);
				$pos+=$length;
			}
			else $pos++;
			if ($copy) {
				if (strlen(implode(' ',$insert))) {
					$diff.= '@@+'.implode(' ',$insert);
					$insert = array();
				}
				$diff.= '@@='.$copy['offset'].','.$copy['length'];
				for ($i=0; $i<$copy['length']; $i++) array_shift($t);
				$copy = array();
			}
			else $insert[] = array_shift($t);
		}
		if ($insert) $diff.= '@@+'.implode(' ',$insert);
		return $diff;
	}

	/*
		Function: patch
		Reconstructs original string using the result from above diff function

		Parameters:
		$source -
		$diff -
	*/
	function patch($source, $diff) {
		$s = explode(' ', $source);
		$d = explode('@@', $diff);
		foreach($d as $v) if (isset($v[0])) switch($v[0]) {
			case '+': // insert
				$t[] = substr($v, 1);
				break;
			case '=': // copy
				$block = explode(',', substr($v, 1));
				foreach (array_slice($s, $block[0], $block[1]) as $u) $t[] = $u;
				break;
		}
		return implode(' ', $t);
	}

	/*
		Section: Other
		Various other functions related to file access.

		Function: serveFile
		Sends a file directly to the client.

		Parameters:
		$filename - The filename to serve
		$root - The directory the filename should be in, after
			resolving any .. and symbolic links. Can be
			empty to omit checking.
	*/
	function serveFile($filename, $root) {
		while (ob_get_level() > 0) {
			ob_end_clean();
		}
		$real = realpath($filename);
		$root = realpath($root);
		if ($real === false || $root && !startsWith($real, $root . DIRECTORY_SEPARATOR)) {
			http_response_code(404);
			die("Invalid filename: $filename");
		}
		header('Content-Type: ' . getMimeType($filename));
		readfile($filename);
		exit;
	}

	/*
		Function: startsWith
		Returns whether $string starts with $prefix
	*/
	function startsWith($string, $prefix) {
		return substr($string, 0, strlen($prefix)) == $prefix;
	}

	/*
		Function: getMimetype
		Returns the mimetype for the given path.
	*/
	function getMimeType($path) {
		// This uses finfo for most files, but that looks at the
		// content, not the extension, so it won't get the type
		// right for js and css files.
		switch(pathinfo($path, PATHINFO_EXTENSION)) {
			case 'css': return 'text/css';
			case 'js': return 'application/javascript';
			default: return finfo_file(finfo_open(FILEINFO_MIME_TYPE), $path);
		}
	}

	/**
	 * Helper class that allows reading and writing to a file, while
	 * using appropriate locking. All file I/O should use this
	 * class, rather than e.g. file_get_contents or
	 * file_put_contents.
	 */
	class HyphaFile {
		private $filename;
		// The opened file. If this is not false, the file is
		// opened and has an active exclusive lock.
		private $fileHandle = false;

		/*
			Constructor

			Creates an object representing the given file.
			The file is not opened or locked by the
			constructor.
		*/
		function __construct($filename) {
			$this->filename = $filename;
		}

		/*
			Function: lock

			Open the file and take an exclusive lock. If the
			file does not exist yet, it is created as an
			empty file.
		*/
		function lock() {
			if ($this->fileHandle)
				throw new LogicException('Cannot lock file ' . $this->filename . ', already locked');

			// Clear the last error, to prevent using an
			// older error when something goes wrong without
			// generating a PHP error (e.g. like read/write
			// before PHP 7.4).
			error_clear_last();

			// Limit maximum number of attempts to prevent
			// looping indefinitely.
			$MAX_ATTEMPTS=10;
			for ($attempt = 0; $attempt < $MAX_ATTEMPTS; ++$attempt) {
				$fileHandle = fopen($this->filename, 'c+');
				if ($fileHandle === false)
					throw new RuntimeException('Cannot lock file ' . $this->filename . ', open failed: ' . error_get_last()['message']);

				if (!flock($fileHandle, LOCK_EX)) {
					fclose($fileHandle);
					throw new RuntimeException('Cannot lock file ' . $this->filename . ', lock failed: ' . error_get_last()['message']);
				}

				// If the locked file still exists
				// (non-zero link count), we're done.
				// Otherwise, another process has replaced the
				// file and we still have the
				// old (now replaced) file open, so
				// retry opening and locking the new
				// file. Note: This makes all locking
				// fail when the file is hardlinked
				// elsewhere.
				if (fstat($fileHandle)['nlink'] > 0) {
					$this->fileHandle = $fileHandle;
					return;
				}
				fclose($fileHandle);
			}

			throw new RuntimeException('Cannot lock file ' . $this->filename . ', repeatedly deleted by other processes');
		}


		/*
			Function: read

			Read and return the contents of the file.

			If the file is not open already, it is opened,
			read, and closed. No locking is needed in this
			case, since writes always happen on a temporary
			files and atomically replace the actual file, so
			if we open the actual filename, we will always
			see a consistent file (which could be replaced
			by the time we finish reading, but then we'll
			just have read the older version completely,
			which is ok).

			This method can essentially be used just like
			file_get_contents, but with support for an
			existing lock.
		*/
		function read() {
			if ($this->fileHandle)
				return stream_get_contents($this->fileHandle, /* maxlength */ -1, /* offset */ 0);
			else
				return file_get_contents($this->filename);
		}

		/*
			Function: lockAndRead

			Helper function to call both lock and read. Note
			that this takes an exclusive lock, so it is only
			needed if you intend to change the contents of
			the file. For only reading it, just call read.
		*/
		function lockAndRead() {
			$this->lock();
			return $this->read();
		}

		/*
			Function: write

			Write the given string to the file, replacing any
			current contents.

			The file must be already locked before calling
			this. It is not closed or unlocked after
			writing.
		*/
		function write($content) {
			if (!$this->fileHandle)
				throw new LogicException('Cannot write to file ' . $this->filename . ', not locked');

			// Clear the last error, to prevent using an
			// older error when something goes wrong without
			// generating a PHP error (e.g. like read/write
			// before PHP 7.4).
			error_clear_last();

			// Write data to a tempfile first and then
			// rename the tempfile over the existing file. This:
			//  - Ensures that file reading can be done
			//    without locks, since the actual file will
			//    never be modified.
			//  - Prevents corruption on full disk (since
			//    rename will be atomic, unlike the
			//    truncate-and-write that would happen when
			//    writing to the file directly).
			//
			// This uses a fixed temp filename instead of
			// using tempnam, since the latter might default
			// to a different directory on permission
			// problems, which would break atomicity if it
			// is on another filesystem.
			//
			$tmpfilename = $this->filename . '.tmp_for_writing';

			// The lock should make sure that no two
			// processes try to use the same tmpfile at the
			// same time (we cannot use the 'x' O_EXCL mode
			// to fail if the file already exists, since
			// if we then get interrupted and leave a
			// tmpfile lying around, all further writes to
			// the file are prevented).
			$fileHandle = fopen($tmpfilename, 'w');
			if ($fileHandle === false)
				throw new RuntimeException('Failed to write to file ' . $this->filename . ', open ' . $tmpfilename . ' failed: ' . error_get_last()['message']);

			// Take an exclusive lock on the new file
			// already, so we still have a lock after the
			// rename. Use non-blocking, since we should
			// always be able to lock this file directly
			// (and if not, blocking with the original lock
			// held might cause a deadlock).
			if (!flock($fileHandle, LOCK_EX | LOCK_NB)) {
				fclose($fileHandle);
				unlink($tmpfilename);
				throw new RuntimeException('Failed to write to file ' . $this->filename . ', lock ' . $tmpfilename . ' failed: ' . error_get_last()['message']);
			}

			// Write to the new file
			if (fwrite($fileHandle, $content) !== strlen($content)) {
				fclose($fileHandle);
				unlink($tmpfilename);
				throw new RuntimeException('Failed to write to file ' . $this->filename . ', write to ' . $tmpfilename . ' failed: ' . error_get_last()['message']);
			}

			// Overwrite the original (but only if the write
			// succeeded)
			if (!rename($tmpfilename, $this->filename)) {
				fclose($fileHandle);
				unlink($tmpfilename);
				throw new RuntimeException('Failed to write to file ' . $this->filename . ', rename from ' . $tmpfilename . ' failed: ' . error_get_last()['message']);
			}

			// Close the old file, but only after the
			// rename, since this unlocks the old file and
			// ensures any other writers waiting for this
			// lock can detect the old file (that they now
			// have locked) is deleted and the lock they
			// have gotten is no longer valid.
			fclose($this->fileHandle);

			// And continue using the new file
			$this->fileHandle = $fileHandle;
		}

		/*
			Helper function to call lock, write and unlock.
			This could be used as a drop-in replacement for
			file_put_contents, when the complete file
			contents are to be replaced (as opposed to
			modifying a part of the file, which requires
			reading the contents within the lock as well).
		*/
		function writeWithLock($content) {
			$this->lock();
			$this->writeAndUnlock($content);
		}

		/*
			Function: unlock

			Close and unlock the file.
		*/
		function unlock() {
			if (!$this->fileHandle)
				throw new LogicException('Cannot unlock file ' . $this->filename . ', not locked');
			fclose($this->fileHandle);
			$this->fileHandle = false;
		}

		/*
			Function: writeAndUnlock

			Helper to call both write and unlock.
		*/
		function writeAndUnlock($content) {
			$this->write($content);
			$this->unlock();
		}

		/*
			Function isLocked

			Returns true when the file is open and an
			exclusive lock is held.
		*/
		function isLocked() {
			return !!$this->fileHandle;
		}
	}

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
			if ($fileinfo['size'] > $max_size) return __('file-too-big-must-be-less-than') . $max_size . 'bytes';
			if ($fileinfo['error'] == UPLOAD_ERR_INI_SIZE) return __('file-too-big-must-be-less-than') . ini_get('upload_max_filesize');
			if ($fileinfo['error']) return __('error-uploading-file') . $fileinfo["error"];

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
					return __('image-type-must-be-one-of') . 'jpg, png';
			}
			if ($image === false)
				return __('failed-to-process-image') . error_get_last();
			imagedestroy($image);

			// Generate a filename and create the file using
			// fopen. This ensure that the file is actually
			// created by us, ruling out any race
			// conditions.
			$attempts = 0;
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
