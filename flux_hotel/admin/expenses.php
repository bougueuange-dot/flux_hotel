<?php error_reporting(E_ALL); ini_set('display_errors', '1'); require_once __DIR__ . '/../config/database.php'; require_once __DIR__ . '/../includes/auth.php'; /* |-------------------------------------------------------------------------- | Vérification de connexion |-------------------------------------------------------------------------- */ if (!isLoggedIn()) { header('Location: /flux_hotel/auth/login.php'); exit; } /* |-------------------------------------------------------------------------- | Vérification du rôle administrateur |-------------------------------------------------------------------------- */ if ((int) ($_SESSION['role_id'] ?? 0) !== 1) { header('Location: /flux_hotel/reception/dashboard.php'); exit; } /* |-------------------------------------------------------------------------- | Informations de l'utilisateur connecté |-------------------------------------------------------------------------- */ $hotelId = (int) ($_SESSION['hotel_id'] ?? 0); $firstName = $_SESSION['first_name'] ?? ''; $lastName = $_SESSION['last_name'] ?? ''; /* |-------------------------------------------------------------------------- | Variables |-------------------------------------------------------------------------- */ $expenses = []; $totalExpenses = 0; $error = ''; /* |-------------------------------------------------------------------------- | Récupération des décaissements |-------------------------------------------------------------------------- */ try { $sql = " SELECT e.id, e.reason, e.amount, e.paid_by, e.paid_for, e.expense_date, e.responsible, e.created_at, u.first_name AS user_first_name, u.last_name AS user_last_name FROM expenses e INNER JOIN users u ON u.id = e.user_id WHERE e.hotel_id = :hotel_id ORDER BY e.expense_date DESC "; $stmt = $pdo->prepare($sql); $stmt->execute([ ':hotel_id' => $hotelId ]); $expenses = $stmt->fetchAll(); /* |-------------------------------------------------------------------------- | Calcul du total |-------------------------------------------------------------------------- */ foreach ($expenses as $expense) { $totalExpenses += (float) $expense['amount']; } } catch (PDOException $e) { $error = 'Impossible de récupérer les décaissements.'; } ?> <!DOCTYPE html> <html lang="fr"> <head>
<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    Décaissements - Hotel Flow
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
       STATISTIQUE
       ===================================================== */

    .summary {

        background: white;

        padding: 25px;

        border-radius: 12px;

        box-shadow:
            0 3px 12px
            rgba(0, 0, 0, 0.08);

        margin-bottom: 25px;

        border-left:
            5px solid #dc3545;

    }


    .summary-title {

        color: #666;

        font-size: 14px;

        margin-bottom: 8px;

    }


    .summary-amount {

        font-size: 30px;

        font-weight: bold;

        color: #dc3545;

    }


    /* =====================================================
       MESSAGES
       ===================================================== */

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

    .table-card {

        background: white;

        border-radius: 12px;

        box-shadow:
            0 3px 12px
            rgba(0, 0, 0, 0.08);

        overflow: hidden;

    }


    .table-wrapper {

        overflow-x: auto;

    }


    table {

        width: 100%;

        border-collapse: collapse;

        min-width: 950px;

    }


    th {

        background: #1e3a5f;

        color: white;

        padding: 14px;

        text-align: left;

        font-size: 13px;

    }


    td {

        padding: 14px;

        border-bottom:
            1px solid #eee;

        font-size: 14px;

    }


    tbody tr:hover {

        background: #f8fafc;

    }


    .amount {

        font-weight: bold;

        color: #c62828;

    }


    .date {

        white-space: nowrap;

    }


    .empty {

        text-align: center;

        padding: 50px 20px;

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


        .summary {

            padding: 20px;

        }


        .summary-amount {

            font-size: 25px;

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

</header> <!-- ========================================================= NAVIGATION ========================================================= --> <nav class="nav">
<div class="nav-container">


    <a
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
        class="active"
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
<div class="title">

    <h2>
        💸 Décaissements
    </h2>

    <p>

        Consultez les sorties d'argent
        enregistrées par la réception.

    </p>

</div>


<?php if ($error !== ''): ?>

    <div class="error">

        ⚠️

        <?= htmlspecialchars($error) ?>

    </div>

<?php endif; ?>


<!-- =====================================================
     TOTAL
     ===================================================== -->

<div class="summary">

    <div class="summary-title">

        Total des décaissements enregistrés

    </div>


    <div class="summary-amount">

        <?= number_format(
            $totalExpenses,
            0,
            ',',
            ' '
        ) ?>

        FCFA

    </div>

</div>


<!-- =====================================================
     TABLEAU
     ===================================================== -->

<div class="table-card">


    <?php if (count($expenses) === 0): ?>


        <div class="empty">

            <div style="font-size:40px;">
                💸
            </div>

            <p>
                Aucun décaissement enregistré.
            </p>

        </div>


    <?php else: ?>


        <div class="table-wrapper">


            <table>


                <thead>

                    <tr>

                        <th>
                            Date
                        </th>

                        <th>
                            Motif
                        </th>

                        <th>
                            Montant
                        </th>

                        <th>
                            Payé par
                        </th>

                        <th>
                            Payé pour
                        </th>

                        <th>
                            Responsable
                        </th>

                        <th>
                            Enregistré par
                        </th>

                    </tr>

                </thead>


                <tbody>


                <?php foreach ($expenses as $expense): ?>


                    <tr>


                        <td class="date">

                            <?= date(
                                'd/m/Y H:i',
                                strtotime(
                                    $expense['expense_date']
                                )
                            ) ?>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $expense['reason']
                            ) ?>

                        </td>


                        <td class="amount">

                            <?= number_format(
                                (float) $expense['amount'],
                                0,
                                ',',
                                ' '
                            ) ?>

                            FCFA

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $expense['paid_by']
                            ) ?>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $expense['paid_for']
                            ) ?>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $expense['responsible']
                            ) ?>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $expense['user_first_name']
                                . ' '
                                . $expense['user_last_name']
                            ) ?>

                        </td>


                    </tr>


                <?php endforeach; ?>


                </tbody>


            </table>


        </div>


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