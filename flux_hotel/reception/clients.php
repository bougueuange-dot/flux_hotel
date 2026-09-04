<?php /* |-------------------------------------------------------------------------- | HOTEL FLOW - CLIENTS RÉCEPTION |-------------------------------------------------------------------------- */ require_once __DIR__ . '/../includes/auth.php'; require_once __DIR__ . '/../config/database.php'; requireRole(2); $hotelId = getHotelId(); if ($hotelId <= 0) { die('Erreur : aucun hôtel associé à cette session.'); } $firstName = $_SESSION['first_name'] ?? ''; $lastName = $_SESSION['last_name'] ?? ''; $error = ''; $success = ''; /* |-------------------------------------------------------------------------- | AJOUTER UN CLIENT |-------------------------------------------------------------------------- */ if ($_SERVER['REQUEST_METHOD'] === 'POST') { $clientFirstName = trim($_POST['first_name'] ?? ''); $clientLastName = trim($_POST['last_name'] ?? ''); $cni = trim($_POST['cni_number'] ?? ''); $phone = trim($_POST['phone'] ?? ''); if ( $clientFirstName === '' || $clientLastName === '' || $cni === '' ) { $error = 'Veuillez remplir le prénom, le nom et la CNI.'; } else { try { /* |-------------------------------------------------------------------------- | Vérifier si la CNI existe déjà dans cet hôtel |-------------------------------------------------------------------------- */ $check = $pdo->prepare(" SELECT id FROM clients WHERE hotel_id = :hotel_id AND cni_number = :cni_number LIMIT 1 "); $check->execute([ ':hotel_id' => $hotelId, ':cni_number' => $cni ]); if ($check->fetch()) { $error = 'Un client avec cette CNI existe déjà.'; } else { /* |-------------------------------------------------------------------------- | Insérer le client |-------------------------------------------------------------------------- */ $insert = $pdo->prepare(" INSERT INTO clients ( hotel_id, first_name, last_name, cni_number, phone, created_at ) VALUES ( :hotel_id, :first_name, :last_name, :cni_number, :phone, NOW() ) "); $insert->execute([ ':hotel_id' => $hotelId, ':first_name' => $clientFirstName, ':last_name' => $clientLastName, ':cni_number' => $cni, ':phone' => $phone !== '' ? $phone : null ]); $success = 'Client ajouté avec succès.'; } } catch (PDOException $e) { $error = 'Impossible d\'ajouter le client.'; } } } /* |-------------------------------------------------------------------------- | RECHERCHE |-------------------------------------------------------------------------- */ $search = trim($_GET['search'] ?? ''); try { if ($search !== '') { $sql = " SELECT id, first_name, last_name, cni_number, phone, created_at FROM clients WHERE hotel_id = :hotel_id AND ( first_name LIKE :search OR last_name LIKE :search OR cni_number LIKE :search OR phone LIKE :search ) ORDER BY id DESC "; $stmt = $pdo->prepare($sql); $stmt->execute([ ':hotel_id' => $hotelId, ':search' => '%' . $search . '%' ]); } else { $sql = " SELECT id, first_name, last_name, cni_number, phone, created_at FROM clients WHERE hotel_id = :hotel_id ORDER BY id DESC "; $stmt = $pdo->prepare($sql); $stmt->execute([ ':hotel_id' => $hotelId ]); } $clients = $stmt->fetchAll(PDO::FETCH_ASSOC); } catch (PDOException $e) { $clients = []; $error = 'Impossible de charger les clients.'; } ?> <!DOCTYPE html> <html lang="fr"> <head> <meta charset="UTF-8">
<meta
name="viewport"
content="width=device-width, initial-scale=1.0"

<title>Clients - Hotel Flow</title> <!-- ===================================================== PWA ===================================================== --> <link rel="manifest" href="/flux_hotel/manifest.json" >
<meta
name="theme-color"
content="#1e3a5f"

<!-- ===================================================== CSS ===================================================== --> <style> * { box-sizing: border-box; } html, body { margin: 0; padding: 0; width: 100%; min-height: 100%; } body { font-family: Arial, Helvetica, sans-serif; background: #f4f6f9; color: #222; overflow-x: hidden; } /* ===================================================== HEADER ===================================================== */ .header { width: 100%; background: #1e3a5f; color: white; padding: 18px 20px; } .header-content { max-width: 1200px; margin: auto; } .header h1 { margin: 0; font-size: 25px; } .header p { margin: 6px 0 0; color: #dbe7f5; font-size: 14px; } /* ===================================================== NAVIGATION ===================================================== */ .nav { width: 100%; background: white; border-bottom: 1px solid #ddd; } .nav-container { max-width: 1200px; margin: auto; padding: 8px 20px; display: flex; flex-wrap: wrap; gap: 6px; } .nav a { display: flex; align-items: center; justify-content: center; min-height: 42px; padding: 9px 13px; border-radius: 7px; color: #1e3a5f; text-decoration: none; font-size: 14px; } .nav a:hover { background: #eaf0f7; } .nav a.active { background: #1e3a5f; color: white; } .nav a.logout { margin-left: auto; background: #c62828; color: white; } /* ===================================================== CONTENU ===================================================== */ .container { width: 100%; max-width: 1200px; margin: 25px auto; padding: 0 20px; } /* ===================================================== TITRE ===================================================== */ .page-title { background: white; padding: 22px; border-radius: 12px; box-shadow: 0 3px 12px rgba(0,0,0,.08); margin-bottom: 20px; } .page-title h2 { margin: 0 0 6px; color: #1e3a5f; } .page-title p { margin: 0; color: #666; } /* ===================================================== MESSAGES ===================================================== */ .message { padding: 14px 16px; border-radius: 8px; margin-bottom: 20px; font-weight: bold; } .message.error { background: #f8d7da; color: #842029; } .message.success { background: #d1e7dd; color: #0f5132; } /* ===================================================== AJOUT CLIENT ===================================================== */ .add-card { background: white; padding: 22px; border-radius: 12px; box-shadow: 0 3px 12px rgba(0,0,0,.08); margin-bottom: 20px; } .add-card h3 { margin-top: 0; color: #1e3a5f; } .form-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 15px; } .form-group { display: flex; flex-direction: column; } .form-group label { margin-bottom: 6px; font-size: 13px; font-weight: bold; color: #555; } .form-group input { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 7px; font-size: 14px; outline: none; } .form-group input:focus { border-color: #1e3a5f; box-shadow: 0 0 0 2px rgba(30,58,95,.1); } .form-button { display: flex; align-items: end; } .btn-add { width: 100%; padding: 12px; border: none; border-radius: 7px; background: #198754; color: white; font-weight: bold; cursor: pointer; } .btn-add:hover { background: #157347; } /* ===================================================== RECHERCHE ===================================================== */ .search-card { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 3px 12px rgba(0,0,0,.08); margin-bottom: 20px; } .search-form { display: flex; gap: 10px; } .search-form input { flex: 1; min-width: 0; padding: 12px; border: 1px solid #ccc; border-radius: 7px; font-size: 14px; } .search-form button { padding: 12px 20px; border: none; border-radius: 7px; background: #1e3a5f; color: white; font-weight: bold; cursor: pointer; } .search-form button:hover { background: #162d49; } .btn-reset { display: flex; align-items: center; padding: 0 15px; border-radius: 7px; background: #6c757d; color: white; text-decoration: none; font-size: 14px; } /* ===================================================== LISTE ===================================================== */ .card { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 3px 12px rgba(0,0,0,.08); } .count { display: inline-block; background: #1e3a5f; color: white; padding: 8px 14px; border-radius: 20px; font-size: 13px; margin-bottom: 15px; } .table-container { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; } table { width: 100%; min-width: 750px; border-collapse: collapse; } th, td { padding: 13px; border-bottom: 1px solid #eee; text-align: left; } th { background: #f8f9fa; color: #1e3a5f; font-size: 13px; } td { font-size: 14px; } tr:hover { background: #fafafa; } .empty { text-align: center; padding: 45px 20px; color: #777; } /* ===================================================== FOOTER ===================================================== */ .footer { text-align: center; color: #777; padding: 35px 15px; font-size: 13px; } /* ===================================================== TABLETTE ===================================================== */ @media (max-width: 900px) { .form-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } } /* ===================================================== MOBILE ===================================================== */ @media (max-width: 600px) { .header { padding: 15px 12px; } .header h1 { font-size: 21px; } .header p { font-size: 12px; } /* NAV */ .nav-container { width: 100%; padding: 8px; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 6px; } .nav a { width: 100%; min-height: 44px; padding: 8px 4px; font-size: 12px; text-align: center; } .nav a.logout { margin-left: 0; grid-column: 1 / -1; } /* CONTENU */ .container { width: 100%; margin: 18px auto; padding: 0 12px; } .page-title { padding: 18px; } .page-title h2 { font-size: 21px; } .page-title p { font-size: 13px; line-height: 1.5; } /* AJOUT */ .add-card { padding: 16px; } .form-grid { grid-template-columns: 1fr; gap: 12px; } .form-group input { min-height: 44px; font-size: 16px; } .btn-add { min-height: 46px; font-size: 15px; } /* RECHERCHE */ .search-card { padding: 16px; } .search-form { display: grid; grid-template-columns: 1fr; gap: 8px; } .search-form input { min-height: 44px; font-size: 16px; } .search-form button { min-height: 44px; } .btn-reset { min-height: 44px; justify-content: center; } /* TABLE */ .card { padding: 14px; } .table-container { width: 100%; overflow-x: auto; } table { min-width: 750px; } th, td { padding: 10px; font-size: 13px; } .footer { padding: 30px 15px; font-size: 12px; } } </style> </head> <body> <!-- ===================================================== HEADER ===================================================== --> <header class="header">
<div class="header-content">

    <h1>
        🏨 Hotel Flow
    </h1>

    <p>
        Espace Réception
    </p>

</div>

</header> <!-- ===================================================== NAVIGATION ===================================================== --> <nav class="nav">
<div class="nav-container">

    <a
        href="/flux_hotel/reception/dashboard.php"
    >
        🏠 Tableau de bord
    </a>

    <a
        class="active"
        href="/flux_hotel/reception/clients.php"
    >
        👥 Clients
    </a>

    <a
        href="/flux_hotel/reception/stays.php"
    >
        🛏️ Séjours
    </a>
     
    <a
       
    href="/flux_hotel/reception/reservations.php"
    >
       📅 Réservations
    </a>
    <a
        href="/flux_hotel/reception/rooms.php"
    >
        🚪 Chambres
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
        🚪 Déconnexion
    </a>

</div>

</nav> <!-- ===================================================== CONTENU ===================================================== --> <main class="container">
<!-- TITRE -->

<div class="page-title">

    <h2>
        👥 Clients
    </h2>

    <p>
        Bonjour
        <?= htmlspecialchars($firstName) ?>
        <?= htmlspecialchars($lastName) ?>.
        Gestion des clients de votre hôtel.
    </p>

</div>


<!-- MESSAGES -->

<?php if ($error !== ''): ?>

    <div class="message error">

        ⚠️
        <?= htmlspecialchars($error) ?>

    </div>

<?php endif; ?>


<?php if ($success !== ''): ?>

    <div class="message success">

        ✅
        <?= htmlspecialchars($success) ?>

    </div>

<?php endif; ?>


<!-- =================================================
     AJOUT CLIENT
     ================================================= -->

<div class="add-card">

    <h3>
        ➕ Ajouter un client
    </h3>

    <form method="POST">

        <div class="form-grid">


            <div class="form-group">

                <label for="first_name">
                    Prénom *
                </label>

                <input
                    type="text"
                    id="first_name"
                    name="first_name"
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
                    id="last_name"
                    name="last_name"
                    placeholder="Ex : Dupont"
                    required
                >

            </div>


            <div class="form-group">

                <label for="cni_number">
                    CNI *
                </label>

                <input
                    type="text"
                    id="cni_number"
                    name="cni_number"
                    placeholder="Numéro CNI"
                    required
                >

            </div>


            <div class="form-group">

                <label for="phone">
                    Téléphone
                </label>

                <input
                    type="tel"
                    id="phone"
                    name="phone"
                    placeholder="Ex : 691000000"
                >

            </div>


            <div class="form-button">

                <button
                    type="submit"
                    class="btn-add"
                >
                    ➕ Ajouter le client
                </button>

            </div>

        </div>

    </form>

</div>


<!-- =================================================
     RECHERCHE
     ================================================= -->

<div class="search-card">

    <form
        method="GET"
        class="search-form"
    >

        <input
            type="search"
            name="search"
            value="<?= htmlspecialchars($search) ?>"
            placeholder="🔎 Rechercher par nom, CNI ou téléphone..."
        >

        <button type="submit">
            🔎 Rechercher
        </button>

        <?php if ($search !== ''): ?>

            <a
                class="btn-reset"
                href="/flux_hotel/reception/clients.php"
            >
                Réinitialiser
            </a>

        <?php endif; ?>

    </form>

</div>


<!-- =================================================
     LISTE DES CLIENTS
     ================================================= -->

<div class="card">

    <div class="count">

        <?= count($clients) ?>

        client(s)

        <?php if ($search !== ''): ?>

            trouvé(s)

        <?php endif; ?>

    </div>


    <?php if (count($clients) > 0): ?>

        <div class="table-container">

            <table>

                <thead>

                    <tr>

                        <th>ID</th>

                        <th>Prénom</th>

                        <th>Nom</th>

                        <th>CNI</th>

                        <th>Téléphone</th>

                        <th>Créé le</th>

                    </tr>

                </thead>


                <tbody>

                <?php foreach ($clients as $client): ?>

                    <tr>

                        <td>
                            <?= (int) $client['id'] ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $client['first_name']
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $client['last_name']
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $client['cni_number']
                            ) ?>
                        </td>

                        <td>

                            <?php if (
                                !empty($client['phone'])
                            ): ?>

                                <?= htmlspecialchars(
                                    $client['phone']
                                ) ?>

                            <?php else: ?>

                                <span style="color:#999;">
                                    Non renseigné
                                </span>

                            <?php endif; ?>

                        </td>

                        <td>

                            <?= htmlspecialchars(
                                $client['created_at']
                            ) ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php else: ?>

        <div class="empty">

            <div style="font-size:45px;">
                👥
            </div>

            <?php if ($search !== ''): ?>

                <p>
                    Aucun client ne correspond à votre recherche.
                </p>

            <?php else: ?>

                <p>
                    Aucun client enregistré pour cet hôtel.
                </p>

            <?php endif; ?>

        </div>

    <?php endif; ?>

</div>

</main> <!-- ===================================================== FOOTER ===================================================== --> <footer class="footer">
Hotel Flow © <?= date('Y') ?>

</footer> <!-- ===================================================== PWA ===================================================== --> <script> if ('serviceWorker' in navigator) { window.addEventListener( 'load', function () { navigator.serviceWorker.register( '/flux_hotel/sw.js' ) .catch(function (error) { console.error( 'Erreur Service Worker :', error ); }); } ); } </script> </body> </html>