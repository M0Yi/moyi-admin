package api

import (
	"context"
	"database/sql"
	"encoding/json"
	"errors"
	"io"
	"os"
	"path/filepath"
	"strings"
	"time"

	_ "modernc.org/sqlite"
)

const metadataSchemaVersion = 1

func (s *installStore) sqlitePath() string {
	path := strings.TrimSpace(s.path)
	if path == "" {
		return ""
	}
	switch strings.ToLower(filepath.Ext(path)) {
	case ".db", ".sqlite", ".sqlite3":
		return path
	default:
		return filepath.Join(filepath.Dir(path), "moyi-admin-meta.db")
	}
}

func (s *installStore) openSQLiteLocked() (*sql.DB, error) {
	dbPath := s.sqlitePath()
	if dbPath == "" {
		return nil, errors.New("metadata sqlite path is empty")
	}
	if err := os.MkdirAll(filepath.Dir(dbPath), 0o755); err != nil {
		return nil, err
	}
	db, err := sql.Open("sqlite", dbPath)
	if err != nil {
		return nil, err
	}
	db.SetMaxOpenConns(1)
	if err := ensureMetadataSchema(db); err != nil {
		_ = db.Close()
		return nil, err
	}
	return db, nil
}

func ensureMetadataSchema(db *sql.DB) error {
	ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
	defer cancel()
	statements := []string{
		`PRAGMA journal_mode = WAL`,
		`PRAGMA busy_timeout = 5000`,
		`CREATE TABLE IF NOT EXISTS schema_migrations (
			version INTEGER PRIMARY KEY,
			applied_at TEXT NOT NULL
		)`,
		`CREATE TABLE IF NOT EXISTS install_state (
			id INTEGER PRIMARY KEY CHECK (id = 1),
			initialized INTEGER NOT NULL,
			site_name TEXT NOT NULL,
			admin_entry TEXT NOT NULL,
			admin_user TEXT NOT NULL,
			database_driver TEXT NOT NULL,
			database_host TEXT NOT NULL DEFAULT '',
			database_port TEXT NOT NULL DEFAULT '',
			database_name TEXT NOT NULL DEFAULT '',
			database_username TEXT NOT NULL DEFAULT '',
			database_password TEXT NOT NULL DEFAULT '',
			database_ssl_mode TEXT NOT NULL DEFAULT '',
			database_file_path TEXT NOT NULL DEFAULT '',
			ai_provider TEXT NOT NULL DEFAULT '',
			ai_api_key TEXT NOT NULL DEFAULT '',
			ai_base_url TEXT NOT NULL DEFAULT '',
			ai_chat_model TEXT NOT NULL DEFAULT '',
			system_timezone TEXT NOT NULL DEFAULT '',
			system_locale TEXT NOT NULL DEFAULT '',
			storage_driver TEXT NOT NULL DEFAULT '',
			storage_local_path TEXT NOT NULL DEFAULT '',
			storage_public_url TEXT NOT NULL DEFAULT '',
			storage_max_file_size_mb INTEGER NOT NULL DEFAULT 0,
			storage_allowed_extensions TEXT NOT NULL DEFAULT '',
			storage_agent_export_retention_days INTEGER NOT NULL DEFAULT 0,
			password_salt TEXT NOT NULL DEFAULT '',
			password_hash TEXT NOT NULL DEFAULT '',
			installed_at TEXT NOT NULL DEFAULT ''
		)`,
		`CREATE TABLE IF NOT EXISTS admin_users (
			username TEXT PRIMARY KEY,
			display_name TEXT NOT NULL,
			role TEXT NOT NULL,
			status TEXT NOT NULL,
			password_salt TEXT NOT NULL DEFAULT '',
			password_hash TEXT NOT NULL DEFAULT '',
			source TEXT NOT NULL DEFAULT '',
			created_at TEXT NOT NULL DEFAULT '',
			updated_at TEXT NOT NULL DEFAULT '',
			last_login_at TEXT NOT NULL DEFAULT ''
		)`,
		`CREATE TABLE IF NOT EXISTS admin_roles (
			key TEXT PRIMARY KEY,
			name TEXT NOT NULL,
			scope TEXT NOT NULL DEFAULT '',
			status TEXT NOT NULL,
			description TEXT NOT NULL DEFAULT ''
		)`,
		`CREATE TABLE IF NOT EXISTS admin_menus (
			key TEXT PRIMARY KEY,
			label TEXT NOT NULL,
			path TEXT NOT NULL,
			status TEXT NOT NULL
		)`,
		`CREATE TABLE IF NOT EXISTS admin_permissions (
			key TEXT PRIMARY KEY,
			subject TEXT NOT NULL,
			permission TEXT NOT NULL,
			boundary TEXT NOT NULL DEFAULT '',
			status TEXT NOT NULL
		)`,
		`CREATE TABLE IF NOT EXISTS data_sources (
			name TEXT PRIMARY KEY,
			driver TEXT NOT NULL,
			host TEXT NOT NULL DEFAULT '',
			port TEXT NOT NULL DEFAULT '',
			database_name TEXT NOT NULL DEFAULT '',
			username TEXT NOT NULL DEFAULT '',
			password TEXT NOT NULL DEFAULT '',
			ssl_mode TEXT NOT NULL DEFAULT '',
			file_path TEXT NOT NULL DEFAULT '',
			role TEXT NOT NULL DEFAULT '',
			status TEXT NOT NULL DEFAULT '',
			last_message TEXT NOT NULL DEFAULT '',
			schema_summary TEXT NOT NULL DEFAULT '',
			last_checked_at TEXT NOT NULL DEFAULT ''
		)`,
		`CREATE TABLE IF NOT EXISTS audit_events (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			timestamp TEXT NOT NULL,
			category TEXT NOT NULL,
			action TEXT NOT NULL,
			actor TEXT NOT NULL,
			detail TEXT NOT NULL,
			method TEXT NOT NULL DEFAULT '',
			path TEXT NOT NULL DEFAULT '',
			ip TEXT NOT NULL DEFAULT '',
			user_agent TEXT NOT NULL DEFAULT '',
			status_code INTEGER NOT NULL DEFAULT 0,
			duration_ms INTEGER NOT NULL DEFAULT 0
		)`,
		`CREATE TABLE IF NOT EXISTS agent_sessions (
			id TEXT PRIMARY KEY,
			title TEXT NOT NULL DEFAULT '',
			actor TEXT NOT NULL DEFAULT '',
			started_at TEXT NOT NULL DEFAULT '',
			updated_at TEXT NOT NULL DEFAULT '',
			last_message TEXT NOT NULL DEFAULT '',
			run_count INTEGER NOT NULL DEFAULT 0
		)`,
		`CREATE TABLE IF NOT EXISTS agent_runs (
			id TEXT PRIMARY KEY,
			session_id TEXT NOT NULL DEFAULT '',
			actor TEXT NOT NULL DEFAULT '',
			started_at TEXT NOT NULL DEFAULT '',
			mode TEXT NOT NULL DEFAULT '',
			goal TEXT NOT NULL DEFAULT '',
			message TEXT NOT NULL DEFAULT '',
			reply TEXT NOT NULL DEFAULT '',
			status TEXT NOT NULL DEFAULT '',
			model_used INTEGER NOT NULL DEFAULT 0,
			tool_count INTEGER NOT NULL DEFAULT 0,
			file_count INTEGER NOT NULL DEFAULT 0,
			duration_ms INTEGER NOT NULL DEFAULT 0,
			metadata_json TEXT NOT NULL DEFAULT '',
			plan_json TEXT NOT NULL DEFAULT '',
			trace_json TEXT NOT NULL DEFAULT '',
			insights_json TEXT NOT NULL DEFAULT '',
			suggestions_json TEXT NOT NULL DEFAULT ''
		)`,
		`CREATE TABLE IF NOT EXISTS agent_tool_results (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			run_id TEXT NOT NULL,
			tool_index INTEGER NOT NULL DEFAULT 0,
			name TEXT NOT NULL DEFAULT '',
			ok INTEGER NOT NULL DEFAULT 0,
			table_name TEXT NOT NULL DEFAULT '',
			sql_text TEXT NOT NULL DEFAULT '',
			message TEXT NOT NULL DEFAULT '',
			error TEXT NOT NULL DEFAULT '',
			file_name TEXT NOT NULL DEFAULT '',
			file_url TEXT NOT NULL DEFAULT '',
			row_count INTEGER NOT NULL DEFAULT 0,
			columns_json TEXT NOT NULL DEFAULT '',
			result_json TEXT NOT NULL DEFAULT '',
			created_at TEXT NOT NULL DEFAULT ''
		)`,
		`INSERT OR IGNORE INTO schema_migrations(version, applied_at) VALUES(1, ?)`,
		`INSERT OR IGNORE INTO schema_migrations(version, applied_at) VALUES(2, ?)`,
	}
	for _, statement := range statements {
		if strings.Contains(statement, "schema_migrations") && strings.Contains(statement, "?") {
			if _, err := db.ExecContext(ctx, statement, formatStoreTime(time.Now().UTC())); err != nil {
				return err
			}
			continue
		}
		if _, err := db.ExecContext(ctx, statement); err != nil {
			return err
		}
	}
	return nil
}

func (s *installStore) loadSQLiteLocked() (installState, error) {
	db, err := s.openSQLiteLocked()
	if err != nil {
		return installState{}, err
	}
	defer db.Close()

	state, err := loadInstallStateFromDB(db)
	if errors.Is(err, sql.ErrNoRows) {
		legacy, legacyErr := s.loadLegacyJSONLocked()
		if legacyErr != nil {
			return installState{}, legacyErr
		}
		if !legacy.Initialized {
			return installState{}, nil
		}
		if err := saveInstallStateToDB(db, legacy); err != nil {
			return installState{}, err
		}
		return loadInstallStateFromDB(db)
	}
	return state, err
}

func (s *installStore) saveSQLiteLocked(state installState) error {
	db, err := s.openSQLiteLocked()
	if err != nil {
		return err
	}
	defer db.Close()
	return saveInstallStateToDB(db, state)
}

func (s *installStore) loadLegacyJSONLocked() (installState, error) {
	if strings.TrimSpace(s.path) == "" {
		return installState{}, nil
	}
	switch strings.ToLower(filepath.Ext(s.path)) {
	case ".db", ".sqlite", ".sqlite3":
		return installState{}, nil
	}
	file, err := os.Open(s.path)
	if errors.Is(err, os.ErrNotExist) {
		return installState{}, nil
	}
	if err != nil {
		return installState{}, err
	}
	defer file.Close()
	var state installState
	if err := json.NewDecoder(file).Decode(&state); err != nil && !errors.Is(err, io.EOF) {
		return installState{}, err
	}
	return state, nil
}

func loadInstallStateFromDB(db *sql.DB) (installState, error) {
	row := db.QueryRow(`SELECT
		initialized, site_name, admin_entry, admin_user,
		database_driver, database_host, database_port, database_name, database_username, database_password, database_ssl_mode, database_file_path,
		ai_provider, ai_api_key, ai_base_url, ai_chat_model,
		system_timezone, system_locale,
		storage_driver, storage_local_path, storage_public_url, storage_max_file_size_mb, storage_allowed_extensions, storage_agent_export_retention_days,
		password_salt, password_hash, installed_at
		FROM install_state WHERE id = 1`)
	var initialized int
	var installedAt string
	var state installState
	err := row.Scan(
		&initialized, &state.SiteName, &state.AdminEntry, &state.AdminUser,
		&state.Database.Driver, &state.Database.Host, &state.Database.Port, &state.Database.Database, &state.Database.Username, &state.Database.Password, &state.Database.SSLMode, &state.Database.FilePath,
		&state.AI.Provider, &state.AI.APIKey, &state.AI.BaseURL, &state.AI.ChatModel,
		&state.System.Timezone, &state.System.Locale,
		&state.Storage.Driver, &state.Storage.LocalPath, &state.Storage.PublicURL, &state.Storage.MaxFileSizeMB, &state.Storage.AllowedExtensions, &state.Storage.AgentExportRetentionDays,
		&state.PasswordSalt, &state.PasswordHash, &installedAt,
	)
	if err != nil {
		return installState{}, err
	}
	state.Initialized = initialized == 1
	state.InstalledAt = parseStoreTime(installedAt)
	state.Database = state.Database.sanitized()
	state.AI = state.AI.sanitized()
	state.System = state.System.normalized()
	state.Storage = state.Storage.normalized()

	access, err := loadAccessFromDB(db)
	if err != nil {
		return installState{}, err
	}
	state.Access = access.withoutBootstrap(state)
	sources, err := loadDataSourcesFromDB(db)
	if err != nil {
		return installState{}, err
	}
	state.DataSources = sources
	return state, nil
}

func saveInstallStateToDB(db *sql.DB, state installState) error {
	ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
	defer cancel()
	tx, err := db.BeginTx(ctx, nil)
	if err != nil {
		return err
	}
	defer tx.Rollback()
	state.Database = state.Database.sanitized()
	state.AI = state.AI.sanitized()
	state.System = state.System.normalized()
	state.Storage = state.Storage.normalized()
	initialized := 0
	if state.Initialized {
		initialized = 1
	}
	_, err = tx.ExecContext(ctx, `INSERT INTO install_state(
		id, initialized, site_name, admin_entry, admin_user,
		database_driver, database_host, database_port, database_name, database_username, database_password, database_ssl_mode, database_file_path,
		ai_provider, ai_api_key, ai_base_url, ai_chat_model,
		system_timezone, system_locale,
		storage_driver, storage_local_path, storage_public_url, storage_max_file_size_mb, storage_allowed_extensions, storage_agent_export_retention_days,
		password_salt, password_hash, installed_at
	) VALUES(1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
	ON CONFLICT(id) DO UPDATE SET
		initialized=excluded.initialized,
		site_name=excluded.site_name,
		admin_entry=excluded.admin_entry,
		admin_user=excluded.admin_user,
		database_driver=excluded.database_driver,
		database_host=excluded.database_host,
		database_port=excluded.database_port,
		database_name=excluded.database_name,
		database_username=excluded.database_username,
		database_password=excluded.database_password,
		database_ssl_mode=excluded.database_ssl_mode,
		database_file_path=excluded.database_file_path,
		ai_provider=excluded.ai_provider,
		ai_api_key=excluded.ai_api_key,
		ai_base_url=excluded.ai_base_url,
		ai_chat_model=excluded.ai_chat_model,
		system_timezone=excluded.system_timezone,
		system_locale=excluded.system_locale,
		storage_driver=excluded.storage_driver,
		storage_local_path=excluded.storage_local_path,
		storage_public_url=excluded.storage_public_url,
		storage_max_file_size_mb=excluded.storage_max_file_size_mb,
		storage_allowed_extensions=excluded.storage_allowed_extensions,
		storage_agent_export_retention_days=excluded.storage_agent_export_retention_days,
		password_salt=excluded.password_salt,
		password_hash=excluded.password_hash,
		installed_at=excluded.installed_at`,
		initialized, state.SiteName, state.AdminEntry, state.AdminUser,
		state.Database.Driver, state.Database.Host, state.Database.Port, state.Database.Database, state.Database.Username, state.Database.Password, state.Database.SSLMode, state.Database.FilePath,
		state.AI.Provider, state.AI.APIKey, state.AI.BaseURL, state.AI.ChatModel,
		state.System.Timezone, state.System.Locale,
		state.Storage.Driver, state.Storage.LocalPath, state.Storage.PublicURL, state.Storage.MaxFileSizeMB, state.Storage.AllowedExtensions, state.Storage.AgentExportRetentionDays,
		state.PasswordSalt, state.PasswordHash, formatStoreTime(state.InstalledAt),
	)
	if err != nil {
		return err
	}
	if err := replaceAccessRows(ctx, tx, state.Access.normalized(state)); err != nil {
		return err
	}
	if err := replaceDataSourceRows(ctx, tx, normalizeDataSources(state.DataSources)); err != nil {
		return err
	}
	return tx.Commit()
}

func loadAccessFromDB(db *sql.DB) (accessConfig, error) {
	roles, err := queryRows(db, `SELECT key, name, scope, status, description FROM admin_roles ORDER BY key`, func(rows *sql.Rows) (adminRoleConfig, error) {
		var role adminRoleConfig
		err := rows.Scan(&role.Key, &role.Name, &role.Scope, &role.Status, &role.Description)
		return role, err
	})
	if err != nil {
		return accessConfig{}, err
	}
	menus, err := queryRows(db, `SELECT key, label, path, status FROM admin_menus ORDER BY key`, func(rows *sql.Rows) (adminMenuConfig, error) {
		var menu adminMenuConfig
		err := rows.Scan(&menu.Key, &menu.Label, &menu.Path, &menu.Status)
		return menu, err
	})
	if err != nil {
		return accessConfig{}, err
	}
	permissions, err := queryRows(db, `SELECT key, subject, permission, boundary, status FROM admin_permissions ORDER BY key`, func(rows *sql.Rows) (adminPermissionConfig, error) {
		var permission adminPermissionConfig
		err := rows.Scan(&permission.Key, &permission.Subject, &permission.Permission, &permission.Boundary, &permission.Status)
		return permission, err
	})
	if err != nil {
		return accessConfig{}, err
	}
	users, err := queryRows(db, `SELECT username, display_name, role, status, password_salt, password_hash, source, created_at, updated_at, last_login_at FROM admin_users ORDER BY username`, func(rows *sql.Rows) (adminAccountConfig, error) {
		var user adminAccountConfig
		var createdAt, updatedAt, lastLoginAt string
		err := rows.Scan(&user.Username, &user.DisplayName, &user.Role, &user.Status, &user.PasswordSalt, &user.PasswordHash, &user.Source, &createdAt, &updatedAt, &lastLoginAt)
		user.CreatedAt = parseStoreTime(createdAt)
		user.UpdatedAt = parseStoreTime(updatedAt)
		user.LastLoginAt = parseStoreTime(lastLoginAt)
		return user, err
	})
	if err != nil {
		return accessConfig{}, err
	}
	return accessConfig{
		Users:       users,
		Roles:       roles,
		Menus:       menus,
		Permissions: permissions,
	}, nil
}

func replaceAccessRows(ctx context.Context, tx *sql.Tx, access accessConfig) error {
	for _, table := range []string{"admin_users", "admin_roles", "admin_menus", "admin_permissions"} {
		if _, err := tx.ExecContext(ctx, "DELETE FROM "+table); err != nil {
			return err
		}
	}
	for _, user := range normalizeAdminAccounts(access.Users) {
		if _, err := tx.ExecContext(ctx, `INSERT INTO admin_users(username, display_name, role, status, password_salt, password_hash, source, created_at, updated_at, last_login_at) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
			user.Username, user.DisplayName, user.Role, user.Status, user.PasswordSalt, user.PasswordHash, user.Source, formatStoreTime(user.CreatedAt), formatStoreTime(user.UpdatedAt), formatStoreTime(user.LastLoginAt)); err != nil {
			return err
		}
	}
	for _, role := range normalizeRoleConfigs(access.Roles) {
		if _, err := tx.ExecContext(ctx, `INSERT INTO admin_roles(key, name, scope, status, description) VALUES(?, ?, ?, ?, ?)`, role.Key, role.Name, role.Scope, role.Status, role.Description); err != nil {
			return err
		}
	}
	for _, menu := range normalizeMenuConfigs(access.Menus) {
		if _, err := tx.ExecContext(ctx, `INSERT INTO admin_menus(key, label, path, status) VALUES(?, ?, ?, ?)`, menu.Key, menu.Label, menu.Path, menu.Status); err != nil {
			return err
		}
	}
	for _, permission := range normalizePermissionConfigs(access.Permissions) {
		if _, err := tx.ExecContext(ctx, `INSERT INTO admin_permissions(key, subject, permission, boundary, status) VALUES(?, ?, ?, ?, ?)`, permission.Key, permission.Subject, permission.Permission, permission.Boundary, permission.Status); err != nil {
			return err
		}
	}
	return nil
}

func loadDataSourcesFromDB(db *sql.DB) ([]dataSourceConfig, error) {
	return queryRows(db, `SELECT name, driver, host, port, database_name, username, password, ssl_mode, file_path, role, status, last_message, schema_summary, last_checked_at FROM data_sources ORDER BY name`, func(rows *sql.Rows) (dataSourceConfig, error) {
		var source dataSourceConfig
		var lastCheckedAt string
		err := rows.Scan(&source.Name, &source.Driver, &source.Host, &source.Port, &source.Database, &source.Username, &source.Password, &source.SSLMode, &source.FilePath, &source.Role, &source.Status, &source.LastMessage, &source.SchemaSummary, &lastCheckedAt)
		source.LastCheckedAt = parseStoreTime(lastCheckedAt)
		return source, err
	})
}

func replaceDataSourceRows(ctx context.Context, tx *sql.Tx, sources []dataSourceConfig) error {
	if _, err := tx.ExecContext(ctx, "DELETE FROM data_sources"); err != nil {
		return err
	}
	for _, source := range sources {
		source = source.normalized()
		if _, err := tx.ExecContext(ctx, `INSERT INTO data_sources(name, driver, host, port, database_name, username, password, ssl_mode, file_path, role, status, last_message, schema_summary, last_checked_at) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
			source.Name, source.Driver, source.Host, source.Port, source.Database, source.Username, source.Password, source.SSLMode, source.FilePath, source.Role, source.Status, source.LastMessage, source.SchemaSummary, formatStoreTime(source.LastCheckedAt)); err != nil {
			return err
		}
	}
	return nil
}

func (s *installStore) AppendAuditRecord(record adminAuditRecord) error {
	s.mu.Lock()
	defer s.mu.Unlock()
	if s.path == "" {
		return nil
	}
	db, err := s.openSQLiteLocked()
	if err != nil {
		return err
	}
	defer db.Close()
	_, err = db.Exec(`INSERT INTO audit_events(timestamp, category, action, actor, detail, method, path, ip, user_agent, status_code, duration_ms) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
		formatStoreTime(record.Timestamp), record.Category, record.Action, record.Actor, record.Detail, record.Method, record.Path, record.IP, record.UserAgent, record.StatusCode, record.DurationMS)
	return err
}

func (s *installStore) ListAuditEvents(limit int) ([]adminAuditEvent, error) {
	s.mu.Lock()
	defer s.mu.Unlock()
	if s.path == "" {
		return nil, nil
	}
	db, err := s.openSQLiteLocked()
	if err != nil {
		return nil, err
	}
	defer db.Close()
	query := `SELECT timestamp, category, action, actor, detail, method, path, ip, user_agent, status_code, duration_ms FROM audit_events ORDER BY id DESC`
	args := []any{}
	if limit > 0 {
		query += ` LIMIT ?`
		args = append(args, limit)
	}
	records, err := queryRows(db, query, func(rows *sql.Rows) (adminAuditRecord, error) {
		var record adminAuditRecord
		var timestamp string
		err := rows.Scan(&timestamp, &record.Category, &record.Action, &record.Actor, &record.Detail, &record.Method, &record.Path, &record.IP, &record.UserAgent, &record.StatusCode, &record.DurationMS)
		record.Timestamp = parseStoreTime(timestamp)
		return record, err
	}, args...)
	if err != nil {
		return nil, err
	}
	events := make([]adminAuditEvent, 0, len(records))
	for _, record := range records {
		events = append(events, record.toAdminAuditEvent())
	}
	return events, nil
}

func (s *installStore) AppendAgentRun(record agentRunRecord) error {
	s.mu.Lock()
	defer s.mu.Unlock()
	if s.path == "" || strings.TrimSpace(record.ID) == "" {
		return nil
	}
	db, err := s.openSQLiteLocked()
	if err != nil {
		return err
	}
	defer db.Close()

	ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
	defer cancel()
	tx, err := db.BeginTx(ctx, nil)
	if err != nil {
		return err
	}
	defer tx.Rollback()

	record.SessionID = normalizeAgentSessionID(record.SessionID)
	if record.SessionID == "" {
		record.SessionID = "session-" + record.ID
	}
	if record.StartedAt.IsZero() {
		record.StartedAt = time.Now().UTC()
	}
	title := truncateAuditText(record.Message, 80)
	if title == "" {
		title = record.Goal
	}
	modelUsed := 0
	if record.ModelUsed {
		modelUsed = 1
	}
	if record.ToolCount == 0 && len(record.ToolResults) > 0 {
		record.ToolCount = len(record.ToolResults)
	}
	if record.FileCount == 0 && len(record.Files) > 0 {
		record.FileCount = len(record.Files)
	}
	_, err = tx.ExecContext(ctx, `INSERT INTO agent_sessions(id, title, actor, started_at, updated_at, last_message, run_count)
		VALUES(?, ?, ?, ?, ?, ?, 1)
		ON CONFLICT(id) DO UPDATE SET
			title=CASE WHEN agent_sessions.title = '' THEN excluded.title ELSE agent_sessions.title END,
			actor=excluded.actor,
			updated_at=excluded.updated_at,
			last_message=excluded.last_message,
			run_count=agent_sessions.run_count + 1`,
		record.SessionID, title, record.Actor, formatStoreTime(record.StartedAt), formatStoreTime(record.StartedAt), truncateAuditText(record.Message, 220))
	if err != nil {
		return err
	}

	metadataJSON, err := marshalStoreJSON(record.Run.Metadata)
	if err != nil {
		return err
	}
	planJSON, err := marshalStoreJSON(record.Run.Plan)
	if err != nil {
		return err
	}
	traceJSON, err := marshalStoreJSON(record.Run.Trace)
	if err != nil {
		return err
	}
	insightsJSON, err := marshalStoreJSON(record.Run.Insights)
	if err != nil {
		return err
	}
	suggestionsJSON, err := marshalStoreJSON(record.Run.Suggestions)
	if err != nil {
		return err
	}
	_, err = tx.ExecContext(ctx, `INSERT INTO agent_runs(
		id, session_id, actor, started_at, mode, goal, message, reply, status, model_used,
		tool_count, file_count, duration_ms, metadata_json, plan_json, trace_json, insights_json, suggestions_json
	) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
	ON CONFLICT(id) DO UPDATE SET
		session_id=excluded.session_id,
		actor=excluded.actor,
		started_at=excluded.started_at,
		mode=excluded.mode,
		goal=excluded.goal,
		message=excluded.message,
		reply=excluded.reply,
		status=excluded.status,
		model_used=excluded.model_used,
		tool_count=excluded.tool_count,
		file_count=excluded.file_count,
		duration_ms=excluded.duration_ms,
		metadata_json=excluded.metadata_json,
		plan_json=excluded.plan_json,
		trace_json=excluded.trace_json,
		insights_json=excluded.insights_json,
		suggestions_json=excluded.suggestions_json`,
		record.ID, record.SessionID, record.Actor, formatStoreTime(record.StartedAt), record.Mode, record.Goal, truncateAuditText(record.Message, 2000), truncateAuditText(record.Reply, 4000), record.Status, modelUsed,
		record.ToolCount, record.FileCount, record.DurationMS, metadataJSON, planJSON, traceJSON, insightsJSON, suggestionsJSON)
	if err != nil {
		return err
	}
	if _, err := tx.ExecContext(ctx, `DELETE FROM agent_tool_results WHERE run_id = ?`, record.ID); err != nil {
		return err
	}
	for index, result := range record.ToolResults {
		ok := 0
		if result.OK {
			ok = 1
		}
		fileName := ""
		fileURL := ""
		if result.File != nil {
			fileName = result.File.Name
			fileURL = result.File.URL
		}
		columnsJSON, err := marshalStoreJSON(result.Columns)
		if err != nil {
			return err
		}
		resultJSON, err := marshalStoreJSON(result)
		if err != nil {
			return err
		}
		if _, err := tx.ExecContext(ctx, `INSERT INTO agent_tool_results(run_id, tool_index, name, ok, table_name, sql_text, message, error, file_name, file_url, row_count, columns_json, result_json, created_at)
			VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
			record.ID, index, result.Name, ok, result.Table, result.SQL, truncateAuditText(result.Message, 1000), truncateAuditText(result.Error, 1000), fileName, fileURL, len(result.Rows), columnsJSON, resultJSON, formatStoreTime(record.StartedAt)); err != nil {
			return err
		}
	}
	return tx.Commit()
}

func (s *installStore) ListAgentSessions(limit int) ([]agentSessionRecord, error) {
	s.mu.Lock()
	defer s.mu.Unlock()
	if s.path == "" {
		return nil, nil
	}
	db, err := s.openSQLiteLocked()
	if err != nil {
		return nil, err
	}
	defer db.Close()
	query := `SELECT id, title, actor, started_at, updated_at, last_message, run_count FROM agent_sessions ORDER BY updated_at DESC, id DESC`
	args := []any{}
	if limit > 0 {
		query += ` LIMIT ?`
		args = append(args, limit)
	}
	return queryRows(db, query, func(rows *sql.Rows) (agentSessionRecord, error) {
		var record agentSessionRecord
		var startedAt, updatedAt string
		err := rows.Scan(&record.ID, &record.Title, &record.Actor, &startedAt, &updatedAt, &record.LastMessage, &record.RunCount)
		record.StartedAt = parseStoreTime(startedAt)
		record.UpdatedAt = parseStoreTime(updatedAt)
		return record, err
	}, args...)
}

func (s *installStore) ListAgentRuns(limit int) ([]agentRunRecord, error) {
	s.mu.Lock()
	defer s.mu.Unlock()
	if s.path == "" {
		return nil, nil
	}
	db, err := s.openSQLiteLocked()
	if err != nil {
		return nil, err
	}
	defer db.Close()
	query := `SELECT id, session_id, actor, started_at, mode, goal, message, reply, status, model_used, tool_count, file_count, duration_ms FROM agent_runs ORDER BY started_at DESC, id DESC`
	args := []any{}
	if limit > 0 {
		query += ` LIMIT ?`
		args = append(args, limit)
	}
	return queryRows(db, query, func(rows *sql.Rows) (agentRunRecord, error) {
		var record agentRunRecord
		var startedAt string
		var modelUsed int
		err := rows.Scan(&record.ID, &record.SessionID, &record.Actor, &startedAt, &record.Mode, &record.Goal, &record.Message, &record.Reply, &record.Status, &modelUsed, &record.ToolCount, &record.FileCount, &record.DurationMS)
		record.StartedAt = parseStoreTime(startedAt)
		record.ModelUsed = modelUsed == 1
		return record, err
	}, args...)
}

func (s *installStore) ListAgentToolResults(limit int) ([]agentToolResultRecord, error) {
	s.mu.Lock()
	defer s.mu.Unlock()
	if s.path == "" {
		return nil, nil
	}
	db, err := s.openSQLiteLocked()
	if err != nil {
		return nil, err
	}
	defer db.Close()
	query := `SELECT run_id, tool_index, name, ok, table_name, sql_text, message, error, file_name, file_url, row_count, columns_json, created_at FROM agent_tool_results ORDER BY id DESC`
	args := []any{}
	if limit > 0 {
		query += ` LIMIT ?`
		args = append(args, limit)
	}
	return queryRows(db, query, func(rows *sql.Rows) (agentToolResultRecord, error) {
		var record agentToolResultRecord
		var ok int
		var createdAt string
		err := rows.Scan(&record.RunID, &record.Index, &record.Name, &ok, &record.Table, &record.SQL, &record.Message, &record.Error, &record.FileName, &record.FileURL, &record.RowCount, &record.Columns, &createdAt)
		record.OK = ok == 1
		record.CreatedAt = parseStoreTime(createdAt)
		return record, err
	}, args...)
}

func marshalStoreJSON(value any) (string, error) {
	if value == nil {
		return "", nil
	}
	payload, err := json.Marshal(value)
	if err != nil {
		return "", err
	}
	return string(payload), nil
}

func queryRows[T any](db *sql.DB, query string, scan func(*sql.Rows) (T, error), args ...any) ([]T, error) {
	rows, err := db.Query(query, args...)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	out := []T{}
	for rows.Next() {
		item, err := scan(rows)
		if err != nil {
			return nil, err
		}
		out = append(out, item)
	}
	return out, rows.Err()
}

func formatStoreTime(t time.Time) string {
	if t.IsZero() {
		return ""
	}
	return t.UTC().Format(time.RFC3339Nano)
}

func parseStoreTime(value string) time.Time {
	value = strings.TrimSpace(value)
	if value == "" {
		return time.Time{}
	}
	for _, layout := range []string{time.RFC3339Nano, time.RFC3339, "2006-01-02 15:04:05"} {
		if parsed, err := time.Parse(layout, value); err == nil {
			return parsed
		}
	}
	return time.Time{}
}
