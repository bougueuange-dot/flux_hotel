<?php session_start(); $error = ''; $hotel_id = 0; $db_ok = false; $clients_table_ok = false; // ========================================================== // CONNEXION MYSQL // ========================================================== require_once __DIR__ . '/../config/database.php'; // ========================================================== // TEST PDO // ========================================================== if (isset($pdo) && $pdo instanceof PDO) { $db_ok = true; } // ========================================================== // RÉCUPÉRATION HOTEL_ID // ========================================================== if (isset($_SESSION['hotel_id'])) { $hotel_id = (int) $_SESSION['hotel_id']; } // ========================================================== // TEST TABLE CLIENTS // ========================================================== if ($db_ok) { try { $stmt = $pdo->query(" SELECT COUNT(*) AS total FROM clients "); $result = $stmt->fetch(PDO::FETCH_ASSOC); $clients_table_ok = true; $total_clients = (int)($result['total'] ?? 0); } catch (PDOException $e) { $error = $e->getMessage(); $total_clients = 0; } } else { $total_clients = 0; } ?> <!DOCTYPE html> <html lang="fr"> <head> <meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1.0"

<title>Hotel Flow - Test</title> <style> * { box-sizing: border-box; } body { margin: 0; font-family: Arial, sans-serif; background: #f4f6f9; } .box { max-width: 700px; margin: 60px auto; background: white; padding: 35px; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,.1); } h1 { color: #1e3a5f; } .result { padding: 18px; margin: 12px 0; border-radius: 9px; } .ok { background: #d1e7dd; color: #0f5132; } .warning { background: #fff3cd; color: #664d03; } .error { background: #f8d7da; color: #842029; } .number { font-size: 25px; font-weight: bold; } </style> </head> <body> <div class="box"> <h1>🏨 Hotel Flow — Diagnostic</h1> <div class="result <?= $db_ok ? 'ok' : 'error' ?>"> <?php if ($db_ok): ?>
✅ Connexion MySQL :

<strong>OK</strong>

<?php else: ?>
❌ Connexion MySQL :

<strong>ÉCHEC</strong>

<?php endif; ?> </div> <div class="result <?= $hotel_id > 0 ? 'ok' : 'warning' ?>"> <?php if ($hotel_id > 0): ?>
✅ Hôtel de la session :

<strong><?= $hotel_id ?></strong>

<?php else: ?>
⚠️ Aucun `hotel_id` dans la session.

<?php endif; ?> </div> <div class="result <?= $clients_table_ok ? 'ok' : 'error' ?>"> <?php if ($clients_table_ok): ?>
✅ Table `clients` :

<strong>OK</strong>

<br><br>

Nombre total de clients :

<span class="number">

    <?= $total_clients ?>

</span>

<?php else: ?>
❌ Impossible de lire la table `clients`.

<?php endif; ?> </div> <?php if ($error !== ''): ?> <div class="result error">
❌ Erreur SQL :

<br><br>

<?= htmlspecialchars($error) ?>

</div> <?php endif; ?> </div> </body> </html>