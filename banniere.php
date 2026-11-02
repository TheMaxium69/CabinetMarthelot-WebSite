<?php
/* Cabinet Marthelot — événement de la semaine (JSON lu par js/main.js pour la bannière) */

require __DIR__ . '/inc/events.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');

$event = next_week_event();
echo json_encode($event ? [
    'id' => $event['id'],
    'titre' => $event['titre'],
    'date' => format_date($event['date']),
    'lieu' => $event['lieu'],
] : null, JSON_UNESCAPED_UNICODE);
