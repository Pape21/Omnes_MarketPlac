<?php
session_start();
require_once("includes/db_connect.php");
require_once("includes/functions.php");

// Traiter l'ajout au panier
if (isset($_POST['add_to_cart']) && isset($_POST['product_id']) && isset($_POST['quantity'])) {
    $product_id = intval($_POST['product_id']);
    $quantity = intval($_POST['quantity']);
    
    // Vérifier si le produit existe
    $product = getProductById($conn, $product_id);
    if ($product && $quantity > 0 && $quantity <= $product['stock']) {
        addToCart($product_id, $quantity);
        $added_to_cart = true;
    }
    
    // Rediriger vers la page du panier
    header("Location: cart.php?added=1");
    exit();
}

// Traiter l'ajout au panier depuis l'URL
if (isset($_GET['add']) && isset($_GET['id'])) {
    $product_id = intval($_GET['id']);
    $quantity = isset($_GET['quantity']) ? intval($_GET['quantity']) : 1;
    
    // Vérifier si le produit existe
    $product = getProductById($conn, $product_id);
    if ($product && $quantity > 0) {
        addToCart($product_id, $quantity);
    }
    
    // Rediriger vers la page du panier
    header("Location: cart.php?added=1");
    exit();
}

// Traiter la suppression d'un article du panier
if (isset($_GET['remove']) && isset($_GET['id'])) {
    $product_id = $_GET['id'];
    removeFromCart($product_id);
    
    // Rediriger vers la page du panier
    header("Location: cart.php?removed=1");
    exit();
}

// Traiter la mise à jour des quantités
if (isset($_POST['update_cart'])) {
    foreach ($_POST['quantity'] as $product_id => $quantity) {
        $quantity = intval($quantity);
        if ($quantity <= 0) {
            removeFromCart($product_id);
        } else {
            // Vérifier le stock disponible
            $product = getProductById($conn, $product_id);
            if ($product && $quantity <= $product['stock']) {
                $_SESSION['cart'][$product_id] = $quantity;
            }
        }
    }
    
    // Rediriger vers la page du panier
    header("Location: cart.php?updated=1");
    exit();
}

// Messages de notification
$added = isset($_GET['added']) && $_GET['added'] == 1;
$removed = isset($_GET['removed']) && $_GET['removed'] == 1;
$updated = isset($_GET['updated']) && $_GET['updated'] == 1;

// Récupérer les articles du panier
$cartItems = getCartItems($conn);
$cartTotal = getCartTotal($conn);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panier - Omnes MarketPlace</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/style.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</head>
<body>
    <?php include("includes/header.php"); ?>

    <div class="container mt-5">
        <h1>Votre panier</h1>
        
<?php if ($added): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <strong>Succès!</strong> Le produit a été ajouté à votre panier.
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if ($removed): ?>
<div class="alert alert-info alert-dismissible fade show" role="alert">
    <strong>Information:</strong> Le produit a été retiré de votre panier.
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if ($updated): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <strong>Succès!</strong> Votre panier a été mis à jour.
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>
        
        <?php if (empty($cartItems)): ?>
            <div class="alert alert-info">
                <p>Votre panier est vide.</p>
                <a href="index.php" class="btn btn-primary">Continuer vos achats</a>
            </div>
        <?php else: ?>
            <form method="POST" action="cart.php">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Produit</th>
                                <th>Prix unitaire</th>
                                <th>Quantité</th>
                                <th>Total</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cartItems as $item): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                        <img src="<?php echo get_image_url($item['image']); ?>" alt="<?php echo $item['name']; ?>" style="width: 50px; height: 50px; object-fit: cover; margin-right: 10px;">
                                            <div>
                                                <h5><?php echo $item['name']; ?></h5>
                                                <p class="text-muted small">
                                                    <?php 
                                                    if ($item['sale_type'] == 'auction') {
                                                        echo '<span class="badge bg-primary">Enchère remportée</span>';
                                                    } elseif ($item['sale_type'] == 'negotiation') {
                                                        echo '<span class="badge bg-success">Négociation acceptée</span>';
                                                    } else {
                                                        echo '<span class="badge bg-info">Achat immédiat</span>';
                                                    }
                                                    ?>
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo $item['price']; ?> €</td>
                                    <td>
                                        <input type="number" name="quantity[<?php echo $item['id']; ?>]" value="<?php echo $item['quantity']; ?>" min="1" max="<?php echo $item['stock']; ?>" class="form-control" style="width: 80px;">
                                    </td>
                                    <td><?php echo $item['price'] * $item['quantity']; ?> €</td>
                                    <td>
                                        <a href="cart.php?remove=1&id=<?php echo $item['id']; ?>" class="btn btn-danger btn-sm">
                                            <i class="fas fa-trash"></i> Supprimer
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" class="text-end"><strong>Total:</strong></td>
                                <td><strong><?php echo $cartTotal; ?> €</strong></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                
                <div class="d-flex justify-content-between mt-3">
                    <a href="index.php" class="btn btn-secondary">Continuer vos achats</a>
                    <div>
                        <button type="submit" name="update_cart" class="btn btn-info">Mettre à jour le panier</button>
                        <a href="checkout.php" class="btn btn-primary">Procéder au paiement</a>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <?php include("includes/footer.php"); ?>
</body>
</html>

