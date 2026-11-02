<?php
/* Cabinet Marthelot — liste des événements et inscription */

require __DIR__ . '/inc/events.php';

$message = '';
$errorEvent = '';
$old = ['prenom' => '', 'nom' => '', 'societe' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $event = find_event((string) ($_POST['event'] ?? ''));
    foreach ($old as $k => $v) $old[$k] = trim((string) ($_POST[$k] ?? ''));

    // Champ piège anti-robots : doit rester vide
    if (!empty($_POST['site_web'])) {
        header('Location: evenements.php?inscrit=1');
        exit;
    }
    if (!$event || is_past($event)) {
        $message = 'Cet événement n\'est plus ouvert aux inscriptions.';
    } elseif ($old['prenom'] === '' || $old['nom'] === '' || !filter_var($old['email'], FILTER_VALIDATE_EMAIL) || empty($_POST['consentement'])) {
        $message = 'Merci de remplir les champs obligatoires avec une adresse e-mail valide.';
        $errorEvent = $event['id'];
    } elseif (!add_inscription($event['id'], $old['prenom'], $old['nom'], $old['societe'], $old['email'])) {
        $message = 'Cette adresse e-mail est déjà inscrite à cet événement.';
        $errorEvent = $event['id'];
    } else {
        header('Location: evenements.php?inscrit=1#ev-' . $event['id']);
        exit;
    }
}

$events = get_events();
$upcoming = array_values(array_filter($events, function ($ev) { return !is_past($ev); }));
$past = array_reverse(array_values(array_filter($events, 'is_past')));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Événements - Cabinet Marthelot</title>
  <meta name="description" content="Les événements du Cabinet Marthelot à Nancy : rencontres, ateliers et conférences pour les dirigeants de micro-entreprises, TPE et PME. Inscription gratuite en ligne.">
  <meta name="robots" content="index, follow, max-image-preview:large">
  <meta name="author" content="Cabinet Marthelot">
  <meta name="theme-color" content="#0d2041">
  <link rel="canonical" href="https://www.cabinet-marthelot.com/evenements.php">

  <!-- Icônes -->
  <link rel="icon" type="image/png" href="assets/img/favicon.png">
  <link rel="apple-touch-icon" href="assets/img/apple-touch-icon.png">
  <link rel="manifest" href="site.webmanifest">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Source+Serif+4:opsz,wght@8..60,500;8..60,600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
  <a class="skip-link" href="#contenu">Aller au contenu</a>

  <header class="site-header">
    <div class="container header-inner">
      <a class="brand" href="./" aria-label="Cabinet Marthelot - accueil">
        <img src="assets/img/marthelot-mark.png" alt="" width="42" height="42">
        <span class="brand-text"><small>CABINET</small><strong>MARTHELOT</strong></span>
      </a>
      <button class="nav-toggle" aria-label="Ouvrir le menu" aria-expanded="false" aria-controls="menu">
        <span></span><span></span><span></span>
      </button>
      <nav class="main-nav" id="menu" aria-label="Navigation principale">
        <ul>
          <li><a href="./">Accueil</a></li>
          <li><a href="formule.html">La formule</a></li>
          <li><a href="evenements.php" aria-current="page">Événements</a></li>
          <li><a href="contact.html">Contact</a></li>
          <li><a class="btn btn-navy" href="contact.html">Prendre rendez-vous</a></li>
        </ul>
      </nav>
    </div>
  </header>
  <main id="contenu">
    <section class="page-hero">
      <div class="container" style="position:relative">
        <nav class="breadcrumb" aria-label="Fil d'Ariane"><a href="./">Accueil</a> / Événements</nav>
        <p class="eyebrow">Agenda</p>
        <h1>Nos événements</h1>
        <p class="lead">Rencontres, ateliers et conférences pour les dirigeants de micro-entreprises, TPE et PME. L'inscription est gratuite.</p>
      </div>
    </section>

    <section class="section">
      <div class="container">
        <?php if (isset($_GET['inscrit'])): ?>
          <div class="form-status show event-alert">Votre inscription est bien enregistrée. À très bientôt !</div>
        <?php elseif ($message && !$errorEvent): ?>
          <div class="form-status show event-alert is-error"><?= e($message) ?></div>
        <?php endif; ?>

        <div class="section-head">
          <p class="eyebrow">À venir</p>
          <h2>Prochains événements</h2>
        </div>

        <?php if (!$upcoming): ?>
          <p class="lead">Aucun événement n'est programmé pour le moment. Revenez bientôt !</p>
        <?php endif; ?>

        <div class="event-list">
          <?php foreach ($upcoming as $ev): $open = $errorEvent === $ev['id']; ?>
            <article class="event-card" id="ev-<?= e($ev['id']) ?>">
              <p class="event-date"><?= e(format_date($ev['date'])) ?></p>
              <h3><?= e($ev['titre']) ?></h3>
              <?php if ($ev['lieu']): ?><p class="event-place"><?= e($ev['lieu']) ?></p><?php endif; ?>
              <?php if ($ev['description']): ?><p class="event-desc"><?= nl2br(e($ev['description'])) ?></p><?php endif; ?>

              <details class="event-signup"<?= $open ? ' open' : '' ?>>
                <summary class="btn btn-navy">S'inscrire</summary>
                <form method="post" action="evenements.php#ev-<?= e($ev['id']) ?>" class="form-grid">
                  <?php if ($open): ?><div class="form-status show is-error"><?= e($message) ?></div><?php endif; ?>
                  <input type="hidden" name="event" value="<?= e($ev['id']) ?>">
                  <div class="field">
                    <label for="prenom-<?= e($ev['id']) ?>">Prénom <span class="req">*</span></label>
                    <input type="text" id="prenom-<?= e($ev['id']) ?>" name="prenom" required autocomplete="given-name" value="<?= $open ? e($old['prenom']) : '' ?>">
                  </div>
                  <div class="field">
                    <label for="nom-<?= e($ev['id']) ?>">Nom <span class="req">*</span></label>
                    <input type="text" id="nom-<?= e($ev['id']) ?>" name="nom" required autocomplete="family-name" value="<?= $open ? e($old['nom']) : '' ?>">
                  </div>
                  <div class="field">
                    <label for="societe-<?= e($ev['id']) ?>">Société</label>
                    <input type="text" id="societe-<?= e($ev['id']) ?>" name="societe" autocomplete="organization" value="<?= $open ? e($old['societe']) : '' ?>">
                  </div>
                  <div class="field">
                    <label for="email-<?= e($ev['id']) ?>">E-mail <span class="req">*</span></label>
                    <input type="email" id="email-<?= e($ev['id']) ?>" name="email" required autocomplete="email" value="<?= $open ? e($old['email']) : '' ?>">
                  </div>
                  <div class="hp" aria-hidden="true">
                    <label>Site web <input type="text" name="site_web" tabindex="-1" autocomplete="off"></label>
                  </div>
                  <label class="consent">
                    <input type="checkbox" name="consentement" value="1" required>
                    <span>J'accepte que mes informations soient utilisées par le Cabinet Marthelot pour gérer mon inscription à cet événement. Voir les <a href="mentions-legales.html">mentions légales</a>.</span>
                  </label>
                  <div class="form-actions"><button class="btn btn-primary" type="submit">Valider mon inscription</button></div>
                </form>
              </details>
            </article>
          <?php endforeach; ?>
        </div>

        <?php if ($past): ?>
          <div class="section-head" style="margin-top:56px">
            <p class="eyebrow">Archives</p>
            <h2>Événements passés</h2>
          </div>
          <div class="event-list">
            <?php foreach ($past as $ev): ?>
              <article class="event-card is-past">
                <p class="event-date"><?= e(format_date($ev['date'])) ?></p>
                <h3><?= e($ev['titre']) ?></h3>
                <?php if ($ev['lieu']): ?><p class="event-place"><?= e($ev['lieu']) ?></p><?php endif; ?>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </section>
  </main>

  <footer class="site-footer">
    <div class="container">
      <div class="footer-main">
        <div class="footer-brand">
          <a class="brand" href="./">
            <img src="assets/img/marthelot-mark-white.png" alt="" width="42" height="42">
            <span class="brand-text"><small>CABINET</small><strong>MARTHELOT</strong></span>
          </a>
          <p>Cabinet de conseil et d'accompagnement des micro-entreprises, TPE et PME. Une formule unique pour structurer, piloter et développer votre entreprise.</p>
        </div>
        <div>
          <h4>Navigation</h4>
          <ul>
            <li><a href="./">Accueil</a></li>
            <li><a href="formule.html">La formule</a></li>
            <li><a href="evenements.php">Événements</a></li>
            <li><a href="contact.html">Contact</a></li>
            <li><a href="mentions-legales.html">Mentions légales</a></li>
          </ul>
        </div>
        <div>
          <h4>Expertises</h4>
          <ul>
            <li><a href="formule.html#communication">Communication</a></li>
            <li><a href="formule.html#gestion">Gestion administrative &amp; financière</a></li>
            <li><a href="formule.html#strategie">Entrepreneuriat &amp; stratégie</a></li>
          </ul>
        </div>
        <div>
          <h4>Contact</h4>
          <ul>
            <li><a href="mailto:contact@cabinet-marthelot.com">contact@cabinet-marthelot.com</a></li>
            <li><a href="tel:+33767008972">07 67 00 89 72</a></li>
            <li>Nancy et ses alentours</li>
          </ul>
        </div>
      </div>
      <div class="footer-legal">
        <span>© <span data-year>2026</span> Cabinet Marthelot SAS · <span class="ids">SIRET 107 155 475 00018</span></span>
        <div class="credits">
          <a class="credit" href="https://tyrolium.fr" target="_blank" rel="noopener"><img src="assets/img/tyrolium.png" alt="">Conçu par <strong>Tyrolium</strong></a>
          <a class="credit" href="https://solidserv.fr" target="_blank" rel="noopener"><img src="assets/img/solidserv.png" alt="">Hébergé par <strong>SolidServ</strong></a>
        </div>
      </div>
    </div>
  </footer>

  <script src="js/main.js"></script>
</body>
</html>
