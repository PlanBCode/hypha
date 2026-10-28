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
		return $node->getInnerHtml();
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
		$node->setInnerHtml($content);
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
		return $condition ? '<span style="color:#b90;">&#8727;</span>' : '';
	}

	/*
		Function: appendAsterisk
		Append a 'golden' asterisk to the given DOM element. This can be used to indicate admin users or private pages.

		Parameters:
		$condition - when true (or omitted), the asterisk is added, otherwise nothing happens.
	*/
	function appendAsterisk(HyphaDomElement $parent, bool $condition = true) {
		if ($condition) {
			$span = $parent->createChild('span');
			$span->setAttribute('style', "color:#b90;");
			$span->setText("\u{2217}");
		}
	}

	/*
		Function: makeButton
		Convenience function to generate HTML button input

		Parameters:
		$label - button label
		$action - javascript action
		$id - optional element id
	*/
	/*
		Function: parseAction
		Returns the url, command, argument and form id of an action
		made by makeAction(), or null for any other script.
	*/
	function parseAction($action) {
		if (!preg_match("/^hypha\\('([^']*)', '([^']*)', '([^']*)', document\\.getElementById\\('([^']*)'\\),null\\);$/", $action, $m)) return null;
		return ['url' => $m[1], 'command' => $m[2], 'argument' => $m[3], 'form' => $m[4]];
	}

	function makeButton($label, $action, $id = '', $class = '') {
		global $hyphaHtml;
		// A button made by makeAction() also works without
		// JavaScript: a link when it only opens a page, else a
		// submit button that posts the form with its command and
		// argument; with JavaScript hypha() still runs (#667).
		$parsed = parseAction($action);
		if ($parsed) {
			if ($parsed['command'] === '' && $parsed['argument'] === '') {
				$button = $hyphaHtml->createElement('a');
				$button->setAttribute('href', $parsed['url']);
				$button->setText($label);
			} else {
				$button = $hyphaHtml->createElement('button');
				$button->setAttribute('type', 'submit');
				$button->setAttribute('form', $parsed['form']);
				$button->setAttribute('formaction', $parsed['url']);
				$button->setAttribute('name', 'hyphaCommand[' . $parsed['command'] . ']');
				$button->setAttribute('value', $parsed['argument']);
				$button->setAttribute('onclick', $action . ' return false;');
				$button->setText($label);
			}
			if ($id)
				$button->setAttribute("id", $id);
			if ($class)
				$button->addClass($class);
			$button->addClass('button');
			return $button;
		}
		$button = $hyphaHtml->createElement('input');
		if ($id)
			$button->setAttribute("id", $id);
		if ($class)
			$button->addClass($class);
		$button->addClass('button');
		$button->setAttribute("type", "button");
		$button->setAttribute("value", $label);
		$button->setAttribute("onclick", $action);
		return $button;
	}

	/*
		Function: makeLink
		Convenience function to generate HTML anchor with javascript code as action

		Parameters:
		$label - anchor text
		$action - javascript action
		$class - optional CSS class
	*/
	function makeLink($label, $action, $class = null, $tooltip = null) {
		global $hyphaHtml;
		$link = $hyphaHtml->createElement('a');
		if ($class)
			$link->addClass($class);
		$link->addClass('button');
		// A link that only opens a page needs no JavaScript (#667).
		$parsed = parseAction($action);
		if ($parsed && $parsed['command'] === '' && $parsed['argument'] === '')
			$link->setAttribute('href', $parsed['url']);
		else
			$link->setAttribute('href', "javascript:$action");
		if ($tooltip !== null)
			$link->setAttribute('title', $tooltip);
		$link->appendHtml($label);
		return $link;
	}

	/*
		Function: makeInfoButton
		Generate span with an onClick to toggle an info popup with the given tag

		Parameters:
		$tag - tag for which to get the information.
	*/
	function makeInfoButton($tag) {
		global $O_O;
		global $hyphaHtml;
		$button = $hyphaHtml->createElement('span');
		$button->addClass('hyphaInfoButton');
		$onclick = 'toggleInfoPopup(event, '.json_encode($tag).', ' . json_encode($O_O->getInterfaceLanguage()) . ', this)';
		$button->setAttribute("onclick", $onclick);
		return $button;
	}

	/*
		Function: xpath_encode
		Encodes an XPath string literal from the given string
		value (taking care of encoding any quotes inside the
		value). Returns an xpath expression that is already
		enclosed in quotes.

		This should be used whenever including a string variable in an xpath expression. E.g.

		$node->findXPath('.//button[name='.xpath_encode($name).']');

		The name is picked in symmetry with e.g. json_encode.

		Parameters:
		$value - the string value to encode
	*/
	function xpath_encode($value) {
		// XPath string literals can either be single- or double-quoted
		// and contain anything except for their quotes. To support strings
		// with quotes, you can use the other type of quotes, possibly
		// concatening multiple parts if quotes are mixed.
		// Here, we take the easy route and just always use double
		// quotes (except for any double quotes in the string, which are
		// single-quoted). This could be less verbose for strings containing
		// double quotes but not single quotes, but well.
		$parts = explode('"', $value);
		$quoted = array_map(function($part) { return '"' . $part . '"'; }, $parts);
		if (count($quoted) == 1)
			return $quoted[0];
		return "concat(" . implode("'\"'", $quoted) . ")";
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
