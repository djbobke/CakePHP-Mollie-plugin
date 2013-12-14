<?php
App::uses('HttpSocket', 'Network/Http');
App::uses('String', 'Utility');
App::uses('Validation', 'Utility');
App::uses('Xml', 'Utility');

class MollieComponent extends Component {

	public $partnerId = null;

	public $testmode = null;

	public $reportUrl = array('controller' => 'api', 'action' => 'report');

	public $returnUrl = array('controller' => 'payment', 'action' => 'return');

	public function __construct(ComponentCollection $collection, $settings = array()) {
		parent::__construct($collection, $settings);

		if ($this->testmode === null) {
			$this->testmode = (Configure::read('debug') >= 1);
		}

		/* Need to disable verify_host for now because secure.mollie.nl != mollie.nl */
		$this->Http = new HttpSocket(array('ssl_verify_host' => false));
	}

	private function __testmode(&$options) {
		if ($this->testmode == true) {
			$options['testmode'] = 'true';
		}
	}

	public function iDealBanklist() {
		$params = array('a' => 'banklist');
		$this->__testmode($params);

		$response = $this->Http->get('https://secure.mollie.nl/xml/ideal', $params);
		$data = Xml::toArray(Xml::build($response->body));

		if (isset($data['response']['bank']['bank_id'])) {
			/* apparently there is only 1 bank */
			$banks = array($data['response']['bank']['bank_id'] => $data['response']['bank']['bank_name']);
		} else {
			foreach ($data['response']['bank'] as $bank) {
				$bankid = $bank['bank_id'];
				$banks[$bankid] = $bank['bank_name'];
			}
		}
		return $banks;
	}
/**
 * @throws InternalErrorException when no partner ID is configured
 */
	public function iDealFetchPayment($bankId, $amount, $description, $return = null) {
		if ($this->partnerId === null) {
			throw new InternalErrorException(__('No Mollie partner ID configured'));
		}
		$params = array(
			'a' => 'fetch',
			'partnerid' => $this->partnerId,
			'bank_id' => $bankId,
			'amount' => $amount,
			'description' => $description,
			'reporturl' => Router::url($this->reportUrl, true),
			'returnurl' => Router::url($this->returnUrl, true),
		);
		if (!is_null($return)) {
			$params['returnurl'] = Router::url($return, true);
		}
		$this->__testmode($params);
		$response = $this->Http->get('https://secure.mollie.nl/xml/ideal', $params);
		$data = Xml::toArray(Xml::build($response->body));

		return array(
			'url' => $data['response']['order']['URL'],
			'transaction_id' => $data['response']['order']['transaction_id'],
			'currency' => $data['response']['order']['currency']);
	}

/**
 * @throws InternalErrorException when no partner ID is configured
 */
	public function iDealCheckPayment($transactionId, &$payed) {
		if ($this->partnerId === null) {
			throw new InternalErrorException(__('No Mollie partner ID configured'));
		}
		$params = array(
			'a' => 'check',
			'partnerid' => $this->partnerId,
			'transaction_id' => $transactionId);
		$this->__testmode($params);
		$payed = false;
		try {
			$response = $this->Http->get('https://secure.mollie.nl/xml/ideal', $params);
			$data = Xml::toArray(Xml::build($response->body));
			if (isset($data['response']['order']) && $data['response']['order']['payed'] == 'true') {
				$data = $data['response']['order'];
				$payed = $data['payed'];
				return array(
					'payed' => $payed,
					'amount' => $data['amount'],
					'name' => $data['consumer']['consumerName'],
					'account' => $data['consumer']['consumerAccount'],
					'city' => $data['consumer']['consumerCity']);
			}
			return array('payed' => false);
		} catch (Exception $e) {
			return false;
		}
	}

/**
 * @throws InternalErrorException when no partner ID is configured
 */
	public function callFetchPayment($amount, $report = null) {
		if ($this->partnerId === null) {
			throw new InternalErrorException(__('No Mollie partner ID configured'));
		}
		$params = array(
			'a' => 'fetch',
			'partnerid' => $this->partnerId,
			'amount' => $amount,
			'country' => 31,
			'report' => Router::url($this->reportUrl, true),
		);
		if (!is_null($report)) {
			$params['report'] = Router::url($report, true);
		}
		$response = $this->Http->get('https://www.mollie.nl/xml/micropayment/', $params);
		$data = Xml::toArray(Xml::build($response->body));

		if (!isset($data['response']['item'])) {
			return false;
		}
		$data = $data['response']['item'];
		$mode = $data['mode'];
		if ($mode == 'ppc') {
			$customercost = $data['costpercall'];
		} elseif ($mode == 'ppm') {
			$customercost = $data['costperminute'];
		}

		return array(
			'number' => $data['servicenumber'],
			'mode' => $mode,
			'paycode' => $data['paycode'],
			'amount' => $data['amount'],
			'duration' => $data['duration'],
			'customercost' => $customercost,
			'currency' => $data['currency'],
			'payout' => $data['payout']);
	}

	public function callCheckPayment($servicenumber, $paycode, &$payed) {
		if ($this->partnerId === null) {
			throw new InternalErrorException(__('No Mollie partner ID configured'));
		}

		$params = array(
			'a' => 'check',
			'servicenumber' => $servicenumber,
			'paycode' => $paycode);
		$payed = false;
		try {
			$response = $this->Http->get('https://www.mollie.nl/xml/micropayment/', $params);
			$data = Xml::toArray(Xml::build($response->body));

			if (isset($data['response']['item']) && $data['response']['item']['payed'] == 'true') {
				$data = $data['response']['item'];

				$payed = ($data['payed'] == 'true');
				return array(
					'payed' => $payed,
					'amount' => $data['amount']);
			}
			return false;
		} catch (Exception $e) {
			return false;
		}
	}

/**
 * @throws InternalErrorException when no partner ID is configured
 */
	public function smsFetchPayment($micropaymentId, $amount = null, $country = null) {
		if ($this->partnerId === null) {
			throw new InternalErrorException(__('No Mollie partner ID configured'));
		}

		$paycode = String::uuid();
		$targetUrl = 'http://www.mollie.nl/partners/betaal/?partnerid=' . $this->partnerId . '&id=' . $micropaymentId;
		if (!is_null($country)) {
			$targetUrl .= '&land=' . $country;
		}
		$targetUrl .= '&parameter[1]=' . $paycode;
		return array(
			'paycode' => $paycode,
			'amount' => $amount,
			'targetUrl' => $targetUrl
			);
	}

	public function smsCheckPayment($paycode, &$payed) {
		if (Validation::uuid($paycode) == true) {
			/* Set payed always to true, Mollie offers no way to verify the payment
			 * we need to trust on their callback
			 */
			$payed = 'true';
		} else {
			$payed = 'false';
		}
		return array('payed' => $payed);
	}

}
