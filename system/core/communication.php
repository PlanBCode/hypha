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
			$senderName = hypha_getTitle();
		}
		// Properly validating a e-mail address is hard, but at least
		// verify that it contains only printable ASCII and no angle
		// brackets for now, to prevent escaping the email header with
		// embedded newlines.
		if (preg_match("/[^\x20-\x7e]|[<>]/", $senderEmail)) {
			notify('error', __('email-field-invalid') . ': ' . $senderEmail);
		}
		$messageHtml = '<!DOCTYPE HTML>' . "\r\n";
		$messageHtml .= '<html>' . "\r\n";
		$messageHtml .= '	<head>' . "\r\n";
		$messageHtml .= '		<meta http-equiv=Content-Type content="text/html; charset=utf-8"/>' . "\r\n";
		$messageHtml .= '		<title>' . htmlspecialchars($subject) . '</title>' . "\r\n";
		if (null !== $style) {
			$messageHtml .= '		<style>' . "\r\n";
			$messageHtml .= '			' . htmlspecialchars($style). "\r\n";
			$messageHtml .= '		</style>' . "\r\n";
		}
		$messageHtml .= '	</head>' . "\r\n";
		$messageHtml .= '	<body>' . "\r\n";
		$messageHtml .= addBaseUrl(dewikify_html($message));
		$messageHtml .= '	</body>' . "\r\n";
		$messageHtml .= '</html>' . "\r\n";

		if (preg_match("/[^\r\n\t\x20-\x7e]/", $messageHtml)) {
			// Apply quoted_printable to allow non-ASCII content.
			// This also handles wordwrapping to keep line lengths
			// (well) within the RFC5322 max 998 chars.
			$messageEncoded = mailEncodeQuotedPrintable($messageHtml);
			$transferEncoding = 'quoted-printable';
		} else {
			// Just do wordwrapping to keep line lengths short
			// enough (follow the quoted-printable max length
			// instead of the RFC5322 998 bytes). Note that this
			// only cuts at spaces (cut_long_words defaults to
			// false), which prevents cutting up multi-byte utf-8,
			// but can also produce lines longer than 76 chars
			// (which is ok if they are shorter than 998 bytes).
			$messageEncoded = wordwrap($messageHtml, 76, "\r\n");
			$transferEncoding = '7bit';
		}

		$headers = 'MIME-Version: 1.0' . "\r\n";
		$headers .= 'Content-type: text/html; charset=utf-8' . "\r\n";
		$headers .= 'Content-Transfer-Encoding: ' . $transferEncoding . "\r\n";
		$headers .= mailEncodeAddressHeader('From', $senderName, $senderEmail) . "\r\n";
		$headers .= 'Reply-To: ' . $senderEmail . "\r\n";
		$headers .= 'X-Mailer: PHP/' . phpversion() . "\r\n";

		$error = array();

		$mailSubject = mailEncodeUnstructuredHeader('Subject', $subject, /* omit_header */ true);

		foreach (explode(',', $receivers) as $receiver) {
			$validEmail = filter_var($receiver, FILTER_VALIDATE_EMAIL);
			if (!$DEBUG && !$validEmail) {
				$error[] = $receiver;
				continue;
			}
			$success = mail($receiver, $mailSubject, $messageEncoded, $headers, '-f '.$senderEmail);
			if (!$success) {
				$error[] = $receiver;
			}
		}

		if ($error) {
			return __('error-sending-message') . ' ' . implode(',', $error);
		}

		return '';
	}

	/**
	 * Builds an unstructured e-mail header, optionally encoding it
	 * according to the rules of RFC2047 if non-ASCII characters are
	 * contained.
	 *
	 * This corresponds to the RFC5322 unstructured rule used for e.g.
	 * Subject.
	 *
	 * @param string $header  The name of the header. A : and space will be added.
	 * @param string $content The content of the header.
	 * @param string $omit_header Do not add the header, : and space to the
	 *               result, but only take it into account for the allowed line length.
	 *
	 * @return string         The resulting header
	 */
        function mailEncodeUnstructuredHeader($header, $content, $omit_header) {
		if (preg_match("/^[\x20-\x7e]*$/", $content)) {
			if ($omit_header)
				return $content;
			else
				return "$header: $content";
		}

                return mailEncodeRfc2047("$header:", $content, '', '\x21-\x7e', $omit_header);
        }

	/**
	 * Builds an e-mail header containing a name and address, either
	 * quoting or encoding it according to the rules of RFC2047 if
	 * non-valid ASCII or non-ASCII characters are contained.
	 *
	 * This corresponds to the RFC5322 mailbox rule used for e.g. From.
	 *
	 * @param string $header  The name of the header. A : will be added.
	 * @param string $name    The name to be put before the address.
	 * @param string $address The address to put into the header.
	 *
	 * @return string         The resulting header
	 */
        function mailEncodeAddressHeader($header, $name, $address) {
		// XXX: This assumes the address complies with the RFC5322
		// rules. Validating just the rules for allowed characters is
		// quite complicated (especially since there are also rules for
		// quoting the local-part with "" and the domain with []), so
		// just send the message and see what happens...
		//
		// If there are only printable ASCII characters in the name, we
		// can just quote it and be done. This corresponds to the
		// RFC5322 quoted-string rule.
		if (preg_match("/^[\x20-\x7e]*$/", $name)) {
			$escaped = preg_replace('/[\\\\"]/', '\\\\$0', $name);
			return "$header: \"$escaped\" <$address>";
		}
		// Allowed chars in an encoded-word are specified by RFC2047
		// section 5.(3).
                return mailEncodeRfc2047("$header:", $name, "<$address>", 'a-zA-Z0-9!*+\\/=_-', /* $omit_prefix */ false);
        }

	/**
	 * Encodes an email header according to the rules of RFC2047.
	 *
	 * Note that PHP has mb_encode_mimeheader and iconv_mime_encode which
	 * aim to do the same thing, but fail to encode characters not allowed
	 * in a display-name as part of an address header, so this is
	 * implemented here from scratch.
	 *
	 * This does not do any attempt to minimize the encoding (i.e. it does
	 * not try to keep ASCII words or whitespace unencoded, but just
	 * encodes everything) to keep the code somewhat simple.
	 *
	 * @param string $prefix  A prefix to prepend to the first line without
	 *                        encoding (i.e. the header name). A space will
	 *                        be added after this prefix.
	 * @param string $content The main content to be encoded.
	 * @param string $suffix  A suffix to append after the content without
	 *                        encoding (i.e. "<address>"). A space will be
	 *                        added before this suffix.
	 * @param string $allowed The bytes allow in the output string,
	 *                        specified as a preg range (to be inserted
	 *                        into []).
	 * @param string $omit_prefix Do not add the prefix, and space to the
	 *                            result, but only take it into account for
	 *                            the allowed line length.
	 *
	 * @return string         The resulting header
	 */
        function mailEncodeRfc2047($prefix, $content, $suffix, $allowed, $omit_prefix) {
		$start = '=?UTF-8?Q?';
		$end = '?=';
		// Lines containing encoded-words can be max 76 characters
		// (encoded-words can be max 75 chars, but this is now implied
		// since each line will start with either the header name or a
		// single whitespace char).
		$max_line_len = 76;

		$result = $prefix . ' ' . $start;
		$room = $max_line_len - strlen($result);
		if ($omit_prefix)
			$result = $start;

		// This splits on *characters*, since we cannot split a multi-byte char over multiple encoded-words.
		foreach (mb_str_split(mb_scrub($content), 1, 'UTF-8') as $c) {
			// Note that this matches *bytes*, since the output should be 7-bit ASCII
			// This adds ? = and _ to the list of to-be-escaped
			// chars since they are special in Q-encoding and must
			// be escaped even when allowed in the output.
			if (preg_match("/[^$allowed]|[?=_]/", $c))
				$to_append = implode('', array_map(fn ($b) => sprintf('=%02X', ord($b)), str_split($c)));
			else
				$to_append = $c;

			// Ensure we do not exceed the max line length 
			if ($room < strlen($to_append) + strlen($end)) {
				// This whitespace will be removed (\r\n as
				// part of folding in RFC5322 section 3.2.2,
				// the \t between two encoded-words as
				// specified by RFC2047 section 6).
				$result .= $end . "\r\n\t" . $start;
				$room = $max_line_len - 1 /* \t */ - strlen($start);
			}

			$result .= $to_append;
			$room -= strlen($to_append);
		}
		$result .= $end;
		$room -= strlen($end);

		if ($room < 1 /* space */ + strlen($suffix))
			// This space is *not* removed (the crlf is)
			$result .= "\r\n " . $suffix;
		else
			$result .= ' ' . $suffix;

		return $result;
	}

	/**
	 * Encodes a email body as quoted-printable according to the rules of
	 * RFC2045 section 6.7.
	 *
	 * Note that PHP has quoted_printable_encode which does exactly this,
	 * but it is broken for some utf-8 strings
	 * (https://github.com/php/php-src/issues/25284), so this reimplements
	 * it.
	 *
	 * @param string $content The content to be encoded.
	 *
	 * @return string         The resulting header
	 */
        function mailEncodeQuotedPrintable($content) {
		// Quoted-printable lines can be max 76 characters
		$max_line_len = 76;

		$result = '';
		$room = $max_line_len;

		// This splits on *characters*, since we cannot split a multi-byte char over multiple encoded-words.
		foreach (mb_str_split(mb_scrub($content), 1, 'UTF-8') as $c) {
			// Note that this matches *bytes*, since the output should be 7-bit ASCII
			// Match printable ASCII except tab, space and =
			// This does *not* encode any CR and LF, though the
			// spec only specifies CRLF combinations to be passed
			// through unencoded, but well.
			// This *does* include space and tab, which is strictly
			// only required when followed directly by a CRLF, but
			// not encoding other space requires more complex code,
			// so just encode everything.
			if (preg_match("/[^\n\r\x21-\x3c\x3e-\x7e]|=/", $c))
				$to_append = implode('', array_map(fn ($b) => sprintf('=%02X', ord($b)), str_split($c)));
			else
				$to_append = $c;

			if ($c == "\n" || $c == "\r") {
				$room = $max_line_len;
			} else if ($room < strlen($to_append) + 1 /* = */) {
				# This = creates a softwrap, where the CRLF will be removed on decode
				$result .= "=\r\n";
				$room = $max_line_len;
			}

			$result .= $to_append;
			$room -= strlen($to_append);
		}
		return $result;
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
		$body = $html->findOneXPath('body');
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
		// Ensure a we have a last digest time
		hypha_getLastDigestTime();
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
			//
			// Also with a digest interval of a minute or less, so $s is always set.
			//
			else $s = max(1, floor($t / 60)).' '.__('minutes');
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
			$message.= '</div>';
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
