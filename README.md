# Moyi Admin

> 超轻量 Hyperf 控制中枢 · CRUD 核心 · 极速部署

## 文案摘要
- Hyperf 3.1 + Swoole 5，天生高并发。
- 一键 CRUD，模型/表格/表单一体生成。
- JWT + Session 双态守护，日志可追溯。
- Excel/CSV 导入导出、回收站实时可用。

## 技术栈
- PHP 8.1+, Hyperf 3.1, Swoole 5
- MySQL / Redis / Hyperf ORM
- Blade, 原生 JS, Bootstrap 基础样式

## 极速开始
1. `git clone https://github.com/M0Yi/moyi-admin.git`
2. `composer install`
3. `cp .env.example .env` 并填写 DB/Redis
4. `php bin/hyperf.php start` 或 `docker-compose up -d`

访问 `http://localhost:6501/install` 完成初始化，登录后台自由发挥。
