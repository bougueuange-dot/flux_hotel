<?php session_start(); require_once __DIR__ . '/../config/database.php'; $error = ''; $alerts = []; if (!isset($_SESSION['user_id'])) { header('Location: ../auth/login.php'); exit; } if (!isset($_SESSION['hotel_id'])) { $error = 'Hotel ID absent de la session.'; } else { $hotel_id = (int) $_SESSION['hotel_id']; try { /* * Récupérer les alertes de l'hôtel connecté */ $sql = " SELECT a.id, a.type, a.message, a.alert_at, a.is_read, s.expected_departure_at, c.first_name, c.last_name, r.room_number FROM alerts a INNER JOIN stays s ON a.stay_id = s.id INNER JOIN clients c ON s.client_id = c.id INNER JOIN rooms r ON s.room_id = r.id WHERE a.hotel_id = :hotel_id ORDER BY a.alert_at DESC "; $stmt = $pdo->prepare($sql); $stmt->execute([ ':hotel_id' => $hotel_id ]); $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC); /* * Compter les alertes non lues */ $sqlUnread = " SELECT COUNT(*) FROM alerts WHERE hotel_id = :hotel_id AND is_read = 0 "; $stmtUnread = $pdo->prepare($sqlUnread); $stmtUnread->execute([ ':hotel_id' => $hotel_id ]); $unreadCount = (int) $stmtUnread->fetchColumn(); } catch (PDOException $e) { $error = 'Erreur MySQL : ' . $e->getMessage(); $alerts = []; $unreadCount = 0; } } ?> <!DOCTYPE html> <html lang="fr"> <head> <meta charset="UTF-8"> <meta name="viewport" content="width=device-width, initial-scale=1.0"> <title>Alertes - Hotel Flow</title> <style> * { box-sizing: border-box; } body { margin: 0; font-family: Arial, sans-serif; background: #f4f6f9; color: #222; } .container { width: 95%; max-width: 1200px; margin: 30px auto; } .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; } h1 { margin: 0; color: #1e3a5f; } .subtitle { margin-top: 8px; color: #666; } .back { background: #1e3a5f; color: white; text-decoration: none; padding: 10px 16px; border-radius: 7px; } .error { background: #f8d7da; color: #842029; padding: 15px; border-radius: 8px; margin-bottom: 20px; } .cards { display: flex; gap: 20px; margin-bottom: 25px; } .card { background: white; padding: 20px; border-radius: 10px; width: 230px; box-shadow: 0 3px 12px rgba(0,0,0,0.08); } .card-title { color: #666; margin-bottom: 8px; } .card-number { font-size: 30px; font-weight: bold; color: #1e3a5f; } .red { color: #dc3545; } .table-box { background: white; border-radius: 10px; overflow-x: auto; box-shadow: 0 3px 12px rgba(0,0,0,0.08); } table { width: 100%; border-collapse: collapse; min-width: 900px; } th { background: #1e3a5f; color: white; padding: 14px; text-align: left; } td { padding: 14px; border-bottom: 1px solid #eee; } .unread { background: #fff5f5; } .badge { padding: 6px 10px; border-radius: 20px; font-size: 12px; font-weight: bold; } .badge-red { background: #f8d7da; color: #842029; } .badge-green { background: #d1e7dd; color: #0f5132; } .empty { padding: 50px; text-align: center; color: #666; } @media (max-width: 700px) { .header { flex-direction: column; align-items: flex-start; gap: 15px; } .cards { flex-direction: column; } .card { width: 100%; } } </style> </head> <body> <div class="container">
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
    .read-button {
    display: inline-block;
    margin-top: 8px;
    padding: 7px 10px;
    background: #198754;
    color: white;
    text-decoration: none;
    border-radius: 6px;
    font-size: 12px;
}

.read-button:hover {
    background: #146c43;
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
<div class="header">

    <div>

        <h1>⚠️ Alertes</h1>

        <div class="subtitle">
            Consultez les alertes générées par Hotel Flow.
        </div>

    </div>

    <a
        href="dashboard.php"
        class="back"
    >
        ← Tableau de bord
    </a>

</div>


<?php if ($error !== ''): ?>

    <div class="error">

        ❌ <?= htmlspecialchars($error) ?>

    </div>

<?php endif; ?>


<div class="cards">


    <div class="card">

        <div class="card-title">
            Total des alertes
        </div>

        <div class="card-number">
            <?= count($alerts) ?>
        </div>

    </div>


    <div class="card">

        <div class="card-title">
            Alertes non lues
        </div>

        <div class="card-number red">
            <?= $unreadCount ?>
        </div>

    </div>


</div>


<div class="table-box">


    <?php if (count($alerts) === 0): ?>

        <div class="empty">

            <div style="font-size:45px;">
                ✅
            </div>

            <h2>
                Aucune alerte
            </h2>

            <p>
                Aucune alerte n'est actuellement enregistrée.
            </p>

        </div>


    <?php else: ?>


        <table>

            <thead>

                <tr>

                    <th>Client</th>
                    <th>Chambre</th>
                    <th>Départ prévu</th>
                    <th>Heure alerte</th>
                    <th>Message</th>
                    <th>Statut</th>

                </tr>

            </thead>


            <tbody>


            <?php foreach ($alerts as $alert): ?>

                <tr class="<?=
                    ((int)$alert['is_read'] === 0)
                    ? 'unread'
                    : ''
                ?>">


                    <td>

                        <strong>

                            <?= htmlspecialchars(
                                $alert['first_name']
                            ) ?>

                            <?= htmlspecialchars(
                                $alert['last_name']
                            ) ?>

                        </strong>

                    </td>


                    <td>

                        <strong>

                            <?= htmlspecialchars(
                                $alert['room_number']
                            ) ?>

                        </strong>

                    </td>


                    <td>

                        <?= date(
                            'd/m/Y H:i',
                            strtotime(
                                $alert['expected_departure_at']
                            )
                        ) ?>

                    </td>


                    <td>

                        <?= date(
                            'd/m/Y H:i',
                            strtotime(
                                $alert['alert_at']
                            )
                        ) ?>

                    </td>


                    <td>

                        <?= htmlspecialchars(
                            $alert['message']
                        ) ?>

                    </td>


                    <td>

                    <?php if ((int)$alert['is_read'] === 0): ?>

<span class="badge badge-red">
    🔴 Non lue
</span>

<a
    href="mark_alert_read.php?id=<?= (int)$alert['id'] ?>"
    class="read-button"
>
    ✓ Marquer comme lue
</a>

<?php else: ?>

<span class="badge badge-green">
    🟢 Lue
</span>

<?php endif; ?>


                    </td>


                </tr>

            <?php endforeach; ?>


            </tbody>

        </table>


    <?php endif; ?>


</div>

</div> 
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