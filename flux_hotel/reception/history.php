<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

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

Vérification du rôle
*/

if ((int) ($_SESSION['role_id'] ?? 0) !== 2) {

header('Location: /flux_hotel/admin/dashboard.php');
exit;

}

/*
|--------------------------------------------------------------------------

Informations utilisateur
*/

$hotelId = (int) $_SESSION['hotel_id'];

$firstName = $_SESSION['first_name'] ?? '';
$lastName = $_SESSION['last_name'] ?? '';

$error = '';

$success = '';

$stays = [];

/*
|--------------------------------------------------------------------------

LIBÉRER UNE CHAMBRE
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

$stayId = (int) ($_POST['stay_id'] ?? 0);

if ($stayId <= 0) {

    $error = "Séjour invalide.";

} else {

    try {

        /*
        |--------------------------------------------------------------------------
        | Début transaction
        |--------------------------------------------------------------------------
        */

        $pdo->beginTransaction();

        /*
        |--------------------------------------------------------------------------
        | Récupérer le séjour
        |--------------------------------------------------------------------------
        |
        | FOR UPDATE verrouille la ligne pendant l'opération.
        |
        */

        $sql = "
            SELECT
                id,
                room_id,
                status
            FROM stays
            WHERE id = :stay_id
            AND hotel_id = :hotel_id
            FOR UPDATE
        ";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([

            ':stay_id' => $stayId,

            ':hotel_id' => $hotelId

        ]);

        $stay = $stmt->fetch();

        if (!$stay) {

            throw new Exception(
                "Le séjour demandé n'existe pas."
            );

        }

        /*
        |--------------------------------------------------------------------------
        | Vérifier que le séjour est encore actif
        |--------------------------------------------------------------------------
        */

        if ($stay['status'] !== 'active') {

            throw new Exception(
                "Ce séjour est déjà terminé."
            );

        }

        /*
        |--------------------------------------------------------------------------
        | Enregistrer le départ réel
        |--------------------------------------------------------------------------
        */

        $sql = "
            UPDATE stays
            SET
                actual_departure_at = NOW(),
                status = 'completed'
            WHERE id = :stay_id
            AND hotel_id = :hotel_id
            AND status = 'active'
        ";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([

            ':stay_id' => $stayId,

            ':hotel_id' => $hotelId

        ]);

        if ($stmt->rowCount() !== 1) {

            throw new Exception(
                "Impossible de terminer le séjour."
            );

        }

        /*
        |--------------------------------------------------------------------------
        | Libérer la chambre
        |--------------------------------------------------------------------------
        */

        $sql = "
            UPDATE rooms
            SET status = 'available'
            WHERE id = :room_id
            AND hotel_id = :hotel_id
            AND status = 'occupied'
        ";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([

            ':room_id' => $stay['room_id'],

            ':hotel_id' => $hotelId

        ]);

        if ($stmt->rowCount() !== 1) {

            throw new Exception(
                "Impossible de libérer la chambre."
            );

        }

        /*
        |--------------------------------------------------------------------------
        | Valider
        |--------------------------------------------------------------------------
        */

        $pdo->commit();

        $success =
            "Départ enregistré avec succès. " .
            "La chambre est maintenant disponible.";

    } catch (Exception $e) {

        if ($pdo->inTransaction()) {

            $pdo->rollBack();

        }

        $error = $e->getMessage();

    }

}

}

/*
|--------------------------------------------------------------------------

RÉCUPÉRER L'HISTORIQUE
*/

try {

$sql = "
    SELECT

        s.id,

        s.occupation_type,

        s.arrival_at,

        s.duration,

        s.expected_departure_at,

        s.actual_departure_at,

        s.price,

        s.payment_method,

        s.status,

        c.first_name,

        c.last_name,

        c.cni_number,

        c.phone,

        r.room_number,

        r.room_type,

        u.first_name AS user_first_name,

        u.last_name AS user_last_name

    FROM stays s

    INNER JOIN clients c
        ON c.id = s.client_id

    INNER JOIN rooms r
        ON r.id = s.room_id

    INNER JOIN users u
        ON u.id = s.user_id

    WHERE s.hotel_id = :hotel_id

    ORDER BY s.arrival_at DESC
";

$stmt = $pdo->prepare($sql);

$stmt->execute([

    ':hotel_id' => $hotelId

]);

$stays = $stmt->fetchAll();

} catch (PDOException $e) {

$error =
    "Impossible de récupérer l'historique des séjours.";

}

?>

<!DOCTYPE html> <html lang="fr"> <head>
<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Historique - Hotel Flow</title>
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

        font-family: Arial, Helvetica, sans-serif;

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
       MESSAGES
       ===================================================== */

    .success {

        background: #d1e7dd;

        color: #0f5132;

        padding: 15px;

        border-radius: 8px;

        margin-bottom: 20px;

    }

    .error {

        background: #f8d7da;

        color: #842029;

        padding: 15px;

        border-radius: 8px;

        margin-bottom: 20px;

    }

    /* =====================================================
       TABLEAU
       ===================================================== */

    .table-container {

        background: white;

        border-radius: 12px;

        box-shadow: 0 3px 12px rgba(0, 0, 0, 0.08);

        overflow-x: auto;

    }

    table {

        width: 100%;

        border-collapse: collapse;

        min-width: 1050px;

    }

    th {

        background: #1e3a5f;

        color: white;

        padding: 14px 12px;

        text-align: left;

        font-size: 13px;

        white-space: nowrap;

    }

    td {

        padding: 14px 12px;

        border-bottom: 1px solid #eee;

        font-size: 13px;

        vertical-align: middle;

    }

    tr:hover td {

        background: #f8fafc;

    }

    /* =====================================================
       CLIENT
       ===================================================== */

    .client-name {

        font-weight: bold;

        color: #1e3a5f;

    }

    .cni {

        color: #666;

        font-size: 12px;

        margin-top: 3px;

    }

    /* =====================================================
       CHAMBRE
       ===================================================== */

    .room-number {

        font-weight: bold;

        font-size: 17px;

        color: #1e3a5f;

    }

    .room-type {

        color: #777;

        font-size: 12px;

    }

    /* =====================================================
       STATUT
       ===================================================== */

    .badge {

        display: inline-block;

        padding: 6px 10px;

        border-radius: 20px;

        font-size: 12px;

        font-weight: bold;

    }

    .badge-active {

        background: #cff4fc;

        color: #055160;

    }

    .badge-completed {

        background: #d1e7dd;

        color: #0f5132;

    }

    .badge-cancelled {

        background: #f8d7da;

        color: #842029;

    }

    /* =====================================================
       RETARD
       ===================================================== */

    .late {

        display: inline-block;

        margin-top: 6px;

        padding: 5px 8px;

        border-radius: 5px;

        background: #f8d7da;

        color: #842029;

        font-weight: bold;

        font-size: 11px;

    }

    .on-time {

        display: inline-block;

        margin-top: 6px;

        padding: 5px 8px;

        border-radius: 5px;

        background: #d1e7dd;

        color: #0f5132;

        font-size: 11px;

    }

    /* =====================================================
       BOUTON LIBÉRER
       ===================================================== */

    .release-button {

        border: none;

        background: #dc3545;

        color: white;

        padding: 9px 12px;

        border-radius: 6px;

        cursor: pointer;

        font-weight: bold;

        font-size: 12px;

    }

    .release-button:hover {

        background: #b02a37;

    }

    /* =====================================================
       AUCUN SÉJOUR
       ===================================================== */

    .empty {

        padding: 50px 20px;

        text-align: center;

        color: #777;

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
       MOBILE
       ===================================================== */

    @media (max-width: 600px) {

        .container {

            padding: 0 15px;

            margin-top: 20px;

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

    }

</style>

</head> <body> <!-- ========================================================= HEADER ========================================================= --> <header class="header">
<div class="header-content">

    <h1>
        🏨 Hotel Flow
    </h1>

    <p>
        Espace Réception
    </p>

</div>

</header> <!-- ========================================================= NAVIGATION ========================================================= --> <nav class="nav">
<div class="nav-container">

    <a href="/flux_hotel/reception/dashboard.php">
        🏠 Tableau de bord
    </a>
    <a href="/flux_hotel/reception/clients.php">
        👥 Clients
    </a>

    <a href="/flux_hotel/reception/checkin.php">
        📝 Enregistrement
    </a>

    <a href="/flux_hotel/reception/reservations.php">
        📅 Réservations
    </a>

    <a href="/flux_hotel/reception/rooms.php">
        🛏️ Chambres
    </a>

    <a
        class="active"
        href="/flux_hotel/reception/history.php"
    >
        📋 Historique
    </a>

    <a href="/flux_hotel/reception/expenses.php">
        💸 Décaissement
    </a>

    <a
        class="logout"
        href="/flux_hotel/auth/logout.php"
    >
        Déconnexion
    </a>

</div>

</nav> <!-- ========================================================= CONTENU ========================================================= --> <main class="container">
<div class="page-title">

    <h2>
        Historique des séjours
    </h2>

    <p>
        Consultez les arrivées, les départs et
        libérez les chambres lorsque les clients partent.
    </p>

</div>

<!-- =====================================================
     MESSAGES
     ===================================================== -->

<?php if ($success !== ''): ?>

    <div class="success">

        <?= htmlspecialchars($success) ?>

    </div>

<?php endif; ?>

<?php if ($error !== ''): ?>

    <div class="error">

        <?= htmlspecialchars($error) ?>

    </div>

<?php endif; ?>

<!-- =====================================================
     TABLEAU
     ===================================================== -->

<?php if (count($stays) === 0): ?>

    <div class="empty">

        <h3>
            Aucun séjour
        </h3>

        <p>
            Aucun séjour n'a encore été enregistré
            dans cet hôtel.
        </p>

    </div>

<?php else: ?>

    <div class="table-container">

        <table>

            <thead>

                <tr>

                    <th>
                        Client
                    </th>

                    <th>
                        Chambre
                    </th>

                    <th>
                        Arrivée
                    </th>

                    <th>
                        Départ prévu
                    </th>

                    <th>
                        Départ réel
                    </th>

                    <th>
                        Montant
                    </th>

                    <th>
                        Statut
                    </th>

                    <th>
                        Action
                    </th>

                </tr>

            </thead>

            <tbody>

                <?php foreach ($stays as $stay): ?>

                    <?php

                    /*
                    |--------------------------------------------------------------------------
                    | Calcul du retard
                    |--------------------------------------------------------------------------
                    */

                    $isLate = false;

                    $lateMinutes = 0;

                    if (
                        $stay['status'] === 'active'
                        &&
                        !empty($stay['expected_departure_at'])
                    ) {

                        $expectedTime =
                            strtotime(
                                $stay['expected_departure_at']
                            );

                        $currentTime = time();

                        $toleranceTime =
                            $expectedTime + (30 * 60);

                        if ($currentTime > $toleranceTime) {

                            $isLate = true;

                            $lateMinutes =
                                floor(
                                    ($currentTime - $expectedTime)
                                    / 60
                                );

                        }

                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Texte du statut
                    |--------------------------------------------------------------------------
                    */

                    if ($stay['status'] === 'active') {

                        $statusText = 'En cours';

                        $statusClass = 'badge-active';

                    } elseif ($stay['status'] === 'completed') {

                        $statusText = 'Terminé';

                        $statusClass = 'badge-completed';

                    } else {

                        $statusText = 'Annulé';

                        $statusClass = 'badge-cancelled';

                    }

                    ?>

                    <tr>

                        <!-- CLIENT -->

                        <td>

                            <div class="client-name">

                                <?= htmlspecialchars(
                                    $stay['first_name']
                                    . ' '
                                    . $stay['last_name']
                                ) ?>

                            </div>

                            <?php if (!empty($stay['cni_number'])): ?>

                                <div class="cni">

                                    CNI :
                                    <?= htmlspecialchars(
                                        $stay['cni_number']
                                    ) ?>

                                </div>

                            <?php endif; ?>

                            <?php if (!empty($stay['phone'])): ?>

                                <div class="cni">

                                    📞
                                    <?= htmlspecialchars(
                                        $stay['phone']
                                    ) ?>

                                </div>

                            <?php endif; ?>

                        </td>

                        <!-- CHAMBRE -->

                        <td>

                            <div class="room-number">

                                <?= htmlspecialchars(
                                    $stay['room_number']
                                ) ?>

                            </div>

                            <div class="room-type">

                                <?= htmlspecialchars(
                                    $stay['room_type']
                                ) ?>

                            </div>

                        </td>

                        <!-- ARRIVÉE -->

                        <td>

                            <?= date(
                                'd/m/Y',
                                strtotime($stay['arrival_at'])
                            ) ?>

                            <br>

                            <strong>

                                <?= date(
                                    'H:i',
                                    strtotime($stay['arrival_at'])
                                ) ?>

                            </strong>

                        </td>

                        <!-- DÉPART PRÉVU -->

                        <td>

                            <?= date(
                                'd/m/Y',
                                strtotime(
                                    $stay['expected_departure_at']
                                )
                            ) ?>

                            <br>

                            <strong>

                                <?= date(
                                    'H:i',
                                    strtotime(
                                        $stay['expected_departure_at']
                                    )
                                ) ?>

                            </strong>

                            <?php if ($isLate): ?>

                                <div class="late">

                                    ⚠️ RETARD

                                    <br>

                                    <?= $lateMinutes ?>
                                    minute(s)

                                </div>

                            <?php elseif ($stay['status'] === 'active'): ?>

                                <div class="on-time">

                                    ✓ Dans la tolérance

                                </div>

                            <?php endif; ?>

                        </td>

                        <!-- DÉPART RÉEL -->

                        <td>

                            <?php if (!empty($stay['actual_departure_at'])): ?>

                                <?= date(
                                    'd/m/Y',
                                    strtotime(
                                        $stay['actual_departure_at']
                                    )
                                ) ?>

                                <br>

                                <strong>

                                    <?= date(
                                        'H:i',
                                        strtotime(
                                            $stay['actual_departure_at']
                                        )
                                    ) ?>

                                </strong>

                            <?php else: ?>

                                <span style="color:#999;">
                                    —
                                </span>

                            <?php endif; ?>

                        </td>

                        <!-- MONTANT -->

                        <td>

                            <strong>

                                <?= number_format(
                                    (float) $stay['price'],
                                    0,
                                    ',',
                                    ' '
                                ) ?>

                                FCFA

                            </strong>

                            <br>

                            <span style="color:#777; font-size:11px;">

                                <?= htmlspecialchars(
                                    $stay['payment_method']
                                ) ?>

                            </span>

                        </td>

                        <!-- STATUT -->

                        <td>

                            <span class="badge <?= $statusClass ?>">

                                <?= $statusText ?>

                            </span>

                        </td>

                        <!-- ACTION -->

                        <td>

                            <?php if ($stay['status'] === 'active'): ?>

                                <form
                                    method="POST"
                                    action=""
                                    onsubmit="
                                        return confirm(
                                            'Confirmer le départ de ce client et libérer la chambre ?'
                                        );
                                    "
                                >

                                    <input
                                        type="hidden"
                                        name="stay_id"
                                        value="<?= (int) $stay['id'] ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="release-button"
                                    >

                                        🔓 Libérer

                                    </button>

                                </form>

                            <?php else: ?>

                                <span style="color:#999;">

                                    Terminé

                                </span>

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

    </div>

<?php endif; ?>

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