$(function () {
  const API = 'php/register.php';

  function showAlert(type, message) {
    const alert = `<div class="alert alert-${type} alert-dismissible fade show" role="alert">${message}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>`;
    $('#registerAlert').html(alert);
  }

  $('#registerForm').on('submit', function (e) {
    e.preventDefault();

    const payload = {
      username: $('#username').val().trim(),
      email: $('#email').val().trim(),
      password: $('#password').val(),
      age: $('#age').val() ? parseInt($('#age').val(), 10) : null,
      dob: $('#dob').val() || null,
      contact: $('#contact').val().trim() || null
    };

    $.ajax({
      url: API,
      method: 'POST',
      data: JSON.stringify(payload),
      contentType: 'application/json',
      dataType: 'json'
    })
      .done(function (res) {
        if (res.success) {
          showAlert('success', 'Registration successful. You can now login.');
          $('#registerForm')[0].reset();
        } else {
          showAlert('danger', res.message || 'Registration failed.');
        }
      })
      .fail(function () {
        showAlert('danger', 'Network error. Please try again.');
      });
  });
});


