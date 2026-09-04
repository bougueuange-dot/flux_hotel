<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireRole(2);

$hotelId = getHotelId();

$firstName = $_SESSION['first_name'] ?? '';
$lastName = $_SESSION['last_name'] ?? '';


/*
|--------------------------------------------------------------------------
| VÉRIFICATION CONNEXION
|--------------------------------------------------------------------------

|--------------------------------------------------------------------------
| VÉRIFICATION RÔLE RÉCEPTION
|--------------------------------------------------------------------------
*/

if ((int) ($_SESSION['role_id'] ?? 0) !== 2) {

    header('Location: /flux_hotel/admin/dashboard.php');
    exit;

}


/*
|--------------------------------------------------------------------------
| INFORMATIONS UTILISATEUR
|--------------------------------------------------------------------------
*/

$hotelId = (int) ($_SESSION['hotel_id'] ?? 0);

$firstName = $_SESSION['first_name'] ?? '';

$lastName = $_SESSION['last_name'] ?? '';


/*
|--------------------------------------------------------------------------
| VARIABLES
|--------------------------------------------------------------------------
*/

$availableRooms = 0;

$occupiedRooms = 0;

$presentClients = 0;

$arrivalsToday = 0;

$departuresToday = 0;

$error = '';


/*
|--------------------------------------------------------------------------
| RÉCUPÉRATION DES DONNÉES
|--------------------------------------------------------------------------
*/

try {


    /*
    |--------------------------------------------------------------------------
    | CHAMBRES
    |--------------------------------------------------------------------------
    */

    $sql = "

        SELECT

            SUM(status = 'available') AS available_rooms,

            SUM(status = 'occupied') AS occupied_rooms

        FROM rooms

        WHERE hotel_id = :hotel_id

    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([

        ':hotel_id' => $hotelId

    ]);

    $roomStats = $stmt->fetch(PDO::FETCH_ASSOC);


    $availableRooms =
        (int) ($roomStats['available_rooms'] ?? 0);

    $occupiedRooms =
        (int) ($roomStats['occupied_rooms'] ?? 0);


    /*
    |--------------------------------------------------------------------------
    | CLIENTS PRÉSENTS
    |--------------------------------------------------------------------------
    */

    $sql = "

        SELECT COUNT(DISTINCT client_id)

        FROM stays

        WHERE hotel_id = :hotel_id

        AND status = 'active'

    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([

        ':hotel_id' => $hotelId

    ]);

    $presentClients =
        (int) $stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | ARRIVÉES DU JOUR
    |--------------------------------------------------------------------------
    */

    $sql = "

        SELECT COUNT(*)

        FROM stays

        WHERE hotel_id = :hotel_id

        AND DATE(arrival_at) = CURDATE()

    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([

        ':hotel_id' => $hotelId

    ]);

    $arrivalsToday =
        (int) $stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | DÉPARTS DU JOUR
    |--------------------------------------------------------------------------
    */

    $sql = "

        SELECT COUNT(*)

        FROM stays

        WHERE hotel_id = :hotel_id

        AND DATE(expected_departure_at) = CURDATE()

        AND status = 'active'

    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([

        ':hotel_id' => $hotelId

    ]);

    $departuresToday =
        (int) $stmt->fetchColumn();


} catch (PDOException $e) {

    $error =
        "Impossible de charger les données du tableau de bord.";

}

?>

<!DOCTYPE html>

<html lang="fr">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0, maximum-scale=1.0"
>

<title>Hotel Flow - Réception</title>


<!-- =====================================================
     PWA
     ===================================================== -->

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


<style>

/* =====================================================
   RESET
   ===================================================== */

* {

    box-sizing: border-box;

}

html {

    width: 100%;

    min-height: 100%;

}

body {

    margin: 0;

    padding: 0;

    width: 100%;

    min-height: 100vh;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background: #f4f6f9;

    color: #222;

    overflow-x: hidden;

}


/* =====================================================
   HEADER
   ===================================================== */

.header {

    width: 100%;

    background: #1e3a5f;

    color: white;

    padding: 20px;

}

.header-content {

    width: 100%;

    max-width: 1200px;

    margin: auto;

}

.header h1 {

    margin: 0;

    font-size: 27px;

}

.header p {

    margin: 6px 0 0;

    color: #dbe7f5;

    font-size: 14px;

}


/* =====================================================
   NAVIGATION
   ===================================================== */

.nav {

    width: 100%;

    background: white;

    border-bottom: 1px solid #ddd;

}

.nav-container {

    width: 100%;

    max-width: 1200px;

    margin: auto;

    padding: 10px 20px;

    display: flex;

    flex-wrap: wrap;

    gap: 6px;

}

.nav a {

    display: flex;

    align-items: center;

    justify-content: center;

    min-height: 42px;

    padding: 10px 13px;

    border-radius: 7px;

    text-decoration: none;

    color: #1e3a5f;

    font-size: 14px;

    transition: background .2s;

}

.nav a:hover {

    background: #eaf0f7;

}

.nav a.active {

    background: #1e3a5f;

    color: white;

}

.nav a.logout {

    margin-left: auto;

    background: #c62828;

    color: white;

}

.nav a.logout:hover {

    background: #a91f1f;

}


/* =====================================================
   CONTENU
   ===================================================== */

.container {

    width: 100%;

    max-width: 1200px;

    margin: 30px auto;

    padding: 0 20px;

}


/* =====================================================
   BIENVENUE
   ===================================================== */

.welcome {

    margin-bottom: 25px;

}

.welcome h2 {

    margin: 0 0 7px;

    color: #1e3a5f;

    font-size: 25px;

    line-height: 1.3;

}

.welcome p {

    margin: 0;

    color: #666;

    line-height: 1.5;

}


/* =====================================================
   ERREUR
   ===================================================== */

.error {

    background: #f8d7da;

    color: #842029;

    padding: 15px;

    border-radius: 8px;

    margin-bottom: 20px;

}


/* =====================================================
   CARTES
   ===================================================== */

.cards {

    width: 100%;

    display: grid;

    grid-template-columns:
        repeat(3, minmax(0, 1fr));

    gap: 20px;

}

.card {

    width: 100%;

    min-width: 0;

    background: white;

    padding: 25px;

    border-radius: 12px;

    box-shadow:
        0 3px 12px
        rgba(0, 0, 0, .08);

}

.card-icon {

    font-size: 32px;

    margin-bottom: 12px;

}

.card-title {

    color: #666;

    font-size: 14px;

    line-height: 1.4;

}

.card-number {

    font-size: 34px;

    font-weight: bold;

    color: #1e3a5f;

    margin-top: 10px;

}


/* =====================================================
   COULEURS CARTES
   ===================================================== */

.card.available {

    border-left:
        5px solid #198754;

}

.card.available .card-number {

    color: #198754;

}


.card.occupied {

    border-left:
        5px solid #dc3545;

}

.card.occupied .card-number {

    color: #dc3545;

}


.card.clients {

    border-left:
        5px solid #0d6efd;

}

.card.clients .card-number {

    color: #0d6efd;

}


.card.arrivals {

    border-left:
        5px solid #fd7e14;

}

.card.arrivals .card-number {

    color: #fd7e14;

}


.card.departures {

    border-left:
        5px solid #6f42c1;

}

.card.departures .card-number {

    color: #6f42c1;

}


/* =====================================================
   FOOTER
   ===================================================== */

.footer {

    width: 100%;

    text-align: center;

    color: #777;

    padding: 40px 20px;

    font-size: 13px;

}


/* =====================================================
   TABLETTE
   ===================================================== */

@media (max-width: 900px) {

    .container {

        margin-top: 25px;

    }

    .cards {

        grid-template-columns:
            repeat(2, minmax(0, 1fr));

    }

}


/* =====================================================
   MOBILE
   ===================================================== */

@media (max-width: 600px) {


    body {

        width: 100%;

        font-size: 14px;

    }


    /* HEADER */

    .header {

        padding: 16px 14px;

    }

    .header h1 {

        font-size: 21px;

    }

    .header p {

        font-size: 12px;

    }


    /* NAVIGATION */

    .nav {

        width: 100%;

    }

    .nav-container {

        width: 100%;

        padding: 8px;

        display: grid;

        grid-template-columns:
            repeat(2, minmax(0, 1fr));

        gap: 6px;

    }

    .nav a {

        width: 100%;

        min-width: 0;

        min-height: 44px;

        padding: 9px 4px;

        text-align: center;

        font-size: 12px;

        line-height: 1.2;

    }

    .nav a.logout {

        margin-left: 0;

        grid-column:
            1 / -1;

    }


    /* CONTENU */

    .container {

        width: 100%;

        max-width: none;

        margin: 20px auto;

        padding: 0 12px;

    }


    /* BIENVENUE */

    .welcome {

        margin-bottom: 20px;

    }

    .welcome h2 {

        font-size: 20px;

        line-height: 1.4;

    }

    .welcome p {

        font-size: 13px;

        line-height: 1.5;

    }


    /* CARTES */

    .cards {

        width: 100%;

        display: grid;

        grid-template-columns: 1fr;

        gap: 12px;

    }

    .card {

        width: 100%;

        padding: 20px;

        border-radius: 10px;

    }

    .card-icon {

        font-size: 28px;

        margin-bottom: 8px;

    }

    .card-title {

        font-size: 13px;

    }

    .card-number {

        font-size: 30px;

        margin-top: 8px;

    }


    /* FOOTER */

    .footer {

        padding: 30px 15px;

        font-size: 12px;

    }

}


/* =====================================================
   TRÈS PETITS TÉLÉPHONES
   ===================================================== */

@media (max-width: 360px) {


    .nav-container {

        grid-template-columns: 1fr;

    }

    .nav a.logout {

        grid-column: auto;

    }

    .welcome h2 {

        font-size: 18px;

    }

    .card {

        padding: 18px;

    }

    .card-number {

        font-size: 27px;

    }

}

</style>

</head>


<body>


<!-- =====================================================
     HEADER
     ===================================================== -->

<header class="header">

    <div class="header-content">

        <h1>
            🏨 Hotel Flow
        </h1>

        <p>
            Espace Réception
        </p>

    </div>

</header>

<button id="installAppBtn" type="button" style="
    display:none;
    position:fixed;
    right:15px;
    bottom:15px;
    z-index:9999;
    border:none;
    background:#1e3a5f;
    color:white;
    padding:13px 18px;
    border-radius:10px;
    font-size:14px;
    font-weight:bold;
    box-shadow:0 4px 15px rgba(0,0,0,.2);
">
    📱 Installer Hotel Flow
</button>

<!-- =====================================================
     NAVIGATION
     ===================================================== -->

<nav class="nav">

    <div class="nav-container">


        <a
            class="active"
            href="/flux_hotel/reception/dashboard.php"
        >
            🏠 Tableau de bord
        </a>


        <a
            href="/flux_hotel/reception/clients.php"
        >
            👥 Clients
        </a>


        <a
            href="/flux_hotel/reception/stays.php"
        >
            🛏️ Séjours
        </a>
        
        <a
        
            href="/flux_hotel/reception/reservations.php"
        >
            📅 Réservations
        </a>


        <a
            href="/flux_hotel/reception/rooms.php"
        >
            🚪 Chambres
        </a>


        <a
            href="/flux_hotel/reception/history.php"
        >
            📋 Historique
        </a>


        <a
            href="/flux_hotel/reception/expenses.php"
        >
            💸 Décaissement
        </a>


        <a
            class="logout"
            href="/flux_hotel/auth/logout.php"
        >
            🚪 Déconnexion
        </a>


    </div>

</nav>


<!-- =====================================================
     CONTENU
     ===================================================== -->

<main class="container">


    <div class="welcome">

        <h2>

            Bonjour
            <?= htmlspecialchars($firstName) ?>
            <?= htmlspecialchars($lastName) ?>
            👋

        </h2>

        <p>

            Voici la situation actuelle de votre hôtel.

        </p>

    </div>


    <?php if ($error !== ''): ?>

        <div class="error">

            ⚠️

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>


    <!-- =================================================
         CARTES
         ================================================= -->

    <div class="cards">


        <!-- CHAMBRES DISPONIBLES -->

        <div class="card available">

            <div class="card-icon">

                🛏️

            </div>

            <div class="card-title">

                Chambres disponibles

            </div>

            <div class="card-number">

                <?= $availableRooms ?>

            </div>

        </div>


        <!-- CHAMBRES OCCUPÉES -->

        <div class="card occupied">

            <div class="card-icon">

                🔴

            </div>

            <div class="card-title">

                Chambres occupées

            </div>

            <div class="card-number">

                <?= $occupiedRooms ?>

            </div>

        </div>


        <!-- CLIENTS PRÉSENTS -->

        <div class="card clients">

            <div class="card-icon">

                👥

            </div>

            <div class="card-title">

                Clients présents

            </div>

            <div class="card-number">

                <?= $presentClients ?>

            </div>

        </div>


        <!-- ARRIVÉES -->

        <div class="card arrivals">

            <div class="card-icon">

                🛎️

            </div>

            <div class="card-title">

                Arrivées aujourd'hui

            </div>

            <div class="card-number">

                <?= $arrivalsToday ?>

            </div>

        </div>


        <!-- DÉPARTS -->

        <div class="card departures">

            <div class="card-icon">

                🚪

            </div>

            <div class="card-title">

                Départs aujourd'hui

            </div>

            <div class="card-number">

                <?= $departuresToday ?>

            </div>

        </div>


    </div>


</main>


<!-- =====================================================
     FOOTER
     ===================================================== -->

<footer class="footer">

    Hotel Flow © <?= date('Y') ?>

</footer>


<!-- =====================================================
     SERVICE WORKER PWA
     ===================================================== -->
<script>

let deferredPrompt = null;

const installAppBtn = document.getElementById('installAppBtn');


/*
|--------------------------------------------------------------------------
| Détection de l'installation
|--------------------------------------------------------------------------
*/

window.addEventListener('beforeinstallprompt', function(event) {

    event.preventDefault();

    deferredPrompt = event;

    if (installAppBtn) {
        installAppBtn.style.display = 'block';
    }

});


/*
|--------------------------------------------------------------------------
| Installation
|--------------------------------------------------------------------------
*/

if (installAppBtn) {

    installAppBtn.addEventListener('click', async function() {

        if (!deferredPrompt) {
            return;
        }

        deferredPrompt.prompt();

        const result = await deferredPrompt.userChoice;

        console.log(
            'Installation Hotel Flow :',
            result.outcome
        );

        deferredPrompt = null;

        installAppBtn.style.display = 'none';

    });

}


/*
|--------------------------------------------------------------------------
| Application déjà installée
|--------------------------------------------------------------------------
*/

window.addEventListener('appinstalled', function() {

    console.log('Hotel Flow installé avec succès.');

    if (installAppBtn) {
        installAppBtn.style.display = 'none';
    }

});

</script>

<script>

if ('serviceWorker' in navigator) {

    window.addEventListener(
        'load',
        function () {

            navigator.serviceWorker.register(
                '/flux_hotel/sw.js'
            )

            .then(function (registration) {

                console.log(
                    'Hotel Flow PWA activée',
                    registration.scope
                );

            })

            .catch(function (error) {

                console.error(
                    'Erreur Service Worker :',
                    error
                );

            });

        }
    );

}

</script>


</body>

</html>
