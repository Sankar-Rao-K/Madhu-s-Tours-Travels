<?php
declare(strict_types=1);
require __DIR__ . '/lib/core.php'; require __DIR__ . '/lib/sanitize.php'; require __DIR__ . '/lib/images.php'; require __DIR__ . '/lib/generate.php';
init_data(); start_session();
header('X-Robots-Tag: noindex, nofollow'); header('Cache-Control: no-store'); header('X-Content-Type-Options: nosniff');
function out(array $d, int $c = 200): void { http_response_code($c); header('Content-Type: application/json; charset=utf-8'); echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit; }
function input(): array { $j = json_decode((string)file_get_contents('php://input'), true); return is_array($j) ? $j : []; }
function site_pages(): array {
  $o = []; $skip = ['404.html', 'thank-you.html'];
  foreach (array_merge(glob(ROOT . '/*.html') ?: [], glob(ROOT . '/services/*.html') ?: [], glob(ROOT . '/blog/*.html') ?: []) as $f) {
    $rel = ltrim(substr($f, strlen(ROOT)), '/'); if (in_array(basename($f), $skip, true)) continue; $s = (string)file_get_contents($f);
    if (str_contains($s, 'http-equiv="refresh"') || str_contains($s, 'noindex')) continue;
    if (in_array(substr(basename($f, '.html'), 0), jread(DATA_DIR . '/generated.json', ['slugs' => []])['slugs'], true) && str_starts_with($rel, 'blog/')) continue;
    preg_match('#<title>(.*?)</title>#s', $s, $m); $t = trim(html_entity_decode(preg_replace('/\s*\|.*$/', '', $m[1] ?? $rel))); $o[] = ['path' => $rel, 'title' => $t ?: $rel]; }
  return $o; }
function validate_publish(array $a): array {
  $p = []; if (trim($a['title']) === '') $p[] = 'Please enter a blog title.';
  if ($e = reserved_slug($a['slug'], $a['id'])) $p[] = 'Web address: ' . $e;
  if (!isset(cat_map()[$a["primary"]])) $p[] = 'Please choose a service (main category).';
  if (trim($a['excerpt']) === '') $p[] = 'Please add a short description (it appears on blog cards).';
  if (mb_strlen(trim(strip_tags($a['body']))) < 80) $p[] = 'The article is too short. Please add more content.';
  if (empty($a['cover']['src']) || !is_file(ROOT . '/' . $a['cover']['src'])) $p[] = 'Please upload a cover photo.';
  elseif (trim($a['cover']['alt'] ?? '') === '') $p[] = 'Please describe the cover photo (alt text) for accessibility and search engines.';
  if ($a['date'] > date('Y-m-d')) $p[] = 'The publication date is in the future. Scheduling is not supported — choose today or an earlier date.';
  $own = ["blog/{$a['slug']}.html"]; foreach (broken_refs($a['body'], $own) as $b) $p[] = 'A link or image points to a page that does not exist: ' . $b;
  return $p; }
function merge_fields(array $a, array $in): array {
  $cm = cat_map();
  foreach (['title', 'excerpt', 'author', 'seoTitle', 'seoDesc'] as $k) if (isset($in[$k])) $a[$k] = trim(strip_tags((string)$in[$k]));
  if (isset($in['slug'])) $a['slug'] = slugify((string)$in['slug']);
  if (isset($in['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$in['date'])) $a['date'] = $in['date'];
  if (isset($in['primary'])) $a['primary'] = isset($cm[$in['primary']]) ? $in['primary'] : '';
  if (isset($in['related']) && is_array($in['related'])) $a['related'] = array_values(array_diff(array_filter($in['related'], fn($x) => isset($cm[$x])), [$a['primary']]));
  if (isset($in['tags'])) $a['tags'] = array_slice(array_values(array_unique(array_filter(array_map(fn($t) => trim(strip_tags((string)$t)), (array)$in['tags'])))), 0, 15);
  if (array_key_exists('cover', $in)) { $c = $in['cover']; $a['cover'] = (is_array($c) && ($s = san_img((string)($c['src'] ?? ''))) && str_starts_with($s, 'assets/img/blog/')) ? ['src' => $s, 'alt' => trim(strip_tags((string)($c['alt'] ?? '')))] : null; }
  if (isset($in['body'])) { $w = []; $a['body'] = sanitize_html((string)$in['body'], $w); }
  return $a; }

try {
  $act = $_GET['a'] ?? '';
  if ($act === 'preview') { require_user(); $a = get_article((string)($_GET['id'] ?? '')); $a = $a['pending'] ?? $a;
    $sch = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $GLOBALS['RT_BASE'] = $sch . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/') . '/';
    header('Content-Type: text/html; charset=utf-8'); header('Referrer-Policy: no-referrer'); echo render_article($a, true); exit; }
  if ($act === 'session') { $u = current_user(); out(['auth' => (bool)$u, 'csrf' => $_SESSION['csrf'], 'user' => $u ? ['name' => $u['name'], 'email' => $u['email'], 'id' => $u['id']] : null, 'siteUrl' => SITE_URL, 'needsSetup' => !users()]); }
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new ApiError('Not allowed.', 405);
  require_csrf(); $in = input();
  if ($act === 'login') { do_login((string)($in['email'] ?? ''), (string)($in['password'] ?? '')); out(['ok' => true]); }
  $me = require_user();
  switch ($act) {
    case 'logout': $_SESSION = []; session_destroy(); out(['ok' => true]);
    case 'boot': {
      $arts = array_values(array_filter(all_articles(), fn($a) => $a['status'] !== 'deleted'));
      $list = array_map(fn($a) => array_diff_key($a, ['body' => 1, 'pending' => 1]) + ['hasPending' => !empty($a['pending'])], $arts); out(['categories' => categories(), 'articles' => $list, 'pages' => site_pages(), 'siteUrl' => SITE_URL]); }
    case 'article.get': { $a = get_article((string)($in['id'] ?? '')); out(['article' => $a['pending'] ?? $a, 'live' => $a['status'] === 'published', 'hasPending' => !empty($a['pending'])]); }
    case 'article.new': { $a = new_article(); $a['author'] = BRAND; save_article($a); out(['article' => $a]); }
    case 'slug.check': { $s = slugify((string)($in['slug'] ?? '')); out(['slug' => $s, 'error' => reserved_slug($s, $in['id'] ?? null)]); }
    case 'article.save': { $a = get_article((string)$in['id']);
      if ($a['status'] === 'published') { /* live article: keep edits aside until the owner clicks Update Live Article */
        $base = $a['pending'] ?? array_diff_key($a, ['pending' => 1]); $p = merge_fields($base, $in); $warn = null;
        if ($p['slug'] !== $a['slug'] && ($e = reserved_slug($p['slug'], $a['id']))) { $p['slug'] = $a['slug']; $warn = $e; }
        unset($p['pending']); $a['pending'] = $p; save_article($a); out(['ok' => true, 'article' => $p, 'warning' => $warn, 'pending' => true]); }
      $a = merge_fields($a, $in); if ($a['slug'] !== '' && ($e = reserved_slug($a['slug'], $a['id']))) { $a['slug'] = ''; $slugErr = $e; }
      save_article($a); out(['ok' => true, 'article' => $a, 'warning' => $slugErr ?? null]); }
    case 'import': { $w = []; $html = sanitize_html((string)($in['html'] ?? ''), $w); out(['html' => $html, 'warnings' => $w]); }
    case 'article.publish': {
      $prev = get_article((string)$in['id']); $a = merge_fields($prev['pending'] ?? $prev, $in); unset($a['pending']); $a['id'] = $prev['id']; $a['created'] = $prev['created'];
      if ($a['slug'] === '' && $a['title'] !== '') $a['slug'] = slugify($a['title']);
      $p = validate_publish($a); if ($p) throw new ApiError('Almost there — please fix the following before publishing.', 422, ['problems' => $p]);
      $redBefore = jread(DATA_DIR . '/redirects.json', []); $red = $redBefore;
      if ($prev['status'] === 'published' && $prev['publishedSlug'] && $prev['publishedSlug'] !== $a['slug']) $red[$prev['publishedSlug']] = $a['slug'];
      $a['status'] = 'published'; $a['publishedSlug'] = $a['slug']; $a['firstPublished'] = $a['firstPublished'] ?? date('c');
      try { jwrite(DATA_DIR . '/redirects.json', $red); save_article($a); rebuild_site(); }
      catch (Throwable $e) { jwrite(DATA_DIR . '/redirects.json', $redBefore); jwrite(DATA_DIR . '/articles/' . $prev['id'] . '.json', $prev);
        try { rebuild_site(); } catch (Throwable $e2) {} throw new ApiError('Publishing did not complete, so nothing was changed. Your draft is safe — please try again. (' . $e->getMessage() . ')', 500); }
      $a = get_article($a['id']); out(['ok' => true, 'article' => $a, 'url' => abs_url("blog/{$a['slug']}.html"), 'live' => true]); }
    case 'article.status': {
      $a = get_article((string)$in['id']); $to = (string)($in['to'] ?? ''); if ($to !== 'delete' && !empty($a['pending'])) { $a = $a['pending'] + ['pending' => null]; unset($a['pending']); } $map = ['unpublish' => 'draft', 'archive' => 'archived', 'trash' => 'trashed', 'restore' => 'draft'];
      if ($to === 'delete') { if ($a['status'] !== 'trashed') throw new ApiError('Only articles in Trash can be deleted permanently.'); unlink(DATA_DIR . '/articles/' . $a['id'] . '.json'); out(['ok' => true]); }
      if (!isset($map[$to])) throw new ApiError('Unknown action.'); $wasArt = get_article($a['id']); $was = $wasArt['status']; $a['status'] = $map[$to]; save_article($a);
      if ($was === 'published') { try { rebuild_site(); } catch (Throwable $e) { jwrite(DATA_DIR . '/articles/' . $wasArt['id'] . '.json', $wasArt); throw new ApiError('Could not remove the article from the website: ' . $e->getMessage(), 500); } }
      out(['ok' => true, 'article' => $a]); }
    case 'media.list': {
      $idx = media_index(); foreach (glob(ROOT . '/assets/img/blog/*/*.*') ?: [] as $f) { $r = ltrim(substr($f, strlen(ROOT)), '/'); $idx[$r] ??= ['alt' => '', 'caption' => '', 'name' => basename($f), 'w' => 0, 'h' => 0, 'size' => filesize($f), 'uploaded' => date('c', filemtime($f))]; }
      $use = media_usage(); $o = []; foreach ($idx as $src => $m) { if (!is_file(ROOT . '/' . $src)) continue; $o[] = ['src' => $src] + $m + ['usedBy' => $use[$src] ?? []]; }
      usort($o, fn($a, $b) => strcmp($b['uploaded'], $a['uploaded'])); out(['media' => $o]); }
    case 'media.update': { $idx = media_index(); $s = (string)$in['src']; if (!isset($idx[$s])) throw new ApiError('Image not found.', 404);
      $idx[$s]['alt'] = trim(strip_tags((string)($in['alt'] ?? ''))); $idx[$s]['caption'] = trim(strip_tags((string)($in['caption'] ?? ''))); jwrite(DATA_DIR . '/media.json', $idx); out(['ok' => true]); }
    case 'media.delete': { $s = (string)$in['src']; if (!preg_match('#^assets/img/blog/\d{4}/[\w.-]+$#', $s)) throw new ApiError('Invalid image.');
      $use = media_usage()[$s] ?? []; if ($use) throw new ApiError('This image is used by: ' . implode(', ', array_column($use, 'title')) . '. Remove it from those articles first.', 409);
      @unlink(ROOT . '/' . $s); $idx = media_index(); unset($idx[$s]); jwrite(DATA_DIR . '/media.json', $idx); out(['ok' => true]); }
    case 'cat.save': {
      $cats = categories(); $label = trim(strip_tags((string)($in['label'] ?? ''))); if (mb_strlen($label) < 3) throw new ApiError('Please enter a category name.');
      if (!empty($in['id'])) { foreach ($cats as &$c) if ($c['id'] === $in['id']) $c['label'] = $label; unset($c); }
      else { $svc = $in['service'] ?: null; if ($svc && !is_file(ROOT . '/' . $svc)) throw new ApiError('Please choose an existing page.');
        $id = slugify($label); if (isset(cat_map()[$id])) throw new ApiError('That category already exists.');
        $cats[] = ['id' => $id, 'label' => $label, 'service' => $svc, 'hub' => null, 'icon' => 'bi-journal-text', 'img' => 'assets/img/pk2.jpeg', 'locked' => false]; }
      jwrite(DATA_DIR . '/categories.json', $cats); try { rebuild_site(); } catch (Throwable $e) {} out(['categories' => $cats]); }
    case 'users.list': out(['users' => array_map(fn($u) => ['id' => $u['id'], 'name' => $u['name'], 'email' => $u['email']], users())]);
    case 'users.add': add_user((string)$in['email'], (string)($in['name'] ?? ''), (string)$in['password']); out(['ok' => true]);
    case 'users.remove': { $us = users(); if (count($us) < 2) throw new ApiError('You cannot remove the only administrator.'); if ($in['id'] === $me['id']) throw new ApiError('You cannot remove yourself.');
      jwrite(DATA_DIR . '/users.json', array_values(array_filter($us, fn($u) => $u['id'] !== $in['id']))); out(['ok' => true]); }
    case 'users.password': { if (!password_verify((string)$in['current'], $me['hash'])) throw new ApiError('Current password is not correct.'); if (strlen((string)$in['new']) < 10) throw new ApiError('New password must be at least 10 characters.');
      $us = users(); foreach ($us as &$u) if ($u['id'] === $me['id']) $u['hash'] = password_hash((string)$in['new'], PASSWORD_DEFAULT); unset($u); jwrite(DATA_DIR . '/users.json', $us); out(['ok' => true]); }
    default: throw new ApiError('Unknown request.', 404);
  }
} catch (ApiError $e) { out(['ok' => false, 'error' => $e->getMessage()] + $e->extra, $e->status);
} catch (Throwable $e) { error_log('[rt-admin] ' . $e); out(['ok' => false, 'error' => 'Something went wrong on the server. Please try again.'], 500); }
