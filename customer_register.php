<?php
session_start();
require_once 'classes/database.php';
$db = Database::getInstance();

$sweetAlertConfig = "";

// Handle registration
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstname = trim($_POST['firstname']);
    $lastname  = trim($_POST['lastname']);
    $username  = trim($_POST['username']);
    $email     = trim($_POST['email']);
    $password_raw  = $_POST['password'];

    // Validation
    if ($firstname === "" || $lastname === "" || $username === "" || $email === "" || $password_raw === "") {
        $sweetAlertConfig = "
        <script>
        Swal.fire({
          icon: 'error',
          title: 'Missing Information',
          text: 'Please fill in all fields.'
        });
        </script>";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $sweetAlertConfig = "
        <script>
        Swal.fire({
          icon: 'error',
          title: 'Invalid Email',
          text: 'Please enter a valid email format.'
        });
        </script>";
    } else {
        // Password validation (same as admin)
        $passwordValid = preg_match('/^(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&]).{6,}$/', $password_raw);

        if (!$passwordValid) {
            $sweetAlertConfig = "
            <script>
            Swal.fire({
              icon: 'error',
              title: 'Invalid Password',
              text: 'Password must be at least 6 characters long, include an uppercase letter, a number, and a special character.'
            });
            </script>";
        } else {
            $result = $db->registerCustomer($firstname, $lastname, $username, $email, $password_raw);

            if ($result === true) {
                $sweetAlertConfig = "
                <script>
                Swal.fire({
                  icon: 'success',
                  title: 'Registration Successful',
                  text: 'You have successfully registered.',
                  confirmButtonText: 'OK'
                }).then(() => {
                  window.location.href = 'customer_login.php';
                });
                </script>";
            } else {
                $sweetAlertConfig = "
                <script>
                Swal.fire({
                  icon: 'error',
                  title: 'Registration Failed',
                  text: '".htmlspecialchars($result)."'
                });
                </script>";
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Customer Register - Happy Sprays</title>
<style>
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    margin: 0; background: #fff; color: #000;
}
/* Navbar */
.top-nav {
    position: fixed; top: 0; left: 0; width: 100%;
    background: #fff; border-bottom: 1px solid #eee;
    padding: 10px 20px; display: flex;
    justify-content: space-between; align-items: center;
    font-family: 'Playfair Display', serif;
    font-size: 22px; font-weight: 700; text-transform: uppercase;
    letter-spacing: 2px; z-index: 1000;
}
.top-nav .logo { flex: 1; text-align: center; }
.nav-actions {
    display: flex; align-items: center; gap: 20px;
    position: absolute; right: 20px; top: 50%;
    transform: translateY(-50%);
}
.icon-btn, .cart-link, .profile-link { background: none; border: none; cursor: pointer; }
/* Sub Nav */
.sub-nav {
    position: fixed; top: 60px; left: 0; width: 100%;
    background: #fff; border-bottom: 1px solid #ccc;
    text-align: center; padding: 12px 0; z-index: 999;
    font-family: 'Playfair Display', serif;
    text-transform: uppercase; font-weight: 600; letter-spacing: 1px;
}
.sub-nav a { margin: 0 20px; text-decoration: none; color: #000; font-size: 16px; }
.sub-nav a:hover { color: #555; }
/* Register Form */
.register-container {
    background: #e9e9e9ff; /* same as footer */
    padding: 50px;
    border-radius: 12px;
    width: 420px; /* bigger */
    box-shadow: 0 6px 15px rgba(0,0,0,0.15);
    text-align: center;
    margin: 180px auto 80px;
}
.register-container h2 {
    margin-bottom: 25px;
    font-size: 30px;
    font-weight: bold;
}
.register-container input {
    width: 100%;
    padding: 15px; /* bigger */
    margin: 12px 0;
    border: 1px solid #ccc;
    border-radius: 10px;
    font-size: 16px;
    box-sizing: border-box;
}
.register-container button {
    width: 100%;
    padding: 15px;
    border: 1px solid #000;
    border-radius: 10px;
    background: #fff;
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
    transition: 0.3s;
    margin-top: 10px;
    box-sizing: border-box;
}
.register-container button:hover {
    background: #000;
    color: #fff;
}
.msg {
    color: red;
    font-size: 14px;
    margin-bottom: 12px;
}
.extra-links {
    margin-top: 18px;
    font-size: 14px;
}
.extra-links a {
    color: #000;
    text-decoration: none;
}
.extra-links a:hover {
    text-decoration: underline;
}
/* Footer */
footer {
    background: #e9e9e9ff;
    border-top: 1px solid #eee;
    padding: 40px 20px;
    text-align: center;
    font-size: 14px;
    color: #555;
}
.footer-columns {
    display: flex;
    justify-content: center;
    gap: 100px;
    margin-bottom: 20px;
}
.footer-columns h4 {
    font-size: 16px;
    margin-bottom: 10px;
    font-weight: bold;
    color: #000;
}
.footer-columns a {
    display: block;
    text-decoration: none;
    color: #555;
    margin: 5px 0;
}
.footer-columns a:hover { color: #000; }
.social-icons { margin-top: 15px; }
.social-icons a {
    margin: 0 8px;
    color: #555;
    text-decoration: none;
    font-size: 18px;
}
.social-icons a:hover { color: #000; }
</style>
</head>
<body>
  <!-- Top Navbar -->
  <div class="top-nav">
    <div class="logo">Happy Sprays</div>
    <div class="nav-actions">
      <a href="cart.php" class="cart-link" title="Cart">🛒</a>
      <a href="customer_login.php" class="profile-link" title="Login">👤</a>
    </div>
  </div>

  <!-- Sub Navbar -->
  <div class="sub-nav">
    <a href="index.php">Home</a>
    <a href="index.php?gender=Male">For Him</a>
    <a href="index.php?gender=Female">For Her</a>
    <a href="contact.php">Contact</a>
  </div>

<!-- Register Form -->
  <div class="register-container">
    <h2>Create Account</h2>
    <form id="customerForm" method="post" autocomplete="off" novalidate>
      <input type="text" name="firstname" id="firstname" placeholder="First Name" required>
      <input type="text" name="lastname" id="lastname" placeholder="Last Name" required>
      <input type="text" name="username" id="username" placeholder="Username" required>
      <div class="invalid-feedback">Username is required.</div>
      <input type="email" name="email" id="email" placeholder="Email Address" required>
      <div class="invalid-feedback">Email is required.</div>
      <input type="password" name="password" id="password" placeholder="Password" required>
      <div class="invalid-feedback">Password requirements not met.</div>
      <button id="registerButton" type="submit" disabled>Register</button>
    </form>
    <div class="extra-links">
      <a href="customer_login.php">Already have an account? Login</a>
    </div>
  </div>

  <!-- Footer -->
  <footer>
    <div class="footer-columns">
        <div>
            <h4>Company</h4>
            <a href="about.php">About</a>
            <a href="reviews.php">Reviews</a>
        </div>
        <div>
            <h4>Customer Service</h4>
            <a href="contact.php">Contact</a>
            <a href="faq.php">FAQ</a>
        </div>
    </div>
    <div class="social-icons">
      <a href="social.php?page=facebook">Facebook</a>
      <a href="social.php?page=instagram">Instagram</a>
    </div>
    <p>© 2025 Happy Sprays. All rights reserved.</p>
  </footer>

  <script src="./bootstrap-5.3.3-dist/js/bootstrap.js"></script>
  <script src="./package/dist/sweetalert2.js"></script>
  <?php echo $sweetAlertConfig; ?>

  <script>
  // Validation like admin register.php
  const registerButton = document.getElementById('registerButton');
  const usernameField = document.getElementById('username');
  const emailField = document.getElementById('email');
  const passwordField = document.getElementById('password');

  const isPasswordValid = (value) => /^(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&]).{6,}$/.test(value);
  const isEmailValid = (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);

  // AJAX Username check
  usernameField.addEventListener('input', () => {
    const username = usernameField.value.trim();
    if (username === "") {
      registerButton.disabled = true;
      return;
    }
    fetch('ajax/check_username.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: `username=${encodeURIComponent(username)}`
    })
    .then(res => res.json())
    .then(data => {
      if (data.exists) {
        usernameField.classList.add('is-invalid');
        registerButton.disabled = true;
      } else {
        usernameField.classList.remove('is-invalid');
        usernameField.classList.add('is-valid');
        registerButton.disabled = false;
      }
    });
  });

  // AJAX Email check
  emailField.addEventListener('input', () => {
    const email = emailField.value.trim();
    if (!isEmailValid(email)) {
      emailField.classList.add('is-invalid');
      registerButton.disabled = true;
      return;
    }
    fetch('ajax/check_email.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: `email=${encodeURIComponent(email)}`
    })
    .then(res => res.json())
    .then(data => {
      if (data.exists) {
        emailField.classList.add('is-invalid');
        registerButton.disabled = true;
      } else {
        emailField.classList.remove('is-invalid');
        emailField.classList.add('is-valid');
        registerButton.disabled = false;
      }
    });
  });

  // Password validation
  passwordField.addEventListener('input', () => {
    if (isPasswordValid(passwordField.value)) {
      passwordField.classList.add('is-valid');
      passwordField.classList.remove('is-invalid');
    } else {
      passwordField.classList.remove('is-valid');
      passwordField.classList.add('is-invalid');
    }
  });
  </script>
  
</body>
</html>
