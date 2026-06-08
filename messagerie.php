<?php
session_start();
require_once 'config/Database.php';
require_once 'models/User.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: connexion.php");
    exit();
}

$db = Database::getInstance()->getConnection();
$me = $_SESSION['user_id'];

// ── AJAX handlers ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    if ($_POST['action'] === 'send') {
        $conv_id = intval($_POST['conversation_id'] ?? 0);
        $content = trim($_POST['content'] ?? '');
        if (!$conv_id || $content === '') { echo json_encode(['ok' => false]); exit; }
        $check = $db->prepare("SELECT id FROM conversations WHERE id=:id AND (user1_id=:me OR user2_id=:me)");
        $check->execute(['id' => $conv_id, 'me' => $me]);
        if (!$check->rowCount()) { echo json_encode(['ok' => false]); exit; }
        $stmt = $db->prepare("INSERT INTO messages (conversation_id, sender_id, content) VALUES (:conv, :sender, :content) RETURNING id, created_at");
        $stmt->execute(['conv' => $conv_id, 'sender' => $me, 'content' => $content]);
        $msg = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['ok' => true, 'id' => $msg['id'], 'created_at' => $msg['created_at']]);
        exit;
    }

    if ($_POST['action'] === 'poll') {
        $conv_id = intval($_POST['conversation_id'] ?? 0);
        $last_id = intval($_POST['last_id'] ?? 0);
        $check = $db->prepare("SELECT id FROM conversations WHERE id=:id AND (user1_id=:me OR user2_id=:me)");
        $check->execute(['id' => $conv_id, 'me' => $me]);
        if (!$check->rowCount()) { echo json_encode(['ok' => false]); exit; }
        $db->prepare("UPDATE messages SET is_read=TRUE WHERE conversation_id=:conv AND sender_id!=:me AND is_read=FALSE")->execute(['conv' => $conv_id, 'me' => $me]);
        $stmt = $db->prepare("SELECT m.id, m.sender_id, m.content, m.created_at FROM messages m WHERE m.conversation_id=:conv AND m.id > :last ORDER BY m.id ASC");
        $stmt->execute(['conv' => $conv_id, 'last' => $last_id]);
        $msgs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Also return updated unread counts for sidebar
        $unread_stmt = $db->prepare("
            SELECT c.id AS conv_id, COUNT(m.id) AS unread
            FROM conversations c
            LEFT JOIN messages m ON m.conversation_id=c.id AND m.sender_id!=:me AND m.is_read=FALSE
            WHERE c.user1_id=:me OR c.user2_id=:me
            GROUP BY c.id
        ");
        $unread_stmt->execute(['me' => $me]);
        $unread_map = [];
        foreach ($unread_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $unread_map[$row['conv_id']] = $row['unread'];
        }
        echo json_encode(['ok' => true, 'messages' => $msgs, 'unread' => $unread_map]);
        exit;
    }

    if ($_POST['action'] === 'open_conversation') {
        $other_id = intval($_POST['other_id'] ?? 0);
        if (!$other_id || $other_id == $me) { echo json_encode(['ok' => false]); exit; }
        $u1 = min($me, $other_id);
        $u2 = max($me, $other_id);
        $find = $db->prepare("SELECT id FROM conversations WHERE user1_id=:u1 AND user2_id=:u2");
        $find->execute(['u1' => $u1, 'u2' => $u2]);
        $conv = $find->fetch(PDO::FETCH_ASSOC);
        if (!$conv) {
            $ins = $db->prepare("INSERT INTO conversations (user1_id, user2_id) VALUES (:u1, :u2) RETURNING id");
            $ins->execute(['u1' => $u1, 'u2' => $u2]);
            $conv = $ins->fetch(PDO::FETCH_ASSOC);
        }
        echo json_encode(['ok' => true, 'conversation_id' => $conv['id']]);
        exit;
    }

    echo json_encode(['ok' => false]);
    exit;
}

// ── Open conversation from ?with=USER_ID ────────────────────────────────────
$open_conv_id   = null;
$open_other     = null;
if (isset($_GET['with'])) {
    $other_id = intval($_GET['with']);
    if ($other_id && $other_id != $me) {
        $u1 = min($me, $other_id);
        $u2 = max($me, $other_id);
        $find = $db->prepare("SELECT id FROM conversations WHERE user1_id=:u1 AND user2_id=:u2");
        $find->execute(['u1' => $u1, 'u2' => $u2]);
        $conv = $find->fetch(PDO::FETCH_ASSOC);
        if (!$conv) {
            $ins = $db->prepare("INSERT INTO conversations (user1_id, user2_id) VALUES (:u1, :u2) RETURNING id");
            $ins->execute(['u1' => $u1, 'u2' => $u2]);
            $conv = $ins->fetch(PDO::FETCH_ASSOC);
        }
        $open_conv_id = $conv['id'];
        $stmt = $db->prepare("SELECT id, full_name, profile_picture_url, specific_profession,
            (SELECT image_url FROM portfolios WHERE user_id=u.id ORDER BY position ASC, created_at DESC LIMIT 1) AS fallback_avatar
            FROM users u WHERE u.id=:id");
        $stmt->execute(['id' => $other_id]);
        $open_other = $stmt->fetch(PDO::FETCH_ASSOC);
        // Mark as read immediately
        $db->prepare("UPDATE messages SET is_read=TRUE WHERE conversation_id=:conv AND sender_id!=:me AND is_read=FALSE")->execute(['conv' => $open_conv_id, 'me' => $me]);
    }
}

// ── Load conversations list ──────────────────────────────────────────────────
$convs_stmt = $db->prepare("
    SELECT c.id,
        CASE WHEN c.user1_id=:me THEN c.user2_id ELSE c.user1_id END AS other_id,
        u.full_name, u.profile_picture_url, u.specific_profession,
        (SELECT image_url FROM portfolios WHERE user_id=u.id ORDER BY position ASC, created_at DESC LIMIT 1) AS fallback_avatar,
        (SELECT content FROM messages WHERE conversation_id=c.id ORDER BY id DESC LIMIT 1) AS last_message,
        (SELECT created_at FROM messages WHERE conversation_id=c.id ORDER BY id DESC LIMIT 1) AS last_at,
        (SELECT COUNT(*) FROM messages WHERE conversation_id=c.id AND sender_id!=:me AND is_read=FALSE) AS unread
    FROM conversations c
    JOIN users u ON u.id = CASE WHEN c.user1_id=:me THEN c.user2_id ELSE c.user1_id END
    WHERE c.user1_id=:me OR c.user2_id=:me
    ORDER BY last_at DESC NULLS LAST
");
$convs_stmt->execute(['me' => $me]);
$conversations = $convs_stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Load messages for the open conversation ──────────────────────────────────
$open_messages = [];
if ($open_conv_id) {
    $msg_stmt = $db->prepare("SELECT m.id, m.sender_id, m.content, m.created_at FROM messages m WHERE m.conversation_id=:conv ORDER BY m.id ASC");
    $msg_stmt->execute(['conv' => $open_conv_id]);
    $open_messages = $msg_stmt->fetchAll(PDO::FETCH_ASSOC);
}

function timeAgo($ts) {
    if (!$ts) return '';
    $diff = time() - strtotime($ts);
    if ($diff < 60) return "à l'instant";
    if ($diff < 3600) return floor($diff/60) . ' min';
    if ($diff < 86400) return floor($diff/3600) . ' h';
    return date('d/m', strtotime($ts));
}

$my_info = (new User($db))->getUserProfile($me);
?>
<!doctype html>
<html lang="fr" <?php if((($_COOKIE['chicbook_theme']??'dark')==='light'))echo' class="light"';?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messagerie - ChicBook</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { theme: { extend: { colors: { brand: '#d4a5d4' } } } }</script>
    <link rel="stylesheet" href="assets/css/custom.css">
    <style>
        html, body { height: 100%; overflow: hidden; }
        #chat-messages { scrollbar-width: thin; scrollbar-color: #333 transparent; }
        #chat-messages::-webkit-scrollbar { width: 4px; }
        #chat-messages::-webkit-scrollbar-track { background: transparent; }
        #chat-messages::-webkit-scrollbar-thumb { background: #333; border-radius: 4px; }
        #msg-input { resize: none; overflow: hidden; min-height: 44px; max-height: 120px; }

        /* Barre de conversations horizontale */
        #conv-topbar { display:flex; flex-direction:row; gap:4px; overflow-x:auto; padding:10px 16px; scrollbar-width:none; }
        #conv-topbar::-webkit-scrollbar { display:none; }
        .conv-bubble { position: relative; display: flex; flex-direction: column; align-items: center; gap: 0; cursor: pointer; padding: 6px 8px 8px; border-radius: 12px; transition: background .15s; flex-shrink:0; }
        .conv-bubble:hover { background: #1a1a1a; }
        .conv-bubble.active { background: #1e1e1e; }
        .conv-bubble .bubble-name { max-height: 0; overflow: hidden; opacity: 0; transition: max-height .2s ease, opacity .2s ease, margin-top .2s ease; font-size: 11px; color: #aaa; text-align: center; white-space: nowrap; margin-top: 0; max-width: 64px; text-overflow: ellipsis; }
        .conv-bubble:hover .bubble-name, .conv-bubble.active .bubble-name { max-height: 20px; opacity: 1; margin-top: 5px; }
        .conv-bubble .unread-dot { position: absolute; top: 4px; right: 4px; width: 10px; height: 10px; background: #d4a5d4; border-radius: 50%; border: 2px solid #0a0a0a; }
    </style>
</head>
<body class="bg-black text-white" style="font-family:'Open Sans',sans-serif;">
<?php include 'includes/header.php'; ?>

<div style="display:flex; flex-direction:column; height:100%; overflow:hidden;">

    <!-- ── Barre du haut : bulles de conversations ───────────────────────── -->
    <div style="border-bottom:1px solid #1a1a1a; background:#0a0a0a; flex-shrink:0;">
        <div id="conv-topbar">
            <?php if (empty($conversations)): ?>
                <p style="color:#444; font-size:12px; padding:8px 0; white-space:nowrap;">Aucune conversation — contactez un talent depuis son profil.</p>
            <?php else: foreach ($conversations as $c): ?>
                <?php
                $is_active  = ($open_conv_id == $c['id']);
                $avatar     = $c['profile_picture_url'] ?: $c['fallback_avatar'];
                $initials   = strtoupper(mb_substr($c['full_name'], 0, 1));
                $first_name = explode(' ', $c['full_name'])[0];
                ?>
                <button id="conv-item-<?= $c['id'] ?>"
                        data-conv-id="<?= $c['id'] ?>"
                        data-other-id="<?= $c['other_id'] ?>"
                        data-name="<?= htmlspecialchars($c['full_name'], ENT_QUOTES) ?>"
                        data-avatar="<?= htmlspecialchars($avatar ?? '', ENT_QUOTES) ?>"
                        data-profession="<?= htmlspecialchars($c['specific_profession'] ?? '', ENT_QUOTES) ?>"
                        class="conv-bubble <?= $is_active ? 'active' : '' ?>">
                    <!-- Avatar circulaire -->
                    <div style="width:52px; height:52px; border-radius:50%; overflow:hidden; background:#d4a5d4; display:flex; align-items:center; justify-content:center; color:#000; font-weight:700; font-size:18px; flex-shrink:0; <?= $is_active ? 'box-shadow:0 0 0 2px #d4a5d4;' : '' ?>">
                        <?php if ($avatar): ?>
                            <img src="<?= htmlspecialchars($avatar) ?>" style="width:100%; height:100%; object-fit:cover;" alt="">
                        <?php else: ?>
                            <?= $initials ?>
                        <?php endif; ?>
                    </div>
                    <!-- Nom (visible au hover / actif) -->
                    <span class="bubble-name"><?= htmlspecialchars($first_name) ?></span>
                    <!-- Badge non lu -->
                    <span id="unread-badge-<?= $c['id'] ?>" class="unread-dot" <?= $c['unread'] > 0 ? '' : 'style="display:none;"' ?>></span>
                </button>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <!-- ── Zone de chat (pleine largeur) ─────────────────────────────────── -->
    <div style="flex:1; display:flex; flex-direction:column; min-width:0; overflow:hidden;">

        <!-- État vide -->
        <div id="chat-empty" class="<?= $open_conv_id ? 'hidden' : 'flex' ?> flex-grow items-center justify-center flex-col gap-4 text-center">
            <div class="w-16 h-16 rounded-full bg-[#111] flex items-center justify-center text-3xl">💬</div>
            <p class="text-[#555] text-sm">Sélectionnez une conversation<br>ou contactez un talent depuis son profil.</p>
        </div>

        <!-- Chat actif -->
        <div id="chat-active" class="<?= $open_conv_id ? 'flex' : 'hidden' ?> flex-col" style="flex:1; min-height:0; overflow:hidden;">

            <!-- Header chat -->
            <div id="chat-header" class="flex items-center gap-3 px-6 py-4 border-b border-[#1a1a1a] flex-shrink-0 bg-[#0a0a0a]">
                <div id="chat-avatar" class="w-9 h-9 rounded-full overflow-hidden bg-[#d4a5d4] flex items-center justify-center text-black font-bold text-sm flex-shrink-0">
                    <?php $open_avatar = $open_other ? ($open_other['profile_picture_url'] ?: $open_other['fallback_avatar']) : null; ?>
                    <?php if ($open_avatar): ?>
                        <img src="<?= htmlspecialchars($open_avatar) ?>" class="w-full h-full object-cover" alt="">
                    <?php else: ?>
                        <span id="chat-avatar-initial"><?= $open_other ? strtoupper(mb_substr($open_other['full_name'], 0, 1)) : '' ?></span>
                    <?php endif; ?>
                </div>
                <div class="flex-grow min-w-0">
                    <a id="chat-name-link" href="<?= $open_other ? 'profil.php?id='.$open_other['id'] : '#' ?>" class="text-white font-semibold text-sm hover:text-[#d4a5d4] transition-colors">
                        <?= $open_other ? htmlspecialchars($open_other['full_name']) : '' ?>
                    </a>
                    <p id="chat-profession" class="text-[#555] text-xs"><?= $open_other ? htmlspecialchars($open_other['specific_profession'] ?? '') : '' ?></p>
                </div>
                <a id="chat-profile-link" href="<?= $open_other ? 'profil.php?id='.$open_other['id'] : '#' ?>" class="text-[#555] text-xs hover:text-[#d4a5d4] transition-colors flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    Voir le profil
                </a>
            </div>

            <!-- Messages -->
            <div id="chat-messages" class="overflow-y-auto px-6 py-5 gap-3" style="flex:1; min-height:0;">
                <?php foreach ($open_messages as $msg): ?>
                    <?php $is_mine = ($msg['sender_id'] == $me); ?>
                    <div class="flex <?= $is_mine ? 'justify-end' : 'justify-start' ?>" data-msg-id="<?= $msg['id'] ?>">
                        <div class="max-w-[70%] px-4 py-2.5 rounded-2xl text-sm leading-relaxed <?= $is_mine ? 'bg-[#d4a5d4] text-black rounded-br-sm' : 'bg-[#1a1a1a] text-white rounded-bl-sm' ?>">
                            <?= nl2br(htmlspecialchars($msg['content'])) ?>
                            <div class="text-[10px] mt-1 <?= $is_mine ? 'text-black/50' : 'text-[#555]' ?> text-right"><?= date('H:i', strtotime($msg['created_at'])) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Zone de saisie -->
            <div class="flex-shrink-0 border-t border-[#1a1a1a] px-5 py-4 bg-[#0a0a0a]">
                <div class="flex items-end gap-3">
                    <textarea id="msg-input" placeholder="Écrivez un message…"
                              class="flex-grow bg-[#111] border border-[#2a2a2a] text-white text-sm rounded-2xl px-4 py-2.5 outline-none focus:border-[#d4a5d4] transition-colors placeholder-[#444]"
                              rows="1"></textarea>
                    <button id="send-btn"
                            class="w-10 h-10 rounded-full bg-[#d4a5d4] flex items-center justify-center flex-shrink-0 hover:opacity-90 transition-opacity disabled:opacity-40"
                            onclick="sendMessage()">
                        <svg class="w-4 h-4 text-black" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M22 2L11 13M22 2L15 22l-4-9-9-4 20-7z"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const ME = <?= $me ?>;

// ── Bind conversation clicks ──────────────────────────────────────────────
document.querySelectorAll('.conv-bubble[data-conv-id]').forEach(btn => {
    btn.addEventListener('click', () => {
        openConv(
            parseInt(btn.dataset.convId),
            parseInt(btn.dataset.otherId),
            btn.dataset.name,
            btn.dataset.avatar,
            btn.dataset.profession
        );
    });
});
let currentConvId = <?= $open_conv_id ?? 'null' ?>;
let lastMsgId = <?= !empty($open_messages) ? end($open_messages)['id'] : 0 ?>;
let pollTimer = null;

// ── Auto-resize textarea ──────────────────────────────────────────────────
const msgInput = document.getElementById('msg-input');
msgInput.addEventListener('input', () => {
    msgInput.style.height = 'auto';
    msgInput.style.height = Math.min(msgInput.scrollHeight, 120) + 'px';
});
msgInput.addEventListener('keydown', e => {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
});

// ── Scroll to bottom ──────────────────────────────────────────────────────
function scrollToBottom() {
    const el = document.getElementById('chat-messages');
    if (el) el.scrollTop = el.scrollHeight;
}
scrollToBottom();

// ── Render a single message bubble ───────────────────────────────────────
function renderMessage(msg) {
    const isMine = (msg.sender_id == ME);
    const time = new Date(msg.created_at).toLocaleTimeString('fr-FR', {hour:'2-digit', minute:'2-digit'});
    const content = msg.content.replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>');
    const div = document.createElement('div');
    div.className = 'flex ' + (isMine ? 'justify-end' : 'justify-start');
    div.dataset.msgId = msg.id;
    div.innerHTML = `
        <div class="max-w-[70%] px-4 py-2.5 rounded-2xl text-sm leading-relaxed ${isMine ? 'bg-[#d4a5d4] text-black rounded-br-sm' : 'bg-[#1a1a1a] text-white rounded-bl-sm'}">
            ${content}
            <div class="text-[10px] mt-1 ${isMine ? 'text-black/50' : 'text-[#555]'} text-right">${time}</div>
        </div>`;
    return div;
}

// ── Send message ──────────────────────────────────────────────────────────
function sendMessage() {
    if (!currentConvId) return;
    const content = msgInput.value.trim();
    if (!content) return;
    msgInput.value = '';
    msgInput.style.height = 'auto';

    fetch('messagerie.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: `action=send&conversation_id=${currentConvId}&content=${encodeURIComponent(content)}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            const msg = {id: data.id, sender_id: ME, content: content, created_at: data.created_at};
            const el = renderMessage(msg);
            document.getElementById('chat-messages').appendChild(el);
            lastMsgId = data.id;
            scrollToBottom();
        }
    });
}

// ── Poll for new messages ─────────────────────────────────────────────────
function startPolling() {
    if (pollTimer) clearInterval(pollTimer);
    pollTimer = setInterval(poll, 2500);
}
function poll() {
    if (!currentConvId) return;
    fetch('messagerie.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: `action=poll&conversation_id=${currentConvId}&last_id=${lastMsgId}`
    })
    .then(r => r.json())
    .then(data => {
        if (!data.ok) return;
        const container = document.getElementById('chat-messages');
        data.messages.forEach(msg => {
            container.appendChild(renderMessage(msg));
            lastMsgId = msg.id;
        });
        if (data.messages.length) scrollToBottom();
        // Update unread badges
        if (data.unread) {
            Object.entries(data.unread).forEach(([cid, count]) => {
                const badge = document.getElementById('unread-badge-' + cid);
                if (!badge) return;
                badge.style.display = (count > 0 && cid != currentConvId) ? 'block' : 'none';
            });
        }
    });
}
if (currentConvId) startPolling();

// ── Open a conversation ───────────────────────────────────────────────────
function openConv(convId, otherId, otherName, otherAvatar, otherProfession) {
    // Update sidebar active state
    document.querySelectorAll('.conv-bubble').forEach(el => {
        el.classList.remove('active');
        const av = el.querySelector('div');
        if (av) av.style.boxShadow = '';
    });
    const item = document.getElementById('conv-item-' + convId);
    if (item) {
        item.classList.add('active');
        const av = item.querySelector('div');
        if (av) av.style.boxShadow = '0 0 0 2px #d4a5d4';
    }

    // Hide empty state, show chat
    document.getElementById('chat-empty').classList.add('hidden');
    const chatActive = document.getElementById('chat-active');
    chatActive.classList.remove('hidden');
    chatActive.style.display = 'flex';

    // Update header
    const avatarEl = document.getElementById('chat-avatar');
    const initial = otherName ? otherName.charAt(0).toUpperCase() : '?';
    if (otherAvatar) {
        avatarEl.innerHTML = `<img src="${otherAvatar}" class="w-full h-full object-cover" alt="">`;
    } else {
        avatarEl.innerHTML = `<span>${initial}</span>`;
    }
    document.getElementById('chat-name-link').textContent = otherName;
    document.getElementById('chat-name-link').href = 'profil.php?id=' + otherId;
    document.getElementById('chat-profession').textContent = otherProfession;
    document.getElementById('chat-profile-link').href = 'profil.php?id=' + otherId;

    // Clear messages area
    const container = document.getElementById('chat-messages');
    container.innerHTML = '<div class="text-[#333] text-xs text-center py-4">Chargement…</div>';

    // Mark badge as read
    const badge = document.getElementById('unread-badge-' + convId);
    if (badge) badge.style.display = 'none';

    currentConvId = convId;
    lastMsgId = 0;

    // Load messages via poll (with last_id=0 to get all)
    fetch('messagerie.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: `action=poll&conversation_id=${convId}&last_id=0`
    })
    .then(r => r.json())
    .then(data => {
        container.innerHTML = '';
        if (!data.ok) return;
        data.messages.forEach(msg => {
            container.appendChild(renderMessage(msg));
            lastMsgId = msg.id;
        });
        scrollToBottom();
    });

    startPolling();
    // Update URL without reload
    history.replaceState({}, '', 'messagerie.php?conv=' + convId);
}
</script>
</body>
</html>
