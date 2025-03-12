<?php
session_start();
require_once("../includes/db_connect.php");
require_once("../includes/functions.php");

// Vérifier si l'utilisateur est connecté et est un vendeur
if (!isLoggedIn() || !isSeller()) {
    header("Location: ../login.php");
    exit();
}

// Récupérer les statistiques du vendeur
$seller_id = $_SESSION['user_id'];
$stats = [
    'products' => 0,
    'orders' => 0,
    'revenue' => 0,
    'pending_negotiations' => 0
];

// Nombre total de produits du vendeur
$result = $conn->query("SELECT COUNT(*) as count FROM products WHERE seller_id = $seller_id");
if ($result && $row = $result->fetch_assoc()) {
    $stats['products'] = $row['count'];
}

// Nombre total de commandes pour les produits du vendeur
$result = $conn->query("SELECT COUNT(DISTINCT o.id) as count 
                        FROM orders o 
                        JOIN order_items oi ON o.id = oi.order_id 
                        JOIN products p ON oi.product_id = p.id 
                        WHERE p.seller_id = $seller_id");
if ($result && $row = $result->fetch_assoc()) {
    $stats['orders'] = $row['count'];
}

// Chiffre d'affaires total du vendeur
$result = $conn->query("SELECT SUM(oi.price * oi.quantity) as total 
                        FROM order_items oi 
                        JOIN orders o ON oi.order_id = o.id 
                        JOIN products p ON oi.product_id = p.id 
                        WHERE p.seller_id = $seller_id AND o.payment_status = 'completed'");
if ($result && $row = $result->fetch_assoc()) {
    $stats['revenue'] = $row['total'] ? $row['total'] : 0;
}

// Nombre de négociations en attente
$result = $conn->query("SELECT COUNT(*) as count 
                        FROM negotiations n 
                        JOIN products p ON n.product_id = p.id 
                        WHERE p.seller_id = $seller_id AND n.seller_response IS NULL");
if ($result && $row = $result->fetch_assoc()) {
    $stats['pending_negotiations'] = $row['count'];
}

// Récupérer les dernières commandes
$recentOrders = [];
$result = $conn->query("SELECT o.id, o.user_id, o.total_amount, o.created_at, o.order_status, u.username, 
                        GROUP_CONCAT(p.name SEPARATOR ', ') as products 
                        FROM orders o 
                        JOIN order_items oi ON o.id = oi.order_id 
                        JOIN products p ON oi.product_id = p.id 
                        JOIN users u ON o.user_id = u.id 
                        WHERE p.seller_id = $seller_id 
                        GROUP BY o.id 
                        ORDER BY o.created_at DESC 
                        LIMIT 5");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recentOrders[] = $row;
    }
}

// Récupérer les dernières négociations
$recentNegotiations = [];
$result = $conn->query("SELECT n.id, n.product_id, n.user_id, n.amount, n.message, n.created_at, 
                        p.name as product_name, u.username 
                        FROM negotiations n 
                        JOIN products p ON n.product_id = p.id 
                        JOIN users u ON n.user_id = u.id 
                        WHERE p.seller_id = $seller_id AND n.seller_response IS NULL 
                        ORDER BY n.created_at DESC 
                        LIMIT 5");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recentNegotiations[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Espace Vendeur - Omnes MarketPlace</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/seller.css">
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
                        <p class="text-white-50">Espace Vendeur</p>
                    </div>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="index.php">
                                <i class="fas fa-tachometer-alt"></i> Tableau de bord
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="order_details.php">
                                <i class="fas fa-box"></i> Mes produits
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="products.php">
                                <i class="fas fa-plus"></i> Ajouter un produit
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="orders.php">
                                <i class="fas fa-shopping-cart"></i> Commandes
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="negotiations.php">
                                <i class="fas fa-comments-dollar"></i> Négociations
                                <?php if ($stats['pending_negotiations'] > 0): ?>
                                    <span class="badge bg-danger"><?php echo $stats['pending_negotiations']; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="auctions.php">
                                <i class="fas fa-gavel"></i> Enchères
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
                    <h1 class="h2">Tableau de bord vendeur</h1>
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
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Négociations en attente</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['pending_negotiations']; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-comments-dollar fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent orders and negotiations -->
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
                                                    <th>Produits</th>
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
                                                        <td><?php echo substr($order['products'], 0, 30) . (strlen($order['products']) > 30 ? '...' : ''); ?></td>
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
                                <h6 class="m-0 font-weight-bold text-primary">Négociations en attente</h6>
                                <a href="negotiations.php" class="btn btn-sm btn-primary">Voir tout</a>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recentNegotiations)): ?>
                                    <p class="text-center">Aucune négociation en attente</p>
                                <?php else: ?>
                                    <div class="list-group">
                                        <?php foreach ($recentNegotiations as $negotiation): ?>
                                            <a href="negotiation_details.php?id=<?php echo $negotiation['id']; ?>" class="list-group-item list-group-item-action">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <h5 class="mb-1"><?php echo $negotiation['product_name']; ?></h5>
                                                    <small><?php echo date('d/m/Y H:i', strtotime($negotiation['created_at'])); ?></small>
                                                </div>
                                                <p class="mb-1">Offre de <strong><?php echo $negotiation['username']; ?></strong>: <?php echo $negotiation['amount']; ?> €</p>
                                                <?php if (!empty($negotiation['message'])): ?>
                                                    <small class="text-muted"><?php echo substr($negotiation['message'], 0, 100) . (strlen($negotiation['message']) > 100 ? '...' : ''); ?></small>
                                                <?php endif; ?>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick actions -->
                <div class="row">
                    <div class="col-lg-12">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Actions rapides</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <a href="add_products.php" class="btn btn-primary btn-block">
                                            <i class="fas fa-plus"></i> Ajouter un produit
                                        </a>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <a href="negotiations.php" class="btn btn-success btn-block">
                                            <i class="fas fa-comments-dollar"></i> Gérer les négociations
                                        </a>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <a href="orders.php" class="btn btn-info btn-block">
                                            <i class="fas fa-shipping-fast"></i> Gérer les expéditions
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>

