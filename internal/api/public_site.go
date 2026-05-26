package api

import (
	"encoding/json"
	stdhtml "html"
	"html/template"
	"io/fs"
	"net/http"
	"os"
	"path/filepath"
	"regexp"
	"sort"
	"strings"
	"sync"
	"time"
)

type publicSite struct {
	Title       string
	Description string
	ExportedAt  string
	Version     string
	YearSpan    string
	PostCount   int
	TagCount    int
	ImageCount  int
	AuthorName  string
	AuthorEmail string
	AuthorBio   string
	Hero        publicSitePost
	Posts       []publicSitePost
	Pages       []publicSitePost
	Tags        []publicSiteTag
	TopTags     []publicSiteTag
}

type publicSitePost struct {
	ID           string
	Title        string
	Slug         string
	Type         string
	URL          string
	FeatureImage string
	Excerpt      string
	DateText     string
	PublishedAt  time.Time
	HTML         template.HTML
	Tags         []publicSiteTag
}

type publicSiteTag struct {
	ID           string
	Name         string
	Slug         string
	URL          string
	Description  string
	FeatureImage string
	Count        int
	Visibility   string
}

type ghostExport struct {
	Meta ghostMeta `json:"meta"`
	Data ghostData `json:"data"`
}

type ghostMeta struct {
	ExportedOn int64  `json:"exported_on"`
	Version    string `json:"version"`
}

type ghostData struct {
	Posts     []ghostPost    `json:"posts"`
	Tags      []ghostTag     `json:"tags"`
	PostsTags []ghostPostTag `json:"posts_tags"`
	Settings  []ghostSetting `json:"settings"`
	Users     []ghostUser    `json:"users"`
}

type ghostPost struct {
	ID           string `json:"id"`
	Title        string `json:"title"`
	Slug         string `json:"slug"`
	Type         string `json:"type"`
	Status       string `json:"status"`
	FeatureImage string `json:"feature_image"`
	HTML         string `json:"html"`
	Plaintext    string `json:"plaintext"`
	PublishedAt  string `json:"published_at"`
}

type ghostTag struct {
	ID           string `json:"id"`
	Name         string `json:"name"`
	Slug         string `json:"slug"`
	Description  string `json:"description"`
	FeatureImage string `json:"feature_image"`
	Visibility   string `json:"visibility"`
}

type ghostPostTag struct {
	PostID    string `json:"post_id"`
	TagID     string `json:"tag_id"`
	SortOrder int    `json:"sort_order"`
}

type ghostSetting struct {
	Key   string `json:"key"`
	Value string `json:"value"`
}

type ghostUser struct {
	Name  string `json:"name"`
	Email string `json:"email"`
	Bio   string `json:"bio"`
}

var publicSiteCache struct {
	once sync.Once
	site publicSite
}

var (
	ghostTagRE    = regexp.MustCompile(`<[^>]+>`)
	ghostSpaceRE  = regexp.MustCompile(`\s+`)
	ghostSrcsetRE = regexp.MustCompile(`\s+srcset="[^"]*"`)
	ghostSizesRE  = regexp.MustCompile(`\s+sizes="[^"]*"`)
)

func loadPublicSite() publicSite {
	publicSiteCache.once.Do(func() {
		site, err := readPublicSite()
		if err != nil {
			site = fallbackPublicSite()
		}
		publicSiteCache.site = site
	})
	return publicSiteCache.site
}

func readPublicSite() (publicSite, error) {
	contentRoot := ghostContentRoot()
	exportPath := filepath.Join(contentRoot, "data", "my-designe.ghost.2024-06-22-11-07-48.json")
	body, err := os.ReadFile(exportPath)
	if err != nil {
		return publicSite{}, err
	}

	var exported ghostExport
	if err := json.Unmarshal(body, &exported); err != nil {
		return publicSite{}, err
	}

	settings := map[string]string{}
	for _, setting := range exported.Data.Settings {
		settings[setting.Key] = setting.Value
	}

	site := publicSite{
		Title:       firstNonEmptyPublic(settings["title"], "MY.DESIGNE"),
		Description: firstNonEmptyPublic(settings["description"], "默毅传媒"),
		Version:     exported.Meta.Version,
		AuthorName:  "默毅",
		AuthorEmail: "moyi@mymoyi.cn",
		AuthorBio:   "微信号: MoYiWeChat",
	}
	if exported.Meta.ExportedOn > 0 {
		site.ExportedAt = time.UnixMilli(exported.Meta.ExportedOn).UTC().Format("2006-01-02")
	}

	for _, user := range exported.Data.Users {
		if strings.TrimSpace(user.Name) == "" {
			continue
		}
		site.AuthorName = strings.TrimSpace(user.Name)
		if strings.TrimSpace(user.Email) != "" {
			site.AuthorEmail = strings.TrimSpace(user.Email)
		}
		if strings.TrimSpace(user.Bio) != "" {
			site.AuthorBio = strings.TrimSpace(user.Bio)
		}
		break
	}

	tagByID := make(map[string]publicSiteTag, len(exported.Data.Tags))
	for _, tag := range exported.Data.Tags {
		name := strings.TrimSpace(tag.Name)
		slug := strings.Trim(strings.TrimSpace(tag.Slug), "/")
		if name == "" || slug == "" {
			continue
		}
		publicTag := publicSiteTag{
			ID:           tag.ID,
			Name:         name,
			Slug:         slug,
			URL:          "/tag/" + slug + "/",
			Description:  strings.TrimSpace(tag.Description),
			FeatureImage: rewriteGhostAssetURL(tag.FeatureImage),
			Visibility:   strings.TrimSpace(tag.Visibility),
		}
		tagByID[tag.ID] = publicTag
	}

	postTags := make(map[string][]ghostPostTag)
	for _, relation := range exported.Data.PostsTags {
		postTags[relation.PostID] = append(postTags[relation.PostID], relation)
	}
	for postID := range postTags {
		sort.SliceStable(postTags[postID], func(i, j int) bool {
			return postTags[postID][i].SortOrder < postTags[postID][j].SortOrder
		})
	}

	tagCounts := map[string]int{}
	var minYear int
	var maxYear int
	for _, post := range exported.Data.Posts {
		if strings.TrimSpace(post.Status) != "published" {
			continue
		}

		publicPost := publicSitePost{
			ID:           post.ID,
			Title:        strings.TrimSpace(post.Title),
			Slug:         strings.Trim(strings.TrimSpace(post.Slug), "/"),
			Type:         strings.TrimSpace(post.Type),
			FeatureImage: rewriteGhostAssetURL(post.FeatureImage),
			HTML:         rewriteGhostHTML(post.HTML),
		}
		if publicPost.Title == "" || publicPost.Slug == "" {
			continue
		}
		publicPost.URL = "/" + publicPost.Slug + "/"
		publicPost.PublishedAt = parseGhostTime(post.PublishedAt)
		publicPost.DateText = formatPublicDate(publicPost.PublishedAt)
		publicPost.Tags = publicTagsForPost(postTags[post.ID], tagByID)
		publicPost.Excerpt = buildPublicExcerpt(post.Plaintext, post.HTML, publicPost.Tags)

		if publicPost.PublishedAt.Year() > 1 {
			year := publicPost.PublishedAt.Year()
			if minYear == 0 || year < minYear {
				minYear = year
			}
			if year > maxYear {
				maxYear = year
			}
		}

		switch publicPost.Type {
		case "page":
			site.Pages = append(site.Pages, publicPost)
		default:
			site.Posts = append(site.Posts, publicPost)
			for _, tag := range publicPost.Tags {
				if tag.Visibility == "internal" {
					continue
				}
				tagCounts[tag.ID]++
			}
		}
	}

	sort.SliceStable(site.Posts, func(i, j int) bool {
		return site.Posts[i].PublishedAt.After(site.Posts[j].PublishedAt)
	})
	sort.SliceStable(site.Pages, func(i, j int) bool {
		return site.Pages[i].Title < site.Pages[j].Title
	})

	if len(site.Posts) > 0 {
		site.Hero = site.Posts[0]
	}
	site.PostCount = len(site.Posts)
	site.ImageCount = countGhostImages(contentRoot)
	if minYear > 0 && maxYear > 0 && minYear != maxYear {
		site.YearSpan = formatYearRange(minYear, maxYear)
	} else if maxYear > 0 {
		site.YearSpan = formatYearRange(maxYear, maxYear)
	}
	if site.YearSpan == "" {
		site.YearSpan = "2019-2024"
	}

	for _, tag := range tagByID {
		if tag.Visibility == "internal" {
			continue
		}
		tag.Count = tagCounts[tag.ID]
		if tag.Count == 0 {
			continue
		}
		site.Tags = append(site.Tags, tag)
	}
	sort.SliceStable(site.Tags, func(i, j int) bool {
		if site.Tags[i].Count == site.Tags[j].Count {
			return site.Tags[i].Name < site.Tags[j].Name
		}
		return site.Tags[i].Count > site.Tags[j].Count
	})
	site.TagCount = len(site.Tags)
	site.TopTags = append(site.TopTags, site.Tags...)
	if len(site.TopTags) > 8 {
		site.TopTags = site.TopTags[:8]
	}

	return site, nil
}

func renderPublicSiteRoute(w http.ResponseWriter, r *http.Request, data publicHomeData) bool {
	path := strings.Trim(r.URL.Path, "/")
	if path == "" {
		renderPublicHome(w, data)
		return true
	}

	if strings.HasPrefix(path, "tag/") {
		slug := strings.Trim(strings.TrimPrefix(path, "tag/"), "/")
		tag, posts, ok := publicSiteTagBySlug(slug)
		if !ok {
			return false
		}
		renderPublicTag(w, publicTagPageData{
			publicHomeData: data,
			Tag:            tag,
			Posts:          posts,
		})
		return true
	}

	post, ok := publicSitePostBySlug(path)
	if !ok {
		return false
	}
	renderPublicPost(w, publicPostPageData{
		publicHomeData: data,
		Post:           post,
		RelatedPosts:   relatedPublicPosts(post, 3),
	})
	return true
}

func publicSitePostBySlug(slug string) (publicSitePost, bool) {
	slug = strings.Trim(slug, "/")
	site := loadPublicSite()
	for _, post := range site.Posts {
		if post.Slug == slug {
			return post, true
		}
	}
	for _, page := range site.Pages {
		if page.Slug == slug {
			return page, true
		}
	}
	return publicSitePost{}, false
}

func publicSiteTagBySlug(slug string) (publicSiteTag, []publicSitePost, bool) {
	slug = strings.Trim(slug, "/")
	site := loadPublicSite()
	var found publicSiteTag
	ok := false
	for _, tag := range site.Tags {
		if tag.Slug == slug {
			found = tag
			ok = true
			break
		}
	}
	if !ok {
		return publicSiteTag{}, nil, false
	}
	var posts []publicSitePost
	for _, post := range site.Posts {
		if postHasTag(post, slug) {
			posts = append(posts, post)
		}
	}
	return found, posts, true
}

func relatedPublicPosts(current publicSitePost, limit int) []publicSitePost {
	if limit <= 0 {
		return nil
	}
	site := loadPublicSite()
	tagSlugs := map[string]bool{}
	for _, tag := range current.Tags {
		tagSlugs[tag.Slug] = true
	}
	var related []publicSitePost
	for _, post := range site.Posts {
		if post.Slug == current.Slug {
			continue
		}
		matched := len(tagSlugs) == 0
		for _, tag := range post.Tags {
			if tagSlugs[tag.Slug] {
				matched = true
				break
			}
		}
		if !matched {
			continue
		}
		related = append(related, post)
		if len(related) >= limit {
			return related
		}
	}
	return related
}

func publicTagsForPost(relations []ghostPostTag, tagByID map[string]publicSiteTag) []publicSiteTag {
	var tags []publicSiteTag
	for _, relation := range relations {
		tag, ok := tagByID[relation.TagID]
		if !ok || tag.Name == "" {
			continue
		}
		tags = append(tags, tag)
	}
	if len(tags) > 4 {
		return tags[:4]
	}
	return tags
}

func postHasTag(post publicSitePost, slug string) bool {
	for _, tag := range post.Tags {
		if tag.Slug == slug {
			return true
		}
	}
	return false
}

func buildPublicExcerpt(plaintext string, html string, tags []publicSiteTag) string {
	text := normalizePublicText(plaintext)
	if text == "" {
		text = normalizePublicText(ghostTagRE.ReplaceAllString(html, " "))
	}
	if text == "" {
		var names []string
		for _, tag := range tags {
			if tag.Visibility == "internal" {
				continue
			}
			names = append(names, tag.Name)
			if len(names) >= 3 {
				break
			}
		}
		if len(names) > 0 {
			text = "围绕 " + strings.Join(names, "、") + " 打造的品牌互动与视觉传播案例。"
		} else {
			text = "品牌互动与视觉传播案例，保留自旧 Ghost 内容库。"
		}
	}
	return trimRunes(text, 86)
}

func normalizePublicText(value string) string {
	value = stdhtml.UnescapeString(value)
	value = ghostSpaceRE.ReplaceAllString(strings.TrimSpace(value), " ")
	return value
}

func rewriteGhostHTML(value string) template.HTML {
	value = strings.ReplaceAll(value, "__GHOST_URL__/content/", "/content/")
	value = strings.ReplaceAll(value, "__GHOST_URL__", "")
	value = ghostSrcsetRE.ReplaceAllString(value, "")
	value = ghostSizesRE.ReplaceAllString(value, "")
	return template.HTML(value)
}

func rewriteGhostAssetURL(value string) string {
	value = strings.TrimSpace(value)
	if value == "" {
		return ""
	}
	value = strings.ReplaceAll(value, "__GHOST_URL__/content/", "/content/")
	value = strings.ReplaceAll(value, "__GHOST_URL__", "")
	if strings.HasPrefix(value, "content/") {
		value = "/" + value
	}
	return value
}

func parseGhostTime(value string) time.Time {
	if value == "" {
		return time.Time{}
	}
	if parsed, err := time.Parse(time.RFC3339Nano, value); err == nil {
		return parsed
	}
	return time.Time{}
}

func formatPublicDate(value time.Time) string {
	if value.IsZero() {
		return "未标注日期"
	}
	return value.In(time.FixedZone("CST", 8*60*60)).Format("2006.01.02")
}

func formatYearRange(minYear int, maxYear int) string {
	if minYear <= 0 || maxYear <= 0 {
		return ""
	}
	if minYear == maxYear {
		return strings.TrimSpace(time.Date(minYear, 1, 1, 0, 0, 0, 0, time.UTC).Format("2006"))
	}
	return time.Date(minYear, 1, 1, 0, 0, 0, 0, time.UTC).Format("2006") + "-" + time.Date(maxYear, 1, 1, 0, 0, 0, 0, time.UTC).Format("2006")
}

func trimRunes(value string, limit int) string {
	if limit <= 0 {
		return value
	}
	runes := []rune(value)
	if len(runes) <= limit {
		return value
	}
	return strings.TrimSpace(string(runes[:limit])) + "..."
}

func firstNonEmptyPublic(values ...string) string {
	for _, value := range values {
		if strings.TrimSpace(value) != "" {
			return strings.TrimSpace(value)
		}
	}
	return ""
}

func countGhostImages(contentRoot string) int {
	imagesRoot := filepath.Join(contentRoot, "images")
	count := 0
	_ = filepath.WalkDir(imagesRoot, func(path string, d fs.DirEntry, err error) error {
		if err != nil || d == nil || d.IsDir() {
			return nil
		}
		count++
		return nil
	})
	return count
}

func ghostContentRoot() string {
	for _, dir := range []string{"old_content", "../old_content", "../../old_content", "../../../old_content"} {
		if stat, err := os.Stat(dir); err == nil && stat.IsDir() {
			return dir
		}
	}
	return "old_content"
}

func fallbackPublicSite() publicSite {
	return publicSite{
		Title:       "MY.DESIGNE",
		Description: "默毅传媒",
		YearSpan:    "2019-2024",
		AuthorName:  "默毅",
		AuthorEmail: "moyi@mymoyi.cn",
		AuthorBio:   "微信号: MoYiWeChat",
		PostCount:   0,
		TagCount:    0,
		ImageCount:  0,
		Hero: publicSitePost{
			Title:   "Ghost 内容恢复中",
			URL:     "/",
			Excerpt: "旧内容数据会在 old_content 可用时自动加载。",
		},
	}
}
