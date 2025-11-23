-- MySQL 初始化脚本
-- 此脚本会在 MySQL 容器首次启动时自动执行
-- 用于创建 moyi 数据库

-- 创建数据库（如果不存在）
CREATE DATABASE IF NOT EXISTS `moyi` 
    CHARACTER SET utf8mb4 
    COLLATE utf8mb4_unicode_ci;

-- 显示创建的数据库
SHOW DATABASES LIKE 'moyi';

