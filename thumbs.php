<?php
// thumbs.php – einfacher Thumbnail-Generator (GD)

function ensureDir(string $dir): void {
    if (!is_dir($dir)) mkdir($dir, 0755, true);
}

function imageCreateFromAny(string $path) {
    $info = @getimagesize($path);
    if (!$info) return null;
    switch ($info[2]) {
        case IMAGETYPE_JPEG: return imagecreatefromjpeg($path);
        case IMAGETYPE_PNG:  return imagecreatefrompng($path);
        case IMAGETYPE_GIF:  return imagecreatefromgif($path);
        case IMAGETYPE_WEBP: return function_exists('imagecreatefromwebp') ? imagecreatefromwebp($path) : null;
        default: return null;
    }
}

// EXIF-Orientierung bei JPEG beachten
function fixOrientation($img, string $path) {
    if (!function_exists('exif_read_data')) return $img;
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if ($ext !== 'jpg' && $ext !== 'jpeg') return $img;

    $exif = @exif_read_data($path);
    if (!$exif || !isset($exif['Orientation'])) return $img;

    switch ($exif['Orientation']) {
        case 3: $img = imagerotate($img, 180, 0); break;
        case 6: $img = imagerotate($img, -90, 0); break;
        case 8: $img = imagerotate($img, 90, 0); break;
    }
    return $img;
}

/**
 * Erzeugt (oder liefert) Thumbnail-Pfad.
 * Speichert Thumbs als JPEG.
 *
 * @param string $src      Absoluter Pfad zum Original
 * @param string $thumbDir Absolutes Zielverzeichnis
 * @param int    $maxW     max. Breite
 * @param int    $maxH     max. Höhe
 * @param int    $quality  JPEG-Qualität (1–100)
 * @return string|null     Absoluter Pfad zum Thumbnail oder null bei Fehler
 */
function makeThumb(string $src, string $thumbDir, int $maxW = 600, int $maxH = 600, int $quality = 82): ?string {
    if (!file_exists($src)) return null;
    ensureDir($thumbDir);

    $base = pathinfo($src, PATHINFO_BASENAME);
    $thumbPath = rtrim($thumbDir, '/').'/'.preg_replace('/\.(jpe?g|png|gif|webp)$/i', '.jpg', $base);

    // Cache: vorhandenen Thumb wiederverwenden, wenn aktueller
    if (file_exists($thumbPath) && filemtime($thumbPath) >= filemtime($src)) return $thumbPath;

    $img = imageCreateFromAny($src);
    if (!$img) return null;

    $img = fixOrientation($img, $src);

    $w = imagesx($img);
    $h = imagesy($img);
    if ($w <= 0 || $h <= 0) { imagedestroy($img); return null; }

    $scale = min($maxW / $w, $maxH / $h, 1);
    $nw = (int) floor($w * $scale);
    $nh = (int) floor($h * $scale);

    $thumb = imagecreatetruecolor($nw, $nh);
    // Weißer Hintergrund (für Transparenzquellen)
    $white = imagecolorallocate($thumb, 255, 255, 255);
    imagefill($thumb, 0, 0, $white);

    imagecopyresampled($thumb, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imagejpeg($thumb, $thumbPath, $quality);

    imagedestroy($thumb);
    imagedestroy($img);

    return $thumbPath;
}
?>
