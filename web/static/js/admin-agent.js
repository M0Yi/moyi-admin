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
  const root = document.querySelector("[data-access-users]");
  if (!root) {
    return;
  }
  const rows = Array.from(root.querySelectorAll("[data-access-user-row]"));
  const visibleCount = root.querySelector("[data-access-visible-count]");
  const search = root.querySelector("[data-access-search]");
  const status = root.querySelector("[data-access-status]");
  const role = root.querySelector("[data-access-role]");
  const detailEmpty = root.querySelector("[data-access-detail-empty]");
  const detailBody = root.querySelector("[data-access-detail-body]");
  const detailView = root.querySelector("[data-access-detail-view]");
  const editView = root.querySelector("[data-access-edit-view]");
  const form = root.querySelector("[data-access-user-form]");
  const createToggle = root.querySelector("[data-access-create-toggle]");
  const cancelButton = root.querySelector("[data-access-cancel]");
  const editToggle = root.querySelector("[data-access-edit-toggle]");
  const sideTitle = root.querySelector("[data-access-side-title]");
  const sideMeta = root.querySelector("[data-access-side-meta]");
  const formNote = root.querySelector("[data-access-form-note]");
  const usernameHelp = root.querySelector("[data-access-username-help]");
  const passwordLabel = root.querySelector("[data-access-password-label]");
  const passwordHelp = root.querySelector("[data-access-password-help]");
  const submitButton = root.querySelector("[data-access-submit-button]");
  const detail = {
    initial: root.querySelector("[data-access-detail-initial]"),
    name: root.querySelector("[data-access-detail-name]"),
    username: root.querySelector("[data-access-detail-username]"),
    role: root.querySelector("[data-access-detail-role]"),
    roleKey: root.querySelector("[data-access-detail-role-key]"),
    status: root.querySelector("[data-access-detail-status]"),
    source: root.querySelector("[data-access-detail-source]"),
    created: root.querySelector("[data-access-detail-created]"),
    last: root.querySelector("[data-access-detail-last]"),
    protectedNote: root.querySelector("[data-access-protected-note]"),
  };
  const controls = {
    username: root.querySelector("[data-access-username-input]"),
    displayName: root.querySelector("[data-access-display-name-input]"),
    role: root.querySelector("[data-access-role-input]"),
    status: root.querySelector("[data-access-status-input]"),
    password: root.querySelector("[data-access-password-input]"),
  };
  let selectedRow = null;
  let panelMode = "detail";

  function safeText(value, fallback) {
    const text = (value || "").trim();
    return text || fallback || "-";
  }

  function setText(node, value, fallback) {
    if (node) {
      node.textContent = safeText(value, fallback);
    }
  }

  function truthy(value) {
    const text = (value || "").trim().toLowerCase();
    return text === "1" || text === "true" || text === "yes" || text === "y";
  }

  function highlightSelected() {
    rows.forEach((row) => row.classList.toggle("is-selected", row === selectedRow && panelMode !== "create"));
  }

  function fillForm(row) {
    if (!form || !row) {
      return;
    }
    if (controls.username) {
      controls.username.value = row.dataset.username || "";
    }
    if (controls.displayName) {
      controls.displayName.value = row.dataset.displayName || "";
    }
    if (controls.role) {
      controls.role.value = row.dataset.roleKey || "";
    }
    if (controls.status) {
      controls.status.value = row.dataset.status || "enabled";
    }
    if (controls.password) {
      controls.password.value = "";
    }
  }

  function syncPanelState(mode) {
    panelMode = mode;
    const createMode = mode === "create";
    const editMode = mode === "edit";
    const canEditSelected = !!selectedRow && truthy(selectedRow.dataset.canEdit);
    if (detailView) {
      detailView.hidden = createMode || editMode;
    }
    if (editView) {
      editView.hidden = mode === "detail";
    }
    if (cancelButton) {
      cancelButton.hidden = mode === "detail";
    }
    if (createToggle) {
      createToggle.textContent = createMode ? "收起新增" : "新增管理员";
      createToggle.setAttribute("aria-expanded", createMode ? "true" : "false");
    }
    if (editToggle) {
      editToggle.hidden = !canEditSelected || mode !== "detail";
    }
    if (sideTitle) {
      sideTitle.textContent = createMode ? "新增管理员" : editMode ? "编辑管理员" : "账号详情";
    }
    if (sideMeta) {
      sideMeta.textContent = createMode ? "Create" : editMode ? "Edit" : "Profile";
    }
    if (formNote) {
      formNote.textContent = createMode
        ? "新增管理员后，就可以把它分配到已有用户组，并继承对应的菜单、动作权限和 Agent 数据边界。"
        : "保存后会立即更新当前管理员的显示名称、用户组和状态；密码留空会保留原密码。";
    }
    if (usernameHelp) {
      usernameHelp.textContent = createMode
        ? "用于登录，建议使用稳定账号名；保存后会作为管理员唯一标识。"
        : "管理员账号是唯一标识，编辑已有管理员时这里会锁定，避免误改成新账号。";
    }
    if (passwordLabel) {
      passwordLabel.textContent = createMode ? "初始密码" : "重置密码";
    }
    if (passwordHelp) {
      passwordHelp.textContent = createMode
        ? "新增管理员必须设置初始密码；编辑已有管理员时，留空会保留原密码。"
        : "如需重置该管理员密码，直接填写新密码；留空则保持原密码不变。";
    }
    if (submitButton) {
      submitButton.textContent = editMode ? "保存管理员变更" : "保存管理员";
    }
    if (controls.username) {
      controls.username.readOnly = editMode;
      controls.username.setAttribute("aria-readonly", editMode ? "true" : "false");
    }
    if (createMode) {
      form?.reset();
    } else if (editMode && selectedRow) {
      fillForm(selectedRow);
    }
    highlightSelected();
  }

  function clearDetail(message) {
    selectedRow = null;
    highlightSelected();
    if (detailEmpty) {
      detailEmpty.hidden = false;
      detailEmpty.textContent = message || "选择左侧管理员查看账号详情";
    }
    if (detailBody) {
      detailBody.hidden = true;
    }
    if (detail.protectedNote) {
      detail.protectedNote.hidden = true;
    }
    if (editToggle) {
      editToggle.hidden = true;
    }
  }

  function selectRow(row) {
    if (!row || row.hidden) {
      return;
    }
    selectedRow = row;
    syncPanelState("detail");
    highlightSelected();
    setText(detail.initial, row.dataset.initial, "A");
    setText(detail.name, row.dataset.displayName, row.dataset.username || "管理员");
    setText(detail.username, row.dataset.username, "-");
    setText(detail.role, row.dataset.role, "-");
    setText(detail.roleKey, row.dataset.roleKey, "-");
    setText(detail.source, row.dataset.source, "-");
    setText(detail.created, row.dataset.created, "-");
    setText(detail.last, row.dataset.lastSeen, "-");
    if (detail.status) {
      detail.status.textContent = safeText(row.dataset.statusText, "-");
      detail.status.className = "admin-badge " + safeText(row.dataset.statusClass, "is-muted");
    }
    if (detail.protectedNote) {
      detail.protectedNote.hidden = truthy(row.dataset.canEdit);
    }
    if (detailEmpty) {
      detailEmpty.hidden = true;
    }
    if (detailBody) {
      detailBody.hidden = false;
    }
  }

  function openCreateMode() {
    syncPanelState("create");
    editView?.scrollIntoView({ behavior: "smooth", block: "start" });
    controls.username?.focus();
  }

  function openEditMode() {
    if (!selectedRow || !truthy(selectedRow.dataset.canEdit)) {
      return;
    }
    syncPanelState("edit");
    editView?.scrollIntoView({ behavior: "smooth", block: "start" });
    controls.displayName?.focus();
  }

  function applyFilters() {
    const query = (search?.value || "").trim().toLowerCase();
    const statusValue = status?.value || "";
    const roleValue = role?.value || "";
    let count = 0;
    let firstVisible = null;
    rows.forEach((row) => {
      const text = (row.dataset.filterText || "").toLowerCase();
      const matched =
        (!query || text.includes(query)) &&
        (!statusValue || row.dataset.status === statusValue) &&
        (!roleValue || row.dataset.roleKey === roleValue);
      row.hidden = !matched;
      if (matched) {
        count += 1;
        firstVisible = firstVisible || row;
      }
    });
    if (visibleCount) {
      visibleCount.textContent = String(count);
    }
    if (panelMode === "create") {
      highlightSelected();
      return;
    }
    if (selectedRow && !selectedRow.hidden) {
      if (panelMode === "edit") {
        fillForm(selectedRow);
        highlightSelected();
        return;
      }
      selectRow(selectedRow);
      return;
    }
    if (firstVisible) {
      if (panelMode === "edit") {
        syncPanelState("detail");
      }
      selectRow(firstVisible);
    } else {
      if (panelMode === "edit") {
        syncPanelState("detail");
      }
      clearDetail("没有匹配的管理员账号");
    }
  }

  rows.forEach((row) => {
    row.addEventListener("click", (event) => {
      if (event.target.closest("a, button, input, select, textarea, form")) {
        return;
      }
      selectRow(row);
    });
    row.addEventListener("keydown", (event) => {
      if (event.key === "Enter" || event.key === " ") {
        event.preventDefault();
        selectRow(row);
      }
    });
  });
  [search, status, role].forEach((control) => {
    control?.addEventListener("input", applyFilters);
    control?.addEventListener("change", applyFilters);
  });
  createToggle?.addEventListener("click", () => {
    if (panelMode === "create") {
      syncPanelState("detail");
      applyFilters();
      return;
    }
    openCreateMode();
  });
  editToggle?.addEventListener("click", () => {
    openEditMode();
  });
  cancelButton?.addEventListener("click", () => {
    syncPanelState("detail");
    applyFilters();
  });
  applyFilters();
})();

(function () {
  const root = document.querySelector("[data-access-roles]");
  if (!root) {
    return;
  }
  const rows = Array.from(root.querySelectorAll("[data-access-role-row]"));
  const visibleCount = root.querySelector("[data-access-role-visible-count]");
  const search = root.querySelector("[data-access-role-search]");
  const status = root.querySelector("[data-access-role-status]");
  const createToggle = root.querySelector("[data-access-role-create-toggle]");
  const cancelButton = root.querySelector("[data-access-role-cancel]");
  const detailView = root.querySelector("[data-access-role-detail-view]");
  const editView = root.querySelector("[data-access-role-edit-view]");
  const editToggle = root.querySelector("[data-access-role-edit-toggle]");
  const emptyState = root.querySelector("[data-access-role-empty]");
  const summaryBody = root.querySelector("[data-access-role-summary-body]");
  const panelTitle = root.querySelector("[data-access-role-panel-title]");
  const panelMeta = root.querySelector("[data-access-role-panel-meta]");
  const keyHelp = root.querySelector("[data-access-role-key-help]");
  const formNote = root.querySelector("[data-access-role-form-note]");
  const form = root.querySelector("[data-access-role-form]");
  const detail = {
    initial: root.querySelector("[data-access-role-initial]"),
    name: root.querySelector("[data-access-role-name]"),
    key: root.querySelector("[data-access-role-key-label]"),
    scope: root.querySelector("[data-access-role-scope]"),
    status: root.querySelector("[data-access-role-status-badge]"),
    menuSummary: root.querySelector("[data-access-role-menu-summary]"),
    permissionSummary: root.querySelector("[data-access-role-permission-summary]"),
    allowedSummary: root.querySelector("[data-access-role-allowed-summary]"),
    userCount: root.querySelector("[data-access-role-user-count]"),
  };
  if (!form) {
    return;
  }
  const controls = {
    key: form.querySelector("[data-access-role-key-input]"),
    name: form.querySelector("[data-access-role-name-input]"),
    status: form.querySelector("[data-access-role-status-input]"),
    scope: form.querySelector("[data-access-role-scope-input]"),
    dataScope: form.querySelector("[data-access-role-data-scope-input]"),
    description: form.querySelector("[data-access-role-description-input]"),
    menuKeys: form.querySelector("[data-access-role-menu-input]"),
    permissionKeys: form.querySelector("[data-access-role-permission-input]"),
    allowedTables: form.querySelector("[data-access-role-allowed-input]"),
  };
  const submitButton = form.querySelector("[data-access-role-submit]");
  let selectedRow = null;
  let panelMode = "detail";

  function safeText(value, fallback) {
    const text = (value || "").trim();
    return text || fallback || "";
  }

  function setText(node, value, fallback) {
    if (node) {
      node.textContent = safeText(value, fallback);
    }
  }

  function parseValues(value) {
    return (value || "")
      .split(/[\s,，;；]+/)
      .map((item) => item.trim().toLowerCase())
      .filter(Boolean);
  }

  function syncTablePickerFromValue() {
    const values = new Set(parseValues(controls.allowedTables?.value || ""));
    form.querySelectorAll("[data-agent-table-checkbox]").forEach((checkbox) => {
      checkbox.checked = values.has((checkbox.value || "").toLowerCase());
    });
  }

  function syncChecklistFromValue(holder, selector) {
    const values = new Set(parseValues(holder?.value || ""));
    form.querySelectorAll(selector).forEach((checkbox) => {
      checkbox.checked = values.has((checkbox.value || "").toLowerCase());
    });
  }

  function syncChecklistValue(holder, selector) {
    if (!holder) {
      return;
    }
    const values = Array.from(form.querySelectorAll(selector))
      .filter((checkbox) => checkbox.checked)
      .map((checkbox) => checkbox.value);
    holder.value = values.join(", ");
  }

  function syncScope() {
    controls.dataScope?.dispatchEvent(new Event("change", { bubbles: true }));
  }

  function highlightSelected() {
    rows.forEach((row) => row.classList.toggle("is-selected", row === selectedRow && panelMode !== "create"));
  }

  function syncPanelState(mode) {
    panelMode = mode || "detail";
    const createMode = panelMode === "create";
    const editMode = panelMode === "edit";
    highlightSelected();
    if (detailView) {
      detailView.hidden = createMode || editMode;
    }
    if (editView) {
      editView.hidden = !(createMode || editMode);
    }
    if (createToggle) {
      createToggle.textContent = createMode ? "收起新增" : "新增用户组";
      createToggle.setAttribute("aria-expanded", createMode ? "true" : "false");
    }
    if (cancelButton) {
      cancelButton.hidden = !(createMode || editMode);
    }
    if (panelTitle) {
      panelTitle.textContent = createMode ? "新增用户组" : editMode ? "编辑用户组" : "用户组详情";
    }
    if (panelMeta) {
      panelMeta.textContent = createMode ? "Create" : editMode ? "Editor" : "Groups";
    }
    if (editToggle) {
      editToggle.hidden = !selectedRow || createMode || editMode;
    }
    if (formNote) {
      formNote.textContent = createMode
        ? "新增用户组后，就可以在管理员账号里把账号分配到对应用户组，并继承这里配置的只读数据边界。"
        : "保存后会立即同步到已关联管理员账号的只读数据边界。";
    }
    if (submitButton) {
      submitButton.textContent = createMode ? "创建用户组" : "保存用户组权限";
    }
    if (createMode) {
      form.reset();
      if (controls.key) {
        controls.key.value = "";
        controls.key.readOnly = false;
      }
      if (controls.name) {
        controls.name.value = "";
      }
      if (controls.status) {
        controls.status.value = "enabled";
      }
      if (controls.scope) {
        controls.scope.value = "";
      }
      if (controls.dataScope) {
        controls.dataScope.value = "none";
      }
      if (controls.description) {
        controls.description.value = "";
      }
      if (controls.menuKeys) {
        controls.menuKeys.value = "";
      }
      if (controls.permissionKeys) {
        controls.permissionKeys.value = "";
      }
      if (controls.allowedTables) {
        controls.allowedTables.value = "";
      }
      if (keyHelp) {
        keyHelp.textContent = "用于唯一标识用户组，建议使用类似 finance_reader 的稳定 Key。";
      }
      syncChecklistFromValue(controls.menuKeys, "[data-access-role-menu-checkbox]");
      syncChecklistFromValue(controls.permissionKeys, "[data-access-role-permission-checkbox]");
      syncTablePickerFromValue();
      syncScope();
      if (emptyState) {
        emptyState.hidden = false;
        emptyState.textContent = "正在创建新用户组，请在右侧填写基础信息和只读数据范围。";
      }
      if (summaryBody) {
        summaryBody.hidden = true;
      }
      return;
    }
    if (editMode && selectedRow) {
      fillForm(selectedRow);
      if (keyHelp) {
        keyHelp.textContent = "已有用户组 Key 保存后保持不变，避免影响已分配账号。";
      }
    }
  }

  function fillSummary(row) {
    setText(detail.initial, row.dataset.initial, "组");
    setText(detail.name, row.dataset.name, "用户组");
    setText(detail.key, row.dataset.key, "-");
    setText(detail.scope, row.dataset.scope, "-");
    setText(detail.menuSummary, row.dataset.menuSummary, "-");
    setText(detail.permissionSummary, row.dataset.permissionSummary, "-");
    setText(detail.allowedSummary, row.dataset.allowedSummary, "-");
    setText(detail.userCount, safeText(row.dataset.userCount, "0") + " 位管理员", "-");
    if (detail.status) {
      detail.status.textContent = safeText(row.dataset.statusText, "-");
      detail.status.className = "admin-badge " + safeText(row.dataset.statusClass, "is-muted");
    }
    if (emptyState) {
      emptyState.hidden = true;
    }
    if (summaryBody) {
      summaryBody.hidden = false;
    }
  }

  function fillForm(row) {
    if (controls.key) {
      controls.key.value = safeText(row.dataset.key, "");
      controls.key.readOnly = true;
    }
    if (controls.name) {
      controls.name.value = safeText(row.dataset.name, "");
    }
    if (controls.status) {
      controls.status.value = safeText(row.dataset.status, "enabled");
    }
    if (controls.scope) {
      controls.scope.value = safeText(row.dataset.scope, "");
    }
    if (controls.dataScope) {
      controls.dataScope.value = safeText(row.dataset.dataScope, "none");
    }
    if (controls.description) {
      controls.description.value = safeText(row.dataset.description, "");
    }
    if (controls.menuKeys) {
      controls.menuKeys.value = safeText(row.dataset.menuKeys, "");
    }
    if (controls.permissionKeys) {
      controls.permissionKeys.value = safeText(row.dataset.permissionKeys, "");
    }
    if (controls.allowedTables) {
      controls.allowedTables.value = safeText(row.dataset.allowedTables, "");
    }
    if (keyHelp) {
      keyHelp.textContent = "已有用户组 Key 保存后保持不变，避免影响已分配账号。";
    }
    syncChecklistFromValue(controls.menuKeys, "[data-access-role-menu-checkbox]");
    syncChecklistFromValue(controls.permissionKeys, "[data-access-role-permission-checkbox]");
    syncTablePickerFromValue();
    syncScope();
  }

  function showEmpty(message) {
    selectedRow = null;
    syncPanelState("detail");
    highlightSelected();
    if (emptyState) {
      emptyState.hidden = false;
      emptyState.textContent = message || "选择左侧用户组查看并编辑权限配置";
    }
    if (summaryBody) {
      summaryBody.hidden = true;
    }
  }

  function selectRow(row) {
    if (!row || row.hidden) {
      return;
    }
    selectedRow = row;
    syncPanelState("detail");
    highlightSelected();
    fillSummary(row);
  }

  function openEditMode() {
    if (!selectedRow || selectedRow.hidden) {
      return;
    }
    syncPanelState("edit");
    fillSummary(selectedRow);
    fillForm(selectedRow);
  }

  function applyFilters() {
    const query = (search?.value || "").trim().toLowerCase();
    const statusValue = status?.value || "";
    let count = 0;
    let firstVisible = null;
    rows.forEach((row) => {
      const text = (row.dataset.filterText || "").toLowerCase();
      const matched = (!query || text.includes(query)) && (!statusValue || row.dataset.status === statusValue);
      row.hidden = !matched;
      if (matched) {
        count += 1;
        firstVisible = firstVisible || row;
      }
    });
    if (visibleCount) {
      visibleCount.textContent = String(count);
    }
    if (panelMode === "create") {
      return;
    }
    if (selectedRow && !selectedRow.hidden) {
      if (panelMode === "edit") {
        fillSummary(selectedRow);
        fillForm(selectedRow);
        highlightSelected();
        return;
      }
      selectRow(selectedRow);
      return;
    }
    if (firstVisible) {
      if (panelMode === "edit") {
        syncPanelState("detail");
      }
      selectRow(firstVisible);
      return;
    }
    showEmpty("没有匹配的用户组");
  }

  rows.forEach((row) => {
    row.addEventListener("click", (event) => {
      if (event.target.closest("a, button, input, select, textarea, form")) {
        return;
      }
      selectRow(row);
    });
    row.addEventListener("keydown", (event) => {
      if (event.key === "Enter" || event.key === " ") {
        event.preventDefault();
        selectRow(row);
      }
    });
  });
  [search, status].forEach((control) => {
    control?.addEventListener("input", applyFilters);
    control?.addEventListener("change", applyFilters);
  });
  createToggle?.addEventListener("click", () => {
    if (panelMode === "create") {
      syncPanelState("detail");
      applyFilters();
      return;
    }
    syncPanelState("create");
  });
  editToggle?.addEventListener("click", () => {
    openEditMode();
  });
  cancelButton?.addEventListener("click", () => {
    syncPanelState("detail");
    applyFilters();
  });
  form.querySelectorAll("[data-access-role-menu-checkbox]").forEach((checkbox) => {
    checkbox.addEventListener("change", () => {
      syncChecklistValue(controls.menuKeys, "[data-access-role-menu-checkbox]");
    });
  });
  form.querySelectorAll("[data-access-role-permission-checkbox]").forEach((checkbox) => {
    checkbox.addEventListener("change", () => {
      syncChecklistValue(controls.permissionKeys, "[data-access-role-permission-checkbox]");
    });
  });
  form.querySelectorAll("[data-access-role-menu-group-select]").forEach((button) => {
    button.addEventListener("click", () => {
      const group = button.closest(".agent-table-group");
      if (!group) {
        return;
      }
      group.querySelectorAll("[data-access-role-menu-checkbox]").forEach((checkbox) => {
        checkbox.checked = true;
      });
      syncChecklistValue(controls.menuKeys, "[data-access-role-menu-checkbox]");
    });
  });
  form.querySelectorAll("[data-access-role-permission-group-select]").forEach((button) => {
    button.addEventListener("click", () => {
      const group = button.closest(".agent-table-group");
      if (!group) {
        return;
      }
      group.querySelectorAll("[data-access-role-permission-checkbox]").forEach((checkbox) => {
        checkbox.checked = true;
      });
      syncChecklistValue(controls.permissionKeys, "[data-access-role-permission-checkbox]");
    });
  });
  applyFilters();
})();

(function () {
  const root = document.querySelector("[data-access-sessions]");
  if (!root) {
    return;
  }
  const rows = Array.from(root.querySelectorAll("[data-access-session-row]"));
  const visibleCount = root.querySelector("[data-access-session-visible-count]");
  const search = root.querySelector("[data-access-session-search]");
  const status = root.querySelector("[data-access-session-status]");
  const emptyState = root.querySelector("[data-access-session-empty]");
  const detailBody = root.querySelector("[data-access-session-body]");
  const detail = {
    initial: root.querySelector("[data-access-session-initial]"),
    id: root.querySelector("[data-access-session-id]"),
    username: root.querySelector("[data-access-session-username]"),
    ip: root.querySelector("[data-access-session-ip]"),
    agent: root.querySelector("[data-access-session-agent]"),
    created: root.querySelector("[data-access-session-created]"),
    expires: root.querySelector("[data-access-session-expires]"),
    status: root.querySelector("[data-access-session-status-badge]"),
    revoke: root.querySelector("[data-access-session-revoke]"),
    revokeID: root.querySelector("[data-access-session-revoke-id]"),
  };
  let selectedRow = null;

  function safeText(value, fallback) {
    const text = (value || "").trim();
    return text || fallback || "-";
  }

  function setText(node, value, fallback) {
    if (node) {
      node.textContent = safeText(value, fallback);
    }
  }

  function truthy(value) {
    const text = (value || "").trim().toLowerCase();
    return text === "1" || text === "true" || text === "yes" || text === "y";
  }

  function highlightSelected() {
    rows.forEach((row) => row.classList.toggle("is-selected", row === selectedRow));
  }

  function showEmpty(message) {
    selectedRow = null;
    highlightSelected();
    if (emptyState) {
      emptyState.hidden = false;
      emptyState.textContent = message || "选择左侧会话查看登录详情";
    }
    if (detailBody) {
      detailBody.hidden = true;
    }
    if (detail.revoke) {
      detail.revoke.hidden = true;
    }
    if (detail.revokeID) {
      detail.revokeID.value = "";
    }
  }

  function selectRow(row) {
    if (!row || row.hidden) {
      return;
    }
    selectedRow = row;
    highlightSelected();
    setText(detail.initial, row.dataset.initial, "S");
    setText(detail.id, row.dataset.sessionId || row.dataset.idShort, "-");
    setText(detail.username, row.dataset.username, "-");
    setText(detail.ip, row.dataset.ip, "-");
    setText(detail.agent, row.dataset.userAgent, "-");
    setText(detail.created, row.dataset.created, "-");
    setText(detail.expires, row.dataset.expires, "-");
    if (detail.status) {
      detail.status.textContent = safeText(row.dataset.statusText, "-");
      detail.status.className = "admin-badge " + safeText(row.dataset.statusClass, "is-muted");
    }
    if (detail.revoke) {
      detail.revoke.hidden = !truthy(row.dataset.canRevoke);
    }
    if (detail.revokeID) {
      detail.revokeID.value = (row.dataset.sessionId || "").trim();
    }
    if (emptyState) {
      emptyState.hidden = true;
    }
    if (detailBody) {
      detailBody.hidden = false;
    }
  }

  function applyFilters() {
    const query = (search?.value || "").trim().toLowerCase();
    const statusValue = status?.value || "";
    let count = 0;
    let firstVisible = null;
    rows.forEach((row) => {
      const text = (row.dataset.filterText || "").toLowerCase();
      const matched = (!query || text.includes(query)) && (!statusValue || row.dataset.status === statusValue);
      row.hidden = !matched;
      if (matched) {
        count += 1;
        firstVisible = firstVisible || row;
      }
    });
    if (visibleCount) {
      visibleCount.textContent = String(count);
    }
    if (selectedRow && !selectedRow.hidden) {
      selectRow(selectedRow);
      return;
    }
    if (firstVisible) {
      selectRow(firstVisible);
      return;
    }
    showEmpty("没有匹配的登录会话");
  }

  rows.forEach((row) => {
    row.addEventListener("click", (event) => {
      if (event.target.closest("a, button, input, select, textarea, form")) {
        return;
      }
      selectRow(row);
    });
    row.addEventListener("keydown", (event) => {
      if (event.key === "Enter" || event.key === " ") {
        event.preventDefault();
        selectRow(row);
      }
    });
  });
  [search, status].forEach((control) => {
    control?.addEventListener("input", applyFilters);
    control?.addEventListener("change", applyFilters);
  });
  applyFilters();
})();

(function () {
  const root = document.querySelector("[data-access-permissions]");
  if (!root) {
    return;
  }
  const rows = Array.from(root.querySelectorAll("[data-access-menu-row], [data-access-permission-row]"));
  const menuVisibleCount = root.querySelector("[data-access-menu-visible-count]");
  const permissionVisibleCount = root.querySelector("[data-access-permission-visible-count]");
  const search = root.querySelector("[data-access-permission-search]");
  const kind = root.querySelector("[data-access-permission-kind]");
  const status = root.querySelector("[data-access-permission-status]");
  const emptyState = root.querySelector("[data-access-permission-empty]");
  const detailBody = root.querySelector("[data-access-permission-body]");
  const detail = {
    panelTitle: root.querySelector("[data-access-permission-panel-title]"),
    panelMeta: root.querySelector("[data-access-permission-panel-meta]"),
    initial: root.querySelector("[data-access-permission-initial]"),
    name: root.querySelector("[data-access-permission-name]"),
    key: root.querySelector("[data-access-permission-key]"),
    subtitle: root.querySelector("[data-access-permission-subtitle]"),
    status: root.querySelector("[data-access-permission-status-badge]"),
    scopeLabel: root.querySelector("[data-access-permission-scope-label]"),
    scopeValue: root.querySelector("[data-access-permission-scope-value]"),
    boundaryLabel: root.querySelector("[data-access-permission-boundary-label]"),
    boundaryValue: root.querySelector("[data-access-permission-boundary-value]"),
  };
  let selectedRow = null;

  function safeText(value, fallback) {
    const text = (value || "").trim();
    return text || fallback || "-";
  }

  function setText(node, value, fallback) {
    if (node) {
      node.textContent = safeText(value, fallback);
    }
  }

  function rowKind(row) {
    const kindValue = (row?.dataset.kind || "").trim().toLowerCase();
    if (kindValue) {
      return kindValue;
    }
    return row?.matches("[data-access-menu-row]") ? "menu" : "permission";
  }

  function joinText(values, fallback) {
    const parts = values.map((value) => (value || "").trim()).filter(Boolean);
    return parts.length > 0 ? parts.join(" · ") : fallback || "-";
  }

  function highlightSelected() {
    rows.forEach((row) => row.classList.toggle("is-selected", row === selectedRow));
  }

  function showEmpty(message) {
    selectedRow = null;
    highlightSelected();
    if (emptyState) {
      emptyState.hidden = false;
      emptyState.textContent = message || "选择左侧菜单或权限查看访问边界";
    }
    if (detailBody) {
      detailBody.hidden = true;
    }
  }

  function fillDetail(row) {
    const menu = rowKind(row) === "menu";
    if (detail.panelTitle) {
      detail.panelTitle.textContent = menu ? "菜单详情" : "权限详情";
    }
    if (detail.panelMeta) {
      detail.panelMeta.textContent = menu ? "Menu" : "Permission";
    }
    setText(detail.initial, row.dataset.initial, menu ? "M" : "P");
    setText(detail.name, row.dataset.label || row.dataset.key, menu ? "菜单" : "权限");
    setText(detail.key, row.dataset.key, "-");
    setText(
      detail.subtitle,
      menu
        ? joinText([row.dataset.path, row.dataset.description], "")
        : joinText([row.dataset.permission, row.dataset.description], ""),
      "-"
    );
    if (detail.status) {
      detail.status.textContent = safeText(row.dataset.statusText, "-");
      detail.status.className = "admin-badge " + safeText(row.dataset.statusClass, "is-muted");
    }
    setText(detail.scopeLabel, menu ? "访问路径" : "作用对象", "-");
    setText(detail.scopeValue, menu ? row.dataset.path : row.dataset.subject, "-");
    setText(detail.boundaryLabel, menu ? "菜单说明" : "执行边界", "-");
    setText(detail.boundaryValue, menu ? row.dataset.description : row.dataset.boundary, "-");
    if (emptyState) {
      emptyState.hidden = true;
    }
    if (detailBody) {
      detailBody.hidden = false;
    }
  }

  function selectRow(row) {
    if (!row || row.hidden) {
      return;
    }
    selectedRow = row;
    highlightSelected();
    fillDetail(row);
  }

  function applyFilters() {
    const query = (search?.value || "").trim().toLowerCase();
    const kindValue = kind?.value || "";
    const statusValue = status?.value || "";
    let menuCount = 0;
    let permissionCount = 0;
    let firstVisible = null;
    rows.forEach((row) => {
      const text = (row.dataset.filterText || "").toLowerCase();
      const matched =
        (!query || text.includes(query)) &&
        (!kindValue || rowKind(row) === kindValue) &&
        (!statusValue || row.dataset.status === statusValue);
      row.hidden = !matched;
      if (matched) {
        if (rowKind(row) === "menu") {
          menuCount += 1;
        } else {
          permissionCount += 1;
        }
        firstVisible = firstVisible || row;
      }
    });
    if (menuVisibleCount) {
      menuVisibleCount.textContent = String(menuCount);
    }
    if (permissionVisibleCount) {
      permissionVisibleCount.textContent = String(permissionCount);
    }
    if (selectedRow && !selectedRow.hidden) {
      selectRow(selectedRow);
      return;
    }
    if (firstVisible) {
      selectRow(firstVisible);
      return;
    }
    showEmpty("没有匹配的菜单或权限");
  }

  rows.forEach((row) => {
    row.addEventListener("click", (event) => {
      if (event.target.closest("a, button, input, select, textarea, form")) {
        return;
      }
      selectRow(row);
    });
    row.addEventListener("keydown", (event) => {
      if (event.key === "Enter" || event.key === " ") {
        event.preventDefault();
        selectRow(row);
      }
    });
  });
  [search, kind, status].forEach((control) => {
    control?.addEventListener("input", applyFilters);
    control?.addEventListener("change", applyFilters);
  });
  applyFilters();
})();

(function () {
  const root = document.querySelector("[data-data-sources]");
  if (!root) {
    return;
  }
  const rows = Array.from(root.querySelectorAll("[data-data-source-row]"));
  const visibleCount = root.querySelector("[data-data-source-visible-count]");
  const search = root.querySelector("[data-data-source-search]");
  const driver = root.querySelector("[data-data-source-driver]");
  const status = root.querySelector("[data-data-source-status]");
  const createToggle = root.querySelector("[data-data-source-create-toggle]");
  const detailPanel = root.querySelector("[data-data-source-detail-panel]");
  const createPanel = root.querySelector("[data-data-source-create-panel]");
  const createCancel = root.querySelector("[data-data-source-create-cancel]");
  const createForm = root.querySelector("[data-ds-form]");
  const emptyState = root.querySelector("[data-data-source-empty]");
  const detailBody = root.querySelector("[data-data-source-body]");
  const detail = {
    initial: root.querySelector("[data-data-source-initial]"),
    name: root.querySelector("[data-data-source-name]"),
    target: root.querySelector("[data-data-source-target]"),
    driverText: root.querySelector("[data-data-source-driver-text]"),
    role: root.querySelector("[data-data-source-role]"),
    status: root.querySelector("[data-data-source-status-badge]"),
    lastChecked: root.querySelector("[data-data-source-last-checked]"),
    schema: root.querySelector("[data-data-source-schema]"),
    message: root.querySelector("[data-data-source-message]"),
    test: root.querySelector("[data-data-source-test]"),
    testName: root.querySelector("[data-data-source-test-name]"),
    delete: root.querySelector("[data-data-source-delete]"),
    deleteName: root.querySelector("[data-data-source-delete-name]"),
    builtin: root.querySelector("[data-data-source-builtin]"),
  };
  let selectedRow = null;

  function safeText(value, fallback) {
    const text = (value || "").trim();
    return text || fallback || "-";
  }

  function setText(node, value, fallback) {
    if (node) {
      node.textContent = safeText(value, fallback);
    }
  }

  function truthy(value) {
    const text = (value || "").trim().toLowerCase();
    return text === "1" || text === "true" || text === "yes" || text === "y";
  }

  function highlightSelected() {
    rows.forEach((row) => row.classList.toggle("is-selected", row === selectedRow));
  }

  function setCreateMode(active) {
    if (detailPanel) {
      detailPanel.hidden = active;
    }
    if (createPanel) {
      createPanel.hidden = !active;
    }
    if (createToggle) {
      createToggle.setAttribute("aria-expanded", active ? "true" : "false");
      createToggle.textContent = active ? "收起新增" : "新增数据源";
    }
    if (createCancel) {
      createCancel.hidden = !active;
    }
  }

  function openCreateMode() {
    if (createForm) {
      createForm.reset();
      const driverControl = createForm.querySelector("[data-ds-driver]");
      driverControl?.dispatchEvent(new Event("change", { bubbles: true }));
    }
    setCreateMode(true);
    createPanel?.scrollIntoView({ behavior: "smooth", block: "start" });
    createForm?.querySelector('input[name="name"]')?.focus();
  }

  function showEmpty(message) {
    selectedRow = null;
    highlightSelected();
    if (emptyState) {
      emptyState.hidden = false;
      emptyState.textContent = message || "选择左侧数据源查看连接详情";
    }
    if (detailBody) {
      detailBody.hidden = true;
    }
    if (detail.test) {
      detail.test.hidden = true;
    }
    if (detail.testName) {
      detail.testName.value = "";
    }
    if (detail.delete) {
      detail.delete.hidden = true;
    }
    if (detail.deleteName) {
      detail.deleteName.value = "";
    }
    if (detail.builtin) {
      detail.builtin.hidden = true;
    }
  }

  function selectRow(row) {
    if (!row || row.hidden) {
      return;
    }
    setCreateMode(false);
    const sourceName = (row.dataset.name || "").trim();
    const editable = truthy(row.dataset.editable);
    selectedRow = row;
    highlightSelected();
    setText(detail.initial, row.dataset.initial, "D");
    setText(detail.name, sourceName, "数据源");
    setText(detail.target, row.dataset.target, "-");
    setText(detail.driverText, row.dataset.driverText, "-");
    setText(detail.role, row.dataset.role, "-");
    setText(detail.lastChecked, row.dataset.lastChecked, "-");
    setText(detail.schema, row.dataset.schema, "-");
    setText(detail.message, row.dataset.message, "-");
    if (detail.status) {
      detail.status.textContent = safeText(row.dataset.statusText, "-");
      detail.status.className = "admin-badge " + safeText(row.dataset.statusClass, "is-muted");
    }
    if (detail.test) {
      detail.test.hidden = !editable || !sourceName;
    }
    if (detail.testName) {
      detail.testName.value = editable ? sourceName : "";
    }
    if (detail.delete) {
      detail.delete.hidden = !editable || !sourceName;
    }
    if (detail.deleteName) {
      detail.deleteName.value = sourceName;
    }
    if (detail.builtin) {
      detail.builtin.hidden = editable;
    }
    if (emptyState) {
      emptyState.hidden = true;
    }
    if (detailBody) {
      detailBody.hidden = false;
    }
  }

  function applyFilters() {
    const query = (search?.value || "").trim().toLowerCase();
    const driverValue = driver?.value || "";
    const statusValue = status?.value || "";
    let count = 0;
    let firstVisible = null;
    rows.forEach((row) => {
      const text = (row.dataset.filterText || "").toLowerCase();
      const matched =
        (!query || text.includes(query)) &&
        (!driverValue || row.dataset.driver === driverValue) &&
        (!statusValue || row.dataset.status === statusValue);
      row.hidden = !matched;
      if (matched) {
        count += 1;
        firstVisible = firstVisible || row;
      }
    });
    if (visibleCount) {
      visibleCount.textContent = String(count);
    }
    if (selectedRow && !selectedRow.hidden) {
      selectRow(selectedRow);
      return;
    }
    if (firstVisible) {
      selectRow(firstVisible);
      if (!createPanel?.hidden && rows.length > 0) {
        setCreateMode(false);
      }
      return;
    }
    showEmpty("没有匹配的数据源");
    if (rows.length === 0) {
      setCreateMode(true);
    }
  }

  rows.forEach((row) => {
    row.addEventListener("click", (event) => {
      if (event.target.closest("a, button, input, select, textarea, form")) {
        return;
      }
      selectRow(row);
    });
    row.addEventListener("keydown", (event) => {
      if (event.key === "Enter" || event.key === " ") {
        event.preventDefault();
        selectRow(row);
      }
    });
  });
  [search, driver, status].forEach((control) => {
    control?.addEventListener("input", applyFilters);
    control?.addEventListener("change", applyFilters);
  });
  createToggle?.addEventListener("click", openCreateMode);
  createCancel?.addEventListener("click", () => setCreateMode(false));
  setCreateMode(rows.length === 0);
  applyFilters();
})();

(function () {
  const root = document.querySelector("[data-file-manager]");
  if (!root) {
    return;
  }
  const rows = Array.from(root.querySelectorAll("[data-file-row]"));
  const visibleCount = root.querySelector("[data-file-visible-count]");
  const search = root.querySelector("[data-file-search]");
  const kind = root.querySelector("[data-file-kind]");
  const createToggle = root.querySelector("[data-file-create-toggle]");
  const detailPanel = root.querySelector("[data-file-detail-panel]");
  const createPanel = root.querySelector("[data-file-create-panel]");
  const createCancel = root.querySelector("[data-file-create-cancel]");
  const createForm = createPanel?.querySelector("form");
  const emptyState = root.querySelector("[data-file-empty]");
  const detailBody = root.querySelector("[data-file-body]");
  const detail = {
    initial: root.querySelector("[data-file-initial]"),
    name: root.querySelector("[data-file-name]"),
    path: root.querySelector("[data-file-path]"),
    kindText: root.querySelector("[data-file-kind-text]"),
    size: root.querySelector("[data-file-size]"),
    modified: root.querySelector("[data-file-modified]"),
    status: root.querySelector("[data-file-status-badge]"),
    preview: root.querySelector("[data-file-preview]"),
    download: root.querySelector("[data-file-download]"),
    delete: root.querySelector("[data-file-delete]"),
    deletePath: root.querySelector("[data-file-delete-path]"),
  };
  let selectedRow = null;

  function safeText(value, fallback) {
    const text = (value || "").trim();
    return text || fallback || "-";
  }

  function setText(node, value, fallback) {
    if (node) {
      node.textContent = safeText(value, fallback);
    }
  }

  function setLink(node, value) {
    if (!node) {
      return;
    }
    const href = (value || "").trim();
    node.hidden = !href;
    node.setAttribute("href", href || "#");
  }

  function highlightSelected() {
    rows.forEach((row) => row.classList.toggle("is-selected", row === selectedRow));
  }

  function setCreateMode(active) {
    if (detailPanel) {
      detailPanel.hidden = active;
    }
    if (createPanel) {
      createPanel.hidden = !active;
    }
    if (createToggle) {
      createToggle.setAttribute("aria-expanded", active ? "true" : "false");
      createToggle.textContent = active ? "收起上传" : "上传文件";
    }
    if (createCancel) {
      createCancel.hidden = !active;
    }
  }

  function openCreateMode() {
    createForm?.reset();
    setCreateMode(true);
    createPanel?.scrollIntoView({ behavior: "smooth", block: "start" });
    createForm?.querySelector('input[type="file"]')?.focus();
  }

  function showEmpty(message) {
    selectedRow = null;
    highlightSelected();
    if (emptyState) {
      emptyState.hidden = false;
      emptyState.textContent = message || "选择左侧文件查看预览和操作入口";
    }
    if (detailBody) {
      detailBody.hidden = true;
    }
    setLink(detail.preview, "");
    setLink(detail.download, "");
    if (detail.delete) {
      detail.delete.hidden = true;
    }
    if (detail.deletePath) {
      detail.deletePath.value = "";
    }
  }

  function selectRow(row) {
    if (!row || row.hidden) {
      return;
    }
    setCreateMode(false);
    const path = (row.dataset.path || "").trim();
    selectedRow = row;
    highlightSelected();
    setText(detail.initial, row.dataset.initial, "F");
    setText(detail.name, row.dataset.name, "文件");
    setText(detail.path, path, "-");
    setText(detail.kindText, row.dataset.kindText, "-");
    setText(detail.size, row.dataset.size, "-");
    setText(detail.modified, row.dataset.modified, "-");
    if (detail.status) {
      detail.status.textContent = safeText(row.dataset.statusText, "-");
      detail.status.className = "admin-badge " + safeText(row.dataset.statusClass, "is-muted");
    }
    setLink(detail.preview, row.dataset.previewUrl);
    setLink(detail.download, row.dataset.downloadUrl);
    if (detail.delete) {
      detail.delete.hidden = !path;
    }
    if (detail.deletePath) {
      detail.deletePath.value = path;
    }
    if (emptyState) {
      emptyState.hidden = true;
    }
    if (detailBody) {
      detailBody.hidden = false;
    }
  }

  function applyFilters() {
    const query = (search?.value || "").trim().toLowerCase();
    const kindValue = kind?.value || "";
    let count = 0;
    let firstVisible = null;
    rows.forEach((row) => {
      const text = (row.dataset.filterText || "").toLowerCase();
      const matched = (!query || text.includes(query)) && (!kindValue || row.dataset.kind === kindValue);
      row.hidden = !matched;
      if (matched) {
        count += 1;
        firstVisible = firstVisible || row;
      }
    });
    if (visibleCount) {
      visibleCount.textContent = String(count);
    }
    if (selectedRow && !selectedRow.hidden) {
      selectRow(selectedRow);
      return;
    }
    if (firstVisible) {
      selectRow(firstVisible);
      return;
    }
    showEmpty("没有匹配的文件");
    if (rows.length === 0) {
      setCreateMode(true);
    }
  }

  rows.forEach((row) => {
    row.addEventListener("click", (event) => {
      if (event.target.closest("a, button, input, select, textarea, form")) {
        return;
      }
      selectRow(row);
    });
    row.addEventListener("keydown", (event) => {
      if (event.key === "Enter" || event.key === " ") {
        event.preventDefault();
        selectRow(row);
      }
    });
  });
  [search, kind].forEach((control) => {
    control?.addEventListener("input", applyFilters);
    control?.addEventListener("change", applyFilters);
  });
  createToggle?.addEventListener("click", () => {
    if (!createPanel?.hidden) {
      setCreateMode(false);
      return;
    }
    openCreateMode();
  });
  createCancel?.addEventListener("click", () => setCreateMode(false));
  setCreateMode(rows.length === 0);
  applyFilters();
})();

(function () {
  const root = document.querySelector("[data-task-ops]");
  if (!root) {
    return;
  }
  const rows = Array.from(root.querySelectorAll("[data-task-row]"));
  const visibleCount = root.querySelector("[data-task-visible-count]");
  const search = root.querySelector("[data-task-search]");
  const status = root.querySelector("[data-task-status]");
  const type = root.querySelector("[data-task-type]");
  const createToggle = root.querySelector("[data-task-create-toggle]");
  const detailPanel = root.querySelector("[data-task-detail-panel]");
  const createPanel = root.querySelector("[data-task-create-panel]");
  const createCancel = root.querySelector("[data-task-create-cancel]");
  const createForm = createPanel?.querySelector("form");
  const logRows = Array.from(root.querySelectorAll("[data-task-log-row]"));
  const logVisibleCount = root.querySelector("[data-task-log-visible-count]");
  const logMeta = root.querySelector("[data-task-log-meta]");
  const emptyState = root.querySelector("[data-task-empty]");
  const detailBody = root.querySelector("[data-task-body]");
  const detail = {
    initial: root.querySelector("[data-task-initial]"),
    name: root.querySelector("[data-task-name]"),
    id: root.querySelector("[data-task-id]"),
    typeName: root.querySelector("[data-task-type-name]"),
    queue: root.querySelector("[data-task-queue]"),
    status: root.querySelector("[data-task-status-badge]"),
    attempts: root.querySelector("[data-task-attempts]"),
    createdBy: root.querySelector("[data-task-created-by]"),
    createdAt: root.querySelector("[data-task-created-at]"),
    availableAt: root.querySelector("[data-task-available-at]"),
    startedAt: root.querySelector("[data-task-started-at]"),
    finishedAt: root.querySelector("[data-task-finished-at]"),
    result: root.querySelector("[data-task-result]"),
    lastError: root.querySelector("[data-task-last-error]"),
    run: root.querySelector("[data-task-run]"),
    runID: root.querySelector("[data-task-run-id]"),
    retry: root.querySelector("[data-task-retry]"),
    retryID: root.querySelector("[data-task-retry-id]"),
    cancel: root.querySelector("[data-task-cancel]"),
    cancelID: root.querySelector("[data-task-cancel-id]"),
    noAction: root.querySelector("[data-task-no-action]"),
  };
  let selectedRow = null;

  function safeText(value, fallback) {
    const text = (value || "").trim();
    return text || fallback || "-";
  }

  function setText(node, value, fallback) {
    if (node) {
      node.textContent = safeText(value, fallback);
    }
  }

  function truthy(value) {
    const text = (value || "").trim().toLowerCase();
    return text === "1" || text === "true" || text === "yes" || text === "y";
  }

  function highlightSelected() {
    rows.forEach((row) => row.classList.toggle("is-selected", row === selectedRow));
  }

  function setCreateMode(active) {
    if (detailPanel) {
      detailPanel.hidden = active;
    }
    if (createPanel) {
      createPanel.hidden = !active;
    }
    if (createToggle) {
      createToggle.setAttribute("aria-expanded", active ? "true" : "false");
      createToggle.textContent = active ? "收起创建" : "创建任务";
    }
    if (createCancel) {
      createCancel.hidden = !active;
    }
  }

  function openCreateMode() {
    createForm?.reset();
    setCreateMode(true);
    createPanel?.scrollIntoView({ behavior: "smooth", block: "start" });
    createForm?.querySelector('select[name="task_type"]')?.focus();
  }

  function syncAction(form, input, visible, taskID) {
    if (form) {
      form.hidden = !visible;
    }
    if (input) {
      input.value = visible ? taskID : "";
    }
  }

  function syncLogs() {
    const query = (search?.value || "").trim().toLowerCase();
    const taskID = (selectedRow?.dataset.id || "").trim();
    const taskLabel = (selectedRow?.dataset.idShort || selectedRow?.dataset.name || "").trim();
    let visible = 0;
    logRows.forEach((row) => {
      const text = (row.dataset.filterText || "").toLowerCase();
      const matchesSearch = !query || text.includes(query);
      const matchesTask = !taskID || row.dataset.taskId === taskID;
      const matched = matchesSearch && matchesTask;
      row.hidden = !matched;
      row.classList.toggle("is-selected", matched && !!taskID && row.dataset.taskId === taskID);
      if (matched) {
        visible += 1;
      }
    });
    if (logVisibleCount) {
      logVisibleCount.textContent = String(visible);
    }
    if (logMeta) {
      logMeta.textContent = taskID ? (taskLabel ? "Lifecycle Logs · " + taskLabel : "Lifecycle Logs") : "Lifecycle Logs";
    }
  }

  function showEmpty(message) {
    selectedRow = null;
    highlightSelected();
    if (emptyState) {
      emptyState.hidden = false;
      emptyState.textContent = message || "选择左侧任务查看状态与执行入口";
    }
    if (detailBody) {
      detailBody.hidden = true;
    }
    syncAction(detail.run, detail.runID, false, "");
    syncAction(detail.retry, detail.retryID, false, "");
    syncAction(detail.cancel, detail.cancelID, false, "");
    if (detail.noAction) {
      detail.noAction.hidden = true;
    }
    syncLogs();
  }

  function selectRow(row) {
    if (!row || row.hidden) {
      return;
    }
    setCreateMode(false);
    const taskID = (row.dataset.id || "").trim();
    const canRun = truthy(row.dataset.canRun);
    const canRetry = truthy(row.dataset.canRetry);
    const canCancel = truthy(row.dataset.canCancel);
    const hasAction = !!taskID && (canRun || canRetry || canCancel);
    selectedRow = row;
    highlightSelected();
    setText(detail.initial, row.dataset.initial, "T");
    setText(detail.name, row.dataset.name, "任务");
    setText(detail.id, taskID || row.dataset.idShort, "-");
    setText(detail.typeName, row.dataset.typeName, "-");
    setText(detail.queue, row.dataset.queue, "-");
    setText(detail.attempts, row.dataset.attempts, "-");
    setText(detail.createdBy, row.dataset.createdBy, "-");
    setText(detail.createdAt, row.dataset.createdAt, "-");
    setText(detail.availableAt, row.dataset.availableAt, "-");
    setText(detail.startedAt, row.dataset.startedAt, "-");
    setText(detail.finishedAt, row.dataset.finishedAt, "-");
    setText(detail.result, row.dataset.result, "等待执行结果");
    setText(detail.lastError, row.dataset.lastError, "暂无错误");
    if (detail.status) {
      detail.status.textContent = safeText(row.dataset.statusText, "-");
      detail.status.className = "admin-badge " + safeText(row.dataset.statusClass, "is-muted");
    }
    syncAction(detail.run, detail.runID, canRun && !!taskID, taskID);
    syncAction(detail.retry, detail.retryID, canRetry && !!taskID, taskID);
    syncAction(detail.cancel, detail.cancelID, canCancel && !!taskID, taskID);
    if (detail.noAction) {
      detail.noAction.hidden = hasAction;
    }
    if (emptyState) {
      emptyState.hidden = true;
    }
    if (detailBody) {
      detailBody.hidden = false;
    }
    syncLogs();
  }

  function applyFilters() {
    const query = (search?.value || "").trim().toLowerCase();
    const statusValue = status?.value || "";
    const typeValue = type?.value || "";
    let count = 0;
    let firstVisible = null;
    rows.forEach((row) => {
      const text = (row.dataset.filterText || "").toLowerCase();
      const matched =
        (!query || text.includes(query)) &&
        (!statusValue || row.dataset.status === statusValue) &&
        (!typeValue || row.dataset.type === typeValue);
      row.hidden = !matched;
      if (matched) {
        count += 1;
        firstVisible = firstVisible || row;
      }
    });
    if (visibleCount) {
      visibleCount.textContent = String(count);
    }
    if (selectedRow && !selectedRow.hidden) {
      selectRow(selectedRow);
      return;
    }
    if (firstVisible) {
      selectRow(firstVisible);
      return;
    }
    showEmpty("没有匹配的后台任务");
    if (rows.length === 0) {
      setCreateMode(true);
    }
  }

  rows.forEach((row) => {
    row.addEventListener("click", (event) => {
      if (event.target.closest("a, button, input, select, textarea, form")) {
        return;
      }
      selectRow(row);
    });
    row.addEventListener("keydown", (event) => {
      if (event.key === "Enter" || event.key === " ") {
        event.preventDefault();
        selectRow(row);
      }
    });
  });
  [search, status, type].forEach((control) => {
    control?.addEventListener("input", applyFilters);
    control?.addEventListener("change", applyFilters);
  });
  createToggle?.addEventListener("click", () => {
    if (!createPanel?.hidden) {
      setCreateMode(false);
      return;
    }
    openCreateMode();
  });
  createCancel?.addEventListener("click", () => setCreateMode(false));
  setCreateMode(rows.length === 0);
  applyFilters();
})();

(function () {
  function parseTableValues(value) {
    return (value || "")
      .split(/[\s,，;；]+/)
      .map((item) => item.trim().toLowerCase())
      .filter(Boolean);
  }

  function syncPickerFromValue(form) {
    const holder = form.querySelector('[data-agent-table-values], textarea[name$="_allowed_tables"]');
    const values = new Set(parseTableValues(holder ? holder.value : ""));
    form.querySelectorAll("[data-agent-table-checkbox]").forEach((checkbox) => {
      checkbox.checked = values.has((checkbox.value || "").toLowerCase());
    });
  }

  function syncValueFromPicker(form) {
    const holder = form.querySelector('[data-agent-table-values], textarea[name$="_allowed_tables"]');
    if (!holder) {
      return;
    }
    const values = Array.from(form.querySelectorAll("[data-agent-table-checkbox]:checked")).map((checkbox) => checkbox.value);
    holder.value = values.join(", ");
    holder.dispatchEvent(new Event("input", { bubbles: true }));
    holder.dispatchEvent(new Event("change", { bubbles: true }));
  }

  document.querySelectorAll("[data-allowed-tables-preset]").forEach((button) => {
    button.addEventListener("click", () => {
      const form = button.closest("form");
      const textarea = form ? form.querySelector('[data-agent-table-values], textarea[name$="_allowed_tables"]') : null;
      const scope = form ? form.querySelector('select[name$="_data_scope"]') : null;
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
      if (form) {
        syncPickerFromValue(form);
      }
      if (textarea.type !== "hidden") {
        textarea.focus();
      }
    });
  });

  document.querySelectorAll("[data-agent-table-picker]").forEach((picker) => {
    const form = picker.closest("form");
    if (!form) {
      return;
    }
    syncPickerFromValue(form);
    picker.querySelectorAll("[data-agent-table-checkbox]").forEach((checkbox) => {
      checkbox.addEventListener("change", () => {
        const scopeControl = form.querySelector('select[name$="_data_scope"]');
        if (scopeControl && scopeControl.value !== "tables") {
          scopeControl.value = "tables";
          scopeControl.dispatchEvent(new Event("change", { bubbles: true }));
        }
        syncValueFromPicker(form);
      });
    });
    picker.querySelectorAll("[data-agent-table-group-select]").forEach((button) => {
      button.addEventListener("click", () => {
        const group = button.closest(".agent-table-group");
        if (!group) {
          return;
        }
        group.querySelectorAll("[data-agent-table-checkbox]").forEach((checkbox) => {
          checkbox.checked = true;
        });
        const scope = form.querySelector('select[name$="_data_scope"]');
        if (scope) {
          scope.value = "tables";
          scope.dispatchEvent(new Event("change", { bubbles: true }));
        }
        syncValueFromPicker(form);
      });
    });
  });

  document.querySelectorAll('form select[name$="_data_scope"]').forEach((scope) => {
    const form = scope.closest("form");
    const holder = form ? form.querySelector('[data-agent-table-values], textarea[name$="_allowed_tables"]') : null;
    const picker = form ? form.querySelector("[data-agent-table-picker]") : null;
    if (!holder && !picker) {
      return;
    }
    function sync() {
      const useTables = scope.value === "tables";
      if (holder && holder.tagName === "TEXTAREA") {
        holder.disabled = !useTables;
      }
      if (picker) {
        picker.querySelectorAll("input, button").forEach((control) => {
          control.disabled = !useTables;
        });
      }
      (picker || holder)?.closest("label")?.classList.toggle("is-disabled", !useTables);
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
  const legacySessionStorageKey = "moyi-admin-agent-session-id";
  const sessionStorageKey = `moyi-admin-agent-session-id:${form.action}`;
  let sessionID = loadSessionID();
  const submitButton = form.querySelector('button[type="submit"]');
  const newSessionButton = document.getElementById("agentNewSessionButton");
  const sessionState = document.getElementById("agentSessionState");
  const runMode = document.getElementById("agentRunMode");
  const runGoal = document.getElementById("agentRunGoal");
  const taskStatusBadge = document.getElementById("agentTaskStatusBadge");
  const taskTitle = document.getElementById("agentTaskTitle");
  const taskGoal = document.getElementById("agentTaskGoal");
  const taskIntent = document.getElementById("agentTaskIntent");
  const taskTable = document.getElementById("agentTaskTable");
  const taskFormat = document.getElementById("agentTaskFormat");
  const taskUpdated = document.getElementById("agentTaskUpdated");
  const taskStepList = document.getElementById("agentTaskStepList");
  const recentTaskList = document.getElementById("agentRecentTaskList");
  const planList = document.getElementById("agentPlanList");
  const traceList = document.getElementById("agentTraceList");
  const insightList = document.getElementById("agentInsightList");
  const suggestionList = document.getElementById("agentSuggestionList");
  const defaultAssistantIntro = "你好，我可以帮你整理后台信息、解释配置、生成清单，或者继续跟进你上一轮的问题。";
  const defaultRunMode = "Standby";
  const defaultRunGoal = "等待任务";
  const presetPrompt = loadPresetPrompt();
  let busy = false;

  function loadSessionID() {
    if (!window.localStorage) {
      return "";
    }
    const scoped = window.localStorage.getItem(sessionStorageKey) || "";
    if (scoped) {
      return scoped;
    }
    const legacy = window.localStorage.getItem(legacySessionStorageKey) || "";
    if (legacy) {
      window.localStorage.setItem(sessionStorageKey, legacy);
      window.localStorage.removeItem(legacySessionStorageKey);
    }
    return legacy;
  }

  function persistSessionID(value) {
    if (!window.localStorage) {
      return;
    }
    const normalized = (value || "").trim();
    if (normalized) {
      window.localStorage.setItem(sessionStorageKey, normalized);
    } else {
      window.localStorage.removeItem(sessionStorageKey);
    }
    window.localStorage.removeItem(legacySessionStorageKey);
  }

  function syncSessionState() {
    if (sessionState) {
      if (sessionID) {
        sessionState.textContent = "继续上一轮对话";
        sessionState.className = "agent-session-chip is-active";
      } else {
        sessionState.textContent = "准备开始新对话";
        sessionState.className = "agent-session-chip";
      }
    }
    if (newSessionButton) {
      newSessionButton.disabled = busy;
    }
  }

  function loadPresetPrompt() {
    try {
      const params = new URL(window.location.href).searchParams;
      return (params.get("prompt") || "").trim();
    } catch (error) {
      return "";
    }
  }

  function appendMessage(role, text, payload) {
    const toolResults = Array.isArray(payload) ? payload : payload && payload.toolResults;
    const files = collectFiles(toolResults);
    const run = payload && !Array.isArray(payload) ? payload.run : null;
    const currentTask = payload && !Array.isArray(payload) ? payload.currentTask : null;
    const article = document.createElement("article");
    article.className = `agent-message is-${role}`;

    const label = document.createElement("span");
    label.textContent = role === "user" ? "你" : "AI";
    article.appendChild(label);

    const paragraph = document.createElement("p");
    paragraph.textContent = text;
    article.appendChild(paragraph);

    if (run) {
      updateRuntimePanel(run);
    }
    if (currentTask) {
      updateCurrentTask(currentTask);
      upsertRecentTask(currentTask);
    }

    if (files.length > 0) {
      const fileList = document.createElement("div");
      fileList.className = "agent-message-files";
      files.forEach((file) => {
        fileList.appendChild(renderFileResult(file));
      });
      article.appendChild(fileList);
    }

    messages.appendChild(article);
    messages.scrollTop = messages.scrollHeight;
    return article;
  }

  function buildAssistantHistoryEntry(text, payload) {
    const parts = [];
    const content = (text || "").trim();
    if (content) {
      parts.push(content);
    }
    const toolResults = payload && Array.isArray(payload.toolResults) ? payload.toolResults : [];
    const summary = summarizeToolResultsForHistory(toolResults);
    if (summary) {
      parts.push(summary);
    }
    return parts.join("\n");
  }

  function summarizeToolResultsForHistory(toolResults) {
    if (!Array.isArray(toolResults) || toolResults.length === 0) {
      return "";
    }
    const lines = [];
    toolResults.forEach((result) => {
      if (!result) {
        return;
      }
      if (!result.ok) {
        const error = (result.error || "").trim();
        if (error) {
          lines.push(`工具结果：${error}`);
        }
        return;
      }
      if (result.name !== "export_table") {
        return;
      }
      const parts = [];
      if (result.table) {
        parts.push(`表 ${result.table}`);
      }
      if (result.file && result.file.name) {
        parts.push(`文件 ${result.file.name}`);
      }
      const message = (result.message || "").trim();
      if (message) {
        parts.push(message);
      }
      if (parts.length > 0) {
        lines.push(`工具结果：导出成功，${parts.join("，")}。`);
      }
    });
    return lines.join("\n");
  }

  function collectFiles(toolResults) {
    if (!Array.isArray(toolResults) || toolResults.length === 0) {
      return [];
    }
    const files = [];
    const seen = new Set();
    toolResults.forEach((result) => {
      if (!result || !result.ok || !result.file || !result.file.url) {
        return;
      }
      const key = `${result.file.url}|${result.file.name || ""}`;
      if (seen.has(key)) {
        return;
      }
      seen.add(key);
      files.push(result.file);
    });
    return files;
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
    if (traceList) {
      traceList.replaceChildren(...Array.from(renderTraceList(run.trace || []).children));
    }
    if (insightList) {
      insightList.replaceChildren(...Array.from(renderInsightList(run.insights || []).children));
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

  function taskStatusClass(status) {
    switch ((status || "").toLowerCase()) {
      case "done":
        return "is-ready";
      case "failed":
        return "is-warning";
      case "running":
        return "is-progress";
      default:
        return "is-muted";
    }
  }

  function resetRuntimePanel() {
    if (runMode) {
      runMode.textContent = defaultRunMode;
    }
    if (runGoal) {
      runGoal.textContent = defaultRunGoal;
    }
    if (planList) {
      planList.replaceChildren(emptyLine("提交任务后生成。"));
    }
    if (traceList) {
      traceList.replaceChildren(emptyLine("调用工具后展示。"));
    }
    if (insightList) {
      insightList.replaceChildren(emptyLine("有结果后会整理到这里。"));
    }
    if (suggestionList) {
      suggestionList.replaceChildren(
        renderSuggestionButton({ label: "智能体方案", prompt: "给出 Moyi Admin 智能体构造方案" }),
      );
    }
  }

  function resetConversationFeed(notice) {
    messages.replaceChildren();
    appendMessage("assistant", defaultAssistantIntro);
    if (notice) {
      appendMessage("assistant", notice);
    } else if (sessionID) {
      appendMessage("assistant", "这次会继续沿用上一轮对话内容。如果你想从头开始，点一下“新会话”就可以。");
    }
  }

  function startNewSession() {
    history.length = 0;
    sessionID = "";
    persistSessionID("");
    resetRuntimePanel();
    updateCurrentTask(null);
    resetConversationFeed("已开启新会话，这一轮会从新的上下文开始。");
    syncSessionState();
    input.focus();
  }

  function renderTaskStepItem(step) {
    const row = document.createElement("div");
    row.className = `agent-task-step ${taskStatusClass(step && step.status)}`;
    row.appendChild(strongLine((step && step.title) || "任务步骤"));
    row.appendChild(smallLine((step && step.detail) || "等待继续补充。"));
    return row;
  }

  function updateCurrentTask(task) {
    if (taskStatusBadge) {
      taskStatusBadge.className = `admin-badge ${taskStatusClass(task && task.status)}`;
      taskStatusBadge.textContent = (task && task.status_text) || "暂无任务";
    }
    if (taskTitle) {
      taskTitle.textContent = (task && task.title) || "等待新的后台任务";
    }
    if (taskGoal) {
      taskGoal.textContent = (task && task.goal) || "提交任务后，这里会保留当前任务目标、边界和步骤状态。";
    }
    if (taskIntent) {
      taskIntent.textContent = (task && task.intent) || "-";
    }
    if (taskTable) {
      taskTable.textContent = task && task.primary_table ? `主表 ${task.primary_table}` : "未聚焦数据表";
    }
    if (taskFormat) {
      taskFormat.textContent = task && task.export_format ? `导出 ${task.export_format}` : "未指定导出格式";
    }
    if (taskUpdated) {
      taskUpdated.textContent = (task && task.updated_at) || "-";
    }
    if (taskStepList) {
      const steps = task && Array.isArray(task.steps) ? task.steps : [];
      if (steps.length === 0) {
        taskStepList.replaceChildren(emptyLine("任务创建后会把步骤状态写到这里。"));
      } else {
        taskStepList.replaceChildren(...steps.map(renderTaskStepItem));
      }
    }
  }

  function createTaskHistoryItem(task) {
    const article = document.createElement("article");
    article.className = "agent-task-history-item";
    article.setAttribute("data-agent-task-id", (task && task.id) || "");

    const head = document.createElement("div");
    head.className = "agent-task-history-head";

    const copy = document.createElement("div");
    copy.appendChild(strongLine((task && task.title) || "后台任务"));
    copy.appendChild(smallLine((task && task.goal) || "等待继续补充。"));

    const badge = document.createElement("span");
    badge.className = `admin-badge ${taskStatusClass(task && task.status)}`;
    badge.textContent = (task && task.status_text) || "等待继续";
    head.append(copy, badge);
    article.appendChild(head);

    const meta = document.createElement("div");
    meta.className = "agent-task-history-meta";
    if (task && task.updated_at) {
      const updated = document.createElement("span");
      updated.textContent = task.updated_at;
      meta.appendChild(updated);
    }
    if (task && task.intent) {
      const intent = document.createElement("span");
      intent.className = "mono";
      intent.textContent = task.intent;
      meta.appendChild(intent);
    }
    if (task && task.primary_table) {
      const table = document.createElement("span");
      table.textContent = task.primary_table;
      meta.appendChild(table);
    }
    if (task && task.export_format) {
      const format = document.createElement("span");
      format.textContent = task.export_format;
      meta.appendChild(format);
    }
    article.appendChild(meta);

    if (task && task.last_user_message) {
      const message = document.createElement("p");
      message.textContent = task.last_user_message;
      article.appendChild(message);
    }
    return article;
  }

  function upsertRecentTask(task) {
    if (!recentTaskList || !task || !task.id) {
      return;
    }
    const item = createTaskHistoryItem(task);
    const existing = recentTaskList.querySelector(`[data-agent-task-id="${task.id}"]`);
    const emptyState = recentTaskList.querySelector(".agent-history-empty");
    if (emptyState) {
      emptyState.remove();
    }
    if (existing) {
      recentTaskList.replaceChild(item, existing);
      recentTaskList.prepend(item);
    } else {
      recentTaskList.prepend(item);
    }
    while (recentTaskList.children.length > 8) {
      recentTaskList.removeChild(recentTaskList.lastElementChild);
    }
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

  function renderFileResult(file) {
    const wrap = document.createElement("div");
    wrap.className = "agent-file-result";
    if (String(file.mime || "").startsWith("image/") && file.url) {
      const preview = document.createElement("img");
      preview.className = "agent-file-preview";
      preview.src = file.url;
      preview.alt = file.description || file.name || "生成图片";
      wrap.appendChild(preview);
    }
    const link = document.createElement("a");
    link.href = file.url;
    link.textContent = file.name || "下载文件";
    link.setAttribute("download", file.name || "");
    const meta = document.createElement("small");
    const size = Number(file.size || 0);
    meta.textContent = `${file.description || "生成文件"} · ${formatBytes(size)}`;
    wrap.append(link, meta);
    if (file.original_prompt || file.prompt) {
      const prompt = document.createElement("div");
      prompt.className = "agent-file-prompt";
      if (file.original_prompt) {
        const originalLabel = document.createElement("strong");
        originalLabel.textContent = "原始需求";
        const originalText = document.createElement("p");
        originalText.textContent = file.original_prompt;
        prompt.append(originalLabel, originalText);
      }
      if (file.prompt) {
        const label = document.createElement("strong");
        label.textContent = "实际绘图提示词";
        const text = document.createElement("p");
        text.textContent = file.prompt;
        prompt.append(label, text);
      }
      wrap.appendChild(prompt);
    }
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

  async function sendMessage(text) {
    const message = text.trim();
    if (!message) {
      return;
    }
    appendMessage("user", message);
    history.push({ role: "user", content: message });
    input.value = "";
    setBusy(true);

    const pending = appendMessage("assistant", "正在思考中...");
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
        persistSessionID(sessionID);
        syncSessionState();
      }
      appendMessage("assistant", result.reply || "已完成。", {
        run: result.run,
        currentTask: result.current_task || null,
        toolResults: result.tool_results || [],
      });
      history.push({
        role: "assistant",
        content: buildAssistantHistoryEntry(result.reply || "已完成。", {
          run: result.run,
          currentTask: result.current_task || null,
          toolResults: result.tool_results || [],
        }),
      });
    } catch (error) {
      pending.remove();
      appendMessage("assistant", `智能体请求失败：${error.message}`);
    } finally {
      setBusy(false);
      input.focus();
    }
  }

  function setBusy(isBusy) {
    busy = isBusy;
    if (submitButton) {
      submitButton.disabled = isBusy;
      submitButton.textContent = isBusy ? "发送中" : "发送";
    }
    input.disabled = isBusy;
    syncSessionState();
  }

  form.addEventListener("submit", (event) => {
    event.preventDefault();
    sendMessage(input.value);
  });

  function insertTextareaNewline(field) {
    const start = field.selectionStart || 0;
    const end = field.selectionEnd || 0;
    const value = field.value || "";
    field.value = `${value.slice(0, start)}\n${value.slice(end)}`;
    const cursor = start + 1;
    field.setSelectionRange(cursor, cursor);
    field.dispatchEvent(new Event("input", { bubbles: true }));
  }

  input.addEventListener("keydown", (event) => {
    if (event.key !== "Enter" || event.isComposing) {
      return;
    }
    if (event.shiftKey || event.metaKey || event.ctrlKey || event.altKey) {
      event.preventDefault();
      insertTextareaNewline(input);
      return;
    }
    event.preventDefault();
    form.requestSubmit();
  });

  newSessionButton?.addEventListener("click", () => {
    if (busy) {
      return;
    }
    startNewSession();
  });

  document.addEventListener("click", (event) => {
    const button = event.target.closest("[data-agent-prompt]");
    if (!button) {
      return;
    }
    sendMessage(button.getAttribute("data-agent-prompt") || "");
  });

  resetConversationFeed("");
  syncSessionState();
  if (presetPrompt && !input.value.trim()) {
    input.value = presetPrompt;
    input.focus();
    input.setSelectionRange(input.value.length, input.value.length);
  }
})();
