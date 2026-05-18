package api

import (
	"html/template"
	"net/http"
	"strings"
)

type publicHomeData struct {
	SiteName          string
	PublicTagline     string
	PublicHeadline    string
	PublicDescription string
	AdminLoginPath    string
	DebugLoginEnabled bool
	DebugUsername     string
	DebugPassword     string
}

func homeHandler() http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path != "/" {
			http.NotFound(w, r)
			return
		}

		renderPublicHome(w, publicHomeDataFromState(installState{
			SiteName: "Moyi Admin",
			System:   defaultSystemConfig(),
		}))
	}
}

func publicHomeDataFromState(state installState) publicHomeData {
	system := state.System.normalized()
	siteName := strings.TrimSpace(state.SiteName)
	if siteName == "" {
		siteName = "Moyi Admin"
	}
	return publicHomeData{
		SiteName:          siteName,
		PublicTagline:     system.PublicTagline,
		PublicHeadline:    system.PublicHeadline,
		PublicDescription: system.PublicDescription,
	}
}

func renderPublicHome(w http.ResponseWriter, data publicHomeData) {
	w.Header().Set("Content-Type", "text/html; charset=utf-8")
	w.WriteHeader(http.StatusOK)
	_ = publicHomeTemplate.Execute(w, data)
}

var publicHomeTemplate = template.Must(template.New("public-home").Parse(`<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{.SiteName}} 项目进展</title>
  <style>
    :root {
      color-scheme: light;
      --page: #f3f6f5;
      --surface: #ffffff;
      --surface-2: #f7f9f8;
      --line: #dce3e2;
      --text: #172225;
      --muted: #647174;
      --accent: #1f6f78;
      --accent-2: #b2762d;
      --ok: #1b7f5a;
      --warn: #a15c00;
      --dark: #111c1f;
      --shadow: 0 8px 24px rgba(24, 39, 43, 0.05);
    }

    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      min-height: 100vh;
      background: var(--page);
      color: var(--text);
      font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      letter-spacing: 0;
    }

    a,
    button {
      font: inherit;
    }

    a {
      color: inherit;
      text-decoration: none;
    }

    .site {
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    .topbar {
      height: 58px;
      padding: 0 28px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      border-bottom: 1px solid var(--line);
      background: rgba(255, 255, 255, 0.94);
      backdrop-filter: blur(8px);
      position: sticky;
      top: 0;
      z-index: 2;
    }

    .brand {
      display: flex;
      align-items: center;
      gap: 10px;
      min-width: 0;
    }

    .logo {
      width: 32px;
      height: 32px;
      border-radius: 7px;
      background: #dca54a;
      color: var(--dark);
      display: grid;
      place-items: center;
      font-weight: 800;
    }

    .brand-text strong {
      display: block;
      font-size: 14px;
    }

    .brand-text span {
      color: var(--muted);
      font-size: 12px;
    }

    .admin-link {
      height: 34px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border: 1px solid var(--dark);
      border-radius: 6px;
      padding: 0 11px;
      color: var(--dark);
      font-size: 13px;
      font-weight: 700;
    }

    .top-actions {
      display: flex;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;
      justify-content: flex-end;
    }

    .admin-link.primary {
      background: var(--dark);
      color: #ffffff;
      border-color: var(--dark);
    }

    .hero {
      padding: 42px 28px 24px;
      background: linear-gradient(180deg, #ffffff 0%, #f3f6f5 100%);
    }

    .hero-inner,
    .content {
      width: min(1040px, 100%);
      margin: 0 auto;
    }

    .eyebrow {
      color: var(--accent);
      font-size: 12px;
      font-weight: 800;
      margin-bottom: 12px;
    }

    h1 {
      margin: 0;
      max-width: 780px;
      font-size: 34px;
      line-height: 1.18;
      letter-spacing: 0;
    }

    .lead {
      margin: 14px 0 0;
      max-width: 700px;
      color: var(--muted);
      font-size: 15px;
      line-height: 1.72;
    }

    .hero-actions {
      margin-top: 20px;
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
    }

    .button {
      height: 36px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border-radius: 6px;
      padding: 0 13px;
      font-size: 13px;
      font-weight: 750;
    }

    .button.primary {
      background: var(--accent);
      color: #ffffff;
    }

    .button.secondary {
      border: 1px solid var(--line);
      background: #ffffff;
      color: var(--text);
    }

    .content {
      padding: 18px 28px 46px;
      display: grid;
      gap: 16px;
    }

    .grid {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 14px;
    }

    .panel {
      background: var(--surface);
      border: 1px solid var(--line);
      border-radius: 8px;
      box-shadow: var(--shadow);
      min-width: 0;
    }

    .panel-head {
      min-height: 48px;
      padding: 12px 14px;
      border-bottom: 1px solid var(--line);
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 14px;
    }

    .panel-head h2 {
      margin: 0;
      font-size: 14px;
    }

    .badge {
      display: inline-flex;
      align-items: center;
      height: 22px;
      border-radius: 999px;
      padding: 0 8px;
      background: var(--surface-2);
      color: var(--muted);
      font-size: 12px;
      white-space: nowrap;
    }

    .stack {
      padding: 14px;
      display: grid;
      gap: 10px;
    }

    .row {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 12px;
      min-height: 34px;
      border-bottom: 1px solid var(--line);
      padding-bottom: 10px;
    }

    .row:last-child {
      border-bottom: 0;
      padding-bottom: 0;
    }

    .label {
      min-width: 0;
    }

    .label strong {
      display: block;
      font-size: 13px;
      overflow-wrap: anywhere;
    }

    .label span {
      color: var(--muted);
      display: block;
      margin-top: 4px;
      font-size: 12px;
      overflow-wrap: anywhere;
    }

    .value {
      color: var(--text);
      font-size: 13px;
      font-weight: 700;
      white-space: nowrap;
    }

    .value.warn {
      color: var(--warn);
    }

    .mono {
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
      overflow-wrap: anywhere;
      white-space: normal;
    }

    .debug-note {
      margin-top: 4px;
      color: var(--muted);
      font-size: 12px;
      line-height: 1.5;
    }

    .timeline {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 14px;
    }

    .step {
      padding: 16px;
      min-height: 126px;
      padding: 14px;
    }

    .step-num {
      color: var(--accent-2);
      font-weight: 800;
      font-size: 13px;
      margin-bottom: 9px;
    }

    .step strong {
      display: block;
      font-size: 14px;
      margin-bottom: 7px;
    }

    .step p {
      margin: 0;
      color: var(--muted);
      line-height: 1.65;
      font-size: 13px;
    }

    @media (prefers-color-scheme: dark) {
      :root {
        color-scheme: dark;
        --page: #101716;
        --surface: #172120;
        --surface-2: #1d2928;
        --line: #2d3d3c;
        --text: #e7eeee;
        --muted: #a7b5b3;
        --accent: #4aa3aa;
        --accent-2: #d39a4a;
        --ok: #63c08f;
        --warn: #dfae5a;
        --dark: #f1f6f5;
        --shadow: 0 14px 36px rgba(0, 0, 0, 0.24);
      }

      body {
        background: var(--page);
      }

      .topbar {
        background: rgba(23, 33, 32, 0.94);
      }

      .logo {
        background: #d39a4a;
        color: #12120f;
      }

      .admin-link,
      .button.secondary {
        border-color: #3a4c49;
        background: #111a19;
        color: var(--text);
      }

      .admin-link.primary {
        background: #e7eeee;
        border-color: #e7eeee;
        color: #101716;
      }

      .hero {
        background: linear-gradient(180deg, #141d1c 0%, #101716 100%);
      }

      .button.primary {
        background: #2c838b;
        color: #ffffff;
      }

      .panel {
        background: var(--surface);
        border-color: var(--line);
      }

      .badge {
        background: var(--surface-2);
        color: var(--muted);
      }
    }

    @media (max-width: 980px) {
      h1 {
        font-size: 30px;
      }

      .grid,
      .timeline {
        grid-template-columns: 1fr 1fr;
      }
    }

    @media (max-width: 640px) {
      .topbar,
      .hero {
        padding-left: 18px;
        padding-right: 18px;
      }

      .content {
        padding-left: 18px;
        padding-right: 18px;
      }

      h1 {
        font-size: 26px;
      }

      .grid,
      .timeline {
        grid-template-columns: 1fr;
      }

      .brand-text span {
        display: none;
      }

      .top-actions {
        gap: 8px;
      }
    }
  </style>
</head>
<body>
  <div class="site">
    <header class="topbar">
      <div class="brand">
        <div class="logo">M</div>
        <div class="brand-text">
          <strong>{{.SiteName}}</strong>
          <span>{{.PublicTagline}}</span>
        </div>
      </div>
      <div class="top-actions">
        {{if .AdminLoginPath}}<a class="admin-link primary" href="{{.AdminLoginPath}}">进入后台</a>{{end}}
        <a class="admin-link" href="/api/version">服务状态</a>
      </div>
    </header>

    <section class="hero">
      <div class="hero-inner">
        <div class="eyebrow">AI-native Admin / Project Progress</div>
        <h1>{{.PublicHeadline}}</h1>
        <p class="lead">{{.PublicDescription}}</p>
        <div class="hero-actions">
          {{if .AdminLoginPath}}<a class="button primary" href="{{.AdminLoginPath}}">进入后台管理</a>{{end}}
          <a class="button secondary" href="/api/version">查看服务状态</a>
          <a class="button secondary" href="/healthz">健康检查</a>
        </div>
      </div>
    </section>

    <main class="content">
      {{if .AdminLoginPath}}
        <section aria-label="调试入口">
          <article class="panel">
            <div class="panel-head">
              <h2>调试后台入口</h2>
              <span class="badge">Debug</span>
            </div>
            <div class="stack">
              <div class="row">
                <div class="label">
                  <strong>后台地址</strong>
                  <span class="mono">{{.AdminLoginPath}}</span>
                </div>
                <a class="admin-link" href="{{.AdminLoginPath}}">打开</a>
              </div>
              {{if .DebugLoginEnabled}}
                <div class="row">
                  <div class="label">
                    <strong>默认账号</strong>
                    <span class="mono">{{.DebugUsername}}</span>
                  </div>
                </div>
                <div class="row">
                  <div class="label">
                    <strong>默认密码</strong>
                    <span class="mono">{{.DebugPassword}}</span>
                    <span class="debug-note">登录页会自动填充账号密码，仅用于本地调试环境。</span>
                  </div>
                </div>
              {{end}}
            </div>
          </article>
        </section>
      {{end}}

      <section class="grid" aria-label="项目内容">
        <article class="panel">
          <div class="panel-head">
            <h2>项目定位</h2>
            <span class="badge">方向确定</span>
          </div>
          <div class="stack">
            <div class="row">
              <div class="label">
                <strong>AI 作为主入口</strong>
                <span>用户通过自然语言提出数据查询、导出和分析任务。</span>
              </div>
            </div>
            <div class="row">
              <div class="label">
                <strong>CRUD 作为底层能力</strong>
                <span>传统增删改查保留为工具能力，不再作为主要体验。</span>
              </div>
            </div>
          </div>
        </article>

        <article class="panel">
          <div class="panel-head">
            <h2>当前进展</h2>
            <span class="badge">Phase 1</span>
          </div>
          <div class="stack">
            <div class="row">
              <div class="label">
                <strong>旧 Hyperf 已归档</strong>
                <span>旧代码保存在 legacy-hyperf，作为迁移比对材料。</span>
              </div>
              <span class="value">完成</span>
            </div>
            <div class="row">
              <div class="label">
                <strong>Go 服务骨架</strong>
                <span>HTTP 服务、配置、日志、健康检查已经可运行。</span>
              </div>
              <span class="value">完成</span>
            </div>
          </div>
        </article>

        <article class="panel">
          <div class="panel-head">
            <h2>后台能力</h2>
            <span class="badge">需登录</span>
          </div>
          <div class="stack">
            <div class="row">
              <div class="label">
                <strong>数据源管理</strong>
                <span>连接业务数据库，探测 Schema，设置权限边界。</span>
              </div>
            </div>
            <div class="row">
              <div class="label">
                <strong>智能体任务</strong>
                <span>执行只读查询、解释结果、生成导出和报告。</span>
              </div>
            </div>
          </div>
        </article>
      </section>

      <section class="timeline" aria-label="实施路线">
        <article class="panel step">
          <div class="step-num">01</div>
          <strong>基础服务</strong>
          <p>Go 服务、热更新、健康检查、公开首页和后台登录入口。</p>
        </article>
        <article class="panel step">
          <div class="step-num">02</div>
          <strong>元数据与认证</strong>
          <p>用户、角色、权限、会话、数据源和审计日志表。</p>
        </article>
        <article class="panel step">
          <div class="step-num">03</div>
          <strong>安全查询</strong>
          <p>Schema 探测、SQL Guard、只读查询、结果解释。</p>
        </article>
        <article class="panel step">
          <div class="step-num">04</div>
          <strong>导出与报告</strong>
          <p>CSV、Excel、异步任务、报告生成和查询模板。</p>
        </article>
      </section>
    </main>
  </div>
</body>
</html>`))
