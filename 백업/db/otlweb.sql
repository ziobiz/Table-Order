-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- 호스트: localhost
-- 생성 시간: 26-02-19 21:20
-- 서버 버전: 10.1.13-MariaDB
-- PHP 버전: 7.4.5p1

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- 데이터베이스: `otlweb`
--

-- --------------------------------------------------------

--
-- 테이블 구조 `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- 테이블의 덤프 데이터 `admins`
--

INSERT INTO `admins` (`id`, `username`, `password`, `created_at`) VALUES
(1, 'hqadmin', 'hq1234', '2026-02-11 22:27:31');

-- --------------------------------------------------------

--
-- 테이블 구조 `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `category_name` varchar(255) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- 테이블의 덤프 데이터 `categories`
--

INSERT INTO `categories` (`id`, `category_name`, `sort_order`) VALUES
(1, '메인 메뉴', 1);

-- --------------------------------------------------------

--
-- 테이블 구조 `category_translations`
--

CREATE TABLE `category_translations` (
  `id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `lang_code` varchar(5) NOT NULL,
  `category_name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- 테이블의 덤프 데이터 `category_translations`
--

INSERT INTO `category_translations` (`id`, `category_id`, `lang_code`, `category_name`) VALUES
(1, 1, 'ko', '메인 메뉴'),
(2, 1, 'en', 'Main Menu'),
(3, 1, 'ja', 'メインメニュー'),
(4, 1, 'th', 'เมนูหลัก'),
(5, 1, 'vi', 'Món chính'),
(6, 1, 'id', 'Menu Utama');

-- --------------------------------------------------------

--
-- 테이블 구조 `menus`
--

CREATE TABLE `menus` (
  `id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `is_available` tinyint(4) NOT NULL DEFAULT '1',
  `is_dinein` tinyint(4) NOT NULL DEFAULT '1',
  `is_pickup` tinyint(4) NOT NULL DEFAULT '1',
  `is_delivery` tinyint(4) NOT NULL DEFAULT '1',
  `price` int(11) NOT NULL DEFAULT '0',
  `price_pickup` int(11) NOT NULL DEFAULT '0',
  `price_delivery` int(11) NOT NULL DEFAULT '0',
  `daily_limit` int(11) NOT NULL DEFAULT '0',
  `current_stock` int(11) NOT NULL DEFAULT '0',
  `image_url` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- 테이블의 덤프 데이터 `menus`
--

INSERT INTO `menus` (`id`, `category_id`, `is_available`, `is_dinein`, `is_pickup`, `is_delivery`, `price`, `price_pickup`, `price_delivery`, `daily_limit`, `current_stock`, `image_url`) VALUES
(1, 1, 1, 1, 1, 1, 10000, 9000, 12000, 0, 43, NULL);

-- --------------------------------------------------------

--
-- 테이블 구조 `menu_option_groups`
--

CREATE TABLE `menu_option_groups` (
  `id` int(11) NOT NULL,
  `menu_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- 테이블의 덤프 데이터 `menu_option_groups`
--

INSERT INTO `menu_option_groups` (`id`, `menu_id`, `group_id`) VALUES
(1, 1, 1),
(2, 1, 2);

-- --------------------------------------------------------

--
-- 테이블 구조 `menu_translations`
--

CREATE TABLE `menu_translations` (
  `id` int(11) NOT NULL,
  `menu_id` int(11) NOT NULL,
  `lang_code` varchar(5) NOT NULL,
  `menu_name` varchar(255) NOT NULL,
  `description` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- 테이블의 덤프 데이터 `menu_translations`
--

INSERT INTO `menu_translations` (`id`, `menu_id`, `lang_code`, `menu_name`, `description`) VALUES
(1, 1, 'ko', '불고기 덮밥', '달콤한 소스로 볶은 불고기 덮밥입니다.'),
(2, 1, 'en', 'Bulgogi Rice Bowl', 'Sweet marinated beef served over rice.'),
(3, 1, 'ja', 'プルコギ丼', '甘いタレで炒めたプルコギ丼です。'),
(4, 1, 'th', 'ข้าวหน้าบูลโกกิ', 'เนื้อผัดซอสหวานราดข้าว'),
(5, 1, 'vi', 'Cơm bò Bulgogi', 'Cơm với thịt bò sốt ngọt kiểu Hàn Quốc.'),
(6, 1, 'id', 'Nasi Bulgogi', 'Nasi dengan daging sapi saus manis ala Korea.');

-- --------------------------------------------------------

--
-- 테이블 구조 `option_groups`
--

CREATE TABLE `option_groups` (
  `id` int(11) NOT NULL,
  `group_name_ko` varchar(255) NOT NULL,
  `is_required` tinyint(1) NOT NULL DEFAULT '0',
  `max_select` int(11) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- 테이블의 덤프 데이터 `option_groups`
--

INSERT INTO `option_groups` (`id`, `group_name_ko`, `is_required`, `max_select`) VALUES
(1, '추가 토핑', 0, 3),
(2, '사이즈 선택', 1, 1);

-- --------------------------------------------------------

--
-- 테이블 구조 `option_items`
--

CREATE TABLE `option_items` (
  `id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `item_name_ko` varchar(255) NOT NULL,
  `price_dinein` int(11) NOT NULL DEFAULT '0',
  `price_pickup` int(11) NOT NULL DEFAULT '0',
  `price_delivery` int(11) NOT NULL DEFAULT '0',
  `is_available` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- 테이블의 덤프 데이터 `option_items`
--

INSERT INTO `option_items` (`id`, `group_id`, `item_name_ko`, `price_dinein`, `price_pickup`, `price_delivery`, `is_available`) VALUES
(1, 1, '치즈 추가', 1000, 1000, 1000, 1),
(2, 1, '계란 후라이', 1000, 1000, 1000, 1),
(3, 1, '곱빼기 고기', 3000, 3000, 3000, 1),
(4, 2, '보통 사이즈', 0, 0, 0, 1),
(5, 2, '대 사이즈', 2000, 2000, 2000, 1);

-- --------------------------------------------------------

--
-- 테이블 구조 `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `store_id` int(11) NOT NULL,
  `order_type` enum('dinein','pickup','delivery') NOT NULL DEFAULT 'dinein',
  `total_amount` int(11) NOT NULL DEFAULT '0',
  `address` varchar(255) DEFAULT NULL,
  `tel` varchar(50) DEFAULT NULL,
  `status` enum('pending','paid','canceled','completed') NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `used_point` int(11) NOT NULL DEFAULT '0',
  `paid_amount` int(11) NOT NULL DEFAULT '0',
  `guest_name` varchar(100) DEFAULT NULL,
  `guest_tel` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- 테이블의 덤프 데이터 `orders`
--

INSERT INTO `orders` (`id`, `user_id`, `store_id`, `order_type`, `total_amount`, `address`, `tel`, `status`, `created_at`, `updated_at`, `used_point`, `paid_amount`, `guest_name`, `guest_tel`) VALUES
(1, NULL, 1, 'dinein', 10000, '', '', '', '2026-02-11 21:51:56', '2026-02-12 01:23:51', 0, 0, NULL, NULL),
(2, NULL, 1, 'dinein', 35000, 'Cyprus Lamprou, 5 Flat, Office 101, 1082, Nicosia, Cyprus', '01020890909', '', '2026-02-11 22:00:57', '2026-02-12 01:26:07', 0, 0, NULL, NULL),
(3, NULL, 1, 'dinein', 10000, 'Cyprus Lamprou, 5 Flat, Office 101, 1082, Nicosia, Cyprus', '01020890909', '', '2026-02-11 22:05:53', '2026-02-12 01:03:13', 0, 0, NULL, NULL),
(4, NULL, 1, 'dinein', 17000, '', '', '', '2026-02-11 22:21:07', '2026-02-12 01:03:11', 0, 17000, NULL, NULL),
(5, NULL, 1, 'dinein', 15000, 'Cyprus Lamprou, 5 Flat, Office 101, 1082, Nicosia, Cyprus', '01020890909', '', '2026-02-11 22:24:07', '2026-02-12 01:26:09', 3000, 12000, NULL, NULL),
(6, NULL, 1, 'dinein', 10000, 'Cyprus Lamprou, 5 Flat, Office 101, 1082, Nicosia, Cyprus', '01020890909', '', '2026-02-11 22:25:56', '2026-02-12 01:03:14', 5000, 5000, NULL, NULL),
(7, 1, 1, 'dinein', 10000, 'Cyprus Lamprou, 5 Flat, Office 101, 1082, Nicosia, Cyprus', '01020890909', '', '2026-02-11 23:04:10', '2026-02-12 01:03:16', 1, 9999, 'rick', '01020890909'),
(8, 1, 1, 'dinein', 20000, '', '', '', '2026-02-11 23:16:07', '2026-02-12 01:26:08', 0, 20000, '', ''),
(9, 1, 1, 'dinein', 48000, '', '', '', '2026-02-12 03:04:06', '2026-02-12 03:05:52', 0, 48000, '', ''),
(10, 1, 1, 'dinein', 14000, '', '', '', '2026-02-12 03:04:38', '2026-02-12 03:05:46', 0, 14000, '', ''),
(11, 1, 1, 'dinein', 15000, '', '', '', '2026-02-12 03:08:56', '2026-02-12 03:15:10', 0, 15000, '', ''),
(12, 1, 1, 'dinein', 34000, '', '', '', '2026-02-12 03:14:37', '2026-02-12 03:14:58', 0, 34000, '', ''),
(13, 1, 1, 'dinein', 25000, '', '', '', '2026-02-12 03:17:31', '2026-02-12 03:44:03', 0, 25000, '', ''),
(14, 1, 1, 'dinein', 10000, '', '', '', '2026-02-12 03:17:57', '2026-02-12 03:44:04', 0, 10000, '', ''),
(15, 1, 1, 'dinein', 12000, '', '', '', '2026-02-12 03:18:37', '2026-02-12 03:48:43', 0, 12000, '', ''),
(16, NULL, 1, 'dinein', 22000, '', '', '', '2026-02-12 03:37:21', '2026-02-12 03:43:20', 0, 22000, '', ''),
(17, NULL, 1, 'dinein', 10000, '', '', '', '2026-02-12 03:39:02', '2026-02-12 03:44:33', 0, 10000, '', ''),
(18, NULL, 1, 'dinein', 13000, '', '', '', '2026-02-12 03:42:53', '2026-02-12 04:01:59', 0, 13000, '', ''),
(19, NULL, 1, 'dinein', 12000, '', '', '', '2026-02-12 03:51:26', '2026-02-12 04:01:57', 0, 12000, '', ''),
(20, NULL, 1, 'dinein', 27000, '', '', '', '2026-02-12 03:51:54', '2026-02-12 03:53:26', 0, 27000, '', ''),
(21, NULL, 1, 'dinein', 10000, '', '', '', '2026-02-12 03:54:41', '2026-02-12 04:01:56', 0, 10000, '', ''),
(22, NULL, 1, 'dinein', 12000, '', '', '', '2026-02-12 03:54:55', '2026-02-12 03:57:06', 0, 12000, '', ''),
(23, NULL, 1, 'dinein', 10000, '', '', '', '2026-02-12 04:05:32', '2026-02-12 04:05:48', 0, 10000, '', ''),
(24, NULL, 1, 'dinein', 10000, '', '', '', '2026-02-12 04:05:41', '2026-02-12 04:06:15', 0, 10000, '', ''),
(25, NULL, 1, 'dinein', 11000, '', '', '', '2026-02-12 04:18:37', '2026-02-12 04:28:37', 0, 11000, '', ''),
(26, NULL, 1, 'dinein', 10000, '', '', '', '2026-02-12 04:43:26', '2026-02-12 04:45:28', 0, 10000, '', ''),
(27, NULL, 1, 'dinein', 14000, '', '', '', '2026-02-12 04:46:37', '2026-02-12 04:54:25', 0, 14000, '', ''),
(28, NULL, 1, 'dinein', 12000, '', '', '', '2026-02-12 04:46:47', '2026-02-12 04:47:14', 0, 12000, '', ''),
(29, NULL, 1, 'dinein', 10000, '', '', '', '2026-02-12 04:53:03', '2026-02-12 05:02:40', 0, 10000, '', ''),
(30, NULL, 1, 'dinein', 10000, '', '', '', '2026-02-12 05:02:13', '2026-02-12 05:02:41', 0, 10000, '', ''),
(31, NULL, 1, 'dinein', 10000, '', '', '', '2026-02-12 05:06:28', '2026-02-12 05:06:37', 0, 10000, '', '');

-- --------------------------------------------------------

--
-- 테이블 구조 `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `menu_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT '1',
  `price` int(11) NOT NULL DEFAULT '0',
  `options_text` text,
  `item_status` varchar(20) NOT NULL DEFAULT 'PENDING'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- 테이블의 덤프 데이터 `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `menu_id`, `quantity`, `price`, `options_text`, `item_status`) VALUES
(1, 1, 1, 1, 10000, '', 'SERVED'),
(2, 2, 1, 3, 45000, '', 'SERVED'),
(3, 2, 1, 1, 45000, '치즈 추가, 계란 후라이, 곱빼기 고기', 'SERVED'),
(4, 3, 1, 1, 10000, '', 'SERVED'),
(5, 4, 1, 1, 17000, '치즈 추가, 계란 후라이, 곱빼기 고기, 보통 사이즈, 대 사이즈', 'SERVED'),
(6, 5, 1, 1, 15000, '치즈 추가, 계란 후라이, 곱빼기 고기', 'SERVED'),
(7, 6, 1, 1, 10000, '', 'SERVED'),
(8, 7, 1, 1, 10000, '', 'SERVED'),
(9, 8, 1, 2, 20000, '', 'SERVED'),
(10, 9, 1, 2, 48000, '', 'SERVED'),
(11, 9, 1, 1, 48000, '치즈 추가, 계란 후라이, 보통 사이즈, 대 사이즈', 'SERVED'),
(12, 9, 1, 1, 48000, '계란 후라이, 곱빼기 고기', 'SERVED'),
(13, 10, 1, 1, 14000, '치즈 추가, 계란 후라이, 대 사이즈', 'SERVED'),
(14, 11, 1, 1, 15000, '치즈 추가, 계란 후라이, 곱빼기 고기, 보통 사이즈', 'SERVED'),
(15, 12, 1, 1, 34000, '치즈 추가', 'SERVED'),
(16, 12, 1, 1, 34000, '계란 후라이, 보통 사이즈', 'SERVED'),
(17, 12, 1, 1, 34000, '치즈 추가, 계란 후라이', 'SERVED'),
(18, 13, 1, 1, 25000, '치즈 추가, 계란 후라이', 'served'),
(19, 13, 1, 1, 25000, '치즈 추가, 대 사이즈', 'served'),
(20, 14, 1, 1, 10000, '', 'SERVED'),
(21, 15, 1, 1, 12000, '치즈 추가, 계란 후라이', 'SERVED'),
(22, 16, 1, 1, 22000, '치즈 추가', 'SERVED'),
(23, 16, 1, 1, 22000, '계란 후라이', 'SERVED'),
(24, 17, 1, 1, 10000, '', 'SERVED'),
(25, 18, 1, 1, 13000, '곱빼기 고기', 'SERVED'),
(26, 19, 1, 1, 12000, '치즈 추가, 계란 후라이', 'served'),
(27, 20, 1, 1, 27000, '치즈 추가, 곱빼기 고기, 대 사이즈', 'served'),
(28, 20, 1, 1, 27000, '계란 후라이', 'served'),
(29, 21, 1, 1, 10000, '', 'served'),
(30, 22, 1, 1, 12000, '치즈 추가, 계란 후라이', 'served'),
(31, 23, 1, 1, 10000, '', 'served'),
(32, 24, 1, 1, 10000, '', 'served'),
(33, 25, 1, 1, 11000, '치즈 추가', 'served'),
(34, 26, 1, 1, 10000, '', 'served'),
(35, 27, 1, 1, 14000, '계란 후라이, 곱빼기 고기', 'served'),
(36, 28, 1, 1, 12000, '치즈 추가, 계란 후라이', 'served'),
(37, 29, 1, 1, 10000, '', 'served'),
(38, 30, 1, 1, 10000, '', 'served'),
(39, 31, 1, 1, 10000, '', 'served');

-- --------------------------------------------------------

--
-- 테이블 구조 `order_payments`
--

CREATE TABLE `order_payments` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `payer_user_id` int(11) NOT NULL,
  `pay_method` enum('CARD','CASH','POINT') NOT NULL,
  `amount` int(11) NOT NULL DEFAULT '0',
  `used_point` int(11) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 테이블 구조 `order_sessions`
--

CREATE TABLE `order_sessions` (
  `id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `table_number` varchar(20) NOT NULL,
  `status` enum('OPEN','CLOSED') NOT NULL DEFAULT 'OPEN',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 테이블 구조 `point_logs`
--

CREATE TABLE `point_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `amount` int(11) NOT NULL,
  `type` enum('EARN','USE') NOT NULL,
  `payer` enum('HQ','STORE') NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 테이블 구조 `point_master_policies`
--

CREATE TABLE `point_master_policies` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `point_value` decimal(10,4) NOT NULL DEFAULT '1.0000',
  `min_use_amount` int(11) NOT NULL DEFAULT '0',
  `min_use_point` int(11) NOT NULL DEFAULT '0',
  `max_use_pct` int(11) NOT NULL DEFAULT '100',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- 테이블의 덤프 데이터 `point_master_policies`
--

INSERT INTO `point_master_policies` (`id`, `name`, `point_value`, `min_use_amount`, `min_use_point`, `max_use_pct`, `created_at`, `updated_at`) VALUES
(1, '기본 정책 v1', 1.0000, 0, 0, 100, '2026-02-11 22:19:01', '2026-02-11 22:19:01');

-- --------------------------------------------------------

--
-- 테이블 구조 `reviews`
--

CREATE TABLE `reviews` (
  `id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `menu_id` int(11) DEFAULT NULL,
  `order_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `guest_name` varchar(100) DEFAULT NULL,
  `rating` tinyint(4) NOT NULL,
  `content` text NOT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `lang_code` varchar(5) DEFAULT 'ko',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- 테이블의 덤프 데이터 `reviews`
--

INSERT INTO `reviews` (`id`, `store_id`, `menu_id`, `order_id`, `user_id`, `guest_name`, `rating`, `content`, `image_path`, `lang_code`, `created_at`) VALUES
(1, 1, NULL, 1, 1, NULL, 5, 'great', NULL, 'ko', '2026-02-11 23:13:11'),
(2, 1, NULL, 8, 1, NULL, 3, '너무 맛이 없어요.. 별점을 드리기가 너무 힘들어요', NULL, 'ko', '2026-02-11 23:16:39'),
(3, 1, NULL, 1, 1, NULL, 5, '정말 기분이 아주 꽝입니다..', NULL, 'ko', '2026-02-11 23:20:54'),
(4, 1, NULL, 1, 1, NULL, 5, '와아 맛나요', 'uploads/reviews/rev_1_1770820497.jpg', 'ko', '2026-02-11 23:34:57'),
(5, 1, NULL, 1, 1, NULL, 4, '그래도 먹어야지요', 'uploads/reviews/rev_1_1770820826.jpg', 'ko', '2026-02-11 23:40:26');

-- --------------------------------------------------------

--
-- 테이블 구조 `stores`
--

CREATE TABLE `stores` (
  `id` int(11) NOT NULL,
  `store_name` varchar(255) NOT NULL,
  `currency_code` varchar(10) NOT NULL DEFAULT 'KRW',
  `service_charge_type` enum('NONE','PERCENT','FIX') DEFAULT 'NONE',
  `service_charge_rate` int(11) DEFAULT '0',
  `tax_inclusive` enum('Y','N') DEFAULT 'N',
  `tax_rate` int(11) DEFAULT '10',
  `allow_tip` enum('Y','N') DEFAULT 'N',
  `tip_type` enum('PERCENT','FIX') DEFAULT 'PERCENT',
  `tip_value` int(11) DEFAULT '0',
  `point_policy` enum('NONE','SINGLE','MULTI') DEFAULT 'NONE',
  `point_rate` int(11) DEFAULT '0',
  `point_payer` enum('HQ','STORE') DEFAULT 'STORE',
  `point_policy_id` int(11) DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `kds_theme` varchar(20) NOT NULL DEFAULT 'sky',
  `kds_alert_5` int(11) NOT NULL DEFAULT '5',
  `kds_alert_10` int(11) NOT NULL DEFAULT '10',
  `kds_alert_20` int(11) NOT NULL DEFAULT '20',
  `kds_alert_30` int(11) NOT NULL DEFAULT '30',
  `kds_sound` varchar(20) NOT NULL DEFAULT 'chime1',
  `kds_sound_custom` varchar(255) DEFAULT NULL,
  `use_local_status` tinyint(1) NOT NULL DEFAULT '1',
  `kds_datetime_locale` varchar(5) NOT NULL DEFAULT 'ko',
  `kds_sync_order_status` tinyint(1) NOT NULL DEFAULT '1',
  `kds_kitchen_theme` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- 테이블의 덤프 데이터 `stores`
--

INSERT INTO `stores` (`id`, `store_name`, `currency_code`, `service_charge_type`, `service_charge_rate`, `tax_inclusive`, `tax_rate`, `allow_tip`, `tip_type`, `tip_value`, `point_policy`, `point_rate`, `point_payer`, `point_policy_id`, `username`, `password`, `kds_theme`, `kds_alert_5`, `kds_alert_10`, `kds_alert_20`, `kds_alert_30`, `kds_sound`, `kds_sound_custom`, `use_local_status`, `kds_datetime_locale`, `kds_sync_order_status`, `kds_kitchen_theme`) VALUES
(1, 'Demo Store', 'KRW', 'NONE', 0, 'N', 10, 'N', 'PERCENT', 0, 'NONE', 0, 'STORE', 1, 'store1', 'store1234', 'pastel', 5, 10, 15, 20, 'chime1', '', 0, 'ko', 1, 'sky');

-- --------------------------------------------------------

--
-- 테이블 구조 `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nickname` varchar(100) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `username` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- 테이블의 덤프 데이터 `users`
--

INSERT INTO `users` (`id`, `email`, `password`, `nickname`, `created_at`, `username`) VALUES
(1, 'test@wirexcard.net', 'testpass', '홍길동', '2026-02-11 21:53:36', 'user1');

-- --------------------------------------------------------

--
-- 테이블 구조 `user_wallets`
--

CREATE TABLE `user_wallets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL DEFAULT '0',
  `asset_type` enum('SINGLE','MULTI','GLOBAL','ME','AD','WE') NOT NULL,
  `balance` int(11) NOT NULL DEFAULT '0',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- 테이블의 덤프 데이터 `user_wallets`
--

INSERT INTO `user_wallets` (`id`, `user_id`, `store_id`, `asset_type`, `balance`, `updated_at`) VALUES
(3, 1, 1, 'GLOBAL', 10000, '2026-02-11 21:54:52'),
(4, 1, 1, 'SINGLE', 5000, '2026-02-11 21:54:52'),
(5, 1, 1, 'MULTI', 8000, '2026-02-11 21:54:52'),
(6, 1, 1, 'WE', 3, '2026-02-11 21:54:52'),
(7, 1, 1, 'AD', 2, '2026-02-11 21:54:52'),
(8, 1, 1, 'ME', 5, '2026-02-11 21:54:52');

--
-- 덤프된 테이블의 인덱스
--

--
-- 테이블의 인덱스 `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- 테이블의 인덱스 `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- 테이블의 인덱스 `category_translations`
--
ALTER TABLE `category_translations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`);

--
-- 테이블의 인덱스 `menus`
--
ALTER TABLE `menus`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`);

--
-- 테이블의 인덱스 `menu_option_groups`
--
ALTER TABLE `menu_option_groups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `menu_id` (`menu_id`),
  ADD KEY `group_id` (`group_id`);

--
-- 테이블의 인덱스 `menu_translations`
--
ALTER TABLE `menu_translations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `menu_id` (`menu_id`);

--
-- 테이블의 인덱스 `option_groups`
--
ALTER TABLE `option_groups`
  ADD PRIMARY KEY (`id`);

--
-- 테이블의 인덱스 `option_items`
--
ALTER TABLE `option_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `group_id` (`group_id`);

--
-- 테이블의 인덱스 `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_orders_user` (`user_id`);

--
-- 테이블의 인덱스 `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `menu_id` (`menu_id`);

--
-- 테이블의 인덱스 `order_payments`
--
ALTER TABLE `order_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `payer_user_id` (`payer_user_id`);

--
-- 테이블의 인덱스 `order_sessions`
--
ALTER TABLE `order_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_store_status` (`store_id`,`status`);

--
-- 테이블의 인덱스 `point_logs`
--
ALTER TABLE `point_logs`
  ADD PRIMARY KEY (`id`);

--
-- 테이블의 인덱스 `point_master_policies`
--
ALTER TABLE `point_master_policies`
  ADD PRIMARY KEY (`id`);

--
-- 테이블의 인덱스 `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `store_id` (`store_id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `fk_reviews_menu` (`menu_id`);

--
-- 테이블의 인덱스 `stores`
--
ALTER TABLE `stores`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_stores_point_policy` (`point_policy_id`);

--
-- 테이블의 인덱스 `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- 테이블의 인덱스 `user_wallets`
--
ALTER TABLE `user_wallets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_wallet` (`user_id`,`store_id`,`asset_type`),
  ADD KEY `store_id` (`store_id`);

--
-- 덤프된 테이블의 AUTO_INCREMENT
--

--
-- 테이블의 AUTO_INCREMENT `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- 테이블의 AUTO_INCREMENT `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- 테이블의 AUTO_INCREMENT `category_translations`
--
ALTER TABLE `category_translations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- 테이블의 AUTO_INCREMENT `menus`
--
ALTER TABLE `menus`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- 테이블의 AUTO_INCREMENT `menu_option_groups`
--
ALTER TABLE `menu_option_groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- 테이블의 AUTO_INCREMENT `menu_translations`
--
ALTER TABLE `menu_translations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- 테이블의 AUTO_INCREMENT `option_groups`
--
ALTER TABLE `option_groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- 테이블의 AUTO_INCREMENT `option_items`
--
ALTER TABLE `option_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- 테이블의 AUTO_INCREMENT `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- 테이블의 AUTO_INCREMENT `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- 테이블의 AUTO_INCREMENT `order_payments`
--
ALTER TABLE `order_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 테이블의 AUTO_INCREMENT `order_sessions`
--
ALTER TABLE `order_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 테이블의 AUTO_INCREMENT `point_logs`
--
ALTER TABLE `point_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 테이블의 AUTO_INCREMENT `point_master_policies`
--
ALTER TABLE `point_master_policies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- 테이블의 AUTO_INCREMENT `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- 테이블의 AUTO_INCREMENT `stores`
--
ALTER TABLE `stores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- 테이블의 AUTO_INCREMENT `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- 테이블의 AUTO_INCREMENT `user_wallets`
--
ALTER TABLE `user_wallets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- 덤프된 테이블의 제약사항
--

--
-- 테이블의 제약사항 `category_translations`
--
ALTER TABLE `category_translations`
  ADD CONSTRAINT `category_translations_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`);

--
-- 테이블의 제약사항 `menus`
--
ALTER TABLE `menus`
  ADD CONSTRAINT `menus_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`);

--
-- 테이블의 제약사항 `menu_option_groups`
--
ALTER TABLE `menu_option_groups`
  ADD CONSTRAINT `menu_option_groups_ibfk_1` FOREIGN KEY (`menu_id`) REFERENCES `menus` (`id`),
  ADD CONSTRAINT `menu_option_groups_ibfk_2` FOREIGN KEY (`group_id`) REFERENCES `option_groups` (`id`);

--
-- 테이블의 제약사항 `menu_translations`
--
ALTER TABLE `menu_translations`
  ADD CONSTRAINT `menu_translations_ibfk_1` FOREIGN KEY (`menu_id`) REFERENCES `menus` (`id`);

--
-- 테이블의 제약사항 `option_items`
--
ALTER TABLE `option_items`
  ADD CONSTRAINT `option_items_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `option_groups` (`id`);

--
-- 테이블의 제약사항 `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- 테이블의 제약사항 `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`),
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`menu_id`) REFERENCES `menus` (`id`);

--
-- 테이블의 제약사항 `order_payments`
--
ALTER TABLE `order_payments`
  ADD CONSTRAINT `order_payments_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`),
  ADD CONSTRAINT `order_payments_ibfk_2` FOREIGN KEY (`payer_user_id`) REFERENCES `users` (`id`);

--
-- 테이블의 제약사항 `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `fk_reviews_menu` FOREIGN KEY (`menu_id`) REFERENCES `menus` (`id`),
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`),
  ADD CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`),
  ADD CONSTRAINT `reviews_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- 테이블의 제약사항 `stores`
--
ALTER TABLE `stores`
  ADD CONSTRAINT `fk_stores_point_policy` FOREIGN KEY (`point_policy_id`) REFERENCES `point_master_policies` (`id`);

--
-- 테이블의 제약사항 `user_wallets`
--
ALTER TABLE `user_wallets`
  ADD CONSTRAINT `user_wallets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `user_wallets_ibfk_2` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
