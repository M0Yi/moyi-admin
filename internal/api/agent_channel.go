package api

import (
	"bytes"
	"context"
	"crypto/aes"
	"crypto/md5"
	"crypto/rand"
	"crypto/sha256"
	"crypto/subtle"
	"encoding/base64"
	"encoding/binary"
	"encoding/hex"
	"encoding/json"
	"fmt"
	htmltemplate "html/template"
	"io"
	"log/slog"
	"net/http"
	"net/url"
	"os"
	"path/filepath"
	"strconv"
	"strings"
	"time"

	qrcode "github.com/skip2/go-qrcode"
)

const (
	agentWeChatChannelKey      = "wechat_bind"
	agentWeChatProviderID      = "openclaw-weixin"
	agentWeChatDefaultName     = "OpenClaw Weixin 通道"
	agentWeChatDefaultHint     = "openclaw-weixin"
	agentWeChatDefaultBaseURL  = "https://ilinkai.weixin.qq.com"
	agentWeChatDefaultCDNURL   = "https://novac2c.cdn.weixin.qq.com/c2c"
	agentWeChatDefaultBotType  = "3"
	agentWeChatPairTTL         = 5 * time.Minute
	agentWeChatSessionPrefix   = "moyi_wc_pair_"
	agentWeChatTokenPrefix     = "moyi_wc_"
	agentWeChatMaxMessageRunes = 2000
	agentWeChatMaxReplyRunes   = 3500
	agentWeChatSessionExpired  = -14
	agentWeChatTypingStatus    = 1
	agentWeChatTypingCancel    = 2
	agentWeChatTypingKeepalive = 5 * time.Second
)

var (
	agentWeChatHTTPClient     = &http.Client{Timeout: 10 * time.Second}
	agentWeChatLongPollClient = &http.Client{Timeout: 40 * time.Second}
	agentWeChatCDNClient      = &http.Client{Timeout: 60 * time.Second}
	agentWeChatCDNBaseURL     = agentWeChatDefaultCDNURL
)

func defaultAgentChannelConfig() agentChannelConfig {
	return agentChannelConfig{
		WeChat: agentWeChatChannelConfig{
			Status:      "disabled",
			DisplayName: agentWeChatDefaultName,
			AgentHint:   agentWeChatDefaultHint,
			DataScope:   agentTableAccessNone,
		},
	}
}

func agentWeChatDefaultChannelKeyForAdmin(username string) string {
	username = normalizeSettingText(username, 32)
	if username == "" {
		return newAgentWeChatChannelKey()
	}
	return normalizeAgentWeChatChannelKey("wechat_admin_" + username)
}

func agentWeChatDefaultChannelNameForAdmin(user adminAccountConfig) string {
	label := strings.TrimSpace(user.DisplayName)
	if label == "" {
		label = strings.TrimSpace(user.Username)
	}
	if label == "" {
		return agentWeChatDefaultName
	}
	name := normalizeSettingText(label+" 的微信 Agent", 48)
	if name == "" {
		return agentWeChatDefaultName
	}
	return name
}

func ensureDefaultAgentWeChatChannelsForAdmins(state *installState) bool {
	if state == nil {
		return false
	}
	access := state.Access.normalized(*state)
	existing := state.AgentChannels.normalized().WeChats
	knownUsers := make(map[string]adminAccountConfig, len(access.Users))
	for _, user := range access.Users {
		key := strings.ToLower(strings.TrimSpace(user.Username))
		if key == "" {
			continue
		}
		knownUsers[key] = user
	}
	now := time.Now().UTC()
	claimed := make([]bool, len(existing))
	result := make([]agentWeChatChannelConfig, 0, len(access.Users))
	changed := false
	pickBestChannel := func(username string, allowOrphan bool) (agentWeChatChannelConfig, int, bool) {
		username = strings.ToLower(strings.TrimSpace(username))
		if username == "" {
			return agentWeChatChannelConfig{}, -1, false
		}
		defaultKey := agentWeChatDefaultChannelKeyForAdmin(username)
		bestIndex := -1
		bestScore := -1
		for i, channel := range existing {
			if claimed[i] {
				continue
			}
			adminUser := strings.ToLower(strings.TrimSpace(channel.AdminUser))
			matches := adminUser == username
			if !matches && allowOrphan {
				if adminUser == "" || knownUsers[adminUser].Username == "" {
					matches = true
				}
			}
			if !matches {
				continue
			}
			score := agentWeChatChannelPriority(channel, defaultKey)
			if bestIndex == -1 || score > bestScore {
				bestIndex = i
				bestScore = score
			}
		}
		if bestIndex == -1 {
			return agentWeChatChannelConfig{}, -1, false
		}
		return existing[bestIndex], bestIndex, true
	}
	for _, user := range access.Users {
		username := strings.TrimSpace(user.Username)
		if username == "" {
			continue
		}
		allowOrphan := strings.EqualFold(username, state.AdminUser)
		createdDefault := false
		channel, index, ok := pickBestChannel(username, allowOrphan)
		if ok {
			claimed[index] = true
		} else {
			createdDefault = true
			channel = agentWeChatChannelConfig{
				Key:         agentWeChatDefaultChannelKeyForAdmin(username),
				DisplayName: agentWeChatDefaultChannelNameForAdmin(user),
				AgentHint:   agentWeChatDefaultHint,
				BaseURL:     agentWeChatDefaultBaseURL,
				BotType:     agentWeChatDefaultBotType,
				CreatedAt:   now,
			}
			changed = true
		}
		normalizedUser := strings.ToLower(strings.TrimSpace(username))
		if strings.ToLower(strings.TrimSpace(channel.AdminUser)) != normalizedUser {
			channel.AdminUser = username
			changed = true
		}
		if strings.TrimSpace(channel.DisplayName) == "" {
			channel.DisplayName = agentWeChatDefaultChannelNameForAdmin(user)
			changed = true
		}
		if channel.CreatedAt.IsZero() {
			channel.CreatedAt = now
			changed = true
		}
		enabled := !strings.EqualFold(strings.TrimSpace(user.Status), "disabled")
		if createdDefault && channel.Enabled != enabled {
			channel.Enabled = enabled
			changed = true
		}
		if !enabled {
			if channel.Enabled {
				channel.Enabled = false
				changed = true
			}
		}
		if channel.Enabled {
			if strings.TrimSpace(channel.Token) == "" {
				channel.Token = newAgentWeChatToken()
				changed = true
			}
			if channel.Status == "" || channel.Status == "disabled" {
				channel.Status = "waiting"
				changed = true
			}
		} else if channel.Status != "disabled" {
			channel.Status = "disabled"
			changed = true
		}
		channel.UpdatedAt = now
		result = append(result, channel.normalized())
	}
	if len(existing) != len(result) {
		changed = true
	}
	state.AgentChannels = agentChannelConfig{WeChats: result}.normalized()
	return changed
}

func agentWeChatChannelPriority(channel agentWeChatChannelConfig, defaultKey string) int {
	channel = channel.normalized()
	score := 0
	if channel.Key == defaultKey {
		score += 2
	}
	if channel.Status == "bound" || channel.ProviderToken != "" || channel.AccountID != "" {
		score += 100
	}
	if channel.BoundUser != "" || !channel.BoundAt.IsZero() {
		score += 40
	}
	if channel.Status == "scanned" || channel.LoginQRCode != "" || channel.BindCode != "" || channel.QRPayload != "" {
		score += 20
	}
	if channel.Enabled {
		score += 10
	}
	if !channel.LastOutboundAt.IsZero() {
		score += 8
	}
	if !channel.LastMessageAt.IsZero() {
		score += 6
	}
	if !channel.LastHeartbeatAt.IsZero() {
		score += 4
	}
	if !channel.UpdatedAt.IsZero() {
		score += 2
	}
	if channel.Token != "" {
		score++
	}
	return score
}

func (c agentChannelConfig) normalized() agentChannelConfig {
	defaults := defaultAgentChannelConfig()
	var wechats []agentWeChatChannelConfig
	seen := make(map[string]struct{})
	addChannel := func(channel agentWeChatChannelConfig, fallbackKey string) {
		if !agentWeChatChannelHasStoredConfig(channel) {
			return
		}
		if strings.TrimSpace(channel.Key) == "" {
			channel.Key = fallbackKey
		}
		channel = channel.normalized()
		if channel.DisplayName == "" {
			channel.DisplayName = defaults.WeChat.DisplayName
		}
		if channel.AgentHint == "" {
			channel.AgentHint = defaults.WeChat.AgentHint
		}
		if channel.Key == "" {
			channel.Key = newAgentWeChatChannelKey()
		}
		if _, ok := seen[channel.Key]; ok {
			return
		}
		seen[channel.Key] = struct{}{}
		wechats = append(wechats, channel)
	}
	for _, channel := range c.WeChats {
		addChannel(channel, "")
	}
	addChannel(c.WeChat, agentWeChatChannelKey)
	c.WeChats = wechats
	if len(wechats) > 0 {
		c.WeChat = wechats[0]
		return c
	}
	c.WeChat = defaults.WeChat.normalized()
	return c
}

func (c agentWeChatChannelConfig) normalized() agentWeChatChannelConfig {
	c.Key = normalizeAgentWeChatChannelKey(c.Key)
	c.Status = strings.ToLower(strings.TrimSpace(c.Status))
	c.BindCode = normalizeAgentWeChatBindCode(c.BindCode)
	c.BindSession = normalizeAgentWeChatSession(c.BindSession)
	c.BaseURL = normalizeAgentWeChatBaseURL(c.BaseURL)
	c.BotType = normalizeSettingText(c.BotType, 16)
	c.LoginQRCode = normalizeAgentWeChatSession(c.LoginQRCode)
	c.LoginSession = normalizeAgentWeChatSession(c.LoginSession)
	c.QRPayload = strings.TrimSpace(c.QRPayload)
	c.QRImageURL = strings.TrimSpace(c.QRImageURL)
	c.LoginMessage = normalizeSettingText(c.LoginMessage, 160)
	c.ProviderToken = strings.TrimSpace(c.ProviderToken)
	c.AccountID = normalizeSettingText(c.AccountID, 120)
	c.OpenClawUserID = normalizeSettingText(c.OpenClawUserID, 120)
	c.SyncBuffer = strings.TrimSpace(c.SyncBuffer)
	c.Token = strings.TrimSpace(c.Token)
	c.DisplayName = normalizeSettingText(c.DisplayName, 48)
	c.AgentHint = normalizeSettingText(c.AgentHint, 64)
	c.AdminUser = normalizeSettingText(c.AdminUser, 32)
	c.AllowedTables = normalizeAgentAllowedTables(c.AllowedTables)
	c.DataScope = normalizeAgentWeChatDataScope(c.DataScope, c.AllowedTables)
	if c.DataScope != agentTableAccessTables {
		c.AllowedTables = nil
	}
	c.BoundUser = normalizeSettingText(c.BoundUser, 120)
	c.ClientInfo = normalizeSettingText(c.ClientInfo, 180)
	c.LastError = normalizeSettingText(c.LastError, 240)
	if c.BaseURL == "" {
		c.BaseURL = agentWeChatDefaultBaseURL
	}
	if c.BotType == "" {
		c.BotType = agentWeChatDefaultBotType
	}
	if c.DisplayName == "" {
		c.DisplayName = agentWeChatDefaultName
	}
	if c.AgentHint == "" {
		c.AgentHint = agentWeChatDefaultHint
	}
	if !c.Enabled {
		c.Status = "disabled"
		c.BindCode = ""
		c.BindSession = ""
		c.LoginQRCode = ""
		c.LoginSession = ""
		c.QRPayload = ""
		c.QRImageURL = ""
		c.LoginMessage = ""
		c.ProviderToken = ""
		c.AccountID = ""
		c.OpenClawUserID = ""
		c.SyncBuffer = ""
		c.Token = ""
		c.BoundUser = ""
		c.ClientInfo = ""
		c.LastError = ""
		c.BindExpiresAt = time.Time{}
		c.BoundAt = time.Time{}
		c.LastMessageAt = time.Time{}
		c.LastHeartbeatAt = time.Time{}
		c.LastOutboundAt = time.Time{}
		return c
	}
	switch c.Status {
	case "waiting", "scanned", "bound", "expired":
	default:
		if c.ProviderToken != "" || c.AccountID != "" {
			c.Status = "bound"
		} else {
			c.Status = "waiting"
		}
	}
	if (c.ProviderToken != "" || c.AccountID != "") && c.Status != "expired" {
		c.Status = "bound"
		c.BindCode = ""
		c.BindSession = ""
		c.BindExpiresAt = time.Time{}
		c.LoginQRCode = ""
		c.LoginSession = ""
		c.QRPayload = ""
		c.QRImageURL = ""
	} else if c.BindCode != "" && c.BindSession != "" && agentWeChatPairExpiredAt(c, time.Now().UTC()) {
		c.Status = "expired"
	} else if c.LoginQRCode != "" && agentWeChatPairExpiredAt(c, time.Now().UTC()) {
		c.Status = "expired"
	} else if c.BindCode == "" && c.LoginQRCode == "" && c.Status != "expired" {
		c.Status = "waiting"
	}
	return c
}

func (c agentWeChatChannelConfig) statusText() string {
	c = c.normalized()
	if !c.Enabled {
		return "未启用"
	}
	if c.Status == "expired" {
		return "微信会话已失效"
	}
	if c.Status == "bound" || c.ProviderToken != "" || c.AccountID != "" {
		return "已接入"
	}
	if c.Status == "scanned" {
		return "已扫码，等待确认"
	}
	if c.BindCode != "" && c.BindSession != "" {
		if agentWeChatPairExpiredAt(c, time.Now().UTC()) {
			return "接入会话已过期"
		}
		return "等待适配器接入"
	}
	if c.LoginQRCode != "" || c.QRPayload != "" {
		if agentWeChatPairExpiredAt(c, time.Now().UTC()) {
			return "二维码已过期"
		}
		return "等待微信扫码"
	}
	return "等待生成二维码"
}

func (c agentWeChatChannelConfig) statusClass() string {
	c = c.normalized()
	if !c.Enabled {
		return "is-muted"
	}
	if c.Status == "expired" {
		return "is-warning"
	}
	if c.Status == "bound" || c.ProviderToken != "" || c.AccountID != "" {
		return "is-ready"
	}
	if c.Status == "scanned" || c.BindCode != "" || c.LoginQRCode != "" {
		return "is-warning"
	}
	return "is-muted"
}

func buildAdminAgentWeChatChannel(state installState, channel agentWeChatChannelConfig, entry string, requestBase string) adminAgentWeChatChannel {
	channel = channel.normalized()
	base := strings.TrimRight(requestBase, "/")
	if base == "" {
		base = "http://127.0.0.1:9754"
	}
	pairEndpoint := base + "/api/agent/pair/exchange"
	bindEndpoint := base + "/api/agent/channels/wechat/bind"
	sessionEndpoint := base + "/api/agent/channels/openclaw-weixin/session"
	messageEndpoint := base + "/api/agent/messages"
	hasQR := agentWeChatHasActiveQRCodeAt(channel, time.Now().UTC())
	qrURL := ""
	qrPayload := ""
	if hasQR {
		qrURL = channel.QRImageURL
		qrPayload = channel.QRPayload
	}
	scope := agentScopeForWeChatChannel(state, channel)
	adminUser := strings.TrimSpace(channel.AdminUser)
	adminUserLabel := "未关联管理员"
	if adminUser != "" {
		adminUserLabel = adminUser
		if user, ok := findAdminAccount(state, adminUser); ok {
			adminUserLabel = user.DisplayName + " (" + user.Username + ")"
		}
	}
	return adminAgentWeChatChannel{
		Key:             channel.Key,
		Action:          entry + "/wechat-agent/channels",
		Enabled:         channel.Enabled,
		IsBound:         channel.Status == "bound" || channel.ProviderToken != "" || channel.AccountID != "",
		StatusText:      channel.statusText(),
		StatusClass:     channel.statusClass(),
		BindCode:        channel.BindCode,
		BindSession:     maskAgentWeChatSession(firstNonEmpty(channel.LoginSession, channel.BindSession)),
		BindExpiresAt:   formatAgentChannelTime(channel.BindExpiresAt, "尚未生成"),
		HasQRCode:       hasQR,
		QRImageURL:      htmltemplate.URL(qrURL),
		QRPayload:       qrPayload,
		TokenMasked:     maskAgentWeChatToken(channel.Token),
		DisplayName:     channel.DisplayName,
		AdminUser:       adminUser,
		AdminUserLabel:  adminUserLabel,
		AdminRole:       agentWeChatChannelAdminRoleSummary(state, channel),
		DataScope:       scope.Mode,
		AllowedTables:   agentAllowedTablesString(scope.Tables),
		AllowedSummary:  roleTableAccessSummary(scope.Mode, scope.Tables),
		BaseURL:         channel.BaseURL,
		BotType:         channel.BotType,
		LoginMessage:    displayFallback(channel.LoginMessage, "等待通道动作"),
		AccountID:       displayFallback(channel.AccountID, "尚未接入"),
		OpenClawUserID:  displayFallback(channel.OpenClawUserID, "尚未确认"),
		BoundUser:       displayFallback(channel.BoundUser, "尚未绑定"),
		BoundAt:         formatAgentChannelTime(channel.BoundAt, "尚未绑定"),
		LastMessageAt:   formatAgentChannelTime(channel.LastMessageAt, "暂无消息"),
		LastHeartbeatAt: formatAgentChannelTime(channel.LastHeartbeatAt, "暂无心跳"),
		LastOutboundAt:  formatAgentChannelTime(channel.LastOutboundAt, "暂无回复"),
		LastError:       displayFallback(channel.LastError, "无"),
		PairEndpoint:    pairEndpoint,
		BindEndpoint:    bindEndpoint,
		SessionEndpoint: sessionEndpoint,
		MessageEndpoint: messageEndpoint,
		MeEndpoint:      base + "/api/agent/me",
	}
}

func buildAdminAgentWeChatChannels(state installState, channels agentChannelConfig, entry string, requestBase string) []adminAgentWeChatChannel {
	channels = channels.normalized()
	rows := make([]adminAgentWeChatChannel, 0, len(channels.WeChats))
	for _, channel := range channels.WeChats {
		rows = append(rows, buildAdminAgentWeChatChannel(state, channel, entry, requestBase))
	}
	return rows
}

func buildAdminAgentTableGroups(state installState, snapshots []adminSchemaSnapshotRecord) []adminAgentTableGroup {
	provider := newTableToolProvider(state, "", "")
	provider.schemaSnapshots = snapshots
	definitions := provider.tableDefinitions()
	groupLabels := []struct {
		key         string
		title       string
		description string
	}{
		{"core", "核心管理", "初始化状态、管理员、角色、菜单和权限边界。"},
		{"foundation", "基础服务", "数据源、文件、任务、通知、审计和系统设置。"},
		{"agent", "智能体记录", "Agent 会话、运行轨迹、工具结果和微信聊天归档。"},
		{"extension", "能力扩展", "插件扩展、资源模型和自动生成工具。"},
	}
	index := map[string]int{}
	groups := make([]adminAgentTableGroup, 0, len(groupLabels)+4)
	for _, item := range groupLabels {
		index[item.key] = len(groups)
		groups = append(groups, adminAgentTableGroup{
			Title:       item.title,
			Description: item.description,
		})
	}
	externalIndex := map[string]int{}
	for _, definition := range definitions {
		option := adminAgentTableOption{
			Name:        definition.Name,
			Label:       definition.DisplayName,
			Type:        definition.Type,
			Description: definition.Description,
		}
		groupKey := adminAgentTableGroupKey(definition.Name)
		if strings.HasPrefix(definition.Name, "datasource.") {
			sourceLabel := agentExternalTableSourceLabel(definition.DisplayName)
			externalKey := strings.ToLower(sourceLabel)
			if _, ok := externalIndex[externalKey]; !ok {
				externalIndex[externalKey] = len(groups)
				groups = append(groups, adminAgentTableGroup{
					Title:       "外部数据源：" + sourceLabel,
					Description: "来自真实业务库结构快照的只读数据表，需要通道显式授权后才能查询。",
				})
			}
			groups[externalIndex[externalKey]].Tables = append(groups[externalIndex[externalKey]].Tables, option)
			continue
		}
		if groupIndex, ok := index[groupKey]; ok {
			groups[groupIndex].Tables = append(groups[groupIndex].Tables, option)
		}
	}
	out := make([]adminAgentTableGroup, 0, len(groups))
	for _, group := range groups {
		if len(group.Tables) == 0 {
			continue
		}
		out = append(out, group)
	}
	return out
}

func adminAgentTableGroupKey(table string) string {
	switch normalizeAgentTableName(table) {
	case "install_state", "admin_settings", "admin_users", "admin_sessions", "admin_roles", "admin_menus", "admin_permissions":
		return "core"
	case "data_sources", "schema_snapshots", "storage_settings", "upload_files", "audit_events", "setting_change_logs", "background_tasks", "task_worker_settings", "background_task_logs", "notification_deliveries":
		return "foundation"
	case "agent_sessions", "agent_runs", "agent_tool_results", "agent_wechat_messages", "ai_capabilities", "agent_blueprint":
		return "agent"
	case "plugin_extensions", "resource_models", "resource_tools":
		return "extension"
	default:
		return "foundation"
	}
}

func agentExternalTableSourceLabel(displayName string) string {
	displayName = strings.TrimSpace(displayName)
	if displayName == "" {
		return "未命名"
	}
	if idx := strings.Index(displayName, "."); idx > 0 {
		return displayName[:idx]
	}
	return displayName
}

func findAgentWeChatChannelByKey(channels agentChannelConfig, key string) (agentWeChatChannelConfig, int, bool) {
	channels = channels.normalized()
	key = normalizeAgentWeChatChannelKey(key)
	if key == "" && len(channels.WeChats) == 1 {
		return channels.WeChats[0], 0, true
	}
	for i, channel := range channels.WeChats {
		if channel.Key == key {
			return channel, i, true
		}
	}
	return agentWeChatChannelConfig{}, -1, false
}

func findAgentWeChatChannelByAdminUser(channels agentChannelConfig, username string) (agentWeChatChannelConfig, int, bool) {
	channels = channels.normalized()
	username = strings.ToLower(strings.TrimSpace(username))
	if username == "" {
		return agentWeChatChannelConfig{}, -1, false
	}
	defaultKey := agentWeChatDefaultChannelKeyForAdmin(username)
	var fallback agentWeChatChannelConfig
	fallbackIndex := -1
	for i, channel := range channels.WeChats {
		if strings.ToLower(strings.TrimSpace(channel.AdminUser)) != username {
			continue
		}
		if channel.Key == defaultKey {
			return channel, i, true
		}
		if fallbackIndex == -1 {
			fallback = channel
			fallbackIndex = i
		}
	}
	if fallbackIndex >= 0 {
		return fallback, fallbackIndex, true
	}
	return agentWeChatChannelConfig{}, -1, false
}

func findAgentWeChatChannelByToken(channels agentChannelConfig, token string) (agentWeChatChannelConfig, int, bool) {
	channels = channels.normalized()
	token = strings.TrimSpace(token)
	if token == "" {
		return agentWeChatChannelConfig{}, -1, false
	}
	for i, channel := range channels.WeChats {
		if channel.Enabled && channel.Token != "" && len(channel.Token) == len(token) && subtle.ConstantTimeCompare([]byte(channel.Token), []byte(token)) == 1 {
			return channel, i, true
		}
	}
	return agentWeChatChannelConfig{}, -1, false
}

func findAgentWeChatChannelByPair(channels agentChannelConfig, code string, session string, requireSession bool) (agentWeChatChannelConfig, int, bool) {
	channels = channels.normalized()
	for i, channel := range channels.WeChats {
		if channel.Enabled && agentWeChatPairMatches(channel, code, session, requireSession) {
			return channel, i, true
		}
	}
	return agentWeChatChannelConfig{}, -1, false
}

func upsertAgentWeChatChannel(channels *agentChannelConfig, channel agentWeChatChannelConfig) {
	if channels == nil {
		return
	}
	normalized := channels.normalized()
	channel = channel.normalized()
	if channel.Key == "" {
		channel.Key = newAgentWeChatChannelKey()
	}
	for i, existing := range normalized.WeChats {
		if existing.Key == channel.Key {
			normalized.WeChats[i] = channel
			*channels = normalized.normalized()
			return
		}
	}
	normalized.WeChats = append(normalized.WeChats, channel)
	*channels = normalized.normalized()
}

func (s *adminServer) agentWeChatChannelSubmit(w http.ResponseWriter, r *http.Request) {
	state, entry, _, ok := s.authorizedAdminState(w, r, "wechat-agent", "agent.wechat.manage")
	if !ok {
		return
	}
	redirectBase := entry + "/wechat-agent"
	if err := r.ParseForm(); err != nil {
		http.Redirect(w, r, redirectBase+"?error="+url.QueryEscape("微信绑定渠道请求格式不正确"), http.StatusFound)
		return
	}
	before := redactedAgentChannelSnapshot(state.AgentChannels)
	channels := state.AgentChannels.normalized()
	action := strings.TrimSpace(r.FormValue("wechat_channel_action"))
	if action == "" {
		action = "save"
	}
	if action == "add" {
		http.Redirect(w, r, redirectBase+"?error="+url.QueryEscape("微信 Agent 通道会随着管理员账号自动创建，不能手动新增"), http.StatusFound)
		return
	}
	key := normalizeAgentWeChatChannelKey(r.FormValue("wechat_channel_key"))
	wechat, _, found := findAgentWeChatChannelByKey(channels, key)
	if !found {
		if currentChannel, _, ok := findAgentWeChatChannelByAdminUser(channels, s.sessionUsername(r)); ok {
			wechat = currentChannel
			key = wechat.Key
			found = true
		}
	}
	if found && key == "" {
		key = wechat.Key
	}
	if !found {
		http.Redirect(w, r, redirectBase+"?error="+url.QueryEscape("微信通道不存在，请先新增通道"), http.StatusFound)
		return
	}
	if action == "delete" {
		action = "disable"
	}
	enabled := r.FormValue("wechat_channel_enabled") == "1"
	if action == "disable" {
		enabled = false
	}
	now := time.Now().UTC()
	wechat.Enabled = enabled
	wechat.Key = key
	wechat.DisplayName = strings.TrimSpace(r.FormValue("wechat_channel_name"))
	if wechat.DisplayName == "" {
		if adminUser, ok := findAdminAccount(state, wechat.AdminUser); ok {
			wechat.DisplayName = agentWeChatDefaultChannelNameForAdmin(adminUser)
		} else {
			wechat.DisplayName = agentWeChatDefaultName
		}
	}
	adminUser := strings.TrimSpace(wechat.AdminUser)
	if adminUser == "" {
		adminUser = strings.TrimSpace(r.FormValue("wechat_admin_user"))
	}
	if adminUser == "" {
		adminUser = defaultAgentWeChatAdminUser(state, s.sessionUsername(r))
	}
	adminAccount, adminOK := findAdminAccount(state, adminUser)
	if !adminOK || adminAccount.Status == "disabled" {
		http.Redirect(w, r, redirectBase+"?error="+url.QueryEscape("请选择一个启用的后台管理员账号作为微信 Agent 身份"), http.StatusFound)
		return
	}
	wechat.AdminUser = adminAccount.Username
	wechat.AllowedTables = nil
	wechat.DataScope = ""
	wechat.BaseURL = normalizeAgentWeChatBaseURL(r.FormValue("wechat_base_url"))
	wechat.BotType = normalizeSettingText(r.FormValue("wechat_bot_type"), 16)
	if !enabled {
		wechat.Status = "disabled"
		wechat.BindCode = ""
		wechat.BindSession = ""
		wechat.LoginQRCode = ""
		wechat.LoginSession = ""
		wechat.QRPayload = ""
		wechat.QRImageURL = ""
		wechat.LoginMessage = ""
		wechat.ProviderToken = ""
		wechat.AccountID = ""
		wechat.OpenClawUserID = ""
		wechat.SyncBuffer = ""
		wechat.Token = ""
		wechat.BoundUser = ""
		wechat.ClientInfo = ""
		wechat.LastError = ""
		wechat.BindExpiresAt = time.Time{}
		wechat.BoundAt = time.Time{}
		wechat.LastMessageAt = time.Time{}
		wechat.LastHeartbeatAt = time.Time{}
		wechat.LastOutboundAt = time.Time{}
	} else {
		if wechat.CreatedAt.IsZero() {
			wechat.CreatedAt = now
		}
		if wechat.Token == "" || action == "reset_token" {
			wechat.Token = newAgentWeChatToken()
		}
		if action == "regenerate" {
			session := newAgentWeChatBindSession()
			qr, err := fetchAgentWeChatLoginQRCode(r.Context(), wechat.BaseURL, wechat.BotType)
			if err != nil {
				http.Redirect(w, r, redirectBase+"?error="+url.QueryEscape("生成 OpenClaw Weixin 登录二维码失败："+err.Error()), http.StatusFound)
				return
			}
			slog.Info("agent wechat login QR generated", "base_url", wechat.BaseURL, "bot_type", wechat.BotType, "qrcode", agentWeChatShortID(qr.Code), "expires_seconds", qr.ExpireSeconds)
			wechat.BindCode = ""
			wechat.BindSession = session
			wechat.BindExpiresAt = now.Add(time.Duration(qr.ExpireSeconds) * time.Second)
			if qr.ExpireSeconds <= 0 {
				wechat.BindExpiresAt = now.Add(agentWeChatPairTTL)
			}
			wechat.LoginQRCode = qr.Code
			wechat.LoginSession = session
			wechat.QRPayload = qr.Payload
			wechat.QRImageURL = qr.ImageURL
			wechat.LoginMessage = qr.Message
			wechat.ProviderToken = ""
			wechat.AccountID = ""
			wechat.OpenClawUserID = ""
			wechat.SyncBuffer = ""
			wechat.Status = "waiting"
			wechat.BoundUser = ""
			wechat.ClientInfo = ""
			wechat.LastError = ""
			wechat.BoundAt = time.Time{}
			wechat.LastMessageAt = time.Time{}
			wechat.LastOutboundAt = time.Time{}
		} else if action == "poll" {
			if wechat.LoginQRCode == "" {
				http.Redirect(w, r, redirectBase+"?error="+url.QueryEscape("请先生成 OpenClaw Weixin 登录二维码"), http.StatusFound)
				return
			}
			login, err := pollAgentWeChatLoginStatus(r.Context(), wechat.BaseURL, wechat.LoginQRCode)
			if err != nil {
				http.Redirect(w, r, redirectBase+"?error="+url.QueryEscape("刷新 OpenClaw Weixin 登录状态失败："+err.Error()), http.StatusFound)
				return
			}
			applyAgentWeChatLoginStatus(&wechat, login, now)
			slog.Info("agent wechat login status polled", "status", login.Status, "account", login.AccountID, "user", login.UserID, "has_provider_token", strings.TrimSpace(login.BotToken) != "")
		}
	}
	wechat.UpdatedAt = now
	upsertAgentWeChatChannel(&channels, wechat.normalized())
	state.AgentChannels = channels
	if err := s.store.Save(state); err != nil {
		http.Redirect(w, r, redirectBase+"?error="+url.QueryEscape("保存微信绑定渠道失败："+err.Error()), http.StatusFound)
		return
	}
	changeAction := "保存微信绑定渠道"
	changeDetail := "更新 AI Agent 微信对话入口、绑定码或绑定 token"
	if action == "disable" {
		changeAction = "禁用微信绑定渠道"
		changeDetail = "禁用 AI Agent 微信对话入口，保留通道配置和聊天归档"
	}
	s.recordSettingChange(r, state, "agent_channel", changeAction, changeDetail, before, redactedAgentChannelSnapshot(state.AgentChannels))
	s.recordAuditEvent(r, state, auditEventInput{
		Category:   "ai",
		Action:     changeAction,
		Detail:     "通道：" + wechat.DisplayName + "，状态：" + wechat.statusText(),
		StatusCode: http.StatusFound,
	})
	http.Redirect(w, r, redirectBase+"?saved=wechat_channel", http.StatusFound)
}

func (s *adminServer) agentWeChatBindInfo(w http.ResponseWriter, r *http.Request) {
	state, err := s.store.Load()
	if err != nil {
		writeJSON(w, http.StatusInternalServerError, map[string]string{"error": "读取初始化状态失败"})
		return
	}
	channels := state.AgentChannels.normalized()
	code := normalizeAgentWeChatBindCode(r.URL.Query().Get("code"))
	session := normalizeAgentWeChatSession(firstNonEmpty(r.URL.Query().Get("session"), r.URL.Query().Get("session_token")))
	wechat, _, found := findAgentWeChatChannelByPair(channels, code, session, true)
	if !state.Initialized || !found || !wechat.Enabled {
		writeAgentWeChatBindInfoError(w, r, http.StatusForbidden, "微信绑定渠道未启用")
		return
	}
	if agentWeChatPairExpiredAt(wechat, time.Now().UTC()) {
		writeAgentWeChatBindInfoError(w, r, http.StatusGone, "微信绑定二维码已过期")
		return
	}
	base := requestPublicBaseURL(r)
	if strings.Contains(strings.ToLower(r.Header.Get("Accept")), "application/json") {
		writeJSON(w, http.StatusOK, map[string]any{
			"ok":          true,
			"channel":     wechat.Key,
			"code":        code,
			"session":     session,
			"server":      base,
			"expires_at":  wechat.BindExpiresAt.Format(time.RFC3339),
			"bind_api":    base + "/api/agent/channels/wechat/bind",
			"message_api": base + "/api/agent/messages",
		})
		return
	}
	w.Header().Set("Content-Type", "text/html; charset=utf-8")
	w.WriteHeader(http.StatusOK)
	_, _ = fmt.Fprintf(w, `<!doctype html><html lang="zh-CN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>微信绑定渠道</title><style>body{margin:0;background:#f3f6f5;color:#172225;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.box{max-width:420px;margin:12vh auto;padding:24px;border:1px solid #dce3e2;border-radius:8px;background:#fff}.code{font-size:34px;font-weight:800;letter-spacing:.08em}.muted{color:#647174;font-size:13px;line-height:1.7}.mono{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;word-break:break-all}</style></head><body><main class="box"><h1>微信绑定渠道</h1><p class="muted">二维码有效。请由微信通道客户端读取并调用绑定接口完成确认。</p><div class="code">%s</div><p class="muted">会话：<span class="mono">%s</span></p><p class="muted">到期：%s</p></main></body></html>`,
		htmltemplate.HTMLEscapeString(code),
		htmltemplate.HTMLEscapeString(maskAgentWeChatSession(session)),
		htmltemplate.HTMLEscapeString(formatAgentChannelTime(wechat.BindExpiresAt, "未知")),
	)
}

func (s *adminServer) agentWeChatBindExchange(w http.ResponseWriter, r *http.Request) {
	var payload agentWeChatPairExchangeRequest
	if err := decodeAgentAPIRequest(r, &payload, 32*1024); err != nil {
		writeJSON(w, http.StatusBadRequest, map[string]string{"error": "invalid request"})
		return
	}
	query := r.URL.Query()
	if payload.Code == "" {
		payload.Code = query.Get("code")
	}
	if payload.Session == "" && payload.SessionToken == "" {
		payload.Session = firstNonEmpty(query.Get("session"), query.Get("session_token"))
	}
	s.exchangeAgentWeChatPair(w, r, payload, true)
}

func (s *adminServer) agentWeChatSession(w http.ResponseWriter, r *http.Request) {
	state, wechat, ok := s.authenticatedAgentWeChatState(w, r)
	if !ok {
		return
	}
	if r.Method == http.MethodGet {
		writeJSON(w, http.StatusOK, agentWeChatSessionResponse{
			OK:              true,
			Channel:         agentWeChatProviderID,
			Status:          wechat.Status,
			AccountID:       wechat.AccountID,
			UserID:          wechat.OpenClawUserID,
			DisplayName:     wechat.DisplayName,
			AgentHint:       wechat.AgentHint,
			LastError:       wechat.LastError,
			LastHeartbeatAt: formatStoreTime(wechat.LastHeartbeatAt),
			Endpoints:       agentWeChatEndpoints(requestPublicBaseURL(r)),
		})
		return
	}
	if r.Method != http.MethodPost {
		http.NotFound(w, r)
		return
	}
	var payload agentWeChatSessionUpdateRequest
	if err := decodeAgentAPIRequest(r, &payload, 128*1024); err != nil {
		writeJSON(w, http.StatusBadRequest, map[string]string{"error": "invalid request"})
		return
	}
	now := time.Now().UTC()
	channels := state.AgentChannels.normalized()
	wechat, _, ok = findAgentWeChatChannelByKey(channels, wechat.Key)
	if !ok {
		writeJSON(w, http.StatusUnauthorized, map[string]string{"error": "wechat agent channel not found"})
		return
	}
	wechat = wechat.normalized()
	if payload.DisplayName != "" {
		wechat.DisplayName = normalizeSettingText(payload.DisplayName, 48)
	}
	if payload.AgentHint != "" {
		wechat.AgentHint = normalizeSettingText(payload.AgentHint, 64)
	}
	if payload.SessionKey != "" {
		wechat.LoginSession = normalizeAgentWeChatSession(payload.SessionKey)
	}
	if payload.QRDataURL != "" || payload.QRURL != "" || payload.QRPayload != "" {
		qrPayload := firstNonEmpty(payload.QRPayload, payload.QRURL, payload.QRDataURL)
		wechat.QRPayload = strings.TrimSpace(qrPayload)
		if strings.HasPrefix(strings.ToLower(strings.TrimSpace(payload.QRDataURL)), "data:image/") {
			wechat.QRImageURL = strings.TrimSpace(payload.QRDataURL)
		} else if qrPayload != "" {
			imageURL, err := agentWeChatQRCodeDataURL(qrPayload)
			if err != nil {
				writeJSON(w, http.StatusBadRequest, map[string]string{"error": "invalid qr payload"})
				return
			}
			wechat.QRImageURL = imageURL
		}
		if wechat.BindExpiresAt.IsZero() {
			wechat.BindExpiresAt = now.Add(agentWeChatPairTTL)
		}
	}
	if payload.Status != "" {
		applyAgentWeChatLoginStatus(&wechat, agentWeChatLoginStatus{
			Status:    payload.Status,
			BotToken:  payload.ProviderToken,
			AccountID: firstNonEmpty(payload.AccountID, payload.BotID),
			BaseURL:   payload.BaseURL,
			UserID:    firstNonEmpty(payload.UserID, payload.From),
		}, now)
	}
	if payload.AccountID != "" || payload.BotID != "" {
		wechat.AccountID = normalizeSettingText(firstNonEmpty(payload.AccountID, payload.BotID), 120)
	}
	if payload.UserID != "" || payload.From != "" {
		wechat.OpenClawUserID = normalizeSettingText(firstNonEmpty(payload.UserID, payload.From), 120)
		wechat.BoundUser = wechat.OpenClawUserID
	}
	if payload.ProviderToken != "" {
		wechat.ProviderToken = strings.TrimSpace(payload.ProviderToken)
		wechat.LastError = ""
	}
	if payload.Message != "" {
		wechat.LoginMessage = normalizeSettingText(payload.Message, 160)
	}
	if wechat.AccountID != "" || wechat.ProviderToken != "" {
		wechat.Status = "bound"
		if wechat.BoundAt.IsZero() {
			wechat.BoundAt = now
		}
		wechat.LastError = ""
	}
	slog.Info("agent wechat session updated", "status", wechat.Status, "account", wechat.AccountID, "user", wechat.OpenClawUserID, "has_provider_token", wechat.ProviderToken != "", "has_qr", wechat.QRPayload != "")
	wechat.LastHeartbeatAt = now
	wechat.UpdatedAt = now
	if err := s.store.UpdateAgentWeChatRuntimeChannels([]agentWeChatChannelConfig{wechat.normalized()}); err != nil {
		writeJSON(w, http.StatusInternalServerError, map[string]string{"error": "save session failed"})
		return
	}
	s.recordAuditEvent(r, state, auditEventInput{
		Category:   "ai",
		Action:     "OpenClaw Weixin 会话",
		Actor:      "agent:" + displayFallback(wechat.AccountID, agentWeChatProviderID),
		Detail:     "OpenClaw Weixin 通道状态：" + wechat.statusText(),
		StatusCode: http.StatusOK,
	})
	writeJSON(w, http.StatusOK, agentWeChatSessionResponse{
		OK:              true,
		Channel:         agentWeChatProviderID,
		Status:          wechat.Status,
		AccountID:       wechat.AccountID,
		UserID:          wechat.OpenClawUserID,
		DisplayName:     wechat.DisplayName,
		AgentHint:       wechat.AgentHint,
		LastError:       wechat.LastError,
		LastHeartbeatAt: formatStoreTime(wechat.LastHeartbeatAt),
		Endpoints:       agentWeChatEndpoints(requestPublicBaseURL(r)),
	})
}

func (s *adminServer) agentWeChatPairExchange(w http.ResponseWriter, r *http.Request) {
	var payload agentWeChatPairExchangeRequest
	if err := decodeAgentAPIRequest(r, &payload, 32*1024); err != nil {
		writeJSON(w, http.StatusBadRequest, map[string]string{"error": "invalid request"})
		return
	}
	s.exchangeAgentWeChatPair(w, r, payload, false)
}

func (s *adminServer) exchangeAgentWeChatPair(w http.ResponseWriter, r *http.Request, payload agentWeChatPairExchangeRequest, requireSession bool) {
	state, err := s.store.Load()
	if err != nil {
		writeJSON(w, http.StatusInternalServerError, map[string]string{"error": "读取初始化状态失败"})
		return
	}
	if !state.Initialized {
		writeJSON(w, http.StatusNotFound, map[string]string{"error": "system not initialized"})
		return
	}
	code := normalizeAgentWeChatBindCode(payload.Code)
	session := normalizeAgentWeChatSession(firstNonEmpty(payload.Session, payload.SessionToken))
	channels := state.AgentChannels.normalized()
	wechat, _, found := findAgentWeChatChannelByPair(channels, code, session, requireSession)
	if !found || !wechat.Enabled {
		writeJSON(w, http.StatusForbidden, map[string]string{"error": "wechat agent channel disabled"})
		return
	}
	now := time.Now().UTC()
	if agentWeChatPairExpiredAt(wechat, now) {
		writeJSON(w, http.StatusGone, map[string]string{"error": "pair code expired"})
		return
	}
	token := newAgentWeChatToken()
	displayName := normalizeSettingText(firstNonEmpty(payload.DisplayName, payload.Nickname, payload.NickName), 48)
	if displayName == "" {
		displayName = agentWeChatDefaultName
	}
	clientInfo := payload.ClientInfo.String()
	wechat.Token = token
	wechat.BindCode = ""
	wechat.BindSession = ""
	wechat.BindExpiresAt = time.Time{}
	wechat.LoginQRCode = ""
	wechat.LoginSession = ""
	wechat.QRPayload = ""
	wechat.QRImageURL = ""
	wechat.Status = "bound"
	wechat.DisplayName = displayName
	wechat.AgentHint = normalizeSettingText(firstNonEmpty(payload.AgentHint, agentWeChatDefaultHint), 64)
	wechat.BoundUser = normalizeSettingText(firstNonEmpty(payload.UserID, payload.OpenID, payload.OpenIDAlt, displayName), 120)
	wechat.OpenClawUserID = wechat.BoundUser
	wechat.AccountID = normalizeSettingText(firstNonEmpty(payload.AccountID, payload.BotID), 120)
	wechat.ClientInfo = clientInfo
	wechat.LastError = ""
	wechat.BoundAt = now
	wechat.LastHeartbeatAt = now
	wechat.UpdatedAt = now
	if err := s.store.UpdateAgentWeChatRuntimeChannels([]agentWeChatChannelConfig{wechat.normalized()}); err != nil {
		writeJSON(w, http.StatusInternalServerError, map[string]string{"error": "save pairing failed"})
		return
	}
	s.recordAuditEvent(r, state, auditEventInput{
		Category:   "ai",
		Action:     "微信渠道绑定",
		Actor:      "agent:" + displayName,
		Detail:     "微信绑定渠道已换取 Agent token",
		StatusCode: http.StatusOK,
	})
	base := requestPublicBaseURL(r)
	writeJSON(w, http.StatusOK, map[string]any{
		"token":       token,
		"token_type":  "Bearer",
		"scopes":      []string{"agent:chat", "agent:read"},
		"server_time": now.Format(time.RFC3339),
		"user": map[string]any{
			"id": displayFallback(wechat.BoundUser, "moyi-admin"),
		},
		"agent": map[string]any{
			"id":          wechat.Key,
			"displayName": displayName,
			"agentHint":   wechat.AgentHint,
			"tokenPrefix": tokenPrefix(token),
			"createdAt":   now.Unix(),
		},
		"endpoints": agentWeChatEndpoints(base),
	})
}

func (s *adminServer) agentWeChatMe(w http.ResponseWriter, r *http.Request) {
	state, wechat, ok := s.authenticatedAgentWeChatState(w, r)
	if !ok {
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{
		"ok":          true,
		"channel":     wechat.Key,
		"site_name":   state.SiteName,
		"server_time": time.Now().UTC().Format(time.RFC3339),
		"user": map[string]any{
			"id": displayFallback(wechat.BoundUser, "moyi-admin"),
		},
		"agent": map[string]any{
			"id":          wechat.Key,
			"displayName": wechat.DisplayName,
			"agentHint":   wechat.AgentHint,
			"tokenPrefix": tokenPrefix(wechat.Token),
		},
		"endpoints": agentWeChatEndpoints(requestPublicBaseURL(r)),
	})
}

func (s *adminServer) agentWeChatMessage(w http.ResponseWriter, r *http.Request) {
	state, wechat, ok := s.authenticatedAgentWeChatState(w, r)
	if !ok {
		return
	}
	var payload agentWeChatMessageRequest
	if err := decodeAgentAPIRequest(r, &payload, 128*1024); err != nil {
		writeJSON(w, http.StatusBadRequest, agentWeChatMessageResponse{OK: false, Error: "消息请求格式不正确"})
		return
	}
	message := strings.TrimSpace(firstNonEmpty(payload.Message, payload.Text, payload.Body, payload.CommandBody, payload.CommandBodyAlt))
	if message == "" {
		writeJSON(w, http.StatusBadRequest, agentWeChatMessageResponse{OK: false, Error: "请输入要转发给智能体的微信消息"})
		return
	}
	if len([]rune(message)) > agentWeChatMaxMessageRunes {
		writeJSON(w, http.StatusBadRequest, agentWeChatMessageResponse{OK: false, Error: "单次消息不能超过 2000 个字符"})
		return
	}
	sessionID := normalizeAgentSessionID(payload.SessionID)
	if sessionID == "" {
		sessionID = agentWeChatSessionID(firstNonEmpty(payload.ConversationID, payload.SessionKey, payload.SessionKeyAlt, payload.From, payload.FromAlt, payload.To, payload.ToAlt), firstNonEmpty(payload.SenderID, payload.From, payload.FromAlt, payload.AccountID, payload.AccountIDAlt))
	}
	startedAt := time.Now()
	actor := "wechat"
	if payload.SenderName != "" {
		actor = "wechat:" + normalizeSettingText(payload.SenderName, 40)
	} else if firstNonEmpty(payload.From, payload.FromAlt) != "" {
		actor = "wechat:" + normalizeSettingText(firstNonEmpty(payload.From, payload.FromAlt), 40)
	} else if payload.SenderID != "" {
		actor = "wechat:" + normalizeSettingText(payload.SenderID, 40)
	}
	messageID := firstNonEmpty(payload.MessageID, payload.MessageSid, payload.MessageSidAlt)
	agentPayload := agentChatRequest{
		Message:   message,
		SessionID: sessionID,
	}
	scope := agentScopeForWeChatChannel(state, wechat)
	roleAccess := adminRoleAccessForUsername(state, wechat.AdminUser)
	agentPayload.TableAccessMode = scope.Mode
	agentPayload.AllowedTables = scope.Tables
	agentPayload.AllowReadOnlyQuery = boolPtr(roleAccess.HasPermission("agent.sql.select"))
	agentPayload.AllowWebRead = boolPtr(roleAccess.HasPermission("agent.web.read"))
	agentPayload.AllowImageGenerate = boolPtr(roleAccess.HasPermission("agent.image.generate"))
	response := s.runAgentChat(r.Context(), state, agentPayload)
	response.SessionID = sessionID
	duration := time.Since(startedAt)
	statusCode := http.StatusOK
	if !response.OK {
		statusCode = http.StatusBadRequest
	}
	_ = s.store.AppendAgentRun(agentRunRecord{
		ID:          response.Run.ID,
		SessionID:   sessionID,
		Actor:       actor,
		Mode:        response.Run.Mode,
		Goal:        response.Run.Goal,
		Message:     message,
		Reply:       response.Reply,
		Status:      agentRunStatus(response.OK),
		ModelUsed:   response.ModelUsed,
		ToolCount:   len(response.ToolResults),
		FileCount:   len(response.Files),
		DurationMS:  duration.Milliseconds(),
		StartedAt:   startedAt.UTC(),
		Run:         response.Run,
		ToolResults: response.ToolResults,
		Files:       response.Files,
	})
	fromUser := firstNonEmpty(payload.From, payload.FromAlt, payload.SenderID, payload.SenderName)
	toUser := firstNonEmpty(payload.To, payload.ToAlt, payload.AccountID, payload.AccountIDAlt)
	_ = s.store.UpsertAgentWeChatMessage(agentWeChatMessageRecord{
		ChannelKey:  wechat.Key,
		ChannelName: wechat.DisplayName,
		Provider:    agentWeChatProviderID,
		MessageID:   messageID,
		SessionID:   sessionID,
		RunID:       response.Run.ID,
		FromUserID:  fromUser,
		ToUserID:    toUser,
		InboundText: message,
		ReplyText:   response.Reply,
		Files:       response.Files,
		Status:      agentRunStatus(response.OK),
		Error:       response.Error,
		ModelUsed:   response.ModelUsed,
		ToolCount:   len(response.ToolResults),
		FileCount:   len(response.Files),
		DurationMS:  duration.Milliseconds(),
		ReceivedAt:  startedAt.UTC(),
		RepliedAt:   time.Now().UTC(),
		CreatedAt:   startedAt.UTC(),
	})
	wechat.LastMessageAt = time.Now().UTC()
	wechat.LastHeartbeatAt = wechat.LastMessageAt
	if firstNonEmpty(payload.AccountID, payload.AccountIDAlt) != "" {
		wechat.AccountID = normalizeSettingText(firstNonEmpty(payload.AccountID, payload.AccountIDAlt), 120)
	}
	if firstNonEmpty(payload.From, payload.FromAlt) != "" {
		wechat.OpenClawUserID = normalizeSettingText(firstNonEmpty(payload.From, payload.FromAlt), 120)
		wechat.BoundUser = wechat.OpenClawUserID
	}
	wechat.UpdatedAt = wechat.LastMessageAt
	if err := s.store.UpdateAgentWeChatRuntimeChannels([]agentWeChatChannelConfig{wechat.normalized()}); err != nil {
		slog.Error("agent wechat failed to persist message runtime fields", "key", wechat.Key, "message_id", messageID, "error", err)
	}
	s.recordAuditEvent(r, state, auditEventInput{
		Category:   "ai",
		Action:     "微信渠道对话",
		Actor:      actor,
		Detail:     fmt.Sprintf("会话 %s，模式 %s，工具 %d 次", sessionID, response.Run.Mode, len(response.ToolResults)),
		StatusCode: statusCode,
		Duration:   duration,
	})
	writeJSON(w, statusCode, agentWeChatMessageResponse{
		OK:          response.OK,
		Channel:     agentWeChatProviderID,
		MessageID:   messageID,
		SessionID:   sessionID,
		RunID:       response.Run.ID,
		Reply:       response.Reply,
		ModelUsed:   response.ModelUsed,
		Files:       response.Files,
		ToolResults: response.ToolResults,
		Error:       response.Error,
	})
}

func (s *adminServer) startAgentWeChatChannelWorker() {
	slog.Info("agent wechat worker starting", "provider", agentWeChatProviderID)
	go func() {
		timer := time.NewTimer(3 * time.Second)
		defer timer.Stop()
		for {
			<-timer.C
			_, interval := s.runAgentWeChatChannelPollOnce(context.Background())
			if interval < time.Second {
				interval = time.Second
			}
			timer.Reset(interval)
		}
	}()
}

func (s *adminServer) runAgentWeChatChannelPollOnce(ctx context.Context) (bool, time.Duration) {
	if !s.agentChannelMu.TryLock() {
		slog.Debug("agent wechat worker skipped because previous poll is still running")
		return false, time.Second
	}
	defer s.agentChannelMu.Unlock()

	state, err := s.store.Load()
	if err != nil {
		slog.Error("agent wechat worker failed to load state", "error", err)
		return false, 30 * time.Second
	}
	if !state.Initialized {
		return false, 30 * time.Second
	}
	channels := state.AgentChannels.normalized()
	if len(channels.WeChats) == 0 {
		return false, 5 * time.Second
	}
	originalChannels := make(map[string]agentWeChatChannelConfig, len(channels.WeChats))
	for _, channel := range channels.WeChats {
		normalized := channel.normalized()
		originalChannels[normalized.Key] = normalized
	}

	ran := false
	nextInterval := time.Second
	for _, current := range channels.WeChats {
		wechat := current.normalized()
		if !agentWeChatCanPoll(wechat) {
			continue
		}
		ran = true
		slog.Debug("agent wechat getupdates polling", "key", wechat.Key, "account", wechat.AccountID, "sync_buffer", agentWeChatShortID(wechat.SyncBuffer))
		updates, err := fetchAgentWeChatUpdates(ctx, wechat)
		if err != nil {
			slog.Error("agent wechat getupdates failed", "key", wechat.Key, "account", wechat.AccountID, "error", err)
			wechat.LastError = normalizeSettingText("微信消息监听失败："+err.Error(), 240)
			wechat.LastHeartbeatAt = time.Now().UTC()
			wechat.UpdatedAt = wechat.LastHeartbeatAt
			upsertAgentWeChatRuntimeChannel(&channels, wechat, originalChannels)
			nextInterval = 10 * time.Second
			continue
		}
		if code := updates.errorCode(); code != 0 {
			message := firstNonEmpty(updates.ErrMsg, fmt.Sprintf("provider errcode %d", code))
			expired := code == agentWeChatSessionExpired
			slog.Error("agent wechat provider returned error", "key", wechat.Key, "account", wechat.AccountID, "code", code, "message", message, "expired", expired)
			wechat.LastError = normalizeSettingText("微信消息监听失败："+message, 240)
			wechat.LastHeartbeatAt = time.Now().UTC()
			wechat.UpdatedAt = wechat.LastHeartbeatAt
			if expired {
				wechat.Status = "expired"
				wechat.ProviderToken = ""
				wechat.LoginMessage = "微信会话已失效，请重新生成二维码登录"
				nextInterval = 30 * time.Second
			} else if nextInterval < 10*time.Second {
				nextInterval = 10 * time.Second
			}
			upsertAgentWeChatRuntimeChannel(&channels, wechat, originalChannels)
			continue
		}
		if len(updates.Messages) > 0 {
			slog.Info("agent wechat getupdates received messages", "key", wechat.Key, "account", wechat.AccountID, "count", len(updates.Messages), "next_sync_buffer", agentWeChatShortID(updates.GetUpdatesBuf))
		} else if time.Since(s.agentChannelLastIdleLogAt) > 30*time.Second {
			slog.Info("agent wechat channel listening", "key", wechat.Key, "account", wechat.AccountID, "status", wechat.Status, "sync_buffer", agentWeChatShortID(firstNonEmpty(updates.GetUpdatesBuf, wechat.SyncBuffer)))
			s.agentChannelLastIdleLogAt = time.Now()
		}
		now := time.Now().UTC()
		if strings.TrimSpace(updates.GetUpdatesBuf) != "" {
			wechat.SyncBuffer = strings.TrimSpace(updates.GetUpdatesBuf)
		}
		wechat.LastHeartbeatAt = now
		wechat.LoginMessage = "微信通道监听中"
		s.processAgentWeChatChannelMessages(ctx, state, &wechat, updates.Messages)
		wechat.UpdatedAt = time.Now().UTC()
		upsertAgentWeChatRuntimeChannel(&channels, wechat.normalized(), originalChannels)
	}
	if !ran {
		return false, 5 * time.Second
	}
	if err := s.store.UpdateAgentWeChatRuntimeChannels(channels.normalized().WeChats); err != nil {
		slog.Error("agent wechat failed to save channel state", "error", err)
		return true, 10 * time.Second
	}
	return true, nextInterval
}

func upsertAgentWeChatRuntimeChannel(channels *agentChannelConfig, updated agentWeChatChannelConfig, originals map[string]agentWeChatChannelConfig) {
	updated = updated.normalized()
	if original, ok := originals[updated.Key]; ok {
		updated = preserveAgentWeChatAuthorization(updated, original)
	}
	upsertAgentWeChatChannel(channels, updated)
}

func preserveAgentWeChatAuthorization(updated agentWeChatChannelConfig, original agentWeChatChannelConfig) agentWeChatChannelConfig {
	original = original.normalized()
	updated.AdminUser = original.AdminUser
	updated.DataScope = original.DataScope
	updated.AllowedTables = append([]string(nil), original.AllowedTables...)
	return updated
}

func (s *adminServer) processAgentWeChatChannelMessages(ctx context.Context, state installState, wechat *agentWeChatChannelConfig, messages []agentWeChatProviderMessage) {
	if wechat == nil {
		return
	}
	for _, message := range messages {
		if agentWeChatShouldSkipMessage(message) {
			slog.Debug("agent wechat skipped outbound/provider message", "message_id", agentWeChatMessageID(message.MessageID), "type", message.MessageType)
			continue
		}
		inboundText := truncateRunes(agentWeChatProviderMessageText(message), agentWeChatMaxMessageRunes)
		if inboundText == "" {
			s.handleAgentWeChatUnsupportedInbound(ctx, wechat, message)
			continue
		}
		sessionID := agentWeChatSessionID(firstNonEmpty(message.SessionID, message.FromUserID, message.ClientID), message.FromUserID)
		scope := agentScopeForWeChatChannel(state, *wechat)
		slog.Info("agent wechat inbound message", "key", wechat.Key, "message_id", agentWeChatMessageID(message.MessageID), "from", message.FromUserID, "to", message.ToUserID, "session", sessionID, "text_len", len([]rune(inboundText)), "admin_user", wechat.AdminUser, "data_scope", scope.Mode, "allowed_tables", len(scope.Tables))
		stopTyping := s.startAgentWeChatTyping(ctx, *wechat, message)
		startedAt := time.Now()
		receivedAt := agentWeChatProviderMessageTime(message, startedAt.UTC())
		response := s.runAgentChat(ctx, state, agentChatRequest{
			Message:            inboundText,
			SessionID:          sessionID,
			TableAccessMode:    scope.Mode,
			AllowedTables:      scope.Tables,
			AllowReadOnlyQuery: boolPtr(adminRoleAccessForUsername(state, wechat.AdminUser).HasPermission("agent.sql.select")),
			AllowWebRead:       boolPtr(adminRoleAccessForUsername(state, wechat.AdminUser).HasPermission("agent.web.read")),
			AllowImageGenerate: boolPtr(adminRoleAccessForUsername(state, wechat.AdminUser).HasPermission("agent.image.generate")),
		})
		response.SessionID = sessionID
		duration := time.Since(startedAt)
		slog.Info("agent wechat agent run completed", "key", wechat.Key, "message_id", agentWeChatMessageID(message.MessageID), "run_id", response.Run.ID, "session", sessionID, "ok", response.OK, "tools", len(response.ToolResults), "files", len(response.Files), "model_used", response.ModelUsed, "duration_ms", duration.Milliseconds(), "table_authorization", response.Run.Metadata["table_authorization"])
		actor := "wechat:" + normalizeSettingText(firstNonEmpty(message.FromUserID, wechat.OpenClawUserID, wechat.BoundUser), 40)
		if err := s.store.AppendAgentRun(agentRunRecord{
			ID:          response.Run.ID,
			SessionID:   sessionID,
			Actor:       actor,
			Mode:        response.Run.Mode,
			Goal:        response.Run.Goal,
			Message:     inboundText,
			Reply:       response.Reply,
			Status:      agentRunStatus(response.OK),
			ModelUsed:   response.ModelUsed,
			ToolCount:   len(response.ToolResults),
			FileCount:   len(response.Files),
			DurationMS:  duration.Milliseconds(),
			StartedAt:   startedAt.UTC(),
			Run:         response.Run,
			ToolResults: response.ToolResults,
			Files:       response.Files,
		}); err != nil {
			slog.Error("agent wechat failed to append agent run", "message_id", agentWeChatMessageID(message.MessageID), "run_id", response.Run.ID, "error", err)
		}
		wechat.LastMessageAt = time.Now().UTC()
		wechat.LastHeartbeatAt = wechat.LastMessageAt
		if strings.TrimSpace(message.FromUserID) != "" {
			wechat.OpenClawUserID = normalizeSettingText(message.FromUserID, 120)
			wechat.BoundUser = wechat.OpenClawUserID
		}
		if strings.TrimSpace(message.ToUserID) != "" {
			wechat.AccountID = normalizeSettingText(message.ToUserID, 120)
		}
		replyText := strings.TrimSpace(response.Reply)
		if len(response.Files) > 0 {
			replyText = agentWeChatAttachmentReplyText(response.Files)
		}
		replyErrors := []string{}
		if !response.OK && strings.TrimSpace(response.Error) != "" {
			replyErrors = append(replyErrors, response.Error)
		}
		repliedAt := time.Time{}
		if replyText != "" {
			slog.Info("agent wechat sending text reply", "message_id", agentWeChatMessageID(message.MessageID), "to", message.FromUserID, "reply_len", len([]rune(replyText)))
			if err := sendAgentWeChatTextReply(ctx, *wechat, message, replyText); err != nil {
				slog.Error("agent wechat text reply failed", "message_id", agentWeChatMessageID(message.MessageID), "to", message.FromUserID, "error", err)
				wechat.LastError = normalizeSettingText("微信回复发送失败："+err.Error(), 240)
				replyErrors = append(replyErrors, err.Error())
			} else {
				slog.Info("agent wechat text reply sent", "message_id", agentWeChatMessageID(message.MessageID), "to", message.FromUserID)
				wechat.LastOutboundAt = time.Now().UTC()
				repliedAt = wechat.LastOutboundAt
				wechat.LastError = ""
			}
		}
		for _, file := range response.Files {
			slog.Info("agent wechat sending attachment reply", "message_id", agentWeChatMessageID(message.MessageID), "to", message.FromUserID, "file", file.Name, "size", file.Size, "mime", file.MIME)
			if err := s.sendAgentWeChatFileReply(ctx, *wechat, message, file); err != nil {
				slog.Error("agent wechat attachment reply failed", "message_id", agentWeChatMessageID(message.MessageID), "to", message.FromUserID, "file", file.Name, "error", err)
				wechat.LastError = normalizeSettingText("微信附件发送失败："+err.Error(), 240)
				replyErrors = append(replyErrors, file.Name+": "+err.Error())
				continue
			}
			slog.Info("agent wechat attachment reply sent", "message_id", agentWeChatMessageID(message.MessageID), "to", message.FromUserID, "file", file.Name)
			wechat.LastOutboundAt = time.Now().UTC()
			repliedAt = wechat.LastOutboundAt
			wechat.LastError = ""
		}
		archiveStatus := agentRunStatus(response.OK && len(replyErrors) == 0)
		if repliedAt.IsZero() && (replyText != "" || len(response.Files) > 0 || len(replyErrors) > 0) {
			repliedAt = time.Now().UTC()
		}
		if err := s.store.UpsertAgentWeChatMessage(agentWeChatMessageRecord{
			ChannelKey:  wechat.Key,
			ChannelName: wechat.DisplayName,
			Provider:    agentWeChatProviderID,
			MessageID:   agentWeChatMessageID(message.MessageID),
			SessionID:   sessionID,
			RunID:       response.Run.ID,
			FromUserID:  message.FromUserID,
			ToUserID:    message.ToUserID,
			InboundText: inboundText,
			ReplyText:   replyText,
			Files:       response.Files,
			Status:      archiveStatus,
			Error:       strings.Join(replyErrors, "; "),
			ModelUsed:   response.ModelUsed,
			ToolCount:   len(response.ToolResults),
			FileCount:   len(response.Files),
			DurationMS:  duration.Milliseconds(),
			ReceivedAt:  receivedAt,
			RepliedAt:   repliedAt,
			CreatedAt:   startedAt.UTC(),
		}); err != nil {
			slog.Error("agent wechat failed to archive message", "key", wechat.Key, "message_id", agentWeChatMessageID(message.MessageID), "run_id", response.Run.ID, "error", err)
		}
		stopTyping()
	}
}

func (s *adminServer) handleAgentWeChatUnsupportedInbound(ctx context.Context, wechat *agentWeChatChannelConfig, message agentWeChatProviderMessage) {
	if wechat == nil {
		return
	}
	startedAt := time.Now()
	receivedAt := agentWeChatProviderMessageTime(message, startedAt.UTC())
	sessionID := agentWeChatSessionID(firstNonEmpty(message.SessionID, message.FromUserID, message.ClientID), message.FromUserID)
	inboundSummary := agentWeChatNonTextMessageSummary(message)
	replyText := agentWeChatUnsupportedMessageReply()
	slog.Info("agent wechat unsupported non-text inbound message", "key", wechat.Key, "message_id", agentWeChatMessageID(message.MessageID), "from", message.FromUserID, "to", message.ToUserID, "session", sessionID, "items", len(message.ItemList), "summary", inboundSummary)

	wechat.LastMessageAt = time.Now().UTC()
	wechat.LastHeartbeatAt = wechat.LastMessageAt
	if strings.TrimSpace(message.FromUserID) != "" {
		wechat.OpenClawUserID = normalizeSettingText(message.FromUserID, 120)
		wechat.BoundUser = wechat.OpenClawUserID
	}
	if strings.TrimSpace(message.ToUserID) != "" {
		wechat.AccountID = normalizeSettingText(message.ToUserID, 120)
	}

	replyErrors := []string{}
	repliedAt := time.Time{}
	slog.Info("agent wechat sending unsupported-type reply", "message_id", agentWeChatMessageID(message.MessageID), "to", message.FromUserID, "reply_len", len([]rune(replyText)))
	if err := sendAgentWeChatTextReply(ctx, *wechat, message, replyText); err != nil {
		slog.Error("agent wechat unsupported-type reply failed", "message_id", agentWeChatMessageID(message.MessageID), "to", message.FromUserID, "error", err)
		wechat.LastError = normalizeSettingText("微信非文本回复发送失败："+err.Error(), 240)
		replyErrors = append(replyErrors, err.Error())
	} else {
		slog.Info("agent wechat unsupported-type reply sent", "message_id", agentWeChatMessageID(message.MessageID), "to", message.FromUserID)
		wechat.LastOutboundAt = time.Now().UTC()
		repliedAt = wechat.LastOutboundAt
		wechat.LastError = ""
	}
	if repliedAt.IsZero() {
		repliedAt = time.Now().UTC()
	}
	if err := s.store.UpsertAgentWeChatMessage(agentWeChatMessageRecord{
		ChannelKey:  wechat.Key,
		ChannelName: wechat.DisplayName,
		Provider:    agentWeChatProviderID,
		MessageID:   agentWeChatMessageID(message.MessageID),
		SessionID:   sessionID,
		FromUserID:  message.FromUserID,
		ToUserID:    message.ToUserID,
		InboundText: inboundSummary,
		ReplyText:   replyText,
		Status:      agentRunStatus(len(replyErrors) == 0),
		Error:       strings.Join(replyErrors, "; "),
		DurationMS:  time.Since(startedAt).Milliseconds(),
		ReceivedAt:  receivedAt,
		RepliedAt:   repliedAt,
		CreatedAt:   startedAt.UTC(),
	}); err != nil {
		slog.Error("agent wechat failed to archive unsupported non-text message", "key", wechat.Key, "message_id", agentWeChatMessageID(message.MessageID), "error", err)
	}
}

func (s *adminServer) updateAgentWeChatWorkerError(key string, message string, sessionExpired bool) {
	state, err := s.store.Load()
	if err != nil || !state.Initialized {
		if err != nil {
			slog.Error("agent wechat failed to load state for worker error update", "error", err)
		}
		return
	}
	channels := state.AgentChannels.normalized()
	wechat, _, found := findAgentWeChatChannelByKey(channels, key)
	if !found {
		return
	}
	wechat = wechat.normalized()
	if !wechat.Enabled {
		return
	}
	now := time.Now().UTC()
	wechat.LastError = normalizeSettingText(message, 240)
	wechat.LastHeartbeatAt = now
	wechat.UpdatedAt = now
	if sessionExpired {
		wechat.Status = "expired"
		wechat.ProviderToken = ""
		wechat.LoginMessage = "微信会话已失效，请重新生成二维码登录"
	}
	if err := s.store.UpdateAgentWeChatRuntimeChannels([]agentWeChatChannelConfig{wechat.normalized()}); err != nil {
		slog.Error("agent wechat failed to persist worker error", "error", err)
	}
}

func agentWeChatCanPoll(channel agentWeChatChannelConfig) bool {
	channel = channel.normalized()
	return channel.Enabled && channel.Status == "bound" && strings.TrimSpace(channel.ProviderToken) != "" && strings.TrimSpace(channel.BaseURL) != ""
}

func (s *adminServer) startAgentWeChatTyping(ctx context.Context, channel agentWeChatChannelConfig, inbound agentWeChatProviderMessage) func() {
	targetUserID := strings.TrimSpace(inbound.FromUserID)
	if targetUserID == "" {
		return func() {}
	}
	configCtx, cancel := context.WithTimeout(ctx, 8*time.Second)
	config, err := fetchAgentWeChatConfig(configCtx, channel, targetUserID, inbound.ContextToken)
	cancel()
	if err != nil {
		slog.Error("agent wechat getconfig failed for typing", "message_id", agentWeChatMessageID(inbound.MessageID), "to", targetUserID, "error", err)
		return func() {}
	}
	typingTicket := strings.TrimSpace(config.TypingTicket)
	if typingTicket == "" {
		slog.Info("agent wechat typing skipped without ticket", "message_id", agentWeChatMessageID(inbound.MessageID), "to", targetUserID)
		return func() {}
	}

	send := func(parent context.Context, status int, label string) bool {
		sendCtx, cancel := context.WithTimeout(parent, 5*time.Second)
		defer cancel()
		if err := sendAgentWeChatTyping(sendCtx, channel, targetUserID, typingTicket, status); err != nil {
			slog.Error("agent wechat typing "+label+" failed", "message_id", agentWeChatMessageID(inbound.MessageID), "to", targetUserID, "status", status, "error", err)
			return false
		}
		if label == "keepalive" {
			slog.Debug("agent wechat typing keepalive sent", "message_id", agentWeChatMessageID(inbound.MessageID), "to", targetUserID, "status", status)
		} else {
			slog.Info("agent wechat typing "+label+" sent", "message_id", agentWeChatMessageID(inbound.MessageID), "to", targetUserID, "status", status)
		}
		return true
	}

	if !send(ctx, agentWeChatTypingStatus, "start") {
		return func() {}
	}
	done := make(chan struct{})
	go func() {
		ticker := time.NewTicker(agentWeChatTypingKeepalive)
		defer ticker.Stop()
		for {
			select {
			case <-ticker.C:
				send(context.Background(), agentWeChatTypingStatus, "keepalive")
			case <-done:
				return
			}
		}
	}()

	stopped := false
	return func() {
		if stopped {
			return
		}
		stopped = true
		close(done)
		send(context.Background(), agentWeChatTypingCancel, "cancel")
	}
}

func (s *adminServer) authenticatedAgentWeChatState(w http.ResponseWriter, r *http.Request) (installState, agentWeChatChannelConfig, bool) {
	state, err := s.store.Load()
	if err != nil {
		writeJSON(w, http.StatusInternalServerError, map[string]string{"error": "读取初始化状态失败"})
		return installState{}, agentWeChatChannelConfig{}, false
	}
	channels := state.AgentChannels.normalized()
	token := agentBearerToken(r)
	wechat, _, found := findAgentWeChatChannelByToken(channels, token)
	if !state.Initialized || !found || !wechat.Enabled || wechat.Token == "" {
		writeJSON(w, http.StatusUnauthorized, map[string]string{"error": "wechat agent channel not bound"})
		return installState{}, agentWeChatChannelConfig{}, false
	}
	if token == "" || len(token) != len(wechat.Token) || subtle.ConstantTimeCompare([]byte(token), []byte(wechat.Token)) != 1 {
		writeJSON(w, http.StatusUnauthorized, map[string]string{"error": "invalid bearer token"})
		return installState{}, agentWeChatChannelConfig{}, false
	}
	return state, wechat, true
}

func decodeAgentAPIRequest(r *http.Request, target any, limit int64) error {
	defer r.Body.Close()
	contentType := strings.ToLower(r.Header.Get("Content-Type"))
	if strings.Contains(contentType, "application/x-www-form-urlencoded") || strings.Contains(contentType, "multipart/form-data") {
		if err := r.ParseForm(); err != nil {
			return err
		}
		raw := map[string]any{}
		for key, values := range r.Form {
			if len(values) > 0 {
				raw[key] = values[0]
			}
		}
		payload, err := json.Marshal(raw)
		if err != nil {
			return err
		}
		return json.Unmarshal(payload, target)
	}
	body, err := io.ReadAll(io.LimitReader(r.Body, limit))
	if err != nil {
		return err
	}
	body = bytes.TrimSpace(body)
	if len(body) == 0 {
		return nil
	}
	return json.Unmarshal(body, target)
}

func agentBearerToken(r *http.Request) string {
	auth := strings.TrimSpace(r.Header.Get("Authorization"))
	if strings.HasPrefix(strings.ToLower(auth), "bearer ") {
		return strings.TrimSpace(auth[len("Bearer "):])
	}
	return strings.TrimSpace(r.URL.Query().Get("token"))
}

func agentWeChatEndpoints(base string) map[string]string {
	base = strings.TrimRight(base, "/")
	return map[string]string{
		"rest_base": base + "/api/agent",
		"bind":      base + "/api/agent/channels/wechat/bind",
		"session":   base + "/api/agent/channels/openclaw-weixin/session",
		"message":   base + "/api/agent/messages",
		"chat":      base + "/api/agent/chat",
		"me":        base + "/api/agent/me",
	}
}

func requestPublicBaseURL(r *http.Request) string {
	if r == nil {
		return ""
	}
	proto := strings.TrimSpace(r.Header.Get("X-Forwarded-Proto"))
	if proto == "" {
		proto = strings.TrimSpace(r.Header.Get("X-Scheme"))
	}
	if proto == "" {
		if r.TLS != nil {
			proto = "https"
		} else {
			proto = "http"
		}
	}
	host := strings.TrimSpace(r.Header.Get("X-Forwarded-Host"))
	if host == "" {
		host = strings.TrimSpace(r.Host)
	}
	if host == "" {
		return ""
	}
	return proto + "://" + host
}

func fetchAgentWeChatLoginQRCode(ctx context.Context, baseURL string, botType string) (agentWeChatLoginQRCode, error) {
	baseURL = normalizeAgentWeChatBaseURL(baseURL)
	if baseURL == "" {
		baseURL = agentWeChatDefaultBaseURL
	}
	botType = normalizeSettingText(botType, 16)
	if botType == "" {
		botType = agentWeChatDefaultBotType
	}
	endpoint := appendURLQuery(strings.TrimRight(baseURL, "/")+"/ilink/bot/get_bot_qrcode", url.Values{"bot_type": {botType}})
	req, err := http.NewRequestWithContext(ctx, http.MethodGet, endpoint, nil)
	if err != nil {
		return agentWeChatLoginQRCode{}, err
	}
	resp, err := agentWeChatHTTPClient.Do(req)
	if err != nil {
		return agentWeChatLoginQRCode{}, err
	}
	defer resp.Body.Close()
	if resp.StatusCode < 200 || resp.StatusCode >= 300 {
		body, _ := io.ReadAll(io.LimitReader(resp.Body, 4096))
		return agentWeChatLoginQRCode{}, fmt.Errorf("qrcode http %d: %s", resp.StatusCode, strings.TrimSpace(string(body)))
	}
	var qrResp agentWeChatLoginQRCodeResponse
	if err := json.NewDecoder(io.LimitReader(resp.Body, 128*1024)).Decode(&qrResp); err != nil {
		return agentWeChatLoginQRCode{}, fmt.Errorf("decode qrcode response: %w", err)
	}
	code := strings.TrimSpace(qrResp.Code)
	payload := strings.TrimSpace(qrResp.Payload)
	if code == "" {
		return agentWeChatLoginQRCode{}, fmt.Errorf("qrcode response missing qrcode")
	}
	if payload == "" {
		payload = code
	}
	imageURL, err := agentWeChatQRCodeDataURL(payload)
	if err != nil {
		return agentWeChatLoginQRCode{}, err
	}
	return agentWeChatLoginQRCode{
		Code:          code,
		Payload:       payload,
		ImageURL:      imageURL,
		ExpireSeconds: int(agentWeChatPairTTL.Seconds()),
		Message:       "使用微信扫描 OpenClaw Weixin 登录二维码",
	}, nil
}

func pollAgentWeChatLoginStatus(ctx context.Context, baseURL string, code string) (agentWeChatLoginStatus, error) {
	baseURL = normalizeAgentWeChatBaseURL(baseURL)
	if baseURL == "" {
		baseURL = agentWeChatDefaultBaseURL
	}
	code = normalizeAgentWeChatSession(code)
	if code == "" {
		return agentWeChatLoginStatus{}, fmt.Errorf("missing qrcode")
	}
	endpoint := appendURLQuery(strings.TrimRight(baseURL, "/")+"/ilink/bot/get_qrcode_status", url.Values{"qrcode": {code}})
	req, err := http.NewRequestWithContext(ctx, http.MethodGet, endpoint, nil)
	if err != nil {
		return agentWeChatLoginStatus{}, err
	}
	req.Header.Set("iLink-App-ClientVersion", "1")
	resp, err := agentWeChatHTTPClient.Do(req)
	if err != nil {
		return agentWeChatLoginStatus{}, err
	}
	defer resp.Body.Close()
	if resp.StatusCode < 200 || resp.StatusCode >= 300 {
		body, _ := io.ReadAll(io.LimitReader(resp.Body, 4096))
		return agentWeChatLoginStatus{}, fmt.Errorf("login status http %d: %s", resp.StatusCode, strings.TrimSpace(string(body)))
	}
	var status agentWeChatLoginStatus
	if err := json.NewDecoder(io.LimitReader(resp.Body, 128*1024)).Decode(&status); err != nil {
		return agentWeChatLoginStatus{}, fmt.Errorf("decode login status response: %w", err)
	}
	status.Status = strings.ToLower(strings.TrimSpace(status.Status))
	if status.Status == "" {
		status.Status = "wait"
	}
	return status, nil
}

func fetchAgentWeChatUpdates(ctx context.Context, channel agentWeChatChannelConfig) (agentWeChatGetUpdatesResponse, error) {
	channel = channel.normalized()
	body, err := json.Marshal(agentWeChatGetUpdatesRequest{
		GetUpdatesBuf: channel.SyncBuffer,
		BaseInfo:      agentWeChatBaseInfo(),
	})
	if err != nil {
		return agentWeChatGetUpdatesResponse{}, err
	}
	req, err := newAgentWeChatProviderRequest(ctx, channel, http.MethodPost, "/ilink/bot/getupdates", body)
	if err != nil {
		return agentWeChatGetUpdatesResponse{}, err
	}
	resp, err := agentWeChatLongPollClient.Do(req)
	if err != nil {
		if agentWeChatIsTimeout(err) {
			slog.Debug("agent wechat getupdates timeout without messages", "account", channel.AccountID)
			return agentWeChatGetUpdatesResponse{GetUpdatesBuf: channel.SyncBuffer}, nil
		}
		return agentWeChatGetUpdatesResponse{}, err
	}
	defer resp.Body.Close()
	if resp.StatusCode < 200 || resp.StatusCode >= 300 {
		body, _ := io.ReadAll(io.LimitReader(resp.Body, 4096))
		return agentWeChatGetUpdatesResponse{}, fmt.Errorf("getupdates http %d: %s", resp.StatusCode, strings.TrimSpace(string(body)))
	}
	var parsed agentWeChatGetUpdatesResponse
	if err := json.NewDecoder(io.LimitReader(resp.Body, 4*1024*1024)).Decode(&parsed); err != nil {
		return agentWeChatGetUpdatesResponse{}, fmt.Errorf("decode getupdates response: %w", err)
	}
	return parsed, nil
}

func fetchAgentWeChatConfig(ctx context.Context, channel agentWeChatChannelConfig, userID string, contextToken string) (agentWeChatGetConfigResponse, error) {
	channel = channel.normalized()
	body, err := json.Marshal(agentWeChatGetConfigRequest{
		ILinkUserID:  strings.TrimSpace(userID),
		ContextToken: strings.TrimSpace(contextToken),
		BaseInfo:     agentWeChatBaseInfo(),
	})
	if err != nil {
		return agentWeChatGetConfigResponse{}, err
	}
	req, err := newAgentWeChatProviderRequest(ctx, channel, http.MethodPost, "/ilink/bot/getconfig", body)
	if err != nil {
		return agentWeChatGetConfigResponse{}, err
	}
	resp, err := agentWeChatHTTPClient.Do(req)
	if err != nil {
		return agentWeChatGetConfigResponse{}, err
	}
	defer resp.Body.Close()
	if resp.StatusCode < 200 || resp.StatusCode >= 300 {
		body, _ := io.ReadAll(io.LimitReader(resp.Body, 4096))
		return agentWeChatGetConfigResponse{}, fmt.Errorf("getconfig http %d: %s", resp.StatusCode, strings.TrimSpace(string(body)))
	}
	var parsed agentWeChatGetConfigResponse
	if err := json.NewDecoder(io.LimitReader(resp.Body, 128*1024)).Decode(&parsed); err != nil {
		return agentWeChatGetConfigResponse{}, fmt.Errorf("decode getconfig response: %w", err)
	}
	if code := parsed.errorCode(); code != 0 {
		return agentWeChatGetConfigResponse{}, fmt.Errorf("getconfig errcode %d: %s", code, parsed.ErrMsg)
	}
	return parsed, nil
}

func sendAgentWeChatTyping(ctx context.Context, channel agentWeChatChannelConfig, userID string, typingTicket string, status int) error {
	channel = channel.normalized()
	body, err := json.Marshal(agentWeChatSendTypingRequest{
		ILinkUserID:  strings.TrimSpace(userID),
		TypingTicket: strings.TrimSpace(typingTicket),
		Status:       status,
		BaseInfo:     agentWeChatBaseInfo(),
	})
	if err != nil {
		return err
	}
	req, err := newAgentWeChatProviderRequest(ctx, channel, http.MethodPost, "/ilink/bot/sendtyping", body)
	if err != nil {
		return err
	}
	resp, err := agentWeChatHTTPClient.Do(req)
	if err != nil {
		return err
	}
	defer resp.Body.Close()
	if resp.StatusCode < 200 || resp.StatusCode >= 300 {
		body, _ := io.ReadAll(io.LimitReader(resp.Body, 4096))
		return fmt.Errorf("sendtyping http %d: %s", resp.StatusCode, strings.TrimSpace(string(body)))
	}
	var parsed agentWeChatProviderResponse
	if err := json.NewDecoder(io.LimitReader(resp.Body, 128*1024)).Decode(&parsed); err == nil {
		if code := parsed.errorCode(); code != 0 {
			return fmt.Errorf("sendtyping errcode %d: %s", code, parsed.ErrMsg)
		}
	}
	return nil
}

func sendAgentWeChatTextReply(ctx context.Context, channel agentWeChatChannelConfig, inbound agentWeChatProviderMessage, reply string) error {
	channel = channel.normalized()
	reply = truncateRunes(strings.TrimSpace(reply), agentWeChatMaxReplyRunes)
	if reply == "" {
		return nil
	}
	targetUserID := strings.TrimSpace(inbound.FromUserID)
	if targetUserID == "" {
		return fmt.Errorf("missing reply target")
	}
	clientID := newAgentWeChatClientID()
	payload := agentWeChatSendMessageRequest{
		Message: agentWeChatProviderMessage{
			FromUserID:   "",
			ToUserID:     targetUserID,
			ClientID:     clientID,
			MessageType:  2,
			MessageState: 2,
			ContextToken: strings.TrimSpace(inbound.ContextToken),
			ItemList: []agentWeChatProviderMessageItem{
				{
					Type: 1,
					TextItem: &agentWeChatProviderTextItem{
						Text: reply,
					},
				},
			},
		},
		BaseInfo: agentWeChatBaseInfo(),
	}
	body, err := json.Marshal(payload)
	if err != nil {
		return err
	}
	req, err := newAgentWeChatProviderRequest(ctx, channel, http.MethodPost, "/ilink/bot/sendmessage", body)
	if err != nil {
		return err
	}
	startedAt := time.Now()
	resp, err := agentWeChatHTTPClient.Do(req)
	if err != nil {
		return err
	}
	defer resp.Body.Close()
	if resp.StatusCode < 200 || resp.StatusCode >= 300 {
		body, _ := io.ReadAll(io.LimitReader(resp.Body, 4096))
		return fmt.Errorf("sendmessage http %d: %s", resp.StatusCode, strings.TrimSpace(string(body)))
	}
	var parsed agentWeChatProviderResponse
	if err := json.NewDecoder(io.LimitReader(resp.Body, 128*1024)).Decode(&parsed); err == nil {
		if code := parsed.errorCode(); code != 0 {
			return fmt.Errorf("sendmessage errcode %d: %s", code, parsed.ErrMsg)
		}
	}
	slog.Debug("agent wechat sendmessage text completed", "client_id", clientID, "to", targetUserID, "duration_ms", time.Since(startedAt).Milliseconds())
	return nil
}

func (s *adminServer) sendAgentWeChatFileReply(ctx context.Context, channel agentWeChatChannelConfig, inbound agentWeChatProviderMessage, file agentFileResult) error {
	slog.Debug("agent wechat resolving reply file", "file", file.Name, "url", file.URL)
	filePath, err := s.agentWeChatReplyFilePath(file)
	if err != nil {
		return err
	}
	mediaType := 3
	if agentWeChatIsImageFile(file) {
		mediaType = 1
	}
	slog.Info("agent wechat uploading reply file to provider CDN", "file", filepath.Base(filePath), "path", filePath, "media_type", mediaType)
	uploaded, err := uploadAgentWeChatFileAttachment(ctx, channel, inbound.FromUserID, filePath, mediaType)
	if err != nil {
		return err
	}
	slog.Info("agent wechat provider CDN upload completed", "file", filepath.Base(filePath), "filekey", agentWeChatShortID(uploaded.FileKey), "plain_size", uploaded.FileSize, "cipher_size", uploaded.FileSizeCiphertext, "media_type", mediaType)
	if mediaType == 1 {
		return sendAgentWeChatImageMessage(ctx, channel, inbound, filepath.Base(filePath), uploaded)
	}
	return sendAgentWeChatFileMessage(ctx, channel, inbound, filepath.Base(filePath), uploaded)
}

func (s *adminServer) agentWeChatReplyFilePath(file agentFileResult) (string, error) {
	name := filepath.Base(strings.TrimSpace(file.Name))
	if name == "." || name == "/" || name == "" {
		return "", fmt.Errorf("unsupported agent reply file %q", file.Name)
	}
	if !strings.HasPrefix(name, "moyi-agent-export-") && !strings.HasPrefix(name, "moyi-agent-image-") {
		return "", fmt.Errorf("unsupported agent reply file %q", file.Name)
	}
	if _, ok := agentExportContentType(name); !ok {
		return "", fmt.Errorf("unsupported agent reply file type %q", name)
	}
	filePath := filepath.Join(s.agentExportDir(), name)
	info, err := os.Stat(filePath)
	if err != nil {
		return "", err
	}
	if info.IsDir() {
		return "", fmt.Errorf("export file is a directory")
	}
	return filePath, nil
}

func uploadAgentWeChatFileAttachment(ctx context.Context, channel agentWeChatChannelConfig, toUserID string, filePath string, mediaType int) (agentWeChatUploadedFile, error) {
	plaintext, err := os.ReadFile(filePath)
	if err != nil {
		return agentWeChatUploadedFile{}, err
	}
	if len(plaintext) == 0 {
		return agentWeChatUploadedFile{}, fmt.Errorf("export file is empty")
	}
	fileKey := newAgentWeChatFileKey()
	aesKey := make([]byte, 16)
	if _, err := rand.Read(aesKey); err != nil {
		return agentWeChatUploadedFile{}, err
	}
	ciphertext, err := agentWeChatAESECBEncrypt(plaintext, aesKey)
	if err != nil {
		return agentWeChatUploadedFile{}, err
	}
	rawMD5 := md5.Sum(plaintext)
	aesKeyHex := hex.EncodeToString(aesKey)
	slog.Debug("agent wechat requesting upload url", "to", toUserID, "file", filepath.Base(filePath), "raw_size", len(plaintext), "cipher_size", len(ciphertext), "filekey", agentWeChatShortID(fileKey))
	uploadURL, err := getAgentWeChatUploadURL(ctx, channel, agentWeChatGetUploadURLRequest{
		FileKey:     fileKey,
		MediaType:   mediaType,
		ToUserID:    strings.TrimSpace(toUserID),
		RawSize:     len(plaintext),
		RawFileMD5:  hex.EncodeToString(rawMD5[:]),
		FileSize:    len(ciphertext),
		NoNeedThumb: true,
		AESKey:      aesKeyHex,
		BaseInfo:    agentWeChatBaseInfo(),
	})
	if err != nil {
		return agentWeChatUploadedFile{}, err
	}
	slog.Debug("agent wechat upload url received", "file", filepath.Base(filePath), "filekey", agentWeChatShortID(fileKey), "upload_param", agentWeChatShortID(uploadURL.UploadParam))
	downloadParam, err := uploadAgentWeChatCiphertextToCDN(ctx, uploadURL, fileKey, ciphertext)
	if err != nil {
		return agentWeChatUploadedFile{}, err
	}
	return agentWeChatUploadedFile{
		FileKey:            fileKey,
		DownloadParam:      downloadParam,
		AESKeyHex:          aesKeyHex,
		FileSize:           len(plaintext),
		FileSizeCiphertext: len(ciphertext),
	}, nil
}

func getAgentWeChatUploadURL(ctx context.Context, channel agentWeChatChannelConfig, payload agentWeChatGetUploadURLRequest) (agentWeChatGetUploadURLResponse, error) {
	body, err := json.Marshal(payload)
	if err != nil {
		return agentWeChatGetUploadURLResponse{}, err
	}
	req, err := newAgentWeChatProviderRequest(ctx, channel, http.MethodPost, "/ilink/bot/getuploadurl", body)
	if err != nil {
		return agentWeChatGetUploadURLResponse{}, err
	}
	startedAt := time.Now()
	resp, err := agentWeChatHTTPClient.Do(req)
	if err != nil {
		return agentWeChatGetUploadURLResponse{}, err
	}
	defer resp.Body.Close()
	if resp.StatusCode < 200 || resp.StatusCode >= 300 {
		body, _ := io.ReadAll(io.LimitReader(resp.Body, 4096))
		return agentWeChatGetUploadURLResponse{}, fmt.Errorf("getuploadurl http %d: %s", resp.StatusCode, strings.TrimSpace(string(body)))
	}
	rawBody, err := io.ReadAll(io.LimitReader(resp.Body, 512*1024))
	if err != nil {
		return agentWeChatGetUploadURLResponse{}, err
	}
	var parsed agentWeChatGetUploadURLResponse
	if err := json.Unmarshal(rawBody, &parsed); err != nil {
		return agentWeChatGetUploadURLResponse{}, fmt.Errorf("decode getuploadurl response: %w", err)
	}
	parsed = normalizeAgentWeChatUploadURLResponse(parsed, rawBody)
	if code := parsed.errorCode(); code != 0 {
		return agentWeChatGetUploadURLResponse{}, fmt.Errorf("getuploadurl errcode %d: %s", code, parsed.ErrMsg)
	}
	if strings.TrimSpace(parsed.UploadParam) == "" && strings.TrimSpace(parsed.UploadFullURL) == "" {
		raw := truncateRunes(string(rawBody), 800)
		slog.Error("agent wechat getuploadurl response missing upload_param", "to", payload.ToUserID, "filekey", agentWeChatShortID(payload.FileKey), "raw", raw)
		return agentWeChatGetUploadURLResponse{}, fmt.Errorf("getuploadurl response missing upload_param/upload_full_url: %s", raw)
	}
	slog.Debug("agent wechat getuploadurl completed", "to", payload.ToUserID, "filekey", agentWeChatShortID(payload.FileKey), "has_upload_full_url", parsed.UploadFullURL != "", "duration_ms", time.Since(startedAt).Milliseconds())
	return parsed, nil
}

func uploadAgentWeChatCiphertextToCDN(ctx context.Context, uploadURL agentWeChatGetUploadURLResponse, fileKey string, ciphertext []byte) (string, error) {
	endpoint := strings.TrimSpace(uploadURL.UploadFullURL)
	if endpoint == "" {
		cdnBase := strings.TrimRight(strings.TrimSpace(agentWeChatCDNBaseURL), "/")
		if cdnBase == "" {
			cdnBase = agentWeChatDefaultCDNURL
		}
		endpoint = appendURLQuery(cdnBase+"/upload", url.Values{
			"encrypted_query_param": {uploadURL.UploadParam},
			"filekey":               {fileKey},
		})
	}
	var lastErr error
	for attempt := 0; attempt < 3; attempt++ {
		startedAt := time.Now()
		slog.Debug("agent wechat CDN upload attempt", "attempt", attempt+1, "filekey", agentWeChatShortID(fileKey), "cipher_size", len(ciphertext))
		req, err := http.NewRequestWithContext(ctx, http.MethodPost, endpoint, bytes.NewReader(ciphertext))
		if err != nil {
			return "", err
		}
		req.Header.Set("Content-Type", "application/octet-stream")
		resp, err := agentWeChatCDNClient.Do(req)
		if err != nil {
			slog.Error("agent wechat CDN upload request failed", "attempt", attempt+1, "filekey", agentWeChatShortID(fileKey), "error", err)
			lastErr = err
			continue
		}
		downloadParam := strings.TrimSpace(resp.Header.Get("x-encrypted-param"))
		body, _ := io.ReadAll(io.LimitReader(resp.Body, 4096))
		_ = resp.Body.Close()
		if resp.StatusCode >= 400 && resp.StatusCode < 500 {
			slog.Error("agent wechat CDN upload client error", "attempt", attempt+1, "filekey", agentWeChatShortID(fileKey), "status", resp.StatusCode, "body", strings.TrimSpace(string(body)))
			return "", fmt.Errorf("cdn upload client error %d: %s", resp.StatusCode, strings.TrimSpace(string(body)))
		}
		if resp.StatusCode != http.StatusOK {
			slog.Error("agent wechat CDN upload server error", "attempt", attempt+1, "filekey", agentWeChatShortID(fileKey), "status", resp.StatusCode, "body", strings.TrimSpace(string(body)))
			lastErr = fmt.Errorf("cdn upload server error %d: %s", resp.StatusCode, strings.TrimSpace(string(body)))
			continue
		}
		if downloadParam == "" {
			slog.Error("agent wechat CDN upload missing encrypted param", "attempt", attempt+1, "filekey", agentWeChatShortID(fileKey))
			lastErr = fmt.Errorf("cdn upload response missing x-encrypted-param")
			continue
		}
		slog.Debug("agent wechat CDN upload completed", "attempt", attempt+1, "filekey", agentWeChatShortID(fileKey), "download_param", agentWeChatShortID(downloadParam), "duration_ms", time.Since(startedAt).Milliseconds())
		return downloadParam, nil
	}
	if lastErr != nil {
		return "", lastErr
	}
	return "", fmt.Errorf("cdn upload failed")
}

func sendAgentWeChatFileMessage(ctx context.Context, channel agentWeChatChannelConfig, inbound agentWeChatProviderMessage, fileName string, uploaded agentWeChatUploadedFile) error {
	channel = channel.normalized()
	targetUserID := strings.TrimSpace(inbound.FromUserID)
	if targetUserID == "" {
		return fmt.Errorf("missing file target")
	}
	clientID := newAgentWeChatClientID()
	payload := agentWeChatSendMessageRequest{
		Message: agentWeChatProviderMessage{
			FromUserID:   "",
			ToUserID:     targetUserID,
			ClientID:     clientID,
			MessageType:  2,
			MessageState: 2,
			ContextToken: strings.TrimSpace(inbound.ContextToken),
			ItemList: []agentWeChatProviderMessageItem{
				{
					Type: 4,
					FileItem: &agentWeChatProviderFileItem{
						Media: &agentWeChatProviderCDNMedia{
							EncryptQueryParam: uploaded.DownloadParam,
							AESKey:            base64.StdEncoding.EncodeToString([]byte(uploaded.AESKeyHex)),
							EncryptType:       1,
						},
						FileName: fileName,
						Len:      strconv.Itoa(uploaded.FileSize),
					},
				},
			},
		},
		BaseInfo: agentWeChatBaseInfo(),
	}
	body, err := json.Marshal(payload)
	if err != nil {
		return err
	}
	req, err := newAgentWeChatProviderRequest(ctx, channel, http.MethodPost, "/ilink/bot/sendmessage", body)
	if err != nil {
		return err
	}
	startedAt := time.Now()
	resp, err := agentWeChatHTTPClient.Do(req)
	if err != nil {
		return err
	}
	defer resp.Body.Close()
	if resp.StatusCode < 200 || resp.StatusCode >= 300 {
		body, _ := io.ReadAll(io.LimitReader(resp.Body, 4096))
		return fmt.Errorf("send file http %d: %s", resp.StatusCode, strings.TrimSpace(string(body)))
	}
	var parsed agentWeChatProviderResponse
	if err := json.NewDecoder(io.LimitReader(resp.Body, 128*1024)).Decode(&parsed); err == nil {
		if code := parsed.errorCode(); code != 0 {
			return fmt.Errorf("send file errcode %d: %s", code, parsed.ErrMsg)
		}
	}
	slog.Debug("agent wechat sendmessage file completed", "client_id", clientID, "to", targetUserID, "file", fileName, "duration_ms", time.Since(startedAt).Milliseconds())
	return nil
}

func sendAgentWeChatImageMessage(ctx context.Context, channel agentWeChatChannelConfig, inbound agentWeChatProviderMessage, fileName string, uploaded agentWeChatUploadedFile) error {
	channel = channel.normalized()
	targetUserID := strings.TrimSpace(inbound.FromUserID)
	if targetUserID == "" {
		return fmt.Errorf("missing image target")
	}
	clientID := newAgentWeChatClientID()
	payload := agentWeChatSendMessageRequest{
		Message: agentWeChatProviderMessage{
			FromUserID:   "",
			ToUserID:     targetUserID,
			ClientID:     clientID,
			MessageType:  2,
			MessageState: 2,
			ContextToken: strings.TrimSpace(inbound.ContextToken),
			ItemList: []agentWeChatProviderMessageItem{
				{
					Type: 2,
					ImageItem: &agentWeChatProviderImageItem{
						Media: &agentWeChatProviderCDNMedia{
							EncryptQueryParam: uploaded.DownloadParam,
							AESKey:            base64.StdEncoding.EncodeToString([]byte(uploaded.AESKeyHex)),
							EncryptType:       1,
						},
						AESKey:  uploaded.AESKeyHex,
						MidSize: uploaded.FileSize,
						HDSize:  uploaded.FileSize,
					},
				},
			},
		},
		BaseInfo: agentWeChatBaseInfo(),
	}
	body, err := json.Marshal(payload)
	if err != nil {
		return err
	}
	req, err := newAgentWeChatProviderRequest(ctx, channel, http.MethodPost, "/ilink/bot/sendmessage", body)
	if err != nil {
		return err
	}
	startedAt := time.Now()
	resp, err := agentWeChatHTTPClient.Do(req)
	if err != nil {
		return err
	}
	defer resp.Body.Close()
	if resp.StatusCode < 200 || resp.StatusCode >= 300 {
		body, _ := io.ReadAll(io.LimitReader(resp.Body, 4096))
		return fmt.Errorf("send image http %d: %s", resp.StatusCode, strings.TrimSpace(string(body)))
	}
	var parsed agentWeChatProviderResponse
	if err := json.NewDecoder(io.LimitReader(resp.Body, 128*1024)).Decode(&parsed); err == nil {
		if code := parsed.errorCode(); code != 0 {
			return fmt.Errorf("send image errcode %d: %s", code, parsed.ErrMsg)
		}
	}
	slog.Debug("agent wechat sendmessage image completed", "client_id", clientID, "to", targetUserID, "file", fileName, "duration_ms", time.Since(startedAt).Milliseconds())
	return nil
}

func newAgentWeChatProviderRequest(ctx context.Context, channel agentWeChatChannelConfig, method string, path string, body []byte) (*http.Request, error) {
	baseURL := normalizeAgentWeChatBaseURL(channel.BaseURL)
	if baseURL == "" {
		baseURL = agentWeChatDefaultBaseURL
	}
	endpoint := strings.TrimRight(baseURL, "/") + "/" + strings.TrimLeft(path, "/")
	req, err := http.NewRequestWithContext(ctx, method, endpoint, bytes.NewReader(body))
	if err != nil {
		return nil, err
	}
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("Content-Length", strconv.Itoa(len(body)))
	req.Header.Set("AuthorizationType", "ilink_bot_token")
	req.Header.Set("Authorization", "Bearer "+strings.TrimSpace(channel.ProviderToken))
	req.Header.Set("X-WECHAT-UIN", randomAgentWeChatUIN())
	return req, nil
}

func applyAgentWeChatLoginStatus(channel *agentWeChatChannelConfig, status agentWeChatLoginStatus, now time.Time) {
	switch strings.ToLower(strings.TrimSpace(status.Status)) {
	case "confirmed":
		channel.Status = "bound"
		channel.ProviderToken = strings.TrimSpace(status.BotToken)
		channel.AccountID = normalizeSettingText(status.AccountID, 120)
		channel.OpenClawUserID = normalizeSettingText(status.UserID, 120)
		channel.BoundUser = displayFallback(channel.OpenClawUserID, channel.AccountID)
		channel.ClientInfo = normalizeSettingText(agentWeChatProviderID+"/"+channel.AccountID, 180)
		channel.LoginQRCode = ""
		channel.LoginSession = ""
		channel.QRPayload = ""
		channel.QRImageURL = ""
		channel.BindCode = ""
		channel.BindSession = ""
		channel.BindExpiresAt = time.Time{}
		channel.BoundAt = now
		channel.LastHeartbeatAt = now
		channel.LastError = ""
		channel.LoginMessage = "与微信连接成功"
	case "scaned", "scanned":
		channel.Status = "scanned"
		channel.LoginMessage = "已扫码，等待手机端确认"
	case "expired":
		channel.Status = "expired"
		channel.LoginMessage = "二维码已过期"
	default:
		channel.Status = "waiting"
		channel.LoginMessage = "等待微信扫码"
	}
	if strings.TrimSpace(status.BaseURL) != "" {
		channel.BaseURL = normalizeAgentWeChatBaseURL(status.BaseURL)
	}
}

func agentWeChatQRCodeDataURL(payload string) (string, error) {
	payload = strings.TrimSpace(payload)
	if payload == "" {
		return "", fmt.Errorf("qrcode payload is empty")
	}
	png, err := qrcode.Encode(payload, qrcode.Medium, 256)
	if err != nil {
		return "", err
	}
	return "data:image/png;base64," + base64.StdEncoding.EncodeToString(png), nil
}

func appendURLQuery(endpoint string, values url.Values) string {
	parsed, err := url.Parse(strings.TrimSpace(endpoint))
	if err != nil {
		if strings.Contains(endpoint, "?") {
			return endpoint + "&" + values.Encode()
		}
		return endpoint + "?" + values.Encode()
	}
	query := parsed.Query()
	for key, list := range values {
		for _, value := range list {
			query.Add(key, value)
		}
	}
	parsed.RawQuery = query.Encode()
	return parsed.String()
}

func agentWeChatBaseInfo() map[string]string {
	return map[string]string{
		"channel_version": "moyi-admin-openclaw-weixin",
	}
}

func normalizeAgentWeChatUploadURLResponse(response agentWeChatGetUploadURLResponse, raw []byte) agentWeChatGetUploadURLResponse {
	if strings.TrimSpace(response.UploadParam) == "" {
		response.UploadParam = findAgentWeChatJSONString(raw, "upload_param", "uploadParam")
	}
	if strings.TrimSpace(response.UploadFullURL) == "" {
		response.UploadFullURL = findAgentWeChatJSONString(raw, "upload_full_url", "uploadFullURL")
	}
	if strings.TrimSpace(response.ThumbUploadParam) == "" {
		response.ThumbUploadParam = findAgentWeChatJSONString(raw, "thumb_upload_param", "thumbUploadParam")
	}
	return response
}

func findAgentWeChatJSONString(raw []byte, keys ...string) string {
	var decoded any
	if err := json.Unmarshal(raw, &decoded); err != nil {
		return ""
	}
	wanted := map[string]struct{}{}
	for _, key := range keys {
		wanted[key] = struct{}{}
	}
	return findAgentWeChatJSONStringValue(decoded, wanted)
}

func findAgentWeChatJSONStringValue(value any, keys map[string]struct{}) string {
	switch typed := value.(type) {
	case map[string]any:
		for key, candidate := range typed {
			if _, ok := keys[key]; ok {
				if text, ok := candidate.(string); ok && strings.TrimSpace(text) != "" {
					return strings.TrimSpace(text)
				}
			}
		}
		for _, candidate := range typed {
			if text := findAgentWeChatJSONStringValue(candidate, keys); text != "" {
				return text
			}
		}
	case []any:
		for _, candidate := range typed {
			if text := findAgentWeChatJSONStringValue(candidate, keys); text != "" {
				return text
			}
		}
	}
	return ""
}

func agentWeChatShortID(value string) string {
	value = strings.TrimSpace(value)
	if value == "" {
		return ""
	}
	if len(value) <= 16 {
		return value
	}
	return value[:8] + "..." + value[len(value)-6:]
}

func agentWeChatMessageID(value any) string {
	switch typed := value.(type) {
	case nil:
		return ""
	case string:
		return agentWeChatShortID(typed)
	case float64:
		return strconv.FormatInt(int64(typed), 10)
	case int64:
		return strconv.FormatInt(typed, 10)
	case int:
		return strconv.Itoa(typed)
	default:
		return strings.TrimSpace(fmt.Sprint(typed))
	}
}

func agentWeChatIsTimeout(err error) bool {
	type timeoutError interface {
		Timeout() bool
	}
	if timeout, ok := err.(timeoutError); ok && timeout.Timeout() {
		return true
	}
	return false
}

func randomAgentWeChatUIN() string {
	var buf [4]byte
	if _, err := rand.Read(buf[:]); err != nil {
		return base64.StdEncoding.EncodeToString([]byte(strconv.FormatInt(time.Now().UnixNano(), 10)))
	}
	return base64.StdEncoding.EncodeToString([]byte(strconv.FormatUint(uint64(binary.BigEndian.Uint32(buf[:])), 10)))
}

func newAgentWeChatClientID() string {
	var buf [8]byte
	if _, err := rand.Read(buf[:]); err != nil {
		return "moyi-weixin-" + strconv.FormatInt(time.Now().UnixNano(), 36)
	}
	return "moyi-weixin-" + hex.EncodeToString(buf[:])
}

func newAgentWeChatFileKey() string {
	var buf [16]byte
	if _, err := rand.Read(buf[:]); err != nil {
		sum := sha256.Sum256([]byte(strconv.FormatInt(time.Now().UnixNano(), 36)))
		return hex.EncodeToString(sum[:16])
	}
	return hex.EncodeToString(buf[:])
}

func agentWeChatAESECBEncrypt(plaintext []byte, key []byte) ([]byte, error) {
	block, err := aes.NewCipher(key)
	if err != nil {
		return nil, err
	}
	blockSize := block.BlockSize()
	padding := blockSize - len(plaintext)%blockSize
	padded := make([]byte, 0, len(plaintext)+padding)
	padded = append(padded, plaintext...)
	padded = append(padded, bytes.Repeat([]byte{byte(padding)}, padding)...)
	ciphertext := make([]byte, len(padded))
	for start := 0; start < len(padded); start += blockSize {
		block.Encrypt(ciphertext[start:start+blockSize], padded[start:start+blockSize])
	}
	return ciphertext, nil
}

func agentWeChatIsImageFile(file agentFileResult) bool {
	mimeType := strings.ToLower(strings.TrimSpace(file.MIME))
	if strings.HasPrefix(mimeType, "image/") {
		return true
	}
	contentType, ok := agentExportContentType(strings.TrimSpace(file.Name))
	return ok && strings.HasPrefix(strings.ToLower(contentType), "image/")
}

func agentWeChatAttachmentReplyText(files []agentFileResult) string {
	if len(files) == 0 {
		return ""
	}
	imageCount := 0
	for _, file := range files {
		if agentWeChatIsImageFile(file) {
			imageCount++
		}
	}
	switch {
	case imageCount == len(files) && len(files) == 1:
		return "图片已经生成好了，下面直接发给你。"
	case imageCount == len(files):
		return "已生成 " + strconv.Itoa(len(files)) + " 张图片，下面直接发给你。"
	case imageCount == 0 && len(files) == 1:
		return "已生成导出文件，正在通过微信附件发送：" + files[0].Name
	case imageCount == 0:
		return "已生成 " + strconv.Itoa(len(files)) + " 个导出文件，正在通过微信附件发送。"
	default:
		return "已生成 " + strconv.Itoa(len(files)) + " 个结果，下面继续通过微信发送。"
	}
}

func agentWeChatShouldSkipMessage(message agentWeChatProviderMessage) bool {
	if message.MessageType == 2 {
		return true
	}
	return false
}

func agentWeChatProviderMessageText(message agentWeChatProviderMessage) string {
	for _, item := range message.ItemList {
		if item.Type == 1 && item.TextItem != nil && strings.TrimSpace(item.TextItem.Text) != "" {
			return strings.TrimSpace(item.TextItem.Text)
		}
		if strings.TrimSpace(item.Text) != "" {
			return strings.TrimSpace(item.Text)
		}
	}
	return ""
}

func agentWeChatUnsupportedMessageReply() string {
	return "当前微信 Agent 暂不支持处理图片、语音、文件、视频等非文本消息，请发送文字内容或文字指令。"
}

func agentWeChatNonTextMessageSummary(message agentWeChatProviderMessage) string {
	labels := []string{}
	seen := map[string]struct{}{}
	for _, item := range message.ItemList {
		label := agentWeChatProviderItemTypeLabel(item)
		if _, ok := seen[label]; ok {
			continue
		}
		seen[label] = struct{}{}
		labels = append(labels, label)
	}
	if len(labels) == 0 {
		return "【非文本消息】未知类型"
	}
	return "【非文本消息】" + strings.Join(labels, "、")
}

func agentWeChatProviderItemTypeLabel(item agentWeChatProviderMessageItem) string {
	switch item.Type {
	case 1:
		return "空文本"
	case 2:
		return "图片"
	case 3:
		return "语音"
	case 4:
		return "文件"
	case 5:
		return "视频"
	default:
		return "类型 " + strconv.Itoa(item.Type)
	}
}

func truncateRunes(value string, max int) string {
	value = strings.TrimSpace(value)
	if max <= 0 {
		return value
	}
	runes := []rune(value)
	if len(runes) <= max {
		return value
	}
	return string(runes[:max])
}

func agentWeChatHasActiveQRCodeAt(channel agentWeChatChannelConfig, now time.Time) bool {
	channel = channel.normalized()
	return channel.Enabled && channel.QRImageURL != "" && !agentWeChatPairExpiredAt(channel, now)
}

func agentWeChatPairExpiredAt(channel agentWeChatChannelConfig, now time.Time) bool {
	return !channel.BindExpiresAt.IsZero() && !channel.BindExpiresAt.After(now)
}

func agentWeChatPairMatches(channel agentWeChatChannelConfig, code string, session string, requireSession bool) bool {
	channel = channel.normalized()
	if channel.BindCode == "" || code == "" || len(channel.BindCode) != len(code) || subtle.ConstantTimeCompare([]byte(channel.BindCode), []byte(code)) != 1 {
		return false
	}
	if requireSession || session != "" {
		if channel.BindSession == "" || session == "" || len(channel.BindSession) != len(session) || subtle.ConstantTimeCompare([]byte(channel.BindSession), []byte(session)) != 1 {
			return false
		}
	}
	return true
}

func agentWeChatChannelHasStoredConfig(channel agentWeChatChannelConfig) bool {
	status := strings.ToLower(strings.TrimSpace(channel.Status))
	if strings.TrimSpace(channel.Key) != "" || channel.Enabled {
		return true
	}
	if status != "" && status != "disabled" {
		return true
	}
	for _, value := range []string{
		channel.BindCode,
		channel.BindSession,
		channel.LoginQRCode,
		channel.LoginSession,
		channel.QRPayload,
		channel.QRImageURL,
		channel.LoginMessage,
		channel.ProviderToken,
		channel.AccountID,
		channel.OpenClawUserID,
		channel.SyncBuffer,
		channel.Token,
		channel.AdminUser,
		channel.BoundUser,
		channel.ClientInfo,
		channel.LastError,
	} {
		if strings.TrimSpace(value) != "" {
			return true
		}
	}
	if !channel.CreatedAt.IsZero() || !channel.UpdatedAt.IsZero() || !channel.BoundAt.IsZero() || !channel.LastMessageAt.IsZero() || !channel.LastHeartbeatAt.IsZero() || !channel.LastOutboundAt.IsZero() {
		return true
	}
	if len(normalizeAgentAllowedTables(channel.AllowedTables)) > 0 {
		return true
	}
	if normalizeAgentWeChatDataScope(channel.DataScope, channel.AllowedTables) == agentTableAccessAll {
		return true
	}
	if normalizeSettingText(channel.DisplayName, 48) != "" && normalizeSettingText(channel.DisplayName, 48) != agentWeChatDefaultName {
		return true
	}
	if normalizeAgentWeChatBaseURL(channel.BaseURL) != "" && normalizeAgentWeChatBaseURL(channel.BaseURL) != agentWeChatDefaultBaseURL {
		return true
	}
	if normalizeSettingText(channel.BotType, 16) != "" && normalizeSettingText(channel.BotType, 16) != agentWeChatDefaultBotType {
		return true
	}
	return false
}

func writeAgentWeChatBindInfoError(w http.ResponseWriter, r *http.Request, status int, message string) {
	if strings.Contains(strings.ToLower(r.Header.Get("Accept")), "application/json") {
		writeJSON(w, status, map[string]any{"ok": false, "error": message})
		return
	}
	w.Header().Set("Content-Type", "text/html; charset=utf-8")
	w.WriteHeader(status)
	_, _ = fmt.Fprintf(w, `<!doctype html><html lang="zh-CN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>微信绑定渠道</title><style>body{margin:0;background:#f3f6f5;color:#172225;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.box{max-width:420px;margin:12vh auto;padding:24px;border:1px solid #dce3e2;border-radius:8px;background:#fff}.muted{color:#647174;font-size:13px;line-height:1.7}</style></head><body><main class="box"><h1>微信绑定渠道</h1><p class="muted">%s</p></main></body></html>`, htmltemplate.HTMLEscapeString(message))
}

func agentWeChatSessionID(conversationID string, senderID string) string {
	key := firstNonEmpty(conversationID, senderID, strconv.FormatInt(time.Now().UnixNano(), 36))
	sum := sha256.Sum256([]byte(key))
	return "wechat-" + hex.EncodeToString(sum[:])[:20]
}

func newAgentWeChatBindCode() string {
	buf := make([]byte, 6)
	if _, err := rand.Read(buf); err != nil {
		return fmt.Sprintf("%06d", time.Now().UnixNano()%1000000)
	}
	var builder strings.Builder
	for _, b := range buf {
		builder.WriteByte('0' + byte(int(b)%10))
	}
	return builder.String()
}

func newAgentWeChatChannelKey() string {
	buf := make([]byte, 8)
	if _, err := rand.Read(buf); err != nil {
		sum := sha256.Sum256([]byte(strconv.FormatInt(time.Now().UnixNano(), 36)))
		return "wechat_" + hex.EncodeToString(sum[:8])
	}
	return "wechat_" + hex.EncodeToString(buf)
}

func newAgentWeChatBindSession() string {
	buf := make([]byte, 16)
	if _, err := rand.Read(buf); err != nil {
		sum := sha256.Sum256([]byte(strconv.FormatInt(time.Now().UnixNano(), 36)))
		return agentWeChatSessionPrefix + hex.EncodeToString(sum[:16])
	}
	return agentWeChatSessionPrefix + hex.EncodeToString(buf)
}

func newAgentWeChatToken() string {
	buf := make([]byte, 24)
	if _, err := rand.Read(buf); err != nil {
		sum := sha256.Sum256([]byte(strconv.FormatInt(time.Now().UnixNano(), 36)))
		return agentWeChatTokenPrefix + hex.EncodeToString(sum[:])
	}
	return agentWeChatTokenPrefix + hex.EncodeToString(buf)
}

func normalizeAgentWeChatChannelKey(value string) string {
	value = strings.ToLower(strings.TrimSpace(value))
	if value == "" {
		return ""
	}
	var builder strings.Builder
	for _, r := range value {
		if (r >= 'a' && r <= 'z') || (r >= '0' && r <= '9') || r == '_' || r == '-' {
			builder.WriteRune(r)
		}
	}
	key := builder.String()
	if len(key) > 64 {
		key = key[:64]
	}
	return key
}

func normalizeAgentWeChatBindCode(value string) string {
	value = strings.TrimSpace(value)
	var builder strings.Builder
	for _, r := range value {
		if r >= '0' && r <= '9' {
			builder.WriteRune(r)
		}
	}
	code := builder.String()
	if len(code) > 6 {
		code = code[:6]
	}
	return code
}

func normalizeAgentWeChatSession(value string) string {
	value = strings.TrimSpace(value)
	if len(value) > 128 {
		value = value[:128]
	}
	return value
}

func normalizeAgentWeChatBaseURL(value string) string {
	value = strings.TrimSpace(value)
	if value == "" {
		return ""
	}
	parsed, err := url.Parse(value)
	if err != nil || parsed.Scheme == "" || parsed.Host == "" {
		return ""
	}
	parsed.Path = strings.TrimRight(parsed.Path, "/")
	parsed.RawQuery = ""
	parsed.Fragment = ""
	return strings.TrimRight(parsed.String(), "/")
}

func maskAgentWeChatToken(token string) string {
	token = strings.TrimSpace(token)
	if token == "" {
		return "尚未生成"
	}
	if len(token) <= 12 {
		return maskSecretValue(token)
	}
	return tokenPrefix(token) + "..." + token[len(token)-6:]
}

func normalizeAgentWeChatDataScope(scope string, tables []string) string {
	scope = strings.ToLower(strings.TrimSpace(scope))
	if scope == "" {
		if len(normalizeAgentAllowedTables(tables)) > 0 {
			return agentTableAccessTables
		}
		return agentTableAccessNone
	}
	mode := normalizeAgentTableAccessMode(scope)
	if mode == agentTableAccessTables {
		return agentTableAccessTables
	}
	if mode == agentTableAccessAll {
		return agentTableAccessAll
	}
	return agentTableAccessNone
}

func agentWeChatAllowedSummary(scope string, tables []string) string {
	switch effectiveAgentTableAccessMode(normalizeAgentWeChatDataScope(scope, tables), tables) {
	case agentTableAccessNone:
		return "禁用数据查询"
	case agentTableAccessAll:
		return "全部已登记数据表"
	}
	normalized := normalizeAgentAllowedTables(tables)
	if len(normalized) == 0 {
		return "未授权任何数据表"
	}
	if len(normalized) <= 5 {
		return strings.Join(normalized, "、")
	}
	return strings.Join(normalized[:5], "、") + " 等 " + strconv.Itoa(len(normalized)) + " 张"
}

func tokenPrefix(token string) string {
	token = strings.TrimSpace(token)
	if len(token) <= 14 {
		return token
	}
	return token[:14]
}

func maskAgentWeChatSession(session string) string {
	session = strings.TrimSpace(session)
	if session == "" {
		return "尚未生成"
	}
	if len(session) <= 20 {
		return maskSecretValue(session)
	}
	return session[:14] + "..." + session[len(session)-6:]
}

func displayFallback(value string, fallback string) string {
	if strings.TrimSpace(value) == "" {
		return fallback
	}
	return strings.TrimSpace(value)
}

func formatAgentChannelTime(t time.Time, fallback string) string {
	if t.IsZero() {
		return fallback
	}
	return formatAdminTime(t)
}

func firstNonEmpty(values ...string) string {
	for _, value := range values {
		if strings.TrimSpace(value) != "" {
			return strings.TrimSpace(value)
		}
	}
	return ""
}

func redactedAgentChannelSnapshot(channels agentChannelConfig) map[string]any {
	normalized := channels.normalized()
	wechats := make([]map[string]any, 0, len(normalized.WeChats))
	for _, wechat := range normalized.WeChats {
		wechats = append(wechats, map[string]any{
			"key":              wechat.Key,
			"enabled":          wechat.Enabled,
			"status":           wechat.Status,
			"bind_code":        maskSecretValue(wechat.BindCode),
			"bind_session":     maskAgentWeChatSession(wechat.BindSession),
			"bind_expires_at":  formatStoreTime(wechat.BindExpiresAt),
			"provider":         agentWeChatProviderID,
			"base_url":         wechat.BaseURL,
			"bot_type":         wechat.BotType,
			"login_session":    maskAgentWeChatSession(wechat.LoginSession),
			"qr_payload":       maskSecretValue(wechat.QRPayload),
			"provider_token":   maskSecretValue(wechat.ProviderToken),
			"sync_buffer":      maskSecretValue(wechat.SyncBuffer),
			"token":            maskAgentWeChatToken(wechat.Token),
			"display_name":     wechat.DisplayName,
			"agent_hint":       wechat.AgentHint,
			"admin_user":       wechat.AdminUser,
			"data_scope":       wechat.DataScope,
			"allowed_tables":   normalizeAgentAllowedTables(wechat.AllowedTables),
			"account_id":       wechat.AccountID,
			"openclaw_user_id": wechat.OpenClawUserID,
			"bound_user":       wechat.BoundUser,
			"client_info":      wechat.ClientInfo,
			"last_error":       wechat.LastError,
			"bound_at":         formatStoreTime(wechat.BoundAt),
			"last_message_at":  formatStoreTime(wechat.LastMessageAt),
			"last_outbound_at": formatStoreTime(wechat.LastOutboundAt),
		})
	}
	return map[string]any{
		"wechat_count": len(wechats),
		"wechats":      wechats,
	}
}

func agentNoticeFromQuery(query url.Values) (string, string) {
	if message := strings.TrimSpace(query.Get("error")); message != "" {
		return message, "alert-danger"
	}
	switch query.Get("saved") {
	case "wechat_channel":
		return "微信绑定渠道已保存。", "alert-success"
	default:
		return "", ""
	}
}

type agentWeChatPairExchangeRequest struct {
	Code         string                `json:"code"`
	Session      string                `json:"session"`
	SessionToken string                `json:"session_token"`
	DisplayName  string                `json:"display_name"`
	AgentHint    string                `json:"agent_hint"`
	AccountID    string                `json:"account_id"`
	BotID        string                `json:"bot_id"`
	UserID       string                `json:"user_id"`
	OpenID       string                `json:"openid"`
	OpenIDAlt    string                `json:"open_id"`
	Nickname     string                `json:"nickname"`
	NickName     string                `json:"nick_name"`
	ClientInfo   agentWeChatClientInfo `json:"client_info"`
}

type agentWeChatLoginQRCode struct {
	Code          string
	Payload       string
	ImageURL      string
	ExpireSeconds int
	Message       string
}

type agentWeChatLoginQRCodeResponse struct {
	Code    string `json:"qrcode"`
	Payload string `json:"qrcode_img_content"`
}

type agentWeChatLoginStatus struct {
	Status    string `json:"status"`
	BotToken  string `json:"bot_token"`
	AccountID string `json:"ilink_bot_id"`
	BaseURL   string `json:"baseurl"`
	UserID    string `json:"ilink_user_id"`
}

type agentWeChatGetUpdatesRequest struct {
	GetUpdatesBuf string            `json:"get_updates_buf"`
	BaseInfo      map[string]string `json:"base_info,omitempty"`
}

type agentWeChatProviderResponse struct {
	Ret     int    `json:"ret"`
	ErrCode int    `json:"errcode"`
	ErrMsg  string `json:"errmsg"`
}

func (r agentWeChatProviderResponse) errorCode() int {
	if r.ErrCode != 0 {
		return r.ErrCode
	}
	return r.Ret
}

type agentWeChatGetUpdatesResponse struct {
	agentWeChatProviderResponse
	Messages             []agentWeChatProviderMessage `json:"msgs"`
	GetUpdatesBuf        string                       `json:"get_updates_buf"`
	LongPollingTimeoutMS int                          `json:"longpolling_timeout_ms"`
}

type agentWeChatGetConfigRequest struct {
	ILinkUserID  string            `json:"ilink_user_id"`
	ContextToken string            `json:"context_token,omitempty"`
	BaseInfo     map[string]string `json:"base_info,omitempty"`
}

type agentWeChatGetConfigResponse struct {
	agentWeChatProviderResponse
	TypingTicket string `json:"typing_ticket,omitempty"`
}

type agentWeChatSendTypingRequest struct {
	ILinkUserID  string            `json:"ilink_user_id"`
	TypingTicket string            `json:"typing_ticket"`
	Status       int               `json:"status"`
	BaseInfo     map[string]string `json:"base_info,omitempty"`
}

type agentWeChatGetUploadURLRequest struct {
	FileKey     string            `json:"filekey"`
	MediaType   int               `json:"media_type"`
	ToUserID    string            `json:"to_user_id"`
	RawSize     int               `json:"rawsize"`
	RawFileMD5  string            `json:"rawfilemd5"`
	FileSize    int               `json:"filesize"`
	NoNeedThumb bool              `json:"no_need_thumb"`
	AESKey      string            `json:"aeskey"`
	BaseInfo    map[string]string `json:"base_info,omitempty"`
}

type agentWeChatGetUploadURLResponse struct {
	agentWeChatProviderResponse
	UploadParam      string `json:"upload_param"`
	UploadFullURL    string `json:"upload_full_url,omitempty"`
	ThumbUploadParam string `json:"thumb_upload_param,omitempty"`
}

type agentWeChatSendMessageRequest struct {
	Message  agentWeChatProviderMessage `json:"msg"`
	BaseInfo map[string]string          `json:"base_info,omitempty"`
}

type agentWeChatProviderMessage struct {
	Seq          int64                            `json:"seq,omitempty"`
	MessageID    any                              `json:"message_id,omitempty"`
	FromUserID   string                           `json:"from_user_id,omitempty"`
	ToUserID     string                           `json:"to_user_id,omitempty"`
	ClientID     string                           `json:"client_id,omitempty"`
	CreateTimeMS int64                            `json:"create_time_ms,omitempty"`
	SessionID    string                           `json:"session_id,omitempty"`
	MessageType  int                              `json:"message_type,omitempty"`
	MessageState int                              `json:"message_state,omitempty"`
	ItemList     []agentWeChatProviderMessageItem `json:"item_list,omitempty"`
	ContextToken string                           `json:"context_token,omitempty"`
}

type agentWeChatProviderMessageItem struct {
	Type      int                           `json:"type,omitempty"`
	Text      string                        `json:"text,omitempty"`
	TextItem  *agentWeChatProviderTextItem  `json:"text_item,omitempty"`
	FileItem  *agentWeChatProviderFileItem  `json:"file_item,omitempty"`
	ImageItem *agentWeChatProviderImageItem `json:"image_item,omitempty"`
}

type agentWeChatProviderTextItem struct {
	Text string `json:"text,omitempty"`
}

type agentWeChatProviderFileItem struct {
	Media    *agentWeChatProviderCDNMedia `json:"media,omitempty"`
	FileName string                       `json:"file_name,omitempty"`
	MD5      string                       `json:"md5,omitempty"`
	Len      string                       `json:"len,omitempty"`
}

type agentWeChatProviderImageItem struct {
	Media       *agentWeChatProviderCDNMedia `json:"media,omitempty"`
	ThumbMedia  *agentWeChatProviderCDNMedia `json:"thumb_media,omitempty"`
	AESKey      string                       `json:"aeskey,omitempty"`
	MidSize     int                          `json:"mid_size,omitempty"`
	ThumbSize   int                          `json:"thumb_size,omitempty"`
	ThumbHeight int                          `json:"thumb_height,omitempty"`
	ThumbWidth  int                          `json:"thumb_width,omitempty"`
	HDSize      int                          `json:"hd_size,omitempty"`
}

type agentWeChatProviderCDNMedia struct {
	EncryptQueryParam string `json:"encrypt_query_param,omitempty"`
	AESKey            string `json:"aes_key,omitempty"`
	EncryptType       int    `json:"encrypt_type,omitempty"`
}

type agentWeChatUploadedFile struct {
	FileKey            string
	DownloadParam      string
	AESKeyHex          string
	FileSize           int
	FileSizeCiphertext int
}

type agentWeChatSessionUpdateRequest struct {
	Status        string `json:"status"`
	SessionKey    string `json:"session_key"`
	QRDataURL     string `json:"qr_data_url"`
	QRURL         string `json:"qr_url"`
	QRPayload     string `json:"qr_payload"`
	AccountID     string `json:"account_id"`
	BotID         string `json:"bot_id"`
	UserID        string `json:"user_id"`
	From          string `json:"from"`
	BaseURL       string `json:"base_url"`
	ProviderToken string `json:"provider_token"`
	DisplayName   string `json:"display_name"`
	AgentHint     string `json:"agent_hint"`
	Message       string `json:"message"`
}

type agentWeChatSessionResponse struct {
	OK              bool              `json:"ok"`
	Channel         string            `json:"channel"`
	Status          string            `json:"status"`
	AccountID       string            `json:"account_id,omitempty"`
	UserID          string            `json:"user_id,omitempty"`
	DisplayName     string            `json:"display_name,omitempty"`
	AgentHint       string            `json:"agent_hint,omitempty"`
	LastError       string            `json:"last_error,omitempty"`
	LastHeartbeatAt string            `json:"last_heartbeat_at,omitempty"`
	Endpoints       map[string]string `json:"endpoints,omitempty"`
}

type agentWeChatClientInfo struct {
	Platform string `json:"platform"`
	Version  string `json:"version"`
}

func (c agentWeChatClientInfo) String() string {
	platform := normalizeSettingText(c.Platform, 80)
	version := normalizeSettingText(c.Version, 40)
	if platform == "" {
		return ""
	}
	if version == "" {
		return platform
	}
	return platform + "/" + version
}

type agentWeChatMessageRequest struct {
	Message        string `json:"message"`
	Text           string `json:"text"`
	Body           string `json:"body"`
	CommandBody    string `json:"CommandBody"`
	CommandBodyAlt string `json:"command_body"`
	SessionID      string `json:"session_id"`
	SessionKey     string `json:"SessionKey"`
	SessionKeyAlt  string `json:"session_key"`
	ConversationID string `json:"conversation_id"`
	MessageID      string `json:"message_id"`
	MessageSid     string `json:"MessageSid"`
	MessageSidAlt  string `json:"message_sid"`
	SenderID       string `json:"sender_id"`
	SenderName     string `json:"sender_name"`
	From           string `json:"From"`
	FromAlt        string `json:"from"`
	To             string `json:"To"`
	ToAlt          string `json:"to"`
	AccountID      string `json:"AccountId"`
	AccountIDAlt   string `json:"account_id"`
}

type agentWeChatMessageResponse struct {
	OK          bool              `json:"ok"`
	Channel     string            `json:"channel,omitempty"`
	MessageID   string            `json:"message_id,omitempty"`
	SessionID   string            `json:"session_id,omitempty"`
	RunID       string            `json:"run_id,omitempty"`
	Reply       string            `json:"reply,omitempty"`
	ModelUsed   bool              `json:"model_used,omitempty"`
	Files       []agentFileResult `json:"files,omitempty"`
	ToolResults []agentToolResult `json:"tool_results,omitempty"`
	Error       string            `json:"error,omitempty"`
}

type agentWeChatMessageRecord struct {
	ArchiveKey  string
	ChannelKey  string
	ChannelName string
	Provider    string
	MessageID   string
	SessionID   string
	RunID       string
	FromUserID  string
	ToUserID    string
	InboundText string
	ReplyText   string
	Files       []agentFileResult
	Status      string
	Error       string
	ModelUsed   bool
	ToolCount   int
	FileCount   int
	DurationMS  int64
	ReceivedAt  time.Time
	RepliedAt   time.Time
	CreatedAt   time.Time
}

func (r agentWeChatMessageRecord) normalized() agentWeChatMessageRecord {
	r.ChannelKey = normalizeAgentWeChatChannelKey(r.ChannelKey)
	r.ChannelName = normalizeSettingText(r.ChannelName, 120)
	r.Provider = normalizeSettingText(firstNonEmpty(r.Provider, agentWeChatProviderID), 60)
	r.MessageID = normalizeSettingText(r.MessageID, 160)
	r.SessionID = normalizeAgentSessionID(r.SessionID)
	r.RunID = normalizeSettingText(r.RunID, 80)
	r.FromUserID = normalizeSettingText(r.FromUserID, 160)
	r.ToUserID = normalizeSettingText(r.ToUserID, 160)
	r.InboundText = truncateAuditText(strings.TrimSpace(r.InboundText), 2000)
	r.ReplyText = truncateAuditText(strings.TrimSpace(r.ReplyText), 4000)
	r.Status = normalizeSettingText(firstNonEmpty(r.Status, "ok"), 40)
	r.Error = truncateAuditText(strings.TrimSpace(r.Error), 1000)
	if r.FileCount == 0 && len(r.Files) > 0 {
		r.FileCount = len(r.Files)
	}
	if r.ReceivedAt.IsZero() {
		r.ReceivedAt = time.Now().UTC()
	}
	if r.CreatedAt.IsZero() {
		r.CreatedAt = r.ReceivedAt
	}
	if r.RepliedAt.IsZero() && (r.ReplyText != "" || len(r.Files) > 0 || r.Error != "") {
		r.RepliedAt = time.Now().UTC()
	}
	if r.ArchiveKey == "" {
		r.ArchiveKey = agentWeChatMessageArchiveKey(r.ChannelKey, r.MessageID, r.RunID, r.SessionID, r.ReceivedAt)
	}
	return r
}

func agentWeChatMessageArchiveKey(channelKey string, messageID string, runID string, sessionID string, receivedAt time.Time) string {
	channelKey = normalizeAgentWeChatChannelKey(channelKey)
	if channelKey == "" {
		channelKey = "wechat"
	}
	messageID = normalizeSettingText(messageID, 160)
	if messageID != "" {
		return channelKey + ":msg:" + messageID
	}
	runID = normalizeSettingText(runID, 80)
	if runID != "" {
		return channelKey + ":run:" + runID
	}
	sessionID = normalizeAgentSessionID(sessionID)
	if sessionID == "" {
		sessionID = "session"
	}
	if receivedAt.IsZero() {
		receivedAt = time.Now().UTC()
	}
	return channelKey + ":time:" + sessionID + ":" + receivedAt.Format("20060102150405.000000000")
}

func agentWeChatProviderMessageTime(message agentWeChatProviderMessage, fallback time.Time) time.Time {
	if message.CreateTimeMS > 0 {
		return time.UnixMilli(message.CreateTimeMS).UTC()
	}
	if fallback.IsZero() {
		return time.Now().UTC()
	}
	return fallback.UTC()
}
