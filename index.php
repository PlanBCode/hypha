<?php
	// Hypha is intended to support groups who work on a project, and is distributed under a Simple Public License 2.0 from www.hypha.net

	/*
		Section: 2. Core script: index.php
		Basic operation flow

		Array: $phpPostProcessing
		Array containing functions to be called just before sending the document to the client.
		All scripts can stage final modifications to the final document. E.g. the email obfuscation scheme should be applied only after the full document is constructed from the various modules. This mechanism is used also for inserting the editor code-chain when the final document needs it. The functions added to this array should take an instance of the class <HTMLDocument> as argument.
	*/

	/*
		Group: Stage 1 - Security
		Basic security is obtained in a number of ways.
		- *htaccess* All http requests (pages, images, ajax calls, whatever...) to the folder where hypha resides or one of its subfolder are directed to this script (index.php) by a RewriteRule in .htaccess. One exception is made for calls to hypha.php, our maintenance script. In this way we can shield off direct access to data files.
		- *session* When the main script is run it first initializes the session data through session_start(); This is a standard php command to administer data associated with a certain client, e.g. if a user is logged in. When no action is noticed from the client side within a ceratin time the data is dropped. In the above example the user will be considered logged out. For hypha, the login state is stored in the variable $_SESSION['hyphaLogin']
		- *errors* Switch off php error reporting to the client.
		- *sanity check* Check if the version of php is compatible with our script.
		- *code scan* Client data sent through $_GET or $_POST variables are scanned for malicious code injection before continuing to the main script.
		- *email obfuscation* Email addresses are obfuscated to prevent spambots from harvesting published email addresses. This function is actually registered later on once all core scripts are loaded. See below...
	*/

	// Load the session and close it immediately (there does not
	// seem to be any way to do this without also writing out the
	// session file with PHP < 5.6). This prevents the session file
	// from staying locked throughout the entire request and
	// prevents serving multiple requests within the same session.
	session_start();
	session_write_close();

	$DEBUG = false;
	error_reporting($DEBUG ? E_ALL ^ E_NOTICE : NULL);

	if (strnatcmp(phpversion(),'5.4') < 0) die('Error: you are running php version '.substr(phpversion(),0,strpos(phpversion(), '-')).'; Hypha works only with php version 5.4 and higher');
	if (function_exists('apache_get_modules') && !in_array('mod_rewrite', apache_get_modules())) die ('Error: Apache should have mod_rewrite enabled');

	/*
		Group: Stage 2 - Get query and handle direct file requests
		Here we extract our page query from the php $_SERVER data. Page requests should be formatted in a directory like structure, e.g. http://www.dom.ain/en/home/edit, the first level usually being a language id, the second level begin a page id, and the third level being an optional view.
		Some requests don't need the whole script to run. Certain file requests (css, javascript) can be handled directly here.
	*/

	$_path = substr($_SERVER["PHP_SELF"], 0, strpos($_SERVER["PHP_SELF"], 'index.php'));
	$hyphaUrl = 'http://'.$_SERVER["SERVER_NAME"].$_path;
	$hyphaQuery = substr($_SERVER["REQUEST_URI"], strlen($_path));
//	$hyphaQuery = urldecode(substr($_SERVER["REQUEST_URI"], strlen($_path)));


	/*
		Group: Stage 3 - Build hypha script
		Additional scripts and data are included.
		- *core* Include core scripts
		- *dataypes* Include modules for available datatypes: textpage, mailinglist, blog et cetera
		- *languages* Compile a list of available user interface languages
	*/

	$_handle = opendir("system/core/");
	while ($_file = readdir($_handle)) if (substr($_file, -4) == '.php') require_once("system/core/".$_file);
	closedir($_handle);

	$_handle = opendir("system/datatypes/");
	while ($_file = readdir($_handle)) if (substr($_file, -4) == '.php') include_once("system/datatypes/".$_file);
	closedir($_handle);

	$_handle = opendir("system/languages/");
	while ($_file = readdir($_handle)) if (substr($_file, -4) == '.php') $uiLangList[] = basename($_file, '.php');
	closedir($_handle);

	// Shortcut for direct file requests
	if ($hyphaQuery == 'data/hypha.css')
		serveFile($hyphaQuery, false);
	if (startsWith($hyphaQuery, 'system/wymeditor'))
		serveFile($hyphaQuery, 'system/wymeditor');

	/*
		Group: Stage 4 - Load website data
		Website data is loaded, see chapter about <Base> functions. The HTMLDocument $hyphaHtml is loaded with the website default layout.
	*/

	$hyphaHtml = new HTMLDocument('data/hypha.html');
	$hyphaHtml->linkStyle($hyphaUrl.'data/hypha.css');
	$hyphaHtml->setTitle(hypha_getTitle());
	$hyphaHtml->writeToElement('header', hypha_getHeader());
	$hyphaHtml->writeToElement('footer', hypha_getFooter());
	$hyphaHtml->writeToElement('menu', hypha_getMenu());

	/*
		Group: Stage 5 - Initialize user and determine language to use
		Check is a user is logged in and load user data. Add login/logout functionality according to session login status.
	*/

	// load user and requested page, and execute issued commands
	do {
		loadUser($_SESSION['hyphaLogin']);
		loadPage(explode('/', $hyphaQuery));
	} while (executePostedCommand() == 'reload');

	/*
		Group: Stage 6 - Load page and process query
		User and page classes are instantiated, and user commands executed.
		If a command was issued from the client side (using a $_POST variable 'command') the eventhandler is called to take the appropriate action. When necessary (e.g. when the page query implicated an update of the database, or the user state was changed) this step is reiterated until no more events have to be processed.
	*/

	if ($hyphaPage) $hyphaPage->build();

	registerPostProcessingFunction('dewikify');

	// add hypha commands and navigation
	$_cmds[] = '<a href="index/'.$hyphaLanguage.'">'.__('index').'</a>';
	if (!$hyphaUser) {
		addLoginRoutine($hyphaHtml);
		$_cmds[] = '<a href="javascript:login();">'.__('login').'</a>';
	}
	else {
		addNewPageRoutine($hyphaHtml, explode('/', $hyphaQuery), $hyphaPageTypes);
		$_cmds[] = makeLink(__('new-page'), 'newPage();');
		$_cmds[] = makeLink(__('settings'), makeAction('settings', '', ''));
		$_cmds[] = makeLink(__('logout'), makeAction($hyphaQuery, 'logout', ''));
	}
	$hyphaHtml->writeToElement('hyphaCommands', implode(' - ', $_cmds));
	if ($hyphaUser) $hyphaHtml->writeToElement('hyphaCommands', '<br/><span id="loggedIn">'.__('logged-in-as').' `'.$hyphaUser->getAttribute('username').'`'.asterisk(isAdmin()).'</span>');

	// obfuscate email addresses to strangers. It's ok to send readible addresses to logged in members. This also prevents conflicts in the editor.
//	if (!$hyphaUser) registerPostProcessingFunction('obfuscateEmail');

	// poor man's cron job
	if (time() - hypha_getLastDigestTime() >= hypha_getDigestInterval()) flushDigest();

	/*
		Group: Stage 7 - Output
		The page gets delivered.
		Finally, when all is said and done, the page is compiled into HTML and sent to the client.
	*/
	header('Content-Type: text/html; charset=utf-8');
	print $hyphaHtml->toString();
	exit;
?>
