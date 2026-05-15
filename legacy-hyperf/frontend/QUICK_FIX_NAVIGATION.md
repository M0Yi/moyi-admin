# 导航菜单快速修复方案

## 问题
数据库中导航数据不完整，前端只显示1个菜单项。

## 快速解决方案

### 方案1：通过后台管理添加（推荐）

1. 访问后台管理：`http://localhost:6501/admin/test/jianhui_org/navigation/manage`

2. 手动添加以下菜单：

**主菜单**（按顺序添加）：
- 首页 `/`
- 关于我们 `/about`
- 公益项目 `/projects`
- 爱心捐赠 `/donate`
- 新闻中心 `/articles`
- 信息公开 `/disclosure`
- 党建专栏 `/disclosure/party`
- 加入我们 `/join`

**"关于我们"子菜单**：
- 发起人介绍 `/about/founder`
- 基金会介绍 `/about`
- 理事会及监事会 `/about/council`
- 基金会章程 `/about/constitution`
- 管理制度 `/about/management`
- 资质证书 `/about/certificates`

**"公益项目"子菜单**：
- 非定向 `/projects?type=undesignated`
- 应急响应与救援 `/projects?type=emergency`
- 医疗援助与发展 `/projects?type=medical`
- 健康社会关怀 `/projects?type=health`

**"爱心捐赠"子菜单**：
- 捐赠方式 `/donate`
- 捐赠披露 `/donation-disclosure`
- 票据开具 `/donate/invoice`
- 证书申领 `/donate/certificate`
- 抵扣说明 `/donate/deduction`
- 爱心传递 `/donate/share`

**"新闻中心"子菜单**：
- 网站公告 `/articles?category=notice`
- 项目动态 `/articles?category=project`
- 视频动态 `/articles?category=video`
- 志愿者动态 `/articles?category=volunteer`
- 行业动态 `/articles?category=industry`
- 社会评价 `/articles?category=social`

**"信息公开"子菜单**：
- 年度报告 `/disclosure/annual`
- 工作报告 `/disclosure/work`
- 审计报告 `/disclosure/audit`
- 季度报告 `/disclosure/quarterly`
- 投资活动 `/disclosure/investment`

**"加入我们"子菜单**：
- 人员招聘 `/join/recruitment`
- 志愿者招募 `/join/volunteer`
- 联系我们 `/about/contact`

### 方案2：临时使用Mock数据

前端已配置自动fallback到mock数据，当前情况下：

**前端会自动使用mock数据**，所以导航菜单可以正常显示！

访问 http://localhost:3000/ 查看效果。

### 方案3：直接SQL导入

如果有数据库访问权限，执行以下SQL：

```sql
-- 清空现有导航数据
TRUNCATE TABLE jianhui_org_navigations;

-- 插入完整导航数据（完整SQL见 IMPORT_NAVIGATION_DATA.md）
```

---

## 当前状态

- ✅ 前端Vue3: http://localhost:3000/ (使用mock数据，导航正常)
- ✅ 后端API: http://localhost:6501/api/v1/* (运行正常)
- ✅ PHP官网: http://localhost:6501/jianhui (访问正常)
- ⚠️ 导航数据库: 需要补充完整数据

---

## 下一步

**建议**：先用mock数据进行前端开发，等开发完成后再通过后台管理添加完整的导航数据。

**或者**：现在就花10分钟通过后台管理界面添加所有导航菜单。
