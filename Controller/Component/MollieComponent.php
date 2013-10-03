<?php
App::uses('HttpSocket', 'Network/Http');
App::uses('String', 'Utility');
App::uses('Validation', 'Utility');

class MollieComponent extends Component {

	public $partnerId = null;

	public $testmode = false;

	public $reportUrl = array('controller' => 'api', 'action' => 'report');

	public $returnUrl = array('controller' => 'payment', 'action' => 'return');

	private function __getHttpSocket() {
		/* Need to disable verify_host for now because secure.mollie.nl != mollie.nl */
		$sock = new HttpSocket(array('ssl_verify_host' => false));
		$sock->responseClass = 'Mollie.XmlResponse';
		return $sock;
	}

	private function __testmode(&$options) {
		if ($this->testmode = true) {
			if (is_array($options)) {
				$options['testmode'] = 'true';
			} else {
				$options .= '&testmode=true';
			}
		}
	}

	public function iDealBanklist() {
		$params = array('a' => 'banklist');
		$this->__testmode($params);

		$sock = $this->__getHttpSocket();
		$response = $sock->get('https://secure.mollie.nl/xml/ideal', $params);
		foreach ($response->body()->bank as $bank) {
			//@codingStandardsIgnoreStart
			$bankid = (string)$bank->bank_id;
			$banks[$bankid] = (string)$bank->bank_name;
			//@codingStandardsIgnoreEnd
		}
		return $banks;
	}
/**
 * @throws InternalErrorException when no partner ID is configured
 */
	public function iDealFetchPayment($bankId, $amount, $description, $return = null) {
		if (is_null($this->partnerId)) {
			throw InternalErrorException('No Mollie partner ID configured');
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
		$sock = $this->__getHttpSocket();
		$response = $sock->get('https://secure.mollie.nl/xml/ideal', $params);
		$data = $response->body()->order;
		return array(
			'url' => (string)$data->URL,
			//@codingStandardsIgnoreStart
			'transaction_id' => (string)$data->transaction_id,
			//@codingStandardsIgnoreEnd
			'currency' => (string)$data->currency);
	}

/**
 * @throws InternalErrorException when no partner ID is configured
 */
	public function iDealCheckPayment($transactionId, &$payed) {
		if (is_null($this->partnerId)) {
			throw InternalErrorException('No Mollie partner ID configured');
		}
		$params = array(
			'a' => 'check',
			'partnerid' => $this->partnerId,
			'transaction_id' => $transactionId);
		$this->__testmode($params);
		$payed = false;
		try {
			$sock = $this->__getHttpSocket();
			$response = $sock->get('https://secure.mollie.nl/xml/ideal', $params);
			if ($response) {
				$res = $response->body();
				if (isset($res->order)) {
					$data = $res->order;
					$payed = (string)$data->payed;
					return array(
						'payed' => $payed,
						'amount' => (string)$data->amount,
						'name' => (string)$data->consumer->consumerName,
						'account' => (string)$data->consumer->consumerAccount,
						'city' => (string)$data->consumer->consumerCity);
				}
				return array('payed' => false);
			}
			return false;
		} catch (Exception $e) {
			return false;
		}
	}

/**
 * @throws InternalErrorException when no partner ID is configured
 */
	public function callFetchPayment($amount, $report = null) {
		if (is_null($this->partnerId)) {
			throw InternalErrorException('No Mollie partner ID configured');
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
		$sock = $this->__getHttpSocket();

		$response = $sock->get('https://www.mollie.nl/xml/micropayment/', $params);
		$data = $response->body()->item;

		$mode = (string)$data->mode;
		if ($mode == 'ppc') {
			$customercost = (string)$data->costpercall;
		} elseif ($mode == 'ppm') {
			$customercost = (string)$data->costperminute;
		}

		return array(
			'number' => (string)$data->servicenumber,
			'mode' => $mode,
			'paycode' => (string)$data->paycode,
			'amount' => (string)$data->amount,
			'duration' => (string)$data->duration,
			'customercost' => $customercost,
			'currency' => (string)$data->currency,
			'payout' => (string)$data->payout);
	}

	public function callCheckPayment($servicenumber, $paycode, &$payed) {
		$params = array(
			'a' => 'check',
			'servicenumber' => $servicenumber,
			'paycode' => $paycode);
		$payed = false;
		try {
			$sock = $this->__getHttpSocket();
			$response = $sock->get('https://www.mollie.nl/xml/micropayment/', $params);
			if ($response) {
				$data = $response->body()->item;

				$payed = (string)$data->payed;
				return array(
					'payed' => $payed,
					'amount' => (string)$data->amount);
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
		if (is_null($this->partnerId)) {
			throw InternalErrorException('No Mollie partner ID configured');
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
