<?php
	/*
		Title: Language

		This chapter describes the hypha user interface.
	*/

	$hyphaDictionary = array();

	/*
		Function: loadUserInterfaceLanguage
		Selects a dictionary for the user interface.

		Parameters:
		$path - path to the folder whers the language files are found.
		$lang - language identifier
	*/
	function loadUserInterfaceLanguage($path, $lang) {
		global $hyphaDictionary;
		$lang = file_exists($path.'/'.$lang.'.php') ? $lang : 'en';
		include($path.'/'.$lang.'.php');
		$hyphaDictionary = $LANG;
	}

	/*
		Function: __
		Returns a string from the selected dictionary.

		Parameters:
		$msgid - identifier used as a key to fetch the corresponding string from the dictionary.
	*/
	function __($msgid) {
		global $hyphaDictionary;
		return array_key_exists($msgid, $hyphaDictionary) ? $hyphaDictionary[$msgid] : $msgid;
	}

	/*
		Variable: $isoLangList
		Associative array containing connecting iso639 language codes with their native name (and english translation)
	*/
	$isoLangList = json_decode('{"aa":"\u02bfAf\u00e1r af (Afar)","ab":"\u0430\u04a7\u0441\u0443\u0430 \u0431\u044b\u0437\u0448\u04d9\u0430 (Abkhaz)","af":"Afrikaans (Afrikaans)","am":"(Amhari) \u0720\u072b\u0722\u0710 \u0724\u0718\u072a\u071d\u071d\u0710","ar":"(Arabic) \u0639\u0631\u0628\u064a","as":"\u0985\u09b8\u09ae\u09c0\u09af\u09bc\u09be (Assamese)","ay":"aymar aru (Aymara)","az":"Az\u0259rbaycan dili (Azerbaijani)","ba":"\u0431\u0430\u0448\u04a1\u043e\u0440\u0442 \u0442\u0435\u043b\u0435 (Bashkir)","be":"\u0411\u0435\u043b\u0430\u0440\u0443\u0441\u043a\u0456 (Belarussian)","bg":"\u0411\u044a\u043b\u0433\u0430\u0440\u0441\u043a\u0438 (Bulgarian)","bh":" (Bihari)","bi":" (Bislama)","bn":"\u09ac\u09be\u0982\u09b2\u09be (Bengali; Bangla)","bo":"\u0f56\u0f7c\u0f51\u0f0b\u0f66\u0f90\u0f51\u0f0b (Tibetan)","br":"ar brezhoneg (Breton)","ca":"Catal\u00e0 (Catalan)","co":"corsu (Corsican)","cs":"\u010ce\u0161tina (Czech)","cy":"Cymraeg (Welsh)","da":"Dansk (Danish)","de":"Deutsch (German)","dz":"\u0f62\u0fab\u0f7c\u0f44\u0f0b\u0f41 (Bhutanese)","el":"\u0395\u03bb\u03bb\u03b7\u03bd\u03b9\u03ba\u03ac (Greek)","en":"English (English)","eo":"Esperanto (Esperanto)","es":"Espa\u00f1ol (Spanish)","et":"Eesti (Estonian)","eu":"euskara (Basque)","fa":"(Persian) \u067e\u0627\u0631\u0633\u06cc","fi":"Suomi (Finnish)","fj":"Vakaviti (Fijian)","fo":"F\u00f8royskt (Faroese)","fr":"Fran\u00e7ais (French)","fy":"Frysk (Frisian)","ga":"Gaeilge (Irish Gaelic)","gd":"G\u00e0idhlig (Scots Gaelic)","gl":"Galego (Galician)","gn":"Ava\u00f1e\'\u1ebd (Guarani)","gu":"z\u0a97\u0ac1\u0a9c\u0ab0\u0abe\u0aa4\u0ac0 (Gujarati)","ha":"(Hausa) \u062d\u064e\u0648\u0652\u0633\u064e","he":"(Hebrew) \u05e2\u05d1\u05e8\u05d9\u05ea","hi":"\u0939\u093f\u0902\u0926\u0940 (Hindi)","hr":"Hrvatski (Croatian)","hu":"Magyar (Hungarian)","hy":"\u0540\u0561\u0575\u0565\u0580\u0567\u0576 (Armenian)","ia":"Interlingua","id":"Bahasa Indonesia (Indonesian)","ie":"Interlingue","ik":"Inupiatun (Inupiak)","is":"\u00cdslenska (Icelandic)","it":"Italiano (Italian)","iu":"\u1403\u14c4\u1483\u144e\u1450\u1466 (Inuktitut)","ja":"\u65e5\u672c\u8a9e (Japanese)","jw":"basa Jawa (Javanese)","ka":"\u10e5\u10d0\u10e0\u10d7\u10e3\u10da\u10d8 (Georgian)","kk":"\u049a\u0430\u0437\u0430\u049b \u0442\u0456\u043b\u0456 \/ Qazaq tili (Kazakh) \u0642\u0627\u0632\u0627\u0642 \u0674\u062a\u0649\u0644\u0649","kl":"Kalaallisut (Greenlandic)","km":" (Cambodian)","kn":"\u0c95\u0ca8\u0ccd\u0ca8\u0ca1 (Kannada)","ko":"\ud55c\uad6d\uc5b4 (Korean)","ks":"\u0915\u0949\u0936\u0941\u0930 (Kashmiri) \u0643\u0672\u0634\u064f\u0631","ku":"Kurd\u00ed \/ \u043a\u2019\u00f6\u0440\u0434\u0438 (Kurdish) \u06a9\u0648\u0631\u062f\u06cc","ky":"(Kirghiz) \u0642\u0649\u0631\u0639\u0649\u0632","la":"Lingua Latina (Latin)","ln":"ling\u00e1la (Lingala)","lo":"\u0e9e\u0eb2\u0eaa\u0eb2\u0ea5\u0eb2\u0ea7 (Laothian)","lt":"Lietuviskai (Lithuanian)","lv":"Latvie\u0161u (Latvian)","mg":"Fiteny Malagasy (Malagasy)","mi":"te Reo M\u0101ori (Maori)","mk":"\u041c\u0430\u043a\u0435\u0434\u043e\u043d\u0441\u043a\u0438 (Macedonian)","ml":"\u0d2e\u0d32\u0d2f\u0d3e\u0d33\u0d02 (Malayalam)","mn":"\u043c\u043e\u043d\u0433\u043e\u043b (Mongolian)","mo":"\u043b\u0438\u043c\u0431\u0430 \u043c\u043e\u043b\u0434\u043e\u0432\u0435\u043d\u044f\u0441\u043a\u044d (Moldavian)","mr":"\u092e\u0930\u093e\u0920\u0940 (Marathi)","ms":"Bahasa melayu (Malay)","mt":"Malti (Maltese)","my":"\u1017\u1019\u102c\u1005\u1000\u102c\u1038 (Burmese)","na":"Ekakair\u0169 Naoero (Nauru)","ne":"\u0928\u0947\u092a\u093e\u0932\u0940 (Nepali)","nl":"Nederlands (Dutch)","no":"Norsk (Norwegian)","oc":" (Occitan)","om":"Afaan Oromo (Afan Oromo)","or":"\u0b13\u0b5c\u0b3f\u0b06 (Oriya)","pa":"\u0a2a\u0a70\u0a1c\u0a3e\u0a2c\u0a40 (Punjabi)","pl":"Polski (Polish)","ps":"(Pashto, Pushto) \u067e\u069a\u062a\u0648","pt":"Portugu\u00eas (Portuguese)","qu":"Qhichwa (Quechua)","rm":" (Rhaeto-Romance)","rn":"\u00edkiR\u01d4ndi (Kirundi)","ro":"Rom\u00e2n\u0103 (Romanian)","ru":"\u0420\u0443\u0441\u0441\u043a\u0438\u0439 (Russian)","rw":"Ikinyarwanda (Kinyarwanda)","sa":"\u0938\u0902\u0938\u094d\u0915\u0943\u0924\u092e\u094d (Sanskrit)","sd":"(Sindhi) \u0633\u0646\u068c\u064a","sg":"Y\u00e2ng\u00e2 t\u00ee S\u00e4ng\u00f6 (Sangho)","sh":" (Serbo-Croatian)","si":"\u0dc3\u0dd2\u0d82\u0dc4\u0dbd (Sinhalese)","sk":"Sloven\u010dina (Slovak)","sl":"Sloven\u0161\u010dina (Slovenian)","sm":"Gagana Samoa (Samoan)","sn":"chiShona (Shona)","so":"af Soomaali (Somali)","sq":"Shqip (Albanian)","sr":"\u0441\u0440\u043f\u0441\u043a\u0438 (Serbian)","ss":"siSwati (Siswati)","st":"seSotho (Sesotho)","su":"basa Sunda (Sundanese)","sv":"Svenska (Swedish)","sw":"Swahili (Swahili)","ta":"\u0ba4\u0bae\u0bbf\u0bb4\u0bcd (Tamil)","te":"\u0c24\u0c46\u0c32\u0c41\u0c17\u0c41 (Telugu)","tg":"\u0442\u043e\u04b7\u0438\u043a\u04e3 (Tajik)","th":"\u0e44\u0e17\u0e22 (Thai)","ti":"\u1275\u130d\u122d\u129b (Tigrinya)","tk":"\u0442\u04af\u0440\u043am\u0435\u043d\u0447\u0435 (Turkmen)","tl":"Tagalog (Tagalog)","tn":"seTswana (Setswana)","to":"chiTonga (Tonga)","tr":"T\u00fcrk\u00e7e (Turkish)","ts":"xiTsonga (Tsonga)","tt":"\u0442\u0430\u0442\u0430\u0440\u0447\u0430 \/ tatar tele (Tatar) \u062a\u0627\u062a\u0627\u0631\u0686\u0627","tw":"twi (Twi)","ug":"\u0423\u0439\u0493\u0443\u0440 (Uighur)\u0626\u06c7\u064a\u063a\u06c7\u0631","uk":"\u0423\u043a\u0440\u0430\u0457\u043d\u0441\u044c\u043a\u0430 (Ukrainian)","ur":"(Urdu) \u0627\u0631\u062f\u0648","uz":"o\'zbek tili \/ \u045e\u0437\u0431\u0435\u043a \u0442\u0438\u043b\u0438 (Uzbek) \u0623\u06c7\u0632\u0628\u06d0\u0643 \ufe97\ufef4\ufee0\u06cc","vi":"Ti\u1ebfng Vi\u1ec7t (Vietnamese)","vo":"Volapuk","wo":"Wollof (Wolof)","xh":"isiXhosa (Xhosa)","yi":"(Yiddish) \u05f2\u05b4\u05d3\u05d9\u05e9","yo":"Yor\u00f9b\u00e1 (Yoruba)","za":"Saw cuengh \/ Sa\u026f cue\u014b\u0185 (Zhuang)","zh":"\u4e2d\u6587 (Chinese)","zu":"isiZulu (Zulu)"}', true);

	/*
		Function: languageOptionList
		Returns an html option list with languages

		Parameters:
		$select - which option should be preselected
		$omit - which language should be omitted
	*/
	function languageOptionList($select, $omit) {
		global $isoLangList;
		$html = '';
		foreach($isoLangList as $code => $langName) if ($code!=$omit) $html.= '<option value='.$code.($code==$select ? ' selected' : '').'>'.$code.': '.$langName.'</option>';
		return addslashes($html);
	}
?>
