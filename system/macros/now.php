<?php

/**
 * Trivial example macro, returns the current date and time.
 *
 * Parameters:
 *  - format: The datetime format, as passed to the PHP date() function.
 *    See https://www.php.net/manual/en/function.date.php
 *
 * Example usage:
 *
 *     <macro name="now" format="D, d M Y H:i:s"/>
 */
class NowMacro extends HyphaMacro {
	public function invoke() {
		$format = $this->macro_tag->getAttribute('format');
		if (!$format)
			$format = DATE_ISO8601;

		$span = $this->doc->createElement('span');
		$span->text(date($format));
		return $span;
	}
}

return NowMacro::class;
