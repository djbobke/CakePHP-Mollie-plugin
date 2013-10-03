<?php
App::uses('ComponentCollection', 'Controller');
App::uses('Component', 'Controller');
App::uses('MollieComponent', 'Mollie.Controller/Component');

/**
 * MollieComponent Test Case
 *
 */
class MollieComponentTest extends CakeTestCase {

/**
 * setUp method
 *
 * @return void
 */
	public function setUp() {
		parent::setUp();
		$Collection = new ComponentCollection();
		$this->Mollie = new MollieComponent($Collection);
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

/**
 * testIDealBanklist method
 *
 * @return void
 */
	public function testIDealBanklist() {
	}

/**
 * testIDealFetchPayment method
 *
 * @return void
 */
	public function testIDealFetchPayment() {
	}

/**
 * testIDealCheckPayment method
 *
 * @return void
 */
	public function testIDealCheckPayment() {
	}

/**
 * testCallFetchPayment method
 *
 * @return void
 */
	public function testCallFetchPayment() {
	}

/**
 * testCallCheckPayment method
 *
 * @return void
 */
	public function testCallCheckPayment() {
	}

/**
 * testSmsFetchPayment method
 *
 * @return void
 */
	public function testSmsFetchPayment() {
	}

/**
 * testSmsCheckPayment method
 *
 * @return void
 */
	public function testSmsCheckPayment() {
	}

}
