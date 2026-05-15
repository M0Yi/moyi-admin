# Legacy Hyperf To Go Migration Execution Plan

## 1. Correction

The Go rewrite must not continue as a series of temporary pages and isolated handlers. The legacy Hyperf project already has a product foundation:

- hidden admin entry
- install wizard
- site metadata
- admin login
- theme variables
- Bootstrap-based UI primitives
- admin shell layout
- sidebar and topbar
- multi-tab admin workspace
- form, table, modal, toast, loading, confirm components
- CRUD configuration
- database connection management
- permission, role, menu, audit, upload, and addon modules

The Go version should migrate these foundations in order. AI-native features should be built on top of this base, not ahead of it.

## 2. Migration Principles

1. Match legacy behavior before changing product direction.
2. Keep the hidden admin entry and install lock model.
3. Build infrastructure before feature pages.
4. Reuse the legacy visual language first, then refine.
5. Keep public pages separate from admin pages.
6. Persist initialization, users, permissions, and system settings in metadata DB.
7. Only after database, auth, and admin shell are stable should AI tools enter the backend.

## 3. Execution Phases

### Phase 0: Baseline Inventory

Status: started.

Outputs:

- map legacy routes, controllers, services, models, views, CSS, and JS
- identify which modules are foundation vs feature
- document behavior that must be preserved

Foundation modules from legacy:

- install
- auth
- site
- user
- role
- permission
- menu
- operation log
- login log
- database connection
- upload file
- admin layout
- sidebar/topbar/tab shell
- data table/form components

### Phase 1: Go Web Foundation

Status: in progress.

Tasks:

- static asset server under `/assets/`
- shared admin theme CSS based on legacy variables
- template renderer instead of large inline HTML strings
- page layout primitives: install shell, login shell, admin shell
- response helpers for HTML, JSON, redirect, errors
- config module with env and defaults
- local development hot reload with Air

Acceptance:

- install/login/admin pages share the same visual foundation
- styles are not duplicated inside every handler
- hidden admin entry still works
- tests cover route behavior

### Phase 2: Metadata Database Foundation

Tasks:

- select metadata database during install
- validate database connection
- run migrations
- store installation state in metadata DB
- keep file-based install state only as a bootstrap fallback

Initial tables:

- system_settings
- admin_sites
- admin_users
- admin_roles
- admin_permissions
- admin_role_users
- admin_permission_roles
- admin_menus
- admin_login_logs
- admin_operation_logs
- admin_database_connections

Acceptance:

- install creates metadata tables
- install creates default site
- install creates super admin
- install creates default roles, permissions, menus
- repeat install is blocked by DB state

### Phase 3: Auth, RBAC, And Logs

Tasks:

- password hashing
- session storage
- login/logout
- remember me
- permission middleware
- hidden entry guard
- login logs
- operation logs

Acceptance:

- admin pages require auth
- menu visibility follows permission
- actions write operation logs

### Phase 4: Admin Shell Migration

Tasks:

- migrate legacy topbar
- migrate sidebar
- migrate menu tree
- migrate iframe/tab shell decision
- migrate dashboard skeleton
- add responsive behavior

Acceptance:

- after login, user sees admin shell, not a placeholder page
- default menu includes dashboard, system, users, roles, permissions, database connections, logs

### Phase 5: Core System Modules

Tasks:

- site settings
- user management
- role management
- permission management
- menu management
- database connection management
- upload file management
- audit and login logs

Acceptance:

- legacy admin foundation is functionally represented in Go
- no AI features are required for this phase

### Phase 6: Data Management Layer

Tasks:

- schema inspector
- table metadata registry
- safe list/detail query
- export job foundation
- query templates
- SQL guard

Acceptance:

- database connections can be inspected
- read-only queries are permission checked
- exports are audited

### Phase 7: AI-Native Layer

Tasks:

- agent orchestrator
- controlled tools
- query planning
- result explanation
- report generation
- Excel/CSV export through agent

Acceptance:

- AI uses controlled tools only
- generated SQL is guarded and audited
- user can ask natural-language data questions

## 4. Immediate Next Tasks

1. Add Go static asset service.
2. Add shared admin foundation stylesheet derived from legacy Hyperf styles.
3. Stop embedding large page styles directly in handlers.
4. Extract templates for install, installed, login, workspace, and public home.
5. Replace current install/login visuals with legacy-inspired card layout.
6. Add metadata DB connection test before accepting install.
7. Add migrations and move install state from JSON file into metadata DB.

## 5. Current Rule

Until Phase 5 is complete, new work should prioritize infrastructure parity with legacy Hyperf. AI features remain planned, but should not displace auth, install, layout, RBAC, metadata storage, and audit foundations.
