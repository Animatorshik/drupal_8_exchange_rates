<?php

namespace Drupal\exchange_rates_custom;

use Drupal\Core\Database\Database;

class ExchangeRatesCustomUpdate {

	/**
	 * Get rates from API for currencies in DB.
	 *
	 * @return mixed
	 */
	public function get_rates_from_api_db_list() {
		$exc = new ExchangeRatesCustom;
		$currency_list = $exc->clear_currency_list($exc->get_saved_currency_list());
		$data_api = json_decode($exc->get_data_from_api($currency_list));
		return $data_api;
	}

	/**
	 * Update currencies rates in DB.
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function update_data_in_db() {
		$data_api = $this->get_rates_from_api_db_list();

		// This time timestamp.
		$timestamp_now = time();

		// Save base currency.
		// Save date of rates actuality.
		// Save module updating date.
		\Drupal::configFactory()->getEditable('exchange_rates_custom.settings')
			->set('base_currency', $data_api->base)
			->set('last_update', $data_api->timestamp)
			->set('last_update_module', $timestamp_now)
			->save();

		// Update rates.
		$query = Database::getConnection();
		foreach ($data_api->rates as $code => $rate) {
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
	}
}