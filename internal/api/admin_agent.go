package api

import (
	"archive/zip"
	"bytes"
	"context"
	"database/sql"
	"encoding/base64"
	"encoding/csv"
	"encoding/json"
	"errors"
	"fmt"
	"html"
	"io"
	"net"
	"net/http"
	neturl "net/url"
	"os"
	"path/filepath"
	"regexp"
	"sort"
	"strconv"
	"strings"
	"time"
	"unicode/utf8"
)

const (
	defaultAgentTimeout      = 18 * time.Second
	defaultAgentImageTimeout = 90 * time.Second
)

var agentHTTPClient = &http.Client{Timeout: defaultAgentTimeout}
var agentImageHTTPClient = &http.Client{Timeout: defaultAgentImageTimeout}
var agentWebSearchBaseURL = "https://duckduckgo.com/html/"
var agentAllowPrivateWebTargets = false

type agentChatRequest struct {
	SessionID          string                   `json:"session_id,omitempty"`
	Message            string                   `json:"message"`
	History            []agentChatMessage       `json:"history,omitempty"`
	ResolvedMessage    string                   `json:"-"`
	CompressedContext  string                   `json:"-"`
	HistoricalTasks    string                   `json:"-"`
	StructuredMemory   agentStructuredMemory    `json:"-"`
	LastImage          *agentFileResult         `json:"-"`
	LastExport         *agentExportContinuation `json:"-"`
	TableAccessMode    string                   `json:"-"`
	AllowedTables      []string                 `json:"-"`
	AllowReadOnlyQuery *bool                    `json:"-"`
	AllowWebRead       *bool                    `json:"-"`
	AllowImageGenerate *bool                    `json:"-"`
}

type agentChatMessage struct {
	Role    string `json:"role"`
	Content string `json:"content"`
}

type agentChatResponse struct {
	OK          bool               `json:"ok"`
	SessionID   string             `json:"session_id,omitempty"`
	Reply       string             `json:"reply"`
	Run         agentRun           `json:"run"`
	CurrentTask *agentTaskSnapshot `json:"current_task,omitempty"`
	ToolResults []agentToolResult  `json:"tool_results,omitempty"`
	Files       []agentFileResult  `json:"files,omitempty"`
	ModelUsed   bool               `json:"model_used"`
	Error       string             `json:"error,omitempty"`
	ModelError  string             `json:"-"`
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
	Sessions       []agentSessionRecord
	Runs           []agentRunRecord
	ToolResults    []agentToolResultRecord
	WeChatMessages []agentWeChatMessageRecord
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

type agentTaskRecord struct {
	ID              string
	SessionID       string
	Actor           string
	Title           string
	Goal            string
	Status          string
	Intent          string
	PrimaryTable    string
	FocusTables     []string
	Filters         map[string]string
	ExportFormat    string
	LastTool        string
	LastRunID       string
	LastUserMessage string
	LastReply       string
	CreatedAt       time.Time
	UpdatedAt       time.Time
	CompletedAt     time.Time
}

type agentTaskStepRecord struct {
	ID        int64
	TaskID    string
	RunID     string
	StepIndex int
	Title     string
	Detail    string
	Status    string
	Tool      string
	CreatedAt time.Time
	UpdatedAt time.Time
}

type agentTaskSnapshot struct {
	ID              string                  `json:"id"`
	Title           string                  `json:"title"`
	Goal            string                  `json:"goal"`
	Status          string                  `json:"status"`
	StatusText      string                  `json:"status_text"`
	Intent          string                  `json:"intent"`
	PrimaryTable    string                  `json:"primary_table,omitempty"`
	ExportFormat    string                  `json:"export_format,omitempty"`
	LastUserMessage string                  `json:"last_user_message,omitempty"`
	UpdatedAt       string                  `json:"updated_at,omitempty"`
	Steps           []agentTaskStepSnapshot `json:"steps,omitempty"`
}

type agentTaskStepSnapshot struct {
	Title      string `json:"title"`
	Detail     string `json:"detail"`
	Status     string `json:"status"`
	StatusText string `json:"status_text"`
	Tool       string `json:"tool,omitempty"`
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
	Name           string `json:"name"`
	URL            string `json:"url"`
	MIME           string `json:"mime"`
	Size           int64  `json:"size"`
	Description    string `json:"description"`
	Prompt         string `json:"prompt,omitempty"`
	OriginalPrompt string `json:"original_prompt,omitempty"`
}

type agentSessionContext struct {
	History           []agentChatMessage
	CompressedContext string
	HistoricalTasks   string
	Memory            agentStructuredMemory
	ActiveTask        *agentTaskRecord
	LastExport        *agentExportContinuation
	LastImage         *agentFileResult
}

type agentExportContinuation struct {
	UserMessage string
	Reply       string
	Table       string
	FileName    string
}

type agentStructuredMemory struct {
	TaskAnchorMessage string            `json:"task_anchor_message,omitempty"`
	CurrentGoal       string            `json:"current_goal,omitempty"`
	LastIntent        string            `json:"last_intent,omitempty"`
	PrimaryTable      string            `json:"primary_table,omitempty"`
	FocusTables       []string          `json:"focus_tables,omitempty"`
	ActiveFilters     map[string]string `json:"active_filters,omitempty"`
	LastTool          string            `json:"last_tool,omitempty"`
	LastSQL           string            `json:"last_sql,omitempty"`
	LastFileName      string            `json:"last_file_name,omitempty"`
	ExportFormat      string            `json:"export_format,omitempty"`
	LastUserMessage   string            `json:"last_user_message,omitempty"`
	LastReply         string            `json:"last_reply,omitempty"`
	RecentFiles       []string          `json:"recent_files,omitempty"`
	RecentOperations  []string          `json:"recent_operations,omitempty"`
}

type agentTableColumn struct {
	Name        string
	Type        string
	Description string
}

type agentTableDefinition struct {
	Name              string
	Type              string
	DisplayName       string
	Description       string
	Aliases           []string
	HiddenFromCatalog bool
}

type agentExternalTable struct {
	ID          string
	Source      dataSourceConfig
	RawName     string
	DisplayName string
	Description string
	Columns     []agentTableColumn
	RawColumns  map[string]string
}

type tableToolProvider struct {
	state              installState
	exportDir          string
	downloadBasePath   string
	rawMessage         string
	allowedTables      map[string]struct{}
	denyAllTables      bool
	memory             agentStructuredMemory
	lastExport         *agentExportContinuation
	allowReadOnlyQuery bool
	allowWebRead       bool
	allowImageGenerate bool
	auditEvents        []adminAuditEvent
	notifications      []adminNotificationDeliveryRecord
	adminSessions      []adminSessionRecord
	backgroundTasks    []adminBackgroundTaskRecord
	backgroundLogs     []adminBackgroundTaskLogRecord
	schemaSnapshots    []adminSchemaSnapshotRecord
	settingChanges     []adminSettingChangeRecord
	runtime            agentRuntimeSnapshot
}

type agentQueryScope struct {
	Mode   string
	Tables []string
}

type agentExportFormat struct {
	Extension string
	MIME      string
	Label     string
}

type agentExportSheet struct {
	Table   string              `json:"table"`
	Columns []string            `json:"columns"`
	Headers []string            `json:"headers,omitempty"`
	Rows    []map[string]string `json:"rows"`
}

type agentExportData struct {
	GeneratedAt string             `json:"generated_at"`
	Sheets      []agentExportSheet `json:"sheets"`
	TotalRows   int                `json:"total_rows"`
}

type agentGeneratedImageArtifact struct {
	URL      string
	Base64   string
	MIMEType string
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
	agentIntentAccessScope  agentIntent = "access_scope"
	agentIntentSystemConfig agentIntent = "system_config"
	agentIntentWebAccess    agentIntent = "web_access"
	agentIntentImage        agentIntent = "image_generation"
)

const (
	agentTaskStatusRunning = "running"
	agentTaskStatusWaiting = "waiting"
	agentTaskStatusDone    = "done"
	agentTaskStatusFailed  = "failed"
)

var simpleSelectPattern = regexp.MustCompile(`(?i)^\s*select\s+(.+?)\s+from\s+([a-zA-Z_][a-zA-Z0-9_]*(?:\.[a-zA-Z_][a-zA-Z0-9_]*)*)(?:\s+limit\s+(\d+))?\s*$`)
var identifierPattern = regexp.MustCompile(`^[a-zA-Z_][a-zA-Z0-9_]*$`)
var agentURLPattern = regexp.MustCompile(`https?://[^\s<>"']+`)
var agentHTMLTitlePattern = regexp.MustCompile(`(?is)<title[^>]*>(.*?)</title>`)
var agentHTMLScriptPattern = regexp.MustCompile(`(?is)<script[^>]*>.*?</script>`)
var agentHTMLStylePattern = regexp.MustCompile(`(?is)<style[^>]*>.*?</style>`)
var agentHTMLTagPattern = regexp.MustCompile(`(?is)<[^>]+>`)
var agentHTMLSpacePattern = regexp.MustCompile(`\s+`)
var agentSearchResultPattern = regexp.MustCompile(`(?is)<a[^>]+href=["']([^"']+)["'][^>]*>(.*?)</a>`)

const (
	agentSessionRawRunLimit      = 4
	agentSessionSummaryRunLimit  = 12
	agentSessionSummaryCharLimit = 1800
)

func boolPtr(value bool) *bool {
	return &value
}

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
	currentUser := s.sessionUsername(r)
	access := adminRoleAccessForUsername(state, currentUser)
	if !s.isAuthenticated(r) {
		writeJSON(w, http.StatusUnauthorized, agentChatResponse{
			OK:    false,
			Error: "请先登录后台",
		})
		return
	}
	if !access.CanViewPage("ai") {
		writeJSON(w, http.StatusForbidden, agentChatResponse{
			OK:    false,
			Error: "当前管理员未获授权访问 AI 智能体。",
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
	sessionContext := s.agentSessionContext(sessionID, currentUser)
	payload.History = preferredAgentHistory(sessionContext.History, payload.History)
	payload.CompressedContext = strings.TrimSpace(sessionContext.CompressedContext)
	payload.HistoricalTasks = strings.TrimSpace(sessionContext.HistoricalTasks)
	payload.StructuredMemory = sessionContext.Memory.normalized()
	payload.LastImage = sessionContext.LastImage
	payload.LastExport = sessionContext.LastExport
	payload.ResolvedMessage = resolveAgentContinuationMessage(payload.Message, sessionContext)
	scope := agentScopeForAdminAccount(state, currentUser)
	payload.TableAccessMode = scope.Mode
	payload.AllowedTables = scope.Tables
	payload.AllowReadOnlyQuery = boolPtr(access.HasPermission("agent.sql.select"))
	payload.AllowWebRead = boolPtr(access.HasPermission("agent.web.read"))
	payload.AllowImageGenerate = boolPtr(access.HasPermission("agent.image.generate"))
	response := s.runAgentChat(r.Context(), state, payload)
	response.SessionID = sessionID
	statusCode := http.StatusOK
	if !response.OK {
		statusCode = http.StatusBadRequest
	}
	duration := time.Since(startedAt)
	actor := currentUser
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
	response.CurrentTask = s.persistAgentTaskState(sessionID, actor, payload, response, sessionContext)
	s.recordAuditEvent(r, state, auditEventInput{
		Category:   "ai",
		Action:     "智能体对话",
		Detail:     fmt.Sprintf("模式 %s，工具调用 %d 次，返回文件 %d 个，数据范围 %s", response.Run.Mode, len(response.ToolResults), len(response.Files), scope.Metadata()),
		StatusCode: statusCode,
		Duration:   duration,
	})
	if response.ModelError != "" {
		s.notifyAdminEvent(r, state, state.Notifications.normalized().EventAIErrors, "ai_model_error", "AI 模型调用异常", "智能体任务已降级为本地工具回复。模式："+response.Run.Mode+"，错误："+response.ModelError)
	}
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
	if !adminRoleAccessForUsername(state, s.sessionUsername(r)).CanViewPage("ai") {
		http.Error(w, "当前管理员未获授权访问 AI 导出文件", http.StatusForbidden)
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
	disposition := "attachment"
	if strings.HasPrefix(contentType, "image/") {
		disposition = "inline"
	}
	w.Header().Set("Content-Disposition", disposition+`; filename="`+name+`"`)
	http.ServeFile(w, r, filePath)
}

func (s *adminServer) runAgentChat(ctx context.Context, state installState, payload agentChatRequest) agentChatResponse {
	entry := s.adminEntryForState(state)
	scope := newAgentQueryScope(payload.TableAccessMode, payload.AllowedTables)
	tools := newTableToolProvider(state, s.agentExportDir(), entry+"/ai/files", s.listAuditEvents(50))
	tools = tools.withQueryScope(scope)
	tools = tools.withStructuredMemory(payload.StructuredMemory)
	tools = tools.withLastExport(payload.LastExport)
	tools = tools.withRequestMessage(payload.Message)
	if payload.AllowReadOnlyQuery != nil {
		tools.allowReadOnlyQuery = *payload.AllowReadOnlyQuery
	}
	if payload.AllowWebRead != nil {
		tools.allowWebRead = *payload.AllowWebRead
	}
	if payload.AllowImageGenerate != nil {
		tools.allowImageGenerate = *payload.AllowImageGenerate
	}
	tools.runtime = agentRuntimeSnapshot{
		Sessions:       s.listAgentSessions(50),
		Runs:           s.listAgentRuns(80),
		ToolResults:    s.listAgentToolResults(120),
		WeChatMessages: s.listAgentWeChatMessages(120),
	}
	tools.notifications = s.listNotificationDeliveries(80)
	tools.adminSessions = s.listAdminSessions(80)
	tools.backgroundTasks = s.listBackgroundTasks(80)
	tools.backgroundLogs = s.listBackgroundTaskLogs(120)
	tools.schemaSnapshots = s.listSchemaSnapshots(120)
	tools.settingChanges = s.listSettingChanges(120)
	if shouldReuseLastImagePreview(payload.Message, payload.LastImage, payload.StructuredMemory) {
		run := buildAgentRun(state, payload.Message, agentIntentImage, nil)
		run.Goal = "重新展示上一张已生成图片"
		run.Trace = []agentTraceItem{
			{Title: "识别图片续接", Detail: "检测到用户希望直接查看上一张图片，不重新生成。", Status: "done"},
			{Title: "复用已有结果", Detail: "已把上一张生成图片重新附在当前回复中。", Status: "done"},
		}
		run.Insights = []agentInsight{
			{Title: "图片已重新附上", Detail: "这次直接复用了上一张生成图片，没有再次调用图片模型。", Tone: "ready"},
		}
		run.Suggestions = []agentSuggestion{
			{Label: "改成横版", Prompt: "基于刚才那张图，改成更适合横版首页横幅的构图"},
			{Label: "换个风格", Prompt: "延续刚才那张图的主题，但改成更简洁的插图风格"},
			{Label: "加说明文字", Prompt: "基于刚才那张图，生成一版适合加标题文案的海报构图"},
		}
		run.Metadata["table_authorization"] = scope.Metadata()
		if tools.allowWebRead {
			run.Metadata["web_authorization"] = "enabled"
		} else {
			run.Metadata["web_authorization"] = "disabled"
		}
		if tools.allowImageGenerate {
			run.Metadata["image_authorization"] = "enabled"
		} else {
			run.Metadata["image_authorization"] = "disabled"
		}
		applyAgentStructuredMemoryMetadata(run.Metadata, payload.StructuredMemory)
		return agentChatResponse{
			OK:        true,
			Reply:     finalizeAgentReply(payload.Message, "刚才那张图已经重新附上，可以直接预览或下载。"),
			Run:       run,
			Files:     []agentFileResult{*payload.LastImage},
			ModelUsed: false,
		}
	}
	effectiveMessage := strings.TrimSpace(payload.ResolvedMessage)
	if effectiveMessage == "" {
		effectiveMessage = payload.Message
	}
	intent := determineAgentIntent(effectiveMessage)
	results := planAndRunAgentTools(tools, effectiveMessage)
	run := buildAgentRun(state, payload.Message, intent, results)
	run.Metadata["table_authorization"] = scope.Metadata()
	if tools.allowWebRead {
		run.Metadata["web_authorization"] = "enabled"
	} else {
		run.Metadata["web_authorization"] = "disabled"
	}
	if tools.allowImageGenerate {
		run.Metadata["image_authorization"] = "enabled"
	} else {
		run.Metadata["image_authorization"] = "disabled"
	}
	applyAgentStructuredMemoryMetadata(run.Metadata, payload.StructuredMemory)
	fallbackReply := composeLocalAgentReply(payload.Message, run, results, scope.Mode, scope.Tables)
	files := collectAgentFiles(results)

	modelPayload := payload
	modelPayload.CompressedContext = strings.TrimSpace(payload.CompressedContext)
	modelPayload.HistoricalTasks = strings.TrimSpace(payload.HistoricalTasks)
	modelPayload.StructuredMemory = payload.StructuredMemory.normalized()
	modelPayload.TableAccessMode = scope.Mode
	modelPayload.AllowedTables = append([]string(nil), scope.Tables...)
	modelReply, modelUsed, err := "", false, error(nil)
	if shouldCallAgentModel(intent, results) {
		modelReply, modelUsed, err = callConfiguredAgentModel(ctx, state.AI, modelPayload, run, results)
	}
	if err == nil && strings.TrimSpace(modelReply) != "" {
		modelReply = finalizeAgentReply(payload.Message, modelReply)
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
		fallbackReply += "\n\n模型暂时不可用，已改用本地工具完成。"
		fallbackReply = finalizeAgentReply(payload.Message, fallbackReply)
		return agentChatResponse{
			OK:          true,
			Reply:       fallbackReply,
			Run:         run,
			ToolResults: results,
			Files:       files,
			ModelUsed:   false,
			ModelError:  err.Error(),
		}
	}

	fallbackReply = finalizeAgentReply(payload.Message, fallbackReply)
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

func agentMessageWantsDetailedReply(message string) bool {
	lower := strings.ToLower(strings.TrimSpace(message))
	return containsAny(lower,
		"详细", "展开", "具体", "完整", "一步一步", "分步骤", "详细说", "详细讲", "详细解释",
		"why", "in detail", "step by step", "full explanation")
}

func splitAgentReplySentences(reply string) []string {
	reply = strings.TrimSpace(reply)
	if reply == "" {
		return nil
	}
	parts := make([]string, 0, 4)
	var current strings.Builder
	flush := func(force bool) {
		text := strings.TrimSpace(current.String())
		if text == "" && !force {
			current.Reset()
			return
		}
		if text != "" {
			parts = append(parts, text)
		}
		current.Reset()
	}
	for _, r := range reply {
		current.WriteRune(r)
		switch r {
		case '。', '！', '？', '!', '?', '\n':
			flush(false)
		}
	}
	flush(false)
	return parts
}

func finalizeAgentReply(message string, reply string) string {
	reply = strings.TrimSpace(reply)
	if reply == "" {
		return ""
	}
	reply = regexp.MustCompile(`\n{3,}`).ReplaceAllString(reply, "\n\n")
	if agentMessageWantsDetailedReply(message) || strings.Contains(reply, "```") {
		return reply
	}
	sentences := splitAgentReplySentences(reply)
	if utf8.RuneCountInString(reply) <= 160 && strings.Count(reply, "\n") <= 2 && len(sentences) <= 3 {
		return reply
	}
	if len(sentences) == 0 {
		return reply
	}
	out := make([]string, 0, 3)
	total := 0
	for _, sentence := range sentences {
		sentence = strings.TrimSpace(sentence)
		if sentence == "" {
			continue
		}
		length := utf8.RuneCountInString(sentence)
		if len(out) >= 3 {
			break
		}
		if len(out) > 0 && total+length > 160 {
			break
		}
		out = append(out, sentence)
		total += length
	}
	if len(out) == 0 {
		return reply
	}
	return strings.Join(out, " ")
}

func newAgentSessionID() string {
	return "session-" + strconv.FormatInt(time.Now().UnixNano(), 36)
}

func (s *adminServer) agentSessionContext(sessionID string, actor string) agentSessionContext {
	sessionID = normalizeAgentSessionID(sessionID)
	actor = strings.TrimSpace(actor)
	if sessionID == "" {
		return agentSessionContext{}
	}
	tasks := s.listAgentTasks(60)
	activeTask := latestAgentTaskForSession(tasks, sessionID, actor)
	if activeTask == nil {
		activeTask = latestOpenAgentTaskForActor(tasks, actor)
	}
	historicalTasks := summarizeHistoricalAgentTasks(tasks, sessionID, actor)
	runs := s.listAgentRuns(160)
	if len(runs) == 0 {
		return buildAgentTaskSeedContext(historicalTasks, activeTask)
	}
	toolsByRun := map[string][]agentToolResultRecord{}
	for _, record := range s.listAgentToolResults(320) {
		if strings.TrimSpace(record.RunID) == "" {
			continue
		}
		toolsByRun[record.RunID] = append(toolsByRun[record.RunID], record)
	}

	matchedDesc := make([]agentRunRecord, 0, 40)
	for _, run := range runs {
		if run.SessionID != sessionID {
			continue
		}
		if actor != "" && run.Actor != actor {
			continue
		}
		matchedDesc = append(matchedDesc, run)
		if len(matchedDesc) >= 40 {
			break
		}
	}
	if len(matchedDesc) == 0 {
		return buildAgentTaskSeedContext(historicalTasks, activeTask)
	}

	recentRunsDesc := matchedDesc
	if len(recentRunsDesc) > agentSessionRawRunLimit {
		recentRunsDesc = recentRunsDesc[:agentSessionRawRunLimit]
	}
	ctx := agentSessionContext{
		History: make([]agentChatMessage, 0, len(recentRunsDesc)*2),
	}
	for i := len(recentRunsDesc) - 1; i >= 0; i-- {
		run := recentRunsDesc[i]
		if message := strings.TrimSpace(run.Message); message != "" {
			ctx.History = append(ctx.History, agentChatMessage{Role: "user", Content: message})
		}
		reply := agentPersistedAssistantHistory(run, toolsByRun[run.ID])
		if reply != "" {
			ctx.History = append(ctx.History, agentChatMessage{Role: "assistant", Content: reply})
		}
	}
	ctx.Memory = buildAgentStructuredMemory(matchedDesc, toolsByRun)
	if len(matchedDesc) > agentSessionRawRunLimit {
		ctx.CompressedContext = buildCompressedAgentSessionContext(matchedDesc[agentSessionRawRunLimit:], toolsByRun)
	}
	for _, run := range matchedDesc {
		lastExport := latestSessionExportContinuation(run, toolsByRun[run.ID])
		if ctx.LastExport == nil && lastExport != nil {
			ctx.LastExport = lastExport
		}
		lastImage := latestSessionImageContinuation(toolsByRun[run.ID])
		if ctx.LastImage == nil && lastImage != nil {
			ctx.LastImage = lastImage
		}
		if ctx.LastExport != nil && ctx.LastImage != nil {
			break
		}
	}
	ctx.ActiveTask = activeTask
	ctx.HistoricalTasks = historicalTasks
	ctx.History = tailAgentHistory(ctx.History, 8)
	return ctx
}

func buildAgentTaskSeedContext(historicalTasks string, activeTask *agentTaskRecord) agentSessionContext {
	ctx := agentSessionContext{
		HistoricalTasks: historicalTasks,
		ActiveTask:      activeTask,
	}
	if activeTask == nil {
		return ctx
	}
	ctx.Memory = agentStructuredMemory{
		TaskAnchorMessage: activeTask.Title,
		CurrentGoal:       activeTask.Goal,
		LastIntent:        activeTask.Intent,
		PrimaryTable:      activeTask.PrimaryTable,
		FocusTables:       append([]string(nil), activeTask.FocusTables...),
		ActiveFilters:     copyAgentFilters(activeTask.Filters),
		LastTool:          activeTask.LastTool,
		LastFileName:      "",
		ExportFormat:      activeTask.ExportFormat,
		LastUserMessage:   activeTask.LastUserMessage,
		LastReply:         activeTask.LastReply,
	}.normalized()
	return ctx
}

func (s *adminServer) persistAgentTaskState(sessionID string, actor string, payload agentChatRequest, response agentChatResponse, sessionContext agentSessionContext) *agentTaskSnapshot {
	actor = strings.TrimSpace(actor)
	if actor == "" {
		return nil
	}
	now := time.Now().UTC()
	activeTask := sessionContext.ActiveTask
	continueTask := shouldContinueAgentTask(payload.Message, payload.StructuredMemory, activeTask)
	if activeTask != nil && !continueTask && activeTask.Status != agentTaskStatusDone {
		closed := *activeTask
		closed.Status = agentTaskStatusDone
		closed.CompletedAt = now
		closed.UpdatedAt = now
		_ = s.store.UpsertAgentTask(closed)
		activeTask = nil
	}

	task := buildAgentTaskRecord(activeTask, sessionID, actor, payload, response, now)
	steps := buildAgentTaskSteps(task.ID, response, now)
	if err := s.store.UpsertAgentTask(task); err != nil {
		return nil
	}
	if err := s.store.ReplaceAgentTaskSteps(task.ID, steps); err != nil {
		return nil
	}
	return buildAgentTaskSnapshot(task, steps)
}

func shouldContinueAgentTask(message string, memory agentStructuredMemory, activeTask *agentTaskRecord) bool {
	if activeTask == nil {
		return false
	}
	if shouldReuseStructuredTaskMemory(message, memory) {
		return true
	}
	if len(inferAgentExplicitTablesFromMessage(message)) == 0 && strings.TrimSpace(message) != "" && len([]rune(strings.TrimSpace(message))) <= 24 {
		return true
	}
	return false
}

func buildAgentTaskRecord(activeTask *agentTaskRecord, sessionID string, actor string, payload agentChatRequest, response agentChatResponse, now time.Time) agentTaskRecord {
	memory := payload.StructuredMemory.normalized()
	task := agentTaskRecord{
		ID:              newAgentTaskID(),
		SessionID:       sessionID,
		Actor:           actor,
		CreatedAt:       now,
		UpdatedAt:       now,
		Status:          deriveAgentTaskStatus(response),
		Intent:          response.Run.Mode,
		Title:           truncateAuditText(firstNonEmpty(response.Run.Goal, payload.Message, memory.CurrentGoal), 96),
		Goal:            strings.TrimSpace(firstNonEmpty(memory.CurrentGoal, response.Run.Goal, payload.Message)),
		PrimaryTable:    deriveAgentPrimaryTable(memory, response.ToolResults),
		FocusTables:     deriveAgentFocusTables(memory, response.ToolResults),
		Filters:         copyAgentFilters(memory.ActiveFilters),
		ExportFormat:    deriveAgentExportFormat(memory, response),
		LastTool:        deriveAgentLastTool(response.ToolResults),
		LastRunID:       response.Run.ID,
		LastUserMessage: strings.TrimSpace(payload.Message),
		LastReply:       strings.TrimSpace(response.Reply),
	}
	if activeTask != nil {
		task.ID = activeTask.ID
		task.CreatedAt = activeTask.CreatedAt
		task.FocusTables = mergeAgentFocusTables(task.FocusTables, activeTask.FocusTables)
		task.Filters = mergeAgentFilters(activeTask.Filters, task.Filters)
		if task.Title == "" {
			task.Title = activeTask.Title
		}
		if task.Goal == "" {
			task.Goal = activeTask.Goal
		}
		if task.PrimaryTable == "" {
			task.PrimaryTable = activeTask.PrimaryTable
		}
		if task.ExportFormat == "" {
			task.ExportFormat = activeTask.ExportFormat
		}
		if task.LastTool == "" {
			task.LastTool = activeTask.LastTool
		}
	}
	if task.Status == agentTaskStatusDone || task.Status == agentTaskStatusFailed {
		task.CompletedAt = now
	}
	return task.normalized()
}

func buildAgentTaskSteps(taskID string, response agentChatResponse, now time.Time) []agentTaskStepRecord {
	steps := make([]agentTaskStepRecord, 0, len(response.Run.Plan)+1)
	for index, step := range response.Run.Plan {
		steps = append(steps, agentTaskStepRecord{
			TaskID:    taskID,
			RunID:     response.Run.ID,
			StepIndex: index,
			Title:     step.Title,
			Detail:    step.Detail,
			Status:    normalizeAgentTaskStepStatus(step.Status),
			CreatedAt: now,
			UpdatedAt: now,
		})
	}
	if deriveAgentTaskStatus(response) == agentTaskStatusWaiting {
		steps = append(steps, agentTaskStepRecord{
			TaskID:    taskID,
			RunID:     response.Run.ID,
			StepIndex: len(steps),
			Title:     "等待下一步",
			Detail:    "本轮任务已完成，等待继续追问、补充条件或下一个受控动作。",
			Status:    agentTaskStatusWaiting,
			CreatedAt: now,
			UpdatedAt: now,
		})
	}
	return steps
}

func buildAgentTaskSnapshot(task agentTaskRecord, steps []agentTaskStepRecord) *agentTaskSnapshot {
	snapshot := &agentTaskSnapshot{
		ID:              task.ID,
		Title:           task.Title,
		Goal:            task.Goal,
		Status:          task.Status,
		StatusText:      agentTaskStatusText(task.Status),
		Intent:          task.Intent,
		PrimaryTable:    task.PrimaryTable,
		ExportFormat:    task.ExportFormat,
		LastUserMessage: task.LastUserMessage,
		UpdatedAt:       formatAdminTime(task.UpdatedAt),
		Steps:           make([]agentTaskStepSnapshot, 0, len(steps)),
	}
	for _, step := range steps {
		snapshot.Steps = append(snapshot.Steps, agentTaskStepSnapshot{
			Title:      step.Title,
			Detail:     step.Detail,
			Status:     step.Status,
			StatusText: agentTaskStatusText(step.Status),
			Tool:       step.Tool,
		})
	}
	return snapshot
}

func latestAgentTaskForSession(tasks []agentTaskRecord, sessionID string, actor string) *agentTaskRecord {
	sessionID = normalizeAgentSessionID(sessionID)
	actor = strings.TrimSpace(actor)
	for _, task := range tasks {
		if task.SessionID != sessionID {
			continue
		}
		if actor != "" && task.Actor != actor {
			continue
		}
		candidate := task.normalized()
		return &candidate
	}
	return nil
}

func latestOpenAgentTaskForActor(tasks []agentTaskRecord, actor string) *agentTaskRecord {
	actor = strings.TrimSpace(actor)
	if actor == "" {
		return nil
	}
	for _, task := range tasks {
		task = task.normalized()
		if task.Actor != actor {
			continue
		}
		if task.Status == agentTaskStatusDone || task.Status == agentTaskStatusFailed {
			continue
		}
		candidate := task
		return &candidate
	}
	return nil
}

func summarizeHistoricalAgentTasks(tasks []agentTaskRecord, sessionID string, actor string) string {
	actor = strings.TrimSpace(actor)
	if actor == "" {
		return ""
	}
	lines := make([]string, 0, 5)
	for _, task := range tasks {
		if task.Actor != actor || task.SessionID == sessionID {
			continue
		}
		task = task.normalized()
		lineParts := []string{}
		if task.Title != "" {
			lineParts = append(lineParts, "任务="+task.Title)
		}
		if task.PrimaryTable != "" {
			lineParts = append(lineParts, "主表="+task.PrimaryTable)
		}
		if task.ExportFormat != "" {
			lineParts = append(lineParts, "格式="+task.ExportFormat)
		}
		if summary := agentFilterSummary(task.Filters); summary != "" {
			lineParts = append(lineParts, "筛选="+summary)
		}
		if len(lineParts) == 0 {
			continue
		}
		lines = append(lines, strings.Join(lineParts, "；"))
		if len(lines) >= 4 {
			break
		}
	}
	if len(lines) == 0 {
		return ""
	}
	return "当前管理员最近完成或处理中任务摘要：" + strings.Join(lines, "\n")
}

func newAgentTaskID() string {
	return "task-" + strconv.FormatInt(time.Now().UnixNano(), 36)
}

func buildAgentStructuredMemory(runsDesc []agentRunRecord, toolsByRun map[string][]agentToolResultRecord) agentStructuredMemory {
	if len(runsDesc) == 0 {
		return agentStructuredMemory{}
	}

	latest := runsDesc[0]
	memory := agentStructuredMemory{
		TaskAnchorMessage: strings.TrimSpace(firstNonEmpty(latest.Message, latest.Goal)),
		CurrentGoal:       strings.TrimSpace(firstNonEmpty(latest.Goal, latest.Message)),
		LastIntent:        strings.TrimSpace(latest.Mode),
		LastUserMessage:   strings.TrimSpace(latest.Message),
		LastReply:         truncateAuditText(strings.TrimSpace(latest.Reply), 220),
	}

	tableSeen := map[string]struct{}{}
	fileSeen := map[string]struct{}{}
	opSeen := map[string]struct{}{}
	for i, run := range runsDesc {
		if i >= 10 {
			break
		}
		inferredTables := inferAgentExplicitTablesFromMessage(run.Message)
		for _, table := range inferredTables {
			table = normalizeAgentTableName(table)
			if table == "" {
				continue
			}
			if memory.PrimaryTable == "" {
				memory.PrimaryTable = table
			}
			if _, ok := tableSeen[table]; !ok {
				tableSeen[table] = struct{}{}
				memory.FocusTables = append(memory.FocusTables, table)
			}
		}
		toolResults := toolsByRun[run.ID]
		for _, result := range toolResults {
			if memory.LastTool == "" && strings.TrimSpace(result.Name) != "" {
				memory.LastTool = strings.TrimSpace(result.Name)
			}
			if memory.LastSQL == "" && strings.TrimSpace(result.SQL) != "" {
				memory.LastSQL = strings.TrimSpace(result.SQL)
			}
			if table := normalizeAgentTableName(result.Table); table != "" {
				if memory.PrimaryTable == "" {
					memory.PrimaryTable = table
				}
				if _, ok := tableSeen[table]; !ok {
					tableSeen[table] = struct{}{}
					memory.FocusTables = append(memory.FocusTables, table)
				}
			}
			if op := strings.TrimSpace(result.Name); op != "" {
				if _, ok := opSeen[op]; !ok {
					opSeen[op] = struct{}{}
					memory.RecentOperations = append(memory.RecentOperations, op)
				}
			}
			if fileName := strings.TrimSpace(result.FileName); fileName != "" {
				if memory.LastFileName == "" {
					memory.LastFileName = fileName
				}
				if memory.ExportFormat == "" {
					memory.ExportFormat = agentFileExtensionLabel(fileName)
				}
				if _, ok := fileSeen[fileName]; !ok {
					fileSeen[fileName] = struct{}{}
					memory.RecentFiles = append(memory.RecentFiles, fileName)
				}
			}
		}
	}

	if memory.PrimaryTable != "" {
		for _, run := range runsDesc {
			related := false
			for _, table := range inferAgentExplicitTablesFromMessage(run.Message) {
				if normalizeAgentTableName(table) == memory.PrimaryTable {
					related = true
					break
				}
			}
			if !related {
				for _, result := range toolsByRun[run.ID] {
					if normalizeAgentTableName(result.Table) == memory.PrimaryTable {
						related = true
						break
					}
				}
			}
			if !related {
				continue
			}
			filters := inferAgentFilters(memory.PrimaryTable, run.Message)
			for column, value := range filters {
				if strings.TrimSpace(value) == "" {
					continue
				}
				if memory.ActiveFilters == nil {
					memory.ActiveFilters = map[string]string{}
				}
				if _, ok := memory.ActiveFilters[column]; !ok {
					memory.ActiveFilters[column] = value
				}
			}
		}
	}

	return memory.normalized()
}

func preferredAgentHistory(sessionHistory []agentChatMessage, requestHistory []agentChatMessage) []agentChatMessage {
	if len(sessionHistory) > 0 {
		return tailAgentHistory(sessionHistory, 8)
	}
	return tailAgentHistory(requestHistory, 8)
}

func (m agentStructuredMemory) normalized() agentStructuredMemory {
	m.TaskAnchorMessage = strings.TrimSpace(m.TaskAnchorMessage)
	m.CurrentGoal = strings.TrimSpace(m.CurrentGoal)
	m.LastIntent = strings.TrimSpace(m.LastIntent)
	m.PrimaryTable = normalizeAgentTableName(m.PrimaryTable)
	m.LastTool = strings.TrimSpace(m.LastTool)
	m.LastSQL = strings.TrimSpace(m.LastSQL)
	m.LastFileName = strings.TrimSpace(m.LastFileName)
	m.ExportFormat = strings.ToUpper(strings.TrimSpace(m.ExportFormat))
	m.LastUserMessage = strings.TrimSpace(m.LastUserMessage)
	m.LastReply = strings.TrimSpace(m.LastReply)
	m.FocusTables = normalizeAgentAllowedTables(m.FocusTables)
	m.RecentFiles = normalizeAgentRecentStrings(m.RecentFiles, 4)
	m.RecentOperations = normalizeAgentRecentStrings(m.RecentOperations, 6)
	if len(m.ActiveFilters) > 0 {
		normalizedFilters := make(map[string]string, len(m.ActiveFilters))
		for key, value := range m.ActiveFilters {
			key = normalizeAgentTableName(key)
			value = strings.TrimSpace(value)
			if key == "" || value == "" {
				continue
			}
			normalizedFilters[key] = value
		}
		if len(normalizedFilters) > 0 {
			m.ActiveFilters = normalizedFilters
		} else {
			m.ActiveFilters = nil
		}
	}
	return m
}

func (m agentStructuredMemory) hasContext() bool {
	return m.TaskAnchorMessage != "" || m.CurrentGoal != "" || m.PrimaryTable != "" || len(m.FocusTables) > 0
}

func (m agentStructuredMemory) filterSummary() string {
	if len(m.ActiveFilters) == 0 {
		return ""
	}
	keys := make([]string, 0, len(m.ActiveFilters))
	for key := range m.ActiveFilters {
		keys = append(keys, key)
	}
	sort.Strings(keys)
	parts := make([]string, 0, len(keys))
	for _, key := range keys {
		parts = append(parts, key+"="+m.ActiveFilters[key])
	}
	return strings.Join(parts, "，")
}

func (m agentStructuredMemory) promptSummary() string {
	m = m.normalized()
	if !m.hasContext() {
		return ""
	}
	parts := make([]string, 0, 8)
	if m.CurrentGoal != "" {
		parts = append(parts, "当前任务="+m.CurrentGoal)
	}
	if m.TaskAnchorMessage != "" && m.TaskAnchorMessage != m.CurrentGoal {
		parts = append(parts, "最近锚点消息="+m.TaskAnchorMessage)
	}
	if m.LastIntent != "" {
		parts = append(parts, "最近模式="+m.LastIntent)
	}
	if m.PrimaryTable != "" {
		parts = append(parts, "主表="+m.PrimaryTable)
	}
	if len(m.FocusTables) > 0 {
		parts = append(parts, "相关表="+strings.Join(m.FocusTables, ","))
	}
	if summary := m.filterSummary(); summary != "" {
		parts = append(parts, "当前筛选="+summary)
	}
	if m.ExportFormat != "" {
		parts = append(parts, "最近导出格式="+m.ExportFormat)
	}
	if m.LastFileName != "" {
		parts = append(parts, "最近文件="+m.LastFileName)
	}
	if m.LastTool != "" {
		parts = append(parts, "最近工具="+m.LastTool)
	}
	return strings.Join(parts, "；")
}

func normalizeAgentRecentStrings(values []string, limit int) []string {
	out := make([]string, 0, len(values))
	seen := map[string]struct{}{}
	for _, value := range values {
		value = strings.TrimSpace(value)
		if value == "" {
			continue
		}
		if _, ok := seen[value]; ok {
			continue
		}
		seen[value] = struct{}{}
		out = append(out, value)
		if limit > 0 && len(out) >= limit {
			break
		}
	}
	return out
}

func agentFileExtensionLabel(name string) string {
	ext := strings.TrimPrefix(strings.ToLower(strings.TrimSpace(filepath.Ext(name))), ".")
	if ext == "" {
		return ""
	}
	return strings.ToUpper(ext)
}

func applyAgentStructuredMemoryMetadata(metadata map[string]string, memory agentStructuredMemory) {
	memory = memory.normalized()
	if metadata == nil || !memory.hasContext() {
		return
	}
	if memory.CurrentGoal != "" {
		metadata["memory_goal"] = truncateAuditText(memory.CurrentGoal, 80)
	}
	if memory.PrimaryTable != "" {
		metadata["memory_primary_table"] = memory.PrimaryTable
	}
	if len(memory.FocusTables) > 0 {
		metadata["memory_tables"] = strings.Join(memory.FocusTables, ",")
	}
	if summary := memory.filterSummary(); summary != "" {
		metadata["memory_filters"] = truncateAuditText(summary, 120)
	}
	if memory.ExportFormat != "" {
		metadata["memory_export_format"] = memory.ExportFormat
	}
}

func (r agentTaskRecord) normalized() agentTaskRecord {
	r.ID = strings.TrimSpace(r.ID)
	r.SessionID = normalizeAgentSessionID(r.SessionID)
	r.Actor = strings.TrimSpace(r.Actor)
	r.Title = strings.TrimSpace(r.Title)
	r.Goal = strings.TrimSpace(r.Goal)
	r.Status = normalizeAgentTaskStepStatus(r.Status)
	r.Intent = strings.TrimSpace(r.Intent)
	r.PrimaryTable = normalizeAgentTableName(r.PrimaryTable)
	r.FocusTables = normalizeAgentAllowedTables(r.FocusTables)
	r.Filters = copyAgentFilters(r.Filters)
	r.ExportFormat = strings.ToUpper(strings.TrimSpace(r.ExportFormat))
	r.LastTool = strings.TrimSpace(r.LastTool)
	r.LastRunID = strings.TrimSpace(r.LastRunID)
	r.LastUserMessage = strings.TrimSpace(r.LastUserMessage)
	r.LastReply = strings.TrimSpace(r.LastReply)
	return r
}

func normalizeAgentTaskStepStatus(status string) string {
	switch strings.ToLower(strings.TrimSpace(status)) {
	case "done", "success", "ok", "completed":
		return agentTaskStatusDone
	case "failed", "error", "blocked":
		return agentTaskStatusFailed
	case "running", "in_progress", "progress":
		return agentTaskStatusRunning
	case "waiting", "pending", "hold":
		return agentTaskStatusWaiting
	default:
		return agentTaskStatusWaiting
	}
}

func agentTaskStatusText(status string) string {
	switch normalizeAgentTaskStepStatus(status) {
	case agentTaskStatusDone:
		return "已完成"
	case agentTaskStatusFailed:
		return "失败"
	case agentTaskStatusRunning:
		return "执行中"
	default:
		return "等待继续"
	}
}

func deriveAgentTaskStatus(response agentChatResponse) string {
	if !response.OK {
		return agentTaskStatusFailed
	}
	for _, result := range response.ToolResults {
		if !result.OK {
			return agentTaskStatusFailed
		}
	}
	return agentTaskStatusWaiting
}

func deriveAgentPrimaryTable(memory agentStructuredMemory, results []agentToolResult) string {
	if memory.PrimaryTable != "" {
		return normalizeAgentTableName(memory.PrimaryTable)
	}
	for _, result := range results {
		if table := normalizeAgentTableName(result.Table); table != "" {
			if strings.Contains(table, ",") {
				parts := normalizeAgentAllowedTables([]string{table})
				if len(parts) > 0 {
					return parts[0]
				}
			}
			return table
		}
	}
	return ""
}

func deriveAgentFocusTables(memory agentStructuredMemory, results []agentToolResult) []string {
	focus := append([]string(nil), memory.FocusTables...)
	for _, result := range results {
		if strings.TrimSpace(result.Table) == "" {
			continue
		}
		focus = append(focus, normalizeAgentAllowedTables([]string{result.Table})...)
	}
	return normalizeAgentAllowedTables(focus)
}

func deriveAgentExportFormat(memory agentStructuredMemory, response agentChatResponse) string {
	if len(response.Files) > 0 {
		if label := agentFileExtensionLabel(response.Files[0].Name); label != "" {
			return label
		}
	}
	if memory.ExportFormat != "" {
		return memory.ExportFormat
	}
	for _, result := range response.ToolResults {
		if result.File != nil {
			if label := agentFileExtensionLabel(result.File.Name); label != "" {
				return label
			}
		}
	}
	return ""
}

func deriveAgentLastTool(results []agentToolResult) string {
	for i := len(results) - 1; i >= 0; i-- {
		if name := strings.TrimSpace(results[i].Name); name != "" {
			return name
		}
	}
	return ""
}

func copyAgentFilters(filters map[string]string) map[string]string {
	if len(filters) == 0 {
		return nil
	}
	out := make(map[string]string, len(filters))
	for key, value := range filters {
		key = normalizeAgentTableName(key)
		value = strings.TrimSpace(value)
		if key == "" || value == "" {
			continue
		}
		out[key] = value
	}
	if len(out) == 0 {
		return nil
	}
	return out
}

func mergeAgentFilters(base map[string]string, newer map[string]string) map[string]string {
	out := copyAgentFilters(base)
	if out == nil {
		out = map[string]string{}
	}
	for key, value := range copyAgentFilters(newer) {
		out[key] = value
	}
	if len(out) == 0 {
		return nil
	}
	return out
}

func mergeAgentFocusTables(current []string, previous []string) []string {
	merged := append([]string(nil), current...)
	merged = append(merged, previous...)
	return normalizeAgentAllowedTables(merged)
}

func agentFilterSummary(filters map[string]string) string {
	if len(filters) == 0 {
		return ""
	}
	keys := make([]string, 0, len(filters))
	for key := range filters {
		keys = append(keys, key)
	}
	sort.Strings(keys)
	parts := make([]string, 0, len(keys))
	for _, key := range keys {
		parts = append(parts, key+"="+filters[key])
	}
	return strings.Join(parts, "，")
}

func buildCompressedAgentSessionContext(olderRunsDesc []agentRunRecord, toolsByRun map[string][]agentToolResultRecord) string {
	if len(olderRunsDesc) == 0 {
		return ""
	}

	lines := []string{
		fmt.Sprintf("当前会话较早历史已压缩，共 %d 轮，以下保留关键事实与结果：", len(olderRunsDesc)),
	}
	for i := len(olderRunsDesc) - 1; i >= 0; i-- {
		if len(lines)-1 >= agentSessionSummaryRunLimit {
			break
		}
		run := olderRunsDesc[i]
		line := summarizeCompressedAgentRun(run, toolsByRun[run.ID])
		if line == "" {
			continue
		}
		candidate := strings.Join(append(lines, line), "\n")
		if len([]rune(candidate)) > agentSessionSummaryCharLimit {
			break
		}
		lines = append(lines, line)
	}
	if len(lines) == 1 {
		return ""
	}
	return strings.Join(lines, "\n")
}

func agentPersistedAssistantHistory(run agentRunRecord, toolResults []agentToolResultRecord) string {
	parts := make([]string, 0, 2)
	if reply := strings.TrimSpace(run.Reply); reply != "" {
		parts = append(parts, reply)
	}
	summary := summarizeAgentToolResultsForHistory(toolResults)
	if summary != "" {
		parts = append(parts, summary)
	}
	return strings.TrimSpace(strings.Join(parts, "\n"))
}

func summarizeCompressedAgentRun(run agentRunRecord, toolResults []agentToolResultRecord) string {
	message := truncateAuditText(strings.TrimSpace(run.Message), 48)
	if message == "" {
		message = truncateAuditText(strings.TrimSpace(run.Goal), 48)
	}
	if message == "" {
		message = "未命名任务"
	}
	parts := []string{"用户：" + message}
	if goal := truncateAuditText(strings.TrimSpace(run.Goal), 42); goal != "" && goal != message {
		parts = append(parts, "目标："+goal)
	}
	if run.Mode != "" {
		parts = append(parts, "模式："+run.Mode)
	}
	if toolSummary := summarizeAgentToolResultsCompact(toolResults); toolSummary != "" {
		parts = append(parts, toolSummary)
	}
	if reply := truncateAuditText(strings.TrimSpace(run.Reply), 72); reply != "" {
		parts = append(parts, "回复："+reply)
	}
	return strings.Join(parts, "；")
}

func summarizeAgentToolResultsForHistory(results []agentToolResultRecord) string {
	parts := make([]string, 0, len(results))
	for _, result := range results {
		if !result.OK {
			if detail := strings.TrimSpace(result.Error); detail != "" {
				parts = append(parts, "工具结果："+detail)
			}
			continue
		}
		if result.Name != "export_table" {
			continue
		}
		detailParts := make([]string, 0, 4)
		if table := strings.TrimSpace(result.Table); table != "" {
			detailParts = append(detailParts, "表 "+table)
		}
		if fileName := strings.TrimSpace(result.FileName); fileName != "" {
			detailParts = append(detailParts, "文件 "+fileName)
		}
		if summary := strings.TrimSpace(result.Message); summary != "" {
			detailParts = append(detailParts, summary)
		}
		if len(detailParts) > 0 {
			parts = append(parts, "工具结果：导出成功，"+strings.Join(detailParts, "，")+"。")
		}
	}
	return strings.TrimSpace(strings.Join(parts, "\n"))
}

func summarizeAgentToolResultsCompact(results []agentToolResultRecord) string {
	if len(results) == 0 {
		return ""
	}
	parts := make([]string, 0, len(results))
	for i, result := range results {
		if i >= 2 {
			break
		}
		if !result.OK {
			detail := truncateAuditText(strings.TrimSpace(result.Error), 60)
			if detail != "" {
				parts = append(parts, "拦截："+detail)
			}
			continue
		}
		switch result.Name {
		case "export_table":
			detail := "导出"
			if table := strings.TrimSpace(result.Table); table != "" {
				detail += " " + table
			}
			if fileName := strings.TrimSpace(result.FileName); fileName != "" {
				detail += " -> " + fileName
			}
			parts = append(parts, detail)
		case "generate_image":
			detail := "生成图片"
			if fileName := strings.TrimSpace(result.FileName); fileName != "" {
				detail += " -> " + fileName
			}
			parts = append(parts, detail)
		case "query_readonly":
			detail := "只读查询"
			if table := strings.TrimSpace(result.Table); table != "" {
				detail += " " + table
			}
			if result.RowCount > 0 {
				detail += fmt.Sprintf(" 返回 %d 行", result.RowCount)
			}
			parts = append(parts, detail)
		case "count_table":
			detail := "统计"
			if table := strings.TrimSpace(result.Table); table != "" {
				detail += " " + table
			}
			if msg := truncateAuditText(strings.TrimSpace(result.Message), 36); msg != "" {
				detail += " " + msg
			}
			parts = append(parts, detail)
		case "preview_table":
			detail := "预览"
			if table := strings.TrimSpace(result.Table); table != "" {
				detail += " " + table
			}
			parts = append(parts, detail)
		case "describe_table":
			detail := "读取字段结构"
			if table := strings.TrimSpace(result.Table); table != "" {
				detail += " " + table
			}
			parts = append(parts, detail)
		case "list_tables":
			parts = append(parts, "列出可读数据表")
		case "access_scope":
			parts = append(parts, "读取权限快照")
		default:
			if msg := truncateAuditText(strings.TrimSpace(result.Message), 48); msg != "" {
				parts = append(parts, msg)
			}
		}
	}
	if len(results) > 2 {
		parts = append(parts, fmt.Sprintf("另有 %d 个工具结果", len(results)-2))
	}
	return strings.Join(parts, "；")
}

func latestSessionExportContinuation(run agentRunRecord, toolResults []agentToolResultRecord) *agentExportContinuation {
	if strings.TrimSpace(run.Message) == "" {
		return nil
	}
	for _, result := range toolResults {
		if result.Name != "export_table" || !result.OK {
			continue
		}
		return &agentExportContinuation{
			UserMessage: strings.TrimSpace(run.Message),
			Reply:       strings.TrimSpace(run.Reply),
			Table:       strings.TrimSpace(result.Table),
			FileName:    strings.TrimSpace(result.FileName),
		}
	}
	return nil
}

func latestSessionImageContinuation(toolResults []agentToolResultRecord) *agentFileResult {
	for _, result := range toolResults {
		if result.Name != "generate_image" || !result.OK {
			continue
		}
		fileName := strings.TrimSpace(result.FileName)
		fileURL := strings.TrimSpace(result.FileURL)
		if fileName == "" || fileURL == "" {
			continue
		}
		mimeType, ok := agentExportContentType(fileName)
		if !ok || !strings.HasPrefix(mimeType, "image/") {
			continue
		}
		return &agentFileResult{
			Name:        fileName,
			URL:         fileURL,
			MIME:        mimeType,
			Description: "上一张生成图片",
		}
	}
	return nil
}

func resolveAgentContinuationMessage(message string, ctx agentSessionContext) string {
	message = strings.TrimSpace(message)
	if message == "" {
		return message
	}
	if _, ok := extractAgentSQL(message); ok {
		return message
	}
	if len(inferAgentExplicitTablesFromMessage(message)) > 0 {
		return message
	}
	ctx.Memory = ctx.Memory.normalized()
	if shouldReuseStructuredTaskMemory(message, ctx.Memory) {
		return synthesizeStructuredContinuationMessage(message, ctx.Memory, ctx.LastExport)
	}
	if ctx.LastExport == nil || !shouldReuseLastExportContext(message) {
		return message
	}
	base := strings.TrimSpace(ctx.LastExport.UserMessage)
	if base == "" {
		return message
	}
	return strings.TrimSpace(base + "；保持和上一份导出相同的数据范围与筛选条件，另外" + message)
}

func shouldReuseLastExportContext(message string) bool {
	lower := strings.ToLower(strings.TrimSpace(message))
	if lower == "" {
		return false
	}
	if containsAny(lower, "xlsx", "excel", "xls", "csv", "json", "格式", "转为", "改成", "换成", "另存为", "重新导出", "重导", "再导一份", "重新生成", "这个文件", "刚才那个", "刚才这份", "上一份", "上一个导出", "保持同样", "只保留", "前10", "前 10", "limit ") {
		return true
	}
	return false
}

func shouldReuseLastImagePreview(message string, lastImage *agentFileResult, memory agentStructuredMemory) bool {
	if lastImage == nil || strings.TrimSpace(lastImage.URL) == "" {
		return false
	}
	lower := strings.ToLower(strings.TrimSpace(message))
	if lower == "" {
		return false
	}
	if containsAny(lower, "改成", "换成", "重新生成", "重做", "再画", "再生成", "再来一张", "横版", "竖版", "方图", "加文字", "去文字") {
		return false
	}
	showIntent := containsAny(lower, "发给我", "展示", "预览", "打开", "给我看", "看看", "看下", "看一下", "再发", "重新发", "贴出来", "贴给我")
	target := containsAny(lower, "图", "图片", "海报", "封面", "插图", "配图", "横幅")
	if showIntent && target {
		return true
	}
	memory = memory.normalized()
	if memory.LastTool == "generate_image" && containsAny(lower, "刚才", "上一张", "上一个", "这张", "那个", "那张") && target {
		return true
	}
	return len([]rune(lower)) <= 12 && target && containsAny(lower, "发我", "预览", "看看", "给我看")
}

func shouldReuseStructuredTaskMemory(message string, memory agentStructuredMemory) bool {
	memory = memory.normalized()
	if !memory.hasContext() {
		return false
	}
	lower := strings.ToLower(strings.TrimSpace(message))
	if lower == "" {
		return false
	}
	if shouldReuseLastExportContext(message) {
		return true
	}
	if containsAny(lower, "继续", "接着", "刚才", "上一份", "上一个", "上面", "这个", "这些", "那些", "同样", "一样", "沿用", "改成", "换成", "再来", "再查", "再导", "只看", "只要", "只保留", "筛选", "过滤", "启用", "禁用", "再给我", "还是", "另外") {
		return true
	}
	return len([]rune(lower)) <= 20
}

func synthesizeStructuredContinuationMessage(message string, memory agentStructuredMemory, lastExport *agentExportContinuation) string {
	memory = memory.normalized()
	if !memory.hasContext() {
		return message
	}
	parts := make([]string, 0, 6)
	anchor := strings.TrimSpace(firstNonEmpty(memory.TaskAnchorMessage, memory.CurrentGoal))
	if anchor != "" {
		parts = append(parts, anchor)
	}
	if memory.PrimaryTable != "" {
		parts = append(parts, "沿用当前任务表 "+memory.PrimaryTable)
	}
	if summary := memory.filterSummary(); summary != "" && shouldCarryStructuredFilters(message) {
		parts = append(parts, "保持当前筛选条件 "+summary)
	}
	if memory.ExportFormat != "" && lastExport != nil {
		parts = append(parts, "沿用上一份导出的数据范围")
	}
	parts = append(parts, "本次补充要求："+message)
	return strings.Join(parts, "；")
}

func shouldCarryStructuredFilters(message string) bool {
	lower := strings.ToLower(strings.TrimSpace(message))
	if lower == "" {
		return false
	}
	if containsAny(lower, "全部", "清空筛选", "取消筛选", "不过滤", "所有") {
		return false
	}
	return true
}

func agentRunStatus(ok bool) string {
	if ok {
		return "ok"
	}
	return "failed"
}

func boolText(value bool) string {
	if value {
		return "是"
	}
	return "否"
}

func summarizeSchemaSnapshotChecks(checksJSON string) string {
	var checks []string
	if err := json.Unmarshal([]byte(strings.TrimSpace(checksJSON)), &checks); err != nil || len(checks) == 0 {
		return strings.TrimSpace(checksJSON)
	}
	if len(checks) > 6 {
		checks = checks[:6]
	}
	return strings.Join(checks, "；")
}

func shortSchemaHash(hash string) string {
	hash = strings.TrimSpace(hash)
	if len(hash) > 12 {
		return hash[:12]
	}
	return hash
}

func newTableToolProvider(state installState, exportDir string, downloadBasePath string, auditEvents ...[]adminAuditEvent) tableToolProvider {
	state.Database = state.Database.sanitized()
	state.AI = state.AI.sanitized()
	events := []adminAuditEvent(nil)
	if len(auditEvents) > 0 {
		events = auditEvents[0]
	}
	return tableToolProvider{
		state:              state,
		exportDir:          strings.TrimSpace(exportDir),
		downloadBasePath:   strings.TrimRight(strings.TrimSpace(downloadBasePath), "/"),
		allowReadOnlyQuery: true,
		auditEvents:        events,
	}
}

func (p tableToolProvider) tableDefinitions() []agentTableDefinition {
	definitions := append([]agentTableDefinition(nil), agentTableDefinitions()...)
	for _, table := range p.externalAgentTables() {
		aliases := []string{
			table.RawName,
			table.DisplayName,
			externalAgentTableCommentAlias(table.Description),
			table.Source.Name,
			table.Source.Role,
			"外部数据源",
			"业务数据源",
			"业务库",
		}
		for _, column := range table.Columns {
			aliases = append(aliases, column.Name, column.Description)
		}
		definitions = append(definitions, agentTableDefinition{
			Name:        table.ID,
			Type:        "external_data_source",
			DisplayName: table.DisplayName,
			Description: table.Description,
			Aliases:     aliases,
		})
	}
	return definitions
}

func externalAgentTableCommentAlias(description string) string {
	description = strings.TrimSpace(description)
	if idx := strings.LastIndex(description, "："); idx >= 0 && idx+len("：") < len(description) {
		return strings.TrimSpace(description[idx+len("："):])
	}
	return ""
}

func (p tableToolProvider) externalAgentTables() []agentExternalTable {
	sources := map[string]dataSourceConfig{}
	for _, source := range normalizeDataSources(p.state.DataSources) {
		source = source.normalized()
		if source.Name == "" || source.Status != "available" {
			continue
		}
		sources[strings.ToLower(source.Name)] = source
	}
	if len(sources) == 0 || len(p.schemaSnapshots) == 0 {
		return nil
	}

	latest := map[string]adminSchemaSnapshotRecord{}
	for _, snapshot := range p.schemaSnapshots {
		key := strings.ToLower(strings.TrimSpace(snapshot.DataSourceName))
		if key == "" {
			continue
		}
		if _, ok := latest[key]; !ok {
			latest[key] = snapshot
		}
	}

	usedIDs := map[string]int{}
	tables := make([]agentExternalTable, 0)
	for key, source := range sources {
		snapshot, ok := latest[key]
		if !ok || strings.TrimSpace(snapshot.SchemaJSON) == "" {
			continue
		}
		var inspected []inspectedDatabaseTable
		if err := json.Unmarshal([]byte(snapshot.SchemaJSON), &inspected); err != nil {
			continue
		}
		for _, inspectedTable := range inspected {
			rawTable := strings.TrimSpace(inspectedTable.Name)
			if rawTable == "" {
				continue
			}
			baseID := externalAgentTableID(source.Name, rawTable)
			id := baseID
			if usedIDs[id] > 0 {
				id = baseID + "_" + strconv.Itoa(usedIDs[id]+1)
			}
			usedIDs[baseID]++
			columns, rawColumns := externalAgentColumns(inspectedTable.Columns)
			if len(columns) == 0 {
				continue
			}
			comment := normalizeSchemaComment(inspectedTable.Comment)
			description := "外部数据源 `" + source.Name + "` 的真实表 `" + rawTable + "`"
			if comment != "" {
				description += "：" + comment
			}
			tables = append(tables, agentExternalTable{
				ID:          id,
				Source:      source,
				RawName:     rawTable,
				DisplayName: source.Name + "." + rawTable,
				Description: description,
				Columns:     columns,
				RawColumns:  rawColumns,
			})
		}
	}
	sort.SliceStable(tables, func(i, j int) bool {
		return tables[i].ID < tables[j].ID
	})
	return tables
}

func externalAgentColumns(columns []inspectedDatabaseColumn) ([]agentTableColumn, map[string]string) {
	out := make([]agentTableColumn, 0, len(columns))
	rawByName := map[string]string{}
	used := map[string]int{}
	for _, column := range columns {
		raw := strings.TrimSpace(column.Name)
		if raw == "" {
			continue
		}
		name := slugAgentIdentifier(raw)
		if used[name] > 0 {
			name = name + "_" + strconv.Itoa(used[name]+1)
		}
		used[slugAgentIdentifier(raw)]++
		description := normalizeSchemaComment(column.Comment)
		if description == "" {
			description = raw
		}
		columnType := strings.TrimSpace(column.Type)
		if columnType == "" {
			columnType = "string"
		}
		out = append(out, agentTableColumn{Name: name, Type: columnType, Description: description})
		rawByName[name] = raw
	}
	return out, rawByName
}

func (p tableToolProvider) externalAgentTableByName(name string) (agentExternalTable, bool) {
	name = normalizeAgentTableName(name)
	for _, table := range p.externalAgentTables() {
		if table.ID == name {
			return table, true
		}
	}
	return agentExternalTable{}, false
}

func externalAgentTableID(sourceName string, tableName string) string {
	return "datasource." + slugAgentIdentifier(sourceName) + "." + slugAgentIdentifier(tableName)
}

func slugAgentIdentifier(value string) string {
	value = strings.ToLower(strings.TrimSpace(value))
	var builder strings.Builder
	lastUnderscore := false
	for _, r := range value {
		valid := (r >= 'a' && r <= 'z') || (r >= '0' && r <= '9')
		if valid {
			builder.WriteRune(r)
			lastUnderscore = false
			continue
		}
		if r == '_' || r == '-' || r == '.' || r == ' ' || r == '\t' || r == '\n' || r == '\r' {
			if builder.Len() > 0 && !lastUnderscore {
				builder.WriteRune('_')
				lastUnderscore = true
			}
		}
	}
	out := strings.Trim(builder.String(), "_")
	if out == "" {
		out = "item"
	}
	if out[0] >= '0' && out[0] <= '9' {
		out = "t_" + out
	}
	return out
}

func (p tableToolProvider) withAllowedTables(tables []string) tableToolProvider {
	normalized := normalizeAgentAllowedTables(tables)
	if len(normalized) == 0 {
		p.allowedTables = nil
		return p
	}
	p.allowedTables = make(map[string]struct{}, len(normalized))
	for _, table := range normalized {
		p.allowedTables[table] = struct{}{}
	}
	return p
}

const (
	agentTableAccessAll    = "all"
	agentTableAccessTables = "tables"
	agentTableAccessNone   = "none"
)

func newAgentQueryScope(mode string, tables []string) agentQueryScope {
	normalized := normalizeAgentAllowedTables(tables)
	effective := effectiveAgentTableAccessMode(mode, normalized)
	if effective != agentTableAccessTables {
		normalized = nil
	}
	return agentQueryScope{
		Mode:   effective,
		Tables: append([]string(nil), normalized...),
	}
}

func (s agentQueryScope) Metadata() string {
	switch s.Mode {
	case agentTableAccessNone:
		return "none"
	case agentTableAccessTables:
		return strings.Join(normalizeAgentAllowedTables(s.Tables), ",")
	default:
		return "all"
	}
}

func (s agentQueryScope) Instruction() string {
	normalized := normalizeAgentAllowedTables(s.Tables)
	switch effectiveAgentTableAccessMode(s.Mode, normalized) {
	case agentTableAccessNone:
		return "当前会话禁用了数据表查询，不能读取任何数据表、字段结构或导出数据。"
	case agentTableAccessAll:
		return "当前会话明确启用了全部只读表，可读取所有已登记数据表和虚拟表的只读数据。"
	default:
		return "当前会话启用了数据表白名单，只允许读取：" + strings.Join(normalized, "、") + "。任何未在白名单内的数据表都必须视为未授权，不能声称拥有读取权限，也不能根据历史记忆补答。"
	}
}

func (s agentQueryScope) ReplyNote() string {
	normalized := normalizeAgentAllowedTables(s.Tables)
	switch effectiveAgentTableAccessMode(s.Mode, normalized) {
	case agentTableAccessNone:
		return "当前通道未启用数据查询。"
	case agentTableAccessAll:
		return "当前通道可读取全部只读表。"
	default:
		return "当前通道只允许读取：" + strings.Join(normalized, "、") + "。"
	}
}

func normalizeAgentTableAccessMode(mode string) string {
	switch strings.ToLower(strings.TrimSpace(mode)) {
	case "", "all", "*", "全部", "全部只读":
		return agentTableAccessAll
	case "tables", "table", "allowlist", "whitelist", "limited", "指定表", "指定数据表":
		return agentTableAccessTables
	case "none", "disabled", "off", "deny", "禁用", "禁用数据查询":
		return agentTableAccessNone
	default:
		return agentTableAccessAll
	}
}

func effectiveAgentTableAccessMode(mode string, tables []string) string {
	mode = normalizeAgentTableAccessMode(mode)
	if mode == agentTableAccessTables && len(normalizeAgentAllowedTables(tables)) == 0 {
		return agentTableAccessNone
	}
	return mode
}

func (p tableToolProvider) withTableAccess(mode string, tables []string) tableToolProvider {
	return p.withQueryScope(newAgentQueryScope(mode, tables))
}

func (p tableToolProvider) withQueryScope(scope agentQueryScope) tableToolProvider {
	switch effectiveAgentTableAccessMode(scope.Mode, scope.Tables) {
	case agentTableAccessNone:
		p.allowedTables = nil
		p.denyAllTables = true
		return p
	case agentTableAccessTables:
		return p.withAllowedTables(scope.Tables)
	default:
		p.allowedTables = nil
		p.denyAllTables = false
		return p
	}
}

func (p tableToolProvider) withStructuredMemory(memory agentStructuredMemory) tableToolProvider {
	p.memory = memory.normalized()
	return p
}

func (p tableToolProvider) withLastExport(lastExport *agentExportContinuation) tableToolProvider {
	p.lastExport = lastExport
	return p
}

func (p tableToolProvider) withRequestMessage(message string) tableToolProvider {
	p.rawMessage = strings.TrimSpace(message)
	return p
}

func normalizeAgentAllowedTables(tables []string) []string {
	out := make([]string, 0, len(tables))
	seen := map[string]struct{}{}
	for _, table := range tables {
		for _, part := range strings.FieldsFunc(table, func(r rune) bool {
			return r == ',' || r == '，' || r == '\n' || r == '\r' || r == '\t' || r == ';' || r == '；' || r == ' '
		}) {
			name := normalizeAgentTableName(part)
			if name == "" {
				continue
			}
			if name == "*" || name == "all" || name == "全部" {
				return nil
			}
			if _, ok := seen[name]; ok {
				continue
			}
			seen[name] = struct{}{}
			out = append(out, name)
		}
	}
	return out
}

func agentAllowedTablesString(tables []string) string {
	normalized := normalizeAgentAllowedTables(tables)
	return strings.Join(normalized, ", ")
}

func agentAuthorizationMetadata(mode string, tables []string) string {
	return newAgentQueryScope(mode, tables).Metadata()
}

func agentAuthorizationInstruction(mode string, tables []string) string {
	return newAgentQueryScope(mode, tables).Instruction()
}

func agentAuthorizationReplyNote(mode string, tables []string) string {
	return newAgentQueryScope(mode, tables).ReplyNote()
}

func agentWebAuthorizationInstruction(allowed bool) string {
	if !allowed {
		return "当前会话未启用公开网页访问，不能抓取网页或执行联网检索。"
	}
	return "当前会话允许访问公开 http/https 页面，但默认拒绝 localhost、127.0.0.1、内网地址和其他受保护目标。"
}

func agentImageAuthorizationInstruction(allowed bool) string {
	if !allowed {
		return "当前会话未启用图片创作能力，不能调用图片模型生成海报、插图或封面图。"
	}
	return "当前会话允许调用图片模型生成图片，并会把生成结果保存为后台文件返回。"
}

func (p tableToolProvider) isTableAuthorized(table string) bool {
	table = normalizeAgentTableName(table)
	if p.denyAllTables {
		return false
	}
	if table == "" || len(p.allowedTables) == 0 {
		return true
	}
	_, ok := p.allowedTables[table]
	return ok
}

func (p tableToolProvider) authorizedDefinitions() []agentTableDefinition {
	if p.denyAllTables {
		return nil
	}
	definitions := filterAgentCatalogDefinitions(p.tableDefinitions())
	if len(p.allowedTables) == 0 {
		return definitions
	}
	out := make([]agentTableDefinition, 0, len(definitions))
	for _, definition := range definitions {
		if _, ok := p.allowedTables[definition.Name]; ok {
			out = append(out, definition)
		}
	}
	return out
}

func (p tableToolProvider) authorizedTableSummary() string {
	if p.denyAllTables {
		return "未授权任何数据表"
	}
	definitions := p.authorizedDefinitions()
	names := make([]string, 0, minInt(len(definitions), 10))
	known := map[string]struct{}{}
	for _, definition := range definitions {
		known[definition.Name] = struct{}{}
		if len(names) >= 10 {
			break
		}
		names = append(names, definition.Name+"("+definition.DisplayName+")")
	}
	if len(p.allowedTables) > 0 && len(names) < 10 {
		extra := make([]string, 0, len(p.allowedTables))
		for table := range p.allowedTables {
			if _, ok := known[table]; ok {
				continue
			}
			extra = append(extra, table)
		}
		sort.Strings(extra)
		for _, table := range extra {
			if len(names) >= 10 {
				break
			}
			names = append(names, table)
		}
	}
	if len(names) == 0 {
		return "无授权数据表"
	}
	total := len(definitions)
	if len(p.allowedTables) > 0 && len(p.allowedTables) > total {
		total = len(p.allowedTables)
	}
	if total > len(names) {
		names = append(names, "等 "+strconv.Itoa(total)+" 张")
	}
	return strings.Join(names, "、")
}

func (p tableToolProvider) unauthorizedTableResult(toolName string, table string) agentToolResult {
	table = normalizeAgentTableName(table)
	return agentToolResult{
		Name:  toolName,
		OK:    false,
		Table: table,
		Error: "当前账号未授权查询 `" + table + "`。已授权：" + p.authorizedTableSummary() + "。",
	}
}

func (p tableToolProvider) accessWeb(message string) agentToolResult {
	urls := extractAgentURLs(message)
	if len(urls) > 0 {
		return p.fetchWebPage(urls[0])
	}
	return p.searchWeb(message)
}

func (p tableToolProvider) unauthorizedWebResult(toolName string) agentToolResult {
	return agentToolResult{
		Name:  toolName,
		OK:    false,
		Error: "当前账号未授予 `agent.web.read`，不能访问公开网页或执行联网检索。",
	}
}

func (p tableToolProvider) unauthorizedImageResult(toolName string) agentToolResult {
	return agentToolResult{
		Name:  toolName,
		OK:    false,
		Error: "当前账号未授予 `agent.image.generate`，不能生成图片。",
	}
}

func (p tableToolProvider) generateImage(message string) agentToolResult {
	result := agentToolResult{
		Name:    "generate_image",
		Columns: []string{"file", "format", "size"},
	}
	if !p.allowImageGenerate {
		return p.unauthorizedImageResult(result.Name)
	}
	if p.exportDir == "" {
		result.OK = false
		result.Error = "图片输出目录未配置。"
		return result
	}
	if err := os.MkdirAll(p.exportDir, 0o755); err != nil {
		result.OK = false
		result.Error = "创建图片输出目录失败：" + err.Error()
		return result
	}

	ai := p.state.AI.sanitized()
	if ai.IsDisabled() {
		result.OK = false
		result.Error = "当前后台尚未启用 AI 图片模型。"
		return result
	}
	if err := validateAIConfig(ai); err != nil {
		result.OK = false
		result.Error = "AI 配置不可用：" + err.Error()
		return result
	}
	endpoint, err := ai.imageGenerationURL()
	if err != nil {
		result.OK = false
		result.Error = "图片模型接口地址不正确：" + err.Error()
		return result
	}

	originalPrompt := strings.TrimSpace(message)
	if originalPrompt == "" {
		result.OK = false
		result.Error = "请输入要生成的图片描述。"
		return result
	}
	prompt := buildAgentImagePrompt(originalPrompt)
	size := inferAgentImageSize(originalPrompt)

	body, _ := json.Marshal(map[string]any{
		"model": ai.DisplayImageModel(),
		"input": map[string]any{
			"messages": []map[string]any{
				{
					"role": "user",
					"content": []map[string]string{
						{"type": "text", "text": prompt},
					},
				},
			},
		},
		"parameters": map[string]any{
			"size": size,
		},
	})
	ctx, cancel := context.WithTimeout(context.Background(), defaultAgentImageTimeout)
	defer cancel()
	req, err := http.NewRequestWithContext(ctx, http.MethodPost, endpoint, bytes.NewReader(body))
	if err != nil {
		result.OK = false
		result.Error = "创建图片生成请求失败：" + err.Error()
		return result
	}
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("Authorization", "Bearer "+ai.APIKey)

	resp, err := agentImageHTTPClient.Do(req)
	if err != nil {
		result.OK = false
		result.Error = "图片模型请求失败：" + err.Error()
		return result
	}
	defer resp.Body.Close()
	respBody, _ := io.ReadAll(io.LimitReader(resp.Body, 512*1024))
	if resp.StatusCode < 200 || resp.StatusCode >= 300 {
		result.OK = false
		result.Error = fmt.Sprintf("图片模型返回 %d：%s", resp.StatusCode, truncateAgentText(string(respBody), 180))
		return result
	}

	artifact, err := extractAgentGeneratedImageArtifact(respBody)
	if err != nil {
		result.OK = false
		result.Error = "图片模型没有返回可用图片：" + err.Error()
		return result
	}
	imageBytes, mimeType, sourceURL, err := downloadAgentGeneratedImage(ctx, artifact)
	if err != nil {
		result.OK = false
		result.Error = "下载生成图片失败：" + err.Error()
		return result
	}
	if detected := strings.TrimSpace(http.DetectContentType(imageBytes)); mimeType == "" || mimeType == "application/octet-stream" {
		mimeType = detected
	}
	ext := agentImageExtension(mimeType, sourceURL)
	now := time.Now()
	fileName := "moyi-agent-image-" + now.Format("20060102-150405") + "-" + strconv.FormatInt(now.UnixNano()%1_000_000, 10) + "." + ext
	filePath := filepath.Join(p.exportDir, fileName)
	if err := os.WriteFile(filePath, imageBytes, 0o600); err != nil {
		result.OK = false
		result.Error = "保存生成图片失败：" + err.Error()
		return result
	}

	fileResult := agentFileResult{
		Name:           fileName,
		URL:            p.downloadBasePath + "/" + fileName,
		MIME:           mimeType,
		Size:           int64(len(imageBytes)),
		Description:    "文生图结果：" + truncateAgentText(originalPrompt, 72),
		Prompt:         prompt,
		OriginalPrompt: originalPrompt,
	}
	result.OK = true
	result.Message = "已生成 1 张图片，并按整理后的绘图提示词保存为后台文件。"
	result.File = &fileResult
	result.Rows = []map[string]string{{
		"file":   fileName,
		"format": strings.ToUpper(ext),
		"size":   strconv.Itoa(len(imageBytes)),
	}}
	return result
}

func inferAgentImageSize(message string) string {
	lower := strings.ToLower(strings.TrimSpace(message))
	switch {
	case containsAny(lower, "横版", "横幅", "横向", "banner", "landscape", "16:9", "宽屏"):
		return "1664*928"
	case containsAny(lower, "竖版", "竖向", "封面", "海报", "portrait", "9:16"):
		return "928*1664"
	case containsAny(lower, "方图", "方形", "正方形", "头像", "1:1", "square"):
		return "1024*1024"
	default:
		return "1024*1024"
	}
}

func buildAgentImagePrompt(message string) string {
	original := strings.TrimSpace(message)
	if original == "" {
		return ""
	}
	purpose := agentImagePurpose(original)
	composition := agentImageComposition(original)
	style := agentImageStyleSummary(original)
	textRule := agentImageTextRule(original)
	mediumRule := agentImageMediumRule(original)
	return strings.Join([]string{
		"用户原始需求：" + original,
		"请围绕这个需求生成一张" + purpose + "，采用" + composition + "。",
		"视觉方向：" + style + "。",
		"画面要求：主体明确，层次清晰，构图完整，留白自然，避免杂乱，质感统一，细节干净，整体更像成熟的商业视觉而不是随意拼贴。",
		mediumRule,
		textRule,
	}, "\n")
}

func agentImagePurpose(message string) string {
	lower := strings.ToLower(strings.TrimSpace(message))
	switch {
	case containsAny(lower, "海报", "poster"):
		return "宣传海报"
	case containsAny(lower, "封面", "cover"):
		return "封面主视觉"
	case containsAny(lower, "横幅", "banner", "头图"):
		return "横幅主视觉"
	case containsAny(lower, "插图", "配图", "插画", "illustration"):
		return "插图配图"
	default:
		return "视觉主图"
	}
}

func agentImageComposition(message string) string {
	lower := strings.ToLower(strings.TrimSpace(message))
	switch {
	case containsAny(lower, "横版", "横幅", "横向", "banner", "landscape", "16:9", "宽屏"):
		return "横版宽幅构图"
	case containsAny(lower, "竖版", "竖向", "封面", "portrait", "9:16"):
		return "竖版封面构图"
	case containsAny(lower, "方图", "方形", "正方形", "头像", "1:1", "square"):
		return "方形聚焦构图"
	default:
		return "主体清晰的平衡构图"
	}
}

func agentImageStyleSummary(message string) string {
	lower := strings.ToLower(strings.TrimSpace(message))
	style := make([]string, 0, 8)
	if containsAny(lower, "科技", "tech", "未来", "数字化", "后台") {
		style = append(style, "蓝绿冷调", "科技感", "轻微发光", "现代数字界面气质")
	}
	if containsAny(lower, "公益", "慈善", "温暖", "希望", "爱心") {
		style = append(style, "温暖可信", "克制真诚", "具有公益叙事感")
	}
	if containsAny(lower, "简洁", "极简", "干净", "minimal") {
		style = append(style, "简洁", "留白充足", "信息噪点少")
	}
	if containsAny(lower, "高级", "质感", "品牌", "banner", "海报", "封面") {
		style = append(style, "高完成度", "品牌视觉感", "适合正式对外展示")
	}
	if containsAny(lower, "插图", "插画", "illustration", "配图") {
		style = append(style, "高级插画风格", "形体概括明确")
	}
	if containsAny(lower, "写实", "真实", "摄影", "照片", "photo", "realistic") {
		style = append(style, "高质量写实风格")
	}
	if len(style) == 0 {
		style = append(style, "现代", "干净", "构图明确", "视觉重点集中")
	}
	return strings.Join(uniqueAgentPromptParts(style), "、")
}

func agentImageTextRule(message string) string {
	lower := strings.ToLower(strings.TrimSpace(message))
	if containsAny(lower, "文字", "文案", "标题", "标语", "slogan", "logo") {
		return "如果需要文字，请确保文字极少、排版清晰、可读性强，并与画面整体风格统一。"
	}
	if containsAny(lower, "不要字", "无字", "不要文字", "不要文案", "不要 logo", "不要logo") {
		return "不要出现任何文字、logo、水印、二维码或界面截图。"
	}
	return "除非用户明确要求，否则不要出现任何文字、logo、水印、二维码、边框或界面截图。"
}

func agentImageMediumRule(message string) string {
	lower := strings.ToLower(strings.TrimSpace(message))
	if containsAny(lower, "写实", "真实", "摄影", "照片", "photo", "realistic") {
		return "请优先输出自然可信的写实摄影质感，不要过度插画化。"
	}
	if containsAny(lower, "图标", "icon", "logo") {
		return "请保持图形简洁、边缘干净、识别度高。"
	}
	return "如果用户没有明确要求真实摄影，请优先输出高级插画或成熟视觉设计风格。"
}

func uniqueAgentPromptParts(values []string) []string {
	out := make([]string, 0, len(values))
	seen := make(map[string]struct{}, len(values))
	for _, value := range values {
		value = strings.TrimSpace(value)
		if value == "" {
			continue
		}
		if _, ok := seen[value]; ok {
			continue
		}
		seen[value] = struct{}{}
		out = append(out, value)
	}
	return out
}

func extractAgentGeneratedImageArtifact(body []byte) (agentGeneratedImageArtifact, error) {
	var payload any
	if err := json.Unmarshal(body, &payload); err != nil {
		return agentGeneratedImageArtifact{}, err
	}
	artifact := findAgentGeneratedImageArtifact(payload)
	if artifact.URL == "" && artifact.Base64 == "" {
		return agentGeneratedImageArtifact{}, errors.New(truncateAgentText(string(body), 180))
	}
	return artifact, nil
}

func findAgentGeneratedImageArtifact(node any) agentGeneratedImageArtifact {
	switch value := node.(type) {
	case map[string]any:
		artifact := agentGeneratedImageArtifact{}
		if mimeType, ok := value["mime_type"].(string); ok {
			artifact.MIMEType = strings.TrimSpace(mimeType)
		}
		for _, key := range []string{"url", "image_url", "image"} {
			raw, _ := value[key].(string)
			raw = strings.TrimSpace(raw)
			if raw == "" {
				continue
			}
			if strings.HasPrefix(strings.ToLower(raw), "http://") || strings.HasPrefix(strings.ToLower(raw), "https://") || strings.HasPrefix(strings.ToLower(raw), "data:image/") {
				artifact.URL = raw
				return artifact
			}
			if looksLikeBase64ImagePayload(raw) {
				artifact.Base64 = raw
				return artifact
			}
		}
		for _, key := range []string{"b64_json", "base64", "image_base64"} {
			raw, _ := value[key].(string)
			raw = strings.TrimSpace(raw)
			if raw == "" {
				continue
			}
			artifact.Base64 = raw
			return artifact
		}
		for _, child := range value {
			found := findAgentGeneratedImageArtifact(child)
			if found.URL != "" || found.Base64 != "" {
				if found.MIMEType == "" {
					found.MIMEType = artifact.MIMEType
				}
				return found
			}
		}
	case []any:
		for _, child := range value {
			found := findAgentGeneratedImageArtifact(child)
			if found.URL != "" || found.Base64 != "" {
				return found
			}
		}
	}
	return agentGeneratedImageArtifact{}
}

func looksLikeBase64ImagePayload(value string) bool {
	value = strings.TrimSpace(value)
	if len(value) < 64 {
		return false
	}
	for _, r := range value {
		if (r >= 'a' && r <= 'z') || (r >= 'A' && r <= 'Z') || (r >= '0' && r <= '9') || r == '+' || r == '/' || r == '=' || r == '-' || r == '_' {
			continue
		}
		return false
	}
	return true
}

func downloadAgentGeneratedImage(ctx context.Context, artifact agentGeneratedImageArtifact) ([]byte, string, string, error) {
	if strings.HasPrefix(strings.ToLower(strings.TrimSpace(artifact.URL)), "data:image/") {
		data, mimeType, err := decodeAgentDataImageURL(artifact.URL)
		return data, firstNonEmpty(mimeType, artifact.MIMEType), "", err
	}
	if strings.TrimSpace(artifact.Base64) != "" {
		data, err := decodeAgentBase64Image(artifact.Base64)
		return data, firstNonEmpty(artifact.MIMEType, "image/png"), "", err
	}
	req, err := http.NewRequestWithContext(ctx, http.MethodGet, artifact.URL, nil)
	if err != nil {
		return nil, "", "", err
	}
	resp, err := agentImageHTTPClient.Do(req)
	if err != nil {
		return nil, "", "", err
	}
	defer resp.Body.Close()
	if resp.StatusCode < 200 || resp.StatusCode >= 300 {
		body, _ := io.ReadAll(io.LimitReader(resp.Body, 32*1024))
		return nil, "", "", fmt.Errorf("image download returned %d: %s", resp.StatusCode, truncateAgentText(string(body), 120))
	}
	data, err := io.ReadAll(io.LimitReader(resp.Body, 20*1024*1024))
	if err != nil {
		return nil, "", "", err
	}
	return data, firstNonEmpty(strings.TrimSpace(resp.Header.Get("Content-Type")), artifact.MIMEType), artifact.URL, nil
}

func decodeAgentDataImageURL(raw string) ([]byte, string, error) {
	parts := strings.SplitN(raw, ",", 2)
	if len(parts) != 2 {
		return nil, "", errors.New("data URL 格式不正确")
	}
	meta := strings.TrimSpace(parts[0])
	mimeType := "image/png"
	if strings.HasPrefix(strings.ToLower(meta), "data:") {
		mimeType = strings.TrimPrefix(strings.SplitN(strings.TrimPrefix(meta, "data:"), ";", 2)[0], " ")
	}
	data, err := decodeAgentBase64Image(parts[1])
	return data, mimeType, err
}

func decodeAgentBase64Image(raw string) ([]byte, error) {
	raw = strings.TrimSpace(raw)
	if raw == "" {
		return nil, errors.New("图片内容为空")
	}
	if data, err := base64.StdEncoding.DecodeString(raw); err == nil {
		return data, nil
	}
	if data, err := base64.RawStdEncoding.DecodeString(raw); err == nil {
		return data, nil
	}
	if data, err := base64.URLEncoding.DecodeString(raw); err == nil {
		return data, nil
	}
	return base64.RawURLEncoding.DecodeString(raw)
}

func agentImageExtension(mimeType string, sourceURL string) string {
	mimeType = strings.ToLower(strings.TrimSpace(strings.SplitN(mimeType, ";", 2)[0]))
	switch mimeType {
	case "image/jpeg", "image/jpg":
		return "jpg"
	case "image/webp":
		return "webp"
	case "image/gif":
		return "gif"
	case "image/png":
		return "png"
	}
	ext := strings.TrimPrefix(strings.ToLower(filepath.Ext(sourceURL)), ".")
	switch ext {
	case "jpg", "jpeg", "png", "webp", "gif":
		if ext == "jpeg" {
			return "jpg"
		}
		return ext
	default:
		return "png"
	}
}

func (p tableToolProvider) fetchWebPage(rawURL string) agentToolResult {
	result := agentToolResult{
		Name:    "web_fetch",
		Columns: []string{"url", "title", "content_type", "summary", "content"},
	}
	if !p.allowWebRead {
		return p.unauthorizedWebResult(result.Name)
	}
	ctx, cancel := context.WithTimeout(context.Background(), defaultAgentTimeout)
	defer cancel()
	body, finalURL, contentType, err := fetchAgentWebDocument(ctx, rawURL)
	if err != nil {
		result.OK = false
		result.Error = err.Error()
		return result
	}
	title, content := parseAgentHTMLDocument(body, contentType)
	summary := truncateAgentText(content, 480)
	if title == "" {
		title = truncateAgentText(summary, 80)
	}
	if title == "" {
		title = finalURL
	}
	result.OK = true
	result.Message = "已读取公开网页 `" + finalURL + "`，并提取标题与正文摘要。"
	result.Rows = []map[string]string{{
		"url":          finalURL,
		"title":        title,
		"content_type": contentType,
		"summary":      summary,
		"content":      content,
	}}
	return result
}

func (p tableToolProvider) searchWeb(message string) agentToolResult {
	result := agentToolResult{
		Name:    "web_search",
		Columns: []string{"title", "url"},
	}
	if !p.allowWebRead {
		return p.unauthorizedWebResult(result.Name)
	}
	query := inferAgentWebSearchQuery(message)
	if query == "" {
		result.OK = false
		result.Error = "没有识别到可用于联网检索的关键词，请直接提供链接或更明确的网页关键词。"
		return result
	}
	searchURL, err := buildAgentSearchURL(query)
	if err != nil {
		result.OK = false
		result.Error = "构造联网检索地址失败：" + err.Error()
		return result
	}
	ctx, cancel := context.WithTimeout(context.Background(), defaultAgentTimeout)
	defer cancel()
	body, _, _, err := fetchAgentWebDocument(ctx, searchURL)
	if err != nil {
		result.OK = false
		result.Error = err.Error()
		return result
	}
	rows := parseAgentSearchResults(string(body), 5)
	if len(rows) == 0 {
		result.OK = false
		result.Error = "公开网页检索没有返回可用结果，请换一个关键词或直接提供链接。"
		return result
	}
	result.OK = true
	result.Message = fmt.Sprintf("已完成公开网页检索，关键词：`%s`。", query)
	result.Rows = rows
	return result
}

func extractAgentURLs(message string) []string {
	matches := agentURLPattern.FindAllString(message, -1)
	if len(matches) == 0 {
		return nil
	}
	out := make([]string, 0, len(matches))
	seen := map[string]struct{}{}
	for _, match := range matches {
		candidate := strings.TrimRight(strings.TrimSpace(match), ".,，。!！?？;；:：)）]】}\"'")
		if candidate == "" {
			continue
		}
		if _, ok := seen[candidate]; ok {
			continue
		}
		seen[candidate] = struct{}{}
		out = append(out, candidate)
	}
	return out
}

func inferAgentWebSearchQuery(message string) string {
	query := strings.TrimSpace(message)
	for _, rawURL := range extractAgentURLs(query) {
		query = strings.ReplaceAll(query, rawURL, " ")
	}
	replacements := []string{
		"帮我", "请", "麻烦", "搜索", "搜一下", "搜一搜", "查一下", "查找", "联网", "上网", "访问", "打开", "读取",
		"浏览", "看看", "看下", "总结", "整理", "告诉我", "给我", "一下", "吧",
	}
	for _, target := range replacements {
		query = strings.ReplaceAll(query, target, " ")
		query = strings.ReplaceAll(query, strings.ToUpper(target), " ")
	}
	query = strings.NewReplacer("\n", " ", "\r", " ", "\t", " ", "，", " ", ",", " ", "。", " ", "：", " ", ":", " ").Replace(query)
	query = agentHTMLSpacePattern.ReplaceAllString(strings.TrimSpace(query), " ")
	if len([]rune(query)) > 160 {
		query = string([]rune(query)[:160])
	}
	return strings.TrimSpace(query)
}

func validateAgentWebURL(ctx context.Context, rawURL string) (*neturl.URL, error) {
	_ = ctx
	parsed, err := neturl.Parse(strings.TrimSpace(rawURL))
	if err != nil {
		return nil, errors.New("网页地址格式不正确")
	}
	scheme := strings.ToLower(strings.TrimSpace(parsed.Scheme))
	if scheme != "http" && scheme != "https" {
		return nil, errors.New("只允许访问公开 http/https 页面")
	}
	if parsed.User != nil {
		return nil, errors.New("不允许携带带凭据的网页地址")
	}
	host := strings.ToLower(strings.TrimSpace(parsed.Hostname()))
	if host == "" {
		return nil, errors.New("网页地址缺少主机名")
	}
	if agentAllowPrivateWebTargets {
		return parsed, nil
	}
	if ip := net.ParseIP(host); ip != nil {
		if !isAgentPublicIP(ip) {
			return nil, errors.New("已拒绝访问本机、内网或受保护地址")
		}
		return parsed, nil
	}
	if isAgentBlockedWebHost(host) {
		return nil, errors.New("已拒绝访问本机、内网或受保护地址")
	}
	return parsed, nil
}

func buildAgentSearchURL(query string) (string, error) {
	base, err := neturl.Parse(strings.TrimSpace(agentWebSearchBaseURL))
	if err != nil {
		return "", err
	}
	params := base.Query()
	params.Set("q", query)
	base.RawQuery = params.Encode()
	return base.String(), nil
}

func fetchAgentWebDocument(ctx context.Context, rawURL string) ([]byte, string, string, error) {
	validated, err := validateAgentWebURL(ctx, rawURL)
	if err != nil {
		return nil, "", "", err
	}
	req, err := http.NewRequestWithContext(ctx, http.MethodGet, validated.String(), nil)
	if err != nil {
		return nil, "", "", err
	}
	req.Header.Set("User-Agent", "MoyiAdminAgent/1.0 (+https://moyi.admin)")
	req.Header.Set("Accept", "text/html,application/xhtml+xml,text/plain,application/json,text/*;q=0.9,*/*;q=0.1")
	client := *agentHTTPClient
	client.CheckRedirect = func(req *http.Request, via []*http.Request) error {
		if len(via) >= 4 {
			return errors.New("网页跳转次数过多")
		}
		_, err := validateAgentWebURL(ctx, req.URL.String())
		return err
	}
	resp, err := client.Do(req)
	if err != nil {
		return nil, "", "", err
	}
	defer resp.Body.Close()
	if resp.StatusCode < 200 || resp.StatusCode >= 300 {
		return nil, "", "", fmt.Errorf("网页返回状态 %d", resp.StatusCode)
	}
	if resp.Request != nil && resp.Request.URL != nil {
		if _, err := validateAgentWebURL(ctx, resp.Request.URL.String()); err != nil {
			return nil, "", "", err
		}
	}
	contentType := strings.ToLower(strings.TrimSpace(strings.Split(resp.Header.Get("Content-Type"), ";")[0]))
	if contentType == "" {
		contentType = "text/html"
	}
	if !strings.HasPrefix(contentType, "text/") &&
		contentType != "application/xhtml+xml" &&
		contentType != "application/json" &&
		contentType != "application/xml" &&
		contentType != "text/xml" {
		return nil, "", "", fmt.Errorf("暂不支持读取 `%s` 类型的网页内容", contentType)
	}
	body, err := io.ReadAll(io.LimitReader(resp.Body, 512*1024))
	if err != nil {
		return nil, "", "", err
	}
	finalURL := validated.String()
	if resp.Request != nil && resp.Request.URL != nil {
		finalURL = resp.Request.URL.String()
	}
	return body, finalURL, contentType, nil
}

func parseAgentHTMLDocument(body []byte, contentType string) (string, string) {
	raw := string(body)
	title := ""
	if matches := agentHTMLTitlePattern.FindStringSubmatch(raw); len(matches) == 2 {
		title = truncateAgentText(stripAgentHTMLToText(matches[1]), 120)
	}
	text := ""
	if strings.Contains(contentType, "html") || strings.Contains(strings.ToLower(truncateAgentText(raw, 256)), "<html") {
		text = stripAgentHTMLToText(raw)
	} else {
		text = strings.Map(func(r rune) rune {
			if r == '\t' || r == '\n' || r == '\r' || r >= 0x20 {
				return r
			}
			return -1
		}, raw)
		text = agentHTMLSpacePattern.ReplaceAllString(strings.TrimSpace(text), " ")
		text = html.UnescapeString(text)
	}
	text = truncateAgentText(text, 4000)
	if title == "" {
		title = truncateAgentText(text, 120)
	}
	return title, text
}

func stripAgentHTMLToText(body string) string {
	body = agentHTMLScriptPattern.ReplaceAllString(body, " ")
	body = agentHTMLStylePattern.ReplaceAllString(body, " ")
	body = strings.NewReplacer("</p>", "\n", "</div>", "\n", "<br>", "\n", "<br/>", "\n", "<br />", "\n", "</li>", "\n", "</tr>", "\n", "</h1>", "\n", "</h2>", "\n", "</h3>", "\n").Replace(body)
	body = agentHTMLTagPattern.ReplaceAllString(body, " ")
	body = html.UnescapeString(body)
	body = strings.ReplaceAll(body, "\u00a0", " ")
	body = agentHTMLSpacePattern.ReplaceAllString(strings.TrimSpace(body), " ")
	return strings.TrimSpace(body)
}

func parseAgentSearchResults(body string, limit int) []map[string]string {
	if limit <= 0 {
		limit = 5
	}
	matches := agentSearchResultPattern.FindAllStringSubmatch(body, -1)
	if len(matches) == 0 {
		return nil
	}
	results := make([]map[string]string, 0, limit)
	seen := map[string]struct{}{}
	for _, match := range matches {
		if len(match) < 3 {
			continue
		}
		title := truncateAgentText(stripAgentHTMLToText(match[2]), 160)
		if title == "" {
			continue
		}
		rawURL := html.UnescapeString(strings.TrimSpace(match[1]))
		if rawURL == "" {
			continue
		}
		if parsed, err := neturl.Parse(rawURL); err == nil {
			if uddg := strings.TrimSpace(parsed.Query().Get("uddg")); uddg != "" {
				rawURL = uddg
			}
		}
		if !strings.HasPrefix(strings.ToLower(rawURL), "http://") && !strings.HasPrefix(strings.ToLower(rawURL), "https://") {
			continue
		}
		if _, err := validateAgentWebURL(context.Background(), rawURL); err != nil {
			continue
		}
		if _, ok := seen[rawURL]; ok {
			continue
		}
		seen[rawURL] = struct{}{}
		results = append(results, map[string]string{
			"title": title,
			"url":   rawURL,
		})
		if len(results) >= limit {
			break
		}
	}
	return results
}

func isAgentBlockedWebHost(host string) bool {
	host = strings.ToLower(strings.TrimSpace(host))
	if host == "" {
		return true
	}
	if host == "localhost" || strings.HasSuffix(host, ".localhost") {
		return true
	}
	if strings.HasSuffix(host, ".local") || strings.HasSuffix(host, ".internal") || strings.HasSuffix(host, ".lan") || strings.HasSuffix(host, ".home") || strings.HasSuffix(host, ".corp") || strings.HasSuffix(host, ".localdomain") {
		return true
	}
	if !strings.Contains(host, ".") {
		return true
	}
	return false
}

func isAgentPublicIP(ip net.IP) bool {
	if ip == nil {
		return false
	}
	return !ip.IsLoopback() &&
		!ip.IsPrivate() &&
		!ip.IsLinkLocalMulticast() &&
		!ip.IsLinkLocalUnicast() &&
		!ip.IsMulticast() &&
		!ip.IsUnspecified()
}

func truncateAgentText(value string, limit int) string {
	value = strings.TrimSpace(value)
	if limit <= 0 {
		return value
	}
	runes := []rune(value)
	if len(runes) <= limit {
		return value
	}
	return strings.TrimSpace(string(runes[:limit])) + "..."
}

func isUnauthorizedAgentResult(result agentToolResult) bool {
	return !result.OK && strings.Contains(result.Error, "未授权")
}

func shouldCallAgentModel(intent agentIntent, results []agentToolResult) bool {
	if intent == agentIntentAccessScope || intent == agentIntentSystemConfig {
		return false
	}
	for _, result := range results {
		if !result.OK {
			return false
		}
		if result.File != nil {
			return false
		}
	}
	return true
}

func determineAgentIntent(message string) agentIntent {
	lower := strings.ToLower(strings.TrimSpace(message))
	if sql, ok := extractAgentSQL(message); ok && strings.HasPrefix(strings.ToLower(sql), "select ") {
		return agentIntentQuery
	}
	if containsAny(lower, "delete", "update", "insert", "drop", "alter", "truncate", "create", "replace", "attach", "detach", "pragma", "vacuum") {
		return agentIntentGuardrail
	}
	if isAgentAccessScopeQuestion(message) {
		return agentIntentAccessScope
	}
	if isAgentSystemConfigQuestion(message) {
		return agentIntentSystemConfig
	}
	if isAgentWebQuestion(message) {
		return agentIntentWebAccess
	}
	if isAgentImageQuestion(message) {
		return agentIntentImage
	}
	if containsAny(lower, "插件扩展", "扩展包", "插件系统", "资源模型", "资源定义", "crud 生成", "crud生成", "工具生成", "ai 工具生成", "ai工具生成", "resource model", "resource tool", "plugin extension") &&
		!containsAny(lower, "导出", "表格", "文件", "下载", "发给我", "返回文件", "excel", "xlsx", "csv", "json") {
		return agentIntentPreview
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
	if containsAny(lower, "预览", "查看", "明细", "rows", "preview") ||
		(strings.Contains(lower, "数据") && !containsAny(lower, "列出", "有哪些", "什么表", "哪些表", "数据表", "数据库")) {
		return agentIntentPreview
	}
	if containsAny(lower, "列出", "有哪些", "表", "tables", "table", "数据库") {
		return agentIntentTableCatalog
	}
	return agentIntentAdmin
}

func isAgentAccessScopeQuestion(message string) bool {
	lower := strings.ToLower(strings.TrimSpace(message))
	if lower == "" {
		return false
	}
	if containsAny(lower, "后台权限", "权限表", "权限记录", "角色权限", "菜单权限", "admin_permissions", "permission table", "permissions table") {
		return false
	}
	return containsAny(lower,
		"我有哪些权限",
		"我有什么权限",
		"我的权限",
		"查询我有哪些权限",
		"当前权限",
		"当前通道权限",
		"这个通道权限",
		"微信通道权限",
		"数据权限",
		"授权数据",
		"授权了哪些表",
		"授权了什么表",
		"可查询哪些表",
		"可以查询哪些表",
		"能查哪些表",
		"可读哪些表",
	)
}

func isAgentWebQuestion(message string) bool {
	lower := strings.ToLower(strings.TrimSpace(message))
	if lower == "" {
		return false
	}
	if len(extractAgentURLs(message)) > 0 {
		return true
	}
	if containsAny(lower, "官网", "网站", "网页", "网址", "url", "web page", "website") {
		return true
	}
	if containsAny(lower, "搜索", "搜一下", "搜一搜", "查一下", "查找", "搜索一下", "search", "google", "duckduckgo", "bing") &&
		containsAny(lower, "官网", "网站", "网页", "网址", "链接", "新闻", "资料", "互联网", "网上") {
		return true
	}
	if containsAny(lower, "访问", "打开", "抓取", "读取", "联网", "上网", "浏览") &&
		containsAny(lower, "官网", "网站", "网页", "网址", "链接", "互联网", "网上") {
		return true
	}
	return false
}

func isAgentSystemConfigQuestion(message string) bool {
	lower := strings.ToLower(strings.TrimSpace(message))
	if lower == "" {
		return false
	}
	if containsAny(lower, "导出", "表格", "文件", "下载", "发给我", "返回文件", "xlsx", "csv", "json") {
		return false
	}
	if containsAny(lower, "数据表", "哪些表", "什么表", "列出表", "select ", " from ", "字段", "结构", "schema") {
		return false
	}
	return containsAny(lower,
		"站点信息",
		"站点配置",
		"系统配置",
		"后台配置",
		"ai 配置",
		"ai配置",
		"模型配置",
		"站点和 ai 配置",
		"查看站点信息和 ai 配置",
		"元数据数据库",
		"数据库配置",
		"首页设置",
		"系统信息")
}

func isAgentImageQuestion(message string) bool {
	lower := strings.ToLower(strings.TrimSpace(message))
	if lower == "" {
		return false
	}
	if containsAny(lower, "支持图片创作", "支持文生图", "能不能文生图", "可以文生图吗", "能生成图片吗") &&
		!containsAny(lower, "帮我", "给我", "生成", "画", "做", "设计", "创作") {
		return false
	}
	action := containsAny(lower, "生成", "画", "绘制", "做一张", "做个", "创作", "出一张", "设计一张", "做成", "整理成", "合成", "输出", "来一张", "给我一张")
	target := containsAny(lower, "图片", "图", "海报", "插图", "封面", "配图", "横幅", "banner", "poster", "image", "介绍图", "概览图", "示意图", "信息图", "封面图", "主视觉")
	if action && target {
		return true
	}
	if target && containsAny(lower, "一张", "单张", "一图", "总览", "对比", "展示", "介绍", "说明") {
		return true
	}
	if containsAny(lower, "文生图", "图片创作", "生成一张", "画一张", "生成海报", "生成封面", "生成插图") {
		return true
	}
	return false
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
	case agentIntentWebAccess:
		return "访问公开网页并提取关键信息"
	case agentIntentImage:
		return "生成并返回图片文件"
	case agentIntentSystemConfig:
		return "查看站点与 AI 配置概览"
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
	case agentIntentAccessScope:
		return "查看当前会话数据权限"
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
	case agentIntentWebAccess:
		return []agentPlanStep{
			{Title: "识别网页目标", Detail: "优先识别显式 URL，没有链接时转为公开网页检索。", Status: "done"},
			{Title: "执行公开访问", Detail: "只访问公开 http/https 页面，拒绝本机、内网和受保护地址。", Status: "done"},
			{Title: "抽取正文", Detail: "提取标题、链接和正文摘要，交给智能体整理回答。", Status: "done"},
		}
	case agentIntentImage:
		return []agentPlanStep{
			{Title: "理解画面需求", Detail: "识别图片主题、风格、版式和输出方向。", Status: "done"},
			{Title: "调用图片模型", Detail: "使用百炼图片模型生成结果，并保持当前权限边界。", Status: "done"},
			{Title: "保存文件", Detail: "把图片保存为后台文件，返回预览与下载入口。", Status: "done"},
		}
	case agentIntentSystemConfig:
		return []agentPlanStep{
			{Title: "识别系统配置问题", Detail: "把站点信息、AI 配置、数据库类型等问题与数据表查询分开。", Status: "done"},
			{Title: "读取安全概览", Detail: "只返回站点、数据库、AI、存储等安全配置摘要，不暴露敏感值。", Status: "done"},
			{Title: "直接回答", Detail: "给出简短结论，不回退到内部状态表。", Status: "done"},
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
	case agentIntentAccessScope:
		return []agentPlanStep{
			{Title: "读取权限快照", Detail: "直接读取本次会话启动时冻结的数据权限，不访问业务数据表。", Status: "done"},
			{Title: "整理边界", Detail: "说明数据权限模式、可读表数量和授权表清单。", Status: "done"},
			{Title: "保持只读", Detail: "权限查询不会修改通道配置，也不会触发导出。", Status: "done"},
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
		case "generate_image":
			detail := result.Message
			if result.File != nil {
				detail = "已生成图片文件 `" + result.File.Name + "`，可以继续要求改成横版、竖版或调整风格。"
			}
			insights = append(insights, agentInsight{
				Title:  "图片结果已生成",
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
		case "access_scope":
			insights = append(insights, agentInsight{
				Title:  "权限快照已冻结",
				Detail: result.Message,
				Tone:   "info",
			})
		case "inspect_system_config":
			insights = append(insights, agentInsight{
				Title:  "系统配置已独立处理",
				Detail: "站点信息与 AI 配置现在走独立规则，不再混入通用数据表识别。",
				Tone:   "ready",
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
	if intent == agentIntentWebAccess {
		for _, result := range results {
			if !result.OK {
				continue
			}
			switch result.Name {
			case "web_fetch":
				title := ""
				if len(result.Rows) > 0 {
					title = strings.TrimSpace(result.Rows[0]["title"])
				}
				if title == "" {
					title = "网页内容"
				}
				insights = append(insights, agentInsight{
					Title:  "公开网页已读取",
					Detail: "已提取 `" + title + "` 的标题和正文摘要，可以继续追问重点或要求整理成结论。",
					Tone:   "ready",
				})
			case "web_search":
				insights = append(insights, agentInsight{
					Title:  "公开网页检索已完成",
					Detail: fmt.Sprintf("本次返回 %d 条搜索结果，可以继续指定其中某个链接深入阅读。", len(result.Rows)),
					Tone:   "info",
				})
			}
		}
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
	case agentIntentWebAccess:
		return []agentSuggestion{
			{Label: "继续读链接", Prompt: "继续访问刚才提到的那个链接，并把要点整理给我"},
			{Label: "提炼要点", Prompt: "把刚才网页里的关键信息提炼成 3 条结论"},
			{Label: "结合后台", Prompt: "把这个网页信息和我们当前后台任务结合起来给建议"},
		}
	case agentIntentImage:
		return []agentSuggestion{
			{Label: "横版海报", Prompt: "把刚才那张图改成更适合横版首页横幅的构图"},
			{Label: "竖版封面", Prompt: "基于刚才的主题，再生成一张更适合竖版封面的图片"},
			{Label: "简洁插图", Prompt: "延续刚才的主题，但改成更简洁的插图风格"},
		}
	case agentIntentGuardrail:
		return []agentSuggestion{
			{Label: "列出数据表", Prompt: "列出当前可查询的数据表"},
			{Label: "查看权限", Prompt: "检查一下当前是否有数据库读取权限"},
			{Label: "预览数据源", Prompt: "预览数据源配置"},
		}
	case agentIntentUserAccess:
		return []agentSuggestion{
			{Label: "账号明细", Prompt: "预览后台管理员账号"},
			{Label: "角色权限", Prompt: "预览后台权限"},
			{Label: "菜单入口", Prompt: "预览后台菜单"},
			{Label: "账号数量", Prompt: "我们后台有几个管理员账号？"},
		}
	case agentIntentAccessScope:
		return []agentSuggestion{
			{Label: "可读表", Prompt: "列出当前可查询的数据表"},
			{Label: "数据源", Prompt: "预览数据源配置"},
			{Label: "导出授权表", Prompt: "把当前授权的数据表清单导出 CSV 发给我"},
		}
	case agentIntentSystemConfig:
		return []agentSuggestion{
			{Label: "系统体检", Prompt: "对当前后台做一次系统体检并给出下一步建议"},
			{Label: "数据源", Prompt: "预览数据源配置"},
			{Label: "AI 能力", Prompt: "预览智能体能力"},
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
			{Label: "生成海报", Prompt: "帮我生成一张蓝绿色科技感的后台海报"},
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
	if intent == agentIntentAccessScope {
		return []agentToolResult{tools.accessScopeResult()}
	}
	if intent == agentIntentSystemConfig {
		return []agentToolResult{tools.inspectSystemConfig()}
	}
	if intent == agentIntentWebAccess {
		return []agentToolResult{tools.accessWeb(message)}
	}
	if intent == agentIntentImage {
		return []agentToolResult{tools.generateImage(message)}
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
		countUsers := tools.countTable("admin_users")
		if isUnauthorizedAgentResult(countUsers) {
			return []agentToolResult{countUsers}
		}
		previewUsers := tools.previewTable("admin_users", 20)
		if isUnauthorizedAgentResult(previewUsers) {
			return []agentToolResult{previewUsers}
		}
		results := []agentToolResult{countUsers, previewUsers}
		roles := tools.previewTable("admin_roles", 20)
		if roles.OK {
			results = append(results, roles)
		}
		if containsAny(lower, "权限", "permission", "permissions") {
			permissions := tools.previewTable("admin_permissions", 20)
			if isUnauthorizedAgentResult(permissions) {
				return []agentToolResult{permissions}
			}
			results = append(results, permissions)
		}
		if containsAny(lower, "菜单", "导航", "menu", "menus") {
			menus := tools.previewTable("admin_menus", 20)
			if isUnauthorizedAgentResult(menus) {
				return []agentToolResult{menus}
			}
			results = append(results, menus)
		}
		return results
	}
	if intent == agentIntentPreview {
		tables := tools.inferExplicitTablesFromMessage(message)
		if len(tables) > 1 {
			results := make([]agentToolResult, 0, len(tables))
			for i, table := range tables {
				if i >= 4 {
					break
				}
				results = append(results, tools.previewTable(table, 10))
			}
			return results
		}
		return []agentToolResult{tools.previewTable(tools.inferTableName(message), 10)}
	}
	if containsAny(lower, "字段", "结构", "schema", "describe", "columns") {
		return []agentToolResult{tools.describeTable(tools.inferTableName(message))}
	}
	if containsAny(lower, "列出", "有哪些", "表", "tables", "table", "数据库") {
		return []agentToolResult{tools.listTables()}
	}
	if containsAny(lower, "预览", "查看", "数据", "明细", "rows", "preview") {
		return []agentToolResult{tools.previewTable(tools.inferTableName(message), 10)}
	}
	return []agentToolResult{tools.listTables()}
}

func composeLocalAgentReply(message string, run agentRun, results []agentToolResult, tableAccessMode string, allowedTables []string) string {
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
		return "这个问题不需要查表。我可以直接帮你看后台、查数据、导出文件，或者生成图片。"
	}
	if len(successful) == 0 {
		return "这次没有命中可执行工具。你可以直接说表名、查询目标，或让我先列出可查询的数据表。"
	}

	if run.Mode == string(agentIntentDesign) {
		return "我建议把这块收成更轻的智能体工作台：对话、结果、下一步，别把太多信息堆在一页。"
	}
	if run.Mode == string(agentIntentHealth) {
		return "系统体检完成。" + agentAuthorizationReplyNote(tableAccessMode, allowedTables)
	}
	if run.Mode == string(agentIntentWebAccess) {
		for _, result := range successful {
			switch result.Name {
			case "web_fetch":
				if len(result.Rows) > 0 {
					title := strings.TrimSpace(result.Rows[0]["title"])
					if title == "" {
						title = strings.TrimSpace(result.Rows[0]["url"])
					}
					if title != "" {
						return "已读取网页 `" + title + "`。"
					}
				}
				return "已读取网页内容。"
			case "web_search":
				return fmt.Sprintf("已完成网页检索，找到 %d 条结果。", len(result.Rows))
			}
		}
	}
	if run.Mode == string(agentIntentImage) {
		for _, result := range successful {
			if result.File != nil {
				return fmt.Sprintf("图片已生成：`%s`。", result.File.Name)
			}
		}
		return "图片任务已完成，但这次没有返回可用文件。"
	}
	if run.Mode == string(agentIntentSystemConfig) {
		siteName := ""
		database := ""
		aiProvider := ""
		aiModel := ""
		for _, result := range successful {
			if result.Name != "inspect_system_config" {
				continue
			}
			for _, row := range result.Rows {
				switch row["key"] {
				case "site_name":
					siteName = row["value"]
				case "database_driver":
					database = row["value"]
				case "ai_provider":
					aiProvider = row["value"]
				case "ai_model":
					aiModel = row["value"]
				}
			}
		}
		parts := make([]string, 0, 4)
		if siteName != "" {
			parts = append(parts, "站点："+siteName)
		}
		if database != "" {
			parts = append(parts, "数据库："+database)
		}
		if aiProvider != "" {
			if aiModel != "" {
				parts = append(parts, "AI："+aiProvider+" / "+aiModel)
			} else {
				parts = append(parts, "AI："+aiProvider)
			}
		}
		if len(parts) == 0 {
			return "已读取站点与 AI 配置概览。"
		}
		return "已读取站点与 AI 配置：" + strings.Join(parts, "；") + "。"
	}
	if run.Mode == string(agentIntentAccessScope) {
		return composeAccessScopeReply(results)
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
			return fmt.Sprintf("当前后台管理员账号共 %s 个。内置管理员是 `%s`，角色为 %s。%s", count, username, role, agentAuthorizationReplyNote(tableAccessMode, allowedTables))
		}
		return fmt.Sprintf("当前后台管理员账号共 %s 个。%s", count, agentAuthorizationReplyNote(tableAccessMode, allowedTables))
	}
	if run.Mode == string(agentIntentExport) {
		for _, result := range successful {
			if result.File != nil {
				return fmt.Sprintf("已生成文件 `%s`。", result.File.Name)
			}
		}
		return "已读取数据，但还没有生成文件。"
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
		return fmt.Sprintf("当前可查询数据表共 %d 张：%s。", len(names), strings.Join(names, "、"))
	case "describe_table":
		return fmt.Sprintf("`%s` 字段共 %d 个。", result.Table, len(result.Rows))
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

func composeAccessScopeReply(results []agentToolResult) string {
	for _, result := range results {
		if result.Name != "access_scope" || len(result.Rows) == 0 {
			continue
		}
		row := result.Rows[0]
		mode := displayFallback(row["mode"], "未知")
		count := displayFallback(row["table_count"], "0")
		tables := strings.TrimSpace(row["tables"])
		summary := strings.TrimSpace(row["summary"])
		if tables == "" {
			return "当前数据权限：" + mode + "，可读数据表 0 张。"
		}
		if summary == "" {
			summary = tables
		}
		return "当前数据权限：" + mode + "，可读数据表 " + count + " 张：" + summary + "。"
	}
	return "暂时无法读取当前数据权限。"
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
	authorizationInstruction := agentAuthorizationInstruction(payload.TableAccessMode, payload.AllowedTables)
	webInstruction := agentWebAuthorizationInstruction(payload.AllowWebRead != nil && *payload.AllowWebRead)
	imageInstruction := agentImageAuthorizationInstruction(payload.AllowImageGenerate != nil && *payload.AllowImageGenerate)
	messages := []map[string]string{
		{
			"role":    "system",
			"content": "你是 Moyi Admin 后台管理员智能体。先判断是否需要工具，再直接给结论。只有任务涉及数据、表、统计、筛选、导出或账号权限时才使用数据工具；涉及官网、公开网页、文档、链接时才使用网页访问工具；明确要求海报、封面、插图、配图或文生图时才使用图片生成工具。权限边界：" + authorizationInstruction + "。网页边界：" + webInstruction + "。图片边界：" + imageInstruction + "。如果工具结果提示未授权，必须直接说明无权访问，不要继续推测。只能基于系统提供的 run 与工具结果回答；不要编造数据库内容；不要泄露密钥、密码哈希、盐值或会话信息。如果本次工具结果里没有成功返回文件，就绝对不要声称已经生成了图片、压缩包、下载链接或表格文件；如果工具结果只返回 1 个文件，就只能说 1 个。默认只用 1 到 3 句短句回答；除非用户明确要求详细说明，否则不要复述问题、不要解释计划、不要附带太多建议。",
		},
	}
	if summary := strings.TrimSpace(payload.CompressedContext); summary != "" {
		messages = append(messages, map[string]string{
			"role":    "system",
			"content": "这是当前会话较早历史的压缩摘要，只用于保持上下文连续和延续用户目标，不要机械复述整段摘要：" + summary,
		})
	}
	if summary := strings.TrimSpace(payload.HistoricalTasks); summary != "" {
		messages = append(messages, map[string]string{
			"role":    "system",
			"content": "这是当前管理员跨会话的长期任务记忆摘要，只用于帮助识别常见任务、最近操作习惯和历史上下文：" + summary,
		})
	}
	if summary := strings.TrimSpace(payload.StructuredMemory.promptSummary()); summary != "" {
		messages = append(messages, map[string]string{
			"role":    "system",
			"content": "这是当前会话的结构化任务记忆，优先用它理解用户正在继续什么任务、围绕哪张表、是否沿用筛选和导出格式：" + summary,
		})
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
			Name:              "install_state",
			Type:              "metadata_table",
			DisplayName:       "系统初始化状态",
			Description:       "当前系统初始化状态、安全入口、数据库与 AI 概览",
			Aliases:           []string{"install_state", "系统初始化状态", "初始化状态表"},
			HiddenFromCatalog: true,
		},
		{
			Name:        "admin_settings",
			Type:        "derived_view",
			DisplayName: "后台配置",
			Description: "后台运行参数与基础配置",
			Aliases:     []string{"系统配置", "配置项", "设置", "后台设置", "运行参数", "首页设置", "站点资料", "展示设置"},
		},
		{
			Name:        "setting_change_logs",
			Type:        "metadata_table",
			DisplayName: "设置变更记录",
			Description: "系统设置、存储、AI、安全、通知和任务设置的保存历史与变更摘要",
			Aliases:     []string{"设置变更", "变更记录", "设置历史", "配置历史", "修改记录", "基础设置记录", "设置日志"},
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
			Name:        "notification_deliveries",
			Type:        "metadata_table",
			DisplayName: "通知发送记录",
			Description: "后台 Webhook / 飞书机器人通知事件、接收人、发送状态和失败原因",
			Aliases:     []string{"通知事件", "通知记录", "发送记录", "Webhook 记录", "飞书通知", "飞书机器人", "告警记录", "通知发送"},
		},
		{
			Name:        "background_tasks",
			Type:        "metadata_table",
			DisplayName: "后台任务",
			Description: "后台异步任务、队列状态、尝试次数、执行结果和失败原因",
			Aliases:     []string{"后台任务", "任务队列", "异步任务", "队列任务", "任务记录", "失败任务", "重试任务"},
		},
		{
			Name:        "task_worker_settings",
			Type:        "derived_view",
			DisplayName: "后台任务执行器设置",
			Description: "后台任务自动执行、扫描间隔、批量数量和定时调度策略",
			Aliases:     []string{"任务设置", "任务执行器", "worker 设置", "自动执行", "定时调度", "调度设置"},
		},
		{
			Name:        "background_task_logs",
			Type:        "metadata_table",
			DisplayName: "后台任务日志",
			Description: "后台任务入队、开始、成功、失败、重试和取消等生命周期日志",
			Aliases:     []string{"任务日志", "后台任务日志", "任务生命周期", "执行日志", "队列日志", "重试日志"},
		},
		{
			Name:        "admin_users",
			Type:        "metadata_table",
			DisplayName: "后台管理员账号",
			Description: "后台管理员账号、显示名称、角色和启用状态",
			Aliases:     []string{"管理员账号", "后台账号", "账号", "账户", "用户", "用户管理", "管理员", "超级管理员账号"},
		},
		{
			Name:        "admin_sessions",
			Type:        "metadata_table",
			DisplayName: "后台登录会话",
			Description: "后台管理员登录会话、来源 IP、到期时间和下线状态",
			Aliases:     []string{"登录会话", "后台会话", "在线会话", "在线管理员", "会话管理", "session"},
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
			Name:        "schema_snapshots",
			Type:        "metadata_table",
			DisplayName: "数据源结构快照",
			Description: "数据源测试时沉淀的真实表结构、表注释、字段注释、索引和扫描摘要",
			Aliases:     []string{"结构快照", "Schema 快照", "schema snapshots", "表结构", "表注释", "字段注释", "字段说明", "数据库结构", "结构扫描"},
		},
		{
			Name:        "plugin_extensions",
			Type:        "computed_view",
			DisplayName: "插件扩展包",
			Description: "已登记的插件能力包、资源声明、工具数量和接入状态",
			Aliases:     []string{"插件扩展", "插件", "扩展包", "能力包", "插件系统", "extension", "plugin", "plugin extension"},
		},
		{
			Name:        "resource_models",
			Type:        "computed_view",
			DisplayName: "资源模型",
			Description: "由插件或核心模块声明的资源、字段、动作和权限边界",
			Aliases:     []string{"资源模型", "资源定义", "CRUD 生成", "crud", "资源", "模型", "工具生成器", "resource model"},
		},
		{
			Name:        "resource_tools",
			Type:        "computed_view",
			DisplayName: "资源工具",
			Description: "资源模型自动生成的 AI 可调用只读、导出和受控管理工具",
			Aliases:     []string{"AI 工具生成", "AI工具生成", "工具生成", "资源工具", "CRUD 工具", "工具注册", "tool registry", "resource tool"},
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
			Name:        "agent_wechat_messages",
			Type:        "metadata_table",
			DisplayName: "微信 Agent 聊天记录",
			Description: "微信 Agent 通道的用户消息、AI 回复、文件回复、发送状态和错误信息",
			Aliases:     []string{"微信聊天记录", "微信回复记录", "微信 Agent 记录", "微信通道记录", "聊天归档", "agent wechat messages"},
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

func filterAgentCatalogDefinitions(definitions []agentTableDefinition) []agentTableDefinition {
	out := make([]agentTableDefinition, 0, len(definitions))
	for _, definition := range definitions {
		if definition.HiddenFromCatalog {
			continue
		}
		out = append(out, definition)
	}
	return out
}

func (p tableToolProvider) listTables() agentToolResult {
	definitions := p.authorizedDefinitions()
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
		Message: "已读取当前渠道已授权的只读数据表列表，包含内部名、中文名称和表注释。",
		Columns: []string{"name", "display_name", "type", "comment"},
		Rows:    rows,
	}
}

func (p tableToolProvider) accessScopeResult() agentToolResult {
	mode := "全部只读表"
	if p.denyAllTables {
		mode = "禁用数据查询"
	} else if len(p.allowedTables) > 0 {
		mode = "指定数据表"
	}
	tables := p.authorizedTableNames()
	return agentToolResult{
		Name:    "access_scope",
		OK:      true,
		Message: "已读取当前会话冻结的数据权限快照；本次查询不会修改通道配置。",
		Columns: []string{"mode", "table_count", "tables", "summary"},
		Rows: []map[string]string{{
			"mode":        mode,
			"table_count": strconv.Itoa(len(tables)),
			"tables":      strings.Join(tables, ","),
			"summary":     p.authorizedTableSummary(),
		}},
	}
}

func (p tableToolProvider) inspectSystemConfig() agentToolResult {
	system := p.state.System.normalized()
	storage := p.state.Storage.normalized()
	rows := []map[string]string{
		{"section": "site", "key": "site_name", "value": p.state.SiteName},
		{"section": "site", "key": "public_headline", "value": system.PublicHeadline},
		{"section": "database", "key": "database_driver", "value": p.state.Database.DisplayName()},
		{"section": "ai", "key": "ai_provider", "value": p.state.AI.DisplayName()},
		{"section": "ai", "key": "ai_model", "value": p.state.AI.DisplayModel()},
		{"section": "storage", "key": "storage_driver", "value": storage.DisplayName()},
		{"section": "system", "key": "timezone", "value": system.Timezone},
	}
	return agentToolResult{
		Name:    "inspect_system_config",
		OK:      true,
		Message: "已读取站点与 AI 配置概览。",
		Columns: []string{"section", "key", "value"},
		Rows:    rows,
	}
}

func (p tableToolProvider) authorizedTableNames() []string {
	if p.denyAllTables {
		return nil
	}
	if len(p.allowedTables) == 0 {
		return p.knownTableNames()
	}
	names := make([]string, 0, len(p.allowedTables))
	for table := range p.allowedTables {
		names = append(names, table)
	}
	sort.Strings(names)
	return names
}

func (p tableToolProvider) knownTableNames() []string {
	definitions := p.tableDefinitions()
	names := make([]string, 0, len(definitions))
	for _, definition := range definitions {
		names = append(names, definition.Name)
	}
	return names
}

func (p tableToolProvider) singleAuthorizedTable() string {
	if p.denyAllTables || len(p.allowedTables) != 1 {
		return ""
	}
	for table := range p.allowedTables {
		table = normalizeAgentTableName(table)
		if table == "" {
			return ""
		}
		if p.isResolvableContextTable(table) {
			return table
		}
	}
	return ""
}

func (p tableToolProvider) knownTableSummary() string {
	parts := make([]string, 0, len(p.tableDefinitions()))
	for _, definition := range p.tableDefinitions() {
		parts = append(parts, definition.DisplayName+"("+definition.Name+")")
	}
	return strings.Join(parts, "、")
}

func (p tableToolProvider) unknownTableResult(toolName string, table string) agentToolResult {
	if table == "" {
		return p.unresolvedTableResult(toolName, "操作")
	}
	return agentToolResult{
		Name:  toolName,
		OK:    false,
		Table: table,
		Error: "未知数据表：" + table + "。当前可查询：" + p.knownTableSummary() + "。",
	}
}

func (p tableToolProvider) unresolvedTableResult(toolName string, action string) agentToolResult {
	action = strings.TrimSpace(action)
	if action == "" {
		action = "操作"
	}
	summary := strings.TrimSpace(p.authorizedTableSummary())
	detail := "这次没有识别到你要" + action + "的数据表。请直接说明表名，或先让我列出当前可查询的数据表。"
	switch summary {
	case "", "无授权数据表", "未授权任何数据表":
		detail += " 当前账号没有可查询的数据表。"
	default:
		detail += " 当前可查询：" + summary + "。"
	}
	return agentToolResult{
		Name:  toolName,
		OK:    false,
		Error: detail,
	}
}

func (p tableToolProvider) countTable(table string) agentToolResult {
	table = normalizeAgentTableName(table)
	if table == "" {
		return p.unresolvedTableResult("count_table", "统计")
	}
	if !p.isTableAuthorized(table) {
		return p.unauthorizedTableResult("count_table", table)
	}
	if _, ok := p.tableColumns(table); !ok {
		return p.unknownTableResult("count_table", table)
	}
	if external, ok := p.externalAgentTableByName(table); ok {
		count, err := p.countExternalTableRows(external)
		if err != nil {
			return agentToolResult{Name: "count_table", OK: false, Table: table, Error: "外部数据源统计失败：" + err.Error()}
		}
		return agentToolResult{
			Name:    "count_table",
			OK:      true,
			Message: "`" + table + "` 数量统计完成。",
			Table:   table,
			Columns: []string{"table", "count"},
			Rows: []map[string]string{{
				"table": table,
				"count": strconv.Itoa(count),
			}},
		}
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
	tables := p.inferExportTables(message)
	if len(tables) == 0 {
		if table := p.inferTableName(message); table != "" {
			tables = []string{table}
		}
	}
	if len(tables) == 0 {
		return p.unresolvedTableResult("export_table", "导出")
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
		if !p.isTableAuthorized(table) {
			return p.unauthorizedTableResult("export_table", table)
		}
		columns, ok := p.exportColumnsForMessage(table, message)
		if !ok {
			return p.unknownTableResult("export_table", table)
		}
		rows := []map[string]string(nil)
		if external, ok := p.externalAgentTableByName(table); ok {
			fetchLimit := limit
			if len(p.inferFilters(table, message)) > 0 && fetchLimit < 10000 {
				fetchLimit = 10000
			}
			externalRows, err := p.queryExternalTableRows(external, columns, fetchLimit)
			if err != nil {
				return agentToolResult{Name: "export_table", OK: false, Table: table, Error: "外部数据源导出失败：" + err.Error()}
			}
			rows = p.filterRowsForMessage(table, externalRows, message)
		} else {
			rows = p.filterRowsForMessage(table, p.tableRows(table), message)
		}
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
			Headers: p.exportHeadersForColumns(table, columns),
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
	if containsAny(lower, "csv", "逗号分隔", "comma separated") {
		return agentExportFormat{
			Extension: "csv",
			MIME:      "text/csv; charset=utf-8",
			Label:     "CSV",
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
		Extension: "xlsx",
		MIME:      "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
		Label:     "XLSX",
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
	case ".png":
		return "image/png", true
	case ".jpg", ".jpeg":
		return "image/jpeg", true
	case ".webp":
		return "image/webp", true
	case ".gif":
		return "image/gif", true
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
	multiSheet := len(data.Sheets) > 1
	for sheetIndex, sheet := range data.Sheets {
		if sheetIndex > 0 {
			if err := writer.Write([]string{}); err != nil {
				return err
			}
		}
		if multiSheet {
			if err := writer.Write([]string{"数据表：" + sheet.Table}); err != nil {
				return err
			}
		}
		header := agentExportSheetHeaders(sheet)
		if err := writer.Write(header); err != nil {
			return err
		}
		for _, row := range sheet.Rows {
			record := make([]string, 0, len(sheet.Columns))
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

	header := agentExportSheetHeaders(sheet)
	agentXLSXRow(&body, 1, header)
	for rowIndex, row := range sheet.Rows {
		values := make([]string, 0, len(sheet.Columns))
		for _, column := range sheet.Columns {
			values = append(values, row[column])
		}
		agentXLSXRow(&body, rowIndex+2, values)
	}

	body.WriteString(`</sheetData>`)
	body.WriteString(`</worksheet>`)
	return body.String()
}

func agentExportSheetHeaders(sheet agentExportSheet) []string {
	headers := append([]string(nil), sheet.Headers...)
	if len(headers) != len(sheet.Columns) {
		headers = append([]string(nil), sheet.Columns...)
	}
	return headers
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
	if table == "" {
		return p.unresolvedTableResult("describe_table", "查看字段结构")
	}
	if !p.isTableAuthorized(table) {
		return p.unauthorizedTableResult("describe_table", table)
	}
	columns, ok := p.tableColumns(table)
	if !ok {
		return p.unknownTableResult("describe_table", table)
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
	if table == "" {
		return p.unresolvedTableResult("preview_table", "预览")
	}
	if !p.isTableAuthorized(table) {
		return p.unauthorizedTableResult("preview_table", table)
	}
	columns, ok := p.tableColumns(table)
	if !ok {
		return p.unknownTableResult("preview_table", table)
	}
	if limit <= 0 || limit > 50 {
		limit = 10
	}

	columnNames := make([]string, 0, len(columns))
	for _, column := range columns {
		columnNames = append(columnNames, column.Name)
	}
	rows := []map[string]string(nil)
	if external, ok := p.externalAgentTableByName(table); ok {
		externalRows, err := p.queryExternalTableRows(external, columnNames, limit)
		if err != nil {
			return agentToolResult{Name: "preview_table", OK: false, Table: table, Error: "外部数据源预览失败：" + err.Error()}
		}
		rows = externalRows
	} else {
		rows = p.tableRows(table)
		if len(rows) > limit {
			rows = rows[:limit]
		}
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
	if !p.allowReadOnlyQuery {
		result.OK = false
		result.Error = "当前账号未授予 `agent.sql.select`，不能执行只读 SQL 查询。"
		return result
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
	if !p.isTableAuthorized(table) {
		result.Table = table
		result.OK = false
		result.Error = p.unauthorizedTableResult("query_readonly", table).Error
		return result
	}
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
		unknown := p.unknownTableResult("query_readonly", table)
		unknown.SQL = sql
		return unknown
	}
	if isAgentCountExpression(rawColumns) {
		if external, ok := p.externalAgentTableByName(table); ok {
			count, err := p.countExternalTableRows(external)
			if err != nil {
				result.OK = false
				result.Table = table
				result.Error = "外部数据源统计失败：" + err.Error()
				return result
			}
			result.OK = true
			result.Message = "只读数量查询执行完成。"
			result.Table = table
			result.Columns = []string{"count"}
			result.Rows = []map[string]string{{"count": strconv.Itoa(count)}}
			return result
		}
		rows := p.tableRows(table)
		result.OK = true
		result.Message = "只读数量查询执行完成。"
		result.Table = table
		result.Columns = []string{"count"}
		result.Rows = []map[string]string{{"count": strconv.Itoa(len(rows))}}
		return result
	}

	selectedColumns, err := p.selectColumns(table, rawColumns)
	if err != nil {
		result.OK = false
		result.Table = table
		result.Error = err.Error()
		return result
	}

	if external, ok := p.externalAgentTableByName(table); ok {
		rows, err := p.queryExternalTableRows(external, selectedColumns, limit)
		if err != nil {
			result.OK = false
			result.Table = table
			result.Error = "外部数据源查询失败：" + err.Error()
			return result
		}
		result.OK = true
		result.Message = "只读查询执行完成。"
		result.Table = table
		result.Columns = selectedColumns
		result.Rows = rows
		return result
	}

	preview := p.previewTable(table, limit)
	if !preview.OK {
		preview.Name = "query_readonly"
		preview.SQL = sql
		return preview
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

func (p tableToolProvider) queryExternalTableRows(table agentExternalTable, selectedColumns []string, limit int) ([]map[string]string, error) {
	if limit <= 0 {
		limit = 10
	}
	if limit > 10000 {
		limit = 10000
	}
	if len(selectedColumns) == 0 {
		selectedColumns = make([]string, 0, len(table.Columns))
		for _, column := range table.Columns {
			selectedColumns = append(selectedColumns, column.Name)
		}
	}

	rawColumns := make([]string, 0, len(selectedColumns))
	for _, column := range selectedColumns {
		column = strings.TrimSpace(column)
		raw := table.RawColumns[column]
		if raw == "" {
			return nil, errors.New("外部表 `" + table.ID + "` 不存在字段：" + column)
		}
		rawColumns = append(rawColumns, raw)
	}

	db, err := openAgentExternalDataSource(table.Source)
	if err != nil {
		return nil, err
	}
	defer db.Close()

	ctx, cancel := context.WithTimeout(context.Background(), 8*time.Second)
	defer cancel()

	selectParts := make([]string, 0, len(rawColumns))
	for _, raw := range rawColumns {
		selectParts = append(selectParts, quoteAgentExternalIdentifier(table.Source.Driver, raw))
	}
	query := "SELECT " + strings.Join(selectParts, ", ") + " FROM " + quoteAgentExternalTableName(table.Source.Driver, table.RawName) + " LIMIT " + strconv.Itoa(limit)
	rows, err := db.QueryContext(ctx, query)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	values := make([]any, len(rawColumns))
	scanTargets := make([]any, len(rawColumns))
	for i := range values {
		scanTargets[i] = &values[i]
	}
	out := make([]map[string]string, 0)
	for rows.Next() {
		if err := rows.Scan(scanTargets...); err != nil {
			return nil, err
		}
		row := make(map[string]string, len(selectedColumns))
		for i, column := range selectedColumns {
			row[column] = formatAgentExternalSQLValue(values[i])
		}
		out = append(out, row)
	}
	return out, rows.Err()
}

func (p tableToolProvider) countExternalTableRows(table agentExternalTable) (int, error) {
	db, err := openAgentExternalDataSource(table.Source)
	if err != nil {
		return 0, err
	}
	defer db.Close()

	ctx, cancel := context.WithTimeout(context.Background(), 8*time.Second)
	defer cancel()

	query := "SELECT COUNT(*) FROM " + quoteAgentExternalTableName(table.Source.Driver, table.RawName)
	var count int
	if err := db.QueryRowContext(ctx, query).Scan(&count); err != nil {
		return 0, err
	}
	return count, nil
}

func openAgentExternalDataSource(source dataSourceConfig) (*sql.DB, error) {
	source = source.normalized()
	switch source.Driver {
	case "sqlite":
		if source.FilePath == "" {
			return nil, errors.New("SQLite 文件路径为空")
		}
		return sql.Open("sqlite", source.FilePath)
	case "mysql", "postgres":
		return openNetworkSQLDatabase(networkDatabaseConfig{
			Driver:      source.Driver,
			DisplayName: source.DisplayName(),
			Host:        source.Host,
			Port:        source.Port,
			Database:    source.Database,
			Username:    source.Username,
			Password:    source.Password,
			SSLMode:     source.SSLMode,
			Purpose:     "Agent 外部数据源只读查询",
		})
	default:
		return nil, errors.New("不支持的数据源类型：" + source.Driver)
	}
}

func quoteAgentExternalTableName(driver string, table string) string {
	table = strings.TrimSpace(table)
	if normalizeDatabaseDriver(driver) == "postgres" {
		parts := strings.Split(table, ".")
		quoted := make([]string, 0, len(parts))
		for _, part := range parts {
			quoted = append(quoted, quoteAgentExternalIdentifier(driver, part))
		}
		return strings.Join(quoted, ".")
	}
	return quoteAgentExternalIdentifier(driver, table)
}

func quoteAgentExternalIdentifier(driver string, identifier string) string {
	identifier = strings.TrimSpace(identifier)
	if normalizeDatabaseDriver(driver) == "mysql" {
		return "`" + strings.ReplaceAll(identifier, "`", "``") + "`"
	}
	return `"` + strings.ReplaceAll(identifier, `"`, `""`) + `"`
}

func formatAgentExternalSQLValue(value any) string {
	if value == nil {
		return ""
	}
	switch typed := value.(type) {
	case []byte:
		return string(typed)
	case time.Time:
		return typed.Format(time.RFC3339)
	default:
		return fmt.Sprint(typed)
	}
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
	case "setting_change_logs":
		return []agentTableColumn{
			{Name: "time", Type: "datetime", Description: "变更时间"},
			{Name: "category", Type: "string", Description: "设置分组"},
			{Name: "action", Type: "string", Description: "保存动作"},
			{Name: "actor", Type: "string", Description: "操作者"},
			{Name: "summary", Type: "string", Description: "变更摘要"},
			{Name: "before", Type: "string", Description: "变更前 JSON，敏感字段已脱敏"},
			{Name: "after", Type: "string", Description: "变更后 JSON，敏感字段已脱敏"},
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
	case "notification_deliveries":
		return []agentTableColumn{
			{Name: "time", Type: "datetime", Description: "发送时间"},
			{Name: "event", Type: "string", Description: "通知事件类型"},
			{Name: "title", Type: "string", Description: "通知标题"},
			{Name: "receiver", Type: "string", Description: "接收人或接收备注"},
			{Name: "channel", Type: "string", Description: "通知通道"},
			{Name: "target", Type: "string", Description: "脱敏后的发送目标"},
			{Name: "status", Type: "string", Description: "发送状态"},
			{Name: "status_code", Type: "integer", Description: "通知通道响应状态码"},
			{Name: "error", Type: "string", Description: "失败原因"},
		}, true
	case "background_tasks":
		return []agentTableColumn{
			{Name: "task_id", Type: "string", Description: "任务标识"},
			{Name: "name", Type: "string", Description: "任务名称"},
			{Name: "type", Type: "string", Description: "任务类型"},
			{Name: "queue", Type: "string", Description: "队列名称"},
			{Name: "status", Type: "string", Description: "任务状态"},
			{Name: "attempts", Type: "string", Description: "尝试次数/最大次数"},
			{Name: "created_by", Type: "string", Description: "创建人"},
			{Name: "created_at", Type: "datetime", Description: "创建时间"},
			{Name: "available_at", Type: "datetime", Description: "可执行时间"},
			{Name: "finished_at", Type: "datetime", Description: "完成时间"},
			{Name: "result", Type: "string", Description: "执行结果"},
			{Name: "last_error", Type: "string", Description: "最近失败原因"},
		}, true
	case "task_worker_settings":
		return []agentTableColumn{
			{Name: "enabled", Type: "boolean", Description: "是否开启自动执行"},
			{Name: "interval_seconds", Type: "integer", Description: "Worker 扫描间隔秒数"},
			{Name: "batch_size", Type: "integer", Description: "单轮最多执行任务数"},
			{Name: "schedule_health_enabled", Type: "boolean", Description: "是否启用系统体检定时任务"},
			{Name: "schedule_health_minutes", Type: "integer", Description: "系统体检定时间隔分钟"},
			{Name: "schedule_cleanup_enabled", Type: "boolean", Description: "是否启用导出清理定时任务"},
			{Name: "schedule_cleanup_minutes", Type: "integer", Description: "导出清理定时间隔分钟"},
			{Name: "status", Type: "string", Description: "执行器状态说明"},
		}, true
	case "background_task_logs":
		return []agentTableColumn{
			{Name: "time", Type: "datetime", Description: "日志时间"},
			{Name: "task_id", Type: "string", Description: "任务标识"},
			{Name: "level", Type: "string", Description: "日志级别"},
			{Name: "event", Type: "string", Description: "生命周期事件"},
			{Name: "status", Type: "string", Description: "任务状态"},
			{Name: "attempt", Type: "integer", Description: "当前尝试次数"},
			{Name: "message", Type: "string", Description: "日志消息"},
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
	case "admin_sessions":
		return []agentTableColumn{
			{Name: "session_id", Type: "string", Description: "会话标识"},
			{Name: "username", Type: "string", Description: "管理员账号"},
			{Name: "ip", Type: "string", Description: "来源 IP"},
			{Name: "user_agent", Type: "string", Description: "浏览器或客户端"},
			{Name: "status", Type: "string", Description: "会话状态"},
			{Name: "created_at", Type: "datetime", Description: "登录时间"},
			{Name: "expires_at", Type: "datetime", Description: "到期时间"},
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
			{Name: "schema_summary", Type: "string", Description: "最近一次结构扫描摘要，包含表名、字段名、注释和索引概览"},
		}, true
	case "schema_snapshots":
		return []agentTableColumn{
			{Name: "id", Type: "integer", Description: "快照自增标识"},
			{Name: "data_source", Type: "string", Description: "数据源名称"},
			{Name: "driver", Type: "string", Description: "数据库驱动"},
			{Name: "target", Type: "string", Description: "连接目标"},
			{Name: "summary", Type: "string", Description: "结构扫描摘要"},
			{Name: "table_count", Type: "integer", Description: "表数量"},
			{Name: "column_count", Type: "integer", Description: "字段数量"},
			{Name: "schema_hash", Type: "string", Description: "结构快照哈希，用于判断结构是否变化"},
			{Name: "checks", Type: "string", Description: "扫描检查项，包含表清单、字段结构、表注释、字段注释和索引概览"},
			{Name: "captured_at", Type: "datetime", Description: "快照采集时间"},
		}, true
	case "plugin_extensions":
		return []agentTableColumn{
			{Name: "key", Type: "string", Description: "插件标识"},
			{Name: "name", Type: "string", Description: "插件名称"},
			{Name: "kind", Type: "string", Description: "插件类型"},
			{Name: "version", Type: "string", Description: "插件版本"},
			{Name: "resources", Type: "string", Description: "插件声明的资源模型"},
			{Name: "tools", Type: "string", Description: "插件生成或登记的工具数量"},
			{Name: "status", Type: "string", Description: "接入状态"},
			{Name: "description", Type: "string", Description: "插件说明"},
		}, true
	case "resource_models":
		return []agentTableColumn{
			{Name: "key", Type: "string", Description: "资源模型标识"},
			{Name: "name", Type: "string", Description: "资源名称"},
			{Name: "plugin", Type: "string", Description: "来源插件"},
			{Name: "source", Type: "string", Description: "资源来源类型"},
			{Name: "actions", Type: "string", Description: "可生成工具的动作"},
			{Name: "fields", Type: "string", Description: "字段数量、表注释和字段注释摘要"},
			{Name: "tools", Type: "integer", Description: "已生成工具数量"},
			{Name: "status", Type: "string", Description: "接入状态"},
			{Name: "description", Type: "string", Description: "资源说明"},
		}, true
	case "resource_tools":
		return []agentTableColumn{
			{Name: "name", Type: "string", Description: "工具名称"},
			{Name: "resource", Type: "string", Description: "所属资源"},
			{Name: "resource_key", Type: "string", Description: "资源模型标识"},
			{Name: "action", Type: "string", Description: "工具动作"},
			{Name: "permission", Type: "string", Description: "权限动作"},
			{Name: "boundary", Type: "string", Description: "执行边界"},
			{Name: "status", Type: "string", Description: "工具状态"},
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
	case "agent_wechat_messages":
		return []agentTableColumn{
			{Name: "channel_key", Type: "string", Description: "微信 Agent 通道标识"},
			{Name: "channel_name", Type: "string", Description: "通道名称"},
			{Name: "message_id", Type: "string", Description: "微信消息标识"},
			{Name: "session_id", Type: "string", Description: "会话标识"},
			{Name: "run_id", Type: "string", Description: "智能体运行标识"},
			{Name: "from_user_id", Type: "string", Description: "微信用户"},
			{Name: "to_user_id", Type: "string", Description: "机器人账号"},
			{Name: "inbound_text", Type: "string", Description: "用户消息"},
			{Name: "reply_text", Type: "string", Description: "AI 回复"},
			{Name: "files", Type: "string", Description: "回复文件"},
			{Name: "status", Type: "string", Description: "发送状态"},
			{Name: "error", Type: "string", Description: "错误信息"},
			{Name: "received_at", Type: "datetime", Description: "收到时间"},
			{Name: "replied_at", Type: "datetime", Description: "回复时间"},
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
		if external, ok := p.externalAgentTableByName(table); ok {
			return external.Columns, true
		}
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
		notifications := state.Notifications.normalized()
		return []map[string]string{
			{"key": "site_name", "value": state.SiteName},
			{"key": "admin_tagline", "value": system.AdminTagline},
			{"key": "public_tagline", "value": system.PublicTagline},
			{"key": "public_headline", "value": system.PublicHeadline},
			{"key": "public_description", "value": system.PublicDescription},
			{"key": "admin_entry", "value": state.AdminEntry},
			{"key": "database", "value": state.Database.DisplayName() + " / " + state.Database.DisplayTarget()},
			{"key": "ai_provider", "value": state.AI.DisplayName()},
			{"key": "ai_model", "value": state.AI.DisplayModel()},
			{"key": "ai_api_key", "value": state.AI.maskedAPIKey()},
			{"key": "timezone", "value": system.Timezone},
			{"key": "locale", "value": system.Locale},
			{"key": "storage", "value": storage.DisplayName() + " / " + storage.LocalPath},
			{"key": "notification_channel", "value": notifications.DisplayName()},
			{"key": "notification_receiver", "value": notifications.Receiver},
		}
	case "setting_change_logs":
		out := make([]map[string]string, 0, len(p.settingChanges))
		for _, row := range p.settingChanges {
			out = append(out, map[string]string{
				"time":     formatAgentTime(row.Timestamp),
				"category": settingChangeCategoryLabel(row.Category),
				"action":   row.Action,
				"actor":    displayNotificationValue(row.Actor, "system"),
				"summary":  row.Summary,
				"before":   truncateAuditText(row.BeforeJSON, 400),
				"after":    truncateAuditText(row.AfterJSON, 400),
			})
		}
		return out
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
	case "notification_deliveries":
		out := make([]map[string]string, 0, len(p.notifications))
		for _, record := range p.notifications {
			out = append(out, map[string]string{
				"time":        formatAdminTime(record.Timestamp),
				"event":       record.Event,
				"title":       record.Title,
				"receiver":    record.Receiver,
				"channel":     notificationChannelLabel(record.Channel),
				"target":      record.Target,
				"message":     record.Message,
				"status":      notificationDeliveryStatusLabel(record.Status, record.StatusCode),
				"status_code": strconv.Itoa(record.StatusCode),
				"error":       record.Error,
			})
		}
		return out
	case "background_tasks":
		rows := buildAdminBackgroundTaskRows(p.backgroundTasks, "")
		out := make([]map[string]string, 0, len(rows))
		for _, row := range rows {
			out = append(out, map[string]string{
				"task_id":      row.IDShort,
				"name":         row.Name,
				"type":         row.TypeName,
				"queue":        row.Queue,
				"status":       row.Status,
				"attempts":     row.Attempts,
				"created_by":   row.CreatedBy,
				"created_at":   row.CreatedAt,
				"available_at": row.AvailableAt,
				"finished_at":  row.FinishedAt,
				"result":       row.Result,
				"last_error":   row.LastError,
			})
		}
		return out
	case "task_worker_settings":
		worker := state.TaskWorker.normalized()
		return []map[string]string{
			{
				"enabled":                  boolText(worker.Enabled),
				"interval_seconds":         strconv.Itoa(worker.IntervalSeconds),
				"batch_size":               strconv.Itoa(worker.BatchSize),
				"schedule_health_enabled":  boolText(worker.ScheduleHealthEnabled),
				"schedule_health_minutes":  strconv.Itoa(worker.ScheduleHealthMinutes),
				"schedule_cleanup_enabled": boolText(worker.ScheduleCleanupEnabled),
				"schedule_cleanup_minutes": strconv.Itoa(worker.ScheduleCleanupMinutes),
				"status":                   worker.StatusText(),
			},
		}
	case "background_task_logs":
		rows := buildAdminBackgroundTaskLogRows(p.backgroundLogs)
		out := make([]map[string]string, 0, len(rows))
		for _, row := range rows {
			out = append(out, map[string]string{
				"time":    row.Time,
				"task_id": row.TaskIDShort,
				"level":   row.Level,
				"event":   row.Event,
				"status":  row.Status,
				"attempt": strconv.Itoa(row.Attempt),
				"message": row.Message,
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
	case "admin_sessions":
		rows := buildAdminSessionRows(p.adminSessions, "", "")
		out := make([]map[string]string, 0, len(rows))
		for _, row := range rows {
			out = append(out, map[string]string{
				"session_id": row.IDShort,
				"username":   row.Username,
				"ip":         row.IP,
				"user_agent": row.UserAgent,
				"status":     row.Status,
				"created_at": row.CreatedAt,
				"expires_at": row.ExpiresAt,
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
				"name":           row.Name,
				"driver":         row.Driver,
				"target":         row.Target,
				"role":           row.Role,
				"status":         status,
				"schema_summary": row.Schema,
			})
		}
		return out
	case "schema_snapshots":
		out := make([]map[string]string, 0, len(p.schemaSnapshots))
		for _, row := range p.schemaSnapshots {
			checks := summarizeSchemaSnapshotChecks(row.ChecksJSON)
			out = append(out, map[string]string{
				"id":           strconv.FormatInt(row.ID, 10),
				"data_source":  row.DataSourceName,
				"driver":       row.Driver,
				"target":       row.Target,
				"summary":      row.Summary,
				"table_count":  strconv.Itoa(row.TableCount),
				"column_count": strconv.Itoa(row.ColumnCount),
				"schema_hash":  shortSchemaHash(row.SchemaHash),
				"checks":       checks,
				"captured_at":  formatAgentTime(row.CapturedAt),
			})
		}
		return out
	case "plugin_extensions":
		models := buildResourceModels(state)
		tools := buildResourceTools(models)
		plugins := buildPluginExtensions(state, models, tools)
		out := make([]map[string]string, 0, len(plugins))
		for _, row := range plugins {
			out = append(out, map[string]string{
				"key":         row.Key,
				"name":        row.Name,
				"kind":        row.Kind,
				"version":     row.Version,
				"resources":   row.Resources,
				"tools":       row.Tools,
				"status":      row.Status,
				"description": row.Description,
			})
		}
		return out
	case "resource_models":
		models := buildResourceModels(state)
		out := make([]map[string]string, 0, len(models))
		for _, row := range models {
			fields := strconv.Itoa(row.FieldCount) + " 个"
			if row.FieldsSummary != "" {
				fields += "：" + row.FieldsSummary
			}
			out = append(out, map[string]string{
				"key":         row.Key,
				"name":        row.Name,
				"plugin":      row.Plugin,
				"source":      row.Source,
				"actions":     row.Actions,
				"fields":      fields,
				"tools":       strconv.Itoa(row.ToolCount),
				"status":      row.Status,
				"description": row.Description + " " + row.ReadScope,
			})
		}
		return out
	case "resource_tools":
		models := buildResourceModels(state)
		tools := buildResourceTools(models)
		out := make([]map[string]string, 0, len(tools))
		for _, row := range tools {
			out = append(out, map[string]string{
				"name":         row.Name,
				"resource":     row.Resource,
				"resource_key": row.ResourceKey,
				"action":       row.Action,
				"permission":   row.Permission,
				"boundary":     row.Boundary,
				"status":       row.Status,
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
	case "agent_wechat_messages":
		out := make([]map[string]string, 0, len(p.runtime.WeChatMessages))
		for _, row := range p.runtime.WeChatMessages {
			out = append(out, map[string]string{
				"channel_key":  row.ChannelKey,
				"channel_name": row.ChannelName,
				"message_id":   row.MessageID,
				"session_id":   row.SessionID,
				"run_id":       row.RunID,
				"from_user_id": row.FromUserID,
				"to_user_id":   row.ToUserID,
				"inbound_text": row.InboundText,
				"reply_text":   row.ReplyText,
				"files":        adminAgentWeChatFileSummary(row.Files),
				"status":       agentRunStatusText(row.Status),
				"error":        row.Error,
				"received_at":  formatAgentTime(row.ReceivedAt),
				"replied_at":   formatAgentTime(row.RepliedAt),
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
			{"name": "web_access", "boundary": "允许访问公开 http/https 页面，拒绝本机、内网和受保护地址", "status": "已启用"},
			{"name": "agent_memory", "boundary": "会话、运行和工具结果写入元数据表", "status": "已启用"},
			{"name": "resource_registry", "boundary": "插件扩展、资源模型和生成工具统一登记，供智能体发现", "status": "已启用"},
			{"name": "external_data_source_reader", "boundary": "按通道授权只读查询外部 SQLite/MySQL/PostgreSQL 数据源", "status": "已启用"},
			{"name": "resource_tool_generator", "boundary": "从资源模型生成读取结构、预览、查询和导出工具", "status": "本次接入"},
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
			{"layer": "工具层", "role": "统一封装只读查询、结构探测和数据预览", "status": "已启用", "next_action": "继续补充外部数据源筛选、分页和字段脱敏策略"},
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

func (p tableToolProvider) exportHeadersForColumns(table string, selectedColumns []string) []string {
	definitions, ok := p.tableColumns(table)
	if !ok {
		return append([]string(nil), selectedColumns...)
	}
	labels := make(map[string]string, len(definitions))
	for _, column := range definitions {
		label := normalizeSchemaComment(column.Description)
		if label == "" {
			label = column.Name
		}
		labels[column.Name] = label
	}
	headers := make([]string, 0, len(selectedColumns))
	for _, column := range selectedColumns {
		if label := strings.TrimSpace(labels[column]); label != "" {
			headers = append(headers, label)
			continue
		}
		headers = append(headers, column)
	}
	return headers
}

func (p tableToolProvider) filterRowsForMessage(table string, rows []map[string]string, message string) []map[string]string {
	filters := p.inferFilters(table, message)
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
	case "admin_settings":
		return map[string][]string{
			"key":   {"配置项", "设置项", "键"},
			"value": {"配置值", "设置值", "值"},
		}
	case "setting_change_logs":
		return map[string][]string{
			"time":     {"时间", "变更时间", "修改时间", "保存时间"},
			"category": {"分组", "设置分组", "配置分组", "模块"},
			"action":   {"动作", "操作", "保存动作"},
			"actor":    {"操作者", "管理员", "操作人"},
			"summary":  {"摘要", "说明", "变更说明"},
			"before":   {"变更前", "修改前", "旧值"},
			"after":    {"变更后", "修改后", "新值"},
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
	case "admin_sessions":
		return map[string][]string{
			"session_id": {"会话", "会话ID", "session"},
			"username":   {"账号", "管理员", "用户名"},
			"ip":         {"IP", "来源IP", "登录IP"},
			"user_agent": {"浏览器", "客户端", "UserAgent"},
			"status":     {"状态", "会话状态", "在线状态"},
			"created_at": {"登录时间", "创建时间"},
			"expires_at": {"到期时间", "过期时间"},
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
	case "notification_deliveries":
		return map[string][]string{
			"time":        {"时间", "发送时间"},
			"event":       {"事件", "通知事件", "事件类型"},
			"title":       {"标题", "通知标题"},
			"receiver":    {"接收人", "接收备注"},
			"channel":     {"通道", "通知通道"},
			"target":      {"目标", "Webhook", "飞书机器人", "发送目标"},
			"message":     {"消息", "内容", "通知内容"},
			"status":      {"状态", "发送状态"},
			"status_code": {"状态码", "响应码"},
			"error":       {"错误", "失败原因"},
		}
	case "background_tasks":
		return map[string][]string{
			"task_id":      {"任务ID", "任务标识", "ID"},
			"name":         {"任务名称", "名称"},
			"type":         {"任务类型", "类型"},
			"queue":        {"队列", "队列名称"},
			"status":       {"状态", "任务状态", "执行状态"},
			"attempts":     {"尝试次数", "重试次数"},
			"created_by":   {"创建人", "管理员"},
			"created_at":   {"创建时间", "入队时间"},
			"available_at": {"可执行时间", "下次执行"},
			"finished_at":  {"完成时间", "结束时间"},
			"result":       {"结果", "执行结果"},
			"last_error":   {"错误", "失败原因", "异常"},
		}
	case "task_worker_settings":
		return map[string][]string{
			"enabled":                  {"启用", "自动执行", "Worker状态"},
			"interval_seconds":         {"扫描间隔", "间隔", "秒"},
			"batch_size":               {"批量", "单轮数量", "执行数量"},
			"schedule_health_enabled":  {"体检调度", "系统体检"},
			"schedule_health_minutes":  {"体检间隔", "体检分钟"},
			"schedule_cleanup_enabled": {"清理调度", "导出清理"},
			"schedule_cleanup_minutes": {"清理间隔", "清理分钟"},
			"status":                   {"状态", "说明"},
		}
	case "background_task_logs":
		return map[string][]string{
			"time":    {"时间", "日志时间"},
			"task_id": {"任务ID", "任务标识"},
			"level":   {"级别", "日志级别"},
			"event":   {"事件", "生命周期", "动作"},
			"status":  {"状态", "任务状态"},
			"attempt": {"尝试", "尝试次数", "重试次数"},
			"message": {"消息", "日志", "内容"},
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
			"name":           {"名称", "数据源"},
			"driver":         {"类型", "驱动"},
			"target":         {"地址", "目标"},
			"role":           {"用途"},
			"status":         {"状态"},
			"schema_summary": {"结构", "结构摘要", "表结构", "字段结构", "表注释", "字段注释"},
		}
	case "schema_snapshots":
		return map[string][]string{
			"id":           {"快照ID", "标识"},
			"data_source":  {"数据源", "数据源名称"},
			"driver":       {"驱动", "数据库类型"},
			"target":       {"地址", "目标"},
			"summary":      {"摘要", "结构摘要", "扫描摘要"},
			"table_count":  {"表数量", "表数"},
			"column_count": {"字段数量", "字段数"},
			"schema_hash":  {"哈希", "结构哈希", "变更哈希"},
			"checks":       {"检查项", "表结构", "字段结构", "表注释", "字段注释", "索引"},
			"captured_at":  {"采集时间", "扫描时间", "检查时间"},
		}
	case "plugin_extensions":
		return map[string][]string{
			"key":         {"插件标识", "标识", "key"},
			"name":        {"插件名称", "名称"},
			"kind":        {"类型", "插件类型"},
			"version":     {"版本", "插件版本"},
			"resources":   {"资源", "资源模型"},
			"tools":       {"工具", "工具数量"},
			"status":      {"状态", "接入状态"},
			"description": {"说明", "插件说明"},
		}
	case "resource_models":
		return map[string][]string{
			"key":         {"资源标识", "标识", "key"},
			"name":        {"资源名称", "名称"},
			"plugin":      {"插件", "来源插件"},
			"source":      {"来源", "资源来源"},
			"actions":     {"动作", "可用动作", "生成动作"},
			"fields":      {"字段", "字段注释", "表注释", "字段摘要"},
			"tools":       {"工具", "工具数量"},
			"status":      {"状态", "接入状态"},
			"description": {"说明", "资源说明"},
		}
	case "resource_tools":
		return map[string][]string{
			"name":         {"工具", "工具名称"},
			"resource":     {"资源", "所属资源"},
			"resource_key": {"资源标识", "资源key"},
			"action":       {"动作", "工具动作"},
			"permission":   {"权限", "权限动作"},
			"boundary":     {"边界", "执行边界"},
			"status":       {"状态", "工具状态"},
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
	case "agent_wechat_messages":
		return map[string][]string{
			"channel_key":  {"通道", "通道标识"},
			"channel_name": {"通道名称", "微信通道"},
			"message_id":   {"消息ID", "微信消息"},
			"session_id":   {"会话", "会话ID"},
			"run_id":       {"运行ID", "智能体运行"},
			"from_user_id": {"微信用户", "发送人", "用户"},
			"to_user_id":   {"机器人", "接收账号"},
			"inbound_text": {"用户消息", "提问", "消息内容"},
			"reply_text":   {"AI回复", "回复", "回答"},
			"files":        {"文件", "回复文件"},
			"status":       {"状态", "发送状态"},
			"error":        {"错误", "失败原因"},
			"received_at":  {"收到时间", "消息时间"},
			"replied_at":   {"回复时间", "发送时间"},
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

func (p tableToolProvider) inferFilters(table string, message string) map[string]string {
	lower := strings.ToLower(message)
	filters := map[string]string{}
	columns, ok := p.tableColumns(table)
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

func inferAgentFilters(table string, message string) map[string]string {
	return newTableToolProvider(installState{}, "", "").inferFilters(table, message)
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
	tables := inferAgentExplicitTablesFromMessage(message)
	if len(tables) > 0 {
		return tables
	}
	return nil
}

func (p tableToolProvider) inferExportTables(message string) []string {
	raw := strings.TrimSpace(firstNonEmpty(p.rawMessage, message))
	lower := strings.ToLower(raw)
	if containsAny(lower, "所有表", "全部表", "所有数据表", "全部数据表", "all tables") {
		return p.knownTableNames()
	}
	tables := p.inferExplicitTablesFromMessage(raw)
	if len(tables) > 0 {
		return tables
	}
	if p.lastExport != nil && shouldReuseLastExportContext(raw) {
		if tables = normalizeAgentAllowedTables([]string{p.lastExport.Table}); len(tables) > 0 {
			return tables
		}
	}
	if table := p.inferTableNameFromMemory(raw); table != "" {
		return []string{table}
	}
	if table := p.singleAuthorizedTable(); table != "" {
		return []string{table}
	}
	tables = p.inferTablesFromMessage(message)
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
	tables := inferAgentExplicitTablesFromMessage(message)
	if len(tables) > 0 {
		return tables[0]
	}
	return ""
}

func (p tableToolProvider) inferTableName(message string) string {
	raw := strings.TrimSpace(firstNonEmpty(p.rawMessage, message))
	tables := p.inferExplicitTablesFromMessage(raw)
	if len(tables) > 0 {
		return tables[0]
	}
	if table := p.inferTableNameFromMemory(raw); table != "" {
		return table
	}
	if table := p.singleAuthorizedTable(); table != "" {
		return table
	}
	tables = p.inferTablesFromMessage(message)
	if len(tables) > 0 {
		return tables[0]
	}
	return ""
}

func (p tableToolProvider) inferTableNameFromMemory(message string) string {
	memory := p.memory.normalized()
	if !memory.hasContext() {
		return ""
	}
	lower := strings.ToLower(strings.TrimSpace(message))
	if lower == "" {
		return ""
	}
	if isAgentSystemConfigQuestion(message) || isAgentAccessScopeQuestion(message) || isAgentWebQuestion(message) || isAgentImageQuestion(message) {
		return ""
	}
	if containsAny(lower, "列出当前可查询的数据表", "列出数据表", "哪些表", "什么表", "可查询的数据表", "数据权限", "权限范围") {
		return ""
	}
	continuation := shouldReuseStructuredTaskMemory(message, memory) ||
		containsAny(lower,
			"这个", "这些", "那个", "那些", "刚才", "上一轮", "上一份", "上一个", "继续", "接着",
			"再查", "再看", "再导", "只看", "只保留", "筛选", "过滤", "启用", "禁用", "状态", "数量", "统计",
			"字段", "结构", "导出", "预览", "明细")
	if !continuation && utf8.RuneCountInString(lower) > 48 {
		return ""
	}
	candidates := make([]string, 0, 1+len(memory.FocusTables))
	if memory.PrimaryTable != "" {
		candidates = append(candidates, memory.PrimaryTable)
	}
	candidates = append(candidates, memory.FocusTables...)
	for _, candidate := range normalizeAgentAllowedTables(candidates) {
		if p.isResolvableContextTable(candidate) {
			return candidate
		}
	}
	return ""
}

func (p tableToolProvider) isResolvableContextTable(table string) bool {
	table = normalizeAgentTableName(table)
	if table == "" || !p.isTableAuthorized(table) {
		return false
	}
	_, ok := p.tableColumns(table)
	return ok
}

func knownAgentTables() []string {
	definitions := filterAgentCatalogDefinitions(agentTableDefinitions())
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
		return agentToolResult{
			Name:  toolName,
			OK:    false,
			Error: "这次没有识别到要操作的数据表，请直接说明表名，或先列出当前可查询的数据表。",
		}
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
	return inferAgentTablesFromDefinitions(message, agentTableDefinitions(), false)
}

func inferAgentExplicitTablesFromMessage(message string) []string {
	return inferAgentTablesFromDefinitions(message, agentTableDefinitions(), true)
}

func inferAgentTablesFromDefinitions(message string, definitions []agentTableDefinition, explicitOnly bool) []string {
	matches := make([]agentTableMatch, 0)
	for _, definition := range definitions {
		score := scoreAgentTableDefinition(message, definition)
		if explicitOnly {
			score = scoreAgentExplicitTableDefinition(message, definition)
		}
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

func (p tableToolProvider) inferTablesFromMessage(message string) []string {
	return inferAgentTablesFromDefinitions(message, p.tableDefinitions(), false)
}

func (p tableToolProvider) inferExplicitTablesFromMessage(message string) []string {
	return inferAgentTablesFromDefinitions(message, p.tableDefinitions(), true)
}

func scoreAgentExplicitTableDefinition(message string, definition agentTableDefinition) int {
	score := 0
	if agentNormalizedContains(message, definition.Name) {
		score = maxAgentScore(score, 100)
	}
	if agentNormalizedContains(message, definition.DisplayName) {
		score = maxAgentScore(score, 70)
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
	definitions := filterAgentCatalogDefinitions(agentTableDefinitions())
	parts := make([]string, 0, len(definitions))
	for _, definition := range definitions {
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
