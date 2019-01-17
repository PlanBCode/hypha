<?php

require_once __DIR__ . '/document.php';

class WymHTMLForm extends HTMLForm {
	function getFormFieldTypes() {
		return array_merge(parent::getFormFieldTypes(), ['editor']);
	}

	function updateFormField($field, $value) {
		// Editor tags must always contain valid HTML, so use
		// setHtml rather than setText
		if ($field->tagName == 'editor')
			$field->setHtml($value);
		return parent::updateFormField($field, $value);
	}
}
