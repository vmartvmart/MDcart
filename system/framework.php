<?php
// Buffer everything from here on. On some PHP setups a stray PHP
// notice/warning/deprecation gets echoed straight into the response body
// (see the error handler below, and this file's final lines) — often
// during the pre-action/event pipeline further down, i.e. before any
// controller (admin or storefront) has even run. When that happens to a
// JSON/AJAX action (an admin settings-save button, a storefront cart
// call, ...) it silently corrupts the response: the HTTP status is a
// normal 200, but the body is no longer valid JSON, so the calling page's
// AJAX handler fails with no visible symptom at all — it just looks like
// the button did nothing. Buffering here and sorting it out just before
// $response->output() at the bottom of this file fixes that without
// losing the on-page error display for normal (non-JSON) pages — see
// below.
ob_start();

// Autoloader
$autoloader = new \MDcart\System\Engine\Autoloader();
$autoloader->register('MDcart\\' . APPLICATION, DIR_APPLICATION);
$autoloader->register('MDcart\Extension', DIR_EXTENSION);
$autoloader->register('MDcart\System', DIR_SYSTEM);

//require_once(DIR_SYSTEM . 'helper/vendor.php');
//oc_generate_vendor();

// use additional 3rd-party vendor autoloaders
if (defined('DIR_STORAGE') && is_file(DIR_STORAGE . 'vendor/autoload.php')) {
	require_once(DIR_STORAGE . 'vendor/autoload.php');
}

// Registry
$registry = new \MDcart\System\Engine\Registry();
$registry->set('autoloader', $autoloader);

// Config
$config = new \MDcart\System\Engine\Config();
$config->addPath(DIR_CONFIG);
// Load the default config
$config->load('default');
$config->load(strtolower(APPLICATION));
$registry->set('config', $config);

// Set the default application
$config->set('application', APPLICATION);

// Set the default time zone
date_default_timezone_set($config->get('date_timezone'));

// Logging
$log = new \MDcart\System\Library\Log($config->get('error_filename'));
$registry->set('log', $log);

// Error Handler
set_error_handler(function(int $code, string $message, string $file, int $line) use ($log, $config) {
	// PHP 8 compatible check for the @ suppression operator
	if (!(error_reporting() & $code)) {
		// Return false to let the standard PHP internal error handler take over (or do nothing)
		return false;
	}

	switch ($code) {
		case E_NOTICE:
		case E_USER_NOTICE:
			$error = 'Notice';
			break;
		case E_WARNING:
		case E_USER_WARNING:
			$error = 'Warning';
			break;
		case E_ERROR:
		case E_USER_ERROR:
			$error = 'Fatal Error';
			break;
		case E_DEPRECATED:
		case E_USER_DEPRECATED:
			$error = 'Deprecated';
			break;
		default:
			$error = 'Unknown';
			break;
	}

	if ($config->get('error_log')) {
		$log->write('PHP ' . $error . ':  ' . $message . ' in ' . $file . ' on line ' . $line);
	}

	if ($config->get('error_display')) {
		echo '<b>' . $error . '</b>: ' . $message . ' in <b>' . $file . '</b> on line <b>' . $line . '</b>';
	} elseif ($error === 'Fatal Error' || $error === 'Unknown') {
		if (!headers_sent()) {
			header('Location: ' . $config->get('error_page'));
		}
		exit();
	}

	return true;
});

// Exception Handler
set_exception_handler(function(\Throwable $e) use ($log, $config): void {
	$output  = 'Error: ' . $e->getMessage() . "\n";
	$output .= 'File: ' . $e->getFile() . "\n";
	$output .= 'Line: ' . $e->getLine() . "\n\n";

	foreach ($e->getTrace() as $key => $trace) {
		$output .= 'Backtrace: ' . $key . "\n";
		$output .= 'File: ' . ($trace['file'] ?? 'unknown') . "\n";
		$output .= 'Line: ' . ($trace['line'] ?? 'unknown') . "\n";

		if (isset($trace['class'])) {
			$output .= 'Class: ' . $trace['class'] . "\n";
		}

		$output .= 'Function: ' . $trace['function'] . "\n\n";
	}

	if ($config->get('error_log')) {
		$log->write(trim($output));
	}

	if ($config->get('error_display')) {
		echo $output;
	} else {
		header('Location: ' . $config->get('error_page'));
		exit();
	}
});

// Event
$event = new \MDcart\System\Engine\Event($registry);
$registry->set('event', $event);

// Event Register
if ($config->has('action_event')) {
	foreach ($config->get('action_event') as $key => $value) {
		foreach ($value as $priority => $action) {
			$event->register($key, new \MDcart\System\Engine\Action($action), $priority);
		}
	}
}

// Factory
$registry->set('factory', new \MDcart\System\Engine\Factory($registry));

// Loader
$loader = new \MDcart\System\Engine\Loader($registry);
$registry->set('load', $loader);

// Request
$request = new \MDcart\System\Library\Request();
$registry->set('request', $request);

// Compatibility
if (isset($request->get['route'])) {
	$request->get['route'] = str_replace('|', '.', $request->get['route']);
	$request->get['route'] = str_replace('%7C', '|', (string)$request->get['route']);
}

// Response
$response = new \MDcart\System\Library\Response();
$registry->set('response', $response);

foreach ($config->get('response_header') as $header) {
	$response->addHeader($header);
}

$response->addHeader('Access-Control-Allow-Origin: *');
$response->addHeader('Access-Control-Allow-Credentials: true');
$response->addHeader('Access-Control-Max-Age: 1000');
$response->addHeader('Access-Control-Allow-Headers: X-Requested-With, Content-Type, Origin, Cache-Control, Pragma, Authorization, Accept, Accept-Encoding');
$response->addHeader('Access-Control-Allow-Methods: PUT, POST, GET, OPTIONS, DELETE');
header('Expires: Thu, 19 Nov 1981 08:52:00 GMT');
header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
header('Pragma: no-cache');
$response->setCompression((int)$config->get('response_compression'));

// Database
if ($config->get('db_autostart')) {
	$db = new \MDcart\System\Library\DB($config->get('db_engine'), $config->get('db_hostname'), $config->get('db_username'), $config->get('db_password'), $config->get('db_database'), $config->get('db_port'), $config->get('db_ssl_key'), $config->get('db_ssl_cert'), $config->get('db_ssl_ca'));
	$registry->set('db', $db);
}

// Session
if ($config->get('session_autostart')) {
	$session = new \MDcart\System\Library\Session($config->get('session_engine'), $registry);
	$registry->set('session', $session);

	if (isset($request->cookie[$config->get('session_name')])) {
		$session_id = $request->cookie[$config->get('session_name')];
	} else {
		$session_id = '';
	}

	$session->start($session_id);

	// Require higher security for session cookies
	$option = [
		'expires'  => 0,
		'path'     => $config->get('session_path'),
		'domain'   => $config->get('session_domain'),
		'secure'   => $request->server['HTTPS'],
		'httponly' => true,
		'samesite' => $config->get('session_samesite')
	];

	setcookie($config->get('session_name'), $session->getId(), $option);
}

// Cache
$registry->set('cache', new \MDcart\System\Library\Cache($config->get('cache_engine'), $config->get('cache_expire')));

// Template
$template = new \MDcart\System\Library\Template($config->get('template_engine'));
$template->addPath(DIR_TEMPLATE);
$registry->set('template', $template);

// Language
$language = new \MDcart\System\Library\Language($config->get('language_code'));
$language->addPath(DIR_LANGUAGE);
$language->load('default');
$registry->set('language', $language);

// Url
$registry->set('url', new \MDcart\System\Library\Url($config->get('site_url')));

// Document
$registry->set('document', new \MDcart\System\Library\Document());

$action = '';
$args = [];

// Action error object to execute if any other actions cannot be executed.
$error = new \MDcart\System\Engine\Action($config->get('action_error'));

// Pre Actions
foreach ($config->get('action_pre_action') as $pre_action) {
	$pre_action = new \MDcart\System\Engine\Action($pre_action);

	$result = $pre_action->execute($registry, $args);

	if ($result instanceof \MDcart\System\Engine\Action) {
		$action = $result;

		break;
	}

	// If action cannot be executed, we return an action error object.
	if ($result instanceof \Exception) {
		$action = $error;

		// In case there is an error we only want to execute once.
		$error = '';

		break;
	}
}

// Route
if (isset($request->get['route'])) {
	$route = (string)$request->get['route'];
} else {
	$route = (string)$config->get('action_default');
}

// To block calls to controller methods we want to keep from being accessed directly
if (str_contains($route, '._')) {
	$action = new \MDcart\System\Engine\Action($config->get('action_error'));
}

if ($action) {
	$route = $action->getId();
}

// Keep the original trigger
$trigger = $route;

$args = [];

// Trigger the pre events
$event->trigger('controller/' . $trigger . '/before', [&$route, &$args]);

// Action to execute
if (!$action) {
	$action = new \MDcart\System\Engine\Action($route);
}

// Dispatch
while ($action) {
	// Execute action
	$output = $action->execute($registry, $args);

	// Make action a non-object so it's not infinitely looping
	$action = '';

	// Action object returned then we keep the loop going
	if ($output instanceof \MDcart\System\Engine\Action) {
		$action = $output;
	}

	// If action cannot be executed, we return the action error object.
	if ($output instanceof \Exception) {
		$action = $error;

		// In case there is an error we don't want to infinitely keep calling the action error object.
		$error = '';
	}
}

// Trigger the post events
$event->trigger('controller/' . $trigger . '/after', [&$route, &$args, &$output]);

// Sort out anything stray that got echoed directly during the request
// (see the ob_start() at the top of this file) instead of letting it
// leak in front of the real response and corrupt it.
$stray_output = ob_get_clean();

if ($stray_output !== false && trim($stray_output) !== '') {
	if ($config->get('error_log')) {
		$log->write('Stray output before response (route: ' . $trigger . '): ' . trim($stray_output));
	}

	// Only re-show it for a normal HTML page when on-page error display is
	// enabled, matching the previous behaviour there — never for a
	// JSON/AJAX response, where this content would corrupt it exactly as
	// described above.
	$is_json_response = false;

	foreach ($response->getHeaders() as $header) {
		if (stripos($header, 'Content-Type:') === 0 && stripos($header, 'json') !== false) {
			$is_json_response = true;

			break;
		}
	}

	if (!$is_json_response && $config->get('error_display')) {
		echo $stray_output;
	}
}

// Output
$response->output();
