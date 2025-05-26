jQuery(function ($) {
  // we're using the fact that the header of the page ends with a hr element with class "wp-header-end"
  const btn =
    '<a id="retrieve-cards-btn" class="button button-primary" style="transform: translateY(10px); margin-inline-start: 4px;">Synchronisation des cartes</a>';
  $("hr.wp-header-end").before(btn); // we position the element next to the "Add Post button", and fix the position

  $("#retrieve-cards-btn").on("click", function (e) {
    e.preventDefault(); // prevent button default action

    $.post( // ajax request
      kcsync_ajax_data.ajaxurl,
      {
        action: "kcsync_sync_stories", // launch the create post action
        _ajax_nonce: kcsync_ajax_data.nonce, // getting nonce for security
      },
      function (response) {
        if (response.success) {
          alert("Article crée avec succès !");
          location.reload(); // releoding the page
        } else {
          alert("Erreur : " + response.data);
        }
      }
    );
  });
});
