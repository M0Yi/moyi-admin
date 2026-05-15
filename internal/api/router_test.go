package api

import (
	"archive/zip"
	"bytes"
	"database/sql"
	"encoding/hex"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"mime/multipart"
	"net"
	"net/http"
	"net/http/httptest"
	"net/url"
	"os"
	"path/filepath"
	"strings"
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
	server := httptest.NewServer(NewRouter(RouterOptions{InstallStateFile: stateFile}))
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
	listener, err := net.Listen("tcp", "127.0.0.1:0")
	if err != nil {
		t.Fatalf("listen test database port: %v", err)
	}
	defer listener.Close()
	go func() {
		conn, err := listener.Accept()
		if err == nil {
			_ = conn.Close()
		}
	}()
	host, port, err := net.SplitHostPort(listener.Addr().String())
	if err != nil {
		t.Fatalf("split listener address: %v", err)
	}

	stateFile := filepath.Join(t.TempDir(), "install_state.json")
	server := httptest.NewServer(NewRouter(RouterOptions{InstallStateFile: stateFile}))
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
		"db_driver":             {"mysql"},
		"db_host":               {host},
		"db_port":               {port},
		"db_name":               {"moyi_admin"},
		"db_username":           {"root"},
		"db_password":           {"rootpass"},
		"db_ssl_mode":           {"disable"},
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
	if state.Database.Driver != "mysql" || state.Database.Database != "moyi_admin" {
		t.Fatalf("unexpected database config: %+v", state.Database)
	}
	if state.AI.Provider != "bailian" || state.AI.ChatModel != "qwen-plus" || state.AI.APIKey != "sk-test-bailian" {
		t.Fatalf("unexpected AI config: %+v", state.AI)
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
		System: defaultSystemConfig(),
		Storage: storageConfig{
			Driver:                   "local",
			LocalPath:                filepath.Join(dataDir, "uploads"),
			PublicURL:                "/uploads",
			MaxFileSizeMB:            64,
			AllowedExtensions:        ".pdf,.xlsx",
			AgentExportRetentionDays: 15,
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
	assertCount("admin_users", 2, `SELECT COUNT(*) FROM admin_users WHERE username IN (?, ?)`, "admin", "ops_user")
	assertCount("admin_roles", 3, `SELECT COUNT(*) FROM admin_roles`)
	assertCount("admin_menus", 8, `SELECT COUNT(*) FROM admin_menus`)
	assertCount("admin_permissions", 7, `SELECT COUNT(*) FROM admin_permissions`)
	assertCount("data_sources", 1, `SELECT COUNT(*) FROM data_sources WHERE name = ? AND schema_summary = ?`, "business_main", "orders 订单表")
	assertCount("audit_events", 1, `SELECT COUNT(*) FROM audit_events WHERE category = ? AND action = ?`, "settings", "save_storage")
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
	events, err := store.ListAuditEvents(5)
	if err != nil {
		t.Fatalf("list audit events: %v", err)
	}
	if len(events) != 1 || events[0].Category != "settings" || events[0].Action != "save_storage" {
		t.Fatalf("expected audit event loaded from sqlite table, got %+v", events)
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
	server := httptest.NewServer(NewRouter(RouterOptions{InstallStateFile: stateFile}))
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
	for _, expected := range []string{"基础信息", "存储设置", "本地存储目录", "data/uploads", "/settings/storage"} {
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
	opsResp.Body.Close()
	if opsResp.StatusCode != http.StatusFound {
		t.Fatalf("expected ops login redirect, got %d", opsResp.StatusCode)
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
	for _, expected := range []string{"新增管理员", "ops_user", "运维管理员", "菜单与权限", "agent.sql.select"} {
		if !strings.Contains(pageText, expected) {
			t.Fatalf("expected users page to contain %q", expected)
		}
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
		"site_name": {"Moyi Ops"},
		"timezone":  {"UTC"},
		"locale":    {"en-US"},
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
	for _, row := range parsed.ToolResults[0].Rows {
		if row["name"] == "admin_menus" {
			foundMenus = true
		}
		if row["name"] == "admin_users" && row["type"] == "metadata_table" {
			foundMetadataType = true
		}
	}
	if !foundMenus || !foundMetadataType {
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
	if !strings.Contains(fileText, "username") || !strings.Contains(fileText, "admin") || !strings.Contains(fileText, "超级管理员") {
		t.Fatalf("expected CSV admin user content, got %q", fileText)
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
	if !strings.Contains(worksheet, "admin_users") || !strings.Contains(worksheet, "username") || !strings.Contains(worksheet, "超级管理员") {
		t.Fatalf("expected xlsx worksheet content, got %q", worksheet)
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
	for _, expected := range []string{"username", "role", "status"} {
		if !strings.Contains(worksheet, expected) {
			t.Fatalf("expected xlsx worksheet to include %q, got %q", expected, worksheet)
		}
	}
	if strings.Contains(worksheet, "display_name") || strings.Contains(worksheet, "created_at") {
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
		Initialized:  true,
		SiteName:     siteName,
		AdminEntry:   "/moyi-7k3x9-admin",
		AdminUser:    username,
		Database:     defaultDatabaseConfig(),
		PasswordSalt: salt,
		PasswordHash: hash,
		InstalledAt:  time.Now().UTC(),
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
