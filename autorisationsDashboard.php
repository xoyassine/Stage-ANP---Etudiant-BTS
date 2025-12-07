<?php
session_start();
require_once 'database.php';

// 1. Sécuriser l'accès au tableau de bord
// Vérifier si l'utilisateur est connecté et n'est PAS un admin
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_role'] === 'admin') {
    header("Location: connection.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$autorisations = [];

try {
    // 2. Récupérer toutes les demandes faites par cet utilisateur
    // Utilisation de requêtes préparées pour la sécurité
    $stmt = $pdo->prepare("SELECT a.id, a.idDemande, a.dateAutorisation, a.dateFin, d.superficie, d.activite, d.port, a.fichierPDF
                          FROM autorisation a
                          JOIN demande d ON a.idDemande = d.id 
                          WHERE d.idUtilisateur = ? 
                          ORDER BY a.dateAutorisation DESC");
    $stmt->execute([$user_id]);
    $autorisations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Afficher une erreur si la requête échoue
    $errorMessage = "Erreur lors du chargement des demandes: " . $e->getMessage();
}


?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="src\anp-maroc-seeklogo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <title>AUTORISATIONS DASHBOARD</title>
</head>
<body>
    <nav class="navbar navbar-expand-lg bg-body-tertiary">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
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
                        <a class="nav-link active" aria-current="page" href="#">AUTORISATIONS</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="renouveler_autorisations.php">RENOUVELLER AUTORISATIONS</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php">PROFILE</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-danger" href="logout.php">DECONNEXION</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <h3 class="mb-4">Historique de mes autorisations</h3>
        
        <?php if (isset($errorMessage)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($errorMessage) ?></div>
        <?php elseif (empty($autorisations)): ?>
            <div class="alert alert-info">Vous n'avez pas encore reçu d'autorisation.</div>
        <?php else: ?>
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th scope="col">Numéro</th>
                        <th scope="col">Numéro Demande</th>
                        <th scope="col">Date Autorisation</th>
                        <th scope="col">Date Fin</th>
                        <th scope="col">Superficie</th>
                        <th scope="col">Activité</th>
                        <th scope="col">Port</th>
                        <th scope="col">Voir fichier</th>
                        <th scope="col"> </th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($autorisations as $autorisation): ?>
                        <tr>
                            <th scope="row"><?= htmlspecialchars($autorisation['id']) ?></th>
                            <td><?= htmlspecialchars($autorisation['idDemande']) ?></td>
                            <td><?= htmlspecialchars($autorisation['dateAutorisation']) ?></td>
                            <td><?= htmlspecialchars($autorisation['dateFin']) ?> ans</td>
                            <td><?= htmlspecialchars($autorisation['superficie']) ?></td>
                            <td><?= htmlspecialchars($autorisation['activite']) ?></td>
                            <td><?= htmlspecialchars($autorisation['port']) ?></td>
                            <td>
                                <?php if ($autorisation['fichierPDF']): ?>
                                    <a href="<?= htmlspecialchars($autorisation['fichierPDF']) ?>" target="_blank" class="btn btn-sm btn-info mb-1">Voir PDF</a>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="renouveler_autorisations.php?demande_id=<?= $autorisation['idDemande'] ?>" class="btn btn-sm btn-success mb-1">Renouveler</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>