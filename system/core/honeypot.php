<?php
	/*
		Section: Honeypot against scanners

		A client that requests a trap is almost certainly a scanner and is blocked for a short
		while: every request it makes gets 403 until the block expires. There are two kinds of
		trap:

		1. Via a link. A hidden link to "bot-trap" is added to every page. Readers do not see
		   it, assistive technology skips it and search engines do not follow it (nofollow), so
		   only a crawler that reads the raw HTML follows it.
		2. Via URLs. Scanners probe sites for software they know is vulnerable: WordPress
		   logins, exposed .git folders, .env files, Exchange, phpMyAdmin and so on. Hypha has
		   none of these, so a request for such a path is a scanner. The default list is in
		   hypha_honeypotDefaultPatterns(); a site can extend or reduce it in
		   data/hypha.honeypot.paths (see below).

		Blocked clients are kept in data/hypha.honeypot as a JSON map of a hashed client
		address to the time the block expires; the address is never stored in the clear and
		expired entries are pruned on each write. Behind a reverse proxy, REMOTE_ADDR must be
		the real client (e.g. mod_remoteip), otherwise the proxy gets blocked.
	*/

	// Trap patterns are matched against the request path relative to the site root,
	// including the language (e.g. "nl/home"), without leading or trailing slashes and
	// ignoring case:
	// - "*" matches any characters within one path segment;
	// - "**" matches any number of whole segments, including none: "**/wp-login.php" also
	//   matches "wp-login.php", and "owa/**" also matches "owa".
	// Hypha's own paths start with a language, a system page (settings, index, tag, ...),
	// data/ or system/, so the defaults never match a Hypha page.
	//
	// data/hypha.honeypot.paths, optional, one pattern per line:
	// - a pattern adds a trap, e.g. "**/old-cms/**";
	// - "!pattern" removes a default pattern, e.g. "!admin/**" when the site needs it;
	// - lines starting with # are comments.

	// How long a client is blocked after hitting a trap, in seconds.
	define('HYPHA_HONEYPOT_BLOCK_SECONDS', 300);

	// Path of the hidden link added to every page.
	define('HYPHA_HONEYPOT_LINK_PATH', 'bot-trap');

	/*
		Function: hypha_honeypotDefaultPatterns
		Paths that scanners probe and that do not exist in Hypha.
	*/
	function hypha_honeypotDefaultPatterns() {
		return [
			HYPHA_HONEYPOT_LINK_PATH,
			// Credentials and configuration
			'.aws/**',
			'**/.env',
			'.git/**',
			'.svn/**',
			'web.config',
			// WordPress
			'**/wp-login.php',
			'**/wp-admin/**',
			'**/wp-content/**',
			'**/wp-includes/**',
			'**/xmlrpc.php',
			// PHP tools
			'**/phpmyadmin/**',
			'pma/**',
			'**/phpunit/**',
			// Microsoft Exchange, Outlook Web Access, Autodiscover
			'owa/**',
			'ecp/**',
			'ews/**',
			'autodiscover/**',
			// Other platforms
			'admin/**',
			'install/**',
			'cgi-bin/**',
			'Telerik.Web.UI.WebResource.axd',
			'localstart.aspx',
			'main.aspx',
			'index.asp',
			'index.action',
			'login.action',
			'main.jhtml',
			'main.jsa',
			'base.pl',
			'dns-query',
			'api/v1/trade',
		];
	}

	/*
		Function: hypha_honeypotPatterns
		The default patterns, adjusted by data/hypha.honeypot.paths when it exists.
	*/
	function hypha_honeypotPatterns() {
		$patterns = hypha_honeypotDefaultPatterns();
		$file = 'data/hypha.honeypot.paths';
		if (!is_file($file))
			return $patterns;
		foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
			$line = trim($line);
			if ($line === '' || $line[0] === '#')
				continue;
			if ($line[0] === '!')
				$patterns = array_values(array_filter($patterns, function ($pattern) use ($line) {
					return strcasecmp($pattern, substr($line, 1)) !== 0;
				}));
			else
				$patterns[] = $line;
		}
		return $patterns;
	}

	/*
		Function: hypha_honeypotPatternToRegex
		Turns a trap pattern into an anchored, case-insensitive regular expression.
	*/
	function hypha_honeypotPatternToRegex($pattern) {
		$pattern = trim($pattern, '/');
		$regex = '';
		$parts = preg_split('#(\*\*/|/\*\*|\*\*|\*)#', $pattern, -1, PREG_SPLIT_DELIM_CAPTURE);
		foreach ($parts as $part) {
			if ($part === '**/')
				$regex .= '(?:.*/)?';
			else if ($part === '/**')
				$regex .= '(?:/.*)?';
			else if ($part === '**')
				$regex .= '.*';
			else if ($part === '*')
				$regex .= '[^/]*';
			else
				$regex .= preg_quote($part, '#');
		}
		return '#^' . $regex . '$#i';
	}

	/*
		Function: hypha_honeypotIsTrap
		Whether the given path (relative to the site root, including the language) is a trap.
	*/
	function hypha_honeypotIsTrap($path) {
		$path = trim($path, '/');
		if ($path === '')
			return false;
		foreach (hypha_honeypotPatterns() as $pattern)
			if (preg_match(hypha_honeypotPatternToRegex($pattern), $path))
				return true;
		return false;
	}

	/*
		Function: hypha_honeypotClientAddress
		The client address, or '' when it is not known (e.g. CLI).
	*/
	function hypha_honeypotClientAddress() {
		return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
	}

	function hypha_honeypotKey($address) {
		return hash('sha256', $address);
	}

	/*
		Function: hypha_honeypotIsBlocked
		Whether the given address is currently blocked. Reads without a lock; a stale read only
		means at most one extra allowed request.
	*/
	function hypha_honeypotIsBlocked($address) {
		if ($address === '') return false;
		$file = 'data/hypha.honeypot';
		if (!is_file($file)) return false;
		$blocks = json_decode(file_get_contents($file), true);
		if (!is_array($blocks)) return false;
		$key = hypha_honeypotKey($address);
		return isset($blocks[$key]) && $blocks[$key] > time();
	}

	/*
		Function: hypha_honeypotBlock
		Block the given address for HYPHA_HONEYPOT_BLOCK_SECONDS, pruning expired entries.
	*/
	function hypha_honeypotBlock($address) {
		if ($address === '') return;
		$handle = fopen('data/hypha.honeypot', 'c+');
		if ($handle === false) return;
		if (!flock($handle, LOCK_EX)) { fclose($handle); return; }
		$contents = stream_get_contents($handle);
		$blocks = $contents ? json_decode($contents, true) : [];
		if (!is_array($blocks)) $blocks = [];
		$now = time();
		foreach ($blocks as $key => $expiry) if ($expiry <= $now) unset($blocks[$key]);
		$blocks[hypha_honeypotKey($address)] = $now + HYPHA_HONEYPOT_BLOCK_SECONDS;
		ftruncate($handle, 0);
		rewind($handle);
		fwrite($handle, json_encode($blocks));
		fflush($handle);
		flock($handle, LOCK_UN);
		fclose($handle);
	}

	/*
		Function: hypha_honeypotGuard
		Called early in index.php with the request path relative to the site root, including
		the language. Sends 403 and exits when the client is blocked or has just hit a trap.
	*/
	function hypha_honeypotGuard($path) {
		$address = hypha_honeypotClientAddress();
		if (hypha_honeypotIsBlocked($address)) {
			http_response_code(403);
			exit;
		}
		if (hypha_honeypotIsTrap($path)) {
			hypha_honeypotBlock($address);
			http_response_code(403);
			exit;
		}
	}

	/*
		Function: hypha_honeypotLinkHtml
		A hidden link to the trap, to add to every page. Hidden from sight and from assistive
		technology, and marked nofollow, so only a scanner that reads the raw HTML follows it.
	*/
	function hypha_honeypotLinkHtml() {
		return '<a href="' . HYPHA_HONEYPOT_LINK_PATH . '" rel="nofollow" aria-hidden="true" tabindex="-1" style="display:none">.</a>';
	}
