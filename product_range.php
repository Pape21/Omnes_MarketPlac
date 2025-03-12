<?php
session_start();
require_once("includes/db_connect.php");
require_once("includes/functions.php");

// Vérifier si la gamme de produit est fournie
if (!isset($_GET['range']) || empty($_GET['range'])) {
    header("Location: index.php");
    exit();
}

$range = $_GET['range'];

// Vérifier si la gamme est valide
$valid_ranges = ['rare', 'premium', 'regular'];
if (!in_array($range, $valid_ranges)) {
    header("Location: index.php");
    exit();
}

// Définir le titre et la description selon la gamme
$title = '';
$description = '';
$price_min = 0;
$price_max = 0;

switch ($range) {
    case 'rare':
        $title = 'Articles rares';
        $description = 'Découvrez notre collection d\'articles rares et uniques. Ces produits sont disponibles en quantité limitée et représentent des opportunités exceptionnelles.';
        $price_min = 500;
        $price_max = 100000;
        break;
    case 'premium':
        $title = 'Articles hauts de gamme';
        $description = 'Notre sélection d\'articles haut de gamme offre une qualité supérieure et des caractéristiques premium pour les clients les plus exigeants.';
        $price_min = 100;
        $price_max = 499.99;
        break;
    case 'regular':
        $title = 'Articles réguliers';
        $description = 'Nos articles réguliers offrent un excellent rapport qualité-prix pour vos besoins quotidiens.';
        $price_min = 0;
        $price_max = 99.99;
        break;
}

// Récupérer les produits selon la gamme de prix
$sql = "SELECT * FROM products WHERE price >= $price_min";
if ($price_max > 0) {
    $sql .= " AND price <= $price_max";
}
$sql .= " ORDER BY price DESC LIMIT 100";

$result = $conn->query($sql);
$products = array();

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?> - Omnes MarketPlace</title>
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
                <li class="breadcrumb-item active" aria-current="page"><?php echo $title; ?></li>
            </ol>
        </nav>

        <h1 class="section-title"><?php echo $title; ?></h1>
        
        <div class="mb-4">
            <p class="lead"><?php echo $description; ?></p>
            <?php if ($range == 'rare'): ?>
                <p><i class="fas fa-gem text-primary me-2"></i> Prix: 500€ et plus</p>
            <?php elseif ($range == 'premium'): ?>
                <p><i class="fas fa-star text-warning me-2"></i> Prix: Entre 100€ et 499.99€</p>
            <?php else: ?>
                <p><i class="fas fa-tag text-success me-2"></i> Prix: Jusqu'à 99.99€</p>
            <?php endif; ?>
        </div>

        <?php if (empty($products)): ?>
            <div class="alert alert-info">
                <p>Aucun produit disponible dans cette gamme pour le moment.</p>
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
                            
                            <?php if ($range == 'rare'): ?>
                                <div class="badge bg-danger position-absolute" style="top: 40px; right: 10px;">Rare</div>
                            <?php elseif ($range == 'premium'): ?>
                                <div class="badge bg-warning text-dark position-absolute" style="top: 40px; right: 10px;">Premium</div>
                            <?php endif; ?>
                            
                            <img src="<?php echo get_image_url($product['image']); ?>" class="card-img-top" alt="<?php echo $product['name']; ?>">
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
