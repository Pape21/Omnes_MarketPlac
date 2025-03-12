<?php
session_start();
require_once("includes/db_connect.php");
require_once("includes/functions.php");

// Rediriger si non connecté
if (!isLoggedIn()) {
  header("Location: login.php");
  exit();
}

// Vérifier si le panier est vide
$cart_items = getCartItems($conn);
if (empty($cart_items) && !isset($_GET['negotiation_id']) && !isset($_GET['auction_id'])) {
  header("Location: cart.php");
  exit();
}

// Récupérer les informations de l'utilisateur
$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM users WHERE id = $user_id";
$result = $conn->query($sql);
$user = $result->fetch_assoc();

// Traitement de la commande
$error = "";
$order_id = null;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  // Informations de livraison
  $shipping_info = array(
      'address' => $_POST['address'],
      'city' => $_POST['city'],
      'postal_code' => $_POST['postal_code'],
      'country' => $_POST['country'],
      'phone' => $_POST['phone']
  );
  
  // Informations de paiement
  $payment_info = array(
      'method' => $_POST['payment_method'],
      'card_number' => $_POST['card_number'],
      'card_name' => $_POST['card_name'],
      'card_expiry' => $_POST['card_expiry'],
      'card_cvv' => $_POST['card_cvv']
  );
  
  // Validation des informations de paiement
  if (empty($payment_info['card_number']) || empty($payment_info['card_name']) || 
      empty($payment_info['card_expiry']) || empty($payment_info['card_cvv'])) {
      $error = "Veuillez remplir tous les champs de paiement.";
  } else {
      // Vérifier le format du numéro de carte
      $card_number = preg_replace('/\s+/', '', $payment_info['card_number']);
      if (!preg_match('/^\d{13,19}$/', $card_number)) {
          $error = "Le numéro de carte n'est pas valide.";
      }
      
      // Vérifier le format de la date d'expiration (MM/YY)
      if (!preg_match('/^(0[1-9]|1[0-2])\/([0-9]{2})$/', $payment_info['card_expiry'])) {
          $error = "La date d'expiration n'est pas valide (format MM/YY).";
      } else {
          // Vérifier si la carte n'est pas expirée
          list($exp_month, $exp_year) = explode('/', $payment_info['card_expiry']);
          $exp_year = '20' . $exp_year; // Convertir YY en YYYY
          $exp_timestamp = mktime(0, 0, 0, $exp_month + 1, 0, $exp_year);
          
          if ($exp_timestamp < time()) {
              $error = "La carte a expiré.";
          }
      }
      
      // Vérifier le format du CVV
      $cvv_length = ($payment_info['method'] == 'amex') ? 4 : 3;
      if (!preg_match('/^\d{' . $cvv_length . '}$/', $payment_info['card_cvv'])) {
          $error = "Le code de sécurité n'est pas valide.";
      }
  }
  
  if (empty($error)) {
      // Créer la commande
      if (isset($_GET['negotiation_id'])) {
          // Commande à partir d'une négociation
          $negotiation_id = $_GET['negotiation_id'];
          $sql = "SELECT n.*, p.* FROM negotiations n JOIN products p ON n.product_id = p.id WHERE n.id = $negotiation_id AND n.user_id = $user_id AND n.seller_response = 1";
          $result = $conn->query($sql);
          
          if ($result->num_rows > 0) {
              $negotiation = $result->fetch_assoc();
              $items = array(array(
                  'id' => $negotiation['product_id'],
                  'quantity' => 1,
                  'price' => $negotiation['amount']
              ));
              
              $order_id = createOrder($conn, $user_id, $items, $negotiation['amount'], $shipping_info, $payment_info);
          }
      } elseif (isset($_GET['auction_id'])) {
          // Commande à partir d'une enchère
          $auction_id = $_GET['auction_id'];
          $sql = "SELECT b.*, p.* FROM bids b JOIN products p ON b.product_id = p.id WHERE b.id = $auction_id AND b.user_id = $user_id";
          $result = $conn->query($sql);
          
          if ($result->num_rows > 0) {
              $auction = $result->fetch_assoc();
              $items = array(array(
                  'id' => $auction['product_id'],
                  'quantity' => 1,
                  'price' => $auction['amount']
              ));
              
              $order_id = createOrder($conn, $user_id, $items, $auction['amount'], $shipping_info, $payment_info);
          }
      } else {
          // Commande à partir du panier
          $total = getCartTotal($conn);
          $order_id = createOrder($conn, $user_id, $cart_items, $total, $shipping_info, $payment_info);
          
          // Vider le panier si la commande est créée avec succès
          if ($order_id) {
              $_SESSION['cart'] = array();
          }
      }
      
      if ($order_id) {
          // Envoyer un email de confirmation
          $email_sent = sendOrderConfirmationEmail($order_id, $user['email'], $user['username'], $total);
          
          // Envoyer un SMS de confirmation
          $sms_sent = sendOrderConfirmationSMS($user['phone'], $order_id, $total);
          
          // Stocker les statuts d'envoi dans la session pour affichage sur la page de confirmation
          $_SESSION['email_confirmation_sent'] = $email_sent;
          $_SESSION['sms_confirmation_sent'] = $sms_sent;
          
          // Rediriger vers la page de confirmation
          header("Location: order_confirmation.php?id=$order_id");
          exit();
      } else {
          $error = "Les informations de paiement sont invalides ou la transaction a été refusée. Veuillez vérifier vos informations et réessayer.";
      }
  }
}

// Calculer le total
$total = 0;
if (isset($_GET['negotiation_id'])) {
  $negotiation_id = $_GET['negotiation_id'];
  $sql = "SELECT n.*, p.name, p.image FROM negotiations n JOIN products p ON n.product_id = p.id WHERE n.id = $negotiation_id AND n.user_id = $user_id AND n.seller_response = 1";
  $result = $conn->query($sql);
  
  if ($result->num_rows > 0) {
      $negotiation = $result->fetch_assoc();
      $cart_items = array(array(
          'id' => $negotiation['product_id'],
          'name' => $negotiation['name'],
          'image' => $negotiation['image'],
          'price' => $negotiation['amount'],
          'quantity' => 1
      ));
      $total = $negotiation['amount'];
  }
} elseif (isset($_GET['auction_id'])) {
  $auction_id = $_GET['auction_id'];
  $sql = "SELECT b.*, p.name, p.image FROM bids b JOIN products p ON b.product_id = p.id WHERE b.id = $auction_id AND b.user_id = $user_id";
  $result = $conn->query($sql);
  
  if ($result->num_rows > 0) {
      $auction = $result->fetch_assoc();
      $cart_items = array(array(
          'id' => $auction['product_id'],
          'name' => $auction['name'],
          'image' => $auction['image'],
          'price' => $auction['amount'],
          'quantity' => 1
      ));
      $total = $auction['amount'];
  }
} else {
  $total = getCartTotal($conn);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Paiement - Omnes MarketPlace</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
  <link rel="stylesheet" href="css/style.css">
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</head>
<body>
  <?php include("includes/header.php"); ?>

  <div class="container mt-5">
      <h1 class="section-title">Finaliser votre commande</h1>
      
      <?php if (!empty($error)): ?>
          <div class="alert alert-danger animate-fade-in"><?php echo $error; ?></div>
      <?php endif; ?>
      
      <div class="row">
          <div class="col-md-8">
              <div class="card mb-4 animate-on-scroll">
                  <div class="card-header bg-primary text-white">
                      <h5 class="mb-0"><i class="fas fa-map-marker-alt me-2"></i>Informations de livraison</h5>
                  </div>
                  <div class="card-body">
                      <form method="POST" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
                          <div class="row">
                              <div class="col-md-6 mb-3">
                                  <label for="first_name" class="form-label">Prénom</label>
                                  <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo $user['first_name']; ?>" required>
                              </div>
                              <div class="col-md-6 mb-3">
                                  <label for="last_name" class="form-label">Nom</label>
                                  <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo $user['last_name']; ?>" required>
                              </div>
                          </div>
                          
                          <div class="mb-3">
                              <label for="address" class="form-label">Adresse</label>
                              <input type="text" class="form-control" id="address" name="address" value="<?php echo $user['address']; ?>" required>
                          </div>
                          
                          <div class="row">
                              <div class="col-md-6 mb-3">
                                  <label for="city" class="form-label">Ville</label>
                                  <input type="text" class="form-control" id="city" name="city" value="<?php echo $user['city']; ?>" required>
                              </div>
                              <div class="col-md-6 mb-3">
                                  <label for="postal_code" class="form-label">Code postal</label>
                                  <input type="text" class="form-control" id="postal_code" name="postal_code" value="<?php echo $user['postal_code']; ?>" required>
                              </div>
                          </div>
                          
                          <div class="row">
                              <div class="col-md-6 mb-3">
                                  <label for="country" class="form-label">Pays</label>
                                  <input type="text" class="form-control" id="country" name="country" value="<?php echo $user['country']; ?>" required>
                              </div>
                              <div class="col-md-6 mb-3">
                                  <label for="phone" class="form-label">Téléphone</label>
                                  <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo $user['phone']; ?>" required>
                              </div>
                          </div>
                          
                          <div class="card mt-4 animate-on-scroll">
                              <div class="card-header bg-primary text-white">
                                  <h5 class="mb-0"><i class="fas fa-credit-card me-2"></i>Informations de paiement</h5>
                              </div>
                              <div class="card-body">
                                  <input type="hidden" id="payment_method" name="payment_method" value="visa">
                                  
                                  <div class="mb-4">
                                      <label class="form-label">Type de carte de paiement</label>
                                      <div class="row">
                                          <div class="col-md-3 col-6 mb-2">
                                              <div class="payment-card selected d-flex align-items-center" data-type="visa">
                                                  <img src="images/visa.png" alt="Visa">
                                                  <span>Visa</span>
                                              </div>
                                          </div>
                                          <div class="col-md-3 col-6 mb-2">
                                              <div class="payment-card d-flex align-items-center" data-type="mastercard">
                                                  <img src="images/mastercard.png" alt="MasterCard">
                                                  <span>MasterCard</span>
                                              </div>
                                          </div>
                                          <div class="col-md-3 col-6 mb-2">
                                              <div class="payment-card d-flex align-items-center" data-type="amex">
                                                  <img src="images/amex.png" alt="American Express">
                                                  <span>Amex</span>
                                              </div>
                                          </div>
                                          <div class="col-md-3 col-6 mb-2">
                                              <div class="payment-card d-flex align-items-center" data-type="paypal">
                                                  <img src="images/paypal.png" alt="PayPal">
                                                  <span>PayPal</span>
                                              </div>
                                          </div>
                                      </div>
                                  </div>
                                  
                                  <div class="mb-3">
                                      <label for="card_number" class="form-label">Numéro de carte</label>
                                      <input type="text" class="form-control" id="card_number" name="card_number" placeholder="XXXX XXXX XXXX XXXX" required>
                                      <div class="form-text">Entrez le numéro sans espaces pour les tests. Exemple: 4111111111111111 (Visa)</div>
                                  </div>
                                  
                                  <div class="row">
                                      <div class="col-md-6 mb-3">
                                          <label for="card_name" class="form-label">Nom sur la carte</label>
                                          <input type="text" class="form-control" id="card_name" name="card_name" placeholder="JOHN DOE" required>
                                      </div>
                                      <div class="col-md-3 mb-3">
                                          <label for="card_expiry" class="form-label">Date d'expiration</label>
                                          <input type="text" class="form-control" id="card_expiry" name="card_expiry" placeholder="MM/YY" required>
                                      </div>
                                      <div class="col-md-3 mb-3">
                                          <label for="card_cvv" class="form-label">Code de sécurité</label>
                                          <input type="text" class="form-control" id="card_cvv" name="card_cvv" placeholder="XXX" required>
                                          <div class="form-text">3 chiffres au dos de la carte (4 pour Amex)</div>
                                      </div>
                                  </div>
                                  
                                  
                              </div>
                          </div>
                          
                          <div class="d-flex justify-content-between mt-4">
                              <a href="cart.php" class="btn btn-outline-primary">
                                  <i class="fas fa-arrow-left me-2"></i>Retour au panier
                              </a>
                              <button type="submit" class="btn btn-success">
                                  <i class="fas fa-check me-2"></i>Confirmer la commande
                              </button>
                          </div>
                      </form>
                  </div>
              </div>
          </div>
          
          <div class="col-md-4">
              <div class="card animate-on-scroll">
                  <div class="card-header bg-primary text-white">
                      <h5 class="mb-0"><i class="fas fa-shopping-cart me-2"></i>Résumé de la commande</h5>
                  </div>
                  <div class="card-body">
                      <?php foreach ($cart_items as $item): ?>
                          <div class="d-flex mb-3">
                              <img src="<?php echo get_image_url($item['image']); ?>" alt="<?php echo $item['name']; ?>" class="img-thumbnail me-2" style="width: 60px; height: 60px; object-fit: cover;">
                              <div>
                                  <h6 class="mb-0"><?php echo $item['name']; ?></h6>
                                  <p class="mb-0">Quantité: <?php echo $item['quantity']; ?></p>
                                  <p class="mb-0 price"><?php echo $item['price']; ?> €</p>
                              </div>
                          </div>
                      <?php endforeach; ?>
                      
                      <hr>
                      
                      <div class="d-flex justify-content-between mb-2">
                          <span>Sous-total:</span>
                          <span><?php echo $total; ?> €</span>
                      </div>
                      <div class="d-flex justify-content-between mb-2">
                          <span>Frais de livraison:</span>
                          <span class="text-success">Gratuit</span>
                      </div>
                      <hr>
                      <div class="d-flex justify-content-between mb-3">
                          <strong>Total:</strong>
                          <strong class="price"><?php echo $total; ?> €</strong>
                      </div>
                      
                      <div class="alert alert-success">
                          <i class="fas fa-shield-alt me-2"></i> Paiement sécurisé
                          
                      </div>
                  </div>
              </div>
          </div>
      </div>
  </div>

  <?php include("includes/footer.php"); ?>
  
  <script>
    // Validation du formulaire de paiement côté client
    $(document).ready(function() {
        $('form').submit(function(event) {
            let isValid = true;
            const cardType = $('#payment_method').val();
            const cardNumber = $('#card_number').val().replace(/\s+/g, '');
            const cardExpiry = $('#card_expiry').val();
            const cardCVV = $('#card_cvv').val();
            
            // Validation du numéro de carte
            if (!/^\d{13,19}$/.test(cardNumber)) {
                alert('Le numéro de carte n\'est pas valide.');
                isValid = false;
            }
            
            // Validation de la date d'expiration
            if (!/^(0[1-9]|1[0-2])\/([0-9]{2})$/.test(cardExpiry)) {
                alert('La date d\'expiration n\'est pas valide (format MM/YY).');
                isValid = false;
            }
            
            // Validation du CVV
            const cvvLength = (cardType === 'amex') ? 4 : 3;
            if (!new RegExp(`^\\d{${cvvLength}}$`).test(cardCVV)) {
                alert(`Le code de sécurité doit contenir ${cvvLength} chiffres.`);
                isValid = false;
            }
            
            if (!isValid) {
                event.preventDefault();
            }
        });
        
        // Formater automatiquement le numéro de carte
        $('#card_number').on('input', function() {
            let value = $(this).val().replace(/\D/g, '');
            if (value.length > 16) value = value.substr(0, 16);
            
            // Ajouter des espaces tous les 4 chiffres
            let formattedValue = '';
            for (let i = 0; i < value.length; i++) {
                if (i > 0 && i % 4 === 0) {
                    formattedValue += ' ';
                }
                formattedValue += value[i];
            }
            
            $(this).val(formattedValue);
        });
        
        // Formater automatiquement la date d'expiration
        $('#card_expiry').on('input', function() {
            let value = $(this).val().replace(/\D/g, '');
            if (value.length > 4) value = value.substr(0, 4);
            
            if (value.length > 2) {
                value = value.substr(0, 2) + '/' + value.substr(2);
            }
            
            $(this).val(value);
        });
    });
  </script>
</body>
</html>

