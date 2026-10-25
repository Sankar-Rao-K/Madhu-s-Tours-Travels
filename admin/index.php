<?php
declare(strict_types=1);
require __DIR__ . '/lib/core.php'; init_data(); start_session();
header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https:; style-src 'self' 'unsafe-inline'; script-src 'self'; frame-ancestors 'none'; base-uri 'none'; form-action 'self'");
header('X-Robots-Tag: noindex, nofollow'); header('Cache-Control: no-store');
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>Blog Admin · Rajahmundry Tourism</title><link rel="icon" href="../assets/img/favicon.png"><link rel="stylesheet" href="assets/admin.css?v=1"></head>
<body><div id="app"><p class="loading">Loading…</p></div><script src="assets/admin.js?v=1"></script></body></html>
