(function () {
  document.querySelectorAll("[data-disclosure-toggle]").forEach((button) => {
    const targetID = button.getAttribute("data-disclosure-toggle");
    const target = targetID ? document.getElementById(targetID) : null;
    if (!target) {
      return;
    }
    const openLabel = button.getAttribute("data-open-label") || button.textContent || "展开";
    const closeLabel = button.getAttribute("data-close-label") || "收起";
    function sync(open) {
      target.hidden = !open;
      button.setAttribute("aria-expanded", open ? "true" : "false");
      button.textContent = open ? closeLabel : openLabel;
    }
    button.addEventListener("click", (event) => {
      event.preventDefault();
      sync(target.hidden);
    });
    sync(!target.hidden);
  });
})();

(function () {
  document.querySelectorAll("[data-allowed-tables-preset]").forEach((button) => {
    button.addEventListener("click", () => {
      const form = button.closest("form");
      const textarea = form ? form.querySelector('textarea[name="wechat_allowed_tables"]') : null;
      const scope = form ? form.querySelector('select[name="wechat_data_scope"]') : null;
      if (!textarea) {
        return;
      }
      textarea.value = button.getAttribute("data-allowed-tables-preset") || "";
      if (scope) {
        scope.value = "tables";
        scope.dispatchEvent(new Event("change", { bubbles: true }));
      }
      textarea.dispatchEvent(new Event("input", { bubbles: true }));
      textarea.dispatchEvent(new Event("change", { bubbles: true }));
      textarea.focus();
    });
  });

  document.querySelectorAll('form select[name="wechat_data_scope"]').forEach((scope) => {
    const form = scope.closest("form");
    const textarea = form ? form.querySelector('textarea[name="wechat_allowed_tables"]') : null;
    if (!textarea) {
      return;
    }
    function sync() {
      const useTables = scope.value === "tables";
      textarea.disabled = !useTables;
      textarea.closest("label")?.classList.toggle("is-disabled", !useTables);
    }
    scope.addEventListener("change", sync);
    sync();
  });
})();

(function () {
  const hub = document.querySelector("[data-settings-hub]");
  if (!hub) {
    return;
  }
  const tabs = Array.from(hub.querySelectorAll("[data-settings-tab]"));
  const sections = Array.from(hub.querySelectorAll("[data-settings-section]"));
  if (tabs.length === 0 || sections.length === 0) {
    return;
  }

  const savedTarget = {
    system: "system",
    storage: "storage",
    ai: "ai",
    security: "security",
    notifications: "notifications",
    notification_test: "notifications",
    task_worker: "queue",
  };

  function knownSection(key) {
    return sections.some((section) => section.getAttribute("data-settings-section") === key);
  }

  function currentSectionFromURL() {
    const hash = window.location.hash.replace(/^#settings-/, "");
    if (knownSection(hash)) {
      return hash;
    }
    const saved = new URLSearchParams(window.location.search).get("saved") || "";
    if (knownSection(savedTarget[saved])) {
      return savedTarget[saved];
    }
    return "system";
  }

  function activate(sectionKey, replaceURL) {
    if (!knownSection(sectionKey)) {
      sectionKey = "system";
    }
    tabs.forEach((tab) => {
      const active = tab.getAttribute("data-settings-tab") === sectionKey;
      tab.classList.toggle("active", active);
      tab.setAttribute("aria-pressed", active ? "true" : "false");
    });
    sections.forEach((section) => {
      const active = section.getAttribute("data-settings-section") === sectionKey;
      section.classList.toggle("is-active", active);
      section.hidden = !active;
    });
    if (replaceURL && window.history && window.location.hash !== "#settings-" + sectionKey) {
      window.history.replaceState(null, "", "#settings-" + sectionKey);
    }
  }

  tabs.forEach((tab) => {
    tab.addEventListener("click", () => activate(tab.getAttribute("data-settings-tab"), true));
  });
  window.addEventListener("hashchange", () => activate(currentSectionFromURL(), false));
  activate(currentSectionFromURL(), false);
})();

(function () {
  const dataSourceForms = Array.from(document.querySelectorAll("[data-ds-form]"));
  dataSourceForms.forEach((form) => {
    const driver = form.querySelector("[data-ds-driver]");
    const serverBlocks = Array.from(form.querySelectorAll('[data-ds-block="server"]'));
    const sqliteBlocks = Array.from(form.querySelectorAll('[data-ds-block="sqlite"]'));
    if (!driver) {
      return;
    }

    function setBlockState(blocks, visible) {
      blocks.forEach((block) => {
        block.hidden = !visible;
        block.querySelectorAll("input, select, textarea").forEach((field) => {
          field.disabled = !visible;
        });
      });
    }

    function syncDataSourceFields() {
      const isSQLite = driver.value === "sqlite";
      setBlockState(serverBlocks, !isSQLite);
      setBlockState(sqliteBlocks, isSQLite);
    }

    driver.addEventListener("change", syncDataSourceFields);
    syncDataSourceFields();
  });
})();

(function () {
  const form = document.getElementById("agentChatForm");
  const messages = document.getElementById("agentMessages");
  const input = document.getElementById("agentMessageInput");
  if (!form || !messages || !input) {
    return;
  }

  const history = [];
  const sessionStorageKey = "moyi-admin-agent-session-id";
  let sessionID = window.localStorage ? window.localStorage.getItem(sessionStorageKey) || "" : "";
  const submitButton = form.querySelector('button[type="submit"]');
  const runMode = document.getElementById("agentRunMode");
  const runGoal = document.getElementById("agentRunGoal");
  const planList = document.getElementById("agentPlanList");
  const suggestionList = document.getElementById("agentSuggestionList");

  function appendMessage(role, text, payload) {
    const toolResults = Array.isArray(payload) ? payload : payload && payload.toolResults;
    const run = payload && !Array.isArray(payload) ? payload.run : null;
    const article = document.createElement("article");
    article.className = `agent-message is-${role}`;

    const label = document.createElement("span");
    label.textContent = role === "user" ? "你" : "AI";
    article.appendChild(label);

    const paragraph = document.createElement("p");
    paragraph.textContent = text;
    article.appendChild(paragraph);

    if (run) {
      article.appendChild(renderRunSummary(run));
      updateRuntimePanel(run);
    }

    if (Array.isArray(toolResults) && toolResults.length > 0) {
      const resultBox = document.createElement("div");
      resultBox.className = "agent-tool-results";
      toolResults.forEach((result) => {
        resultBox.appendChild(renderToolResult(result));
      });
      article.appendChild(resultBox);
    }

    messages.appendChild(article);
    messages.scrollTop = messages.scrollHeight;
    return article;
  }

  function renderRunSummary(run) {
    const wrap = document.createElement("div");
    wrap.className = "agent-run-summary";

    const grid = document.createElement("div");
    grid.className = "agent-run-grid";
    grid.appendChild(renderRunBlock("执行计划", renderPlanList(run.plan || [])));
    grid.appendChild(renderRunBlock("工具轨迹", renderTraceList(run.trace || [])));
    grid.appendChild(renderRunBlock("洞察", renderInsightList(run.insights || [])));
    wrap.appendChild(grid);
    return wrap;
  }

  function renderRunBlock(title, child) {
    const block = document.createElement("section");
    block.className = "agent-run-block";
    const heading = document.createElement("h3");
    heading.textContent = title;
    block.appendChild(heading);
    block.appendChild(child);
    return block;
  }

  function renderPlanList(items) {
    const list = document.createElement("div");
    list.className = "agent-plan-list";
    if (items.length === 0) {
      list.appendChild(emptyLine("暂无计划"));
      return list;
    }
    items.forEach((item) => {
      const row = document.createElement("div");
      row.className = `agent-plan-item is-${item.status || "done"}`;
      row.appendChild(strongLine(item.title || "步骤"));
      row.appendChild(smallLine(item.detail || ""));
      list.appendChild(row);
    });
    return list;
  }

  function renderTraceList(items) {
    const list = document.createElement("div");
    list.className = "agent-trace-list";
    if (items.length === 0) {
      list.appendChild(emptyLine("暂无轨迹"));
      return list;
    }
    items.forEach((item) => {
      const row = document.createElement("div");
      row.className = `agent-trace-item is-${item.status || "done"}`;
      row.appendChild(strongLine(item.title || item.tool || "工具"));
      row.appendChild(smallLine(item.detail || ""));
      list.appendChild(row);
    });
    return list;
  }

  function renderInsightList(items) {
    const list = document.createElement("div");
    list.className = "agent-insight-list";
    if (items.length === 0) {
      list.appendChild(emptyLine("暂无洞察"));
      return list;
    }
    items.forEach((item) => {
      const row = document.createElement("div");
      row.className = `agent-insight-item is-${item.tone || "info"}`;
      row.appendChild(strongLine(item.title || "洞察"));
      row.appendChild(smallLine(item.detail || ""));
      list.appendChild(row);
    });
    return list;
  }

  function updateRuntimePanel(run) {
    if (runMode) {
      runMode.textContent = run.mode || "running";
    }
    if (runGoal) {
      runGoal.textContent = run.goal || "后台任务";
    }
    if (planList) {
      planList.replaceChildren(...Array.from(renderPlanList(run.plan || []).children));
    }
    if (suggestionList) {
      const suggestions = Array.isArray(run.suggestions) ? run.suggestions : [];
      if (suggestions.length === 0) {
        suggestionList.replaceChildren(emptyLine("暂无建议"));
      } else {
        suggestionList.replaceChildren(...suggestions.map(renderSuggestionButton));
      }
    }
  }

  function renderSuggestionButton(suggestion) {
    const button = document.createElement("button");
    button.type = "button";
    button.textContent = suggestion.label || "继续";
    button.setAttribute("data-agent-prompt", suggestion.prompt || suggestion.label || "");
    return button;
  }

  function strongLine(text) {
    const strong = document.createElement("strong");
    strong.textContent = text;
    return strong;
  }

  function smallLine(text) {
    const small = document.createElement("small");
    small.textContent = text;
    return small;
  }

  function emptyLine(text) {
    const p = document.createElement("p");
    p.textContent = text;
    return p;
  }

  function renderToolResult(result) {
    const box = document.createElement("section");
    box.className = `agent-tool-result ${result.ok ? "is-ok" : "is-error"}`;

    const head = document.createElement("div");
    head.className = "agent-tool-head";
    const name = document.createElement("strong");
    name.textContent = result.name || "tool";
    const status = document.createElement("span");
    status.textContent = result.ok ? "完成" : "拒绝";
    head.append(name, status);
    box.appendChild(head);

    const message = document.createElement("p");
    message.textContent = result.ok ? result.message || "工具调用完成" : result.error || "工具调用失败";
    box.appendChild(message);

    if (result.sql) {
      const sql = document.createElement("code");
      sql.textContent = result.sql;
      box.appendChild(sql);
    }

    if (result.file) {
      box.appendChild(renderFileResult(result.file));
    }

    if (Array.isArray(result.columns) && result.columns.length > 0 && Array.isArray(result.rows)) {
      box.appendChild(renderResultTable(result.columns, result.rows));
    }
    return box;
  }

  function renderFileResult(file) {
    const wrap = document.createElement("div");
    wrap.className = "agent-file-result";
    const link = document.createElement("a");
    link.href = file.url;
    link.textContent = file.name || "下载文件";
    link.setAttribute("download", file.name || "");
    const meta = document.createElement("small");
    const size = Number(file.size || 0);
    meta.textContent = `${file.description || "生成文件"} · ${formatBytes(size)}`;
    wrap.append(link, meta);
    return wrap;
  }

  function formatBytes(size) {
    if (!Number.isFinite(size) || size <= 0) {
      return "0 B";
    }
    if (size < 1024) {
      return `${size} B`;
    }
    if (size < 1024 * 1024) {
      return `${(size / 1024).toFixed(1)} KB`;
    }
    return `${(size / 1024 / 1024).toFixed(1)} MB`;
  }

  function renderResultTable(columns, rows) {
    const wrap = document.createElement("div");
    wrap.className = "agent-result-table-wrap";
    const table = document.createElement("table");
    table.className = "agent-result-table";

    const thead = document.createElement("thead");
    const headRow = document.createElement("tr");
    columns.forEach((column) => {
      const th = document.createElement("th");
      th.textContent = column;
      headRow.appendChild(th);
    });
    thead.appendChild(headRow);
    table.appendChild(thead);

    const tbody = document.createElement("tbody");
    rows.forEach((row) => {
      const tr = document.createElement("tr");
      columns.forEach((column) => {
        const td = document.createElement("td");
        td.textContent = row[column] || "";
        tr.appendChild(td);
      });
      tbody.appendChild(tr);
    });
    if (rows.length === 0) {
      const tr = document.createElement("tr");
      const td = document.createElement("td");
      td.colSpan = columns.length;
      td.textContent = "无数据";
      tr.appendChild(td);
      tbody.appendChild(tr);
    }
    table.appendChild(tbody);
    wrap.appendChild(table);
    return wrap;
  }

  async function sendMessage(text) {
    const message = text.trim();
    if (!message) {
      return;
    }
    appendMessage("user", message);
    history.push({ role: "user", content: message });
    input.value = "";
    setBusy(true);

    const pending = appendMessage("assistant", "正在规划任务并调用受控工具...");
    try {
      const response = await fetch(form.action, {
        method: "POST",
        credentials: "same-origin",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
        },
        body: JSON.stringify({
          session_id: sessionID,
          message,
          history: history.slice(-8),
        }),
      });
      const result = await response.json();
      pending.remove();
      if (!response.ok || !result.ok) {
        appendMessage("assistant", result.error || "智能体请求失败，请稍后再试。");
        return;
      }
      if (result.session_id) {
        sessionID = result.session_id;
        if (window.localStorage) {
          window.localStorage.setItem(sessionStorageKey, sessionID);
        }
      }
      appendMessage("assistant", result.reply || "已完成。", {
        run: result.run,
        toolResults: result.tool_results || [],
      });
      history.push({ role: "assistant", content: result.reply || "" });
    } catch (error) {
      pending.remove();
      appendMessage("assistant", `智能体请求失败：${error.message}`);
    } finally {
      setBusy(false);
      input.focus();
    }
  }

  function setBusy(isBusy) {
    if (submitButton) {
      submitButton.disabled = isBusy;
      submitButton.textContent = isBusy ? "运行中" : "运行";
    }
    input.disabled = isBusy;
  }

  form.addEventListener("submit", (event) => {
    event.preventDefault();
    sendMessage(input.value);
  });

  input.addEventListener("keydown", (event) => {
    if ((event.metaKey || event.ctrlKey) && event.key === "Enter") {
      event.preventDefault();
      form.requestSubmit();
    }
  });

  document.addEventListener("click", (event) => {
    const button = event.target.closest("[data-agent-prompt]");
    if (!button) {
      return;
    }
    sendMessage(button.getAttribute("data-agent-prompt") || "");
  });
})();
