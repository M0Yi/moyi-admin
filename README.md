# Moyi Admin

Moyi Admin is being redesigned as a Go-based AI-native data operations workspace.

The previous Hyperf/PHP implementation has been archived in `legacy-hyperf/` for comparison and migration reference.

Start here:

- [Go + AI refactor design](docs/go-ai-admin-refactor.md)
- [Legacy Hyperf archive](legacy-hyperf/README.archive.md)

## Development

Run the API server:

```bash
go run ./cmd/server
```

The default API address is `:9752`. Override it with `MOYI_HTTP_ADDR` when needed.

`MOYI_ADMIN_ENTRY` is the pre-initialization install entry. After setup succeeds, the real admin entry is randomly generated and stored in `data/install_state.json`.

First-time setup:

```text
http://127.0.0.1:9752/
```

When the system is not initialized, the homepage displays the setup wizard. The wizard creates the site name, first super admin account, AI provider configuration, and a random admin login entry. Initialization state is stored in `data/install_state.json` by default.
It also records the metadata database configuration. SQLite is the default local option; MySQL and PostgreSQL are available in the form. Use the database and AI check buttons before submitting the setup form.

Run with hot reload:

```bash
air
```

Install Air if it is not available locally:

```bash
go install github.com/air-verse/air@latest
```

Useful endpoints:

- `GET /`
- `GET /moyi-7k3x9-admin/install` before initialization
- `GET /moyi-<random>-admin/login` after initialization
- `POST /api/install/check-database`
- `POST /api/install/check-ai`
- `GET /healthz`
- `GET /api/health`
- `GET /api/version`

Configuration is read from environment variables. See `.env.example` for the initial options.
