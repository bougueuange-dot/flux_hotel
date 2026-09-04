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

if ((int) ($_SESSION['role_id'] ?? 0) !== 2) {

    header('Location: /flux_hotel/admin/dashboard.php');

    exit;
}


/*
|--------------------------------------------------------------------------
| INFORMATIONS DE SESSION
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

$rooms = [];

$error = '';

$success = '';

$showClientForm = false;


/*
|--------------------------------------------------------------------------
| AFFICHER FORMULAIRE NOUVEAU CLIENT
|--------------------------------------------------------------------------
*/

if (
    isset($_GET['new_client'])
    &&
    $_GET['new_client'] === '1'
) {

    $showClientForm = true;

}


/*
|--------------------------------------------------------------------------
| TRAITEMENT DES FORMULAIRES
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {


    /*
    |--------------------------------------------------------------------------
    | AJOUT D'UN CLIENT
    |--------------------------------------------------------------------------
    */

    if (
        isset($_POST['action'])
        &&
        $_POST['action'] === 'add_client'
    ) {


        $clientFirstName =
            trim($_POST['first_name'] ?? '');

        $clientLastName =
            trim($_POST['last_name'] ?? '');

        $clientCni =
            trim($_POST['cni_number'] ?? '');

        $clientPhone =
            trim($_POST['phone'] ?? '');


        $showClientForm = true;


        if (
            $clientFirstName === ''
            ||
            $clientLastName === ''
        ) {

            $error =
                "Le prénom et le nom sont obligatoires.";

        } else {


            try {


                /*
                |--------------------------------------------------------------------------
                | VÉRIFICATION CNI
                |--------------------------------------------------------------------------
                */

                $existingClient = false;


                if ($clientCni !== '') {


                    $sql = "
                        SELECT id

                        FROM clients

                        WHERE hotel_id = :hotel_id

                        AND cni_number = :cni_number

                        LIMIT 1
                    ";


                    $stmt =
                        $pdo->prepare($sql);


                    $stmt->execute([

                        ':hotel_id' =>
                            $hotelId,

                        ':cni_number' =>
                            $clientCni

                    ]);


                    $existingClient =
                        $stmt->fetch();

                }


                if ($existingClient) {


                    $error =
                        "Un client avec cette CNI existe déjà.";


                } else {


                    /*
                    |--------------------------------------------------------------------------
                    | INSERTION CLIENT
                    |--------------------------------------------------------------------------
                    */

                    $sql = "
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
                    ";


                    $stmt =
                        $pdo->prepare($sql);


                    $stmt->execute([

                        ':hotel_id' =>
                            $hotelId,

                        ':first_name' =>
                            $clientFirstName,

                        ':last_name' =>
                            $clientLastName,

                        ':cni_number' =>
                            $clientCni !== ''
                                ? $clientCni
                                : null,

                        ':phone' =>
                            $clientPhone !== ''
                                ? $clientPhone
                                : null

                    ]);


                    $newClientId =
                        (int) $pdo->lastInsertId();


                    /*
                    |--------------------------------------------------------------------------
                    | REDIRECTION
                    |--------------------------------------------------------------------------
                    */

                    header(
                        'Location: /flux_hotel/reception/stays.php?client_id='
                        . $newClientId
                        . '&client_added=1'
                    );

                    exit;

                }


            } catch (PDOException $e) {


                $error =
                    "Impossible d'ajouter le client.";

            }

        }

    }


    /*
    |--------------------------------------------------------------------------
    | ENREGISTREMENT DU SÉJOUR
    |--------------------------------------------------------------------------
    */

    if (
        isset($_POST['action'])
        &&
        $_POST['action'] === 'add_stay'
    ) {


        $clientId =
            (int) ($_POST['client_id'] ?? 0);

        $roomId =
            (int) ($_POST['room_id'] ?? 0);

        $occupationType =
            trim($_POST['occupation_type'] ?? '');

        $arrivalAt =
            trim($_POST['arrival_at'] ?? '');

        $duration =
            (int) ($_POST['duration'] ?? 0);

        $price =
            (float) ($_POST['price'] ?? 0);

        $paymentMethod =
            trim($_POST['payment_method'] ?? '');


        /*
        |--------------------------------------------------------------------------
        | VALIDATION
        |--------------------------------------------------------------------------
        */

        if (
            $clientId <= 0
            ||
            $roomId <= 0
            ||
            $occupationType === ''
            ||
            $arrivalAt === ''
            ||
            $duration <= 0
            ||
            $price <= 0
            ||
            $paymentMethod === ''
        ) {

            $error =
                "Veuillez remplir tous les champs obligatoires.";

        } else {


            try {


                $pdo->beginTransaction();


                /*
                |--------------------------------------------------------------------------
                | VÉRIFICATION CLIENT
                |--------------------------------------------------------------------------
                */

                $sql = "
                    SELECT id

                    FROM clients

                    WHERE id = :client_id

                    AND hotel_id = :hotel_id

                    LIMIT 1
                ";


                $stmt =
                    $pdo->prepare($sql);


                $stmt->execute([

                    ':client_id' =>
                        $clientId,

                    ':hotel_id' =>
                        $hotelId

                ]);


                $client =
                    $stmt->fetch();


                if (!$client) {

                    throw new Exception(
                        "Le client sélectionné n'est pas valide."
                    );

                }


                /*
                |--------------------------------------------------------------------------
                | VÉRIFICATION CHAMBRE
                |--------------------------------------------------------------------------
                */

                $sql = "
                    SELECT

                        id,

                        status

                    FROM rooms

                    WHERE id = :room_id

                    AND hotel_id = :hotel_id

                    LIMIT 1

                    FOR UPDATE
                ";


                $stmt =
                    $pdo->prepare($sql);


                $stmt->execute([

                    ':room_id' =>
                        $roomId,

                    ':hotel_id' =>
                        $hotelId

                ]);


                $room =
                    $stmt->fetch();


                if (!$room) {

                    throw new Exception(
                        "La chambre sélectionnée n'existe pas."
                    );

                }


                if ($room['status'] !== 'available') {

                    throw new Exception(
                        "Cette chambre n'est plus disponible."
                    );

                }


                /*
                |--------------------------------------------------------------------------
                | CALCUL DÉPART PRÉVU
                |--------------------------------------------------------------------------
                */

                $arrivalDate =
                    new DateTime($arrivalAt);


                $expectedDeparture =
                    clone $arrivalDate;


                $expectedDeparture->modify(
                    '+' . $duration . ' day'
                );


                $expectedDepartureAt =
                    $expectedDeparture->format(
                        'Y-m-d H:i:s'
                    );


                /*
                |--------------------------------------------------------------------------
                | CRÉATION DU SÉJOUR
                |--------------------------------------------------------------------------
                */

                $sql = "
                    INSERT INTO stays (

                        hotel_id,

                        client_id,

                        room_id,

                        user_id,

                        occupation_type,

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

                        :occupation_type,

                        :arrival_at,

                        :duration,

                        :expected_departure_at,

                        NULL,

                        :price,

                        :payment_method,

                        'active',

                        NOW()

                    )
                ";


                $stmt =
                    $pdo->prepare($sql);


                $stmt->execute([

                    ':hotel_id' =>
                        $hotelId,

                    ':client_id' =>
                        $clientId,

                    ':room_id' =>
                        $roomId,

                    ':user_id' =>
                        (int) ($_SESSION['user_id'] ?? 0),

                    ':occupation_type' =>
                        $occupationType,

                    ':arrival_at' =>
                        $arrivalAt,

                    ':duration' =>
                        $duration,

                    ':expected_departure_at' =>
                        $expectedDepartureAt,

                    ':price' =>
                        $price,

                    ':payment_method' =>
                        $paymentMethod

                ]);


                /*
                |--------------------------------------------------------------------------
                | CHAMBRE OCCUPÉE
                |--------------------------------------------------------------------------
                */

                $sql = "
                    UPDATE rooms

                    SET status = 'occupied'

                    WHERE id = :room_id

                    AND hotel_id = :hotel_id
                ";


                $stmt =
                    $pdo->prepare($sql);


                $stmt->execute([

                    ':room_id' =>
                        $roomId,

                    ':hotel_id' =>
                        $hotelId

                ]);


                $pdo->commit();


                /*
                |--------------------------------------------------------------------------
                | REDIRECTION
                |--------------------------------------------------------------------------
                */

                header(
                    'Location: /flux_hotel/reception/stays.php?success=1'
                );

                exit;


            } catch (Exception $e) {


                if ($pdo->inTransaction()) {

                    $pdo->rollBack();

                }


                $error =
                    $e->getMessage();


            } catch (PDOException $e) {


                if ($pdo->inTransaction()) {

                    $pdo->rollBack();

                }


                $error =
                    "Impossible d'enregistrer le séjour.";

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
    isset($_GET['success'])
    &&
    $_GET['success'] === '1'
) {

    $success =
        "Le séjour a été enregistré avec succès.";

}


if (
    isset($_GET['client_added'])
    &&
    $_GET['client_added'] === '1'
) {

    $success =
        "Client ajouté avec succès. "
        . "Vous pouvez maintenant enregistrer son séjour.";

}


/*
|--------------------------------------------------------------------------
| CHAMBRES DISPONIBLES
|--------------------------------------------------------------------------
*/

try {


    $sql = "
        SELECT

            id,

            room_number,

            room_type,

            price

        FROM rooms

        WHERE hotel_id = :hotel_id

        AND status = 'available'

        ORDER BY room_number ASC
    ";


    $stmt =
        $pdo->prepare($sql);


    $stmt->execute([

        ':hotel_id' =>
            $hotelId

    ]);


    $rooms =
        $stmt->fetchAll();


} catch (PDOException $e) {


    $error =
        "Impossible de récupérer les chambres.";

}


/*
|--------------------------------------------------------------------------
| DATE ET HEURE PAR DÉFAUT
|--------------------------------------------------------------------------
*/

$defaultArrival =
    date('Y-m-d\TH:i');

?>


<!DOCTYPE html>

<html lang="fr">


<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Séjours - Hotel Flow</title>
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

    max-width: 950px;

    margin: 30px auto;

    padding: 0 20px;

}


.page-title {

    margin-bottom: 25px;

}


.page-title h2 {

    color: #1e3a5f;

    margin-bottom: 8px;

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
   CARTE
   ===================================================== */

.form-card {

    background: white;

    padding: 30px;

    border-radius: 12px;

    box-shadow:
        0 3px 12px
        rgba(0, 0, 0, 0.08);

    margin-bottom: 25px;

}


.form-card h3 {

    color: #1e3a5f;

    margin-top: 0;

    margin-bottom: 25px;

}


/* =====================================================
   FORMULAIRE
   ===================================================== */

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

}


/* =====================================================
   RECHERCHE CLIENT
   ===================================================== */

.client-search {

    position: relative;

}


.client-search input {

    padding-right: 45px;

}


.search-icon {

    position: absolute;

    right: 14px;

    top: 12px;

    font-size: 18px;

    color: #777;

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

    z-index: 100;

    display: none;

    overflow: hidden;

}


.search-result {

    padding: 13px 15px;

    border-bottom: 1px solid #eee;

    cursor: pointer;

}


.search-result:last-child {

    border-bottom: none;

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

    color: #666;

    text-align: center;

}


.client-selected {

    margin-top: 10px;

    padding: 10px 12px;

    background: #d1e7dd;

    color: #0f5132;

    border-radius: 7px;

    display: none;

}


/* =====================================================
   BOUTONS
   ===================================================== */

.buttons {

    display: flex;

    gap: 10px;

    flex-wrap: wrap;

    margin-top: 5px;

}


.btn {

    display: inline-block;

    padding: 12px 18px;

    border: none;

    border-radius: 7px;

    text-decoration: none;

    cursor: pointer;

    font-size: 15px;

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


.btn-secondary {

    background: #6c757d;

    color: white;

}


.btn-secondary:hover {

    background: #565e64;

}


/* =====================================================
   NOUVEAU CLIENT
   ===================================================== */

.client-box {

    background: #f8fafc;

    border: 1px solid #dce3ea;

}


.client-info {

    background: #eef5fb;

    padding: 12px;

    border-radius: 7px;

    color: #1e3a5f;

    margin-bottom: 20px;

}


/* =====================================================
   MESSAGE CHAMBRE
   ===================================================== */

.no-room {

    background: #fff3cd;

    color: #664d03;

    padding: 15px;

    border-radius: 8px;

    margin-bottom: 20px;

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

@media (max-width: 700px) {


    .container {

        margin-top: 20px;

        padding: 0 15px;

    }


    .form-card {

        padding: 20px;

    }


    .form-grid {

        grid-template-columns: 1fr;

    }


    .form-group.full {

        grid-column: auto;

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


<!-- =====================================================
     NAVIGATION
     ===================================================== -->

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
            class="active"
            href="/flux_hotel/reception/stays.php"
        >
            📝 Séjours
        </a>
        
        <a href="/flux_hotel/reception/reservations.php">
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


<!-- =====================================================
     CONTENU
     ===================================================== -->

<main class="container">


    <div class="page-title">

        <h2>
            Enregistrement d'un séjour
        </h2>

        <p>
            Enregistrez l'arrivée d'un client dans une chambre.
        </p>

    </div>


    <?php if ($success !== ''): ?>

        <div class="success">

            ✅
            <?= htmlspecialchars($success) ?>

        </div>

    <?php endif; ?>


    <?php if ($error !== ''): ?>

        <div class="error">

            ⚠️
            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>


    <!-- =================================================
         FORMULAIRE NOUVEAU CLIENT
         ================================================= -->

    <?php if ($showClientForm): ?>


        <div class="form-card client-box">


            <h3>
                👤 Ajouter un nouveau client
            </h3>


            <div class="client-info">

                Ce client sera automatiquement enregistré
                dans la base de données de cet hôtel.

            </div>


            <form
                method="POST"
                action=""
            >


                <input
                    type="hidden"
                    name="action"
                    value="add_client"
                >


                <div class="form-grid">


                    <div class="form-group">

                        <label for="first_name">

                            Prénom *

                        </label>


                        <input
                            type="text"
                            name="first_name"
                            id="first_name"
                            placeholder="Ex : Jean"
                            required
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
                            placeholder="Ex : Dupont"
                            required
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
                        >

                    </div>


                </div>


                <div class="buttons">


                    <button
                        type="submit"
                        class="btn btn-success"
                    >

                        💾 Ajouter le client

                    </button>


                    <a
                        href="/flux_hotel/reception/stays.php"
                        class="btn btn-secondary"
                    >

                        Annuler

                    </a>


                </div>


            </form>


        </div>


    <?php else: ?>


        <!-- =================================================
             FORMULAIRE SÉJOUR
             ================================================= -->

        <div class="form-card">


            <h3>
                📝 Nouveau séjour
            </h3>


            <?php if (count($rooms) === 0): ?>

                <div class="no-room">

                    ⚠️ Aucune chambre disponible actuellement.

                </div>

            <?php endif; ?>


            <form
                method="POST"
                action=""
                id="stayForm"
            >


                <input
                    type="hidden"
                    name="action"
                    value="add_stay"
                >


                <!-- CLIENT -->

                <div class="form-group full">

                    <label for="client_search">

                        👤 Client *

                    </label>


                    <div class="client-search">


                        <input
                            type="text"
                            id="client_search"
                            placeholder="Tapez le nom, prénom, CNI ou téléphone..."
                            autocomplete="off"
                        >


                        <span class="search-icon">

                            🔍

                        </span>


                        <div
                            id="search_results"
                            class="search-results"
                        ></div>


                    </div>


                    <!-- ID CLIENT -->

                    <input
                        type="hidden"
                        name="client_id"
                        id="client_id"
                        value=""
                    >


                    <!-- CLIENT SÉLECTIONNÉ -->

                    <div
                        id="client_selected"
                        class="client-selected"
                    ></div>


                </div>


                <!-- NOUVEAU CLIENT -->

                <div class="form-group full">


                    <a
                        href="/flux_hotel/reception/stays.php?new_client=1"
                        class="btn btn-primary"
                    >

                        ➕ Ajouter un nouveau client

                    </a>


                </div>


                <div class="form-grid">


                    <!-- CHAMBRE -->

                    <div class="form-group">


                        <label for="room_id">

                            Chambre *

                        </label>


                        <select
                            name="room_id"
                            id="room_id"
                            required
                        >


                            <option value="">

                                -- Sélectionner une chambre --

                            </option>


                            <?php foreach (
                                $rooms
                                as $room
                            ): ?>


                                <option
                                    value="<?= (int) $room['id'] ?>"
                                    data-price="<?= htmlspecialchars(
                                        $room['price']
                                    ) ?>"
                                >


                                    Chambre
                                    <?= htmlspecialchars(
                                        $room['room_number']
                                    ) ?>

                                    -

                                    <?= htmlspecialchars(
                                        $room['room_type']
                                    ) ?>

                                    -

                                    <?= number_format(
                                        (float) $room['price'],
                                        0,
                                        ',',
                                        ' '
                                    ) ?>

                                    FCFA


                                </option>


                            <?php endforeach; ?>


                        </select>


                    </div>


                    <!-- TYPE DE SÉJOUR -->

                    <div class="form-group">


                        <label for="occupation_type">

                            Type de séjour *

                        </label>


                        <select
                            name="occupation_type"
                            id="occupation_type"
                            required
                        >


                            <option value="">

                                -- Sélectionner --

                            </option>


                            <option value="nuitée">

                                Nuitée

                            </option>


                            <option value="sieste">

                                Sieste

                            </option>


                            <option value="journée">

                                Journée

                            </option>


                            <option value="long_sejour">

                                Long séjour

                            </option>


                            <option value="autre">

                                Autre

                            </option>


                        </select>


                    </div>


                    <!-- ARRIVÉE -->

                    <div class="form-group">


                        <label for="arrival_at">

                            Date et heure d'arrivée *

                        </label>


                        <input
                            type="datetime-local"
                            name="arrival_at"
                            id="arrival_at"
                            value="<?= $defaultArrival ?>"
                            required
                        >


                    </div>


                    <!-- DURÉE -->

                    <div class="form-group">


                        <label for="duration">

                            Durée *

                        </label>


                        <input
                            type="number"
                            name="duration"
                            id="duration"
                            min="1"
                            value="1"
                            required
                        >


                        <small>

                            Durée en jours.

                        </small>


                    </div>


                    <!-- PRIX -->

                    <div class="form-group">


                        <label for="price">

                            Prix total (FCFA) *

                        </label>


                        <input
                            type="number"
                            name="price"
                            id="price"
                            min="0"
                            step="1"
                            placeholder="Ex : 25000"
                            required
                        >


                    </div>


                    <!-- PAIEMENT -->

                    <div class="form-group">


                        <label for="payment_method">

                            Mode de paiement *

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


                <button
                    type="submit"
                    class="btn btn-success"
                    id="submitStay"
                >

                    ✅ Enregistrer le séjour

                </button>


            </form>


        </div>


    <?php endif; ?>


</main>


<footer class="footer">

    Hotel Flow © <?= date('Y') ?>

</footer>


<!-- =====================================================
     JAVASCRIPT RECHERCHE CLIENT
     ===================================================== -->

<script>


const searchInput =
    document.getElementById('client_search');


const searchResults =
    document.getElementById('search_results');


const clientIdInput =
    document.getElementById('client_id');


const clientSelected =
    document.getElementById('client_selected');


/*
|--------------------------------------------------------------------------
| TIMER DE RECHERCHE
|--------------------------------------------------------------------------
*/

let searchTimer = null;


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
            |--------------------------------------------------------------------------
            | RÉINITIALISER LE CLIENT SÉLECTIONNÉ
            |--------------------------------------------------------------------------
            */

            clientIdInput.value = '';

            clientSelected.style.display =
                'none';


            /*
            |--------------------------------------------------------------------------
            | EFFACER LES RÉSULTATS
            |--------------------------------------------------------------------------
            */

            searchResults.innerHTML =
                '';

            searchResults.style.display =
                'none';


            /*
            |--------------------------------------------------------------------------
            | MINIMUM 2 CARACTÈRES
            |--------------------------------------------------------------------------
            */

            if (query.length < 2) {

                return;

            }


            /*
            |--------------------------------------------------------------------------
            | ATTENDRE UN PEU AVANT LA REQUÊTE
            |--------------------------------------------------------------------------
            */

            clearTimeout(searchTimer);


            searchTimer =
                setTimeout(
                    function () {

                        searchClients(query);

                    },
                    300
                );

        }
    );


    /*
    |--------------------------------------------------------------------------
    | FERMER LES RÉSULTATS EN CLIQUANT AILLEURS
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

}


/*
|--------------------------------------------------------------------------
| FONCTION RECHERCHE
|--------------------------------------------------------------------------
*/

async function searchClients(query) {


    try {


        searchResults.innerHTML =
            '<div class="no-result">Recherche...</div>';


        searchResults.style.display =
            'block';


        const response =
            await fetch(
                '/flux_hotel/reception/search_clients.php?q='
                +
                encodeURIComponent(query)
            );


        const data =
            await response.json();


        if (
            !data.success
        ) {


            searchResults.innerHTML =
                '<div class="no-result">Erreur de recherche.</div>';


            return;

        }


        if (
            data.clients.length === 0
        ) {


            searchResults.innerHTML =
                '<div class="no-result">'
                +
                'Aucun client trouvé.'
                +
                '</div>';


            return;

        }


        /*
        |--------------------------------------------------------------------------
        | AFFICHER LES CLIENTS
        |--------------------------------------------------------------------------
        */

        searchResults.innerHTML =
            '';


        data.clients.forEach(
            function (client) {


                const item =
                    document.createElement(
                        'div'
                    );


                item.className =
                    'search-result';


                /*
                |--------------------------------------------------------------------------
                | NOM
                |--------------------------------------------------------------------------
                */

                const name =
                    document.createElement(
                        'div'
                    );


                name.className =
                    'search-result-name';


                name.textContent =
                    client.last_name
                    +
                    ' '
                    +
                    client.first_name;


                /*
                |--------------------------------------------------------------------------
                | INFORMATIONS
                |--------------------------------------------------------------------------
                */

                const info =
                    document.createElement(
                        'div'
                    );


                info.className =
                    'search-result-info';


                let details = [];


                if (
                    client.cni_number
                ) {

                    details.push(
                        'CNI : '
                        +
                        client.cni_number
                    );

                }


                if (
                    client.phone
                ) {

                    details.push(
                        'Tél : '
                        +
                        client.phone
                    );

                }


                info.textContent =
                    details.join(' • ');


                item.appendChild(name);

                item.appendChild(info);


                /*
                |--------------------------------------------------------------------------
                | SÉLECTION DU CLIENT
                |--------------------------------------------------------------------------
                */

                item.addEventListener(
                    'click',
                    function () {


                        clientIdInput.value =
                            client.id;


                        searchInput.value =
                            client.last_name
                            +
                            ' '
                            +
                            client.first_name;


                        clientSelected.innerHTML =
                            '✅ Client sélectionné : <strong>'
                            +
                            escapeHtml(
                                client.last_name
                            )
                            +
                            ' '
                            +
                            escapeHtml(
                                client.first_name
                            )
                            +
                            '</strong>';


                        clientSelected.style.display =
                            'block';


                        searchResults.style.display =
                            'none';

                    }
                );


                searchResults.appendChild(
                    item
                );

            }
        );


        searchResults.style.display =
            'block';


    } catch (error) {


        searchResults.innerHTML =
            '<div class="no-result">'
            +
            'Impossible de contacter le serveur.'
            +
            '</div>';


    }

}


/*
|--------------------------------------------------------------------------
| PROTECTION AFFICHAGE HTML
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
| VÉRIFICATION AVANT ENVOI
|--------------------------------------------------------------------------
*/

const stayForm =
    document.getElementById('stayForm');


if (stayForm) {


    stayForm.addEventListener(
        'submit',
        function (event) {


            if (
                clientIdInput.value === ''
            ) {


                event.preventDefault();


                alert(
                    'Veuillez rechercher et sélectionner un client.'
                );


                searchInput.focus();

            }

        }
    );

}


</script>
<script>

if ('serviceWorker' in navigator) {

    window.addEventListener('load', function () {

        navigator.serviceWorker
            .register('/flux_hotel/service-worker.js')
            .then(function (registration) {

                console.log(
                    'Hotel Flow PWA activée.',
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
