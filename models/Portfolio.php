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
        $query = "SELECT * FROM portfolios WHERE user_id = :u ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':u', $user_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}