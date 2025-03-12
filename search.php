<?php
session_start();
require_once("includes/db_connect.php");
require_once("includes/functions.php");

// Récupérer le terme de recherche
$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';

// Rechercher les produits
$products = searchProducts($conn, $keyword);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recherche - Omnes MarketPlace</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/style.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</head>
<body>
    <?php include("includes/header.php"); ?>

    <div class="container mt-5">
        <h1>Résultats de recherche pour "<?php echo htmlspecialchars($keyword); ?>"</h1>
        
        <?php if (empty($products)): ?>
            <div class="alert alert-info">
                <p>Aucun produit ne correspond à votre recherche.</p>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($products as $product): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card h-100">
                            <img src="<?php echo $product['image']; ?>" class="card-img-top" alt="<?php echo $product['name']; ?>">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo $product['name']; ?></h5>
                                <p class="card-text"><?php echo substr($product['description'], 0, 100); ?>...</p>
                                <p class="price"><?php echo $product['price']; ?> €</p>
                                <a href="product.php?id=<?php echo $product['id']; ?>" class="btn btn-primary">Voir détails</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php include("includes/footer.php"); ?>
</body>
</html>

