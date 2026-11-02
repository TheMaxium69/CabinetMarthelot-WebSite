<?php
/* Cabinet Marthelot — gestion des événements (stockage JSON dans /data) */

date_default_timezone_set('Europe/Paris');

const DATA_DIR = __DIR__ . '/../data';
const EVENTS_FILE = DATA_DIR . '/events.json';
const INSCRIPTIONS_FILE = DATA_DIR . '/inscriptions.json';

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function read_json($file)
{
    if (!is_file($file)) return [];
    $data = json_decode((string) file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

function write_json($file, array $data)
{
    if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0755, true);
    file_put_contents($file, json_encode(array_values($data), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
}

/* Événements triés par date croissante */
function get_events()
{
    $events = read_json(EVENTS_FILE);
    usort($events, function ($a, $b) { return strcmp($a['date'], $b['date']); });
    return $events;
}

function find_event($id)
{
    foreach (get_events() as $event) {
        if ($event['id'] === $id) return $event;
    }
    return null;
}

function add_event($titre, $date, $lieu, $description)
{
    $events = read_json(EVENTS_FILE);
    $events[] = [
        'id' => bin2hex(random_bytes(6)),
        'titre' => $titre,
        'date' => $date,
        'lieu' => $lieu,
        'description' => $description,
    ];
    write_json(EVENTS_FILE, $events);
}

function delete_event($id)
{
    write_json(EVENTS_FILE, array_filter(read_json(EVENTS_FILE), function ($ev) use ($id) { return $ev['id'] !== $id; }));
    write_json(INSCRIPTIONS_FILE, array_filter(read_json(INSCRIPTIONS_FILE), function ($i) use ($id) { return $i['event'] !== $id; }));
}

function is_past($event)
{
    return strtotime($event['date']) < time();
}

/* Prochain événement dans les 7 jours (pour la bannière) */
function next_week_event()
{
    $now = time();
    foreach (get_events() as $event) {
        $ts = strtotime($event['date']);
        if ($ts >= $now && $ts <= $now + 7 * 86400) return $event;
    }
    return null;
}

function get_inscriptions($eventId)
{
    return array_values(array_filter(read_json(INSCRIPTIONS_FILE), function ($i) use ($eventId) { return $i['event'] === $eventId; }));
}

/* Retourne false si l'adresse est déjà inscrite à cet événement */
function add_inscription($eventId, $prenom, $nom, $societe, $email)
{
    $all = read_json(INSCRIPTIONS_FILE);
    foreach ($all as $i) {
        if ($i['event'] === $eventId && strcasecmp($i['email'], $email) === 0) return false;
    }
    $all[] = [
        'event' => $eventId,
        'prenom' => $prenom,
        'nom' => $nom,
        'societe' => $societe,
        'email' => $email,
        'inscrit_le' => date('Y-m-d H:i'),
    ];
    write_json(INSCRIPTIONS_FILE, $all);
    return true;
}

/* Date en français : « jeudi 12 novembre 2026 à 18h30 » */
function format_date($date)
{
    $jours = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
    $mois = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    $ts = strtotime($date);
    return $jours[(int) date('w', $ts)] . ' ' . (int) date('j', $ts) . ' ' . $mois[(int) date('n', $ts) - 1] . ' ' . date('Y', $ts) . ' à ' . date('G\hi', $ts);
}
