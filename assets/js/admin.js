(function ($) {
  function sensitiveOptions() {
    var environment_type = $("select.environment").val();
    var api_environment_string = environment_type + "_settings";

    $(".env").closest("tr").hide();
    $("." + api_environment_string)
      .closest("tr")
      .show();
  }

  $(document).ready(function () {
    sensitiveOptions();
    $("select.environment").on("change", function (e, data) {
      sensitiveOptions();
    });
  });
})(jQuery);
