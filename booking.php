<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL & ~E_DEPRECATED); // Sementara nonaktifkan peringatan deprecated

session_start();
require_once __DIR__ . '/config/database.php';

// Debug: Periksa keberadaan autoloader
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    die('Autoloader vendor tidak ditemukan. Jalankan "composer install" di ' . __DIR__);
}

require_once __DIR__ . '/vendor/autoload.php';

// Periksa apakah pelanggan sudah login
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    $_SESSION['error_message'] = "Silakan login sebagai pelanggan untuk memesan tiket.";
    header("Location: login.php");
    exit;
}

// Initialize Midtrans library
try {
    require_once __DIR__ . '/midtrans/midtrans-php/Midtrans.php';
} catch (Exception $e) {
    die("Error: Gagal memuat pustaka Midtrans: " . htmlspecialchars($e->getMessage()));
}

// Konfigurasi Midtrans
\Midtrans\Config::$serverKey = 'SB-Mid-server-iq5sAGE3ZDMshI7d9mbUEu2-';
\Midtrans\Config::$clientKey = 'SB-Mid-client-b1N1iraCqIGw6yNT';
\Midtrans\Config::$isProduction = false;
\Midtrans\Config::$isSanitized = true;
\Midtrans\Config::$is3ds = true;

// URL callback (ganti dengan URL publik atau Ngrok untuk pengujian)
$callback_url = 'https://your-domain.com/project_wisata/booking.php'; // Ganti dengan URL Ngrok atau domain publik

// Generate CSRF token jika belum ada
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Initialize atau perbarui data pemesanan di sesi
if (!isset($_SESSION['booking_data'])) {
    $_SESSION['booking_data'] = [
        'step' => 1,
        'destination_id' => '',
        'destination_name' => '',
        'ticket_type' => '',
        'quantity' => 1,
        'date' => '',
        'name' => $_SESSION['full_name'] ?? 'Pengguna',
        'email' => $_SESSION['email'] ?? '',
        'phone' => '',
        'total_price' => 0,
        'order_id' => '',
        'payment_status' => 'pending',
        'snap_token' => '',
        'transaction_status' => '',
        'payment_method' => 'bca'
    ];
} elseif (!isset($_SESSION['user_id'])) {
    $_SESSION['booking_data']['step'] = 1; // Reset ke langkah 1 jika tidak login
}

// Ambil destinasi dari database
try {
    $stmt = $pdo->query("SELECT id, name, price FROM destinations ORDER BY created_at DESC");
    $destinations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Gagal memuat destinasi: " . htmlspecialchars($e->getMessage());
    $destinations = [];
}

// Tangani pembaruan status transaksi dari Midtrans
if (isset($_GET['order_id']) && isset($_GET['status'])) {
    try {
        $booking_data = &$_SESSION['booking_data'];
        if ($_GET['order_id'] === $booking_data['order_id']) {
            switch ($_GET['status']) {
                case 'success':
                    $booking_data['payment_status'] = 'paid';
                    $booking_data['step'] = 5;
                    saveBookingToDatabase($pdo, $booking_data, 'completed');
                    resetBooking(); // Reset sesi setelah pembayaran berhasil
                    break;
                case 'pending':
                    $booking_data['payment_status'] = 'pending';
                    $booking_data['step'] = 4;
                    break;
                case 'error':
                case 'closed':
                    $booking_data['payment_status'] = 'failed';
                    $booking_data['step'] = 5;
                    saveBookingToDatabase($pdo, $booking_data, 'failed');
                    break;
            }
        } else {
            $_SESSION['error_message'] = "Order ID tidak sesuai.";
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Gagal memproses status transaksi: " . htmlspecialchars($e->getMessage());
        error_log("Error processing transaction status: " . $e->getMessage());
    }
}

// Proses pengiriman form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    try {
        // Validasi CSRF token
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception("Token CSRF tidak valid. Silakan coba lagi.");
        }

        // Langkah 1: Pilih destinasi dan tiket
        if (isset($_POST['next_step']) && $_SESSION['booking_data']['step'] == 1) {
            validateStep1($_POST, $destinations);
            $_SESSION['booking_data']['step'] = 2;
        }
        // Langkah 2: Informasi pribadi
        elseif (isset($_POST['next_step']) && $_SESSION['booking_data']['step'] == 2) {
            validateStep2($_POST);
            $_SESSION['booking_data']['step'] = 3;
        }
        // Langkah 3: Proses pembayaran
        elseif (isset($_POST['complete_booking']) && $_SESSION['booking_data']['step'] == 3) {
            processPayment($pdo, $_POST, $destinations);
            $_SESSION['booking_data']['step'] = 4;
        }
        // Langkah sebelumnya
        elseif (isset($_POST['prev_step'])) {
            $_SESSION['booking_data']['step'] = max(1, $_SESSION['booking_data']['step'] - 1);
        }
        // Batalkan pemesanan
        elseif (isset($_POST['cancel_booking'])) {
            cancelBooking($pdo);
        }
        // Coba ulang pembayaran
        elseif (isset($_POST['retry_payment'])) {
            processPayment($pdo, $_POST, $destinations);
            $_SESSION['booking_data']['step'] = 4;
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = htmlspecialchars($e->getMessage());
        error_log("Form processing error: " . $e->getMessage());
    }
    // Perbarui CSRF token setelah pengiriman form
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Periksa status transaksi secara berkala
if ($_SESSION['booking_data']['step'] == 4 && !empty($_SESSION['booking_data']['order_id']) && isset($_SESSION['user_id'])) {
    checkTransactionStatus($pdo);
}

/**
 * Validasi data form Langkah 1
 */
function validateStep1($postData, $destinations)
{
    if (empty($postData['destination_id'])) {
        throw new Exception("Silakan pilih destinasi.");
    }
    if (empty($postData['ticket_type'])) {
        throw new Exception("Silakan pilih jenis tiket.");
    }
    if (empty($postData['visit_date'])) {
        throw new Exception("Silakan pilih tanggal kunjungan.");
    }

    $booking_data = &$_SESSION['booking_data'];
    $booking_data['destination_id'] = filter_var($postData['destination_id'], FILTER_SANITIZE_NUMBER_INT);
    $booking_data['ticket_type'] = filter_var($postData['ticket_type'], FILTER_SANITIZE_STRING);
    $booking_data['quantity'] = max(1, filter_var($postData['quantity'], FILTER_SANITIZE_NUMBER_INT));
    $booking_data['date'] = filter_var($postData['visit_date'], FILTER_SANITIZE_STRING);

    // Validasi destinasi
    $destination_valid = false;
    $destination_price = 0;
    $destination_name = '';
    foreach ($destinations as $dest) {
        if ($dest['id'] == $booking_data['destination_id']) {
            $destination_valid = true;
            $destination_price = (float)$dest['price'];
            $destination_name = $dest['name'];
            break;
        }
    }

    if (!$destination_valid) {
        throw new Exception("Destinasi yang dipilih tidak valid.");
    }

    $booking_data['destination_name'] = $destination_name;

    // Validasi jenis tiket dan jumlah
    if (!in_array($booking_data['ticket_type'], ['perorangan', 'kelompok'])) {
        throw new Exception("Jenis tiket tidak valid.");
    }
    if ($booking_data['ticket_type'] == 'kelompok' && $booking_data['quantity'] < 5) {
        $booking_data['quantity'] = 5;
    }

    // Validasi tanggal kunjungan
    $visit_date = strtotime($booking_data['date']);
    $today = strtotime('2025-07-15 12:44:00'); // Tanggal saat ini
    if (!$visit_date || $visit_date < $today) {
        throw new Exception("Tanggal kunjungan harus setelah hari ini.");
    }

    // Hitung harga
    $ticket_price = $booking_data['ticket_type'] == 'perorangan' ? $destination_price : $destination_price * 0.85;
    $booking_data['total_price'] = $ticket_price * $booking_data['quantity'];
}

/**
 * Validasi data form Langkah 2
 */
function validateStep2($postData)
{
    if (empty($postData['name'])) {
        throw new Exception("Nama lengkap harus diisi.");
    }
    if (empty($postData['email'])) {
        throw new Exception("Email harus diisi.");
    }
    if (empty($postData['phone'])) {
        throw new Exception("Nomor telepon harus diisi.");
    }

    $booking_data = &$_SESSION['booking_data'];
    $booking_data['name'] = filter_var(trim($postData['name']), FILTER_SANITIZE_STRING);
    $booking_data['email'] = filter_var(trim($postData['email']), FILTER_SANITIZE_EMAIL);
    $booking_data['phone'] = filter_var(trim($postData['phone']), FILTER_SANITIZE_STRING);

    if (!filter_var($booking_data['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Format email tidak valid.");
    }
    if (!preg_match("/^[a-zA-Z\s]+$/", $booking_data['name'])) {
        throw new Exception("Nama hanya boleh berisi huruf dan spasi.");
    }
    if (!preg_match("/^[0-9]{10,13}$/", $booking_data['phone'])) {
        throw new Exception("Nomor telepon harus berisi 10-13 digit.");
    }
}

/**
 * Proses pembayaran dengan Midtrans
 */
function processPayment($pdo, $postData, $destinations)
{
    global $callback_url; // Gunakan URL callback global

    if (!isset($postData['agree_terms'])) {
        throw new Exception("Anda harus menyetujui syarat dan ketentuan.");
    }
    if (empty($postData['payment_method'])) {
        throw new Exception("Silakan pilih metode pembayaran.");
    }

    $booking_data = &$_SESSION['booking_data'];
    $booking_data['order_id'] = 'LOA-' . date('YmdHis') . '-' . mt_rand(1000, 9999);
    $booking_data['payment_method'] = filter_var($postData['payment_method'], FILTER_SANITIZE_STRING);

    $transaction_details = [
        'order_id' => $booking_data['order_id'],
        'gross_amount' => (float)$booking_data['total_price']
    ];

    $customer_details = [
        'first_name' => $booking_data['name'],
        'email' => $booking_data['email'],
        'phone' => $booking_data['phone']
    ];

    $destination_price = 0;
    $destination_name = '';
    foreach ($destinations as $dest) {
        if ($dest['id'] == $booking_data['destination_id']) {
            $destination_price = (float)$dest['price'];
            $destination_name = $dest['name'];
            break;
        }
    }

    $ticket_price = $booking_data['ticket_type'] == 'perorangan' ? $destination_price : $destination_price * 0.85;

    $item_details = [
        [
            'id' => 'tiket-' . $booking_data['destination_id'] . '-' . $booking_data['ticket_type'],
            'price' => $ticket_price,
            'quantity' => (int)$booking_data['quantity'],
            'name' => 'Tiket ' . $destination_name . ' (' . ucfirst($booking_data['ticket_type']) . ')',
            'brand' => 'Lolong Adventure',
            'category' => 'Tiket Wisata'
        ]
    ];

    $payment_method = $booking_data['payment_method'];
    $enable_payments = [];
    $params = [
        'transaction_details' => $transaction_details,
        'customer_details' => $customer_details,
        'item_details' => $item_details,
        'enabled_payments' => $enable_payments,
        'expiry' => [
            'unit' => 'hour',
            'duration' => 24
        ]
    ];

    switch ($payment_method) {
        case 'bca':
            $enable_payments = ['bank_transfer'];
            $params['bank_transfer'] = ['bank' => 'bca'];
            break;
        case 'mandiri':
            $enable_payments = ['bank_transfer'];
            $params['bank_transfer'] = ['bank' => 'mandiri'];
            break;
        case 'bri':
            $enable_payments = ['bank_transfer'];
            $params['bank_transfer'] = ['bank' => 'bri'];
            break;
        case 'bni':
            $enable_payments = ['bank_transfer'];
            $params['bank_transfer'] = ['bank' => 'bni'];
            break;
        case 'permata':
            $enable_payments = ['bank_transfer'];
            $params['bank_transfer'] = ['bank' => 'permata'];
            break;
        case 'cimb':
            $enable_payments = ['bank_transfer'];
            $params['bank_transfer'] = ['bank' => 'cimb'];
            break;
        case 'gopay':
            $enable_payments = ['gopay'];
            $params['gopay'] = ['enable_callback' => true, 'callback_url' => $callback_url];
            break;
        case 'dana':
            $enable_payments = ['dana'];
            $params['dana'] = ['enable_callback' => true, 'callback_url' => $callback_url];
            break;
        case 'ovo':
            $enable_payments = ['qris'];
            $params['qris'] = ['acquirer' => 'ovo', 'callback_url' => $callback_url];
            break;
        case 'shopeepay':
            $enable_payments = ['shopeepay'];
            $params['shopeepay'] = ['enable_callback' => true, 'callback_url' => $callback_url];
            break;
        default:
            throw new Exception("Metode pembayaran tidak valid.");
    }

    $params['enabled_payments'] = $enable_payments;

    try {
        $snapToken = \Midtrans\Snap::getSnapToken($params);
        $booking_data['snap_token'] = $snapToken;
        $booking_data['payment_status'] = 'pending';
        saveBookingToDatabase($pdo, $booking_data, 'pending');
    } catch (Exception $e) {
        throw new Exception("Gagal memproses pembayaran: " . htmlspecialchars($e->getMessage()));
    }
}

/**
 * Batalkan pemesanan dan perbarui status
 */
function cancelBooking($pdo)
{
    $booking_data = &$_SESSION['booking_data'];
    if (empty($booking_data['order_id'])) {
        throw new Exception("Tidak ada pemesanan untuk dibatalkan.");
    }

    try {
        \Midtrans\Transaction::cancel($booking_data['order_id']);
        $booking_data['payment_status'] = 'failed';
        $booking_data['step'] = 5;
        saveBookingToDatabase($pdo, $booking_data, 'cancelled');
        resetBooking();
    } catch (Exception $e) {
        throw new Exception("Gagal membatalkan pemesanan: " . htmlspecialchars($e->getMessage()));
    }
}

/**
 * Periksa status transaksi dengan Midtrans
 */
function checkTransactionStatus($pdo)
{
    $booking_data = &$_SESSION['booking_data'];
    if (!preg_match('/^LOA-\d{14}-\d{4}$/', $booking_data['order_id'])) {
        throw new Exception("Format ID pesanan tidak valid.");
    }

    try {
        $status = \Midtrans\Transaction::status($booking_data['order_id']);
        if (!is_object($status) || !isset($status->transaction_status)) {
            throw new Exception("Respon status transaksi dari Midtrans tidak valid.");
        }

        $booking_data['transaction_status'] = $status->transaction_status;
        switch ($status->transaction_status) {
            case 'settlement':
            case 'capture':
                $booking_data['payment_status'] = 'paid';
                $booking_data['step'] = 5;
                saveBookingToDatabase($pdo, $booking_data, 'completed');
                resetBooking(); // Reset sesi setelah pembayaran berhasil
                break;
            case 'pending':
                $booking_data['payment_status'] = 'pending';
                break;
            case 'expire':
            case 'cancel':
            case 'deny':
                $booking_data['payment_status'] = 'failed';
                $booking_data['step'] = 5;
                saveBookingToDatabase($pdo, $booking_data, $status->transaction_status == 'cancel' ? 'cancelled' : 'failed');
                break;
            default:
                throw new Exception("Status transaksi tidak dikenal: " . htmlspecialchars($status->transaction_status));
        }
    } catch (Exception $e) {
        error_log("Kesalahan pemeriksaan status transaksi: " . $e->getMessage());
        $_SESSION['error_message'] = "Gagal memeriksa status transaksi. Silakan coba lagi atau hubungi dukungan.";
    }
}

/**
 * Simpan pemesanan ke database
 */
function saveBookingToDatabase($pdo, $booking_data, $status)
{
    try {
        $user_id = $_SESSION['user_id'] ?? null;
        if (!$user_id) {
            throw new Exception("Sesi pengguna tidak ditemukan.");
        }

        $stmt = $pdo->prepare("SELECT id FROM bookings WHERE booking_code = ?");
        $stmt->execute([$booking_data['order_id']]);
        if ($stmt->fetch()) {
            $stmt = $pdo->prepare("UPDATE bookings SET 
                destination_id = ?, user_id = ?, booking_date = ?, participants = ?, 
                total_price = ?, status = ?, payment_status = ?, payment_method = ?, 
                payment_token = ?, payment_data = ?, updated_at = NOW() 
                WHERE booking_code = ?");
            $stmt->execute([
                $booking_data['destination_id'],
                $user_id,
                $booking_data['date'],
                $booking_data['quantity'],
                $booking_data['total_price'],
                $status,
                $booking_data['payment_status'],
                $booking_data['payment_method'],
                $booking_data['snap_token'] ?? null,
                json_encode([
                    'destination_name' => $booking_data['destination_name'],
                    'ticket_type' => $booking_data['ticket_type'],
                    'quantity' => $booking_data['quantity'],
                    'date' => $booking_data['date'],
                    'total_price' => $booking_data['total_price'],
                    'payment_method' => $booking_data['payment_method'],
                    'transaction_status' => $booking_data['transaction_status']
                ]),
                $booking_data['order_id']
            ]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO bookings (
                booking_code, destination_id, user_id, booking_date, participants, 
                special_requests, total_price, status, payment_status, payment_method, 
                payment_token, payment_data, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([
                $booking_data['order_id'],
                $booking_data['destination_id'],
                $user_id,
                $booking_data['date'],
                $booking_data['quantity'],
                '',
                $booking_data['total_price'],
                $status,
                $booking_data['payment_status'],
                $booking_data['payment_method'],
                $booking_data['snap_token'] ?? null,
                json_encode([
                    'destination_name' => $booking_data['destination_name'],
                    'ticket_type' => $booking_data['ticket_type'],
                    'quantity' => $booking_data['quantity'],
                    'date' => $booking_data['date'],
                    'total_price' => $booking_data['total_price'],
                    'payment_method' => $booking_data['payment_method'],
                    'transaction_status' => $booking_data['transaction_status']
                ])
            ]);
        }
        return true;
    } catch (PDOException $e) {
        error_log("Kesalahan database: " . $e->getMessage());
        return false;
    }
}

/**
 * Reset data pemesanan setelah selesai atau dibatalkan
 */
function resetBooking()
{
    $_SESSION['booking_data'] = [
        'step' => 1,
        'destination_id' => '',
        'destination_name' => '',
        'ticket_type' => '',
        'quantity' => 1,
        'date' => '',
        'name' => $_SESSION['full_name'] ?? 'Pengguna',
        'email' => $_SESSION['email'] ?? '',
        'phone' => '',
        'total_price' => 0,
        'order_id' => '',
        'payment_status' => 'pending',
        'snap_token' => '',
        'transaction_status' => '',
        'payment_method' => 'bca'
    ];
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pemesanan Tiket - Lolong Adventure</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #2a7f62;
            --secondary: #f8f9fa;
            --accent: #ff7e33;
            --text: #1a1a1a;
            --text-light: #5e5e5e;
            --white: #ffffff;
            --success-color: #28a745;
            --error-color: #dc3545;
            --border-color: #dfe1e5;
            --shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(145deg, var(--primary) 0%, #1a5c46 100%);
            margin: 0;
            padding: 2rem;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: flex-start;
        }

        .booking-container {
            max-width: 800px;
            background: var(--white);
            border-radius: 24px;
            box-shadow: var(--shadow);
            padding: 2.5rem;
            margin: 20px;
            animation: fadeIn 1s ease-out;
            position: relative;
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

        .booking-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .booking-header h1 {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            font-size: 2.5rem;
            color: var(--primary);
        }

        .user-info {
            text-align: center;
            margin-bottom: 1.5rem;
            font-size: 1rem;
            color: var(--text-light);
        }

        .progress-steps {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            position: relative;
            margin-bottom: 2rem;
            gap: 10px;
        }

        .progress-bar {
            position: absolute;
            top: 15px;
            left: 0;
            height: 6px;
            background: var(--accent);
            border-radius: 3px;
            transition: width 0.5s ease;
            z-index: 0;
        }

        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            flex: 1;
            min-width: 80px;
            position: relative;
            z-index: 1;
        }

        .step-number {
            width: 40px;
            height: 40px;
            background: var(--border-color);
            color: var(--text-light);
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }

        .step.completed .step-number,
        .step.active .step-number {
            background: var(--primary);
            color: var(--white);
        }

        .step-label {
            font-size: 0.85rem;
            color: var(--text-light);
            text-align: center;
            margin-top: 8px;
            word-wrap: break-word;
        }

        .step.active .step-label,
        .step.completed .step-label {
            color: var(--primary);
            font-weight: 500;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            font-weight: 500;
            color: var(--text);
            margin-bottom: 8px;
            display: block;
        }

        .form-control {
            border-radius: 12px;
            border: 1px solid var(--border-color);
            padding: 1rem;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 4px rgba(255, 126, 51, 0.15);
            outline: none;
        }

        .ticket-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .ticket-option {
            padding: 1.5rem;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            position: relative;
        }

        .ticket-option:hover {
            border-color: var(--primary);
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .ticket-option.selected {
            border-color: var(--primary);
            background: rgba(42, 127, 98, 0.05);
        }

        .discount-badge {
            position: absolute;
            top: -10px;
            right: 10px;
            background: var(--accent);
            color: var(--white);
            padding: 5px 12px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .ticket-option h3 {
            font-size: 1.2rem;
            margin-bottom: 10px;
            color: var(--text);
        }

        .ticket-option p {
            font-size: 0.9rem;
            color: var(--text-light);
            margin-bottom: 0;
        }

        .quantity-selector {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .quantity-selector button {
            background: var(--secondary);
            border: 1px solid var(--border-color);
            padding: 0.5rem 1rem;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1.2rem;
            transition: all 0.3s ease;
        }

        .quantity-selector button:hover {
            background: var(--primary);
            color: var(--white);
        }

        .quantity-selector input {
            width: 80px;
            text-align: center;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            padding: 0.5rem;
            font-size: 1rem;
        }

        .date-picker {
            position: relative;
            display: flex;
            align-items: center;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 0.5rem;
            background: var(--white);
            transition: all 0.3s ease;
        }

        .date-picker:hover {
            border-color: var(--primary);
            box-shadow: 0 0 8px rgba(42, 127, 98, 0.2);
        }

        .date-picker input {
            border: none;
            outline: none;
            flex: 1;
            padding: 0.5rem 1rem;
            font-size: 1rem;
            background: transparent;
        }

        .date-picker input:focus {
            box-shadow: none;
        }

        .date-picker i {
            color: var(--primary);
            font-size: 1.2rem;
            margin-right: 0.8rem;
        }

        .btn {
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.95rem;
        }

        .btn-primary {
            background: var(--primary);
            color: var(--white);
        }

        .btn-primary:hover {
            background: #1a5c46;
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(42, 127, 98, 0.35);
        }

        .btn-secondary {
            background: var(--text-light);
            color: var(--white);
        }

        .btn-secondary:hover {
            background: #4b4b4b;
            transform: translateY(-3px);
        }

        .btn-danger {
            background: var(--error-color);
            color: var(--white);
        }

        .btn-danger:hover {
            background: #c82333;
            transform: translateY(-3px);
        }

        .summary-card {
            background: var(--secondary);
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.8rem;
            font-size: 0.95rem;
        }

        .summary-row.total-price {
            margin-top: 0.8rem;
            padding-top: 0.8rem;
            border-top: 1px solid var(--border-color);
            font-weight: 600;
        }

        .summary-row.total-price span:last-child {
            color: var(--primary);
            font-size: 1.2rem;
        }

        .payment-methods {
            margin-bottom: 1.5rem;
        }

        .payment-selection {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .payment-selection span {
            font-weight: 500;
            color: var(--text);
        }

        .payment-method {
            display: flex;
            align-items: center;
            padding: 1rem;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .payment-method:hover {
            border-color: var(--primary);
            background: rgba(42, 127, 98, 0.05);
        }

        .payment-method img {
            width: 50px;
            height: 50px;
            object-fit: contain;
            margin-right: 1rem;
            border-radius: 4px;
        }

        .payment-method-info h4 {
            font-size: 1rem;
            margin: 0;
            color: var(--text);
        }

        .payment-method-info p {
            font-size: 0.85rem;
            color: var(--text-light);
            margin: 0;
        }

        .modal-content {
            border-radius: 15px;
        }

        .modal-header {
            background: var(--primary);
            color: var(--white);
            border-top-left-radius: 15px;
            border-top-right-radius: 15px;
        }

        .modal-title {
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .payment-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
        }

        .terms-checkbox {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            margin-bottom: 1.5rem;
        }

        .terms-checkbox input {
            width: 1.3rem;
            height: 1.3rem;
            accent-color: var(--primary);
        }

        .error-message {
            background: rgba(220, 53, 69, 0.1);
            color: var(--error-color);
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        .confirmation-message {
            text-align: center;
            padding: 2rem;
            background: var(--secondary);
            border-radius: 12px;
            margin-bottom: 1.5rem;
        }

        .confirmation-message i {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .booking-details {
            background: var(--secondary);
            padding: 1.5rem;
            border-radius: 12px;
            margin-top: 1rem;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.8rem;
            font-size: 0.95rem;
        }

        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 1.5rem;
            flex-wrap: wrap;
        }

        @media (max-width: 768px) {
            .booking-container {
                padding: 1.5rem;
                margin: 10px;
            }

            .ticket-options {
                grid-template-columns: 1fr;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }

            .payment-grid {
                grid-template-columns: 1fr;
            }

            .booking-header h1 {
                font-size: 2rem;
            }

            .progress-steps {
                gap: 5px;
            }

            .step {
                min-width: 60px;
            }

            .step-label {
                font-size: 0.75rem;
            }
        }

        @media (max-width: 576px) {
            .booking-container {
                padding: 1rem;
            }

            .booking-header h1 {
                font-size: 1.8rem;
            }

            .step {
                min-width: 50px;
            }

            .step-number {
                width: 35px;
                height: 35px;
                font-size: 1rem;
            }

            .step-label {
                font-size: 0.7rem;
            }

            .form-control {
                font-size: 0.9rem;
                padding: 0.8rem;
            }

            .btn {
                padding: 0.7rem 1.2rem;
                font-size: 0.9rem;
            }

            .payment-method img {
                width: 40px;
                height: 40px;
            }
        }

        @media (max-width: 375px) {
            .booking-container {
                padding: 0.8rem;
                margin: 5px;
            }

            .booking-header h1 {
                font-size: 1.5rem;
            }

            .step {
                min-width: 45px;
            }

            .step-number {
                width: 30px;
                height: 30px;
                font-size: 0.9rem;
            }

            .step-label {
                font-size: 0.65rem;
            }

            .form-control {
                font-size: 0.85rem;
                padding: 0.7rem;
            }

            .btn {
                padding: 0.6rem 1rem;
                font-size: 0.85rem;
            }

            .payment-method img {
                width: 35px;
                height: 35px;
            }
        }
    </style>
</head>

<body>
    <div class="booking-container">
        <div class="booking-header">
            <h1>Pemesanan Tiket - Lolong Adventure</h1>
            <div class="user-info">
                <p>Selamat datang, <strong><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Pelanggan'); ?></strong>!</p>
            </div>
        </div>
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="error-message">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <?php echo htmlspecialchars($_SESSION['error_message']);
                unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>

        <div class="progress-steps">
            <div class="progress-bar" style="width: <?php echo ($_SESSION['booking_data']['step'] - 1) * 25; ?>%;"></div>
            <?php
            $steps = ['Destinasi', 'Data Diri', 'Pembayaran', 'Konfirmasi', 'Selesai'];
            foreach ($steps as $index => $label):
                $step_number = $index + 1;
                $is_active = $_SESSION['booking_data']['step'] == $step_number;
                $is_completed = $_SESSION['booking_data']['step'] > $step_number;
            ?>
                <div class="step <?php echo $is_active ? 'active' : ($is_completed ? 'completed' : ''); ?>">
                    <div class="step-number"><?php echo $step_number; ?></div>
                    <div class="step-label"><?php echo $label; ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($_SESSION['booking_data']['step'] == 1): ?>
            <form method="POST" action="booking.php">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <div class="form-group">
                    <label for="destination_id">Pilih Destinasi</label>
                    <select name="destination_id" id="destination_id" class="form-control" required onchange="updateTicketPrices()">
                        <option value="">-- Pilih Destinasi --</option>
                        <?php foreach ($destinations as $dest): ?>
                            <option value="<?php echo htmlspecialchars($dest['id']); ?>"
                                data-price="<?php echo $dest['price']; ?>"
                                <?php echo $_SESSION['booking_data']['destination_id'] == $dest['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dest['name']); ?> (Rp <?php echo number_format($dest['price'], 0, ',', '.'); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Jenis Tiket</label>
                    <div class="ticket-options">
                        <div class="ticket-option <?php echo $_SESSION['booking_data']['ticket_type'] == 'perorangan' ? 'selected' : ''; ?>" onclick="selectTicketType('perorangan')">
                            <h3>Perorangan</h3>
                            <p id="price-perorangan">Pilih destinasi terlebih dahulu</p>
                            <input type="radio" name="ticket_type" value="perorangan" style="display:none;" <?php echo $_SESSION['booking_data']['ticket_type'] == 'perorangan' ? 'checked' : ''; ?>>
                        </div>
                        <div class="ticket-option <?php echo $_SESSION['booking_data']['ticket_type'] == 'kelompok' ? 'selected' : ''; ?>" onclick="selectTicketType('kelompok')">
                            <span class="discount-badge">Diskon 15%</span>
                            <h3>Kelompok</h3>
                            <p id="price-kelompok">Pilih destinasi terlebih dahulu</p>
                            <input type="radio" name="ticket_type" value="kelompok" style="display:none;" <?php echo $_SESSION['booking_data']['ticket_type'] == 'kelompok' ? 'checked' : ''; ?>>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label for="quantity">Jumlah Tiket</label>
                    <div class="quantity-selector">
                        <button type="button" onclick="decrementQuantity()">-</button>
                        <input type="number" name="quantity" id="quantity" class="form-control" min="1"
                            value="<?php echo $_SESSION['booking_data']['quantity']; ?>" required readonly>
                        <button type="button" onclick="incrementQuantity()">+</button>
                    </div>
                </div>
                <div class="form-group">
                    <label for="visit_date">Tanggal Kunjungan</label>
                    <div class="date-picker">
                        <i class="bi bi-calendar"></i>
                        <input type="date" name="visit_date" id="visit_date"
                            min="<?php echo date('Y-m-d', strtotime('+1 day', strtotime('2025-07-15 12:44:00'))); ?>"
                            value="<?php echo $_SESSION['booking_data']['date']; ?>" required>
                    </div>
                </div>
                <div class="action-buttons">
                    <button type="submit" name="next_step" id="next-step-btn" class="btn btn-primary" disabled>
                        <i class="bi bi-arrow-right"></i> Lanjut
                    </button>
                </div>
            </form>

        <?php elseif ($_SESSION['booking_data']['step'] == 2): ?>
            <form method="POST" action="booking.php" id="personal-info-form">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <div class="form-group">
                    <label for="name">Nama Lengkap</label>
                    <input type="text" name="name" id="name" class="form-control"
                        value="<?php echo htmlspecialchars($_SESSION['booking_data']['name']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" name="email" id="email" class="form-control"
                        value="<?php echo htmlspecialchars($_SESSION['booking_data']['email']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="phone">Nomor Telepon</label>
                    <input type="text" name="phone" id="phone" class="form-control"
                        value="<?php echo htmlspecialchars($_SESSION['booking_data']['phone']); ?>" required>
                </div>
                <div class="action-buttons">
                    <button type="submit" name="prev_step" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Kembali
                    </button>
                    <button type="submit" name="next_step" class="btn btn-primary" id="next-step-personal">
                        <i class="bi bi-arrow-right"></i> Lanjut
                    </button>
                </div>
            </form>

        <?php elseif ($_SESSION['booking_data']['step'] == 3): ?>
            <form method="POST" action="booking.php" id="payment-form">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="payment_method" id="payment_method" value="<?php echo htmlspecialchars($_SESSION['booking_data']['payment_method']); ?>">
                <div class="summary-card">
                    <h3>Ringkasan Pemesanan</h3>
                    <div class="summary-row">
                        <span>Destinasi</span>
                        <span><?php echo htmlspecialchars($_SESSION['booking_data']['destination_name']); ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Jenis Tiket</span>
                        <span><?php echo ucfirst($_SESSION['booking_data']['ticket_type']); ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Jumlah</span>
                        <span><?php echo $_SESSION['booking_data']['quantity']; ?> tiket</span>
                    </div>
                    <div class="summary-row">
                        <span>Tanggal</span>
                        <span><?php echo $_SESSION['booking_data']['date']; ?></span>
                    </div>
                    <div class="summary-row total-price">
                        <span>Total</span>
                        <span>Rp <?php echo number_format($_SESSION['booking_data']['total_price'], 0, ',', '.'); ?></span>
                    </div>
                </div>
                <div class="payment-methods">
                    <h3>Metode Pembayaran</h3>
                    <div class="payment-selection">
                        <span id="selected-payment">Belum memilih metode pembayaran</span>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#paymentModal">
                            <i class="bi bi-credit-card"></i> Pilih Metode
                        </button>
                    </div>
                </div>
                <div class="terms-checkbox">
                    <input type="checkbox" name="agree_terms" id="agree_terms" required>
                    <label for="agree_terms">Saya setuju dengan <a href="#" style="color: var(--primary);">syarat dan ketentuan</a></label>
                </div>
                <div class="action-buttons">
                    <button type="submit" name="prev_step" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Kembali
                    </button>
                    <button type="submit" name="complete_booking" class="btn btn-primary" id="pay-now-btn" disabled>
                        <i class="bi bi-credit-card"></i> Bayar Sekarang
                    </button>
                </div>
            </form>

            <!-- Modal Metode Pembayaran -->
            <div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="paymentModalLabel">Pilih Metode Pembayaran</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="payment-grid">
                                <div class="payment-method" onclick="selectPaymentMethod('bca', 'Bank BCA', 'Transfer bank')">
                                    <img src="./assets/images/payments/LOGO-BCA.png" alt="BCA" onerror="this.src='https://via.placeholder.com/50?text=BCA';">
                                    <div class="payment-method-info">
                                        <h4>Bank BCA</h4>
                                        <p>Transfer bank</p>
                                    </div>
                                </div>
                                <div class="payment-method" onclick="selectPaymentMethod('mandiri', 'Bank Mandiri', 'Transfer bank')">
                                    <img src="./assets/images/payments/LOGO-MANDIRI.png" alt="Mandiri" onerror="this.src='https://via.placeholder.com/50?text=Mandiri';">
                                    <div class="payment-method-info">
                                        <h4>Bank Mandiri</h4>
                                        <p>Transfer bank</p>
                                    </div>
                                </div>
                                <div class="payment-method" onclick="selectPaymentMethod('bri', 'Bank BRI', 'Transfer bank')">
                                    <img src="./assets/images/payments/LOGO-BRI.png" alt="BRI" onerror="this.src='https://via.placeholder.com/50?text=BRI';">
                                    <div class="payment-method-info">
                                        <h4>Bank BRI</h4>
                                        <p>Transfer bank</p>
                                    </div>
                                </div>
                                <div class="payment-method" onclick="selectPaymentMethod('bni', 'Bank BNI', 'Transfer bank')">
                                    <img src="./assets/images/payments/LOGO-BNI.png" alt="BNI" onerror="this.src='https://via.placeholder.com/50?text=BNI';">
                                    <div class="payment-method-info">
                                        <h4>Bank BNI</h4>
                                        <p>Transfer bank</p>
                                    </div>
                                </div>
                                <div class="payment-method" onclick="selectPaymentMethod('permata', 'Bank Permata', 'Transfer bank')">
                                    <img src="./assets/images/payments/LOGO-PERMATA.png" alt="Permata" onerror="this.src='https://via.placeholder.com/50?text=Permata';">
                                    <div class="payment-method-info">
                                        <h4>Bank Permata</h4>
                                        <p>Transfer bank</p>
                                    </div>
                                </div>
                                <div class="payment-method" onclick="selectPaymentMethod('cimb', 'Bank CIMB Niaga', 'Transfer bank')">
                                    <img src="./assets/images/payments/LOGO-CIMB.png" alt="CIMB" onerror="this.src='https://via.placeholder.com/50?text=CIMB';">
                                    <div class="payment-method-info">
                                        <h4>Bank CIMB Niaga</h4>
                                        <p>Transfer bank</p>
                                    </div>
                                </div>
                                <div class="payment-method" onclick="selectPaymentMethod('gopay', 'GoPay', 'QR code payment')">
                                    <img src="./assets/images/payments/LOGO-GOPAY.png" alt="GoPay" onerror="this.src='https://via.placeholder.com/50?text=GoPay';">
                                    <div class="payment-method-info">
                                        <h4>GoPay</h4>
                                        <p>QR code payment</p>
                                    </div>
                                </div>
                                <div class="payment-method" onclick="selectPaymentMethod('dana', 'DANA', 'QR code payment')">
                                    <img src="./assets/images/payments/LOGO-DANA.png" alt="DANA" onerror="this.src='https://via.placeholder.com/50?text=DANA';">
                                    <div class="payment-method-info">
                                        <h4>DANA</h4>
                                        <p>QR code payment</p>
                                    </div>
                                </div>
                                <div class="payment-method" onclick="selectPaymentMethod('ovo', 'OVO', 'Phone number')">
                                    <img src="./assets/images/payments/LOGO-OVO.png" alt="OVO" onerror="this.src='https://via.placeholder.com/50?text=OVO';">
                                    <div class="payment-method-info">
                                        <h4>OVO</h4>
                                        <p>Phone number</p>
                                    </div>
                                </div>
                                <div class="payment-method" onclick="selectPaymentMethod('shopeepay', 'ShopeePay', 'QR code payment')">
                                    <img src="./assets/images/payments/LOGO-SHOPPEPAY.png" alt="ShopeePay" onerror="this.src='https://via.placeholder.com/50?text=ShopeePay';">
                                    <div class="payment-method-info">
                                        <h4>ShopeePay</h4>
                                        <p>QR code payment</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($_SESSION['booking_data']['step'] == 4): ?>
            <div class="confirmation-message">
                <i class="bi bi-hourglass-split" style="color: var(--primary);"></i>
                <h3>Menunggu Pembayaran</h3>
                <p><strong>Order ID:</strong> <?php echo htmlspecialchars($_SESSION['booking_data']['order_id']); ?></p>
                <p><strong>Status:</strong> <?php echo ucfirst($_SESSION['booking_data']['payment_status']); ?></p>
            </div>
            <div id="snap-container"></div>
            <div class="action-buttons">
                <form method="POST" action="booking.php" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="payment_method" value="<?php echo htmlspecialchars($_SESSION['booking_data']['payment_method']); ?>">
                    <button type="submit" name="retry_payment" class="btn btn-primary">
                        <i class="bi bi-arrow-repeat"></i> Coba Lagi
                    </button>
                </form>
                <form method="POST" action="booking.php" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <button type="submit" name="cancel_booking" class="btn btn-danger">
                        <i class="bi bi-x-circle"></i> Batalkan
                    </button>
                </form>
            </div>
            <script src="https://app.sandbox.midtrans.com/snap/snap.js" data-client-key="<?php echo \Midtrans\Config::$clientKey; ?>"></script>
            <script>
                window.snap.pay('<?php echo htmlspecialchars($_SESSION['booking_data']['snap_token']); ?>', {
                    onSuccess: function(result) {
                        console.log('Payment success:', result);
                        window.location.href = 'booking.php?order_id=' + encodeURIComponent(result.order_id) + '&status=success';
                    },
                    onPending: function(result) {
                        console.log('Payment pending:', result);
                        window.location.href = 'booking.php?order_id=' + encodeURIComponent(result.order_id) + '&status=pending';
                    },
                    onError: function(result) {
                        console.log('Payment error:', result);
                        Swal.fire({
                            icon: 'error',
                            title: 'Pembayaran Gagal',
                            text: 'Terjadi kesalahan saat memproses pembayaran. Silakan coba lagi.',
                            confirmButtonText: 'OK'
                        });
                        window.location.href = 'booking.php?order_id=' + encodeURIComponent(result.order_id) + '&status=error';
                    },
                    onClose: function() {
                        console.log('Payment popup closed');
                        Swal.fire({
                            icon: 'info',
                            title: 'Pembayaran Dibatalkan',
                            text: 'Anda menutup jendela pembayaran. Silakan coba lagi atau batalkan pemesanan.',
                            confirmButtonText: 'OK'
                        });
                        window.location.href = 'booking.php?order_id=<?php echo urlencode($_SESSION['booking_data']['order_id']); ?>&status=closed';
                    }
                });
            </script>

        <?php elseif ($_SESSION['booking_data']['step'] == 5): ?>
            <div class="confirmation-message">
                <?php if ($_SESSION['booking_data']['payment_status'] == 'paid'): ?>
                    <i class="bi bi-check-circle-fill" style="color: var(--success-color);"></i>
                    <h3>Pembayaran Berhasil!</h3>
                    <p>Tiket berhasil dikirim ke email Anda.</p>
                <?php else: ?>
                    <i class="bi bi-x-circle-fill" style="color: var(--error-color);"></i>
                    <h3>Pemesanan Gagal</h3>
                    <p>Transaksi gagal atau dibatalkan.</p>
                <?php endif; ?>
            </div>
            <div class="booking-details">
                <h3>Detail Pemesanan</h3>
                <div class="detail-row">
                    <span class="label">Order ID</span>
                    <span><?php echo htmlspecialchars($_SESSION['booking_data']['order_id']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="label">Status</span>
                    <span><?php echo ucfirst($_SESSION['booking_data']['payment_status']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="label">Destinasi</span>
                    <span><?php echo htmlspecialchars($_SESSION['booking_data']['destination_name']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="label">Total</span>
                    <span>Rp <?php echo number_format($_SESSION['booking_data']['total_price'], 0, ',', '.'); ?></span>
                </div>
            </div>
            <div class="action-buttons">
                <a href="landingpage.php" class="btn btn-primary">
                    <i class="bi bi-house-door"></i> Kembali ke Beranda
                </a>
                <a href="status_pemesanan.php" class="btn btn-primary">
                    <i class="bi bi-clipboard-check"></i> Lihat Status Pemesanan Anda
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function selectTicketType(type) {
            document.querySelectorAll('.ticket-option').forEach(option => {
                option.classList.remove('selected');
            });
            document.querySelector(`.ticket-option[onclick="selectTicketType('${type}')"]`).classList.add('selected');
            document.querySelector(`input[value="${type}"]`).checked = true;
            updateQuantityConstraints(type);
            checkFormCompletion();
        }

        function updateQuantityConstraints(type) {
            const quantityInput = document.getElementById('quantity');
            if (type === 'kelompok' && parseInt(quantityInput.value) < 5) {
                quantityInput.value = 5;
            }
        }

        function incrementQuantity() {
            const quantityInput = document.getElementById('quantity');
            const ticketType = document.querySelector('input[name="ticket_type"]:checked')?.value;
            let quantity = parseInt(quantityInput.value);
            quantityInput.value = quantity + 1;
            if (ticketType === 'kelompok' && quantityInput.value < 5) {
                quantityInput.value = 5;
            }
            checkFormCompletion();
        }

        function decrementQuantity() {
            const quantityInput = document.getElementById('quantity');
            const ticketType = document.querySelector('input[name="ticket_type"]:checked')?.value;
            let quantity = parseInt(quantityInput.value);
            if (quantity > 1 && !(ticketType === 'kelompok' && quantity <= 5)) {
                quantityInput.value = quantity - 1;
            }
            checkFormCompletion();
        }

        function updateTicketPrices() {
            const destinationSelect = document.getElementById('destination_id');
            if (!destinationSelect) return;
            const selectedOption = destinationSelect.options[destinationSelect.selectedIndex];
            const price = selectedOption ? parseFloat(selectedOption.getAttribute('data-price')) : 0;

            if (price) {
                document.getElementById('price-perorangan').innerText = `Rp ${new Intl.NumberFormat('id-ID').format(price)}/orang`;
                document.getElementById('price-kelompok').innerText = `Rp ${new Intl.NumberFormat('id-ID').format(price * 0.85)}/orang`;
            } else {
                document.getElementById('price-perorangan').innerText = 'Pilih destinasi terlebih dahulu';
                document.getElementById('price-kelompok').innerText = 'Pilih destinasi terlebih dahulu';
            }
            checkFormCompletion();
        }

        function selectPaymentMethod(method, name, description) {
            document.getElementById('payment_method').value = method;
            document.getElementById('selected-payment').innerText = `${name} (${description})`;
            document.getElementById('pay-now-btn').disabled = false;
            const modal = bootstrap.Modal.getInstance(document.getElementById('paymentModal'));
            modal.hide();
        }

        function checkFormCompletion() {
            const destination = document.getElementById('destination_id')?.value;
            const ticketType = document.querySelector('input[name="ticket_type"]:checked')?.value;
            const visitDate = document.getElementById('visit_date')?.value;
            const nextButton = document.getElementById('next-step-btn');

            if (destination && ticketType && visitDate && nextButton) {
                nextButton.disabled = false;
            } else if (nextButton) {
                nextButton.disabled = true;
            }
        }

        function validateEmail(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }

        function checkPersonalInfoForm() {
            const name = document.getElementById('name')?.value.trim();
            const email = document.getElementById('email')?.value.trim();
            const phone = document.getElementById('phone')?.value.trim();
            const nextButton = document.getElementById('next-step-personal');
            const phoneRegex = /^[0-9]{10,13}$/;

            if (name && validateEmail(email) && phoneRegex.test(phone) && nextButton) {
                nextButton.disabled = false;
            } else if (nextButton) {
                nextButton.disabled = true;
            }
        }

        // Polling untuk memeriksa status transaksi
        <?php if ($_SESSION['booking_data']['step'] == 4 && !empty($_SESSION['booking_data']['order_id'])): ?>

            function checkTransactionStatus() {
                fetch('booking.php?order_id=<?php echo urlencode($_SESSION['booking_data']['order_id']); ?>&check_status=1', {
                        method: 'GET'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'settlement' || data.status === 'capture') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Pembayaran Berhasil!',
                                text: 'Transaksi Anda telah berhasil. Tiket telah dikirim ke email Anda.',
                                confirmButtonText: 'OK'
                            }).then(() => {
                                window.location.href = 'booking.php?order_id=<?php echo urlencode($_SESSION['booking_data']['order_id']); ?>&status=success';
                            });
                        } else if (data.status === 'expire' || data.status === 'cancel' || data.status === 'deny') {
                            Swal.fire({
                                icon: 'error',
                                title: 'Pembayaran Gagal',
                                text: 'Transaksi Anda gagal atau dibatalkan. Silakan coba lagi.',
                                confirmButtonText: 'OK'
                            }).then(() => {
                                window.location.href = 'booking.php?order_id=<?php echo urlencode($_SESSION['booking_data']['order_id']); ?>&status=error';
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error checking transaction status:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Kesalahan',
                            text: 'Gagal memeriksa status transaksi. Silakan coba lagi.',
                            confirmButtonText: 'OK'
                        });
                    });
            }

            setInterval(checkTransactionStatus, 10000); // Cek setiap 10 detik
        <?php endif; ?>

        // Tambahan untuk menangani pemeriksaan status transaksi melalui AJAX
        <?php
        if (isset($_GET['check_status']) && isset($_GET['order_id'])) {
            // Pastikan sesi booking_data dan order_id tersedia
            if (!isset($_SESSION['booking_data']['order_id']) || $_GET['order_id'] !== $_SESSION['booking_data']['order_id']) {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'Order ID tidak sesuai atau sesi tidak valid']);
                error_log("Order ID mismatch or session invalid: GET order_id={$_GET['order_id']}, Session order_id=" . ($_SESSION['booking_data']['order_id'] ?? 'null'));
                exit;
            }

            try {
                $status = \Midtrans\Transaction::status($_SESSION['booking_data']['order_id']);
                if (is_object($status) && isset($status->transaction_status)) {
                    header('Content-Type: application/json');
                    echo json_encode(['status' => $status->transaction_status]);
                } else {
                    header('Content-Type: application/json');
                    echo json_encode(['status' => 'error', 'message' => 'Invalid transaction status response from Midtrans']);
                    error_log("Invalid Midtrans response for order_id {$_SESSION['booking_data']['order_id']}: " . json_encode($status));
                }
                exit;
            } catch (Exception $e) {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
                error_log("Error checking transaction status for order_id {$_SESSION['booking_data']['order_id']}: " . $e->getMessage());
                exit;
            }
        }
        ?>

        document.addEventListener('DOMContentLoaded', () => {
            updateTicketPrices();
            checkFormCompletion();
            checkPersonalInfoForm();

            const destinationSelect = document.getElementById('destination_id');
            if (destinationSelect) {
                destinationSelect.addEventListener('change', () => {
                    updateTicketPrices();
                    checkFormCompletion();
                });
            }

            const visitDate = document.getElementById('visit_date');
            if (visitDate) {
                visitDate.addEventListener('change', checkFormCompletion);
            }

            document.querySelectorAll('input[name="ticket_type"]').forEach(radio => {
                radio.addEventListener('change', checkFormCompletion);
            });

            const personalInfoForm = document.getElementById('personal-info-form');
            if (personalInfoForm) {
                personalInfoForm.addEventListener('input', checkPersonalInfoForm);
            }

            const paymentMethod = document.getElementById('payment_method').value;
            if (paymentMethod) {
                let name = '',
                    description = '';
                switch (paymentMethod) {
                    case 'bca':
                        name = 'Bank BCA';
                        description = 'Transfer bank';
                        break;
                    case 'mandiri':
                        name = 'Bank Mandiri';
                        description = 'Transfer bank';
                        break;
                    case 'bri':
                        name = 'Bank BRI';
                        description = 'Transfer bank';
                        break;
                    case 'bni':
                        name = 'Bank BNI';
                        description = 'Transfer bank';
                        break;
                    case 'permata':
                        name = 'Bank Permata';
                        description = 'Transfer bank';
                        break;
                    case 'cimb':
                        name = 'Bank CIMB Niaga';
                        description = 'Transfer bank';
                        break;
                    case 'gopay':
                        name = 'GoPay';
                        description = 'QR code payment';
                        break;
                    case 'dana':
                        name = 'DANA';
                        description = 'QR code payment';
                        break;
                    case 'ovo':
                        name = 'OVO';
                        description = 'Phone number';
                        break;
                    case 'shopeepay':
                        name = 'ShopeePay';
                        description = 'QR code payment';
                        break;
                }
                if (name && description) {
                    document.getElementById('selected-payment').innerText = `${name} (${description})`;
                    document.getElementById('pay-now-btn').disabled = false;
                }
            }
        });
    </script>
</body>

</html>