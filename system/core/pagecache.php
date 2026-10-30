<?php
	/*
		Title: Site data cache

		Caches what anonymous page views need, so that 100 views per
		second fit on one CPU (PlanBCode/hypha#411).

		- <HyphaCacheGeneration>: one value that changes on every write
		  to the site data, read before any data file is read.
		- <HyphaMemoryDb>: the in-memory database in APCu shared memory,
		  shared by all workers of one server.
		- <HyphaPageIndex>: sort date of every page; <HyphaExcerpts>:
		  the rendered excerpt of every article, so the pagelist macro
		  opens no page file at all.
		- <HyphaRoute>: what the fast path needs from data/hypha.xml.
		- <HyphaPageCache>: anonymous HTML, fresh for CACHE_TTL, then
		  served stale for up to STALE_TTL while one request rebuilds
		  it. Anonymous HTML holds no CSRF token (see
		  RequestContext::getOrGenerateCsrfToken), so it is the same
		  for every anonymous visitor.
	*/

	class HyphaCacheGeneration {
		const DIR = 'data/cache';
		const FILE = 'data/cache/generation';
		private static $value = null;

		/*
			Function: read
			Reads the generation once per request. Must be called
			before data/hypha.xml or a page file is read, so a
			result can never be stored under a newer generation
			than the data it was built from.
		*/
		static function read() {
			if (self::$value === null) {
				$value = @file_get_contents(self::FILE);
				self::$value = $value === false ? '0' : $value;
			}
			return self::$value;
		}

		/*
			Function: affects
			Whether a write to this file changes rendered pages.
			Stats, digest and the cache itself do not.
		*/
		static function affects($filename) {
			return $filename === 'data/hypha.xml'
				|| strpos($filename, 'data/pages/') === 0
				|| strpos($filename, 'data/themes/') === 0;
		}

		/*
			Function: bump
			Called by HyphaFile::write after the rename, while the
			writer still holds its lock.
		*/
		static function bump() {
			if (!is_dir(self::DIR)) @mkdir(self::DIR, 0775, true);
			$tmp = self::FILE . '.' . getmypid();
			if (@file_put_contents($tmp, bin2hex(random_bytes(8))) !== false)
				@rename($tmp, self::FILE);
		}
	}

	/*
		Class: HyphaMemoryDb
		Thin wrapper around APCu. Keys are prefixed per site (the
		real path of data/), since one APCu segment is shared by all
		sites of an Apache or PHP-FPM pool. When APCu is missing or
		off, every get misses and every set is ignored, so the
		callers fall back to building from the files.
	*/
	class HyphaMemoryDb {
		private static $prefix = null;

		static function enabled() {
			static $enabled = null;
			if ($enabled === null)
				$enabled = function_exists('apcu_enabled') && apcu_enabled();
			return $enabled;
		}

		static function prefixed($key) {
			return self::key($key);
		}

		private static function key($key) {
			if (self::$prefix === null) self::$prefix = 'hypha:' . md5((string)realpath('data')) . ':';
			return self::$prefix . $key;
		}

		static function get($key) {
			if (!self::enabled()) return null;
			$value = apcu_fetch(self::key($key), $ok);
			return $ok ? $value : null;
		}

		static function set($key, $value, $ttl = 0) {
			if (self::enabled()) apcu_store(self::key($key), $value, $ttl);
		}

		// Returns true for exactly one caller until $ttl passes or release().
		static function lock($key, $ttl) {
			return self::enabled() && apcu_add(self::key('lock:' . $key), getmypid(), $ttl);
		}

		static function release($key) {
			if (self::enabled()) apcu_delete(self::key('lock:' . $key));
		}

		static function inc($key) {
			if (!self::enabled()) return false;
			$k = self::key($key);
			apcu_add($k, 0);
			return apcu_inc($k);
		}

		static function take($key) {
			if (!self::enabled()) return 0;
			$k = self::key($key);
			$value = 0;
			// Read and reset; a concurrent increment between the two
			// is counted in the next flush.
			$value = (int)apcu_fetch($k);
			apcu_dec($k, $value);
			return $value;
		}
	}

	class HyphaPageIndex {
		const FILE = 'data/cache/sortdates.json';

		/*
			Function: getSortTimestamps
			Returns [page id => sort timestamp or 0]. An entry is
			reused while the page file has the same inode, size and
			mtime (HyphaFile always writes a new file and renames
			it). While the generation is unchanged the stat() calls
			are skipped. Kept in the memory db, or in a JSON file
			without APCu.
		*/
		static function getSortTimestamps(RequestContext $O_O, $nodes) {
			$generation = HyphaCacheGeneration::read();
			$index = HyphaMemoryDb::get('sortdates');
			if (!is_array($index)) {
				$contents = HyphaMemoryDb::enabled() ? false : @file_get_contents(self::FILE);
				$index = $contents ? json_decode($contents, true) : null;
			}
			if (!is_array($index)) $index = ['generation' => null, 'pages' => []];
			$trusted = $index['generation'] === $generation;
			$pages = $index['pages'];
			$result = [];
			$changed = false;
			if (!$trusted) clearstatcache();
			foreach ($nodes as $node) {
				$id = $node->getAttribute('id');
				if (!$trusted || !isset($pages[$id])) {
					$st = @stat('data/pages/' . $id);
					$sig = $node->getAttribute('type') . ':' . ($st ? $st['ino'] . ':' . $st['size'] . ':' . $st['mtime'] : '-');
					if (!isset($pages[$id]) || $pages[$id][0] !== $sig) {
						$date = createPageInstance($O_O, $node)->getSortDateTime();
						$pages[$id] = [$sig, $date ? $date->getTimestamp() : 0];
						$changed = true;
					}
				}
				$result[$id] = $pages[$id][1];
			}
			if ($changed || !$trusted) {
				$index = ['generation' => $generation, 'pages' => $pages];
				if (HyphaMemoryDb::enabled()) {
					HyphaMemoryDb::set('sortdates', $index);
				} else {
					if (!is_dir(dirname(self::FILE))) @mkdir(dirname(self::FILE), 0775, true);
					$tmp = self::FILE . '.' . getmypid();
					if (@file_put_contents($tmp, json_encode($index)) !== false)
						@rename($tmp, self::FILE);
				}
			}
			return $result;
		}
	}

	/*
		Class: HyphaExcerpts
		The rendered excerpt (title, author, date, first lines) of a
		page per language, valid for one generation: the excerpt can
		depend on hypha.xml (author names) as well as on the page
		file, so every write renders it again on first use.
	*/
	class HyphaExcerpts {
		static function get($id, $language) {
			$entry = HyphaMemoryDb::get('excerpt:' . $id . ':' . $language);
			return is_array($entry) && $entry[0] === HyphaCacheGeneration::read() ? $entry[1] : null;
		}

		static function set($id, $language, $html) {
			HyphaMemoryDb::set('excerpt:' . $id . ':' . $language, [HyphaCacheGeneration::read(), $html]);
		}
	}

	/*
		Class: HyphaRoute
		The part of data/hypha.xml that the fast path needs, stored
		per generation by every full request that does not find it.
	*/
	class HyphaRoute {
		static function get() {
			$route = HyphaMemoryDb::get('route');
			return is_array($route) && $route['generation'] === HyphaCacheGeneration::read() ? $route : null;
		}

		static function storeIfMissing() {
			global $hyphaXml;
			if (!HyphaMemoryDb::enabled() || self::get()) return;
			$pages = [];
			foreach ($hyphaXml->findXPath('pageList/page/language') as $lang) {
				$key = $lang->getAttribute('id') . '/' . strtolower($lang->getAttribute('name'));
				if (isset($pages[$key])) continue;
				$page = $lang->parentNode;
				$pages[$key] = [
					in_array($page->getAttribute('private'), ['true', '1', 'on']),
					$lang->getAttribute('name'),
				];
			}
			HyphaMemoryDb::set('route', [
				'generation' => HyphaCacheGeneration::read(),
				'defaultPage' => hypha_getDefaultPage(),
				'defaultLanguage' => hypha_getDefaultLanguage(),
				'defaultInterfaceLanguage' => hypha_getDefaultInterfaceLanguage(),
				'interfaceLanguages' => Language::getInterfaceLanguageList(),
				'theme' => hypha_getNormalTheme(),
				'digestDue' => (int)hypha_getLastDigestTime() + (int)hypha_getDigestInterval(),
				'pages' => $pages,
			]);
		}
	}

	class HyphaPageCache {
		const DIR = 'data/cache/pages';
		// Fresh for one hour, then served stale for up to two more
		// hours while one request renders the page again. A write
		// makes every entry stale at once (the generation changes),
		// so after an edit the first request renders the new page and
		// concurrent requests get the old one only during that render.
		const CACHE_TTL = 3600;
		const STALE_TTL = 7200;
		const REBUILD_LOCK_TTL = 30;

		// When true, anonymous HTML is marked as cacheable by a shared
		// cache (reverse proxy, CDN) for CACHE_TTL and STALE_TTL. Off by
		// default: a shared cache does not see the generation, so an
		// edit would only show after up to CACHE_TTL + STALE_TTL unless
		// the shared cache is purged on every write.
		const PROXY_CACHE = false;

		static function cacheTtl() { return self::CACHE_TTL; }
		static function staleTtl() { return self::STALE_TTL; }

		private static $key = null;

		/*
			Function: cacheable
			The request conditions that need no site data.
		*/
		static function cacheableRequest() {
			if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET', 'HEAD'])) return false;
			if (!empty($_SERVER['QUERY_STRING'])) return false;
			// A session may hold a login, a preview theme or
			// notifications: always render.
			foreach (['hyphaSessionInsecure', 'hyphaSessionSecure'] as $name)
				if (isset($_COOKIE[$name])) return false;
			return true;
		}

		static function key($rootUrl, $path, $language, $interfaceLanguage, $theme) {
			return sha1(implode("\n", [$rootUrl, $path, $language, $interfaceLanguage, $theme]));
		}

		static function interfaceLanguage(array $available, $contentLanguage, $fallback) {
			$options = [];
			if (array_key_exists('HTTP_ACCEPT_LANGUAGE', $_SERVER))
				$options[] = strtolower(substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2));
			$options[] = $contentLanguage;
			$options[] = $fallback;
			foreach ($options as $option) if (in_array($option, $available)) return $option;
			return null;
		}

		/*
			Function: serveFast
			Called at the very top of index.php, before data/hypha.xml
			is parsed. Serves a fresh or stale entry and exits, or
			returns so the normal code path runs.
		*/
		static function serveFast() {
			if (!HyphaMemoryDb::enabled() || !self::cacheableRequest()) return;
			$route = HyphaRoute::get();
			if (!$route) return;
			// flushDigest() only acts (and only moves lastDigestTime)
			// when there is something to send: then let the normal
			// path run.
			if (time() >= $route['digestDue'] && @filesize('data/digest')) return;

			$request = new HyphaRequest();
			if ($request->isSystemPage() || $request->getView() !== null) return;
			$path = $request->getRelativeUrlPath(false);
			$language = $request->getLanguage() ?: $route['defaultLanguage'];
			$name = $request->getPageName() ?? $route['defaultPage'];
			$page = $route['pages'][$language . '/' . strtolower($name)] ?? null;
			if (!$page || $page[0]) return; // missing or private
			if ($path !== '' && $path !== $language . '/' . $page[1]) return; // not canonical
			$interfaceLanguage = self::interfaceLanguage($route['interfaceLanguages'], $language, $route['defaultInterfaceLanguage']);
			$key = self::key($request->getRootUrl(), $path, $language, $interfaceLanguage, $route['theme']);

			$entry = HyphaMemoryDb::get('html:' . $key);
			if (!is_array($entry)) return;
			$age = time() - $entry['created'];
			$fresh = $entry['generation'] === $route['generation'] && $age < self::cacheTtl();
			if (!$fresh) {
				if ($age >= self::cacheTtl() + self::staleTtl()) return;
				// Stale: the first request rebuilds, the others get
				// the stale copy meanwhile.
				if (HyphaMemoryDb::lock('html:' . $key, self::REBUILD_LOCK_TTL)) {
					self::$key = $key;
					return;
				}
			}
			self::countView($route['digestDue']);
			self::sendHeaders($fresh ? 'hit' : 'stale', $entry['created']);
			print $entry['html'];
			exit;
		}

		/*
			Function: keyFor
			Key for the normal code path, or null when the response
			must not be stored. Same parts as serveFast.
		*/
		static function keyFor(RequestContext $O_O) {
			if (self::$key !== null) return self::$key;
			if (!HyphaMemoryDb::enabled() || !self::cacheableRequest() || $O_O->isUser()) return null;
			$request = $O_O->getRequest();
			if ($request->isSystemPage() || $request->getView() !== null) return null;
			$name = $request->getPageName();
			$language = $O_O->getContentLanguage();
			$path = $request->getRelativeUrlPath(false);
			$node = hypha_getPage($language, $name === null ? hypha_getDefaultPage() : $name);
			if (!$node || in_array($node->getAttribute('private'), ['true', '1', 'on'])) return null;
			if ($path !== '' && $path !== $language . '/' . hypha_pageGetLanguage($node, $language)->getAttribute('name')) return null;
			return self::key($request->getRootUrl(), $path, $language, $O_O->getInterfaceLanguage(), $O_O->getThemeName());
		}

		static function countView($bucket) {
			if (!HyphaMemoryDb::enabled()) {
				// Same as loadPage() for anonymous views.
				require_once 'system/core/database.php';
				$stats = new HyphaFile('data/hypha.stats');
				$contents = $stats->lockAndRead();
				$data = $contents ? json_decode($contents, true) : [];
				$data[$bucket] = ($data[$bucket] ?? 0) + 1;
				$stats->writeAndUnlock(json_encode($data));
				return;
			}
			// Count in memory and add to data/hypha.stats once a minute.
			HyphaMemoryDb::inc('views:' . $bucket);
			HyphaMemoryDb::set('views-buckets:' . $bucket, true, 86400 * 30);
			if (HyphaMemoryDb::lock('stats-flush', 60)) {
				require_once 'system/core/database.php';
				$stats = new HyphaFile('data/hypha.stats');
				$contents = $stats->lockAndRead();
				$data = self::addPendingViews($contents ? json_decode($contents, true) : []);
				$stats->writeAndUnlock(json_encode($data));
			}
		}

		/*
			Function: addPendingViews
			Adds the views counted in memory to the given stats
			array and resets them. Also called by
			hypha_incrementStats(), so the counts are written with
			any write of data/hypha.stats, for every period (bucket)
			that has views pending.
		*/
		static function addPendingViews(array $data) {
			if (!HyphaMemoryDb::enabled()) return $data;
			foreach (new APCUIterator('/^' . preg_quote(HyphaMemoryDb::prefixed('views-buckets:'), '/') . '/', APC_ITER_KEY) as $item) {
				$bucket = substr($item['key'], strlen(HyphaMemoryDb::prefixed('views-buckets:')));
				$count = HyphaMemoryDb::take('views:' . $bucket);
				if ($count) $data[$bucket] = ($data[$bucket] ?? 0) + $count;
			}
			return $data;
		}

		static function sendHeaders($state, $created) {
			header('Content-Type: text/html; charset=utf-8');
			header('X-Hypha-Cache: ' . $state);
			if (self::PROXY_CACHE) {
				// Anonymous HTML holds no token, so a shared cache may
				// keep it: fresh for an hour, stale for two more.
				header('Cache-Control: public, max-age=0, s-maxage=' . self::cacheTtl() . ', stale-while-revalidate=' . self::staleTtl() . ', stale-if-error=' . self::staleTtl());
			} else {
				header('Cache-Control: private, no-cache');
			}
			header('Vary: Cookie, Accept-Language');
		}

		static function store($key, $html, RequestContext $O_O) {
			$failed = http_response_code() !== 200;
			$token = $O_O->peekCsrfToken();
			if ($token && strpos($html, $token) !== false) $failed = true;
			foreach (headers_list() as $header) {
				// A Set-Cookie (a session, a CSRF token) or a redirect
				// means the response is not the same for everyone.
				if (stripos($header, 'Location:') === 0 || stripos($header, 'Set-Cookie:') === 0) $failed = true;
			}
			if (!$failed && HyphaMemoryDb::enabled()) {
				HyphaMemoryDb::set('html:' . $key, [
					'generation' => HyphaCacheGeneration::read(),
					'created' => time(),
					'html' => $html,
				], self::cacheTtl() + self::staleTtl());
			}
			HyphaMemoryDb::release('html:' . $key);
		}
	}
