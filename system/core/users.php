<?php
//	include_once('base.php');
	include_once('events.php');

	/*
		Title: Users

		This chapter describes the login mechanism and guest editors.

		this module requires two element to exist in the HTMLDocument, 'login' and 'menu'
	*/

	/*
		Function: loadUser
		sets UI language according to user preference and returns DOMElement containing user data

		Parameters:
		$id
	*/
	function loadUser($id) {
		global $hyphaUser;

		if ($id) $hyphaUser = hypha_getUserById($id);
		else $hyphaUser = false;

		if ($hyphaUser) loadUserInterfaceLanguage('system/languages', $hyphaUser->getAttribute('language'));
		else loadUserInterfaceLanguage('system/languages', hypha_getDefaultLanguage());
	}

	/*
		Function: login
		logs in user, setting SESSION variable hyphaLogin. Returns 'reload' on success.
	*/
	registerCommandCallback('login', 'login');
	function login() {
		$user = hypha_getUserByName($_POST['loginUsername']);
		if ($user && $user->getAttribute('rights') != 'exmember' && verifyPassword($_POST['loginPassword'], $user->getAttribute('password'))) {
			// Use a brand new session id for extra security
			session_regenerate_id();
			$_SESSION['hyphaLogin'] = $user->getAttribute('id');
			return 'reload';
		}
		else {
			unset($_SESSION['hyphaLogin']);
			notify('error', __('login-failed').'. <a href="javascript:reregister();">'.__('reregister').'</a>');
		}
	}

	/*
		Function: logout
		logs out user, unsetting SESSION variable 'hyphaLogin'. Reloads page.
	*/
	registerCommandCallback('logout', 'logout');
	function logout() {
		global $hyphaQuery;

		$arg = explode('/', $hyphaQuery);
		$language = $arg[0];
		$pagename = $arg[1];
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
		unset($_SESSION['hyphaLogin']);
		return 'reload';
	}

	/*
		Function: isUser
		returns true if user is logged in.
	*/
	function isUser() {
		return isset($_SESSION['hyphaLogin']) ? true : false;
	}

	/*
		Function: isAdmin
		returns true if user has admin rights.
	*/
	function isAdmin() {
		if (!isUser()) return false;
		if (hypha_getUserById($_SESSION['hyphaLogin'])->getAttribute('rights') !== 'admin') return false;
		return true;
	}

	/*
		Function: getUserEmailList
		returns a comma separated list of all registered users, omiting invitees and exmembers
	*/
	function getUserEmailList() {
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
		global $hyphaQuery;
		ob_start();
		/*
			Function: login
			writes login element

			Function: reregister
			writes login element
		*/
?>
	function login() {
		html = '<table class="section">';
		html+= '<tr><th>Username:</th><td><input name="loginUsername" id="loginUsername" type="text" size="10" /></td></tr>';
		html+= '<tr><th>Password:</th><td><input name="loginPassword" type="password" size="10" /></td></tr>';
		html+= '<tr><td></td><td><input type="submit" name="login" value="<?=__('login')?>" onclick="hypha(\'<?=$hyphaQuery?>\', \'login\', \'\');" /><input type="button" name="cancel" value="<?=__('cancel')?>" onclick="document.getElementById(\'popup\').style.visibility=\'hidden\';" /></td></tr>';
		html+= '</table>';
		document.getElementById('popup').innerHTML = html;
		document.getElementById('popup').style.left = document.getElementById('hyphaCommands').offsetLeft + 'px';
		document.getElementById('popup').style.top = (document.getElementById('hyphaCommands').offsetTop + 25) + 'px';
		document.getElementById('popup').style.visibility = 'visible';
		document.getElementById('loginUsername').focus();
	}
	function reregister() {
		document.getElementById('command').value = 'reregister';
		document.getElementById('popup').innerHTML = '<?=__('name-or-email')?>: <input name="searchLogin" id="searchLogin" type="text" size="10" /><input type="submit" name="submit" value="<?=__('submit')?>" /><input type="button" name="cancel" value="<?=__('cancel')?>" onclick="showLogin();" />';
		document.getElementById('searchLogin').focus();
	}
<?php
		$html->writeScript(ob_get_clean());
	}

	/*
		Function: reregister
		search for user based on a search string and send notification by email.
	*/
	function reregister($search) {
		global $hyphaUrl;
		foreach(hypha_getUserList() as $user) if ($user->getAttribute('username')==$search || $user->getAttribute('email')==$search) {
			$key = uniqid(rand(), true);
			addHyphaUserRegistrationKey($user, $key);
			$mailBody = __('reregister-to').'\''.hypha_getTitle().'\'. '.__('follow-link-to-register').'<br /><a href="'.$hyphaUrl.'settings/register/'.$key.'">'.$hyphaUrl.'settings/register/'.$key.'</a>';
			$result = sendMail($user->getAttribute('email'), hypha_getTitle().' <'.hypha_getEmail().'>', __('reregistration').'\''.hypha_getTitle().'\'', nl2br($mailBody));
			if ($result) notify('error', $result);
			else notify('success',  __('reregistration-sent'));
			return false;
		}
		notify('success', __('reregistration-error'));
	}
?>
