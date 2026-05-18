package api

import (
	"sort"
	"strconv"
	"strings"
)

type adminResourceModel struct {
	Key           string
	Name          string
	Plugin        string
	Source        string
	Table         string
	Description   string
	Actions       string
	ReadScope     string
	Status        string
	StatusClass   string
	FieldsSummary string
	FieldCount    int
	ToolCount     int
}

type adminResourceTool struct {
	Name        string
	Plugin      string
	Resource    string
	ResourceKey string
	Action      string
	Permission  string
	Boundary    string
	Status      string
	StatusClass string
}

type adminPluginExtension struct {
	Key           string
	Name          string
	Kind          string
	Version       string
	Description   string
	Resources     string
	Tools         string
	ResourceCount int
	ToolCount     int
	Status        string
	StatusClass   string
}

func buildResourceModels(state installState) []adminResourceModel {
	storage := state.Storage.normalized()
	taskWorker := state.TaskWorker.normalized()
	models := []adminResourceModel{
		resourceModel("install_state", "系统初始化状态", "core.admin", "metadata", "install_state", "站点、隐藏入口、元数据数据库、AI 配置和安装时间。", []string{"describe", "preview", "export"}, "后台管理员可读，敏感字段脱敏", "已接入", "is-ready", 8, "site_name 站点；admin_entry 后台入口；database_driver 元数据库；ai_provider AI 服务"),
		resourceModel("admin_settings", "后台运行配置", "core.admin", "derived_view", "admin_settings", "站点资料、首页展示、语言、时区、AI、存储、安全与通知策略。", []string{"describe", "preview", "export", "configure"}, "后台管理员可读，配置修改走受控表单", "已接入", "is-ready", 14, "site_name 站点；admin_tagline 后台副标题；storage 存储；notification_channel 通知通道"),
		resourceModel("admin_users", "后台管理员账号", "core.admin", "metadata", "admin_users", "后台管理员账号、显示名称、角色、启用状态和来源。", []string{"describe", "preview", "query", "export", "manage"}, "后台管理员可读，密码与哈希永不展示", "已接入", "is-ready", 7, "username 登录账号；display_name 显示名称；role 角色；status 启用状态"),
		resourceModel("data_sources", "数据源配置", "core.data", "metadata", "data_sources", "元数据库、旧系统归档和业务数据源的接入状态。", []string{"describe", "preview", "query", "export", "manage"}, "后台管理员可读，连接密码脱敏", "已接入", "is-ready", 6, "name 数据源；driver 类型；target 目标；schema_summary 结构摘要"),
		resourceModel("schema_snapshots", "数据源结构快照", "core.data", "metadata", "schema_snapshots", "真实数据源测试时沉淀的表结构、表注释、字段注释和索引摘要。", []string{"describe", "preview", "query", "export"}, "后台管理员可读，用于注释匹配和范化检索", "已接入", "is-ready", 10, "data_source 数据源；summary 扫描摘要；table_count 表数量；checks 检查项"),
		resourceModel("upload_files", "文件管理", "core.storage", "filesystem_view", storage.LocalPath, "后台上传文件、智能体导出文件、本地路径、大小和更新时间。", []string{"describe", "preview", "query", "export", "upload"}, "后台管理员可读，下载走后台鉴权入口", "已接入", "is-ready", 6, "name 文件名；path 相对路径；kind 类型；size 大小；modified_at 更新时间"),
		resourceModel("audit_events", "审计日志", "core.operations", "metadata", "audit_events", "后台登录、设置、文件、智能体对话和关键操作的审计事件。", []string{"describe", "preview", "query", "export"}, "后台管理员可读，保留 IP 与请求路径", "已接入", "is-ready", 9, "time 时间；category 分类；action 操作；actor 操作者；detail 详情"),
		resourceModel("background_tasks", "后台任务", "core.operations", "metadata", "background_tasks", "异步任务、队列状态、尝试次数、执行结果和失败原因。", []string{"describe", "preview", "query", "export", "run"}, "后台管理员可读，执行动作走受控按钮", "已接入", "is-ready", 12, "task_id 任务标识；type 类型；queue 队列；status 状态；result 结果"),
		resourceModel("notification_deliveries", "通知发送记录", "core.operations", "metadata", "notification_deliveries", "Webhook / 飞书机器人通知事件、接收人、发送目标、状态码和失败原因。", []string{"describe", "preview", "query", "export"}, "后台管理员可读，通知目标脱敏", "已接入", "is-ready", 9, "event 事件；title 标题；receiver 接收人；channel 通道；status 发送状态；error 失败原因"),
		resourceModel("ai_capabilities", "智能体能力", "core.agent", "computed_view", "ai_capabilities", "后台智能体可调用工具、执行边界和当前接入状态。", []string{"describe", "preview", "export"}, "后台管理员可读，用于能力发现", "已接入", "is-ready", 3, "name 工具能力；boundary 执行边界；status 当前状态"),
		resourceModel("agent_runs", "智能体运行记录", "core.agent", "metadata", "agent_runs", "每次智能体运行的模式、目标、模型使用、工具次数、文件数量和耗时。", []string{"describe", "preview", "query", "export"}, "后台管理员可读，用于追踪和复盘", "已接入", "is-ready", 12, "mode 意图模式；goal 目标；tool_count 工具次数；file_count 文件数量"),
		resourceModel("agent_tool_results", "智能体工具结果", "core.agent", "metadata", "agent_tool_results", "每次工具调用的目标表、SQL、行数、文件结果和错误信息。", []string{"describe", "preview", "query", "export"}, "后台管理员可读，敏感字段已脱敏", "已接入", "is-ready", 11, "name 工具；table 目标表；sql 只读 SQL；file 生成文件；row_count 行数"),
		resourceModel("agent_wechat_messages", "微信 Agent 聊天记录", "core.agent", "metadata", "agent_wechat_messages", "微信 Agent 通道收到的用户消息、AI 回复、文件回复、发送状态和错误信息。", []string{"describe", "preview", "query", "export"}, "后台管理员可读，用于复盘微信通道对话", "已接入", "is-ready", 14, "channel_key 通道；session_id 会话；inbound_text 用户消息；reply_text AI回复；status 状态"),
		resourceModel("plugin_extensions", "插件扩展包", "core.data", "computed_view", "plugin_extensions", "已登记插件能力包、资源声明、工具数量和接入状态。", []string{"describe", "preview", "query", "export"}, "后台管理员可读，用于扩展发现", "本次接入", "is-progress", 8, "key 插件标识；kind 类型；resources 资源；tools 工具；status 状态"),
		resourceModel("resource_models", "资源模型", "core.data", "computed_view", "resource_models", "由插件或核心模块声明的资源、字段、动作和权限边界。", []string{"describe", "preview", "query", "export"}, "后台管理员可读，是 AI 工具生成的来源", "本次接入", "is-progress", 10, "key 资源标识；plugin 插件；actions 动作；fields 字段；tools 工具"),
		resourceModel("resource_tools", "资源工具", "core.data", "computed_view", "resource_tools", "资源模型自动生成的 AI 可调用只读、导出和受控管理工具。", []string{"describe", "preview", "query", "export"}, "后台管理员可读，用于工具注册核对", "本次接入", "is-progress", 8, "name 工具名；resource 资源；action 动作；permission 权限；boundary 边界"),
	}

	if taskWorker.Enabled {
		models = append(models, resourceModel("task_worker_settings", "后台任务执行器设置", "core.operations", "derived_view", "task_worker_settings", "后台任务自动执行、扫描间隔、批量数量和定时调度策略。", []string{"describe", "preview", "export", "configure"}, "后台管理员可读，修改走受控表单", "已接入", "is-ready", 8, "enabled 是否启用；interval_seconds 扫描间隔；batch_size 单轮数量；status 状态"))
	} else {
		models = append(models, resourceModel("task_worker_settings", "后台任务执行器设置", "core.operations", "derived_view", "task_worker_settings", "后台任务自动执行、扫描间隔、批量数量和定时调度策略。", []string{"describe", "preview", "export", "configure"}, "后台管理员可读，修改走受控表单", "已接入", "is-ready", 8, "enabled 是否启用；interval_seconds 扫描间隔；batch_size 单轮数量；status "+taskWorker.StatusText()))
	}

	for _, source := range normalizeDataSources(state.DataSources) {
		source = source.normalized()
		status := dataSourceStatusText(source.Status)
		statusClass := dataSourceStatusClass(source.Status)
		description := "已登记业务数据源：" + source.Role + "。"
		if source.Status != "available" {
			description = "已登记业务数据源：" + source.Role + "，等待连接测试或结构扫描后进入稳定读取。"
		}
		fieldsSummary := strings.TrimSpace(source.SchemaSummary)
		fieldCount := estimateResourceFieldCount(fieldsSummary)
		if fieldsSummary == "" {
			fieldsSummary = "等待结构扫描后填充表注释、字段注释和索引摘要"
		}
		models = append(models, resourceModel("datasource."+source.Name, source.Name+" 数据源", "external.data_source", source.DisplayName(), source.DisplayTarget(), description, []string{"describe", "preview", "query", "export"}, "后台管理员可读，连接密码脱敏", status, statusClass, fieldCount, fieldsSummary))
	}
	return models
}

func buildResourceTools(models []adminResourceModel) []adminResourceTool {
	tools := make([]adminResourceTool, 0, len(models)*4)
	for _, model := range models {
		for _, action := range splitResourceActions(model.Actions) {
			label, permission, boundary, status, statusClass := resourceActionInfo(action)
			tools = append(tools, adminResourceTool{
				Name:        "resource." + model.Key + "." + action,
				Plugin:      model.Plugin,
				Resource:    model.Name,
				ResourceKey: model.Key,
				Action:      label,
				Permission:  permission,
				Boundary:    boundary,
				Status:      status,
				StatusClass: statusClass,
			})
		}
	}
	return tools
}

func buildPluginExtensions(state installState, models []adminResourceModel, tools []adminResourceTool) []adminPluginExtension {
	plugins := []adminPluginExtension{
		{Key: "core.admin", Name: "后台核心扩展", Kind: "builtin", Version: "go-core", Description: "隐藏入口、系统设置、管理员账号和权限边界。", Status: "已接入", StatusClass: "is-ready"},
		{Key: "core.data", Name: "资源与数据扩展", Kind: "builtin", Version: "go-core", Description: "数据源、结构快照、资源模型和 AI 工具注册表。", Status: "本次接入", StatusClass: "is-progress"},
		{Key: "core.storage", Name: "文件存储扩展", Kind: "builtin", Version: "go-core", Description: "上传文件、预览下载、智能体导出文件和保留策略。", Status: "已接入", StatusClass: "is-ready"},
		{Key: "core.operations", Name: "运维任务扩展", Kind: "builtin", Version: "go-core", Description: "审计日志、后台任务、通知事件和运行记录。", Status: "已接入", StatusClass: "is-ready"},
		{Key: "core.agent", Name: "智能体运行扩展", Kind: "builtin", Version: "go-core", Description: "智能体能力、运行记忆、工具轨迹和产出文件。", Status: "已接入", StatusClass: "is-ready"},
		{Key: "legacy.hyperf_reference", Name: "旧 Hyperf 参考包", Kind: "archive", Version: "legacy", Description: "仅作为旧系统控制器、服务、模型和插件资源的迁移对照。", Status: "只读归档", StatusClass: "is-muted"},
	}
	if hasExternalDataSource(state) {
		plugins = append(plugins, adminPluginExtension{Key: "external.data_source", Name: "业务数据源扩展", Kind: "runtime", Version: "registered", Description: "由后台登记的数据源动态声明资源模型和只读查询工具。", Status: "动态接入", StatusClass: "is-progress"})
	}

	for i := range plugins {
		resourceNames := resourceNamesForPlugin(models, plugins[i].Key)
		plugins[i].ResourceCount = len(resourceNames)
		plugins[i].ToolCount = toolCountForPlugin(tools, plugins[i].Key)
		plugins[i].Resources = summarizeResourceNames(resourceNames)
		plugins[i].Tools = strconv.Itoa(plugins[i].ToolCount) + " 个工具"
		if plugins[i].ResourceCount == 0 && plugins[i].Key == "legacy.hyperf_reference" {
			plugins[i].Resources = "归档代码参考"
			plugins[i].Tools = "0 个工具"
		}
	}
	return plugins
}

func buildExtensionMetrics(plugins []adminPluginExtension, models []adminResourceModel, tools []adminResourceTool) []adminMetric {
	readyPlugins := 0
	for _, plugin := range plugins {
		if plugin.StatusClass != "is-muted" {
			readyPlugins++
		}
	}
	return []adminMetric{
		{Label: "扩展包", Value: strconv.Itoa(len(plugins)), Detail: strconv.Itoa(readyPlugins) + " 个参与运行", Status: "is-ready"},
		{Label: "资源模型", Value: strconv.Itoa(len(models)), Detail: "后台与智能体共用", Status: "is-ready"},
		{Label: "生成工具", Value: strconv.Itoa(len(tools)), Detail: "读取、查询、导出与受控命令", Status: "is-progress"},
		{Label: "传统 CRUD", Value: "已替换", Detail: "资源模型 + Agent 工具", Status: "is-progress"},
	}
}

func resourceModel(key string, name string, plugin string, source string, table string, description string, actions []string, readScope string, status string, statusClass string, fieldCount int, fieldsSummary string) adminResourceModel {
	if status == "" {
		status = "已接入"
	}
	if statusClass == "" {
		statusClass = "is-ready"
	}
	return adminResourceModel{
		Key:           strings.TrimSpace(key),
		Name:          strings.TrimSpace(name),
		Plugin:        strings.TrimSpace(plugin),
		Source:        strings.TrimSpace(source),
		Table:         strings.TrimSpace(table),
		Description:   strings.TrimSpace(description),
		Actions:       strings.Join(actions, ", "),
		ReadScope:     strings.TrimSpace(readScope),
		Status:        strings.TrimSpace(status),
		StatusClass:   strings.TrimSpace(statusClass),
		FieldsSummary: strings.TrimSpace(fieldsSummary),
		FieldCount:    fieldCount,
		ToolCount:     len(actions),
	}
}

func splitResourceActions(actions string) []string {
	parts := strings.FieldsFunc(actions, func(r rune) bool {
		return r == ',' || r == '，' || r == ' ' || r == '\n' || r == '\t'
	})
	out := make([]string, 0, len(parts))
	seen := map[string]bool{}
	for _, part := range parts {
		action := strings.ToLower(strings.TrimSpace(part))
		if action == "" || seen[action] {
			continue
		}
		seen[action] = true
		out = append(out, action)
	}
	return out
}

func resourceActionInfo(action string) (string, string, string, string, string) {
	switch strings.ToLower(strings.TrimSpace(action)) {
	case "describe":
		return "读取结构", "read:schema", "读取字段、注释、表说明和索引摘要。", "已启用", "is-ready"
	case "preview":
		return "预览数据", "read:preview", "最多预览受控行数，敏感字段脱敏。", "已启用", "is-ready"
	case "query":
		return "只读查询", "read:select", "允许单表 SELECT 和范化字段匹配，拒绝写入。", "已启用", "is-ready"
	case "export":
		return "导出文件", "read:export", "按筛选结果生成 CSV/XLSX/JSON 等文件。", "已启用", "is-ready"
	case "configure":
		return "配置保存", "admin:configure", "通过后台表单提交，写入审计记录。", "受控命令", "is-progress"
	case "manage":
		return "后台管理", "admin:manage", "创建、禁用、删除等写操作保留在后台受控流程。", "受控命令", "is-progress"
	case "upload":
		return "文件操作", "admin:file", "上传、预览、下载与删除均走后台鉴权。", "受控命令", "is-progress"
	case "run":
		return "任务执行", "admin:task", "执行、重试、取消任务均走后台受控按钮。", "受控命令", "is-progress"
	default:
		return action, "read", "按资源模型声明的边界执行。", "待确认", "is-muted"
	}
}

func hasExternalDataSource(state installState) bool {
	return len(normalizeDataSources(state.DataSources)) > 0
}

func resourceNamesForPlugin(models []adminResourceModel, plugin string) []string {
	names := make([]string, 0)
	for _, model := range models {
		if model.Plugin == plugin {
			names = append(names, model.Name)
		}
	}
	sort.Strings(names)
	return names
}

func toolCountForPlugin(tools []adminResourceTool, plugin string) int {
	count := 0
	for _, tool := range tools {
		if tool.Plugin == plugin {
			count++
		}
	}
	return count
}

func summarizeResourceNames(names []string) string {
	if len(names) == 0 {
		return "暂无资源"
	}
	if len(names) <= 4 {
		return strings.Join(names, "、")
	}
	return strings.Join(names[:4], "、") + " 等 " + strconv.Itoa(len(names)) + " 个"
}

func estimateResourceFieldCount(summary string) int {
	summary = strings.TrimSpace(summary)
	if summary == "" {
		return 0
	}
	count := strings.Count(summary, "字段")
	if count > 0 {
		return count
	}
	return 1
}
