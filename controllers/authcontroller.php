<?php

require_once 'config/Database.php';
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

        $required_fields = ['nom_complet', 'email', 'password', 'password_confirm', 'ville', 'pays', 'metier'];
        foreach ($required_fields as $field) {
            if (empty(trim($postData[$field]))) {
                $errors[] = "Veuillez remplir tous les champs.";
                break; 

            }
        }

        if (!empty($errors)) return $errors;

        if (!filter_var($postData['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Le format de l'adresse email est invalide.";
        }

        if ($this->userModel->emailExists($postData['email'])) {
            $errors[] = "Cette adresse email est déjà utilisée.";
        }

        if ($postData['password'] !== $postData['password_confirm']) {
            $errors[] = "Les mots de passe ne correspondent pas.";
        }

        if (!preg_match('/^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d@$!%*#?&]{8,}$/', $postData['password'])) {
            $errors[] = "Le mot de passe doit contenir au moins 8 caractères, une lettre et un chiffre.";
        }

        if (empty($errors)) {
            if ($this->userModel->create($postData)) {
                return ['success' => true];
            } else {
                $errors[] = "Une erreur est survenue lors de la création de votre compte.";
            }
        }

        return $errors;
    }
}
?>