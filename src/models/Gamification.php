<?php
/**
 * Model Gamification
 * EGI - Escola de Gestão Imobiliária
 */

class Gamification {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Obter ranking geral
     */
    public function getRanking($limit = 10) {
        $sql = "SELECT * FROM vw_ranking_geral LIMIT :limit";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Obter ranking da imobiliária
     */
    public function getRankingByImobiliaria($imobiliariaId, $limit = 10) {
        $sql = "SELECT 
                    u.id,
                    u.nome,
                    u.email,
                    u.avatar,
                    u.nivel,
                    u.pontos_totais,
                    COUNT(DISTINCT ulp.lesson_id) as aulas_concluidas,
                    COUNT(DISTINCT asub.id) as atividades_entregues,
                    COUNT(DISTINCT ub.badge_id) as badges_conquistadas,
                    RANK() OVER (ORDER BY u.pontos_totais DESC) as posicao_ranking
                FROM users u
                INNER JOIN user_imobiliaria ui ON u.id = ui.user_id
                LEFT JOIN user_lesson_progress ulp ON u.id = ulp.user_id AND ulp.status = 'concluido'
                LEFT JOIN activity_submissions asub ON u.id = asub.user_id
                LEFT JOIN user_badges ub ON u.id = ub.user_id
                WHERE ui.imobiliaria_id = :imobiliaria_id AND u.role_id = 2 AND u.status = 'aprovado'
                GROUP BY u.id
                ORDER BY u.pontos_totais DESC
                LIMIT :limit";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':imobiliaria_id', $imobiliariaId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Obter badges do usuário
     */
    public function getUserBadges($userId) {
        $sql = "SELECT b.*, ub.conquistada_em, ub.visualizada
                FROM user_badges ub
                INNER JOIN badges b ON ub.badge_id = b.id
                WHERE ub.user_id = :user_id
                ORDER BY ub.conquistada_em DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Obter todas as badges disponíveis
     */
    public function getAllBadges() {
        $sql = "SELECT * FROM badges WHERE ativo = 1 ORDER BY nivel_requerido ASC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }
    
    /**
     * Dar badge ao usuário
     */
    public function awardBadge($userId, $badgeId) {
        // Verificar se já possui
        $sql = "SELECT id FROM user_badges WHERE user_id = :user_id AND badge_id = :badge_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId, ':badge_id' => $badgeId]);
        
        if($stmt->fetch()) {
            return ['success' => false, 'message' => 'Badge já conquistada'];
        }
        
        // Buscar informações da badge
        $sql = "SELECT * FROM badges WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $badgeId]);
        $badge = $stmt->fetch();
        
        if(!$badge) {
            return ['success' => false, 'message' => 'Badge não encontrada'];
        }
        
        // Dar badge
        $sql = "INSERT INTO user_badges (user_id, badge_id) VALUES (:user_id, :badge_id)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId, ':badge_id' => $badgeId]);
        
        // Adicionar pontos bônus se houver
        if($badge['pontos_bonus'] > 0) {
            $sql = "CALL sp_adicionar_pontos(:user_id, :pontos, 'badge_earned', :descricao, NULL, NULL)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':user_id' => $userId,
                ':pontos' => $badge['pontos_bonus'],
                ':descricao' => "Badge conquistada: " . $badge['nome']
            ]);
        }
        
        // Criar notificação
        $this->createNotification($userId, 'Badge Conquistada!', 'Parabéns! Você conquistou a badge "' . $badge['nome'] . '"', 'sucesso');
        
        return ['success' => true, 'badge' => $badge];
    }
    
    /**
     * Verificar e dar badges automáticas
     */
    public function checkAndAwardBadges($userId) {
        $userModel = new User();
        $stats = $userModel->getStats($userId);
        
        // Badge: Dedicado (10 aulas concluídas)
        if($stats['aulas_concluidas'] >= 10) {
            $this->awardBadge($userId, 3);
        }
        
        // Badge: Pontual (5 atividades no prazo)
        $sql = "SELECT COUNT(*) as total FROM activity_submissions 
                WHERE user_id = :user_id AND atrasado = 0";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        $result = $stmt->fetch();
        
        if($result['total'] >= 5) {
            $this->awardBadge($userId, 2);
        }
        
        // Badge: Top 3
        $ranking = $userModel->getUserRanking($userId);
        if($ranking <= 3) {
            $this->awardBadge($userId, 5);
        }
    }
    
    /**
     * Obter histórico de pontos
     */
    public function getPointsHistory($userId, $limit = 20) {
        $sql = "SELECT * FROM gamification_points 
                WHERE user_id = :user_id 
                ORDER BY created_at DESC 
                LIMIT :limit";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Criar notificação
     */
    public function createNotification($userId, $titulo, $mensagem, $tipo = 'info', $link = null) {
        $sql = "INSERT INTO notificacoes (user_id, titulo, mensagem, tipo, link) 
                VALUES (:user_id, :titulo, :mensagem, :tipo, :link)";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':user_id' => $userId,
            ':titulo' => $titulo,
            ':mensagem' => $mensagem,
            ':tipo' => $tipo,
            ':link' => $link
        ]);
    }
    
    /**
     * Obter notificações não lidas
     */
    public function getUnreadNotifications($userId) {
        $sql = "SELECT * FROM notificacoes 
                WHERE user_id = :user_id AND lida = 0 
                ORDER BY created_at DESC 
                LIMIT 10";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Marcar notificação como lida
     */
    public function markAsRead($notificationId) {
        $sql = "UPDATE notificacoes SET lida = 1 WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $notificationId]);
    }
    
    /**
     * Obter estatísticas de gamificação
     */
    public function getGamificationStats($userId) {
        $userModel = new User();
        $stats = $userModel->getStats($userId);
        $ranking = $userModel->getUserRanking($userId);
        $badges = $this->getUserBadges($userId);
        
        // Calcular próximo nível
        $nivel_atual = $stats['nivel'];
        $pontos_proximo_nivel = $this->getPontosParaNivel($nivel_atual + 1);
        $pontos_faltantes = $pontos_proximo_nivel - $stats['pontos_totais'];
        
        return [
            'nivel' => $nivel_atual,
            'pontos_totais' => $stats['pontos_totais'],
            'pontos_proximo_nivel' => $pontos_proximo_nivel,
            'pontos_faltantes' => max(0, $pontos_faltantes),
            'porcentagem_nivel' => min(100, ($stats['pontos_totais'] / $pontos_proximo_nivel) * 100),
            'ranking_posicao' => $ranking,
            'badges_total' => count($badges),
            'aulas_concluidas' => $stats['aulas_concluidas'],
            'atividades_entregues' => $stats['atividades_entregues']
        ];
    }
    
    /**
     * Calcular pontos necessários para nível
     */
    private function getPontosParaNivel($nivel) {
        $pontos = [
            1 => 0,
            2 => 100,
            3 => 250,
            4 => 500,
            5 => 1000,
            6 => 2000
        ];
        
        return $pontos[$nivel] ?? 2000;
    }
    
    /**
     * Obter calendário de deadlines
     */
    public function getCalendar($mes = null, $ano = null) {
        $mes = $mes ?? date('m');
        $ano = $ano ?? date('Y');
        
        $sql = "SELECT * FROM deadlines 
                WHERE MONTH(data_evento) = :mes AND YEAR(data_evento) = :ano
                ORDER BY data_evento ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':mes' => $mes, ':ano' => $ano]);
        return $stmt->fetchAll();
    }
    
    /**
     * Obter próximos deadlines
     */
    public function getUpcomingDeadlines($userId, $dias = 7) {
        $sql = "SELECT d.*, a.titulo as activity_titulo, asub.status as submission_status
                FROM deadlines d
                LEFT JOIN activities a ON d.activity_id = a.id
                LEFT JOIN activity_submissions asub ON a.id = asub.activity_id AND asub.user_id = :user_id
                WHERE d.data_evento BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL :dias DAY)
                AND (asub.id IS NULL OR asub.status = 'pendente')
                ORDER BY d.data_evento ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':dias' => $dias
        ]);
        return $stmt->fetchAll();
    }
}
?>