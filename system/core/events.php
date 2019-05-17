<?php
	include_once('various.php');
	include_once('document.php');

	/*
		Title: Events
		This chapter is about server client communication, ajax, system messages et cetera

		Section: HTTP requests
		On page calls and ajax requests, with or without posting form data.
		Our goal is to achieve the highest possible performance on older computers and poor internet connections. Therefore we have to balance minimization of data traffic with minimization of client sided data processing.

		Passing information from client to server is done through form posts. All HTML is put inside a form element with two hidden input elements, 'command' and 'argument'.

		(start code)
			<body>
				<form id="hyphaForm" method="post" action="">
					<input id="command" name="command" type="hidden" />
					<input id="argument" name="argument" type="hidden" />
					<-- all body HTML goes here -->
				</form>
			</body>
		(end code)

		Two specific javascript functions facilitate subsequent communication with the server: <hypha> is used for loading a new page or view, <ajax> can be used to communicate with the server from within a loaded page.

		Finally, a number of functions are available to generate the HTML/javascript code for buttons that trigger whole thing. See <createGotoPageButton>, <createExecuteCommandButton> and <createGotoPageAndExecuteCommandButton>.
	*/

	/*
		Function: addEventHandler
		Setup a <HTMLDocument> for ajax communication and changing pages and views.
		Parameters:
		$html - an instance of <HTMLDocument>
	*/
	registerPostProcessingFunction('addEventHandler');
	function addEventHandler($html) {
		// add a javascript function to process client commands and ajax calls
		ob_start();
?>
<script>
	/*
		Function: hypha
		Javascript function to load another page or view.

		Parameters:
		url - the hypha-formatted page identifier, e.g. 'en/hompage/defaultview'. When left empty the current paged is reloaded.
		cmd - php function to execute, e.g. 'save'
		arg - argument to pass to the cmd function
		form - the form tag to use for posting (if cmd or arg are given)
	*/
	function hypha(url, cmd, arg, form) {
		// update url
		url = url.replace(/\s\//g, '/').replace(/\s$/g, '').replace(/\s/g, '_');

		// if no cmd or arg is given, redirect to url
		if (!cmd && !arg) { window.location = url; return; }

		var $form = $(form);
		$form.attr('action', url);

		// Add a command and argument hidden field if needed
		// (but do not bother if it would be empty). Always set
		// the fields (even to the empty value) if they exist,
		// though.
		var $cmd = $form.find('input[name="command"]');
		if (cmd && $cmd.length < 1) {
			$cmd = $('<input type="hidden" name="command" />');
			$form.append($cmd);
		}
		$cmd.val(cmd);

		var $arg = $form.find('input[name="argument"]');
		if (arg && $arg.length < 1) {
			$arg = $('<input type="hidden" name="argument" />');
			$form.append($arg);
		}
		$arg.val(arg);

		// When there is a field with name "submit",
		// $form.submit (and $form[0].submit) will be a
		// reference to that field rather than a function we can
		// call to submit the field. To work around that, we
		// call the submit function from the prototype manually,
		// but that only submits the form, without running any
		// event handlers. So we first run the event handles
		// manually by calling trigger. To prevent trigger from
		// also submitting the form (and throwing an error if
		// there is a field named submit), we pass it an Event
		// object with preventDefault set, since then trigger
		// knows not to submit the form.
		var evt = new jQuery.Event("submit");
		evt.preventDefault();
		$form.trigger(evt);
		$form[0].__proto__.submit.call($form[0]);
	}

	/*
		Function: ajax
		Javascript function to interact with the server from within a loaded page in the client browser.

		Parameters:
		url - the hypha-formatted page identifier, e.g. 'en/hompage/defaultview'
		resultCallback - pointer to javascript function. This function is called when the ajax http request reponds. The response text is passed as an argument to this function.
		sendPostParams - optional flag to send form data. When this argument is set true the full hyphaForm data will be sent along through a http POST request. If omitted or set false a http GET request will be performed.
	*/
	function ajax(url, resultCallback, sendPostParams) {
		var request;  // The variable that makes Ajax possible!
		try{
			// Opera 8.0+, Firefox, Safari
			request = new XMLHttpRequest();
		} catch (e){
			// Internet Explorer Browsers
			try{
				request = new ActiveXObject("Msxml2.XMLHTTP");
			} catch (e) {
				try{
					request = new ActiveXObject("Microsoft.XMLHTTP");
				} catch (e){
					// Something went wrong
					alert("<?=__('browser-broke')?>");
					return false;
				}
			}
		}
		request.onreadystatechange = function() {
			if(request.readyState == 4) resultCallback(request.responseText);
		}
		if (sendPostParams) {
			var params = "";
			var form = document.forms['hyphaForm'];
			for (var i=0; i < form.elements.length; i++) if (form.elements[i].hasAttribute('name')) {
				params+= (params ? "&" : "") + form.elements[i].getAttribute('name') + "=";
				if (form.elements[i].tagName=='textarea') params+= encodeURIComponent(form.elements[i].innerHTML);
				else params+= encodeURIComponent(form.elements[i].value);
			}
			request.open('POST', url, true);
			request.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
			request.setRequestHeader("Content-length", params.length);
			request.setRequestHeader("Connection", "close");
			request.send(params);
		}
		else {
			request.open('GET', url, true);
			request.send(null);
		}
	}
</script>
<?php
		$html->writeScript(ob_get_clean());
	}

	/*
		Variable: $hyphaEventList
		list of allowed callback functions

		php scripts can register certain functions that can be called on client demand
		We need to make this list to prevent client sided eval requests being sent from form data
	*/

	/*
		Function: registerCommandCallback
		add function to a list of allowed callback functions: $hyphaEventList[]

		The function can return:
		 - 'reload' when the current page should be reloaded.
		   Almost all commands should do this, to prevent the
		   browser re-submitting the POST request on a refresh.
		   Any messages will be automatically preserved across
		   the reload.
		 - an array containing 'redirect' and an url, to
		   redirect to that url. Any messages will be
		   automatically preserved across the reload.
		 - false when the command was not actually handled and
		   should be retried later (after loading the requested
		   page).
		 - null when the command was handled, but no redirect
		   needs to happen.

		Parameters:
		$id - command id
		$func - associated function
	*/
	function registerCommandCallback($id, $func) {
		global $hyphaEventList;
		$hyphaEventList[$id] = $func;
	}

	/*
		Function: processCommand
		call a function-by-id from the list of allowed callback functions.

		Parameters:
		$id - command id
		$arg - argument to pass to the associated function
	*/
	function processCommand($id, $arg) {
		global $hyphaEventList;
		if (count($hyphaEventList) && array_key_exists($id, $hyphaEventList))
			return call_user_func($hyphaEventList[$id], $arg);
		return false;
	}

	function makeAction($langPageView, $command, $argument, HTMLForm $form = null) {
		if (null === $form) {
			global $hyphaHtml;
			$form = $hyphaHtml->getDefaultForm();
		}
		return 'hypha(\''.$langPageView.'\', \''.$command.'\', \''.$argument.'\', document.getElementById(\''.$form->getId().'\'));';
	}

	/*
		Function: createGotoPageButton
		function to create the HTML code for a button that loads another hypha page.

		Parameters:
		$label - button label, e.g. 'edit'
		$langPageView - page to load in lang/page/view format, e.g. 'en/home/edit'.
	*/
	function createGotoPageButton($label, $langPageView) {
		return '<input type="button" class="button" value="'.$label.'" onclick="hypha(\''.$langPageView.'\', \'\', \'\');" />';
	}

	/*
		Function: createExecuteCommandButton
		function to create the HTML code for a button to execute a certain serverside-command on the current page.
		The current page is reloaded, i.e. the form data is posted to itself alongside with the instruction to execute a certain command. This can be used for data operations like saving data, starting a new translation, etcetera.

		Parameters:
		$label - button label, e.g. 'save'
		$command - command-id for the function to call. This function can be registered using the function <registerCommandCallback>
		$argument - argument to pass to the callback function.
	*/
	function createExecuteCommandButton($label, $command, $argument) {
		return '<input type="button" class="button" value="'.$label.'" onclick="hypha(\'\', \''.$command.'\', \''.$argument.'\');" />';
	}

	/*
		Function: createGotoPageAndExecuteCommandButton
		function to create the HTML code for a button to execute a certain serverside-command on another page.
		The form data is posted to a given page alongside with the instruction to execute a certain command.

		Parameters:
		$label - button label, e.g. 'register'
		$langPageView - page to load in lang/page/view format, e.g. 'settings/register/userX'.
		$command - command-id for the function to call. This function can be registered using the function <registerCommandCallback>
		$argument - argument to pass to the callback function.
	*/
	function createGotoPageAndExecuteCommandButton($label, $langPageView, $command, $argument) {
		return '<input type="button" class="button" value="'.$label.'" onclick="hypha(\''.$langPageView.'\', \''.$command.'\', \''.$argument.'\');" />';
	}

	function getCsrfToken() {
		if (!isset($_SESSION['hyphaCsrfToken'])) {
			session_start();
			regenerateCsrfToken();
			session_write_close();
		}

		return $_SESSION['hyphaCsrfToken'];
	}

	/*
		Function: regenerateCsrfToken

		Regenerate the CSRF token. Should be called when a new
		session starts, such as during login. Should be called
		while the session is already open (e.g. between
		session_start() and session_write_close()).
	*/
	function regenerateCsrfToken() {
		$_SESSION['hyphaCsrfToken'] = bin2hex(openssl_random_pseudo_bytes(8));
	}

	// Automatically insert the CSRF token into all forms in the
	// generated document
	registerPostProcessingFunction('injectCsrf');
	function injectCsrf(HTMLDocument $html) {
		$forms = $html->find('form');
		foreach ($forms as $form) {
			// if form does not have a csrf field, inject csrf field.
			if ($form->find('input[name=csrfToken]')->count() === 0) {
				$form->append($input = $html->create('<input name="csrfToken" type="hidden"'));
				$input->setAttr('value', getCsrfToken());
			}
		}
	}

	// execute posted commands
	function executePostedCommand() {
		if(isset($_POST['command'])) {
			if (!isset($_POST['csrfToken']) || $_POST['csrfToken'] != getCsrfToken()) {
				notify('error', __('csrf-error'));
				unset($_POST['command']);
				return;
			}

			$result = processCommand($_POST['command'], isset($_POST['argument']) ? $_POST_['argument'] : null);
			if ($result !== false) {
				// Command was handled
				unset($_POST['command']);
				processCommandResult($result);
			}
		}
	}

	function processCommandResult($result) {
		if (!$result)
			return;

		// Command requests a reload
		if ($result === 'reload') {
			$url = preserveNotifications($_SERVER['REQUEST_URI']);
			header('Location: ' . $url);
			exit;
		}

		// Command requests a redirect
		if (count($result) == 2 && $result[0] == 'redirect') {
			$url = preserveNotifications($result[1]);
			header('Location: ' . $url);
			exit;
		}
	}

//	/*
//		Section: Scheduler
//		On timed events.
//
//		The scheduler implements a poor man's timing process which doesn't depend on autonomous server sided software to run. Jobs can be scheduled and their anticipated execution time is written into a file. Every time the website is visited the script checks this file to see if any timers have expired.
//
//		This actually works pretty neat, provided... the website has enough visitors. If not, the whole thing will not be very accurate but still deliver the goods whenever someone stumbles upon the site. If that doens't happen at all, well what or whom exactly are we schedulding for...
//
//		Another caveat might be that scheduled jobs that are time consuming will bother random visitors, who get a slower response than they deserve...
//	*/
//
//	/*
//		Function: addScheduler
//		Setup a <HTMLDocument> for scheduling jobs.
//
//		Parameters:
//		$html - an instance of <HTMLDocument>
//	*/
//	registerPostProcessingFunction('addScheduler');
//	function addScheduler($html) {
//		if (file_exists('schedule')) {
//			$schedule = unserialize(file_get_contents('schedule'));
//			foreach($schedule as $id => $job) if (time() > $job['time']) {
//				$cmd = $job['cmd'];
//				$arg = $job['arg'];
//				unset($schedule[$id]);
//				file_put_contents('schedule', serialize($schedule));
//				processCommand($cmd, $arg);
//			}
//		}
//	}
//
//	/*
//		Function: scheduleJob
//		Add job to the schedule.
//
//		Parameters:
//		$id - identifier for the job.
//		$duration - timeout in seconds.
//		$command - command-id for the function to call. This function can be registered using the function <registerCommandCallback>
//		$argument - argument to pass to the callback function.
//	*/
//	function scheduleJob($id, $duration, $command, $argument) {
//		if (file_exists('schedule')) $schedule = unserialize(file_get_contents('schedule'));
//		$job = array('time'=>time()+$duration, 'cmd'=>$command, 'arg'=>$argument);
//		$schedule[$id] = $job;
//		file_put_contents('schedule', serialize($schedule));
//	}

	/*
		Section: Notifications
		On popup system messages.
	*/

	/*
		Variable: $hyphaNotificationList
		list of notifications to add in the php post processing queue
	*/

	/*
		Function: preserveNotifications

		Store pending notifications in the session, so they can
		be reloaded after a redirect (by storing them in a
		session, instead of a GET parameter, they can be shown
		only once, even when the user refreshes that page). An
		identifier is assigned to the set of stored
		notifications, which will be passed through a GET
		parameter (to prevent showing the notifications in the
		wrong browser window).

		Parameters:
		$url - The url that will be redirected to

		Returns the url passed with the appropriate GET
		parameter added (if needed, otherwise the url is
		returned unmodified).
	*/
	function preserveNotifications($url) {
		global $hyphaNotificationList;
		if (count($hyphaNotificationList)) {
			$id = uniqid();
			session_start();
			$_SESSION['notifications'][$id] = $hyphaNotificationList;
			session_write_close();
			if (strpos($url, '?') === false)
				$url .= '?notify=' . rawurlencode($id);
			else
				$url .= '&notify=' . rawurlencode($id);
			$hyphaNotificationList = array();
		}
		return $url;
	}

	/*
		Function: addNotifier
		Setup a <HTMLDocument> for notifications from both php or javascript processes.
		Parameters:
		$html - an instance of <HTMLDocument>
	*/
	registerPostProcessingFunction('addNotifier');
	$GLOBALS['hyphaNotificationList'] = array();
	function addNotifier($html) {
		global $hyphaNotificationList;

		// Show any notifications from before a redirect. The
		// notifications themselves are stored in the session
		// (to prevent showing them again when the user
		// refreshes), but an identifier is passed through a GET
		// parameter to prevent showing a notification in the
		// wrong browser window.
		if (isset($_GET['notify'])) {
			$notify = $_GET['notify'];
			session_start();
			if (isset($_SESSION['notifications']) && isset($_SESSION['notifications'][$notify])) {
				$notifications = $_SESSION['notifications'][$notify];
				$hyphaNotificationList = array_merge($notifications, $hyphaNotificationList);

				unset($_SESSION['notifications'][$notify]);
			}
			session_write_close();
		}


		// Compatibility for older version that did not have the
		// #hyphaNotify element in their html. Add to the
		// header, to ensure it will be seen.
		if (!$html->getElementById('hyphaNotify'))
			$html->writeToElement('header', '<div id="hyphaNotify"></div>');

		if (count($hyphaNotificationList))
			foreach ($hyphaNotificationList as $msg)
				$html->writeToElement('hyphaNotify', $msg);

		// add a javascript function to process client commands and ajax calls
		ob_start();
?>
<script>
	/*
		Function: notify
		Javascript function to send a notification.

		Parameters:
		type - type of message, this refers to a CSS style class for markup, e.g. 'status' or 'error'
		message - text message.
	*/
	function notify(type, message) {
		document.getElementById('hyphaNotify').innerHTML = document.getElementById('hyphaNotify').innerHTML + '<div class="' + type + '" time="' + new Date().getTime() + '">' + message + '</div>';
		notifyTimer();
	}
	/*
		Function: notifyTimer
		Javascript function which handles removal of notifications after some time passed by.
	*/
	function notifyTimer() {
		if(document.getElementById('hyphaNotify')) {
			var now = new Date().getTime();
			var msgbox = document.getElementById('hyphaNotify');
			if (msgbox.children.length) {
				var msg = msgbox.firstChild;
				while (msg) {
					var next = msg.nextSibling;
					if (msg.nodeType === 1) {
						if(!msg.hasAttribute('time')) msg.setAttribute('time', now);
						else if (now - msg.getAttribute('time') > 10000) msg.parentNode.removeChild(msg);
					}
					msg = next;
				}
			}
			if (msgbox.children.length) {
				setTimeout(notifyTimer, 100);
			}
		}
	}
	setTimeout(notifyTimer, 1000);
</script>
<?php
		$html->writeScript(ob_get_clean());
	}

	/*
		Function: notify
		PHP function to send a notification.

		Parameters:
		type - type of message, this refers to a CSS style class for markup, e.g. 'status' or 'error'
		message - text message.
	*/
	function notify($type, $message) {
		global $hyphaNotificationList;
		if ($message) $hyphaNotificationList[] = '<div class="'.$type.'">'.$message.'</div>';
	}
