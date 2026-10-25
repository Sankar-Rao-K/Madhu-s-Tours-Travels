<?php
declare(strict_types=1);
require __DIR__ . '/lib/core.php'; require __DIR__ . '/lib/images.php';
init_data(); start_session(); header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
try { if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new ApiError('Not allowed.', 405); require_csrf(); require_user();
  if (empty($_FILES['file'])) throw new ApiError('No file was received. The photo may be too large for the server.');
  $r = process_upload($_FILES['file']); echo json_encode(['ok' => true, 'media' => $r]);
} catch (ApiError $e) { http_response_code($e->status); echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) { error_log('[rt-admin] ' . $e); http_response_code(500); echo json_encode(['ok' => false, 'error' => 'Upload failed. Please try again.']); }
