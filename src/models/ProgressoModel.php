<?php
class ProgressoModel {
    private $pdo;

    public function __construct() {
        $this->pdo = getDB();
    }

    public function concluirConteudo($user_id, $conteudo_id, $entrega) {
        $stmt = $this->pdo->prepare("INSERT INTO progressos (user_id, conteudo_id, concluido, entrega, data_conclusao) VALUES (:user, :conteudo, 1, :entrega, NOW()) ON DUPLICATE KEY UPDATE concluido=1, entrega=:entrega, data_conclusao=NOW()");
        $stmt->execute(['user' => $user_id, 'conteudo' => $conteudo_id, 'entrega' => $entrega]);
    }

    public function addPontos($user_id, $pontos) {
        $stmt = $this->pdo->prepare("UPDATE users SET pontos = pontos + :pontos WHERE id = :id");
        $stmt->execute(['pontos' => $pontos, 'id' => $user_id]);
    }

    public function updateNivel($user_id, $nivel) {
        $stmt = $this->pdo->prepare("UPDATE users SET nivel = :nivel WHERE id = :id");
        $stmt->execute(['nivel' => $nivel, 'id' => $user_id]);
    }

    public function getPontos($user_id) {
        $stmt = $this->pdo->prepare("SELECT pontos FROM users WHERE id = :id");
        $stmt->execute(['id' => $user_id]);
        return $stmt->fetchColumn();
    }

    public function countEntregas($user_id) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM progressos p JOIN conteudos c ON p.conteudo_id = c.id WHERE p.user_id = :user AND c.tipo = 'atividade' AND p.concluido = 1");
        $stmt->execute(['user' => $user_id]);
        return $stmt->fetchColumn();
    }

    public function countEntregasNoPrazo($user_id) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM progressos p JOIN conteudos c ON p.conteudo_id = c.id WHERE p.user_id = :user AND c.tipo = 'atividade' AND p.concluido = 1 AND p.data_conclusao <= c.deadline");
        $stmt->execute(['user' => $user_id]);
        return $stmt->fetchColumn();
    }

    public function addBadge($user_id, $badge_id) {
        $stmt = $this->pdo->prepare("INSERT IGNORE INTO user_badges (user_id, badge_id) VALUES (:user, :badge)");
        $stmt->execute(['user' => $user_id, 'badge' => $badge_id]);
    }

    public function getBadges($user_id) {
        $stmt = $this->pdo->prepare("SELECT b.* FROM badges b JOIN user_badges ub ON b.id = ub.badge_id WHERE ub.user_id = :user");
        $stmt->execute(['user' => $user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getProgresso($user_id) {
        // Retorna % de conclusão, etc.
        $total = $this->pdo->query("SELECT COUNT(*) FROM conteudos")->fetchColumn();
        $concluidos = $this->pdo->prepare("SELECT COUNT(*) FROM progressos WHERE user_id = :user AND concluido = 1");
        $concluidos->execute(['user' => $user_id]);
        $perc = ($concluidos->fetchColumn() / $total) * 100;
        return ['perc' => $perc];
    }

    public function getRanking($imobiliaria_id = null) {
        $where = $imobiliaria_id ? "WHERE imobiliaria_id = :imob" : "";
        $stmt = $this->pdo->prepare("SELECT nome, pontos FROM users $where ORDER BY pontos DESC LIMIT 10");
        if ($imobiliaria_id) $stmt->execute(['imob' => $imobiliaria_id]);
        else $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAlertas($user_id) {
        // Alertas para prazos
        $stmt = $this->pdo->prepare("SELECT c.titulo, c.deadline FROM conteudos c LEFT JOIN progressos p ON c.id = p.conteudo_id AND p.user_id = :user WHERE p.concluido = 0 AND c.deadline IS NOT NULL AND c.deadline < DATE_ADD(NOW(), INTERVAL 3 DAY)");
        $stmt->execute(['user' => $user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getConteudo($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM conteudos WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>