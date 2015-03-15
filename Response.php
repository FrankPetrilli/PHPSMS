<?php
class Response {
	public $success;

	function __construct($success) {
		$this->success = $success;
	}

	public function __toString() {
		return json_encode($this, 128);
	}
}

class Failure extends Response {
	public $error;

	function __construct($success, $error) {
		$this->success = $success;
		$this->error = $error;
	}
}

class Success extends Response {
	// Currently an exact replica.
	function __construct() {
		$this->success = true;
	}
}
?>
