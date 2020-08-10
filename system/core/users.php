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
	function addLoginRoutine($html) {
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
		html = '<div class="login-wrapper">';
		html+= '<div class="username"><label class="username" for="loginUsername"><?=__('login-username')?>:</label><input name="loginUsername" id="loginUsername" type="text" /></div>';
		html+= '<div class="password"><label class="password" for="loginPassword"><?=__('login-password')?>:</label><input name="loginPassword" id="loginPassword" type="password" /></td></div>';
		html+= '<div class="submit"><input class="button" type="submit" name="login" value="<?=__('login')?>" onclick="hypha(\'<?=$currentUrlPath?>\', \'login\', \'\', $(this).closest(\'form\'));" /></div>';
		html+= '<div class="cancel"><input class="button" type="button" name="cancel" value="<?=__('cancel')?>" onclick="document.getElementById(\'popup\').style.display=\'none\';" /></div>';
		html+= '<div class="forgot-password"><?=__('forgot-password')?><a href="javascript:reregister();"><?=__('reregister')?></a></div>';
		html+= '</div>';
		document.getElementById('popup').innerHTML = html;
		document.getElementById('popup').style.display = 'block';
		document.getElementById('loginUsername').focus();
	}
	function reregister() {
		html = '<table class="section">';
		html+= '<tr><th><?=__('name-or-email')?></th><td><input name="searchLogin" id="searchLogin" type="text" size="10" /></td></tr>';
		html+= '<tr><td></td><td><input type="submit" name="submit" value="<?=__('submit')?>" onclick="hypha(\'<?=$currentUrlPath?>\', \'reregister\', document.getElementById(\'searchLogin\').value, $(this).closest(\'form\'));" /><input type="button" name="cancel" value="<?=__('cancel')?>" onclick="showLogin();" /></td></tr>';
		html+= '</table>';
		document.getElementById('popup').innerHTML = html;
		document.getElementById('popup').style.display = 'block';
		document.getElementById('searchLogin').focus();
	}
<?php
		$html->writeScript(ob_get_clean());
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
