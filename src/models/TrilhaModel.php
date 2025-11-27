<?php
class TrilhaModel {
    private $pdo;

    public function __construct() {
        $this->pdo = getDB();
    }

    public function createTrilha($titulo, $descricao) {
        $stmt = $this->pdo->prepare("INSERT INTO trilhas (titulo, descricao) VALUES (:titulo, :desc)");
        $stmt->execute(['titulo' => $titulo, 'desc' => $descricao]);
        return $this->pdo->lastInsertId();
    }

    public function addConteudo($trilha_id, $tipo, $titulo, $arquivo, $deadline, $pontos) {
        $stmt = $this->pdo->prepare("INSERT INTO conteudos (trilha_id, tipo, titulo, arquivo, deadline, pontos) VALUES (:trilha, :tipo, :titulo, :arquivo, :deadline, :pontos)");
        $stmt->execute(['trilha' => $trilha_id, 'tipo' => $tipo, 'titulo' => $titulo, 'arquivo' => $arquivo, 'deadline' => $deadline, 'pontos' => $pontos]);
    }

    public function getTrilhas() {
        $stmt = $this->pdo->query("SELECT * FROM trilhas");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDeadlines() {
        $stmt = $this->pdo->query("SELECT * FROM conteudos WHERE deadline IS NOT NULL");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>