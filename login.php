<?php
session_start();
ob_start();

require_once __DIR__ . '../config/database.php';
require_once __DIR__ . '/vendor/autoload.php';

// Initialize Google Client
$google_client = new Google_Client();
$google_client->setClientId('84111139078-hh7dushs4q7p22s4qcq6aa0p6ocnii0c.apps.googleusercontent.com');
$google_client->setClientSecret('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');
$google_client->setRedirectUri('http://localhost/project_wisata/login.php'); // Ganti ke http://localhost:8000 jika menggunakan port
$google_client->addScope('email');
$google_client->addScope('profile');

// Log redirect URI untuk debugging
error_log("Redirect URI yang digunakan: " . $google_client->getRedirectUri());

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    $redirect = ($_SESSION['role'] === 'admin') ? './pages/dashboard.php' : 'landingpage.php';
    header("Location: $redirect");
    exit();
}

$error = '';
$success_message = '';
$login_type = isset($_POST['login_type']) ? $_POST['login_type'] : 'customer';
$login_attempt = false;

// Handle Remember Me Cookie
$remember_checked = isset($_COOKIE['remember_email']) ? 'checked' : '';

// Handle Google Login for Customers
if (isset($_GET['code']) && $login_type === 'customer') {
    try {
        $token = $google_client->fetchAccessTokenWithAuthCode($_GET['code']);
        error_log("Google OAuth Response: " . json_encode($token)); // Log respons untuk debug
        if (isset($token['error'])) {
            error_log("Google OAuth Error: " . json_encode($token));
            $error = "Gagal login dengan Google: " . htmlspecialchars($token['error']);
            header("Location: login.php");
            exit;
        }

        $google_client->setAccessToken($token['access_token']);
        $payload = $google_client->verifyIdToken($token['id_token']);
        if ($payload) {
            $user_info = [
                'email' => $payload['email'],
                'name' => $payload['name'],
                'id' => $payload['sub']
            ];
        } else {
            throw new Exception("Gagal memverifikasi token ID.");
        }

        if (!$user_info['email'] || !$user_info['id']) {
            error_log("Google OAuth: Invalid user info received");
            $error = "Gagal mendapatkan informasi pengguna dari Google.";
            header("Location: login.php");
            exit;
        }

        // Check if user exists in database
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$user_info['email']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            // Register new customer
            $stmt = $pdo->prepare("INSERT INTO users (full_name, email, google_id, username, role) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$user_info['name'], $user_info['email'], $user_info['id'], $user_info['email'], 'customer']);
            $_SESSION['user_id'] = $pdo->lastInsertId();
        } else {
            $_SESSION['user_id'] = $user['id'];
        }

        $_SESSION['full_name'] = $user_info['name'];
        $_SESSION['email'] = $user_info['email'];
        $_SESSION['role'] = 'customer';
        $_SESSION['login_method'] = 'google';
        $_SESSION['last_activity'] = time();
        $_SESSION['success_message'] = "Selamat datang, " . htmlspecialchars($user_info['name']) . "! Anda berhasil login sebagai pelanggan.";

        ob_end_clean();
        header("Location: landingpage.php");
        exit;
    } catch (Exception $e) {
        error_log("Google OAuth Exception: " . $e->getMessage());
        $error = "Gagal login dengan Google: " . htmlspecialchars($e->getMessage());
        header("Location: login.php");
        exit;
    }
}

// Handle Manual Login for Admins
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $login_type === 'admin') {
    $login_attempt = true;
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']) ? true : false;

    if (empty($email) || empty($password)) {
        $error = 'Email dan password harus diisi!';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid!';
    } else {
        try {
            // Check admin credentials
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = 'admin'");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && $email === 'admin@lolongadventure.com' && $password === 'admin12345') {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = 'admin';
                $_SESSION['full_name'] = $user['full_name'] ?? 'Administrator';
                $_SESSION['email'] = $email;
                $_SESSION['role'] = 'admin';
                $_SESSION['login_method'] = 'manual';
                $_SESSION['last_activity'] = time();

                // Set remember me cookie if selected
                if ($remember) {
                    setcookie('remember_email', $email, time() + (30 * 24 * 60 * 60), '/');
                } else {
                    setcookie('remember_email', '', time() - 3600, '/');
                }

                ob_end_clean();
                header('Location: ./pages/dashboard.php');
                exit();
            } else {
                $error = 'Email atau password admin salah!';
            }
        } catch (PDOException $e) {
            error_log('Database Error: ' . $e->getMessage());
            $error = 'Terjadi kesalahan sistem. Silakan coba lagi.';
        }
    }
}

// Display success message from session if any
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

ob_end_flush();
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Lolong Adventure</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #2a7f62;
            /* Forest green */
            --secondary: #f8f9fa;
            /* Light gray */
            --accent: #ff7e33;
            /* Vibrant orange */
            --text: #1a1a1a;
            /* Darker gray */
            --text-light: #5e5e5e;
            /* Medium gray */
            --white: #ffffff;
            --success-color: #28a745;
            --error-color: #dc3545;
            --border-color: #dfe1e5;
            --shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
            --google-blue: #4285f4;
            /* Google branding color */
        }

        body {
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(145deg, var(--primary) 0%, #1a5c46 100%);
            margin: 0;
            padding: 1rem;
            color: var(--text);
            position: relative;
            overflow: hidden;
        }

        .particles {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            overflow: hidden;
        }

        .particle {
            position: absolute;
            background: rgba(255, 255, 255, 0.25);
            border-radius: 50%;
            animation: float-particle 18s infinite linear;
            opacity: 0.35;
        }

        @keyframes float-particle {
            0% {
                transform: translateY(0) rotate(0deg);
            }

            100% {
                transform: translateY(-1200px) rotate(720deg);
            }
        }

        h1,
        h5 {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
        }

        .login-container {
            max-width: 500px;
            width: 100%;
            z-index: 1;
            animation: fadeIn 1s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-card {
            background: var(--white);
            border-radius: 24px;
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .login-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.18);
        }

        .login-header {
            background: linear-gradient(145deg, var(--primary) 0%, #1a5c46 100%);
            padding: 2.5rem 2rem;
            text-align: center;
            color: var(--white);
            position: relative;
            border-bottom: 5px solid var(--accent);
        }

        .login-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at 20%, rgba(255, 255, 255, 0.2), transparent 70%);
            z-index: 0;
        }

        .login-header h1,
        .login-header h5 {
            position: relative;
            z-index: 1;
        }

        .login-body {
            padding: 2.5rem;
            background: var(--secondary);
        }

        .form-control {
            border-radius: 12px;
            border: 1px solid var(--border-color);
            padding: 1rem 1.3rem;
            transition: all 0.3s ease;
            background: var(--white);
            font-size: 0.95rem;
        }

        .form-control:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 4px rgba(255, 126, 51, 0.15);
            outline: none;
        }

        .input-group-text {
            background: var(--white);
            border: 1px solid var(--border-color);
            border-right: none;
            border-radius: 12px 0 0 12px;
            color: var(--primary);
            padding: 1rem 1.3rem;
            font-size: 1rem;
        }

        .btn-login {
            background: var(--primary);
            border: none;
            padding: 1rem;
            border-radius: 12px;
            color: var(--white);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
            width: 100%;
            font-size: 0.95rem;
        }

        .btn-login:hover {
            background: #1a5c46;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(42, 127, 98, 0.35);
        }

        .btn-login::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.25), transparent);
            transition: 0.5s;
        }

        .btn-login:hover::after {
            left: 100%;
        }

        .btn-google {
            background: var(--white);
            border: 2px solid var(--google-blue);
            color: var(--google-blue);
            padding: 1rem;
            border-radius: 12px;
            font-weight: 600;
            text-align: center;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.6rem;
            font-size: 0.95rem;
        }

        .btn-google:hover {
            background: var(--google-blue);
            color: var(--white);
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(66, 133, 244, 0.35);
        }

        .logo-text {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            font-size: 2.6rem;
            letter-spacing: 0.5px;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.25);
        }

        .logo-text span {
            color: var(--accent);
        }

        .alert {
            border-radius: 12px;
            border-left: 5px solid;
            padding: 1rem;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            background: rgba(255, 255, 255, 0.95);
        }

        .alert-success {
            border-left-color: var(--success-color);
            color: var(--success-color);
        }

        .alert-danger {
            border-left-color: var(--error-color);
            color: var(--error-color);
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            margin-bottom: 1.5rem;
        }

        .remember-me input {
            width: 1.3rem;
            height: 1.3rem;
            accent-color: var(--primary);
            cursor: pointer;
        }

        .remember-me label {
            font-size: 0.9rem;
            color: var(--text-light);
            cursor: pointer;
        }

        .back-to-home {
            display: block;
            text-align: center;
            padding: 1rem;
            border-radius: 12px;
            background: transparent;
            color: var(--primary);
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            border: 2px solid var(--primary);
            font-size: 0.95rem;
        }

        .back-to-home:hover {
            background: var(--primary);
            color: var(--white);
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(42, 127, 98, 0.35);
        }

        .divider {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin: 1.5rem 0;
            color: var(--text-light);
            font-size: 0.85rem;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid var(--border-color);
        }

        .login-toggle {
            display: flex;
            justify-content: center;
            margin-bottom: 2rem;
            background: var(--white);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            position: relative;
        }

        .login-toggle-btn {
            flex: 1;
            padding: 1rem;
            border: none;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            background: var(--secondary);
            color: var(--text);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .login-toggle-btn.active {
            background: var(--primary);
            color: var(--white);
        }

        .login-toggle-btn:hover:not(.active) {
            background: #e9ecef;
            color: var(--primary);
        }

        .login-toggle-btn i {
            font-size: 1.2rem;
        }

        .login-form,
        .google-login {
            transition: opacity 0.4s ease, transform 0.4s ease;
        }

        .login-form.hidden,
        .google-login.hidden {
            opacity: 0;
            transform: translateY(15px);
            display: none;
        }

        .copyright {
            color: var(--white);
            font-size: 0.85rem;
            text-align: center;
            margin-top: 2rem;
            opacity: 0.85;
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
        }

        @keyframes pulse {

            0%,
            100% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.05);
            }
        }

        .pulse {
            animation: pulse 2s ease-in-out infinite;
        }

        @media (max-width: 480px) {
            .login-container {
                max-width: 100%;
                padding: 1rem;
            }

            .login-header {
                padding: 1.5rem;
            }

            .login-body {
                padding: 1.5rem;
            }

            .logo-text {
                font-size: 2rem;
            }

            .login-toggle-btn {
                font-size: 0.9rem;
                padding: 0.8rem;
            }

            .login-toggle-btn i {
                font-size: 1rem;
            }

            .btn-login,
            .btn-google,
            .back-to-home {
                padding: 0.8rem;
                font-size: 0.9rem;
            }
        }
    </style>
</head>

<body>
    <!-- Floating Particles Background -->
    <div class="particles">
        <div class="particle" style="width: 20px; height: 20px; left: 10%; top: 20%; animation-delay: 0s;"></div>
        <div class="particle" style="width: 15px; height: 15px; left: 30%; top: 50%; animation-delay: 2s;"></div>
        <div class="particle" style="width: 25px; height: 25px; left: 70%; top: 30%; animation-delay: 4s;"></div>
        <div class="particle" style="width: 18px; height: 18px; left: 50%; top: 80%; animation-delay: 6s;"></div>
        <div class="particle" style="width: 22px; height: 22px; left: 90%; top: 10%; animation-delay: 8s;"></div>
    </div>

    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="d-flex justify-content-center align-items-center mb-3 pulse">
                    <img src="https://w7.pngwing.com/pngs/987/739/png-transparent-logo-cv-wisata-outbond-indonesia-thumbnail.png" alt="logo" class="logo" width="64" height="64" style="border-radius: 50%; margin-right: 12px; border: 2px solid var(--accent);" loading="lazy">
                    <h1 class="logo-text">Lolong <span>Adventure</span></h1>
                </div>
                <h5>Selamat Datang Kembali</h5>
            </div>
            <div class="login-body">
                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="bi bi-check-circle-fill"></i>
                        <?php echo htmlspecialchars($success_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="login-toggle">
                    <button class="login-toggle-btn <?php echo $login_type === 'admin' ? 'active' : ''; ?>" data-login-type="admin"><i class="bi bi-shield-lock"></i> Admin</button>
                    <button class="login-toggle-btn <?php echo $login_type === 'customer' ? 'active' : ''; ?>" data-login-type="customer"><i class="bi bi-person-circle"></i> Pelanggan</button>
                </div>

                <div class="login-form <?php echo $login_type === 'customer' ? 'hidden' : ''; ?>">
                    <form method="POST" action="">
                        <input type="hidden" name="login_type" value="admin">
                        <div class="mb-3">
                            <label for="email" class="form-label fw-medium">Email</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email" autocomplete="off" required autofocus>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label fw-medium">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" autocomplete="off" required>
                            </div>
                        </div>
                        <div class="remember-me">
                            <input type="checkbox" id="remember" name="remember" <?php echo $remember_checked; ?>>
                            <label for="remember">Ingat email saya</label>
                        </div>
                        <button type="submit" class="btn btn-login">
                            <i class="bi bi-box-arrow-in-right me-2"></i> Masuk sebagai Admin
                        </button>
                    </form>
                </div>

                <div class="google-login <?php echo $login_type === 'admin' ? 'hidden' : ''; ?>">
                    <a href="<?php echo htmlspecialchars($google_client->createAuthUrl()); ?>" class="btn btn-google">
                        <i class="bi bi-google me-2"></i> Masuk dengan Google
                    </a>
                </div>

                <div class="divider">atau</div>

                <a href="landingpage.php" class="back-to-home">
                    <i class="bi bi-house-door me-2"></i> Kembali ke Halaman Utama
                </a>
            </div>
        </div>
        <div class="copyright">
            © <?php echo date('Y'); ?> Lolong Adventure. All rights reserved.
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const emailField = document.getElementById('email');
            if (emailField) {
                emailField.focus();
            }

            const toggleButtons = document.querySelectorAll('.login-toggle-btn');
            const loginForm = document.querySelector('.login-form');
            const googleLogin = document.querySelector('.google-login');

            toggleButtons.forEach(button => {
                button.addEventListener('click', () => {
                    toggleButtons.forEach(btn => btn.classList.remove('active'));
                    button.classList.add('active');

                    const loginType = button.getAttribute('data-login-type');
                    if (loginType === 'admin') {
                        loginForm.classList.remove('hidden');
                        googleLogin.classList.add('hidden');
                        if (emailField) emailField.focus();
                    } else {
                        loginForm.classList.add('hidden');
                        googleLogin.classList.remove('hidden');
                    }
                });
            });

            const buttons = document.querySelectorAll('.btn-login, .btn-google, .back-to-home');
            buttons.forEach(button => {
                button.addEventListener('mouseenter', () => {
                    button.style.transition = 'all 0.3s ease';
                });
            });
        });
    </script>
</body>

</html>