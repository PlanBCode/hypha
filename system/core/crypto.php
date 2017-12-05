<?php
	function hashPassword($password) {
		// Prefer using the password_hash() PHP function, which
		// generates a new password hash using the default
		// hashing algorithm (which is bcrypt/blowfish using a
		// salt and a number of rounds of hashing up to at least
		// PHP7).
		if (function_exists('password_hash'))
			return password_hash($password, PASSWORD_DEFAULT);
		// On older PHP versions, use a simple md5 hash that was
		// used in previous versions of hypha.
		return md5($password);
	}

	function verifyPassword($password, $hash) {
		// Older versions of hypha, and new versions running on
		// PHP 5.4, use plain md5 passwords, which will not have
		// a $ sign at the start. Note that this is a weak
		// hashing strategy, which is also prone to timing
		// attacks, so this is not preferred.
		if (substr($hash, 0, 1) != "$")
			return md5($password) == $hash;

		// If the hash starts with $, it will be a hash that
		// includes the algorithm used an any options (salt,
		// number of iterations), so let password_verify sort
		// that out.
		if (function_exists('password_verify'))
			return password_verify($password, $hash);

		// Non-md5 hash, but no password_verify available? This
		// means this hypha install was migrated from PHP >= 5.5
		// to < 5.5.
		notify('error', 'Cannot verify your password, PHP 5.5 required');
		return false;
	}
