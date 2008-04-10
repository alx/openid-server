<?php

require_once "config.php";
require_once "handlers.php";
require_once "auth.php";
require_once "storage.php";

require_once "Auth/OpenID/Server.php";
require_once "Auth/OpenID/MySQLStore.php";

define('PHP_SERVER_PATH', dirname(dirname(__FILE__))."/");

/**
 * Require the Smarty template core.
 */
require_once(SMARTY_DIR . '/Smarty.class.php');

global $__storage_backend, $__auth_backend, $__openid_store;

function &Server_getAuthBackend()
{
    global $__auth_backend, $auth_parameters;

    if (!$__auth_backend) {
        // Try to instantiate auth backend class from settings.
        $cls = 'AuthBackend_' . AUTH_BACKEND;
        $__auth_backend = new $cls();
        if (!$__auth_backend->connect($auth_parameters)) {
            return null;
        }
    }
    return $__auth_backend;
}

function &Server_getStorageBackend()
{
    global $__storage_backend, $storage_parameters;

    if (!$__storage_backend) {
        // Try to instantiate storage backend class from settings.
        $cls = 'Storage_' . STORAGE_BACKEND;
        $__storage_backend = new $cls();
        if (!$__storage_backend->connect($storage_parameters)) {
            return null;
        }
    }
    return $__storage_backend;
}

function &Server_getOpenIDStore()
{
    global $__openid_store, $storage_parameters;

    if (!$__openid_store) {
        // Try to instantiate storage backend class from settings.

        $parameters = $storage_parameters;
        $parameters['phptype'] = 'mysql';
        $db =& DB::connect($parameters);

        if (!PEAR::isError($db)) {
            $__openid_store =& new Auth_OpenID_MySQLStore($db);
            $__openid_store->createTables();
        } else {
            return null;
        }
    }
    return $__openid_store;
}

function Server_setAccount($account_name, $admin = false)
{
    $_SESSION['account'] = $account_name;
    if ($admin) {
        $_SESSION['admin'] = 1;
    }
}

function Server_clearAccount()
{
    unset($_SESSION['account']);
    unset($_SESSION['admin']);
    unset($_SESSION['request']);
    unset($_SESSION['sreg_request']);
}

function Server_getAccount()
{
    if (array_key_exists('account', $_SESSION)) {
        return $_SESSION['account'];
    }

    return null;
}

/**
 * Get the URL of the current script
 */
function getServerURL()
{
    $path = dirname($_SERVER['SCRIPT_NAME']);
    if ($path[strlen($path) - 1] != '/') {
        $path .= '/';
    }

    $host = $_SERVER['HTTP_HOST'];
    $port = $_SERVER['SERVER_PORT'];
    $s = isset($_SERVER['HTTPS']) ? 's' : '';
    if (($s && $port == "443") || (!$s && $port == "80")) {
        $p = '';
    } else {
        $p = ':' . $port;
    }
    
    return "http$s://$host$p$path";
}

function Server_getAccountIdentifier($account)
{
    return sprintf("%s?user=%s", getServerURL(), $account);
}

function Server_addMessage($str)
{
    if (!array_key_exists('messages', $_SESSION)) {
        $_SESSION['messages'] = array();
    }

    $_SESSION['messages'][] = $str;
}

function Server_getMessages()
{
    if (array_key_exists('messages', $_SESSION)) {
        return $_SESSION['messages'];
    } else {
        return array();
    }
}

function Server_clearMessages()
{
    unset($_SESSION['messages']);
}

function Server_getHandler(&$request)
{
    global $request_handlers;

    if (array_key_exists('action', $request) &&
        (array_key_exists($request['action'], $request_handlers))) {
        // The handler is array($filename, $function_name).
        return $request_handlers[$request['action']];
    }

    return null;
}

function Server_accountCheck($username, $pass1, $pass2)
{
    $errors = array();

    if ($pass1 != $pass2) {
        $errors[] = "Passwords must match.";
    } else if (strlen($pass1) < MIN_PASSWORD_LENGTH) {
        $errors[] = 'Password must be at least '.
            MIN_PASSWORD_LENGTH.' characters long.';
    }

    if (strlen($username) < MIN_USERNAME_LENGTH) {
        $errors[] = 'Username must be at least '.
            MIN_USERNAME_LENGTH.' characters long.';
    }

    return $errors;
}

function Server_redirect($url, $action = null)
{
    if ($action) {
        if (strpos($url, "?") === false) {
            $url .= "?action=".$action;
        } else {
            $url .= "&action=".$action;
        }
    }

    header("Location: ".$url);
    exit(0);
}

function Server_getRequest()
{
    $method = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
    case 'GET':
        return array($method, $_GET);
        break;
    case 'POST':
        return array($method, $_POST);
        break;
    }

    return array($method, null);
}

function &getServer()
{
    static $server = null;
    if (!isset($server)) {
        $server =& new Auth_OpenID_Server(Server_getOpenIDStore());
    }
    return $server;
}

function setRequestInfo($info=null, $sreg=null)
{
    if (!isset($info)) {
        unset($_SESSION['request']);
    } else {
        $_SESSION['request'] = serialize($info);
        $_SESSION['sreg_request'] = serialize($sreg);
    }
}

function getRequestInfo()
{
    if (isset($_SESSION['request'])) {
        return array(unserialize($_SESSION['request']),
                     unserialize($_SESSION['sreg_request']));
    } else {
        return false;
    }
}

function Server_handleResponse($response)
{
    $server =& getServer();
    $webresponse =& $server->encodeResponse($response);

    foreach ($webresponse->headers as $k => $v) {
        header("$k: $v");
    }

    header('Connection: close');
    print $webresponse->body;
    exit(0);
}

function Server_requestSregData($request)
{
    $optional = array();
    $required = array();
    $policy_url = null;

    $request = Auth_OpenID::fixArgs($request);

    if (array_key_exists('openid.sreg.required', $request)) {
        $required = explode(",", $request['openid.sreg.required']);
    }

    if (array_key_exists('openid.sreg.optional', $request)) {
        $optional = explode(",", $request['openid.sreg.optional']);
    }

    if (array_key_exists('openid.sreg.policy_url', $request)) {
        $policy_url = $request['openid.sreg.policy_url'];
    }

    return array($optional, $required, $policy_url);
}

function addSregData($account, &$response, $allowed_fields = null)
{
    $storage =& Server_getStorageBackend();

    $profile = $storage->getPersona($account);

    list($r, $sreg) = getRequestInfo();
    list($optional, $required, $policy_url) = $sreg;

    if ($allowed_fields === null) {
        $allowed_fields = array_merge($optional, $required);
    }

    $data = array();
    foreach ($optional as $field) {
        if (array_key_exists($field, $profile) &&
            in_array($field, $allowed_fields)) {
            $data[$field] = $profile[$field];
        }
    }
    foreach ($required as $field) {
        if (array_key_exists($field, $profile) &&
            in_array($field, $allowed_fields)) {
            $data[$field] = $profile[$field];
        }
    }

    $response->addFields('sreg', $data);    
}

/**
 * The Smarty template class used by this application.
 */
class Template extends Smarty {
    function Template()
    {
        $this->template_dir = PHP_SERVER_PATH . 'templates';
        $this->compile_dir = PHP_SERVER_PATH . 'templates_c';
        $this->errors = array();
        $this->messages = array();
    }

    function addError($str)
    {
        $this->errors[] = $str;
    }

    function addMessage($str)
    {
        $this->messages[] = $str;
    }

    function display($filename = null, $template_override = false)
    {
        $this->assign('errors', $this->errors);
        $this->assign('messages', $this->messages);
        $this->assign('SERVER_URL', getServerURL());
        $this->assign('SITE_TITLE', SITE_TITLE);
        $this->assign('ADMIN', isset($_SESSION['admin']));
        $this->assign('SITE_ADMIN_EMAIL', SITE_ADMIN_EMAIL);
        $this->assign('ALLOW_PUBLIC_REGISTRATION', ALLOW_PUBLIC_REGISTRATION);
        $this->assign('account', Server_getAccount());
        $this->assign('account_openid_url', Server_getAccountIdentifier(Server_getAccount()));

        if ($template_override && $filename) {
            return parent::display($filename);
        } else if (!$template_override) {
            if ($filename) {
                $this->assign('body', $this->fetch($filename));
            }
            return parent::display('index.tpl');
        }
    }
}

function Server_needAuth(&$request)
{
    if (!Server_getAccount()) {

        $destination = getServerURL() . "?action=login";

        if (array_key_exists('action', $request)) {
            $destination .= "&next_action=".$request['action'];
        }

        header("Location: ".$destination);
        exit(0);
    }
}

function Server_needAdmin()
{
    if (!isset($_SESSION['admin'])) {
        Server_redirect(getServerURL());
    }
}

function getLanguage($code)
{
    global $language_codes;

    foreach ($language_codes as $pair) {
        if ($pair[0] == $code) {
            return $pair[1];
        }
    }

    return null;
}

function getCountryName($code)
{
    global $country_codes;

    foreach ($country_codes as $pair) {
        if ($pair[0] == $code) {
            return $pair[1];
        }
    }

    return null;
}

// These values were taken from
// http://www.iso.org/iso/en/prods-services/iso3166ma/02iso-3166-code-lists/list-en1-semic.txt
global $country_codes;
$country_codes = array(array("AF", "Afghanistan"),
                       array("AX", "Åland islands"),
                       array("AL", "Albania"),
                       array("DZ", "Algeria"),
                       array("AS", "American Samoa"),
                       array("AD", "Andorra"),
                       array("AO", "Angola"),
                       array("AI", "Anguilla"),
                       array("AQ", "Antarctica"),
                       array("AG", "Antigua and Barbuda"),
                       array("AR", "Argentina"),
                       array("AM", "Armenia"),
                       array("AW", "Aruba"),
                       array("AU", "Australia"),
                       array("AT", "Austria"),
                       array("AZ", "Azerbaijan"),
                       array("BS", "Bahamas"),
                       array("BH", "Bahrain"),
                       array("BD", "Bangladesh"),
                       array("BB", "Barbados"),
                       array("BY", "Belarus"),
                       array("BE", "Belgium"),
                       array("BZ", "Belize"),
                       array("BJ", "Benin"),
                       array("BM", "Bermuda"),
                       array("BT", "Bhutan"),
                       array("BO", "Bolivia"),
                       array("BA", "Bosnia and Herzegovina"),
                       array("BW", "Botswana"),
                       array("BV", "Bouvet Island"),
                       array("BR", "Brazil"),
                       array("IO", "British Indian Ocean Territory"),
                       array("BN", "Brunei Darussalam"),
                       array("BG", "Bulgaria"),
                       array("BF", "Burkina Faso"),
                       array("BI", "Burundi"),
                       array("KH", "Cambodia"),
                       array("CM", "Cameroon"),
                       array("CA", "Canada"),
                       array("CV", "Cape Verde"),
                       array("KY", "Cayman Islands"),
                       array("CF", "Central African Republic"),
                       array("TD", "Chad"),
                       array("CL", "Chile"),
                       array("CN", "China"),
                       array("CX", "Christmas Island"),
                       array("CC", "Cocos (Keeling) Islands"),
                       array("CO", "Colombia"),
                       array("KM", "Comoros"),
                       array("CG", "Congo"),
                       array("CD", "Congo, The Democratic Republic of the"),
                       array("CK", "Cook Islands"),
                       array("CR", "Costa Rica"),
                       array("CI", "Cote d'Ivoire"),
                       array("HR", "Croatia"),
                       array("CU", "Cuba"),
                       array("CY", "Cyprus"),
                       array("CZ", "Czech Republic"),
                       array("DK", "Denmark"),
                       array("DJ", "Djibouti"),
                       array("DM", "Dominica"),
                       array("DO", "Dominican Republic"),
                       array("EC", "Ecuador"),
                       array("EG", "Egypt"),
                       array("SV", "El Salvador"),
                       array("GQ", "Equatorial Guinea"),
                       array("ER", "Eritrea"),
                       array("EE", "Estonia"),
                       array("ET", "Ethiopia"),
                       array("FK", "Falkland Islands (Malvinas)"),
                       array("FO", "Faroe Islands"),
                       array("FJ", "Fiji"),
                       array("FI", "Finland"),
                       array("FR", "France"),
                       array("GF", "French Guiana"),
                       array("PF", "French Polynesia"),
                       array("TF", "French Southern Territories"),
                       array("GA", "Gabon"),
                       array("GM", "Gambia"),
                       array("GE", "Georgia"),
                       array("DE", "Germany"),
                       array("GH", "Ghana"),
                       array("GI", "Gibraltar"),
                       array("GR", "Greece"),
                       array("GL", "Greenland"),
                       array("GD", "Grenada"),
                       array("GP", "Guadeloupe"),
                       array("GU", "Guam"),
                       array("GT", "Guatemala"),
                       array("GG", "Guernsey"),
                       array("GN", "Guinea"),
                       array("GW", "Guinea-bissau"),
                       array("GY", "Guyana"),
                       array("HT", "Haiti"),
                       array("HM", "Heard Island and McDonald Islands"),
                       array("VA", "Holy See (Vatican City State)"),
                       array("HN", "Honduras"),
                       array("HK", "Hong Kong"),
                       array("HU", "Hungary"),
                       array("IS", "Iceland"),
                       array("IN", "India"),
                       array("ID", "Indonesia"),
                       array("IR", "Iran, Islamic Republic of"),
                       array("IQ", "Iraq"),
                       array("IE", "Ireland"),
                       array("IM", "Isle of Man"),
                       array("IL", "Israel"),
                       array("IT", "Italy"),
                       array("JM", "Jamaica"),
                       array("JP", "Japan"),
                       array("JE", "Jersey"),
                       array("JO", "Jordan"),
                       array("KZ", "Kazakhstan"),
                       array("KE", "Kenya"),
                       array("KI", "Kiribati"),
                       array("KP", "Korea, Democratic People's Republic of"),
                       array("KR", "Korea, Republic of"),
                       array("KW", "Kuwait"),
                       array("KG", "Kyrgyzstan"),
                       array("LA", "Lao People's Democratic Republic"),
                       array("LV", "Latvia"),
                       array("LB", "Lebanon"),
                       array("LS", "Lesotho"),
                       array("LR", "Liberia"),
                       array("LY", "Libyan Arab Jamahiriya"),
                       array("LI", "Liechtenstein"),
                       array("LT", "Lithuania"),
                       array("LU", "Luxembourg"),
                       array("MO", "Macao"),
                       array("MK", "Macedonia, The Former Yugoslav Republic of"),
                       array("MG", "Madagascar"),
                       array("MW", "Malawi"),
                       array("MY", "Malaysia"),
                       array("MV", "Maldives"),
                       array("ML", "Mali"),
                       array("MT", "Malta"),
                       array("MH", "Marshall Islands"),
                       array("MQ", "Martinique"),
                       array("MR", "Mauritania"),
                       array("MU", "Mauritius"),
                       array("YT", "Mayotte"),
                       array("MX", "Mexico"),
                       array("FM", "Micronesia, Federated States of"),
                       array("MD", "Moldova, Republic of"),
                       array("MC", "Monaco"),
                       array("MN", "Mongolia"),
                       array("MS", "Montserrat"),
                       array("MA", "Morocco"),
                       array("MZ", "Mozambique"),
                       array("MM", "Myanmar"),
                       array("NA", "Namibia"),
                       array("NR", "Nauru"),
                       array("NP", "Nepal"),
                       array("NL", "Netherlands"),
                       array("AN", "Netherlands Antilles"),
                       array("NC", "New Caledonia"),
                       array("NZ", "New Zealand"),
                       array("NI", "Nicaragua"),
                       array("NE", "Niger"),
                       array("NG", "Nigeria"),
                       array("NU", "Niue"),
                       array("NF", "Norfolk Island"),
                       array("MP", "Northern Mariana Islands"),
                       array("NO", "Norway"),
                       array("OM", "Oman"),
                       array("PK", "Pakistan"),
                       array("PW", "Palau"),
                       array("PS", "Palestinian Territory, Occupied"),
                       array("PA", "Panama"),
                       array("PG", "Papua New Guinea"),
                       array("PY", "Paraguay"),
                       array("PE", "Peru"),
                       array("PH", "Philippines"),
                       array("PN", "Pitcairn"),
                       array("PL", "Poland"),
                       array("PT", "Portugal"),
                       array("PR", "Puerto Rico"),
                       array("QA", "Qatar"),
                       array("RE", "Reunion"),
                       array("RO", "Romania"),
                       array("RU", "Russian Federation"),
                       array("RW", "Rwanda"),
                       array("SH", "Saint Helena"),
                       array("KN", "Saint Kitts and Nevis"),
                       array("LC", "Saint Lucia"),
                       array("PM", "Saint Pierre and Miquelon"),
                       array("VC", "Saint Vincent and the Grenadines"),
                       array("WS", "Samoa"),
                       array("SM", "San Marino"),
                       array("ST", "Sao Tome and Principe"),
                       array("SA", "Saudi Arabia"),
                       array("SN", "Senegal"),
                       array("CS", "Serbia and Montenegro"),
                       array("SC", "Seychelles"),
                       array("SL", "Sierra Leone"),
                       array("SG", "Singapore"),
                       array("SK", "Slovakia"),
                       array("SI", "Slovenia"),
                       array("SB", "Solomon Islands"),
                       array("SO", "Somalia"),
                       array("ZA", "South Africa"),
                       array("GS", "South Georgia and the South Sandwich Islands"),
                       array("ES", "Spain"),
                       array("LK", "Sri Lanka"),
                       array("SD", "Sudan"),
                       array("SR", "Suriname"),
                       array("SJ", "Svalbard and Jan Mayen"),
                       array("SZ", "Swaziland"),
                       array("SE", "Sweden"),
                       array("CH", "Switzerland"),
                       array("SY", "Syrian Arab Republic"),
                       array("TW", "Taiwan, Province of China"),
                       array("TJ", "Tajikistan"),
                       array("TZ", "Tanzania, United Republic of"),
                       array("TH", "Thailand"),
                       array("TL", "Timor-Leste"),
                       array("TG", "Togo"),
                       array("TK", "Tokelau"),
                       array("TO", "Tonga"),
                       array("TT", "Trinidad and Tobago"),
                       array("TN", "Tunisia"),
                       array("TR", "Turkey"),
                       array("TM", "Turkmenistan"),
                       array("TC", "Turks and Caicos Islands"),
                       array("TV", "Tuvalu"),
                       array("UG", "Uganda"),
                       array("UA", "Ukraine"),
                       array("AE", "United Arab Emirates"),
                       array("GB", "United Kingdom"),
                       array("US", "United States"),
                       array("UM", "United States Minor Outlying Islands"),
                       array("UY", "Uruguay"),
                       array("UZ", "Uzbekistan"),
                       array("VU", "Vanuatu"),
                       array("VE", "Venezuela"),
                       array("VN", "Viet Nam"),
                       array("VG", "Virgin Islands, British"),
                       array("VI", "Virgin Islands, U.S."),
                       array("WF", "Wallis and Futuna"),
                       array("EH", "Western Sahara"),
                       array("YE", "Yemen"),
                       array("ZM", "Zambia"),
                       array("ZW", "Zimbabwe"));

// These values were taken from http://www.twinsun.com/tz/tz-link.htm
global $timezone_strings;
$timezone_strings = array("Africa/Abidjan",
                          "Africa/Accra",
                          "Africa/Addis_Ababa",
                          "Africa/Algiers",
                          "Africa/Asmera",
                          "Africa/Bamako",
                          "Africa/Bangui",
                          "Africa/Banjul",
                          "Africa/Bissau",
                          "Africa/Blantyre",
                          "Africa/Brazzaville",
                          "Africa/Bujumbura",
                          "Africa/Cairo",
                          "Africa/Casablanca",
                          "Africa/Ceuta",
                          "Africa/Conakry",
                          "Africa/Dakar",
                          "Africa/Dar_es_Salaam",
                          "Africa/Djibouti",
                          "Africa/Douala",
                          "Africa/El_Aaiun",
                          "Africa/Freetown",
                          "Africa/Gaborone",
                          "Africa/Harare",
                          "Africa/Johannesburg",
                          "Africa/Kampala",
                          "Africa/Khartoum",
                          "Africa/Kigali",
                          "Africa/Kinshasa",
                          "Africa/Lagos",
                          "Africa/Libreville",
                          "Africa/Lome",
                          "Africa/Luanda",
                          "Africa/Lubumbashi",
                          "Africa/Lusaka",
                          "Africa/Malabo",
                          "Africa/Maputo",
                          "Africa/Maseru",
                          "Africa/Mbabane",
                          "Africa/Mogadishu",
                          "Africa/Monrovia",
                          "Africa/Nairobi",
                          "Africa/Ndjamena",
                          "Africa/Niamey",
                          "Africa/Nouakchott",
                          "Africa/Ouagadougou",
                          "Africa/Porto-Novo",
                          "Africa/Sao_Tome",
                          "Africa/Tripoli",
                          "Africa/Tunis",
                          "Africa/Windhoek",
                          "America/Adak",
                          "America/Anchorage",
                          "America/Anguilla",
                          "America/Antigua",
                          "America/Araguaina",
                          "America/Argentina/Buenos_Aires",
                          "America/Argentina/Catamarca",
                          "America/Argentina/Cordoba",
                          "America/Argentina/Jujuy",
                          "America/Argentina/La_Rioja",
                          "America/Argentina/Mendoza",
                          "America/Argentina/Rio_Gallegos",
                          "America/Argentina/San_Juan",
                          "America/Argentina/Tucuman",
                          "America/Argentina/Ushuaia",
                          "America/Aruba",
                          "America/Asuncion",
                          "America/Bahia",
                          "America/Barbados",
                          "America/Belem",
                          "America/Belize",
                          "America/Boa_Vista",
                          "America/Bogota",
                          "America/Boise",
                          "America/Cambridge_Bay",
                          "America/Campo_Grande",
                          "America/Cancun",
                          "America/Caracas",
                          "America/Cayenne",
                          "America/Cayman",
                          "America/Chicago",
                          "America/Chihuahua",
                          "America/Coral_Harbour",
                          "America/Costa_Rica",
                          "America/Cuiaba",
                          "America/Curacao",
                          "America/Danmarkshavn",
                          "America/Dawson",
                          "America/Dawson_Creek",
                          "America/Denver",
                          "America/Detroit",
                          "America/Dominica",
                          "America/Edmonton",
                          "America/Eirunepe",
                          "America/El_Salvador",
                          "America/Fortaleza",
                          "America/Glace_Bay",
                          "America/Godthab",
                          "America/Goose_Bay",
                          "America/Grand_Turk",
                          "America/Grenada",
                          "America/Guadeloupe",
                          "America/Guatemala",
                          "America/Guayaquil",
                          "America/Guyana",
                          "America/Halifax",
                          "America/Havana",
                          "America/Hermosillo",
                          "America/Indiana/Indianapolis",
                          "America/Indiana/Knox",
                          "America/Indiana/Marengo",
                          "America/Indiana/Petersburg",
                          "America/Indiana/Vevay",
                          "America/Indiana/Vincennes",
                          "America/Inuvik",
                          "America/Iqaluit",
                          "America/Jamaica",
                          "America/Juneau",
                          "America/Kentucky/Louisville",
                          "America/Kentucky/Monticello",
                          "America/La_Paz",
                          "America/Lima",
                          "America/Los_Angeles",
                          "America/Maceio",
                          "America/Managua",
                          "America/Manaus",
                          "America/Martinique",
                          "America/Mazatlan",
                          "America/Menominee",
                          "America/Merida",
                          "America/Mexico_City",
                          "America/Miquelon",
                          "America/Moncton",
                          "America/Monterrey",
                          "America/Montevideo",
                          "America/Montreal",
                          "America/Montserrat",
                          "America/Nassau",
                          "America/New_York",
                          "America/Nipigon",
                          "America/Nome",
                          "America/Noronha",
                          "America/North_Dakota/Center",
                          "America/Panama",
                          "America/Pangnirtung",
                          "America/Paramaribo",
                          "America/Phoenix",
                          "America/Port-au-Prince",
                          "America/Port_of_Spain",
                          "America/Porto_Velho",
                          "America/Puerto_Rico",
                          "America/Rainy_River",
                          "America/Rankin_Inlet",
                          "America/Recife",
                          "America/Regina",
                          "America/Rio_Branco",
                          "America/Santiago",
                          "America/Santo_Domingo",
                          "America/Sao_Paulo",
                          "America/Scoresbysund",
                          "America/Shiprock",
                          "America/St_Johns",
                          "America/St_Kitts",
                          "America/St_Lucia",
                          "America/St_Thomas",
                          "America/St_Vincent",
                          "America/Swift_Current",
                          "America/Tegucigalpa",
                          "America/Thule",
                          "America/Thunder_Bay",
                          "America/Tijuana",
                          "America/Toronto",
                          "America/Tortola",
                          "America/Vancouver",
                          "America/Whitehorse",
                          "America/Winnipeg",
                          "America/Yakutat",
                          "America/Yellowknife",
                          "Antarctica/Casey",
                          "Antarctica/Davis",
                          "Antarctica/DumontDUrville",
                          "Antarctica/Mawson",
                          "Antarctica/McMurdo",
                          "Antarctica/Palmer",
                          "Antarctica/Rothera",
                          "Antarctica/South_Pole",
                          "Antarctica/Syowa",
                          "Antarctica/Vostok",
                          "Arctic/Longyearbyen",
                          "Asia/Aden",
                          "Asia/Almaty",
                          "Asia/Amman",
                          "Asia/Anadyr",
                          "Asia/Aqtau",
                          "Asia/Aqtobe",
                          "Asia/Ashgabat",
                          "Asia/Baghdad",
                          "Asia/Bahrain",
                          "Asia/Baku",
                          "Asia/Bangkok",
                          "Asia/Beirut",
                          "Asia/Bishkek",
                          "Asia/Brunei",
                          "Asia/Calcutta",
                          "Asia/Choibalsan",
                          "Asia/Chongqing",
                          "Asia/Colombo",
                          "Asia/Damascus",
                          "Asia/Dhaka",
                          "Asia/Dili",
                          "Asia/Dubai",
                          "Asia/Dushanbe",
                          "Asia/Gaza",
                          "Asia/Harbin",
                          "Asia/Hong_Kong",
                          "Asia/Hovd",
                          "Asia/Irkutsk",
                          "Asia/Jakarta",
                          "Asia/Jayapura",
                          "Asia/Jerusalem",
                          "Asia/Kabul",
                          "Asia/Kamchatka",
                          "Asia/Karachi",
                          "Asia/Kashgar",
                          "Asia/Katmandu",
                          "Asia/Krasnoyarsk",
                          "Asia/Kuala_Lumpur",
                          "Asia/Kuching",
                          "Asia/Kuwait",
                          "Asia/Macau",
                          "Asia/Magadan",
                          "Asia/Makassar",
                          "Asia/Manila",
                          "Asia/Muscat",
                          "Asia/Nicosia",
                          "Asia/Novosibirsk",
                          "Asia/Omsk",
                          "Asia/Oral",
                          "Asia/Phnom_Penh",
                          "Asia/Pontianak",
                          "Asia/Pyongyang",
                          "Asia/Qatar",
                          "Asia/Qyzylorda",
                          "Asia/Rangoon",
                          "Asia/Riyadh",
                          "Asia/Saigon",
                          "Asia/Sakhalin",
                          "Asia/Samarkand",
                          "Asia/Seoul",
                          "Asia/Shanghai",
                          "Asia/Singapore",
                          "Asia/Taipei",
                          "Asia/Tashkent",
                          "Asia/Tbilisi",
                          "Asia/Tehran",
                          "Asia/Thimphu",
                          "Asia/Tokyo",
                          "Asia/Ulaanbaatar",
                          "Asia/Urumqi",
                          "Asia/Vientiane",
                          "Asia/Vladivostok",
                          "Asia/Yakutsk",
                          "Asia/Yekaterinburg",
                          "Asia/Yerevan",
                          "Atlantic/Azores",
                          "Atlantic/Bermuda",
                          "Atlantic/Canary",
                          "Atlantic/Cape_Verde",
                          "Atlantic/Faeroe",
                          "Atlantic/Jan_Mayen",
                          "Atlantic/Madeira",
                          "Atlantic/Reykjavik",
                          "Atlantic/South_Georgia",
                          "Atlantic/St_Helena",
                          "Atlantic/Stanley",
                          "Australia/Adelaide",
                          "Australia/Brisbane",
                          "Australia/Broken_Hill",
                          "Australia/Currie",
                          "Australia/Darwin",
                          "Australia/Hobart",
                          "Australia/Lindeman",
                          "Australia/Lord_Howe",
                          "Australia/Melbourne",
                          "Australia/Perth",
                          "Australia/Sydney",
                          "Europe/Amsterdam",
                          "Europe/Andorra",
                          "Europe/Athens",
                          "Europe/Belgrade",
                          "Europe/Berlin",
                          "Europe/Bratislava",
                          "Europe/Brussels",
                          "Europe/Bucharest",
                          "Europe/Budapest",
                          "Europe/Chisinau",
                          "Europe/Copenhagen",
                          "Europe/Dublin",
                          "Europe/Gibraltar",
                          "Europe/Helsinki",
                          "Europe/Istanbul",
                          "Europe/Kaliningrad",
                          "Europe/Kiev",
                          "Europe/Lisbon",
                          "Europe/Ljubljana",
                          "Europe/London",
                          "Europe/Luxembourg",
                          "Europe/Madrid",
                          "Europe/Malta",
                          "Europe/Mariehamn",
                          "Europe/Minsk",
                          "Europe/Monaco",
                          "Europe/Moscow",
                          "Europe/Oslo",
                          "Europe/Paris",
                          "Europe/Prague",
                          "Europe/Riga",
                          "Europe/Rome",
                          "Europe/Samara",
                          "Europe/San_Marino",
                          "Europe/Sarajevo",
                          "Europe/Simferopol",
                          "Europe/Skopje",
                          "Europe/Sofia",
                          "Europe/Stockholm",
                          "Europe/Tallinn",
                          "Europe/Tirane",
                          "Europe/Uzhgorod",
                          "Europe/Vaduz",
                          "Europe/Vatican",
                          "Europe/Vienna",
                          "Europe/Vilnius",
                          "Europe/Warsaw",
                          "Europe/Zagreb",
                          "Europe/Zaporozhye",
                          "Europe/Zurich",
                          "Indian/Antananarivo",
                          "Indian/Chagos",
                          "Indian/Christmas",
                          "Indian/Cocos",
                          "Indian/Comoro",
                          "Indian/Kerguelen",
                          "Indian/Mahe",
                          "Indian/Maldives",
                          "Indian/Mauritius",
                          "Indian/Mayotte",
                          "Indian/Reunion",
                          "Pacific/Apia",
                          "Pacific/Auckland",
                          "Pacific/Chatham",
                          "Pacific/Easter",
                          "Pacific/Efate",
                          "Pacific/Enderbury",
                          "Pacific/Fakaofo",
                          "Pacific/Fiji",
                          "Pacific/Funafuti",
                          "Pacific/Galapagos",
                          "Pacific/Gambier",
                          "Pacific/Guadalcanal",
                          "Pacific/Guam",
                          "Pacific/Honolulu",
                          "Pacific/Johnston",
                          "Pacific/Kiritimati",
                          "Pacific/Kosrae",
                          "Pacific/Kwajalein",
                          "Pacific/Majuro",
                          "Pacific/Marquesas",
                          "Pacific/Midway",
                          "Pacific/Nauru",
                          "Pacific/Niue",
                          "Pacific/Norfolk",
                          "Pacific/Noumea",
                          "Pacific/Pago_Pago",
                          "Pacific/Palau",
                          "Pacific/Pitcairn",
                          "Pacific/Ponape",
                          "Pacific/Port_Moresby",
                          "Pacific/Rarotonga",
                          "Pacific/Saipan",
                          "Pacific/Tahiti",
                          "Pacific/Tarawa",
                          "Pacific/Tongatapu",
                          "Pacific/Truk",
                          "Pacific/Wake",
                          "Pacific/Wallis");

// These codes taken from http://www.w3.org/WAI/ER/IG/ert/iso639.htm.
global $language_codes;
$language_codes = array(array("AA", "Afar"),
                        array("AB", "Abkhazian"),
                        array("AF", "Afrikaans"),
                        array("AM", "Amharic"),
                        array("AR", "Arabic"),
                        array("AS", "Assamese"),
                        array("AY", "Aymara"),
                        array("AZ", "Azerbaijani"),
                        array("BA", "Bashkir"),
                        array("BE", "Byelorussian"),
                        array("BG", "Bulgarian"),
                        array("BH", "Bihari"),
                        array("BI", "Bislama"),
                        array("BN", "Bengali"),
                        array("BO", "Tibetan"),
                        array("BR", "Breton"),
                        array("CA", "Catalan"),
                        array("CO", "Corsican"),
                        array("CS", "Czech"),
                        array("CY", "Welsh"),
                        array("DA", "Danish"),
                        array("DE", "German"),
                        array("DZ", "Bhutani"),
                        array("EL", "Greek"),
                        array("EN", "English"),
                        array("EO", "Esperanto"),
                        array("ES", "Spanish"),
                        array("ET", "Estonian"),
                        array("EU", "Basque"),
                        array("FA", "Persian"),
                        array("FI", "Finnish"),
                        array("FJ", "Fiji"),
                        array("FO", "Faeroese"),
                        array("FR", "French"),
                        array("FY", "Frisian"),
                        array("GA", "Irish"),
                        array("GD", "Gaelic"),
                        array("GL", "Galician"),
                        array("GN", "Guarani"),
                        array("GU", "Gujarati"),
                        array("HA", "Hausa"),
                        array("HI", "Hindi"),
                        array("HR", "Croatian"),
                        array("HU", "Hungarian"),
                        array("HY", "Armenian"),
                        array("IA", "Interlingua"),
                        array("IE", "Interlingue"),
                        array("IK", "Inupiak"),
                        array("IN", "Indonesian"),
                        array("IS", "Icelandic"),
                        array("IT", "Italian"),
                        array("IW", "Hebrew"),
                        array("JA", "Japanese"),
                        array("JI", "Yiddish"),
                        array("JW", "Javanese"),
                        array("KA", "Georgian"),
                        array("KK", "Kazakh"),
                        array("KL", "Greenlandic"),
                        array("KM", "Cambodian"),
                        array("KN", "Kannada"),
                        array("KO", "Korean"),
                        array("KS", "Kashmiri"),
                        array("KU", "Kurdish"),
                        array("KY", "Kirghiz"),
                        array("LA", "Latin"),
                        array("LN", "Lingala"),
                        array("LO", "Laothian"),
                        array("LT", "Lithuanian"),
                        array("LV", "Latvian"),
                        array("MG", "Malagasy"),
                        array("MI", "Maori"),
                        array("MK", "Macedonian"),
                        array("ML", "Malayalam"),
                        array("MN", "Mongolian"),
                        array("MO", "Moldavian"),
                        array("MR", "Marathi"),
                        array("MS", "Malay"),
                        array("MT", "Maltese"),
                        array("MY", "Burmese"),
                        array("NA", "Nauru"),
                        array("NE", "Nepali"),
                        array("NL", "Dutch"),
                        array("NO", "Norwegian"),
                        array("OC", "Occitan"),
                        array("OM", "Oromo"),
                        array("OR", "Oriya"),
                        array("PA", "Punjabi"),
                        array("PL", "Polish"),
                        array("PS", "Pashto"),
                        array("PT", "Portuguese"),
                        array("QU", "Quechua"),
                        array("RM", "Rhaeto-Romance"),
                        array("RN", "Kirundi"),
                        array("RO", "Romanian"),
                        array("RU", "Russian"),
                        array("RW", "Kinyarwanda"),
                        array("SA", "Sanskrit"),
                        array("SD", "Sindhi"),
                        array("SG", "Sangro"),
                        array("SH", "Serbo-Croatian"),
                        array("SI", "Singhalese"),
                        array("SK", "Slovak"),
                        array("SL", "Slovenian"),
                        array("SM", "Samoan"),
                        array("SN", "Shona"),
                        array("SO", "Somali"),
                        array("SQ", "Albanian"),
                        array("SR", "Serbian"),
                        array("SS", "Siswati"),
                        array("ST", "Sesotho"),
                        array("SU", "Sudanese"),
                        array("SV", "Swedish"),
                        array("SW", "Swahili"),
                        array("TA", "Tamil"),
                        array("TE", "Tegulu"),
                        array("TG", "Tajik"),
                        array("TH", "Thai"),
                        array("TI", "Tigrinya"),
                        array("TK", "Turkmen"),
                        array("TL", "Tagalog"),
                        array("TN", "Setswana"),
                        array("TO", "Tonga"),
                        array("TR", "Turkish"),
                        array("TS", "Tsonga"),
                        array("TT", "Tatar"),
                        array("TW", "Twi"),
                        array("UK", "Ukrainian"),
                        array("UR", "Urdu"),
                        array("UZ", "Uzbek"),
                        array("VI", "Vietnamese"),
                        array("VO", "Volapuk"),
                        array("WO", "Wolof"),
                        array("XH", "Xhosa"),
                        array("YO", "Yoruba"),
                        array("ZH", "Chinese"),
                        array("ZU", "Zulu"));
?>