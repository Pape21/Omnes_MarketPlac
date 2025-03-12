<?php
session_start();
require_once("includes/db_connect.php");
require_once("includes/functions.php");

// Vérifier si le type de vente est fourni
if (!isset($_GET['type']) || empty($_GET['type'])) {
    header("Location: index.php");
    exit();
}

$sale_type = $_GET['type'];

// Vérifier si le type de vente est valide
$valid_types = ['immediate', 'negotiation', 'auction'];
if (!in_array($sale_type, $valid_types)) {
    header("Location: index.php");
    exit();
}

// Définir le titre et la description selon le type de vente
$title = '';
$description = '';
switch ($sale_type) {
    case 'immediate':
        $title = 'Achat immédiat';
        $description = 'Découvrez tous nos produits disponibles à l\'achat immédiat. Ajoutez-les directement à votre panier et finalisez votre achat en quelques clics.';
        break;
    case 'negotiation':
        $title = 'Produits négociables';
        $description = 'Ces produits sont ouverts à la négociation. Faites une offre au vendeur et discutez du prix pour trouver un accord qui vous convient.';
        break;
    case 'auction':
        $title = 'Enchères en cours';
        $description = 'Participez à nos enchères en ligne et tentez de remporter ces articles au meilleur prix. Soyez stratégique et n\'oubliez pas de surveiller la fin des enchères !';
        break;
}

// Récupérer les produits selon le type de vente
$products = getProductsBySaleType($conn, $sale_type, 100); // Limite augmentée pour afficher plus de produits
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
        </div>

        <?php if (empty($products)): ?>
            <div class="alert alert-info">
                <p>Aucun produit disponible dans cette catégorie pour le moment.</p>
                <a href="index.php" class="btn btn-primary">Retour à l'accueil</a>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($products as $product): ?>
                    <div class="col-md-3 mb-4">
                        <div class="card h-100 animate-on-scroll <?php echo $sale_type == 'auction' ? 'auction-card' : ($sale_type == 'negotiation' ? 'negotiation-card' : ''); ?>">
                            <?php if ($sale_type == 'auction'): ?>
                                <div class="badge bg-primary position-absolute" style="top: 10px; right: 10px;">Enchère</div>
                                <?php 
                                // Récupérer l'enchère la plus élevée
                                $highestBid = getHighestBid($conn, $product['id']);
                                $currentPrice = $highestBid ? $highestBid['amount'] : $product['price'];
                                ?>
                            <?php elseif ($sale_type == 'negotiation'): ?>
                                <div class="badge bg-secondary position-absolute" style="top: 10px; right: 10px;">Négociable</div>
                            <?php else: ?>
                                <div class="badge bg-info position-absolute" style="top: 10px; right: 10px;">Achat immédiat</div>
                            <?php endif; ?>
                            
                            <img src="<?php echo get_image_url($product['image']); ?>" class="card-img-top" alt="<?php echo $product['name']; ?>">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo $product['name']; ?></h5>
                                <p class="card-text"><?php echo substr($product['description'], 0, 80); ?>...</p>
                                
                                <?php if ($sale_type == 'auction'): ?>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <p class="mb-0"><small>Prix de départ:</small></p>
                                            <p class="price mb-0"><?php echo $product['price']; ?> €</p>
                                        </div>
                                        <div>
                                            <p class="mb-0"><small>Enchère actuelle:</small></p>
                                            <p class="price mb-0 text-primary"><?php echo $currentPrice; ?> €</p>
                                        </div>
                                    </div>
                                    <p class="text-muted small mt-2">Fin: <?php echo date('d/m/Y H:i', strtotime($product['auction_end'])); ?></p>
                                    <a href="product.php?id=<?php echo $product['id']; ?>" class="btn btn-primary w-100 mt-2">Enchérir</a>
                                <?php elseif ($sale_type == 'negotiation'): ?>
                                    <p class="price"><?php echo $product['price']; ?> €</p>
                                    <p class="text-muted small"><i class="fas fa-comments-dollar me-1"></i> Prix négociable avec le vendeur</p>
                                    <a href="product.php?id=<?php echo $product['id']; ?>" class="btn btn-secondary w-100">Faire une offre</a>
                                <?php else: ?>
                                    <p class="price"><?php echo $product['price']; ?> €</p>
                                    <a href="product.php?id=<?php echo $product['id']; ?>" class="btn btn-primary w-100">Voir détails</a>
                                <?php endif; ?>
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

