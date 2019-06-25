/**
 * Creates an info popup.
 *
 * @param clickEvent Element, click event, used to determine position of popup
 * @param key string, the key for with to get the info
 * @param language string
 * @param infoButton Element, the info button for which to make an info popup
 */
function toggleInfoPopup(clickEvent, key, language, infoButton) {
	// If it has a clicked state, close the popup and remove the state.
	var $infoButton = $(infoButton);
	if ($infoButton.hasClass('clicked')) {
		$infoButton.trigger('closeMe');
		return;
	}

	// Directly set the clicked class, this also prevents the spamming of the system before the result is returned.
	$infoButton.addClass('clicked');

	// Create an empty popup.
	var $helpPopup = $('<div></div>').appendTo('body');

	// Fetch popup content with Ajax and place it in the popup.
	var url = 'help/' + encodeURIComponent(key) + '/' + encodeURIComponent(language);
	$.get(url, function (response) {
		positionTextBox(clickEvent, $helpPopup, response);

		// Create close function.
		$infoButton.bind('closeMe', function () {
			$helpPopup.remove();
			$infoButton.removeClass('clicked');
			$infoButton.unbind('closeMe');
		});

		// Create close button.
		var $closeButton = $('<button></button>').appendTo($helpPopup);
		$closeButton.on('click', function () {
			$infoButton.trigger('closeMe');
		});
		$closeButton.css('left', $helpPopup.css('width'));
		$closeButton.addClass('closebutton');
		$closeButton.append(document.createTextNode('X'));
	});
}

/**
 * Positions the textBox next to where the clickEvent took place.
 *
 * @param clickEvent Element
 * @param $textBox jQuery Element
 * @param text string
 */
function positionTextBox(clickEvent, $textBox, text) {
	var x = clickEvent.clientX; // Get the horizontal coordinate
	var y = clickEvent.clientY; // Get the vertical coordinate
	// determine if window is scrolled
	if (window.pageXOffset !== undefined) {
		// All browsers, except IE9 and earlier
		x += window.pageXOffset;
		y += window.pageYOffset;
	} else { // IE9 and earlier
		x += document.documentElement.scrollLeft;
		y += document.documentElement.scrollTop;
	}
	// get viewport
	var sx = document.documentElement.clientWidth;
	var sy = document.documentElement.clientHeight;
	var windowCenter = sx / 2;

	$textBox.css('fontSize', '12px');
	$textBox.css('fontStyle', 'normal');
	$textBox.css('padding', '10px');
	$textBox.css('position', 'absolute');
	$textBox.html(text);
	var box = {
		width: $textBox.innerWidth(),
		height: $textBox.innerHeight(),
	};

	$textBox.css('width', box.width + 'px');
	$textBox.css('height', box.height + 'px');
	$textBox.addClass('notelo');

	if ((y < box.height) && (x < box.width)) {
		$textBox.css('left', x + box.width / 3 + 'px');
		$textBox.css('top', y + 15 + 'px');
	} else if ((y < box.height) && ((box.width < x) && (x < sx - box.width))) {
		$textBox.css('left', x + 'px');
		$textBox.css('top', y + 15 + 'px');
		$textBox.addClass('up');
	} else if ((y < box.height) && (x > sx - box.width)) {
		$textBox.css('left', x - box.width + 'px');
		$textBox.css('top', y + 15 + 'px');
	} else if (((y >= box.height) && (y < sy - box.height)) && (x < windowCenter)) {
		$textBox.css('left', x - 40 + box.width / 2 + 'px');
		$textBox.css('top', y - 10 - box.height / 2 + 'px');
		$textBox.addClass('left');
	} else if (((y >= box.height) && (y < sy - box.height)) && (x > windowCenter)) {
		$textBox.css('left', x - box.width + 'px');
		$textBox.css('top', y - 10 - box.height / 2 + 'px');
		$textBox.addClass('right');
	} else if ((y >= box.height) && (x < box.width)) {
		$textBox.css('left', x + box.width / 3 + 'px');
		$textBox.css('top', y - 10 - box.height + 'px');
	} else if ((y >= box.height) && ((box.width < x) && (x < sx - box.width))) {
		$textBox.css('left', x - box.width / 3 + 'px');
		$textBox.css('top', y - box.height - 40 + 'px');
		$textBox.addClass('down');
	} else if ((y >= box.height) && (x > sx - box.width)) {
		$textBox.css('left', x - box.width + 'px');
		$textBox.css('top', y - box.height - 15 + 'px');
	} else alert('Could not determine the area');
}
