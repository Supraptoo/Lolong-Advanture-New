<?php
session_start();

// Periksa apakah pengguna sudah login
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu.";
    header("Location: login.php");
    exit;
}

// Koneksi Database
require_once 'config/database.php';

// Ambil informasi pengguna dari sesi
$user_id = $_SESSION['user_id'];
$full_name = "096_Suprapto"; // Hard-coded sesuai profil

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $destination = $_POST['destination'] ?? '';
    $booking_date = $_POST['booking_date'] ?? '';
    $people = intval($_POST['people'] ?? 1);

    // Validasi input
    if (empty($destination) || empty($booking_date) || $people < 1) {
        $_SESSION['error'] = "Semua field harus diisi dengan benar.";
    } else {
        // Insert ke database
        $query = "INSERT INTO bookings (user_id, full_name, destination, booking_date, people, status, created_at)
                  VALUES (?, ?, ?, ?, ?, 'Pending', NOW())";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("isssi", $user_id, $full_name, $destination, $booking_date, $people);

        if ($stmt->execute()) {
            $_SESSION['success'] = "Pemesanan berhasil dibuat!";
            header("Location: status_pemesanan.php");
            exit;
        } else {
            $_SESSION['error'] = "Gagal membuat pemesanan. Silakan coba lagi.";
        }
        $stmt->close();
    }
}

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesan Tiket - Lolong Adventure</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1a5c46;
            --secondary: #e6ecea;
            --accent: #e76f51;
            --text: #1a1a1a;
            --text-light: #4a4a4a;
            --white: #ffffff;
            --border-color: #d0d5d2;
            --success-color: #2e7d32;
            --error-color: #d32f2f;
            --gradient: linear-gradient(135deg, #1a5c46, #134639);
            --shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            color: var(--text);
            background-color: var(--white);
            overflow-x: hidden;
            line-height: 1.6;
        }

        .error-message,
        .success-message {
            padding: 15px;
            margin: 20px 5%;
            border-radius: 8px;
            text-align: center;
            color: var(--white);
            position: fixed;
            top: 80px;
            left: 0;
            right: 0;
            z-index: 1200;
            box-shadow: var(--shadow);
        }

        .error-message {
            background: var(--error-color);
        }

        .success-message {
            background: var(--success-color);
        }

        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            padding: 15px 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 1000;
            background: var(--gradient);
            color: var(--white);
            box-shadow: var(--shadow);
            transition: background 0.3s ease;
        }

        .navbar.scrolled {
            background: rgba(255, 255, 255, 0.95);
            color: var(--text);
            padding: 10px 5%;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo img {
            width: 50px;
            height: 50px;
            transition: transform 0.3s ease;
        }

        .logo img:hover {
            transform: scale(1.1);
        }

        .logo-text {
            font-family: 'Montserrat', sans-serif;
            font-size: 24px;
            font-weight: 700;
            color: var(--white);
        }

        .navbar.scrolled .logo-text {
            color: var(--primary);
        }

        .logo-text span {
            color: var(--accent);
        }

        .nav-links {
            display: flex;
            gap: 35px;
            align-items: center;
        }

        .nav-links a {
            text-decoration: none;
            color: var(--white);
            font-weight: 500;
            position: relative;
            transition: all 0.3s ease;
        }

        .navbar.scrolled .nav-links a {
            color: var(--text);
        }

        .nav-links a:hover {
            color: var(--accent);
        }

        .nav-links a::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 0;
            height: 2px;
            background-color: var(--accent);
            transition: width 0.3s ease;
        }

        .nav-links a:hover::after {
            width: 100%;
        }

        .nav-links .btn-primary {
            background: var(--white);
            color: var(--primary);
            box-shadow: var(--shadow);
            opacity: 1;
            visibility: visible;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .nav-links .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
        }

        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            font-size: 28px;
            color: var(--white);
            cursor: pointer;
            z-index: 1100;
        }

        .navbar.scrolled .mobile-menu-btn {
            color: var(--primary);
        }

        .btn {
            display: inline-block;
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            text-align: center;
            border: none;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            z-index: 1;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: var(--error-color);
            transition: left 0.4s ease;
            z-index: -1;
        }

        .btn:hover::before {
            left: 0;
        }

        .btn-primary {
            background: var(--white);
            color: var(--primary);
            box-shadow: var(--shadow);
        }

        .btn-primary:hover {
            color: var(--primary);
            transform: translateY(-3px);
        }

        .btn-secondary {
            background: transparent;
            color: var(--white);
            border: 2px solid var(--white);
        }

        .btn-secondary:hover {
            color: var(--white);
            background: var(--error-color);
            border-color: var(--error-color);
        }

        .navbar.scrolled .btn-secondary {
            color: var(--primary);
            border: 2px solid var(--primary);
        }

        .navbar.scrolled .btn-secondary:hover {
            color: var(--white);
            background: var(--error-color);
            border-color: var(--error-color);
        }

        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            background: var(--gradient);
            padding: 15px 5%;
            display: flex;
            justify-content: space-around;
            align-items: center;
            z-index: 1000;
            box-shadow: 0 -4px 12px rgba(0, 0, 0, 0.2);
        }

        .bottom-nav a {
            display: flex;
            flex-direction: column;
            align-items: center;
            color: var(--white);
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .bottom-nav a i {
            font-size: 1.4rem;
            margin-bottom: 5px;
        }

        .bottom-nav a:hover,
        .bottom-nav a.active {
            color: var(--accent);
            transform: translateY(-3px);
        }

        .section {
            padding: 100px 0;
            position: relative;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: calc(100vh - 140px);
        }

        .booking-section {
            width: 100%;
            background: var(--white);
            padding: 20px;
            text-align: center;
        }

        .booking-card {
            background: var(--secondary);
            border-radius: 10px;
            padding: 20px;
            box-shadow: var(--shadow);
            max-width: 500px;
            margin: 0 auto;
        }

        .booking-form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .booking-form label {
            font-size: 1.1rem;
            color: var(--primary);
            text-align: left;
        }

        .booking-form input,
        .booking-form select {
            padding: 12px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            font-size: 1rem;
            font-family: 'Poppins', sans-serif;
        }

        .booking-form input:focus,
        .booking-form select:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 5px rgba(231, 111, 81, 0.3);
        }

        @media (max-width: 768px) {
            .nav-links {
                display: none;
                flex-direction: column;
                position: absolute;
                top: 80px;
                left: 0;
                width: 100%;
                background: var(--gradient);
                padding: 20px;
                box-shadow: var(--shadow);
            }

            .nav-links.active {
                display: flex;
            }

            .mobile-menu-btn {
                display: block;
            }

            .section {
                padding: 80px 0;
                min-height: calc(100vh - 140px);
            }

            .booking-section {
                padding: 15px;
            }

            .booking-card {
                padding: 15px;
                max-width: 400px;
            }

            .booking-form label {
                font-size: 1rem;
            }

            .booking-form input,
            .booking-form select {
                font-size: 0.9rem;
            }

            .btn {
                padding: 10px 20px;
                font-size: 0.9rem;
            }

            .bottom-nav {
                padding: 10px 5%;
            }

            .bottom-nav a {
                font-size: 0.8rem;
            }

            .bottom-nav a i {
                font-size: 1.2rem;
            }
        }

        @media (max-width: 480px) {
            .booking-card {
                padding: 10px;
                max-width: 350px;
            }

            .booking-form label {
                font-size: 0.9rem;
            }

            .booking-form input,
            .booking-form select {
                font-size: 0.85rem;
            }

            .btn {
                padding: 8px 18px;
                font-size: 0.85rem;
            }

            .bottom-nav a {
                font-size: 0.7rem;
            }

            .bottom-nav a i {
                font-size: 1.1rem;
            }
        }

        @media (max-width: 375px) {
            .booking-section {
                padding: 10px;
            }

            .booking-card {
                padding: 8px;
                max-width: 300px;
            }

            .booking-form label {
                font-size: 0.85rem;
            }

            .booking-form input,
            .booking-form select {
                font-size: 0.8rem;
            }

            .btn {
                padding: 8px 15px;
                font-size: 0.8rem;
            }
        }
    </style>
</head>

<body>
    <!-- Messages -->
    <?php if (isset($_SESSION['error'])): ?>
        <div class="error-message"><?php echo htmlspecialchars($_SESSION['error']); ?></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['success'])): ?>
        <div class="success-message"><?php echo htmlspecialchars($_SESSION['success']); ?></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <!-- Navbar -->
    <nav class="navbar">
        <div class="logo">
            <img src="assets/images/logo.png" alt="Lolong Adventure Logo">
            <div class="logo-text">Lolong <span>Adventure</span></div>
        </div>
        <button class="mobile-menu-btn" id="mobileMenuBtn"><i class="fas fa-bars"></i></button>
        <div class="nav-links" id="navLinks">
            <a href="landingpage.php#home">Beranda</a>
            <a href="landingpage.php#about">Tentang Kami</a>
            <a href="landingpage.php#destinations">Destinasi</a>
            <a href="landingpage.php#events">Event</a>
            <a href="landingpage.php#location">Lokasi</a>
            <a href="landingpage.php#contact">Kontak</a>
            <a href="profile.php" class="btn btn-primary"><i class="fas fa-user"></i> Profil</a>
        </div>
    </nav>

    <!-- Booking Section -->
    <section class="section booking-section" id="booking">
        <div class="booking-card" data-aos="fade-up" data-aos-delay="200">
            <h2>Pesan Tiket</h2>
            <form class="booking-form" method="POST" action="booking.php">
                <label for="destination">Destinasi</label>
                <select name="destination" id="destination" required>
                    <option value="">Pilih Destinasi</option>
                    <option value="Gunung Merapi">Gunung Merapi</option>
                    <option value="Pantai Pasir Putih">Pantai Pasir Putih</option>
                    <option value="Air Terjun Curug">Air Terjun Curug</option>
                </select>
                <label for="booking_date">Tanggal Pemesanan</label>
                <input type="date" name="booking_date" id="booking_date" required>
                <label for="people">Jumlah Orang</label>
                <input type="number" name="people" id="people" min="1" value="1" required>
                <button type="submit" class="btn btn-primary">Pesan Sekarang</button>
            </form>
        </div>
    </section>

    <!-- Bottom Navigation -->
    <nav class="bottom-nav">
        <a href="landingpage.php#home"><i class="fas fa-home"></i> Beranda</a>
        <a href="status_pemesanan.php" class="active"><i class="fas fa-clipboard-list"></i> Status Pemesanan</a>
        <a href="profile.php"><i class="fas fa-user"></i> Profil</a>
    </nav>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>
    <script>
        AOS.init({
            duration: 1000,
            easing: 'ease-in-out',
            once: true,
            offset: 120
        });

        const navbar = document.querySelector('.navbar');
        window.addEventListener('scroll', () => {
            navbar.classList.toggle('scrolled', window.scrollY > 50);
        });

        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const navLinks = document.getElementById('navLinks');
        mobileMenuBtn.addEventListener('click', () => {
            navLinks.classList.toggle('active');
            mobileMenuBtn.innerHTML = navLinks.classList.contains('active') ? '<i class="fas fa-times"></i>' : '<i class="fas fa-bars"></i>';
        });

        document.querySelectorAll('.nav-links a').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 768) {
                    navLinks.classList.remove('active');
                    mobileMenuBtn.innerHTML = '<i class="fas fa-bars"></i>';
                }
            });
        });

        const bottomNavLinks = document.querySelectorAll('.bottom-nav a');
        bottomNavLinks.forEach(link => {
            link.addEventListener('click', () => {
                bottomNavLinks.forEach(l => l.classList.remove('active'));
                link.classList.add('active');
            });
        });
    </script>
</body>

</html>