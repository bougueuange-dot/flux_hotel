<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo '<h1>TEST SESSION HOTEL FLOW</h1>';

echo '<pre>';

print_r($_SESSION);

echo '</pre>';

echo '<hr>';

echo 'Session ID : ';

echo session_id();

echo '<hr>';

echo 'User ID : ';

echo $_SESSION['user_id'] ?? 'ABSENT';

echo '<br>';

echo 'Hotel ID : ';

echo $_SESSION['hotel_id'] ?? 'ABSENT';

echo '<br>';

echo 'Role ID : ';

echo $_SESSION['role_id'] ?? 'ABSENT';

echo '<br>';

echo 'Prénom : ';

echo $_SESSION['first_name'] ?? 'ABSENT';

echo '<br>';

echo 'Nom : ';

echo $_SESSION['last_name'] ?? 'ABSENT';

echo '<hr>';

if (
    isset($_SESSION['user_id']) &&
    isset($_SESSION['hotel_id']) &&
    isset($_SESSION['role_id'])
) {

    echo '<h2 style="color:green;">
        ✅ SESSION OK
    </h2>';

} else {

    echo '<h2 style="color:red;">
        ❌ SESSION INCOMPLÈTE
    </h2>';

}