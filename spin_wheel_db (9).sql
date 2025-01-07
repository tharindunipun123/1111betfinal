-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 07, 2025 at 06:58 AM
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
-- Database: `spin_wheel_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `betting_results`
--

CREATE TABLE `betting_results` (
  `id` int(11) NOT NULL,
  `userId` int(11) NOT NULL,
  `round` int(11) NOT NULL,
  `multiplier` float NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cricket_bets`
--

CREATE TABLE `cricket_bets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `match_id` int(11) NOT NULL,
  `bet_type` enum('team1_win','team2_win','draw','full_target_yes','full_target_no','six_over_target_yes','six_over_target_no') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `multiplier` decimal(5,2) NOT NULL,
  `status` enum('pending','won','lost') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `winnings` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cricket_bets`
--

INSERT INTO `cricket_bets` (`id`, `user_id`, `match_id`, `bet_type`, `amount`, `multiplier`, `status`, `created_at`, `winnings`) VALUES
(1, 1, 1, 'team1_win', 100.00, 10.00, 'pending', '2024-12-29 12:57:00', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `cricket_matches`
--

CREATE TABLE `cricket_matches` (
  `id` int(11) NOT NULL,
  `team1` varchar(100) NOT NULL,
  `team2` varchar(100) NOT NULL,
  `match_time` datetime NOT NULL,
  `status` enum('upcoming','live','completed') DEFAULT 'upcoming',
  `result` enum('team1_win','team2_win','draw','pending') DEFAULT 'pending',
  `draw_multiplier` decimal(5,2) DEFAULT 1.00,
  `facebook_live_link` varchar(255) DEFAULT NULL,
  `is_locked` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `team1_win_multiplier` decimal(5,2) DEFAULT 1.00,
  `team2_win_multiplier` decimal(5,2) DEFAULT 1.00,
  `full_target_multiplier_yes` decimal(5,2) DEFAULT 2.00,
  `full_target_multiplier_no` decimal(5,2) DEFAULT 2.00,
  `six_over_target_multiplier_yes` decimal(5,2) DEFAULT 2.00,
  `six_over_target_multiplier_no` decimal(5,2) DEFAULT 2.00,
  `full_target_locked` tinyint(1) DEFAULT 0,
  `six_over_target_locked` tinyint(1) DEFAULT 0,
  `full_target_result` enum('pending','yes','no') DEFAULT 'pending',
  `six_over_target_result` enum('pending','yes','no') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cricket_matches`
--

INSERT INTO `cricket_matches` (`id`, `team1`, `team2`, `match_time`, `status`, `result`, `draw_multiplier`, `facebook_live_link`, `is_locked`, `created_at`, `team1_win_multiplier`, `team2_win_multiplier`, `full_target_multiplier_yes`, `full_target_multiplier_no`, `six_over_target_multiplier_yes`, `six_over_target_multiplier_no`, `full_target_locked`, `six_over_target_locked`, `full_target_result`, `six_over_target_result`) VALUES
(1, '', '', '2024-12-29 18:26:00', 'upcoming', 'pending', 10.00, '', 0, '2024-12-29 12:56:48', 10.00, 10.00, 10.00, 10.00, 10.00, 10.00, 0, 0, 'pending', 'pending'),
(2, '', '', '2024-12-29 18:26:00', 'upcoming', 'pending', 10.00, '', 0, '2024-12-29 12:57:19', 10.00, 10.00, 10.00, 10.00, 10.00, 10.00, 0, 0, 'pending', 'pending'),
(3, '', '', '2024-12-29 18:26:00', 'upcoming', 'pending', 10.00, '', 0, '2024-12-29 12:57:21', 10.00, 10.00, 10.00, 10.00, 10.00, 10.00, 0, 0, 'pending', 'pending');

-- --------------------------------------------------------

--
-- Table structure for table `dice_betting_results`
--

CREATE TABLE `dice_betting_results` (
  `id` int(11) NOT NULL,
  `userId` int(11) NOT NULL,
  `round` int(11) NOT NULL,
  `multiplier` float NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dice_rounds`
--

CREATE TABLE `dice_rounds` (
  `id` int(11) NOT NULL,
  `round_number` int(11) NOT NULL,
  `updated_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `winning_multiplier` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lucky_betting_results`
--

CREATE TABLE `lucky_betting_results` (
  `id` int(11) NOT NULL,
  `userId` int(11) NOT NULL,
  `round` int(11) NOT NULL,
  `multiplier` float NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lucky_manual_set`
--

CREATE TABLE `lucky_manual_set` (
  `id` int(11) NOT NULL,
  `round_number` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lucky_rounds`
--

CREATE TABLE `lucky_rounds` (
  `id` int(11) NOT NULL,
  `round_number` int(11) NOT NULL,
  `updated_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `winning_multiplier` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lucky_win_history`
--

CREATE TABLE `lucky_win_history` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `round_number` int(11) NOT NULL,
  `winning_multiplier` decimal(10,2) NOT NULL,
  `win_amount` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `manual_set`
--

CREATE TABLE `manual_set` (
  `id` int(11) NOT NULL,
  `round_number` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `match_teams`
--

CREATE TABLE `match_teams` (
  `id` int(11) NOT NULL,
  `match_id` int(11) NOT NULL,
  `team_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `match_teams`
--

INSERT INTO `match_teams` (`id`, `match_id`, `team_name`, `created_at`) VALUES
(1, 1, 'angoda', '2024-12-29 12:56:48'),
(2, 1, 'ambilipitiya', '2024-12-29 12:56:48'),
(3, 2, 'angoda', '2024-12-29 12:57:19'),
(4, 2, 'ambilipitiya', '2024-12-29 12:57:19'),
(5, 3, 'angoda', '2024-12-29 12:57:21'),
(6, 3, 'ambilipitiya', '2024-12-29 12:57:21');

-- --------------------------------------------------------

--
-- Table structure for table `payment_receipts`
--

CREATE TABLE `payment_receipts` (
  `id` int(11) NOT NULL,
  `bank_name` varchar(255) DEFAULT NULL,
  `reference_number` varchar(255) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `receipt_url` varchar(1000) DEFAULT NULL,
  `status` enum('pending','verified','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `user_id` int(11) DEFAULT NULL,
  `users_id` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payment_receipts`
--

INSERT INTO `payment_receipts` (`id`, `bank_name`, `reference_number`, `amount`, `receipt_url`, `status`, `created_at`, `updated_at`, `user_id`, `users_id`) VALUES
(1, 'Commercial', '7894561234599', 1000.00, '/uploads/1733095749102.jpeg', 'pending', '2024-12-01 23:29:09', '2024-12-01 23:29:09', NULL, NULL),
(2, '0769146421', '0769146421', 500.00, '/uploads/1733130226395.jpeg', 'pending', '2024-12-02 09:03:46', '2024-12-02 09:03:46', NULL, '16');

-- --------------------------------------------------------

--
-- Table structure for table `rounds`
--

CREATE TABLE `rounds` (
  `id` int(11) NOT NULL,
  `round_number` int(11) NOT NULL,
  `updated_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `winning_multiplier` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `wallet` decimal(10,2) DEFAULT 1000.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password_hash`, `wallet`) VALUES
(1, 'tharindu', '$2a$10$2ra9NA8KPEtCbAoMjjgnBOfKzmoiwA/PWR3UK9bHvUHWbd6FIdAAi', 732.00),
(5, 'mali', '$2a$10$0LB5qRyvkn9Tdfl5E1cZrefhC50LTu1DUWUrzo9BUpIR5MmeI7pJC', 7864.00),
(7, 'tharu', '$2a$10$a2y3ZLepxq/9YqWRE8eyOeDYlLQ5ZhV7l0xAUoxnNp39iBVUC8r..', 115.00),
(8, 'admin', '$2a$10$MRazUqNkk/oiqDxMlcDpU.dkQRjkMkkCTxXFAnubIEjXmT04x5hCe', 964.00);

-- --------------------------------------------------------

--
-- Table structure for table `win_history`
--

CREATE TABLE `win_history` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `round_number` int(11) NOT NULL,
  `winning_multiplier` decimal(10,2) NOT NULL,
  `win_amount` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `withdrawal_requests`
--

CREATE TABLE `withdrawal_requests` (
  `id` int(11) NOT NULL,
  `user_name` varchar(255) DEFAULT NULL,
  `bank_name` varchar(255) DEFAULT NULL,
  `account_number` varchar(255) DEFAULT NULL,
  `account_holder_name` varchar(255) DEFAULT NULL,
  `ifsc_code` varchar(50) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `user_id` int(11) DEFAULT NULL,
  `users_id` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `withdrawal_requests`
--

INSERT INTO `withdrawal_requests` (`id`, `user_name`, `bank_name`, `account_number`, `account_holder_name`, `ifsc_code`, `amount`, `notes`, `status`, `created_at`, `updated_at`, `user_id`, `users_id`) VALUES
(1, 'tharindunipun', 'Commercial', '7894561234599', 'Tharindu Nipun', 'CSEDERTY', 5000.00, 'no', 'pending', '2024-12-01 23:26:50', '2024-12-01 23:26:50', NULL, NULL),
(2, '', '', '0769146421', 'Tharindu Nipun', '', 5000.00, '', 'pending', '2024-12-02 00:05:24', '2024-12-02 00:05:24', NULL, NULL),
(3, '', '', '0769146421', 'Tharindu Nipun', '', 5000.00, '', 'pending', '2024-12-02 00:08:40', '2024-12-02 00:08:40', NULL, NULL),
(6, 'tharindunipun', 'Commercial', '0769146421', 'Tharindu Nipun', 'CSEDERTY', 5000.00, '', 'pending', '2024-12-02 08:41:15', '2024-12-02 08:41:15', NULL, NULL),
(8, 'tharindunipun', 'Commercial', '0769146421', 'Tharindu Nipun', 'CSEDERTY', 5000.00, '', 'pending', '2024-12-02 08:48:15', '2024-12-02 08:48:15', NULL, '16'),
(9, '', '', '0769146421', 'Tharindu Nipun', '', 5000.00, 'no', 'pending', '2024-12-02 08:49:52', '2024-12-02 08:49:52', NULL, '16'),
(10, '', '', '0769146421', 'tharindunipun', '', 5000.00, 'no', 'pending', '2024-12-02 09:03:01', '2024-12-02 09:03:01', NULL, '16');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `betting_results`
--
ALTER TABLE `betting_results`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `cricket_bets`
--
ALTER TABLE `cricket_bets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `match_id` (`match_id`);

--
-- Indexes for table `cricket_matches`
--
ALTER TABLE `cricket_matches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `dice_betting_results`
--
ALTER TABLE `dice_betting_results`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `dice_rounds`
--
ALTER TABLE `dice_rounds`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `manual_set`
--
ALTER TABLE `manual_set`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `match_teams`
--
ALTER TABLE `match_teams`
  ADD PRIMARY KEY (`id`),
  ADD KEY `match_id` (`match_id`);

--
-- Indexes for table `payment_receipts`
--
ALTER TABLE `payment_receipts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `rounds`
--
ALTER TABLE `rounds`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `win_history`
--
ALTER TABLE `win_history`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `withdrawal_requests`
--
ALTER TABLE `withdrawal_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_withdrawal_user` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `betting_results`
--
ALTER TABLE `betting_results`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cricket_bets`
--
ALTER TABLE `cricket_bets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `cricket_matches`
--
ALTER TABLE `cricket_matches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `dice_betting_results`
--
ALTER TABLE `dice_betting_results`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dice_rounds`
--
ALTER TABLE `dice_rounds`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `manual_set`
--
ALTER TABLE `manual_set`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `match_teams`
--
ALTER TABLE `match_teams`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `payment_receipts`
--
ALTER TABLE `payment_receipts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `rounds`
--
ALTER TABLE `rounds`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `win_history`
--
ALTER TABLE `win_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `withdrawal_requests`
--
ALTER TABLE `withdrawal_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `cricket_bets`
--
ALTER TABLE `cricket_bets`
  ADD CONSTRAINT `cricket_bets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `cricket_bets_ibfk_2` FOREIGN KEY (`match_id`) REFERENCES `cricket_matches` (`id`);

--
-- Constraints for table `match_teams`
--
ALTER TABLE `match_teams`
  ADD CONSTRAINT `match_teams_ibfk_1` FOREIGN KEY (`match_id`) REFERENCES `cricket_matches` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payment_receipts`
--
ALTER TABLE `payment_receipts`
  ADD CONSTRAINT `fk_payment_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `withdrawal_requests`
--
ALTER TABLE `withdrawal_requests`
  ADD CONSTRAINT `fk_withdrawal_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
