<?php
namespace helpers;

/**
 * Class validate is a container for global method for validation average data types
 */
class validate {
	/**
	 * Return TRUE if value is not empty string or empty array.
	 *
	 * @param string|array $value
	 *
	 * @return bool
	 */
	public static function not_empty($value) {
		// NOTE: we cannot use empty(), because '0' will be empty, but it can be in user input and it's not empty input.
		return $value != '' && $value != [];
	}

	/**
	 * Return TRUE if value_1 is equal value_2
	 *
	 * @param mixed $value_1
	 * @param mixed $value_2
	 *
	 * @return bool
	 */
	public static function equal($value_1, $value_2) {
		return $value_1 == $value_2;
	}

	/**
	 * Return TRUE if value_1 is not equal value_2
	 *
	 * @param mixed $value_1
	 * @param mixed $value_2
	 *
	 * @return bool
	 */
	public static function not_equal($value_1, $value_2) {
		return $value_1 != $value_2;
	}

	/**
	 * Returns TRUE if provided value is a number, contains only digits.
	 * Ex: 123, "001", 100
	 *
	 * @param mixed $value
	 * @return bool
	 */
	public static function is_number($value) {
		if (!is_numeric($value)) {
			return false;
		}

		return (bool) preg_match('/^[0-9]+$/', (string) $value);
	}

	/**
	 * Returns TRUE if provided value is an array
	 *
	 * @param $value
	 *
	 * @return bool
	 */
	public static function is_array($value) {
		return is_array($value);
	}

	/**
	 * Returns TRUE if provided value exist in array
	 *
	 * @param $value
	 * @param array $array
	 * @return bool
	 */
	public static function in_array($value, array $array) {
		return in_array($value, $array);
	}

	public static function strict_in_array($value, array $array) {
		return in_array($value, $array, true);
	}

	/**
	 * Returns TRUE if provided value not exist in array
	 *
	 * @param $value
	 * @param array $array
	 * @return bool
	 */
	public static function not_in_array($value, array $array) {
		return !self::in_array($value, $array);
	}

	/**
	 * Returns TRUE if provided value is a scalar and can be converted to string
	 *
	 * @param $value
	 *
	 * @return bool
	 */
	public static function is_string($value) {
		return is_scalar($value);
	}

	/**
	 * Returns TRUE if length of string or array is equal or less
	 *
	 * @param string|array $value
	 * @param int $length
	 *
	 * @return bool
	 */
	public static function max_length($value, $length) {
		if (!is_numeric($length)) {
			return false;
		}

		if (is_scalar($value)) {
			return strlen($value) <= (int) $length;
		}

		if (is_array($value)) {
			return count($value) <= (int) $length;
		}

		return false;
	}

	/**
	 * Returns TRUE if length of string or array is equal or more
	 *
	 * @param string|array $value
	 * @param int $length
	 *
	 * @return bool
	 */
	public static function min_length($value, $length) {
		if (!is_numeric($length)) {
			return false;
		}

		if (is_string($value)) {
			return strlen($value) >= (int) $length;
		}

		if (is_array($value)) {
			return count($value) >= (int) $length;
		}

		return false;
	}

	/**
	 * Returns TRUE if string or array has exact length.
	 *
	 * @param string|array $value
	 * @param int $length
	 *
	 * @return bool
	 */
	public static function length($value, $length) {
		if (!is_numeric($length)) {
			return false;
		}

		if (is_string($value)) {
			return strlen($value) === (int) $length;
		}

		if (is_array($value)) {
			return count($value) === (int) $length;
		}

		return false;
	}

	/**
	 * Validates url to be correct
	 *
	 * @param $value
	 * @return bool
	 */
	public static function url($value) {
		return (bool) filter_var($value, FILTER_VALIDATE_URL);
	}

	/**
	 * Return TRUE if domain name has A or AAAA record. Method does not check connection to the server.
	 *
	 * @param string $value
	 *
	 * @return bool
	 */
	public static function website_exists($value) {
		if (!is_string($value)) {
			return false;
		}

		$value = trim($value);

		if (strpos($value, 'http://') === false && strpos($value, 'https://') === false) {
			$value = 'http://' . $value;
		}

		$data = @parse_url($value);

		if (!is_array($data)) {
			return false;
		}

		$a_record = false;
		$aaaa_record = checkdnsrr($data['host'], 'AAAA');

//		if (\environment::is_production()) {
//			$a_record = checkdnsrr($data['host'], 'A');
//		}

		if ($a_record || $aaaa_record) {
			return true;
		}

		return false;
	}

	/**
	 * Return TRUE is URL exists and return HTTP code not equal 404
	 *
	 * @param string $value
	 *
	 * @return bool
	 */
	public static function url_exists($value) {
		if (!is_string($value) || empty($value)) {
			return false;
		}

		$curl = @curl_init($value);

		if ($curl === false) {
			return false;
		}

		@curl_setopt_array($curl, array(
			CURLOPT_FAILONERROR => true,
			CURLOPT_HEADER => true,
			CURLOPT_NOBODY => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_MAXREDIRS => 3,
			CURLOPT_AUTOREFERER => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_CONNECTTIMEOUT => 5,
			CURLOPT_TIMEOUT => 10,
			CURLOPT_SSL_VERIFYHOST => 0,
		));

		@curl_exec($curl);

		if (@curl_errno($curl)) {
			@curl_close($curl);

			return false;
		}

		$code = @curl_getinfo($curl, CURLINFO_HTTP_CODE);
		@curl_close($curl);

		return $code != 404;
	}

	/**
	 * Return TRUE is the property is valid year number between 1900 and 2200
	 *
	 * @param int $value
	 *
	 * @return bool
	 */
	public static function year($value) {
		if (!is_numeric($value)) {
			return false;
		}

		return (self::is_number($value) && $value >= 1900 && $value <= 2200);
	}

	/**
	 * Returns TRUE if provided value is valid country calling (phone) code.
	 *
	 * @param int $value
	 *
	 * @return bool
	 */
	public static function country_calling_code($value) {
		if (!is_numeric($value)) {
			return false;
		}

		$value = (int) $value;

		// caching codes in memory for prevent kicking database during script/daemon lifetime
		static $codes;
		static $update_time;
		static $lifetime = 3600; // 1 hour, for daemons, reload cached list, because daemons can work without restart during months

		if (!isset($codes) || time() > $update_time) {
			$update_time = time() + $lifetime;
			$codes = [];

			$codes = \db::connect('syssql')
				->select("SELECT calling_code FROM sYra.country;")
				->execute()
				->fetch_column_values('calling_code');
		}

		return in_array($value, $codes);
	}

	/**
	 * Return TRUE if provide valid IPv4 or IPv6 address, if $allow_ipv6 is TRUE.
	 *
	 * @param string $value
	 * @param bool $allow_ipv6 - default is TRUE
	 *
	 * @return bool
	 */
	public static function ip($value, $allow_ipv6 = true) {
		return self::ipv4($value) || ($allow_ipv6 && self::ipv6($value));
	}

	/**
	 * Returns TRUE if provide valid IPv4 address
	 *
	 * @param string $value
	 *
	 * @return bool
	 */
	public static function ipv4($value) {
		return (bool) filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
	}

	/**
	 * Returns TRUE if provide valid IPv6 address
	 *
	 * @param string $value
	 *
	 * @return bool
	 */
	public static function ipv6($value) {
		return (bool) filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
	}

	/**
	 * Return TRUE if $text matched regular expression in $regex
	 *
	 * @param string $value
	 * @param string $regex
	 *
	 * @return bool
	 */
	public static function regex($value, $regex) {
		if (!is_string($value) || !is_string($regex)) {
			return false;
		}

		$regex = trim($regex);

		if (empty($value) || empty($regex)) {
			return false;
		}

		return (bool) preg_match($regex, $value);
	}

	/**
	 * Return TRUE if the string is date in dd/mm/YYYY or d/m/YYYY format.
	 *
	 * @param string $value
	 *
	 * @return bool
	 */
	public static function date($value) {
		if (!is_scalar($value)) {
			return false;
		}

		$date = \DateTime::createFromFormat('d/m/Y', $value);
		return $date !== false && !array_sum($date->getLastErrors());
	}

	/**
	 * Returns TRUE if the string is date in dd/mm/YYYY or d/m/YYYY format
	 * AND date is less than now.
	 *
	 * @param string $value
	 *
	 * @return bool
	 */
	public static function date_in_past($value) {
		if (!is_scalar($value)) {
			return false;
		}

		$date = \DateTime::createFromFormat("d/m/Y", $value);
		if ($date !== false && !array_sum($date->getLastErrors())) {
			$now = new \DateTime();

			return $date < $now;
		}

		return false;
	}

	/**
	 * Returns TRUE is provided string looks like valid E-Mail address
	 *
	 * @param string $value
	 *
	 * @return bool
	 */
	public static function email($value) {
		return (bool) preg_match('/^(?!(?:(?:\x22?\x5C[\x00-\x7E]\x22?)|(?:\x22?[^\x5C\x22]\x22?)){255,})(?!(?:(?:\x22?\x5C[\x00-\x7E]\x22?)|(?:\x22?[^\x5C\x22]\x22?)){65,}@)(?:(?:[\x21\x23-\x27\x2A\x2B\x2D\x2F-\x39\x3D\x3F\x5E-\x7E]+)|(?:\x22(?:[\x01-\x08\x0B\x0C\x0E-\x1F\x21\x23-\x5B\x5D-\x7F]|(?:\x5C[\x00-\x7F]))*\x22))(?:\.(?:(?:[\x21\x23-\x27\x2A\x2B\x2D\x2F-\x39\x3D\x3F\x5E-\x7E]+)|(?:\x22(?:[\x01-\x08\x0B\x0C\x0E-\x1F\x21\x23-\x5B\x5D-\x7F]|(?:\x5C[\x00-\x7F]))*\x22)))*@(?:(?:(?!.*[^.]{64,})(?:(?:(?:xn--)?[a-z0-9]+(?:-[a-z0-9]+)*\.){1,126}){1,}(?:(?:[a-z][a-z0-9]*)|(?:(?:xn--)[a-z0-9]+))(?:-[a-z0-9]+)*)|(?:\[(?:(?:IPv6:(?:(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){7})|(?:(?!(?:.*[a-f0-9][:\]]){7,})(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,5})?::(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,5})?)))|(?:(?:IPv6:(?:(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){5}:)|(?:(?!(?:.*[a-f0-9]:){5,})(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,3})?::(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,3}:)?)))?(?:(?:25[0-5])|(?:2[0-4][0-9])|(?:1[0-9]{2})|(?:[1-9]?[0-9]))(?:\.(?:(?:25[0-5])|(?:2[0-4][0-9])|(?:1[0-9]{2})|(?:[1-9]?[0-9]))){3}))\]))$/iD', $value);
	}

	/**
	 * Returns TRUE is provided string looks like valid E-Mail address
	 * and domain has MX record
	 *
	 * @param string $value
	 *
	 * @return bool
	 */
	public static function email_with_mx($value) {
		if (!self::email($value)) {
			return false;
		}

		$domain = substr($value, strrpos($value, '@') + 1);

		return checkdnsrr($domain, 'MX');
	}

	/**
	 * Returns TRUE if Certificate Signing Request is valid and key size is more than 1024 bits.
	 * If domain name is not omitted, check domain name for www and wildcard
	 *
	 * @param string $original_csr
	 * @param string $domain_name
	 *
	 * @return bool
	 * @codeCoverageIgnore Ignore in Unit Test, because added only for compatibility
	 */
	public static function csr($original_csr, $domain_name = '') {
		$original_csr = preg_replace('/^[\-]+.*?[\-]+(.*?)[\-]+.*?[\-]+$/si', '-----BEGIN NEW CERTIFICATE REQUEST-----\\1-----END NEW CERTIFICATE REQUEST-----', $original_csr);

		//fix for poor Rafe's SSL issue
		//strips out the first and last line from the csr and replaces it with our predefined ones
		$csr = openssl_csr_get_subject($original_csr);

		//make sure the common name exists
		if ($csr['CN'] != '') {
			//pull the public key deets so we can check the key size is >= 2048
			$public_key_details = openssl_pkey_get_details(openssl_csr_get_public_key($original_csr));

			if ($public_key_details['bits'] == '' || $public_key_details['bits'] <= 1024) {
				return false;
			}

			//if there is no domain name supplied, then we just want to know the CSR is valid
			if ($domain_name == '') {
				return true;
			}

			// WWW
			$common_name_has_www = (strpos($csr['CN'], 'www.') === 0);
			$domain_name_has_www = (strpos($domain_name, 'www.') === 0);

			//add the prefix for the common name if the domain name has it
			if (!$common_name_has_www && $domain_name_has_www) {
				$csr['CN'] = "www.{$csr['CN']}";
			}

			//add the prefix for the domain name if the common name has it
			if (!$domain_name_has_www && $common_name_has_www) {
				$domain_name = "www.{$domain_name}";
			}

			// WILDCARD
			$common_name_has_wildcard = strpos($csr['CN'], '*.') === 0;
			$domain_name_has_wildcard = strpos($domain_name, '*.') === 0;

			if ($common_name_has_wildcard && !$domain_name_has_wildcard) {
				$domain_name = "*.{$domain_name}";
			}

			//make sure they match
			if ($csr['CN'] == $domain_name) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Returns TRUE is Signed Mark Data file is valid.
	 *
	 * NOTE: The file that is generated once you fulfill the sunrise eligibility requirements and that allows you
	 * to register a domain name label within a TLD (extension) as long as you comply with the registries policy.
	 * The Clearinghouse provides you with the SMD file, but it the registry’s determination to accept the SMD file.
	 *
	 * @param string $smd
	 * @return bool
	 *
	 * @codeCoverageIgnore Ignore in Unit Test, because added only for compatibility
	 */
	public static function smd($smd) {
		if (!is_scalar($smd)) {
			return false;
		}

		if (!preg_match('/Marks:\s[a-z0-9\s_-]+/i', $smd)) {
			return false;
		}

		if (!preg_match('/smdID:\s[0-9-]+/i', $smd)) {
			return false;
		}

		if (!preg_match('/U-labels:\s[a-z0-9\s,_-]+/i', $smd)) {
			return false;
		}

		if (!preg_match('/notBefore:\s[\d]{4}-[\d]{2}-[\d]{2}\s[\d]{2}:[\d]{2}/i', $smd)) {
			return false;
		}

		if (!preg_match('/notAfter:\s[\d]{4}-[\d]{2}-[\d]{2}\s[\d]{2}:[\d]{2}/i', $smd)) {
			return false;
		}

		if (!preg_match('/-----BEGIN ENCODED SMD-----.*?-----END ENCODED SMD-----/si', $smd)) {
			return false;
		}

		return true;
	}

	/**
	 * Return TRUE is Australian Company Number (ACN) looks valid.
	 *
	 * @param string $value
	 * @return bool
	 */
	public static function acn($value) {
		if (!is_string($value)) {
			return false;
		}

		$value = str_replace(' ', '', $value);

		if (preg_match('/^[0-9]{9}$/', $value)) {
			$total = 0;

			for ($i = 0; $i < 8; $i++) {
				$total += ($value[$i] * (8 - $i));
			}

			$check_bit = 10 - ($total % 10);

			if ($check_bit == 10) {
				$check_bit = 0;
			}

			if ($check_bit == $value[8]) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Return TRUE if Australian Business Number (ABN) looks valid.
	 *
	 * @param string $value
	 * @return bool
	 */
	public static function abn($value) {
		if (!is_string($value)) {
			return false;
		}

		$value = str_replace(' ', '', $value);

		if (preg_match('/^[0-9]{11}$/', $value)) {
			$total = 0;
			$weights = [10, 1, 3, 5, 7, 9, 11, 13, 15, 17, 19];
			$value[0] = $value[0] - 1;

			for ($i = 0; $i < 11; $i++) {
				$total += $value[$i] * $weights[$i];
			}

			return ($total % 89) == 0;
		}

		return false;
	}


	/**
	 * Check for valid phone number in international format.
	 *
	 * Accept: +12345678912 , +1 234 567 8912 , +1(234) 567 8912, +1-234-567-8912
	 *
	 * @param $value
	 * @return bool
	 */
	public static function phone_number($value) {
		if (!is_string($value)) {
			return false;
		}

		$value = trim($value);

		if (strpos($value, '+') === 0) {
			$value = substr($value, 1);
		}

		$value = preg_replace('/[^0-9]+/', '', $value);

		if (strlen($value) < 4 || strlen($value) > 13) {
			return false;
		}

		return true;
	}

	/**
	 * NYC phone number must be in international format, ie. +1 xxx xxx xxxx
	 *
	 * @param $full_phone
	 * @return bool
	 * @codeCoverageIgnore Ignore in Unit Test, because added only for compatibility
	 */
	public static function check_nyc_phone_number($full_phone) {
		if (strpos($full_phone, '+1') === 0) {
			$full_phone = substr($full_phone, 2);
		}

		if ($full_phone[0] == '1') {
			$full_phone = substr($full_phone, 1);
		}

		$phone = preg_replace('/[^0-9]+/', '', $full_phone);

		if (strlen($phone) < 10) {
			return false;
		}

		return true;
	}

	/**
	 * Validates an australian phone number
	 *
	 * @param string $country_code
	 * @param string $number
	 *
	 * @return bool
	 * @codeCoverageIgnore	Ignore in Unit Test, because added only for compatibility
	 */
	public static function au_phone_number($country_code, $number) {
		if ($country_code == '' || $number == '') {
			return false;
		}

		return (bool) preg_match('/^(\\+61\\.[123478][0-9]{8})$/', "+$country_code.$number");
	}

	/**
	 * Validate an IRD number
	 *
	 * @param $ird_number
	 * @return bool
	 */
	public static function ird_number($ird_number) {
		return \nz_gst::valid_ird_number($ird_number);
	}
}
