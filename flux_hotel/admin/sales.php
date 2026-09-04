<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';


/*
|--------------------------------------------------------------------------
| VÉRIFICATION DE CONNEXION
|--------------------------------------------------------------------------
*/

if (!isLoggedIn()) {

    header('Location: /flux_hotel/auth/login.php');
    exit;

}


/*
|--------------------------------------------------------------------------
| VÉRIFICATION DU RÔLE
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
| HÔTEL DE L'UTILISATEUR
|--------------------------------------------------------------------------
*/

$hotelId = (int) ($_SESSION['hotel_id'] ?? 0);


/*
|--------------------------------------------------------------------------
| VARIABLES
|--------------------------------------------------------------------------
*/

$error = '';

$totalRevenue = 0;
$cashRevenue = 0;
$mobileMoneyRevenue = 0;

$stayCount = 0;

$activeRevenue = 0;
$completedRevenue = 0;

$totalExpenses = 0;

$totalCash = 0;


/*
|--------------------------------------------------------------------------
| RÉCUPÉRATION DES REVENUS DES CHAMBRES
|--------------------------------------------------------------------------
|
| IMPORTANT :
| On utilise uniquement la table "stays".
|
| Les ventes de :
| - Boisson
| - Petit déjeuner
| - Blanchisserie
| etc.
|
| ne sont PAS prises en compte.
|
|--------------------------------------------------------------------------
*/

try {

    /*
    |--------------------------------------------------------------------------
    | REVENUS TOTAUX DES CHAMBRES
    |--------------------------------------------------------------------------
    */

    $sql = "
        SELECT
            COALESCE(SUM(price), 0) AS total_revenue,
            COUNT(*) AS stay_count
        FROM stays
        WHERE hotel_id = :hotel_id
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':hotel_id' => $hotelId
    ]);

    $result = $stmt->fetch();

    $totalRevenue =
        (float) ($result['total_revenue'] ?? 0);

    $stayCount =
        (int) ($result['stay_count'] ?? 0);


    /*
    |--------------------------------------------------------------------------
    | PAIEMENTS EN ESPÈCES
    |--------------------------------------------------------------------------
    */

    $sql = "
        SELECT
            COALESCE(SUM(price), 0)
        FROM stays
        WHERE hotel_id = :hotel_id
        AND payment_method = 'cash'
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':hotel_id' => $hotelId
    ]);

    $cashRevenue =
        (float) $stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | MOBILE MONEY
    |--------------------------------------------------------------------------
    */

    $sql = "
        SELECT
            COALESCE(SUM(price), 0)
        FROM stays
        WHERE hotel_id = :hotel_id
        AND payment_method = 'mobile_money'
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':hotel_id' => $hotelId
    ]);

    $mobileMoneyRevenue =
        (float) $stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | SÉJOURS ACTUELLEMENT EN COURS
    |--------------------------------------------------------------------------
    */

    $sql = "
        SELECT
            COALESCE(SUM(price), 0)
        FROM stays
        WHERE hotel_id = :hotel_id
        AND status = 'active'
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':hotel_id' => $hotelId
    ]);

    $activeRevenue =
        (float) $stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | SÉJOURS TERMINÉS
    |--------------------------------------------------------------------------
    */

    $sql = "
        SELECT
            COALESCE(SUM(price), 0)
        FROM stays
        WHERE hotel_id = :hotel_id
        AND status = 'completed'
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':hotel_id' => $hotelId
    ]);

    $completedRevenue =
        (float) $stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | TOTAL DES DÉCAISSEMENTS
    |--------------------------------------------------------------------------
    |
    | Les décaissements sont pris dans la table expenses.
    |
    */

    $sql = "
        SELECT
            COALESCE(SUM(amount), 0)
        FROM expenses
        WHERE hotel_id = :hotel_id
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':hotel_id' => $hotelId
    ]);

    $totalExpenses =
        (float) $stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | TOTAL EN CAISSE
    |--------------------------------------------------------------------------
    |
    | La caisse physique correspond aux paiements en espèces.
    |
    | Total caisse =
    |
    | Paiements espèces - Décaissements
    |
    */

    $totalCash =
        $cashRevenue - $totalExpenses;


} catch (PDOException $e) {

    $error =
        "Impossible de récupérer les informations financières.";

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

<title>Revenus des chambres - Hotel Flow</title>

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

body {

    margin: 0;

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

    max-width: 1100px;

    margin: 35px auto;

    padding: 0 20px;

}

.page-title {

    margin-bottom: 30px;

}

.page-title h2 {

    margin: 0 0 8px;

    color: #1e3a5f;

}

.page-title p {

    margin: 0;

    color: #666;

}


/* =====================================================
   MESSAGE ERREUR
   ===================================================== */

.error {

    background: #f8d7da;

    color: #842029;

    padding: 15px;

    border-radius: 8px;

    margin-bottom: 25px;

}


/* =====================================================
   CARTES
   ===================================================== */

.cards {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 20px;

    margin-bottom: 25px;

}

.card {

    background: white;

    border-radius: 12px;

    padding: 25px;

    box-shadow:
        0 3px 12px
        rgba(0, 0, 0, 0.08);

}

.card-icon {

    font-size: 30px;

    margin-bottom: 12px;

}

.card-title {

    color: #666;

    font-size: 14px;

    margin-bottom: 8px;

}

.card-value {

    color: #1e3a5f;

    font-size: 24px;

    font-weight: bold;

}


/* =====================================================
   TOTAL CAISSE
   ===================================================== */

.cash-box {

    background: #1e3a5f;

    color: white;

    border-radius: 14px;

    padding: 28px;

    margin-bottom: 30px;

    box-shadow:
        0 4px 15px
        rgba(0, 0, 0, 0.12);

}

.cash-title {

    font-size: 15px;

    color: #dbe7f5;

    margin-bottom: 8px;

}

.cash-value {

    font-size: 32px;

    font-weight: bold;

}

.cash-description {

    margin-top: 8px;

    font-size: 13px;

    color: #dbe7f5;

}


/* =====================================================
   RÉSUMÉ
   ===================================================== */

.summary {

    background: white;

    border-radius: 12px;

    box-shadow:
        0 3px 12px
        rgba(0, 0, 0, 0.08);

    padding: 25px;

}

.summary h3 {

    margin-top: 0;

    color: #1e3a5f;

    margin-bottom: 20px;

}

.summary-row {

    display: flex;

    justify-content: space-between;

    align-items: center;

    padding: 16px 0;

    border-bottom: 1px solid #eee;

}

.summary-row:last-child {

    border-bottom: none;

}

.summary-label {

    color: #555;

}

.summary-value {

    font-size: 19px;

    font-weight: bold;

    color: #1e3a5f;

}

.summary-value.expense {

    color: #c62828;

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

@media (max-width: 900px) {

    .cards {

        grid-template-columns:
            repeat(2, 1fr);

    }

}


/* =====================================================
   MOBILE
   ===================================================== */

@media (max-width: 550px) {

    .container {

        padding: 0 15px;

        margin-top: 20px;

    }

    .cards {

        grid-template-columns: 1fr;

    }

    .summary-row {

        flex-direction: column;

        align-items: flex-start;

        gap: 6px;

    }

    .cash-value {

        font-size: 27px;

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
            Espace Administrateur
        </p>

    </div>

</header>


<!-- =====================================================
     NAVIGATION
     ===================================================== -->

<nav class="nav">

    <div class="nav-container">

        <a href="/flux_hotel/admin/dashboard.php">
            🏠 Tableau de bord
        </a>

        <a href="/flux_hotel/admin/rooms.php">
            🛏️ Chambres
        </a>

        <a href="/flux_hotel/admin/history.php">
            📋 Historique
        </a>

        <a
            class="active"
            href="/flux_hotel/admin/sales.php"
        >
            💰 Revenus chambres
        </a>

        <a href="/flux_hotel/admin/expenses.php">
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
            Revenus des chambres
        </h2>

        <p>
            Consultez les revenus générés uniquement
            par les séjours en chambre.
        </p>

    </div>


    <?php if ($error !== ''): ?>

        <div class="error">

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>


    <!-- =================================================
         CARTES PRINCIPALES
         ================================================= -->

    <div class="cards">


        <!-- REVENUS CHAMBRES -->

        <div class="card">

            <div class="card-icon">
                💰
            </div>

            <div class="card-title">
                Revenus chambres
            </div>

            <div class="card-value">

                <?= number_format(
                    $totalRevenue,
                    0,
                    ',',
                    ' '
                ) ?>

                FCFA

            </div>

        </div>


        <!-- ESPÈCES -->

        <div class="card">

            <div class="card-icon">
                💵
            </div>

            <div class="card-title">
                Paiements en espèces
            </div>

            <div class="card-value">

                <?= number_format(
                    $cashRevenue,
                    0,
                    ',',
                    ' '
                ) ?>

                FCFA

            </div>

        </div>


        <!-- MOBILE MONEY -->

        <div class="card">

            <div class="card-icon">
                📱
            </div>

            <div class="card-title">
                Mobile Money
            </div>

            <div class="card-value">

                <?= number_format(
                    $mobileMoneyRevenue,
                    0,
                    ',',
                    ' '
                ) ?>

                FCFA

            </div>

        </div>


        <!-- NOMBRE DE SÉJOURS -->

        <div class="card">

            <div class="card-icon">
                🛏️
            </div>

            <div class="card-title">
                Nombre de séjours
            </div>

            <div class="card-value">

                <?= $stayCount ?>

            </div>

        </div>


    </div>


    <!-- =================================================
         TOTAL EN CAISSE
         ================================================= -->

    <div class="cash-box">

        <div class="cash-title">

            💰 Total en caisse

        </div>

        <div class="cash-value">

            <?= number_format(
                $totalCash,
                0,
                ',',
                ' '
            ) ?>

            FCFA

        </div>

        <div class="cash-description">

            Paiements en espèces − décaissements

        </div>

    </div>


    <!-- =================================================
         RÉSUMÉ DES REVENUS
         ================================================= -->

    <div class="summary">

        <h3>
            Résumé des revenus
        </h3>


        <!-- SÉJOURS ACTIFS -->

        <div class="summary-row">

            <div class="summary-label">

                Séjours actuellement en cours

            </div>

            <div class="summary-value">

                <?= number_format(
                    $activeRevenue,
                    0,
                    ',',
                    ' '
                ) ?>

                FCFA

            </div>

        </div>


        <!-- SÉJOURS TERMINÉS -->

        <div class="summary-row">

            <div class="summary-label">

                Séjours terminés

            </div>

            <div class="summary-value">

                <?= number_format(
                    $completedRevenue,
                    0,
                    ',',
                    ' '
                ) ?>

                FCFA

            </div>

        </div>


        <!-- DÉCAISSEMENTS -->

        <div class="summary-row">

            <div class="summary-label">

                Décaissements

            </div>

            <div class="summary-value expense">

                - <?= number_format(
                    $totalExpenses,
                    0,
                    ',',
                    ' '
                ) ?>

                FCFA

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
