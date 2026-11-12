<?php
	/*
		Section: Sitemap and robots.txt
		Answers /sitemap.xml and /robots.txt for search engines.

		The sitemap (https://www.sitemaps.org/protocol.html) has one <url>
		for every language version of every page an anonymous visitor
		can read, at the address /<language>/<page name>. Private pages,
		unpublished articles and system views (edit, index, tag, settings)
		are left out. The home page and the pages linked from the menu
		get priority 0.8, the others 0.6. The lastmod is the time of the
		newest version of that language, in UTC.

		Above HYPHA_SITEMAP_MAX_URLS entries, /sitemap.xml becomes a
		sitemap index that points to /sitemap-1.xml, /sitemap-2.xml and
		so on, since a single sitemap may hold at most 50,000 urls.

		A site that is kept out of search engines (allowIndex="off" on
		the root of hypha.xml, see #404) has no sitemap, and its
		robots.txt disallows everything.

		Both are answered from a cache in data/ while no page has
		changed, before hypha.xml is read, as anonymous pages are.
	*/

	const HYPHA_SITEMAP_MAX_URLS = 50000;
	const HYPHA_SITEMAP_PRIORITY_MAIN = '0.8';
	const HYPHA_SITEMAP_PRIORITY_OTHER = '0.6';
	const HYPHA_SITEMAP_CACHE_FILE = 'data/sitemap.cache';
	const HYPHA_LLMS_CACHE_FILE = 'data/llms.cache';
	const HYPHA_LLMS_MAX_PAGES = 10;

	/*
		Function: hypha_getSitemapAllowed
		returns whether search engines may index this site. True unless
		the allowIndex attribute of hypha.xml is "off".
	*/
	function hypha_getSitemapAllowed() {
		global $hyphaXml;
		return $hyphaXml->documentElement->getAttribute('allowIndex') !== 'off';
	}

	/*
		Function: hypha_getSitemapMainPageIds
		returns the ids of the main pages as array keys: the home page
		and every page linked from the menu.
	*/
	function hypha_getSitemapMainPageIds() {
		global $hyphaXml;
		$ids = [];
		$home = hypha_getPage(hypha_getDefaultLanguage(), hypha_getDefaultPage());
		if ($home) $ids[$home->getAttribute('id')] = true;
		$menu = $hyphaXml->findOneXPath('menu');
		if ($menu) foreach ($menu->findXPath('.//a[@href]') as $link) {
			// Menu links are stored as hypha:<page id>, see wikify_link()
			if (preg_match('|^hypha:([^/#]+)|', $link->getAttribute('href'), $matches))
				$ids[$matches[1]] = true;
		}
		return $ids;
	}

	/*
		Function: hypha_getSitemapLastModified
		returns the time of the last change of every language of a
		page, as an array of language => unix time. A language without
		a known time is missing from the array. Returns false when an
		anonymous visitor cannot read the page, such as an article that
		is not published.

		Parameters:
		$page - the page node from hypha.xml
	*/
	function hypha_getSitemapLastModified(HyphaDomElement $page) {
		$type = $page->getAttribute('type');
		if ($type !== textpage::class && $type !== peer_reviewed_article::class)
			return [];

		$xml = new Xml();
		$xml->loadFromFile('data/pages/' . $page->getAttribute('id'));
		if (!$xml->documentElement)
			return $type === textpage::class ? [] : false;

		$times = [];
		if ($type === textpage::class) {
			foreach ($xml->findXPath('language') as $language) {
				$version = getCurrentVersionNode($language);
				if ($version) $times[$language->getAttribute('xml:id')] = (int)ltrim($version->getAttribute('xml:id'), 't');
			}
			return $times;
		}

		$article = $xml->findOneXPath(peer_reviewed_article::FIELD_NAME_ARTICLE);
		if (!$article || $article->getAttribute(peer_reviewed_article::FIELD_NAME_STATUS) !== peer_reviewed_article::STATUS_PUBLISHED)
			return false;
		$time = max((int)ltrim($article->getAttribute(peer_reviewed_article::FIELD_NAME_PUBLISHED_AT), 't'), (int)ltrim($article->getAttribute(peer_reviewed_article::FIELD_NAME_UPDATED_AT), 't'));
		// An article has one language
		if ($time > 0) foreach ($page->findXPath('language') as $language)
			$times[$language->getAttribute('id')] = $time;
		return $times;
	}

	/*
		Function: hypha_buildSitemapEntries
		returns the entries of the sitemap, in the order of the page
		list, as arrays with the keys path (relative to the root url),
		lastmod (unix time or null) and priority.
	*/
	function hypha_buildSitemapEntries() {
		$mainPageIds = hypha_getSitemapMainPageIds();
		$dataTypes = hypha_getDataTypes();
		$entries = [];
		foreach (hypha_getPageList() as $page) {
			// The same test as loadPage() for anonymous visitors
			if (in_array($page->getAttribute('private'), ['true', '1', 'on'])) continue;
			if (!array_key_exists($page->getAttribute('type'), $dataTypes)) continue;
			$times = hypha_getSitemapLastModified($page);
			if ($times === false) continue;
			$priority = isset($mainPageIds[$page->getAttribute('id')]) ? HYPHA_SITEMAP_PRIORITY_MAIN : HYPHA_SITEMAP_PRIORITY_OTHER;
			foreach ($page->findXPath('language') as $language) {
				$id = $language->getAttribute('id');
				$entries[] = [
					'path' => $id . '/' . rawurlencode($language->getAttribute('name')),
					'lastmod' => isset($times[$id]) ? $times[$id] : null,
					'priority' => $priority,
				];
			}
		}
		return $entries;
	}

	/*
		Function: hypha_sitemapCacheKey
		returns the key of a cache of <hypha_getSitemapCached>. Every
		save replaces its file by a rename (see HyphaFile), which also
		changes the time of data/pages, so the times of hypha.xml and
		data/pages tell whether a cache is still valid.

		Parameters:
		$variant - what else the result depends on, such as the root url
	*/
	function hypha_sitemapCacheKey($variant = '') {
		clearstatcache();
		return @filemtime('data/hypha.xml') . '-' . @filemtime('data/pages') . '-' . md5($variant);
	}

	/*
		Function: hypha_readSitemapCache
		returns the result in a cache file of <hypha_getSitemapCached>
		while it is valid, else null. Reads neither hypha.xml nor a page
		file, so it can answer before the site data is loaded.

		Parameters:
		$file - the cache file
		$variant - what else the result depends on, such as the root url
	*/
	function hypha_readSitemapCache($file, $variant = '') {
		$cached = json_decode((string)@file_get_contents($file), true);
		if (is_array($cached) && isset($cached['key'], $cached['value']) && $cached['key'] === hypha_sitemapCacheKey($variant))
			return $cached['value'];
		return null;
	}

	/*
		Function: hypha_getSitemapCached
		returns the result of a build function, from a cache file in
		data/ while neither hypha.xml nor a page file has changed.

		Parameters:
		$file - the cache file
		$build - function that returns the result, as JSON-encodable data
		$variant - what else the result depends on, such as the root url
	*/
	function hypha_getSitemapCached($file, callable $build, $variant = '') {
		$value = hypha_readSitemapCache($file, $variant);
		if ($value !== null) return $value;

		$key = hypha_sitemapCacheKey($variant);
		$value = $build();

		// A change in the same second as this build would have the
		// same key, so only keep a cache built after that second.
		// A cache that cannot be written only costs time.
		if (max(filemtime('data/hypha.xml'), filemtime('data/pages')) < time()) {
			$cache = new HyphaFile($file);
			try {
				$cache->lock();
				$cache->writeAndUnlock(json_encode(['key' => $key, 'value' => $value]));
			} catch (RuntimeException $e) {
			}
		}
		return $value;
	}

	/*
		Function: hypha_getSitemapCache
		returns whether the site may be indexed (allowed) and the
		entries of <hypha_buildSitemapEntries>, from a cache, see
		<hypha_getSitemapCached>.
	*/
	function hypha_getSitemapCache() {
		return hypha_getSitemapCached(HYPHA_SITEMAP_CACHE_FILE, function () {
			$allowed = hypha_getSitemapAllowed();
			return ['allowed' => $allowed, 'entries' => $allowed ? hypha_buildSitemapEntries() : []];
		});
	}

	/*
		Function: hypha_serveSitemapFast
		Called at the top of index.php, before data/hypha.xml is
		parsed, like the cache of anonymous pages: answers /robots.txt
		and /sitemap.xml from the cache of <hypha_getSitemapCache> and
		exits. Returns when the request is for something else or the
		cache is missing or outdated, so the normal code path runs and
		builds the cache.
	*/
	function hypha_serveSitemapFast() {
		if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'], true)) return;
		$request = new HyphaRequest();
		$path = $request->getRelativeUrlPath(false);
		if ($path !== 'robots.txt' && !preg_match('/^sitemap(?:-([1-9][0-9]*))?\.xml$/', $path, $matches)) return;
		$sitemap = hypha_readSitemapCache(HYPHA_SITEMAP_CACHE_FILE);
		if (!$sitemap) return;
		if ($path === 'robots.txt') serveRobotsTxt($request->getRootUrl(), $sitemap['allowed']);
		serveSitemap($request->getRootUrl(), isset($matches[1]) ? (int)$matches[1] : null, $sitemap);
	}


	/*
		Function: hypha_sitemapUrlSet
		returns a sitemap (urlset) with the given entries.

		Parameters:
		$rootUrl - the root url of the site, ending in a slash
		$entries - entries of <hypha_buildSitemapEntries>
	*/
	function hypha_sitemapUrlSet($rootUrl, array $entries) {
		$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml.= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
		foreach ($entries as $entry) {
			$xml.= "\t<url>\n";
			$xml.= "\t\t<loc>" . htmlspecialchars($rootUrl . $entry['path'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</loc>\n";
			if ($entry['lastmod'] !== null)
				$xml.= "\t\t<lastmod>" . gmdate('Y-m-d\TH:i:s\Z', $entry['lastmod']) . "</lastmod>\n";
			$xml.= "\t\t<priority>" . $entry['priority'] . "</priority>\n";
			$xml.= "\t</url>\n";
		}
		$xml.= "</urlset>\n";
		return $xml;
	}

	/*
		Function: hypha_sitemapIndex
		returns a sitemap index that points to one sitemap per chunk.

		Parameters:
		$rootUrl - the root url of the site, ending in a slash
		$chunks - the entries, split in chunks of HYPHA_SITEMAP_MAX_URLS
	*/
	function hypha_sitemapIndex($rootUrl, array $chunks) {
		$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml.= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
		foreach ($chunks as $index => $entries) {
			$lastmod = max(array_map(function($entry) { return (int)$entry['lastmod']; }, $entries));
			$xml.= "\t<sitemap>\n";
			$xml.= "\t\t<loc>" . htmlspecialchars($rootUrl . 'sitemap-' . ($index + 1) . '.xml', ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</loc>\n";
			if ($lastmod > 0)
				$xml.= "\t\t<lastmod>" . gmdate('Y-m-d\TH:i:s\Z', $lastmod) . "</lastmod>\n";
			$xml.= "\t</sitemap>\n";
		}
		$xml.= "</sitemapindex>\n";
		return $xml;
	}

	/*
		Function: serveSitemap
		answers /sitemap.xml or /sitemap-<number>.xml and exits.

		Parameters:
		$rootUrl - the root url of the site, ending in a slash
		$number - null for /sitemap.xml, else the number of the part
		$sitemap - the result of <hypha_getSitemapCache>
	*/
	function serveSitemap($rootUrl, $number, array $sitemap) {
		if (!$sitemap['allowed']) {
			http_response_code(404);
			exit;
		}
		$chunks = array_chunk($sitemap['entries'], HYPHA_SITEMAP_MAX_URLS);
		if ($number === null && count($chunks) <= 1)
			$xml = hypha_sitemapUrlSet($rootUrl, $chunks ? $chunks[0] : []);
		else if ($number === null)
			$xml = hypha_sitemapIndex($rootUrl, $chunks);
		else if (count($chunks) > 1 && isset($chunks[$number - 1]))
			$xml = hypha_sitemapUrlSet($rootUrl, $chunks[$number - 1]);
		else {
			http_response_code(404);
			exit;
		}
		header('Content-Type: application/xml; charset=utf-8');
		echo $xml;
		exit;
	}

	/*
		Function: serveLlmsTxt
		Answers /llms.txt (llmstxt.org): the site in Markdown for
		language models and AI agents, with the HYPHA_LLMS_MAX_PAGES
		public pages that were created last; the sitemap has all of
		them. Published articles first, with their excerpt; then the
		other pages; both newest first. A site that may not be indexed
		answers 404, as for the sitemap.

		Parameters:
		$rootUrl - the root url of the site
	*/
	function serveLlmsTxt($rootUrl) {
		if (!hypha_getSitemapAllowed()) {
			http_response_code(404);
			exit;
		}
		// Reading every page takes long on a large site, so the lists
		// come from a cache while no page has changed (#701).
		$parts = hypha_getSitemapCached(HYPHA_LLMS_CACHE_FILE, fn () => hypha_buildLlmsParts($rootUrl), $rootUrl);
		$out = '# ' . hypha_llmsLabel(hypha_getTitle()) . "\n\n";
		if ($parts['summary'] !== '') $out .= '> ' . $parts['summary'] . "\n\n";
		$out .= __('llms-intro', ['count' => HYPHA_LLMS_MAX_PAGES, 'sitemap' => $rootUrl . 'sitemap.xml']) . "\n";
		if ($parts['articles']) $out .= "\n## " . __('llms-articles') . "\n\n" . implode("\n", $parts['articles']) . "\n";
		if ($parts['pages']) $out .= "\n## " . __('llms-pages') . "\n\n" . implode("\n", $parts['pages']) . "\n";
		header('Content-Type: text/markdown; charset=utf-8');
		echo $out;
		exit;
	}

	/*
		Function: hypha_llmsLabel
		returns text as one line for the label of a Markdown link: a [
		or ] would end the link.

		Parameters:
		$text - the text
	*/
	function hypha_llmsLabel($text) {
		return str_replace(['[', ']'], ['(', ')'], trim(preg_replace('/\s+/u', ' ', (string)$text)));
	}

	/*
		Function: hypha_getLlmsCreated
		returns when a language version of a page was created, as unix
		time, or 0 when that is not known: the creation time of an
		article, else the time of the first version of the text.

		Parameters:
		$xml - the page file, or null
		$type - the type of the page
		$language - the language id
	*/
	function hypha_getLlmsCreated($xml, $type, $language) {
		if (!$xml || !$xml->documentElement) return 0;
		if ($type === peer_reviewed_article::class) {
			$article = $xml->findOneXPath(peer_reviewed_article::FIELD_NAME_ARTICLE);
			if (!$article) return 0;
			// Articles from before created_at was stored
			foreach ([peer_reviewed_article::FIELD_NAME_CREATED_AT, peer_reviewed_article::FIELD_NAME_PUBLISHED_AT, peer_reviewed_article::FIELD_NAME_UPDATED_AT] as $field)
				if ((int)ltrim($article->getAttribute($field), 't') > 0) return (int)ltrim($article->getAttribute($field), 't');
			return 0;
		}
		$times = [];
		foreach ($xml->findXPath('language[@xml:id=' . hypha_sitemapQuote($language) . ']/version') as $version)
			$times[] = (int)ltrim($version->getAttribute('xml:id'), 't');
		return $times ? min($times) : 0;
	}

	/*
		Function: hypha_buildLlmsParts
		returns the parts of llms.txt that depend on the pages: the
		summary, and the lines of the HYPHA_LLMS_MAX_PAGES pages that
		were created last, the published articles and the other pages
		apart, newest first.

		Parameters:
		$rootUrl - the root url of the site
	*/
	function hypha_buildLlmsParts($rootUrl) {
		$label = 'hypha_llmsLabel';
		$short = fn ($text, $length) => mb_strlen($text) > $length ? rtrim(mb_substr($text, 0, mb_strrpos(mb_substr($text, 0, $length), ' ') ?: $length), ' ,.;:') . '…' : $text;
		$dataTypes = hypha_getDataTypes();
		$items = [];
		$summary = '';
		foreach (hypha_getPageList() as $page) {
			if (in_array($page->getAttribute('private'), ['true', '1', 'on'])) continue;
			$type = $page->getAttribute('type');
			if (!array_key_exists($type, $dataTypes)) continue;
			$times = hypha_getSitemapLastModified($page);
			if ($times === false) continue;
			$xml = null;
			if ($type === peer_reviewed_article::class || $type === textpage::class) {
				$xml = new Xml();
				$xml->loadFromFile('data/pages/' . $page->getAttribute('id'));
			}
			foreach ($page->findXPath('language') as $language) {
				$url = $rootUrl . $language->getAttribute('id') . '/' . rawurlencode($language->getAttribute('name'));
				$item = ['created' => hypha_getLlmsCreated($xml, $type, $language->getAttribute('id')), 'order' => count($items), 'article' => $type === peer_reviewed_article::class];
				if ($type === peer_reviewed_article::class) {
					$title = $xml && $xml->documentElement ? $xml->findXPath('//title')->item(0) : null;
					$excerpt = $xml && $xml->documentElement ? $xml->findXPath('//excerpt')->item(0) : null;
					$item['line'] = '- [' . $label($title ? $title->textContent : showPagename($language->getAttribute('name'))) . '](' . $url . ')' . ($excerpt && hypha_plainText($excerpt) !== '' ? ': ' . $short(hypha_plainText($excerpt), 200) : '');
				}
				else {
					$item['line'] = '- [' . $label(showPagename($language->getAttribute('name'))) . '](' . $url . ')';
					// The text of the default page summarises the site.
					if ($summary === '' && $xml && $xml->documentElement && $language->getAttribute('name') === hypha_getDefaultPage()) {
						$version = getCurrentVersionNode($xml->findXPath('language')->item(0));
						if ($version) $summary = $short(hypha_plainText($version), 300);
					}
				}
				$items[] = $item;
			}
		}
		// Of pages created in the same second, the one added last to
		// the page list is the newest.
		usort($items, fn ($a, $b) => $b['created'] - $a['created'] ?: $b['order'] - $a['order']);
		$items = array_slice($items, 0, HYPHA_LLMS_MAX_PAGES);
		$lines = fn ($article) => array_column(array_filter($items, fn ($item) => $item['article'] === $article), 'line');
		return ['summary' => $summary, 'articles' => $lines(true), 'pages' => $lines(false)];
	}

	/*
		Function: hypha_sitemapQuote
		Returns the string as an XPath 1.0 string literal.
	*/
	function hypha_sitemapQuote($string) {
		if (strpos($string, '"') === false) return '"' . $string . '"';
		if (strpos($string, "'") === false) return "'" . $string . "'";
		return 'concat("' . str_replace('"', '", \'"\', "', $string) . '")';
	}

	/*
		Function: serveRobotsTxt
		answers /robots.txt and exits: it names the sitemap, or
		disallows everything when the site is kept out of search
		engines.

		Parameters:
		$rootUrl - the root url of the site, ending in a slash
		$allowed - whether search engines may index the site
	*/
	function serveRobotsTxt($rootUrl, $allowed) {
		header('Content-Type: text/plain; charset=utf-8');
		echo "User-agent: *\n";
		if ($allowed) {
			echo "Allow: /\n";
			echo "\n";
			echo 'Sitemap: ' . $rootUrl . "sitemap.xml\n";
		}
		else
			echo "Disallow: /\n";
		exit;
	}
