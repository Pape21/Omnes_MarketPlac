<?php
session_start();
require_once("../includes/db_connect.php");
require_once("../includes/functions.php");

// Vérifier si l'utilisateur est connecté et est un administrateur
if (!isLoggedIn() || !isAdmin()) {
    header("Location: ../login.php");
    exit();
}

// Initialiser la variable $products comme un tableau vide
$products = [];

// Au début du fichier, après la connexion à la base de données et avant de récupérer les produits, ajoutez ce code pour vérifier et créer la colonne status si elle n'existe pas
// Vérifier si la colonne 'status' existe dans la table products
$checkColumnQuery = "SHOW COLUMNS FROM products LIKE 'status'";
$columnResult = $conn->query($checkColumnQuery);

// Si la colonne n'existe pas, l'ajouter
if ($columnResult && $columnResult->num_rows == 0) {
    $addColumnQuery = "ALTER TABLE products ADD COLUMN status ENUM('active', 'inactive') NOT NULL DEFAULT 'active'";
    $conn->query($addColumnQuery);
    
    // Mettre à jour tous les produits existants pour avoir un statut actif par défaut
    $updateStatusQuery = "UPDATE products SET status = 'active'";
    $conn->query($updateStatusQuery);
}

// Modifier la requête de récupération des produits pour inclure la colonne status ou une valeur par défaut
$query = "SELECT p.*, u.username as seller_name, c.name as category_name,
          IFNULL(p.status, 'active') as status
          FROM products p 
          LEFT JOIN users u ON p.seller_id = u.id 
          LEFT JOIN categories c ON p.category_id = c.id 
          ORDER BY p.created_at DESC";

// Récupérer la liste des produits
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}

// Gérer la suppression d'un produit
if (isset($_POST['delete_product'])) {
    $product_id = $_POST['product_id'];
    $conn->query("DELETE FROM products WHERE id = " . intval($product_id));
    header("Location: products.php");
    exit();
}

// Gérer la mise à jour du statut d'un produit
if (isset($_POST['update_product_status'])) {
  $product_id = $_POST['product_id'];
  $status = $_POST['status'];
  $conn->query("UPDATE products SET status = '" . $conn->real_escape_string($status) . "' WHERE id = " . intval($product_id));
  header("Location: products.php");
  exit();
}

// Calculer les statistiques
$active = 0;
$inactive = 0;
$low_stock = 0;
if (!empty($products)) {
    foreach($products as $product) {
        if($product['status'] == 'active') $active++;
        else $inactive++;
        if($product['stock'] < 5) $low_stock++;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Produits - Administration</title>
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
                        <a class="nav-link" href="sellers.php">
                            <i class="fas fa-store"></i> Vendeurs
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="products.php">
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
                    <h1 class="h2">Gestion des Produits</h1>
                </div>

                <!-- Table des produits directement en haut -->
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Image</th>
                                <th>Nom</th>
                                <th>Vendeur</th>
                                <th>Catégorie</th>
                                <th>Prix</th>
                                <th>Stock</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($products)): ?>
                                <?php foreach ($products as $product): ?>
                                <tr>
                                    <td><?php echo $product['id']; ?></td>
                                    <td>
                                        <?php if (strpos($product['image'], '/') === 0): ?>
                                            <img src="<?php echo '/omnes-marketplace' . htmlspecialchars($product['image']); ?>" 
                                                alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                                class="img-thumbnail" 
                                                style="max-width: 50px;">
                                        <?php else: ?>
                                            <img src="<?php echo htmlspecialchars($product['image']); ?>" 
                                                alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                                class="img-thumbnail" 
                                                style="max-width: 50px;">
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                                    <td><?php echo htmlspecialchars($product['seller_name']); ?></td>
                                    <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                                    <td><?php echo number_format($product['price'], 2); ?> €</td>
                                    <td><?php echo $product['stock']; ?></td>
                                    <td>
                                        <?php if ($product['status'] == 'active'): ?>
                                            <span class="badge text-bg-success">Actif</span>
                                        <?php else: ?>
                                            <span class="badge text-bg-danger">Inactif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="javascript:void(0)" onclick="editProduct(<?php echo $product['id']; ?>)" class="text-decoration-none me-2">Modifier</a>
                                        <form action="" method="POST" class="d-inline" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce produit ?');">
                                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                            <button type="submit" name="delete_product" class="btn btn-link p-0 text-decoration-none me-2">Supprimer</button>
                                        </form>
                                        <?php if ($product['status'] != 'active'): ?>
                                        <form action="" method="POST" class="d-inline">
                                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                            <input type="hidden" name="status" value="active">
                                            <button type="submit" name="update_product_status" class="btn btn-link p-0 text-decoration-none me-2">Activer</button>
                                        </form>
                                        <?php else: ?>
                                        <form action="" method="POST" class="d-inline">
                                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                            <input type="hidden" name="status" value="inactive">
                                            <button type="submit" name="update_product_status" class="btn btn-link p-0 text-decoration-none">Désactiver</button>
                                        </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center">Aucun produit trouvé</td>
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
                                            Total Produits</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo count($products); ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-shopping-bag fa-2x text-gray-300"></i>
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
                                            Produits Actifs</div>
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
                        <div class="card border-left-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Stock Faible</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $low_stock; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
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
                                            Produits Inactifs</div>
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
    <script>
        function editProduct(productId) {
            window.location.href = 'edit_product.php?id=' + productId;
        }
    </script>
</body>
</html>

