<?php
/**
 * Model Course
 * EGI - Escola de Gestão Imobiliária
 */

class Course {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Listar todos os cursos ativos
     */
    public function getAll() {
        $sql = "SELECT * FROM courses WHERE ativo = 1 ORDER BY ordem ASC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }
    
    /**
     * Buscar curso por ID com módulos e aulas
     */
    public function getById($id) {
        $sql = "SELECT * FROM courses WHERE id = :id AND ativo = 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }
    
    /**
     * Obter módulos de um curso
     */
    public function getModules($courseId) {
        $sql = "SELECT * FROM modules WHERE course_id = :course_id AND ativo = 1 ORDER BY ordem ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':course_id' => $courseId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Obter aulas de um módulo
     */
    public function getLessons($moduleId) {
        $sql = "SELECT * FROM lessons WHERE module_id = :module_id AND ativo = 1 ORDER BY ordem ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':module_id' => $moduleId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Obter progresso do usuário em um curso
     */
    public function getUserProgress($userId, $courseId) {
        $sql = "SELECT * FROM vw_progresso_curso 
                WHERE user_id = :user_id AND course_id = :course_id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':course_id' => $courseId
        ]);
        return $stmt->fetch();
    }
    
    /**
     * Listar cursos com progresso do usuário
     */
    public function getAllWithProgress($userId) {
        $sql = "SELECT 
                    c.*,
                    COALESCE(pc.total_aulas, 0) as total_aulas,
                    COALESCE(pc.aulas_concluidas, 0) as aulas_concluidas,
                    COALESCE(pc.porcentagem_progresso, 0) as porcentagem_progresso
                FROM courses c
                LEFT JOIN vw_progresso_curso pc ON c.id = pc.course_id AND pc.user_id = :user_id
                WHERE c.ativo = 1
                ORDER BY c.ordem ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Criar novo curso
     */
    public function create($data) {
        $sql = "INSERT INTO courses (titulo, descricao, thumbnail, ordem) 
                VALUES (:titulo, :descricao, :thumbnail, :ordem)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':titulo' => $data['titulo'],
            ':descricao' => $data['descricao'] ?? null,
            ':thumbnail' => $data['thumbnail'] ?? null,
            ':ordem' => $data['ordem'] ?? 0
        ]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * Criar módulo
     */
    public function createModule($data) {
        $sql = "INSERT INTO modules (course_id, titulo, descricao, ordem) 
                VALUES (:course_id, :titulo, :descricao, :ordem)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':course_id' => $data['course_id'],
            ':titulo' => $data['titulo'],
            ':descricao' => $data['descricao'] ?? null,
            ':ordem' => $data['ordem'] ?? 0
        ]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * Obter estrutura completa do curso
     */
    public function getFullStructure($courseId, $userId = null) {
        $course = $this->getById($courseId);
        if(!$course) return null;
        
        $course['modules'] = [];
        $modules = $this->getModules($courseId);
        
        foreach($modules as $module) {
            $module['lessons'] = $this->getLessons($module['id']);
            
            // Se houver usuário, buscar progresso de cada aula
            if($userId) {
                foreach($module['lessons'] as &$lesson) {
                    $lesson['progress'] = $this->getLessonProgress($userId, $lesson['id']);
                }
            }
            
            $course['modules'][] = $module;
        }
        
        return $course;
    }
    
    /**
     * Obter progresso de uma aula específica
     */
    private function getLessonProgress($userId, $lessonId) {
        $sql = "SELECT * FROM user_lesson_progress 
                WHERE user_id = :user_id AND lesson_id = :lesson_id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':lesson_id' => $lessonId
        ]);
        
        $progress = $stmt->fetch();
        if(!$progress) {
            return [
                'status' => 'nao_iniciado',
                'porcentagem' => 0
            ];
        }
        
        return $progress;
    }
}
?>