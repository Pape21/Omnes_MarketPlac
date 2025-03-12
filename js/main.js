import $ from "jquery"
import * as bootstrap from "bootstrap"

$(document).ready(() => {
  // Initialisation des tooltips Bootstrap
  var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
  var tooltipList = tooltipTriggerList.map((tooltipTriggerEl) => new bootstrap.Tooltip(tooltipTriggerEl))

  // Animation des éléments au chargement de la page
  animateElements()

  // Animation au défilement
  $(window).on("scroll", () => {
    animateElements()
  })

  function animateElements() {
    $(".animate-on-scroll").each(function () {
      const elementTop = $(this).offset().top
      const elementHeight = $(this).outerHeight()
      const windowHeight = $(window).height()
      const scrollY = window.scrollY || window.pageYOffset

      if (scrollY > elementTop - windowHeight + elementHeight / 2) {
        $(this).addClass("animate-fade-in")
      }
    })
  }

  // Gestion du panier
  $(".add-to-cart").click(function (e) {
    e.preventDefault()

    var productId = $(this).data("product-id")
    var quantity = $("#quantity").val() || 1

    // Ajouter une animation au bouton
    const $btn = $(this)
    $btn.prop("disabled", true)
    const originalText = $btn.html()
    $btn.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Ajout...')

    // Déboguer les valeurs
    console.log("Product ID:", productId)
    console.log("Quantity:", quantity)

    $.ajax({
      url: "ajax/add_to_cart.php",
      type: "POST",
      data: {
        product_id: productId,
        quantity: quantity,
      },
      success: (response) => {
        console.log("Response:", response)
        try {
          var data = JSON.parse(response)
          if (data.success) {
            // Mettre à jour le compteur du panier
            $(".cart-count").text(data.cart_count)

            // Afficher un message de succès
            showAlert("success", "Produit ajouté au panier avec succès!")

            // Animation de confirmation
            $btn.html('<i class="fas fa-check"></i> Ajouté')
            setTimeout(() => {
              $btn.html(originalText)
              $btn.prop("disabled", false)
            }, 2000)
          } else {
            showAlert("danger", data.message)
            $btn.html(originalText)
            $btn.prop("disabled", false)
          }
        } catch (e) {
          console.error("Erreur de parsing JSON:", e, response)
          showAlert("danger", "Une erreur est survenue. Veuillez réessayer.")
          $btn.html(originalText)
          $btn.prop("disabled", false)
        }
      },
      error: (xhr, status, error) => {
        console.error("Erreur AJAX:", status, error)
        showAlert("danger", "Une erreur est survenue. Veuillez réessayer.")
        $btn.html(originalText)
        $btn.prop("disabled", false)
      },
    })
  })

  // Gestion des enchères
  $("#bid-form").submit(function (e) {
    e.preventDefault()

    var productId = $(this).data("product-id")
    var bidAmount = $("#bid-amount").val()

    // Ajouter une animation au bouton
    const $btn = $(this).find('button[type="submit"]')
    $btn.prop("disabled", true)
    const originalText = $btn.html()
    $btn.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Traitement...')

    $.ajax({
      url: "ajax/place_bid.php",
      type: "POST",
      data: {
        product_id: productId,
        amount: bidAmount,
      },
      success: (response) => {
        try {
          var data = JSON.parse(response)
          if (data.success) {
            // Mettre à jour l'historique des enchères avec animation
            const newBid = $(
              '<div class="alert alert-success animate-fade-in">' +
                "<strong>" +
                data.username +
                "</strong> a enchéri " +
                data.amount +
                " € " +
                '<small class="text-muted">à l\'instant</small>' +
                "</div>",
            )

            $(".bid-history").prepend(newBid)

            // Mettre à jour l'enchère actuelle avec animation
            $(".current-bid").fadeOut(200, function () {
              $(this)
                .text(data.amount + " €")
                .fadeIn(200)
            })

            // Mettre à jour le montant minimum pour la prochaine enchère
            $("#bid-amount").attr("min", Number.parseFloat(data.amount) + 1)
            $("#bid-amount").val(Number.parseFloat(data.amount) + 1)
            $(".form-text").text("L'enchère minimum est de " + (Number.parseFloat(data.amount) + 1) + " €")

            // Réinitialiser le formulaire
            showAlert("success", "Enchère placée avec succès!")

            // Restaurer le bouton
            $btn.html('<i class="fas fa-check"></i> Enchère placée')
            setTimeout(() => {
              $btn.html(originalText)
              $btn.prop("disabled", false)
            }, 2000)
          } else {
            showAlert("danger", data.message)
            $btn.html(originalText)
            $btn.prop("disabled", false)
          }
        } catch (e) {
          console.error("Erreur de parsing JSON:", e, response)
          showAlert("danger", "Une erreur est survenue. Veuillez réessayer.")
          $btn.html(originalText)
          $btn.prop("disabled", false)
        }
      },
      error: (xhr, status, error) => {
        console.error("Erreur AJAX:", status, error)
        showAlert("danger", "Une erreur est survenue. Veuillez réessayer.")
        $btn.html(originalText)
        $btn.prop("disabled", false)
      },
    })
  })

  // Gestion des négociations
  $("#negotiation-form").submit(function (e) {
    e.preventDefault()

    var productId = $(this).data("product-id")
    var offerAmount = $("#offer-amount").val()
    var message = $("#offer-message").val()

    // Ajouter une animation au bouton
    const $btn = $(this).find('button[type="submit"]')
    $btn.prop("disabled", true)
    const originalText = $btn.html()
    $btn.html(
      '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Envoi en cours...',
    )

    $.ajax({
      url: "ajax/place_negotiation.php",
      type: "POST",
      data: {
        product_id: productId,
        amount: offerAmount,
        message: message,
      },
      success: (response) => {
        try {
          var data = JSON.parse(response)
          if (data.success) {
            // Créer un nouvel élément pour l'historique des négociations
            const newNegotiation = $(
              '<div class="alert alert-info animate-fade-in" id="negotiation-new">' +
                "<p><strong>Votre offre:</strong> " +
                data.amount +
                " €<br>" +
                (data.message ? "<strong>Message:</strong> " + data.message + "<br>" : "") +
                '<small class="text-muted">Envoyée à l\'instant</small></p>' +
                '<p class="text-info">En attente de réponse du vendeur</p>' +
                "</div>",
            )

            // Ajouter la nouvelle négociation à l'historique
            if ($(".negotiation-history").length) {
              $(".negotiation-history").prepend(newNegotiation)
            } else {
              // Si l'historique n'existe pas encore, le créer
              const negotiationHistory = $(
                '<div class="mt-4"><h4>Vos négociations</h4><div class="negotiation-history"></div></div>',
              )
              negotiationHistory.find(".negotiation-history").append(newNegotiation)
              $("#negotiation-form").after(negotiationHistory)
            }

            // Réinitialiser le formulaire
            $("#offer-amount").val("")
            $("#offer-message").val("")

            showAlert("success", "Offre de négociation envoyée avec succès!")

            // Restaurer le bouton
            $btn.html('<i class="fas fa-check"></i> Offre envoyée')
            setTimeout(() => {
              $btn.html(originalText)
              $btn.prop("disabled", false)
            }, 2000)
          } else {
            showAlert("danger", data.message)
            $btn.html(originalText)
            $btn.prop("disabled", false)
          }
        } catch (e) {
          console.error("Erreur de parsing JSON:", e, response)
          showAlert("danger", "Une erreur est survenue. Veuillez réessayer.")
          $btn.html(originalText)
          $btn.prop("disabled", false)
        }
      },
      error: (xhr, status, error) => {
        console.error("Erreur AJAX:", status, error)
        showAlert("danger", "Une erreur est survenue. Veuillez réessayer.")
        $btn.html(originalText)
        $btn.prop("disabled", false)
      },
    })
  })

  // Répondre à une négociation
  $(".respond-negotiation").click(function () {
    var negotiationId = $(this).data("negotiation-id")
    var response = $(this).data("response")
    var counterOffer = $("#counter-offer-" + negotiationId).val()

    // Ajouter une animation au bouton
    const $btn = $(this)
    $btn.prop("disabled", true)
    const originalText = $btn.html()
    $btn.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>')

    $.ajax({
      url: "ajax/respond_negotiation.php",
      type: "POST",
      data: {
        negotiation_id: negotiationId,
        response: response,
        counter_offer: counterOffer,
      },
      success: (response) => {
        try {
          var data = JSON.parse(response)
          if (data.success) {
            // Mettre à jour l'affichage avec animation
            $("#negotiation-" + negotiationId).fadeOut(300, function () {
              $(this)
                .replaceWith(
                  '<div class="alert alert-' +
                    (data.response == 1 ? "success" : "danger") +
                    ' animate-fade-in">' +
                    "Vous avez " +
                    (data.response == 1 ? "accepté" : "refusé") +
                    " cette offre." +
                    (data.counter_offer ? " Contre-offre: " + data.counter_offer + " €" : "") +
                    "</div>",
                )
                .fadeIn(300)
            })

            showAlert("success", "Réponse envoyée avec succès!")
          } else {
            showAlert("danger", data.message)
            $btn.html(originalText)
            $btn.prop("disabled", false)
          }
        } catch (e) {
          console.error("Erreur de parsing JSON:", e, response)
          showAlert("danger", "Une erreur est survenue. Veuillez réessayer.")
          $btn.html(originalText)
          $btn.prop("disabled", false)
        }
      },
      error: (xhr, status, error) => {
        console.error("Erreur AJAX:", status, error)
        showAlert("danger", "Une erreur est survenue. Veuillez réessayer.")
        $btn.html(originalText)
        $btn.prop("disabled", false)
      },
    })
  })

  // Marquer une notification comme lue
  $(".mark-as-read").click(function (e) {
    e.preventDefault()
    var notificationId = $(this).data("notification-id")
    const $notification = $("#notification-" + notificationId)

    $.ajax({
      url: "ajax/mark_notification.php",
      type: "POST",
      data: {
        notification_id: notificationId,
      },
      success: (response) => {
        try {
          var data = JSON.parse(response)
          if (data.success) {
            // Mettre à jour l'affichage avec animation
            $notification.removeClass("unread")
            $(".mark-as-read[data-notification-id='" + notificationId + "']").fadeOut(300)

            // Mettre à jour le compteur
            var count = Number.parseInt($(".notification-count").text())
            if (count > 0) {
              count--
              if (count > 0) {
                $(".notification-count").text(count)
              } else {
                $(".notification-count").fadeOut(300)
              }
            }
          }
        } catch (e) {
          console.error("Erreur de parsing JSON:", e, response)
        }
      },
      error: (xhr, status, error) => {
        console.error("Erreur AJAX:", status, error)
      },
    })
  })

  // Sélection de carte de paiement
  $(".payment-card").click(function () {
    $(".payment-card").removeClass("selected")
    $(this).addClass("selected")
    $("#payment_method").val($(this).data("type"))
  })

  // Fonction pour afficher des alertes
  function showAlert(type, message) {
    var alert = $(
      '<div class="alert alert-' +
        type +
        ' alert-dismissible fade show animate-fade-in" role="alert">' +
        message +
        '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' +
        "</div>",
    )

    $("#alerts-container").append(alert)

    // Faire disparaître l'alerte après 5 secondes
    setTimeout(() => {
      alert.alert("close")
    }, 5000)
  }
})

