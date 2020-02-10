$(function() {
	$('input[data-image-uploader]').each(function () {
		// get input
		var $input = $(this);

		// Add preview
		var $preview;

		var createPreviewImage = function (src) {
			if (src) {
				$preview = $('<img />', {'class': 'preview_uploaded_image', 'src': src}).insertAfter($input);
				$('<a class="delete-uploaded-image-button" />')
					.on('click', function (e) {
						e.preventDefault();
						// call delete function
						if ($input.prop('defaultValue') === $preview.attr('src')) {
							$.post('delete_uploaded_image', {src: $preview.attr('src')});
						}

						// remove delete button
						$(this).remove();

						// remove image
						$preview.remove();

						$input.attr('value', null);

						// toggle input
						toggleInputAndDelete();
					})
					.insertAfter($preview);
			}
		};

		var toggleInputAndDelete = function () {
			$input.toggle(!$input.next().hasClass('preview_uploaded_image'));
		};

		$input.on('change', function () {
			createPreviewImage(window.URL.createObjectURL(this.files[0]));
			toggleInputAndDelete();
		});

		createPreviewImage($input.data('image-uploader-src'));
		toggleInputAndDelete();
	});
});
