package config

import (
	"log/slog"
	"os"
	"path/filepath"
	"strings"
)

type Config struct {
	Env              string
	HTTPAddr         string
	AdminEntry       string
	AdminUsername    string
	AdminPassword    string
	SessionSecret    string
	InstallStateFile string
	LogLevel         slog.Level
}

func Load() Config {
	dataDir := getenv("MOYI_DATA_DIR", "data")

	return Config{
		Env:              getenv("MOYI_ENV", "development"),
		HTTPAddr:         getenv("MOYI_HTTP_ADDR", ":9752"),
		AdminEntry:       normalizePath(getenv("MOYI_ADMIN_ENTRY", "moyi-7k3x9-admin")),
		AdminUsername:    getenv("MOYI_ADMIN_USERNAME", "admin"),
		AdminPassword:    getenv("MOYI_ADMIN_PASSWORD", "admin"),
		SessionSecret:    getenv("MOYI_SESSION_SECRET", "moyi-admin-dev-session-secret"),
		InstallStateFile: getenv("MOYI_INSTALL_STATE_FILE", filepath.Join(dataDir, "install_state.json")),
		LogLevel:         parseLogLevel(getenv("MOYI_LOG_LEVEL", "info")),
	}
}

func getenv(key string, fallback string) string {
	value := strings.TrimSpace(os.Getenv(key))
	if value == "" {
		return fallback
	}
	return value
}

func parseLogLevel(value string) slog.Level {
	switch strings.ToLower(strings.TrimSpace(value)) {
	case "debug":
		return slog.LevelDebug
	case "warn", "warning":
		return slog.LevelWarn
	case "error":
		return slog.LevelError
	default:
		return slog.LevelInfo
	}
}

func normalizePath(value string) string {
	value = strings.TrimSpace(value)
	value = strings.Trim(value, "/")
	if value == "" {
		return "/moyi-7k3x9-admin"
	}
	return "/" + value
}
