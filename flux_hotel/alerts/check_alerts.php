<?php require_once __DIR__ . '/../config/database.php'; $created = 0; $message = ''; $success = true; // ========================================================== // VÉRIFICATION DES ALERTES // ========================================================== try { $sql = " SELECT s.id AS stay_id, s.hotel_id, s.expected_departure_at, c.first_name, c.last_name, r.room_number FROM stays s INNER JOIN clients c ON c.id = s.client_id INNER JOIN rooms r ON r.id = s.room_id WHERE s.status = 'active' AND s.expected_departure_at <= DATE_ADD( NOW(), INTERVAL 24 HOUR ) ORDER BY s.expected_departure_at ASC "; $stmt = $pdo->prepare($sql); $stmt->execute(); $stays = $stmt->fetchAll(PDO::FETCH_ASSOC); foreach ($stays as $stay) { $stay_id = (int) $stay['stay_id']; $hotel_id = (int) $stay['hotel_id']; $client_name = $stay['first_name'] . ' ' . $stay['last_name']; $room_number = $stay['room_number']; $departure_timestamp = strtotime($stay['expected_departure_at']); $departure = date('d/m/Y H:i', $departure_timestamp); // Déterminer le type d'alerte if ($departure_timestamp < time()) { $type = 'departure_overdue'; $alert_message = 'Départ dépassé : ' . $client_name . ' - Chambre ' . $room_number . '. Départ prévu le ' . $departure; } else { $type = 'departure'; $alert_message = 'Départ prochain : ' . $client_name . ' - Chambre ' . $room_number . '. Départ prévu le ' . $departure; } // Vérifier si l'alerte existe déjà $check = $pdo->prepare(" SELECT id FROM alerts WHERE hotel_id = :hotel_id AND stay_id = :stay_id AND type = :type LIMIT 1 "); $check->execute([ ':hotel_id' => $hotel_id, ':stay_id' => $stay_id, ':type' => $type ]); $existing = $check->fetch(PDO::FETCH_ASSOC); // Créer l'alerte uniquement si elle n'existe pas if (!$existing) { $insert = $pdo->prepare(" INSERT INTO alerts ( hotel_id, stay_id, type, message, alert_at, is_read, created_at ) VALUES ( :hotel_id, :stay_id, :type, :message, NOW(), 0, NOW() ) "); $insert->execute([ ':hotel_id' => $hotel_id, ':stay_id' => $stay_id, ':type' => $type, ':message' => $alert_message ]); $created++; } } $message = $created . ' nouvelle(s) alerte(s) créée(s).'; } catch (PDOException $e) { $success = false; $message = 'Erreur MySQL : ' . $e->getMessage(); } ?> <!DOCTYPE html> <html lang="fr"> <head> <meta charset="UTF-8"> <meta name="viewport" content="width=device-width, initial-scale=1.0"> <title>Hotel Flow - Alertes</title> <style> * { box-sizing: border-box; } body { margin: 0; font-family: Arial, sans-serif; background: #f4f6f9; } .container { width: 90%; max-width: 650px; margin: 80px auto; background: white; padding: 35px; border-radius: 12px; text-align: center; box-shadow: 0 4px 15px rgba(0,0,0,.08); } h1 { color: #1e3a5f; } .message { padding: 20px; border-radius: 8px; margin: 25px 0; } .success { background: #d1e7dd; color: #0f5132; } .error { background: #f8d7da; color: #842029; } .button { display: inline-block; padding: 12px 18px; margin: 5px; background: #1e3a5f; color: white; text-decoration: none; border-radius: 7px; } .button:hover { background: #162d49; } </style> </head> <body> <div class="container">
<h1>
    ⚠️ Hotel Flow
</h1>


<?php if ($success): ?>

    <div class="message success">

        ✅ Vérification terminée.

        <br><br>

        <strong>
            <?= (int)$created ?>
        </strong>

        nouvelle(s) alerte(s) créée(s).

    </div>

<?php else: ?>

    <div class="message error">

        ❌

        <?= htmlspecialchars($message) ?>

    </div>

<?php endif; ?>


<a
    href="../admin/alerts.php"
    class="button"
>
    ⚠️ Voir les alertes
</a>


<a
    href="../admin/dashboard.php"
    class="button"
>
    ← Tableau de bord
</a>

</div> </body> </html>