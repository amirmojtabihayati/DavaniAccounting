-- phpMyAdmin SQL Dump
-- version 5.1.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Apr 09, 2025 at 09:51 PM
-- Server version: 5.7.36
-- PHP Version: 8.0.13

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `davani_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `debts`
--

DROP TABLE IF EXISTS `debts`;
CREATE TABLE IF NOT EXISTS `debts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `amount` decimal(10,0) NOT NULL,
  `title` varchar(100) COLLATE utf8_persian_ci NOT NULL,
  `approval_number` varchar(50) COLLATE utf8_persian_ci NOT NULL,
  `date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `approval_number` (`approval_number`)
) ENGINE=MyISAM AUTO_INCREMENT=14 DEFAULT CHARSET=utf8 COLLATE=utf8_persian_ci;

--
-- Dumping data for table `debts`
--

INSERT INTO `debts` (`id`, `student_id`, `amount`, `title`, `approval_number`, `date`, `created_at`) VALUES
(13, 1, '1000000', 'سایر...', '11', '2024-09-17', '2025-04-05 15:04:45');

-- --------------------------------------------------------

--
-- Table structure for table `installments`
--

DROP TABLE IF EXISTS `installments`;
CREATE TABLE IF NOT EXISTS `installments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `national_code` varchar(10) COLLATE utf8_persian_ci NOT NULL,
  `debt_amount` decimal(10,2) NOT NULL,
  `debt_title` varchar(100) COLLATE utf8_persian_ci NOT NULL,
  `due_date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_persian_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
CREATE TABLE IF NOT EXISTS `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `amount_paid` decimal(10,0) NOT NULL,
  `payment_date` date NOT NULL,
  `payment_title` varchar(255) COLLATE utf8_persian_ci NOT NULL,
  `transaction_number` varchar(255) COLLATE utf8_persian_ci NOT NULL,
  `payment_type` varchar(100) COLLATE utf8_persian_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8 COLLATE=utf8_persian_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `student_id`, `amount_paid`, `payment_date`, `payment_title`, `transaction_number`, `payment_type`, `created_at`) VALUES
(3, 1, '1000000', '2025-12-31', 'شهریه هیئت امنایی', '155', 'کارتخوان', '2025-03-24 18:33:09');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

DROP TABLE IF EXISTS `students`;
CREATE TABLE IF NOT EXISTS `students` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `national_code` varchar(10) COLLATE utf8_persian_ci NOT NULL,
  `first_name` varchar(50) COLLATE utf8_persian_ci NOT NULL,
  `last_name` varchar(50) COLLATE utf8_persian_ci NOT NULL,
  `field` varchar(50) COLLATE utf8_persian_ci DEFAULT NULL,
  `grade` varchar(50) COLLATE utf8_persian_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `national_code` (`national_code`)
) ENGINE=MyISAM AUTO_INCREMENT=25 DEFAULT CHARSET=utf8 COLLATE=utf8_persian_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `national_code`, `first_name`, `last_name`, `field`, `grade`, `created_at`) VALUES
(1, '1810920752', 'حسین', 'مطور', 'شبکه و نرم افزار', 'دوازدهم', '2025-03-17 14:55:17'),
(2, '1862020123', 'عباس', 'شریان', 'الکتروتکنیک', 'دوازدهم', '2025-03-17 14:55:55'),
(3, '1825657433', 'علی', 'حاتمی کیا', 'الکترونیک', 'دوازدهم', '2025-03-18 17:09:31'),
(4, '1812453455', 'یوسف', 'قاسمی', 'شبکه و نرم افزار', 'دهم', '2025-03-18 17:10:44'),
(5, '1813099095', 'حامد', 'شاکر نژاد', 'الکتروتکنیک', 'دوازدهم', '2025-03-18 17:11:50'),
(6, '1792053214', 'علی', 'احمدیانی', 'الکترونیک', 'دهم', '2025-03-19 14:33:17'),
(7, '1820920753', 'داوود', 'مهاجرانی', 'شبکه و نرم افزار', 'یازدهم', '2025-03-19 14:33:17'),
(8, '1853250214', 'حسن', 'نصرالله', 'الکتروتکنیک', 'دوازدهم', '2025-03-19 14:33:17'),
(10, '1816542123', 'محمد', 'بحرانی', 'شبکه و نرم افزار', 'یازدهم', '2025-03-19 14:46:39'),
(16, '1926532258', 'یاسر', 'احمدیانی', 'الکترونیک', 'دهم', '2025-03-19 16:24:59'),
(15, '1933256258', 'حسین', 'کرباسی', 'الکتروتکنیک', 'یازدهم', '2025-03-19 16:17:12'),
(17, '6541236654', 'جواد', 'بلغمی', 'شبکه و نرم افزار', 'دهم', '2025-03-19 16:24:59'),
(18, '9826547789', 'مجی', 'حیاتی', 'الکتروتکنیک', 'دهم', '2025-03-19 16:24:59'),
(19, '1945612258', 'سمیر', 'احمدیانی', 'الکترونیک', 'یازدهم', '2025-03-23 17:32:39'),
(20, '6547532454', 'رامبد', 'بلغمی', 'شبکه و نرم افزار', 'دوازدهم', '2025-03-23 17:32:39'),
(21, '9829856789', 'وحید', 'حیاتی', 'الکتروتکنیک', 'یازدهم', '2025-03-23 17:32:39'),
(22, '1810920753', 'حسین', 'مطور', 'شبکه و نرم افزار', 'یازدهم', '2025-03-23 17:48:44'),
(23, '1820950254', 'سمیر', 'احمدیانی', 'الکترونیک', 'یازدهم', '2025-03-23 17:50:19'),
(24, '1812453459', 'حسین', 'حاتمی کیا', 'الکتروتکنیک', 'دهم', '2025-03-24 16:44:54');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) COLLATE utf8_persian_ci NOT NULL,
  `password` varchar(255) COLLATE utf8_persian_ci NOT NULL,
  `role` enum('admin','user') COLLATE utf8_persian_ci NOT NULL DEFAULT 'user',
  `profile` varchar(255) COLLATE utf8_persian_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=MyISAM AUTO_INCREMENT=8 DEFAULT CHARSET=utf8 COLLATE=utf8_persian_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `profile`, `created_at`) VALUES
(1, 'hossein', '$2y$10$m5453488yU4GiK5FjRIOpeRGJiyfWTVjNb04HQsYbf0dapsUNAapO', 'admin', NULL, '2025-03-21 21:02:58'),
(2, 'حسین', '$2y$10$80cwAWDUiIXSublVkjhZ2OfKbdwbCQK9ibMl/Vv426kBwcpKaqzUe', 'admin', NULL, '2025-03-21 21:46:33'),
(3, 'mahi', '$2y$10$qEFQhovUL22N0aB01NpHnO/d9y5d0sExogUM7RhhSu2joZvcMVdXG', 'user', 'uploads/Desktop (2).png', '2025-03-21 21:47:08'),
(4, 'abbi', '$2y$10$U9xFx9fg./W4idKBwDFs0eIXdWmDhyWaNh/7Of6Zm0YXkO8TzwQj6', 'user', NULL, '2025-03-21 21:49:53'),
(5, 'دانی', '$2y$10$nMiNlBEViSSGkDVLRXne/eBvN5BwN8GjxusXqZBAPdnEso4XoBE1i', 'admin', NULL, '2025-03-21 22:08:24'),
(6, 'حسین45', '$2y$10$Zrb2JfOieFwYpfvOQbq0J.o56t4HIeLIy6da0XhiAD.5Ik/bdu1CC', 'admin', 'uploads/Desktop (127).jpg', '2025-03-24 16:48:21'),
(7, 'مجتبی', '$2y$10$RBvE.s5YlWe5bQ/MQflB5uey0w8I2pOoDiIjtg2pI4tqvMWP0cseu', 'user', 'uploads/Screenshot (1).png', '2025-04-08 08:21:17');
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
