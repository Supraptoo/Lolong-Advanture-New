-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 13, 2025 at 11:29 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `lolong_adventure`
--

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `booking_code` varchar(20) NOT NULL,
  `destination_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `booking_date` datetime NOT NULL,
  `participants` int(11) NOT NULL,
  `special_requests` text DEFAULT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `status` enum('pending','confirmed','completed','cancelled','failed') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `payment_status` enum('pending','paid','failed') DEFAULT 'pending',
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_token` varchar(255) DEFAULT NULL,
  `payment_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payment_data`)),
  `ticket_type` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `booking_code`, `destination_id`, `user_id`, `booking_date`, `participants`, `special_requests`, `total_price`, `status`, `notes`, `created_at`, `updated_at`, `payment_status`, `payment_method`, `payment_token`, `payment_data`, `ticket_type`) VALUES
(10, '', 10, 11, '2025-04-15 00:00:00', 20, NULL, 1500000.00, 'cancelled', '', '2025-04-15 06:14:06', '2025-07-04 19:41:47', 'pending', 'bca', NULL, '{\"transaction_status\": \"pending\", \"payment_method\": \"bca\"}', 'perorangan'),
(11, '', 11, 8, '2025-04-25 00:00:00', 1, 'pp', 0.00, 'cancelled', NULL, '2025-04-25 07:57:03', '2025-07-04 19:41:57', 'pending', 'bca', NULL, '{\"transaction_status\": \"pending\", \"payment_method\": \"bca\"}', 'perorangan'),
(12, '', 11, 11, '2025-04-25 00:00:00', 1, 'ijD', 0.00, 'cancelled', NULL, '2025-04-25 08:03:43', '2025-06-10 03:37:27', 'pending', 'bca', NULL, '{\"transaction_status\": \"pending\", \"payment_method\": \"bca\"}', 'perorangan'),
(13, '', 9, 8, '2025-06-17 00:00:00', 1, NULL, 60000.00, 'cancelled', '', '2025-06-03 04:34:27', '2025-07-04 19:42:06', 'pending', 'bca', NULL, '{\"transaction_status\": \"pending\", \"payment_method\": \"bca\"}', 'perorangan');

-- --------------------------------------------------------

--
-- Table structure for table `contacts`
--

CREATE TABLE `contacts` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `subject` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `google_id` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `destinations`
--

CREATE TABLE `destinations` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `location` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `duration` varchar(50) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `difficulty` enum('easy','medium','hard') DEFAULT NULL,
  `image_url` varchar(255) DEFAULT 'default.jpg',
  `is_featured` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('active','inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `destinations`
--

INSERT INTO `destinations` (`id`, `name`, `location`, `description`, `duration`, `price`, `difficulty`, `image_url`, `is_featured`, `created_at`, `updated_at`, `status`) VALUES
(9, 'RAFTING', 'lolong', 'Pengalaman arung jeram di Sungai Sengkarang dengan berbagai pilihan rute', '2-3 jam', 175000.00, 'medium', 'dest_67fdff6bc2050.jpg', 1, '2025-04-15 06:10:57', '2025-07-04 19:19:43', 'active'),
(10, 'PAINTBALL', 'Lapangan lolong', 'Permainan paintball dengan fasilitas lengkap', '1-2 jam', 125000.00, 'easy', 'dest_67fdff5e06e5a.jpg', 1, '2025-04-15 06:12:35', '2025-07-04 19:19:43', 'active'),
(11, 'OUTBOUND', 'Buper lolong', 'Kegiatan outbound dengan fasilitas dokumentasi dan air mineral', 'Custom', 75000.00, 'easy', 'dest_67fdff4ceb1f2.jpg', 1, '2025-04-15 06:13:34', '2025-07-04 19:19:43', 'active'),
(14, 'new durian', 'lolong', 'test', '1', 1000.00, 'easy', 'dest_683d908d9b8e8.jpg', 0, '2025-06-02 11:52:45', '2025-06-02 11:52:45', 'active'),
(15, 'CAMPING', 'Lolong', 'Area camping dengan fasilitas tenda dome dan equipment lengkap', 'Custom', 100000.00, 'easy', 'default.jpg', 1, '2025-07-04 19:19:43', '2025-07-04 19:19:43', 'active'),
(16, 'AULA', 'Lolong', 'Ruang aula dengan fasilitas soundsystem dan proyektor', 'Custom', 400000.00, 'easy', 'default.jpg', 1, '2025-07-04 19:19:43', '2025-07-04 19:19:43', 'active'),
(17, 'HOME STAY', 'Lolong', 'Penginapan dengan fasilitas lengkap termasuk wifi dan karaoke', 'Custom', 500000.00, 'easy', 'default.jpg', 1, '2025-07-04 19:19:43', '2025-07-04 19:19:43', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `event_date` date NOT NULL,
  `location` varchar(255) NOT NULL,
  `max_participants` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `registered_participants` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`id`, `title`, `description`, `event_date`, `location`, `max_participants`, `price`, `image_url`, `created_at`, `updated_at`, `is_active`, `registered_participants`) VALUES
(1, 'EVENT BARU', 'bagus', '2025-04-17', 'lolong', 1, 100000.00, 'event_67ffeab10d09a2.32598160.jpg', '2025-04-16 17:36:49', '2025-04-16 17:50:32', 1, 0),
(2, 'pppp', 'jojoij', '9999-09-09', 'kpoj', 9, 900000.00, 'event_67fff77eeee927.50792641.jpg', '2025-04-16 18:31:26', '2025-04-16 18:31:26', 1, 0);

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `status` enum('pending','paid','failed') DEFAULT 'pending',
  `payment_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `booking_id`, `amount`, `payment_method`, `status`, `payment_date`) VALUES
(7, 11, 70000.00, 'ovo', 'pending', '2025-04-25 07:57:16'),
(8, 12, 70000.00, 'bca', 'pending', '2025-04-25 08:03:53');

-- --------------------------------------------------------

--
-- Table structure for table `testimonials`
--

CREATE TABLE `testimonials` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `author_name` varchar(100) NOT NULL,
  `author_role` varchar(100) DEFAULT NULL,
  `content` text NOT NULL,
  `rating` tinyint(1) NOT NULL,
  `is_approved` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `profile_pic` varchar(255) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `role` enum('admin','manager','staff') DEFAULT 'staff',
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  `token_expires` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `google_id` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `password`, `full_name`, `profile_pic`, `email`, `phone`, `address`, `role`, `is_active`, `last_login`, `reset_token`, `token_expires`, `created_at`, `updated_at`, `created_by`, `google_id`) VALUES
(8, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin Lolong', NULL, 'admin@lolongadventure.com', '085326785775', NULL, 'admin', 1, NULL, NULL, NULL, '2025-03-26 14:57:39', '2025-07-04 19:19:43', NULL, NULL),
(11, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user1', NULL, 'user@example.com', NULL, NULL, '', 1, NULL, NULL, NULL, '2025-04-09 20:01:01', '2025-04-13 08:56:45', NULL, NULL),
(12, '', 'suprapto', NULL, 'supraptouyeuye@gmail.com', '081229952175', NULL, '', 1, NULL, NULL, NULL, '2025-06-10 03:13:44', '2025-06-10 03:13:44', NULL, NULL),
(13, '', 'prapto', NULL, 'ssuprapto351@gmail.com', '0919879872', NULL, '', 1, NULL, NULL, NULL, '2025-07-12 09:21:29', '2025-07-12 09:21:29', NULL, NULL),
(14, '', 'Su prapto', NULL, 'sup29686@gmail.com', NULL, NULL, 'staff', 1, NULL, NULL, NULL, '2025-07-13 08:01:41', '2025-07-13 08:01:41', NULL, '104891930692322915625'),
(19, '', 'User Two', NULL, 'userytprem02@gmail.com', NULL, NULL, 'staff', 1, NULL, NULL, NULL, '2025-07-13 08:23:45', '2025-07-13 08:23:45', NULL, '108217415719315876821');

-- --------------------------------------------------------

--
-- Table structure for table `user_activities`
--

CREATE TABLE `user_activities` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `activity_description` varchar(255) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` varchar(255) NOT NULL,
  `activity_time` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_booking_code` (`booking_code`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_destination_id` (`destination_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `contacts`
--
ALTER TABLE `contacts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `destinations`
--
ALTER TABLE `destinations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_id` (`booking_id`);

--
-- Indexes for table `testimonials`
--
ALTER TABLE `testimonials`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `idx_email` (`email`),
  ADD KEY `fk_created_by` (`created_by`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_role` (`role`);

--
-- Indexes for table `user_activities`
--
ALTER TABLE `user_activities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `contacts`
--
ALTER TABLE `contacts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `destinations`
--
ALTER TABLE `destinations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `testimonials`
--
ALTER TABLE `testimonials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `user_activities`
--
ALTER TABLE `user_activities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`destination_id`) REFERENCES `destinations` (`id`),
  ADD CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_destination` FOREIGN KEY (`destination_id`) REFERENCES `destinations` (`id`),
  ADD CONSTRAINT `fk_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`);

--
-- Constraints for table `testimonials`
--
ALTER TABLE `testimonials`
  ADD CONSTRAINT `testimonials_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`),
  ADD CONSTRAINT `testimonials_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
