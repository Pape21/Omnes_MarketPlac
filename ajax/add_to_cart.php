<?php
session_start();
require_once("../includes/db_connect.php");
require_once("../includes/functions.php");

// Activer l'affichage des erreurs pour le débogage
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Vérifier si la requête est de type POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

// Vérifier si les données nécessaires sont présentes
if (!isset($_POST['product_id']) || empty($_POST['product_id'])) {
    echo json_encode(['success' => false, 'message' => 'ID du produit manquant']);
    exit();
}

$product_id = intval($_POST['product_id']);
$quantity = isset($_POST['quantity']) && !empty($_POST['quantity']) ? intval($_POST['quantity']) : 1;

// Déboguer les valeurs reçues
error_log("Product ID: " . $product_id);
error_log("Quantity: " . $quantity);

// Vérifier si le produit existe
$product = getProductById($conn, $product_id);
if (!$product) {
    echo json_encode(['success' => false, 'message' => 'Produit introuvable']);
    exit();
}

// Vérifier si la quantité demandée est disponible
if (isset($product['stock']) && $quantity > $product['stock']) {
    echo json_encode(['success' => false, 'message' => 'Stock insuffisant. Seulement ' . $product['stock'] . ' unité(s) disponible(s).']);
    exit();
}

// Ajouter le produit au panier
addToCart($product_id, $quantity);

// Calculer le nombre total d'articles dans le panier
$cartCount = 0;
if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $qty) {
        $cartCount += $qty;
    }
}

// Retourner une réponse de succès
echo json_encode([
    'success' => true, 
    'message' => 'Produit ajouté au panier avec succès', 
    'cart_count' => $cartCount
]);
?>

