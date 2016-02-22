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

	session_start();

	$DEBUG = true;
	error_reporting($DEBUG ? E_ALL ^ E_NOTICE : NULL);

	// sanity check
	if (strnatcmp(phpversion(),'5.4') < 0) die('Error: you are running php version '.substr(phpversion(),0,strpos(phpversion(), '-')).'; Hypha works only with php version 5.4 and higher');
	if (!in_array('mod_rewrite', apache_get_modules())) die ('Error: Apache should have mod_rewrite enabled');

	// check for possible code injection in data coming from the client side through $_POST or $_GET variables
	foreach ($_POST as $name => $value) if (preg_match('/.*\<\?.*\?\>.*/', $value)) { $_POST[$name] = ""; echo 'Error: php code found in POST variable'; }
	foreach ($_GET as $name => $value) if (preg_match('/.*\<\?.*\?\>.*/', $value)) { $_GET[$name] = ""; echo 'Error: php code found in GET variable'; }

	$errorMessage = '';
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
<?
	exit;

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
		if ($dir == '' && $file == 'index.php') return true;
		if ($dir == '' && $file == 'documentation.txt') return true;
		if ($dir == 'data' && $file == 'hypha.html') return true;
		if ($dir == 'data' && $file == 'hypha.css') return true;
		if ($dir == 'system/core') return true;
		if ($dir == 'system/datatypes') return true;
		if ($dir == 'system/languages') return true;
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
					if (isAllowedFile($dir.'/'.$file)) $index[ltrim($dir.'/'.$file, "./")] = filemtime($dir.'/'.$file);
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
		$data = '	function data($name) {'."\n";
		$data.= '		switch ($name) {'."\n";
		$data.= '			case \'index\': $zip = "'.base64_encode(gzencode(implode(',', array_keys(index())), 9)).'"; break;'."\n";
		foreach ($files as $file) $data.= '			case \''.$file.'\': $zip = "'.base64_encode(gzencode(file_get_contents($file), 9)).'"; break;'."\n";
		$data.= '		}'."\n";
		$data.= '		if ($zip) return gzdecode(base64_decode($zip));'."\n";
		$data.= '	}'."\n";
		$data.= '?>'."\n";

		// include data library
		$pos = strrpos( $hypha, '	function data($name) {' );
		$hypha = substr_replace($hypha, $data, $pos);

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
<?
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

			// create data folders
			mkdir('data/pages', 0755);
			mkdir('data/images', 0755);

			// create .htaccess file
			file_put_contents('.htaccess', "php_flag short_open_tag on\nphp_flag display_errors on\nRewriteEngine On\nRewriteRule hypha.php$ hypha.php [L]\nRewriteRule ^(.*)$ index.php [L]\n");

			getWymeditor();

			// create default page
			$id = uniqid();
			$xml = new DomDocument('1.0', 'UTF-8');
			$xml->preserveWhiteSpace = false;
			$xml->formatOutput = true;
			$hypha = $xml->createElement('hypha');
			$hypha->setAttribute('type', 'textpage');
			$hypha->setAttribute('multiLingual', 'on');
			$hypha->setAttribute('versions', 'on');
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
			$hypha->appendChild($xml->createElement('title', $_POST['setupTitle']));
			$header = $xml->createElement('header', $_POST['setupTitle']);
			$hypha->appendChild($header);
			$footer = $xml->createElement('footer', '');
			setNodeHtml($footer, '<a href="http://creativecommons.org/licenses/by-sa/3.0/"><img alt="Creative Commons License" style="border-width: 0pt; float: right; margin-left: 5px;" src="http://i.creativecommons.org/l/by-sa/3.0/88x31.png" /></a> This work is licensed under a <a href="http://creativecommons.org/licenses/by-sa/3.0/">Creative Commons Attribution-ShareAlike 3.0 Unported License</a>. Website powered by <a href="http://www.hypha.net">hypha</a>.');
			$hypha->appendChild($footer);
			$menu = $xml->createElement('menu', '');
			setNodeHtml($menu, '<a href="hypha:'.$id.'"/>');
			$hypha->appendChild($menu);
			$userList = $xml->createElement('userList', '');
			$user = $xml->createElement('user', '');
			$user->setAttribute('id', uniqid());
			$user->setAttribute('username', $_POST['setupUsername']);
			$user->setAttribute('password', md5($_POST['setupPassword']));
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
			$iso639 = data('system/core/language.php');
			$p1 = strpos($iso639, "json_decode('");
			$p2 = strpos($iso639, "', true);", $p1);
			$iso639 = json_decode(preg_replace('/\\\\\'/', '\'', substr($iso639, $p1+13, $p2-$p1-13)));
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
<?
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
<?
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
<?
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
<?
		foreach ($localIndex as $file => $timestamp) {
			if (substr($file, 0, 17) == 'system/languages/') {
				$name = substr($file, 17, -4);
				echo '<input type="checkbox" name="build_'.substr($file,0,-4).'"'.($name == 'en' ? ' checked="checked"' : '').' /> '.$name.'<br/>'."\n";
			}
		}
?>
				</td></tr>
				<tr><td colspan="2" style="text-align:right;"><input type="submit" id="gobutton" name="command" value="build"></td></tr>
			</table>
<?
		return ob_get_clean();
	}

	/*
		Function: getWymeditor
		install latest version of wymeditor from github
	*/
	function getWymeditor() {
		// get download link for latest release
		$ch = curl_init('https://api.github.com/repos/wymeditor/wymeditor/releases/latest');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch,CURLOPT_USERAGENT,'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
		$githubLatest = json_decode(curl_exec($ch), true);
		curl_close($ch);

		// download file
		file_put_contents('system/wymeditor.tar.gz', file_get_contents($githubLatest['assets'][0]['browser_download_url']));

		// unzip file
		$buffer_size = 4096; // read 4kb at a time
		$file = gzopen('system/wymeditor.tar.gz', 'rb');
		$out_file = fopen('system/wymeditor.tar', 'wb');
		while(!gzeof($file)) fwrite($out_file, gzread($file, $buffer_size));
		fclose($out_file);
		gzclose($file);
		unlink('system/wymeditor.tar.gz');

		// unpack tar
		$filesize = filesize('system/wymeditor.tar');
		$fh = fopen('system/wymeditor.tar', 'rb');
		$total = 0;
		while (false !== ($block = fread($fh, 512))) {
			$total += 512;
			$meta = array();
			// Extract meta data
			// http://www.mkssoftware.com/docs/man4/tar.4.asp
			$meta['filename'] = trim(substr($block, 0, 99));
			$meta['mode'] = octdec((int)trim(substr($block, 100, 8)));
			$meta['userid'] = octdec(substr($block, 108, 8));
			$meta['groupid'] = octdec(substr($block, 116, 8));
			$meta['filesize'] = octdec(substr($block, 124, 12));
			$meta['mtime'] = octdec(substr($block, 136, 12));
			$meta['header_checksum'] = octdec(substr($block, 148, 8));
			$meta['link_flag'] = octdec(substr($block, 156, 1));
			$meta['linkname'] = trim(substr($block, 157, 99));
			$meta['databytes'] = ($meta['filesize'] + 511) & ~511;

			if ($meta['link_flag'] == 5) {
				// Create folder
				mkdir('system/' . $meta['filename'], 0777, true);
				chmod('system/' . $meta['filename'], $meta['mode']);
			}

			if ($meta['databytes'] > 0) {
				$block = fread($fh, $meta['databytes']);
				// Extract data
				$data = substr($block, 0, $meta['filesize']);
				// Write data and set permissions
				if (strpos($meta['filename'], 'wymeditor/')===0) {
					if (!file_exists(dirname('system/'.$meta['filename']))) mkdir(dirname('system/'.$meta['filename']), 0777, true);
					if (false !== ($ftmp = fopen('system/' . $meta['filename'], 'wb'))) {
						fwrite($ftmp, $data);
						fclose($ftmp);
						touch('system/' . $meta['filename'], $meta['mtime'], $meta['mtime']);

						if ($meta['mode'] == 0744) $meta['mode'] = 0644;
						chmod('system/' . $meta['filename'], $meta['mode']);
					}
				}
				$total += $meta['databytes'];
			}

			if ($total >= $filesize-1024) break;
		}
		fclose($fh);
		unlink('system/wymeditor.tar');
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
		switch ($name) {
			case 'index': $zip = "H4sIAAAAAAACA22P0Q7DIAhFf6ip38SUVJOKRq5N/fu165Y0o4+cA1zQoeDsAoEwKqtTBpIsOtdYJ/234B13s5IsnZbDsDxzWe/cl8buRcoGnhmPop5rDP0FGNGVm23nkFCawb6NimJxyblL8oRUxO7aWGAjNmqpdMtD8T0fEx9xfuniqJFmr3ovI/I6JQm8X43fqesE7HgDQBcpfakBAAA="; break;
			case 'system/datatypes/settings.php': $zip = "H4sIAAAAAAACA+1cbXPbNhL+LP8KVOOWUhpJzsvkOpYlT5q0TeaS1k3SuQ+9jEORkMSaIhW+xHEu/u+3izcCJEhRsppLMvchsU0sFovF7rMLcMGT0/VyfTC6ddB5FLppekxSmmVBtEjX7oIedJZu5Ic0Jcur9dIl6yT+i3qZojnoHHReUkoehml8fNA5OYM+04Nbo4OOh8wMXoS+z2jkpwSJyH8OOp15HnlZEEfk/NyLozRLci/rHb4L6OVtcpinNIncFe0z0s7aTWiUHR/rpI4DdEjeHyPJYbYM0sFUdiSTgglrT+giSDOaPIpXK5jVIzcMZ6530XOklE+jd0FGgenDJHGveozfbeIE/HG/347LC7oKIr/CJWGPf6WXf4BMWzDjBBZ2oqEtp9/zIKtweYsP23J46MMMKixW7gXlLW35/BG5Vk55tD0vUHb8jtqUDY+31HQQeS5YS2Zjp9q24vnSfUcfel6cR1XVp1rbNvyeoCO+FA+sXE2KbXg/d5OLfG1lKpq24fYyuwLksHITTVtxAx/8KaQrQIEanjrBVrOmUW6fMzZwTtfwT0esWR6Efg+xaTSCcRZhPHNDchik8TM3WjyDQQGaltkqHHOCYE44sk0mmutyksEU4VFAlvTsxMVxHNGdhillLDjEMTAeTMN4EUQ2Jmp6en+S0CxPIhLFWTC/6jk0SWLElfPznsM4DbJ4gCJK5aWXQeYt1aAMaDkYdzwX+DmIrs4xEe3UDzJh0L3+mMwS6l6MNWoBowY9Q1w2U2sXBk9GB0Sx5zRNYa7WHkq3Rq8Xmkqt3ZhCzT6GF1k7rbhLGL24m1jJU27zBjn3Ays5laZsdtCM3C4U2qwpEjwpkfp07uZhpsjSZXypTRVpri1Gb6wwtwRp+Hkg7X58YPcH4znTN0Ipe4qWHfDwAnw/ftTaB9MFzR5mWRLM8oz2HBnQwXcmE1KK+NI6D10uJHgCY3QOPJDZj1e/AlWv3IvrRLoW86XLBPT8Khaa7jnSt4S7eJAVLejAlfA9dMgbZ1jiO3TeOG15C3xCYMMY+GOeZWCpbCg38mjo9HnDQ7YQBXwBvcP+9fs3Hwohr2kge1Qra1MKwuBKaKi8ikmwWCJIAxjyVKBPbiB3LvKJFqIXqUed2PHsPM3cBCHs4HR6cJK5s5ASls1OuillvLvkMvCz5aR75+jo2+4U8t4sgf/hx3J6cjphMik7PZ0en4yggbX705MgWucZwTbkJ8QS1F0S+Lan2dUaqDNIoLskDT7A73ePuuSdG+bwK4xYp2ddii4ZTUEQvyToPA/D9oL+LKhNQYunuwqqS6EJCj8Sq3rXsByXceK3k/pMUN8xxdYec7klV112q9JgCzIPktVgNzHu2sW420qMWpXQlRuE7QT5CUlNIcSjXddPDV6jsCCCmDx3PToIATlzcOeqpClEPK/iGSyulPyCPwPeZB5DUPOWPS3+EDclhzhKn1BvGRPnJF6z6CVmATiNrUMHfqsFqELKyYSRnzqEy0d959hBxJ8CIy28/cnIXg+dkxEfb+qMySnognczlw/+R1iBX043gzaoFgELgAlkPPdC6kKU1IN0OUTrSRWPiNvENp6mYS4oNvsyHfx0QewmMYzC1rtFIFBb/WLUVsi/q+txrdZ62f0f2rj4iie+1ZGQlQueYAz2Lxp6MYKyF4cwg38AviTxJfx2v1u4JVtrjS95A00qaXoVZCHk2afTNzCcGKPRknc05HKiyeUq55g8KUzCjZkk/gmcRTYoMYK+X4exD859G8P/+dlvL1/96Wjr4rzuM+xgqyk7d7guXN9nTB1lq7cFIfxOcVaCE5UpX+cw4kc91SSUjdYT40jqC3oFlHkUvA38XgLG3QMrzpJcpqemIPqG5p8Udq1iMJAKGMkuYgEeBwuaZr36pLqIvpDIOsPCMnzxRE1VuZKSGxt+jH0U3jlxyTKh80kXH2bxMXQUctXECwBhhNEW2T7iqjvVZUsRov6KMe8bOv92nGHZavHpUExnHodhfDkIg+gCuxU7cOA7S9D1pOhKnCQcqumOZIcRNIOCDbmbCVHsk2UyQnrD5oR7Oq+lJhOawp4M9IgY9rwwkdukOjNyos2X2xMLSRqE8/ODBt0AcRTenSU9tYYyB+7II4I09zzABjR2Id2p+u1YOADT76WbDpTJCC7XTdtIYx+/fZDSzy2/qLjkBPOeyB57uDCsLU9YvOWTwqXp953hpggmznP3Eb/axhUU8Jc49mdXKqrce1CNKgtOooeVTxs+UE6waj68NYg0xYvy+ZhyRwHgzN0w5UOE3sY1WwEwKnAJ3hTSeVZkYHiWYHHiN5oPi9n2ibb/9pN4zSZXP3SADquiXRgv4jzr1eMAigdr287JzWO3nQ6MRFws7dZZk4zrRnTl68LiOBL3CTuGyNnEQa3axIGx0yfffUdEq6kW1gq5P0ZTeaq0BT6V387cCDry2QqBwQ5SKuLo2KC9NWIz2Opw43M4z8gbk4HP5TAjb3Tl/59kfNYnGXl9WvrlHWPYJvOFnGF0+GspdZ7Rsb2fyqOLKL6MBsa7MXvwl3jY07DbuoP81PEDOVlfR4htQRkmYT+q4rJNJ+z3ARucvgex1UtPqVJ8GxbGru9ooZpJ8Q0XI+Vi8OkVW+KqHNU2BTi2Romgtjax07aMxiwfW/hrxX5pB1680a/sfdlCjWUC4/rFpKzJjn1znLfbF2NqhptOyrP5yn7YnjHpZjsQTcCcCcPez8IsqevjAcOz2GNkx0TbYG5e11rP4VZi8RvJbO6GKR3XZHCll6B8SYzXdSo7gvQhxuoe7biD6/YpgiaglswqD333CsnmYRzDOsl+I/LDg/tHR5JIPB5A7sfob/Fm0bqM88TK494DKwtOf4s1i1aQHhbZyuTB0e7vBEXKPkhL9Rdf4TvAciWKJsHuiaZ4Nb1tgH3Muz0TvcxIW27EsST/31igY7Bf2K1Jj/u8CKCgXxcKG6axtk7BlsqIQc/qpOcNm9IafJN/JgyzMqEzNhmW3sTRLMyTSRd6Br6bUdUHTac/RgIIXfm6nmJzHpdeQRxeDbZI516yHpakzmiwHJ8bOijvvtsl4j4Dq0Eg0KqdwBLbHgNEmRKbLVWR7xnJKEKcEJNL85gdxjeO+QQxzT6oaLIYizEqQ0Vj2Cebh33OcdM+sGrcNLRAX2PwlTgp2vfxkPV8olIo1xjdVMJUhoZyGlNqhxxubGVw1tD5zNpRvDUoddEcQ3U59I24CxGOhU4If+XeupE6r8n3LD420DG7YoQPmsiEFcij7WLuZj5QkrO57kgWVjWlIFtEa2Q5QMqvOD6ras6bBGafHfSu3Qg9uHIizHJEmAZHg+Ivfjh85+hIng7fvd8ll4kL8SSez1kAxtmna+oFbgipU5IW0QpZsDDV8tR477ggbO3QTRY5MpEvKIztI//r95wmV+PNuMHmJP1F6qlw8qbkf6iN1Owjsppwnz7ipV9zCqtKlJtcRJp8uQiscIFHaVp4APtDOcC30gPu/FD2gHkQUm6UMUAgFnQ6kGi5I7bcQ6Z4wwf+LnMXZrNPcwcdGNYOf+/d2I1K2D2ZfIrlELRURP412r1ZTL+XnVuGb6U2pM04HyTbvJGp1qC0SeLn7rvAwyMO5jjLJlJuhholFxdNK040eZ8wOh6yVITiJtwfY2feY7NocZy1Ge9nRmeOx581jLfviGjig+5pZZRoxgG+eCpPlMtfzW6FRnVK/qhKKpShk/JH+0YYXji/fXECi5ur4u7IF1KhsE1myW/M3KD2oJRWVrwARzB9QFxjaOkBe6okkLeAtjR73kUzUKawPZqneWGjqcKh9blsm2PZxlPZNoeyjWeybY5kdQaS/Ba0ipzkhtF/j2e24vAri8HMy27FE7z1cn2KlgipHzr5XnMBfkGsmE9N7QC/9rTPgcWdqLoBV8WWdG9pj7hKWDNiWmT4exvRTBJrBqZ6erU39bKoUqdcicr6aNc2eF7eU+UHNEnjyA01WxFFwG2qMllhMDDbTyVJ2zG3KvxoYqr32rEooYm96rJjJUETb71XjehaiMXaPJ6H87R7xiyqK9eL/0nYrSw9B5fJjCNeGXhh4F1Mukwoze5wbUatV6+w0DHL6HeUixdLNsr1VlZIaqPZU4bCH1Z0NQP5wyDNuGpbW7daOaulW5pL1resMzjLbqpBW2U9yY8XNGoqKG5D1OmKZ1VRvmLxFWP00RZVFEUFhPVFu7wPiHc6lcS8L6tLcT58+OA6r+UXHTiMqjvRTRwnE4e+54vqYLGGlh+Y7Gc29hrNhpK0Uud2M1V3H5Vav/++AO2LNE5EYQJK0DfUrR5XlCyqhWDRRH2AfOBPnaGLlTFBerFRaUI0LAXCjuNd1k8NrOf6hAVkvvMfuGGwiI49imA4RozSK/x5tcSaRj7YKBclmOritDKAb3QD+PiRVBJEQz8bVtlUx+aeegGJ3vP6oFV3dUuivAiNkxDCbaUZxYczKmUdPAbUZB0M/TdqrkhLhHgd8aONB0dxpOypJFoCGxlwiTYb1xeC9Daprw1S0jHvtw/IPjjSajzxaZIWw22hDNO9qtIFzRfgEvl1mpZK2Ozz36DTiyWSVtRp2U0ioG0urS91y+/StJhRMSUFudM79uF3u1W+WQTu+/IHs7Mm39M+PdPO6PSv2GwW59qAjzqIGqlocs32D3/bWYv8uoS8l9vi8ob+2Yh88xcjTMadQ1Yfpw5HZEmktU5Tdv2MSiUd/WMLbCp9Ul8FiBrWNnriah/rpeU89hJGplrZl3FStZS2Ayr1JSeOAk/99iuplia33p586iuWpYNhvZhVv6ip/gmH5t12uBzJLjclLmQr/oDxGnA0G2QxtG7IAKrVoja9ad/A2qS5bT6qUr4L27rmuU326uqpq/juh4JWgW1tF3OL1eRlwrtfdWXvrOL4wlzKeRKvdllMCZi6B1U9EHZq2cDVv3B2bS1hl7XOO3kPGY1gqxdeETYQ8VzJkSCzdCvnqsHHpnUpkspabKqCTptsVd2wJje42Mw14bN15sXzfK/PwZDPvZ2rlj4Kt7+lUmw/zXrpftRqrW6kfTU5f8AH3kXx2pcUe4cJfZvj6wy/6bKHPcKwDZZdqwXXml2ZLCMv7r8XCfj/6mq6ErrdBXWNnN2uF1P6fC6i7+MeurAglrj+F8OaJMjsVAAA"; break;
			case 'system/datatypes/text.php': $zip = "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"; break;
			case 'system/languages/en.php': $zip = "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"; break;
			case 'system/languages/nl.php': $zip = "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"; break;
			case 'system/core/base.php': $zip = "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"; break;
			case 'system/core/database.php': $zip = "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"; break;
			case 'system/core/pages.php': $zip = "H4sIAAAAAAACA81aW3Mjt7F+ln4FlmaCmRUva6XOKUcSqdixHW/VZr1lKXkRZRY4A5JYDWcmg6Ek5uz+9/M1LnPhRVolrlTKXooE0PdGo7uBi8vjI5VGyTqW0yyNZMDlvUxLPciXOQ/Pj4+Oj4avj4+OrlWZyDP2QSykxk8aWSrNoqXIS1mwWOqoUDOpWbmU7EFsWE4rmSgkK+RCaSyS8eD46PWwQvnnRGhtUeKXmOmyEFHJIhpm86xgS5HGiUoXTLBIFqVQKbtTacyyOYtFKSyyLTjCxv4P+PL1LFER6xIf70D/fRbLHusuy1WCP4lIF2vM9OyCVKzo672SDzRSqHtRyh8TsYACjubrNCpVlrLpNMpSUFtHZdBNLT4CCQ3Bo0WSzUQCEpt8KX4CnfOd0XeOrJnpltBgf0wcsdE2mJsk9DRJf824mjNL2xH1C5tiEgAtOW8tqIXCvMXRHy9k+W1ZwnLrEqZ3a3jIRiPGs5SzSwZxJTtjc5FoGTqMXntAZLieEvW/yNKLV2mnJXTY4qeBo8K3zY+KeRvI2+opIJp3YJ+P7b/KRypTztYqiQNa9bnyxx/d5BkTcfxePpAr/ZKtS5Ua/4xjzT6Ke0GOnpfGQTFG7pnCRnm9MT6IAizA4fUZfhnz9tmKvDfOovUKu4uZwWz2UUYllvxjLYsN1hAOZn/A0cjfCXulKOwG5hUAqHKTY4P1scUKbLcHVS4ZuFOJmCXSorIrAjWQA8ZL+VjyHuOzJFtwJssotPunUsmO0IHfLIYl/DX4rN8d9OpsNtWlKErS7eX4+MJqa9ygcy8She0rPzhRAujBIr0XBcszDeNiaKBlIg3EFeE7d/OAdvP4tpaDQuaJQNgaTvTJcAH5GA/rwV8n+tPNr5N48jDRk0H/drhQWGK9g3YScAwSmS6guwsiHTr69biVyVGzM35Iy/LK8/gLVCADAPcMGutXlcip1WtgxXQbnl+UxlQmbo062iLqjC/KAv+WcIFE5yIddU4xdjmaTgMeFRKK6wNdnwzMw8vxxbCMDQg/d6hPDGqDY0wWw4olfmCV1ShT8ajjOLrGfIeRFdpDY85OgOWSvFyKaBlY2zOhrReETEbLDCuy3Nt0DRR8YGYHHN8syGhkHI8QUzDhlgUZk7z2W4cjusAkAz6uwC+GFi+EYpeOl6EFcAIPD0lMstQSqzRfl2YfjDrER6cpPC3teNahYRvKEEyMrazX37y59Rvg5uvbkEQgfquRy3GHZeksWRejzo5jU8wKz2nBndys84MrqrgwAPEfEklfv9u8jYMJd6xerWcrVU6gpVhp8poYDkTAzi8vbXwGZxSuzzvDp9UUe7fZUlG0lNHdLHtsqemDPRS23MSPDsfMOac7PLY884UcIISX2AR+U/iftZHMNhBIVBKiQbqNcMzfjTqHdZhn+Ton3elyk8jBvdJqphJVbkYTvlRxLNMJh5WGW3w22dJG/y21XLmhFqdMxqr8bj/TZu9apr0R4ZQwV0MI44JgGTDt0Ho5Hk5oHzzrKWQkktX6xQmb8CHxNEHor9bQj2cRURxoI+oZDoJnIZ1rELBxKDjrJcAzqBkOii/zOeZCo/LaSfBBOrFGOETD2hKIVZrK4qfrv76jFMTlTM8CWfsncl4C6uBqo/c/Z6sVzlsNKLCLSP+OoBCH8kf+AlplllOy9UJa14A6Yaf/E76cYO3cdMKYX4l8GkHDcYBmjmXaJUaIue70vkCVcGTSgf74oVClvDITOLopXk6jRIo0CC3UTjaVZCJ2ST59xSmSJDYDSqWM4R2USpmEhVKdoU9xhpTz2oyqriUiwALCZDtUacyzJMkeTI0Qx4W0hcNKlDhSlmWZnw2HDw8PgzhbDZBNDX0qFSE7bdPhlC6+vfr5f//wRyZms0LeK0HcW6prDZo4+5rwOOlNYiVTwz+ShUTpJTaWNL8XEmwgHqAuoVRQEH6dy0ghf6nF2cckHADZ10JTsrZnGgWQfOQmGdw3LT6KR74vDxXFYjtbLBbGI6CzIlsZdRorrIuEycFiwFp4i8Ub+viaPk4tgSsp2beJzgj9hcmoyarjrbzSmz/oEgvt9FHpjCIcFS69RgXkv/s4pJsD22WF//23wkQB/D8cMigRQt6pymSQhupS5N5lxnSJtMacnwxZucBqrW0aUygKQj2mM1ofowQqIUCmZZ2GPyxhcphWregXTE2K80YzCrRFL1IEVL2xyzSN3qfIA6byEdJqqw2bXjS0EIasO7364erq7c/vb3hLTn5LVY+DIkklTnxC/UppkA8Owz2HtEp8vpdzsU7qMs7kyW1tEw8HcdFyDd+KlqySz9WpkYAO+VwlUvMzU9JpWdzLHzEQcKrmh2ZuiBzQACLZspUf1GWSf4fBaP0QCjv5PA6zhSyK2ulcZLvOXHQMeOKMQjvxIlb3zMRYpElZkhVnX33zzTfnyJQHdLpblCGlrlg49mWrVUZQseNq9n2yPM2KD1ZgxZAjyL4j6mh9sSzW3AbYm04HnPdsFn4xK4YV/y/Qz8xrwgplVDEb/6vYBFsWcj7qGC6ta3hVWx8y+MVL8FMF3pb+rWE1qBWIuC/uzpsmajrsiyxEgP9dBmqr1Jqps8doYvzvukDDQrN/z0K0tw8ZKLbBqrJNs6Hk9hvyzurr2TNhroXjvesxNSLzTTV5e2g5qgRdFkFruMfe4DQpizzT2xOcBV/oG1u+lTc2/4Aj1Rm0MP9m7lZh/a/2uv9IqDB972C/X211Ofc56+fj9oANLlWmZ32YznKl/4ZDLQjZp08VidGI+xY6r06QOh+C41EDspmBVIz2HJJTOgmHry1k7rIqqhAqFhzTZvLvrunskPgpZMHF27iaOK0mnmbFYwQvFgNYeT2stUJpjJdqKUUM8fm7LBK2eIBv++xucHgDDzgO/e1p23Lz5qhygAPGWOeUqDZM0VC/cdRa92oevOpOf3z77oerG/6wWVGUQpbFuihActQMpAdyvzLL+jO16JMbmppVEXeBIzVdicep8VD1T1n5ZVMbR135SKUqwkeJlOMBmnEhBv+KaIlQs8PFje1/w/J8wMMe+7r2xz91iRLwgQm79fBzL4pylU8tmgrYyGzg4ZmvVDo1CW1AHPZsURHwj/mC9/Ap6U+e0udCzfE5W6FCDXcVpFLTEevbdOYlamrr6ahLC9zFwDpV/1Ax+QT+I/48iJFhld3LqcUsY4P3GQ1AkVu5pae1RyCTkBZ9WRRZUfNqmW2u5bvY/GLrnf6Pa7O6Owcfj0ed+iKi02hvDhvDY1TFcfZALYiB+2rF/sWxEUw4yHumBnwChquKnze3ylb6DJZQEBU+gbYXFlQW/OWHa2gOhS93oaG6fMO09RGnE99WbrVcaSEiH/WYCa7ablUPOve3PS5Q6+8212JBR549EtZ2lxKC6uSqfMRs6acumkajnTR9NyY3mNh3b/ZqRLdmcAy4mjv0G5v3mSursNfc6VavIVCO7N1bfZV5Q0WbXmZV3+ZZzHudy34KnRVlUOH2923+kuKGty1Wm7S2k1k8wGK6AKChAe/0eBtRAZZW/kap0+s06RDorVtvHL7rW3rNsF0H7Fbmh2J/LqnipDK8qrpNJ8NSmPoLwy9KCj80EkKA2tvUrZuB7faDoeBgPE+mS5DKSGot6PLMtn+ZqC4LqXOwzuk2wHBOMYaaEb4LY1QMHy82x/WRY9j5/e9Z5ZH43p1++PnqunE2wUfN0E2zk8sbTt014akSS8SuPbMPak+zZeouy9vLXdO3dWKAE0MqZGlWqjlOCBsYAWzHq1zsX1B0y4nrQ3vavBsH4tImOnZ8e3fQZBWmu9P7Zt5TpTeEtpkMcKP2wK/CCDXYuWmyOAycH0qNLEOOzZ6DAAd2OfkNpaKIA6LUNflX3uANI5olTZVd0UCjljELbqrpd0KX3yscOuW1QsQIT2rPN6NvU0RuHMdBeHty4pHYNdojtzj3GaB5HG8bmw7GNHP3QmGl1aAW6gtycZyBuUj9bUuhFsuy03nJ3ZGtCPwtDCJUfetSXc+eA+OQ6NR1QsuCJhDXJ+Pn/a8Hmrd87+1dvRapKpG/6OoC370ViLIUCQMM+bCE2CAdUS8RQSyN6C1NRs1JpNIJRmlO9xDiKIXR2FIpNdJxVsT0CoeeN8hCm75whdTCEJo1iphCRwji1PLMCvyiYfAiCwpBSJrmStbvC+gdjyjZRskkZvNCyTRONtQZBgemN+z7wjIdrjbm/QdFL4ReYd/nNKd/d/rGheOdrrSJzf3Gs4ZW63jnvrRrAoBxtEKW6yJlOUqjqb/v58MJcio4yxQfWzP0FGCiu83nANPboTJdld4umhODh1Gssrneobci/hx2lna6byl8JqI7aIcaz9YgdEEQKw1qm5crpX3wP6eQ6ZYcB6SIJXXL55uGBIlK74zvJCjG/KVFMNvYiGPPMBXrkJbkdNthAJ54AvP9z39l3/sXMADKcut7WboloWfGpgt7Hpy4Vn93lsUm87QXU/sTQ1rDw/74bSlXwRuzrRFmvZBZFK0Lu9ngtXUzwZA5s42WmJ5DqHSeNeZ9vlXdImEN7XOJOEo3k2AoMOy1XWtK91fkDrDMhQgGry/DJj0z0DGfk+HYWM71IOJT7D38XuwQsJ75G1Iem8+LyVC8jIMv0Co+SJfwfn6hVgumi6gaNPujXjpDqQEzD90slSlNoPb0F8gPsQPC/Ang4agT3Px6dksi2wjQ/do8Xqk6Dbx72nlC1n07aHf/mG1TyESU6l7W26m9fZ7YLygGEPq39saX7YwvsMWWgts22Zrcss0Bm1S2AHV7SjHCk4tyaW8YrUboEGHBStxRoospr6HwuK4W2pb7qmm5lpnIjJ3b12HQ+RRcnuF/HDWfKNCfhDe/YqIThl95+3ZPu3/g7gWod1hHggUVE5Y9cz/aMNE+vg7uqMq3nthOoHKKorPJjovfvvbZm1nAQehBnHkJlRVkfH+AGwbdAy9po+D2m0C61NbmTttcUEPalB06l43s7kWiyc2NAWvXTVCebB/WbeaCRhFvMnNbOD9SpF4K/Xe7Wlc5bXcqLJ4RI9/41vzwUNUtLvX53Fj9btdcjTjyLnHzBqOcr5qxvXLu38G5PNFN+/dN1U+kh0t61WdczjGHtJFaI8e7XYnH9glknlZsMR8eOqIqBk1NfV+/7EWijsR7lZsXwNvFCyieNR7ILhXdam9uaijqElD2FPCP/VUf8fCnMwp2ia3Eq2XQHVUvA1LiYJeMWJfLqpn1uXqC7Mh5Xu8K20bww5YpRAi5M9hFPCrIPUfsTm62Zyu1umGjEhWz0Zh1S7EIG1Wuil+NPLKqCbHnSaKK3YNEFY9Gvmz1Oqd2AGfPPksUi9arxEav9gBdIvjqtyP2+bjZLKkeQ5rJz8dNVpzTo+ByVDXfH14ux8f/DwLq4nz9LwAA"; break;
			case 'system/core/language.php': $zip = "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"; break;
			case 'system/core/users.php': $zip = "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"; break;
			case 'system/core/editor.php': $zip = "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"; break;
			case 'system/core/crypto.php': $zip = "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"; break;
			case 'system/core/communication.php': $zip = "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"; break;
			case 'system/core/events.php': $zip = "H4sIAAAAAAACA91a+4/bxhH+Wf4rtsIhpGqJuhQtUNydznAcp3aRxIfetQ3gHA4rciUxprgsH6dTEv/v/WZml6Ie93DSFGgBwyeSu7MzszPfPHbPXhSL4lkvzeOsScyNzWMTBre6TG1TRfgUDE53vyY2bpYmr9vPz3rj3z/r9a7SOjMn6vUtvlX0vEgrFS90UZtS4aee2qZWlSlv8RxnKYap2C6XTZ7Guk5tPlT6B303VNW6qs1SLU1V6bmplME4AyIaRPHv0sQ0+kS9ubq6UKX5V2MqXvBdrgpMULHOMiyXJ0yvHTFUq7ReKFvyX+KlsFWd5nM1s+VSJbrWEVFpSjW3OiOWa6t0vEjNrVH1wqhFOl+AEs2r0mlmVGFKmquhF2VzZbOERLPLogG7wkJhsWCa4zknOWyeC/tVpK4WpjSYb9QKtDUtYtVUZ0xumebpMv2RFaPsjNlTdalnszQWQXZHOJVWaWISGV6UNoYSIWIkqrvQ/AR+mG2eOSvt0s/F+m5/IHxicxK7tM18ISoifYHvl1kG3X/zNQ2CpKBGayotg0xmyDqEx3plobUkMTlG0Vj3FZsR0N5DQwGrKdDlnK0qcKyGVa1LUlhiBnjsnU1tsj6nX70zXidNJv3Fuljor/DUh7VgT/GKeOxj10i2Sb8vM3pnsjrNccv2Va6XpvNYrws8CrN9Nd6f6Dn0MzfP90wdjRQsURHjoq+5hTXTpqvRyIkyJln499nYSxgaKMQLTo4EJVaFiVPa+h/0ra7iMi1qNWtyMSU103GapbWujaqaaUUGv+tcbj9gxbLDJ+qMtXdOu9hUMBlwojKrEzIQrXKzEm/C29vUrIbqjLzpHN6Vq6mRKbCXzSJmdwkxLXqZ5qBItDGHiLo9/irNoaD1kJZrllPMgBlvpNJQFKRNM02+hrXmJgcK1OKMpNFxRxukMJZh2tQ1Ta8XGhZdpvM5CK8WlmgsyBfUpTHqLC4NSP3F1vYCHH3Bk86H/v3rOxPDiV+JdbivbKk7E1/mycGxEPH342cOGr9yMp0onSQMkG8wMjMlw1ndFNDAGQn0pcPWc5aE4Wt7F4kDYGo+p10qGB7pFe1QFbGHlzBOQp8TPB0t6mWmYIbkfnAoxqnZzlLMaK808xSwW17Afy5a3PCMh8EO4wz8fqt2pQp53YH6CSzMMwtIU0dsbH8vs1PopDceqyLTYKb1DwBjTTbLlkI+Qdzzl4liaqPzualfO/T4Yn2l599C0DCgMcFgdP4WISM8Jq56dnrD4BHi6QXEE7wQp30cMfArNkU9gpbLytSTflPPRn9mIPlUFNmZ80QA4VkvJhD3LYJF+QbCh6yKAQvjEOPsBYZVe2OGCtJj6k2cGZ2Hg4HXN7YImj2AHuRYLlB0ozKk6cRQDqn7qj0TWufOzP+BvIF89QRRrDLYbLz762bFW/eZN1sjesGEyZNpsGrKTPCC3vgUA5jh7N5Of0DcFKfqgZBfAeYRQFmteb04D05lQLFlyF/DuDGWUO1lWep12ElcNt7JVLaZ7qqJEAw6sWCx3ALHNrx2nY8kGkneQGRHEnJrB4GwCAgISDflUJkIqBSYfLxA7oCP48TMdJPVRDuI1D8XiJ+ZmSF8Lot6zTTjpixJQzQ8IQwvjQAs8RIvEyyN/GyLfSM45ZerkHEEGAwzJIhwxsjmgDSB/vI6IOWJiPZbkixWCDGHNGpIJMTnG94Xv0PPaW+j0rDHh+Pvq+/H4zni/zgYdN8e8cvtd/zqhrGm16adGxz4Yv02Cds8YhDd6qwxWBncPDijzTU2U/BqawptVvU+aOEiuI4EITC2IRTr9TAkTCfHpyo927e1KDP5vF7g4/PngwO2+D69ZhPspTMVgt+ff2btPbQ+IvsyrWWWySoKt3liV1HrI56zjwcsm7z4fsPm/BTyPT2CE1CzeQhiTEu7wpTf1A9AqjQVnl4BjKY6/kAmbpl1kuEAtFGKDc9oJaV6BHOx8oo8ilhidFvUdeHLBPwtLLCP03M8VHiCrmtzV3O+C9fAfE3YuOUzdXeliLE5TziYkjIq8GoL+oRYOMv0XFLtPOnUHuLmTKcljN/AeGQxjSQ9swYBszUKyfJXKQVR2jJM0JllVJWsXYtoF+8ur7x8kXo7UxaGRBsA+CLyM03m5Ab/5XU7tiXt6hwGly0IIPUJAmxvzVBtiy+4QLDsSJ8qhaBEKm6jAudrS/0BKc1L2hVfZf0OM+tyTQQokL0DM1r9OTp+PlRfpVRAoWS81DOQoRGedcH67775+g2E+pu8FN/5CCOo44UKzcDTfOvrs9d3RYaCrFRfiEFTzGsX36H9Eiq4Nd+948gU9r+p7pbZHyJaEXVpn5fqrqWERkvsEWppXNrKzuodgnvcM/+XlpIZiqcrLr1KGIF81EjHQA4h8uYGyZJINcLfDyZAPuGpgpm6KXOxBLfQM/ffx2ctr5HNkfwmqM+RAXMaStjprSF0Mqaz0I/n0ZdcmEwm6o+DHTPpjBM/u4KbySY5aDxkRhLexa0mqt8/9e/YlyYPgSgPpdQ6pPGC3uqMJ0a+NN2GbmJi6zOQO1ro6mWN0gKlBlJQyuaCgd9gYez5RIWOxReq/1lfnYDRAWLhHi0Epz1aGNefiFy9gwzUkv5OJgEBEwoljUntyianYujvf3uLkgRqxaR9CinnjagDnAVwRPkkChw6nZl3PC+yhUG9QJgTDH0S8JwxgmBMJvixQB/nm29gKagc+q+kEBhRStwfqr4uiswVP+O70Wq14vgxAj3hMek/laJsK2iKlG6bnzDbdWyImzizldlbMU/cXremy9r8aV8rANenKAUEcyC9J/fx2dnYZ9qc97uKaFWmtbnkD+Fe2t9JAzZ5ueTJXKtRFoJvGSXGqAnhkXaFkBD74NrW4RLTKZkUHipuAfh6UcUAGCTznbqdgdy1CVy8RahwmUJiKFkDwX8a4J70EAj0JfAxN1yQmFuuRLo9LQOLa7t5IE54x0GPs5Q2kEqI2suAPMOuTPcQRAlwkmxlQ1o9rpQ9Vb6/PpT7HKWUiLsMFakOvSIalHBXlY1TTWH4nvz6Ho5DEB0qJnOgxG4ZOmU72eYRM6+poKa59ySKrhR0a1ItQTW6bnkcTdcjCNWWao+rKnqqYh4uRB7X1zbrTk1tUXK/ljgFtw0wbufjQH32GfhBuXjzwaxvzB3eVY7uzshN6EurG54SHlL+gCIgR9pDX98fX4/Ofzr45fPrjyHLwkjB8PIAoc3Qj26XWyWRr72UcH2UIYBTD+sf3N87crshSuM9EKncQhJHw++DINqaGQXfA9P4taPQeeMp0avBaXCP0R3qxVGF1XFKGdJ2/jbtPu0afgI7VKJUbY3OHHc7jjtGmOmpoeLEUeDHtgpJ0jqQQRtZqeSgosU3A1KalM/HXKxQlaKkutkuZcyYie0mz4ekDo8cE1vLbu+DaylJ00hY7wMp4SCbRw7Okz5vFehFQR8YDDSNP7ge2P376P8fnFIv++EtO9T6/PSNa1sTeOfDiRSgBPwjjxI23+t8RHzOtP2u2wwZqjQykZRObcFEZRxSSok9aV2ZbCZ1Ex9jtCUwdUzLZrd70mHRW7tUmd2+OInHS1kqViQqZimCXKVvKWrRN5RI1EnbdNvrUudV5k7CTC1HXp9ut76t451xA7OM2+BMCsnODgGyd0tlJ40PQZCpqXy/rh10dk+EOndoLjj+QG9pN1Yc9pBDRrbxk8dQ6z/lLU8Guce95pGzg9/SgbrdS+8893iGVnNUpO5M9df4xyebsDerXw2/SOfJwaqxpziGe5bf8Rnj/49/PGJOh0PKf9Fzfnm+sO1K49bGnJH6c4qORtyICx7gM0ERi3MzWER4dEPF6fu2cXzts7cj6U4gQ95NJXdnQGfuVdtLvpbUrMkPL+EqPEnZZJ02P3Mw0V5suIwXJmnkePAdTDBdcvXDzQl3JEwtWj9KpcvC1eXwPr5wgEWDiibyMaE73Vkt0nihEmuqPKBCrDAOExpspV3apvJtX6m3qP20okNYmGPZ5JH6q522cc6vnvAhEaw1BSDldRqnBefoshHsQWCfkIWq1ZovAjC4zNIMkRl5a7mWIWTxKzOtUNPS8NuUfiXSjXZHvAsTf6hcqzWVM+HKGOrR6FyolJXcpDB3RVqapFUXNVXjuqHjZrWyJaigxKzrNSIvwQVUdEsyR1G0xcdCV8rk3EtlfmxZcQMVKDqUgZtzZemW4guph8XSMfISwu4pXXyp6XNispQ0THPn1iYVt6INKx2JIl25qOpmiWK9Uk3hMh5ihZflFBcbmFfYwIUusINKU9MX3r0yIL+iAZaPu5fQAUSGvFpumLgdS9yNF4gqynnpQkIMvVEHNp0vWARebLPNP9Dm8zs2CdqxGHlNs2xFnwodpDEJVvf6GhIzam4wT1VUI5adrvpCs4Rr6IVNj5k6WL6jSO+6xQOn5o5lYot4Phx+/qMH4y1je6fi7ZfukTh39GC+vqAMvJLbJuKRf0NnOlBUmeos/dHILG7zSE9re65vbhodL8INCZgwVduTc3UEfUhHk7YPsCiv3gf0CIxyHcwjOvObuE/47dqmUqD79/jt3zvI8wtKoSufmGEEkUMM0xmBF6yd7KTo7QIwHzBuCuCPndJ2x1A8JYAVGXfClusjqv/4QE9icx7VJgMgwBlD0kgyT8dZ0Jjlu0+QIuYDo/+TnKKjP9fq8GI/kDI8aNK/3JrJ1PhwlvopYqWTc7Hd5x222Egn5xvu2DrxomWSiW0ZqDNkPsj9RUa6H7a/tWQ30qv29xFtAZjaudC4DXG7zdkulZ0ebd5dgFP0JPEHsdSfpbSoc+lP/asxjbn3HpIs9Biabq/JbT/CeV4Pnzunrm5h85vfQ/KM76Gt/3Dv/aNd3Z5u7sZsvq/bK4x0HdMm619xFeloWc2T9HYz01W0MjkM8C3gWw+dwaPzautIKE1oSIe/B0dX9ZrNNqD4O6VbgeuTINrqb+5qYfBCBmPeSSCXkIJBFJy6dUis0TknG8mrRZoBjWXpwaHm6R7xgXIhSd0zggMUKA5U91jjyrZK6oo+lKGn/71LTRufYVdY33+Lgs/z9ZbHHIwzVFBRCKE/8AIHC0PJa0szoySWU+RXl5eK91OqLvbHpS4/NEVb4Na6bqqAPDEwZWm5ZHYEaQm6teAed3FepAmJi6EfIy5z772ZLRvcnCB2j1yfOuU5yktyDFdOBnjB+sD7PgdXeUd9sS/hMOGAKF9J1kJjzumrF5SIjUGNa8WeCEZjy9Cdgx3cSB5xz25KqbTg64y0J0tLZ097EKxntUvcJSF2t0Om68PKdjz5avRpSuuceed25W4L7OqkPQKHc0zt3ZN35PSZO2GWeVFM/l2a3B+OuozQUQZZN26WllXNYCDpGvQFKw3Zi921BGaY7I8nRfTzEhgDMHdXDtyyUW4Tc0V7P5lM1OftfNLQ7+j79mE7JwFQCn3ZRj7+MiQl+UsNck5C65DmRjxnvj+H8uA/HR8fC9FCUyf5WzAV8b4bwTyPOv60G27GCiG5di5MPEGhT/QXdv5og+R029FDtSxaiQUgFQ07NjZUnx8fd47mWQ+/Yk0XFDYHSg8sS+v+suPq+wH34s3F/z7SHgnUHm1h7aPpCdnSZsrhce+v+RpsF08jXi0ioIz89KiDkh8p0v0bn6Uk+v0zAAA="; break;
			case 'system/core/various.php': $zip = "H4sIAAAAAAACA6VXbW/bNhD+nPwKTvFGKYutNPvQzrFlIH1BA7TJtjpbgb4YNEVbXChRICmnwbL/viMp2bKjpAEGGIF4R97Lc88dmdFkfy8+3N/bm3Ij2BD9SRSXlQYB/OoForJYsYKzgjK0qApquCz0YH/vMN7fq4+/qcVDtGTmvCiYemtyAYo/mKlUoRG3MvR2+v6dNWdYYdBCyRyRAn0EWSFThohG2iheLAfe/29EkZwZpvQQVj23p7/e7v038Wz5Dd3eCP3THOsn8gZ0ryStcnDdTxZS5cRcVqasDBqjBRGandrdTXBjhLEV8AUKaxM04yK9gE8dITjPCM3uq2wSPbeMUGNsMEbdUWiyYpBOWB84fWq4RlUuWrs/lRQkBbtBry7fNydCbwt0/YSUJSvSl9ZD2DPsmzXgNRRyMOy1YO4Itjoc+ZP2e+do68gUtDbbsMnQn1Ku1iiDCuiSUU4EzYjSs5RRt9lZWOfsXWyQ6yfnhuXhcRQdodcX09nF5e9Xl9PXH6zpfzuIpttEA81fihvWSTQj16SxzPpQlaViWqMbogpgm/ZMtGGjEuJl6lH+GaKAay0abkjTd/yloGz73yGqvkfUozVTPGVvABPWQbt+IlixNFnU0EmxXK5YXZ/7u3mNp62N4PNvuZhVms04uFIFETOmlFQ6tGzacO8joHCfT/jZ4BgfIXw1fdN/gSPfGeEPzQmITJLUJh3i0QQcIRgWMgVwx4E7EiQji28ymsv0NhmlfIVg15Cn4yC7LTNynpdSmSDBAyjNcqZYKQhlIY4/q5hbx7gF0gCPYrCQjGJvLXamgbqoZuBsFmKXXN+GBVH07Q7cBoIKRlSDwPcQcuPBbdrqe5c4cKHuoLPb8zTErXRw9OBk8MXa6q/OxufOUN1rdt+Ra/7ooaYgaXpGNLtSwlP4Pbm2noVATU5I8OIaRHMtRWVcQ7y0810ZjSSllbJjHj4XCI8IyhRbjAP4A6VBUoGM50ukFV0Lwa5sbZ1773GthT4r24e21Z1t5q8A22h2VvnVfhw/cAGAYt1Zm+RbF0Cn1lv13bYUcg6w9FzhQHvqnHV16Q41Q5vxHaQVjYPw09fhl8NJFMSWrL1n4wAP1hYHuHcSgLzjitoanP/XfpPWA9zw0+Jp9FBMEMNXT6PHTs23abKj3KHLAzR5hB6PvwK2cmw/A+5X+Sk1PmjXYAtxW5Dgy2EUBnfhZAi/H0+O7z7ru88/R5++giKIooOmVL2T3i+PlL+zjzUkzPV16wUFLyW8lCJlBV6r0U3GCmQyBrjapwHXbjwM0DSDTwpH5gzBREvtHciLlFO4vaETcl5YsdK2WKXiKysuyZLp7qsPJm/KHcD92iV4aS5ZqAeMRRvfOiyu61HMYGC56VmvXRosL83t5qm3VcDGRLjx6QtY90grlAkwRpdgT5tbwcYBlUKq4cH81+PTIPnp4MXzk+ej2G4Agg39c64L6hx64KwyRhYN13eeuha7JYPCWZBcznO33WPeiZcgcyYAq3qjW1o5oTWIf5MV0VTx0iAvs1qegkaWdglUZf5WQTzdgWgTcOgdQed7I/ABNuzTdQszaCzHDnNbAkw+pgBRQbTeLPEghMMT6NDUUZ2nAxzgIZga4BURFXNS5w8USBZUcHrthN65lcbJYyi/g9HyZIxJQTNg1Q03WRstWv+jsEbtYfBrC/Ya+R729/C1oe6iu4NpM8I25oZtLJINWqOY1LhMkv3/AMtMoENyDQAA"; break;
			case 'system/core/document.php': $zip = "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"; break;
			case 'data/hypha.css': $zip = "H4sIAAAAAAACA51Y226jOBi+JlLewZpo7xoEJLQJveyo0kq7VzsvYMAJ3gJGxmmbHe277+8T2MSZGW1RE2r//v7zwS1ZfUXf1yuETqwX2xPuaHst0Ij7cTsSTk/P69W/69V61aQO2Uj/IQVK45x0077oWlQatMgD+/KNk/JSNUSgP//68oC+4YZ1+MFlEtkzBjkdBABHLRGC8O044Ir25wIlcfKkWEaCfIotbum5L/6+jIKernK1Yi3jxSbLMnWa9mTbEHpuhBR2l1tZW1ySVsn50VBBFD4pevbB8WBpaHdWFCXjNeFFMnzqnbhqafWmtqoLH4HdwGgPUpptNgjKer2vpTkcDmZvJNW0aXDT4RONrKU1MnRRiau3M2eXvt4aAEKIASgvQizA94nZ41JPbfyWYVGov81eTd8pcJO7VUswL1pyEs/I2CaVyqEO8zPtt4IN/kLJgGdn1m6Fq7PHY0qs1darKQT08QIdrOU2Fes63NevjHfa+LQWDXgmSSbrbnr8/gJxgMF13CF6MiTRgOtahUK6B8sl8jEnO9JfPMZyM5dEz5NNlNqaXHBMdQw4FlkSumHmnG0ZMHBMjaytXfppzZVHiztroddSo1xNx6HFkDE9641JN811aPCLttz4yzzv8whZR/IZ8Jl4bH6iTbqDw6l0yx2lZp+CuW4ck7huQb5tEYRrwDMTvhT7CL/m21HADauDDRmNgnwHOyxjUlOxdVLLlzOdFemwcbthMUVlB3kyFRq95LOxQkAyNTZXlMQ97khA15Cc0ji4P/9BRzfNlxysoxD8BBWRG+HY0Dx0vvtJ+ENlltbQMO+Ej1DrlLToe3R7ygvjKArGh4Y6MQb1FTlmCoO4ELI1zWHmBDJy6qL0ARsug0Ie2EhldS5wCQX5Iogbc/msmQoX5hanKdJMCOwXxUrV1NwNRtepsVoKKGcK6qYh2NTuQG+Y6r9LZqW2Oe+oXxHTq3S7/dASl6ytA/02iTPdbefWnO11ZzY+fzzYJNPinA6JCV9rYFWWodmboinf/JKGnXaGjD5a4ppUjGPlE1sRPamREXuq/jiGlsxBQ5lZ0P3fbpktKTzm0IhcuMGvBlPMzK1jtsthcLJ0bg/B/UBJv4dzJvXvLpS4tqSgAnxZOVY/Ho/uoQUkRIEIOjfVzoVzkToWE85NYFvkUllEkUxU46WqyDh6dElp6eZZUYdAky3EQTaEQu0gEJPLQAfI3VLDXCOGjs8VIXMrQgB1P6PewUgzZ+bwIbzpNbNObPKl8nayDULKE49e0C231ytRP4hG0dwMx+BOFJ6LoRgLWuHW7EA9ui1ukyttEvjhnyrrqU+9je+aa5m8NneXNscxFDrCZVO7uOn4vzHNUG1HVtmKUQ3vpLYMe+Yl/t0y8zOgomHvptguMaA4g060n2ZieuKyya9X39E896NNlcjHDP9maJB5psljTs7QOiUmeUBm7YxHQfqSkTegU+ln0azcimHFagLc3Kqa5XkyFzA1x9gLzuTgWLs4iZ/mW53Xxu9XKgUYy86kg2YG1fOh/npWBcNv4Kr4WHli2Rc1gD+GO7XCzJ0Wzz8/34EWl6DIqzbquAS6RdD90YWwpr1FmEuePt9k0G/02256209v+fT2eK/be4ac4ezCbrmwXy7kywWP1XytgLHjg8PVt71u5a0MlNa3e4RsRKmQx2ND4Gb6ksjHzI5OMTsOdtJcXLX3yzkznUbPmxEGbb4+vb5+fXImHm/MNakoQ0FLZa7L5pIfCMj5PFx8R1y2oIOXDSf53NCOpIX7uaEN1CArnRlHQ9kQ/fqEmuZuLwkNqLM31ACJds6/CvbJ0sIunNyYL9e/adn/A5yuFBLqEQAA"; break;
			case 'data/hypha.html': $zip = "H4sIAAAAAAACA32SSw6DMAxE95V6B8QFIrFO2bDtJYySQqTERmCQevuGT6kFoatYeePR2IluOfjyfssy3VowSxVrduxtqdV6LljtXNdk3l+lcVPmzCOfqe3zTB0BwlQRMjiMeIMCB4vjr0savrsWKgoB0AxJhafGYZJwD85f9YiMKl4f83bQWJGzLfZbhBBJXERx9p3537ABLrJ6wObpBs4TrCZmCon1Cc1k+8ERHiyE4EXEsVfYi7HlCNSNXX7ejVbbe8fJ18/yAdhEKSg1AgAA"; break;
			case 'index.php': $zip = "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"; break;
			case 'documentation.txt': $zip = "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"; break;
			case 'system/datatypes/text.php': $zip = "H4sIAAAAAAACA+1Z64/iOBL/DH+FF6FxWEGjlW6/QENrZnZHd1JrerQ995BGLdYkhvjaibOxA83dzv9+VX6EEOjXdp/uVtoPCOK43lW/KpvziyItuuNvu533kmk9IYbfmYKtebeTsjyRXJPUZJLgku52up1rzslbqdWk2zn/BIvz7rfjbqef7oqU4fPnXcH1lxsyIzSwolMkjJF/zZ7AN88TTZCG/Lvb6RTVUoqY9O8yCfuBoLOq8tgIlZPFIla5NmUVm6iP1JdCm48q4UPS3wi+HVgGnYKVPDeTyaPbkX+nb1KhR3MQB7rmfEv+kclor/MQnyeTrJJGXIp8XTF5lfvFDS816KWv8jar0VwqlnwoVfZBSB7RhBk2tr4b07MDXUbzNTdvjSnFsjKwUyR04Ljhp+Rr2MfL9yrLIAzvmZRLFt86/a7ZBvV7W5ZsF1nZQ0I1LnoOD1L/xEF9c0RfumXH4WsrAMtKyCTybtZbYeKURN7qRgA6MdOcUJ4IQyd2IbgGlyKnXKezLDm7nTYITMlyLZnhLap6/RRpwlcMgjMhfrNO1dbv+3rCBPfa6TkeE5OWakt4WaqSiBUpSrEBOTbNidCk5L9UHFyYEDA2VZUhsRSQXESq9RpWRY58gDD6RmjNIc8W1z9eX//l6uMXakvhUq1FTm8G5M2boKCX8UGy9QAEmKrMSa6MWO0iahWBmCwWEZVIOjJqhJ5tJgWoDaKqwmmZs4wTiC6RDLMTViQEnazAIJNy2Cl5jAa4Yq59itU8mm9LYfhn9aPkGVgVUdyEDEEFdNQn/xhiHF4PziihZwxzS+jb6IRhBwVxWhbqi1UAsqyrFiJP+N2lt0I3ZTYK1y0GW1teAZUJ1Dwgihlan6hc7gikPayzOOYIPAqCzDdCVRjdjbAFbL1Vh9THWD9qQcYgtkMCBfx3cSveO8FREwUSFVe415Mc6T8kj6TNBekvPl1df/5CPdjQGzIh9GQ6sASqC3N8KxLQSZNtyoMxmMxHOfuw7FDMwbGMJKUqSKK2uUsxtdp7sNt5zFlefx9x/3Rts1OVzmneqE4QCzqyWgRaEJJ5GDQKcAVRzyzGAUQZo6yF1sQj7wWrOv0Fc4gwIxm75W/tQ9SKzxlFvD5M/iE5xE8IxjSwdNI9y3f2IcJaDqgKCeDF1jSPlaMHb02R1vH3tF8bfsJSR4SEsDzgMAfJtjIaYGudt/ediyWXmr/YVbBmJaKT4BOC8Li/LNXreqvzclv2Lnu2QXvS/0IOnOpzrtPe1+fw7av2t6d1MhfW53Wy5/SskDrYtyg2qZ/p2SN97OfX7GPt7nTUnFA5iAAauRJcJrYf2WAEley7MIZow0xl61EtF/BQ4vB0Me+eJ2JD7CA962luA96DzTvJZ70C20C+nnxf3E0hC0t0/VJBymRuqVDadolJySEfxYZPe8BwOT+/mNlEFUaC7y7m5+PlnJyLvICkMDDMz3qIej0bJfc7eLVHNkxWsAgsHvT2xbwHHXkpq3LWAxIBUzGvN9sGMMUNt3xXFQ/sGIO+Tb3ilMe3S3V3oJvzIKhku8BRYGczinVLeJwqAgiILHjiefGkR8nFHAR5p3jCkT0RDDAAY4gAfPl47uX6IaCHlC+aC6iLgRMAki6eOIxAooDcRSw5y6MT4xGNWR5zSRuN8iWdsNH/7gc/L/II+X477h1YZI88r2RP41z1uF3urPVqVrUhvHHsuQ/Hwxbf7v/HcP4cmK6N80X1fwTWr4a1vRZQpSJJeH4KpvYIagHpPryC44D9ghOAWq0o4um4Cd7Blj1+uwGwITGcryxChf1XhfGjeZRXUh77Btk5VvPum3ypi+nvrmP8gdh/IPYRYvsLM7eDvjJ+I+eoD6jg4Xst1ZJJ4u5I/1pimbmfmpdOcj0EzshRLoeTbLOOAKkdYT0xz0hzXyh2etO4SGi9mLQz2aviR9BDhh6v6M3Ux9POS/bmrI5IA7UIXqsE1SK8WcF7PoXHerxpbXSvAUnBW8AOdvMkdKnarm9mLSXJr7+S2l3129p/9q3TY/+yee/mTrbu3gkaITr0ngunfYH39zZGgftJXB7srx/RR1W+UqWpctgud2S7yzwGQZFRQ9z1OgnTOKlKCed2RbacSol3OvZsvwTHQcqQJQde3B4ZoPHYw0LJMwV0cIZgK+iNW1Ym9sigQQZ/Opzt7dwCDfT8Ztw9C8i3ZtK2764rWLNp6R1Q5W7GaF/B2Je+sn4Qa5hYovuZrqAhOaZnFplchtRTwzkjaclXs16NDQ8f4nvzJ248H7M5Yoo9mlZF4o79J3Lk/hv8uj7wehETvj7mvjTBoVw4S3gZ0UsV2wKC1DurkeWsf2hbTWbVslc7fr6jkHeKJfQUfrlLq6jvA3cSxQJ0PTPZ2r3zRY33OMGek6S/MQ+dc34nifhQIbajnnhHGJHBN8uK/eEDHFViTwC4KfFWnbANE5ItAb4aUK4R4BGz6n8cwtCNzaQRWdDWB1W/231m64/Y5hozrFUd4Y7FabRnwrQDqwG2nUiCuVkErN5XJf7t9zdnHPrD0QzabgHZE3QNzg50QObkyNTDfoq/7+PhLuGafbsVvsaFNjDSQT3YCAz9wzuL6U7bYVMbzx0eNWbHjNDztIRhlp54gacUfypZQRWNtPgXn5Dv/lSYKbELWy7WqZkslUymNvf2GUYi2kSMARkBmEQH+kKDc6kHHoflcH8P+3Axov8cZaPdkPx5IiA3XUya9M7XUCUD2yQRC0FCrCos9hBZjMV3KKmRTHa7KzGsMTvEB/NziHErpVpgceSnlk044/0gVquojT/5Ue9vEA6O8epoP/5DA6o/Zd9+VvCYHPT11fm1C6fQ/wCX9LdqoB8AAA=="; break;
			case 'system/languages/nl.php': $zip = "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"; break;
			case 'system/languages/en.php': $zip = "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"; break;
		}
		if ($zip) return gzdecode(base64_decode($zip));
	}
?>
