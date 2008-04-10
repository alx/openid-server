<?php

/**
 * This file contains handler functions which handle responses to
 * particular requests.
 */

require_once "common.php";
require_once "constants.php";
require_once "captcha.php";

function render_login($method, &$request, &$template)
{
    global $auth, $storage;

    if (Server_getAccount()) {
        Server_redirect(getServerURL());
    }

    if ($method == 'POST') {
        // Process login.
        $u = $request['username'];
        $p = $request['passwd'];

        if ($u && $p) {

            if ($u == ADMIN_USERNAME &&
                md5($p) == ADMIN_PASSWORD_MD5) {
                // Log in as admin.
                Server_setAccount($u, true);
                Server_redirect(getServerURL());
            } else if (($u != ADMIN_USERNAME) &&
                       $auth->authenticate($u, $p)) {
                Server_setAccount($u);

                $url = getServerURL();

                if (array_key_exists('next_action', $request)) {
                    $url .= "?action=".$request['next_action'];
                }

                Server_redirect($url);
            } else {
                $template->addError("Invalid account information.");
            }
        }
    }

    if (array_key_exists('next_action', $request)) {
        $template->assign('next_action', $request['next_action']);
    }

    list($info, $sreg) = getRequestInfo();

    if ($info) {
        // Reverse lookup from URL to account name.
        $username = $storage->getAccountForUrl($info->identity);

        if ($username !== null) {
            $template->assign('required_user', $username);
            $template->assign('identity_url', $info->identity);
        } else {
            // Return an OpenID error because this server does not
            // know about that URL.
            Server_clearAccount();
            setRequestInfo();
            $template->addError("You've tried to authenticate using a URL this ".
                                "server does not manage (<code>".$info->identity."</code>).".
                                " If you are using your own identity page, there may be a typo ".
                                "in the URL.");
        }
    }

    $template->assign('onload_js', "document.forms.loginform.username.focus();");
    $template->display('login.tpl');
}

function render_logout($method, &$request, &$template)
{
    Server_clearAccount();
    Server_redirect(getServerURL());
}

function render_sites($method, &$request, &$template)
{
    global $storage;

    Server_needAuth($request);

    $account = Server_getAccount();
    $sites = $storage->getSites($account);

    if ($method == 'POST') {

        if ($request['site']) {
            if (isset($request['trust_selected'])) {
                foreach ($request['site'] as $site => $on) {
                    $storage->trustLog($account, $site, true);
                }
            } else if (isset($request['untrust_selected'])) {
                foreach ($request['site'] as $site => $on) {
                    $storage->trustLog($account, $site, false);
                }
            } else if (isset($request['remove'])) {
                foreach ($request['site'] as $site => $on) {
                    $storage->removeTrustLog($account, $site);
                }
            }

            $template->addMessage('Settings saved.');
        }
    }

    $sites = $storage->getSites($account);

    $max_trustroot_length = 50;

    for ($i = 0; $i < count($sites); $i++) {
        $sites[$i]['trust_root_full'] = $sites[$i]['trust_root'];
        if (strlen($sites[$i]['trust_root']) > $max_trustroot_length) {
            $sites[$i]['trust_root'] = substr($sites[$i]['trust_root'], 0, $max_trustroot_length) . "...";
        }

        $sites[$i]['trust_root'] = preg_replace("/\*/", "<span class='anything'>anything</span>",
                                                $sites[$i]['trust_root']);
    }

    $template->assign('sites', $sites);
    $template->display('sites.tpl');
}

function render_account($method, &$request, &$template)
{
    global $language_codes,
        $country_codes,
        $timezone_strings,
        $sreg_fields,
        $storage;

    Server_needAuth($request);
    $account = Server_getAccount();

    if ($method == 'POST') {
        $profile_form = $request['profile'];

        // Adjust DOB value.
        $dob = $profile_form['dob'];

        if (!$dob['Date_Year']) {
            $dob['Date_Year'] = '0000';
        }

        if (!$dob['Date_Month']) {
            $dob['Date_Month'] = '00';
        }

        if (!$dob['Date_Day']) {
            $dob['Date_Day'] = '00';
        }

        $profile_form['dob'] = sprintf("%d-%d-%d",
                                       $dob['Date_Year'],
                                       $dob['Date_Month'],
                                       $dob['Date_Day']);

        $profile = array();

        foreach ($sreg_fields as $field) {
            $profile[$field] = $profile_form[$field];
        }

        // Save profile.
        $storage->savePersona($account, $profile);

        // Add a message to the session so it'll get displayed after
        // the redirect.
        Server_addMessage("Changes saved.");

        // Redirect to account screen to make reloading easy.
        Server_redirect(getServerURL(), 'account');
    }

    $profile = $storage->getPersona($account);

    if ($profile['dob'] === null) {
        $profile['dob'] = '0000-00-00';
    }

    // Stuff profile data and choices into template.
    $template->assign('profile', $profile);
    $template->assign('timezones', $timezone_strings);
    $template->assign('countries', $country_codes);
    $template->assign('languages', $language_codes);

    $template->display('account.tpl');
}

function render_register($method, &$request, &$template)
{
    global $auth, $storage;

    if (!ALLOW_PUBLIC_REGISTRATION) {
        Server_redirect(getServerURL());
    }

    if ($method == 'POST') {
        $hash = null;
        if (array_key_exists('hash', $_SESSION)) {
            $hash = $_SESSION['hash'];
        }

        $success = true;

        if ($hash !== md5($request['captcha_text'])) {
            $template->addError('Security code does not match image.  Please try again.');
            $success = false;
        }

        $errors = Server_accountCheck($request['username'],
                                      $request['pass1'],
                                      $request['pass2']);

        if ($errors) {
            foreach ($errors as $e) {
                $template->addError($e);
            }
        } else {
            // Good.
            if (($request['username'] != ADMIN_USERNAME) &&
                $auth->newAccount($request['username'], $request['pass1'], $request)) {

                // Add an identity URL to storage.
                $storage->addIdentifier($request['username'],
                                        Server_getAccountIdentifier($request['username']));

                Server_setAccount($request['username']);
                Server_addMessage("Registration successful; welcome, ".$request['username']."!");
                Server_redirect(getServerURL());
            } else {
                $template->addError('Sorry; that username is already taken!');
            }
        }

        $template->assign('username', $request['username']);
    }

    $template->display('register.tpl');
}

function render_captcha($method, &$request, &$template)
{
    // Render a captcha image and store the hash.  See register.tpl.
    $hash = generateCaptcha(PHP_SERVER_PATH . "/src/fonts/FreeSans.ttf", 6);

    // Put the captcha hash into the session so it can be checked.
    $_SESSION['hash'] = $hash;
}

function render_identityPage($method, &$request, &$template)
{
    $serve_xrds_now = false;

    // If an Accept header is sent, display the XRDS immediately;
    // otherwise, display the identity page with an XRDS location
    // header.
    $headers = apache_request_headers();
    foreach ($headers as $header => $value) {
        if (($header == 'Accept') &&
            preg_match("/application\/xrds\+xml/", $value)) {
            $serve_xrds_now = true;
        }
    }

    if ($serve_xrds_now) {
        $request['xrds'] = $request['user'];
        render_XRDS($method, $request, $template);
    } else {
        header("X-XRDS-Location: ".getServerURL()."?xrds=".$request['user']);
        $template->assign('openid_url', Server_getAccountIdentifier($request['user']));
        $template->assign('user', $request['user']);
        $template->display('idpage.tpl', true);
    }
}

function render_trust($method, &$request, &$template)
{
    global $storage;

    Server_needAuth($request);

    $account = Server_getAccount();
    list($request_info, $sreg) = getRequestInfo();

    if (!$request_info) {
        Server_redirect(getServerURL());
    }

    $urls = $storage->getUrlsForAccount($account);

    if (!in_array($request_info->identity, $urls)){
        Server_clearAccount();
        setRequestInfo($request_info, $sreg);
        Server_needAuth($request);
    }

    if ($method == 'POST') {

        $trusted = false;

        if (isset($request['trust_forever'])) {
            $storage->trustLog(Server_getAccount(), $request_info->trust_root, true);
            $trusted = true;
        } else if (isset($request['trust_once'])) {
            $storage->trustLog(Server_getAccount(), $request_info->trust_root, false);
            $trusted = true;
        } else {
            $storage->trustLog(Server_getAccount(), $request_info->trust_root, false);
        }

        if ($trusted) {
            $allowed_fields = array();

            if (array_key_exists('sreg', $request)) {
                $allowed_fields = array_keys($request['sreg']);
            }

            $response = $request_info->answer(true);
            addSregData($account, $response, $allowed_fields);
        } else {
            $response = $request_info->answer(false);
        }

        setRequestInfo();
        Server_handleResponse($response);
    }

    if ($sreg) {
        // Get the profile data and mark it up so it's easy to tell
        // what's required and what's optional.
        $profile = $storage->getPersona($account);

        list($optional, $required, $policy_url) = $sreg;

        $sreg_labels = array('nickname' => 'Nickname',
                             'fullname' => 'Full name',
                             'email' => 'E-mail address',
                             'dob' => 'Birth date',
                             'postcode' => 'Postal code',
                             'gender' => 'Gender',
                             'country' => 'Country',
                             'timezone' => 'Time zone',
                             'language' => 'Language');

        $profile['country'] = getCountryName($profile['country']);
        $profile['language'] = getLanguage($profile['language']);

        $new_profile = array();
        foreach ($profile as $k => $v) {
            if (in_array($k, $optional) ||
                in_array($k, $required)) {
                $new_profile[] = array('name' => $sreg_labels[$k],
                                       'real_name' => $k,
                                       'value' => $v,
                                       'optional' => in_array($k, $optional),
                                       'required' => in_array($k, $required));
            }
        }

        $template->assign('profile', $new_profile);
        $template->assign('policy_url', $policy_url);
    }

    $template->assign('trust_root', $request_info->trust_root);
    $template->assign('identity', $request_info->identity);
    $template->display('trust.tpl');
}

function render_serve($method, &$request, &$template)
{
    global $storage;

    $server =& getServer();

    $http_request = $request;
    $request = Auth_OpenID::fixArgs($request);
    $request = $server->decodeRequest($request);

    if (!$request) {
        Server_redirect(getServerURL());
    }

    if (is_a($request, 'Auth_OpenID_ServerError')) {
        Server_handleResponse($request);
    }

    setRequestInfo($request, Server_requestSregData($http_request));

    if (in_array($request->mode,
                 array('checkid_immediate', 'checkid_setup'))) {

        $urls = array();
        $account = Server_getAccount();

        if ($account) {
            $urls = $storage->getUrlsForAccount($account);
        }

        if ($request->immediate && !$account) {
            $response =& $request->answer(false, getServerURL());
        } else if ($account &&
                   $storage->isTrusted($account, $request->trust_root) &&
                   in_array($request->identity, $urls)) {
             $response =& $request->answer(true);
             addSregData($account, $response);
        } else if ($account != $storage->getAccountForUrl($request->identity)) {
            Server_clearAccount();
            setRequestInfo($request, Server_requestSregData($http_request));
            $http_request['action'] = 'trust';
            Server_needAuth($http_request);
        } else {
            if ($storage->isTrusted($account, $request->trust_root)) {
                $response =& $request->answer(true);
                addSregData($account, $response);
            } else {
                Server_redirect(getServerURL(), 'trust');
            }
        }
    } else {
        $response =& $server->handleRequest($request);
    }

    setRequestInfo();

    Server_handleResponse($response);
}

function render_XRDS($method, &$request, &$template)
{
    $username = $request['xrds'];
    $template->assign('account', $username);
    $template->assign('openid_url', Server_getAccountIdentifier($username));

    header("Content-type: application/xrds+xml");
    $template->display('xrds.tpl', true);
}

function render_admin($method, &$request, &$template)
{
    global $auth, $storage;

    Server_needAuth($request);
    Server_needAdmin();

    if (array_key_exists('username', $request)) {

        $username = $request['username'];
        $pass1 = $request['pass1'];
        $pass2 = $request['pass2'];

        $success = true;

        $errors = Server_accountCheck($username, $pass1, $pass2);

        if ($errors) {
            foreach ($errors as $e) {
                $template->addError($e);
            }
        } else {
            // Good.
            if (($username != ADMIN_USERNAME) &&
                $auth->newAccount($username, $pass1, $request)) {
                // Add an identity URL to storage.
                $storage->addIdentifier($username,
                                        Server_getAccountIdentifier($username));
                Server_addMessage('Account created.');
                Server_redirect(getServerURL(), 'admin');
            } else {
                $template->addError("Sorry; the username '$username' is already taken!");
            }
        }
    } else if (array_key_exists('remove', $request)) {

        foreach ($request['account'] as $account => $on) {
            $auth->removeAccount($account);
            $storage->removeAccount($account);
        }

        Server_addMessage('Account(s) removed.');
        Server_redirect(getServerURL()."?search=".$request['search'],
                        'admin');
    }

    if (array_key_exists('search', $request) &&
        ($request['search'] || array_key_exists('showall', $request))) {
        // Search for accounts.

        if (array_key_exists('showall', $request)) {
            $results = $auth->search();
            $template->assign('showall', 1);
        } else {
            $results = $auth->search($request['search']);
        }

        $template->assign('search', $request['search']);
        $template->assign('search_results', $results);
    }

    $template->display('admin.tpl');
}

?>