// Polling para alertas
setInterval(() => {
    fetch('/gamification/get_alerts')
        .then(response => response.json())
        .then(alerts => {
            const alertsDiv = document.getElementById('alerts');
            alertsDiv.innerHTML = '';
            alerts.forEach(alert => {
                const msg = alert.deadline < new Date() ? 'Atividade atrasada!' : 'Prazo chegando!';
                alertsDiv.innerHTML += `<div class="alert">${msg} - ${alert.titulo}</div>`;
            });
        });
}, 60000);  // Cada minuto

// Calendário (em calendario.php)
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('calendar')) {
        var calendarEl = document.getElementById('calendar');
        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            events: deadlines.map(d => ({ title: d.titulo, start: d.deadline }))  // deadlines de PHP
        });
        calendar.render();
    }
});