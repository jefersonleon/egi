<?php
/**
 * Model Lesson
 * EGI - Escola de Gestão Imobiliária
 */

class Lesson {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Buscar aula por ID
     */
    public function getById($id) {
        $sql = "SELECT l.*, m.titulo as module_titulo, m.course_id
                FROM lessons l
                INNER JOIN modules m ON l.module_id = m.id
                WHERE l.id = :id AND l.ativo = 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }
    
    /**
     * Obter arquivos da aula
     */
    public function getFiles($lessonId) {
        $sql = "SELECT * FROM lesson_files WHERE lesson_id = :lesson_id ORDER BY created_at ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':lesson_id' => $lessonId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Criar nova aula
     */
    public function create($data) {
        $sql = "INSERT INTO lessons (module_id, titulo, descricao, tipo, conteudo, duracao_minutos, pontos, ordem, obrigatorio) 
                VALUES (:module_id, :titulo, :descricao, :tipo, :conteudo, :duracao, :pontos, :ordem, :obrigatorio)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':module_id' => $data['module_id'],
            ':titulo' => $data['titulo'],
            ':descricao' => $data['descricao'] ?? null,
            ':tipo' => $data['tipo'],
            ':conteudo' => $data['conteudo'] ?? null,
            ':duracao' => $data['duracao_minutos'] ?? 0,
            ':pontos' => $data['pontos'] ?? PONTOS_AULA_CONCLUIDA,
            ':ordem' => $data['ordem'] ?? 0,
            ':obrigatorio' => $data['obrigatorio'] ?? 1
        ]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * Adicionar arquivo à aula
     */
    public function addFile($lessonId, $fileData) {
        $sql = "INSERT INTO lesson_files (lesson_id, nome_original, nome_arquivo, tipo_arquivo, tamanho_bytes, caminho) 
                VALUES (:lesson_id, :nome_original, :nome_arquivo, :tipo_arquivo, :tamanho, :caminho)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':lesson_id' => $lessonId,
            ':nome_original' => $fileData['nome_original'],
            ':nome_arquivo' => $fileData['nome_arquivo'],
            ':tipo_arquivo' => $fileData['tipo_arquivo'],
            ':tamanho' => $fileData['tamanho_bytes'],
            ':caminho' => $fileData['caminho']
        ]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * Registrar início de visualização
     */
    public function startLesson($userId, $lessonId) {
        // Verificar se já existe progresso
        $sql = "SELECT id FROM user_lesson_progress WHERE user_id = :user_id AND lesson_id = :lesson_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId, ':lesson_id' => $lessonId]);
        
        if($stmt->fetch()) {
            // Atualizar última visualização
            $sql = "UPDATE user_lesson_progress 
                    SET ultima_visualizacao = NOW(), status = 'em_progresso'
                    WHERE user_id = :user_id AND lesson_id = :lesson_id";
        } else {
            // Criar novo registro
            $sql = "INSERT INTO user_lesson_progress (user_id, lesson_id, status, primeira_visualizacao, ultima_visualizacao) 
                    VALUES (:user_id, :lesson_id, 'em_progresso', NOW(), NOW())";
        }
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':user_id' => $userId, ':lesson_id' => $lessonId]);
    }
    
    /**
     * Marcar aula como concluída
     */
    public function completeLesson($userId, $lessonId) {
        // Buscar pontos da aula
        $lesson = $this->getById($lessonId);
        $pontos = $lesson['pontos'] ?? PONTOS_AULA_CONCLUIDA;
        
        // Verificar se já foi concluída
        $sql = "SELECT status FROM user_lesson_progress WHERE user_id = :user_id AND lesson_id = :lesson_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId, ':lesson_id' => $lessonId]);
        $progress = $stmt->fetch();
        
        if($progress && $progress['status'] === 'concluido') {
            return ['success' => false, 'message' => 'Aula já foi concluída'];
        }
        
        // Atualizar progresso
        $sql = "INSERT INTO user_lesson_progress (user_id, lesson_id, status, porcentagem, concluido_em, pontos_ganhos) 
                VALUES (:user_id, :lesson_id, 'concluido', 100, NOW(), :pontos)
                ON DUPLICATE KEY UPDATE 
                    status = 'concluido', 
                    porcentagem = 100, 
                    concluido_em = NOW(), 
                    pontos_ganhos = :pontos";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':lesson_id' => $lessonId,
            ':pontos' => $pontos
        ]);
        
        // Adicionar pontos ao usuário via procedure
        $sql = "CALL sp_adicionar_pontos(:user_id, :pontos, 'lesson_complete', :descricao, :lesson_id, NULL)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':pontos' => $pontos,
            ':descricao' => "Aula concluída: " . $lesson['titulo'],
            ':lesson_id' => $lessonId
        ]);
        
        // Registrar log
        $userModel = new User();
        $userModel->logAccess($userId, 'lesson_view', $lessonId);
        
        return ['success' => true, 'pontos' => $pontos];
    }
    
    /**
     * Atualizar porcentagem de progresso
     */
    public function updateProgress($userId, $lessonId, $porcentagem) {
        $sql = "INSERT INTO user_lesson_progress (user_id, lesson_id, porcentagem, status, ultima_visualizacao) 
                VALUES (:user_id, :lesson_id, :porcentagem, 'em_progresso', NOW())
                ON DUPLICATE KEY UPDATE 
                    porcentagem = :porcentagem, 
                    status = IF(:porcentagem >= 100, 'concluido', 'em_progresso'),
                    ultima_visualizacao = NOW()";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':user_id' => $userId,
            ':lesson_id' => $lessonId,
            ':porcentagem' => $porcentagem
        ]);
    }
    
    /**
     * Obter próxima aula
     */
    public function getNextLesson($currentLessonId) {
        // Buscar aula atual
        $current = $this->getById($currentLessonId);
        if(!$current) return null;
        
        // Buscar próxima aula no mesmo módulo
        $sql = "SELECT * FROM lessons 
                WHERE module_id = :module_id AND ordem > :ordem AND ativo = 1
                ORDER BY ordem ASC LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':module_id' => $current['module_id'],
            ':ordem' => $current['ordem']
        ]);
        
        $nextLesson = $stmt->fetch();
        if($nextLesson) return $nextLesson;
        
        // Se não houver próxima aula no módulo, buscar primeiro do próximo módulo
        $sql = "SELECT l.* FROM lessons l
                INNER JOIN modules m ON l.module_id = m.id
                WHERE m.course_id = :course_id AND m.ordem > (
                    SELECT ordem FROM modules WHERE id = :module_id
                )
                AND l.ativo = 1
                ORDER BY m.ordem ASC, l.ordem ASC
                LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':course_id' => $current['course_id'],
            ':module_id' => $current['module_id']
        ]);
        
        return $stmt->fetch();
    }
}
?>