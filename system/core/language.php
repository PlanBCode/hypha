<?php
	/*
		Function: __
		Returns a string from the selected dictionary.

		Parameters:
		$msgid - identifier used as a key to fetch the corresponding string from the dictionary.
	*/
	function __($msgid, $args = null) {
		global $O_O;
		$hyphaDictionary = $O_O->getDictionary();
		$msg = array_key_exists($msgid, $hyphaDictionary) ? $hyphaDictionary[$msgid] : $msgid;
		if ($args) $msg = hypha_substitute($msg, $args);
		return $msg;
	}

	/*
		Function: languageOptionList
		Returns an html option list with languages

		Parameters:
		$select - which option should be preselected
		$omit - which language should be omitted
	*/
	function languageOptionList($select, $omit) {
		global $isoLangList;
		$langList = hypha_getUsedContentLanguages();

		$inUse = '';
		foreach ($langList as $code) if ($code!=$omit) $inUse.= '<option value='.$code.($code==$select ? ' selected' : '').'>'.$code.': '.$isoLangList[$code].'</option>';
		$html = '<optgroup label="in use">' . $inUse . '</optgroup>';

		$new = '';
		foreach($isoLangList as $code => $langName) if (!in_array($code, $langList) && $code!=$omit ) $new.= '<option value='.$code.($code==$select ? ' selected' : '').'>'.$code.': '.$langName.'</option>';
		$html .= '<optgroup label="add new">' . $new . '</optgroup>';
		return $html;
	}

	/*
		Variable: $isoLangList
		Associative array containing connecting iso639 language codes with their native name (and english translation)
	*/

    $isoLangList = json_decode(file_get_contents('system/languages/languages.json'), true);
