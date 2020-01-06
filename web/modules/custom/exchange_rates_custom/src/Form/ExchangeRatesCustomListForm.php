<?php

namespace Drupal\exchange_rates_custom\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\exchange_rates_custom\Controller\ExchangeRatesCustomController;

class ExchangeRatesCustomListForm extends ConfigFormBase {
	/**
	 * {@inheritdoc}
	 */
	protected function getEditableConfigNames() {
		return [
			'exchange_rates_custom.list',
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function getFormId() {
		return 'exchange_rates_custom_list_form';
	}

	/**
	 * Builds Exchange Rates list form.
	 *
	 * @param array $form
	 * @param \Drupal\Core\Form\FormStateInterface $form_state
	 *
	 * @return array
	 */
	public function buildForm(array $form, FormStateInterface $form_state) {
		$data = new ExchangeRatesCustomController;
		$currency_rates = $data->get_currency_rates();
		$table_header = [
			'code' => t('Currency code'),
			'value' => t('Currency value'),
		];
		// Convert object to array.
		$currency_rates_array = json_decode(json_encode($currency_rates), true);

		$base_currency = $data->get_base_currency();
		$last_updated = $data->get_last_updated_date();

		$form['last_updated'] = [
			'#type' => 'item',
			'#markup' => $this->t('Exchange rate date: ') . '<b>' . date('d.m.Y', $last_updated) . '</b>',
		];

		$form['base_currency'] = [
			'#type' => 'item',
			'#markup' => $this->t('Base currency: ') . '<b>' . $base_currency . '</b>',
		];

		$form['table'] = [
			'#type' => 'table',
			'#header' => $table_header,
			'#rows' => $currency_rates_array,
			'#empty' => t('No currencies found'),
		];

		$form['update_list_description'] = [
			'#type' => 'item',
			'#markup' => $this->t('You can update currency values from API manually:'),
		];

		$form['actions'] = [
			'#type' => 'actions',
		];

		$form['actions']['submit'] = [
			'#type' => 'submit',
			'#value' => $this->t('Update values'),
			'#button_type' => 'primary',
		];

		return $form;
	}

	/**
	 * @param array $form
	 * @param \Drupal\Core\Form\FormStateInterface $form_state
	 */
	public function submitForm(array &$form, FormStateInterface $form_state) {
		$data = new ExchangeRatesCustomController;
		$update_result = $data->update();
		if ($update_result) {
			$this->messenger()->addMessage(t('All currency values have been updated successfully!'));
		} else {
			$this->messenger()->addError(t('Something wrong. We can\'t update currency values.'));
		}

		// Redirect to module List Page
		$url = Url::fromRoute('exchange_rates_custom.list');
		$form_state->setRedirectUrl($url);
	}

	/**
	 * @param array $form
	 * @param \Drupal\Core\Form\FormStateInterface $form_state
	 */
	public function validateForm(array &$form, FormStateInterface $form_state) {
		$data = new ExchangeRatesCustomController;
		if (!$data->get_api_key()) {
			$form_state->setErrorByName('actions', $this->t('API Key should be set.'));
		}
	}
}