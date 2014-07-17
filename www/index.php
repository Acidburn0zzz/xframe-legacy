<?php

/**
 * @author Linus Norton <linusnorton@gmail.com>
 *
 * Welcome to xFrame. This file dispatches the request to be handled by the framework.
 */

require(dirname(__FILE__).'/../init.php');

parse_str(file_get_contents("php://input"), $_PUT);
$_REQUEST = array_merge($_REQUEST, $_PUT);

//pass the request URI, parameters and path of this file to the request
$request = new Request($_SERVER['REQUEST_URI'], $_REQUEST, $_SERVER['PHP_SELF'], $_SERVER['REQUEST_METHOD']);
//pass request to the dispatcher which maps it to a resource and controller 
echo Dispatcher::dispatch($request);
