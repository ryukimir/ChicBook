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
  <link rel="stylesheet" href="src/style.css" />
</head>

<body>
  <?php include 'includes/header.php'; ?>

  <main class="auth-main">
    <div class="auth-header-text">
      <h1>Rejoindre ChicBook</h1>
      <h2>Créez votre profil talent et rejoignez le réseau</h2>
      <p>Commencez simplement avec vos informations essentielles.</p>
    </div>

    <div class="auth-card">
      <h3>Inscription Talent</h3>

      <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
          <?php foreach ($errors as $error) echo $error . "<br>"; ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($successMessage)): ?>
        <div class="alert alert-success"><?php echo $successMessage; ?></div>
      <?php else: ?>



        <form class="auth-form" action="inscription.php" method="POST" id="inscription-form">
          <div class="form-group">
            <label class="form-label">Genre</label>
            <div class="gender-radio-group">
              <label class="gender-radio">
                <input type="radio" name="gender" value="femme" <?php echo get_post_value('gender') == 'femme' ? 'checked' : ''; ?> required>
                <span>Femme</span>
              </label>
              <label class="gender-radio">
                <input type="radio" name="gender" value="homme" <?php echo get_post_value('gender') == 'homme' ? 'checked' : ''; ?>>
                <span>Homme</span>
              </label>
              <label class="gender-radio">
                <input type="radio" name="gender" value="non_binaire" <?php echo get_post_value('gender') == 'non_binaire' ? 'checked' : ''; ?>>
                <span>Non-binaire</span>
              </label>
            </div>
          </div>

          <div class="form-group">
            <input type="text" name="nom_complet" placeholder="Nom complet" value="<?php echo get_post_value('nom_complet'); ?>" required>
          </div>

          <div class="form-group">
            <select name="langues" required>
              <option value="" disabled <?php echo empty(get_post_value('langues')) ? 'selected' : ''; ?>>Langues parlées</option>
              <?php foreach ($languages as $lang): ?>
                <option value="<?= $lang['id'] ?>" <?php echo get_post_value('langues') == $lang['id'] ? 'selected' : ''; ?>>
                  <?= htmlspecialchars($lang['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <input type="email" name="email" placeholder="Email" value="<?php echo get_post_value('email'); ?>" required>
          </div>

          <div class="form-group">
            <input type="password" name="password" placeholder="Mot de passe (8 caractères minimum)" required>
          </div>

          <div class="form-group">
            <input type="password" name="password_confirm" placeholder="Confirmer le mot de passe" required>
          </div>

          <div class="form-row">
            <div class="form-group half autocomplete-wrapper">
              <input type="text" name="ville" id="ville-input" placeholder="Ville" value="<?php echo get_post_value('ville'); ?>" autocomplete="off" required>
              <ul id="ville-suggestions" class="suggestions-list"></ul>
            </div>
            <div class="form-group half">
              <select name="pays" id="pays-select" required>
                <option value="" disabled selected>Pays</option>
              </select>
            </div>
          </div>

          <div class="form-group">
            <select name="metier" id="metier-select" required>
              <option value="" disabled <?php echo empty(get_post_value('metier')) ? 'selected' : ''; ?>>Votre métier</option>
              <?php
              $metiers_physiques = ['Mannequin', 'Comédien', 'Danseur'];
              foreach ($professions as $metier):
                $requires_measurements = in_array($metier['name'], $metiers_physiques) ? 'true' : 'false';
              ?>
                <option value="<?php echo $metier['id']; ?>" data-measurements="<?php echo $requires_measurements; ?>" <?php echo get_post_value('metier') == $metier['id'] ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($metier['name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div id="mensurations-bloc" style="display: none; background-color: #2a2a2a; padding: 20px; border-radius: 8px; margin-top: 10px;">
            <h4 style="color: #d4a5d4; margin-bottom: 15px; font-weight: 500;">Vos mensurations</h4>

            <div class="form-group" style="margin-bottom: 15px;">
              <input type="date" name="birth_date" title="Date de naissance">
            </div>

            <div class="form-row" style="margin-bottom: 15px;">
              <div class="form-group half">
                <select name="eye_color_id">
                  <option value="" selected disabled>Couleur des yeux</option>
                  <?php foreach ($eye_colors as $eye): ?>
                    <option value="<?= $eye['id'] ?>"><?= htmlspecialchars($eye['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group half">
                <select name="hair_color_id">
                  <option value="" selected disabled>Couleur des cheveux</option>
                  <?php foreach ($hair_colors as $hair): ?>
                    <option value="<?= $hair['id'] ?>"><?= htmlspecialchars($hair['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <div class="form-group" style="margin-bottom: 15px;">
              <select name="ethnicity_id">
                <option value="" selected disabled>Origine / Ethnie</option>
                <?php foreach ($ethnicities as $eth): ?>
                  <option value="<?= $eth['id'] ?>"><?= htmlspecialchars($eth['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-row" style="margin-bottom: 15px;">
              <div class="form-group half">
                <input type="number" name="height" placeholder="Taille (hauteur en cm)">
              </div>
              <div class="form-group half">
                <input type="text" name="shoe_size" placeholder="Pointure (Ex. 39)">
              </div>
            </div>

            <div class="form-row" style="margin-bottom: 15px;">
              <div class="form-group half">
                <input type="text" name="waist_size" placeholder="Tour de taille (Ex. 64 cm)">

              </div>
              <div class="form-group half">
                <input type="text" name="hip_size" placeholder="Tour de hanches (Ex. 92 cm)">
              </div>
            </div>

            <div class="form-row" style="margin-bottom: 15px;">
              <div class="form-group half">
                <input type="text" name="chest_size" placeholder="Tour de poitrine (Ex. 85)" value="<?php echo get_post_value('chest_size'); ?>">
              </div>
              <div class="form-group half">
                <input type="text" name="cup_size" placeholder="Bonnet (Ex. B, C...)" value="<?php echo get_post_value('cup_size'); ?>">
              </div>
            </div>

            <input type="hidden" name="has_measurements" id="has_measurements" value="0">
          </div>

          <button type="submit" class="btn-submit-auth">Créer mon compte</button>
          <p class="auth-footer-link">
            Déjà un compte ? <a href="connexion.php">Se connecter</a>
          </p>
        </form>

      <?php endif; ?>
    </div>
  </main>

  <script src="script.js"></script>
</body>

</html>