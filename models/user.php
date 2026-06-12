<?php

class User
{
    private $conn;
    private $table_name = "users";

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function emailExists($email)
    {
        $query = "SELECT id FROM " . $this->table_name . " WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":email", $email);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    public function verifyCredentials($email, $password)
    {
        $query = "SELECT id, password_hash, full_name, role FROM " . $this->table_name . " WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":email", $email);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (password_verify($password, $row['password_hash'])) {
                return $row;
            }
        }
        return false;
    }

    public function saveLoginCode($user_id, $code)
    {
        $query = "UPDATE " . $this->table_name . " SET login_code = :code WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":code", $code);
        $stmt->bindParam(":id", $user_id);
        return $stmt->execute();
    }

    public function verifyLoginCode($user_id, $code)
    {
        $query = "SELECT id FROM " . $this->table_name . " WHERE id = :id AND login_code = :code LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $user_id);
        $stmt->bindParam(":code", $code);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    public function clearLoginCode($user_id)
    {
        $query = "UPDATE " . $this->table_name . " SET login_code = NULL WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $user_id);
        return $stmt->execute();
    }

    public function updateExpertise($user_id, $profession, $tags)
    {
        $stmt = $this->conn->prepare("UPDATE " . $this->table_name . " SET specific_profession = :prof, expertise_tags = :tags WHERE id = :id");
        $stmt->bindParam(":prof", $profession);
        $stmt->bindParam(":tags", $tags);
        $stmt->bindParam(":id", $user_id);
        $ok = $stmt->execute();

        // Sync user_professions avec la nouvelle profession
        if ($ok && !empty($profession)) {
            $stmtP = $this->conn->prepare("SELECT id FROM professions WHERE name = :name LIMIT 1");
            $stmtP->execute([':name' => $profession]);
            $prof = $stmtP->fetch(PDO::FETCH_ASSOC);
            if ($prof) {
                $this->conn->prepare("DELETE FROM user_professions WHERE user_id = :uid")->execute([':uid' => $user_id]);
                $this->conn->prepare("INSERT INTO user_professions (user_id, profession_id) VALUES (:uid, :pid)")->execute([':uid' => $user_id, ':pid' => $prof['id']]);
            }
        }

        return $ok;
    }
    public function updateProfilePicture($user_id, $image_url)
    {
        $query = "UPDATE " . $this->table_name . " SET profile_picture_url = :url WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":url", $image_url);
        $stmt->bindParam(":id", $user_id);
        return $stmt->execute();
    }
    public function getUserProfile($user_id)
    {

        $query = "SELECT u.id, u.full_name, u.email, u.specific_profession, u.expertise_tags, u.city, u.country, u.bio, u.profile_picture_url, u.birth_date, u.profile_theme, u.show_age, u.gender, p.name as profession_name, p.has_measurements
                  FROM " . $this->table_name . " u
                  LEFT JOIN user_professions up ON u.id = up.user_id
                  LEFT JOIN professions p ON up.profession_id = p.id
                  LEFT JOIN measurements m ON u.id = m.user_id
                  WHERE u.id = :id LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $user_id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    public function updateTheme($user_id, $theme) {
        $allowed = ['classique', 'editorial', 'luxe'];
        $theme = in_array($theme, $allowed) ? $theme : 'classique';
        $stmt = $this->conn->prepare("UPDATE users SET profile_theme = :theme WHERE id = :id");
        return $stmt->execute(['theme' => $theme, 'id' => $user_id]);
    }

    public function updateInfo($user_id, $bio, $profile_picture = null)
    {
        $sql = "UPDATE users SET bio = :bio";
        if ($profile_picture) {
            $sql .= ", profile_picture_url = :pic";
        }
        $sql .= " WHERE id = :id";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':bio', $bio);
        $stmt->bindParam(':id', $user_id);
        if ($profile_picture) {
            $stmt->bindParam(':pic', $profile_picture);
        }
        return $stmt->execute();
    }

    public function updateGeneralInfo($user_id, $full_name, $city, $country, $show_age = false, $gender = '', $birth_date = null)
    {
        $query = "UPDATE " . $this->table_name . " SET full_name = :name, city = :city, country = :country, show_age = :show_age, gender = :gender, birth_date = :birth_date WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":name", $full_name);
        $stmt->bindParam(":city", $city);
        $stmt->bindParam(":country", $country);
        $stmt->bindParam(":show_age", $show_age, PDO::PARAM_BOOL);
        $stmt->bindParam(":gender", $gender);
        $stmt->bindParam(":birth_date", $birth_date);
        $stmt->bindParam(":id", $user_id);
        return $stmt->execute();
    }

    public function getMeasurements($user_id)
    {
        $stmt = $this->conn->prepare("SELECT * FROM measurements WHERE user_id = :id LIMIT 1");
        $stmt->execute(['id' => $user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function upsertMeasurements($user_id, $data)
    {
        $check = $this->conn->prepare("SELECT user_id FROM measurements WHERE user_id = :id");
        $check->execute(['id' => $user_id]);
        if ($check->rowCount() > 0) {
            $stmt = $this->conn->prepare(
                "UPDATE measurements SET height=:height, chest_size=:chest, waist_size=:waist, hip_size=:hip,
                 shoe_size=:shoe, eye_color_id=:eye, hair_color_id=:hair, ethnicity_id=:eth WHERE user_id=:id"
            );
        } else {
            $stmt = $this->conn->prepare(
                "INSERT INTO measurements (user_id, height, chest_size, waist_size, hip_size, shoe_size, eye_color_id, hair_color_id, ethnicity_id)
                 VALUES (:id, :height, :chest, :waist, :hip, :shoe, :eye, :hair, :eth)"
            );
        }
        return $stmt->execute([
            'id'     => $user_id,
            'height' => $data['height'] ?: null,
            'chest'  => $data['chest_size'] ?: null,
            'waist'  => $data['waist_size'] ?: null,
            'hip'    => $data['hip_size'] ?: null,
            'shoe'   => $data['shoe_size'] ?: null,
            'eye'    => $data['eye_color_id'] ?: null,
            'hair'   => $data['hair_color_id'] ?: null,
            'eth'    => $data['ethnicity_id'] ?: null,
        ]);
    }

    public function updatePassword($user_id, $current_password, $new_password)
    {

        $query = "SELECT password_hash FROM " . $this->table_name . " WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $user_id);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (password_verify($current_password, $row['password_hash'])) {

            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $update_query = "UPDATE " . $this->table_name . " SET password_hash = :hash WHERE id = :id";
            $update_stmt = $this->conn->prepare($update_query);
            $update_stmt->bindParam(":hash", $new_hash);
            $update_stmt->bindParam(":id", $user_id);
            return $update_stmt->execute();
        }
        return false;
    }
    public function create($data)
    {
        try {
            $this->conn->beginTransaction();

            // 1. On retire birth_date d'ici, ça n'existe pas dans 'users' !
            $query = "INSERT INTO " . $this->table_name . "
                      (role, gender, full_name, email, password_hash, city, country)
                      VALUES ('talent', :gender, :full_name, :email, :password_hash, :city, :country)";

            $stmt = $this->conn->prepare($query);
            $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);

            $stmt->bindParam(":gender", $data['gender']);
            
            // Petite sécurité au cas où tu utilises prenom+nom OU nom_complet
            $full_name = '';
            if (isset($data['prenom']) && isset($data['nom'])) {
                $full_name = trim($data['prenom']) . ' ' . trim($data['nom']);
            } else {
                $full_name = trim($data['nom_complet'] ?? 'Talent');
            }
            
            $stmt->bindParam(":full_name", $full_name);
            $stmt->bindParam(":email", $data['email']);
            $stmt->bindParam(":password_hash", $hashed_password);
            $stmt->bindParam(":city", $data['ville']);
            $stmt->bindParam(":country", $data['pays']);
            $stmt->execute();

            // On récupère l'ID de ce nouvel utilisateur fraîchement créé
            $user_id = $this->conn->lastInsertId();

            // 2. On ajoute son métier
            $query_prof = "INSERT INTO user_professions (user_id, profession_id) VALUES (:user_id, :profession_id)";
            $stmt_prof = $this->conn->prepare($query_prof);
            $stmt_prof->bindParam(":user_id", $user_id);
            $stmt_prof->bindParam(":profession_id", $data['metier']);
            $stmt_prof->execute();

            // 3. On ajoute sa langue
            if (!empty($data['langues'])) {
                $query_lang = "INSERT INTO user_languages (user_id, language_id) VALUES (:user_id, :language_id)";
                $stmt_lang = $this->conn->prepare($query_lang);
                $stmt_lang->bindParam(":user_id", $user_id);
                $stmt_lang->bindParam(":language_id", $data['langues']);
                $stmt_lang->execute();
            }

            // 4. C'EST LÀ QUE VA LA DATE DE NAISSANCE ! (Dans la table measurements)
            if (isset($data['has_measurements']) && $data['has_measurements'] == "1") {
                $query_meas = "INSERT INTO measurements 
                              (user_id, birth_date, height, chest_size, waist_size, hip_size, shoe_size, eye_color_id, hair_color_id, ethnicity_id) 
                              VALUES (:user_id, :birth_date, :height, :chest_size, :waist_size, :hip_size, :shoe_size, :eye_color_id, :hair_color_id, :ethnicity_id)";

                $stmt_meas = $this->conn->prepare($query_meas);
                $stmt_meas->bindParam(":user_id", $user_id);

                $birth_date = !empty($data['birth_date']) ? $data['birth_date'] : null;
                $height = !empty($data['height']) ? $data['height'] : null;
                $chest = !empty($data['chest_size']) ? $data['chest_size'] : null;
                $waist = !empty($data['waist_size']) ? $data['waist_size'] : null;
                $hip = !empty($data['hip_size']) ? $data['hip_size'] : null;
                $shoe = !empty($data['shoe_size']) ? $data['shoe_size'] : null;

                $eye_color_id = !empty($data['eye_color_id']) ? $data['eye_color_id'] : null;
                $hair_color_id = !empty($data['hair_color_id']) ? $data['hair_color_id'] : null;
                $ethnicity_id = !empty($data['ethnicity_id']) ? $data['ethnicity_id'] : null;

                $stmt_meas->bindParam(":birth_date", $birth_date);
                $stmt_meas->bindParam(":height", $height);
                $stmt_meas->bindParam(":chest_size", $chest);
                $stmt_meas->bindParam(":waist_size", $waist);
                $stmt_meas->bindParam(":hip_size", $hip);
                $stmt_meas->bindParam(":shoe_size", $shoe);
                $stmt_meas->bindParam(":eye_color_id", $eye_color_id);
                $stmt_meas->bindParam(":hair_color_id", $hair_color_id);
                $stmt_meas->bindParam(":ethnicity_id", $ethnicity_id);

                $stmt_meas->execute();
            }

            $this->conn->commit();
            return true;
            
        } catch (PDOException $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }
}
