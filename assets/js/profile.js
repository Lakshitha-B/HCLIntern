$(function () {
  const API = 'php/profile.php';

  function showAlert(type, message) {
    const alert = `<div class="alert alert-${type} alert-dismissible fade show" role="alert">${message}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>`;
    $('#profileAlert').html(alert);
  }

  function getToken() {
    return localStorage.getItem('session_token');
  }

  function ensureAuth() {
    const token = getToken();
    if (!token) {
      window.location.href = 'login.html';
      return null;
    }
    return token;
  }

  function fetchProfile() {
    const token = ensureAuth();
    if (!token) return;

    $.ajax({
      url: API,
      method: 'GET',
      data: { token: token },
      dataType: 'json'
    })
      .done(function (res) {
        if (res.success && res.user) {
          const u = res.user;
          $('#viewUsername').text(u.username);
          $('#viewEmail').text(u.email);
          $('#age').val(u.age || '');
          $('#dob').val(u.dob || '');
          $('#contact').val(u.contact || '');
        } else {
          showAlert('danger', res.message || 'Failed to fetch profile.');
          if (res.code === 'INVALID_TOKEN') {
            localStorage.removeItem('session_token');
            window.location.href = 'login.html';
          }
        }
      })
      .fail(function () {
        showAlert('danger', 'Network error. Please try again.');
      });
  }

  $('#profileForm').on('submit', function (e) {
    e.preventDefault();
    const token = ensureAuth();
    if (!token) return;

    const payload = {
      token: token,
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
          showAlert('success', 'Profile updated successfully.');
          fetchProfile();
        } else {
          showAlert('danger', res.message || 'Update failed.');
          if (res.code === 'INVALID_TOKEN') {
            localStorage.removeItem('session_token');
            window.location.href = 'login.html';
          }
        }
      })
      .fail(function () {
        showAlert('danger', 'Network error. Please try again.');
      });
  });

  $('#logoutBtn').on('click', function () {
    const token = getToken();
    if (token) {
      $.ajax({
        url: API,
        method: 'DELETE',
        data: { token: token },
        dataType: 'json'
      })
        .always(function () {
          localStorage.removeItem('session_token');
          window.location.href = 'login.html';
        });
    } else {
      window.location.href = 'login.html';
    }
  });

  // init
  fetchProfile();
});


