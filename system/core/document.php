<?php
	/*
		Title: Document

		This chapter describes how the HTML document is built
	*/

	require_once dirname(__FILE__).'/../php-dom-wrapper/All.php';

	/*
		Variable: $postProcessingList
		list of post processing functions

		Various invoked scripts and functions can add callback routines to the $postProcessingList array by calling <registerPostProcessingFunction>. Just before sending a document to the client this stack of commands is carried out.
		In this stage additional code can be loaded into the HTML document, for example the editor. By doing this in the end we can make the data load as light as possible, e.g. not load an editor when there's no need for it.
	*/
	$postProcessingList = array();

	/*
		Function: registerPostProcessingFunction
		add a function to the list of things to do with an <HTMLDocument> just before sending it to the client.

		Parameters:
		$func - function to call. This function to be called has to take one argument, a <HTMLDocument> instance to work on.
	*/
	function registerPostProcessingFunction($func) {
		global $postProcessingList;
		$postProcessingList[] = $func;
	}
	/*
		Class: HTMLDocument

		extension of the DOMDocument class
	*/

	class HTMLDocument extends DOMWrap\Document {
		/*
			Function: __construct
			creates an empty HTML file

			Parameters:
			$filename - optional parameter of HTML template file (HyphaFile instance).
		*/
		public function __construct($file = false) {
			parent::__construct('1.0', 'UTF-8');
			$this->preserveWhiteSpace = false;
			$this->formatOutput = true;
			if ($file)
				$contents = $file->read();
			else
				$contents = '<html><head></head><body></body></html>';
			$this->loadHTML('<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">'.$contents);
			$this->documentElement->setAttribute('xmlns', 'http://www.w3.org/1999/xhtml');
			$metaMime = $this->createElement('meta', '');
			$metaMime->setAttribute('http-equiv', "Content-Type");
			$metaMime->setAttribute('content', "text/html; charset=utf-8");
			$this->getElementsByTagName('head')->Item(0)->appendChild($metaMime);
		}

		/*
			Function: setTitle
			set document title element

			Parameters:
			$title -
		*/
		public function setTitle($title) {
			if ($this->getElementsByTagName('title')->length) $this->getElementsByTagName('title')->Item(0)->nodeValue = $title;
			else $this->getElementsByTagName('head')->Item(0)->appendChild($this->createElement('title', $title));
		}

		/*
			Function: setFavicon
			set document favicon

			Parameters:
			$href - icon URL
		*/
		public function setFavicon($href) {
			$style = $this->createElement('link', '');
			$style->setAttribute('href', $href);
			$style->setAttribute('rel', 'shortcut icon');
			$style->setAttribute('type', 'text/ico');
			$this->getElementsByTagName('head')->Item(0)->appendChild($style);
		}

		/*
			Function: linkScript
			adds a line to the head section of the document to link an external javascript

			Parameters:
			$src - url to a javascript
		*/
		public function linkScript($src) {
			$script = $this->createElement('script', '');
			$script->setAttribute('src', $src);
			$script->setAttribute('type', 'text/javascript');
			$this->getElementsByTagName('head')->Item(0)->appendChild($script);
		}

		/*
			Function: linkStyle
			adds a line to the head section of the document to link an external CSS document

			Parameters:
			$href - url to a CSS document
		*/
		public function linkStyle($href) {
			$style = $this->createElement('link', '');
			$style->setAttribute('href', $href);
			$style->setAttribute('rel', 'stylesheet');
			$style->setAttribute('type', 'text/css');
			$this->getElementsByTagName('head')->Item(0)->appendChild($style);
		}

		/*
			Function: writeScript
			adds content to the script element in the head section of the document, creates script element when needed

			Parameters:
			$js - javascript code to be added
		*/
		public function writeScript($js) {
			$script = $this->getElementById('jsAggregator');
			if (empty($script)) {
				$script = $this->createElement('script', '');
				$script->setAttribute('type', 'text/javascript');
				$script->setAttribute('id', 'jsAggregator');
				$this->getElementsByTagName('head')->Item(0)->appendChild($script);
			}
			$script->appendChild($this->createTextNode(preg_replace('#<script[^>]*>(.*)</script>#isU','$1', $js)));
		}

		/*
			Function: writeStyle
			adds content to the style element in the head section of the document, creates style element when needed

			Parameters:
			$css - CSS code to be added
		*/
		public function writeStyle($css) {
			$style = null;
			foreach($this->getElementsByTagName('style') as $s) if (!$s->hasAttribute('src')) $style = $s;
			if (empty($style)) {
				$style = $this->createElement('style', '');
				$style->setAttribute('type', 'text/css');
				$this->getElementsByTagName('head')->Item(0)->appendChild($style);
			}
			$style->appendChild($this->createTextNode(preg_replace('/(<style>|<\/style>)/i', '', $css)));
		}

		/*
			Function: writeToElement
			adds content to an element in the document with a certain id

			Parameters:
			$id - element identifier
			$data - content to be added
		*/
		public function writeToElement($id, $data) {
			if ($this->getElementById($id)) setInnerHtml($this->getElementById($id), getInnerHtml($this->getElementById($id)).$data);
		}

		/*
			Function: toString
			returns the document as formatted string
		*/
		public function toString() {
			global $postProcessingList;
			// executed operations staged by other modules...
			foreach($postProcessingList as $func) $func($this);

			// format <head> element into convenient order, could be removed to gain speed
			function ranking($node) {
				switch($node->tagName) {
					case 'meta': return '0'.$node->getAttribute('name');
					case 'title': return '1';
					case 'link': return '2'.$node->getAttribute('rel');
					case 'style': return '3';
					case 'script': return $node->hasAttribute('src') ? '4' : '5';
					default: return '6';
				}
			}

			// Sort the head tag children, keeping the order of nodes with the same rank
			// unchanged (this is important for script tags).
			$nodes = $this->getElementsByTagName('head')->Item(0)->childNodes;
			for($i=0;$i<$nodes->length-1;$i++)
				for ($j=$i+1;$j<$nodes->length;$j++)
					if(ranking($nodes->Item($j-1)) > ranking($nodes->Item($j))) {
						$swapnode = $this->getElementsByTagName('head')->Item(0)->removeChild($nodes->Item($j));
						$this->getElementsByTagName('head')->Item(0)->insertBefore($swapnode, $nodes->Item($j-1));
					}

			// convert to string
			$html = $this->saveHTML();

			// remove javascript code comments to minimize data transfer
			$html = preg_replace('%/\*(?:(?!\*/).)*\*/%s', '', $html);

			return $html;
		}
	}

	/*
		Class: HTMLForm

		Wrapper around a DomElement that contains form fields,
		to allow manipulating the form more easily.
	*/
	class HTMLForm {
		/** The DOM form being wrapped */
		public $elem;
		/** The data associated with this form */
		public $data;
		/** Any collected errors from validation */
		public $errors;
		/** The fields found in this form. Maps field name to its DOM element. */
		public $fields;
		/** The labels for each field. Maps field name to the label. */
		public $labels;
		/** The preview images in the form. Maps field name to the img element. */
		public $image_previews;

		/*
			Function: __construct
			Wraps an existing form

			Parameters:
			$form - A DOMWrap\Element that contains the form
			        fields.
		*/
		function __construct($form, $data = array()) {
			$this->elem = $form;
			$this->data = $data;
			$this->errors = array();
			$this->fields = array();
			$this->labels = array();
			$this->image_previews = array();

			// Look through the DOM to find form fields and
			// their labels
			foreach($form->find('input, select, textarea, label, img') as $elem) {
				if ($elem->tagName == 'label') {
					$name = $elem->getAttr('for');
					if ($name)
						$this->labels[$name] = $elem->text();
				} else if ($elem->tagName == 'img') {
					$name = $elem->getAttr('data-preview-for');
					if ($name)
						$this->image_previews[$name] = $elem;
				} else {
					$name = $elem->getAttr('name');
					if ($name)
						$this->fields[$name] = $elem;
				}
			}
		}

		/*
			Function: setData

			Set the data to be used by validation and
			updateDom().
		*/
		function setData($data) {
			$this->data = $data;
		}

		/*
			Function: updateDom

			Updates the form fields with the data set in
			$this->data (leaving the value unchanged if a
			field is not present in $this->data).

			Additionally, add any errors found during
			validation to an ul.form-errors list (creating
			it if needed).
		 */
		function updateDom() {
			// Put new values in the form
			foreach($this->fields as $name => $elem) {
				$value = $this->dataFor($name);
				if ($value !== null)
					$this->updateFormField($elem, $value);
			}

			foreach($this->image_previews as $name => $elem) {
				$this->updateImagePreview($elem, $this->dataFor($name));
			}

			// Show any errors
			if ($this->errors) {
				$list = $this->elem->find('ul.form-errors')->first();
				if (!$list) {
					$list = $this->elem->ownerDocument->createElement('ul')->addClass('form-errors');
					$this->elem->prepend($list);
				}

				foreach ($this->errors as $name => $error) {
					$item = $list->document()->createElement('li');
					$item->setText($this->labelFor($name) . ': ' . $error);
					$list->append($item);
				}
			}
		}

		/*
			Function updateFormField

			Update the value of the given DOM node that contains a
			single HTML form field with the value given.
		*/
		function updateFormField($field, $value) {
			if ($field->tagName == 'input' && $field->getAttr('type') == 'checkbox') {
				if ($value)
					$field->setAttr('checked', 'checked');
				else
					$field->removeAttr('checked');
			} else if ($field->tagName == 'input') {
				$field->setAttr('value', $value);
			} else if ($field->tagName == 'select') {
				foreach($field->find('option') as $option) {
					if ($option->getAttr('value') == $value)
						$option->setAttr('selected', 'selected');
					else
						$option->removeAttr('selected');
				}
			} else if ($field->tagName == 'textarea') {
				$field->setText($value);
			}
		}


		function updateImagePreview($field, $value) {
			if ($value) {
				$image = new HyphaImage($value);

				$width = $field->getAttribute('width');
				$height = $field->getAttribute('height');
				if (!$width)
					$width = 50;
				if (!$height)
					$height = $width;

				$field->setAttribute('src', $image->getUrl($width, $height));
			}
		}

		function labelFor($name) {
			if (array_key_exists($name, $this->labels))
				return $this->labels[$name];
			return $name;
		}

		function dataFor($name, $default = null) {
			if (is_array($this->data)) {
				if (array_key_exists($name, $this->data))
					return $this->data[$name];
			} else if ($this->data instanceof DOMElement)  {
				// Look for an attribute
				if ($this->data->hasAttribute($name))
					return $this->data->getAttribute($name);

				// Look for a descendent node with the
				// given name
				$result = $this->data->find($name)->first();
				// TODO: Use text or html?
				if ($result)
					return $result->text();
			}
			return $default;
		}

		function validateRequiredField($name) {
			if (!array_key_exists($name, $this->fields)) {
				$this->errors[$name] = __('field-not-found');
				return false;
			}
			$value = $this->dataFor($name);
			if (!$value) {
				$this->errors[$name] = __('required-field-missing');
				return false;
			}
			return true;
		}

		function validateEmailField($name) {
			$value = $this->dataFor($name);
			if (!$value)
				return true;

			if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
				$this->errors[$name] = __('invalid-email');
				return false;
			}
			return true;
		}

		function validateMoneyField($name) {
			$value = $this->dataFor($name);
			if (!$value)
				return true;

			//TODO: Allow , as separator and/or limit to 2
			//decimal places? Limit to positive numbers.
			if (!filter_var($value, FILTER_VALIDATE_FLOAT)) {
				$this->errors[$name] = __('invalid-money-amount');
				return false;
			}
			return true;
		}

		function handleImageUpload($name, $fileinfo) {
			if (!array_key_exists('error', $fileinfo) || $fileinfo['error'] == UPLOAD_ERR_NO_FILE)
				return;

			$result = HyphaImage::importUploadedImage($fileinfo);
			if (is_string($result))
				$this->errors[$name] = $result;
			else
				$this->data[$name] = $result->getFilename();
		}
	}
?>
