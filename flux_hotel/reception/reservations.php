<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

/*
|--------------------------------------------------------------------------
| CONNEXION
|--------------------------------------------------------------------------
*/

if (!isLoggedIn()) {
    header('Location: /flux_hotel/auth/login.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| RÔLE RÉCEPTIONNISTE
|--------------------------------------------------------------------------
*/

if ((int)($_SESSION['role_id'] ?? 0) !== 2) {
    header('Location: /flux_hotel/admin/dashboard.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| SESSION
|--------------------------------------------------------------------------
*/

$hotelId  = (int)($_SESSION['hotel_id'] ?? 0);
$userId   = (int)($_SESSION['user_id'] ?? 0);
$firstName = $_SESSION['first_name'] ?? '';
$lastName  = $_SESSION['last_name'] ?? '';

$error = '';
$success = '';

$rooms = [];
$reservations = [];

/*
|--------------------------------------------------------------------------
| DATE PAR DÉFAUT
|--------------------------------------------------------------------------
*/

$defaultArrival = date('Y-m-d\TH:i');

/*
|--------------------------------------------------------------------------
| TRAITEMENT DES FORMULAIRES
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    /*
    |--------------------------------------------------------------------------
    | NOUVELLE RÉSERVATION
    |--------------------------------------------------------------------------
    */

    if ($action === 'add_reservation') {

        /*
        |--------------------------------------------------------------------------
        | CLIENT
        |--------------------------------------------------------------------------
        */

        $clientId = (int)($_POST['client_id'] ?? 0);

        $firstNameClient = trim($_POST['first_name'] ?? '');
        $lastNameClient  = trim($_POST['last_name'] ?? '');
        $cniNumber       = trim($_POST['cni_number'] ?? '');
        $phone           = trim($_POST['phone'] ?? '');

        /*
        |--------------------------------------------------------------------------
        | RÉSERVATION
        |--------------------------------------------------------------------------
        */

        $roomId = (int)($_POST['room_id'] ?? 0);

        $stayType = trim(
            $_POST['stay_type'] ?? 'Nuitée'
        );

        $arrivalAt = trim(
            $_POST['arrival_at'] ?? ''
        );

        $duration = (int)(
            $_POST['duration'] ?? 0
        );

        $price = (float)(
            $_POST['price'] ?? 0
        );

        $paymentMethod = trim(
            $_POST['payment_method'] ?? ''
        );

        /*
        |--------------------------------------------------------------------------
        | VALIDATION
        |--------------------------------------------------------------------------
        */

        if ($roomId <= 0) {

            $error = "Veuillez sélectionner une chambre.";

        } elseif ($arrivalAt === '') {

            $error = "Veuillez sélectionner la date et l'heure d'arrivée.";

        } elseif ($duration <= 0) {

            $error = "La durée doit être supérieure à zéro.";

        } elseif ($price <= 0) {

            $error = "Veuillez renseigner un prix valide.";

        } elseif ($paymentMethod === '') {

            $error = "Veuillez sélectionner un mode de paiement.";

        } elseif ($clientId <= 0 && (
            $firstNameClient === '' ||
            $lastNameClient === ''
        )) {

            $error = "Veuillez sélectionner un client existant ou renseigner le nom et le prénom du nouveau client.";

        } else {

            try {

                /*
                |--------------------------------------------------------------------------
                | TRANSACTION
                |--------------------------------------------------------------------------
                */

                $pdo->beginTransaction();

                /*
                |--------------------------------------------------------------------------
                | CLIENT EXISTANT
                |--------------------------------------------------------------------------
                */

                if ($clientId > 0) {

                    $stmt = $pdo->prepare("
                        SELECT
                            id,
                            first_name,
                            last_name
                        FROM clients
                        WHERE id = :client_id
                        AND hotel_id = :hotel_id
                        LIMIT 1
                    ");

                    $stmt->execute([
                        ':client_id' => $clientId,
                        ':hotel_id'  => $hotelId
                    ]);

                    $client = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$client) {

                        throw new Exception(
                            "Le client sélectionné n'existe pas dans cet hôtel."
                        );
                    }

                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | NOUVEAU CLIENT
                    |--------------------------------------------------------------------------
                    */

                    /*
                     * Si un numéro CNI est fourni,
                     * on vérifie s'il existe déjà dans cet hôtel.
                     */

                    if ($cniNumber !== '') {

                        $stmt = $pdo->prepare("
                            SELECT id
                            FROM clients
                            WHERE hotel_id = :hotel_id
                            AND cni_number = :cni_number
                            LIMIT 1
                        ");

                        $stmt->execute([
                            ':hotel_id'   => $hotelId,
                            ':cni_number' => $cniNumber
                        ]);

                        $existingClient = $stmt->fetch(
                            PDO::FETCH_ASSOC
                        );

                        if ($existingClient) {

                            /*
                             * Le client existe déjà.
                             * On réutilise son ID.
                             */

                            $clientId = (int)$existingClient['id'];

                        }

                    }

                    /*
                    |--------------------------------------------------------------------------
                    | SI LE CLIENT N'EXISTE PAS → CRÉATION
                    |--------------------------------------------------------------------------
                    */

                    if ($clientId <= 0) {

                        $stmt = $pdo->prepare("
                            INSERT INTO clients (
                                hotel_id,
                                first_name,
                                last_name,
                                cni_number,
                                phone,
                                created_at
                            )
                            VALUES (
                                :hotel_id,
                                :first_name,
                                :last_name,
                                :cni_number,
                                :phone,
                                NOW()
                            )
                        ");

                        $stmt->execute([
                            ':hotel_id'   => $hotelId,
                            ':first_name' => $firstNameClient,
                            ':last_name'  => $lastNameClient,
                            ':cni_number' => $cniNumber !== ''
                                ? $cniNumber
                                : null,
                            ':phone' => $phone !== ''
                                ? $phone
                                : null
                        ]);

                        $clientId = (int)$pdo->lastInsertId();

                        if ($clientId <= 0) {

                            throw new Exception(
                                "Impossible de créer le client."
                            );
                        }
                    }
                }

                /*
                |--------------------------------------------------------------------------
                | CHAMBRE
                |--------------------------------------------------------------------------
                */

                $stmt = $pdo->prepare("
                    SELECT
                        id,
                        room_number,
                        room_type,
                        price,
                        status
                    FROM rooms
                    WHERE id = :room_id
                    AND hotel_id = :hotel_id
                    LIMIT 1
                    FOR UPDATE
                ");

                $stmt->execute([
                    ':room_id'  => $roomId,
                    ':hotel_id' => $hotelId
                ]);

                $room = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$room) {

                    throw new Exception(
                        "La chambre sélectionnée n'existe pas."
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | VÉRIFIER DISPONIBILITÉ
                |--------------------------------------------------------------------------
                */

                if ($room['status'] !== 'available') {

                    throw new Exception(
                        "Cette chambre n'est plus disponible."
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | DATE D'ARRIVÉE
                |--------------------------------------------------------------------------
                */

                try {

                    $arrivalDate = new DateTime($arrivalAt);

                } catch (Exception $e) {

                    throw new Exception(
                        "La date d'arrivée est invalide."
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | DATE DE DÉPART
                |--------------------------------------------------------------------------
                */

                $departureDate = clone $arrivalDate;

                $departureDate->modify(
                    '+' . $duration . ' day'
                );

                $expectedDepartureAt =
                    $departureDate->format(
                        'Y-m-d H:i:s'
                    );

                /*
                |--------------------------------------------------------------------------
                | CRÉATION DE LA RÉSERVATION
                |--------------------------------------------------------------------------
                */

                $stmt = $pdo->prepare("
                    INSERT INTO stays (
                        hotel_id,
                        client_id,
                        room_id,
                        user_id,
                        occupation_type,
                        stay_type,
                        arrival_at,
                        duration,
                        expected_departure_at,
                        actual_departure_at,
                        price,
                        payment_method,
                        status,
                        created_at
                    )
                    VALUES (
                        :hotel_id,
                        :client_id,
                        :room_id,
                        :user_id,
                        'reservation',
                        :stay_type,
                        :arrival_at,
                        :duration,
                        :expected_departure_at,
                        NULL,
                        :price,
                        :payment_method,
                        'active',
                        NOW()
                    )
                ");

                $stmt->execute([
                    ':hotel_id' => $hotelId,
                    ':client_id' => $clientId,
                    ':room_id' => $roomId,
                    ':user_id' => $userId,
                    ':stay_type' => $stayType,
                    ':arrival_at' => $arrivalDate->format(
                        'Y-m-d H:i:s'
                    ),
                    ':duration' => $duration,
                    ':expected_departure_at' =>
                        $expectedDepartureAt,
                    ':price' => $price,
                    ':payment_method' =>
                        $paymentMethod
                ]);

                /*
                |--------------------------------------------------------------------------
                | PASSAGE DE LA CHAMBRE À RESERVED
                |--------------------------------------------------------------------------
                */

                $stmt = $pdo->prepare("
                    UPDATE rooms
                    SET status = 'reserved'
                    WHERE id = :room_id
                    AND hotel_id = :hotel_id
                    AND status = 'available'
                ");

                $stmt->execute([
                    ':room_id'  => $roomId,
                    ':hotel_id' => $hotelId
                ]);

                if ($stmt->rowCount() !== 1) {

                    throw new Exception(
                        "La chambre vient d'être réservée par un autre utilisateur."
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | VALIDATION TRANSACTION
                |--------------------------------------------------------------------------
                */

                $pdo->commit();

                header(
                    'Location: /flux_hotel/reception/reservations.php?success=1'
                );

                exit;

            } catch (PDOException $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $error =
                    "Erreur base de données : " .
                    $e->getMessage();

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
    | ANNULER UNE RÉSERVATION
    |--------------------------------------------------------------------------
    */

    if ($action === 'cancel_reservation') {

        $reservationId = (int)(
            $_POST['reservation_id'] ?? 0
        );

        if ($reservationId <= 0) {

            $error = "Réservation invalide.";

        } else {

            try {

                $pdo->beginTransaction();

                /*
                |--------------------------------------------------------------------------
                | RÉCUPÉRER LA RÉSERVATION
                |--------------------------------------------------------------------------
                */

                $stmt = $pdo->prepare("
                    SELECT
                        id,
                        room_id,
                        status
                    FROM stays
                    WHERE id = :id
                    AND hotel_id = :hotel_id
                    AND occupation_type = 'reservation'
                    LIMIT 1
                    FOR UPDATE
                ");

                $stmt->execute([
                    ':id'       => $reservationId,
                    ':hotel_id' => $hotelId
                ]);

                $reservation = $stmt->fetch(
                    PDO::FETCH_ASSOC
                );

                if (!$reservation) {

                    throw new Exception(
                        "Réservation introuvable."
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | VÉRIFIER STATUT
                |--------------------------------------------------------------------------
                */

                if ($reservation['status'] !== 'active') {

                    throw new Exception(
                        "Cette réservation ne peut plus être annulée."
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | ANNULER
                |--------------------------------------------------------------------------
                */

                $stmt = $pdo->prepare("
                    UPDATE stays
                    SET status = 'cancelled'
                    WHERE id = :id
                    AND hotel_id = :hotel_id
                ");

                $stmt->execute([
                    ':id'       => $reservationId,
                    ':hotel_id' => $hotelId
                ]);

                /*
                |--------------------------------------------------------------------------
                | LIBÉRER LA CHAMBRE
                |--------------------------------------------------------------------------
                */

                $stmt = $pdo->prepare("
                    UPDATE rooms
                    SET status = 'available'
                    WHERE id = :room_id
                    AND hotel_id = :hotel_id
                    AND status = 'reserved'
                ");

                $stmt->execute([
                    ':room_id'  => $reservation['room_id'],
                    ':hotel_id' => $hotelId
                ]);

                /*
                |--------------------------------------------------------------------------
                | VALIDATION
                |--------------------------------------------------------------------------
                */

                $pdo->commit();

                header(
                    'Location: /flux_hotel/reception/reservations.php?cancelled=1'
                );

                exit;

            } catch (PDOException $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $error =
                    "Erreur base de données : " .
                    $e->getMessage();

            } catch (Exception $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $error = $e->getMessage();
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| MESSAGES
|--------------------------------------------------------------------------
*/

if (
    isset($_GET['success']) &&
    $_GET['success'] === '1'
) {

    $success =
        "La réservation a été enregistrée avec succès.";
}

if (
    isset($_GET['cancelled']) &&
    $_GET['cancelled'] === '1'
) {

    $success =
        "La réservation a été annulée et la chambre est de nouveau disponible.";
}

/*
|--------------------------------------------------------------------------
| RÉCUPÉRER LES CHAMBRES DISPONIBLES
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->prepare("
        SELECT
            id,
            room_number,
            room_type,
            price
        FROM rooms
        WHERE hotel_id = :hotel_id
        AND status = 'available'
        ORDER BY room_number ASC
    ");

    $stmt->execute([
        ':hotel_id' => $hotelId
    ]);

    $rooms = $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );

} catch (PDOException $e) {

    $error =
        "Impossible de récupérer les chambres : " .
        $e->getMessage();
}

/*
|--------------------------------------------------------------------------
| RÉCUPÉRER LES RÉSERVATIONS
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->prepare("
        SELECT
            s.id,
            s.arrival_at,
            s.expected_departure_at,
            s.duration,
            s.stay_type,
            s.price,
            s.payment_method,
            s.status,

            c.first_name,
            c.last_name,
            c.phone,

            r.room_number,
            r.room_type

        FROM stays s

        INNER JOIN clients c
            ON c.id = s.client_id

        INNER JOIN rooms r
            ON r.id = s.room_id

        WHERE s.hotel_id = :hotel_id
        AND s.occupation_type = 'reservation'

        ORDER BY
            CASE
                WHEN s.status = 'active' THEN 1
                WHEN s.status = 'completed' THEN 2
                ELSE 3
            END,
            s.arrival_at ASC
    ");

    $stmt->execute([
        ':hotel_id' => $hotelId
    ]);

    $reservations = $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );

} catch (PDOException $e) {

    $error =
        "Impossible de récupérer les réservations : " .
        $e->getMessage();
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

<title>Réservations - Hotel Flow</title>

<meta
    name="theme-color"
    content="#1e3a5f"
>

<style>

/*
|--------------------------------------------------------------------------
| RESET
|--------------------------------------------------------------------------
*/

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

    line-height: 1.5;
}

button,
input,
select {
    font-family: inherit;
}

/*
|--------------------------------------------------------------------------
| HEADER
|--------------------------------------------------------------------------
*/

.header {
    background: #1e3a5f;
    color: white;
    padding: 20px;
}

.header-content {
    max-width: 1200px;
    margin: 0 auto;
}

.header h1 {
    margin: 0;
    font-size: 28px;
}

.header p {
    margin: 5px 0 0;
    color: #dbe7f5;
}

/*
|--------------------------------------------------------------------------
| NAVIGATION
|--------------------------------------------------------------------------
*/

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

    transition: 0.2s;
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

/*
|--------------------------------------------------------------------------
| CONTENU
|--------------------------------------------------------------------------
*/

.container {
    max-width: 1200px;

    margin: 30px auto;

    padding: 0 20px;
}

.page-title {
    margin-bottom: 25px;
}

.page-title h2 {
    color: #1e3a5f;

    margin: 0 0 8px;
}

.page-title p {
    margin: 0;

    color: #666;
}

/*
|--------------------------------------------------------------------------
| MESSAGES
|--------------------------------------------------------------------------
*/

.success {
    background: #d1e7dd;

    color: #0f5132;

    padding: 15px;

    border-radius: 8px;

    margin-bottom: 20px;

    border-left: 4px solid #198754;
}

.error {
    background: #f8d7da;

    color: #842029;

    padding: 15px;

    border-radius: 8px;

    margin-bottom: 20px;

    border-left: 4px solid #c62828;
}

/*
|--------------------------------------------------------------------------
| CARTES
|--------------------------------------------------------------------------
*/

.card {
    background: white;

    padding: 30px;

    border-radius: 12px;

    box-shadow:
        0 3px 12px
        rgba(0, 0, 0, 0.08);

    margin-bottom: 25px;
}

.card h3 {
    color: #1e3a5f;

    margin-top: 0;

    margin-bottom: 25px;
}

/*
|--------------------------------------------------------------------------
| FORMULAIRE
|--------------------------------------------------------------------------
*/

.form-grid {
    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 20px;
}

.form-group {
    margin-bottom: 18px;
}

.form-group.full {
    grid-column: 1 / -1;
}

.form-group label {
    display: block;

    font-weight: bold;

    margin-bottom: 7px;

    color: #333;
}

.form-group input,
.form-group select {
    width: 100%;

    padding: 12px;

    border: 1px solid #ccc;

    border-radius: 7px;

    font-size: 15px;

    background: white;
}

.form-group input:focus,
.form-group select:focus {
    outline: none;

    border-color: #1e3a5f;

    box-shadow:
        0 0 0 3px
        rgba(30, 58, 95, 0.1);
}

/*
|--------------------------------------------------------------------------
| RECHERCHE CLIENT
|--------------------------------------------------------------------------
*/

.client-search {
    position: relative;
}

.search-results {
    position: absolute;

    top: calc(100% + 5px);

    left: 0;

    right: 0;

    background: white;

    border: 1px solid #ddd;

    border-radius: 8px;

    box-shadow:
        0 5px 15px
        rgba(0, 0, 0, 0.12);

    z-index: 1000;

    display: none;

    overflow: hidden;

    max-height: 300px;

    overflow-y: auto;
}

.search-result {
    padding: 13px 15px;

    border-bottom: 1px solid #eee;

    cursor: pointer;
}

.search-result:hover {
    background: #f0f5fa;
}

.search-result-name {
    font-weight: bold;

    color: #1e3a5f;
}

.search-result-info {
    font-size: 13px;

    color: #666;

    margin-top: 4px;
}

.no-result {
    padding: 15px;

    text-align: center;

    color: #666;
}

/*
|--------------------------------------------------------------------------
| CLIENT SÉLECTIONNÉ
|--------------------------------------------------------------------------
*/

.client-selected {
    margin-top: 10px;

    padding: 12px;

    background: #d1e7dd;

    color: #0f5132;

    border-radius: 7px;

    border-left: 4px solid #198754;
}

/*
|--------------------------------------------------------------------------
| NOUVEAU CLIENT
|--------------------------------------------------------------------------
*/

.new-client-box {
    display: none;

    margin-top: 15px;

    padding: 20px;

    background: #f8fafc;

    border: 1px solid #dce3ea;

    border-radius: 10px;
}

.new-client-title {
    margin: 0 0 15px;

    color: #1e3a5f;

    font-size: 17px;
}

.info-box {
    background: #fff3cd;

    color: #664d03;

    padding: 12px;

    border-radius: 7px;

    margin-bottom: 18px;

    font-size: 14px;
}

/*
|--------------------------------------------------------------------------
| BOUTONS
|--------------------------------------------------------------------------
*/

.btn {
    display: inline-block;

    padding: 12px 18px;

    border: none;

    border-radius: 7px;

    text-decoration: none;

    cursor: pointer;

    font-size: 15px;

    transition: 0.2s;
}

.btn-primary {
    background: #1e3a5f;

    color: white;
}

.btn-primary:hover {
    background: #162d49;
}

.btn-success {
    background: #198754;

    color: white;
}

.btn-success:hover {
    background: #146c43;
}

.btn-danger {
    background: #c62828;

    color: white;
}

.btn-danger:hover {
    background: #a91f1f;
}

.btn-secondary {
    background: #6c757d;

    color: white;
}

.btn-secondary:hover {
    background: #5c636a;
}

.btn-small {
    padding: 8px 12px;

    font-size: 13px;
}

/*
|--------------------------------------------------------------------------
| ACTIONS CLIENT
|--------------------------------------------------------------------------
*/

.client-actions {
    display: flex;

    gap: 10px;

    flex-wrap: wrap;

    margin-top: 10px;
}

/*
|--------------------------------------------------------------------------
| TABLEAU
|--------------------------------------------------------------------------
*/

.table-container {
    width: 100%;

    overflow-x: auto;
}

table {
    width: 100%;

    border-collapse: collapse;

    min-width: 1000px;
}

table th {
    background: #1e3a5f;

    color: white;

    padding: 12px;

    text-align: left;

    white-space: nowrap;
}

table td {
    padding: 12px;

    border-bottom: 1px solid #eee;

    vertical-align: middle;
}

table tr:hover {
    background: #f8fafc;
}

/*
|--------------------------------------------------------------------------
| BADGES
|--------------------------------------------------------------------------
*/

.badge {
    display: inline-block;

    padding: 5px 10px;

    border-radius: 20px;

    font-size: 12px;

    font-weight: bold;
}

.badge-active {
    background: #d1e7dd;

    color: #0f5132;
}

.badge-cancelled {
    background: #f8d7da;

    color: #842029;
}

.badge-completed {
    background: #cfe2ff;

    color: #084298;
}

/*
|--------------------------------------------------------------------------
| CHAMBRE
|--------------------------------------------------------------------------
*/

.room-price-info {
    margin-top: 7px;

    color: #198754;

    font-size: 13px;

    font-weight: bold;
}

/*
|--------------------------------------------------------------------------
| EMPTY
|--------------------------------------------------------------------------
*/

.empty {
    padding: 30px;

    text-align: center;

    color: #777;

    background: #f8f9fa;

    border-radius: 8px;
}

/*
|--------------------------------------------------------------------------
| FOOTER
|--------------------------------------------------------------------------
*/

.footer {
    text-align: center;

    color: #777;

    padding: 40px 20px;

    font-size: 13px;
}

/*
|--------------------------------------------------------------------------
| MOBILE
|--------------------------------------------------------------------------
*/

@media (max-width: 700px) {

    .container {
        margin-top: 20px;

        padding: 0 15px;
    }

    .card {
        padding: 20px;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }

    .form-group.full {
        grid-column: auto;
    }

    .nav-container {
        display: grid;

        grid-template-columns:
            repeat(2, 1fr);

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

    input,
    select,
    button {
        width: 100%;

        min-height: 44px;

        font-size: 16px;
    }

    .client-actions {
        flex-direction: column;
    }

    .client-actions .btn {
        width: 100%;

        text-align: center;
    }

}

</style>

</head>

<body>

<!-- =========================================================
     HEADER
     ========================================================= -->

<header class="header">

    <div class="header-content">

        <h1>🏨 Hotel Flow</h1>

        <p>
            Espace Réception —
            <?= htmlspecialchars(
                trim($firstName . ' ' . $lastName),
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </p>

    </div>

</header>

<!-- =========================================================
     NAVIGATION
     ========================================================= -->

<nav class="nav">

    <div class="nav-container">

        <a
            href="/flux_hotel/reception/dashboard.php"
        >
            🏠 Tableau de bord
        </a>

        <a
            href="/flux_hotel/reception/rooms.php"
        >
            🛏️ Chambres
        </a>

        <a
            href="/flux_hotel/reception/stays.php"
        >
            📝 Séjours
        </a>

        <a
            class="active"
            href="/flux_hotel/reception/reservations.php"
        >
            📅 Réservations
        </a>

        <a
            href="/flux_hotel/reception/clients.php"
        >
            👥 Clients
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
            Déconnexion
        </a>

    </div>

</nav>

<!-- =========================================================
     CONTENU
     ========================================================= -->

<main class="container">

    <div class="page-title">

        <h2>📅 Réservations</h2>

        <p>
            Planifiez et gérez les réservations des chambres.
        </p>

    </div>

    <!-- MESSAGES -->

    <?php if ($success !== ''): ?>

        <div class="success">

            ✅
            <?= htmlspecialchars(
                $success,
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </div>

    <?php endif; ?>

    <?php if ($error !== ''): ?>

        <div class="error">

            ⚠️
            <?= htmlspecialchars(
                $error,
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         NOUVELLE RÉSERVATION
         ===================================================== -->

    <div class="card">

        <h3>
            ➕ Nouvelle réservation
        </h3>

        <?php if (count($rooms) === 0): ?>

            <div class="error">

                ⚠️
                Aucune chambre disponible actuellement.

            </div>

        <?php endif; ?>


        <form
            method="POST"
            action=""
            id="reservationForm"
        >

            <input
                type="hidden"
                name="action"
                value="add_reservation"
            >

            <!-- =================================================
                 CLIENT
                 ================================================= -->

            <div class="form-group full">

                <label for="client_search">

                    👤 Client

                </label>

                <div class="client-search">

                    <input
                        type="text"
                        id="client_search"
                        placeholder="Rechercher un client existant : nom, prénom, CNI ou téléphone..."
                        autocomplete="off"
                    >

                    <div
                        id="search_results"
                        class="search-results"
                    ></div>

                </div>

                <input
                    type="hidden"
                    name="client_id"
                    id="client_id"
                    value=""
                >

                <div
                    id="client_selected"
                    class="client-selected"
                    style="display:none;"
                ></div>

                <div class="client-actions">

                    <button
                        type="button"
                        id="newClientBtn"
                        class="btn btn-primary"
                    >
                        ➕ Nouveau client
                    </button>

                    <button
                        type="button"
                        id="clearClientBtn"
                        class="btn btn-secondary"
                        style="display:none;"
                    >
                        ✖ Effacer le client
                    </button>

                </div>


                <!-- =============================================
                     NOUVEAU CLIENT
                     ============================================= -->

                <div
                    id="newClientBox"
                    class="new-client-box"
                >

                    <h4 class="new-client-title">

                        👤 Informations du nouveau client

                    </h4>

                    <div class="info-box">

                        💡 Si le client n'est pas encore enregistré,
                        renseignez simplement ses informations.
                        Il sera automatiquement créé dans cet hôtel
                        avant l'enregistrement de la réservation.

                    </div>

                    <div class="form-grid">

                        <div class="form-group">

                            <label for="first_name">

                                Prénom *

                            </label>

                            <input
                                type="text"
                                name="first_name"
                                id="first_name"
                                placeholder="Prénom"
                            >

                        </div>

                        <div class="form-group">

                            <label for="last_name">

                                Nom *

                            </label>

                            <input
                                type="text"
                                name="last_name"
                                id="last_name"
                                placeholder="Nom"
                            >

                        </div>

                        <div class="form-group">

                            <label for="phone">

                                Téléphone

                            </label>

                            <input
                                type="text"
                                name="phone"
                                id="phone"
                                placeholder="Ex : 6XXXXXXXX"
                            >

                        </div>

                        <div class="form-group">

                            <label for="cni_number">

                                Numéro CNI

                            </label>

                            <input
                                type="text"
                                name="cni_number"
                                id="cni_number"
                                placeholder="Numéro de CNI"
                            >

                        </div>

                    </div>

                </div>

            </div>


            <!-- =================================================
                 CHAMBRE + TYPE
                 ================================================= -->

            <div class="form-grid">

                <div class="form-group">

                    <label for="room_id">

                        🛏️ Chambre *

                    </label>

                    <select
                        name="room_id"
                        id="room_id"
                        required
                    >

                        <option value="">

                            -- Sélectionner une chambre --

                        </option>

                        <?php foreach ($rooms as $room): ?>

                            <option
                                value="<?= (int)$room['id'] ?>"
                                data-price="<?= htmlspecialchars(
                                    $room['price'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                            >

                                Chambre
                                <?= htmlspecialchars(
                                    $room['room_number'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                                -

                                <?= htmlspecialchars(
                                    $room['room_type'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                                -

                                <?= number_format(
                                    (float)$room['price'],
                                    0,
                                    ',',
                                    ' '
                                ) ?>

                                FCFA

                            </option>

                        <?php endforeach; ?>

                    </select>

                    <div
                        id="roomPriceInfo"
                        class="room-price-info"
                    ></div>

                </div>


                <div class="form-group">

                    <label for="stay_type">

                        🏨 Type de séjour *

                    </label>

                    <select
                        name="stay_type"
                        id="stay_type"
                        required
                    >

                        <option value="Nuitée">
                            Nuitée
                        </option>

                        <option value="Sieste">
                            Sieste
                        </option>

                        <option value="Journée">
                            Journée
                        </option>

                        <option value="Long séjour">
                            Long séjour
                        </option>

                        <option value="Autre">
                            Autre
                        </option>

                    </select>

                </div>


                <!-- =================================================
                     DATE
                     ================================================= -->

                <div class="form-group">

                    <label for="arrival_at">

                        📅 Date et heure d'arrivée *

                    </label>

                    <input
                        type="datetime-local"
                        name="arrival_at"
                        id="arrival_at"
                        value="<?= htmlspecialchars(
                            $defaultArrival,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        required
                    >

                </div>


                <!-- =================================================
                     DURÉE
                     ================================================= -->

                <div class="form-group">

                    <label for="duration">

                        ⏱️ Durée

                    </label>

                    <input
                        type="number"
                        name="duration"
                        id="duration"
                        value="1"
                        min="1"
                        required
                    >

                    <small
                        style="
                            color:#777;
                            display:block;
                            margin-top:5px;
                        "
                    >
                        Durée en jour(s)
                    </small>

                </div>


                <!-- =================================================
                     PRIX
                     ================================================= -->

                <div class="form-group">

                    <label for="price">

                        💰 Prix total *

                    </label>

                    <input
                        type="number"
                        name="price"
                        id="price"
                        min="1"
                        step="1"
                        placeholder="Ex : 25000"
                        required
                    >

                </div>


                <!-- =================================================
                     PAIEMENT
                     ================================================= -->

                <div class="form-group">

                    <label for="payment_method">

                        💳 Mode de paiement *

                    </label>

                    <select
                        name="payment_method"
                        id="payment_method"
                        required
                    >

                        <option value="">

                            -- Sélectionner --

                        </option>

                        <option value="cash">

                            Espèces

                        </option>

                        <option value="mobile_money">

                            Mobile Money

                        </option>

                        <option value="card">

                            Carte bancaire

                        </option>

                        <option value="bank">

                            Virement bancaire

                        </option>

                    </select>

                </div>

            </div>


            <!-- =================================================
                 BOUTON
                 ================================================= -->

            <div
                style="
                    margin-top:20px;
                    display:flex;
                    gap:10px;
                    flex-wrap:wrap;
                "
            >

                <button
                    type="submit"
                    class="btn btn-success"
                    <?= count($rooms) === 0
                        ? 'disabled'
                        : '' ?>
                >

                    📅 Enregistrer la réservation

                </button>

            </div>

        </form>

    </div>


    <!-- =====================================================
         LISTE DES RÉSERVATIONS
         ===================================================== -->

    <div class="card">

        <h3>

            📋 Liste des réservations

        </h3>

        <?php if (count($reservations) === 0): ?>

            <div class="empty">

                📭 Aucune réservation enregistrée.

            </div>

        <?php else: ?>

            <div class="table-container">

                <table>

                    <thead>

                        <tr>

                            <th>Client</th>

                            <th>Téléphone</th>

                            <th>Chambre</th>

                            <th>Type</th>

                            <th>Arrivée</th>

                            <th>Départ prévu</th>

                            <th>Durée</th>

                            <th>Prix</th>

                            <th>Paiement</th>

                            <th>Statut</th>

                            <th>Action</th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php foreach ($reservations as $reservation): ?>

                        <tr>

                            <td>

                                <strong>

                                    <?= htmlspecialchars(
                                        $reservation['last_name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                   
                                    <?= htmlspecialchars(
                                        $reservation['first_name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </strong>

                            </td>

                            <td>

                                <?= htmlspecialchars(
                                    $reservation['phone'] ?? '',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </td>

                            <td>

                                🛏️

                                <?= htmlspecialchars(
                                    $reservation['room_number'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                                <br>

                                <small style="color:#777;">

                                    <?= htmlspecialchars(
                                        $reservation['room_type'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </small>

                            </td>

                            <td>

                                <?= htmlspecialchars(
                                    $reservation['stay_type'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </td>

                            <td>

                                <?= date(
                                    'd/m/Y H:i',
                                    strtotime(
                                        $reservation['arrival_at']
                                    )
                                ) ?>

                            </td>

                            <td>

                                <?= date(
                                    'd/m/Y H:i',
                                    strtotime(
                                        $reservation[
                                            'expected_departure_at'
                                        ]
                                    )
                                ) ?>

                            </td>

                            <td>

                                <?= (int)$reservation['duration'] ?>

                                jour(s)

                            </td>

                            <td>

                                <strong>

                                    <?= number_format(
                                        (float)$reservation['price'],
                                        0,
                                        ',',
                                        ' '
                                    ) ?>

                                    FCFA

                                </strong>

                            </td>

                            <td>

                                <?php

                                $paymentLabels = [
                                    'cash' => 'Espèces',
                                    'mobile_money' => 'Mobile Money',
                                    'card' => 'Carte',
                                    'bank' => 'Virement'
                                ];

                                echo htmlspecialchars(
                                    $paymentLabels[
                                        $reservation[
                                            'payment_method'
                                        ]
                                    ]
                                    ??
                                    $reservation[
                                        'payment_method'
                                    ],
                                    ENT_QUOTES,
                                    'UTF-8'
                                );

                                ?>

                            </td>

                            <td>

                                <?php if (
                                    $reservation['status']
                                    === 'active'
                                ): ?>

                                    <span
                                        class="badge badge-active"
                                    >
                                        Active
                                    </span>

                                <?php elseif (
                                    $reservation['status']
                                    === 'cancelled'
                                ): ?>

                                    <span
                                        class="badge badge-cancelled"
                                    >
                                        Annulée
                                    </span>

                                <?php else: ?>

                                    <span
                                        class="badge badge-completed"
                                    >
                                        Terminée
                                    </span>

                                <?php endif; ?>

                            </td>

                            <td>

                                <?php if (
                                    $reservation['status']
                                    === 'active'
                                ): ?>

                                    <form
                                        method="POST"
                                        onsubmit="
                                            return confirm(
                                                'Voulez-vous vraiment annuler cette réservation ?'
                                            );
                                        "
                                    >

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="cancel_reservation"
                                        >

                                        <input
                                            type="hidden"
                                            name="reservation_id"
                                            value="<?= (int)$reservation['id'] ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="btn btn-danger btn-small"
                                        >

                                            Annuler

                                        </button>

                                    </form>

                                <?php else: ?>

                                    —

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </div>

</main>


<!-- =========================================================
     FOOTER
     ========================================================= -->

<footer class="footer">

    Hotel Flow © <?= date('Y') ?>

</footer>


<script>

/*
|--------------------------------------------------------------------------
| ÉLÉMENTS
|--------------------------------------------------------------------------
*/

const searchInput =
    document.getElementById('client_search');

const searchResults =
    document.getElementById('search_results');

const clientIdInput =
    document.getElementById('client_id');

const clientSelected =
    document.getElementById('client_selected');

const newClientBtn =
    document.getElementById('newClientBtn');

const clearClientBtn =
    document.getElementById('clearClientBtn');

const newClientBox =
    document.getElementById('newClientBox');

const firstNameInput =
    document.getElementById('first_name');

const lastNameInput =
    document.getElementById('last_name');

const phoneInput =
    document.getElementById('phone');

const cniInput =
    document.getElementById('cni_number');

const reservationForm =
    document.getElementById('reservationForm');

const roomSelect =
    document.getElementById('room_id');

const priceInput =
    document.getElementById('price');

const roomPriceInfo =
    document.getElementById('roomPriceInfo');


let searchTimer = null;


/*
|--------------------------------------------------------------------------
| AFFICHER NOUVEAU CLIENT
|--------------------------------------------------------------------------
*/

if (newClientBtn) {

    newClientBtn.addEventListener(
        'click',
        function () {

            /*
             * On désélectionne le client existant.
             */

            clientIdInput.value = '';

            clientSelected.style.display =
                'none';

            searchInput.value = '';

            searchResults.innerHTML = '';

            searchResults.style.display =
                'none';

            /*
             * Afficher formulaire nouveau client
             */

            newClientBox.style.display =
                'block';

            clearClientBtn.style.display =
                'inline-block';

            firstNameInput.focus();
        }
    );
}


/*
|--------------------------------------------------------------------------
| EFFACER CLIENT
|--------------------------------------------------------------------------
*/

if (clearClientBtn) {

    clearClientBtn.addEventListener(
        'click',
        function () {

            clientIdInput.value = '';

            searchInput.value = '';

            clientSelected.innerHTML = '';

            clientSelected.style.display =
                'none';

            newClientBox.style.display =
                'none';

            clearClientBtn.style.display =
                'none';

            firstNameInput.value = '';
            lastNameInput.value = '';
            phoneInput.value = '';
            cniInput.value = '';

            searchResults.innerHTML = '';

            searchResults.style.display =
                'none';

            searchInput.focus();
        }
    );
}


/*
|--------------------------------------------------------------------------
| RECHERCHE CLIENT
|--------------------------------------------------------------------------
*/

if (searchInput) {

    searchInput.addEventListener(
        'input',
        function () {

            const query =
                this.value.trim();

            /*
             * Une nouvelle recherche annule
             * automatiquement la sélection.
             */

            clientIdInput.value = '';

            clientSelected.style.display =
                'none';

            clearClientBtn.style.display =
                'none';

            /*
             * Si l'utilisateur commence à rechercher
             * un client existant, on cache le formulaire
             * nouveau client.
             */

            if (query.length > 0) {

                newClientBox.style.display =
                    'none';
            }

            searchResults.innerHTML = '';

            searchResults.style.display =
                'none';

            if (query.length < 2) {

                return;
            }

            clearTimeout(searchTimer);

            searchTimer = setTimeout(
                function () {

                    searchClients(query);

                },
                300
            );
        }
    );
}


/*
|--------------------------------------------------------------------------
| CLIC EN DEHORS DE LA RECHERCHE
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'click',
    function (event) {

        if (
            !event.target.closest(
                '.client-search'
            )
        ) {

            searchResults.style.display =
                'none';
        }
    }
);


/*
|--------------------------------------------------------------------------
| RECHERCHER CLIENT SUR LE SERVEUR
|--------------------------------------------------------------------------
*/

async function searchClients(query) {

    try {

        searchResults.innerHTML =
            '<div class="no-result">Recherche...</div>';

        searchResults.style.display =
            'block';

        const response = await fetch(
            '/flux_hotel/reception/search_clients.php?q=' +
            encodeURIComponent(query)
        );

        if (!response.ok) {

            throw new Error(
                'Erreur HTTP'
            );
        }

        const data =
            await response.json();

        if (!data.success) {

            searchResults.innerHTML =
                '<div class="no-result">' +
                'Erreur de recherche.' +
                '</div>';

            return;
        }

        if (
            !data.clients ||
            data.clients.length === 0
        ) {

            searchResults.innerHTML =
                '<div class="no-result">' +
                'Aucun client trouvé.' +
                '</div>';

            return;
        }

        searchResults.innerHTML = '';

        data.clients.forEach(
            function (client) {

                const item =
                    document.createElement('div');

                item.className =
                    'search-result';

                const name =
                    document.createElement('div');

                name.className =
                    'search-result-name';

                name.textContent =
                    client.last_name +
                    ' ' +
                    client.first_name;

                const info =
                    document.createElement('div');

                info.className =
                    'search-result-info';

                let details = [];

                if (client.cni_number) {

                    details.push(
                        'CNI : ' +
                        client.cni_number
                    );
                }

                if (client.phone) {

                    details.push(
                        'Tél : ' +
                        client.phone
                    );
                }

                info.textContent =
                    details.join(' • ');

                item.appendChild(name);

                item.appendChild(info);


                /*
                 * Sélection client
                 */

                item.addEventListener(
                    'click',
                    function () {

                        clientIdInput.value =
                            client.id;

                        searchInput.value =
                            client.last_name +
                            ' ' +
                            client.first_name;

                        clientSelected.innerHTML =
                            '✅ Client sélectionné : ' +
                            '<strong>' +
                            escapeHtml(
                                client.last_name
                            ) +
                            ' ' +
                            escapeHtml(
                                client.first_name
                            ) +
                            '</strong>';

                        clientSelected.style.display =
                            'block';

                        searchResults.style.display =
                            'none';

                        newClientBox.style.display =
                            'none';

                        clearClientBtn.style.display =
                            'inline-block';

                        /*
                         * Nettoyage nouveau client
                         */

                        firstNameInput.value = '';
                        lastNameInput.value = '';
                        phoneInput.value = '';
                        cniInput.value = '';
                    }
                );

                searchResults.appendChild(item);

            }
        );

        searchResults.style.display =
            'block';

    } catch (error) {

        searchResults.innerHTML =
            '<div class="no-result">' +
            'Impossible de contacter le serveur.' +
            '</div>';

        searchResults.style.display =
            'block';
    }
}


/*
|--------------------------------------------------------------------------
| PROTECTION HTML
|--------------------------------------------------------------------------
*/

function escapeHtml(value) {

    const div =
        document.createElement('div');

    div.textContent =
        value ?? '';

    return div.innerHTML;
}


/*
|--------------------------------------------------------------------------
| VALIDATION DU FORMULAIRE
|--------------------------------------------------------------------------
*/

if (reservationForm) {

    reservationForm.addEventListener(
        'submit',
        function (event) {

            /*
             * Vérification client
             */

            const existingClient =
                clientIdInput.value.trim();

            const newFirstName =
                firstNameInput.value.trim();

            const newLastName =
                lastNameInput.value.trim();

            /*
             * Aucun client existant ET
             * aucun nouveau client correctement rempli.
             */

            if (
                existingClient === '' &&
                (
                    newFirstName === '' ||
                    newLastName === ''
                )
            ) {

                event.preventDefault();

                alert(
                    'Veuillez sélectionner un client existant ou cliquer sur "Nouveau client" puis renseigner le prénom et le nom.'
                );

                return;
            }


            /*
             * Vérification chambre
             */

            if (
                roomSelect.value === ''
            ) {

                event.preventDefault();

                alert(
                    'Veuillez sélectionner une chambre.'
                );

                roomSelect.focus();

                return;
            }


            /*
             * Vérification prix
             */

            if (
                parseFloat(priceInput.value) <= 0
                ||
                isNaN(
                    parseFloat(priceInput.value)
                )
            ) {

                event.preventDefault();

                alert(
                    'Veuillez renseigner un prix valide.'
                );

                priceInput.focus();

                return;
            }

        }
    );
}


/*
|--------------------------------------------------------------------------
| PRIX AUTOMATIQUE DE LA CHAMBRE
|--------------------------------------------------------------------------
*/

if (
    roomSelect &&
    priceInput
) {

    roomSelect.addEventListener(
        'change',
        function () {

            const option =
                this.options[
                    this.selectedIndex
                ];

            const price =
                option.dataset.price;

            if (price) {

                priceInput.value =
                    Math.round(
                        parseFloat(price)
                    );

                roomPriceInfo.textContent =
                    '💰 Tarif de la chambre : ' +
                    new Intl.NumberFormat(
                        'fr-FR'
                    ).format(
                        parseFloat(price)
                    ) +
                    ' FCFA';

            } else {

                priceInput.value = '';

                roomPriceInfo.textContent =
                    '';
            }
        }
    );
}


/*
|--------------------------------------------------------------------------
| EMPÊCHER LE DOUBLE CLIC
|--------------------------------------------------------------------------
*/

if (reservationForm) {

    reservationForm.addEventListener(
        'submit',
        function () {

            const submitButton =
                reservationForm.querySelector(
                    'button[type="submit"]'
                );

            if (submitButton) {

                submitButton.disabled =
                    true;

                submitButton.innerHTML =
                    '⏳ Enregistrement...';
            }

        }
    );
}

</script>

</body>

</html>