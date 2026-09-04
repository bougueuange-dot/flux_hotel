<?php

session_start();

/*
|--------------------------------------------------------------------------
| HOTEL FLOW - PAGE D'ACCUEIL
|--------------------------------------------------------------------------
| On vérifie simplement si une session existe.
| Sinon, on envoie vers la connexion.
|--------------------------------------------------------------------------
*/

if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {

    // Utilisateur connecté
    if ((int)($_SESSION['role_id'] ?? 0) === 1) {

        header('Location: /flux_hotel/admin/dashboard.php');
        exit;

    }

    if ((int)($_SESSION['role_id'] ?? 0) === 2) {

        header('Location: /flux_hotel/reception/dashboard.php');
        exit;

    }

}

/*
|--------------------------------------------------------------------------
| Aucun utilisateur connecté
|--------------------------------------------------------------------------
*/

header('Location: /flux_hotel/auth/login.php');
exit;
