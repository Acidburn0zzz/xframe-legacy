<?php

/**
 * @author Linus Norton <linusnorton@gmail.com>
 *
 * Welcome to xFrame. This file dispatches the request to be handled by the framework.
 */

require(dirname(__FILE__).'/../init.php');

// Preserve the original php://input
define('XFRAME_ORIGINAL_PHP_INPUT', file_get_contents('php://input'));

//pass the request URI, parameters and path of this file to the request
parse_str(XFRAME_ORIGINAL_PHP_INPUT, $_PUT);
$_REQUEST = array_merge($_REQUEST, $_PUT);

$request = new Request($_SERVER['REQUEST_URI'], $_REQUEST, $_SERVER['PHP_SELF'], $_SERVER['REQUEST_METHOD']);
//pass request to the dispatcher which maps it to a resource and controller 
echo Dispatcher::dispatch($request);
