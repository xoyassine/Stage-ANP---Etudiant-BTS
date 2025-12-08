<?php
session_start();
require_once 'database.php';

// Vérifier si admin connecté
if (!isset($_SESSION['logged_in']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: connection.php");
    exit;
}

// Valider un utilisateur
if (isset($_GET['validate_id'])) {
    $stmt = $pdo->prepare("UPDATE utilisateur SET is_valid = 1 WHERE id = ?");
    $stmt->execute([$_GET['validate_id']]);
    header("Location: admin_gestion_comptes.php");
    exit;
}

// Rejeter un utilisateur
if (isset($_GET['rejeter_id'])) {
    $stmt = $pdo->prepare("UPDATE utilisateur SET is_valid = -1 WHERE id = ?");
    $stmt->execute([$_GET['rejeter_id']]);
    header("Location: admin_gestion_comptes.php");
    exit;
}

// Filtre par rôle (client_physique / client_morale)
$filterRole = $_GET['role'] ?? '';
$sql = "SELECT * FROM utilisateur WHERE role != 'admin' ";
$params = [];

if ($filterRole === 'client_physique' || $filterRole === 'client_morale') {
    $sql .= " AND role = ?";
    $params[] = $filterRole;
}

// Filtre par validité (0 = en attente, 1 = validé, -1 = rejeté)
$isvalidfilter = $_GET['isvalid'] ?? '';
if ($isvalidfilter === '0' || $isvalidfilter === '1' || $isvalidfilter === '-1') {
    $sql .= " AND is_valid = ?";
    $params[] = $isvalidfilter;
}

// Recherche par ID (CIN/ICE)

$idsearch = $_GET['idsearch'] ?? '';
if (!empty($idsearch)) {
    $sql .= " AND id LIKE ?";
    $params[] = '%' . $idsearch . '%';
}

$sql .= " ORDER BY nom DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Admin - Vérification des comptes</title>
    <link rel="icon" type="image/x-icon" href="src\anp-maroc-seeklogo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
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
                    <a class="nav-link active" aria-current="page" href="#">GESTION COMPTES</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="admin_gestion_demandes.php">GESTION DEMANDES ET AUTORISATION</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-danger" href="logout.php">DECONNEXION</a>
                </li>
            </ul>
        </div>
    </div>
</nav>
<div class="container mt-5">
    <h3 class="mb-4">Gestion des comptes clients</h3>

    <div class="mb-3">
        <form method="get" class="form-inline">
            <label for="IdSearch" class="me-2">Rechercher par Id : </label>
            <input type="text" name="idsearch" id="IdSearch" class="form-control w-auto d-inline-block me-2" value="<?= htmlspecialchars($_GET['idsearch'] ?? '') ?>">
            <label for="roleFilter" class="me-2">Filtrer par rôle :</label>
            <select name="role" id="roleFilter" class="form-select w-auto d-inline-block me-2">
                <option value="">Tous</option>
                <option value="client_physique" <?= $filterRole === 'client_physique' ? 'selected' : '' ?>>Client Physique</option>
                <option value="client_morale" <?= $filterRole === 'client_morale' ? 'selected' : '' ?>>Client Morale</option>
            </select>
            <label for="isvalidFilter" class="me-2">Filtrer par Validité :</label>
            <select name="isvalid" id="isvalidFilter" class="form-select w-auto d-inline-block me-2">
                <option value="">Tous</option>
                <option value=0 <?= $isvalidfilter === 0 ? 'selected' : '' ?>>En attente</option>
                <option value=1 <?= $isvalidfilter === 1 ? 'selected' : '' ?>>Validé</option>
                <option value=-1 <?= $isvalidfilter === -1 ? 'selected' : '' ?>>Refusée</option>
            </select>
            <button type="submit" class="btn btn-primary btn-sm">Filtrer</button>
        </form>
    </div>

    <?php if (empty($users)): ?>
        <div class="alert alert-info">Aucun utilisateur trouvé.</div>
    <?php else: ?>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>id (CIN/ICE)</th>
                    <th>Date Inscription</th>
                    <th>Nom</th>
                    <th>Prénom</th>
                    <th>Email</th>
                    <th>Téléphone</th>
                    <th>Rôle</th>
                    <th>Validé ?</th>
                    <th>Fichiers</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?= htmlspecialchars($u['id']) ?></td>
                        <td><?= htmlspecialchars($u['date_inscription']) ?></td>
                        <td><?= htmlspecialchars($u['nom']) ?></td>
                        <td><?= htmlspecialchars($u['prenom']) ?></td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td><?= htmlspecialchars($u['telephone']) ?></td>
                        <td><?= htmlspecialchars($u['role']) ?></td>
                        <td>
                            <?php
                                if ($u['is_valid'] == 1) {
                                    echo '<span class="badge bg-success">Validé</span>';
                                } elseif ($u['is_valid'] == -1) {
                                    echo '<span class="badge bg-danger">Rejeté</span>';
                                } else {
                                    echo '<span class="badge bg-warning text-dark">En Attente</span>';
                                }
                            ?>
                        <td>
                            <?php
                            if ($u['role'] === 'client_physique') {
                                $stmtF = $pdo->prepare("SELECT docCIN, docCINVerso FROM client_physique WHERE id = ?");
                                $stmtF->execute([$u['id']]);
                                $phys = $stmtF->fetch();
                                if ($phys) {
                                    if ($phys['docCIN']) echo '<a href="'.htmlspecialchars($phys['docCIN']).'" target="_blank">CIN Recto</a><br>';
                                    if ($phys['docCINVerso']) echo '<a href="'.htmlspecialchars($phys['docCINVerso']).'" target="_blank">CIN Verso</a>';
                                } else { echo 'Aucun fichier'; }
                            } elseif ($u['role'] === 'client_morale') {
                                $stmtF = $pdo->prepare("SELECT docRegistreCommerce FROM client_morale WHERE id = ?");
                                $stmtF->execute([$u['id']]);
                                $mor = $stmtF->fetch();
                                if ($mor && $mor['docRegistreCommerce']) {
                                    echo '<a href="'.htmlspecialchars($mor['docRegistreCommerce']).'" target="_blank">Voir registre</a>';
                                } else { echo 'Aucun fichier'; }
                            } else { echo '-'; }
                            ?>
                        </td>
                        <td>
                            <?php if ($u['is_valid'] == 0 || $u['is_valid'] == -1): ?>
                                <a href="admin_gestion_comptes.php?validate_id=<?= urlencode($u['id']) ?>" class="btn btn-success btn-sm mb-1">Valider</a>
                            <?php endif; ?>
                            <a href="admin_gestion_comptes.php?rejeter_id=<?= urlencode($u['id']) ?>" class="btn btn-danger btn-sm" onclick="return confirm('Voulez-vous vraiment rejeter ce compte ?')">Rejeter</a>
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
