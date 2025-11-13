-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- 主机： 118.145.177.169:3306
-- 生成日期： 2025-11-12 17:59:16
-- 服务器版本： 11.8.3-MariaDB-ubu2404
-- PHP 版本： 8.3.27

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- 数据库： `inkako_fund`
--
CREATE DATABASE IF NOT EXISTS `inkako_fund` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `inkako_fund`;

-- --------------------------------------------------------

--
-- 表的结构 `admin_attachments`
--

DROP TABLE IF EXISTS `admin_attachments`;
CREATE TABLE `admin_attachments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `site_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT '所属站点ID',
  `name` varchar(255) NOT NULL COMMENT '附件名称',
  `description` text DEFAULT NULL COMMENT '附件描述',
  `category` varchar(50) DEFAULT NULL COMMENT '附件分类',
  `tags` varchar(255) DEFAULT NULL COMMENT '标签（逗号分隔）',
  `original_filename` varchar(255) NOT NULL COMMENT '原始文件名',
  `filename` varchar(255) NOT NULL COMMENT '存储文件名',
  `file_path` varchar(255) NOT NULL COMMENT '文件相对路径',
  `file_url` varchar(255) DEFAULT NULL COMMENT '文件访问URL',
  `content_type` varchar(100) NOT NULL COMMENT '文件MIME类型',
  `file_size` bigint(20) UNSIGNED NOT NULL COMMENT '文件大小（字节）',
  `storage_driver` varchar(20) NOT NULL DEFAULT 'local' COMMENT '存储驱动：local/s3',
  `file_hash` varchar(64) DEFAULT NULL COMMENT '文件MD5哈希值',
  `related_type` varchar(50) DEFAULT NULL COMMENT '关联类型（如：content、page、banner等）',
  `related_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT '关联ID',
  `user_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT '上传用户ID',
  `username` varchar(50) DEFAULT NULL COMMENT '上传用户名',
  `status` tinyint(4) NOT NULL DEFAULT 1 COMMENT '状态：0=禁用，1=启用',
  `sort` int(11) NOT NULL DEFAULT 0 COMMENT '排序',
  `ip_address` varchar(50) DEFAULT NULL COMMENT '上传IP地址',
  `user_agent` varchar(255) DEFAULT NULL COMMENT 'User Agent',
  `download_count` int(11) NOT NULL DEFAULT 0 COMMENT '下载次数',
  `last_downloaded_at` timestamp NULL DEFAULT NULL COMMENT '最后下载时间',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='管理员附件表';

-- --------------------------------------------------------

--
-- 表的结构 `admin_configs`
--

DROP TABLE IF EXISTS `admin_configs`;
CREATE TABLE `admin_configs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `site_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT '所属站点ID',
  `group` varchar(50) NOT NULL DEFAULT 'system' COMMENT '配置分组',
  `key` varchar(100) NOT NULL COMMENT '配置键',
  `value` text DEFAULT NULL COMMENT '配置值',
  `type` varchar(20) NOT NULL DEFAULT 'string' COMMENT '类型：string,int,bool,json',
  `description` varchar(255) DEFAULT NULL COMMENT '描述',
  `sort` int(11) NOT NULL DEFAULT 0 COMMENT '排序',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统配置表';

-- --------------------------------------------------------

--
-- 表的结构 `admin_menus`
--

DROP TABLE IF EXISTS `admin_menus`;
CREATE TABLE `admin_menus` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `site_id` bigint(20) UNSIGNED NOT NULL DEFAULT 0 COMMENT '站点ID，0=全局菜单',
  `parent_id` bigint(20) UNSIGNED NOT NULL DEFAULT 0 COMMENT '父级ID，0=顶级菜单',
  `name` varchar(100) NOT NULL COMMENT '菜单名称（唯一标识）',
  `title` varchar(100) NOT NULL COMMENT '菜单标题（显示文本）',
  `icon` varchar(50) DEFAULT NULL COMMENT '菜单图标（CSS类名或图标路径）',
  `path` varchar(255) DEFAULT NULL COMMENT '路由路径',
  `component` varchar(255) DEFAULT NULL COMMENT '组件路径（前后端分离时使用）',
  `redirect` varchar(255) DEFAULT NULL COMMENT '重定向路径',
  `type` varchar(20) NOT NULL DEFAULT 'menu' COMMENT '类型：menu=菜单，link=外链，group=分组，divider=分割线',
  `target` varchar(20) NOT NULL DEFAULT '_self' COMMENT '打开方式：_self=当前窗口，_blank=新窗口',
  `badge` varchar(50) DEFAULT NULL COMMENT '徽章文本',
  `badge_type` varchar(20) DEFAULT NULL COMMENT '徽章类型：primary,success,warning,danger,info',
  `permission` varchar(100) DEFAULT NULL COMMENT '权限标识（关联权限系统）',
  `visible` tinyint(4) NOT NULL DEFAULT 1 COMMENT '是否可见：0=隐藏，1=显示',
  `status` tinyint(4) NOT NULL DEFAULT 1 COMMENT '状态：0=禁用，1=启用',
  `sort` int(11) NOT NULL DEFAULT 0 COMMENT '排序（数字越小越靠前）',
  `cache` tinyint(4) NOT NULL DEFAULT 1 COMMENT '是否缓存：0=不缓存，1=缓存',
  `config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '扩展配置（JSON格式）：{keep_alive, hidden_breadcrumb, etc}' CHECK (json_valid(`config`)),
  `remark` text DEFAULT NULL COMMENT '备注说明',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- 表的结构 `admin_permissions`
--

DROP TABLE IF EXISTS `admin_permissions`;
CREATE TABLE `admin_permissions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `site_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT '所属站点ID',
  `parent_id` bigint(20) UNSIGNED NOT NULL DEFAULT 0 COMMENT '父级ID',
  `name` varchar(50) NOT NULL COMMENT '权限名称',
  `slug` varchar(100) NOT NULL COMMENT '权限标识',
  `type` varchar(20) NOT NULL DEFAULT 'menu' COMMENT '类型：menu=菜单，button=按钮',
  `icon` varchar(50) DEFAULT NULL COMMENT '图标',
  `path` varchar(255) DEFAULT NULL COMMENT '路由路径',
  `component` varchar(255) DEFAULT NULL COMMENT '组件路径',
  `description` varchar(255) DEFAULT NULL COMMENT '描述',
  `status` tinyint(4) NOT NULL DEFAULT 1 COMMENT '状态：0=禁用，1=启用',
  `sort` int(11) NOT NULL DEFAULT 0 COMMENT '排序',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='权限表';

-- --------------------------------------------------------

--
-- 表的结构 `admin_permission_role`
--

DROP TABLE IF EXISTS `admin_permission_role`;
CREATE TABLE `admin_permission_role` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `permission_id` bigint(20) UNSIGNED NOT NULL COMMENT '权限ID',
  `role_id` bigint(20) UNSIGNED NOT NULL COMMENT '角色ID',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='角色权限关联表';

-- --------------------------------------------------------

--
-- 表的结构 `admin_roles`
--

DROP TABLE IF EXISTS `admin_roles`;
CREATE TABLE `admin_roles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `site_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT '所属站点ID',
  `name` varchar(50) NOT NULL COMMENT '角色名称',
  `slug` varchar(50) NOT NULL COMMENT '角色标识',
  `description` varchar(255) DEFAULT NULL COMMENT '角色描述',
  `status` tinyint(4) NOT NULL DEFAULT 1 COMMENT '状态：0=禁用，1=启用',
  `sort` int(11) NOT NULL DEFAULT 0 COMMENT '排序',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='角色表';

-- --------------------------------------------------------

--
-- 表的结构 `admin_role_user`
--

DROP TABLE IF EXISTS `admin_role_user`;
CREATE TABLE `admin_role_user` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `role_id` bigint(20) UNSIGNED NOT NULL COMMENT '角色ID',
  `user_id` bigint(20) UNSIGNED NOT NULL COMMENT '用户ID',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户角色关联表';

-- --------------------------------------------------------

--
-- 表的结构 `admin_upload_files`
--

DROP TABLE IF EXISTS `admin_upload_files`;
CREATE TABLE `admin_upload_files` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `site_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT '所属站点ID',
  `upload_token` varchar(64) NOT NULL COMMENT '上传令牌',
  `user_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT '上传用户ID',
  `username` varchar(50) DEFAULT NULL COMMENT '上传用户名',
  `original_filename` varchar(255) NOT NULL COMMENT '原始文件名',
  `filename` varchar(255) NOT NULL COMMENT '存储文件名',
  `file_path` varchar(255) NOT NULL COMMENT '文件相对路径',
  `file_url` varchar(255) DEFAULT NULL COMMENT '文件访问URL',
  `content_type` varchar(100) NOT NULL COMMENT '文件MIME类型',
  `file_size` bigint(20) UNSIGNED NOT NULL COMMENT '文件大小（字节）',
  `storage_driver` varchar(20) NOT NULL DEFAULT 'local' COMMENT '存储驱动：local/s3',
  `status` tinyint(4) NOT NULL DEFAULT 0 COMMENT '状态：0=待上传，1=已上传，2=违规，3=已删除',
  `violation_reason` text DEFAULT NULL COMMENT '违规原因',
  `token_expire_at` timestamp NOT NULL COMMENT '令牌过期时间',
  `uploaded_at` timestamp NULL DEFAULT NULL COMMENT '实际上传时间',
  `checked_at` timestamp NULL DEFAULT NULL COMMENT '最后检查时间',
  `check_status` tinyint(4) NOT NULL DEFAULT 0 COMMENT '检查状态：0=未检查，1=通过，2=违规',
  `check_result` text DEFAULT NULL COMMENT '检查结果（JSON）',
  `ip_address` varchar(50) DEFAULT NULL COMMENT '上传IP地址',
  `user_agent` varchar(255) DEFAULT NULL COMMENT 'User Agent',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='文件上传管理表';

-- --------------------------------------------------------

--
-- 表的结构 `admin_users`
--

DROP TABLE IF EXISTS `admin_users`;
CREATE TABLE `admin_users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `site_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT '所属站点ID',
  `username` varchar(50) NOT NULL COMMENT '用户名',
  `password` varchar(255) NOT NULL COMMENT '密码',
  `email` varchar(100) DEFAULT NULL COMMENT '邮箱',
  `mobile` varchar(20) DEFAULT NULL COMMENT '手机号',
  `avatar` varchar(255) DEFAULT NULL COMMENT '头像',
  `real_name` varchar(50) DEFAULT NULL COMMENT '真实姓名',
  `status` tinyint(4) NOT NULL DEFAULT 1 COMMENT '状态：0=禁用，1=启用',
  `is_admin` tinyint(4) NOT NULL DEFAULT 0 COMMENT '是否超级管理员',
  `last_login_ip` varchar(50) DEFAULT NULL COMMENT '最后登录IP',
  `last_login_at` timestamp NULL DEFAULT NULL COMMENT '最后登录时间',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='管理员表';

--
-- 转储表的索引
--

--
-- 表的索引 `admin_attachments`
--
ALTER TABLE `admin_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_site_id` (`site_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_related_type` (`related_type`),
  ADD KEY `idx_related` (`related_type`,`related_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_sort` (`sort`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_file_hash` (`file_hash`);

--
-- 表的索引 `admin_configs`
--
ALTER TABLE `admin_configs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `admin_configs_key_unique` (`key`),
  ADD KEY `admin_configs_group_index` (`group`),
  ADD KEY `admin_configs_sort_index` (`sort`),
  ADD KEY `idx_sites_id` (`site_id`);

--
-- 表的索引 `admin_menus`
--
ALTER TABLE `admin_menus`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sites_id` (`site_id`),
  ADD KEY `idx_parent_id` (`parent_id`),
  ADD KEY `idx_sites_status` (`site_id`,`status`),
  ADD KEY `idx_sites_parent_sort` (`site_id`,`parent_id`,`sort`),
  ADD KEY `idx_name` (`name`),
  ADD KEY `idx_permission` (`permission`);

--
-- 表的索引 `admin_permissions`
--
ALTER TABLE `admin_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `admin_permissions_slug_unique` (`slug`),
  ADD KEY `admin_permissions_parent_id_index` (`parent_id`),
  ADD KEY `admin_permissions_status_index` (`status`),
  ADD KEY `admin_permissions_sort_index` (`sort`),
  ADD KEY `idx_sites_id` (`site_id`);

--
-- 表的索引 `admin_permission_role`
--
ALTER TABLE `admin_permission_role`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_permission_role` (`permission_id`,`role_id`),
  ADD KEY `admin_permission_role_role_id_index` (`role_id`);

--
-- 表的索引 `admin_roles`
--
ALTER TABLE `admin_roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `admin_roles_slug_unique` (`slug`),
  ADD KEY `admin_roles_status_index` (`status`),
  ADD KEY `admin_roles_sort_index` (`sort`),
  ADD KEY `idx_sites_id` (`site_id`);

--
-- 表的索引 `admin_role_user`
--
ALTER TABLE `admin_role_user`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_role_user` (`role_id`,`user_id`),
  ADD KEY `admin_role_user_user_id_index` (`user_id`);

--
-- 表的索引 `admin_upload_files`
--
ALTER TABLE `admin_upload_files`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `admin_upload_files_upload_token_unique` (`upload_token`),
  ADD KEY `idx_site_id` (`site_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_upload_token` (`upload_token`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_check_status` (`check_status`),
  ADD KEY `idx_token_expire_at` (`token_expire_at`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- 表的索引 `admin_users`
--
ALTER TABLE `admin_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `admin_users_username_unique` (`username`),
  ADD UNIQUE KEY `admin_users_email_unique` (`email`),
  ADD KEY `admin_users_status_index` (`status`),
  ADD KEY `admin_users_created_at_index` (`created_at`),
  ADD KEY `idx_sites_id` (`site_id`);

--
-- 在导出的表使用AUTO_INCREMENT
--

--
-- 使用表AUTO_INCREMENT `admin_attachments`
--
ALTER TABLE `admin_attachments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `admin_configs`
--
ALTER TABLE `admin_configs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `admin_menus`
--
ALTER TABLE `admin_menus`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `admin_permissions`
--
ALTER TABLE `admin_permissions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `admin_permission_role`
--
ALTER TABLE `admin_permission_role`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `admin_roles`
--
ALTER TABLE `admin_roles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `admin_role_user`
--
ALTER TABLE `admin_role_user`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `admin_upload_files`
--
ALTER TABLE `admin_upload_files`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `admin_users`
--
ALTER TABLE `admin_users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
