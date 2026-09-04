<?php /* |-------------------------------------------------------------------------- | SESSION |-------------------------------------------------------------------------- */ if (session_status() === PHP_SESSION_NONE) { session_start(); } /* |-------------------------------------------------------------------------- | BASE DE DONNÉES |-------------------------------------------------------------------------- */ require_once __DIR__ . '/../config/database.php'; /* |-------------------------------------------------------------------------- | VARIABLES |-------------------------------------------------------------------------- */ $error = ''; $username = ''; $loginSuccess = false; $redirectUrl = ''; /* |-------------------------------------------------------------------------- | TRAITEMENT DE LA CONNEXION |-------------------------------------------------------------------------- */ if ($_SERVER['REQUEST_METHOD'] === 'POST') { $username = trim($_POST['username'] ?? ''); $password = $_POST['password'] ?? ''; if ($username === '' || $password === '') { $error = 'Veuillez remplir tous les champs.'; } else { try { /* |-------------------------------------------------------------------------- | RECHERCHE UTILISATEUR |-------------------------------------------------------------------------- */ $sql = " SELECT id, hotel_id, role_id, first_name, last_name, username, password_hash, status FROM users WHERE username = :username LIMIT 1 "; $stmt = $pdo->prepare($sql); $stmt->execute([ ':username' => $username ]); $user = $stmt->fetch(PDO::FETCH_ASSOC); /* |-------------------------------------------------------------------------- | UTILISATEUR INTROUVABLE |-------------------------------------------------------------------------- */ if (!$user) { $error = 'Nom d’utilisateur ou mot de passe incorrect.'; } /* |-------------------------------------------------------------------------- | COMPTE INACTIF |-------------------------------------------------------------------------- */ elseif ($user['status'] !== 'active') { $error = 'Votre compte est désactivé.'; } /* |-------------------------------------------------------------------------- | MOT DE PASSE INCORRECT |-------------------------------------------------------------------------- */ elseif (!password_verify( $password, $user['password_hash'] )) { $error = 'Nom d’utilisateur ou mot de passe incorrect.'; } /* |-------------------------------------------------------------------------- | CONNEXION RÉUSSIE |-------------------------------------------------------------------------- */ else { /* | Nouvelle session */ session_regenerate_id(true); /* | Données de session */ $_SESSION['user_id'] = (int) $user['id']; $_SESSION['hotel_id'] = (int) $user['hotel_id']; $_SESSION['role_id'] = (int) $user['role_id']; $_SESSION['first_name'] = $user['first_name']; $_SESSION['last_name'] = $user['last_name']; $_SESSION['username'] = $user['username']; /* |-------------------------------------------------------------------------- | DÉTERMINER LA DESTINATION |-------------------------------------------------------------------------- */ if ((int) $user['role_id'] === 1) { $redirectUrl = '/flux_hotel/admin/dashboard.php'; $loginSuccess = true; } elseif ((int) $user['role_id'] === 2) { $redirectUrl = '/flux_hotel/reception/dashboard.php'; $loginSuccess = true; } else { session_unset(); session_destroy(); $error = 'Votre rôle utilisateur n’est pas reconnu.'; } } } catch (PDOException $e) { /* | En production, éviter d'afficher directement | le message SQL à l'utilisateur. */ $error = 'Une erreur est survenue lors de la connexion.'; } } } /* |-------------------------------------------------------------------------- | SI CONNEXION RÉUSSIE |-------------------------------------------------------------------------- | | On affiche une animation avant d'aller au dashboard. |-------------------------------------------------------------------------- */ if ($loginSuccess === true): ?> <!DOCTYPE html> <html lang="fr"> <head> <meta charset="UTF-8">
<meta
name="viewport"
content="width=device-width, initial-scale=1.0"

<meta name="theme-color" content="#1e3a5f"> <title>Hotel Flow - Chargement</title> <style> /* |-------------------------------------------------------------------------- | RESET |-------------------------------------------------------------------------- */ * { box-sizing: border-box; } html, body { margin: 0; padding: 0; width: 100%; height: 100%; } /* |-------------------------------------------------------------------------- | ÉCRAN DE CHARGEMENT |-------------------------------------------------------------------------- */ body { background: linear-gradient( 135deg, #1e3a5f 0%, #28527a 50%, #1e3a5f 100% ); font-family: Arial, Helvetica, sans-serif; color: white; display: flex; align-items: center; justify-content: center; overflow: hidden; } /* |-------------------------------------------------------------------------- | CONTENEUR |-------------------------------------------------------------------------- */ .loading-container { text-align: center; width: 90%; max-width: 420px; animation: fadeIn .5s ease; } /* |-------------------------------------------------------------------------- | LOGO |-------------------------------------------------------------------------- */ .loading-logo { width: 90px; height: 90px; margin: 0 auto 20px; border-radius: 50%; background: rgba(255,255,255,.12); display: flex; align-items: center; justify-content: center; font-size: 45px; box-shadow: 0 10px 35px rgba(0,0,0,.20); animation: logoPulse 1.5s ease-in-out infinite; } /* |-------------------------------------------------------------------------- | TITRE |-------------------------------------------------------------------------- */ .loading-title { font-size: 28px; font-weight: bold; margin-bottom: 8px; } .loading-text { font-size: 15px; color: #dbe7f5; margin-bottom: 25px; } /* |-------------------------------------------------------------------------- | SPINNER |-------------------------------------------------------------------------- */ .spinner { width: 45px; height: 45px; margin: 0 auto 20px; border-radius: 50%; border: 4px solid rgba(255,255,255,.25); border-top-color: white; animation: spin .8s linear infinite; } /* |-------------------------------------------------------------------------- | BARRE DE PROGRESSION |-------------------------------------------------------------------------- */ .progress { width: 100%; height: 5px; background: rgba(255,255,255,.18); border-radius: 10px; overflow: hidden; } .progress-bar { height: 100%; width: 0%; background: white; border-radius: 10px; animation: progress 1.5s ease forwards; } /* |-------------------------------------------------------------------------- | ANIMATIONS |-------------------------------------------------------------------------- */ @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } } @keyframes logoPulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.08); } } @keyframes progress {

    from {
        width: 0%;
    }

    to {
        width: 100%;
    }

} @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } } /* |-------------------------------------------------------------------------- | MOBILE |-------------------------------------------------------------------------- */ @media (max-width: 600px) { .loading-logo { width: 80px; height: 80px; font-size: 40px; } .loading-title { font-size: 24px; } } </style> </head> <body> <div class="loading-container">
<div class="loading-logo">

    🏨

</div>


<div class="loading-title">

    

</div>


<div class="loading-text">

    Connexion réussie...

    <br>

    Préparation de votre espace

</div>


<div class="spinner"></div>


<div class="progress">

    <div class="progress-bar"></div>

</div>

</div> <script> /* |-------------------------------------------------------------------------- | REDIRECTION APRÈS L'ANIMATION |-------------------------------------------------------------------------- */ setTimeout(function () { window.location.href = <?= json_encode($redirectUrl) ?>; }, 4000); </script> </body> </html> <?php exit; endif; ?> <!DOCTYPE html> <html lang="fr"> <head> <meta charset="UTF-8">
<meta
name="viewport"
content="width=device-width, initial-scale=1.0"

<link rel="manifest" href="/flux_hotel/manifest.json" >
<meta
name="theme-color"
content="#1e3a5f"

<meta
name="mobile-web-app-capable"
content="yes"

<meta
name="apple-mobile-web-app-capable"
content="yes"

<meta
name="apple-mobile-web-app-title"
content="Hotel Flow"

<title></title> 
<style> 
/* |-------------------------------------------------------------------------- | RESET |-------------------------------------------------------------------------- */
 * { box-sizing: border-box; } html, body { margin: 0; padding: 0; width: 100%; min-height: 100%; } body { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; background: #f4f6f9; font-family: Arial, Helvetica, sans-serif; } /* |-------------------------------------------------------------------------- | CARTE |-------------------------------------------------------------------------- */
 .login-card { width: 100%; max-width: 420px; background: white; padding: 35px; border-radius: 15px; box-shadow: 0 5px 25px rgba(0, 0, 0, .10); } /* |-------------------------------------------------------------------------- | LOGO |-------------------------------------------------------------------------- */ 
 .logo { text-align: center; margin-bottom: 15px; } h1 { margin: 0; text-align: center; color: #1e3a5f; font-size: 28px; } 
 .logo img {
    width: 90px;
    height: 90px;
    object-fit: contain;
    display: block;
    margin: 0 auto;
}
loading-screen {
    position: fixed;
    inset: 0;
    background: #1e3a5f;
    color: white;

    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;

    z-index: 99999;

    opacity: 1;
    visibility: visible;

    transition: opacity .6s ease, visibility .6s ease;
}

#loading-screen.hide {
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
}

.loading-logo {
    margin-bottom: 20px;
}

.loading-logo img {
    width: 120px;
    height: 120px;
    object-fit: contain;

    animation: logoPulse 1.5s infinite ease-in-out;
}

#loading-screen h2 {
    margin: 5px 0;
    font-size: 28px;
}

#loading-screen p {
    margin: 5px 0 25px;
    color: #dbe7f5;
}

.progress-container {
    width: 280px;
    max-width: 80%;
    height: 7px;

    background: rgba(255,255,255,.25);

    border-radius: 10px;
    overflow: hidden;
}

#progress-bar {
    width: 0%;
    height: 100%;

    background: white;

    border-radius: 10px;
}

#progress-text {
    margin-top: 10px;
    font-size: 14px;
}
 .subtitle { text-align: center; color: #777; margin-top: 8px; margin-bottom: 28px; } /* |-------------------------------------------------------------------------- | ERREUR |-------------------------------------------------------------------------- */ .error { background: #f8d7da; color: #842029; padding: 13px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; line-height: 1.5; } 
/* |-------------------------------------------------------------------------- | FORMULAIRE |-------------------------------------------------------------------------- */ 
.form-group { margin-bottom: 18px; } label { display: block; margin-bottom: 7px; color: #333; font-size: 14px; font-weight: bold; } input { width: 100%; height: 47px; padding: 10px 13px; border: 1px solid #ccc; border-radius: 8px; font-size: 16px; background: white; } input:focus { outline: none; border-color: #1e3a5f; box-shadow: 0 0 0 2px rgba(30, 58, 95, .10); } .progress-bar {
    height: 100%;
    width: 0%;

    background: white;

    border-radius: 10px;

    animation: progress 2s ease forwards;
}/* |-------------------------------------------------------------------------- | BOUTON |-------------------------------------------------------------------------- */ button { width: 100%; height: 48px; margin-top: 5px; border: none; border-radius: 8px; background: #1e3a5f; color: white; font-size: 16px; font-weight: bold; cursor: pointer; transition: background .2s, transform .1s; } button:hover { background: #162d49; } button:active { transform: scale(.98); } /* |-------------------------------------------------------------------------- | MOBILE |-------------------------------------------------------------------------- */ @media (max-width: 600px) { body { padding: 12px; } .login-card { padding: 25px 18px; } h1 { font-size: 24px; } } </style> </head> <body> <div class="login-card">
<div class="logo">
    <img src="/flux_hotel/icons/logo.png" alt="Hotel Flow">
</div>
<div id="loading-screen">

    <div class="loading-logo">
        <img src="/flux_hotel/icons/icon-192.png" alt="Hotel Flow">
    </div>

    <h2>Hotel Flow</h2>

    <p>Chargement de votre espace...</p>

    <div class="progress-container">
        <div id="progress-bar"></div>
    </div>

    <div id="progress-text">0%</div>

</div>


<?php if ($error !== ''): ?>

    <div class="error">

        ⚠️

        <?= htmlspecialchars($error) ?>

    </div>

<?php endif; ?>


<form
    method="POST"
    action=""
    autocomplete="on"
    id="loginForm"
>


    <div class="form-group">

        <label for="username">

            Nom d'utilisateur

        </label>

        <input
            type="text"
            id="username"
            name="username"
            value="<?= htmlspecialchars($username) ?>"
            autocomplete="username"
            required
        >

    </div>


    <div class="form-group">

        <label for="password">

            Mot de passe

        </label>

        <input
            type="password"
            id="password"
            name="password"
            autocomplete="current-password"
            required
        >

    </div>

    
    <button
        type="submit"
        id="loginButton"
    >

        🔐 Se connecter

    </button>


</form>

</div> <script> /* |-------------------------------------------------------------------------- | ANIMATION DU BOUTON |-------------------------------------------------------------------------- | | Cette animation s'affiche immédiatement après le clic. | La vraie animation de chargement sera ensuite affichée | par PHP lorsque la connexion est validée. |-------------------------------------------------------------------------- */ const loginForm = document.getElementById('loginForm'); const loginButton = document.getElementById('loginButton'); if (loginForm && loginButton) { loginForm.addEventListener( 'submit', function () { loginButton.disabled = true; loginButton.innerHTML = '⏳ Connexion en cours...'; loginButton.style.opacity = '0.8'; loginButton.style.cursor = 'wait'; } ); } 
</script> </body> </html>