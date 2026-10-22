<?php
/**
 * Robi SMS API Proxy - Fixed Version
 */

error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 0);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS, HEAD");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Max-Age: 86400");

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'OPTIONS') { http_response_code(204); exit; }

header("Cache-Control: no-store, no-cache, must-revalidate");
header("Content-Type: application/json; charset=utf-8");

// ================= CONFIG =================
$MAIN_API_URL = "https://api.eyecon-app.com/app/cli_auth/gettransport";

// Proxy ON/OFF
$USE_PROXY   = true;
$PROXY_HOST  = "p.webshare.io";
$PROXY_PORT  = "80";
$PROXY_USER  = "idkkkdrv-rotate";
$PROXY_PASS  = "wr9ucocz1h9w";

// ================= INPUT =================
function getInput() {
    $in = [];
    if (!empty($_GET))  $in = array_merge($in, $_GET);
    if (!empty($_POST)) $in = array_merge($in, $_POST);
    $raw = file_get_contents("php://input");
    if (!empty($raw)) {
        $j = json_decode($raw, true);
        if (is_array($j)) $in = array_merge($in, $j);
    }
    return $in;
}

function normalizeNumber($n) {
    $n = preg_replace('/\D+/', '', (string)$n);
    if (strlen($n) == 13 && substr($n,0,2) == "88") $n = substr($n,2);
    if (strlen($n) == 12 && substr($n,0,2) == "88") $n = substr($n,2);
    if (strlen($n) == 10 && $n[0] == "1") $n = "0".$n;
    return $n;
}

$in     = getInput();
$msisdn = $in['msisdn'] ?? $in['number'] ?? $in['phone'] ?? $in['cli'] ?? null;

if (empty($msisdn)) {
    http_response_code(400);
    echo json_encode(["status"=>false,"message"=>"missing msisdn"]);
    exit;
}

$msisdn = normalizeNumber($msisdn);

if (!preg_match('/^01[3-9]\d{8}$/', $msisdn)) {
    http_response_code(400);
    echo json_encode(["status"=>false,"message"=>"invalid number"]);
    exit;
}

// ================= PARAMS =================
// NOTE: cli শুধু number দিলে Eyecon অনেক সময় error_status 3 দেয়
// তাই cli এ full 880 ফরম্যাট দিচ্ছি
$cli_full = "88" . $msisdn;  // 8801XXXXXXXXX

$params = [
    'cv'                       => "vc_739_vn_4.2026.02.19.1555_a",
    'cli'                      => $cli_full,
    'reg_id'                   => "",
    'is_already_social_auth'   => "false",
    'installer_name'           => "manually or unknown source",
    'n_sims'                   => "1",
    'time'                     => (string) round(microtime(true) * 1000),
    'is_sms_sending_available' => "true",
    'is_whatsapp_installed'    => "false",
    'mc'                       => "3D:6E:2C:AD:DD:92",
    'device_id'                => "338fdcde4f9cb045",
    'time_zone'                => "Asia/Dhaka",
    'device_manu'              => "Google",
    'device_model'             => "Pixel 4"
];

$finalUrl = $MAIN_API_URL . "?" . http_build_query($params);

// ================= CURL =================
$ch = curl_init();
$opts = [
    CURLOPT_URL            => $finalUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_CONNECTTIMEOUT => 15,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_CUSTOMREQUEST  => "GET",
    CURLOPT_ENCODING       => "",
    CURLOPT_HTTPHEADER     => [
        "User-Agent: Mozilla/5.0 (Linux; Android 10; Pixel 4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/87.0.4280.88 Mobile Safari/537.36",
        "Connection: Keep-Alive",
        "accept-charset: UTF-8",
        "content-type: application/x-www-form-urlencoded; charset=utf-8"
    ]
];

if ($USE_PROXY) {
    $opts[CURLOPT_PROXY]         = $PROXY_HOST;
    $opts[CURLOPT_PROXYPORT]     = $PROXY_PORT;
    $opts[CURLOPT_PROXYUSERPWD]  = $PROXY_USER . ":" . $PROXY_PASS;
    $opts[CURLOPT_PROXYTYPE]     = CURLPROXY_HTTP;
}

curl_setopt_array($ch, $opts);
$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// ================= OUTPUT =================
if ($response === false || !empty($curlError)) {
    http_response_code(502);
    echo json_encode([
        "status"  => false,
        "message" => "curl failed",
        "error"   => $curlError
    ]);
    exit;
}

http_response_code($httpCode ?: 200);
echo $response;
exit;{
