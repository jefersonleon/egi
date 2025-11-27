<?php
/**
 * DashboardController
 * Gerencia dashboards de mentorados e admin
 */

class DashboardController {
    private $auth;
    private $userModel;
    private $courseModel;
    private $activityModel;
    private $gamificationModel;
    
    public function __construct() {
        $this->auth = new AuthController();
        $this->userModel = new User();
        $this->courseModel = new Course();
        $this->activityModel = new Activity();
        $this->gamificationModel = new Gamification();
    }
    
    /**
     * Dashboard do Mentorado
     */
    public function mentorado() {
        $this->auth->requireAuth();
        
        $userId = $_SESSION['user_id'];
        
        // Buscar dados do usuário
        $user = $this->userModel->findById($userId);
        
        // Estatísticas
        $stats = $this->userModel->getStats($userId);
        
        // Gamificação
        $gamificationStats = $this->gamificationModel->getGamificationStats($userId);
        
        // Cursos com progresso
        $courses = $this->courseModel->getAllWithProgress($userId);
        
        // Atividades
        $activities = $this->activityModel->getUserActivities($userId);
        
        // Próximos deadlines
        $upcomingDeadlines = $this->gamificationModel->getUpcomingDeadlines($userId, 7);
        
        // Ranking
        $ranking = $this->gamificationModel->getRanking(5);
        
        // Badges
        $badges = $this->gamificationModel->getUserBadges($userId);
        
        // Notificações não lidas
        $notifications = $this->gamificationModel->getUnreadNotifications($userId);
        
        // Passar dados para a view
        $data = [
            'user' => $user,
            'stats' => $stats,
            'gamification' => $gamificationStats,
            'courses' => $courses,
            'activities' => $activities,
            'deadlines' => $upcomingDeadlines,
            'ranking' => $ranking,
            'badges' => $badges,
            'notifications' => $notifications
        ];
        
        require_once APP_PATH . '/views/dashboard/mentorado.php';
    }
    
    /**
     * Dashboard do Admin
     */
    public function admin() {
        $this->auth->requireAdmin();
        
        // Estatísticas gerais
        $stats = $this->getAdminStats();
        
        // Usuários pendentes
        $pendingUsers = $this->userModel->getPendingUsers();
        
        // Submissões pendentes
        $pendingSubmissions = $this->activityModel->getPendingSubmissions();
        
        // Ranking geral
        $ranking = $this->gamificationModel->getRanking(10);
        
        // Todos os mentorados
        $mentorados = $this->userModel->getAllMentorados();
        
        $data = [
            'stats' => $stats,
            'pending_users' => $pendingUsers,
            'pending_submissions' => $pendingSubmissions,
            'ranking' => $ranking,
            'mentorados' => $mentorados
        ];
        
        require_once APP_PATH . '/views/dashboard/admin.php';
    }
    
    /**
     * Obter estatísticas para admin
     */
    private function getAdminStats() {
        $db = Database::getInstance()->getConnection();
        
        // Total de mentorados
        $sql = "SELECT COUNT(*) as total FROM users WHERE role_id = 2 AND status = 'aprovado'";
        $total_mentorados = $db->query($sql)->fetch()['total'];
        
        // Pendentes
        $sql = "SELECT COUNT(*) as total FROM users WHERE status = 'pendente'";
        $pendentes = $db->query($sql)->fetch()['total'];
        
        // Total de cursos
        $sql = "SELECT COUNT(*) as total FROM courses WHERE ativo = 1";
        $total_cursos = $db->query($sql)->fetch()['total'];
        
        // Total de aulas
        $sql = "SELECT COUNT(*) as total FROM lessons WHERE ativo = 1";
        $total_aulas = $db->query($sql)->fetch()['total'];
        
        // Atividades pendentes
        $sql = "SELECT COUNT(*) as total FROM activity_submissions WHERE status = 'pendente'";
        $atividades_pendentes = $db->query($sql)->fetch()['total'];
        
        // Acessos hoje
        $sql = "SELECT COUNT(DISTINCT user_id) as total FROM access_logs WHERE DATE(created_at) = CURDATE()";
        $acessos_hoje = $db->query($sql)->fetch()['total'];
        
        return [
            'total_mentorados' => $total_mentorados,
            'pendentes' => $pendentes,
            'total_cursos' => $total_cursos,
            'total_aulas' => $total_aulas,
            'atividades_pendentes' => $atividades_pendentes,
            'acessos_hoje' => $acessos_hoje
        ];
    }
}
?>