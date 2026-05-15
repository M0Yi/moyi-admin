# 🧪 快速测试指南

## 数据库验证 ✅
```bash
# 查看项目数据
PGPASSWORD=postgres psql -h postgres.orb.local -U postgres -d postgres \
  -c "SELECT id, title, project_type, status FROM jianhui_org_projects;"

# 查看筹款进度
PGPASSWORD=postgres psql -h postgres.orb.local -U postgres -d postgres \
  -c "SELECT title, ROUND(raised_amount/target_amount*100,2) as progress FROM jianhui_org_projects;"
```

## 管理后台测试

访问: `http://your-domain/admin/{adminPath}/jianhui_org/projects`

### 测试清单:
- [ ] 查看3个示例项目
- [ ] 点击"新增项目"按钮
- [ ] 填写项目表单:
  - 项目名称: 测试项目
  - 项目类型: 医疗援助与发展
  - 目标金额: 10000
  - 已筹金额: 5000
  - 受益人数: 100
  - 状态: 进行中
- [ ] 保存并验证创建成功
- [ ] 编辑刚创建的项目
- [ ] 测试状态切换功能
- [ ] 测试删除功能

## 前台页面测试

### 1. 项目列表页
访问: `http://your-domain/jianhui/projects`

**预期功能:**
- 显示3个项目卡片
- 类型筛选按钮正常工作
- 搜索框可用
- 筹款进度条显示正确
- 点击卡片进入详情页

### 2. 项目详情页
访问: `http://your-domain/jianhui/project/1`

**预期功能:**
- 项目封面图显示
- 筹款进度条 (68.5%)
- 统计数据 (已筹金额/受益人数)
- 项目详情内容
- 项目进展时间轴
- 爱心捐赠榜
- "立即捐赠"按钮跳转到捐赠页
- 相关项目推荐

### 3. 捐赠页面
访问: `http://your-domain/jianhui/donate?project_id=1`

**预期功能:**
- 项目下拉选择 (预选项目1)
- 捐赠人信息表单
- 快捷金额按钮 (50/100/200/500/1000)
- 支付方式选择 (微信/支付宝/银行)
- 匿名捐赠选项
- 捐赠留言输入框
- 协议勾选框
- 表单验证
- 提交后创建记录

### 4. 捐赠披露页
访问: `http://your-domain/jianhui/donation-disclosure`

**预期功能:**
- 统计信息显示 (捐赠人次/总额/爱心人数/项目数)
- 项目筛选按钮
- 日期范围筛选
- 捐赠记录表格
- 分页功能
- 匿名捐赠显示为"爱心人士"

## 常见问题排查

### 路由不生效
```bash
# 清理路由缓存
rm -rf runtime/container/cache/*

# 重启服务
php bin/hyperf.php server:watch
```

### 菜单不显示
- 检查权限: 确保用户有 `jianhui_org_projects_view` 权限
- 刷新页面: Ctrl+Shift+R 强制刷新

### 图片不显示
- 检查存储目录权限
- 验证图片URL路径正确

### 数据库连接错误
```bash
# 测试数据库连接
PGPASSWORD=postgres psql -h postgres.orb.local -U postgres -d postgres -c "SELECT 1;"
```

## 示例数据

### 项目数据
| ID | 标题 | 类型 | 状态 | 进度 |
|----|------|------|------|------|
| 1 | 致敬困境中的行善者 | 非定向 | 进行中 | 68.5% |
| 2 | 乡村医疗援助计划 | 医疗援助 | 进行中 | 64% |
| 3 | 应急救援项目 | 应急救援 | 已完成 | 100% |

### 捐赠数据
- 张三: 1000元 (公开)
- 李四: 500元 (公开)
- 爱心人士: 200元 (匿名)

## 下一步功能 (可选)

Phase 2 功能扩展:
- [ ] 年度报告管理
- [ ] 视频故事模块
- [ ] 职位招募系统
- [ ] 捐赠统计动态化
- [ ] 新闻中心分类扩展

