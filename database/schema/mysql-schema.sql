/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `api_sync_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `api_sync_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `provider` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'football_data',
  `endpoint` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `http_status` smallint unsigned DEFAULT NULL,
  `success` tinyint(1) NOT NULL DEFAULT '0',
  `records_total` int unsigned NOT NULL DEFAULT '0',
  `records_changed` int unsigned NOT NULL DEFAULT '0',
  `message` text COLLATE utf8mb4_unicode_ci,
  `meta` json DEFAULT NULL,
  `synced_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `api_sync_logs_provider_success_synced_at_index` (`provider`,`success`,`synced_at`)
) ENGINE=InnoDB AUTO_INCREMENT=1439 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `competition_package_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `competition_package_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `competition_package_id` bigint unsigned NOT NULL,
  `competition_id` bigint unsigned DEFAULT NULL,
  `competition_code` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `competition_package_items_pkg_code_unique` (`competition_package_id`,`competition_code`),
  KEY `competition_package_items_competition_id_foreign` (`competition_id`),
  KEY `competition_package_items_code_pkg_idx` (`competition_code`,`competition_package_id`),
  CONSTRAINT `competition_package_items_competition_id_foreign` FOREIGN KEY (`competition_id`) REFERENCES `competitions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `competition_package_items_competition_package_id_foreign` FOREIGN KEY (`competition_package_id`) REFERENCES `competition_packages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `competition_packages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `competition_packages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `competition_packages_code_unique` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `competition_seasons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `competition_seasons` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `competition_id` bigint unsigned NOT NULL,
  `provider` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'football_data',
  `external_id` bigint unsigned NOT NULL,
  `year` smallint unsigned NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `current_matchday` smallint unsigned DEFAULT NULL,
  `winner_payload` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `competition_seasons_provider_external_id_unique` (`provider`,`external_id`),
  UNIQUE KEY `competition_seasons_competition_id_year_unique` (`competition_id`,`year`),
  CONSTRAINT `competition_seasons_competition_id_foreign` FOREIGN KEY (`competition_id`) REFERENCES `competitions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `competitions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `competitions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `provider` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'football_data',
  `external_id` bigint unsigned NOT NULL,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emblem` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `competitions_provider_external_id_unique` (`provider`,`external_id`),
  KEY `competitions_provider_code_index` (`provider`,`code`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB AUTO_INCREMENT=74 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `football_match_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `football_match_details` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `football_match_id` bigint unsigned NOT NULL,
  `provider` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'football_data',
  `external_id` bigint unsigned NOT NULL,
  `payload` json DEFAULT NULL,
  `fetched_at` timestamp NULL DEFAULT NULL,
  `last_error` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `football_match_details_football_match_id_unique` (`football_match_id`),
  KEY `football_match_details_provider_external_id_index` (`provider`,`external_id`),
  CONSTRAINT `football_match_details_football_match_id_foreign` FOREIGN KEY (`football_match_id`) REFERENCES `football_matches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=53 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `football_matches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `football_matches` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `provider` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'football_data',
  `external_id` bigint unsigned NOT NULL,
  `competition_id` bigint unsigned NOT NULL,
  `competition_season_id` bigint unsigned NOT NULL,
  `home_team_id` bigint unsigned DEFAULT NULL,
  `away_team_id` bigint unsigned DEFAULT NULL,
  `utc_date` datetime NOT NULL,
  `local_date` datetime DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `matchday` smallint unsigned DEFAULT NULL,
  `stage` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `group_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `score_winner` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `score_duration` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `home_score_full_time` tinyint unsigned DEFAULT NULL,
  `away_score_full_time` tinyint unsigned DEFAULT NULL,
  `home_score_half_time` tinyint unsigned DEFAULT NULL,
  `away_score_half_time` tinyint unsigned DEFAULT NULL,
  `home_score_extra_time` tinyint unsigned DEFAULT NULL,
  `away_score_extra_time` tinyint unsigned DEFAULT NULL,
  `home_score_penalties` tinyint unsigned DEFAULT NULL,
  `away_score_penalties` tinyint unsigned DEFAULT NULL,
  `last_updated_by_provider_at` timestamp NULL DEFAULT NULL,
  `in_play_started_at` timestamp NULL DEFAULT NULL,
  `interval_started_at` timestamp NULL DEFAULT NULL,
  `resumed_from_interval_at` timestamp NULL DEFAULT NULL,
  `finished_at` timestamp NULL DEFAULT NULL,
  `live_clock_anchor_at` timestamp NULL DEFAULT NULL,
  `live_clock_accumulated_seconds` int unsigned NOT NULL DEFAULT '0',
  `raw_payload` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `football_matches_provider_external_id_unique` (`provider`,`external_id`),
  KEY `football_matches_competition_season_id_foreign` (`competition_season_id`),
  KEY `football_matches_home_team_id_foreign` (`home_team_id`),
  KEY `football_matches_away_team_id_foreign` (`away_team_id`),
  KEY `football_matches_competition_id_competition_season_id_index` (`competition_id`,`competition_season_id`),
  KEY `football_matches_stage_group_name_index` (`stage`,`group_name`),
  KEY `football_matches_utc_date_status_index` (`utc_date`,`status`),
  CONSTRAINT `football_matches_away_team_id_foreign` FOREIGN KEY (`away_team_id`) REFERENCES `teams` (`id`) ON DELETE SET NULL,
  CONSTRAINT `football_matches_competition_id_foreign` FOREIGN KEY (`competition_id`) REFERENCES `competitions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `football_matches_competition_season_id_foreign` FOREIGN KEY (`competition_season_id`) REFERENCES `competition_seasons` (`id`) ON DELETE CASCADE,
  CONSTRAINT `football_matches_home_team_id_foreign` FOREIGN KEY (`home_team_id`) REFERENCES `teams` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=453 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB AUTO_INCREMENT=819 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `legal_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `legal_documents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `version` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '0',
  `published_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `active_type` varchar(50) COLLATE utf8mb4_unicode_ci GENERATED ALWAYS AS ((case when (`is_active` = 1) then `type` else NULL end)) STORED,
  PRIMARY KEY (`id`),
  UNIQUE KEY `legal_documents_type_version_unique` (`type`,`version`),
  UNIQUE KEY `legal_documents_active_type_unique` (`active_type`),
  KEY `legal_documents_created_by_foreign` (`created_by`),
  KEY `legal_documents_type_is_active_index` (`type`,`is_active`),
  KEY `legal_documents_type_published_at_index` (`type`,`published_at`),
  CONSTRAINT `legal_documents_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `match_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `match_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `football_match_id` bigint unsigned NOT NULL,
  `provider` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'api_football',
  `minute` smallint unsigned DEFAULT NULL,
  `extra_minute` smallint unsigned DEFAULT NULL,
  `team_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `player_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assist_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `event_type` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `event_detail` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `raw_payload` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `match_events_football_match_id_provider_index` (`football_match_id`,`provider`),
  KEY `match_events_event_type_minute_index` (`event_type`,`minute`),
  CONSTRAINT `match_events_football_match_id_foreign` FOREIGN KEY (`football_match_id`) REFERENCES `football_matches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `match_lineups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `match_lineups` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `football_match_id` bigint unsigned NOT NULL,
  `provider` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'api_football',
  `team_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `formation` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `start_xi` json DEFAULT NULL,
  `substitutes` json DEFAULT NULL,
  `coach` json DEFAULT NULL,
  `raw_payload` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `match_lineups_unique_team` (`football_match_id`,`provider`,`team_name`),
  CONSTRAINT `match_lineups_football_match_id_foreign` FOREIGN KEY (`football_match_id`) REFERENCES `football_matches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `match_player_statistics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `match_player_statistics` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `football_match_id` bigint unsigned NOT NULL,
  `provider` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'api_football',
  `team_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `player_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider_player_id` bigint unsigned DEFAULT NULL,
  `statistics` json DEFAULT NULL,
  `raw_payload` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `match_player_statistics_football_match_id_provider_index` (`football_match_id`,`provider`),
  KEY `match_player_statistics_provider_player_id_index` (`provider_player_id`),
  CONSTRAINT `match_player_statistics_football_match_id_foreign` FOREIGN KEY (`football_match_id`) REFERENCES `football_matches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `match_provider_refs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `match_provider_refs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `football_match_id` bigint unsigned NOT NULL,
  `provider` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `external_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `match_provider_refs_provider_external_id_unique` (`provider`,`external_id`),
  UNIQUE KEY `match_provider_refs_football_match_id_provider_unique` (`football_match_id`,`provider`),
  KEY `match_provider_refs_football_match_id_index` (`football_match_id`),
  CONSTRAINT `match_provider_refs_football_match_id_foreign` FOREIGN KEY (`football_match_id`) REFERENCES `football_matches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=453 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `match_team_statistics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `match_team_statistics` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `football_match_id` bigint unsigned NOT NULL,
  `provider` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'api_football',
  `team_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `statistics` json DEFAULT NULL,
  `raw_payload` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `match_team_stats_unique_team` (`football_match_id`,`provider`,`team_name`),
  CONSTRAINT `match_team_statistics_football_match_id_foreign` FOREIGN KEY (`football_match_id`) REFERENCES `football_matches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pool_invites`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pool_invites` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pool_id` bigint unsigned NOT NULL,
  `invited_by` bigint unsigned NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sector` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `token` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `accepted_by` bigint unsigned DEFAULT NULL,
  `accepted_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pool_invites_token_unique` (`token`),
  KEY `pool_invites_invited_by_foreign` (`invited_by`),
  KEY `pool_invites_accepted_by_foreign` (`accepted_by`),
  KEY `pool_invites_pool_id_status_index` (`pool_id`,`status`),
  KEY `pool_invites_email_status_index` (`email`,`status`),
  CONSTRAINT `pool_invites_accepted_by_foreign` FOREIGN KEY (`accepted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pool_invites_invited_by_foreign` FOREIGN KEY (`invited_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pool_invites_pool_id_foreign` FOREIGN KEY (`pool_id`) REFERENCES `pools` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pool_members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pool_members` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pool_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `role` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'member',
  `sector` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `activated_by` bigint unsigned DEFAULT NULL,
  `activated_at` timestamp NULL DEFAULT NULL,
  `deactivated_by` bigint unsigned DEFAULT NULL,
  `deactivated_at` timestamp NULL DEFAULT NULL,
  `admin_note` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pool_members_pool_id_user_id_unique` (`pool_id`,`user_id`),
  KEY `pool_members_activated_by_foreign` (`activated_by`),
  KEY `pool_members_deactivated_by_foreign` (`deactivated_by`),
  KEY `pool_members_pool_id_status_index` (`pool_id`,`status`),
  KEY `pool_members_user_id_status_index` (`user_id`,`status`),
  CONSTRAINT `pool_members_activated_by_foreign` FOREIGN KEY (`activated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pool_members_deactivated_by_foreign` FOREIGN KEY (`deactivated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pool_members_pool_id_foreign` FOREIGN KEY (`pool_id`) REFERENCES `pools` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pool_members_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pool_rankings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pool_rankings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pool_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `points_total` int unsigned NOT NULL DEFAULT '0',
  `exact_scores` smallint unsigned NOT NULL DEFAULT '0',
  `correct_results` smallint unsigned NOT NULL DEFAULT '0',
  `correct_home_goals` smallint unsigned NOT NULL DEFAULT '0',
  `correct_away_goals` smallint unsigned NOT NULL DEFAULT '0',
  `predictions_counted` smallint unsigned NOT NULL DEFAULT '0',
  `position` smallint unsigned DEFAULT NULL,
  `last_calculated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pool_rankings_pool_id_user_id_unique` (`pool_id`,`user_id`),
  KEY `pool_rankings_user_id_foreign` (`user_id`),
  KEY `pool_rankings_pool_id_position_index` (`pool_id`,`position`),
  CONSTRAINT `pool_rankings_pool_id_foreign` FOREIGN KEY (`pool_id`) REFERENCES `pools` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pool_rankings_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pools`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pools` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `owner_id` bigint unsigned NOT NULL,
  `competition_id` bigint unsigned DEFAULT NULL,
  `competition_season_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `instructions` text COLLATE utf8mb4_unicode_ci,
  `sectors` json DEFAULT NULL,
  `tie_breakers` json DEFAULT NULL,
  `visibility` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'invite_only',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `invite_code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `allow_prediction_changes` tinyint(1) NOT NULL DEFAULT '1',
  `prediction_lock_minutes` smallint unsigned NOT NULL DEFAULT '120',
  `allow_pending_member_predictions` tinyint(1) NOT NULL DEFAULT '1',
  `stage` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'GROUP_STAGE',
  `points_exact_score` tinyint unsigned NOT NULL DEFAULT '5',
  `points_correct_result` tinyint unsigned NOT NULL DEFAULT '3',
  `points_correct_goals` tinyint unsigned NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pools_slug_unique` (`slug`),
  UNIQUE KEY `pools_invite_code_unique` (`invite_code`),
  KEY `pools_owner_id_status_index` (`owner_id`,`status`),
  KEY `pools_visibility_status_index` (`visibility`,`status`),
  KEY `pools_competition_season_id_foreign` (`competition_season_id`),
  KEY `pools_competition_season_status_idx` (`competition_id`,`competition_season_id`,`status`),
  CONSTRAINT `pools_competition_id_foreign` FOREIGN KEY (`competition_id`) REFERENCES `competitions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pools_competition_season_id_foreign` FOREIGN KEY (`competition_season_id`) REFERENCES `competition_seasons` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pools_owner_id_foreign` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `prediction_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prediction_versions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `prediction_id` bigint unsigned NOT NULL,
  `changed_by` bigint unsigned DEFAULT NULL,
  `home_score` tinyint unsigned NOT NULL,
  `away_score` tinyint unsigned NOT NULL,
  `changed_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `prediction_versions_changed_by_foreign` (`changed_by`),
  KEY `prediction_versions_prediction_id_changed_at_index` (`prediction_id`,`changed_at`),
  CONSTRAINT `prediction_versions_changed_by_foreign` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `prediction_versions_prediction_id_foreign` FOREIGN KEY (`prediction_id`) REFERENCES `predictions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `predictions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `predictions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pool_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `football_match_id` bigint unsigned NOT NULL,
  `home_score` tinyint unsigned NOT NULL,
  `away_score` tinyint unsigned NOT NULL,
  `points` int unsigned NOT NULL DEFAULT '0',
  `last_changed_at` timestamp NULL DEFAULT NULL,
  `locked_at` timestamp NULL DEFAULT NULL,
  `calculated_at` timestamp NULL DEFAULT NULL,
  `eligible` tinyint(1) NOT NULL DEFAULT '1',
  `ineligible_reason` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `predictions_unique_pool_user_match` (`pool_id`,`user_id`,`football_match_id`),
  KEY `predictions_user_id_foreign` (`user_id`),
  KEY `predictions_pool_id_eligible_index` (`pool_id`,`eligible`),
  KEY `predictions_football_match_id_calculated_at_index` (`football_match_id`,`calculated_at`),
  CONSTRAINT `predictions_football_match_id_foreign` FOREIGN KEY (`football_match_id`) REFERENCES `football_matches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `predictions_pool_id_foreign` FOREIGN KEY (`pool_id`) REFERENCES `pools` (`id`) ON DELETE CASCADE,
  CONSTRAINT `predictions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `standing_rows`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `standing_rows` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `standing_id` bigint unsigned NOT NULL,
  `team_id` bigint unsigned DEFAULT NULL,
  `position` tinyint unsigned DEFAULT NULL,
  `played_games` smallint unsigned NOT NULL DEFAULT '0',
  `won` smallint unsigned NOT NULL DEFAULT '0',
  `draw` smallint unsigned NOT NULL DEFAULT '0',
  `lost` smallint unsigned NOT NULL DEFAULT '0',
  `goal_difference` smallint NOT NULL DEFAULT '0',
  `goals_for` smallint unsigned NOT NULL DEFAULT '0',
  `goals_against` smallint unsigned NOT NULL DEFAULT '0',
  `points` smallint unsigned NOT NULL DEFAULT '0',
  `raw_payload` json DEFAULT NULL,
  `form_payload` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `standing_rows_team_id_foreign` (`team_id`),
  KEY `standing_rows_standing_position_idx` (`standing_id`,`position`),
  CONSTRAINT `standing_rows_standing_id_foreign` FOREIGN KEY (`standing_id`) REFERENCES `standings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `standing_rows_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=26113 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `standings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `standings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `competition_id` bigint unsigned NOT NULL,
  `competition_season_id` bigint unsigned NOT NULL,
  `provider` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'football_data',
  `external_id` bigint unsigned DEFAULT NULL,
  `stage` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `group_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `raw_payload` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `standings_competition_season_id_foreign` (`competition_season_id`),
  KEY `standings_competition_season_idx` (`competition_id`,`competition_season_id`),
  KEY `standings_stage_group_idx` (`stage`,`group_name`),
  CONSTRAINT `standings_competition_id_foreign` FOREIGN KEY (`competition_id`) REFERENCES `competitions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `standings_competition_season_id_foreign` FOREIGN KEY (`competition_season_id`) REFERENCES `competition_seasons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=581 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `team_provider_refs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `team_provider_refs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `team_id` bigint unsigned NOT NULL,
  `provider` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `external_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `team_provider_refs_provider_external_id_unique` (`provider`,`external_id`),
  UNIQUE KEY `team_provider_refs_team_id_provider_unique` (`team_id`,`provider`),
  KEY `team_provider_refs_team_id_index` (`team_id`),
  CONSTRAINT `team_provider_refs_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=69 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `teams`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `teams` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `provider` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'football_data',
  `external_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `short_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tla` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `crest` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `teams_provider_external_id_unique` (`provider`,`external_id`),
  KEY `teams_tla_index` (`tla`)
) ENGINE=InnoDB AUTO_INCREMENT=69 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_legal_acceptances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_legal_acceptances` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `legal_document_id` bigint unsigned NOT NULL,
  `accepted_at` timestamp NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_legal_acceptances_user_id_legal_document_id_unique` (`user_id`,`legal_document_id`),
  KEY `user_legal_acceptances_legal_document_id_accepted_at_index` (`legal_document_id`,`accepted_at`),
  KEY `user_legal_acceptances_user_id_accepted_at_index` (`user_id`,`accepted_at`),
  CONSTRAINT `user_legal_acceptances_legal_document_id_foreign` FOREIGN KEY (`legal_document_id`) REFERENCES `legal_documents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_legal_acceptances_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_name` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `area` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `is_admin` tinyint(1) NOT NULL DEFAULT '0',
  `subscription_tier` tinyint unsigned NOT NULL DEFAULT '1',
  `competition_package_id` bigint unsigned DEFAULT NULL,
  `must_change_password` tinyint(1) NOT NULL DEFAULT '0',
  `password_changed_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_package_tier_idx` (`competition_package_id`,`subscription_tier`),
  CONSTRAINT `users_competition_package_id_foreign` FOREIGN KEY (`competition_package_id`) REFERENCES `competition_packages` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

