package api

import (
	"bytes"
	"context"
	"crypto/hmac"
	"crypto/rand"
	"crypto/sha256"
	"crypto/subtle"
	"database/sql"
	"encoding/base64"
	"encoding/csv"
	"encoding/hex"
	"encoding/json"
	"errors"
	"fmt"
	"html/template"
	"io"
	"log/slog"
	"mime"
	"mime/multipart"
	"net"
	"net/http"
	"net/url"
	"os"
	"path/filepath"
	"sort"
	"strconv"
	"strings"
	"sync"
	"time"

	mysql "github.com/go-sql-driver/mysql"
)

const (
	adminSessionCookie   = "moyi_admin_session"
	adminSessionTTL      = 12 * time.Hour
	defaultAIProvider    = "bailian"
	defaultAIBaseURL     = "https://dashscope.aliyuncs.com/compatible-mode/v1"
	defaultAIChatModel   = "qwen-plus"
	defaultAIImageModel  = "qwen-image-2.0-pro"
	defaultAITestTimeout = 12 * time.Second
)

var aiCheckHTTPClient = &http.Client{Timeout: defaultAITestTimeout}

type adminServer struct {
	basePath                  string
	env                       string
	username                  string
	password                  string
	sessionSecret             string
	store                     *installStore
	auditMu                   sync.Mutex
	taskMu                    sync.Mutex
	agentChannelMu            sync.Mutex
	agentChannelLastIdleLogAt time.Time
}

func newAdminServer(entry string, username string, password string, sessionSecret string, stateFile string, env string) *adminServer {
	if username == "" {
		username = "admin"
	}
	if password == "" {
		password = "admin"
	}
	if sessionSecret == "" {
		sessionSecret = "moyi-admin-dev-session-secret"
	}
	if strings.TrimSpace(env) == "" {
		env = "development"
	}

	return &adminServer{
		basePath:      entry,
		env:           strings.ToLower(strings.TrimSpace(env)),
		username:      username,
		password:      password,
		sessionSecret: sessionSecret,
		store:         newInstallStore(stateFile),
	}
}

func (s *adminServer) get(w http.ResponseWriter, r *http.Request) {
	if r.URL.Path == "/" {
		s.home(w, r)
		return
	}
	state, err := s.store.Load()
	if err != nil {
		http.Error(w, "读取初始化状态失败", http.StatusInternalServerError)
		return
	}
	if state.Initialized && !adminPathMatches(r.URL.Path, s.adminEntryForState(state)) {
		data := publicHomeDataFromState(state)
		if s.debugMode() {
			debugPassword := s.debugLoginPassword(state)
			data.AdminLoginPath = s.adminEntryForState(state) + "/login"
			data.DebugLoginEnabled = debugPassword != ""
			data.DebugUsername = state.AdminUser
			data.DebugPassword = debugPassword
		}
		if renderPublicSiteRoute(w, r, data) {
			return
		}
	}
	s.adminRoute(w, r)
}

func (s *adminServer) post(w http.ResponseWriter, r *http.Request) {
	if r.URL.Path == "/" {
		s.installSubmit(w, r)
		return
	}
	s.adminRoute(w, r)
}

func (s *adminServer) adminRoute(w http.ResponseWriter, r *http.Request) {
	state, err := s.store.Load()
	if err != nil {
		http.Error(w, "读取初始化状态失败", http.StatusInternalServerError)
		return
	}

	entry := s.adminEntryForState(state)
	if !adminPathMatches(r.URL.Path, entry) {
		http.NotFound(w, r)
		return
	}

	subpath := adminSubpath(r.URL.Path, entry)
	switch {
	case r.Method == http.MethodGet && subpath == "/":
		s.entry(w, r)
	case r.Method == http.MethodGet && subpath == "/install":
		s.installPage(w, r)
	case r.Method == http.MethodPost && subpath == "/install":
		s.installSubmit(w, r)
	case r.Method == http.MethodGet && subpath == "/login":
		s.loginPage(w, r)
	case r.Method == http.MethodPost && subpath == "/login":
		s.loginSubmit(w, r)
	case r.Method == http.MethodPost && subpath == "/logout":
		s.logout(w, r)
	case r.Method == http.MethodGet && subpath == "/workspace":
		s.adminPage(w, r, "dashboard")
	case r.Method == http.MethodGet && subpath == "/foundation":
		s.adminPage(w, r, "foundation")
	case r.Method == http.MethodGet && subpath == "/data-sources":
		s.adminPage(w, r, "data-sources")
	case r.Method == http.MethodGet && subpath == "/extensions":
		s.adminPage(w, r, "extensions")
	case r.Method == http.MethodGet && subpath == "/extensions/export":
		s.extensionsExport(w, r)
	case r.Method == http.MethodPost && subpath == "/data-sources/save":
		s.dataSourceSaveSubmit(w, r)
	case r.Method == http.MethodPost && subpath == "/data-sources/test":
		s.dataSourceTestSubmit(w, r)
	case r.Method == http.MethodPost && subpath == "/data-sources/delete":
		s.dataSourceDeleteSubmit(w, r)
	case r.Method == http.MethodGet && subpath == "/ai":
		s.adminPage(w, r, "ai")
	case r.Method == http.MethodGet && subpath == "/ai/tasks":
		s.adminPage(w, r, "ai-tasks")
	case r.Method == http.MethodGet && subpath == "/ai/runs":
		s.adminPage(w, r, "ai-runs")
	case r.Method == http.MethodGet && subpath == "/ai/capabilities":
		s.adminPage(w, r, "ai-capabilities")
	case r.Method == http.MethodPost && subpath == "/ai/chat":
		s.aiChat(w, r)
	case r.Method == http.MethodGet && subpath == "/wechat-agent":
		s.adminPage(w, r, "wechat-agent")
	case r.Method == http.MethodGet && subpath == "/wechat-agent/messages":
		s.adminPage(w, r, "wechat-agent-messages")
	case r.Method == http.MethodGet && subpath == "/wechat-agent/messages/export":
		s.agentWeChatMessagesExport(w, r)
	case r.Method == http.MethodPost && subpath == "/wechat-agent/channels":
		s.agentWeChatChannelSubmit(w, r)
	case r.Method == http.MethodPost && subpath == "/ai/channels/wechat":
		s.agentWeChatChannelSubmit(w, r)
	case r.Method == http.MethodGet && strings.HasPrefix(subpath, "/ai/files/"):
		s.aiFileDownload(w, r)
	case r.Method == http.MethodGet && subpath == "/users":
		s.adminPage(w, r, "users")
	case r.Method == http.MethodGet && subpath == "/users/accounts":
		s.adminPage(w, r, "users")
	case r.Method == http.MethodGet && subpath == "/users/groups":
		s.adminPage(w, r, "user-groups")
	case r.Method == http.MethodGet && subpath == "/users/sessions":
		s.adminPage(w, r, "user-sessions")
	case r.Method == http.MethodGet && subpath == "/users/permissions":
		s.adminPage(w, r, "user-permissions")
	case r.Method == http.MethodPost && subpath == "/users/save":
		s.adminUserSaveSubmit(w, r)
	case r.Method == http.MethodPost && subpath == "/users/roles/save":
		s.adminRoleSaveSubmit(w, r)
	case r.Method == http.MethodPost && subpath == "/users/toggle":
		s.adminUserToggleSubmit(w, r)
	case r.Method == http.MethodPost && subpath == "/users/delete":
		s.adminUserDeleteSubmit(w, r)
	case r.Method == http.MethodPost && subpath == "/users/sessions/revoke":
		s.adminSessionRevokeSubmit(w, r)
	case r.Method == http.MethodGet && subpath == "/settings":
		s.adminPage(w, r, "settings")
	case r.Method == http.MethodPost && subpath == "/settings/system":
		s.settingsSystemSubmit(w, r)
	case r.Method == http.MethodPost && subpath == "/settings/storage":
		s.settingsStorageSubmit(w, r)
	case r.Method == http.MethodPost && subpath == "/settings/ai":
		s.settingsAISubmit(w, r)
	case r.Method == http.MethodPost && subpath == "/settings/security":
		s.settingsSecuritySubmit(w, r)
	case r.Method == http.MethodPost && subpath == "/settings/notifications":
		s.settingsNotificationsSubmit(w, r)
	case r.Method == http.MethodPost && subpath == "/settings/notifications/test":
		s.settingsNotificationTestSubmit(w, r)
	case r.Method == http.MethodPost && subpath == "/settings/tasks":
		s.backgroundTaskSettingsSubmit(w, r)
	case r.Method == http.MethodGet && subpath == "/files":
		s.adminPage(w, r, "files")
	case r.Method == http.MethodPost && subpath == "/files/upload":
		s.fileUploadSubmit(w, r)
	case r.Method == http.MethodPost && subpath == "/files/delete":
		s.fileDeleteSubmit(w, r)
	case r.Method == http.MethodGet && strings.HasPrefix(subpath, "/files/preview/"):
		s.fileServe(w, r, false)
	case r.Method == http.MethodGet && strings.HasPrefix(subpath, "/files/download/"):
		s.fileServe(w, r, true)
	case r.Method == http.MethodGet && subpath == "/tasks":
		s.adminPage(w, r, "tasks")
	case r.Method == http.MethodPost && subpath == "/tasks/enqueue":
		s.backgroundTaskEnqueueSubmit(w, r)
	case r.Method == http.MethodPost && subpath == "/tasks/settings":
		s.backgroundTaskSettingsSubmit(w, r)
	case r.Method == http.MethodPost && subpath == "/tasks/run":
		s.backgroundTaskRunSubmit(w, r)
	case r.Method == http.MethodPost && subpath == "/tasks/run-all":
		s.backgroundTaskRunAllSubmit(w, r)
	case r.Method == http.MethodPost && subpath == "/tasks/retry":
		s.backgroundTaskRetrySubmit(w, r)
	case r.Method == http.MethodPost && subpath == "/tasks/cancel":
		s.backgroundTaskCancelSubmit(w, r)
	case r.Method == http.MethodGet && subpath == "/notifications":
		s.adminPage(w, r, "notifications")
	case r.Method == http.MethodGet && subpath == "/audit/export":
		s.auditExport(w, r)
	case r.Method == http.MethodGet && subpath == "/audit":
		s.adminPage(w, r, "audit")
	default:
		http.NotFound(w, r)
	}
}

func (s *adminServer) home(w http.ResponseWriter, r *http.Request) {
	if r.URL.Path != "/" {
		http.NotFound(w, r)
		return
	}

	state, err := s.store.Load()
	if err != nil {
		http.Error(w, "读取初始化状态失败", http.StatusInternalServerError)
		return
	}
	if !state.Initialized {
		s.renderInstall(w, http.StatusOK, installPageData{
			Action:      "/",
			SiteName:    "Moyi Admin",
			Username:    s.username,
			AdminEntry:  "初始化完成后自动生成",
			Database:    defaultDatabaseConfig(),
			AI:          defaultAIConfig(),
			RootInstall: true,
		})
		return
	}

	data := publicHomeDataFromState(state)
	if s.debugMode() {
		debugPassword := s.debugLoginPassword(state)
		data.AdminLoginPath = s.adminEntryForState(state) + "/login"
		data.DebugLoginEnabled = debugPassword != ""
		data.DebugUsername = state.AdminUser
		data.DebugPassword = debugPassword
	}
	renderPublicHome(w, data)
}

func (s *adminServer) entry(w http.ResponseWriter, r *http.Request) {
	state, err := s.store.Load()
	if err != nil {
		http.Error(w, "读取初始化状态失败", http.StatusInternalServerError)
		return
	}
	entry := s.adminEntryForState(state)
	if !state.Initialized {
		http.Redirect(w, r, s.basePath+"/install", http.StatusFound)
		return
	}
	if s.isAuthenticated(r) {
		http.Redirect(w, r, adminLandingPath(state, s.sessionUsername(r), entry), http.StatusFound)
		return
	}
	http.Redirect(w, r, entry+"/login", http.StatusFound)
}

func (s *adminServer) installPage(w http.ResponseWriter, r *http.Request) {
	state, err := s.store.Load()
	if err != nil {
		http.Error(w, "读取初始化状态失败", http.StatusInternalServerError)
		return
	}
	if state.Initialized {
		s.renderInstalled(w, state)
		return
	}

	s.renderInstall(w, http.StatusOK, installPageData{
		Action:     s.basePath + "/install",
		SiteName:   "Moyi Admin",
		Username:   s.username,
		AdminEntry: "初始化完成后自动生成",
		Database:   defaultDatabaseConfig(),
		AI:         defaultAIConfig(),
	})
}

func (s *adminServer) installSubmit(w http.ResponseWriter, r *http.Request) {
	state, err := s.store.Load()
	if err != nil {
		http.Error(w, "读取初始化状态失败", http.StatusInternalServerError)
		return
	}
	if state.Initialized {
		s.renderInstalled(w, state)
		return
	}

	if err := r.ParseForm(); err != nil {
		s.renderInstall(w, http.StatusBadRequest, installPageData{
			Action:     r.URL.Path,
			AdminEntry: s.basePath,
			Error:      "初始化请求格式不正确，请重新提交。",
		})
		return
	}

	data := installPageData{
		Action:      r.URL.Path,
		SiteName:    strings.TrimSpace(r.FormValue("site_name")),
		Username:    strings.TrimSpace(r.FormValue("username")),
		AdminEntry:  "初始化完成后自动生成",
		Database:    databaseConfigFromForm(r),
		AI:          aiConfigFromForm(r),
		RootInstall: r.URL.Path == "/",
	}
	password := r.FormValue("password")
	confirmation := r.FormValue("password_confirmation")
	if err := validateInstallForm(data.SiteName, data.Username, password, confirmation, data.Database, data.AI); err != nil {
		data.Error = err.Error()
		data.Database.Password = ""
		data.AI.APIKey = ""
		s.renderInstall(w, http.StatusBadRequest, data)
		return
	}
	check := checkDatabaseConfig(data.Database)
	if !check.OK {
		data.Error = "数据库检查未通过：" + check.Message
		data.Database.Password = ""
		data.AI.APIKey = ""
		s.renderInstall(w, http.StatusBadRequest, data)
		return
	}
	aiCheck := checkAIConfig(r.Context(), data.AI)
	if !aiCheck.OK {
		data.Error = "AI 配置检查未通过：" + aiCheck.Message
		data.Database.Password = ""
		data.AI.APIKey = ""
		s.renderInstall(w, http.StatusBadRequest, data)
		return
	}

	salt, hash, err := hashPassword(password)
	if err != nil {
		http.Error(w, "生成管理员密码失败", http.StatusInternalServerError)
		return
	}
	adminEntry, err := generateAdminEntry()
	if err != nil {
		http.Error(w, "生成随机后台入口失败", http.StatusInternalServerError)
		return
	}

	state = installState{
		Initialized:        true,
		SiteName:           data.SiteName,
		AdminEntry:         adminEntry,
		AdminUser:          data.Username,
		DebugLoginPassword: "",
		Database:           data.Database.sanitized(),
		AI:                 data.AI.sanitized(),
		System:             defaultSystemConfig(),
		Storage:            defaultStorageConfig(),
		TaskWorker:         defaultTaskWorkerConfig(),
		Access:             defaultAccessConfig(),
		PasswordSalt:       salt,
		PasswordHash:       hash,
		InstalledAt:        time.Now().UTC(),
	}
	if s.debugMode() {
		state.DebugLoginPassword = password
	}
	if err := s.store.Save(state); err != nil {
		http.Error(w, "保存初始化状态失败", http.StatusInternalServerError)
		return
	}

	s.recordAuditEvent(r, state, auditEventInput{
		Category:   "system",
		Action:     "系统初始化",
		Actor:      state.AdminUser,
		Detail:     "创建站点、超级管理员、随机后台入口与默认基础配置",
		StatusCode: http.StatusFound,
	})
	http.Redirect(w, r, adminEntry+"/login?installed=1", http.StatusFound)
}

func (s *adminServer) checkDatabase(w http.ResponseWriter, r *http.Request) {
	state, err := s.store.Load()
	if err != nil {
		writeJSON(w, http.StatusInternalServerError, map[string]any{
			"ok":      false,
			"message": "读取初始化状态失败",
		})
		return
	}
	if state.Initialized {
		writeJSON(w, http.StatusConflict, map[string]any{
			"ok":      false,
			"message": "系统已经初始化，无需重复检查安装数据库",
		})
		return
	}

	if err := r.ParseForm(); err != nil {
		writeJSON(w, http.StatusBadRequest, map[string]any{
			"ok":      false,
			"message": "数据库检查请求格式不正确",
		})
		return
	}

	result := checkDatabaseConfig(databaseConfigFromForm(r))
	statusCode := http.StatusOK
	if !result.OK {
		statusCode = http.StatusBadRequest
	}
	writeJSON(w, statusCode, result)
}

func (s *adminServer) checkAI(w http.ResponseWriter, r *http.Request) {
	state, err := s.store.Load()
	if err != nil {
		writeJSON(w, http.StatusInternalServerError, map[string]any{
			"ok":      false,
			"message": "读取初始化状态失败",
		})
		return
	}
	if state.Initialized {
		writeJSON(w, http.StatusConflict, map[string]any{
			"ok":      false,
			"message": "系统已经初始化，无需重复检查安装 AI 配置",
		})
		return
	}

	if err := r.ParseForm(); err != nil {
		writeJSON(w, http.StatusBadRequest, map[string]any{
			"ok":      false,
			"message": "AI 配置检查请求格式不正确",
		})
		return
	}

	result := checkAIConfig(r.Context(), aiConfigFromForm(r))
	statusCode := http.StatusOK
	if !result.OK {
		statusCode = http.StatusBadRequest
	}
	writeJSON(w, statusCode, result)
}

func (s *adminServer) loginPage(w http.ResponseWriter, r *http.Request) {
	state, err := s.store.Load()
	if err != nil {
		http.Error(w, "读取初始化状态失败", http.StatusInternalServerError)
		return
	}
	if !state.Initialized {
		http.Redirect(w, r, s.basePath+"/install", http.StatusFound)
		return
	}
	entry := s.adminEntryForState(state)
	if s.isAuthenticated(r) {
		http.Redirect(w, r, adminLandingPath(state, s.sessionUsername(r), entry), http.StatusFound)
		return
	}
	debugPassword := s.debugLoginPassword(state)
	s.renderLogin(w, http.StatusOK, loginPageData{
		Action:       entry + "/login",
		Username:     state.AdminUser,
		Password:     debugPassword,
		DebugPrefill: debugPassword != "",
		Success:      r.URL.Query().Get("installed") == "1",
	})
}

func (s *adminServer) loginSubmit(w http.ResponseWriter, r *http.Request) {
	state, err := s.store.Load()
	if err != nil {
		http.Error(w, "读取初始化状态失败", http.StatusInternalServerError)
		return
	}
	if !state.Initialized {
		http.Redirect(w, r, s.basePath+"/install", http.StatusFound)
		return
	}
	entry := s.adminEntryForState(state)

	if err := r.ParseForm(); err != nil {
		debugPassword := s.debugLoginPassword(state)
		s.renderLogin(w, http.StatusBadRequest, loginPageData{
			Action:       entry + "/login",
			Username:     state.AdminUser,
			Password:     debugPassword,
			DebugPrefill: debugPassword != "",
			Error:        "登录请求格式不正确，请重新提交。",
		})
		return
	}

	username := strings.TrimSpace(r.FormValue("username"))
	password := r.FormValue("password")
	security := state.Security.normalized()
	if s.loginLocked(state, username, requestClientIP(r), time.Now().UTC()) {
		debugPassword := s.debugLoginPassword(state)
		s.recordAuditEvent(r, state, auditEventInput{
			Category:   "login",
			Action:     "登录锁定",
			Actor:      username,
			Detail:     "失败次数超过阈值，临时拒绝登录",
			StatusCode: http.StatusTooManyRequests,
		})
		s.notifyAdminEvent(r, state, state.Notifications.normalized().EventLoginFailures, "login_lockout", "后台登录保护已触发", "账号 "+displayNotificationValue(username, "未知账号")+" / IP "+displayNotificationValue(requestClientIP(r), "未知 IP")+" 在登录保护窗口内失败过多，系统已临时拒绝登录。")
		s.renderLogin(w, http.StatusTooManyRequests, loginPageData{
			Action:       entry + "/login",
			Username:     username,
			Password:     debugPassword,
			DebugPrefill: debugPassword != "",
			Error:        "登录失败次数过多，请稍后再试。",
		})
		return
	}
	if !state.credentialsMatch(username, password) {
		debugPassword := s.debugLoginPassword(state)
		s.recordAuditEvent(r, state, auditEventInput{
			Category:   "login",
			Action:     "登录失败",
			Actor:      username,
			Detail:     "账号或密码校验未通过",
			StatusCode: http.StatusUnauthorized,
		})
		s.renderLogin(w, http.StatusUnauthorized, loginPageData{
			Action:       entry + "/login",
			Username:     username,
			Password:     debugPassword,
			DebugPrefill: debugPassword != "",
			Error:        "账号或密码错误，请检查初始化时创建的管理员账号。",
		})
		return
	}
	updatedState, changed := state.withAdminLogin(username, time.Now().UTC())
	if changed {
		state = updatedState
	}
	if s.debugMode() && strings.TrimSpace(state.DebugLoginPassword) == "" {
		state.DebugLoginPassword = password
		changed = true
	}
	if changed {
		if err := s.store.Save(state); err != nil {
			slog.Warn("save admin login state failed", "error", err)
		}
	}

	sessionTTL := security.sessionTTL()
	now := time.Now()
	expiresAt := now.Add(sessionTTL)
	sessionID := newAdminSessionID()
	sessionToken := s.createSessionToken(username, sessionID, expiresAt)
	_ = s.store.AppendAdminSession(adminSessionRecord{
		ID:        sessionID,
		Username:  username,
		IP:        requestClientIP(r),
		UserAgent: truncateAuditText(r.UserAgent(), 220),
		Status:    "active",
		CreatedAt: now.UTC(),
		ExpiresAt: expiresAt.UTC(),
	})
	http.SetCookie(w, &http.Cookie{
		Name:     adminSessionCookie,
		Value:    sessionToken,
		Path:     entry,
		HttpOnly: true,
		SameSite: http.SameSiteLaxMode,
		Expires:  expiresAt,
		MaxAge:   int(sessionTTL.Seconds()),
	})
	s.recordAuditEvent(r, state, auditEventInput{
		Category:   "login",
		Action:     "登录成功",
		Actor:      username,
		Detail:     "管理员进入后台控制台",
		StatusCode: http.StatusFound,
	})
	http.Redirect(w, r, adminLandingPath(state, username, entry), http.StatusFound)
}

func (s *adminServer) logout(w http.ResponseWriter, r *http.Request) {
	entry := s.adminEntryForRequest(r)
	if state, err := s.store.Load(); err == nil && state.Initialized {
		actor := s.sessionUsername(r)
		if actor == "" {
			actor = state.AdminUser
		}
		s.recordAuditEvent(r, state, auditEventInput{
			Category:   "login",
			Action:     "退出登录",
			Actor:      actor,
			Detail:     "管理员退出后台会话",
			StatusCode: http.StatusFound,
		})
	}
	if cookie, err := r.Cookie(adminSessionCookie); err == nil {
		s.revokeSessionToken(cookie.Value)
	}
	http.SetCookie(w, &http.Cookie{
		Name:     adminSessionCookie,
		Value:    "",
		Path:     entry,
		HttpOnly: true,
		SameSite: http.SameSiteLaxMode,
		Expires:  time.Unix(0, 0),
		MaxAge:   -1,
	})
	http.Redirect(w, r, entry+"/login", http.StatusFound)
}

func (s *adminServer) adminPage(w http.ResponseWriter, r *http.Request, active string) {
	state, entry, access, ok := s.authorizedAdminState(w, r, active, "")
	if !ok {
		return
	}

	w.Header().Set("Content-Type", "text/html; charset=utf-8")
	w.WriteHeader(http.StatusOK)
	_ = adminWorkspaceTemplate.Execute(w, s.adminPageData(state, entry, active, r.URL.Query(), access.Username, s.sessionID(r), requestPublicBaseURL(r)))
}

func (s *adminServer) settingsSystemSubmit(w http.ResponseWriter, r *http.Request) {
	state, entry, _, ok := s.authorizedAdminState(w, r, "settings", "admin.settings.manage")
	if !ok {
		return
	}
	if err := r.ParseForm(); err != nil {
		http.Redirect(w, r, entry+"/settings?error="+url.QueryEscape("系统设置请求格式不正确"), http.StatusFound)
		return
	}

	siteName := strings.TrimSpace(r.FormValue("site_name"))
	if siteName == "" {
		http.Redirect(w, r, entry+"/settings?error="+url.QueryEscape("站点名称不能为空"), http.StatusFound)
		return
	}

	before := systemSettingSnapshot(state.SiteName, state.System)
	state.SiteName = siteName
	state.System = systemConfigFromForm(r).normalized()
	if err := s.store.Save(state); err != nil {
		http.Redirect(w, r, entry+"/settings?error="+url.QueryEscape("保存系统设置失败："+err.Error()), http.StatusFound)
		return
	}
	s.recordSettingChange(r, state, "system", "保存基础信息", "更新站点名称、公共首页展示、默认语言或时区", before, systemSettingSnapshot(state.SiteName, state.System))
	s.recordAuditEvent(r, state, auditEventInput{
		Category:   "operation",
		Action:     "保存基础信息",
		Detail:     "更新站点名称、公共首页展示、默认语言或时区",
		StatusCode: http.StatusFound,
	})
	http.Redirect(w, r, entry+"/settings?saved=system", http.StatusFound)
}

func (s *adminServer) settingsStorageSubmit(w http.ResponseWriter, r *http.Request) {
	state, entry, _, ok := s.authorizedAdminState(w, r, "settings", "admin.settings.manage")
	if !ok {
		return
	}
	if err := r.ParseForm(); err != nil {
		http.Redirect(w, r, entry+"/settings?error="+url.QueryEscape("存储设置请求格式不正确"), http.StatusFound)
		return
	}

	before := state.Storage.normalized()
	storage := storageConfigFromForm(r).normalized()
	if err := validateStorageConfig(storage); err != nil {
		s.notifyAdminEvent(r, state, state.Notifications.normalized().EventStorageWarning, "storage_warning", "存储设置校验未通过", err.Error())
		http.Redirect(w, r, entry+"/settings?error="+url.QueryEscape(err.Error()), http.StatusFound)
		return
	}
	if storage.IsLocal() {
		if err := os.MkdirAll(storage.LocalPath, 0o755); err != nil {
			s.notifyAdminEvent(r, state, state.Notifications.normalized().EventStorageWarning, "storage_warning", "本地存储目录创建失败", "目录 "+storage.LocalPath+" 创建失败："+err.Error())
			http.Redirect(w, r, entry+"/settings?error="+url.QueryEscape("创建本地存储目录失败："+err.Error()), http.StatusFound)
			return
		}
	}

	state.Storage = storage
	if err := s.store.Save(state); err != nil {
		http.Redirect(w, r, entry+"/settings?error="+url.QueryEscape("保存存储设置失败："+err.Error()), http.StatusFound)
		return
	}
	s.recordSettingChange(r, state, "storage", "保存存储设置", "更新上传目录、扩展名、文件大小限制或导出保留策略", before, storage)
	s.recordAuditEvent(r, state, auditEventInput{
		Category:   "operation",
		Action:     "保存存储设置",
		Detail:     "更新上传目录、扩展名、文件大小限制或导出保留策略",
		StatusCode: http.StatusFound,
	})
	http.Redirect(w, r, entry+"/settings?saved=storage", http.StatusFound)
}

func (s *adminServer) settingsAISubmit(w http.ResponseWriter, r *http.Request) {
	state, entry, _, ok := s.authorizedAdminState(w, r, "settings", "admin.settings.manage")
	if !ok {
		return
	}
	if err := r.ParseForm(); err != nil {
		http.Redirect(w, r, entry+"/settings?error="+url.QueryEscape("AI 设置请求格式不正确"), http.StatusFound)
		return
	}

	before := redactedAISettingSnapshot(state.AI)
	ai := aiConfigFromForm(r).sanitized()
	if ai.Provider == "bailian" && strings.TrimSpace(ai.APIKey) == "" {
		ai.APIKey = strings.TrimSpace(state.AI.APIKey)
	}
	if ai.Provider == "disabled" {
		ai.APIKey = ""
	}
	check := checkAIConfig(r.Context(), ai)
	if !check.OK {
		http.Redirect(w, r, entry+"/settings?error="+url.QueryEscape("AI 配置检查未通过："+check.Message), http.StatusFound)
		return
	}

	state.AI = ai
	if err := s.store.Save(state); err != nil {
		http.Redirect(w, r, entry+"/settings?error="+url.QueryEscape("保存 AI 设置失败："+err.Error()), http.StatusFound)
		return
	}
	s.recordSettingChange(r, state, "ai", "保存 AI 设置", "更新 AI 服务商、Base URL、对话模型或图片模型", before, redactedAISettingSnapshot(state.AI))
	s.recordAuditEvent(r, state, auditEventInput{
		Category:   "operation",
		Action:     "保存 AI 设置",
		Detail:     "更新 AI 服务商、Base URL、对话模型或图片模型",
		StatusCode: http.StatusFound,
	})
	http.Redirect(w, r, entry+"/settings?saved=ai", http.StatusFound)
}

func (s *adminServer) settingsSecuritySubmit(w http.ResponseWriter, r *http.Request) {
	state, entry, ok := s.authenticatedAdminState(w, r)
	if !ok {
		return
	}
	currentUser := s.sessionUsername(r)
	if currentUser == "" {
		currentUser = state.AdminUser
	}
	if err := r.ParseForm(); err != nil {
		http.Redirect(w, r, entry+"/settings?error="+url.QueryEscape("安全设置请求格式不正确"), http.StatusFound)
		return
	}

	action := strings.TrimSpace(r.FormValue("security_action"))
	if action == "" && strings.TrimSpace(r.FormValue("current_password")) == "" && strings.TrimSpace(r.FormValue("new_password")) == "" {
		action = "policy"
	}
	if action == "policy" {
		access := adminRoleAccessForUsername(state, currentUser)
		if !access.CanViewPage("settings") || !access.HasPermission("admin.settings.manage") {
			http.Error(w, "当前管理员未获授权执行该操作", http.StatusForbidden)
			return
		}
		before := state.Security.normalized()
		security := securityConfigFromForm(r).normalized()
		if err := validateSecurityConfig(security); err != nil {
			http.Redirect(w, r, entry+"/settings?error="+url.QueryEscape(err.Error()), http.StatusFound)
			return
		}
		state.Security = security
		if err := s.store.Save(state); err != nil {
			http.Redirect(w, r, entry+"/settings?error="+url.QueryEscape("保存登录保护失败："+err.Error()), http.StatusFound)
			return
		}
		s.recordSettingChange(r, state, "security", "保存登录保护", "更新会话有效期、失败阈值和锁定窗口", before, security)
		s.recordAuditEvent(r, state, auditEventInput{
			Category:   "operation",
			Action:     "保存登录保护",
			Actor:      currentUser,
			Detail:     "更新会话有效期、失败阈值和锁定窗口",
			StatusCode: http.StatusFound,
		})
		http.Redirect(w, r, entry+"/settings?saved=security", http.StatusFound)
		return
	}

	currentPassword := r.FormValue("current_password")
	newPassword := r.FormValue("new_password")
	confirmation := r.FormValue("new_password_confirmation")
	if !state.credentialsMatch(currentUser, currentPassword) {
		http.Redirect(w, r, entry+"/settings?error="+url.QueryEscape("当前密码不正确"), http.StatusFound)
		return
	}
	if len(newPassword) < 6 {
		http.Redirect(w, r, entry+"/settings?error="+url.QueryEscape("新密码长度至少 6 位"), http.StatusFound)
		return
	}
	if newPassword != confirmation {
		http.Redirect(w, r, entry+"/settings?error="+url.QueryEscape("两次输入的新密码不一致"), http.StatusFound)
		return
	}

	salt, hash, err := hashPassword(newPassword)
	if err != nil {
		http.Redirect(w, r, entry+"/settings?error="+url.QueryEscape("生成新密码失败："+err.Error()), http.StatusFound)
		return
	}
	if strings.EqualFold(currentUser, state.AdminUser) {
		state.PasswordSalt = salt
		state.PasswordHash = hash
		if s.debugMode() {
			state.DebugLoginPassword = newPassword
		}
	} else {
		access := state.Access.normalized(state)
		index := findAdminAccountIndex(access.Users, currentUser)
		if index < 0 {
			http.Redirect(w, r, entry+"/settings?error="+url.QueryEscape("当前管理员不存在"), http.StatusFound)
			return
		}
		access.Users[index].PasswordSalt = salt
		access.Users[index].PasswordHash = hash
		access.Users[index].UpdatedAt = time.Now().UTC()
		state.Access = access.withoutBootstrap(state)
	}
	if err := s.store.Save(state); err != nil {
		http.Redirect(w, r, entry+"/settings?error="+url.QueryEscape("保存安全设置失败："+err.Error()), http.StatusFound)
		return
	}
	s.recordSettingChange(r, state, "security", "修改管理员密码", "当前管理员更新登录密码", map[string]string{"password": "redacted"}, map[string]string{"password": "changed"})
	s.recordAuditEvent(r, state, auditEventInput{
		Category:   "operation",
		Action:     "修改管理员密码",
		Actor:      currentUser,
		Detail:     "当前管理员更新登录密码",
		StatusCode: http.StatusFound,
	})
	http.Redirect(w, r, entry+"/settings?saved=security", http.StatusFound)
}

func (s *adminServer) settingsNotificationsSubmit(w http.ResponseWriter, r *http.Request) {
	state, entry, _, ok := s.authorizedAdminState(w, r, "settings", "admin.settings.manage")
	if !ok {
		return
	}
	if err := r.ParseForm(); err != nil {
		http.Redirect(w, r, entry+"/settings?error="+url.QueryEscape("通知设置请求格式不正确"), http.StatusFound)
		return
	}

	before := redactedNotificationSettingSnapshot(state.Notifications)
	notifications := notificationConfigFromForm(r).normalized()
	if notifications.Channel == "feishu" && strings.TrimSpace(notifications.FeishuSecret) == "" {
		notifications.FeishuSecret = strings.TrimSpace(state.Notifications.FeishuSecret)
	}
	if notifications.Channel != "feishu" {
		notifications.FeishuSecret = ""
	}
	if err := validateNotificationConfig(notifications); err != nil {
		http.Redirect(w, r, entry+"/settings?error="+url.QueryEscape(err.Error()), http.StatusFound)
		return
	}
	state.Notifications = notifications
	if err := s.store.Save(state); err != nil {
		http.Redirect(w, r, entry+"/settings?error="+url.QueryEscape("保存通知设置失败："+err.Error()), http.StatusFound)
		return
	}
	s.recordSettingChange(r, state, "notifications", "保存通知设置", "更新后台通知通道、接收人与触发事件", before, redactedNotificationSettingSnapshot(state.Notifications))
	s.recordAuditEvent(r, state, auditEventInput{
		Category:   "operation",
		Action:     "保存通知设置",
		Detail:     "更新后台通知通道、接收人与触发事件",
		StatusCode: http.StatusFound,
	})
	http.Redirect(w, r, entry+"/settings?saved=notifications", http.StatusFound)
}

func (s *adminServer) settingsNotificationTestSubmit(w http.ResponseWriter, r *http.Request) {
	state, entry, _, ok := s.authorizedAdminState(w, r, "settings", "admin.settings.manage")
	if !ok {
		return
	}

	result, err := s.deliverAdminNotification(r.Context(), state, "notification_test", "Moyi Admin 测试通知", "后台通知通道测试成功。如果你收到了这条消息，说明当前通知配置可用。")
	if err != nil {
		s.recordAuditEvent(r, state, auditEventInput{
			Category:   "operation",
			Action:     "测试通知",
			Detail:     "测试通知发送失败：" + err.Error(),
			StatusCode: http.StatusFound,
		})
		http.Redirect(w, r, entry+"/settings?error="+url.QueryEscape("测试通知失败："+err.Error()), http.StatusFound)
		return
	}
	s.recordAuditEvent(r, state, auditEventInput{
		Category:   "operation",
		Action:     "测试通知",
		Detail:     result.Message,
		StatusCode: http.StatusFound,
	})
	http.Redirect(w, r, entry+"/settings?saved=notification_test", http.StatusFound)
}

func (s *adminServer) recordSettingChange(r *http.Request, state installState, category string, action string, summary string, before any, after any) {
	actor := s.sessionUsername(r)
	if actor == "" {
		actor = state.AdminUser
	}
	_ = s.store.AppendSettingChange(adminSettingChangeRecord{
		Timestamp:  time.Now().UTC(),
		Category:   strings.TrimSpace(category),
		Action:     strings.TrimSpace(action),
		Actor:      actor,
		Summary:    truncateAuditText(summary, 300),
		BeforeJSON: settingChangeJSON(before),
		AfterJSON:  settingChangeJSON(after),
	})
}

func settingChangeJSON(value any) string {
	if value == nil {
		return "{}"
	}
	data, err := json.Marshal(value)
	if err != nil {
		return "{}"
	}
	return string(data)
}

func systemSettingSnapshot(siteName string, system systemConfig) map[string]string {
	system = system.normalized()
	return map[string]string{
		"site_name":          strings.TrimSpace(siteName),
		"timezone":           system.Timezone,
		"locale":             system.Locale,
		"admin_tagline":      system.AdminTagline,
		"public_tagline":     system.PublicTagline,
		"public_headline":    system.PublicHeadline,
		"public_description": system.PublicDescription,
	}
}

func redactedAISettingSnapshot(ai aiConfig) map[string]string {
	ai = ai.sanitized()
	return map[string]string{
		"provider":       ai.Provider,
		"base_url":       ai.BaseURL,
		"chat_model":     ai.ChatModel,
		"image_model":    ai.ImageModel,
		"api_key_masked": ai.maskedAPIKey(),
	}
}

func redactedNotificationSettingSnapshot(config notificationConfig) map[string]any {
	config = config.normalized()
	return map[string]any{
		"enabled":               config.Enabled,
		"channel":               config.Channel,
		"receiver":              config.Receiver,
		"target_masked":         redactedNotificationSettingTarget(config),
		"feishu_secret_set":     config.Channel == "feishu" && strings.TrimSpace(config.FeishuSecret) != "",
		"event_login_failures":  config.EventLoginFailures,
		"event_ai_errors":       config.EventAIErrors,
		"event_storage_warning": config.EventStorageWarning,
	}
}

func redactedNotificationSettingTarget(config notificationConfig) string {
	config = config.normalized()
	if config.Channel != "webhook" && config.Channel != "feishu" {
		return notificationChannelLabel(config.Channel)
	}
	if strings.TrimSpace(config.WebhookURL) == "" {
		return notificationChannelLabel(config.Channel) + " 未配置"
	}
	return notificationChannelLabel(config.Channel) + " 已配置"
}

func (s *adminServer) dataSourceSaveSubmit(w http.ResponseWriter, r *http.Request) {
	state, entry, _, ok := s.authorizedAdminState(w, r, "data-sources", "admin.data_sources.manage")
	if !ok {
		return
	}
	if err := r.ParseForm(); err != nil {
		http.Redirect(w, r, entry+"/data-sources?error="+url.QueryEscape("数据源请求格式不正确"), http.StatusFound)
		return
	}
	source := dataSourceConfigFromForm(r).normalized()
	if err := validateDataSourceConfig(source); err != nil {
		http.Redirect(w, r, entry+"/data-sources?error="+url.QueryEscape(err.Error()), http.StatusFound)
		return
	}

	state.DataSources = upsertDataSource(state.DataSources, source)
	action := strings.TrimSpace(r.FormValue("data_source_action"))
	if action == "save_test" {
		index := findDataSourceIndex(state.DataSources, source.Name)
		if index < 0 {
			http.Redirect(w, r, entry+"/data-sources?error="+url.QueryEscape("数据源保存后未找到，请刷新后重试"), http.StatusFound)
			return
		}
		result := checkDataSourceConfig(state.DataSources[index])
		checkedAt := time.Now().UTC()
		applyDataSourceCheckResult(&state, index, result, checkedAt)
		if err := s.store.Save(state); err != nil {
			http.Redirect(w, r, entry+"/data-sources?error="+url.QueryEscape("保存数据源测试结果失败："+err.Error()), http.StatusFound)
			return
		}
		if result.OK {
			if snapshot, ok := newSchemaSnapshotRecord(state.DataSources[index], result, checkedAt); ok {
				if err := s.store.AppendSchemaSnapshot(snapshot); err != nil {
					http.Redirect(w, r, entry+"/data-sources?error="+url.QueryEscape("保存结构快照失败："+err.Error()), http.StatusFound)
					return
				}
			}
		}
		s.recordAuditEvent(r, state, auditEventInput{
			Category:   "operation",
			Action:     "保存并测试数据源",
			Detail:     state.DataSources[index].Name + "：" + result.Message,
			StatusCode: http.StatusFound,
		})
		if !result.OK {
			http.Redirect(w, r, entry+"/data-sources?error="+url.QueryEscape(result.Message), http.StatusFound)
			return
		}
		http.Redirect(w, r, entry+"/data-sources?saved=test", http.StatusFound)
		return
	}

	if err := s.store.Save(state); err != nil {
		http.Redirect(w, r, entry+"/data-sources?error="+url.QueryEscape("保存数据源失败："+err.Error()), http.StatusFound)
		return
	}
	s.recordAuditEvent(r, state, auditEventInput{
		Category:   "operation",
		Action:     "保存数据源",
		Detail:     "登记或更新数据源：" + source.Name,
		StatusCode: http.StatusFound,
	})
	http.Redirect(w, r, entry+"/data-sources?saved=datasource", http.StatusFound)
}

func (s *adminServer) dataSourceTestSubmit(w http.ResponseWriter, r *http.Request) {
	state, entry, _, ok := s.authorizedAdminState(w, r, "data-sources", "admin.data_sources.manage")
	if !ok {
		return
	}
	if err := r.ParseForm(); err != nil {
		http.Redirect(w, r, entry+"/data-sources?error="+url.QueryEscape("测试请求格式不正确"), http.StatusFound)
		return
	}
	name := strings.TrimSpace(r.FormValue("name"))
	index := findDataSourceIndex(state.DataSources, name)
	if index < 0 {
		http.Redirect(w, r, entry+"/data-sources?error="+url.QueryEscape("数据源不存在"), http.StatusFound)
		return
	}

	result := checkDataSourceConfig(state.DataSources[index])
	checkedAt := time.Now().UTC()
	applyDataSourceCheckResult(&state, index, result, checkedAt)
	if err := s.store.Save(state); err != nil {
		http.Redirect(w, r, entry+"/data-sources?error="+url.QueryEscape("保存数据源测试结果失败："+err.Error()), http.StatusFound)
		return
	}
	if result.OK {
		if snapshot, ok := newSchemaSnapshotRecord(state.DataSources[index], result, checkedAt); ok {
			if err := s.store.AppendSchemaSnapshot(snapshot); err != nil {
				http.Redirect(w, r, entry+"/data-sources?error="+url.QueryEscape("保存结构快照失败："+err.Error()), http.StatusFound)
				return
			}
		}
	}
	s.recordAuditEvent(r, state, auditEventInput{
		Category:   "operation",
		Action:     "测试数据源",
		Detail:     state.DataSources[index].Name + "：" + result.Message,
		StatusCode: http.StatusFound,
	})
	if !result.OK {
		http.Redirect(w, r, entry+"/data-sources?error="+url.QueryEscape(result.Message), http.StatusFound)
		return
	}
	http.Redirect(w, r, entry+"/data-sources?saved=test", http.StatusFound)
}

func applyDataSourceCheckResult(state *installState, index int, result databaseCheckResult, checkedAt time.Time) {
	if state == nil || index < 0 || index >= len(state.DataSources) {
		return
	}
	state.DataSources[index].Status = "unavailable"
	if result.OK {
		state.DataSources[index].Status = "available"
	}
	state.DataSources[index].LastMessage = result.Message
	state.DataSources[index].SchemaSummary = strings.Join(result.Checks, "；")
	state.DataSources[index].LastCheckedAt = checkedAt
}

func (s *adminServer) dataSourceDeleteSubmit(w http.ResponseWriter, r *http.Request) {
	state, entry, _, ok := s.authorizedAdminState(w, r, "data-sources", "admin.data_sources.manage")
	if !ok {
		return
	}
	if err := r.ParseForm(); err != nil {
		http.Redirect(w, r, entry+"/data-sources?error="+url.QueryEscape("删除请求格式不正确"), http.StatusFound)
		return
	}
	name := strings.TrimSpace(r.FormValue("name"))
	index := findDataSourceIndex(state.DataSources, name)
	if index < 0 {
		http.Redirect(w, r, entry+"/data-sources?error="+url.QueryEscape("数据源不存在"), http.StatusFound)
		return
	}
	deletedName := state.DataSources[index].Name
	state.DataSources = append(state.DataSources[:index], state.DataSources[index+1:]...)
	if err := s.store.Save(state); err != nil {
		http.Redirect(w, r, entry+"/data-sources?error="+url.QueryEscape("删除数据源失败："+err.Error()), http.StatusFound)
		return
	}
	s.recordAuditEvent(r, state, auditEventInput{
		Category:   "operation",
		Action:     "删除数据源",
		Detail:     "删除数据源：" + deletedName,
		StatusCode: http.StatusFound,
	})
	http.Redirect(w, r, entry+"/data-sources?saved=delete", http.StatusFound)
}

func (s *adminServer) adminUserSaveSubmit(w http.ResponseWriter, r *http.Request) {
	state, entry, _, ok := s.authorizedAdminState(w, r, "users", "admin.users.manage")
	if !ok {
		return
	}
	if err := r.ParseForm(); err != nil {
		http.Redirect(w, r, entry+"/users?error="+url.QueryEscape("管理员请求格式不正确"), http.StatusFound)
		return
	}
	account, password := adminAccountFromForm(r)
	if strings.EqualFold(account.Username, state.AdminUser) {
		http.Redirect(w, r, entry+"/users?error="+url.QueryEscape("初始化超级管理员不可在这里覆盖"), http.StatusFound)
		return
	}
	if err := validateAdminAccount(account, password, true); err != nil {
		http.Redirect(w, r, entry+"/users?error="+url.QueryEscape(err.Error()), http.StatusFound)
		return
	}
	access := state.Access.normalized(state)
	if role, ok := findAdminRoleConfig(access.Roles, account.Role); !ok || role.Status == "disabled" {
		http.Redirect(w, r, entry+"/users?error="+url.QueryEscape("请选择可用的用户组"), http.StatusFound)
		return
	}
	var existing *adminAccountConfig
	for i := range access.Users {
		if strings.EqualFold(access.Users[i].Username, account.Username) {
			existing = &access.Users[i]
			break
		}
	}
	if existing == nil && strings.TrimSpace(password) == "" {
		http.Redirect(w, r, entry+"/users?error="+url.QueryEscape("新增管理员必须设置初始密码"), http.StatusFound)
		return
	}
	if existing != nil {
		account.PasswordSalt = existing.PasswordSalt
		account.PasswordHash = existing.PasswordHash
		account.CreatedAt = existing.CreatedAt
		account.LastLoginAt = existing.LastLoginAt
	}
	if strings.TrimSpace(password) != "" {
		salt, hash, err := hashPassword(password)
		if err != nil {
			http.Redirect(w, r, entry+"/users?error="+url.QueryEscape("生成管理员密码失败："+err.Error()), http.StatusFound)
			return
		}
		account.PasswordSalt = salt
		account.PasswordHash = hash
	}
	now := time.Now().UTC()
	if account.CreatedAt.IsZero() {
		account.CreatedAt = now
	}
	account.UpdatedAt = now
	access.Users = upsertAdminAccount(access.Users, account)
	state.Access = access.withoutBootstrap(state)
	if err := s.store.Save(state); err != nil {
		http.Redirect(w, r, entry+"/users?error="+url.QueryEscape("保存管理员失败："+err.Error()), http.StatusFound)
		return
	}
	s.recordAuditEvent(r, state, auditEventInput{
		Category:   "operation",
		Action:     "保存管理员",
		Detail:     "登记或更新管理员：" + account.Username,
		StatusCode: http.StatusFound,
	})
	http.Redirect(w, r, entry+"/users?saved=user", http.StatusFound)
}

func (s *adminServer) adminRoleSaveSubmit(w http.ResponseWriter, r *http.Request) {
	state, entry, _, ok := s.authorizedAdminState(w, r, "user-groups", "admin.roles.manage")
	if !ok {
		return
	}
	redirectBase := entry + "/users/groups"
	access := state.Access.normalized(state)
	if err := r.ParseForm(); err != nil {
		http.Redirect(w, r, redirectBase+"?error="+url.QueryEscape("用户组权限请求格式不正确"), http.StatusFound)
		return
	}
	role := adminRoleFromForm(r)
	role.MenuKeys = normalizeAdminMenuSelection(role.MenuKeys, access.Menus)
	role.PermissionKeys = normalizeAdminPermissionSelection(role.PermissionKeys, access.Permissions)
	if err := validateAdminRole(role); err != nil {
		http.Redirect(w, r, redirectBase+"?error="+url.QueryEscape(err.Error()), http.StatusFound)
		return
	}
	if existing, ok := findAdminRoleConfig(access.Roles, role.Key); ok {
		if role.Name == "" {
			role.Name = existing.Name
		}
		if role.Scope == "" {
			role.Scope = existing.Scope
		}
		if role.Description == "" {
			role.Description = existing.Description
		}
	}
	access.Roles = upsertAdminRole(access.Roles, role)
	state.Access = access.withoutBootstrap(state)
	if err := s.store.Save(state); err != nil {
		http.Redirect(w, r, redirectBase+"?error="+url.QueryEscape("保存用户组权限失败："+err.Error()), http.StatusFound)
		return
	}
	s.recordAuditEvent(r, state, auditEventInput{
		Category:   "operation",
		Action:     "保存用户组权限",
		Detail:     role.Name + " -> " + roleTableAccessSummary(role.DataScope, role.AllowedTables),
		StatusCode: http.StatusFound,
	})
	http.Redirect(w, r, redirectBase+"?saved=role", http.StatusFound)
}

func (s *adminServer) adminUserToggleSubmit(w http.ResponseWriter, r *http.Request) {
	state, entry, _, ok := s.authorizedAdminState(w, r, "users", "admin.users.manage")
	if !ok {
		return
	}
	if err := r.ParseForm(); err != nil {
		http.Redirect(w, r, entry+"/users?error="+url.QueryEscape("状态切换请求格式不正确"), http.StatusFound)
		return
	}
	username := strings.TrimSpace(r.FormValue("username"))
	if strings.EqualFold(username, state.AdminUser) {
		http.Redirect(w, r, entry+"/users?error="+url.QueryEscape("初始化超级管理员不能禁用"), http.StatusFound)
		return
	}
	access := state.Access.normalized(state)
	index := findAdminAccountIndex(access.Users, username)
	if index < 0 {
		http.Redirect(w, r, entry+"/users?error="+url.QueryEscape("管理员不存在"), http.StatusFound)
		return
	}
	if access.Users[index].Status == "disabled" {
		access.Users[index].Status = "enabled"
	} else {
		access.Users[index].Status = "disabled"
	}
	access.Users[index].UpdatedAt = time.Now().UTC()
	state.Access = access.withoutBootstrap(state)
	if err := s.store.Save(state); err != nil {
		http.Redirect(w, r, entry+"/users?error="+url.QueryEscape("保存管理员状态失败："+err.Error()), http.StatusFound)
		return
	}
	s.recordAuditEvent(r, state, auditEventInput{
		Category:   "operation",
		Action:     "切换管理员状态",
		Detail:     username + " -> " + access.Users[index].Status,
		StatusCode: http.StatusFound,
	})
	http.Redirect(w, r, entry+"/users?saved=toggle", http.StatusFound)
}

func (s *adminServer) adminUserDeleteSubmit(w http.ResponseWriter, r *http.Request) {
	state, entry, _, ok := s.authorizedAdminState(w, r, "users", "admin.users.manage")
	if !ok {
		return
	}
	if err := r.ParseForm(); err != nil {
		http.Redirect(w, r, entry+"/users?error="+url.QueryEscape("删除请求格式不正确"), http.StatusFound)
		return
	}
	username := strings.TrimSpace(r.FormValue("username"))
	if strings.EqualFold(username, state.AdminUser) {
		http.Redirect(w, r, entry+"/users?error="+url.QueryEscape("初始化超级管理员不能删除"), http.StatusFound)
		return
	}
	access := state.Access.normalized(state)
	index := findAdminAccountIndex(access.Users, username)
	if index < 0 {
		http.Redirect(w, r, entry+"/users?error="+url.QueryEscape("管理员不存在"), http.StatusFound)
		return
	}
	deleted := access.Users[index].Username
	access.Users = append(access.Users[:index], access.Users[index+1:]...)
	state.Access = access.withoutBootstrap(state)
	if err := s.store.Save(state); err != nil {
		http.Redirect(w, r, entry+"/users?error="+url.QueryEscape("删除管理员失败："+err.Error()), http.StatusFound)
		return
	}
	s.recordAuditEvent(r, state, auditEventInput{
		Category:   "operation",
		Action:     "删除管理员",
		Detail:     "删除管理员：" + deleted,
		StatusCode: http.StatusFound,
	})
	http.Redirect(w, r, entry+"/users?saved=delete", http.StatusFound)
}

func (s *adminServer) adminSessionRevokeSubmit(w http.ResponseWriter, r *http.Request) {
	state, entry, _, ok := s.authorizedAdminState(w, r, "user-sessions", "admin.sessions.manage")
	if !ok {
		return
	}
	redirectBase := entry + "/users/sessions"
	if err := r.ParseForm(); err != nil {
		http.Redirect(w, r, redirectBase+"?error="+url.QueryEscape("会话请求格式不正确"), http.StatusFound)
		return
	}
	sessionID := strings.TrimSpace(r.FormValue("session_id"))
	if sessionID == "" {
		http.Redirect(w, r, redirectBase+"?error="+url.QueryEscape("会话不存在"), http.StatusFound)
		return
	}
	if sessionID == s.sessionID(r) {
		http.Redirect(w, r, redirectBase+"?error="+url.QueryEscape("不能在这里下线当前会话，请使用右上角退出登录"), http.StatusFound)
		return
	}
	if err := s.store.RevokeAdminSession(sessionID, time.Now().UTC()); err != nil {
		http.Redirect(w, r, redirectBase+"?error="+url.QueryEscape("下线会话失败："+err.Error()), http.StatusFound)
		return
	}
	s.recordAuditEvent(r, state, auditEventInput{
		Category:   "operation",
		Action:     "下线后台会话",
		Actor:      s.sessionUsername(r),
		Detail:     "强制下线会话：" + truncateAuditText(sessionID, 80),
		StatusCode: http.StatusFound,
	})
	http.Redirect(w, r, redirectBase+"?saved=session", http.StatusFound)
}

func (s *adminServer) fileUploadSubmit(w http.ResponseWriter, r *http.Request) {
	state, entry, _, ok := s.authorizedAdminState(w, r, "files", "admin.files.manage")
	if !ok {
		return
	}
	storage := state.Storage.normalized()
	if err := validateStorageConfig(storage); err != nil {
		s.notifyAdminEvent(r, state, state.Notifications.normalized().EventStorageWarning, "storage_warning", "文件上传被存储配置拦截", err.Error())
		http.Redirect(w, r, entry+"/files?error="+url.QueryEscape(err.Error()), http.StatusFound)
		return
	}
	root, err := storageLocalRoot(storage)
	if err != nil {
		s.notifyAdminEvent(r, state, state.Notifications.normalized().EventStorageWarning, "storage_warning", "存储目录不可用", err.Error())
		http.Redirect(w, r, entry+"/files?error="+url.QueryEscape("存储目录不可用："+err.Error()), http.StatusFound)
		return
	}
	if err := os.MkdirAll(root, 0o755); err != nil {
		s.notifyAdminEvent(r, state, state.Notifications.normalized().EventStorageWarning, "storage_warning", "创建存储目录失败", "目录 "+root+" 创建失败："+err.Error())
		http.Redirect(w, r, entry+"/files?error="+url.QueryEscape("创建存储目录失败："+err.Error()), http.StatusFound)
		return
	}

	maxBytes := int64(storage.MaxFileSizeMB) * 1024 * 1024
	r.Body = http.MaxBytesReader(w, r.Body, maxBytes*10+1024*1024)
	if err := r.ParseMultipartForm(32 << 20); err != nil {
		s.notifyAdminEvent(r, state, state.Notifications.normalized().EventStorageWarning, "storage_warning", "读取上传文件失败", err.Error())
		http.Redirect(w, r, entry+"/files?error="+url.QueryEscape("读取上传文件失败："+err.Error()), http.StatusFound)
		return
	}

	files := r.MultipartForm.File["files"]
	if len(files) == 0 {
		http.Redirect(w, r, entry+"/files?error="+url.QueryEscape("请选择要上传的文件"), http.StatusFound)
		return
	}

	uploaded := 0
	for _, header := range files {
		if header.Size > maxBytes {
			s.notifyAdminEvent(r, state, state.Notifications.normalized().EventStorageWarning, "storage_warning", "文件超过上传大小限制", "文件 "+header.Filename+" 超过 "+strconv.Itoa(storage.MaxFileSizeMB)+" MB 限制。")
			http.Redirect(w, r, entry+"/files?error="+url.QueryEscape("文件超过大小限制："+header.Filename), http.StatusFound)
			return
		}
		if !storageExtensionAllowed(storage, header.Filename) {
			s.notifyAdminEvent(r, state, state.Notifications.normalized().EventStorageWarning, "storage_warning", "不支持的文件扩展名", "文件 "+header.Filename+" 的扩展名 "+filepath.Ext(header.Filename)+" 未在允许列表中。")
			http.Redirect(w, r, entry+"/files?error="+url.QueryEscape("不支持的文件扩展名："+filepath.Ext(header.Filename)), http.StatusFound)
			return
		}
		if err := saveUploadedFile(root, header.Filename, header.Open, maxBytes); err != nil {
			s.notifyAdminEvent(r, state, state.Notifications.normalized().EventStorageWarning, "storage_warning", "保存上传文件失败", "文件 "+header.Filename+" 保存失败："+err.Error())
			http.Redirect(w, r, entry+"/files?error="+url.QueryEscape("保存文件失败："+err.Error()), http.StatusFound)
			return
		}
		uploaded++
	}

	s.recordAuditEvent(r, state, auditEventInput{
		Category:   "file",
		Action:     "上传文件",
		Detail:     "上传 " + strconv.Itoa(uploaded) + " 个文件到本地存储",
		StatusCode: http.StatusFound,
	})
	http.Redirect(w, r, entry+"/files?saved=upload&count="+strconv.Itoa(uploaded), http.StatusFound)
}

func (s *adminServer) fileDeleteSubmit(w http.ResponseWriter, r *http.Request) {
	state, entry, _, ok := s.authorizedAdminState(w, r, "files", "admin.files.manage")
	if !ok {
		return
	}
	if err := r.ParseForm(); err != nil {
		http.Redirect(w, r, entry+"/files?error="+url.QueryEscape("删除请求格式不正确"), http.StatusFound)
		return
	}
	relativePath := strings.TrimSpace(r.FormValue("path"))
	root, err := storageLocalRoot(state.Storage.normalized())
	if err != nil {
		http.Redirect(w, r, entry+"/files?error="+url.QueryEscape("存储目录不可用："+err.Error()), http.StatusFound)
		return
	}
	target, err := safeStoragePath(root, relativePath)
	if err != nil {
		http.Redirect(w, r, entry+"/files?error="+url.QueryEscape("文件路径不合法"), http.StatusFound)
		return
	}
	info, err := os.Stat(target)
	if err != nil {
		http.Redirect(w, r, entry+"/files?error="+url.QueryEscape("文件不存在"), http.StatusFound)
		return
	}
	if info.IsDir() {
		http.Redirect(w, r, entry+"/files?error="+url.QueryEscape("不能删除目录"), http.StatusFound)
		return
	}
	if err := os.Remove(target); err != nil {
		http.Redirect(w, r, entry+"/files?error="+url.QueryEscape("删除文件失败："+err.Error()), http.StatusFound)
		return
	}
	s.recordAuditEvent(r, state, auditEventInput{
		Category:   "file",
		Action:     "删除文件",
		Detail:     "删除本地存储文件：" + truncateAuditText(relativePath, 120),
		StatusCode: http.StatusFound,
	})
	http.Redirect(w, r, entry+"/files?saved=delete", http.StatusFound)
}

func (s *adminServer) fileServe(w http.ResponseWriter, r *http.Request, download bool) {
	state, entry, _, ok := s.authorizedAdminState(w, r, "files", "")
	if !ok {
		return
	}
	prefix := entry + "/files/preview/"
	if download {
		prefix = entry + "/files/download/"
	}
	encodedPath := strings.TrimPrefix(r.URL.Path, prefix)
	relativePath, err := url.PathUnescape(encodedPath)
	if err != nil {
		http.NotFound(w, r)
		return
	}
	root, err := storageLocalRoot(state.Storage.normalized())
	if err != nil {
		http.NotFound(w, r)
		return
	}
	target, err := safeStoragePath(root, relativePath)
	if err != nil {
		http.NotFound(w, r)
		return
	}
	info, err := os.Stat(target)
	if err != nil || info.IsDir() {
		http.NotFound(w, r)
		return
	}
	if download {
		w.Header().Set("Content-Disposition", `attachment; filename="`+filepath.Base(target)+`"`)
	} else {
		w.Header().Set("Content-Disposition", `inline; filename="`+filepath.Base(target)+`"`)
	}
	http.ServeFile(w, r, target)
}

func (s *adminServer) backgroundTaskEnqueueSubmit(w http.ResponseWriter, r *http.Request) {
	state, entry, _, ok := s.authorizedAdminState(w, r, "tasks", "admin.tasks.manage")
	if !ok {
		return
	}
	if err := r.ParseForm(); err != nil {
		http.Redirect(w, r, entry+"/tasks?error="+url.QueryEscape("任务请求格式不正确"), http.StatusFound)
		return
	}
	taskType := strings.TrimSpace(r.FormValue("task_type"))
	task, err := newBackgroundTaskRecord(taskType, s.sessionUsername(r))
	if err != nil {
		http.Redirect(w, r, entry+"/tasks?error="+url.QueryEscape(err.Error()), http.StatusFound)
		return
	}
	if err := s.store.EnqueueBackgroundTask(task); err != nil {
		http.Redirect(w, r, entry+"/tasks?error="+url.QueryEscape("任务入队失败："+err.Error()), http.StatusFound)
		return
	}
	s.appendBackgroundTaskLog(task, "info", "queued", "任务已加入 "+task.Queue+" 队列")
	s.recordAuditEvent(r, state, auditEventInput{
		Category:   "task",
		Action:     "创建后台任务",
		Actor:      s.sessionUsername(r),
		Detail:     task.Name + " 已加入 " + task.Queue + " 队列",
		StatusCode: http.StatusFound,
	})
	http.Redirect(w, r, entry+"/tasks?saved=enqueue", http.StatusFound)
}

func (s *adminServer) backgroundTaskSettingsSubmit(w http.ResponseWriter, r *http.Request) {
	state, entry, _, ok := s.authorizedAdminState(w, r, "tasks", "admin.tasks.manage")
	if !ok {
		return
	}
	redirectPath := entry + "/tasks"
	successQuery := "saved=settings"
	if strings.Contains(r.URL.Path, "/settings/tasks") {
		redirectPath = entry + "/settings"
		successQuery = "saved=task_worker"
	}
	redirectError := func(message string) {
		http.Redirect(w, r, redirectPath+"?error="+url.QueryEscape(message), http.StatusFound)
	}
	if err := r.ParseForm(); err != nil {
		redirectError("任务设置请求格式不正确")
		return
	}
	before := state.TaskWorker.normalized()
	config := taskWorkerConfigFromForm(r).normalized()
	if err := validateTaskWorkerConfig(config); err != nil {
		redirectError(err.Error())
		return
	}
	state.TaskWorker = config
	if err := s.store.Save(state); err != nil {
		redirectError("保存任务设置失败：" + err.Error())
		return
	}
	s.recordSettingChange(r, state, "task", "保存任务设置", "更新后台任务自动执行与定时调度策略", before, config)
	s.recordAuditEvent(r, state, auditEventInput{
		Category:   "task",
		Action:     "保存任务设置",
		Actor:      s.sessionUsername(r),
		Detail:     "更新后台任务自动执行与定时调度策略",
		StatusCode: http.StatusFound,
	})
	http.Redirect(w, r, redirectPath+"?"+successQuery, http.StatusFound)
}

func (s *adminServer) backgroundTaskRunSubmit(w http.ResponseWriter, r *http.Request) {
	state, entry, _, ok := s.authorizedAdminState(w, r, "tasks", "admin.tasks.manage")
	if !ok {
		return
	}
	if err := r.ParseForm(); err != nil {
		http.Redirect(w, r, entry+"/tasks?error="+url.QueryEscape("任务执行请求格式不正确"), http.StatusFound)
		return
	}
	taskID := strings.TrimSpace(r.FormValue("task_id"))
	task, err := s.runnableBackgroundTask(taskID)
	if err != nil {
		http.Redirect(w, r, entry+"/tasks?error="+url.QueryEscape(err.Error()), http.StatusFound)
		return
	}
	task = s.runBackgroundTask(r.Context(), state, task)
	if err := s.store.UpdateBackgroundTask(task); err != nil {
		http.Redirect(w, r, entry+"/tasks?error="+url.QueryEscape("保存任务结果失败："+err.Error()), http.StatusFound)
		return
	}
	statusCode := http.StatusFound
	if task.Status == "failed" {
		statusCode = http.StatusInternalServerError
	}
	s.recordAuditEvent(r, state, auditEventInput{
		Category:   "task",
		Action:     "执行后台任务",
		Actor:      s.sessionUsername(r),
		Detail:     task.Name + "：" + backgroundTaskStatusLabel(task.Status),
		StatusCode: statusCode,
	})
	if task.Status == "failed" {
		http.Redirect(w, r, entry+"/tasks?error="+url.QueryEscape("任务执行失败："+task.LastError), http.StatusFound)
		return
	}
	http.Redirect(w, r, entry+"/tasks?saved=run", http.StatusFound)
}

func (s *adminServer) backgroundTaskRunAllSubmit(w http.ResponseWriter, r *http.Request) {
	state, entry, _, ok := s.authorizedAdminState(w, r, "tasks", "admin.tasks.manage")
	if !ok {
		return
	}
	if err := r.ParseForm(); err != nil {
		http.Redirect(w, r, entry+"/tasks?error="+url.QueryEscape("批量执行请求格式不正确"), http.StatusFound)
		return
	}
	limit := 20
	completed := 0
	failed := 0
	for completed+failed < limit {
		task, err := s.runnableBackgroundTask("")
		if err != nil && (errors.Is(err, sql.ErrNoRows) || strings.Contains(err.Error(), "暂无可执行")) {
			break
		}
		if err != nil {
			http.Redirect(w, r, entry+"/tasks?error="+url.QueryEscape("读取待执行任务失败："+err.Error()), http.StatusFound)
			return
		}
		task = s.runBackgroundTask(r.Context(), state, task)
		if err := s.store.UpdateBackgroundTask(task); err != nil {
			http.Redirect(w, r, entry+"/tasks?error="+url.QueryEscape("保存任务结果失败："+err.Error()), http.StatusFound)
			return
		}
		if task.Status == "succeeded" {
			completed++
		} else {
			failed++
		}
	}
	s.recordAuditEvent(r, state, auditEventInput{
		Category:   "task",
		Action:     "批量执行后台任务",
		Actor:      s.sessionUsername(r),
		Detail:     "批量执行完成 " + strconv.Itoa(completed) + " 个，失败/待重试 " + strconv.Itoa(failed) + " 个",
		StatusCode: http.StatusFound,
	})
	http.Redirect(w, r, entry+"/tasks?saved=run_all&count="+strconv.Itoa(completed)+"&failed="+strconv.Itoa(failed), http.StatusFound)
}

func (s *adminServer) backgroundTaskRetrySubmit(w http.ResponseWriter, r *http.Request) {
	state, entry, _, ok := s.authorizedAdminState(w, r, "tasks", "admin.tasks.manage")
	if !ok {
		return
	}
	if err := r.ParseForm(); err != nil {
		http.Redirect(w, r, entry+"/tasks?error="+url.QueryEscape("任务重试请求格式不正确"), http.StatusFound)
		return
	}
	taskID := strings.TrimSpace(r.FormValue("task_id"))
	if taskID == "" {
		http.Redirect(w, r, entry+"/tasks?error="+url.QueryEscape("缺少任务 ID"), http.StatusFound)
		return
	}
	task, err := s.store.BackgroundTaskByID(taskID)
	if errors.Is(err, sql.ErrNoRows) {
		http.Redirect(w, r, entry+"/tasks?error="+url.QueryEscape("任务不存在"), http.StatusFound)
		return
	}
	if err != nil {
		http.Redirect(w, r, entry+"/tasks?error="+url.QueryEscape("读取任务失败："+err.Error()), http.StatusFound)
		return
	}
	task.Status = "pending"
	task.AvailableAt = time.Now().UTC()
	task.StartedAt = time.Time{}
	task.FinishedAt = time.Time{}
	task.LastError = ""
	task.UpdatedAt = time.Now().UTC()
	if err := s.store.UpdateBackgroundTask(task); err != nil {
		http.Redirect(w, r, entry+"/tasks?error="+url.QueryEscape("任务重试失败："+err.Error()), http.StatusFound)
		return
	}
	s.appendBackgroundTaskLog(task, "info", "retry", "任务已被管理员重新放回待执行队列")
	s.recordAuditEvent(r, state, auditEventInput{
		Category:   "task",
		Action:     "重试后台任务",
		Actor:      s.sessionUsername(r),
		Detail:     task.Name + " 已重新进入 pending 状态",
		StatusCode: http.StatusFound,
	})
	http.Redirect(w, r, entry+"/tasks?saved=retry", http.StatusFound)
}

func (s *adminServer) backgroundTaskCancelSubmit(w http.ResponseWriter, r *http.Request) {
	state, entry, _, ok := s.authorizedAdminState(w, r, "tasks", "admin.tasks.manage")
	if !ok {
		return
	}
	if err := r.ParseForm(); err != nil {
		http.Redirect(w, r, entry+"/tasks?error="+url.QueryEscape("任务取消请求格式不正确"), http.StatusFound)
		return
	}
	taskID := strings.TrimSpace(r.FormValue("task_id"))
	if taskID == "" {
		http.Redirect(w, r, entry+"/tasks?error="+url.QueryEscape("缺少任务 ID"), http.StatusFound)
		return
	}
	task, err := s.store.BackgroundTaskByID(taskID)
	if errors.Is(err, sql.ErrNoRows) {
		http.Redirect(w, r, entry+"/tasks?error="+url.QueryEscape("任务不存在"), http.StatusFound)
		return
	}
	if err != nil {
		http.Redirect(w, r, entry+"/tasks?error="+url.QueryEscape("读取任务失败："+err.Error()), http.StatusFound)
		return
	}
	status := strings.ToLower(strings.TrimSpace(task.Status))
	if status != "pending" && status != "retry" {
		http.Redirect(w, r, entry+"/tasks?error="+url.QueryEscape("只有待执行或等待重试的任务可以取消"), http.StatusFound)
		return
	}
	now := time.Now().UTC()
	task.Status = "canceled"
	task.Result = "管理员取消任务"
	task.LastError = ""
	task.FinishedAt = now
	task.UpdatedAt = now
	if err := s.store.UpdateBackgroundTask(task); err != nil {
		http.Redirect(w, r, entry+"/tasks?error="+url.QueryEscape("取消任务失败："+err.Error()), http.StatusFound)
		return
	}
	s.appendBackgroundTaskLog(task, "warn", "canceled", "任务已被管理员取消")
	s.recordAuditEvent(r, state, auditEventInput{
		Category:   "task",
		Action:     "取消后台任务",
		Actor:      s.sessionUsername(r),
		Detail:     task.Name + " 已取消",
		StatusCode: http.StatusFound,
	})
	http.Redirect(w, r, entry+"/tasks?saved=cancel", http.StatusFound)
}

func (s *adminServer) auditExport(w http.ResponseWriter, r *http.Request) {
	state, _, _, ok := s.authorizedAdminState(w, r, "audit", "")
	if !ok {
		return
	}
	filter := auditFilterFromQuery(r.URL.Query())
	events := filterAuditEvents(s.listAuditEvents(2000), filter)
	s.recordAuditEvent(r, state, auditEventInput{
		Category:   "operation",
		Action:     "导出审计日志",
		Detail:     "导出审计日志 " + strconv.Itoa(len(events)) + " 条",
		StatusCode: http.StatusOK,
	})

	filename := "moyi-audit-" + time.Now().Format("20060102-150405") + ".csv"
	w.Header().Set("Content-Type", "text/csv; charset=utf-8")
	w.Header().Set("Content-Disposition", `attachment; filename="`+filename+`"`)
	w.WriteHeader(http.StatusOK)
	writer := csv.NewWriter(w)
	_ = writer.Write([]string{"时间", "分类", "操作", "管理员", "详情", "方法", "路径", "IP", "状态", "耗时"})
	for _, event := range events {
		_ = writer.Write([]string{event.Time, event.Category, event.Action, event.Actor, event.Detail, event.Method, event.Path, event.IP, event.Status, event.Duration})
	}
	writer.Flush()
}

func (s *adminServer) extensionsExport(w http.ResponseWriter, r *http.Request) {
	state, _, _, ok := s.authorizedAdminState(w, r, "extensions", "")
	if !ok {
		return
	}
	models := buildResourceModels(state)
	tools := buildResourceTools(models)
	plugins := buildPluginExtensions(state, models, tools)
	total := len(plugins) + len(models) + len(tools)
	s.recordAuditEvent(r, state, auditEventInput{
		Category:   "operation",
		Action:     "导出能力扩展清单",
		Detail:     "导出插件、资源模型和工具清单 " + strconv.Itoa(total) + " 条",
		StatusCode: http.StatusOK,
	})

	filename := "moyi-extensions-" + time.Now().Format("20060102-150405") + ".csv"
	w.Header().Set("Content-Type", "text/csv; charset=utf-8")
	w.Header().Set("Content-Disposition", `attachment; filename="`+filename+`"`)
	w.WriteHeader(http.StatusOK)
	writer := csv.NewWriter(w)
	_ = writer.Write([]string{"类别", "标识", "名称", "来源", "动作/资源", "状态", "说明"})
	for _, plugin := range plugins {
		_ = writer.Write([]string{"插件扩展包", plugin.Key, plugin.Name, plugin.Kind + " / " + plugin.Version, plugin.Resources + " / " + plugin.Tools, plugin.Status, plugin.Description})
	}
	for _, model := range models {
		_ = writer.Write([]string{"资源模型", model.Key, model.Name, model.Plugin + " / " + model.Source, model.Actions, model.Status, model.Description + "；" + model.FieldsSummary})
	}
	for _, tool := range tools {
		_ = writer.Write([]string{"资源工具", tool.Name, tool.Resource, tool.Plugin, tool.Action + " / " + tool.Permission, tool.Status, tool.Boundary})
	}
	writer.Flush()
}

func (s *adminServer) agentWeChatMessagesExport(w http.ResponseWriter, r *http.Request) {
	state, _, _, ok := s.authorizedAdminState(w, r, "wechat-agent-messages", "")
	if !ok {
		return
	}
	channelKey := normalizeAgentWeChatChannelKey(r.URL.Query().Get("channel"))
	records := s.listAgentWeChatMessagesPage(channelKey, 5000, 0)
	detail := "导出微信 Agent 聊天记录 " + strconv.Itoa(len(records)) + " 条"
	if channelKey != "" {
		detail += "，通道 " + channelKey
	}
	s.recordAuditEvent(r, state, auditEventInput{
		Category:   "operation",
		Action:     "导出微信聊天记录",
		Detail:     detail,
		StatusCode: http.StatusOK,
	})

	filename := "moyi-wechat-agent-messages-" + time.Now().Format("20060102-150405") + ".csv"
	w.Header().Set("Content-Type", "text/csv; charset=utf-8")
	w.Header().Set("Content-Disposition", `attachment; filename="`+filename+`"`)
	w.WriteHeader(http.StatusOK)
	writer := csv.NewWriter(w)
	_ = writer.Write([]string{"收到时间", "回复时间", "通道", "通道名称", "消息ID", "会话ID", "运行ID", "发送人", "接收人", "用户消息", "AI回复", "文件", "状态", "错误", "工具数", "文件数", "耗时ms"})
	for _, record := range records {
		_ = writer.Write([]string{
			formatAdminTime(record.ReceivedAt),
			formatAdminTime(record.RepliedAt),
			record.ChannelKey,
			record.ChannelName,
			record.MessageID,
			record.SessionID,
			record.RunID,
			record.FromUserID,
			record.ToUserID,
			record.InboundText,
			record.ReplyText,
			adminAgentWeChatFileSummary(record.Files),
			agentRunStatusText(record.Status),
			record.Error,
			strconv.Itoa(record.ToolCount),
			strconv.Itoa(record.FileCount),
			strconv.FormatInt(record.DurationMS, 10),
		})
	}
	writer.Flush()
}

func (s *adminServer) authenticatedAdminState(w http.ResponseWriter, r *http.Request) (installState, string, bool) {
	state, err := s.store.Load()
	if err != nil {
		http.Error(w, "读取初始化状态失败", http.StatusInternalServerError)
		return installState{}, "", false
	}
	entry := s.adminEntryForState(state)
	if !state.Initialized {
		http.Redirect(w, r, s.basePath+"/install", http.StatusFound)
		return installState{}, entry, false
	}
	if !s.isAuthenticated(r) {
		http.Redirect(w, r, entry+"/login", http.StatusFound)
		return installState{}, entry, false
	}
	return state, entry, true
}

func (s *adminServer) authorizedAdminState(w http.ResponseWriter, r *http.Request, active string, permissionKey string) (installState, string, adminRoleAccess, bool) {
	state, entry, ok := s.authenticatedAdminState(w, r)
	if !ok {
		return installState{}, entry, adminRoleAccess{}, false
	}
	access := adminRoleAccessForUsername(state, s.sessionUsername(r))
	if !access.Valid {
		http.Redirect(w, r, entry+"/login", http.StatusFound)
		return installState{}, entry, adminRoleAccess{}, false
	}
	if strings.TrimSpace(active) != "" && !access.CanViewPage(active) {
		http.Error(w, "当前管理员未获授权访问该页面", http.StatusForbidden)
		return installState{}, entry, adminRoleAccess{}, false
	}
	if permissionKey != "" && !access.HasPermission(permissionKey) {
		http.Error(w, "当前管理员未获授权执行该操作", http.StatusForbidden)
		return installState{}, entry, adminRoleAccess{}, false
	}
	return state, entry, access, true
}

func (s *adminServer) adminPageData(state installState, entry string, active string, query url.Values, currentUser string, currentSessionID string, requestBase string) adminPageData {
	if active == "" {
		active = "dashboard"
	}
	titles := map[string][2]string{
		"dashboard":             {"工作台", "系统运行概览、迁移进度与关键入口"},
		"foundation":            {"基础服务", "旧系统基础设施对照、迁移状态与下一步"},
		"data-sources":          {"数据源", "元数据连接、业务库接入与结构探测"},
		"extensions":            {"能力扩展", "插件、资源模型与 AI 工具生成"},
		"ai":                    {"智能助理", "只保留对话输入、上下文控制与即时回复"},
		"ai-tasks":              {"任务中心", "跨会话任务状态、步骤与最近任务记录"},
		"ai-runs":               {"对话记录", "最近运行历史、对话结果与文件沉淀"},
		"ai-capabilities":       {"使用说明", "模型入口、权限边界与常用能力说明"},
		"wechat-agent":          {"微信 Agent 通道", "微信对话入口、二维码绑定、管理员身份与消息状态"},
		"wechat-agent-messages": {"微信聊天记录", "微信 Agent 的用户消息、AI 回复、文件回复和发送状态归档"},
		"users":                 {"管理员账号", "后台管理员账号、角色归属与启停管理"},
		"user-groups":           {"用户组权限", "用户组、数据表授权与微信 Agent 继承边界"},
		"user-sessions":         {"登录会话", "后台在线会话、来源设备与强制下线"},
		"user-permissions":      {"菜单权限", "后台菜单目录、权限清单与访问边界"},
		"settings":              {"系统设置", "安装状态、安全入口与运行参数"},
		"files":                 {"文件管理", "上传文件、存储目录、预览下载与清理"},
		"tasks":                 {"后台任务", "异步队列、手动执行、失败重试和任务结果"},
		"notifications":         {"通知事件", "Webhook / 飞书机器人发送记录、事件状态和失败原因"},
		"audit":                 {"审计日志", "初始化、登录与关键操作记录"},
	}
	title := titles[active]
	if title[0] == "" {
		active = "dashboard"
		title = titles[active]
	}

	aiStatus := "未启用"
	aiStatusClass := "is-warning"
	if !state.AI.IsDisabled() {
		aiStatus = "已配置"
		aiStatusClass = "is-ready"
	}
	installedAt := formatAdminTime(state.InstalledAt)
	system := state.System.normalized()
	storage := state.Storage.normalized()
	security := state.Security.normalized()
	notifications := state.Notifications.normalized()
	taskWorker := state.TaskWorker.normalized()
	agentChannels := state.AgentChannels.normalized()
	if strings.TrimSpace(currentUser) == "" {
		currentUser = state.AdminUser
	}
	accessProfile := adminRoleAccessForUsername(state, currentUser)
	canManageSettings := accessProfile.HasPermission("admin.settings.manage")
	canManageDataSources := accessProfile.HasPermission("admin.data_sources.manage")
	canManageFiles := accessProfile.HasPermission("admin.files.manage")
	canManageTasks := accessProfile.HasPermission("admin.tasks.manage")
	canManageWeChat := accessProfile.HasPermission("agent.wechat.manage")
	canViewSettingsPage := accessProfile.CanViewPage("settings")
	canViewWeChatMessages := accessProfile.CanViewPage("wechat-agent-messages")
	canViewAuditPage := accessProfile.CanViewPage("audit")
	canReadAgentTables := accessProfile.HasPermission("agent.tables.read")
	canRunAgentSQL := accessProfile.HasPermission("agent.sql.select")
	canReadWeb := accessProfile.HasPermission("agent.web.read")
	canGenerateAgentImages := accessProfile.HasPermission("agent.image.generate")
	agentScope := agentScopeForAdminAccount(state, currentUser)
	auditFilter := auditFilterFromQuery(query)
	auditEvents := s.listAuditEvents(120)
	if active == "audit" {
		auditEvents = filterAuditEvents(s.listAuditEvents(500), auditFilter)
	}
	notificationRecords := s.listNotificationDeliveries(120)
	taskRecords := s.listBackgroundTasks(120)
	taskLogRecords := s.listBackgroundTaskLogs(160)
	resourceModels := buildResourceModels(state)
	resourceTools := buildResourceTools(resourceModels)
	pluginExtensions := buildPluginExtensions(state, resourceModels, resourceTools)
	if len(auditEvents) == 0 && !auditFilter.active() {
		auditEvents = []adminAuditEvent{
			{Time: installedAt, Category: "system", Action: "系统初始化", Actor: state.AdminUser, Detail: "创建站点、管理员与后台随机入口", Meta: state.AdminUser, Status: "200", StatusClass: "is-ready"},
			{Time: "当前会话", Category: "operation", Action: "后台访问", Actor: state.AdminUser, Detail: "进入管理控制台", Meta: state.AdminUser, Status: "200", StatusClass: "is-ready"},
		}
	}

	data := adminPageData{
		BasePath:               entry,
		LogoutAction:           entry + "/logout",
		Active:                 active,
		Title:                  title[0],
		Subtitle:               title[1],
		SiteName:               state.SiteName,
		Username:               currentUser,
		AdminTagline:           system.AdminTagline,
		AdminEntry:             entry,
		InstalledAt:            installedAt,
		Database:               state.Database.DisplayName(),
		DatabaseDSN:            state.Database.DisplayTarget(),
		AIProvider:             state.AI.DisplayName(),
		AIModel:                state.AI.DisplayModel(),
		AITarget:               state.AI.DisplayTarget(),
		AIStatus:               aiStatus,
		AIStatusClass:          aiStatusClass,
		CanManageSettings:      canManageSettings,
		CanManageDataSources:   canManageDataSources,
		CanManageFiles:         canManageFiles,
		CanManageTasks:         canManageTasks,
		CanManageWeChat:        canManageWeChat,
		CanViewSettingsPage:    canViewSettingsPage,
		CanViewWeChatMessages:  canViewWeChatMessages,
		CanViewAuditPage:       canViewAuditPage,
		CanReadAgentTables:     canReadAgentTables,
		CanRunAgentSQL:         canRunAgentSQL,
		CanReadWeb:             canReadWeb,
		CanGenerateAgentImages: canGenerateAgentImages,
		AgentScopeSummary:      roleTableAccessSummary(agentScope.Mode, agentScope.Tables),
	}
	data.SettingsNotice, data.SettingsNoticeClass = settingsNoticeFromQuery(query)
	data.AgentNotice, data.AgentNoticeClass = agentNoticeFromQuery(query)
	data.UserNotice, data.UserNoticeClass = userNoticeFromQuery(query)
	data.DataSourceNotice, data.DataSourceNoticeClass = dataSourceNoticeFromQuery(query)
	data.FileNotice, data.FileNoticeClass = fileNoticeFromQuery(query)
	data.TaskNotice, data.TaskNoticeClass = taskNoticeFromQuery(query)
	data.NavItems = buildAdminNav(state, entry, active, accessProfile)
	data.NavGroups = buildAdminNavGroups(state, entry, active, accessProfile)
	data.AdminUserMetrics = buildAdminUserMetrics(state)
	data.ExtensionMetrics = buildExtensionMetrics(pluginExtensions, resourceModels, resourceTools)
	data.PluginExtensions = pluginExtensions
	data.ResourceModels = resourceModels
	data.ResourceTools = resourceTools
	data.Metrics = []adminMetric{
		{Label: "系统状态", Value: "已初始化", Detail: "安装时间 " + installedAt, Status: "is-ready"},
		{Label: "元数据数据库", Value: state.Database.DisplayName(), Detail: state.Database.DisplayTarget(), Status: "is-ready"},
		{Label: "AI 服务", Value: state.AI.DisplayName(), Detail: state.AI.DisplayModel(), Status: aiStatusClass},
		{Label: "后台入口", Value: entry, Detail: "随机入口已启用", Status: "is-secure"},
	}
	data.Tasks = []adminTask{
		{Name: "初始化与隐藏入口", Owner: "Foundation", Status: "已完成", StatusClass: "is-ready"},
		{Name: "元数据表迁移", Owner: "Database", Status: "已接入", StatusClass: "is-ready"},
		{Name: "运行审计日志", Owner: "Observability", Status: "已接入", StatusClass: "is-ready"},
		{Name: "用户角色权限", Owner: "Access", Status: "已接入", StatusClass: "is-ready"},
		{Name: "AI 工具与运行记忆", Owner: "Agent", Status: "已接入", StatusClass: "is-ready"},
	}
	data.DataSourceSaveAction = entry + "/data-sources/save"
	data.DataSources = buildAdminDataSources(state, entry)
	data.DataSourceMetrics = buildAdminDataSourceMetrics(data.DataSources)
	if active == "ai-capabilities" {
		data.AgentCapabilities = []adminCapability{
			{Name: "任务规划", Boundary: "理解目标、拆解步骤、生成建议动作", Status: "已启用", StatusClass: "is-ready"},
			{Name: "长期任务记忆", Boundary: "跨会话保留最近任务目标、主表、筛选与导出格式", Status: "已接入", StatusClass: "is-ready"},
			{Name: "工具轨迹", Boundary: "展示每次工具调用、结果与拦截原因", Status: "已启用", StatusClass: "is-ready"},
			{Name: "只读数据工具", Boundary: "表清单、字段结构、预览与 SELECT", Status: "已启用", StatusClass: "is-ready"},
			{Name: "公开网页访问", Boundary: "允许访问公开 http/https 页面，默认拒绝本机、内网和受保护地址", Status: "已启用", StatusClass: "is-ready"},
			{Name: "图片创作", Boundary: "调用百炼图片模型生成海报、插图和封面，结果自动保存为后台文件", Status: "已启用", StatusClass: "is-ready"},
			{Name: "安全边界", Boundary: "拒绝写入、多语句、注释与危险关键字", Status: "已启用", StatusClass: "is-ready"},
			{Name: "导出与状态机", Boundary: "表格导出、运行记录、任务步骤和工具结果落入元数据表", Status: "已接入", StatusClass: "is-ready"},
		}
	}
	agentCreateChannel := agentChannels.WeChat
	if currentChannel, _, ok := findAgentWeChatChannelByAdminUser(agentChannels, currentUser); ok {
		agentCreateChannel = currentChannel
	} else if strings.TrimSpace(agentCreateChannel.AdminUser) == "" {
		agentCreateChannel.AdminUser = defaultAgentWeChatAdminUser(state, currentUser)
	}
	data.AgentWeChatChannel = buildAdminAgentWeChatChannel(state, agentCreateChannel, entry, requestBase)
	data.AgentWeChatChannels = buildAdminAgentWeChatChannels(state, agentChannels, entry, requestBase)
	data.AgentTableGroups = buildAdminAgentTableGroups(state, s.listSchemaSnapshots(120))
	if active == "wechat-agent" {
		wechatMessages := buildAdminAgentWeChatMessageRowsByChannel(s.listAgentWeChatMessages(adminAgentWeChatChannelPreviewSource))
		for i := range data.AgentWeChatChannels {
			data.AgentWeChatChannels[i].ChatMessages = wechatMessages[data.AgentWeChatChannels[i].Key]
		}
	}
	if active == "wechat-agent-messages" {
		data.AgentWeChatMessagePage = s.buildAdminAgentWeChatMessagePage(query, entry, agentChannels)
	}
	if active == "ai-runs" {
		data.AgentRuns = buildAdminAgentRunRows(s.listAgentRuns(18))
	}
	if active == "ai-tasks" {
		agentTaskRecords := filterAgentTasksByActor(s.listAgentTasks(24), currentUser, 8)
		data.AgentTasks = buildAdminAgentTaskRows(agentTaskRecords)
		if currentTask := pickAdminCurrentAgentTask(agentTaskRecords); currentTask != nil {
			taskRow := buildAdminAgentTaskRow(*currentTask)
			data.CurrentAgentTask = &taskRow
			data.CurrentAgentTaskSteps = buildAdminAgentTaskStepRows(s.listAgentTaskSteps(currentTask.ID))
		}
	}
	data.UserSaveAction = entry + "/users/save"
	data.RoleSaveAction = entry + "/users/roles/save"
	data.AdminUsers = buildAdminUserRows(state, entry)
	data.AdminRoleMetrics = buildAdminRoleMetrics(state)
	adminSessions := s.listAdminSessions(40)
	data.AdminSessionMetrics = buildAdminSessionMetrics(adminSessions, currentSessionID)
	data.AdminSessions = buildAdminSessionRows(adminSessions, entry, currentSessionID)
	data.AdminRoles = buildAdminRoleRows(state)
	data.AdminPermissionMetrics = buildAdminPermissionMetrics(state)
	data.AdminMenus = buildAdminMenuRows(state)
	data.AdminPermissions = buildAdminPermissionRows(state)
	data.RoleMenuGroups = buildAdminRoleMenuGroups(state)
	data.RolePermissionGroups = buildAdminRolePermissionGroups(state)
	data.Settings = []adminSettingRow{
		{Key: "站点名称", Value: state.SiteName},
		{Key: "后台入口", Value: entry},
		{Key: "数据库", Value: state.Database.DisplayName() + " / " + state.Database.DisplayTarget()},
		{Key: "AI 服务", Value: state.AI.DisplayName() + " / " + state.AI.DisplayModel()},
		{Key: "会话有效期", Value: strconv.Itoa(security.SessionTTLHours) + " 小时"},
		{Key: "登录保护", Value: strconv.Itoa(security.LoginMaxAttempts) + " 次失败 / " + strconv.Itoa(security.LoginLockMinutes) + " 分钟"},
		{Key: "后台副标题", Value: system.AdminTagline},
		{Key: "首页副标题", Value: system.PublicTagline},
		{Key: "通知通道", Value: notifications.DisplayName()},
		{Key: "后台任务", Value: taskWorker.StatusText()},
		{Key: "时区", Value: system.Timezone},
		{Key: "语言", Value: system.Locale},
		{Key: "存储", Value: storage.DisplayName() + " / " + storage.LocalPath},
	}
	data.SystemSettings = adminSystemSettings{
		Action:            entry + "/settings/system",
		SiteName:          state.SiteName,
		Timezone:          system.Timezone,
		Locale:            system.Locale,
		AdminTagline:      system.AdminTagline,
		PublicTagline:     system.PublicTagline,
		PublicHeadline:    system.PublicHeadline,
		PublicDescription: system.PublicDescription,
	}
	data.StorageSettings = adminStorageSettings{
		Action:             entry + "/settings/storage",
		Driver:             storage.Driver,
		DriverName:         storage.DisplayName(),
		LocalPath:          storage.LocalPath,
		PublicURL:          storage.PublicURL,
		MaxFileSizeMB:      storage.MaxFileSizeMB,
		AllowedExtensions:  storage.AllowedExtensions,
		RetentionDays:      storage.AgentExportRetentionDays,
		PathStatus:         storagePathStatus(storage.LocalPath),
		PathStatusClass:    storagePathStatusClass(storage.LocalPath),
		AllowedDescription: storageAllowedDescription(storage.AllowedExtensions),
	}
	ai := state.AI.sanitized()
	data.AISettings = adminAISettings{
		Action:       entry + "/settings/ai",
		Provider:     ai.Provider,
		BaseURL:      ai.BaseURL,
		ChatModel:    ai.ChatModel,
		ImageModel:   ai.ImageModel,
		MaskedAPIKey: ai.maskedAPIKey(),
	}
	data.SecuritySettings = adminSecuritySettings{
		Action:           entry + "/settings/security",
		Username:         currentUser,
		Entry:            entry,
		SessionTTLHours:  security.SessionTTLHours,
		LoginMaxAttempts: security.LoginMaxAttempts,
		LoginLockMinutes: security.LoginLockMinutes,
	}
	data.NotificationSettings = adminNotificationSettings{
		Action:              entry + "/settings/notifications",
		TestAction:          entry + "/settings/notifications/test",
		Enabled:             notifications.Enabled,
		Channel:             notifications.Channel,
		ChannelName:         notifications.ChannelName(),
		Receiver:            notifications.Receiver,
		WebhookURL:          notifications.WebhookURL,
		FeishuSecretMasked:  maskSecretValue(notifications.FeishuSecret),
		EventLoginFailures:  notifications.EventLoginFailures,
		EventAIErrors:       notifications.EventAIErrors,
		EventStorageWarning: notifications.EventStorageWarning,
	}
	data.SettingChanges = buildAdminSettingChangeRows(s.listSettingChanges(12))
	data.FileUploadAction = entry + "/files/upload"
	data.FileRows = listAdminFiles(storage, entry, 200)
	data.FileMetrics = buildAdminFileMetrics(data.FileRows)
	data.FileStorageSummary = storage.DisplayName() + " / " + storage.LocalPath
	data.FileAllowedSummary = storageAllowedDescription(storage.AllowedExtensions) + "，单文件上限 " + strconv.Itoa(storage.MaxFileSizeMB) + " MB"
	data.TaskEnqueueAction = entry + "/tasks/enqueue"
	data.TaskRunAction = entry + "/tasks/run"
	data.TaskRunAllAction = entry + "/tasks/run-all"
	data.TaskWorkerSettings = adminTaskWorkerSettings{
		Action:                 entry + "/tasks/settings",
		Enabled:                taskWorker.Enabled,
		IntervalSeconds:        taskWorker.IntervalSeconds,
		BatchSize:              taskWorker.BatchSize,
		ScheduleHealthEnabled:  taskWorker.ScheduleHealthEnabled,
		ScheduleHealthMinutes:  taskWorker.ScheduleHealthMinutes,
		ScheduleCleanupEnabled: taskWorker.ScheduleCleanupEnabled,
		ScheduleCleanupMinutes: taskWorker.ScheduleCleanupMinutes,
		StatusText:             taskWorker.StatusText(),
	}
	data.TaskTypeOptions = backgroundTaskTypeOptions()
	data.TaskRows = buildAdminBackgroundTaskRows(taskRecords, entry)
	data.TaskLogRows = buildAdminBackgroundTaskLogRows(taskLogRecords)
	data.TaskMetrics = buildBackgroundTaskMetrics(taskRecords)
	data.FoundationServices = buildFoundationServices(state, len(auditEvents), len(data.FileRows), len(taskRecords))
	data.NotificationRows = buildAdminNotificationDeliveryRows(notificationRecords)
	data.NotificationMetrics = buildNotificationMetrics(notificationRecords)
	auditFilter.Action = entry + "/audit"
	auditFilter.ExportAction = entry + "/audit/export"
	auditFilter.ExportURL = auditExportURL(auditFilter)
	data.AuditFilter = auditFilter
	data.AuditEvents = auditEvents
	data.AuditMetrics = buildAuditMetrics(auditEvents)
	return data
}

func buildAdminNav(state installState, entry string, active string, access adminRoleAccess) []adminNavItem {
	currentSidebarKey := adminSidebarActiveKey(active)
	items := []adminNavItem{
		{Key: "dashboard", Label: "工作台", Href: entry + "/workspace"},
		{Key: "foundation", Label: "基础服务", Href: entry + "/foundation"},
		{Key: "data-sources", Label: "数据源", Href: entry + "/data-sources"},
		{Key: "extensions", Label: "能力扩展", Href: entry + "/extensions"},
		{Key: "ai", Label: "AI 智能体", Href: entry + "/ai"},
		{Key: "wechat-agent", Label: "微信 Agent", Href: entry + "/wechat-agent"},
		{Key: "wechat-agent-messages", Label: "微信聊天", Href: entry + "/wechat-agent/messages"},
		{Key: "users", Label: "管理员账号", Href: entry + "/users"},
		{Key: "user-groups", Label: "用户组权限", Href: entry + "/users/groups"},
		{Key: "user-sessions", Label: "登录会话", Href: entry + "/users/sessions"},
		{Key: "user-permissions", Label: "菜单权限", Href: entry + "/users/permissions"},
		{Key: "settings", Label: "系统设置", Href: entry + "/settings"},
		{Key: "files", Label: "文件管理", Href: entry + "/files"},
		{Key: "tasks", Label: "后台任务", Href: entry + "/tasks"},
		{Key: "notifications", Label: "通知事件", Href: entry + "/notifications"},
		{Key: "audit", Label: "审计日志", Href: entry + "/audit"},
	}
	filtered := make([]adminNavItem, 0, len(items))
	for i := range items {
		if !access.CanViewPage(items[i].Key) {
			continue
		}
		items[i].Active = strings.TrimSpace(items[i].Key) == currentSidebarKey
		filtered = append(filtered, items[i])
	}
	_ = state
	return filtered
}

func buildAdminNavGroups(state installState, entry string, active string, access adminRoleAccess) []adminNavGroup {
	currentSidebarKey := adminSidebarActiveKey(active)
	groups := []adminNavGroup{
		{
			Label: "总览",
			Items: []adminNavItem{
				{Key: "dashboard", Label: "工作台", Href: entry + "/workspace"},
			},
		},
		{
			Label: "基础设施",
			Items: []adminNavItem{
				{Key: "foundation", Label: "基础服务", Href: entry + "/foundation"},
				{Key: "data-sources", Label: "数据源", Href: entry + "/data-sources"},
				{Key: "files", Label: "文件管理", Href: entry + "/files"},
				{Key: "tasks", Label: "后台任务", Href: entry + "/tasks"},
			},
		},
		{
			Label: "智能体",
			Items: []adminNavItem{
				{Key: "ai", Label: "AI 智能体", Href: entry + "/ai"},
				{Key: "wechat-agent", Label: "微信 Agent", Href: entry + "/wechat-agent"},
				{Key: "wechat-agent-messages", Label: "微信聊天", Href: entry + "/wechat-agent/messages"},
				{Key: "extensions", Label: "能力扩展", Href: entry + "/extensions"},
			},
		},
		{
			Label: "访问控制",
			Items: []adminNavItem{
				{Key: "users", Label: "管理员账号", Href: entry + "/users"},
				{Key: "user-groups", Label: "用户组权限", Href: entry + "/users/groups"},
				{Key: "user-sessions", Label: "登录会话", Href: entry + "/users/sessions"},
				{Key: "user-permissions", Label: "菜单权限", Href: entry + "/users/permissions"},
			},
		},
		{
			Label: "系统运维",
			Items: []adminNavItem{
				{Key: "settings", Label: "系统设置", Href: entry + "/settings"},
				{Key: "notifications", Label: "通知事件", Href: entry + "/notifications"},
				{Key: "audit", Label: "审计日志", Href: entry + "/audit"},
			},
		},
	}
	filtered := make([]adminNavGroup, 0, len(groups))
	for groupIndex := range groups {
		items := make([]adminNavItem, 0, len(groups[groupIndex].Items))
		groupActive := false
		for itemIndex := range groups[groupIndex].Items {
			if !access.CanViewPage(groups[groupIndex].Items[itemIndex].Key) {
				continue
			}
			activeItem := strings.TrimSpace(groups[groupIndex].Items[itemIndex].Key) == currentSidebarKey
			groups[groupIndex].Items[itemIndex].Active = activeItem
			if activeItem {
				groupActive = true
			}
			items = append(items, groups[groupIndex].Items[itemIndex])
		}
		if len(items) == 0 {
			continue
		}
		groups[groupIndex].Items = items
		groups[groupIndex].Active = groupActive
		filtered = append(filtered, groups[groupIndex])
	}
	_ = state
	return filtered
}

func buildAdminRoleMenuGroups(state installState) []adminRoleMenuGroup {
	access := state.Access.normalized(state)
	menuByKey := make(map[string]adminMenuConfig, len(access.Menus))
	for _, menu := range access.Menus {
		menuByKey[strings.ToLower(strings.TrimSpace(menu.Key))] = menu
	}
	type groupDef struct {
		Title       string
		Description string
		Keys        []string
	}
	groupDefs := []groupDef{
		{Title: "总览", Description: "默认登录入口与整体概览页面。", Keys: []string{"dashboard"}},
		{Title: "基础设施", Description: "数据源、文件、任务等基础能力入口。", Keys: []string{"foundation", "data_sources", "files", "tasks"}},
		{Title: "智能体", Description: "AI 工作台、微信 Agent 和能力扩展。", Keys: []string{"ai", "wechat_agent", "extensions"}},
		{Title: "访问控制", Description: "管理员、用户组、会话与权限清单。", Keys: []string{"users", "user_groups", "user_sessions", "user_permissions"}},
		{Title: "系统运维", Description: "系统设置、通知和审计日志。", Keys: []string{"settings", "notifications", "audit"}},
	}
	groups := make([]adminRoleMenuGroup, 0, len(groupDefs))
	for _, def := range groupDefs {
		items := make([]adminRoleMenuOption, 0, len(def.Keys))
		for _, key := range def.Keys {
			menu, ok := menuByKey[strings.ToLower(strings.TrimSpace(key))]
			if !ok {
				continue
			}
			statusKey := strings.ToLower(strings.TrimSpace(menu.Status))
			if statusKey == "" {
				statusKey = "enabled"
			}
			items = append(items, adminRoleMenuOption{
				Key:         menu.Key,
				Label:       menu.Label,
				Path:        menu.Path,
				Status:      accessStatusText(statusKey),
				StatusClass: accessStatusClass(statusKey),
				Description: "访问路径：" + menu.Path,
			})
		}
		if len(items) == 0 {
			continue
		}
		groups = append(groups, adminRoleMenuGroup{
			Title:       def.Title,
			Description: def.Description,
			Items:       items,
		})
	}
	return groups
}

func buildAdminRolePermissionGroups(state installState) []adminRolePermissionGroup {
	access := state.Access.normalized(state)
	type groupDef struct {
		Title       string
		Description string
		Prefix      string
	}
	groupDefs := []groupDef{
		{Title: "后台动作", Description: "控制账号、用户组、设置、文件、任务等后台操作。", Prefix: "admin."},
		{Title: "智能体动作", Description: "控制微信 Agent 和数据查询类工具能力。", Prefix: "agent."},
	}
	groups := make([]adminRolePermissionGroup, 0, len(groupDefs))
	for _, def := range groupDefs {
		items := make([]adminRolePermissionOption, 0, len(access.Permissions))
		for _, permission := range access.Permissions {
			if !strings.HasPrefix(strings.ToLower(strings.TrimSpace(permission.Key)), def.Prefix) {
				continue
			}
			statusKey := strings.ToLower(strings.TrimSpace(permission.Status))
			if statusKey == "" {
				statusKey = "enabled"
			}
			items = append(items, adminRolePermissionOption{
				Key:         permission.Key,
				Label:       permission.Permission + " / " + permission.Subject,
				Subject:     permission.Subject,
				Status:      accessStatusText(statusKey),
				StatusClass: accessStatusClass(statusKey),
				Description: permission.Boundary,
			})
		}
		if len(items) == 0 {
			continue
		}
		groups = append(groups, adminRolePermissionGroup{
			Title:       def.Title,
			Description: def.Description,
			Items:       items,
		})
	}
	return groups
}

func buildFoundationServices(state installState, auditCount int, fileCount int, taskCount int) []adminFoundationService {
	storage := state.Storage.normalized()
	return []adminFoundationService{
		{Name: "初始化安装", Legacy: "InstallController / install 视图", Current: "首页安装向导、数据库检查、AI 配置检查、元数据表创建", Status: "已迁移", StatusClass: "is-ready", Next: "补安装向导重置保护"},
		{Name: "隐藏后台入口", Legacy: "AdminEntryMiddleware", Current: "初始化时随机生成 " + state.AdminEntry, Status: "已迁移", StatusClass: "is-ready", Next: "保持初始化随机入口，不做自动轮换"},
		{Name: "管理员认证", Legacy: "AuthController / AdminAuthMiddleware", Current: "超级管理员登录、会话记录、强制下线、改密、失败锁定", Status: "已迁移", StatusClass: "is-ready", Next: "补设备标记与登录地展示"},
		{Name: "系统设置", Legacy: "SiteController / AdminConfig", Current: "站点资料、首页展示、语言、时区、AI、存储、安全与通知策略", Status: "已接入", StatusClass: "is-ready", Next: "补设置分组与变更历史"},
		{Name: "存储与文件", Legacy: "UploadFileController / StorageEngine", Current: storage.DisplayName() + "，当前文件 " + strconv.Itoa(fileCount) + " 个", Status: "已接入", StatusClass: "is-ready", Next: "补 S3/OSS 驱动与文件审查"},
		{Name: "运行审计", Legacy: "LoginLog / OperationLog / InterceptLog / ErrorStatistic", Current: "SQLite 审计表、通知发送记录、筛选与 CSV 导出，当前审计 " + strconv.Itoa(auditCount) + " 条", Status: "已接入", StatusClass: "is-ready", Next: "补审计详情页"},
		{Name: "后台任务", Legacy: "AsyncQueue / QueueHandleListener", Current: "SQLite 队列表、常驻 Worker、定时体检/清理、重试与取消，当前任务 " + strconv.Itoa(taskCount) + " 个", Status: "已接入", StatusClass: "is-ready", Next: "补任务依赖、失败策略和详情页"},
		{Name: "数据源连接", Legacy: "DatabaseConnectionController", Current: "连接登记、真实登录检查、SQLite/MySQL/PostgreSQL 结构与注释扫描、智能体只读读取", Status: "已接入", StatusClass: "is-ready", Next: "补 Schema 快照与变更对比"},
		{Name: "用户角色权限", Legacy: "User / Role / Permission / Menu", Current: "管理员账号、角色、菜单、权限清单持久化", Status: "已接入", StatusClass: "is-ready", Next: "补细粒度权限拦截与页面级守卫"},
		{Name: "插件扩展", Legacy: "AddonController / Addons*Service", Current: "Go 端扩展包规范、资源模型注册表、后台能力扩展页", Status: "持续完善", StatusClass: "is-progress", Next: "补插件配置、启停、版本迁移与发布链路"},
		{Name: "CRUD 生成", Legacy: "CrudGenerator / UniversalCrud", Current: "资源模型驱动 AI 工具生成，替代传统表单 CRUD", Status: "持续接入", StatusClass: "is-progress", Next: "继续接入真实业务资源、导出与多表只读查询"},
	}
}

func buildAuditMetrics(events []adminAuditEvent) []adminMetric {
	counts := map[string]int{}
	for _, event := range events {
		category := strings.TrimSpace(event.Category)
		if category == "" {
			category = "operation"
		}
		counts[category]++
	}
	return []adminMetric{
		{Label: "审计事件", Value: strconv.Itoa(len(events)), Detail: "最近记录", Status: "is-ready"},
		{Label: "登录日志", Value: strconv.Itoa(counts["login"]), Detail: "成功与失败登录", Status: auditMetricStatus(counts["login"])},
		{Label: "文件操作", Value: strconv.Itoa(counts["file"]), Detail: "上传与删除", Status: auditMetricStatus(counts["file"])},
		{Label: "智能体调用", Value: strconv.Itoa(counts["ai"]), Detail: "后台 AI 对话", Status: auditMetricStatus(counts["ai"])},
	}
}

func buildNotificationMetrics(records []adminNotificationDeliveryRecord) []adminMetric {
	sent := 0
	failed := 0
	events := map[string]bool{}
	for _, record := range records {
		if strings.TrimSpace(record.Event) != "" {
			events[record.Event] = true
		}
		switch strings.ToLower(strings.TrimSpace(record.Status)) {
		case "sent":
			sent++
		case "failed":
			failed++
		}
	}
	failureStatus := "is-ready"
	if failed > 0 {
		failureStatus = "is-warning"
	}
	return []adminMetric{
		{Label: "通知记录", Value: strconv.Itoa(len(records)), Detail: "最近发送", Status: "is-ready"},
		{Label: "发送成功", Value: strconv.Itoa(sent), Detail: "通道已接收", Status: auditMetricStatus(sent)},
		{Label: "发送失败", Value: strconv.Itoa(failed), Detail: "需要检查通道", Status: failureStatus},
		{Label: "事件类型", Value: strconv.Itoa(len(events)), Detail: "已触发类型", Status: auditMetricStatus(len(events))},
	}
}

func buildBackgroundTaskMetrics(records []adminBackgroundTaskRecord) []adminMetric {
	pending := 0
	running := 0
	failed := 0
	succeeded := 0
	canceled := 0
	for _, record := range records {
		switch strings.ToLower(strings.TrimSpace(record.Status)) {
		case "pending", "retry":
			pending++
		case "running":
			running++
		case "failed":
			failed++
		case "succeeded":
			succeeded++
		case "canceled":
			canceled++
		}
	}
	failureStatus := "is-ready"
	if failed > 0 {
		failureStatus = "is-warning"
	}
	return []adminMetric{
		{Label: "任务总数", Value: strconv.Itoa(len(records)), Detail: "最近任务记录", Status: auditMetricStatus(len(records))},
		{Label: "待执行", Value: strconv.Itoa(pending), Detail: "pending / retry", Status: auditMetricStatus(pending)},
		{Label: "执行中", Value: strconv.Itoa(running), Detail: "当前运行", Status: auditMetricStatus(running)},
		{Label: "失败", Value: strconv.Itoa(failed), Detail: "可重试任务", Status: failureStatus},
		{Label: "已完成", Value: strconv.Itoa(succeeded), Detail: "成功结束", Status: auditMetricStatus(succeeded)},
		{Label: "已取消", Value: strconv.Itoa(canceled), Detail: "管理员取消", Status: auditMetricStatus(canceled)},
	}
}

func buildAdminDataSourceMetrics(rows []adminDataSource) []adminMetric {
	total := len(rows)
	custom := 0
	available := 0
	pending := 0
	system := 0
	for _, row := range rows {
		if row.Editable {
			custom++
		} else {
			system++
		}
		switch row.StatusKey {
		case "available":
			available++
		case "unavailable":
			// keep reflected by the status badge in the list
		default:
			pending++
		}
	}
	return []adminMetric{
		{Label: "数据源总数", Value: strconv.Itoa(total), Detail: "系统内置与手动登记", Status: auditMetricStatus(total)},
		{Label: "自定义数据源", Value: strconv.Itoa(custom), Detail: "可以测试和删除", Status: auditMetricStatus(custom)},
		{Label: "连接可用", Value: strconv.Itoa(available), Detail: "最近一次探测通过", Status: auditMetricStatus(available)},
		{Label: "待复核", Value: strconv.Itoa(pending), Detail: "未测试、参考源或需要处理", Status: pendingMetricStatus(pending)},
		{Label: "系统内置", Value: strconv.Itoa(system), Detail: "元数据与迁移参考", Status: auditMetricStatus(system)},
	}
}

func buildAdminFileMetrics(rows []adminFileRow) []adminMetric {
	total := len(rows)
	var totalSize int64
	imageCount := 0
	documentCount := 0
	otherCount := 0
	for _, row := range rows {
		totalSize += row.SizeBytes
		switch row.KindKey {
		case "image":
			imageCount++
		case "pdf", "spreadsheet", "text":
			documentCount++
		default:
			otherCount++
		}
	}
	return []adminMetric{
		{Label: "文件总数", Value: strconv.Itoa(total), Detail: "当前本地存储中的文件", Status: auditMetricStatus(total)},
		{Label: "总占用", Value: formatAdminFileSize(totalSize), Detail: "按当前列表累计", Status: auditMetricStatus(total)},
		{Label: "图片资源", Value: strconv.Itoa(imageCount), Detail: "可直接预览的图片文件", Status: auditMetricStatus(imageCount)},
		{Label: "文档资料", Value: strconv.Itoa(documentCount), Detail: "PDF、表格与文本资料", Status: auditMetricStatus(documentCount)},
		{Label: "其他类型", Value: strconv.Itoa(otherCount), Detail: "其余扩展名文件", Status: auditMetricStatus(otherCount)},
	}
}

func buildAdminBackgroundTaskRows(records []adminBackgroundTaskRecord, entry string) []adminBackgroundTaskRow {
	rows := make([]adminBackgroundTaskRow, 0, len(records))
	now := time.Now().UTC()
	for _, record := range records {
		status := strings.ToLower(strings.TrimSpace(record.Status))
		canRun := (status == "pending" || status == "retry") && (record.AvailableAt.IsZero() || !record.AvailableAt.After(now))
		canRetry := status == "failed" || status == "retry"
		canCancel := status == "pending" || status == "retry"
		startedAt := ""
		if !record.StartedAt.IsZero() {
			startedAt = formatAdminTime(record.StartedAt)
		}
		finishedAt := ""
		if !record.FinishedAt.IsZero() {
			finishedAt = formatAdminTime(record.FinishedAt)
		}
		rows = append(rows, adminBackgroundTaskRow{
			Initial:      adminUserInitial(record.Name, record.Type),
			ID:           record.ID,
			IDShort:      shortTaskID(record.ID),
			Name:         record.Name,
			Type:         record.Type,
			TypeName:     backgroundTaskTypeName(record.Type),
			Queue:        displayNotificationValue(record.Queue, "default"),
			StatusKey:    status,
			Status:       backgroundTaskStatusLabel(record.Status),
			StatusClass:  backgroundTaskStatusClass(record.Status),
			Attempts:     strconv.Itoa(record.Attempts) + "/" + strconv.Itoa(record.MaxAttempts),
			CreatedBy:    displayNotificationValue(record.CreatedBy, "system"),
			CreatedAt:    formatAdminTime(record.CreatedAt),
			AvailableAt:  formatAdminTime(record.AvailableAt),
			StartedAt:    startedAt,
			FinishedAt:   finishedAt,
			Result:       truncateAuditText(record.Result, 140),
			LastError:    truncateAuditText(record.LastError, 140),
			RunAction:    entry + "/tasks/run",
			RetryAction:  entry + "/tasks/retry",
			CancelAction: entry + "/tasks/cancel",
			CanRun:       canRun,
			CanRetry:     canRetry,
			CanCancel:    canCancel,
		})
	}
	return rows
}

func buildAdminBackgroundTaskLogRows(records []adminBackgroundTaskLogRecord) []adminBackgroundTaskLogRow {
	rows := make([]adminBackgroundTaskLogRow, 0, len(records))
	for _, record := range records {
		rows = append(rows, adminBackgroundTaskLogRow{
			Time:        formatAdminTime(record.Timestamp),
			TaskID:      record.TaskID,
			TaskIDShort: shortTaskID(record.TaskID),
			Level:       backgroundTaskLogLevelLabel(record.Level),
			LevelClass:  backgroundTaskLogLevelClass(record.Level),
			Event:       backgroundTaskEventLabel(record.Event),
			Status:      backgroundTaskStatusLabel(record.Status),
			Attempt:     record.Attempt,
			Message:     truncateAuditText(record.Message, 180),
		})
	}
	return rows
}

func backgroundTaskTypeOptions() []adminTaskTypeOption {
	return []adminTaskTypeOption{
		{Type: "system_health_check", Name: "系统体检", Description: "检查初始化状态、存储目录、数据源登记和 AI 配置"},
		{Type: "storage_cleanup", Name: "AI 导出清理", Description: "按存储设置的保留天数清理过期智能体导出文件"},
		{Type: "notification_test", Name: "测试通知", Description: "通过当前通知通道发送一条后台测试通知"},
	}
}

func backgroundTaskTypeName(taskType string) string {
	for _, option := range backgroundTaskTypeOptions() {
		if option.Type == strings.TrimSpace(taskType) {
			return option.Name
		}
	}
	if strings.TrimSpace(taskType) == "" {
		return "未知任务"
	}
	return taskType
}

func backgroundTaskStatusLabel(status string) string {
	switch strings.ToLower(strings.TrimSpace(status)) {
	case "pending":
		return "待执行"
	case "retry":
		return "等待重试"
	case "running":
		return "执行中"
	case "succeeded":
		return "已完成"
	case "failed":
		return "失败"
	case "canceled":
		return "已取消"
	default:
		return "未知"
	}
}

func backgroundTaskStatusClass(status string) string {
	switch strings.ToLower(strings.TrimSpace(status)) {
	case "pending", "retry":
		return "is-progress"
	case "running":
		return "is-secure"
	case "succeeded":
		return "is-ready"
	case "failed":
		return "is-warning"
	case "canceled":
		return "is-muted"
	default:
		return "is-muted"
	}
}

func backgroundTaskLogLevelLabel(level string) string {
	switch strings.ToLower(strings.TrimSpace(level)) {
	case "error":
		return "错误"
	case "warn", "warning":
		return "警告"
	default:
		return "信息"
	}
}

func backgroundTaskLogLevelClass(level string) string {
	switch strings.ToLower(strings.TrimSpace(level)) {
	case "error":
		return "is-warning"
	case "warn", "warning":
		return "is-progress"
	default:
		return "is-ready"
	}
}

func backgroundTaskEventLabel(event string) string {
	switch strings.TrimSpace(event) {
	case "queued":
		return "入队"
	case "started":
		return "开始执行"
	case "succeeded":
		return "执行成功"
	case "failed":
		return "执行失败"
	case "retry_scheduled":
		return "安排重试"
	case "retry":
		return "重新入队"
	case "canceled":
		return "取消"
	default:
		return displayNotificationValue(event, "任务事件")
	}
}

func shortTaskID(id string) string {
	id = strings.TrimSpace(id)
	if len(id) <= 18 {
		return id
	}
	return id[:9] + "..." + id[len(id)-6:]
}

func newBackgroundTaskRecord(taskType string, actor string) (adminBackgroundTaskRecord, error) {
	taskType = strings.TrimSpace(taskType)
	var option adminTaskTypeOption
	for _, candidate := range backgroundTaskTypeOptions() {
		if candidate.Type == taskType {
			option = candidate
			break
		}
	}
	if option.Type == "" {
		return adminBackgroundTaskRecord{}, errors.New("请选择有效的任务类型")
	}
	now := time.Now().UTC()
	return adminBackgroundTaskRecord{
		ID:          newBackgroundTaskID(),
		Name:        option.Name,
		Type:        option.Type,
		Queue:       "default",
		Status:      "pending",
		Priority:    backgroundTaskDefaultPriority(option.Type),
		MaxAttempts: 3,
		PayloadJSON: backgroundTaskPayloadJSON("admin_console", option.Description),
		CreatedBy:   displayNotificationValue(actor, "system"),
		CreatedAt:   now,
		AvailableAt: now,
		UpdatedAt:   now,
	}, nil
}

func backgroundTaskPayloadJSON(source string, description string) string {
	payload, _ := json.Marshal(map[string]string{
		"created_from": strings.TrimSpace(source),
		"description":  strings.TrimSpace(description),
	})
	return string(payload)
}

func newBackgroundTaskID() string {
	bytes := make([]byte, 12)
	if _, err := rand.Read(bytes); err != nil {
		return "task_" + strconv.FormatInt(time.Now().UnixNano(), 36)
	}
	return "task_" + hex.EncodeToString(bytes)
}

func backgroundTaskDefaultPriority(taskType string) int {
	switch strings.TrimSpace(taskType) {
	case "system_health_check":
		return 20
	case "notification_test":
		return 10
	default:
		return 0
	}
}

func (s *adminServer) runnableBackgroundTask(taskID string) (adminBackgroundTaskRecord, error) {
	now := time.Now().UTC()
	var task adminBackgroundTaskRecord
	var err error
	if strings.TrimSpace(taskID) == "" {
		task, err = s.store.NextRunnableBackgroundTask(now)
		if errors.Is(err, sql.ErrNoRows) {
			return adminBackgroundTaskRecord{}, errors.New("暂无可执行的后台任务")
		}
	} else {
		task, err = s.store.BackgroundTaskByID(taskID)
		if errors.Is(err, sql.ErrNoRows) {
			return adminBackgroundTaskRecord{}, errors.New("任务不存在")
		}
	}
	if err != nil {
		return adminBackgroundTaskRecord{}, err
	}
	status := strings.ToLower(strings.TrimSpace(task.Status))
	if status != "pending" && status != "retry" {
		return adminBackgroundTaskRecord{}, errors.New("任务当前状态不能执行：" + backgroundTaskStatusLabel(task.Status))
	}
	if !task.AvailableAt.IsZero() && task.AvailableAt.After(now) {
		return adminBackgroundTaskRecord{}, errors.New("任务尚未到可执行时间：" + formatAdminTime(task.AvailableAt))
	}
	return task, nil
}

func (s *adminServer) runBackgroundTask(ctx context.Context, state installState, task adminBackgroundTaskRecord) adminBackgroundTaskRecord {
	s.taskMu.Lock()
	defer s.taskMu.Unlock()
	return s.executeBackgroundTask(ctx, state, task)
}

func (s *adminServer) executeBackgroundTask(ctx context.Context, state installState, task adminBackgroundTaskRecord) adminBackgroundTaskRecord {
	if task.MaxAttempts <= 0 {
		task.MaxAttempts = 3
	}
	now := time.Now().UTC()
	task.Status = "running"
	task.Attempts++
	task.StartedAt = now
	task.FinishedAt = time.Time{}
	task.LastError = ""
	task.UpdatedAt = now
	_ = s.store.UpdateBackgroundTask(task)
	s.appendBackgroundTaskLog(task, "info", "started", "任务开始执行")

	result, err := s.performBackgroundTask(ctx, state, task)
	finishedAt := time.Now().UTC()
	task.FinishedAt = finishedAt
	task.UpdatedAt = finishedAt
	task.Result = truncateAuditText(result, 1500)
	if err != nil {
		task.LastError = truncateAuditText(err.Error(), 1000)
		if task.Attempts < task.MaxAttempts {
			task.Status = "retry"
			task.AvailableAt = finishedAt.Add(time.Duration(task.Attempts) * time.Minute)
			s.appendBackgroundTaskLog(task, "warn", "retry_scheduled", "任务执行失败，已安排重试："+task.LastError)
		} else {
			task.Status = "failed"
			task.AvailableAt = finishedAt
			s.appendBackgroundTaskLog(task, "error", "failed", "任务执行失败："+task.LastError)
		}
		return task
	}
	task.Status = "succeeded"
	task.LastError = ""
	task.AvailableAt = finishedAt
	s.appendBackgroundTaskLog(task, "info", "succeeded", displayNotificationValue(task.Result, "任务执行成功"))
	return task
}

func (s *adminServer) appendBackgroundTaskLog(task adminBackgroundTaskRecord, level string, event string, message string) {
	record := adminBackgroundTaskLogRecord{
		TaskID:    task.ID,
		Timestamp: time.Now().UTC(),
		Level:     strings.ToLower(strings.TrimSpace(level)),
		Event:     strings.TrimSpace(event),
		Message:   truncateAuditText(message, 1000),
		Status:    task.Status,
		Attempt:   task.Attempts,
	}
	if record.Level == "" {
		record.Level = "info"
	}
	if record.Event == "" {
		record.Event = "task_event"
	}
	if record.Message == "" {
		record.Message = backgroundTaskStatusLabel(task.Status)
	}
	_ = s.store.AppendBackgroundTaskLog(record)
}

type backgroundTaskWorkerResult struct {
	Enqueued  int
	Completed int
	Failed    int
}

func (s *adminServer) startBackgroundTaskWorker() {
	go func() {
		timer := time.NewTimer(2 * time.Second)
		defer timer.Stop()
		for {
			<-timer.C
			_, interval := s.runBackgroundTaskWorkerOnce(context.Background(), time.Now().UTC())
			if interval < 5*time.Second {
				interval = 5 * time.Second
			}
			timer.Reset(interval)
		}
	}()
}

func (s *adminServer) runBackgroundTaskWorkerOnce(ctx context.Context, now time.Time) (backgroundTaskWorkerResult, time.Duration) {
	state, err := s.store.Load()
	if err != nil || !state.Initialized {
		return backgroundTaskWorkerResult{}, 30 * time.Second
	}
	config := state.TaskWorker.normalized()
	interval := config.workerInterval()
	if !config.Enabled {
		return backgroundTaskWorkerResult{}, interval
	}
	result := backgroundTaskWorkerResult{}
	result.Enqueued = s.enqueueScheduledBackgroundTasks(state, config, now)
	completed, failed := s.runReadyBackgroundTasks(ctx, state, config.BatchSize)
	result.Completed = completed
	result.Failed = failed
	return result, interval
}

func (s *adminServer) enqueueScheduledBackgroundTasks(state installState, config taskWorkerConfig, now time.Time) int {
	enqueued := 0
	if config.ScheduleHealthEnabled && s.enqueueScheduledBackgroundTask("system_health_check", config.ScheduleHealthMinutes, now) {
		enqueued++
	}
	if config.ScheduleCleanupEnabled && s.enqueueScheduledBackgroundTask("storage_cleanup", config.ScheduleCleanupMinutes, now) {
		enqueued++
	}
	return enqueued
}

func (s *adminServer) enqueueScheduledBackgroundTask(taskType string, intervalMinutes int, now time.Time) bool {
	if intervalMinutes <= 0 {
		return false
	}
	since := now.Add(-time.Duration(intervalMinutes) * time.Minute)
	exists, err := s.store.BackgroundTaskExistsSince(taskType, since)
	if err != nil || exists {
		return false
	}
	task, err := newBackgroundTaskRecord(taskType, "scheduler")
	if err != nil {
		return false
	}
	task.PayloadJSON = backgroundTaskPayloadJSON("scheduler", backgroundTaskTypeName(taskType))
	task.CreatedAt = now
	task.AvailableAt = now
	task.UpdatedAt = now
	if err := s.store.EnqueueBackgroundTask(task); err != nil {
		return false
	}
	s.appendBackgroundTaskLog(task, "info", "queued", "定时调度已加入任务队列")
	return true
}

func (s *adminServer) runReadyBackgroundTasks(ctx context.Context, state installState, batchSize int) (int, int) {
	if batchSize <= 0 {
		batchSize = defaultTaskWorkerConfig().BatchSize
	}
	completed := 0
	failed := 0
	for completed+failed < batchSize {
		task, err := s.runnableBackgroundTask("")
		if err != nil {
			return completed, failed
		}
		task = s.runBackgroundTask(ctx, state, task)
		if err := s.store.UpdateBackgroundTask(task); err != nil {
			failed++
			continue
		}
		if task.Status == "succeeded" {
			completed++
		} else {
			failed++
		}
	}
	return completed, failed
}

func (s *adminServer) performBackgroundTask(ctx context.Context, state installState, task adminBackgroundTaskRecord) (string, error) {
	switch strings.TrimSpace(task.Type) {
	case "system_health_check":
		return s.performSystemHealthTask(state)
	case "storage_cleanup":
		return s.cleanupAgentExportFiles(state)
	case "notification_test":
		ctx, cancel := context.WithTimeout(ctx, 15*time.Second)
		defer cancel()
		result, err := s.deliverAdminNotification(ctx, state, "notification_test", "Moyi Admin 后台任务测试通知", "后台任务队列已成功执行测试通知任务。")
		if err != nil {
			return result.Message, err
		}
		return result.Message, nil
	default:
		return "", errors.New("未知后台任务类型：" + task.Type)
	}
}

func (s *adminServer) performSystemHealthTask(state installState) (string, error) {
	messages := []string{"初始化状态：已完成", "后台入口：" + s.adminEntryForState(state)}
	storage := state.Storage.normalized()
	if err := validateStorageConfig(storage); err != nil {
		return strings.Join(messages, "；"), err
	}
	root, err := storageLocalRoot(storage)
	if err != nil {
		return strings.Join(messages, "；"), err
	}
	if info, err := os.Stat(root); err != nil {
		return strings.Join(messages, "；"), errors.New("存储目录不可用：" + err.Error())
	} else if !info.IsDir() {
		return strings.Join(messages, "；"), errors.New("存储路径不是目录：" + root)
	}
	messages = append(messages, "存储目录：可用")
	messages = append(messages, "数据源："+strconv.Itoa(len(state.DataSources))+" 个")
	if state.AI.IsDisabled() {
		messages = append(messages, "AI：未启用")
	} else {
		messages = append(messages, "AI："+state.AI.DisplayName()+" / "+state.AI.DisplayModel())
	}
	messages = append(messages, "管理员账号："+strconv.Itoa(len(state.Access.normalized(state).Users))+" 个")
	return strings.Join(messages, "；"), nil
}

func (s *adminServer) cleanupAgentExportFiles(state installState) (string, error) {
	retentionDays := state.Storage.normalized().AgentExportRetentionDays
	if retentionDays <= 0 {
		retentionDays = defaultStorageConfig().AgentExportRetentionDays
	}
	dir := s.agentExportDir()
	entries, err := os.ReadDir(dir)
	if os.IsNotExist(err) {
		return "导出目录不存在，无需清理：" + dir, nil
	}
	if err != nil {
		return "", err
	}
	cutoff := time.Now().Add(-time.Duration(retentionDays) * 24 * time.Hour)
	removed := 0
	skipped := 0
	for _, entry := range entries {
		if entry.IsDir() {
			continue
		}
		name := entry.Name()
		if !strings.HasPrefix(name, "moyi-agent-export-") {
			skipped++
			continue
		}
		if _, ok := agentExportContentType(name); !ok {
			skipped++
			continue
		}
		info, err := entry.Info()
		if err != nil {
			skipped++
			continue
		}
		if info.ModTime().After(cutoff) {
			skipped++
			continue
		}
		if err := os.Remove(filepath.Join(dir, name)); err != nil {
			return "已清理 " + strconv.Itoa(removed) + " 个文件", err
		}
		removed++
	}
	return "已按 " + strconv.Itoa(retentionDays) + " 天保留策略清理 AI 导出文件 " + strconv.Itoa(removed) + " 个，跳过 " + strconv.Itoa(skipped) + " 个。", nil
}

func auditFilterFromQuery(query url.Values) adminAuditFilter {
	return adminAuditFilter{
		Category: strings.ToLower(strings.TrimSpace(query.Get("category"))),
		Status:   strings.ToLower(strings.TrimSpace(query.Get("status"))),
		Keyword:  strings.TrimSpace(query.Get("keyword")),
	}
}

func (f adminAuditFilter) active() bool {
	return f.Category != "" || f.Status != "" || f.Keyword != ""
}

func auditExportURL(filter adminAuditFilter) string {
	if filter.ExportAction == "" {
		return ""
	}
	values := url.Values{}
	if filter.Category != "" {
		values.Set("category", filter.Category)
	}
	if filter.Status != "" {
		values.Set("status", filter.Status)
	}
	if filter.Keyword != "" {
		values.Set("keyword", filter.Keyword)
	}
	if len(values) == 0 {
		return filter.ExportAction
	}
	return filter.ExportAction + "?" + values.Encode()
}

func filterAuditEvents(events []adminAuditEvent, filter adminAuditFilter) []adminAuditEvent {
	if !filter.active() {
		return events
	}
	filtered := make([]adminAuditEvent, 0, len(events))
	for _, event := range events {
		if auditEventMatchesFilter(event, filter) {
			filtered = append(filtered, event)
		}
	}
	return filtered
}

func auditEventMatchesFilter(event adminAuditEvent, filter adminAuditFilter) bool {
	if filter.Category != "" && strings.ToLower(strings.TrimSpace(event.Category)) != filter.Category {
		return false
	}
	if filter.Status != "" && !auditStatusMatches(event.Status, filter.Status) {
		return false
	}
	keyword := strings.ToLower(strings.TrimSpace(filter.Keyword))
	if keyword == "" {
		return true
	}
	haystack := strings.ToLower(strings.Join([]string{
		event.Time,
		event.Category,
		event.Action,
		event.Actor,
		event.Detail,
		event.Method,
		event.Path,
		event.IP,
		event.Status,
		event.Duration,
	}, " "))
	return strings.Contains(haystack, keyword)
}

func auditStatusMatches(status string, filter string) bool {
	code, err := strconv.Atoi(strings.TrimSpace(status))
	if err == nil {
		switch filter {
		case "success":
			return code >= 200 && code < 400
		case "warning":
			return code >= 400 && code < 500
		case "error":
			return code >= 500
		default:
			expected, expectedErr := strconv.Atoi(filter)
			return expectedErr == nil && code == expected
		}
	}
	return filter == strings.ToLower(strings.TrimSpace(status))
}

func buildAdminNotificationDeliveryRows(records []adminNotificationDeliveryRecord) []adminNotificationDeliveryRow {
	rows := make([]adminNotificationDeliveryRow, 0, len(records))
	for _, record := range records {
		rows = append(rows, adminNotificationDeliveryRow{
			Time:        formatAdminTime(record.Timestamp),
			Event:       record.Event,
			Title:       record.Title,
			Receiver:    displayNotificationValue(record.Receiver, "-"),
			Channel:     notificationChannelLabel(record.Channel),
			Target:      displayNotificationValue(record.Target, "-"),
			Message:     record.Message,
			Status:      notificationDeliveryStatusLabel(record.Status, record.StatusCode),
			StatusClass: notificationDeliveryStatusClass(record.Status),
			Error:       record.Error,
		})
	}
	return rows
}

func notificationChannelLabel(channel string) string {
	switch strings.ToLower(strings.TrimSpace(channel)) {
	case "webhook":
		return "Webhook"
	case "feishu":
		return "飞书机器人"
	case "disabled", "":
		return "暂不启用"
	default:
		return channel
	}
}

func notificationDeliveryStatusLabel(status string, statusCode int) string {
	switch strings.ToLower(strings.TrimSpace(status)) {
	case "sent":
		if statusCode > 0 {
			return "成功 " + strconv.Itoa(statusCode)
		}
		return "成功"
	case "failed":
		if statusCode > 0 {
			return "失败 " + strconv.Itoa(statusCode)
		}
		return "失败"
	default:
		return "未知"
	}
}

func notificationDeliveryStatusClass(status string) string {
	switch strings.ToLower(strings.TrimSpace(status)) {
	case "sent":
		return "is-ready"
	case "failed":
		return "is-warning"
	default:
		return "is-muted"
	}
}

func buildAdminSettingChangeRows(records []adminSettingChangeRecord) []adminSettingChangeRow {
	rows := make([]adminSettingChangeRow, 0, len(records))
	for _, record := range records {
		rows = append(rows, adminSettingChangeRow{
			Time:     formatAdminTime(record.Timestamp),
			Category: settingChangeCategoryLabel(record.Category),
			Action:   record.Action,
			Actor:    displayNotificationValue(record.Actor, "system"),
			Summary:  displayNotificationValue(record.Summary, "-"),
		})
	}
	return rows
}

func settingChangeCategoryLabel(category string) string {
	switch strings.ToLower(strings.TrimSpace(category)) {
	case "system":
		return "基础信息"
	case "storage":
		return "存储"
	case "ai":
		return "AI"
	case "security":
		return "安全"
	case "notifications":
		return "通知"
	case "task":
		return "后台任务"
	default:
		return displayNotificationValue(category, "设置")
	}
}

func buildAdminAgentRunRows(records []agentRunRecord) []adminAgentRunRow {
	rows := make([]adminAgentRunRow, 0, len(records))
	for _, record := range records {
		model := "本地工具"
		if record.ModelUsed {
			model = "模型+工具"
		}
		rows = append(rows, adminAgentRunRow{
			ID:          record.ID,
			SessionID:   record.SessionID,
			Actor:       record.Actor,
			Mode:        record.Mode,
			Goal:        record.Goal,
			Message:     truncateAuditText(record.Message, 80),
			Status:      agentRunStatusText(record.Status),
			StatusClass: agentRunStatusClass(record.Status),
			Model:       model,
			ToolCount:   record.ToolCount,
			FileCount:   record.FileCount,
			Duration:    strconv.FormatInt(record.DurationMS, 10) + " ms",
			StartedAt:   formatAdminTime(record.StartedAt),
		})
	}
	return rows
}

func filterAgentTasksByActor(records []agentTaskRecord, actor string, limit int) []agentTaskRecord {
	actor = strings.TrimSpace(actor)
	filtered := make([]agentTaskRecord, 0, len(records))
	for _, record := range records {
		record = record.normalized()
		if actor != "" && record.Actor != actor {
			continue
		}
		filtered = append(filtered, record)
		if limit > 0 && len(filtered) >= limit {
			break
		}
	}
	return filtered
}

func pickAdminCurrentAgentTask(records []agentTaskRecord) *agentTaskRecord {
	for _, record := range records {
		record = record.normalized()
		switch record.Status {
		case agentTaskStatusWaiting, agentTaskStatusRunning:
			candidate := record
			return &candidate
		}
	}
	if len(records) == 0 {
		return nil
	}
	candidate := records[0].normalized()
	return &candidate
}

func buildAdminAgentTaskRows(records []agentTaskRecord) []adminAgentTaskRow {
	rows := make([]adminAgentTaskRow, 0, len(records))
	for _, record := range records {
		rows = append(rows, buildAdminAgentTaskRow(record))
	}
	return rows
}

func buildAdminAgentTaskRow(record agentTaskRecord) adminAgentTaskRow {
	record = record.normalized()
	title := record.Title
	if title == "" {
		title = truncateAuditText(firstNonEmpty(record.Goal, record.LastUserMessage, record.PrimaryTable, "后台任务"), 96)
	}
	return adminAgentTaskRow{
		ID:              record.ID,
		SessionID:       record.SessionID,
		Actor:           record.Actor,
		Title:           title,
		Goal:            truncateAuditText(firstNonEmpty(record.Goal, record.LastReply, record.LastUserMessage), 160),
		Status:          agentTaskStatusText(record.Status),
		StatusClass:     agentTaskStatusClass(record.Status),
		Intent:          truncateAuditText(record.Intent, 32),
		PrimaryTable:    record.PrimaryTable,
		ExportFormat:    record.ExportFormat,
		LastUserMessage: truncateAuditText(record.LastUserMessage, 120),
		UpdatedAt:       formatAdminTime(record.UpdatedAt),
	}
}

func buildAdminAgentTaskStepRows(records []agentTaskStepRecord) []adminAgentTaskStepRow {
	rows := make([]adminAgentTaskStepRow, 0, len(records))
	for _, record := range records {
		status := normalizeAgentTaskStepStatus(record.Status)
		title := strings.TrimSpace(record.Title)
		if title == "" {
			title = "任务步骤"
		}
		rows = append(rows, adminAgentTaskStepRow{
			Title:       title,
			Detail:      truncateAuditText(strings.TrimSpace(record.Detail), 180),
			Status:      agentTaskStatusText(status),
			StatusClass: agentTaskStatusClass(status),
			Tool:        strings.TrimSpace(record.Tool),
		})
	}
	return rows
}

func agentTaskStatusClass(status string) string {
	switch normalizeAgentTaskStepStatus(status) {
	case agentTaskStatusDone:
		return "is-ready"
	case agentTaskStatusFailed:
		return "is-warning"
	case agentTaskStatusRunning:
		return "is-progress"
	default:
		return "is-muted"
	}
}

const (
	adminAgentWeChatMessagesPageSize     = 20
	adminAgentWeChatChannelPreviewLimit  = 8
	adminAgentWeChatChannelPreviewSource = 120
)

func (s *adminServer) buildAdminAgentWeChatMessagePage(query url.Values, entry string, channels agentChannelConfig) adminAgentWeChatMessagePage {
	action := entry + "/wechat-agent/messages"
	channelKey := normalizeAgentWeChatChannelKey(query.Get("channel"))
	page := parseAdminPositiveInt(query.Get("page"), 1)
	if page < 1 {
		page = 1
	}
	total := s.countAgentWeChatMessages(channelKey)
	totalPages := 1
	if total > 0 {
		totalPages = (total + adminAgentWeChatMessagesPageSize - 1) / adminAgentWeChatMessagesPageSize
	}
	if page > totalPages {
		page = totalPages
	}
	offset := (page - 1) * adminAgentWeChatMessagesPageSize
	records := s.listAgentWeChatMessagesPage(channelKey, adminAgentWeChatMessagesPageSize, offset)
	rows := buildAdminAgentWeChatMessageRows(records, 0, 0)
	showingFrom := 0
	showingTo := 0
	if total > 0 && len(rows) > 0 {
		showingFrom = offset + 1
		showingTo = offset + len(rows)
	}
	result := adminAgentWeChatMessagePage{
		Action:         action,
		ResetURL:       action,
		ExportURL:      adminAgentWeChatMessagesExportURL(entry+"/wechat-agent/messages/export", channelKey),
		ChannelKey:     channelKey,
		ChannelOptions: buildAdminAgentWeChatChannelOptions(channels, channelKey),
		Rows:           rows,
		Page:           page,
		PageSize:       adminAgentWeChatMessagesPageSize,
		Total:          total,
		TotalPages:     totalPages,
		ShowingFrom:    showingFrom,
		ShowingTo:      showingTo,
		RangeText:      "暂无记录",
		PageText:       fmt.Sprintf("第 %d / %d 页", page, totalPages),
	}
	if total > 0 {
		result.RangeText = fmt.Sprintf("%d-%d / %d 条", showingFrom, showingTo, total)
	}
	if page > 1 {
		result.HasPrev = true
		result.PrevURL = adminAgentWeChatMessagesURL(action, channelKey, page-1)
	}
	if page < totalPages {
		result.HasNext = true
		result.NextURL = adminAgentWeChatMessagesURL(action, channelKey, page+1)
	}
	return result
}

func parseAdminPositiveInt(value string, fallback int) int {
	parsed, err := strconv.Atoi(strings.TrimSpace(value))
	if err != nil || parsed <= 0 {
		return fallback
	}
	return parsed
}

func adminAgentWeChatMessagesURL(action string, channelKey string, page int) string {
	values := url.Values{}
	if normalizedKey := normalizeAgentWeChatChannelKey(channelKey); normalizedKey != "" {
		values.Set("channel", normalizedKey)
	}
	if page > 1 {
		values.Set("page", strconv.Itoa(page))
	}
	if encoded := values.Encode(); encoded != "" {
		return action + "?" + encoded
	}
	return action
}

func adminAgentWeChatMessagesExportURL(action string, channelKey string) string {
	values := url.Values{}
	if normalizedKey := normalizeAgentWeChatChannelKey(channelKey); normalizedKey != "" {
		values.Set("channel", normalizedKey)
	}
	if encoded := values.Encode(); encoded != "" {
		return action + "?" + encoded
	}
	return action
}

func buildAdminAgentWeChatChannelOptions(channels agentChannelConfig, selected string) []adminAgentWeChatChannelOption {
	channels = channels.normalized()
	selected = normalizeAgentWeChatChannelKey(selected)
	options := make([]adminAgentWeChatChannelOption, 0, len(channels.WeChats))
	for _, channel := range channels.WeChats {
		key := normalizeAgentWeChatChannelKey(channel.Key)
		if key == "" {
			continue
		}
		label := strings.TrimSpace(channel.DisplayName)
		if label == "" {
			label = key
		}
		options = append(options, adminAgentWeChatChannelOption{
			Key:      key,
			Label:    label,
			Selected: key == selected,
		})
	}
	return options
}

func buildAdminAgentWeChatMessageRowsByChannel(records []agentWeChatMessageRecord) map[string][]adminAgentWeChatMessageRow {
	rows := map[string][]adminAgentWeChatMessageRow{}
	for _, record := range records {
		key := normalizeAgentWeChatChannelKey(record.ChannelKey)
		if key == "" {
			key = "wechat"
		}
		if len(rows[key]) >= adminAgentWeChatChannelPreviewLimit {
			continue
		}
		rows[key] = append(rows[key], buildAdminAgentWeChatMessageRow(record, 140, 180))
	}
	return rows
}

func buildAdminAgentWeChatMessageRows(records []agentWeChatMessageRecord, inboundLimit int, replyLimit int) []adminAgentWeChatMessageRow {
	rows := make([]adminAgentWeChatMessageRow, 0, len(records))
	for _, record := range records {
		rows = append(rows, buildAdminAgentWeChatMessageRow(record, inboundLimit, replyLimit))
	}
	return rows
}

func buildAdminAgentWeChatMessageRow(record agentWeChatMessageRecord, inboundLimit int, replyLimit int) adminAgentWeChatMessageRow {
	channelName := strings.TrimSpace(record.ChannelName)
	if channelName == "" {
		channelName = normalizeAgentWeChatChannelKey(record.ChannelKey)
	}
	inboundText := displayFallback(record.InboundText, "-")
	replyText := displayFallback(record.ReplyText, "-")
	if inboundLimit > 0 {
		inboundText = truncateAuditText(record.InboundText, inboundLimit)
	}
	if replyLimit > 0 {
		replyText = truncateAuditText(record.ReplyText, replyLimit)
	}
	return adminAgentWeChatMessageRow{
		ChannelKey:   displayFallback(normalizeAgentWeChatChannelKey(record.ChannelKey), "-"),
		ChannelName:  displayFallback(channelName, "-"),
		Provider:     displayFallback(record.Provider, "-"),
		ReceivedAt:   formatAdminTime(record.ReceivedAt),
		RepliedAt:    formatAdminTime(record.RepliedAt),
		SessionID:    displayFallback(record.SessionID, "-"),
		RunID:        displayFallback(record.RunID, "-"),
		MessageID:    displayFallback(record.MessageID, "-"),
		FromUserID:   displayFallback(record.FromUserID, "-"),
		ToUserID:     displayFallback(record.ToUserID, "-"),
		InboundText:  inboundText,
		ReplyText:    replyText,
		FileSummary:  adminAgentWeChatFileSummary(record.Files),
		Status:       agentRunStatusText(record.Status),
		StatusClass:  agentRunStatusClass(record.Status),
		Error:        truncateAuditText(record.Error, 240),
		DurationText: strconv.FormatInt(record.DurationMS, 10) + " ms",
	}
}

func adminAgentWeChatFileSummary(files []agentFileResult) string {
	if len(files) == 0 {
		return "-"
	}
	names := make([]string, 0, len(files))
	for _, file := range files {
		if strings.TrimSpace(file.Name) != "" {
			names = append(names, file.Name)
		}
	}
	if len(names) == 0 {
		return strconv.Itoa(len(files)) + " 个文件"
	}
	if len(names) > 2 {
		return strings.Join(names[:2], "、") + " 等 " + strconv.Itoa(len(names)) + " 个文件"
	}
	return strings.Join(names, "、")
}

func agentRunStatusText(status string) string {
	switch strings.ToLower(strings.TrimSpace(status)) {
	case "ok":
		return "完成"
	case "failed":
		return "失败"
	default:
		return "未知"
	}
}

func agentRunStatusClass(status string) string {
	switch strings.ToLower(strings.TrimSpace(status)) {
	case "ok":
		return "is-ready"
	case "failed":
		return "is-warning"
	default:
		return "is-muted"
	}
}

func auditMetricStatus(count int) string {
	if count > 0 {
		return "is-ready"
	}
	return "is-muted"
}

func pendingMetricStatus(count int) string {
	if count > 0 {
		return "is-progress"
	}
	return "is-muted"
}

func formatAdminTime(t time.Time) string {
	if t.IsZero() {
		return "-"
	}
	return t.Local().Format("2006-01-02 15:04")
}

func (s *adminServer) renderInstall(w http.ResponseWriter, statusCode int, data installPageData) {
	if data.Action == "" {
		data.Action = s.basePath + "/install"
	}
	if data.AdminEntry == "" {
		data.AdminEntry = s.basePath
	}
	w.Header().Set("Content-Type", "text/html; charset=utf-8")
	w.WriteHeader(statusCode)
	_ = adminInstallTemplate.Execute(w, data)
}

func (s *adminServer) renderInstalled(w http.ResponseWriter, state installState) {
	entry := s.adminEntryForState(state)
	w.Header().Set("Content-Type", "text/html; charset=utf-8")
	w.WriteHeader(http.StatusOK)
	_ = adminInstalledTemplate.Execute(w, installedPageData{
		SiteName:    state.SiteName,
		AdminUser:   state.AdminUser,
		LoginPath:   entry + "/login",
		Database:    state.Database.DisplayName(),
		DatabaseDSN: state.Database.DisplayTarget(),
		AIProvider:  state.AI.DisplayName(),
		AIModel:     state.AI.DisplayModel(),
	})
}

func (s *adminServer) renderLogin(w http.ResponseWriter, statusCode int, data loginPageData) {
	if data.Action == "" {
		data.Action = s.basePath + "/login"
	}
	if !data.DebugPrefill {
		data.Password = ""
	}
	w.Header().Set("Content-Type", "text/html; charset=utf-8")
	w.WriteHeader(statusCode)
	_ = adminLoginTemplate.Execute(w, data)
}

func (s *adminServer) debugMode() bool {
	env := strings.ToLower(strings.TrimSpace(s.env))
	return env == "" || env == "development" || env == "dev" || env == "local" || env == "debug" || env == "test"
}

func (s *adminServer) debugLoginPassword(state installState) string {
	if !s.debugMode() {
		return ""
	}
	if password := strings.TrimSpace(state.DebugLoginPassword); password != "" {
		return password
	}
	if strings.TrimSpace(s.password) != "" && state.credentialsMatch(state.AdminUser, s.password) {
		return s.password
	}
	return ""
}

func (s *adminServer) isInstalled() bool {
	state, err := s.store.Load()
	return err == nil && state.Initialized
}

func (s *adminServer) isAuthenticated(r *http.Request) bool {
	cookie, err := r.Cookie(adminSessionCookie)
	if err != nil {
		return false
	}
	return s.validateSessionToken(cookie.Value)
}

func (s *adminServer) loginLocked(state installState, username string, ip string, now time.Time) bool {
	security := state.Security.normalized()
	if security.LoginMaxAttempts <= 0 || security.LoginLockMinutes <= 0 {
		return false
	}
	since := now.Add(-time.Duration(security.LoginLockMinutes) * time.Minute)
	attempts, err := s.store.CountFailedLoginAttempts(username, ip, since)
	if err != nil {
		return false
	}
	return attempts >= security.LoginMaxAttempts
}

func (s *adminServer) createSessionToken(username string, sessionID string, expiresAt time.Time) string {
	expires := strconv.FormatInt(expiresAt.Unix(), 10)
	payload := username + "|" + expires + "|" + sessionID
	signature := s.sign(payload)
	return payload + "|" + signature
}

func (s *adminServer) validateSessionToken(token string) bool {
	parts := strings.Split(token, "|")
	if len(parts) != 3 && len(parts) != 4 {
		return false
	}

	username, expires := parts[0], parts[1]
	sessionID := ""
	signature := ""
	if len(parts) == 4 {
		sessionID = parts[2]
		signature = parts[3]
	} else {
		signature = parts[2]
	}
	state, err := s.store.Load()
	if err != nil || !state.Initialized {
		return false
	}
	access := adminRoleAccessForUsername(state, username)
	account, ok := findAdminAccount(state, username)
	if !ok || account.Status == "disabled" || !access.Valid {
		return false
	}

	expiresUnix, err := strconv.ParseInt(expires, 10, 64)
	if err != nil || time.Now().After(time.Unix(expiresUnix, 0)) {
		return false
	}

	payload := username + "|" + expires
	if sessionID != "" {
		payload += "|" + sessionID
	}
	expected := s.sign(payload)
	if subtle.ConstantTimeCompare([]byte(signature), []byte(expected)) != 1 {
		return false
	}
	if sessionID == "" {
		return true
	}
	active, err := s.store.AdminSessionActive(sessionID, username, time.Now().UTC())
	return err == nil && active
}

func (s *adminServer) sessionUsername(r *http.Request) string {
	cookie, err := r.Cookie(adminSessionCookie)
	if err != nil {
		return ""
	}
	parts := strings.Split(cookie.Value, "|")
	if (len(parts) != 3 && len(parts) != 4) || !s.validateSessionToken(cookie.Value) {
		return ""
	}
	return parts[0]
}

func (s *adminServer) sessionID(r *http.Request) string {
	cookie, err := r.Cookie(adminSessionCookie)
	if err != nil {
		return ""
	}
	if !s.validateSessionToken(cookie.Value) {
		return ""
	}
	parts := strings.Split(cookie.Value, "|")
	if len(parts) != 4 {
		return ""
	}
	return parts[2]
}

func (s *adminServer) revokeSessionToken(token string) {
	parts := strings.Split(token, "|")
	if len(parts) != 4 || !s.validateSessionToken(token) {
		return
	}
	_ = s.store.RevokeAdminSession(parts[2], time.Now().UTC())
}

func (s *adminServer) sign(payload string) string {
	mac := hmac.New(sha256.New, []byte(s.sessionSecret))
	_, _ = mac.Write([]byte(payload))
	return base64.RawURLEncoding.EncodeToString(mac.Sum(nil))
}

func (s *adminServer) adminEntryForRequest(r *http.Request) string {
	state, err := s.store.Load()
	if err != nil {
		return normalizeAdminEntry(s.basePath)
	}
	entry := s.adminEntryForState(state)
	if adminPathMatches(r.URL.Path, entry) {
		return entry
	}
	return normalizeAdminEntry(s.basePath)
}

func (s *adminServer) adminEntryForState(state installState) string {
	if state.Initialized {
		if entry := normalizeAdminEntry(state.AdminEntry); entry != "" {
			return entry
		}
	}
	return normalizeAdminEntry(s.basePath)
}

func normalizeAdminEntry(entry string) string {
	entry = strings.TrimSpace(entry)
	if entry == "" {
		return ""
	}
	entry = "/" + strings.Trim(entry, "/")
	if entry == "/" {
		return ""
	}
	return entry
}

func adminPathMatches(path string, entry string) bool {
	return path == entry || strings.HasPrefix(path, entry+"/")
}

func adminSubpath(path string, entry string) string {
	if path == entry {
		return "/"
	}
	return strings.TrimPrefix(path, entry)
}

func generateAdminEntry() (string, error) {
	bytes := make([]byte, 6)
	if _, err := rand.Read(bytes); err != nil {
		return "", err
	}
	return "/moyi-" + hex.EncodeToString(bytes) + "-admin", nil
}

func newAdminSessionID() string {
	bytes := make([]byte, 16)
	if _, err := rand.Read(bytes); err != nil {
		return strconv.FormatInt(time.Now().UnixNano(), 36)
	}
	return "sess_" + hex.EncodeToString(bytes)
}

type loginPageData struct {
	Action       string
	Username     string
	Password     string
	DebugPrefill bool
	Error        string
	Success      bool
}

type installPageData struct {
	Action      string
	SiteName    string
	Username    string
	AdminEntry  string
	Database    databaseConfig
	AI          aiConfig
	Error       string
	RootInstall bool
}

type installedPageData struct {
	SiteName    string
	AdminUser   string
	LoginPath   string
	Database    string
	DatabaseDSN string
	AIProvider  string
	AIModel     string
}

type adminPageData struct {
	BasePath               string
	LogoutAction           string
	Active                 string
	Title                  string
	Subtitle               string
	SiteName               string
	Username               string
	AdminTagline           string
	AdminEntry             string
	InstalledAt            string
	Database               string
	DatabaseDSN            string
	AIProvider             string
	AIModel                string
	AITarget               string
	AIStatus               string
	AIStatusClass          string
	AgentNotice            string
	AgentNoticeClass       string
	UserNotice             string
	UserNoticeClass        string
	UserSaveAction         string
	RoleSaveAction         string
	DataSourceNotice       string
	DataSourceNoticeClass  string
	DataSourceSaveAction   string
	SettingsNotice         string
	SettingsNoticeClass    string
	FileNotice             string
	FileNoticeClass        string
	TaskNotice             string
	TaskNoticeClass        string
	TaskEnqueueAction      string
	TaskRunAction          string
	TaskRunAllAction       string
	CanManageSettings      bool
	CanManageDataSources   bool
	CanManageFiles         bool
	CanManageTasks         bool
	CanManageWeChat        bool
	CanViewSettingsPage    bool
	CanViewWeChatMessages  bool
	CanViewAuditPage       bool
	CanReadAgentTables     bool
	CanRunAgentSQL         bool
	CanReadWeb             bool
	CanGenerateAgentImages bool
	AgentScopeSummary      string
	NavItems               []adminNavItem
	NavGroups              []adminNavGroup
	Metrics                []adminMetric
	AdminUserMetrics       []adminMetric
	Tasks                  []adminTask
	FoundationServices     []adminFoundationService
	ExtensionMetrics       []adminMetric
	PluginExtensions       []adminPluginExtension
	ResourceModels         []adminResourceModel
	ResourceTools          []adminResourceTool
	DataSourceMetrics      []adminMetric
	DataSources            []adminDataSource
	AgentCapabilities      []adminCapability
	AgentWeChatChannel     adminAgentWeChatChannel
	AgentWeChatChannels    []adminAgentWeChatChannel
	AgentTableGroups       []adminAgentTableGroup
	RoleMenuGroups         []adminRoleMenuGroup
	RolePermissionGroups   []adminRolePermissionGroup
	AgentWeChatMessagePage adminAgentWeChatMessagePage
	CurrentAgentTask       *adminAgentTaskRow
	CurrentAgentTaskSteps  []adminAgentTaskStepRow
	AgentTasks             []adminAgentTaskRow
	AgentRuns              []adminAgentRunRow
	AdminUsers             []adminUserRow
	AdminRoleMetrics       []adminMetric
	AdminSessionMetrics    []adminMetric
	AdminPermissionMetrics []adminMetric
	AdminSessions          []adminSessionRow
	AdminRoles             []adminRoleRow
	AdminMenus             []adminMenuRow
	AdminPermissions       []adminPermissionRow
	Settings               []adminSettingRow
	SystemSettings         adminSystemSettings
	StorageSettings        adminStorageSettings
	AISettings             adminAISettings
	SecuritySettings       adminSecuritySettings
	NotificationSettings   adminNotificationSettings
	SettingChanges         []adminSettingChangeRow
	TaskWorkerSettings     adminTaskWorkerSettings
	FileMetrics            []adminMetric
	FileRows               []adminFileRow
	FileUploadAction       string
	FileStorageSummary     string
	FileAllowedSummary     string
	TaskMetrics            []adminMetric
	TaskRows               []adminBackgroundTaskRow
	TaskLogRows            []adminBackgroundTaskLogRow
	TaskTypeOptions        []adminTaskTypeOption
	NotificationMetrics    []adminMetric
	NotificationRows       []adminNotificationDeliveryRow
	AuditFilter            adminAuditFilter
	AuditMetrics           []adminMetric
	AuditEvents            []adminAuditEvent
}

type adminNavItem struct {
	Key    string
	Label  string
	Href   string
	Active bool
}

type adminNavGroup struct {
	Label  string
	Items  []adminNavItem
	Active bool
}

type adminMetric struct {
	Label  string
	Value  string
	Detail string
	Status string
}

type adminTask struct {
	Name        string
	Owner       string
	Status      string
	StatusClass string
}

type adminTaskTypeOption struct {
	Type        string
	Name        string
	Description string
}

type adminFoundationService struct {
	Name        string
	Legacy      string
	Current     string
	Status      string
	StatusClass string
	Next        string
}

type adminDataSource struct {
	Initial      string
	Name         string
	DriverKey    string
	Driver       string
	Target       string
	Role         string
	StatusKey    string
	Status       string
	StatusClass  string
	Message      string
	LastChecked  string
	Schema       string
	Editable     bool
	TestAction   string
	DeleteAction string
}

type adminCapability struct {
	Name        string
	Boundary    string
	Status      string
	StatusClass string
}

type adminAgentWeChatChannel struct {
	Key             string
	Action          string
	Enabled         bool
	IsBound         bool
	StatusText      string
	StatusClass     string
	BindCode        string
	BindSession     string
	BindExpiresAt   string
	HasQRCode       bool
	QRImageURL      template.URL
	QRPayload       string
	TokenMasked     string
	DisplayName     string
	AdminUser       string
	AdminUserLabel  string
	AdminRole       string
	DataScope       string
	AllowedTables   string
	AllowedSummary  string
	BaseURL         string
	BotType         string
	LoginMessage    string
	AccountID       string
	OpenClawUserID  string
	BoundUser       string
	BoundAt         string
	LastMessageAt   string
	LastHeartbeatAt string
	LastOutboundAt  string
	LastError       string
	PairEndpoint    string
	BindEndpoint    string
	SessionEndpoint string
	MessageEndpoint string
	MeEndpoint      string
	ChatMessages    []adminAgentWeChatMessageRow
}

type adminAgentTableGroup struct {
	Title       string
	Description string
	Tables      []adminAgentTableOption
}

type adminAgentTableOption struct {
	Name        string
	Label       string
	Type        string
	Description string
}

type adminRoleMenuGroup struct {
	Title       string
	Description string
	Items       []adminRoleMenuOption
}

type adminRoleMenuOption struct {
	Key         string
	Label       string
	Path        string
	Status      string
	StatusClass string
	Description string
}

type adminRolePermissionGroup struct {
	Title       string
	Description string
	Items       []adminRolePermissionOption
}

type adminRolePermissionOption struct {
	Key         string
	Label       string
	Subject     string
	Status      string
	StatusClass string
	Description string
}

type adminAgentWeChatMessageRow struct {
	ChannelKey   string
	ChannelName  string
	Provider     string
	ReceivedAt   string
	RepliedAt    string
	SessionID    string
	RunID        string
	MessageID    string
	FromUserID   string
	ToUserID     string
	InboundText  string
	ReplyText    string
	FileSummary  string
	Status       string
	StatusClass  string
	Error        string
	DurationText string
}

type adminAgentWeChatMessagePage struct {
	Action         string
	ResetURL       string
	ExportURL      string
	ChannelKey     string
	ChannelOptions []adminAgentWeChatChannelOption
	Rows           []adminAgentWeChatMessageRow
	Page           int
	PageSize       int
	Total          int
	TotalPages     int
	ShowingFrom    int
	ShowingTo      int
	RangeText      string
	PageText       string
	HasPrev        bool
	HasNext        bool
	PrevURL        string
	NextURL        string
}

type adminAgentWeChatChannelOption struct {
	Key      string
	Label    string
	Selected bool
}

type adminUserRow struct {
	Username     string
	DisplayName  string
	Initial      string
	Role         string
	RoleKey      string
	StatusKey    string
	Status       string
	StatusClass  string
	Source       string
	SourceLabel  string
	CreatedAt    string
	LastSeen     string
	CanDelete    bool
	ToggleLabel  string
	ToggleAction string
	DeleteAction string
}

type adminSessionRecord struct {
	ID        string    `json:"id"`
	Username  string    `json:"username"`
	IP        string    `json:"ip,omitempty"`
	UserAgent string    `json:"user_agent,omitempty"`
	Status    string    `json:"status"`
	CreatedAt time.Time `json:"created_at"`
	ExpiresAt time.Time `json:"expires_at"`
	RevokedAt time.Time `json:"revoked_at,omitempty"`
}

type adminSessionRow struct {
	Initial      string
	ID           string
	IDShort      string
	Username     string
	StatusKey    string
	IP           string
	UserAgent    string
	CreatedAt    string
	ExpiresAt    string
	Status       string
	StatusClass  string
	CanRevoke    bool
	RevokeAction string
}

type adminRoleRow struct {
	Initial           string
	Key               string
	Name              string
	Scope             string
	StatusKey         string
	Status            string
	StatusClass       string
	Description       string
	DataScope         string
	AllowedTables     string
	AllowedSummary    string
	MenuKeys          string
	MenuSummary       string
	PermissionKeys    string
	PermissionSummary string
	UserCount         int
}

type adminMenuRow struct {
	Initial     string
	Key         string
	Label       string
	Path        string
	StatusKey   string
	Status      string
	StatusClass string
}

type adminPermissionRow struct {
	Initial     string
	Key         string
	Subject     string
	Permission  string
	Boundary    string
	StatusKey   string
	Status      string
	StatusClass string
}

type adminAgentRunRow struct {
	ID          string
	SessionID   string
	Actor       string
	Mode        string
	Goal        string
	Message     string
	Status      string
	StatusClass string
	Model       string
	ToolCount   int
	FileCount   int
	Duration    string
	StartedAt   string
}

type adminAgentTaskRow struct {
	ID              string
	SessionID       string
	Actor           string
	Title           string
	Goal            string
	Status          string
	StatusClass     string
	Intent          string
	PrimaryTable    string
	ExportFormat    string
	LastUserMessage string
	UpdatedAt       string
}

type adminAgentTaskStepRow struct {
	Title       string
	Detail      string
	Status      string
	StatusClass string
	Tool        string
}

type adminSettingRow struct {
	Key   string
	Value string
}

type adminSystemSettings struct {
	Action            string
	SiteName          string
	Timezone          string
	Locale            string
	AdminTagline      string
	PublicTagline     string
	PublicHeadline    string
	PublicDescription string
}

type adminStorageSettings struct {
	Action             string
	Driver             string
	DriverName         string
	LocalPath          string
	PublicURL          string
	MaxFileSizeMB      int
	AllowedExtensions  string
	RetentionDays      int
	PathStatus         string
	PathStatusClass    string
	AllowedDescription string
}

type adminAISettings struct {
	Action       string
	Provider     string
	BaseURL      string
	ChatModel    string
	ImageModel   string
	MaskedAPIKey string
}

type adminSecuritySettings struct {
	Action           string
	Username         string
	Entry            string
	SessionTTLHours  int
	LoginMaxAttempts int
	LoginLockMinutes int
}

type adminNotificationSettings struct {
	Action              string
	TestAction          string
	Enabled             bool
	Channel             string
	ChannelName         string
	Receiver            string
	WebhookURL          string
	FeishuSecretMasked  string
	EventLoginFailures  bool
	EventAIErrors       bool
	EventStorageWarning bool
}

type adminTaskWorkerSettings struct {
	Action                 string
	Enabled                bool
	IntervalSeconds        int
	BatchSize              int
	ScheduleHealthEnabled  bool
	ScheduleHealthMinutes  int
	ScheduleCleanupEnabled bool
	ScheduleCleanupMinutes int
	StatusText             string
}

type adminFileRow struct {
	Initial      string
	Name         string
	Path         string
	KindKey      string
	Kind         string
	SizeBytes    int64
	Size         string
	Modified     string
	StatusKey    string
	Status       string
	StatusClass  string
	PreviewURL   string
	DownloadURL  string
	DeleteAction string
}

type adminAuditEvent struct {
	Time        string
	Category    string
	Action      string
	Actor       string
	Detail      string
	Method      string
	Path        string
	IP          string
	Meta        string
	Status      string
	StatusClass string
	Duration    string
}

type adminAuditFilter struct {
	Action       string
	ExportAction string
	ExportURL    string
	Category     string
	Status       string
	Keyword      string
}

type adminAuditRecord struct {
	Timestamp  time.Time `json:"timestamp"`
	Category   string    `json:"category"`
	Action     string    `json:"action"`
	Actor      string    `json:"actor"`
	Detail     string    `json:"detail"`
	Method     string    `json:"method,omitempty"`
	Path       string    `json:"path,omitempty"`
	IP         string    `json:"ip,omitempty"`
	UserAgent  string    `json:"user_agent,omitempty"`
	StatusCode int       `json:"status_code,omitempty"`
	DurationMS int64     `json:"duration_ms,omitempty"`
}

type adminNotificationDeliveryRecord struct {
	ID         int64     `json:"id,omitempty"`
	Timestamp  time.Time `json:"timestamp"`
	Event      string    `json:"event"`
	Title      string    `json:"title"`
	Receiver   string    `json:"receiver"`
	Channel    string    `json:"channel"`
	Target     string    `json:"target"`
	Message    string    `json:"message"`
	Status     string    `json:"status"`
	StatusCode int       `json:"status_code,omitempty"`
	Error      string    `json:"error,omitempty"`
}

type adminBackgroundTaskRecord struct {
	ID          string    `json:"id"`
	Name        string    `json:"name"`
	Type        string    `json:"type"`
	Queue       string    `json:"queue"`
	Status      string    `json:"status"`
	Priority    int       `json:"priority"`
	Attempts    int       `json:"attempts"`
	MaxAttempts int       `json:"max_attempts"`
	PayloadJSON string    `json:"payload_json,omitempty"`
	Result      string    `json:"result,omitempty"`
	LastError   string    `json:"last_error,omitempty"`
	CreatedBy   string    `json:"created_by,omitempty"`
	CreatedAt   time.Time `json:"created_at"`
	AvailableAt time.Time `json:"available_at"`
	StartedAt   time.Time `json:"started_at,omitempty"`
	FinishedAt  time.Time `json:"finished_at,omitempty"`
	UpdatedAt   time.Time `json:"updated_at"`
}

type adminBackgroundTaskLogRecord struct {
	ID        int64     `json:"id"`
	TaskID    string    `json:"task_id"`
	Timestamp time.Time `json:"timestamp"`
	Level     string    `json:"level"`
	Event     string    `json:"event"`
	Message   string    `json:"message"`
	Status    string    `json:"status"`
	Attempt   int       `json:"attempt"`
}

type adminSchemaSnapshotRecord struct {
	ID             int64     `json:"id,omitempty"`
	DataSourceName string    `json:"data_source_name"`
	Driver         string    `json:"driver"`
	Target         string    `json:"target"`
	Summary        string    `json:"summary"`
	TableCount     int       `json:"table_count"`
	ColumnCount    int       `json:"column_count"`
	SchemaHash     string    `json:"schema_hash"`
	ChecksJSON     string    `json:"checks_json,omitempty"`
	SchemaJSON     string    `json:"schema_json,omitempty"`
	CapturedAt     time.Time `json:"captured_at"`
}

type adminSettingChangeRecord struct {
	ID         int64     `json:"id,omitempty"`
	Timestamp  time.Time `json:"timestamp"`
	Category   string    `json:"category"`
	Action     string    `json:"action"`
	Actor      string    `json:"actor"`
	Summary    string    `json:"summary"`
	BeforeJSON string    `json:"before_json,omitempty"`
	AfterJSON  string    `json:"after_json,omitempty"`
}

type adminSettingChangeRow struct {
	Time     string
	Category string
	Action   string
	Actor    string
	Summary  string
}

type adminBackgroundTaskRow struct {
	Initial      string
	ID           string
	IDShort      string
	Name         string
	Type         string
	TypeName     string
	Queue        string
	StatusKey    string
	Status       string
	StatusClass  string
	Attempts     string
	CreatedBy    string
	CreatedAt    string
	AvailableAt  string
	StartedAt    string
	FinishedAt   string
	Result       string
	LastError    string
	RunAction    string
	RetryAction  string
	CancelAction string
	CanRun       bool
	CanRetry     bool
	CanCancel    bool
}

type adminBackgroundTaskLogRow struct {
	Time        string
	TaskID      string
	TaskIDShort string
	Level       string
	LevelClass  string
	Event       string
	Status      string
	Attempt     int
	Message     string
}

type adminNotificationDeliveryRow struct {
	Time        string
	Event       string
	Title       string
	Receiver    string
	Channel     string
	Target      string
	Message     string
	Status      string
	StatusClass string
	Error       string
}

type auditEventInput struct {
	Category   string
	Action     string
	Actor      string
	Detail     string
	StatusCode int
	Duration   time.Duration
}

type installState struct {
	Initialized        bool               `json:"initialized"`
	SiteName           string             `json:"site_name"`
	AdminEntry         string             `json:"admin_entry"`
	AdminUser          string             `json:"admin_user"`
	DebugLoginPassword string             `json:"debug_login_password,omitempty"`
	Database           databaseConfig     `json:"database"`
	AI                 aiConfig           `json:"ai"`
	System             systemConfig       `json:"system,omitempty"`
	Storage            storageConfig      `json:"storage,omitempty"`
	Security           securityConfig     `json:"security,omitempty"`
	Notifications      notificationConfig `json:"notifications,omitempty"`
	TaskWorker         taskWorkerConfig   `json:"task_worker,omitempty"`
	AgentChannels      agentChannelConfig `json:"agent_channels,omitempty"`
	DataSources        []dataSourceConfig `json:"data_sources,omitempty"`
	Access             accessConfig       `json:"access,omitempty"`
	PasswordSalt       string             `json:"password_salt"`
	PasswordHash       string             `json:"password_hash"`
	InstalledAt        time.Time          `json:"installed_at"`
}

type systemConfig struct {
	Timezone          string `json:"timezone,omitempty"`
	Locale            string `json:"locale,omitempty"`
	AdminTagline      string `json:"admin_tagline,omitempty"`
	PublicTagline     string `json:"public_tagline,omitempty"`
	PublicHeadline    string `json:"public_headline,omitempty"`
	PublicDescription string `json:"public_description,omitempty"`
}

type storageConfig struct {
	Driver                   string `json:"driver,omitempty"`
	LocalPath                string `json:"local_path,omitempty"`
	PublicURL                string `json:"public_url,omitempty"`
	MaxFileSizeMB            int    `json:"max_file_size_mb,omitempty"`
	AllowedExtensions        string `json:"allowed_extensions,omitempty"`
	AgentExportRetentionDays int    `json:"agent_export_retention_days,omitempty"`
}

type securityConfig struct {
	SessionTTLHours  int `json:"session_ttl_hours,omitempty"`
	LoginMaxAttempts int `json:"login_max_attempts,omitempty"`
	LoginLockMinutes int `json:"login_lock_minutes,omitempty"`
}

type notificationConfig struct {
	Enabled             bool   `json:"enabled,omitempty"`
	Channel             string `json:"channel,omitempty"`
	Receiver            string `json:"receiver,omitempty"`
	WebhookURL          string `json:"webhook_url,omitempty"`
	FeishuSecret        string `json:"feishu_secret,omitempty"`
	EventLoginFailures  bool   `json:"event_login_failures,omitempty"`
	EventAIErrors       bool   `json:"event_ai_errors,omitempty"`
	EventStorageWarning bool   `json:"event_storage_warning,omitempty"`
}

type taskWorkerConfig struct {
	Enabled                bool `json:"enabled,omitempty"`
	IntervalSeconds        int  `json:"interval_seconds,omitempty"`
	BatchSize              int  `json:"batch_size,omitempty"`
	ScheduleHealthEnabled  bool `json:"schedule_health_enabled,omitempty"`
	ScheduleHealthMinutes  int  `json:"schedule_health_minutes,omitempty"`
	ScheduleCleanupEnabled bool `json:"schedule_cleanup_enabled,omitempty"`
	ScheduleCleanupMinutes int  `json:"schedule_cleanup_minutes,omitempty"`
}

type agentChannelConfig struct {
	WeChat  agentWeChatChannelConfig   `json:"wechat,omitempty"`
	WeChats []agentWeChatChannelConfig `json:"wechats,omitempty"`
}

type agentWeChatChannelConfig struct {
	Key             string    `json:"key,omitempty"`
	Enabled         bool      `json:"enabled,omitempty"`
	Status          string    `json:"status,omitempty"`
	BindCode        string    `json:"bind_code,omitempty"`
	BindSession     string    `json:"bind_session,omitempty"`
	BindExpiresAt   time.Time `json:"bind_expires_at,omitempty"`
	BaseURL         string    `json:"base_url,omitempty"`
	BotType         string    `json:"bot_type,omitempty"`
	LoginQRCode     string    `json:"login_qrcode,omitempty"`
	LoginSession    string    `json:"login_session,omitempty"`
	QRPayload       string    `json:"qr_payload,omitempty"`
	QRImageURL      string    `json:"qr_image_url,omitempty"`
	LoginMessage    string    `json:"login_message,omitempty"`
	ProviderToken   string    `json:"provider_token,omitempty"`
	AccountID       string    `json:"account_id,omitempty"`
	OpenClawUserID  string    `json:"openclaw_user_id,omitempty"`
	SyncBuffer      string    `json:"sync_buffer,omitempty"`
	Token           string    `json:"token,omitempty"`
	DisplayName     string    `json:"display_name,omitempty"`
	AgentHint       string    `json:"agent_hint,omitempty"`
	AdminUser       string    `json:"admin_user,omitempty"`
	DataScope       string    `json:"data_scope,omitempty"`
	AllowedTables   []string  `json:"allowed_tables,omitempty"`
	BoundUser       string    `json:"bound_user,omitempty"`
	ClientInfo      string    `json:"client_info,omitempty"`
	LastError       string    `json:"last_error,omitempty"`
	CreatedAt       time.Time `json:"created_at,omitempty"`
	UpdatedAt       time.Time `json:"updated_at,omitempty"`
	BoundAt         time.Time `json:"bound_at,omitempty"`
	LastMessageAt   time.Time `json:"last_message_at,omitempty"`
	LastHeartbeatAt time.Time `json:"last_heartbeat_at,omitempty"`
	LastOutboundAt  time.Time `json:"last_outbound_at,omitempty"`
}

type databaseConfig struct {
	Driver   string `json:"driver"`
	Host     string `json:"host,omitempty"`
	Port     string `json:"port,omitempty"`
	Database string `json:"database,omitempty"`
	Username string `json:"username,omitempty"`
	Password string `json:"password,omitempty"`
	SSLMode  string `json:"ssl_mode,omitempty"`
	FilePath string `json:"file_path,omitempty"`
}

type dataSourceConfig struct {
	Name          string    `json:"name"`
	Driver        string    `json:"driver"`
	Host          string    `json:"host,omitempty"`
	Port          string    `json:"port,omitempty"`
	Database      string    `json:"database,omitempty"`
	Username      string    `json:"username,omitempty"`
	Password      string    `json:"password,omitempty"`
	SSLMode       string    `json:"ssl_mode,omitempty"`
	FilePath      string    `json:"file_path,omitempty"`
	Role          string    `json:"role,omitempty"`
	Status        string    `json:"status,omitempty"`
	LastMessage   string    `json:"last_message,omitempty"`
	SchemaSummary string    `json:"schema_summary,omitempty"`
	LastCheckedAt time.Time `json:"last_checked_at,omitempty"`
}

type accessConfig struct {
	Users       []adminAccountConfig    `json:"users,omitempty"`
	Roles       []adminRoleConfig       `json:"roles,omitempty"`
	Menus       []adminMenuConfig       `json:"menus,omitempty"`
	Permissions []adminPermissionConfig `json:"permissions,omitempty"`
}

type adminAccountConfig struct {
	Username     string    `json:"username"`
	DisplayName  string    `json:"display_name,omitempty"`
	Role         string    `json:"role"`
	Status       string    `json:"status"`
	PasswordSalt string    `json:"password_salt,omitempty"`
	PasswordHash string    `json:"password_hash,omitempty"`
	Source       string    `json:"source,omitempty"`
	CreatedAt    time.Time `json:"created_at,omitempty"`
	UpdatedAt    time.Time `json:"updated_at,omitempty"`
	LastLoginAt  time.Time `json:"last_login_at,omitempty"`
}

type adminRoleConfig struct {
	Key                   string   `json:"key"`
	Name                  string   `json:"name"`
	Scope                 string   `json:"scope,omitempty"`
	Status                string   `json:"status"`
	Description           string   `json:"description,omitempty"`
	DataScope             string   `json:"data_scope,omitempty"`
	AllowedTables         []string `json:"allowed_tables,omitempty"`
	MenuKeys              []string `json:"menu_keys,omitempty"`
	PermissionKeys        []string `json:"permission_keys,omitempty"`
	MenusConfigured       bool     `json:"menus_configured,omitempty"`
	PermissionsConfigured bool     `json:"permissions_configured,omitempty"`
}

type adminMenuConfig struct {
	Key    string `json:"key"`
	Label  string `json:"label"`
	Path   string `json:"path"`
	Status string `json:"status"`
}

type adminPermissionConfig struct {
	Key        string `json:"key"`
	Subject    string `json:"subject"`
	Permission string `json:"permission"`
	Boundary   string `json:"boundary"`
	Status     string `json:"status"`
}

type aiConfig struct {
	Provider   string `json:"provider"`
	APIKey     string `json:"api_key,omitempty"`
	BaseURL    string `json:"base_url,omitempty"`
	ChatModel  string `json:"chat_model,omitempty"`
	ImageModel string `json:"image_model,omitempty"`
}

func defaultDatabaseConfig() databaseConfig {
	return databaseConfig{
		Driver:   "mysql",
		Host:     "127.0.0.1",
		Port:     "3306",
		Database: "moyi_admin",
		Username: "root",
		SSLMode:  "disable",
		FilePath: "data/moyi-admin.db",
	}
}

func defaultSystemConfig() systemConfig {
	return systemConfig{
		Timezone:          "Asia/Shanghai",
		Locale:            "zh-CN",
		AdminTagline:      "Go AI 管理台",
		PublicTagline:     "Go + AI 重构进行中",
		PublicHeadline:    "Moyi Admin 正在从传统 CRUD 后台，重构为 Go 驱动的 AI 数据工作台。",
		PublicDescription: "这个页面用于公开展示项目方向、当前进展和下一阶段计划。后台管理入口会独立登录，后续承载数据源、智能查询、导出、报告和审计能力。",
	}
}

func systemConfigFromForm(r *http.Request) systemConfig {
	return systemConfig{
		Timezone:          strings.TrimSpace(r.FormValue("timezone")),
		Locale:            strings.TrimSpace(r.FormValue("locale")),
		AdminTagline:      strings.TrimSpace(r.FormValue("admin_tagline")),
		PublicTagline:     strings.TrimSpace(r.FormValue("public_tagline")),
		PublicHeadline:    strings.TrimSpace(r.FormValue("public_headline")),
		PublicDescription: strings.TrimSpace(r.FormValue("public_description")),
	}
}

func defaultStorageConfig() storageConfig {
	return storageConfig{
		Driver:                   "local",
		LocalPath:                "data/uploads",
		PublicURL:                "/uploads",
		MaxFileSizeMB:            20,
		AllowedExtensions:        ".jpg,.jpeg,.png,.gif,.webp,.pdf,.txt,.md,.csv,.xlsx,.json,.doc,.docx,.zip",
		AgentExportRetentionDays: 7,
	}
}

func defaultSecurityConfig() securityConfig {
	return securityConfig{
		SessionTTLHours:  int(adminSessionTTL / time.Hour),
		LoginMaxAttempts: 5,
		LoginLockMinutes: 15,
	}
}

func defaultNotificationConfig() notificationConfig {
	return notificationConfig{
		Enabled:             false,
		Channel:             "disabled",
		EventLoginFailures:  true,
		EventAIErrors:       true,
		EventStorageWarning: true,
	}
}

func defaultTaskWorkerConfig() taskWorkerConfig {
	return taskWorkerConfig{
		Enabled:                true,
		IntervalSeconds:        30,
		BatchSize:              3,
		ScheduleHealthEnabled:  true,
		ScheduleHealthMinutes:  60,
		ScheduleCleanupEnabled: true,
		ScheduleCleanupMinutes: 24 * 60,
	}
}

func securityConfigFromForm(r *http.Request) securityConfig {
	sessionTTLHours, _ := strconv.Atoi(strings.TrimSpace(r.FormValue("session_ttl_hours")))
	loginMaxAttempts, _ := strconv.Atoi(strings.TrimSpace(r.FormValue("login_max_attempts")))
	loginLockMinutes, _ := strconv.Atoi(strings.TrimSpace(r.FormValue("login_lock_minutes")))
	return securityConfig{
		SessionTTLHours:  sessionTTLHours,
		LoginMaxAttempts: loginMaxAttempts,
		LoginLockMinutes: loginLockMinutes,
	}
}

func notificationConfigFromForm(r *http.Request) notificationConfig {
	return notificationConfig{
		Enabled:             r.FormValue("notification_enabled") == "1",
		Channel:             strings.TrimSpace(r.FormValue("notification_channel")),
		Receiver:            strings.TrimSpace(r.FormValue("notification_receiver")),
		WebhookURL:          strings.TrimSpace(r.FormValue("notification_webhook_url")),
		FeishuSecret:        strings.TrimSpace(r.FormValue("notification_feishu_secret")),
		EventLoginFailures:  r.FormValue("notification_event_login_failures") == "1",
		EventAIErrors:       r.FormValue("notification_event_ai_errors") == "1",
		EventStorageWarning: r.FormValue("notification_event_storage_warning") == "1",
	}
}

func taskWorkerConfigFromForm(r *http.Request) taskWorkerConfig {
	intervalSeconds, _ := strconv.Atoi(strings.TrimSpace(r.FormValue("task_worker_interval_seconds")))
	batchSize, _ := strconv.Atoi(strings.TrimSpace(r.FormValue("task_worker_batch_size")))
	healthMinutes, _ := strconv.Atoi(strings.TrimSpace(r.FormValue("task_schedule_health_minutes")))
	cleanupMinutes, _ := strconv.Atoi(strings.TrimSpace(r.FormValue("task_schedule_cleanup_minutes")))
	return taskWorkerConfig{
		Enabled:                r.FormValue("task_worker_enabled") == "1",
		IntervalSeconds:        intervalSeconds,
		BatchSize:              batchSize,
		ScheduleHealthEnabled:  r.FormValue("task_schedule_health_enabled") == "1",
		ScheduleHealthMinutes:  healthMinutes,
		ScheduleCleanupEnabled: r.FormValue("task_schedule_cleanup_enabled") == "1",
		ScheduleCleanupMinutes: cleanupMinutes,
	}
}

func storageConfigFromForm(r *http.Request) storageConfig {
	maxFileSize, _ := strconv.Atoi(strings.TrimSpace(r.FormValue("storage_max_file_size_mb")))
	retentionDays, _ := strconv.Atoi(strings.TrimSpace(r.FormValue("storage_retention_days")))
	return storageConfig{
		Driver:                   strings.TrimSpace(r.FormValue("storage_driver")),
		LocalPath:                strings.TrimSpace(r.FormValue("storage_local_path")),
		PublicURL:                strings.TrimSpace(r.FormValue("storage_public_url")),
		MaxFileSizeMB:            maxFileSize,
		AllowedExtensions:        strings.TrimSpace(r.FormValue("storage_allowed_extensions")),
		AgentExportRetentionDays: retentionDays,
	}
}

func databaseConfigFromForm(r *http.Request) databaseConfig {
	db := databaseConfig{
		Driver:   strings.TrimSpace(r.FormValue("db_driver")),
		Host:     strings.TrimSpace(r.FormValue("db_host")),
		Port:     strings.TrimSpace(r.FormValue("db_port")),
		Database: strings.TrimSpace(r.FormValue("db_name")),
		Username: strings.TrimSpace(r.FormValue("db_username")),
		Password: r.FormValue("db_password"),
		SSLMode:  strings.TrimSpace(r.FormValue("db_ssl_mode")),
		FilePath: strings.TrimSpace(r.FormValue("db_file_path")),
	}
	if db.Driver == "" {
		db.Driver = "mysql"
	}
	if db.SSLMode == "" {
		db.SSLMode = "disable"
	}
	return db
}

func defaultAIConfig() aiConfig {
	return aiConfig{
		Provider:   defaultAIProvider,
		BaseURL:    defaultAIBaseURL,
		ChatModel:  defaultAIChatModel,
		ImageModel: defaultAIImageModel,
	}
}

func aiConfigFromForm(r *http.Request) aiConfig {
	provider := strings.TrimSpace(r.FormValue("ai_provider"))
	if provider == "" {
		provider = "disabled"
	}
	ai := aiConfig{
		Provider:   provider,
		APIKey:     r.FormValue("ai_api_key"),
		BaseURL:    strings.TrimSpace(r.FormValue("ai_base_url")),
		ChatModel:  strings.TrimSpace(r.FormValue("ai_chat_model")),
		ImageModel: strings.TrimSpace(r.FormValue("ai_image_model")),
	}
	if ai.Provider == "bailian" {
		if ai.BaseURL == "" {
			ai.BaseURL = defaultAIBaseURL
		}
		if ai.ChatModel == "" {
			ai.ChatModel = defaultAIChatModel
		}
		if ai.ImageModel == "" {
			ai.ImageModel = defaultAIImageModel
		}
	}
	return ai
}

func (d databaseConfig) sanitized() databaseConfig {
	d.Driver = strings.ToLower(strings.TrimSpace(d.Driver))
	d.Host = strings.TrimSpace(d.Host)
	d.Port = strings.TrimSpace(d.Port)
	d.Database = strings.TrimSpace(d.Database)
	d.Username = strings.TrimSpace(d.Username)
	d.SSLMode = strings.TrimSpace(d.SSLMode)
	d.FilePath = strings.TrimSpace(d.FilePath)
	return d
}

func (a aiConfig) sanitized() aiConfig {
	a.Provider = strings.ToLower(strings.TrimSpace(a.Provider))
	a.APIKey = strings.TrimSpace(a.APIKey)
	a.BaseURL = strings.TrimRight(strings.TrimSpace(a.BaseURL), "/")
	a.ChatModel = strings.TrimSpace(a.ChatModel)
	a.ImageModel = strings.TrimSpace(a.ImageModel)
	if a.Provider == "" {
		a.Provider = "disabled"
	}
	if a.Provider == "bailian" {
		if a.BaseURL == "" {
			a.BaseURL = defaultAIBaseURL
		}
		if a.ChatModel == "" {
			a.ChatModel = defaultAIChatModel
		}
		if a.ImageModel == "" {
			a.ImageModel = defaultAIImageModel
		}
	}
	return a
}

func (c systemConfig) normalized() systemConfig {
	defaults := defaultSystemConfig()
	c.Timezone = strings.TrimSpace(c.Timezone)
	c.Locale = strings.TrimSpace(c.Locale)
	if c.Timezone == "" {
		c.Timezone = defaults.Timezone
	}
	if c.Locale == "" {
		c.Locale = defaults.Locale
	}
	c.AdminTagline = normalizeSettingText(c.AdminTagline, 48)
	if c.AdminTagline == "" {
		c.AdminTagline = defaults.AdminTagline
	}
	c.PublicTagline = normalizeSettingText(c.PublicTagline, 80)
	if c.PublicTagline == "" {
		c.PublicTagline = defaults.PublicTagline
	}
	c.PublicHeadline = normalizeSettingText(c.PublicHeadline, 140)
	if c.PublicHeadline == "" {
		c.PublicHeadline = defaults.PublicHeadline
	}
	c.PublicDescription = normalizeSettingText(c.PublicDescription, 260)
	if c.PublicDescription == "" {
		c.PublicDescription = defaults.PublicDescription
	}
	return c
}

func normalizeSettingText(value string, maxRunes int) string {
	value = strings.Join(strings.Fields(strings.TrimSpace(value)), " ")
	if maxRunes <= 0 {
		return value
	}
	runes := []rune(value)
	if len(runes) > maxRunes {
		return string(runes[:maxRunes])
	}
	return value
}

func (c storageConfig) normalized() storageConfig {
	defaults := defaultStorageConfig()
	c.Driver = strings.ToLower(strings.TrimSpace(c.Driver))
	c.LocalPath = strings.TrimSpace(c.LocalPath)
	c.PublicURL = strings.TrimSpace(c.PublicURL)
	c.AllowedExtensions = normalizeStorageExtensions(c.AllowedExtensions)
	if c.Driver == "" {
		c.Driver = defaults.Driver
	}
	if c.LocalPath == "" {
		c.LocalPath = defaults.LocalPath
	}
	if c.PublicURL == "" {
		c.PublicURL = defaults.PublicURL
	}
	if c.MaxFileSizeMB <= 0 {
		c.MaxFileSizeMB = defaults.MaxFileSizeMB
	}
	if c.AgentExportRetentionDays <= 0 {
		c.AgentExportRetentionDays = defaults.AgentExportRetentionDays
	}
	if c.AllowedExtensions == "" {
		c.AllowedExtensions = defaults.AllowedExtensions
	}
	return c
}

func (c storageConfig) IsLocal() bool {
	return strings.EqualFold(c.Driver, "local")
}

func (c storageConfig) DisplayName() string {
	switch strings.ToLower(c.Driver) {
	case "local":
		return "本地文件系统"
	default:
		if c.Driver == "" {
			return "本地文件系统"
		}
		return c.Driver
	}
}

func (c securityConfig) normalized() securityConfig {
	defaults := defaultSecurityConfig()
	if c.SessionTTLHours <= 0 {
		c.SessionTTLHours = defaults.SessionTTLHours
	}
	if c.LoginMaxAttempts <= 0 {
		c.LoginMaxAttempts = defaults.LoginMaxAttempts
	}
	if c.LoginLockMinutes <= 0 {
		c.LoginLockMinutes = defaults.LoginLockMinutes
	}
	return c
}

func (c securityConfig) sessionTTL() time.Duration {
	c = c.normalized()
	return time.Duration(c.SessionTTLHours) * time.Hour
}

func validateSecurityConfig(security securityConfig) error {
	security = security.normalized()
	if security.SessionTTLHours < 1 || security.SessionTTLHours > 168 {
		return errors.New("会话有效期需要在 1 到 168 小时之间")
	}
	if security.LoginMaxAttempts < 1 || security.LoginMaxAttempts > 20 {
		return errors.New("登录失败阈值需要在 1 到 20 次之间")
	}
	if security.LoginLockMinutes < 1 || security.LoginLockMinutes > 1440 {
		return errors.New("登录锁定窗口需要在 1 到 1440 分钟之间")
	}
	return nil
}

func (c notificationConfig) normalized() notificationConfig {
	defaults := defaultNotificationConfig()
	c.Channel = strings.ToLower(strings.TrimSpace(c.Channel))
	c.Receiver = normalizeSettingText(c.Receiver, 120)
	c.WebhookURL = strings.TrimSpace(c.WebhookURL)
	c.FeishuSecret = strings.TrimSpace(c.FeishuSecret)
	if c.Channel == "" {
		c.Channel = defaults.Channel
	}
	if c.Channel != "disabled" && c.Channel != "webhook" && c.Channel != "feishu" {
		c.Channel = defaults.Channel
	}
	if !c.Enabled {
		c.Channel = "disabled"
	}
	if !c.EventLoginFailures && !c.EventAIErrors && !c.EventStorageWarning {
		c.EventLoginFailures = defaults.EventLoginFailures
		c.EventAIErrors = defaults.EventAIErrors
		c.EventStorageWarning = defaults.EventStorageWarning
	}
	return c
}

func (c notificationConfig) ChannelName() string {
	switch c.normalized().Channel {
	case "webhook":
		return "Webhook"
	case "feishu":
		return "飞书机器人"
	default:
		return "暂不启用"
	}
}

func (c notificationConfig) DisplayName() string {
	c = c.normalized()
	if !c.Enabled || c.Channel == "disabled" {
		return "暂不启用"
	}
	if c.Receiver != "" {
		return c.ChannelName() + " / " + c.Receiver
	}
	return c.ChannelName()
}

func validateNotificationConfig(notifications notificationConfig) error {
	notifications = notifications.normalized()
	if !notifications.Enabled || notifications.Channel == "disabled" {
		return nil
	}
	if notifications.Receiver == "" {
		return errors.New("请输入通知接收人或备注")
	}
	if notifications.Channel == "webhook" || notifications.Channel == "feishu" {
		if notifications.WebhookURL == "" {
			return errors.New("请输入 " + notificationChannelLabel(notifications.Channel) + " Webhook 地址")
		}
		parsed, err := url.Parse(notifications.WebhookURL)
		if err != nil || parsed.Scheme == "" || parsed.Host == "" {
			return errors.New(notificationChannelLabel(notifications.Channel) + " Webhook 地址不正确")
		}
		if parsed.Scheme != "https" && parsed.Scheme != "http" {
			return errors.New(notificationChannelLabel(notifications.Channel) + " Webhook 地址只支持 http 或 https")
		}
	}
	return nil
}

func (c taskWorkerConfig) normalized() taskWorkerConfig {
	defaults := defaultTaskWorkerConfig()
	if c.IntervalSeconds <= 0 {
		c.IntervalSeconds = defaults.IntervalSeconds
	}
	if c.BatchSize <= 0 {
		c.BatchSize = defaults.BatchSize
	}
	if c.ScheduleHealthMinutes <= 0 {
		c.ScheduleHealthMinutes = defaults.ScheduleHealthMinutes
	}
	if c.ScheduleCleanupMinutes <= 0 {
		c.ScheduleCleanupMinutes = defaults.ScheduleCleanupMinutes
	}
	return c
}

func (c taskWorkerConfig) workerInterval() time.Duration {
	c = c.normalized()
	return time.Duration(c.IntervalSeconds) * time.Second
}

func (c taskWorkerConfig) StatusText() string {
	c = c.normalized()
	if !c.Enabled {
		return "自动执行已关闭"
	}
	return "每 " + strconv.Itoa(c.IntervalSeconds) + " 秒扫描，单轮最多 " + strconv.Itoa(c.BatchSize) + " 个"
}

func validateTaskWorkerConfig(config taskWorkerConfig) error {
	config = config.normalized()
	if config.IntervalSeconds < 5 || config.IntervalSeconds > 3600 {
		return errors.New("Worker 扫描间隔需要在 5 到 3600 秒之间")
	}
	if config.BatchSize < 1 || config.BatchSize > 50 {
		return errors.New("单轮执行数量需要在 1 到 50 个之间")
	}
	if config.ScheduleHealthMinutes < 5 || config.ScheduleHealthMinutes > 10080 {
		return errors.New("系统体检定时间隔需要在 5 到 10080 分钟之间")
	}
	if config.ScheduleCleanupMinutes < 5 || config.ScheduleCleanupMinutes > 10080 {
		return errors.New("导出清理定时间隔需要在 5 到 10080 分钟之间")
	}
	return nil
}

type notificationPayload struct {
	Event     string `json:"event"`
	Title     string `json:"title"`
	Message   string `json:"message"`
	SiteName  string `json:"site_name"`
	Receiver  string `json:"receiver,omitempty"`
	Timestamp string `json:"timestamp"`
}

type notificationSendResult struct {
	Event      string
	Title      string
	Receiver   string
	Channel    string
	Target     string
	Message    string
	StatusCode int
}

var notificationHTTPClient = &http.Client{Timeout: 6 * time.Second}

func sendTestNotification(ctx context.Context, state installState) (notificationSendResult, error) {
	return sendEventNotification(ctx, state, "notification_test", "Moyi Admin 测试通知", "后台通知通道测试成功。如果你收到了这条消息，说明当前通知配置可用。")
}

func (s *adminServer) deliverAdminNotification(ctx context.Context, state installState, event string, title string, message string) (notificationSendResult, error) {
	result, err := sendEventNotification(ctx, state, event, title, message)
	status := "sent"
	errorText := ""
	if err != nil {
		status = "failed"
		errorText = err.Error()
	}
	record := adminNotificationDeliveryRecord{
		Timestamp:  time.Now().UTC(),
		Event:      result.Event,
		Title:      result.Title,
		Receiver:   result.Receiver,
		Channel:    result.Channel,
		Target:     result.Target,
		Message:    truncateAuditText(message, 500),
		Status:     status,
		StatusCode: result.StatusCode,
		Error:      truncateAuditText(errorText, 500),
	}
	if record.Event == "" {
		record.Event = strings.TrimSpace(event)
	}
	if record.Title == "" {
		record.Title = strings.TrimSpace(title)
	}
	_ = s.store.AppendNotificationDelivery(record)
	return result, err
}

func (s *adminServer) notifyAdminEvent(r *http.Request, state installState, enabled bool, event string, title string, message string) {
	if !enabled {
		return
	}
	result, err := s.deliverAdminNotification(r.Context(), state, event, title, message)
	if err != nil {
		s.recordAuditEvent(r, state, auditEventInput{
			Category:   "operation",
			Action:     "事件通知失败",
			Detail:     title + "：" + err.Error(),
			StatusCode: http.StatusOK,
		})
		return
	}
	s.recordAuditEvent(r, state, auditEventInput{
		Category:   "operation",
		Action:     "发送事件通知",
		Detail:     result.Message,
		StatusCode: http.StatusOK,
	})
}

func sendEventNotification(ctx context.Context, state installState, event string, title string, message string) (notificationSendResult, error) {
	notifications := state.Notifications.normalized()
	payload := notificationPayload{
		Event:     strings.TrimSpace(event),
		Title:     strings.TrimSpace(title),
		Message:   truncateAuditText(message, 500),
		SiteName:  strings.TrimSpace(state.SiteName),
		Receiver:  notifications.Receiver,
		Timestamp: time.Now().UTC().Format(time.RFC3339),
	}
	if payload.Event == "" {
		payload.Event = "admin_event"
	}
	if payload.Title == "" {
		payload.Title = "Moyi Admin 事件通知"
	}
	if payload.SiteName == "" {
		payload.SiteName = "Moyi Admin"
	}
	return sendNotification(ctx, notifications, payload)
}

func sendNotification(ctx context.Context, notifications notificationConfig, payload notificationPayload) (notificationSendResult, error) {
	notifications = notifications.normalized()
	result := notificationSendResult{
		Event:    payload.Event,
		Title:    payload.Title,
		Receiver: notifications.Receiver,
		Channel:  notifications.Channel,
		Target:   maskNotificationTarget(notifications),
	}
	if err := validateNotificationConfig(notifications); err != nil {
		return result, err
	}
	if !notifications.Enabled || notifications.Channel == "disabled" {
		return result, errors.New("通知通道未启用")
	}

	switch notifications.Channel {
	case "webhook":
		body, err := json.Marshal(payload)
		if err != nil {
			return result, fmt.Errorf("构造通知内容失败：%w", err)
		}
		req, err := http.NewRequestWithContext(ctx, http.MethodPost, notifications.WebhookURL, bytes.NewReader(body))
		if err != nil {
			return result, fmt.Errorf("创建 Webhook 请求失败：%w", err)
		}
		req.Header.Set("Content-Type", "application/json")
		req.Header.Set("User-Agent", "moyi-admin-notifier/1.0")
		resp, err := notificationHTTPClient.Do(req)
		if err != nil {
			return result, fmt.Errorf("请求 Webhook 失败：%w", err)
		}
		defer resp.Body.Close()
		result.StatusCode = resp.StatusCode
		respBody, _ := io.ReadAll(io.LimitReader(resp.Body, 4096))
		if resp.StatusCode < 200 || resp.StatusCode >= 300 {
			message := strings.TrimSpace(string(respBody))
			if message == "" {
				message = http.StatusText(resp.StatusCode)
			}
			return result, fmt.Errorf("Webhook 返回 %d：%s", resp.StatusCode, message)
		}
		result.Message = payload.Title + " 已通过 Webhook 发送给 " + notifications.Receiver
		return result, nil
	case "feishu":
		body, err := json.Marshal(buildFeishuNotificationPayload(notifications, payload))
		if err != nil {
			return result, fmt.Errorf("构造飞书机器人通知失败：%w", err)
		}
		req, err := http.NewRequestWithContext(ctx, http.MethodPost, notifications.WebhookURL, bytes.NewReader(body))
		if err != nil {
			return result, fmt.Errorf("创建飞书机器人请求失败：%w", err)
		}
		req.Header.Set("Content-Type", "application/json")
		req.Header.Set("User-Agent", "moyi-admin-notifier/1.0")
		resp, err := notificationHTTPClient.Do(req)
		if err != nil {
			return result, fmt.Errorf("请求飞书机器人失败：%w", err)
		}
		defer resp.Body.Close()
		result.StatusCode = resp.StatusCode
		respBody, _ := io.ReadAll(io.LimitReader(resp.Body, 4096))
		if resp.StatusCode < 200 || resp.StatusCode >= 300 {
			message := strings.TrimSpace(string(respBody))
			if message == "" {
				message = http.StatusText(resp.StatusCode)
			}
			return result, fmt.Errorf("飞书机器人返回 %d：%s", resp.StatusCode, message)
		}
		if message := feishuNotificationError(respBody); message != "" {
			return result, errors.New(message)
		}
		result.Message = payload.Title + " 已通过飞书机器人发送给 " + notifications.Receiver
		return result, nil
	default:
		return result, errors.New("暂不支持该通知通道")
	}
}

func buildFeishuNotificationPayload(notifications notificationConfig, payload notificationPayload) map[string]any {
	text := strings.Join([]string{
		"【" + displayNotificationValue(payload.SiteName, "Moyi Admin") + "】" + displayNotificationValue(payload.Title, "事件通知"),
		"事件：" + displayNotificationValue(payload.Event, "admin_event"),
		"接收：" + displayNotificationValue(notifications.Receiver, "未设置"),
		"时间：" + displayNotificationValue(payload.Timestamp, time.Now().UTC().Format(time.RFC3339)),
		"",
		displayNotificationValue(payload.Message, "系统事件已触发。"),
	}, "\n")
	body := map[string]any{
		"msg_type": "text",
		"content": map[string]string{
			"text": text,
		},
	}
	if strings.TrimSpace(notifications.FeishuSecret) != "" {
		timestamp := strconv.FormatInt(time.Now().Unix(), 10)
		body["timestamp"] = timestamp
		body["sign"] = feishuRobotSign(timestamp, notifications.FeishuSecret)
	}
	return body
}

func feishuRobotSign(timestamp string, secret string) string {
	stringToSign := timestamp + "\n" + strings.TrimSpace(secret)
	mac := hmac.New(sha256.New, []byte(stringToSign))
	return base64.StdEncoding.EncodeToString(mac.Sum(nil))
}

func feishuNotificationError(respBody []byte) string {
	var parsed map[string]any
	if err := json.Unmarshal(respBody, &parsed); err != nil {
		return ""
	}
	for _, key := range []string{"code", "StatusCode"} {
		value, ok := parsed[key]
		if !ok {
			continue
		}
		code, ok := numericJSONValue(value)
		if !ok || code == 0 {
			continue
		}
		message := displayNotificationValue(stringJSONValue(parsed["msg"]), stringJSONValue(parsed["StatusMessage"]))
		if message == "" {
			message = strings.TrimSpace(string(respBody))
		}
		return fmt.Sprintf("飞书机器人返回 %d：%s", int(code), message)
	}
	return ""
}

func numericJSONValue(value any) (float64, bool) {
	switch v := value.(type) {
	case float64:
		return v, true
	case int:
		return float64(v), true
	case string:
		n, err := strconv.ParseFloat(strings.TrimSpace(v), 64)
		return n, err == nil
	default:
		return 0, false
	}
}

func stringJSONValue(value any) string {
	if text, ok := value.(string); ok {
		return strings.TrimSpace(text)
	}
	return ""
}

func maskNotificationTarget(notifications notificationConfig) string {
	notifications = notifications.normalized()
	switch notifications.Channel {
	case "feishu":
		return maskFeishuWebhookTarget(notifications.WebhookURL)
	case "webhook":
		parsed, err := url.Parse(notifications.WebhookURL)
		if err != nil || parsed.Scheme == "" || parsed.Host == "" {
			return truncateAuditText(notifications.WebhookURL, 160)
		}
		parsed.User = nil
		parsed.RawQuery = ""
		parsed.Fragment = ""
		return truncateAuditText(parsed.String(), 160)
	default:
		return notificationChannelLabel(notifications.Channel)
	}
}

func maskFeishuWebhookTarget(rawURL string) string {
	parsed, err := url.Parse(strings.TrimSpace(rawURL))
	if err != nil || parsed.Scheme == "" || parsed.Host == "" {
		return "飞书机器人 Webhook 已配置"
	}
	parts := strings.Split(strings.Trim(parsed.Path, "/"), "/")
	if len(parts) > 0 {
		parts[len(parts)-1] = "****"
		parsed.Path = "/" + strings.Join(parts, "/")
	}
	parsed.User = nil
	parsed.RawQuery = ""
	parsed.Fragment = ""
	return truncateAuditText(parsed.String(), 160)
}

func displayNotificationValue(value string, fallback string) string {
	value = strings.TrimSpace(value)
	if value == "" {
		return fallback
	}
	return value
}

func normalizeStorageExtensions(value string) string {
	parts := strings.FieldsFunc(strings.ToLower(value), func(r rune) bool {
		return r == ',' || r == '，' || r == ';' || r == '；' || r == ' ' || r == '\n' || r == '\t'
	})
	seen := map[string]bool{}
	normalized := make([]string, 0, len(parts))
	for _, part := range parts {
		part = strings.TrimSpace(part)
		if part == "" {
			continue
		}
		if !strings.HasPrefix(part, ".") {
			part = "." + part
		}
		if seen[part] {
			continue
		}
		seen[part] = true
		normalized = append(normalized, part)
	}
	return strings.Join(normalized, ",")
}

func validateStorageConfig(storage storageConfig) error {
	if storage.Driver != "local" {
		return errors.New("当前阶段仅支持本地文件系统存储")
	}
	if storage.LocalPath == "" {
		return errors.New("本地存储目录不能为空")
	}
	if storage.MaxFileSizeMB < 1 || storage.MaxFileSizeMB > 1024 {
		return errors.New("单文件大小限制需要在 1 到 1024 MB 之间")
	}
	if storage.AgentExportRetentionDays < 1 || storage.AgentExportRetentionDays > 365 {
		return errors.New("导出文件保留天数需要在 1 到 365 天之间")
	}
	if storage.PublicURL != "" && !strings.HasPrefix(storage.PublicURL, "/") && !strings.HasPrefix(storage.PublicURL, "http://") && !strings.HasPrefix(storage.PublicURL, "https://") {
		return errors.New("公开访问前缀需要以 /、http:// 或 https:// 开头")
	}
	if storage.AllowedExtensions == "" {
		return errors.New("允许扩展名不能为空")
	}
	return nil
}

func storagePathStatus(path string) string {
	path = strings.TrimSpace(path)
	if path == "" {
		return "未配置存储目录"
	}
	info, err := os.Stat(path)
	if errors.Is(err, os.ErrNotExist) {
		return "保存后自动创建"
	}
	if err != nil {
		return "目录不可读"
	}
	if !info.IsDir() {
		return "路径不是目录"
	}
	return "目录可用"
}

func storagePathStatusClass(path string) string {
	switch storagePathStatus(path) {
	case "目录可用":
		return "is-ready"
	case "保存后自动创建":
		return "is-progress"
	default:
		return "is-warning"
	}
}

func storageAllowedDescription(extensions string) string {
	count := 0
	for _, part := range strings.Split(extensions, ",") {
		if strings.TrimSpace(part) != "" {
			count++
		}
	}
	if count == 0 {
		return "未配置扩展名"
	}
	return strconv.Itoa(count) + " 种扩展名"
}

func defaultAccessConfig() accessConfig {
	return accessConfig{
		Roles: []adminRoleConfig{
			{
				Key:                   "super_admin",
				Name:                  "超级管理员",
				Scope:                 "后台全局管理",
				Status:                "enabled",
				Description:           "拥有后台所有管理能力",
				DataScope:             agentTableAccessAll,
				MenuKeys:              []string{"dashboard", "foundation", "data_sources", "extensions", "ai", "wechat_agent", "users", "user_groups", "user_sessions", "user_permissions", "settings", "files", "tasks", "notifications", "audit"},
				PermissionKeys:        []string{"admin.users.manage", "admin.roles.manage", "admin.sessions.manage", "admin.settings.manage", "admin.data_sources.manage", "admin.extensions.read", "admin.files.manage", "admin.tasks.manage", "agent.wechat.manage", "agent.tables.read", "agent.sql.select", "agent.web.read", "agent.image.generate", "agent.secrets.mask"},
				MenusConfigured:       true,
				PermissionsConfigured: true,
			},
			{
				Key:                   "ops_admin",
				Name:                  "运维管理员",
				Scope:                 "基础设施、文件、数据源和日志",
				Status:                "enabled",
				Description:           "适合维护系统设置与基础服务",
				DataScope:             agentTableAccessTables,
				AllowedTables:         []string{"admin_settings", "data_sources", "schema_snapshots", "storage_settings", "upload_files", "background_tasks", "background_task_logs", "notification_deliveries", "setting_change_logs", "audit_events"},
				MenuKeys:              []string{"dashboard", "foundation", "data_sources", "settings", "files", "tasks", "notifications", "audit"},
				PermissionKeys:        []string{"admin.settings.manage", "admin.data_sources.manage", "admin.files.manage", "admin.tasks.manage"},
				MenusConfigured:       true,
				PermissionsConfigured: true,
			},
			{
				Key:                   "agent_reader",
				Name:                  "智能体只读访问",
				Scope:                 "智能体运行、数据源结构和文件视图",
				Status:                "enabled",
				Description:           "适合查看数据、导出报表和使用 AI 工具",
				DataScope:             agentTableAccessTables,
				AllowedTables:         []string{"data_sources", "schema_snapshots", "upload_files", "agent_runs", "agent_sessions", "agent_tool_results", "agent_wechat_messages", "ai_capabilities"},
				MenuKeys:              []string{"dashboard", "ai", "data_sources", "files"},
				PermissionKeys:        []string{"admin.extensions.read", "agent.tables.read", "agent.sql.select", "agent.web.read", "agent.image.generate", "agent.secrets.mask"},
				MenusConfigured:       true,
				PermissionsConfigured: true,
			},
		},
		Menus: []adminMenuConfig{
			{Key: "dashboard", Label: "工作台", Path: "/workspace", Status: "enabled"},
			{Key: "foundation", Label: "基础服务", Path: "/foundation", Status: "enabled"},
			{Key: "data_sources", Label: "数据源", Path: "/data-sources", Status: "enabled"},
			{Key: "extensions", Label: "能力扩展", Path: "/extensions", Status: "enabled"},
			{Key: "ai", Label: "AI 智能体", Path: "/ai", Status: "enabled"},
			{Key: "wechat_agent", Label: "微信 Agent", Path: "/wechat-agent", Status: "enabled"},
			{Key: "users", Label: "管理员账号", Path: "/users", Status: "enabled"},
			{Key: "user_groups", Label: "用户组权限", Path: "/users/groups", Status: "enabled"},
			{Key: "user_sessions", Label: "登录会话", Path: "/users/sessions", Status: "enabled"},
			{Key: "user_permissions", Label: "菜单权限", Path: "/users/permissions", Status: "enabled"},
			{Key: "settings", Label: "系统设置", Path: "/settings", Status: "enabled"},
			{Key: "files", Label: "文件管理", Path: "/files", Status: "enabled"},
			{Key: "tasks", Label: "后台任务", Path: "/tasks", Status: "enabled"},
			{Key: "notifications", Label: "通知事件", Path: "/notifications", Status: "enabled"},
			{Key: "audit", Label: "审计日志", Path: "/audit", Status: "enabled"},
		},
		Permissions: []adminPermissionConfig{
			{Key: "admin.users.manage", Subject: "admin_users", Permission: "manage", Boundary: "允许创建、禁用、删除非初始化管理员", Status: "enabled"},
			{Key: "admin.roles.manage", Subject: "admin_roles", Permission: "manage", Boundary: "允许维护用户组、后台菜单和动作权限分配", Status: "enabled"},
			{Key: "admin.sessions.manage", Subject: "admin_sessions", Permission: "revoke", Boundary: "允许查看后台登录会话并强制下线非当前会话", Status: "enabled"},
			{Key: "admin.settings.manage", Subject: "admin_settings", Permission: "manage", Boundary: "允许维护站点、存储和运行参数", Status: "enabled"},
			{Key: "admin.data_sources.manage", Subject: "data_sources", Permission: "manage", Boundary: "允许登记、测试和删除业务数据源", Status: "enabled"},
			{Key: "admin.extensions.read", Subject: "plugin_extensions", Permission: "read", Boundary: "允许查看插件扩展、资源模型和生成工具", Status: "enabled"},
			{Key: "admin.files.manage", Subject: "upload_files", Permission: "manage", Boundary: "允许上传、预览、下载和删除后台文件", Status: "enabled"},
			{Key: "admin.tasks.manage", Subject: "background_tasks", Permission: "manage", Boundary: "允许创建、执行和重试后台任务", Status: "enabled"},
			{Key: "agent.wechat.manage", Subject: "agent_wechat_channels", Permission: "manage", Boundary: "允许维护微信 Agent 通道、登录二维码和管理员身份绑定", Status: "enabled"},
			{Key: "agent.tables.read", Subject: "all_registered_tables", Permission: "read", Boundary: "允许读取所有已登记数据表和虚拟表", Status: "enabled"},
			{Key: "agent.sql.select", Subject: "all_registered_tables", Permission: "select", Boundary: "仅允许 SELECT，拒绝写入、多语句和危险关键字", Status: "enabled"},
			{Key: "agent.web.read", Subject: "public_web_pages", Permission: "read", Boundary: "允许访问公开 http/https 页面，默认拒绝本机、内网和受保护地址", Status: "enabled"},
			{Key: "agent.image.generate", Subject: "image_generation", Permission: "generate", Boundary: "允许调用百炼图片模型生成海报、插图、封面和其他公开图片素材", Status: "enabled"},
			{Key: "agent.secrets.mask", Subject: "sensitive_fields", Permission: "mask", Boundary: "API Key、密码、盐值和哈希只允许脱敏展示", Status: "enabled"},
		},
	}
}

func (a accessConfig) normalized(state installState) accessConfig {
	defaults := defaultAccessConfig()
	a.Menus = normalizeMenuConfigs(a.Menus)
	if len(a.Menus) == 0 {
		a.Menus = defaults.Menus
	} else {
		a.Menus = mergeDefaultMenuConfigs(a.Menus, defaults.Menus)
	}
	a.Permissions = normalizePermissionConfigs(a.Permissions)
	if len(a.Permissions) == 0 {
		a.Permissions = defaults.Permissions
	} else {
		a.Permissions = mergeDefaultPermissionConfigs(a.Permissions, defaults.Permissions)
	}
	a.Roles = normalizeRoleConfigsWithCatalog(a.Roles, a.Menus, a.Permissions)
	if len(a.Roles) == 0 {
		a.Roles = defaults.Roles
	} else {
		a.Roles = mergeDefaultRoleConfigsWithCatalog(a.Roles, defaults.Roles, a.Menus, a.Permissions)
	}
	a.Users = normalizeAdminAccounts(a.Users)
	if state.AdminUser != "" {
		bootstrap := adminAccountConfig{
			Username:     state.AdminUser,
			DisplayName:  state.AdminUser,
			Role:         "super_admin",
			Status:       "enabled",
			PasswordSalt: state.PasswordSalt,
			PasswordHash: state.PasswordHash,
			Source:       "install_state",
			CreatedAt:    state.InstalledAt,
		}
		a.Users = append([]adminAccountConfig{bootstrap}, removeAdminAccount(a.Users, state.AdminUser)...)
	}
	return a
}

func (a accessConfig) withoutBootstrap(state installState) accessConfig {
	a = a.normalized(state)
	a.Users = removeAdminAccount(a.Users, state.AdminUser)
	return a
}

func adminAccountFromForm(r *http.Request) (adminAccountConfig, string) {
	account := adminAccountConfig{
		Username:    strings.TrimSpace(r.FormValue("username")),
		DisplayName: strings.TrimSpace(r.FormValue("display_name")),
		Role:        strings.TrimSpace(r.FormValue("role")),
		Status:      strings.TrimSpace(r.FormValue("status")),
		Source:      "access_config",
	}
	if account.DisplayName == "" {
		account.DisplayName = account.Username
	}
	if account.Role == "" {
		account.Role = "agent_reader"
	}
	if account.Status == "" {
		account.Status = "enabled"
	}
	return account.normalized(), r.FormValue("password")
}

func adminRoleFromForm(r *http.Request) adminRoleConfig {
	role := adminRoleConfig{
		Key:                   strings.TrimSpace(r.FormValue("role_key")),
		Name:                  strings.TrimSpace(r.FormValue("role_name")),
		Scope:                 strings.TrimSpace(r.FormValue("role_scope")),
		Status:                strings.TrimSpace(r.FormValue("role_status")),
		Description:           strings.TrimSpace(r.FormValue("role_description")),
		DataScope:             strings.TrimSpace(r.FormValue("role_data_scope")),
		AllowedTables:         normalizeAgentAllowedTables([]string{r.FormValue("role_allowed_tables")}),
		MenuKeys:              normalizeAdminSelectionKeys([]string{r.FormValue("role_menu_keys")}),
		PermissionKeys:        normalizeAdminSelectionKeys([]string{r.FormValue("role_permission_keys")}),
		MenusConfigured:       true,
		PermissionsConfigured: true,
	}
	if role.Status == "" {
		role.Status = "enabled"
	}
	if role.DataScope != agentTableAccessTables {
		role.AllowedTables = nil
	}
	return role
}

func validateAdminRole(role adminRoleConfig) error {
	role.Key = strings.TrimSpace(role.Key)
	role.Name = strings.TrimSpace(role.Name)
	if role.Key == "" {
		return errors.New("请选择用户组")
	}
	if role.Name == "" {
		return errors.New("请输入用户组名称")
	}
	for _, r := range role.Key {
		if (r >= 'a' && r <= 'z') || (r >= 'A' && r <= 'Z') || (r >= '0' && r <= '9') || r == '_' || r == '-' {
			continue
		}
		return errors.New("用户组 Key 只能包含字母、数字、下划线或短横线")
	}
	scope := newAgentQueryScope(role.DataScope, role.AllowedTables)
	if scope.Mode == agentTableAccessTables && len(scope.Tables) == 0 {
		return errors.New("选择指定只读数据表时至少需要勾选一张表")
	}
	return nil
}

func (a adminAccountConfig) normalized() adminAccountConfig {
	a.Username = strings.TrimSpace(a.Username)
	a.DisplayName = strings.TrimSpace(a.DisplayName)
	a.Role = strings.TrimSpace(a.Role)
	a.Status = strings.ToLower(strings.TrimSpace(a.Status))
	a.Source = strings.TrimSpace(a.Source)
	if a.DisplayName == "" {
		a.DisplayName = a.Username
	}
	if a.Role == "" {
		a.Role = "agent_reader"
	}
	if a.Status != "disabled" {
		a.Status = "enabled"
	}
	if a.Source == "" {
		a.Source = "access_config"
	}
	return a
}

func validateAdminAccount(account adminAccountConfig, password string, allowBlankPassword bool) error {
	account = account.normalized()
	if account.Username == "" {
		return errors.New("请输入管理员账号")
	}
	if len([]rune(account.Username)) < 3 || len([]rune(account.Username)) > 32 {
		return errors.New("管理员账号长度需要在 3 到 32 位之间")
	}
	for _, r := range account.Username {
		if (r >= 'a' && r <= 'z') || (r >= 'A' && r <= 'Z') || (r >= '0' && r <= '9') || r == '_' {
			continue
		}
		return errors.New("管理员账号只能包含字母、数字和下划线")
	}
	if account.DisplayName == "" {
		return errors.New("请输入显示名称")
	}
	if account.Role == "" {
		return errors.New("请选择管理员角色")
	}
	if !allowBlankPassword && strings.TrimSpace(password) == "" {
		return errors.New("请输入管理员密码")
	}
	if strings.TrimSpace(password) != "" && len(password) < 6 {
		return errors.New("管理员密码长度至少 6 位")
	}
	return nil
}

func normalizeAdminAccounts(users []adminAccountConfig) []adminAccountConfig {
	out := make([]adminAccountConfig, 0, len(users))
	seen := map[string]bool{}
	for _, user := range users {
		user = user.normalized()
		if user.Username == "" {
			continue
		}
		key := strings.ToLower(user.Username)
		if seen[key] {
			continue
		}
		seen[key] = true
		out = append(out, user)
	}
	sort.SliceStable(out, func(i, j int) bool {
		if out[i].Source == "install_state" && out[j].Source != "install_state" {
			return true
		}
		if out[j].Source == "install_state" && out[i].Source != "install_state" {
			return false
		}
		return strings.ToLower(out[i].Username) < strings.ToLower(out[j].Username)
	})
	return out
}

func upsertAdminAccount(users []adminAccountConfig, account adminAccountConfig) []adminAccountConfig {
	account = account.normalized()
	users = removeAdminAccount(users, account.Username)
	return normalizeAdminAccounts(append(users, account))
}

func removeAdminAccount(users []adminAccountConfig, username string) []adminAccountConfig {
	username = strings.ToLower(strings.TrimSpace(username))
	out := make([]adminAccountConfig, 0, len(users))
	for _, user := range users {
		if strings.ToLower(strings.TrimSpace(user.Username)) == username {
			continue
		}
		out = append(out, user)
	}
	return out
}

func findAdminAccountIndex(users []adminAccountConfig, username string) int {
	username = strings.ToLower(strings.TrimSpace(username))
	for i := range users {
		if strings.ToLower(strings.TrimSpace(users[i].Username)) == username {
			return i
		}
	}
	return -1
}

func findAdminAccount(state installState, username string) (adminAccountConfig, bool) {
	username = strings.ToLower(strings.TrimSpace(username))
	for _, user := range state.Access.normalized(state).Users {
		if strings.ToLower(user.Username) == username {
			return user, true
		}
	}
	return adminAccountConfig{}, false
}

func upsertAdminRole(roles []adminRoleConfig, role adminRoleConfig) []adminRoleConfig {
	key := strings.ToLower(strings.TrimSpace(role.Key))
	out := make([]adminRoleConfig, 0, len(roles)+1)
	replaced := false
	for _, existing := range roles {
		if strings.ToLower(strings.TrimSpace(existing.Key)) == key {
			out = append(out, role)
			replaced = true
			continue
		}
		out = append(out, existing)
	}
	if !replaced {
		out = append(out, role)
	}
	return normalizeRoleConfigs(out)
}

func normalizeAdminSelectionKeys(values []string) []string {
	out := make([]string, 0, len(values))
	seen := map[string]struct{}{}
	for _, value := range values {
		for _, part := range strings.FieldsFunc(value, func(r rune) bool {
			return r == ',' || r == '，' || r == '\n' || r == '\r' || r == '\t' || r == ';' || r == '；' || r == ' '
		}) {
			key := strings.ToLower(strings.TrimSpace(part))
			if key == "" {
				continue
			}
			if _, ok := seen[key]; ok {
				continue
			}
			seen[key] = struct{}{}
			out = append(out, key)
		}
	}
	return out
}

func normalizeAdminMenuSelection(values []string, menus []adminMenuConfig) []string {
	allowed := make(map[string]string, len(menus))
	for _, menu := range normalizeMenuConfigs(menus) {
		if strings.EqualFold(strings.TrimSpace(menu.Status), "disabled") {
			continue
		}
		key := strings.ToLower(strings.TrimSpace(menu.Key))
		if key != "" {
			allowed[key] = menu.Key
		}
	}
	out := make([]string, 0, len(values))
	for _, value := range normalizeAdminSelectionKeys(values) {
		if original, ok := allowed[value]; ok {
			out = append(out, original)
		}
	}
	return out
}

func normalizeAdminPermissionSelection(values []string, permissions []adminPermissionConfig) []string {
	allowed := make(map[string]string, len(permissions))
	for _, permission := range normalizePermissionConfigs(permissions) {
		if strings.EqualFold(strings.TrimSpace(permission.Status), "disabled") {
			continue
		}
		key := strings.ToLower(strings.TrimSpace(permission.Key))
		if key != "" {
			allowed[key] = permission.Key
		}
	}
	out := make([]string, 0, len(values))
	for _, value := range normalizeAdminSelectionKeys(values) {
		if original, ok := allowed[value]; ok {
			out = append(out, original)
		}
	}
	return out
}

func findAdminRoleConfig(roles []adminRoleConfig, key string) (adminRoleConfig, bool) {
	key = strings.ToLower(strings.TrimSpace(key))
	for _, role := range roles {
		if strings.ToLower(strings.TrimSpace(role.Key)) == key {
			return role, true
		}
	}
	return adminRoleConfig{}, false
}

func normalizeRoleConfigs(roles []adminRoleConfig) []adminRoleConfig {
	return normalizeRoleConfigsWithCatalog(roles, defaultAccessConfig().Menus, defaultAccessConfig().Permissions)
}

func normalizeRoleConfigsWithCatalog(roles []adminRoleConfig, menus []adminMenuConfig, permissions []adminPermissionConfig) []adminRoleConfig {
	defaults := defaultRoleConfigByKey()
	availableMenus := normalizeMenuConfigs(menus)
	availablePermissions := normalizePermissionConfigs(permissions)
	out := make([]adminRoleConfig, 0, len(roles))
	seen := map[string]bool{}
	for _, role := range roles {
		role.Key = strings.TrimSpace(role.Key)
		role.Name = strings.TrimSpace(role.Name)
		role.Scope = strings.TrimSpace(role.Scope)
		role.Status = strings.ToLower(strings.TrimSpace(role.Status))
		role.Description = strings.TrimSpace(role.Description)
		rawDataScope := strings.TrimSpace(role.DataScope)
		if !role.MenusConfigured && len(normalizeAdminSelectionKeys(role.MenuKeys)) > 0 {
			role.MenusConfigured = true
		}
		if !role.PermissionsConfigured && len(normalizeAdminSelectionKeys(role.PermissionKeys)) > 0 {
			role.PermissionsConfigured = true
		}
		role.MenuKeys = normalizeAdminMenuSelection(role.MenuKeys, availableMenus)
		role.PermissionKeys = normalizeAdminPermissionSelection(role.PermissionKeys, availablePermissions)
		role.AllowedTables = normalizeAgentAllowedTables(role.AllowedTables)
		role.DataScope = normalizeAgentTableAccessMode(role.DataScope)
		if role.Key != "" {
			if fallback, ok := defaults[strings.ToLower(role.Key)]; ok {
				if rawDataScope == "" {
					role.DataScope = fallback.DataScope
					role.AllowedTables = append([]string(nil), fallback.AllowedTables...)
				}
				if role.DataScope == agentTableAccessTables && len(role.AllowedTables) == 0 {
					role.AllowedTables = append([]string(nil), fallback.AllowedTables...)
				}
				if !role.MenusConfigured {
					role.MenuKeys = append([]string(nil), fallback.MenuKeys...)
				}
				if !role.PermissionsConfigured {
					role.PermissionKeys = append([]string(nil), fallback.PermissionKeys...)
				}
			} else if rawDataScope == "" {
				role.DataScope = agentTableAccessNone
			}
		}
		if role.DataScope == agentTableAccessTables && len(role.AllowedTables) == 0 {
			role.DataScope = agentTableAccessNone
		}
		if role.DataScope != agentTableAccessTables {
			role.AllowedTables = nil
		}
		if role.Key == "" || role.Name == "" {
			continue
		}
		if role.Status != "disabled" {
			role.Status = "enabled"
		}
		key := strings.ToLower(role.Key)
		if seen[key] {
			continue
		}
		seen[key] = true
		out = append(out, role)
	}
	return out
}

func defaultRoleConfigByKey() map[string]adminRoleConfig {
	defaults := defaultAccessConfig()
	out := make(map[string]adminRoleConfig, len(defaults.Roles))
	for _, role := range defaults.Roles {
		out[strings.ToLower(strings.TrimSpace(role.Key))] = role
	}
	return out
}

func mergeDefaultRoleConfigs(roles []adminRoleConfig, defaults []adminRoleConfig) []adminRoleConfig {
	return mergeDefaultRoleConfigsWithCatalog(roles, defaults, defaultAccessConfig().Menus, defaultAccessConfig().Permissions)
}

func mergeDefaultRoleConfigsWithCatalog(roles []adminRoleConfig, defaults []adminRoleConfig, menus []adminMenuConfig, permissions []adminPermissionConfig) []adminRoleConfig {
	seen := map[string]bool{}
	for _, role := range roles {
		seen[strings.ToLower(strings.TrimSpace(role.Key))] = true
	}
	for _, role := range defaults {
		key := strings.ToLower(strings.TrimSpace(role.Key))
		if key == "" || seen[key] {
			continue
		}
		roles = append(roles, role)
		seen[key] = true
	}
	return normalizeRoleConfigsWithCatalog(roles, menus, permissions)
}

func normalizeMenuConfigs(menus []adminMenuConfig) []adminMenuConfig {
	out := make([]adminMenuConfig, 0, len(menus))
	seen := map[string]bool{}
	for _, menu := range menus {
		menu.Key = strings.TrimSpace(menu.Key)
		menu.Label = strings.TrimSpace(menu.Label)
		menu.Path = strings.TrimSpace(menu.Path)
		menu.Status = strings.ToLower(strings.TrimSpace(menu.Status))
		if menu.Key == "" || menu.Label == "" || menu.Path == "" {
			continue
		}
		if menu.Status != "disabled" {
			menu.Status = "enabled"
		}
		key := strings.ToLower(menu.Key)
		if seen[key] {
			continue
		}
		seen[key] = true
		out = append(out, menu)
	}
	return out
}

func mergeDefaultMenuConfigs(menus []adminMenuConfig, defaults []adminMenuConfig) []adminMenuConfig {
	seen := map[string]bool{}
	for _, menu := range menus {
		seen[strings.ToLower(strings.TrimSpace(menu.Key))] = true
	}
	for _, menu := range defaults {
		key := strings.ToLower(strings.TrimSpace(menu.Key))
		if key == "" || seen[key] {
			continue
		}
		menus = append(menus, menu)
		seen[key] = true
	}
	return menus
}

func normalizePermissionConfigs(permissions []adminPermissionConfig) []adminPermissionConfig {
	out := make([]adminPermissionConfig, 0, len(permissions))
	seen := map[string]bool{}
	for _, permission := range permissions {
		permission.Key = strings.TrimSpace(permission.Key)
		permission.Subject = strings.TrimSpace(permission.Subject)
		permission.Permission = strings.TrimSpace(permission.Permission)
		permission.Boundary = strings.TrimSpace(permission.Boundary)
		permission.Status = strings.ToLower(strings.TrimSpace(permission.Status))
		if permission.Key == "" || permission.Subject == "" || permission.Permission == "" {
			continue
		}
		if permission.Status != "disabled" {
			permission.Status = "enabled"
		}
		key := strings.ToLower(permission.Key)
		if seen[key] {
			continue
		}
		seen[key] = true
		out = append(out, permission)
	}
	return out
}

func mergeDefaultPermissionConfigs(permissions []adminPermissionConfig, defaults []adminPermissionConfig) []adminPermissionConfig {
	seen := map[string]bool{}
	defaultByKey := make(map[string]adminPermissionConfig, len(defaults))
	for _, permission := range defaults {
		key := strings.ToLower(strings.TrimSpace(permission.Key))
		if key != "" {
			defaultByKey[key] = permission
		}
	}
	for i, permission := range permissions {
		key := strings.ToLower(strings.TrimSpace(permission.Key))
		seen[key] = true
		if refreshed, ok := defaultByKey[key]; ok {
			if permission.Status != "" {
				refreshed.Status = permission.Status
			}
			permissions[i] = refreshed
		}
	}
	for _, permission := range defaults {
		key := strings.ToLower(strings.TrimSpace(permission.Key))
		if key == "" || seen[key] {
			continue
		}
		permissions = append(permissions, permission)
		seen[key] = true
	}
	return permissions
}

func accessStatusText(status string) string {
	if strings.EqualFold(status, "disabled") {
		return "禁用"
	}
	return "启用"
}

func accessStatusClass(status string) string {
	if strings.EqualFold(status, "disabled") {
		return "is-muted"
	}
	return "is-ready"
}

func roleNameByKey(roles []adminRoleConfig, key string) string {
	key = strings.TrimSpace(key)
	for _, role := range roles {
		if role.Key == key {
			return role.Name
		}
	}
	return key
}

func roleTableAccessSummary(mode string, tables []string) string {
	scope := newAgentQueryScope(mode, tables)
	switch scope.Mode {
	case agentTableAccessAll:
		return "全部已登记表（只读）"
	case agentTableAccessTables:
		return strconv.Itoa(len(scope.Tables)) + " 张只读表"
	default:
		return "禁止读取数据表"
	}
}

type adminRoleAccess struct {
	Valid          bool
	Username       string
	RoleKey        string
	MenuKeys       []string
	PermissionKeys []string
	menuSet        map[string]struct{}
	permissionSet  map[string]struct{}
}

func adminPageMenuKey(active string) string {
	switch strings.TrimSpace(active) {
	case "dashboard":
		return "dashboard"
	case "foundation":
		return "foundation"
	case "data-sources":
		return "data_sources"
	case "extensions":
		return "extensions"
	case "ai", "ai-tasks", "ai-runs", "ai-capabilities":
		return "ai"
	case "wechat-agent", "wechat-agent-messages":
		return "wechat_agent"
	case "users":
		return "users"
	case "user-groups":
		return "user_groups"
	case "user-sessions":
		return "user_sessions"
	case "user-permissions":
		return "user_permissions"
	case "settings":
		return "settings"
	case "files":
		return "files"
	case "tasks":
		return "tasks"
	case "notifications":
		return "notifications"
	case "audit":
		return "audit"
	default:
		return ""
	}
}

func adminSidebarActiveKey(active string) string {
	switch strings.TrimSpace(active) {
	case "ai", "ai-tasks", "ai-runs", "ai-capabilities":
		return "ai"
	default:
		return strings.TrimSpace(active)
	}
}

func adminPagePath(active string) string {
	switch strings.TrimSpace(active) {
	case "dashboard":
		return "/workspace"
	case "foundation":
		return "/foundation"
	case "data-sources":
		return "/data-sources"
	case "extensions":
		return "/extensions"
	case "ai":
		return "/ai"
	case "ai-tasks":
		return "/ai/tasks"
	case "ai-runs":
		return "/ai/runs"
	case "ai-capabilities":
		return "/ai/capabilities"
	case "wechat-agent":
		return "/wechat-agent"
	case "wechat-agent-messages":
		return "/wechat-agent/messages"
	case "users":
		return "/users"
	case "user-groups":
		return "/users/groups"
	case "user-sessions":
		return "/users/sessions"
	case "user-permissions":
		return "/users/permissions"
	case "settings":
		return "/settings"
	case "files":
		return "/files"
	case "tasks":
		return "/tasks"
	case "notifications":
		return "/notifications"
	case "audit":
		return "/audit"
	default:
		return "/workspace"
	}
}

func adminPageRequiredPermission(active string) string {
	switch strings.TrimSpace(active) {
	case "users":
		return "admin.users.manage"
	case "user-groups":
		return "admin.roles.manage"
	case "user-sessions":
		return "admin.sessions.manage"
	default:
		return ""
	}
}

func effectiveRoleMenuKeys(menus []adminMenuConfig, role adminRoleConfig) []string {
	keys := normalizeAdminMenuSelection(role.MenuKeys, menus)
	if len(keys) == 0 {
		return []string{"dashboard"}
	}
	return keys
}

func effectiveRolePermissionKeys(menus []adminMenuConfig, permissions []adminPermissionConfig, role adminRoleConfig) []string {
	keys := normalizeAdminPermissionSelection(role.PermissionKeys, permissions)
	menuKeys := effectiveRoleMenuKeys(menus, role)
	if accessPermissionEnabled(permissions, "agent.web.read") &&
		roleGetsDefaultAgentPermission(menuKeys, keys) &&
		!containsNormalizedAdminKey(keys, "agent.web.read") {
		keys = append(keys, "agent.web.read")
	}
	if accessPermissionEnabled(permissions, "agent.image.generate") &&
		roleGetsDefaultAgentPermission(menuKeys, keys) &&
		!containsNormalizedAdminKey(keys, "agent.image.generate") {
		keys = append(keys, "agent.image.generate")
	}
	return keys
}

func roleGetsDefaultAgentPermission(menuKeys []string, permissionKeys []string) bool {
	for _, key := range menuKeys {
		switch strings.ToLower(strings.TrimSpace(key)) {
		case "ai", "wechat_agent":
			return true
		}
	}
	for _, key := range permissionKeys {
		switch strings.ToLower(strings.TrimSpace(key)) {
		case "agent.tables.read", "agent.sql.select", "agent.wechat.manage", "agent.web.read", "agent.image.generate":
			return true
		}
	}
	return false
}

func accessPermissionEnabled(permissions []adminPermissionConfig, key string) bool {
	key = strings.ToLower(strings.TrimSpace(key))
	for _, permission := range normalizePermissionConfigs(permissions) {
		if strings.ToLower(strings.TrimSpace(permission.Key)) != key {
			continue
		}
		return strings.ToLower(strings.TrimSpace(permission.Status)) != "disabled"
	}
	return false
}

func containsNormalizedAdminKey(values []string, target string) bool {
	target = strings.ToLower(strings.TrimSpace(target))
	for _, value := range values {
		if strings.ToLower(strings.TrimSpace(value)) == target {
			return true
		}
	}
	return false
}

func adminRoleAccessForUsername(state installState, username string) adminRoleAccess {
	access := state.Access.normalized(state)
	user, ok := findAdminAccountInAccess(access, username)
	if !ok || user.Status == "disabled" {
		return adminRoleAccess{}
	}
	role, ok := findAdminRoleConfig(access.Roles, user.Role)
	if !ok || role.Status == "disabled" {
		return adminRoleAccess{}
	}
	menuKeys := effectiveRoleMenuKeys(access.Menus, role)
	permissionKeys := effectiveRolePermissionKeys(access.Menus, access.Permissions, role)
	menuSet := make(map[string]struct{}, len(menuKeys))
	for _, key := range menuKeys {
		menuSet[strings.ToLower(strings.TrimSpace(key))] = struct{}{}
	}
	permissionSet := make(map[string]struct{}, len(permissionKeys))
	for _, key := range permissionKeys {
		permissionSet[strings.ToLower(strings.TrimSpace(key))] = struct{}{}
	}
	return adminRoleAccess{
		Valid:          true,
		Username:       user.Username,
		RoleKey:        role.Key,
		MenuKeys:       menuKeys,
		PermissionKeys: permissionKeys,
		menuSet:        menuSet,
		permissionSet:  permissionSet,
	}
}

func (a adminRoleAccess) HasMenu(key string) bool {
	if !a.Valid {
		return false
	}
	_, ok := a.menuSet[strings.ToLower(strings.TrimSpace(key))]
	return ok
}

func (a adminRoleAccess) HasPermission(key string) bool {
	if !a.Valid {
		return false
	}
	_, ok := a.permissionSet[strings.ToLower(strings.TrimSpace(key))]
	return ok
}

func (a adminRoleAccess) CanViewPage(active string) bool {
	menuKey := adminPageMenuKey(active)
	if menuKey == "" || !a.HasMenu(menuKey) {
		return false
	}
	permissionKey := adminPageRequiredPermission(active)
	return permissionKey == "" || a.HasPermission(permissionKey)
}

func roleMenuAccessSummary(menus []adminMenuConfig, role adminRoleConfig) string {
	keys := effectiveRoleMenuKeys(menus, role)
	if len(keys) == 1 && strings.EqualFold(keys[0], "dashboard") {
		return "仅工作台入口"
	}
	return strconv.Itoa(len(keys)) + " 个后台菜单"
}

func rolePermissionAccessSummary(menus []adminMenuConfig, permissions []adminPermissionConfig, role adminRoleConfig) string {
	keys := effectiveRolePermissionKeys(menus, permissions, role)
	if len(keys) == 0 {
		return "未授予动作权限"
	}
	return strconv.Itoa(len(keys)) + " 项动作权限"
}

func adminLandingPath(state installState, username string, entry string) string {
	order := []string{"dashboard", "foundation", "data-sources", "extensions", "ai", "wechat-agent", "users", "user-groups", "user-sessions", "user-permissions", "settings", "files", "tasks", "notifications", "audit"}
	access := adminRoleAccessForUsername(state, username)
	for _, active := range order {
		if access.CanViewPage(active) {
			return entry + adminPagePath(active)
		}
	}
	if access.HasMenu("dashboard") {
		return entry + "/workspace"
	}
	return entry + "/login"
}

func agentScopeForAdminAccount(state installState, username string) agentQueryScope {
	accessProfile := adminRoleAccessForUsername(state, username)
	if !accessProfile.Valid || !accessProfile.HasPermission("agent.tables.read") {
		return newAgentQueryScope(agentTableAccessNone, nil)
	}
	access := state.Access.normalized(state)
	user, ok := findAdminAccountInAccess(access, username)
	if !ok {
		return newAgentQueryScope(agentTableAccessNone, nil)
	}
	role, ok := findAdminRoleConfig(access.Roles, user.Role)
	if !ok || role.Status == "disabled" {
		return newAgentQueryScope(agentTableAccessNone, nil)
	}
	return newAgentQueryScope(role.DataScope, role.AllowedTables)
}

func findAdminAccountInAccess(access accessConfig, username string) (adminAccountConfig, bool) {
	username = strings.ToLower(strings.TrimSpace(username))
	for _, user := range normalizeAdminAccounts(access.Users) {
		if strings.ToLower(strings.TrimSpace(user.Username)) == username {
			return user, true
		}
	}
	return adminAccountConfig{}, false
}

func defaultAgentWeChatAdminUser(state installState, currentUser string) string {
	access := state.Access.normalized(state)
	for _, candidate := range []string{currentUser, state.AdminUser} {
		if user, ok := findAdminAccountInAccess(access, candidate); ok && user.Status != "disabled" {
			return user.Username
		}
	}
	for _, user := range access.Users {
		if user.Status != "disabled" {
			return user.Username
		}
	}
	return strings.TrimSpace(state.AdminUser)
}

func agentScopeForWeChatChannel(state installState, channel agentWeChatChannelConfig) agentQueryScope {
	if strings.TrimSpace(channel.AdminUser) != "" {
		return agentScopeForAdminAccount(state, channel.AdminUser)
	}
	if strings.TrimSpace(channel.DataScope) != "" || len(normalizeAgentAllowedTables(channel.AllowedTables)) > 0 {
		return newAgentQueryScope(channel.DataScope, channel.AllowedTables)
	}
	return newAgentQueryScope(agentTableAccessNone, nil)
}

func agentWeChatChannelAdminRoleSummary(state installState, channel agentWeChatChannelConfig) string {
	if strings.TrimSpace(channel.AdminUser) == "" {
		if strings.TrimSpace(channel.DataScope) != "" || len(normalizeAgentAllowedTables(channel.AllowedTables)) > 0 {
			return "旧版通道授权"
		}
		return "未关联管理员"
	}
	access := state.Access.normalized(state)
	user, ok := findAdminAccountInAccess(access, channel.AdminUser)
	if !ok {
		return "管理员不存在"
	}
	role, ok := findAdminRoleConfig(access.Roles, user.Role)
	if !ok {
		return "用户组不存在"
	}
	return role.Name
}

func buildAdminUserRows(state installState, entry string) []adminUserRow {
	access := state.Access.normalized(state)
	rows := make([]adminUserRow, 0, len(access.Users))
	for _, user := range access.Users {
		statusKey := strings.ToLower(strings.TrimSpace(user.Status))
		if statusKey == "" {
			statusKey = "enabled"
		}
		statusText := accessStatusText(statusKey)
		toggleLabel := "禁用"
		if statusKey == "disabled" {
			toggleLabel = "启用"
		}
		lastSeen := formatAdminTime(user.LastLoginAt)
		if user.Source == "install_state" {
			lastSeen = "当前会话"
		}
		rows = append(rows, adminUserRow{
			Username:     user.Username,
			DisplayName:  user.DisplayName,
			Initial:      adminUserInitial(user.DisplayName, user.Username),
			Role:         roleNameByKey(access.Roles, user.Role),
			RoleKey:      user.Role,
			StatusKey:    statusKey,
			Status:       statusText,
			StatusClass:  accessStatusClass(statusKey),
			Source:       user.Source,
			SourceLabel:  adminUserSourceLabel(user.Source),
			CreatedAt:    formatAdminTime(user.CreatedAt),
			LastSeen:     lastSeen,
			CanDelete:    user.Source != "install_state",
			ToggleLabel:  toggleLabel,
			ToggleAction: entry + "/users/toggle",
			DeleteAction: entry + "/users/delete",
		})
	}
	return rows
}

func buildAdminUserMetrics(state installState) []adminMetric {
	access := state.Access.normalized(state)
	total := len(access.Users)
	enabled := 0
	disabled := 0
	protected := 0
	for _, user := range access.Users {
		status := strings.ToLower(strings.TrimSpace(user.Status))
		if status == "disabled" {
			disabled++
		} else {
			enabled++
		}
		if user.Source == "install_state" || strings.EqualFold(user.Username, state.AdminUser) {
			protected++
		}
	}
	return []adminMetric{
		{Label: "账号总数", Value: strconv.Itoa(total), Detail: "后台可登录身份", Status: "is-ready"},
		{Label: "启用账号", Value: strconv.Itoa(enabled), Detail: "允许进入后台", Status: "is-ready"},
		{Label: "禁用账号", Value: strconv.Itoa(disabled), Detail: "已阻止登录", Status: "is-warning"},
		{Label: "用户组", Value: strconv.Itoa(len(access.Roles)), Detail: "角色与只读数据权限", Status: "is-ready"},
		{Label: "保护账号", Value: strconv.Itoa(protected), Detail: "初始化/超级管理员", Status: "is-secure"},
	}
}

func buildAdminRoleMetrics(state installState) []adminMetric {
	access := state.Access.normalized(state)
	total := len(access.Roles)
	enabled := 0
	disabled := 0
	scoped := 0
	assigned := 0
	for _, role := range access.Roles {
		status := strings.ToLower(strings.TrimSpace(role.Status))
		if status == "disabled" {
			disabled++
		} else {
			enabled++
		}
		if normalizeAgentTableAccessMode(role.DataScope) == agentTableAccessTables {
			scoped++
		}
	}
	for _, user := range access.Users {
		if strings.TrimSpace(user.Role) != "" {
			assigned++
		}
	}
	return []adminMetric{
		{Label: "用户组总数", Value: strconv.Itoa(total), Detail: "后台角色与权限集合", Status: "is-ready"},
		{Label: "启用用户组", Value: strconv.Itoa(enabled), Detail: "可被管理员账号关联", Status: "is-ready"},
		{Label: "指定只读表", Value: strconv.Itoa(scoped), Detail: "按白名单限制只读查询", Status: "is-progress"},
		{Label: "禁用用户组", Value: strconv.Itoa(disabled), Detail: "已停止分配使用", Status: "is-warning"},
		{Label: "关联管理员", Value: strconv.Itoa(assigned), Detail: "当前已绑定角色的管理员账号", Status: "is-secure"},
	}
}

func buildAdminSessionMetrics(records []adminSessionRecord, currentSessionID string) []adminMetric {
	total := len(records)
	active := 0
	revoked := 0
	expired := 0
	current := 0
	now := time.Now().UTC()
	for _, record := range records {
		status := strings.ToLower(strings.TrimSpace(record.Status))
		if status == "" {
			status = "active"
		}
		if status == "active" && !record.ExpiresAt.IsZero() && !now.Before(record.ExpiresAt) {
			status = "expired"
		}
		switch status {
		case "active":
			active++
		case "revoked":
			revoked++
		case "expired":
			expired++
		}
		if record.ID != "" && record.ID == currentSessionID {
			current++
		}
	}
	return []adminMetric{
		{Label: "会话总数", Value: strconv.Itoa(total), Detail: "最近登录与历史会话记录", Status: "is-ready"},
		{Label: "在线会话", Value: strconv.Itoa(active), Detail: "仍可继续访问后台", Status: "is-ready"},
		{Label: "当前会话", Value: strconv.Itoa(current), Detail: "你正在使用的登录会话", Status: "is-secure"},
		{Label: "已下线", Value: strconv.Itoa(revoked), Detail: "被手动撤销的后台会话", Status: "is-warning"},
		{Label: "已过期", Value: strconv.Itoa(expired), Detail: "超过有效期的历史登录", Status: "is-muted"},
	}
}

func buildAdminPermissionMetrics(state installState) []adminMetric {
	access := state.Access.normalized(state)
	menuTotal := len(access.Menus)
	menuEnabled := 0
	permissionTotal := len(access.Permissions)
	permissionEnabled := 0
	subjects := map[string]struct{}{}
	for _, menu := range access.Menus {
		if strings.ToLower(strings.TrimSpace(menu.Status)) != "disabled" {
			menuEnabled++
		}
	}
	for _, permission := range access.Permissions {
		if strings.ToLower(strings.TrimSpace(permission.Status)) != "disabled" {
			permissionEnabled++
		}
		subject := strings.TrimSpace(permission.Subject)
		if subject != "" {
			subjects[subject] = struct{}{}
		}
	}
	return []adminMetric{
		{Label: "菜单总数", Value: strconv.Itoa(menuTotal), Detail: "后台导航与入口定义", Status: "is-ready"},
		{Label: "启用菜单", Value: strconv.Itoa(menuEnabled), Detail: "当前会出现在后台导航", Status: "is-ready"},
		{Label: "权限项", Value: strconv.Itoa(permissionTotal), Detail: "对象、动作与边界规则", Status: "is-progress"},
		{Label: "启用权限", Value: strconv.Itoa(permissionEnabled), Detail: "当前生效的访问控制规则", Status: "is-ready"},
		{Label: "资源对象", Value: strconv.Itoa(len(subjects)), Detail: "被权限规则覆盖的资源域", Status: "is-secure"},
	}
}

func adminUserInitial(displayName string, username string) string {
	text := strings.TrimSpace(displayName)
	if text == "" {
		text = strings.TrimSpace(username)
	}
	for _, r := range text {
		return strings.ToUpper(string(r))
	}
	return "A"
}

func adminUserSourceLabel(source string) string {
	switch strings.ToLower(strings.TrimSpace(source)) {
	case "install_state":
		return "初始化账号"
	case "access_config":
		return "后台配置"
	default:
		if strings.TrimSpace(source) == "" {
			return "后台配置"
		}
		return source
	}
}

func buildAdminSessionRows(records []adminSessionRecord, entry string, currentSessionID string) []adminSessionRow {
	rows := make([]adminSessionRow, 0, len(records))
	now := time.Now().UTC()
	for _, record := range records {
		status := strings.ToLower(strings.TrimSpace(record.Status))
		if status == "" {
			status = "active"
		}
		if status == "active" && !record.ExpiresAt.IsZero() && !now.Before(record.ExpiresAt) {
			status = "expired"
		}
		rows = append(rows, adminSessionRow{
			Initial:      adminUserInitial(record.Username, record.Username),
			ID:           record.ID,
			IDShort:      shortSessionID(record.ID),
			Username:     record.Username,
			StatusKey:    status,
			IP:           displayNotificationValue(record.IP, "-"),
			UserAgent:    truncateAuditText(displayNotificationValue(record.UserAgent, "-"), 80),
			CreatedAt:    formatAdminTime(record.CreatedAt),
			ExpiresAt:    formatAdminTime(record.ExpiresAt),
			Status:       adminSessionStatusText(status),
			StatusClass:  adminSessionStatusClass(status),
			CanRevoke:    status == "active" && record.ID != "" && record.ID != currentSessionID,
			RevokeAction: entry + "/users/sessions/revoke",
		})
	}
	return rows
}

func shortSessionID(id string) string {
	id = strings.TrimSpace(id)
	if len(id) <= 14 {
		return id
	}
	return id[:9] + "..." + id[len(id)-4:]
}

func adminSessionStatusText(status string) string {
	switch strings.ToLower(strings.TrimSpace(status)) {
	case "active":
		return "在线"
	case "revoked":
		return "已下线"
	case "expired":
		return "已过期"
	default:
		return "未知"
	}
}

func adminSessionStatusClass(status string) string {
	switch strings.ToLower(strings.TrimSpace(status)) {
	case "active":
		return "is-ready"
	case "revoked", "expired":
		return "is-muted"
	default:
		return "is-warning"
	}
}

func buildAdminRoleRows(state installState) []adminRoleRow {
	access := state.Access.normalized(state)
	userCounts := make(map[string]int, len(access.Users))
	for _, user := range access.Users {
		key := strings.ToLower(strings.TrimSpace(user.Role))
		if key == "" {
			continue
		}
		userCounts[key]++
	}
	rows := make([]adminRoleRow, 0, len(access.Roles))
	for _, role := range access.Roles {
		statusKey := strings.ToLower(strings.TrimSpace(role.Status))
		if statusKey == "" {
			statusKey = "enabled"
		}
		roleKey := strings.ToLower(strings.TrimSpace(role.Key))
		rows = append(rows, adminRoleRow{
			Initial:           adminUserInitial(role.Name, role.Key),
			Key:               role.Key,
			Name:              role.Name,
			Scope:             role.Scope,
			StatusKey:         statusKey,
			Status:            accessStatusText(statusKey),
			StatusClass:       accessStatusClass(statusKey),
			Description:       role.Description,
			DataScope:         role.DataScope,
			AllowedTables:     agentAllowedTablesString(role.AllowedTables),
			AllowedSummary:    roleTableAccessSummary(role.DataScope, role.AllowedTables),
			MenuKeys:          strings.Join(effectiveRoleMenuKeys(access.Menus, role), ", "),
			MenuSummary:       roleMenuAccessSummary(access.Menus, role),
			PermissionKeys:    strings.Join(effectiveRolePermissionKeys(access.Menus, access.Permissions, role), ", "),
			PermissionSummary: rolePermissionAccessSummary(access.Menus, access.Permissions, role),
			UserCount:         userCounts[roleKey],
		})
	}
	return rows
}

func buildAdminMenuRows(state installState) []adminMenuRow {
	access := state.Access.normalized(state)
	rows := make([]adminMenuRow, 0, len(access.Menus))
	for _, menu := range access.Menus {
		statusKey := strings.ToLower(strings.TrimSpace(menu.Status))
		if statusKey == "" {
			statusKey = "enabled"
		}
		rows = append(rows, adminMenuRow{
			Initial:     adminUserInitial(menu.Label, menu.Key),
			Key:         menu.Key,
			Label:       menu.Label,
			Path:        menu.Path,
			StatusKey:   statusKey,
			Status:      accessStatusText(statusKey),
			StatusClass: accessStatusClass(statusKey),
		})
	}
	return rows
}

func buildAdminPermissionRows(state installState) []adminPermissionRow {
	access := state.Access.normalized(state)
	rows := make([]adminPermissionRow, 0, len(access.Permissions))
	for _, permission := range access.Permissions {
		statusKey := strings.ToLower(strings.TrimSpace(permission.Status))
		if statusKey == "" {
			statusKey = "enabled"
		}
		rows = append(rows, adminPermissionRow{
			Initial:     adminUserInitial(permission.Key, permission.Subject),
			Key:         permission.Key,
			Subject:     permission.Subject,
			Permission:  permission.Permission,
			Boundary:    permission.Boundary,
			StatusKey:   statusKey,
			Status:      accessStatusText(statusKey),
			StatusClass: accessStatusClass(statusKey),
		})
	}
	return rows
}

func userNoticeFromQuery(query url.Values) (string, string) {
	if message := strings.TrimSpace(query.Get("error")); message != "" {
		return message, "alert-danger"
	}
	switch query.Get("saved") {
	case "user":
		return "管理员账号已保存。", "alert-success"
	case "role":
		return "用户组权限已保存。", "alert-success"
	case "toggle":
		return "管理员状态已更新。", "alert-success"
	case "delete":
		return "管理员账号已删除。", "alert-success"
	case "session":
		return "后台会话已下线。", "alert-success"
	default:
		return "", ""
	}
}

func dataSourceConfigFromForm(r *http.Request) dataSourceConfig {
	driver := strings.TrimSpace(r.FormValue("driver"))
	if driver == "" {
		driver = "mysql"
	}
	ds := dataSourceConfig{
		Name:     strings.TrimSpace(r.FormValue("name")),
		Driver:   driver,
		Host:     strings.TrimSpace(r.FormValue("host")),
		Port:     strings.TrimSpace(r.FormValue("port")),
		Database: strings.TrimSpace(r.FormValue("database")),
		Username: strings.TrimSpace(r.FormValue("username")),
		Password: r.FormValue("password"),
		SSLMode:  strings.TrimSpace(r.FormValue("ssl_mode")),
		FilePath: strings.TrimSpace(r.FormValue("file_path")),
		Role:     strings.TrimSpace(r.FormValue("role")),
		Status:   "unchecked",
	}
	if ds.SSLMode == "" {
		ds.SSLMode = "disable"
	}
	if ds.Role == "" {
		ds.Role = "业务数据源"
	}
	return ds
}

func (ds dataSourceConfig) normalized() dataSourceConfig {
	ds.Name = strings.TrimSpace(ds.Name)
	ds.Driver = strings.ToLower(strings.TrimSpace(ds.Driver))
	ds.Host = strings.TrimSpace(ds.Host)
	ds.Port = strings.TrimSpace(ds.Port)
	ds.Database = strings.TrimSpace(ds.Database)
	ds.Username = strings.TrimSpace(ds.Username)
	ds.SSLMode = strings.TrimSpace(ds.SSLMode)
	ds.FilePath = strings.TrimSpace(ds.FilePath)
	ds.Role = strings.TrimSpace(ds.Role)
	ds.Status = strings.ToLower(strings.TrimSpace(ds.Status))
	ds.LastMessage = strings.TrimSpace(ds.LastMessage)
	ds.SchemaSummary = strings.TrimSpace(ds.SchemaSummary)
	if ds.Driver == "pgsql" {
		ds.Driver = "postgres"
	}
	if ds.Driver == "" {
		ds.Driver = "mysql"
	}
	if ds.SSLMode == "" {
		ds.SSLMode = "disable"
	}
	if ds.Role == "" {
		ds.Role = "业务数据源"
	}
	if ds.Status == "" {
		ds.Status = "unchecked"
	}
	switch ds.Driver {
	case "mysql":
		if ds.Port == "" {
			ds.Port = "3306"
		}
	case "postgres":
		if ds.Port == "" {
			ds.Port = "5432"
		}
	}
	return ds
}

func validateDataSourceConfig(ds dataSourceConfig) error {
	ds = ds.normalized()
	if ds.Name == "" {
		return errors.New("请输入数据源名称")
	}
	for i, r := range ds.Name {
		valid := (r >= 'a' && r <= 'z') || (r >= 'A' && r <= 'Z') || (r >= '0' && r <= '9') || r == '_' || r == '-'
		if !valid || (i == 0 && !((r >= 'a' && r <= 'z') || (r >= 'A' && r <= 'Z'))) {
			return errors.New("数据源名称需要以字母开头，只能包含字母、数字、中划线和下划线")
		}
	}
	if len([]rune(ds.Name)) > 32 {
		return errors.New("数据源名称不能超过 32 个字符")
	}
	switch ds.Driver {
	case "sqlite":
		if ds.FilePath == "" {
			return errors.New("请输入 SQLite 文件路径")
		}
	case "mysql", "postgres":
		if ds.Host == "" {
			return errors.New("请输入数据库主机")
		}
		if ds.Port == "" {
			return errors.New("请输入数据库端口")
		}
		port, err := strconv.Atoi(ds.Port)
		if err != nil || port <= 0 || port > 65535 {
			return errors.New("数据库端口不正确")
		}
		if ds.Database == "" {
			return errors.New("请输入数据库名称")
		}
		if ds.Username == "" {
			return errors.New("请输入数据库用户名")
		}
	default:
		return errors.New("请选择支持的数据源类型")
	}
	return nil
}

func upsertDataSource(sources []dataSourceConfig, source dataSourceConfig) []dataSourceConfig {
	source = source.normalized()
	for i := range sources {
		if strings.EqualFold(sources[i].Name, source.Name) {
			if strings.TrimSpace(source.Password) == "" {
				source.Password = sources[i].Password
			}
			source.Status = "unchecked"
			source.LastMessage = "配置已更新，等待重新测试"
			source.SchemaSummary = ""
			source.LastCheckedAt = time.Time{}
			sources[i] = source
			return normalizeDataSources(sources)
		}
	}
	return normalizeDataSources(append(sources, source))
}

func normalizeDataSources(sources []dataSourceConfig) []dataSourceConfig {
	out := make([]dataSourceConfig, 0, len(sources))
	seen := map[string]bool{}
	for _, source := range sources {
		source = source.normalized()
		if source.Name == "" {
			continue
		}
		key := strings.ToLower(source.Name)
		if seen[key] {
			continue
		}
		seen[key] = true
		out = append(out, source)
	}
	sort.SliceStable(out, func(i, j int) bool {
		return strings.ToLower(out[i].Name) < strings.ToLower(out[j].Name)
	})
	return out
}

func findDataSourceIndex(sources []dataSourceConfig, name string) int {
	name = strings.ToLower(strings.TrimSpace(name))
	for i := range sources {
		if strings.ToLower(strings.TrimSpace(sources[i].Name)) == name {
			return i
		}
	}
	return -1
}

func checkDataSourceConfig(source dataSourceConfig) databaseCheckResult {
	source = source.normalized()
	if err := validateDataSourceConfig(source); err != nil {
		return databaseCheckResult{OK: false, Message: err.Error(), Checks: []string{"配置校验未通过"}}
	}
	switch source.Driver {
	case "sqlite":
		return checkSQLiteDataSource(source.FilePath)
	case "mysql", "postgres":
		return checkNetworkDataSource(source)
	default:
		return databaseCheckResult{OK: false, Message: "不支持的数据源类型", Checks: []string{source.Driver}}
	}
}

func checkSQLiteDataSource(path string) databaseCheckResult {
	path = strings.TrimSpace(path)
	dir := filepath.Dir(path)
	if err := os.MkdirAll(dir, 0o755); err != nil {
		return databaseCheckResult{OK: false, Message: "无法创建 SQLite 目录：" + err.Error(), Checks: []string{"目录：" + dir}}
	}
	if info, err := os.Stat(path); err == nil {
		if info.IsDir() {
			return databaseCheckResult{OK: false, Message: "SQLite 路径不能是目录", Checks: []string{"路径：" + path}}
		}
		file, err := os.Open(path)
		if err != nil {
			return databaseCheckResult{OK: false, Message: "SQLite 文件不可读：" + err.Error(), Checks: []string{"路径：" + path}}
		}
		defer file.Close()
		header := make([]byte, 16)
		n, _ := io.ReadFull(file, header)
		if n >= 16 && string(header) != "SQLite format 3\x00" {
			return databaseCheckResult{OK: false, Message: "文件不是有效 SQLite 数据库", Checks: []string{"路径：" + path}}
		}
		inspection, err := inspectSQLiteSchema(path)
		checks := []string{"文件：" + path, "大小：" + formatAdminFileSize(info.Size())}
		if err != nil {
			checks = append(checks, "结构扫描失败："+err.Error())
			return databaseCheckResult{
				OK:      true,
				Message: "SQLite 文件可读取，结构扫描失败：" + err.Error(),
				Checks:  checks,
			}
		}
		checks = append(checks, inspection.Checks...)
		return databaseCheckResult{
			OK:         true,
			Message:    "SQLite 文件可读取，" + inspection.Summary + "。",
			Checks:     checks,
			Inspection: inspection,
		}
	} else if !errors.Is(err, os.ErrNotExist) {
		return databaseCheckResult{OK: false, Message: "SQLite 路径不可访问：" + err.Error(), Checks: []string{"路径：" + path}}
	}
	tmp, err := os.CreateTemp(dir, ".moyi-datasource-check-*")
	if err != nil {
		return databaseCheckResult{OK: false, Message: "SQLite 目录不可写：" + err.Error(), Checks: []string{"目录：" + dir}}
	}
	tmpName := tmp.Name()
	_ = tmp.Close()
	_ = os.Remove(tmpName)
	return databaseCheckResult{
		OK:      true,
		Message: "SQLite 文件尚未创建，目录可写，结构扫描等待文件创建。",
		Checks:  []string{"文件：" + path, "目录：" + dir, "结构扫描：等待数据库文件创建后读取真实表和字段"},
	}
}

func inspectSQLiteSchema(path string) (databaseSchemaInspection, error) {
	ctx, cancel := context.WithTimeout(context.Background(), 3*time.Second)
	defer cancel()

	db, err := sql.Open("sqlite", path)
	if err != nil {
		return databaseSchemaInspection{}, err
	}
	defer db.Close()
	if err := db.PingContext(ctx); err != nil {
		return databaseSchemaInspection{}, err
	}

	rows, err := db.QueryContext(ctx, `SELECT name FROM sqlite_master WHERE type IN ('table', 'view') AND name NOT LIKE 'sqlite_%' ORDER BY name`)
	if err != nil {
		return databaseSchemaInspection{}, err
	}
	defer rows.Close()

	tables := make([]string, 0)
	for rows.Next() {
		var name string
		if err := rows.Scan(&name); err != nil {
			return databaseSchemaInspection{}, err
		}
		if strings.TrimSpace(name) != "" {
			tables = append(tables, name)
		}
	}
	if err := rows.Err(); err != nil {
		return databaseSchemaInspection{}, err
	}

	inspectedTables := make([]inspectedDatabaseTable, 0, len(tables))
	for _, table := range tables {
		columns, err := inspectSQLiteTableColumns(ctx, db, table)
		if err != nil {
			return databaseSchemaInspection{}, err
		}
		inspectedTables = append(inspectedTables, inspectedDatabaseTable{
			Name:    table,
			Kind:    "table",
			Columns: columns,
		})
	}
	return summarizeDatabaseSchema(inspectedTables), nil
}

func inspectSQLiteTableColumns(ctx context.Context, db *sql.DB, table string) ([]inspectedDatabaseColumn, error) {
	rows, err := db.QueryContext(ctx, `PRAGMA table_info(`+quoteSQLiteIdentifier(table)+`)`)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	columns := make([]inspectedDatabaseColumn, 0)
	for rows.Next() {
		var cid int
		var name, columnType string
		var notNull int
		var defaultValue sql.NullString
		var pk int
		if err := rows.Scan(&cid, &name, &columnType, &notNull, &defaultValue, &pk); err != nil {
			return nil, err
		}
		column := inspectedDatabaseColumn{
			Name:     strings.TrimSpace(name),
			Type:     strings.ToUpper(strings.TrimSpace(columnType)),
			Nullable: notNull == 0,
		}
		if pk > 0 {
			column.Key = "PK"
		}
		columns = append(columns, column)
	}
	return columns, rows.Err()
}

func quoteSQLiteIdentifier(name string) string {
	return `"` + strings.ReplaceAll(name, `"`, `""`) + `"`
}

func minInt(left int, right int) int {
	if left < right {
		return left
	}
	return right
}

func checkNetworkDataSource(source dataSourceConfig) databaseCheckResult {
	source = source.normalized()
	return checkNetworkDatabase(networkDatabaseConfig{
		Driver:        source.Driver,
		DisplayName:   source.DisplayName(),
		Host:          source.Host,
		Port:          source.Port,
		Database:      source.Database,
		Username:      source.Username,
		Password:      source.Password,
		SSLMode:       source.SSLMode,
		Purpose:       "业务数据源",
		RequireSchema: true,
	})
}

type networkDatabaseConfig struct {
	Driver        string
	DisplayName   string
	Host          string
	Port          string
	Database      string
	Username      string
	Password      string
	SSLMode       string
	Purpose       string
	RequireSchema bool
}

type databaseSchemaInspection struct {
	Summary     string                   `json:"summary"`
	Checks      []string                 `json:"checks"`
	TableCount  int                      `json:"table_count"`
	ColumnCount int                      `json:"column_count"`
	Tables      []inspectedDatabaseTable `json:"tables,omitempty"`
}

type inspectedDatabaseTable struct {
	Name    string                    `json:"name"`
	Kind    string                    `json:"kind,omitempty"`
	Comment string                    `json:"comment,omitempty"`
	Columns []inspectedDatabaseColumn `json:"columns,omitempty"`
	Indexes []string                  `json:"indexes,omitempty"`
}

type inspectedDatabaseColumn struct {
	Name     string `json:"name"`
	Type     string `json:"type,omitempty"`
	Comment  string `json:"comment,omitempty"`
	Nullable bool   `json:"nullable"`
	Key      string `json:"key,omitempty"`
}

func checkNetworkDatabase(config networkDatabaseConfig) databaseCheckResult {
	config.Driver = normalizeDatabaseDriver(config.Driver)
	config.DisplayName = strings.TrimSpace(config.DisplayName)
	if config.DisplayName == "" {
		config.DisplayName = config.Driver
	}
	config.Purpose = strings.TrimSpace(config.Purpose)
	if config.Purpose == "" {
		config.Purpose = "数据库"
	}
	address := net.JoinHostPort(config.Host, config.Port)

	db, err := openNetworkSQLDatabase(config)
	if err != nil {
		return databaseCheckResult{
			OK:      false,
			Message: config.DisplayName + " 驱动初始化失败：" + err.Error(),
			Checks:  []string{"地址：" + address, "数据库：" + config.Database, "用途：" + config.Purpose},
		}
	}
	defer db.Close()
	db.SetMaxOpenConns(1)
	db.SetMaxIdleConns(1)
	db.SetConnMaxLifetime(30 * time.Second)

	ctx, cancel := context.WithTimeout(context.Background(), 8*time.Second)
	defer cancel()
	if err := db.PingContext(ctx); err != nil {
		return databaseCheckResult{
			OK:      false,
			Message: config.DisplayName + " 登录或数据库连接失败：" + err.Error(),
			Checks:  []string{"地址：" + address, "数据库：" + config.Database, "用户：" + config.Username},
		}
	}

	inspection, err := inspectNetworkDatabaseSchema(ctx, db, config.Driver)
	if err != nil {
		result := databaseCheckResult{
			OK:      !config.RequireSchema,
			Message: config.DisplayName + " 登录成功，但结构扫描失败：" + err.Error(),
			Checks:  []string{"地址：" + address, "数据库：" + config.Database, "用户：" + config.Username, "结构扫描：失败"},
		}
		if config.RequireSchema {
			result.Message = config.DisplayName + " 连接成功，但无法读取表结构：" + err.Error()
		}
		return result
	}
	checks := []string{"地址：" + address, "数据库：" + config.Database, "用户：" + config.Username, "用途：" + config.Purpose}
	checks = append(checks, inspection.Checks...)
	return databaseCheckResult{
		OK:         true,
		Message:    config.DisplayName + " 登录成功，" + inspection.Summary + "。",
		Checks:     checks,
		Inspection: inspection,
	}
}

func normalizeDatabaseDriver(driver string) string {
	driver = strings.ToLower(strings.TrimSpace(driver))
	if driver == "postgresql" {
		return "postgres"
	}
	return driver
}

func openNetworkSQLDatabase(config networkDatabaseConfig) (*sql.DB, error) {
	switch normalizeDatabaseDriver(config.Driver) {
	case "mysql":
		mysqlConfig := mysql.NewConfig()
		mysqlConfig.User = strings.TrimSpace(config.Username)
		mysqlConfig.Passwd = config.Password
		mysqlConfig.Net = "tcp"
		mysqlConfig.Addr = net.JoinHostPort(strings.TrimSpace(config.Host), strings.TrimSpace(config.Port))
		mysqlConfig.DBName = strings.TrimSpace(config.Database)
		mysqlConfig.ParseTime = true
		mysqlConfig.Timeout = 3 * time.Second
		mysqlConfig.ReadTimeout = 5 * time.Second
		mysqlConfig.WriteTimeout = 5 * time.Second
		mysqlConfig.Params = map[string]string{"charset": "utf8mb4,utf8"}
		switch strings.ToLower(strings.TrimSpace(config.SSLMode)) {
		case "", "disable", "disabled", "false":
		case "skip-verify", "skip_verify":
			mysqlConfig.TLSConfig = "skip-verify"
		default:
			mysqlConfig.TLSConfig = "true"
		}
		return sql.Open("mysql", mysqlConfig.FormatDSN())
	case "postgres":
		return sql.Open("pgx", postgresConnectionString(config))
	default:
		return nil, errors.New("不支持的数据源驱动：" + config.Driver)
	}
}

func postgresConnectionString(config networkDatabaseConfig) string {
	u := url.URL{
		Scheme: "postgres",
		User:   url.UserPassword(strings.TrimSpace(config.Username), config.Password),
		Host:   net.JoinHostPort(strings.TrimSpace(config.Host), strings.TrimSpace(config.Port)),
		Path:   strings.TrimSpace(config.Database),
	}
	query := u.Query()
	sslMode := strings.ToLower(strings.TrimSpace(config.SSLMode))
	if sslMode == "" {
		sslMode = "disable"
	}
	query.Set("sslmode", sslMode)
	query.Set("connect_timeout", "3")
	u.RawQuery = query.Encode()
	return u.String()
}

func inspectNetworkDatabaseSchema(ctx context.Context, db *sql.DB, driver string) (databaseSchemaInspection, error) {
	switch normalizeDatabaseDriver(driver) {
	case "mysql":
		return inspectMySQLSchema(ctx, db)
	case "postgres":
		return inspectPostgresSchema(ctx, db)
	default:
		return databaseSchemaInspection{}, errors.New("不支持结构扫描：" + driver)
	}
}

func inspectMySQLSchema(ctx context.Context, db *sql.DB) (databaseSchemaInspection, error) {
	tableRows, err := db.QueryContext(ctx, `SELECT TABLE_NAME, TABLE_TYPE, COALESCE(TABLE_COMMENT, '')
		FROM information_schema.TABLES
		WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE IN ('BASE TABLE', 'VIEW')
		ORDER BY TABLE_NAME
		LIMIT 80`)
	if err != nil {
		return databaseSchemaInspection{}, err
	}
	defer tableRows.Close()

	tables := make([]inspectedDatabaseTable, 0)
	tableIndex := map[string]int{}
	for tableRows.Next() {
		var table inspectedDatabaseTable
		if err := tableRows.Scan(&table.Name, &table.Kind, &table.Comment); err != nil {
			return databaseSchemaInspection{}, err
		}
		table.Name = strings.TrimSpace(table.Name)
		if table.Name == "" {
			continue
		}
		table.Comment = normalizeSchemaComment(table.Comment)
		tableIndex[table.Name] = len(tables)
		tables = append(tables, table)
	}
	if err := tableRows.Err(); err != nil {
		return databaseSchemaInspection{}, err
	}

	columnRows, err := db.QueryContext(ctx, `SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_KEY, COALESCE(COLUMN_COMMENT, '')
		FROM information_schema.COLUMNS
		WHERE TABLE_SCHEMA = DATABASE()
		ORDER BY TABLE_NAME, ORDINAL_POSITION
		LIMIT 1200`)
	if err != nil {
		return databaseSchemaInspection{}, err
	}
	defer columnRows.Close()
	for columnRows.Next() {
		var tableName, name, columnType, nullable, key, comment string
		if err := columnRows.Scan(&tableName, &name, &columnType, &nullable, &key, &comment); err != nil {
			return databaseSchemaInspection{}, err
		}
		index, ok := tableIndex[tableName]
		if !ok {
			continue
		}
		tables[index].Columns = append(tables[index].Columns, inspectedDatabaseColumn{
			Name:     strings.TrimSpace(name),
			Type:     strings.TrimSpace(columnType),
			Nullable: strings.EqualFold(strings.TrimSpace(nullable), "YES"),
			Key:      mysqlColumnKeyLabel(key),
			Comment:  normalizeSchemaComment(comment),
		})
	}
	if err := columnRows.Err(); err != nil {
		return databaseSchemaInspection{}, err
	}

	indexRows, err := db.QueryContext(ctx, `SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',')
		FROM information_schema.STATISTICS
		WHERE TABLE_SCHEMA = DATABASE()
		GROUP BY TABLE_NAME, INDEX_NAME, NON_UNIQUE
		ORDER BY TABLE_NAME, INDEX_NAME
		LIMIT 240`)
	if err == nil {
		defer indexRows.Close()
		for indexRows.Next() {
			var tableName, indexName, columns string
			var nonUnique int
			if err := indexRows.Scan(&tableName, &indexName, &nonUnique, &columns); err != nil {
				return databaseSchemaInspection{}, err
			}
			index, ok := tableIndex[tableName]
			if !ok || len(tables[index].Indexes) >= 4 {
				continue
			}
			label := indexName + "(" + columns + ")"
			if nonUnique == 0 && !strings.EqualFold(indexName, "PRIMARY") {
				label += " UNIQUE"
			}
			tables[index].Indexes = append(tables[index].Indexes, label)
		}
		if err := indexRows.Err(); err != nil {
			return databaseSchemaInspection{}, err
		}
	}

	return summarizeDatabaseSchema(tables), nil
}

func inspectPostgresSchema(ctx context.Context, db *sql.DB) (databaseSchemaInspection, error) {
	tableRows, err := db.QueryContext(ctx, `SELECT n.nspname, c.relname,
			CASE c.relkind WHEN 'r' THEN 'BASE TABLE' WHEN 'p' THEN 'PARTITIONED TABLE' WHEN 'v' THEN 'VIEW' WHEN 'm' THEN 'MATERIALIZED VIEW' ELSE c.relkind::text END,
			COALESCE(obj_description(c.oid, 'pg_class'), '')
		FROM pg_class c
		JOIN pg_namespace n ON n.oid = c.relnamespace
		WHERE n.nspname NOT IN ('pg_catalog', 'information_schema') AND n.nspname NOT LIKE 'pg_toast%'
			AND c.relkind IN ('r', 'p', 'v', 'm')
		ORDER BY n.nspname, c.relname
		LIMIT 80`)
	if err != nil {
		return databaseSchemaInspection{}, err
	}
	defer tableRows.Close()

	tables := make([]inspectedDatabaseTable, 0)
	tableIndex := map[string]int{}
	for tableRows.Next() {
		var schemaName, tableName string
		var table inspectedDatabaseTable
		if err := tableRows.Scan(&schemaName, &tableName, &table.Kind, &table.Comment); err != nil {
			return databaseSchemaInspection{}, err
		}
		table.Name = qualifiedPostgresName(schemaName, tableName)
		table.Comment = normalizeSchemaComment(table.Comment)
		tableIndex[table.Name] = len(tables)
		tables = append(tables, table)
	}
	if err := tableRows.Err(); err != nil {
		return databaseSchemaInspection{}, err
	}

	pkColumns, err := postgresPrimaryKeyColumns(ctx, db)
	if err != nil {
		return databaseSchemaInspection{}, err
	}
	columnRows, err := db.QueryContext(ctx, `SELECT n.nspname, c.relname, a.attname, format_type(a.atttypid, a.atttypmod),
			CASE WHEN a.attnotnull THEN 'NO' ELSE 'YES' END,
			COALESCE(col_description(c.oid, a.attnum), '')
		FROM pg_attribute a
		JOIN pg_class c ON c.oid = a.attrelid
		JOIN pg_namespace n ON n.oid = c.relnamespace
		WHERE a.attnum > 0 AND NOT a.attisdropped
			AND n.nspname NOT IN ('pg_catalog', 'information_schema') AND n.nspname NOT LIKE 'pg_toast%'
			AND c.relkind IN ('r', 'p', 'v', 'm')
		ORDER BY n.nspname, c.relname, a.attnum
		LIMIT 1200`)
	if err != nil {
		return databaseSchemaInspection{}, err
	}
	defer columnRows.Close()
	for columnRows.Next() {
		var schemaName, tableName, name, columnType, nullable, comment string
		if err := columnRows.Scan(&schemaName, &tableName, &name, &columnType, &nullable, &comment); err != nil {
			return databaseSchemaInspection{}, err
		}
		qualified := qualifiedPostgresName(schemaName, tableName)
		index, ok := tableIndex[qualified]
		if !ok {
			continue
		}
		key := ""
		if pkColumns[qualified+"."+name] {
			key = "PK"
		}
		tables[index].Columns = append(tables[index].Columns, inspectedDatabaseColumn{
			Name:     strings.TrimSpace(name),
			Type:     strings.TrimSpace(columnType),
			Nullable: strings.EqualFold(nullable, "YES"),
			Key:      key,
			Comment:  normalizeSchemaComment(comment),
		})
	}
	if err := columnRows.Err(); err != nil {
		return databaseSchemaInspection{}, err
	}

	indexRows, err := db.QueryContext(ctx, `SELECT schemaname, tablename, indexname
		FROM pg_indexes
		WHERE schemaname NOT IN ('pg_catalog', 'information_schema') AND schemaname NOT LIKE 'pg_toast%'
		ORDER BY schemaname, tablename, indexname
		LIMIT 240`)
	if err == nil {
		defer indexRows.Close()
		for indexRows.Next() {
			var schemaName, tableName, indexName string
			if err := indexRows.Scan(&schemaName, &tableName, &indexName); err != nil {
				return databaseSchemaInspection{}, err
			}
			qualified := qualifiedPostgresName(schemaName, tableName)
			index, ok := tableIndex[qualified]
			if !ok || len(tables[index].Indexes) >= 4 {
				continue
			}
			tables[index].Indexes = append(tables[index].Indexes, strings.TrimSpace(indexName))
		}
		if err := indexRows.Err(); err != nil {
			return databaseSchemaInspection{}, err
		}
	}

	return summarizeDatabaseSchema(tables), nil
}

func postgresPrimaryKeyColumns(ctx context.Context, db *sql.DB) (map[string]bool, error) {
	rows, err := db.QueryContext(ctx, `SELECT n.nspname, c.relname, a.attname
		FROM pg_index i
		JOIN pg_class c ON c.oid = i.indrelid
		JOIN pg_namespace n ON n.oid = c.relnamespace
		JOIN unnest(i.indkey) WITH ORDINALITY AS keys(attnum, ord) ON true
		JOIN pg_attribute a ON a.attrelid = c.oid AND a.attnum = keys.attnum
		WHERE i.indisprimary
			AND n.nspname NOT IN ('pg_catalog', 'information_schema') AND n.nspname NOT LIKE 'pg_toast%'`)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	keys := map[string]bool{}
	for rows.Next() {
		var schemaName, tableName, columnName string
		if err := rows.Scan(&schemaName, &tableName, &columnName); err != nil {
			return nil, err
		}
		keys[qualifiedPostgresName(schemaName, tableName)+"."+columnName] = true
	}
	return keys, rows.Err()
}

func qualifiedPostgresName(schemaName string, tableName string) string {
	schemaName = strings.TrimSpace(schemaName)
	tableName = strings.TrimSpace(tableName)
	if schemaName == "" || schemaName == "public" {
		return tableName
	}
	return schemaName + "." + tableName
}

func mysqlColumnKeyLabel(key string) string {
	switch strings.ToUpper(strings.TrimSpace(key)) {
	case "PRI":
		return "PK"
	case "UNI":
		return "UNIQUE"
	case "MUL":
		return "INDEX"
	default:
		return ""
	}
}

func normalizeSchemaComment(comment string) string {
	comment = strings.TrimSpace(comment)
	comment = strings.ReplaceAll(comment, "\r", " ")
	comment = strings.ReplaceAll(comment, "\n", " ")
	return strings.Join(strings.Fields(comment), " ")
}

func summarizeDatabaseSchema(tables []inspectedDatabaseTable) databaseSchemaInspection {
	totalColumns := 0
	tablePreview := make([]string, 0, minInt(len(tables), 8))
	columnPreview := make([]string, 0, minInt(len(tables), 8))
	indexPreview := make([]string, 0, minInt(len(tables), 5))
	for _, table := range tables {
		totalColumns += len(table.Columns)
		if len(tablePreview) < 8 {
			tablePreview = append(tablePreview, schemaObjectLabel(table.Name, table.Comment))
		}
		if len(columnPreview) < 8 {
			columns := make([]string, 0, minInt(len(table.Columns), 6))
			for _, column := range table.Columns {
				if len(columns) >= 6 {
					break
				}
				columns = append(columns, schemaColumnLabel(column))
			}
			if len(columns) > 0 {
				columnPreview = append(columnPreview, schemaObjectLabel(table.Name, table.Comment)+": "+strings.Join(columns, "、"))
			}
		}
		if len(indexPreview) < 5 && len(table.Indexes) > 0 {
			indexes := table.Indexes
			if len(indexes) > 3 {
				indexes = indexes[:3]
			}
			indexPreview = append(indexPreview, table.Name+": "+strings.Join(indexes, "、"))
		}
	}

	checks := []string{
		"表数量：" + strconv.Itoa(len(tables)),
		"字段数量：" + strconv.Itoa(totalColumns),
	}
	if len(tablePreview) > 0 {
		checks = append(checks, "表清单："+strings.Join(tablePreview, "、"))
	}
	if len(columnPreview) > 0 {
		checks = append(checks, "字段结构："+strings.Join(columnPreview, "；"))
	}
	if len(indexPreview) > 0 {
		checks = append(checks, "索引概览："+strings.Join(indexPreview, "；"))
	}
	summary := "未发现业务表"
	if len(tables) > 0 {
		summary = "发现 " + strconv.Itoa(len(tables)) + " 张表、" + strconv.Itoa(totalColumns) + " 个字段"
	}
	return databaseSchemaInspection{
		Summary:     summary,
		Checks:      checks,
		TableCount:  len(tables),
		ColumnCount: totalColumns,
		Tables:      tables,
	}
}

func newSchemaSnapshotRecord(source dataSourceConfig, result databaseCheckResult, capturedAt time.Time) (adminSchemaSnapshotRecord, bool) {
	source = source.normalized()
	inspection := result.Inspection
	if strings.TrimSpace(source.Name) == "" || strings.TrimSpace(inspection.Summary) == "" {
		return adminSchemaSnapshotRecord{}, false
	}
	checksJSON, err := json.Marshal(inspection.Checks)
	if err != nil {
		return adminSchemaSnapshotRecord{}, false
	}
	schemaJSON, err := json.Marshal(inspection.Tables)
	if err != nil {
		return adminSchemaSnapshotRecord{}, false
	}
	hashBytes := sha256.Sum256([]byte(source.Name + "\n" + source.Driver + "\n" + string(schemaJSON)))
	return adminSchemaSnapshotRecord{
		DataSourceName: source.Name,
		Driver:         source.DisplayName(),
		Target:         source.DisplayTarget(),
		Summary:        inspection.Summary,
		TableCount:     inspection.TableCount,
		ColumnCount:    inspection.ColumnCount,
		SchemaHash:     hex.EncodeToString(hashBytes[:]),
		ChecksJSON:     string(checksJSON),
		SchemaJSON:     string(schemaJSON),
		CapturedAt:     capturedAt,
	}, true
}

func schemaObjectLabel(name string, comment string) string {
	name = strings.TrimSpace(name)
	comment = normalizeSchemaComment(comment)
	if comment == "" {
		return name
	}
	return name + "(" + comment + ")"
}

func schemaColumnLabel(column inspectedDatabaseColumn) string {
	parts := []string{strings.TrimSpace(column.Name)}
	if strings.TrimSpace(column.Type) != "" {
		parts = append(parts, strings.TrimSpace(column.Type))
	}
	if strings.TrimSpace(column.Key) != "" {
		parts = append(parts, strings.TrimSpace(column.Key))
	}
	if !column.Nullable {
		parts = append(parts, "NOT NULL")
	}
	if strings.TrimSpace(column.Comment) != "" {
		parts = append(parts, "注释:"+normalizeSchemaComment(column.Comment))
	}
	return strings.Join(parts, " ")
}

func (ds dataSourceConfig) DisplayName() string {
	switch ds.normalized().Driver {
	case "mysql":
		return "MySQL"
	case "postgres":
		return "PostgreSQL"
	case "sqlite":
		return "SQLite"
	default:
		return ds.Driver
	}
}

func (ds dataSourceConfig) DisplayTarget() string {
	ds = ds.normalized()
	if ds.Driver == "sqlite" {
		return ds.FilePath
	}
	return net.JoinHostPort(ds.Host, ds.Port) + "/" + ds.Database
}

func dataSourceStatusText(status string) string {
	switch strings.ToLower(strings.TrimSpace(status)) {
	case "available":
		return "可用"
	case "unavailable":
		return "不可用"
	default:
		return "待测试"
	}
}

func dataSourceStatusClass(status string) string {
	switch strings.ToLower(strings.TrimSpace(status)) {
	case "available":
		return "is-ready"
	case "unavailable":
		return "is-warning"
	default:
		return "is-progress"
	}
}

func buildAdminDataSources(state installState, entry string) []adminDataSource {
	rows := []adminDataSource{
		{Initial: "M", Name: "metadata", DriverKey: "metadata", Driver: state.Database.DisplayName(), Target: state.Database.DisplayTarget(), Role: "系统元数据", StatusKey: "available", Status: "可用", StatusClass: "is-ready", Message: "初始化时配置的元数据连接", LastChecked: formatAdminTime(state.InstalledAt), Schema: "承载安装状态、系统配置和后续基础表"},
		{Initial: "L", Name: "legacy-hyperf", DriverKey: "reference", Driver: "参考归档", Target: "legacy-hyperf/", Role: "旧系统比对", StatusKey: "reference", Status: "只读参考", StatusClass: "is-progress", Message: "旧 Hyperf 代码已归档，用于迁移对照", Schema: "控制器、模型、服务、视图与插件资源"},
	}
	for _, source := range normalizeDataSources(state.DataSources) {
		source = source.normalized()
		statusKey := strings.ToLower(strings.TrimSpace(source.Status))
		if statusKey == "" {
			statusKey = "pending"
		}
		rows = append(rows, adminDataSource{
			Initial:      adminUserInitial(source.Name, source.Name),
			Name:         source.Name,
			DriverKey:    source.Driver,
			Driver:       source.DisplayName(),
			Target:       source.DisplayTarget(),
			Role:         source.Role,
			StatusKey:    statusKey,
			Status:       dataSourceStatusText(source.Status),
			StatusClass:  dataSourceStatusClass(source.Status),
			Message:      source.LastMessage,
			LastChecked:  formatAdminTime(source.LastCheckedAt),
			Schema:       source.SchemaSummary,
			Editable:     true,
			TestAction:   entry + "/data-sources/test",
			DeleteAction: entry + "/data-sources/delete",
		})
	}
	return rows
}

func dataSourceNoticeFromQuery(query url.Values) (string, string) {
	if message := strings.TrimSpace(query.Get("error")); message != "" {
		return message, "alert-danger"
	}
	switch query.Get("saved") {
	case "datasource":
		return "数据源配置已保存，请点击测试连接完成可用性检查。", "alert-success"
	case "test":
		return "数据源基础连接检查通过。", "alert-success"
	case "delete":
		return "数据源已删除。", "alert-success"
	default:
		return "", ""
	}
}

func settingsNoticeFromQuery(query url.Values) (string, string) {
	if message := strings.TrimSpace(query.Get("error")); message != "" {
		return message, "alert-danger"
	}
	switch query.Get("saved") {
	case "system":
		return "基础信息已保存。", "alert-success"
	case "storage":
		return "存储设置已保存，目录状态已重新检查。", "alert-success"
	case "ai":
		return "AI 设置已保存并通过连接检查。", "alert-success"
	case "security":
		return "安全设置已保存。", "alert-success"
	case "notifications":
		return "通知设置已保存。", "alert-success"
	case "notification_test":
		return "测试通知已发送。", "alert-success"
	case "task_worker":
		return "后台自动队列设置已保存。", "alert-success"
	default:
		return "", ""
	}
}

func fileNoticeFromQuery(query url.Values) (string, string) {
	if message := strings.TrimSpace(query.Get("error")); message != "" {
		return message, "alert-danger"
	}
	switch query.Get("saved") {
	case "upload":
		count := strings.TrimSpace(query.Get("count"))
		if count == "" {
			count = "1"
		}
		return "已上传 " + count + " 个文件。", "alert-success"
	case "delete":
		return "文件已删除。", "alert-success"
	default:
		return "", ""
	}
}

func taskNoticeFromQuery(query url.Values) (string, string) {
	if message := strings.TrimSpace(query.Get("error")); message != "" {
		return message, "alert-danger"
	}
	switch query.Get("saved") {
	case "settings":
		return "后台任务自动执行设置已保存。", "alert-success"
	case "enqueue":
		return "后台任务已加入队列。", "alert-success"
	case "run":
		return "后台任务已执行完成。", "alert-success"
	case "run_all":
		count := strings.TrimSpace(query.Get("count"))
		failed := strings.TrimSpace(query.Get("failed"))
		if count == "" {
			count = "0"
		}
		if failed == "" {
			failed = "0"
		}
		return "批量执行完成 " + count + " 个任务，失败/待重试 " + failed + " 个。", "alert-success"
	case "retry":
		return "后台任务已重新进入待执行队列。", "alert-success"
	case "cancel":
		return "后台任务已取消。", "alert-success"
	default:
		return "", ""
	}
}

func (s *adminServer) recordAuditEvent(r *http.Request, state installState, input auditEventInput) {
	if !state.Initialized {
		return
	}
	actor := strings.TrimSpace(input.Actor)
	if actor == "" {
		actor = state.AdminUser
	}
	if actor == "" {
		actor = "system"
	}
	category := strings.ToLower(strings.TrimSpace(input.Category))
	if category == "" {
		category = "operation"
	}
	statusCode := input.StatusCode
	if statusCode == 0 {
		statusCode = http.StatusOK
	}
	record := adminAuditRecord{
		Timestamp:  time.Now().UTC(),
		Category:   category,
		Action:     strings.TrimSpace(input.Action),
		Actor:      actor,
		Detail:     truncateAuditText(input.Detail, 220),
		Method:     r.Method,
		Path:       r.URL.Path,
		IP:         requestClientIP(r),
		UserAgent:  truncateAuditText(r.UserAgent(), 220),
		StatusCode: statusCode,
		DurationMS: input.Duration.Milliseconds(),
	}
	if record.Action == "" {
		record.Action = "后台操作"
	}
	if record.Detail == "" {
		record.Detail = "记录后台关键操作"
	}
	_ = s.store.AppendAuditRecord(record)
}

func (s *adminServer) listAuditEvents(limit int) []adminAuditEvent {
	events, err := s.store.ListAuditEvents(limit)
	if err != nil {
		return nil
	}
	return events
}

func (s *adminServer) listNotificationDeliveries(limit int) []adminNotificationDeliveryRecord {
	records, err := s.store.ListNotificationDeliveries(limit)
	if err != nil {
		return nil
	}
	return records
}

func (s *adminServer) listBackgroundTasks(limit int) []adminBackgroundTaskRecord {
	records, err := s.store.ListBackgroundTasks(limit)
	if err != nil {
		return nil
	}
	return records
}

func (s *adminServer) listBackgroundTaskLogs(limit int) []adminBackgroundTaskLogRecord {
	records, err := s.store.ListBackgroundTaskLogs(limit)
	if err != nil {
		return nil
	}
	return records
}

func (s *adminServer) listSchemaSnapshots(limit int) []adminSchemaSnapshotRecord {
	records, err := s.store.ListSchemaSnapshots(limit)
	if err != nil {
		return nil
	}
	return records
}

func (s *adminServer) listSettingChanges(limit int) []adminSettingChangeRecord {
	records, err := s.store.ListSettingChanges(limit)
	if err != nil {
		return nil
	}
	return records
}

func (s *adminServer) listAdminSessions(limit int) []adminSessionRecord {
	records, err := s.store.ListAdminSessions(limit)
	if err != nil {
		return nil
	}
	return records
}

func (s *adminServer) listAgentSessions(limit int) []agentSessionRecord {
	records, err := s.store.ListAgentSessions(limit)
	if err != nil {
		return nil
	}
	return records
}

func (s *adminServer) listAgentRuns(limit int) []agentRunRecord {
	records, err := s.store.ListAgentRuns(limit)
	if err != nil {
		return nil
	}
	return records
}

func (s *adminServer) listAgentTasks(limit int) []agentTaskRecord {
	records, err := s.store.ListAgentTasks(limit)
	if err != nil {
		return nil
	}
	return records
}

func (s *adminServer) listAgentTaskSteps(taskID string) []agentTaskStepRecord {
	records, err := s.store.ListAgentTaskSteps(taskID)
	if err != nil {
		return nil
	}
	return records
}

func (s *adminServer) listAgentToolResults(limit int) []agentToolResultRecord {
	records, err := s.store.ListAgentToolResults(limit)
	if err != nil {
		return nil
	}
	return records
}

func (s *adminServer) listAgentWeChatMessages(limit int) []agentWeChatMessageRecord {
	records, err := s.store.ListAgentWeChatMessages(limit)
	if err != nil {
		return nil
	}
	return records
}

func (s *adminServer) listAgentWeChatMessagesPage(channelKey string, limit int, offset int) []agentWeChatMessageRecord {
	records, err := s.store.ListAgentWeChatMessagesPage(channelKey, limit, offset)
	if err != nil {
		return nil
	}
	return records
}

func (s *adminServer) countAgentWeChatMessages(channelKey string) int {
	count, err := s.store.CountAgentWeChatMessages(channelKey)
	if err != nil {
		return 0
	}
	return count
}

func (r adminAuditRecord) toAdminAuditEvent() adminAuditEvent {
	status := "-"
	statusClass := "is-muted"
	if r.StatusCode > 0 {
		status = strconv.Itoa(r.StatusCode)
		if r.StatusCode >= 200 && r.StatusCode < 400 {
			statusClass = "is-ready"
		} else if r.StatusCode >= 400 && r.StatusCode < 500 {
			statusClass = "is-warning"
		} else {
			statusClass = "is-muted"
		}
	}
	duration := "-"
	if r.DurationMS > 0 {
		duration = strconv.FormatInt(r.DurationMS, 10) + " ms"
	}
	metaParts := make([]string, 0, 4)
	if strings.TrimSpace(r.Actor) != "" {
		metaParts = append(metaParts, r.Actor)
	}
	if strings.TrimSpace(r.Method) != "" || strings.TrimSpace(r.Path) != "" {
		metaParts = append(metaParts, strings.TrimSpace(r.Method+" "+r.Path))
	}
	if strings.TrimSpace(r.IP) != "" {
		metaParts = append(metaParts, r.IP)
	}
	if duration != "-" {
		metaParts = append(metaParts, duration)
	}
	return adminAuditEvent{
		Time:        formatAdminTime(r.Timestamp),
		Category:    r.Category,
		Action:      r.Action,
		Actor:       r.Actor,
		Detail:      r.Detail,
		Method:      r.Method,
		Path:        r.Path,
		IP:          r.IP,
		Meta:        strings.Join(metaParts, " · "),
		Status:      status,
		StatusClass: statusClass,
		Duration:    duration,
	}
}

func requestClientIP(r *http.Request) string {
	if forwarded := strings.TrimSpace(r.Header.Get("X-Forwarded-For")); forwarded != "" {
		if first := strings.TrimSpace(strings.Split(forwarded, ",")[0]); first != "" {
			return first
		}
	}
	if realIP := strings.TrimSpace(r.Header.Get("X-Real-IP")); realIP != "" {
		return realIP
	}
	host, _, err := net.SplitHostPort(r.RemoteAddr)
	if err == nil && host != "" {
		return host
	}
	return strings.TrimSpace(r.RemoteAddr)
}

func truncateAuditText(value string, limit int) string {
	value = strings.TrimSpace(value)
	if limit <= 0 {
		return value
	}
	runes := []rune(value)
	if len(runes) <= limit {
		return value
	}
	return string(runes[:limit]) + "..."
}

func storageLocalRoot(storage storageConfig) (string, error) {
	storage = storage.normalized()
	if !storage.IsLocal() {
		return "", errors.New("当前阶段仅支持本地文件系统")
	}
	if filepath.IsAbs(storage.LocalPath) {
		return filepath.Clean(storage.LocalPath), nil
	}
	return filepath.Abs(storage.LocalPath)
}

func safeStoragePath(root string, relativePath string) (string, error) {
	rootAbs, err := filepath.Abs(root)
	if err != nil {
		return "", err
	}
	relativePath = filepath.Clean(strings.TrimSpace(relativePath))
	if relativePath == "." || relativePath == "" || filepath.IsAbs(relativePath) || strings.HasPrefix(relativePath, "..") {
		return "", errors.New("invalid storage path")
	}
	targetAbs, err := filepath.Abs(filepath.Join(rootAbs, relativePath))
	if err != nil {
		return "", err
	}
	if targetAbs != rootAbs && !strings.HasPrefix(targetAbs, rootAbs+string(filepath.Separator)) {
		return "", errors.New("invalid storage path")
	}
	return targetAbs, nil
}

func saveUploadedFile(root string, originalFilename string, open func() (multipart.File, error), maxBytes int64) error {
	source, err := open()
	if err != nil {
		return err
	}
	defer source.Close()

	relativePath, err := newUploadedRelativePath(originalFilename)
	if err != nil {
		return err
	}
	target, err := safeStoragePath(root, relativePath)
	if err != nil {
		return err
	}
	if err := os.MkdirAll(filepath.Dir(target), 0o755); err != nil {
		return err
	}
	destination, err := os.OpenFile(target, os.O_CREATE|os.O_WRONLY|os.O_EXCL, 0o644)
	if err != nil {
		return err
	}
	defer destination.Close()

	written, err := io.Copy(destination, io.LimitReader(source, maxBytes+1))
	if err != nil {
		_ = os.Remove(target)
		return err
	}
	if written > maxBytes {
		_ = os.Remove(target)
		return errors.New("文件超过大小限制")
	}
	return nil
}

func newUploadedRelativePath(originalFilename string) (string, error) {
	tokenBytes := make([]byte, 4)
	if _, err := rand.Read(tokenBytes); err != nil {
		return "", err
	}
	now := time.Now()
	return filepath.ToSlash(filepath.Join(
		"uploads",
		now.Format("2006"),
		now.Format("01"),
		now.Format("02"),
		strconv.FormatInt(now.UnixNano(), 10)+"-"+hex.EncodeToString(tokenBytes)+"-"+safeUploadFilename(originalFilename),
	)), nil
}

func safeUploadFilename(originalFilename string) string {
	name := filepath.Base(strings.TrimSpace(originalFilename))
	ext := strings.ToLower(filepath.Ext(name))
	base := strings.TrimSuffix(name, filepath.Ext(name))
	base = strings.Map(func(r rune) rune {
		switch {
		case r >= 'a' && r <= 'z':
			return r
		case r >= 'A' && r <= 'Z':
			return r
		case r >= '0' && r <= '9':
			return r
		case r == '-' || r == '_':
			return r
		case r >= 0x4e00 && r <= 0x9fff:
			return r
		default:
			return '-'
		}
	}, base)
	base = strings.Trim(base, "-_")
	if base == "" {
		base = "file"
	}
	if len([]rune(base)) > 48 {
		base = string([]rune(base)[:48])
	}
	return base + ext
}

func storageExtensionAllowed(storage storageConfig, filename string) bool {
	allowed := strings.Split(storage.normalized().AllowedExtensions, ",")
	ext := strings.ToLower(filepath.Ext(filename))
	if ext == "" {
		return false
	}
	for _, item := range allowed {
		if strings.TrimSpace(item) == ext {
			return true
		}
	}
	return false
}

func listAdminFiles(storage storageConfig, entry string, limit int) []adminFileRow {
	root, err := storageLocalRoot(storage)
	if err != nil {
		return nil
	}
	info, err := os.Stat(root)
	if errors.Is(err, os.ErrNotExist) || err != nil || !info.IsDir() {
		return nil
	}

	rows := make([]adminFileRow, 0)
	_ = filepath.WalkDir(root, func(path string, entryInfo os.DirEntry, walkErr error) error {
		if walkErr != nil || entryInfo.IsDir() {
			return nil
		}
		info, err := entryInfo.Info()
		if err != nil {
			return nil
		}
		relative, err := filepath.Rel(root, path)
		if err != nil {
			return nil
		}
		relative = filepath.ToSlash(relative)
		escaped := escapeStorageRelativePath(relative)
		rows = append(rows, adminFileRow{
			Initial:      adminUserInitial(filepath.Base(path), filepath.Base(path)),
			Name:         filepath.Base(path),
			Path:         relative,
			KindKey:      fileKindKey(path),
			Kind:         fileKind(path),
			SizeBytes:    info.Size(),
			Size:         formatAdminFileSize(info.Size()),
			Modified:     formatAdminTime(info.ModTime()),
			StatusKey:    "uploaded",
			Status:       "已上传",
			StatusClass:  "is-ready",
			PreviewURL:   entry + "/files/preview/" + escaped,
			DownloadURL:  entry + "/files/download/" + escaped,
			DeleteAction: entry + "/files/delete",
		})
		return nil
	})
	sort.SliceStable(rows, func(i, j int) bool {
		return rows[i].Modified > rows[j].Modified
	})
	if limit > 0 && len(rows) > limit {
		return rows[:limit]
	}
	return rows
}

func escapeStorageRelativePath(relativePath string) string {
	parts := strings.Split(filepath.ToSlash(relativePath), "/")
	for i, part := range parts {
		parts[i] = url.PathEscape(part)
	}
	return strings.Join(parts, "/")
}

func fileKind(path string) string {
	ext := strings.ToLower(filepath.Ext(path))
	contentType := mime.TypeByExtension(ext)
	switch {
	case strings.HasPrefix(contentType, "image/"):
		return "图片"
	case contentType == "application/pdf":
		return "PDF"
	case strings.Contains(contentType, "spreadsheet") || ext == ".csv" || ext == ".xlsx":
		return "表格"
	case strings.HasPrefix(contentType, "text/") || ext == ".json" || ext == ".md":
		return "文本"
	case ext != "":
		return strings.TrimPrefix(strings.ToUpper(ext), ".")
	default:
		return "文件"
	}
}

func fileKindKey(path string) string {
	ext := strings.ToLower(filepath.Ext(path))
	contentType := mime.TypeByExtension(ext)
	switch {
	case strings.HasPrefix(contentType, "image/"):
		return "image"
	case contentType == "application/pdf":
		return "pdf"
	case strings.Contains(contentType, "spreadsheet") || ext == ".csv" || ext == ".xlsx":
		return "spreadsheet"
	case strings.HasPrefix(contentType, "text/") || ext == ".json" || ext == ".md":
		return "text"
	default:
		return "other"
	}
}

func formatAdminFileSize(bytes int64) string {
	if bytes < 0 {
		bytes = 0
	}
	units := []string{"B", "KB", "MB", "GB", "TB"}
	value := float64(bytes)
	unit := 0
	for value >= 1024 && unit < len(units)-1 {
		value /= 1024
		unit++
	}
	if unit == 0 {
		return strconv.FormatInt(bytes, 10) + " B"
	}
	return fmt.Sprintf("%.1f %s", value, units[unit])
}

func (d databaseConfig) DisplayName() string {
	switch strings.ToLower(d.Driver) {
	case "sqlite":
		return "SQLite"
	case "mysql":
		return "MySQL"
	case "postgres", "postgresql":
		return "PostgreSQL"
	default:
		if d.Driver == "" {
			return "未配置"
		}
		return d.Driver
	}
}

func (d databaseConfig) DisplayTarget() string {
	switch strings.ToLower(d.Driver) {
	case "sqlite":
		if d.FilePath == "" {
			return "data/moyi-admin.db"
		}
		return d.FilePath
	case "mysql", "postgres", "postgresql":
		host := d.Host
		if host == "" {
			host = "127.0.0.1"
		}
		port := d.Port
		if port == "" {
			if strings.EqualFold(d.Driver, "mysql") {
				port = "3306"
			} else {
				port = "5432"
			}
		}
		return host + ":" + port + "/" + d.Database
	default:
		return ""
	}
}

func (d databaseConfig) IsSQLite() bool {
	return strings.EqualFold(d.Driver, "sqlite")
}

func (d databaseConfig) IsMySQL() bool {
	return strings.EqualFold(d.Driver, "mysql")
}

func (d databaseConfig) IsPostgres() bool {
	return strings.EqualFold(d.Driver, "postgres") || strings.EqualFold(d.Driver, "postgresql")
}

func (a aiConfig) IsDisabled() bool {
	return strings.EqualFold(a.Provider, "disabled") || strings.TrimSpace(a.Provider) == ""
}

func (a aiConfig) IsBailian() bool {
	return strings.EqualFold(a.Provider, "bailian")
}

func (a aiConfig) DisplayName() string {
	a = a.sanitized()
	switch a.Provider {
	case "bailian":
		return "阿里云百炼"
	case "disabled":
		return "暂未启用"
	default:
		return a.Provider
	}
}

func (a aiConfig) DisplayModel() string {
	a = a.sanitized()
	if a.Provider == "disabled" {
		return "后续后台配置"
	}
	if a.ChatModel == "" {
		return defaultAIChatModel
	}
	return a.ChatModel
}

func (a aiConfig) DisplayImageModel() string {
	a = a.sanitized()
	if a.Provider == "disabled" {
		return "后续后台配置"
	}
	if a.ImageModel == "" {
		return defaultAIImageModel
	}
	return a.ImageModel
}

func (a aiConfig) DisplayTarget() string {
	a = a.sanitized()
	if a.Provider == "disabled" {
		return "未连接 AI 服务"
	}
	return a.BaseURL
}

func (a aiConfig) maskedAPIKey() string {
	key := strings.TrimSpace(a.APIKey)
	if key == "" {
		return ""
	}
	runes := []rune(key)
	if len(runes) <= 8 {
		return "****"
	}
	return string(runes[:4]) + "****" + string(runes[len(runes)-4:])
}

func maskSecretValue(value string) string {
	value = strings.TrimSpace(value)
	if value == "" {
		return ""
	}
	runes := []rune(value)
	if len(runes) <= 8 {
		return "****"
	}
	return string(runes[:4]) + "****" + string(runes[len(runes)-4:])
}

func (a aiConfig) chatCompletionsURL() (string, error) {
	a = a.sanitized()
	parsed, err := url.Parse(a.BaseURL)
	if err != nil {
		return "", err
	}
	path := strings.TrimRight(parsed.Path, "/")
	if strings.HasSuffix(path, "/chat/completions") {
		parsed.Path = path
	} else {
		parsed.Path = path + "/chat/completions"
	}
	return parsed.String(), nil
}

func (a aiConfig) imageGenerationURL() (string, error) {
	a = a.sanitized()
	parsed, err := url.Parse(a.BaseURL)
	if err != nil {
		return "", err
	}
	path := strings.TrimRight(parsed.Path, "/")
	path = strings.TrimSuffix(path, "/chat/completions")
	path = strings.TrimSuffix(path, "/compatible-mode/v1")
	path = strings.TrimSuffix(path, "/api/v1")
	if path == "" {
		parsed.Path = "/api/v1/services/aigc/multimodal-generation/generation"
	} else {
		parsed.Path = strings.TrimRight(path, "/") + "/api/v1/services/aigc/multimodal-generation/generation"
	}
	parsed.RawQuery = ""
	parsed.Fragment = ""
	return parsed.String(), nil
}

func (s installState) credentialsMatch(username string, password string) bool {
	if !s.Initialized || username == "" || password == "" {
		return false
	}
	account, ok := findAdminAccount(s, username)
	access := adminRoleAccessForUsername(s, username)
	if !ok || account.Status == "disabled" || account.PasswordSalt == "" || account.PasswordHash == "" || !access.Valid {
		return false
	}
	usernameOK := subtle.ConstantTimeCompare([]byte(strings.ToLower(username)), []byte(strings.ToLower(account.Username))) == 1
	hash := derivePasswordHash(password, account.PasswordSalt)
	passwordOK := subtle.ConstantTimeCompare([]byte(hash), []byte(account.PasswordHash)) == 1
	return usernameOK && passwordOK
}

func (s installState) withAdminLogin(username string, loggedAt time.Time) (installState, bool) {
	if strings.EqualFold(username, s.AdminUser) {
		return s, false
	}
	access := s.Access.normalized(s)
	index := findAdminAccountIndex(access.Users, username)
	if index < 0 {
		return s, false
	}
	access.Users[index].LastLoginAt = loggedAt
	access.Users[index].UpdatedAt = loggedAt
	s.Access = access.withoutBootstrap(s)
	return s, true
}

type installStore struct {
	path   string
	mu     sync.Mutex
	memory installState
}

func newInstallStore(path string) *installStore {
	return &installStore{path: strings.TrimSpace(path)}
}

func (s *installStore) Load() (installState, error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	if s.path == "" {
		return s.memory, nil
	}
	return s.loadSQLiteLocked()
}

func (s *installStore) Save(state installState) error {
	s.mu.Lock()
	defer s.mu.Unlock()

	if s.path == "" {
		s.memory = state
		return nil
	}
	return s.saveSQLiteLocked(state)
}

func validateInstallForm(siteName string, username string, password string, confirmation string, db databaseConfig, ai aiConfig) error {
	if siteName == "" {
		return errors.New("请输入站点名称")
	}
	if username == "" {
		return errors.New("请输入管理员账号")
	}
	if len(username) < 3 {
		return errors.New("管理员账号长度至少 3 位")
	}
	for _, r := range username {
		if (r >= 'a' && r <= 'z') || (r >= 'A' && r <= 'Z') || (r >= '0' && r <= '9') || r == '_' {
			continue
		}
		return errors.New("管理员账号只能包含字母、数字和下划线")
	}
	if len(password) < 6 {
		return errors.New("管理员密码长度至少 6 位")
	}
	if password != confirmation {
		return errors.New("两次输入的密码不一致")
	}
	if err := validateDatabaseConfig(db); err != nil {
		return err
	}
	if err := validateAIConfig(ai); err != nil {
		return err
	}
	return nil
}

func validateDatabaseConfig(db databaseConfig) error {
	driver := strings.ToLower(strings.TrimSpace(db.Driver))
	switch driver {
	case "sqlite":
		if strings.TrimSpace(db.FilePath) == "" {
			return errors.New("请输入 SQLite 数据库文件路径")
		}
	case "mysql", "postgres", "postgresql":
		if strings.TrimSpace(db.Host) == "" {
			return errors.New("请输入数据库主机")
		}
		if strings.TrimSpace(db.Port) == "" {
			return errors.New("请输入数据库端口")
		}
		port, err := strconv.Atoi(strings.TrimSpace(db.Port))
		if err != nil || port <= 0 || port > 65535 {
			return errors.New("数据库端口不正确")
		}
		if strings.TrimSpace(db.Database) == "" {
			return errors.New("请输入数据库名称")
		}
		if strings.TrimSpace(db.Username) == "" {
			return errors.New("请输入数据库用户名")
		}
	default:
		return errors.New("请选择支持的数据库类型")
	}
	return nil
}

func validateAIConfig(ai aiConfig) error {
	ai = ai.sanitized()
	switch ai.Provider {
	case "disabled":
		return nil
	case "bailian":
		if strings.TrimSpace(ai.APIKey) == "" {
			return errors.New("请输入阿里云百炼 API Key，或选择暂不启用 AI")
		}
		if len([]rune(strings.TrimSpace(ai.APIKey))) < 8 {
			return errors.New("阿里云百炼 API Key 长度不正确")
		}
		if strings.TrimSpace(ai.BaseURL) == "" {
			return errors.New("请输入阿里云百炼 Base URL")
		}
		parsed, err := url.Parse(ai.BaseURL)
		if err != nil || parsed.Scheme == "" || parsed.Host == "" {
			return errors.New("阿里云百炼 Base URL 不正确")
		}
		if parsed.Scheme != "https" && parsed.Scheme != "http" {
			return errors.New("阿里云百炼 Base URL 只支持 http 或 https")
		}
		if strings.TrimSpace(ai.ChatModel) == "" {
			return errors.New("请输入阿里云百炼对话模型")
		}
		if strings.TrimSpace(ai.ImageModel) == "" {
			return errors.New("请输入阿里云百炼图片模型")
		}
	default:
		return errors.New("请选择支持的 AI 服务商")
	}
	return nil
}

type databaseCheckResult struct {
	OK         bool                     `json:"ok"`
	Message    string                   `json:"message"`
	Checks     []string                 `json:"checks"`
	Inspection databaseSchemaInspection `json:"-"`
}

type aiCheckResult struct {
	OK      bool     `json:"ok"`
	Message string   `json:"message"`
	Checks  []string `json:"checks"`
}

func checkDatabaseConfig(db databaseConfig) databaseCheckResult {
	db = db.sanitized()
	if err := validateDatabaseConfig(db); err != nil {
		return databaseCheckResult{
			OK:      false,
			Message: err.Error(),
			Checks:  []string{"配置校验未通过"},
		}
	}

	switch strings.ToLower(db.Driver) {
	case "sqlite":
		if info, err := os.Stat(db.FilePath); err == nil {
			result := checkSQLiteDataSource(db.FilePath)
			if result.OK && !info.IsDir() {
				result.Message += " 初始化会创建或复用 metadata 数据表。"
				result.Checks = append(result.Checks, "用途：系统元数据")
			}
			return result
		} else if !errors.Is(err, os.ErrNotExist) {
			return databaseCheckResult{
				OK:      false,
				Message: "SQLite 路径不可访问：" + err.Error(),
				Checks:  []string{"路径：" + db.FilePath},
			}
		}
		dir := filepath.Dir(db.FilePath)
		if err := os.MkdirAll(dir, 0o755); err != nil {
			return databaseCheckResult{
				OK:      false,
				Message: "无法创建 SQLite 目录：" + err.Error(),
				Checks:  []string{"目录：" + dir},
			}
		}
		tmp, err := os.CreateTemp(dir, ".moyi-db-check-*")
		if err != nil {
			return databaseCheckResult{
				OK:      false,
				Message: "SQLite 目录不可写：" + err.Error(),
				Checks:  []string{"目录：" + dir},
			}
		}
		tmpName := tmp.Name()
		_ = tmp.Close()
		_ = os.Remove(tmpName)
		return databaseCheckResult{
			OK:      true,
			Message: "SQLite 路径检查通过，目录可写。下一步会创建 metadata 数据表。",
			Checks: []string{
				"数据库文件：" + db.FilePath,
				"目录可写：" + dir,
			},
		}
	case "mysql", "postgres", "postgresql":
		return checkNetworkDatabase(networkDatabaseConfig{
			Driver:        db.Driver,
			DisplayName:   db.DisplayName(),
			Host:          db.Host,
			Port:          db.Port,
			Database:      db.Database,
			Username:      db.Username,
			Password:      db.Password,
			SSLMode:       db.SSLMode,
			Purpose:       "系统元数据",
			RequireSchema: false,
		})
	default:
		return databaseCheckResult{
			OK:      false,
			Message: "请选择支持的数据库类型",
			Checks:  []string{"支持：SQLite、MySQL、PostgreSQL"},
		}
	}
}

func checkAIConfig(ctx context.Context, ai aiConfig) aiCheckResult {
	ai = ai.sanitized()
	if err := validateAIConfig(ai); err != nil {
		return aiCheckResult{
			OK:      false,
			Message: err.Error(),
			Checks:  []string{"AI 配置校验未通过"},
		}
	}

	if ai.IsDisabled() {
		return aiCheckResult{
			OK:      true,
			Message: "AI 暂不启用。初始化完成后可在后台继续配置百炼。",
			Checks:  []string{"服务商：暂不启用"},
		}
	}

	endpoint, err := ai.chatCompletionsURL()
	if err != nil {
		return aiCheckResult{
			OK:      false,
			Message: "百炼接口地址不正确：" + err.Error(),
			Checks:  []string{"Base URL：" + ai.BaseURL},
		}
	}

	testCtx, cancel := context.WithTimeout(ctx, defaultAITestTimeout)
	defer cancel()
	body, _ := json.Marshal(map[string]any{
		"model": ai.ChatModel,
		"messages": []map[string]string{
			{"role": "system", "content": "你是配置检查助手。"},
			{"role": "user", "content": "请只回复 ok"},
		},
		"temperature": 0,
		"max_tokens":  8,
	})
	req, err := http.NewRequestWithContext(testCtx, http.MethodPost, endpoint, bytes.NewReader(body))
	if err != nil {
		return aiCheckResult{
			OK:      false,
			Message: "创建百炼检查请求失败：" + err.Error(),
			Checks:  []string{"接口：" + endpoint},
		}
	}
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("Authorization", "Bearer "+ai.APIKey)

	resp, err := aiCheckHTTPClient.Do(req)
	if err != nil {
		return aiCheckResult{
			OK:      false,
			Message: "连接百炼兼容接口失败：" + err.Error(),
			Checks: []string{
				"接口：" + endpoint,
				"模型：" + ai.ChatModel,
			},
		}
	}
	defer resp.Body.Close()
	respBody, _ := io.ReadAll(io.LimitReader(resp.Body, 4096))

	if resp.StatusCode == http.StatusUnauthorized || resp.StatusCode == http.StatusForbidden {
		return aiCheckResult{
			OK:      false,
			Message: "百炼 API Key 无效或没有模型权限",
			Checks: []string{
				"接口：" + endpoint,
				"模型：" + ai.ChatModel,
				"Key：" + ai.maskedAPIKey(),
			},
		}
	}
	if resp.StatusCode < 200 || resp.StatusCode >= 300 {
		return aiCheckResult{
			OK:      false,
			Message: fmt.Sprintf("百炼接口返回 %d：%s", resp.StatusCode, strings.TrimSpace(string(respBody))),
			Checks: []string{
				"接口：" + endpoint,
				"模型：" + ai.ChatModel,
			},
		}
	}

	var parsed struct {
		Choices []struct {
			Message struct {
				Content string `json:"content"`
			} `json:"message"`
		} `json:"choices"`
	}
	if err := json.Unmarshal(respBody, &parsed); err != nil {
		return aiCheckResult{
			OK:      false,
			Message: "百炼接口响应无法解析：" + err.Error(),
			Checks:  []string{"接口：" + endpoint},
		}
	}
	if len(parsed.Choices) == 0 {
		return aiCheckResult{
			OK:      false,
			Message: "百炼接口响应没有返回 choices",
			Checks:  []string{"接口：" + endpoint},
		}
	}
	return aiCheckResult{
		OK:      true,
		Message: "阿里云百炼配置验证成功。",
		Checks: []string{
			"接口：" + endpoint,
			"模型：" + ai.ChatModel,
			"Key：" + ai.maskedAPIKey(),
		},
	}
}

func hashPassword(password string) (string, string, error) {
	saltBytes := make([]byte, 16)
	if _, err := rand.Read(saltBytes); err != nil {
		return "", "", err
	}
	salt := hex.EncodeToString(saltBytes)
	return salt, derivePasswordHash(password, salt), nil
}

func derivePasswordHash(password string, salt string) string {
	sum := sha256.Sum256([]byte(salt + ":" + password))
	current := sum[:]
	for i := 0; i < 20000; i++ {
		next := sha256.Sum256(append(current, []byte(":"+salt+":"+password)...))
		current = next[:]
	}
	return hex.EncodeToString(current)
}

var adminLoginTemplate = template.Must(template.New("admin-login").Parse(`<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Moyi Admin 登录</title>
  <link rel="stylesheet" href="/assets/css/admin-foundation.css?v=20260519-sidebar-fixed1">
  <style>
    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      min-height: 100vh;
      display: grid;
      place-items: center;
      background: #f5f7f8;
      color: #162029;
      font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      letter-spacing: 0;
    }

    .login {
      width: min(440px, calc(100vw - 36px));
      background: #ffffff;
      border: 1px solid #d9e0e5;
      border-radius: 8px;
      box-shadow: 0 14px 40px rgba(21, 31, 43, 0.08);
      padding: 28px;
    }

    h1 {
      margin: 0;
      font-size: 24px;
    }

    p {
      margin: 10px 0 24px;
      color: #63717e;
      line-height: 1.6;
      font-size: 14px;
    }

    .alert {
      margin-bottom: 18px;
      border: 1px solid #f0c3c3;
      border-radius: 7px;
      background: #fff6f6;
      color: #9f1d1d;
      padding: 11px 12px;
      font-size: 13px;
      line-height: 1.5;
    }

    .success {
      margin-bottom: 18px;
      border: 1px solid #b8dfc8;
      border-radius: 7px;
      background: #f2fbf5;
      color: #18794e;
      padding: 11px 12px;
      font-size: 13px;
      line-height: 1.5;
    }

    label {
      display: block;
      margin-bottom: 7px;
      font-size: 13px;
      font-weight: 700;
    }

    input,
    select {
      width: 100%;
      height: 42px;
      border: 1px solid #d9e0e5;
      border-radius: 7px;
      padding: 0 12px;
      font: inherit;
      margin-bottom: 16px;
      outline: none;
    }

    input:focus,
    select:focus {
      border-color: #176b87;
      box-shadow: 0 0 0 3px rgba(23, 107, 135, 0.12);
    }

    .section {
      margin-top: 8px;
      padding-top: 18px;
      border-top: 1px solid #d9e0e5;
    }

    .section-title {
      margin: 0 0 14px;
      color: #162029;
      font-size: 15px;
      font-weight: 800;
    }

    .grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 0 12px;
    }

    .full {
      grid-column: 1 / -1;
    }

    button {
      width: 100%;
      height: 42px;
      border: 0;
      border-radius: 7px;
      background: #176b87;
      color: #ffffff;
      font: inherit;
      font-weight: 750;
      cursor: pointer;
    }

    .note {
      margin-top: 16px;
      color: #63717e;
      font-size: 12px;
      line-height: 1.6;
      background: #f0f3f5;
      border-radius: 7px;
      padding: 11px 12px;
    }
  </style>
</head>
<body class="admin-auth-page">
  <div class="auth-container">
    <main class="auth-card">
      <div class="auth-body">
        <div class="auth-header">
          <div class="auth-logo">M</div>
          <h1 class="auth-title">后台登录</h1>
          <p class="auth-subtitle">使用初始化时创建的管理员账号登录</p>
        </div>
        {{if .Success}}<div class="alert alert-success">系统初始化完成，现在可以登录后台。</div>{{end}}
        {{if .Error}}<div class="alert alert-danger">{{.Error}}</div>{{end}}
        <form id="installForm" method="post" action="{{.Action}}">
          <div class="form-group">
            <label class="form-label" for="username">账号</label>
            <input class="form-control" id="username" name="username" autocomplete="username" value="{{.Username}}" placeholder="admin">
          </div>
          <div class="form-group">
            <label class="form-label" for="password">密码</label>
            <input class="form-control" id="password" name="password" type="password" autocomplete="current-password" value="{{.Password}}" placeholder="请输入密码">
          </div>
          {{if .DebugPrefill}}<div class="alert alert-success">调试模式已自动填充账号密码，方便本地开发验证。</div>{{end}}
          <button class="btn btn-primary" type="submit">登录</button>
        </form>
        <div class="note">如果还没有初始化系统，请先访问隐藏入口下的安装地址完成初始化。</div>
      </div>
    </main>
  </div>
</body>
</html>`))

var adminInstallTemplate = template.Must(template.New("admin-install").Parse(`<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Moyi Admin 初始化</title>
  <link rel="stylesheet" href="/assets/css/admin-foundation.css?v=20260519-sidebar-fixed1">
  <style>
    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      min-height: 100vh;
      display: grid;
      place-items: center;
      background: #f5f7f8;
      color: #162029;
      font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      letter-spacing: 0;
    }

    .install {
      width: min(520px, calc(100vw - 36px));
      background: #ffffff;
      border: 1px solid #d9e0e5;
      border-radius: 8px;
      box-shadow: 0 14px 40px rgba(21, 31, 43, 0.08);
      padding: 28px;
    }

    h1 {
      margin: 0;
      font-size: 24px;
    }

    p {
      margin: 10px 0 24px;
      color: #63717e;
      line-height: 1.6;
      font-size: 14px;
    }

    .alert {
      margin-bottom: 18px;
      border: 1px solid #f0c3c3;
      border-radius: 7px;
      background: #fff6f6;
      color: #9f1d1d;
      padding: 11px 12px;
      font-size: 13px;
      line-height: 1.5;
    }

    label {
      display: block;
      margin-bottom: 7px;
      font-size: 13px;
      font-weight: 700;
    }

    input {
      width: 100%;
      height: 42px;
      border: 1px solid #d9e0e5;
      border-radius: 7px;
      padding: 0 12px;
      font: inherit;
      margin-bottom: 16px;
      outline: none;
    }

    input:focus {
      border-color: #176b87;
      box-shadow: 0 0 0 3px rgba(23, 107, 135, 0.12);
    }

    button {
      width: 100%;
      height: 42px;
      border: 0;
      border-radius: 7px;
      background: #176b87;
      color: #ffffff;
      font: inherit;
      font-weight: 750;
      cursor: pointer;
    }

    .note {
      margin-top: 16px;
      color: #63717e;
      font-size: 12px;
      line-height: 1.6;
      background: #f0f3f5;
      border-radius: 7px;
      padding: 11px 12px;
    }
  </style>
</head>
<body class="admin-install-page">
  <div class="install-container">
    <main class="install-card">
      <div class="install-header">
        <h1>系统初始化</h1>
        <p>欢迎使用 MoYi Admin，请填写以下信息完成初始化</p>
      </div>
      <div class="install-body">
        {{if .Error}}<div class="alert alert-danger">{{.Error}}</div>{{end}}
        <form id="installForm" method="post" action="{{.Action}}">
          <div class="form-section">
            <div class="section-title">站点信息</div>
            <div class="form-group">
              <label for="site_name">站点名称 <span class="required">*</span></label>
              <input class="form-control" id="site_name" name="site_name" value="{{.SiteName}}" placeholder="Moyi Admin">
            </div>
          </div>

          <div class="form-section">
            <div class="section-title">元数据数据库</div>
            <div class="form-row">
              <div class="form-group form-group-full">
                <label for="db_driver">数据库类型 <span class="required">*</span></label>
                <select class="form-select" id="db_driver" name="db_driver" data-role="db-driver">
                  <option value="mysql" {{if .Database.IsMySQL}}selected{{end}}>MySQL（推荐，兼容旧系统）</option>
                  <option value="postgres" {{if .Database.IsPostgres}}selected{{end}}>PostgreSQL</option>
                  <option value="sqlite" {{if .Database.IsSQLite}}selected{{end}}>SQLite（仅本地开发）</option>
                </select>
                <small class="form-text" data-role="db-help"></small>
              </div>
              <div class="form-group form-group-full" data-db-block="sqlite" {{if not .Database.IsSQLite}}hidden{{end}}>
                <label for="db_file_path">SQLite 文件路径</label>
                <input class="form-control" id="db_file_path" name="db_file_path" value="{{.Database.FilePath}}" placeholder="data/moyi-admin.db">
                <small class="form-text">只建议本地开发使用。生产环境请使用 MySQL 或 PostgreSQL。</small>
              </div>
              <div class="form-group" data-db-block="server" {{if .Database.IsSQLite}}hidden{{end}}>
                <label for="db_host">数据库主机</label>
                <input class="form-control" id="db_host" name="db_host" value="{{.Database.Host}}" placeholder="127.0.0.1">
              </div>
              <div class="form-group" data-db-block="server" {{if .Database.IsSQLite}}hidden{{end}}>
                <label for="db_port">数据库端口</label>
                <input class="form-control" id="db_port" name="db_port" value="{{.Database.Port}}" placeholder="3306">
              </div>
              <div class="form-group form-group-full" data-db-block="server" {{if .Database.IsSQLite}}hidden{{end}}>
                <label for="db_name">数据库名称</label>
                <input class="form-control" id="db_name" name="db_name" value="{{.Database.Database}}" placeholder="moyi_admin">
              </div>
              <div class="form-group" data-db-block="server" {{if .Database.IsSQLite}}hidden{{end}}>
                <label for="db_username">数据库用户名</label>
                <input class="form-control" id="db_username" name="db_username" value="{{.Database.Username}}" placeholder="root">
              </div>
              <div class="form-group" data-db-block="server" {{if .Database.IsSQLite}}hidden{{end}}>
                <label for="db_password">数据库密码</label>
                <input class="form-control" id="db_password" name="db_password" type="password" value="{{.Database.Password}}" placeholder="可为空">
              </div>
              <div class="form-group form-group-full" data-db-block="server" {{if .Database.IsSQLite}}hidden{{end}}>
                <label for="db_ssl_mode">SSL 模式</label>
                <input class="form-control" id="db_ssl_mode" name="db_ssl_mode" value="{{.Database.SSLMode}}" placeholder="disable">
              </div>
              <div class="form-group form-group-full">
                <button class="btn btn-secondary" type="button" data-role="db-check">检查数据库</button>
                <div class="db-check-result" data-role="db-check-result" hidden></div>
              </div>
            </div>
          </div>

          <div class="form-section">
            <div class="section-title">AI 智能体配置</div>
            <div class="form-row">
              <div class="form-group form-group-full">
                <label for="ai_provider">AI 服务商 <span class="required">*</span></label>
                <select class="form-select" id="ai_provider" name="ai_provider" data-role="ai-provider">
                  <option value="bailian" {{if .AI.IsBailian}}selected{{end}}>阿里云百炼（DashScope / OpenAI 兼容）</option>
                  <option value="disabled" {{if .AI.IsDisabled}}selected{{end}}>暂不启用，后续后台配置</option>
                </select>
                <small class="form-text" data-role="ai-help"></small>
              </div>
              <div class="form-group form-group-full" data-ai-block="bailian" {{if not .AI.IsBailian}}hidden{{end}}>
                <label for="ai_api_key">百炼 API Key <span class="required">*</span></label>
                <input class="form-control" id="ai_api_key" name="ai_api_key" type="password" value="{{.AI.APIKey}}" autocomplete="off" placeholder="sk-...">
                <small class="form-text">参考 gochat 的百炼配置，Key 会用于后续 AI Agent 查询、解释和导出任务。</small>
              </div>
              <div class="form-group form-group-full" data-ai-block="bailian" {{if not .AI.IsBailian}}hidden{{end}}>
                <label for="ai_base_url">OpenAI 兼容 Base URL</label>
                <input class="form-control" id="ai_base_url" name="ai_base_url" value="{{.AI.BaseURL}}" placeholder="https://dashscope.aliyuncs.com/compatible-mode/v1">
              </div>
              <div class="form-group form-group-full" data-ai-block="bailian" {{if not .AI.IsBailian}}hidden{{end}}>
                <label for="ai_chat_model">默认对话模型</label>
                <input class="form-control" id="ai_chat_model" name="ai_chat_model" list="ai_chat_models" value="{{.AI.ChatModel}}" placeholder="qwen-plus">
                <datalist id="ai_chat_models">
                  <option value="qwen-plus"></option>
                  <option value="qwen-turbo"></option>
                  <option value="qwen-max"></option>
                  <option value="qwen3.5-plus"></option>
                </datalist>
              </div>
              <div class="form-group form-group-full" data-ai-block="bailian" {{if not .AI.IsBailian}}hidden{{end}}>
                <label for="ai_image_model">默认图片模型</label>
                <input class="form-control" id="ai_image_model" name="ai_image_model" list="ai_image_models" value="{{.AI.ImageModel}}" placeholder="qwen-image-2.0-pro">
                <datalist id="ai_image_models">
                  <option value="qwen-image-2.0-pro"></option>
                  <option value="wan2.7-image"></option>
                  <option value="wan2.7-image-pro"></option>
                </datalist>
                <small class="form-text">用于文生图、封面图和海报生成，后续 AI 智能体会直接调用这条能力。</small>
              </div>
              <div class="form-group form-group-full">
                <button class="btn btn-secondary" type="button" data-role="ai-check">检查 AI 配置</button>
                <div class="ai-check-result" data-role="ai-check-result" hidden></div>
              </div>
            </div>
          </div>

          <div class="form-section">
            <div class="section-title">超级管理员</div>
            <div class="form-row">
              <div class="form-group form-group-full">
                <label for="username">超级管理员账号 <span class="required">*</span></label>
                <input class="form-control" id="username" name="username" autocomplete="username" value="{{.Username}}" placeholder="admin">
              </div>
              <div class="form-group">
                <label for="password">超级管理员密码 <span class="required">*</span></label>
                <input class="form-control" id="password" name="password" type="password" autocomplete="new-password" placeholder="至少 6 位">
              </div>
              <div class="form-group">
                <label for="password_confirmation">确认密码 <span class="required">*</span></label>
                <input class="form-control" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" placeholder="再次输入密码">
              </div>
            </div>
          </div>

          <button class="btn btn-primary" type="submit">完成初始化</button>
        </form>
		<div class="note">后台入口会在初始化成功时随机生成，并写入本地初始化状态。请保存初始化后的登录地址，首页初始化后会恢复项目进展页。</div>
      </div>
    </main>
  </div>
  <script src="/assets/js/install-wizard.js?v=20260514-ai1"></script>
</body>
</html>`))

var adminInstalledTemplate = template.Must(template.New("admin-installed").Parse(`<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Moyi Admin 已初始化</title>
  <style>
    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      min-height: 100vh;
      display: grid;
      place-items: center;
      background: #f5f7f8;
      color: #162029;
      font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      letter-spacing: 0;
    }

    main {
      width: min(460px, calc(100vw - 36px));
      background: #ffffff;
      border: 1px solid #d9e0e5;
      border-radius: 8px;
      box-shadow: 0 14px 40px rgba(21, 31, 43, 0.08);
      padding: 28px;
    }

    h1 {
      margin: 0;
      font-size: 24px;
    }

    p {
      color: #63717e;
      line-height: 1.7;
      font-size: 14px;
    }

    a {
      height: 40px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border-radius: 7px;
      background: #176b87;
      color: #ffffff;
      font-weight: 750;
      text-decoration: none;
      padding: 0 14px;
      font-size: 14px;
    }

    @media (prefers-color-scheme: dark) {
      :root {
        color-scheme: dark;
      }

      body {
        background: #101716;
        color: #e7eeee;
      }

      main {
        background: #172120;
        border-color: #2d3d3c;
        box-shadow: 0 18px 48px rgba(0, 0, 0, 0.3);
      }

      p {
        color: #a7b5b3;
      }

      a {
        background: #2c838b;
      }
    }
  </style>
</head>
<body>
  <main>
    <h1>系统已初始化</h1>
    <p>{{.SiteName}} 已完成初始化，超级管理员账号为 {{.AdminUser}}。为避免重复初始化，安装入口已锁定。</p>
    <p>元数据数据库：{{.Database}}，{{.DatabaseDSN}}</p>
    <p>AI 配置：{{.AIProvider}}，默认模型 {{.AIModel}}</p>
    <a href="{{.LoginPath}}">进入后台登录</a>
  </main>
</body>
</html>`))

var adminWorkspaceTemplate = template.Must(template.New("admin-workspace").Parse(`<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{.Title}} - Moyi Admin</title>
  <link rel="stylesheet" href="/assets/css/admin-foundation.css?v=20260519-sidebar-fixed1">
</head>
<body class="admin-shell">
  <div class="admin-layout">
    <aside class="admin-sidebar">
      <a class="admin-brand" href="{{.BasePath}}/workspace" aria-label="Moyi Admin 工作台">
        <span class="admin-brand-mark">M</span>
        <span>
          <strong>Moyi Admin</strong>
          <small>{{.AdminTagline}}</small>
        </span>
      </a>
      <nav class="admin-nav" aria-label="后台导航">
        {{range .NavGroups}}
          <section class="admin-nav-group {{if .Active}}is-open{{end}}">
            <div class="admin-nav-group-label">{{.Label}}</div>
            <div class="admin-nav-children">
              {{range .Items}}
                <a class="{{if .Active}}active{{end}}" href="{{.Href}}">{{.Label}}</a>
              {{end}}
            </div>
          </section>
        {{end}}
      </nav>
      <div class="admin-sidebar-status">
        <div class="admin-sidebar-status-head">
          <div class="admin-sidebar-status-copy">
            <div class="admin-sidebar-label">当前会话</div>
            <strong>{{.SiteName}}</strong>
          </div>
          <span class="admin-status-pill is-ready">已初始化</span>
        </div>
        <div class="admin-sidebar-status-meta">
          <div class="admin-sidebar-status-row">
            <small>账号</small>
            <span>{{.Username}}</span>
          </div>
          <div class="admin-sidebar-status-row">
            <small>数据库</small>
            <span>{{.Database}}</span>
          </div>
        </div>
        <div class="admin-sidebar-status-actions">
          <form method="post" action="{{.LogoutAction}}">
            <button class="admin-sidebar-logout" type="submit">退出登录</button>
          </form>
        </div>
      </div>
    </aside>

    <div class="admin-content">
      <main class="admin-page{{if or (eq .Active "ai") (eq .Active "ai-tasks") (eq .Active "ai-runs") (eq .Active "ai-capabilities")}} is-ai-page{{end}}">
        {{if not (or (eq .Active "ai") (eq .Active "ai-tasks") (eq .Active "ai-runs") (eq .Active "ai-capabilities"))}}
          <section class="admin-page-intro">
            <div class="admin-breadcrumb">后台 / {{.Title}}</div>
            <h1>{{.Title}}</h1>
            <p>{{.Subtitle}}</p>
          </section>
        {{end}}
        {{if eq .Active "dashboard"}}
          <section class="admin-metrics" aria-label="系统概览">
            {{range .Metrics}}
              <article class="admin-metric-card">
                <span class="metric-label">{{.Label}}</span>
                <strong>{{.Value}}</strong>
                <small>{{.Detail}}</small>
                <i class="admin-status-dot {{.Status}}"></i>
              </article>
            {{end}}
          </section>

          <section class="admin-grid">
            <div class="admin-panel admin-panel-wide">
              <div class="admin-panel-head">
                <h2>迁移执行台</h2>
                <span class="admin-panel-meta">Foundation</span>
              </div>
              <div class="admin-table">
                <div class="admin-table-row admin-table-head">
                  <span>任务</span><span>责任域</span><span>状态</span>
                </div>
                {{range .Tasks}}
                  <div class="admin-table-row">
                    <span>{{.Name}}</span><span>{{.Owner}}</span><span class="admin-badge {{.StatusClass}}">{{.Status}}</span>
                  </div>
	                {{end}}
	              </div>
	            </div>
            <div class="admin-panel">
              <div class="admin-panel-head">
                <h2>基础信息</h2>
                <span class="admin-panel-meta">{{.InstalledAt}}</span>
              </div>
              <dl class="admin-kv">
                <div><dt>管理员</dt><dd>{{.Username}}</dd></div>
                <div><dt>后台入口</dt><dd class="mono">{{.AdminEntry}}</dd></div>
                <div><dt>元数据</dt><dd>{{.Database}} · {{.DatabaseDSN}}</dd></div>
                <div><dt>AI 服务</dt><dd>{{.AIProvider}} · {{.AIModel}}</dd></div>
              </dl>
            </div>

            <div class="admin-panel admin-panel-wide">
              <div class="admin-panel-head">
                <h2>快捷入口</h2>
                <span class="admin-panel-meta">Manage</span>
              </div>
              <div class="admin-command-grid">
                <a href="{{.BasePath}}/foundation">基础服务盘点</a>
                <a href="{{.BasePath}}/data-sources">数据源管理</a>
                <a href="{{.BasePath}}/extensions">能力扩展</a>
                <a href="{{.BasePath}}/ai">AI 智能体配置</a>
                <a href="{{.BasePath}}/wechat-agent">微信 Agent 通道</a>
                <a href="{{.BasePath}}/users">管理员账号</a>
                <a href="{{.BasePath}}/files">文件管理</a>
                <a href="{{.BasePath}}/tasks">后台任务</a>
              </div>
            </div>
          </section>
        {{else if eq .Active "foundation"}}
          <section class="admin-panel admin-panel-wide">
            <div class="admin-panel-head">
              <h2>基础服务迁移盘点</h2>
              <span class="admin-panel-meta">Legacy Compare</span>
            </div>
            <div class="admin-table foundation-table">
              <div class="admin-table-row admin-table-head">
                <span>服务</span><span>旧系统参考</span><span>Go 端现状</span><span>状态</span><span>下一步</span>
              </div>
              {{range .FoundationServices}}
                <div class="admin-table-row">
                  <span><strong>{{.Name}}</strong></span>
                  <span>{{.Legacy}}</span>
                  <span>{{.Current}}</span>
                  <span class="admin-badge {{.StatusClass}}">{{.Status}}</span>
                  <span>{{.Next}}</span>
                </div>
              {{end}}
            </div>
          </section>

          <section class="admin-grid foundation-overview-grid">
            <div class="admin-panel">
              <div class="admin-panel-head">
                <h2>近期已落地</h2>
                <span class="admin-panel-meta">Recent</span>
              </div>
              <dl class="admin-kv">
                <div><dt>访问控制</dt><dd>管理员、用户组、会话和菜单权限都已经落到统一后台规范里</dd></div>
                <div><dt>数据源与文件</dt><dd>数据源登记、上传文件、详情面板和只读数据边界都已经接入</dd></div>
                <div><dt>任务与审计</dt><dd>后台任务、通知事件、审计日志和 AI 只读上下文已经形成闭环</dd></div>
              </dl>
            </div>
            <div class="admin-panel">
              <div class="admin-panel-head">
                <h2>下一批基础服务</h2>
                <span class="admin-panel-meta">Next</span>
              </div>
              <dl class="admin-kv">
                <div><dt>扩展系统</dt><dd>继续补插件配置、启停控制、版本迁移和发布链路</dd></div>
                <div><dt>真实业务资源</dt><dd>把真实业务表、导出能力和多表只读查询接进资源模型</dd></div>
                <div><dt>基础能力增强</dt><dd>补对象存储、Schema 快照对比、审计详情和细粒度权限守卫</dd></div>
              </dl>
            </div>
          </section>
        {{else if eq .Active "extensions"}}
          <section class="admin-metrics" aria-label="能力扩展概览">
            {{range .ExtensionMetrics}}
              <article class="admin-metric-card">
                <span class="metric-label">{{.Label}}</span>
                <strong>{{.Value}}</strong>
                <small>{{.Detail}}</small>
                <i class="admin-status-dot {{.Status}}"></i>
              </article>
            {{end}}
          </section>

          <section class="admin-grid">
            <div class="admin-panel admin-panel-wide">
              <div class="admin-panel-head">
                <h2>插件扩展包</h2>
                <div class="admin-panel-actions">
                  <span class="admin-panel-meta">Extension Registry</span>
                  <a class="admin-panel-link" href="{{.BasePath}}/extensions/export">导出清单</a>
                </div>
              </div>
              <div class="admin-table plugin-extension-table">
                <div class="admin-table-row admin-table-head">
                  <span>插件</span><span>类型</span><span>资源</span><span>工具</span><span>状态</span><span>说明</span>
                </div>
                {{range .PluginExtensions}}
                  <div class="admin-table-row">
                    <span><strong class="mono">{{.Key}}</strong><small>{{.Name}} · {{.Version}}</small></span>
                    <span>{{.Kind}}</span>
                    <span>{{.Resources}}</span>
                    <span>{{.Tools}}</span>
                    <span class="admin-badge {{.StatusClass}}">{{.Status}}</span>
                    <span>{{.Description}}</span>
                  </div>
                {{end}}
              </div>
            </div>

            <div class="admin-panel admin-panel-wide">
              <div class="admin-panel-head">
                <h2>资源模型</h2>
                <span class="admin-panel-meta">Resource Models</span>
              </div>
              <div class="admin-table resource-model-table">
                <div class="admin-table-row admin-table-head">
                  <span>资源</span><span>来源</span><span>动作</span><span>字段</span><span>状态</span><span>说明</span>
                </div>
                {{range .ResourceModels}}
                  <div class="admin-table-row">
                    <span><strong class="mono">{{.Key}}</strong><small>{{.Name}} · {{.Plugin}}</small></span>
                    <span>{{.Source}}<small class="mono">{{.Table}}</small></span>
                    <span>{{.Actions}}<small>{{.ToolCount}} 个工具</small></span>
                    <span>{{.FieldCount}} 个<small>{{.FieldsSummary}}</small></span>
                    <span class="admin-badge {{.StatusClass}}">{{.Status}}</span>
                    <span>{{.Description}}<small>{{.ReadScope}}</small></span>
                  </div>
                {{end}}
              </div>
            </div>

            <div class="admin-panel admin-panel-wide">
              <div class="admin-panel-head">
                <h2>AI 工具生成</h2>
                <span class="admin-panel-meta">Generated Tools</span>
              </div>
              <div class="admin-table resource-tool-table">
                <div class="admin-table-row admin-table-head">
                  <span>工具</span><span>资源</span><span>动作</span><span>权限</span><span>边界</span><span>状态</span>
                </div>
                {{range .ResourceTools}}
                  <div class="admin-table-row">
                    <span><strong class="mono">{{.Name}}</strong><small>{{.Plugin}}</small></span>
                    <span>{{.Resource}}<small class="mono">{{.ResourceKey}}</small></span>
                    <span>{{.Action}}</span>
                    <span class="mono">{{.Permission}}</span>
                    <span>{{.Boundary}}</span>
                    <span class="admin-badge {{.StatusClass}}">{{.Status}}</span>
                  </div>
                {{end}}
              </div>
            </div>
          </section>
        {{else if eq .Active "data-sources"}}
          {{if .DataSourceNotice}}
            <div class="admin-alert {{.DataSourceNoticeClass}}">{{.DataSourceNotice}}</div>
          {{end}}
          <div class="data-source-page access-claw-page" data-data-sources>
            <section class="access-claw-metrics" aria-label="数据源概览">
              {{range .DataSourceMetrics}}
                <article class="access-claw-metric">
                  <span>{{.Label}}</span>
                  <strong>{{.Value}}</strong>
                  <small>{{.Detail}}</small>
                  <i class="admin-status-dot {{.Status}}"></i>
                </article>
              {{end}}
            </section>

            <form class="access-claw-filters" data-data-source-filters>
              <input class="form-control" type="search" placeholder="搜索数据源、目标地址、用途说明" autocomplete="off" data-data-source-search>
              <select class="form-select" data-data-source-driver>
                <option value="">全部类型</option>
                <option value="metadata">系统元数据</option>
                <option value="reference">迁移参考</option>
                <option value="mysql">MySQL</option>
                <option value="postgres">PostgreSQL</option>
                <option value="sqlite">SQLite</option>
              </select>
              <select class="form-select" data-data-source-status>
                <option value="">全部状态</option>
                <option value="available">可用</option>
                <option value="pending">待测试</option>
                <option value="reference">只读参考</option>
                <option value="unavailable">不可用</option>
              </select>
              <a class="admin-panel-link is-button" href="{{.BasePath}}/settings">存储与设置</a>
              <span class="access-claw-filter-spacer"></span>
              {{if .CanManageDataSources}}
                <button class="admin-panel-link is-button is-primary" type="button" data-data-source-create-toggle aria-expanded="false">新增数据源</button>
              {{end}}
              <a class="admin-panel-link is-button" href="{{.BasePath}}/data-sources">刷新列表</a>
            </form>
            {{if not .CanManageDataSources}}
              <p class="form-text admin-readonly-note">当前账号只有查看权限，可看连接状态和结构摘要，但不能新增、测试或删除数据源。</p>
            {{end}}

            <section class="access-claw-grid">
              <div class="admin-panel access-claw-table-card">
                <div class="access-claw-table-head">
                  <div>
                    <h2>数据源列表</h2>
                    <span>匹配 <b data-data-source-visible-count>{{len .DataSources}}</b> / 全部 {{len .DataSources}} 个数据源</span>
                  </div>
                  <span class="admin-panel-meta">Connections</span>
                </div>
                <div class="access-claw-table-wrap">
                  <table class="access-claw-table data-source-list-table">
                    <thead>
                      <tr>
                        <th>数据源</th>
                        <th>类型</th>
                        <th>目标地址</th>
                        <th>用途 / 结构</th>
                        <th>状态</th>
                      </tr>
                    </thead>
                    <tbody>
                      {{range .DataSources}}
                        <tr class="data-source-row" tabindex="0"
                          data-data-source-row
                          data-initial="{{.Initial}}"
                          data-name="{{.Name}}"
                          data-driver="{{.DriverKey}}"
                          data-driver-text="{{.Driver}}"
                          data-target="{{.Target}}"
                          data-role="{{.Role}}"
                          data-status="{{.StatusKey}}"
                          data-status-text="{{.Status}}"
                          data-status-class="{{.StatusClass}}"
                          data-message="{{.Message}}"
                          data-last-checked="{{.LastChecked}}"
                          data-schema="{{.Schema}}"
                          data-editable="{{if .Editable}}true{{else}}false{{end}}"
                          data-filter-text="{{.Name}} {{.Driver}} {{.Target}} {{.Role}} {{.Schema}} {{.Message}}">
                          <td>
                            <div class="access-claw-user">
                              <span class="access-user-avatar">{{.Initial}}</span>
                              <span>
                                <strong class="mono">{{.Name}}</strong>
                                <small>{{if .Message}}{{.Message}}{{else}}已登记数据连接{{end}}</small>
                              </span>
                            </div>
                          </td>
                          <td><strong>{{.Driver}}</strong><small>{{if .Editable}}自定义登记{{else}}系统内置{{end}}</small></td>
                          <td><strong class="mono">{{.Target}}</strong><small>{{.LastChecked}}</small></td>
                          <td><strong>{{.Role}}</strong><small>{{if .Schema}}{{.Schema}}{{else}}等待结构摘要{{end}}</small></td>
                          <td><span class="admin-badge {{.StatusClass}}">{{.Status}}</span></td>
                        </tr>
                      {{else}}
                        <tr>
                          <td class="access-user-empty" colspan="5">
                            <strong>暂无数据源</strong>
                            <span>先登记一个业务数据源，后续 AI 与权限配置才能继续接入。</span>
                          </td>
                        </tr>
                      {{end}}
                    </tbody>
                  </table>
                </div>
              </div>

              <aside class="access-claw-aside">
                <div class="admin-panel access-claw-side-panel" data-data-source-detail-panel>
                  <div class="admin-panel-head access-side-panel-head">
                    <div>
                      <h2>数据源详情</h2>
                      <span class="admin-panel-meta">Inspect</span>
                    </div>
                    <a class="admin-panel-link is-button" href="{{.BasePath}}/data-sources">刷新</a>
                  </div>
                  <div class="access-side-view">
                    <div class="access-detail-empty" data-data-source-empty>选择左侧数据源查看连接状态、结构摘要和操作入口</div>
                    <div class="access-detail-body" data-data-source-body hidden>
                      <div class="access-detail-head">
                        <span class="access-user-avatar" data-data-source-initial>D</span>
                        <div>
                          <h2 data-data-source-name>数据源</h2>
                          <small class="mono" data-data-source-target>-</small>
                        </div>
                      </div>
                      <dl class="access-detail-kv">
                        <div><dt>数据库类型</dt><dd data-data-source-driver-text>-</dd></div>
                        <div><dt>用途说明</dt><dd data-data-source-role>-</dd></div>
                        <div><dt>当前状态</dt><dd><span class="admin-badge is-muted" data-data-source-status-badge>-</span></dd></div>
                        <div><dt>最近探测</dt><dd data-data-source-last-checked>-</dd></div>
                        <div><dt>结构说明</dt><dd data-data-source-schema>-</dd></div>
                        <div><dt>附加说明</dt><dd data-data-source-message>-</dd></div>
                      </dl>
                      <div class="access-detail-actions">
                        {{if .CanManageDataSources}}
                          <form method="post" action="{{.BasePath}}/data-sources/test" data-data-source-test hidden>
                            <input type="hidden" name="name" value="" data-data-source-test-name>
                            <button class="admin-panel-link is-button" type="submit">测试连接</button>
                          </form>
                          <form method="post" action="{{.BasePath}}/data-sources/delete" data-data-source-delete hidden>
                            <input type="hidden" name="name" value="" data-data-source-delete-name>
                            <button class="admin-panel-link is-button" type="submit">删除数据源</button>
                          </form>
                        {{else}}
                          <span class="admin-badge is-muted">当前账号只有查看权限</span>
                        {{end}}
                        <span class="admin-badge is-muted" data-data-source-builtin hidden>内置数据源</span>
                      </div>
                    </div>
                  </div>
                </div>

                {{if .CanManageDataSources}}
                <div class="admin-panel access-claw-side-panel" data-data-source-create-panel hidden>
                  <div class="admin-panel-head access-side-panel-head">
                    <div>
                    <h2>登记数据源</h2>
                    <span class="admin-panel-meta">Connection</span>
                    </div>
                    <button class="admin-panel-link is-button" type="button" data-data-source-create-cancel>收起</button>
                  </div>
                  <div class="access-create-view">
                    <form class="admin-settings-form" method="post" action="{{.DataSourceSaveAction}}" data-ds-form>
                      <div class="admin-form-grid">
                        <label>
                          <span>连接名称</span>
                          <input class="form-control mono" name="name" placeholder="business_main" autocomplete="off">
                        </label>
                        <label>
                          <span>数据库类型</span>
                          <select class="form-select" name="driver" data-ds-driver>
                            <option value="mysql">MySQL</option>
                            <option value="postgres">PostgreSQL</option>
                            <option value="sqlite">SQLite</option>
                          </select>
                        </label>
                        <label data-ds-block="server">
                          <span>主机</span>
                          <input class="form-control mono" name="host" placeholder="127.0.0.1" autocomplete="off">
                        </label>
                        <label data-ds-block="server">
                          <span>端口</span>
                          <input class="form-control mono" name="port" placeholder="3306 / 5432" autocomplete="off">
                        </label>
                        <label data-ds-block="server">
                          <span>数据库名</span>
                          <input class="form-control mono" name="database" placeholder="moyi_business" autocomplete="off">
                        </label>
                        <label data-ds-block="server">
                          <span>用户名</span>
                          <input class="form-control mono" name="username" placeholder="root" autocomplete="off">
                        </label>
                        <label data-ds-block="server">
                          <span>密码</span>
                          <input class="form-control mono" name="password" type="password" placeholder="更新时留空保留原密码" autocomplete="new-password">
                        </label>
                        <label data-ds-block="server">
                          <span>SSL 模式</span>
                          <input class="form-control mono" name="ssl_mode" value="disable" autocomplete="off">
                        </label>
                        <label class="admin-form-wide" data-ds-block="sqlite" hidden>
                          <span>SQLite 文件路径</span>
                          <input class="form-control mono" name="file_path" placeholder="data/business.db" autocomplete="off">
                          <small class="form-text">选择 SQLite 时使用；MySQL 与 PostgreSQL 会忽略该项。</small>
                        </label>
                        <label class="admin-form-wide">
                          <span>用途说明</span>
                          <input class="form-control" name="role" placeholder="业务数据源 / 报表库 / 旧系统迁移库" autocomplete="off">
                        </label>
                      </div>
                      <div class="admin-form-actions">
                        <button class="admin-submit-button" type="submit" name="data_source_action" value="save">保存数据源</button>
                        <button class="admin-secondary-button" type="submit" name="data_source_action" value="save_test">保存并测试</button>
                      </div>
                    </form>
                  </div>
                </div>
                {{end}}

                <div class="admin-panel access-claw-side-panel">
                  <div class="admin-panel-head">
                    <h2>探测能力</h2>
                    <span class="admin-panel-meta">Probe</span>
                  </div>
                  <div class="access-create-view">
                    <dl class="admin-kv">
                      <div><dt>配置校验</dt><dd>名称、驱动、主机、端口、库名和 SQLite 路径</dd></div>
                      <div><dt>基础连接</dt><dd>MySQL 与 PostgreSQL 做可达性检查，SQLite 做文件与目录检查</dd></div>
                      <div><dt>结构扫描</dt><dd>SQLite 已读取真实表和字段；其他驱动后续会补齐注释与结构摘要</dd></div>
                    </dl>
                  </div>
                </div>
              </aside>
            </section>
          </div>
        {{else if or (eq .Active "ai") (eq .Active "ai-tasks") (eq .Active "ai-runs") (eq .Active "ai-capabilities")}}
          {{if .AgentNotice}}
            <div class="admin-alert {{.AgentNoticeClass}}">{{.AgentNotice}}</div>
          {{end}}
          <section class="admin-panel admin-panel-wide agent-module-shell{{if eq .Active "ai"}} is-chat-focus{{end}}">
            <div class="admin-panel-head agent-console-head">
              <div>
                {{if eq .Active "ai"}}
                  <h2>智能助理</h2>
                  <span class="admin-panel-meta">对话 / 任务 / 记录 / 说明</span>
                {{else}}
                  <h2>AI 智能体工作台</h2>
                  <span class="admin-panel-meta">对话 / 任务 / 记录 / 说明</span>
                {{end}}
              </div>
              {{if ne .Active "ai"}}
                <div class="admin-panel-actions agent-console-actions">
                  <span class="admin-badge {{.AIStatusClass}}">{{.AIStatus}}</span>
                  {{if .CanViewSettingsPage}}
                    <a class="admin-panel-link" href="{{.BasePath}}/settings">模型设置</a>
                  {{end}}
                  {{if .CanViewWeChatMessages}}
                    <a class="admin-panel-link" href="{{.BasePath}}/wechat-agent/messages">微信记录</a>
                  {{end}}
                  {{if .CanViewAuditPage}}
                    <a class="admin-panel-link" href="{{.BasePath}}/audit">审计日志</a>
                  {{end}}
                </div>
              {{end}}
            </div>
            <nav class="agent-module-nav" aria-label="AI 智能体页面">
              <a href="{{.BasePath}}/ai" {{if eq .Active "ai"}}class="is-active"{{end}}>对话</a>
              <a href="{{.BasePath}}/ai/tasks" {{if eq .Active "ai-tasks"}}class="is-active"{{end}}>任务</a>
              <a href="{{.BasePath}}/ai/runs" {{if eq .Active "ai-runs"}}class="is-active"{{end}}>记录</a>
              <a href="{{.BasePath}}/ai/capabilities" {{if eq .Active "ai-capabilities"}}class="is-active"{{end}}>说明</a>
            </nav>
            {{if ne .Active "ai"}}
              <div class="agent-module-hero">
                <div class="agent-intro-copy">
                  <span class="agent-intro-eyebrow">受控工作台</span>
                  <h3>{{if eq .Active "ai-tasks"}}把跨会话任务状态单独收纳{{else if eq .Active "ai-runs"}}把运行历史和工具结果单独沉淀{{else}}把模型入口、权限边界和常用能力单独说明{{end}}</h3>
                  <p>{{if eq .Active "ai-tasks"}}这里负责看当前任务、步骤推进和最近任务记录；发起新问题或继续追问请回到对话页。{{else if eq .Active "ai-runs"}}这里集中看最近运行、工具调用次数和文件产出；对话页不再承担历史中心的职责。{{else}}这里负责说明模型入口、数据边界、公开网页访问和常用任务模板；真正发起任务请回到对话页。{{end}}</p>
                </div>
                <div class="agent-context-chips">
                  <span class="agent-context-chip">{{.AgentScopeSummary}}</span>
                  <span class="agent-context-chip">{{if .CanRunAgentSQL}}只读查询 + 导出{{else if .CanReadAgentTables}}受控读取{{else if .CanReadWeb}}公开网页访问{{else}}方案模式{{end}}</span>
                  <span class="agent-context-chip">{{.AIProvider}} · {{.AIModel}}</span>
                  {{if .CanReadWeb}}
                    <span class="agent-context-chip">公开网页访问已启用</span>
                  {{end}}
                  {{if .CanGenerateAgentImages}}
                    <span class="agent-context-chip">图片创作已启用</span>
                  {{end}}
                </div>
              </div>
            {{end}}
          </section>

          {{if eq .Active "ai"}}
            <section class="admin-panel admin-panel-wide agent-chat-page-panel">
              <div class="agent-chat-surface-head">
                <div>
                  <span class="agent-intro-eyebrow">当前对话</span>
                  <h2>直接说你的问题</h2>
                  <p>支持连续追问；需要换一个新话题时，再开启新会话。</p>
                </div>
                <div class="agent-chat-session-controls">
                  <span class="agent-session-chip" id="agentSessionState">准备开始新对话</span>
                  <button type="button" class="agent-chat-secondary" id="agentNewSessionButton">新会话</button>
                </div>
              </div>
              {{if and (not .CanReadAgentTables) (not .CanReadWeb)}}
                <p class="form-text admin-readonly-note">当前账号更适合做说明、整理和方案类对话，暂时不能读取后台数据。</p>
              {{else if and .CanReadWeb (not .CanReadAgentTables)}}
                <p class="form-text admin-readonly-note">当前账号可以整理公开网页资料，但不能读取后台数据。</p>
              {{else if not .CanRunAgentSQL}}
                <p class="form-text admin-readonly-note">当前账号可以查看授权范围内的信息，但部分统计和导出任务可能会受限。</p>
              {{end}}
              {{if .CanGenerateAgentImages}}
                <p class="form-text agent-chat-capability-note">可以直接让我生成海报、封面图、插图或配图，图片会作为后台文件返回给你。</p>
              {{end}}
              <div class="agent-chat-layout">
                <div class="agent-chat-stage">
                  <div class="agent-conversation-shell">
                    <div class="agent-messages" id="agentMessages" aria-live="polite">
                      <article class="agent-message is-assistant">
                        <span>AI</span>
                        <p>你好，我可以帮你整理后台信息、解释配置、生成清单，或者继续跟进你上一轮的问题。</p>
                      </article>
                    </div>
                  </div>
                  <form class="agent-chat-form" id="agentChatForm" action="{{.BasePath}}/ai/chat" method="post">
                    <div class="agent-chat-form-main">
                      <div class="agent-chat-suggestion-strip" aria-label="对话建议">
                        <button type="button" data-agent-prompt="帮我看看当前后台还有哪些事情值得优先处理">看一下待办</button>
                        <button type="button" data-agent-prompt="帮我整理一份管理员账号清单">整理账号清单</button>
                        {{if .CanGenerateAgentImages}}
                          <button type="button" data-agent-prompt="帮我生成一张蓝绿色科技感的后台海报">生成一张海报</button>
                        {{end}}
                        <button type="button" data-agent-prompt="解释一下当前这个后台的整体结构">解释后台结构</button>
                      </div>
                      <label class="sr-only" for="agentMessageInput">输入智能体任务</label>
                      <textarea id="agentMessageInput" name="message" rows="4" maxlength="2000" placeholder="直接说你的问题，例如：帮我整理一份管理员账号清单"></textarea>
                      <div class="agent-chat-form-footer">
                        <span class="agent-chat-form-hint">支持连续追问；按 <code>Enter</code> 直接发送，按住辅助键再回车可换行。</span>
                        <button type="submit">发送</button>
                      </div>
                    </div>
                  </form>
                </div>
                <aside class="agent-wechat-guide-card">
                  {{if .AgentWeChatChannels}}
	                    {{$wechat := .AgentWeChatChannel}}
	                    {{if $wechat.IsBound}}
	                      <div class="agent-wechat-guide-head">
	                        <span class="agent-intro-eyebrow">微信 Agent</span>
	                        <h3>微信已接入</h3>
	                        <p>用户现在可以直接在微信里继续追问、收图片和收文件。</p>
	                      </div>
	                      <div class="agent-wechat-guide-status">
	                        <span class="admin-badge {{$wechat.StatusClass}}">{{$wechat.StatusText}}</span>
	                        <strong>{{$wechat.DisplayName}}</strong>
	                        <p>{{$wechat.LoginMessage}}</p>
	                      </div>
	                      <dl class="agent-wechat-guide-meta">
	                        <div><dt>管理员身份</dt><dd>{{$wechat.AdminUserLabel}}</dd></div>
	                        <div><dt>继承权限</dt><dd>{{$wechat.AdminRole}} · {{$wechat.AllowedSummary}}</dd></div>
	                        <div><dt>微信用户</dt><dd>{{$wechat.BoundUser}}</dd></div>
	                        <div><dt>最近回复</dt><dd>{{$wechat.LastOutboundAt}}</dd></div>
	                      </dl>
	                      <div class="agent-wechat-guide-inline-note">需要换绑或重新发二维码时，直接在这里重新生成。</div>
	                      <div class="agent-wechat-guide-actions">
	                        <a class="admin-panel-link is-button is-primary" href="{{.BasePath}}/wechat-agent">管理微信 Agent</a>
	                        {{if .CanManageWeChat}}
	                          <form class="agent-wechat-guide-inline-form" method="post" action="{{$wechat.Action}}">
	                            <input type="hidden" name="wechat_channel_key" value="{{$wechat.Key}}">
	                            <input type="hidden" name="wechat_channel_enabled" value="1">
	                            <input type="hidden" name="wechat_channel_name" value="{{$wechat.DisplayName}}">
	                            <input type="hidden" name="wechat_base_url" value="{{$wechat.BaseURL}}">
	                            <input type="hidden" name="wechat_bot_type" value="{{$wechat.BotType}}">
	                            <input type="hidden" name="wechat_admin_user" value="{{$wechat.AdminUser}}">
	                            <button class="admin-panel-link is-button" type="submit" name="wechat_channel_action" value="regenerate">重新生成二维码</button>
	                          </form>
	                        {{end}}
	                        {{if .CanViewWeChatMessages}}
	                          <a class="admin-panel-link" href="{{.BasePath}}/wechat-agent/messages">查看微信记录</a>
	                        {{end}}
	                      </div>
	                    {{else}}
	                      <div class="agent-wechat-guide-head">
	                        <span class="agent-intro-eyebrow">微信 Agent</span>
	                        <h3>微信未接入</h3>
	                        <p>生成二维码后，让用户扫码并发第一句话，就能把这条通道接起来。</p>
	                      </div>
	                      <div class="agent-wechat-guide-status">
	                        <span class="admin-badge {{$wechat.StatusClass}}">{{$wechat.StatusText}}</span>
	                        <strong>{{$wechat.DisplayName}}</strong>
	                        <p>{{$wechat.LoginMessage}}</p>
	                      </div>
	                      <dl class="agent-wechat-guide-meta">
	                        <div><dt>管理员身份</dt><dd>{{$wechat.AdminUserLabel}}</dd></div>
	                        <div><dt>继承权限</dt><dd>{{$wechat.AdminRole}} · {{$wechat.AllowedSummary}}</dd></div>
	                        <div><dt>二维码有效期</dt><dd>{{$wechat.BindExpiresAt}}</dd></div>
	                        <div><dt>最近回复</dt><dd>{{$wechat.LastOutboundAt}}</dd></div>
	                      </dl>
	                      {{if and .CanManageWeChat $wechat.HasQRCode}}
	                        <div class="agent-wechat-guide-qr">
	                          <img src="{{$wechat.QRImageURL}}" alt="微信 Agent 绑定二维码">
	                          <p>扫码后先发一句“你好”，确认这条通道已经连通。</p>
	                        </div>
	                      {{end}}
	                      <div class="agent-wechat-guide-inline-note">如果二维码过期或用户换设备，直接重新生成即可。</div>
	                      <div class="agent-wechat-guide-actions">
	                        {{if .CanManageWeChat}}
	                          <form class="agent-wechat-guide-inline-form" method="post" action="{{$wechat.Action}}">
	                            <input type="hidden" name="wechat_channel_key" value="{{$wechat.Key}}">
	                            <input type="hidden" name="wechat_channel_enabled" value="1">
	                            <input type="hidden" name="wechat_channel_name" value="{{$wechat.DisplayName}}">
	                            <input type="hidden" name="wechat_base_url" value="{{$wechat.BaseURL}}">
	                            <input type="hidden" name="wechat_bot_type" value="{{$wechat.BotType}}">
	                            <input type="hidden" name="wechat_admin_user" value="{{$wechat.AdminUser}}">
	                            <button class="admin-panel-link is-button is-primary" type="submit" name="wechat_channel_action" value="regenerate">{{if $wechat.HasQRCode}}重新生成二维码{{else}}生成绑定二维码{{end}}</button>
	                          </form>
	                        {{end}}
	                        <a class="admin-panel-link" href="{{.BasePath}}/wechat-agent">查看微信 Agent</a>
	                        {{if .CanViewWeChatMessages}}
	                          <a class="admin-panel-link" href="{{.BasePath}}/wechat-agent/messages">查看微信记录</a>
	                        {{end}}
	                      </div>
	                    {{end}}
                  {{else}}
                    <div class="agent-wechat-guide-empty">
                      <strong>还没有微信 Agent 通道</strong>
                      {{if .CanManageWeChat}}
                        <p>先新增一个微信通道，生成二维码后让用户扫码绑定，后面这个智能体就能直接在微信里继续对话。</p>
                        <div class="agent-wechat-guide-copy-block">
                          <p>建议先创建通道并生成二维码，然后把二维码和这句提示发给用户：“扫码进入微信助理，直接发一句你好，我会继续帮你处理后台任务。”</p>
                        </div>
                        <div class="agent-wechat-guide-actions">
                          <a class="admin-panel-link is-button is-primary" href="{{.BasePath}}/wechat-agent">新增并绑定微信 Agent</a>
                        </div>
                      {{else}}
                        <p>当前账号没有微信通道维护权限，需要管理员先完成微信 Agent 绑定。</p>
                      {{end}}
                    </div>
                  {{end}}
                </aside>
              </div>
            </section>
          {{else if eq .Active "ai-tasks"}}
            <section class="admin-panel admin-panel-wide agent-task-history-panel">
              <div class="admin-panel-head">
                <h2>当前任务</h2>
                <span class="admin-badge {{if .CurrentAgentTask}}{{.CurrentAgentTask.StatusClass}}{{else}}is-muted{{end}}">{{if .CurrentAgentTask}}{{.CurrentAgentTask.Status}}{{else}}暂无任务{{end}}</span>
              </div>
              <div class="agent-task-runtime-card">
                <strong>{{if .CurrentAgentTask}}{{.CurrentAgentTask.Title}}{{else}}等待新的后台任务{{end}}</strong>
                <p>{{if .CurrentAgentTask}}{{.CurrentAgentTask.Goal}}{{else}}去对话页发起任务后，这里会保留当前任务目标、边界和步骤状态。{{end}}</p>
                <div class="agent-task-runtime-meta">
                  <span class="mono">{{if and .CurrentAgentTask .CurrentAgentTask.Intent}}{{.CurrentAgentTask.Intent}}{{else}}-{{end}}</span>
                  <span>{{if and .CurrentAgentTask .CurrentAgentTask.PrimaryTable}}主表 {{.CurrentAgentTask.PrimaryTable}}{{else}}未聚焦数据表{{end}}</span>
                  <span>{{if and .CurrentAgentTask .CurrentAgentTask.ExportFormat}}导出 {{.CurrentAgentTask.ExportFormat}}{{else}}未指定导出格式{{end}}</span>
                  <span>{{if .CurrentAgentTask}}{{.CurrentAgentTask.UpdatedAt}}{{else}}-{{end}}</span>
                </div>
              </div>
              <div class="agent-task-step-list">
                {{if .CurrentAgentTaskSteps}}
                  {{range .CurrentAgentTaskSteps}}
                    <div class="agent-task-step {{.StatusClass}}">
                      <strong>{{.Title}}</strong>
                      <small>{{.Detail}}</small>
                    </div>
                  {{end}}
                {{else}}
                  <p>任务创建后会把步骤状态写到这里。</p>
                {{end}}
              </div>
            </section>

            <section class="admin-panel admin-panel-wide agent-task-history-panel">
              <div class="admin-panel-head">
                <h2>最近任务</h2>
                <span class="admin-panel-meta">跨会话任务记录</span>
              </div>
              <div class="agent-task-history-list">
                {{if .AgentTasks}}
                  {{range .AgentTasks}}
                    <article class="agent-task-history-item" data-agent-task-id="{{.ID}}">
                      <div class="agent-task-history-head">
                        <div>
                          <strong>{{.Title}}</strong>
                          <span>{{if .Goal}}{{.Goal}}{{else}}{{.LastUserMessage}}{{end}}</span>
                        </div>
                        <span class="admin-badge {{.StatusClass}}">{{.Status}}</span>
                      </div>
                      <div class="agent-task-history-meta">
                        <span>{{.UpdatedAt}}</span>
                        {{if .Intent}}<span class="mono">{{.Intent}}</span>{{end}}
                        {{if .PrimaryTable}}<span>{{.PrimaryTable}}</span>{{end}}
                        {{if .ExportFormat}}<span>{{.ExportFormat}}</span>{{end}}
                      </div>
                      {{if .LastUserMessage}}
                        <p>{{.LastUserMessage}}</p>
                      {{end}}
                    </article>
                  {{end}}
                {{else}}
                  <div class="agent-history-empty">
                    <strong>暂无任务状态</strong>
                    <span>完成一次 AI 任务后，这里会沉淀跨会话的任务记录和步骤状态。</span>
                  </div>
                {{end}}
              </div>
            </section>
          {{else if eq .Active "ai-runs"}}
            <div class="admin-metrics agent-ai-metrics">
              <article class="admin-metric-card">
                <span class="metric-label">当前边界</span>
                <strong>{{.AgentScopeSummary}}</strong>
                <small>{{if .CanRunAgentSQL}}允许只读查询与导出{{else if .CanReadAgentTables}}允许受控读取，不允许 SQL{{else}}当前仅允许方案与说明类任务{{end}}{{if .CanReadWeb}} · 公开网页访问已启用{{end}}</small>
                <span class="admin-status-dot {{if or .CanReadAgentTables .CanReadWeb}}{{if .CanRunAgentSQL}}is-ready{{else}}is-warning{{end}}{{else}}is-muted{{end}}"></span>
              </article>
              <article class="admin-metric-card">
                <span class="metric-label">模型服务</span>
                <strong>{{.AIProvider}}</strong>
                <small>{{.AIModel}}</small>
                <span class="admin-status-dot {{.AIStatusClass}}"></span>
              </article>
              <article class="admin-metric-card">
                <span class="metric-label">最近运行</span>
                <strong>{{len .AgentRuns}} 条</strong>
                <small>运行记录、工具次数和生成文件都会沉淀在这里。</small>
                <span class="admin-status-dot is-secure"></span>
              </article>
            </div>
            <section class="admin-panel admin-panel-wide agent-history-panel">
              <div class="admin-panel-head">
                <h2>最近运行</h2>
                <span class="admin-panel-meta">最近对话与结果记录</span>
              </div>
              <div class="agent-history-list">
                {{if .AgentRuns}}
                  {{range .AgentRuns}}
                    <article class="agent-history-item">
                      <div class="agent-history-head">
                        <div>
                          <strong class="mono">{{.Mode}}</strong>
                          <span>{{.Goal}}</span>
                        </div>
                        <span class="admin-badge {{.StatusClass}}">{{.Status}}</span>
                      </div>
                      <div class="agent-history-meta">
                        <span>{{.StartedAt}}</span>
                        <span class="mono">{{.Actor}}</span>
                        <span>{{.Model}}</span>
                        <span>工具 {{.ToolCount}} 次</span>
                        <span>文件 {{.FileCount}}</span>
                        <span>{{.Duration}}</span>
                      </div>
                      <p>{{.Message}}</p>
                    </article>
                  {{end}}
                {{else}}
                  <div class="agent-history-empty">
                    <strong>暂无运行记录</strong>
                    <span>提交一次任务后会写入 agent_runs，并在这里保留最近执行历史。</span>
                  </div>
                {{end}}
              </div>
            </section>
          {{else if eq .Active "ai-capabilities"}}
            <div class="admin-metrics agent-ai-metrics">
              <article class="admin-metric-card">
                <span class="metric-label">当前边界</span>
                <strong>{{.AgentScopeSummary}}</strong>
                <small>{{if .CanRunAgentSQL}}允许只读查询与导出{{else if .CanReadAgentTables}}允许受控读取，不允许 SQL{{else}}当前仅允许方案与说明类任务{{end}}{{if .CanReadWeb}} · 公开网页访问已启用{{end}}</small>
                <span class="admin-status-dot {{if or .CanReadAgentTables .CanReadWeb}}{{if .CanRunAgentSQL}}is-ready{{else}}is-warning{{end}}{{else}}is-muted{{end}}"></span>
              </article>
              <article class="admin-metric-card">
                <span class="metric-label">模型服务</span>
                <strong>{{.AIProvider}}</strong>
                <small>{{.AIModel}}</small>
                <span class="admin-status-dot {{.AIStatusClass}}"></span>
              </article>
              <article class="admin-metric-card">
                <span class="metric-label">能力清单</span>
                <strong>{{len .AgentCapabilities}} 项</strong>
                <small>规划、轨迹、只读工具、公开网页访问、图片创作与导出记忆</small>
                <span class="admin-status-dot is-progress"></span>
              </article>
            </div>

            <section class="agent-support-grid">
              <div class="admin-panel agent-model-panel">
                <div class="admin-panel-head">
                  <h2>模型与入口</h2>
                  <span class="admin-badge {{.AIStatusClass}}">{{.AIStatus}}</span>
                </div>
                <dl class="admin-kv">
                  <div><dt>服务商</dt><dd>{{.AIProvider}}</dd></div>
                  <div><dt>模型</dt><dd class="mono">{{.AIModel}}</dd></div>
                  <div><dt>图片模型</dt><dd class="mono">{{.AISettings.ImageModel}}</dd></div>
                  <div><dt>接口</dt><dd class="mono">{{.AITarget}}</dd></div>
                </dl>
                <div class="agent-related-links">
                  {{if .CanViewSettingsPage}}
                    <a class="admin-panel-link" href="{{.BasePath}}/settings">去系统设置</a>
                  {{end}}
                  {{if .CanViewWeChatMessages}}
                    <a class="admin-panel-link" href="{{.BasePath}}/wechat-agent/messages">查看微信记录</a>
                  {{end}}
                  {{if .CanViewAuditPage}}
                    <a class="admin-panel-link" href="{{.BasePath}}/audit">查看审计日志</a>
                  {{end}}
                </div>
              </div>

              <div class="admin-panel agent-capability-panel">
                <div class="admin-panel-head">
                  <h2>使用边界</h2>
                  <span class="admin-panel-meta">Runtime Boundaries</span>
                </div>
                <dl class="admin-kv">
                  <div><dt>数据范围</dt><dd>{{.AgentScopeSummary}}</dd></div>
                  <div><dt>查询方式</dt><dd>{{if .CanRunAgentSQL}}允许只读 SQL 与导出{{else if .CanReadAgentTables}}仅允许受控读取{{else}}不允许后台数据读取{{end}}</dd></div>
                  <div><dt>网页访问</dt><dd>{{if .CanReadWeb}}允许访问公开网页{{else}}当前未授予公开网页访问{{end}}</dd></div>
                  <div><dt>图片创作</dt><dd>{{if .CanGenerateAgentImages}}允许调用图片模型并自动保存结果{{else}}当前未授予图片创作能力{{end}}</dd></div>
                </dl>
              </div>
            </section>

            <section class="admin-panel admin-panel-wide">
              <div class="admin-panel-head">
                <h2>智能体能力</h2>
                <span class="admin-panel-meta">Guarded Tools</span>
              </div>
              <div class="agent-capability-grid">
                {{range .AgentCapabilities}}
                  <article class="agent-capability-card">
                    <div class="agent-capability-head">
                      <strong>{{.Name}}</strong>
                      <span class="admin-badge {{.StatusClass}}">{{.Status}}</span>
                    </div>
                    <p>{{.Boundary}}</p>
                  </article>
                {{end}}
              </div>
            </section>

            <section class="admin-panel admin-panel-wide">
              <div class="admin-panel-head">
                <h2>常用任务模板</h2>
                <span class="admin-panel-meta">Prompt Starters</span>
              </div>
              <div class="agent-quick-grid" aria-label="常用任务模板">
                <section class="agent-quick-group">
                  <div class="agent-quick-group-head">
                    <h3>诊断与规划</h3>
                    <p>把巡检、方案和配置类任务先收在这里，需要时再回对话页执行。</p>
                  </div>
                  <div class="agent-quick-actions">
                    <a href="{{.BasePath}}/ai?prompt={{urlquery "对当前后台做一次系统体检并给出下一步建议"}}">系统体检</a>
                    <a href="{{.BasePath}}/ai?prompt={{urlquery "给出 Moyi Admin 智能体构造方案"}}">智能体方案</a>
                    <a href="{{.BasePath}}/ai?prompt={{urlquery "查看站点信息和 AI 配置"}}">站点配置</a>
                  </div>
                </section>
                <section class="agent-quick-group">
                  <div class="agent-quick-group-head">
                    <h3>数据与导出</h3>
                    <p>{{if and .CanReadAgentTables .CanRunAgentSQL}}把查询、巡检和文件导出模板集中在这一组。{{else}}没有足够权限时，这组模板也会自动收缩。{{end}}</p>
                  </div>
                  <div class="agent-quick-actions">
                    {{if and .CanReadAgentTables .CanRunAgentSQL}}
                      <a href="{{.BasePath}}/ai?prompt={{urlquery "我们后台有几个管理员账号？"}}">管理员账号</a>
                      <a href="{{.BasePath}}/ai?prompt={{urlquery "把管理员账号的账号、角色、状态整理成 XLSX 文件发给我"}}">导出 XLSX</a>
                      <a href="{{.BasePath}}/ai?prompt={{urlquery "把数据源配置整理成 JSON 文件发给我"}}">导出 JSON</a>
                      <a href="{{.BasePath}}/ai?prompt={{urlquery "预览数据源配置"}}">数据源巡检</a>
                    {{else if .CanReadAgentTables}}
                      <a href="{{.BasePath}}/ai?prompt={{urlquery "列出所有可以查询的数据表"}}">查看授权表</a>
                    {{else}}
                      <span class="agent-quick-empty">当前用户组未授予数据读取能力。</span>
                    {{end}}
                  </div>
                </section>
                <section class="agent-quick-group">
                  <div class="agent-quick-group-head">
                    <h3>联网与网页</h3>
                    <p>{{if .CanReadWeb}}公开网页抓取和检索已经开放，适合临时查官网、公告和外部说明。{{else}}当前用户组未授予公开网页访问能力。{{end}}</p>
                  </div>
                  <div class="agent-quick-actions">
                    {{if .CanReadWeb}}
                      <a href="{{.BasePath}}/ai?prompt={{urlquery "搜索 Moyi Admin 相关公开资料并整理 3 条要点"}}">公开检索</a>
                      <a href="{{.BasePath}}/ai?prompt={{urlquery "访问 https://openai.com 并总结首页重点"}}">读取网页</a>
                    {{else}}
                      <span class="agent-quick-empty">当前用户组未授予公开网页访问能力。</span>
                    {{end}}
                  </div>
                </section>
                <section class="agent-quick-group">
                  <div class="agent-quick-group-head">
                    <h3>图片创作</h3>
                    <p>{{if .CanGenerateAgentImages}}适合快速生成海报、封面、配图和插图，结果会自动保存到文件管理。{{else}}当前用户组未授予图片创作能力。{{end}}</p>
                  </div>
                  <div class="agent-quick-actions">
                    {{if .CanGenerateAgentImages}}
                      <a href="{{.BasePath}}/ai?prompt={{urlquery "帮我生成一张蓝绿色科技感的后台海报"}}">后台海报</a>
                      <a href="{{.BasePath}}/ai?prompt={{urlquery "生成一张适合官网首页横幅使用的公益主题插图"}}">首页插图</a>
                      <a href="{{.BasePath}}/ai?prompt={{urlquery "帮我做一张简洁的系统公告封面图"}}">公告封面</a>
                    {{else}}
                      <span class="agent-quick-empty">当前用户组未授予图片创作能力。</span>
                    {{end}}
                  </div>
                </section>
              </div>
            </section>
          {{end}}
        {{else if or (eq .Active "wechat-agent") (eq .Active "wechat-agent-messages")}}
          {{if .AgentNotice}}
            <div class="admin-alert {{.AgentNoticeClass}}">{{.AgentNotice}}</div>
          {{end}}
          <section class="admin-panel admin-panel-wide agent-module-shell">
            <div class="admin-panel-head">
              <div>
                <h2>{{if eq .Active "wechat-agent"}}微信 Agent{{else}}微信聊天{{end}}</h2>
                <span class="admin-panel-meta">{{if eq .Active "wechat-agent"}}每个管理员账号对应一条微信 Agent 通道，这里只负责二维码绑定、状态查看和维护。{{else}}独立查看微信侧的用户消息、AI 回复、文件回复和发送状态。{{end}}</span>
              </div>
            </div>
            <nav class="agent-module-nav" aria-label="微信 Agent 页面">
              <a href="{{.BasePath}}/wechat-agent" {{if eq .Active "wechat-agent"}}class="is-active"{{end}}>通道</a>
              <a href="{{.BasePath}}/wechat-agent/messages" {{if eq .Active "wechat-agent-messages"}}class="is-active"{{end}}>聊天记录</a>
            </nav>
          </section>
          {{if eq .Active "wechat-agent"}}
          <section class="admin-grid">
            <div class="admin-panel admin-panel-wide">
              <div class="admin-panel-head">
                <h2>微信 Agent 通道</h2>
                <div class="admin-panel-actions">
                  <span class="admin-badge is-ready">{{len .AgentWeChatChannels}} 个通道</span>
                </div>
              </div>
              <p class="form-text">每个管理员账号都会自动生成一条专属微信 Agent 通道，不能手动新增；如需新增入口，请先新增管理员账号。</p>
              {{if not .CanManageWeChat}}
                <p class="form-text admin-readonly-note">当前账号可以查看通道状态和聊天记录，但不能修改二维码、令牌或通道状态。</p>
              {{end}}
              <div class="agent-channel-list">
                <div class="agent-channel-list-head">
                  <span>通道</span><span>状态</span><span>管理员 / 用户组</span><span>最近消息</span>
                </div>
                {{range .AgentWeChatChannels}}
                  {{$channel := .}}
                  <details class="agent-channel-item">
                    <summary class="agent-channel-summary">
                      <span><strong>{{.DisplayName}}</strong><small class="mono">{{.Key}}</small></span>
                      <span><span class="admin-badge {{.StatusClass}}">{{.StatusText}}</span><small>{{.LoginMessage}}</small></span>
                      <span><strong>{{.AdminUserLabel}}</strong><small>{{.AdminRole}} · {{.AllowedSummary}}</small></span>
                      <span>{{.LastMessageAt}}<small>最近回复：{{.LastOutboundAt}}</small></span>
                    </summary>
                    <div class="agent-channel-detail-card">
                      <div class="agent-channel-layout">
                        <div class="agent-channel-main">
                          <form class="admin-settings-form" method="post" action="{{.Action}}">
                            <input type="hidden" name="wechat_channel_key" value="{{.Key}}">
                            <fieldset class="admin-form-fieldset" {{if not $.CanManageWeChat}}disabled{{end}}>
                              <div class="admin-form-grid">
                                <label>
                                  <span>渠道状态</span>
                                  <select class="form-select" name="wechat_channel_enabled">
                                    <option value="1" {{if .Enabled}}selected{{end}}>启用</option>
                                    <option value="0" {{if not .Enabled}}selected{{end}}>停用</option>
                                  </select>
                                </label>
                                <label>
                                  <span>显示名称</span>
                                  <input class="form-control" name="wechat_channel_name" value="{{.DisplayName}}" autocomplete="off">
                                </label>
                                <label>
                                  <span>Provider Base URL</span>
                                  <input class="form-control mono" name="wechat_base_url" value="{{.BaseURL}}" autocomplete="off">
                                </label>
                                <label>
                                  <span>Bot Type</span>
                                  <input class="form-control mono" name="wechat_bot_type" value="{{.BotType}}" autocomplete="off">
                                </label>
                                <label>
                                  <span>关联管理员</span>
                                  <input type="hidden" name="wechat_admin_user" value="{{$channel.AdminUser}}">
                                  <input class="form-control" value="{{$channel.AdminUserLabel}}" readonly>
                                  <small class="form-text">这条通道会跟随管理员账号自动创建并继承 {{$channel.AdminRole}} · {{$channel.AllowedSummary}}。</small>
                                </label>
                                <div class="admin-form-actions admin-form-wide agent-channel-form-footer">
                                  {{if $.CanManageWeChat}}
                                    <button class="admin-submit-button" type="submit" name="wechat_channel_action" value="save">保存配置</button>
                                  {{else}}
                                    <span class="admin-badge is-muted">当前账号只有查看权限</span>
                                  {{end}}
                                </div>
                                {{if $.CanManageWeChat}}
                                  <details class="agent-channel-maintenance admin-form-wide">
                                    <summary>通道维护</summary>
                                    <div class="agent-channel-maintenance-grid">
                                      <section>
                                        <strong>微信绑定</strong>
                                        <span>二维码失效或登录状态不同步时使用。</span>
                                        {{if .HasQRCode}}
                                          <div class="agent-channel-qr-inline">
                                            <img class="agent-channel-qr" src="{{.QRImageURL}}" alt="OpenClaw Weixin 登录二维码">
                                            <label class="admin-copy-line">
                                              <span>二维码内容</span>
                                              <input class="form-control mono" value="{{.QRPayload}}" readonly>
                                              <small>有效期：{{.BindExpiresAt}}</small>
                                            </label>
                                          </div>
                                        {{end}}
                                        <div class="agent-channel-text-actions">
                                          <button type="submit" name="wechat_channel_action" value="regenerate">生成二维码</button>
                                          <button type="submit" name="wechat_channel_action" value="poll">刷新状态</button>
                                        </div>
                                      </section>
                                      <section>
                                        <strong>高级维护</strong>
                                        <span>令牌泄露时可重置；不用该入口时直接禁用通道。</span>
                                        <div class="agent-channel-text-actions">
                                          <button type="submit" name="wechat_channel_action" value="reset_token">重置令牌</button>
                                          <button class="is-danger" type="submit" name="wechat_channel_action" value="disable">禁用通道</button>
                                        </div>
                                      </section>
                                    </div>
                                  </details>
                                {{end}}
                              </div>
                            </fieldset>
                          </form>
                          <dl class="admin-kv agent-channel-kv">
                            <div><dt>会话</dt><dd class="mono">{{.BindSession}}</dd></div>
                            <div><dt>状态</dt><dd>{{.LoginMessage}}</dd></div>
                            <div><dt>接入令牌</dt><dd class="mono">{{.TokenMasked}}</dd></div>
                            <div><dt>账号 ID</dt><dd class="mono">{{.AccountID}}</dd></div>
                            <div><dt>微信用户</dt><dd class="mono">{{.OpenClawUserID}}</dd></div>
                            <div><dt>绑定用户</dt><dd>{{.BoundUser}}</dd></div>
                            <div><dt>后台身份</dt><dd>{{.AdminUserLabel}}</dd></div>
                            <div><dt>用户组权限</dt><dd>{{.AdminRole}} · {{.AllowedSummary}}</dd></div>
                            <div><dt>绑定时间</dt><dd>{{.BoundAt}}</dd></div>
                            <div><dt>最近消息</dt><dd>{{.LastMessageAt}}</dd></div>
                            <div><dt>最近心跳</dt><dd>{{.LastHeartbeatAt}}</dd></div>
                            <div><dt>最近回复</dt><dd>{{.LastOutboundAt}}</dd></div>
                            <div><dt>通道错误</dt><dd>{{.LastError}}</dd></div>
                          </dl>
                        </div>
                      </div>
                      <div class="agent-channel-chat-log">
                        <div class="agent-channel-section-head">
                          <strong>最近聊天记录</strong>
                          <a class="admin-panel-link" href="{{$.BasePath}}/wechat-agent/messages?channel={{.Key}}">查看全部</a>
                        </div>
                        {{if .ChatMessages}}
                          <div class="admin-table agent-channel-message-table">
                            <div class="admin-table-row admin-table-head">
                              <span>时间</span><span>用户消息</span><span>AI 回复</span><span>文件</span><span>状态</span>
                            </div>
                            {{range .ChatMessages}}
                              <div class="admin-table-row">
                                <span>{{.ReceivedAt}}<small class="mono">{{.MessageID}}</small><small class="mono">{{.SessionID}}</small><small class="mono">{{.RunID}}</small></span>
                                <span>{{.InboundText}}<small class="mono">{{.FromUserID}}</small></span>
                                <span>{{.ReplyText}}<small>回复：{{.RepliedAt}} · {{.DurationText}}</small></span>
                                <span>{{.FileSummary}}</span>
                                <span><span class="admin-badge {{.StatusClass}}">{{.Status}}</span>{{if .Error}}<small>{{.Error}}</small>{{end}}</span>
                              </div>
                            {{end}}
                          </div>
                        {{else}}
                          <div class="agent-channel-empty">暂无聊天记录；收到微信消息并完成回复后会自动归档。</div>
                        {{end}}
                      </div>
                    </div>
                  </details>
                {{else}}
                  <div class="agent-channel-empty">暂无可用微信通道；先新增管理员账号，系统会自动生成对应的微信 Agent 通道。</div>
                {{end}}
              </div>
            </div>
          </section>
          {{else}}
          <section class="admin-panel admin-panel-wide">
            <div class="admin-panel-head">
              <div>
                <h2>微信 Agent 聊天记录</h2>
                <span class="admin-panel-meta">按最新消息优先展示，回复文本、文件和错误状态均来自归档表。</span>
              </div>
              <div class="admin-panel-actions">
                <span class="admin-badge is-ready">{{.AgentWeChatMessagePage.RangeText}}</span>
                <a class="admin-panel-link" href="{{.AgentWeChatMessagePage.ExportURL}}">导出 CSV</a>
              </div>
            </div>
            <form class="admin-settings-form audit-filter-form" method="get" action="{{.AgentWeChatMessagePage.Action}}">
              <div class="admin-form-grid">
                <label>
                  <span>微信通道</span>
                  <select class="form-select" name="channel">
                    <option value="" {{if eq .AgentWeChatMessagePage.ChannelKey ""}}selected{{end}}>全部通道</option>
                    {{range .AgentWeChatMessagePage.ChannelOptions}}
                      <option value="{{.Key}}" {{if .Selected}}selected{{end}}>{{.Label}} · {{.Key}}</option>
                    {{end}}
                  </select>
                </label>
              </div>
              <div class="admin-filter-actions">
                <button class="admin-submit-button" type="submit">筛选记录</button>
                <a class="admin-filter-reset" href="{{.AgentWeChatMessagePage.ResetURL}}">重置</a>
              </div>
            </form>
            <div class="admin-table agent-message-history-table">
              <div class="admin-table-row admin-table-head">
                <span>时间</span><span>通道</span><span>用户消息</span><span>AI 回复</span><span>文件</span><span>状态</span>
              </div>
              {{if .AgentWeChatMessagePage.Rows}}
                {{range .AgentWeChatMessagePage.Rows}}
                  <div class="admin-table-row">
                    <span>{{.ReceivedAt}}<small class="mono">{{.MessageID}}</small><small class="mono">{{.SessionID}}</small></span>
                    <span><strong>{{.ChannelName}}</strong><small class="mono">{{.ChannelKey}}</small><small>{{.Provider}}</small></span>
                    <span>{{.InboundText}}<small class="mono">{{.FromUserID}}</small></span>
                    <span>{{.ReplyText}}<small>回复：{{.RepliedAt}} · {{.DurationText}}</small><small class="mono">{{.RunID}}</small></span>
                    <span>{{.FileSummary}}</span>
                    <span><span class="admin-badge {{.StatusClass}}">{{.Status}}</span>{{if .Error}}<small>{{.Error}}</small>{{end}}</span>
                  </div>
                {{end}}
              {{else}}
                <div class="admin-table-row">
                  <span>-</span><span>暂无聊天归档</span><span>收到微信消息并完成回复后会自动出现在这里。</span><span>-</span><span>-</span><span class="admin-badge is-muted">等待</span>
                </div>
              {{end}}
            </div>
            <div class="admin-pagination">
              <span>{{.AgentWeChatMessagePage.RangeText}} · {{.AgentWeChatMessagePage.PageText}}</span>
              <div>
                {{if .AgentWeChatMessagePage.HasPrev}}
                  <a href="{{.AgentWeChatMessagePage.PrevURL}}">上一页</a>
                {{else}}
                  <span class="disabled">上一页</span>
                {{end}}
                {{if .AgentWeChatMessagePage.HasNext}}
                  <a href="{{.AgentWeChatMessagePage.NextURL}}">下一页</a>
                {{else}}
                  <span class="disabled">下一页</span>
                {{end}}
              </div>
            </div>
          </section>
          {{end}}
        {{else if or (eq .Active "users") (eq .Active "user-groups") (eq .Active "user-sessions") (eq .Active "user-permissions")}}
          {{if .UserNotice}}
            <div class="admin-alert {{.UserNoticeClass}}">{{.UserNotice}}</div>
          {{end}}
          <section class="admin-grid">
            {{if eq .Active "users"}}
            <div class="access-users-page access-claw-page" data-access-users>
              <section class="access-claw-metrics" aria-label="管理员账号概览">
                {{range .AdminUserMetrics}}
                  <article class="access-claw-metric">
                    <span>{{.Label}}</span>
                    <strong>{{.Value}}</strong>
                    <small>{{.Detail}}</small>
                    <i class="admin-status-dot {{.Status}}"></i>
                  </article>
                {{end}}
              </section>

              <form class="access-claw-filters" data-access-filters>
                <input class="form-control" type="search" placeholder="搜索账号、显示名称、用户组" autocomplete="off" data-access-search>
                <select class="form-select" data-access-status>
                  <option value="">全部状态</option>
                  <option value="enabled">启用</option>
                  <option value="disabled">禁用</option>
                </select>
                <select class="form-select" data-access-role>
                  <option value="">全部用户组</option>
                  {{range .AdminRoles}}
                    <option value="{{.Key}}">{{.Name}}</option>
                  {{end}}
                </select>
                <a class="admin-panel-link is-button" href="{{.BasePath}}/users/groups">用户组权限</a>
                <a class="admin-panel-link is-button" href="{{.BasePath}}/users/sessions">登录会话</a>
                <span class="access-claw-filter-spacer"></span>
                <button class="admin-panel-link is-button is-primary" type="button" data-access-create-toggle aria-expanded="false">新增管理员</button>
              </form>

              <section class="access-claw-grid">
                <div class="admin-panel access-claw-table-card">
                  <div class="access-claw-table-head">
                    <div>
                      <h2>账号列表</h2>
                      <span>匹配 <b data-access-visible-count>{{len .AdminUsers}}</b> / 全部 {{len .AdminUsers}} 个管理员</span>
                    </div>
                    <a class="admin-panel-link is-button" href="{{.BasePath}}/users">刷新</a>
                  </div>
                  <div class="access-claw-table-wrap">
                    <table class="access-claw-table">
                      <thead>
                        <tr>
                          <th>管理员</th>
                          <th>用户组</th>
                          <th>来源</th>
                          <th>状态</th>
                          <th>最近访问</th>
                          <th>操作</th>
                        </tr>
                      </thead>
                      <tbody>
                        {{range .AdminUsers}}
                          <tr class="access-admin-row" tabindex="0"
                            data-access-user-row
                            data-username="{{.Username}}"
                            data-display-name="{{.DisplayName}}"
                            data-initial="{{.Initial}}"
                            data-role="{{.Role}}"
                            data-role-key="{{.RoleKey}}"
                            data-status="{{.StatusKey}}"
                            data-status-text="{{.Status}}"
                            data-status-class="{{.StatusClass}}"
                            data-source="{{.SourceLabel}}"
                            data-created="{{.CreatedAt}}"
                            data-last-seen="{{.LastSeen}}"
                            data-can-edit="{{if .CanDelete}}true{{else}}false{{end}}"
                            data-filter-text="{{.Username}} {{.DisplayName}} {{.Role}} {{.RoleKey}} {{.SourceLabel}}">
                            <td>
                              <div class="access-claw-user">
                                <span class="access-user-avatar">{{.Initial}}</span>
                                <span>
                                  <strong>{{.DisplayName}}</strong>
                                  <small class="mono">{{.Username}}</small>
                                </span>
                              </div>
                            </td>
                            <td><strong>{{.Role}}</strong><small class="mono">{{.RoleKey}}</small></td>
                            <td>{{.SourceLabel}}<small>{{.CreatedAt}}</small></td>
                            <td><span class="admin-badge {{.StatusClass}}">{{.Status}}</span></td>
                            <td>{{.LastSeen}}</td>
                            <td>
                              <span class="admin-file-actions access-user-actions">
                                {{if .CanDelete}}
                                  <form method="post" action="{{.ToggleAction}}">
                                    <input type="hidden" name="username" value="{{.Username}}">
                                    <button type="submit">{{.ToggleLabel}}</button>
                                  </form>
                                  <form method="post" action="{{.DeleteAction}}">
                                    <input type="hidden" name="username" value="{{.Username}}">
                                    <button type="submit">删除</button>
                                  </form>
                                {{else}}
                                  <span class="admin-badge is-muted">内置保护</span>
                                {{end}}
                              </span>
                            </td>
                          </tr>
                        {{else}}
                          <tr>
                            <td class="access-user-empty" colspan="6">
                              <strong>暂无管理员账号</strong>
                              <span>创建第一个后台管理员后会出现在这里。</span>
                            </td>
                          </tr>
                        {{end}}
                      </tbody>
                    </table>
                  </div>
                </div>

                <aside class="access-claw-aside">
                  <div class="admin-panel access-claw-side-panel">
                    <div class="admin-panel-head access-side-panel-head">
                      <div>
                        <h2 data-access-side-title>账号详情</h2>
                        <span class="admin-panel-meta" data-access-side-meta>Profile</span>
                      </div>
                      <button class="admin-panel-link is-button" type="button" data-access-cancel hidden>返回详情</button>
                    </div>
                    <div class="access-side-view" data-access-detail-view>
                      <div class="access-detail-empty" data-access-detail-empty>选择左侧管理员查看账号详情</div>
                      <div class="access-detail-body" data-access-detail-body hidden>
                        <div class="access-detail-head">
                          <span class="access-user-avatar" data-access-detail-initial>A</span>
                          <div>
                            <h2 data-access-detail-name>管理员</h2>
                            <small class="mono" data-access-detail-username>-</small>
                          </div>
                        </div>
                        <dl class="access-detail-kv">
                          <div><dt>用户组</dt><dd><span data-access-detail-role>-</span><small class="mono" data-access-detail-role-key>-</small></dd></div>
                          <div><dt>状态</dt><dd><span class="admin-badge is-muted" data-access-detail-status>-</span></dd></div>
                          <div><dt>来源</dt><dd data-access-detail-source>-</dd></div>
                          <div><dt>创建时间</dt><dd data-access-detail-created>-</dd></div>
                          <div><dt>最近访问</dt><dd data-access-detail-last>-</dd></div>
                        </dl>
                        <div class="access-detail-actions">
                          <button class="admin-panel-link is-button" type="button" data-access-edit-toggle hidden>编辑当前管理员</button>
                          <span class="admin-badge is-muted" data-access-protected-note hidden>内置超级管理员不可在这里覆盖</span>
                          <a href="{{.BasePath}}/users/groups">调整用户组权限</a>
                          <a href="{{.BasePath}}/users/permissions">查看菜单权限</a>
                        </div>
                      </div>
                    </div>
                    <div class="access-side-view access-create-view" data-access-edit-view hidden>
                      <form class="admin-settings-form" method="post" action="{{.UserSaveAction}}" data-access-user-form>
                        <div class="access-role-form-note" data-access-form-note>新增管理员后，就可以把它分配到已有用户组，并继承对应的菜单、动作权限和 Agent 数据边界。</div>
                        <div class="access-form-stack">
                          <label>
                            <span>账号</span>
                            <input class="form-control mono" name="username" placeholder="ops_admin" autocomplete="off" data-access-username-input>
                            <small class="form-text" data-access-username-help>用于登录，建议使用稳定账号名；保存后会作为管理员唯一标识。</small>
                          </label>
                          <label>
                            <span>显示名称</span>
                            <input class="form-control" name="display_name" placeholder="运维管理员" autocomplete="off" data-access-display-name-input>
                          </label>
                          <label>
                            <span>用户组</span>
                            <select class="form-select" name="role" data-access-role-input>
                              {{range .AdminRoles}}
                                <option value="{{.Key}}">{{.Name}}</option>
                              {{end}}
                            </select>
                          </label>
                          <label>
                            <span>账号状态</span>
                            <select class="form-select" name="status" data-access-status-input>
                              <option value="enabled">启用</option>
                              <option value="disabled">禁用</option>
                            </select>
                          </label>
                          <label>
                            <span data-access-password-label>初始密码</span>
                            <input class="form-control mono" name="password" type="password" placeholder="至少 6 位" autocomplete="new-password" data-access-password-input>
                            <small class="form-text" data-access-password-help>新增管理员必须设置初始密码；编辑已有管理员时，留空会保留原密码。</small>
                          </label>
                        </div>
                        <button class="admin-submit-button" type="submit" data-access-submit-button>保存管理员</button>
                      </form>
                    </div>
                  </div>
                </aside>
              </section>
            </div>
            {{end}}

            {{if eq .Active "user-groups"}}
            <div class="access-role-page access-claw-page" data-access-roles>
              <section class="access-claw-metrics" aria-label="用户组权限概览">
                {{range .AdminRoleMetrics}}
                  <article class="access-claw-metric">
                    <span>{{.Label}}</span>
                    <strong>{{.Value}}</strong>
                    <small>{{.Detail}}</small>
                    <i class="admin-status-dot {{.Status}}"></i>
                  </article>
                {{end}}
              </section>

              <form class="access-claw-filters" data-access-role-filters>
                <input class="form-control" type="search" placeholder="搜索用户组、范围、权限说明" autocomplete="off" data-access-role-search>
                <select class="form-select" data-access-role-status>
                  <option value="">全部状态</option>
                  <option value="enabled">启用</option>
                  <option value="disabled">禁用</option>
                </select>
                <a class="admin-panel-link is-button" href="{{.BasePath}}/users">管理员账号</a>
                <a class="admin-panel-link is-button" href="{{.BasePath}}/users/permissions">菜单权限</a>
                <span class="access-claw-filter-spacer"></span>
                <button class="admin-panel-link is-button is-primary" type="button" data-access-role-create-toggle aria-expanded="false">新增用户组</button>
              </form>

              <section class="access-claw-grid">
                <div class="admin-panel access-claw-table-card">
                  <div class="access-claw-table-head">
                    <div>
                      <h2>用户组列表</h2>
                      <span>匹配 <b data-access-role-visible-count>{{len .AdminRoles}}</b> / 全部 {{len .AdminRoles}} 个用户组</span>
                    </div>
                    <a class="admin-panel-link is-button" href="{{.BasePath}}/users/groups">刷新</a>
                  </div>
                  <div class="access-claw-table-wrap">
                    <table class="access-claw-table access-role-list-table">
                      <thead>
                        <tr>
                          <th>用户组</th>
                          <th>管理范围</th>
                          <th>只读数据范围</th>
                          <th>关联管理员</th>
                        </tr>
                      </thead>
                      <tbody>
                        {{range .AdminRoles}}
                          <tr class="access-role-row" tabindex="0"
                            data-access-role-row
                            data-initial="{{.Initial}}"
                            data-key="{{.Key}}"
                            data-name="{{.Name}}"
                            data-scope="{{.Scope}}"
                            data-status="{{.StatusKey}}"
                            data-status-text="{{.Status}}"
                            data-status-class="{{.StatusClass}}"
                            data-description="{{.Description}}"
                            data-data-scope="{{.DataScope}}"
                            data-allowed-tables="{{.AllowedTables}}"
                            data-allowed-summary="{{.AllowedSummary}}"
                            data-menu-keys="{{.MenuKeys}}"
                            data-menu-summary="{{.MenuSummary}}"
                            data-permission-keys="{{.PermissionKeys}}"
                            data-permission-summary="{{.PermissionSummary}}"
                            data-user-count="{{.UserCount}}"
                            data-filter-text="{{.Name}} {{.Key}} {{.Scope}} {{.Description}} {{.AllowedSummary}} {{.MenuSummary}} {{.PermissionSummary}}">
                            <td>
                              <div class="access-claw-user">
                                <span class="access-user-avatar">{{.Initial}}</span>
                                <span>
                                  <strong>{{.Name}}</strong>
                                  <small class="mono">{{.Key}}</small>
                                </span>
                              </div>
                            </td>
                            <td><strong>{{.Scope}}</strong><small>{{.Description}}</small></td>
                            <td><span class="admin-badge {{.StatusClass}}">{{.Status}}</span><small>{{.AllowedSummary}}</small></td>
                            <td><strong>{{.UserCount}} 人</strong><small>已关联管理员账号</small></td>
                          </tr>
                        {{else}}
                          <tr>
                            <td class="access-user-empty" colspan="4">
                              <strong>暂无用户组权限</strong>
                              <span>创建第一个用户组后会出现在这里。</span>
                            </td>
                          </tr>
                        {{end}}
                      </tbody>
                    </table>
                  </div>
                </div>

                <aside class="access-claw-aside">
                  <div class="admin-panel access-claw-side-panel access-role-side-panel">
                    <div class="admin-panel-head access-side-panel-head">
                      <div>
                        <h2 data-access-role-panel-title>用户组详情</h2>
                        <span class="admin-panel-meta" data-access-role-panel-meta>Groups</span>
                      </div>
                      <button class="admin-panel-link is-button" type="button" data-access-role-cancel hidden>返回当前项</button>
                    </div>
                    <div class="access-side-view access-role-summary" data-access-role-detail-view>
                      <div class="access-detail-empty" data-access-role-empty>选择左侧用户组查看并编辑权限配置</div>
                      <div class="access-detail-body access-role-summary-body" data-access-role-summary-body hidden>
                        <div class="access-detail-head">
                          <span class="access-user-avatar" data-access-role-initial>组</span>
                          <div>
                            <h2 data-access-role-name>用户组</h2>
                            <small class="mono" data-access-role-key-label>-</small>
                          </div>
                        </div>
                        <dl class="access-detail-kv">
                          <div><dt>管理范围</dt><dd data-access-role-scope>-</dd></div>
                          <div><dt>状态</dt><dd><span class="admin-badge is-muted" data-access-role-status-badge>-</span></dd></div>
                          <div><dt>后台菜单</dt><dd data-access-role-menu-summary>-</dd></div>
                          <div><dt>动作权限</dt><dd data-access-role-permission-summary>-</dd></div>
                          <div><dt>只读数据范围</dt><dd data-access-role-allowed-summary>-</dd></div>
                          <div><dt>关联管理员</dt><dd data-access-role-user-count>-</dd></div>
                        </dl>
                        <div class="access-detail-actions">
                          <button class="admin-panel-link is-button" type="button" data-access-role-edit-toggle>编辑当前用户组</button>
                          <a href="{{.BasePath}}/users">查看管理员账号</a>
                        </div>
                      </div>
                    </div>
                    <div class="access-side-view access-create-view access-role-edit-view" data-access-role-edit-view hidden>
                      <form class="admin-settings-form access-role-form" method="post" action="{{.RoleSaveAction}}" data-access-role-form>
                        <div class="access-role-form-note" data-access-role-form-note>新增用户组后，就可以在管理员账号里把账号分配到对应用户组，并让后台菜单、动作权限和 Agent 只读边界一起生效。</div>
                        <div class="admin-form-grid">
                          <label>
                            <span>用户组 Key</span>
                            <input class="form-control mono" name="role_key" autocomplete="off" data-access-role-key-input>
                            <small class="form-text" data-access-role-key-help>用于唯一标识用户组，建议使用类似 finance_reader 的稳定 Key。</small>
                          </label>
                          <label>
                            <span>用户组名称</span>
                            <input class="form-control" name="role_name" autocomplete="off" data-access-role-name-input>
                          </label>
                          <label>
                            <span>状态</span>
                            <select class="form-select" name="role_status" data-access-role-status-input>
                              <option value="enabled">启用</option>
                              <option value="disabled">禁用</option>
                            </select>
                          </label>
                          <label>
                            <span>管理范围</span>
                            <input class="form-control" name="role_scope" autocomplete="off" data-access-role-scope-input>
                          </label>
                          <label>
                            <span>只读数据范围</span>
                            <select class="form-select" name="role_data_scope" data-access-role-data-scope-input>
                              <option value="none">禁止读取数据表</option>
                              <option value="tables">指定只读数据表</option>
                              <option value="all">全部已登记表（只读）</option>
                            </select>
                          </label>
                          <label class="admin-form-wide">
                            <span>说明</span>
                            <input class="form-control" name="role_description" autocomplete="off" data-access-role-description-input>
                          </label>
                          <label class="admin-form-wide">
                            <span>后台菜单</span>
                            <input type="hidden" name="role_menu_keys" value="" data-access-role-menu-input>
                            <div class="agent-table-picker" data-access-role-menu-picker>
                              <div class="agent-table-picker-note">这里只控制后台左侧导航和页面入口。未勾选任何菜单时，会默认保留“工作台”作为登录入口。</div>
                              {{range $.RoleMenuGroups}}
                                <section class="agent-table-group">
                                  <div class="agent-table-group-head">
                                    <div>
                                      <strong>{{.Title}}</strong>
                                      <span>{{.Description}}</span>
                                    </div>
                                    <button type="button" data-access-role-menu-group-select>全选本组</button>
                                  </div>
                                  <div class="agent-table-options">
                                    {{range .Items}}
                                      <label class="agent-table-option">
                                        <input type="checkbox" value="{{.Key}}" data-access-role-menu-checkbox>
                                        <span>
                                          <strong>{{.Label}}</strong>
                                          <small class="mono">{{.Key}}</small>
                                          <em>{{.Description}}</em>
                                        </span>
                                      </label>
                                    {{end}}
                                  </div>
                                </section>
                              {{end}}
                            </div>
                            <small class="form-text">用户组进入后台后只能看到已授权的菜单页面。</small>
                          </label>
                          <label class="admin-form-wide">
                            <span>动作权限</span>
                            <input type="hidden" name="role_permission_keys" value="" data-access-role-permission-input>
                            <div class="agent-table-picker" data-access-role-permission-picker>
                              <div class="agent-table-picker-note">动作权限控制“新增、保存、删除、执行任务、微信 Agent 维护、数据查询”等实际操作，不会再只停留在文案层。</div>
                              {{range $.RolePermissionGroups}}
                                <section class="agent-table-group">
                                  <div class="agent-table-group-head">
                                    <div>
                                      <strong>{{.Title}}</strong>
                                      <span>{{.Description}}</span>
                                    </div>
                                    <button type="button" data-access-role-permission-group-select>全选本组</button>
                                  </div>
                                  <div class="agent-table-options">
                                    {{range .Items}}
                                      <label class="agent-table-option">
                                        <input type="checkbox" value="{{.Key}}" data-access-role-permission-checkbox>
                                        <span>
                                          <strong>{{.Label}}</strong>
                                          <small class="mono">{{.Key}}</small>
                                          <em>{{.Description}}</em>
                                        </span>
                                      </label>
                                    {{end}}
                                  </div>
                                </section>
                              {{end}}
                            </div>
                            <small class="form-text">例如只给“菜单”而不给“动作权限”，页面就算能看到，也不能执行保存或删除。</small>
                          </label>
                          <label class="admin-form-wide">
                            <span>授权只读数据表</span>
                            <input type="hidden" name="role_allowed_tables" value="" data-agent-table-values data-access-role-allowed-input>
                            <div class="agent-table-picker" data-agent-table-picker>
                              <div class="agent-table-picker-note">该白名单会被关联管理员的后台 AI 与微信 Agent 继承；同时还需要配合“AI 智能体”菜单和智能体相关动作权限，才能真正执行受控查询。</div>
                              {{range $.AgentTableGroups}}
                                <section class="agent-table-group">
                                  <div class="agent-table-group-head">
                                    <div>
                                      <strong>{{.Title}}</strong>
                                      <span>{{.Description}}</span>
                                    </div>
                                    <button type="button" data-agent-table-group-select>全选本组</button>
                                  </div>
                                  <div class="agent-table-options">
                                    {{range .Tables}}
                                      <label class="agent-table-option">
                                        <input type="checkbox" value="{{.Name}}" data-agent-table-checkbox>
                                        <span>
                                          <strong>{{.Label}}</strong>
                                          <small class="mono">{{.Name}}</small>
                                          <em>{{.Description}}</em>
                                        </span>
                                      </label>
                                    {{end}}
                                  </div>
                                </section>
                              {{end}}
                            </div>
                            <small class="form-text">仅在“指定只读数据表”模式下生效。</small>
                          </label>
                        </div>
                        <button class="admin-submit-button" type="submit" data-access-role-submit>保存用户组权限</button>
                      </form>
                    </div>
                  </div>
                </aside>
              </section>
            </div>
            {{end}}

            {{if eq .Active "user-sessions"}}
            <div class="access-session-page access-claw-page" data-access-sessions>
              <section class="access-claw-metrics" aria-label="登录会话概览">
                {{range .AdminSessionMetrics}}
                  <article class="access-claw-metric">
                    <span>{{.Label}}</span>
                    <strong>{{.Value}}</strong>
                    <small>{{.Detail}}</small>
                    <i class="admin-status-dot {{.Status}}"></i>
                  </article>
                {{end}}
              </section>

              <form class="access-claw-filters" data-access-session-filters>
                <input class="form-control" type="search" placeholder="搜索会话 ID、账号、IP 或客户端" autocomplete="off" data-access-session-search>
                <select class="form-select" data-access-session-status>
                  <option value="">全部状态</option>
                  <option value="active">在线</option>
                  <option value="revoked">已下线</option>
                  <option value="expired">已过期</option>
                </select>
                <a class="admin-panel-link is-button" href="{{.BasePath}}/users">管理员账号</a>
                <a class="admin-panel-link is-button" href="{{.BasePath}}/users/permissions">菜单权限</a>
                <span class="access-claw-filter-spacer"></span>
                <a class="admin-panel-link is-button" href="{{.BasePath}}/users/sessions">刷新</a>
              </form>

              <section class="access-claw-grid">
                <div class="admin-panel access-claw-table-card">
                  <div class="access-claw-table-head">
                    <div>
                      <h2>会话列表</h2>
                      <span>匹配 <b data-access-session-visible-count>{{len .AdminSessions}}</b> / 全部 {{len .AdminSessions}} 条会话记录</span>
                    </div>
                    <span class="admin-panel-meta">Sessions</span>
                  </div>
                  <div class="access-claw-table-wrap">
                    <table class="access-claw-table access-session-list-table">
                      <thead>
                        <tr>
                          <th>会话</th>
                          <th>来源</th>
                          <th>状态</th>
                          <th>有效期</th>
                          <th>操作</th>
                        </tr>
                      </thead>
                      <tbody>
                        {{range .AdminSessions}}
                          <tr class="access-session-row" tabindex="0"
                            data-access-session-row
                            data-initial="{{.Initial}}"
                            data-session-id="{{.ID}}"
                            data-id-short="{{.IDShort}}"
                            data-username="{{.Username}}"
                            data-status="{{.StatusKey}}"
                            data-status-text="{{.Status}}"
                            data-status-class="{{.StatusClass}}"
                            data-ip="{{.IP}}"
                            data-user-agent="{{.UserAgent}}"
                            data-created="{{.CreatedAt}}"
                            data-expires="{{.ExpiresAt}}"
                            data-can-revoke="{{if .CanRevoke}}1{{else}}0{{end}}"
                            data-filter-text="{{.ID}} {{.IDShort}} {{.Username}} {{.IP}} {{.UserAgent}} {{.Status}}">
                            <td>
                              <div class="access-claw-user">
                                <span class="access-user-avatar">{{.Initial}}</span>
                                <span>
                                  <strong>{{.Username}}</strong>
                                  <small class="mono">{{.IDShort}}</small>
                                </span>
                              </div>
                            </td>
                            <td><strong>{{.IP}}</strong><small>{{.UserAgent}}</small></td>
                            <td><span class="admin-badge {{.StatusClass}}">{{.Status}}</span></td>
                            <td><strong>{{.ExpiresAt}}</strong><small>{{.CreatedAt}}</small></td>
                            <td>
                              <span class="admin-file-actions access-user-actions">
                                {{if .CanRevoke}}
                                  <form method="post" action="{{.RevokeAction}}">
                                    <input type="hidden" name="session_id" value="{{.ID}}">
                                    <button type="submit">下线</button>
                                  </form>
                                {{else}}
                                  <span class="admin-badge is-muted">当前/不可操作</span>
                                {{end}}
                              </span>
                            </td>
                          </tr>
                        {{else}}
                          <tr>
                            <td class="access-user-empty" colspan="5">
                              <strong>暂无后台会话</strong>
                              <span>新的后台登录会自动出现在这里。</span>
                            </td>
                          </tr>
                        {{end}}
                      </tbody>
                    </table>
                  </div>
                </div>

                <aside class="access-claw-aside">
                  <div class="admin-panel access-claw-side-panel access-session-side-panel">
                    <div class="admin-panel-head access-side-panel-head">
                      <div>
                        <h2>会话详情</h2>
                        <span class="admin-panel-meta">Sessions</span>
                      </div>
                    </div>
                    <div class="access-side-view">
                      <div class="access-detail-empty" data-access-session-empty>选择左侧会话查看来源、有效期和下线状态</div>
                      <div class="access-detail-body" data-access-session-body hidden>
                        <div class="access-detail-head">
                          <span class="access-user-avatar" data-access-session-initial>S</span>
                          <div>
                            <h2 data-access-session-username>会话</h2>
                            <small class="mono" data-access-session-id>-</small>
                          </div>
                        </div>
                        <dl class="access-detail-kv">
                          <div><dt>来源 IP</dt><dd data-access-session-ip>-</dd></div>
                          <div><dt>客户端</dt><dd data-access-session-agent>-</dd></div>
                          <div><dt>创建时间</dt><dd data-access-session-created>-</dd></div>
                          <div><dt>有效期</dt><dd data-access-session-expires>-</dd></div>
                          <div><dt>状态</dt><dd><span class="admin-badge is-muted" data-access-session-status-badge>-</span></dd></div>
                        </dl>
                        <div class="access-detail-actions">
                          <form method="post" action="{{.BasePath}}/users/sessions/revoke" data-access-session-revoke hidden>
                            <input type="hidden" name="session_id" value="" data-access-session-revoke-id>
                            <button class="admin-panel-link is-button" type="submit">强制下线</button>
                          </form>
                          <a href="{{.BasePath}}/users">查看管理员账号</a>
                        </div>
                      </div>
                    </div>
                  </div>
                </aside>
              </section>
            </div>
            {{end}}

            {{if eq .Active "user-permissions"}}
            <div class="access-permission-page access-claw-page" data-access-permissions>
              <section class="access-claw-metrics" aria-label="菜单与权限概览">
                {{range .AdminPermissionMetrics}}
                  <article class="access-claw-metric">
                    <span>{{.Label}}</span>
                    <strong>{{.Value}}</strong>
                    <small>{{.Detail}}</small>
                    <i class="admin-status-dot {{.Status}}"></i>
                  </article>
                {{end}}
              </section>

              <form class="access-claw-filters" data-access-permission-filters>
                <input class="form-control" type="search" placeholder="搜索菜单、路径、权限 Key、对象或边界" autocomplete="off" data-access-permission-search>
                <select class="form-select" data-access-permission-kind>
                  <option value="">全部类型</option>
                  <option value="menu">菜单</option>
                  <option value="permission">权限</option>
                </select>
                <select class="form-select" data-access-permission-status>
                  <option value="">全部状态</option>
                  <option value="enabled">启用</option>
                  <option value="disabled">禁用</option>
                </select>
                <a class="admin-panel-link is-button" href="{{.BasePath}}/users">管理员账号</a>
                <a class="admin-panel-link is-button" href="{{.BasePath}}/users/groups">用户组权限</a>
                <span class="access-claw-filter-spacer"></span>
                <a class="admin-panel-link is-button" href="{{.BasePath}}/users/permissions">刷新</a>
              </form>

              <section class="access-claw-grid">
                <div class="access-claw-stack">
                  <div class="admin-panel access-claw-table-card">
                    <div class="access-claw-table-head">
                      <div>
                        <h2>菜单清单</h2>
                        <span>匹配 <b data-access-menu-visible-count>{{len .AdminMenus}}</b> / 全部 {{len .AdminMenus}} 个菜单</span>
                      </div>
                      <span class="admin-panel-meta">Menus</span>
                    </div>
                    <div class="access-claw-table-wrap">
                      <table class="access-claw-table access-menu-list-table">
                        <thead>
                          <tr>
                            <th>菜单</th>
                            <th>路径</th>
                            <th>状态</th>
                          </tr>
                        </thead>
                        <tbody>
                          {{range .AdminMenus}}
                            <tr class="access-menu-row" tabindex="0"
                              data-access-menu-row
                              data-kind="menu"
                              data-initial="{{.Initial}}"
                              data-key="{{.Key}}"
                              data-label="{{.Label}}"
                              data-path="{{.Path}}"
                              data-status="{{.StatusKey}}"
                              data-status-text="{{.Status}}"
                              data-status-class="{{.StatusClass}}"
                              data-description="后台菜单入口"
                              data-filter-text="{{.Label}} {{.Key}} {{.Path}} {{.Status}}">
                              <td>
                                <div class="access-claw-user">
                                  <span class="access-user-avatar">{{.Initial}}</span>
                                  <span>
                                    <strong>{{.Label}}</strong>
                                    <small class="mono">{{.Key}}</small>
                                  </span>
                                </div>
                              </td>
                              <td class="mono">{{.Path}}</td>
                              <td><span class="admin-badge {{.StatusClass}}">{{.Status}}</span></td>
                            </tr>
                          {{else}}
                            <tr>
                              <td class="access-user-empty" colspan="3">
                                <strong>暂无菜单配置</strong>
                                <span>后台菜单启用后会出现在这里。</span>
                              </td>
                            </tr>
                          {{end}}
                        </tbody>
                      </table>
                    </div>
                  </div>

                  <div class="admin-panel access-claw-table-card">
                    <div class="access-claw-table-head">
                      <div>
                        <h2>权限清单</h2>
                        <span>匹配 <b data-access-permission-visible-count>{{len .AdminPermissions}}</b> / 全部 {{len .AdminPermissions}} 条权限规则</span>
                      </div>
                      <span class="admin-panel-meta">Permissions</span>
                    </div>
                    <div class="access-claw-table-wrap">
                      <table class="access-claw-table access-permission-list-table">
                        <thead>
                          <tr>
                            <th>权限</th>
                            <th>资源对象</th>
                            <th>状态</th>
                          </tr>
                        </thead>
                        <tbody>
                          {{range .AdminPermissions}}
                            <tr class="access-permission-row" tabindex="0"
                              data-access-permission-row
                              data-kind="permission"
                              data-initial="{{.Initial}}"
                              data-key="{{.Key}}"
                              data-label="{{.Key}}"
                              data-subject="{{.Subject}}"
                              data-permission="{{.Permission}}"
                              data-boundary="{{.Boundary}}"
                              data-status="{{.StatusKey}}"
                              data-status-text="{{.Status}}"
                              data-status-class="{{.StatusClass}}"
                              data-description="{{.Permission}}"
                              data-filter-text="{{.Key}} {{.Subject}} {{.Permission}} {{.Boundary}} {{.Status}}">
                              <td>
                                <div class="access-claw-user">
                                  <span class="access-user-avatar">{{.Initial}}</span>
                                  <span>
                                    <strong class="mono">{{.Key}}</strong>
                                    <small>{{.Permission}}</small>
                                  </span>
                                </div>
                              </td>
                              <td><strong class="mono">{{.Subject}}</strong><small>{{.Boundary}}</small></td>
                              <td><span class="admin-badge {{.StatusClass}}">{{.Status}}</span></td>
                            </tr>
                          {{else}}
                            <tr>
                              <td class="access-user-empty" colspan="3">
                                <strong>暂无权限规则</strong>
                                <span>访问控制规则会出现在这里。</span>
                              </td>
                            </tr>
                          {{end}}
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>

                <aside class="access-claw-aside">
                  <div class="admin-panel access-claw-side-panel access-permission-side-panel">
                    <div class="admin-panel-head access-side-panel-head">
                      <div>
                        <h2 data-access-permission-panel-title>权限详情</h2>
                        <span class="admin-panel-meta" data-access-permission-panel-meta>Permissions</span>
                      </div>
                    </div>
                    <div class="access-side-view">
                      <div class="access-detail-empty" data-access-permission-empty>选择左侧菜单或权限规则查看详细边界</div>
                      <div class="access-detail-body" data-access-permission-body hidden>
                        <div class="access-detail-head">
                          <span class="access-user-avatar" data-access-permission-initial>P</span>
                          <div>
                            <h2 data-access-permission-name>权限项</h2>
                            <small class="mono" data-access-permission-key>-</small>
                            <small data-access-permission-subtitle>-</small>
                          </div>
                        </div>
                        <dl class="access-detail-kv">
                          <div><dt>状态</dt><dd><span class="admin-badge is-muted" data-access-permission-status-badge>-</span></dd></div>
                          <div><dt data-access-permission-scope-label>路径</dt><dd data-access-permission-scope-value>-</dd></div>
                          <div><dt data-access-permission-boundary-label>边界</dt><dd data-access-permission-boundary-value>-</dd></div>
                        </dl>
                        <div class="access-detail-actions">
                          <a href="{{.BasePath}}/users">查看管理员账号</a>
                          <a href="{{.BasePath}}/users/groups">查看用户组权限</a>
                        </div>
                      </div>
                    </div>
                  </div>
                </aside>
              </section>
            </div>
            {{end}}
          </section>
        {{else if eq .Active "settings"}}
          {{if .SettingsNotice}}
            <div class="admin-alert {{.SettingsNoticeClass}}">{{.SettingsNotice}}</div>
          {{end}}
          <section class="settings-hub" data-settings-hub>
            <aside class="settings-nav-panel" aria-label="系统设置分组">
              <div class="settings-nav-head">
                <strong>设置菜单</strong>
                <span>按模块维护系统配置</span>
              </div>
              <nav class="settings-menu">
                <button class="active" type="button" data-settings-tab="system">
                  基础信息
                  <span>站点资料、首页展示、语言与时区</span>
                </button>
                <button type="button" data-settings-tab="storage">
                  存储设置
                  <span>本地目录、上传限制、导出保留策略</span>
                </button>
                <button type="button" data-settings-tab="ai">
                  AI 模型
                  <span>百炼兼容接口、模型与 Key 检查</span>
                </button>
                <button type="button" data-settings-tab="security">
                  登录安全
                  <span>登录保护、后台入口和管理员密码</span>
                </button>
                <button type="button" data-settings-tab="notifications">
                  消息通知
                  <span>Webhook、飞书机器人和测试通知</span>
                </button>
                <button type="button" data-settings-tab="queue">
                  后台队列
                  <span>自动执行、定时体检和导出清理</span>
                </button>
                <button type="button" data-settings-tab="runtime">
                  运行状态
                  <span>运行参数与设置变更历史</span>
                </button>
              </nav>
            </aside>

            <div class="settings-content">
              <section class="settings-section is-active" id="settings-system" data-settings-section="system">
                <div class="settings-section-head">
                  <div>
                    <h2>基础信息</h2>
                    <p>管理站点展示、语言、时区和首页文案。</p>
                  </div>
                  <span class="admin-badge is-ready">System</span>
                </div>
                <div class="settings-section-grid">
            {{if not .CanManageSettings}}
              <p class="form-text admin-readonly-note">当前账号只有查看权限，不能修改基础信息、存储、AI、通知和自动队列设置。</p>
            {{end}}
            <div class="admin-panel">
              <div class="admin-panel-head">
                <h2>基础信息</h2>
                <span class="admin-panel-meta">System</span>
              </div>
              <form class="admin-settings-form" method="post" action="{{.SystemSettings.Action}}">
                <fieldset class="admin-form-fieldset" {{if not .CanManageSettings}}disabled{{end}}>
                  <div class="admin-form-grid">
                    <label>
                      <span>站点名称</span>
                      <input class="form-control" name="site_name" value="{{.SystemSettings.SiteName}}" autocomplete="off">
                    </label>
                    <label>
                      <span>默认语言</span>
                      <select class="form-select" name="locale">
                        <option value="zh-CN" {{if eq .SystemSettings.Locale "zh-CN"}}selected{{end}}>简体中文</option>
                        <option value="en-US" {{if eq .SystemSettings.Locale "en-US"}}selected{{end}}>English</option>
                      </select>
                    </label>
                    <label class="admin-form-wide">
                      <span>默认时区</span>
                      <input class="form-control" name="timezone" value="{{.SystemSettings.Timezone}}" placeholder="Asia/Shanghai" autocomplete="off">
                    </label>
                    <label class="admin-form-wide">
                      <span>后台副标题</span>
                      <input class="form-control" name="admin_tagline" value="{{.SystemSettings.AdminTagline}}" autocomplete="off">
                    </label>
                    <label class="admin-form-wide">
                      <span>首页副标题</span>
                      <input class="form-control" name="public_tagline" value="{{.SystemSettings.PublicTagline}}" autocomplete="off">
                    </label>
                    <label class="admin-form-wide">
                      <span>首页主标题</span>
                      <textarea class="form-control" name="public_headline" rows="2">{{.SystemSettings.PublicHeadline}}</textarea>
                    </label>
                    <label class="admin-form-wide">
                      <span>首页简介</span>
                      <textarea class="form-control" name="public_description" rows="3">{{.SystemSettings.PublicDescription}}</textarea>
                    </label>
                  </div>
                  <button class="admin-submit-button" type="submit">保存基础信息</button>
                </fieldset>
              </form>
            </div>
                </div>
              </section>

              <section class="settings-section" id="settings-storage" data-settings-section="storage">
                <div class="settings-section-head">
                  <div>
                    <h2>存储设置</h2>
                    <p>管理上传目录、公开访问前缀、文件限制和智能体导出保留。</p>
                  </div>
                  <span class="admin-badge {{.StorageSettings.PathStatusClass}}">{{.StorageSettings.PathStatus}}</span>
                </div>
                <div class="settings-section-grid">
            <div class="admin-panel">
              <div class="admin-panel-head">
                <h2>存储设置</h2>
                <span class="admin-badge {{.StorageSettings.PathStatusClass}}">{{.StorageSettings.PathStatus}}</span>
              </div>
              <form class="admin-settings-form" method="post" action="{{.StorageSettings.Action}}">
                <fieldset class="admin-form-fieldset" {{if not .CanManageSettings}}disabled{{end}}>
                  <div class="admin-form-grid">
                    <label>
                      <span>存储驱动</span>
                      <select class="form-select" name="storage_driver">
                        <option value="local" {{if eq .StorageSettings.Driver "local"}}selected{{end}}>本地文件系统</option>
                      </select>
                    </label>
                    <label>
                      <span>单文件大小 MB</span>
                      <input class="form-control" name="storage_max_file_size_mb" type="number" min="1" max="1024" value="{{.StorageSettings.MaxFileSizeMB}}">
                    </label>
                    <label class="admin-form-wide">
                      <span>本地存储目录</span>
                      <input class="form-control mono" name="storage_local_path" value="{{.StorageSettings.LocalPath}}" autocomplete="off">
                    </label>
                    <label class="admin-form-wide">
                      <span>公开访问前缀</span>
                      <input class="form-control mono" name="storage_public_url" value="{{.StorageSettings.PublicURL}}" placeholder="/uploads" autocomplete="off">
                    </label>
                    <label class="admin-form-wide">
                      <span>允许扩展名</span>
                      <input class="form-control mono" name="storage_allowed_extensions" value="{{.StorageSettings.AllowedExtensions}}" autocomplete="off">
                      <small class="form-text">{{.StorageSettings.AllowedDescription}}</small>
                    </label>
                    <label>
                      <span>导出保留天数</span>
                      <input class="form-control" name="storage_retention_days" type="number" min="1" max="365" value="{{.StorageSettings.RetentionDays}}">
                    </label>
                  </div>
                  <button class="admin-submit-button" type="submit">保存存储设置</button>
                </fieldset>
              </form>
            </div>
                </div>
              </section>

              <section class="settings-section" id="settings-ai" data-settings-section="ai">
                <div class="settings-section-head">
                  <div>
                    <h2>AI 模型</h2>
                    <p>配置后台智能体默认模型服务和连接检查。</p>
                  </div>
                  <span class="admin-badge {{.AIStatusClass}}">{{.AIStatus}}</span>
                </div>
                <div class="settings-section-grid">
            <div class="admin-panel">
              <div class="admin-panel-head">
                <h2>AI 模型设置</h2>
                <span class="admin-panel-meta">Bailian</span>
              </div>
              <form class="admin-settings-form" method="post" action="{{.AISettings.Action}}">
                <fieldset class="admin-form-fieldset" {{if not .CanManageSettings}}disabled{{end}}>
                  <div class="admin-form-grid">
                    <label>
                      <span>AI 服务商</span>
                      <select class="form-select" name="ai_provider">
                        <option value="bailian" {{if eq .AISettings.Provider "bailian"}}selected{{end}}>阿里云百炼</option>
                        <option value="disabled" {{if eq .AISettings.Provider "disabled"}}selected{{end}}>暂不启用</option>
                      </select>
                    </label>
                    <label>
                      <span>对话模型</span>
                      <input class="form-control mono" name="ai_chat_model" value="{{.AISettings.ChatModel}}" placeholder="qwen-plus" autocomplete="off">
                    </label>
                    <label>
                      <span>图片模型</span>
                      <input class="form-control mono" name="ai_image_model" value="{{.AISettings.ImageModel}}" placeholder="qwen-image-2.0-pro" autocomplete="off">
                    </label>
                    <label class="admin-form-wide">
                      <span>API Key</span>
                      <input class="form-control mono" name="ai_api_key" type="password" placeholder="{{if .AISettings.MaskedAPIKey}}{{.AISettings.MaskedAPIKey}}，留空保留{{else}}sk-...{{end}}" autocomplete="new-password">
                      <small class="form-text">保存时会调用模型接口检查；留空会保留已有 Key。</small>
                    </label>
                    <label class="admin-form-wide">
                      <span>OpenAI 兼容 Base URL</span>
                      <input class="form-control mono" name="ai_base_url" value="{{.AISettings.BaseURL}}" placeholder="https://dashscope.aliyuncs.com/compatible-mode/v1" autocomplete="off">
                    </label>
                  </div>
                  <button class="admin-submit-button" type="submit">保存并检查 AI</button>
                </fieldset>
              </form>
            </div>
                </div>
              </section>

              <section class="settings-section" id="settings-security" data-settings-section="security">
                <div class="settings-section-head">
                  <div>
                    <h2>登录安全</h2>
                    <p>管理登录保护、当前管理员密码和后台固定入口。</p>
                  </div>
                  <span class="admin-badge is-secure">Policy</span>
                </div>
                <div class="settings-section-grid">
            <div class="admin-panel">
              <div class="admin-panel-head">
                <h2>登录保护</h2>
                <span class="admin-panel-meta">Policy</span>
              </div>
              <form class="admin-settings-form" method="post" action="{{.SecuritySettings.Action}}">
                <input type="hidden" name="security_action" value="policy">
                <fieldset class="admin-form-fieldset" {{if not .CanManageSettings}}disabled{{end}}>
                  <div class="admin-form-grid">
                    <label>
                      <span>会话有效期 / 小时</span>
                      <input class="form-control" name="session_ttl_hours" type="number" min="1" max="168" value="{{.SecuritySettings.SessionTTLHours}}">
                    </label>
                    <label>
                      <span>失败次数阈值</span>
                      <input class="form-control" name="login_max_attempts" type="number" min="1" max="20" value="{{.SecuritySettings.LoginMaxAttempts}}">
                    </label>
                    <label class="admin-form-wide">
                      <span>锁定窗口 / 分钟</span>
                      <input class="form-control" name="login_lock_minutes" type="number" min="1" max="1440" value="{{.SecuritySettings.LoginLockMinutes}}">
                      <small class="form-text">同一管理员或同一 IP 在窗口内连续失败达到阈值后，会临时拒绝登录。</small>
                    </label>
                  </div>
                  <button class="admin-submit-button" type="submit">保存登录保护</button>
                </fieldset>
              </form>
            </div>

            <div class="admin-panel">
              <div class="admin-panel-head">
                <h2>安全设置</h2>
                <span class="admin-panel-meta">{{.SecuritySettings.Username}}</span>
              </div>
              <form class="admin-settings-form" method="post" action="{{.SecuritySettings.Action}}">
                <input type="hidden" name="security_action" value="password">
                <div class="admin-form-grid">
                  <label class="admin-form-wide">
                    <span>当前密码</span>
                    <input class="form-control mono" name="current_password" type="password" autocomplete="current-password">
                  </label>
                  <label>
                    <span>新密码</span>
                    <input class="form-control mono" name="new_password" type="password" autocomplete="new-password" placeholder="至少 6 位">
                  </label>
                  <label>
                    <span>确认新密码</span>
                    <input class="form-control mono" name="new_password_confirmation" type="password" autocomplete="new-password">
                  </label>
                  <label class="admin-form-wide">
                    <span>后台入口</span>
                    <input class="form-control mono" value="{{.SecuritySettings.Entry}}" readonly>
                    <small class="form-text">后台入口在初始化时随机生成，之后保持固定，避免影响已保存的管理地址。</small>
                  </label>
                </div>
                <button class="admin-submit-button" type="submit">更新密码</button>
              </form>
            </div>
                </div>
              </section>

              <section class="settings-section" id="settings-notifications" data-settings-section="notifications">
                <div class="settings-section-head">
                  <div>
                    <h2>消息通知</h2>
                    <p>配置消息通道、接收人、机器人地址和通知事件。</p>
                  </div>
                  <span class="admin-panel-meta">{{.NotificationSettings.ChannelName}}</span>
                </div>
                <div class="settings-section-grid">
            <div class="admin-panel">
              <div class="admin-panel-head">
                <h2>通知设置</h2>
                <span class="admin-panel-meta">{{.NotificationSettings.ChannelName}}</span>
              </div>
              <form class="admin-settings-form" method="post" action="{{.NotificationSettings.Action}}">
                <fieldset class="admin-form-fieldset" {{if not .CanManageSettings}}disabled{{end}}>
                  <div class="admin-form-grid">
                    <label>
                      <span>通知状态</span>
                      <select class="form-select" name="notification_enabled">
                        <option value="0" {{if not .NotificationSettings.Enabled}}selected{{end}}>暂不启用</option>
                        <option value="1" {{if .NotificationSettings.Enabled}}selected{{end}}>启用</option>
                      </select>
                    </label>
                    <label>
                      <span>通知通道</span>
                      <select class="form-select" name="notification_channel">
                        <option value="disabled" {{if eq .NotificationSettings.Channel "disabled"}}selected{{end}}>暂不启用</option>
                        <option value="webhook" {{if eq .NotificationSettings.Channel "webhook"}}selected{{end}}>Webhook</option>
                        <option value="feishu" {{if eq .NotificationSettings.Channel "feishu"}}selected{{end}}>飞书机器人</option>
                      </select>
                    </label>
                    <label class="admin-form-wide">
                      <span>接收人 / 备注</span>
                      <input class="form-control" name="notification_receiver" value="{{.NotificationSettings.Receiver}}" placeholder="运维群、管理员、值班人" autocomplete="off">
                    </label>
                    <label class="admin-form-wide">
                      <span>Webhook / 机器人地址</span>
                      <input class="form-control mono" name="notification_webhook_url" value="{{.NotificationSettings.WebhookURL}}" placeholder="https://open.feishu.cn/open-apis/bot/v2/hook/..." autocomplete="off">
                      <small class="form-text">普通 Webhook 会发送原始 JSON；飞书机器人会发送飞书 text 消息体。</small>
                    </label>
                    <label class="admin-form-wide">
                      <span>飞书签名密钥</span>
                      <input class="form-control mono" name="notification_feishu_secret" type="password" placeholder="{{if .NotificationSettings.FeishuSecretMasked}}{{.NotificationSettings.FeishuSecretMasked}}，留空保留{{else}}可选，开启签名校验时填写{{end}}" autocomplete="new-password">
                      <small class="form-text">飞书机器人启用“签名校验”时填写；未启用签名校验可留空。</small>
                    </label>
                    <label class="admin-checkline">
                      <input type="checkbox" name="notification_event_login_failures" value="1" {{if .NotificationSettings.EventLoginFailures}}checked{{end}}>
                      <span>登录失败与锁定</span>
                    </label>
                    <label class="admin-checkline">
                      <input type="checkbox" name="notification_event_ai_errors" value="1" {{if .NotificationSettings.EventAIErrors}}checked{{end}}>
                      <span>AI 调用异常</span>
                    </label>
                    <label class="admin-checkline admin-form-wide">
                      <input type="checkbox" name="notification_event_storage_warning" value="1" {{if .NotificationSettings.EventStorageWarning}}checked{{end}}>
                      <span>存储与文件风险</span>
                    </label>
                  </div>
                  <div class="admin-button-row">
                    <button class="admin-submit-button" type="submit">保存通知设置</button>
                  </div>
                </fieldset>
              </form>
              {{if .CanManageSettings}}
                <form class="admin-inline-form" method="post" action="{{.NotificationSettings.TestAction}}">
                  <button class="admin-secondary-button" type="submit">发送测试通知</button>
                </form>
              {{end}}
            </div>
                </div>
              </section>

              <section class="settings-section" id="settings-queue" data-settings-section="queue">
                <div class="settings-section-head">
                  <div>
                    <h2>后台队列</h2>
                    <p>管理自动执行队列、定时系统体检和智能体导出清理。</p>
                  </div>
                  <span class="admin-panel-meta">{{.TaskWorkerSettings.StatusText}}</span>
                </div>
                <div class="settings-section-grid">
            <div class="admin-panel">
              <div class="admin-panel-head">
                <h2>后台自动队列</h2>
                <span class="admin-panel-meta">{{.TaskWorkerSettings.StatusText}}</span>
              </div>
              <form class="admin-settings-form" method="post" action="{{.BasePath}}/settings/tasks">
                <fieldset class="admin-form-fieldset" {{if not .CanManageSettings}}disabled{{end}}>
                  <div class="admin-form-grid">
                    <label>
                      <span>自动执行</span>
                      <select class="form-select" name="task_worker_enabled">
                        <option value="1" {{if .TaskWorkerSettings.Enabled}}selected{{end}}>开启自动队列</option>
                        <option value="0" {{if not .TaskWorkerSettings.Enabled}}selected{{end}}>关闭自动队列</option>
                      </select>
                    </label>
                    <label>
                      <span>扫描间隔 / 秒</span>
                      <input class="form-control" name="task_worker_interval_seconds" type="number" min="5" max="3600" value="{{.TaskWorkerSettings.IntervalSeconds}}">
                    </label>
                    <label>
                      <span>单轮任务数</span>
                      <input class="form-control" name="task_worker_batch_size" type="number" min="1" max="50" value="{{.TaskWorkerSettings.BatchSize}}">
                    </label>
                    <label>
                      <span>系统体检调度</span>
                      <select class="form-select" name="task_schedule_health_enabled">
                        <option value="1" {{if .TaskWorkerSettings.ScheduleHealthEnabled}}selected{{end}}>启用</option>
                        <option value="0" {{if not .TaskWorkerSettings.ScheduleHealthEnabled}}selected{{end}}>关闭</option>
                      </select>
                    </label>
                    <label>
                      <span>体检间隔 / 分钟</span>
                      <input class="form-control" name="task_schedule_health_minutes" type="number" min="5" max="10080" value="{{.TaskWorkerSettings.ScheduleHealthMinutes}}">
                    </label>
                    <label>
                      <span>导出清理调度</span>
                      <select class="form-select" name="task_schedule_cleanup_enabled">
                        <option value="1" {{if .TaskWorkerSettings.ScheduleCleanupEnabled}}selected{{end}}>启用</option>
                        <option value="0" {{if not .TaskWorkerSettings.ScheduleCleanupEnabled}}selected{{end}}>关闭</option>
                      </select>
                    </label>
                    <label>
                      <span>清理间隔 / 分钟</span>
                      <input class="form-control" name="task_schedule_cleanup_minutes" type="number" min="5" max="10080" value="{{.TaskWorkerSettings.ScheduleCleanupMinutes}}">
                    </label>
                    <label class="admin-form-wide">
                      <span>队列说明</span>
                      <input class="form-control" value="自动队列会在 Go 服务内扫描待执行任务，不会启动额外端口。" readonly>
                    </label>
                  </div>
                  <button class="admin-submit-button" type="submit">保存自动队列设置</button>
                </fieldset>
              </form>
            </div>
                </div>
              </section>

              <section class="settings-section" id="settings-runtime" data-settings-section="runtime">
                <div class="settings-section-head">
                  <div>
                    <h2>运行状态</h2>
                    <p>查看当前运行参数、固定入口和设置变更记录。</p>
                  </div>
                  <a class="admin-panel-link" href="{{.BasePath}}/tasks">进入后台任务</a>
                </div>
                <div class="settings-section-grid">
            <div class="admin-panel admin-panel-wide">
              <div class="admin-panel-head">
                <h2>运行参数</h2>
                <a class="admin-panel-link" href="{{.BasePath}}/tasks">进入后台任务</a>
              </div>
              <dl class="admin-kv admin-kv-table">
                {{range .Settings}}
                  <div><dt>{{.Key}}</dt><dd>{{.Value}}</dd></div>
                {{end}}
              </dl>
            </div>

            <div class="admin-panel admin-panel-wide">
              <div class="admin-panel-head">
                <h2>设置变更记录</h2>
                <span class="admin-panel-meta">History</span>
              </div>
              <div class="admin-table setting-change-table">
                <div class="admin-table-row admin-table-head">
                  <span>时间</span><span>分组</span><span>动作</span><span>操作者</span><span>摘要</span>
                </div>
                {{if .SettingChanges}}
                  {{range .SettingChanges}}
                    <div class="admin-table-row">
                      <span>{{.Time}}</span><span>{{.Category}}</span><span>{{.Action}}</span><span>{{.Actor}}</span><span>{{.Summary}}</span>
                    </div>
                  {{end}}
                {{else}}
                  <div class="admin-table-row">
                    <span>暂无变更记录</span><span>-</span><span>-</span><span>-</span><span>保存任一设置后会写入这里。</span>
                  </div>
                {{end}}
              </div>
            </div>
                </div>
              </section>
            </div>
          </section>
        {{else if eq .Active "files"}}
          {{if .FileNotice}}
            <div class="admin-alert {{.FileNoticeClass}}">{{.FileNotice}}</div>
          {{end}}
          <div class="file-manager-page access-claw-page" data-file-manager>
            <section class="access-claw-metrics" aria-label="文件管理概览">
              {{range .FileMetrics}}
                <article class="access-claw-metric">
                  <span>{{.Label}}</span>
                  <strong>{{.Value}}</strong>
                  <small>{{.Detail}}</small>
                  <i class="admin-status-dot {{.Status}}"></i>
                </article>
              {{end}}
            </section>

            <form class="access-claw-filters" data-file-filters>
              <input class="form-control" type="search" placeholder="搜索文件名、路径或类型" autocomplete="off" data-file-search>
              <select class="form-select" data-file-kind>
                <option value="">全部类型</option>
                <option value="image">图片</option>
                <option value="pdf">PDF</option>
                <option value="spreadsheet">表格</option>
                <option value="text">文本</option>
                <option value="other">其他</option>
              </select>
              <a class="admin-panel-link is-button" href="{{.BasePath}}/settings">存储设置</a>
              <span class="access-claw-filter-spacer"></span>
              {{if .CanManageFiles}}
                <button class="admin-panel-link is-button is-primary" type="button" data-file-create-toggle aria-expanded="false">上传文件</button>
              {{end}}
              <a class="admin-panel-link is-button" href="{{.BasePath}}/files">刷新列表</a>
            </form>
            {{if not .CanManageFiles}}
              <p class="form-text admin-readonly-note">当前账号可以预览和下载文件，但不能上传或删除文件。</p>
            {{end}}

            <section class="access-claw-grid">
              <div class="admin-panel access-claw-table-card">
                <div class="access-claw-table-head">
                  <div>
                    <h2>文件列表</h2>
                    <span>匹配 <b data-file-visible-count>{{len .FileRows}}</b> / 全部 {{len .FileRows}} 个文件</span>
                  </div>
                  <span class="admin-panel-meta">Local Files</span>
                </div>
                <div class="access-claw-table-wrap">
                  <table class="access-claw-table file-manager-list-table">
                    <thead>
                      <tr>
                        <th>文件</th>
                        <th>类型</th>
                        <th>大小</th>
                        <th>更新时间</th>
                      </tr>
                    </thead>
                    <tbody>
                      {{if .FileRows}}
                        {{range .FileRows}}
                          <tr class="file-manager-row" tabindex="0"
                            data-file-row
                            data-initial="{{.Initial}}"
                            data-name="{{.Name}}"
                            data-path="{{.Path}}"
                            data-kind="{{.KindKey}}"
                            data-kind-text="{{.Kind}}"
                            data-size="{{.Size}}"
                            data-modified="{{.Modified}}"
                            data-status="{{.StatusKey}}"
                            data-status-text="{{.Status}}"
                            data-status-class="{{.StatusClass}}"
                            data-preview-url="{{.PreviewURL}}"
                            data-download-url="{{.DownloadURL}}"
                            data-filter-text="{{.Name}} {{.Path}} {{.Kind}}">
                            <td>
                              <div class="access-claw-user">
                                <span class="access-user-avatar">{{.Initial}}</span>
                                <span>
                                  <strong>{{.Name}}</strong>
                                  <small class="mono">{{.Path}}</small>
                                </span>
                              </div>
                            </td>
                            <td><strong>{{.Kind}}</strong><small>本地存储文件</small></td>
                            <td><strong>{{.Size}}</strong><small>{{.Status}}</small></td>
                            <td><strong>{{.Modified}}</strong><small>可预览、下载、删除</small></td>
                          </tr>
                        {{end}}
                      {{else}}
                        <tr>
                          <td class="access-user-empty" colspan="4">
                            <strong>暂无文件</strong>
                            <span>上传文件后，这里会自动展示本地存储中的文件清单。</span>
                          </td>
                        </tr>
                      {{end}}
                    </tbody>
                  </table>
                </div>
              </div>

              <aside class="access-claw-aside">
                <div class="admin-panel access-claw-side-panel" data-file-detail-panel>
                  <div class="admin-panel-head access-side-panel-head">
                    <div>
                      <h2>文件详情</h2>
                      <span class="admin-panel-meta">Preview</span>
                    </div>
                    <a class="admin-panel-link is-button" href="{{.BasePath}}/files">刷新</a>
                  </div>
                  <div class="access-side-view">
                    <div class="access-detail-empty" data-file-empty>从左侧选择文件后，可以直接预览、下载或删除</div>
                    <div class="access-detail-body" data-file-body hidden>
                      <div class="access-detail-head">
                        <span class="access-user-avatar" data-file-initial>F</span>
                        <div>
                          <h2 data-file-name>文件</h2>
                          <small class="mono" data-file-path>-</small>
                        </div>
                      </div>
                      <dl class="access-detail-kv">
                        <div><dt>类型</dt><dd data-file-kind-text>-</dd></div>
                        <div><dt>大小</dt><dd data-file-size>-</dd></div>
                        <div><dt>更新时间</dt><dd data-file-modified>-</dd></div>
                        <div><dt>状态</dt><dd><span class="admin-badge is-muted" data-file-status-badge>-</span></dd></div>
                      </dl>
                      <div class="access-detail-actions">
                        <a href="#" target="_blank" rel="noreferrer" data-file-preview>预览文件</a>
                        <a href="#" data-file-download>下载文件</a>
                        {{if .CanManageFiles}}
                          <form method="post" action="{{.BasePath}}/files/delete" data-file-delete>
                            <input type="hidden" name="path" value="" data-file-delete-path>
                            <button class="admin-panel-link is-button" type="submit">删除文件</button>
                          </form>
                        {{else}}
                          <span class="admin-badge is-muted">当前账号只有查看权限</span>
                        {{end}}
                      </div>
                    </div>
                  </div>
                </div>

                {{if .CanManageFiles}}
                <div class="admin-panel access-claw-side-panel" data-file-create-panel hidden>
                  <div class="admin-panel-head access-side-panel-head">
                    <div>
                      <h2>上传文件</h2>
                      <span class="admin-panel-meta">{{.FileAllowedSummary}}</span>
                    </div>
                    <button class="admin-panel-link is-button" type="button" data-file-create-cancel>收起</button>
                  </div>
                  <div class="access-create-view">
                    <form class="admin-settings-form" method="post" action="{{.FileUploadAction}}" enctype="multipart/form-data">
                      <label class="admin-upload-box">
                        <span>选择文件</span>
                        <input name="files" type="file" multiple>
                        <small>文件会写入当前本地存储目录，并按日期自动分组。</small>
                      </label>
                      <button class="admin-submit-button" type="submit">上传文件</button>
                    </form>
                    <dl class="admin-kv">
                      <div><dt>存储</dt><dd>{{.FileStorageSummary}}</dd></div>
                      <div><dt>限制</dt><dd>{{.FileAllowedSummary}}</dd></div>
                      <div><dt>管理</dt><dd>支持上传、预览、下载和删除本地文件</dd></div>
                    </dl>
                  </div>
                </div>
                {{end}}
              </aside>
            </section>
          </div>
        {{else if eq .Active "tasks"}}
          {{if .TaskNotice}}
            <div class="admin-alert {{.TaskNoticeClass}}">{{.TaskNotice}}</div>
          {{end}}
          <div class="task-ops-page access-claw-page" data-task-ops>
            <section class="access-claw-metrics" aria-label="后台任务概览">
              {{range .TaskMetrics}}
                <article class="access-claw-metric">
                  <span>{{.Label}}</span>
                  <strong>{{.Value}}</strong>
                  <small>{{.Detail}}</small>
                  <i class="admin-status-dot {{.Status}}"></i>
                </article>
              {{end}}
            </section>

            <form class="access-claw-filters" data-task-filters>
              <input class="form-control" type="search" placeholder="搜索任务、队列、执行人或结果" autocomplete="off" data-task-search>
              <select class="form-select" data-task-status>
                <option value="">全部状态</option>
                <option value="pending">待执行</option>
                <option value="retry">等待重试</option>
                <option value="running">执行中</option>
                <option value="succeeded">已完成</option>
                <option value="failed">失败</option>
                <option value="canceled">已取消</option>
              </select>
              <select class="form-select" data-task-type>
                <option value="">全部任务</option>
                {{range .TaskTypeOptions}}
                  <option value="{{.Type}}">{{.Name}}</option>
                {{end}}
              </select>
              <a class="admin-panel-link is-button" href="{{.BasePath}}/settings">系统设置</a>
              <span class="access-claw-filter-spacer"></span>
              {{if .CanManageTasks}}
                <button class="admin-panel-link is-button is-primary" type="button" data-task-create-toggle aria-expanded="false">创建任务</button>
              {{end}}
              <a class="admin-panel-link is-button" href="{{.BasePath}}/tasks">刷新列表</a>
            </form>
            {{if not .CanManageTasks}}
              <p class="form-text admin-readonly-note">当前账号可以查看任务队列和日志，但不能创建、执行、重试或取消任务。</p>
            {{end}}

            <section class="access-claw-grid">
              <div class="access-claw-stack">
                <div class="admin-panel access-claw-table-card">
                  <div class="access-claw-table-head">
                    <div>
                      <h2>任务队列</h2>
                      <span>匹配 <b data-task-visible-count>{{len .TaskRows}}</b> / 全部 {{len .TaskRows}} 条任务</span>
                    </div>
                    <span class="admin-panel-meta">Background Tasks</span>
                  </div>
                  <div class="access-claw-table-wrap">
                    <table class="access-claw-table task-list-table">
                      <thead>
                        <tr>
                          <th>任务</th>
                          <th>队列</th>
                          <th>状态</th>
                          <th>时间</th>
                          <th>结果摘要</th>
                        </tr>
                      </thead>
                      <tbody>
                        {{if .TaskRows}}
                          {{range .TaskRows}}
                            <tr class="task-row" tabindex="0"
                              data-task-row
                              data-initial="{{.Initial}}"
                              data-id="{{.ID}}"
                              data-id-short="{{.IDShort}}"
                              data-name="{{.Name}}"
                              data-type="{{.Type}}"
                              data-type-name="{{.TypeName}}"
                              data-queue="{{.Queue}}"
                              data-status="{{.StatusKey}}"
                              data-status-text="{{.Status}}"
                              data-status-class="{{.StatusClass}}"
                              data-attempts="{{.Attempts}}"
                              data-created-by="{{.CreatedBy}}"
                              data-created-at="{{.CreatedAt}}"
                              data-available-at="{{.AvailableAt}}"
                              data-started-at="{{.StartedAt}}"
                              data-finished-at="{{.FinishedAt}}"
                              data-result="{{.Result}}"
                              data-last-error="{{.LastError}}"
                              data-can-run="{{if .CanRun}}true{{else}}false{{end}}"
                              data-can-retry="{{if .CanRetry}}true{{else}}false{{end}}"
                              data-can-cancel="{{if .CanCancel}}true{{else}}false{{end}}"
                              data-filter-text="{{.Name}} {{.TypeName}} {{.Queue}} {{.CreatedBy}} {{.Result}} {{.LastError}}">
                              <td>
                                <div class="access-claw-user">
                                  <span class="access-user-avatar">{{.Initial}}</span>
                                  <span>
                                    <strong>{{.Name}}</strong>
                                    <small class="mono">{{.IDShort}} · {{.TypeName}}</small>
                                  </span>
                                </div>
                              </td>
                              <td><strong>{{.Queue}}</strong><small>尝试 {{.Attempts}}</small></td>
                              <td><span class="admin-badge {{.StatusClass}}">{{.Status}}</span><small>{{if .LastError}}{{.LastError}}{{else}}当前任务状态{{end}}</small></td>
                              <td><strong>创建 {{.CreatedAt}}</strong><small>可执行 {{.AvailableAt}}{{if .FinishedAt}} · 完成 {{.FinishedAt}}{{end}}</small></td>
                              <td><strong>{{if .Result}}{{.Result}}{{else}}等待执行结果{{end}}</strong><small>{{.CreatedBy}}</small></td>
                            </tr>
                          {{end}}
                        {{else}}
                          <tr>
                            <td class="access-user-empty" colspan="5">
                              <strong>暂无任务</strong>
                              <span>先创建一个系统体检任务，队列和日志就会开始累积。</span>
                            </td>
                          </tr>
                        {{end}}
                      </tbody>
                    </table>
                  </div>
                </div>

                <div class="admin-panel access-claw-table-card">
                  <div class="access-claw-table-head">
                    <div>
                      <h2>任务日志</h2>
                      <span>展示 <b data-task-log-visible-count>{{len .TaskLogRows}}</b> / 全部 {{len .TaskLogRows}} 条生命周期日志</span>
                    </div>
                    <span class="admin-panel-meta" data-task-log-meta>Lifecycle Logs</span>
                  </div>
                  <div class="access-claw-table-wrap">
                    <table class="access-claw-table task-log-list-table">
                      <thead>
                        <tr>
                          <th>时间 / 任务</th>
                          <th>事件</th>
                          <th>状态</th>
                          <th>尝试</th>
                          <th>消息</th>
                        </tr>
                      </thead>
                      <tbody>
                        {{if .TaskLogRows}}
                          {{range .TaskLogRows}}
                            <tr class="task-log-row"
                              data-task-log-row
                              data-task-id="{{.TaskID}}"
                              data-status="{{.Status}}"
                              data-filter-text="{{.TaskIDShort}} {{.Event}} {{.Status}} {{.Message}} {{.Level}}">
                              <td><strong>{{.Time}}</strong><small class="mono">{{.TaskIDShort}}</small></td>
                              <td><span class="admin-badge {{.LevelClass}}">{{.Level}}</span><small>{{.Event}}</small></td>
                              <td><strong>{{.Status}}</strong><small>任务生命周期状态</small></td>
                              <td><strong>{{.Attempt}}</strong><small>第 {{.Attempt}} 次尝试</small></td>
                              <td><strong>{{.Message}}</strong><small>后台执行日志</small></td>
                            </tr>
                          {{end}}
                        {{else}}
                          <tr>
                            <td class="access-user-empty" colspan="5">
                              <strong>暂无任务日志</strong>
                              <span>任务入队、执行或失败后，这里会自动出现对应的生命周期记录。</span>
                            </td>
                          </tr>
                        {{end}}
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>

              <aside class="access-claw-aside">
                <div class="admin-panel access-claw-side-panel" data-task-detail-panel>
                  <div class="admin-panel-head access-side-panel-head">
                    <div>
                      <h2>任务详情</h2>
                      <span class="admin-panel-meta">Queue Item</span>
                    </div>
                    <a class="admin-panel-link is-button" href="{{.BasePath}}/tasks">刷新</a>
                  </div>
                  <div class="access-side-view">
                    <div class="access-detail-empty" data-task-empty>选择左侧任务查看状态、结果摘要和可执行动作</div>
                    <div class="access-detail-body" data-task-body hidden>
                      <div class="access-detail-head">
                        <span class="access-user-avatar" data-task-initial>T</span>
                        <div>
                          <h2 data-task-name>任务</h2>
                          <small class="mono" data-task-id>-</small>
                        </div>
                      </div>
                      <dl class="access-detail-kv">
                        <div><dt>任务类型</dt><dd data-task-type-name>-</dd></div>
                        <div><dt>队列</dt><dd data-task-queue>-</dd></div>
                        <div><dt>状态</dt><dd><span class="admin-badge is-muted" data-task-status-badge>-</span></dd></div>
                        <div><dt>尝试次数</dt><dd data-task-attempts>-</dd></div>
                        <div><dt>发起人</dt><dd data-task-created-by>-</dd></div>
                        <div><dt>创建时间</dt><dd data-task-created-at>-</dd></div>
                        <div><dt>可执行时间</dt><dd data-task-available-at>-</dd></div>
                        <div><dt>开始时间</dt><dd data-task-started-at>-</dd></div>
                        <div><dt>完成时间</dt><dd data-task-finished-at>-</dd></div>
                        <div><dt>执行结果</dt><dd data-task-result>-</dd></div>
                        <div><dt>最近错误</dt><dd data-task-last-error>-</dd></div>
                      </dl>
                      <div class="access-detail-actions">
                        {{if .CanManageTasks}}
                          <form method="post" action="{{.TaskRunAction}}" data-task-run hidden>
                            <input type="hidden" name="task_id" value="" data-task-run-id>
                            <button class="admin-panel-link is-button" type="submit">执行当前任务</button>
                          </form>
                          <form method="post" action="{{.BasePath}}/tasks/retry" data-task-retry hidden>
                            <input type="hidden" name="task_id" value="" data-task-retry-id>
                            <button class="admin-panel-link is-button" type="submit">重试当前任务</button>
                          </form>
                          <form method="post" action="{{.BasePath}}/tasks/cancel" data-task-cancel hidden>
                            <input type="hidden" name="task_id" value="" data-task-cancel-id>
                            <button class="admin-panel-link is-button" type="submit">取消当前任务</button>
                          </form>
                          <span class="admin-badge is-muted" data-task-no-action hidden>当前任务没有可执行操作</span>
                        {{else}}
                          <span class="admin-badge is-muted">当前账号只有查看权限</span>
                        {{end}}
                      </div>
                    </div>
                  </div>
                </div>

                {{if .CanManageTasks}}
                <div class="admin-panel access-claw-side-panel" data-task-create-panel hidden>
                  <div class="admin-panel-head access-side-panel-head">
                    <div>
                      <h2>队列操作</h2>
                      <span class="admin-panel-meta">Queue</span>
                    </div>
                    <button class="admin-panel-link is-button" type="button" data-task-create-cancel>收起</button>
                  </div>
                  <div class="access-create-view">
                    <form class="admin-settings-form" method="post" action="{{.TaskEnqueueAction}}">
                      <div class="admin-form-grid">
                        <label class="admin-form-wide">
                          <span>任务类型</span>
                          <select class="form-select" name="task_type">
                            {{range .TaskTypeOptions}}
                              <option value="{{.Type}}">{{.Name}} - {{.Description}}</option>
                            {{end}}
                          </select>
                        </label>
                      </div>
                      <button class="admin-submit-button" type="submit">加入队列</button>
                    </form>
                    <dl class="admin-kv">
                      <div><dt>运行方式</dt><dd>支持手动执行单个任务或批量执行当前可运行队列。</dd></div>
                      <div><dt>失败处理</dt><dd>失败任务会记录日志，并按最大次数进入重试链路。</dd></div>
                      <div><dt>任务边界</dt><dd>系统体检、导出清理、测试通知都会落库记录执行结果。</dd></div>
                    </dl>
                    <div class="admin-button-row">
                      <form class="admin-inline-form" method="post" action="{{.TaskRunAction}}">
                        <button class="admin-submit-button" type="submit">执行下一个任务</button>
                      </form>
                      <form class="admin-inline-form" method="post" action="{{.TaskRunAllAction}}">
                        <button class="admin-secondary-button" type="submit">批量执行就绪任务</button>
                      </form>
                    </div>
                  </div>
                </div>
                {{end}}

                <div class="admin-panel access-claw-side-panel">
                  <div class="admin-panel-head">
                    <h2>自动执行</h2>
                    <span class="admin-panel-meta">{{.TaskWorkerSettings.StatusText}}</span>
                  </div>
                  <div class="access-create-view">
                    {{if not .CanManageSettings}}
                      <p class="form-text admin-readonly-note">自动执行属于系统设置权限，当前账号只能查看，不能修改。</p>
                    {{end}}
                    <form class="admin-settings-form" method="post" action="{{.TaskWorkerSettings.Action}}">
                      <fieldset class="admin-form-fieldset" {{if not .CanManageSettings}}disabled{{end}}>
                        <div class="admin-form-grid">
                          <label>
                            <span>Worker 状态</span>
                            <select class="form-select" name="task_worker_enabled">
                              <option value="0" {{if not .TaskWorkerSettings.Enabled}}selected{{end}}>关闭自动执行</option>
                              <option value="1" {{if .TaskWorkerSettings.Enabled}}selected{{end}}>开启自动执行</option>
                            </select>
                          </label>
                          <label>
                            <span>扫描间隔（秒）</span>
                            <input class="form-control" name="task_worker_interval_seconds" type="number" min="5" max="3600" value="{{.TaskWorkerSettings.IntervalSeconds}}">
                          </label>
                          <label>
                            <span>单轮执行数量</span>
                            <input class="form-control" name="task_worker_batch_size" type="number" min="1" max="50" value="{{.TaskWorkerSettings.BatchSize}}">
                          </label>
                          <label>
                            <span>系统体检调度</span>
                            <select class="form-select" name="task_schedule_health_enabled">
                              <option value="1" {{if .TaskWorkerSettings.ScheduleHealthEnabled}}selected{{end}}>启用</option>
                              <option value="0" {{if not .TaskWorkerSettings.ScheduleHealthEnabled}}selected{{end}}>关闭</option>
                            </select>
                          </label>
                          <label>
                            <span>体检间隔（分钟）</span>
                            <input class="form-control" name="task_schedule_health_minutes" type="number" min="5" max="10080" value="{{.TaskWorkerSettings.ScheduleHealthMinutes}}">
                          </label>
                          <label>
                            <span>导出清理调度</span>
                            <select class="form-select" name="task_schedule_cleanup_enabled">
                              <option value="1" {{if .TaskWorkerSettings.ScheduleCleanupEnabled}}selected{{end}}>启用</option>
                              <option value="0" {{if not .TaskWorkerSettings.ScheduleCleanupEnabled}}selected{{end}}>关闭</option>
                            </select>
                          </label>
                          <label>
                            <span>清理间隔（分钟）</span>
                            <input class="form-control" name="task_schedule_cleanup_minutes" type="number" min="5" max="10080" value="{{.TaskWorkerSettings.ScheduleCleanupMinutes}}">
                          </label>
                        </div>
                        <button class="admin-submit-button" type="submit">保存自动执行设置</button>
                      </fieldset>
                    </form>
                  </div>
                </div>
              </aside>
            </section>
          </div>
        {{else if eq .Active "notifications"}}
          <section class="admin-metrics" aria-label="通知概览">
            {{range .NotificationMetrics}}
              <article class="admin-metric-card">
                <span class="metric-label">{{.Label}}</span>
                <strong>{{.Value}}</strong>
                <small>{{.Detail}}</small>
                <i class="admin-status-dot {{.Status}}"></i>
              </article>
            {{end}}
          </section>

          <section class="admin-panel admin-panel-wide">
            <div class="admin-panel-head">
              <h2>通知事件</h2>
              <a class="admin-panel-link" href="{{.BasePath}}/settings">通知设置</a>
            </div>
            <div class="admin-table notification-table">
              <div class="admin-table-row admin-table-head">
                <span>时间 / 事件</span><span>接收人</span><span>通道</span><span>目标</span><span>结果</span>
              </div>
              {{if .NotificationRows}}
                {{range .NotificationRows}}
                  <div class="admin-table-row">
                    <span><strong>{{.Title}}</strong><small>{{.Time}} · {{.Event}}</small>{{if .Message}}<small>{{.Message}}</small>{{end}}</span>
                    <span>{{.Receiver}}</span>
                    <span>{{.Channel}}</span>
                    <span class="mono">{{.Target}}</span>
                    <span><b class="admin-badge {{.StatusClass}}">{{.Status}}</b>{{if .Error}}<small>{{.Error}}</small>{{end}}</span>
                  </div>
                {{end}}
              {{else}}
                <div class="admin-table-row admin-empty-row">
                  <span>暂无通知发送记录。</span><span>-</span><span>-</span><span>-</span><span class="admin-badge is-muted">等待事件</span>
                </div>
              {{end}}
            </div>
          </section>
        {{else if eq .Active "audit"}}
          <section class="admin-metrics" aria-label="审计概览">
            {{range .AuditMetrics}}
              <article class="admin-metric-card">
                <span class="metric-label">{{.Label}}</span>
                <strong>{{.Value}}</strong>
                <small>{{.Detail}}</small>
                <i class="admin-status-dot {{.Status}}"></i>
              </article>
            {{end}}
          </section>

          <section class="admin-panel admin-panel-wide">
            <div class="admin-panel-head">
              <h2>审计事件</h2>
              <a class="admin-panel-link" href="{{.AuditFilter.ExportURL}}">导出 CSV</a>
            </div>
            <form class="admin-settings-form audit-filter-form" method="get" action="{{.AuditFilter.Action}}">
              <div class="admin-form-grid">
                <label>
                  <span>事件分类</span>
                  <select class="form-select" name="category">
                    <option value="" {{if eq .AuditFilter.Category ""}}selected{{end}}>全部分类</option>
                    <option value="login" {{if eq .AuditFilter.Category "login"}}selected{{end}}>登录</option>
                    <option value="operation" {{if eq .AuditFilter.Category "operation"}}selected{{end}}>操作</option>
                    <option value="file" {{if eq .AuditFilter.Category "file"}}selected{{end}}>文件</option>
                    <option value="ai" {{if eq .AuditFilter.Category "ai"}}selected{{end}}>AI</option>
                    <option value="system" {{if eq .AuditFilter.Category "system"}}selected{{end}}>系统</option>
                  </select>
                </label>
                <label>
                  <span>状态</span>
                  <select class="form-select" name="status">
                    <option value="" {{if eq .AuditFilter.Status ""}}selected{{end}}>全部状态</option>
                    <option value="success" {{if eq .AuditFilter.Status "success"}}selected{{end}}>成功 2xx/3xx</option>
                    <option value="warning" {{if eq .AuditFilter.Status "warning"}}selected{{end}}>警告 4xx</option>
                    <option value="error" {{if eq .AuditFilter.Status "error"}}selected{{end}}>错误 5xx</option>
                  </select>
                </label>
                <label class="admin-form-wide">
                  <span>关键词</span>
                  <input class="form-control" name="keyword" value="{{.AuditFilter.Keyword}}" placeholder="操作、路径、账号、IP" autocomplete="off">
                </label>
              </div>
              <div class="admin-filter-actions">
                <button class="admin-submit-button" type="submit">筛选日志</button>
                <a class="admin-filter-reset" href="{{.AuditFilter.Action}}">重置</a>
              </div>
            </form>
            <div class="admin-timeline">
              {{if .AuditEvents}}
                {{range .AuditEvents}}
                  <article>
                    <time>{{.Time}} · {{.Category}}</time>
                    <strong>{{.Action}}</strong>
                    <span>{{.Meta}} <b class="admin-badge {{.StatusClass}}">{{.Status}}</b></span>
                    <p>{{.Detail}}</p>
                  </article>
                {{end}}
              {{else}}
                <article>
                  <time>-</time>
                  <strong>暂无审计事件</strong>
                  <span>system</span>
                  <p>完成登录、设置、文件或 AI 操作后会记录到这里。</p>
                </article>
              {{end}}
            </div>
          </section>
        {{end}}
      </main>
    </div>
  </div>
  <script src="/assets/js/admin-agent.js?v=20260519-agent-task-memory1"></script>
</body>
</html>`))
