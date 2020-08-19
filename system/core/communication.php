<?php
	include_once('base.php');
	include_once('users.php');
//	include_once('events.php');
	/*
		Title: Communication
		Contains functions for sending email to one, a few or a lot of recipients, as well as the digest system to keep the group of collaborators up to date.
	*/

	/**
	 * Sends email to one or more recipients
	 *
	 * @param string $receivers , comma separated email addresses.
	 * @param string $subject
	 * @param string $message
	 * @param string|null $senderEmail
	 * @param string|null $senderName
	 * @param string|null $style
	 *
	 * @return string
	 */
	function sendMail($receivers, $subject, $message, $senderEmail = null, $senderName = null, $style = null) {
		global $DEBUG;
		if (null == $senderEmail) {
			$senderEmail = hypha_getEmail();
		}
		if (null == $senderName) {
			$senderName = addSlashes(hypha_getTitle());
		}
		$headers = 'MIME-Version: 1.0' . "\r\n";
		$headers .= 'Content-type: text/html; charset=utf-8' . "\r\n";
		$headers .= 'From: ' . $senderName . ' <' . $senderEmail . '>' . "\r\n";
		$headers .= 'Reply-To: ' . $senderEmail . "\r\n";
		$headers .= 'X-Mailer: PHP/' . phpversion() . "\r\n";

		$messageHtml = '<!DOCTYPE HTML>' . "\r\n";
		$messageHtml .= '<html>' . "\r\n";
		$messageHtml .= '	<head>' . "\r\n";
		$messageHtml .= '		<meta http-equiv=Content-Type content="text/html; charset=utf-8"/>' . "\r\n";
		$messageHtml .= '		<title>' . $subject . '</title>' . "\r\n";
		if (null !== $style) {
			$messageHtml .= '		<style>' . "\r\n";
			$messageHtml .= '			' . $style . "\r\n";
			$messageHtml .= '		</style>' . "\r\n";
		}
		$messageHtml .= '	</head>' . "\r\n";
		$messageHtml .= '	<body>' . "\r\n";
		// RFC5322 specifies lines must not be longer than 998
		// characters, so wrap them (a bit conservativelly).
		// Wrapping should be safe for HTML content.
		// https://tools.ietf.org/html/rfc5322#section-2.1.1
		$messageHtml .= wordwrap(addBaseUrl(dewikify_html($message)), 900, "\r\n");
		$messageHtml .= '	</body>' . "\r\n";
		$messageHtml .= '</html>' . "\r\n";

		$error = array();
		foreach (explode(',', $receivers) as $receiver) {
			$validEmail = filter_var($receiver, FILTER_VALIDATE_EMAIL);
			if (!$DEBUG && !$validEmail) {
				$error[] = $receiver;
				continue;
			}
			$success = mail($receiver, $subject, $messageHtml, $headers, '-f '.$senderEmail);
			if (!$success) {
				$error[] = $receiver;
			}
		}

		if ($error) {
			return __('error-sending-message') . ' ' . implode(',', $error);
		}

		return '';
	}

	/*
		Function: obfuscateEmail
		Adds spambot protection for published email addresses.
		This is done by inserting the email addresses in reverse character order, and fixing this client-sided with javascript and a CSS fallback mechanism.

		Parameters:
			$html - HTMLDocument instance
	*/
//	registerPostProcessingFunction('obfuscateEmail');
	function obfuscateEmail($html) {
		// reverse the character order of all email addresses in href attributes
		$body = $html->getElementsByTagName('body')->Item(0);
		setInnerHtml($body, preg_replace('/[\'|\"]mailto:([A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,4})[\'|\"]/ie', "'mailto:'.strrev('$1')", getInnerHtml($body)));

		// replace all email addresses by a span element containing the address in reverse order
		setInnerHtml($body, preg_replace('/\b([A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,4})\b/ie', "'<span class=\"obfuscate\">'.strrev('$1').'</span>'", getInnerHtml($body)));

		// add a javascript function to undo these operations on the client side
		$js = <<<'EOD'
	function unobfuscateEmail(elem) {
		// reverse visible name
		elem.innerHTML = elem.innerHTML.replace(/(<span class="obfuscate">(.*?)<\/span>)/g, function(str, span, email) {return email.split('').reverse().join('')});
		// for some reason within the textarea the tags get parsed with character encoding. Is WYMeditor doing this? Anyway, let's do the previous statement again again
		elem.innerHTML = elem.innerHTML.replace(/(&lt;span class="obfuscate"&gt;(.*?)&lt;\/span&gt;)/g, function(str, span, email) {return email.split('').reverse().join('')});
		// reverse the href mailto attribute
		elem.innerHTML = elem.innerHTML.replace(/([\'|\"]mailto:([A-Za-z0-9\._%\+-@]+?)[\'|\"])/g, function(str, href, email) {return 'mailto:'+email.split('').reverse().join('')});
	}
EOD;
		$html->writeScript($js);

		// invoke the unobfuscateEmail routine only after the document is fully loaded. To avoid jQuery onload when it's not strictly needed we deploy a <script> element at the end of the HTML document
		$html->writeToElement('main', '<script>unobfuscateEmail(document.body);</script>');

		// add CSS fallback in case javascript is not available
		$css = <<<'END'
.obfuscate {
	unicode-bidi: bidi-override;
	direction: rtl;
}
END;
		$html->writeStyle($css);
	}

	/*
		Title: Digest

		On keeping everyone up to date.
	*/
	/*
		Function: writeToDigest
		adds message to digest file

		Parameters:
			$message -
			$type - page type
			$id - id of page. If id is given, a htmlDiff is in inserted in the digest
	*/
	function writeToDigest($message, $type, $id = false) {
		global $hyphaXml;
		hypha_addDigest('<div'.($type=='settings' ? ' style="color:#840;"' : '').'>'.date('j-m-y, H:i ', time()).$message.($id ? ' - <a href="#'.$id.'">view changes</a>' : '').'</div>'."\n");
		if (!hypha_getLastDigestTime()) {
			$hyphaXml->lockAndReload();
			hypha_setLastDigestTime(time());
			$hyphaXml->saveAndUnlock();
		}
	}

	/*
		Function: flushDigest
		compile periodic report, send to group members and empty digest file

		Parameters:
			$message -
			$type - page type
			$hasDiff -
	*/
	function flushDigest() {
		global $hyphaPagelist, $hyphaXml, $O_O;
		$digest = hypha_getAndClearDigest();
		if ($digest) {
			$t = time() - hypha_getLastDigestTime();
			if ($t > 86400) $s = floor($t / 86400).' '.__('days');
			elseif ($t > 3600) $s = floor($t / 3600).' '.__('hours');
			elseif ($t > 60) $s = floor($t / 60).' '.__('minutes');
			$message = '<div style="font-family: sans; font-size: 12pt;">';
			$message.= '<div style="font-size: 14pt;"><b>'.hypha_getTitle().'</b>: '.__('hypha-summary-of-the-last').' '.$s.'</div>';
			$message.= hypha_getStats(hypha_getLastDigestTime()+hypha_getDigestInterval());
			$message.= ' '.__('page-visits');
			$message.= '<hr />';
			$message.= $digest;
			preg_match_all('/\"\#([\w]+)\"/', $message, $pages);
			$idList = array_unique($pages[1]);
			foreach ($idList as $id) {
				$node = hypha_getPageById($id);
				$type = $node->getAttribute('type');
				$page = new $type($node, $O_O);
				$message.= '<a name="'.$id.'"></a>';
				$message.= $page->digest(hypha_getLastDigestTime());
			}
			$hyphaXml->lockAndReload();
			hypha_setLastDigestTime(time());
			$hyphaXml->saveAndUnlock();
			notify('error', sendMail(getUserEmailList(), hypha_getTitle().': '.__('hypha-summary'), $message) );
		}
	}

	/*
		Function: htmlDiff
		generates a 'track changes' style html markup for the differences between to versions of a text. Adaptation of code by Paul Butler.

		Parameters:

			$o - old version
			$n - new version
	*/
	function htmlDiff($o, $n){
		// split versions $o and $n at spaces and html tags (but keep spaces within tags)
		$splito = preg_split('/(<[^>]*>)| /i', $o, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
		$splitn = preg_split('/(<[^>]*>)| /i', $n, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);

		// generate matrix with differences
		$diff = matrixDiff($splito, $splitn);

		// add html layout
		$html = "";
		foreach($diff as $part) {
			if(is_array($part)) {
				if (!empty($part['d'])) {
					foreach($part['d'] as $delPart) {
						if (substr($delPart, 0, 4)=='<img') $html.= '<img class="del"'.substr($delPart, 4);
						else if(preg_match("/<[^>]*>/",$delPart) == 0) $html.= wrap('<del><span>',$delPart,' </span></del>'); // we ignore deleted html tags
					}
					str_replace("</span></del><del><span>","",$html);

				}
				if (!empty($part['i'])) {
					foreach($part['i'] as $insPart) {
						if (substr($insPart, 0, 4)=='<img') $html.= '<img class="ins"'.substr($insPart, 4);
						else if(preg_match("/<[^>]*>/",$insPart) != 0) $html .= $insPart; // we won't wrap html elements in ins element
						else $html.= wrap('<ins>',$insPart,' </ins>');
					}
					str_replace("</ins><ins>","",$html);
				}
			}
			else $html .= $part . ' ';
		}

		return $html;
	}

	/*
		Function: matrixDiff

		Parameters:

			$old - old version
			$new - new version
	*/
	function matrixDiff($old, $new) {
		$maxlen = 0;
		foreach($old as $oindex => $ovalue){
			// array with indexes of the new items that are the same as this old item
			$nkeys = array_keys($new, $ovalue);

			foreach($nkeys as $nindex){
				// is this some kind of counter trick?
				$matrix[$oindex][$nindex] = isset($matrix[$oindex - 1][$nindex - 1]) ? $matrix[$oindex - 1][$nindex - 1] + 1 : 1;
				if($matrix[$oindex][$nindex] > $maxlen) {
					$maxlen = $matrix[$oindex][$nindex];
					$omax = $oindex + 1 - $maxlen;
					$nmax = $nindex + 1 - $maxlen;
				}
			}
		}
		if($maxlen == 0) return array(array('d'=>$old, 'i'=>$new));
		return array_merge(
			matrixDiff(array_slice($old, 0, $omax), array_slice($new, 0, $nmax)),
			array_slice($new, $nmax, $maxlen),
			matrixDiff(array_slice($old, $omax + $maxlen), array_slice($new, $nmax + $maxlen)));
	}

	/*
		Function: wrap

		Parameters:

			$beginWrap -
			$s -
			$endWrap -
	*/
	function wrap($beginWrap, $s, $endWrap) {
		$ret = $beginWrap;
		$stack = array();
		$subPos = 0;
		$tagPos = 0;
		while($tagPos = strpos($s, '<', $tagPos)) {
			$tag = substr($s, $tagPos, strpos($s, '>', $tagPos) - $tagPos+1);
			if (substr($tag, -2, 1)!='/') { // skip closed elements
				if ($tag[1]=='/') { // tag is end tag
					if(substr($tag, 2, -1)==substr(end($stack), 1, strpos(end($stack), ' ') - 1)) array_pop($stack);
					else {
						$ret.= substr($s, $subPos, $tagPos - $subPos).$endWrap.$tag.$beginWrap;
						$subPos = $tagPos + strlen($tag);
					}
				}
				else $stack[$tagPos] = $tag;
			}
			$tagPos+= strlen($tag);
		}
		while (count($stack)) {
			$tag = reset($stack);
			$ret.=substr($s, $subPos, key($stack)-$subPos).$endWrap.$tag.$beginWrap;
			$subPos = key($stack) + strlen($tag);
			array_shift($stack);
		}
		$ret.= substr($s, $subPos).$endWrap;
		return $ret;
	}
