<?php
session_start();
require_once("../includes/db_connect.php");
require_once("../includes/functions.php");

// Vérifier si l'utilisateur est connecté et est un administrateur
if (!isLoggedIn() || !isAdmin()) {
    header("Location: ../login.php");
    exit();
}

// Vérifier si l'ID du vendeur est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: sellers.php");
    exit();
}

$seller_id = intval($_GET['id']);

// Récupérer les informations du vendeur
$query = "SELECT * FROM users WHERE id = $seller_id AND role = 'seller'";
$result = $conn->query($query);

if (!$result || $result->num_rows == 0) {
    header("Location: sellers.php");
    exit();
}

$seller = $result->fetch_assoc();

// Récupérer les produits du vendeur
$products = [];
$query = "SELECT * FROM products WHERE seller_id = $seller_id ORDER BY created_at DESC";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}

// Récupérer les ventes du vendeur
$sales = [];
$query = "SELECT o.id, o.created_at, o.total_amount, o.order_status, oi.product_id, oi.quantity, oi.price, p.name as product_name
          FROM orders o
          JOIN order_items oi ON o.id = oi.order_id
          JOIN products p ON oi.product_id = p.id
          WHERE p.seller_id = $seller_id
          ORDER BY o.created_at DESC";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $sales[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails du Vendeur - Administration</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css">
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include('includes/admin_sidebar.php'); ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Détails du Vendeur</h1>
                    <a href="sellers.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Retour à la liste
                    </a>
                </div>

                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title">Informations du vendeur</h5>
                            </div>
                            <div class="card-body">
                                <p><strong>ID:</strong> <?php echo $seller['id']; ?></p>
                                <p><strong>Nom d'utilisateur:</strong> <?php echo htmlspecialchars($seller['username']); ?></p>
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($seller['email']); ?></p>
                                <p><strong>Nom complet:</strong> <?php echo htmlspecialchars($seller['first_name'] . ' ' . $seller['last_name']); ?></p>
                                <p><strong>Adresse:</strong> <?php echo htmlspecialchars($seller['address'] ?? 'Non spécifiée'); ?></p>
                                <p><strong>Ville:</strong> <?php echo htmlspecialchars($seller['city'] ?? 'Non spécifiée'); ?></p>
                                <p><strong>Code postal:</strong> <?php echo htmlspecialchars($seller['postal_code'] ?? 'Non spécifié'); ?></p>
                                <p><strong>Pays:</strong> <?php echo htmlspecialchars($seller['country'] ?? 'Non spécifié'); ?></p>
                                <p><strong>Téléphone:</strong> <?php echo htmlspecialchars($seller['phone'] ?? 'Non spécifié'); ?></p>
                                <p><strong>Date d'inscription:</strong> <?php echo date('d/m/Y', strtotime($seller['created_at'])); ?></p>
                                <p>
                                    <strong>Statut:</strong> 
                                    <?php 
                                    $status = isset($seller['status']) ? $seller['status'] : 'approved';
                                    if ($status == 'approved'): ?>
                                        <span class="badge bg-success">Approuvé</span>
                                    <?php elseif ($status == 'pending'): ?>
                                        <span class="badge bg-warning">En attente</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Refusé</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title">Statistiques</h5>
                            </div>
                            <div class="card-body">
                                <p><strong>Nombre de produits:</strong> <?php echo count($products); ?></p>
                                <p><strong>Nombre de ventes:</strong> <?php echo count($sales); ?></p>
                                <?php
                                $total_sales = 0;
                                foreach ($sales as $sale) {
                                    $total_sales += $sale['price'] * $sale['quantity'];
                                }
                                ?>
                                <p><strong>Chiffre d'affaires total:</strong> <?php echo number_format($total_sales, 2); ?> €</p>
                            </div>
                        </div>
                    </div>
                </div>

                <h3>Produits du vendeur</h3>
                <div class="table-responsive">
                    <table class="table table-striped table-sm">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nom</th>
                                <th>Prix</th>
                                <th>Stock</th>
                                <th>Type de vente</th>
                                <th>Date d'ajout</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($products)): ?>
                                <tr>
                                    <td colspan="7" class="text-center">Aucun produit trouvé</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($products as $product): ?>
                                <tr>
                                    <td><?php echo $product['id']; ?></td>
                                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                                    <td><?php echo number_format($product['price'], 2); ?> €</td>
                                    <td><?php echo $product['stock']; ?></td>
                                    <td><?php echo ucfirst($product['sale_type']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($product['created_at'])); ?></td>
                                    <td>
                                        <a href="../product.php?id=<?php echo $product['id']; ?>" class="btn btn-sm btn-primary" target="_blank">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <h3 class="mt-4">Ventes du vendeur</h3>
                <div class="table-responsive">
                    <table class="table table-striped table-sm">
                        <thead>
                            <tr>
                                <th>ID Commande</th>
                                <th>Produit</th>
                                <th>Quantité</th>
                                <th>Prix unitaire</th>
                                <th>Total</th>
                                <th>Statut</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($sales)): ?>
                                <tr>
                                    <td colspan="7" class="text-center">Aucune vente trouvée</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($sales as $sale): ?>
                                <tr>
                                    <td><?php echo $sale['id']; ?></td>
                                    <td><?php echo htmlspecialchars($sale['product_name']); ?></td>
                                    <td><?php echo $sale['quantity']; ?></td>
                                    <td><?php echo number_format($sale['price'], 2); ?> €</td>
                                    <td><?php echo number_format($sale['price'] * $sale['quantity'], 2); ?> €</td>
                                    <td>
                                        <?php if ($sale['order_status'] == 'completed'): ?>
                                            <span class="badge bg-success">Complétée</span>
                                        <?php elseif ($sale['order_status'] == 'pending'): ?>
                                            <span class="badge bg-warning">En attente</span>
                                        <?php elseif ($sale['order_status'] == 'cancelled'): ?>
                                            <span class="badge bg-danger">Annulée</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary"><?php echo ucfirst($sale['order_status']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($sale['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </main>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

