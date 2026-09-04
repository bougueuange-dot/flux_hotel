<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
// ======================================================
// VÉRIFICATION AUTOMATIQUE DES ALERTES
// ======================================================

if (
    isset($_SESSION['user_id']) &&
    isset($_SESSION['hotel_id'])
) {

    $hotel_id = (int) $_SESSION['hotel_id'];

    try {

        $sql = "

            SELECT
                s.id AS stay_id,
                s.expected_departure_at,
                c.first_name,
                c.last_name,
                r.room_number

            FROM stays s

            INNER JOIN clients c
                ON c.id = s.client_id

            INNER JOIN rooms r
                ON r.id = s.room_id

            WHERE s.hotel_id = :hotel_id

            AND s.expected_departure_at <= DATE_ADD(
                NOW(),
                INTERVAL 24 HOUR
            )

        ";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            ':hotel_id' => $hotel_id
        ]);

        $staysForAlerts = $stmt->fetchAll(PDO::FETCH_ASSOC);


        foreach ($staysForAlerts as $stay) {

            $stay_id = (int) $stay['stay_id'];

            // Vérifier si une alerte existe déjà
            $check = $pdo->prepare("

                SELECT id

                FROM alerts

                WHERE hotel_id = :hotel_id

                AND stay_id = :stay_id

                AND type IN (
                    'departure',
                    'departure_overdue'
                )

                LIMIT 1

            ");

            $check->execute([

                ':hotel_id' => $hotel_id,

                ':stay_id' => $stay_id

            ]);

            $existingAlert = $check->fetch(PDO::FETCH_ASSOC);


            // Créer uniquement si elle n'existe pas
            if (!$existingAlert) {

                $clientName =
                    $stay['first_name']
                    . ' '
                    . $stay['last_name'];

                $departure =
                    date(
                        'd/m/Y H:i',
                        strtotime(
                            $stay['expected_departure_at']
                        )
                    );


                if (
                    strtotime(
                        $stay['expected_departure_at']
                    ) < time()
                ) {

                    $type = 'departure_overdue';

                    $message =
                        'Départ dépassé : '
                        . $clientName
                        . ' - Chambre '
                        . $stay['room_number']
                        . '. Départ prévu le '
                        . $departure;

                } else {

                    $type = 'departure';

                    $message =
                        'Départ prochain : '
                        . $clientName
                        . ' - Chambre '
                        . $stay['room_number']
                        . '. Départ prévu le '
                        . $departure;

                }


                $insert = $pdo->prepare("

                    INSERT INTO alerts (
                        hotel_id,
                        stay_id,
                        type,
                        message,
                        alert_at,
                        is_read,
                        created_at
                    )

                    VALUES (
                        :hotel_id,
                        :stay_id,
                        :type,
                        :message,
                        NOW(),
                        0,
                        NOW()
                    )

                ");

                $insert->execute([

                    ':hotel_id' => $hotel_id,

                    ':stay_id' => $stay_id,

                    ':type' => $type,

                    ':message' => $message

                ]);

            }

        }


    } catch (PDOException $e) {

        // Ne pas bloquer le dashboard
        // si la vérification des alertes rencontre une erreur.

    }

}
// ======================================================
// NOMBRE D'ALERTES NON LUES
// ======================================================

$unreadAlerts = 0;

if (isset($_SESSION['hotel_id'])) {

    $hotel_id = (int) $_SESSION['hotel_id'];

    try {

        $stmt = $pdo->prepare("

            SELECT COUNT(*)

            FROM alerts

            WHERE hotel_id = :hotel_id

            AND is_read = 0

        ");

        $stmt->execute([
            ':hotel_id' => $hotel_id
        ]);

        $unreadAlerts = (int) $stmt->fetchColumn();

    } catch (PDOException $e) {

        $unreadAlerts = 0;

    }

}


/*
|--------------------------------------------------------------------------

Vérification de connexion
*/

if (!isLoggedIn()) {

header('Location: /flux_hotel/auth/login.php');
exit;

}

/*
|--------------------------------------------------------------------------

Vérification du rôle administrateur
*/

if ((int) ($_SESSION['role_id'] ?? 0) !== 1) {

header('Location: /flux_hotel/reception/dashboard.php');
exit;

}

/*
|--------------------------------------------------------------------------

Informations de session
*/

$hotelId = (int) ($_SESSION['hotel_id'] ?? 0);

$firstName = $_SESSION['first_name'] ?? '';

$lastName = $_SESSION['last_name'] ?? '';

/*
|--------------------------------------------------------------------------

Variables
*/

$availableRooms = 0;

$occupiedRooms = 0;

$reservedRooms = 0;

$totalRooms = 0;

$todaySales = 0;

$todayExpenses = 0;

$balance = 0;

$lateDepartures = [];

$error = '';

/*
|--------------------------------------------------------------------------

Récupération des données
*/

try {

/*
|--------------------------------------------------------------------------
| 1. STATISTIQUES DES CHAMBRES
|--------------------------------------------------------------------------
*/

$sql = "

    SELECT

        COUNT(*) AS total_rooms,

        SUM(status = 'available')
            AS available_rooms,

        SUM(status = 'occupied')
            AS occupied_rooms,

        SUM(status = 'reserved')
            AS reserved_rooms

    FROM rooms

    WHERE hotel_id = :hotel_id

";

$stmt = $pdo->prepare($sql);

$stmt->execute([

    ':hotel_id' => $hotelId

]);

$roomStats = $stmt->fetch();

$totalRooms =
    (int) ($roomStats['total_rooms'] ?? 0);

$availableRooms =
    (int) ($roomStats['available_rooms'] ?? 0);

$occupiedRooms =
    (int) ($roomStats['occupied_rooms'] ?? 0);

$reservedRooms =
    (int) ($roomStats['reserved_rooms'] ?? 0);

/*
|--------------------------------------------------------------------------
| 2. VENTES DU JOUR
|--------------------------------------------------------------------------
*/

$sql = "

    SELECT

        COALESCE(
            SUM(amount),
            0
        )

    FROM sales

    WHERE hotel_id = :hotel_id

    AND DATE(sold_at) = CURDATE()

";

$stmt = $pdo->prepare($sql);

$stmt->execute([

    ':hotel_id' => $hotelId

]);

$todaySales =
    (float) $stmt->fetchColumn();

/*
|--------------------------------------------------------------------------
| 3. DÉCAISSEMENTS DU JOUR
|--------------------------------------------------------------------------
*/

$sql = "

    SELECT

        COALESCE(
            SUM(amount),
            0
        )

    FROM expenses

    WHERE hotel_id = :hotel_id

    AND DATE(expense_date) = CURDATE()

";

$stmt = $pdo->prepare($sql);

$stmt->execute([

    ':hotel_id' => $hotelId

]);

$todayExpenses =
    (float) $stmt->fetchColumn();

/*
|--------------------------------------------------------------------------
| 4. SOLDE DU JOUR
|--------------------------------------------------------------------------
*/

$balance =
    $todaySales - $todayExpenses;

/*
|--------------------------------------------------------------------------
| 5. DÉPARTS EN RETARD
|--------------------------------------------------------------------------
|
| Retard uniquement après 30 minutes de tolérance.
|
*/

$sql = "

    SELECT

        s.id AS stay_id,

        s.expected_departure_at,

        c.first_name,

        c.last_name,

        r.room_number,

        TIMESTAMPDIFF(

            MINUTE,

            s.expected_departure_at,

            NOW()

        ) AS delay_minutes

    FROM stays s

    INNER JOIN clients c
        ON c.id = s.client_id

    INNER JOIN rooms r
        ON r.id = s.room_id

    WHERE s.hotel_id = :hotel_id

    AND s.status = 'active'

    AND NOW() >

        DATE_ADD(

            s.expected_departure_at,

            INTERVAL 30 MINUTE

        )

    ORDER BY s.expected_departure_at ASC

";

$stmt = $pdo->prepare($sql);

$stmt->execute([

    ':hotel_id' => $hotelId

]);

$lateDepartures =
    $stmt->fetchAll();

} catch (PDOException $e) {

$error =
    'Impossible de charger les données du tableau de bord.';

}

?>

<!DOCTYPE html> <html lang="fr"> <head>
<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    Tableau de bord - Hotel Flow
</title>
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

        font-size: 27px;

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

        text-decoration: none;

        color: #1e3a5f;

        padding: 10px 14px;

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

    /* =====================================================
       CONTENU
       ===================================================== */

    .container {

        max-width: 1200px;

        margin: 30px auto;

        padding: 0 20px;

    }

    .welcome {

        margin-bottom: 25px;

    }

    .welcome h2 {

        margin-bottom: 5px;

        color: #1e3a5f;

    }

    .welcome p {

        color: #666;

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
       CARTES CHAMBRES
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

        padding: 22px;

        border-radius: 12px;

        box-shadow:
            0 3px 12px
            rgba(0, 0, 0, 0.08);

    }

    .card-title {

        color: #666;

        font-size: 14px;

        margin-bottom: 10px;

    }

    .card-number {

        font-size: 30px;

        font-weight: bold;

        color: #1e3a5f;

    }

    .available {

        border-left:
            5px solid #198754;

    }

    .occupied {

        border-left:
            5px solid #dc3545;

    }

    .reserved {

        border-left:
            5px solid #ffc107;

    }

    .total {

        border-left:
            5px solid #0d6efd;

    }

    /* =====================================================
       FINANCES
       ===================================================== */

    .finance-grid {

        display: grid;

        grid-template-columns:
            repeat(3, 1fr);

        gap: 20px;

        margin-bottom: 30px;

    }

    .finance-card {

        background: white;

        padding: 25px;

        border-radius: 12px;

        box-shadow:
            0 3px 12px
            rgba(0, 0, 0, 0.08);

    }

    .finance-title {

        color: #666;

        font-size: 14px;

        margin-bottom: 10px;

    }

    .finance-amount {

        font-size: 25px;

        font-weight: bold;

    }

    .sales {

        border-left:
            5px solid #198754;

    }

    .expenses {

        border-left:
            5px solid #dc3545;

    }

    .balance {

        border-left:
            5px solid #0d6efd;

    }

    .sales .finance-amount {

        color: #198754;

    }

    .expenses .finance-amount {

        color: #dc3545;

    }

    .balance .finance-amount {

        color: #0d6efd;

    }

    /* =====================================================
       ALERTES
       ===================================================== */

    .section {

        background: white;

        padding: 25px;

        border-radius: 12px;

        box-shadow:
            0 3px 12px
            rgba(0, 0, 0, 0.08);

    }

    .section h3 {

        margin-top: 0;

        color: #1e3a5f;

    }

    .alert {

        background: #fff3cd;

        border-left:
            5px solid #dc3545;

        padding: 15px;

        border-radius: 8px;

        margin-bottom: 10px;

    }

    .alert:last-child {

        margin-bottom: 0;

    }

    .alert-client {

        font-weight: bold;

        color: #842029;

        margin-bottom: 6px;

    }

    .alert-details {

        color: #555;

        font-size: 14px;

    }

    .no-alert {

        text-align: center;

        padding: 30px;

        color: #777;

    }
    .dashboard-card {
    background: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 3px 12px rgba(0,0,0,0.08);
    position: relative;
}

.card-icon {
    font-size: 30px;
    margin-bottom: 10px;
}

.card-title {
    color: #666;
}

.card-number {
    font-size: 32px;
    font-weight: bold;
    color: #dc3545;
    margin: 5px 0 15px;
}

.dashboard-card a {
    color: #1e3a5f;
    text-decoration: none;
    font-weight: bold;
}


    .badge {

        display: inline-block;

        background: #dc3545;

        color: white;

        padding: 4px 9px;

        border-radius: 20px;

        font-size: 12px;

        margin-left: 5px;

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

    /* =====================================================
       RESPONSIVE
       ===================================================== */

    @media (max-width: 900px) {

        .cards {

            grid-template-columns:
                repeat(2, 1fr);

        }

        .finance-grid {

            grid-template-columns:
                1fr;

        }

    }

    @media (max-width: 600px) {

        .container {

            padding: 0 15px;

            margin-top: 20px;

        }

        .cards {

            grid-template-columns:
                1fr;

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

        .finance-card {

            padding: 20px;

        }

    }

</style>

</head> <body> <!-- ========================================================= HEADER ========================================================= --> <header class="header">
<div class="header-content">

    <h1>
        🏨 Hotel Flow
    </h1>

    <p>
        Espace Administrateur
    </p>

</div>
<div class="dashboard-card">

    <div class="card-icon">
        ⚠️
    </div>

    <div>

        <div class="card-title">
            Alertes
        </div>

        <div class="card-number">
            <?= $unreadAlerts ?>
        </div>

    </div>

    <a href="alerts.php">
        Voir les alertes →
    </a>

</div>


</header> <!-- ========================================================= NAVIGATION ========================================================= --> <nav class="nav">
<div class="nav-container">

    <a
        class="active"
        href="/flux_hotel/admin/dashboard.php"
    >
        🏠 Tableau de bord
    </a>

    <a
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
        href="/flux_hotel/admin/sales.php"
    >
        💰 Revenus chambres
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

</nav> <!-- ========================================================= CONTENU ========================================================= --> <main class="container">
<div class="welcome">

    <h2>

        Bonjour
        <?= htmlspecialchars($firstName) ?>
        <?= htmlspecialchars($lastName) ?>
        👋

    </h2>

    <p>

        Voici la situation de votre hôtel
        aujourd'hui.

    </p>

</div>

<?php if ($error !== ''): ?>

    <div class="error">

        ⚠️

        <?= htmlspecialchars($error) ?>

    </div>

<?php endif; ?>

<!-- =====================================================
     CHAMBRES
     ===================================================== -->

<div class="cards">

    <div class="card total">

        <div class="card-title">
            Total chambres
        </div>

        <div class="card-number">

            <?= $totalRooms ?>

        </div>

    </div>

    <div class="card available">

        <div class="card-title">
            Chambres disponibles
        </div>

        <div class="card-number">

            <?= $availableRooms ?>

        </div>

    </div>

    <div class="card occupied">

        <div class="card-title">
            Chambres occupées
        </div>

        <div class="card-number">

            <?= $occupiedRooms ?>

        </div>

    </div>

    <div class="card reserved">

        <div class="card-title">
            Chambres réservées
        </div>

        <div class="card-number">

            <?= $reservedRooms ?>

        </div>

    </div>

</div>

<!-- =====================================================
     FINANCES
     ===================================================== -->

<div class="finance-grid">

    <div class="finance-card sales">

        <div class="finance-title">

            💰 Ventes du jour

        </div>

        <div class="finance-amount">

            <?= number_format(
                $todaySales,
                0,
                ',',
                ' '
            ) ?>

            FCFA

        </div>

    </div>

    <div class="finance-card expenses">

        <div class="finance-title">

            💸 Décaissements du jour

        </div>

        <div class="finance-amount">

            <?= number_format(
                $todayExpenses,
                0,
                ',',
                ' '
            ) ?>

            FCFA

        </div>

    </div>

    <div class="finance-card balance">

        <div class="finance-title">

            💵 Solde du jour

        </div>

        <div class="finance-amount">

            <?= number_format(
                $balance,
                0,
                ',',
                ' '
            ) ?>

            FCFA

        </div>

    </div>

</div>

<!-- =====================================================
     ALERTES
     ===================================================== -->

<div class="section">

    <h3>

        ⚠️ Départs en retard

        <?php if (count($lateDepartures) > 0): ?>

            <span class="badge">

                <?= count($lateDepartures) ?>

            </span>

        <?php endif; ?>

    </h3>

    <?php if (count($lateDepartures) === 0): ?>

        <div class="no-alert">

            <div style="font-size:40px;">
                ✅
            </div>

            <p>
                Aucun départ en retard actuellement.
            </p>

        </div>

    <?php else: ?>

        <?php foreach ($lateDepartures as $departure): ?>

            <div class="alert">

                <div class="alert-client">

                    ⚠️

                    <?= htmlspecialchars(
                        $departure['first_name']
                        . ' '
                        . $departure['last_name']
                    ) ?>

                    —

                    Chambre

                    <?= htmlspecialchars(
                        $departure['room_number']
                    ) ?>

                </div>

                <div class="alert-details">

                    Départ prévu :

                    <strong>

                        <?= date(
                            'd/m/Y H:i',
                            strtotime(
                                $departure[
                                    'expected_departure_at'
                                ]
                            )
                        ) ?>

                    </strong>

                    &nbsp; | &nbsp;

                    Heure actuelle :

                    <strong>

                        <?= date('H:i') ?>

                    </strong>

                    &nbsp; | &nbsp;

                    Retard :

                    <strong>

                        <?= max(
                            0,
                            (int) $departure[
                                'delay_minutes'
                            ]
                        ) ?>

                        minute(s)

                    </strong>

                </div>

            </div>

        <?php endforeach; ?>

    <?php endif; ?>

</div>

</main> <footer class="footer">
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

</body> </html>