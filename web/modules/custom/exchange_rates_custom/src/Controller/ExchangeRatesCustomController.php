<?php

namespace Drupal\exchange_rates_custom\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Database;

/**
 * Defines ExchangeRatesCustomController class.
 */
class ExchangeRatesCustomController extends ControllerBase {

	/**
	 * Get data from API.
	 *
	 * @param bool $symbols
	 *
	 * @return bool|string
	 */
	public function get_data_api($symbols = false) {
		$data = new ExchangeRatesCustomController;
		$api_key = $data->get_api_key();

		$symbols_url = '';
		if ($symbols) {
			$symbols_url = '&symbols=' . $symbols;
		}
		$url = "http://data.fixer.io/api/latest?access_key=" . $api_key . $symbols_url;

		$c = curl_init();
		curl_setopt($c, CURLOPT_URL, $url);
		curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
		$output = curl_exec($c);
		curl_close($c);
		
		return $output;
	}

	/**
	 * Get currency list.
	 * Get all available currencies from API.
	 *
	 * @return string
	 */
	public function get_currency_list() {
		$data = new ExchangeRatesCustomController;
		$data_api = json_decode($data->get_data_api());
		$rates = $data_api->rates;
		$string = '';
		foreach ($rates as $key => $rate) {
			if ($rate == end($rates)) {
				$string = $string . $key;
			} else {
				$string = $string . $key . ', ';
			}
		}
		return $string;
	}

	/**
	 * Get currency rates from DB.
	 *
	 * @return mixed
	 */
	public function get_currency_rates() {
		$query = Database::getConnection();
		$result = $query->select('custom_exchange_rates', 'cer')
			->fields('cer', ['code', 'value'])
			->execute()
			->fetchAllAssoc('code');
		return $result;
	}

	/**
	 * Clear Currency Codes field from bad symbols.
	 *
	 * @param $junk_string
	 *
	 * @return null|string|string[]
	 */
	public function clear_currency_list($junk_string) {
		$currency_codes = preg_replace("/[^,A-Z]/", '', strtoupper($junk_string));
		return $currency_codes;
	}

	/**
	 * Get a list of saved currencies.
	 * List of currencies saved in DB.
	 *
	 * @return string
	 */
	public function get_saved_currency_list() {
		// Connect to DB.
		$query = Database::getConnection();
		$currency = $query->select('custom_exchange_rates', 'cer')
			->fields('cer', ['code'])
			->execute()
			->fetchCol();
		$result = implode(", ", $currency);
		return $result;
	}

	/**
	 * Get saved an API Key from DB.
	 *
	 * @return mixed
	 */
	public function get_api_key() {
		// Connect to DB.
		$query = Database::getConnection();
		$result = $query->select('custom_exchange_rates_settings', 'cers')
			->fields('cers', ['value'])
			->condition('cers.setting', 'api_key')
			->execute()
			->fetchField();
		return $result;
	}

	/**
	 * Get base currency from DB.
	 *
	 * @return mixed
	 */
	public function get_base_currency() {
		// Connect to DB.
		$query = Database::getConnection();
		$result = $query->select('custom_exchange_rates_settings', 'cers')
			->fields('cers', ['value'])
			->condition('cers.setting', 'base_currency')
			->execute()
			->fetchField();
		return $result;
	}

	/**
	 * Get last updated date from DB.
	 *
	 * @return mixed
	 */
	public function get_last_updated_date() {
		// Connect to DB.
		$query = Database::getConnection();
		$result = $query->select('custom_exchange_rates_settings', 'cers')
			->fields('cers', ['value'])
			->condition('cers.setting', 'last_updated')
			->execute()
			->fetchField();
		return $result;
	}

	/**
	 * Currency converter.
	 *
	 * @param $value
	 * @param $code_1
	 * @param $code_2
	 *
	 * @return float|int
	 */
	public function convert($value, $code_1, $code_2) {
		$query = Database::getConnection();
		$code_1_value = $query->select('custom_exchange_rates', 'cer')
			->fields('cer', ['value'])
			->condition('cer.code', $code_1)
			->execute()
			->fetchField();
		$code_2_value = $query->select('custom_exchange_rates', 'cer')
			->fields('cer', ['value'])
			->condition('cer.code', $code_2)
			->execute()
			->fetchField();
		$result = $value / $code_1_value * $code_2_value;
		return $result;
	}

	/**
	 * Update data from API.
	 *
	 * @return bool
	 * @throws
	 */
	public function update() {
		$data = new ExchangeRatesCustomController;
		$api_key = $data->get_api_key();
		if ($api_key) {
			$currency_list = $data->clear_currency_list($data->get_saved_currency_list());
			$data_api = json_decode($data->get_data_api($currency_list));
			$values_api = [];
			$rates = $data_api->rates;
			foreach ($rates as $key => $rate) {
				$values_api[] = [
					'code' => $key,
					'value' => $rate,
				];
				$values_api_codes[] = $key;
			}

			// Work with DB.
			$query = Database::getConnection();
			// Insert data in DB.
			$query->merge('custom_exchange_rates_settings')
				->insertFields(array(
					'setting' => 'base_currency',
					'value' => $data_api->base,
				))
				->updateFields(array(
					'value' => $data_api->base,
				))
				->key('setting', 'base_currency')
				->execute();
			$query->merge('custom_exchange_rates_settings')
				->insertFields(array(
					'setting' => 'last_updated',
					'value' => $data_api->timestamp,
				))
				->updateFields(array(
					'value' => $data_api->timestamp,
				))
				->key('setting', 'last_updated')
				->execute();
			foreach ($values_api as $record) {
				$query->merge('custom_exchange_rates')
					->insertFields(array(
						'code' => $record['code'],
						'value' => $record['value'],
					))
					->updateFields(array(
						'value' => $record['value'],
					))
					->key('code', $record['code'])
					->execute();
			}
			return true;
		} else {
			return false;
		}
	}
}