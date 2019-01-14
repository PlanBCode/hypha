<?php

require_once __DIR__ . '/document.php';

class WymHTMLForm extends HTMLForm {
	function getFormFieldTypes() {
		return array_merge(parent::getFormFieldTypes(), ['editor']);
	}

	function updateFormField($field, $value) {
		if ($field->tagName == 'editor')
			$field->setText($value);
		return parent::updateFormField($field, $value);
	}
}
