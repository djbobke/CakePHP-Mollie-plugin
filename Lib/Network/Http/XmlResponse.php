<?php

App::uses('Xml', 'Utility');
App::uses('HttpSocketResponse', 'Network/Http');

class XmlResponse extends HttpSocketResponse {

	public function parseResponse($message) {
		parent::parseResponse($message);

		$this->body = Xml::build($this->body);
	}

	public function body() {
		return $this->body;
	}
}
