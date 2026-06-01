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
</style>

<div class="grid gap-3" style="grid-template-columns: repeat(auto-fill, minmax(180px,1fr));" id="edit-grid">
    <?php foreach ($photos as $photo): ?>
    <div class="photo-tile"
         draggable="true"
         data-id="<?= $photo['id'] ?>"
         data-url="<?= htmlspecialchars($photo['image_url']) ?>">
        <img src="<?= htmlspecialchars($photo['image_url']) ?>" alt="">
        <button class="delete-btn" onclick="deletePhoto(<?= $photo['id'] ?>, this.closest('.photo-tile'))" title="Supprimer">✕</button>
        <div class="drag-handle">⠿⠿⠿</div>
    </div>
    <?php endforeach; ?>

    <!-- Tile "ajouter" -->
    <div class="add-tile" onclick="document.getElementById('photo-file-input').click()">
        <svg width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
        </svg>
        <span style="font-size:12px;font-weight:600;">Ajouter une photo</span>
    </div>
</div>

<input type="file" id="photo-file-input" accept="image/*" multiple class="hidden">

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
