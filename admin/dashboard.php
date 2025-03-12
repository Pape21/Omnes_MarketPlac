<?php
session_start();
require_once("../includes/db_connect.php");
require_once("../includes/functions.php");

// Vérifier si l'utilisateur est un administrateur
requireAdmin();

// Statistiques générales
// Nombre total d'utilisateurs
$sql = "SELECT COUNT(*) as total FROM users";
$result = $conn->query($sql);
$total_users = $result->fetch_assoc()['total'];

// Nombre de vendeurs
$sql = "SELECT COUNT(*) as total FROM users WHERE role = 'seller'";
$result = $conn->query($sql);
$total_sellers = $result->fetch_assoc()['total'];

// Nombre de produits
$sql = "SELECT COUNT(*) as total FROM products";
$result = $conn->query($sql);
$total_products = $result->fetch_assoc()['total'];

// Nombre de commandes
$sql = "SELECT COUNT(*) as total FROM orders";
$result = $conn->query($sql);
$total_orders = $result->fetch_assoc()['total'];

// Chiffre d'affaires total
$sql = "SELECT SUM(total) as revenue FROM orders";
$result = $conn->query($sql);
$total_revenue = $result->fetch_assoc()['revenue'] ?: 0;

// Statistiques du jour
$today = date('Y-m-d');

// Nouveaux utilisateurs aujourd'hui
$sql = "SELECT COUNT(*) as total FROM users WHERE DATE(created_at) = '$today'";
$result = $conn->query($sql);
$new_users_today = $result->fetch_assoc()['total'];

// Nouvelles commandes aujourd'hui
$sql = "SELECT COUNT(*) as total FROM orders WHERE DATE(created_at) = '$today'";
$result = $conn->query($sql);
$new_orders_today = $result->fetch_assoc()['total'];

// Chiffre d'affaires du jour
$sql = "SELECT SUM(total) as revenue FROM orders WHERE DATE(created_at) = '$today'";
$result = $conn->query($sql);
$revenue_today = $result->fetch_assoc()['revenue'] ?: 0;

// Demandes vendeur en attente
$sql = "SELECT COUNT(*) as total FROM seller_requests WHERE status = 'pending'";
$result = $conn->query($sql);
$pending_seller_requests = $result->fetch_assoc()['total'];

// Récupérer les dernières commandes
$sql = "SELECT o.*, u.username, u.first_name, u.last_name 
        FROM orders o 
        JOIN users u ON o.user_id = u.id 
        ORDER BY o.created_at DESC 
        LIMIT 5";
$result = $conn->query($sql);
$recent_orders = $result->fetch_all(MYSQLI_ASSOC);

// Récupérer les derniers utilisateurs inscrits
$sql = "SELECT * FROM users ORDER BY created_at DESC LIMIT 5";
$result = $conn->query($sql);
$recent_users = $result->fetch_all(MYSQLI_ASSOC);

// Récupérer les produits les plus vendus
$sql = "SELECT p.id, p.name, p.price, p.image, COUNT(oi.product_id) as sales_count 
        FROM products p 
        JOIN order_items oi ON p.id = oi.product_id 
        GROUP BY p.id 
        ORDER BY sales_count DESC 
        LIMIT 5";
$result = $conn->query($sql);
$top_products = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - Administration</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="../css/admin.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include("includes/admin_header.php"); ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include("includes/admin_sidebar.php"); ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Tableau de bord</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                                <i class="fas fa-print"></i> Imprimer
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="refreshDashboard">
                                <i class="fas fa-sync-alt"></i> Actualiser
                            </button>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle">
                            <i class="fas fa-calendar-alt"></i> Cette semaine
                        </button>
                    </div>
                </div>
                
                <!-- Cartes de statistiques -->
                <div class="row">
                    <div class="col-md-3 col-sm-6 mb-4">
                        <div class="card shadow-sm mb-4">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-muted small text-uppercase mb-1">
                                            Utilisateurs</div>
                                        <div class="h5 mb-0 fw-bold"><?php echo $total_users; ?></div>
                                        <div class="text-xs text-success mt-2">
                                            <i class="fas fa-arrow-up"></i> <?php echo $new_users_today; ?> aujourd'hui
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-users fa-2x opacity-50"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 col-sm-6 mb-4">
                        <div class="card shadow-sm mb-4">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-muted small text-uppercase mb-1">
                                            Chiffre d'affaires</div>
                                        <div class="h5 mb-0 fw-bold"><?php echo number_format($total_revenue, 2, ',', ' '); ?> €</div>
                                        <div class="text-xs text-success mt-2">
                                            <i class="fas fa-arrow-up"></i> <?php echo number_format($revenue_today, 2, ',', ' '); ?> € aujourd'hui
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-euro-sign fa-2x opacity-50"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 col-sm-6 mb-4">
                        <div class="card shadow-sm mb-4">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-muted small text-uppercase mb-1">
                                            Commandes</div>
                                        <div class="h5 mb-0 fw-bold"><?php echo $total_orders; ?></div>
                                        <div class="text-xs text-success mt-2">
                                            <i class="fas fa-arrow-up"></i> <?php echo $new_orders_today; ?> aujourd'hui
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-shopping-cart fa-2x opacity-50"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 col-sm-6 mb-4">
                        <div class="card shadow-sm mb-4">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-muted small text-uppercase mb-1">
                                            Demandes vendeur</div>
                                        <div class="h5 mb-0 fw-bold"><?php echo $pending_seller_requests; ?></div>
                                        <div class="text-xs text-warning mt-2">
                                            <i class="fas fa-clock"></i> En attente
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-store fa-2x opacity-50"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Graphiques -->
                <div class="row">
                    <div class="col-lg-8 mb-4">
                        <div class="card shadow mb-4">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">Aperçu des revenus</h5>
                                <div class="dropdown no-arrow">
                                    <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink"
                                        data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                                    </a>
                                    <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in"
                                        aria-labelledby="dropdownMenuLink">
                                        <div class="dropdown-header">Options:</div>
                                        <a class="dropdown-item" href="#">Dernière semaine</a>
                                        <a class="dropdown-item" href="#">Dernier mois</a>
                                        <a class="dropdown-item" href="#">Dernière année</a>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item" href="reports.php">Voir tous les rapports</a>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="chart-area">
                                    <canvas id="revenueChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4 mb-4">
                        <div class="card shadow mb-4">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">Répartition des ventes</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-pie pt-4">
                                    <canvas id="salesDistributionChart"></canvas>
                                </div>
                                <div class="mt-4 text-center small">
                                    <span class="me-2">
                                        <i class="fas fa-circle text-primary"></i> Produits physiques
                                    </span>
                                    <span class="me-2">
                                        <i class="fas fa-circle text-success"></i> Produits numériques
                                    </span>
                                    <span class="me-2">
                                        <i class="fas fa-circle text-info"></i> Services
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Contenu du tableau de bord -->
                <div class="row">
                    <!-- Dernières commandes -->
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow mb-4">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">Dernières commandes</h5>
                                <a href="orders.php" class="btn btn-sm btn-primary">
                                    Voir tout
                                </a>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>ID</th>
                                                <th>Client</th>
                                                <th>Montant</th>
                                                <th>Statut</th>
                                                <th>Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($recent_orders)): ?>
                                                <tr>
                                                    <td colspan="5" class="text-center py-4">Aucune commande récente</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($recent_orders as $order): ?>
                                                    <tr>
                                                        <td>#<?php echo $order['id']; ?></td>
                                                        <td><?php echo $order['first_name'] . ' ' . $order['last_name']; ?></td>
                                                        <td><?php echo number_format($order['total'], 2, ',', ' '); ?> €</td>
                                                        <td>
                                                            <?php if ($order['status'] === 'pending'): ?>
                                                                <span class="badge bg-warning text-dark">En attente</span>
                                                            <?php elseif ($order['status'] === 'processing'): ?>
                                                                <span class="badge bg-info">En traitement</span>
                                                            <?php elseif ($order['status'] === 'shipped'): ?>
                                                                <span class="badge bg-primary">Expédiée</span>
                                                            <?php elseif ($order['status'] === 'delivered'): ?>
                                                                <span class="badge bg-success">Livrée</span>
                                                            <?php elseif ($order['status'] === 'cancelled'): ?>
                                                                <span class="badge bg-danger">Annulée</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?php echo date('d/m/Y', strtotime($order['created_at'])); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Nouveaux utilisateurs -->
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow mb-4">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">Nouveaux utilisateurs</h5>
                                <a href="users.php" class="btn btn-sm btn-primary">
                                    Voir tout
                                </a>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Utilisateur</th>
                                                <th>Email</th>
                                                <th>Rôle</th>
                                                <th>Date d'inscription</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($recent_users)): ?>
                                                <tr>
                                                    <td colspan="4" class="text-center py-4">Aucun utilisateur récent</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($recent_users as $user): ?>
                                                    <tr>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <?php if (!empty($user['profile_image'])): ?>
                                                                    <img src="<?php echo $user['profile_image']; ?>" class="rounded-circle me-2" width="32" height="32" alt="Profile">
                                                                <?php else: ?>
                                                                    <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px; font-size: 0.8rem;">
                                                                        <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                                                                    </div>
                                                                <?php endif; ?>
                                                                <?php echo $user['username']; ?>
                                                            </div>
                                                        </td>
                                                        <td><?php echo $user['email']; ?></td>
                                                        <td>
                                                            <?php if ($user['role'] === 'admin'): ?>
                                                                <span class="badge bg-danger">Admin</span>
                                                            <?php elseif ($user['role'] === 'seller'): ?>
                                                                <span class="badge bg-success">Vendeur</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-secondary">Acheteur</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Produits les plus vendus -->
                <div class="row">
                    <div class="col-12 mb-4">
                        <div class="card shadow mb-4">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">Produits les plus vendus</h5>
                                <a href="products.php" class="btn btn-sm btn-primary">
                                    Voir tous les produits
                                </a>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php if (empty($top_products)): ?>
                                        <div class="col-12 text-center py-4">Aucun produit vendu</div>
                                    <?php else: ?>
                                        <?php foreach ($top_products as $product): ?>
                                            <div class="col-md-4 col-lg-2 mb-4">
                                                <div class="card h-100">
                                                    <img src="<?php echo $product['image']; ?>" class="card-img-top" alt="<?php echo $product['name']; ?>" style="height: 150px; object-fit: cover;">
                                                    <div class="card-body">
                                                        <h6 class="card-title"><?php echo $product['name']; ?></h6>
                                                        <p class="card-text">
                                                            <strong><?php echo number_format($product['price'], 2, ',', ' '); ?> €</strong><br>
                                                            <span class="text-success"><?php echo $product['sales_count']; ?> ventes</span>
                                                        </p>
                                                        <a href="product-details.php?id=<?php echo $product['id']; ?>" class="btn btn-sm btn-outline-primary">Détails</a>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script>
    // Données pour les graphiques (à remplacer par des données réelles)
    const revenueData = {
        labels: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'],
        datasets: [{
            label: 'Revenus',
            backgroundColor: 'rgba(78, 115, 223, 0.05)',
            borderColor: 'rgba(78, 115, 223, 1)',
            pointBackgroundColor: 'rgba(78, 115, 223, 1)',
            pointBorderColor: '#fff',
            pointHoverBackgroundColor: '#fff',
            pointHoverBorderColor: 'rgba(78, 115, 223, 1)',
            data: [10000, 15000, 12000, 18000, 20000, 25000, 22000, 28000, 30000, 32000, 35000, 40000],
            fill: true
        }]
    };

    const salesDistributionData = {
        labels: ['Produits physiques', 'Produits numériques', 'Services'],
        datasets: [{
            data: [55, 30, 15],
            backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc'],
            hoverBackgroundColor: ['#2e59d9', '#17a673', '#2c9faf'],
            hoverBorderColor: 'rgba(234, 236, 244, 1)',
        }]
    };

    // Initialiser les graphiques
    document.addEventListener('DOMContentLoaded', function() {
        // Graphique des revenus
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        new Chart(revenueCtx, {
            type: 'line',
            data: revenueData,
            options: {
                maintainAspectRatio: false,
                layout: {
                    padding: {
                        left: 10,
                        right: 25,
                        top: 25,
                        bottom: 0
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false,
                            drawBorder: false
                        }
                    },
                    y: {
                        ticks: {
                            callback: function(value) {
                                return value.toLocaleString() + ' €';
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.raw.toLocaleString() + ' €';
                            }
                        }
                    }
                }
            }
        });

        // Graphique de répartition des ventes
        const salesDistributionCtx = document.getElementById('salesDistributionChart').getContext('2d');
        new Chart(salesDistributionCtx, {
            type: 'doughnut',
            data: salesDistributionData,
            options: {
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ': ' + context.raw + '%';
                            }
                        }
                    }
                }
            }
        });
    });

    // Actualiser le tableau de bord
    document.getElementById('refreshDashboard').addEventListener('click', function() {
        location.reload();
    });
    </script>
</body>
</html>

