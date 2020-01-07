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
		$api_key = $this->get_api_key();

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
		$data_api = json_decode($this->get_data_api());
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
	public function get_last_update_date() {
		$query = Database::getConnection();
		$result = $query->select('custom_exchange_rates_settings', 'cers')
			->fields('cers', ['value'])
			->condition('cers.setting', 'last_update')
			->execute()
			->fetchField();
		return $result;
	}

	/**
	 * Get module cron period.
	 *
	 * @return mixed
	 */
	public function get_last_update_module_date() {
		$query = Database::getConnection();
		$result = $query->select('custom_exchange_rates_settings', 'cers')
			->fields('cers', ['value'])
			->condition('cers.setting', 'last_update_module')
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
		$api_key = $this->get_api_key();
		if ($api_key) {
			// Update rates from API.
			$currency_list = $this->clear_currency_list($this->get_saved_currency_list());
			$data_api = json_decode($this->get_data_api($currency_list));
			$rates = $data_api->rates;

			// This time timestamp.
			$timestamp_now = time();

			// Update data in DB.
			$query = Database::getConnection();
			// Update base currency.
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
			// Update date of rates actuality.
			$query->merge('custom_exchange_rates_settings')
				->insertFields(array(
					'setting' => 'last_update',
					'value' => $data_api->timestamp,
				))
				->updateFields(array(
					'value' => $data_api->timestamp,
				))
				->key('setting', 'last_update')
				->execute();
			// Update module updating date.
			$query->merge('custom_exchange_rates_settings')
				->insertFields(array(
					'setting' => 'last_update_module',
					'value' => $timestamp_now,
				))
				->updateFields(array(
					'value' => $timestamp_now,
				))
				->key('setting', 'last_update_module')
				->execute();
			// Update rates.
			foreach ($rates as $code => $rate) {
				$query->merge('custom_exchange_rates')
					->insertFields(array(
						'code' => $code,
						'value' => $rate,
					))
					->updateFields(array(
						'value' => $rate,
					))
					->key('code', $code)
					->execute();
			}
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Get cron period from Drupal settings.
	 *
	 * @return array|mixed|null
	 */
	public function get_drupal_cron_period() {
		$cron_period = $this->config('automated_cron.settings')
			->get('interval');
		return $cron_period;
	}

	/**
	 * Get module cron period.
	 *
	 * @return mixed
	 */
	public function get_module_cron_period() {
		$query = Database::getConnection();
		$result = $query->select('custom_exchange_rates_settings', 'cers')
			->fields('cers', ['value'])
			->condition('cers.setting', 'module_cron_period')
			->execute()
			->fetchField();
		return $result;
	}
}
