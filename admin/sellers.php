<?php
session_start();
require_once("../includes/db_connect.php");
require_once("../includes/functions.php");

// Vérifier si l'utilisateur est connecté et est un administrateur
if (!isLoggedIn() || !isAdmin()) {
    header("Location: ../login.php");
    exit();
}

// Initialiser la variable $sellers comme un tableau vide
$sellers = [];

// Vérifier si la colonne 'status' existe dans la table users
$checkColumnQuery = "SHOW COLUMNS FROM users LIKE 'status'";
$columnResult = $conn->query($checkColumnQuery);

// Si la colonne n'existe pas, l'ajouter
if ($columnResult && $columnResult->num_rows == 0) {
    $addColumnQuery = "ALTER TABLE users ADD COLUMN status ENUM('active', 'inactive') NOT NULL DEFAULT 'active'";
    $conn->query($addColumnQuery);
    
    // Mettre à jour tous les utilisateurs existants pour avoir un statut actif par défaut
    $updateStatusQuery = "UPDATE users SET status = 'active'";
    $conn->query($updateStatusQuery);
}

// Récupérer la liste des vendeurs
$query = "SELECT u.*, 
          IFNULL(u.status, 'active') as status,
          COUNT(p.id) as product_count,
          SUM(CASE WHEN p.status = 'active' THEN 1 ELSE 0 END) as active_products
          FROM users u 
          LEFT JOIN products p ON u.id = p.seller_id
          WHERE u.role = 'seller'
          GROUP BY u.id
          ORDER BY u.created_at DESC";

$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $sellers[] = $row;
    }
}

// Gérer la suppression d'un vendeur
if (isset($_POST['delete_seller'])) {
    $seller_id = $_POST['seller_id'];
    $conn->query("DELETE FROM users WHERE id = " . intval($seller_id) . " AND role = 'seller'");
    header("Location: sellers.php");
    exit();
}

// Gérer la mise à jour du statut d'un vendeur
if (isset($_POST['update_seller_status'])) {
    $seller_id = $_POST['seller_id'];
    $status = $_POST['status'];
    $conn->query("UPDATE users SET status = '" . $conn->real_escape_string($status) . "' WHERE id = " . intval($seller_id));
    header("Location: sellers.php");
    exit();
}

// Calculer les statistiques
$active = 0;
$inactive = 0;
$new_sellers = 0;
$total_products = 0;

if (!empty($sellers)) {
    foreach($sellers as $seller) {
        if($seller['status'] == 'active') $active++;
        else $inactive++;
        
        // Considérer comme nouveau si inscrit depuis moins de 30 jours
        $created_date = new DateTime($seller['created_at']);
        $now = new DateTime();
        $interval = $created_date->diff($now);
        if($interval->days < 30) $new_sellers++;
        
        $total_products += $seller['product_count'];
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Vendeurs - Administration</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        /* Style pour la sidebar */
        .sidebar {
            background-color: #343a40;
            min-height: 100vh;
            color: white;
            padding-top: 20px;
        }
        
        .sidebar-header {
            padding: 0 15px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 20px;
        }
        
        .sidebar-header h5 {
            color: white;
            margin-bottom: 5px;
        }
        
        .sidebar-header p {
            color: rgba(255,255,255,0.6);
            margin-bottom: 0;
        }
        
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 10px 15px;
            margin-bottom: 5px;
        }
        
        .sidebar .nav-link:hover, 
        .sidebar .nav-link.active {
            color: white;
            background-color: rgba(255,255,255,0.1);
        }
        
        .sidebar .nav-link i {
            margin-right: 10px;
        }
        
        /* Style pour le contenu principal */
        .main-header {
            margin-bottom: 20px;
            padding: 15px 0;
            border-bottom: 1px solid #dee2e6;
        }
        
        .main-header h1 {
            margin-bottom: 0;
        }
        
        .table-responsive {
            margin-bottom: 30px;
        }
        
        .table thead {
            background-color: #343a40;
            color: white;
        }
        
        .stats-card {
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar simplifiée -->
            <div class="col-md-3 col-lg-2 d-md-block sidebar">
                <div class="sidebar-header">
                    <h5>Omnes MarketPlace</h5>
                    <p>Administration</p>
                </div>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="fas fa-tachometer-alt"></i> Tableau de bord
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="users.php">
                            <i class="fas fa-users"></i> Utilisateurs
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="sellers.php">
                            <i class="fas fa-store"></i> Vendeurs
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="products.php">
                            <i class="fas fa-box"></i> Produits
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="categories.php">
                            <i class="fas fa-tags"></i> Catégories
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="orders.php">
                            <i class="fas fa-shopping-cart"></i> Commandes
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../index.php">
                            <i class="fas fa-home"></i> Retour au site
                        </a>
                    </li>
                </ul>
            </div>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="main-header">
                    <h1 class="h2">Gestion des Vendeurs</h1>
                </div>

                <!-- Table des vendeurs directement en haut -->
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nom d'utilisateur</th>
                                <th>Email</th>
                                <th>Date d'inscription</th>
                                <th>Produits</th>
                                <th>Produits actifs</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($sellers)): ?>
                                <?php foreach ($sellers as $seller): ?>
                                <tr>
                                    <td><?php echo $seller['id']; ?></td>
                                    <td><?php echo htmlspecialchars($seller['username']); ?></td>
                                    <td><?php echo htmlspecialchars($seller['email']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($seller['created_at'])); ?></td>
                                    <td><?php echo $seller['product_count']; ?></td>
                                    <td><?php echo $seller['active_products']; ?></td>
                                    <td>
                                        <?php if ($seller['status'] == 'active'): ?>
                                            <span class="badge text-bg-success">Actif</span>
                                        <?php else: ?>
                                            <span class="badge text-bg-danger">Inactif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="seller_details.php?id=<?php echo $seller['id']; ?>" class="text-decoration-none me-2">Détails</a>
                                        <form action="" method="POST" class="d-inline" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce vendeur ?');">
                                            <input type="hidden" name="seller_id" value="<?php echo $seller['id']; ?>">
                                            <button type="submit" name="delete_seller" class="btn btn-link p-0 text-decoration-none me-2">Supprimer</button>
                                        </form>
                                        <?php if ($seller['status'] != 'active'): ?>
                                        <form action="" method="POST" class="d-inline">
                                            <input type="hidden" name="seller_id" value="<?php echo $seller['id']; ?>">
                                            <input type="hidden" name="status" value="active">
                                            <button type="submit" name="update_seller_status" class="btn btn-link p-0 text-decoration-none me-2">Activer</button>
                                        </form>
                                        <?php else: ?>
                                        <form action="" method="POST" class="d-inline">
                                            <input type="hidden" name="seller_id" value="<?php echo $seller['id']; ?>">
                                            <input type="hidden" name="status" value="inactive">
                                            <button type="submit" name="update_seller_status" class="btn btn-link p-0 text-decoration-none">Désactiver</button>
                                        </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center">Aucun vendeur trouvé</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Cartes statistiques en dessous -->
                <div class="row stats-card">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Total Vendeurs</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo count($sellers); ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-store fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-success shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Vendeurs Actifs</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $active; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-info shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            Nouveaux Vendeurs</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $new_sellers; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-user-plus fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-danger shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                            Vendeurs Inactifs</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $inactive; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-pause-circle fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

