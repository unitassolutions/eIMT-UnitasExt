-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Feb 23, 2026 at 08:59 AM
-- Server version: 10.5.25-MariaDB-cll-lve
-- PHP Version: 8.1.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `skponuni_dap363`
--

-- --------------------------------------------------------

--
-- Table structure for table `app_ext_unitas_entity_buttons`
--

CREATE TABLE `app_ext_unitas_entity_buttons` (
  `id` int(11) NOT NULL,
  `entity_id` int(11) NOT NULL,
  `button_title` varchar(255) NOT NULL,
  `button_type` varchar(20) NOT NULL DEFAULT 'url',
  `report_id` int(11) DEFAULT NULL,
  `external_url` text DEFAULT NULL,
  `button_icon` varchar(50) NOT NULL DEFAULT 'fa-external-link',
  `button_color` varchar(50) NOT NULL DEFAULT 'btn-default',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `app_ext_unitas_entity_buttons`
--

INSERT INTO `app_ext_unitas_entity_buttons` (`id`, `entity_id`, `button_title`, `button_type`, `report_id`, `external_url`, `button_icon`, `button_color`, `sort_order`, `is_active`, `created_at`) VALUES
(1, 21, 'UNITAS #1', 'url', NULL, 'https://dev.onunitas.com/eIMTv2_SKP', '', 'btn-primary', 1, 1, '2025-12-09 05:29:30'),
(2, 21, 'UNITAS #2', 'report', NULL, 'https://dev.onunitas.com/eIMTv2_SKP', '', 'btn-primary', 2, 1, '2025-12-09 05:47:06'),
(3, 22, 'Sub-Entity Button #1', 'url', NULL, 'https://dev.onunitas.com/eIMTv2_SKP', '', 'btn-primary', 1, 1, '2025-12-09 06:02:05'),
(4, 22, 'Sub-Entity Button #2', 'url', NULL, '#', '', 'btn-primary', 2, 1, '2025-12-10 05:09:34'),
(5, 23, 'Ticket Sub-Entity #1', 'url', NULL, '#', '', 'btn-primary', 0, 1, '2025-12-10 05:15:58'),
(6, 1, 'User Button #1', 'report', NULL, '#', '', 'btn-primary', 0, 1, '2025-12-10 10:36:44'),
(7, 21, 'Project Report Button #1', 'url', NULL, 'https://www.skp.onunitas.com/apps/dap/3.6.3/index.php?module=reports/view&reports_id=83', '', 'btn-primary', 0, 1, '2025-12-11 05:55:07'),
(8, 21, 'Project Report Button #2', 'url', NULL, 'https://www.skp.onunitas.com/apps/dap/3.6.3/index.php?module=reports/view&reports_id=84', '', 'btn-primary', 0, 1, '2025-12-11 06:05:24'),
(9, 21, 'Modal Demo #1', 'report', 83, 'index.php?module=reports/view&reports_id=83', '', 'btn-primary', 0, 1, '2025-12-11 06:32:46');

-- --------------------------------------------------------

--
-- Table structure for table `app_unitas_map_reports`
--

CREATE TABLE `app_unitas_map_reports` (
  `id` int(11) NOT NULL,
  `entities_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `fields_id` int(11) NOT NULL,
  `users_groups` text NOT NULL,
  `in_menu` tinyint(1) NOT NULL,
  `background` int(11) NOT NULL,
  `fields_in_popup` text NOT NULL,
  `display_sidebar` tinyint(1) NOT NULL,
  `fields_in_sidebar` text NOT NULL,
  `sidebar_width` varchar(16) NOT NULL,
  `zoom` tinyint(1) NOT NULL,
  `latlng` varchar(16) NOT NULL,
  `is_public_access` tinyint(1) NOT NULL,
  `use_form_map_settings` tinyint(1) NOT NULL DEFAULT 0,
  `use_form_settings` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `app_unitas_map_reports`
--

INSERT INTO `app_unitas_map_reports` (`id`, `entities_id`, `name`, `fields_id`, `users_groups`, `in_menu`, `background`, `fields_in_popup`, `display_sidebar`, `fields_in_sidebar`, `sidebar_width`, `zoom`, `latlng`, `is_public_access`, `use_form_map_settings`, `use_form_settings`) VALUES
(4, 25, 'UNITAS Map Reports v2 #2', 213, '6,5,4', 1, 0, '', 1, '[214]', '', 4, '', 1, 1, 0);

-- --------------------------------------------------------

--
-- Table structure for table `app_unitas_map_reports_config`
--

CREATE TABLE `app_unitas_map_reports_config` (
  `id` int(11) NOT NULL,
  `google_map_api_key` varchar(255) NOT NULL,
  `map_style_light` varchar(64) NOT NULL,
  `map_style_dark` varchar(64) NOT NULL,
  `default_theme` varchar(10) DEFAULT 'auto',
  `default_lat` varchar(32) DEFAULT NULL,
  `default_lng` varchar(32) DEFAULT NULL,
  `default_zoom` int(11) DEFAULT 8
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `app_unitas_map_reports_config`
--

INSERT INTO `app_unitas_map_reports_config` (`id`, `google_map_api_key`, `map_style_light`, `map_style_dark`, `default_theme`, `default_lat`, `default_lng`, `default_zoom`) VALUES
(1, 'AIzaSyDzXuIgQB25EwKVwllu8DjRpZ0daVnYaZM', '2391d7c97ad36e16d94665fe', '2391d7c97ad36e162db612bd', 'dark', '22.364006', '76.7566161', 7);

-- --------------------------------------------------------

--
-- Table structure for table `app_unitas_pivot_map_reports`
--

CREATE TABLE `app_unitas_pivot_map_reports` (
  `id` int(11) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `users_groups` text NOT NULL,
  `is_public_access` tinyint(1) NOT NULL DEFAULT 0,
  `in_menu` tinyint(1) NOT NULL,
  `zoom` tinyint(1) NOT NULL,
  `latlng` varchar(16) NOT NULL,
  `display_legend` tinyint(1) NOT NULL,
  `display_sidebar` tinyint(1) NOT NULL,
  `sidebar_width` varchar(16) NOT NULL,
  `map_type` varchar(20) NOT NULL DEFAULT 'google',
  `use_form_map_settings` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `app_unitas_pivot_map_reports`
--

INSERT INTO `app_unitas_pivot_map_reports` (`id`, `name`, `users_groups`, `is_public_access`, `in_menu`, `zoom`, `latlng`, `display_legend`, `display_sidebar`, `sidebar_width`, `map_type`, `use_form_map_settings`) VALUES
(4, 'UNITAS Pivot Map Reports #1', '6,5,4', 1, 1, 10, '', 1, 1, '', 'google', 0);

-- --------------------------------------------------------

--
-- Table structure for table `app_unitas_pivot_map_reports_entities`
--

CREATE TABLE `app_unitas_pivot_map_reports_entities` (
  `id` int(11) UNSIGNED NOT NULL,
  `reports_id` int(11) NOT NULL,
  `entities_id` int(11) NOT NULL,
  `fields_id` int(11) NOT NULL,
  `background` int(11) NOT NULL,
  `fields_in_popup` text NOT NULL,
  `fields_in_sidebar` text NOT NULL,
  `marker_color` varchar(16) NOT NULL,
  `marker_icon` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `app_unitas_pivot_map_reports_entities`
--

INSERT INTO `app_unitas_pivot_map_reports_entities` (`id`, `reports_id`, `entities_id`, `fields_id`, `background`, `fields_in_popup`, `fields_in_sidebar`, `marker_color`, `marker_icon`) VALUES
(5, 4, 25, 213, 0, '214', '[214]', '', ''),
(6, 4, 26, 223, 0, '224', '[224]', '', '');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `app_ext_unitas_entity_buttons`
--
ALTER TABLE `app_ext_unitas_entity_buttons`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_entity_id` (`entity_id`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- Indexes for table `app_unitas_map_reports`
--
ALTER TABLE `app_unitas_map_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_entities_id` (`entities_id`),
  ADD KEY `idx_fields_id` (`fields_id`);

--
-- Indexes for table `app_unitas_map_reports_config`
--
ALTER TABLE `app_unitas_map_reports_config`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `app_unitas_pivot_map_reports`
--
ALTER TABLE `app_unitas_pivot_map_reports`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `app_unitas_pivot_map_reports_entities`
--
ALTER TABLE `app_unitas_pivot_map_reports_entities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_entities_id` (`entities_id`),
  ADD KEY `idx_fields_id` (`fields_id`),
  ADD KEY `idx_reports_id` (`reports_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `app_ext_unitas_entity_buttons`
--
ALTER TABLE `app_ext_unitas_entity_buttons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `app_unitas_map_reports`
--
ALTER TABLE `app_unitas_map_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `app_unitas_pivot_map_reports`
--
ALTER TABLE `app_unitas_pivot_map_reports`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `app_unitas_pivot_map_reports_entities`
--
ALTER TABLE `app_unitas_pivot_map_reports_entities`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
