[sudo] password for rom: -- MySQL dump 10.13  Distrib 8.0.43, for Linux (x86_64)
--
-- Host: localhost    Database: nuuitasi_calendar4
-- ------------------------------------------------------
-- Server version	8.0.43-0ubuntu0.22.04.1

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `booking`
--

DROP TABLE IF EXISTS `booking`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `booking` (
  `unic_id` int NOT NULL AUTO_INCREMENT,
  `id_specialist` int DEFAULT NULL,
  `id_work_place` int DEFAULT NULL,
  `day_of_creation` datetime DEFAULT NULL,
  `service_id` int NOT NULL,
  `booking_start_datetime` datetime DEFAULT NULL,
  `booking_end_datetime` datetime DEFAULT NULL,
  `client_full_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `client_phone_nr` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `google_event_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Google Calendar Event ID for sync tracking',
  `received_through` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `received_call_date` datetime DEFAULT NULL,
  `client_transcript_conversation` mediumtext COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`unic_id`),
  KEY `idx_google_event_id` (`google_event_id`)
) ENGINE=InnoDB AUTO_INCREMENT=367 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `booking_after_insert` AFTER INSERT ON `booking` FOR EACH ROW BEGIN
    INSERT INTO booking_event_queue (event_type, specialist_id, working_point_id, booking_data)
    VALUES (
        'create',
        NEW.id_specialist,
        NEW.id_work_place,
        JSON_OBJECT(
            'booking_id', NEW.unic_id,
            'specialist_id', NEW.id_specialist,
            'working_point_id', NEW.id_work_place,
            'client_full_name', NEW.client_full_name,
            'booking_start_datetime', NEW.booking_start_datetime,
            'booking_end_datetime', NEW.booking_end_datetime
        )
    );
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `booking_after_insert_sms` AFTER INSERT ON `booking` FOR EACH ROW BEGIN
      DECLARE v_force_sms VARCHAR(10) DEFAULT 'default';

      
      IF @force_sms IS NOT NULL THEN
          SET v_force_sms = @force_sms;
          SET @force_sms = NULL; 
      END IF;

      INSERT INTO booking_sms_queue (action, booking_id, booking_data, force_sms)
      VALUES ('created', NEW.unic_id, JSON_OBJECT(
          'unic_id', NEW.unic_id,
          'id_specialist', NEW.id_specialist,
          'id_work_place', NEW.id_work_place,
          'service_id', NEW.service_id,
          'booking_start_datetime', NEW.booking_start_datetime,
          'booking_end_datetime', NEW.booking_end_datetime,
          'client_full_name', NEW.client_full_name,
          'client_phone_nr', NEW.client_phone_nr,
          'received_through', NEW.received_through,
          'day_of_creation', NEW.day_of_creation
      ), v_force_sms);
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `booking_gcal_after_insert` AFTER INSERT ON `booking` FOR EACH ROW BEGIN
      IF EXISTS (
          SELECT 1 FROM google_calendar_credentials
          WHERE specialist_id = NEW.id_specialist
          AND status IN ('connected', 'active', 'enabled')
          LIMIT 1
      ) THEN
          INSERT INTO google_calendar_sync_queue
          (event_type, booking_id, specialist_id, payload, status, attempts, created_at, updated_at)
          VALUES (
              'created',
              NEW.unic_id,
              NEW.id_specialist,
              JSON_OBJECT(
                  'booking_id', NEW.unic_id,
                  'client_full_name', NEW.client_full_name,
                  'client_phone_nr', NEW.client_phone_nr,
                  'booking_start_datetime', NEW.booking_start_datetime,
                  'booking_end_datetime', NEW.booking_end_datetime,
                  'id_specialist', NEW.id_specialist,
                  'id_work_place', NEW.id_work_place,
                  'service_id', NEW.service_id,
                  'received_through', NEW.received_through,
                  'day_of_creation', NEW.day_of_creation,
                  'unic_id', NEW.unic_id
              ),
              'pending',
              0,
              NOW(),
              NOW()
          );

          INSERT INTO gcal_worker_signals
          (specialist_id, booking_id, event_type, processed, created_at)
          VALUES (
              NEW.id_specialist,
              NEW.unic_id,
              'created',
              FALSE,
              NOW()
          );
      END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `booking_after_update` AFTER UPDATE ON `booking` FOR EACH ROW BEGIN
    INSERT INTO booking_event_queue (event_type, specialist_id, working_point_id, booking_data)
    VALUES (
        'update',
        NEW.id_specialist,
        NEW.id_work_place,
        JSON_OBJECT(
            'booking_id', NEW.unic_id,
            'specialist_id', NEW.id_specialist,
            'working_point_id', NEW.id_work_place,
            'client_full_name', NEW.client_full_name,
            'booking_start_datetime', NEW.booking_start_datetime,
            'booking_end_datetime', NEW.booking_end_datetime,
            'old_specialist_id', OLD.id_specialist,
            'old_working_point_id', OLD.id_work_place
        )
    );
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `booking_after_update_sms` AFTER UPDATE ON `booking` FOR EACH ROW BEGIN
      DECLARE v_force_sms VARCHAR(10) DEFAULT 'default';

      
      IF @force_sms IS NOT NULL THEN
          SET v_force_sms = @force_sms;
          SET @force_sms = NULL; 
      END IF;

      
      IF OLD.booking_start_datetime != NEW.booking_start_datetime
         OR OLD.booking_end_datetime != NEW.booking_end_datetime
         OR OLD.service_id != NEW.service_id
         OR OLD.id_specialist != NEW.id_specialist
         OR OLD.id_work_place != NEW.id_work_place THEN

          INSERT INTO booking_sms_queue (action, booking_id, booking_data, force_sms)
          VALUES ('updated', NEW.unic_id, JSON_OBJECT(
              'unic_id', NEW.unic_id,
              'id_specialist', NEW.id_specialist,
              'id_work_place', NEW.id_work_place,
              'service_id', NEW.service_id,
              'booking_start_datetime', NEW.booking_start_datetime,
              'booking_end_datetime', NEW.booking_end_datetime,
              'client_full_name', NEW.client_full_name,
              'client_phone_nr', NEW.client_phone_nr,
              'received_through', NEW.received_through,
              'day_of_creation', NEW.day_of_creation,
              'old_booking_start_datetime', OLD.booking_start_datetime,
              'old_booking_end_datetime', OLD.booking_end_datetime
          ), v_force_sms);
      END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `booking_gcal_after_update` AFTER UPDATE ON `booking` FOR EACH ROW BEGIN
      IF EXISTS (
          SELECT 1 FROM google_calendar_credentials
          WHERE specialist_id = NEW.id_specialist
          AND status IN ('connected', 'active', 'enabled')
          LIMIT 1
      ) THEN
          INSERT INTO google_calendar_sync_queue
          (event_type, booking_id, specialist_id, payload, status, attempts, created_at, updated_at)
          VALUES (
              'updated',
              NEW.unic_id,
              NEW.id_specialist,
              JSON_OBJECT(
                  'booking_id', NEW.unic_id,
                  'client_full_name', NEW.client_full_name,
                  'client_phone_nr', NEW.client_phone_nr,
                  'booking_start_datetime', NEW.booking_start_datetime,
                  'booking_end_datetime', NEW.booking_end_datetime,
                  'id_specialist', NEW.id_specialist,
                  'id_work_place', NEW.id_work_place,
                  'service_id', NEW.service_id,
                  'received_through', NEW.received_through,
                  'day_of_creation', NEW.day_of_creation,
                  'unic_id', NEW.unic_id,
                  'old_specialist_id', OLD.id_specialist,
                  'old_start_time', OLD.booking_start_datetime,
                  'old_end_time', OLD.booking_end_datetime
              ),
              'pending',
              0,
              NOW(),
              NOW()
          );

          INSERT INTO gcal_worker_signals
          (specialist_id, booking_id, event_type, processed, created_at)
          VALUES (
              NEW.id_specialist,
              NEW.unic_id,
              'updated',
              FALSE,
              NOW()
          );
      END IF;

      IF OLD.id_specialist != NEW.id_specialist AND EXISTS (
          SELECT 1 FROM google_calendar_credentials
          WHERE specialist_id = OLD.id_specialist
          AND status IN ('connected', 'active', 'enabled')
          LIMIT 1
      ) THEN
          INSERT INTO google_calendar_sync_queue
          (event_type, booking_id, specialist_id, payload, status, attempts, created_at, updated_at)
          VALUES (
              'deleted',
              NEW.unic_id,
              OLD.id_specialist,
              JSON_OBJECT(
                  'booking_id', NEW.unic_id,
                  'client_full_name', OLD.client_full_name,
                  'client_phone_nr', OLD.client_phone_nr,
                  'booking_start_datetime', OLD.booking_start_datetime,
                  'booking_end_datetime', OLD.booking_end_datetime,
                  'id_specialist', OLD.id_specialist,
                  'id_work_place', OLD.id_work_place,
                  'service_id', OLD.service_id,
                  'received_through', OLD.received_through,
                  'day_of_creation', OLD.day_of_creation,
                  'unic_id', OLD.unic_id
              ),
              'pending',
              0,
              NOW(),
              NOW()
          );

          INSERT INTO gcal_worker_signals
          (specialist_id, booking_id, event_type, processed, created_at)
          VALUES (
              OLD.id_specialist,
              NEW.unic_id,
              'deleted',
              FALSE,
              NOW()
          );
      END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `booking_before_delete` BEFORE DELETE ON `booking` FOR EACH ROW BEGIN
    INSERT INTO booking_event_queue (event_type, specialist_id, working_point_id, booking_data)
    VALUES (
        'delete',
        OLD.id_specialist,
        OLD.id_work_place,
        JSON_OBJECT(
            'booking_id', OLD.unic_id,
            'specialist_id', OLD.id_specialist,
            'working_point_id', OLD.id_work_place,
            'client_full_name', OLD.client_full_name,
            'booking_start_datetime', OLD.booking_start_datetime,
            'booking_end_datetime', OLD.booking_end_datetime
        )
    );
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `booking_before_delete_sms` BEFORE DELETE ON `booking` FOR EACH ROW BEGIN
      DECLARE v_force_sms VARCHAR(10) DEFAULT 'default';

      
      IF @force_sms IS NOT NULL THEN
          SET v_force_sms = @force_sms;
          SET @force_sms = NULL; 
      END IF;

      INSERT INTO booking_sms_queue (action, booking_id, booking_data, force_sms)
      VALUES ('deleted', OLD.unic_id, JSON_OBJECT(
          'unic_id', OLD.unic_id,
          'id_specialist', OLD.id_specialist,
          'id_work_place', OLD.id_work_place,
          'service_id', OLD.service_id,
          'booking_start_datetime', OLD.booking_start_datetime,
          'booking_end_datetime', OLD.booking_end_datetime,
          'client_full_name', OLD.client_full_name,
          'client_phone_nr', OLD.client_phone_nr,
          'received_through', OLD.received_through,
          'day_of_creation', OLD.day_of_creation
      ), v_force_sms);
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `booking_gcal_before_delete` BEFORE DELETE ON `booking` FOR EACH ROW BEGIN
      IF EXISTS (
          SELECT 1 FROM google_calendar_credentials
          WHERE specialist_id = OLD.id_specialist
          AND status IN ('connected', 'active', 'enabled')
          LIMIT 1
      ) THEN
          INSERT INTO google_calendar_sync_queue
          (event_type, booking_id, specialist_id, payload, status, attempts, created_at, updated_at)
          VALUES (
              'deleted',
              OLD.unic_id,
              OLD.id_specialist,
              JSON_OBJECT(
                  'booking_id', OLD.unic_id,
                  'client_full_name', OLD.client_full_name,
                  'client_phone_nr', OLD.client_phone_nr,
                  'booking_start_datetime', OLD.booking_start_datetime,
                  'booking_end_datetime', OLD.booking_end_datetime,
                  'id_specialist', OLD.id_specialist,
                  'id_work_place', OLD.id_work_place,
                  'service_id', OLD.service_id,
                  'received_through', OLD.received_through,
                  'day_of_creation', OLD.day_of_creation,
                  'unic_id', OLD.unic_id,
                  'google_event_id', OLD.google_event_id
              ),
              'pending',
              0,
              NOW(),
              NOW()
          );

          INSERT INTO gcal_worker_signals
          (specialist_id, booking_id, event_type, processed, created_at)
          VALUES (
              OLD.id_specialist,
              OLD.unic_id,
              'deleted',
              FALSE,
              NOW()
          );
      END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `booking_canceled`
--

DROP TABLE IF EXISTS `booking_canceled`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `booking_canceled` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_specialist` int DEFAULT NULL,
  `id_work_place` int DEFAULT NULL,
  `service_id` int DEFAULT NULL,
  `booking_start_datetime` datetime DEFAULT NULL,
  `booking_end_datetime` datetime DEFAULT NULL,
  `client_full_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `client_phone_nr` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `received_through` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `received_call_date` datetime DEFAULT NULL,
  `client_transcript_conversation` text COLLATE utf8mb4_unicode_ci,
  `day_of_creation` datetime DEFAULT NULL,
  `unic_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `organisation_id` int DEFAULT NULL,
  `cancellation_time` datetime NOT NULL COMMENT 'When the booking was cancelled',
  `made_by` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Who or what system cancelled the booking',
  `google_event_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_specialist` (`id_specialist`),
  KEY `idx_work_place` (`id_work_place`),
  KEY `idx_service` (`service_id`),
  KEY `idx_client_phone` (`client_phone_nr`),
  KEY `idx_booking_start` (`booking_start_datetime`),
  KEY `idx_organisation` (`organisation_id`),
  KEY `idx_cancellation_time` (`cancellation_time`),
  KEY `idx_made_by` (`made_by`),
  KEY `idx_received_through` (`received_through`),
  KEY `idx_booking_canceled_google_event_id` (`google_event_id`)
) ENGINE=InnoDB AUTO_INCREMENT=80 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Backup table for cancelled bookings with cancellation tracking';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `booking_canceled_gcal_after_insert` AFTER INSERT ON `booking_canceled` FOR EACH ROW BEGIN
    
    IF EXISTS (
        SELECT 1 FROM google_calendar_credentials 
        WHERE specialist_id = NEW.id_specialist 
        AND status IN ('connected', 'active', 'enabled')
        LIMIT 1
    ) THEN
        INSERT INTO google_calendar_sync_queue 
        (event_type, booking_id, specialist_id, payload, status, attempts, created_at, updated_at)
        VALUES (
            'deleted',
            NEW.unic_id,
            NEW.id_specialist,
            JSON_OBJECT(
                'booking_id', NEW.unic_id,
                'client_full_name', NEW.client_full_name,
                'client_phone_nr', NEW.client_phone_nr,
                'booking_start_datetime', NEW.booking_start_datetime,
                'booking_end_datetime', NEW.booking_end_datetime,
                'id_specialist', NEW.id_specialist,
                'id_work_place', NEW.id_work_place,
                'service_id', NEW.service_id,
                'canceled_at', NOW()
            ),
            'pending',
            0,
            NOW(),
            NOW()
        );
        
        
        INSERT INTO gcal_worker_signals 
        (specialist_id, booking_id, event_type, processed, created_at)
        VALUES (
            NEW.id_specialist, 
            NEW.unic_id, 
            'deleted',
            FALSE,
            NOW()
        );
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `booking_canceled_after_insert` AFTER INSERT ON `booking_canceled` FOR EACH ROW BEGIN
  INSERT INTO booking_changes (booking_id, specialist_id, workpoint_id, change_type, booking_date)
  VALUES (NEW.unic_id, NEW.id_specialist, NEW.id_work_place, 'DELETE', DATE(NEW.booking_start_datetime));
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `booking_changes`
--

DROP TABLE IF EXISTS `booking_changes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `booking_changes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `booking_id` int NOT NULL,
  `specialist_id` int NOT NULL,
  `workpoint_id` int NOT NULL,
  `change_type` enum('INSERT','UPDATE','DELETE') COLLATE utf8mb4_unicode_ci NOT NULL,
  `change_timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `booking_date` date NOT NULL COMMENT 'Date of the booking for efficient filtering',
  PRIMARY KEY (`id`),
  KEY `idx_specialist_timestamp` (`specialist_id`,`change_timestamp`),
  KEY `idx_workpoint_timestamp` (`workpoint_id`,`change_timestamp`),
  KEY `idx_booking_date` (`booking_date`),
  KEY `idx_change_timestamp` (`change_timestamp`)
) ENGINE=InnoDB AUTO_INCREMENT=78 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `booking_event_queue`
--

DROP TABLE IF EXISTS `booking_event_queue`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `booking_event_queue` (
  `id` int NOT NULL AUTO_INCREMENT,
  `event_type` varchar(20) DEFAULT NULL,
  `specialist_id` int DEFAULT NULL,
  `working_point_id` int DEFAULT NULL,
  `booking_data` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `processed` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_processed` (`processed`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=370 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `booking_sms_queue`
--

DROP TABLE IF EXISTS `booking_sms_queue`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `booking_sms_queue` (
  `id` int NOT NULL AUTO_INCREMENT,
  `action` enum('created','updated','deleted') COLLATE utf8mb4_unicode_ci NOT NULL,
  `booking_id` int NOT NULL,
  `booking_data` json NOT NULL,
  `force_sms` enum('yes','no','default') COLLATE utf8mb4_unicode_ci DEFAULT 'default' COMMENT 'Override channel exclusion settings',
  `status` enum('pending','processing','completed','failed') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `attempts` int DEFAULT '0',
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `processed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=255 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `client_last_check`
--

DROP TABLE IF EXISTS `client_last_check`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `client_last_check` (
  `session_id` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `specialist_id` int DEFAULT NULL,
  `workpoint_id` int DEFAULT NULL,
  `last_check_timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `user_agent_hash` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Hash of user agent for session validation',
  PRIMARY KEY (`session_id`),
  KEY `idx_specialist_check` (`specialist_id`,`last_check_timestamp`),
  KEY `idx_workpoint_check` (`workpoint_id`,`last_check_timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `conversation_memory`
--

DROP TABLE IF EXISTS `conversation_memory`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `conversation_memory` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `client_full_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Full name of the client',
  `client_phone_nr` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Phone number of the client',
  `conversation` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Content of the conversation',
  `conversation_summary` text COLLATE utf8mb4_unicode_ci COMMENT 'AI-generated summary of the conversation for quick reference',
  `dat_time` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date and time of the conversation',
  `finalized_action` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Action that was finalized from the conversation',
  `lenght` int DEFAULT NULL COMMENT 'Length of the conversation (could be word count, duration, etc.)',
  `worplace_id` int DEFAULT NULL COMMENT 'ID of the workplace',
  `workplace_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Name of the workplace',
  `source` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'phone',
  `booked_phone_nr` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Phone number provided by client for booking (may differ from client_phone_nr)',
  PRIMARY KEY (`id`),
  KEY `idx_client_phone` (`client_phone_nr`),
  KEY `idx_datetime` (`dat_time`),
  KEY `idx_workplace` (`worplace_id`),
  KEY `idx_finalized_action` (`finalized_action`),
  KEY `idx_source` (`source`)
) ENGINE=InnoDB AUTO_INCREMENT=349 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores conversation memory with client details and workplace information';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `gcal_worker_signals`
--

DROP TABLE IF EXISTS `gcal_worker_signals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gcal_worker_signals` (
  `id` int NOT NULL AUTO_INCREMENT,
  `specialist_id` int NOT NULL,
  `booking_id` int DEFAULT NULL,
  `event_type` enum('created','updated','deleted') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'created',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `processed` tinyint(1) DEFAULT '0',
  `processed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_processed_created` (`processed`,`created_at`),
  KEY `idx_specialist` (`specialist_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=128 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `google_calendar_credentials`
--

DROP TABLE IF EXISTS `google_calendar_credentials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `google_calendar_credentials` (
  `id` int NOT NULL AUTO_INCREMENT,
  `specialist_id` int NOT NULL,
  `specialist_name` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `calendar_id` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `calendar_name` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `oauth_state` varchar(64) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `refresh_token` text COLLATE utf8mb4_general_ci,
  `access_token` text COLLATE utf8mb4_general_ci,
  `expires_at` datetime DEFAULT NULL,
  `status` enum('pending','active','disabled','error') COLLATE utf8mb4_general_ci DEFAULT 'pending',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `token_expires_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_google_cred_specialist` (`specialist_id`),
  KEY `idx_oauth_state` (`oauth_state`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `google_calendar_sync_queue`
--

DROP TABLE IF EXISTS `google_calendar_sync_queue`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `google_calendar_sync_queue` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `event_type` enum('created','updated','deleted') COLLATE utf8mb4_general_ci NOT NULL,
  `booking_id` int DEFAULT NULL,
  `specialist_id` int NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `status` enum('pending','processing','done','failed') COLLATE utf8mb4_general_ci DEFAULT 'pending',
  `attempts` int DEFAULT '0',
  `last_error` text COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `processed_at` datetime DEFAULT NULL,
  `error_message` text COLLATE utf8mb4_general_ci,
  PRIMARY KEY (`id`),
  KEY `idx_queue_status` (`status`),
  KEY `idx_queue_specialist` (`specialist_id`),
  KEY `idx_queue_booking` (`booking_id`),
  CONSTRAINT `google_calendar_sync_queue_chk_1` CHECK (json_valid(`payload`))
) ENGINE=InnoDB AUTO_INCREMENT=128 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ip_address`
--

DROP TABLE IF EXISTS `ip_address`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ip_address` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'IP address of the client (supports both IPv4 and IPv6)',
  `phone_number` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Phone number associated with this client',
  `vpn_private_key` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Private key for VPN client configuration',
  `date_of_insertion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date and time when record was inserted',
  `notes` text COLLATE utf8mb4_unicode_ci COMMENT 'Additional notes about this configuration',
  PRIMARY KEY (`id`),
  UNIQUE KEY `ip_address` (`ip_address`),
  KEY `phone_number` (`phone_number`),
  KEY `date_of_insertion` (`date_of_insertion`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores client VPN configurations with IP addresses and private keys';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `logs`
--

DROP TABLE IF EXISTS `logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `action_time` datetime DEFAULT NULL,
  `action_type` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `table_name` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `record_id` int DEFAULT NULL,
  `sql_query` mediumtext COLLATE utf8mb4_unicode_ci,
  `old_data` mediumtext COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `organisations`
--

DROP TABLE IF EXISTS `organisations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `organisations` (
  `unic_id` int NOT NULL AUTO_INCREMENT,
  `user` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pasword` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `position` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `oficial_company_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alias_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_head_office_address` mediumtext COLLATE utf8mb4_unicode_ci,
  `company_phone_nr` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `owner_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `owner_phone_nr` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_address` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `www_address` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`unic_id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `services`
--

DROP TABLE IF EXISTS `services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `services` (
  `unic_id` int NOT NULL AUTO_INCREMENT,
  `id_specialist` int DEFAULT NULL,
  `id_work_place` int DEFAULT NULL,
  `id_organisation` int DEFAULT NULL,
  `name_of_service` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name_of_service_in_english` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `duration` int DEFAULT NULL,
  `price_of_service` decimal(10,2) DEFAULT NULL,
  `procent_vat` decimal(5,2) DEFAULT '0.00',
  `deleted` tinyint(1) DEFAULT NULL,
  `suspended` tinyint(1) DEFAULT NULL COMMENT 'NULL or 0 = not suspended, 1 = suspended',
  `name_normalized` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name_english_normalized` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`unic_id`),
  KEY `idx_suspended` (`suspended`),
  KEY `idx_name_normalized` (`name_normalized`),
  KEY `idx_name_english_normalized` (`name_english_normalized`),
  KEY `idx_name_price_normalized` (`name_normalized`,`price_of_service`),
  KEY `idx_name_english_price_normalized` (`name_english_normalized`,`price_of_service`)
) ENGINE=InnoDB AUTO_INCREMENT=190 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `services_normalize_insert` BEFORE INSERT ON `services` FOR EACH ROW BEGIN
    SET NEW.name_normalized = normalize_service_name(NEW.name_of_service);
    SET NEW.name_english_normalized = normalize_service_name(NEW.name_of_service_in_english);
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `services_normalize_update` BEFORE UPDATE ON `services` FOR EACH ROW BEGIN
    SET NEW.name_normalized = normalize_service_name(NEW.name_of_service);
    SET NEW.name_english_normalized = normalize_service_name(NEW.name_of_service_in_english);
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `specialist_time_off`
--

DROP TABLE IF EXISTS `specialist_time_off`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `specialist_time_off` (
  `id` int NOT NULL AUTO_INCREMENT,
  `specialist_id` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_off` date NOT NULL,
  `start_time` time NOT NULL DEFAULT '00:01:00',
  `end_time` time NOT NULL DEFAULT '23:59:00',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_specialist_date` (`specialist_id`,`date_off`),
  KEY `idx_specialist_id` (`specialist_id`),
  KEY `idx_date_off` (`date_off`)
) ENGINE=InnoDB AUTO_INCREMENT=81 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `specialists`
--

DROP TABLE IF EXISTS `specialists`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `specialists` (
  `unic_id` int NOT NULL AUTO_INCREMENT,
  `organisation_id` int DEFAULT NULL,
  `user` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `speciality` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone_nr` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `h_of_email_schedule` int DEFAULT NULL,
  `m_of_email_schedule` int DEFAULT NULL,
  PRIMARY KEY (`unic_id`)
) ENGINE=InnoDB AUTO_INCREMENT=89 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `specialists_setting_and_attr`
--

DROP TABLE IF EXISTS `specialists_setting_and_attr`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `specialists_setting_and_attr` (
  `id` int NOT NULL AUTO_INCREMENT,
  `specialist_id` int NOT NULL COMMENT 'References specialists.unic_id',
  `back_color` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT '#667eea' COMMENT 'Background color for specialist in calendar displays (hex format)',
  `foreground_color` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT '#ffffff' COMMENT 'Foreground/text color for specialist in calendar displays (hex format)',
  `daily_email_enabled` tinyint(1) DEFAULT '0' COMMENT 'Boolean: 1 = enabled, 0 = disabled. If true, specialist receives daily email with program',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `specialist_can_delete_booking` tinyint(1) DEFAULT '0' COMMENT 'Boolean: 1 = can delete bookings, 0 = cannot delete bookings',
  `specialist_can_modify_booking` tinyint(1) DEFAULT '0' COMMENT 'Boolean: 1 = can modify bookings, 0 = cannot modify bookings',
  `specialist_can_add_services` tinyint(1) DEFAULT '1' COMMENT 'Boolean: 1 = can add services, 0 = cannot add services',
  `specialist_can_modify_services` tinyint(1) DEFAULT '1' COMMENT 'Boolean: 1 = can modify services, 0 = cannot modify services',
  `specialist_can_delete_services` tinyint DEFAULT NULL,
  `specialist_nr_visible_to_client` tinyint(1) DEFAULT '0' COMMENT 'Boolean: 1 = phone number visible to clients, 0 = phone number hidden from clients',
  `specialist_email_visible_to_client` tinyint(1) DEFAULT '0' COMMENT 'Boolean: 1 = email visible to clients, 0 = email hidden from clients',
  PRIMARY KEY (`id`),
  UNIQUE KEY `specialist_id` (`specialist_id`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Specialist settings and attributes for calendar display and email notifications';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `super_users`
--

DROP TABLE IF EXISTS `super_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `super_users` (
  `unic_id` int NOT NULL,
  `user` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `full_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pasword` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone_nr` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `memorable_word` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`unic_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `voice_config`
--

DROP TABLE IF EXISTS `voice_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `voice_config` (
  `id` int NOT NULL AUTO_INCREMENT,
  `workpoint_id` int NOT NULL,
  `tts_model` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'openai',
  `tts_access_link` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tts_secret_key` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stt_model` enum('WHISPER_TINY','WHISPER_BASE','NULL') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'WHISPER_BASE',
  `language` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'lt',
  `welcome_message` text COLLATE utf8mb4_unicode_ci,
  `answer_after_rings` int NOT NULL COMMENT 'After how many rings will it answer . -1 for not answering at all \r\n\r\n',
  `voice_settings` json DEFAULT NULL,
  `vad_threshold` decimal(3,2) DEFAULT '0.50',
  `silence_timeout` int DEFAULT '500',
  `audio_format` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT '16kHz_16bit_mono',
  `buffer_size` int DEFAULT '1000',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_workpoint` (`workpoint_id`),
  KEY `idx_workpoint_active` (`workpoint_id`,`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `webhook_logs`
--

DROP TABLE IF EXISTS `webhook_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `webhook_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `webhook_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Name/identifier of the webhook endpoint',
  `request_method` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'HTTP method (GET, POST, etc.)',
  `request_url` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Full request URL',
  `request_headers` text COLLATE utf8mb4_unicode_ci COMMENT 'JSON encoded request headers',
  `request_body` text COLLATE utf8mb4_unicode_ci COMMENT 'Request body content (for POST requests)',
  `request_params` text COLLATE utf8mb4_unicode_ci COMMENT 'JSON encoded request parameters',
  `client_ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Client IP address',
  `user_agent` text COLLATE utf8mb4_unicode_ci COMMENT 'User agent string',
  `response_status_code` int DEFAULT NULL COMMENT 'HTTP response status code',
  `response_body` text COLLATE utf8mb4_unicode_ci COMMENT 'Response body content',
  `response_headers` text COLLATE utf8mb4_unicode_ci COMMENT 'JSON encoded response headers',
  `processing_time_ms` int DEFAULT NULL COMMENT 'Processing time in milliseconds',
  `error_message` text COLLATE utf8mb4_unicode_ci COMMENT 'Error message if any',
  `error_trace` text COLLATE utf8mb4_unicode_ci COMMENT 'Full error stack trace',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Timestamp when the webhook call was received',
  `processed_at` timestamp NULL DEFAULT NULL COMMENT 'Timestamp when processing completed',
  `is_successful` tinyint(1) DEFAULT NULL COMMENT 'Whether the webhook call was successful (1) or failed (0)',
  `related_booking_id` int DEFAULT NULL COMMENT 'Related booking ID if applicable',
  `related_specialist_id` int DEFAULT NULL COMMENT 'Related specialist ID if applicable',
  `related_organisation_id` int DEFAULT NULL COMMENT 'Related organisation ID if applicable',
  `related_working_point_id` int DEFAULT NULL COMMENT 'Related working point ID if applicable',
  `additional_data` text COLLATE utf8mb4_unicode_ci COMMENT 'JSON encoded additional data specific to the webhook',
  PRIMARY KEY (`id`),
  KEY `idx_webhook_name` (`webhook_name`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_is_successful` (`is_successful`),
  KEY `idx_client_ip` (`client_ip`),
  KEY `idx_related_booking_id` (`related_booking_id`),
  KEY `idx_related_specialist_id` (`related_specialist_id`),
  KEY `idx_related_organisation_id` (`related_organisation_id`),
  KEY `idx_related_working_point_id` (`related_working_point_id`),
  KEY `idx_webhook_logs_composite` (`webhook_name`,`created_at`,`is_successful`),
  KEY `idx_webhook_logs_date_range` (`created_at`,`is_successful`)
) ENGINE=InnoDB AUTO_INCREMENT=38429 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Logs of all webhook calls to the system';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `working_points`
--

DROP TABLE IF EXISTS `working_points`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `working_points` (
  `unic_id` int NOT NULL AUTO_INCREMENT,
  `name_of_the_place` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description_of_the_place` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Description of the activity of the place Ex: Salon de cosmetica si infrumusetare" or "Restaurant de lux"',
  `address` mediumtext COLLATE utf8mb4_unicode_ci,
  `landmark` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `directions` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `user` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lead_person_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Workpoint SUPERVISOR full name\r\n(printed out to the clients for contact whne the bot conversation fails)',
  `lead_person_phone_nr` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'the phone number of the Worpoint Supervisor ',
  `workplace_phone_nr` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'OPTIONAL it could be a landline phone nr',
  `booking_phone_nr` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'VERY IMPORTANT (it shall be unique) and is the number where the bot respond and is used as BUSSINESS IDENTIFYER',
  `booking_sms_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'the phone number used for SMS, (it could be the same number as the booking_phone_nr , or our own sms server phone number , build \r\nfor the country)\r\n',
  `ip_address` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `organisation_id` int DEFAULT NULL,
  `country` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `language` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `curency` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'EUR',
  `we_handling` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'specialis' COMMENT 'Describe what we handling (specialist, table, bay, etc)	',
  `specialist_relevance` enum('strong','medium','low','') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium' COMMENT 'strong/medium/low  tell to the Ai how  relevant is then name of the specialist in conversation (specialist_relevance=low when we handle bays or ramps)',
  PRIMARY KEY (`unic_id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `working_program`
--

DROP TABLE IF EXISTS `working_program`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `working_program` (
  `unic_id` int NOT NULL AUTO_INCREMENT,
  `specialist_id` int DEFAULT NULL,
  `working_place_id` int DEFAULT NULL,
  `organisation_id` int NOT NULL,
  `day_of_week` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shift1_start` time DEFAULT NULL,
  `shift1_end` time DEFAULT NULL,
  `shift2_start` time DEFAULT NULL,
  `shift2_end` time DEFAULT NULL,
  `shift3_start` time DEFAULT NULL,
  `shift3_end` time DEFAULT NULL,
  PRIMARY KEY (`unic_id`)
) ENGINE=InnoDB AUTO_INCREMENT=848 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `workingpoint_settings_and_attr`
--

DROP TABLE IF EXISTS `workingpoint_settings_and_attr`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `workingpoint_settings_and_attr` (
  `id` int NOT NULL AUTO_INCREMENT,
  `working_point_id` int NOT NULL,
  `setting_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text COLLATE utf8mb4_unicode_ci,
  `excluded_channels` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'SMS',
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_workpoint_setting` (`working_point_id`,`setting_key`),
  KEY `idx_working_point_id` (`working_point_id`),
  KEY `idx_setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `workpoint_social_media`
--

DROP TABLE IF EXISTS `workpoint_social_media`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `workpoint_social_media` (
  `id` int NOT NULL AUTO_INCREMENT,
  `workpoint_id` int NOT NULL COMMENT 'References working_points.unic_id',
  `platform` enum('whatsapp_business','facebook_messenger') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Social media platform',
  `is_active` tinyint(1) DEFAULT '0' COMMENT 'Whether this platform is active for this workpoint',
  `whatsapp_phone_number` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'WhatsApp Business phone number',
  `whatsapp_phone_number_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'WhatsApp Business Phone Number ID (separate from phone number)',
  `whatsapp_business_account_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Meta WhatsApp Business Account ID',
  `whatsapp_access_token` text COLLATE utf8mb4_unicode_ci COMMENT 'WhatsApp Business API access token',
  `facebook_page_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Facebook Page ID',
  `facebook_page_access_token` text COLLATE utf8mb4_unicode_ci COMMENT 'Facebook Page Access Token',
  `facebook_app_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Facebook App ID',
  `facebook_app_secret` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Facebook App Secret',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_test_at` timestamp NULL DEFAULT NULL COMMENT 'Last time connection was tested',
  `last_test_status` enum('success','failed','not_tested') COLLATE utf8mb4_unicode_ci DEFAULT 'not_tested' COMMENT 'Status of last connection test',
  `last_test_message` text COLLATE utf8mb4_unicode_ci COMMENT 'Message from last connection test',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_workpoint_platform` (`workpoint_id`,`platform`),
  KEY `idx_workpoint_id` (`workpoint_id`),
  KEY `idx_platform` (`platform`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='WhatsApp and Facebook Messenger credentials for each workpoint';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping routines for database 'nuuitasi_calendar4'
--
/*!50003 DROP FUNCTION IF EXISTS `normalize_service_name` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `normalize_service_name`(input_text VARCHAR(255)) RETURNS varchar(255) CHARSET utf8mb4
    NO SQL
    DETERMINISTIC
BEGIN
    DECLARE normalized VARCHAR(255);

    IF input_text IS NULL THEN
        RETURN NULL;
    END IF;

    SET normalized = input_text;

    
    SET normalized = REPLACE(normalized, ' ', '');
    SET normalized = REPLACE(normalized, '.', '');
    SET normalized = REPLACE(normalized, ',', '');
    SET normalized = REPLACE(normalized, '-', '');
    SET normalized = REPLACE(normalized, '–', '');
    SET normalized = REPLACE(normalized, '—', '');
    SET normalized = REPLACE(normalized, '_', '');
    SET normalized = REPLACE(normalized, '/', '');
    SET normalized = REPLACE(normalized, '\\', '');
    SET normalized = REPLACE(normalized, '(', '');
    SET normalized = REPLACE(normalized, ')', '');
    SET normalized = REPLACE(normalized, '[', '');
    SET normalized = REPLACE(normalized, ']', '');
    SET normalized = REPLACE(normalized, '{', '');
    SET normalized = REPLACE(normalized, '}', '');
    SET normalized = REPLACE(normalized, ':', '');
    SET normalized = REPLACE(normalized, ';', '');
    SET normalized = REPLACE(normalized, '!', '');
    SET normalized = REPLACE(normalized, '?', '');
    SET normalized = REPLACE(normalized, '\'', '');
    SET normalized = REPLACE(normalized, '"', '');
    SET normalized = REPLACE(normalized, '`', '');
    SET normalized = REPLACE(normalized, '´', '');
    SET normalized = REPLACE(normalized, '*', '');
    SET normalized = REPLACE(normalized, '&', '');
    SET normalized = REPLACE(normalized, '+', '');
    SET normalized = REPLACE(normalized, '=', '');
    SET normalized = REPLACE(normalized, '|', '');
    SET normalized = REPLACE(normalized, '@', '');
    SET normalized = REPLACE(normalized, '#', '');
    SET normalized = REPLACE(normalized, '$', '');
    SET normalized = REPLACE(normalized, '%', '');
    SET normalized = REPLACE(normalized, '^', '');
    SET normalized = REPLACE(normalized, '~', '');
    SET normalized = REPLACE(normalized, '<', '');
    SET normalized = REPLACE(normalized, '>', '');

    
    SET normalized = REPLACE(normalized, 'À', 'A');
    SET normalized = REPLACE(normalized, 'Á', 'A');
    SET normalized = REPLACE(normalized, 'Â', 'A');
    SET normalized = REPLACE(normalized, 'Ã', 'A');
    SET normalized = REPLACE(normalized, 'Ä', 'A');
    SET normalized = REPLACE(normalized, 'Å', 'A');
    SET normalized = REPLACE(normalized, 'Ā', 'A');
    SET normalized = REPLACE(normalized, 'Ă', 'A');
    SET normalized = REPLACE(normalized, 'Ą', 'A');

    SET normalized = REPLACE(normalized, 'È', 'E');
    SET normalized = REPLACE(normalized, 'É', 'E');
    SET normalized = REPLACE(normalized, 'Ê', 'E');
    SET normalized = REPLACE(normalized, 'Ë', 'E');
    SET normalized = REPLACE(normalized, 'Ē', 'E');
    SET normalized = REPLACE(normalized, 'Ė', 'E');
    SET normalized = REPLACE(normalized, 'Ę', 'E');
    SET normalized = REPLACE(normalized, 'Ě', 'E');

    SET normalized = REPLACE(normalized, 'Ì', 'I');
    SET normalized = REPLACE(normalized, 'Í', 'I');
    SET normalized = REPLACE(normalized, 'Î', 'I');
    SET normalized = REPLACE(normalized, 'Ï', 'I');
    SET normalized = REPLACE(normalized, 'Ī', 'I');
    SET normalized = REPLACE(normalized, 'Į', 'I');

    SET normalized = REPLACE(normalized, 'Ò', 'O');
    SET normalized = REPLACE(normalized, 'Ó', 'O');
    SET normalized = REPLACE(normalized, 'Ô', 'O');
    SET normalized = REPLACE(normalized, 'Õ', 'O');
    SET normalized = REPLACE(normalized, 'Ö', 'O');
    SET normalized = REPLACE(normalized, 'Ø', 'O');
    SET normalized = REPLACE(normalized, 'Ō', 'O');
    SET normalized = REPLACE(normalized, 'Ő', 'O');

    SET normalized = REPLACE(normalized, 'Ù', 'U');
    SET normalized = REPLACE(normalized, 'Ú', 'U');
    SET normalized = REPLACE(normalized, 'Û', 'U');
    SET normalized = REPLACE(normalized, 'Ü', 'U');
    SET normalized = REPLACE(normalized, 'Ū', 'U');
    SET normalized = REPLACE(normalized, 'Ų', 'U');
    SET normalized = REPLACE(normalized, 'Ű', 'U');

    SET normalized = REPLACE(normalized, 'Ý', 'Y');
    SET normalized = REPLACE(normalized, 'Ÿ', 'Y');

    SET normalized = REPLACE(normalized, 'Ć', 'C');
    SET normalized = REPLACE(normalized, 'Č', 'C');
    SET normalized = REPLACE(normalized, 'Ç', 'C');

    SET normalized = REPLACE(normalized, 'Ď', 'D');
    SET normalized = REPLACE(normalized, 'Đ', 'D');
    SET normalized = REPLACE(normalized, 'Ð', 'D');

    SET normalized = REPLACE(normalized, 'Ģ', 'G');
    SET normalized = REPLACE(normalized, 'Ğ', 'G');

    SET normalized = REPLACE(normalized, 'Ķ', 'K');

    SET normalized = REPLACE(normalized, 'Ł', 'L');
    SET normalized = REPLACE(normalized, 'Ļ', 'L');

    SET normalized = REPLACE(normalized, 'Ń', 'N');
    SET normalized = REPLACE(normalized, 'Ň', 'N');
    SET normalized = REPLACE(normalized, 'Ņ', 'N');
    SET normalized = REPLACE(normalized, 'Ñ', 'N');

    SET normalized = REPLACE(normalized, 'Ř', 'R');

    SET normalized = REPLACE(normalized, 'Ś', 'S');
    SET normalized = REPLACE(normalized, 'Š', 'S');
    SET normalized = REPLACE(normalized, 'Ş', 'S');
    SET normalized = REPLACE(normalized, 'Ș', 'S');
    SET normalized = REPLACE(normalized, 'ẞ', 'SS');

    SET normalized = REPLACE(normalized, 'Ť', 'T');
    SET normalized = REPLACE(normalized, 'Ţ', 'T');
    SET normalized = REPLACE(normalized, 'Ț', 'T');
    SET normalized = REPLACE(normalized, 'Þ', 'TH');

    SET normalized = REPLACE(normalized, 'Ź', 'Z');
    SET normalized = REPLACE(normalized, 'Ż', 'Z');
    SET normalized = REPLACE(normalized, 'Ž', 'Z');

    SET normalized = REPLACE(normalized, 'Æ', 'AE');
    SET normalized = REPLACE(normalized, 'Œ', 'OE');
    SET normalized = REPLACE(normalized, 'ß', 'ss');

    SET normalized = REPLACE(normalized, 'I', 'I');

    
    SET normalized = LOWER(normalized);

    
    SET normalized = REPLACE(normalized, 'à', 'a');
    SET normalized = REPLACE(normalized, 'á', 'a');
    SET normalized = REPLACE(normalized, 'â', 'a');
    SET normalized = REPLACE(normalized, 'ã', 'a');
    SET normalized = REPLACE(normalized, 'ä', 'a');
    SET normalized = REPLACE(normalized, 'å', 'a');
    SET normalized = REPLACE(normalized, 'ā', 'a');
    SET normalized = REPLACE(normalized, 'ă', 'a');
    SET normalized = REPLACE(normalized, 'ą', 'a');

    SET normalized = REPLACE(normalized, 'è', 'e');
    SET normalized = REPLACE(normalized, 'é', 'e');
    SET normalized = REPLACE(normalized, 'ê', 'e');
    SET normalized = REPLACE(normalized, 'ë', 'e');
    SET normalized = REPLACE(normalized, 'ē', 'e');
    SET normalized = REPLACE(normalized, 'ė', 'e');
    SET normalized = REPLACE(normalized, 'ę', 'e');
    SET normalized = REPLACE(normalized, 'ě', 'e');

    SET normalized = REPLACE(normalized, 'ì', 'i');
    SET normalized = REPLACE(normalized, 'í', 'i');
    SET normalized = REPLACE(normalized, 'î', 'i');
    SET normalized = REPLACE(normalized, 'ï', 'i');
    SET normalized = REPLACE(normalized, 'ī', 'i');
    SET normalized = REPLACE(normalized, 'į', 'i');
    SET normalized = REPLACE(normalized, 'ı', 'i');

    SET normalized = REPLACE(normalized, 'ò', 'o');
    SET normalized = REPLACE(normalized, 'ó', 'o');
    SET normalized = REPLACE(normalized, 'ô', 'o');
    SET normalized = REPLACE(normalized, 'õ', 'o');
    SET normalized = REPLACE(normalized, 'ö', 'o');
    SET normalized = REPLACE(normalized, 'ø', 'o');
    SET normalized = REPLACE(normalized, 'ō', 'o');
    SET normalized = REPLACE(normalized, 'ő', 'o');

    SET normalized = REPLACE(normalized, 'ù', 'u');
    SET normalized = REPLACE(normalized, 'ú', 'u');
    SET normalized = REPLACE(normalized, 'û', 'u');
    SET normalized = REPLACE(normalized, 'ü', 'u');
    SET normalized = REPLACE(normalized, 'ū', 'u');
    SET normalized = REPLACE(normalized, 'ų', 'u');
    SET normalized = REPLACE(normalized, 'ű', 'u');

    SET normalized = REPLACE(normalized, 'ý', 'y');
    SET normalized = REPLACE(normalized, 'ÿ', 'y');

    SET normalized = REPLACE(normalized, 'ć', 'c');
    SET normalized = REPLACE(normalized, 'č', 'c');
    SET normalized = REPLACE(normalized, 'ç', 'c');

    SET normalized = REPLACE(normalized, 'ď', 'd');
    SET normalized = REPLACE(normalized, 'đ', 'd');
    SET normalized = REPLACE(normalized, 'ð', 'd');

    SET normalized = REPLACE(normalized, 'ģ', 'g');
    SET normalized = REPLACE(normalized, 'ğ', 'g');

    SET normalized = REPLACE(normalized, 'ķ', 'k');

    SET normalized = REPLACE(normalized, 'ł', 'l');
    SET normalized = REPLACE(normalized, 'ļ', 'l');

    SET normalized = REPLACE(normalized, 'ń', 'n');
    SET normalized = REPLACE(normalized, 'ň', 'n');
    SET normalized = REPLACE(normalized, 'ņ', 'n');
    SET normalized = REPLACE(normalized, 'ñ', 'n');

    SET normalized = REPLACE(normalized, 'ř', 'r');

    SET normalized = REPLACE(normalized, 'ś', 's');
    SET normalized = REPLACE(normalized, 'š', 's');
    SET normalized = REPLACE(normalized, 'ş', 's');
    SET normalized = REPLACE(normalized, 'ș', 's');

    SET normalized = REPLACE(normalized, 'ť', 't');
    SET normalized = REPLACE(normalized, 'ţ', 't');
    SET normalized = REPLACE(normalized, 'ț', 't');
    SET normalized = REPLACE(normalized, 'þ', 'th');

    SET normalized = REPLACE(normalized, 'ź', 'z');
    SET normalized = REPLACE(normalized, 'ż', 'z');
    SET normalized = REPLACE(normalized, 'ž', 'z');

    SET normalized = REPLACE(normalized, 'æ', 'ae');
    SET normalized = REPLACE(normalized, 'œ', 'oe');

    RETURN normalized;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-12-31 13:22:02
