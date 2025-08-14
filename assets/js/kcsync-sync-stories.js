jQuery(function ($) {
  // we're using the fact that the header of the page ends with a hr element with class "wp-header-end"
  const btn =
    '<a id="retrieve-cards-btn" class="button button-primary" style="transform: translateY(10px); margin-inline-start: 4px;">Synchronisation des cartes</a>';
  $("hr.wp-header-end").before(btn); // we position the element next to the "Add Post button", and fix the position

  $("#retrieve-cards-btn").on("click", function (e) {
    e.preventDefault(); // prevent button default action
    
    const $btn = $(this);
    const originalText = $btn.text();
    
    // Désactiver le bouton et montrer l'état de chargement
    $btn.prop('disabled', true)
        .text('Synchronisation en cours...')
        .addClass('updating-message');

    $.post( // ajax request
      kcsync_ajax_data.ajaxurl,
      {
        action: "kcsync_sync_stories", // launch the create post action
        _ajax_nonce: kcsync_ajax_data.nonce, // getting nonce for security
      },
      function (response) {
        if (response.success) {
          // Succès - montrer un message de confirmation
          $btn.removeClass('updating-message')
              .addClass('updated-message')
              .text('Synchronisation réussie !');
          
          // Message de succès plus informatif
          const successMessage = response.data || 'Synchronisation terminée avec succès !';
          
          // Créer une notification WordPress-style
          const notice = $('<div class="notice notice-success is-dismissible"><p>' + successMessage + '</p></div>');
          $('#retrieve-cards-btn').after(notice);
          
          // Recharger la page après un délai pour laisser l'utilisateur voir le message
          setTimeout(function() {
            location.reload();
          }, 2000);
          
        } else {
          // Erreur - restaurer le bouton et montrer l'erreur
          $btn.prop('disabled', false)
              .removeClass('updating-message')
              .text(originalText);
          
          // Message d'erreur plus informatif
          const errorMessage = response.data || 'Une erreur est survenue lors de la synchronisation.';
          
          // Créer une notification d'erreur WordPress-style sous les boutons
          const notice = $('<div class="notice notice-error is-dismissible"><p><strong>Erreur :</strong> ' + errorMessage + '</p></div>');
          $('#retrieve-cards-btn').after(notice);
        }
      }
    ).fail(function(xhr, status, error) {
      // Gestion des erreurs AJAX (réseau, timeout, etc.)
      $btn.prop('disabled', false)
          .removeClass('updating-message')
          .text(originalText);
      
      const errorMessage = 'Erreur de connexion : ' + (error || 'Problème réseau détecté');
      
      // Créer une notification d'erreur WordPress-style sous les boutons
      const notice = $('<div class="notice notice-error is-dismissible"><p><strong>Erreur réseau :</strong> ' + errorMessage + '</p></div>');
      $('#retrieve-cards-btn').after(notice);
    });
  });
});
