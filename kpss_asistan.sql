-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 22, 2026 at 10:43 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `kpss_asistan`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_log`
--

CREATE TABLE `activity_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `activity_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_log`
--

INSERT INTO `activity_log` (`id`, `user_id`, `activity_date`) VALUES
(4, 1, '2026-04-17'),
(3, 1, '2026-04-20'),
(2, 1, '2026-04-21');

-- --------------------------------------------------------

--
-- Table structure for table `notes`
--

CREATE TABLE `notes` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `topic_name` varchar(100) NOT NULL,
  `hashtags` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pomodoro_log`
--

CREATE TABLE `pomodoro_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `odak_dakika` int(11) NOT NULL,
  `calisma_tarihi` date DEFAULT curdate()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pomodoro_log`
--

INSERT INTO `pomodoro_log` (`id`, `user_id`, `odak_dakika`, `calisma_tarihi`) VALUES
(1, 1, 5, '2026-04-22');

-- --------------------------------------------------------

--
-- Table structure for table `progress`
--

CREATE TABLE `progress` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `lesson_name` varchar(50) NOT NULL,
  `topic_name` varchar(100) NOT NULL,
  `task_name` varchar(100) NOT NULL,
  `is_completed` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `progress`
--

INSERT INTO `progress` (`id`, `user_id`, `lesson_name`, `topic_name`, `task_name`, `is_completed`) VALUES
(1, 1, 'Matematik', 'İşlem Yeteneği', '0', 0),
(2, 1, 'Matematik', 'İşlem Yeteneği', '1', 0),
(3, 1, 'Matematik', 'İşlem Yeteneği', '2', 0),
(4, 1, 'Türkçe', 'Noktalama İşaretleri', '0', 0),
(5, 1, 'Türkçe', 'Noktalama İşaretleri', '1', 0),
(6, 1, 'Türkçe', 'Noktalama İşaretleri', '2', 0),
(7, 1, 'Tarih', 'İlk ve Orta Çağlarda Türk Dünyası', '0', 0),
(8, 1, 'Tarih', 'İlk ve Orta Çağlarda Türk Dünyası', '1', 0),
(9, 1, 'Tarih', 'İlk ve Orta Çağlarda Türk Dünyası', '2', 0),
(10, 1, 'Matematik', 'Temel Kavramlar', '0', 0),
(11, 1, 'Matematik', 'Temel Kavramlar', '1', 0),
(12, 1, 'Matematik', 'Temel Kavramlar', '2', 0),
(13, 1, 'Türkçe', 'Ses Bilgisi', '0', 0),
(14, 1, 'Türkçe', 'Ses Bilgisi', '1', 0),
(15, 1, 'Türkçe', 'Ses Bilgisi', '2', 0);

-- --------------------------------------------------------

--
-- Table structure for table `tekrar_kutusu`
--

CREATE TABLE `tekrar_kutusu` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `ders_adi` varchar(50) NOT NULL,
  `kategori` varchar(20) NOT NULL,
  `icerik` text NOT NULL,
  `olusturma_tarihi` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `profil_foto` varchar(255) DEFAULT 'default_pp.png',
  `bio` varchar(255) DEFAULT 'KPSS yolcusu kalmasın...',
  `sinav_tipi` varchar(20) DEFAULT 'Ortaöğretim',
  `isim` varchar(100) DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `created_at`, `profil_foto`, `bio`, `sinav_tipi`, `isim`) VALUES
(1, 'zeynepss', '$2y$10$jhRWXIlqZs/OMlGdTTbUh.S5azGDPN95H3UMWeRi/eTzMtE7ohCSy', '2026-04-22 14:31:03', 'pp_1_1776886005.jpg', 'KPSS yolcusu kalmasın...', 'Ortaöğretim', 'Zeynep'),
(2, 'deneme', '$2y$10$qVhmCNQbrS5sasKN/i29VOjqNnP/itJdoDWbZOjfaaAXJiScVDk2S', '2026-04-22 14:59:32', 'default_pp.png', 'KPSS yolcusu kalmasın...', 'Önlisans', 'kişi');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`,`activity_date`);

--
-- Indexes for table `notes`
--
ALTER TABLE `notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `pomodoro_log`
--
ALTER TABLE `pomodoro_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `progress`
--
ALTER TABLE `progress`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `tekrar_kutusu`
--
ALTER TABLE `tekrar_kutusu`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `notes`
--
ALTER TABLE `notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pomodoro_log`
--
ALTER TABLE `pomodoro_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `progress`
--
ALTER TABLE `progress`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `tekrar_kutusu`
--
ALTER TABLE `tekrar_kutusu`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `notes`
--
ALTER TABLE `notes`
  ADD CONSTRAINT `notes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pomodoro_log`
--
ALTER TABLE `pomodoro_log`
  ADD CONSTRAINT `pomodoro_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `progress`
--
ALTER TABLE `progress`
  ADD CONSTRAINT `progress_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
