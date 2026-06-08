<?php
class Portfolio {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function addPhoto($user_id, $image_url, $title = "") {
        $query = "INSERT INTO portfolios (user_id, image_url, title) VALUES (:u, :url, :t)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':u', $user_id);
        $stmt->bindParam(':url', $image_url);
        $stmt->bindParam(':t', $title);
        return $stmt->execute();
    }

    public function getPhotos($user_id) {
        $query = "SELECT * FROM portfolios WHERE user_id = :u ORDER BY position ASC, created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':u', $user_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPhotoById($photo_id) {
        $stmt = $this->conn->prepare("SELECT * FROM portfolios WHERE id = :id LIMIT 1");
        $stmt->bindParam(':id', $photo_id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function deletePhoto($photo_id, $user_id) {
        $query = "DELETE FROM portfolios WHERE id = :id AND user_id = :u";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $photo_id);
        $stmt->bindParam(':u', $user_id);
        return $stmt->execute();
    }
}