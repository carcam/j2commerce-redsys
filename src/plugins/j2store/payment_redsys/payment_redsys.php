<?php

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

defined('_JEXEC') or die('Restricted access');

require_once(JPATH_ADMINISTRATOR . '/components/com_j2store/library/plugins/payment.php');

if (version_compare(PHP_VERSION, 7.0, '<')) {
	include JPATH_SITE . '/plugins/j2store/payment_redsys/library/apiRedsys.php';
} else {
	include JPATH_SITE . '/plugins/j2store/payment_redsys/library/apiRedsys_7.php';
}
class PlgJ2StorePayment_redsys extends J2StorePaymentPlugin
{

	/**
	 * @var $_element  string  Should always correspond with the plugin's filename,
	 *                         forcing it to be unique
	 */
	var $_element    = 'payment_redsys';
	var $_isLog      = false;

	private $merchant_code = '';
	private $merchant_terminal = '';
	private $merchant_signature = '';
	private $payment_page_title  = '';
	private $environment  = '';
	private $encryption_method = '';



	/**
	 * Constructor
	 *
	 * For php4 compatability we must not use the __constructor as a constructor for plugins
	 * because func_get_args ( void ) returns a copy of all passed arguments NOT references.
	 * This causes problems with cross-referencing necessary for the observer design pattern.
	 *
	 * @param object $subject The object to observe
	 * @param 	array  $config  An array that holds the plugin configuration
	 * @since 1.5
	 */
	function __construct(&$subject, $config)
	{
		parent::__construct($subject, $config);
		$this->loadLanguage('', JPATH_ADMINISTRATOR);
		$this->merchant_code = trim($this->params->get('redsys_merchant_code', ''));
		$this->merchant_terminal = trim($this->params->get('redsys_merchant_terminal', ''));
		$this->merchant_signature = trim($this->params->get('redsys_merchant_signature', ''));
		$this->payment_page_title  = trim($this->params->get('redsys_title', ''));
		$this->environment  = $this->params->get('sandbox', 0) ? 'test' : 'live';
		$this->encryption_method = $this->params->get('redsys_encryption_method', 'sha1');
		$this->_isLog = $this->params->get('debug', 0);
	}


	/**
	 * @param $data     array       form post data
	 * @return string   HTML to display
	 */
	function _prePayment($data)
	{
		$currency = J2Store::currency();
		// prepare the payment form
		$vars = new JObject();
		$vars->order_id = $data['order_id'];
		$vars->orderpayment_id = $data['orderpayment_id'];

		F0FTable::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_j2store/tables');
		$order = F0FTable::getAnInstance('Order', 'J2StoreTable');
		$order->load(array('order_id' => $data['order_id']));

		$currency_values = $this->getCurrency($order, false);
		$vars->orderpayment_amount = round($currency->format($order->order_total, $currency_values['currency_code'], $currency_values['currency_value'], false), 2);
		$amount = $vars->orderpayment_amount * 100;

		$vars->orderpayment_type = $this->_element;
		$vars->onbeforepayment_text = $this->params->get('onbeforepayment', '');
		$vars->onerrorpayment_text = $this->params->get('onerrorpayment', '');
		$vars->button_text = $this->params->get('button_text', 'J2STORE_PLACE_ORDER');

		$url_ok = Uri::root() . "index.php?option=com_j2store&view=checkout&task=confirmPayment&orderpayment_type=" . $this->_element . "&paction=display_message";
		$url_ko = Uri::root() . "index.php?option=com_j2store&view=checkout&task=confirmPayment&orderpayment_type=" . $this->_element . "&paction=cancel";
		$merchant_url = Uri::root() . "index.php?option=com_j2store&view=checkout&task=confirmPayment&orderpayment_type=" . $this->_element . "&paction=process&tmpl=component";

		$orderinfo = $order->getOrderinformation();
		// set variables for user info
		$vars->first_name   = $orderinfo->billing_first_name;
		$vars->last_name    = $orderinfo->billing_last_name;
		$vars->email        = $order->user_email;
		$vars->address_1    = $orderinfo->billing_address_1;
		$vars->address_2    = $orderinfo->billing_address_2;
		$vars->city         = $orderinfo->billing_city;
		$vars->country      = $this->getCountryById($orderinfo->billing_country_id)->country_name;;
		$vars->region       = $this->getZoneById($orderinfo->billing_zone_id)->zone_name;
		$vars->postal_code  = $orderinfo->billing_zip;
		$order_id_for_sermpa = $this->params->get('redsys_prefix_order', '') . '' . $vars->orderpayment_id;
		$consumer_language = $this->params->get('redsys_language', '001');
		$transaction_type = $this->params->get('redsys_transactiontype', '1');
		if ($this->encryption_method != "HMAC_SHA256_V1") {
			require_once(JPath::clean(dirname(__FILE__) . "/redsys/Redsys.php"));
			$redsys = new Redsys($this->payment_page_title, $this->merchant_code, $this->merchant_terminal, $this->merchant_signature, $this->environment, $this->encryption_method);

			$redsys->setAmount($amount)
				->setCurrency($currency_values['currency_number'])
				->setOrder(strval($order_id_for_sermpa))
				->setProductDescription(Text::_('J2STORE_ORDER_DESCRIPTION') . ':' . $vars->orderpayment_id)
				->setConsumerLanguage($consumer_language)
				->setMerchantData($vars->order_id)
				->setTransactionType($transaction_type)
				->setMerchantURL($merchant_url)
				->setUrlOK($url_ok)
				->setUrlKO($url_ko);
			$vars->post_url = $redsys->getEnvironment();
			try {
				$vars->fields = $redsys->getFields();
			} catch (RedsysException $e) {
				$html =  $vars->onerrorpayment_text;
			}
		} else {

			$miObj = new RedsysAPI;
			$miObj->setParameter("DS_MERCHANT_AMOUNT", (string)$amount);
			$miObj->setParameter("DS_MERCHANT_ORDER", strval($order_id_for_sermpa));
			$miObj->setParameter("DS_MERCHANT_MERCHANTCODE", $this->merchant_code);
			$miObj->setParameter("DS_MERCHANT_CURRENCY", $currency_values['currency_number']); //$currency_values['currency_number']
			$miObj->setParameter("DS_MERCHANT_TRANSACTIONTYPE", $transaction_type);
			$miObj->setParameter("DS_MERCHANT_TERMINAL", $this->merchant_terminal);
			$miObj->setParameter("DS_MERCHANT_MERCHANTURL", $merchant_url);
			$miObj->setParameter("DS_MERCHANT_URLOK", $url_ok); //$url_ok
			$miObj->setParameter("DS_MERCHANT_URLKO", $url_ko); //$url_ko
			$miObj->setParameter("Ds_Merchant_MerchantData", $vars->order_id); //$url_ko

			//$vars->version = $this->encryption_method;//"HMAC_SHA256_V1";
			//echo "<pre>";print_r($miObj->vars_pay);exit;
			$kc = $this->merchant_signature; //'Mk9m98IfEblmPfrpsawt7BmxObt98Jev';
			$params = $miObj->createMerchantParameters();
			$signature = $miObj->createMerchantSignature($kc);
			$vars->post_url = $this->getPostUrl();
			$fields = array(
				'Ds_SignatureVersion' => $this->encryption_method,
				'Ds_MerchantParameters' => $params,
				'Ds_Signature' => $signature
			);
			$vars->fields = $fields;
		}

		$html = $this->_getLayout('prepayment', $vars);
		return $html;
	}

	function getPostUrl()
	{
		$mode = $this->params->get('sandbox', 0);
		if ($mode) {
			$server = $this->params->get('test_server', 0);
			if ($server) {
				return "http://sis-d.redsys.es/sis/realizarPago/utf-8";
			} else {
				return "https://sis-t.redsys.es:25443/sis/realizarPago/utf-8";
			}
		} else {
			$server = $this->params->get('live_server', 0);
			if ($server) {
				return "https://sis-i.redsys.es:25443/sis/realizarPago/utf-8";
			} else {
				return "https://sis.redsys.es/sis/realizarPago/utf-8";
			}
		}
		//return $this->params->get('sandbox', 0)? "http://sis-d.redsys.es/sis/realizarPago":"https://sis-i.redsys.es:25443/sis/realizarPago";//'test':'live';
	}
	/**
	 * Processes the payment form
	 * and returns HTML to be displayed to the user
	 * generally with a success/failed message
	 *
	 * @param $data     array       form post data
	 * @return string   HTML to display
	 */
	function _postPayment($data)
	{
		// Process the payment
		$app = Factory::getApplication();
		$paction = $app->input->getString('paction');
		$vars = new JObject();
		switch ($paction) {
			case "display_message":
				$session = Factory::getSession();
				$session->set('j2store_cart', array());
				$vars->message = Text::_($this->params->get('onafterpayment', ''));
				$html = $this->_getLayout('message', $vars);
				$html .= $this->_displayArticle();
				break;
			case "process":
				$vars->message = $this->_process();
				$html = $this->_getLayout('message', $vars);
				echo $html; // TODO Remove this
				$app = Factory::getApplication();
				$app->close();
				break;
			case "cancel":
				$vars->message = Text::_($this->params->get('oncancelpayment', ''));
				$html = $this->_getLayout('message', $vars);
				break;
			default:
				$vars->message = Text::_($this->params->get('onerrorpayment', ''));
				$html = $this->_getLayout('message', $vars);
				break;
		}

		return $html;
	}

	/**
	 * Prepares variables for the payment form
	 *
	 * @return unknown_type
	 */
	function _renderForm($data)
	{
		$user = Factory::getUser();
		$vars = new \stdClass();
		$vars->onselection_text = $this->params->get('onselection', '');
		$html = $this->_getLayout('form', $vars);

		return $html;
	}

	/************************************
	 * Note to 3pd:
	 *
	 * The methods between here
	 * and the next comment block are
	 * specific to this payment plugin
	 *
	 ************************************/

	/**
	 * Gets the value for the Redsys variable
	 *
	 * @param string $name
	 * @return string
	 * @access protected
	 */
	function _getParam($name, $default = '')
	{
		$return = $this->params->get($name, $default);

		$sandbox_param = "sandbox_$name";
		$sb_value = $this->params->get($sandbox_param);
		if ($this->params->get('sandbox') && !empty($sb_value)) {
			$return = $this->params->get($sandbox_param, $default);
		}

		return $return;
	}

	/**
	 * Process callback
	 * @return HTML
	 */
	function _process()
	{
		$app = Factory::getApplication();
		$data = array();
		$response = array();
		$errors = array();
		if ($this->encryption_method != "HMAC_SHA256_V1") {
			$data = $app->input->getArray($_REQUEST);
			//get the raw post
			$transaction_details = $this->_getFormattedTransactionDetails($data);
			$this->_log($transaction_details, "RESPONSE:");
		} else {
			$response = $app->input->getArray($_REQUEST);
			$miObj = new RedsysAPI;
			$data = $miObj->decodeMerchantParameters($response['Ds_MerchantParameters']);
			$kc = $this->merchant_signature;
			$signature = $miObj->createMerchantSignatureNotif($kc, $data);
			$data = json_decode($data, true);
			$transaction_details = $this->_getFormattedTransactionDetails($data);
			$data['signature'] = $signature;
			$data['Ds_Signature'] = $response['Ds_Signature'];
		}

		try {
			//get the feedback
			if (isset($data['Ds_MerchantData']) && $data['Ds_MerchantData']) {
				$this->_log($transaction_details, "Step 1:");
				$transaction_message = '';
				$order_id = $data['Ds_MerchantData'];
				$order = F0FTable::getAnInstance('Order', 'J2StoreTable')->getClone();
				$order->load(array('order_id' => $order_id));
				if (!empty($order->order_id) && $order->order_id == $order_id) {
					$order->transaction_details = '';
					//first set the order status to pending by default
					$order->update_status(4, true);
					if ((int) $data['Ds_Amount']) {
						$amount = (int) $data['Ds_Amount'];
					} else {
						$currency_values = $this->getCurrency($order);
						$stored_amount = $this->format($order->order_total, $currency_values['currency_code'], $currency_values['currency_value'], false);
						$amount = $stored_amount * 100;
					}
					//get the transaction message and authorization code
					//run few basic checks
					//check the merchant code
					if ($data['Ds_MerchantCode'] != $this->merchant_code) {
						//set order status failed
						$order->update_status(3);
						$errors[] = Text::_('J2STORE_REDSYS_ERROR_MERCHANT_CODE_MISMATCH');
					}
					//no errors, go ahead and check the feedback
					if (count($errors) < 1) {
						try {
							//validate the signature.
							// Transaction valid. Save your data here.
							if ((int) $data['Ds_Response'] <= 99) {
								//authorized
								$order->payment_complete();
							}

							if ((int) $data['Ds_Response'] == 9915) {
								$order->update_status(6);
							}
						} catch (RedsysException $e) {
							// Transaction no valid. Save your data here.
							$errors[] = $e;
							//set order status failed
							$order->update_status(3);
						}
					}
					//check for errors. if they are, then set the order state ID to failed
					if (count($errors)) {
						//set order status failed
						$order->update_status(3);
						$order->transaction_details .= implode('/n', $errors);
					}
					$order->transaction_id = $data['Ds_AuthorisationCode'];
					$order->transaction_status = $data['Ds_Response'];;
					$order->transaction_details .= $this->_getFormattedTransactionDetails($data);

					// save the data
					if (! $order->store()) {
						$errors[] = $order->getError();
					}
					//clear cart
					$order->empty_cart();
				} else {
					$errors[] = Text::_('J2STORE_REDSYS_ERROR_INVALID_ORDERPAYMENTID');
				}
			}
		} catch (RedsysException $e) {
			$errors[] = $e;
		}

		if (count($errors)) {
			$config = Factory::getConfig();
			if (version_compare(JVERSION, '3.0', 'ge')) {
				$sitename = $config->get('sitename');
			} else {
				$sitename = $config->getValue('config.sitename');
			}

			$error = implode('/n', $errors);
			//send error notification to the administrators
			$subject = Text::sprintf('J2STORE_REDSYS_EMAIL_PAYMENT_NOT_VALIDATED_SUBJECT', $sitename);
			$body = Text::sprintf('J2STORE_REDSYS_EMAIL_PAYMENT_FAILED_BODY', 'Administrator', $sitename, Uri::root(), $error, $transaction_details);
			$receivers = $this->_getAdmins();
			foreach ($receivers as $receiver) {
				J2Store::email()->sendErrorEmails($receiver->email, $subject, $body);
			}
			return $error;
		}

		// if here, all went well
		return Text::_($this->params->get('onafterpayment', ''));
	}

	/**
	 * Gets admins data
	 *
	 * @return array|boolean
	 * @access protected
	 */
	function _getAdmins()
	{
		$db = Factory::getDBO();
		$query = $db->getQuery(true);
		$query->select('u.name,u.email');
		$query->from('#__users AS u');
		$query->join('LEFT', '#__user_usergroup_map AS ug ON u.id=ug.user_id');
		$query->where('u.sendEmail = 1');
		$query->where('ug.group_id = 8');

		$db->setQuery($query);
		$admins = $db->loadObjectList();
		if ($error = $db->getErrorMsg()) {
			JError::raiseError(500, $error);
			return false;
		}

		return $admins;
	}


	/**
	 * Formatts the payment data for storing
	 *
	 * @param array $data
	 * @return string
	 */
	function _getFormattedTransactionDetails($data)
	{
		$separator = "\n";
		$formatted = array();

		foreach ($data as $key => $value) {
			if ($key != 'view' && $key != 'layout') {
				$formatted[] = $key . ' = ' . $value;
			}
		}

		return count($formatted) ? implode("\n", $formatted) : '';
	}

	/**
	 * Simple logger
	 *
	 * @param string $text
	 * @param string $type
	 * @return void
	 */
	function _log($text, $type = 'message')
	{
		if ($this->_isLog) {
			$file = JPATH_ROOT . "/cache/{$this->_element}.log";
			$date = Factory::getDate();

			$f = fopen($file, 'a');
			fwrite($f, "\n\n" . $date->format('Y-m-d H:i:s'));
			fwrite($f, "\n" . $type . ': ' . $text);
			fclose($f);
		}
	}
	//get currency code, value, number
	function getCurrency($order, $convert = false)
	{
		$results = array();
		$currency_code = $order->currency_code;
		$currency_value = $order->currency_value;
		$currencies = $this->getAcceptedCurrencies();
		$currencyObject = J2Store::currency();
		if (isset($currencies[$order->currency_code]) && JString::strlen($currencies[$order->currency_code]) > 1) {
			$currency_number = $currencies[$order->currency_code];
		} else {
			$default_currency = $this->params->get('redsys_currency', '978');
			$code = array_search($default_currency, $currencies);
			if ($code && $currencyObject->has($code)) {
				$currencyObject->set($code);

				$currency_number = $default_currency;
				$currency_code = $code;
				$currency_value = $currencyObject->getValue($code);
				$convert = true;
			}
		}
		$results['currency_number'] = $currency_number;
		$results['currency_code'] = $currency_code;
		$results['currency_value'] = $currency_value;
		$results['convert'] = $convert;

		return $results;
	}

	//accepted currency
	function getAcceptedCurrencies()
	{

		$currencies = array(
			'EUR' => '978',
			'USD' => '840',
			'GBP' => '826',
			'JPY' => '392',
			'ARS' => '032',
			'CAD' => '124',
			'CLP' => '152',
			'COP' => '170',
			'INR' => '356',
			'MXN' => '484',
			'PEN' => '604',
			'CHF' => '756',
			'BRL' => '986',
			'VEF' => '937',
			'TRY' => '949'

		);

		return $currencies;
	}
}
