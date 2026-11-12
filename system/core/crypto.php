<?php
	/*
		Constant: HYPHA_LEGACY_MD5_CUTOFF
		Legacy unsalted md5 password hashes are accepted until this
		moment (1 January 2027, 00:00 UTC). Until then a member who
		logs in gets a current hash automatically (see login()).
		From then on such a hash is refused, and the member sets a
		new password with "recover account".
	*/
	define('HYPHA_LEGACY_MD5_CUTOFF', gmmktime(0, 0, 0, 1, 1, 2027));

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
		// attacks, so this is not preferred. login() replaces
		// such a hash after a successful login.
		// hash_equals compares in constant time and, unlike ==,
		// does not treat two different "0e<digits>" hashes as
		// equal numbers.
		if ($hash === '')
			return false;
		if (substr($hash, 0, 1) != "$") {
			if (time() >= HYPHA_LEGACY_MD5_CUTOFF)
				return false;
			return hash_equals($hash, md5((string)$password));
		}

		// If the hash starts with $, it will be a hash that
		// includes the algorithm used an any options (salt,
		// number of iterations), so let password_verify sort
		// that out.
		if (function_exists('password_verify'))
			return password_verify((string)$password, $hash);

		// Non-md5 hash, but no password_verify available? This
		// means this hypha install was migrated from PHP >= 5.5
		// to < 5.5.
		notify('error', 'Cannot verify your password, PHP 5.5 required');
		return false;
	}

	/*
		Function: passwordNeedsRehash
		Returns true when the given hash is a legacy unsalted md5
		hash, or uses other parameters than hashPassword() would
		use now, so it should be replaced after a successful
		login.
	*/
	function passwordNeedsRehash($hash) {
		if (!function_exists('password_hash'))
			return false;
		if (substr($hash, 0, 1) != "$")
			return true;
		return password_needs_rehash($hash, PASSWORD_DEFAULT);
	}
