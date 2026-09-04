<?php error_reporting(E_ALL); ini_set('display_errors', '1'); require_once __DIR__ . '/../config/database.php'; require_once __DIR__ . '/../includes/auth.php'; /* |-------------------------------------------------------------------------- | Vérification de connexion |-------------------------------------------------------------------------- */ if (!isLoggedIn()) { header('Location: /flux_hotel/auth/login.php'); exit; } /* |-------------------------------------------------------------------------- | Vérification du rôle réception |-------------------------------------------------------------------------- */ if ((int) ($_SESSION['role_id'] ?? 0) !== 2) { header('Location: /flux_hotel/admin/dashboard.php'); exit; } /* |-------------------------------------------------------------------------- | Informations de l'utilisateur connecté |-------------------------------------------------------------------------- */ $hotelId = (int) ($_SESSION['hotel_id'] ?? 0); $userId = (int) ($_SESSION['user_id'] ?? 0); $firstName = $_SESSION['first_name'] ?? ''; $lastName = $_SESSION['last_name'] ?? ''; $error = ''; $success = ''; /* |-------------------------------------------------------------------------- | TRAITEMENT DU FORMULAIRE |-------------------------------------------------------------------------- */ if ($_SERVER['REQUEST_METHOD'] === 'POST') { $reason = trim($_POST['reason'] ?? ''); $amount = trim($_POST['amount'] ?? ''); $paidBy = trim($_POST['paid_by'] ?? ''); $paidFor = trim($_POST['paid_for'] ?? ''); $expenseDate = trim($_POST['expense_date'] ?? ''); $responsible = trim($_POST['responsible'] ?? ''); /* |-------------------------------------------------------------------------- | Validation |-------------------------------------------------------------------------- */ if ( $reason === '' || $amount === '' || $paidBy === '' || $paidFor === '' || $expenseDate === '' || $responsible === '' ) { $error = 'Veuillez remplir tous les champs.'; } elseif (!is_numeric($amount) || $amount <= 0) { $error = 'Le montant doit être supérieur à zéro.'; } else { try { /* |-------------------------------------------------------------------------- | Insertion du décaissement |-------------------------------------------------------------------------- */ $sql = " INSERT INTO expenses ( hotel_id, user_id, reason, amount, paid_by, paid_for, expense_date, responsible, created_at ) VALUES ( :hotel_id, :user_id, :reason, :amount, :paid_by, :paid_for, :expense_date, :responsible, NOW() ) "; $stmt = $pdo->prepare($sql); $stmt->execute([ ':hotel_id' => $hotelId, ':user_id' => $userId, ':reason' => $reason, ':amount' => $amount, ':paid_by' => $paidBy, ':paid_for' => $paidFor, ':expense_date' => $expenseDate, ':responsible' => $responsible ]); $success = 'Décaissement enregistré avec succès.'; } catch (PDOException $e) { $error = 'Impossible d\'enregistrer le décaissement.'; } } } /* |-------------------------------------------------------------------------- | Date actuelle pour le formulaire |-------------------------------------------------------------------------- */ $currentDate = date('Y-m-d\TH:i'); ?> <!DOCTYPE html> <html lang="fr"> <head>
<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    Décaissement - Hotel Flow
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

        max-width: 800px;

        margin: 30px auto;

        padding: 0 20px;

    }


    .title {

        margin-bottom: 25px;

    }


    .title h2 {

        margin-bottom: 5px;

        color: #1e3a5f;

    }


    .title p {

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

        box-shadow:
            0 3px 15px
            rgba(0, 0, 0, 0.08);

    }


    .form-group {

        margin-bottom: 20px;

    }


    .form-group label {

        display: block;

        margin-bottom: 7px;

        font-weight: bold;

        color: #333;

    }


    .form-group input {

        width: 100%;

        padding: 12px;

        border: 1px solid #ccc;

        border-radius: 7px;

        font-size: 15px;

    }


    .form-group input:focus {

        outline: none;

        border-color: #1e3a5f;

        box-shadow:
            0 0 0 2px
            rgba(30, 58, 95, 0.1);

    }


    .row {

        display: grid;

        grid-template-columns:
            1fr 1fr;

        gap: 20px;

    }


    .button {

        width: 100%;

        border: none;

        padding: 14px;

        background: #1e3a5f;

        color: white;

        border-radius: 7px;

        font-size: 16px;

        font-weight: bold;

        cursor: pointer;

    }


    .button:hover {

        background: #162d4a;

    }


    .back {

        display: inline-block;

        margin-top: 20px;

        text-decoration: none;

        color: #1e3a5f;

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

    @media (max-width: 600px) {

        .container {

            margin-top: 20px;

            padding: 0 15px;

        }


        .form-card {

            padding: 20px;

        }


        .row {

            grid-template-columns: 1fr;

            gap: 0;

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
        href="/flux_hotel/reception/checkin.php"
    >
        📝 Enregistrement
    </a>


    <a
        href="/flux_hotel/reception/history.php"
    >
        📋 Historique
    </a>


    <a
        class="active"
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

</nav> <!-- ========================================================= CONTENU ========================================================= --> <main class="container">
<div class="title">

    <h2>
        💸 Nouveau décaissement
    </h2>

    <p>
        Enregistrer une sortie d'argent de l'hôtel.
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


<div class="form-card">


    <form
        method="POST"
        action=""
    >


        <!-- MOTIF -->

        <div class="form-group">

            <label for="reason">
                Motif du décaissement
            </label>

            <input
                type="text"
                id="reason"
                name="reason"
                placeholder="Exemple : Achat de produits d'entretien"
                value="<?= htmlspecialchars($_POST['reason'] ?? '') ?>"
                required
            >

        </div>


        <!-- MONTANT -->

        <div class="form-group">

            <label for="amount">
                Montant (FCFA)
            </label>

            <input
                type="number"
                id="amount"
                name="amount"
                min="1"
                step="1"
                placeholder="Exemple : 25000"
                value="<?= htmlspecialchars($_POST['amount'] ?? '') ?>"
                required
            >

        </div>


        <div class="row">


            <!-- PAR QUI -->

            <div class="form-group">

                <label for="paid_by">
                    Payé par
                </label>

                <input
                    type="text"
                    id="paid_by"
                    name="paid_by"
                    placeholder="Exemple : Caisse réception"
                    value="<?= htmlspecialchars($_POST['paid_by'] ?? '') ?>"
                    required
                >

            </div>


            <!-- POUR QUI -->

            <div class="form-group">

                <label for="paid_for">
                    Payé pour
                </label>

                <input
                    type="text"
                    id="paid_for"
                    name="paid_for"
                    placeholder="Exemple : Fournisseur"
                    value="<?= htmlspecialchars($_POST['paid_for'] ?? '') ?>"
                    required
                >

            </div>


        </div>


        <!-- DATE -->

        <div class="form-group">

            <label for="expense_date">
                Date et heure
            </label>

            <input
                type="datetime-local"
                id="expense_date"
                name="expense_date"
                value="<?= htmlspecialchars(
                    $_POST['expense_date']
                    ?? $currentDate
                ) ?>"
                required
            >

        </div>


        <!-- RESPONSABLE -->

        <div class="form-group">

            <label for="responsible">
                Responsable
            </label>

            <input
                type="text"
                id="responsible"
                name="responsible"
                placeholder="Nom du responsable"
                value="<?= htmlspecialchars(
                    $_POST['responsible']
                    ?? ($firstName . ' ' . $lastName)
                ) ?>"
                required
            >

        </div>


        <button
            type="submit"
            class="button"
        >

            💾 Enregistrer le décaissement

        </button>


    </form>


</div>


<a
    class="back"
    href="/flux_hotel/reception/dashboard.php"
>

    ← Retour au tableau de bord

</a>

</main> 
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