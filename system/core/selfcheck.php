<?php
	/*
		Section: Self-check of the data folder

		All site data, including user accounts and password hashes, is in data/ below the
		web root. It is protected only because .htaccess sends every request for data/
		through index.php. When Apache does not read .htaccess (AllowOverride None) or
		mod_rewrite is off, anyone can download data/hypha.xml.

		Hypha checks this on the first request and then every HYPHA_SELFCHECK_INTERVAL
		seconds: it writes a probe file with a random token to data/ and requests it from
		its own web server. When the token comes back, data/ is readable over HTTP and
		Hypha stops: every page answers 503 with an explanation, and the problem is logged.
		That result is not cached, so the site works again as soon as the configuration
		is fixed.

		PHP cannot stop Apache from serving a file directly, so this does not close the
		leak by itself. It makes sure the site does not keep running with it unnoticed.
		When the check cannot be carried out (no connection to the local web server,
		allow_url_fopen off), Hypha does not stop, but logs a warning.
	*/

	define('HYPHA_SELFCHECK_INTERVAL', 600);
	define('HYPHA_SELFCHECK_PROBE', 'hypha.probe');
	define('HYPHA_SELFCHECK_RESULT', 'data/hypha.selfcheck');
	define('HYPHA_SELFCHECK_HEADER', 'X-Hypha-Selfcheck');

	/*
		Function: hypha_dataFolderIsExposed
		Requests a probe file in data/ from the local web server.
		Returns true when it is served, false when it is not, and
		null when that cannot be determined.

		Parameters:
		$rootUrlPath - path of the site root on the server, ending in /
	*/
	function hypha_dataFolderIsExposed($rootUrlPath) {
		$token = bin2hex(random_bytes(16));
		if (@file_put_contents('data/' . HYPHA_SELFCHECK_PROBE, $token, LOCK_EX) === false)
			return null;
		if (!ini_get('allow_url_fopen'))
			return null;

		// Ask this web server itself, on the local address, for the
		// virtual host of this request, so a proxy in front does not
		// hide what Apache would serve. SERVER_PORT follows the Host
		// header, which behind NGINX or a port mapping is not the port
		// Apache listens on, so also try the usual ones.
		$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
		$ports = [isset($_SERVER['SERVER_PORT']) ? (int)$_SERVER['SERVER_PORT'] : 80, 80, 8080, 443];
		foreach (array_unique($ports) as $port) {
			$scheme = $port === 443 ? 'https' : 'http';
			$url = $scheme . '://127.0.0.1:' . $port . $rootUrlPath . 'data/' . HYPHA_SELFCHECK_PROBE;
			$context = stream_context_create([
				'http' => [
					'method' => 'GET',
					'header' => "Host: " . $host . "\r\n" . HYPHA_SELFCHECK_HEADER . ": 1\r\n",
					'timeout' => 3,
					'follow_location' => 0,
					'ignore_errors' => true,
				],
				'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
			]);
			$body = @file_get_contents($url, false, $context);
			// $http_response_header is deprecated in PHP 8.5; its
			// replacement exists since 8.4.
			$headers = function_exists('http_get_last_response_headers') ? http_get_last_response_headers() : ($http_response_header ?? null);
			if ($body === false || !isset($headers[0]))
				continue; // nothing listens there
			$status = (int)(explode(' ', $headers[0])[1] ?? 0);
			return $status === 200 && trim($body) === $token;
		}
		return null;
	}

	/*
		Function: hypha_selfCheckGuard
		Called early in index.php. Stops the request with 503 when data/ is readable over
		HTTP. Checks at most once per HYPHA_SELFCHECK_INTERVAL while the result is good.

		Parameters:
		$rootUrlPath - path of the site root on the server, ending in /
	*/
	function hypha_selfCheckGuard($rootUrlPath) {
		// The probe request itself comes back through index.php when
		// .htaccess works; do not check again from there.
		if (isset($_SERVER['HTTP_X_HYPHA_SELFCHECK']))
			return;

		$cached = @json_decode((string)@file_get_contents(HYPHA_SELFCHECK_RESULT), true);
		if (is_array($cached) && isset($cached['time'], $cached['result']) && $cached['result'] !== 'exposed' && time() - $cached['time'] < HYPHA_SELFCHECK_INTERVAL)
			return;

		$exposed = hypha_dataFolderIsExposed($rootUrlPath);
		$result = $exposed === null ? 'unknown' : ($exposed ? 'exposed' : 'ok');
		@file_put_contents(HYPHA_SELFCHECK_RESULT, json_encode(['time' => time(), 'result' => $result]), LOCK_EX);

		if ($result === 'unknown')
			error_log('Hypha: could not check whether data/ is readable over HTTP; make sure it is not (see README, "Web Server Configuration")');
		if ($result !== 'exposed')
			return;

		error_log('Hypha stopped: data/ is readable over HTTP, so user accounts and password hashes can be downloaded. Let Apache read .htaccess (AllowOverride FileInfo) with mod_rewrite enabled, or deny data/ in the virtual host (see README, "Web Server Configuration").');
		http_response_code(503);
		header('Content-Type: text/html; charset=utf-8');
		header('Retry-After: 600');
		echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Hypha stopped</title></head><body>';
		echo '<h1>Hypha stopped</h1>';
		echo '<p>The data folder of this site can be downloaded directly from the web server, including user accounts and password hashes. Hypha does not run until that is fixed.</p>';
		echo '<p>For the administrator: let Apache read <code>.htaccess</code> (<code>AllowOverride FileInfo</code>) with <code>mod_rewrite</code> enabled, or deny <code>data/</code> in the virtual host. See the section "Web Server Configuration" in the README.</p>';
		echo '</body></html>';
		exit;
	}
