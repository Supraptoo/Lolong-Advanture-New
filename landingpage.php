<?php
session_start();
require_once 'vendor/autoload.php';

// Konfigurasi Google Client
$google_client = new Google_Client();
$google_client->setClientId('84111139078-hh7dushs4q7p22s4qcq6aa0p6ocnii0c.apps.googleusercontent.com');
$google_client->setClientSecret('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');
$google_client->setRedirectUri('http://localhost/project_wisata/landingpage.php');
$google_client->addScope('email');
$google_client->addScope('profile');

// Koneksi Database
require_once 'config/database.php';

// Proses logout
if (isset($_GET['logout'])) {
  session_unset();
  session_destroy();
  header("Location: landingpage.php");
  exit;
}

// Reset transaksi lama saat memulai transaksi baru
if (isset($_GET['new_transaction']) && $_GET['new_transaction'] === 'true') {
  unset($_SESSION['transaction']);
  unset($_SESSION['booking_id']);
  header("Location: landingpage.php");
  exit;
}

// Proses login dengan Google
if (isset($_GET['code'])) {
  try {
    $token = $google_client->fetchAccessTokenWithAuthCode($_GET['code']);
    if (isset($token['error'])) {
      error_log("Google OAuth Error: " . json_encode($token));
      $_SESSION['error'] = "Gagal login dengan Google: " . htmlspecialchars($token['error']);
      header("Location: landingpage.php");
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
      $_SESSION['error'] = "Gagal mendapatkan informasi pengguna dari Google.";
      header("Location: landingpage.php");
      exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$user_info['email']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
      $stmt = $pdo->prepare("INSERT INTO users (full_name, email, google_id, username) VALUES (?, ?, ?, ?)");
      $stmt->execute([$user_info['name'], $user_info['email'], $user_info['id'], $user_info['email']]);
      $_SESSION['user_id'] = $pdo->lastInsertId();
    } else {
      $_SESSION['user_id'] = $user['id'];
    }

    $_SESSION['full_name'] = $user_info['name'];
    $_SESSION['email'] = $user_info['email'];
    $_SESSION['login_success'] = "Anda telah berhasil login!";
    $_SESSION['role'] = 'customer';

    header("Location: booking.php");
    exit;
  } catch (Exception $e) {
    error_log("Google OAuth Exception: " . $e->getMessage());
    $_SESSION['error'] = "Gagal login dengan Google: " . htmlspecialchars($e->getMessage());
    header("Location: landingpage.php");
    exit;
  }
}

// Redirect setelah transaksi selesai
if (isset($_GET['transaction_complete']) && $_GET['transaction_complete'] === 'true') {
  $_SESSION['transaction_complete'] = "Transaksi Anda telah selesai!";
  unset($_SESSION['transaction']);
  unset($_SESSION['booking_id']);
}

// Handle messages display
$error_message = $_SESSION['error'] ?? '';
$success_message = $_SESSION['transaction_complete'] ?? $_SESSION['login_success'] ?? '';
unset($_SESSION['error']);
unset($_SESSION['transaction_complete']);
unset($_SESSION['login_success']);

// Fetch destinations from database
try {
  $stmt = $pdo->prepare("SELECT * FROM destinations WHERE status = 'active'");
  $stmt->execute();
  $destinations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  error_log("Database Error (Destinations): " . $e->getMessage());
  $destinations = [];
  $_SESSION['error'] = "Gagal mengambil data destinasi: " . htmlspecialchars($e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Lolong Adventure - Wisata Alam Premium Pekalongan</title>
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
      --shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
      --shadow-light: 0 4px 12px rgba(0, 0, 0, 0.1);
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

    h1,
    h2,
    h3,
    h4 {
      font-family: 'Montserrat', sans-serif;
      font-weight: 700;
      line-height: 1.3;
      word-wrap: break-word;
      overflow-wrap: break-word;
      hyphens: auto;
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
      box-shadow: 0 12px 24px rgba(0, 0, 0, 0.2);
    }

    .nav-links .btn-primary span,
    .nav-links .btn-primary i {
      position: relative;
      z-index: 2;
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
      background: var(--accent);
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

    .navbar.scrolled .btn-secondary {
      color: var(--primary);
      border: 2px solid var(--primary);
    }

    .btn-secondary:hover {
      color: var(--white);
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

    .hero {
      position: relative;
      min-height: 600px;
      display: flex;
      align-items: center;
      padding: 0 5%;
      overflow: hidden;
      background: var(--gradient);
      color: var(--white);
    }

    .hero::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: url('assets/images/landingpage/bg_landingpage.jpg') center/cover no-repeat fixed;
      opacity: 0.3;
      z-index: 1;
      transition: opacity 0.5s ease, transform 0.5s ease;
    }

    .hero:hover::before {
      opacity: 0.5;
      transform: scale(1.02);
    }

    .hero-content {
      position: relative;
      z-index: 2;
      max-width: 90%;
      text-align: center;
      margin: 0 auto;
      padding: 20px 10px;
    }

    .hero h1 {
      font-size: 3.5rem;
      margin-bottom: 20px;
      text-shadow: 0 5px 20px rgba(0, 0, 0, 0.3);
      line-height: 1.3;
      hyphens: auto;
    }

    .hero p {
      font-size: 1.2rem;
      margin-bottom: 25px;
      max-width: 600px;
      margin-left: auto;
      margin-right: auto;
      opacity: 0.95;
      line-height: 1.8;
      hyphens: auto;
    }

    .cta-buttons {
      display: flex;
      justify-content: center;
      gap: 20px;
      flex-wrap: wrap;
    }

    .floating-elements {
      position: absolute;
      width: 100%;
      height: 100%;
      top: 0;
      left: 0;
      overflow: hidden;
      z-index: 1;
    }

    .floating-element {
      position: absolute;
      background: rgba(255, 255, 255, 0.15);
      border-radius: 50%;
      backdrop-filter: blur(5px);
      animation: float 12s infinite ease-in-out;
      box-shadow: var(--shadow);
    }

    @keyframes float {
      0% {
        transform: translateY(0) rotate(0deg);
      }

      50% {
        transform: translateY(-30px) rotate(180deg);
      }

      100% {
        transform: translateY(0) rotate(360deg);
      }
    }

    .section {
      padding: 120px 5%;
      position: relative;
    }

    .section-title {
      text-align: center;
      margin-bottom: 80px;
    }

    .section-title h2 {
      font-size: 3rem;
      color: var(--primary);
      margin-bottom: 20px;
      position: relative;
      display: inline-block;
    }

    .section-title h2::after {
      content: '';
      position: absolute;
      bottom: -12px;
      left: 50%;
      transform: translateX(-50%);
      width: 100px;
      height: 5px;
      background: var(--accent);
      border-radius: 3px;
    }

    .section-title p {
      color: var(--text-light);
      max-width: 800px;
      margin: 0 auto;
      font-size: 1.2rem;
    }

    .about-content {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 60px;
      align-items: center;
    }

    .about-text h3 {
      font-size: 2.5rem;
      color: var(--primary);
      margin-bottom: 25px;
    }

    .about-text p {
      margin-bottom: 25px;
      color: var(--text-light);
      font-size: 1.1rem;
    }

    .about-image {
      position: relative;
      border-radius: 20px;
      overflow: hidden;
      box-shadow: var(--shadow);
      transition: transform 0.5s ease;
    }

    .about-image:hover {
      transform: scale(1.03);
    }

    .about-image img {
      width: 100%;
      height: auto;
      display: block;
      transition: transform 0.5s ease;
    }

    .about-image:hover img {
      transform: scale(1.08);
    }

    .destination-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(360px, 1fr));
      gap: 40px;
      padding: 0 20px;
    }

    .destination-card {
      background: var(--white);
      border-radius: 20px;
      overflow: hidden;
      box-shadow: var(--shadow);
      transition: all 0.4s ease;
      position: relative;
    }

    .destination-card:hover {
      transform: translateY(-8px);
      box-shadow: 0 12px 32px rgba(0, 0, 0, 0.15);
    }

    .card-image {
      height: 350px;
      position: relative;
      overflow: hidden;
    }

    .card-image img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      border-bottom: 4px solid var(--accent);
      transition: transform 0.5s ease, opacity 0.5s ease;
    }

    .destination-card:hover .card-image img {
      transform: scale(1.1);
      opacity: 0.9;
    }

    .card-image::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: linear-gradient(to bottom, rgba(0, 0, 0, 0.1), rgba(0, 0, 0, 0.3));
      z-index: 1;
      transition: opacity 0.5s ease;
    }

    .destination-card:hover .card-image::before {
      opacity: 0.6;
    }

    .card-badge {
      position: absolute;
      top: 20px;
      right: 20px;
      background: var(--accent);
      color: var(--white);
      padding: 8px 16px;
      border-radius: 20px;
      font-size: 14px;
      font-weight: 600;
      z-index: 2;
    }

    .card-content {
      padding: 25px;
      background: var(--white);
      position: relative;
      z-index: 2;
    }

    .card-content h3 {
      font-size: 1.7rem;
      margin-bottom: 15px;
      color: var(--primary);
    }

    .card-content p {
      color: var(--text-light);
      margin-bottom: 20px;
      font-size: 1rem;
      line-height: 1.5;
    }

    .card-meta {
      display: flex;
      justify-content: space-between;
      color: var(--text-light);
      font-size: 0.95rem;
      margin-bottom: 20px;
    }

    .card-meta span {
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .card-meta i {
      color: var(--accent);
    }

    .map-section {
      background: var(--secondary);
      padding: 120px 5%;
    }

    .map-container {
      max-width: 1300px;
      margin: 0 auto;
    }

    .map-wrapper {
      border-radius: 20px;
      overflow: hidden;
      box-shadow: var(--shadow);
      height: 550px;
    }

    .map-wrapper iframe {
      width: 100%;
      height: 100%;
      border: none;
    }

    .nearby-attractions {
      margin-top: 100px;
    }

    .attractions-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
      gap: 35px;
      margin-top: 50px;
    }

    .attraction-card {
      background: var(--white);
      border-radius: 20px;
      overflow: hidden;
      box-shadow: var(--shadow);
      transition: all 0.4s ease;
    }

    .attraction-card:hover {
      transform: translateY(-8px);
      box-shadow: 0 12px 32px rgba(0, 0, 0, 0.15);
    }

    .attraction-image {
      height: 220px;
      overflow: hidden;
    }

    .attraction-image img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      transition: transform 0.5s ease;
    }

    .attraction-card:hover .attraction-image img {
      transform: scale(1.1);
    }

    .attraction-content {
      padding: 25px;
    }

    .attraction-content h4 {
      font-size: 1.5rem;
      color: var(--primary);
      margin-bottom: 15px;
    }

    .attraction-content p {
      color: var(--text-light);
      margin-bottom: 20px;
      font-size: 1rem;
    }

    .attraction-meta {
      display: flex;
      justify-content: space-between;
      font-size: 0.95rem;
      color: var(--text-light);
    }

    .attraction-distance {
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .attraction-distance i {
      color: var(--accent);
    }

    .events-container {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(360px, 1fr));
      gap: 40px;
    }

    .event-card {
      background: var(--white);
      border-radius: 20px;
      overflow: hidden;
      box-shadow: var(--shadow);
      transition: all 0.4s ease;
      display: flex;
      flex-direction: column;
    }

    .event-card:hover {
      transform: translateY(-8px);
      box-shadow: 0 12px 32px rgba(0, 0, 0, 0.15);
    }

    .event-image {
      height: 240px;
      overflow: hidden;
      position: relative;
    }

    .event-image img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      transition: transform 0.5s ease;
    }

    .event-card:hover .event-image img {
      transform: scale(1.1);
    }

    .event-date {
      position: absolute;
      top: 20px;
      left: 20px;
      background: rgba(255, 255, 255, 0.95);
      padding: 12px 18px;
      border-radius: 10px;
      text-align: center;
      box-shadow: var(--shadow);
      transition: all 0.3s ease;
    }

    .event-card:hover .event-date {
      background: var(--accent);
      color: var(--white);
    }

    .event-day {
      display: block;
      font-size: 28px;
      font-weight: 700;
      color: var(--primary);
      transition: color 0.3s ease;
    }

    .event-card:hover .event-day {
      color: var(--white);
    }

    .event-month {
      display: block;
      font-size: 14px;
      font-weight: 600;
      color: var(--text-light);
      text-transform: uppercase;
      margin-top: 5px;
      transition: color 0.3s ease;
    }

    .event-card:hover .event-month {
      color: var(--white);
    }

    .event-category {
      position: absolute;
      top: 20px;
      right: 20px;
      background: var(--primary);
      color: var(--white);
      padding: 8px 16px;
      border-radius: 20px;
      font-size: 14px;
      font-weight: 600;
      transition: all 0.3s ease;
    }

    .event-card:hover .event-category {
      background: var(--white);
      color: var(--primary);
    }

    .event-content {
      padding: 25px;
      flex: 1;
      display: flex;
      flex-direction: column;
    }

    .event-content h3 {
      margin: 0 0 20px;
      font-size: 1.6rem;
      color: var(--primary);
    }

    .event-excerpt {
      color: var(--text-light);
      margin-bottom: 20px;
      line-height: 1.6;
      flex: 1;
    }

    .event-meta {
      display: flex;
      justify-content: space-between;
      margin-bottom: 20px;
      font-size: 0.95rem;
      color: var(--text-light);
    }

    .event-meta span {
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .event-meta i {
      color: var(--accent);
    }

    .view-all-events {
      text-align: center;
      margin-top: 60px;
    }

    .cta-section {
      background: var(--gradient);
      color: var(--white);
      padding: 120px 5%;
      text-align: center;
      position: relative;
      overflow: hidden;
    }

    .cta-section::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: url('assets/images/landingpage/bg-bawah.jpg') center/cover no-repeat;
      opacity: 0.25;
      z-index: 1;
    }

    .cta-content {
      position: relative;
      z-index: 2;
      max-width: 900px;
      margin: 0 auto;
    }

    .cta-content h2 {
      font-size: 3rem;
      margin-bottom: 25px;
    }

    .cta-content p {
      font-size: 1.3rem;
      margin-bottom: 40px;
      opacity: 0.95;
    }

    footer {
      background: #1a3a5f;
      color: var(--white);
      padding: 100px 5% 40px;
    }

    .footer-content {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 50px;
      max-width: 1300px;
      margin: 0 auto;
    }

    .footer-column h3 {
      font-size: 1.5rem;
      margin-bottom: 30px;
      color: var(--white);
      position: relative;
      padding-bottom: 12px;
    }

    .footer-column h3::after {
      content: '';
      position: absolute;
      bottom: 0;
      left: 0;
      width: 60px;
      height: 4px;
      background: var(--accent);
    }

    .footer-column p {
      color: rgba(255, 255, 255, 0.75);
      margin-bottom: 25px;
      font-size: 1rem;
    }

    .social-links {
      display: flex;
      gap: 20px;
      margin-top: 25px;
    }

    .social-links a {
      display: flex;
      align-items: center;
      justify-content: center;
      width: 45px;
      height: 45px;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.15);
      color: var(--white);
      transition: all 0.3s ease;
    }

    .social-links a:hover {
      background: var(--accent);
      color: #1a3a5f;
      transform: translateY(-5px);
    }

    .footer-links {
      list-style: none;
    }

    .footer-links li {
      margin-bottom: 15px;
    }

    .footer-links a {
      color: rgba(255, 255, 255, 0.75);
      text-decoration: none;
      transition: all 0.3s ease;
    }

    .footer-links a:hover {
      color: var(--accent);
      transform: translateX(8px);
    }

    .newsletter-form {
      display: flex;
      flex-direction: column;
      gap: 20px;
    }

    .newsletter-form input {
      padding: 15px 20px;
      border-radius: 10px;
      border: none;
      background: rgba(255, 255, 255, 0.15);
      color: var(--white);
      font-family: 'Poppins', sans-serif;
    }

    .newsletter-form input::placeholder {
      color: rgba(255, 255, 255, 0.65);
    }

    .footer-column i {
      margin-right: 12px;
      color: var(--accent);
      width: 25px;
      text-align: center;
    }

    .footer-bottom {
      text-align: center;
      margin-top: 80px;
      padding-top: 40px;
      border-top: 1px solid rgba(255, 255, 255, 0.15);
      color: rgba(255, 255, 255, 0.6);
      font-size: 1rem;
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
      background: rgba(255, 255, 255, 0.6);
      border-radius: 50%;
      animation: float-particle 20s infinite linear;
      opacity: 0.4;
    }

    @keyframes float-particle {
      0% {
        transform: translateY(0) rotate(0deg);
      }

      100% {
        transform: translateY(-1200px) rotate(720deg);
      }
    }

    @media (max-width: 1440px) {
      .hero {
        min-height: 550px;
      }

      .hero h1 {
        font-size: 3rem;
      }

      .hero p {
        font-size: 1.1rem;
        max-width: 80%;
      }

      .btn {
        padding: 10px 25px;
      }
    }

    @media (max-width: 1024px) {
      .hero {
        min-height: 500px;
      }

      .hero h1 {
        font-size: 2.5rem;
      }

      .hero p {
        font-size: 1rem;
        max-width: 85%;
      }

      .section {
        padding: 100px 5%;
      }

      .destination-grid {
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
      }
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

      .hero {
        min-height: 400px;
        padding-top: 80px;
        /* Add padding to account for navbar */
      }

      .hero-content {
        max-width: 95%;
        padding: 20px 10px;
        /* Increased padding for better spacing */
      }

      .hero h1 {
        font-size: 1.8rem;
        line-height: 1.4;
        margin-bottom: 15px;
      }

      .hero p {
        font-size: 0.9rem;
        max-width: 90%;
        line-height: 1.8;
      }

      .cta-buttons {
        flex-direction: column;
        gap: 15px;
      }

      .btn {
        padding: 10px 20px;
        font-size: 0.9rem;
      }

      .about-content {
        grid-template-columns: 1fr;
      }

      .about-image {
        order: -1;
      }

      .section-title h2 {
        font-size: 2.2rem;
      }

      .destination-grid {
        grid-template-columns: 1fr;
      }

      .events-container {
        grid-template-columns: 1fr;
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
      .hero {
        min-height: 360px;
        padding-top: 80px;
      }

      .hero-content {
        padding: 25px 15px;
        /* Increased padding for better visibility */
      }

      .hero h1 {
        font-size: 1.6rem;
        line-height: 1.5;
      }

      .hero p {
        font-size: 0.85rem;
        max-width: 95%;
        line-height: 1.9;
      }

      .btn {
        padding: 8px 18px;
        font-size: 0.85rem;
      }

      .section-title h2 {
        font-size: 1.8rem;
      }

      .card-badge,
      .event-category {
        font-size: 12px;
        padding: 6px 12px;
      }

      .event-date {
        padding: 10px 15px;
      }

      .event-day {
        font-size: 24px;
      }

      .event-month {
        font-size: 14px;
      }

      .card-image {
        height: 280px;
      }

      .bottom-nav a {
        font-size: 0.7rem;
      }

      .bottom-nav a i {
        font-size: 1.1rem;
      }
    }

    @media (max-width: 375px) {
      .hero {
        min-height: 340px;
        padding-top: 80px;
      }

      .hero-content {
        padding: 30px 10px;
        /* Further increased padding */
      }

      .hero h1 {
        font-size: 1.4rem;
        line-height: 1.6;
      }

      .hero p {
        font-size: 0.8rem;
        max-width: 98%;
        line-height: 2;
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
  <?php if ($error_message): ?>
    <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
  <?php endif; ?>
  <?php if ($success_message): ?>
    <div class="success-message"><?php echo htmlspecialchars($success_message); ?></div>
  <?php endif; ?>

  <!-- Floating Particles Background -->
  <div class="particles" id="particles"></div>

  <!-- Navbar -->
  <nav class="navbar">
    <div class="logo">
      <img src="assets/images/logo.png" alt="Lolong Adventure Logo">
      <div class="logo-text">Lolong <span>Adventure</span></div>
    </div>
    <button class="mobile-menu-btn" id="mobileMenuBtn"><i class="fas fa-bars"></i></button>
    <div class="nav-links" id="navLinks">
      <a href="#home">Beranda</a>
      <a href="#about">Tentang Kami</a>
      <a href="#destinations">Destinasi</a>
      <a href="#events">Event</a>
      <a href="#location">Lokasi</a>
      <a href="#contact">Kontak</a>
      <?php if (isset($_SESSION['user_id'])): ?>
        <a href="profile.php" class="btn btn-primary"><i class="fas fa-user"></i> Profil</a>
      <?php else: ?>
        <a href="login.php" class="btn btn-primary"><i class="fas fa-lock"></i> Login</a>
      <?php endif; ?>
    </div>
  </nav>

  <!-- Hero Section -->
  <?php if (isset($_SESSION['user_id'])): ?>
    <section class="hero" id="home">
      <div class="floating-elements">
        <div class="floating-element" style="width: 120px; height: 120px; top: 15%; left: 5%; animation-delay: 0s;"></div>
        <div class="floating-element" style="width: 180px; height: 180px; top: 65%; left: 85%; animation-delay: 2s;"></div>
        <div class="floating-element" style="width: 100px; height: 100px; top: 75%; left: 15%; animation-delay: 4s;"></div>
        <div class="floating-element" style="width: 140px; height: 140px; top: 25%; left: 75%; animation-delay: 6s;"></div>
      </div>
      <div class="hero-content">
        <h1 data-aos="fade-up" data-aos-delay="200">Selamat Datang, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h1>
        <p data-aos="fade-up" data-aos-delay="400">Rasakan sensasi wisata alam premium bersama pemandu profesional. Nikmati rafting, camping, dan outbound dengan standar keselamatan terbaik.</p>
        <div class="cta-buttons" data-aos="fade-up" data-aos-delay="600">
          <a href="booking.php" class="btn btn-primary"><i class="fas fa-ticket-alt"></i> Pesan Sekarang</a>
          <a href="#events" class="btn btn-secondary">Event Terbaru</a>
        </div>
      </div>
    </section>
  <?php else: ?>
    <section class="hero" id="welcome">
      <div class="hero-content">
        <h1 data-aos="fade-up" data-aos-delay="200">Selamat Datang di Lolong Adventure</h1>
        <p data-aos="fade-up" data-aos-delay="400">Login untuk menjelajahi petualangan alam premium di Pekalongan. Pesan tiket Anda sekarang!</p>
        <div class="cta-buttons" data-aos="fade-up" data-aos-delay="600">
          <a href="login.php" class="btn btn-primary"><i class="fas fa-lock"></i> Login Sekarang</a>
          <a href="#about" class="btn btn-secondary">Pelajari Lebih Lanjut</a>
        </div>
      </div>
    </section>
  <?php endif; ?>

  <!-- About Section -->
  <section class="section" id="about">
    <div class="section-title" data-aos="fade-up">
      <h2>Tentang Lolong Adventure</h2>
      <p>Penyedia jasa wisata alam profesional dengan pengalaman lebih dari 10 tahun</p>
    </div>
    <div class="about-content">
      <div class="about-text" data-aos="fade-right">
        <h3>Petualangan Alam yang Tak Terlupakan</h3>
        <p>Lolong Adventure, berdiri sejak 2010, adalah pionir wisata petualangan di Pekalongan. Kami telah mengajak ribuan petualang menjelajahi keindahan J Villages.</p>
        <p>Dengan pemandu bersertifikat dan peralatan berstandar internasional, kami menawarkan pengalaman aman dan menantang untuk semua kalangan, dari pemula hingga profesional.</p>
        <p>Kami berdedikasi untuk memberikan pelayanan terbaik sambil menjaga kelestarian alam dan mendukung komunitas lokal.</p>
        <div class="cta-buttons" style="justify-content: flex-start; margin-top: 40px;">
          <a href="#contact" class="btn btn-primary">Hubungi Kami</a>
          <a href="<?php echo isset($_SESSION['user_id']) ? 'booking.php' : 'login.php'; ?>" class="btn btn-secondary">Pesan Tiket</a>
        </div>
      </div>
      <div class="about-image" data-aos="fade-left" data-aos-delay="200">
        <img src="assets/images/landingpage/bg_landingpage.jpg" alt="Tim Lolong Adventure">
      </div>
    </div>
  </section>

  <!-- Destinations Section -->
  <section class="section destinations" id="destinations" style="background-color: var(--secondary);">
    <div class="section-title" data-aos="fade-up">
      <h2>Destinasi Wisata</h2>
      <p>Jelajahi pengalaman menakjubkan bersama kami</p>
    </div>
    <div class="destination-grid">
      <?php foreach ($destinations as $index => $destination): ?>
        <div class="destination-card" data-aos="fade-up" data-aos-delay="<?php echo 100 + ($index * 100); ?>">
          <div class="card-image">
            <img src="assets/images/destinations/<?php echo htmlspecialchars($destination['image_url']); ?>" alt="<?php echo htmlspecialchars($destination['name']); ?>">
            <?php if ($destination['is_featured']): ?>
              <span class="card-badge"><?php echo $destination['name'] === 'Arung Jeram' ? 'Populer' : 'Favorit'; ?></span>
            <?php endif; ?>
          </div>
          <div class="card-content">
            <h3><?php echo htmlspecialchars($destination['name']); ?></h3>
            <p><?php echo htmlspecialchars($destination['description']); ?> (Harga: Rp<?php echo number_format($destination['price'], 0, ',', '.'); ?>)</p>
            <div class="card-meta">
              <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($destination['location']); ?></span>
              <span><i class="fas fa-clock"></i> <?php echo htmlspecialchars($destination['duration']); ?></span>
            </div>
            <a href="<?php echo isset($_SESSION['user_id']) ? 'booking.php?package=' . urlencode(strtolower(str_replace(' ', '-', $destination['name']))) : 'login.php'; ?>" class="btn btn-primary w-full mt-5">Pesan Sekarang</a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- Events Section -->
  <section class="section" id="events">
    <div class="section-title" data-aos="fade-up">
      <h2>Event & Kegiatan</h2>
      <p>Ikuti event seru kami atau buat acara khusus untuk kelompok Anda</p>
    </div>
    <div class="events-container">
      <div class="event-card" data-aos="fade-up" data-aos-delay="100">
        <div class="event-image">
          <img src="assets/images/events/Festival-durian.jpg" alt="Festival Durian Lolong">
          <div class="event-date">
            <span class="event-day">20</span>
            <span class="event-month">Juli</span>
          </div>
          <span class="event-category">Umum</span>
        </div>
        <div class="event-content">
          <h3>Festival Durian Lolong</h3>
          <div class="event-meta">
            <span><i class="fas fa-map-marker-alt"></i> Basecamp Lolong</span>
            <span><i class="fas fa-clock"></i> 07.00 - 15.00</span>
          </div>
          <p class="event-excerpt">Festival Durian Lolong merupakan bagian dari Lolong Culture Festival, mempromosikan durian Pekalongan dan potensi wisata daerah.</p>
          <a href="<?php echo isset($_SESSION['user_id']) ? 'booking.php?event=family-gathering' : 'login.php'; ?>" class="btn btn-primary">Daftar Sekarang</a>
        </div>
      </div>
      <div class="event-card" data-aos="fade-up" data-aos-delay="200">
        <div class="event-image">
          <img src="assets/images/events/corporate-outbound.jpg" alt="Corporate Outbound">
          <div class="event-date">
            <span class="event-day">25</span>
            <span class="event-month">Juli</span>
          </div>
          <span class="event-category">Corporate</span>
        </div>
        <div class="event-content">
          <h3>Corporate Outbound Training</h3>
          <div class="event-meta">
            <span><i class="fas fa-map-marker-alt"></i> Area Outbound</span>
            <span><i class="fas fa-clock"></i> 08.00 - 17.00</span>
          </div>
          <p class="event-excerpt">Program untuk meningkatkan teamwork dan leadership di alam.</p>
          <a href="<?php echo isset($_SESSION['user_id']) ? 'booking.php?event=corporate-outbound' : 'login.php'; ?>" class="btn btn-primary">Daftar Sekarang</a>
        </div>
      </div>
      <div class="event-card" data-aos="fade-up" data-aos-delay="300">
        <div class="event-image">
          <img src="assets/images/events/night-camping.jpg" alt="Night Camping">
          <div class="event-date">
            <span class="event-day">30</span>
            <span class="event-month">Juli</span>
          </div>
          <span class="event-category">Adventure</span>
        </div>
        <div class="event-content">
          <h3>Night Camping Adventure</h3>
          <div class="event-meta">
            <span><i class="fas fa-map-marker-alt"></i> Camping Ground</span>
            <span><i class="fas fa-clock"></i> 16.00 - 10.00</span>
          </div>
          <p class="event-excerpt">Camping malam dengan api unggun, observasi bintang, dan trekking.</p>
          <a href="<?php echo isset($_SESSION['user_id']) ? 'booking.php?event=night-camping' : 'login.php'; ?>" class="btn btn-primary">Daftar Sekarang</a>
        </div>
      </div>
    </div>
    <div class="view-all-events" data-aos="fade-up">
      <a href="#" class="btn btn-secondary">Lihat Semua Event</a>
    </div>
  </section>

  <!-- Map & Location Section -->
  <section class="map-section" id="location">
    <div class="section-title" data-aos="fade-up">
      <h2>Lokasi Kami</h2>
      <p>Temukan jalur menuju basecamp Lolong Adventure dengan mudah</p>
    </div>
    <div class="map-container">
      <div class="map-wrapper" data-aos="fade-up" data-aos-delay="200">
        <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d63362.0374630634!2d109.6119104!3d-7.0688584!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x2e701ec04b9c8b5b%3A0xd2f6294441946c79!2sLolong%20Adventure!5e0!3m2!1sid!2sid!4v1717130623456!5m2!1sid!2sid" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
      </div>
    </div>
    <div class="nearby-attractions" data-aos="fade-up">
      <h3 style="text-align: center; margin-bottom: 40px; color: var(--primary);">Tempat Menarik di Sekitar</h3>
      <div class="attractions-grid">
        <div class="attraction-card">
          <div class="attraction-image">
            <img src="assets/images/landingpage/sipare.webp" alt="Sipare Green Park">
          </div>
          <div class="attraction-content">
            <h4>Sipare Green Park</h4>
            <p>Wisata alam yang asri dengan udara sejuk, melalui perkebunan belimbing dan karet.</p>
            <div class="attraction-meta">
              <span class="attraction-distance"><i class="fas fa-location-arrow"></i> 3.5 km dari basecamp</span>
            </div>
          </div>
        </div>
        <div class="attraction-card">
          <div class="attraction-image">
            <img src="assets/images/landingpage/sigarung.jpg" alt="Sigarung">
          </div>
          <div class="attraction-content">
            <h4>Sigarung</h4>
            <p>Kedai kopi khas Lolong dengan cita rasa unik dan berbagai jenis durian.</p>
            <div class="attraction-meta">
              <span class="attraction-distance"><i class="fas fa-location-arrow"></i> 8 km dari basecamp</span>
            </div>
          </div>
        </div>
        <div class="attraction-card">
          <div class="attraction-image">
            <img src="assets/images/landingpage/sokolangit.webp" alt="Soko Langit">
          </div>
          <div class="attraction-content">
            <h4>Soko Langit</h4>
            <p>Destinasi wisata edukasi dengan Dino Park untuk anak-anak.</p>
            <div class="attraction-meta">
              <span class="attraction-distance"><i class="fas fa-location-arrow"></i> 12 km dari basecamp</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- CTA Section -->
  <section class="cta-section">
    <div class="cta-content">
      <h2 data-aos="fade-up">Mulai Petualangan Anda Sekarang!</h2>
      <p data-aos="fade-up" data-aos-delay="200">Pesan tiket Anda dan nikmati pengalaman wisata alam terbaik di Pekalongan.</p>
      <div class="cta-buttons" data-aos="fade-up" data-aos-delay="400">
        <a href="<?php echo isset($_SESSION['user_id']) ? 'booking.php' : 'login.php'; ?>" class="btn btn-primary"><i class="fas fa-ticket-alt"></i> Pesan Tiket</a>
        <a href="https://wa inseparable: true;">https://wa.me/6281229952175" class="btn btn-secondary"><i class="fab fa-whatsapp"></i> WhatsApp</a>
      </div>
    </div>
  </section>

  <!-- Footer -->
  <footer id="contact">
    <div class="footer-content">
      <div class="footer-column" data-aos="fade-up">
        <h3>Tentang Kami</h3>
        <p>Lolong Adventure adalah penyedia jasa wisata alam profesional di Pekalongan dengan pengalaman lebih dari 10 tahun melayani ribuan pelanggan.</p>
        <div class="social-links">
          <a href="#"><i class="fab fa-facebook-f"></i></a>
          <a href="#"><i class="fab fa-instagram"></i></a>
          <a href="#"><i class="fab fa-youtube"></i></a>
          <a href="#"><i class="fab fa-tiktok"></i></a>
        </div>
      </div>
      <div class="footer-column" data-aos="fade-up" data-aos-delay="100">
        <h3>Link Cepat</h3>
        <ul class="footer-links">
          <li><a href="#home">Beranda</a></li>
          <li><a href="#about">Tentang Kami</a></li>
          <li><a href="#destinations">Destinasi</a></li>
          <li><a href="#events">Event</a></li>
          <li><a href="#location">Lokasi</a></li>
          <li><a href="<?php echo isset($_SESSION['user_id']) ? 'booking.php' : 'login.php'; ?>">Pesan Tiket</a></li>
        </ul>
      </div>
      <div class="footer-column" data-aos="fade-up" data-aos-delay="200">
        <h3>Kontak Kami</h3>
        <p><i class="fas fa-map-marker-alt"></i> Jl. Raya Lolong No. 123, Pekalongan, Jawa Tengah</p>
        <p><i class="fas fa-phone-alt"></i> +62 812 3456 7890</p>
        <p><i class="fas fa-envelope"></i> info@lolongadventure.com</p>
        <p><i class="fas fa-clock"></i> Buka setiap hari 08.00 - 17.00 WIB</p>
      </div>
      <div class="footer-column" data-aos="fade-up" data-aos-delay="300">
        <h3>Newsletter</h3>
        <p>Daftar untuk mendapatkan informasi promo dan event terbaru dari kami.</p>
        <div class="newsletter-form">
          <input type="email" placeholder="Alamat Email Anda" required>
          <button type="submit" class="btn btn-primary">Berlangganan</button>
        </div>
      </div>
    </div>
    <div class="footer-bottom">
      <p>© 2025 Lolong Adventure. All Rights Reserved.</p>
    </div>
  </footer>

  <!-- Bottom Navigation (Only for Logged-in Users) -->
  <?php if (isset($_SESSION['user_id'])): ?>
    <nav class="bottom-nav">
      <a href="#home" class="active"><i class="fas fa-home"></i> Beranda</a>
      <a href="status_pemesanan.php"><i class="fas fa-clipboard-list"></i> Status Pemesanan</a>
      <a href="profile.php"><i class="fas fa-user"></i> Profil</a>
    </nav>
  <?php endif; ?>

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

    const particlesContainer = document.getElementById('particles');
    for (let i = 0; i < 25; i++) {
      const particle = document.createElement('div');
      particle.classList.add('particle');
      const size = Math.random() * 12 + 6;
      const posX = Math.random() * 100;
      const posY = Math.random() * 100;
      const delay = Math.random() * 20;
      const duration = Math.random() * 25 + 15;
      particle.style.width = `${size}px`;
      particle.style.height = `${size}px`;
      particle.style.left = `${posX}%`;
      particle.style.top = `${posY}%`;
      particle.style.animationDelay = `${delay}s`;
      particle.style.animationDuration = `${duration}s`;
      particlesContainer.appendChild(particle);
    }
  </script>
</body>

</html>