CREATE TABLE IF NOT EXISTS `image_master` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `image` VARCHAR(255) NOT NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'Active',
  `created_by` VARCHAR(100) DEFAULT NULL,
  `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ticket_settings` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `important_info` TEXT DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `ticket_settings` (`id`, `important_info`)
SELECT 1, '<li>Please carry a valid photo ID.</li><li>Report at the airport at least 2 hours before departure.</li>'
WHERE NOT EXISTS (SELECT 1 FROM `ticket_settings` WHERE id=1);

CREATE TABLE IF NOT EXISTS `saved_tickets` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `pnr` VARCHAR(50) DEFAULT NULL,
  `booking_date` VARCHAR(50) DEFAULT NULL,
  `pax_count` INT(11) DEFAULT NULL,
  `base_fare` DECIMAL(10,2) DEFAULT NULL,
  `tax` DECIMAL(10,2) DEFAULT NULL,
  `total_fare` DECIMAL(10,2) DEFAULT NULL,
  `passenger_names` TEXT DEFAULT NULL,
  `passengers_json` LONGTEXT DEFAULT NULL,
  `sector` VARCHAR(255) DEFAULT NULL,
  `airline` VARCHAR(255) DEFAULT NULL,
  `flight_html` LONGTEXT DEFAULT NULL,
  `return_flight_html` LONGTEXT DEFAULT NULL,
  `departure_date` VARCHAR(50) DEFAULT NULL,
  `arrival_date` VARCHAR(50) DEFAULT NULL,
  `pdf_path` VARCHAR(255) DEFAULT NULL,
  `webcheckin_status` VARCHAR(50) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
