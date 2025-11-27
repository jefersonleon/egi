<?php
class UserModel {
    private $pdo;

    public function __construct() {
        $this->pdo = getDB();
    }

    public function getUserByEmail($email) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function createUser($nome, $email, $senha, $tipo, $imobiliaria_id) {
        $stmt = $this->pdo->prepare("INSERT INTO users (nome, email, senha, tipo, imobiliaria_id) VALUES (:nome, :email, :senha, :tipo, :imob)");
        $stmt->execute(['nome' => $nome, 'email' => $email, 'senha' => $senha, 'tipo' => $tipo, 'imob' => $imobiliaria_id]);
    }

    public function getPendentes() {
        $stmt = $this->pdo->query("SELECT * FROM users WHERE aprovado = 0 AND tipo = 'mentorado'");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function aprovarUser($id) {
        $stmt = $this->pdo->prepare("UPDATE users SET aprovado = 1 WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }
}
?>