<?php
session_start();
require_once 'database.php';

$errorMessage = "";

// TRAITEMENT DU FORMULAIRE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $ID = trim($_POST['ID'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($ID === '' || $password === '') {
        $errorMessage = "Veuillez remplir tous les champs.";
    } else {

        // Chercher l'utilisateur
        $stmt = $pdo->prepare("SELECT * FROM utilisateur WHERE id = ?");
        $stmt->execute([$ID]);
        $user = $stmt->fetch();

        if (!$user) {
            $errorMessage = "Aucun compte trouvé avec cet identifiant.";
        } 
        elseif (!password_verify($password, $user['mot_de_passe'])) {
            $errorMessage = "Mot de passe incorrect.";
        } 
        elseif ($user['is_valid'] == 0) {
            $errorMessage = "Votre compte n'est pas encore validé par l'administrateur.";
        } 
        else {
            // Connexion réussie
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['logged_in'] = true;
            $_SESSION['user_role'] = $user['role'];

            // Redirection selon rôle
            if ($user['role'] === 'admin') {
                header("Location: admin_gestion_comptes.php");
            } else {
                header("Location: demandesDashboard.php");
            }
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="src\anp-maroc-seeklogo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <title>Connexion</title>
</head>
<body>
<nav class="navbar bg-body-tertiary">
    <div class="container">
        <a class="navbar-brand" href="#">
            <img src="src\anp-maroc-seeklogo.png" alt="anp" width="45" height="25">
        </a>
    </div>
</nav>
<div class="container mt-5" style="max-width: 500px;">
    
    <h3 class="mb-4 text-center fw-bold">Connexion</h3>

    <?php if (!empty($errorMessage)): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($errorMessage) ?>
        </div>
    <?php endif; ?>

    <form method="post">
        
        <div class="form-floating mb-3">
            <input type="text" class="form-control" id="ID" name="ID" placeholder="CIN/ICE" required>
            <label for="ID">CIN/ICE</label>
        </div>

        <div class="form-floating mb-3">
            <input type="password" class="form-control" id="password" name="password" placeholder="Mot de passe" required>
            <label for="password">Mot de passe</label>
        </div>

        <button class="btn btn-primary w-100" type="submit">Se connecter</button>

        <div class="col-12 mt-3">
            <a href="inscription.php" class="btn btn-outline-secondary w-100">
                Je n’ai pas de compte
            </a>
        </div>

    </form>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
