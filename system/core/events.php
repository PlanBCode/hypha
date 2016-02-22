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
		global $hyphaUrl;

		// place all body content in a form
		$body = $html->getElementsByTagName('body')->Item(0);
		ob_start();
?>
	<form name="hyphaForm" method="post" action="" accept-charset="utf-8">
		<input id="command" name="command" type="hidden">
		<input id="argument" name="argument" type="hidden">
		<?=getInnerHtml($body)?>
	</form>
<?
		setInnerHtml($body, ob_get_clean());

		// add a javascript function to process client commands and ajax calls
		ob_start();
?>
<script>
	/*
		Variable: baseUrl
		Javascript variable containing the base url from the document location object.
	*/
	var baseUrl = '<?=$hyphaUrl?>';
	var postProcessingList = new Array();

	/*
		Function: hypha
		Javascript function to load another page or view.

		Parameters:
		url - the hypha-formatted page identifier, e.g. 'en/hompage/defaultview'. When left empty the current paged is reloaded.
		cmd - php function to execute, e.g. 'save'
		arg - argument to pass to the cmd function
	*/
	function hypha(url, cmd, arg) {
		url = baseUrl + url.replace(/\s\//g, '/').replace(/\s$/g, '').replace(/\s/g, '_');
		document.getElementById('command').value = cmd;
		document.getElementById('argument').value = arg;
		document.forms['hyphaForm'].action = url;
		for(i=0; i<postProcessingList.length; i++) postProcessingList[i]();
		if (cmd||arg) document.forms['hyphaForm'].submit();
		else window.location = url;
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
			request.open('POST', baseUrl+url, true);
			request.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
			request.setRequestHeader("Content-length", params.length);
			request.setRequestHeader("Connection", "close");
			request.send(params);
		}
		else {
			request.open('GET', baseUrl+url, true);
			request.send(null);
		}
	}
</script>
<?
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
		if (count($hyphaEventList) && array_key_exists($id, $hyphaEventList)) {
			if(is_array($hyphaEventList[$id])) return $hyphaEventList[$id][0]->{$hyphaEventList[$id][1]}($arg);
			else return $hyphaEventList[$id]($arg);
		}
	}

	function makeAction($langPageView, $command, $argument) {
		return 'hypha(\''.$langPageView.'\', \''.$command.'\', \''.$argument.'\');';
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

	// execute posted commands
	function executePostedCommand() {
		if(isset($_POST['command'])) {
			$result = processCommand($_POST['command'], $_POST['argument']);
			unset($_POST['command']);
			return $result;
		}
	}

	/*
		Section: Scheduler
		On timed events.

		The scheduler implements a poor man's timing process which doesn't depend on autonomous server sided software to run. Jobs can be scheduled and their anticipated execution time is written into a file. Every time the website is visited the script checks this file to see if any timers have expired.

		This actually works pretty neat, provided... the website has enough visitors. If not, the whole thing will not be very accurate but still deliver the goods whenever someone stumbles upon the site. If that doens't happen at all, well what or whom exactly are we schedulding for...

		Another caveat might be that scheduled jobs that are time consuming will bother random visitors, who get a slower response than they deserve...
	*/

	/*
		Function: addScheduler
		Setup a <HTMLDocument> for scheduling jobs.

		Parameters:
		$html - an instance of <HTMLDocument>
	*/
	registerPostProcessingFunction('addScheduler');
	function addScheduler($html) {
		if (file_exists('schedule')) {
			$schedule = unserialize(file_get_contents('schedule'));
			foreach($schedule as $id => $job) if (time() > $job['time']) {
				$cmd = $job['cmd'];
				$arg = $job['arg'];
				unset($schedule[$id]);
				file_put_contents('schedule', serialize($schedule));
				processCommand($cmd, $arg);
			}
		}
	}

	/*
		Function: scheduleJob
		Add job to the schedule.

		Parameters:
		$id - identifier for the job.
		$duration - timeout in seconds.
		$command - command-id for the function to call. This function can be registered using the function <registerCommandCallback>
		$argument - argument to pass to the callback function.
	*/
	function scheduleJob($id, $duration, $command, $argument) {
		if (file_exists('schedule')) $schedule = unserialize(file_get_contents('schedule'));
		$job = array('time'=>time()+$duration, 'cmd'=>$command, 'arg'=>$argument);
		$schedule[$id] = $job;
		file_put_contents('schedule', serialize($schedule));
	}

	/*
		Section: Notifications
		On popup system messages.
	*/

	/*
		Variable: $hyphaNotificationList
		list of notifications to add in the php post processing queue
	*/

	/*
		Function: addNotifier
		Setup a <HTMLDocument> for notifications from both php or javascript processes.
		Parameters:
		$html - an instance of <HTMLDocument>
	*/
	registerPostProcessingFunction('addNotifier');
	function addNotifier($html) {
		global $hyphaNotificationList;
		// add hyphaNotify element to body
		$body = $html->getElementsByTagName('body')->Item(0);
		$msgdiv = $html->createElement('div', '');
		$msgdiv->setAttribute('id', 'hyphaNotify');
		$msgdiv->setAttribute('style', 'visibility:'.(count($hyphaNotificationList)?'visible':'hidden').';');
		$body->appendChild($msgdiv);
		if (count($hyphaNotificationList)) foreach ($hyphaNotificationList as $msg) $html->writeToElement('hyphaNotify', $msg);
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
						else if (now - msg.getAttribute('time') > 5000) msg.parentNode.removeChild(msg);
					}
					msg = next;
				}
			}
			if (msgbox.children.length) {
				document.getElementById('hyphaNotify').style.visibility = 'visible';
				setTimeout(notifyTimer, 100);
			}
			else document.getElementById('hyphaNotify').style.visibility = 'hidden';
		}
	}
	setTimeout(notifyTimer, 1000);
</script>
<?
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
?>
