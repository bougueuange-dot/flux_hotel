<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

/*
|--------------------------------------------------------------------------
| AUTHENTIFICATION
|--------------------------------------------------------------------------
*/

if (!isLoggedIn()) {
    header('Location: /flux_hotel/auth/login.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| ADMINISTRATEUR UNIQUEMENT
|--------------------------------------------------------------------------
*/

if ((int)($_SESSION['role_id'] ?? 0) !== 1) {
    header('Location: /flux_hotel/reception/dashboard.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| SESSION
|--------------------------------------------------------------------------
*/

$hotelId = (int)($_SESSION['hotel_id'] ?? 0);

$error = '';
$success = '';

$users = [];
$roles = [];

/*
|--------------------------------------------------------------------------
| VÉRIFICATION HÔTEL
|--------------------------------------------------------------------------
*/

if ($hotelId <= 0) {
    die('Hôtel non identifié.');
}

/*
|--------------------------------------------------------------------------
| TRAITEMENT DES FORMULAIRES
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    /*
    |--------------------------------------------------------------------------
    | AJOUTER UTILISATEUR
    |--------------------------------------------------------------------------
    */

    if ($action === 'add_user') {

        $username = trim($_POST['username'] ?? '');
        $roleId = (int)($_POST['role_id'] ?? 0);
        $password = $_POST['password'] ?? '';

        if (
            $username === '' ||
            $roleId <= 0 ||
            $password === ''
        ) {
            $error = "Veuillez remplir tous les champs obligatoires.";
        } elseif (strlen($username) < 3) {
            $error = "Le nom d'utilisateur doit contenir au moins 3 caractères.";
        } elseif (strlen($password) < 6) {
            $error = "Le mot de passe doit contenir au moins 6 caractères.";
        } else {

            try {

                /*
                |--------------------------------------------------------------------------
                | VÉRIFIER LE RÔLE
                |--------------------------------------------------------------------------
                */

                $stmt = $pdo->prepare("
                    SELECT id
                    FROM roles
                    WHERE id = :role_id
                    LIMIT 1
                ");

                $stmt->execute([
                    ':role_id' => $roleId
                ]);

                $role = $stmt->fetch();

                if (!$role) {
                    throw new Exception("Le rôle sélectionné n'existe pas.");
                }

                /*
                |--------------------------------------------------------------------------
                | VÉRIFIER USERNAME
                |--------------------------------------------------------------------------
                */

                $stmt = $pdo->prepare("
                    SELECT id
                    FROM users
                    WHERE hotel_id = :hotel_id
                    AND username = :username
                    LIMIT 1
                ");

                $stmt->execute([
                    ':hotel_id' => $hotelId,
                    ':username' => $username
                ]);

                if ($stmt->fetch()) {
                    throw new Exception(
                        "Ce nom d'utilisateur existe déjà dans cet hôtel."
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | HASH MOT DE PASSE
                |--------------------------------------------------------------------------
                */

                $passwordHash = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                /*
                |--------------------------------------------------------------------------
                | CRÉATION
                |--------------------------------------------------------------------------
                |
                | Ta table exige first_name et last_name.
                | On met donc une valeur neutre.
                |
                */

                $stmt = $pdo->prepare("
                    INSERT INTO users (
                        hotel_id,
                        role_id,
                        first_name,
                        last_name,
                        username,
                        password_hash,
                        phone,
                        status,
                        created_at
                    )
                    VALUES (
                        :hotel_id,
                        :role_id,
                        :first_name,
                        :last_name,
                        :username,
                        :password_hash,
                        NULL,
                        'active',
                        NOW()
                    )
                ");

                $stmt->execute([
                    ':hotel_id' => $hotelId,
                    ':role_id' => $roleId,
                    ':first_name' => '',
                    ':last_name' => '',
                    ':username' => $username,
                    ':password_hash' => $passwordHash
                ]);

                header(
                    'Location: /flux_hotel/admin/users.php?success=added'
                );
                exit;

            } catch (PDOException $e) {

                $error = "Impossible de créer l'utilisateur.";

            } catch (Exception $e) {

                $error = $e->getMessage();
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | MODIFIER UTILISATEUR
    |--------------------------------------------------------------------------
    */

    if ($action === 'edit_user') {

        $userId = (int)($_POST['user_id'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $roleId = (int)($_POST['role_id'] ?? 0);
        $password = $_POST['password'] ?? '';

        if (
            $userId <= 0 ||
            $username === '' ||
            $roleId <= 0
        ) {
            $error = "Veuillez remplir tous les champs obligatoires.";
        } elseif (strlen($username) < 3) {
            $error = "Le nom d'utilisateur doit contenir au moins 3 caractères.";
        } elseif (
            $password !== '' &&
            strlen($password) < 6
        ) {
            $error = "Le nouveau mot de passe doit contenir au moins 6 caractères.";
        } else {

            try {

                /*
                |--------------------------------------------------------------------------
                | VÉRIFIER QUE L'UTILISATEUR APPARTIENT À L'HÔTEL
                |--------------------------------------------------------------------------
                */

                $stmt = $pdo->prepare("
                    SELECT id
                    FROM users
                    WHERE id = :user_id
                    AND hotel_id = :hotel_id
                    LIMIT 1
                ");

                $stmt->execute([
                    ':user_id' => $userId,
                    ':hotel_id' => $hotelId
                ]);

                $existingUser = $stmt->fetch();

                if (!$existingUser) {
                    throw new Exception(
                        "Utilisateur introuvable."
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | VÉRIFIER LE RÔLE
                |--------------------------------------------------------------------------
                */

                $stmt = $pdo->prepare("
                    SELECT id
                    FROM roles
                    WHERE id = :role_id
                    LIMIT 1
                ");

                $stmt->execute([
                    ':role_id' => $roleId
                ]);

                if (!$stmt->fetch()) {
                    throw new Exception(
                        "Le rôle sélectionné n'existe pas."
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | VÉRIFIER USERNAME
                |--------------------------------------------------------------------------
                */

                $stmt = $pdo->prepare("
                    SELECT id
                    FROM users
                    WHERE hotel_id = :hotel_id
                    AND username = :username
                    AND id != :user_id
                    LIMIT 1
                ");

                $stmt->execute([
                    ':hotel_id' => $hotelId,
                    ':username' => $username,
                    ':user_id' => $userId
                ]);

                if ($stmt->fetch()) {
                    throw new Exception(
                        "Ce nom d'utilisateur est déjà utilisé."
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | MODIFICATION AVEC OU SANS MOT DE PASSE
                |--------------------------------------------------------------------------
                */

                if ($password !== '') {

                    $passwordHash = password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );

                    $stmt = $pdo->prepare("
                        UPDATE users
                        SET
                            role_id = :role_id,
                            username = :username,
                            password_hash = :password_hash
                        WHERE id = :user_id
                        AND hotel_id = :hotel_id
                    ");

                    $stmt->execute([
                        ':role_id' => $roleId,
                        ':username' => $username,
                        ':password_hash' => $passwordHash,
                        ':user_id' => $userId,
                        ':hotel_id' => $hotelId
                    ]);

                } else {

                    $stmt = $pdo->prepare("
                        UPDATE users
                        SET
                            role_id = :role_id,
                            username = :username
                        WHERE id = :user_id
                        AND hotel_id = :hotel_id
                    ");

                    $stmt->execute([
                        ':role_id' => $roleId,
                        ':username' => $username,
                        ':user_id' => $userId,
                        ':hotel_id' => $hotelId
                    ]);
                }

                header(
                    'Location: /flux_hotel/admin/users.php?success=updated'
                );
                exit;

            } catch (PDOException $e) {

                $error = "Impossible de modifier l'utilisateur.";

            } catch (Exception $e) {

                $error = $e->getMessage();
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | ACTIVER / DÉSACTIVER
    |--------------------------------------------------------------------------
    */

    if ($action === 'toggle_status') {

        $userId = (int)($_POST['user_id'] ?? 0);

        if ($userId <= 0) {

            $error = "Utilisateur invalide.";

        } else {

            try {

                /*
                | Ne pas modifier son propre compte
                */

                $currentUserId = (int)($_SESSION['user_id'] ?? 0);

                if ($userId === $currentUserId) {
                    throw new Exception(
                        "Vous ne pouvez pas désactiver votre propre compte."
                    );
                }

                $stmt = $pdo->prepare("
                    SELECT status
                    FROM users
                    WHERE id = :user_id
                    AND hotel_id = :hotel_id
                    LIMIT 1
                ");

                $stmt->execute([
                    ':user_id' => $userId,
                    ':hotel_id' => $hotelId
                ]);

                $user = $stmt->fetch();

                if (!$user) {
                    throw new Exception(
                        "Utilisateur introuvable."
                    );
                }

                $newStatus =
                    $user['status'] === 'active'
                        ? 'inactive'
                        : 'active';

                $stmt = $pdo->prepare("
                    UPDATE users
                    SET status = :status
                    WHERE id = :user_id
                    AND hotel_id = :hotel_id
                ");

                $stmt->execute([
                    ':status' => $newStatus,
                    ':user_id' => $userId,
                    ':hotel_id' => $hotelId
                ]);

                header(
                    'Location: /flux_hotel/admin/users.php?success=status'
                );
                exit;

            } catch (PDOException $e) {

                $error = "Impossible de modifier le statut.";

            } catch (Exception $e) {

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

if (($_GET['success'] ?? '') === 'added') {
    $success = "Utilisateur ajouté avec succès.";
}

if (($_GET['success'] ?? '') === 'updated') {
    $success = "Utilisateur modifié avec succès.";
}

if (($_GET['success'] ?? '') === 'status') {
    $success = "Statut de l'utilisateur modifié.";
}

/*
|--------------------------------------------------------------------------
| RÉCUPÉRER LES RÔLES
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->query("
        SELECT id, name, description
        FROM roles
        ORDER BY name ASC
    ");

    $roles = $stmt->fetchAll();

} catch (PDOException $e) {

    $error = "Impossible de récupérer les rôles.";
}

/*
|--------------------------------------------------------------------------
| RÉCUPÉRER LES UTILISATEURS DE L'HÔTEL
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->prepare("
        SELECT
            u.id,
            u.username,
            u.role_id,
            u.first_name,
            u.last_name,
            u.phone,
            u.status,
            u.created_at,
            r.name AS role_name
        FROM users u
        INNER JOIN roles r
            ON r.id = u.role_id
        WHERE u.hotel_id = :hotel_id
        ORDER BY u.username ASC
    ");

    $stmt->execute([
        ':hotel_id' => $hotelId
    ]);

    $users = $stmt->fetchAll();

} catch (PDOException $e) {

    $error = "Impossible de récupérer les utilisateurs.";
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

<title>Utilisateurs - Hotel Flow</title>

<meta
    name="theme-color"
    content="#1e3a5f"
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

/* HEADER */

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
    margin: 6px 0 0;
    color: #dbe7f5;
}

/* NAVIGATION */

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

/* CONTENU */

.container {
    max-width: 1150px;
    margin: 30px auto;
    padding: 0 20px;
}

.page-title h2 {
    color: #1e3a5f;
    margin-bottom: 8px;
}

.page-title p {
    color: #666;
}

/* MESSAGES */

.success,
.error {
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.success {
    background: #d1e7dd;
    color: #0f5132;
}

.error {
    background: #f8d7da;
    color: #842029;
}

/* CARD */

.card {
    background: white;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 3px 12px rgba(0,0,0,0.08);
    margin-bottom: 25px;
}

.card h3 {
    margin-top: 0;
    color: #1e3a5f;
}

/* FORM */

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}

.form-group {
    margin-bottom: 15px;
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
}

.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: #1e3a5f;
}

/* BOUTONS */

.btn {
    border: none;
    border-radius: 7px;
    padding: 10px 15px;
    cursor: pointer;
    font-size: 14px;
}

.btn-primary {
    background: #1e3a5f;
    color: white;
}

.btn-primary:hover {
    background: #162d49;
}

.btn-warning {
    background: #f0ad4e;
    color: white;
}

.btn-success {
    background: #198754;
    color: white;
}

.btn-danger {
    background: #c62828;
    color: white;
}

/* TABLE */

.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th {
    background: #1e3a5f;
    color: white;
    padding: 13px;
    text-align: left;
}

td {
    padding: 13px;
    border-bottom: 1px solid #eee;
}

tr:hover {
    background: #f8fafc;
}

/* BADGES */

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

.badge-inactive {
    background: #f8d7da;
    color: #842029;
}

/* ACTIONS */

.actions {
    display: flex;
    gap: 7px;
    flex-wrap: wrap;
}

/* FOOTER */

.footer {
    text-align: center;
    color: #777;
    padding: 40px 20px;
    font-size: 13px;
}
.password-wrapper {
    position: relative;
    width: 100%;
}

.password-wrapper input {
    width: 100%;
    padding-right: 50px;
}

.password-toggle {
    position: absolute;
    right: 5px;
    top: 50%;
    transform: translateY(-50%);

    width: 42px;
    height: 42px;

    border: none;
    background: transparent;

    cursor: pointer;
    font-size: 18px;
}

.password-toggle:hover {
    background: #f1f1f1;
    border-radius: 6px;
}

.form-group small {
    display: block;
    margin-top: 6px;
    color: #777;
    font-size: 12px;
}

/* MOBILE */

@media (max-width: 700px) {

    .container {
        padding: 0 15px;
        margin-top: 20px;
    }

    .card {
        padding: 20px;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }

    .nav-container {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        padding: 8px;
    }

    .nav a {
        text-align: center;
        font-size: 12px;
        padding: 10px 5px;
    }

    .nav .logout {
        margin-left: 0;
        grid-column: span 2;
    }

    input,
    select,
    button {
        min-height: 44px;
        font-size: 16px;
    }

}

</style>

</head>

<body>

<header class="header">

    <div class="header-content">

        <h1>🏨 Hotel Flow</h1>

        <p>
            Administration — Gestion des utilisateurs
        </p>

    </div>

</header>

<nav class="nav">

    <div class="nav-container">

        <a href="/flux_hotel/admin/dashboard.php">
            🏠 Tableau de bord
        </a>

        <a href="/flux_hotel/admin/rooms.php">
            🛏️ Chambres
        </a>


        <a href="/flux_hotel/admin/history.php">
            📋 Historique
        </a>


        <a href="/flux_hotel/admin/sales.php">
            💰 Revenus chambre
        </a>


        <a href="/flux_hotel/admin/expenses.php">
            💸 Décaissements
        </a>


        <a href="/flux_hotel/admin/alerts.php">
            ⚠️ Alertes
        </a>

        <a
            href="/flux_hotel/admin/users.php"
            class="active"
        >
            👤 Utilisateurs
        </a>

        <a href="/flux_hotel/auth/logout.php" class="logout">
            Déconnexion
        </a>

    </div>

</nav>

<main class="container">

    <div class="page-title">

        <h2>👤 Gestion des utilisateurs</h2>

        <p>
            Créez et gérez les comptes d'accès de votre hôtel.
        </p>

    </div>

    <?php if ($success !== ''): ?>

        <div class="success">
            ✅ <?= htmlspecialchars($success) ?>
        </div>

    <?php endif; ?>

    <?php if ($error !== ''): ?>

        <div class="error">
            ⚠️ <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>


    <!-- =====================================================
         AJOUT UTILISATEUR
         ===================================================== -->

    <div class="card">

        <h3>➕ Ajouter un utilisateur</h3>

        <form method="POST">

            <input
                type="hidden"
                name="action"
                value="add_user"
            >

            <div class="form-grid">

                <div class="form-group">

                    <label for="username">
                        Nom d'utilisateur *
                    </label>

                    <input
                        type="text"
                        name="username"
                        id="username"
                        placeholder="Ex : reception"
                        required
                        minlength="3"
                    >

                </div>

                <div class="form-group">

                    <label for="role_id">
                        Rôle *
                    </label>

                    <select
                        name="role_id"
                        id="role_id"
                        required
                    >

                        <option value="">
                            -- Sélectionner un rôle --
                        </option>

                        <?php foreach ($roles as $role): ?>

                            <option
                                value="<?= (int)$role['id'] ?>"
                            >
                                <?= htmlspecialchars($role['name']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="form-group">

    <label for="password">
        Mot de passe *
    </label>

    <div class="password-wrapper">

        <input
            type="password"
            name="password"
            id="password"
            placeholder="Minimum 6 caractères"
            minlength="6"
            required
        >

        <button
            type="button"
            class="password-toggle"
            onclick="togglePassword('password', this)"
            title="Afficher le mot de passe"
        >
            👁️
        </button>

    </div>

</div>



            </div>

            <button
                type="submit"
                class="btn btn-success"
            >
                ➕ Ajouter l'utilisateur
            </button>

        </form>

    </div>


    <!-- =====================================================
         LISTE
         ===================================================== -->

    <div class="card">

        <h3>📋 Utilisateurs de l'hôtel</h3>

        <?php if (count($users) === 0): ?>

            <p>
                Aucun utilisateur enregistré.
            </p>

        <?php else: ?>

            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>

                            <th>Nom d'utilisateur</th>

                            <th>Rôle</th>

                            <th>Statut</th>

                            <th>Créé le</th>

                            <th>Actions</th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php foreach ($users as $user): ?>

                        <tr>

                            <td>
                                <strong>
                                    <?= htmlspecialchars(
                                        $user['username']
                                    ) ?>
                                </strong>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $user['role_name']
                                ) ?>
                            </td>

                            <td>

                                <?php if ($user['status'] === 'active'): ?>

                                    <span class="badge badge-active">
                                        Actif
                                    </span>

                                <?php else: ?>

                                    <span class="badge badge-inactive">
                                        Inactif
                                    </span>

                                <?php endif; ?>

                            </td>

                            <td>

                                <?= date(
                                    'd/m/Y H:i',
                                    strtotime($user['created_at'])
                                ) ?>

                            </td>

                            <td>

                                <div class="actions">

                                    <button
                                        type="button"
                                        class="btn btn-primary"
                                        onclick='editUser(
                                            <?= json_encode($user['id']) ?>,
                                            <?= json_encode($user['username']) ?>,
                                            <?= json_encode($user['role_id']) ?>
                                        )'
                                    >
                                        ✏️ Modifier
                                    </button>

                                    <?php if (
                                        (int)$user['id'] !==
                                        (int)($_SESSION['user_id'] ?? 0)
                                    ): ?>

                                        <form
                                            method="POST"
                                            onsubmit="return confirm(
                                                'Voulez-vous vraiment modifier le statut de cet utilisateur ?'
                                            );"
                                        >

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="toggle_status"
                                            >

                                            <input
                                                type="hidden"
                                                name="user_id"
                                                value="<?= (int)$user['id'] ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="btn
                                                <?= $user['status'] === 'active'
                                                    ? 'btn-danger'
                                                    : 'btn-success'
                                                ?>"
                                            >

                                                <?= $user['status'] === 'active'
                                                    ? 'Désactiver'
                                                    : 'Activer'
                                                ?>

                                            </button>

                                        </form>

                                    <?php endif; ?>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </div>


    <!-- =====================================================
         MODIFICATION
         ===================================================== -->

    <div
        class="card"
        id="editCard"
        style="display:none;"
    >

        <h3>✏️ Modifier l'utilisateur</h3>

        <form method="POST">

            <input
                type="hidden"
                name="action"
                value="edit_user"
            >

            <input
                type="hidden"
                name="user_id"
                id="edit_user_id"
            >

            <div class="form-grid">

                <div class="form-group">

                    <label for="edit_username">
                        Nom d'utilisateur *
                    </label>

                    <input
                        type="text"
                        name="username"
                        id="edit_username"
                        required
                        minlength="3"
                    >

                </div>

                <div class="form-group">

                    <label for="edit_role_id">
                        Rôle *
                    </label>

                    <select
                        name="role_id"
                        id="edit_role_id"
                        required
                    >

                        <?php foreach ($roles as $role): ?>

                            <option
                                value="<?= (int)$role['id'] ?>"
                            >
                                <?= htmlspecialchars($role['name']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="form-group">

                    <label for="edit_password">
                        Nouveau mot de passe
                    </label>

                    <input
                        type="password"
                        name="password"
                        id="edit_password"
                        placeholder="Laisser vide pour conserver l'ancien"
                        minlength="6"
                    >

                </div>

            </div>

            <button
                type="submit"
                class="btn btn-primary"
            >
                💾 Enregistrer les modifications
            </button>

            <button
                type="button"
                class="btn btn-danger"
                onclick="closeEdit()"
            >
                Annuler
            </button>

        </form>

    </div>

</main>

<footer class="footer">

    Hotel Flow © <?= date('Y') ?>

</footer>

<script>

function editUser(id, username, roleId) {

    document.getElementById('editCard').style.display = 'block';

    document.getElementById('edit_user_id').value = id;

    document.getElementById('edit_username').value = username;

    document.getElementById('edit_role_id').value = roleId;

    document.getElementById('edit_password').value = '';

    document.getElementById('editCard').scrollIntoView({
        behavior: 'smooth',
        block: 'start'
    });
}

function closeEdit() {

    document.getElementById('editCard').style.display = 'none';

}

</script>
<script>

function togglePassword(inputId, button) {

    const input = document.getElementById(inputId);

    if (!input) {
        return;
    }

    if (input.type === 'password') {

        input.type = 'text';

        button.textContent = '🙈';
        button.title = 'Masquer le mot de passe';

    } else {

        input.type = 'password';

        button.textContent = '👁️';
        button.title = 'Afficher le mot de passe';

    }
}

</script>


</body>

</html>

