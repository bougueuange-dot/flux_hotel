<?php /* |-------------------------------------------------------------------------- | Hotel Flow - Vérification des retards |-------------------------------------------------------------------------- */ error_reporting(E_ALL); ini_set('display_errors', '1'); require_once __DIR__ . '/../config/database.php'; require_once __DIR__ . '/../includes/auth.php'; /* |-------------------------------------------------------------------------- | Vérifier la connexion |-------------------------------------------------------------------------- */ if (!isLoggedIn()) { header('Location: /flux_hotel/auth/login.php'); exit; } /* |-------------------------------------------------------------------------- | Récupérer l'hôtel de l'utilisateur connecté |-------------------------------------------------------------------------- */ $hotelId = (int) ($_SESSION['hotel_id'] ?? 0); if ($hotelId <= 0) { die("Hôtel invalide."); } /* |-------------------------------------------------------------------------- | Rechercher les séjours en retard |-------------------------------------------------------------------------- | | Un retard est créé seulement si : | | heure actuelle > heure prévue + 30 minutes | */ $sql = " SELECT s.id AS stay_id, s.expected_departure_at, c.first_name, c.last_name, r.room_number, TIMESTAMPDIFF( MINUTE, s.expected_departure_at, NOW() ) AS delay_minutes FROM stays s INNER JOIN clients c ON c.id = s.client_id INNER JOIN rooms r ON r.id = s.room_id WHERE s.hotel_id = :hotel_id AND s.status = 'active' AND NOW() > DATE_ADD( s.expected_departure_at, INTERVAL 30 MINUTE ) "; $stmt = $pdo->prepare($sql); $stmt->execute([ ':hotel_id' => $hotelId ]); $lateStays = $stmt->fetchAll(); /* |-------------------------------------------------------------------------- | Créer les alertes |-------------------------------------------------------------------------- */ $created = 0; foreach ($lateStays as $stay) { /* |-------------------------------------------------------------------------- | Vérifier si une alerte existe déjà |-------------------------------------------------------------------------- */ $checkSql = " SELECT id FROM alerts WHERE hotel_id = :hotel_id AND stay_id = :stay_id AND type = 'late_departure' LIMIT 1 "; $checkStmt = $pdo->prepare($checkSql); $checkStmt->execute([ ':hotel_id' => $hotelId, ':stay_id' => $stay['stay_id'] ]); $existingAlert = $checkStmt->fetch(); /* |-------------------------------------------------------------------------- | Si aucune alerte n'existe, on la crée |-------------------------------------------------------------------------- */ if (!$existingAlert) { $clientName = $stay['first_name'] . ' ' . $stay['last_name']; $message = "Retard de départ : " . $clientName . " - Chambre " . $stay['room_number'] . " - Départ prévu à " . date( 'H:i', strtotime( $stay['expected_departure_at'] ) ) . " - Retard de " . $stay['delay_minutes'] . " minute(s)."; $insertSql = " INSERT INTO alerts ( hotel_id, stay_id, type, message, alert_at, is_read, created_at ) VALUES ( :hotel_id, :stay_id, 'late_departure', :message, NOW(), 0, NOW() ) "; $insertStmt = $pdo->prepare($insertSql); $insertStmt->execute([ ':hotel_id' => $hotelId, ':stay_id' => $stay['stay_id'], ':message' => $message ]); $created++; } } /* |-------------------------------------------------------------------------- | Affichage du résultat |-------------------------------------------------------------------------- */ ?> <!DOCTYPE html> <html lang="fr"> <head>
<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Vérification des alertes - Hotel Flow</title>
<link
    rel="manifest"
    href="/flux_hotel/manifest.json"
>

<meta
    name="theme-color"
    content="#1e3a5f"
>

<meta
    name="mobile-web-app-capable"
    content="yes"
>

<meta
    name="apple-mobile-web-app-capable"
    content="yes"
>

<meta
    name="apple-mobile-web-app-status-bar-style"
    content="default"
>

<meta
    name="apple-mobile-web-app-title"
    content="Hotel Flow"
>

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>


<style>

    body {

        font-family: Arial, sans-serif;

        background: #f4f6f9;

        margin: 0;

        padding: 30px;

    }


    .box {

        max-width: 600px;

        margin: 50px auto;

        background: white;

        padding: 30px;

        border-radius: 12px;

        box-shadow:
            0 3px 15px
            rgba(0,0,0,0.08);

    }


    h1 {

        color: #1e3a5f;

    }


    .success {

        background: #d1e7dd;

        color: #0f5132;

        padding: 15px;

        border-radius: 8px;

        margin: 20px 0;

    }


    .info {

        background: #cff4fc;

        color: #055160;

        padding: 15px;

        border-radius: 8px;

        margin: 20px 0;

    }


    a {

        display: inline-block;

        margin-top: 15px;

        padding: 10px 15px;

        background: #1e3a5f;

        color: white;

        text-decoration: none;

        border-radius: 6px;

    }

</style>

</head> <body> <div class="box">
<h1>
    ⚠️ Hotel Flow
</h1>


<?php if ($created > 0): ?>

    <div class="success">

        <?= $created ?>

        nouvelle(s) alerte(s) de retard
        ont été créée(s).

    </div>

<?php else: ?>

    <div class="info">

        Aucune nouvelle alerte de retard.

    </div>

<?php endif; ?>


<p>

    Nombre de séjours actuellement en retard :

    <strong>
        <?= count($lateStays) ?>
    </strong>

</p>


<a href="/flux_hotel/reception/dashboard.php">

    ← Retour au tableau de bord

</a>

</div> </body> </html>