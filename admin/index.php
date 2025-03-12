<?php
session_start();
require_once("../includes/db_connect.php");
require_once("../includes/functions.php");

// Vérifier si l'utilisateur est connecté et est un administrateur
if (!isLoggedIn() || !isAdmin()) {
    header("Location: ../login.php");
    exit();
}

// Récupérer les statistiques
$stats = [
    'users' => 0,
    'products' => 0,
    'orders' => 0,
    'revenue' => 0
];

// Nombre total d'utilisateurs
$result = $conn->query("SELECT COUNT(*) as count FROM users");
if ($result && $row = $result->fetch_assoc()) {
    $stats['users'] = $row['count'];
}

// Nombre total de produits
$result = $conn->query("SELECT COUNT(*) as count FROM products");
if ($result && $row = $result->fetch_assoc()) {
    $stats['products'] = $row['count'];
}

// Nombre total de commandes
$result = $conn->query("SELECT COUNT(*) as count FROM orders");
if ($result && $row = $result->fetch_assoc()) {
    $stats['orders'] = $row['count'];
}

// Chiffre d'affaires total
$result = $conn->query("SELECT SUM(total_amount) as total FROM orders WHERE payment_status = 'completed'");
if ($result && $row = $result->fetch_assoc()) {
    $stats['revenue'] = $row['total'] ? $row['total'] : 0;
}

// Récupérer les dernières commandes
$recentOrders = [];
$result = $conn->query("SELECT o.id, o.user_id, o.total_amount, o.created_at, o.order_status, u.username 
                        FROM orders o 
                        JOIN users u ON o.user_id = u.id 
                        ORDER BY o.created_at DESC 
                        LIMIT 5");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recentOrders[] = $row;
    }
}

// Récupérer les derniers utilisateurs inscrits
$recentUsers = [];
$result = $conn->query("SELECT id, username, email, role, created_at 
                        FROM users 
                        ORDER BY created_at DESC 
                        LIMIT 5");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recentUsers[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - Omnes MarketPlace</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav id="sidebar" class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <h5 class="text-white">Omnes MarketPlace</h5>
                        <p class="text-white-50">Administration</p>
                    </div>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="index.php">
                                <i class="fas fa-tachometer-alt"></i> Tableau de bord
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="users.php">
                                <i class="fas fa-users"></i> Utilisateurs
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="sellers.php">
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
                            <a class="nav-link" href="reports.php">
                                <i class="fas fa-chart-bar"></i> Rapports
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="settings.php">
                                <i class="fas fa-cog"></i> Paramètres
                            </a>
                        </li>
                    </ul>
                    
                    <hr class="text-white-50">
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="../index.php">
                                <i class="fas fa-home"></i> Retour au site
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../logout.php">
                                <i class="fas fa-sign-out-alt"></i> Déconnexion
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>
            
            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Tableau de bord</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary">Exporter</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary">Imprimer</button>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle">
                            <i class="fas fa-calendar"></i> Cette semaine
                        </button>
                    </div>
                </div>
                
                <!-- Statistics cards -->
                <div class="row">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Utilisateurs</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['users']; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-users fa-2x text-gray-300"></i>
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
                                            Chiffre d'affaires</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['revenue'], 2); ?> €</div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-euro-sign fa-2x text-gray-300"></i>
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
                                            Produits</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['products']; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-box fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Commandes</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['orders']; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-shopping-cart fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent orders and users -->
                <div class="row">
                    <div class="col-lg-6">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold text-primary">Dernières commandes</h6>
                                <a href="orders.php" class="btn btn-sm btn-primary">Voir tout</a>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recentOrders)): ?>
                                    <p class="text-center">Aucune commande récente</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-bordered" width="100%" cellspacing="0">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Client</th>
                                                    <th>Montant</th>
                                                    <th>Statut</th>
                                                    <th>Date</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recentOrders as $order): ?>
                                                    <tr>
                                                        <td><a href="order_details.php?id=<?php echo $order['id']; ?>">#<?php echo $order['id']; ?></a></td>
                                                        <td><?php echo $order['username']; ?></td>
                                                        <td><?php echo number_format($order['total_amount'], 2); ?> €</td>
                                                        <td>
                                                            <?php if ($order['order_status'] == 'pending'): ?>
                                                                <span class="badge bg-warning">En attente</span>
                                                            <?php elseif ($order['order_status'] == 'processing'): ?>
                                                                <span class="badge bg-info">En traitement</span>
                                                            <?php elseif ($order['order_status'] == 'shipped'): ?>
                                                                <span class="badge bg-primary">Expédié</span>
                                                            <?php elseif ($order['order_status'] == 'delivered'): ?>
                                                                <span class="badge bg-success">Livré</span>
                                                            <?php elseif ($order['order_status'] == 'cancelled'): ?>
                                                                <span class="badge bg-danger">Annulé</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?php echo date('d/m/Y', strtotime($order['created_at'])); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-6">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold text-primary">Derniers utilisateurs inscrits</h6>
                                <a href="users.php" class="btn btn-sm btn-primary">Voir tout</a>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recentUsers)): ?>
                                    <p class="text-center">Aucun utilisateur récent</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-bordered" width="100%" cellspacing="0">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Nom d'utilisateur</th>
                                                    <th>Email</th>
                                                    <th>Rôle</th>
                                                    <th>Date d'inscription</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recentUsers as $user): ?>
                                                    <tr>
                                                        <td><?php echo $user['id']; ?></td>
                                                        <td><?php echo $user['username']; ?></td>
                                                        <td><?php echo $user['email']; ?></td>
                                                        <td>
                                                            <?php if ($user['role'] == 'admin'): ?>
                                                                <span class="badge bg-danger">Administrateur</span>
                                                            <?php elseif ($user['role'] == 'seller'): ?>
                                                                <span class="badge bg-success">Vendeur</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-info">Acheteur</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>

