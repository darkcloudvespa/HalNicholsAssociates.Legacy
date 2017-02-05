<?php
/**
 * eProcessing
 *
 * eProcessing onsite payment processor
 *
 * @version 1.1
 * @author Jonathan Davis
 * @copyright Ingenesis Limited, June, 2012
 * @license GNU GPL version 3 (or later) {@see license.txt}
 * @subpackage eProcessing
 * @package shopp
 * @since 1.2
 **/

defined( 'WPINC' ) || header( 'HTTP/1.1 403' ) & exit; // Prevent direct access

class eProcessing extends GatewayFramework implements GatewayModule {

	public $secure = true;
	public $captures = true;
	public $refunds = true;
	public $cards = array('visa', 'mc', 'amex', 'disc', 'jcb', 'dc');

	const APIURL = 'https://www.eprocessingnetwork.com/cgi-bin/tdbe/transact.pl';

	public function __construct () {
		parent::__construct();

		$this->setup('ePNAccount', 'RestrictKey');

		// Initialize message protocols
		$this->messages();

		add_action('shopp_eprocessing_sale',    array($this, 'sale'));
		add_action('shopp_eprocessing_auth',    array($this, 'sale'));
		add_action('shopp_eprocessing_capture', array($this, 'capture'));
		add_action('shopp_eprocessing_void',    array($this, 'void'));
		add_action('shopp_eprocessing_refund',  array($this, 'refund'));
	}

	public function actions () { /* Not implemented */ }

	public function sale ( $Event ) {

		$request = apply_filters('shopp_eprocessing_sale_txn', array(), $Event);
		$response = $this->send($request);

		if ( is_a($response, 'ShoppError') ) {
			return shopp_add_order_event($Event->order, 'auth-fail', array(
				'amount' => $Event->amount,                  // Amount to be captured
				'error' => $response->code,                  // Error code (if provided)
				'message' => join(' ', $response->messages), // Error message reported by the gateway
				'gateway' => $Event->gateway                 // Gateway handler name (module name from @subpackage)
			));
		}

		shopp_add_order_event($Event->order, 'authed',array(
			'txnid' => $response['txnid'],
			'amount' => $Event->amount,
			'gateway' => $Paymethod->processor,
			'paymethod' => $Paymethod->label,
			'paytype' => $Billing->cardtype,
			'payid' => $Billing->card,
			'capture' => ('sale' == $Event->name)
		));

	}

	public function capture ( $Event ) {

		$request = apply_filters('shopp_eprocessing_auth2sale_txn', array(), $Event);
		$response = $this->send($request);

		if (!$response || is_a($response,'ShoppError')) {
			return shopp_add_order_event($Event->order, 'capture-fail', array(
				'amount' => $Event->amount,			// Amount to be captured
				'error' => $response->code,			// Error code (if provided)
				'message' => join(' ', $response->messages),	// Error message reported by the gateway
				'gateway' => $Event->gateway		// Gateway handler name (module name from @subpackage)
			));
		}

		shopp_add_order_event($Event->order,'captured',array(
			'txnid' => $response['txnid'],
			'amount' => $Event->amount,
			'fees' => '',
			'gateway' => $Event->gateway
		));

	}

	public function void ( $Event ) {

		$request = apply_filters('shopp_eprocessing_void_txn', array(), $Event);
		$response = $this->send($request);

		if (!$response || is_a($response, 'ShoppError')) {
			return shopp_add_order_event($Event->order, 'void-fail',array(
				'error' => $response->code,                  // Error code (if provided)
				'message' => join(' ', $response->messages), // Error message reported by the gateway
				'gateway' => $Event->gateway                 // Gateway handler name (module name from @subpackage)
			));
		}

		shopp_add_order_event($Event->order,'voided',array(
			'txnorigin' => $Event->txnid,  // Original transaction ID (txnid of original Purchase record)
			'txnid' => $response['txnid'], // Transaction ID for the VOID event
			'gateway' => $Event->gateway   // Gateway handler name (module name from @subpackage)
		));

	}

	public function refund ( $Event ) {

		$request = apply_filters('shopp_eprocessing_return_txn',array(),$Event);
		// echo $request; exit;
		$response = $this->send($request);

		if (!$response || is_a($response,'ShoppError')) {
			return shopp_add_order_event($Event->order,'refund-fail',array(
				'amount' => $Event->amount,					// Amount of the refund attempt
				'error' => $response->code,					// Error code (if provided)
				'message' => join(' ',$response->messages),	// Error message reported by the gateway
				'gateway' => $Event->gateway				// Gateway handler name (module name from @subpackage)
			));
		}

		shopp_add_order_event($Event->order,'refunded',array(
			'txnid' => $response['txnid'],					// Transaction ID for the REFUND event
			'amount' => $Event->amount,						// Amount refunded
			'gateway' => $Event->gateway					// Gateway handler name (module name from @subpackage)
		));

	}

	public function messages () {

		// Authorization-only and Authorization+Capture transactions
		add_filter('shopp_eprocessing_sale_txn', array($this, 'credentials'));
		add_filter('shopp_eprocessing_sale_txn', array($this, 'payment'), 10, 2);
		add_filter('shopp_eprocessing_sale_txn', array($this, 'encode'));

		// Capture
		add_filter('shopp_eprocessing_auth2sale_txn', array($this, 'credentials'));
		add_filter('shopp_eprocessing_auth2sale_txn', array($this, 'auth2sale'), 10, 2);
		add_filter('shopp_eprocessing_auth2sale_txn', array($this, 'encode'));

		// Refunds
		add_filter('shopp_eprocessing_return_txn', array($this, 'credentials'));
		add_filter('shopp_eprocessing_return_txn', array($this, 'returntxn'), 10, 2);
		add_filter('shopp_eprocessing_return_txn', array($this, 'encode'));

		// Void/Cancel
		add_filter('shopp_eprocessing_void_txn', array($this, 'credentials'));
		add_filter('shopp_eprocessing_void_txn', array($this, 'voidtxn'), 10, 2);
		add_filter('shopp_eprocessing_void_txn', array($this, 'encode'));

	}

	public function credentials ($_) {

		$_['ePNAccount'] = $this->settings['ePNAccount'];
		$_['RestrictKey'] = $this->settings['RestrictKey'];

		$_['Inv'] = 'report';
		$_['HTML'] = 'No';

		return $_;
	}

	public function payment ( $_, $Event ) {
		$Order = ShoppOrder();
		$Customer = $Order->Customer;
		$Billing = $Order->Billing;

		// Required fields
		$_['TranType'] = 'sale' == $Event->name? 'Sale' : 'AuthOnly';
		$_['CardNo'] = $Billing->card;
		$_['ExpMonth'] = date('m',$Billing->cardexpires);
		$_['ExpYear'] = date('y',$Billing->cardexpires);
		$_['Total'] = $this->amount('total');
		$_['Address'] = $Billing->address;
		$_['Zip'] = $Billing->postcode;
		$_['CVV2Type'] = 1;
		$_['CVV2'] = $Billing->cvv;

		// Optional fields
		$_['Company']   = $Customer->company;
		$_['FirstName'] = $Customer->firstname;
		$_['LastName']  = $Customer->lastname;
		$_['Phone']     = $Customer->phone;
		$_['EMail']     = $Customer->email;

		return $_;
	}

	public function auth2sale ($_,$Event) {

		$_['TranType'] = 'Auth2Sale';
		$_['TransID'] = $Event->txnid;

		return $_;
	}

	public function voidtxn ($_,$Event) {

		$_['TranType'] = 'Void';
		$_['TransID'] = $Event->txnid;

		return $_;
	}

	public function returntxn ($_,$Event) {

		$_['TranType'] = 'Return';
		$_['TransID'] = $Event->txnid;
		$_['Total'] = $this->amount($Event->amount);

		return $_;
	}

	public function send ($data) {

		$response = parent::send($data,self::APIURL);

		// Error handling
		if ( empty($response) ) return new ShoppError(Lookup::errors('gateway','noresponse'),'eprocessing_noresponse',SHOPP_COMM_ERR);

		$response = self::response($response);
		if ( 'Y' != $response['txn'] ) return new ShoppError($response['msg'], 'eprocessing_error', SHOPP_TRXN_ERR);

		return $response;
	}

	static function response ($buffer) {
		$result = array();
		$keys = array('response','avs','cvv2','inv','txnid');

		$buffer = strip_tags($buffer);
		$values = explode(',',$buffer);

		foreach ($values as $i => $value)
			$result[ $keys[$i] ] = trim($value,'"');

		$result['txn'] = $result['response']{0};
		$result['msg'] = substr($result['response'],1);

		return $result;
	}

	public function settings () {
		$this->ui->cardmenu(0,array(
			'name' => 'cards',
			'selected' => $this->settings['cards']
		),$this->cards);

		$this->ui->text(1,array(
			'name' => 'ePNAccount',
			'value' => $this->settings['ePNAccount'],
			'size' => '8',
			'label' => __('Enter your eProcessingNetwork Account number.','Shopp')
		));

		$this->ui->text(1,array(
			'name' => 'RestrictKey',
			'value' => $this->settings['RestrictKey'],
			'size' => '16',
			'label' => __('Enter your eProcessingNetwork Restrict Key.','Shopp')
		));
	}

} // END class eProcessing