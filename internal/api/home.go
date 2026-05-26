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
	Site              publicSite
}

type publicPostPageData struct {
	publicHomeData
	Post         publicSitePost
	RelatedPosts []publicSitePost
}

type publicTagPageData struct {
	publicHomeData
	Tag   publicSiteTag
	Posts []publicSitePost
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
	data.Site = loadPublicSite()
	renderPublicTemplate(w, "home", data)
}

func renderPublicPost(w http.ResponseWriter, data publicPostPageData) {
	data.Site = loadPublicSite()
	renderPublicTemplate(w, "post", data)
}

func renderPublicTag(w http.ResponseWriter, data publicTagPageData) {
	data.Site = loadPublicSite()
	renderPublicTemplate(w, "tag", data)
}

func renderPublicTemplate(w http.ResponseWriter, name string, data any) {
	w.Header().Set("Content-Type", "text/html; charset=utf-8")
	w.WriteHeader(http.StatusOK)
	_ = publicSiteTemplate.ExecuteTemplate(w, name, data)
}

var publicSiteTemplate = template.Must(template.New("public-site").Parse(`{{define "head"}}
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{.Site.Title}} - {{.Site.Description}}</title>
  <meta name="description" content="{{.Site.Description}}">
  <link rel="stylesheet" href="/assets/css/site.css">
  <link rel="stylesheet" href="/content/public/cards.min.css">
  <style>@media (prefers-color-scheme: dark){:root{color-scheme:dark;}}</style>
</head>
{{end}}

{{define "header"}}
<header class="site-header">
  <a class="brand" href="/">
    <span class="brand-mark">MY</span>
    <span>
      <strong>{{.Site.Title}}</strong>
      <small>{{.Site.Description}}</small>
    </span>
  </a>
  <nav class="site-nav" aria-label="主导航">
    <a href="/#work">案例</a>
    <a href="/#brands">品牌</a>
    <a href="/about/">关于</a>
  </nav>
</header>
{{end}}

{{define "footer"}}
<footer class="site-footer">
  <div>
    <strong>{{.Site.Title}}</strong>
    <p>{{.Site.Description}} · 品牌互动、视觉传播与数字体验作品展示。</p>
  </div>
  <div class="footer-links">
    <a class="icp-link" href="https://beian.miit.gov.cn/" target="_blank" rel="noopener">湘ICP备2021005520号-1</a>
    {{if .AdminLoginPath}}
    <a class="footer-admin" href="{{.AdminLoginPath}}">管理入口</a>
    {{end}}
  </div>
</footer>
{{end}}

{{define "home"}}
{{template "head" .}}
<body class="public-site">
  {{template "header" .}}

  <main>
    <section class="hero" aria-label="官网首页">
      {{if .Site.Hero.FeatureImage}}<img class="hero-image" src="{{.Site.Hero.FeatureImage}}" alt="{{.Site.Hero.Title}}">{{end}}
      <div class="hero-shade"></div>
      <div class="hero-copy">
        <p class="eyebrow">H5 Interactive / Brand Campaign / Visual Design</p>
        <h1>{{.Site.Title}}</h1>
        <p class="hero-lead">{{.Site.Description}}，把品牌活动、互动 H5 与视觉内容做成可以被看见、被参与、被传播的数字体验。</p>
        <div class="hero-actions">
          <a class="button-primary" href="#work">查看案例</a>
          <a class="button-secondary" href="#contact">联系合作</a>
        </div>
      </div>
      <a class="hero-feature" href="{{.Site.Hero.URL}}">
        <span>Featured Case</span>
        <strong>{{.Site.Hero.Title}}</strong>
        <small>{{.Site.Hero.DateText}}</small>
      </a>
    </section>

    <section class="stats-band" aria-label="作品数据摘要">
      <article>
        <strong>{{.Site.PostCount}}</strong>
        <span>个品牌案例</span>
      </article>
      <article>
        <strong>{{.Site.TagCount}}</strong>
        <span>个项目标签</span>
      </article>
      <article>
        <strong>{{.Site.YearSpan}}</strong>
        <span>作品时间线</span>
      </article>
      <article>
        <strong>{{.Site.ImageCount}}</strong>
        <span>份图片素材</span>
      </article>
    </section>

    <section class="intro-section">
      <div class="section-heading">
        <p class="eyebrow">Creative Service</p>
        <h2>让品牌活动拥有更清晰的互动节奏和视觉记忆点。</h2>
      </div>
      <p>我们把创意策划、H5 互动、品牌视觉、活动传播串成一条完整路径。每个案例都围绕真实品牌目标展开，让用户能快速理解活动、参与互动，并留下可传播的内容印象。</p>
    </section>

    <section class="work-section" id="work" aria-label="案例作品">
      <div class="section-heading split">
        <div>
          <p class="eyebrow">Selected Work</p>
          <h2>品牌互动案例</h2>
        </div>
        <div class="filterbar" aria-label="案例筛选">
          <button class="filter-button is-active" type="button" data-filter="all">全部</button>
          {{range .Site.TopTags}}<button class="filter-button" type="button" data-filter="{{.Slug}}">{{.Name}}</button>{{end}}
        </div>
      </div>

      <div class="case-grid">
        {{range .Site.Posts}}
        <article class="case-card" data-tags="{{range .Tags}}{{.Slug}} {{end}}">
          <a class="case-media" href="{{.URL}}" aria-label="{{.Title}}">
            {{if .FeatureImage}}<img src="{{.FeatureImage}}" alt="{{.Title}}" loading="lazy">{{end}}
          </a>
          <div class="case-body">
            <div class="case-meta">
              <span>{{.DateText}}</span>
              {{range .Tags}}<a href="{{.URL}}">{{.Name}}</a>{{end}}
            </div>
            <h3><a href="{{.URL}}">{{.Title}}</a></h3>
            <p>{{.Excerpt}}</p>
          </div>
        </article>
        {{end}}
      </div>
    </section>

    <section class="brand-section" id="brands" aria-label="品牌标签">
      <div class="section-heading">
        <p class="eyebrow">Brands & Topics</p>
        <h2>服务过的品牌与项目场景。</h2>
      </div>
      <div class="tag-cloud">
        {{range .Site.Tags}}<a href="{{.URL}}">{{.Name}}<span>{{.Count}}</span></a>{{end}}
      </div>
    </section>

    <section class="contact-section" id="contact" aria-label="联系合作">
      <div>
        <p class="eyebrow">Contact</p>
        <h2>让一个活动，从想法变成可传播的互动体验。</h2>
      </div>
      <div class="contact-card">
        <strong>{{.Site.AuthorName}}</strong>
        <p>{{.Site.AuthorBio}}</p>
        <a class="button-primary" href="mailto:{{.Site.AuthorEmail}}">{{.Site.AuthorEmail}}</a>
      </div>
    </section>
  </main>

  {{template "footer" .}}
  <script src="/assets/js/site.js" defer></script>
</body>
</html>
{{end}}

{{define "post"}}
{{template "head" .}}
<body class="public-site article-page">
  {{template "header" .}}
  <main>
    <article class="article-shell">
      <header class="article-hero">
        <a class="back-link" href="/#work">返回案例</a>
        <div class="article-title">
          <p class="eyebrow">{{.Post.DateText}}</p>
          <h1>{{.Post.Title}}</h1>
          <div class="case-meta">
            {{range .Post.Tags}}<a href="{{.URL}}">{{.Name}}</a>{{end}}
          </div>
        </div>
        {{if .Post.FeatureImage}}<img src="{{.Post.FeatureImage}}" alt="{{.Post.Title}}">{{end}}
      </header>
      <div class="ghost-content">
        {{.Post.HTML}}
      </div>
    </article>

    {{if .RelatedPosts}}
    <section class="related-section">
      <div class="section-heading">
        <p class="eyebrow">More Work</p>
        <h2>继续看相关案例</h2>
      </div>
      <div class="case-grid compact">
        {{range .RelatedPosts}}
        <article class="case-card">
          <a class="case-media" href="{{.URL}}">
            {{if .FeatureImage}}<img src="{{.FeatureImage}}" alt="{{.Title}}" loading="lazy">{{end}}
          </a>
          <div class="case-body">
            <div class="case-meta"><span>{{.DateText}}</span></div>
            <h3><a href="{{.URL}}">{{.Title}}</a></h3>
          </div>
        </article>
        {{end}}
      </div>
    </section>
    {{end}}
  </main>
  {{template "footer" .}}
</body>
</html>
{{end}}

{{define "tag"}}
{{template "head" .}}
<body class="public-site tag-page">
  {{template "header" .}}
  <main>
    <section class="listing-hero">
      <p class="eyebrow">Tag</p>
      <h1>{{.Tag.Name}}</h1>
      {{if .Tag.Description}}<p>{{.Tag.Description}}</p>{{end}}
    </section>
    <section class="work-section">
      <div class="case-grid">
        {{range .Posts}}
        <article class="case-card">
          <a class="case-media" href="{{.URL}}">
            {{if .FeatureImage}}<img src="{{.FeatureImage}}" alt="{{.Title}}" loading="lazy">{{end}}
          </a>
          <div class="case-body">
            <div class="case-meta"><span>{{.DateText}}</span></div>
            <h3><a href="{{.URL}}">{{.Title}}</a></h3>
            <p>{{.Excerpt}}</p>
          </div>
        </article>
        {{end}}
      </div>
    </section>
  </main>
  {{template "footer" .}}
</body>
</html>
{{end}}`))
