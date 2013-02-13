$(document).ready(function() {
  $('#donatePesapal').click(function(e) {
    e.preventDefault();
    $('#donateForm').slideDown('fast');
  });

  $('#donateForm form').validate({
    rules: {
      email: {
        required: true,
        email: true
      },
      amount: {
        required: true,
        digits: true
      }
    },
    //errorElement: "div",
    //errorClass: "alert alert-error",
    onsubmit: function(element) { $(element).valid(); },
    onkeyup: false,
    onfocusout: function(element) { $(element).valid(); },
  });
});