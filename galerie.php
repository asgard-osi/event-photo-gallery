<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/thumbs.php';

/* ============================
   Password gate
   ============================ */
if (isset($_GET['logout'])) {
  unset($_SESSION['galerie_ok']);
  header('Location: galerie.php');
  exit;
}

$show_form = !isset($_SESSION['galerie_ok']);
$error = null;

if ($show_form && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $pw = $_POST['pw'] ?? '';
  if (hash_equals($GALLERY_PASSWORD, $pw)) {
    $_SESSION['galerie_ok'] = true;
    header('Location: galerie.php');
    exit;
  } else {
    $error = 'Wrong password. Please try again.';
  }
}

if ($show_form) {
  ?>
  <!DOCTYPE html>
  <html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?php echo 'Gallery – ' . htmlspecialchars($SITE_TITLE ?? ''); ?></title>
    <style>
      :root { --green:#2c3e2f; --gold:#b8860b; --bg:#fdfdf8; }
      * { box-sizing:border-box; margin:0; padding:0; }
      html { scroll-behavior:smooth; }
      body { font-family:'Georgia', serif; background:var(--bg); color:var(--green); min-height:100svh; display:grid; place-items:center; padding:2rem 1rem; }
      h1 { font-family:'Brush Script MT', cursive; font-size:2.2rem; color:var(--gold); text-align:center; margin-bottom:1rem; }
      .card { width:min(420px, 100%); background:#fff; border:1px solid rgba(44,62,47,.15); border-radius:12px; padding:1.25rem; box-shadow:0 6px 24px rgba(0,0,0,.05); }
      label { display:block; margin:.5rem 0 .25rem; }
      input[type="password"] { width:100%; padding:.7rem .9rem; border:1px solid rgba(44,62,47,.25); border-radius:8px; font-size:1rem; }
      .btn, a.btn { display:inline-block; margin-top:.9rem; border:2px solid var(--green); border-radius:8px; padding:.6rem 1.1rem; cursor:pointer; color:var(--green); background:#fff; font-size:1rem; text-decoration:none; transition:background .3s, color .3s; }
      .btn:hover { background:var(--green); color:#fff; }
      .error { color:#a11; margin-top:.6rem; }
      .row { display:flex; gap:.6rem; flex-wrap:wrap; align-items:center; }
    </style>
  </head>
  <body>
    <div class="card">
      <h1>Gallery</h1>
      <p style="opacity:.85;margin-bottom:.6rem;">Please enter the password to view the photos.</p>
      <form method="post" autocomplete="off">
        <label for="pw">Password</label>
        <input type="password" id="pw" name="pw" required autofocus />
        <?php if ($error): ?><div class="error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <div class="row">
          <button class="btn" type="submit">Unlock</button>
          <a class="btn" href="./">Back to start</a>
        </div>
      </form>
    </div>
  </body>
  </html>
  <?php
  exit;
}

/* ============================
   Author mapping (CSV)
   ============================ */
$authorsCsv = $UPLOAD_DIR . '/_authors.csv';
$authorsMap = [];
if (is_file($authorsCsv) && is_readable($authorsCsv)) {
    $fh = fopen($authorsCsv, 'r');
    if ($fh) {
        while (($line = fgets($fh)) !== false) {
            $parts = explode(';', trim($line));
            if (count($parts) >= 2) {
                $fn = $parts[0];
                $au = $parts[1];
                if ($fn !== '') $authorsMap[$fn] = $au; // key = stored basename
            }
        }
        fclose($fh);
    }
}

/* ============================
   Helpers
   ============================ */
$allowed = ['jpg','jpeg','png','gif','webp'];

function getCaptureTimestamp(string $path): int {
    $fallback = @filemtime($path) ?: 0;
    if (!function_exists('exif_read_data')) return $fallback;
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','tif','tiff'], true)) return $fallback;

    $exif = @exif_read_data($path, 'EXIF', true);
    if (!$exif) return $fallback;

    $candidates = [];
    if (isset($exif['EXIF']['DateTimeOriginal']))  $candidates[] = $exif['EXIF']['DateTimeOriginal'];
    if (isset($exif['EXIF']['DateTimeDigitized'])) $candidates[] = $exif['EXIF']['DateTimeDigitized'];
    if (isset($exif['IFD0']['DateTime']))          $candidates[] = $exif['IFD0']['DateTime'];

    foreach ($candidates as $dt) {
        if (preg_match('/^\d{4}:\d{2}:\d{2} \d{2}:\d{2}:\d{2}$/', $dt)) {
            $norm = str_replace(':', '-', substr($dt, 0, 10)) . substr($dt, 10);
            $ts = strtotime($norm);
            if ($ts !== false) return $ts;
        } else {
            $ts = strtotime($dt);
            if ($ts !== false) return $ts;
        }
    }
    return $fallback;
}

function collectFiles(string $dir, array $allowed): array {
    $paths = [];
    if (is_dir($dir)) {
        foreach ($allowed as $ext) {
            $paths = array_merge($paths, glob($dir . '/*.' . $ext));
            $paths = array_merge($paths, glob($dir . '/*.' . strtoupper($ext)));
        }
    }
    return array_map(function($p){
        return [
            'path'       => $p,
            'name'       => basename($p),
            'capture_ts' => getCaptureTimestamp($p),
            'mtime'      => @filemtime($p) ?: 0,
        ];
    }, $paths);
}

function sortFiles(array &$files, string $mode): void {
    $allowedModes = ['capture_desc','capture_asc','upload_desc','upload_asc'];
    if (!in_array($mode, $allowedModes, true)) $mode = 'capture_desc';
    usort($files, function($a, $b) use ($mode) {
        switch ($mode) {
            case 'capture_asc': $cmp = $a['capture_ts'] <=> $b['capture_ts']; break;
            case 'upload_desc': $cmp = $b['mtime']      <=> $a['mtime'];      break;
            case 'upload_asc':  $cmp = $a['mtime']      <=> $b['mtime'];      break;
            case 'capture_desc':
            default:            $cmp = $b['capture_ts'] <=> $a['capture_ts']; break;
        }
        if ($cmp !== 0) return $cmp;
        return strcmp($a['name'], $b['name']);
    });
}

$sort = $_GET['sort'] ?? 'capture_desc';

/* ============================
   Data – main section
   ============================ */
$mainFiles = collectFiles($UPLOAD_DIR, $allowed);
sortFiles($mainFiles, $sort);

/* ============================
   Data – extra sections (uploads/<folder>)
   ============================ */
$sections = [];
foreach ($EXTRA_FOLDERS as $folderName) {
    $title = $folderName;
    $dir   = $UPLOAD_DIR . '/' . $folderName;
    if (!is_dir($dir)) continue;

    $url        = $UPLOAD_URL . '/' . rawurlencode($folderName);
    $thumbDir   = $dir . '/_thumbs';
    $displayDir = $dir . '/_display'; // larger display size

    $files = collectFiles($dir, $allowed);
    sortFiles($files, $sort);

    $sections[] = [
        'title'      => $title,
        'dir'        => $dir,
        'url'        => $url,
        'thumbDir'   => $thumbDir,
        'displayDir' => $displayDir,
        'files'      => $files,
    ];
}

/* ============================
   Render sizes / quality
   ============================ */
$THUMB_SIZE   = 600;   // grid tiles
$DISPLAY_SIZE = 1600;  // lightbox display (not original)
$THUMB_QLT    = 82;
$DISPLAY_QLT  = 85;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title><?php echo 'Gallery – ' . htmlspecialchars($SITE_TITLE ?? ''); ?></title>
<style>
  :root { --green:#2c3e2f; --gold:#b8860b; --bg:#fdfdf8; }
  * { box-sizing:border-box; margin:0; padding:0; }
  html { scroll-behavior:smooth; }
  body { font-family:'Georgia', serif; background:var(--bg); color:var(--green); padding:2rem 1rem 5rem; max-width:1200px; margin:0 auto; }
  h1 {
    font-family:'Brush Script MT', cursive;
    font-size:2.6rem;
    color:var(--gold);
    text-align:center;
    margin-bottom:1.2rem;
  }
  h2 {
    font-size:1.8rem;
    font-weight:normal;
    text-align:center;
    color:var(--gold);
    margin:2rem 0 1rem;
  }
  .topbar { display:flex; justify-content:space-between; align-items:center; margin-bottom:.5rem; gap:1rem; flex-wrap:wrap; }
  .btn, a.btn { border:2px solid var(--green); border-radius:8px; padding:.6rem 1.1rem; cursor:pointer; color:var(--green); background:#fff; font-size:1rem; text-decoration:none; transition:background .3s, color .3s; }
  .btn:hover, a.btn:hover { background:var(--green); color:#fff; }
  .controls { display:flex; gap:.75rem; align-items:center; flex-wrap:wrap; }
  .select { border:1px solid rgba(44,62,47,.4); border-radius:8px; padding:.45rem .7rem; background:#fff; color:var(--green); font-size:1rem; }

  /* Section jump navigation */
  .section-nav {
    display:flex; justify-content:center; flex-wrap:wrap;
    gap:.6rem; margin:1rem 0 1.5rem;
  }
  .section-nav a {
    border:2px solid var(--green); border-radius:8px;
    padding:.5rem 1rem; text-decoration:none;
    color:var(--green); background:#fff;
    transition:background .3s, color .3s;
  }
  .section-nav a:hover { background:var(--green); color:#fff; }

  .grid { display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:8px; }
  .tile { position:relative; overflow:hidden; border-radius:10px; background:#fff; border:1px solid rgba(44,62,47,.15); cursor:zoom-in; min-width:0; aspect-ratio:1/1; }
  .tile img { width:100%; height:100%; object-fit:cover; transition:transform .3s ease; max-width:none; min-width:0; }
  .tile:hover img { transform:scale(1.03); }
  .empty { text-align:center; padding:3rem 1rem; opacity:.8; }

  .lightbox { position:fixed; inset:0; background:rgba(0,0,0,.85); display:none; align-items:center; justify-content:center; z-index:9999; }
  .lightbox.open { display:flex; }
  .lightbox img { max-width:90vw; max-height:80vh; box-shadow:0 10px 40px rgba(0,0,0,.5); border-radius:8px; }
  .lightbox .close, .lightbox .prev, .lightbox .next, .lightbox .download {
    position:absolute; top:50%; transform:translateY(-50%);
    border:none; background:rgba(255,255,255,.9); color:var(--green);
    font-size:1.05rem; padding:.6rem .9rem; border-radius:8px; cursor:pointer; user-select:none; text-decoration:none;
  }
  .lightbox .close { top:5%; right:3%; transform:none; }
  .lightbox .prev  { left:3%; } 
  .lightbox .next  { right:3%; }
  .lightbox .download { top:auto; bottom:7%; right:3%; transform:none; }
  .lightbox .close:hover, .lightbox .prev:hover, .lightbox .next:hover, .lightbox .download:hover { background:var(--green); color:#fff; }

  .caption {
    position:absolute; bottom:7%; left:50%; transform:translateX(-50%);
    background:rgba(0,0,0,.55); color:#fff; padding:.4rem .7rem; border-radius:8px; font-size:1rem;
    max-width:70vw; text-align:center; pointer-events:none;
  }

  footer { text-align:center; font-size:.9rem; color:var(--green); margin-top:2rem; }
  footer a { color:var(--green); text-decoration:underline; }
</style>
</head>
<body>
  <h1>Gallery</h1>

  <div class="topbar">
    <a href="./" class="btn">⬅ Back to start</a>
    <div class="controls">
      <form method="get" id="sortForm">
        <label for="sort" style="margin-right:.25rem; opacity:.85;">Sort by:</label>
        <select name="sort" id="sort" class="select" onchange="document.getElementById('sortForm').submit()">
          <?php
          $options = [
            'capture_desc' => 'Capture time – newest first',
            'capture_asc'  => 'Capture time – oldest first',
            'upload_desc'  => 'Upload date – newest first',
            'upload_asc'   => 'Upload date – oldest first',
          ];
          $sort = array_key_exists($sort, $options) ? $sort : 'capture_desc';
          foreach ($options as $val => $label) {
              $sel = $sort === $val ? 'selected' : '';
              echo "<option value=\"$val\" $sel>$label</option>";
          }
          ?>
        </select>
        <div style="opacity:.8; margin-left:.5rem;">Click an image to view it larger. Originals via download.</div>
      </form>
    </div>
  </div>

  <!-- Section jump navigation -->
  <nav class="section-nav">
    <a href="#main">Hauptgalerie</a>
    <?php foreach ($sections as $sec): ?>
      <a href="#sec-<?php echo htmlspecialchars($sec['title']); ?>">
        <?php echo htmlspecialchars($sec['title']); ?>
      </a>
    <?php endforeach; ?>
  </nav>

  <?php
  // ===== Main section =====
  echo '<h2 id="main">Hauptgalerie</h2>';
  if (empty($mainFiles)): ?>
    <div class="empty">No photos in the main folder yet.</div>
  <?php else: ?>
    <div class="grid" id="gallery-main">
      <?php
      // directories for thumbs/display in main folder
      $MAIN_THUMBS   = $THUMB_DIR;                // from config.php
      $MAIN_DISPLAY  = $UPLOAD_DIR . '/_display'; // display images
      foreach ($mainFiles as $i => $item):
        $absPath  = $item['path'];
        $basename = $item['name'];

        // get/create thumbs + display images
        $thumbAbs   = makeThumb($absPath, $MAIN_THUMBS,  $THUMB_SIZE,   $THUMB_SIZE,   $THUMB_QLT);
        $displayAbs = makeThumb($absPath, $MAIN_DISPLAY, $DISPLAY_SIZE, $DISPLAY_SIZE, $DISPLAY_QLT);
        if (!$thumbAbs || !$displayAbs) continue;

        $thumbRel   = $UPLOAD_URL . '/_thumbs/'  . rawurlencode(basename($thumbAbs));
        $displayRel = $UPLOAD_URL . '/_display/' . rawurlencode(basename($displayAbs));
        $fullRel    = $UPLOAD_URL . '/' . rawurlencode($basename);

        $alt    = htmlspecialchars(pathinfo($basename, PATHINFO_FILENAME));
        $author = $authorsMap[$basename] ?? '';
      ?>
        <figure class="tile"
                data-index="<?php echo (int)$i; ?>"
                data-display="<?php echo htmlspecialchars($displayRel); ?>"
                data-full="<?php echo htmlspecialchars($fullRel); ?>"
                data-author="<?php echo htmlspecialchars($author); ?>">
          <img src="<?php echo htmlspecialchars($thumbRel); ?>" alt="<?php echo $alt; ?>" loading="lazy" decoding="async" />
        </figure>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php
  // ===== Extra sections =====
  foreach ($sections as $sec):
      $files     = $sec['files'];
      if (empty($files)) continue;
      $title     = $sec['title'];
      $dir       = $sec['dir'];
      $url       = $sec['url'];
      $tDir      = $sec['thumbDir'];
      $dDir      = $sec['displayDir'];
  ?>
    <h2 id="sec-<?php echo htmlspecialchars($title); ?>"><?php echo htmlspecialchars($title); ?></h2>
    <div class="grid">
      <?php foreach ($files as $item):
        $absPath  = $item['path'];
        $basename = $item['name'];

        $thumbAbs   = makeThumb($absPath, $tDir, $THUMB_SIZE, $THUMB_SIZE, $THUMB_QLT);
        $displayAbs = makeThumb($absPath, $dDir, $DISPLAY_SIZE, $DISPLAY_SIZE, $DISPLAY_QLT);
        if (!$thumbAbs || !$displayAbs) continue;

        $thumbRel   = $url . '/_thumbs/'  . rawurlencode(basename($thumbAbs));
        $displayRel = $url . '/_display/' . rawurlencode(basename($displayAbs));
        $fullRel    = $url . '/' . rawurlencode($basename);

        $alt    = htmlspecialchars(pathinfo($basename, PATHINFO_FILENAME));
        $author = $authorsMap[$basename] ?? '';
      ?>
        <figure class="tile"
                data-display="<?php echo htmlspecialchars($displayRel); ?>"
                data-full="<?php echo htmlspecialchars($fullRel); ?>"
                data-author="<?php echo htmlspecialchars($author); ?>">
          <img src="<?php echo htmlspecialchars($thumbRel); ?>" alt="<?php echo $alt; ?>" loading="lazy" decoding="async" />
        </figure>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>

  <!-- Lightbox -->
  <div class="lightbox" id="lightbox" aria-hidden="true">
    <button class="close" id="lbClose" aria-label="Close">✕</button>
    <button class="prev" id="lbPrev" aria-label="Previous">◀</button>
    <img id="lbImage" src="" alt="Preview" />
    <a id="lbDownload" class="download" href="#" download>⬇ Download original</a>
    <div class="caption" id="lbCaption" style="display:none;"></div>
    <button class="next" id="lbNext" aria-label="Next">▶</button>
  </div>

<script>
(function(){
  const tiles = Array.from(document.querySelectorAll('.tile'));
  const lb = document.getElementById('lightbox');
  const lbImg = document.getElementById('lbImage');
  const lbCap = document.getElementById('lbCaption');
  const lbDl  = document.getElementById('lbDownload');
  const btnClose = document.getElementById('lbClose');
  const btnPrev  = document.getElementById('lbPrev');
  const btnNext  = document.getElementById('lbNext');

  if (!tiles.length) return;

  let current = 0;

  function setCaptionFrom(idx) {
    const author = tiles[idx].getAttribute('data-author') || '';
    if (author) {
      lbCap.textContent = 'by ' + author;
      lbCap.style.display = 'block';
    } else {
      lbCap.style.display = 'none';
      lbCap.textContent = '';
    }
  }

  function openAt(index) {
    current = index;
    const display = tiles[current].getAttribute('data-display');
    const full    = tiles[current].getAttribute('data-full');
    lbImg.src = display;         // load display-size image
    lbDl.href = full;            // original for download
    try {
      const url = new URL(full, window.location.href);
      const fn  = decodeURIComponent(url.pathname.split('/').pop());
      lbDl.setAttribute('download', fn);
    } catch {}
    setCaptionFrom(current);
    lb.classList.add('open');
    lb.setAttribute('aria-hidden', 'false');
  }

  function close() {
    lb.classList.remove('open');
    lb.setAttribute('aria-hidden', 'true');
    lbImg.src = '';
    lbCap.style.display = 'none';
    lbCap.textContent = '';
  }

  function prev() {
    current = (current - 1 + tiles.length) % tiles.length;
    const display = tiles[current].getAttribute('data-display');
    const full    = tiles[current].getAttribute('data-full');
    lbImg.src = display;
    lbDl.href = full;
    setCaptionFrom(current);
  }

  function next() {
    current = (current + 1) % tiles.length;
    const display = tiles[current].getAttribute('data-display');
    const full    = tiles[current].getAttribute('data-full');
    lbImg.src = display;
    lbDl.href = full;
    setCaptionFrom(current);
  }

  tiles.forEach((tile, idx) => tile.addEventListener('click', () => openAt(idx)));
  btnClose.addEventListener('click', close);
  btnPrev.addEventListener('click', prev);
  btnNext.addEventListener('click', next);
  lb.addEventListener('click', (e) => { if (e.target === lb) close(); });
  document.addEventListener('keydown', (e) => {
    if (!lb.classList.contains('open')) return;
    if (e.key === 'Escape') close();
    if (e.key === 'ArrowLeft') prev();
    if (e.key === 'ArrowRight') next();
  });
})();
</script>

<footer>
  <p><?php echo htmlspecialchars($SITE_TITLE ?? ''); ?> &copy; <?php echo date('Y'); ?>
    <a target="_blank" href="impressum.html" style="margin-left:.5rem;">Imprint</a>
  </p>
</footer>
</body>
</html>

