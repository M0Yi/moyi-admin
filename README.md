# Introduction

This is a skeleton application using the Hyperf framework. This application is meant to be used as a starting place for those looking to get their feet wet with Hyperf Framework.

# Requirements

Hyperf has some requirements for the system environment, it can only run under Linux and Mac environment, but due to the development of Docker virtualization technology, Docker for Windows can also be used as the running environment under Windows.

The various versions of Dockerfile have been prepared for you in the [hyperf/hyperf-docker](https://github.com/hyperf/hyperf-docker) project, or directly based on the already built [hyperf/hyperf](https://hub.docker.com/r/hyperf/hyperf) Image to run.

When you don't want to use Docker as the basis for your running environment, you need to make sure that your operating environment meets the following requirements:  

 - PHP >= 8.1
 - Any of the following network engines
   - Swoole PHP extension >= 5.0，with `swoole.use_shortname` set to `Off` in your `php.ini`
   - Swow PHP extension >= 1.3
 - JSON PHP extension
 - Pcntl PHP extension
 - OpenSSL PHP extension （If you need to use the HTTPS）
 - PDO PHP extension （If you need to use the MySQL Client）
 - Redis PHP extension （If you need to use the Redis Client）
 - Protobuf PHP extension （If you need to use the gRPC Server or Client）

# Installation using Composer

The easiest way to create a new Hyperf project is to use [Composer](https://getcomposer.org/). If you don't have it already installed, then please install as per [the documentation](https://getcomposer.org/download/).

To create your new Hyperf project:

```bash
composer create-project hyperf/hyperf-skeleton path/to/install
```

If your development environment is based on Docker you can use the official Composer image to create a new Hyperf project:

```bash
docker run --rm -it -v $(pwd):/app composer create-project --ignore-platform-reqs hyperf/hyperf-skeleton path/to/install
```

# Getting started

Once installed, you can run the server immediately using the command below.

```bash
cd path/to/install
php bin/hyperf.php start
```

Or if in a Docker based environment you can use the `docker-compose.yml` provided by the template:

## CI: 自动构建并推送 Docker 镜像

此项目已内置 GitHub Actions 工作流，支持在推送到 `main`/`master` 分支或打 `v*.*.*` 标签时，自动构建并推送镜像到 Docker Hub。

### 预置条件
- 在 Docker Hub 创建仓库，命名建议为 `<你的用户名>/moyi-admin`。
- 在 GitHub 仓库的 Settings → Secrets and variables → Actions 中新增 Secrets：
  - `DOCKERHUB_USERNAME`：你的 Docker Hub 用户名。
  - `DOCKERHUB_TOKEN`：Docker Hub 的 Access Token（或密码，不推荐）。

### 触发规则
- Push 到 `main` 或 `master`，将生成如下标签之一：
  - `latest`（默认分支）
  - 分支名标签（如 `main`、`feature-xxx`）
  - 提交 SHA 标签（如 `sha-<short>`）
- Push 带有 `v*.*.*` 的标签（如 `v1.2.3`），会额外生成 `v1.2.3` 标签。

### 工作流说明
- 文件位置：`.github/workflows/docker-publish.yml`
- 使用 `docker/build-push-action@v5` 构建并推送。
- 默认推送到 `docker.io/${DOCKERHUB_USERNAME}/moyi-admin`。
- 平台：`linux/amd64`（如需多架构可改为 `linux/amd64,linux/arm64`）。
- 使用 GitHub Actions 缓存加速构建。

### .dockerignore 调整
- 已优化为不将 `.env*` 与 `vendor/` 打入镜像，减少体积并避免敏感信息泄露。
- 若你的镜像不需在构建阶段执行 `composer install`，可在 `.dockerignore` 中恢复 `vendor/`，并修改 `Dockerfile` 跳过 Composer 安装。

### 运行示例
```bash
# 拉取镜像（以你的 Docker Hub 用户名替换 <username>）
docker pull <username>/moyi-admin:latest

# 以环境变量运行（推荐在运行时注入，而非打包 .env）
docker run -d --name moyi-admin \
  -p 9501:9501 \
  -e APP_ENV=prod \
  <username>/moyi-admin:latest

# 若确需使用 .env 文件，可改用 --env-file 方式
docker run -d --name moyi-admin \
  -p 9501:9501 \
  --env-file ./.env \
  <username>/moyi-admin:latest
```

### 常见定制
- 修改镜像名：在工作流中将 `IMAGE_NAME` 改为你自己的仓库名。
- 多架构构建：将 `platforms` 设置为 `linux/amd64,linux/arm64`（需要开启 QEMU）。
- 自定义标签：可在 `docker/metadata-action` 的 `tags` 中增加规则。

```bash
cd path/to/install
docker-compose up
```

This will start the cli-server on port `9501`, and bind it to all network interfaces. You can then visit the site at `http://localhost:9501/` which will bring up Hyperf default home page.

## Hints

- A nice tip is to rename `hyperf-skeleton` of files like `composer.json` and `docker-compose.yml` to your actual project name.
- Take a look at `config/routes.php` and `app/Controller/IndexController.php` to see an example of a HTTP entrypoint.

**Remember:** you can always replace the contents of this README.md file to something that fits your project description.
