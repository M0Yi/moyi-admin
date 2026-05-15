package api

import (
	"log/slog"
	"net/http"
	"os"
	"time"
)

type RouterOptions struct {
	Logger           *slog.Logger
	AdminEntry       string
	AdminUsername    string
	AdminPassword    string
	SessionSecret    string
	InstallStateFile string
}

func NewRouter(options RouterOptions) http.Handler {
	logger := options.Logger
	if logger == nil {
		logger = slog.Default()
	}
	adminEntry := options.AdminEntry
	if adminEntry == "" {
		adminEntry = "/moyi-7k3x9-admin"
	}
	admin := newAdminServer(adminEntry, options.AdminUsername, options.AdminPassword, options.SessionSecret, options.InstallStateFile)

	mux := http.NewServeMux()
	mux.HandleFunc("GET /", admin.get)
	mux.HandleFunc("POST /", admin.post)
	mux.HandleFunc("POST /api/install/check-database", admin.checkDatabase)
	mux.HandleFunc("POST /api/install/check-ai", admin.checkAI)
	mux.HandleFunc("GET /healthz", healthHandler)
	mux.HandleFunc("GET /api/health", healthHandler)
	mux.HandleFunc("GET /api/version", versionHandler)
	mux.Handle("GET /assets/", http.StripPrefix("/assets/", http.FileServer(staticAssetsFS())))

	return withRequestLog(logger, mux)
}

func withRequestLog(logger *slog.Logger, next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		startedAt := time.Now()
		recorder := &statusRecorder{ResponseWriter: w, statusCode: http.StatusOK}

		next.ServeHTTP(recorder, r)

		logger.Info(
			"http request",
			"method", r.Method,
			"path", r.URL.Path,
			"status", recorder.statusCode,
			"duration_ms", time.Since(startedAt).Milliseconds(),
		)
	})
}

type statusRecorder struct {
	http.ResponseWriter
	statusCode int
}

func (r *statusRecorder) WriteHeader(statusCode int) {
	r.statusCode = statusCode
	r.ResponseWriter.WriteHeader(statusCode)
}

func staticAssetsFS() http.FileSystem {
	for _, dir := range []string{"web/static", "../web/static", "../../web/static"} {
		if stat, err := os.Stat(dir); err == nil && stat.IsDir() {
			return http.Dir(dir)
		}
	}
	return http.Dir("web/static")
}
