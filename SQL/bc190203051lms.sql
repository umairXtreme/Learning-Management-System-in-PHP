-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 12, 2025 at 09:02 PM
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
-- Database: `bc190203051lms`
--

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `id` int(11) NOT NULL,
  `title` varchar(80) NOT NULL,
  `short_description` text DEFAULT NULL,
  `long_description` longtext DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `thumbnail` varchar(255) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `duration` enum('3 Weeks','6 Weeks','3 Months','6 Months','1 Year','2 Year') NOT NULL DEFAULT '3 Weeks',
  `rating` decimal(3,1) NOT NULL DEFAULT 0.0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('Published','Draft') NOT NULL DEFAULT 'Draft',
  `instructor_id` int(11) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `category` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `course_instructors`
--

CREATE TABLE `course_instructors` (
  `id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `instructor_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `enrollments`
--

CREATE TABLE `enrollments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `enrolled_at` datetime DEFAULT current_timestamp(),
  `completed_at` datetime DEFAULT NULL,
  `progress` int(11) DEFAULT 0 CHECK (`progress` between 0 and 100),
  `status` enum('Active','Completed','Dropped','Pending') DEFAULT NULL,
  `is_deleted` tinyint(1) DEFAULT 0,
  `enrollment_id` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lessons`
--

CREATE TABLE `lessons` (
  `id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `video_url` text NOT NULL,
  `is_demo` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `content` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `course_id` int(11) DEFAULT NULL,
  `enrollment_id` varchar(50) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `status` enum('Pending','Success','Failed') DEFAULT NULL,
  `reference_number` varchar(255) DEFAULT NULL,
  `method` enum('JazzCash','EasyPaisa','BankTransfer') DEFAULT NULL,
  `account_holder_name` varchar(255) DEFAULT NULL,
  `billing_address` text DEFAULT NULL,
  `account_number` varchar(50) DEFAULT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `branch_code` varchar(20) DEFAULT NULL,
  `swift_code` varchar(50) DEFAULT NULL,
  `iban` varchar(100) DEFAULT NULL,
  `transaction_date` datetime DEFAULT current_timestamp(),
  `admin_note` text DEFAULT NULL,
  `proof_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_methods`
--

CREATE TABLE `payment_methods` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `method` enum('JazzCash','EasyPaisa','BankTransfer') NOT NULL,
  `account_holder_name` varchar(100) NOT NULL,
  `account_number` varchar(30) NOT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `branch_name` varchar(100) DEFAULT NULL,
  `swift_code` varchar(50) DEFAULT NULL,
  `iban` varchar(50) DEFAULT NULL,
  `billing_address` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_proofs`
--

CREATE TABLE `payment_proofs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `enrollment_id` varchar(50) NOT NULL,
  `course_id` int(11) NOT NULL,
  `method` varchar(100) NOT NULL,
  `account_number` varchar(50) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `reference_number` varchar(100) NOT NULL,
  `proof_file` varchar(255) NOT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `rating` int(11) NOT NULL CHECK (`rating` between 1 and 5),
  `content` text NOT NULL,
  `instructor_reply` text DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('student','instructor','admin') DEFAULT 'student',
  `resume` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `profile_picture` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `full_name`, `email`, `password`, `role`, `resume`, `status`, `created_at`, `profile_picture`, `phone`) VALUES
(4, 'bc190203051', 'Umair Maqbool', 'bc190203051@vu.edu.pk', '$2y$10$cwo2.VNEhXXTJ3mB3WRPjeJEk4gVro1/K6Lcc1WH.jtWsed78NbiW', 'admin', NULL, 'approved', '2025-03-12 10:42:53', 'admin_4_1746565670.png', '03208034571'),
(30, 'bc1902030512', 'Inst Umair M', 'bc1902030512@vu.edu.pk', '$2y$10$ZF0Rx.mZvaUaBE/Ub8yWC.MLJvuHAV3EXU4DqqMT0W7aiR3KOrDVa', 'instructor', NULL, 'approved', '2025-05-12 18:57:24', NULL, NULL),
(31, 'bc1902030513', 'Std Umair M', 'bc1902030513@vu.edu.pk', '$2y$10$2x.ra8AujRYk2gvF3/07tuclzObNU/ijlJXAH1aJruui3JIg82Ezi', 'student', NULL, 'approved', '2025-05-12 18:58:18', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_lessons`
--

CREATE TABLE `user_lessons` (
  `id` int(11) NOT NULL,
  `enrollment_id` int(11) NOT NULL,
  `lesson_id` int(11) NOT NULL,
  `started_at` datetime DEFAULT NULL,
  `last_watched_at` datetime DEFAULT NULL,
  `progress_percent` tinyint(4) DEFAULT 0,
  `is_completed` tinyint(1) DEFAULT 0,
  `completed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `course_instructors`
--
ALTER TABLE `course_instructors`
  ADD PRIMARY KEY (`id`),
  ADD KEY `course_id` (`course_id`),
  ADD KEY `instructor_id` (`instructor_id`);

--
-- Indexes for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `enrollment_id` (`enrollment_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `lessons`
--
ALTER TABLE `lessons`
  ADD PRIMARY KEY (`id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payment_methods`
--
ALTER TABLE `payment_methods`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `payment_proofs`
--
ALTER TABLE `payment_proofs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_lessons`
--
ALTER TABLE `user_lessons`
  ADD PRIMARY KEY (`id`),
  ADD KEY `enrollment_id` (`enrollment_id`),
  ADD KEY `lesson_id` (`lesson_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `course_instructors`
--
ALTER TABLE `course_instructors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `enrollments`
--
ALTER TABLE `enrollments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `lessons`
--
ALTER TABLE `lessons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `payment_methods`
--
ALTER TABLE `payment_methods`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `payment_proofs`
--
ALTER TABLE `payment_proofs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `user_lessons`
--
ALTER TABLE `user_lessons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `courses`
--
ALTER TABLE `courses`
  ADD CONSTRAINT `courses_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`);

--
-- Constraints for table `course_instructors`
--
ALTER TABLE `course_instructors`
  ADD CONSTRAINT `course_instructors_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `course_instructors_ibfk_2` FOREIGN KEY (`instructor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD CONSTRAINT `enrollments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `enrollments_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `lessons`
--
ALTER TABLE `lessons`
  ADD CONSTRAINT `lessons_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payment_methods`
--
ALTER TABLE `payment_methods`
  ADD CONSTRAINT `payment_methods_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`);

--
-- Constraints for table `user_lessons`
--
ALTER TABLE `user_lessons`
  ADD CONSTRAINT `user_lessons_ibfk_1` FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_lessons_ibfk_2` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
