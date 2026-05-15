# Moyi Admin Go + AI Refactor Design

## 1. Background

The current Moyi Admin project is based on Hyperf and follows a traditional admin-system model: menus, CRUD configuration, table lists, forms, exports, and page-level permissions.

The next version should not be a Go translation of the old PHP code. It should become an AI-native data management platform. Traditional CRUD remains as an internal capability, but the user-facing workflow should be driven by natural language, task execution, reports, and exports.

## 2. Product Positioning

Moyi Admin should become an AI data operations workspace.

The core user experience is:

```text
Ask -> Plan -> Check -> Execute -> Explain -> Export
```

Example tasks:

- Query recent user growth and export an Excel file.
- Find abnormal order records and explain possible causes.
- Inspect a database schema and describe what each table is used for.
- Generate a weekly business report from several tables.
- Compare data across different database connections.
- Create a reusable query template from a natural language request.

The system should feel less like a page generator and more like a reliable data analyst with tools.

## 3. Design Principles

1. AI is the interface, Go is the execution boundary.
2. The agent never gets unrestricted database access.
3. Every data operation must be permission checked, auditable, and reproducible.
4. CRUD is a low-level capability, not the main product surface.
5. Query plans should be visible before risky execution.
6. Export, report, and chart generation are first-class operations.
7. The system should support multiple databases from the beginning.
8. The old Hyperf project is reference material, not the target architecture.

## 4. Target Architecture

```text
Web UI
  |
  v
Go API Gateway
  |
  +-- Auth / RBAC / Tenant / Site Context
  |
  +-- Agent Orchestrator
  |     |
  |     +-- Intent Parser
  |     +-- Planning Engine
  |     +-- Tool Router
  |     +-- Result Explainer
  |
  +-- Data Access Layer
  |     |
  |     +-- Schema Inspector
  |     +-- SQL Builder / SQL Guard
  |     +-- Query Executor
  |     +-- Data Masking
  |
  +-- Task System
  |     |
  |     +-- Async Jobs
  |     +-- Export Jobs
  |     +-- Report Jobs
  |
  +-- Audit / Logs / Observability
  |
  +-- Storage
        |
        +-- Metadata DB
        +-- Object/File Storage
        +-- Cache
```

## 5. Proposed Go Modules

```text
cmd/server
internal/api
internal/auth
internal/agent
internal/agent/tools
internal/database
internal/database/schema
internal/database/query
internal/exporter
internal/report
internal/task
internal/audit
internal/config
internal/store
pkg/sdk
```

Suggested responsibilities:

- `cmd/server`: application entrypoint.
- `internal/api`: HTTP routing, request validation, response shaping.
- `internal/auth`: login, sessions or JWT, RBAC, tenant/site context.
- `internal/agent`: conversation state, planning, model calls, tool routing.
- `internal/agent/tools`: controlled tool implementations available to the agent.
- `internal/database`: database connection registry and runtime connection handling.
- `internal/database/schema`: schema introspection for MySQL, PostgreSQL, and future engines.
- `internal/database/query`: SQL generation helpers, guards, pagination, limits.
- `internal/exporter`: CSV, Excel, and later PDF export.
- `internal/report`: report templates, chart data, narrative generation.
- `internal/task`: async job queue and task lifecycle.
- `internal/audit`: operation logs, query logs, permission decisions.
- `internal/store`: metadata persistence.
- `pkg/sdk`: optional client/tool SDK for extensions.

## 6. Core Agent Tools

The agent should only interact with the system through explicit tools.

### 6.1 `inspect_schema`

Purpose: read database metadata.

Inputs:

- data source ID
- optional table name
- optional schema name

Outputs:

- tables
- columns
- indexes
- foreign keys where available
- row count estimate where safe

### 6.2 `query_data`

Purpose: execute read-only data queries.

Rules:

- SELECT only in the first phase.
- Required row limit.
- Required timeout.
- Permission filter injection.
- SQL guard before execution.
- Audit log after execution.

Outputs:

- rows
- columns
- SQL used
- execution time
- truncated flag

### 6.3 `export_data`

Purpose: export query results to CSV or Excel.

Rules:

- Must reference an approved query plan or saved query.
- Must record file path, creator, source, filters, and expiry.
- Large exports should run as async jobs.

### 6.4 `generate_report`

Purpose: turn query results into a readable report.

Outputs:

- markdown report
- chart specs
- source queries
- export attachments

### 6.5 `explain_result`

Purpose: explain query results to the user.

Rules:

- Must distinguish facts from inference.
- Must include source query references.
- Must avoid inventing data not present in results.

### 6.6 `save_query_template`

Purpose: save a reusable task.

Examples:

- "Weekly active users by channel"
- "Orders with abnormal refund rate"
- "Content publish volume by category"

## 7. Query Safety Model

The first production milestone should support read-only intelligence.

Minimum guardrails:

- Reject non-SELECT SQL.
- Reject multiple statements.
- Require explicit `LIMIT`.
- Enforce maximum rows and execution time.
- Apply table-level and column-level permissions.
- Mask sensitive fields.
- Log generated SQL, user ID, source IP, tool name, and result size.
- Show query plan before execution when confidence is low.

Future write operations should require a stricter flow:

```text
Natural language request
  -> proposed mutation plan
  -> permission check
  -> dry run
  -> user confirmation
  -> transaction execution
  -> audit log
```

## 8. Metadata Data Model

Initial metadata tables:

```text
users
roles
permissions
user_roles
data_sources
data_source_permissions
schema_snapshots
conversations
messages
agent_runs
agent_tool_calls
query_templates
export_jobs
report_jobs
audit_logs
```

Important fields:

- `data_sources`: type, host, port, database, username, encrypted password, status.
- `schema_snapshots`: data source ID, schema hash, captured schema JSON, created time.
- `agent_runs`: user request, normalized intent, model, status, token usage.
- `agent_tool_calls`: tool name, input JSON, output summary, status, duration.
- `audit_logs`: actor, action, resource, decision, metadata, created time.

## 9. API Sketch

```text
POST   /api/auth/login
POST   /api/auth/logout
GET    /api/me

GET    /api/data-sources
POST   /api/data-sources
GET    /api/data-sources/:id
PUT    /api/data-sources/:id
POST   /api/data-sources/:id/test
POST   /api/data-sources/:id/inspect

POST   /api/agent/chat
GET    /api/agent/conversations
GET    /api/agent/conversations/:id

POST   /api/query/preview
POST   /api/query/execute
POST   /api/query/templates
GET    /api/query/templates

POST   /api/exports
GET    /api/exports
GET    /api/exports/:id/download

POST   /api/reports
GET    /api/reports
GET    /api/reports/:id

GET    /api/audit-logs
```

## 10. Frontend Shape

The first screen should be a workbench, not a traditional admin dashboard.

Primary areas:

- Agent input and conversation.
- Query result panel.
- Generated SQL / query plan panel.
- Export and report actions.
- Data source switcher.
- Recent tasks.
- Saved templates.
- Audit trail access.

Traditional table pages can exist as secondary diagnostic tools, but they should not define the product.

## 11. Migration Plan

### Phase 0: Archive

- Move the old Hyperf project into `legacy-hyperf/`.
- Keep it as reference for features, database tables, routes, and business behavior.
- Do not continue adding new product logic to the Hyperf version.

### Phase 1: Go Foundation

- Create Go module.
- Add config loading.
- Add HTTP server.
- Add structured logging.
- Add health check.
- Add metadata database connection.
- Add migration mechanism.

### Phase 2: Auth And Data Sources

- Implement users, roles, permissions.
- Implement data source CRUD as internal admin capability.
- Implement database connection test.
- Implement schema inspection for MySQL and PostgreSQL.

### Phase 3: Read-Only Agent MVP

- Implement chat endpoint.
- Add agent run persistence.
- Add `inspect_schema` tool.
- Add guarded `query_data` tool.
- Return natural language explanations with source SQL.

### Phase 4: Export And Reports

- Add CSV export.
- Add Excel export.
- Add async export jobs.
- Add markdown report generation.
- Add chart-ready data output.

### Phase 5: Productization

- Add saved query templates.
- Add scheduled reports.
- Add team permissions.
- Add operation audit UI.
- Add plugin/tool SDK.

## 12. Technology Choices

Recommended first stack:

- Language: Go 1.23+
- HTTP: `chi` or `gin`
- SQL: `sqlc` for core metadata, `database/sql` for dynamic data sources
- Migrations: `goose` or `atlas`
- Config: environment variables plus YAML for local development
- Logs: `slog` or `zap`
- Excel: `excelize`
- Queue: start with database-backed jobs, add Redis later if needed
- AI provider: adapter interface, OpenAI-compatible implementation first

The dynamic query engine should avoid being tightly coupled to one ORM. Metadata tables can use generated SQL, while user data sources should use controlled raw SQL with guards.

## 13. Open Questions

1. Should the first version support only read-only queries, or include confirmed write operations?
2. Should Moyi Admin remain multi-site/multi-tenant from day one?
3. Which database should be the metadata store: MySQL or PostgreSQL?
4. Should the frontend be rebuilt now, or after the Go API MVP is stable?
5. Should exported files be stored locally first, or go directly to object storage?
6. Which AI provider should be the default deployment target?

## 14. First Implementation Target

The first concrete MVP should prove this loop:

```text
User asks a data question
  -> system inspects schema
  -> agent proposes a safe query
  -> Go executes guarded SELECT
  -> result is explained
  -> user exports CSV or Excel
```

That proves the new product direction without getting stuck rebuilding every old admin feature.
