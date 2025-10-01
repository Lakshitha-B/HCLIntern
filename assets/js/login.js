$(function () {
  const API = 'php/login.php';

  function showAlert(type, message) {
    const alert = `<div class="alert alert-${type} alert-dismissible fade show" role="alert">${message}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>`;
    $('#loginAlert').html(alert);
  }

  $('#loginForm').on('submit', function (e) {
    e.preventDefault();

    const payload = {
      identifier: $('#identifier').val().trim(),
      password: $('#password').val()
    };

    $.ajax({
      url: API,
      method: 'POST',
      data: JSON.stringify(payload),
      contentType: 'application/json',
      dataType: 'json'
    })
      .done(function (res) {
        if (res.success && res.token) {
          localStorage.setItem('session_token', res.token);
          window.location.href = 'profile.html';
        } else {
          showAlert('danger', res.message || 'Invalid credentials.');
        }
      })
      .fail(function () {
        showAlert('danger', 'Network error. Please try again.');
      });
  });
});


