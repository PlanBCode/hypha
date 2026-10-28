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
	*/

	const HYPHA_SITEMAP_MAX_URLS = 50000;
	const HYPHA_SITEMAP_PRIORITY_MAIN = '0.8';
	const HYPHA_SITEMAP_PRIORITY_OTHER = '0.6';
	const HYPHA_SITEMAP_CACHE_FILE = 'data/sitemap.cache';

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
		Function: hypha_getSitemapEntries
		returns the entries of <hypha_buildSitemapEntries>, from a
		cache in data/ while neither hypha.xml nor a page file has
		changed. Every save replaces its file by a rename (see
		HyphaFile), which also changes the time of data/pages, so the
		times of hypha.xml and data/pages tell whether the cache is
		still valid.
	*/
	function hypha_getSitemapEntries() {
		clearstatcache();
		$key = filemtime('data/hypha.xml') . '-' . filemtime('data/pages');
		$cache = new HyphaFile(HYPHA_SITEMAP_CACHE_FILE);
		if (file_exists(HYPHA_SITEMAP_CACHE_FILE)) {
			$cached = json_decode($cache->read(), true);
			if (is_array($cached) && isset($cached['key'], $cached['entries']) && $cached['key'] === $key)
				return $cached['entries'];
		}

		$entries = hypha_buildSitemapEntries();

		// A change in the same second as this build would have the
		// same key, so only keep a cache built after that second.
		// A cache that cannot be written only costs time.
		if (max(filemtime('data/hypha.xml'), filemtime('data/pages')) < time()) {
			try {
				$cache->lock();
				$cache->writeAndUnlock(json_encode(['key' => $key, 'entries' => $entries]));
			} catch (RuntimeException $e) {
			}
		}
		return $entries;
	}

	/*
		Function: hypha_sitemapUrlSet
		returns a sitemap (urlset) with the given entries.

		Parameters:
		$rootUrl - the root url of the site, ending in a slash
		$entries - entries of <hypha_getSitemapEntries>
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
	*/
	function serveSitemap($rootUrl, $number) {
		if (!hypha_getSitemapAllowed()) {
			http_response_code(404);
			exit;
		}
		$chunks = array_chunk(hypha_getSitemapEntries(), HYPHA_SITEMAP_MAX_URLS);
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
		Function: serveRobotsTxt
		answers /robots.txt and exits: it names the sitemap, or
		disallows everything when the site is kept out of search
		engines.

		Parameters:
		$rootUrl - the root url of the site, ending in a slash
	*/
	function serveRobotsTxt($rootUrl) {
		header('Content-Type: text/plain; charset=utf-8');
		echo "User-agent: *\n";
		if (hypha_getSitemapAllowed()) {
			echo "Allow: /\n";
			echo "\n";
			echo 'Sitemap: ' . $rootUrl . "sitemap.xml\n";
		}
		else
			echo "Disallow: /\n";
		exit;
	}
