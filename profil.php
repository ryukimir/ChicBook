<?php
session_start();
require_once 'config/Database.php';
require_once 'models/User.php';
require_once 'models/Portfolio.php';

$is_logged_in = isset($_SESSION['user_id']);

$db = Database::getInstance()->getConnection();
$userModel = new User($db);

$profile_id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null);

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$domain = $_SERVER['HTTP_HOST'];
$share_url = $protocol . "://" . $domain . dirname($_SERVER['PHP_SELF']) . "/profil.php?id=" . $profile_id;

if (!$profile_id) {
    header("Location: index.php");
    exit();
}

$profile_data = $userModel->getUserProfile($profile_id);

if (!$profile_data) {
    die("Ce profil n'existe pas.");
}

$is_own_profile = ($is_logged_in && $_SESSION['user_id'] == $profile_id);

// Calcul de l'âge à partir de la date de naissance
$age = null;
if (!empty($profile_data['birth_date'])) {
    $birth = new DateTime($profile_data['birth_date']);
    $today = new DateTime();
    $age = $today->diff($birth)->y;
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= htmlspecialchars($profile_data['full_name']) ?> - ChicBook</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      tailwind.config = {
        theme: { extend: { colors: { brand: '#d4a5d4', dark: '#1a1a1a' } } }
      }
    </script>
    <link rel="stylesheet" href="assets/css/custom.css" />
</head>
<body class="bg-white font-['Arial',sans-serif]">
    <?php include 'includes/header.php'; ?>

    <main class="max-w-[1200px] mx-auto mt-[120px] mb-10 px-5 flex gap-[50px]">

        <!-- Sidebar -->
        <aside class="w-[250px] flex-shrink-0">
            <h2 class="text-2xl text-[#1a1a1a] mb-1">
                <?= htmlspecialchars($profile_data['specific_profession'] ?? $profile_data['profession_name'] ?? 'Talent') ?>
            </h2>

            <?php if ($age !== null): ?>
                <p class="text-sm text-[#888] mb-1"><?= $age ?> ans</p>
            <?php endif; ?>

            <p class="text-sm text-[#666] mb-8"><?= htmlspecialchars($profile_data['city']) ?>, <?= htmlspecialchars($profile_data['country']) ?></p>

            <?php if (!empty($profile_data['expertise_tags'])): ?>
                <div class="mb-6 flex flex-wrap gap-2">
                    <?php
                    $tags = explode(',', $profile_data['expertise_tags']);
                    foreach ($tags as $tag):
                        if (trim($tag) != ''):
                    ?>
                            <span class="bg-[#e6e6e6] text-[#1a1a1a] px-3 py-1 rounded-2xl text-xs font-bold">
                                #<?= htmlspecialchars(trim($tag)) ?>
                            </span>
                    <?php
                        endif;
                    endforeach;
                    ?>
                </div>
            <?php endif; ?>

            <div class="bg-[#f9f9f9] p-4 rounded-lg text-sm text-[#444] leading-relaxed mb-5">
                <?php if (!empty($profile_data['bio'])): ?>
                    <p><?= nl2br(htmlspecialchars($profile_data['bio'])) ?></p>
                <?php else: ?>
                    <?php if ($is_own_profile): ?>
                        <div class="text-center text-[#888]">
                            <p>Votre biographie est vide.</p>
                            <a href="edit_profil.php#section-bio" class="inline-block mt-2.5 bg-[#e0e0e0] px-4 py-2 rounded text-xs hover:bg-[#d4a5d4] hover:text-white transition-colors">Ajouter une biographie</a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <div>
                <a href="#" id="btn-share" class="text-[#888] text-sm hover:text-[#d4a5d4] transition-colors">Partager le profil</a>
            </div>
        </aside>

        <!-- Contenu principal -->
        <section class="flex-grow">
            <div class="flex justify-between items-center mb-10 pb-5 border-b border-[#eee]">
                <h1 class="text-[38px] text-[#1a1a1a] font-light"><?= htmlspecialchars($profile_data['full_name']) ?></h1>

                <div class="flex gap-4">
                    <?php if (!$is_own_profile): ?>
                        <button class="bg-[#e6e6e6] text-[#1a1a1a] px-6 py-2.5 rounded-full text-sm cursor-pointer border-none hover:bg-[#d4a5d4] transition-colors">Suivre</button>
                        <button class="bg-[#666] text-white px-6 py-2.5 rounded-full text-sm cursor-pointer border-none hover:bg-[#1a1a1a] transition-colors">Contacter</button>
                    <?php else: ?>
                        <div class="relative inline-block group">
                            <button class="bg-[#666] text-white px-6 py-2.5 rounded-full text-sm cursor-pointer border-none hover:bg-[#1a1a1a] transition-colors">Gérer mon profil ▼</button>
                            <div class="hidden group-hover:block absolute right-0 top-full mt-2.5 bg-[#1a1a1a] min-w-[220px] shadow-[0_8px_16px_rgba(0,0,0,0.5)] border border-[#333] rounded-lg overflow-hidden z-[100] animate-[fadeInUp_0.2s_ease]">
                                <a href="edit_profil.php#section-infos" class="block text-white px-4 py-3 text-sm border-b border-[#222] hover:bg-[#333] hover:text-[#d4a5d4] transition-colors">Modifier le profil</a>
                                <a href="edit_profil.php#section-portfolio" class="block text-white px-4 py-3 text-sm border-b border-[#222] hover:bg-[#333] hover:text-[#d4a5d4] transition-colors">Ajouter des photos</a>
                                <a href="edit_profil.php#section-portfolio-manage" class="block text-white px-4 py-3 text-sm hover:bg-[#333] hover:text-[#e57373] transition-colors">Supprimer des photos</a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Grille masonry portfolio -->
            <div class="[column-count:3] gap-4 md:[column-count:2] sm:[column-count:1]" style="column-count:3; column-gap:15px;">
                <?php
                $portfolioModel = new Portfolio($db);
                $photos = $portfolioModel->getPhotos($profile_id);

                if (empty($photos)): ?>
                    <p class="text-[#888]">Aucune photo dans le book pour le moment.</p>
                <?php else:
                    foreach ($photos as $photo): ?>
                        <div style="break-inside:avoid; margin-bottom:15px;">
                            <img src="<?= htmlspecialchars($photo['image_url']) ?>" alt="Photo de portfolio" class="w-full block rounded-lg hover:scale-[1.02] transition-transform duration-300">
                        </div>
                <?php endforeach;
                endif; ?>
            </div>
        </section>

    </main>

    <script>
        document.getElementById('btn-share').addEventListener('click', async (e) => {
            e.preventDefault();
            const shareData = {
                title: '<?= addslashes(htmlspecialchars($profile_data['full_name'])) ?> - ChicBook',
                text: 'Découvrez le portfolio de <?= addslashes(htmlspecialchars($profile_data['full_name'])) ?> sur ChicBook !',
                url: '<?= $share_url ?>'
            };
            if (navigator.share) {
                try { await navigator.share(shareData); } catch (err) { }
            } else {
                navigator.clipboard.writeText(shareData.url).then(() => {
                    const btn = document.getElementById('btn-share');
                    const orig = btn.innerHTML;
                    btn.innerHTML = '✅ Lien copié !';
                    btn.style.color = '#d4a5d4';
                    setTimeout(() => { btn.innerHTML = orig; btn.style.color = '#888'; }, 3000);
                }).catch(() => alert("Erreur lors de la copie du lien."));
            }
        });
    </script>
</body>
</html>
