-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 19, 2025 at 08:19 AM
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
-- Database: `it_request`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_log`
--

CREATE TABLE `activity_log` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `log_name` varchar(255) DEFAULT NULL,
  `description` text NOT NULL,
  `subject_type` varchar(255) DEFAULT NULL,
  `subject_id` bigint(20) UNSIGNED DEFAULT NULL,
  `causer_type` varchar(255) DEFAULT NULL,
  `causer_id` bigint(20) UNSIGNED DEFAULT NULL,
  `properties` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`properties`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `approvals`
--

CREATE TABLE `approvals` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `request_id` bigint(20) UNSIGNED NOT NULL,
  `approver_id` bigint(20) UNSIGNED NOT NULL,
  `level` int(11) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'Pending',
  `decided_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `assignments`
--

CREATE TABLE `assignments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `request_id` bigint(20) UNSIGNED NOT NULL,
  `assignee_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `assignee_department_id` bigint(20) UNSIGNED DEFAULT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `accepted_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attachments`
--

CREATE TABLE `attachments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `request_id` bigint(20) UNSIGNED NOT NULL,
  `uploaded_by` bigint(20) UNSIGNED NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `mime_type` varchar(50) DEFAULT NULL,
  `size` int(11) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cache`
--

CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cache_locks`
--

CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `category_department`
--

CREATE TABLE `category_department` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `category_id` bigint(20) UNSIGNED NOT NULL,
  `department_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `category_department`
--

INSERT INTO `category_department` (`id`, `category_id`, `department_id`, `created_at`, `updated_at`) VALUES
(1, 24, 101, '2025-09-02 03:16:06', '2025-09-02 03:29:33');

-- --------------------------------------------------------

--
-- Table structure for table `comments`
--

CREATE TABLE `comments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `request_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `comments`
--

INSERT INTO `comments` (`id`, `request_id`, `user_id`, `message`, `created_at`, `updated_at`) VALUES
(6, 16, 1, 'Komentar', '2025-09-02 08:59:23', '2025-09-02 08:59:23'),
(7, 18, 31, 'Tes', '2025-12-18 08:43:09', '2025-12-18 08:43:09'),
(8, 18, 1, 'Testing Balas Komen', '2025-12-18 08:46:53', '2025-12-18 08:46:53');

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `division_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `division_id`, `name`, `code`, `created_at`, `updated_at`, `email`) VALUES
(101, 101, 'Keamanan & Insfratukture', 'KIT', '2025-09-01 07:19:13', '2025-12-18 08:57:49', 'pamanbiriiin@gmail.com'),
(103, 101, 'Development Information Technology', 'DIT', '2025-12-18 08:57:39', '2025-12-18 08:57:39', 'muhamad.sobirin@lrtjakarta.co.id');

-- --------------------------------------------------------

--
-- Table structure for table `divisions`
--

CREATE TABLE `divisions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `divisions`
--

INSERT INTO `divisions` (`id`, `name`, `created_at`, `updated_at`) VALUES
(101, 'Information Technology', '2025-09-01 07:09:44', '2025-09-01 07:23:18'),
(102, 'HC & GA', '2025-12-18 08:55:11', '2025-12-18 08:55:31');

-- --------------------------------------------------------

--
-- Table structure for table `employee`
--

CREATE TABLE `employee` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `nik` varchar(255) NOT NULL,
  `nama_lengkap` varchar(255) NOT NULL,
  `jabatan` varchar(255) DEFAULT NULL,
  `division_id` bigint(20) UNSIGNED NOT NULL,
  `is_pic` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `escalation_rules`
--

CREATE TABLE `escalation_rules` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `sla_id` bigint(20) UNSIGNED NOT NULL,
  `threshold_minutes` int(11) NOT NULL,
  `escalate_to_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `escalate_to_department_id` bigint(20) UNSIGNED DEFAULT NULL,
  `notify_roles` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `impacts`
--

CREATE TABLE `impacts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(50) NOT NULL,
  `code` varchar(20) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `impacts`
--

INSERT INTO `impacts` (`id`, `name`, `code`, `created_at`, `updated_at`) VALUES
(1, 'Low', 'LOW', NULL, NULL),
(2, 'Medium', 'MED', NULL, NULL),
(4, 'High', 'HIGH', '2025-09-01 14:24:11', '2025-09-01 14:24:11');

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) UNSIGNED NOT NULL,
  `reserved_at` int(10) UNSIGNED DEFAULT NULL,
  `available_at` int(10) UNSIGNED NOT NULL,
  `created_at` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `jobs`
--

INSERT INTO `jobs` (`id`, `queue`, `payload`, `attempts`, `reserved_at`, `available_at`, `created_at`) VALUES
(1, 'default', '{\"uuid\":\"b4c71d5e-89a5-4803-bab9-7881223744d0\",\"displayName\":\"App\\\\Mail\\\\TicketCreated\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"data\":{\"commandName\":\"Illuminate\\\\Mail\\\\SendQueuedMailable\",\"command\":\"O:34:\\\"Illuminate\\\\Mail\\\\SendQueuedMailable\\\":15:{s:8:\\\"mailable\\\";O:22:\\\"App\\\\Mail\\\\TicketCreated\\\":4:{s:6:\\\"ticket\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:24:\\\"App\\\\Models\\\\Master\\\\Ticket\\\";s:2:\\\"id\\\";i:8;s:9:\\\"relations\\\";a:1:{i:0;s:9:\\\"requester\\\";}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:13:\\\"recipientType\\\";s:9:\\\"requester\\\";s:2:\\\"to\\\";a:1:{i:0;a:2:{s:4:\\\"name\\\";N;s:7:\\\"address\\\";s:22:\\\"pamanbiriiin@gmail.com\\\";}}s:6:\\\"mailer\\\";s:4:\\\"smtp\\\";}s:5:\\\"tries\\\";N;s:7:\\\"timeout\\\";N;s:13:\\\"maxExceptions\\\";N;s:17:\\\"shouldBeEncrypted\\\";b:0;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;s:3:\\\"job\\\";N;}\"}}', 0, NULL, 1756781054, 1756781054),
(2, 'default', '{\"uuid\":\"fac5d4e0-1c29-40f0-963e-98456ed070a3\",\"displayName\":\"App\\\\Mail\\\\TicketCreated\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"data\":{\"commandName\":\"Illuminate\\\\Mail\\\\SendQueuedMailable\",\"command\":\"O:34:\\\"Illuminate\\\\Mail\\\\SendQueuedMailable\\\":15:{s:8:\\\"mailable\\\";O:22:\\\"App\\\\Mail\\\\TicketCreated\\\":4:{s:6:\\\"ticket\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:24:\\\"App\\\\Models\\\\Master\\\\Ticket\\\";s:2:\\\"id\\\";i:9;s:9:\\\"relations\\\";a:1:{i:0;s:9:\\\"requester\\\";}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:13:\\\"recipientType\\\";s:9:\\\"requester\\\";s:2:\\\"to\\\";a:1:{i:0;a:2:{s:4:\\\"name\\\";N;s:7:\\\"address\\\";s:22:\\\"pamanbiriiin@gmail.com\\\";}}s:6:\\\"mailer\\\";s:4:\\\"smtp\\\";}s:5:\\\"tries\\\";N;s:7:\\\"timeout\\\";N;s:13:\\\"maxExceptions\\\";N;s:17:\\\"shouldBeEncrypted\\\";b:0;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;s:3:\\\"job\\\";N;}\"}}', 0, NULL, 1756781154, 1756781154);

-- --------------------------------------------------------

--
-- Table structure for table `job_batches`
--

CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `menus`
--

CREATE TABLE `menus` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `icon` varchar(255) DEFAULT NULL,
  `url` varchar(255) DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `role_id` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `menus`
--

INSERT INTO `menus` (`id`, `title`, `icon`, `url`, `parent_id`, `is_active`, `order`, `created_at`, `updated_at`, `role_id`) VALUES
(1, 'Dashboard', 'bi bi-speedometer2', 'dashboard.index', NULL, 1, 1, '2024-12-12 10:14:08', '2025-09-01 17:01:24', '[\"admin\",\"pic\",\"pelapor\"]'),
(10, 'Setting', 'bi bi-gear', NULL, NULL, 1, 9999, '2024-12-12 10:18:34', '2025-07-30 17:10:04', '[\"admin\",\"qshe\"]'),
(11, 'User Management', NULL, 'user.index', 10, 1, 1, '2024-12-12 10:18:54', '2025-09-01 06:31:42', '[\"admin\"]'),
(12, 'Menu', NULL, 'menu.index', 10, 1, 2, '2024-12-12 10:19:08', '2025-07-18 03:12:03', '[\"admin\"]'),
(13, 'Role', NULL, 'role.index', 10, 1, 3, '2024-12-12 10:19:22', '2025-07-18 03:11:53', '[\"admin\"]'),
(31, 'Master Data', 'bi bi-database-gear', NULL, NULL, 1, 3, '2025-07-18 07:56:25', '2025-07-18 07:58:19', '[\"admin\",\"qshe\"]'),
(32, 'Divisi', NULL, 'divisi.index', 31, 1, 1, '2025-07-18 07:57:01', '2025-07-18 07:57:13', '[\"admin\",\"qshe\"]'),
(39, 'SLA Management', NULL, 'dashboard.index', 31, 0, 4, '2025-09-01 06:32:07', '2025-09-01 16:35:18', '[\"admin\"]'),
(40, 'Departement', NULL, 'department.index', 31, 1, 2, '2025-09-01 06:32:55', '2025-09-01 07:13:36', '[\"admin\"]'),
(41, 'Problem Category', NULL, 'problem-category.index', 31, 1, 3, '2025-09-01 06:33:16', '2025-09-01 13:40:05', '[\"admin\"]'),
(42, 'Routing Rules', NULL, 'dashboard.index', 10, 0, 5, '2025-09-01 06:33:31', '2025-09-01 16:38:49', '[\"admin\"]'),
(43, 'Escalation Rules', NULL, 'dashboard.index', 10, 0, 6, '2025-09-01 06:33:45', '2025-09-01 16:38:47', '[\"admin\"]'),
(44, 'Request', 'bi bi-ticket-perforated', 'ticket.index', NULL, 1, 2, '2025-09-01 06:35:09', '2025-09-02 09:06:12', '[\"admin\",\"pelapor\"]'),
(46, 'Prioritas', NULL, 'prioritas.index', 31, 1, 7, '2025-09-01 14:12:53', '2025-09-01 14:12:53', '[\"admin\"]'),
(47, 'Status', NULL, 'status.index', 31, 1, 6, '2025-09-01 14:22:33', '2025-09-01 14:22:33', '[\"admin\"]'),
(48, 'Impact', NULL, 'impact.index', 31, 1, 8, '2025-09-01 14:22:52', '2025-09-01 14:22:52', '[\"admin\"]'),
(49, 'Urgency', NULL, 'urgency.index', 31, 1, 9, '2025-09-01 14:23:15', '2025-09-01 14:23:15', '[\"admin\"]'),
(50, 'Manajemen Kategori', NULL, 'department-problem-assign.index', 31, 1, 9, '2025-09-02 03:09:18', '2025-09-02 03:09:18', '[\"admin\"]');

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '0001_01_01_000001_create_cache_table', 1),
(2, '0001_01_01_000002_create_jobs_table', 1),
(3, '2025_05_22_214033_create_activity_log_table', 1),
(4, '2025_07_15_030314_create_divisions_table', 1),
(5, '2025_07_18_095043_create_users_table', 1),
(6, '2025_07_18_095117_create_employee_table', 1),
(7, '2025_07_28_112236_create_notifications_table', 1),
(8, '2025_09_01_132102_create_table_departments', 1),
(9, '2025_09_28_000004_create_problem_categories_table', 1),
(10, '2025_09_29_205853_create_master', 2),
(11, '2025_09_29_220726_create_request_histories', 3),
(12, '2025_09_29_223626_add_assignment_to_requests_table', 4),
(13, '2025_09_29_225803_add_username_to_users_table', 5),
(14, '2025_09_29_231207_add_assignby_to_requests_table', 6),
(15, '2025_09_29_393842_add_email_to_departments_table', 7),
(16, '2025_09_30_095333_create_category_department_table', 8),
(17, '2025_09_30_153012_create_ticket_categories_table', 9);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `message` text NOT NULL,
  `url` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `priorities`
--

CREATE TABLE `priorities` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(50) NOT NULL,
  `code` varchar(20) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `priorities`
--

INSERT INTO `priorities` (`id`, `name`, `code`, `created_at`, `updated_at`) VALUES
(1, 'Low', 'LOW', NULL, NULL),
(2, 'Medium', 'MED', NULL, NULL),
(3, 'High', 'HIGH', NULL, NULL),
(5, 'Critical', 'CRIT', '2025-09-01 14:24:31', '2025-09-01 14:24:31');

-- --------------------------------------------------------

--
-- Table structure for table `problem_categories`
--

CREATE TABLE `problem_categories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `parent_id` bigint(20) UNSIGNED DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `problem_categories`
--

INSERT INTO `problem_categories` (`id`, `parent_id`, `name`, `code`, `description`, `created_at`, `updated_at`) VALUES
(23, NULL, 'Hardware Issue', 'HRDW', 'Tes', '2025-09-01 14:44:16', '2025-09-01 14:44:16'),
(24, NULL, 'Software Bugs', 'SFT', 'ss', '2025-09-02 03:26:33', '2025-09-02 03:26:33');

-- --------------------------------------------------------

--
-- Table structure for table `requests`
--

CREATE TABLE `requests` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `ticket_no` varchar(30) NOT NULL,
  `requester_id` bigint(20) UNSIGNED NOT NULL,
  `department_id` bigint(20) UNSIGNED DEFAULT NULL,
  `category_id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp(),
  `resolved_at` timestamp NULL DEFAULT NULL,
  `closed_at` timestamp NULL DEFAULT NULL,
  `priority_id` bigint(20) UNSIGNED DEFAULT NULL,
  `impact_id` bigint(20) UNSIGNED DEFAULT NULL,
  `urgency_id` bigint(20) UNSIGNED DEFAULT NULL,
  `status_id` bigint(20) UNSIGNED NOT NULL,
  `assigned_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `assigned_department_id` bigint(20) UNSIGNED DEFAULT NULL,
  `assignby` varchar(255) DEFAULT NULL,
  `ticket_category_id` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `requests`
--

INSERT INTO `requests` (`id`, `ticket_no`, `requester_id`, `department_id`, `category_id`, `title`, `description`, `created_at`, `updated_at`, `resolved_at`, `closed_at`, `priority_id`, `impact_id`, `urgency_id`, `status_id`, `assigned_user_id`, `assigned_department_id`, `assignby`, `ticket_category_id`) VALUES
(3, 'TCK-HRDW-090125-0002', 1, NULL, 23, 'Testing 2', 'Tes', '2025-09-01 14:54:20', '2025-09-01 14:54:20', NULL, NULL, 2, 2, 2, 1, NULL, NULL, NULL, NULL),
(4, 'TCK-HRDW-090125-0003', 1, NULL, 23, 'TESTING @', 'TES', '2025-09-01 14:55:44', '2025-09-01 14:55:44', NULL, NULL, 2, 2, 2, 1, NULL, NULL, NULL, NULL),
(6, 'TCK-HRDW-090125-0004', 1, NULL, 23, 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Donec vel rutrum risus.', 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Donec vel rutrum risus. Vivamus tellus purus, molestie et porttitor sed, eleifend eget lacus. Nulla id orci risus. Ut sodales consectetur rhoncus. Mauris sit amet sollicitudin augue. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia curae; Ut commodo, justo ultricies egestas fringilla, arcu nibh elementum neque, nec rutrum mi urna non velit. In condimentum vitae sem sodales laoreet.', '2025-09-01 15:17:04', '2025-09-01 16:16:45', NULL, NULL, 2, 4, 2, 2, 1, 101, '1', NULL),
(13, 'TCK-SFT-090225-0003', 1, 101, 24, 'TES Baruu', 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Donec vestibulum in lorem id consequat. Cras nec nisi vitae massa consequat imperdiet eu ut libero. Fusce malesuada nisl suscipit, dapibus lacus sit amet, consequat purus. Cras commodo porta dictum. Pellentesque commodo, nisi sed placerat ultrices, ligula nulla pulvinar ligula, at egestas mauris sem elementum tellus. Integer tristique rutrum sapien a malesuada. Etiam eget velit a risus rhoncus hendrerit vel ut arcu. Donec interdum nulla magna, eu imperdiet velit dapibus id. Pellentesque non aliquam magna. Morbi mi tortor, ullamcorper sit amet tincidunt quis, laoreet sed lectus. Vivamus et euismod ex. Donec sit amet metus metus.', '2025-09-02 03:45:55', '2025-09-02 03:56:32', NULL, NULL, 1, 1, 1, 2, NULL, NULL, NULL, NULL),
(14, 'TCK-SFT-090225-0004', 1, 101, 24, 'ssss', 'sssssssssssss', '2025-09-02 03:57:04', '2025-09-02 04:06:53', NULL, NULL, 1, 1, 2, 2, NULL, NULL, NULL, NULL),
(15, 'TCK-HRDW-090225-0001', 30, NULL, 23, 'Koneksi Jaringan Error', 'Wifi jaringan tidak konek', '2025-09-02 08:00:21', '2025-09-02 08:00:21', NULL, NULL, 3, 4, 4, 1, NULL, NULL, NULL, NULL),
(16, 'TCK-HRDW-090225-0002', 1, NULL, 23, 'Wifi Laptop Tidak Bisa Konek', 'Wifi Laptop Tidak Bisa Konek', '2025-09-02 08:42:56', '2025-09-02 08:42:56', NULL, NULL, 3, 2, 4, 1, NULL, NULL, NULL, 1),
(17, 'TCK-HRDW-121825-0001', 31, NULL, 23, 'Testing', 'Testing', '2025-12-18 08:23:58', '2025-12-18 08:49:00', NULL, NULL, 2, 2, 2, 1, 31, 101, NULL, 2),
(18, 'TCK-HRDW-121825-0002', 31, NULL, 23, 'Testing Baru', 'Testing', '2025-12-18 08:26:32', '2025-12-18 08:45:50', NULL, NULL, 1, 2, 1, 2, 1, 101, NULL, 2);

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `created_at`, `updated_at`) VALUES
(1, 'superadmin', '2024-12-12 10:13:04', '2024-12-12 10:13:04'),
(3, 'admin', '2024-12-13 02:23:37', '2024-12-13 02:23:37'),
(9, 'pic', '2025-07-18 04:00:58', '2025-07-18 04:00:58'),
(10, 'pelapor', '2025-07-21 08:41:33', '2025-07-21 08:41:33');

-- --------------------------------------------------------

--
-- Table structure for table `routing_rules`
--

CREATE TABLE `routing_rules` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `category_id` bigint(20) UNSIGNED NOT NULL,
  `assignee_department_id` bigint(20) UNSIGNED NOT NULL,
  `default_priority` varchar(20) DEFAULT NULL,
  `sla_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sessions`
--

INSERT INTO `sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`) VALUES
('ZigV1TGGrxRImLhSpTggYTfgiOf4ku6t4066wORy', 31, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 'YTo1OntzOjY6Il90b2tlbiI7czo0MDoib0trMThLdVZ4aVplclFXR1BlcGRQdE56akE4amZRSkk3NkZoMnE3bSI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6MzU6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMS9ub3RpZmljYXRpb25zIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319czozOiJ1cmwiO2E6MTp7czo4OiJpbnRlbmRlZCI7czozMToiaHR0cDovLzEyNy4wLjAuMTo4MDAwL2Rhc2hib2FyZCI7fXM6NTA6ImxvZ2luX3dlYl81OWJhMzZhZGRjMmIyZjk0MDE1ODBmMDE0YzdmNThlYTRlMzA5ODlkIjtpOjMxO30=', 1766055220);

-- --------------------------------------------------------

--
-- Table structure for table `sla`
--

CREATE TABLE `sla` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `response_time_minutes` int(11) NOT NULL,
  `resolve_time_minutes` int(11) NOT NULL,
  `business_hours` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `statuses`
--

CREATE TABLE `statuses` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(50) NOT NULL,
  `code` varchar(20) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `statuses`
--

INSERT INTO `statuses` (`id`, `name`, `code`, `created_at`, `updated_at`) VALUES
(1, 'Open', 'OPEN', NULL, NULL),
(2, 'In Progress', 'INPROG', NULL, NULL),
(3, 'Resolved', 'RESOLVED', NULL, NULL),
(4, 'Closed', 'CLOSED', NULL, NULL),
(5, 'Cancelled', 'CANCEL', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `status_logs`
--

CREATE TABLE `status_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `request_id` bigint(20) UNSIGNED NOT NULL,
  `from_status` varchar(20) DEFAULT NULL,
  `to_status` varchar(20) NOT NULL,
  `changed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `remark` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ticket_categories`
--

CREATE TABLE `ticket_categories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `code` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ticket_categories`
--

INSERT INTO `ticket_categories` (`id`, `name`, `code`, `description`, `created_at`, `updated_at`) VALUES
(1, 'Incident', 'INC', NULL, NULL, NULL),
(2, 'Service Request', 'SR', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `ticket_histories`
--

CREATE TABLE `ticket_histories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `ticket_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `status_id` bigint(20) UNSIGNED DEFAULT NULL,
  `action` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ticket_histories`
--

INSERT INTO `ticket_histories` (`id`, `ticket_id`, `user_id`, `status_id`, `action`, `created_at`, `updated_at`) VALUES
(1, 6, 1, 1, 'Ticket dibuat', '2025-09-01 15:17:04', '2025-09-01 15:17:04'),
(19, 13, 1, 1, 'Ticket dibuat', '2025-09-02 03:45:55', '2025-09-02 03:45:55'),
(20, 13, 1, 2, 'Status diupdate', '2025-09-02 03:53:13', '2025-09-02 03:53:13'),
(21, 13, 1, 1, 'Status diupdate', '2025-09-02 03:54:27', '2025-09-02 03:54:27'),
(22, 13, 1, 2, 'Status diupdate', '2025-09-02 03:56:32', '2025-09-02 03:56:32'),
(23, 14, 1, 1, 'Ticket dibuat', '2025-09-02 03:57:04', '2025-09-02 03:57:04'),
(24, 14, 1, 2, 'Status diupdate', '2025-09-02 04:00:43', '2025-09-02 04:00:43'),
(25, 14, 1, 1, 'Status diupdate', '2025-09-02 04:03:32', '2025-09-02 04:03:32'),
(26, 14, 1, 2, 'Status diupdate', '2025-09-02 04:06:53', '2025-09-02 04:06:53'),
(27, 15, 30, 1, 'Ticket dibuat', '2025-09-02 08:00:21', '2025-09-02 08:00:21'),
(28, 16, 1, 1, 'Ticket dibuat', '2025-09-02 08:42:56', '2025-09-02 08:42:56'),
(29, 17, 31, 1, 'Ticket dibuat', '2025-12-18 08:23:58', '2025-12-18 08:23:58'),
(30, 18, 31, 1, 'Ticket dibuat', '2025-12-18 08:26:32', '2025-12-18 08:26:32'),
(31, 18, 31, 2, 'Status diupdate', '2025-12-18 08:45:42', '2025-12-18 08:45:42'),
(32, 18, 31, NULL, 'Tiket ditugaskan kepada Muhamad Sobirin di departemen Keamanan & Insfratukture', '2025-12-18 08:45:50', '2025-12-18 08:45:50'),
(33, 17, 31, NULL, 'Tiket ditugaskan kepada Muhamad Sobirin di departemen Keamanan & Insfratukture', '2025-12-18 08:49:00', '2025-12-18 08:49:00');

-- --------------------------------------------------------

--
-- Table structure for table `urgencies`
--

CREATE TABLE `urgencies` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(50) NOT NULL,
  `code` varchar(20) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `urgencies`
--

INSERT INTO `urgencies` (`id`, `name`, `code`, `created_at`, `updated_at`) VALUES
(1, 'Low', 'LOW', NULL, NULL),
(2, 'Medium', 'MED', NULL, NULL),
(4, 'High', 'HIGH', '2025-09-01 14:23:52', '2025-09-01 14:23:52');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_type` varchar(255) NOT NULL DEFAULT 'karyawan',
  `nik` varchar(255) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(255) NOT NULL DEFAULT 'pelapor',
  `division_id` bigint(20) UNSIGNED DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `username` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `user_type`, `nik`, `name`, `email`, `phone`, `password`, `role`, `division_id`, `remember_token`, `created_at`, `updated_at`, `username`) VALUES
(1, 'karyawan', '3211150906010011', 'Muhamad Sobirin', 'pamanbiriiin@gmail.com', '0', '$2y$12$EFVZIHys4MMXzGYH6SyBe.03wWc3KTu2XeyoRLqf8.N1CzerMAphq', 'admin', NULL, NULL, NULL, '2025-09-01 15:58:43', 'Paman Biriiin'),
(26, 'karyawan', NULL, 'Admin System', 'sobirinjamo33@gmail.com', NULL, '$2y$12$gIACwGQh9vlIqgjTjt/kaucST7CzO.5HlQvFN6auFQC1zZBjiMao6', 'admin', NULL, NULL, NULL, '2025-09-01 15:58:53', 'Admin Sistem'),
(27, 'karyawan', NULL, 'Request User', 'requester@company.com', NULL, '$2y$12$EFVZIHys4MMXzGYH6SyBe.03wWc3KTu2XeyoRLqf8.N1CzerMAphq', 'pelapor', NULL, NULL, NULL, NULL, ''),
(28, 'karyawan', NULL, 'Approver Manager', 'approver@company.com', NULL, '$2y$12$F0lT3OrtAMKpJtjoSLETLuN1Xh.v0H4d7P96Z2Qd8OwZwnp1.BhZ6', 'approver', NULL, NULL, NULL, NULL, ''),
(29, 'karyawan', NULL, 'IT Staff', 'staff@company.com', NULL, '$2y$12$2GoIA7kUwIeDV9Cc2x083ex42xDWwHXm/bugvRt603U2wewNMLlY6', 'staff', NULL, NULL, NULL, NULL, ''),
(30, 'karyawan', NULL, 'Hery Sapto Dwi Nurcahyo', 'hery.nurcahyo@lrtjakarta.co.id', '0', '$2y$12$kF14JTvPR61hlzsTQvITF.yIJIPHgf3LPP1aRQPHiEE.XSxsd3ecC', 'pelapor', NULL, NULL, '2025-09-02 07:58:58', '2025-09-02 07:58:58', 'hery.nurcahyo'),
(31, 'karyawan', NULL, 'Muhamad Sobirin', 'muhamad.sobirin@lrtjakarta.co.id', '0', '$2y$12$VSO1nSgHgf2gI8BdQksCh.JZf3DTk4HIs3oLT6.8AwUANTd4Am0zi', 'admin', NULL, NULL, '2025-12-17 03:50:08', '2025-12-17 03:50:08', 'muhamad.sobirin');

-- --------------------------------------------------------

--
-- Table structure for table `watchers`
--

CREATE TABLE `watchers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `request_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `subject` (`subject_type`,`subject_id`),
  ADD KEY `causer` (`causer_type`,`causer_id`),
  ADD KEY `activity_log_log_name_index` (`log_name`);

--
-- Indexes for table `approvals`
--
ALTER TABLE `approvals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `approvals_request_id_foreign` (`request_id`),
  ADD KEY `approvals_approver_id_foreign` (`approver_id`);

--
-- Indexes for table `assignments`
--
ALTER TABLE `assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `assignments_request_id_foreign` (`request_id`),
  ADD KEY `assignments_assignee_user_id_foreign` (`assignee_user_id`),
  ADD KEY `assignments_assignee_department_id_foreign` (`assignee_department_id`);

--
-- Indexes for table `attachments`
--
ALTER TABLE `attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `attachments_request_id_foreign` (`request_id`),
  ADD KEY `attachments_uploaded_by_foreign` (`uploaded_by`);

--
-- Indexes for table `cache`
--
ALTER TABLE `cache`
  ADD PRIMARY KEY (`key`);

--
-- Indexes for table `cache_locks`
--
ALTER TABLE `cache_locks`
  ADD PRIMARY KEY (`key`);

--
-- Indexes for table `category_department`
--
ALTER TABLE `category_department`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_department_category_id_foreign` (`category_id`),
  ADD KEY `category_department_department_id_foreign` (`department_id`);

--
-- Indexes for table `comments`
--
ALTER TABLE `comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `comments_request_id_foreign` (`request_id`),
  ADD KEY `comments_user_id_foreign` (`user_id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `departments_code_unique` (`code`),
  ADD KEY `departments_division_id_foreign` (`division_id`);

--
-- Indexes for table `divisions`
--
ALTER TABLE `divisions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `employee`
--
ALTER TABLE `employee`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `employee_nik_unique` (`nik`),
  ADD KEY `employee_user_id_foreign` (`user_id`),
  ADD KEY `employee_division_id_foreign` (`division_id`);

--
-- Indexes for table `escalation_rules`
--
ALTER TABLE `escalation_rules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `escalation_rules_sla_id_foreign` (`sla_id`),
  ADD KEY `escalation_rules_escalate_to_user_id_foreign` (`escalate_to_user_id`),
  ADD KEY `escalation_rules_escalate_to_department_id_foreign` (`escalate_to_department_id`);

--
-- Indexes for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

--
-- Indexes for table `impacts`
--
ALTER TABLE `impacts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `impacts_code_unique` (`code`);

--
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jobs_queue_index` (`queue`);

--
-- Indexes for table `job_batches`
--
ALTER TABLE `job_batches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `menus`
--
ALTER TABLE `menus`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `notifications_user_id_foreign` (`user_id`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`email`);

--
-- Indexes for table `priorities`
--
ALTER TABLE `priorities`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `priorities_code_unique` (`code`);

--
-- Indexes for table `problem_categories`
--
ALTER TABLE `problem_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `problem_categories_code_unique` (`code`),
  ADD KEY `problem_categories_parent_id_foreign` (`parent_id`);

--
-- Indexes for table `requests`
--
ALTER TABLE `requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `requests_ticket_no_unique` (`ticket_no`),
  ADD KEY `requests_requester_id_foreign` (`requester_id`),
  ADD KEY `requests_department_id_foreign` (`department_id`),
  ADD KEY `requests_category_id_foreign` (`category_id`),
  ADD KEY `requests_priority_id_foreign` (`priority_id`),
  ADD KEY `requests_impact_id_foreign` (`impact_id`),
  ADD KEY `requests_urgency_id_foreign` (`urgency_id`),
  ADD KEY `requests_status_id_foreign` (`status_id`),
  ADD KEY `requests_assigned_user_id_foreign` (`assigned_user_id`),
  ADD KEY `requests_assigned_department_id_foreign` (`assigned_department_id`),
  ADD KEY `requests_ticket_category_id_foreign` (`ticket_category_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `routing_rules`
--
ALTER TABLE `routing_rules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `routing_rules_category_id_foreign` (`category_id`),
  ADD KEY `routing_rules_assignee_department_id_foreign` (`assignee_department_id`),
  ADD KEY `routing_rules_sla_id_foreign` (`sla_id`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sessions_user_id_index` (`user_id`),
  ADD KEY `sessions_last_activity_index` (`last_activity`);

--
-- Indexes for table `sla`
--
ALTER TABLE `sla`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `statuses`
--
ALTER TABLE `statuses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `statuses_code_unique` (`code`);

--
-- Indexes for table `status_logs`
--
ALTER TABLE `status_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `status_logs_request_id_foreign` (`request_id`),
  ADD KEY `status_logs_changed_by_foreign` (`changed_by`);

--
-- Indexes for table `ticket_categories`
--
ALTER TABLE `ticket_categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `ticket_histories`
--
ALTER TABLE `ticket_histories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ticket_histories_ticket_id_foreign` (`ticket_id`),
  ADD KEY `ticket_histories_user_id_foreign` (`user_id`),
  ADD KEY `ticket_histories_status_id_foreign` (`status_id`);

--
-- Indexes for table `urgencies`
--
ALTER TABLE `urgencies`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `urgencies_code_unique` (`code`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`),
  ADD KEY `users_division_id_foreign` (`division_id`);

--
-- Indexes for table `watchers`
--
ALTER TABLE `watchers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `watchers_request_id_foreign` (`request_id`),
  ADD KEY `watchers_user_id_foreign` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `approvals`
--
ALTER TABLE `approvals`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `assignments`
--
ALTER TABLE `assignments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attachments`
--
ALTER TABLE `attachments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `category_department`
--
ALTER TABLE `category_department`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `comments`
--
ALTER TABLE `comments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=104;

--
-- AUTO_INCREMENT for table `divisions`
--
ALTER TABLE `divisions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=103;

--
-- AUTO_INCREMENT for table `employee`
--
ALTER TABLE `employee`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `escalation_rules`
--
ALTER TABLE `escalation_rules`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `impacts`
--
ALTER TABLE `impacts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `menus`
--
ALTER TABLE `menus`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `priorities`
--
ALTER TABLE `priorities`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `problem_categories`
--
ALTER TABLE `problem_categories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `requests`
--
ALTER TABLE `requests`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `routing_rules`
--
ALTER TABLE `routing_rules`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sla`
--
ALTER TABLE `sla`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `statuses`
--
ALTER TABLE `statuses`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `status_logs`
--
ALTER TABLE `status_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ticket_categories`
--
ALTER TABLE `ticket_categories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `ticket_histories`
--
ALTER TABLE `ticket_histories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `urgencies`
--
ALTER TABLE `urgencies`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `watchers`
--
ALTER TABLE `watchers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `approvals`
--
ALTER TABLE `approvals`
  ADD CONSTRAINT `approvals_approver_id_foreign` FOREIGN KEY (`approver_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `approvals_request_id_foreign` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `assignments`
--
ALTER TABLE `assignments`
  ADD CONSTRAINT `assignments_assignee_department_id_foreign` FOREIGN KEY (`assignee_department_id`) REFERENCES `departments` (`id`),
  ADD CONSTRAINT `assignments_assignee_user_id_foreign` FOREIGN KEY (`assignee_user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `assignments_request_id_foreign` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `attachments`
--
ALTER TABLE `attachments`
  ADD CONSTRAINT `attachments_request_id_foreign` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attachments_uploaded_by_foreign` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `category_department`
--
ALTER TABLE `category_department`
  ADD CONSTRAINT `category_department_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `problem_categories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `category_department_department_id_foreign` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `comments`
--
ALTER TABLE `comments`
  ADD CONSTRAINT `comments_request_id_foreign` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `comments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `departments`
--
ALTER TABLE `departments`
  ADD CONSTRAINT `departments_division_id_foreign` FOREIGN KEY (`division_id`) REFERENCES `divisions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `employee`
--
ALTER TABLE `employee`
  ADD CONSTRAINT `employee_division_id_foreign` FOREIGN KEY (`division_id`) REFERENCES `divisions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `employee_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `escalation_rules`
--
ALTER TABLE `escalation_rules`
  ADD CONSTRAINT `escalation_rules_escalate_to_department_id_foreign` FOREIGN KEY (`escalate_to_department_id`) REFERENCES `departments` (`id`),
  ADD CONSTRAINT `escalation_rules_escalate_to_user_id_foreign` FOREIGN KEY (`escalate_to_user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `escalation_rules_sla_id_foreign` FOREIGN KEY (`sla_id`) REFERENCES `sla` (`id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `problem_categories`
--
ALTER TABLE `problem_categories`
  ADD CONSTRAINT `problem_categories_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `problem_categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `requests`
--
ALTER TABLE `requests`
  ADD CONSTRAINT `requests_assigned_department_id_foreign` FOREIGN KEY (`assigned_department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `requests_assigned_user_id_foreign` FOREIGN KEY (`assigned_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `requests_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `problem_categories` (`id`),
  ADD CONSTRAINT `requests_department_id_foreign` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`),
  ADD CONSTRAINT `requests_impact_id_foreign` FOREIGN KEY (`impact_id`) REFERENCES `impacts` (`id`),
  ADD CONSTRAINT `requests_priority_id_foreign` FOREIGN KEY (`priority_id`) REFERENCES `priorities` (`id`),
  ADD CONSTRAINT `requests_requester_id_foreign` FOREIGN KEY (`requester_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `requests_status_id_foreign` FOREIGN KEY (`status_id`) REFERENCES `statuses` (`id`),
  ADD CONSTRAINT `requests_ticket_category_id_foreign` FOREIGN KEY (`ticket_category_id`) REFERENCES `ticket_categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `requests_urgency_id_foreign` FOREIGN KEY (`urgency_id`) REFERENCES `urgencies` (`id`);

--
-- Constraints for table `routing_rules`
--
ALTER TABLE `routing_rules`
  ADD CONSTRAINT `routing_rules_assignee_department_id_foreign` FOREIGN KEY (`assignee_department_id`) REFERENCES `departments` (`id`),
  ADD CONSTRAINT `routing_rules_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `problem_categories` (`id`),
  ADD CONSTRAINT `routing_rules_sla_id_foreign` FOREIGN KEY (`sla_id`) REFERENCES `sla` (`id`);

--
-- Constraints for table `status_logs`
--
ALTER TABLE `status_logs`
  ADD CONSTRAINT `status_logs_changed_by_foreign` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `status_logs_request_id_foreign` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ticket_histories`
--
ALTER TABLE `ticket_histories`
  ADD CONSTRAINT `ticket_histories_status_id_foreign` FOREIGN KEY (`status_id`) REFERENCES `statuses` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `ticket_histories_ticket_id_foreign` FOREIGN KEY (`ticket_id`) REFERENCES `requests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ticket_histories_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_division_id_foreign` FOREIGN KEY (`division_id`) REFERENCES `divisions` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `watchers`
--
ALTER TABLE `watchers`
  ADD CONSTRAINT `watchers_request_id_foreign` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `watchers_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
