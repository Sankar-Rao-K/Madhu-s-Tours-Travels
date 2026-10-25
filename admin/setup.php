<?php
declare(strict_types=1);
require __DIR__ . '/lib/core.php'; init_data(); start_session(); header('X-Robots-Tag: noindex'); header('Cache-Control: no-store');
$key = __DIR__ . '/setup.key'; $msg = ''; $done = false;
function page(string $body): void { echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>Admin setup</title><link rel="stylesheet" href="assets/admin.css"><body class="auth-body"><div class="auth-card">' . $body . '</div></body></html>'; exit; }
if (users()) { http_response_code(403); page('<h1>Setup is already complete</h1><p>An administrator already exists. <a href="./">Go to sign in</a>.</p>'); }
if (!is_file($key)) { page('<h1>First-time setup</h1><p>For security, setup needs a one-time key. In cPanel File Manager, create a file named <code>setup.key</code> inside the <code>admin</code> folder and type any long random text inside it (20+ characters). Then reload this page.</p>'); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try { if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) throw new ApiError('Session expired — reload the page.');
    rate_check('setup:' . ($_SERVER['REMOTE_ADDR'] ?? '?'));
    if (!hash_equals(trim((string)file_get_contents($key)), trim((string)($_POST['key'] ?? '')))) { rate_check('setup:' . ($_SERVER['REMOTE_ADDR'] ?? '?'), true); throw new ApiError('The setup key does not match the text inside setup.key.'); }
    add_user(trim((string)$_POST['email']), trim((string)$_POST['name']), (string)$_POST['password']); @unlink($key); $done = true;
  } catch (ApiError $e) { $msg = $e->getMessage(); } }
if ($done) page('<h1>All set</h1><p>Your administrator account was created and the setup key was deleted. <a class="btn" href="./">Sign in</a></p>');
page('<h1>Create the first administrator</h1>' . ($msg ? '<p class="err">' . h($msg) . '</p>' : '') . '<form method="post"><input type="hidden" name="csrf" value="' . h($_SESSION['csrf']) . '">
<label>Setup key (text from setup.key)<input name="key" type="password" required autocomplete="off"></label><label>Your name<input name="name" required></label>
<label>Email (used to sign in)<input name="email" type="email" required></label><label>Password (10+ characters)<input name="password" type="password" minlength="10" required autocomplete="new-password"></label>
<button class="btn" type="submit">Create administrator</button></form>');
