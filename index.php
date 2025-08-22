<?php
require_once __DIR__ . '/config.php';
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?php echo htmlspecialchars($SITE_TITLE ?? ''); ?></title>
  <style>
    :root { --green:#2c3e2f; --gold:#b8860b; --bg:#fdfdf8; }
    * { margin:0; padding:0; box-sizing:border-box; }
    body {
      font-family:'Georgia', serif;
      background: var(--bg) url('file.png') no-repeat top center fixed;
      background-size: 100% auto; background-repeat: repeat-y; background-attachment: fixed;
      color: var(--green); display:flex; flex-direction:column; align-items:center; padding:2rem 1rem;
    }
    h1 { font-family:'Brush Script MT', cursive; font-size:3rem; text-align:center; color:var(--gold); animation: fadeInDown 1.2s ease; }
    .upload-section { margin-top:3rem; text-align:center; animation: fadeInUp 1.2s ease; }
    .upload-section h2 { font-size:1.5rem; margin-bottom:1rem; }
    form, #uploadForm { display:flex; flex-direction:column; align-items:center; }
    input[type="file"] { display:none; }
    label.custom-file-upload, a.galerie-button {
      border:2px solid var(--green); border-radius:8px; padding:.7rem 1.5rem; cursor:pointer; color:var(--green); background:#fff;
      font-size:1rem; transition: background .3s, color .3s; margin-top:1rem; text-decoration:none;
    }
    label.custom-file-upload:hover, a.galerie-button:hover { background:var(--green); color:#fff; }
    .success-message { color:green; margin-top:1rem; }
    #progressWrap { display:none; margin-top:1rem; width:min(420px, 90vw); text-align:left; }
    progress { width:100%; height:16px; appearance:none; -webkit-appearance:none; }
    progress::-webkit-progress-bar { background:#eee; border-radius:8px; }
    progress::-webkit-progress-value { background: var(--green); border-radius:8px; }
    progress::-moz-progress-bar { background: var(--green); border-radius:8px; }
    @keyframes fadeInDown { from{opacity:0; transform:translateY(-20px)} to{opacity:1; transform:translateY(0)} }
    @keyframes fadeInUp   { from{opacity:0; transform:translateY(20px)}  to{opacity:1; transform:translateY(0)} }
    .galerie-cta { display:inline-block; animation: fadeInUp 1.2s ease both; animation-delay: .15s; }
    footer { position:fixed; bottom:0; width:100%; background:rgba(253,253,248,.9); text-align:center; font-size:.9rem; padding:.5rem; color:var(--green); z-index:999; }
    footer a { color:var(--green); text-decoration:underline; }
    /* Modal */
    #nameModal { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:10000; align-items:center; justify-content:center; }
    #nameModal .box { background:#fff; padding:1rem; border-radius:12px; width:min(420px,90vw); box-shadow:0 10px 30px rgba(0,0,0,.2); }
    #nameModal .box h3 { margin:0 0 .5rem; font-family:'Georgia', serif; color:var(--green); }
    #nameModal .box input { width:100%; padding:.6rem .8rem; border:1px solid rgba(44,62,47,.3); border-radius:8px; font-size:1rem; }
    #nameModal .row { display:flex; gap:.5rem; justify-content:flex-end; margin-top:.75rem; }
    #nameModal .btn { border:2px solid var(--green); border-radius:8px; padding:.5rem 1rem; background:#fff; color:var(--green); cursor:pointer; }
    #nameModal .btn:hover { background:var(--green); color:#fff; }
  </style>
</head>
<body>
  <h1><br><?php echo htmlspecialchars($SITE_TITLE ?? ''); ?></h1>

  <div class="upload-section">
    <h2><?php echo  htmlspecialchars($SITE_SUBTITLE ?? ''); ?></h2>

    <form id="uploadForm" action="upload.php" method="post" enctype="multipart/form-data">
      <input id="file-upload" type="file" name="photos[]" accept="image/*" multiple required />
      <label for="file-upload" class="custom-file-upload">Dateien auswählen</label>

      <div id="progressWrap">
        <progress id="uploadProgress" max="100" value="0"></progress>
        <div id="progressText" style="margin-top:.25rem; font-size:.95rem;">0%</div>
        <div id="batchText" style="margin-top:.25rem; font-size:.95rem; opacity:.9;"></div>
        <div id="successText" style="margin-top:.25rem; font-size:.95rem; opacity:.9;"></div>
      </div>
    </form>

    <p class="success-message" id="successMessage"></p>
  </div>

  <a href="galerie.php" class="galerie-button galerie-cta">Galerie</a>

  <!-- Name-Modal -->
  <div id="nameModal">
    <div class="box">
      <h3>Wie heißt du?</h3>
      <p style="margin:.25rem 0 .75rem; opacity:.85;">Bitte gib deinen Namen an. Er wird deinen Fotos zugeordnet.</p>
      <input id="authorInput" type="text" placeholder="Name" />
      <div class="row">
        <button id="nameCancel" type="button" class="btn">Abbrechen</button>
        <button id="nameOk" type="button" class="btn">OK</button>
      </div>
    </div>
  </div>

  <script>
    if (window.location.search.includes('upload=success')) {
      document.getElementById('successMessage').textContent = 'Danke für das Hochladen eurer Fotos!';
    }

    const form         = document.getElementById('uploadForm');
    const fileInput    = document.getElementById('file-upload');
    const progressWrap = document.getElementById('progressWrap');
    const progressBar  = document.getElementById('uploadProgress');
    const progressText = document.getElementById('progressText');
    const batchText    = document.getElementById('batchText');
    const successText  = document.getElementById('successText');

    const nameModal   = document.getElementById('nameModal');
    const authorInput = document.getElementById('authorInput');
    const nameOk      = document.getElementById('nameOk');
    const nameCancel  = document.getElementById('nameCancel');

    const BATCH_SIZE = 5;
    let AUTHOR = null;

    // Session-Author laden
    fetch('session_author.php').then(r=>r.json()).then(j => { if (j && j.author) AUTHOR = j.author; }).catch(()=>{});

    // Datei-Label abfangen: erst Name klären, dann Filepicker öffnen
    const pickLabel = document.querySelector('label.custom-file-upload');
    pickLabel.addEventListener('click', async (e) => {
      if (AUTHOR) return; // schon vorhanden
      e.preventDefault();
      const name = await askAuthor();
      if (name) fileInput.click();
    });

    // Falls jemand direkt das Input fokussiert
    fileInput.addEventListener('focus', async (e) => {
      if (!AUTHOR) {
        e.preventDefault();
        const name = await askAuthor();
        if (name) fileInput.click();
      }
    }, true);

    // Nach Dateiauswahl automatisch starten
    fileInput.addEventListener('change', () => {
      if (fileInput.files.length > 0) startBatchUpload();
    });

    // Formular-Submit (falls jemand Enter drückt)
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      if (fileInput.files.length > 0) startBatchUpload();
    });

    function openModal()  { nameModal.style.display = 'flex'; setTimeout(()=>authorInput.focus(), 0); }
    function closeModal() { nameModal.style.display = 'none'; }

    function askAuthor() {
      return new Promise((resolve) => {
        openModal();
        function cleanup() {
          nameOk.removeEventListener('click', onOk);
          nameCancel.removeEventListener('click', onCancel);
          closeModal();
        }
        async function onOk() {
          const val = authorInput.value.trim();
          if (!val) return;
          try {
            const res = await fetch('session_author.php', { method:'POST', body: new URLSearchParams({author: val}) });
            const j = await res.json();
            if (j && j.ok) { AUTHOR = j.author; resolve(AUTHOR); cleanup(); }
            else resolve(null);
          } catch { resolve(null); cleanup(); }
        }
        function onCancel(){ resolve(null); cleanup(); }
        nameOk.addEventListener('click', onOk);
        nameCancel.addEventListener('click', onCancel);
      });
    }

    async function startBatchUpload() {
      const files = Array.from(fileInput.files);
      if (!files.length) return;

      progressWrap.style.display = 'block';
      successText.textContent = '';
      progressBar.value = 0; progressText.textContent = '0%'; batchText.textContent = '';

      const totalBytes = files.reduce((acc, f) => acc + (f.size || 0), 0);
      let uploadedBytes = 0, uploadedCount = 0, errorCount = 0;

      const batches = [];
      for (let i = 0; i < files.length; i += BATCH_SIZE) {
        batches.push(files.slice(i, i + BATCH_SIZE));
      }

      for (let b = 0; b < batches.length; b++) {
        const batch = batches[b];
        batchText.textContent = `Sende Paket ${b+1} / ${batches.length} (${batch.length} Datei(en))…`;

        await new Promise((resolve) => {
          const fd = new FormData();
          batch.forEach(f => fd.append('photos[]', f, f.name));
          if (AUTHOR) fd.append('author', AUTHOR);

          const xhr = new XMLHttpRequest();
          xhr.open('POST', 'upload.php?ajax=1', true);

          xhr.upload.onprogress = (e) => {
            if (!e.lengthComputable) return;
            const current = uploadedBytes + e.loaded;
            const pct = Math.round((current / totalBytes) * 100);
            progressBar.value = pct; progressText.textContent = pct + '%';
          };

          xhr.onreadystatechange = () => {
            if (xhr.readyState === 4) {
              try {
                const res = JSON.parse(xhr.responseText || '{}');
                if (xhr.status >= 200 && xhr.status < 300) {
                  uploadedCount += (res.uploaded ?? 0);
                  errorCount   += (res.errors ? res.errors.length : 0);
                } else {
                  errorCount   += batch.length;
                }
              } catch { errorCount += batch.length; }

              const batchBytes = batch.reduce((acc, f) => acc + (f.size || 0), 0);
              uploadedBytes += batchBytes;

              if (b === batches.length - 1) { progressBar.value = 100; progressText.textContent = '100%'; }
              resolve();
            }
          };

          xhr.onerror = () => {
            errorCount += batch.length;
            const batchBytes = batch.reduce((acc, f) => acc + (f.size || 0), 0);
            uploadedBytes += batchBytes;
            resolve();
          };

          xhr.send(fd);
        });
      }

      successText.textContent = `${uploadedCount} / ${files.length} Datei(en) erfolgreich hochgeladen` + (errorCount ? `, ${errorCount} Fehler` : '');
      batchText.textContent = '';
      fileInput.value = '';
    }
  </script>

  <footer>
    <p>Christin & Michael Osing &copy; 2025 <a target="_blank" href="impressum.html">Impressum</a></p>
  </footer>
</body>
</html>

