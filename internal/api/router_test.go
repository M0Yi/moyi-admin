package api

import (
	"archive/zip"
	"bytes"
	"context"
	"database/sql"
	"encoding/hex"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"mime/multipart"
	"net/http"
	"net/http/httptest"
	"net/url"
	"os"
	"path/filepath"
	"strings"
	"sync"
	"testing"
	"time"
)

func TestHomeShowsInstallBeforeInitialization(t *testing.T) {
	server := httptest.NewServer(NewRouter(RouterOptions{}))
	defer server.Close()

	resp, err := http.Get(server.URL + "/")
	if err != nil {
		t.Fatalf("GET / failed: %v", err)
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		t.Fatalf("expected status 200, got %d", resp.StatusCode)
	}
	if contentType := resp.Header.Get("Content-Type"); !strings.Contains(contentType, "text/html") {
		t.Fatalf("expected html content type, got %q", contentType)
	}

	body, err := io.ReadAll(resp.Body)
	if err != nil {
		t.Fatalf("read response: %v", err)
	}
	if !strings.Contains(string(body), "系统初始化") {
		t.Fatal("expected install page on first visit")
	}
	if !strings.Contains(string(body), "元数据数据库") {
		t.Fatal("expected database config section")
	}
	if !strings.Contains(string(body), "AI 智能体配置") {
		t.Fatal("expected AI config section")
	}
	if !strings.Contains(string(body), `value="mysql" selected`) {
		t.Fatal("expected MySQL to be selected by default")
	}
	if !strings.Contains(string(body), `id="installForm"`) {
		t.Fatal("expected install form id for wizard script binding")
	}
	if !strings.Contains(string(body), `data-db-block="sqlite" hidden`) {
		t.Fatal("expected SQLite fields hidden for default MySQL install")
	}
}

func TestHomeShowsPublicPageAfterInitialization(t *testing.T) {
	stateFile := writeInstalledState(t, "Test Admin", "admin", "secret123")
	server := httptest.NewServer(NewRouter(RouterOptions{InstallStateFile: stateFile, AdminPassword: "secret123", DisableTaskWorker: true}))
	defer server.Close()

	resp, err := http.Get(server.URL + "/")
	if err != nil {
		t.Fatalf("GET / failed: %v", err)
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		t.Fatalf("expected status 200, got %d", resp.StatusCode)
	}
	body, err := io.ReadAll(resp.Body)
	if err != nil {
		t.Fatalf("read response: %v", err)
	}
	if !strings.Contains(string(body), "AI 数据工作台") {
		t.Fatal("expected public home page title")
	}
	if !strings.Contains(string(body), "项目进展") {
		t.Fatal("expected project progress content")
	}
	if !strings.Contains(string(body), "prefers-color-scheme: dark") {
		t.Fatal("expected public home to support dark mode")
	}
	if !strings.Contains(string(body), "/moyi-7k3x9-admin/login") || !strings.Contains(string(body), "进入后台管理") {
		t.Fatal("expected public home to expose debug admin entry")
	}
	if !strings.Contains(string(body), "默认账号") || !strings.Contains(string(body), "secret123") {
		t.Fatal("expected public home to show debug login credentials")
	}
}

func TestHomeHidesDebugEntryInProduction(t *testing.T) {
	stateFile := writeInstalledState(t, "Test Admin", "admin", "secret123")
	server := httptest.NewServer(NewRouter(RouterOptions{Env: "production", InstallStateFile: stateFile, AdminPassword: "secret123", DisableTaskWorker: true}))
	defer server.Close()

	resp, err := http.Get(server.URL + "/")
	if err != nil {
		t.Fatalf("GET / failed: %v", err)
	}
	defer resp.Body.Close()
	body, err := io.ReadAll(resp.Body)
	if err != nil {
		t.Fatalf("read response: %v", err)
	}
	bodyText := string(body)
	if strings.Contains(bodyText, "/moyi-7k3x9-admin/login") || strings.Contains(bodyText, "默认密码") || strings.Contains(bodyText, "secret123") {
		t.Fatal("production home should not expose debug admin entry or credentials")
	}
}

func TestAdminEntryRedirectsToInstallBeforeInitialization(t *testing.T) {
	server := httptest.NewServer(NewRouter(RouterOptions{}))
	defer server.Close()

	client := server.Client()
	client.CheckRedirect = func(req *http.Request, via []*http.Request) error {
		return http.ErrUseLastResponse
	}

	resp, err := client.Get(server.URL + "/moyi-7k3x9-admin")
	if err != nil {
		t.Fatalf("GET hidden admin entry failed: %v", err)
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusFound {
		t.Fatalf("expected status 302, got %d", resp.StatusCode)
	}
	if location := resp.Header.Get("Location"); location != "/moyi-7k3x9-admin/install" {
		t.Fatalf("expected redirect to hidden install, got %q", location)
	}
}

func TestAdminInstallPage(t *testing.T) {
	server := httptest.NewServer(NewRouter(RouterOptions{}))
	defer server.Close()

	resp, err := http.Get(server.URL + "/moyi-7k3x9-admin/install")
	if err != nil {
		t.Fatalf("GET hidden admin install failed: %v", err)
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		t.Fatalf("expected status 200, got %d", resp.StatusCode)
	}
	body, err := io.ReadAll(resp.Body)
	if err != nil {
		t.Fatalf("read response: %v", err)
	}
	if !strings.Contains(string(body), "系统初始化") {
		t.Fatal("expected install page")
	}
	if !strings.Contains(string(body), "完成初始化") {
		t.Fatal("expected install submit button")
	}
	if !strings.Contains(string(body), "元数据数据库") {
		t.Fatal("expected database config section")
	}
	if !strings.Contains(string(body), "AI 智能体配置") {
		t.Fatal("expected AI config section")
	}
	if !strings.Contains(string(body), "阿里云百炼") {
		t.Fatal("expected Bailian provider option")
	}
	if !strings.Contains(string(body), `name="db_driver"`) {
		t.Fatal("expected database driver field")
	}
	if !strings.Contains(string(body), "MySQL（推荐，兼容旧系统）") {
		t.Fatal("expected MySQL recommendation")
	}
	if !strings.Contains(string(body), `method="post"`) {
		t.Fatal("expected install form to post")
	}
}

func TestAdminInstallSubmitCreatesInitialState(t *testing.T) {
	stateFile := filepath.Join(t.TempDir(), "install_state.json")
	sqlitePath := filepath.Join(filepath.Dir(stateFile), "moyi-admin.db")
	server := httptest.NewServer(NewRouter(RouterOptions{InstallStateFile: stateFile, DisableTaskWorker: true}))
	defer server.Close()
	aiServer := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path != "/compatible-mode/v1/chat/completions" {
			t.Fatalf("unexpected AI check path %q", r.URL.Path)
		}
		if got := r.Header.Get("Authorization"); got != "Bearer sk-test-bailian" {
			t.Fatalf("unexpected AI authorization header %q", got)
		}
		_, _ = io.Copy(io.Discard, r.Body)
		w.Header().Set("Content-Type", "application/json")
		_, _ = w.Write([]byte(`{"choices":[{"message":{"content":"ok"}}]}`))
	}))
	defer aiServer.Close()

	client := server.Client()
	client.CheckRedirect = func(req *http.Request, via []*http.Request) error {
		return http.ErrUseLastResponse
	}

	resp, err := client.PostForm(server.URL+"/moyi-7k3x9-admin/install", url.Values{
		"site_name":             {"Test Admin"},
		"db_driver":             {"sqlite"},
		"db_file_path":          {sqlitePath},
		"ai_provider":           {"bailian"},
		"ai_api_key":            {"sk-test-bailian"},
		"ai_base_url":           {aiServer.URL + "/compatible-mode/v1"},
		"ai_chat_model":         {"qwen-plus"},
		"username":              {"root_user"},
		"password":              {"secret123"},
		"password_confirmation": {"secret123"},
	})
	if err != nil {
		t.Fatalf("POST hidden admin install failed: %v", err)
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusFound {
		t.Fatalf("expected status 302, got %d", resp.StatusCode)
	}

	state, err := newInstallStore(stateFile).Load()
	if err != nil {
		t.Fatalf("load install state: %v", err)
	}
	if location := resp.Header.Get("Location"); location != state.AdminEntry+"/login?installed=1" {
		t.Fatalf("expected redirect to generated login success, got %q; state entry %q", location, state.AdminEntry)
	}
	if !state.Initialized {
		t.Fatal("expected initialized state")
	}
	if !isGeneratedAdminEntry(state.AdminEntry) {
		t.Fatalf("expected generated admin entry, got %q", state.AdminEntry)
	}
	if state.SiteName != "Test Admin" || state.AdminUser != "root_user" {
		t.Fatalf("unexpected state: %+v", state)
	}
	if state.Database.Driver != "sqlite" || state.Database.FilePath != sqlitePath {
		t.Fatalf("unexpected database config: %+v", state.Database)
	}
	if state.AI.Provider != "bailian" || state.AI.ChatModel != "qwen-plus" || state.AI.APIKey != "sk-test-bailian" {
		t.Fatalf("unexpected AI config: %+v", state.AI)
	}
	if !state.TaskWorker.Enabled || !state.TaskWorker.ScheduleHealthEnabled || !state.TaskWorker.ScheduleCleanupEnabled {
		t.Fatalf("expected automatic queue to be enabled after install, got %+v", state.TaskWorker)
	}
	if !state.credentialsMatch("root_user", "secret123") {
		t.Fatal("expected stored credentials to match")
	}
}

func TestInstallStorePersistsMetadataInSQLiteTables(t *testing.T) {
	adminSalt, adminHash, err := hashPassword("secret123")
	if err != nil {
		t.Fatalf("hash admin password: %v", err)
	}
	opsSalt, opsHash, err := hashPassword("ops12345")
	if err != nil {
		t.Fatalf("hash ops password: %v", err)
	}

	dataDir := t.TempDir()
	now := time.Date(2026, 5, 15, 10, 30, 0, 0, time.UTC)
	stateFile := filepath.Join(dataDir, "install_state.json")
	sourceDB := filepath.Join(dataDir, "business.db")
	store := newInstallStore(stateFile)
	err = store.Save(installState{
		Initialized: true,
		SiteName:    "Metadata Admin",
		AdminEntry:  "/moyi-test-admin",
		AdminUser:   "admin",
		Database: databaseConfig{
			Driver:   "sqlite",
			FilePath: sourceDB,
		},
		AI: aiConfig{
			Provider:  "bailian",
			APIKey:    "sk-test",
			BaseURL:   "https://dashscope.aliyuncs.com/compatible-mode/v1",
			ChatModel: "qwen-plus",
		},
		System: systemConfig{
			Timezone:          "Asia/Shanghai",
			Locale:            "zh-CN",
			AdminTagline:      "Ops Console",
			PublicTagline:     "迁移进展",
			PublicHeadline:    "Moyi Admin 基础设施迁移中",
			PublicDescription: "公开首页展示当前迁移状态。",
		},
		Storage: storageConfig{
			Driver:                   "local",
			LocalPath:                filepath.Join(dataDir, "uploads"),
			PublicURL:                "/uploads",
			MaxFileSizeMB:            64,
			AllowedExtensions:        ".pdf,.xlsx",
			AgentExportRetentionDays: 15,
		},
		Security: securityConfig{
			SessionTTLHours:  6,
			LoginMaxAttempts: 3,
			LoginLockMinutes: 10,
		},
		Notifications: notificationConfig{
			Enabled:             true,
			Channel:             "webhook",
			Receiver:            "运维群",
			WebhookURL:          "https://example.com/webhook",
			EventLoginFailures:  true,
			EventAIErrors:       true,
			EventStorageWarning: false,
		},
		DataSources: []dataSourceConfig{
			{
				Name:          "business_main",
				Driver:        "sqlite",
				FilePath:      sourceDB,
				Role:          "readonly",
				Status:        "ready",
				LastMessage:   "连接正常",
				SchemaSummary: "orders 订单表",
				LastCheckedAt: now,
			},
		},
		Access: accessConfig{
			Users: []adminAccountConfig{
				{
					Username:     "ops_user",
					DisplayName:  "运维用户",
					Role:         "ops_admin",
					Status:       "enabled",
					PasswordSalt: opsSalt,
					PasswordHash: opsHash,
					Source:       "access_config",
					CreatedAt:    now,
					UpdatedAt:    now,
				},
			},
		},
		PasswordSalt: adminSalt,
		PasswordHash: adminHash,
		InstalledAt:  now,
	})
	if err != nil {
		t.Fatalf("save metadata state: %v", err)
	}
	if err := store.AppendAuditRecord(adminAuditRecord{
		Timestamp:  now,
		Category:   "settings",
		Action:     "save_storage",
		Actor:      "admin",
		Detail:     "保存存储设置",
		Method:     http.MethodPost,
		Path:       "/moyi-test-admin/settings/storage",
		StatusCode: http.StatusFound,
		DurationMS: 12,
	}); err != nil {
		t.Fatalf("append audit record: %v", err)
	}
	if err := store.AppendNotificationDelivery(adminNotificationDeliveryRecord{
		Timestamp:  now,
		Event:      "notification_test",
		Title:      "测试通知",
		Receiver:   "运维群",
		Channel:    "webhook",
		Target:     "https://example.com/webhook",
		Message:    "通知测试发送成功",
		Status:     "sent",
		StatusCode: http.StatusAccepted,
	}); err != nil {
		t.Fatalf("append notification delivery: %v", err)
	}
	if err := store.AppendSchemaSnapshot(adminSchemaSnapshotRecord{
		DataSourceName: "business_main",
		Driver:         "SQLite",
		Target:         sourceDB,
		Summary:        "发现 1 张表、3 个字段",
		TableCount:     1,
		ColumnCount:    3,
		SchemaHash:     "schemahash1",
		ChecksJSON:     `["表数量：1","字段数量：3","表清单：orders(订单表)"]`,
		SchemaJSON:     `[{"name":"orders","comment":"订单表","columns":[{"name":"id","type":"integer","nullable":false,"key":"PK"}]}]`,
		CapturedAt:     now,
	}); err != nil {
		t.Fatalf("append schema snapshot: %v", err)
	}
	if err := store.AppendSettingChange(adminSettingChangeRecord{
		Timestamp:  now,
		Category:   "storage",
		Action:     "保存存储设置",
		Actor:      "admin",
		Summary:    "更新本地存储目录",
		BeforeJSON: `{"driver":"local","path":"old"}`,
		AfterJSON:  `{"driver":"local","path":"new"}`,
	}); err != nil {
		t.Fatalf("append setting change: %v", err)
	}
	if err := store.AppendAdminSession(adminSessionRecord{
		ID:        "session-admin-1",
		Username:  "admin",
		IP:        "127.0.0.1",
		UserAgent: "metadata-test",
		Status:    "active",
		CreatedAt: now,
		ExpiresAt: now.Add(2 * time.Hour),
	}); err != nil {
		t.Fatalf("append admin session: %v", err)
	}
	if err := store.AppendAgentRun(agentRunRecord{
		ID:         "run-test-1",
		SessionID:  "session-test-1",
		Actor:      "admin",
		Mode:       string(agentIntentTableCatalog),
		Goal:       "列出当前可查询的数据表",
		Message:    "列出当前可查询的数据表",
		Reply:      "已列出当前可查询的数据表",
		Status:     "ok",
		ModelUsed:  false,
		ToolCount:  1,
		FileCount:  0,
		DurationMS: 18,
		StartedAt:  now,
		Run: agentRun{
			ID:   "run-test-1",
			Mode: string(agentIntentTableCatalog),
			Goal: "列出当前可查询的数据表",
			Plan: []agentPlanStep{{Title: "读取表清单", Detail: "读取元数据表", Status: "done"}},
		},
		ToolResults: []agentToolResult{
			{Name: "list_tables", OK: true, Message: "已读取表清单", Columns: []string{"name"}, Rows: []map[string]string{{"name": "install_state"}}},
		},
	}); err != nil {
		t.Fatalf("append agent run: %v", err)
	}

	dbPath := filepath.Join(dataDir, "moyi-admin-meta.db")
	if _, err := os.Stat(dbPath); err != nil {
		t.Fatalf("expected metadata sqlite database: %v", err)
	}
	db, err := sql.Open("sqlite", dbPath)
	if err != nil {
		t.Fatalf("open metadata sqlite database: %v", err)
	}
	defer db.Close()
	assertCount := func(name string, min int, query string, args ...any) {
		t.Helper()
		var count int
		if err := db.QueryRow(query, args...).Scan(&count); err != nil {
			t.Fatalf("query %s: %v", name, err)
		}
		if count < min {
			t.Fatalf("expected %s count >= %d, got %d", name, min, count)
		}
	}
	assertCount("install_state", 1, `SELECT COUNT(*) FROM install_state WHERE site_name = ? AND ai_provider = ?`, "Metadata Admin", "bailian")
	assertCount("install_state system", 1, `SELECT COUNT(*) FROM install_state WHERE system_admin_tagline = ? AND system_public_headline = ?`, "Ops Console", "Moyi Admin 基础设施迁移中")
	assertCount("install_state security", 1, `SELECT COUNT(*) FROM install_state WHERE security_session_ttl_hours = ? AND security_login_max_attempts = ? AND security_login_lock_minutes = ?`, 6, 3, 10)
	assertCount("install_state notifications", 1, `SELECT COUNT(*) FROM install_state WHERE notification_enabled = 1 AND notification_channel = ? AND notification_receiver = ?`, "webhook", "运维群")
	assertCount("admin_users", 2, `SELECT COUNT(*) FROM admin_users WHERE username IN (?, ?)`, "admin", "ops_user")
	assertCount("admin_roles", 3, `SELECT COUNT(*) FROM admin_roles`)
	assertCount("admin_menus", 9, `SELECT COUNT(*) FROM admin_menus`)
	assertCount("admin_permissions", 8, `SELECT COUNT(*) FROM admin_permissions`)
	assertCount("data_sources", 1, `SELECT COUNT(*) FROM data_sources WHERE name = ? AND schema_summary = ?`, "business_main", "orders 订单表")
	assertCount("schema_snapshots", 1, `SELECT COUNT(*) FROM schema_snapshots WHERE data_source_name = ? AND table_count = ?`, "business_main", 1)
	assertCount("setting_change_logs", 1, `SELECT COUNT(*) FROM setting_change_logs WHERE category = ? AND action = ?`, "storage", "保存存储设置")
	assertCount("audit_events", 1, `SELECT COUNT(*) FROM audit_events WHERE category = ? AND action = ?`, "settings", "save_storage")
	assertCount("notification_deliveries", 1, `SELECT COUNT(*) FROM notification_deliveries WHERE event = ? AND status = ?`, "notification_test", "sent")
	assertCount("admin_sessions", 1, `SELECT COUNT(*) FROM admin_sessions WHERE id = ? AND status = ?`, "session-admin-1", "active")
	assertCount("agent_sessions", 1, `SELECT COUNT(*) FROM agent_sessions WHERE id = ? AND run_count = 1`, "session-test-1")
	assertCount("agent_runs", 1, `SELECT COUNT(*) FROM agent_runs WHERE id = ? AND mode = ?`, "run-test-1", string(agentIntentTableCatalog))
	assertCount("agent_tool_results", 1, `SELECT COUNT(*) FROM agent_tool_results WHERE run_id = ? AND name = ?`, "run-test-1", "list_tables")

	loaded, err := store.Load()
	if err != nil {
		t.Fatalf("load metadata state: %v", err)
	}
	if len(loaded.Access.Users) != 1 || loaded.Access.Users[0].Username != "ops_user" {
		t.Fatalf("expected bootstrap admin to be stored in admin_users but hidden from access config, got %+v", loaded.Access.Users)
	}
	if len(loaded.DataSources) != 1 || loaded.DataSources[0].SchemaSummary != "orders 订单表" {
		t.Fatalf("expected data source loaded from sqlite table, got %+v", loaded.DataSources)
	}
	if loaded.Security.SessionTTLHours != 6 || loaded.Security.LoginMaxAttempts != 3 || loaded.Security.LoginLockMinutes != 10 {
		t.Fatalf("expected security policy loaded from sqlite table, got %+v", loaded.Security)
	}
	if loaded.System.AdminTagline != "Ops Console" || loaded.System.PublicHeadline != "Moyi Admin 基础设施迁移中" {
		t.Fatalf("expected system display settings loaded from sqlite table, got %+v", loaded.System)
	}
	if !loaded.Notifications.Enabled || loaded.Notifications.Channel != "webhook" || loaded.Notifications.Receiver != "运维群" || loaded.Notifications.EventStorageWarning {
		t.Fatalf("expected notification settings loaded from sqlite table, got %+v", loaded.Notifications)
	}
	events, err := store.ListAuditEvents(5)
	if err != nil {
		t.Fatalf("list audit events: %v", err)
	}
	if len(events) != 1 || events[0].Category != "settings" || events[0].Action != "save_storage" {
		t.Fatalf("expected audit event loaded from sqlite table, got %+v", events)
	}
	deliveries, err := store.ListNotificationDeliveries(5)
	if err != nil {
		t.Fatalf("list notification deliveries: %v", err)
	}
	if len(deliveries) != 1 || deliveries[0].Event != "notification_test" || deliveries[0].Status != "sent" {
		t.Fatalf("expected notification delivery loaded from sqlite table, got %+v", deliveries)
	}
	snapshots, err := store.ListSchemaSnapshots(5)
	if err != nil {
		t.Fatalf("list schema snapshots: %v", err)
	}
	if len(snapshots) != 1 || snapshots[0].DataSourceName != "business_main" || !strings.Contains(snapshots[0].SchemaJSON, "orders") {
		t.Fatalf("expected schema snapshot loaded from sqlite table, got %+v", snapshots)
	}
	changes, err := store.ListSettingChanges(5)
	if err != nil {
		t.Fatalf("list setting changes: %v", err)
	}
	if len(changes) != 1 || changes[0].Category != "storage" || changes[0].Action != "保存存储设置" || !strings.Contains(changes[0].AfterJSON, "new") {
		t.Fatalf("expected setting change loaded from sqlite table, got %+v", changes)
	}
	adminSessions, err := store.ListAdminSessions(5)
	if err != nil {
		t.Fatalf("list admin sessions: %v", err)
	}
	if len(adminSessions) != 1 || adminSessions[0].ID != "session-admin-1" {
		t.Fatalf("expected admin session loaded from sqlite table, got %+v", adminSessions)
	}
	runs, err := store.ListAgentRuns(5)
	if err != nil {
		t.Fatalf("list agent runs: %v", err)
	}
	if len(runs) != 1 || runs[0].ID != "run-test-1" || runs[0].ToolCount != 1 {
		t.Fatalf("expected agent run loaded from sqlite table, got %+v", runs)
	}
}

func TestAdminInstallSubmitRejectsFailedDatabaseCheck(t *testing.T) {
	stateFile := filepath.Join(t.TempDir(), "install_state.json")
	server := httptest.NewServer(NewRouter(RouterOptions{InstallStateFile: stateFile, DisableTaskWorker: true}))
	defer server.Close()

	resp, err := http.PostForm(server.URL+"/moyi-7k3x9-admin/install", url.Values{
		"site_name":             {"Test Admin"},
		"db_driver":             {"mysql"},
		"db_host":               {"127.0.0.1"},
		"db_port":               {"1"},
		"db_name":               {"moyi_admin"},
		"db_username":           {"root"},
		"db_password":           {"rootpass"},
		"db_ssl_mode":           {"disable"},
		"username":              {"root_user"},
		"password":              {"secret123"},
		"password_confirmation": {"secret123"},
	})
	if err != nil {
		t.Fatalf("POST hidden admin install failed: %v", err)
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusBadRequest {
		t.Fatalf("expected status 400, got %d", resp.StatusCode)
	}
	body, err := io.ReadAll(resp.Body)
	if err != nil {
		t.Fatalf("read response: %v", err)
	}
	if !strings.Contains(string(body), "数据库检查未通过") {
		t.Fatal("expected database check error")
	}
	if strings.Contains(string(body), "rootpass") {
		t.Fatal("database password should not be rendered back into the page")
	}

	state, err := newInstallStore(stateFile).Load()
	if err != nil {
		t.Fatalf("load install state: %v", err)
	}
	if state.Initialized {
		t.Fatal("system should not initialize when database check fails")
	}
}

func TestRootInstallSubmitCreatesInitialState(t *testing.T) {
	stateFile := filepath.Join(t.TempDir(), "install_state.json")
	server := httptest.NewServer(NewRouter(RouterOptions{InstallStateFile: stateFile}))
	defer server.Close()

	client := server.Client()
	client.CheckRedirect = func(req *http.Request, via []*http.Request) error {
		return http.ErrUseLastResponse
	}

	resp, err := client.PostForm(server.URL+"/", url.Values{
		"site_name":             {"Root Install"},
		"db_driver":             {"sqlite"},
		"db_file_path":          {filepath.Join(t.TempDir(), "moyi-admin.db")},
		"username":              {"root_user"},
		"password":              {"secret123"},
		"password_confirmation": {"secret123"},
	})
	if err != nil {
		t.Fatalf("POST root install failed: %v", err)
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusFound {
		t.Fatalf("expected status 302, got %d", resp.StatusCode)
	}
	state, err := newInstallStore(stateFile).Load()
	if err != nil {
		t.Fatalf("load install state: %v", err)
	}
	if location := resp.Header.Get("Location"); location != state.AdminEntry+"/login?installed=1" {
		t.Fatalf("expected redirect to generated login success, got %q; state entry %q", location, state.AdminEntry)
	}
	if !isGeneratedAdminEntry(state.AdminEntry) {
		t.Fatalf("expected generated admin entry, got %q", state.AdminEntry)
	}
}

func TestGeneratedAdminEntryIsRequiredAfterInitialization(t *testing.T) {
	stateFile := filepath.Join(t.TempDir(), "install_state.json")
	server := httptest.NewServer(NewRouter(RouterOptions{InstallStateFile: stateFile}))
	defer server.Close()

	client := server.Client()
	client.CheckRedirect = func(req *http.Request, via []*http.Request) error {
		return http.ErrUseLastResponse
	}

	resp, err := client.PostForm(server.URL+"/", url.Values{
		"site_name":             {"Random Entry Admin"},
		"db_driver":             {"sqlite"},
		"db_file_path":          {filepath.Join(t.TempDir(), "moyi-admin.db")},
		"username":              {"root_user"},
		"password":              {"secret123"},
		"password_confirmation": {"secret123"},
	})
	if err != nil {
		t.Fatalf("POST root install failed: %v", err)
	}
	resp.Body.Close()
	if resp.StatusCode != http.StatusFound {
		t.Fatalf("expected install redirect status 302, got %d", resp.StatusCode)
	}

	state, err := newInstallStore(stateFile).Load()
	if err != nil {
		t.Fatalf("load install state: %v", err)
	}
	if !isGeneratedAdminEntry(state.AdminEntry) {
		t.Fatalf("expected generated admin entry, got %q", state.AdminEntry)
	}

	oldLoginResp, err := client.Get(server.URL + "/moyi-7k3x9-admin/login")
	if err != nil {
		t.Fatalf("GET old fixed login failed: %v", err)
	}
	oldLoginResp.Body.Close()
	if oldLoginResp.StatusCode != http.StatusNotFound {
		t.Fatalf("expected old fixed login to be hidden after init, got %d", oldLoginResp.StatusCode)
	}

	generatedLoginResp, err := client.Get(server.URL + state.AdminEntry + "/login")
	if err != nil {
		t.Fatalf("GET generated login failed: %v", err)
	}
	defer generatedLoginResp.Body.Close()
	if generatedLoginResp.StatusCode != http.StatusOK {
		t.Fatalf("expected generated login status 200, got %d", generatedLoginResp.StatusCode)
	}
}

func TestDatabaseCheckSQLite(t *testing.T) {
	server := httptest.NewServer(NewRouter(RouterOptions{}))
	defer server.Close()

	resp, err := http.PostForm(server.URL+"/api/install/check-database", url.Values{
		"db_driver":    {"sqlite"},
		"db_file_path": {filepath.Join(t.TempDir(), "moyi-admin.db")},
	})
	if err != nil {
		t.Fatalf("POST database check failed: %v", err)
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		t.Fatalf("expected status 200, got %d", resp.StatusCode)
	}
	var body map[string]any
	if err := json.NewDecoder(resp.Body).Decode(&body); err != nil {
		t.Fatalf("decode response: %v", err)
	}
	if body["ok"] != true {
		t.Fatalf("expected ok database check, got %+v", body)
	}
}

func TestDatabaseCheckSQLiteReadsRealSchema(t *testing.T) {
	sqlitePath := filepath.Join(t.TempDir(), "business.db")
	db, err := sql.Open("sqlite", sqlitePath)
	if err != nil {
		t.Fatalf("open sqlite fixture: %v", err)
	}
	if _, err := db.Exec(`CREATE TABLE admin_notes (id INTEGER PRIMARY KEY, title TEXT NOT NULL, created_at TEXT)`); err != nil {
		_ = db.Close()
		t.Fatalf("create sqlite fixture table: %v", err)
	}
	if err := db.Close(); err != nil {
		t.Fatalf("close sqlite fixture: %v", err)
	}

	server := httptest.NewServer(NewRouter(RouterOptions{}))
	defer server.Close()

	resp, err := http.PostForm(server.URL+"/api/install/check-database", url.Values{
		"db_driver":    {"sqlite"},
		"db_file_path": {sqlitePath},
	})
	if err != nil {
		t.Fatalf("POST database check failed: %v", err)
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		t.Fatalf("expected status 200, got %d", resp.StatusCode)
	}
	var body map[string]any
	if err := json.NewDecoder(resp.Body).Decode(&body); err != nil {
		t.Fatalf("decode response: %v", err)
	}
	checks, _ := body["checks"].([]any)
	checkText := fmt.Sprint(checks)
	if body["ok"] != true || !strings.Contains(checkText, "表数量：1") || !strings.Contains(checkText, "admin_notes") || !strings.Contains(checkText, "title TEXT NOT NULL") {
		t.Fatalf("expected real sqlite schema checks, got %+v", body)
	}
}

func TestDatabaseSchemaSummaryIncludesCommentsAndIndexes(t *testing.T) {
	inspection := summarizeDatabaseSchema([]inspectedDatabaseTable{
		{
			Name:    "admin_users",
			Kind:    "BASE TABLE",
			Comment: "后台管理员账号",
			Columns: []inspectedDatabaseColumn{
				{Name: "id", Type: "bigint", Key: "PK", Nullable: false, Comment: "主键"},
				{Name: "username", Type: "varchar(64)", Nullable: false, Comment: "登录账号"},
				{Name: "status", Type: "varchar(16)", Nullable: true, Comment: "启用状态"},
			},
			Indexes: []string{"PRIMARY(id)", "uniq_username(username) UNIQUE"},
		},
	})
	checkText := strings.Join(inspection.Checks, "；")
	for _, expected := range []string{"发现 1 张表、3 个字段", "admin_users(后台管理员账号)", "username varchar(64) NOT NULL 注释:登录账号", "uniq_username(username) UNIQUE"} {
		if !strings.Contains(inspection.Summary+"；"+checkText, expected) {
			t.Fatalf("expected schema summary to contain %q, got summary=%q checks=%+v", expected, inspection.Summary, inspection.Checks)
		}
	}
}

func TestPostgresConnectionStringNormalizesDriverAndSSL(t *testing.T) {
	config := networkDatabaseConfig{
		Driver:   "postgresql",
		Host:     "127.0.0.1",
		Port:     "5432",
		Database: "moyi_admin",
		Username: "admin@example.com",
		Password: "p@ss word",
	}
	if got := normalizeDatabaseDriver(config.Driver); got != "postgres" {
		t.Fatalf("expected postgres driver normalization, got %q", got)
	}
	dsn := postgresConnectionString(config)
	for _, expected := range []string{"postgres://", "127.0.0.1:5432", "moyi_admin", "sslmode=disable", "connect_timeout=3"} {
		if !strings.Contains(dsn, expected) {
			t.Fatalf("expected postgres dsn to contain %q, got %q", expected, dsn)
		}
	}
}

func TestAICheckDisabled(t *testing.T) {
	server := httptest.NewServer(NewRouter(RouterOptions{}))
	defer server.Close()

	resp, err := http.PostForm(server.URL+"/api/install/check-ai", url.Values{
		"ai_provider": {"disabled"},
	})
	if err != nil {
		t.Fatalf("POST AI check failed: %v", err)
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		t.Fatalf("expected status 200, got %d", resp.StatusCode)
	}
	var body map[string]any
	if err := json.NewDecoder(resp.Body).Decode(&body); err != nil {
		t.Fatalf("decode response: %v", err)
	}
	if body["ok"] != true {
		t.Fatalf("expected ok AI check, got %+v", body)
	}
}

func TestAICheckBailianCompatibleEndpoint(t *testing.T) {
	aiServer := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path != "/v1/chat/completions" {
			t.Fatalf("unexpected path %q", r.URL.Path)
		}
		if got := r.Header.Get("Authorization"); got != "Bearer sk-test-bailian" {
			t.Fatalf("unexpected authorization %q", got)
		}
		var payload map[string]any
		if err := json.NewDecoder(r.Body).Decode(&payload); err != nil {
			t.Fatalf("decode payload: %v", err)
		}
		if payload["model"] != "qwen-plus" {
			t.Fatalf("unexpected model payload: %+v", payload)
		}
		w.Header().Set("Content-Type", "application/json")
		_, _ = w.Write([]byte(`{"choices":[{"message":{"content":"ok"}}]}`))
	}))
	defer aiServer.Close()

	server := httptest.NewServer(NewRouter(RouterOptions{}))
	defer server.Close()

	resp, err := http.PostForm(server.URL+"/api/install/check-ai", url.Values{
		"ai_provider":   {"bailian"},
		"ai_api_key":    {"sk-test-bailian"},
		"ai_base_url":   {aiServer.URL + "/v1"},
		"ai_chat_model": {"qwen-plus"},
	})
	if err != nil {
		t.Fatalf("POST AI check failed: %v", err)
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		body, _ := io.ReadAll(resp.Body)
		t.Fatalf("expected status 200, got %d: %s", resp.StatusCode, body)
	}
	var body map[string]any
	if err := json.NewDecoder(resp.Body).Decode(&body); err != nil {
		t.Fatalf("decode response: %v", err)
	}
	if body["ok"] != true {
		t.Fatalf("expected ok AI check, got %+v", body)
	}
}

func TestAdminLoginRedirectsToInstallBeforeInitialization(t *testing.T) {
	server := httptest.NewServer(NewRouter(RouterOptions{}))
	defer server.Close()

	client := server.Client()
	client.CheckRedirect = func(req *http.Request, via []*http.Request) error {
		return http.ErrUseLastResponse
	}

	resp, err := client.Get(server.URL + "/moyi-7k3x9-admin/login")
	if err != nil {
		t.Fatalf("GET hidden login failed: %v", err)
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusFound {
		t.Fatalf("expected status 302, got %d", resp.StatusCode)
	}
	if location := resp.Header.Get("Location"); location != "/moyi-7k3x9-admin/install" {
		t.Fatalf("expected redirect to hidden install, got %q", location)
	}
}

func TestAdminLoginRejectsBadCredentials(t *testing.T) {
	stateFile := writeInstalledState(t, "Test Admin", "admin", "secret123")
	server := httptest.NewServer(NewRouter(RouterOptions{InstallStateFile: stateFile}))
	defer server.Close()

	resp, err := http.PostForm(server.URL+"/moyi-7k3x9-admin/login", url.Values{
		"username": {"admin"},
		"password": {"wrong"},
	})
	if err != nil {
		t.Fatalf("POST hidden admin login failed: %v", err)
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusUnauthorized {
		t.Fatalf("expected status 401, got %d", resp.StatusCode)
	}
	body, err := io.ReadAll(resp.Body)
	if err != nil {
		t.Fatalf("read response: %v", err)
	}
	if !strings.Contains(string(body), "账号或密码错误") {
		t.Fatal("expected login error")
	}
}

func TestAdminLoginAllowsInitializedCredentials(t *testing.T) {
	stateFile := writeInstalledState(t, "Test Admin", "admin", "secret123")
	server := httptest.NewServer(NewRouter(RouterOptions{InstallStateFile: stateFile}))
	defer server.Close()

	client := server.Client()
	client.CheckRedirect = func(req *http.Request, via []*http.Request) error {
		return http.ErrUseLastResponse
	}

	resp, err := client.PostForm(server.URL+"/moyi-7k3x9-admin/login", url.Values{
		"username": {"admin"},
		"password": {"secret123"},
	})
	if err != nil {
		t.Fatalf("POST hidden admin login failed: %v", err)
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusFound {
		t.Fatalf("expected status 302, got %d", resp.StatusCode)
	}
	if location := resp.Header.Get("Location"); location != "/moyi-7k3x9-admin/workspace" {
		t.Fatalf("expected redirect to workspace, got %q", location)
	}

	var sessionCookie *http.Cookie
	for _, cookie := range resp.Cookies() {
		if cookie.Name == adminSessionCookie {
			sessionCookie = cookie
			break
		}
	}
	if sessionCookie == nil {
		t.Fatal("expected session cookie")
	}

	req, err := http.NewRequest(http.MethodGet, server.URL+"/moyi-7k3x9-admin/workspace", nil)
	if err != nil {
		t.Fatalf("create request: %v", err)
	}
	req.AddCookie(sessionCookie)
	workspaceResp, err := client.Do(req)
	if err != nil {
		t.Fatalf("GET hidden workspace failed: %v", err)
	}
	defer workspaceResp.Body.Close()

	if workspaceResp.StatusCode != http.StatusOK {
		t.Fatalf("expected status 200, got %d", workspaceResp.StatusCode)
	}
	body, err := io.ReadAll(workspaceResp.Body)
	if err != nil {
		t.Fatalf("read response: %v", err)
	}
	bodyText := string(body)
	if !strings.Contains(bodyText, "Go AI 管理台") {
		t.Fatal("expected admin management shell")
	}
	if !strings.Contains(bodyText, "数据源") || !strings.Contains(bodyText, "AI 智能体") || !strings.Contains(bodyText, "用户权限") {
		t.Fatal("expected admin navigation entries")
	}

	aiReq, err := http.NewRequest(http.MethodGet, server.URL+"/moyi-7k3x9-admin/ai", nil)
	if err != nil {
		t.Fatalf("create AI page request: %v", err)
	}
	aiReq.AddCookie(sessionCookie)
	aiResp, err := client.Do(aiReq)
	if err != nil {
		t.Fatalf("GET hidden AI page failed: %v", err)
	}
	defer aiResp.Body.Close()
	if aiResp.StatusCode != http.StatusOK {
		t.Fatalf("expected AI page status 200, got %d", aiResp.StatusCode)
	}
	aiBody, err := io.ReadAll(aiResp.Body)
	if err != nil {
		t.Fatalf("read AI page response: %v", err)
	}
	if !strings.Contains(string(aiBody), "默认模型服务") {
		t.Fatal("expected AI management page")
	}
	if !strings.Contains(string(aiBody), "智能体工作台") {
		t.Fatal("expected AI workbench panel")
	}
	if !strings.Contains(string(aiBody), "/ai/chat") {
		t.Fatal("expected AI chat endpoint in page")
	}
}

func TestAdminLoginPagePrefillsDebugCredentials(t *testing.T) {
	stateFile := writeInstalledState(t, "Test Admin", "admin", "secret123")
	server := httptest.NewServer(NewRouter(RouterOptions{InstallStateFile: stateFile, AdminPassword: "secret123"}))
	defer server.Close()

	resp, err := http.Get(server.URL + "/moyi-7k3x9-admin/login")
	if err != nil {
		t.Fatalf("GET hidden login failed: %v", err)
	}
	defer resp.Body.Close()
	if resp.StatusCode != http.StatusOK {
		t.Fatalf("expected login status 200, got %d", resp.StatusCode)
	}
	body, err := io.ReadAll(resp.Body)
	if err != nil {
		t.Fatalf("read login response: %v", err)
	}
	bodyText := string(body)
	if !strings.Contains(bodyText, `name="username" autocomplete="username" value="admin"`) {
		t.Fatal("expected debug username to be prefilled")
	}
	if !strings.Contains(bodyText, `name="password" type="password" autocomplete="current-password" value="secret123"`) {
		t.Fatal("expected debug password to be prefilled")
	}
	if !strings.Contains(bodyText, "调试模式已自动填充账号密码") {
		t.Fatal("expected debug prefill notice")
	}
}

func TestAdminLoginPageDoesNotPrefillPasswordInProduction(t *testing.T) {
	stateFile := writeInstalledState(t, "Test Admin", "admin", "secret123")
	server := httptest.NewServer(NewRouter(RouterOptions{Env: "production", InstallStateFile: stateFile, AdminPassword: "secret123"}))
	defer server.Close()

	resp, err := http.Get(server.URL + "/moyi-7k3x9-admin/login")
	if err != nil {
		t.Fatalf("GET hidden login failed: %v", err)
	}
	defer resp.Body.Close()
	if resp.StatusCode != http.StatusOK {
		t.Fatalf("expected login status 200, got %d", resp.StatusCode)
	}
	body, err := io.ReadAll(resp.Body)
	if err != nil {
		t.Fatalf("read login response: %v", err)
	}
	bodyText := string(body)
	if strings.Contains(bodyText, `value="secret123"`) || strings.Contains(bodyText, "调试模式已自动填充账号密码") {
		t.Fatal("production login page should not prefill debug password")
	}
}

func TestAdminLoginPageDoesNotPrefillUnknownDebugPasswordAndCachesAfterLogin(t *testing.T) {
	salt, hash, err := hashPassword("821121")
	if err != nil {
		t.Fatalf("hash password: %v", err)
	}
	stateFile := filepath.Join(t.TempDir(), "install_state.json")
	store := newInstallStore(stateFile)
	if err := store.Save(installState{
		Initialized:  true,
		SiteName:     "Test Admin",
		AdminEntry:   "/moyi-7k3x9-admin",
		AdminUser:    "admin",
		Database:     defaultDatabaseConfig(),
		PasswordSalt: salt,
		PasswordHash: hash,
		InstalledAt:  time.Now().UTC(),
	}); err != nil {
		t.Fatalf("save install state: %v", err)
	}
	server := httptest.NewServer(NewRouter(RouterOptions{InstallStateFile: stateFile, AdminPassword: "admin"}))
	defer server.Close()

	resp, err := http.Get(server.URL + "/moyi-7k3x9-admin/login")
	if err != nil {
		t.Fatalf("GET hidden login failed: %v", err)
	}
	body, err := io.ReadAll(resp.Body)
	resp.Body.Close()
	if err != nil {
		t.Fatalf("read login response: %v", err)
	}
	bodyText := string(body)
	if strings.Contains(bodyText, `name="password" type="password" autocomplete="current-password" value="admin"`) || strings.Contains(bodyText, "调试模式已自动填充账号密码") {
		t.Fatal("login page should not prefill an unverified debug password")
	}

	homeResp, err := http.Get(server.URL + "/")
	if err != nil {
		t.Fatalf("GET home failed: %v", err)
	}
	homeBody, err := io.ReadAll(homeResp.Body)
	homeResp.Body.Close()
	if err != nil {
		t.Fatalf("read home response: %v", err)
	}
	if strings.Contains(string(homeBody), "默认密码") {
		t.Fatal("home page should not show a default password when the debug password is unknown")
	}

	client := server.Client()
	client.CheckRedirect = func(req *http.Request, via []*http.Request) error {
		return http.ErrUseLastResponse
	}
	loginReq, err := http.NewRequest(http.MethodPost, server.URL+"/moyi-7k3x9-admin/login", strings.NewReader(url.Values{
		"username": {"admin"},
		"password": {"821121"},
	}.Encode()))
	if err != nil {
		t.Fatalf("create login request: %v", err)
	}
	loginReq.Header.Set("Content-Type", "application/x-www-form-urlencoded")
	loginResp, err := client.Do(loginReq)
	if err != nil {
		t.Fatalf("POST login failed: %v", err)
	}
	loginResp.Body.Close()
	if loginResp.StatusCode != http.StatusFound {
		t.Fatalf("expected login redirect, got %d", loginResp.StatusCode)
	}
	state, err := store.Load()
	if err != nil {
		t.Fatalf("reload state after login: %v", err)
	}
	if state.DebugLoginPassword != "821121" {
		t.Fatalf("expected debug password to be cached after successful local login, got %q", state.DebugLoginPassword)
	}
}

func TestAdminSettingsPageShowsStorageSettings(t *testing.T) {
	stateFile := writeInstalledState(t, "Test Admin", "admin", "secret123")
	server := httptest.NewServer(NewRouter(RouterOptions{InstallStateFile: stateFile}))
	defer server.Close()

	client := server.Client()
	client.CheckRedirect = func(req *http.Request, via []*http.Request) error {
		return http.ErrUseLastResponse
	}
	sessionCookie := loginTestAdmin(t, client, server.URL)

	req, err := http.NewRequest(http.MethodGet, server.URL+"/moyi-7k3x9-admin/settings", nil)
	if err != nil {
		t.Fatalf("create settings request: %v", err)
	}
	req.AddCookie(sessionCookie)
	resp, err := client.Do(req)
	if err != nil {
		t.Fatalf("GET settings page failed: %v", err)
	}
	defer resp.Body.Close()
	if resp.StatusCode != http.StatusOK {
		t.Fatalf("expected settings status 200, got %d", resp.StatusCode)
	}
	body, err := io.ReadAll(resp.Body)
	if err != nil {
		t.Fatalf("read settings response: %v", err)
	}
	bodyText := string(body)
	for _, expected := range []string{"data-settings-hub", `data-settings-tab="storage"`, `data-settings-section="notifications"`, "设置菜单", "基础信息", "存储设置", "AI 模型设置", "登录保护", "安全设置", "通知设置", "飞书机器人", "后台队列", "运行状态", "设置变更记录", "暂无变更记录", "首页主标题", "首页简介", "会话有效期", "本地存储目录", "data/uploads", "/settings/storage", "/settings/ai", "/settings/security", "/settings/notifications", "/settings/notifications/test", "发送测试通知"} {
		if !strings.Contains(bodyText, expected) {
			t.Fatalf("expected settings page to contain %q", expected)
		}
	}
}

func TestAdminFoundationPageShowsLegacyChecklist(t *testing.T) {
	stateFile := writeInstalledState(t, "Test Admin", "admin", "secret123")
	server := httptest.NewServer(NewRouter(RouterOptions{InstallStateFile: stateFile}))
	defer server.Close()

	client := server.Client()
	client.CheckRedirect = func(req *http.Request, via []*http.Request) error {
		return http.ErrUseLastResponse
	}
	sessionCookie := loginTestAdmin(t, client, server.URL)

	req, err := http.NewRequest(http.MethodGet, server.URL+"/moyi-7k3x9-admin/foundation", nil)
	if err != nil {
		t.Fatalf("create foundation request: %v", err)
	}
	req.AddCookie(sessionCookie)
	resp, err := client.Do(req)
	if err != nil {
		t.Fatalf("GET foundation page failed: %v", err)
	}
	defer resp.Body.Close()
	if resp.StatusCode != http.StatusOK {
		t.Fatalf("expected foundation status 200, got %d", resp.StatusCode)
	}
	body, err := io.ReadAll(resp.Body)
	if err != nil {
		t.Fatalf("read foundation response: %v", err)
	}
	bodyText := string(body)
	for _, expected := range []string{"基础服务迁移盘点", "运行审计", "UploadFileController", "LoginLog", "DatabaseConnectionController"} {
		if !strings.Contains(bodyText, expected) {
			t.Fatalf("expected foundation page to contain %q", expected)
		}
	}
}

func TestAdminDataSourceManagementSavesTestsAndDeletes(t *testing.T) {
	stateFile := writeInstalledState(t, "Test Admin", "admin", "secret123")
	sourcePath := filepath.Join(filepath.Dir(stateFile), "business.db")
	server := httptest.NewServer(NewRouter(RouterOptions{InstallStateFile: stateFile}))
	defer server.Close()

	client := server.Client()
	client.CheckRedirect = func(req *http.Request, via []*http.Request) error {
		return http.ErrUseLastResponse
	}
	sessionCookie := loginTestAdmin(t, client, server.URL)

	saveResp, err := postFormWithCookie(t, client, server.URL+"/moyi-7k3x9-admin/data-sources/save", sessionCookie, url.Values{
		"name":      {"business_main"},
		"driver":    {"sqlite"},
		"file_path": {sourcePath},
		"role":      {"业务数据源"},
	})
	if err != nil {
		t.Fatalf("POST data source save failed: %v", err)
	}
	saveResp.Body.Close()
	if saveResp.StatusCode != http.StatusFound {
		t.Fatalf("expected data source save redirect, got %d", saveResp.StatusCode)
	}

	state, err := newInstallStore(stateFile).Load()
	if err != nil {
		t.Fatalf("load state after data source save: %v", err)
	}
	if len(state.DataSources) != 1 || state.DataSources[0].Name != "business_main" || state.DataSources[0].Driver != "sqlite" {
		t.Fatalf("expected saved sqlite data source, got %+v", state.DataSources)
	}

	testResp, err := postFormWithCookie(t, client, server.URL+"/moyi-7k3x9-admin/data-sources/test", sessionCookie, url.Values{
		"name": {"business_main"},
	})
	if err != nil {
		t.Fatalf("POST data source test failed: %v", err)
	}
	testResp.Body.Close()
	if testResp.StatusCode != http.StatusFound {
		t.Fatalf("expected data source test redirect, got %d", testResp.StatusCode)
	}
	state, err = newInstallStore(stateFile).Load()
	if err != nil {
		t.Fatalf("load state after data source test: %v", err)
	}
	if state.DataSources[0].Status != "available" || !strings.Contains(state.DataSources[0].LastMessage, "SQLite") {
		t.Fatalf("expected tested data source to be available, got %+v", state.DataSources[0])
	}

	pageReq, err := http.NewRequest(http.MethodGet, server.URL+"/moyi-7k3x9-admin/data-sources", nil)
	if err != nil {
		t.Fatalf("create data source page request: %v", err)
	}
	pageReq.AddCookie(sessionCookie)
	pageResp, err := client.Do(pageReq)
	if err != nil {
		t.Fatalf("GET data source page failed: %v", err)
	}
	defer pageResp.Body.Close()
	pageBody, err := io.ReadAll(pageResp.Body)
	if err != nil {
		t.Fatalf("read data source page: %v", err)
	}
	for _, expected := range []string{"登记数据源", "business_main", "SQLite 文件尚未创建", "测试", "删除"} {
		if !strings.Contains(string(pageBody), expected) {
			t.Fatalf("expected data source page to contain %q", expected)
		}
	}

	deleteResp, err := postFormWithCookie(t, client, server.URL+"/moyi-7k3x9-admin/data-sources/delete", sessionCookie, url.Values{
		"name": {"business_main"},
	})
	if err != nil {
		t.Fatalf("POST data source delete failed: %v", err)
	}
	deleteResp.Body.Close()
	if deleteResp.StatusCode != http.StatusFound {
		t.Fatalf("expected data source delete redirect, got %d", deleteResp.StatusCode)
	}
	state, err = newInstallStore(stateFile).Load()
	if err != nil {
		t.Fatalf("load state after data source delete: %v", err)
	}
	if len(state.DataSources) != 0 {
		t.Fatalf("expected data source to be deleted, got %+v", state.DataSources)
	}
}

func TestAdminDataSourceTestWritesSchemaSnapshot(t *testing.T) {
	stateFile := writeInstalledState(t, "Test Admin", "admin", "secret123")
	sourcePath := filepath.Join(filepath.Dir(stateFile), "business.db")
	db, err := sql.Open("sqlite", sourcePath)
	if err != nil {
		t.Fatalf("open sqlite data source fixture: %v", err)
	}
	if _, err := db.Exec(`CREATE TABLE admin_notes (id INTEGER PRIMARY KEY, title TEXT NOT NULL, created_at TEXT)`); err != nil {
		_ = db.Close()
		t.Fatalf("create sqlite data source fixture: %v", err)
	}
	if err := db.Close(); err != nil {
		t.Fatalf("close sqlite data source fixture: %v", err)
	}

	server := httptest.NewServer(NewRouter(RouterOptions{InstallStateFile: stateFile, DisableTaskWorker: true}))
	defer server.Close()
	client := server.Client()
	client.CheckRedirect = func(req *http.Request, via []*http.Request) error {
		return http.ErrUseLastResponse
	}
	sessionCookie := loginTestAdmin(t, client, server.URL)

	saveResp, err := postFormWithCookie(t, client, server.URL+"/moyi-7k3x9-admin/data-sources/save", sessionCookie, url.Values{
		"name":      {"business_main"},
		"driver":    {"sqlite"},
		"file_path": {sourcePath},
		"role":      {"业务数据源"},
	})
	if err != nil {
		t.Fatalf("POST data source save failed: %v", err)
	}
	saveResp.Body.Close()
	testResp, err := postFormWithCookie(t, client, server.URL+"/moyi-7k3x9-admin/data-sources/test", sessionCookie, url.Values{
		"name": {"business_main"},
	})
	if err != nil {
		t.Fatalf("POST data source test failed: %v", err)
	}
	testResp.Body.Close()
	if testResp.StatusCode != http.StatusFound {
		t.Fatalf("expected data source test redirect, got %d", testResp.StatusCode)
	}

	store := newInstallStore(stateFile)
	snapshots, err := store.ListSchemaSnapshots(5)
	if err != nil {
		t.Fatalf("list schema snapshots: %v", err)
	}
	if len(snapshots) != 1 {
		t.Fatalf("expected one schema snapshot, got %+v", snapshots)
	}
	snapshot := snapshots[0]
	if snapshot.DataSourceName != "business_main" || snapshot.TableCount != 1 || snapshot.ColumnCount != 3 || !strings.Contains(snapshot.SchemaJSON, "admin_notes") || !strings.Contains(snapshot.ChecksJSON, "title TEXT NOT NULL") {
		t.Fatalf("unexpected schema snapshot: %+v", snapshot)
	}
	state, err := store.Load()
	if err != nil {
		t.Fatalf("load state after schema snapshot: %v", err)
	}
	if !strings.Contains(state.DataSources[0].SchemaSummary, "admin_notes") {
		t.Fatalf("expected data source schema summary to include table name, got %+v", state.DataSources[0])
	}
}

func TestAdminAccessManagementCreatesTogglesDeletesAndAuthenticatesUser(t *testing.T) {
	stateFile := writeInstalledState(t, "Test Admin", "admin", "secret123")
	server := httptest.NewServer(NewRouter(RouterOptions{InstallStateFile: stateFile}))
	defer server.Close()

	client := server.Client()
	client.CheckRedirect = func(req *http.Request, via []*http.Request) error {
		return http.ErrUseLastResponse
	}
	sessionCookie := loginTestAdmin(t, client, server.URL)

	saveResp, err := postFormWithCookie(t, client, server.URL+"/moyi-7k3x9-admin/users/save", sessionCookie, url.Values{
		"username":     {"ops_user"},
		"display_name": {"运维用户"},
		"role":         {"ops_admin"},
		"status":       {"enabled"},
		"password":     {"ops12345"},
	})
	if err != nil {
		t.Fatalf("POST user save failed: %v", err)
	}
	saveResp.Body.Close()
	if saveResp.StatusCode != http.StatusFound {
		t.Fatalf("expected user save redirect, got %d", saveResp.StatusCode)
	}

	state, err := newInstallStore(stateFile).Load()
	if err != nil {
		t.Fatalf("load state after user save: %v", err)
	}
	if len(state.Access.Users) != 1 || state.Access.Users[0].Username != "ops_user" || state.Access.Users[0].PasswordHash == "" {
		t.Fatalf("expected saved access user, got %+v", state.Access.Users)
	}

	opsResp, err := client.PostForm(server.URL+"/moyi-7k3x9-admin/login", url.Values{
		"username": {"ops_user"},
		"password": {"ops12345"},
	})
	if err != nil {
		t.Fatalf("POST ops login failed: %v", err)
	}
	var opsCookie *http.Cookie
	for _, cookie := range opsResp.Cookies() {
		if cookie.Name == adminSessionCookie {
			opsCookie = cookie
			break
		}
	}
	opsResp.Body.Close()
	if opsResp.StatusCode != http.StatusFound {
		t.Fatalf("expected ops login redirect, got %d", opsResp.StatusCode)
	}
	if opsCookie == nil {
		t.Fatal("expected ops session cookie")
	}

	pageReq, err := http.NewRequest(http.MethodGet, server.URL+"/moyi-7k3x9-admin/users", nil)
	if err != nil {
		t.Fatalf("create users page request: %v", err)
	}
	pageReq.AddCookie(sessionCookie)
	pageResp, err := client.Do(pageReq)
	if err != nil {
		t.Fatalf("GET users page failed: %v", err)
	}
	defer pageResp.Body.Close()
	pageBody, err := io.ReadAll(pageResp.Body)
	if err != nil {
		t.Fatalf("read users page: %v", err)
	}
	pageText := string(pageBody)
	for _, expected := range []string{"新增管理员", "ops_user", "运维管理员", "后台会话", "在线", "菜单与权限", "agent.sql.select", "admin.sessions.manage"} {
		if !strings.Contains(pageText, expected) {
			t.Fatalf("expected users page to contain %q", expected)
		}
	}
	sessions, err := newInstallStore(stateFile).ListAdminSessions(10)
	if err != nil {
		t.Fatalf("list admin sessions: %v", err)
	}
	if len(sessions) < 2 {
		t.Fatalf("expected admin and ops sessions, got %+v", sessions)
	}
	opsParts := strings.Split(opsCookie.Value, "|")
	if len(opsParts) != 4 {
		t.Fatalf("expected new session token format, got %q", opsCookie.Value)
	}
	revokeResp, err := postFormWithCookie(t, client, server.URL+"/moyi-7k3x9-admin/users/sessions/revoke", sessionCookie, url.Values{
		"session_id": {opsParts[2]},
	})
	if err != nil {
		t.Fatalf("POST session revoke failed: %v", err)
	}
	revokeResp.Body.Close()
	if revokeResp.StatusCode != http.StatusFound {
		t.Fatalf("expected session revoke redirect, got %d", revokeResp.StatusCode)
	}
	opsWorkspaceReq, err := http.NewRequest(http.MethodGet, server.URL+"/moyi-7k3x9-admin/workspace", nil)
	if err != nil {
		t.Fatalf("create ops workspace request after revoke: %v", err)
	}
	opsWorkspaceReq.AddCookie(opsCookie)
	opsWorkspaceResp, err := client.Do(opsWorkspaceReq)
	if err != nil {
		t.Fatalf("GET ops workspace after revoke failed: %v", err)
	}
	opsWorkspaceResp.Body.Close()
	if opsWorkspaceResp.StatusCode != http.StatusFound {
		t.Fatalf("expected revoked ops session redirect, got %d", opsWorkspaceResp.StatusCode)
	}

	toggleResp, err := postFormWithCookie(t, client, server.URL+"/moyi-7k3x9-admin/users/toggle", sessionCookie, url.Values{
		"username": {"ops_user"},
	})
	if err != nil {
		t.Fatalf("POST user toggle failed: %v", err)
	}
	toggleResp.Body.Close()
	if toggleResp.StatusCode != http.StatusFound {
		t.Fatalf("expected user toggle redirect, got %d", toggleResp.StatusCode)
	}
	disabledResp, err := client.PostForm(server.URL+"/moyi-7k3x9-admin/login", url.Values{
		"username": {"ops_user"},
		"password": {"ops12345"},
	})
	if err != nil {
		t.Fatalf("POST disabled ops login failed: %v", err)
	}
	disabledResp.Body.Close()
	if disabledResp.StatusCode != http.StatusUnauthorized {
		t.Fatalf("expected disabled user login 401, got %d", disabledResp.StatusCode)
	}

	deleteResp, err := postFormWithCookie(t, client, server.URL+"/moyi-7k3x9-admin/users/delete", sessionCookie, url.Values{
		"username": {"ops_user"},
	})
	if err != nil {
		t.Fatalf("POST user delete failed: %v", err)
	}
	deleteResp.Body.Close()
	if deleteResp.StatusCode != http.StatusFound {
		t.Fatalf("expected user delete redirect, got %d", deleteResp.StatusCode)
	}
	state, err = newInstallStore(stateFile).Load()
	if err != nil {
		t.Fatalf("load state after user delete: %v", err)
	}
	if len(state.Access.Users) != 0 {
		t.Fatalf("expected access user deleted, got %+v", state.Access.Users)
	}
}

func TestAdminSettingsUpdatesSystemAndStorage(t *testing.T) {
	stateFile := writeInstalledState(t, "Test Admin", "admin", "secret123")
	server := httptest.NewServer(NewRouter(RouterOptions{InstallStateFile: stateFile}))
	defer server.Close()

	client := server.Client()
	client.CheckRedirect = func(req *http.Request, via []*http.Request) error {
		return http.ErrUseLastResponse
	}
	sessionCookie := loginTestAdmin(t, client, server.URL)

	systemResp, err := postFormWithCookie(t, client, server.URL+"/moyi-7k3x9-admin/settings/system", sessionCookie, url.Values{
		"site_name":          {"Moyi Ops"},
		"timezone":           {"UTC"},
		"locale":             {"en-US"},
		"admin_tagline":      {"Ops 管理台"},
		"public_tagline":     {"迁移进展"},
		"public_headline":    {"Moyi Ops 正在迁移基础设施"},
		"public_description": {"公共首页展示真实项目进展。"},
	})
	if err != nil {
		t.Fatalf("POST system settings failed: %v", err)
	}
	defer systemResp.Body.Close()
	if systemResp.StatusCode != http.StatusFound {
		t.Fatalf("expected system settings redirect, got %d", systemResp.StatusCode)
	}

	uploadDir := filepath.Join(t.TempDir(), "uploads")
	storageResp, err := postFormWithCookie(t, client, server.URL+"/moyi-7k3x9-admin/settings/storage", sessionCookie, url.Values{
		"storage_driver":             {"local"},
		"storage_local_path":         {uploadDir},
		"storage_public_url":         {"/files"},
		"storage_max_file_size_mb":   {"64"},
		"storage_allowed_extensions": {"jpg, PDF, xlsx"},
		"storage_retention_days":     {"30"},
	})
	if err != nil {
		t.Fatalf("POST storage settings failed: %v", err)
	}
	defer storageResp.Body.Close()
	if storageResp.StatusCode != http.StatusFound {
		t.Fatalf("expected storage settings redirect, got %d", storageResp.StatusCode)
	}

	state, err := newInstallStore(stateFile).Load()
	if err != nil {
		t.Fatalf("load updated state: %v", err)
	}
	if state.SiteName != "Moyi Ops" || state.System.Timezone != "UTC" || state.System.Locale != "en-US" {
		t.Fatalf("system settings were not saved: %+v", state)
	}
	if state.System.AdminTagline != "Ops 管理台" || state.System.PublicHeadline != "Moyi Ops 正在迁移基础设施" || state.System.PublicDescription != "公共首页展示真实项目进展。" {
		t.Fatalf("system display settings were not saved: %+v", state.System)
	}
	storage := state.Storage.normalized()
	if storage.LocalPath != uploadDir || storage.PublicURL != "/files" || storage.MaxFileSizeMB != 64 || storage.AgentExportRetentionDays != 30 {
		t.Fatalf("storage settings were not saved: %+v", storage)
	}
	if storage.AllowedExtensions != ".jpg,.pdf,.xlsx" {
		t.Fatalf("unexpected normalized extensions %q", storage.AllowedExtensions)
	}
	if info, err := os.Stat(uploadDir); err != nil || !info.IsDir() {
		t.Fatalf("expected upload directory to be created, info=%+v err=%v", info, err)
	}
	changes, err := newInstallStore(stateFile).ListSettingChanges(10)
	if err != nil {
		t.Fatalf("list setting changes after system/storage save: %v", err)
	}
	seenChanges := map[string]bool{}
	for _, change := range changes {
		seenChanges[change.Category+":"+change.Action] = true
	}
	for _, expected := range []string{"system:保存基础信息", "storage:保存存储设置"} {
		if !seenChanges[expected] {
			t.Fatalf("expected setting change %q, got %+v", expected, changes)
		}
	}

	homeResp, err := client.Get(server.URL + "/")
	if err != nil {
		t.Fatalf("GET public home after system settings failed: %v", err)
	}
	defer homeResp.Body.Close()
	homeBody, err := io.ReadAll(homeResp.Body)
	if err != nil {
		t.Fatalf("read public home after system settings: %v", err)
	}
	homeText := string(homeBody)
	for _, expected := range []string{"Moyi Ops 正在迁移基础设施", "公共首页展示真实项目进展。", "迁移进展"} {
		if !strings.Contains(homeText, expected) {
			t.Fatalf("expected public home to contain %q", expected)
		}
	}
}

func TestAdminSettingsUpdatesNotifications(t *testing.T) {
	stateFile := writeInstalledState(t, "Test Admin", "admin", "secret123")
	server := httptest.NewServer(NewRouter(RouterOptions{InstallStateFile: stateFile}))
	defer server.Close()
	webhookCalls := 0
	webhook := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		webhookCalls++
		if r.Method != http.MethodPost {
			t.Fatalf("expected webhook method POST, got %s", r.Method)
		}
		if contentType := r.Header.Get("Content-Type"); !strings.Contains(contentType, "application/json") {
			t.Fatalf("expected JSON webhook content type, got %q", contentType)
		}
		var payload notificationPayload
		if err := json.NewDecoder(r.Body).Decode(&payload); err != nil {
			t.Fatalf("decode webhook payload: %v", err)
		}
		if payload.Event != "notification_test" || payload.SiteName != "Test Admin" || payload.Receiver != "运维值班群" {
			t.Fatalf("unexpected webhook payload: %+v", payload)
		}
		w.WriteHeader(http.StatusAccepted)
		_, _ = w.Write([]byte(`{"ok":true}`))
	}))
	defer webhook.Close()

	client := server.Client()
	client.CheckRedirect = func(req *http.Request, via []*http.Request) error {
		return http.ErrUseLastResponse
	}
	sessionCookie := loginTestAdmin(t, client, server.URL)

	notificationResp, err := postFormWithCookie(t, client, server.URL+"/moyi-7k3x9-admin/settings/notifications", sessionCookie, url.Values{
		"notification_enabled":               {"1"},
		"notification_channel":               {"webhook"},
		"notification_receiver":              {"运维值班群"},
		"notification_webhook_url":           {webhook.URL},
		"notification_event_login_failures":  {"1"},
		"notification_event_ai_errors":       {"1"},
		"notification_event_storage_warning": {"1"},
	})
	if err != nil {
		t.Fatalf("POST notification settings failed: %v", err)
	}
	notificationResp.Body.Close()
	if notificationResp.StatusCode != http.StatusFound {
		t.Fatalf("expected notification settings redirect, got %d", notificationResp.StatusCode)
	}

	state, err := newInstallStore(stateFile).Load()
	if err != nil {
		t.Fatalf("load state after notification settings: %v", err)
	}
	notifications := state.Notifications.normalized()
	if !notifications.Enabled || notifications.Channel != "webhook" || notifications.Receiver != "运维值班群" || notifications.WebhookURL != webhook.URL {
		t.Fatalf("notification settings were not saved: %+v", notifications)
	}
	if !notifications.EventLoginFailures || !notifications.EventAIErrors || !notifications.EventStorageWarning {
		t.Fatalf("notification event flags were not saved: %+v", notifications)
	}
	changes, err := newInstallStore(stateFile).ListSettingChanges(5)
	if err != nil {
		t.Fatalf("list setting changes after notification save: %v", err)
	}
	if len(changes) == 0 || changes[0].Category != "notifications" || changes[0].Action != "保存通知设置" {
		t.Fatalf("expected notification setting change record, got %+v", changes)
	}
	if strings.Contains(changes[0].AfterJSON, webhook.URL) {
		t.Fatalf("notification setting change should mask webhook target, got %s", changes[0].AfterJSON)
	}

	testResp, err := postFormWithCookie(t, client, server.URL+"/moyi-7k3x9-admin/settings/notifications/test", sessionCookie, url.Values{})
	if err != nil {
		t.Fatalf("POST notification test failed: %v", err)
	}
	testResp.Body.Close()
	if testResp.StatusCode != http.StatusFound {
		t.Fatalf("expected notification test redirect, got %d", testResp.StatusCode)
	}
	if webhookCalls != 1 {
		t.Fatalf("expected webhook to be called once, got %d", webhookCalls)
	}
	deliveries, err := newInstallStore(stateFile).ListNotificationDeliveries(5)
	if err != nil {
		t.Fatalf("list notification deliveries after test send: %v", err)
	}
	if len(deliveries) != 1 || deliveries[0].Event != "notification_test" || deliveries[0].Status != "sent" || deliveries[0].StatusCode != http.StatusAccepted {
		t.Fatalf("expected notification delivery record, got %+v", deliveries)
	}

	pageReq, err := http.NewRequest(http.MethodGet, server.URL+"/moyi-7k3x9-admin/settings", nil)
	if err != nil {
		t.Fatalf("create settings request after notification save: %v", err)
	}
	pageReq.AddCookie(sessionCookie)
	pageResp, err := client.Do(pageReq)
	if err != nil {
		t.Fatalf("GET settings after notification save failed: %v", err)
	}
	defer pageResp.Body.Close()
	pageBody, err := io.ReadAll(pageResp.Body)
	if err != nil {
		t.Fatalf("read settings after notification save: %v", err)
	}
	pageText := string(pageBody)
	for _, expected := range []string{"通知设置", "运维值班群", webhook.URL, "Webhook", "发送测试通知"} {
		if !strings.Contains(pageText, expected) {
			t.Fatalf("expected settings page to contain %q", expected)
		}
	}

	notificationPageReq, err := http.NewRequest(http.MethodGet, server.URL+"/moyi-7k3x9-admin/notifications", nil)
	if err != nil {
		t.Fatalf("create notification page request: %v", err)
	}
	notificationPageReq.AddCookie(sessionCookie)
	notificationPageResp, err := client.Do(notificationPageReq)
	if err != nil {
		t.Fatalf("GET notification page failed: %v", err)
	}
	defer notificationPageResp.Body.Close()
	if notificationPageResp.StatusCode != http.StatusOK {
		t.Fatalf("expected notification page status 200, got %d", notificationPageResp.StatusCode)
	}
	notificationPageBody, err := io.ReadAll(notificationPageResp.Body)
	if err != nil {
		t.Fatalf("read notification page: %v", err)
	}
	notificationPageText := string(notificationPageBody)
	for _, expected := range []string{"通知事件", "Moyi Admin 测试通知", "notification_test", "成功 202", "通知设置"} {
		if !strings.Contains(notificationPageText, expected) {
			t.Fatalf("expected notification page to contain %q", expected)
		}
	}
}

func TestAdminSettingsUpdatesFeishuNotifications(t *testing.T) {
	stateFile := writeInstalledState(t, "Test Admin", "admin", "secret123")
	server := httptest.NewServer(NewRouter(RouterOptions{InstallStateFile: stateFile}))
	defer server.Close()
	feishuCalls := 0
	feishu := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		feishuCalls++
		if r.Method != http.MethodPost {
			t.Fatalf("expected feishu method POST, got %s", r.Method)
		}
		if contentType := r.Header.Get("Content-Type"); !strings.Contains(contentType, "application/json") {
			t.Fatalf("expected JSON feishu content type, got %q", contentType)
		}
		var payload struct {
			Timestamp string `json:"timestamp"`
			Sign      string `json:"sign"`
			MsgType   string `json:"msg_type"`
			Content   struct {
				Text string `json:"text"`
			} `json:"content"`
		}
		if err := json.NewDecoder(r.Body).Decode(&payload); err != nil {
			t.Fatalf("decode feishu payload: %v", err)
		}
		if payload.MsgType != "text" {
			t.Fatalf("expected feishu text payload, got %+v", payload)
		}
		if payload.Timestamp == "" || payload.Sign != feishuRobotSign(payload.Timestamp, "feishu-secret") {
			t.Fatalf("expected signed feishu payload, got %+v", payload)
		}
		for _, expected := range []string{"【Test Admin】Moyi Admin 测试通知", "事件：notification_test", "接收：飞书运维群", "后台通知通道测试成功"} {
			if !strings.Contains(payload.Content.Text, expected) {
				t.Fatalf("expected feishu payload text to contain %q, got %q", expected, payload.Content.Text)
			}
		}
		w.WriteHeader(http.StatusOK)
		_, _ = w.Write([]byte(`{"code":0,"msg":"success"}`))
	}))
	defer feishu.Close()

	client := server.Client()
	client.CheckRedirect = func(req *http.Request, via []*http.Request) error {
		return http.ErrUseLastResponse
	}
	sessionCookie := loginTestAdmin(t, client, server.URL)

	notificationResp, err := postFormWithCookie(t, client, server.URL+"/moyi-7k3x9-admin/settings/notifications", sessionCookie, url.Values{
		"notification_enabled":               {"1"},
		"notification_channel":               {"feishu"},
		"notification_receiver":              {"飞书运维群"},
		"notification_webhook_url":           {feishu.URL + "/open-apis/bot/v2/hook/test-token?debug=1"},
		"notification_feishu_secret":         {"feishu-secret"},
		"notification_event_login_failures":  {"1"},
		"notification_event_ai_errors":       {"1"},
		"notification_event_storage_warning": {"1"},
	})
	if err != nil {
		t.Fatalf("POST feishu notification settings failed: %v", err)
	}
	notificationResp.Body.Close()
	if notificationResp.StatusCode != http.StatusFound {
		t.Fatalf("expected feishu notification settings redirect, got %d", notificationResp.StatusCode)
	}

	state, err := newInstallStore(stateFile).Load()
	if err != nil {
		t.Fatalf("load state after feishu notification settings: %v", err)
	}
	notifications := state.Notifications.normalized()
	if !notifications.Enabled || notifications.Channel != "feishu" || notifications.Receiver != "飞书运维群" || notifications.FeishuSecret != "feishu-secret" {
		t.Fatalf("feishu notification settings were not saved: %+v", notifications)
	}
	changes, err := newInstallStore(stateFile).ListSettingChanges(5)
	if err != nil {
		t.Fatalf("list setting changes after feishu notification save: %v", err)
	}
	if len(changes) == 0 || strings.Contains(changes[0].AfterJSON, "feishu-secret") || strings.Contains(changes[0].AfterJSON, "test-token") {
		t.Fatalf("feishu notification setting change should mask target and secret, got %+v", changes)
	}

	testResp, err := postFormWithCookie(t, client, server.URL+"/moyi-7k3x9-admin/settings/notifications/test", sessionCookie, url.Values{})
	if err != nil {
		t.Fatalf("POST feishu notification test failed: %v", err)
	}
	testResp.Body.Close()
	if testResp.StatusCode != http.StatusFound {
		t.Fatalf("expected feishu notification test redirect, got %d", testResp.StatusCode)
	}
	if feishuCalls != 1 {
		t.Fatalf("expected feishu to be called once, got %d", feishuCalls)
	}
	deliveries, err := newInstallStore(stateFile).ListNotificationDeliveries(5)
	if err != nil {
		t.Fatalf("list feishu notification deliveries after test send: %v", err)
	}
	if len(deliveries) != 1 || deliveries[0].Event != "notification_test" || deliveries[0].Channel != "feishu" || deliveries[0].Status != "sent" || deliveries[0].StatusCode != http.StatusOK {
		t.Fatalf("expected feishu notification delivery record, got %+v", deliveries)
	}
	if strings.Contains(deliveries[0].Target, "debug=1") || strings.Contains(deliveries[0].Target, "test-token") {
		t.Fatalf("expected feishu target to be masked, got %q", deliveries[0].Target)
	}
}

func TestAdminBackgroundTasksCanEnqueueAndRun(t *testing.T) {
	stateFile := writeInstalledState(t, "Test Admin", "admin", "secret123")
	store := newInstallStore(stateFile)
	state, err := store.Load()
	if err != nil {
		t.Fatalf("load state: %v", err)
	}
	uploadDir := filepath.Join(t.TempDir(), "uploads")
	if err := os.MkdirAll(uploadDir, 0o755); err != nil {
		t.Fatalf("create upload dir: %v", err)
	}
	state.Storage = defaultStorageConfig()
	state.Storage.LocalPath = uploadDir
	if err := store.Save(state); err != nil {
		t.Fatalf("save state with storage dir: %v", err)
	}

	server := httptest.NewServer(NewRouter(RouterOptions{InstallStateFile: stateFile, DisableTaskWorker: true}))
	defer server.Close()
	client := server.Client()
	client.CheckRedirect = func(req *http.Request, via []*http.Request) error {
		return http.ErrUseLastResponse
	}
	sessionCookie := loginTestAdmin(t, client, server.URL)

	pageReq, err := http.NewRequest(http.MethodGet, server.URL+"/moyi-7k3x9-admin/tasks", nil)
	if err != nil {
		t.Fatalf("create tasks page request: %v", err)
	}
	pageReq.AddCookie(sessionCookie)
	pageResp, err := client.Do(pageReq)
	if err != nil {
		t.Fatalf("GET tasks page failed: %v", err)
	}
	defer pageResp.Body.Close()
	if pageResp.StatusCode != http.StatusOK {
		t.Fatalf("expected tasks page status 200, got %d", pageResp.StatusCode)
	}
	pageBody, err := io.ReadAll(pageResp.Body)
	if err != nil {
		t.Fatalf("read tasks page: %v", err)
	}
	for _, expected := range []string{"后台任务", "创建任务", "系统体检", "执行下一个任务", "自动执行", "保存自动执行设置"} {
		if !strings.Contains(string(pageBody), expected) {
			t.Fatalf("expected tasks page to contain %q", expected)
		}
	}

	settingsPageReq, err := http.NewRequest(http.MethodGet, server.URL+"/moyi-7k3x9-admin/settings", nil)
	if err != nil {
		t.Fatalf("create settings page request: %v", err)
	}
	settingsPageReq.AddCookie(sessionCookie)
	settingsPageResp, err := client.Do(settingsPageReq)
	if err != nil {
		t.Fatalf("GET settings page failed: %v", err)
	}
	defer settingsPageResp.Body.Close()
	if settingsPageResp.StatusCode != http.StatusOK {
		t.Fatalf("expected settings page status 200, got %d", settingsPageResp.StatusCode)
	}
	settingsPageBody, err := io.ReadAll(settingsPageResp.Body)
	if err != nil {
		t.Fatalf("read settings page: %v", err)
	}
	for _, expected := range []string{"后台自动队列", "开启自动队列", "保存自动队列设置"} {
		if !strings.Contains(string(settingsPageBody), expected) {
			t.Fatalf("expected settings page to contain %q", expected)
		}
	}

	settingsResp, err := postFormWithCookie(t, client, server.URL+"/moyi-7k3x9-admin/tasks/settings", sessionCookie, url.Values{
		"task_worker_enabled":           {"1"},
		"task_worker_interval_seconds":  {"5"},
		"task_worker_batch_size":        {"2"},
		"task_schedule_health_enabled":  {"1"},
		"task_schedule_health_minutes":  {"30"},
		"task_schedule_cleanup_enabled": {"1"},
		"task_schedule_cleanup_minutes": {"60"},
	})
	if err != nil {
		t.Fatalf("POST task settings failed: %v", err)
	}
	settingsResp.Body.Close()
	if settingsResp.StatusCode != http.StatusFound {
		t.Fatalf("expected task settings redirect, got %d", settingsResp.StatusCode)
	}
	state, err = store.Load()
	if err != nil {
		t.Fatalf("load state after task settings: %v", err)
	}
	if !state.TaskWorker.Enabled || state.TaskWorker.IntervalSeconds != 5 || state.TaskWorker.BatchSize != 2 || state.TaskWorker.ScheduleHealthMinutes != 30 || state.TaskWorker.ScheduleCleanupMinutes != 60 {
		t.Fatalf("task worker settings were not saved: %+v", state.TaskWorker)
	}
	changes, err := store.ListSettingChanges(5)
	if err != nil {
		t.Fatalf("list setting changes after task settings: %v", err)
	}
	if len(changes) == 0 || changes[0].Category != "task" || changes[0].Action != "保存任务设置" {
		t.Fatalf("expected task setting change record, got %+v", changes)
	}

	settingsTaskResp, err := postFormWithCookie(t, client, server.URL+"/moyi-7k3x9-admin/settings/tasks", sessionCookie, url.Values{
		"task_worker_enabled":           {"0"},
		"task_worker_interval_seconds":  {"15"},
		"task_worker_batch_size":        {"4"},
		"task_schedule_health_enabled":  {"0"},
		"task_schedule_health_minutes":  {"45"},
		"task_schedule_cleanup_enabled": {"1"},
		"task_schedule_cleanup_minutes": {"120"},
	})
	if err != nil {
		t.Fatalf("POST settings task worker failed: %v", err)
	}
	settingsTaskResp.Body.Close()
	if settingsTaskResp.StatusCode != http.StatusFound {
		t.Fatalf("expected settings task worker redirect, got %d", settingsTaskResp.StatusCode)
	}
	if location := settingsTaskResp.Header.Get("Location"); location != "/moyi-7k3x9-admin/settings?saved=task_worker" {
		t.Fatalf("expected redirect back to system settings, got %q", location)
	}
	state, err = store.Load()
	if err != nil {
		t.Fatalf("load state after settings task worker: %v", err)
	}
	if state.TaskWorker.Enabled || state.TaskWorker.IntervalSeconds != 15 || state.TaskWorker.BatchSize != 4 || state.TaskWorker.ScheduleHealthEnabled || !state.TaskWorker.ScheduleCleanupEnabled || state.TaskWorker.ScheduleCleanupMinutes != 120 {
		t.Fatalf("system settings task worker form was not saved: %+v", state.TaskWorker)
	}

	enqueueResp, err := postFormWithCookie(t, client, server.URL+"/moyi-7k3x9-admin/tasks/enqueue", sessionCookie, url.Values{
		"task_type": {"system_health_check"},
	})
	if err != nil {
		t.Fatalf("POST enqueue task failed: %v", err)
	}
	enqueueResp.Body.Close()
	if enqueueResp.StatusCode != http.StatusFound {
		t.Fatalf("expected enqueue redirect, got %d", enqueueResp.StatusCode)
	}
	tasks, err := store.ListBackgroundTasks(5)
	if err != nil {
		t.Fatalf("list tasks after enqueue: %v", err)
	}
	if len(tasks) != 1 || tasks[0].Type != "system_health_check" || tasks[0].Status != "pending" {
		t.Fatalf("expected pending health task, got %+v", tasks)
	}

	runResp, err := postFormWithCookie(t, client, server.URL+"/moyi-7k3x9-admin/tasks/run", sessionCookie, url.Values{})
	if err != nil {
		t.Fatalf("POST run task failed: %v", err)
	}
	runResp.Body.Close()
	if runResp.StatusCode != http.StatusFound {
		t.Fatalf("expected run redirect, got %d", runResp.StatusCode)
	}
	tasks, err = store.ListBackgroundTasks(5)
	if err != nil {
		t.Fatalf("list tasks after run: %v", err)
	}
	if len(tasks) != 1 || tasks[0].Status != "succeeded" || !strings.Contains(tasks[0].Result, "存储目录：可用") {
		t.Fatalf("expected succeeded health task with result, got %+v", tasks)
	}
	logs, err := store.ListBackgroundTaskLogs(10)
	if err != nil {
		t.Fatalf("list task logs after run: %v", err)
	}
	seenLogEvents := map[string]bool{}
	for _, log := range logs {
		seenLogEvents[log.Event] = true
	}
	for _, event := range []string{"queued", "started", "succeeded"} {
		if !seenLogEvents[event] {
			t.Fatalf("expected task logs to contain %q, got %+v", event, logs)
		}
	}

	cancelEnqueueResp, err := postFormWithCookie(t, client, server.URL+"/moyi-7k3x9-admin/tasks/enqueue", sessionCookie, url.Values{
		"task_type": {"storage_cleanup"},
	})
	if err != nil {
		t.Fatalf("POST enqueue cancel task failed: %v", err)
	}
	cancelEnqueueResp.Body.Close()
	tasks, err = store.ListBackgroundTasks(5)
	if err != nil {
		t.Fatalf("list tasks before cancel: %v", err)
	}
	cancelID := ""
	for _, task := range tasks {
		if task.Type == "storage_cleanup" && task.Status == "pending" {
			cancelID = task.ID
			break
		}
	}
	if cancelID == "" {
		t.Fatalf("expected pending storage cleanup task before cancel, got %+v", tasks)
	}
	cancelResp, err := postFormWithCookie(t, client, server.URL+"/moyi-7k3x9-admin/tasks/cancel", sessionCookie, url.Values{
		"task_id": {cancelID},
	})
	if err != nil {
		t.Fatalf("POST cancel task failed: %v", err)
	}
	cancelResp.Body.Close()
	if cancelResp.StatusCode != http.StatusFound {
		t.Fatalf("expected cancel redirect, got %d", cancelResp.StatusCode)
	}

	for _, taskType := range []string{"system_health_check", "storage_cleanup"} {
		resp, err := postFormWithCookie(t, client, server.URL+"/moyi-7k3x9-admin/tasks/enqueue", sessionCookie, url.Values{"task_type": {taskType}})
		if err != nil {
			t.Fatalf("POST enqueue batch task %s failed: %v", taskType, err)
		}
		resp.Body.Close()
	}
	runAllResp, err := postFormWithCookie(t, client, server.URL+"/moyi-7k3x9-admin/tasks/run-all", sessionCookie, url.Values{})
	if err != nil {
		t.Fatalf("POST run all tasks failed: %v", err)
	}
	runAllResp.Body.Close()
	if runAllResp.StatusCode != http.StatusFound {
		t.Fatalf("expected run all redirect, got %d", runAllResp.StatusCode)
	}
	tasks, err = store.ListBackgroundTasks(10)
	if err != nil {
		t.Fatalf("list tasks after run all: %v", err)
	}
	succeeded := 0
	canceled := 0
	for _, task := range tasks {
		switch task.Status {
		case "succeeded":
			succeeded++
		case "canceled":
			canceled++
		}
	}
	if succeeded < 3 || canceled != 1 {
		t.Fatalf("expected batch tasks to succeed and one canceled task, got %+v", tasks)
	}

	doneReq, err := http.NewRequest(http.MethodGet, server.URL+"/moyi-7k3x9-admin/tasks", nil)
	if err != nil {
		t.Fatalf("create done tasks page request: %v", err)
	}
	doneReq.AddCookie(sessionCookie)
	doneResp, err := client.Do(doneReq)
	if err != nil {
		t.Fatalf("GET tasks page after run failed: %v", err)
	}
	defer doneResp.Body.Close()
	doneBody, err := io.ReadAll(doneResp.Body)
	if err != nil {
		t.Fatalf("read done tasks page: %v", err)
	}
	doneText := string(doneBody)
	for _, expected := range []string{"已完成", "已取消", "任务日志", "批量执行就绪任务", "系统体检", "存储目录：可用"} {
		if !strings.Contains(doneText, expected) {
			t.Fatalf("expected tasks page after run to contain %q", expected)
		}
	}
}

func TestBackgroundTaskWorkerRunsReadyBatch(t *testing.T) {
	stateFile := writeInstalledState(t, "Worker Admin", "admin", "secret123")
	store := newInstallStore(stateFile)
	state, err := store.Load()
	if err != nil {
		t.Fatalf("load state: %v", err)
	}
	uploadDir := filepath.Join(t.TempDir(), "uploads")
	if err := os.MkdirAll(uploadDir, 0o755); err != nil {
		t.Fatalf("create upload dir: %v", err)
	}
	state.Storage = defaultStorageConfig()
	state.Storage.LocalPath = uploadDir
	state.TaskWorker = taskWorkerConfig{
		Enabled:                true,
		IntervalSeconds:        5,
		BatchSize:              2,
		ScheduleHealthEnabled:  false,
		ScheduleHealthMinutes:  30,
		ScheduleCleanupEnabled: false,
		ScheduleCleanupMinutes: 60,
	}
	if err := store.Save(state); err != nil {
		t.Fatalf("save worker state: %v", err)
	}
	for _, taskType := range []string{"system_health_check", "storage_cleanup"} {
		task, err := newBackgroundTaskRecord(taskType, "test")
		if err != nil {
			t.Fatalf("new task %s: %v", taskType, err)
		}
		if err := store.EnqueueBackgroundTask(task); err != nil {
			t.Fatalf("enqueue task %s: %v", taskType, err)
		}
	}
	admin := &adminServer{
		basePath:      "/moyi-7k3x9-admin",
		username:      "admin",
		password:      "secret123",
		sessionSecret: "test-secret",
		store:         store,
	}
	result, interval := admin.runBackgroundTaskWorkerOnce(context.Background(), time.Now().UTC())
	if result.Completed != 2 || result.Failed != 0 || interval != 5*time.Second {
		t.Fatalf("unexpected worker result %+v interval %s", result, interval)
	}
	tasks, err := store.ListBackgroundTasks(10)
	if err != nil {
		t.Fatalf("list worker tasks: %v", err)
	}
	for _, task := range tasks {
		if task.Status != "succeeded" {
			t.Fatalf("expected worker task to succeed, got %+v", tasks)
		}
	}
}

func TestAdminNotificationsSendRuntimeEvents(t *testing.T) {
	stateFile := writeInstalledState(t, "Test Admin", "admin", "secret123")
	var payloadsMu sync.Mutex
	payloads := make([]notificationPayload, 0, 3)
	webhook := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		var payload notificationPayload
		if err := json.NewDecoder(r.Body).Decode(&payload); err != nil {
			t.Fatalf("decode notification payload: %v", err)
		}
		payloadsMu.Lock()
		payloads = append(payloads, payload)
		payloadsMu.Unlock()
		w.WriteHeader(http.StatusOK)
		_, _ = w.Write([]byte(`{"ok":true}`))
	}))
	defer webhook.Close()
	aiServer := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path != "/v1/chat/completions" {
			t.Fatalf("unexpected AI path %q", r.URL.Path)
		}
		http.Error(w, "model unavailable", http.StatusBadGateway)
	}))
	defer aiServer.Close()

	store := newInstallStore(stateFile)
	state, err := store.Load()
	if err != nil {
		t.Fatalf("load state for notification runtime test: %v", err)
	}
	state.Security = securityConfig{SessionTTLHours: 12, LoginMaxAttempts: 1, LoginLockMinutes: 30}
	state.Storage = defaultStorageConfig()
	state.Storage.LocalPath = t.TempDir()
	state.AI = aiConfig{
		Provider:  "bailian",
		APIKey:    "sk-runtime-event",
		BaseURL:   aiServer.URL + "/v1",
		ChatModel: "qwen-plus",
	}
	state.Notifications = notificationConfig{
		Enabled:             true,
		Channel:             "webhook",
		Receiver:            "运维值班群",
		WebhookURL:          webhook.URL,
		EventLoginFailures:  true,
		EventAIErrors:       true,
		EventStorageWarning: true,
	}
	if err := store.Save(state); err != nil {
		t.Fatalf("save state for notification runtime test: %v", err)
	}

	server := httptest.NewServer(NewRouter(RouterOptions{InstallStateFile: stateFile}))
	defer server.Close()
	client := server.Client()
	client.CheckRedirect = func(req *http.Request, via []*http.Request) error {
		return http.ErrUseLastResponse
	}
	sessionCookie := loginTestAdmin(t, client, server.URL)

	failedResp, err := client.PostForm(server.URL+"/moyi-7k3x9-admin/login", url.Values{
		"username": {"admin"},
		"password": {"wrong-password"},
	})
	if err != nil {
		t.Fatalf("POST failed login failed: %v", err)
	}
	failedResp.Body.Close()
	if failedResp.StatusCode != http.StatusUnauthorized {
		t.Fatalf("expected failed login 401, got %d", failedResp.StatusCode)
	}
	lockedResp, err := client.PostForm(server.URL+"/moyi-7k3x9-admin/login", url.Values{
		"username": {"admin"},
		"password": {"secret123"},
	})
	if err != nil {
		t.Fatalf("POST locked login failed: %v", err)
	}
	lockedResp.Body.Close()
	if lockedResp.StatusCode != http.StatusTooManyRequests {
		t.Fatalf("expected locked login 429, got %d", lockedResp.StatusCode)
	}

	chatReq, err := http.NewRequest(http.MethodPost, server.URL+"/moyi-7k3x9-admin/ai/chat", bytes.NewBufferString(`{"message":"查看站点信息和 AI 配置"}`))
	if err != nil {
		t.Fatalf("create AI chat request: %v", err)
	}
	chatReq.Header.Set("Content-Type", "application/json")
	chatReq.AddCookie(sessionCookie)
	chatResp, err := client.Do(chatReq)
	if err != nil {
		t.Fatalf("POST AI chat for notification failed: %v", err)
	}
	defer chatResp.Body.Close()
	if chatResp.StatusCode != http.StatusOK {
		t.Fatalf("expected AI chat fallback status 200, got %d", chatResp.StatusCode)
	}
	var chatParsed agentChatResponse
	if err := json.NewDecoder(chatResp.Body).Decode(&chatParsed); err != nil {
		t.Fatalf("decode AI chat fallback response: %v", err)
	}
	if !chatParsed.OK || chatParsed.ModelUsed {
		t.Fatalf("expected local fallback response, got %+v", chatParsed)
	}

	uploadResp, err := postMultipartWithCookie(t, client, server.URL+"/moyi-7k3x9-admin/files/upload", sessionCookie, "blocked.exe", []byte("blocked"))
	if err != nil {
		t.Fatalf("POST blocked upload failed: %v", err)
	}
	uploadResp.Body.Close()
	if uploadResp.StatusCode != http.StatusFound {
		t.Fatalf("expected blocked upload redirect, got %d", uploadResp.StatusCode)
	}

	payloadsMu.Lock()
	defer payloadsMu.Unlock()
	seen := map[string]bool{}
	for _, payload := range payloads {
		seen[payload.Event] = true
		if payload.SiteName != "Test Admin" || payload.Receiver != "运维值班群" {
			t.Fatalf("unexpected notification payload: %+v", payload)
		}
	}
	for _, event := range []string{"login_lockout", "ai_model_error", "storage_warning"} {
		if !seen[event] {
			t.Fatalf("expected notification event %q, got %+v", event, payloads)
		}
	}
	deliveries, err := store.ListNotificationDeliveries(10)
	if err != nil {
		t.Fatalf("list runtime notification deliveries: %v", err)
	}
	seenDelivery := map[string]bool{}
	for _, delivery := range deliveries {
		if delivery.Status == "sent" {
			seenDelivery[delivery.Event] = true
		}
	}
	for _, event := range []string{"login_lockout", "ai_model_error", "storage_warning"} {
		if !seenDelivery[event] {
			t.Fatalf("expected delivery record for %q, got %+v", event, deliveries)
		}
	}
}

func TestAdminSettingsUpdatesAIAndSecurity(t *testing.T) {
	stateFile := writeInstalledState(t, "Test Admin", "admin", "secret123")
	server := httptest.NewServer(NewRouter(RouterOptions{InstallStateFile: stateFile}))
	defer server.Close()
	aiServer := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path != "/v1/chat/completions" {
			t.Fatalf("unexpected AI check path %q", r.URL.Path)
		}
		if got := r.Header.Get("Authorization"); got != "Bearer sk-settings-bailian" {
			t.Fatalf("unexpected AI authorization %q", got)
		}
		w.Header().Set("Content-Type", "application/json")
		_, _ = w.Write([]byte(`{"choices":[{"message":{"content":"ok"}}]}`))
	}))
	defer aiServer.Close()

	client := server.Client()
	client.CheckRedirect = func(req *http.Request, via []*http.Request) error {
		return http.ErrUseLastResponse
	}
	sessionCookie := loginTestAdmin(t, client, server.URL)

	aiResp, err := postFormWithCookie(t, client, server.URL+"/moyi-7k3x9-admin/settings/ai", sessionCookie, url.Values{
		"ai_provider":   {"bailian"},
		"ai_api_key":    {"sk-settings-bailian"},
		"ai_base_url":   {aiServer.URL + "/v1"},
		"ai_chat_model": {"qwen-max"},
	})
	if err != nil {
		t.Fatalf("POST AI settings failed: %v", err)
	}
	aiResp.Body.Close()
	if aiResp.StatusCode != http.StatusFound {
		t.Fatalf("expected AI settings redirect, got %d", aiResp.StatusCode)
	}

	state, err := newInstallStore(stateFile).Load()
	if err != nil {
		t.Fatalf("load state after AI settings: %v", err)
	}
	if state.AI.Provider != "bailian" || state.AI.APIKey != "sk-settings-bailian" || state.AI.ChatModel != "qwen-max" {
		t.Fatalf("AI settings were not saved: %+v", state.AI)
	}
	changes, err := newInstallStore(stateFile).ListSettingChanges(5)
	if err != nil {
		t.Fatalf("list setting changes after AI save: %v", err)
	}
	if len(changes) == 0 || changes[0].Category != "ai" || changes[0].Action != "保存 AI 设置" {
		t.Fatalf("expected AI setting change record, got %+v", changes)
	}
	if strings.Contains(changes[0].AfterJSON, "sk-settings-bailian") {
		t.Fatalf("AI setting change should not store raw api key, got %s", changes[0].AfterJSON)
	}

	securityResp, err := postFormWithCookie(t, client, server.URL+"/moyi-7k3x9-admin/settings/security", sessionCookie, url.Values{
		"current_password":          {"secret123"},
		"new_password":              {"newsecret123"},
		"new_password_confirmation": {"newsecret123"},
	})
	if err != nil {
		t.Fatalf("POST security settings failed: %v", err)
	}
	securityResp.Body.Close()
	if securityResp.StatusCode != http.StatusFound {
		t.Fatalf("expected security settings redirect, got %d", securityResp.StatusCode)
	}

	oldLoginResp, err := client.PostForm(server.URL+"/moyi-7k3x9-admin/login", url.Values{
		"username": {"admin"},
		"password": {"secret123"},
	})
	if err != nil {
		t.Fatalf("POST old password login failed: %v", err)
	}
	oldLoginResp.Body.Close()
	if oldLoginResp.StatusCode != http.StatusUnauthorized {
		t.Fatalf("expected old password to fail, got %d", oldLoginResp.StatusCode)
	}
	newLoginResp, err := client.PostForm(server.URL+"/moyi-7k3x9-admin/login", url.Values{
		"username": {"admin"},
		"password": {"newsecret123"},
	})
	if err != nil {
		t.Fatalf("POST new password login failed: %v", err)
	}
	newLoginResp.Body.Close()
	if newLoginResp.StatusCode != http.StatusFound {
		t.Fatalf("expected new password login redirect, got %d", newLoginResp.StatusCode)
	}
	changes, err = newInstallStore(stateFile).ListSettingChanges(5)
	if err != nil {
		t.Fatalf("list setting changes after password save: %v", err)
	}
	if len(changes) == 0 || changes[0].Category != "security" || changes[0].Action != "修改管理员密码" {
		t.Fatalf("expected security setting change record, got %+v", changes)
	}
}

func TestAdminSecurityPolicyControlsSessionAndLockout(t *testing.T) {
	stateFile := writeInstalledState(t, "Test Admin", "admin", "secret123")
	server := httptest.NewServer(NewRouter(RouterOptions{InstallStateFile: stateFile}))
	defer server.Close()

	client := server.Client()
	client.CheckRedirect = func(req *http.Request, via []*http.Request) error {
		return http.ErrUseLastResponse
	}
	sessionCookie := loginTestAdmin(t, client, server.URL)

	policyResp, err := postFormWithCookie(t, client, server.URL+"/moyi-7k3x9-admin/settings/security", sessionCookie, url.Values{
		"security_action":    {"policy"},
		"session_ttl_hours":  {"2"},
		"login_max_attempts": {"2"},
		"login_lock_minutes": {"30"},
	})
	if err != nil {
		t.Fatalf("POST security policy failed: %v", err)
	}
	policyResp.Body.Close()
	if policyResp.StatusCode != http.StatusFound {
		t.Fatalf("expected security policy redirect, got %d", policyResp.StatusCode)
	}

	state, err := newInstallStore(stateFile).Load()
	if err != nil {
		t.Fatalf("load state after security policy: %v", err)
	}
	if state.Security.SessionTTLHours != 2 || state.Security.LoginMaxAttempts != 2 || state.Security.LoginLockMinutes != 30 {
		t.Fatalf("security policy was not saved: %+v", state.Security)
	}

	loginResp, err := client.PostForm(server.URL+"/moyi-7k3x9-admin/login", url.Values{
		"username": {"admin"},
		"password": {"secret123"},
	})
	if err != nil {
		t.Fatalf("POST login after security policy failed: %v", err)
	}
	loginResp.Body.Close()
	if loginResp.StatusCode != http.StatusFound {
		t.Fatalf("expected login redirect after security policy, got %d", loginResp.StatusCode)
	}
	var loginCookie *http.Cookie
	for _, cookie := range loginResp.Cookies() {
		if cookie.Name == adminSessionCookie {
			loginCookie = cookie
			break
		}
	}
	if loginCookie == nil {
		t.Fatal("expected session cookie after login")
	}
	if loginCookie.MaxAge != int((2 * time.Hour).Seconds()) {
		t.Fatalf("expected session max age 7200, got %d", loginCookie.MaxAge)
	}

	for attempt := 1; attempt <= 2; attempt++ {
		failedResp, err := client.PostForm(server.URL+"/moyi-7k3x9-admin/login", url.Values{
			"username": {"admin"},
			"password": {"wrong-password"},
		})
		if err != nil {
			t.Fatalf("POST failed login attempt %d failed: %v", attempt, err)
		}
		failedResp.Body.Close()
		if failedResp.StatusCode != http.StatusUnauthorized {
			t.Fatalf("expected failed login attempt %d status 401, got %d", attempt, failedResp.StatusCode)
		}
	}

	lockedResp, err := client.PostForm(server.URL+"/moyi-7k3x9-admin/login", url.Values{
		"username": {"admin"},
		"password": {"secret123"},
	})
	if err != nil {
		t.Fatalf("POST locked login failed: %v", err)
	}
	lockedResp.Body.Close()
	if lockedResp.StatusCode != http.StatusTooManyRequests {
		t.Fatalf("expected locked login status 429, got %d", lockedResp.StatusCode)
	}
}

func TestAdminFileManagerUploadsDownloadsAndDeletes(t *testing.T) {
	stateFile := writeInstalledState(t, "Test Admin", "admin", "secret123")
	uploadDir := filepath.Join(t.TempDir(), "uploads")
	store := newInstallStore(stateFile)
	state, err := store.Load()
	if err != nil {
		t.Fatalf("load state: %v", err)
	}
	state.Storage = defaultStorageConfig()
	state.Storage.LocalPath = uploadDir
	if err := store.Save(state); err != nil {
		t.Fatalf("save state with storage: %v", err)
	}

	server := httptest.NewServer(NewRouter(RouterOptions{InstallStateFile: stateFile}))
	defer server.Close()

	client := server.Client()
	client.CheckRedirect = func(req *http.Request, via []*http.Request) error {
		return http.ErrUseLastResponse
	}
	sessionCookie := loginTestAdmin(t, client, server.URL)

	uploadResp, err := postMultipartWithCookie(t, client, server.URL+"/moyi-7k3x9-admin/files/upload", sessionCookie, "note.txt", []byte("hello file manager"))
	if err != nil {
		t.Fatalf("POST file upload failed: %v", err)
	}
	defer uploadResp.Body.Close()
	if uploadResp.StatusCode != http.StatusFound {
		t.Fatalf("expected upload redirect, got %d", uploadResp.StatusCode)
	}

	relativePath := findUploadedFileForTest(t, uploadDir)
	pageReq, err := http.NewRequest(http.MethodGet, server.URL+"/moyi-7k3x9-admin/files", nil)
	if err != nil {
		t.Fatalf("create files request: %v", err)
	}
	pageReq.AddCookie(sessionCookie)
	pageResp, err := client.Do(pageReq)
	if err != nil {
		t.Fatalf("GET files page failed: %v", err)
	}
	defer pageResp.Body.Close()
	body, err := io.ReadAll(pageResp.Body)
	if err != nil {
		t.Fatalf("read files page: %v", err)
	}
	bodyText := string(body)
	if !strings.Contains(bodyText, "文件管理") || !strings.Contains(bodyText, "note.txt") || !strings.Contains(bodyText, "预览") || !strings.Contains(bodyText, "下载") {
		t.Fatalf("expected uploaded file in file manager page, got %q", bodyText)
	}

	downloadReq, err := http.NewRequest(http.MethodGet, server.URL+"/moyi-7k3x9-admin/files/download/"+escapeStorageRelativePath(relativePath), nil)
	if err != nil {
		t.Fatalf("create download request: %v", err)
	}
	downloadReq.AddCookie(sessionCookie)
	downloadResp, err := client.Do(downloadReq)
	if err != nil {
		t.Fatalf("download uploaded file failed: %v", err)
	}
	defer downloadResp.Body.Close()
	downloadBody, err := io.ReadAll(downloadResp.Body)
	if err != nil {
		t.Fatalf("read downloaded file: %v", err)
	}
	if string(downloadBody) != "hello file manager" {
		t.Fatalf("unexpected downloaded content %q", downloadBody)
	}

	deleteResp, err := postFormWithCookie(t, client, server.URL+"/moyi-7k3x9-admin/files/delete", sessionCookie, url.Values{"path": {relativePath}})
	if err != nil {
		t.Fatalf("POST file delete failed: %v", err)
	}
	defer deleteResp.Body.Close()
	if deleteResp.StatusCode != http.StatusFound {
		t.Fatalf("expected delete redirect, got %d", deleteResp.StatusCode)
	}
	if _, err := os.Stat(filepath.Join(uploadDir, filepath.FromSlash(relativePath))); !errors.Is(err, os.ErrNotExist) {
		t.Fatalf("expected uploaded file to be deleted, err=%v", err)
	}
}

func TestAdminAuditRecordsLoginSettingsFilesAndAI(t *testing.T) {
	stateFile := writeInstalledState(t, "Test Admin", "admin", "secret123")
	uploadDir := filepath.Join(filepath.Dir(stateFile), "uploads")
	store := newInstallStore(stateFile)
	state, err := store.Load()
	if err != nil {
		t.Fatalf("load state: %v", err)
	}
	state.Storage = defaultStorageConfig()
	state.Storage.LocalPath = uploadDir
	if err := store.Save(state); err != nil {
		t.Fatalf("save state with storage: %v", err)
	}

	server := httptest.NewServer(NewRouter(RouterOptions{InstallStateFile: stateFile}))
	defer server.Close()

	client := server.Client()
	client.CheckRedirect = func(req *http.Request, via []*http.Request) error {
		return http.ErrUseLastResponse
	}
	sessionCookie := loginTestAdmin(t, client, server.URL)

	settingsResp, err := postFormWithCookie(t, client, server.URL+"/moyi-7k3x9-admin/settings/system", sessionCookie, url.Values{
		"site_name": {"Audit Admin"},
		"timezone":  {"Asia/Shanghai"},
		"locale":    {"zh-CN"},
	})
	if err != nil {
		t.Fatalf("POST settings for audit failed: %v", err)
	}
	settingsResp.Body.Close()
	if settingsResp.StatusCode != http.StatusFound {
		t.Fatalf("expected settings redirect, got %d", settingsResp.StatusCode)
	}

	uploadResp, err := postMultipartWithCookie(t, client, server.URL+"/moyi-7k3x9-admin/files/upload", sessionCookie, "audit.txt", []byte("audit log"))
	if err != nil {
		t.Fatalf("POST upload for audit failed: %v", err)
	}
	uploadResp.Body.Close()
	if uploadResp.StatusCode != http.StatusFound {
		t.Fatalf("expected upload redirect, got %d", uploadResp.StatusCode)
	}

	body := bytes.NewBufferString(`{"message":"查看审计日志"}`)
	aiReq, err := http.NewRequest(http.MethodPost, server.URL+"/moyi-7k3x9-admin/ai/chat", body)
	if err != nil {
		t.Fatalf("create AI audit request: %v", err)
	}
	aiReq.Header.Set("Content-Type", "application/json")
	aiReq.AddCookie(sessionCookie)
	aiResp, err := client.Do(aiReq)
	if err != nil {
		t.Fatalf("POST AI audit chat failed: %v", err)
	}
	aiResp.Body.Close()
	if aiResp.StatusCode != http.StatusOK {
		t.Fatalf("expected AI audit status 200, got %d", aiResp.StatusCode)
	}

	auditReq, err := http.NewRequest(http.MethodGet, server.URL+"/moyi-7k3x9-admin/audit", nil)
	if err != nil {
		t.Fatalf("create audit request: %v", err)
	}
	auditReq.AddCookie(sessionCookie)
	auditResp, err := client.Do(auditReq)
	if err != nil {
		t.Fatalf("GET audit page failed: %v", err)
	}
	defer auditResp.Body.Close()
	if auditResp.StatusCode != http.StatusOK {
		t.Fatalf("expected audit status 200, got %d", auditResp.StatusCode)
	}
	auditBody, err := io.ReadAll(auditResp.Body)
	if err != nil {
		t.Fatalf("read audit response: %v", err)
	}
	auditText := string(auditBody)
	for _, expected := range []string{"登录成功", "保存基础信息", "上传文件", "智能体对话", "审计事件", "127.0.0.1"} {
		if !strings.Contains(auditText, expected) {
			t.Fatalf("expected audit page to contain %q", expected)
		}
	}
	for _, expected := range []string{"事件分类", "关键词", "导出 CSV", "/audit/export"} {
		if !strings.Contains(auditText, expected) {
			t.Fatalf("expected audit filters to contain %q", expected)
		}
	}

	filterReq, err := http.NewRequest(http.MethodGet, server.URL+"/moyi-7k3x9-admin/audit?category=file", nil)
	if err != nil {
		t.Fatalf("create filtered audit request: %v", err)
	}
	filterReq.AddCookie(sessionCookie)
	filterResp, err := client.Do(filterReq)
	if err != nil {
		t.Fatalf("GET filtered audit page failed: %v", err)
	}
	defer filterResp.Body.Close()
	filterBody, err := io.ReadAll(filterResp.Body)
	if err != nil {
		t.Fatalf("read filtered audit response: %v", err)
	}
	filterText := string(filterBody)
	if !strings.Contains(filterText, "上传文件") {
		t.Fatalf("expected filtered audit page to contain file event, got %q", filterText)
	}
	if strings.Contains(filterText, "智能体对话") {
		t.Fatalf("expected filtered audit page to hide AI event, got %q", filterText)
	}

	exportReq, err := http.NewRequest(http.MethodGet, server.URL+"/moyi-7k3x9-admin/audit/export?category=ai", nil)
	if err != nil {
		t.Fatalf("create audit export request: %v", err)
	}
	exportReq.AddCookie(sessionCookie)
	exportResp, err := client.Do(exportReq)
	if err != nil {
		t.Fatalf("GET audit export failed: %v", err)
	}
	defer exportResp.Body.Close()
	if exportResp.StatusCode != http.StatusOK {
		t.Fatalf("expected audit export status 200, got %d", exportResp.StatusCode)
	}
	if contentType := exportResp.Header.Get("Content-Type"); !strings.Contains(contentType, "text/csv") {
		t.Fatalf("expected audit export csv content type, got %q", contentType)
	}
	exportBody, err := io.ReadAll(exportResp.Body)
	if err != nil {
		t.Fatalf("read audit export response: %v", err)
	}
	exportText := string(exportBody)
	if !strings.Contains(exportText, "时间,分类,操作") || !strings.Contains(exportText, "智能体对话") {
		t.Fatalf("expected audit export CSV with AI event, got %q", exportText)
	}
	if strings.Contains(exportText, "上传文件") {
		t.Fatalf("expected AI audit export to exclude file events, got %q", exportText)
	}

	events, err := newInstallStore(stateFile).ListAuditEvents(20)
	if err != nil {
		t.Fatalf("read audit events: %v", err)
	}
	seenAI := false
	seenFile := false
	for _, event := range events {
		seenAI = seenAI || event.Category == "ai"
		seenFile = seenFile || event.Category == "file"
	}
	if !seenAI || !seenFile {
		t.Fatalf("expected audit table to include ai and file events, got %+v", events)
	}
}

func TestAdminAgentChatListsGuardedTables(t *testing.T) {
	stateFile := writeInstalledState(t, "Test Admin", "admin", "secret123")
	server := httptest.NewServer(NewRouter(RouterOptions{InstallStateFile: stateFile}))
	defer server.Close()

	client := server.Client()
	client.CheckRedirect = func(req *http.Request, via []*http.Request) error {
		return http.ErrUseLastResponse
	}
	sessionCookie := loginTestAdmin(t, client, server.URL)

	body := bytes.NewBufferString(`{"message":"列出当前可查询的数据表"}`)
	req, err := http.NewRequest(http.MethodPost, server.URL+"/moyi-7k3x9-admin/ai/chat", body)
	if err != nil {
		t.Fatalf("create agent chat request: %v", err)
	}
	req.Header.Set("Content-Type", "application/json")
	req.AddCookie(sessionCookie)
	resp, err := client.Do(req)
	if err != nil {
		t.Fatalf("POST AI chat failed: %v", err)
	}
	defer resp.Body.Close()
	if resp.StatusCode != http.StatusOK {
		t.Fatalf("expected status 200, got %d", resp.StatusCode)
	}

	var parsed agentChatResponse
	if err := json.NewDecoder(resp.Body).Decode(&parsed); err != nil {
		t.Fatalf("decode AI chat response: %v", err)
	}
	if !parsed.OK || len(parsed.ToolResults) != 1 {
		t.Fatalf("expected successful tool response, got %+v", parsed)
	}
	if parsed.SessionID == "" {
		t.Fatal("expected agent session id")
	}
	if parsed.Run.Mode != string(agentIntentTableCatalog) || len(parsed.Run.Plan) == 0 || len(parsed.Run.Trace) == 0 {
		t.Fatalf("expected agent run plan and trace, got %+v", parsed.Run)
	}
	if parsed.ToolResults[0].Name != "list_tables" {
		t.Fatalf("expected list_tables tool, got %q", parsed.ToolResults[0].Name)
	}
	foundMenus := false
	foundMetadataType := false
	foundBackgroundTasks := false
	for _, row := range parsed.ToolResults[0].Rows {
		if row["name"] == "admin_menus" {
			foundMenus = true
		}
		if row["name"] == "admin_users" && row["type"] == "metadata_table" {
			foundMetadataType = true
		}
		if row["name"] == "background_tasks" && row["type"] == "metadata_table" {
			foundBackgroundTasks = true
		}
	}
	if !foundMenus || !foundMetadataType || !foundBackgroundTasks {
		t.Fatalf("expected real metadata tables in list_tables result, got %+v", parsed.ToolResults[0].Rows)
	}
	if !strings.Contains(parsed.Reply, "install_state") {
		t.Fatalf("expected reply to mention install_state, got %q", parsed.Reply)
	}
	runs, err := newInstallStore(stateFile).ListAgentRuns(5)
	if err != nil {
		t.Fatalf("list persisted agent runs: %v", err)
	}
	if len(runs) != 1 || runs[0].ID != parsed.Run.ID || runs[0].SessionID != parsed.SessionID || runs[0].ToolCount != 1 {
		t.Fatalf("expected persisted agent run, got %+v", runs)
	}
	tools, err := newInstallStore(stateFile).ListAgentToolResults(5)
	if err != nil {
		t.Fatalf("list persisted agent tool results: %v", err)
	}
	if len(tools) != 1 || tools[0].Name != "list_tables" || !tools[0].OK {
		t.Fatalf("expected persisted agent tool result, got %+v", tools)
	}
}

func TestAgentWeChatBindingChannelExchangesCodeAndChats(t *testing.T) {
	stateFile := writeInstalledState(t, "Test Admin", "admin", "secret123")
	var qrRequests int
	var statusRequests int
	weixinProvider := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		switch r.URL.Path {
		case "/ilink/bot/get_bot_qrcode":
			qrRequests++
			if r.URL.Query().Get("bot_type") != "3" {
				t.Fatalf("unexpected bot_type: %s", r.URL.RawQuery)
			}
			_, _ = w.Write([]byte(`{"qrcode":"qr-token-1","qrcode_img_content":"https://weixin.qq.com/x/openclaw-login"}`))
		case "/ilink/bot/get_qrcode_status":
			statusRequests++
			if r.URL.Query().Get("qrcode") != "qr-token-1" {
				t.Fatalf("unexpected qrcode status query: %s", r.URL.RawQuery)
			}
			if r.Header.Get("iLink-App-ClientVersion") == "" {
				t.Fatal("expected iLink client version header")
			}
			_, _ = w.Write([]byte(`{"status":"confirmed","bot_token":"provider-token-1","ilink_bot_id":"bot-1@im.bot","baseurl":"https://ilinkai.weixin.qq.com","ilink_user_id":"wx-user-1@im.wechat"}`))
		default:
			http.NotFound(w, r)
		}
	}))
	defer weixinProvider.Close()

	server := httptest.NewServer(NewRouter(RouterOptions{InstallStateFile: stateFile, DisableTaskWorker: true}))
	defer server.Close()

	client := server.Client()
	client.CheckRedirect = func(req *http.Request, via []*http.Request) error {
		return http.ErrUseLastResponse
	}
	sessionCookie := loginTestAdmin(t, client, server.URL)

	aiPageReq, err := http.NewRequest(http.MethodGet, server.URL+"/moyi-7k3x9-admin/ai", nil)
	if err != nil {
		t.Fatalf("create AI page request: %v", err)
	}
	aiPageReq.AddCookie(sessionCookie)
	aiPageResp, err := client.Do(aiPageReq)
	if err != nil {
		t.Fatalf("GET AI page failed: %v", err)
	}
	aiPageBody, err := io.ReadAll(aiPageResp.Body)
	aiPageResp.Body.Close()
	if err != nil {
		t.Fatalf("read AI page: %v", err)
	}
	if strings.Contains(string(aiPageBody), "OpenClaw Weixin 通道") {
		t.Fatal("expected AI page to keep wechat channel management separated")
	}

	pageReq, err := http.NewRequest(http.MethodGet, server.URL+"/moyi-7k3x9-admin/wechat-agent", nil)
	if err != nil {
		t.Fatalf("create wechat agent page request: %v", err)
	}
	pageReq.AddCookie(sessionCookie)
	pageResp, err := client.Do(pageReq)
	if err != nil {
		t.Fatalf("GET wechat agent page failed: %v", err)
	}
	pageBody, err := io.ReadAll(pageResp.Body)
	pageResp.Body.Close()
	if err != nil {
		t.Fatalf("read wechat agent page: %v", err)
	}
	pageHTML := string(pageBody)
	if !strings.Contains(pageHTML, "OpenClaw Weixin 通道") || !strings.Contains(pageHTML, "/wechat-agent/channels") {
		t.Fatal("expected wechat agent page to expose openclaw-weixin channel")
	}
	if strings.Contains(pageHTML, "接入接口") || strings.Contains(pageHTML, "/api/agent/channels/openclaw-weixin/session") {
		t.Fatal("expected wechat agent page not to expose internal channel APIs")
	}
	if strings.Contains(pageHTML, "公众号") || strings.Contains(pageHTML, "腾讯二维码") {
		t.Fatalf("expected wechat agent page not to expose official account flow, got %s", pageHTML)
	}

	enableResp, err := postFormWithCookie(t, client, server.URL+"/moyi-7k3x9-admin/wechat-agent/channels", sessionCookie, url.Values{
		"wechat_channel_enabled": {"1"},
		"wechat_channel_name":    {"OpenClaw Weixin 通道"},
		"wechat_base_url":        {weixinProvider.URL},
		"wechat_bot_type":        {"3"},
		"wechat_data_scope":      {"all"},
		"wechat_channel_action":  {"regenerate"},
	})
	if err != nil {
		t.Fatalf("POST wechat channel settings failed: %v", err)
	}
	enableResp.Body.Close()
	if enableResp.StatusCode != http.StatusFound {
		t.Fatalf("expected wechat channel redirect, got %d", enableResp.StatusCode)
	}

	store := newInstallStore(stateFile)
	state, err := store.Load()
	if err != nil {
		t.Fatalf("load state after wechat channel settings: %v", err)
	}
	wechat := state.AgentChannels.normalized().WeChat
	token := wechat.Token
	if !wechat.Enabled || token == "" || wechat.LoginQRCode != "qr-token-1" || wechat.QRPayload != "https://weixin.qq.com/x/openclaw-login" || !strings.HasPrefix(wechat.QRImageURL, "data:image/png;base64,") {
		t.Fatalf("expected pending openclaw weixin login QR, got %+v", wechat)
	}
	if !wechat.BindExpiresAt.After(time.Now().UTC()) {
		t.Fatalf("expected openclaw login QR expiration in future, got %s", wechat.BindExpiresAt)
	}
	if qrRequests != 1 {
		t.Fatalf("expected one QR request, got %d", qrRequests)
	}

	generatedPageReq, err := http.NewRequest(http.MethodGet, server.URL+"/moyi-7k3x9-admin/wechat-agent", nil)
	if err != nil {
		t.Fatalf("create generated wechat agent page request: %v", err)
	}
	generatedPageReq.AddCookie(sessionCookie)
	generatedPageResp, err := client.Do(generatedPageReq)
	if err != nil {
		t.Fatalf("GET generated wechat agent page failed: %v", err)
	}
	generatedBody, err := io.ReadAll(generatedPageResp.Body)
	generatedPageResp.Body.Close()
	if err != nil {
		t.Fatalf("read generated wechat agent page: %v", err)
	}
	generatedHTML := string(generatedBody)
	if !strings.Contains(generatedHTML, "src=\"data:image/png;base64,") || !strings.Contains(generatedHTML, "二维码内容") || !strings.Contains(generatedHTML, "https://weixin.qq.com/x/openclaw-login") || strings.Contains(generatedHTML, "公众号") {
		t.Fatalf("expected admin page to render openclaw QR image, got %s", generatedHTML)
	}

	sessionReq, err := http.NewRequest(http.MethodPost, server.URL+"/api/agent/channels/openclaw-weixin/session", bytes.NewBufferString(`{"status":"scaned","session_key":"adapter-session","qr_url":"https://weixin.qq.com/x/adapter-login","message":"adapter qr ready"}`))
	if err != nil {
		t.Fatalf("create session update request: %v", err)
	}
	sessionReq.Header.Set("Content-Type", "application/json")
	sessionReq.Header.Set("Authorization", "Bearer "+token)
	sessionResp, err := client.Do(sessionReq)
	if err != nil {
		t.Fatalf("POST openclaw session update failed: %v", err)
	}
	sessionResp.Body.Close()
	if sessionResp.StatusCode != http.StatusOK {
		t.Fatalf("expected session update 200, got %d", sessionResp.StatusCode)
	}

	pollResp, err := postFormWithCookie(t, client, server.URL+"/moyi-7k3x9-admin/wechat-agent/channels", sessionCookie, url.Values{
		"wechat_channel_enabled": {"1"},
		"wechat_channel_name":    {"OpenClaw Weixin 通道"},
		"wechat_base_url":        {weixinProvider.URL},
		"wechat_bot_type":        {"3"},
		"wechat_data_scope":      {"all"},
		"wechat_channel_action":  {"poll"},
	})
	if err != nil {
		t.Fatalf("POST wechat channel poll failed: %v", err)
	}
	pollResp.Body.Close()
	if pollResp.StatusCode != http.StatusFound {
		t.Fatalf("expected wechat channel poll redirect, got %d", pollResp.StatusCode)
	}

	state, err = store.Load()
	if err != nil {
		t.Fatalf("load state after openclaw login poll: %v", err)
	}
	wechat = state.AgentChannels.normalized().WeChat
	if !strings.HasPrefix(token, agentWeChatTokenPrefix) {
		t.Fatalf("expected wechat agent token, got %q", token)
	}
	if statusRequests != 1 {
		t.Fatalf("expected one login status request, got %d", statusRequests)
	}
	if wechat.ProviderToken != "provider-token-1" || wechat.AccountID != "bot-1@im.bot" || wechat.OpenClawUserID != "wx-user-1@im.wechat" || wechat.QRPayload != "" {
		t.Fatalf("expected openclaw login to bind provider account, got %+v", wechat)
	}

	meReq, err := http.NewRequest(http.MethodGet, server.URL+"/api/agent/me", nil)
	if err != nil {
		t.Fatalf("create agent me request: %v", err)
	}
	meReq.Header.Set("Authorization", "Bearer "+token)
	meResp, err := client.Do(meReq)
	if err != nil {
		t.Fatalf("GET agent me failed: %v", err)
	}
	meResp.Body.Close()
	if meResp.StatusCode != http.StatusOK {
		t.Fatalf("expected agent me 200, got %d", meResp.StatusCode)
	}

	chatReq, err := http.NewRequest(http.MethodPost, server.URL+"/api/agent/messages", bytes.NewBufferString(`{"Body":"我们后台有几个管理员账号？","From":"wx-user-1@im.wechat","To":"bot-1@im.bot","AccountId":"bot-1@im.bot","MessageSid":"msg-1"}`))
	if err != nil {
		t.Fatalf("create wechat message request: %v", err)
	}
	chatReq.Header.Set("Content-Type", "application/json")
	chatReq.Header.Set("Authorization", "Bearer "+token)
	chatResp, err := client.Do(chatReq)
	if err != nil {
		t.Fatalf("POST wechat agent message failed: %v", err)
	}
	defer chatResp.Body.Close()
	if chatResp.StatusCode != http.StatusOK {
		body, _ := io.ReadAll(chatResp.Body)
		t.Fatalf("expected wechat message 200, got %d: %s", chatResp.StatusCode, body)
	}
	var chatParsed agentWeChatMessageResponse
	if err := json.NewDecoder(chatResp.Body).Decode(&chatParsed); err != nil {
		t.Fatalf("decode wechat message response: %v", err)
	}
	if !chatParsed.OK || chatParsed.SessionID == "" || !strings.Contains(chatParsed.Reply, "admin") {
		t.Fatalf("expected successful wechat agent response, got %+v", chatParsed)
	}
	wechatMessages, err := store.ListAgentWeChatMessages(5)
	if err != nil {
		t.Fatalf("list wechat agent messages: %v", err)
	}
	if len(wechatMessages) == 0 || wechatMessages[0].MessageID != "msg-1" || !strings.Contains(wechatMessages[0].InboundText, "管理员账号") || !strings.Contains(wechatMessages[0].ReplyText, "admin") {
		t.Fatalf("expected archived wechat chat record, got %+v", wechatMessages)
	}
	historyPageReq, err := http.NewRequest(http.MethodGet, server.URL+"/moyi-7k3x9-admin/wechat-agent/messages", nil)
	if err != nil {
		t.Fatalf("create wechat history page request: %v", err)
	}
	historyPageReq.AddCookie(sessionCookie)
	historyPageResp, err := client.Do(historyPageReq)
	if err != nil {
		t.Fatalf("GET wechat history page failed: %v", err)
	}
	historyBody, err := io.ReadAll(historyPageResp.Body)
	historyPageResp.Body.Close()
	if err != nil {
		t.Fatalf("read wechat history page: %v", err)
	}
	historyHTML := string(historyBody)
	if !strings.Contains(historyHTML, "微信 Agent 聊天记录") || !strings.Contains(historyHTML, "msg-1") || !strings.Contains(historyHTML, "管理员账号") {
		t.Fatalf("expected wechat message page to render chat history, got %s", historyHTML)
	}
	runs, err := store.ListAgentRuns(5)
	if err != nil {
		t.Fatalf("list wechat agent runs: %v", err)
	}
	if len(runs) == 0 || !strings.HasPrefix(runs[0].Actor, "wechat:") {
		t.Fatalf("expected wechat actor persisted, got %+v", runs)
	}
}

func TestAgentWeChatMessagesPagePaginatesNewestFirst(t *testing.T) {
	stateFile := writeInstalledState(t, "Test Admin", "admin", "secret123")
	store := newInstallStore(stateFile)
	state, err := store.Load()
	if err != nil {
		t.Fatalf("load state: %v", err)
	}
	state.AgentChannels.WeChats = []agentWeChatChannelConfig{
		{Key: "wechat_a", Enabled: true, Status: "connected", DisplayName: "财务微信"},
		{Key: "wechat_b", Enabled: true, Status: "connected", DisplayName: "运营微信"},
	}
	if err := store.Save(state); err != nil {
		t.Fatalf("save state with wechat channels: %v", err)
	}
	baseTime := time.Date(2026, 5, 17, 9, 0, 0, 0, time.UTC)
	for i := 1; i <= 22; i++ {
		channelKey := "wechat_a"
		channelName := "财务微信"
		if i == 2 {
			channelKey = "wechat_b"
			channelName = "运营微信"
		}
		receivedAt := baseTime.Add(time.Duration(i) * time.Minute)
		if err := store.UpsertAgentWeChatMessage(agentWeChatMessageRecord{
			ArchiveKey:  fmt.Sprintf("%s:msg:page-msg-%02d", channelKey, i),
			ChannelKey:  channelKey,
			ChannelName: channelName,
			Provider:    agentWeChatProviderID,
			MessageID:   fmt.Sprintf("page-msg-%02d", i),
			SessionID:   fmt.Sprintf("session-%02d", i),
			RunID:       fmt.Sprintf("run-%02d", i),
			FromUserID:  fmt.Sprintf("wx-user-%02d@im.wechat", i),
			ToUserID:    "bot@im.bot",
			InboundText: fmt.Sprintf("用户消息 %02d", i),
			ReplyText:   fmt.Sprintf("AI 回复 %02d", i),
			Status:      "ok",
			DurationMS:  int64(100 + i),
			ReceivedAt:  receivedAt,
			RepliedAt:   receivedAt.Add(time.Second),
			CreatedAt:   receivedAt,
		}); err != nil {
			t.Fatalf("upsert wechat message %d: %v", i, err)
		}
	}

	server := httptest.NewServer(NewRouter(RouterOptions{InstallStateFile: stateFile, DisableTaskWorker: true}))
	defer server.Close()
	client := server.Client()
	client.CheckRedirect = func(req *http.Request, via []*http.Request) error {
		return http.ErrUseLastResponse
	}
	sessionCookie := loginTestAdmin(t, client, server.URL)

	firstReq, err := http.NewRequest(http.MethodGet, server.URL+"/moyi-7k3x9-admin/wechat-agent/messages", nil)
	if err != nil {
		t.Fatalf("create first page request: %v", err)
	}
	firstReq.AddCookie(sessionCookie)
	firstResp, err := client.Do(firstReq)
	if err != nil {
		t.Fatalf("GET first message page failed: %v", err)
	}
	firstBody, err := io.ReadAll(firstResp.Body)
	firstResp.Body.Close()
	if err != nil {
		t.Fatalf("read first message page: %v", err)
	}
	firstHTML := string(firstBody)
	if !strings.Contains(firstHTML, "微信 Agent 聊天记录") || !strings.Contains(firstHTML, "用户消息 22") || !strings.Contains(firstHTML, "用户消息 03") || !strings.Contains(firstHTML, "下一页") {
		t.Fatalf("expected first page to show newest 20 records, got %s", firstHTML)
	}
	if strings.Contains(firstHTML, "用户消息 02") {
		t.Fatalf("expected first page to hide older records, got %s", firstHTML)
	}

	secondReq, err := http.NewRequest(http.MethodGet, server.URL+"/moyi-7k3x9-admin/wechat-agent/messages?page=2", nil)
	if err != nil {
		t.Fatalf("create second page request: %v", err)
	}
	secondReq.AddCookie(sessionCookie)
	secondResp, err := client.Do(secondReq)
	if err != nil {
		t.Fatalf("GET second message page failed: %v", err)
	}
	secondBody, err := io.ReadAll(secondResp.Body)
	secondResp.Body.Close()
	if err != nil {
		t.Fatalf("read second message page: %v", err)
	}
	secondHTML := string(secondBody)
	if !strings.Contains(secondHTML, "用户消息 02") || !strings.Contains(secondHTML, "用户消息 01") || strings.Contains(secondHTML, "用户消息 03") {
		t.Fatalf("expected second page to show oldest overflow records, got %s", secondHTML)
	}

	filterReq, err := http.NewRequest(http.MethodGet, server.URL+"/moyi-7k3x9-admin/wechat-agent/messages?channel=wechat_b", nil)
	if err != nil {
		t.Fatalf("create filtered message page request: %v", err)
	}
	filterReq.AddCookie(sessionCookie)
	filterResp, err := client.Do(filterReq)
	if err != nil {
		t.Fatalf("GET filtered message page failed: %v", err)
	}
	filterBody, err := io.ReadAll(filterResp.Body)
	filterResp.Body.Close()
	if err != nil {
		t.Fatalf("read filtered message page: %v", err)
	}
	filterHTML := string(filterBody)
	if !strings.Contains(filterHTML, "运营微信") || !strings.Contains(filterHTML, "用户消息 02") || !strings.Contains(filterHTML, "/wechat-agent/messages/export?channel=wechat_b") || strings.Contains(filterHTML, "用户消息 22") {
		t.Fatalf("expected channel filter to limit records, got %s", filterHTML)
	}

	exportReq, err := http.NewRequest(http.MethodGet, server.URL+"/moyi-7k3x9-admin/wechat-agent/messages/export?channel=wechat_b", nil)
	if err != nil {
		t.Fatalf("create filtered message export request: %v", err)
	}
	exportReq.AddCookie(sessionCookie)
	exportResp, err := client.Do(exportReq)
	if err != nil {
		t.Fatalf("GET filtered message export failed: %v", err)
	}
	exportBody, err := io.ReadAll(exportResp.Body)
	exportResp.Body.Close()
	if err != nil {
		t.Fatalf("read filtered message export: %v", err)
	}
	exportText := string(exportBody)
	if exportResp.StatusCode != http.StatusOK || !strings.Contains(exportText, "用户消息 02") || strings.Contains(exportText, "用户消息 22") {
		t.Fatalf("expected filtered export to contain only channel records, status %d body %s", exportResp.StatusCode, exportText)
	}
}

func TestAgentWeChatChannelManagementAddsAndDisablesMultiple(t *testing.T) {
	stateFile := writeInstalledState(t, "Test Admin", "admin", "secret123")
	store := newInstallStore(stateFile)
	server := httptest.NewServer(NewRouter(RouterOptions{InstallStateFile: stateFile, DisableTaskWorker: true}))
	defer server.Close()

	client := server.Client()
	client.CheckRedirect = func(req *http.Request, via []*http.Request) error {
		return http.ErrUseLastResponse
	}
	sessionCookie := loginTestAdmin(t, client, server.URL)
	channelForms := []struct {
		name   string
		tables string
	}{
		{name: "微信通道 A", tables: "admin_users, admin_roles"},
		{name: "微信通道 B", tables: "data_sources"},
	}
	for _, form := range channelForms {
		resp, err := postFormWithCookie(t, client, server.URL+"/moyi-7k3x9-admin/wechat-agent/channels", sessionCookie, url.Values{
			"wechat_channel_enabled": {"1"},
			"wechat_channel_name":    {form.name},
			"wechat_base_url":        {"https://ilinkai.weixin.qq.com"},
			"wechat_bot_type":        {"3"},
			"wechat_allowed_tables":  {form.tables},
			"wechat_channel_action":  {"add"},
		})
		if err != nil {
			t.Fatalf("POST add wechat channel failed: %v", err)
		}
		resp.Body.Close()
		if resp.StatusCode != http.StatusFound {
			t.Fatalf("expected add redirect, got %d", resp.StatusCode)
		}
	}

	state, err := store.Load()
	if err != nil {
		t.Fatalf("load state after adding wechat channels: %v", err)
	}
	channels := state.AgentChannels.normalized()
	if len(channels.WeChats) != 2 {
		t.Fatalf("expected two wechat channels, got %+v", channels.WeChats)
	}
	if channels.WeChats[0].Key == "" || channels.WeChats[1].Key == "" || channels.WeChats[0].Key == channels.WeChats[1].Key {
		t.Fatalf("expected stable unique wechat channel keys, got %+v", channels.WeChats)
	}
	channelA := agentWeChatChannelConfig{}
	channelB := agentWeChatChannelConfig{}
	for _, channel := range channels.WeChats {
		switch channel.DisplayName {
		case "微信通道 A":
			channelA = channel
		case "微信通道 B":
			channelB = channel
		}
	}
	if got := strings.Join(channelA.AllowedTables, ","); got != "admin_users,admin_roles" {
		t.Fatalf("expected channel A allowed tables to persist, got %q", got)
	}
	if got := strings.Join(channelB.AllowedTables, ","); got != "data_sources" {
		t.Fatalf("expected channel B allowed tables to persist, got %q", got)
	}

	editResp, err := postFormWithCookie(t, client, server.URL+"/moyi-7k3x9-admin/wechat-agent/channels", sessionCookie, url.Values{
		"wechat_channel_key":     {channelB.Key},
		"wechat_channel_enabled": {"1"},
		"wechat_channel_name":    {channelB.DisplayName},
		"wechat_base_url":        {channelB.BaseURL},
		"wechat_bot_type":        {channelB.BotType},
		"wechat_allowed_tables":  {"orders, admin_users\nanalytics.events, orders"},
		"wechat_channel_action":  {"save"},
	})
	if err != nil {
		t.Fatalf("POST edit wechat channel failed: %v", err)
	}
	editResp.Body.Close()
	if editResp.StatusCode != http.StatusFound {
		t.Fatalf("expected edit redirect, got %d", editResp.StatusCode)
	}
	state, err = store.Load()
	if err != nil {
		t.Fatalf("load state after editing wechat channel: %v", err)
	}
	channels = state.AgentChannels.normalized()
	channelB, _, ok := findAgentWeChatChannelByKey(channels, channelB.Key)
	if !ok {
		t.Fatalf("expected edited wechat channel B to remain present")
	}
	if got := strings.Join(channelB.AllowedTables, ","); got != "orders,admin_users,analytics.events" {
		t.Fatalf("expected edited custom allowed tables to persist, got %q", got)
	}

	pageReq, err := http.NewRequest(http.MethodGet, server.URL+"/moyi-7k3x9-admin/wechat-agent", nil)
	if err != nil {
		t.Fatalf("create wechat agent page request: %v", err)
	}
	pageReq.AddCookie(sessionCookie)
	pageResp, err := client.Do(pageReq)
	if err != nil {
		t.Fatalf("GET wechat agent page failed: %v", err)
	}
	pageBody, err := io.ReadAll(pageResp.Body)
	pageResp.Body.Close()
	if err != nil {
		t.Fatalf("read wechat agent page: %v", err)
	}
	pageHTML := string(pageBody)
	for _, expected := range []string{"微信通道 A", "微信通道 B", "禁用通道", "2 个通道", "点击展开配置", "数据权限", "指定数据表", "常用授权", "admin_users, admin_roles", "orders, admin_users, analytics.events"} {
		if !strings.Contains(pageHTML, expected) {
			t.Fatalf("expected wechat agent page to contain %q", expected)
		}
	}
	for _, unexpected := range []string{"等待二维码", "二维码内容", "留空表示全部已登记数据表"} {
		if strings.Contains(pageHTML, unexpected) {
			t.Fatalf("expected wechat agent page not to reserve empty QR content %q", unexpected)
		}
	}

	disableResp, err := postFormWithCookie(t, client, server.URL+"/moyi-7k3x9-admin/wechat-agent/channels", sessionCookie, url.Values{
		"wechat_channel_key":     {channelA.Key},
		"wechat_channel_enabled": {"1"},
		"wechat_channel_name":    {channelA.DisplayName},
		"wechat_base_url":        {channelA.BaseURL},
		"wechat_bot_type":        {channelA.BotType},
		"wechat_allowed_tables":  {strings.Join(channelA.AllowedTables, ",")},
		"wechat_channel_action":  {"disable"},
	})
	if err != nil {
		t.Fatalf("POST disable wechat channel failed: %v", err)
	}
	disableResp.Body.Close()
	if disableResp.StatusCode != http.StatusFound {
		t.Fatalf("expected disable redirect, got %d", disableResp.StatusCode)
	}
	state, err = store.Load()
	if err != nil {
		t.Fatalf("load state after disabling wechat channel: %v", err)
	}
	channels = state.AgentChannels.normalized()
	disabledA, _, ok := findAgentWeChatChannelByKey(channels, channelA.Key)
	if !ok || disabledA.DisplayName != "微信通道 A" || disabledA.Enabled || disabledA.Status != "disabled" {
		t.Fatalf("expected first wechat channel to be disabled and retained, got %+v", disabledA)
	}
	if got := strings.Join(disabledA.AllowedTables, ","); got != "admin_users,admin_roles" {
		t.Fatalf("expected disabled channel to retain allowed tables, got %q", got)
	}
	if disabledA.Token != "" || disabledA.ProviderToken != "" || disabledA.AccountID != "" {
		t.Fatalf("expected disabled channel runtime credentials to be cleared, got %+v", disabledA)
	}
	enabledB, _, ok := findAgentWeChatChannelByKey(channels, channelB.Key)
	if !ok || enabledB.DisplayName != "微信通道 B" || !enabledB.Enabled {
		t.Fatalf("expected second wechat channel to remain enabled, got %+v", enabledB)
	}
}

func TestAgentAllowedTablesPreserveCustomTables(t *testing.T) {
	got := normalizeAgentAllowedTables([]string{"custom_orders, ADMIN_USERS\nanalytics.events;custom_orders"})
	want := []string{"custom_orders", "admin_users", "analytics.events"}
	if strings.Join(got, ",") != strings.Join(want, ",") {
		t.Fatalf("expected custom tables to be preserved, got %+v", got)
	}
	if value := agentAllowedTablesString(nil); value != "" {
		t.Fatalf("expected empty allowed table input to stay empty, got %q", value)
	}
	provider := tableToolProvider{}.withAllowedTables([]string{"custom_orders"})
	if !provider.isTableAuthorized("custom_orders") {
		t.Fatalf("expected custom table to be authorized")
	}
	if provider.isTableAuthorized("admin_users") {
		t.Fatalf("expected non-listed table to remain unauthorized")
	}
	if summary := provider.authorizedTableSummary(); !strings.Contains(summary, "custom_orders") {
		t.Fatalf("expected custom table in authorization summary, got %q", summary)
	}
}

func TestAgentWeChatChannelTableAuthorizationRestrictsChatTools(t *testing.T) {
	stateFile := writeInstalledState(t, "Test Admin", "admin", "secret123")
	store := newInstallStore(stateFile)
	state, err := store.Load()
	if err != nil {
		t.Fatalf("load installed state: %v", err)
	}
	token := newAgentWeChatToken()
	state.AgentChannels.WeChats = []agentWeChatChannelConfig{
		{
			Key:           "wechat_limited",
			Enabled:       true,
			Status:        "bound",
			BaseURL:       agentWeChatDefaultBaseURL,
			BotType:       "3",
			ProviderToken: "provider-token-limited",
			AccountID:     "bot-limited@im.bot",
			Token:         token,
			DisplayName:   "受限微信通道",
			AgentHint:     agentWeChatDefaultHint,
			AllowedTables: []string{"data_sources"},
		},
	}
	if err := store.Save(state); err != nil {
		t.Fatalf("save limited wechat channel: %v", err)
	}

	server := httptest.NewServer(NewRouter(RouterOptions{InstallStateFile: stateFile, DisableTaskWorker: true}))
	defer server.Close()
	req, err := http.NewRequest(http.MethodPost, server.URL+"/api/agent/messages", bytes.NewBufferString(`{"Body":"我们后台有几个管理员账号？","From":"wx-user-limited@im.wechat","To":"bot-limited@im.bot","AccountId":"bot-limited@im.bot","MessageSid":"msg-limited"}`))
	if err != nil {
		t.Fatalf("create limited wechat message request: %v", err)
	}
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("Authorization", "Bearer "+token)
	resp, err := server.Client().Do(req)
	if err != nil {
		t.Fatalf("POST limited wechat agent message failed: %v", err)
	}
	defer resp.Body.Close()
	if resp.StatusCode != http.StatusOK {
		body, _ := io.ReadAll(resp.Body)
		t.Fatalf("expected limited wechat message 200, got %d: %s", resp.StatusCode, body)
	}
	var parsed agentWeChatMessageResponse
	if err := json.NewDecoder(resp.Body).Decode(&parsed); err != nil {
		t.Fatalf("decode limited wechat response: %v", err)
	}
	if len(parsed.ToolResults) == 0 {
		t.Fatalf("expected tool guardrail results, got %+v", parsed)
	}
	for _, result := range parsed.ToolResults {
		if result.OK || !strings.Contains(result.Error, "未授权") {
			t.Fatalf("expected unauthorized table result, got %+v", result)
		}
	}
	if !strings.Contains(parsed.Reply, "未授权") {
		t.Fatalf("expected reply to mention authorization boundary, got %q", parsed.Reply)
	}
	if strings.Contains(parsed.Reply, "默认拥有所有") || strings.Contains(parsed.Reply, "全表只读") {
		t.Fatalf("expected limited channel reply not to claim full table access, got %q", parsed.Reply)
	}
	state, err = store.Load()
	if err != nil {
		t.Fatalf("reload state after limited wechat message: %v", err)
	}
	wechat, _, ok := findAgentWeChatChannelByKey(state.AgentChannels.normalized(), "wechat_limited")
	if !ok {
		t.Fatal("expected limited wechat channel to remain present")
	}
	if got := strings.Join(wechat.AllowedTables, ","); got != "data_sources" {
		t.Fatalf("direct message must not rewrite allowed tables, got %q", got)
	}
	if wechat.DataScope != agentTableAccessTables {
		t.Fatalf("direct message must not rewrite data scope, got %q", wechat.DataScope)
	}
}

func TestAgentWeChatEmptyAuthorizationDoesNotDefaultToAllTables(t *testing.T) {
	stateFile := writeInstalledState(t, "Test Admin", "admin", "secret123")
	store := newInstallStore(stateFile)
	state, err := store.Load()
	if err != nil {
		t.Fatalf("load installed state: %v", err)
	}
	token := newAgentWeChatToken()
	state.AgentChannels.WeChats = []agentWeChatChannelConfig{
		{
			Key:           "wechat_no_data",
			Enabled:       true,
			Status:        "bound",
			BaseURL:       agentWeChatDefaultBaseURL,
			BotType:       "3",
			ProviderToken: "provider-token-no-data",
			AccountID:     "bot-no-data@im.bot",
			Token:         token,
			DisplayName:   "未授权数据通道",
			AgentHint:     agentWeChatDefaultHint,
		},
	}
	if err := store.Save(state); err != nil {
		t.Fatalf("save no-data wechat channel: %v", err)
	}

	server := httptest.NewServer(NewRouter(RouterOptions{InstallStateFile: stateFile, DisableTaskWorker: true}))
	defer server.Close()
	req, err := http.NewRequest(http.MethodPost, server.URL+"/api/agent/messages", bytes.NewBufferString(`{"Body":"我们后台有几个管理员账号？","From":"wx-user-no-data@im.wechat","To":"bot-no-data@im.bot","AccountId":"bot-no-data@im.bot","MessageSid":"msg-no-data"}`))
	if err != nil {
		t.Fatalf("create no-data wechat message request: %v", err)
	}
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("Authorization", "Bearer "+token)
	resp, err := server.Client().Do(req)
	if err != nil {
		t.Fatalf("POST no-data wechat agent message failed: %v", err)
	}
	defer resp.Body.Close()
	if resp.StatusCode != http.StatusOK {
		body, _ := io.ReadAll(resp.Body)
		t.Fatalf("expected no-data wechat message 200, got %d: %s", resp.StatusCode, body)
	}
	var parsed agentWeChatMessageResponse
	if err := json.NewDecoder(resp.Body).Decode(&parsed); err != nil {
		t.Fatalf("decode no-data wechat response: %v", err)
	}
	if len(parsed.ToolResults) != 1 || parsed.ToolResults[0].OK || !strings.Contains(parsed.ToolResults[0].Error, "未授权") {
		t.Fatalf("expected empty authorization to deny data access, got %+v", parsed.ToolResults)
	}
	if strings.Contains(parsed.Reply, "默认拥有所有") || strings.Contains(parsed.Reply, "全表只读") {
		t.Fatalf("expected no-data reply not to claim full table access, got %q", parsed.Reply)
	}
}

func TestAgentScopeQuestionReportsAuthorizationWithoutModelOrUserTables(t *testing.T) {
	stateFile := writeInstalledState(t, "Test Admin", "admin", "secret123")
	store := newInstallStore(stateFile)
	state, err := store.Load()
	if err != nil {
		t.Fatalf("load installed state: %v", err)
	}
	modelCalls := 0
	aiServer := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		modelCalls++
		http.Error(w, "model should not be called", http.StatusInternalServerError)
	}))
	defer aiServer.Close()
	state.AI = aiConfig{
		Provider:  "bailian",
		APIKey:    "sk-agent-scope",
		BaseURL:   aiServer.URL + "/v1",
		ChatModel: "qwen-plus",
	}
	if err := store.Save(state); err != nil {
		t.Fatalf("save state with AI config: %v", err)
	}

	admin := newAdminServer("/moyi-7k3x9-admin", "admin", "secret123", "", stateFile, "test")
	response := admin.runAgentChat(context.Background(), state, agentChatRequest{
		Message:         "查询我有哪些权限",
		TableAccessMode: agentTableAccessTables,
		AllowedTables:   []string{"data_sources"},
	})
	if !response.OK || response.ModelUsed || modelCalls != 0 {
		t.Fatalf("expected local deterministic scope response, got response=%+v model_calls=%d", response, modelCalls)
	}
	if response.Run.Mode != string(agentIntentAccessScope) {
		t.Fatalf("expected access scope mode, got %q", response.Run.Mode)
	}
	if response.Run.Metadata["table_authorization"] != "data_sources" {
		t.Fatalf("expected scope metadata to remain data_sources, got %+v", response.Run.Metadata)
	}
	if len(response.ToolResults) != 1 || response.ToolResults[0].Name != "access_scope" {
		t.Fatalf("expected access_scope result only, got %+v", response.ToolResults)
	}
	if !strings.Contains(response.Reply, "data_sources") || !strings.Contains(response.Reply, "指定数据表") {
		t.Fatalf("expected reply to report current authorized table, got %q", response.Reply)
	}
	if strings.Contains(response.Reply, "当前后台管理员账号") {
		t.Fatalf("permission scope query should not answer as admin user query, got %q", response.Reply)
	}
}

func TestAgentWeChatWorkerPreservesAuthorizationAndBlocksUnauthorizedExport(t *testing.T) {
	stateFile := writeInstalledState(t, "Test Admin", "admin", "secret123")
	store := newInstallStore(stateFile)

	var textReplies []string
	var fileReplies int
	provider := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		switch r.URL.Path {
		case "/ilink/bot/getupdates":
			_, _ = w.Write([]byte(`{"ret":0,"get_updates_buf":"buf-limited-2","msgs":[{"message_id":"provider-msg-limited-export","from_user_id":"wx-user-limited@im.wechat","to_user_id":"bot-limited@im.bot","session_id":"session-limited-export","message_type":1,"message_state":2,"context_token":"ctx-limited","item_list":[{"type":1,"text_item":{"text":"把管理员账号导出 csv 发给我"}}]}]}`))
		case "/ilink/bot/getconfig":
			_, _ = w.Write([]byte(`{"ret":0,"typing_ticket":"typing-ticket-limited"}`))
		case "/ilink/bot/sendtyping":
			_, _ = w.Write([]byte(`{"ret":0}`))
		case "/ilink/bot/sendmessage":
			var payload agentWeChatSendMessageRequest
			if err := json.NewDecoder(r.Body).Decode(&payload); err != nil {
				t.Fatalf("decode sendmessage request: %v", err)
			}
			if len(payload.Message.ItemList) != 1 {
				t.Fatalf("expected one message item, got %+v", payload.Message.ItemList)
			}
			item := payload.Message.ItemList[0]
			if item.Type == 4 {
				fileReplies++
			}
			if item.TextItem != nil {
				textReplies = append(textReplies, item.TextItem.Text)
			}
			_, _ = w.Write([]byte(`{"ret":0}`))
		default:
			http.NotFound(w, r)
		}
	}))
	defer provider.Close()

	state, err := store.Load()
	if err != nil {
		t.Fatalf("load installed state: %v", err)
	}
	state.AgentChannels.WeChats = []agentWeChatChannelConfig{
		{
			Key:           "wechat_limited_export",
			Enabled:       true,
			Status:        "bound",
			BaseURL:       provider.URL,
			BotType:       "3",
			ProviderToken: "provider-token-limited-export",
			AccountID:     "bot-limited@im.bot",
			SyncBuffer:    "buf-limited-1",
			Token:         newAgentWeChatToken(),
			DisplayName:   "受限导出通道",
			AgentHint:     agentWeChatDefaultHint,
			DataScope:     agentTableAccessTables,
			AllowedTables: []string{"data_sources"},
		},
	}
	state.AgentChannels.WeChat = agentWeChatChannelConfig{}
	if err := store.Save(state); err != nil {
		t.Fatalf("save limited export channel: %v", err)
	}

	admin := newAdminServer("/moyi-7k3x9-admin", "admin", "secret123", "", stateFile, "test")
	ran, _ := admin.runAgentWeChatChannelPollOnce(context.Background())
	if !ran {
		t.Fatal("expected wechat worker to process limited export message")
	}
	if fileReplies != 0 {
		t.Fatalf("expected unauthorized export not to send file, sent %d file replies", fileReplies)
	}
	if len(textReplies) != 1 || !strings.Contains(textReplies[0], "未授权") {
		t.Fatalf("expected text reply to mention unauthorized table, got %+v", textReplies)
	}

	state, err = store.Load()
	if err != nil {
		t.Fatalf("reload state after limited export worker: %v", err)
	}
	channels := state.AgentChannels.normalized()
	wechat, _, ok := findAgentWeChatChannelByKey(channels, "wechat_limited_export")
	if !ok {
		t.Fatalf("expected limited channel to remain present")
	}
	if wechat.DataScope != agentTableAccessTables {
		t.Fatalf("expected worker to preserve data scope, got %q", wechat.DataScope)
	}
	if got := strings.Join(wechat.AllowedTables, ","); got != "data_sources" {
		t.Fatalf("expected worker to preserve allowed tables, got %q", got)
	}
	if wechat.SyncBuffer != "buf-limited-2" {
		t.Fatalf("expected runtime sync buffer to update, got %q", wechat.SyncBuffer)
	}
}

func TestAgentWeChatListeningHeartbeatDoesNotRewriteAuthorization(t *testing.T) {
	stateFile := writeInstalledState(t, "Test Admin", "admin", "secret123")
	store := newInstallStore(stateFile)
	provider := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		switch r.URL.Path {
		case "/ilink/bot/getupdates":
			_, _ = w.Write([]byte(`{"ret":0,"get_updates_buf":"buf-heartbeat-2","msgs":[]}`))
		default:
			http.NotFound(w, r)
		}
	}))
	defer provider.Close()

	state, err := store.Load()
	if err != nil {
		t.Fatalf("load installed state: %v", err)
	}
	state.AgentChannels.WeChats = []agentWeChatChannelConfig{
		{
			Key:           "wechat_heartbeat_limited",
			Enabled:       true,
			Status:        "bound",
			BaseURL:       provider.URL,
			BotType:       "3",
			ProviderToken: "provider-token-heartbeat",
			AccountID:     "bot-heartbeat@im.bot",
			SyncBuffer:    "buf-heartbeat-1",
			Token:         newAgentWeChatToken(),
			DisplayName:   "心跳受限通道",
			AgentHint:     agentWeChatDefaultHint,
			DataScope:     agentTableAccessTables,
			AllowedTables: []string{"data_sources"},
		},
	}
	state.AgentChannels.WeChat = agentWeChatChannelConfig{}
	if err := store.Save(state); err != nil {
		t.Fatalf("save heartbeat channel: %v", err)
	}

	admin := newAdminServer("/moyi-7k3x9-admin", "admin", "secret123", "", stateFile, "test")
	ran, _ := admin.runAgentWeChatChannelPollOnce(context.Background())
	if !ran {
		t.Fatal("expected heartbeat poll to run")
	}
	state, err = store.Load()
	if err != nil {
		t.Fatalf("reload state after heartbeat: %v", err)
	}
	wechat, _, ok := findAgentWeChatChannelByKey(state.AgentChannels.normalized(), "wechat_heartbeat_limited")
	if !ok {
		t.Fatalf("expected heartbeat channel to remain present")
	}
	if got := strings.Join(wechat.AllowedTables, ","); got != "data_sources" {
		t.Fatalf("heartbeat must not rewrite allowed tables, got %q", got)
	}
	if wechat.DataScope != agentTableAccessTables {
		t.Fatalf("heartbeat must not rewrite data scope, got %q", wechat.DataScope)
	}
	if wechat.SyncBuffer != "buf-heartbeat-2" {
		t.Fatalf("expected heartbeat to update sync buffer, got %q", wechat.SyncBuffer)
	}
}

func TestAgentWeChatSessionUpdatePreservesAuthorization(t *testing.T) {
	stateFile := writeInstalledState(t, "Test Admin", "admin", "secret123")
	store := newInstallStore(stateFile)
	token := newAgentWeChatToken()
	state, err := store.Load()
	if err != nil {
		t.Fatalf("load installed state: %v", err)
	}
	state.AgentChannels.WeChats = []agentWeChatChannelConfig{
		{
			Key:           "wechat_session_limited",
			Enabled:       true,
			Status:        "waiting",
			BaseURL:       agentWeChatDefaultBaseURL,
			BotType:       "3",
			Token:         token,
			DisplayName:   "会话受限通道",
			AgentHint:     agentWeChatDefaultHint,
			DataScope:     agentTableAccessTables,
			AllowedTables: []string{"data_sources"},
		},
	}
	state.AgentChannels.WeChat = agentWeChatChannelConfig{}
	if err := store.Save(state); err != nil {
		t.Fatalf("save session channel: %v", err)
	}

	server := httptest.NewServer(NewRouter(RouterOptions{InstallStateFile: stateFile, DisableTaskWorker: true}))
	defer server.Close()
	req, err := http.NewRequest(http.MethodPost, server.URL+"/api/agent/channels/openclaw-weixin/session", bytes.NewBufferString(`{"status":"confirmed","provider_token":"provider-token-session","account_id":"bot-session@im.bot","user_id":"wx-session@im.wechat","display_name":"运行态会话"}`))
	if err != nil {
		t.Fatalf("create session update request: %v", err)
	}
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("Authorization", "Bearer "+token)
	resp, err := server.Client().Do(req)
	if err != nil {
		t.Fatalf("POST session update failed: %v", err)
	}
	defer resp.Body.Close()
	if resp.StatusCode != http.StatusOK {
		body, _ := io.ReadAll(resp.Body)
		t.Fatalf("expected session update 200, got %d: %s", resp.StatusCode, body)
	}

	state, err = store.Load()
	if err != nil {
		t.Fatalf("reload state after session update: %v", err)
	}
	wechat, _, ok := findAgentWeChatChannelByKey(state.AgentChannels.normalized(), "wechat_session_limited")
	if !ok {
		t.Fatal("expected session channel to remain present")
	}
	if wechat.ProviderToken != "provider-token-session" || wechat.AccountID != "bot-session@im.bot" {
		t.Fatalf("expected session runtime fields to update, got %+v", wechat)
	}
	if wechat.DataScope != agentTableAccessTables {
		t.Fatalf("session update must not rewrite data scope, got %q", wechat.DataScope)
	}
	if got := strings.Join(wechat.AllowedTables, ","); got != "data_sources" {
		t.Fatalf("session update must not rewrite allowed tables, got %q", got)
	}
}

func TestAgentWeChatPairExchangePreservesAuthorization(t *testing.T) {
	stateFile := writeInstalledState(t, "Test Admin", "admin", "secret123")
	store := newInstallStore(stateFile)
	state, err := store.Load()
	if err != nil {
		t.Fatalf("load installed state: %v", err)
	}
	state.AgentChannels.WeChats = []agentWeChatChannelConfig{
		{
			Key:           "wechat_pair_limited",
			Enabled:       true,
			Status:        "waiting",
			BaseURL:       agentWeChatDefaultBaseURL,
			BotType:       "3",
			BindCode:      "ABC123",
			BindSession:   "moyi_wc_pair_session_test",
			BindExpiresAt: time.Now().UTC().Add(time.Hour),
			DisplayName:   "配对受限通道",
			AgentHint:     agentWeChatDefaultHint,
			DataScope:     agentTableAccessTables,
			AllowedTables: []string{"data_sources"},
		},
	}
	state.AgentChannels.WeChat = agentWeChatChannelConfig{}
	if err := store.Save(state); err != nil {
		t.Fatalf("save pair channel: %v", err)
	}

	server := httptest.NewServer(NewRouter(RouterOptions{InstallStateFile: stateFile, DisableTaskWorker: true}))
	defer server.Close()
	req, err := http.NewRequest(http.MethodPost, server.URL+"/api/agent/pair/exchange", bytes.NewBufferString(`{"code":"ABC123","session":"moyi_wc_pair_session_test","display_name":"配对运行态","account_id":"bot-pair@im.bot","user_id":"wx-pair@im.wechat"}`))
	if err != nil {
		t.Fatalf("create pair exchange request: %v", err)
	}
	req.Header.Set("Content-Type", "application/json")
	resp, err := server.Client().Do(req)
	if err != nil {
		t.Fatalf("POST pair exchange failed: %v", err)
	}
	defer resp.Body.Close()
	if resp.StatusCode != http.StatusOK {
		body, _ := io.ReadAll(resp.Body)
		t.Fatalf("expected pair exchange 200, got %d: %s", resp.StatusCode, body)
	}

	state, err = store.Load()
	if err != nil {
		t.Fatalf("reload state after pair exchange: %v", err)
	}
	wechat, _, ok := findAgentWeChatChannelByKey(state.AgentChannels.normalized(), "wechat_pair_limited")
	if !ok {
		t.Fatal("expected pair channel to remain present")
	}
	if wechat.Token == "" || wechat.AccountID != "bot-pair@im.bot" || wechat.BoundUser != "wx-pair@im.wechat" {
		t.Fatalf("expected pair runtime fields to update, got %+v", wechat)
	}
	if wechat.DataScope != agentTableAccessTables {
		t.Fatalf("pair exchange must not rewrite data scope, got %q", wechat.DataScope)
	}
	if got := strings.Join(wechat.AllowedTables, ","); got != "data_sources" {
		t.Fatalf("pair exchange must not rewrite allowed tables, got %q", got)
	}
}

func TestAgentWeChatWorkerErrorPreservesAuthorization(t *testing.T) {
	stateFile := writeInstalledState(t, "Test Admin", "admin", "secret123")
	store := newInstallStore(stateFile)
	state, err := store.Load()
	if err != nil {
		t.Fatalf("load installed state: %v", err)
	}
	state.AgentChannels.WeChats = []agentWeChatChannelConfig{
		{
			Key:           "wechat_error_limited",
			Enabled:       true,
			Status:        "bound",
			BaseURL:       agentWeChatDefaultBaseURL,
			BotType:       "3",
			ProviderToken: "provider-token-error",
			AccountID:     "bot-error@im.bot",
			Token:         newAgentWeChatToken(),
			DisplayName:   "报错受限通道",
			AgentHint:     agentWeChatDefaultHint,
			DataScope:     agentTableAccessTables,
			AllowedTables: []string{"data_sources"},
		},
	}
	state.AgentChannels.WeChat = agentWeChatChannelConfig{}
	if err := store.Save(state); err != nil {
		t.Fatalf("save worker error channel: %v", err)
	}

	admin := newAdminServer("/moyi-7k3x9-admin", "admin", "secret123", "", stateFile, "test")
	admin.updateAgentWeChatWorkerError("wechat_error_limited", "provider timeout", true)

	state, err = store.Load()
	if err != nil {
		t.Fatalf("reload state after worker error: %v", err)
	}
	wechat, _, ok := findAgentWeChatChannelByKey(state.AgentChannels.normalized(), "wechat_error_limited")
	if !ok {
		t.Fatal("expected worker error channel to remain present")
	}
	if wechat.Status != "expired" || wechat.LastError != "provider timeout" {
		t.Fatalf("expected worker error runtime fields to update, got %+v", wechat)
	}
	if wechat.DataScope != agentTableAccessTables {
		t.Fatalf("worker error must not rewrite data scope, got %q", wechat.DataScope)
	}
	if got := strings.Join(wechat.AllowedTables, ","); got != "data_sources" {
		t.Fatalf("worker error must not rewrite allowed tables, got %q", got)
	}
}

func TestAgentWeChatWorkerRepliesUnsupportedForNonTextMessage(t *testing.T) {
	stateFile := writeInstalledState(t, "Test Admin", "admin", "secret123")
	store := newInstallStore(stateFile)

	var textReplies []string
	var getConfigRequests int
	provider := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		switch r.URL.Path {
		case "/ilink/bot/getupdates":
			_, _ = w.Write([]byte(`{"ret":0,"get_updates_buf":"buf-non-text-2","msgs":[{"message_id":"provider-msg-non-text","from_user_id":"wx-user-non-text@im.wechat","to_user_id":"bot-non-text@im.bot","session_id":"session-non-text","message_type":1,"message_state":2,"context_token":"ctx-non-text","item_list":[{"type":4,"file_item":{"file_name":"photo.jpg"}}]}]}`))
		case "/ilink/bot/getconfig":
			getConfigRequests++
			_, _ = w.Write([]byte(`{"ret":0,"typing_ticket":"typing-ticket-non-text"}`))
		case "/ilink/bot/sendmessage":
			var payload agentWeChatSendMessageRequest
			if err := json.NewDecoder(r.Body).Decode(&payload); err != nil {
				t.Fatalf("decode sendmessage request: %v", err)
			}
			if payload.Message.ToUserID != "wx-user-non-text@im.wechat" || payload.Message.ContextToken != "ctx-non-text" {
				t.Fatalf("unexpected unsupported reply envelope: %+v", payload.Message)
			}
			if len(payload.Message.ItemList) != 1 || payload.Message.ItemList[0].TextItem == nil {
				t.Fatalf("expected one text unsupported reply, got %+v", payload.Message.ItemList)
			}
			textReplies = append(textReplies, payload.Message.ItemList[0].TextItem.Text)
			_, _ = w.Write([]byte(`{"ret":0}`))
		default:
			http.NotFound(w, r)
		}
	}))
	defer provider.Close()

	state, err := store.Load()
	if err != nil {
		t.Fatalf("load installed state: %v", err)
	}
	state.AgentChannels.WeChats = []agentWeChatChannelConfig{
		{
			Key:           "wechat_non_text",
			Enabled:       true,
			Status:        "bound",
			BaseURL:       provider.URL,
			BotType:       "3",
			ProviderToken: "provider-token-non-text",
			AccountID:     "bot-non-text@im.bot",
			SyncBuffer:    "buf-non-text-1",
			Token:         newAgentWeChatToken(),
			DisplayName:   "非文本通道",
			AgentHint:     agentWeChatDefaultHint,
			DataScope:     agentTableAccessTables,
			AllowedTables: []string{"data_sources"},
		},
	}
	state.AgentChannels.WeChat = agentWeChatChannelConfig{}
	if err := store.Save(state); err != nil {
		t.Fatalf("save non-text channel: %v", err)
	}

	admin := newAdminServer("/moyi-7k3x9-admin", "admin", "secret123", "", stateFile, "test")
	ran, _ := admin.runAgentWeChatChannelPollOnce(context.Background())
	if !ran {
		t.Fatal("expected wechat worker to process non-text message")
	}
	if getConfigRequests != 0 {
		t.Fatalf("non-text message should not start typing indicator, got %d getconfig requests", getConfigRequests)
	}
	if len(textReplies) != 1 || !strings.Contains(textReplies[0], "暂不支持") || !strings.Contains(textReplies[0], "非文本") {
		t.Fatalf("expected unsupported non-text reply, got %+v", textReplies)
	}
	runs, err := store.ListAgentRuns(5)
	if err != nil {
		t.Fatalf("list agent runs after non-text message: %v", err)
	}
	if len(runs) != 0 {
		t.Fatalf("non-text message should not invoke agent run, got %+v", runs)
	}
	messages, err := store.ListAgentWeChatMessages(5)
	if err != nil {
		t.Fatalf("list archived non-text message: %v", err)
	}
	if len(messages) != 1 || !strings.Contains(messages[0].MessageID, "n-text") || !strings.Contains(messages[0].InboundText, "非文本") || !strings.Contains(messages[0].InboundText, "文件") || !strings.Contains(messages[0].ReplyText, "暂不支持") || messages[0].Status != "ok" {
		t.Fatalf("expected archived unsupported non-text reply, got %+v", messages)
	}

	state, err = store.Load()
	if err != nil {
		t.Fatalf("reload state after non-text message: %v", err)
	}
	wechat, _, ok := findAgentWeChatChannelByKey(state.AgentChannels.normalized(), "wechat_non_text")
	if !ok {
		t.Fatal("expected non-text channel to remain present")
	}
	if wechat.LastOutboundAt.IsZero() || wechat.LastError != "" || wechat.SyncBuffer != "buf-non-text-2" {
		t.Fatalf("expected non-text reply to update runtime fields, got %+v", wechat)
	}
	if got := strings.Join(wechat.AllowedTables, ","); got != "data_sources" {
		t.Fatalf("non-text reply must not rewrite allowed tables, got %q", got)
	}
}

func TestAgentModelPromptCarriesChannelTableAuthorization(t *testing.T) {
	stateFile := writeInstalledState(t, "Test Admin", "admin", "secret123")
	store := newInstallStore(stateFile)
	state, err := store.Load()
	if err != nil {
		t.Fatalf("load installed state: %v", err)
	}

	var captured struct {
		Messages []struct {
			Role    string `json:"role"`
			Content string `json:"content"`
		} `json:"messages"`
	}
	aiServer := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path != "/v1/chat/completions" {
			t.Fatalf("unexpected AI path %q", r.URL.Path)
		}
		if got := r.Header.Get("Authorization"); got != "Bearer sk-agent-limited" {
			t.Fatalf("unexpected authorization %q", got)
		}
		if err := json.NewDecoder(r.Body).Decode(&captured); err != nil {
			t.Fatalf("decode model request: %v", err)
		}
		w.Header().Set("Content-Type", "application/json")
		_, _ = w.Write([]byte(`{"choices":[{"message":{"content":"模型确认受限"}}]}`))
	}))
	defer aiServer.Close()

	state.AI = aiConfig{
		Provider:  "bailian",
		APIKey:    "sk-agent-limited",
		BaseURL:   aiServer.URL + "/v1",
		ChatModel: "qwen-plus",
	}
	if err := store.Save(state); err != nil {
		t.Fatalf("save state with AI config: %v", err)
	}

	admin := newAdminServer("/moyi-7k3x9-admin", "admin", "secret123", "", stateFile, "test")
	response := admin.runAgentChat(context.Background(), state, agentChatRequest{
		Message:         "列出有哪些数据表",
		TableAccessMode: agentTableAccessTables,
		AllowedTables:   []string{"data_sources"},
	})
	if !response.OK || !response.ModelUsed {
		t.Fatalf("expected configured model to be used, got %+v", response)
	}
	if response.Reply != "模型确认受限" {
		t.Fatalf("unexpected model reply %q", response.Reply)
	}
	if response.Run.Metadata["table_authorization"] != "data_sources" {
		t.Fatalf("expected run metadata to record limited tables, got %+v", response.Run.Metadata)
	}

	joinedMessages := ""
	for _, message := range captured.Messages {
		joinedMessages += "\n" + message.Content
	}
	if !strings.Contains(joinedMessages, "只允许读取：data_sources") {
		t.Fatalf("expected model prompt to carry allowed table list, got %q", joinedMessages)
	}
	if strings.Contains(joinedMessages, "默认拥有所有已登记数据表") {
		t.Fatalf("expected model prompt not to claim full table access, got %q", joinedMessages)
	}

	if len(response.ToolResults) != 1 || response.ToolResults[0].Name != "list_tables" {
		t.Fatalf("expected list_tables result, got %+v", response.ToolResults)
	}
	for _, row := range response.ToolResults[0].Rows {
		if row["name"] != "data_sources" {
			t.Fatalf("expected only authorized data_sources table, got row %+v", row)
		}
	}
}

func TestAgentWeChatWorkerPollsProviderAndReplies(t *testing.T) {
	stateFile := writeInstalledState(t, "Test Admin", "admin", "secret123")
	store := newInstallStore(stateFile)

	var getUpdatesRequests int
	var getConfigRequests int
	var sendMessageRequests int
	var typingStatuses []int
	provider := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		switch r.URL.Path {
		case "/ilink/bot/getupdates":
			getUpdatesRequests++
			if r.Method != http.MethodPost {
				t.Fatalf("expected getupdates POST, got %s", r.Method)
			}
			if r.Header.Get("AuthorizationType") != "ilink_bot_token" || r.Header.Get("Authorization") != "Bearer provider-token-1" {
				t.Fatalf("unexpected getupdates auth headers: %s %s", r.Header.Get("AuthorizationType"), r.Header.Get("Authorization"))
			}
			if r.Header.Get("X-WECHAT-UIN") == "" {
				t.Fatal("expected X-WECHAT-UIN header")
			}
			var payload agentWeChatGetUpdatesRequest
			if err := json.NewDecoder(r.Body).Decode(&payload); err != nil {
				t.Fatalf("decode getupdates request: %v", err)
			}
			if payload.GetUpdatesBuf != "buf-1" || payload.BaseInfo["channel_version"] == "" {
				t.Fatalf("unexpected getupdates payload: %+v", payload)
			}
			_, _ = w.Write([]byte(`{"ret":0,"get_updates_buf":"buf-2","msgs":[{"message_id":"provider-msg-1","from_user_id":"wx-user-2@im.wechat","to_user_id":"bot-1@im.bot","client_id":"client-1","session_id":"session-1","message_type":1,"message_state":2,"context_token":"ctx-1","item_list":[{"type":1,"text_item":{"text":"我们后台有几个管理员账号？"}}]}]}`))
		case "/ilink/bot/getconfig":
			getConfigRequests++
			if r.Method != http.MethodPost {
				t.Fatalf("expected getconfig POST, got %s", r.Method)
			}
			var payload agentWeChatGetConfigRequest
			if err := json.NewDecoder(r.Body).Decode(&payload); err != nil {
				t.Fatalf("decode getconfig request: %v", err)
			}
			if payload.ILinkUserID != "wx-user-2@im.wechat" || payload.ContextToken != "ctx-1" || payload.BaseInfo["channel_version"] == "" {
				t.Fatalf("unexpected getconfig payload: %+v", payload)
			}
			_, _ = w.Write([]byte(`{"ret":0,"typing_ticket":"typing-ticket-1"}`))
		case "/ilink/bot/sendtyping":
			if r.Method != http.MethodPost {
				t.Fatalf("expected sendtyping POST, got %s", r.Method)
			}
			var payload agentWeChatSendTypingRequest
			if err := json.NewDecoder(r.Body).Decode(&payload); err != nil {
				t.Fatalf("decode sendtyping request: %v", err)
			}
			if payload.ILinkUserID != "wx-user-2@im.wechat" || payload.TypingTicket != "typing-ticket-1" || payload.BaseInfo["channel_version"] == "" {
				t.Fatalf("unexpected sendtyping payload: %+v", payload)
			}
			typingStatuses = append(typingStatuses, payload.Status)
			_, _ = w.Write([]byte(`{"ret":0}`))
		case "/ilink/bot/sendmessage":
			sendMessageRequests++
			if r.Method != http.MethodPost {
				t.Fatalf("expected sendmessage POST, got %s", r.Method)
			}
			if r.Header.Get("Authorization") != "Bearer provider-token-1" {
				t.Fatalf("unexpected sendmessage authorization: %s", r.Header.Get("Authorization"))
			}
			var payload agentWeChatSendMessageRequest
			if err := json.NewDecoder(r.Body).Decode(&payload); err != nil {
				t.Fatalf("decode sendmessage request: %v", err)
			}
			if payload.Message.FromUserID != "" || payload.Message.ToUserID != "wx-user-2@im.wechat" || payload.Message.MessageType != 2 || payload.Message.MessageState != 2 || payload.Message.ContextToken != "ctx-1" {
				t.Fatalf("unexpected sendmessage envelope: %+v", payload.Message)
			}
			if len(payload.Message.ItemList) != 1 || payload.Message.ItemList[0].TextItem == nil || !strings.Contains(payload.Message.ItemList[0].TextItem.Text, "admin") {
				t.Fatalf("expected sendmessage text reply to mention admin, got %+v", payload.Message.ItemList)
			}
			_, _ = w.Write([]byte(`{"ret":0}`))
		default:
			http.NotFound(w, r)
		}
	}))
	defer provider.Close()

	state, err := store.Load()
	if err != nil {
		t.Fatalf("load installed state: %v", err)
	}
	state.AgentChannels.WeChat = agentWeChatChannelConfig{
		Enabled:        true,
		Status:         "bound",
		BaseURL:        provider.URL,
		BotType:        "3",
		ProviderToken:  "provider-token-1",
		AccountID:      "bot-1@im.bot",
		OpenClawUserID: "wx-user-1@im.wechat",
		SyncBuffer:     "buf-1",
		Token:          newAgentWeChatToken(),
		DisplayName:    agentWeChatDefaultName,
		AgentHint:      agentWeChatDefaultHint,
		DataScope:      agentTableAccessAll,
		BoundUser:      "wx-user-1@im.wechat",
		BoundAt:        time.Now().UTC(),
	}
	if err := store.Save(state); err != nil {
		t.Fatalf("save wechat channel: %v", err)
	}

	admin := newAdminServer("/moyi-7k3x9-admin", "admin", "secret123", "", stateFile, "test")
	ran, interval := admin.runAgentWeChatChannelPollOnce(context.Background())
	if !ran || interval < time.Second {
		t.Fatalf("expected worker to poll once, ran=%v interval=%s", ran, interval)
	}
	if getUpdatesRequests != 1 || sendMessageRequests != 1 {
		t.Fatalf("expected one getupdates and one sendmessage request, got %d and %d", getUpdatesRequests, sendMessageRequests)
	}
	if getConfigRequests != 1 || len(typingStatuses) != 2 || typingStatuses[0] != agentWeChatTypingStatus || typingStatuses[1] != agentWeChatTypingCancel {
		t.Fatalf("expected typing start/cancel around agent run, getconfig=%d statuses=%v", getConfigRequests, typingStatuses)
	}
	state, err = store.Load()
	if err != nil {
		t.Fatalf("reload state after worker: %v", err)
	}
	wechat := state.AgentChannels.normalized().WeChat
	if wechat.SyncBuffer != "buf-2" || wechat.LastMessageAt.IsZero() || wechat.LastOutboundAt.IsZero() || wechat.LastError != "" {
		t.Fatalf("expected worker to persist channel progress, got %+v", wechat)
	}
	runs, err := store.ListAgentRuns(5)
	if err != nil {
		t.Fatalf("list agent runs: %v", err)
	}
	if len(runs) == 0 || runs[0].SessionID == "" || runs[0].Actor != "wechat:wx-user-2@im.wechat" {
		t.Fatalf("expected provider message to persist as wechat agent run, got %+v", runs)
	}
	wechatMessages, err := store.ListAgentWeChatMessages(5)
	if err != nil {
		t.Fatalf("list archived wechat messages: %v", err)
	}
	if len(wechatMessages) == 0 || wechatMessages[0].MessageID != "provider-msg-1" || wechatMessages[0].FromUserID != "wx-user-2@im.wechat" || !strings.Contains(wechatMessages[0].ReplyText, "admin") || wechatMessages[0].Status != "ok" {
		t.Fatalf("expected provider reply to be archived, got %+v", wechatMessages)
	}
}

func TestAgentWeChatWorkerPollsMultipleChannels(t *testing.T) {
	stateFile := writeInstalledState(t, "Test Admin", "admin", "secret123")
	store := newInstallStore(stateFile)

	getUpdatesByToken := map[string]int{}
	provider := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path != "/ilink/bot/getupdates" {
			http.NotFound(w, r)
			return
		}
		token := strings.TrimPrefix(r.Header.Get("Authorization"), "Bearer ")
		getUpdatesByToken[token]++
		switch token {
		case "provider-token-a":
			_, _ = w.Write([]byte(`{"ret":0,"get_updates_buf":"buf-a-2","msgs":[]}`))
		case "provider-token-b":
			_, _ = w.Write([]byte(`{"ret":0,"get_updates_buf":"buf-b-2","msgs":[]}`))
		default:
			t.Fatalf("unexpected provider token: %q", token)
		}
	}))
	defer provider.Close()

	state, err := store.Load()
	if err != nil {
		t.Fatalf("load installed state: %v", err)
	}
	state.AgentChannels.WeChats = []agentWeChatChannelConfig{
		{
			Key:           "wechat_a",
			Enabled:       true,
			Status:        "bound",
			BaseURL:       provider.URL,
			BotType:       "3",
			ProviderToken: "provider-token-a",
			AccountID:     "bot-a@im.bot",
			SyncBuffer:    "buf-a-1",
			Token:         newAgentWeChatToken(),
			DisplayName:   "微信通道 A",
			AgentHint:     agentWeChatDefaultHint,
			DataScope:     agentTableAccessAll,
		},
		{
			Key:           "wechat_b",
			Enabled:       true,
			Status:        "bound",
			BaseURL:       provider.URL,
			BotType:       "3",
			ProviderToken: "provider-token-b",
			AccountID:     "bot-b@im.bot",
			SyncBuffer:    "buf-b-1",
			Token:         newAgentWeChatToken(),
			DisplayName:   "微信通道 B",
			AgentHint:     agentWeChatDefaultHint,
			DataScope:     agentTableAccessAll,
		},
	}
	state.AgentChannels.WeChat = agentWeChatChannelConfig{}
	if err := store.Save(state); err != nil {
		t.Fatalf("save wechat channels: %v", err)
	}

	admin := newAdminServer("/moyi-7k3x9-admin", "admin", "secret123", "", stateFile, "test")
	ran, interval := admin.runAgentWeChatChannelPollOnce(context.Background())
	if !ran || interval < time.Second {
		t.Fatalf("expected worker to poll channels, ran=%v interval=%s", ran, interval)
	}
	if getUpdatesByToken["provider-token-a"] != 1 || getUpdatesByToken["provider-token-b"] != 1 {
		t.Fatalf("expected both channels to poll once, got %+v", getUpdatesByToken)
	}
	state, err = store.Load()
	if err != nil {
		t.Fatalf("reload state after worker: %v", err)
	}
	channels := state.AgentChannels.normalized()
	wechatA, _, ok := findAgentWeChatChannelByKey(channels, "wechat_a")
	if !ok || wechatA.SyncBuffer != "buf-a-2" {
		t.Fatalf("expected channel A sync buffer to update, got %+v", wechatA)
	}
	wechatB, _, ok := findAgentWeChatChannelByKey(channels, "wechat_b")
	if !ok || wechatB.SyncBuffer != "buf-b-2" {
		t.Fatalf("expected channel B sync buffer to update, got %+v", wechatB)
	}
}

func TestAgentWeChatWorkerSendsExportFileAsWeixinAttachment(t *testing.T) {
	stateFile := writeInstalledState(t, "Test Admin", "admin", "secret123")
	store := newInstallStore(stateFile)

	oldCDNBaseURL := agentWeChatCDNBaseURL
	defer func() {
		agentWeChatCDNBaseURL = oldCDNBaseURL
	}()

	var cdnUploadRequests int
	var expectedCipherSize int
	cdn := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path != "/upload" {
			http.NotFound(w, r)
			return
		}
		cdnUploadRequests++
		if r.URL.Query().Get("encrypted_query_param") != "upload-param-1" || r.URL.Query().Get("filekey") == "" {
			t.Fatalf("unexpected CDN upload query: %s", r.URL.RawQuery)
		}
		body, err := io.ReadAll(r.Body)
		if err != nil {
			t.Fatalf("read CDN upload body: %v", err)
		}
		if len(body) != expectedCipherSize || len(body)%16 != 0 {
			t.Fatalf("expected encrypted CDN body size %d aligned to 16, got %d", expectedCipherSize, len(body))
		}
		w.Header().Set("x-encrypted-param", "download-param-1")
		w.WriteHeader(http.StatusOK)
	}))
	defer cdn.Close()
	agentWeChatCDNBaseURL = cdn.URL

	var getUpdatesRequests int
	var getUploadURLRequests int
	var textMessages []string
	var fileItems []agentWeChatProviderFileItem
	provider := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		switch r.URL.Path {
		case "/ilink/bot/getupdates":
			getUpdatesRequests++
			_, _ = w.Write([]byte(`{"ret":0,"get_updates_buf":"buf-export-2","msgs":[{"message_id":"provider-msg-export","from_user_id":"wx-user-file@im.wechat","to_user_id":"bot-1@im.bot","session_id":"session-export","message_type":1,"message_state":2,"context_token":"ctx-file","item_list":[{"type":1,"text_item":{"text":"把管理员账号导出 csv 发给我"}}]}]}`))
		case "/ilink/bot/getuploadurl":
			getUploadURLRequests++
			if r.Header.Get("Authorization") != "Bearer provider-token-1" {
				t.Fatalf("unexpected getuploadurl authorization: %s", r.Header.Get("Authorization"))
			}
			var payload agentWeChatGetUploadURLRequest
			if err := json.NewDecoder(r.Body).Decode(&payload); err != nil {
				t.Fatalf("decode getuploadurl request: %v", err)
			}
			if payload.MediaType != 3 || payload.ToUserID != "wx-user-file@im.wechat" || !payload.NoNeedThumb || payload.RawSize <= 0 || payload.RawFileMD5 == "" || payload.AESKey == "" || payload.FileKey == "" {
				t.Fatalf("unexpected getuploadurl payload: %+v", payload)
			}
			expectedCipherSize = payload.FileSize
			_, _ = w.Write([]byte(`{"ret":0,"upload_full_url":"` + cdn.URL + `/upload?encrypted_query_param=upload-param-1&filekey=` + payload.FileKey + `&taskid=task-1"}`))
		case "/ilink/bot/sendmessage":
			var payload agentWeChatSendMessageRequest
			if err := json.NewDecoder(r.Body).Decode(&payload); err != nil {
				t.Fatalf("decode sendmessage request: %v", err)
			}
			if payload.Message.FromUserID != "" || payload.Message.ToUserID != "wx-user-file@im.wechat" || payload.Message.ContextToken != "ctx-file" {
				t.Fatalf("unexpected sendmessage envelope: %+v", payload.Message)
			}
			if len(payload.Message.ItemList) != 1 {
				t.Fatalf("expected one message item, got %+v", payload.Message.ItemList)
			}
			item := payload.Message.ItemList[0]
			switch item.Type {
			case 1:
				if item.TextItem == nil {
					t.Fatalf("expected text item, got %+v", item)
				}
				textMessages = append(textMessages, item.TextItem.Text)
			case 4:
				if item.FileItem == nil {
					t.Fatalf("expected file item, got %+v", item)
				}
				fileItems = append(fileItems, *item.FileItem)
			default:
				t.Fatalf("unexpected message item type: %+v", item)
			}
			_, _ = w.Write([]byte(`{"ret":0}`))
		default:
			http.NotFound(w, r)
		}
	}))
	defer provider.Close()

	state, err := store.Load()
	if err != nil {
		t.Fatalf("load installed state: %v", err)
	}
	state.AgentChannels.WeChat = agentWeChatChannelConfig{
		Enabled:        true,
		Status:         "bound",
		BaseURL:        provider.URL,
		BotType:        "3",
		ProviderToken:  "provider-token-1",
		AccountID:      "bot-1@im.bot",
		OpenClawUserID: "wx-user-1@im.wechat",
		SyncBuffer:     "buf-export-1",
		Token:          newAgentWeChatToken(),
		DisplayName:    agentWeChatDefaultName,
		AgentHint:      agentWeChatDefaultHint,
		DataScope:      agentTableAccessAll,
		BoundUser:      "wx-user-1@im.wechat",
		BoundAt:        time.Now().UTC(),
	}
	if err := store.Save(state); err != nil {
		t.Fatalf("save wechat channel: %v", err)
	}

	admin := newAdminServer("/moyi-7k3x9-admin", "admin", "secret123", "", stateFile, "test")
	ran, _ := admin.runAgentWeChatChannelPollOnce(context.Background())
	if !ran {
		t.Fatal("expected worker to poll export message")
	}
	if getUpdatesRequests != 1 || getUploadURLRequests != 1 || cdnUploadRequests != 1 {
		t.Fatalf("expected getupdates/getuploadurl/cdn once, got %d/%d/%d", getUpdatesRequests, getUploadURLRequests, cdnUploadRequests)
	}
	if len(textMessages) != 1 {
		t.Fatalf("expected one text caption before file, got %+v", textMessages)
	}
	if strings.Contains(textMessages[0], "/ai/files/") || strings.Contains(strings.ToLower(textMessages[0]), "http") {
		t.Fatalf("expected wechat text caption not to expose download link, got %q", textMessages[0])
	}
	if len(fileItems) != 1 {
		t.Fatalf("expected one file attachment message, got %+v", fileItems)
	}
	fileItem := fileItems[0]
	if fileItem.Media == nil || fileItem.Media.EncryptQueryParam != "download-param-1" || fileItem.Media.AESKey == "" || fileItem.Media.EncryptType != 1 {
		t.Fatalf("expected wechat file CDN media, got %+v", fileItem)
	}
	if !strings.HasPrefix(fileItem.FileName, "moyi-agent-export-") || !strings.HasSuffix(fileItem.FileName, ".csv") || fileItem.Len == "" {
		t.Fatalf("expected export CSV attachment metadata, got %+v", fileItem)
	}
	state, err = store.Load()
	if err != nil {
		t.Fatalf("reload state after export worker: %v", err)
	}
	wechat := state.AgentChannels.normalized().WeChat
	if wechat.LastOutboundAt.IsZero() || wechat.LastError != "" {
		t.Fatalf("expected successful file outbound status, got %+v", wechat)
	}
}

func TestAdminAgentChatRunsReadOnlyQuery(t *testing.T) {
	stateFile := writeInstalledState(t, "Test Admin", "admin", "secret123")
	server := httptest.NewServer(NewRouter(RouterOptions{InstallStateFile: stateFile}))
	defer server.Close()

	client := server.Client()
	client.CheckRedirect = func(req *http.Request, via []*http.Request) error {
		return http.ErrUseLastResponse
	}
	sessionCookie := loginTestAdmin(t, client, server.URL)

	body := bytes.NewBufferString(`{"message":"select site_name, ai_provider from install_state limit 1"}`)
	req, err := http.NewRequest(http.MethodPost, server.URL+"/moyi-7k3x9-admin/ai/chat", body)
	if err != nil {
		t.Fatalf("create agent query request: %v", err)
	}
	req.Header.Set("Content-Type", "application/json")
	req.AddCookie(sessionCookie)
	resp, err := client.Do(req)
	if err != nil {
		t.Fatalf("POST AI chat failed: %v", err)
	}
	defer resp.Body.Close()
	if resp.StatusCode != http.StatusOK {
		t.Fatalf("expected status 200, got %d", resp.StatusCode)
	}

	var parsed agentChatResponse
	if err := json.NewDecoder(resp.Body).Decode(&parsed); err != nil {
		t.Fatalf("decode AI chat response: %v", err)
	}
	if len(parsed.ToolResults) != 1 || parsed.ToolResults[0].Name != "query_readonly" || !parsed.ToolResults[0].OK {
		t.Fatalf("expected successful query_readonly tool, got %+v", parsed.ToolResults)
	}
	if got := parsed.ToolResults[0].Rows[0]["site_name"]; got != "Test Admin" {
		t.Fatalf("expected site_name Test Admin, got %q", got)
	}
}

func TestAdminAgentChatAnswersAdminUserCount(t *testing.T) {
	stateFile := writeInstalledState(t, "Test Admin", "admin", "secret123")
	server := httptest.NewServer(NewRouter(RouterOptions{InstallStateFile: stateFile}))
	defer server.Close()

	client := server.Client()
	client.CheckRedirect = func(req *http.Request, via []*http.Request) error {
		return http.ErrUseLastResponse
	}
	sessionCookie := loginTestAdmin(t, client, server.URL)

	body := bytes.NewBufferString(`{"message":"我们后台有几个管理员账号？"}`)
	req, err := http.NewRequest(http.MethodPost, server.URL+"/moyi-7k3x9-admin/ai/chat", body)
	if err != nil {
		t.Fatalf("create admin user count request: %v", err)
	}
	req.Header.Set("Content-Type", "application/json")
	req.AddCookie(sessionCookie)
	resp, err := client.Do(req)
	if err != nil {
		t.Fatalf("POST AI chat failed: %v", err)
	}
	defer resp.Body.Close()
	if resp.StatusCode != http.StatusOK {
		t.Fatalf("expected status 200, got %d", resp.StatusCode)
	}

	var parsed agentChatResponse
	if err := json.NewDecoder(resp.Body).Decode(&parsed); err != nil {
		t.Fatalf("decode AI chat response: %v", err)
	}
	if parsed.Run.Mode != string(agentIntentUserAccess) {
		t.Fatalf("expected user access mode, got %q", parsed.Run.Mode)
	}
	if len(parsed.ToolResults) < 2 || parsed.ToolResults[0].Name != "count_table" {
		t.Fatalf("expected count_table tool first, got %+v", parsed.ToolResults)
	}
	if got := parsed.ToolResults[0].Rows[0]["count"]; got != "1" {
		t.Fatalf("expected admin user count 1, got %q", got)
	}
	if !strings.Contains(parsed.Reply, "1 个") || !strings.Contains(parsed.Reply, "admin") {
		t.Fatalf("expected direct admin count reply, got %q", parsed.Reply)
	}
}

func TestAdminAgentChatSupportsCountQuery(t *testing.T) {
	stateFile := writeInstalledState(t, "Test Admin", "admin", "secret123")
	server := httptest.NewServer(NewRouter(RouterOptions{InstallStateFile: stateFile}))
	defer server.Close()

	client := server.Client()
	client.CheckRedirect = func(req *http.Request, via []*http.Request) error {
		return http.ErrUseLastResponse
	}
	sessionCookie := loginTestAdmin(t, client, server.URL)

	body := bytes.NewBufferString(`{"message":"select count(*) from admin_users"}`)
	req, err := http.NewRequest(http.MethodPost, server.URL+"/moyi-7k3x9-admin/ai/chat", body)
	if err != nil {
		t.Fatalf("create count query request: %v", err)
	}
	req.Header.Set("Content-Type", "application/json")
	req.AddCookie(sessionCookie)
	resp, err := client.Do(req)
	if err != nil {
		t.Fatalf("POST AI chat failed: %v", err)
	}
	defer resp.Body.Close()
	if resp.StatusCode != http.StatusOK {
		t.Fatalf("expected status 200, got %d", resp.StatusCode)
	}

	var parsed agentChatResponse
	if err := json.NewDecoder(resp.Body).Decode(&parsed); err != nil {
		t.Fatalf("decode AI chat response: %v", err)
	}
	if len(parsed.ToolResults) != 1 || parsed.ToolResults[0].Name != "query_readonly" || !parsed.ToolResults[0].OK {
		t.Fatalf("expected successful query_readonly count, got %+v", parsed.ToolResults)
	}
	if got := parsed.ToolResults[0].Rows[0]["count"]; got != "1" {
		t.Fatalf("expected count 1, got %q", got)
	}
}

func TestAdminAgentChatSkipsDataToolsForGeneralAdminTask(t *testing.T) {
	stateFile := writeInstalledState(t, "Test Admin", "admin", "secret123")
	server := httptest.NewServer(NewRouter(RouterOptions{InstallStateFile: stateFile}))
	defer server.Close()

	client := server.Client()
	client.CheckRedirect = func(req *http.Request, via []*http.Request) error {
		return http.ErrUseLastResponse
	}
	sessionCookie := loginTestAdmin(t, client, server.URL)

	body := bytes.NewBufferString(`{"message":"你现在是什么角色？"}`)
	req, err := http.NewRequest(http.MethodPost, server.URL+"/moyi-7k3x9-admin/ai/chat", body)
	if err != nil {
		t.Fatalf("create general admin request: %v", err)
	}
	req.Header.Set("Content-Type", "application/json")
	req.AddCookie(sessionCookie)
	resp, err := client.Do(req)
	if err != nil {
		t.Fatalf("POST AI chat failed: %v", err)
	}
	defer resp.Body.Close()
	if resp.StatusCode != http.StatusOK {
		t.Fatalf("expected status 200, got %d", resp.StatusCode)
	}

	var parsed agentChatResponse
	if err := json.NewDecoder(resp.Body).Decode(&parsed); err != nil {
		t.Fatalf("decode AI chat response: %v", err)
	}
	if parsed.Run.Mode != string(agentIntentAdmin) {
		t.Fatalf("expected admin assistant mode, got %q", parsed.Run.Mode)
	}
	if len(parsed.ToolResults) != 0 {
		t.Fatalf("expected no data tools for general admin task, got %+v", parsed.ToolResults)
	}
	if !strings.Contains(parsed.Reply, "后台管理员助理") {
		t.Fatalf("expected admin role reply, got %q", parsed.Reply)
	}
}

func TestAdminAgentChatExportsTableFile(t *testing.T) {
	stateFile := writeInstalledState(t, "Test Admin", "admin", "secret123")
	server := httptest.NewServer(NewRouter(RouterOptions{InstallStateFile: stateFile}))
	defer server.Close()

	client := server.Client()
	client.CheckRedirect = func(req *http.Request, via []*http.Request) error {
		return http.ErrUseLastResponse
	}
	sessionCookie := loginTestAdmin(t, client, server.URL)

	body := bytes.NewBufferString(`{"message":"把管理员账号的账号、角色、状态整理成表格文件发给我"}`)
	req, err := http.NewRequest(http.MethodPost, server.URL+"/moyi-7k3x9-admin/ai/chat", body)
	if err != nil {
		t.Fatalf("create export request: %v", err)
	}
	req.Header.Set("Content-Type", "application/json")
	req.AddCookie(sessionCookie)
	resp, err := client.Do(req)
	if err != nil {
		t.Fatalf("POST AI chat failed: %v", err)
	}
	defer resp.Body.Close()
	if resp.StatusCode != http.StatusOK {
		t.Fatalf("expected status 200, got %d", resp.StatusCode)
	}

	var parsed agentChatResponse
	if err := json.NewDecoder(resp.Body).Decode(&parsed); err != nil {
		t.Fatalf("decode AI chat response: %v", err)
	}
	if parsed.Run.Mode != string(agentIntentExport) {
		t.Fatalf("expected export mode, got %q", parsed.Run.Mode)
	}
	if len(parsed.Files) != 1 || parsed.Files[0].URL == "" {
		t.Fatalf("expected generated file, got %+v", parsed.Files)
	}
	if len(parsed.ToolResults) != 1 || parsed.ToolResults[0].Name != "export_table" || parsed.ToolResults[0].File == nil {
		t.Fatalf("expected export_table tool with file, got %+v", parsed.ToolResults)
	}

	fileReq, err := http.NewRequest(http.MethodGet, server.URL+parsed.Files[0].URL, nil)
	if err != nil {
		t.Fatalf("create file download request: %v", err)
	}
	fileReq.AddCookie(sessionCookie)
	fileResp, err := client.Do(fileReq)
	if err != nil {
		t.Fatalf("download export file failed: %v", err)
	}
	defer fileResp.Body.Close()
	if fileResp.StatusCode != http.StatusOK {
		t.Fatalf("expected file status 200, got %d", fileResp.StatusCode)
	}
	fileBody, err := io.ReadAll(fileResp.Body)
	if err != nil {
		t.Fatalf("read export file: %v", err)
	}
	fileText := string(fileBody)
	for _, expected := range []string{"登录账号", "所属角色", "账号启用状态", "admin", "超级管理员"} {
		if !strings.Contains(fileText, expected) {
			t.Fatalf("expected CSV admin user content to contain %q, got %q", expected, fileText)
		}
	}
	if strings.Contains(fileText, "username") || strings.Contains(fileText, "role,status") {
		t.Fatalf("expected CSV headers to prefer field comments, got %q", fileText)
	}
}

func TestAdminAgentChatExportsXLSXFile(t *testing.T) {
	stateFile := writeInstalledState(t, "Test Admin", "admin", "secret123")
	server := httptest.NewServer(NewRouter(RouterOptions{InstallStateFile: stateFile}))
	defer server.Close()

	client := server.Client()
	client.CheckRedirect = func(req *http.Request, via []*http.Request) error {
		return http.ErrUseLastResponse
	}
	sessionCookie := loginTestAdmin(t, client, server.URL)

	body := bytes.NewBufferString(`{"message":"把管理员账号的账号、角色、状态整理成 XLSX 文件发给我"}`)
	req, err := http.NewRequest(http.MethodPost, server.URL+"/moyi-7k3x9-admin/ai/chat", body)
	if err != nil {
		t.Fatalf("create xlsx export request: %v", err)
	}
	req.Header.Set("Content-Type", "application/json")
	req.AddCookie(sessionCookie)
	resp, err := client.Do(req)
	if err != nil {
		t.Fatalf("POST AI chat failed: %v", err)
	}
	defer resp.Body.Close()
	if resp.StatusCode != http.StatusOK {
		t.Fatalf("expected status 200, got %d", resp.StatusCode)
	}

	var parsed agentChatResponse
	if err := json.NewDecoder(resp.Body).Decode(&parsed); err != nil {
		t.Fatalf("decode AI chat response: %v", err)
	}
	if len(parsed.Files) != 1 || !strings.HasSuffix(parsed.Files[0].Name, ".xlsx") {
		t.Fatalf("expected generated xlsx file, got %+v", parsed.Files)
	}
	if parsed.Files[0].MIME != "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" {
		t.Fatalf("unexpected xlsx mime %q", parsed.Files[0].MIME)
	}

	fileReq, err := http.NewRequest(http.MethodGet, server.URL+parsed.Files[0].URL, nil)
	if err != nil {
		t.Fatalf("create xlsx download request: %v", err)
	}
	fileReq.AddCookie(sessionCookie)
	fileResp, err := client.Do(fileReq)
	if err != nil {
		t.Fatalf("download xlsx export file failed: %v", err)
	}
	defer fileResp.Body.Close()
	if fileResp.StatusCode != http.StatusOK {
		t.Fatalf("expected xlsx file status 200, got %d", fileResp.StatusCode)
	}
	fileBody, err := io.ReadAll(fileResp.Body)
	if err != nil {
		t.Fatalf("read xlsx export file: %v", err)
	}
	xlsx, err := zip.NewReader(bytes.NewReader(fileBody), int64(len(fileBody)))
	if err != nil {
		t.Fatalf("expected valid xlsx zip: %v", err)
	}
	worksheet := readZipPartForTest(t, xlsx, "xl/worksheets/sheet1.xml")
	if !strings.Contains(worksheet, "admin_users") || !strings.Contains(worksheet, "登录账号") || !strings.Contains(worksheet, "超级管理员") {
		t.Fatalf("expected xlsx worksheet content, got %q", worksheet)
	}
	if strings.Contains(worksheet, ">username<") {
		t.Fatalf("expected xlsx headers to prefer field comments, got %q", worksheet)
	}
}

func TestAdminAgentChatExportsJSONFile(t *testing.T) {
	stateFile := writeInstalledState(t, "Test Admin", "admin", "secret123")
	server := httptest.NewServer(NewRouter(RouterOptions{InstallStateFile: stateFile}))
	defer server.Close()

	client := server.Client()
	client.CheckRedirect = func(req *http.Request, via []*http.Request) error {
		return http.ErrUseLastResponse
	}
	sessionCookie := loginTestAdmin(t, client, server.URL)

	body := bytes.NewBufferString(`{"message":"把数据源配置整理成 JSON 文件发给我"}`)
	req, err := http.NewRequest(http.MethodPost, server.URL+"/moyi-7k3x9-admin/ai/chat", body)
	if err != nil {
		t.Fatalf("create json export request: %v", err)
	}
	req.Header.Set("Content-Type", "application/json")
	req.AddCookie(sessionCookie)
	resp, err := client.Do(req)
	if err != nil {
		t.Fatalf("POST AI chat failed: %v", err)
	}
	defer resp.Body.Close()
	if resp.StatusCode != http.StatusOK {
		t.Fatalf("expected status 200, got %d", resp.StatusCode)
	}

	var parsed agentChatResponse
	if err := json.NewDecoder(resp.Body).Decode(&parsed); err != nil {
		t.Fatalf("decode AI chat response: %v", err)
	}
	if len(parsed.Files) != 1 || !strings.HasSuffix(parsed.Files[0].Name, ".json") {
		t.Fatalf("expected generated json file, got %+v", parsed.Files)
	}
	if parsed.Files[0].MIME != "application/json; charset=utf-8" {
		t.Fatalf("unexpected json mime %q", parsed.Files[0].MIME)
	}

	fileReq, err := http.NewRequest(http.MethodGet, server.URL+parsed.Files[0].URL, nil)
	if err != nil {
		t.Fatalf("create json download request: %v", err)
	}
	fileReq.AddCookie(sessionCookie)
	fileResp, err := client.Do(fileReq)
	if err != nil {
		t.Fatalf("download json export file failed: %v", err)
	}
	defer fileResp.Body.Close()
	if fileResp.StatusCode != http.StatusOK {
		t.Fatalf("expected json file status 200, got %d", fileResp.StatusCode)
	}

	var exported agentExportData
	if err := json.NewDecoder(fileResp.Body).Decode(&exported); err != nil {
		t.Fatalf("decode exported json: %v", err)
	}
	if exported.TotalRows != 2 || len(exported.Sheets) != 1 || exported.Sheets[0].Table != "data_sources" {
		t.Fatalf("unexpected exported json data: %+v", exported)
	}
	if got := strings.Join(exported.Sheets[0].Headers, ","); !strings.Contains(got, "数据源名称") || !strings.Contains(got, "最近一次结构扫描摘要") {
		t.Fatalf("expected exported json to include comment headers, got %+v", exported.Sheets[0].Headers)
	}
}

func TestAdminAgentChatMatchesTableAndFieldComments(t *testing.T) {
	stateFile := writeInstalledState(t, "Test Admin", "admin", "secret123")
	server := httptest.NewServer(NewRouter(RouterOptions{InstallStateFile: stateFile}))
	defer server.Close()

	client := server.Client()
	client.CheckRedirect = func(req *http.Request, via []*http.Request) error {
		return http.ErrUseLastResponse
	}
	sessionCookie := loginTestAdmin(t, client, server.URL)

	body := bytes.NewBufferString(`{"message":"把后台管理员账号的登录账号、所属角色、账号启用状态整理成 XLSX 文件发给我"}`)
	req, err := http.NewRequest(http.MethodPost, server.URL+"/moyi-7k3x9-admin/ai/chat", body)
	if err != nil {
		t.Fatalf("create comment-matched export request: %v", err)
	}
	req.Header.Set("Content-Type", "application/json")
	req.AddCookie(sessionCookie)
	resp, err := client.Do(req)
	if err != nil {
		t.Fatalf("POST AI chat failed: %v", err)
	}
	defer resp.Body.Close()
	if resp.StatusCode != http.StatusOK {
		t.Fatalf("expected status 200, got %d", resp.StatusCode)
	}

	var parsed agentChatResponse
	if err := json.NewDecoder(resp.Body).Decode(&parsed); err != nil {
		t.Fatalf("decode AI chat response: %v", err)
	}
	if len(parsed.ToolResults) != 1 || parsed.ToolResults[0].Table != "admin_users" {
		t.Fatalf("expected admin_users export from table comment, got %+v", parsed.ToolResults)
	}

	fileReq, err := http.NewRequest(http.MethodGet, server.URL+parsed.Files[0].URL, nil)
	if err != nil {
		t.Fatalf("create comment-matched xlsx download request: %v", err)
	}
	fileReq.AddCookie(sessionCookie)
	fileResp, err := client.Do(fileReq)
	if err != nil {
		t.Fatalf("download comment-matched xlsx export file failed: %v", err)
	}
	defer fileResp.Body.Close()
	fileBody, err := io.ReadAll(fileResp.Body)
	if err != nil {
		t.Fatalf("read comment-matched xlsx export file: %v", err)
	}
	xlsx, err := zip.NewReader(bytes.NewReader(fileBody), int64(len(fileBody)))
	if err != nil {
		t.Fatalf("expected valid xlsx zip: %v", err)
	}
	worksheet := readZipPartForTest(t, xlsx, "xl/worksheets/sheet1.xml")
	for _, expected := range []string{"登录账号", "所属角色", "账号启用状态"} {
		if !strings.Contains(worksheet, expected) {
			t.Fatalf("expected xlsx worksheet to include %q, got %q", expected, worksheet)
		}
	}
	for _, unexpected := range []string{"username", "role", "status", "display_name", "created_at"} {
		if strings.Contains(worksheet, ">"+unexpected+"<") {
			t.Fatalf("expected xlsx worksheet to use comment headers and only include matched comment fields, got %q", worksheet)
		}
	}
	if strings.Contains(worksheet, "后台显示名称") || strings.Contains(worksheet, "创建时间") {
		t.Fatalf("expected xlsx worksheet to only include matched comment fields, got %q", worksheet)
	}
}

func TestAdminAgentChatDescribesTableByComment(t *testing.T) {
	stateFile := writeInstalledState(t, "Test Admin", "admin", "secret123")
	server := httptest.NewServer(NewRouter(RouterOptions{InstallStateFile: stateFile}))
	defer server.Close()

	client := server.Client()
	client.CheckRedirect = func(req *http.Request, via []*http.Request) error {
		return http.ErrUseLastResponse
	}
	sessionCookie := loginTestAdmin(t, client, server.URL)

	body := bytes.NewBufferString(`{"message":"查看后台权限的字段结构"}`)
	req, err := http.NewRequest(http.MethodPost, server.URL+"/moyi-7k3x9-admin/ai/chat", body)
	if err != nil {
		t.Fatalf("create comment-matched describe request: %v", err)
	}
	req.Header.Set("Content-Type", "application/json")
	req.AddCookie(sessionCookie)
	resp, err := client.Do(req)
	if err != nil {
		t.Fatalf("POST AI chat failed: %v", err)
	}
	defer resp.Body.Close()

	var parsed agentChatResponse
	if err := json.NewDecoder(resp.Body).Decode(&parsed); err != nil {
		t.Fatalf("decode AI chat response: %v", err)
	}
	if len(parsed.ToolResults) != 1 || parsed.ToolResults[0].Name != "describe_table" || parsed.ToolResults[0].Table != "admin_permissions" {
		t.Fatalf("expected admin_permissions describe from table comment, got %+v", parsed.ToolResults)
	}
}

func TestAdminAgentChatPreviewsStorageSettings(t *testing.T) {
	stateFile := writeInstalledState(t, "Test Admin", "admin", "secret123")
	server := httptest.NewServer(NewRouter(RouterOptions{InstallStateFile: stateFile}))
	defer server.Close()

	client := server.Client()
	client.CheckRedirect = func(req *http.Request, via []*http.Request) error {
		return http.ErrUseLastResponse
	}
	sessionCookie := loginTestAdmin(t, client, server.URL)

	body := bytes.NewBufferString(`{"message":"查看存储设置"}`)
	req, err := http.NewRequest(http.MethodPost, server.URL+"/moyi-7k3x9-admin/ai/chat", body)
	if err != nil {
		t.Fatalf("create storage settings preview request: %v", err)
	}
	req.Header.Set("Content-Type", "application/json")
	req.AddCookie(sessionCookie)
	resp, err := client.Do(req)
	if err != nil {
		t.Fatalf("POST AI chat failed: %v", err)
	}
	defer resp.Body.Close()

	var parsed agentChatResponse
	if err := json.NewDecoder(resp.Body).Decode(&parsed); err != nil {
		t.Fatalf("decode AI chat response: %v", err)
	}
	if len(parsed.ToolResults) != 1 || parsed.ToolResults[0].Name != "preview_table" || parsed.ToolResults[0].Table != "storage_settings" {
		t.Fatalf("expected storage_settings preview, got %+v", parsed.ToolResults)
	}
	if got := parsed.ToolResults[0].Rows[0]["local_path"]; got != "data/uploads" {
		t.Fatalf("expected default storage local path, got %q", got)
	}
}

func TestAdminAgentChatPreviewsSettingChanges(t *testing.T) {
	stateFile := writeInstalledState(t, "Test Admin", "admin", "secret123")
	store := newInstallStore(stateFile)
	if err := store.AppendSettingChange(adminSettingChangeRecord{
		Timestamp:  time.Date(2026, 5, 17, 10, 30, 0, 0, time.UTC),
		Category:   "system",
		Action:     "保存基础信息",
		Actor:      "admin",
		Summary:    "更新站点名称",
		BeforeJSON: `{"site_name":"Old"}`,
		AfterJSON:  `{"site_name":"New"}`,
	}); err != nil {
		t.Fatalf("append setting change: %v", err)
	}

	server := httptest.NewServer(NewRouter(RouterOptions{InstallStateFile: stateFile}))
	defer server.Close()

	client := server.Client()
	client.CheckRedirect = func(req *http.Request, via []*http.Request) error {
		return http.ErrUseLastResponse
	}
	sessionCookie := loginTestAdmin(t, client, server.URL)

	body := bytes.NewBufferString(`{"message":"查看设置变更记录"}`)
	req, err := http.NewRequest(http.MethodPost, server.URL+"/moyi-7k3x9-admin/ai/chat", body)
	if err != nil {
		t.Fatalf("create setting changes preview request: %v", err)
	}
	req.Header.Set("Content-Type", "application/json")
	req.AddCookie(sessionCookie)
	resp, err := client.Do(req)
	if err != nil {
		t.Fatalf("POST AI chat failed: %v", err)
	}
	defer resp.Body.Close()

	var parsed agentChatResponse
	if err := json.NewDecoder(resp.Body).Decode(&parsed); err != nil {
		t.Fatalf("decode AI chat response: %v", err)
	}
	if len(parsed.ToolResults) != 1 || parsed.ToolResults[0].Name != "preview_table" || parsed.ToolResults[0].Table != "setting_change_logs" {
		t.Fatalf("expected setting_change_logs preview, got %+v", parsed.ToolResults)
	}
	if got := parsed.ToolResults[0].Rows[0]["summary"]; got != "更新站点名称" {
		t.Fatalf("expected setting change summary, got %q", got)
	}
}

func readZipPartForTest(t *testing.T, archive *zip.Reader, name string) string {
	t.Helper()
	for _, part := range archive.File {
		if part.Name != name {
			continue
		}
		reader, err := part.Open()
		if err != nil {
			t.Fatalf("open zip part %s: %v", name, err)
		}
		defer reader.Close()
		content, err := io.ReadAll(reader)
		if err != nil {
			t.Fatalf("read zip part %s: %v", name, err)
		}
		return string(content)
	}
	t.Fatalf("zip part %s not found", name)
	return ""
}

func TestAdminAgentChatBuildsAgentBlueprint(t *testing.T) {
	stateFile := writeInstalledState(t, "Test Admin", "admin", "secret123")
	server := httptest.NewServer(NewRouter(RouterOptions{InstallStateFile: stateFile}))
	defer server.Close()

	client := server.Client()
	client.CheckRedirect = func(req *http.Request, via []*http.Request) error {
		return http.ErrUseLastResponse
	}
	sessionCookie := loginTestAdmin(t, client, server.URL)

	body := bytes.NewBufferString(`{"message":"给出 Moyi Admin 智能体构造方案"}`)
	req, err := http.NewRequest(http.MethodPost, server.URL+"/moyi-7k3x9-admin/ai/chat", body)
	if err != nil {
		t.Fatalf("create agent design request: %v", err)
	}
	req.Header.Set("Content-Type", "application/json")
	req.AddCookie(sessionCookie)
	resp, err := client.Do(req)
	if err != nil {
		t.Fatalf("POST AI chat failed: %v", err)
	}
	defer resp.Body.Close()
	if resp.StatusCode != http.StatusOK {
		t.Fatalf("expected status 200, got %d", resp.StatusCode)
	}

	var parsed agentChatResponse
	if err := json.NewDecoder(resp.Body).Decode(&parsed); err != nil {
		t.Fatalf("decode AI chat response: %v", err)
	}
	if parsed.Run.Mode != string(agentIntentDesign) {
		t.Fatalf("expected agent design mode, got %q", parsed.Run.Mode)
	}
	if len(parsed.ToolResults) != 1 || parsed.ToolResults[0].Name != "inspect_agent_design" {
		t.Fatalf("expected inspect_agent_design tool, got %+v", parsed.ToolResults)
	}
	if len(parsed.Run.Insights) == 0 || len(parsed.Run.Suggestions) == 0 {
		t.Fatalf("expected insights and suggestions, got %+v", parsed.Run)
	}
	if !strings.Contains(parsed.Reply, "智能体工作台") {
		t.Fatalf("expected design reply, got %q", parsed.Reply)
	}
}

func TestAdminAgentChatPreviewsResourceRegistry(t *testing.T) {
	stateFile := writeInstalledState(t, "Test Admin", "admin", "secret123")
	server := httptest.NewServer(NewRouter(RouterOptions{InstallStateFile: stateFile}))
	defer server.Close()

	client := server.Client()
	client.CheckRedirect = func(req *http.Request, via []*http.Request) error {
		return http.ErrUseLastResponse
	}
	sessionCookie := loginTestAdmin(t, client, server.URL)

	body := bytes.NewBufferString(`{"message":"查看资源模型和 AI 工具生成"}`)
	req, err := http.NewRequest(http.MethodPost, server.URL+"/moyi-7k3x9-admin/ai/chat", body)
	if err != nil {
		t.Fatalf("create resource registry request: %v", err)
	}
	req.Header.Set("Content-Type", "application/json")
	req.AddCookie(sessionCookie)
	resp, err := client.Do(req)
	if err != nil {
		t.Fatalf("POST AI chat failed: %v", err)
	}
	defer resp.Body.Close()
	if resp.StatusCode != http.StatusOK {
		t.Fatalf("expected status 200, got %d", resp.StatusCode)
	}

	var parsed agentChatResponse
	if err := json.NewDecoder(resp.Body).Decode(&parsed); err != nil {
		t.Fatalf("decode AI chat response: %v", err)
	}
	if parsed.Run.Mode != string(agentIntentPreview) {
		t.Fatalf("expected preview mode, got %q", parsed.Run.Mode)
	}
	foundModels := false
	foundTools := false
	for _, result := range parsed.ToolResults {
		if result.Table == "resource_models" && len(result.Rows) > 0 {
			foundModels = true
			if result.Rows[0]["key"] == "" {
				t.Fatalf("expected resource model key, got %+v", result.Rows[0])
			}
		}
		if result.Table == "resource_tools" && len(result.Rows) > 0 {
			foundTools = true
		}
	}
	if !foundModels || !foundTools {
		t.Fatalf("expected resource_models and resource_tools previews, got %+v", parsed.ToolResults)
	}
}

func TestAdminAgentChatRejectsWriteQuery(t *testing.T) {
	stateFile := writeInstalledState(t, "Test Admin", "admin", "secret123")
	server := httptest.NewServer(NewRouter(RouterOptions{InstallStateFile: stateFile}))
	defer server.Close()

	client := server.Client()
	client.CheckRedirect = func(req *http.Request, via []*http.Request) error {
		return http.ErrUseLastResponse
	}
	sessionCookie := loginTestAdmin(t, client, server.URL)

	body := bytes.NewBufferString(`{"message":"delete from install_state"}`)
	req, err := http.NewRequest(http.MethodPost, server.URL+"/moyi-7k3x9-admin/ai/chat", body)
	if err != nil {
		t.Fatalf("create rejected query request: %v", err)
	}
	req.Header.Set("Content-Type", "application/json")
	req.AddCookie(sessionCookie)
	resp, err := client.Do(req)
	if err != nil {
		t.Fatalf("POST AI chat failed: %v", err)
	}
	defer resp.Body.Close()

	var parsed agentChatResponse
	if err := json.NewDecoder(resp.Body).Decode(&parsed); err != nil {
		t.Fatalf("decode AI chat response: %v", err)
	}
	if len(parsed.ToolResults) != 1 || parsed.ToolResults[0].OK || !strings.Contains(parsed.ToolResults[0].Error, "只允许") {
		t.Fatalf("expected write query to be rejected, got %+v", parsed.ToolResults)
	}
}

func TestAdminExtensionsPageShowsResourceRegistry(t *testing.T) {
	stateFile := writeInstalledState(t, "Test Admin", "admin", "secret123")
	server := httptest.NewServer(NewRouter(RouterOptions{InstallStateFile: stateFile}))
	defer server.Close()

	client := server.Client()
	client.CheckRedirect = func(req *http.Request, via []*http.Request) error {
		return http.ErrUseLastResponse
	}
	sessionCookie := loginTestAdmin(t, client, server.URL)

	req, err := http.NewRequest(http.MethodGet, server.URL+"/moyi-7k3x9-admin/extensions", nil)
	if err != nil {
		t.Fatalf("create extensions request: %v", err)
	}
	req.AddCookie(sessionCookie)
	resp, err := client.Do(req)
	if err != nil {
		t.Fatalf("GET extensions failed: %v", err)
	}
	defer resp.Body.Close()
	if resp.StatusCode != http.StatusOK {
		t.Fatalf("expected status 200, got %d", resp.StatusCode)
	}
	body, err := io.ReadAll(resp.Body)
	if err != nil {
		t.Fatalf("read extensions body: %v", err)
	}
	html := string(body)
	for _, expected := range []string{"能力扩展", "插件扩展包", "资源模型", "AI 工具生成", "core.admin", "admin_users", "resource.admin_users.preview", "/extensions/export"} {
		if !strings.Contains(html, expected) {
			t.Fatalf("expected extensions page to contain %q", expected)
		}
	}

	exportReq, err := http.NewRequest(http.MethodGet, server.URL+"/moyi-7k3x9-admin/extensions/export", nil)
	if err != nil {
		t.Fatalf("create extensions export request: %v", err)
	}
	exportReq.AddCookie(sessionCookie)
	exportResp, err := client.Do(exportReq)
	if err != nil {
		t.Fatalf("GET extensions export failed: %v", err)
	}
	defer exportResp.Body.Close()
	if exportResp.StatusCode != http.StatusOK {
		t.Fatalf("expected extensions export status 200, got %d", exportResp.StatusCode)
	}
	exportBody, err := io.ReadAll(exportResp.Body)
	if err != nil {
		t.Fatalf("read extensions export: %v", err)
	}
	exportText := string(exportBody)
	for _, expected := range []string{"插件扩展包", "资源模型", "资源工具", "core.admin", "resource.admin_users.preview"} {
		if !strings.Contains(exportText, expected) {
			t.Fatalf("expected extensions export to contain %q, got %s", expected, exportText)
		}
	}
}

func TestAdminMenuPagesExposeClosedLoopActions(t *testing.T) {
	stateFile := writeInstalledState(t, "Test Admin", "admin", "secret123")
	server := httptest.NewServer(NewRouter(RouterOptions{InstallStateFile: stateFile, DisableTaskWorker: true}))
	defer server.Close()

	client := server.Client()
	client.CheckRedirect = func(req *http.Request, via []*http.Request) error {
		return http.ErrUseLastResponse
	}
	sessionCookie := loginTestAdmin(t, client, server.URL)
	pages := []struct {
		path     string
		markers  []string
		describe string
	}{
		{path: "/workspace", describe: "工作台", markers: []string{"快捷入口", "基础服务盘点", "AI 智能体配置"}},
		{path: "/foundation", describe: "基础服务", markers: []string{"基础服务迁移盘点", "Go 端现状", "下一步"}},
		{path: "/data-sources", describe: "数据源", markers: []string{"保存数据源", "探测能力", "数据源列表"}},
		{path: "/extensions", describe: "能力扩展", markers: []string{"插件扩展包", "AI 工具生成", "导出清单"}},
		{path: "/ai", describe: "AI 智能体", markers: []string{"智能体工作台", "/ai/chat", "最近运行"}},
		{path: "/wechat-agent", describe: "微信 Agent", markers: []string{"微信 Agent 通道", "聊天记录", "新增微信 Agent"}},
		{path: "/wechat-agent/messages", describe: "微信记录", markers: []string{"微信 Agent 聊天记录", "导出 CSV", "通道管理"}},
		{path: "/users", describe: "用户权限", markers: []string{"新增管理员", "管理员账号", "后台会话"}},
		{path: "/settings", describe: "系统设置", markers: []string{"保存基础信息", "保存并检查 AI", "保存自动队列设置"}},
		{path: "/files", describe: "文件管理", markers: []string{"上传文件", "文件列表", "存储概览"}},
		{path: "/tasks", describe: "后台任务", markers: []string{"创建任务", "自动执行", "任务列表"}},
		{path: "/notifications", describe: "通知事件", markers: []string{"通知事件", "通知设置", "通知概览"}},
		{path: "/audit", describe: "审计日志", markers: []string{"审计事件", "导出 CSV", "筛选日志"}},
	}
	for _, page := range pages {
		req, err := http.NewRequest(http.MethodGet, server.URL+"/moyi-7k3x9-admin"+page.path, nil)
		if err != nil {
			t.Fatalf("create %s request: %v", page.describe, err)
		}
		req.AddCookie(sessionCookie)
		resp, err := client.Do(req)
		if err != nil {
			t.Fatalf("GET %s failed: %v", page.describe, err)
		}
		body, err := io.ReadAll(resp.Body)
		resp.Body.Close()
		if err != nil {
			t.Fatalf("read %s response: %v", page.describe, err)
		}
		if resp.StatusCode != http.StatusOK {
			t.Fatalf("expected %s status 200, got %d", page.describe, resp.StatusCode)
		}
		html := string(body)
		for _, marker := range page.markers {
			if !strings.Contains(html, marker) {
				t.Fatalf("expected %s page to contain closed-loop marker %q, got %s", page.describe, marker, html)
			}
		}
	}
}

func TestAdminWorkspaceRequiresLoginAfterInitialization(t *testing.T) {
	stateFile := writeInstalledState(t, "Test Admin", "admin", "secret123")
	server := httptest.NewServer(NewRouter(RouterOptions{InstallStateFile: stateFile}))
	defer server.Close()

	client := server.Client()
	client.CheckRedirect = func(req *http.Request, via []*http.Request) error {
		return http.ErrUseLastResponse
	}

	resp, err := client.Get(server.URL + "/moyi-7k3x9-admin/workspace")
	if err != nil {
		t.Fatalf("GET hidden workspace failed: %v", err)
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusFound {
		t.Fatalf("expected status 302, got %d", resp.StatusCode)
	}
	if location := resp.Header.Get("Location"); location != "/moyi-7k3x9-admin/login" {
		t.Fatalf("expected redirect to hidden login, got %q", location)
	}
}

func TestPlainAdminPathIsNotFound(t *testing.T) {
	server := httptest.NewServer(NewRouter(RouterOptions{}))
	defer server.Close()

	resp, err := http.Get(server.URL + "/admin")
	if err != nil {
		t.Fatalf("GET /admin failed: %v", err)
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusNotFound {
		t.Fatalf("expected status 404, got %d", resp.StatusCode)
	}
}

func TestHealthHandler(t *testing.T) {
	server := httptest.NewServer(NewRouter(RouterOptions{}))
	defer server.Close()

	resp, err := http.Get(server.URL + "/healthz")
	if err != nil {
		t.Fatalf("GET /healthz failed: %v", err)
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		t.Fatalf("expected status 200, got %d", resp.StatusCode)
	}

	var body map[string]string
	if err := json.NewDecoder(resp.Body).Decode(&body); err != nil {
		t.Fatalf("decode response: %v", err)
	}
	if body["status"] != "ok" {
		t.Fatalf("expected status ok, got %q", body["status"])
	}
}

func TestStaticAssets(t *testing.T) {
	server := httptest.NewServer(NewRouter(RouterOptions{}))
	defer server.Close()

	resp, err := http.Get(server.URL + "/assets/css/admin-foundation.css")
	if err != nil {
		t.Fatalf("GET admin foundation css failed: %v", err)
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		t.Fatalf("expected status 200, got %d", resp.StatusCode)
	}
	body, err := io.ReadAll(resp.Body)
	if err != nil {
		t.Fatalf("read response: %v", err)
	}
	if !strings.Contains(string(body), "--primary-gradient") {
		t.Fatal("expected admin foundation css")
	}
	if !strings.Contains(string(body), "prefers-color-scheme: dark") {
		t.Fatal("expected admin foundation dark mode styles")
	}
}

func TestInstallWizardAsset(t *testing.T) {
	server := httptest.NewServer(NewRouter(RouterOptions{}))
	defer server.Close()

	resp, err := http.Get(server.URL + "/assets/js/install-wizard.js")
	if err != nil {
		t.Fatalf("GET install wizard js failed: %v", err)
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		t.Fatalf("expected status 200, got %d", resp.StatusCode)
	}
	body, err := io.ReadAll(resp.Body)
	if err != nil {
		t.Fatalf("read response: %v", err)
	}
	if !strings.Contains(string(body), "checkDatabase") {
		t.Fatal("expected install wizard script")
	}
	if !strings.Contains(string(body), "checkAI") {
		t.Fatal("expected AI check script")
	}
	if strings.Contains(string(body), "new FormData(form)") {
		t.Fatal("database check should not submit the whole install form")
	}
	if !strings.Contains(string(body), "databaseFormData") {
		t.Fatal("expected database-only form data helper")
	}
	if !strings.Contains(string(body), "URLSearchParams") {
		t.Fatal("expected urlencoded database check payload")
	}
	if !strings.Contains(string(body), "aiFormData") {
		t.Fatal("expected AI-only form data helper")
	}
	if !strings.Contains(string(body), "installWizard") {
		t.Fatal("expected install wizard ready marker")
	}
}

func writeInstalledState(t *testing.T, siteName string, username string, password string) string {
	t.Helper()

	salt, hash, err := hashPassword(password)
	if err != nil {
		t.Fatalf("hash password: %v", err)
	}

	stateFile := filepath.Join(t.TempDir(), "install_state.json")
	err = newInstallStore(stateFile).Save(installState{
		Initialized:        true,
		SiteName:           siteName,
		AdminEntry:         "/moyi-7k3x9-admin",
		AdminUser:          username,
		DebugLoginPassword: password,
		Database:           defaultDatabaseConfig(),
		PasswordSalt:       salt,
		PasswordHash:       hash,
		InstalledAt:        time.Now().UTC(),
	})
	if err != nil {
		t.Fatalf("save install state: %v", err)
	}
	return stateFile
}

func loginTestAdmin(t *testing.T, client *http.Client, serverURL string) *http.Cookie {
	t.Helper()

	resp, err := client.PostForm(serverURL+"/moyi-7k3x9-admin/login", url.Values{
		"username": {"admin"},
		"password": {"secret123"},
	})
	if err != nil {
		t.Fatalf("POST hidden admin login failed: %v", err)
	}
	defer resp.Body.Close()
	if resp.StatusCode != http.StatusFound {
		t.Fatalf("expected login status 302, got %d", resp.StatusCode)
	}
	for _, cookie := range resp.Cookies() {
		if cookie.Name == adminSessionCookie {
			return cookie
		}
	}
	t.Fatal("expected session cookie")
	return nil
}

func postFormWithCookie(t *testing.T, client *http.Client, target string, cookie *http.Cookie, values url.Values) (*http.Response, error) {
	t.Helper()
	req, err := http.NewRequest(http.MethodPost, target, strings.NewReader(values.Encode()))
	if err != nil {
		return nil, err
	}
	req.Header.Set("Content-Type", "application/x-www-form-urlencoded")
	req.AddCookie(cookie)
	return client.Do(req)
}

func postMultipartWithCookie(t *testing.T, client *http.Client, target string, cookie *http.Cookie, filename string, content []byte) (*http.Response, error) {
	t.Helper()
	var body bytes.Buffer
	writer := multipart.NewWriter(&body)
	part, err := writer.CreateFormFile("files", filename)
	if err != nil {
		return nil, err
	}
	if _, err := part.Write(content); err != nil {
		return nil, err
	}
	if err := writer.Close(); err != nil {
		return nil, err
	}
	req, err := http.NewRequest(http.MethodPost, target, &body)
	if err != nil {
		return nil, err
	}
	req.Header.Set("Content-Type", writer.FormDataContentType())
	req.AddCookie(cookie)
	return client.Do(req)
}

func findUploadedFileForTest(t *testing.T, root string) string {
	t.Helper()
	var found string
	err := filepath.WalkDir(root, func(path string, entry os.DirEntry, err error) error {
		if err != nil || entry.IsDir() || found != "" {
			return err
		}
		relative, err := filepath.Rel(root, path)
		if err != nil {
			return err
		}
		found = filepath.ToSlash(relative)
		return nil
	})
	if err != nil {
		t.Fatalf("walk upload dir: %v", err)
	}
	if found == "" {
		t.Fatal("expected uploaded file")
	}
	return found
}

func isGeneratedAdminEntry(entry string) bool {
	if !strings.HasPrefix(entry, "/moyi-") || !strings.HasSuffix(entry, "-admin") {
		return false
	}
	token := strings.TrimSuffix(strings.TrimPrefix(entry, "/moyi-"), "-admin")
	if len(token) != 12 {
		return false
	}
	_, err := hex.DecodeString(token)
	return err == nil
}
