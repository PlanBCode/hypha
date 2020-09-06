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
		- *session* When the main script is run it first initializes the session data through session_start(); This is a standard php command to administer data associated with a certain client, e.g. if a user is logged in. When no action is noticed from the client side within a certain time the data is dropped. In the above example the user will be considered logged out. For hypha, the login state is stored in the variable $_SESSION['hyphaLogin']
		- *errors* Switch off php error reporting to the client.
		- *sanity check* Check if the version of php is compatible with our script.
		- *code scan* Client data sent through $_GET or $_POST variables are scanned for malicious code injection before continuing to the main script.
		- *email obfuscation* Email addresses are obfuscated to prevent spambots from harvesting published email addresses. This function is actually registered later on once all core scripts are loaded. See below...
	*/

	$DEBUG = file_exists('DEBUG');
	ini_set('display_errors', $DEBUG ? true : false);

	if (strnatcmp(phpversion(),'5.4') < 0) die('Error: you are running php version '.substr(phpversion(),0,strpos(phpversion(), '-')).'; Hypha works only with php version 5.4 and higher');
	if (function_exists('apache_get_modules') && !in_array('mod_rewrite', apache_get_modules())) die ('Error: Apache should have mod_rewrite enabled');

	/*
		Group: Stage 2 - Build hypha script
		Additional scripts and data are included.
		- *core* Include core scripts
		- *languages* Compile a list of available user interface languages
		- *dataypes* Include modules for available datatypes: textpage, mailinglist, blog et cetera
	*/

	foreach (scandir("system/core/") as $_file) if (substr($_file, -4) == '.php') require_once("system/core/" . $_file);

	// Build request
	$hyphaRequest = new HyphaRequest();

	// Build request context
	$O_O = new RequestContext($hyphaRequest);

	// called for its side effects of loading the datatypes
	hypha_getDataTypes();

	/*
		Group: Stage 3 - Get query and handle direct file requests
		Here we extract our page query from the php $_SERVER data. Page requests should be formatted in a directory like structure, e.g. http://www.dom.ain/en/home/edit, the first level usually being a language id, the second level begin a page id, and the third level being an optional view.
		Some requests don't need the whole script to run. Certain file requests (css, javascript) can be handled directly here.
	*/

	$hyphaQuery = $hyphaRequest->getRelativeUrlPath();
	$hyphaUrl = $hyphaRequest->getRootUrl();

	// Shortcut for direct file requests
	if (startsWith($hyphaQuery, 'data/themes/')) serveFile($hyphaQuery, 'data/themes');
	if (startsWith($hyphaQuery, 'system/wymeditor')) serveFile($hyphaQuery, 'system/wymeditor');
	if (startsWith($hyphaQuery, 'system/bowser')) serveFile($hyphaQuery, 'system/bowser');
	if (startsWith($hyphaQuery, 'system/assets')) serveFile($hyphaQuery, 'system/assets');

	// jump to installation procedure when hypha.xml is missing
	if (!is_file('data/hypha.xml')) header('Location: ' . $hyphaUrl . 'hypha.php');

	/*
		Group: Stage 4 - Load website data
		Website data is loaded, see chapter about <Base> functions. The HTMLDocument $hyphaHtml is loaded with the website default layout.
	*/

	$hyphaHtml = new HTMLDocument($O_O->data->themeHtml);
	$hyphaHtml->initForBrowser($hyphaUrl);
	$hyphaHtml->linkStyle('system/assets/hypha-core.css');
	$hyphaHtml->linkStyle($O_O->data->themeCss->getFilename());
	$hyphaHtml->linkScript('system/assets/jquery-1.7.1.min.js');
	$hyphaHtml->linkScript('system/assets/help.js');
	$hyphaHtml->linkScript('system/assets/hypha-preview-image-file-field.js');
	$hyphaHtml->setTitle(hypha_getTitle());
	$hyphaHtml->writeToElement('header', hypha_getHeader());
	$hyphaHtml->writeToElement('footer', hypha_getFooter());
	$hyphaHtml->writeToElement('menu', hypha_getMenu());
	$defaultForm = new HTMLForm('<form id="hyphaForm" method="post" action="" accept-charset="utf-8" enctype="multipart/form-data" />');
	$hyphaHtml->setDefaultForm($defaultForm);

	/*
		Group: Stage 5 - Initialize user and determine language to use
		Check is a user is logged in and load user data. Add login/logout functionality according to session login status.
	*/

	/**
	 * Set user as global variable.
	 * @deprecated Use O_O instead
	 */
	$hyphaUser = $O_O->getUser();

	/**
	 * Set language as global variable.
	 * @deprecated Use O_O instead
	 */
	$hyphaLanguage = $O_O->getInterfaceLanguage();
	$locale = __('_locale');
	if ($locale !== '_locale') {
		setlocale(LC_TIME, $locale);
	}

	/**
	 * Set content language as global variable.
	 * @deprecated Use O_O instead
	 */
	$hyphaContentLanguage = $O_O->getContentLanguage();

	// Load page and execute the posted command. This
	// is tried twice, since some commands need to run before
	// loading the page, and some commands need to run after.
	executePostedCommand($O_O); // Might redirect and exit
	loadPage($O_O);
	executePostedCommand($O_O); // Might redirect and exit

	/*
		Group: Stage 6 - Load page and process query
		User and page classes are instantiated, and user commands executed.
		If a command was issued from the client side (using a $_POST variable 'command') the eventhandler is called to take the appropriate action. When necessary (e.g. when the page query implicated an update of the database, or the user state was changed) this step is reiterated until no more events have to be processed.
	*/

	if ($hyphaPage) processCommandResult($hyphaPage->process($O_O->getRequest()));

	registerPostProcessingFunction('dewikify');

	// add hypha commands and navigation
	$_cmds[] = '<a class="index" href="index/'.$hyphaLanguage.'">'.__('index').'</a>';
	if (!$O_O->isUser()) {
		addLoginRoutine($hyphaHtml);
		$_cmds[] = '<a class="login" href="javascript:login();">'.__('login').'</a>';
	}
	else {
		addNewPageRoutine($hyphaHtml, $hyphaRequest->getRelativeUrlPathParts(false), hypha_getDataTypes());
		$_cmds[] = makeLink(__('new-page'), 'newPage();', 'newPage');
		$_cmds[] = makeLink(__('settings'), makeAction('settings', '', ''), 'settings');
		$_cmds[] = makeLink(__('logout'), makeAction($hyphaRequest->getRelativeUrlPath(false),'logout',''), 'logout');
	}
	// A class is used by default, but also look for the
	// #hyphaCommands id which was used in earlier versions.
	$hyphaHtml->find('.hyphaCommands, #hyphaCommands')->html(implode('', $_cmds));
	if ($O_O->isUser()) $hyphaHtml->writeToElement('hyphaCommands', '<br/><div id="loggedIn">'.__('logged-in-as').' `'.$O_O->getUser()->getAttribute('username').'`'.asterisk(isAdmin()).'</div>');

	// obfuscate email addresses to strangers. It's ok to send readable addresses to logged in members. This also prevents conflicts in the editor.
//	if (!$O_O->isUser()) registerPostProcessingFunction('obfuscateEmail');

	if ($hyphaPage) hypha_setBodyClass($hyphaRequest, $hyphaPage);
	if ($hyphaPage) hypha_setPreviewPanel($O_O, $hyphaPage);

	// Add the default form. This does not happen earlier, since
	// appending makes a copy of the form, so now we can be sure
	// that all changes (if any) to the form are taken into account.
	$hyphaHtml->find('body')->children()->wrapAll($hyphaHtml->getDefaultForm());

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
