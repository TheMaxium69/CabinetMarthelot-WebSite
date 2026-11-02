<?php
/* Cabinet Marthelot — panel de gestion des événements */

require __DIR__ . '/../inc/events.php';

// Mot de passe du panel : défini dans inc/db.php (voir inc/db.template.php)
if (!is_file(__DIR__ . '/../inc/db.php')) {
    http_response_code(500);
    exit('Configuration manquante : copier inc/db.template.php en inc/db.php et y définir le mot de passe.');
}
require __DIR__ . '/../inc/db.php';

session_start();

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf'];
$logged = !empty($_SESSION['panel']);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'login') {
        if (hash_equals((string) $panelPassword, (string) ($_POST['password'] ?? ''))) {
            session_regenerate_id(true);
            $_SESSION['panel'] = true;
            header('Location: ./');
            exit;
        }
        sleep(1);
        $error = 'Mot de passe incorrect.';
    } elseif ($logged && hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        if ($action === 'logout') {
            session_destroy();
            header('Location: ./');
            exit;
        }
        if ($action === 'add') {
            $titre = trim($_POST['titre'] ?? '');
            $date = trim($_POST['date'] ?? '');
            if ($titre !== '' && strtotime($date)) {
                add_event($titre, $date, trim($_POST['lieu'] ?? ''), trim($_POST['description'] ?? ''));
                header('Location: ./?ok=ajout');
                exit;
            }
            $error = 'Le titre et la date sont obligatoires.';
        }
        if ($action === 'delete') {
            delete_event((string) ($_POST['id'] ?? ''));
            header('Location: ./?ok=suppression');
            exit;
        }
    }
}

// Export CSV des inscrits d'un événement
if ($logged && isset($_GET['csv']) && ($event = find_event($_GET['csv']))) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="inscrits-' . $event['id'] . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Prénom', 'Nom', 'Société', 'E-mail', 'Inscrit le'], ';', '"', '');
    foreach (get_inscriptions($event['id']) as $i) {
        fputcsv($out, [$i['prenom'], $i['nom'], $i['societe'], $i['email'], $i['inscrit_le']], ';', '"', '');
    }
    exit;
}

$current = $logged && isset($_GET['event']) ? find_event($_GET['event']) : null;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Panel - Cabinet Marthelot</title>
  <link rel="icon" type="image/png" href="../assets/img/favicon.png">
  <link rel="stylesheet" href="../css/style.css">
  <style>
    body { background: var(--soft); }
    .panel { max-width: 960px; margin: 0 auto; padding: 20px var(--gutter) 64px; }
    .panel h2 { font-size: 1.35rem; }
    .panel-top { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 20px; }
    .panel-top h1 { margin: 0; font-size: 1.6rem; }
    .panel .form-card { margin-bottom: 20px; padding: 20px 16px; }
    .panel .past { color: var(--muted); }
    .panel .row-actions { display: flex; gap: 8px; flex-wrap: wrap; }
    .panel .btn-sm { padding: 10px 14px; font-size: .88rem; min-height: 44px; }
    .panel button.btn-outline { background: #fff; }
    .panel .btn-danger { border-color: #b42318; color: #b42318; background: #fff; }
    .panel .btn-danger:hover { background: #b42318; color: #fff; }
    .panel .alert { padding: 12px 16px; border-radius: var(--radius); background: #fdecea; color: #b42318; margin-bottom: 16px; }
    .panel .alert-ok { background: var(--cyan-100); color: var(--navy); }
    .panel .login { max-width: 400px; margin: 10vh auto 0; }
    .panel input, .panel textarea { font-size: 16px; } /* évite le zoom automatique sur iPhone */

    /* Formulaire d'ajout repliable */
    .add-box summary { list-style: none; cursor: pointer; width: 100%; }
    .add-box summary::-webkit-details-marker { display: none; }
    .add-box[open] summary { margin-bottom: 4px; background: #fff; color: var(--navy); border-color: var(--navy); }
    .add-box[open] summary .plus { transform: rotate(45deg); }
    .add-box .plus { display: inline-block; font-size: 1.3rem; line-height: 1; transition: transform .2s; }

    /* Listes en cartes (lisibles sur téléphone) */
    .items { list-style: none; margin: 0; padding: 0; }
    .item { display: flex; flex-direction: column; gap: 12px; padding: 16px 0; border-top: 1px solid var(--line); }
    .item:first-child { border-top: 0; padding-top: 4px; }
    .item-date { font-size: .82rem; font-weight: 600; color: var(--cyan-600); }
    .item.past .item-date { color: var(--muted); }
    .item-title { display: block; font-weight: 600; color: var(--navy); margin: 2px 0; overflow-wrap: anywhere; }
    .item-sub { display: block; font-size: .88rem; color: var(--muted); overflow-wrap: anywhere; }
    .badge { display: inline-block; margin-top: 6px; padding: 2px 10px; border-radius: 99px; background: var(--cyan-100); color: var(--navy); font-size: .8rem; font-weight: 600; }
    .item .row-actions > * { flex: 1; }
    .item .row-actions form .btn { width: 100%; }
    .tag { display: inline-block; margin-left: 6px; padding: 1px 8px; border-radius: 99px; background: var(--soft); color: var(--muted); font-size: .72rem; font-weight: 600; vertical-align: middle; }

    @media (min-width: 768px) {
      .panel { padding-top: 32px; }
      .panel .form-card { padding: 26px 28px; }
      .panel .form-grid { grid-template-columns: 1fr 1fr; }
      .add-box summary { width: auto; }
      .item { flex-direction: row; align-items: center; justify-content: space-between; }
      .item .row-actions { flex: none; }
      .item .row-actions > * { flex: none; }
    }
  </style>
</head>
<body>
<main class="panel">
<?php if (!$logged): ?>
  <div class="form-card login">
    <h2>Panel événements</h2>
    <?php if ($error): ?><div class="alert"><?= e($error) ?></div><?php endif; ?>
    <form method="post" class="form-grid">
      <input type="hidden" name="action" value="login">
      <div class="field full">
        <label for="password">Mot de passe</label>
        <input type="password" id="password" name="password" required autofocus>
      </div>
      <div class="form-actions"><button class="btn btn-navy" type="submit">Se connecter</button></div>
    </form>
  </div>
<?php else: ?>
  <div class="panel-top">
    <h1>Événements</h1>
    <div class="row-actions">
      <a class="btn btn-outline btn-sm" href="../evenements.php" target="_blank">Voir sur le site</a>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="action" value="logout">
        <button class="btn btn-outline btn-sm" type="submit">Déconnexion</button>
      </form>
    </div>
  </div>

  <?php if ($error): ?><div class="alert"><?= e($error) ?></div><?php endif; ?>
  <?php if (($_GET['ok'] ?? '') === 'ajout'): ?><div class="alert alert-ok">Événement ajouté.</div><?php endif; ?>
  <?php if (($_GET['ok'] ?? '') === 'suppression'): ?><div class="alert alert-ok">Événement supprimé.</div><?php endif; ?>

  <?php if ($current): $inscrits = get_inscriptions($current['id']); ?>
    <div class="form-card">
      <p><a href="./">&larr; Retour aux événements</a></p>
      <h2><?= e($current['titre']) ?></h2>
      <p class="lead" style="font-size:1rem"><?= e(format_date($current['date'])) ?><?= $current['lieu'] ? ' · ' . e($current['lieu']) : '' ?></p>
      <div class="panel-top" style="margin:20px 0 12px">
        <strong><?= count($inscrits) ?> inscrit<?= count($inscrits) > 1 ? 's' : '' ?></strong>
        <?php if ($inscrits): ?><a class="btn btn-navy btn-sm" href="?csv=<?= e($current['id']) ?>">Exporter (CSV)</a><?php endif; ?>
      </div>
      <?php if ($inscrits): ?>
        <ul class="items">
          <?php foreach ($inscrits as $i): ?>
            <li class="item">
              <div>
                <span class="item-title"><?= e($i['prenom'] . ' ' . $i['nom']) ?></span>
                <?php if ($i['societe']): ?><span class="item-sub"><?= e($i['societe']) ?></span><?php endif; ?>
                <a class="item-sub" href="mailto:<?= e($i['email']) ?>"><?= e($i['email']) ?></a>
              </div>
              <span class="item-sub">Inscrit le <?= e(date('d/m/Y à H:i', strtotime($i['inscrit_le']))) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p class="past">Aucun inscrit pour le moment.</p>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <details class="form-card add-box"<?= $error ? ' open' : '' ?>>
      <summary class="btn btn-navy"><span class="plus">+</span> Ajouter un événement</summary>
      <form method="post" class="form-grid">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="action" value="add">
        <div class="field">
          <label for="titre">Titre <span class="req">*</span></label>
          <input type="text" id="titre" name="titre" required>
        </div>
        <div class="field">
          <label for="date">Date et heure <span class="req">*</span></label>
          <input type="datetime-local" id="date" name="date" required>
        </div>
        <div class="field full">
          <label for="lieu">Lieu</label>
          <input type="text" id="lieu" name="lieu" placeholder="Ex. : Nancy, 12 rue ... ou En ligne">
        </div>
        <div class="field full">
          <label for="description">Description</label>
          <textarea id="description" name="description"></textarea>
        </div>
        <div class="form-actions"><button class="btn btn-primary" type="submit">Enregistrer l'événement</button></div>
      </form>
    </details>

    <div class="form-card">
      <h2>Liste des événements</h2>
      <?php $events = array_reverse(get_events()); ?>
      <?php if ($events): ?>
        <ul class="items">
          <?php foreach ($events as $ev): $nb = count(get_inscriptions($ev['id'])); ?>
            <li class="item<?= is_past($ev) ? ' past' : '' ?>">
              <div>
                <span class="item-date"><?= e(date('d/m/Y à H:i', strtotime($ev['date']))) ?><?= is_past($ev) ? '<span class="tag">Passé</span>' : '' ?></span>
                <span class="item-title"><?= e($ev['titre']) ?></span>
                <?php if ($ev['lieu']): ?><span class="item-sub"><?= e($ev['lieu']) ?></span><?php endif; ?>
                <span class="badge"><?= $nb ?> inscrit<?= $nb > 1 ? 's' : '' ?></span>
              </div>
              <div class="row-actions">
                <a class="btn btn-navy btn-sm" href="?event=<?= e($ev['id']) ?>">Voir les inscrits</a>
                <form method="post" data-confirm="Supprimer « <?= e($ev['titre']) ?> » et ses inscriptions ?">
                  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= e($ev['id']) ?>">
                  <button class="btn btn-danger btn-sm" type="submit">Supprimer</button>
                </form>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p class="past">Aucun événement pour le moment.</p>
      <?php endif; ?>
    </div>
  <?php endif; ?>
<?php endif; ?>
</main>
<script src="panel.js"></script>
</body>
</html>
