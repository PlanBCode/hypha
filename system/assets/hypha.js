/*
	The JavaScript of Hypha that is the same on every page: loading
	another page or view, ajax calls and notifications. It is a file
	of its own, so the browser caches it instead of receiving it inline
	with every page (#683).
*/
/*
	Function: hypha
	Javascript function to load another page or view.

	Parameters:
	url - the hypha-formatted page identifier, e.g. 'en/hompage/defaultview'. When left empty the current paged is reloaded.
	cmd - php function to execute, e.g. 'save'
	arg - argument to pass to the cmd function
	form - the form tag to use for posting (if cmd or arg are given)
	callback - optional callback function for making ajax calls
*/
function hypha(url, cmd, arg, form, callback = null) {
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

	if (callback) {
		ajax(url, callback, form);
		return;
	}

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
function ajax(url, resultCallback, form = false) {
	// Every browser since 2006 has XMLHttpRequest; the fallback to
	// ActiveXObject for Internet Explorer 6 is gone (#683).
	var request = new XMLHttpRequest();
	request.onreadystatechange = function() {
		if(request.readyState == 4) resultCallback(request.responseText);
	}
	if (form) {
		var params = "";
		for (var i=0; i < form.elements.length; i++) if (form.elements[i].hasAttribute('name')) {
			params+= (params ? "&" : "") + form.elements[i].getAttribute('name') + "=";
			if (form.elements[i].tagName=='textarea') params+= encodeURIComponent(form.elements[i].innerHTML);
			else params+= encodeURIComponent(form.elements[i].value);
		}
		request.open('POST', url, true);
		request.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
		request.send(params);
	}
	else {
		request.open('GET', url, true);
		request.send(null);
	}
}

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
