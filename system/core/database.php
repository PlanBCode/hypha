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

	class Xml extends DOMDocument {
		public $filename;
		const versionsOn = true;
		const versionsOff = false;
		const multiLingualOn = true;
		const multiLingualOff = false;

		/*
			Constructor:
			Initializes the object
		*/
		function __construct($type, $multiLingual=false, $versions=false) {
			parent::__construct('1.0', 'UTF-8');
			$this->preserveWhiteSpace = false;
			$this->formatOutput = true;
			$rootElement = $this->createElement('hypha');
			$rootElement->setAttribute('type', $type);
			$rootElement->setAttribute('multiLingual', $multiLingual ? 'on' : 'off');
			$rootElement->setAttribute('versions', $versions ? 'on' : 'off');
			$this->appendChild($rootElement);
		}
		/*
			Function: loadFromFile
			Fills the object with content from a file and stores the filename

			Parameters:
			$filename - filename with (relative) path
		*/
		function loadFromFile($filename) {
			$this->filename = $filename;
			if (file_exists($this->filename)) parent::load($this->filename);
		}
		/*
			Function: saveToFile
			Save the object to disk, using its filename
		*/
		function saveToFile() {
			if (isset($this->filename)) parent::save($this->filename);
		}

		function isMultiLingual() {
			return $this->documentElement->getAttribute('multiLingual') =='on' ? true : false;
		}

		function hasVersions() {
			return $this->documentElement->getAttribute('versions') =='on' ? true : false;
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
		if (getWikiContent($node, $language, '')===$content) return null;
		$langNode = getLangNode($node, $language);
		if ($node->getAttribute('versions')=='on') {
			$currentVersionNode = getCurrentVersionNode($langNode);
			// update current version into patch
			if ($currentVersionNode) setInnerHtml($currentVersionNode, diff($content, getWikiContent($node, $language, '')));
			// append new content to version list
			$timeStamp = time();
			if ($currentVersionNode && $currentVersionNode->getAttribute('xml:id') == 't'.$timeStamp) $timeStamp++;
			$newNode = $node->ownerDocument->createElement('version', '');
			$newNode->setAttribute('xml:id', 't'.$timeStamp);
			$newNode->setAttribute('author', $author);
			setInnerHtml($newNode, $content);
			$langNode->appendChild($newNode);
		}
		else setInnerHtml($langNode, $content);
		$node->ownerDocument->saveToFile();
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
				reset($versionList);
				while(key($versionList) && ltrim(key($versionList), 't') > $timestamp) next($versionList);
				return $current ? key($current) : null;
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
				unset($copy);
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
		$filename -
	*/
	function serveFile($filename) {
		$fileInfo = apache_lookup_uri($filename);
		ob_end_clean();
		header('Content-type: '.$fileInfo->content_type);
		readfile($filename);
		exit;
	}
?>
