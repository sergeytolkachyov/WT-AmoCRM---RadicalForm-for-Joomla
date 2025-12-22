<?php
/**
 * @package    WT Amocrm - Radical From
 * @version    1.4.0
 * @author Sergey Tolkachyov <https://web-tolk.ru>
 * @copyright (c) 2022 - December 2025 Sergey Tolkachyov. All rights reserved.
 * @license    GNU/GPL 3 license
 * @link https://web-tolk.ru
 */

namespace Joomla\Plugin\System\Wt_amocrm_radicalform\Extension;

use Exception;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;
use Webtolk\Amocrm\Amocrm;
use function defined;

defined('_JEXEC') or die;

class Wt_amocrm_radicalform extends CMSPlugin implements SubscriberInterface
{

	protected $autoloadLanguage = true;

	/**
	 *
	 * @return array
	 *
	 * @throws Exception
	 * @since 4.1.0
	 *
	 */
	public static function getSubscribedEvents(): array
	{
		return [
			'onAfterDispatch'         => 'onAfterDispatch',
			'onBeforeSendRadicalForm' => 'onBeforeSendRadicalForm',
		];
	}

	/**
	 * Добавляем js-скрпиты на HTML-фронт
	 *
	 * @throws Exception
	 * @since 1.0.0
	 */
	public function onAfterDispatch($event): void
	{
		// We are not work in Joomla API or CLI or Admin area
		if (!$this->getApplication()->isClient('site')) return;

		$doc = $this->getApplication()->getDocument();
		$wa  = $doc->getWebAssetManager();
		// Show plugin version in browser console from js-script for UTM
		$wt_amocrm_radicalform_plugin_info = simplexml_load_file(JPATH_SITE . "/plugins/system/wt_amocrm_radicalform/wt_amocrm_radicalform.xml");
		$doc->addScriptOptions('plg_system_wt_amocrm_radicalform_version', (string) $wt_amocrm_radicalform_plugin_info->version);
		$wa->registerAndUseScript('plg_system_wt_amocrm_radicalform.wt_amocrm_radicalform_utm', 'plg_system_wt_amocrm_radicalform/wt_amocrm_radicalform_utm.js', ['version' => 'auto', 'relative' => true]);

	}

	/**
	 *  Integration with Radical Form plugin
	 *  Contact form plugin
	 *
	 * @param   Event  $event
	 *
	 * @return void
	 * @see    https://hika.su/rasshireniya/radical-form
	 * @see    https://web-tolk.ru/dev/joomla-plugins/wt-amocrm-radicalform
	 * @since  1.0.0
	 */
	public function onBeforeSendRadicalForm(Event $event): void
	{
		/**
		 * @param   array   $clear   это массив данных, полученный от формы и очищенный ото всех вспомогательных данных.
		 * @param   array   $input   это полный массив данных, включая все вспомогательные данные о пользователе и передаваемой форме. Этот массив передается по ссылке и у вас есть возможность изменить переданные данные. В примере выше именно это и происходит, когда вместо вбитого в форму имени устанавливается фиксированная константа.
		 * @param   object  $params  это объект, содержащий все параметры плагина и вспомогательные данные, которые известны при отправке формы. Например здесь можно получить адрес папки, куда были загружены фотографии (их можно переместить в нужное вам место):
		 */
		[$clear, $input, $params] = array_values($event->getArguments());

		if (isset($input['stop_amo']))
		{
			return;
		}

		$lead_data = [
			'created_by' => 0, //ID пользователя, создающий сделку. При передаче значения 0, сделка будет считаться созданной роботом. Поле не является обязательным
		];

		$radicalform_fields_to_amo_comment = '';

		// Название сделки в Amo CRM
		if (!empty($input['rfSubject']))
		{
			$lead_data['name'] = $input['rfSubject'];
		}
		else
		{
			$lead_data['name'] = $params->get('rfSubject');
		}

		if (!empty($input['pipeline_id']))
		{
			$lead_data['pipeline_id'] = (int) $input['pipeline_id'];
		}
		elseif (!empty($this->params->get('pipeline_id')))
		{
			$lead_data['pipeline_id'] = (int) $this->params->get('pipeline_id');
		}

		if (!empty($this->params->get('status_id')))
		{
			$lead_data['status_id'] = (int) $this->params->get('status_id');
		}

		$lead_data['_embedded']['metadata'] = [
			'category'     => 'forms',
			'form_id'      => (!empty($input['form_id'])) ? $input['form_id'] : 1,
			'form_name'    => (!empty($input['rfSubject'])) ? $input['rfSubject'] : 'Call back from site',
			'form_page'    => $input['url'],
			'form_sent_at' => (new Date('now'))->toUnix(),
		];


		// URL страницы, с которой отправлена форма
		if (!empty($input['url']))
		{
			$lead_data['_embedded']['metadata']['referer'] = $input['url'];
		}
		$contact = [];

		// URL страницы, с которой отправлена форма
		if (!empty($input['name']))
		{
			$contact['first_name'] = $input['name'];
		}

		foreach ($clear as $key => $value)
		{

			if (strtolower($key) == "phone")
			{
				$phones = [
					'field_code' => 'PHONE',
					'values'     => []
				];
				/*
				 * If any phone numbers or emails are found
				 */
				if (is_array($value))
				{

					foreach ($value as $phone)
					{
						$phones['values'][] = [
							'enum_code' => 'WORK',
							'value'     => $phone
						];
					}
				}
				else
				{
					/**
					 * Single email or phone number
					 */
					$phones['values'][] = [
						'enum_code' => 'WORK',
						'value'     => $value
					];
				}
				$contact['custom_fields_values'][] = $phones;
				/**
				 * Other form data. Not email or phone
				 */
			}
			elseif (strtolower($key) == "email")
			{
				$emails = [
					'field_code' => 'EMAIL',
					'values'     => []
				];
				/**
				 * If any phone numbers or emails are found
				 */
				if (is_array($value))
				{
					foreach ($value as $email)
					{
						$emails['values'][] = [
							'enum_code' => 'WORK',
							'value'     => $email
						];

					}
				}
				else
				{
					/**
					 * Single email or phone number
					 */
					$emails['values'][] = [
						'enum_code' => 'WORK',
						'value'     => $value
					];
				}
				$contact['custom_fields_values'][] = $emails;
				/**
				 * Other form data. Not email or phone
				 */
			}
		}

		if (!empty($this->params->get('radicalform_to_amocrm_lead_custom_fields')))
		{
			foreach ($this->params->get('radicalform_to_amocrm_lead_custom_fields') as $key => $value)
			{
				$value = new Registry($value);
				$radical_form_field_name = trim($value->get('radical_form_field_name', ''));
				if (empty($radical_form_field_name)) {
					continue;
				}

				// `field`, `contact_field` or `comment`
				$amocrm_field_or_comment = $value->get('amocrm_field_or_comment', 'comment');

				if (array_key_exists($radical_form_field_name, $input) && !empty($input[$radical_form_field_name])) {

					if ($amocrm_field_or_comment == 'comment') {
						$lang                              = $this->getApplication()->getLanguage();
						$radicalform_fields_to_amo_comment .= ($lang->hasKey(
								'PLG_WT_AMOCRM_RADICALFORM_AMOCRM_RF_FIELD_NAME_' . strtoupper($radical_form_field_name)
							) ? Text::_(
								'PLG_WT_AMOCRM_RADICALFORM_AMOCRM_RF_FIELD_NAME_' . strtoupper($radical_form_field_name)
							) : $radical_form_field_name) . ': ' . ((is_array(
								$input[$radical_form_field_name]
							)) ? implode(
								', ',
								$input[$radical_form_field_name]
							) : $input[$radical_form_field_name]) . PHP_EOL;
						continue;
					}

					$custom_field_id_param_name = ($amocrm_field_or_comment == 'field') ? 'lead_custom_field_id' : 'contact_custom_field_id';

					$custom_field_id = (int)$value->get($custom_field_id_param_name);

					if ($custom_field_id < 1) {
						continue;
					}

					$custom_field_data_type = $value->get('custom_field_data_type', 'string');
					$values = [];
					switch ($custom_field_data_type) {
						case 'int':
							$values[] =
								[
									'value' => (int)$input[$radical_form_field_name]
								];
							break;
						case 'float':
							$values[] =
								[
									'value' => (float)$input[$radical_form_field_name]
								];
							break;
						case 'bool':
							$values[] =
								[
									'value' => (bool)$input[$radical_form_field_name]
								];
							break;
						case 'string':
						default:
							$value = ((is_array($input[$radical_form_field_name])) ? implode(
								', ',
								$input[$radical_form_field_name]
							) : (string)$input[$radical_form_field_name]);
							$values[] =
								[
									'value' => $value
								];
							break;
					}

					$custom_field_array = [
						'field_id' => $custom_field_id,
						'values'   => $values
					];

					if ($amocrm_field_or_comment == 'field') {
						$lead_data["custom_fields_values"][] = $custom_field_array;
					} else {
						$contact['custom_fields_values'][] = $custom_field_array;
					}
				}
			}
		}

		$lead_data['_embedded']['contacts'][] = $contact;

		if (isset($input['lead_tag_id']))
		{
			// more than one comma separated tag
			if (strpos($input['lead_tag_id'], ',') !== false)
			{

				$tags      = explode(',', $input['lead_tag_id']);
				$lead_tags = [];
				foreach ($tags as $tag_id)
				{
					$lead_tags[] = [
						'id' => (int) trim($tag_id)
					];
				}

				$lead_data['_embedded']['tags'] = $lead_tags;

			}
			else
			{
				$lead_data['_embedded']['tags'][0]['id'] = (int) trim($input['lead_tag_id']);
			}
		}
		elseif ($this->params->get('lead_tag_id', 0) > 0)
		{
			$lead_data['_embedded']['tags'][0]['id'] = (int) $this->params->get('lead_tag_id');
		}

		/**
		 * Add UTMs into array
		 */
		$lead_data = $this->checkUtms($lead_data);
		$leads[]   = $lead_data;

		/**
		 * Create a lead
		 */
		$amocrm  = new Amocrm();
		$result  = $amocrm->createLeadsComplex($leads);
		$result  = (array) $result;
		$lead_id = $result[0]->id;
//		$contact_id = $result[0]->contact_id;


		$notes = [];
		if (!empty($radicalform_fields_to_amo_comment))
		{
			$notes[] = [
				'created_by' => 0, // 0 - создал робот
				'note_type'  => 'common',
				'params'     => [
					'text' => $radicalform_fields_to_amo_comment,
				]
			];
		}
		$notes[] = [
			'created_by' => 0, // 0 - создал робот
			'note_type'  => 'common',
			'params'     => [
				'text' => Text::_('PLG_WT_AMOCRM_RADICALFORM_FORM_SENT_FROM_PAGE') . $input['url'],
			]
		];

		$notes_result = $amocrm->addNotes('leads', $lead_id, $notes);

	}

	/**
	 * Function checks the utm marks and set its to array fields
	 *
	 * @param   array  $lead_data  Bitrix24 array data
	 *
	 * @return            array    Bitrix24 array data with UTMs
	 * @since    1.0.0
	 */
	private function checkUtms(&$lead_data): array
	{
		$utms = [
			'utm_source',
			'utm_medium',
			'utm_campaign',
			'utm_content',
			'utm_term',
			'fbclid',
			'yclid',
			'gclid',
			'gclientid',
			'from',
			'openstat_source',
			'openstat_ad',
			'openstat_campaign',
			'openstat_service',
			'referrer',
			'roistat',
			'_ym_counter',
			'_ym_uid',
			'utm_referrer'
		];
		foreach ($utms as $key)
		{
			$utm      = $this->getApplication()->getInput()->cookie->get($key, '', 'raw');
			$utm      = urldecode($utm);
			$utm_name = strtoupper($key);
			if (!empty($utm))
			{
				$utm_array = [
					'field_code' => strtoupper($utm_name),
					'values'     => [
						[
							'value' => $utm
						]
					]
				];
				$lead_data["custom_fields_values"][] = $utm_array;
			}
		}
		return $lead_data;
	}
}