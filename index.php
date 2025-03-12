<?php
session_start();
require_once("includes/db_connect.php");
require_once("includes/functions.php");
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Omnes MarketPlace - Accueil</title>
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
<div class="jumbotron bg-white p-4 rounded shadow-sm animate-on-scroll">
    <h1 class="display-4">Bienvenue sur Omnes MarketPlace</h1>
    <p class="lead">La plateforme de magasinage en ligne pour la communauté Omnes Education</p>
    <hr class="my-4">
    <p>Découvrez nos produits, négociez avec les vendeurs ou participez à des enchères pour obtenir des articles uniques.</p>
    <a href="#selection" class="btn btn-primary btn-lg">Découvrir <i class="fas fa-arrow-right ms-2"></i></a>
</div>

<!-- Sélection du jour -->
<section id="selection" class="my-5">
    <h2 class="section-title">Sélection du jour</h2>
    <div class="row">
        <?php
        // Récupérer les nouveaux produits
        $newProducts = getNewProducts($conn, 4);
        
        if (!empty($newProducts)) {
            foreach ($newProducts as $index => $product) {
                echo '<div class="col-md-3 mb-4">';
                echo '<div class="card h-100 animate-on-scroll" style="animation-delay: ' . ($index * 0.1) . 's">';
                echo '<div class="badge bg-primary position-absolute" style="top: 10px; right: 10px;">Nouveau</div>';
                echo '<img src="' . get_image_url($product['image']) . '" class="card-img-top" alt="' . $product['name'] . '">';
                echo '<div class="card-body">';
                echo '<h5 class="card-title">' . $product['name'] . '</h5>';
                echo '<p class="card-text">' . substr($product['description'], 0, 100) . '...</p>';
                echo '<p class="price">' . $product['price'] . ' €</p>';
                echo '<a href="product.php?id=' . $product['id'] . '" class="btn btn-primary w-100">Voir détails</a>';
                echo '</div>';
                echo '</div>';
                echo '</div>';
            }
        } else {
            echo '<div class="col-12 text-center">';
            echo '<p>Aucun nouveau produit disponible pour le moment.</p>';
            echo '</div>';
        }
        ?>
    </div>
</section>

<!-- Enchères en cours -->
<section class="my-5 animate-on-scroll">
  <h2 class="section-title">Enchères en cours</h2>
  <div class="row">
      <?php
      // Récupérer les produits aux enchères
      $auctionProducts = getProductsBySaleType($conn, 'auction', 4);
      
      if (!empty($auctionProducts)) {
          foreach ($auctionProducts as $index => $product) {
              // Récupérer l'enchère la plus élevée
              $highestBid = getHighestBid($conn, $product['id']);
              $currentPrice = $highestBid ? $highestBid['amount'] : $product['price'];
              
              echo '<div class="col-md-3 mb-4">';
              echo '<div class="card h-100 auction-card animate-on-scroll" style="animation-delay: ' . ($index * 0.1) . 's">';
              echo '<div class="badge bg-primary position-absolute" style="top: 10px; right: 10px;">Enchère</div>';
              echo '<img src="' . get_image_url($product['image']) . '" class="card-img-top" alt="' . $product['name'] . '">';
              echo '<div class="card-body">';
              echo '<h5 class="card-title">' . $product['name'] . '</h5>';
              echo '<p class="card-text">' . substr($product['description'], 0, 80) . '...</p>';
              echo '<div class="d-flex justify-content-between align-items-center">';
              echo '<div>';
              echo '<p class="mb-0"><small>Prix de départ:</small></p>';
              echo '<p class="price mb-0">' . $product['price'] . ' €</p>';
              echo '</div>';
              echo '<div>';
              echo '<p class="mb-0"><small>Enchère actuelle:</small></p>';
              echo '<p class="price mb-0 text-primary">' . $currentPrice . ' €</p>';
              echo '</div>';
              echo '</div>';
              echo '<p class="text-muted small mt-2">Fin: ' . date('d/m/Y H:i', strtotime($product['auction_end'])) . '</p>';
              echo '<a href="product.php?id=' . $product['id'] . '" class="btn btn-primary w-100 mt-2">Enchérir</a>';
              echo '</div>';
              echo '</div>';
              echo '</div>';
          }
      } else {
          echo '<div class="col-12 text-center">';
          echo '<p>Aucune enchère disponible pour le moment.</p>';
          echo '</div>';
      }
      ?>
  </div>
  <div class="text-center mt-3">
      <a href="sale_type.php?type=auction" class="btn btn-outline-primary">Voir toutes les enchères</a>
  </div>
</section>

<!-- Produits négociables -->
<section class="my-5 bg-light p-4 rounded animate-on-scroll">
    <h2 class="section-title">Produits négociables</h2>
    <div class="row">
        <?php
        // Récupérer les produits négociables
        $negotiationProducts = getProductsBySaleType($conn, 'negotiation', 4);
        
        if (!empty($negotiationProducts)) {
            foreach ($negotiationProducts as $index => $product) {
                echo '<div class="col-md-3 mb-4">';
                echo '<div class="card h-100 negotiation-card animate-on-scroll" style="animation-delay: ' . ($index * 0.1) . 's">';
                echo '<div class="badge bg-secondary position-absolute" style="top: 10px; right: 10px;">Négociable</div>';
                echo '<img src="' . get_image_url($product['image']) . '" class="card-img-top" alt="' . $product['name'] . '">';
                echo '<div class="card-body">';
                echo '<h5 class="card-title">' . $product['name'] . '</h5>';
                echo '<p class="card-text">' . substr($product['description'], 0, 80) . '...</p>';
                echo '<p class="price">' . $product['price'] . ' €</p>';
                echo '<p class="text-muted small"><i class="fas fa-comments-dollar me-1"></i> Prix négociable avec le vendeur</p>';
                echo '<a href="product.php?id=' . $product['id'] . '" class="btn btn-secondary w-100">Faire une offre</a>';
                echo '</div>';
                echo '</div>';
                echo '</div>';
            }
        } else {
            echo '<div class="col-12 text-center">';
            echo '<p>Aucun produit négociable disponible pour le moment.</p>';
            echo '</div>';
        }
        ?>
    </div>
    <div class="text-center mt-3">
        <a href="sale_type.php?type=negotiation" class="btn btn-outline-secondary">Voir tous les produits négociables</a>
    </div>
</section>

<!-- Ventes flash (Best-sellers) -->
<section class="my-5 bg-light p-4 rounded animate-on-scroll">
    <h2 class="section-title">Ventes flash - Best-sellers</h2>
    <div class="row">
        <?php
        // Récupérer les produits les plus vendus
        $bestSellers = getBestSellers($conn, 4);
        
        if (!empty($bestSellers)) {
            foreach ($bestSellers as $index => $product) {
                echo '<div class="col-md-3 mb-4">';
                echo '<div class="card h-100 animate-on-scroll" style="animation-delay: ' . ($index * 0.1) . 's">';
                echo '<div class="badge bg-danger text-white position-absolute" style="top: 10px; right: 10px">Best-seller</div>';
                echo '<img src="' . get_image_url($product['image']) . '" class="card-img-top" alt="' . $product['name'] . '">';
                echo '<div class="card-body">';
                echo '<h5 class="card-title">' . $product['name'] . '</h5>';
                echo '<p class="card-text">' . substr($product['description'], 0, 100) . '...</p>';
                echo '<p class="price">' . $product['price'] . ' €</p>';
                echo '<a href="product.php?id=' . $product['id'] . '" class="btn btn-primary w-100">Voir détails</a>';
                echo '</div>';
                echo '</div>';
                echo '</div>';
            }
        } else {
            echo '<div class="col-12 text-center">';
            echo '<p>Aucun best-seller disponible pour le moment.</p>';
            echo '</div>';
        }
        ?>
    </div>
</section>

<!-- Coordonnées et carte -->
<section class="my-5 animate-on-scroll contact-section">
    <h2 class="section-title">Nous contacter</h2>
    <div class="row g-4">
        <div class="col-md-6">
            <div class="card h-100 contact-card shadow-lg border-0">
                <div class="card-body p-4">
                    <div class="contact-header mb-4">
                        <div class="contact-icon-wrapper mb-3">
                            <i class="fas fa-address-card contact-icon"></i>
                        </div>
                        <h4 class="card-title fw-bold">Coordonnées</h4>
                        <div class="contact-divider"></div>
                    </div>
                    
                    <div class="contact-info">
                        <div class="contact-item">
                            <div class="contact-item-icon">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <div class="contact-item-content">
                                <h6 class="mb-0">Adresse</h6>
                                <p>10 Rue Sextius Michel, 75015 Paris</p>
                            </div>
                        </div>
                        
                        <div class="contact-item">
                            <div class="contact-item-icon">
                                <i class="fas fa-phone"></i>
                            </div>
                            <div class="contact-item-content">
                                <h6 class="mb-0">Téléphone</h6>
                                <p>+33 1 44 39 06 00</p>
                            </div>
                        </div>
                        
                        <div class="contact-item">
                            <div class="contact-item-icon">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div class="contact-item-content">
                                <h6 class="mb-0">Email</h6>
                                <p>contact@omnesmarketplace.fr</p>
                            </div>
                        </div>
                        
                        <div class="contact-item">
                            <div class="contact-item-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="contact-item-content">
                                <h6 class="mb-0">Horaires</h6>
                                <p>Lundi - Vendredi: 9h00 - 18h00</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <h6 class="fw-bold mb-3">Suivez-nous</h6>
                        <div class="social-icons">
                            <a href="#" class="social-icon"><i class="fab fa-facebook-f"></i></a>
                            <a href="#" class="social-icon"><i class="fab fa-twitter"></i></a>
                            <a href="#" class="social-icon"><i class="fab fa-instagram"></i></a>
                            <a href="#" class="social-icon"><i class="fab fa-linkedin-in"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card h-100 map-card shadow-lg border-0">
                <div class="card-body p-4">
                    <div class="contact-header mb-4">
                        <div class="contact-icon-wrapper mb-3">
                            <i class="fas fa-map-marked-alt contact-icon"></i>
                        </div>
                        <h4 class="card-title fw-bold">Localisation</h4>
                        <div class="contact-divider"></div>
                    </div>
                    <div id="map" class="map-container shadow">
                        <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2625.3662007085366!2d2.2859956!3d48.851292699999996!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x47e6701b4f58251b%3A0x167f5a60fb94aa76!2sECE%20Paris!5e0!3m2!1sfr!2sfr!4v1646579044227!5m2!1sfr!2sfr" width="100%" height="100%" style="border:0;" allowfullscreen="" loading="lazy"></iframe>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
</div>

<?php include("includes/footer.php"); ?>

<script>
// Script pour s'assurer que les boutons fonctionnent correctement
document.addEventListener('DOMContentLoaded', function() {
    // Vérifier tous les liens de boutons
    const buttons = document.querySelectorAll('.btn');
    buttons.forEach(button => {
        button.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            if (href && href !== '#' && !href.startsWith('#')) {
                window.location.href = href;
            }
        });
    });
});
</script>

</body>
</html>

