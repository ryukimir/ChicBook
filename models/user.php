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

    public function create($data)
    {
        try {

            $this->conn->beginTransaction();

            $query = "INSERT INTO " . $this->table_name . " 
                      (role, gender, full_name, email, password_hash, city, country) 
                      VALUES ('talent', :gender, :full_name, :email, :password_hash, :city, :country)";

            $stmt = $this->conn->prepare($query);
            $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);

            $stmt->bindParam(":gender", $data['gender']);
            $stmt->bindParam(":full_name", $data['nom_complet']);
            $stmt->bindParam(":email", $data['email']);
            $stmt->bindParam(":password_hash", $hashed_password);
            $stmt->bindParam(":city", $data['ville']);
            $stmt->bindParam(":country", $data['pays']);
            $stmt->execute();

            $user_id = $this->conn->lastInsertId();

            $query_prof = "INSERT INTO user_professions (user_id, profession_id) VALUES (:user_id, :profession_id)";
            $stmt_prof = $this->conn->prepare($query_prof);
            $stmt_prof->bindParam(":user_id", $user_id);
            $stmt_prof->bindParam(":profession_id", $data['metier']);
            $stmt_prof->execute();

            if (!empty($data['langues'])) {
                $query_lang = "INSERT INTO user_languages (user_id, language_id) VALUES (:user_id, :language_id)";
                $stmt_lang = $this->conn->prepare($query_lang);
                $stmt_lang->bindParam(":user_id", $user_id);
                $stmt_lang->bindParam(":language_id", $data['langues']);
                $stmt_lang->execute();
            }

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

            return false;
        }
    }
}
