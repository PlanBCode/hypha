<?php
	/*
		Title: Various

		Various convenience functions.
	*/

	/*
		Function: getInnerHtml
		Returns inner HTML content from an XML node as string.

		Parameters:
		$node - XML node
	*/
	function getInnerHtml($node) {
		$node->ownerDocument->formatOutput = false;
		$content = '';
		if ($node->childNodes) foreach($node->childNodes as $child) $content.= $node->ownerDocument->saveXML($child);
		$node->ownerDocument->formatOutput = true;

		$doc = new DOMDocument();
		$doc->appendChild($text = $doc->createElement('text'));
		$text->appendChild($doc->createTextNode($content));
		return htmlspecialchars_decode($doc->saveXML($text->childNodes->Item(0)), ENT_NOQUOTES);
	}

	/*
		Function: setInnerHtml

		Writes inner HTML content to XML node.
		Suppress warnings from html parser.

		Parameters:
		$node - target XML node
		$content - strict XHTML content
	*/
	function setInnerHtml($node, $content) {
		while ($node->childNodes->length) $node->removeChild($node->childNodes->item(0));
		libxml_use_internal_errors(true);
		$nodeXml = new DOMDocument('1.0', 'UTF-8');
		if(!$nodeXml->loadHTML('<?xml encoding="UTF-8"><html><body><div xml:id="hyphaImport">'.preg_replace('/\r/i', '', $content).'</div></body></html>')) return __('error-loading-html');
		libxml_clear_errors();
		libxml_use_internal_errors(false);
		foreach($nodeXml->getElementById('hyphaImport')->childNodes as $child) $node->appendChild($node->ownerDocument->importNode($child, true));
	}

	/*
		Function: addBaseUrl

		Makes all internal links absolute.
		Converts occurences of '<a href="ref">' or '<img src="ref">' into '<a href="baseUrl/ref">' resp '<img src="baseUrl/ref">'

		Parameters:
		$string - text string
//		$node - XML node
	*/
//	function addBaseUrl($node) {
	function addBaseUrl($string) {
		global $hyphaUrl;
//		setInnerHtml($node, preg_replace('/(href|src)="([^:]*?)"/', '$1="'.$hyphaUrl.'$2"', getInnerHtml($node)));
		return preg_replace('/(href|src)="([^:]*?)"/', '$1="'.$hyphaUrl.'$2"', $string);
	}

	/*
		Function: removeBaseUrl

		Makes all internal links relative.
		Converts occurences of '<a href="baseUrl/ref">' or '<img src="baseUrl/ref">' into '<a href="ref">' resp '<img src="ref">'

		Parameters:
		$node - XML node
	*/
	function removeBaseUrl($node) {
		global $hyphaUrl;
		setInnerHtml($node, preg_replace('#(href|src)="'.$hyphaUrl.'([^:"]*)("|(?:(?:%20|\s|\+)[^"]*"))#', '$1="$2$3', getInnerHtml($node)));
	}

	/*
		Function: asterisk
		Returns an 'golden' asterisk when the input is true. This can be used to indicate admin users or private pages.

		Parameters:
		$condition - when true HTML code for an asterisk is returned, false returns an empty string.
	*/
	function asterisk($condition) {
		return $condition ? '<span style="color:#b90;">&#8727</span>' : '';
	}

	/*
		Function: makeButton
		Convenience function to generate HTML button input

		Parameters:
		$label - button label
		$action - javascript action
		$id - optional element id
	*/
	function makeButton($label, $action, $id = '', $class = '') {
		$class = 'button' . ($class ? ' ' . $class : '');
		return '<input type="button" class="'.htmlspecialchars($class).'" '.($id?' id="'.htmlspecialchars($id).'"':'').'value="'.htmlspecialchars($label).'" onclick="'.htmlspecialchars($action).'" />';
	}

	/*
		Function: makeLink
		Convenience function to generate HTML anchor with javascript code as action

		Parameters:
		$label - anchor text
		$action - javascript action
		$class - optional CSS class
	*/
	function makeLink($label, $action, $class = null) {
		$classattr = ($class ? " class=".$class : "");
		return '<a'.$classattr.' href="javascript:'.$action.'">'.$label.'</a>';
	}

	/*
		Function: makeInfoButton
		Generate span with an onClick to toggle an info popup with the given tag

		Parameters:
		$tag - tag for which to get the information.
	*/
	function makeInfoButton($tag) {
		global $O_O;
		$onclick = 'toggleInfoPopup(event, '.json_encode($tag).', ' . json_encode($O_O->getInterfaceLanguage()) . ', this)';
		return '<span onclick="' . htmlspecialchars($onclick) . '" class="hyphaInfoButton"></span>';
	}

	/*
		Function: xpath_encode
		Encodes an XPath string literal from the given string
		value (taking care of encoding any quotes inside the
		value). Returns an xpath expression that is already
		enclosed in quotes.

		This should be used whenever including a string variable in an xpath expression. E.g.

		$node->findXPath('//button[name='.xpath_encode($name).']');

		The name is picked in symmetry with e.g. json_encode.

		Parameters:
		$value - the string value to encode
	*/
	function xpath_encode($value) {
		// Let the CssSelector library (within php-dom-wrapper) do the hard work
		return Symfony\Component\CssSelector\XPath\Translator::getXpathLiteral($value);
	}

	/*
		Function: is_iterable

		Implementation of PHP's builtin is_iterable for PHP < 7.1.
		Taken from https://www.php.net/manual/en/function.is-iterable.php#122574
	*/
	if (!function_exists('is_iterable')) {
	    function is_iterable($obj) {
		return is_array($obj) || (is_object($obj) && ($obj instanceof \Traversable));
	    }
	}

	/**
	 * Convert a unix timestamp (int or string) to a DateTime object
	 * set to the default timezone.
	 *
	 * The timestamp can be optionally prefixed with a "t", which
	 * will be stripped before converting it.
	 *
	 * If an empty timestamp (i.e. any value that is falsy before
	 * t-stripping) is passed, null is returned.
	 *
	 * @param (string | int) $timestamp
	 * @return (DateTime | null)
	 */
	function timestampToDateTime($timestamp) {
		if (!$timestamp)
			return null;

		$timestamp = ltrim($timestamp, "t");
		$datetime = new DateTime("@" . $timestamp);
		/* Datetime created from a unix timestamp is always set
		 * to to UTC (even when explicitly passing a timezone to
		 * the constructor or createFromFormat), so set it
		 * explicitly. */
		$datetime->setTimezone(new DateTimeZone(date_default_timezone_get()));
		return $datetime;
	}
