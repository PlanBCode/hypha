<?php

require_once __DIR__ . '/document.php';

class WymHTMLForm extends HTMLForm {
	function getFormFieldTypes() {
		return array_merge(parent::getFormFieldTypes(), ['editor']);
	}

	function getFieldType($field) {
		return $field->tagName == 'editor' ? 'textarea' : parent::getFieldType($field);
	}
}
