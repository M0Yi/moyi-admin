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

const metadataSchemaVersion = 20

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
			debug_login_password TEXT NOT NULL DEFAULT '',
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
			system_admin_tagline TEXT NOT NULL DEFAULT '',
			system_public_tagline TEXT NOT NULL DEFAULT '',
			system_public_headline TEXT NOT NULL DEFAULT '',
			system_public_description TEXT NOT NULL DEFAULT '',
			storage_driver TEXT NOT NULL DEFAULT '',
			storage_local_path TEXT NOT NULL DEFAULT '',
			storage_public_url TEXT NOT NULL DEFAULT '',
			storage_max_file_size_mb INTEGER NOT NULL DEFAULT 0,
			storage_allowed_extensions TEXT NOT NULL DEFAULT '',
			storage_agent_export_retention_days INTEGER NOT NULL DEFAULT 0,
			security_session_ttl_hours INTEGER NOT NULL DEFAULT 0,
			security_login_max_attempts INTEGER NOT NULL DEFAULT 0,
			security_login_lock_minutes INTEGER NOT NULL DEFAULT 0,
			notification_enabled INTEGER NOT NULL DEFAULT 0,
			notification_channel TEXT NOT NULL DEFAULT '',
			notification_receiver TEXT NOT NULL DEFAULT '',
			notification_webhook_url TEXT NOT NULL DEFAULT '',
			notification_feishu_secret TEXT NOT NULL DEFAULT '',
			notification_event_login_failures INTEGER NOT NULL DEFAULT 0,
			notification_event_ai_errors INTEGER NOT NULL DEFAULT 0,
			notification_event_storage_warning INTEGER NOT NULL DEFAULT 0,
			task_worker_enabled INTEGER NOT NULL DEFAULT 1,
			task_worker_interval_seconds INTEGER NOT NULL DEFAULT 0,
			task_worker_batch_size INTEGER NOT NULL DEFAULT 0,
			task_schedule_health_enabled INTEGER NOT NULL DEFAULT 1,
			task_schedule_health_minutes INTEGER NOT NULL DEFAULT 0,
			task_schedule_cleanup_enabled INTEGER NOT NULL DEFAULT 1,
			task_schedule_cleanup_minutes INTEGER NOT NULL DEFAULT 0,
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
		`CREATE TABLE IF NOT EXISTS admin_sessions (
			id TEXT PRIMARY KEY,
			username TEXT NOT NULL DEFAULT '',
			ip TEXT NOT NULL DEFAULT '',
			user_agent TEXT NOT NULL DEFAULT '',
			status TEXT NOT NULL DEFAULT '',
			created_at TEXT NOT NULL DEFAULT '',
			expires_at TEXT NOT NULL DEFAULT '',
			revoked_at TEXT NOT NULL DEFAULT ''
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
		`CREATE TABLE IF NOT EXISTS schema_snapshots (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				data_source_name TEXT NOT NULL DEFAULT '',
				driver TEXT NOT NULL DEFAULT '',
				target TEXT NOT NULL DEFAULT '',
				summary TEXT NOT NULL DEFAULT '',
				table_count INTEGER NOT NULL DEFAULT 0,
				column_count INTEGER NOT NULL DEFAULT 0,
				schema_hash TEXT NOT NULL DEFAULT '',
				checks_json TEXT NOT NULL DEFAULT '',
				schema_json TEXT NOT NULL DEFAULT '',
				captured_at TEXT NOT NULL DEFAULT ''
			)`,
		`CREATE INDEX IF NOT EXISTS idx_schema_snapshots_data_source ON schema_snapshots(data_source_name, captured_at)`,
		`CREATE INDEX IF NOT EXISTS idx_schema_snapshots_hash ON schema_snapshots(schema_hash)`,
		`CREATE TABLE IF NOT EXISTS setting_change_logs (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				timestamp TEXT NOT NULL DEFAULT '',
				category TEXT NOT NULL DEFAULT '',
				action TEXT NOT NULL DEFAULT '',
				actor TEXT NOT NULL DEFAULT '',
				summary TEXT NOT NULL DEFAULT '',
				before_json TEXT NOT NULL DEFAULT '',
				after_json TEXT NOT NULL DEFAULT ''
			)`,
		`CREATE INDEX IF NOT EXISTS idx_setting_change_logs_timestamp ON setting_change_logs(timestamp)`,
		`CREATE INDEX IF NOT EXISTS idx_setting_change_logs_category ON setting_change_logs(category)`,
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
		`CREATE TABLE IF NOT EXISTS agent_channels (
			key TEXT PRIMARY KEY,
			provider TEXT NOT NULL DEFAULT '',
			enabled INTEGER NOT NULL DEFAULT 0,
			status TEXT NOT NULL DEFAULT '',
			bind_code TEXT NOT NULL DEFAULT '',
			bind_session TEXT NOT NULL DEFAULT '',
			bind_expires_at TEXT NOT NULL DEFAULT '',
			base_url TEXT NOT NULL DEFAULT '',
			bot_type TEXT NOT NULL DEFAULT '',
			login_qrcode TEXT NOT NULL DEFAULT '',
			login_session TEXT NOT NULL DEFAULT '',
			qr_payload TEXT NOT NULL DEFAULT '',
			qr_image_url TEXT NOT NULL DEFAULT '',
			login_message TEXT NOT NULL DEFAULT '',
			provider_token TEXT NOT NULL DEFAULT '',
			account_id TEXT NOT NULL DEFAULT '',
			openclaw_user_id TEXT NOT NULL DEFAULT '',
			sync_buffer TEXT NOT NULL DEFAULT '',
			token TEXT NOT NULL DEFAULT '',
			display_name TEXT NOT NULL DEFAULT '',
			agent_hint TEXT NOT NULL DEFAULT '',
			data_scope TEXT NOT NULL DEFAULT '',
			allowed_tables TEXT NOT NULL DEFAULT '',
			bound_user TEXT NOT NULL DEFAULT '',
			client_info TEXT NOT NULL DEFAULT '',
			last_error TEXT NOT NULL DEFAULT '',
			created_at TEXT NOT NULL DEFAULT '',
			updated_at TEXT NOT NULL DEFAULT '',
			bound_at TEXT NOT NULL DEFAULT '',
			last_message_at TEXT NOT NULL DEFAULT '',
			last_heartbeat_at TEXT NOT NULL DEFAULT '',
			last_outbound_at TEXT NOT NULL DEFAULT ''
		)`,
		`CREATE TABLE IF NOT EXISTS agent_wechat_messages (
			archive_key TEXT PRIMARY KEY,
			channel_key TEXT NOT NULL DEFAULT '',
			channel_name TEXT NOT NULL DEFAULT '',
			provider TEXT NOT NULL DEFAULT '',
			message_id TEXT NOT NULL DEFAULT '',
			session_id TEXT NOT NULL DEFAULT '',
			run_id TEXT NOT NULL DEFAULT '',
			from_user_id TEXT NOT NULL DEFAULT '',
			to_user_id TEXT NOT NULL DEFAULT '',
			inbound_text TEXT NOT NULL DEFAULT '',
			reply_text TEXT NOT NULL DEFAULT '',
			files_json TEXT NOT NULL DEFAULT '',
			status TEXT NOT NULL DEFAULT '',
			error TEXT NOT NULL DEFAULT '',
			model_used INTEGER NOT NULL DEFAULT 0,
			tool_count INTEGER NOT NULL DEFAULT 0,
			file_count INTEGER NOT NULL DEFAULT 0,
			duration_ms INTEGER NOT NULL DEFAULT 0,
			received_at TEXT NOT NULL DEFAULT '',
			replied_at TEXT NOT NULL DEFAULT '',
			created_at TEXT NOT NULL DEFAULT ''
		)`,
		`CREATE INDEX IF NOT EXISTS idx_agent_wechat_messages_channel ON agent_wechat_messages(channel_key, received_at)`,
		`CREATE INDEX IF NOT EXISTS idx_agent_wechat_messages_session ON agent_wechat_messages(session_id, received_at)`,
		`CREATE INDEX IF NOT EXISTS idx_agent_wechat_messages_message ON agent_wechat_messages(message_id)`,
		`CREATE TABLE IF NOT EXISTS notification_deliveries (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			timestamp TEXT NOT NULL,
			event TEXT NOT NULL DEFAULT '',
			title TEXT NOT NULL DEFAULT '',
			receiver TEXT NOT NULL DEFAULT '',
			channel TEXT NOT NULL DEFAULT '',
			target TEXT NOT NULL DEFAULT '',
			message TEXT NOT NULL DEFAULT '',
			status TEXT NOT NULL DEFAULT '',
			status_code INTEGER NOT NULL DEFAULT 0,
			error TEXT NOT NULL DEFAULT ''
		)`,
		`CREATE TABLE IF NOT EXISTS background_tasks (
			id TEXT PRIMARY KEY,
			name TEXT NOT NULL DEFAULT '',
			task_type TEXT NOT NULL DEFAULT '',
			queue TEXT NOT NULL DEFAULT '',
			status TEXT NOT NULL DEFAULT '',
			priority INTEGER NOT NULL DEFAULT 0,
			attempts INTEGER NOT NULL DEFAULT 0,
			max_attempts INTEGER NOT NULL DEFAULT 0,
			payload_json TEXT NOT NULL DEFAULT '',
			result TEXT NOT NULL DEFAULT '',
			last_error TEXT NOT NULL DEFAULT '',
			created_by TEXT NOT NULL DEFAULT '',
			created_at TEXT NOT NULL DEFAULT '',
			available_at TEXT NOT NULL DEFAULT '',
			started_at TEXT NOT NULL DEFAULT '',
			finished_at TEXT NOT NULL DEFAULT '',
			updated_at TEXT NOT NULL DEFAULT ''
		)`,
		`CREATE INDEX IF NOT EXISTS idx_background_tasks_status_available ON background_tasks(status, available_at, priority)`,
		`CREATE INDEX IF NOT EXISTS idx_background_tasks_created_at ON background_tasks(created_at)`,
		`CREATE TABLE IF NOT EXISTS background_task_logs (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			task_id TEXT NOT NULL DEFAULT '',
			timestamp TEXT NOT NULL DEFAULT '',
			level TEXT NOT NULL DEFAULT '',
			event TEXT NOT NULL DEFAULT '',
			message TEXT NOT NULL DEFAULT '',
			status TEXT NOT NULL DEFAULT '',
			attempt INTEGER NOT NULL DEFAULT 0
		)`,
		`CREATE INDEX IF NOT EXISTS idx_background_task_logs_task_id ON background_task_logs(task_id, id)`,
		`CREATE INDEX IF NOT EXISTS idx_background_task_logs_timestamp ON background_task_logs(timestamp)`,
		`INSERT OR IGNORE INTO schema_migrations(version, applied_at) VALUES(1, ?)`,
		`INSERT OR IGNORE INTO schema_migrations(version, applied_at) VALUES(2, ?)`,
		`INSERT OR IGNORE INTO schema_migrations(version, applied_at) VALUES(3, ?)`,
		`INSERT OR IGNORE INTO schema_migrations(version, applied_at) VALUES(4, ?)`,
		`INSERT OR IGNORE INTO schema_migrations(version, applied_at) VALUES(5, ?)`,
		`INSERT OR IGNORE INTO schema_migrations(version, applied_at) VALUES(6, ?)`,
		`INSERT OR IGNORE INTO schema_migrations(version, applied_at) VALUES(7, ?)`,
		`INSERT OR IGNORE INTO schema_migrations(version, applied_at) VALUES(8, ?)`,
		`INSERT OR IGNORE INTO schema_migrations(version, applied_at) VALUES(9, ?)`,
		`INSERT OR IGNORE INTO schema_migrations(version, applied_at) VALUES(10, ?)`,
		`INSERT OR IGNORE INTO schema_migrations(version, applied_at) VALUES(11, ?)`,
		`INSERT OR IGNORE INTO schema_migrations(version, applied_at) VALUES(12, ?)`,
		`INSERT OR IGNORE INTO schema_migrations(version, applied_at) VALUES(13, ?)`,
		`INSERT OR IGNORE INTO schema_migrations(version, applied_at) VALUES(14, ?)`,
		`INSERT OR IGNORE INTO schema_migrations(version, applied_at) VALUES(15, ?)`,
		`INSERT OR IGNORE INTO schema_migrations(version, applied_at) VALUES(16, ?)`,
		`INSERT OR IGNORE INTO schema_migrations(version, applied_at) VALUES(17, ?)`,
		`INSERT OR IGNORE INTO schema_migrations(version, applied_at) VALUES(18, ?)`,
		`INSERT OR IGNORE INTO schema_migrations(version, applied_at) VALUES(19, ?)`,
		`INSERT OR IGNORE INTO schema_migrations(version, applied_at) VALUES(20, ?)`,
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
	for _, column := range []struct {
		name       string
		definition string
	}{
		{name: "system_admin_tagline", definition: "TEXT NOT NULL DEFAULT ''"},
		{name: "system_public_tagline", definition: "TEXT NOT NULL DEFAULT ''"},
		{name: "system_public_headline", definition: "TEXT NOT NULL DEFAULT ''"},
		{name: "system_public_description", definition: "TEXT NOT NULL DEFAULT ''"},
		{name: "security_session_ttl_hours", definition: "INTEGER NOT NULL DEFAULT 0"},
		{name: "security_login_max_attempts", definition: "INTEGER NOT NULL DEFAULT 0"},
		{name: "security_login_lock_minutes", definition: "INTEGER NOT NULL DEFAULT 0"},
		{name: "notification_enabled", definition: "INTEGER NOT NULL DEFAULT 0"},
		{name: "notification_channel", definition: "TEXT NOT NULL DEFAULT ''"},
		{name: "notification_receiver", definition: "TEXT NOT NULL DEFAULT ''"},
		{name: "notification_webhook_url", definition: "TEXT NOT NULL DEFAULT ''"},
		{name: "notification_feishu_secret", definition: "TEXT NOT NULL DEFAULT ''"},
		{name: "notification_event_login_failures", definition: "INTEGER NOT NULL DEFAULT 0"},
		{name: "notification_event_ai_errors", definition: "INTEGER NOT NULL DEFAULT 0"},
		{name: "notification_event_storage_warning", definition: "INTEGER NOT NULL DEFAULT 0"},
		{name: "task_worker_enabled", definition: "INTEGER NOT NULL DEFAULT 1"},
		{name: "task_worker_interval_seconds", definition: "INTEGER NOT NULL DEFAULT 0"},
		{name: "task_worker_batch_size", definition: "INTEGER NOT NULL DEFAULT 0"},
		{name: "task_schedule_health_enabled", definition: "INTEGER NOT NULL DEFAULT 1"},
		{name: "task_schedule_health_minutes", definition: "INTEGER NOT NULL DEFAULT 0"},
		{name: "task_schedule_cleanup_enabled", definition: "INTEGER NOT NULL DEFAULT 1"},
		{name: "task_schedule_cleanup_minutes", definition: "INTEGER NOT NULL DEFAULT 0"},
		{name: "debug_login_password", definition: "TEXT NOT NULL DEFAULT ''"},
	} {
		if err := ensureSQLiteColumn(ctx, db, "install_state", column.name, column.definition); err != nil {
			return err
		}
	}
	for _, column := range []struct {
		name       string
		definition string
	}{
		{name: "bind_session", definition: "TEXT NOT NULL DEFAULT ''"},
		{name: "bind_expires_at", definition: "TEXT NOT NULL DEFAULT ''"},
		{name: "base_url", definition: "TEXT NOT NULL DEFAULT ''"},
		{name: "bot_type", definition: "TEXT NOT NULL DEFAULT ''"},
		{name: "login_qrcode", definition: "TEXT NOT NULL DEFAULT ''"},
		{name: "login_session", definition: "TEXT NOT NULL DEFAULT ''"},
		{name: "qr_payload", definition: "TEXT NOT NULL DEFAULT ''"},
		{name: "qr_image_url", definition: "TEXT NOT NULL DEFAULT ''"},
		{name: "login_message", definition: "TEXT NOT NULL DEFAULT ''"},
		{name: "provider_token", definition: "TEXT NOT NULL DEFAULT ''"},
		{name: "account_id", definition: "TEXT NOT NULL DEFAULT ''"},
		{name: "openclaw_user_id", definition: "TEXT NOT NULL DEFAULT ''"},
		{name: "sync_buffer", definition: "TEXT NOT NULL DEFAULT ''"},
		{name: "data_scope", definition: "TEXT NOT NULL DEFAULT ''"},
		{name: "allowed_tables", definition: "TEXT NOT NULL DEFAULT ''"},
		{name: "last_error", definition: "TEXT NOT NULL DEFAULT ''"},
		{name: "last_heartbeat_at", definition: "TEXT NOT NULL DEFAULT ''"},
		{name: "last_outbound_at", definition: "TEXT NOT NULL DEFAULT ''"},
	} {
		if err := ensureSQLiteColumn(ctx, db, "agent_channels", column.name, column.definition); err != nil {
			return err
		}
	}
	return nil
}

func ensureSQLiteColumn(ctx context.Context, db *sql.DB, table string, column string, definition string) error {
	rows, err := db.QueryContext(ctx, `PRAGMA table_info(`+quoteSQLiteIdentifier(table)+`)`)
	if err != nil {
		return err
	}
	defer rows.Close()
	for rows.Next() {
		var cid int
		var name, columnType string
		var notNull int
		var defaultValue sql.NullString
		var pk int
		if err := rows.Scan(&cid, &name, &columnType, &notNull, &defaultValue, &pk); err != nil {
			return err
		}
		if strings.EqualFold(name, column) {
			return rows.Err()
		}
	}
	if err := rows.Err(); err != nil {
		return err
	}
	_, err = db.ExecContext(ctx, `ALTER TABLE `+quoteSQLiteIdentifier(table)+` ADD COLUMN `+quoteSQLiteIdentifier(column)+` `+definition)
	return err
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

// UpdateAgentWeChatRuntimeChannels is intentionally narrow: message polling,
// heartbeat, pairing and worker error paths must not call Save because Save
// replaces configuration rows such as data permissions.
func (s *installStore) UpdateAgentWeChatRuntimeChannels(runtimeChannels []agentWeChatChannelConfig) error {
	s.mu.Lock()
	defer s.mu.Unlock()

	if s.path == "" {
		channels := s.memory.AgentChannels.normalized()
		originals := make(map[string]agentWeChatChannelConfig, len(channels.WeChats))
		for _, channel := range channels.WeChats {
			normalized := channel.normalized()
			originals[normalized.Key] = normalized
		}
		for _, runtimeChannel := range runtimeChannels {
			upsertAgentWeChatRuntimeChannel(&channels, runtimeChannel, originals)
		}
		s.memory.AgentChannels = channels.normalized()
		return nil
	}

	db, err := s.openSQLiteLocked()
	if err != nil {
		return err
	}
	defer db.Close()
	return updateAgentWeChatRuntimeRows(db, runtimeChannels)
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
		initialized, site_name, admin_entry, admin_user, debug_login_password,
		database_driver, database_host, database_port, database_name, database_username, database_password, database_ssl_mode, database_file_path,
		ai_provider, ai_api_key, ai_base_url, ai_chat_model,
		system_timezone, system_locale, system_admin_tagline, system_public_tagline, system_public_headline, system_public_description,
		storage_driver, storage_local_path, storage_public_url, storage_max_file_size_mb, storage_allowed_extensions, storage_agent_export_retention_days,
		security_session_ttl_hours, security_login_max_attempts, security_login_lock_minutes,
		notification_enabled, notification_channel, notification_receiver, notification_webhook_url, notification_feishu_secret, notification_event_login_failures, notification_event_ai_errors, notification_event_storage_warning,
		task_worker_enabled, task_worker_interval_seconds, task_worker_batch_size, task_schedule_health_enabled, task_schedule_health_minutes, task_schedule_cleanup_enabled, task_schedule_cleanup_minutes,
		password_salt, password_hash, installed_at
		FROM install_state WHERE id = 1`)
	var initialized int
	var installedAt string
	var notificationEnabled, notificationLoginFailures, notificationAIErrors, notificationStorageWarning int
	var taskWorkerEnabled, taskScheduleHealthEnabled, taskScheduleCleanupEnabled int
	var state installState
	err := row.Scan(
		&initialized, &state.SiteName, &state.AdminEntry, &state.AdminUser, &state.DebugLoginPassword,
		&state.Database.Driver, &state.Database.Host, &state.Database.Port, &state.Database.Database, &state.Database.Username, &state.Database.Password, &state.Database.SSLMode, &state.Database.FilePath,
		&state.AI.Provider, &state.AI.APIKey, &state.AI.BaseURL, &state.AI.ChatModel,
		&state.System.Timezone, &state.System.Locale, &state.System.AdminTagline, &state.System.PublicTagline, &state.System.PublicHeadline, &state.System.PublicDescription,
		&state.Storage.Driver, &state.Storage.LocalPath, &state.Storage.PublicURL, &state.Storage.MaxFileSizeMB, &state.Storage.AllowedExtensions, &state.Storage.AgentExportRetentionDays,
		&state.Security.SessionTTLHours, &state.Security.LoginMaxAttempts, &state.Security.LoginLockMinutes,
		&notificationEnabled, &state.Notifications.Channel, &state.Notifications.Receiver, &state.Notifications.WebhookURL, &state.Notifications.FeishuSecret, &notificationLoginFailures, &notificationAIErrors, &notificationStorageWarning,
		&taskWorkerEnabled, &state.TaskWorker.IntervalSeconds, &state.TaskWorker.BatchSize, &taskScheduleHealthEnabled, &state.TaskWorker.ScheduleHealthMinutes, &taskScheduleCleanupEnabled, &state.TaskWorker.ScheduleCleanupMinutes,
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
	state.Security = state.Security.normalized()
	state.Notifications.Enabled = notificationEnabled == 1
	state.Notifications.EventLoginFailures = notificationLoginFailures == 1
	state.Notifications.EventAIErrors = notificationAIErrors == 1
	state.Notifications.EventStorageWarning = notificationStorageWarning == 1
	state.Notifications = state.Notifications.normalized()
	state.TaskWorker.Enabled = taskWorkerEnabled == 1
	state.TaskWorker.ScheduleHealthEnabled = taskScheduleHealthEnabled == 1
	state.TaskWorker.ScheduleCleanupEnabled = taskScheduleCleanupEnabled == 1
	state.TaskWorker = state.TaskWorker.normalized()

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
	channels, err := loadAgentChannelsFromDB(db)
	if err != nil {
		return installState{}, err
	}
	state.AgentChannels = channels
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
	state.Security = state.Security.normalized()
	state.Notifications = state.Notifications.normalized()
	state.TaskWorker = state.TaskWorker.normalized()
	initialized := 0
	if state.Initialized {
		initialized = 1
	}
	notificationEnabled := 0
	if state.Notifications.Enabled {
		notificationEnabled = 1
	}
	notificationLoginFailures := 0
	if state.Notifications.EventLoginFailures {
		notificationLoginFailures = 1
	}
	notificationAIErrors := 0
	if state.Notifications.EventAIErrors {
		notificationAIErrors = 1
	}
	notificationStorageWarning := 0
	if state.Notifications.EventStorageWarning {
		notificationStorageWarning = 1
	}
	taskWorkerEnabled := 0
	if state.TaskWorker.Enabled {
		taskWorkerEnabled = 1
	}
	taskScheduleHealthEnabled := 0
	if state.TaskWorker.ScheduleHealthEnabled {
		taskScheduleHealthEnabled = 1
	}
	taskScheduleCleanupEnabled := 0
	if state.TaskWorker.ScheduleCleanupEnabled {
		taskScheduleCleanupEnabled = 1
	}
	_, err = tx.ExecContext(ctx, `INSERT INTO install_state(
		id, initialized, site_name, admin_entry, admin_user, debug_login_password,
		database_driver, database_host, database_port, database_name, database_username, database_password, database_ssl_mode, database_file_path,
		ai_provider, ai_api_key, ai_base_url, ai_chat_model,
		system_timezone, system_locale, system_admin_tagline, system_public_tagline, system_public_headline, system_public_description,
		storage_driver, storage_local_path, storage_public_url, storage_max_file_size_mb, storage_allowed_extensions, storage_agent_export_retention_days,
		security_session_ttl_hours, security_login_max_attempts, security_login_lock_minutes,
		notification_enabled, notification_channel, notification_receiver, notification_webhook_url, notification_feishu_secret, notification_event_login_failures, notification_event_ai_errors, notification_event_storage_warning,
		task_worker_enabled, task_worker_interval_seconds, task_worker_batch_size, task_schedule_health_enabled, task_schedule_health_minutes, task_schedule_cleanup_enabled, task_schedule_cleanup_minutes,
		password_salt, password_hash, installed_at
	) VALUES(1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
	ON CONFLICT(id) DO UPDATE SET
		initialized=excluded.initialized,
		site_name=excluded.site_name,
		admin_entry=excluded.admin_entry,
		admin_user=excluded.admin_user,
		debug_login_password=excluded.debug_login_password,
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
		system_admin_tagline=excluded.system_admin_tagline,
		system_public_tagline=excluded.system_public_tagline,
		system_public_headline=excluded.system_public_headline,
		system_public_description=excluded.system_public_description,
		storage_driver=excluded.storage_driver,
		storage_local_path=excluded.storage_local_path,
		storage_public_url=excluded.storage_public_url,
		storage_max_file_size_mb=excluded.storage_max_file_size_mb,
		storage_allowed_extensions=excluded.storage_allowed_extensions,
		storage_agent_export_retention_days=excluded.storage_agent_export_retention_days,
		security_session_ttl_hours=excluded.security_session_ttl_hours,
		security_login_max_attempts=excluded.security_login_max_attempts,
		security_login_lock_minutes=excluded.security_login_lock_minutes,
		notification_enabled=excluded.notification_enabled,
		notification_channel=excluded.notification_channel,
		notification_receiver=excluded.notification_receiver,
		notification_webhook_url=excluded.notification_webhook_url,
		notification_feishu_secret=excluded.notification_feishu_secret,
		notification_event_login_failures=excluded.notification_event_login_failures,
		notification_event_ai_errors=excluded.notification_event_ai_errors,
		notification_event_storage_warning=excluded.notification_event_storage_warning,
		task_worker_enabled=excluded.task_worker_enabled,
		task_worker_interval_seconds=excluded.task_worker_interval_seconds,
		task_worker_batch_size=excluded.task_worker_batch_size,
		task_schedule_health_enabled=excluded.task_schedule_health_enabled,
		task_schedule_health_minutes=excluded.task_schedule_health_minutes,
		task_schedule_cleanup_enabled=excluded.task_schedule_cleanup_enabled,
		task_schedule_cleanup_minutes=excluded.task_schedule_cleanup_minutes,
		password_salt=excluded.password_salt,
		password_hash=excluded.password_hash,
		installed_at=excluded.installed_at`,
		initialized, state.SiteName, state.AdminEntry, state.AdminUser, state.DebugLoginPassword,
		state.Database.Driver, state.Database.Host, state.Database.Port, state.Database.Database, state.Database.Username, state.Database.Password, state.Database.SSLMode, state.Database.FilePath,
		state.AI.Provider, state.AI.APIKey, state.AI.BaseURL, state.AI.ChatModel,
		state.System.Timezone, state.System.Locale, state.System.AdminTagline, state.System.PublicTagline, state.System.PublicHeadline, state.System.PublicDescription,
		state.Storage.Driver, state.Storage.LocalPath, state.Storage.PublicURL, state.Storage.MaxFileSizeMB, state.Storage.AllowedExtensions, state.Storage.AgentExportRetentionDays,
		state.Security.SessionTTLHours, state.Security.LoginMaxAttempts, state.Security.LoginLockMinutes,
		notificationEnabled, state.Notifications.Channel, state.Notifications.Receiver, state.Notifications.WebhookURL, state.Notifications.FeishuSecret, notificationLoginFailures, notificationAIErrors, notificationStorageWarning,
		taskWorkerEnabled, state.TaskWorker.IntervalSeconds, state.TaskWorker.BatchSize, taskScheduleHealthEnabled, state.TaskWorker.ScheduleHealthMinutes, taskScheduleCleanupEnabled, state.TaskWorker.ScheduleCleanupMinutes,
		state.PasswordSalt, state.PasswordHash, formatStoreTime(state.InstalledAt),
	)
	if err != nil {
		return err
	}
	// Full Save rewrites config-owned tables. Keep this path for admin config
	// submissions and initialization only; runtime flows should use append/update
	// helpers that touch only the fields they own.
	if err := replaceAccessRows(ctx, tx, state.Access.normalized(state)); err != nil {
		return err
	}
	if err := replaceDataSourceRows(ctx, tx, normalizeDataSources(state.DataSources)); err != nil {
		return err
	}
	if err := replaceAgentChannelRows(ctx, tx, state.AgentChannels.normalized()); err != nil {
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

func (s *installStore) AppendAdminSession(record adminSessionRecord) error {
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
	_, err = db.Exec(`INSERT OR REPLACE INTO admin_sessions(id, username, ip, user_agent, status, created_at, expires_at, revoked_at) VALUES(?, ?, ?, ?, ?, ?, ?, ?)`,
		record.ID, record.Username, record.IP, record.UserAgent, record.Status, formatStoreTime(record.CreatedAt), formatStoreTime(record.ExpiresAt), formatStoreTime(record.RevokedAt))
	return err
}

func (s *installStore) RevokeAdminSession(id string, revokedAt time.Time) error {
	s.mu.Lock()
	defer s.mu.Unlock()
	if s.path == "" || strings.TrimSpace(id) == "" {
		return nil
	}
	db, err := s.openSQLiteLocked()
	if err != nil {
		return err
	}
	defer db.Close()
	_, err = db.Exec(`UPDATE admin_sessions SET status = 'revoked', revoked_at = ? WHERE id = ?`, formatStoreTime(revokedAt), strings.TrimSpace(id))
	return err
}

func (s *installStore) AdminSessionActive(id string, username string, now time.Time) (bool, error) {
	s.mu.Lock()
	defer s.mu.Unlock()
	if s.path == "" || strings.TrimSpace(id) == "" {
		return true, nil
	}
	db, err := s.openSQLiteLocked()
	if err != nil {
		return false, err
	}
	defer db.Close()
	var status, expiresAt string
	err = db.QueryRow(`SELECT status, expires_at FROM admin_sessions WHERE id = ? AND username = ?`, strings.TrimSpace(id), strings.TrimSpace(username)).Scan(&status, &expiresAt)
	if errors.Is(err, sql.ErrNoRows) {
		return false, nil
	}
	if err != nil {
		return false, err
	}
	if strings.ToLower(strings.TrimSpace(status)) != "active" {
		return false, nil
	}
	expires := parseStoreTime(expiresAt)
	if !expires.IsZero() && !now.Before(expires) {
		return false, nil
	}
	return true, nil
}

func (s *installStore) ListAdminSessions(limit int) ([]adminSessionRecord, error) {
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
	query := `SELECT id, username, ip, user_agent, status, created_at, expires_at, revoked_at FROM admin_sessions ORDER BY created_at DESC`
	args := []any{}
	if limit > 0 {
		query += ` LIMIT ?`
		args = append(args, limit)
	}
	return queryRows(db, query, func(rows *sql.Rows) (adminSessionRecord, error) {
		var record adminSessionRecord
		var createdAt, expiresAt, revokedAt string
		err := rows.Scan(&record.ID, &record.Username, &record.IP, &record.UserAgent, &record.Status, &createdAt, &expiresAt, &revokedAt)
		record.CreatedAt = parseStoreTime(createdAt)
		record.ExpiresAt = parseStoreTime(expiresAt)
		record.RevokedAt = parseStoreTime(revokedAt)
		return record, err
	}, args...)
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

func loadAgentChannelsFromDB(db *sql.DB) (agentChannelConfig, error) {
	rows, err := queryRows(db, `SELECT key, provider, enabled, status, bind_code, bind_session, bind_expires_at, base_url, bot_type, login_qrcode, login_session, qr_payload, qr_image_url, login_message, provider_token, account_id, openclaw_user_id, sync_buffer, token, display_name, agent_hint, data_scope, allowed_tables, bound_user, client_info, last_error, created_at, updated_at, bound_at, last_message_at, last_heartbeat_at, last_outbound_at FROM agent_channels ORDER BY key`, func(rows *sql.Rows) (agentWeChatChannelConfig, error) {
		var key, provider string
		var enabled int
		var allowedTables string
		var bindExpiresAt, createdAt, updatedAt, boundAt, lastMessageAt, lastHeartbeatAt, lastOutboundAt string
		var channel agentWeChatChannelConfig
		err := rows.Scan(&key, &provider, &enabled, &channel.Status, &channel.BindCode, &channel.BindSession, &bindExpiresAt, &channel.BaseURL, &channel.BotType, &channel.LoginQRCode, &channel.LoginSession, &channel.QRPayload, &channel.QRImageURL, &channel.LoginMessage, &channel.ProviderToken, &channel.AccountID, &channel.OpenClawUserID, &channel.SyncBuffer, &channel.Token, &channel.DisplayName, &channel.AgentHint, &channel.DataScope, &allowedTables, &channel.BoundUser, &channel.ClientInfo, &channel.LastError, &createdAt, &updatedAt, &boundAt, &lastMessageAt, &lastHeartbeatAt, &lastOutboundAt)
		if key != "wechat_bind" && provider != "wechat_bind" && provider != agentWeChatProviderID {
			return agentWeChatChannelConfig{}, nil
		}
		channel.Key = normalizeAgentWeChatChannelKey(key)
		channel.Enabled = enabled == 1
		channel.AllowedTables = decodeAgentAllowedTables(allowedTables)
		channel.BindExpiresAt = parseStoreTime(bindExpiresAt)
		channel.CreatedAt = parseStoreTime(createdAt)
		channel.UpdatedAt = parseStoreTime(updatedAt)
		channel.BoundAt = parseStoreTime(boundAt)
		channel.LastMessageAt = parseStoreTime(lastMessageAt)
		channel.LastHeartbeatAt = parseStoreTime(lastHeartbeatAt)
		channel.LastOutboundAt = parseStoreTime(lastOutboundAt)
		return channel, err
	})
	if err != nil {
		return agentChannelConfig{}, err
	}
	var channels agentChannelConfig
	for _, row := range rows {
		if row.Status != "" || row.BindCode != "" || row.Token != "" || row.Enabled {
			channels.WeChats = append(channels.WeChats, row)
		}
	}
	return channels.normalized(), nil
}

func replaceAgentChannelRows(ctx context.Context, tx *sql.Tx, channels agentChannelConfig) error {
	if _, err := tx.ExecContext(ctx, "DELETE FROM agent_channels"); err != nil {
		return err
	}
	for _, wechat := range channels.normalized().WeChats {
		enabled := 0
		if wechat.Enabled {
			enabled = 1
		}
		if wechat.Key == "" {
			wechat.Key = newAgentWeChatChannelKey()
		}
		if _, err := tx.ExecContext(ctx, `INSERT INTO agent_channels(key, provider, enabled, status, bind_code, bind_session, bind_expires_at, base_url, bot_type, login_qrcode, login_session, qr_payload, qr_image_url, login_message, provider_token, account_id, openclaw_user_id, sync_buffer, token, display_name, agent_hint, data_scope, allowed_tables, bound_user, client_info, last_error, created_at, updated_at, bound_at, last_message_at, last_heartbeat_at, last_outbound_at) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
			wechat.Key, agentWeChatProviderID, enabled, wechat.Status, wechat.BindCode, wechat.BindSession, formatStoreTime(wechat.BindExpiresAt), wechat.BaseURL, wechat.BotType, wechat.LoginQRCode, wechat.LoginSession, wechat.QRPayload, wechat.QRImageURL, wechat.LoginMessage, wechat.ProviderToken, wechat.AccountID, wechat.OpenClawUserID, wechat.SyncBuffer, wechat.Token, wechat.DisplayName, wechat.AgentHint, wechat.DataScope, encodeAgentAllowedTables(wechat.AllowedTables), wechat.BoundUser, wechat.ClientInfo, wechat.LastError, formatStoreTime(wechat.CreatedAt), formatStoreTime(wechat.UpdatedAt), formatStoreTime(wechat.BoundAt), formatStoreTime(wechat.LastMessageAt), formatStoreTime(wechat.LastHeartbeatAt), formatStoreTime(wechat.LastOutboundAt)); err != nil {
			return err
		}
	}
	return nil
}

func updateAgentWeChatRuntimeRows(db *sql.DB, runtimeChannels []agentWeChatChannelConfig) error {
	ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
	defer cancel()
	tx, err := db.BeginTx(ctx, nil)
	if err != nil {
		return err
	}
	defer tx.Rollback()
	for _, channel := range runtimeChannels {
		channel = channel.normalized()
		if channel.Key == "" {
			continue
		}
		enabled := 0
		if channel.Enabled {
			enabled = 1
		}
		if _, err := tx.ExecContext(ctx, `UPDATE agent_channels SET
			enabled = ?,
			status = ?,
			bind_code = ?,
			bind_session = ?,
			bind_expires_at = ?,
			base_url = ?,
			bot_type = ?,
			login_qrcode = ?,
			login_session = ?,
			qr_payload = ?,
			qr_image_url = ?,
			login_message = ?,
			provider_token = ?,
			account_id = ?,
			openclaw_user_id = ?,
			sync_buffer = ?,
			token = ?,
			display_name = ?,
			agent_hint = ?,
			bound_user = ?,
			client_info = ?,
			last_error = ?,
			created_at = ?,
			updated_at = ?,
			bound_at = ?,
			last_message_at = ?,
			last_heartbeat_at = ?,
			last_outbound_at = ?
			WHERE key = ?`,
			enabled, channel.Status, channel.BindCode, channel.BindSession, formatStoreTime(channel.BindExpiresAt), channel.BaseURL, channel.BotType, channel.LoginQRCode, channel.LoginSession, channel.QRPayload, channel.QRImageURL, channel.LoginMessage, channel.ProviderToken, channel.AccountID, channel.OpenClawUserID, channel.SyncBuffer, channel.Token, channel.DisplayName, channel.AgentHint, channel.BoundUser, channel.ClientInfo, channel.LastError, formatStoreTime(channel.CreatedAt), formatStoreTime(channel.UpdatedAt), formatStoreTime(channel.BoundAt), formatStoreTime(channel.LastMessageAt), formatStoreTime(channel.LastHeartbeatAt), formatStoreTime(channel.LastOutboundAt), channel.Key); err != nil {
			return err
		}
	}
	return tx.Commit()
}

func encodeAgentAllowedTables(tables []string) string {
	normalized := normalizeAgentAllowedTables(tables)
	if len(normalized) == 0 {
		return ""
	}
	raw, err := json.Marshal(normalized)
	if err != nil {
		return strings.Join(normalized, ",")
	}
	return string(raw)
}

func decodeAgentAllowedTables(value string) []string {
	value = strings.TrimSpace(value)
	if value == "" {
		return nil
	}
	var tables []string
	if err := json.Unmarshal([]byte(value), &tables); err == nil {
		return normalizeAgentAllowedTables(tables)
	}
	return normalizeAgentAllowedTables([]string{value})
}

func (s *installStore) AppendSchemaSnapshot(record adminSchemaSnapshotRecord) error {
	s.mu.Lock()
	defer s.mu.Unlock()
	if s.path == "" || strings.TrimSpace(record.DataSourceName) == "" {
		return nil
	}
	db, err := s.openSQLiteLocked()
	if err != nil {
		return err
	}
	defer db.Close()
	_, err = db.Exec(`INSERT INTO schema_snapshots(
		data_source_name, driver, target, summary, table_count, column_count, schema_hash, checks_json, schema_json, captured_at
	) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
		record.DataSourceName, record.Driver, record.Target, record.Summary, record.TableCount, record.ColumnCount,
		record.SchemaHash, record.ChecksJSON, record.SchemaJSON, formatStoreTime(record.CapturedAt))
	return err
}

func (s *installStore) ListSchemaSnapshots(limit int) ([]adminSchemaSnapshotRecord, error) {
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
	query := `SELECT id, data_source_name, driver, target, summary, table_count, column_count, schema_hash, checks_json, schema_json, captured_at FROM schema_snapshots ORDER BY captured_at DESC, id DESC`
	args := []any{}
	if limit > 0 {
		query += ` LIMIT ?`
		args = append(args, limit)
	}
	return queryRows(db, query, scanSchemaSnapshotRecord, args...)
}

func scanSchemaSnapshotRecord(rows *sql.Rows) (adminSchemaSnapshotRecord, error) {
	var record adminSchemaSnapshotRecord
	var capturedAt string
	err := rows.Scan(&record.ID, &record.DataSourceName, &record.Driver, &record.Target, &record.Summary, &record.TableCount, &record.ColumnCount, &record.SchemaHash, &record.ChecksJSON, &record.SchemaJSON, &capturedAt)
	record.CapturedAt = parseStoreTime(capturedAt)
	return record, err
}

func (s *installStore) AppendSettingChange(record adminSettingChangeRecord) error {
	s.mu.Lock()
	defer s.mu.Unlock()
	if s.path == "" || strings.TrimSpace(record.Category) == "" {
		return nil
	}
	db, err := s.openSQLiteLocked()
	if err != nil {
		return err
	}
	defer db.Close()
	_, err = db.Exec(`INSERT INTO setting_change_logs(timestamp, category, action, actor, summary, before_json, after_json) VALUES(?, ?, ?, ?, ?, ?, ?)`,
		formatStoreTime(record.Timestamp), record.Category, record.Action, record.Actor, record.Summary, record.BeforeJSON, record.AfterJSON)
	return err
}

func (s *installStore) ListSettingChanges(limit int) ([]adminSettingChangeRecord, error) {
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
	query := `SELECT id, timestamp, category, action, actor, summary, before_json, after_json FROM setting_change_logs ORDER BY timestamp DESC, id DESC`
	args := []any{}
	if limit > 0 {
		query += ` LIMIT ?`
		args = append(args, limit)
	}
	return queryRows(db, query, scanSettingChangeRecord, args...)
}

func scanSettingChangeRecord(rows *sql.Rows) (adminSettingChangeRecord, error) {
	var record adminSettingChangeRecord
	var timestamp string
	err := rows.Scan(&record.ID, &timestamp, &record.Category, &record.Action, &record.Actor, &record.Summary, &record.BeforeJSON, &record.AfterJSON)
	record.Timestamp = parseStoreTime(timestamp)
	return record, err
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

func (s *installStore) AppendNotificationDelivery(record adminNotificationDeliveryRecord) error {
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
	_, err = db.Exec(`INSERT INTO notification_deliveries(timestamp, event, title, receiver, channel, target, message, status, status_code, error) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
		formatStoreTime(record.Timestamp), record.Event, record.Title, record.Receiver, record.Channel, record.Target, record.Message, record.Status, record.StatusCode, record.Error)
	return err
}

func (s *installStore) ListNotificationDeliveries(limit int) ([]adminNotificationDeliveryRecord, error) {
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
	query := `SELECT id, timestamp, event, title, receiver, channel, target, message, status, status_code, error FROM notification_deliveries ORDER BY id DESC`
	args := []any{}
	if limit > 0 {
		query += ` LIMIT ?`
		args = append(args, limit)
	}
	return queryRows(db, query, func(rows *sql.Rows) (adminNotificationDeliveryRecord, error) {
		var record adminNotificationDeliveryRecord
		var timestamp string
		err := rows.Scan(&record.ID, &timestamp, &record.Event, &record.Title, &record.Receiver, &record.Channel, &record.Target, &record.Message, &record.Status, &record.StatusCode, &record.Error)
		record.Timestamp = parseStoreTime(timestamp)
		return record, err
	}, args...)
}

func (s *installStore) EnqueueBackgroundTask(record adminBackgroundTaskRecord) error {
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
	_, err = db.Exec(`INSERT INTO background_tasks(
		id, name, task_type, queue, status, priority, attempts, max_attempts, payload_json, result, last_error, created_by, created_at, available_at, started_at, finished_at, updated_at
	) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
		record.ID, record.Name, record.Type, record.Queue, record.Status, record.Priority, record.Attempts, record.MaxAttempts,
		record.PayloadJSON, record.Result, record.LastError, record.CreatedBy,
		formatStoreTime(record.CreatedAt), formatStoreTime(record.AvailableAt), formatStoreTime(record.StartedAt), formatStoreTime(record.FinishedAt), formatStoreTime(record.UpdatedAt))
	return err
}

func (s *installStore) UpdateBackgroundTask(record adminBackgroundTaskRecord) error {
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
	_, err = db.Exec(`UPDATE background_tasks SET
		name = ?, task_type = ?, queue = ?, status = ?, priority = ?, attempts = ?, max_attempts = ?,
		payload_json = ?, result = ?, last_error = ?, created_by = ?, created_at = ?, available_at = ?,
		started_at = ?, finished_at = ?, updated_at = ?
		WHERE id = ?`,
		record.Name, record.Type, record.Queue, record.Status, record.Priority, record.Attempts, record.MaxAttempts,
		record.PayloadJSON, record.Result, record.LastError, record.CreatedBy,
		formatStoreTime(record.CreatedAt), formatStoreTime(record.AvailableAt), formatStoreTime(record.StartedAt), formatStoreTime(record.FinishedAt), formatStoreTime(record.UpdatedAt),
		record.ID)
	return err
}

func (s *installStore) BackgroundTaskByID(id string) (adminBackgroundTaskRecord, error) {
	s.mu.Lock()
	defer s.mu.Unlock()
	if s.path == "" {
		return adminBackgroundTaskRecord{}, sql.ErrNoRows
	}
	db, err := s.openSQLiteLocked()
	if err != nil {
		return adminBackgroundTaskRecord{}, err
	}
	defer db.Close()
	rows, err := queryRows(db, `SELECT id, name, task_type, queue, status, priority, attempts, max_attempts, payload_json, result, last_error, created_by, created_at, available_at, started_at, finished_at, updated_at FROM background_tasks WHERE id = ? LIMIT 1`, scanBackgroundTaskRecord, strings.TrimSpace(id))
	if err != nil {
		return adminBackgroundTaskRecord{}, err
	}
	if len(rows) == 0 {
		return adminBackgroundTaskRecord{}, sql.ErrNoRows
	}
	return rows[0], nil
}

func (s *installStore) NextRunnableBackgroundTask(now time.Time) (adminBackgroundTaskRecord, error) {
	s.mu.Lock()
	defer s.mu.Unlock()
	if s.path == "" {
		return adminBackgroundTaskRecord{}, sql.ErrNoRows
	}
	db, err := s.openSQLiteLocked()
	if err != nil {
		return adminBackgroundTaskRecord{}, err
	}
	defer db.Close()
	rows, err := queryRows(db, `SELECT id, name, task_type, queue, status, priority, attempts, max_attempts, payload_json, result, last_error, created_by, created_at, available_at, started_at, finished_at, updated_at
		FROM background_tasks
		WHERE status IN ('pending', 'retry') AND (available_at = '' OR available_at <= ?)
		ORDER BY priority DESC, created_at ASC
		LIMIT 1`, scanBackgroundTaskRecord, formatStoreTime(now))
	if err != nil {
		return adminBackgroundTaskRecord{}, err
	}
	if len(rows) == 0 {
		return adminBackgroundTaskRecord{}, sql.ErrNoRows
	}
	return rows[0], nil
}

func (s *installStore) ListBackgroundTasks(limit int) ([]adminBackgroundTaskRecord, error) {
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
	query := `SELECT id, name, task_type, queue, status, priority, attempts, max_attempts, payload_json, result, last_error, created_by, created_at, available_at, started_at, finished_at, updated_at FROM background_tasks ORDER BY created_at DESC`
	args := []any{}
	if limit > 0 {
		query += ` LIMIT ?`
		args = append(args, limit)
	}
	return queryRows(db, query, scanBackgroundTaskRecord, args...)
}

func (s *installStore) BackgroundTaskExistsSince(taskType string, since time.Time) (bool, error) {
	s.mu.Lock()
	defer s.mu.Unlock()
	if s.path == "" || strings.TrimSpace(taskType) == "" {
		return false, nil
	}
	db, err := s.openSQLiteLocked()
	if err != nil {
		return false, err
	}
	defer db.Close()
	var count int
	err = db.QueryRow(`SELECT COUNT(*) FROM background_tasks
		WHERE task_type = ?
			AND (status IN ('pending', 'retry', 'running') OR created_at >= ?)`,
		strings.TrimSpace(taskType), formatStoreTime(since)).Scan(&count)
	return count > 0, err
}

func (s *installStore) AppendBackgroundTaskLog(record adminBackgroundTaskLogRecord) error {
	s.mu.Lock()
	defer s.mu.Unlock()
	if s.path == "" || strings.TrimSpace(record.TaskID) == "" {
		return nil
	}
	db, err := s.openSQLiteLocked()
	if err != nil {
		return err
	}
	defer db.Close()
	_, err = db.Exec(`INSERT INTO background_task_logs(task_id, timestamp, level, event, message, status, attempt) VALUES(?, ?, ?, ?, ?, ?, ?)`,
		record.TaskID, formatStoreTime(record.Timestamp), record.Level, record.Event, record.Message, record.Status, record.Attempt)
	return err
}

func (s *installStore) ListBackgroundTaskLogs(limit int) ([]adminBackgroundTaskLogRecord, error) {
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
	query := `SELECT id, task_id, timestamp, level, event, message, status, attempt FROM background_task_logs ORDER BY id DESC`
	args := []any{}
	if limit > 0 {
		query += ` LIMIT ?`
		args = append(args, limit)
	}
	return queryRows(db, query, scanBackgroundTaskLogRecord, args...)
}

func scanBackgroundTaskLogRecord(rows *sql.Rows) (adminBackgroundTaskLogRecord, error) {
	var record adminBackgroundTaskLogRecord
	var timestamp string
	err := rows.Scan(&record.ID, &record.TaskID, &timestamp, &record.Level, &record.Event, &record.Message, &record.Status, &record.Attempt)
	record.Timestamp = parseStoreTime(timestamp)
	return record, err
}

func scanBackgroundTaskRecord(rows *sql.Rows) (adminBackgroundTaskRecord, error) {
	var record adminBackgroundTaskRecord
	var createdAt, availableAt, startedAt, finishedAt, updatedAt string
	err := rows.Scan(&record.ID, &record.Name, &record.Type, &record.Queue, &record.Status, &record.Priority, &record.Attempts, &record.MaxAttempts,
		&record.PayloadJSON, &record.Result, &record.LastError, &record.CreatedBy, &createdAt, &availableAt, &startedAt, &finishedAt, &updatedAt)
	record.CreatedAt = parseStoreTime(createdAt)
	record.AvailableAt = parseStoreTime(availableAt)
	record.StartedAt = parseStoreTime(startedAt)
	record.FinishedAt = parseStoreTime(finishedAt)
	record.UpdatedAt = parseStoreTime(updatedAt)
	return record, err
}

func (s *installStore) CountFailedLoginAttempts(username string, ip string, since time.Time) (int, error) {
	s.mu.Lock()
	defer s.mu.Unlock()
	if s.path == "" {
		return 0, nil
	}
	db, err := s.openSQLiteLocked()
	if err != nil {
		return 0, err
	}
	defer db.Close()
	username = strings.ToLower(strings.TrimSpace(username))
	ip = strings.TrimSpace(ip)
	var count int
	err = db.QueryRow(`SELECT COUNT(*) FROM audit_events
		WHERE category = 'login'
			AND action = '登录失败'
			AND timestamp >= ?
			AND (LOWER(actor) = ? OR ip = ?)`,
		formatStoreTime(since), username, ip).Scan(&count)
	return count, err
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

func (s *installStore) UpsertAgentWeChatMessage(record agentWeChatMessageRecord) error {
	s.mu.Lock()
	defer s.mu.Unlock()
	if s.path == "" {
		return nil
	}
	record = record.normalized()
	if record.ArchiveKey == "" {
		return nil
	}
	db, err := s.openSQLiteLocked()
	if err != nil {
		return err
	}
	defer db.Close()

	filesJSON, err := marshalStoreJSON(record.Files)
	if err != nil {
		return err
	}
	modelUsed := 0
	if record.ModelUsed {
		modelUsed = 1
	}
	ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
	defer cancel()
	_, err = db.ExecContext(ctx, `INSERT INTO agent_wechat_messages(
		archive_key, channel_key, channel_name, provider, message_id, session_id, run_id,
		from_user_id, to_user_id, inbound_text, reply_text, files_json, status, error,
		model_used, tool_count, file_count, duration_ms, received_at, replied_at, created_at
	) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
	ON CONFLICT(archive_key) DO UPDATE SET
		channel_key=excluded.channel_key,
		channel_name=excluded.channel_name,
		provider=excluded.provider,
		message_id=excluded.message_id,
		session_id=excluded.session_id,
		run_id=excluded.run_id,
		from_user_id=excluded.from_user_id,
		to_user_id=excluded.to_user_id,
		inbound_text=excluded.inbound_text,
		reply_text=excluded.reply_text,
		files_json=excluded.files_json,
		status=excluded.status,
		error=excluded.error,
		model_used=excluded.model_used,
		tool_count=excluded.tool_count,
		file_count=excluded.file_count,
		duration_ms=excluded.duration_ms,
		received_at=excluded.received_at,
		replied_at=excluded.replied_at`,
		record.ArchiveKey, record.ChannelKey, record.ChannelName, record.Provider, record.MessageID, record.SessionID, record.RunID,
		record.FromUserID, record.ToUserID, truncateAuditText(record.InboundText, 2000), truncateAuditText(record.ReplyText, 4000), filesJSON, record.Status, truncateAuditText(record.Error, 1000),
		modelUsed, record.ToolCount, record.FileCount, record.DurationMS, formatStoreTime(record.ReceivedAt), formatStoreTime(record.RepliedAt), formatStoreTime(record.CreatedAt))
	return err
}

func (s *installStore) ListAgentWeChatMessages(limit int) ([]agentWeChatMessageRecord, error) {
	return s.ListAgentWeChatMessagesPage("", limit, 0)
}

func (s *installStore) ListAgentWeChatMessagesPage(channelKey string, limit int, offset int) ([]agentWeChatMessageRecord, error) {
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
	query := `SELECT archive_key, channel_key, channel_name, provider, message_id, session_id, run_id, from_user_id, to_user_id, inbound_text, reply_text, files_json, status, error, model_used, tool_count, file_count, duration_ms, received_at, replied_at, created_at
		FROM agent_wechat_messages`
	args := []any{}
	if normalizedKey := normalizeAgentWeChatChannelKey(channelKey); normalizedKey != "" {
		query += ` WHERE channel_key = ?`
		args = append(args, normalizedKey)
	}
	query += ` ORDER BY received_at DESC, archive_key DESC`
	if limit > 0 {
		query += ` LIMIT ? OFFSET ?`
		if offset < 0 {
			offset = 0
		}
		args = append(args, limit, offset)
	}
	return queryRows(db, query, func(rows *sql.Rows) (agentWeChatMessageRecord, error) {
		var record agentWeChatMessageRecord
		var filesJSON string
		var modelUsed int
		var receivedAt, repliedAt, createdAt string
		err := rows.Scan(&record.ArchiveKey, &record.ChannelKey, &record.ChannelName, &record.Provider, &record.MessageID, &record.SessionID, &record.RunID, &record.FromUserID, &record.ToUserID, &record.InboundText, &record.ReplyText, &filesJSON, &record.Status, &record.Error, &modelUsed, &record.ToolCount, &record.FileCount, &record.DurationMS, &receivedAt, &repliedAt, &createdAt)
		if strings.TrimSpace(filesJSON) != "" {
			_ = json.Unmarshal([]byte(filesJSON), &record.Files)
		}
		record.ModelUsed = modelUsed == 1
		record.ReceivedAt = parseStoreTime(receivedAt)
		record.RepliedAt = parseStoreTime(repliedAt)
		record.CreatedAt = parseStoreTime(createdAt)
		return record, err
	}, args...)
}

func (s *installStore) CountAgentWeChatMessages(channelKey string) (int, error) {
	s.mu.Lock()
	defer s.mu.Unlock()
	if s.path == "" {
		return 0, nil
	}
	db, err := s.openSQLiteLocked()
	if err != nil {
		return 0, err
	}
	defer db.Close()
	query := `SELECT COUNT(*) FROM agent_wechat_messages`
	args := []any{}
	if normalizedKey := normalizeAgentWeChatChannelKey(channelKey); normalizedKey != "" {
		query += ` WHERE channel_key = ?`
		args = append(args, normalizedKey)
	}
	ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
	defer cancel()
	var count int
	if err := db.QueryRowContext(ctx, query, args...).Scan(&count); err != nil {
		return 0, err
	}
	return count, nil
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
