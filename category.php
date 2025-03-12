<?php
session_start();
require_once("includes/db_connect.php");
require_once("includes/functions.php");

// Vérifier si l'ID de catégorie est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$category_id = $_GET['id'];

// Récupérer les informations de la catégorie
$sql = "SELECT * FROM categories WHERE id = $category_id";
$result = $conn->query($sql);

if (!$result || $result->num_rows == 0) {
    header("Location: index.php");
    exit();
}

$category = $result->fetch_assoc();

// Récupérer les produits de cette catégorie
$products = getProductsByCategory($conn, $category_id, 100); // Limite augmentée pour afficher plus de produits
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $category['name']; ?> - Omnes MarketPlace</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/style.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</head>
<body>
    <?php include("includes/header.php"); ?>

    <div class="container mt-5">
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Accueil</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?php echo $category['name']; ?></li>
            </ol>
        </nav>

        <h1 class="section-title"><?php echo $category['name']; ?></h1>
        
        <?php if (!empty($category['description'])): ?>
            <div class="mb-4">
                <p class="lead"><?php echo $category['description']; ?></p>
            </div>
        <?php endif; ?>

        <?php if (empty($products)): ?>
            <div class="alert alert-info">
                <p>Aucun produit disponible dans cette catégorie pour le moment.</p>
                <a href="index.php" class="btn btn-primary">Retour à l'accueil</a>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($products as $product): ?>
                    <div class="col-md-3 mb-4">
                        <div class="card h-100 animate-on-scroll">
                            <?php if ($product['sale_type'] == 'auction'): ?>
                                <div class="badge bg-primary position-absolute" style="top: 10px; right: 10px;">Enchère</div>
                            <?php elseif ($product['sale_type'] == 'negotiation'): ?>
                                <div class="badge bg-secondary position-absolute" style="top: 10px; right: 10px;">Négociable</div>
                            <?php else: ?>
                                <div class="badge bg-info position-absolute" style="top: 10px; right: 10px;">Achat immédiat</div>
                            <?php endif; ?>
                            
                            <img src="<?php echo  get_image_url($product['image']); ?>" class="card-img-top" alt="<?php echo $product['name']; ?>">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo $product['name']; ?></h5>
                                <p class="card-text"><?php echo substr($product['description'], 0, 100); ?>...</p>
                                <p class="price"><?php echo $product['price']; ?> €</p>
                                <a href="product.php?id=<?php echo $product['id']; ?>" class="btn btn-primary w-100">Voir détails</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php include("includes/footer.php"); ?>
    
    <script src="js/main.js"></script>
</body>
</html>

