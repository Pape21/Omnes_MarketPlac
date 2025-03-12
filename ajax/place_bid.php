<?php
session_start();
require_once("../includes/db_connect.php");
require_once("../includes/functions.php");

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
  echo json_encode(['success' => false, 'message' => 'Vous devez être connecté pour enchérir']);
  exit();
}

// Vérifier si la requête est de type POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
  exit();
}

// Vérifier si les données nécessaires sont présentes
if (!isset($_POST['product_id']) || empty($_POST['product_id']) || !isset($_POST['amount']) || empty($_POST['amount'])) {
  echo json_encode(['success' => false, 'message' => 'Données manquantes']);
  exit();
}

$product_id = $_POST['product_id'];
$amount = floatval($_POST['amount']);
$user_id = $_SESSION['user_id'];

// Vérifier si le produit existe et est de type enchère
$product = getProductById($conn, $product_id);
if (!$product) {
  echo json_encode(['success' => false, 'message' => 'Produit introuvable']);
  exit();
}

if ($product['sale_type'] != 'auction') {
  echo json_encode(['success' => false, 'message' => 'Ce produit n\'est pas disponible aux enchères']);
  exit();
}

// Vérifier si l'enchère est toujours en cours
if (strtotime($product['auction_end']) < time()) {
  echo json_encode(['success' => false, 'message' => 'Cette enchère est terminée']);
  exit();
}

// Vérifier si le montant de l'enchère est valide
$highestBid = getHighestBid($conn, $product_id);
$minBid = $highestBid ? $highestBid['amount'] + 1 : $product['price'] + 1;

if ($amount < $minBid) {
  echo json_encode(['success' => false, 'message' => 'Votre enchère doit être d\'au moins ' . $minBid . ' €']);
  exit();
}

// Placer l'enchère
$sql = "INSERT INTO encheres (product_id, user_id, amount, created_at) 
    VALUES ($product_id, $user_id, $amount, NOW())";

if ($conn->query($sql)) {
$bid_id = $conn->insert_id;

// Notifier le vendeur
$seller_id = $product['seller_id'];
$notification_message = 'Nouvelle enchère de ' . $amount . ' € sur votre produit "' . $product['name'] . '"';
$notification_link = 'product.php?id=' . $product_id;

$sql = "INSERT INTO notifications (user_id, message, link, created_at, is_read) 
        VALUES ($seller_id, '$notification_message', '$notification_link', NOW(), 0)";
$conn->query($sql);

// Notifier les autres enchérisseurs
$sql = "SELECT DISTINCT user_id FROM encheres WHERE product_id = $product_id AND user_id != $user_id";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
  while ($row = $result->fetch_assoc()) {
    $bidder_id = $row['user_id'];
    $notification_message = 'Votre enchère a été dépassée sur le produit "' . $product['name'] . '". Nouvelle enchère: ' . $amount . ' €';
    
    $sql = "INSERT INTO notifications (user_id, message, link, created_at, is_read) 
            VALUES ($bidder_id, '$notification_message', '$notification_link', NOW(), 0)";
    $conn->query($sql);
  }
}

// Retourner une réponse de succès
echo json_encode([
  'success' => true, 
  'message' => 'Enchère placée avec succès', 
  'amount' => $amount,
  'username' => $_SESSION['username'],
  'bid_id' => $bid_id
]);
} else {
echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'enregistrement de l\'enchère: ' . $conn->error]);
}
?>

