<?php

namespace Drupal\exchange_rates_custom\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\exchange_rates_custom\ExchangeRatesCustom;
use Drupal\exchange_rates_custom\ExchangeRatesCustomUpdate;

/**
 * Defines ExchangeRatesCustomController class.
 */
class ExchangeRatesCustomController extends ControllerBase {

	/**
	 * Get currency list.
	 * Get all available currencies from API.
	 *
	 * @return string
	 */
	public function get_currency_list() {
		$exc = new ExchangeRatesCustom;
		$result = $exc->currencies_to_string();
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
		$exc = new ExchangeRatesCustom;
		$code_1_value = $exc->get_value_by_code($code_1);
		$code_2_value = $exc->get_value_by_code($code_2);
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
		$exc = new ExchangeRatesCustom;
		$excu = new ExchangeRatesCustomUpdate;
		$api_key = $exc->get_api_key();
		if ($api_key) {
			// Update rates from API.
			$result = $excu->update_data_in_db();
			return $result;
		} else {
			return false;
		}
	}

}
