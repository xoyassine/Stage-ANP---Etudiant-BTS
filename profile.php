<?php
session_start();
require_once 'database.php';

// 1. Sécuriser l'accès
// Vérifier si l'utilisateur est connecté et est un client (non admin)
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_role'] === 'admin') {
    header("Location: connection.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];
$userData = null;
$clientData = null;
$errorMessage = "";

try {
    // 2. Récupérer les informations de base de l'utilisateur
    $stmtUser = $pdo->prepare("SELECT id, nom, prenom, email, telephone, adresse, role, is_valid FROM utilisateur WHERE id = ?");
    $stmtUser->execute([$user_id]);
    $userData = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$userData) {
        $errorMessage = "Erreur: Profil utilisateur introuvable.";
    }

    // 3. Récupérer les informations spécifiques au rôle (client_physique ou client_morale)
    if ($userData && $user_role === 'client_physique') {
        $stmtClient = $pdo->prepare("SELECT numeroCIN, docCIN, docCINVerso FROM client_physique WHERE id = ?");
        $stmtClient->execute([$user_id]);
        $clientData = $stmtClient->fetch(PDO::FETCH_ASSOC);
    } elseif ($userData && $user_role === 'client_morale') {
        $stmtClient = $pdo->prepare("SELECT ICE, docRegistreCommerce, raisonSocial, secteurActivite FROM client_morale WHERE id = ?");
        $stmtClient->execute([$user_id]);
        $clientData = $stmtClient->fetch(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    $errorMessage = "Erreur de base de données : " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <title>Mon Profil</title>
</head>
<body>
    <nav class="navbar navbar-expand-lg bg-body-tertiary">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <img src="src\anp-maroc-seeklogo.png" alt="ANP LOGO" width="45" height="25">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="demandesDashboard.php">DEMANDES</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="nouvelle_demande.php">NOUVELLE DEMANDE</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="autorisationsDashboard.php">AUTORISATIONS</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="profile.php">PROFILE</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-danger" href="logout.php">DECONNEXION</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <h3 class="mb-4 fw-bold">Mon Profil Client</h3>

        <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($errorMessage) ?></div>
        <?php endif; ?>

        <?php if ($userData): ?>
            
            <div class="card mb-4">
                <div class="card-header bg-danger text-white">
                    TOUT LES INFORMATIONS SONT INMODIFIABLES. CONTACTER L'ADMINISTRATEUR POUR TOUTE MODIFICATION.
                </div> 
            </div>

            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    Informations Générales
                </div>
                <div class="card-body">
                    <p><strong>Identifiant (CIN/ICE):</strong> <?= htmlspecialchars($userData['id']) ?></p>
                    <p><strong>Nom:</strong> <?= htmlspecialchars($userData['nom']) ?></p>
                    <p><strong>Prénom:</strong> <?= htmlspecialchars($userData['prenom']) ?></p>
                    <p><strong>Email:</strong> <?= htmlspecialchars($userData['email']) ?></p>
                    <p><strong>Téléphone:</strong> <?= htmlspecialchars($userData['telephone']) ?></p>
                    <p><strong>Adresse:</strong> <?= htmlspecialchars($userData['adresse']) ?></p></div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-secondary text-white">
                    Documents et Détails Spécifiques
                </div>
                <div class="card-body">
                    <?php if ($user_role === 'client_physique' && $clientData): ?>
                        <ul>
                            <li><a href="<?= htmlspecialchars($clientData['docCIN']) ?>" target="_blank">CIN Recto</a></li>
                            <li><a href="<?= htmlspecialchars($clientData['docCINVerso']) ?>" target="_blank">CIN Verso</a></li>
                        </ul>
                    
                    <?php elseif ($user_role === 'client_morale' && $clientData): ?>
                        <p><strong>Raison Sociale:</strong> <?= htmlspecialchars($clientData['raisonSocial']) ?></p>
                        <p><strong>Secteur d'Activité:</strong> <?= htmlspecialchars($clientData['secteurActivite']) ?></p>
                        <p><strong>Document Registre de Commerce:</strong> <a href="<?= htmlspecialchars($clientData['docRegistreCommerce']) ?>" target="_blank">Voir le document</a></p>
                    
                    <?php else: ?>
                        <p>Détails spécifiques au rôle non trouvés.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php endif; ?>
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>