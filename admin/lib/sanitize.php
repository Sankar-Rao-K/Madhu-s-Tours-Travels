<?php
declare(strict_types=1);
/* Allow-list HTML sanitizer. Anything not explicitly allowed is removed (dangerous tags with their content). */
const SAN_DROP = ['script','style','noscript','iframe','object','embed','form','input','button','select','textarea','nav','header','footer','aside','svg','canvas','video','audio','link','meta','title','head','template','dialog','applet','base','frame','frameset','map','math'];
const SAN_MAP = ['h1'=>'h2','h5'=>'h4','h6'=>'h4','b'=>'strong','i'=>'em'];
const SAN_ALLOW = ['h2','h3','h4','p','br','strong','em','ul','ol','li','a','blockquote','table','thead','tbody','tfoot','tr','th','td','img','figure','figcaption','hr'];

function html_extract(string $html): string {
  if (!preg_match('/<(html|body|article|main)\b/i', $html)) return $html;
  libxml_use_internal_errors(true);
  $d = new DOMDocument(); $d->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NONET);
  $x = new DOMXPath($d);
  foreach (['//article', '//main', '//*[@role="main"]', '//body'] as $q) { $n = $x->query($q);
    if ($n && $n->length) { $o = ''; foreach ($n->item(0)->childNodes as $c) $o .= $d->saveHTML($c); return $o; } }
  return $html;
}
function san_href(string $h): ?string {
  $h = trim(html_entity_decode($h, ENT_QUOTES | ENT_HTML5, 'UTF-8')); $probe = strtolower(preg_replace('/[\x00-\x20]+/', '', $h));
  if ($probe === '') return null;
  if (preg_match('#^(https?:|mailto:|tel:)#', $probe)) return $h;
  if (preg_match('#^[a-z][a-z0-9+.\-]*:#', $probe)) return null;          // javascript:, data:, vbscript: ...
  return $h; }
function san_img(string $s): ?string {
  $s = trim(html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
  if (preg_match('#(?:^|/)(assets/img/blog/[A-Za-z0-9_./-]+)$#', $s, $m) && !str_contains($s, '..' . '/..')) return $m[1];
  if (preg_match('#^https://[^\s"\'<>]+$#i', $s)) return $s;
  return null; }
function san_ser(DOMNode $n, array &$w): string {
  $o = '';
  foreach ($n->childNodes as $c) {
    if ($c->nodeType === XML_TEXT_NODE) { $o .= h($c->nodeValue); continue; }
    if ($c->nodeType !== XML_ELEMENT_NODE) continue;
    $t = strtolower($c->nodeName);
    if (in_array($t, SAN_DROP, true)) { if (in_array($t, ['script','iframe','form','object','embed'], true)) $w[$t] = true; continue; }
    $t = SAN_MAP[$t] ?? $t; $in = san_ser($c, $w);
    if (!in_array($t, SAN_ALLOW, true)) { $o .= $in; continue; }
    switch ($t) {
      case 'br': case 'hr': $o .= "<$t>"; break;
      case 'img':
        $src = san_img((string)$c->getAttribute('src')); if (!$src) { $w['badimg'] = true; break; }
        $o .= '<img src="' . h($src) . '" alt="' . h($c->getAttribute('alt')) . '" loading="lazy">'; break;
      case 'a':
        $hr = san_href((string)$c->getAttribute('href'));
        if ($hr === null) { $w['badlink'] = true; $o .= $in; break; }
        $ext = preg_match('#^https?://#i', $hr) ? ' rel="noopener"' : '';
        $o .= '<a href="' . h($hr) . '" class="inline-service-link"' . $ext . '>' . $in . '</a>'; break;
      case 'td': case 'th':
        $at = ''; foreach (['colspan','rowspan'] as $k) { $v = (int)$c->getAttribute($k); if ($v > 1 && $v < 20) $at .= " $k=\"$v\""; }
        $o .= "<$t$at>$in</$t>"; break;
      default: $o .= "<$t>$in</$t>";
    } }
  return $o; }
function sanitize_html(string $html, array &$warnings = []): string {
  $html = preg_replace('/<\?.*?\?>/s', '', $html); $html = preg_replace('/<!--.*?-->/s', '', $html);
  $html = html_extract($html);
  libxml_use_internal_errors(true);
  $d = new DOMDocument(); $d->loadHTML('<?xml encoding="utf-8" ?><div id="rt">' . $html . '</div>', LIBXML_NONET | LIBXML_HTML_NODEFDTD);
  $root = $d->getElementById('rt') ?? $d->documentElement; $w = [];
  $out = san_ser($root, $w);
  $out = preg_replace('#<(p|h2|h3|h4|li|blockquote)>(\s|&nbsp;|\x{00A0})*</\1>#u', '', $out);
  $out = preg_replace('/\n{3,}/', "\n\n", trim($out));
  if (!preg_match('#^\s*<(h2|h3|h4|p|ul|ol|blockquote|table|figure|img|hr)#i', $out) && $out !== '') $out = '<p>' . $out . '</p>';
  if (isset($w['script']) || isset($w['iframe']) || isset($w['object'])) $warnings[] = 'Scripts or embedded content were found and safely removed.';
  if (isset($w['form'])) $warnings[] = 'A form was found and removed.';
  if (isset($w['badlink'])) $warnings[] = 'Some unsafe links were removed.';
  if (isset($w['badimg'])) $warnings[] = 'Some images could not be used (only photos uploaded here or secure https images are allowed).';
  return $out; }

/* Find internal links / images that point to pages that do not exist. $extraPages = root-relative paths about to exist. */
function broken_refs(string $html, array $extraPages = []): array {
  $bad = [];
  preg_match_all('#<(?:a href|img src)="([^"]*)"#', $html, $m);
  $host = parse_url(SITE_URL, PHP_URL_HOST);
  foreach (array_unique($m[1]) as $u) {
    $u = html_entity_decode($u, ENT_QUOTES | ENT_HTML5);
    if (preg_match('#^(mailto:|tel:|\#)#i', $u)) continue;
    if (preg_match('#^https?://#i', $u)) { if (strcasecmp((string)parse_url($u, PHP_URL_HOST), (string)$host) !== 0 && strcasecmp((string)parse_url($u, PHP_URL_HOST), 'www.' . $host) !== 0) continue; $u = (string)parse_url($u, PHP_URL_PATH); }
    $p = preg_replace('#[?\#].*$#', '', $u); $p = preg_replace('#^(\.\./|\./|/)+#', '', $p);
    if ($p === '') continue; if (str_ends_with($p, '/')) $p .= 'index.html';
    if (in_array($p, $extraPages, true) || is_file(ROOT . '/' . $p)) continue;
    $bad[] = $u; }
  return $bad; }
