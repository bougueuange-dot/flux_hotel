<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';


/*
|--------------------------------------------------------------------------
| VÉRIFICATION DE CONNEXION
|--------------------------------------------------------------------------
*/

if (!isLoggedIn()) {

    http_response_code(401);

    echo json_encode([
        'success' => false,
        'message' => 'Non connecté.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| VÉRIFICATION DU RÔLE
|--------------------------------------------------------------------------
*/

if ((int) ($_SESSION['role_id'] ?? 0) !== 2) {

    http_response_code(403);

    echo json_encode([
        'success' => false,
        'message' => 'Accès interdit.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| HÔTEL CONNECTÉ
|--------------------------------------------------------------------------
*/

$hotelId = (int) ($_SESSION['hotel_id'] ?? 0);


/*
|--------------------------------------------------------------------------
| CONFIGURATION JSON
|--------------------------------------------------------------------------
*/

header('Content-Type: application/json; charset=utf-8');


/*
|--------------------------------------------------------------------------
| RECHERCHE
|--------------------------------------------------------------------------
*/

$search = trim($_GET['q'] ?? '');


/*
|--------------------------------------------------------------------------
| RECHERCHE TROP COURTE
|--------------------------------------------------------------------------
*/

if (mb_strlen($search) < 2) {

    echo json_encode([
        'success' => true,
        'clients' => []
    ]);

    exit;
}


try {

    /*
    |--------------------------------------------------------------------------
    | RECHERCHE UNIQUEMENT DANS L'HÔTEL CONNECTÉ
    |--------------------------------------------------------------------------
    */

    $sql = "
        SELECT

            id,

            first_name,

            last_name,

            cni_number,

            phone

        FROM clients

        WHERE hotel_id = :hotel_id

        AND (

            first_name LIKE :search

            OR last_name LIKE :search

            OR cni_number LIKE :search

            OR phone LIKE :search

            OR CONCAT(first_name, ' ', last_name) LIKE :search

            OR CONCAT(last_name, ' ', first_name) LIKE :search

        )

        ORDER BY

            last_name ASC,

            first_name ASC

        LIMIT 10
    ";


    $stmt = $pdo->prepare($sql);


    $searchValue = '%' . $search . '%';


    $stmt->execute([

        ':hotel_id' => $hotelId,

        ':search' => $searchValue

    ]);


    $clients = $stmt->fetchAll();


    echo json_encode([

        'success' => true,

        'clients' => $clients

    ]);


} catch (PDOException $e) {


    http_response_code(500);


    echo json_encode([

        'success' => false,

        'message' => 'Erreur lors de la recherche.'

    ]);

}