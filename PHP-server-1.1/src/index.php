<?php

/**
 * The user-facing portion of the PHP OpenID Server.
 */

session_start();

require_once "config.php";
require_once "common.php";

// Create a page template.
$template =& new Template();

// First, get the request data.
list($method, $request) = Server_getRequest();

// Initialize backends.
$auth =& Server_getAuthBackend();
$storage =& Server_getStorageBackend();

if ($auth === null) {
    $template->addError("Could not connect to authentication server.");
}

if ($storage === null) {
    $template->addError("Could not connect to OpenID storage server.");
}

if (isset($_SERVER['PATH_INFO']) &&
    $_SERVER['PATH_INFO'] == '/serve') {
    require_once "render.php";
    render_serve($method, $request, $template);
    exit(0);
// If it's a request for an identity URL, render that.
} else if (array_key_exists('user', $request) &&
    $request['user']) {
    require_once "render.php";
    render_identityPage($method, $request, $template);
    exit(0);
// If it's a request for a user's XRDS, render that.
} else if (array_key_exists('xrds', $request) &&
    $request['xrds']) {
    require_once "render.php";
    render_XRDS($method, $request, $template);
    exit(0);
}

// If any messages are pending, get them and display them.
$messages = Server_getMessages();

foreach ($messages as $m) {
    $template->addMessage($m);
}

Server_clearMessages();

if ($request === null) {
    // Error; $method not supported.
    $template->addError("Request method $method not supported.");
    $template->display();
} else {
    // Dispatch request to appropriate handler.
    $handler = Server_getHandler($request);

    if ($handler !== null) {
        list($filename, $handler_function) = $handler;
        require_once $filename;
        call_user_func_array($handler_function,
                             array($method, $request, $template));
    } else {
        $template->display('main.tpl');
    }
}

?>
