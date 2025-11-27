<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EGI - <?php echo $title ?? 'Dashboard'; ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script> <!-- Para calendário -->
</head>
<body>
    <header>
        <nav>
            <a href="/<?php echo $_SESSION['tipo']; ?>/dashboard">Dashboard</a>
            <?php if ($_SESSION['tipo'] === 'mentorado'): ?>
                <a href="/mentorado/trilha">Trilha</a>
                <a href="/mentorado/calendario">Calendário</a>
                <a href="/mentorado/ranking">Ranking</a>
            <?php elseif ($_SESSION['tipo'] === 'admin'): ?>
                <a href="/admin/aprovar">Aprovar Usuários</a>
                <a href="/admin/criar_trilha">Criar Trilha</a>
                <a href="/admin/ranking">Ranking</a>
            <?php endif; ?>
            <a href="/auth/logout">Logout</a>
        </nav>
    </header>
    <main>
        <?php echo $content; ?>
    </main>
    <footer>EGI © 2025</footer>
    <script src="/assets/js/main.js"></script>
</body>
</html>