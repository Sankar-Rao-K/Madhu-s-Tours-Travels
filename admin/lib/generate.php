<?php
declare(strict_types=1);
const MK_A = '<!--RT-ARTICLES-->'; const MK_B = '<!--/RT-ARTICLES-->';

function published(): array {
  $p = array_values(array_filter(all_articles(), fn($a) => $a['status'] === 'published'));
  usort($p, fn($a, $b) => [$b['date'], $b['modified']] <=> [$a['date'], $a['modified']]); return $p; }
function fmt_date(string $d): string { $t = DateTime::createFromFormat('Y-m-d', $d); return $t ? $t->format('j F Y') : $d; }
function backup_original(string $rel): void {
  $dst = DATA_DIR . '/backup/original/' . str_replace('/', '__', $rel);
  if (!is_file($dst) && is_file(ROOT . '/' . $rel)) { ensure_dir(dirname($dst)); copy(ROOT . '/' . $rel, $dst); } }
function cover_of(array $a, array $cm): string { return $a['cover']['src'] ?? ($cm[$a['primary']]['img'] ?? 'assets/img/pk2.jpeg'); }
function cover_alt(array $a): string { return $a['cover']['alt'] ?? $a['title']; }
function abs_url(string $rel): string { return rtrim(SITE_URL, '/') . '/' . ltrim($rel, '/'); }

/* ---- rewrite stored body paths so they work from /blog/<slug>.html ---- */
function body_for_page(string $html): string {
  $host = preg_quote((string)parse_url(SITE_URL, PHP_URL_HOST), '#');
  $html = preg_replace_callback('#(href|src)="([^"]*)"#', function ($m) use ($host) {
    $u = $m[2];
    if (preg_match('#^(mailto:|tel:|\#)#i', $u)) return $m[0];
    if (preg_match('#^https?://(www\.)?' . $host . '(/.*)?$#i', $u, $x)) $u = ltrim($x[2] ?? '', '/');
    elseif (preg_match('#^https?://#i', $u)) return $m[0];
    $u = preg_replace('#^(\.\./|\./|/)+#', '', $u);
    return $m[1] . '="../' . ($u === '' ? 'index.html' : $u) . '"'; }, $html);
  return $html; }

function render_article(array $a, bool $preview = false): string {
  $cm = cat_map(); $tpl = file_get_contents(ROOT . '/blog/car-rentals.html');
  $i = strpos($tpl, '<main>'); $j = strpos($tpl, '</main>'); if ($i === false || $j === false) throw new ApiError('The website template could not be read.', 500);
  $head = substr($tpl, 0, $i); $tail = substr($tpl, $j);
  foreach (['#<title>.*?</title>#s', '#<meta[^>]*name="(description|keywords|robots|twitter:[a-z:]+)"[^>]*>#i', '#<link[^>]*rel="canonical"[^>]*>#i', '#<meta[^>]*property="og:[^"]*"[^>]*>#i', '#<script type="application/ld\+json">.*?</script>#s'] as $re)
    $head = preg_replace($re, '', $head);
  [$st, $sd] = seo_defaults($a); $url = abs_url("blog/{$a['slug']}.html"); $img = abs_url(cover_of($a, $cm));
  $prim = $cm[$a['primary']] ?? null; $author = $a['author'] ?: BRAND;
  $crumbs = [['Home', abs_url('index.html')], ['Blog', abs_url('blog.html')]]; if ($prim && $prim['hub']) $crumbs[] = [$prim['label'], abs_url($prim['hub'])]; $crumbs[] = [$a['title'], $url];
  $ld = ['@context' => 'https://schema.org', '@graph' => [
    ['@type' => 'BlogPosting', '@id' => $url . '#article', 'headline' => $a['title'], 'description' => $sd, 'image' => [$img],
     'datePublished' => $a['date'] . 'T00:00:00+05:30', 'dateModified' => date('c', strtotime($a['modified'])), 'mainEntityOfPage' => $url, 'url' => $url,
     'author' => $author === BRAND ? ['@type' => 'Organization', 'name' => BRAND, 'url' => abs_url('')] : ['@type' => 'Person', 'name' => $author],
     'publisher' => ['@type' => 'Organization', 'name' => BRAND, 'url' => abs_url(''), 'logo' => ['@type' => 'ImageObject', 'url' => abs_url('assets/img/logo.png')]],
     'inLanguage' => 'en-IN'] + ($a['tags'] ? ['keywords' => implode(', ', $a['tags'])] : []),
    ['@type' => 'BreadcrumbList', 'itemListElement' => array_map(fn($c, $n) => ['@type' => 'ListItem', 'position' => $n + 1, 'name' => $c[0], 'item' => $c[1]], $crumbs, array_keys($crumbs))]]];
  $meta = '<title>' . h($st) . '</title><meta name="description" content="' . h($sd) . '"/>'
    . ($a['tags'] ? '<meta name="keywords" content="' . h(implode(', ', $a['tags'])) . '"/>' : '')
    . '<meta name="robots" content="' . ($preview ? 'noindex,nofollow' : 'index,follow,max-image-preview:large') . '"/>'
    . ($preview ? '' : '<link rel="canonical" href="' . h($url) . '"/>')
    . '<meta property="og:type" content="article"/><meta property="og:title" content="' . h($st) . '"/><meta property="og:description" content="' . h($sd) . '"/>'
    . '<meta property="og:url" content="' . h($url) . '"/><meta property="og:image" content="' . h($img) . '"/><meta property="og:site_name" content="' . h(BRAND) . '"/>'
    . '<meta property="article:published_time" content="' . h($a['date']) . '"/><meta property="article:modified_time" content="' . h(date('c', strtotime($a['modified']))) . '"/>'
    . '<meta name="twitter:card" content="summary_large_image"/><meta name="twitter:title" content="' . h($st) . '"/><meta name="twitter:description" content="' . h($sd) . '"/><meta name="twitter:image" content="' . h($img) . '"/>'
    . '<link href="../assets/css/blog-articles.css" rel="stylesheet">'
    . '<script type="application/ld+json">' . json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';
  if ($preview) { $base = $GLOBALS['RT_BASE'] ?? ''; $meta = '<base href="' . h($base) . 'blog/">' . $meta; }
  $head = preg_replace('#</head>#i', $meta . '</head>', $head, 1);
  /* breadcrumb + body */
  $bc = '<a href="../index.html">Home</a><i class="bi bi-chevron-right"></i><a href="../blog.html">Blog</a><i class="bi bi-chevron-right"></i>'
      . ($prim && $prim['hub'] ? '<a href="../' . h($prim['hub']) . '">' . h($prim['label']) . '</a><i class="bi bi-chevron-right"></i>' : '') . '<span>' . h($a['title']) . '</span>';
  $svc = []; foreach (array_merge([$a['primary']], $a['related']) as $cid) { $c = $cm[$cid] ?? null; if ($c && $c['service']) $svc[$c['service']] = $c['label']; }
  $rel = ''; if ($svc) { $l = []; foreach ($svc as $p => $lab) $l[] = '<a href="../' . h($p) . '" class="inline-service-link">' . h($lab) . '</a>'; $rel = '<p class="rt-related"><strong>Related services:</strong> ' . implode(' &middot; ', $l) . '</p>'; }
  $main = '<main><section class="page-hero"><div class="container"><div class="breadcrumb-row">' . $bc . '</div><h1>' . h($a['title']) . '</h1>' . ($a['excerpt'] ? '<p>' . h($a['excerpt']) . '</p>' : '') . '</div></section>'
    . '<section class="section"><div class="container"><div class="article-content rt-article">'
    . '<div class="rt-meta"><i class="bi bi-calendar3"></i> ' . h(fmt_date($a['date'])) . ' &middot; ' . h($author) . ($prim ? ' &middot; ' . h($prim['label']) : '') . '</div>'
    . (!empty($a['cover']['src']) ? '<figure class="rt-cover"><img src="../' . h($a['cover']['src']) . '" alt="' . h(cover_alt($a)) . '"></figure>' : '')
    . body_for_page($a['body']) . $rel . '</div></div></section>';
  return $head . $main . $tail; }

/* ---- listing cards ---- */
function card_html(array $a, string $rel, array $cm): string {
  $c = $cm[$a['primary']] ?? ['label' => 'Travel', 'icon' => 'bi-journal-text'];
  return '<a class="blog-card" href="' . $rel . 'blog/' . h($a['slug']) . '.html"><div class="blog-img"><img alt="' . h(cover_alt($a)) . '" loading="lazy" src="' . $rel . h(cover_of($a, $cm)) . '"/></div>'
   . '<div class="blog-card-body"><div class="blog-meta"><i class="bi ' . h($c['icon']) . '"></i> ' . h($c['label']) . ' &middot; ' . h(fmt_date($a['date'])) . '</div><h3>' . h($a['title']) . '</h3><p>' . h($a['excerpt']) . '</p>'
   . '<span class="blog-read">Read Article <i class="bi bi-arrow-right"></i></span></div></a>'; }
function section_html(array $list, string $rel, string $eyebrow, string $title, int $page = 0): string {
  if (!$list) return ''; $cm = cat_map(); $cards = '';
  foreach ($list as $n => $a) { $c = card_html($a, $rel, $cm); if ($page && $n >= $page) $c = preg_replace('#^<a #', '<a hidden data-more ', $c); $cards .= $c; }
  $btn = ($page && count($list) > $page) ? '<div class="text-center" style="margin-top:28px;"><button type="button" class="btn btn-outline" id="rt-more">Load more articles</button></div><script>document.getElementById("rt-more").addEventListener("click",function(){var h=document.querySelectorAll("a[data-more][hidden]"),i=0;for(;i<h.length&&i<9;i++)h[i].hidden=false;if(h.length<=9)this.remove();});</script>' : '';
  return '<link href="' . $rel . 'assets/css/blog-articles.css" rel="stylesheet"><section class="section rt-articles" data-rt-articles><div class="container"><div class="section-head center"><div class="eyebrow" style="justify-content:center;">' . h($eyebrow) . '</div><h2>' . h($title) . '</h2></div><div class="blog-grid">' . $cards . '</div>' . $btn . '</div></section>'; }

/* ---- inject / replace marker blocks in existing pages (originals backed up once) ---- */
function ensure_marker(string $rel): void {
  $f = ROOT . '/' . $rel; if (!is_file($f)) return; $s = (string)file_get_contents($f); if (str_contains($s, MK_A)) return;
  backup_original($rel); $mk = MK_A . MK_B;
  if (preg_match('#<section[^>]*article-guides-section[^>]*>.*?</section>#s', $s, $m, PREG_OFFSET_CAPTURE)) $s = substr_replace($s, $mk, $m[0][1] + strlen($m[0][0]), 0);
  elseif (preg_match('#<section[^>]*id="faq"[^>]*>#', $s, $m, PREG_OFFSET_CAPTURE) || preg_match('#<section[^>]*related-gallery-section[^>]*>#', $s, $m, PREG_OFFSET_CAPTURE)) $s = substr_replace($s, $mk, $m[0][1], 0);
  elseif (($p = strpos($s, '</main>')) !== false) $s = substr_replace($s, $mk, $p, 0); else return;
  atomic_write($f, $s); }
function set_marker(string $rel, string $html): void {
  $f = ROOT . '/' . $rel; $s = (string)file_get_contents($f); $a = strpos($s, MK_A); $b = strpos($s, MK_B); if ($a === false || $b === false) return;
  $n = substr($s, 0, $a + strlen(MK_A)) . $html . substr($s, $b); if ($n !== $s) atomic_write($f, $n); }

function update_sitemap(array $pubs, array $prevSlugs): void {
  $f = ROOT . '/sitemap.xml'; if (!is_file($f)) return; backup_original('sitemap.xml'); $s = (string)file_get_contents($f);
  $all = array_unique(array_merge($prevSlugs, array_column($pubs, 'slug')));
  foreach ($all as $sl) $s = preg_replace('#[ \t]*<url><loc>' . preg_quote(abs_url("blog/$sl.html"), '#') . '</loc>.*?</url>\R?#s', '', $s);
  $add = ''; foreach ($pubs as $a) $add .= '  <url><loc>' . h(abs_url("blog/{$a['slug']}.html")) . '</loc><lastmod>' . date('Y-m-d', strtotime($a['modified'])) . "</lastmod></url>\n";
  $s = str_replace('</urlset>', $add . '</urlset>', $s); atomic_write($f, $s); }
function update_htaccess(array $red, array $gone): void {
  $f = ROOT . '/.htaccess'; $s = is_file($f) ? (string)file_get_contents($f) : ''; $B = '# RT-BLOG-BEGIN'; $E = '# RT-BLOG-END';
  $s = preg_replace('/\R?' . $B . '.*?' . $E . '\R?/s', "\n", $s); $blk = '';
  foreach ($red as $o => $n) $blk .= "Redirect 301 /blog/$o.html /blog/$n.html\n"; foreach ($gone as $g) $blk .= "Redirect gone /blog/$g.html\n";
  if ($blk === '' && !is_file($f)) return; if (is_file($f)) backup_original('.htaccess');
  $s = rtrim($s) . ($blk ? "\n$B\n$blk$E\n" : "\n"); atomic_write($f, ltrim($s)); }

function rebuild_site(): array {
  return with_lock(function () {
    $pubs = published(); $cm = cat_map(); $gj = jread(DATA_DIR . '/generated.json', ['slugs' => []]); $prev = $gj['slugs'];
    foreach ($pubs as $a) { $html = render_article($a); $f = ROOT . "/blog/{$a['slug']}.html"; atomic_write($f, $html);
      if (!is_file($f) || filesize($f) < 2000 || !str_contains((string)file_get_contents($f), 'rel="canonical" href="' . abs_url("blog/{$a['slug']}.html") . '"')) throw new ApiError('The article page could not be created on the server.', 500); }
    $cur = array_column($pubs, 'slug'); $red = jread(DATA_DIR . '/redirects.json', []); $gone = jread(DATA_DIR . '/gone.json', []);
    foreach (array_diff($prev, $cur) as $old) { @unlink(ROOT . "/blog/$old.html"); if (!isset($red[$old]) && !in_array($old, $gone, true)) $gone[] = $old; }
    $gone = array_values(array_diff($gone, $cur)); foreach ($red as $o => $n) if (in_array($o, $cur, true)) unset($red[$o]);
    foreach ($red as $o => $n) if (!in_array($n, $cur, true)) { unset($red[$o]); if (!in_array($o, $gone, true)) $gone[] = $o; }
    jwrite(DATA_DIR . '/redirects.json', $red); jwrite(DATA_DIR . '/gone.json', $gone);
    /* listings */
    $pages = ['blog.html' => [$pubs, '', 'Latest articles', 'Latest Travel Articles', 9], 'index.html' => [array_slice($pubs, 0, 3), '', 'From our blog', 'Latest Travel Articles', 0]];
    foreach ($cm as $c) {
      foreach (['service', 'hub'] as $k) { if (empty($c[$k])) continue; $p = $c[$k]; if (!isset($pages[$p])) $pages[$p] = [[], '../', 'Travel guides', 'Travel Articles & Guides', 0];
        if (!isset($pages[$p]['ids'])) $pages[$p]['ids'] = []; $pages[$p]['ids'][] = $c['id']; } }
    foreach ($pages as $rel => $cfg) {
      if (isset($cfg['ids'])) { $cfg[0] = array_slice(array_values(array_filter($pubs, fn($a) => array_intersect(array_merge([$a['primary']], $a['related']), $cfg['ids']))), 0, 12); }
      if (!is_file(ROOT . '/' . $rel)) continue; ensure_marker($rel);
      $title = $cfg[3]; if (str_contains((string)file_get_contents(ROOT . '/' . $rel), 'article-guides-section')) $title = 'More Travel Articles';
      set_marker($rel, section_html($cfg[0], $cfg[1], $cfg[2], $title, $cfg[4])); }
    update_sitemap($pubs, $prev); update_htaccess($red, $gone);
    jwrite(DATA_DIR . '/generated.json', ['slugs' => $cur, 'at' => date('c')]);
    return ['published' => count($pubs)]; }); }
