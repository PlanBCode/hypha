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
					if ($version<0) for ($i=$version;$i<0;$i++) $content = patch($content, getInnerHtml(next($versionList)));
					else while(key($versionList)>$version) $content = patch($content, getInnerHtml(next($versionList)));
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
			// update current version into patch
			if ($currentVersionNode) setInnerHtml($currentVersionNode, diff($content, getWikiContent($node, $language, '')));
			// append new content to version list
			$timeStamp = time();
			if ($currentVersionNode && $currentVersionNode->getAttribute('xml:id') == 't'.$timeStamp)
				$timeStamp++;
			$newNode = $node->ownerDocument->createElement('version', '');
			$newNode->setAttribute('xml:id', 't'.$timeStamp);
			$newNode->setAttribute('author', $author);
			setInnerHtml($newNode, $content);
			$langNode->appendChild($newNode);
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
					if (ltrim($id, 't') > $timestamp)
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
					unset($insert);
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
		ob_end_clean();
		$real = realpath($filename);
		$root = realpath($root);
		if ($real === false || $root && !startsWith($real, $root . '/')) {
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
		// The opened file. If this is non-null, the file is
		// opened and has an active exclusive lock.
		private $fd = false;

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
			if ($this->fd)
				throw new LogicException('Cannot lock file ' . $this->filename . ', already locked');
			$this->fd = fopen($this->filename, 'c+');
			flock($this->fd, LOCK_EX);
		}


		/*
			Function: read

			Read and return the contents of the file.

			If the file is not opened yet, it is opened and
			a shared lock is taken. After reading the file,
			it is closed and the lock is released.

			If the file was already opened (and thus an
			exclusive lock is held), the file is not closed
			after reading, and the lock remains in effect.

			This method can essentially be used just like
			file_get_contents, but with support for locking.
		*/
		function read() {
			if ($this->fd) {
				$fd = $this->fd;
			} else {
				$fd = fopen($this->filename, 'r');
				flock($fd, LOCK_SH);
			}

			$contents = stream_get_contents($fd);

			if (!$this->fd)
				fclose($fd);

			return $contents;
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

			If the file is not opened yet, it is opened and
			an exclusive lock is taken. After writing, the
			file is closed and the lock is released.

			If the file was already opened (and thus an
			exclusive lock is held), the file is not closed
			after writing, and the lock remains in effect.

			This method can essentially be used just like
			file_put_contents(), but with locking enabled by
			default.
		*/
		function write($content) {
			if (!$this->fd)
				throw new LogicException('Cannot write to file ' . $this->filename . ', not locked');
			ftruncate($this->fd, 0);
			rewind($this->fd);
			fwrite($this->fd, $content);
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
			file_put_contents($this->filename, $content, LOCK_EX);
		}

		/*
			Function: unlock

			Close and unlock the file.
		*/
		function unlock() {
			if (!$this->fd)
				throw new LogicException('Cannot unlock file ' . $this->filename . ', not locked');
			fclose($this->fd);
			$this->fd = false;
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
			return !!$this->fd;
		}
	}

?>
