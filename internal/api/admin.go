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
	"encoding/hex"
	"encoding/json"
	"errors"
	"fmt"
	"html/template"
	"io"
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
)

const (
	adminSessionCookie   = "moyi_admin_session"
	adminSessionTTL      = 12 * time.Hour
	defaultAIProvider    = "bailian"
	defaultAIBaseURL     = "https://dashscope.aliyuncs.com/compatible-mode/v1"
	defaultAIChatModel   = "qwen-plus"
	defaultAITestTimeout = 12 * time.Second
)

var aiCheckHTTPClient = &http.Client{Timeout: defaultAITestTimeout}

type adminServer struct {
	basePath      string
	username      string
	password      string
	sessionSecret string
	store         *installStore
	auditMu       sync.Mutex
}

func newAdminServer(entry string, username string, password string, sessionSecret string, stateFile string) *adminServer {
	if username == "" {
		username = "admin"
	}
	if password == "" {
		password = "admin"
	}
	if sessionSecret == "" {
		sessionSecret = "moyi-admin-dev-session-secret"
	}

	return &adminServer{
		basePath:      entry,
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
	case r.Method == http.MethodPost && subpath == "/data-sources/save":
		s.dataSourceSaveSubmit(w, r)
	case r.Method == http.MethodPost && subpath == "/data-sources/test":
		s.dataSourceTestSubmit(w, r)
	case r.Method == http.MethodPost && subpath == "/data-sources/delete":
		s.dataSourceDeleteSubmit(w, r)
	case r.Method == http.MethodGet && subpath == "/ai":
		s.adminPage(w, r, "ai")
	case r.Method == http.MethodPost && subpath == "/ai/chat":
		s.aiChat(w, r)
	case r.Method == http.MethodGet && strings.HasPrefix(subpath, "/ai/files/"):
		s.aiFileDownload(w, r)
	case r.Method == http.MethodGet && subpath == "/users":
		s.adminPage(w, r, "users")
	case r.Method == http.MethodPost && subpath == "/users/save":
		s.adminUserSaveSubmit(w, r)
	case r.Method == http.MethodPost && subpath == "/users/toggle":
		s.adminUserToggleSubmit(w, r)
	case r.Method == http.MethodPost && subpath == "/users/delete":
		s.adminUserDeleteSubmit(w, r)
	case r.Method == http.MethodGet && subpath == "/settings":
		s.adminPage(w, r, "settings")
	case r.Method == http.MethodPost && subpath == "/settings/system":
		s.settingsSystemSubmit(w, r)
	case r.Method == http.MethodPost && subpath == "/settings/storage":
		s.settingsStorageSubmit(w, r)
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

	homeHandler().ServeHTTP(w, r)
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
		http.Redirect(w, r, entry+"/workspace", http.StatusFound)
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
		Initialized:  true,
		SiteName:     data.SiteName,
		AdminEntry:   adminEntry,
		AdminUser:    data.Username,
		Database:     data.Database.sanitized(),
		AI:           data.AI.sanitized(),
		System:       defaultSystemConfig(),
		Storage:      defaultStorageConfig(),
		Access:       defaultAccessConfig(),
		PasswordSalt: salt,
		PasswordHash: hash,
		InstalledAt:  time.Now().UTC(),
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
		http.Redirect(w, r, entry+"/workspace", http.StatusFound)
		return
	}
	s.renderLogin(w, http.StatusOK, loginPageData{
		Action:   entry + "/login",
		Username: state.AdminUser,
		Success:  r.URL.Query().Get("installed") == "1",
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
		s.renderLogin(w, http.StatusBadRequest, loginPageData{
			Action: entry + "/login",
			Error:  "登录请求格式不正确，请重新提交。",
		})
		return
	}

	username := strings.TrimSpace(r.FormValue("username"))
	password := r.FormValue("password")
	if !state.credentialsMatch(username, password) {
		s.recordAuditEvent(r, state, auditEventInput{
			Category:   "login",
			Action:     "登录失败",
			Actor:      username,
			Detail:     "账号或密码校验未通过",
			StatusCode: http.StatusUnauthorized,
		})
		s.renderLogin(w, http.StatusUnauthorized, loginPageData{
			Action:   entry + "/login",
			Username: username,
			Error:    "账号或密码错误，请检查初始化时创建的管理员账号。",
		})
		return
	}
	if updatedState, changed := state.withAdminLogin(username, time.Now().UTC()); changed {
		if err := s.store.Save(updatedState); err == nil {
			state = updatedState
		}
	}

	http.SetCookie(w, &http.Cookie{
		Name:     adminSessionCookie,
		Value:    s.createSessionToken(username, time.Now().Add(adminSessionTTL)),
		Path:     entry,
		HttpOnly: true,
		SameSite: http.SameSiteLaxMode,
		Expires:  time.Now().Add(adminSessionTTL),
		MaxAge:   int(adminSessionTTL.Seconds()),
	})
	s.recordAuditEvent(r, state, auditEventInput{
		Category:   "login",
		Action:     "登录成功",
		Actor:      username,
		Detail:     "管理员进入后台控制台",
		StatusCode: http.StatusFound,
	})
	http.Redirect(w, r, entry+"/workspace", http.StatusFound)
}

func (s *adminServer) logout(w http.ResponseWriter, r *http.Request) {
	entry := s.adminEntryForRequest(r)
	if state, err := s.store.Load(); err == nil && state.Initialized {
		s.recordAuditEvent(r, state, auditEventInput{
			Category:   "login",
			Action:     "退出登录",
			Actor:      state.AdminUser,
			Detail:     "管理员退出后台会话",
			StatusCode: http.StatusFound,
		})
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
	if !s.isAuthenticated(r) {
		http.Redirect(w, r, entry+"/login", http.StatusFound)
		return
	}

	w.Header().Set("Content-Type", "text/html; charset=utf-8")
	w.WriteHeader(http.StatusOK)
	_ = adminWorkspaceTemplate.Execute(w, s.adminPageData(state, entry, active, r.URL.Query(), s.sessionUsername(r)))
}

func (s *adminServer) settingsSystemSubmit(w http.ResponseWriter, r *http.Request) {
	state, entry, ok := s.authenticatedAdminState(w, r)
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

	state.SiteName = siteName
	state.System = systemConfigFromForm(r).normalized()
	if err := s.store.Save(state); err != nil {
		http.Redirect(w, r, entry+"/settings?error="+url.QueryEscape("保存系统设置失败："+err.Error()), http.StatusFound)
		return
	}
	s.recordAuditEvent(r, state, auditEventInput{
		Category:   "operation",
		Action:     "保存基础信息",
		Detail:     "更新站点名称、默认语言或时区",
		StatusCode: http.StatusFound,
	})
	http.Redirect(w, r, entry+"/settings?saved=system", http.StatusFound)
}

func (s *adminServer) settingsStorageSubmit(w http.ResponseWriter, r *http.Request) {
	state, entry, ok := s.authenticatedAdminState(w, r)
	if !ok {
		return
	}
	if err := r.ParseForm(); err != nil {
		http.Redirect(w, r, entry+"/settings?error="+url.QueryEscape("存储设置请求格式不正确"), http.StatusFound)
		return
	}

	storage := storageConfigFromForm(r).normalized()
	if err := validateStorageConfig(storage); err != nil {
		http.Redirect(w, r, entry+"/settings?error="+url.QueryEscape(err.Error()), http.StatusFound)
		return
	}
	if storage.IsLocal() {
		if err := os.MkdirAll(storage.LocalPath, 0o755); err != nil {
			http.Redirect(w, r, entry+"/settings?error="+url.QueryEscape("创建本地存储目录失败："+err.Error()), http.StatusFound)
			return
		}
	}

	state.Storage = storage
	if err := s.store.Save(state); err != nil {
		http.Redirect(w, r, entry+"/settings?error="+url.QueryEscape("保存存储设置失败："+err.Error()), http.StatusFound)
		return
	}
	s.recordAuditEvent(r, state, auditEventInput{
		Category:   "operation",
		Action:     "保存存储设置",
		Detail:     "更新上传目录、扩展名、文件大小限制或导出保留策略",
		StatusCode: http.StatusFound,
	})
	http.Redirect(w, r, entry+"/settings?saved=storage", http.StatusFound)
}

func (s *adminServer) dataSourceSaveSubmit(w http.ResponseWriter, r *http.Request) {
	state, entry, ok := s.authenticatedAdminState(w, r)
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
	state, entry, ok := s.authenticatedAdminState(w, r)
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
	state.DataSources[index].Status = "unavailable"
	if result.OK {
		state.DataSources[index].Status = "available"
	}
	state.DataSources[index].LastMessage = result.Message
	state.DataSources[index].SchemaSummary = strings.Join(result.Checks, "；")
	state.DataSources[index].LastCheckedAt = time.Now().UTC()
	if err := s.store.Save(state); err != nil {
		http.Redirect(w, r, entry+"/data-sources?error="+url.QueryEscape("保存数据源测试结果失败："+err.Error()), http.StatusFound)
		return
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

func (s *adminServer) dataSourceDeleteSubmit(w http.ResponseWriter, r *http.Request) {
	state, entry, ok := s.authenticatedAdminState(w, r)
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
	state, entry, ok := s.authenticatedAdminState(w, r)
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

func (s *adminServer) adminUserToggleSubmit(w http.ResponseWriter, r *http.Request) {
	state, entry, ok := s.authenticatedAdminState(w, r)
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
	state, entry, ok := s.authenticatedAdminState(w, r)
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

func (s *adminServer) fileUploadSubmit(w http.ResponseWriter, r *http.Request) {
	state, entry, ok := s.authenticatedAdminState(w, r)
	if !ok {
		return
	}
	storage := state.Storage.normalized()
	if err := validateStorageConfig(storage); err != nil {
		http.Redirect(w, r, entry+"/files?error="+url.QueryEscape(err.Error()), http.StatusFound)
		return
	}
	root, err := storageLocalRoot(storage)
	if err != nil {
		http.Redirect(w, r, entry+"/files?error="+url.QueryEscape("存储目录不可用："+err.Error()), http.StatusFound)
		return
	}
	if err := os.MkdirAll(root, 0o755); err != nil {
		http.Redirect(w, r, entry+"/files?error="+url.QueryEscape("创建存储目录失败："+err.Error()), http.StatusFound)
		return
	}

	maxBytes := int64(storage.MaxFileSizeMB) * 1024 * 1024
	r.Body = http.MaxBytesReader(w, r.Body, maxBytes*10+1024*1024)
	if err := r.ParseMultipartForm(32 << 20); err != nil {
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
			http.Redirect(w, r, entry+"/files?error="+url.QueryEscape("文件超过大小限制："+header.Filename), http.StatusFound)
			return
		}
		if !storageExtensionAllowed(storage, header.Filename) {
			http.Redirect(w, r, entry+"/files?error="+url.QueryEscape("不支持的文件扩展名："+filepath.Ext(header.Filename)), http.StatusFound)
			return
		}
		if err := saveUploadedFile(root, header.Filename, header.Open, maxBytes); err != nil {
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
	state, entry, ok := s.authenticatedAdminState(w, r)
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
	state, entry, ok := s.authenticatedAdminState(w, r)
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

func (s *adminServer) adminPageData(state installState, entry string, active string, query url.Values, currentUser string) adminPageData {
	if active == "" {
		active = "dashboard"
	}
	titles := map[string][2]string{
		"dashboard":    {"工作台", "系统运行概览、迁移进度与关键入口"},
		"foundation":   {"基础服务", "旧系统基础设施对照、迁移状态与下一步"},
		"data-sources": {"数据源", "元数据连接、业务库接入与结构探测"},
		"ai":           {"AI 智能体", "模型服务、工具边界与执行能力"},
		"users":        {"用户权限", "管理员账号、角色与访问边界"},
		"settings":     {"系统设置", "安装状态、安全入口与运行参数"},
		"files":        {"文件管理", "上传文件、存储目录、预览下载与清理"},
		"audit":        {"审计日志", "初始化、登录与关键操作记录"},
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
	if strings.TrimSpace(currentUser) == "" {
		currentUser = state.AdminUser
	}
	auditEvents := s.listAuditEvents(120)
	if len(auditEvents) == 0 {
		auditEvents = []adminAuditEvent{
			{Time: installedAt, Category: "system", Action: "系统初始化", Actor: state.AdminUser, Detail: "创建站点、管理员与后台随机入口", Meta: state.AdminUser, Status: "200", StatusClass: "is-ready"},
			{Time: "当前会话", Category: "operation", Action: "后台访问", Actor: state.AdminUser, Detail: "进入管理控制台", Meta: state.AdminUser, Status: "200", StatusClass: "is-ready"},
		}
	}

	data := adminPageData{
		BasePath:      entry,
		LogoutAction:  entry + "/logout",
		Active:        active,
		Title:         title[0],
		Subtitle:      title[1],
		SiteName:      state.SiteName,
		Username:      currentUser,
		AdminEntry:    entry,
		InstalledAt:   installedAt,
		Database:      state.Database.DisplayName(),
		DatabaseDSN:   state.Database.DisplayTarget(),
		AIProvider:    state.AI.DisplayName(),
		AIModel:       state.AI.DisplayModel(),
		AITarget:      state.AI.DisplayTarget(),
		AIStatus:      aiStatus,
		AIStatusClass: aiStatusClass,
	}
	data.SettingsNotice, data.SettingsNoticeClass = settingsNoticeFromQuery(query)
	data.UserNotice, data.UserNoticeClass = userNoticeFromQuery(query)
	data.DataSourceNotice, data.DataSourceNoticeClass = dataSourceNoticeFromQuery(query)
	data.FileNotice, data.FileNoticeClass = fileNoticeFromQuery(query)
	data.NavItems = buildAdminNav(entry, active)
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
	data.AgentCapabilities = []adminCapability{
		{Name: "任务规划", Boundary: "理解目标、拆解步骤、生成建议动作", Status: "已启用", StatusClass: "is-ready"},
		{Name: "工具轨迹", Boundary: "展示每次工具调用、结果与拦截原因", Status: "已启用", StatusClass: "is-ready"},
		{Name: "只读数据工具", Boundary: "表清单、字段结构、预览与 SELECT", Status: "已启用", StatusClass: "is-ready"},
		{Name: "安全边界", Boundary: "拒绝写入、多语句、注释与危险关键字", Status: "已启用", StatusClass: "is-ready"},
		{Name: "导出与记忆", Boundary: "表格导出、运行记录、会话和工具结果落入元数据表", Status: "已接入", StatusClass: "is-ready"},
	}
	data.AgentRuns = buildAdminAgentRunRows(s.listAgentRuns(12))
	data.UserSaveAction = entry + "/users/save"
	data.AdminUsers = buildAdminUserRows(state, entry)
	data.AdminRoles = buildAdminRoleRows(state)
	data.AdminMenus = buildAdminMenuRows(state)
	data.AdminPermissions = buildAdminPermissionRows(state)
	data.Settings = []adminSettingRow{
		{Key: "站点名称", Value: state.SiteName},
		{Key: "后台入口", Value: entry},
		{Key: "数据库", Value: state.Database.DisplayName() + " / " + state.Database.DisplayTarget()},
		{Key: "AI 服务", Value: state.AI.DisplayName() + " / " + state.AI.DisplayModel()},
		{Key: "时区", Value: system.Timezone},
		{Key: "语言", Value: system.Locale},
		{Key: "存储", Value: storage.DisplayName() + " / " + storage.LocalPath},
	}
	data.SystemSettings = adminSystemSettings{
		Action:   entry + "/settings/system",
		SiteName: state.SiteName,
		Timezone: system.Timezone,
		Locale:   system.Locale,
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
	data.FileUploadAction = entry + "/files/upload"
	data.FileRows = listAdminFiles(storage, entry, 200)
	data.FileStorageSummary = storage.DisplayName() + " / " + storage.LocalPath
	data.FileAllowedSummary = storageAllowedDescription(storage.AllowedExtensions) + "，单文件上限 " + strconv.Itoa(storage.MaxFileSizeMB) + " MB"
	data.FoundationServices = buildFoundationServices(state, len(auditEvents), len(data.FileRows))
	data.AuditEvents = auditEvents
	data.AuditMetrics = buildAuditMetrics(auditEvents)
	return data
}

func buildAdminNav(entry string, active string) []adminNavItem {
	items := []adminNavItem{
		{Key: "dashboard", Label: "工作台", Href: entry + "/workspace"},
		{Key: "foundation", Label: "基础服务", Href: entry + "/foundation"},
		{Key: "data-sources", Label: "数据源", Href: entry + "/data-sources"},
		{Key: "ai", Label: "AI 智能体", Href: entry + "/ai"},
		{Key: "users", Label: "用户权限", Href: entry + "/users"},
		{Key: "settings", Label: "系统设置", Href: entry + "/settings"},
		{Key: "files", Label: "文件管理", Href: entry + "/files"},
		{Key: "audit", Label: "审计日志", Href: entry + "/audit"},
	}
	for i := range items {
		items[i].Active = items[i].Key == active
	}
	return items
}

func buildFoundationServices(state installState, auditCount int, fileCount int) []adminFoundationService {
	storage := state.Storage.normalized()
	return []adminFoundationService{
		{Name: "初始化安装", Legacy: "InstallController / install 视图", Current: "首页安装向导、数据库检查、AI 配置检查、元数据表创建", Status: "已迁移", StatusClass: "is-ready", Next: "补安装向导重置保护"},
		{Name: "隐藏后台入口", Legacy: "AdminEntryMiddleware", Current: "初始化时随机生成 " + state.AdminEntry, Status: "已迁移", StatusClass: "is-ready", Next: "保留入口轮换能力"},
		{Name: "管理员认证", Legacy: "AuthController / AdminAuthMiddleware", Current: "超级管理员登录、会话 Cookie、退出登录", Status: "已迁移", StatusClass: "is-ready", Next: "补管理员密码修改"},
		{Name: "系统设置", Legacy: "SiteController / AdminConfig", Current: "站点名称、语言、时区、运行参数", Status: "已接入", StatusClass: "is-ready", Next: "扩展站点主题和安全策略"},
		{Name: "存储与文件", Legacy: "UploadFileController / StorageEngine", Current: storage.DisplayName() + "，当前文件 " + strconv.Itoa(fileCount) + " 个", Status: "已接入", StatusClass: "is-ready", Next: "补 S3/OSS 驱动与文件审查"},
		{Name: "运行审计", Legacy: "LoginLog / OperationLog / InterceptLog / ErrorStatistic", Current: "SQLite 审计表，当前 " + strconv.Itoa(auditCount) + " 条", Status: "已接入", StatusClass: "is-ready", Next: "补审计筛选与导出"},
		{Name: "数据源连接", Legacy: "DatabaseConnectionController", Current: "连接登记、基础测试、SQLite 结构扫描、智能体只读读取", Status: "已接入", StatusClass: "is-ready", Next: "补 MySQL/PostgreSQL 表注释扫描"},
		{Name: "用户角色权限", Legacy: "User / Role / Permission / Menu", Current: "管理员账号、角色、菜单、权限清单持久化", Status: "本次补齐", StatusClass: "is-ready", Next: "补细粒度权限拦截"},
		{Name: "插件扩展", Legacy: "AddonController / Addons*Service", Current: "旧系统归档参考", Status: "待规划", StatusClass: "is-muted", Next: "先定 Go 端扩展包规范"},
		{Name: "CRUD 生成", Legacy: "CrudGenerator / UniversalCrud", Current: "不走传统 CRUD，转向 Agent 工具生成查询/导出", Status: "重构中", StatusClass: "is-progress", Next: "补 Agent 工具注册与数据权限"},
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
	w.Header().Set("Content-Type", "text/html; charset=utf-8")
	w.WriteHeader(statusCode)
	_ = adminLoginTemplate.Execute(w, data)
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

func (s *adminServer) createSessionToken(username string, expiresAt time.Time) string {
	expires := strconv.FormatInt(expiresAt.Unix(), 10)
	payload := username + "|" + expires
	signature := s.sign(payload)
	return payload + "|" + signature
}

func (s *adminServer) validateSessionToken(token string) bool {
	parts := strings.Split(token, "|")
	if len(parts) != 3 {
		return false
	}

	username, expires, signature := parts[0], parts[1], parts[2]
	state, err := s.store.Load()
	if err != nil || !state.Initialized {
		return false
	}
	account, ok := findAdminAccount(state, username)
	if !ok || account.Status == "disabled" {
		return false
	}

	expiresUnix, err := strconv.ParseInt(expires, 10, 64)
	if err != nil || time.Now().After(time.Unix(expiresUnix, 0)) {
		return false
	}

	expected := s.sign(username + "|" + expires)
	return subtle.ConstantTimeCompare([]byte(signature), []byte(expected)) == 1
}

func (s *adminServer) sessionUsername(r *http.Request) string {
	cookie, err := r.Cookie(adminSessionCookie)
	if err != nil {
		return ""
	}
	parts := strings.Split(cookie.Value, "|")
	if len(parts) != 3 || !s.validateSessionToken(cookie.Value) {
		return ""
	}
	return parts[0]
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

type loginPageData struct {
	Action   string
	Username string
	Error    string
	Success  bool
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
	BasePath              string
	LogoutAction          string
	Active                string
	Title                 string
	Subtitle              string
	SiteName              string
	Username              string
	AdminEntry            string
	InstalledAt           string
	Database              string
	DatabaseDSN           string
	AIProvider            string
	AIModel               string
	AITarget              string
	AIStatus              string
	AIStatusClass         string
	UserNotice            string
	UserNoticeClass       string
	UserSaveAction        string
	DataSourceNotice      string
	DataSourceNoticeClass string
	DataSourceSaveAction  string
	SettingsNotice        string
	SettingsNoticeClass   string
	FileNotice            string
	FileNoticeClass       string
	NavItems              []adminNavItem
	Metrics               []adminMetric
	Tasks                 []adminTask
	FoundationServices    []adminFoundationService
	DataSources           []adminDataSource
	AgentCapabilities     []adminCapability
	AgentRuns             []adminAgentRunRow
	AdminUsers            []adminUserRow
	AdminRoles            []adminRoleRow
	AdminMenus            []adminMenuRow
	AdminPermissions      []adminPermissionRow
	Settings              []adminSettingRow
	SystemSettings        adminSystemSettings
	StorageSettings       adminStorageSettings
	FileRows              []adminFileRow
	FileUploadAction      string
	FileStorageSummary    string
	FileAllowedSummary    string
	AuditMetrics          []adminMetric
	AuditEvents           []adminAuditEvent
}

type adminNavItem struct {
	Key    string
	Label  string
	Href   string
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

type adminFoundationService struct {
	Name        string
	Legacy      string
	Current     string
	Status      string
	StatusClass string
	Next        string
}

type adminDataSource struct {
	Name         string
	Driver       string
	Target       string
	Role         string
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

type adminUserRow struct {
	Username     string
	DisplayName  string
	Role         string
	Status       string
	StatusClass  string
	Source       string
	CreatedAt    string
	LastSeen     string
	CanDelete    bool
	ToggleLabel  string
	ToggleAction string
	DeleteAction string
}

type adminRoleRow struct {
	Key         string
	Name        string
	Scope       string
	Status      string
	StatusClass string
	Description string
}

type adminMenuRow struct {
	Key         string
	Label       string
	Path        string
	Status      string
	StatusClass string
}

type adminPermissionRow struct {
	Key         string
	Subject     string
	Permission  string
	Boundary    string
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

type adminSettingRow struct {
	Key   string
	Value string
}

type adminSystemSettings struct {
	Action   string
	SiteName string
	Timezone string
	Locale   string
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

type adminFileRow struct {
	Name         string
	Path         string
	Kind         string
	Size         string
	Modified     string
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

type auditEventInput struct {
	Category   string
	Action     string
	Actor      string
	Detail     string
	StatusCode int
	Duration   time.Duration
}

type installState struct {
	Initialized  bool               `json:"initialized"`
	SiteName     string             `json:"site_name"`
	AdminEntry   string             `json:"admin_entry"`
	AdminUser    string             `json:"admin_user"`
	Database     databaseConfig     `json:"database"`
	AI           aiConfig           `json:"ai"`
	System       systemConfig       `json:"system,omitempty"`
	Storage      storageConfig      `json:"storage,omitempty"`
	DataSources  []dataSourceConfig `json:"data_sources,omitempty"`
	Access       accessConfig       `json:"access,omitempty"`
	PasswordSalt string             `json:"password_salt"`
	PasswordHash string             `json:"password_hash"`
	InstalledAt  time.Time          `json:"installed_at"`
}

type systemConfig struct {
	Timezone string `json:"timezone,omitempty"`
	Locale   string `json:"locale,omitempty"`
}

type storageConfig struct {
	Driver                   string `json:"driver,omitempty"`
	LocalPath                string `json:"local_path,omitempty"`
	PublicURL                string `json:"public_url,omitempty"`
	MaxFileSizeMB            int    `json:"max_file_size_mb,omitempty"`
	AllowedExtensions        string `json:"allowed_extensions,omitempty"`
	AgentExportRetentionDays int    `json:"agent_export_retention_days,omitempty"`
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
	Key         string `json:"key"`
	Name        string `json:"name"`
	Scope       string `json:"scope,omitempty"`
	Status      string `json:"status"`
	Description string `json:"description,omitempty"`
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
	Provider  string `json:"provider"`
	APIKey    string `json:"api_key,omitempty"`
	BaseURL   string `json:"base_url,omitempty"`
	ChatModel string `json:"chat_model,omitempty"`
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
		Timezone: "Asia/Shanghai",
		Locale:   "zh-CN",
	}
}

func systemConfigFromForm(r *http.Request) systemConfig {
	return systemConfig{
		Timezone: strings.TrimSpace(r.FormValue("timezone")),
		Locale:   strings.TrimSpace(r.FormValue("locale")),
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
		Provider:  defaultAIProvider,
		BaseURL:   defaultAIBaseURL,
		ChatModel: defaultAIChatModel,
	}
}

func aiConfigFromForm(r *http.Request) aiConfig {
	provider := strings.TrimSpace(r.FormValue("ai_provider"))
	if provider == "" {
		provider = "disabled"
	}
	ai := aiConfig{
		Provider:  provider,
		APIKey:    r.FormValue("ai_api_key"),
		BaseURL:   strings.TrimSpace(r.FormValue("ai_base_url")),
		ChatModel: strings.TrimSpace(r.FormValue("ai_chat_model")),
	}
	if ai.Provider == "bailian" {
		if ai.BaseURL == "" {
			ai.BaseURL = defaultAIBaseURL
		}
		if ai.ChatModel == "" {
			ai.ChatModel = defaultAIChatModel
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
	}
	return a
}

func (c systemConfig) normalized() systemConfig {
	c.Timezone = strings.TrimSpace(c.Timezone)
	c.Locale = strings.TrimSpace(c.Locale)
	if c.Timezone == "" {
		c.Timezone = defaultSystemConfig().Timezone
	}
	if c.Locale == "" {
		c.Locale = defaultSystemConfig().Locale
	}
	return c
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
			{Key: "super_admin", Name: "超级管理员", Scope: "后台全局管理", Status: "enabled", Description: "拥有后台所有管理能力"},
			{Key: "ops_admin", Name: "运维管理员", Scope: "基础设施、文件、数据源和日志", Status: "enabled", Description: "适合维护系统设置与基础服务"},
			{Key: "agent_reader", Name: "智能体只读访问", Scope: "所有已登记元数据表、派生视图和文件视图", Status: "enabled", Description: "适合查看数据、导出报表和使用 AI 工具"},
		},
		Menus: []adminMenuConfig{
			{Key: "dashboard", Label: "工作台", Path: "/workspace", Status: "enabled"},
			{Key: "foundation", Label: "基础服务", Path: "/foundation", Status: "enabled"},
			{Key: "data_sources", Label: "数据源", Path: "/data-sources", Status: "enabled"},
			{Key: "ai", Label: "AI 智能体", Path: "/ai", Status: "enabled"},
			{Key: "users", Label: "用户权限", Path: "/users", Status: "enabled"},
			{Key: "settings", Label: "系统设置", Path: "/settings", Status: "enabled"},
			{Key: "files", Label: "文件管理", Path: "/files", Status: "enabled"},
			{Key: "audit", Label: "审计日志", Path: "/audit", Status: "enabled"},
		},
		Permissions: []adminPermissionConfig{
			{Key: "admin.users.manage", Subject: "admin_users", Permission: "manage", Boundary: "允许创建、禁用、删除非初始化管理员", Status: "enabled"},
			{Key: "admin.settings.manage", Subject: "admin_settings", Permission: "manage", Boundary: "允许维护站点、存储和运行参数", Status: "enabled"},
			{Key: "admin.data_sources.manage", Subject: "data_sources", Permission: "manage", Boundary: "允许登记、测试和删除业务数据源", Status: "enabled"},
			{Key: "admin.files.manage", Subject: "upload_files", Permission: "manage", Boundary: "允许上传、预览、下载和删除后台文件", Status: "enabled"},
			{Key: "agent.tables.read", Subject: "all_registered_tables", Permission: "read", Boundary: "允许读取所有已登记数据表和虚拟表", Status: "enabled"},
			{Key: "agent.sql.select", Subject: "all_registered_tables", Permission: "select", Boundary: "仅允许 SELECT，拒绝写入、多语句和危险关键字", Status: "enabled"},
			{Key: "agent.secrets.mask", Subject: "sensitive_fields", Permission: "mask", Boundary: "API Key、密码、盐值和哈希只允许脱敏展示", Status: "enabled"},
		},
	}
}

func (a accessConfig) normalized(state installState) accessConfig {
	defaults := defaultAccessConfig()
	a.Roles = normalizeRoleConfigs(a.Roles)
	if len(a.Roles) == 0 {
		a.Roles = defaults.Roles
	}
	a.Menus = normalizeMenuConfigs(a.Menus)
	if len(a.Menus) == 0 {
		a.Menus = defaults.Menus
	}
	a.Permissions = normalizePermissionConfigs(a.Permissions)
	if len(a.Permissions) == 0 {
		a.Permissions = defaults.Permissions
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

func normalizeRoleConfigs(roles []adminRoleConfig) []adminRoleConfig {
	out := make([]adminRoleConfig, 0, len(roles))
	seen := map[string]bool{}
	for _, role := range roles {
		role.Key = strings.TrimSpace(role.Key)
		role.Name = strings.TrimSpace(role.Name)
		role.Scope = strings.TrimSpace(role.Scope)
		role.Status = strings.ToLower(strings.TrimSpace(role.Status))
		role.Description = strings.TrimSpace(role.Description)
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

func buildAdminUserRows(state installState, entry string) []adminUserRow {
	access := state.Access.normalized(state)
	rows := make([]adminUserRow, 0, len(access.Users))
	for _, user := range access.Users {
		statusText := accessStatusText(user.Status)
		toggleLabel := "禁用"
		if user.Status == "disabled" {
			toggleLabel = "启用"
		}
		lastSeen := formatAdminTime(user.LastLoginAt)
		if user.Source == "install_state" {
			lastSeen = "当前会话"
		}
		rows = append(rows, adminUserRow{
			Username:     user.Username,
			DisplayName:  user.DisplayName,
			Role:         roleNameByKey(access.Roles, user.Role),
			Status:       statusText,
			StatusClass:  accessStatusClass(user.Status),
			Source:       user.Source,
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

func buildAdminRoleRows(state installState) []adminRoleRow {
	access := state.Access.normalized(state)
	rows := make([]adminRoleRow, 0, len(access.Roles))
	for _, role := range access.Roles {
		rows = append(rows, adminRoleRow{
			Key:         role.Key,
			Name:        role.Name,
			Scope:       role.Scope,
			Status:      accessStatusText(role.Status),
			StatusClass: accessStatusClass(role.Status),
			Description: role.Description,
		})
	}
	return rows
}

func buildAdminMenuRows(state installState) []adminMenuRow {
	access := state.Access.normalized(state)
	rows := make([]adminMenuRow, 0, len(access.Menus))
	for _, menu := range access.Menus {
		rows = append(rows, adminMenuRow{
			Key:         menu.Key,
			Label:       menu.Label,
			Path:        menu.Path,
			Status:      accessStatusText(menu.Status),
			StatusClass: accessStatusClass(menu.Status),
		})
	}
	return rows
}

func buildAdminPermissionRows(state installState) []adminPermissionRow {
	access := state.Access.normalized(state)
	rows := make([]adminPermissionRow, 0, len(access.Permissions))
	for _, permission := range access.Permissions {
		rows = append(rows, adminPermissionRow{
			Key:         permission.Key,
			Subject:     permission.Subject,
			Permission:  permission.Permission,
			Boundary:    permission.Boundary,
			Status:      accessStatusText(permission.Status),
			StatusClass: accessStatusClass(permission.Status),
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
	case "toggle":
		return "管理员状态已更新。", "alert-success"
	case "delete":
		return "管理员账号已删除。", "alert-success"
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
		summary, schemaChecks, err := inspectSQLiteSchema(path)
		checks := []string{"文件：" + path, "大小：" + formatAdminFileSize(info.Size())}
		if err != nil {
			checks = append(checks, "结构扫描失败："+err.Error())
			return databaseCheckResult{
				OK:      true,
				Message: "SQLite 文件可读取，结构扫描失败：" + err.Error(),
				Checks:  checks,
			}
		}
		checks = append(checks, schemaChecks...)
		return databaseCheckResult{
			OK:      true,
			Message: "SQLite 文件可读取，" + summary + "。",
			Checks:  checks,
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

func inspectSQLiteSchema(path string) (string, []string, error) {
	ctx, cancel := context.WithTimeout(context.Background(), 3*time.Second)
	defer cancel()

	db, err := sql.Open("sqlite", path)
	if err != nil {
		return "", nil, err
	}
	defer db.Close()
	if err := db.PingContext(ctx); err != nil {
		return "", nil, err
	}

	rows, err := db.QueryContext(ctx, `SELECT name FROM sqlite_master WHERE type IN ('table', 'view') AND name NOT LIKE 'sqlite_%' ORDER BY name`)
	if err != nil {
		return "", nil, err
	}
	defer rows.Close()

	tables := make([]string, 0)
	for rows.Next() {
		var name string
		if err := rows.Scan(&name); err != nil {
			return "", nil, err
		}
		if strings.TrimSpace(name) != "" {
			tables = append(tables, name)
		}
	}
	if err := rows.Err(); err != nil {
		return "", nil, err
	}
	if len(tables) == 0 {
		return "未发现业务表", []string{"表数量：0", "字段数量：0"}, nil
	}

	totalColumns := 0
	tableDetails := make([]string, 0, minInt(len(tables), 8))
	for _, table := range tables {
		columns, err := inspectSQLiteTableColumns(ctx, db, table)
		if err != nil {
			return "", nil, err
		}
		totalColumns += len(columns)
		if len(tableDetails) >= 8 {
			continue
		}
		preview := columns
		if len(preview) > 6 {
			preview = preview[:6]
		}
		tableDetails = append(tableDetails, table+"("+strings.Join(preview, "、")+")")
	}

	tablePreview := tables
	if len(tablePreview) > 6 {
		tablePreview = tablePreview[:6]
	}
	checks := []string{
		"表数量：" + strconv.Itoa(len(tables)),
		"字段数量：" + strconv.Itoa(totalColumns),
		"表清单：" + strings.Join(tablePreview, "、"),
	}
	if len(tableDetails) > 0 {
		checks = append(checks, "字段结构："+strings.Join(tableDetails, "；"))
	}
	return "发现 " + strconv.Itoa(len(tables)) + " 张表、" + strconv.Itoa(totalColumns) + " 个字段", checks, nil
}

func inspectSQLiteTableColumns(ctx context.Context, db *sql.DB, table string) ([]string, error) {
	rows, err := db.QueryContext(ctx, `PRAGMA table_info(`+quoteSQLiteIdentifier(table)+`)`)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	columns := make([]string, 0)
	for rows.Next() {
		var cid int
		var name, columnType string
		var notNull int
		var defaultValue sql.NullString
		var pk int
		if err := rows.Scan(&cid, &name, &columnType, &notNull, &defaultValue, &pk); err != nil {
			return nil, err
		}
		column := name
		if strings.TrimSpace(columnType) != "" {
			column += " " + strings.ToUpper(strings.TrimSpace(columnType))
		}
		if pk > 0 {
			column += " PK"
		}
		if notNull > 0 {
			column += " NOT NULL"
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
	address := net.JoinHostPort(source.Host, source.Port)
	conn, err := net.DialTimeout("tcp", address, 2*time.Second)
	if err != nil {
		return databaseCheckResult{
			OK:      false,
			Message: "无法连接数据库端口：" + err.Error(),
			Checks:  []string{"地址：" + address, "数据库：" + source.Database},
		}
	}
	_ = conn.Close()
	return databaseCheckResult{
		OK:      true,
		Message: source.DisplayName() + " 端口可达，连接基础检查通过。",
		Checks:  []string{"地址：" + address, "数据库：" + source.Database, "结构扫描：待接入 " + source.DisplayName() + " 驱动后读取真实表注释与字段注释"},
	}
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
		{Name: "metadata", Driver: state.Database.DisplayName(), Target: state.Database.DisplayTarget(), Role: "系统元数据", Status: "可用", StatusClass: "is-ready", Message: "初始化时配置的元数据连接", LastChecked: formatAdminTime(state.InstalledAt), Schema: "承载安装状态、系统配置和后续基础表"},
		{Name: "legacy-hyperf", Driver: "参考归档", Target: "legacy-hyperf/", Role: "旧系统比对", Status: "只读参考", StatusClass: "is-progress", Message: "旧 Hyperf 代码已归档，用于迁移对照", Schema: "控制器、模型、服务、视图与插件资源"},
	}
	for _, source := range normalizeDataSources(state.DataSources) {
		source = source.normalized()
		rows = append(rows, adminDataSource{
			Name:         source.Name,
			Driver:       source.DisplayName(),
			Target:       source.DisplayTarget(),
			Role:         source.Role,
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

func (s *adminServer) listAgentToolResults(limit int) []agentToolResultRecord {
	records, err := s.store.ListAgentToolResults(limit)
	if err != nil {
		return nil
	}
	return records
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
			Name:         filepath.Base(path),
			Path:         relative,
			Kind:         fileKind(path),
			Size:         formatAdminFileSize(info.Size()),
			Modified:     formatAdminTime(info.ModTime()),
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

func (s installState) credentialsMatch(username string, password string) bool {
	if !s.Initialized || username == "" || password == "" {
		return false
	}
	account, ok := findAdminAccount(s, username)
	if !ok || account.Status == "disabled" || account.PasswordSalt == "" || account.PasswordHash == "" {
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
	default:
		return errors.New("请选择支持的 AI 服务商")
	}
	return nil
}

type databaseCheckResult struct {
	OK      bool     `json:"ok"`
	Message string   `json:"message"`
	Checks  []string `json:"checks"`
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
		address := net.JoinHostPort(db.Host, db.Port)
		conn, err := net.DialTimeout("tcp", address, 2*time.Second)
		if err != nil {
			return databaseCheckResult{
				OK:      false,
				Message: "无法连接数据库端口：" + err.Error(),
				Checks: []string{
					"目标：" + address,
					"说明：当前阶段先检查 TCP 连通性，后续接入驱动后会校验账号密码和建表权限。",
				},
			}
		}
		_ = conn.Close()
		return databaseCheckResult{
			OK:      true,
			Message: db.DisplayName() + " 端口连通。下一步会接入驱动校验账号密码和迁移权限。",
			Checks: []string{
				"目标：" + address,
				"数据库：" + db.Database,
				"用户：" + db.Username,
			},
		}
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
  <link rel="stylesheet" href="/assets/css/admin-foundation.css?v=20260514-agent1">
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
            <input class="form-control" id="password" name="password" type="password" autocomplete="current-password" placeholder="请输入密码">
          </div>
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
  <link rel="stylesheet" href="/assets/css/admin-foundation.css?v=20260514-admin1">
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
  <link rel="stylesheet" href="/assets/css/admin-foundation.css?v=20260514-admin1">
</head>
<body class="admin-shell">
  <div class="admin-layout">
    <aside class="admin-sidebar">
      <a class="admin-brand" href="{{.BasePath}}/workspace" aria-label="Moyi Admin 工作台">
        <span class="admin-brand-mark">M</span>
        <span>
          <strong>Moyi Admin</strong>
          <small>Go AI 管理台</small>
        </span>
      </a>
      <nav class="admin-nav" aria-label="后台导航">
        {{range .NavItems}}
          <a class="{{if .Active}}active{{end}}" href="{{.Href}}">{{.Label}}</a>
        {{end}}
      </nav>
      <div class="admin-sidebar-status">
        <div class="admin-sidebar-label">当前站点</div>
        <strong>{{.SiteName}}</strong>
        <span>{{.Username}} · {{.Database}}</span>
      </div>
    </aside>

    <div class="admin-content">
      <header class="admin-topbar">
        <div class="admin-page-heading">
          <div class="admin-breadcrumb">后台 / {{.Title}}</div>
          <h1>{{.Title}}</h1>
          <p>{{.Subtitle}}</p>
        </div>
        <div class="admin-topbar-actions">
          <span class="admin-status-pill is-ready">已初始化</span>
          <form method="post" action="{{.LogoutAction}}">
            <button class="admin-icon-button" type="submit">退出</button>
          </form>
        </div>
      </header>

      <main class="admin-page">
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
                <a href="{{.BasePath}}/ai">AI 智能体配置</a>
                <a href="{{.BasePath}}/users">用户权限</a>
                <a href="{{.BasePath}}/files">文件管理</a>
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

          <section class="admin-grid">
            <div class="admin-panel">
              <div class="admin-panel-head">
                <h2>本轮优先补齐</h2>
                <span class="admin-panel-meta">Now</span>
              </div>
              <dl class="admin-kv">
                <div><dt>运行审计</dt><dd>登录、设置、文件、AI 对话写入 SQLite 审计表</dd></div>
                <div><dt>日志页面</dt><dd>审计页展示真实事件、状态码、IP 与请求路径</dd></div>
                <div><dt>智能体上下文</dt><dd>审计事件已纳入后台智能体只读查询</dd></div>
              </dl>
            </div>
            <div class="admin-panel">
              <div class="admin-panel-head">
                <h2>下一批基础服务</h2>
                <span class="admin-panel-meta">Next</span>
              </div>
              <dl class="admin-kv">
                <div><dt>数据源管理</dt><dd>连接登记、测试连接、结构扫描、注释索引</dd></div>
                <div><dt>权限体系</dt><dd>管理员、角色、菜单、权限持久化</dd></div>
                <div><dt>扩展系统</dt><dd>插件规范、资源发布、配置管理</dd></div>
              </dl>
            </div>
          </section>
        {{else if eq .Active "data-sources"}}
          {{if .DataSourceNotice}}
            <div class="admin-alert {{.DataSourceNoticeClass}}">{{.DataSourceNotice}}</div>
          {{end}}
          <section class="admin-grid">
            <div class="admin-panel">
              <div class="admin-panel-head">
                <h2>登记数据源</h2>
                <span class="admin-panel-meta">Connection</span>
              </div>
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
                    <small class="form-text">选择 SQLite 时使用；MySQL/PostgreSQL 会忽略该项。</small>
                  </label>
                  <label class="admin-form-wide">
                    <span>用途说明</span>
                    <input class="form-control" name="role" placeholder="业务数据源 / 报表库 / 旧系统迁移库" autocomplete="off">
                  </label>
                </div>
                <button class="admin-submit-button" type="submit">保存数据源</button>
              </form>
            </div>

            <div class="admin-panel">
              <div class="admin-panel-head">
                <h2>探测能力</h2>
                <span class="admin-panel-meta">Probe</span>
              </div>
              <dl class="admin-kv">
                <div><dt>配置校验</dt><dd>名称、驱动、主机、端口、库名和 SQLite 路径</dd></div>
                <div><dt>基础连接</dt><dd>MySQL/PostgreSQL 做 TCP 可达性检查，SQLite 做文件/目录检查</dd></div>
                <div><dt>结构扫描</dt><dd>SQLite 已读取真实表和字段；MySQL/PostgreSQL 后续接驱动读取表注释和字段注释</dd></div>
              </dl>
            </div>

            <div class="admin-panel admin-panel-wide">
              <div class="admin-panel-head">
                <h2>数据源列表</h2>
                <span class="admin-panel-meta">Connections</span>
              </div>
              <div class="admin-table data-source-table">
                <div class="admin-table-row admin-table-head">
                  <span>名称</span><span>类型</span><span>地址</span><span>用途</span><span>状态</span><span>操作</span>
                </div>
                {{range .DataSources}}
                  <div class="admin-table-row">
                    <span><strong class="mono">{{.Name}}</strong>{{if .Message}}<small>{{.Message}}</small>{{end}}</span>
                    <span>{{.Driver}}</span>
                    <span class="mono">{{.Target}}</span>
                    <span>{{.Role}}{{if .Schema}}<small>{{.Schema}}</small>{{end}}</span>
                    <span><b class="admin-badge {{.StatusClass}}">{{.Status}}</b>{{if .LastChecked}}<small>{{.LastChecked}}</small>{{end}}</span>
                    <span class="admin-file-actions">
                      {{if .Editable}}
                        <form method="post" action="{{.TestAction}}">
                          <input type="hidden" name="name" value="{{.Name}}">
                          <button type="submit">测试</button>
                        </form>
                        <form method="post" action="{{.DeleteAction}}">
                          <input type="hidden" name="name" value="{{.Name}}">
                          <button type="submit">删除</button>
                        </form>
                      {{else}}
                        <span class="admin-badge is-muted">内置</span>
                      {{end}}
                    </span>
                  </div>
                {{end}}
              </div>
            </div>
          </section>
        {{else if eq .Active "ai"}}
          <section class="admin-grid">
            <div class="admin-panel admin-panel-wide agent-chat-panel">
              <div class="admin-panel-head">
                <h2>智能体工作台</h2>
                <span class="admin-panel-meta">Agent Runtime</span>
              </div>
              <div class="agent-workbench">
                <div class="agent-dialogue">
                  <div class="agent-messages" id="agentMessages" aria-live="polite">
                    <article class="agent-message is-assistant">
                      <span>AI</span>
                      <p>准备接管后台数据、迁移计划和只读查询任务。</p>
                    </article>
                  </div>
                  <div class="agent-quick-actions" aria-label="快捷任务">
                    <button type="button" data-agent-prompt="对当前后台做一次系统体检并给出下一步建议">系统体检</button>
                    <button type="button" data-agent-prompt="给出 Moyi Admin 智能体构造方案">智能体方案</button>
                    <button type="button" data-agent-prompt="我们后台有几个管理员账号？">管理员账号</button>
                    <button type="button" data-agent-prompt="把管理员账号的账号、角色、状态整理成 XLSX 文件发给我">导出 XLSX</button>
                    <button type="button" data-agent-prompt="把数据源配置整理成 JSON 文件发给我">导出 JSON</button>
                    <button type="button" data-agent-prompt="预览数据源配置">数据源巡检</button>
                    <button type="button" data-agent-prompt="查看站点信息和 AI 配置">站点配置</button>
                  </div>
                  <form class="agent-chat-form" id="agentChatForm" action="{{.BasePath}}/ai/chat" method="post">
                    <label class="sr-only" for="agentMessageInput">输入智能体任务</label>
                    <textarea id="agentMessageInput" name="message" rows="3" maxlength="2000" placeholder="交给智能体一个任务，例如：检查当前后台并给出迁移下一步"></textarea>
                    <button type="submit">运行</button>
                  </form>
                </div>
                <aside class="agent-runtime-panel" aria-label="智能体运行状态">
                  <div class="agent-runtime-card">
                    <span>运行模式</span>
                    <strong id="agentRunMode">Standby</strong>
                  </div>
                  <div class="agent-runtime-card">
                    <span>当前目标</span>
                    <strong id="agentRunGoal">等待任务</strong>
                  </div>
                  <div class="agent-runtime-section">
                    <h3>执行计划</h3>
                    <div class="agent-runtime-list" id="agentPlanList">
                      <p>提交任务后生成。</p>
                    </div>
                  </div>
                  <div class="agent-runtime-section">
                    <h3>建议动作</h3>
                    <div class="agent-suggestion-list" id="agentSuggestionList">
                      <button type="button" data-agent-prompt="给出 Moyi Admin 智能体构造方案">智能体方案</button>
                    </div>
                  </div>
                </aside>
              </div>
            </div>
            <div class="admin-panel">
              <div class="admin-panel-head">
                <h2>默认模型服务</h2>
                <span class="admin-badge {{.AIStatusClass}}">{{.AIStatus}}</span>
              </div>
              <dl class="admin-kv">
                <div><dt>服务商</dt><dd>{{.AIProvider}}</dd></div>
                <div><dt>模型</dt><dd class="mono">{{.AIModel}}</dd></div>
                <div><dt>接口</dt><dd class="mono">{{.AITarget}}</dd></div>
              </dl>
            </div>
            <div class="admin-panel admin-panel-wide">
              <div class="admin-panel-head">
                <h2>智能体能力</h2>
                <span class="admin-panel-meta">Guarded Tools</span>
              </div>
              <div class="admin-table">
                <div class="admin-table-row admin-table-head">
                  <span>能力</span><span>执行边界</span><span>状态</span>
                </div>
                {{range .AgentCapabilities}}
                  <div class="admin-table-row">
                    <span>{{.Name}}</span><span>{{.Boundary}}</span><span class="admin-badge {{.StatusClass}}">{{.Status}}</span>
                  </div>
                {{end}}
              </div>
            </div>
            <div class="admin-panel admin-panel-wide">
              <div class="admin-panel-head">
                <h2>最近运行</h2>
                <span class="admin-panel-meta">Agent Runs</span>
              </div>
              <div class="admin-table agent-run-table">
                <div class="admin-table-row admin-table-head">
                  <span>时间</span><span>模式</span><span>目标</span><span>工具</span><span>状态</span>
                </div>
                {{if .AgentRuns}}
                  {{range .AgentRuns}}
                    <div class="admin-table-row">
                      <span>{{.StartedAt}}<small class="mono">{{.Actor}}</small></span>
                      <span><strong class="mono">{{.Mode}}</strong><small>{{.Model}}</small></span>
                      <span>{{.Goal}}<small>{{.Message}}</small></span>
                      <span>{{.ToolCount}} 次<small>文件 {{.FileCount}} · {{.Duration}}</small></span>
                      <span class="admin-badge {{.StatusClass}}">{{.Status}}</span>
                    </div>
                  {{end}}
                {{else}}
                  <div class="admin-table-row">
                    <span>暂无运行记录</span><span>-</span><span>提交一次任务后会写入 agent_runs</span><span>-</span><span class="admin-badge is-muted">等待</span>
                  </div>
                {{end}}
              </div>
            </div>
          </section>
        {{else if eq .Active "users"}}
          {{if .UserNotice}}
            <div class="admin-alert {{.UserNoticeClass}}">{{.UserNotice}}</div>
          {{end}}
          <section class="admin-grid">
            <div class="admin-panel">
              <div class="admin-panel-head">
                <h2>新增管理员</h2>
                <span class="admin-panel-meta">Account</span>
              </div>
              <form class="admin-settings-form" method="post" action="{{.UserSaveAction}}">
                <div class="admin-form-grid">
                  <label>
                    <span>账号</span>
                    <input class="form-control mono" name="username" placeholder="ops_admin" autocomplete="off">
                  </label>
                  <label>
                    <span>显示名称</span>
                    <input class="form-control" name="display_name" placeholder="运维管理员" autocomplete="off">
                  </label>
                  <label>
                    <span>角色</span>
                    <select class="form-select" name="role">
                      {{range .AdminRoles}}
                        <option value="{{.Key}}">{{.Name}}</option>
                      {{end}}
                    </select>
                  </label>
                  <label>
                    <span>状态</span>
                    <select class="form-select" name="status">
                      <option value="enabled">启用</option>
                      <option value="disabled">禁用</option>
                    </select>
                  </label>
                  <label class="admin-form-wide">
                    <span>初始密码</span>
                    <input class="form-control mono" name="password" type="password" placeholder="至少 6 位" autocomplete="new-password">
                    <small class="form-text">同名账号会更新基础信息；密码留空时保留原密码。</small>
                  </label>
                </div>
                <button class="admin-submit-button" type="submit">保存管理员</button>
              </form>
            </div>

            <div class="admin-panel">
              <div class="admin-panel-head">
                <h2>角色清单</h2>
                <span class="admin-panel-meta">Roles</span>
              </div>
              <div class="admin-table access-role-table">
                <div class="admin-table-row admin-table-head">
                  <span>角色</span><span>范围</span><span>状态</span>
                </div>
                {{range .AdminRoles}}
                  <div class="admin-table-row">
                    <span><strong>{{.Name}}</strong><small class="mono">{{.Key}}</small></span>
                    <span>{{.Scope}}<small>{{.Description}}</small></span>
                    <span class="admin-badge {{.StatusClass}}">{{.Status}}</span>
                  </div>
                {{end}}
              </div>
            </div>

            <div class="admin-panel admin-panel-wide">
              <div class="admin-panel-head">
                <h2>管理员账号</h2>
                <span class="admin-panel-meta">Access</span>
              </div>
              <div class="admin-table access-user-table">
                <div class="admin-table-row admin-table-head">
                  <span>账号</span><span>显示名称</span><span>角色</span><span>状态</span><span>最近访问</span><span>操作</span>
                </div>
                {{range .AdminUsers}}
                  <div class="admin-table-row">
                    <span><strong class="mono">{{.Username}}</strong><small>{{.Source}}</small></span>
                    <span>{{.DisplayName}}<small>{{.CreatedAt}}</small></span>
                    <span>{{.Role}}</span>
                    <span class="admin-badge {{.StatusClass}}">{{.Status}}</span>
                    <span>{{.LastSeen}}</span>
                    <span class="admin-file-actions">
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
                        <span class="admin-badge is-muted">内置</span>
                      {{end}}
                    </span>
                  </div>
                {{end}}
              </div>
            </div>

            <div class="admin-panel admin-panel-wide">
              <div class="admin-panel-head">
                <h2>菜单与权限</h2>
                <span class="admin-panel-meta">Menus / Permissions</span>
              </div>
              <div class="admin-table access-menu-table">
                <div class="admin-table-row admin-table-head">
                  <span>菜单</span><span>路径</span><span>状态</span>
                </div>
                {{range .AdminMenus}}
                  <div class="admin-table-row">
                    <span><strong>{{.Label}}</strong><small class="mono">{{.Key}}</small></span>
                    <span class="mono">{{.Path}}</span>
                    <span class="admin-badge {{.StatusClass}}">{{.Status}}</span>
                  </div>
                {{end}}
              </div>
              <div class="admin-table access-permission-table">
                <div class="admin-table-row admin-table-head">
                  <span>权限</span><span>对象</span><span>动作</span><span>边界</span><span>状态</span>
                </div>
                {{range .AdminPermissions}}
                  <div class="admin-table-row">
                    <span class="mono">{{.Key}}</span>
                    <span class="mono">{{.Subject}}</span>
                    <span>{{.Permission}}</span>
                    <span>{{.Boundary}}</span>
                    <span class="admin-badge {{.StatusClass}}">{{.Status}}</span>
                  </div>
                {{end}}
              </div>
            </div>
          </section>
        {{else if eq .Active "settings"}}
          {{if .SettingsNotice}}
            <div class="admin-alert {{.SettingsNoticeClass}}">{{.SettingsNotice}}</div>
          {{end}}
          <section class="admin-grid">
            <div class="admin-panel">
              <div class="admin-panel-head">
                <h2>基础信息</h2>
                <span class="admin-panel-meta">System</span>
              </div>
              <form class="admin-settings-form" method="post" action="{{.SystemSettings.Action}}">
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
                </div>
                <button class="admin-submit-button" type="submit">保存基础信息</button>
              </form>
            </div>

            <div class="admin-panel">
              <div class="admin-panel-head">
                <h2>存储设置</h2>
                <span class="admin-badge {{.StorageSettings.PathStatusClass}}">{{.StorageSettings.PathStatus}}</span>
              </div>
              <form class="admin-settings-form" method="post" action="{{.StorageSettings.Action}}">
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
              </form>
            </div>

            <div class="admin-panel admin-panel-wide">
              <div class="admin-panel-head">
                <h2>运行参数</h2>
                <a class="admin-panel-link" href="{{.BasePath}}/files">进入文件管理</a>
              </div>
              <dl class="admin-kv admin-kv-table">
                {{range .Settings}}
                  <div><dt>{{.Key}}</dt><dd>{{.Value}}</dd></div>
                {{end}}
              </dl>
            </div>
          </section>
        {{else if eq .Active "files"}}
          {{if .FileNotice}}
            <div class="admin-alert {{.FileNoticeClass}}">{{.FileNotice}}</div>
          {{end}}
          <section class="admin-grid">
            <div class="admin-panel">
              <div class="admin-panel-head">
                <h2>上传文件</h2>
                <span class="admin-panel-meta">{{.FileAllowedSummary}}</span>
              </div>
              <form class="admin-settings-form" method="post" action="{{.FileUploadAction}}" enctype="multipart/form-data">
                <label class="admin-upload-box">
                  <span>选择文件</span>
                  <input name="files" type="file" multiple>
                  <small>文件会写入当前本地存储目录，并按日期自动分组。</small>
                </label>
                <button class="admin-submit-button" type="submit">上传文件</button>
              </form>
            </div>

            <div class="admin-panel">
              <div class="admin-panel-head">
                <h2>存储概览</h2>
                <span class="admin-panel-meta">Storage</span>
              </div>
              <dl class="admin-kv">
                <div><dt>存储</dt><dd>{{.FileStorageSummary}}</dd></div>
                <div><dt>限制</dt><dd>{{.FileAllowedSummary}}</dd></div>
                <div><dt>管理</dt><dd>支持上传、预览、下载和删除本地文件</dd></div>
              </dl>
            </div>

            <div class="admin-panel admin-panel-wide">
              <div class="admin-panel-head">
                <h2>文件列表</h2>
                <span class="admin-panel-meta">Local Files</span>
              </div>
              <div class="admin-table file-manager-table">
                <div class="admin-table-row admin-table-head">
                  <span>文件</span><span>类型</span><span>大小</span><span>更新时间</span><span>操作</span>
                </div>
                {{if .FileRows}}
                  {{range .FileRows}}
                    <div class="admin-table-row">
                      <span><strong>{{.Name}}</strong><small class="mono">{{.Path}}</small></span>
                      <span>{{.Kind}}</span>
                      <span>{{.Size}}</span>
                      <span>{{.Modified}}</span>
                      <span class="admin-file-actions">
                        <a href="{{.PreviewURL}}" target="_blank" rel="noreferrer">预览</a>
                        <a href="{{.DownloadURL}}">下载</a>
                        <form method="post" action="{{.DeleteAction}}">
                          <input type="hidden" name="path" value="{{.Path}}">
                          <button type="submit">删除</button>
                        </form>
                      </span>
                    </div>
                  {{end}}
                {{else}}
                  <div class="admin-table-row admin-empty-row">
                    <span>暂无文件，上传后会出现在这里。</span><span></span><span></span><span></span><span></span>
                  </div>
                {{end}}
              </div>
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
              <span class="admin-panel-meta">Audit</span>
            </div>
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
  <script src="/assets/js/admin-agent.js?v=20260514-agent1"></script>
</body>
</html>`))
