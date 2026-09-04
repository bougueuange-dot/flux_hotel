<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';


/*
|--------------------------------------------------------------------------
| Vérification de connexion
|--------------------------------------------------------------------------
*/

if (!isLoggedIn()) {

    header('Location: /flux_hotel/auth/login.php');
    exit;

}


/*
|--------------------------------------------------------------------------
| Vérification du rôle
|--------------------------------------------------------------------------
|
| 1 = Administrateur
| 2 = Réceptionniste
|
*/

if ((int) ($_SESSION['role_id'] ?? 0) !== 1) {

    header('Location: /flux_hotel/reception/dashboard.php');
    exit;

}


/*
|--------------------------------------------------------------------------
| Hôtel de l'utilisateur connecté
|--------------------------------------------------------------------------
*/

$hotelId = (int) ($_SESSION['hotel_id'] ?? 0);

$firstName = $_SESSION['first_name'] ?? '';

$lastName = $_SESSION['last_name'] ?? '';


/*
|--------------------------------------------------------------------------
| Variables
|--------------------------------------------------------------------------
*/

$rooms = [];

$error = '';


/*
|--------------------------------------------------------------------------
| Vérification de l'hôtel
|--------------------------------------------------------------------------
*/

if ($hotelId <= 0) {

    $error = "Aucun hôtel n'est associé à cet utilisateur.";

} else {


    /*
    |--------------------------------------------------------------------------
    | RÉCUPÉRATION DES CHAMBRES
    |--------------------------------------------------------------------------
    */

    try {

        $sql = "
            SELECT
                id,
                room_number,
                room_type,
                price,
                status
            FROM rooms
            WHERE hotel_id = :hotel_id
            ORDER BY room_number ASC
        ";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([

            ':hotel_id' => $hotelId

        ]);

        $rooms = $stmt->fetchAll();


    } catch (PDOException $e) {

        $error =
            "Impossible de récupérer les chambres.";

    }

}

?>


<!DOCTYPE html>

<html lang="fr">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Chambres - Administration - Hotel Flow</title>
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

/* =====================================================
   RESET
   ===================================================== */

* {

    box-sizing: border-box;

}

html,
body {

    margin: 0;

    padding: 0;

}

body {

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background: #f4f6f9;

    color: #222;

}


/* =====================================================
   HEADER
   ===================================================== */

.header {

    background: #1e3a5f;

    color: white;

    padding: 20px;

}

.header-content {

    max-width: 1200px;

    margin: auto;

}

.header h1 {

    margin: 0;

    font-size: 28px;

}

.header p {

    margin: 5px 0 0;

    color: #dbe7f5;

}


/* =====================================================
   NAVIGATION
   ===================================================== */

.nav {

    background: white;

    border-bottom: 1px solid #ddd;

}

.nav-container {

    max-width: 1200px;

    margin: auto;

    display: flex;

    flex-wrap: wrap;

    gap: 5px;

    padding: 10px 20px;

}

.nav a {

    color: #1e3a5f;

    text-decoration: none;

    padding: 10px 15px;

    border-radius: 6px;

    font-size: 14px;

}

.nav a:hover {

    background: #eaf0f7;

}

.nav .active {

    background: #1e3a5f;

    color: white;

}

.nav .logout {

    margin-left: auto;

    background: #c62828;

    color: white;

}

.nav .logout:hover {

    background: #a91f1f;

}


/* =====================================================
   CONTENU
   ===================================================== */

.container {

    max-width: 1200px;

    margin: 30px auto;

    padding: 0 20px;

}

.page-title {

    margin-bottom: 25px;

}

.page-title h2 {

    margin-bottom: 8px;

    color: #1e3a5f;

}

.page-title p {

    color: #666;

}


/* =====================================================
   ERREUR
   ===================================================== */

.error {

    background: #ffe5e5;

    color: #b00020;

    padding: 15px;

    border-radius: 8px;

    margin-bottom: 25px;

}


/* =====================================================
   LÉGENDE
   ===================================================== */

.legend {

    background: white;

    padding: 15px 20px;

    border-radius: 10px;

    box-shadow:
        0 2px 8px
        rgba(0, 0, 0, 0.07);

    margin-bottom: 25px;

    display: flex;

    flex-wrap: wrap;

    gap: 20px;

}

.legend-item {

    display: flex;

    align-items: center;

    gap: 8px;

    font-size: 14px;

}

.legend-color {

    width: 14px;

    height: 14px;

    border-radius: 50%;

}

.legend-available {

    background: #198754;

}

.legend-occupied {

    background: #dc3545;

}

.legend-reserved {

    background: #fd7e14;

}


/* =====================================================
   GRILLE DES CHAMBRES
   ===================================================== */

.rooms-grid {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 20px;

}


/* =====================================================
   CARTE CHAMBRE
   ===================================================== */

.room-card {

    background: white;

    border-radius: 12px;

    padding: 22px;

    box-shadow:
        0 3px 10px
        rgba(0, 0, 0, 0.08);

    border-top: 6px solid #999;

}


.room-card.available {

    border-top-color: #198754;

}


.room-card.occupied {

    border-top-color: #dc3545;

}


.room-card.reserved {

    border-top-color: #fd7e14;

}


/* =====================================================
   NUMÉRO CHAMBRE
   ===================================================== */

.room-number {

    font-size: 30px;

    font-weight: bold;

    color: #1e3a5f;

    margin-bottom: 8px;

}


/* =====================================================
   TYPE
   ===================================================== */

.room-type {

    color: #666;

    margin-bottom: 15px;

}


/* =====================================================
   PRIX
   ===================================================== */

.room-price {

    font-size: 18px;

    font-weight: bold;

    margin-bottom: 15px;

}


/* =====================================================
   BADGES
   ===================================================== */

.status {

    display: inline-block;

    padding: 7px 12px;

    border-radius: 20px;

    font-size: 13px;

    font-weight: bold;

}


.status.available {

    background: #d1e7dd;

    color: #0f5132;

}


.status.occupied {

    background: #f8d7da;

    color: #842029;

}


.status.reserved {

    background: #ffe5d0;

    color: #984c0c;

}


/* =====================================================
   AUCUNE CHAMBRE
   ===================================================== */

.empty {

    background: white;

    padding: 40px;

    border-radius: 10px;

    text-align: center;

    color: #666;

}


/* =====================================================
   FOOTER
   ===================================================== */

.footer {

    text-align: center;

    color: #777;

    padding: 40px 20px;

    font-size: 13px;

}


/* =====================================================
   TABLETTE
   ===================================================== */

@media (max-width: 1000px) {

    .rooms-grid {

        grid-template-columns:
            repeat(3, 1fr);

    }

}


@media (max-width: 750px) {

    .rooms-grid {

        grid-template-columns:
            repeat(2, 1fr);

    }

}


/* =====================================================
   TÉLÉPHONE
   ===================================================== */

@media (max-width: 500px) {

    .container {

        margin-top: 20px;

        padding: 0 15px;

    }

    .header {

        padding: 18px 15px;

    }

    .header h1 {

        font-size: 24px;

    }

    .nav-container {

        padding: 8px 15px;

    }

    .nav a {

        padding: 8px 10px;

        font-size: 13px;

    }

    .nav .logout {

        margin-left: 0;

    }

    .rooms-grid {

        grid-template-columns: 1fr;

    }

    .legend {

        flex-direction: column;

        gap: 10px;

    }

}
/* =====================================================
   ADAPTATION MOBILE HOTEL FLOW
   ===================================================== */

@media (max-width: 600px) {

    body {
        overflow-x: hidden;
    }

    .header {
        padding: 15px;
    }

    .header h1 {
        font-size: 22px;
    }

    .header p {
        font-size: 13px;
    }

    .container {
        width: 100%;
        margin: 20px auto;
        padding: 0 12px;
    }

    .page-title h2 {
        font-size: 22px;
    }

    .page-title p {
        font-size: 13px;
        line-height: 1.5;
    }

    .nav-container {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 6px;
        padding: 8px;
    }

    .nav a {
        text-align: center;
        padding: 10px 6px;
        font-size: 12px;
    }

    .nav .logout {
        margin-left: 0;
        grid-column: span 2;
    }

    /* Cartes */

    .card,
    .stat-card,
    .dashboard-card,
    .form-card {
        width: 100%;
    }

    /* Formulaires */

    input,
    select,
    textarea,
    button {
        width: 100%;
        min-height: 44px;
        font-size: 16px;
    }

    button {
        cursor: pointer;
    }

    /* Tables */

    .table-container {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    table {
        min-width: 800px;
    }

    /* Grilles */

    .rooms-grid,
    .cards-grid,
    .stats-grid {
        grid-template-columns: 1fr !important;
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
            Espace Administration
        </p>

    </div>

</header>


<!-- =====================================================
     NAVIGATION ADMINISTRATEUR
     ===================================================== -->

<nav class="nav">

    <div class="nav-container">


        <a
            href="/flux_hotel/admin/dashboard.php"
        >
            🏠 Tableau de bord
        </a>


        <a
            class="active"
            href="/flux_hotel/admin/rooms.php"
        >
            🛏️ Chambres
        </a>


        <a
            href="/flux_hotel/admin/history.php"
        >
            📋 Historique
        </a>


        <a
            href="/flux_hotel/admin/expenses.php"
        >
            💸 Décaissements
        </a>


        <a
            href="/flux_hotel/admin/alerts.php"
        >
            ⚠️ Alertes
        </a>

        <a href="/flux_hotel/admin/users.php">
            👤 Utilisateurs
        </a>
        <a
            class="logout"
            href="/flux_hotel/auth/logout.php"
        >
            Déconnexion
        </a>


    </div>

</nav>


<!-- =====================================================
     CONTENU
     ===================================================== -->

<main class="container">


    <div class="page-title">

        <h2>
            Chambres
        </h2>

        <p>
            Consultez l'état actuel des chambres de votre hôtel.
        </p>

    </div>


    <?php if ($error !== ''): ?>

        <div class="error">

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>


    <!-- =================================================
         LÉGENDE
         ================================================= -->

    <div class="legend">


        <div class="legend-item">

            <span
                class="legend-color legend-available"
            ></span>

            Disponible

        </div>


        <div class="legend-item">

            <span
                class="legend-color legend-occupied"
            ></span>

            Occupée

        </div>


        <div class="legend-item">

            <span
                class="legend-color legend-reserved"
            ></span>

            Réservée

        </div>


    </div>


    <!-- =================================================
         CHAMBRES
         ================================================= -->

    <?php if (count($rooms) === 0): ?>


        <div class="empty">

            <h3>
                Aucune chambre
            </h3>

            <p>

                Aucune chambre n'a encore été enregistrée
                pour cet hôtel.

            </p>

        </div>


    <?php else: ?>


        <div class="rooms-grid">


            <?php foreach ($rooms as $room): ?>


                <?php

                $status =
                    $room['status'];


                if (
                    $status === 'available'
                ) {

                    $statusText =
                        'Disponible';

                }

                elseif (
                    $status === 'occupied'
                ) {

                    $statusText =
                        'Occupée';

                }

                elseif (
                    $status === 'reserved'
                ) {

                    $statusText =
                        'Réservée';

                }

                else {

                    $statusText =
                        $status;

                }

                ?>


                <div
                    class="room-card
                    <?= htmlspecialchars($status) ?>"
                >


                    <div class="room-number">

                        Chambre

                        <?= htmlspecialchars(
                            $room['room_number']
                        ) ?>

                    </div>


                    <div class="room-type">

                        <?= htmlspecialchars(
                            $room['room_type']
                        ) ?>

                    </div>


                    <div class="room-price">

                        <?= number_format(
                            (float) $room['price'],
                            0,
                            ',',
                            ' '
                        ) ?>

                        FCFA / nuit

                    </div>


                    <span
                        class="status
                        <?= htmlspecialchars($status) ?>"
                    >

                        <?= htmlspecialchars(
                            $statusText
                        ) ?>

                    </span>


                </div>


            <?php endforeach; ?>


        </div>


    <?php endif; ?>


</main>


<!-- =====================================================
     FOOTER
     ===================================================== -->

<footer class="footer">

    Hotel Flow © <?= date('Y') ?>

</footer>
<script>

if ('serviceWorker' in navigator) {

    window.addEventListener('load', function () {

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

    });

}

</script>


</body>

</html>