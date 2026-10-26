<?php
	/*
		Section: Tracing and audit

		Writes one JSON line per event, so logs can be searched and checked automatically.
		Every line carries a stable message code, the request id and the request path, so a
		user who reports an incident id can be matched to the log, and a script can count or
		alert on codes.

		Two outputs:
		- hypha_trace() writes to the PHP error log (in a container: stderr).
		- hypha_audit() does the same and also appends the line to data/hypha.audit, for
		  events that change who can do what (logins, rights, deletions, mailings). That file
		  is the audit trail; it is append-only and not served by the web server.

		Personal data is kept to a minimum: user ids rather than names, and the client
		address only as a short hash. Decide on a retention period for data/hypha.audit.

		Message codes (keep this list complete; a code is never reused):
		- HYP-1001 info     login succeeded
		- HYP-1002 warning  login failed
		- HYP-1003 info     logout
		- HYP-2001 notice   admin rights granted
		- HYP-2002 notice   admin rights revoked
		- HYP-2003 notice   user removed (made ex-member)
		- HYP-2004 notice   user reactivated
		- HYP-3001 notice   page deleted
		- HYP-4001 notice   mailing sending started
		- HYP-9001 error    uncaught error; the user sees the request id as incident id
	*/

	/*
		Function: hypha_requestId
		A random id for the current request, the same for every call during the request.
	*/
	function hypha_requestId() {
		static $id = null;
		if ($id === null)
			$id = bin2hex(random_bytes(6));
		return $id;
	}

	/*
		Function: hypha_trace
		Write one structured line to the PHP error log.

		Parameters:
		$code - message code from the list above, e.g. 'HYP-1001'
		$level - debug, info, notice, warning or error
		$message - short English description
		$context - extra key/value pairs (no passwords, no free text from users)
	*/
	function hypha_trace($code, $level, $message, $context = []) {
		$line = hypha_traceLine($code, $level, $message, $context);
		error_log('hypha ' . $line);
		return $line;
	}

	/*
		Function: hypha_audit
		Like hypha_trace(), and also append the line to the audit trail data/hypha.audit.
	*/
	function hypha_audit($code, $level, $message, $context = []) {
		$line = hypha_trace($code, $level, $message, $context);
		@file_put_contents('data/hypha.audit', $line . "\n", FILE_APPEND | LOCK_EX);
	}

	function hypha_traceLine($code, $level, $message, $context) {
		global $O_O;
		$entry = [
			'time' => date('c'),
			'code' => $code,
			'level' => $level,
			'message' => $message,
			'request_id' => hypha_requestId(),
			'path' => isset($_SERVER['REQUEST_URI']) ? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : null,
			'method' => isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : null,
			'client' => isset($_SERVER['REMOTE_ADDR']) ? substr(hash('sha256', $_SERVER['REMOTE_ADDR']), 0, 12) : null,
		];
		// The acting user, when known. RequestContext may not exist yet very early in a request.
		if (isset($O_O) && $O_O instanceof RequestContext && $O_O->isUser())
			$entry['user_id'] = $O_O->getUser()->getAttribute('id');
		return json_encode($entry + $context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	}

	/*
		Function: hypha_handleUncaught
		Last resort for errors nobody caught: log them with HYP-9001 and show a plain page with
		the request id as incident id, instead of a blank page or a stack trace.
	*/
	function hypha_handleUncaught(Throwable $e) {
		hypha_trace('HYP-9001', 'error', 'Uncaught ' . get_class($e), [
			'error' => $e->getMessage(),
			'file' => $e->getFile(),
			'line' => $e->getLine(),
		]);
		if (!headers_sent()) {
			http_response_code(500);
			header('Content-Type: text/html; charset=utf-8');
		}
		echo '<!DOCTYPE html><html><body><p>Sorry, something went wrong. Incident id: '
			. htmlspecialchars(hypha_requestId()) . '</p></body></html>';
	}
	set_exception_handler('hypha_handleUncaught');
