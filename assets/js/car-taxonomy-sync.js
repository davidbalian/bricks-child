jQuery(document).ready(function ($) {
  var $btn = $("#car-taxonomy-sync-btn");
  var $progressWrap = $("#car-taxonomy-sync-progress");
  var $progressBar = $("#car-taxonomy-sync-bar");
  var $status = $("#car-taxonomy-sync-status");
  var $result = $("#car-taxonomy-sync-result");
  var totalBatches = 0;
  var errors = [];
  var makes = 0;
  var models = 0;

  function syncBatch(batch) {
    $.post(
      CarTaxonomySync.ajax_url,
      {
        action: "car_taxonomy_sync",
        nonce: CarTaxonomySync.nonce,
        batch: batch,
      },
      function (response) {
        if (!response.success) {
          $status.text("Error during sync.");
          $btn.prop("disabled", false);
          return;
        }
        // Update progress
        if (totalBatches === 0) {
          totalBatches = Math.ceil(response.total / 5);
        }
        var percent = Math.min(
          100,
          Math.round(((batch + 1) / totalBatches) * 100)
        );
        $progressBar.css("width", percent + "%");
        $status.text(
          "Processing batch " + (batch + 1) + " of " + totalBatches + "..."
        );
        makes += response.makes;
        models += response.models;
        if (response.errors && response.errors.length) {
          errors = errors.concat(response.errors);
        }
        if (!response.done) {
          syncBatch(batch + 1);
        } else {
          $status.text("Sync complete!");
          $btn.prop("disabled", false);
          var html =
            '<div class="notice notice-success"><p>' +
            "Sync complete!<br>" +
            "Makes created: " +
            makes +
            "<br>" +
            "Models created: " +
            models +
            "<br>";
          if (errors.length) {
            html += "<br><strong>Errors:</strong><br><ul>";
            errors.forEach(function (err) {
              html += "<li>" + err + "</li>";
            });
            html += "</ul>";
          }
          html += "</p></div>";
          $result.html(html);
        }
      }
    );
  }

  $btn.on("click", function () {
    $btn.prop("disabled", true);
    $progressWrap.show();
    $progressBar.css("width", "0%");
    $status.text("Starting sync...");
    $result.html("");
    totalBatches = 0;
    errors = [];
    makes = 0;
    models = 0;
    syncBatch(0);
  });
});
