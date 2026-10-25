<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Kolkata');
define('ROOT', dirname(__DIR__, 2));
require_once __DIR__ . '/../config.php';
$__l = __DIR__ . '/../config.local.php'; if (is_file($__l)) require_once $__l;
defined('SITE_URL') || define('SITE_URL', SITE_URL_DEFAULT);
defined('DATA_DIR') || define('DATA_DIR', dirname(__DIR__) . '/data');
const BRAND = "Madhu's Tours & Travels";

class ApiError extends Exception { public int $status; public array $extra;
  function __construct(string $m, int $s = 400, array $extra = []) { parent::__construct($m); $this->status = $s; $this->extra = $extra; } }

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function ensure_dir(string $d): void { if (!is_dir($d)) @mkdir($d, 0755, true); }
function init_data(): void {
  foreach (['', '/articles', '/backup'] as $s) ensure_dir(DATA_DIR . $s);
  $ht = DATA_DIR . '/.htaccess';
  if (!is_file($ht)) file_put_contents($ht, "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
  if (!is_file(DATA_DIR . '/index.html')) file_put_contents(DATA_DIR . '/index.html', '');
}
function atomic_write(string $f, string $c): void {
  ensure_dir(dirname($f)); $t = $f . '.tmp' . bin2hex(random_bytes(3));
  if (file_put_contents($t, $c) === false || !rename($t, $f)) throw new ApiError('Could not write file: ' . basename($f), 500);
}
function jread(string $f, $d = []) { if (!is_file($f)) return $d; $j = json_decode((string)file_get_contents($f), true); return $j ?? $d; }
function jwrite(string $f, $data): void { atomic_write($f, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); }
function with_lock(callable $fn) { init_data(); $fp = fopen(DATA_DIR . '/.lock', 'c'); flock($fp, LOCK_EX); try { return $fn(); } finally { flock($fp, LOCK_UN); fclose($fp); } }

/* ---------- session / auth ---------- */
function start_session(): void {
  if (session_status() === PHP_SESSION_ACTIVE) return;
  $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
  session_name('rtadm');
  session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Strict']);
  session_start();
  if (isset($_SESSION['uid']) && time() - ($_SESSION['last'] ?? 0) > 7200) { $_SESSION = []; session_destroy(); session_start(); }
  if (isset($_SESSION['uid'])) $_SESSION['last'] = time();
  if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
}
function users(): array { return jread(DATA_DIR . '/users.json', []); }
function current_user(): ?array { foreach (users() as $u) if (isset($_SESSION['uid']) && $u['id'] === $_SESSION['uid']) return $u; return null; }
function require_user(): array { $u = current_user(); if (!$u) throw new ApiError('Please sign in again.', 401); return $u; }
function require_csrf(): void {
  $t = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
  if (!hash_equals($_SESSION['csrf'] ?? '', $t)) throw new ApiError('Your session expired. Please refresh the page.', 403);
}
function rate_check(string $key, bool $fail = false): void {
  $f = DATA_DIR . '/rate.json'; $r = jread($f, []); $now = time();
  foreach ($r as $k => $v) { $r[$k] = array_values(array_filter($v, fn($t) => $now - $t < 900)); if (!$r[$k]) unset($r[$k]); }
  if ($fail) { $r[$key][] = $now; jwrite($f, $r); return; }
  if (count($r[$key] ?? []) >= 5) throw new ApiError('Too many sign-in attempts. Please wait 15 minutes and try again.', 429);
}
function do_login(string $email, string $pass): void {
  $ip = $_SERVER['REMOTE_ADDR'] ?? '?'; $k1 = 'ip:' . $ip; $k2 = 'em:' . strtolower($email);
  rate_check($k1); rate_check($k2);
  foreach (users() as $u) if (strcasecmp($u['email'], $email) === 0 && password_verify($pass, $u['hash'])) {
    session_regenerate_id(true); $_SESSION['uid'] = $u['id']; $_SESSION['last'] = time(); return; }
  rate_check($k1, true); rate_check($k2, true);
  throw new ApiError('Email or password is not correct.', 401);
}
function add_user(string $email, string $name, string $pass): array {
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new ApiError('Please enter a valid email address.');
  if (strlen($pass) < 10) throw new ApiError('Password must be at least 10 characters.');
  $us = users(); foreach ($us as $u) if (strcasecmp($u['email'], $email) === 0) throw new ApiError('That email already has access.');
  $u = ['id' => 'u' . bin2hex(random_bytes(5)), 'email' => $email, 'name' => $name ?: $email, 'hash' => password_hash($pass, PASSWORD_DEFAULT), 'created' => date('c')];
  $us[] = $u; jwrite(DATA_DIR . '/users.json', $us); return $u;
}

/* ---------- categories ---------- */
function default_categories(): array { return [
 ['id'=>'car','label'=>'Car Rental / Car Booking','service'=>'services/car-rental.html','hub'=>'blog/car-rentals.html','icon'=>'bi-car-front','img'=>'assets/img/about/Car.png','locked'=>true],
 ['id'=>'bus','label'=>'Bus Ticket Booking','service'=>'services/bus-ticket-booking.html','hub'=>'blog/bus-booking.html','icon'=>'bi-bus-front','img'=>'assets/img/services/rjy-10.jpg','locked'=>true],
 ['id'=>'train','label'=>'Train Ticket Booking','service'=>'services/train-ticket-booking.html','hub'=>'blog/train-booking.html','icon'=>'bi-train-front','img'=>'assets/img/services/TT1.jpg','locked'=>true],
 ['id'=>'flight','label'=>'Flight Ticket Booking','service'=>'services/flight-ticket-booking.html','hub'=>'blog/flight-booking.html','icon'=>'bi-airplane','img'=>'assets/img/background.png','locked'=>true],
 ['id'=>'papikondalu','label'=>'Papikondalu Tourism / Godavari Boat Trips','service'=>'services/papikondalu-maredumilli-tourism.html','hub'=>'blog/papikondalu-maredumilli.html','icon'=>'bi-water','img'=>'assets/img/pk1.jpeg','locked'=>true],
 ['id'=>'maredumilli','label'=>'Maredumilli Tourism','service'=>'services/papikondalu-maredumilli-tourism.html','hub'=>'blog/papikondalu-maredumilli.html','icon'=>'bi-tree','img'=>'assets/img/G6.jpeg','locked'=>true],
 ['id'=>'devotional','label'=>'Devotional Yatra Packages','service'=>'services/devotional-yatra-package.html','hub'=>'blog/devotional-yatra.html','icon'=>'bi-flower1','img'=>'assets/img/G8.jpeg','locked'=>true],
 ['id'=>'railway','label'=>'Rajahmundry Railway Station / Railway Travel','service'=>'services/rajahmundry-railway-station.html','hub'=>null,'icon'=>'bi-geo-alt','img'=>'assets/img/services/TT1.jpg','locked'=>true],
 ['id'=>'general','label'=>'General Travel Blog','service'=>null,'hub'=>null,'icon'=>'bi-journal-text','img'=>'assets/img/pk2.jpeg','locked'=>true],
]; }
function categories(): array { $c = jread(DATA_DIR . '/categories.json', null); if (!$c) { $c = default_categories(); jwrite(DATA_DIR . '/categories.json', $c); } return $c; }
function cat_map(): array { $m = []; foreach (categories() as $c) $m[$c['id']] = $c; return $m; }

/* ---------- articles ---------- */
function slugify(string $s): string {
  $s = strtolower(trim($s)); $s = preg_replace('/&/', ' and ', $s);
  $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
  $s = preg_replace('/[^a-z0-9]+/', '-', $s); return trim(substr(trim($s, '-'), 0, 80), '-');
}
function all_articles(): array { $o = []; foreach (glob(DATA_DIR . '/articles/*.json') ?: [] as $f) { $a = jread($f, null); if ($a) $o[] = $a; } usort($o, fn($a, $b) => strcmp($b['modified'], $a['modified'])); return $o; }
function get_article(string $id): array {
  if (!preg_match('/^a[a-f0-9]{12}$/', $id)) throw new ApiError('Article not found.', 404);
  $a = jread(DATA_DIR . '/articles/' . $id . '.json', null); if (!$a) throw new ApiError('Article not found.', 404); return $a; }
function save_article(array $a): void { $a['modified'] = date('c'); jwrite(DATA_DIR . '/articles/' . $a['id'] . '.json', $a); }
function reserved_slug(string $slug, ?string $exceptId = null): ?string {
  if (!preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug) || strlen($slug) > 80) return 'Use only lowercase letters, numbers and hyphens (for example: best-time-to-visit-papikondalu).';
  if (strlen($slug) < 3) return 'The web address is too short.';
  foreach (all_articles() as $x) if ($x['id'] !== $exceptId && $x['slug'] === $slug && $x['status'] !== 'deleted') return 'Another article already uses this web address.';
  $red = jread(DATA_DIR . '/redirects.json', []); if (isset($red[$slug])) return 'This address was used by an older article and redirects elsewhere.';
  $pub = $exceptId ? (get_article_or_null($exceptId)['publishedSlug'] ?? null) : null;
  if (is_file(ROOT . "/blog/$slug.html") && $slug !== $pub && !in_array($slug, jread(DATA_DIR . '/generated.json', ['slugs' => []])['slugs'], true)) return 'This address is already used by an existing page on the website.';
  if (is_dir(ROOT . "/blog/$slug")) return 'This address is already used by an existing section of the website.';
  return null;
}
function get_article_or_null(string $id): ?array { try { return get_article($id); } catch (ApiError $e) { return null; } }
function new_article(): array { return ['id'=>'a'.bin2hex(random_bytes(6)),'title'=>'','slug'=>'','excerpt'=>'','body'=>'','primary'=>'','related'=>[],'tags'=>[],
  'cover'=>null,'author'=>BRAND,'date'=>date('Y-m-d'),'created'=>date('c'),'modified'=>date('c'),'status'=>'draft','seoTitle'=>'','seoDesc'=>'','publishedSlug'=>null,'firstPublished'=>null]; }
function seo_defaults(array $a): array {
  $t = $a['seoTitle'] ?: ($a['title'] . ' | Rajahmundry Tourism');
  $d = $a['seoDesc'] ?: mb_substr(trim(preg_replace('/\s+/', ' ', $a['excerpt'])), 0, 155);
  return [$t, $d]; }

/* ---------- media ---------- */
function media_index(): array { return jread(DATA_DIR . '/media.json', []); }
function media_usage(): array {
  $use = [];
  foreach (all_articles() as $a) { if ($a['status'] === 'deleted') continue;
    preg_match_all('#assets/img/blog/[\w./-]+#', $a['body'], $m); $files = $m[0];
    if (!empty($a['cover']['src'])) $files[] = $a['cover']['src'];
    foreach (array_unique($files) as $f) $use[$f][] = ['id' => $a['id'], 'title' => $a['title'] ?: '(untitled)', 'status' => $a['status']]; }
  return $use; }
