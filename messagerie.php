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
        #msg-input { resize: none; overflow: hidden; min-height: 44px; max-height: 120px; }
        #chat-messages { scrollbar-width: thin; scrollbar-color: #333 transparent; overflow-x: hidden; }
        #chat-messages::-webkit-scrollbar { width: 4px; }
        #chat-messages::-webkit-scrollbar-thumb { background: #333; border-radius: 4px; }
        .msg-bubble { word-break: break-word; overflow-wrap: anywhere; }
        #msg-root { overflow-x: hidden; }
        #chat-panel { overflow-x: hidden; }

        /* ── Layout desktop : liste gauche + chat droite ── */
        #msg-root { display: flex; height: 100%; overflow: hidden; }
        #conv-list-panel { width: 360px; flex-shrink: 0; border-right: 1px solid #1a1a1a; display: flex; flex-direction: column; overflow: hidden; }
        #chat-panel { flex: 1; display: flex; flex-direction: column; overflow: hidden; min-width: 0; }

        /* Rows de conversation */
        .conv-row { display: flex; align-items: center; gap: 14px; padding: 12px 20px; cursor: pointer; transition: background .12s; border-bottom: 1px solid #111; }
        .conv-row:hover { background: #111; }
        .conv-row.active { background: #141414; }
        .conv-row .cr-avatar { width: 54px; height: 54px; border-radius: 50%; overflow: hidden; background: #d4a5d4; display: flex; align-items: center; justify-content: center; color: #000; font-weight: 700; font-size: 20px; flex-shrink: 0; }
        .conv-row .cr-body { flex: 1; min-width: 0; }
        .conv-row .cr-name { font-weight: 700; font-size: 14px; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .conv-row .cr-preview { font-size: 13px; color: #666; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 2px; }
        .conv-row.unread .cr-name { color: #fff; }
        .conv-row.unread .cr-preview { color: #aaa; font-weight: 600; }
        .conv-row .cr-time { font-size: 11px; color: #555; flex-shrink: 0; }
        .conv-row.unread .cr-time { color: #d4a5d4; }
        .conv-row .cr-dot { width: 9px; height: 9px; border-radius: 50%; background: #d4a5d4; flex-shrink: 0; margin-left: 4px; }

        /* ── Mobile : vue liste ou vue chat ── */
        @media (max-width: 768px) {
          html, body { height: calc(100% - 90px); }
          #mobile-topbar { display: none !important; }
          body.chat-open #mobile-nav { display: none !important; }
          body { padding-left: 0 !important; }
          #msg-root { flex-direction: column; }
          #conv-list-panel { width: 100%; border-right: none; }
          #chat-panel { position: fixed; inset: 0; bottom: 0; z-index: 500; background: #000; transform: translateX(100%); transition: transform .28s cubic-bezier(.4,0,.2,1); }
          #chat-panel.open { transform: translateX(0); }
          #chat-messages { padding: 12px !important; }
          .msg-bubble { max-width: 85% !important; }
          #msg-back-btn { display: flex !important; }
          #chat-profile-link { display: none; }
        }
        #msg-back-btn { display: none; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 50%; background: #1a1a1a; flex-shrink: 0; cursor: pointer; }
    </style>
</head>
<body class="bg-black text-white" style="font-family:'Open Sans',sans-serif;">
<?php include 'includes/header.php'; ?>

<div id="msg-root">

    <!-- ── Panel gauche : liste des conversations ───────────────────────── -->
    <div id="conv-list-panel" style="background:#0a0a0a;">
        <!-- Header -->
        <div style="display:flex; align-items:center; padding:20px 20px 14px; border-bottom:1px solid #1a1a1a; flex-shrink:0;">
            <span style="font-size:22px; font-weight:800; color:#fff;">Messages</span>
        </div>
        <!-- Liste -->
        <div style="flex:1; overflow-y:auto; scrollbar-width:thin; scrollbar-color:#222 transparent;">
            <?php if (empty($conversations)): ?>
                <div style="padding:40px 20px; text-align:center; color:#444; font-size:13px;">
                    Aucune conversation.<br>Contactez un talent depuis son profil.
                </div>
            <?php else: foreach ($conversations as $c): ?>
                <?php
                $is_active  = ($open_conv_id == $c['id']);
                $avatar     = $c['profile_picture_url'] ?: $c['fallback_avatar'];
                $initials   = strtoupper(mb_substr($c['full_name'], 0, 1));
                $preview    = $c['last_message'] ? mb_strimwidth($c['last_message'], 0, 50, '…') : 'Démarrer la conversation';
                ?>
                <div id="conv-item-<?= $c['id'] ?>"
                     data-conv-id="<?= $c['id'] ?>"
                     data-other-id="<?= $c['other_id'] ?>"
                     data-name="<?= htmlspecialchars($c['full_name'], ENT_QUOTES) ?>"
                     data-avatar="<?= htmlspecialchars($avatar ?? '', ENT_QUOTES) ?>"
                     data-profession="<?= htmlspecialchars($c['specific_profession'] ?? '', ENT_QUOTES) ?>"
                     class="conv-row <?= $is_active ? 'active' : '' ?> <?= $c['unread'] > 0 ? 'unread' : '' ?>">
                    <!-- Avatar -->
                    <div class="cr-avatar">
                        <?php if ($avatar): ?>
                            <img src="<?= htmlspecialchars($avatar) ?>" style="width:100%;height:100%;object-fit:cover;" alt="">
                        <?php else: ?>
                            <?= $initials ?>
                        <?php endif; ?>
                    </div>
                    <!-- Texte -->
                    <div class="cr-body">
                        <div class="cr-name"><?= htmlspecialchars($c['full_name']) ?></div>
                        <div class="cr-preview"><?= htmlspecialchars($preview) ?></div>
                    </div>
                    <!-- Temps + dot -->
                    <div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px;flex-shrink:0;">
                        <span class="cr-time"><?= timeAgo($c['last_at']) ?></span>
                        <span id="unread-dot-<?= $c['id'] ?>" class="cr-dot" <?= $c['unread'] > 0 ? '' : 'style="display:none;"' ?>></span>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <!-- ── Panel droit : chat ───────────────────────────────────────────── -->
    <div id="chat-panel" style="background:#000;">

        <!-- État vide (desktop uniquement) -->
        <div id="chat-empty" class="<?= $open_conv_id ? 'hidden' : 'flex' ?>" style="flex:1;align-items:center;justify-content:center;flex-direction:column;gap:16px;text-align:center;">
            <div style="width:64px;height:64px;border-radius:50%;background:#111;display:flex;align-items:center;justify-content:center;font-size:28px;">💬</div>
            <p style="color:#444;font-size:13px;">Sélectionnez une conversation.</p>
        </div>

        <!-- Chat actif -->
        <div id="chat-active" class="<?= $open_conv_id ? 'flex' : 'hidden' ?>" style="flex-direction:column;flex:1;min-height:0;overflow:hidden;">

            <!-- Header chat -->
            <div style="display:flex;align-items:center;gap:12px;padding:14px 20px;border-bottom:1px solid #1a1a1a;flex-shrink:0;background:#0a0a0a;">
                <!-- Bouton retour (mobile seulement) -->
                <button id="msg-back-btn" onclick="closeChatMobile()" title="Retour">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
                </button>
                <!-- Avatar -->
                <div id="chat-avatar" style="width:38px;height:38px;border-radius:50%;overflow:hidden;background:#d4a5d4;display:flex;align-items:center;justify-content:center;color:#000;font-weight:700;font-size:15px;flex-shrink:0;">
                    <?php $open_avatar = $open_other ? ($open_other['profile_picture_url'] ?: $open_other['fallback_avatar']) : null; ?>
                    <?php if ($open_avatar): ?>
                        <img src="<?= htmlspecialchars($open_avatar) ?>" style="width:100%;height:100%;object-fit:cover;" alt="">
                    <?php else: ?>
                        <span><?= $open_other ? strtoupper(mb_substr($open_other['full_name'], 0, 1)) : '' ?></span>
                    <?php endif; ?>
                </div>
                <!-- Nom + profession -->
                <div style="flex:1;min-width:0;">
                    <a id="chat-name-link" href="<?= $open_other ? 'profil.php?id='.$open_other['id'] : '#' ?>" style="font-weight:700;font-size:14px;color:#fff;text-decoration:none;"
                       onmouseover="this.style.color='#d4a5d4'" onmouseout="this.style.color='#fff'">
                        <?= $open_other ? htmlspecialchars($open_other['full_name']) : '' ?>
                    </a>
                    <p id="chat-profession" style="font-size:12px;color:#555;margin:0;"><?= $open_other ? htmlspecialchars($open_other['specific_profession'] ?? '') : '' ?></p>
                </div>
                <a id="chat-profile-link" href="<?= $open_other ? 'profil.php?id='.$open_other['id'] : '#' ?>" style="font-size:12px;color:#555;text-decoration:none;display:flex;align-items:center;gap:4px;"
                   onmouseover="this.style.color='#d4a5d4'" onmouseout="this.style.color='#555'">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    Voir le profil
                </a>
            </div>

            <!-- Messages -->
            <div id="chat-messages" style="flex:1;min-height:0;overflow-y:auto;padding:20px 24px;display:flex;flex-direction:column;gap:10px;">
                <?php foreach ($open_messages as $msg): ?>
                    <?php $is_mine = ($msg['sender_id'] == $me); ?>
                    <div style="display:flex;justify-content:<?= $is_mine ? 'flex-end' : 'flex-start' ?>;" data-msg-id="<?= $msg['id'] ?>">
                        <div class="msg-bubble" style="max-width:70%;padding:10px 14px;border-radius:18px;font-size:13px;line-height:1.5;<?= $is_mine ? 'background:#d4a5d4;color:#000;border-bottom-right-radius:4px;' : 'background:#1a1a1a;color:#fff;border-bottom-left-radius:4px;' ?>">
                            <?= nl2br(htmlspecialchars($msg['content'])) ?>
                            <div style="font-size:10px;margin-top:4px;text-align:right;<?= $is_mine ? 'color:rgba(0,0,0,0.4)' : 'color:#555' ?>"><?= date('H:i', strtotime($msg['created_at'])) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Zone de saisie -->
            <div style="flex-shrink:0;border-top:1px solid #1a1a1a;padding:12px 20px;background:#0a0a0a;">
                <div style="display:flex;align-items:flex-end;gap:10px;">
                    <textarea id="msg-input" placeholder="Écrivez un message…" rows="1"
                              style="flex:1;background:#111;border:1px solid #2a2a2a;color:#fff;font-size:13px;border-radius:20px;padding:10px 16px;outline:none;font-family:inherit;transition:border-color .15s;"
                              onfocus="this.style.borderColor='#d4a5d4'" onblur="this.style.borderColor='#2a2a2a'"></textarea>
                    <button id="send-btn" onclick="sendMessage()"
                            style="width:40px;height:40px;border-radius:50%;background:#d4a5d4;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:opacity .15s;"
                            onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
                        <svg width="16" height="16" fill="none" stroke="#000" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M22 2L11 13M22 2L15 22l-4-9-9-4 20-7z"/></svg>
                    </button>
                </div>
            </div>
        </div>
    </div>

</div><!-- /#msg-root -->

<script>
const ME = <?= $me ?>;
let currentConvId = <?= $open_conv_id ?? 'null' ?>;
let lastMsgId = <?= !empty($open_messages) ? end($open_messages)['id'] : 0 ?>;
let pollTimer = null;

// ── Bind conversation row clicks ──────────────────────────────────────────
document.querySelectorAll('.conv-row[data-conv-id]').forEach(row => {
    row.addEventListener('click', () => {
        openConv(
            parseInt(row.dataset.convId),
            parseInt(row.dataset.otherId),
            row.dataset.name,
            row.dataset.avatar,
            row.dataset.profession
        );
    });
});

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

// ── Render a message bubble ───────────────────────────────────────────────
function renderMessage(msg) {
    const isMine = (msg.sender_id == ME);
    const time = new Date(msg.created_at).toLocaleTimeString('fr-FR', {hour:'2-digit', minute:'2-digit'});
    const content = msg.content.replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>');
    const wrap = document.createElement('div');
    wrap.style.cssText = 'display:flex;justify-content:' + (isMine ? 'flex-end' : 'flex-start') + ';';
    wrap.dataset.msgId = msg.id;
    const bubble = isMine
        ? 'max-width:70%;padding:10px 14px;border-radius:18px;border-bottom-right-radius:4px;font-size:13px;line-height:1.5;background:#d4a5d4;color:#000;'
        : 'max-width:70%;padding:10px 14px;border-radius:18px;border-bottom-left-radius:4px;font-size:13px;line-height:1.5;background:#1a1a1a;color:#fff;';
    wrap.innerHTML = `<div class="msg-bubble" style="${bubble}">${content}<div style="font-size:10px;margin-top:4px;text-align:right;color:${isMine?'rgba(0,0,0,0.4)':'#555'}">${time}</div></div>`;
    return wrap;
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
    }).then(r => r.json()).then(data => {
        if (data.ok) {
            const el = renderMessage({id: data.id, sender_id: ME, content, created_at: data.created_at});
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
    }).then(r => r.json()).then(data => {
        if (!data.ok) return;
        const container = document.getElementById('chat-messages');
        data.messages.forEach(msg => {
            container.appendChild(renderMessage(msg));
            lastMsgId = msg.id;
        });
        if (data.messages.length) scrollToBottom();
        if (data.unread) {
            Object.entries(data.unread).forEach(([cid, count]) => {
                const dot = document.getElementById('unread-dot-' + cid);
                if (dot) dot.style.display = (count > 0 && cid != currentConvId) ? 'block' : 'none';
            });
        }
    });
}
if (currentConvId) startPolling();

// ── Open conversation ─────────────────────────────────────────────────────
function openConv(convId, otherId, otherName, otherAvatar, otherProfession) {
    // Active state sur la liste
    document.querySelectorAll('.conv-row').forEach(r => r.classList.remove('active'));
    const row = document.getElementById('conv-item-' + convId);
    if (row) row.classList.add('active');

    // Affiche le panel chat (mobile: slide-in)
    document.getElementById('chat-empty').classList.add('hidden');
    const chatActive = document.getElementById('chat-active');
    chatActive.classList.remove('hidden');
    chatActive.style.display = 'flex';
    document.getElementById('chat-panel').classList.add('open');
    if (window.innerWidth <= 768) document.body.classList.add('chat-open');

    // Header
    const avatarEl = document.getElementById('chat-avatar');
    if (otherAvatar) {
        avatarEl.innerHTML = `<img src="${otherAvatar}" style="width:100%;height:100%;object-fit:cover;" alt="">`;
    } else {
        avatarEl.innerHTML = `<span>${otherName ? otherName.charAt(0).toUpperCase() : '?'}</span>`;
    }
    const nameLink = document.getElementById('chat-name-link');
    nameLink.textContent = otherName;
    nameLink.href = 'profil.php?id=' + otherId;
    document.getElementById('chat-profession').textContent = otherProfession;
    document.getElementById('chat-profile-link').href = 'profil.php?id=' + otherId;

    // Clear + load messages
    const container = document.getElementById('chat-messages');
    container.innerHTML = '<div style="text-align:center;color:#333;font-size:12px;padding:16px;">Chargement…</div>';
    const dot = document.getElementById('unread-dot-' + convId);
    if (dot) dot.style.display = 'none';

    currentConvId = convId;
    lastMsgId = 0;

    fetch('messagerie.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: `action=poll&conversation_id=${convId}&last_id=0`
    }).then(r => r.json()).then(data => {
        container.innerHTML = '';
        if (!data.ok) return;
        data.messages.forEach(msg => {
            container.appendChild(renderMessage(msg));
            lastMsgId = msg.id;
        });
        scrollToBottom();
    });

    startPolling();
    history.replaceState({}, '', 'messagerie.php?conv=' + convId);
}

// ── Retour mobile ─────────────────────────────────────────────────────────
function closeChatMobile() {
    document.getElementById('chat-panel').classList.remove('open');
    document.querySelectorAll('.conv-row').forEach(r => r.classList.remove('active'));
    if (pollTimer) clearInterval(pollTimer);
    currentConvId = null;
    history.replaceState({}, '', 'messagerie.php');
    document.body.classList.remove('chat-open');
}

// Ouvrir la conv depuis ?with= directement en mode chat sur mobile
<?php if ($open_conv_id): ?>
document.getElementById('chat-panel').classList.add('open');
if (window.innerWidth <= 768) {
    const nav = document.getElementById('mobile-nav');
    if (nav) nav.style.display = 'none';
}
<?php endif; ?>
</script>
</body>
</html>
