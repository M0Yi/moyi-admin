(function () {
  "use strict";

  const form = document.getElementById("installForm");
  if (!form) {
    return;
  }

  document.documentElement.dataset.installWizard = "ready";

  const driver = form.querySelector('[data-role="db-driver"]');
  const checkButton = form.querySelector('[data-role="db-check"]');
  const checkResult = form.querySelector('[data-role="db-check-result"]');
  const help = form.querySelector('[data-role="db-help"]');
  const aiProvider = form.querySelector('[data-role="ai-provider"]');
  const aiCheckButton = form.querySelector('[data-role="ai-check"]');
  const aiCheckResult = form.querySelector('[data-role="ai-check-result"]');
  const aiHelp = form.querySelector('[data-role="ai-help"]');
  let databaseCheckOK = false;
  let aiCheckOK = false;

  const driverHelp = {
    mysql: "默认推荐。旧 Hyperf 系统默认使用 MySQL，后续迁移表结构和数据最顺。",
    postgres: "适合需要 PostgreSQL、向量扩展或更强分析能力的部署。",
    sqlite: "仅建议本地开发或快速体验使用，不建议生产环境使用。",
  };

  const aiProviderHelp = {
    bailian: "参考 gochat 的百炼接入方式，使用 DashScope / OpenAI 兼容接口作为后续智能体默认模型。",
    disabled: "暂时不连接 AI 服务，初始化完成后仍可在后台补充配置。",
  };

  function setDefaultPort() {
    const port = form.querySelector("#db_port");
    if (!port || port.value.trim() !== "") {
      return;
    }
    if (driver.value === "postgres") {
      port.value = "5432";
    }
    if (driver.value === "mysql") {
      port.value = "3306";
    }
  }

  function syncDatabaseFields() {
    const value = driver ? driver.value : "mysql";
    form.querySelectorAll("[data-db-block]").forEach((block) => {
      const type = block.getAttribute("data-db-block");
      const visible = value === "sqlite" ? type === "sqlite" : type === "server";
      block.hidden = !visible;
    });
    if (help) {
      help.textContent = driverHelp[value] || "";
    }
    if (value === "postgres") {
      const port = form.querySelector("#db_port");
      if (port && (port.value.trim() === "" || port.value === "3306")) {
        port.value = "5432";
      }
    }
    if (value === "mysql") {
      const port = form.querySelector("#db_port");
      if (port && (port.value.trim() === "" || port.value === "5432")) {
        port.value = "3306";
      }
    }
    if (value === "sqlite") {
      const filePath = form.querySelector("#db_file_path");
      if (filePath && filePath.value.trim() === "") {
        filePath.value = "data/moyi-admin.db";
      }
    }
    setResult("", "");
    databaseCheckOK = false;
  }

  function setResult(kind, html) {
    setBoxResult(checkResult, "db-check-result", kind, html);
  }

  function setAIResult(kind, html) {
    setBoxResult(aiCheckResult, "ai-check-result", kind, html);
  }

  function setBoxResult(target, baseClass, kind, html) {
    if (!target) {
      return;
    }
    target.className = baseClass;
    if (!kind) {
      target.hidden = true;
      target.innerHTML = "";
      return;
    }
    target.hidden = false;
    target.classList.add("is-" + kind);
    target.innerHTML = html;
  }

  function escapeHTML(value) {
    return String(value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function databaseFormData() {
    const data = new URLSearchParams();
    [
      "db_driver",
      "db_file_path",
      "db_host",
      "db_port",
      "db_name",
      "db_username",
      "db_password",
      "db_ssl_mode",
    ].forEach((name) => {
      const field = form.querySelector('[name="' + name + '"]');
      if (field) {
        data.append(name, field.value);
      }
    });
    return data;
  }

  function syncAIFields() {
    const value = aiProvider ? aiProvider.value : "disabled";
    form.querySelectorAll("[data-ai-block]").forEach((block) => {
      block.hidden = value !== block.getAttribute("data-ai-block");
    });
    if (aiHelp) {
      aiHelp.textContent = aiProviderHelp[value] || "";
    }
    if (value === "bailian") {
      const baseURL = form.querySelector("#ai_base_url");
      const model = form.querySelector("#ai_chat_model");
      if (baseURL && baseURL.value.trim() === "") {
        baseURL.value = "https://dashscope.aliyuncs.com/compatible-mode/v1";
      }
      if (model && model.value.trim() === "") {
        model.value = "qwen-plus";
      }
      aiCheckOK = false;
      setAIResult("", "");
      return;
    }
    aiCheckOK = true;
    setAIResult("success", "<strong>AI 暂不启用</strong><span>初始化完成后可在后台补充百炼配置。</span>");
  }

  function aiFormData() {
    const data = new URLSearchParams();
    [
      "ai_provider",
      "ai_api_key",
      "ai_base_url",
      "ai_chat_model",
    ].forEach((name) => {
      const field = form.querySelector('[name="' + name + '"]');
      if (field) {
        data.append(name, field.value);
      }
    });
    return data;
  }

  async function checkDatabase() {
    if (!checkButton) {
      return;
    }
    setResult("loading", "<strong>正在检查数据库配置...</strong><span>请稍候。</span>");
    checkButton.disabled = true;
    const originalText = checkButton.textContent;
    checkButton.textContent = "检查中...";

    try {
      const response = await fetch("/api/install/check-database", {
        method: "POST",
        body: databaseFormData(),
      });
      const result = await response.json();
      const checks = Array.isArray(result.checks)
        ? "<ul>" + result.checks.map((item) => "<li>" + escapeHTML(item) + "</li>").join("") + "</ul>"
        : "";
      if (response.ok && result.ok) {
        databaseCheckOK = true;
        setResult("success", "<strong>" + escapeHTML(result.message) + "</strong>" + checks);
      } else {
        databaseCheckOK = false;
        setResult("error", "<strong>" + escapeHTML(result.message || "数据库检查失败") + "</strong>" + checks);
      }
    } catch (error) {
      databaseCheckOK = false;
      setResult("error", "<strong>数据库检查请求失败</strong><span>" + escapeHTML(error.message) + "</span>");
    } finally {
      checkButton.disabled = false;
      checkButton.textContent = originalText;
    }
  }

  async function checkAI() {
    if (!aiCheckButton) {
      return;
    }
    setAIResult("loading", "<strong>正在检查 AI 配置...</strong><span>请稍候。</span>");
    aiCheckButton.disabled = true;
    const originalText = aiCheckButton.textContent;
    aiCheckButton.textContent = "检查中...";

    try {
      const response = await fetch("/api/install/check-ai", {
        method: "POST",
        body: aiFormData(),
      });
      const result = await response.json();
      const checks = Array.isArray(result.checks)
        ? "<ul>" + result.checks.map((item) => "<li>" + escapeHTML(item) + "</li>").join("") + "</ul>"
        : "";
      if (response.ok && result.ok) {
        aiCheckOK = true;
        setAIResult("success", "<strong>" + escapeHTML(result.message) + "</strong>" + checks);
      } else {
        aiCheckOK = false;
        setAIResult("error", "<strong>" + escapeHTML(result.message || "AI 配置检查失败") + "</strong>" + checks);
      }
    } catch (error) {
      aiCheckOK = false;
      setAIResult("error", "<strong>AI 配置检查请求失败</strong><span>" + escapeHTML(error.message) + "</span>");
    } finally {
      aiCheckButton.disabled = false;
      aiCheckButton.textContent = originalText;
    }
  }

  if (driver) {
    driver.addEventListener("change", syncDatabaseFields);
    syncDatabaseFields();
  }
  form.querySelectorAll('[name^="db_"]').forEach((field) => {
    field.addEventListener("input", () => {
      databaseCheckOK = false;
      setResult("", "");
    });
    field.addEventListener("change", () => {
      databaseCheckOK = false;
      setResult("", "");
    });
  });
  if (checkButton) {
    checkButton.addEventListener("click", checkDatabase);
  }
  if (aiProvider) {
    aiProvider.addEventListener("change", syncAIFields);
    syncAIFields();
  }
  form.querySelectorAll('[name^="ai_"]').forEach((field) => {
    field.addEventListener("input", () => {
      aiCheckOK = false;
      setAIResult("", "");
    });
    field.addEventListener("change", () => {
      if (field === aiProvider) {
        return;
      }
      aiCheckOK = false;
      setAIResult("", "");
    });
  });
  if (aiCheckButton) {
    aiCheckButton.addEventListener("click", checkAI);
  }
  form.addEventListener("submit", (event) => {
    if (!databaseCheckOK) {
      event.preventDefault();
      setResult("error", "<strong>请先检查数据库</strong><span>数据库检查通过后再完成初始化。</span>");
      return;
    }
    if (!aiCheckOK) {
      event.preventDefault();
      setAIResult("error", "<strong>请先检查 AI 配置</strong><span>AI 配置检查通过后再完成初始化；暂时没有 Key 时可选择暂不启用。</span>");
    }
  });
})();
