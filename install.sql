-- =====================================================================
--  工作留痕记录工具 - 数据库结构
--  说明：本文件只建表、不建库、不 USE。
--        宝塔 / 本机都请先【手工创建】目标库（utf8mb4），再导入本文件。
--        可重复导入（幂等）。
-- =====================================================================

SET NAMES utf8mb4;

-- ------------------------- 账号 -------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username`      VARCHAR(50)  NOT NULL COMMENT '登录名',
  `password_hash` VARCHAR(255) NOT NULL COMMENT 'password_hash 结果',
  `display_name`  VARCHAR(50)  NOT NULL DEFAULT '' COMMENT '显示名',
  `role`          ENUM('admin','user') NOT NULL DEFAULT 'admin',
  `status`        TINYINT(1)   NOT NULL DEFAULT 1 COMMENT '1=启用 0=停用',
  `created_at`    DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='登录账号';

-- ------------------------- 留痕记录 -------------------------
CREATE TABLE IF NOT EXISTS `records` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`          INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '记录人',
  `happened_at`      DATETIME     NOT NULL COMMENT '工作发生时间',
  `title`            VARCHAR(120) NOT NULL DEFAULT '' COMMENT '工作标题（可空）',
  `content`          TEXT         NOT NULL COMMENT '工作内容',
  `related_people`   VARCHAR(255) NOT NULL DEFAULT '' COMMENT '相关人员',
  `customer`         VARCHAR(120) NOT NULL DEFAULT '' COMMENT '涉及客户',
  `location`         VARCHAR(120) NOT NULL DEFAULT '' COMMENT '地点',
  `share_token`      CHAR(32)     DEFAULT NULL COMMENT '公开分享令牌，NULL=未分享',
  `share_created_at` DATETIME     DEFAULT NULL,
  `created_at`       DATETIME     NOT NULL,
  `updated_at`       DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_happened` (`happened_at`),
  KEY `idx_share` (`share_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='工作留痕记录';

-- ------------------------- 附件 -------------------------
CREATE TABLE IF NOT EXISTS `attachments` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `record_id`   INT UNSIGNED  NOT NULL,
  `orig_name`   VARCHAR(255)  NOT NULL COMMENT '原始文件名',
  `stored_name` VARCHAR(255)  NOT NULL COMMENT '磁盘相对路径 uploads/2026/10/xxx.ext',
  `mime`        VARCHAR(120)  NOT NULL DEFAULT '',
  `ext`         VARCHAR(20)   NOT NULL DEFAULT '',
  `size`        INT UNSIGNED  NOT NULL DEFAULT 0,
  `kind`        ENUM('image','video','audio','doc') NOT NULL DEFAULT 'doc',
  `created_at`  DATETIME      NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_record` (`record_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='记录附件';

-- ------------------------- 初始账号 -------------------------
-- 默认账号：admin  密码：admin123   （首次登录后请在「账号设置」里立即修改）
INSERT IGNORE INTO `users` (`id`, `username`, `password_hash`, `display_name`, `role`, `status`, `created_at`)
VALUES (1, 'admin', '$2y$10$FundWiJcJ.rG56v4HGJVZO1e8TE3.NADnNOPnQ9hVzw8P/v/j/e1e', '管理员', 'admin', 1, NOW());
