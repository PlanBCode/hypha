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

	/*
	 * Some helper methods for accessing DOM children that are
	 * shared between the HyphaDomElement, HtmlDocument, Xml and HTMLForm
	 * classes.
	 */
	trait HyphaDomAccessors {
		/*
		        Function: get

			Gets the child with the the given tag name
			(non-recursively). If it does not exist, null is
			returned.

			If multiple nodes with the given tag name exist,
			the first one is returned.
		 */
		public function get($tag) {
			return $this->thisElement()->findXPath($tag)->item(0);
		}

		/**
		 * Applies an XPath query to the current node and
		 * returns the results.
		 */
		public function findXPath($query) : DOMNodeList {
			$elem = $this->thisElement();
			$xpath = new \DOMXPath($elem->ownerDocument);
			$res = $xpath->query($query, $elem);
			if ($res === false)
				throw new InvalidArgumentException("XPath query is invalid: $query");
			return $res;
		}

		/**
		 * Applies an XPath query to the current node and
		 * returns the single result.
		 *
		 * If the query returns zero results, null is returned.
		 * If the query returns more than one result, it throws
		 * an UnexpectedValueException.
		 */
		public function findOneXPath($query) : HyphaDomElement | null {
			$res = $this->findXPath($query);

			if ($res->length > 1)
				throw new UnexpectedValueException("XPath query returned more than one result: $query");
			return $res->item(0);
		}

		// Implementing classes must have this return the
		// element accessors should operate on.
		abstract protected function thisElement() : HyphaDomElement;
	}


	/*
	 * Shared superclass for Hypha's XML and HTMLDocument class.
	 */
	abstract class HyphaDomDocument extends DOMDocument {
		use HyphaDomAccessors;

		const XML_HTML_UNKNOWN_TAG = 801;

		function __construct() {
			parent::__construct('1.0', 'UTF-8');

			$this->preserveWhiteSpace = false;
			$this->formatOutput = true;

			$this->registerNodeClass('DOMDocument', get_class($this));
			$this->registerNodeClass('DOMElement', 'HyphaDomElement');
		}

		/* For HyphaDomAccessors */
		protected function thisElement() : HyphaDomElement {
			return $this->documentElement;
		}

		/*
			Function: createFromHtml

			Parses the given HTML string and returns the parsed
			nodes, imported into this document, as an array of
			detached nodes.

			$action is a string or callback that describes the
			action being performed for the log in case of parse errors.
		*/
		public function createFromHtml(string $html, string|callable $action = "") {
			$class = get_class($this);
			$newdoc = new $class();
			$source = '<?xml encoding="utf-8"?' . '>' . $html;
			if (!$newdoc->loadStringHandleErrors($source, $action))
				throw new InvalidArgumentException("Failed to parse HTML while {$action()}: $html");
			$body = $newdoc->documentElement->childNodes->item(0);
			if ($body->tagName != 'body')
				throw new InvalidArgumentException("First child of parsed HTML is not body, but {$body->tagName}");
			// Detach the children from the imported body before
			// returning them. On PHP 8.2 the libxml nodes of a
			// detached subtree are freed together with the PHP
			// object of its root, so returning the childNodes of
			// the temporary body leaves dangling node objects
			// ("Couldn't fetch HyphaDomElement").
			$imported = $this->importNode($body, true);
			$nodes = [];
			while ($imported->firstChild)
				$nodes[] = $imported->removeChild($imported->firstChild);
			return $nodes;
		}

		/**
			Function: loadStringHandleErrors

			Loads HTML or XML (depending on subclass) from the
			given string. Collects any errors during the loading
			and logs them with additional detail.

			$action is a string or callback that describes the
			action being performed for the log in case of parse errors.

			Returns true when the load was (somewhat) successful,
			false when nothing was loaded.
		*/
		protected function loadStringHandleErrors(string $source, string|callable $action) : bool {
			$previousSetting = libxml_use_internal_errors(true);
			libxml_clear_errors();

			$this->loadString($source, 0);
			$errors = libxml_get_errors();
			if (count($errors) > 0) {
				$lines = explode("\n", $source);
				foreach ($errors as $error) {
					// Ignore warnings for unknown tags (e.g. macro or HTML5 tags), log the rest
					if ($error->code !== self::XML_HTML_UNKNOWN_TAG) {
						$trimmed = trim($error->message);
						$action_string = is_string($action) ? $action : $action();
						$line = $lines[$error->line - 1];
						$map = [LIBXML_ERR_WARNING => "warning", LIBXML_ERR_ERROR => "error", LIBXML_ERR_FATAL => "fatal error"];
						$level = $map[$error->level] ?? "unknown error level";
						// Note that the column number is (documented to be) usually incorrect or zero, but log it just in case
						$message = "While $action_string: libxml $level at line {$error->line} and column {$error->column}: $trimmed. Problematic line: $line";
						trigger_error($message, E_USER_WARNING);
					}
				}
				libxml_clear_errors();
			}
			libxml_use_internal_errors($previousSetting);
			/*
			Return value is not influenced by the presence of
			errors, since the errors are typically minor and still
			produce a working DOM, so instead only fail if nothing
			was produced.
			*/
			return $this->documentElement !== null;
		}


		// Implementing classes should call loadXML or loadHTML as appropriate
		abstract protected function loadString(string $source, int $options);
	}

	class Xml extends HyphaDomDocument {
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
			parent::__construct();

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

			if ($contents) {
				if (!$this->loadStringHandleErrors($contents, "loading Xml from $filename"))
					throw new InvalidArgumentException("Failed to parse Xml from $filename");
			}
		}

		protected function loadString(string $source, int $options) {
			$this->loadXml($source, $options);
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

		Extension of the DOMElement class. This adds a few extra helper
		methods.
	*/
	class HyphaDomElement extends DOMElement {
		use HyphaDomAccessors;

		/* For HyphaDomAccessors */
		protected function thisElement() : HyphaDomElement {
			return $this;
		}

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
		function generateId($prefix = 'id') {
			do {
				$id = $prefix . uniqid();
			} while ($this->ownerDocument->getElementById($id));
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
				$result = $this->createChild($tag);
			}
			return $result;
		}

		/**
		 * Checks whether this node has any text content
		 * (counting only whitespace or only html tags as no
		 * content).
		 * @return bool
		 */
		function hasTextContent() {
			return trim($this->extractText()) !== "";
		}

		/**
		 * Checks whether this node has any content (either
		 * text, or commonly used replaced elements like iframe
		 * or img).
		 * @return bool
		 */
		function hasContent() {
			$xpath = "(//img | //iframe | //video | //embed)[1]";
			return $this->hasTextContent() || $this->findXPath($xpath)->length > 0;
		}

		/**
		 * Converts the given string to a DOM text node and
		 * appends the result to the current node.
		 */
		function appendText(string $text) {
			$this->appendChild(new DOMText($text));
		}

		/* Parse the given string as HTML and append the result
		 * to the current node.
		 */
		function appendHtml(string $html) {
			if ($html) {
				// This is a callback to not waste time in the common non-error case
				$action = function() {
					$id = $this->getId();
					$class = $this->getAttribute('class');
					return 'adding HTML to ' . $this->tagName . ($id ? '#' . $id : '') . ($class ? '.' . str_replace(' ', '.', $class) : '');
				};
				$this->appendChildren($this->ownerDocument->createFromHtml($html, $action));
			}
		}

		/*
		 * Append the given iterable of DOMNodes to the current
		 * node.
		 */
		function appendChildren(iterable $nodes) {
			// Convert to array, since we might be passed
			// a DOMNodeList, which is dynamic and behaves
			// unexpectedly when nodes are moved out of it
			// via appendChild.
			if (!is_array($nodes))
				$nodes = iterator_to_array($nodes);

			foreach ($nodes as $node)
				$this->appendChild($node);
		}

		/*

		 * Append the given iterable of DOMNodes to the current node,
		 * importing (copying) the nodes into the current document.
		 */
		function appendImportedChildren(iterable $nodes) {
			foreach ($nodes as $node)
				$this->appendChild($this->ownerDocument->importNode($node, true));
		}

		/*
		 * Creates a node with the given tag name, appends it to
		 * the current node and returns it.
		 */
		function createChild(string $tagname) {
			$node = $this->ownerDocument->createElement($tagname);
			$this->appendChild($node);
			return $node;
		}

		/**
		 * Return a HTML string for the children of this element,
		 * excluding the element itself.
		 */
		function getInnerHtml() {
			$html = '';
			foreach ($this->childNodes as $child)
				$html .= $this->ownerDocument->saveXML($child);
			return $html;
		}

		/**
		 * Return a HTML string for this element, including all of its descendents.
		 */
		function getOuterHtml() {
			return $this->ownerDocument->saveXML($this);
		}

		/**
		 * Replace the contents of this element with the given HTML.
		 */
		function setInnerHtml(string $html) {
			while ($this->childNodes->length > 0)
				$this->removeChild($this->childNodes->item(0));
			$this->appendHtml($html);
		}

		/**
		 * Return the text content of the current element. Raises an
		 * exception of there is no-text content present.
		 */
		function getText() {
			$text = '';
			foreach ($this->childNodes as $child) {
				if (!($child instanceof DOMText))
					throw new TypeError("getText called on node with non-text children: " . $this->getOuterHtml());
				$text .= $child->wholeText;
			}
			return $text;
		}

		/**
		 * Return any text content inside the current element, ignoring
		 * any markup (any text inside subelements is returned).
		 */
		function extractText() {
			$text = '';
			foreach ($this->childNodes as $child) {
				$text .= $child->textContent;
			}
			return $text;
		}

		/**
		 * Replace the contents of this element with the given text (as
		 * a text node, so HTML in the given text is not interpreted).
		 */
		function setText($text) {
			while ($this->childNodes->length > 0)
				$this->removeChild($this->childNodes->item(0));
			$this->appendChild($this->ownerDocument->createTextNode($text));
		}

		/**
		 * Add a class to the "class" attribute if not already present, observing usual space-separation semantics.
		 */
		function addClass($class) {
			$current = $this->getAttribute('class');
			if (!in_array($class, explode(' ', $current)))
				$this->setAttribute('class', $current . ' ' . $class);
		}

		/**
		 * Remove a class to the "class" attribute if present, observing usual space-separation semantics.
		 */
		function removeClass($class) {
			$classes = explode(' ', $this->getAttribute('class'));
			$updated = array_filter($classes, function($c) use ($class) { return $c != $class; });
			$this->setAttribute('class', implode(' ', $updated));
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
			foreach($node->findXPath('language') as $langNode) if ($langNode->getAttribute('xml:id') == $language) return $langNode;
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
			foreach($langNode->findXPath('version') as $v) $versionList[$v->getAttribute('xml:id')] = $v;
			if ($versionList) {
				krsort($versionList);
				$content = getInnerHtml(reset($versionList));
				if ($version) {
					if ($version<0) for ($i=$version;$i<0;$i++) $content = patch($content, next($versionList)->getText());
					else while(key($versionList)>$version) $content = patch($content, next($versionList)->getText());
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
				$currentVersionNode->setText($diff);
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
			foreach($node->findXPath('version') as $v) $versionList[$v->getAttribute('xml:id')] = $v;
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
			foreach($node->findXPath('version') as $v) $versionList[$v->getAttribute('xml:id')] = $v;
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
		foreach($node->findXPath('version') as $v) {
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
	function serveFile($filename, $root, $cors_allow = false) {
		while (ob_get_level() > 0) {
			ob_end_clean();
		}
		$real = realpath($filename);
		$root = realpath($root);
		if ($real === false || $root && !startsWith($real, $root . DIRECTORY_SEPARATOR)) {
			http_response_code(404);
			die("Invalid filename: $filename");
		}
		// TODO: Use X-Sendfile header to let Apache serve the file?
		$filesize = filesize($filename);
		$headers = getallheaders();
		$matches = [];
		// Only supports fully-qualified bytes=start-end ranges,
		// not `bytes=-suffixlength` or `bytes=start-` or
		// multi-part ranges.
		if (array_key_exists('Range', $headers) && preg_match('/bytes=(\d+)-(\d+)$/', $headers['Range'], $matches)) {
			// https://developer.mozilla.org/en-US/docs/Web/HTTP/Range_requests
			// https://stackoverflow.com/questions/9629223/audio-duration-returns-infinity-on-safari-when-mp3-is-served-from-php
			$start = intval($matches[1]);
			$end = intval($matches[2]);
		} else {
			$start = 0;
			$end = $filesize;
		}
		$bytesize = $end - $start;
		header('Content-Type: ' . getMimeType($filename));
		header('Content-Length: ' . (string)($bytesize));
		header('Content-Range: bytes ' . $start . '-' . $end. '/' . $filesize);
		header('Accept-Ranges: bytes');
		if ($cors_allow) {
			header("Access-Control-Allow-Origin: *");
			header("Access-Control-Allowed-Methods: GET");
		}

		// Allow caching of the response (overriding default PHP
		// headers that disallow all caching).
		// TODO: If user-based authorization is ever added to
		// this function, this should be changed from public to
		// private.
		// TODO: Support ETag or If-Modified-Since to allow
		// cheap validation of cached contents?
		header('Pragma: public');
		header('Cache-Control: public, max-age=86400');

		if ($start === 0 && $end === $filesize) {
			readfile($filename);
		} else {
			http_response_code(206); // Partial content
			$handle = fopen($filename, "r");
			fseek($handle, $start);
			$left = $end - $start;
			$BLOCK_SIZE = 16*1024;
			while (!feof($handle) && ($left > 0) && (connection_status() == CONNECTION_NORMAL)) {
				print(fread($handle, min($BLOCK_SIZE, $left)));
				$left -= $BLOCK_SIZE;
			}
			fclose($handle);
		}
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
			Function: getFilename

			Returns the filename that this object represents.
		 */
		function getFilename() {
			return $this->filename;
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
