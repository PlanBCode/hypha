<?php
	// HYPHA
	// hypha is a simple and user friendly cms with wiki and mailinglist functionality
	// it makes use of php5, javascript and xml and is distributed as a monolithic php script
	// the project homepage is www.hypha.net
	//
	// INSTALL
	// 1. add your superuser login name and password here:
	$username = '';
	$password = '';
	//
	// 2. save, and put this script into the webdirectory on your server (e.g. /home/yourname/domains/dom.ain/public_html/hypha.php)
	//
	// 3. open this script in a browser (e.g. www.dom.ain/hypha.php)
	//     on first run, the script will ask some questions and create the files and folders it needs for operation
	//
	// 4. your website is ready to use (www.dom.ain/index.php)
	//     you can now set up your website and invite others to join the project
	//
	// DOCUMENTATION
	// documentation can be generated using NaturalDocs:
	// 1. install NaturalDocs following the instructions on (www.naturalsdocs.org)
	// 2. run the following command in a terminal from the same folder hypha was installed to:
	// naturaldocs -i . -o FramedHTML ./doc -p ./doc
	// 3. open doc/index.html in a webbrowser
	//
	// LICENSE
	// the script was written by Harmen G. Zijp and is distributed under the Simple Public License 2.0
	// which can be found on http://www.opensource.org/licenses/simpl-2.0.html
	// acknowledgements:
	// - xhtml compliant javascript editor: wymeditor - http://www.wymeditor.org
	// - htmldiff routine by Paul Butler

	/*
		Section: 3. Systools: hypha.php

		The script hypha.php serves a number of purposes.

		Installer:
		First of all it's the installer script containing everything that's needed for a fully functional hypha website. All files needed are stored in the script as strings, using gz compression and base64 encoding to convert them into manageable strings. The script will check if an installation is present already and if not install the files in the appropriate folders. Next it will present a screen asking for information needed to finish the installation, like administrator name and password, default language et cetera.

		Maintenance:
		Secondly, the script is used as maintenance script. Users with admin rights will find a button 'system tools' in their settings page. This will make hypha leave the core script (index.php) and launch hypha.php?maintenance in stead. This presents the user a list of hypha files and checks hypha.net for available datatype modules and newer versions of system files. This is done through a call to the same script running on the website www.hypha.net (http://www.hypha.net/hypha.php?file=index), which returns a list of available files along with their creation date. It the timestamp is newer than the listed file's creation date, it is offered for download. Admins can choose which files to update.

		File server:
		Thirdly, the script is used to serve files to other hypha installs. Calling hypha.php?file=filename will return the requested file as a gz compressed base64 encoded string. A special call is hypha.php?file=index, which returns a list of all hypha files with timestamps of their creation. This function of the script is used in conjunction with the maintenance function. One hypha.php script running in maintenance mode requests files from another hypha.php script running on another server (by default this is www.hypha.net but it can be set to any other url with a hypha install).

		Builder:
		Fourthly, the script can be used to build a new hypha.php containing the core scripts together with a number of additional classes for a list of chosen datatypes. This hypha.php script is offered for download and can be used again as an installer script for a new hypha install.
	*/

	/*
		Function: isAllowedFile
		check if file may be shared (don't send site contents, user data or configuration settings)

		Parameters:
			$file - filename
	*/
	function isAllowedFile($file) {
		$path = pathinfo($file);
		$dir = preg_replace('#[\./]*(.*)/*#', '$1', $path['dirname']);
		$file = $path['basename'];
		if ($dir == '' && $file == '.htaccess') return true;
		if ($dir == '' && $file == 'index.php') return true;
		if ($dir == '' && $file == 'documentation.txt') return true;
		if ($dir == 'data' && $file == 'hypha.html') return true;
		if ($dir == 'data' && $file == 'hypha.css') return true;
		if ($dir == 'system/core') return true;
		if ($dir == 'system/datatypes') return true;
		if ($dir == 'system/languages') return true;
		if ($dir == 'system/php-dom-wrapper') return true;
		if ($dir == 'system/wymeditor') return true;
		return false;
	}

	/*
		Function: index
		compiles an array with hypha's system files and their creation timestamp

		Parameters:
			$dir - directory needed for the recursive operation of this function
	*/
	function index($dir = '.') {
		$index = array();
		if ($handle = opendir($dir)) {
			while (false !== ($file = readdir($handle))) {
				if ($file != "." && $file != "..") {
					if (isAllowedFile($dir.'/'.$file)) $index[preg_replace("/^\.\//i","",$dir.'/'.$file)] = filemtime($dir.'/'.$file);
					elseif (is_dir($dir.'/'.$file)) $index = array_merge($index, index($dir.'/'.$file));
				}
			}
			closedir($handle);
		}
		return $index;
	}

	/*
		Function: buildzip
		builds a hypha.php containing all system files as zipped strings and sends it to client for download
	*/
	function buildzip() {
		// make list of files to include
		$files = array_keys(index());
		foreach($_POST as $p => $v) if (substr($p, 0, 6) == 'build_') $files[] = substr($p, 6).'.php';

		// get the base script to modify
		$hypha = file_get_contents('hypha.php');

		// insert superuser name and password
		$hypha = preg_replace('/\$username = \'.*?\';/', '\$username = \''.$_POST['username'].'\';', $hypha);
		$hypha = preg_replace('/\$password = \'.*?\';/', '\$password = \''.$_POST['password'].'\';', $hypha);

		// build data library of zipped files to include
		$data = "			//START_OF_DATA\n";
		$data .= '			case \'index\': $zip = "'.base64_encode(gzencode(implode(',', array_keys(index())), 9)).'"; break;'."\n";
		foreach ($files as $file) $data.= '			case \''.$file.'\': $zip = "'.base64_encode(gzencode(file_get_contents($file), 9)).'"; break;'."\n";
		$data .= "			//END_OF_DATA\n";

		// include data library
		$hypha = preg_replace('#\t*//START_OF_DATA\n.*//END_OF_DATA\n#ms', $data, $hypha);

		// push script to client
		header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment; filename="hypha.php"');
		echo $hypha;
		exit;
	}

	/*
		Function: tools
		generates a html table with all the system files and actions on them depending on possible changes between the local file and the version on a remote hypha

		Parameters:
			$hyphaServer - url of remove hypha
	*/
	function tools($hyphaServer) {
		global $errorMessage;
		switch($_POST['command']) {
			case 'add':
				$data = gzdecode(base64_decode(file_get_contents($hyphaServer.'?file='.$_POST['argument'])));
				if ($data) {
					file_put_contents($_POST['argument'], $data);
					chmod($_POST['argument'], 0664);
				}
				else $errorMessage.= 'file transfer failed<br/>';
				break;
			case 'remove':
				unlink($_POST['argument']);
				break;
		}
		$localIndex = index();
		$serverIndex = unserialize(file_get_contents($hyphaServer.'?file=index'));
		ksort($serverIndex);
		ob_start();
?>
			<div style="width:600px;">
				<input type="submit" name="command" value="back" style="float:right;" />
				<h1>hypha system tools</h1>
				Here you can manage the files of your hypha configuration.
				<ul>
					<li><b>install</b> install a (datatype) module that available on the hypha server but is currently not installed on your system.</li>
					<li><b>remove</b> remove a (datatype) module that is currently installed on your system.</li>
					<li><b>update</b> update a file on your system with a newer version available from the hypha server.</li>
					<li><b>reset</b> your file is newer than the version on the hypha server. You can use this option to revert to the older version. This can be useful when a file got broken or some css code was messed up.</li>
				</ul>
			</div>
			<table class="section">
				<tr><th style="text-align:left">file</th><th colspan="2">action</th></tr>
<?php
		foreach ($serverIndex as $file => $info) {
			echo '<tr><td style="white-space:nowrap;">'.$file.'</td>';
			if (array_key_exists($file, $localIndex)) {
				echo '<td><input type="button" value="'.($localIndex[$file] < $serverIndex[$file] ? 'update' : 'reset').'" onclick="hypha(\'add\', \''.$file.'\');" /></td>';
				$path = pathinfo($file);

				$dir = explode('/', $path['dirname']);
				while (isset($dir[0]) && $dir[0] == '.') array_shift($dir);
				$level0 = isset($dir[0]) ? $dir[0] : false;
				$level1 = isset($dir[1]) ? $dir[1] : false;
				if ($level0 == 'system' && ($level1=='datatypes' || $level1=='languages') && $path['basename']!='settings.php')
				echo '<td><input type="button" value="remove" onclick="hypha(\'remove\', \''.$file.'\');" /></td>';
				else echo '<td></td>';
			}
			else echo '<td></td><td><input type="button" value="install" onclick="hypha(\'add\', \''.$file.'\');" /></td>';
			echo '</tr>';
		}
		echo '</table>';
		return ob_get_clean();
	}

	/*
		Function: install
		install new hypha
	*/
	function install() {
		global $errorMessage;

		if ($_POST['command'] == 'install' && is_writable('.')) {
			// upzip and install files
			$files = explode(',', data('index'));
			foreach ($files as $file) {
				if (!$file)
					continue;
				$path = pathinfo($file);
				$dir = explode('/', $path['dirname']);
				$folder = '';
				foreach($dir as $d) {
					$folder.= ($folder ? '/' : '').$d;
					if (!file_exists($folder)) mkdir($folder, 0755);
				}
				file_put_contents($file, data($file));
				chmod($file, 0664);
			}

			// Needed for password hashing
			require_once('system/core/crypto.php');

			// create data folders
			mkdir('data/pages', 0755);
			mkdir('data/images', 0755);

			// create default page
			$id = uniqid();
			$xml = new DomDocument('1.0', 'UTF-8');
			$xml->preserveWhiteSpace = false;
			$xml->formatOutput = true;
			$hypha = $xml->createElement('hypha');
			$hypha->setAttribute('type', 'textpage');
			$hypha->setAttribute('multiLingual', 'on');
			$hypha->setAttribute('versions', 'on');
			$hypha->setAttribute('schemaVersion', 1);
			$language = $xml->createElement('language', '');
			$language->setAttribute('xml:id', $_POST['setupDefaultLanguage']);
			$version = $xml->createElement('version', '<p>welcome to your brand new hypha website.</p>');
			$version->setAttribute('xml:id', 't'.time());
			$version->setAttribute('author', '');
			setNodeHtml($version, '<p>welcome to your brand new hypha website.</p>');
			$language->appendChild($version);
			$hypha->appendChild($language);
			$xml->appendChild($hypha);
			$xml->save('data/pages/'.$id);

			// build hypha.xml
			$xml = new DomDocument('1.0', 'UTF-8');
			$xml->preserveWhiteSpace = false;
			$xml->formatOutput = true;
			$hypha = $xml->createElement('hypha');
			$hypha->setAttribute('type', 'project');
			$hypha->setAttribute('defaultLanguage', $_POST['setupDefaultLanguage']);
			$hypha->setAttribute('defaultPage', $_POST['setupDefaultPage']);
			$hypha->setAttribute('email', $_POST['setupEmail']);
			$hypha->setAttribute('digestInterval', '21600');
			$hypha->setAttribute('schemaVersion', 1);
			$hypha->appendChild($xml->createElement('title', $_POST['setupTitle']));
			$header = $xml->createElement('header', $_POST['setupTitle']);
			$hypha->appendChild($header);
			$footer = $xml->createElement('footer', '');
			setNodeHtml($footer, '<a href="http://creativecommons.org/licenses/by-sa/3.0/"><img alt="Creative Commons License" style="border-width: 0pt; float: right; margin-left: 5px;" src="//i.creativecommons.org/l/by-sa/3.0/88x31.png" /></a> This work is licensed under a <a href="http://creativecommons.org/licenses/by-sa/3.0/">Creative Commons Attribution-ShareAlike 3.0 Unported License</a>. Website powered by <a href="http://www.hypha.net">hypha</a>.');
			$hypha->appendChild($footer);
			$menu = $xml->createElement('menu', '');
			setNodeHtml($menu, '<a href="hypha:'.$id.'"/>');
			$hypha->appendChild($menu);
			$userList = $xml->createElement('userList', '');
			$user = $xml->createElement('user', '');
			$user->setAttribute('id', uniqid());
			$user->setAttribute('username', $_POST['setupUsername']);
			$user->setAttribute('password', hashPassword($_POST['setupPassword']));
			$user->setAttribute('fullname', $_POST['setupFullname']);
			$user->setAttribute('email', $_POST['setupEmail']);
			$user->setAttribute('rights', 'admin');
			$userList->appendChild($user);
			$hypha->appendChild($userList);
			$pageList = $xml->createElement('pageList', '');
			$page = $xml->createElement('page', '');
			$page->setAttribute('id', $id);
			$page->setAttribute('type', 'textpage');
			$page->setAttribute('private', 'off');
			$language = $xml->createElement('language', '');
			$language->setAttribute('id', $_POST['setupDefaultLanguage']);
			$language->setAttribute('name', $_POST['setupDefaultPage']);
			$page->appendChild($language);
			$pageList->appendChild($page);
			$hypha->appendChild($pageList);
			$hypha->appendChild($xml->createElement('digest', ''));
			$xml->appendChild($hypha);
			$xml->save('data/hypha.xml');

			// goto hypha site
			header('Location: '.$_POST['setupBaseUrl']);
			exit;
		}
		else {
			if (!is_writable('.')) $errorMessage.= 'php has no write access to the hypa installation directory on the server<br/>';

			// extract list of languages
			$iso639 = json_decode(data('system/languages/languages.json'), true);
			$languageOptionList = '';
			foreach($iso639 as $code => $langName) $languageOptionList.= '<option value="'.$code.'"'.( $code=='en' ? ' selected' : '').'>'.$code.': '.$langName.'</option>';

			// build html
			ob_start();
?>
		<div style="width:800px;">
			<h1>hypha installer</h1>
			In order to set up this new hypha site you need to fill out a few things. This is a once only procedure. The entered data will be used to create some folders, include scripts and config files in the folder where the hypha script resides.
			<h2>system configuration</h2>
			The following settings are used to set up your project website. All settings can be changed after the initial installation.<br/>
			<table style="width:800px;" class="section">
				<tr><td width="240"></td><td></td><td width="400"></td></tr>
				<tr><th>title</th><td><input name="setupTitle" size="40" value="<?=$_POST['setupTitle'];?>" /></td><td class="help">The name of your site as it will appear in the browers title bar.</td></tr>
				<tr><th>base url</th><td><input name="setupBaseUrl" size="40" value="http://<?=$_SERVER["SERVER_NAME"].str_replace('/hypha.php', '', $_SERVER["REQUEST_URI"])?>" /></td><td class="help">This is the address where the hypha site can be found.</td></tr>
				<tr><th>default page</th><td><input name="setupDefaultPage" size="40" value="home" value="<?=$_POST['setupDefaultPage'];?>" /></td><td class="help">Hypha lets you create wiki style pages which can link to each other. If no particular page is selected the site will default to this page.</td></tr>
				<tr><th>default language</th><td><select name="setupDefaultLanguage"><?=$languageOptionList?></select></td><td class="help">All pages can be multilingual. If a user does not choose a language the site will default to this language.</td></tr>
				<tr><th>login</th><td><input name="setupUsername" size="40" value="<?=$_POST['setupUsername'];?>" /></td><td class="help">This first user account will automatically have admin rights for site maintenance.</td></tr>
				<tr><th>password</th><td><input name="setupPassword" size="40" value="<?=$_POST['setupPassword'];?>" /></td><td class="help">The superuser password.</td></tr>
				<tr><th>full name</th><td><input name="setupFullname" size="40" value="<?=$_POST['setupFullname'];?>" /></td><td class="help">This name will be used for email messages generated by  the system.</td></tr>
				<tr><th>email</th><td><input name="setupEmail" size="40" value="<?=$_POST['setupEmail'];?>" /></td><td class="help">This email adress will be the reply-to address of system sent messages. You may want to use an email alias that forwards messages to all collaborators in the group.</td></tr>
				<tr><td colspan="3" style="text-align:right;"><input type="submit" name="command" value="install" /></td></tr>
			</table>
		</div>
<?php
			return ob_get_clean();
		}
	}

	/*
		Function: login
		login form
	*/
	function login() {
		ob_start();
?>
			<div style="width:600px;">
				<h1>hypha superuser login</h1>
				Please log in with the superuser account. In case you forgot the password you'll have to look it up in the file 'hypha.php' on your server.
			</div>
			<table class="section">
				<tr><th style="text-align:right; white-space:nowrap;">username:</th><td><input id="username" name="username" type="text" /></td></tr>
				<tr><th style="text-align:right; white-space:nowrap;">password:</th><td><input name="password" type="password" /></td></tr>
				<tr><td colspan="2" style="text-align:right;"><input type="submit" name="command" value="login" /></td></tr>
			</table>
<?php
		return ob_get_clean();
	}

	/*
		Function: build
		produces html page for selecting hypha modules and languages to package in a new hypha.php install script
	*/
	function build() {
		$localIndex = index();
		arsort($localIndex);
		ob_start();
?>
			<div style="width:600px;">
				<h1>hypha builder</h1>
				Here you can compose your own hypha system.
				<ol>
					<li>First you have to enter a name and password for the so called 'superuser' account.</li>
					<li>Then you can select a number of modules for different data types you want to incorporate in your hypha system.</li>
					<li>When you click 'build' your superuser account and selection of modules will be packed into one php file ('hypha.php') which is offered for download.</li>
					<li>Upload the downloaded file into a webdirectory on your server and make shure php has write access to the folder. Open the file in a browser. You will be asked to login as superuser and after setting up some variables hypha will be installed.</li>
				</ol>
			</div>
			<table class="section">
				<tr><th style="text-align:right; white-space:nowrap;">superuser name:</th><td><input id="username" name="username" type="text" /></td></tr>
				<tr><th style="text-align:right; white-space:nowrap;">superuser password:</th><td><input name="password" type="password" /></td></tr>
				<tr name="datatype">
					<th style="text-align:right; white-space:nowrap;">modules to include:</th><td>
<?php
		foreach ($localIndex as $file => $timestamp) {
			if (substr($file, 0, 17) == 'system/datatypes/') {
				$name = substr($file, 17, -4);
				if ($name!='settings') echo '<input type="checkbox" name="build_'.substr($file,0,-4).'"'.($name == 'text' ? ' checked="checked"' : '').' /> '.$name.'<br/>'."\n";
			}
		}
?>
				</td></tr>
				<tr name="language">
					<th style="text-align:right; white-space:nowrap;">languages to include:</th><td>
<?php
		foreach ($localIndex as $file => $timestamp) {
			if (substr($file, 0, 17) == 'system/languages/' && substr($file, -4) == '.php') {
				$name = substr($file, 17, -4);
				echo '<input type="checkbox" name="build_'.substr($file,0,-4).'"'.($name == 'en' ? ' checked="checked"' : '').' /> '.$name.'<br/>'."\n";
			}
		}
?>
				</td></tr>
				<tr><td colspan="2" style="text-align:right;"><input type="submit" id="gobutton" name="command" value="build"></td></tr>
			</table>
<?php
		return ob_get_clean();
	}

	/*
		Function: setNodeHtml
		convenience function to write inner HTML content to a XML node, and suppress warnings from html parser
	*/
	function setNodeHtml($node, $content) {
		while ($node->childNodes->length) $node->removeChild($node->childNodes->item(0));
		libxml_use_internal_errors(true);
		$nodeXml = new DOMDocument('1.0', 'UTF-8');
		if(!$nodeXml->loadHTML('<?xml encoding="UTF-8"><html><body><div xml:id="hyphaImport">'.preg_replace('/\r/i', '', $content).'</div></body></html>')) return __('error-loading-html');
		libxml_clear_errors();
		libxml_use_internal_errors(false);
		foreach($nodeXml->getElementById('hyphaImport')->childNodes as $child) $node->appendChild($node->ownerDocument->importNode($child, true));
	}

	/*
		Function: data
		returns content of files stored as gzipped base64 encoded strings. This function is replaced by the build command.

		See Also:
		<build>
	*/
	function data($name) {
	    $zip = false;
		switch ($name) {
		    // Note: this switch data will be overwritten with file content data for the distributable
			//START_OF_DATA
			case 'index': return '';
			case 'system/languages/languages.json': return file_get_contents('system/languages/languages.json');
			//END_OF_DATA
		}
		if ($zip) return gzdecode(base64_decode($zip)); // $zip can be set by the contents that will be written to the distributable
        //TODO do we need to throw of return something here?
	}

	session_start();

	$DEBUG = true;
	error_reporting($DEBUG ? E_ALL ^ E_NOTICE : NULL);
	$errorMessage = '';

	// sanity check
	if (strnatcmp(phpversion(),'5.4') < 0) die('Error: you are running php version '.substr(phpversion(),0,strpos(phpversion(), '-')).'; Hypha works only with php version 5.4 and higher');
	if (function_exists('apache_get_modules')) {
		if (!in_array('mod_rewrite', apache_get_modules())) die ('Error: Apache should have mod_rewrite enabled');
	} else {
		$errorMessage .= "Automatic URL rewriting is only supported on Apache, some manual webserver configuration might be needed<br/>";
	}

	$hyphaServer = 'http://www.hypha.net/hypha.php';

	// push (encoded) file if we get a file request
	$file = isset($_GET['file']) ? $_GET['file'] : false;
	if ($file) {
		if ($file=='index') echo serialize(index());
		else if (isAllowedFile($file)) if (file_exists($file)) echo base64_encode(gzencode(file_get_contents($file), 9));
		exit;
	}

	// handle login/logout requests
	$cmd = isset($_POST['command']) ? $_POST['command'] : false;
	if ($cmd == 'login' || $cmd == 'continue') {
		if ($_POST['username'] === $username && $_POST['password'] === $password) $_SESSION['hyphaSetupLoggedIn'] = true;
		else $errorMessage.= 'Sorry, wrong user id / password<br/>';
	}
	if ($cmd == 'back') {
		unset($_SESSION['hyphaSetupLoggedIn']);
		header('Location: settings');
	}
	if ($cmd == 'build') buildzip();

	$login = (isset($_SESSION['hyphaSetupLoggedIn']) && ($_SESSION['hyphaSetupLoggedIn'] == true)) ? true : false;

	// if hypha.xml is absent present superuser login and present hypha installer
	// else if maintenance request present superuser login and present hypha system tools
	// else present hypha builder
	if (!file_exists('data/hypha.xml')) $html = $login ? install($hyphaServer) : login();
	else if (key($_GET)=='maintenance') $html = $login ? tools($hyphaServer) : login();
	else $html = build();

	// output html
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<title id="hypha setup"></title>
		<style type="text/css">
			html body {
				font-family: sans-serif;
				font-size: 11pt;
				letter-spacing: 0.07em;
				text-align: justify;
				color: #222;
				line-height: 1.35;
			}
			tr {
				vertical-align: top;
			}
			td {
				padding: 5px 5px 20px 5px;
			}
			.section {
				border:1px solid #000;
				background-color:#eee;
			}
			.help {
				font-size: 10pt;
			}
		</style>
		<script id="script" type="text/javascript">
			function hypha(cmd, arg) {
				document.getElementById('command').value = cmd;
				document.getElementById('argument').value = arg;
				document.forms['hypha'].submit();
			}
		</script>
	</head>

	<body>
		<div>
			<div><strong><font color="#990000"><?=$errorMessage?></font></strong></div>
			<form id="hypha" method="post" accept-charset="utf-8">
				<input id="command" name="command" type="hidden" />
				<input id="argument" name="argument" type="hidden" />
<?=$html?>
			</form>
		</div>
	</body>
</html>
