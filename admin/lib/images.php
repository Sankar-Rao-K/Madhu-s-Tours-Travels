<?php
declare(strict_types=1);
function process_upload(array $f): array {
  $codes = [UPLOAD_ERR_INI_SIZE => 'The file is larger than the server allows.', UPLOAD_ERR_FORM_SIZE => 'The file is too large.', UPLOAD_ERR_PARTIAL => 'The upload was interrupted. Please try again.', UPLOAD_ERR_NO_FILE => 'No file was chosen.'];
  if (($f['error'] ?? 4) !== UPLOAD_ERR_OK) throw new ApiError($codes[$f['error'] ?? 4] ?? 'Upload failed.');
  if ($f['size'] > 10 * 1024 * 1024) throw new ApiError('This photo is larger than 10 MB. Please choose a smaller one.');
  $info = @getimagesize($f['tmp_name']);
  $types = [IMAGETYPE_JPEG => 'jpeg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
  if (!$info || !isset($types[$info[2]])) throw new ApiError('Only JPG, PNG or WebP photos can be uploaded.');
  if (!function_exists('imagecreatetruecolor')) throw new ApiError('The server is missing the image library (PHP GD). Ask your hosting support to enable it.', 500);
  $im = match ($types[$info[2]]) { 'jpeg' => @imagecreatefromjpeg($f['tmp_name']), 'png' => @imagecreatefrompng($f['tmp_name']), 'webp' => @imagecreatefromwebp($f['tmp_name']) };
  if (!$im) throw new ApiError('This photo could not be read. Try another file.');
  if ($types[$info[2]] === 'jpeg' && function_exists('exif_read_data')) { $ex = @exif_read_data($f['tmp_name']); $o = $ex['Orientation'] ?? 1;
    $im = match ($o) { 3 => imagerotate($im, 180, 0), 6 => imagerotate($im, -90, 0), 8 => imagerotate($im, 90, 0), default => $im }; }
  $w = imagesx($im); $h = imagesy($im);
  if ($w > 1600) { $nh = (int)round($h * 1600 / $w); $r = imagecreatetruecolor(1600, $nh); imagealphablending($r, false); imagesavealpha($r, true);
    imagecopyresampled($r, $im, 0, 0, 0, 0, 1600, $nh, $w, $h); $im = $r; $w = 1600; $h = $nh; }
  imagepalettetotruecolor($im); imagealphablending($im, false); imagesavealpha($im, true);
  $base = slugify(pathinfo((string)($f['name'] ?? 'photo'), PATHINFO_FILENAME)) ?: 'photo'; $base = substr($base, 0, 50);
  $hash = substr(sha1_file($f['tmp_name']), 0, 6); $dir = 'assets/img/blog/' . date('Y'); ensure_dir(ROOT . '/' . $dir);
  $ext = function_exists('imagewebp') ? 'webp' : ($types[$info[2]] === 'png' ? 'png' : 'jpg'); $rel = "$dir/$base-$hash.$ext"; $abs = ROOT . '/' . $rel;
  $ok = $ext === 'webp' ? imagewebp($im, $abs, 82) : ($ext === 'png' ? imagepng($im, $abs, 7) : imagejpeg($im, $abs, 84));
  if (!$ok || !is_file($abs)) throw new ApiError('The photo could not be saved on the server.', 500);
  $idx = media_index(); $idx[$rel] = ['alt' => '', 'caption' => '', 'name' => (string)($f['name'] ?? ''), 'w' => $w, 'h' => $h, 'size' => filesize($abs), 'uploaded' => date('c')];
  jwrite(DATA_DIR . '/media.json', $idx); return ['src' => $rel] + $idx[$rel]; }
