<?php

require_once 'controllers/AuthController.php';
require_once 'models/Profession.php';

$db = Database::getInstance()->getConnection();
$professionModel = new Profession($db);
$professions = $professionModel->getAll();

$errors = [];
$successMessage = "";

$eye_colors = $db->query("SELECT * FROM eye_colors ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$hair_colors = $db->query("SELECT * FROM hair_colors ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$ethnicities = $db->query("SELECT * FROM ethnicities ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$languages = $db->query("SELECT * FROM languages ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $authController = new AuthController();
  $result = $authController->handleRegistration($_POST);

  if (isset($result['success']) && $result['success'] === true) {
    $successMessage = "Votre compte a été créé avec succès !";
    $triggerRedirect = true;
  } else {
    $errors = $result;
  }
}

function get_post_value($key)
{
  return isset($_POST[$key]) ? htmlspecialchars($_POST[$key]) : '';
}
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Inscription Talent - ChicBook</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: { extend: { colors: { brand: '#d4a5d4', dark: '#1a1a1a' } } }
    }
  </script>
  <link rel="stylesheet" href="assets/css/custom.css" />
  <style>
    .input-field {
      width: 100%; padding: 15px 20px; border-radius: 8px; border: none;
      background-color: #ffffff; font-size: 15px; font-family: inherit;
      color: #1a1a1a; outline: none;
    }
    .input-field::placeholder { color: #999; }
    select.input-field {
      cursor: pointer; appearance: none;
      background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%231a1a1a' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
      background-repeat: no-repeat; background-position: right 15px center; background-size: 15px;
    }
  </style>
</head>
<body class="bg-black text-white font-['Arial',sans-serif]">
  <?php include 'includes/header.php'; ?>

  <main class="pt-16 pb-20 min-h-screen flex flex-col items-center bg-black">
    <div class="text-center mb-10 text-white">
      <h1 class="text-5xl mb-3 font-bold">Rejoindre ChicBook</h1>
      <h2 class="text-lg font-normal mb-2.5">Créez votre profil talent et rejoignez le réseau</h2>
      <p class="text-sm text-[#aaa]">Commencez simplement avec vos informations essentielles.</p>
    </div>

    <div class="bg-[#1a1a1a] w-full max-w-[620px] rounded-xl p-10 shadow-[0_10px_30px_rgba(0,0,0,0.1)]">
      <h3 class="text-white text-center text-xl mb-8 font-medium">Inscription Talent</h3>

      <?php if (!empty($errors)): ?>
        <div class="bg-[#ffebee] text-[#c62828] border border-[#ef9a9a] p-4 mb-5 rounded-lg text-sm text-center">
          <?php foreach ($errors as $error) echo htmlspecialchars($error) . "<br>"; ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($successMessage)): ?>
        <div class="bg-[#e8f5e9] text-[#2e7d32] border border-[#a5d6a7] p-4 mb-5 rounded-lg text-sm text-center"><?= $successMessage ?></div>
      <?php if (isset($triggerRedirect) && $triggerRedirect === true): ?>
          <script>
            setTimeout(function() {
              window.location.href = 'connexion.php';
            }, 2000);
          </script>
        <?php endif; ?>

      <?php else: ?>

        <form class="flex flex-col gap-5" action="inscription.php" method="POST" id="inscription-form">

          <!-- Genre -->
          <div>
            <label class="block text-white mb-2.5 text-sm">Genre</label>
            <div class="flex gap-2.5 mb-4">
              <?php foreach (['femme' => 'Femme', 'homme' => 'Homme', 'non_binaire' => 'Non-binaire'] as $val => $label): ?>
                <label class="gender-radio flex-1 cursor-pointer">
                  <input type="radio" name="gender" value="<?= $val ?>" <?= get_post_value('gender') == $val ? 'checked' : '' ?> <?= $val === 'femme' ? 'required' : '' ?> class="hidden">
                  <span class="block bg-white text-[#1a1a1a] py-3 text-center rounded-lg text-sm border-2 border-transparent hover:bg-[#f0f0f0] transition-all duration-300"><?= $label ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Prénom / Nom -->
          <div class="flex gap-4">
            <div class="flex-1">
              <input type="text" name="prenom" placeholder="Prénom" value="<?= get_post_value('prenom') ?>" required class="input-field">
            </div>
            <div class="flex-1">
              <input type="text" name="nom" placeholder="Nom" value="<?= get_post_value('nom') ?>" required class="input-field">
            </div>
          </div>

          <!-- Date de naissance -->
          <div>
            <label class="block text-white mb-2 text-sm">Date de naissance</label>
            <input type="date" name="birth_date" value="<?= get_post_value('birth_date') ?>" required class="input-field">
          </div>

          <!-- Langues -->
          <div>
            <select name="langues" required class="input-field">
              <option value="" disabled <?= empty(get_post_value('langues')) ? 'selected' : '' ?>>Langues parlées</option>
              <?php foreach ($languages as $lang): ?>
                <option value="<?= $lang['id'] ?>" <?= get_post_value('langues') == $lang['id'] ? 'selected' : '' ?>><?= htmlspecialchars($lang['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Email -->
          <div>
            <input type="email" name="email" placeholder="Email" value="<?= get_post_value('email') ?>" required class="input-field">
          </div>

          <!-- Mot de passe -->
          <div>
            <input type="password" name="password" placeholder="Mot de passe (8 caractères minimum)" required class="input-field">
          </div>
          <div>
            <input type="password" name="password_confirm" placeholder="Confirmer le mot de passe" required class="input-field">
          </div>

          <!-- Ville / Pays -->
          <div class="flex gap-4">
            <div class="flex-1 relative">
              <input type="text" name="ville" id="ville-input" placeholder="Ville" value="<?= get_post_value('ville') ?>" autocomplete="off" required class="input-field">
              <ul id="ville-suggestions" class="absolute top-full left-0 right-0 bg-white rounded-lg shadow-[0_5px_15px_rgba(0,0,0,0.2)] list-none mt-1 p-0 max-h-[200px] overflow-y-auto z-[1000] hidden">
              </ul>
            </div>
            <div class="flex-1">
              <select name="pays" id="pays-select" required class="input-field">
                <option value="" disabled selected>Pays</option>
              </select>
            </div>
          </div>

          <!-- Métier -->
          <div>
            <select name="metier" id="metier-select" required class="input-field">
              <option value="" disabled <?= empty(get_post_value('metier')) ? 'selected' : '' ?>>Votre métier</option>
              <?php
              $metiers_physiques = ['Mannequin', 'Comédien', 'Danseur'];
              foreach ($professions as $metier):
                $requires_measurements = in_array($metier['name'], $metiers_physiques) ? 'true' : 'false';
              ?>
                <option value="<?= $metier['id'] ?>" data-measurements="<?= $requires_measurements ?>" <?= get_post_value('metier') == $metier['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($metier['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Tags expertise -->
          <div>
            <label class="block text-white mb-2.5 text-sm">Mots-clés qui vous décrivent <span class="text-[#666]">(sélectionnez ceux qui vous correspondent)</span></label>
            <div id="tags-container" class="flex flex-wrap gap-2">
              <?php
              $all_tags = ['Brodeur','Haute couture','Luxe','Editorial','Créatif','Premium','Fashion week','Minimaliste','Streetwear','Avant-garde','Moderne','International','Haut de gamme','Commercial','Artistique','Perlage','Ornementation','Textile','Broderie','Couture','Défilé','Beauté','Hair stylist','Mode','Acteur','Campagne','Publicité','Fashion','Film','Contemporain','Performance','Mouvement','Designer','Sacs','Bijoux','Chaussures','Maroquinerie','Accessoires','Imprimés','Maille','Surface','Mannequin','Maquilleur','Modéliste','Patronage','Atelier','Photographe','Studio','Styliste','Créateur','Photo','Célébrité','Plateau','Vidéaste','Backstage','Réalisateur','Contenu','Coiffeur','Comédien','Danseur'];
              $selected_tags = array_filter(array_map('trim', explode(',', get_post_value('expertise_tags'))));
              foreach ($all_tags as $tag): $sel = in_array($tag, $selected_tags); ?>
              <button type="button" onclick="toggleTag(this)"
                data-tag="<?= htmlspecialchars($tag) ?>"
                class="tag-pill px-3 py-1.5 rounded-full text-xs font-semibold border transition-all duration-150 cursor-pointer <?= $sel ? 'bg-[#d4a5d4] text-black border-[#d4a5d4]' : 'bg-transparent text-[#888] border-[#333] hover:border-[#d4a5d4] hover:text-[#d4a5d4]' ?>">
                <?= htmlspecialchars($tag) ?>
              </button>
              <?php endforeach; ?>
            </div>
            <input type="hidden" name="expertise_tags" id="expertise_tags_input" value="<?= get_post_value('expertise_tags') ?>">
          </div>

          <!-- Bloc mensurations (affiché seulement pour Mannequin/Comédien/Danseur) -->
          <div id="mensurations-bloc" style="display: none;" class="bg-[#2a2a2a] p-5 rounded-lg mt-2.5">
            <h4 class="text-[#d4a5d4] mb-4 font-medium">Vos mensurations</h4>

            <div class="flex gap-4 mb-4">
              <div class="flex-1">
                <select name="eye_color_id" class="input-field">
                  <option value="" selected disabled>Couleur des yeux</option>
                  <?php foreach ($eye_colors as $eye): ?><option value="<?= $eye['id'] ?>"><?= htmlspecialchars($eye['name']) ?></option><?php endforeach; ?>
                </select>
              </div>
              <div class="flex-1">
                <select name="hair_color_id" class="input-field">
                  <option value="" selected disabled>Couleur des cheveux</option>
                  <?php foreach ($hair_colors as $hair): ?><option value="<?= $hair['id'] ?>"><?= htmlspecialchars($hair['name']) ?></option><?php endforeach; ?>
                </select>
              </div>
            </div>

            <div class="mb-4">
              <select name="ethnicity_id" class="input-field">
                <option value="" selected disabled>Origine / Ethnie</option>
                <?php foreach ($ethnicities as $eth): ?><option value="<?= $eth['id'] ?>"><?= htmlspecialchars($eth['name']) ?></option><?php endforeach; ?>
              </select>
            </div>

            <div class="flex gap-4 mb-4">
              <div class="flex-1"><input type="number" name="height" placeholder="Taille (hauteur en cm)" class="input-field"></div>
              <div class="flex-1"><input type="text" name="shoe_size" placeholder="Pointure (Ex. 39)" class="input-field"></div>
            </div>
            <div class="flex gap-4 mb-4">
              <div class="flex-1"><input type="text" name="waist_size" placeholder="Tour de taille (Ex. 64 cm)" class="input-field"></div>
              <div class="flex-1"><input type="text" name="hip_size" placeholder="Tour de hanches (Ex. 92 cm)" class="input-field"></div>
            </div>
            <div class="flex gap-4">
              <div class="flex-1"><input type="text" name="chest_size" placeholder="Tour de poitrine (Ex. 85)" value="<?= get_post_value('chest_size') ?>" class="input-field"></div>
              <div class="flex-1"><input type="text" name="cup_size" placeholder="Bonnet (Ex. B, C...)" value="<?= get_post_value('cup_size') ?>" class="input-field"></div>
            </div>

            <input type="hidden" name="has_measurements" id="has_measurements" value="0">
          </div>

          <button type="submit" class="bg-[#d4a5d4] text-[#1a1a1a] py-4 rounded-full text-base font-bold mt-2.5 hover:opacity-90 transition-opacity cursor-pointer border-none">Créer mon compte</button>
          <p class="text-center text-[#888] text-sm mt-4">
            Déjà un compte ? <a href="connexion.php" class="text-[#888] underline hover:text-[#d4a5d4] transition-colors">Se connecter</a>
          </p>
        </form>

      <?php endif; ?>
    </div>
  </main>

  <style>
    #ville-suggestions li { padding: 12px 20px; cursor: pointer; color: #333; font-size: 14px; border-bottom: 1px solid #f0f0f0; transition: background-color 0.2s; }
    #ville-suggestions li:last-child { border-bottom: none; }
    #ville-suggestions li:hover { background-color: #f0f0f0; color: #d4a5d4; font-weight: bold; }
  </style>
  <script src="assets/js/script.js"></script>
  <script>
  function toggleTag(btn) {
      const active = btn.dataset.selected === '1';
      if (active) {
          btn.dataset.selected = '0';
          btn.style.background = '';
          btn.style.color = '#888';
          btn.style.borderColor = '#333';
      } else {
          btn.dataset.selected = '1';
          btn.style.background = '#d4a5d4';
          btn.style.color = '#000';
          btn.style.borderColor = '#d4a5d4';
      }
      const selected = [...document.querySelectorAll('.tag-pill[data-selected="1"]')].map(b => b.dataset.tag);
      document.getElementById('expertise_tags_input').value = selected.join(',');
  }
  // Init état des pills déjà sélectionnées (re-POST après erreur)
  document.querySelectorAll('.tag-pill').forEach(btn => {
      if (btn.classList.contains('bg-[#d4a5d4]')) {
          btn.dataset.selected = '1';
          btn.style.background = '#d4a5d4';
          btn.style.color = '#000';
          btn.style.borderColor = '#d4a5d4';
          btn.classList.remove('bg-[#d4a5d4]','text-black','border-[#d4a5d4]');
      } else {
          btn.dataset.selected = '0';
      }
  });
  </script>
</body>
</html>

