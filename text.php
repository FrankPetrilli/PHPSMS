<?php
	$DISABLED = false;
	$ALLOW_ANONYMOUS = false;
	$DEFAULT_THRESHOLD = 3;
	$FROM_ADDRESS = "txt@txt.petril.li";

	$date = date('d.m.y'); // Define our date as today in d.m.y notation.

	// Response classes for JSON responses.
	require_once('Response.php');

	if ($DISABLED) {
		print(new Failure(false, "This API is currently disabled."));
		die();
	}

	if (isset($_POST['number'])) {
		$number = $_POST['number'];
	} else {
		print(new Failure(false, "Number needs to be defined."));
		die();
	}

	if (isset($_POST['message'])) {
		$message = $_POST['message'];
	} else {
		print(new Failure(false, "Message needs to be defined."));
		die();
	}

	$key_is_ip = false;
	if (isset($_POST['key'])) {
		$key = $_POST['key'];
	} else {
		if ($ALLOW_ANONYMOUS) {
			$key_is_ip = true;
			// Cloudflare magic.
			if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
				$key = $_SERVER['HTTP_CF_CONNECTING_IP'];
			} else {
				$key = $_SERVER['REMOTE_ADDR'];
			}
		} else {
			print(new Failure(false, "Please create/define an API key first. IP-based messages are disabled."));
			die();
		}
	}

	if (isset($_POST['country'])) {
		$country = $_POST['country'];
	} else {
		print(new Failure(false, "Define a country. (Probably US)"));
		die();
	}

	// Figure out DB info. We'll use environment variables if they're present. Otherwise, we'll use the file.
	// TODO: Make this define all parts.
	require_once('db.php'); // Get database parameters.
	// Overwrite with environment variables.
	/*if (isset($_ENV['DB_PORT_3306_TCP_ADDR'])) {
		define ('DB_HOST', '$_ENV['DB_PORT_3306_TCP_ADDR']');
	}*/

	$conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

	if ($conn->connect_error) {
		print(new Failure(false, "Connect to DB failed. (My fault, not yours)"));
		die();
	}

	$number = formatNumber($number);

	// We have a clean number and a good DB connection.

	$result = getThreshold($conn, $key);
	if ($result->num_rows == 0) {
		if ($key_is_ip) {
			$stmt = $conn->prepare("INSERT INTO textapi_users (api_key, threshold) VALUES (?, ?)");
			$stmt->bind_param("ss", $key, $DEFAULT_THRESHOLD);
			$stmt->execute();
		} else {
			print(new Failure(false, "Your key doesn't exist in our records, and IP-based users are disabled at this time."));
			die();
		}
	}

	$result = getThreshold($conn, $key);

	$user_threshold = $result->fetch_object()->threshold; // We have the user's threshold as defined in the DB.

	$stmt = $conn->prepare("SELECT * FROM textapi_requests WHERE api_key=? AND timestamp=?");
	$stmt->bind_param("ss", $key, $date); // Bind the user's key and day.
	$stmt->execute();
	$result = $stmt->get_result();

	// A user threshold of -1 indicates unlimited requests. So, only die if we have a threshold.
	if ($user_threshold != -1 && $result->num_rows >= $user_threshold) {
		print(new Failure(false, "You've exceeded your threshold for today."));
		die();
	}

	// The user hasn't exceeded any thresholds.

	//$providers = array("tmomail.net", "txt.att.net"); // Fallback. We can just hardcode this as an array if we need to.
	// But as long as we have a DB, let's use it.
	$stmt = $conn->prepare("SELECT endpoint FROM textapi_providers WHERE country=?");
	$stmt->bind_param("s", $country);
	$stmt->execute();
	$result = $stmt->get_result();

	// If we don't find any providers.
	if ($result->num_rows == 0) {
		print(new Failure(false, "We don't have any providers for that country code. Please check your country code."));
		die();
	}

	$providers = array();
	// Add the results we get from the DB to an array.
	while ($row = $result->fetch_assoc()) {
		$providers[] = $row['endpoint'];
	}

	// Prepare a from header based upon the from address and PHP version.
	$headers = 'From: ' . $FROM_ADDRESS . "\r\n" . 'Reply-To: ' . $FROM_ADDRESS . "\r\n" .  'X-Mailer: PHP/' . phpversion();
	foreach ($providers as &$provider) {
		// Add the user's number and the provider together to make an email.
		$email = $number . "@" . $provider;
		// Send it off.
		// TODO: Turn this into an object. Make an object that we can pass off to a function, which decides what route to send it through.
		// We can JSON serialize a standard PHP object and throw it at a server/client somewhere for actual SMS.
		mail($email, "API", $message, $headers);

	}

	// Call this a success. Send the user a JSON object that informs success.

	print(new Success());

	// Add the record into the DB so we can account for their use.
	$stmt = $conn->prepare("INSERT INTO textapi_requests (api_key, number , message, timestamp) VALUES (?, ?, ?, ?)");
	$stmt->bind_param("ssss", $key, $number, $message, $date);
	$stmt->execute();

	// Functions: -----------------------------------------------------------------------

	// Given a phone number, strips special characters and leading country codes.
	// Either returns a 10-digit standard phone number, or dies if it didn't work out.
	function formatNumber($number) {
		$number = preg_replace('/[^0-9]/', '', $number); // Only allow numbers.
		if (strlen($number) == 11 && substr($number, 0, 1) == "1") {
			// Remove the leading one if it's in there.
			$number = substr($number, 1); 
		}
		// Check if we have a 10 digit phone number.
		if (strlen($number) != 10) {
			print(new Failure(false, "Number is not of the right length"));
			die();
		}
		// Our number is probably something valid.
		return $number;
	}

	// Returns a MySQLi result object of the user's threshold for a given api key.
	function getThreshold($conn, $key) {
		$stmt = $conn->prepare("SELECT threshold FROM textapi_users WHERE api_key=?");
		$stmt->bind_param("s", $key);
		$stmt->execute();
		$result = $stmt->get_result();
		return $result;
	}
?>
