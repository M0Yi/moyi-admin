package api

import (
	"archive/zip"
	"bytes"
	"context"
	"encoding/csv"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"net/http"
	"os"
	"path/filepath"
	"regexp"
	"sort"
	"strconv"
	"strings"
	"time"
	"unicode/utf8"
)

const defaultAgentTimeout = 18 * time.Second

var agentHTTPClient = &http.Client{Timeout: defaultAgentTimeout}

type agentChatRequest struct {
	SessionID string             `json:"session_id,omitempty"`
	Message   string             `json:"message"`
	History   []agentChatMessage `json:"history,omitempty"`
}

type agentChatMessage struct {
	Role    string `json:"role"`
	Content string `json:"content"`
}

type agentChatResponse struct {
	OK          bool              `json:"ok"`
	SessionID   string            `json:"session_id,omitempty"`
	Reply       string            `json:"reply"`
	Run         agentRun          `json:"run"`
	ToolResults []agentToolResult `json:"tool_results,omitempty"`
	Files       []agentFileResult `json:"files,omitempty"`
	ModelUsed   bool              `json:"model_used"`
	Error       string            `json:"error,omitempty"`
}

type agentRun struct {
	ID          string            `json:"id"`
	Mode        string            `json:"mode"`
	Goal        string            `json:"goal"`
	Plan        []agentPlanStep   `json:"plan"`
	Trace       []agentTraceItem  `json:"trace"`
	Insights    []agentInsight    `json:"insights"`
	Suggestions []agentSuggestion `json:"suggestions"`
	Metadata    map[string]string `json:"metadata,omitempty"`
}

type agentPlanStep struct {
	Title  string `json:"title"`
	Detail string `json:"detail"`
	Status string `json:"status"`
}

type agentTraceItem struct {
	Title  string `json:"title"`
	Detail string `json:"detail"`
	Tool   string `json:"tool,omitempty"`
	Status string `json:"status"`
}

type agentInsight struct {
	Title  string `json:"title"`
	Detail string `json:"detail"`
	Tone   string `json:"tone"`
}

type agentSuggestion struct {
	Label  string `json:"label"`
	Prompt string `json:"prompt"`
}

type agentToolResult struct {
	Name    string              `json:"name"`
	OK      bool                `json:"ok"`
	Message string              `json:"message"`
	Table   string              `json:"table,omitempty"`
	SQL     string              `json:"sql,omitempty"`
	Columns []string            `json:"columns,omitempty"`
	Rows    []map[string]string `json:"rows,omitempty"`
	File    *agentFileResult    `json:"file,omitempty"`
	Error   string              `json:"error,omitempty"`
}

type agentRuntimeSnapshot struct {
	Sessions    []agentSessionRecord
	Runs        []agentRunRecord
	ToolResults []agentToolResultRecord
}

type agentSessionRecord struct {
	ID          string
	Title       string
	Actor       string
	StartedAt   time.Time
	UpdatedAt   time.Time
	LastMessage string
	RunCount    int
}

type agentRunRecord struct {
	ID          string
	SessionID   string
	Actor       string
	Mode        string
	Goal        string
	Message     string
	Reply       string
	Status      string
	ModelUsed   bool
	ToolCount   int
	FileCount   int
	DurationMS  int64
	StartedAt   time.Time
	Run         agentRun
	ToolResults []agentToolResult
	Files       []agentFileResult
}

type agentToolResultRecord struct {
	RunID     string
	Index     int
	Name      string
	OK        bool
	Table     string
	SQL       string
	Message   string
	Error     string
	FileName  string
	FileURL   string
	RowCount  int
	Columns   string
	CreatedAt time.Time
}

type agentFileResult struct {
	Name        string `json:"name"`
	URL         string `json:"url"`
	MIME        string `json:"mime"`
	Size        int64  `json:"size"`
	Description string `json:"description"`
}

type agentTableColumn struct {
	Name        string
	Type        string
	Description string
}

type agentTableDefinition struct {
	Name        string
	Type        string
	DisplayName string
	Description string
	Aliases     []string
}

type tableToolProvider struct {
	state            installState
	exportDir        string
	downloadBasePath string
	auditEvents      []adminAuditEvent
	runtime          agentRuntimeSnapshot
}

type agentExportFormat struct {
	Extension string
	MIME      string
	Label     string
}

type agentExportSheet struct {
	Table   string              `json:"table"`
	Columns []string            `json:"columns"`
	Rows    []map[string]string `json:"rows"`
}

type agentExportData struct {
	GeneratedAt string             `json:"generated_at"`
	Sheets      []agentExportSheet `json:"sheets"`
	TotalRows   int                `json:"total_rows"`
}

type agentIntent string

const (
	agentIntentAdmin        agentIntent = "admin_assistant"
	agentIntentTableCatalog agentIntent = "table_catalog"
	agentIntentDescribe     agentIntent = "schema_inspection"
	agentIntentPreview      agentIntent = "data_preview"
	agentIntentQuery        agentIntent = "read_query"
	agentIntentExport       agentIntent = "data_export"
	agentIntentGuardrail    agentIntent = "guardrail"
	agentIntentHealth       agentIntent = "system_review"
	agentIntentDesign       agentIntent = "agent_design"
	agentIntentUserAccess   agentIntent = "user_access"
)

var simpleSelectPattern = regexp.MustCompile(`(?i)^\s*select\s+(.+?)\s+from\s+([a-zA-Z_][a-zA-Z0-9_]*)(?:\s+limit\s+(\d+))?\s*$`)
var identifierPattern = regexp.MustCompile(`^[a-zA-Z_][a-zA-Z0-9_]*$`)

func (s *adminServer) aiChat(w http.ResponseWriter, r *http.Request) {
	state, err := s.store.Load()
	if err != nil {
		writeJSON(w, http.StatusInternalServerError, agentChatResponse{
			OK:    false,
			Error: "读取初始化状态失败",
		})
		return
	}
	if !state.Initialized {
		writeJSON(w, http.StatusConflict, agentChatResponse{
			OK:    false,
			Error: "系统尚未初始化，无法使用后台智能体",
		})
		return
	}
	if !s.isAuthenticated(r) {
		writeJSON(w, http.StatusUnauthorized, agentChatResponse{
			OK:    false,
			Error: "请先登录后台",
		})
		return
	}

	defer r.Body.Close()
	var payload agentChatRequest
	if err := json.NewDecoder(io.LimitReader(r.Body, 64*1024)).Decode(&payload); err != nil {
		writeJSON(w, http.StatusBadRequest, agentChatResponse{
			OK:    false,
			Error: "对话请求格式不正确",
		})
		return
	}
	payload.Message = strings.TrimSpace(payload.Message)
	if payload.Message == "" {
		writeJSON(w, http.StatusBadRequest, agentChatResponse{
			OK:    false,
			Error: "请输入要询问智能体的内容",
		})
		return
	}
	if len([]rune(payload.Message)) > 2000 {
		writeJSON(w, http.StatusBadRequest, agentChatResponse{
			OK:    false,
			Error: "单次消息不能超过 2000 个字符",
		})
		return
	}

	startedAt := time.Now()
	sessionID := normalizeAgentSessionID(payload.SessionID)
	if sessionID == "" {
		sessionID = newAgentSessionID()
	}
	response := s.runAgentChat(r.Context(), state, payload)
	response.SessionID = sessionID
	statusCode := http.StatusOK
	if !response.OK {
		statusCode = http.StatusBadRequest
	}
	duration := time.Since(startedAt)
	actor := s.sessionUsername(r)
	if actor == "" {
		actor = state.AdminUser
	}
	_ = s.store.AppendAgentRun(agentRunRecord{
		ID:          response.Run.ID,
		SessionID:   sessionID,
		Actor:       actor,
		Mode:        response.Run.Mode,
		Goal:        response.Run.Goal,
		Message:     payload.Message,
		Reply:       response.Reply,
		Status:      agentRunStatus(response.OK),
		ModelUsed:   response.ModelUsed,
		ToolCount:   len(response.ToolResults),
		FileCount:   len(response.Files),
		DurationMS:  duration.Milliseconds(),
		StartedAt:   startedAt.UTC(),
		Run:         response.Run,
		ToolResults: response.ToolResults,
		Files:       response.Files,
	})
	s.recordAuditEvent(r, state, auditEventInput{
		Category:   "ai",
		Action:     "智能体对话",
		Detail:     fmt.Sprintf("模式 %s，工具调用 %d 次，返回文件 %d 个", response.Run.Mode, len(response.ToolResults), len(response.Files)),
		StatusCode: statusCode,
		Duration:   duration,
	})
	writeJSON(w, statusCode, response)
}

func (s *adminServer) aiFileDownload(w http.ResponseWriter, r *http.Request) {
	state, err := s.store.Load()
	if err != nil {
		http.Error(w, "读取初始化状态失败", http.StatusInternalServerError)
		return
	}
	if !state.Initialized {
		http.NotFound(w, r)
		return
	}
	if !s.isAuthenticated(r) {
		http.Error(w, "请先登录后台", http.StatusUnauthorized)
		return
	}

	entry := s.adminEntryForState(state)
	subpath := adminSubpath(r.URL.Path, entry)
	name := strings.TrimPrefix(subpath, "/ai/files/")
	name = filepath.Base(strings.TrimSpace(name))
	if name == "." || name == "/" || name == "" || strings.Contains(name, string(filepath.Separator)) {
		http.NotFound(w, r)
		return
	}
	contentType, ok := agentExportContentType(name)
	if !strings.HasPrefix(name, "moyi-agent-") || !ok {
		http.NotFound(w, r)
		return
	}

	filePath := filepath.Join(s.agentExportDir(), name)
	w.Header().Set("Content-Type", contentType)
	w.Header().Set("Content-Disposition", `attachment; filename="`+name+`"`)
	http.ServeFile(w, r, filePath)
}

func (s *adminServer) runAgentChat(ctx context.Context, state installState, payload agentChatRequest) agentChatResponse {
	entry := s.adminEntryForState(state)
	tools := newTableToolProvider(state, s.agentExportDir(), entry+"/ai/files", s.listAuditEvents(50))
	tools.runtime = agentRuntimeSnapshot{
		Sessions:    s.listAgentSessions(50),
		Runs:        s.listAgentRuns(80),
		ToolResults: s.listAgentToolResults(120),
	}
	intent := determineAgentIntent(payload.Message)
	results := planAndRunAgentTools(tools, payload.Message)
	run := buildAgentRun(state, payload.Message, intent, results)
	fallbackReply := composeLocalAgentReply(payload.Message, run, results)
	files := collectAgentFiles(results)

	modelReply, modelUsed, err := callConfiguredAgentModel(ctx, state.AI, payload, run, results)
	if err == nil && strings.TrimSpace(modelReply) != "" {
		return agentChatResponse{
			OK:          true,
			Reply:       modelReply,
			Run:         run,
			ToolResults: results,
			Files:       files,
			ModelUsed:   modelUsed,
		}
	}
	if err != nil && !state.AI.IsDisabled() {
		fallbackReply += "\n\n模型服务暂时没有返回可用结果，已使用后台本地受控工具完成这次查询。"
	}

	return agentChatResponse{
		OK:          true,
		Reply:       fallbackReply,
		Run:         run,
		ToolResults: results,
		Files:       files,
		ModelUsed:   false,
	}
}

func (s *adminServer) agentExportDir() string {
	if s.store != nil && strings.TrimSpace(s.store.path) != "" {
		return filepath.Join(filepath.Dir(s.store.path), "exports")
	}
	return filepath.Join(os.TempDir(), "moyi-admin-exports")
}

func collectAgentFiles(results []agentToolResult) []agentFileResult {
	files := make([]agentFileResult, 0)
	for _, result := range results {
		if result.File != nil {
			files = append(files, *result.File)
		}
	}
	return files
}

func normalizeAgentSessionID(value string) string {
	value = strings.TrimSpace(value)
	if value == "" {
		return ""
	}
	value = strings.Map(func(r rune) rune {
		if r >= 'a' && r <= 'z' {
			return r
		}
		if r >= 'A' && r <= 'Z' {
			return r
		}
		if r >= '0' && r <= '9' {
			return r
		}
		if r == '-' || r == '_' {
			return r
		}
		return -1
	}, value)
	if len(value) > 80 {
		value = value[:80]
	}
	return value
}

func newAgentSessionID() string {
	return "session-" + strconv.FormatInt(time.Now().UnixNano(), 36)
}

func agentRunStatus(ok bool) string {
	if ok {
		return "ok"
	}
	return "failed"
}

func newTableToolProvider(state installState, exportDir string, downloadBasePath string, auditEvents ...[]adminAuditEvent) tableToolProvider {
	state.Database = state.Database.sanitized()
	state.AI = state.AI.sanitized()
	events := []adminAuditEvent(nil)
	if len(auditEvents) > 0 {
		events = auditEvents[0]
	}
	return tableToolProvider{
		state:            state,
		exportDir:        strings.TrimSpace(exportDir),
		downloadBasePath: strings.TrimRight(strings.TrimSpace(downloadBasePath), "/"),
		auditEvents:      events,
	}
}

func determineAgentIntent(message string) agentIntent {
	lower := strings.ToLower(strings.TrimSpace(message))
	if sql, ok := extractAgentSQL(message); ok && strings.HasPrefix(strings.ToLower(sql), "select ") {
		return agentIntentQuery
	}
	if containsAny(lower, "delete", "update", "insert", "drop", "alter", "truncate", "create", "replace", "attach", "detach", "pragma", "vacuum") {
		return agentIntentGuardrail
	}
	if containsAny(lower, "codex", "更强", "智能体方案", "智能体构造", "构造方案", "架构", "工作台", "agent os", "agent") ||
		(strings.Contains(lower, "智能体") && containsAny(lower, "设计", "方案", "调整", "丰富", "核心", "主要内容")) {
		return agentIntentDesign
	}
	if containsAny(lower, "存储", "上传", "附件", "文件管理", "文件列表", "文件存储") && !containsAny(lower, "导出", "发给我", "返回文件") {
		return agentIntentPreview
	}
	if containsAny(lower, "审计", "日志", "登录记录", "操作记录", "运行记录") && !containsAny(lower, "导出", "发给我", "返回文件") {
		return agentIntentPreview
	}
	if containsAny(lower, "智能体会话", "智能体运行", "工具调用", "工具轨迹", "agent_runs", "agent sessions", "agent tool") && !containsAny(lower, "导出", "发给我", "返回文件") {
		return agentIntentPreview
	}
	if containsAny(lower, "导出", "表格", "文件", "下载", "发给我", "给我文件", "返回文件", "恢复文件", "excel", "xlsx", "csv", "json") {
		return agentIntentExport
	}
	if strings.Contains(message, "你") && containsAny(lower, "角色", "身份", "能做什么", "职责") {
		return agentIntentAdmin
	}
	if containsAny(lower, "体检", "巡检", "检查", "诊断", "状态", "下一步", "建议", "风险") {
		return agentIntentHealth
	}
	if containsAny(lower, "字段", "结构", "schema", "describe", "columns") {
		return agentIntentDescribe
	}
	if containsAny(lower, "管理员", "账号", "账户", "用户", "权限", "角色", "菜单", "导航", "admin", "user", "users", "role", "permission", "menu") {
		return agentIntentUserAccess
	}
	if containsAny(lower, "列出", "有哪些", "表", "tables", "table", "数据库") {
		return agentIntentTableCatalog
	}
	if containsAny(lower, "预览", "查看", "数据", "明细", "rows", "preview") {
		return agentIntentPreview
	}
	return agentIntentAdmin
}

func buildAgentRun(state installState, message string, intent agentIntent, results []agentToolResult) agentRun {
	mode := string(intent)
	run := agentRun{
		ID:   "run-" + strconv.FormatInt(time.Now().UnixNano(), 36),
		Mode: mode,
		Goal: summarizeAgentGoal(message, intent),
		Metadata: map[string]string{
			"database": state.Database.DisplayName(),
			"ai_model": state.AI.DisplayModel(),
		},
	}
	run.Plan = buildAgentPlan(intent)
	run.Trace = buildAgentTrace(results)
	run.Insights = buildAgentInsights(intent, results)
	run.Suggestions = buildAgentSuggestions(intent, results)
	return run
}

func summarizeAgentGoal(message string, intent agentIntent) string {
	message = strings.TrimSpace(message)
	if message != "" && len([]rune(message)) <= 80 {
		return message
	}
	switch intent {
	case agentIntentDesign:
		return "升级后台智能体构造方案"
	case agentIntentHealth:
		return "检查当前后台智能体与数据源状态"
	case agentIntentQuery:
		return "执行受控只读查询"
	case agentIntentExport:
		return "整理数据并生成表格文件"
	case agentIntentDescribe:
		return "读取数据表字段结构"
	case agentIntentPreview:
		return "预览受控数据表"
	case agentIntentGuardrail:
		return "拦截高风险数据操作"
	case agentIntentUserAccess:
		return "检查后台账号与权限"
	case agentIntentAdmin:
		return "处理后台管理任务"
	default:
		return "识别当前可用数据表"
	}
}

func buildAgentPlan(intent agentIntent) []agentPlanStep {
	switch intent {
	case agentIntentDesign:
		return []agentPlanStep{
			{Title: "理解目标", Detail: "把后台从 CRUD 页面转向任务型智能体工作台。", Status: "done"},
			{Title: "拆解结构", Detail: "规划感知、计划、工具、记忆、审计与导出层。", Status: "done"},
			{Title: "读取现状", Detail: "检查当前模型配置、数据源和已启用工具。", Status: "done"},
			{Title: "生成路线", Detail: "输出可逐步落地的智能体增强方案。", Status: "done"},
		}
	case agentIntentHealth:
		return []agentPlanStep{
			{Title: "收集上下文", Detail: "读取受控表清单、数据源和能力边界。", Status: "done"},
			{Title: "识别短板", Detail: "判断当前后台到强智能体之间缺少的基础设施。", Status: "done"},
			{Title: "给出动作", Detail: "生成下一步接入建议。", Status: "done"},
		}
	case agentIntentGuardrail:
		return []agentPlanStep{
			{Title: "识别风险", Detail: "检测到可能修改数据的操作意图。", Status: "done"},
			{Title: "执行拦截", Detail: "拒绝写入、多语句和危险数据库关键字。", Status: "done"},
			{Title: "保留路径", Detail: "建议改用只读 SELECT 或预览工具。", Status: "done"},
		}
	case agentIntentUserAccess:
		return []agentPlanStep{
			{Title: "定位账号表", Detail: "把管理员、账号、权限类问题映射到后台用户与权限表。", Status: "done"},
			{Title: "读取只读数据", Detail: "查询管理员账号数量、角色和权限边界。", Status: "done"},
			{Title: "直接回答", Detail: "优先给出明确数量，再展示依据。", Status: "done"},
		}
	case agentIntentExport:
		return []agentPlanStep{
			{Title: "识别导出目标", Detail: "从任务中识别需要整理的数据表、字段和筛选条件。", Status: "done"},
			{Title: "读取数据", Detail: "通过只读工具读取匹配数据，不执行写入。", Status: "done"},
			{Title: "生成文件", Detail: "把结果整理为可下载表格文件。", Status: "done"},
		}
	case agentIntentAdmin:
		return []agentPlanStep{
			{Title: "理解后台任务", Detail: "按后台管理员视角判断是否需要工具。", Status: "done"},
			{Title: "避免无关查询", Detail: "当前任务不涉及数据表时不调用数据库工具。", Status: "done"},
			{Title: "给出管理建议", Detail: "直接给出下一步操作建议或可用能力。", Status: "done"},
		}
	default:
		return []agentPlanStep{
			{Title: "理解问题", Detail: "识别用户意图和目标数据范围。", Status: "done"},
			{Title: "选择工具", Detail: "匹配只读数据工具并应用安全边界。", Status: "done"},
			{Title: "执行查询", Detail: "调用后台受控工具读取结果。", Status: "done"},
			{Title: "整理输出", Detail: "返回表格、洞察和下一步建议。", Status: "done"},
		}
	}
}

func buildAgentTrace(results []agentToolResult) []agentTraceItem {
	trace := []agentTraceItem{
		{Title: "任务进入智能体运行器", Detail: "已创建本次后台任务上下文。", Status: "done"},
	}
	if len(results) == 0 {
		trace = append(trace, agentTraceItem{Title: "跳过数据工具", Detail: "当前任务不需要读取数据表或生成文件。", Status: "done"})
		trace = append(trace, agentTraceItem{Title: "生成管理视角回复", Detail: "以后台管理员身份直接回复。", Status: "done"})
		return trace
	}
	for _, result := range results {
		status := "done"
		detail := result.Message
		if !result.OK {
			status = "blocked"
			detail = result.Error
		}
		if detail == "" {
			detail = "工具调用完成。"
		}
		trace = append(trace, agentTraceItem{
			Title:  "调用工具：" + result.Name,
			Detail: detail,
			Tool:   result.Name,
			Status: status,
		})
	}
	trace = append(trace, agentTraceItem{Title: "生成管理视角回复", Detail: "已把工具结果整理为后台可读结论。", Status: "done"})
	return trace
}

func buildAgentInsights(intent agentIntent, results []agentToolResult) []agentInsight {
	insights := make([]agentInsight, 0, 4)
	for _, result := range results {
		if !result.OK {
			insights = append(insights, agentInsight{
				Title:  "安全边界已生效",
				Detail: result.Error,
				Tone:   "warning",
			})
			continue
		}
		switch result.Name {
		case "list_tables":
			insights = append(insights, agentInsight{
				Title:  "数据访问已统一入口",
				Detail: fmt.Sprintf("当前有 %d 张受控表可由智能体读取，后续真实业务库也应通过同一工具层接入。", len(result.Rows)),
				Tone:   "ready",
			})
		case "inspect_agent_design":
			insights = append(insights, agentInsight{
				Title:  "智能体应成为后台主线",
				Detail: "后台入口应该围绕任务、计划、工具轨迹和结果沉淀组织，而不是围绕传统 CRUD 菜单堆叠。",
				Tone:   "ready",
			})
		case "query_readonly":
			insights = append(insights, agentInsight{
				Title:  "查询保持只读",
				Detail: fmt.Sprintf("本次查询返回 %d 行、%d 个字段，未开放写入能力。", len(result.Rows), len(result.Columns)),
				Tone:   "ready",
			})
		case "count_table":
			count := "0"
			if len(result.Rows) > 0 {
				count = result.Rows[0]["count"]
			}
			insights = append(insights, agentInsight{
				Title:  "数量已确认",
				Detail: "`" + result.Table + "` 当前共有 " + count + " 行。",
				Tone:   "ready",
			})
		case "export_table":
			detail := result.Message
			if result.File != nil {
				detail = "已生成文件 `" + result.File.Name + "`，大小 " + strconv.FormatInt(result.File.Size, 10) + " bytes。"
			}
			insights = append(insights, agentInsight{
				Title:  "文件已生成",
				Detail: detail,
				Tone:   "ready",
			})
		case "preview_table":
			insights = append(insights, agentInsight{
				Title:  "预览结果可继续追问",
				Detail: "`" + result.Table + "` 已返回样例数据，可以继续要求筛选、解释或生成导出任务。",
				Tone:   "info",
			})
		case "describe_table":
			insights = append(insights, agentInsight{
				Title:  "字段结构可用于自动生成查询",
				Detail: "`" + result.Table + "` 的字段已识别，下一步可让智能体基于字段生成只读 SELECT。",
				Tone:   "info",
			})
		}
	}
	if intent == agentIntentHealth {
		insights = append(insights, agentInsight{
			Title:  "下一块基础设施",
			Detail: "建议优先接真实数据库 Schema 探测，再接导出 Excel 工具，这两项会直接提升后台可用性。",
			Tone:   "warning",
		})
	}
	if intent == agentIntentAdmin && len(results) == 0 {
		insights = append(insights, agentInsight{
			Title:  "未调用数据工具",
			Detail: "当前任务按后台管理员助理直接处理，没有读取数据库或数据表。",
			Tone:   "info",
		})
	}
	if len(insights) == 0 {
		insights = append(insights, agentInsight{
			Title:  "等待更多上下文",
			Detail: "可以继续给出数据表、字段或业务目标，智能体会补齐计划并调用工具。",
			Tone:   "info",
		})
	}
	return insights
}

func buildAgentSuggestions(intent agentIntent, results []agentToolResult) []agentSuggestion {
	switch intent {
	case agentIntentDesign:
		return []agentSuggestion{
			{Label: "接入真实 Schema", Prompt: "设计真实数据库 Schema 探测工具，并说明需要哪些 Go 接口"},
			{Label: "导出工具", Prompt: "把查询结果导出 Excel 的工具方案做出来"},
			{Label: "迁移计划", Prompt: "基于 legacy-hyperf 制定下一阶段智能体迁移计划"},
		}
	case agentIntentHealth:
		return []agentSuggestion{
			{Label: "查看数据源", Prompt: "预览数据源配置"},
			{Label: "能力清单", Prompt: "预览智能体能力"},
			{Label: "智能体方案", Prompt: "给出 Moyi Admin 智能体构造方案"},
		}
	case agentIntentGuardrail:
		return []agentSuggestion{
			{Label: "只读查询", Prompt: "select * from install_state limit 1"},
			{Label: "字段结构", Prompt: "查看系统初始化状态的字段结构"},
		}
	case agentIntentUserAccess:
		return []agentSuggestion{
			{Label: "账号明细", Prompt: "预览后台管理员账号"},
			{Label: "角色权限", Prompt: "预览后台权限"},
			{Label: "菜单入口", Prompt: "预览后台菜单"},
			{Label: "账号数量", Prompt: "我们后台有几个管理员账号？"},
		}
	case agentIntentExport:
		return []agentSuggestion{
			{Label: "导出账号", Prompt: "把管理员账号的账号、角色、状态整理成 XLSX 文件发给我"},
			{Label: "导出权限", Prompt: "把后台权限整理成 CSV 文件发给我"},
			{Label: "导出数据源", Prompt: "把数据源配置整理成 JSON 文件发给我"},
		}
	case agentIntentAdmin:
		return []agentSuggestion{
			{Label: "系统体检", Prompt: "对当前后台做一次系统体检并给出下一步建议"},
			{Label: "导出账号", Prompt: "把管理员账号整理成表格文件发给我"},
			{Label: "查询表", Prompt: "列出当前可查询的数据表"},
		}
	default:
		suggestions := []agentSuggestion{
			{Label: "系统体检", Prompt: "对当前后台做一次系统体检并给出下一步建议"},
			{Label: "智能体方案", Prompt: "给出 Moyi Admin 智能体构造方案"},
		}
		if len(results) > 0 && results[0].Table != "" {
			suggestions = append(suggestions, agentSuggestion{
				Label:  "继续预览",
				Prompt: "预览 " + results[0].Table,
			})
		} else {
			suggestions = append(suggestions, agentSuggestion{Label: "预览数据源", Prompt: "预览数据源配置"})
		}
		return suggestions
	}
}

func planAndRunAgentTools(tools tableToolProvider, message string) []agentToolResult {
	message = strings.TrimSpace(message)
	lower := strings.ToLower(message)
	intent := determineAgentIntent(message)

	if sql, ok := extractAgentSQL(message); ok {
		return []agentToolResult{tools.runReadOnlyQuery(sql)}
	}
	if containsAny(lower, "delete", "update", "insert", "drop", "alter", "truncate", "create", "replace", "attach", "detach", "pragma", "vacuum") {
		return []agentToolResult{{
			Name:  "query_readonly",
			OK:    false,
			SQL:   message,
			Error: "后台智能体只允许执行只读 SELECT 查询，已拒绝可能修改数据的语句。",
		}}
	}
	if intent == agentIntentAdmin {
		return nil
	}
	if intent == agentIntentDesign {
		return []agentToolResult{tools.inspectAgentDesign()}
	}
	if intent == agentIntentExport {
		return []agentToolResult{tools.exportTables(message)}
	}
	if intent == agentIntentHealth {
		return []agentToolResult{
			tools.listTables(),
			tools.previewTable("data_sources", 10),
			tools.previewTable("ai_capabilities", 10),
		}
	}
	if intent == agentIntentUserAccess {
		results := []agentToolResult{
			tools.countTable("admin_users"),
			tools.previewTable("admin_users", 20),
			tools.previewTable("admin_roles", 20),
		}
		if containsAny(lower, "权限", "permission", "permissions") {
			results = append(results, tools.previewTable("admin_permissions", 20))
		}
		if containsAny(lower, "菜单", "导航", "menu", "menus") {
			results = append(results, tools.previewTable("admin_menus", 20))
		}
		return results
	}
	if intent == agentIntentPreview {
		return []agentToolResult{tools.previewTable(inferAgentTableName(message), 10)}
	}
	if containsAny(lower, "字段", "结构", "schema", "describe", "columns") {
		return []agentToolResult{tools.describeTable(inferAgentTableName(message))}
	}
	if containsAny(lower, "列出", "有哪些", "表", "tables", "table", "数据库") {
		return []agentToolResult{tools.listTables()}
	}
	if containsAny(lower, "预览", "查看", "数据", "明细", "rows", "preview") {
		return []agentToolResult{tools.previewTable(inferAgentTableName(message), 10)}
	}
	return []agentToolResult{tools.listTables()}
}

func composeLocalAgentReply(message string, run agentRun, results []agentToolResult) string {
	successful := make([]agentToolResult, 0, len(results))
	failed := make([]string, 0)
	for _, result := range results {
		if result.OK {
			successful = append(successful, result)
			continue
		}
		if result.Error != "" {
			failed = append(failed, result.Error)
		}
	}

	if len(failed) > 0 && len(successful) == 0 {
		return "这次请求没有执行成功：" + strings.Join(failed, "；")
	}
	if run.Mode == string(agentIntentAdmin) {
		return "我是后台管理员助理。这个任务没有涉及具体数据表查询，所以不会调用数据库工具。你可以让我做后台巡检、解释功能、规划迁移、查询数据、按条件筛选并导出表格文件。"
	}
	if len(successful) == 0 {
		return "我已经收到你的问题，但当前没有找到可执行的数据工具。你可以试试：列出数据表、查看系统初始化状态字段、预览数据源配置，或输入只读 SELECT。"
	}

	if run.Mode == string(agentIntentDesign) {
		return "我会把后台的核心体验收敛成智能体工作台：先理解任务，再制定计划，然后通过受控工具读取数据，最后给出洞察、表格结果和下一步动作。当前已经具备计划、工具轨迹、只读查询和模型兜底；下一步应该接入真实数据库 Schema、导出工具和审计记忆。"
	}
	if run.Mode == string(agentIntentHealth) {
		return "系统体检完成：当前智能体能识别受控数据表、检查数据源状态，并展示已启用的工具边界。重点短板是业务库驱动、导出任务和长期记忆还没有接上。"
	}
	if run.Mode == string(agentIntentUserAccess) {
		count := ""
		username := ""
		role := ""
		for _, result := range successful {
			if result.Name == "count_table" && len(result.Rows) > 0 {
				count = result.Rows[0]["count"]
			}
			if result.Table == "admin_users" && len(result.Rows) > 0 {
				username = result.Rows[0]["username"]
				role = result.Rows[0]["role"]
			}
		}
		if count == "" {
			count = "0"
		}
		if username != "" {
			return fmt.Sprintf("当前后台管理员账号共 %s 个。已识别内置管理员 `%s`，角色为 %s。智能体现在默认拥有所有已登记数据表的只读读取权限，但仍会拒绝写入和危险 SQL。", count, username, role)
		}
		return fmt.Sprintf("当前后台管理员账号共 %s 个。智能体已按全表只读权限检查后台账号表。", count)
	}
	if run.Mode == string(agentIntentExport) {
		for _, result := range successful {
			if result.File != nil {
				return fmt.Sprintf("已按后台只读权限整理数据并生成表格文件 `%s`，可以直接下载。", result.File.Name)
			}
		}
		return "已读取数据，但暂时没有生成可下载文件。"
	}

	result := successful[0]
	switch result.Name {
	case "list_tables":
		names := make([]string, 0, len(result.Rows))
		for _, row := range result.Rows {
			if name := row["name"]; name != "" {
				displayName := row["display_name"]
				if displayName != "" {
					names = append(names, displayName+"("+name+")")
				} else {
					names = append(names, name)
				}
			}
		}
		return fmt.Sprintf("我已列出当前可查询的受控数据表，共 %d 张，包含中文名称、内部名和表注释：%s。", len(names), strings.Join(names, "、"))
	case "describe_table":
		return fmt.Sprintf("`%s` 的字段结构已读取，共 %d 个字段。", result.Table, len(result.Rows))
	case "preview_table":
		return fmt.Sprintf("已预览 `%s`，返回 %d 行数据。", result.Table, len(result.Rows))
	case "query_readonly":
		return fmt.Sprintf("只读查询已执行，返回 %d 行。", len(result.Rows))
	case "count_table":
		if len(result.Rows) > 0 {
			return fmt.Sprintf("`%s` 当前共有 %s 行。", result.Table, result.Rows[0]["count"])
		}
		return "`" + result.Table + "` 当前没有数据。"
	default:
		return "已完成这次后台数据工具调用。"
	}
}

func callConfiguredAgentModel(ctx context.Context, ai aiConfig, payload agentChatRequest, run agentRun, results []agentToolResult) (string, bool, error) {
	ai = ai.sanitized()
	if ai.IsDisabled() {
		return "", false, nil
	}
	if err := validateAIConfig(ai); err != nil {
		return "", false, err
	}

	endpoint, err := ai.chatCompletionsURL()
	if err != nil {
		return "", false, err
	}

	toolContext, _ := json.Marshal(results)
	runContext, _ := json.Marshal(run)
	messages := []map[string]string{
		{
			"role":    "system",
			"content": "你是 Moyi Admin 后台管理员智能体，不是单纯的数据库查询机器人。工作方式参考 Codex：先理解目标，判断是否需要工具，解释计划，调用受控工具，再给出可执行结论。只有任务涉及数据、表、统计、筛选、导出或账号权限时才使用数据工具；普通后台管理咨询不要查询数据库。默认拥有所有已登记数据表和虚拟表的只读读取权限；遇到账号、权限、数量、导出问题要主动查询或生成文件，不要反问用户是否启用模块。只能基于系统提供的 run 与工具结果回答；不要编造数据库内容；不要泄露密钥、密码哈希、盐值或会话信息。回答要简洁、像后台管理里的执行助手。",
		},
	}
	for _, history := range tailAgentHistory(payload.History, 6) {
		role := strings.TrimSpace(history.Role)
		if role != "user" && role != "assistant" {
			continue
		}
		content := strings.TrimSpace(history.Content)
		if content == "" {
			continue
		}
		messages = append(messages, map[string]string{"role": role, "content": content})
	}
	messages = append(messages,
		map[string]string{"role": "user", "content": payload.Message},
		map[string]string{"role": "system", "content": "本次智能体运行 JSON：" + string(runContext)},
		map[string]string{"role": "system", "content": "本次工具结果 JSON：" + string(toolContext)},
	)

	body, _ := json.Marshal(map[string]any{
		"model":       ai.ChatModel,
		"messages":    messages,
		"temperature": 0.2,
		"max_tokens":  900,
	})
	agentCtx, cancel := context.WithTimeout(ctx, defaultAgentTimeout)
	defer cancel()
	req, err := http.NewRequestWithContext(agentCtx, http.MethodPost, endpoint, bytes.NewReader(body))
	if err != nil {
		return "", false, err
	}
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("Authorization", "Bearer "+ai.APIKey)

	resp, err := agentHTTPClient.Do(req)
	if err != nil {
		return "", false, err
	}
	defer resp.Body.Close()
	respBody, _ := io.ReadAll(io.LimitReader(resp.Body, 64*1024))
	if resp.StatusCode < 200 || resp.StatusCode >= 300 {
		return "", false, fmt.Errorf("model returned %d", resp.StatusCode)
	}

	var parsed struct {
		Choices []struct {
			Message struct {
				Content string `json:"content"`
			} `json:"message"`
		} `json:"choices"`
	}
	if err := json.Unmarshal(respBody, &parsed); err != nil {
		return "", false, err
	}
	if len(parsed.Choices) == 0 {
		return "", false, errors.New("model returned no choices")
	}
	return strings.TrimSpace(parsed.Choices[0].Message.Content), true, nil
}

func tailAgentHistory(history []agentChatMessage, limit int) []agentChatMessage {
	if limit <= 0 || len(history) <= limit {
		return history
	}
	return history[len(history)-limit:]
}

func agentTableDefinitions() []agentTableDefinition {
	return []agentTableDefinition{
		{
			Name:        "install_state",
			Type:        "metadata_table",
			DisplayName: "系统初始化状态",
			Description: "当前系统初始化状态、安全入口、数据库与 AI 概览",
			Aliases:     []string{"安装状态", "初始化信息", "站点信息", "后台入口", "随机后台入口", "元数据数据库", "AI 配置", "AI配置"},
		},
		{
			Name:        "admin_settings",
			Type:        "derived_view",
			DisplayName: "后台配置",
			Description: "后台运行参数与基础配置",
			Aliases:     []string{"系统配置", "配置项", "设置", "后台设置", "运行参数"},
		},
		{
			Name:        "storage_settings",
			Type:        "derived_view",
			DisplayName: "存储设置",
			Description: "后台上传、本地存储、公开访问前缀、导出文件保留策略",
			Aliases:     []string{"存储配置", "上传设置", "文件存储", "本地存储", "导出保留", "附件设置"},
		},
		{
			Name:        "upload_files",
			Type:        "filesystem_view",
			DisplayName: "文件管理",
			Description: "后台本地文件列表、路径、类型、大小和更新时间",
			Aliases:     []string{"文件列表", "文件管理", "上传文件", "附件管理", "附件列表", "本地文件"},
		},
		{
			Name:        "audit_events",
			Type:        "metadata_table",
			DisplayName: "审计日志",
			Description: "后台登录、设置、文件、智能体等关键操作的审计事件",
			Aliases:     []string{"审计日志", "运行日志", "操作日志", "登录日志", "后台日志", "日志事件", "审计事件"},
		},
		{
			Name:        "admin_users",
			Type:        "metadata_table",
			DisplayName: "后台管理员账号",
			Description: "后台管理员账号、显示名称、角色和启用状态",
			Aliases:     []string{"管理员账号", "后台账号", "账号", "账户", "用户", "用户管理", "管理员", "超级管理员账号"},
		},
		{
			Name:        "admin_roles",
			Type:        "metadata_table",
			DisplayName: "后台角色",
			Description: "后台角色、作用范围和角色状态",
			Aliases:     []string{"角色管理", "角色", "超级管理员角色", "权限角色"},
		},
		{
			Name:        "admin_menus",
			Type:        "metadata_table",
			DisplayName: "后台菜单",
			Description: "后台菜单入口、路径和启用状态",
			Aliases:     []string{"菜单管理", "菜单", "后台菜单", "导航", "导航菜单", "后台导航"},
		},
		{
			Name:        "admin_permissions",
			Type:        "metadata_table",
			DisplayName: "后台权限",
			Description: "智能体与后台权限边界、只读能力和敏感数据保护规则",
			Aliases:     []string{"权限管理", "权限", "访问控制", "智能体权限", "只读权限", "权限边界"},
		},
		{
			Name:        "data_sources",
			Type:        "metadata_table",
			DisplayName: "数据源配置",
			Description: "已登记的数据源、元数据库连接和旧系统迁移参考位置",
			Aliases:     []string{"数据源", "数据源巡检", "迁移参考", "旧系统比对", "legacy-hyperf", "legacy hyperf"},
		},
		{
			Name:        "ai_capabilities",
			Type:        "computed_view",
			DisplayName: "智能体能力",
			Description: "后台智能体可调用工具、执行边界和当前接入状态",
			Aliases:     []string{"AI 能力", "AI能力", "工具能力", "能力清单", "工具清单", "智能体工具"},
		},
		{
			Name:        "agent_sessions",
			Type:        "metadata_table",
			DisplayName: "智能体会话",
			Description: "后台智能体会话、操作者、最后消息和运行次数",
			Aliases:     []string{"智能体会话", "AI 会话", "会话记录", "对话会话", "agent sessions"},
		},
		{
			Name:        "agent_runs",
			Type:        "metadata_table",
			DisplayName: "智能体运行记录",
			Description: "每次智能体运行的模式、目标、消息、模型使用和耗时",
			Aliases:     []string{"智能体运行", "运行记录", "AI 运行", "任务记录", "agent runs", "run history"},
		},
		{
			Name:        "agent_tool_results",
			Type:        "metadata_table",
			DisplayName: "智能体工具结果",
			Description: "每次智能体工具调用、目标表、SQL、行数、文件结果和错误信息",
			Aliases:     []string{"工具结果", "工具调用", "工具轨迹", "AI 工具结果", "agent tool results"},
		},
		{
			Name:        "agent_blueprint",
			Type:        "computed_view",
			DisplayName: "智能体构造方案",
			Description: "Moyi Admin 智能体架构分层、建设状态和下一步动作",
			Aliases:     []string{"智能体方案", "智能体架构", "建设方案", "蓝图", "构造方案", "Agent 方案"},
		},
	}
}

func agentTableDefinitionByName(name string) (agentTableDefinition, bool) {
	name = normalizeAgentTableName(name)
	for _, definition := range agentTableDefinitions() {
		if definition.Name == name {
			return definition, true
		}
	}
	return agentTableDefinition{}, false
}

func (p tableToolProvider) listTables() agentToolResult {
	definitions := agentTableDefinitions()
	rows := make([]map[string]string, 0, len(definitions))
	for _, definition := range definitions {
		rows = append(rows, map[string]string{
			"name":         definition.Name,
			"display_name": definition.DisplayName,
			"type":         definition.Type,
			"comment":      definition.Description,
		})
	}
	return agentToolResult{
		Name:    "list_tables",
		OK:      true,
		Message: "已读取当前所有已登记只读数据表列表，包含内部名、中文名称和表注释。",
		Columns: []string{"name", "display_name", "type", "comment"},
		Rows:    rows,
	}
}

func (p tableToolProvider) countTable(table string) agentToolResult {
	table = normalizeAgentTableName(table)
	if _, ok := p.tableColumns(table); !ok {
		return unknownAgentTableResult("count_table", table)
	}
	rows := p.tableRows(table)
	return agentToolResult{
		Name:    "count_table",
		OK:      true,
		Message: "`" + table + "` 数量统计完成。",
		Table:   table,
		Columns: []string{"table", "count"},
		Rows: []map[string]string{{
			"table": table,
			"count": strconv.Itoa(len(rows)),
		}},
	}
}

func (p tableToolProvider) exportTables(message string) agentToolResult {
	tables := inferAgentExportTables(message)
	if len(tables) == 0 {
		tables = []string{inferAgentTableName(message)}
	}
	if len(tables) == 0 || tables[0] == "" {
		tables = []string{"admin_users"}
	}

	limit := inferAgentLimit(message, 200)
	format := inferAgentExportFormat(message)
	now := time.Now()
	fileName := "moyi-agent-export-" + now.Format("20060102-150405") + "-" + strconv.FormatInt(now.UnixNano()%1_000_000, 10) + "." + format.Extension
	if p.exportDir == "" {
		return agentToolResult{
			Name:  "export_table",
			OK:    false,
			Error: "导出目录未配置。",
		}
	}
	if err := os.MkdirAll(p.exportDir, 0o755); err != nil {
		return agentToolResult{
			Name:  "export_table",
			OK:    false,
			Error: "创建导出目录失败：" + err.Error(),
		}
	}

	filePath := filepath.Join(p.exportDir, fileName)
	exportData := agentExportData{
		GeneratedAt: now.Format(time.RFC3339),
		Sheets:      make([]agentExportSheet, 0, len(tables)),
	}
	exportedTables := make([]string, 0, len(tables))
	for _, table := range tables {
		table = normalizeAgentTableName(table)
		columns, ok := p.exportColumnsForMessage(table, message)
		if !ok {
			return unknownAgentTableResult("export_table", table)
		}
		rows := p.filterRowsForMessage(table, p.tableRows(table), message)
		if len(rows) > limit {
			rows = rows[:limit]
		}
		sheetRows := make([]map[string]string, 0, len(rows))
		for _, row := range rows {
			exportRow := make(map[string]string, len(columns))
			for _, column := range columns {
				exportRow[column] = row[column]
			}
			sheetRows = append(sheetRows, exportRow)
		}
		exportData.Sheets = append(exportData.Sheets, agentExportSheet{
			Table:   table,
			Columns: columns,
			Rows:    sheetRows,
		})
		exportData.TotalRows += len(sheetRows)
		exportedTables = append(exportedTables, table)
	}

	if err := writeAgentExportFile(filePath, format, exportData); err != nil {
		return agentExportWriteError(err)
	}
	info, err := os.Stat(filePath)
	if err != nil {
		return agentToolResult{Name: "export_table", OK: false, Error: "读取导出文件失败：" + err.Error()}
	}

	fileResult := agentFileResult{
		Name:        fileName,
		URL:         p.downloadBasePath + "/" + fileName,
		MIME:        format.MIME,
		Size:        info.Size(),
		Description: "导出格式：" + format.Label + "；导出表：" + strings.Join(exportedTables, "、"),
	}
	return agentToolResult{
		Name:    "export_table",
		OK:      true,
		Message: fmt.Sprintf("已导出 %d 张表、%d 行数据，格式为 %s。", len(exportedTables), exportData.TotalRows, format.Label),
		Table:   strings.Join(exportedTables, ","),
		Columns: []string{"file", "format", "tables", "rows"},
		Rows: []map[string]string{{
			"file":   fileName,
			"format": format.Label,
			"tables": strings.Join(exportedTables, ","),
			"rows":   strconv.Itoa(exportData.TotalRows),
		}},
		File: &fileResult,
	}
}

func agentExportWriteError(err error) agentToolResult {
	return agentToolResult{Name: "export_table", OK: false, Error: "写入导出文件失败：" + err.Error()}
}

func inferAgentExportFormat(message string) agentExportFormat {
	lower := strings.ToLower(message)
	if containsAny(lower, "xlsx", "excel", "xls") || strings.Contains(message, "电子表格") {
		return agentExportFormat{
			Extension: "xlsx",
			MIME:      "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
			Label:     "XLSX",
		}
	}
	if strings.Contains(lower, "json") {
		return agentExportFormat{
			Extension: "json",
			MIME:      "application/json; charset=utf-8",
			Label:     "JSON",
		}
	}
	return agentExportFormat{
		Extension: "csv",
		MIME:      "text/csv; charset=utf-8",
		Label:     "CSV",
	}
}

func agentExportContentType(name string) (string, bool) {
	switch strings.ToLower(filepath.Ext(name)) {
	case ".csv":
		return "text/csv; charset=utf-8", true
	case ".xlsx":
		return "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet", true
	case ".json":
		return "application/json; charset=utf-8", true
	default:
		return "", false
	}
}

func writeAgentExportFile(filePath string, format agentExportFormat, data agentExportData) error {
	switch format.Extension {
	case "xlsx":
		return writeAgentXLSX(filePath, data)
	case "json":
		return writeAgentJSON(filePath, data)
	default:
		return writeAgentCSV(filePath, data)
	}
}

func writeAgentCSV(filePath string, data agentExportData) (err error) {
	file, err := os.OpenFile(filePath, os.O_CREATE|os.O_WRONLY|os.O_TRUNC, 0o600)
	if err != nil {
		return err
	}
	defer func() {
		if closeErr := file.Close(); err == nil {
			err = closeErr
		}
	}()

	if _, err := file.Write([]byte("\xEF\xBB\xBF")); err != nil {
		return err
	}
	writer := csv.NewWriter(file)
	for sheetIndex, sheet := range data.Sheets {
		if sheetIndex > 0 {
			if err := writer.Write([]string{}); err != nil {
				return err
			}
		}
		header := append([]string{"table"}, sheet.Columns...)
		if err := writer.Write(header); err != nil {
			return err
		}
		for _, row := range sheet.Rows {
			record := make([]string, 0, len(sheet.Columns)+1)
			record = append(record, sheet.Table)
			for _, column := range sheet.Columns {
				record = append(record, row[column])
			}
			if err := writer.Write(record); err != nil {
				return err
			}
		}
	}
	writer.Flush()
	return writer.Error()
}

func writeAgentJSON(filePath string, data agentExportData) (err error) {
	file, err := os.OpenFile(filePath, os.O_CREATE|os.O_WRONLY|os.O_TRUNC, 0o600)
	if err != nil {
		return err
	}
	defer func() {
		if closeErr := file.Close(); err == nil {
			err = closeErr
		}
	}()

	encoder := json.NewEncoder(file)
	encoder.SetEscapeHTML(false)
	encoder.SetIndent("", "  ")
	return encoder.Encode(data)
}

func writeAgentXLSX(filePath string, data agentExportData) (err error) {
	sheets := data.Sheets
	if len(sheets) == 0 {
		sheets = []agentExportSheet{{Table: "Sheet1"}}
	}

	file, err := os.OpenFile(filePath, os.O_CREATE|os.O_WRONLY|os.O_TRUNC, 0o600)
	if err != nil {
		return err
	}
	defer func() {
		if closeErr := file.Close(); err == nil {
			err = closeErr
		}
	}()

	archive := zip.NewWriter(file)
	add := func(name string, content string) error {
		part, err := archive.Create(name)
		if err != nil {
			return err
		}
		_, err = io.WriteString(part, content)
		return err
	}

	if err := add("[Content_Types].xml", agentXLSXContentTypes(len(sheets))); err != nil {
		return err
	}
	if err := add("_rels/.rels", agentXLSXRootRels()); err != nil {
		return err
	}
	if err := add("docProps/core.xml", agentXLSXCore(data.GeneratedAt)); err != nil {
		return err
	}
	if err := add("docProps/app.xml", agentXLSXApp(len(sheets))); err != nil {
		return err
	}
	if err := add("xl/workbook.xml", agentXLSXWorkbook(sheets)); err != nil {
		return err
	}
	if err := add("xl/_rels/workbook.xml.rels", agentXLSXWorkbookRels(len(sheets))); err != nil {
		return err
	}
	if err := add("xl/styles.xml", agentXLSXStyles()); err != nil {
		return err
	}
	for index, sheet := range sheets {
		if err := add("xl/worksheets/sheet"+strconv.Itoa(index+1)+".xml", agentXLSXWorksheet(sheet)); err != nil {
			return err
		}
	}
	return archive.Close()
}

func agentXLSXContentTypes(sheetCount int) string {
	var sheetOverrides strings.Builder
	for index := 1; index <= sheetCount; index++ {
		fmt.Fprintf(&sheetOverrides, `<Override PartName="/xl/worksheets/sheet%d.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>`, index)
	}
	return `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>` +
		`<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">` +
		`<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>` +
		`<Default Extension="xml" ContentType="application/xml"/>` +
		`<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>` +
		`<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>` +
		`<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>` +
		`<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>` +
		sheetOverrides.String() +
		`</Types>`
}

func agentXLSXRootRels() string {
	return `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>` +
		`<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">` +
		`<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>` +
		`<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>` +
		`<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>` +
		`</Relationships>`
}

func agentXLSXCore(generatedAt string) string {
	generatedAt = strings.TrimSpace(generatedAt)
	if generatedAt == "" {
		generatedAt = time.Now().Format(time.RFC3339)
	}
	return `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>` +
		`<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">` +
		`<dc:creator>Moyi Admin AI Agent</dc:creator>` +
		`<cp:lastModifiedBy>Moyi Admin AI Agent</cp:lastModifiedBy>` +
		`<dcterms:created xsi:type="dcterms:W3CDTF">` + agentXMLEscape(generatedAt) + `</dcterms:created>` +
		`<dcterms:modified xsi:type="dcterms:W3CDTF">` + agentXMLEscape(generatedAt) + `</dcterms:modified>` +
		`</cp:coreProperties>`
}

func agentXLSXApp(sheetCount int) string {
	return `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>` +
		`<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">` +
		`<Application>Moyi Admin</Application>` +
		`<DocSecurity>0</DocSecurity>` +
		`<ScaleCrop>false</ScaleCrop>` +
		`<HeadingPairs><vt:vector size="2" baseType="variant"><vt:variant><vt:lpstr>Worksheets</vt:lpstr></vt:variant><vt:variant><vt:i4>` + strconv.Itoa(sheetCount) + `</vt:i4></vt:variant></vt:vector></HeadingPairs>` +
		`</Properties>`
}

func agentXLSXWorkbook(sheets []agentExportSheet) string {
	var sheetXML strings.Builder
	for index, sheet := range sheets {
		fmt.Fprintf(&sheetXML, `<sheet name="%s" sheetId="%d" r:id="rId%d"/>`, agentXMLEscape(agentXLSXSheetName(sheet.Table, index)), index+1, index+1)
	}
	return `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>` +
		`<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">` +
		`<sheets>` + sheetXML.String() + `</sheets>` +
		`</workbook>`
}

func agentXLSXWorkbookRels(sheetCount int) string {
	var rels strings.Builder
	rels.WriteString(`<?xml version="1.0" encoding="UTF-8" standalone="yes"?>`)
	rels.WriteString(`<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">`)
	for index := 1; index <= sheetCount; index++ {
		fmt.Fprintf(&rels, `<Relationship Id="rId%d" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet%d.xml"/>`, index, index)
	}
	fmt.Fprintf(&rels, `<Relationship Id="rId%d" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>`, sheetCount+1)
	rels.WriteString(`</Relationships>`)
	return rels.String()
}

func agentXLSXStyles() string {
	return `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>` +
		`<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">` +
		`<fonts count="1"><font><sz val="11"/><name val="Arial"/></font></fonts>` +
		`<fills count="1"><fill><patternFill patternType="none"/></fill></fills>` +
		`<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>` +
		`<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>` +
		`<cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>` +
		`<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>` +
		`</styleSheet>`
}

func agentXLSXWorksheet(sheet agentExportSheet) string {
	var body strings.Builder
	body.WriteString(`<?xml version="1.0" encoding="UTF-8" standalone="yes"?>`)
	body.WriteString(`<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">`)
	body.WriteString(`<sheetData>`)

	header := append([]string{"table"}, sheet.Columns...)
	agentXLSXRow(&body, 1, header)
	for rowIndex, row := range sheet.Rows {
		values := make([]string, 0, len(sheet.Columns)+1)
		values = append(values, sheet.Table)
		for _, column := range sheet.Columns {
			values = append(values, row[column])
		}
		agentXLSXRow(&body, rowIndex+2, values)
	}

	body.WriteString(`</sheetData>`)
	body.WriteString(`</worksheet>`)
	return body.String()
}

func agentXLSXRow(body *strings.Builder, rowIndex int, values []string) {
	fmt.Fprintf(body, `<row r="%d">`, rowIndex)
	for columnIndex, value := range values {
		cellRef := agentXLSXColumnName(columnIndex+1) + strconv.Itoa(rowIndex)
		space := ""
		if strings.TrimSpace(value) != value {
			space = ` xml:space="preserve"`
		}
		fmt.Fprintf(body, `<c r="%s" t="inlineStr"><is><t%s>%s</t></is></c>`, cellRef, space, agentXMLEscape(value))
	}
	body.WriteString(`</row>`)
}

func agentXLSXColumnName(index int) string {
	if index <= 0 {
		return "A"
	}
	name := ""
	for index > 0 {
		index--
		name = string(rune('A'+index%26)) + name
		index /= 26
	}
	return name
}

func agentXLSXSheetName(table string, index int) string {
	name := strings.TrimSpace(table)
	if name == "" {
		name = "Sheet" + strconv.Itoa(index+1)
	}
	name = strings.NewReplacer("[", "_", "]", "_", ":", "_", "*", "_", "?", "_", "/", "_", "\\", "_").Replace(name)
	if index > 0 {
		name = strconv.Itoa(index+1) + "_" + name
	}
	if utf8.RuneCountInString(name) > 31 {
		runes := []rune(name)
		name = string(runes[:31])
	}
	return name
}

func agentXMLEscape(value string) string {
	clean := strings.Map(func(r rune) rune {
		if r == '\t' || r == '\n' || r == '\r' || r >= 0x20 {
			return r
		}
		return -1
	}, value)
	return strings.NewReplacer(
		"&", "&amp;",
		"<", "&lt;",
		">", "&gt;",
		`"`, "&quot;",
		"'", "&apos;",
	).Replace(clean)
}

func (p tableToolProvider) inspectAgentDesign() agentToolResult {
	rows := p.tableRows("agent_blueprint")
	return agentToolResult{
		Name:    "inspect_agent_design",
		OK:      true,
		Message: "已生成当前后台智能体构造方案。",
		Table:   "agent_blueprint",
		Columns: []string{"layer", "role", "status", "next_action"},
		Rows:    rows,
	}
}

func (p tableToolProvider) describeTable(table string) agentToolResult {
	table = normalizeAgentTableName(table)
	columns, ok := p.tableColumns(table)
	if !ok {
		return unknownAgentTableResult("describe_table", table)
	}

	rows := make([]map[string]string, 0, len(columns))
	for _, column := range columns {
		rows = append(rows, map[string]string{
			"name":        column.Name,
			"type":        column.Type,
			"description": column.Description,
		})
	}
	return agentToolResult{
		Name:    "describe_table",
		OK:      true,
		Message: "已读取 `" + table + "` 字段结构。",
		Table:   table,
		Columns: []string{"name", "type", "description"},
		Rows:    rows,
	}
}

func (p tableToolProvider) previewTable(table string, limit int) agentToolResult {
	table = normalizeAgentTableName(table)
	columns, ok := p.tableColumns(table)
	if !ok {
		return unknownAgentTableResult("preview_table", table)
	}
	if limit <= 0 || limit > 50 {
		limit = 10
	}

	rows := p.tableRows(table)
	if len(rows) > limit {
		rows = rows[:limit]
	}
	columnNames := make([]string, 0, len(columns))
	for _, column := range columns {
		columnNames = append(columnNames, column.Name)
	}

	return agentToolResult{
		Name:    "preview_table",
		OK:      true,
		Message: "已预览 `" + table + "`。",
		Table:   table,
		Columns: columnNames,
		Rows:    rows,
	}
}

func (p tableToolProvider) runReadOnlyQuery(sql string) agentToolResult {
	sql = strings.TrimSpace(sql)
	result := agentToolResult{
		Name: "query_readonly",
		SQL:  sql,
	}
	if err := validateAgentReadOnlySQL(sql); err != nil {
		result.OK = false
		result.Error = err.Error()
		return result
	}

	matches := simpleSelectPattern.FindStringSubmatch(sql)
	if len(matches) != 4 {
		result.OK = false
		result.Error = "当前阶段只支持形如 `select * from table limit 10` 的单表只读查询。"
		return result
	}

	rawColumns := strings.TrimSpace(matches[1])
	table := normalizeAgentTableName(matches[2])
	limit := 10
	if matches[3] != "" {
		parsedLimit, err := strconv.Atoi(matches[3])
		if err != nil || parsedLimit <= 0 {
			result.OK = false
			result.Error = "LIMIT 必须是大于 0 的数字。"
			return result
		}
		if parsedLimit > 50 {
			parsedLimit = 50
		}
		limit = parsedLimit
	}
	if _, ok := p.tableColumns(table); !ok {
		unknown := unknownAgentTableResult("query_readonly", table)
		unknown.SQL = sql
		return unknown
	}
	if isAgentCountExpression(rawColumns) {
		rows := p.tableRows(table)
		result.OK = true
		result.Message = "只读数量查询执行完成。"
		result.Table = table
		result.Columns = []string{"count"}
		result.Rows = []map[string]string{{"count": strconv.Itoa(len(rows))}}
		return result
	}

	preview := p.previewTable(table, limit)
	if !preview.OK {
		preview.Name = "query_readonly"
		preview.SQL = sql
		return preview
	}

	selectedColumns, err := p.selectColumns(table, rawColumns)
	if err != nil {
		result.OK = false
		result.Table = table
		result.Error = err.Error()
		return result
	}

	projected := make([]map[string]string, 0, len(preview.Rows))
	for _, row := range preview.Rows {
		out := make(map[string]string, len(selectedColumns))
		for _, column := range selectedColumns {
			out[column] = row[column]
		}
		projected = append(projected, out)
	}

	result.OK = true
	result.Message = "只读查询执行完成。"
	result.Table = table
	result.Columns = selectedColumns
	result.Rows = projected
	return result
}

func (p tableToolProvider) tableColumns(table string) ([]agentTableColumn, bool) {
	switch normalizeAgentTableName(table) {
	case "install_state":
		return []agentTableColumn{
			{Name: "site_name", Type: "string", Description: "站点名称"},
			{Name: "admin_entry", Type: "string", Description: "随机后台入口"},
			{Name: "admin_user", Type: "string", Description: "初始化管理员账号"},
			{Name: "installed_at", Type: "datetime", Description: "初始化完成时间"},
			{Name: "database_driver", Type: "string", Description: "元数据数据库类型"},
			{Name: "database_target", Type: "string", Description: "元数据数据库地址或文件"},
			{Name: "ai_provider", Type: "string", Description: "AI 服务商"},
			{Name: "ai_model", Type: "string", Description: "默认对话模型"},
		}, true
	case "admin_settings":
		return []agentTableColumn{
			{Name: "key", Type: "string", Description: "配置项"},
			{Name: "value", Type: "string", Description: "配置值，敏感字段已屏蔽"},
		}, true
	case "storage_settings":
		return []agentTableColumn{
			{Name: "driver", Type: "string", Description: "存储驱动"},
			{Name: "local_path", Type: "string", Description: "本地存储目录"},
			{Name: "public_url", Type: "string", Description: "公开访问前缀"},
			{Name: "max_file_size_mb", Type: "integer", Description: "单文件大小限制 MB"},
			{Name: "allowed_extensions", Type: "string", Description: "允许上传和导出的扩展名"},
			{Name: "agent_export_retention_days", Type: "integer", Description: "智能体导出文件保留天数"},
		}, true
	case "upload_files":
		return []agentTableColumn{
			{Name: "name", Type: "string", Description: "文件名"},
			{Name: "path", Type: "string", Description: "存储相对路径"},
			{Name: "kind", Type: "string", Description: "文件类型"},
			{Name: "size", Type: "string", Description: "文件大小"},
			{Name: "modified_at", Type: "datetime", Description: "更新时间"},
			{Name: "status", Type: "string", Description: "文件状态"},
		}, true
	case "audit_events":
		return []agentTableColumn{
			{Name: "time", Type: "datetime", Description: "审计事件时间"},
			{Name: "category", Type: "string", Description: "事件分类，如 login、operation、file、ai"},
			{Name: "action", Type: "string", Description: "操作名称"},
			{Name: "actor", Type: "string", Description: "操作者"},
			{Name: "detail", Type: "string", Description: "操作详情"},
			{Name: "method", Type: "string", Description: "HTTP 方法"},
			{Name: "path", Type: "string", Description: "请求路径"},
			{Name: "ip", Type: "string", Description: "客户端 IP"},
			{Name: "status", Type: "string", Description: "响应状态码"},
		}, true
	case "admin_users":
		return []agentTableColumn{
			{Name: "username", Type: "string", Description: "登录账号"},
			{Name: "display_name", Type: "string", Description: "后台显示名称"},
			{Name: "role", Type: "string", Description: "所属角色"},
			{Name: "status", Type: "string", Description: "账号启用状态"},
			{Name: "source", Type: "string", Description: "数据来源"},
			{Name: "created_at", Type: "datetime", Description: "创建时间"},
			{Name: "last_seen", Type: "string", Description: "最近访问"},
		}, true
	case "admin_roles":
		return []agentTableColumn{
			{Name: "key", Type: "string", Description: "角色标识"},
			{Name: "name", Type: "string", Description: "角色名称"},
			{Name: "scope", Type: "string", Description: "作用范围"},
			{Name: "status", Type: "string", Description: "角色状态"},
		}, true
	case "admin_menus":
		return []agentTableColumn{
			{Name: "key", Type: "string", Description: "菜单标识"},
			{Name: "label", Type: "string", Description: "菜单名称"},
			{Name: "path", Type: "string", Description: "后台路径"},
			{Name: "status", Type: "string", Description: "菜单状态"},
		}, true
	case "admin_permissions":
		return []agentTableColumn{
			{Name: "key", Type: "string", Description: "权限标识"},
			{Name: "subject", Type: "string", Description: "权限对象"},
			{Name: "permission", Type: "string", Description: "权限动作"},
			{Name: "boundary", Type: "string", Description: "执行边界"},
			{Name: "status", Type: "string", Description: "状态"},
		}, true
	case "data_sources":
		return []agentTableColumn{
			{Name: "name", Type: "string", Description: "数据源名称"},
			{Name: "driver", Type: "string", Description: "数据源类型"},
			{Name: "target", Type: "string", Description: "连接目标或参考目录"},
			{Name: "role", Type: "string", Description: "用途"},
			{Name: "status", Type: "string", Description: "当前接入状态"},
		}, true
	case "agent_sessions":
		return []agentTableColumn{
			{Name: "id", Type: "string", Description: "会话标识"},
			{Name: "title", Type: "string", Description: "会话标题"},
			{Name: "actor", Type: "string", Description: "操作者"},
			{Name: "started_at", Type: "datetime", Description: "首次运行时间"},
			{Name: "updated_at", Type: "datetime", Description: "最近运行时间"},
			{Name: "last_message", Type: "string", Description: "最后一条任务消息"},
			{Name: "run_count", Type: "integer", Description: "运行次数"},
		}, true
	case "agent_runs":
		return []agentTableColumn{
			{Name: "id", Type: "string", Description: "运行标识"},
			{Name: "session_id", Type: "string", Description: "所属会话"},
			{Name: "actor", Type: "string", Description: "操作者"},
			{Name: "started_at", Type: "datetime", Description: "运行开始时间"},
			{Name: "mode", Type: "string", Description: "智能体意图模式"},
			{Name: "goal", Type: "string", Description: "运行目标"},
			{Name: "message", Type: "string", Description: "用户任务"},
			{Name: "status", Type: "string", Description: "运行状态"},
			{Name: "model_used", Type: "boolean", Description: "是否使用外部模型"},
			{Name: "tool_count", Type: "integer", Description: "工具调用次数"},
			{Name: "file_count", Type: "integer", Description: "生成文件数量"},
			{Name: "duration_ms", Type: "integer", Description: "耗时毫秒"},
		}, true
	case "agent_tool_results":
		return []agentTableColumn{
			{Name: "run_id", Type: "string", Description: "运行标识"},
			{Name: "index", Type: "integer", Description: "工具序号"},
			{Name: "name", Type: "string", Description: "工具名称"},
			{Name: "ok", Type: "boolean", Description: "是否成功"},
			{Name: "table", Type: "string", Description: "目标数据表"},
			{Name: "sql", Type: "string", Description: "只读 SQL"},
			{Name: "message", Type: "string", Description: "工具消息"},
			{Name: "error", Type: "string", Description: "错误信息"},
			{Name: "file", Type: "string", Description: "生成文件"},
			{Name: "row_count", Type: "integer", Description: "返回行数"},
			{Name: "columns", Type: "string", Description: "返回字段"},
		}, true
	case "ai_capabilities":
		return []agentTableColumn{
			{Name: "name", Type: "string", Description: "工具能力"},
			{Name: "boundary", Type: "string", Description: "执行边界"},
			{Name: "status", Type: "string", Description: "当前状态"},
		}, true
	case "agent_blueprint":
		return []agentTableColumn{
			{Name: "layer", Type: "string", Description: "智能体分层"},
			{Name: "role", Type: "string", Description: "职责"},
			{Name: "status", Type: "string", Description: "接入状态"},
			{Name: "next_action", Type: "string", Description: "下一步动作"},
		}, true
	default:
		return nil, false
	}
}

func (p tableToolProvider) tableRows(table string) []map[string]string {
	state := p.state
	switch normalizeAgentTableName(table) {
	case "install_state":
		return []map[string]string{{
			"site_name":       state.SiteName,
			"admin_entry":     state.AdminEntry,
			"admin_user":      state.AdminUser,
			"installed_at":    formatAgentTime(state.InstalledAt),
			"database_driver": state.Database.DisplayName(),
			"database_target": state.Database.DisplayTarget(),
			"ai_provider":     state.AI.DisplayName(),
			"ai_model":        state.AI.DisplayModel(),
		}}
	case "admin_settings":
		system := state.System.normalized()
		storage := state.Storage.normalized()
		return []map[string]string{
			{"key": "site_name", "value": state.SiteName},
			{"key": "admin_entry", "value": state.AdminEntry},
			{"key": "database", "value": state.Database.DisplayName() + " / " + state.Database.DisplayTarget()},
			{"key": "ai_provider", "value": state.AI.DisplayName()},
			{"key": "ai_model", "value": state.AI.DisplayModel()},
			{"key": "ai_api_key", "value": state.AI.maskedAPIKey()},
			{"key": "timezone", "value": system.Timezone},
			{"key": "locale", "value": system.Locale},
			{"key": "storage", "value": storage.DisplayName() + " / " + storage.LocalPath},
		}
	case "storage_settings":
		storage := state.Storage.normalized()
		return []map[string]string{
			{"driver": storage.DisplayName(), "local_path": storage.LocalPath, "public_url": storage.PublicURL, "max_file_size_mb": strconv.Itoa(storage.MaxFileSizeMB), "allowed_extensions": storage.AllowedExtensions, "agent_export_retention_days": strconv.Itoa(storage.AgentExportRetentionDays)},
		}
	case "upload_files":
		rows := listAdminFiles(state.Storage.normalized(), "", 50)
		out := make([]map[string]string, 0, len(rows))
		for _, row := range rows {
			out = append(out, map[string]string{
				"name":        row.Name,
				"path":        row.Path,
				"kind":        row.Kind,
				"size":        row.Size,
				"modified_at": row.Modified,
				"status":      row.Status,
			})
		}
		return out
	case "audit_events":
		out := make([]map[string]string, 0, len(p.auditEvents))
		for _, event := range p.auditEvents {
			out = append(out, map[string]string{
				"time":     event.Time,
				"category": event.Category,
				"action":   event.Action,
				"actor":    event.Actor,
				"detail":   event.Detail,
				"method":   event.Method,
				"path":     event.Path,
				"ip":       event.IP,
				"status":   event.Status,
			})
		}
		return out
	case "admin_users":
		rows := buildAdminUserRows(state, "")
		out := make([]map[string]string, 0, len(rows))
		for _, row := range rows {
			out = append(out, map[string]string{
				"username":     row.Username,
				"display_name": row.DisplayName,
				"role":         row.Role,
				"status":       row.Status,
				"source":       row.Source,
				"created_at":   row.CreatedAt,
				"last_seen":    row.LastSeen,
			})
		}
		return out
	case "admin_roles":
		rows := buildAdminRoleRows(state)
		out := make([]map[string]string, 0, len(rows))
		for _, row := range rows {
			out = append(out, map[string]string{
				"key":    row.Key,
				"name":   row.Name,
				"scope":  row.Scope,
				"status": row.Status,
			})
		}
		return out
	case "admin_menus":
		rows := buildAdminMenuRows(state)
		out := make([]map[string]string, 0, len(rows))
		for _, row := range rows {
			out = append(out, map[string]string{
				"key":    row.Key,
				"label":  row.Label,
				"path":   row.Path,
				"status": row.Status,
			})
		}
		return out
	case "admin_permissions":
		rows := buildAdminPermissionRows(state)
		out := make([]map[string]string, 0, len(rows))
		for _, row := range rows {
			out = append(out, map[string]string{
				"key":        row.Key,
				"subject":    row.Subject,
				"permission": row.Permission,
				"boundary":   row.Boundary,
				"status":     row.Status,
			})
		}
		return out
	case "data_sources":
		rows := buildAdminDataSources(state, "")
		out := make([]map[string]string, 0, len(rows))
		for _, row := range rows {
			status := row.Status
			if row.Message != "" {
				status += "：" + row.Message
			}
			out = append(out, map[string]string{
				"name":   row.Name,
				"driver": row.Driver,
				"target": row.Target,
				"role":   row.Role,
				"status": status,
			})
		}
		return out
	case "agent_sessions":
		out := make([]map[string]string, 0, len(p.runtime.Sessions))
		for _, row := range p.runtime.Sessions {
			out = append(out, map[string]string{
				"id":           row.ID,
				"title":        row.Title,
				"actor":        row.Actor,
				"started_at":   formatAgentTime(row.StartedAt),
				"updated_at":   formatAgentTime(row.UpdatedAt),
				"last_message": row.LastMessage,
				"run_count":    strconv.Itoa(row.RunCount),
			})
		}
		return out
	case "agent_runs":
		out := make([]map[string]string, 0, len(p.runtime.Runs))
		for _, row := range p.runtime.Runs {
			modelUsed := "否"
			if row.ModelUsed {
				modelUsed = "是"
			}
			out = append(out, map[string]string{
				"id":          row.ID,
				"session_id":  row.SessionID,
				"actor":       row.Actor,
				"started_at":  formatAgentTime(row.StartedAt),
				"mode":        row.Mode,
				"goal":        row.Goal,
				"message":     row.Message,
				"status":      agentRunStatusText(row.Status),
				"model_used":  modelUsed,
				"tool_count":  strconv.Itoa(row.ToolCount),
				"file_count":  strconv.Itoa(row.FileCount),
				"duration_ms": strconv.FormatInt(row.DurationMS, 10),
			})
		}
		return out
	case "agent_tool_results":
		out := make([]map[string]string, 0, len(p.runtime.ToolResults))
		for _, row := range p.runtime.ToolResults {
			ok := "否"
			if row.OK {
				ok = "是"
			}
			file := row.FileName
			if row.FileURL != "" {
				file = row.FileName + " " + row.FileURL
			}
			out = append(out, map[string]string{
				"run_id":    row.RunID,
				"index":     strconv.Itoa(row.Index),
				"name":      row.Name,
				"ok":        ok,
				"table":     row.Table,
				"sql":       row.SQL,
				"message":   row.Message,
				"error":     row.Error,
				"file":      file,
				"row_count": strconv.Itoa(row.RowCount),
				"columns":   row.Columns,
			})
		}
		return out
	case "ai_capabilities":
		return []map[string]string{
			{"name": "planner", "boundary": "任务理解、步骤拆解和下一步建议", "status": "已启用"},
			{"name": "list_tables", "boundary": "只读列出受控数据表", "status": "已启用"},
			{"name": "describe_table", "boundary": "只读查看字段结构", "status": "已启用"},
			{"name": "preview_table", "boundary": "最多预览 50 行，屏蔽敏感字段", "status": "已启用"},
			{"name": "query_readonly", "boundary": "仅允许单表 SELECT，拒绝写入语句", "status": "已启用"},
			{"name": "agent_memory", "boundary": "会话、运行和工具结果写入元数据表", "status": "已启用"},
			{"name": "insight_engine", "boundary": "基于工具结果生成洞察和建议", "status": "已启用"},
		}
	case "agent_blueprint":
		modelStatus := "已配置"
		modelAction := "继续补充工具调用和审计记忆"
		if state.AI.IsDisabled() {
			modelStatus = "待配置"
			modelAction = "在初始化或后台设置中接入百炼模型"
		}
		return []map[string]string{
			{"layer": "感知层", "role": "识别用户目标、页面上下文和数据范围", "status": "已启用", "next_action": "接入更多后台页面上下文"},
			{"layer": "计划层", "role": "拆解任务、生成执行计划和建议动作", "status": "已启用", "next_action": "增加多步骤任务状态持久化"},
			{"layer": "工具层", "role": "统一封装只读查询、结构探测和数据预览", "status": "已启用", "next_action": "接入真实 MySQL/PostgreSQL/SQLite 驱动"},
			{"layer": "模型层", "role": "通过百炼兼容接口整理结论和追问", "status": modelStatus, "next_action": modelAction},
			{"layer": "记忆层", "role": "沉淀会话、审计和任务结果", "status": "已启用", "next_action": "补多轮任务恢复和运行详情页"},
			{"layer": "产出层", "role": "生成表格导出、迁移报告和操作建议", "status": "已启用", "next_action": "补更复杂筛选和多表导出"},
		}
	default:
		return nil
	}
}

func (p tableToolProvider) selectColumns(table string, rawColumns string) ([]string, error) {
	columns, ok := p.tableColumns(table)
	if !ok {
		return nil, errors.New("未知数据表：" + table)
	}
	allowed := make(map[string]bool, len(columns))
	allColumns := make([]string, 0, len(columns))
	for _, column := range columns {
		allowed[column.Name] = true
		allColumns = append(allColumns, column.Name)
	}

	rawColumns = strings.TrimSpace(rawColumns)
	if rawColumns == "*" {
		return allColumns, nil
	}

	parts := strings.Split(rawColumns, ",")
	selected := make([]string, 0, len(parts))
	seen := map[string]bool{}
	for _, part := range parts {
		column := strings.ToLower(strings.TrimSpace(part))
		if !identifierPattern.MatchString(column) {
			return nil, errors.New("字段名不合法：" + strings.TrimSpace(part))
		}
		if !allowed[column] {
			return nil, errors.New("`" + table + "` 不存在字段：" + column)
		}
		if !seen[column] {
			selected = append(selected, column)
			seen[column] = true
		}
	}
	if len(selected) == 0 {
		return nil, errors.New("请选择要查询的字段")
	}
	return selected, nil
}

func (p tableToolProvider) exportColumnsForMessage(table string, message string) ([]string, bool) {
	columns, ok := p.tableColumns(table)
	if !ok {
		return nil, false
	}
	allColumns := make([]string, 0, len(columns))
	for _, column := range columns {
		allColumns = append(allColumns, column.Name)
	}

	selected := make([]string, 0)
	seen := map[string]bool{}
	for _, column := range columns {
		if !agentColumnMatchesMessage(message, table, column) {
			continue
		}
		if !seen[column.Name] {
			selected = append(selected, column.Name)
			seen[column.Name] = true
		}
	}
	if len(selected) == 0 {
		return allColumns, true
	}
	if len(selected) == 1 && !agentMessageHasColumnSelectionCue(message) {
		return allColumns, true
	}
	return selected, true
}

func (p tableToolProvider) filterRowsForMessage(table string, rows []map[string]string, message string) []map[string]string {
	filters := inferAgentFilters(table, message)
	if len(filters) == 0 {
		return rows
	}
	filtered := make([]map[string]string, 0, len(rows))
	for _, row := range rows {
		if agentRowMatchesFilters(row, filters) {
			filtered = append(filtered, row)
		}
	}
	return filtered
}

func agentColumnAliases(table string) map[string][]string {
	switch normalizeAgentTableName(table) {
	case "install_state":
		return map[string][]string{
			"site_name":       {"站点", "站点名称", "项目名称"},
			"admin_entry":     {"后台入口", "随机入口", "管理入口"},
			"admin_user":      {"管理员", "管理员账号", "超级管理员"},
			"installed_at":    {"安装时间", "初始化时间", "完成时间"},
			"database_driver": {"数据库类型", "数据库驱动", "元数据库类型"},
			"database_target": {"数据库地址", "数据库文件", "连接目标"},
			"ai_provider":     {"AI 服务商", "AI服务商", "模型服务商"},
			"ai_model":        {"AI 模型", "AI模型", "对话模型"},
		}
	case "admin_users":
		return map[string][]string{
			"username":     {"账号", "用户名", "登录账号", "账号名"},
			"display_name": {"显示名称", "名称", "昵称"},
			"role":         {"角色", "所属角色", "权限角色"},
			"status":       {"状态", "账号状态", "启用状态"},
			"source":       {"来源", "数据来源"},
			"created_at":   {"创建时间", "初始化时间"},
			"last_seen":    {"最近访问", "最近登录", "最后访问"},
		}
	case "storage_settings":
		return map[string][]string{
			"driver":                      {"驱动", "存储驱动", "存储类型"},
			"local_path":                  {"目录", "本地目录", "存储目录", "上传目录"},
			"public_url":                  {"访问前缀", "公开前缀", "公开地址", "URL"},
			"max_file_size_mb":            {"大小限制", "文件大小", "上传大小", "单文件大小"},
			"allowed_extensions":          {"扩展名", "允许扩展名", "文件类型"},
			"agent_export_retention_days": {"保留天数", "导出保留", "导出文件保留天数"},
		}
	case "upload_files":
		return map[string][]string{
			"name":        {"文件名", "名称"},
			"path":        {"路径", "存储路径", "相对路径"},
			"kind":        {"类型", "文件类型"},
			"size":        {"大小", "文件大小"},
			"modified_at": {"更新时间", "修改时间"},
			"status":      {"状态", "文件状态"},
		}
	case "audit_events":
		return map[string][]string{
			"time":     {"时间", "事件时间", "审计时间"},
			"category": {"分类", "类型", "事件分类"},
			"action":   {"操作", "动作", "事件"},
			"actor":    {"操作者", "管理员", "用户"},
			"detail":   {"详情", "内容", "说明"},
			"method":   {"方法", "HTTP方法"},
			"path":     {"路径", "请求路径"},
			"ip":       {"IP", "客户端IP", "来源IP"},
			"status":   {"状态码", "响应码", "状态"},
		}
	case "admin_roles":
		return map[string][]string{
			"key":    {"角色标识", "标识"},
			"name":   {"角色名称", "名称"},
			"scope":  {"作用范围", "范围"},
			"status": {"角色状态", "状态"},
		}
	case "admin_menus":
		return map[string][]string{
			"key":    {"菜单标识", "标识"},
			"label":  {"菜单名称", "名称", "标题"},
			"path":   {"路径", "后台路径", "入口"},
			"status": {"菜单状态", "状态", "启用状态"},
		}
	case "admin_permissions":
		return map[string][]string{
			"key":        {"权限标识"},
			"subject":    {"对象"},
			"permission": {"动作", "权限", "权限动作"},
			"boundary":   {"边界", "执行边界", "权限边界"},
			"status":     {"状态", "启用状态"},
		}
	case "data_sources":
		return map[string][]string{
			"name":   {"名称", "数据源"},
			"driver": {"类型", "驱动"},
			"target": {"地址", "目标"},
			"role":   {"用途"},
			"status": {"状态"},
		}
	case "agent_sessions":
		return map[string][]string{
			"id":           {"会话标识", "会话ID"},
			"title":        {"标题", "会话标题"},
			"actor":        {"操作者", "管理员"},
			"started_at":   {"开始时间", "首次运行"},
			"updated_at":   {"更新时间", "最近运行"},
			"last_message": {"最后消息", "最近任务"},
			"run_count":    {"运行次数", "次数"},
		}
	case "agent_runs":
		return map[string][]string{
			"id":          {"运行标识", "运行ID"},
			"session_id":  {"会话", "会话ID"},
			"actor":       {"操作者", "管理员"},
			"started_at":  {"开始时间", "运行时间"},
			"mode":        {"模式", "意图"},
			"goal":        {"目标", "任务目标"},
			"message":     {"消息", "用户任务"},
			"status":      {"状态", "运行状态"},
			"model_used":  {"模型", "是否使用模型"},
			"tool_count":  {"工具次数", "工具调用次数"},
			"file_count":  {"文件数量", "生成文件"},
			"duration_ms": {"耗时", "毫秒"},
		}
	case "agent_tool_results":
		return map[string][]string{
			"run_id":    {"运行ID", "运行标识"},
			"index":     {"序号", "工具序号"},
			"name":      {"工具", "工具名称"},
			"ok":        {"状态", "是否成功"},
			"table":     {"表", "目标表"},
			"sql":       {"SQL", "查询"},
			"message":   {"消息", "工具消息"},
			"error":     {"错误", "错误信息"},
			"file":      {"文件", "生成文件"},
			"row_count": {"行数", "返回行数"},
			"columns":   {"字段", "返回字段"},
		}
	case "ai_capabilities":
		return map[string][]string{
			"name":     {"能力", "工具能力", "工具名称"},
			"boundary": {"执行边界", "边界"},
			"status":   {"状态", "接入状态"},
		}
	case "agent_blueprint":
		return map[string][]string{
			"layer":       {"分层", "层级"},
			"role":        {"职责", "作用"},
			"status":      {"状态", "接入状态"},
			"next_action": {"下一步", "下一步动作", "后续动作"},
		}
	default:
		return map[string][]string{}
	}
}

func agentColumnMatchesMessage(message string, table string, column agentTableColumn) bool {
	for _, token := range agentColumnMatchTokens(table, column) {
		if agentNormalizedContains(message, token) {
			return true
		}
	}
	return false
}

func agentColumnMatchTokens(table string, column agentTableColumn) []string {
	tokens := []string{column.Name, column.Description}
	if aliases := agentColumnAliases(table); len(aliases[column.Name]) > 0 {
		tokens = append(tokens, aliases[column.Name]...)
	}
	return tokens
}

func agentMessageHasColumnSelectionCue(message string) bool {
	lower := strings.ToLower(message)
	return containsAny(lower, "字段", "列", "只要", "只导出", "仅导出", "仅显示", "包含", "包括") ||
		strings.Contains(message, "、") ||
		strings.Contains(message, ",") ||
		strings.Contains(message, "，") ||
		strings.Contains(message, "的")
}

func inferAgentFilters(table string, message string) map[string]string {
	lower := strings.ToLower(message)
	filters := map[string]string{}
	columns, ok := newTableToolProvider(installState{}, "", "").tableColumns(table)
	if ok {
		for _, column := range columns {
			for _, token := range agentColumnMatchTokens(table, column) {
				for _, separator := range []string{"=", ":", "："} {
					pattern := strings.ToLower(token) + separator
					if idx := strings.Index(lower, pattern); idx >= 0 {
						value := strings.TrimSpace(message[idx+len(pattern):])
						fields := strings.Fields(value)
						if len(fields) == 0 {
							continue
						}
						value = fields[0]
						value = strings.Trim(value, "，,。.;；")
						if value != "" {
							filters[column.Name] = value
						}
					}
				}
			}
		}
	}
	if strings.Contains(message, "启用") {
		filters["status"] = "启用"
	}
	if strings.Contains(message, "超级管理员") {
		if normalizeAgentTableName(table) == "admin_users" {
			filters["role"] = "超级管理员"
		}
		if normalizeAgentTableName(table) == "admin_roles" {
			filters["name"] = "超级管理员"
		}
	}
	return filters
}

func agentRowMatchesFilters(row map[string]string, filters map[string]string) bool {
	for column, expected := range filters {
		actual, ok := row[column]
		if !ok {
			continue
		}
		if !strings.Contains(strings.ToLower(actual), strings.ToLower(expected)) {
			return false
		}
	}
	return true
}

func validateAgentReadOnlySQL(sql string) error {
	trimmed := strings.TrimSpace(sql)
	if trimmed == "" {
		return errors.New("请输入 SELECT 查询")
	}
	lower := strings.ToLower(trimmed)
	if !strings.HasPrefix(lower, "select ") {
		return errors.New("后台智能体只允许执行 SELECT 查询")
	}
	if strings.Contains(trimmed, ";") || strings.Contains(trimmed, "--") || strings.Contains(trimmed, "/*") || strings.Contains(trimmed, "*/") {
		return errors.New("查询中不能包含多语句或注释")
	}
	for _, keyword := range []string{" insert ", " update ", " delete ", " drop ", " alter ", " create ", " truncate ", " replace ", " attach ", " detach ", " pragma ", " vacuum "} {
		if strings.Contains(" "+lower+" ", keyword) {
			return errors.New("查询包含非只读关键字：" + strings.TrimSpace(keyword))
		}
	}
	return nil
}

func isAgentCountExpression(rawColumns string) bool {
	normalized := strings.ToLower(strings.ReplaceAll(strings.TrimSpace(rawColumns), " ", ""))
	return normalized == "count(*)" || normalized == "count(1)"
}

func extractAgentSQL(message string) (string, bool) {
	trimmed := strings.TrimSpace(message)
	if trimmed == "" {
		return "", false
	}
	trimmed = strings.Trim(trimmed, "` \n\t")
	lower := strings.ToLower(trimmed)
	if strings.HasPrefix(lower, "select ") {
		return strings.TrimSpace(trimmed), true
	}
	if idx := strings.Index(lower, "select "); idx >= 0 {
		sql := strings.TrimSpace(trimmed[idx:])
		sql = strings.TrimRight(sql, "。；;` ")
		return sql, true
	}
	return "", false
}

func inferAgentExportTables(message string) []string {
	lower := strings.ToLower(message)
	known := knownAgentTables()
	if containsAny(lower, "所有表", "全部表", "所有数据表", "全部数据表", "all tables") {
		return known
	}
	tables := inferAgentTablesFromMessage(message)
	if len(tables) > 0 {
		return tables
	}
	return nil
}

func inferAgentLimit(message string, fallback int) int {
	lower := strings.ToLower(message)
	for _, pattern := range []*regexp.Regexp{
		regexp.MustCompile(`(?i)limit\s+(\d+)`),
		regexp.MustCompile(`前\s*(\d+)\s*条`),
		regexp.MustCompile(`最多\s*(\d+)\s*条`),
	} {
		matches := pattern.FindStringSubmatch(lower)
		if len(matches) == 2 {
			limit, err := strconv.Atoi(matches[1])
			if err == nil && limit > 0 {
				if limit > 10000 {
					return 10000
				}
				return limit
			}
		}
	}
	return fallback
}

func inferAgentTableName(message string) string {
	tables := inferAgentTablesFromMessage(message)
	if len(tables) > 0 {
		return tables[0]
	}
	return "install_state"
}

func knownAgentTables() []string {
	definitions := agentTableDefinitions()
	names := make([]string, 0, len(definitions))
	for _, definition := range definitions {
		names = append(names, definition.Name)
	}
	return names
}

func normalizeAgentTableName(table string) string {
	return strings.ToLower(strings.TrimSpace(table))
}

func unknownAgentTableResult(toolName string, table string) agentToolResult {
	if table == "" {
		table = "install_state"
	}
	return agentToolResult{
		Name:  toolName,
		OK:    false,
		Table: table,
		Error: "未知数据表：" + table + "。当前可查询：" + agentKnownTableSummary() + "。",
	}
}

type agentTableMatch struct {
	Name  string
	Score int
}

func inferAgentTablesFromMessage(message string) []string {
	matches := make([]agentTableMatch, 0)
	for _, definition := range agentTableDefinitions() {
		score := scoreAgentTableDefinition(message, definition)
		if score > 0 {
			matches = append(matches, agentTableMatch{Name: definition.Name, Score: score})
		}
	}
	if len(matches) == 0 {
		return nil
	}
	sort.SliceStable(matches, func(i, j int) bool {
		if matches[i].Score == matches[j].Score {
			return matches[i].Name < matches[j].Name
		}
		return matches[i].Score > matches[j].Score
	})
	topScore := matches[0].Score
	threshold := topScore
	if topScore >= 45 {
		threshold = 45
	}
	tables := make([]string, 0, len(matches))
	for _, match := range matches {
		if match.Score < threshold {
			continue
		}
		tables = append(tables, match.Name)
	}
	return tables
}

func scoreAgentTableDefinition(message string, definition agentTableDefinition) int {
	score := 0
	if agentNormalizedContains(message, definition.Name) {
		score = maxAgentScore(score, 100)
	}
	if agentNormalizedContains(message, definition.DisplayName) {
		score = maxAgentScore(score, 70)
	}
	if agentNormalizedContains(message, definition.Description) {
		score = maxAgentScore(score, 35)
	}
	for _, alias := range definition.Aliases {
		if !agentNormalizedContains(message, alias) {
			continue
		}
		aliasScore := 45
		if utf8.RuneCountInString(alias) <= 2 {
			aliasScore = 18
		}
		score = maxAgentScore(score, aliasScore)
	}
	return score
}

func agentKnownTableSummary() string {
	parts := make([]string, 0, len(agentTableDefinitions()))
	for _, definition := range agentTableDefinitions() {
		parts = append(parts, definition.DisplayName+"("+definition.Name+")")
	}
	return strings.Join(parts, "、")
}

func agentNormalizedContains(value string, needle string) bool {
	normalizedValue := normalizeAgentMatchText(value)
	normalizedNeedle := normalizeAgentMatchText(needle)
	return normalizedNeedle != "" && strings.Contains(normalizedValue, normalizedNeedle)
}

func normalizeAgentMatchText(value string) string {
	value = strings.ToLower(strings.TrimSpace(value))
	value = strings.NewReplacer(
		"帐号", "账号",
		"帐户", "账户",
		"賬號", "账号",
		"帳戶", "账户",
		"資料", "资料",
		"數據", "数据",
	).Replace(value)
	return strings.Map(func(r rune) rune {
		if r >= 'a' && r <= 'z' {
			return r
		}
		if r >= '0' && r <= '9' {
			return r
		}
		if r >= 0x4e00 && r <= 0x9fff {
			return r
		}
		return -1
	}, value)
}

func maxAgentScore(left int, right int) int {
	if right > left {
		return right
	}
	return left
}

func containsAny(value string, needles ...string) bool {
	for _, needle := range needles {
		if strings.Contains(value, needle) {
			return true
		}
	}
	return false
}

func formatAgentTime(t time.Time) string {
	if t.IsZero() {
		return ""
	}
	return t.Local().Format("2006-01-02 15:04:05")
}
