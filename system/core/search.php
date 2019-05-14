<?php
	// A *very* simple search function, simply returning the number of occurances of a substring in a text
	// To be replaced with something better, like http://phpir.com/simple-search-the-vector-space-model
	// Or perhaps some library like https://github.com/teamtnt/tntsearch
	function searchPatternInText ($pattern, $text) {
		$count = 1 + substr_count(strtolower(strip_tags($text)), strtolower($pattern));
		$result["relevance"] = 1.0-1.0/$count;
		return $result;
	}
