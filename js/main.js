async function postData(url = '', data = {}) {
  const resp = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data)
  });
  return resp.json();
}

// LOGIN
const loginForm = document.getElementById('loginForm');
if (loginForm) {
  loginForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const username = document.getElementById('username').value.trim();
    const password = document.getElementById('password').value;
    const res = await postData('auth.php?action=login', { username, password });

    if (res.success) {
      if (res.role === 'admin') location = 'admin_dashboard.php';
      else location = 'user_home.php';
    } else {
      alert(res.message || 'Login failed');
    }
  });
}

// REGISTER
const registerForm = document.getElementById('registerForm');
if (registerForm) {
  registerForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const full_name = document.getElementById('reg_fullname').value.trim();
    const username = document.getElementById('reg_username').value.trim();
    const password = document.getElementById('reg_password').value;
    const role = document.querySelector('input[name="role"]:checked').value;

    const res = await postData('auth.php?action=register', {
      full_name, username, password, role
    });

    if (res.success) {
      alert('Account created successfully! You can now log in.');
      location = 'index.php';
    } else {
      alert(res.message || 'Registration failed');
    }
  });
}
