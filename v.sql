SET NAMES utf8mb4;
SET time_zone = '+08:00';

CREATE TABLE IF NOT EXISTS `pay_order` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `close_date` bigint unsigned NOT NULL DEFAULT 0,
  `create_date` bigint unsigned NOT NULL,
  `is_auto` tinyint unsigned NOT NULL DEFAULT 1,
  `notify_url` varchar(2048) NOT NULL DEFAULT '',
  `notify_attempts` tinyint unsigned NOT NULL DEFAULT 0,
  `next_notify_date` bigint unsigned NOT NULL DEFAULT 0,
  `last_notify_error` varchar(255) NOT NULL DEFAULT '',
  `notify_event_id` varchar(64) DEFAULT NULL,
  `order_id` varchar(64) NOT NULL,
  `param` varchar(500) NOT NULL DEFAULT '',
  `pay_date` bigint unsigned NOT NULL DEFAULT 0,
  `pay_id` varchar(100) NOT NULL,
  `pay_url` varchar(2048) NOT NULL DEFAULT '',
  `price` decimal(12,2) NOT NULL,
  `really_price` decimal(12,2) NOT NULL,
  `return_url` varchar(2048) NOT NULL DEFAULT '',
  `state` tinyint NOT NULL DEFAULT 0,
  `type` tinyint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_pay_order_order_id` (`order_id`),
  UNIQUE KEY `uk_pay_order_pay_id` (`pay_id`),
  UNIQUE KEY `uk_pay_order_notify_event_id` (`notify_event_id`),
  KEY `idx_pay_order_match` (`state`, `type`, `really_price`),
  KEY `idx_pay_order_expire` (`state`, `create_date`),
  KEY `idx_pay_order_notify` (`state`, `next_notify_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pay_qrcode` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pay_url` varchar(2048) NOT NULL,
  `price` decimal(12,2) NOT NULL,
  `type` tinyint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pay_qrcode_match` (`type`, `price`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `setting` (
  `vkey` varchar(64) NOT NULL,
  `vvalue` text DEFAULT NULL,
  PRIMARY KEY (`vkey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `setting` (`vkey`, `vvalue`) VALUES
('user', 'admin'),
('pass', ''),
('notifyUrl', ''),
('returnUrl', ''),
('key', ''),
('lastheart', '0'),
('lastpay', '0'),
('jkstate', '0'),
('close', '5'),
('payQf', '1'),
('wxpay', ''),
('zfbpay', ''),
('startTime', '');

CREATE TABLE IF NOT EXISTS `tmp_price` (
  `price` varchar(64) NOT NULL,
  `oid` varchar(64) NOT NULL,
  PRIMARY KEY (`price`),
  UNIQUE KEY `uk_tmp_price_oid` (`oid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `push_event` (
  `event_hash` char(64) NOT NULL,
  `created_at` bigint unsigned NOT NULL,
  PRIMARY KEY (`event_hash`),
  KEY `idx_push_event_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=ascii;
