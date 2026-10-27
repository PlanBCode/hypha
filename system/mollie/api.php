<?php
	/*
	 * Minimal Mollie payment API client.
	 *
	 * Only supports creating and retrieving payments.
	 */

	class MolliePayment {
		const STATUS_OPEN = "open";
		const STATUS_CANCELLED = "cancelled";
		const STATUS_PENDING = "pending";
		const STATUS_AUTHORIZED = "authorized";
		const STATUS_EXPIRED = "expired";
		const STATUS_FAILED = "failed";
		const STATUS_PAID = "paid";

		// TODO: When bumping to PHP8.1, make these readonly
		public $id;
		public $currency;
		public $amount;
		public $status;
		public $isOpen;
		public $isPaid;
		public $checkout_url;
		public $paidAt;

		public function __construct($data) {
			assert($data->resource == 'payment');
			$this->id = $data->id;
			$this->currency = $data->amount->currency;
			$this->amount = $data->amount->value;
			$this->status = $data->status;
			$this->checkout_url = property_exists($data->_links, 'checkout') ? $data->_links->checkout->href : null;
			$this->paidAt = property_exists($data, 'paidAt') ? $data->paidAt : null;
			$this->isPaid = $this->status == self::STATUS_PAID;
			$this->isOpen = $this->status == self::STATUS_OPEN;
		}
	}

	class MollieAPI {
		private $api_url;
		private $api_key;

		public function __construct($api_key) {
			$this->api_url = "https://api.mollie.com/v2";
			$this->api_key = $api_key;
		}



		public function createPayment($currency, $amount, $description, $redirect_url, $webhook_url) {
			$result = $this->request('POST', "/payments", [
				"amount"       => [
					"currency" => $currency,
					"value" => number_format((float)$amount, 2),
				],
				"description"  => $description,
				"redirectUrl"  => $redirect_url,
				"webhookUrl"   => $webhook_url,
			], 201);
			if ($result !== false)
				return new MolliePayment($result);
			else
				return false;
		}

		public function getPayment($id) {
			$result = $this->request('GET', "/payments/$id");
			if ($result !== false)
				return new MolliePayment($result);
			else
				return false;
		}

		private function request($method, $url, $payload = null, $expected_code = 200) {
			$full_url = $this->api_url . $url;
			$ch = curl_init($full_url);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array(
				"Authorization: Bearer $this->api_key",
				'Content-Type: application/json',
			));
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
			if ($payload !== null)
				curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
			$result = curl_exec($ch);
			$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			$type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

			//error_log("$method $full_url\n");
			//error_log("$method $full_url\n" . json_encode($payload, JSON_PRETTY_PRINT) . "\n");

			// TODO: Return errors instead of just printing and returning false?
			if ($result === false) {
				error_log("Mollie API request \"$method $full_url\" failed: " . curl_error($ch));
				return false;
			} else if ($code != $expected_code) {
				if (in_array($type, ['application/json', 'application/hal+json'])) {
					$result = json_encode(json_decode($result), JSON_PRETTY_PRINT);
				}
				error_log("Mollie API request \"$method $full_url\" returned unexpected status ($code), response: $result");
				return false;
			} else if ($result === true) {
				// This should not be possible, but this lets
				// the type checker know as well.
				assert(false);
				return false;
			} else if (!in_array($type, ['application/json', 'application/hal+json'])) {
				error_log("Mollie API request \"$method $full_url\" returned unexpected content-type ($type), response: $result");
				return false;
			} else {
				$array = json_decode($result);
				//print("$method $full_url $code\n" . json_encode($array, JSON_PRETTY_PRINT) . "\n");
				return $array;
			}
		}
	}
?>
