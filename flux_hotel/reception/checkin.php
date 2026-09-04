<?php error_reporting(E_ALL); ini_set('display_errors', '1'); require_once __DIR__ . '/../config/database.php'; require_once __DIR__ . '/../includes/auth.php'; /* |-------------------------------------------------------------------------- | Vérification de connexion |-------------------------------------------------------------------------- */ if (!isLoggedIn()) { header('Location: /flux_hotel/auth/login.php'); exit; } /* |-------------------------------------------------------------------------- | Vérification du rôle |-------------------------------------------------------------------------- */ if ((int) ($_SESSION['role_id'] ?? 0) !== 2) { header('Location: /flux_hotel/admin/dashboard.php'); exit; } /* |-------------------------------------------------------------------------- | Informations de session |-------------------------------------------------------------------------- */ $hotelId = (int) $_SESSION['hotel_id']; $userId = (int) $_SESSION['user_id']; $firstName = $_SESSION['first_name'] ?? ''; $lastName = $_SESSION['last_name'] ?? ''; /* |-------------------------------------------------------------------------- | Variables |-------------------------------------------------------------------------- */ $error = ''; $success = ''; $rooms = []; /* |-------------------------------------------------------------------------- | Récupérer les chambres disponibles |-------------------------------------------------------------------------- */ try { $sql = " SELECT id, room_number, room_type, price FROM rooms WHERE hotel_id = :hotel_id AND status = 'available' ORDER BY room_number ASC "; $stmt = $pdo->prepare($sql); $stmt->execute([ ':hotel_id' => $hotelId ]); $rooms = $stmt->fetchAll(); } catch (PDOException $e) { $error = "Impossible de récupérer les chambres disponibles."; } /* |-------------------------------------------------------------------------- | Traitement du formulaire |-------------------------------------------------------------------------- */ if ($_SERVER['REQUEST_METHOD'] === 'POST') { /* |-------------------------------------------------------------------------- | Récupération des données |-------------------------------------------------------------------------- */ $clientFirstName = trim($_POST['first_name'] ?? ''); $clientLastName = trim($_POST['last_name'] ?? ''); $cniNumber = trim($_POST['cni_number'] ?? ''); $phone = trim($_POST['phone'] ?? ''); $roomId = (int) ($_POST['room_id'] ?? 0); $occupationType = trim($_POST['occupation_type'] ?? ''); $arrivalAt = trim($_POST['arrival_at'] ?? ''); $duration = (int) ($_POST['duration'] ?? 0); $price = (float) ($_POST['price'] ?? 0); $paymentMethod = trim($_POST['payment_method'] ?? ''); /* |-------------------------------------------------------------------------- | Validation |-------------------------------------------------------------------------- */ if ( $clientFirstName === '' || $clientLastName === '' || $roomId <= 0 || $occupationType === '' || $arrivalAt === '' || $duration <= 0 || $price <= 0 || $paymentMethod === '' ) { $error = "Veuillez remplir tous les champs obligatoires."; } else { /* |-------------------------------------------------------------------------- | Conversion de la date |-------------------------------------------------------------------------- */ $arrivalTimestamp = strtotime($arrivalAt); if ($arrivalTimestamp === false) { $error = "La date et l'heure d'arrivée sont invalides."; } else { /* |-------------------------------------------------------------------------- | Calcul du départ prévu |-------------------------------------------------------------------------- | | Ici la durée est considérée en nombre de nuits. | */ $expectedDepartureTimestamp = strtotime("+{$duration} day", $arrivalTimestamp); $expectedDepartureAt = date('Y-m-d H:i:s', $expectedDepartureTimestamp); $arrivalAt = date('Y-m-d H:i:s', $arrivalTimestamp); try { /* |-------------------------------------------------------------------------- | DÉBUT TRANSACTION |-------------------------------------------------------------------------- */ $pdo->beginTransaction(); /* |-------------------------------------------------------------------------- | Vérifier que la chambre appartient à l'hôtel |-------------------------------------------------------------------------- */ $sql = " SELECT id, room_number, price, status FROM rooms WHERE id = :room_id AND hotel_id = :hotel_id FOR UPDATE "; $stmt = $pdo->prepare($sql); $stmt->execute([ ':room_id' => $roomId, ':hotel_id' => $hotelId ]); $room = $stmt->fetch(); if (!$room) { throw new Exception( "La chambre sélectionnée n'existe pas." ); } /* |-------------------------------------------------------------------------- | Vérifier que la chambre est disponible |-------------------------------------------------------------------------- */ if ($room['status'] !== 'available') { throw new Exception( "Cette chambre n'est plus disponible." ); } /* |-------------------------------------------------------------------------- | Vérifier le prix |-------------------------------------------------------------------------- */ if ($price <= 0) { throw new Exception( "Le prix doit être supérieur à zéro." ); } /* |-------------------------------------------------------------------------- | Créer le client |-------------------------------------------------------------------------- */ $clientId = null; /* | Si une CNI est fournie, chercher un client | existant dans le même hôtel. */ if ($cniNumber !== '') { $sql = " SELECT id FROM clients WHERE hotel_id = :hotel_id AND cni_number = :cni_number LIMIT 1 "; $stmt = $pdo->prepare($sql); $stmt->execute([ ':hotel_id' => $hotelId, ':cni_number' => $cniNumber ]); $existingClient = $stmt->fetch(); if ($existingClient) { $clientId = (int) $existingClient['id']; } } /* |-------------------------------------------------------------------------- | Si le client n'existe pas, le créer |-------------------------------------------------------------------------- */ if ($clientId === null) { $sql = " INSERT INTO clients ( hotel_id, first_name, last_name, cni_number, phone ) VALUES ( :hotel_id, :first_name, :last_name, :cni_number, :phone ) "; $stmt = $pdo->prepare($sql); $stmt->execute([ ':hotel_id' => $hotelId, ':first_name' => $clientFirstName, ':last_name' => $clientLastName, ':cni_number' => $cniNumber !== '' ? $cniNumber : null, ':phone' => $phone !== '' ? $phone : null ]); $clientId = (int) $pdo->lastInsertId(); } /* |-------------------------------------------------------------------------- | Créer le séjour |-------------------------------------------------------------------------- */ $sql = " INSERT INTO stays ( hotel_id, client_id, room_id, user_id, occupation_type, arrival_at, duration, expected_departure_at, price, payment_method, status ) VALUES ( :hotel_id, :client_id, :room_id, :user_id, :occupation_type, :arrival_at, :duration, :expected_departure_at, :price, :payment_method, 'active' ) "; $stmt = $pdo->prepare($sql); $stmt->execute([ ':hotel_id' => $hotelId, ':client_id' => $clientId, ':room_id' => $roomId, ':user_id' => $userId, ':occupation_type' => $occupationType, ':arrival_at' => $arrivalAt, ':duration' => $duration, ':expected_departure_at' => $expectedDepartureAt, ':price' => $price, ':payment_method' => $paymentMethod ]); /* |-------------------------------------------------------------------------- | ID du séjour |-------------------------------------------------------------------------- */ $stayId = (int) $pdo->lastInsertId(); /* |-------------------------------------------------------------------------- | Enregistrer la vente |-------------------------------------------------------------------------- */ $description = "Séjour chambre " . $room['room_number'] . " - " . $clientFirstName . " " . $clientLastName; $sql = " INSERT INTO sales ( hotel_id, user_id, description, amount, payment_method, sold_at ) VALUES ( :hotel_id, :user_id, :description, :amount, :payment_method, :sold_at ) "; $stmt = $pdo->prepare($sql); $stmt->execute([ ':hotel_id' => $hotelId, ':user_id' => $userId, ':description' => $description, ':amount' => $price, ':payment_method' => $paymentMethod, ':sold_at' => $arrivalAt ]); /* |-------------------------------------------------------------------------- | Passer la chambre à OCCUPIED |-------------------------------------------------------------------------- */ $sql = " UPDATE rooms SET status = 'occupied' WHERE id = :room_id AND hotel_id = :hotel_id AND status = 'available' "; $stmt = $pdo->prepare($sql); $stmt->execute([ ':room_id' => $roomId, ':hotel_id' => $hotelId ]); /* |-------------------------------------------------------------------------- | Vérification de la mise à jour |-------------------------------------------------------------------------- */ if ($stmt->rowCount() !== 1) { throw new Exception( "Impossible de mettre la chambre en statut occupé." ); } /* |-------------------------------------------------------------------------- | Valider la transaction |-------------------------------------------------------------------------- */ $pdo->commit(); /* |-------------------------------------------------------------------------- | Message de succès |-------------------------------------------------------------------------- */ $success = "Enregistrement effectué avec succès. " . "La chambre " . htmlspecialchars($room['room_number']) . " est maintenant occupée."; /* |-------------------------------------------------------------------------- | Vider les champs |-------------------------------------------------------------------------- */ $_POST = []; /* |-------------------------------------------------------------------------- | Recharger les chambres disponibles |-------------------------------------------------------------------------- */ $sql = " SELECT id, room_number, room_type, price FROM rooms WHERE hotel_id = :hotel_id AND status = 'available' ORDER BY room_number ASC "; $stmt = $pdo->prepare($sql); $stmt->execute([ ':hotel_id' => $hotelId ]); $rooms = $stmt->fetchAll(); } catch (Exception $e) { /* |-------------------------------------------------------------------------- | Annuler la transaction en cas d'erreur |-------------------------------------------------------------------------- */ if ($pdo->inTransaction()) { $pdo->rollBack(); } $error = $e->getMessage(); } } } } ?> <!DOCTYPE html> <html lang="fr"> <head>
<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Enregistrement - Hotel Flow</title>
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

        max-width: 1100px;

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

        max-width: 1100px;

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

        max-width: 900px;

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
       FORMULAIRE
       ===================================================== */

    .form-card {

        background: white;

        padding: 30px;

        border-radius: 12px;

        box-shadow: 0 3px 12px rgba(0, 0, 0, 0.08);

    }

    .form-section {

        margin-bottom: 30px;

    }

    .form-section h3 {

        color: #1e3a5f;

        border-bottom: 1px solid #ddd;

        padding-bottom: 10px;

        margin-bottom: 20px;

    }

    .form-grid {

        display: grid;

        grid-template-columns: repeat(2, 1fr);

        gap: 18px;

    }

    .form-group {

        display: flex;

        flex-direction: column;

    }

    .form-group.full {

        grid-column: 1 / -1;

    }

    label {

        margin-bottom: 7px;

        font-weight: bold;

        font-size: 14px;

    }

    .required {

        color: #c62828;

    }

    input,
    select {

        width: 100%;

        padding: 12px;

        border: 1px solid #ccc;

        border-radius: 6px;

        font-size: 15px;

        background: white;

    }

    input:focus,
    select:focus {

        outline: none;

        border-color: #1e3a5f;

        box-shadow: 0 0 0 2px rgba(30, 58, 95, 0.1);

    }

    .help {

        margin-top: 5px;

        color: #777;

        font-size: 12px;

    }


    /* =====================================================
       BOUTON
       ===================================================== */

    .submit-button {

        width: 100%;

        padding: 14px;

        border: none;

        border-radius: 7px;

        background: #198754;

        color: white;

        font-size: 16px;

        font-weight: bold;

        cursor: pointer;

    }

    .submit-button:hover {

        background: #146c43;

    }


    /* =====================================================
       CHAMBRE SÉLECTIONNÉE
       ===================================================== */

    .room-info {

        margin-top: 8px;

        padding: 10px;

        background: #eaf0f7;

        color: #1e3a5f;

        border-radius: 6px;

        font-size: 13px;

        display: none;

    }


    /* =====================================================
       RESPONSIVE
       ===================================================== */

    @media (max-width: 650px) {

        .container {

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

        .nav .logout {

            margin-left: 0;

        }

    }

</style>

</head> <body> <header class="header">
<div class="header-content">

    <h1>
        🏨 Hotel Flow
    </h1>

    <p>
        Espace Réception
    </p>

</div>

</header> <nav class="nav">
<div class="nav-container">


    <a href="/flux_hotel/reception/dashboard.php">
        🏠 Tableau de bord
    </a>


    <a href="/flux_hotel/reception/rooms.php">
        🛏️ Chambres
    </a>


    <a
        class="active"
        href="/flux_hotel/reception/checkin.php"
    >
        📝 Enregistrement
    </a>


    <a href="/flux_hotel/reception/history.php">
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

</nav> <main class="container">
<div class="page-title">

    <h2>
        Nouvel enregistrement
    </h2>

    <p>
        Enregistrez l'arrivée d'un nouveau client.
    </p>

</div>


<?php if ($success !== ''): ?>

    <div class="success">

        <?= $success ?>

    </div>

<?php endif; ?>


<?php if ($error !== ''): ?>

    <div class="error">

        <?= htmlspecialchars($error) ?>

    </div>

<?php endif; ?>


<?php if (count($rooms) === 0): ?>

    <div class="error">

        <strong>
            Aucune chambre disponible.
        </strong>

        <br><br>

        Toutes les chambres de votre hôtel sont
        actuellement occupées ou réservées.

    </div>


<?php else: ?>


    <form
        method="POST"
        action=""
        class="form-card"
    >


        <!-- =================================================
             INFORMATIONS CLIENT
             ================================================= -->

        <div class="form-section">

            <h3>
                👤 Informations du client
            </h3>


            <div class="form-grid">


                <div class="form-group">

                    <label for="first_name">

                        Prénom

                        <span class="required">*</span>

                    </label>

                    <input
                        type="text"
                        id="first_name"
                        name="first_name"
                        value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>"
                        required
                    >

                </div>


                <div class="form-group">

                    <label for="last_name">

                        Nom

                        <span class="required">*</span>

                    </label>

                    <input
                        type="text"
                        id="last_name"
                        name="last_name"
                        value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>"
                        required
                    >

                </div>


                <div class="form-group">

                    <label for="cni_number">

                        Numéro CNI

                    </label>

                    <input
                        type="text"
                        id="cni_number"
                        name="cni_number"
                        value="<?= htmlspecialchars($_POST['cni_number'] ?? '') ?>"
                    >

                    <span class="help">
                        Facultatif
                    </span>

                </div>


                <div class="form-group">

                    <label for="phone">

                        Téléphone

                    </label>

                    <input
                        type="tel"
                        id="phone"
                        name="phone"
                        value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                    >

                    <span class="help">
                        Facultatif
                    </span>

                </div>


            </div>

        </div>


        <!-- =================================================
             INFORMATIONS SÉJOUR
             ================================================= -->

        <div class="form-section">

            <h3>
                🛏️ Informations du séjour
            </h3>


            <div class="form-grid">


                <div class="form-group full">

                    <label for="room_id">

                        Chambre

                        <span class="required">*</span>

                    </label>


                    <select
                        id="room_id"
                        name="room_id"
                        required
                    >

                        <option value="">
                            -- Sélectionner une chambre --
                        </option>


                        <?php foreach ($rooms as $room): ?>

                            <option
                                value="<?= (int) $room['id'] ?>"
                                data-price="<?= htmlspecialchars($room['price']) ?>"
                                <?= (
                                    (int) ($_POST['room_id'] ?? 0)
                                    === (int) $room['id']
                                )
                                    ? 'selected'
                                    : ''
                                ?>
                            >

                                Chambre
                                <?= htmlspecialchars($room['room_number']) ?>

                                -
                                <?= htmlspecialchars($room['room_type']) ?>

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


                    <div
                        id="roomInfo"
                        class="room-info"
                    ></div>


                </div>


                <div class="form-group">

                    <label for="occupation_type">

                        Type d'occupation

                        <span class="required">*</span>

                    </label>


                    <select
                        id="occupation_type"
                        name="occupation_type"
                        required
                    >

                        <option value="">
                            -- Sélectionner --
                        </option>

                        <option
                            value="single"
                            <?= ($_POST['occupation_type'] ?? '') === 'single'
                                ? 'selected'
                                : ''
                            ?>
                        >
                            Simple
                        </option>

                        <option
                            value="double"
                            <?= ($_POST['occupation_type'] ?? '') === 'double'
                                ? 'selected'
                                : ''
                            ?>
                        >
                            Double
                        </option>

                        <option
                            value="family"
                            <?= ($_POST['occupation_type'] ?? '') === 'family'
                                ? 'selected'
                                : ''
                            ?>
                        >
                            Familiale
                        </option>

                        <option
                            value="other"
                            <?= ($_POST['occupation_type'] ?? '') === 'other'
                                ? 'selected'
                                : ''
                            ?>
                        >
                            Autre
                        </option>

                    </select>

                </div>


                <div class="form-group">

                    <label for="duration">

                        Durée (nuits)

                        <span class="required">*</span>

                    </label>


                    <input
                        type="number"
                        id="duration"
                        name="duration"
                        min="1"
                        value="<?= htmlspecialchars($_POST['duration'] ?? '1') ?>"
                        required
                    >

                </div>


                <div class="form-group">

                    <label for="arrival_at">

                        Date et heure d'arrivée

                        <span class="required">*</span>

                    </label>


                    <input
                        type="datetime-local"
                        id="arrival_at"
                        name="arrival_at"
                        value="<?= htmlspecialchars($_POST['arrival_at'] ?? date('Y-m-d\TH:i')) ?>"
                        required
                    >

                </div>


                <div class="form-group">

                    <label for="price">

                        Prix du séjour (FCFA)

                        <span class="required">*</span>

                    </label>


                    <input
                        type="number"
                        id="price"
                        name="price"
                        min="1"
                        step="1"
                        value="<?= htmlspecialchars($_POST['price'] ?? '') ?>"
                        required
                    >

                    <span class="help">
                        Le prix de la chambre est proposé automatiquement.
                    </span>

                </div>


                <div class="form-group full">

                    <label for="payment_method">

                        Mode de paiement

                        <span class="required">*</span>

                    </label>


                    <select
                        id="payment_method"
                        name="payment_method"
                        required
                    >

                        <option value="">
                            -- Sélectionner --
                        </option>

                        <option
                            value="cash"
                            <?= ($_POST['payment_method'] ?? '') === 'cash'
                                ? 'selected'
                                : ''
                            ?>
                        >
                            Espèces
                        </option>

                        <option
                            value="mobile_money"
                            <?= ($_POST['payment_method'] ?? '') === 'mobile_money'
                                ? 'selected'
                                : ''
                            ?>
                        >
                            Mobile Money
                        </option>

                        <option
                            value="card"
                            <?= ($_POST['payment_method'] ?? '') === 'card'
                                ? 'selected'
                                : ''
                            ?>
                        >
                            Carte bancaire
                        </option>

                        <option
                            value="other"
                            <?= ($_POST['payment_method'] ?? '') === 'other'
                                ? 'selected'
                                : ''
                            ?>
                        >
                            Autre
                        </option>

                    </select>

                </div>


            </div>

        </div>


        <!-- =================================================
             BOUTON
             ================================================= -->

        <button
            type="submit"
            class="submit-button"
        >

            ✅ Enregistrer le séjour

        </button>


    </form>


<?php endif; ?>

</main> <footer style=" text-align:center; color:#777; padding:40px 20px; font-size:13px; ">
Hotel Flow © <?= date('Y') ?>

</footer> <script> /* |-------------------------------------------------------------------------- | Sélection de la chambre |-------------------------------------------------------------------------- | | Lorsque la réception sélectionne une chambre, | son prix est automatiquement proposé. | */ const roomSelect = document.getElementById('room_id'); const priceInput = document.getElementById('price'); const roomInfo = document.getElementById('roomInfo'); if (roomSelect) { roomSelect.addEventListener('change', function () { const selectedOption = this.options[this.selectedIndex]; if (!selectedOption.value) { priceInput.value = ''; roomInfo.style.display = 'none'; return; } const price = selectedOption.getAttribute('data-price'); if (price) { priceInput.value = price; } roomInfo.textContent = 'Prix de la chambre : ' + Number(price).toLocaleString('fr-FR') + ' FCFA'; roomInfo.style.display = 'block'; }); /* |-------------------------------------------------------------------------- | Afficher automatiquement le prix si une chambre | est déjà sélectionnée après une erreur. |-------------------------------------------------------------------------- */ if (roomSelect.value) { roomSelect.dispatchEvent( new Event('change') ); } } 
</script> 
</body> 
</html>