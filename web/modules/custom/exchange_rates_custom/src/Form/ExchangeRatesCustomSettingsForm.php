<?php

namespace Drupal\exchange_rates_custom\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\exchange_rates_custom\Controller\ExchangeRatesCustomController;
use Drupal\Core\Database\Database;

class ExchangeRatesCustomSettingsForm extends ConfigFormBase {
	/**
	 * {@inheritdoc}
	 */
	protected function getEditableConfigNames() {
		return [
			'exchange_rates_custom.settings',
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function getFormId() {
		return 'exchange_rates_custom_settings_form';
	}

	/**
	 * Builds Exchange Rates Custom settings form.
	 *
	 * @param array $form
	 * @param \Drupal\Core\Form\FormStateInterface $form_state
	 *
	 * @return array
	 */
	public function buildForm(array $form, FormStateInterface $form_state) {
		$data = new ExchangeRatesCustomController;
		$currency_codes = $data->get_saved_currency_list();
		$api_key = $data->get_api_key();
		$drupal_cron_period = $data->get_drupal_cron_period();
		$module_cron_period = $data->get_module_cron_period();

		$form['api_key'] = [
			'#type' => 'textfield',
			'#title' => $this->t('API Key'),
			'#description' => $this->t('You can get it on a ') . '<a href="https://fixer.io" target="_blank">fixer.io</a>',
			'#required' => TRUE,
			'#default_value' => $api_key,
		];

		$form['get_list'] = [
			'#type' => 'container',
		];

		$form['get_list']['description'] = [
			'#type' => 'item',
			'#markup' => $this->t('Get a list of available currency codes:'),
		];

		$form['get_list']['button'] = array(
			'#type' => 'button',
			'#value' => $this->t('Get list'),
			'#executes_submit_callback' => FALSE,
			'#limit_validation_errors' => [],
			'#prefix' => '<div class="buttons">',
			'#suffix' => '</div>',
			'#ajax' => array(
				'callback' => '::getListAjaxCallback',
				'event' => 'click',
				'wrapper' => 'currency_list',
				'progress' => array(
					'type' => 'throbber',
					'message' => NULL,
				),
			)
		);

		$form['get_list']['result'] = [
			'#type' => 'item',
			'#markup' => '',
			'#prefix' => '<div id="currency_list">',
			'#suffix' => '</div>',
		];

		$form['currency_codes'] = [
			'#type' => 'textarea',
			'#title' => $this->t('Currency codes'),
			'#description' => $this->t('Enter a currency codes here. Only this currencies will be saved in database and be available for converting! You must separate it with a comma ",". Example: "USD, BYN, RUB".'),
			'#required' => TRUE,
			'#default_value' => $currency_codes,
		];

		$form['cron'] = [
			'#type' => 'container',
		];

		$form['cron']['drupal_period'] = [
			'#type' => 'item',
			'#markup' => $this->t('Drupal cron period: ') . '<b>' . $drupal_cron_period . ' ' . t('seconds') . '</b>',
		];

		$form['cron']['module_period'] = [
			'#type' => 'textfield',
			'#title' => $this->t('Module cron period'),
			'#description' => $this->t('Enter a period in seconds for automatically currency updating. Example: set "86400" for updating values once per day. Set "0" to leave it the same as Drupal cron period.'),
			'#required' => TRUE,
			'#default_value' => $module_cron_period ? $module_cron_period : 0,
		];

		$form['actions'] = [
			'#type' => 'actions',
		];

		$form['actions']['submit'] = [
			'#type' => 'submit',
			'#value' => $this->t('Save settings'),
			'#button_type' => 'primary',
		];

		return $form;
	}

	/**
	 * Get the list of available currency codes by Ajax
	 *
	 * @param array $form
	 * @param \Drupal\Core\Form\FormStateInterface $form_state
	 *
	 * @return mixed
	 */
	public function getListAjaxCallback(array &$form, FormStateInterface $form_state) {
		$data = new ExchangeRatesCustomController;
		$api_key = $data->get_api_key();
		if (!$api_key) {
			$api_key = $form_state->getValue('api_key');
		}
		if (strlen($api_key) == 32) {
			$value = $data->get_currency_list();

			$form['get_list_result']['#markup'] = '<pre>'. $value . '</pre>';
		} else {
			$form['get_list_result']['#markup'] = '<pre>'. t('API Key is wrong. The key length must be 32 characters.') . '</pre>';
		}
		return $form['get_list_result'];
	}

	/**
	 * @param array $form
	 * @param \Drupal\Core\Form\FormStateInterface $form_state
	 *
	 * @throws \Exception
	 */
	public function submitForm(array &$form, FormStateInterface $form_state) {
		$api_key = $form_state->getValue('api_key');

		// Connect to DB.
		$query = Database::getConnection();
		// Insert api_key to DB.
		$query->merge('custom_exchange_rates_settings')
			->insertFields(array(
				'setting' => 'api_key',
				'value' => $api_key,
			))
			->updateFields(array(
				'value' => $api_key,
			))
			->key('setting', 'api_key')
			->execute();

		// Clear the Currency codes field from bad symbols.
		$data = new ExchangeRatesCustomController;
		$clear_string = $data->clear_currency_list($form_state->getValue('currency_codes'));

		// Get currency and rates from API to array.
		$data_api = json_decode($data->get_data_api($clear_string));
		$base_currency = $data_api->base;
		$timestamp = $data_api->timestamp;
		$rates = $data_api->rates;
		$values_api_codes = [];
		foreach ($rates as $code => $rate) {
			$values_api_codes[] = $code;
		}

		// This time timestamp.
		$timestamp_now = time();

		// Clean module cron period.
		$module_cron_period = preg_replace("/[^0-9]/", '', $form_state->getValue('module_period'));

		// Get currency list from DB in array.
		$data_db = $data->clear_currency_list($data->get_saved_currency_list());
		$values_db = explode(',', $data_db);

		// Delete codes in DB if they absent in a API codes array.
		foreach ($values_db as $value_db) {
			if (!in_array($value_db, $values_api_codes)) {
				$query->delete('custom_exchange_rates')
					->condition('code', $value_db)
					->execute();
			}
		}
		// Insert/Update data to DB.
		// Insert/Update base currency.
		$query->merge('custom_exchange_rates_settings')
			->insertFields(array(
				'setting' => 'base_currency',
				'value' => $base_currency,
			))
			->updateFields(array(
				'value' => $base_currency,
			))
			->key('setting', 'base_currency')
			->execute();
		// Insert/Update date of rates actuality.
		$query->merge('custom_exchange_rates_settings')
			->insertFields(array(
				'setting' => 'last_update',
				'value' => $timestamp,
			))
			->updateFields(array(
				'value' => $timestamp,
			))
			->key('setting', 'last_update')
			->execute();
		// Insert/Update module updating date.
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
		// Insert/Update module updating period for cron.
		$query->merge('custom_exchange_rates_settings')
			->insertFields(array(
				'setting' => 'module_cron_period',
				'value' => $module_cron_period,
			))
			->updateFields(array(
				'value' => $module_cron_period,
			))
			->key('setting', 'module_cron_period')
			->execute();
		// Insert/Update rates.
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

		$this->messenger()->addMessage(t('Settings saved successfully!'));

		// Redirect to module Settings Page
		$url = Url::fromRoute('exchange_rates_custom.settings');
		$form_state->setRedirectUrl($url);
	}

	/**
	 * @param array $form
	 * @param \Drupal\Core\Form\FormStateInterface $form_state
	 */
	public function validateForm(array &$form, FormStateInterface $form_state) {
		$data = new ExchangeRatesCustomController;
		$drupal_cron_period = $data->get_drupal_cron_period();

		$api_key = $form_state->getValue('api_key');
		if (strlen($api_key) != 32) {
			$form_state->setErrorByName('api_key', $this->t('API Key is wrong. The key length must be 32 characters.'));
		}

		$currency_codes = $data->clear_currency_list($form_state->getValue('currency_codes'));
		if ($currency_codes == '') {
			$form_state->setErrorByName('currency_codes', $this->t('Currency codes field can\'t be empty.'));
		}

		$module_cron_period = preg_replace("/[^0-9]/", '', $form_state->getValue('module_period'));
		if ($module_cron_period < $drupal_cron_period && $module_cron_period != 0) {
			$form_state->setErrorByName('cron', $this->t('Module cron period should be more then Drupal cron period or 0.'));
		}
	}
}
