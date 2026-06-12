<style>
.photo-tile {
    position: relative;
    border-radius: 12px;
    overflow: hidden;
    aspect-ratio: 1;
    background: #111;
    border: 2px solid #1a1a1a;
    cursor: grab;
    transition: transform .15s, box-shadow .15s, border-color .15s, opacity .15s;
    user-select: none;
}
.photo-tile:active { cursor: grabbing; }
.photo-tile.drag-over { border-color: #d4a5d4; transform: scale(1.03); box-shadow: 0 0 0 2px #d4a5d4; }
.photo-tile.dragging { opacity: .35; }
.photo-tile img { width:100%; height:100%; object-fit:cover; display:block; pointer-events:none; }
.photo-tile .delete-btn {
    position:absolute; top:8px; right:8px;
    width:26px; height:26px;
    background:rgba(0,0,0,.75);
    border:1.5px solid rgba(255,255,255,.15);
    border-radius:50%;
    display:flex; align-items:center; justify-content:center;
    cursor:pointer; color:#fff; font-size:13px; line-height:1;
    opacity:0; transition:opacity .15s;
    backdrop-filter: blur(4px);
}
.photo-tile:hover .delete-btn { opacity:1; }
.photo-tile .delete-btn:hover { background:rgba(220,50,50,.85); border-color:transparent; }
.photo-tile .drag-handle {
    position:absolute; bottom:8px; left:50%; transform:translateX(-50%);
    color:rgba(255,255,255,.25); font-size:10px; letter-spacing:2px;
    pointer-events:none; opacity:0; transition:opacity .15s;
}
.photo-tile:hover .drag-handle { opacity:1; }
.add-tile {
    aspect-ratio:1;
    border-radius:12px;
    border: 2px dashed #2a2a2a;
    display:flex; flex-direction:column; align-items:center; justify-content:center;
    gap:8px; cursor:pointer; color:#444;
    transition: border-color .15s, color .15s;
}
.add-tile:hover { border-color: #d4a5d4; color:#d4a5d4; }
@media (max-width: 768px) {
    #edit-grid { grid-template-columns: repeat(3, 1fr) !important; gap: 3px !important; }
    .photo-tile { border-radius: 0; border: none; border-bottom: 3px solid #000; }
    .photo-tile.drag-over { border-color: #d4a5d4; }
    .photo-tile .delete-btn { opacity: 1; width: 22px; height: 22px; font-size: 11px; top: 5px; right: 5px; }
    .photo-tile .drag-handle { opacity: 1; bottom: 5px; font-size: 9px; }
    .add-tile { border-radius: 0; }
    #upload-progress { padding: 0 12px; }
}
</style>

<div class="grid gap-3" style="grid-template-columns: repeat(auto-fill, minmax(180px,1fr));" id="edit-grid">
    <?php foreach ($photos as $photo): ?>
    <?php if (!empty($photo['video_url'])):
        $vinfo_edit = extractVideoInfo($photo['video_url']);
        $vthumb_edit = $vinfo_edit ? videoThumb($vinfo_edit['platform'], $vinfo_edit['id']) : ''; ?>
    <div class="photo-tile"
         draggable="true"
         data-id="<?= $photo['id'] ?>"
         data-video-url="<?= htmlspecialchars($photo['video_url']) ?>">
        <img src="<?= htmlspecialchars($vthumb_edit) ?>" alt="" style="width:100%;height:100%;object-fit:cover;display:block;pointer-events:none;">
        <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;pointer-events:none;">
            <div style="width:34px;height:34px;border-radius:50%;background:rgba(212,165,212,0.85);display:flex;align-items:center;justify-content:center;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="#000"><path d="M8 5v14l11-7z"/></svg>
            </div>
        </div>
        <button class="delete-btn" onclick="deletePhoto(<?= $photo['id'] ?>, this.closest('.photo-tile'))" title="Supprimer">✕</button>
        <div class="drag-handle">⠿⠿⠿</div>
    </div>
    <?php else: ?>
    <div class="photo-tile"
         draggable="true"
         data-id="<?= $photo['id'] ?>"
         data-url="<?= htmlspecialchars($photo['image_url']) ?>">
        <img src="<?= htmlspecialchars($photo['image_url']) ?>" alt="">
        <button class="delete-btn" onclick="deletePhoto(<?= $photo['id'] ?>, this.closest('.photo-tile'))" title="Supprimer">✕</button>
        <div class="drag-handle">⠿⠿⠿</div>
    </div>
    <?php endif; ?>
    <?php endforeach; ?>

    <!-- Tile "ajouter photo" -->
    <div class="add-tile" onclick="document.getElementById('photo-file-input').click()">
        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
        <span style="font-size:12px;font-weight:600;">Photo</span>
    </div>
    <!-- Tile "ajouter vidéo" -->
    <div class="add-tile" onclick="document.getElementById('video-modal').style.display='flex'">
        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span style="font-size:12px;font-weight:600;">Vidéo</span>
    </div>
</div>

<input type="file" id="photo-file-input" accept="image/*" multiple class="hidden">

<!-- Modal ajout vidéo -->
<div id="video-modal" style="display:none;position:fixed;inset:0;z-index:9000;background:rgba(0,0,0,0.75);backdrop-filter:blur(4px);align-items:center;justify-content:center;">
    <div style="background:#111;border:1px solid #2a2a2a;border-radius:20px;padding:32px;width:100%;max-width:440px;margin:16px;">
        <h3 style="color:#fff;font-weight:700;font-size:16px;margin:0 0 6px;">Ajouter une vidéo</h3>
        <p style="color:#666;font-size:13px;margin:0 0 20px;">Collez l'URL d'une vidéo YouTube ou Dailymotion.</p>
        <input id="video-url-input" type="text" placeholder="https://www.youtube.com/watch?v=... ou dailymotion.com/video/..." style="width:100%;padding:12px 16px;background:#1a1a1a;border:1px solid #2a2a2a;border-radius:12px;color:#fff;font-size:13px;outline:none;box-sizing:border-box;" oninput="this.style.borderColor='#2a2a2a'">
        <p id="video-url-error" style="color:#e55;font-size:12px;margin:8px 0 0;display:none;">URL invalide. Vérifiez le lien YouTube ou Dailymotion.</p>
        <div style="display:flex;gap:10px;margin-top:20px;">
            <button onclick="closeVideoModal()" style="flex:1;padding:11px;background:#1a1a1a;border:1px solid #2a2a2a;border-radius:12px;color:#888;font-size:13px;font-weight:600;cursor:pointer;">Annuler</button>
            <button onclick="submitVideo()" style="flex:1;padding:11px;background:#d4a5d4;border:none;border-radius:12px;color:#000;font-size:13px;font-weight:700;cursor:pointer;">Ajouter</button>
        </div>
    </div>
</div>

<div id="upload-progress" class="hidden mt-4 flex items-center gap-3 text-sm text-[#888]">
    <svg class="animate-spin w-4 h-4 text-brand" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
    <span id="upload-progress-text">Upload en cours…</span>
</div>

<script>
(function() {
    const grid = document.getElementById('edit-grid');
    let dragSrc = null;

    // ── Drag & Drop ──────────────────────────────────────────────
    grid.addEventListener('dragstart', e => {
        const tile = e.target.closest('.photo-tile');
        if (!tile) return;
        dragSrc = tile;
        tile.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
    });

    grid.addEventListener('dragend', e => {
        const tile = e.target.closest('.photo-tile');
        if (tile) tile.classList.remove('dragging');
        grid.querySelectorAll('.photo-tile').forEach(t => t.classList.remove('drag-over'));
    });

    grid.addEventListener('dragover', e => {
        e.preventDefault();
        const tile = e.target.closest('.photo-tile');
        if (!tile || tile === dragSrc) return;
        grid.querySelectorAll('.photo-tile').forEach(t => t.classList.remove('drag-over'));
        tile.classList.add('drag-over');
    });

    grid.addEventListener('drop', e => {
        e.preventDefault();
        const target = e.target.closest('.photo-tile');
        if (!target || target === dragSrc || !dragSrc) return;
        target.classList.remove('drag-over');

        // Réordonner dans le DOM
        const tiles = [...grid.querySelectorAll('.photo-tile')];
        const srcIdx = tiles.indexOf(dragSrc);
        const tgtIdx = tiles.indexOf(target);
        if (srcIdx < tgtIdx) target.after(dragSrc);
        else target.before(dragSrc);

        saveOrder();
    });

    // ── Touch drag & drop (mobile) ───────────────────────────────
    let touchDragSrc = null, touchClone = null, touchOffX = 0, touchOffY = 0;

    grid.addEventListener('touchstart', e => {
        const tile = e.target.closest('.photo-tile');
        if (!tile) return;
        const touch = e.touches[0];
        touchDragSrc = tile;
        const rect = tile.getBoundingClientRect();
        touchOffX = touch.clientX - rect.left;
        touchOffY = touch.clientY - rect.top;
        // Clone flottant
        touchClone = tile.cloneNode(true);
        touchClone.style.cssText = `position:fixed;z-index:9999;width:${rect.width}px;height:${rect.height}px;opacity:.85;pointer-events:none;border-radius:12px;overflow:hidden;top:${rect.top}px;left:${rect.left}px;transition:none;`;
        document.body.appendChild(touchClone);
        tile.style.opacity = '.35';
        e.preventDefault();
    }, { passive: false });

    document.addEventListener('touchmove', e => {
        if (!touchDragSrc || !touchClone) return;
        const touch = e.touches[0];
        touchClone.style.top  = (touch.clientY - touchOffY) + 'px';
        touchClone.style.left = (touch.clientX - touchOffX) + 'px';
        // Trouver la tile sous le doigt
        touchClone.style.display = 'none';
        const el = document.elementFromPoint(touch.clientX, touch.clientY);
        touchClone.style.display = '';
        const over = el ? el.closest('.photo-tile') : null;
        grid.querySelectorAll('.photo-tile').forEach(t => t.classList.remove('drag-over'));
        if (over && over !== touchDragSrc) over.classList.add('drag-over');
        e.preventDefault();
    }, { passive: false });

    document.addEventListener('touchend', e => {
        if (!touchDragSrc) return;
        const touch = e.changedTouches[0];
        touchClone.remove();
        touchClone = null;
        touchDragSrc.style.opacity = '';
        // Trouver la cible
        const el = document.elementFromPoint(touch.clientX, touch.clientY);
        const target = el ? el.closest('.photo-tile') : null;
        grid.querySelectorAll('.photo-tile').forEach(t => t.classList.remove('drag-over'));
        if (target && target !== touchDragSrc) {
            const tiles = [...grid.querySelectorAll('.photo-tile')];
            const srcIdx = tiles.indexOf(touchDragSrc);
            const tgtIdx = tiles.indexOf(target);
            if (srcIdx < tgtIdx) target.after(touchDragSrc);
            else target.before(touchDragSrc);
            saveOrder();
        }
        touchDragSrc = null;
    });

    // ── Sauvegarder l'ordre ──────────────────────────────────────
    function saveOrder() {
        const ids = [...grid.querySelectorAll('.photo-tile')].map(t => t.dataset.id);
        fetch('profil.php?id=<?= $profile_id ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'photo_action=reorder&ids=' + encodeURIComponent(JSON.stringify(ids))
        });
    }

    // ── Supprimer une photo ──────────────────────────────────────
    window.deletePhoto = function(photoId, tile) {
        if (!confirm('Supprimer cette photo du book ?')) return;
        fetch('profil.php?id=<?= $profile_id ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'photo_action=delete&photo_id=' + photoId
        })
        .then(r => r.json())
        .then(d => {
            if (d.ok) {
                tile.style.transition = 'opacity .2s, transform .2s';
                tile.style.opacity = '0';
                tile.style.transform = 'scale(.85)';
                setTimeout(() => tile.remove(), 200);
            }
        });
    };

    // ── Vidéo YouTube ────────────────────────────────────────────
    window.closeVideoModal = function() {
        document.getElementById('video-modal').style.display = 'none';
        document.getElementById('video-url-input').value = '';
        document.getElementById('video-url-error').style.display = 'none';
    };

    window.submitVideo = async function() {
        const url = document.getElementById('video-url-input').value.trim();
        const errEl = document.getElementById('video-url-error');
        if (!url) { errEl.style.display = 'block'; return; }
        errEl.style.display = 'none';
        const fd = new FormData();
        fd.append('photo_action', 'add_video');
        fd.append('video_url', url);
        const res = await fetch('profil.php?id=<?= $profile_id ?>', { method: 'POST', body: fd });
        const data = await res.json();
        if (!data.ok) { errEl.textContent = data.err || 'URL invalide.'; errEl.style.display = 'block'; return; }
        const addTiles = grid.querySelectorAll('.add-tile');
        const tile = document.createElement('div');
        tile.className = 'photo-tile';
        tile.draggable = true;
        tile.dataset.id = data.id;
        tile.dataset.videoUrl = data.video_url;
        tile.innerHTML = `
            <img src="${data.thumb}" alt="" style="width:100%;height:100%;object-fit:cover;display:block;pointer-events:none;">
            <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;pointer-events:none;">
                <div style="width:34px;height:34px;border-radius:50%;background:rgba(212,165,212,0.85);display:flex;align-items:center;justify-content:center;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="#000"><path d="M8 5v14l11-7z"/></svg>
                </div>
            </div>
            <button class="delete-btn" onclick="deletePhoto(${data.id}, this.closest('.photo-tile'))">✕</button>
            <div class="drag-handle">⠿⠿⠿</div>`;
        tile.style.opacity = '0'; tile.style.transform = 'scale(.85)';
        addTiles[0].before(tile);
        requestAnimationFrame(() => {
            tile.style.transition = 'opacity .25s, transform .25s';
            tile.style.opacity = '1'; tile.style.transform = 'scale(1)';
        });
        closeVideoModal();
    };

    document.getElementById('video-modal').addEventListener('click', e => {
        if (e.target === document.getElementById('video-modal')) closeVideoModal();
    });

    // ── Upload ───────────────────────────────────────────────────
    document.getElementById('photo-file-input').addEventListener('change', async function() {
        const files = [...this.files];
        if (!files.length) return;
        const progress = document.getElementById('upload-progress');
        const progressText = document.getElementById('upload-progress-text');
        progress.classList.remove('hidden');

        for (let i = 0; i < files.length; i++) {
            progressText.textContent = `Upload ${i+1}/${files.length}…`;
            const fd = new FormData();
            fd.append('photo_action', 'upload');
            fd.append('photo', files[i]);
            const res = await fetch('profil.php?id=<?= $profile_id ?>', { method:'POST', body: fd });
            const data = await res.json();
            if (data.ok) {
                // Insérer la nouvelle tile avant le bouton "ajouter"
                const addTile = grid.querySelector('.add-tile');
                const tile = document.createElement('div');
                tile.className = 'photo-tile';
                tile.draggable = true;
                tile.dataset.id = data.id;
                tile.dataset.url = data.url;
                tile.innerHTML = `<img src="${data.url}" alt="">
                    <button class="delete-btn" onclick="deletePhoto(${data.id}, this.closest('.photo-tile'))">✕</button>
                    <div class="drag-handle">⠿⠿⠿</div>`;
                tile.style.opacity = '0';
                tile.style.transform = 'scale(.85)';
                addTile.before(tile);
                requestAnimationFrame(() => {
                    tile.style.transition = 'opacity .25s, transform .25s';
                    tile.style.opacity = '1';
                    tile.style.transform = 'scale(1)';
                });
            }
        }
        progress.classList.add('hidden');
        this.value = '';
    });
})();
</script>
