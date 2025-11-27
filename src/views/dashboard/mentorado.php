<?php
function url($page) {
    return "index.php?page=$page";
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - EGI</title>
    <link rel="stylesheet" href="<?php echo CSS_URL; ?>/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="main-wrapper">
        <!-- SIDEBAR -->
        <aside class="sidebar">
            <div class="sidebar-logo">
                <h1>EGI</h1>
                <p>Gestão Imobiliária</p>
            </div>
            
            <ul class="sidebar-menu">
                <li><a href="<?php echo BASE_URL; ?>/index.php?action=dashboard" class="active">
                    <i class="fas fa-home"></i> Dashboard
                </a></li>
                <li><a href="<?php echo BASE_URL; ?>/index.php?action=courses">
                    <i class="fas fa-graduation-cap"></i> Meus Cursos
                </a></li>
                <li><a href="<?php echo BASE_URL; ?>/index.php?action=activities">
                    <i class="fas fa-tasks"></i> Atividades
                </a></li>
                <li><a href="<?php echo BASE_URL; ?>/index.php?action=calendar">
                    <i class="fas fa-calendar-alt"></i> Calendário
                </a></li>
                <li><a href="<?php echo BASE_URL; ?>/index.php?action=ranking">
                    <i class="fas fa-trophy"></i> Ranking
                </a></li>
                <li><a href="<?php echo BASE_URL; ?>/index.php?action=badges">
                    <i class="fas fa-medal"></i> Minhas Badges
                </a></li>
            </ul>
            
            <div class="sidebar-user">
                <div class="sidebar-user-info">
                    <img src="<?php echo UPLOAD_URL; ?>/avatars/<?php echo $data['user']['avatar']; ?>" 
                         class="sidebar-user-avatar" alt="Avatar">
                    <div>
                        <div class="sidebar-user-name"><?php echo $data['user']['nome']; ?></div>
                        <div class="sidebar-user-role">Mentorado</div>
                    </div>
                </div>
                <a href="<?php echo BASE_URL; ?>/index.php?action=logout">
                    <button class="btn-logout"><i class="fas fa-sign-out-alt"></i> Sair</button>
                </a>
            </div>
        </aside>
        
        <!-- CONTEÚDO PRINCIPAL -->
        <main class="main-content">
            <!-- HEADER -->
            <div class="page-header">
                <div>
                    <h1 class="page-title">Olá, <?php echo explode(' ', $data['user']['nome'])[0]; ?>! 👋</h1>
                    <p class="page-subtitle">Seja bem-vindo ao seu painel de aprendizado</p>
                </div>
            </div>
            
            <!-- ALERTAS -->
            <?php if(isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>
            
            <!-- CARDS DE ESTATÍSTICAS -->
            <div class="cards-grid">
                <!-- NÍVEL -->
                <div class="card">
                    <div class="card-header">
                        <div>
                            <div class="card-label">Nível Atual</div>
                            <div class="card-value"><?php echo $data['gamification']['nivel']; ?></div>
                        </div>
                        <div class="card-icon primary">
                            <i class="fas fa-star"></i>
                        </div>
                    </div>
                    <div class="progress-bar-container">
                        <div class="progress-bar" style="width: <?php echo $data['gamification']['porcentagem_nivel']; ?>%"></div>
                    </div>
                    <div class="progress-text">
                        <?php echo number_format($data['gamification']['pontos_faltantes']); ?> pontos para o próximo nível
                    </div>
                </div>
                
                <!-- PONTOS TOTAIS -->
                <div class="card">
                    <div class="card-header">
                        <div>
                            <div class="card-label">Pontos Totais</div>
                            <div class="card-value"><?php echo number_format($data['gamification']['pontos_totais']); ?></div>
                        </div>
                        <div class="card-icon success">
                            <i class="fas fa-coins"></i>
                        </div>
                    </div>
                    <div class="progress-text">
                        Posição no ranking: #<?php echo $data['gamification']['ranking_posicao']; ?>
                    </div>
                </div>
                
                <!-- AULAS CONCLUÍDAS -->
                <div class="card">
                    <div class="card-header">
                        <div>
                            <div class="card-label">Aulas Concluídas</div>
                            <div class="card-value"><?php echo $data['stats']['aulas_concluidas']; ?></div>
                        </div>
                        <div class="card-icon info">
                            <i class="fas fa-book-open"></i>
                        </div>
                    </div>
                    <div class="progress-text">
                        de <?php echo $data['stats']['total_aulas']; ?> aulas disponíveis
                    </div>
                </div>
                
                <!-- BADGES -->
                <div class="card">
                    <div class="card-header">
                        <div>
                            <div class="card-label">Badges Conquistadas</div>
                            <div class="card-value"><?php echo $data['gamification']['badges_total']; ?></div>
                        </div>
                        <div class="card-icon warning">
                            <i class="fas fa-medal"></i>
                        </div>
                    </div>
                    <div class="progress-text">
                        <a href="<?php echo BASE_URL; ?>/index.php?action=badges" style="color: #FFC107;">
                            Ver todas as badges →
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- SEÇÃO: PRÓXIMOS DEADLINES -->
            <?php if(!empty($data['deadlines'])): ?>
            <div class="card" style="margin-bottom: 30px;">
                <div class="card-header">
                    <h2 class="card-title"><i class="fas fa-clock"></i> Próximos Prazos</h2>
                    <a href="<?php echo BASE_URL; ?>/index.php?action=calendar" class="btn btn-outline">
                        Ver Calendário
                    </a>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Atividade</th>
                                <th>Prazo</th>
                                <th>Status</th>
                                <th>Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($data['deadlines'] as $deadline): 
                                $dias_restantes = ceil((strtotime($deadline['data_evento']) - time()) / 86400);
                                $urgente = $dias_restantes <= 3;
                            ?>
                            <tr style="<?php echo $urgente ? 'background: #FFF3CD;' : ''; ?>">
                                <td>
                                    <strong><?php echo $deadline['titulo']; ?></strong><br>
                                    <small><?php echo $deadline['activity_titulo'] ?? ''; ?></small>
                                </td>
                                <td>
                                    <?php echo date('d/m/Y H:i', strtotime($deadline['data_evento'])); ?><br>
                                    <small style="color: <?php echo $urgente ? '#E74C3C' : '#666'; ?>">
                                        <?php 
                                            if($dias_restantes == 0) echo 'HOJE!';
                                            elseif($dias_restantes == 1) echo 'Amanhã';
                                            else echo "Em {$dias_restantes} dias";
                                        ?>
                                    </small>
                                </td>
                                <td>
                                    <?php if($deadline['submission_status'] == 'pendente'): ?>
                                        <span class="badge warning">Entregue - Aguardando avaliação</span>
                                    <?php else: ?>
                                        <span class="badge <?php echo $urgente ? 'danger' : 'info'; ?>">
                                            Pendente
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if(!$deadline['submission_status']): ?>
                                        <a href="<?php echo BASE_URL; ?>/index.php?action=activity_view&id=<?php echo $deadline['activity_id']; ?>" 
                                           class="btn btn-primary">Entregar</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- SEÇÃO: MEUS CURSOS -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title"><i class="fas fa-graduation-cap"></i> Meus Cursos</h2>
                    <a href="<?php echo BASE_URL; ?>/index.php?action=courses" class="btn btn-outline">
                        Ver Todos
                    </a>
                </div>
                <div class="cards-grid" style="margin-top: 20px;">
                    <?php foreach(array_slice($data['courses'], 0, 3) as $course): ?>
                    <div class="card">
                        <h3 style="font-size: 18px; margin-bottom: 15px; color: #1E2A47;">
                            <?php echo $course['titulo']; ?>
                        </h3>
                        <div class="progress-bar-container">
                            <div class="progress-bar" style="width: <?php echo $course['porcentagem_progresso']; ?>%"></div>
                        </div>
                        <div class="progress-text" style="margin-bottom: 15px;">
                            <?php echo round($course['porcentagem_progresso'], 1); ?>% concluído 
                            (<?php echo $course['aulas_concluidas']; ?>/<?php echo $course['total_aulas']; ?> aulas)
                        </div>
                        <a href="<?php echo BASE_URL; ?>/index.php?action=course_view&id=<?php echo $course['id']; ?>" 
                           class="btn btn-primary" style="width: 100%;">
                            Continuar
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- SEÇÃO: TOP 5 RANKING -->
            <div class="card" style="margin-top: 30px;">
                <div class="card-header">
                    <h2 class="card-title"><i class="fas fa-trophy"></i> Top 5 Ranking</h2>
                    <a href="<?php echo BASE_URL; ?>/index.php?action=ranking" class="btn btn-outline">
                        Ver Ranking Completo
                    </a>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Mentorado</th>
                                <th>Nível</th>
                                <th>Pontos</th>
                                <th>Badges</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($data['ranking'] as $index => $mentorado): 
                                $is_me = ($mentorado['id'] == $data['user']['id']);
                            ?>
                            <tr style="<?php echo $is_me ? 'background: #E3F2FD; font-weight: 600;' : ''; ?>">
                                <td>
                                    <?php 
                                        if($index == 0) echo '🥇';
                                        elseif($index == 1) echo '🥈';
                                        elseif($index == 2) echo '🥉';
                                        else echo $index + 1;
                                    ?>
                                </td>
                                <td>
                                    <?php echo $mentorado['nome']; ?>
                                    <?php if($is_me) echo ' <span class="badge primary">Você</span>'; ?>
                                </td>
                                <td><span class="badge info">Nível <?php echo $mentorado['nivel']; ?></span></td>
                                <td><strong><?php echo number_format($mentorado['pontos_totais']); ?></strong></td>
                                <td>
                                    <i class="fas fa-medal" style="color: #FFC107;"></i> 
                                    <?php echo $mentorado['badges_conquistadas']; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
        </main>
    </div>
</body>
</html>