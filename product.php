<?php
session_start();
require_once("includes/db_connect.php");
require_once("includes/functions.php");

// Récupérer l'ID du produit
$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($product_id <= 0) {
    header("Location: index.php");
    exit();
}

// Récupérer les détails du produit
$product = getProductById($conn, $product_id);

if (!$product) {
    header("Location: index.php");
    exit();
}

// Récupérer les informations du vendeur
$seller_query = "SELECT username, email FROM users WHERE id = " . $product['seller_id'];
$seller_result = $conn->query($seller_query);
$seller = $seller_result->fetch_assoc();

// Récupérer la catégorie
$category_query = "SELECT name FROM categories WHERE id = " . $product['category_id'];
$category_result = $conn->query($category_query);
$category = $category_result->fetch_assoc();

// Récupérer l'enchère la plus élevée si c'est un produit aux enchères
$highest_bid = null;
if ($product['sale_type'] == 'auction') {
    $highest_bid = getHighestBid($conn, $product_id);
}

// Récupérer l'historique des enchères si c'est un produit aux enchères
$bid_history = [];
if ($product['sale_type'] == 'auction') {
    $bid_query = "SELECT e.*, u.username FROM encheres e 
                 JOIN users u ON e.user_id = u.id 
                 WHERE e.product_id = $product_id 
                 ORDER BY e.created_at DESC";
    $bid_result = $conn->query($bid_query);
    
    if ($bid_result && $bid_result->num_rows > 0) {
        while ($row = $bid_result->fetch_assoc()) {
            $bid_history[] = $row;
        }
    }
}

// Récupérer l'historique des négociations si c'est un produit négociable et que l'utilisateur est connecté
$negotiation_history = [];
if ($product['sale_type'] == 'negotiation' && isLoggedIn()) {
    $user_id = $_SESSION['user_id'];
    $negotiation_query = "SELECT n.*, u.username FROM negotiations n 
                         JOIN users u ON n.user_id = u.id 
                         WHERE n.product_id = $product_id AND n.user_id = $user_id 
                         ORDER BY n.created_at DESC";
    $negotiation_result = $conn->query($negotiation_query);
    
    if ($negotiation_result && $negotiation_result->num_rows > 0) {
        while ($row = $negotiation_result->fetch_assoc()) {
            $negotiation_history[] = $row;
        }
    }
}

// Récupérer les produits similaires
$similar_products = [];
$similar_query = "SELECT p.*, u.username as seller_name FROM products p 
                 JOIN users u ON p.seller_id = u.id 
                 WHERE p.category_id = " . $product['category_id'] . " 
                 AND p.id != $product_id 
                 ORDER BY p.created_at DESC LIMIT 4";
$similar_result = $conn->query($similar_query);

if ($similar_result && $similar_result->num_rows > 0) {
    while ($row = $similar_result->fetch_assoc()) {
        $similar_products[] = $row;
    }
}

// Vérifier si le stock est défini, sinon utiliser une valeur par défaut
$stock = isset($product['stock']) ? $product['stock'] : (isset($product['quantity']) ? $product['quantity'] : 1);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $product['name']; ?> - Omnes MarketPlace</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="css/style.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</head>
<body>
    <?php include("includes/header.php"); ?>
    
    <div class="container mt-4">
        <div id="alerts-container" class="mt-3"></div>
        
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Accueil</a></li>
                <li class="breadcrumb-item"><a href="category.php?id=<?php echo $product['category_id']; ?>"><?php echo $category['name']; ?></a></li>
                <li class="breadcrumb-item active" aria-current="page"><?php echo $product['name']; ?></li>
            </ol>
        </nav>
        
        <div class="row">
            <div class="col-md-6">
                <div class="product-image-container">
                    <img src="<?php echo get_image_url($product['image']); ?>" alt="<?php echo $product['name']; ?>" class="product-image img-fluid rounded">
                    <?php if ($product['sale_type'] == 'auction'): ?>
                        <div class="badge bg-primary position-absolute" style="top: 10px; right: 10px; font-size: 1rem; padding: 8px 12px;">
                            Enchère
                        </div>
                    <?php elseif ($product['sale_type'] == 'negotiation'): ?>
                        <div class="badge bg-secondary position-absolute" style="top: 10px; right: 10px; font-size: 1rem; padding: 8px 12px;">
                            Négociable
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="product-details">
                    <h1><?php echo $product['name']; ?></h1>
                    
                    <?php if ($product['sale_type'] == 'auction'): ?>
                        <div class="mb-3">
                            <p class="mb-0"><small>Prix de départ:</small></p>
                            <p class="price"><?php echo $product['price']; ?> €</p>
                            
                            <p class="mb-0"><small>Enchère actuelle:</small></p>
                            <p class="price text-primary current-bid"><?php echo $highest_bid ? $highest_bid['amount'] : $product['price']; ?> €</p>
                            
                            <p class="mb-0"><small>Fin de l'enchère:</small></p>
                            <p class="text-danger"><?php echo date('d/m/Y H:i', strtotime($product['auction_end'])); ?></p>
                        </div>
                    <?php else: ?>
                        <p class="price"><?php echo $product['price']; ?> €</p>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <p><strong>Vendeur:</strong> <?php echo $seller['username']; ?></p>
                        <p><strong>Catégorie:</strong> <?php echo $category['name']; ?></p>
                        <?php if (isset($product['condition'])): ?>
                        <p><strong>État:</strong> <?php echo $product['condition']; ?></p>
                        <?php endif; ?>
                        <p><strong>Disponibilité:</strong> <?php echo $stock > 0 ? 'En stock' : 'Épuisé'; ?></p>
                    </div>
                    
                    <div class="mb-4">
                        <h5>Description</h5>
                        <p><?php echo $product['description']; ?></p>
                    </div>
                    
                    <?php if ($product['sale_type'] == 'auction'): ?>
                        <?php if (isLoggedIn()): ?>
                            <form id="bid-form" data-product-id="<?php echo $product_id; ?>" class="bid-form mb-4">
                                <h5>Placer une enchère</h5>
                                <div class="input-group mb-3">
                                    <input type="number" id="bid-amount" class="form-control" min="<?php echo ($highest_bid ? $highest_bid['amount'] + 1 : $product['price'] + 1); ?>" step="1" value="<?php echo ($highest_bid ? $highest_bid['amount'] + 1 : $product['price'] + 1); ?>" required>
                                    <button class="btn btn-primary" type="submit">Enchérir</button>
                                </div>
                                <small class="form-text">L'enchère minimum est de <?php echo ($highest_bid ? $highest_bid['amount'] + 1 : $product['price'] + 1); ?> €</small>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <p>Vous devez être connecté pour enchérir sur ce produit.</p>
                                <a href="login.php" class="btn btn-primary">Se connecter</a>
                            </div>
                        <?php endif; ?>
                    <?php elseif ($product['sale_type'] == 'negotiation'): ?>
                        <?php if (isLoggedIn()): ?>
                            <form id="negotiation-form" data-product-id="<?php echo $product_id; ?>" class="negotiation-form mb-4">
                                <h5>Faire une offre</h5>
                                <div class="mb-3">
                                    <label for="offer-amount" class="form-label">Votre offre (€)</label>
                                    <input type="number" id="offer-amount" class="form-control" min="1" max="<?php echo $product['price']; ?>" step="0.01" required>
                                    <small class="form-text">Proposez un prix inférieur au prix affiché.</small>
                                </div>
                                <div class="mb-3">
                                    <label for="offer-message" class="form-label">Message (optionnel)</label>
                                    <textarea id="offer-message" class="form-control" rows="3"></textarea>
                                </div>
                                <button type="submit" class="btn btn-secondary">Envoyer l'offre</button>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <p>Vous devez être connecté pour négocier ce produit.</p>
                                <a href="login.php" class="btn btn-primary">Se connecter</a>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <form method="POST" action="cart.php" class="mb-4">
                            <div class="d-flex align-items-center">
                                <div class="input-group me-3" style="width: 120px;">
                                    <button class="btn btn-outline-secondary" type="button" id="decrease-quantity">-</button>
                                    <input type="number" id="quantity" name="quantity" class="form-control text-center" value="1" min="1" max="<?php echo $stock; ?>">
                                    <button class="btn btn-outline-secondary" type="button" id="increase-quantity">+</button>
                                </div>
                            </div>
                            <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
                            <button type="submit" name="add_to_cart" class="btn btn-primary mt-2">
                                <i class="fas fa-shopping-cart me-2"></i> Ajouter au panier
                            </button>
                        </form>
                    <?php endif; ?>
                    
                    <div class="d-flex justify-content-between mt-4">
                        <button class="btn btn-outline-primary" onclick="window.history.back()">
                            <i class="fas fa-arrow-left me-2"></i> Retour
                        </button>
                        <button class="btn btn-outline-secondary" onclick="window.location.href='index.php'">
                            <i class="fas fa-home me-2"></i> Accueil
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if ($product['sale_type'] == 'auction' && !empty($bid_history)): ?>
            <div class="mt-5">
                <h3>Historique des enchères</h3>
                <div class="bid-history">
                    <?php foreach ($bid_history as $bid): ?>
                        <div class="alert <?php echo ($highest_bid && isset($bid['id']) && isset($highest_bid['id']) && $bid['id'] == $highest_bid['id']) ? 'alert-success' : 'alert-light'; ?> mb-2">
                            <strong><?php echo $bid['username']; ?></strong> a enchéri <?php echo $bid['amount']; ?> € 
                            <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($bid['created_at'])); ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($product['sale_type'] == 'negotiation' && !empty($negotiation_history)): ?>
            <div class="mt-5">
                <h3>Vos négociations</h3>
                <div class="negotiation-history">
                    <?php foreach ($negotiation_history as $negotiation): ?>
                        <div class="alert <?php echo isset($negotiation['status']) ? ($negotiation['status'] == 'pending' ? 'alert-info' : ($negotiation['status'] == 'accepted' ? 'alert-success' : 'alert-danger')) : 'alert-info'; ?> mb-2" id="negotiation-<?php echo $negotiation['id']; ?>">
                            <p>
                                <strong>Votre offre:</strong> <?php echo $negotiation['amount']; ?> €<br>
                                <?php if (isset($negotiation['message']) && $negotiation['message']): ?>
                                    <strong>Message:</strong> <?php echo $negotiation['message']; ?><br>
                                <?php endif; ?>
                                <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($negotiation['created_at'])); ?></small>
                            </p>
                            
                            <?php if (!isset($negotiation['status']) || $negotiation['status'] == 'pending'): ?>
                                <p class="text-info">En attente de réponse du vendeur</p>
                            <?php elseif ($negotiation['status'] == 'accepted'): ?>
                                <p class="text-success">Offre acceptée par le vendeur</p>
                                <a href="cart.php?add=<?php echo $product_id; ?>&price=<?php echo $negotiation['amount']; ?>" class="btn btn-success btn-sm">Acheter maintenant</a>
                            <?php elseif ($negotiation['status'] == 'rejected'): ?>
                                <p class="text-danger">Offre refusée par le vendeur</p>
                                <?php if (isset($negotiation['counter_offer']) && $negotiation['counter_offer']): ?>
                                    <p>Contre-offre: <?php echo $negotiation['counter_offer']; ?> €</p>
                                    <div class="d-flex">
                                        <a href="cart.php?add=<?php echo $product_id; ?>&price=<?php echo $negotiation['counter_offer']; ?>" class="btn btn-success btn-sm me-2">Accepter</a>
                                        <button class="btn btn-danger btn-sm">Refuser</button>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Produits similaires -->
        <?php if (!empty($similar_products)): ?>
            <section class="my-5">
                <h3>Produits similaires</h3>
                <div class="row">
                    <?php foreach ($similar_products as $similar): ?>
                        <div class="col-md-3 mb-4">
                            <div class="card h-100">
                                <?php if (isset($similar['sale_type'])): ?>
                                    <?php if ($similar['sale_type'] == 'auction'): ?>
                                        <div class="badge bg-primary position-absolute" style="top: 10px; right: 10px;">Enchère</div>
                                    <?php elseif ($similar['sale_type'] == 'negotiation'): ?>
                                        <div class="badge bg-secondary position-absolute" style="top: 10px; right: 10px;">Négociable</div>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <img src="<?php echo get_image_url($similar['image']); ?>" class="card-img-top" alt="<?php echo $similar['name']; ?>">
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo $similar['name']; ?></h5>
                                    <p class="card-text"><?php echo substr($similar['description'], 0, 80); ?>...</p>
                                    <p class="price"><?php echo $similar['price']; ?> €</p>
                                    <a href="product.php?id=<?php echo $similar['id']; ?>" class="btn btn-primary w-100">Voir détails</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </div>
    
    <?php include("includes/footer.php"); ?>
    
    <script src="js/main.js"></script>
    <script>
    $(document).ready(function() {
        // Gestion de la quantité
        $('#increase-quantity').click(function() {
            var quantity = parseInt($('#quantity').val());
            var max = parseInt($('#quantity').attr('max'));
            if (quantity < max) {
                $('#quantity').val(quantity + 1);
            }
        });
        
        $('#decrease-quantity').click(function() {
            var quantity = parseInt($('#quantity').val());
            if (quantity > 1) {
                $('#quantity').val(quantity - 1);
            }
        });
        
        // Assurer que les boutons fonctionnent correctement
        $('.btn').click(function(e) {
            var href = $(this).attr('href');
            if (href && href !== '#' && !href.startsWith('#')) {
                window.location.href = href;
            }
        });

        // Fonction pour afficher des alertes
        function showAlert(type, message) {
          var alert = $(
            '<div class="alert alert-' +
              type +
              ' alert-dismissible fade show animate-fade-in" role="alert">' +
              message +
              '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' +
              '</div>'
          );

          $("#alerts-container").append(alert);

          // Faire disparaître l'alerte après 5 secondes
          setTimeout(() => {
            alert.alert("close");
          }, 5000);
        }
    });
    </script>
</body>
</html>

