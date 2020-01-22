<?php

namespace Drupal\exchange_rates_custom;

use GuzzleHttp\Client;
use Drupal\Core\Database\Database;

/**
 * Class ExchangeRatesCustom
 * @package Drupal\exchange_rates_custom
 */
class ExchangeRatesCustom {

	/**
	 * Get saved an API Key from DB.
	 *
	 * @return mixed
	 */
	public function get_api_key() {
		$result = \Drupal::config('exchange_rates_custom.settings')
			->get('api_key');
		return $result;
	}

	/**
	 * Get data from API.
	 *
	 * @param bool $symbols
	 *
	 * @return bool|string
	 */
	public function get_data_from_api($symbols = false) {
		$api_key = $this->get_api_key();

		$symbols_url = '';
		if ($symbols) {
			$symbols_url = '&symbols=' . $symbols;
		}
		$url = "http://data.fixer.io/api/latest?access_key=" . $api_key . $symbols_url;

		$client = new Client([
			'base_uri' => $url,
		]);
		$output = $client->get($url)->getBody();

		return $output;
	}

	/**
	 * Get all available currencies from API and convert it to string.
	 *
	 * @return string
	 */
	public function currencies_to_string() {
		$data_api = json_decode($this->get_data_from_api());
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
	 * Get base currency from DB.
	 *
	 * @return mixed
	 */
	public function get_base_currency() {
		$result = \Drupal::config('exchange_rates_custom.settings')
			->get('base_currency');
		return $result;
	}

	/**
	 * Get last updated date from DB.
	 *
	 * @return mixed
	 */
	public function get_last_update_date() {
		$result = \Drupal::config('exchange_rates_custom.settings')
			->get('last_update');
		return $result;
	}

	/**
	 * Get module cron period.
	 *
	 * @return mixed
	 */
	public function get_last_update_module_date() {
		$result = \Drupal::config('exchange_rates_custom.settings')
			->get('last_update_module');
		return $result;
	}

	/**
	 * Get currency's value by code.
	 *
	 * @param $code
	 *
	 * @return mixed
	 */
	public function get_value_by_code($code) {
		$query = Database::getConnection();
		$result = $query->select('custom_exchange_rates', 'cer')
			->fields('cer', ['value'])
			->condition('cer.code', $code)
			->execute()
			->fetchField();
		return $result;
	}

	/**
	 * Get cron period from Drupal settings.
	 *
	 * @return array|mixed|null
	 */
	public function get_drupal_cron_period() {
		$result = \Drupal::config('automated_cron.settings')
			->get('interval');
		return $result;
	}

	/**
	 * Get module cron period.
	 *
	 * @return mixed
	 */
	public function get_module_cron_period() {
		$result = \Drupal::config('exchange_rates_custom.settings')
			->get('module_cron_period');
		return $result;
	}

}