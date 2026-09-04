<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| Vérifier la connexion
|--------------------------------------------------------------------------
*/

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id'])
        && !empty($_SESSION['user_id']);
}


/*
|--------------------------------------------------------------------------
| Vérifier le rôle
|--------------------------------------------------------------------------
*/

function requireRole(int $roleId): void
{
    if (!isLoggedIn()) {

        header('Location: /flux_hotel/auth/login.php');
        exit;

    }

    if ((int)($_SESSION['role_id'] ?? 0) !== $roleId) {

        if ((int)($_SESSION['role_id'] ?? 0) === 1) {

            header('Location: /flux_hotel/admin/dashboard.php');
            exit;

        }

        if ((int)($_SESSION['role_id'] ?? 0) === 2) {

            header('Location: /flux_hotel/reception/dashboard.php');
            exit;

        }

        header('Location: /flux_hotel/auth/login.php');
        exit;

    }
}


/*
|--------------------------------------------------------------------------
| Récupérer l'hôtel connecté
|--------------------------------------------------------------------------
*/

function getHotelId(): int
{
    return (int)($_SESSION['hotel_id'] ?? 0);
}
