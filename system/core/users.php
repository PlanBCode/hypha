<?php
//	include_once('base.php');
	include_once('events.php');

	/*
		Title: Users

		This chapter describes the login mechanism and guest editors.

		this module requires two element to exist in the HTMLDocument, 'login' and 'menu'
	*/

	/*
		Function: login
		logs in user, setting SESSION variable hyphaLogin. Returns 'reload' on success.
	*/
	registerCommandCallback('login', 'login');
	function login() {
		global $O_O;
		$username = (string)$O_O->getRequest()->getPostValue('loginUsername');
		$password = (string)$O_O->getRequest()->getPostValue('loginPassword');

		// Limit password guessing. The counters are kept per
		// username (whether or not it exists, so they reveal
		// nothing) and per client address, outside the session
		// so discarding the cookie does not reset them.
		$userBucket = 'login:' . strtolower($username);
		$ipBucket = 'login-ip:' . (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '');
		if (hypha_throttleCount($userBucket, HYPHA_LOGIN_WINDOW) >= HYPHA_LOGIN_MAX_FAILURES_PER_USER
		    || hypha_throttleCount($ipBucket, HYPHA_LOGIN_WINDOW) >= HYPHA_LOGIN_MAX_FAILURES_PER_IP) {
			notify('error', __('login-throttled'));
			return 'reload';
		}

		$user = hypha_getUserByName($username);
		// Spend the same time on an unknown username as on a
		// wrong password, so response times do not reveal which
		// usernames exist.
		if (!$user)
			password_hash($password, PASSWORD_DEFAULT);
		$sess = $O_O->getSession();
		$sess->lockAndReload();
		if ($user && $user->getAttribute('rights') != 'exmember' && verifyPassword($password, $user->getAttribute('password'))) {
			// Use a brand new session id for extra security
			$sess->changeSessionId();
			$sess->set('hyphaLogin', $user->getAttribute('id'));
			$O_O->regenerateCsrfToken();
			$success = true;
		}
		else {
			$sess->remove('hyphaLogin');
			notify('error', __('login-failed').'. <a href="javascript:reregister();">'.__('reregister').'</a>');
			$success = false;
		}
		$sess->writeAndUnlock();
		if ($success) {
			hypha_throttleClear($userBucket);
		} else {
			hypha_throttleRegister($userBucket);
			hypha_throttleRegister($ipBucket);
		}
		return 'reload';
	}

	/*
		Function: logout
		logs out user, unsetting SESSION variable 'hyphaLogin'. Reloads page.
	*/
	registerCommandCallback('logout', 'logout');
	function logout() {
		global $O_O;

		$arg = $O_O->getRequest()->getRelativeUrlPathParts();
		$language = $O_O->getContentLanguage();
		$pagename = $arg[0];
		$page = hypha_getPage($language, $pagename);
		// if current query is a regular page which is not private we can safely log out, but have to switch to default view
		if ($page && $page->getAttribute('private')!=='on') {
			$hyphaQuery = $language.'/'.$pagename;
		}
		// else try and find the default page for the given language
		else {
			$page = hypha_getPage(hypha_getDefaultLanguage(), hypha_getDefaultPage());
			$page = hypha_getPageById($page->getAttribute('id'), $language);
			if ($page) $hyphaQuery = $language.'/'.$page->getAttribute('name');
			else $hyphaQuery = hypha_getDefaultLanguage().'/'.hypha_getDefaultPage();
		}
		$sess = $O_O->getSession();
		$sess->lockAndReload();
		$sess->remove('hyphaLogin');
		$sess->writeAndUnlock();
		return ['redirect', $O_O->getRequest()->getRootUrl() . $hyphaQuery];
	}

	/*
		Function: isUser
		returns true if user is logged in.
	*/
	function isUser() {
		global $O_O;
		return $O_O->isUser();
	}

	/*
		Function: isAdmin
		returns true if user has admin rights.
	*/
	function isAdmin() {
		global $O_O;
		return $O_O->isAdmin();
	}

	/*
		Function getNameForUser
		returns a name for the given user, or the current user
		if no user is given. Returns the fullname if set,
		otherwise the username if set, otherwise the email.
	*/
	function getNameForUser($user = false) {
		global $hyphaUser;
		if ($user === false)
			$user = $hyphaUser;
		if (!$user)
			return __('anonymous');
		return $user->getAttribute('fullname') ?: $user->getAttribute('username') ?: $user->getAttribute('email');
	}

	/*
		Function: getUserEmailList
		returns a comma separated list of all registered users, omiting invitees and exmembers
	*/
	function getUserEmailList() {
		$list = '';
		foreach(hypha_getUserList() as $user) if ($user->getAttribute('rights')=='user' || $user->getAttribute('rights')=='admin') $list.= ($list ? ',' : '').$user->getAttribute('email');
		return $list;
	}

	/*
		Function: createLoginElement
		returns HTML code to insert into the HTML document for the login/out procedures to function.
	*/
	function createLoginElement() {
		return '<div id="login"/>';
	}

	/*
		Function: addLoginRoutine
		add javascript needed for the login procedure to function
	*/
	function addLoginRoutine($hyphaHtml) {
		global $O_O;
		$currentUrlPath = $O_O->getRequest()->getRelativeUrlPath(false);
		ob_start();
		/*
			Function: login
			writes login element

			Function: reregister
			writes login element
		*/
?>
	function login() {
<?php
		$html = <<<EOF
		<div class="login-wrapper">
			<div class="username">
				<label class="username" for="loginUsername">
					[[login-username]]
				</label>
				<input name="loginUsername" id="loginUsername" type="text" />
			</div>
			<div class="password">
				<label class="password" for="loginPassword">
					[[login-password]]
				</label>
				<input name="loginPassword" id="loginPassword" type="password" />
			</div>
			<div class="submit">
				<input class="button" type="submit" name="login" value="[[login]]" onclick="hypha('[[currentUrlPath]]', 'login', '', $(this).closest('form'));" />
			</div>
			<div class="cancel">
				<input class="button" type="button" name="cancel" value="[[cancel]]" onclick="document.getElementById('popup').style.display='none';" />
			</div>
			<div class="forgot-password">
				[[forgot-password]]
				<a href="javascript:reregister();">
					[[reregister]]
				</a>
			</div>
		</div>
EOF;
		$vars = [
			'cancel' => __('cancel'),
			'forgot-password' => __('forgot-password'),
			'login' => __('login'),
			'login-password' => __('login-password'),
			'login-username' => __('login-username'),
			'reregister' => __('reregister'),
			'currentUrlPath' => $currentUrlPath,
		];
		// TODO: This does not work, this does not get the CSRF token...
		$form = $hyphaHtml->createForm(hypha_substitute($html, $vars));
?>
		form = <?= json_encode($form->getOuterHtml()) ?>;
		document.getElementById('popup').innerHTML = form;
		document.getElementById('popup').style.display = 'block';
		document.getElementById('loginUsername').focus();
	}
	function reregister() {
		html = '<table class="section">';
		html+= '<tr><th><?=__('name-or-email')?></th><td><input name="searchLogin" id="searchLogin" type="text" size="10" /></td></tr>';
		html+= '<tr><td></td><td><input type="submit" name="submit" value="<?=__('submit')?>" onclick="hypha(\'<?=$currentUrlPath?>\', \'reregister\', document.getElementById(\'searchLogin\').value, $(this).closest(\'form\'));" /><input type="button" name="cancel" value="<?=__('cancel')?>" onclick="login();" /></td></tr>';
		html+= '</table>';
		document.getElementById('popup').innerHTML = html;
		document.getElementById('popup').style.display = 'block';
		document.getElementById('searchLogin').focus();
	}
<?php
		$hyphaHtml->writeScript(ob_get_clean());
	}

	/*
		Function: reregister
		search for user based on a search string and send notification by email.
	*/
	registerCommandCallback('reregister', 'reregister');
	function reregister($search) {
		global $hyphaUrl, $hyphaXml;
		$search = (string)$search;
		// Always give the same answer, whether or not an account
		// matches, so this cannot be used to find out which
		// usernames and e-mail addresses are registered.
		notify('success', __('reregistration-sent'));

		// An empty search would match invitees, which have no
		// username yet.
		if ($search === '')
			return 'reload';

		$ipBucket = 'recover-ip:' . (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '');
		if (hypha_throttleCount($ipBucket, HYPHA_RECOVERY_WINDOW) >= HYPHA_RECOVERY_MAX_MAILS_PER_IP)
			return 'reload';
		hypha_throttleRegister($ipBucket);

		$hyphaXml->lockAndReload();
		foreach(hypha_getUserList() as $user) if ($user->getAttribute('username')==$search || $user->getAttribute('email')==$search) {
			// Limit the number of recovery mails per account,
			// so the address cannot be flooded.
			$userBucket = 'recover:' . $user->getAttribute('id');
			if (hypha_throttleCount($userBucket, HYPHA_RECOVERY_WINDOW) >= HYPHA_RECOVERY_MAX_MAILS_PER_USER)
				break;
			hypha_throttleRegister($userBucket);

			$key = bin2hex(openssl_random_pseudo_bytes(8));
			hypha_addUserRegistrationKey($user, $key);
			$hyphaXml->saveAndUnlock();
			$mailBody = __('reregister-to').'\''.hypha_getTitle().'\'. '.__('follow-link-to-register').'<br /><a href="'.$hyphaUrl.'settings/register/'.$key.'">'.$hyphaUrl.'settings/register/'.$key.'</a>';
			$result = sendMail($user->getAttribute('email'), __('reregistration').'\''.hypha_getTitle().'\'', nl2br($mailBody));
			// Showing the mail error would reveal that the
			// account exists, so log it for the operator.
			if ($result) error_log('Hypha: sending account recovery mail failed: ' . $result);
			return 'reload';
		}
		$hyphaXml->unlock();
		return 'reload';
	}

	/*
		Constants: limits on login attempts and recovery mails
		Failed logins are counted per username and per client
		address over HYPHA_LOGIN_WINDOW seconds; recovery mails
		per account and per client address over
		HYPHA_RECOVERY_WINDOW seconds. Users behind one proxy or
		NAT share an address and so share the per-address limit;
		15 failed logins per address in 15 minutes still leaves
		room for a few people mistyping their password.
	*/
	const HYPHA_LOGIN_WINDOW = 900;
	const HYPHA_LOGIN_MAX_FAILURES_PER_USER = 10;
	const HYPHA_LOGIN_MAX_FAILURES_PER_IP = 15;
	const HYPHA_RECOVERY_WINDOW = 3600;
	const HYPHA_RECOVERY_MAX_MAILS_PER_USER = 3;
	const HYPHA_RECOVERY_MAX_MAILS_PER_IP = 20;
	const HYPHA_THROTTLE_FILE = 'data/hypha.throttle';
	// Events older than this are dropped from the file
	const HYPHA_THROTTLE_MAX_AGE = 3600;

	/*
		Function: hypha_throttleCount
		Returns the number of events registered for $bucket in the
		last $window seconds. Buckets are stored hashed, so the
		file holds no usernames or addresses.
	*/
	function hypha_throttleCount($bucket, $window) {
		if (!file_exists(HYPHA_THROTTLE_FILE))
			return 0;
		$file = new HyphaFile(HYPHA_THROTTLE_FILE);
		$data = json_decode((string)$file->read(), true);
		$key = hash('sha256', $bucket);
		if (!is_array($data) || !isset($data[$key]) || !is_array($data[$key]))
			return 0;
		$since = time() - $window;
		return count(array_filter($data[$key], function($time) use ($since) { return $time > $since; }));
	}

	/*
		Function: hypha_throttleRegister
		Registers an event for $bucket, and drops expired events.
	*/
	function hypha_throttleRegister($bucket) {
		hypha_throttleUpdate($bucket, true);
	}

	/*
		Function: hypha_throttleClear
		Removes all events for $bucket, e.g. after a successful
		login.
	*/
	function hypha_throttleClear($bucket) {
		hypha_throttleUpdate($bucket, false);
	}

	function hypha_throttleUpdate($bucket, $add) {
		$file = new HyphaFile(HYPHA_THROTTLE_FILE);
		$data = json_decode((string)$file->lockAndRead(), true);
		if (!is_array($data))
			$data = [];
		$now = time();
		$key = hash('sha256', $bucket);
		if ($add) {
			if (!isset($data[$key]) || !is_array($data[$key]))
				$data[$key] = [];
			$data[$key][] = $now;
		} else {
			unset($data[$key]);
		}
		foreach ($data as $k => $times) {
			$times = is_array($times) ? $times : [];
			$times = array_filter($times, function($time) use ($now) { return $time > $now - HYPHA_THROTTLE_MAX_AGE; });
			// Bound the size; the limits are far below this.
			$times = array_slice(array_values($times), -200);
			if ($times)
				$data[$k] = $times;
			else
				unset($data[$k]);
		}
		$file->writeAndUnlock(json_encode($data));
	}
