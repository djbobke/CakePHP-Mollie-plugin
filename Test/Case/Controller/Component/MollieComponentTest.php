<?php
App::uses('ComponentCollection', 'Controller');
App::uses('Component', 'Controller');
App::uses('MollieComponent', 'Mollie.Controller/Component');
App::uses('String', 'Utility');
App::uses('Validation', 'Utility');

class StubHttpResponse {

	public $body = null;

	public function __construct($data) {
		$this->body = $data;
	}

}

class StubHttpSocket extends Object {

	public $body = null;

	public $throw = false;

	public $args = array();

	public $numArgs = null;

/**
 * @throws Exception when throw is true;
 */
	public function get($uri, $query = null, $request = null) {
		$this->numArgs = func_num_args();
		$this->args = func_get_args();

		if ($this->throw == true) {
			throw new Exception(__('Blegh!'));
		}
		return new StubHttpResponse($this->body);
	}

	public function post($uri, $data = null, $request = null) {
		return new StubHttpResponse($this->body);
	}

}

/**
 * MollieComponent Test Case
 *
 */
class MollieComponentTest extends CakeTestCase {

	public $settings = array(
		'partnerId' => 123456,
		'testmode' => true);

/**
 * These teststrings are taken from Mollie's online documentation.
 */
	public $testStrings = array('idealbanklist1' => '<?xml version="1.0"?>
			<response>
			    <bank>
			        <bank_id>0031</bank_id>
			        <bank_name>ABN AMRO</bank_name>
			    </bank>
			    <bank>
			        <bank_id>0721</bank_id>
			        <bank_name>Postbank</bank_name>
			    </bank>
			    <bank>
			        <bank_id>0021</bank_id>
			        <bank_name>Rabobank</bank_name>
			    </bank>
			    <message>This is the current list of banks and their ID\'s that currently support iDEAL-payments</message>
			</response>',
		'idealbanklist2' => '<?xml version="1.0"?>
			<response>
			    <bank>
			        <bank_id>0031</bank_id>
			        <bank_name>ABN AMRO</bank_name>
			    </bank>
			    <message>This is the current list of banks and their ID\'s that currently support iDEAL-payments</message>
			</response>',
		'idealfetch1' => '<?xml version="1.0"?>
			<response>
			    <order>
			        <transaction_id>482d599bbcc7795727650330ad65fe9b</transaction_id>
			        <amount>123</amount>
			        <currency>EUR</currency>
			        <URL>https://mijn.postbank.nl/internetbankieren/SesamLoginServlet?sessie=ideal&amp;trxid=003123456789123&amp;random=123456789abcdefgh</URL>
			        <message>Your iDEAL-payment has succesfuly been setup. Your customer should visit the given URL to make the payment</message>
			    </order>
			</response>',
		'idealcheck1' => '<?xml version="1.0"?>
			<response>
			    <order>
			        <transaction_id>482d599bbcc7795727650330ad65fe9b</transaction_id>
			        <amount>123</amount>
			        <currency>EUR</currency>
			        <payed>true</payed>
			        <consumer>
			            <consumerName>Hr J Janssen</consumerName>
			            <consumerAccount>P001234567</consumerAccount>
			            <consumerCity>Amsterdam</consumerCity>
			        </consumer>
			        <message>This iDEAL-order has successfuly been payed for, and this is the first time you check it.</message>
			    </order>
			</response>',
		'idealcheck2' => '<?xml version="1.0"?>
			<response>
			    <order>
			        <transaction_id>482d599bbcc7795727650330ad65fe9b</transaction_id>
			        <payed>false</payed>
			    </order>
			</response>',
		'callfetch1' => '<?xml version="1.0"?>
			<response>
			    <item country="31">
			        <servicenumber>0909-1100400</servicenumber>
			        <paycode>012345</paycode>
			        <amount>1.75</amount>
			        <duration>131</duration>
			        <mode>ppm</mode>
			        <costperminute>0.80</costperminute>
			        <payout>1.09</payout>
			        <currency>euro</currency>
			    </item>
			</response>',
		'callfetch2' => '<?xml version="1.0"?>
			<response>
			    <item country="31">
			        <servicenumber>0909-1100400</servicenumber>
			        <paycode>012345</paycode>
			        <amount>1.30</amount>
			        <duration>0</duration>
			        <mode>ppc</mode>
			        <costpercall>1.30</costpercall>
			        <payout>0.00</payout>
			        <currency>euro</currency>
			    </item>
			</response>',
		'callfetch3' => '<?xml version="1.0"?>
			<response>
			    <itemmm country="31">
			        <servicenumber>0909-1100400</servicenumber>
			        <paycode>012345</paycode>
			        <amount>1.75</amount>
			        <duration>131</duration>
			        <mode>ppm</mode>
			        <costperminute>0.80</costperminute>
			        <payout>1.09</payout>
			        <currency>euro</currency>
			    </itemmm>
			</response>',
		'callcheck1' => '<?xml version="1.0"?>
			<response>
			    <item country="31">
			        <servicenumber>0909-1100400</servicenumber>
			        <paycode>012345</paycode>
			        <payed>true</payed>
			        <durationdone>131</durationdone>
			        <durationleft>0</durationleft>
			        <amount>1.75</amount>
			        <currency>euro</currency>
			        <paystatus>Payment done.</paystatus>
			    </item>
			</response>',
		'callcheck2' => '<?xml version="1.0"?>
			<response>
			    <item country="31">
			        <servicenumber>0909-1100400</servicenumber>
			        <paycode>012345</paycode>
			        <payed>false</payed>
			    </item>
			</response>',
		'callcheck3' => '<?xml version="1.0"?>
			<response>
			    <itemmm country="31">
			        <servicenumber>0909-1100400</servicenumber>
			        <paycode>012345</paycode>
			        <payed>true</payed>
			        <durationdone>131</durationdone>
			        <durationleft>0</durationleft>
			        <amount>1.75</amount>
			        <currency>euro</currency>
			        <paystatus>Payment done.</paystatus>
			    </itemmm>
			</response>');

/**
 * setUp method
 *
 * @return void
 */
	public function setUp() {
		Router::fullBaseUrl('http://www.example.com/test');
		parent::setUp();
		$this->Collection = new ComponentCollection();
		$this->Mollie = new MollieComponent($this->Collection, $this->settings);
		$this->Mollie->Http = new StubHttpSocket();
	}

/**
 * tearDown method
 *
 * @return void
 */
	public function tearDown() {
		unset($this->Mollie);
		parent::tearDown();
	}

	public function testTestMode() {
		$this->Mollie = new MollieComponent($this->Collection);
		$this->assertNotIdentical($this->Mollie->testmode, null);

		$this->Mollie = new MollieComponent($this->Collection, $this->settings);
		$this->Mollie->Http = new StubHttpSocket();
		$this->Mollie->testmode = true;
		$this->Mollie->Http->body = $this->testStrings['idealbanklist1'];
		$this->assertEqual($this->Mollie->iDealBanklist(), array(
			'0031' => 'ABN AMRO',
			'0721' => 'Postbank',
			'0021' => 'Rabobank'));
		$this->assertEquals(isset($this->Mollie->Http->args[1]['testmode']), true);
		$this->assertEquals($this->Mollie->Http->args[1]['testmode'], 'true');

		$this->Mollie->testmode = false;
		$this->Mollie->Http->body = $this->testStrings['idealbanklist1'];
		$this->assertEqual($this->Mollie->iDealBanklist(), array(
			'0031' => 'ABN AMRO',
			'0721' => 'Postbank',
			'0021' => 'Rabobank'));
		$this->assertEquals(isset($this->Mollie->Http->args[1]['testmode']), false);
	}

/**
 * testIDealBanklist method
 *
 * @return void
 */
	public function testIDealBanklist() {
		$this->Mollie->Http->body = $this->testStrings['idealbanklist1'];
		$this->assertEqual($this->Mollie->iDealBanklist(), array(
			'0031' => 'ABN AMRO',
			'0721' => 'Postbank',
			'0021' => 'Rabobank'));
		$this->assertEquals($this->Mollie->Http->numArgs, 2);
		$this->assertEquals($this->Mollie->Http->args[0], 'https://secure.mollie.nl/xml/ideal');
		$this->assertEquals($this->Mollie->Http->args[1], array(
			'a' => 'banklist',
			'testmode' => 'true'));

		$this->Mollie->Http->body = $this->testStrings['idealbanklist2'];
		$this->assertEqual($this->Mollie->iDealBanklist(), array(
			'0031' => 'ABN AMRO'));
		$this->assertEquals($this->Mollie->Http->numArgs, 2);
		$this->assertEquals($this->Mollie->Http->args[0], 'https://secure.mollie.nl/xml/ideal');
		$this->assertEquals($this->Mollie->Http->args[1], array(
			'a' => 'banklist',
			'testmode' => 'true'));
	}

/**
 * testIDealFetchPayment method
 *
 * @return void
 */
	public function testIDealFetchPayment() {
		$this->Mollie->Http->body = $this->testStrings['idealfetch1'];
		$this->assertEqual($this->Mollie->iDealFetchPayment('0021', 10000, 'test'), array(
			'url' => 'https://mijn.postbank.nl/internetbankieren/SesamLoginServlet?sessie=ideal&trxid=003123456789123&random=123456789abcdefgh',
			'transaction_id' => '482d599bbcc7795727650330ad65fe9b',
			'currency' => 'EUR'));

		$this->assertEqual($this->Mollie->iDealFetchPayment('0021', 10000, 'test', array('controller' => 'piet', 'action' => 'index')), array(
			'url' => 'https://mijn.postbank.nl/internetbankieren/SesamLoginServlet?sessie=ideal&trxid=003123456789123&random=123456789abcdefgh',
			'transaction_id' => '482d599bbcc7795727650330ad65fe9b',
			'currency' => 'EUR'));
	}

/**
 * testIDealCheckPayment method
 *
 * @return void
 */
	public function testIDealCheckPayment() {
		$this->Mollie->Http->body = $this->testStrings['idealcheck1'];
		$payed = false;
		$ret = $this->Mollie->iDealCheckPayment('doesnt-matter-for-test', $payed);
		$this->assertEqual($payed, 'true');
		$this->assertEqual($ret, array(
			'payed' => 'true',
			'amount' => '123',
			'name' => 'Hr J Janssen',
			'account' => 'P001234567',
			'city' => 'Amsterdam'));

		$this->Mollie->Http->body = $this->testStrings['idealcheck2'];
		$payed = false;
		$ret = $this->Mollie->iDealCheckPayment('doesnt-matter-for-test', $payed);
		$this->assertEqual($ret, array('payed' => false));
		$this->assertEqual($payed, false);

		$this->Mollie->Http->throw = true;
		$payed = false;
		$ret = $this->Mollie->iDealCheckPayment('doesnt-matter-for-test', $payed);
		$this->assertEqual($ret, false);
		$this->assertEqual($payed, false);
	}

/**
 * testCallFetchPayment method
 *
 * @return void
 */
	public function testCallFetchPayment() {
		$this->Mollie->Http->body = $this->testStrings['callfetch1'];
		$ret = $this->Mollie->callFetchPayment(12340, array('controller' => 'blog', 'action' => 'index'));
		$this->assertEqual($ret, array(
			'number' => '0909-1100400',
			'mode' => 'ppm',
			'paycode' => '012345',
			'amount' => '1.75',
			'duration' => '131',
			'customercost' => '0.80',
			'currency' => 'euro',
			'payout' => '1.09'));

		$this->Mollie->Http->body = $this->testStrings['callfetch2'];
		$ret = $this->Mollie->callFetchPayment(12340, array('controller' => 'blog', 'action' => 'index'));
		$this->assertEqual($ret, array(
			'number' => '0909-1100400',
			'mode' => 'ppc',
			'paycode' => '012345',
			'amount' => '1.30',
			'duration' => '0',
			'customercost' => '1.30',
			'currency' => 'euro',
			'payout' => '0.00'));

		$this->Mollie->Http->body = $this->testStrings['callfetch3'];
		$ret = $this->Mollie->callFetchPayment(12340, array('controller' => 'blog', 'action' => 'index'));
		$this->assertEqual($ret, false);
	}

/**
 * testCallCheckPayment method
 *
 * @return void
 */
	public function testCallCheckPayment() {
		$this->Mollie->Http->body = $this->testStrings['callcheck1'];
		$payed = false;
		$ret = $this->Mollie->callCheckPayment('0909-1234567890', '12340', $payed);
		$this->assertEqual($ret, array(
			'payed' => true,
			'amount' => '1.75'));
		$this->assertEqual($payed, true);

		$this->Mollie->Http->body = $this->testStrings['callcheck2'];
		$payed = false;
		$ret = $this->Mollie->callCheckPayment('0909-1234567890', '12340', $payed);
		$this->assertEqual($ret, false);
		$this->assertEqual($payed, false);

		$this->Mollie->Http->body = $this->testStrings['callcheck3'];
		$payed = false;
		$ret = $this->Mollie->callCheckPayment('0909-1234567890', '12340', $payed);
		$this->assertEqual($ret, false);
		$this->assertEqual($payed, false);

		$this->Mollie->Http->throw = true;
		$payed = false;
		$ret = $this->Mollie->callCheckPayment('0909-1234567890', '12340', $payed);
		$this->assertEqual($ret, false);
		$this->assertEqual($payed, false);
	}

/**
 * testSmsFetchPayment method
 *
 * @return void
 */
	public function testSmsFetchPayment() {
		$ret = $this->Mollie->smsFetchPayment('12345678', 10000, 'nl');
		$this->assertEqual($ret['amount'], 10000);
		$this->assertEqual(Validation::uuid($ret['paycode']), true);

		$url = parse_url($ret['targetUrl']);

		$this->assertEqual($url['host'], 'www.mollie.nl');
		$this->assertEqual($url['scheme'], 'http');
		$this->assertEqual($url['path'], '/partners/betaal/');

		$query = null;
		parse_str($url['query'], $query);

		$this->assertEqual($query['partnerid'], $this->settings['partnerId']);
		$this->assertEqual($query['id'], '12345678');
		$this->assertEqual($query['land'], 'nl');
		$this->assertEqual($query['parameter'][1], $ret['paycode']);
	}

/**
 * testSmsCheckPayment method
 *
 * @return void
 */
	public function testSmsCheckPayment() {
		$payed = false;
		$this->assertEqual($this->Mollie->smsCheckPayment(String::uuid(), $payed), array('payed' => 'true'));
		$this->assertEqual($payed, 'true');

		$this->assertEqual($this->Mollie->smsCheckPayment('no-valid-uuid', $payed), array('payed' => 'false'));
		$this->assertEqual($payed, 'false');
	}

	public function testAllPartnerNull() {
		$this->Mollie->partnerId = null;

		$shouldBeFalse = false;
		try {
			$this->Mollie->iDealFetchPayment('a', 'b', 'c');
			$shouldBeFalse = true;
		} catch(InternalErrorException $e) {
			$err = $e->getMessage();
		}
		$this->assertEqual($shouldBeFalse, false);
		$this->assertEqual($err, __('No Mollie partner ID configured'));

		$shouldBeFalse = false;
		try {
			$payed = false;
			$this->Mollie->iDealCheckPayment('a', $payed);
			$shouldBeFalse = true;
		} catch(InternalErrorException $e) {
			$err = $e->getMessage();
		}
		$this->assertEqual($payed, false);
		$this->assertEqual($shouldBeFalse, false);
		$this->assertEqual($err, __('No Mollie partner ID configured'));

		$shouldBeFalse = false;
		try {
			$this->Mollie->callFetchPayment(100);
			$shouldBeFalse = true;
		} catch(InternalErrorException $e) {
			$err = $e->getMessage();
		}
		$this->assertEqual($shouldBeFalse, false);
		$this->assertEqual($err, __('No Mollie partner ID configured'));

		$shouldBeFalse = false;
		try {
			$payed = false;
			$this->Mollie->callCheckPayment('1234', '1', $payed);
			$shouldBeFalse = true;
		} catch(InternalErrorException $e) {
			$err = $e->getMessage();
		}
		$this->assertEqual($payed, false);
		$this->assertEqual($shouldBeFalse, false);
		$this->assertEqual($err, __('No Mollie partner ID configured'));

		$shouldBeFalse = false;
		try {
			$this->Mollie->smsFetchPayment('1234');
			$shouldBeFalse = true;
		} catch(InternalErrorException $e) {
			$err = $e->getMessage();
		}
		$this->assertEqual($shouldBeFalse, false);
		$this->assertEqual($err, __('No Mollie partner ID configured'));
	}

}
