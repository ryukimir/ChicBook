<?php

require_once 'config/database.php';
require_once 'models/User.php';

class AuthController {
    private $db;
    private $userModel;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->userModel = new User($this->db);
    }

    public function handleRegistration($postData) {
        $errors = [];

        $required_fields = ['prenom', 'nom', 'email', 'password', 'password_confirm', 'ville', 'pays', 'metier'];
        foreach ($required_fields as $field) {
            if (empty(trim($postData[$field] ?? ''))) {
                $errors[] = "Veuillez remplir tous les champs.";
                break;
            }
        }

        if (!isset($postData['gender']) || empty($postData['gender'])) {
            $errors[] = "Veuillez sélectionner votre genre.";
        }

        if (!empty($errors)) return $errors;

        if (!filter_var($postData['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Le format de l'adresse email est invalide.";
        }

        // Vérification âge minimum 16 ans côté serveur
        $birth_date = $postData['birth_date'] ?? '';
        if (!empty($birth_date)) {
            $birth = DateTime::createFromFormat('Y-m-d', $birth_date);
            $today = new DateTime();
            if (!$birth || $today->diff($birth)->y < 16) {
                $errors[] = "Vous devez avoir au moins 16 ans pour vous inscrire.";
            }
        } else {
            $errors[] = "La date de naissance est requise.";
        }

        if ($this->userModel->emailExists($postData['email'])) {
            $errors[] = "Cette adresse email est déjà utilisée.";
        }

        if ($postData['password'] !== $postData['password_confirm']) {
            $errors[] = "Les mots de passe ne correspondent pas.";
        }

        if (strlen($postData['password']) < 8) {
            $errors[] = "Le mot de passe doit contenir au moins 8 caractères.";
        } elseif (!preg_match('/[A-Za-z]/', $postData['password']) || !preg_match('/\d/', $postData['password'])) {
            $errors[] = "Le mot de passe doit contenir au moins une lettre et un chiffre.";
        }

        if (empty($errors)) {
            try {
                if ($this->userModel->create($postData)) {
                    return ['success' => true];
                }
                $errors[] = "Une erreur est survenue lors de la création de votre compte.";
            } catch (\PDOException $e) {
                $errors[] = "Erreur BDD : " . $e->getMessage();
            }
        }

        return $errors;
    }
}
?>