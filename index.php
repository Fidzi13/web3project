<?php
// ===================================================
// CLOUDflare REAL IP (WAJIB)
// ===================================================
if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
    $_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_CF_CONNECTING_IP'];
}

// ===================================================
// CONFIG
// ===================================================
$allowedCountries = ['US','CA','GB','AU','DE']; // GEO allowlist
$maxClicksNoResult = 7;     // toleransi klik tanpa hasil (proxy EPC)
$delayMs = 1500;            // delay redirect (ms)

$dataDir   = __DIR__.'/data/';
$statsFile = $dataDir.'stats.json';
$blockFile = $dataDir.'blocked_ips.json';
$linksFile = $dataDir.'smartlinks.json';

// init files
if (!file_exists($statsFile)) file_put_contents($statsFile, '{}');
if (!file_exists($blockFile)) file_put_contents($blockFile, '[]');
if (!file_exists($linksFile)) die('No smartlinks');

// ===================================================
// BASIC FILTERS
// ===================================================
// User-Agent bot filter
$ua = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
$bots = ['bot','crawl','spider','facebook','telegram','whatsapp','curl','wget','python','java','monitor'];
foreach ($bots as $b) if (strpos($ua, $b) !== false) exit;
if (trim($ua) === '') exit;

// IP validation (private/reserved)
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) exit;

// Blocked IP list
$blocked = json_decode(file_get_contents($blockFile), true);
if (in_array($ip, $blocked)) exit;

// Mobile only
if (!preg_match('/iphone|android|ipad|ipod|mobile/i', $ua)) exit;

// ===================================================
// GEO FILTER (Cloudflare Header)
// ===================================================
$country = $_SERVER['HTTP_CF_IPCOUNTRY'] ?? '';
if (!$country || !in_array($country, $allowedCountries)) exit;

// ===================================================
// ASN / DATACENTER FILTER (Cloudflare org)
// ===================================================
$org = strtolower($_SERVER['HTTP_CF_ORG'] ?? '');
$dc = ['amazon','google','microsoft','ovh','digitalocean','linode','hetzner','vultr','contabo'];
foreach ($dc as $d) if (strpos($org, $d) !== false) exit;

// ===================================================
// TRACKING (SubID + proxy EPC logic)
// ===================================================
$subid = hash('sha256', $ip.$ua);
$stats = json_decode(file_get_contents($statsFile), true);

if (!isset($stats[$subid])) {
    $stats[$subid] = ['ip'=>$ip,'hits'=>0,'bad'=>0,'t'=>time()];
}
$stats[$subid]['hits']++;

// jika hits banyak tanpa hasil → tandai bad
if ($stats[$subid]['hits'] >= $maxClicksNoResult) {
    $stats[$subid]['bad']++;
    if ($stats[$subid]['bad'] >= 2) {
        $blocked[] = $ip;
        file_put_contents($blockFile, json_encode(array_values(array_unique($blocked))));
        file_put_contents($statsFile, json_encode($stats));
        exit;
    }
}
file_put_contents($statsFile, json_encode($stats));

// ===================================================
// SMARTLINK ROTATION + AUTO-PAUSE
// ===================================================
$links = json_decode(file_get_contents($linksFile), true);
$available = array_values(array_filter($links['links'], fn($l)=>empty($l['paused'])));
if (!$available) exit;

$pick = $available[array_rand($available)];

// hit count
foreach ($links['links'] as &$l) {
    if ($l['url'] === $pick['url']) $l['hits']++;
}

// auto-pause jika rasio buruk
foreach ($links['links'] as &$l) {
    if ($l['hits'] >= 50 && ($l['bad']/$l['hits']) > 0.9) {
        $l['paused'] = true;
    }
}
file_put_contents($linksFile, json_encode($links));

// ===================================================
// RENDER LP + DELAY REDIRECT
// ===================================================
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Crypto Trend</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
</head>
<body>
<h1>Crypto Users Are Quietly Earning Daily Rewards</h1>
<p>
Early users are focusing on emerging crypto ecosystems that reward activity—not just capital.
As adoption grows, early participation often benefits the most.
</p>

<script>
setTimeout(function(){
  window.location.href = <?= json_encode($pick['url']) ?>;
}, <?= (int)$delayMs ?>);
</script>
</body>
</html>
