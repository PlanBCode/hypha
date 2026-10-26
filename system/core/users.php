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
		$username = $O_O->getRequest()->getPostValue('loginUsername');
		$password = $O_O->getRequest()->getPostValue('loginPassword');
		$user = hypha_getUserByName($username);
		$sess = $O_O->getSession();
		$sess->lockAndReload();
		if ($user && $user->getAttribute('rights') != 'exmember' && verifyPassword($password, $user->getAttribute('password'))) {
			// Use a brand new session id for extra security
			$sess->changeSessionId();
			$sess->set('hyphaLogin', $user->getAttribute('id'));
			// Used by RequestContext to end the login after
			// a period of inactivity or a maximum duration.
			$sess->set('hyphaLoginTime', time());
			$sess->set('hyphaLastSeen', time());
			$O_O->regenerateCsrfToken();
		}
		else {
			$sess->remove('hyphaLogin');
			notify('error', __('login-failed').'. <a href="javascript:reregister();">'.__('reregister').'</a>');
		}
		$sess->writeAndUnlock();
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
		$sess->remove('hyphaLoginTime');
		$sess->remove('hyphaLastSeen');
		// Give the browser a new session id and delete the old
		// session, so a copy of the old session id (or CSRF
		// token) is of no use after logging out.
		$sess->changeSessionId();
		$sess->writeAndUnlock();
		$O_O->regenerateCsrfToken();
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
		$hyphaXml->lockAndReload();
		foreach(hypha_getUserList() as $user) if ($user->getAttribute('username')==$search || $user->getAttribute('email')==$search) {
			$key = bin2hex(openssl_random_pseudo_bytes(8));
			hypha_addUserRegistrationKey($user, $key);
			$hyphaXml->saveAndUnlock();
			$mailBody = __('reregister-to').'\''.hypha_getTitle().'\'. '.__('follow-link-to-register').'<br /><a href="'.$hyphaUrl.'settings/register/'.$key.'">'.$hyphaUrl.'settings/register/'.$key.'</a>';
			$result = sendMail($user->getAttribute('email'), __('reregistration').'\''.hypha_getTitle().'\'', nl2br($mailBody));
			if ($result) notify('error', $result);
			else notify('success',  __('reregistration-sent'));
			return 'reload';
		}
		$hyphaXml->unlock();
		notify('success', __('reregistration-error'));
	}
