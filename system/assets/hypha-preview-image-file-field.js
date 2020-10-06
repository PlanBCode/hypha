$(function() {
	$('img[data-preview-for]').each(function () {
		// get preview
		var $preview = $(this);

		// get form
		var $form = $preview.closest('form');

		// get input name
		var inputName = $preview.data('preview-for');

		// get input
		var $input = $form.find('input[name="' + inputName + '"][type="file"]');

		// get shadow input
		var $shadow = $form.find('input[data-file-shadow-value-for="' + inputName + '"]');

		// add button to remove the image file
		var addRemoveImageBtn = function () {
			$('<span class="hyphaRemoveButton">x</span>')
				.on('click', function (e) {
					e.preventDefault();

					// remove delete button
					$(this).remove();

					handleSrc(null);
				})
				.insertAfter($preview, true);
		};

		var handleSrc = function (src) {
			var hasSrc = !!src;

			if (src !== $preview.attr('src')) {
				if (hasSrc) {
					$preview.attr('src', src);
				} else {
					$preview.removeAttr('src');
				}

				// clear shadow file value, indicating a change
				$shadow.attr('value', '');
			}

			if (hasSrc) {
				// hide input
				$input.hide();

				// unlink the label, it is confusing for the user if the label
				// triggers the file upload dialog while the input field itself is hidden.
				$form.find('label[for="' + inputName + '"]').attr('for', inputName + '_disabled');
				addRemoveImageBtn();
			} else {
				// enable input
				$input.show();

				// link the label
				$form.find('label[for="' + inputName + '_disabled"]').attr('for', inputName);

				// clear file value
				$input.attr('value', null);
			}
		};

		var init = function () {
			$input.on('change', function () {
				handleSrc(window.URL.createObjectURL(this.files[0]));
			});

			handleSrc($preview.attr('src'));
		};

		init();
	});
});
