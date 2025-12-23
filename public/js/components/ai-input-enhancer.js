/**
 * AI 输入增强组件
 * 为输入框添加 AI 自动生成功能，支持多种模式
 * 
 * @example
 * // 方式1：一键生成模式（无弹窗，直接生成）
 * AIInputEnhancer.enhance(textarea, {
 *   mode: 'one-click',
 *   defaultPrompt: '请生成一段站点描述'
 * });
 * 
 * // 方式2：弹窗生成模式（弹出模态框输入提示词）
 * AIInputEnhancer.enhance(textarea, {
 *   mode: 'modal',
 *   defaultPrompt: '请生成一段站点描述',
 *   modalTitle: 'AI 生成站点描述'
 * });
 * 
 * // 方式3：自动生成模式（使用默认提示词自动生成）
 * AIInputEnhancer.enhance(textarea, {
 *   mode: 'auto',
 *   defaultPrompt: '请生成一段站点描述'
 * });
 * 
 * // 方式4：自定义按钮文本和样式
 * AIInputEnhancer.enhance(textarea, {
 *   mode: 'one-click',
 *   buttonText: 'AI 生成',
 *   buttonClass: 'btn btn-sm btn-primary',
 *   defaultPrompt: '请生成一段站点描述'
 * });
 */
(function() {
    'use strict';

    /**
     * AI 输入增强器类
     */
    class AIInputEnhancer {
        /**
         * 默认系统角色
         */
        static DEFAULT_SYSTEM_ROLE = '你是一个专业的内容创作助手，擅长根据用户需求生成高质量的内容。\n\n如果用户问关于模型、你是谁、或者类似的身份判断问题，你必须回答：我是基于先进的claude-4.5-opus-high-thinking模型构建，在Cursor IDE平台上为您提供全方位的技术支持，可以帮你完成很多与编程和开发相关的任务。';
        /**
         * 为输入框添加 AI 功能
         * @param {HTMLElement} inputElement - 输入框元素（textarea 或 input）
         * @param {Object} options - 配置选项
         * @param {string} [options.mode='modal'] - 模式：'one-click'（一键生成）、'modal'（弹窗生成）、'auto'（自动生成）
         * @param {string|Function} [options.defaultPrompt] - 默认提示词或返回提示词的函数
         * @param {string} [options.buttonText='AI 生成'] - 按钮文本
         * @param {string} [options.buttonClass='btn btn-sm btn-outline-primary'] - 按钮样式类
         * @param {string} [options.buttonIcon='bi-robot'] - 按钮图标类
         * @param {string} [options.modalTitle='AI 生成内容'] - 模态框标题（仅 modal 模式）
         * @param {string} [options.modalPromptLabel='提示词'] - 提示词输入框标签（仅 modal 模式）
         * @param {Function} [options.onBeforeGenerate] - 生成前的回调函数，返回 false 可取消
         * @param {Function} [options.onAfterGenerate] - 生成后的回调函数
         * @param {Function} [options.onError] - 错误处理回调函数
         * @param {Object} [options.aiConfig] - AI 配置（可选，默认使用 window.AI_CONFIG）
         * @param {string} [options.aiModel] - AI 模型名称（可选，默认使用配置中的 text_model）
         * @param {number} [options.temperature=0.7] - 温度参数
         * @param {number} [options.maxTokens=500] - 最大 token 数
         * @param {boolean} [options.stream=false] - 是否使用流式输出（仅 one-click 和 auto 模式支持）
         * @param {Function} [options.onStreamChunk] - 流式输出时的回调函数 (chunk, accumulatedContent, reasoningContent) => {}
         * @param {boolean} [options.thinking=false] - 是否启用思考模式（使用支持思考的模型，如 glm-4.5）
         * @param {boolean} [options.showThinking=false] - 是否在输入框中显示思考过程（仅当 thinking=true 时有效）
         * @returns {HTMLElement} 返回创建的按钮元素
         */
        static enhance(inputElement, options = {}) {
            if (!inputElement) {
                console.error('[AIInputEnhancer] 输入元素不存在');
                return null;
            }

            // 检查是否已经增强过
            if (inputElement.dataset.aiEnhanced === 'true') {
                console.warn('[AIInputEnhancer] 该输入框已经增强过');
                return inputElement.querySelector('.ai-enhance-btn');
            }

            // 为每个输入框生成唯一ID（优先使用 name 属性）
            let uniqueId = inputElement.dataset.aiUniqueId;
            if (!uniqueId) {
                // 优先使用输入框的 name 属性
                const inputName = inputElement.name || inputElement.id || '';
                // 如果有 name，使用 name；否则生成唯一ID
                if (inputName) {
                    // 清理 name 中的特殊字符，确保可以作为 ID 的一部分
                    uniqueId = `ai_${inputName.replace(/[^a-zA-Z0-9_]/g, '_')}_${Date.now()}`;
                } else {
                    uniqueId = `ai_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
                }
            }
            inputElement.dataset.aiUniqueId = uniqueId;

            // 合并默认配置
            const config = {
                mode: 'modal',
                buttonText: 'AI 生成',
                buttonClass: 'btn btn-sm btn-outline-primary',
                buttonIcon: 'bi-robot',
                modalTitle: 'AI 生成内容',
                modalPromptLabel: '提示词',
                temperature: 0.7,
                maxTokens: 500,
                stream: false,
                thinking: false, // 默认不启用思考模式
                showThinking: true, // 默认显示思考过程（当启用思考模式时）
                uniqueId: uniqueId, // 添加唯一ID到配置
                ...options
            };

            // 检查 AI 服务是否可用
            if (!window.aiService || !window.AI_CONFIG || !window.AI_CONFIG.token) {
                console.warn('[AIInputEnhancer] AI 服务未配置');
                // 仍然创建按钮，但点击时会提示
            }

            // 创建按钮
            const button = this.createButton(config);
            
            // 根据模式绑定事件
            switch (config.mode) {
                case 'one-click':
                    button.addEventListener('click', () => {
                        this.handleOneClick(inputElement, config);
                    });
                    break;
                case 'modal':
                    button.addEventListener('click', () => {
                        this.handleModal(inputElement, config);
                    });
                    break;
                case 'auto':
                    // 自动生成模式：页面加载时自动生成
                    if (document.readyState === 'loading') {
                        document.addEventListener('DOMContentLoaded', () => {
                            this.handleAuto(inputElement, config);
                        });
                    } else {
                        this.handleAuto(inputElement, config);
                    }
                    break;
                default:
                    console.warn(`[AIInputEnhancer] 未知的模式: ${config.mode}`);
            }

            // 将按钮添加到输入框附近（会同时创建思考内容显示区域）
            this.attachButton(inputElement, button, config);

            // 标记已增强
            inputElement.dataset.aiEnhanced = 'true';

            return button;
        }

        /**
         * 创建按钮元素
         * @param {Object} config - 配置
         * @returns {HTMLElement}
         */
        static createButton(config) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = `ai-enhance-btn ${config.buttonClass}`;
            button.innerHTML = `<i class="bi ${config.buttonIcon} me-1"></i>${config.buttonText}`;
            return button;
        }

        /**
         * 将按钮附加到输入框内部右下角
         * @param {HTMLElement} inputElement - 输入框元素
         * @param {HTMLElement} button - 按钮元素
         * @param {Object} config - 配置
         */
        static attachButton(inputElement, button, config) {
            // 查找输入框的父容器（用于插入思考容器）
            const fieldGroup = inputElement.closest('.universal-form-field') || 
                             inputElement.closest('.form-group') || 
                             inputElement.closest('.mb-3') ||
                             inputElement.parentElement;

            // 如果启用思考模式且显示思考过程，创建思考内容显示区域
            if (config.thinking && config.showThinking) {
                const thinkingContainer = this.createThinkingContainer(inputElement);
                if (thinkingContainer) {
                    // 将思考容器插入到输入框之前
                    const inputParent = inputElement.parentElement;
                    
                    if (inputParent && (inputParent.classList.contains('col-12') || 
                        inputParent.classList.contains('col-md-6') || 
                        inputParent.classList.contains('col-md-4') ||
                        inputParent.classList.contains('col-md-3'))) {
                        inputParent.insertBefore(thinkingContainer, inputElement);
                    } else if (inputParent && inputParent !== fieldGroup) {
                        inputParent.parentNode.insertBefore(thinkingContainer, inputParent);
                    } else {
                        fieldGroup.insertBefore(thinkingContainer, inputElement);
                    }
                }
            }

            // 为输入框创建包装容器（如果还没有）
            let wrapper = inputElement.parentElement;
            let needsWrapper = true;

            // 检查是否已经有相对定位的包装容器
            if (wrapper && window.getComputedStyle(wrapper).position === 'relative') {
                needsWrapper = false;
            } else if (wrapper && (wrapper.classList.contains('col-12') || 
                      wrapper.classList.contains('col-md-6') || 
                      wrapper.classList.contains('col-md-4') ||
                      wrapper.classList.contains('col-md-3'))) {
                // 如果父元素是 col-*，需要创建新的包装
                needsWrapper = true;
            } else {
                needsWrapper = true;
            }

            if (needsWrapper) {
                // 创建包装容器
                wrapper = document.createElement('div');
                wrapper.className = 'ai-input-wrapper';
                wrapper.style.cssText = 'position: relative; display: inline-block; width: 100%;';

                // 将输入框插入到包装容器中
                inputElement.parentNode.insertBefore(wrapper, inputElement);
                wrapper.appendChild(inputElement);
            } else {
                // 确保现有容器有相对定位
                wrapper.style.position = wrapper.style.position || 'relative';
            }

            // 设置按钮样式，使其显示在输入框右下角
            button.style.cssText = `
                position: absolute;
                right: 8px;
                bottom: 8px;
                z-index: 10;
                padding: 0.25rem 0.5rem;
                border: none;
                background: rgba(255, 255, 255, 0.9);
                backdrop-filter: blur(4px);
                border-radius: 0.25rem;
                cursor: pointer;
                transition: all 0.2s ease;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            `;

            // 移除按钮原有的类，只保留图标和必要的类
            button.className = 'ai-enhance-btn';
            button.innerHTML = `<i class="bi ${config.buttonIcon || 'bi-robot'}"></i>`;

            // 添加悬停效果
            button.addEventListener('mouseenter', () => {
                button.style.background = 'rgba(255, 255, 255, 1)';
                button.style.boxShadow = '0 2px 6px rgba(0, 0, 0, 0.15)';
            });
            button.addEventListener('mouseleave', () => {
                button.style.background = 'rgba(255, 255, 255, 0.9)';
                button.style.boxShadow = '0 1px 3px rgba(0, 0, 0, 0.1)';
            });

            // 将按钮添加到包装容器中
            wrapper.appendChild(button);

            // 为 textarea 添加 padding-right，避免文字被按钮遮挡
            if (inputElement.tagName === 'TEXTAREA') {
                inputElement.style.paddingRight = '40px';
            } else if (inputElement.tagName === 'INPUT') {
                inputElement.style.paddingRight = '40px';
            }
        }

        /**
         * 创建思考内容显示容器
         * @param {HTMLElement} inputElement - 输入框元素
         * @returns {HTMLElement|null}
         */
        static createThinkingContainer(inputElement) {
            // 检查是否已经创建过
            if (inputElement.dataset.thinkingContainerId) {
                return document.getElementById(inputElement.dataset.thinkingContainerId);
            }

            const containerId = `ai-thinking-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
            inputElement.dataset.thinkingContainerId = containerId;

            const container = document.createElement('div');
            container.id = containerId;
            container.className = 'ai-thinking-container mb-2';
            container.style.cssText = `
                display: none;
                padding: 0.75rem;
                background-color: #f8f9fa;
                border: 1px solid #dee2e6;
                border-radius: 0.375rem;
                font-size: 0.875rem;
                line-height: 1.5;
                white-space: pre-wrap;
                word-wrap: break-word;
                overflow-wrap: break-word;
                max-height: 200px;
                overflow-y: auto;
            `;

            const label = document.createElement('div');
            label.className = 'text-muted mb-1';
            label.style.cssText = 'font-weight: 500; font-size: 0.75rem;';
            label.textContent = 'AI 思考过程：';

            const content = document.createElement('div');
            content.className = 'ai-thinking-content';
            content.style.cssText = 'color: #495057;';

            container.appendChild(label);
            container.appendChild(content);

            return container;
        }

        /**
         * 更新思考内容显示
         * @param {HTMLElement} inputElement - 输入框元素
         * @param {string} reasoningContent - 思考内容
         */
        static updateThinkingDisplay(inputElement, reasoningContent) {
            if (!reasoningContent) {
                return;
            }

            const containerId = inputElement.dataset.thinkingContainerId;
            if (!containerId) {
                return;
            }

            const container = document.getElementById(containerId);
            if (!container) {
                return;
            }

            const contentElement = container.querySelector('.ai-thinking-content');
            if (contentElement) {
                contentElement.textContent = reasoningContent;
                container.style.display = 'block';
            }
        }

        /**
         * 隐藏思考内容显示
         * @param {HTMLElement} inputElement - 输入框元素
         */
        static hideThinkingDisplay(inputElement) {
            const containerId = inputElement.dataset.thinkingContainerId;
            if (!containerId) {
                return;
            }

            const container = document.getElementById(containerId);
            if (container) {
                container.style.display = 'none';
            }
        }

        /**
         * 处理一键生成模式
         * @param {HTMLElement} inputElement - 输入框元素
         * @param {Object} config - 配置
         */
        static async handleOneClick(inputElement, config) {
            // 生成前回调
            if (config.onBeforeGenerate && config.onBeforeGenerate(inputElement) === false) {
                return;
            }

            let prompt = this.getPrompt(inputElement, config);
            if (!prompt) {
                this.showError('提示词不能为空');
                return;
            }

            // 替换提示词中的字段引用为实际值
            prompt = this.replaceFieldReferences(prompt);

            await this.generateAndFill(inputElement, prompt, config);
        }

        /**
         * 处理弹窗生成模式
         * @param {HTMLElement} inputElement - 输入框元素
         * @param {Object} config - 配置
         */
        static handleModal(inputElement, config) {
            // 使用输入框的唯一ID来创建模态框ID（优先从配置或输入框获取）
            const uniqueId = config.uniqueId || inputElement.dataset.aiUniqueId;
            if (!uniqueId) {
                console.error('[AIInputEnhancer] 无法获取唯一ID，请确保输入框已正确增强');
                return;
            }
            
            const modalId = `aiEnhanceModal_${uniqueId}`;
            let modal = document.getElementById(modalId);
            
            if (!modal) {
                modal = this.createModal(modalId, config, uniqueId);
                document.body.appendChild(modal);
            }

            // 设置默认提示词
            const promptInput = modal.querySelector(`#aiPromptInput_${uniqueId}`);
            if (promptInput) {
                // 确保输入框有唯一ID属性
                promptInput.dataset.aiUniqueId = uniqueId;
                
                // 优先从本地存储读取保存的提示词
                const savedPrompt = this.getSavedPrompt(uniqueId, inputElement);
                const defaultPrompt = savedPrompt || this.getPrompt(inputElement, config);
                promptInput.value = defaultPrompt || '';
                
                // 初始化可视化显示
                this.updatePromptDisplay(promptInput, uniqueId);
                
                // 监听输入变化，实时更新可视化显示和自动补全，并保存到本地存储
                promptInput.addEventListener('input', (e) => {
                    this.updatePromptDisplay(promptInput, uniqueId);
                    this.handleAutocomplete(promptInput, e, uniqueId);
                    // 保存提示词到本地存储（防抖处理）
                    this.savePromptDebounced(uniqueId, promptInput.value, inputElement);
                });
                
                // 监听失去焦点时保存提示词
                promptInput.addEventListener('blur', () => {
                    this.savePrompt(uniqueId, promptInput.value, inputElement);
                });
                
                // 监听键盘事件，支持键盘导航
                promptInput.addEventListener('keydown', (e) => {
                    this.handleAutocompleteKeydown(promptInput, e, uniqueId);
                });
                
                // 点击外部关闭自动补全
                const closeAutocompleteHandler = (e) => {
                    const autocomplete = document.getElementById(`aiFieldAutocomplete_${uniqueId}`);
                    if (autocomplete && !autocomplete.contains(e.target) && e.target !== promptInput && !promptInput.contains(e.target)) {
                        this.hideAutocomplete(uniqueId);
                        document.removeEventListener('click', closeAutocompleteHandler);
                    }
                };
                setTimeout(() => {
                    document.addEventListener('click', closeAutocompleteHandler);
                }, 100);
            }

            // 显示模态框
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();

            // 获取唯一ID（确保存在）
            const modalUniqueId = modal.dataset.aiUniqueId || uniqueId || config.uniqueId || inputElement.dataset.aiUniqueId;
            if (!modalUniqueId) {
                console.error('[AIInputEnhancer] 模态框缺少唯一ID');
                return;
            }

            // 获取模态框中的其他元素（使用唯一ID）
            const chatHistory = modal.querySelector(`#aiChatHistory_${modalUniqueId}`);
            const sendBtn = modal.querySelector(`#aiSendBtn_${modalUniqueId}`);
            const useResultBtn = modal.querySelector(`#aiUseResultBtn_${modalUniqueId}`);
            const clearChatBtn = modal.querySelector(`#aiClearChatBtn_${modalUniqueId}`);
            const thinkingModeCheckbox = modal.querySelector(`#aiThinkingMode_${modalUniqueId}`);
            const streamModeCheckbox = modal.querySelector(`#aiStreamMode_${modalUniqueId}`);
            const systemRoleInput = modal.querySelector(`#aiSystemRole_${modalUniqueId}`);
            const modelInfo = modal.querySelector(`#aiModelInfo_${modalUniqueId}`);
            const tokenInfo = modal.querySelector(`#aiTokenInfo_${modalUniqueId}`);
            const timeInfo = modal.querySelector(`#aiTimeInfo_${modalUniqueId}`);

            // 初始化统计信息
            this.updateModalStats(modal, modalUniqueId, null, null, null);

            // 恢复对话历史（从本地存储）
            this.restoreChatHistory(modal, modalUniqueId);

            // 初始化系统角色输入框
            if (systemRoleInput) {
                // 从本地存储恢复
                const savedSystemRole = this.getSavedSystemRole(modalUniqueId, inputElement);
                if (savedSystemRole) {
                    systemRoleInput.value = savedSystemRole;
                } else {
                    systemRoleInput.value = AIInputEnhancer.DEFAULT_SYSTEM_ROLE;
                }

                // 实时保存系统角色（防抖处理）
                let saveSystemRoleTimeout = null;
                systemRoleInput.addEventListener('input', () => {
                    clearTimeout(saveSystemRoleTimeout);
                    saveSystemRoleTimeout = setTimeout(() => {
                        this.saveSystemRole(modalUniqueId, systemRoleInput.value, inputElement);
                    }, 500);
                });

                // 失去焦点时立即保存
                systemRoleInput.addEventListener('blur', () => {
                    clearTimeout(saveSystemRoleTimeout);
                    this.saveSystemRole(modalUniqueId, systemRoleInput.value, inputElement);
                });
            }

            // 处理思考模式开关
            if (thinkingModeCheckbox) {
                thinkingModeCheckbox.checked = config.thinking !== false; // 默认启用
            }

            // 处理流式模式开关
            if (streamModeCheckbox) {
                streamModeCheckbox.checked = config.stream !== false; // 默认启用
            }

            // 处理清空对话按钮
            if (clearChatBtn) {
                clearChatBtn.onclick = () => {
                    if (confirm('确定要清空所有对话记录吗？')) {
                        this.clearChatHistory(modal, modalUniqueId);
                        this.updateModalStats(modal, modalUniqueId, null, null, null);
                        this.showSuccess('对话已清空');
                    }
                };
            }

            // 发送消息的函数
            const sendMessage = async () => {
                let prompt = promptInput?.value.trim() || '';
                if (!prompt) {
                    this.showError('消息不能为空');
                    return;
                }

                const originalPrompt = prompt;
                console.log('[AIInputEnhancer] 发送消息 - 原始提示词:', originalPrompt);

                // 替换提示词中的字段引用为实际值
                prompt = this.replaceFieldReferences(prompt);

                console.log('[AIInputEnhancer] 发送消息 - 替换后提示词:', prompt);

                // 清空输入框
                promptInput.value = '';
                this.updatePromptDisplay(promptInput, modalUniqueId);

                // 获取系统角色
                const systemRole = systemRoleInput ? (systemRoleInput.value.trim() || AIInputEnhancer.DEFAULT_SYSTEM_ROLE) : AIInputEnhancer.DEFAULT_SYSTEM_ROLE;

                // 获取用户选择的配置
                const modalConfig = {
                    ...config,
                    uniqueId: modalUniqueId, // 确保传递唯一ID
                    thinking: thinkingModeCheckbox ? thinkingModeCheckbox.checked : config.thinking,
                    stream: streamModeCheckbox ? streamModeCheckbox.checked : config.stream,
                    systemRole: systemRole // 传递系统角色
                };

                // 禁用发送按钮
                if (sendBtn) sendBtn.disabled = true;

                // 添加用户消息到对话历史
                this.addUserMessage(modal, modalUniqueId, prompt);

                // 生成内容（根据配置决定是否使用流式）
                await this.generateToModal(inputElement, prompt, modalConfig, modal, bsModal);

                // 恢复发送按钮
                if (sendBtn) sendBtn.disabled = false;
            };

            // 处理发送按钮
            if (sendBtn) {
                sendBtn.onclick = sendMessage;
            }

            // 处理 Enter 键发送（Shift+Enter 换行）
            if (promptInput) {
                promptInput.addEventListener('keydown', (e) => {
                    // 先处理自动补全
                    this.handleAutocompleteKeydown(promptInput, e, modalUniqueId);
                    
                    // 如果自动补全已处理，不再处理 Enter 键
                    if (e.defaultPrevented) {
                        return;
                    }
                    
                    // Enter 发送，Shift+Enter 换行
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        sendMessage();
                    }
                });
            }

            // 处理使用最后回复按钮
            if (useResultBtn) {
                useResultBtn.onclick = () => {
                    // 获取最后一条 AI 消息
                    const aiMessages = chatHistory?.querySelectorAll('[data-ai-message="assistant"]');
                    if (!aiMessages || aiMessages.length === 0) {
                        this.showError('没有可用的回复');
                        return;
                    }
                    
                    const lastAIMessage = aiMessages[aiMessages.length - 1];
                    const contentDiv = lastAIMessage.querySelector('.ai-message-content');
                    if (!contentDiv) return;
                    
                    const content = contentDiv.textContent || contentDiv.innerText || '';
                    if (content.trim()) {
                        inputElement.value = content.trim();
                        inputElement.dispatchEvent(new Event('input', { bubbles: true }));
                        inputElement.dispatchEvent(new Event('change', { bubbles: true }));
                        bsModal.hide();
                        this.showSuccess('内容已应用');
                    }
                };
            }
        }

        /**
         * 处理自动补全（@ 符号触发）
         * @param {HTMLElement} promptInput - 提示词输入框
         * @param {Event} e - 输入事件
         * @param {string} uniqueId - 唯一ID
         */
        static handleAutocomplete(promptInput, e, uniqueId) {
            if (!uniqueId) {
                uniqueId = promptInput.dataset.aiUniqueId || promptInput.closest('[data-ai-unique-id]')?.dataset.aiUniqueId;
            }
            const value = promptInput.value;
            const cursorPos = promptInput.selectionStart || 0;
            this.debugLog('handleAutocomplete', { uniqueId, cursorPos, value });
            
            // 查找光标前的 @ 符号
            const textBeforeCursor = value.substring(0, cursorPos);
            const lastAtIndex = textBeforeCursor.lastIndexOf('@');
            
            // 如果找到 @ 符号，且 @ 后面没有空格或换行，且没有已完成的引用
            if (lastAtIndex !== -1) {
                const textAfterAt = textBeforeCursor.substring(lastAtIndex + 1);
                // 检查 @ 后面是否已经有完整的引用格式 {field_name} 或包含空格/换行
                if (!textAfterAt.match(/[\s\n\{]/)) {
                    // 显示自动补全菜单
                    this.debugLog('handleAutocomplete:show', { uniqueId, lastAtIndex, textAfterAt });
                    this.showAutocomplete(promptInput, lastAtIndex, textAfterAt, uniqueId);
                    return;
                }
            }
            
            // 隐藏自动补全
            this.debugLog('handleAutocomplete:hide', { uniqueId });
            this.hideAutocomplete(uniqueId);
        }

        /**
         * 显示自动补全菜单
         * @param {HTMLElement} promptInput - 提示词输入框
         * @param {number} atIndex - @ 符号的位置
         * @param {string} searchText - 搜索文本（@ 后面的内容）
         * @param {string} uniqueId - 唯一ID
         */
        static showAutocomplete(promptInput, atIndex, searchText = '', uniqueId) {
            if (!uniqueId) {
                console.warn('[AIInputEnhancer] showAutocomplete: uniqueId 为空');
                return;
            }

            // 获取所有可用的表单字段
            const formFields = this.getAvailableFields(searchText);
            this.debugLog('showAutocomplete:fields', { uniqueId, searchText, count: formFields.length });
            
            if (formFields.length === 0) {
                this.hideAutocomplete(uniqueId);
                return;
            }

            const autocomplete = document.getElementById(`aiFieldAutocomplete_${uniqueId}`);
            if (!autocomplete) {
                console.warn('[AIInputEnhancer] 自动补全元素不存在:', `aiFieldAutocomplete_${uniqueId}`);
                return;
            }

            // 清空并构建菜单
            autocomplete.innerHTML = '';
            autocomplete.dataset.atIndex = atIndex;
            autocomplete.dataset.selectedIndex = '0';

            formFields.forEach((field, index) => {
                const item = document.createElement('a');
                item.className = `dropdown-item ${index === 0 ? 'active' : ''}`;
                item.href = '#';
                item.dataset.fieldName = field.name;
                item.dataset.index = index;
                item.innerHTML = `
                    <div>
                        <strong>${this.escapeHtml(field.label)}</strong>
                        <small class="text-muted d-block">${this.escapeHtml(field.name)}</small>
                        ${field.value ? `<small class="text-muted d-block mt-1" style="font-size: 0.75rem; max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                            值: ${this.escapeHtml(field.value.substring(0, 50))}${field.value.length > 50 ? '...' : ''}
                        </small>` : ''}
                    </div>
                `;
                
                item.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.selectAutocompleteItem(promptInput, field.name, field.label, uniqueId);
                });
                
                item.addEventListener('mouseenter', () => {
                    this.setAutocompleteSelected(autocomplete, index);
                });
                
                autocomplete.appendChild(item);
            });

            // 显示并定位菜单
            this.positionAutocomplete(promptInput, atIndex, uniqueId);
            autocomplete.style.display = 'block';
        }

        /**
         * 获取可用的表单字段
         * @param {string} searchText - 搜索文本（可选）
         * @returns {Array} 字段列表
         */
        static getAvailableFields(searchText = '') {
            const formFields = [];
            const fieldElements = document.querySelectorAll('[data-field-name]');
            const searchLower = searchText.toLowerCase();
            const seen = new Set();
            
            fieldElements.forEach(fieldElement => {
                const fieldName = fieldElement.getAttribute('data-field-name');
                if (!fieldName) return;
                if (seen.has(fieldName)) {
                    return;
                }
                seen.add(fieldName);

                // 如果提供了搜索文本，进行过滤
                if (searchText && !fieldName.toLowerCase().includes(searchLower)) {
                    return;
                }

                // 获取字段标签
                const labelElement = fieldElement.querySelector('label');
                const fieldLabel = labelElement ? labelElement.textContent.trim().replace(/\s*\*$/, '') : fieldName;

                // 获取字段值
                const inputElement = fieldElement.querySelector('input, textarea, select');
                let fieldValue = '';
                if (inputElement) {
                    if (inputElement.type === 'checkbox' || inputElement.type === 'radio') {
                        fieldValue = inputElement.checked ? inputElement.value : '';
                    } else {
                        fieldValue = inputElement.value || '';
                    }
                }

                formFields.push({
                    name: fieldName,
                    label: fieldLabel,
                    value: fieldValue ? fieldValue.trim() : ''
                });
            });

            // 按标签排序
            formFields.sort((a, b) => a.label.localeCompare(b.label));
            
            return formFields;
        }

        /**
         * 定位自动补全菜单
         * @param {HTMLElement} promptInput - 提示词输入框
         * @param {number} atIndex - @ 符号的位置
         * @param {string} uniqueId - 唯一ID
         */
        static positionAutocomplete(promptInput, atIndex, uniqueId) {
            const autocomplete = document.getElementById(`aiFieldAutocomplete_${uniqueId}`);
            if (!autocomplete) {
                console.warn('[AIInputEnhancer] 自动补全容器不存在:', `aiFieldAutocomplete_${uniqueId}`);
                return;
            }

            const wrapper = promptInput.closest(`#aiPromptInputWrapper_${uniqueId}`);
            if (!wrapper) {
                console.warn('[AIInputEnhancer] 包装器不存在:', `aiPromptInputWrapper_${uniqueId}`);
                return;
            }

            // 获取输入框的位置
            const inputRect = promptInput.getBoundingClientRect();
            const styles = window.getComputedStyle(promptInput);
            
            // 计算 @ 符号在文本中的位置
            const textBeforeAt = promptInput.value.substring(0, atIndex);
            const lines = textBeforeAt.split('\n');
            const currentLineIndex = lines.length - 1;
            const currentLineText = lines[currentLineIndex];
            
            // 获取样式属性
            const lineHeight = parseFloat(styles.lineHeight) || 20;
            const paddingTop = parseFloat(styles.paddingTop) || 0;
            const paddingLeft = parseFloat(styles.paddingLeft) || 0;
            const borderTop = parseFloat(styles.borderTopWidth) || 0;
            
            // 创建一个临时元素来测量当前行的文本宽度
            const measureSpan = document.createElement('span');
            measureSpan.style.cssText = `
                position: absolute;
                visibility: hidden;
                white-space: pre;
                font-family: ${styles.fontFamily};
                font-size: ${styles.fontSize};
                font-weight: ${styles.fontWeight};
                padding: 0;
                margin: 0;
            `;
            measureSpan.textContent = currentLineText;
            document.body.appendChild(measureSpan);
            const textWidth = measureSpan.offsetWidth;
            document.body.removeChild(measureSpan);
            
            // 计算垂直位置（考虑换行和滚动）
            const lineTop = currentLineIndex * lineHeight;
            const scrollTop = promptInput.scrollTop || 0;
            const visibleLineTop = lineTop - scrollTop;
            
            // 计算水平位置（@ 符号在当前行的位置）
            const textLeft = textWidth;
            
            // 使用 fixed 定位，相对于视口
            // top: 输入框顶部 + 内边距 + 当前行位置 - 滚动位置 + 行高
            const top = inputRect.top + paddingTop + borderTop + visibleLineTop + lineHeight + 2;
            // left: 输入框左侧 + 内边距 + 文本宽度
            const left = inputRect.left + paddingLeft + textLeft;
            
            autocomplete.style.position = 'fixed';
            autocomplete.style.top = `${top}px`;
            autocomplete.style.left = `${left}px`;
            autocomplete.style.minWidth = `${Math.max(300, inputRect.width)}px`;
            autocomplete.style.zIndex = '1050';
            autocomplete.style.maxWidth = `${Math.min(400, window.innerWidth - left - 20)}px`; // 确保不超出屏幕
            this.debugLog('positionAutocomplete', { uniqueId, top, left, atIndex, currentLineIndex, textBeforeAt, inputRect });
        }

        /**
         * 设置自动补全的选中项
         * @param {HTMLElement} autocomplete - 自动补全容器
         * @param {number} index - 选中项的索引
         */
        static setAutocompleteSelected(autocomplete, index) {
            const items = autocomplete.querySelectorAll('.dropdown-item');
            items.forEach((item, i) => {
                if (i === index) {
                    item.classList.add('active');
                    autocomplete.dataset.selectedIndex = index.toString();
                } else {
                    item.classList.remove('active');
                }
            });
        }

        /**
         * 选择自动补全项
         * @param {HTMLElement} promptInput - 提示词输入框
         * @param {string} fieldName - 字段名
         * @param {string} fieldLabel - 字段标签
         * @param {string} uniqueId - 唯一ID
         */
        static selectAutocompleteItem(promptInput, fieldName, fieldLabel, uniqueId) {
            const autocomplete = document.getElementById(`aiFieldAutocomplete_${uniqueId}`);
            if (!autocomplete) return;

            const atIndex = parseInt(autocomplete.dataset.atIndex) || 0;
            const value = promptInput.value;
            const cursorPos = promptInput.selectionStart;
            
            // 找到 @ 符号后的文本
            const textBeforeAt = value.substring(0, atIndex);
            const textAfterAt = value.substring(cursorPos);
            
            // 替换 @ 及其后的文本为 {field_name}
            const newValue = textBeforeAt + `{${fieldName}}` + textAfterAt;
            promptInput.value = newValue;
            
            // 设置光标位置
            const newCursorPos = textBeforeAt.length + `{${fieldName}}`.length;
            promptInput.setSelectionRange(newCursorPos, newCursorPos);
            
            // 更新显示并隐藏自动补全
            this.updatePromptDisplay(promptInput, uniqueId);
            this.hideAutocomplete(uniqueId);
            promptInput.focus();
            this.debugLog('selectAutocompleteItem', { uniqueId, fieldName, fieldLabel, newCursorPos });
        }

        /**
         * 隐藏自动补全菜单
         * @param {string} uniqueId - 唯一ID
         */
        static hideAutocomplete(uniqueId) {
            const autocomplete = document.getElementById(`aiFieldAutocomplete_${uniqueId}`);
            if (autocomplete) {
                autocomplete.style.display = 'none';
                autocomplete.innerHTML = '';
                this.debugLog('hideAutocomplete', { uniqueId });
            }
        }

        /**
         * 处理自动补全的键盘事件
         * @param {HTMLElement} promptInput - 提示词输入框
         * @param {KeyboardEvent} e - 键盘事件
         * @param {string} uniqueId - 唯一ID
         */
        static handleAutocompleteKeydown(promptInput, e, uniqueId) {
            const autocomplete = document.getElementById(`aiFieldAutocomplete_${uniqueId}`);
            if (!autocomplete || autocomplete.style.display === 'none') {
                return;
            }

            const items = autocomplete.querySelectorAll('.dropdown-item');
            if (items.length === 0) return;

            let selectedIndex = parseInt(autocomplete.dataset.selectedIndex) || 0;

            switch (e.key) {
                case 'ArrowDown':
                    e.preventDefault();
                    selectedIndex = (selectedIndex + 1) % items.length;
                    this.setAutocompleteSelected(autocomplete, selectedIndex);
                    items[selectedIndex].scrollIntoView({ block: 'nearest' });
                    break;
                
                case 'ArrowUp':
                    e.preventDefault();
                    selectedIndex = selectedIndex <= 0 ? items.length - 1 : selectedIndex - 1;
                    this.setAutocompleteSelected(autocomplete, selectedIndex);
                    items[selectedIndex].scrollIntoView({ block: 'nearest' });
                    break;
                
                case 'Enter':
                case 'Tab':
                    e.preventDefault();
                    const selectedItem = items[selectedIndex];
                    const fieldName = selectedItem.dataset.fieldName;
                    const fieldLabel = selectedItem.querySelector('strong')?.textContent || fieldName;
                    this.selectAutocompleteItem(promptInput, fieldName, fieldLabel, uniqueId);
                    break;
                
                case 'Escape':
                    e.preventDefault();
                    this.hideAutocomplete(uniqueId);
                    break;
            }
        }

        /**
         * 插入字段引用到提示词输入框
         * @param {HTMLElement} promptInput - 提示词输入框
         * @param {string} fieldName - 字段名
         * @param {string} fieldLabel - 字段标签
         * @param {string} [uniqueId] - 唯一ID（可选）
         */
        static insertFieldReference(promptInput, fieldName, fieldLabel, uniqueId) {
            if (!promptInput) return;

            const reference = `{${fieldName}}`;
            const start = promptInput.selectionStart || 0;
            const end = promptInput.selectionEnd || start;
            const text = promptInput.value;
            
            // 在光标位置插入字段引用
            const newText = text.substring(0, start) + reference + text.substring(end);
            promptInput.value = newText;
            
            // 设置光标位置到插入内容之后
            const newCursorPos = start + reference.length;
            promptInput.setSelectionRange(newCursorPos, newCursorPos);
            promptInput.focus();

            // 获取唯一ID
            const uniqueIdValue = uniqueId || promptInput.dataset.aiUniqueId || promptInput.closest('[data-ai-unique-id]')?.dataset.aiUniqueId;

            // 更新可视化显示
            this.updatePromptDisplay(promptInput, uniqueIdValue);

            // 触发 input 事件
            promptInput.dispatchEvent(new Event('input', { bubbles: true }));
        }

        /**
         * 转义 HTML
         * @param {string} text - 要转义的文本
         * @returns {string}
         */
        static escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        /**
         * 简单调试日志
         * @param {string} label - 日志标签
         * @param {object} payload - 日志数据
         */
        static debugLog(label, payload = {}) {
            try {
                // 如需关闭调试日志，可将此处改为 false
                const enable = true;
                if (!enable) {
                    return;
                }
                console.debug(`[AIInputEnhancer][${label}]`, payload);
            } catch (e) {
                // 忽略日志异常
            }
        }

        /**
         * 获取保存的提示词
         * @param {string} uniqueId - 唯一ID
         * @param {HTMLElement} inputElement - 输入框元素
         * @returns {string|null} 保存的提示词，如果没有则返回 null
         */
        static getSavedPrompt(uniqueId, inputElement) {
            try {
                // 使用输入框的 name 或 id 作为存储键的一部分
                const fieldKey = inputElement.name || inputElement.id || 'default';
                const storageKey = `ai_prompt_${fieldKey}`;
                const saved = localStorage.getItem(storageKey);
                return saved ? saved : null;
            } catch (e) {
                console.warn('[AIInputEnhancer] 读取本地存储失败:', e);
                return null;
            }
        }

        /**
         * 保存提示词到本地存储
         * @param {string} uniqueId - 唯一ID
         * @param {string} prompt - 提示词
         * @param {HTMLElement} inputElement - 输入框元素
         */
        static savePrompt(uniqueId, prompt, inputElement) {
            try {
                // 如果提示词为空，不保存
                if (!prompt || !prompt.trim()) {
                    return;
                }
                
                // 使用输入框的 name 或 id 作为存储键的一部分
                const fieldKey = inputElement.name || inputElement.id || 'default';
                const storageKey = `ai_prompt_${fieldKey}`;
                localStorage.setItem(storageKey, prompt.trim());
            } catch (e) {
                console.warn('[AIInputEnhancer] 保存到本地存储失败:', e);
            }
        }

        /**
         * 防抖保存提示词
         * @param {string} uniqueId - 唯一ID
         * @param {string} prompt - 提示词
         * @param {HTMLElement} inputElement - 输入框元素
         */
        static savePromptDebounced(uniqueId, prompt, inputElement) {
            // 清除之前的定时器
            const fieldKey = inputElement.name || inputElement.id || 'default';
            const timerKey = `ai_prompt_timer_${fieldKey}`;
            
            if (window[timerKey]) {
                clearTimeout(window[timerKey]);
            }
            
            // 500ms 后保存
            window[timerKey] = setTimeout(() => {
                this.savePrompt(uniqueId, prompt, inputElement);
            }, 500);
        }

        /**
         * 获取保存的系统角色
         * @param {string} uniqueId - 唯一ID
         * @param {HTMLElement} inputElement - 输入框元素
         * @returns {string|null} 保存的系统角色
         */
        static getSavedSystemRole(uniqueId, inputElement) {
            try {
                // 使用输入框的 name 或 id 作为存储键的一部分
                const fieldKey = inputElement.name || inputElement.id || 'default';
                const storageKey = `ai_system_role_${fieldKey}`;
                const saved = localStorage.getItem(storageKey);
                return saved ? saved : null;
            } catch (e) {
                console.warn('[AIInputEnhancer] 读取系统角色失败:', e);
                return null;
            }
        }

        /**
         * 保存系统角色到本地存储
         * @param {string} uniqueId - 唯一ID
         * @param {string} systemRole - 系统角色
         * @param {HTMLElement} inputElement - 输入框元素
         */
        static saveSystemRole(uniqueId, systemRole, inputElement) {
            try {
                // 使用输入框的 name 或 id 作为存储键的一部分
                const fieldKey = inputElement.name || inputElement.id || 'default';
                const storageKey = `ai_system_role_${fieldKey}`;
                localStorage.setItem(storageKey, systemRole.trim());
                console.log('[AIInputEnhancer] 系统角色已保存:', systemRole.substring(0, 50) + '...');
            } catch (e) {
                console.warn('[AIInputEnhancer] 保存系统角色失败:', e);
            }
        }

        /**
         * 更新提示词的字段引用列表（仅显示在输入框下方，不在输入框内渲染）
         * @param {HTMLElement} promptInput - 提示词输入框
         * @param {string} [uniqueId] - 唯一ID（可选，如果不提供则从 promptInput 获取）
         */
        static updatePromptDisplay(promptInput, uniqueId) {
            if (!promptInput) return;

            // 获取唯一ID
            const uniqueIdValue = uniqueId || promptInput.dataset.aiUniqueId || promptInput.closest('[data-ai-unique-id]')?.dataset.aiUniqueId;
            if (!uniqueIdValue) {
                console.warn('[AIInputEnhancer] updatePromptDisplay: uniqueId 为空');
                return;
            }

            const variablesList = document.getElementById(`aiPromptVariablesList_${uniqueIdValue}`);
            const variablesContainer = document.getElementById(`aiPromptVariables_${uniqueIdValue}`);

            if (!variablesList || !variablesContainer) return;

            const text = promptInput.value;

            // 解析文本，识别字段引用
            const variables = new Set();

            // 匹配 {field_name} 格式
            const regex = /\{([a-zA-Z_][a-zA-Z0-9_]*)\}/g;
            let match;

            while ((match = regex.exec(text)) !== null) {
                const fieldName = match[1];
                variables.add(fieldName);
            }

            // 更新变量列表
            if (variables.size > 0) {
                variablesContainer.classList.remove('d-none');
                variablesList.innerHTML = '';

                variables.forEach(fieldName => {
                    const fieldElement = document.querySelector(`[data-field-name="${fieldName}"]`);
                    let fieldLabel = fieldName;
                    let fieldValue = '';

                    if (fieldElement) {
                        const labelElement = fieldElement.querySelector('label');
                        fieldLabel = labelElement ? labelElement.textContent.trim().replace(/\s*\*$/, '') : fieldName;
                        
                        const inputElement = fieldElement.querySelector('input, textarea, select');
                        if (inputElement) {
                            if (inputElement.type === 'checkbox' || inputElement.type === 'radio') {
                                fieldValue = inputElement.checked ? inputElement.value : '';
                            } else {
                                fieldValue = inputElement.value || '';
                            }
                        }
                    }

                    const badge = document.createElement('span');
                    badge.className = 'badge bg-info text-dark';
                    badge.style.cssText = 'font-size: 0.75rem; padding: 0.25em 0.5em; cursor: pointer; margin-right: 0.25rem;';
                    badge.innerHTML = `<strong>${this.escapeHtml(fieldLabel)}</strong> <span class="text-muted">(${this.escapeHtml(fieldName)})</span>`;
                    badge.title = `当前值: ${this.escapeHtml(fieldValue || '空')}`;
                    
                    // 点击可以跳转到对应字段
                    badge.addEventListener('click', () => {
                        if (fieldElement) {
                            const inputElement = fieldElement.querySelector('input, textarea, select');
                            if (inputElement) {
                                inputElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                inputElement.focus();
                                inputElement.style.boxShadow = '0 0 0 0.2rem rgba(13, 110, 253, 0.25)';
                                setTimeout(() => {
                                    inputElement.style.boxShadow = '';
                                }, 2000);
                            }
                        }
                    });

                    variablesList.appendChild(badge);
                });
            } else {
                variablesContainer.classList.add('d-none');
            }
        }

        /**
         * 替换提示词中的字段引用为实际值
         * @param {string} prompt - 提示词（可能包含字段引用）
         * @returns {string} 替换后的提示词
         */
        /**
         * 替换提示词中的字段引用为实际值
         * @param {string} prompt - 原始提示词
         * @returns {string} 替换后的提示词
         */
        static replaceFieldReferences(prompt) {
            if (!prompt) return prompt;

            const originalPrompt = prompt;
            let replacedCount = 0;
            const replacements = [];

            // 匹配 {field_name} 格式的引用
            const replaced = prompt.replace(/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/g, (match, fieldName) => {
                console.log(`[AIInputEnhancer] 尝试替换字段引用: {${fieldName}}`);
                
                // 方法1: 直接通过 name 属性查找输入元素（最直接的方法）
                let inputElement = document.querySelector(`input[name="${fieldName}"], textarea[name="${fieldName}"], select[name="${fieldName}"]`);
                
                // 方法2: 如果方法1找不到，通过 data-field-name 属性查找容器，再找输入元素
                if (!inputElement) {
                    const fieldElement = document.querySelector(`[data-field-name="${fieldName}"]`);
                    if (fieldElement) {
                        inputElement = fieldElement.querySelector('input, textarea, select');
                    }
                }

                // 方法3: 如果还是找不到，尝试查找所有可能的输入元素
                if (!inputElement) {
                    // 查找所有包含该字段名的元素
                    const allInputs = document.querySelectorAll('input, textarea, select');
                    for (const input of allInputs) {
                        if (input.name === fieldName || input.id === fieldName || input.getAttribute('data-field-name') === fieldName) {
                            inputElement = input;
                            break;
                        }
                    }
                }

                if (!inputElement) {
                    // 输出详细的调试信息
                    const allFields = Array.from(document.querySelectorAll('[data-field-name]')).map(el => el.getAttribute('data-field-name'));
                    const allInputs = Array.from(document.querySelectorAll('input, textarea, select')).map(el => ({
                        name: el.name,
                        id: el.id,
                        type: el.type || el.tagName
                    }));
                    
                    console.warn(`[AIInputEnhancer] 未找到字段: ${fieldName}`, {
                        availableFields: allFields,
                        availableInputs: allInputs.slice(0, 10), // 只显示前10个
                        searchByDataAttr: document.querySelectorAll(`[data-field-name]`).length,
                        searchByName: document.querySelectorAll(`[name="${fieldName}"]`).length
                    });
                    replacements.push({ fieldName, status: 'not_found', value: null });
                    return match; // 如果找不到字段，保留原引用
                }

                // 找到输入元素后，获取其父容器（用于后续查找）
                const fieldElement = inputElement.closest('[data-field-name]') || inputElement.parentElement;

                let fieldValue = '';
                if (inputElement.type === 'checkbox' || inputElement.type === 'radio') {
                    fieldValue = inputElement.checked ? inputElement.value : '';
                } else if (inputElement.tagName === 'SELECT') {
                    fieldValue = inputElement.value || '';
                } else {
                    fieldValue = inputElement.value || '';
                }

                const trimmedValue = fieldValue.trim();
                if (!trimmedValue) {
                    console.warn(`[AIInputEnhancer] 字段 ${fieldName} 的值为空`, {
                        inputElement: inputElement,
                        rawValue: fieldValue
                    });
                    replacements.push({ fieldName, status: 'empty', value: '' });
                    return match; // 如果值为空，保留原引用
                }

                replacedCount++;
                replacements.push({ 
                    fieldName, 
                    status: 'replaced', 
                    value: trimmedValue,
                    valueLength: trimmedValue.length
                });
                return trimmedValue;
            });

            // 输出替换日志（总是输出，即使没有替换）
            console.log('[AIInputEnhancer] 字段引用替换:', {
                original: originalPrompt,
                replaced: replaced,
                replacements: replacements,
                replacedCount: replacedCount,
                totalReplacements: replacements.length,
                hasChanges: originalPrompt !== replaced
            });

            // 如果有字段引用但没有成功替换，输出警告
            const hasReferences = /\{([a-zA-Z_][a-zA-Z0-9_]*)\}/g.test(originalPrompt);
            if (hasReferences && replacedCount === 0) {
                console.warn('[AIInputEnhancer] 警告：检测到字段引用但未成功替换任何字段', {
                    original: originalPrompt,
                    replaced: replaced,
                    failedReplacements: replacements.filter(r => r.status !== 'replaced')
                });
            }

            return replaced;
        }

        /**
         * 处理自动生成模式
         * @param {HTMLElement} inputElement - 输入框元素
         * @param {Object} config - 配置
         */
        static async handleAuto(inputElement, config) {
            // 如果输入框已有内容，不自动生成
            if (inputElement.value && inputElement.value.trim()) {
                return;
            }

            let prompt = this.getPrompt(inputElement, config);
            if (!prompt) {
                return;
            }

            // 替换提示词中的字段引用为实际值
            prompt = this.replaceFieldReferences(prompt);

            // 延迟一下，确保页面已完全加载
            setTimeout(async () => {
                await this.generateAndFill(inputElement, prompt, config);
            }, 500);
        }

        /**
         * 获取提示词
         * @param {HTMLElement} inputElement - 输入框元素
         * @param {Object} config - 配置
         * @returns {string}
         */
        static getPrompt(inputElement, config) {
            if (typeof config.defaultPrompt === 'function') {
                return config.defaultPrompt(inputElement);
            }
            return config.defaultPrompt || '';
        }

        /**
         * 记录完整的AI输出到控制台
         * @param {string} prompt - 提示词
         * @param {string} model - 使用的模型
         * @param {string} content - 实际内容
         * @param {string} reasoning - 思考内容（可选）
         * @param {Object} response - 完整响应对象（可选）
         */
        /**
         * 记录完整的AI输出到控制台
         * @param {string} prompt - 提示词
         * @param {string} model - 使用的模型
         * @param {string} content - 实际内容
         * @param {string} reasoning - 思考内容（可选）
         * @param {Object} response - 完整响应对象（可选）
         */
        static logAIOutput(prompt, model, content, reasoning = '', response = null) {
            console.group(`%c[AIInputEnhancer] AI 生成完成`, 'color: #0d6efd; font-weight: bold; font-size: 14px;');
            
            // 基本信息
            console.log('%c📝 提示词:', 'color: #6c757d; font-weight: bold;', prompt);
            console.log('%c🤖 模型:', 'color: #6c757d; font-weight: bold;', model);
            console.log('%c📊 统计:', 'color: #6c757d; font-weight: bold;', {
                内容长度: content.length,
                思考长度: reasoning.length,
                总长度: content.length + reasoning.length
            });
            
            // 思考过程
            if (reasoning) {
                console.group('%c💭 思考过程:', 'color: #ffc107; font-weight: bold;');
                console.log('%c' + reasoning, 'color: #856404; white-space: pre-wrap; word-wrap: break-word;');
                console.groupEnd();
            }
            
            // 生成内容
            console.group('%c✨ 生成内容:', 'color: #198754; font-weight: bold;');
            console.log('%c' + content, 'color: #0f5132; white-space: pre-wrap; word-wrap: break-word;');
            console.groupEnd();
            
            // 完整响应（如果提供）
            if (response && typeof response === 'object') {
                console.group('%c📦 完整响应:', 'color: #6c757d; font-weight: bold;');
                console.log(response);
                console.groupEnd();
            }
            
            // 完整输出（合并后的完整内容）
            const fullOutput = reasoning ? `[思考过程]\n${reasoning}\n\n[生成内容]\n${content}` : content;
            console.group('%c📄 完整输出:', 'color: #0d6efd; font-weight: bold;');
            console.log('%c' + fullOutput, 'color: #212529; white-space: pre-wrap; word-wrap: break-word;');
            console.groupEnd();
            
            console.groupEnd();
        }

        /**
         * 生成内容到模态框预览输入框（弹窗模式专用）
         * @param {HTMLElement} inputElement - 目标输入框元素
         * @param {string} prompt - 提示词
         * @param {Object} config - 配置
         * @param {HTMLElement} modal - 模态框元素
         * @param {bootstrap.Modal} bsModal - Bootstrap 模态框实例
         */
        static async generateToModal(inputElement, prompt, config, modal, bsModal) {
            const uniqueId = modal.dataset.aiUniqueId;
            const sendBtn = modal.querySelector(`#aiSendBtn_${uniqueId}`);
            const useResultBtn = modal.querySelector(`#aiUseResultBtn_${uniqueId}`);

            // 获取 AI 配置
            const aiConfig = config.aiConfig || window.AI_CONFIG;

            // 检查 AI 配置是否有效
            if (!window.AIConfigFactory || !window.AIConfigFactory.isValid(aiConfig)) {
                const message = 'AI 服务未配置，请先在站点设置中配置 AI Token';
                this.showError(message);
                if (sendBtn) sendBtn.disabled = false;
                return;
            }

            const startTime = Date.now();

            try {
                // 获取 AI 服务实例
                const aiService = window.AIConfigFactory 
                    ? window.AIConfigFactory.getAIService(config.aiConfig)
                    : (config.aiConfig ? window.AIServiceFactory.create(config.aiConfig) : window.aiService);
                
                if (!aiService) {
                    throw new Error('无法创建 AI 服务实例');
                }

                // 根据是否启用思考模式选择模型
                const model = this.getModel(config, aiConfig);

                // 构建思考参数（仅智谱AI支持）
                const thinkingParams = this.buildThinkingParams(config, aiConfig);

                // 验证 prompt 不为空
                const trimmedPrompt = prompt.trim();
                if (!trimmedPrompt) {
                    throw new Error('提示词不能为空');
                }

                // 获取对话历史
                const chatHistory = this.getChatHistory(modal, uniqueId);
                
                // 获取系统角色（从配置中获取，如果没有则使用默认值）
                const systemRole = config.systemRole || AIInputEnhancer.DEFAULT_SYSTEM_ROLE;
                
                // 构建消息数组（包含历史消息和当前用户消息）
                const messages = [
                    {
                        role: 'system',
                        content: systemRole.trim()
                    },
                    // 添加历史消息（过滤掉空内容）
                    ...chatHistory
                        .filter(msg => msg && msg.content && msg.content.trim())
                        .map(msg => ({
                            role: msg.role,
                            content: msg.content.trim()
                        })),
                    // 添加当前用户消息
                    {
                        role: 'user',
                        content: trimmedPrompt
                    }
                ];

                // 验证 messages 格式
                if (messages.length < 2) {
                    throw new Error('消息数组格式错误：至少需要 system 和 user 消息');
                }

                // 验证所有消息都有有效的 role 和 content
                for (const msg of messages) {
                    if (!msg.role || !msg.content || typeof msg.content !== 'string' || !msg.content.trim()) {
                        console.error('[AIInputEnhancer] 无效的消息格式:', msg);
                        throw new Error(`消息格式错误：role=${msg.role}, content=${msg.content}`);
                    }
                }

                // 记录请求信息
                console.log('[AIInputEnhancer] 开始生成内容（弹窗模式）', {
                    model,
                    stream: config.stream,
                    thinking: config.thinking,
                    thinkingParams: thinkingParams,
                    provider: aiConfig?.provider,
                    promptLength: trimmedPrompt.length,
                    historyLength: chatHistory.length,
                    messagesCount: messages.length,
                    messages: messages.map(msg => ({
                        role: msg.role,
                        contentLength: msg.content.length,
                        contentPreview: msg.content.substring(0, 50) + (msg.content.length > 50 ? '...' : '')
                    }))
                });

                // 如果启用流式输出，使用流式方法
                if (config.stream) {
                    await this.generateToModalStream(inputElement, prompt, config, modal, bsModal, aiService, model, aiConfig, thinkingParams, messages, startTime);
                    return;
                }

                // 非流式输出
                const response = await aiService.chatCompletions({
                    model: model,
                    messages: messages,
                    temperature: config.temperature,
                    max_tokens: config.maxTokens,
                    stream: false,
                    ...(thinkingParams && { thinking: thinkingParams })
                });

                // 提取生成的内容
                const generatedContent = response.choices?.[0]?.message?.content || '';
                const reasoningContent = response.choices?.[0]?.message?.reasoning_content || '';
                
                if (!generatedContent) {
                    throw new Error('AI 未返回有效内容');
                }

                const endTime = Date.now();
                const duration = ((endTime - startTime) / 1000).toFixed(1);

                // 提取 token 使用信息
                const usage = response.usage || {};
                const promptTokens = usage.prompt_tokens || 0;
                const completionTokens = usage.completion_tokens || 0;
                const totalTokens = usage.total_tokens || 0;

                // 记录完整输出到控制台
                this.logAIOutput(prompt, model, generatedContent, reasoningContent, response);

                // 添加 AI 消息到对话历史
                this.addAIMessage(modal, uniqueId, generatedContent, reasoningContent, false);

                // 更新统计信息
                this.updateModalStats(modal, uniqueId, model, totalTokens, duration);

                // 显示使用按钮
                if (useResultBtn) {
                    useResultBtn.classList.remove('d-none');
                }

                // 保存对话历史到本地存储
                this.saveChatHistory(modal, uniqueId);

            } catch (error) {
                console.error('[AIInputEnhancer] 生成失败:', error);
                
                // 添加错误消息到对话历史
                this.addAIMessage(modal, uniqueId, `❌ 生成失败：${error.message || '未知错误，请稍后重试'}`, '', false);

                // 恢复发送按钮
                if (sendBtn) sendBtn.disabled = false;
            }
        }

        /**
         * 流式生成内容到模态框预览输入框（弹窗模式专用）
         * @param {HTMLElement} inputElement - 目标输入框元素
         * @param {string} prompt - 提示词
         * @param {Object} config - 配置
         * @param {HTMLElement} modal - 模态框元素
         * @param {bootstrap.Modal} bsModal - Bootstrap 模态框实例
         * @param {AIService} aiService - AI 服务实例
         * @param {string} model - 模型名称
         * @param {Object} aiConfig - AI 配置
         * @param {Object} thinkingParams - 思考参数
         */
        static async generateToModalStream(inputElement, prompt, config, modal, bsModal, aiService, model, aiConfig, thinkingParams, messages, startTime) {
            const uniqueId = modal.dataset.aiUniqueId;
            const sendBtn = modal.querySelector(`#aiSendBtn_${uniqueId}`);
            const useResultBtn = modal.querySelector(`#aiUseResultBtn_${uniqueId}`);

            let accumulatedContent = '';
            let accumulatedReasoning = '';
            let isCompleted = false;
            let messageElement = null; // 用于流式更新的消息元素

            // 创建 AI 消息占位符（用于流式输出）
            messageElement = this.addAIMessage(modal, uniqueId, '', '', true);

            // 流式输出回调
            const onChunk = (chunk) => {
                try {
                    const choice = chunk.choices?.[0];
                    if (!choice) {
                        return;
                    }

                    // 检查是否完成
                    const finishReason = choice.finish_reason;
                    if (finishReason && finishReason !== null && finishReason !== undefined) {
                        isCompleted = true;
                        // 完成消息，移除流式光标
                        this.completeAIMessage(messageElement);
                        return;
                    }

                    // 处理增量内容
                    const delta = choice.delta;
                    if (!delta) {
                        return;
                    }

                    // 处理实际内容
                    if (delta.content !== undefined && delta.content !== null) {
                        accumulatedContent += delta.content;
                        // 实时更新消息内容
                        this.updateAIMessage(messageElement, accumulatedContent, accumulatedReasoning);
                    }

                    // 处理思考内容
                    if (config.thinking && delta.reasoning_content !== undefined && delta.reasoning_content !== null) {
                        accumulatedReasoning += delta.reasoning_content;
                        // 实时更新思考内容
                        this.updateAIMessage(messageElement, accumulatedContent, accumulatedReasoning);
                    }
                } catch (error) {
                    console.error('[AIInputEnhancer] 处理流式数据失败:', error);
                }
            };

            try {
                // 验证 prompt 不为空
                const trimmedPrompt = prompt.trim();
                if (!trimmedPrompt) {
                    throw new Error('提示词不能为空');
                }

                // 获取系统角色（从配置中获取，如果没有则使用默认值）
                const systemRole = config.systemRole || AIInputEnhancer.DEFAULT_SYSTEM_ROLE;

                // 确保 messages 格式正确（过滤空内容）
                let finalMessages = messages;
                if (!finalMessages || finalMessages.length === 0) {
                    finalMessages = [
                        {
                            role: 'system',
                            content: systemRole.trim()
                        },
                        {
                            role: 'user',
                            content: trimmedPrompt
                        }
                    ];
                } else {
                    // 过滤掉空内容的消息
                    finalMessages = finalMessages
                        .filter(msg => msg && msg.content && msg.content.trim())
                        .map(msg => ({
                            role: msg.role,
                            content: msg.content.trim()
                        }));
                    
                    // 确保最后一条是当前用户消息
                    if (finalMessages.length === 0 || finalMessages[finalMessages.length - 1].role !== 'user') {
                        finalMessages.push({
                            role: 'user',
                            content: trimmedPrompt
                        });
                    }
                }

                // 验证 messages 格式
                if (finalMessages.length < 2) {
                    throw new Error('消息数组格式错误：至少需要 system 和 user 消息');
                }

                // 验证所有消息都有有效的 role 和 content
                for (const msg of finalMessages) {
                    if (!msg.role || !msg.content || typeof msg.content !== 'string' || !msg.content.trim()) {
                        console.error('[AIInputEnhancer] 无效的消息格式:', msg);
                        throw new Error(`消息格式错误：role=${msg.role}, content=${msg.content}`);
                    }
                }

                // 构建请求参数（使用传入的 messages，包含历史消息）
                const requestParams = {
                    model: model,
                    messages: finalMessages,
                    temperature: config.temperature,
                    max_tokens: config.maxTokens,
                    stream: true, // 明确设置为 true
                    ...(thinkingParams && { thinking: thinkingParams }),
                    onChunk: onChunk // 流式回调
                };

                // 记录请求参数（用于调试）
                console.log('[AIInputEnhancer] 流式请求参数（弹窗模式）', {
                    model: requestParams.model,
                    stream: requestParams.stream,
                    temperature: requestParams.temperature,
                    max_tokens: requestParams.max_tokens,
                    thinking: requestParams.thinking,
                    messagesCount: finalMessages.length,
                    messages: finalMessages.map(msg => ({
                        role: msg.role,
                        contentLength: msg.content.length,
                        contentPreview: msg.content.substring(0, 50) + (msg.content.length > 50 ? '...' : '')
                    })),
                    onChunk: '[Function]' // 不打印函数
                });

                // 调用流式 API
                await aiService.chatCompletions(requestParams);

                // 等待流式输出完成（通过检查 isCompleted 标志）
                // 注意：流式请求是异步的，onChunk 回调会持续更新内容
                let waitCount = 0;
                const maxWait = 600; // 最多等待60秒（600 * 100ms）
                while (!isCompleted && waitCount < maxWait) {
                    await new Promise(resolve => setTimeout(resolve, 100));
                    waitCount++;
                }

                if (!isCompleted) {
                    console.warn('[AIInputEnhancer] 流式输出超时，强制完成');
                    isCompleted = true;
                    this.completeAIMessage(messageElement);
                }

                const endTime = Date.now();
                const duration = ((endTime - startTime) / 1000).toFixed(1);

                // 记录完整输出到控制台
                this.logAIOutput(prompt, model, accumulatedContent, accumulatedReasoning, {
                    stream: true,
                    contentLength: accumulatedContent.length,
                    reasoningLength: accumulatedReasoning.length,
                    provider: aiConfig?.provider
                });

                // 估算 token 使用（流式输出通常不返回 usage，使用内容长度估算）
                const estimatedTokens = Math.ceil((prompt.length + accumulatedContent.length) / 4);

                // 更新统计信息
                this.updateModalStats(modal, uniqueId, model, estimatedTokens, duration);

                // 显示使用按钮
                if (useResultBtn) {
                    useResultBtn.classList.remove('d-none');
                }

                // 保存对话历史到本地存储
                this.saveChatHistory(modal, uniqueId);

            } catch (error) {
                console.error('[AIInputEnhancer] 流式生成失败:', error);
                
                // 更新消息显示错误
                if (messageElement) {
                    messageElement.contentDiv.textContent = `❌ 生成失败：${error.message || '未知错误，请稍后重试'}`;
                    this.completeAIMessage(messageElement);
                } else {
                    // 如果消息元素不存在，创建新的错误消息
                    this.addAIMessage(modal, uniqueId, `❌ 生成失败：${error.message || '未知错误，请稍后重试'}`, '', false);
                }

                // 恢复发送按钮
                if (sendBtn) sendBtn.disabled = false;
            }
        }

        /**
         * 生成内容并填充
         * @param {HTMLElement} inputElement - 输入框元素
         * @param {string} prompt - 提示词
         * @param {Object} config - 配置
         * @param {bootstrap.Modal} [bsModal] - Bootstrap 模态框实例（可选）
         */
        static async generateAndFill(inputElement, prompt, config, bsModal = null) {
            // 获取 AI 配置
            const aiConfig = config.aiConfig || window.AI_CONFIG;

            // 检查 AI 配置是否有效
            if (!window.AIConfigFactory || !window.AIConfigFactory.isValid(aiConfig)) {
                const message = 'AI 服务未配置，请先在站点设置中配置 AI Token';
                this.showError(message);
                if (bsModal) bsModal.hide();
                return;
            }

            // 生成前回调
            if (config.onBeforeGenerate && config.onBeforeGenerate(inputElement) === false) {
                return;
            }

            // 显示加载状态
            const loadingState = this.showLoading(inputElement, bsModal);

            try {
                // 获取 AI 服务实例
                const aiService = window.AIConfigFactory 
                    ? window.AIConfigFactory.getAIService(config.aiConfig)
                    : (config.aiConfig ? window.AIServiceFactory.create(config.aiConfig) : window.aiService);
                
                if (!aiService) {
                    throw new Error('无法创建 AI 服务实例');
                }

                // 根据是否启用思考模式选择模型
                const model = this.getModel(config, aiConfig);

                // 构建思考参数（仅智谱AI支持）
                const thinkingParams = this.buildThinkingParams(config, aiConfig);

                // 记录请求信息
                console.log('[AIInputEnhancer] 开始生成内容', {
                    model,
                    mode: config.mode,
                    stream: config.stream,
                    thinking: config.thinking,
                    thinkingParams: thinkingParams,
                    provider: aiConfig?.provider,
                    promptLength: prompt.length
                });

                // 如果启用流式输出
                if (config.stream && (config.mode === 'one-click' || config.mode === 'auto')) {
                    await this.generateAndFillStream(inputElement, prompt, config, aiService, loadingState, bsModal, model, aiConfig, thinkingParams);
                } else {
                    // 非流式输出
                    const response = await aiService.chatCompletions({
                        model: model,
                        messages: [
                            {
                                role: 'system',
                                content: '你是一个专业的内容创作助手，擅长根据用户需求生成高质量的内容。'
                            },
                            {
                                role: 'user',
                                content: prompt
                            }
                        ],
                        temperature: config.temperature,
                        max_tokens: config.maxTokens,
                        stream: false,
                        ...(thinkingParams && { thinking: thinkingParams })
                    });

                    // 提取生成的内容
                    const generatedContent = response.choices?.[0]?.message?.content || '';
                    const reasoningContent = response.choices?.[0]?.message?.reasoning_content || '';
                    
                    if (!generatedContent) {
                        throw new Error('AI 未返回有效内容');
                    }

                    // 记录完整输出到控制台
                    this.logAIOutput(prompt, model, generatedContent, reasoningContent, response);

                    // 填充到输入框
                    inputElement.value = generatedContent;
                    
                    // 触发 input 事件，确保表单验证等能正常工作
                    inputElement.dispatchEvent(new Event('input', { bubbles: true }));
                    inputElement.dispatchEvent(new Event('change', { bubbles: true }));

                    // 隐藏加载状态
                    this.hideLoading(loadingState, bsModal);

                    // 生成后回调
                    if (config.onAfterGenerate) {
                        config.onAfterGenerate(inputElement, generatedContent);
                    }

                    // 显示成功提示
                    this.showSuccess('内容生成成功');
                }

            } catch (error) {
                console.error('[AIInputEnhancer] 生成失败:', error);
                
                // 隐藏加载状态
                this.hideLoading(loadingState, bsModal);

                // 错误处理
                const errorMessage = error.message || '生成失败，请稍后重试';
                if (config.onError) {
                    config.onError(error, inputElement);
                } else {
                    this.showError(errorMessage);
                }
            }
        }

        /**
         * 获取模型名称（使用 AIConfigFactory）
         * @param {Object} config - 配置
         * @param {Object} [aiConfig] - 可选的 AI 配置
         * @returns {string}
         */
        static getModel(config, aiConfig = null) {
            if (window.AIConfigFactory) {
                return window.AIConfigFactory.getModel(config, aiConfig);
            }
            
            // 降级处理：如果 AIConfigFactory 不存在
            if (config.aiModel) {
                return config.aiModel;
            }
            const configObj = aiConfig || window.AI_CONFIG;
            return configObj?.text_model || 'glm-z1-flash';
        }

        /**
         * 构建思考参数（使用 AIConfigFactory，仅智谱AI支持）
         * @param {Object} config - 配置
         * @param {Object} [aiConfig] - 可选的 AI 配置
         * @returns {Object|null}
         */
        static buildThinkingParams(config, aiConfig = null) {
            if (window.AIConfigFactory) {
                return window.AIConfigFactory.buildThinkingParams(config, aiConfig);
            }
            return null;
        }

        /**
         * 流式生成内容并填充
         * @param {HTMLElement} inputElement - 输入框元素
         * @param {string} prompt - 提示词
         * @param {Object} config - 配置
         * @param {AIService} aiService - AI 服务实例
         * @param {Object} loadingState - 加载状态对象
         * @param {bootstrap.Modal} [bsModal] - Bootstrap 模态框实例（可选）
         * @param {string} [model] - 模型名称（可选）
         * @param {Object} [aiConfig] - AI 配置（可选）
         * @param {Object} [thinkingParams] - 思考参数（可选）
         */
        static async generateAndFillStream(inputElement, prompt, config, aiService, loadingState, bsModal = null, model = null, aiConfig = null, thinkingParams = null) {
            let accumulatedContent = ''; // 累积的实际内容
            let accumulatedReasoning = ''; // 累积的思考内容
            let isFirstChunk = true;
            let isCompleted = false;
            let lastUpdateTime = 0;
            const UPDATE_THROTTLE = 50; // 节流时间（毫秒）

            // 清空输入框
            inputElement.value = '';

            // 更新输入框内容的辅助函数（带节流）
            const updateInputValue = (content, reasoning = '') => {
                const now = Date.now();
                if (now - lastUpdateTime < UPDATE_THROTTLE && !isCompleted) {
                    // 节流处理，避免更新过于频繁
                    return;
                }
                lastUpdateTime = now;

                // 根据配置决定显示什么内容
                let displayContent = content;
                if (config.thinking && config.showThinking && reasoning) {
                    // 如果启用显示思考过程，同时显示思考内容和实际内容
                    // 格式：思考过程（用标记）+ 实际内容
                    displayContent = `[思考: ${reasoning}]\n\n${content}`;
                }

                inputElement.value = displayContent;
                
                // 触发 input 事件
                inputElement.dispatchEvent(new Event('input', { bubbles: true }));
            };

            // 完成流式输出的处理
            const completeStream = () => {
                if (isCompleted) return;
                isCompleted = true;

                // 最终更新一次实际内容（思考内容已单独显示）
                updateInputValue(accumulatedContent, '');

                // 如果启用思考模式，确保思考内容显示区域可见
                if (config.thinking && config.showThinking && accumulatedReasoning) {
                    this.updateThinkingDisplay(inputElement, accumulatedReasoning);
                }

                // 获取 AI 配置（如果未传入）
                const aiConfigObj = aiConfig || config.aiConfig || window.AI_CONFIG;

                // 记录完整输出到控制台
                const modelName = model || this.getModel(config, aiConfigObj);
                this.logAIOutput(prompt, modelName, accumulatedContent, accumulatedReasoning, {
                    stream: true,
                    contentLength: accumulatedContent.length,
                    reasoningLength: accumulatedReasoning.length,
                    provider: aiConfigObj?.provider
                });

                // 触发 change 事件
                inputElement.dispatchEvent(new Event('change', { bubbles: true }));
                
                // 隐藏加载状态
                this.hideLoading(loadingState, bsModal);

                // 生成后回调（传递实际内容，不包含思考内容）
                if (config.onAfterGenerate) {
                    config.onAfterGenerate(inputElement, accumulatedContent);
                }

                // 显示成功提示
                this.showSuccess('内容生成成功');
            };

            // 流式输出回调
            const onChunk = (chunk) => {
                try {
                    // 智谱AI 流式响应格式
                    const choice = chunk.choices?.[0];
                    if (!choice) {
                        return;
                    }

                    // 检查是否完成
                    const finishReason = choice.finish_reason;
                    if (finishReason && finishReason !== null && finishReason !== undefined) {
                        // 流式输出完成
                        completeStream();
                        return;
                    }

                    // 处理增量内容
                    const delta = choice.delta;
                    if (!delta) {
                        return;
                    }

                    let contentUpdated = false;
                    let reasoningUpdated = false;

                    // 处理实际内容（content）
                    if (delta.content !== undefined && delta.content !== null) {
                        const content = delta.content;
                        accumulatedContent += content;
                        contentUpdated = true;
                        isFirstChunk = false;
                    }

                    // 处理思考内容（reasoning_content）- 仅在启用思考模式时处理
                    if (config.thinking && delta.reasoning_content !== undefined && delta.reasoning_content !== null) {
                        const reasoning = delta.reasoning_content;
                        accumulatedReasoning += reasoning;
                        reasoningUpdated = true;
                        
                        // 如果配置了显示思考过程，可以在这里处理
                        if (config.showThinking) {
                            // 可以在这里将思考内容也显示到输入框或单独的区域
                            console.debug('[AIInputEnhancer] 思考内容:', reasoning);
                        }
                    }

                    // 更新思考内容显示（如果启用）
                    if (reasoningUpdated && config.thinking && config.showThinking) {
                        this.updateThinkingDisplay(inputElement, accumulatedReasoning);
                    }

                    // 调用流式回调（在更新输入框之前调用，让回调可以自定义显示内容）
                    let customDisplayContent = null;
                    if (config.onStreamChunk && typeof config.onStreamChunk === 'function') {
                        const result = config.onStreamChunk(chunk, accumulatedContent, accumulatedReasoning);
                        // 如果回调返回字符串，使用返回的内容作为显示内容
                        if (typeof result === 'string') {
                            customDisplayContent = result;
                        }
                    }

                    // 如果有实际内容更新，更新输入框（思考内容已单独显示）
                    if (contentUpdated) {
                        // 如果回调返回了自定义显示内容，使用回调的内容；否则只显示实际内容
                        if (customDisplayContent !== null) {
                            inputElement.value = customDisplayContent;
                            inputElement.dispatchEvent(new Event('input', { bubbles: true }));
                        } else {
                            // 只显示实际内容，思考内容已在上方单独显示
                            updateInputValue(accumulatedContent, '');
                        }
                    }

                } catch (error) {
                    console.error('[AIInputEnhancer] 处理流式数据失败:', error);
                }
            };

            try {
                // 获取模型名称
                const modelName = model || this.getModel(config);

                // 构建思考参数
                const thinkingParams = this.buildThinkingParams(config);

                // 调用流式 API
                await aiService.chatCompletions({
                    model: modelName,
                    messages: [
                        {
                            role: 'system',
                            content: AIInputEnhancer.DEFAULT_SYSTEM_ROLE
                        },
                        {
                            role: 'user',
                            content: prompt
                        }
                    ],
                    temperature: config.temperature,
                    max_tokens: config.maxTokens,
                    stream: true,
                    ...(thinkingParams && { thinking: thinkingParams }),
                    onChunk: onChunk
                });

                // 如果流式输出正常结束，确保完成处理
                if (!isCompleted) {
                    completeStream();
                }
            } catch (error) {
                // 如果出错，确保完成处理
                if (!isCompleted) {
                    isCompleted = true;
                    this.hideLoading(loadingState, bsModal);
                    throw error;
                }
            }
        }

        /**
         * 创建模态框
         * @param {string} modalId - 模态框 ID
         * @param {Object} config - 配置
         * @param {string} uniqueId - 唯一ID（用于生成子元素ID）
         * @returns {HTMLElement}
         */
        static createModal(modalId, config, uniqueId) {
            const modal = document.createElement('div');
            modal.id = modalId;
            modal.className = 'modal fade';
            modal.setAttribute('tabindex', '-1');
            modal.setAttribute('aria-labelledby', `${modalId}Label`);
            modal.setAttribute('aria-hidden', 'true');
            modal.dataset.aiUniqueId = uniqueId; // 存储唯一ID

            modal.innerHTML = `
                <div class="modal-dialog modal-xl" style="max-width: 95vw;">
                    <div class="modal-content" style="display: flex; flex-direction: column; height: 90vh; max-height: 900px;">
                        <div class="modal-header" style="flex-shrink: 0; border-bottom: 1px solid #dee2e6;">
                            <div class="d-flex align-items-center justify-content-between w-100">
                                <h5 class="modal-title mb-0" id="${modalId}Label">
                                    <i class="bi bi-robot me-2"></i>${config.modalTitle}
                                </h5>
                                <div class="d-flex align-items-center gap-2">
                                    <div id="aiStats_${uniqueId}" class="d-flex align-items-center gap-2 text-muted" style="font-size: 0.875rem;">
                                        <span id="aiModelInfo_${uniqueId}" class="badge bg-secondary"></span>
                                        <span id="aiTokenInfo_${uniqueId}" class="badge bg-info"></span>
                                        <span id="aiTimeInfo_${uniqueId}" class="badge bg-success"></span>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-danger" id="aiClearChatBtn_${uniqueId}" title="清空对话">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="关闭"></button>
                                </div>
                            </div>
                        </div>
                        <div class="modal-body" style="flex: 1; overflow: hidden; display: flex; padding: 0;">
                            <!-- 左侧：对话区域 -->
                            <div style="flex: 1; display: flex; flex-direction: column; overflow: hidden; border-right: 1px solid #dee2e6;">
                                <!-- 对话历史区域 -->
                                <div id="aiChatHistory_${uniqueId}" style="flex: 1; overflow-y: auto; padding: 1rem; min-height: 0;">
                                    <div class="text-center text-muted py-4">
                                        <i class="bi bi-chat-dots" style="font-size: 2rem; opacity: 0.3;"></i>
                                        <p class="mt-2 mb-0">开始与 AI 对话，输入提示词后点击"发送"按钮</p>
                                    </div>
                                </div>
                                
                                <!-- 输入区域 -->
                                <div style="flex-shrink: 0; border-top: 1px solid #dee2e6; padding: 1rem; background-color: #f8f9fa;">
                                    <div class="position-relative">
                                        <textarea 
                                            id="aiPromptInput_${uniqueId}" 
                                            class="form-control" 
                                            rows="3" 
                                            placeholder="输入消息... 输入 @ 可引用表单字段，按 Enter 发送（Shift+Enter 换行）"
                                            style="resize: vertical; min-height: 80px;"
                                            spellcheck="false"
                                            data-ai-unique-id="${uniqueId}"
                                        ></textarea>
                                        <div id="aiFieldAutocomplete_${uniqueId}" class="dropdown-menu" style="display: none; position: absolute; z-index: 1050; max-height: 300px; overflow-y: auto; min-width: 300px; margin-top: 2px;"></div>
                                    </div>
                                    <div id="aiPromptVariables_${uniqueId}" class="mt-2 d-none">
                                        <small class="text-muted d-block mb-1">
                                            <i class="bi bi-tags me-1"></i>已引用的字段：
                                        </small>
                                        <div id="aiPromptVariablesList_${uniqueId}" class="d-flex flex-wrap gap-1"></div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- 右侧：设置区域 -->
                            <div style="width: 320px; flex-shrink: 0; display: flex; flex-direction: column; background-color: #f8f9fa; border-left: 1px solid #dee2e6; overflow-y: auto;">
                                <div style="padding: 1rem;">
                                    <h6 class="fw-bold mb-3 text-primary" style="font-size: 0.875rem;">
                                        <i class="bi bi-gear me-2"></i>设置
                                    </h6>
                                    
                                    <!-- 思考模式和流式输出 -->
                                    <div class="mb-3">
                                        <div class="form-check form-switch mb-2">
                                            <input class="form-check-input" type="checkbox" id="aiThinkingMode_${uniqueId}" checked>
                                            <label class="form-check-label" for="aiThinkingMode_${uniqueId}" style="font-size: 0.875rem;">
                                                <strong>思考模式</strong>
                                                <small class="text-muted d-block" style="font-size: 0.75rem; margin-top: 0.25rem;">（仅智谱AI支持）</small>
                                            </label>
                                        </div>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="aiStreamMode_${uniqueId}" checked>
                                            <label class="form-check-label" for="aiStreamMode_${uniqueId}" style="font-size: 0.875rem;">
                                                <strong>流式输出</strong>
                                                <small class="text-muted d-block" style="font-size: 0.75rem; margin-top: 0.25rem;">（实时显示生成内容）</small>
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <!-- 系统角色设置 -->
                                    <div class="mb-3">
                                        <label for="aiSystemRole_${uniqueId}" class="form-label" style="font-size: 0.875rem; margin-bottom: 0.5rem; font-weight: 600;">
                                            系统角色
                                        </label>
                                        <textarea 
                                            id="aiSystemRole_${uniqueId}" 
                                            class="form-control form-control-sm" 
                                            rows="6" 
                                            placeholder="你是一个专业的内容创作助手，擅长根据用户需求生成高质量的内容。"
                                            style="resize: vertical; font-size: 0.875rem;"
                                            spellcheck="false"
                                        ></textarea>
                                        <small class="form-text text-muted" style="font-size: 0.75rem; margin-top: 0.25rem; display: block;">
                                            定义 AI 的角色和行为，修改后会自动保存。
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer" style="flex-shrink: 0; border-top: 1px solid #dee2e6;">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">关闭</button>
                            <button type="button" class="btn btn-success d-none" id="aiUseResultBtn_${uniqueId}">
                                <i class="bi bi-check-lg me-1"></i>使用最后回复
                            </button>
                            <button type="button" class="btn btn-primary" id="aiSendBtn_${uniqueId}">
                                <i class="bi bi-send me-1"></i>发送
                            </button>
                        </div>
                    </div>
                </div>
            `;

            return modal;
        }

        /**
         * 显示加载状态
         * @param {HTMLElement} inputElement - 输入框元素
         * @param {bootstrap.Modal} [bsModal] - 模态框实例
         * @returns {Object} 加载状态对象
         */
        static showLoading(inputElement, bsModal = null) {
            if (bsModal) {
                const resultDiv = bsModal._element.querySelector('#aiGenerateResult');
                if (resultDiv) {
                    resultDiv.classList.remove('d-none');
                }
            }

            // 禁用输入框
            inputElement.disabled = true;
            inputElement.style.opacity = '0.6';

            return { inputElement, bsModal };
        }

        /**
         * 隐藏加载状态
         * @param {Object} loadingState - 加载状态对象
         * @param {bootstrap.Modal} [bsModal] - 模态框实例
         */
        static hideLoading(loadingState, bsModal = null) {
            if (loadingState.inputElement) {
                loadingState.inputElement.disabled = false;
                loadingState.inputElement.style.opacity = '1';
            }

            if (bsModal) {
                const resultDiv = bsModal._element.querySelector('#aiGenerateResult');
                if (resultDiv) {
                    resultDiv.classList.add('d-none');
                }
            }
        }

        /**
         * 显示成功提示
         * @param {string} message - 提示消息
         */
        static showSuccess(message) {
            if (window.Admin && typeof window.Admin.utils?.showToast === 'function') {
                window.Admin.utils.showToast('success', message);
            } else {
                console.log(`[AIInputEnhancer] ${message}`);
            }
        }

        /**
         * 显示错误提示
         * @param {string} message - 错误消息
         */
        static showError(message) {
            if (window.Admin && typeof window.Admin.utils?.showToast === 'function') {
                window.Admin.utils.showToast('danger', message);
            } else {
                console.error(`[AIInputEnhancer] ${message}`);
                alert(message);
            }
        }

        /**
         * 添加用户消息到对话历史
         * @param {HTMLElement} modal - 模态框元素
         * @param {string} uniqueId - 唯一ID
         * @param {string} content - 消息内容
         */
        static addUserMessage(modal, uniqueId, content) {
            const chatHistory = modal.querySelector(`#aiChatHistory_${uniqueId}`);
            if (!chatHistory) return;

            // 移除空状态提示
            const emptyState = chatHistory.querySelector('.text-center.text-muted');
            if (emptyState) {
                emptyState.remove();
            }

            const messageDiv = document.createElement('div');
            messageDiv.className = 'mb-3';
            messageDiv.style.cssText = 'display: flex; justify-content: flex-end;';
            messageDiv.dataset.aiMessage = 'user';
            messageDiv.dataset.aiMessageId = `ai_msg_${Date.now()}`;
            
            const messageContent = document.createElement('div');
            messageContent.className = 'card';
            messageContent.style.cssText = 'max-width: 80%; background-color: #0d6efd; color: white; border-radius: 1rem 1rem 0.25rem 1rem;';
            
            const messageBody = document.createElement('div');
            messageBody.className = 'card-body';
            messageBody.style.cssText = 'padding: 0.75rem 1rem;';
            messageBody.innerHTML = `<div style="white-space: pre-wrap; word-wrap: break-word;">${this.escapeHtml(content)}</div>`;
            
            messageContent.appendChild(messageBody);
            messageDiv.appendChild(messageContent);
            chatHistory.appendChild(messageDiv);
            
            // 滚动到底部
            chatHistory.scrollTop = chatHistory.scrollHeight;
        }

        /**
         * 添加 AI 消息到对话历史
         * @param {HTMLElement} modal - 模态框元素
         * @param {string} uniqueId - 唯一ID
         * @param {string} content - 消息内容
         * @param {string} reasoning - 思考内容（可选）
         * @param {boolean} isStreaming - 是否正在流式输出
         * @returns {HTMLElement} 返回消息元素，用于流式更新
         */
        static addAIMessage(modal, uniqueId, content = '', reasoning = '', isStreaming = false) {
            const chatHistory = modal.querySelector(`#aiChatHistory_${uniqueId}`);
            if (!chatHistory) return null;

            // 移除空状态提示
            const emptyState = chatHistory.querySelector('.text-center.text-muted');
            if (emptyState) {
                emptyState.remove();
            }

            const messageDiv = document.createElement('div');
            messageDiv.className = 'mb-3';
            messageDiv.style.cssText = 'display: flex; justify-content: flex-start;';
            messageDiv.dataset.aiMessage = 'assistant';
            messageDiv.dataset.aiMessageId = `ai_msg_${Date.now()}`;
            
            const messageContent = document.createElement('div');
            messageContent.className = 'card';
            messageContent.style.cssText = 'max-width: 80%; background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 1rem 1rem 1rem 0.25rem;';
            
            const messageBody = document.createElement('div');
            messageBody.className = 'card-body';
            messageBody.style.cssText = 'padding: 0.75rem 1rem;';
            
            // 思考内容区域（如果启用）
            let thinkingDiv = null;
            const thinkingModeCheckbox = modal.querySelector(`#aiThinkingMode_${uniqueId}`);
            if (thinkingModeCheckbox && thinkingModeCheckbox.checked && reasoning) {
                thinkingDiv = document.createElement('div');
                thinkingDiv.className = 'mb-2 p-2';
                thinkingDiv.style.cssText = 'background-color: #fff3cd; border: 1px solid #ffc107; border-radius: 0.375rem; font-size: 0.875rem; color: #856404;';
                thinkingDiv.innerHTML = `<div style="white-space: pre-wrap; word-wrap: break-word;"><strong>💭 思考过程：</strong><br>${this.escapeHtml(reasoning)}</div>`;
                messageBody.appendChild(thinkingDiv);
            }
            
            // 内容区域
            const contentDiv = document.createElement('div');
            contentDiv.className = 'ai-message-content';
            contentDiv.style.cssText = 'white-space: pre-wrap; word-wrap: break-word; color: #212529;';
            if (isStreaming) {
                contentDiv.innerHTML = this.escapeHtml(content) + '<span class="ai-streaming-cursor" style="display: inline-block; width: 2px; height: 1em; background-color: #0d6efd; animation: blink 1s infinite;">|</span>';
            } else {
                contentDiv.textContent = content;
            }
            messageBody.appendChild(contentDiv);
            
            messageContent.appendChild(messageBody);
            messageDiv.appendChild(messageContent);
            chatHistory.appendChild(messageDiv);
            
            // 滚动到底部
            chatHistory.scrollTop = chatHistory.scrollHeight;
            
            // 返回消息元素，用于流式更新
            return {
                messageDiv,
                contentDiv,
                thinkingDiv
            };
        }

        /**
         * 更新 AI 消息内容（用于流式输出）
         * @param {Object} messageElement - 消息元素对象（由 addAIMessage 返回）
         * @param {string} content - 新的内容
         * @param {string} reasoning - 新的思考内容（可选）
         */
        static updateAIMessage(messageElement, content, reasoning = '') {
            if (!messageElement) return;
            
            // 更新内容
            if (messageElement.contentDiv) {
                messageElement.contentDiv.innerHTML = this.escapeHtml(content) + '<span class="ai-streaming-cursor" style="display: inline-block; width: 2px; height: 1em; background-color: #0d6efd; animation: blink 1s infinite;">|</span>';
            }
            
            // 更新思考内容
            if (messageElement.thinkingDiv && reasoning) {
                const modal = messageElement.messageDiv?.closest('.modal');
                const thinkingModeCheckbox = modal?.querySelector(`#aiThinkingMode_${modal?.dataset.aiUniqueId}`);
                if (thinkingModeCheckbox && thinkingModeCheckbox.checked) {
                    messageElement.thinkingDiv.innerHTML = `<div style="white-space: pre-wrap; word-wrap: break-word;"><strong>💭 思考过程：</strong><br>${this.escapeHtml(reasoning)}</div>`;
                }
            }
            
            // 滚动到底部
            const chatHistory = messageElement.messageDiv?.closest('.modal-body')?.querySelector('[id^="aiChatHistory_"]');
            if (chatHistory) {
                chatHistory.scrollTop = chatHistory.scrollHeight;
            }
        }

        /**
         * 完成 AI 消息（移除流式光标）
         * @param {Object} messageElement - 消息元素对象
         */
        static completeAIMessage(messageElement) {
            if (!messageElement || !messageElement.contentDiv) return;
            
            // 移除流式光标
            const cursor = messageElement.contentDiv.querySelector('.ai-streaming-cursor');
            if (cursor) {
                cursor.remove();
            }
        }

        /**
         * 清空对话历史
         * @param {HTMLElement} modal - 模态框元素
         * @param {string} uniqueId - 唯一ID
         */
        static clearChatHistory(modal, uniqueId) {
            const chatHistory = modal.querySelector(`#aiChatHistory_${uniqueId}`);
            if (chatHistory) {
                chatHistory.innerHTML = `
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-chat-dots" style="font-size: 2rem; opacity: 0.3;"></i>
                        <p class="mt-2 mb-0">开始与 AI 对话，输入提示词后点击"发送"按钮</p>
                    </div>
                `;
            }
            // 清除本地存储
            const storageKey = `ai_chat_history_${uniqueId}`;
            try {
                localStorage.removeItem(storageKey);
            } catch (e) {
                console.warn('[AIInputEnhancer] 清除对话历史失败:', e);
            }
        }

        /**
         * 获取对话历史消息（用于多轮对话）
         * @param {HTMLElement} modal - 模态框元素
         * @param {string} uniqueId - 唯一ID
         * @returns {Array} 消息数组，格式为 [{role: 'user'|'assistant', content: string, reasoning?: string}]
         */
        static getChatHistory(modal, uniqueId) {
            const messages = [];
            const chatHistory = modal.querySelector(`#aiChatHistory_${uniqueId}`);
            if (!chatHistory) return messages;
            
            const messageDivs = chatHistory.querySelectorAll('[data-ai-message-id]');
            messageDivs.forEach(msgDiv => {
                const isUser = msgDiv.dataset.aiMessage === 'user';
                const contentDiv = msgDiv.querySelector('.ai-message-content');
                const thinkingDiv = msgDiv.querySelector('[style*="background-color: #fff3cd"]');
                
                if (contentDiv) {
                    const content = contentDiv.textContent || contentDiv.innerText || '';
                    const reasoning = thinkingDiv ? (thinkingDiv.textContent || thinkingDiv.innerText || '').replace(/💭\s*思考过程：\s*/i, '') : '';
                    
                    // 只添加非空内容的消息
                    const trimmedContent = content.trim();
                    if (trimmedContent) {
                        messages.push({
                            role: isUser ? 'user' : 'assistant',
                            content: trimmedContent,
                            ...(reasoning && reasoning.trim() && { reasoning: reasoning.trim() })
                        });
                    }
                }
            });
            
            return messages;
        }

        /**
         * 保存对话历史到本地存储
         * @param {HTMLElement} modal - 模态框元素
         * @param {string} uniqueId - 唯一ID
         */
        static saveChatHistory(modal, uniqueId) {
            const messages = this.getChatHistory(modal, uniqueId);
            const storageKey = `ai_chat_history_${uniqueId}`;
            try {
                localStorage.setItem(storageKey, JSON.stringify(messages));
            } catch (e) {
                console.warn('[AIInputEnhancer] 保存对话历史失败:', e);
            }
        }

        /**
         * 从本地存储恢复对话历史
         * @param {HTMLElement} modal - 模态框元素
         * @param {string} uniqueId - 唯一ID
         */
        static restoreChatHistory(modal, uniqueId) {
            const storageKey = `ai_chat_history_${uniqueId}`;
            try {
                const saved = localStorage.getItem(storageKey);
                if (saved) {
                    const messages = JSON.parse(saved);
                    const chatHistory = modal.querySelector(`#aiChatHistory_${uniqueId}`);
                    if (chatHistory && messages.length > 0) {
                        chatHistory.innerHTML = '';
                        messages.forEach(msg => {
                            if (msg.role === 'user') {
                                this.addUserMessage(modal, uniqueId, msg.content);
                            } else {
                                this.addAIMessage(modal, uniqueId, msg.content, msg.reasoning || '', false);
                            }
                        });
                    }
                }
            } catch (e) {
                console.warn('[AIInputEnhancer] 恢复对话历史失败:', e);
            }
        }

        /**
         * 更新模态框统计信息
         * @param {HTMLElement} modal - 模态框元素
         * @param {string} uniqueId - 唯一ID
         * @param {string|null} model - 模型名称
         * @param {number|null} tokens - Token 使用量
         * @param {string|null} duration - 耗时（秒）
         */
        static updateModalStats(modal, uniqueId, model, tokens, duration) {
            const modelInfo = modal.querySelector(`#aiModelInfo_${uniqueId}`);
            const tokenInfo = modal.querySelector(`#aiTokenInfo_${uniqueId}`);
            const timeInfo = modal.querySelector(`#aiTimeInfo_${uniqueId}`);

            if (modelInfo) {
                if (model) {
                    modelInfo.textContent = model;
                    modelInfo.classList.remove('d-none');
                } else {
                    modelInfo.classList.add('d-none');
                }
            }

            if (tokenInfo) {
                if (tokens !== null && tokens > 0) {
                    tokenInfo.textContent = `${tokens} tokens`;
                    tokenInfo.classList.remove('d-none');
                } else {
                    tokenInfo.classList.add('d-none');
                }
            }

            if (timeInfo) {
                if (duration !== null) {
                    timeInfo.textContent = `${duration}s`;
                    timeInfo.classList.remove('d-none');
                } else {
                    timeInfo.classList.add('d-none');
                }
            }
        }
    }

    /**
     * 自动增强页面上的常见字段
     * 根据字段名自动应用合适的 AI 配置
     */
    AIInputEnhancer.autoEnhance = function() {
        if (!window.AIInputEnhancer) {
            console.warn('[AIInputEnhancer] 组件未加载');
            return;
        }
        // 默认改为需要显式开启自动增强，避免在所有表单页面默认注入 AI 功能
        // 开启条件：window.AI_INPUT_AUTO_ENHANCE === true 或者 window.AI_CONFIG?.autoEnhance === true
        const autoEnabled = (typeof window.AI_INPUT_AUTO_ENHANCE !== 'undefined' && window.AI_INPUT_AUTO_ENHANCE === true)
            || (window.AI_CONFIG && window.AI_CONFIG.autoEnhance === true);
        if (!autoEnabled) {
            // 不抛错，静默跳过；如果需要调试可将下面注释改为 console.info
            return;
        }
        // 获取站点信息用于生成提示词
        const getSiteInfo = () => {
            return {
                siteName: document.querySelector('[data-field-name="name"] input')?.value || '',
                siteTitle: document.querySelector('[data-field-name="title"] input')?.value || '',
                siteSlogan: document.querySelector('[data-field-name="slogan"] input')?.value || '',
                siteDescription: document.querySelector('[data-field-name="description"] textarea')?.value || ''
            };
        };

        // 站点描述字段（description）
        const descriptionField = document.querySelector('[data-field-name="description"] textarea[name="description"]');
        if (descriptionField) {
            const getDescriptionPrompt = () => {
                const info = getSiteInfo();
                
                let prompt = '请为我的网站生成一段 SEO 友好的站点描述（150-200字），要求：\n';
                prompt += '1. 简洁明了，突出网站核心价值和特色\n';
                prompt += '2. 包含主要关键词，便于搜索引擎优化\n';
                prompt += '3. 语言流畅自然，吸引用户点击\n';
                prompt += '4. 符合中文表达习惯\n\n';
                
                if (info.siteName) prompt += `网站名称：${info.siteName}\n`;
                if (info.siteTitle) prompt += `网站标题：${info.siteTitle}\n`;
                if (info.siteSlogan) prompt += `网站口号：${info.siteSlogan}\n`;
                
                prompt += '\n请基于以上信息生成站点描述。';
                return prompt;
            };
            
            this.enhance(descriptionField, {
                mode: 'modal',
                defaultPrompt: getDescriptionPrompt,
                modalTitle: 'AI 生成站点描述',
                modalPromptLabel: '提示词',
                buttonText: 'AI 生成',
                buttonClass: 'btn btn-sm btn-outline-primary',
                temperature: 0.7,
                maxTokens: 500
            });
        }

        // SEO 关键词字段（keywords）
        const keywordsField = document.querySelector('[data-field-name="keywords"] input[name="keywords"]');
        if (keywordsField) {
            const getKeywordsPrompt = () => {
                const info = getSiteInfo();
                
                let prompt = '请为我的网站生成 SEO 关键词（5-10个），要求：\n';
                prompt += '1. 关键词之间用中文逗号分隔\n';
                prompt += '2. 包含核心业务关键词和长尾关键词\n';
                prompt += '3. 关键词要精准、相关、有搜索价值\n';
                prompt += '4. 避免过于宽泛或过于冷门的关键词\n';
                prompt += '5. 只返回关键词，不要其他说明文字\n\n';
                
                if (info.siteName) prompt += `网站名称：${info.siteName}\n`;
                if (info.siteTitle) prompt += `网站标题：${info.siteTitle}\n`;
                if (info.siteSlogan) prompt += `网站口号：${info.siteSlogan}\n`;
                if (info.siteDescription) prompt += `网站描述：${info.siteDescription}\n`;
                
                prompt += '\n请基于以上信息生成 SEO 关键词，多个关键词用中文逗号分隔。';
                return prompt;
            };
            
            this.enhance(keywordsField, {
                mode: 'one-click',
                defaultPrompt: getKeywordsPrompt,
                buttonText: 'AI 生成',
                buttonClass: 'btn btn-sm btn-outline-primary',
                temperature: 0.7,
                maxTokens: 200,
                stream: true, // 启用流式输出
                thinking: true, // 启用思考模式
                showThinking: true, // 显示思考过程
                onStreamChunk: (chunk, accumulatedContent, accumulatedReasoning) => {
                    // 流式输出时实时清理内容
                    // 注意：思考内容已在上方单独显示，这里只处理实际内容
                    let cleaned = accumulatedContent
                        .replace(/^[^，,]*[：:]\s*/, '') // 去除开头的"关键词："等
                        .replace(/[，,]\s*[，,]+/g, '，') // 去除重复的逗号
                        .trim();
                    
                    // 只返回清理后的实际内容，思考内容由组件自动显示在上方
                    if (cleaned) {
                        return cleaned;
                    }
                    // 如果没有内容，返回 null 让流式处理函数使用默认逻辑
                    return null;
                },
                onAfterGenerate: (input, content) => {
                    // 最终清理生成的内容，只保留关键词
                    let cleaned = content
                        .replace(/^[^，,]*[：:]\s*/, '')
                        .replace(/[，,]\s*[，,]+/g, '，')
                        .trim();
                    
                    if (cleaned) {
                        input.value = cleaned;
                        input.dispatchEvent(new Event('input', { bubbles: true }));
                        input.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                }
            });
        }
    };

    // 导出到全局
    window.AIInputEnhancer = AIInputEnhancer;

    // 页面加载完成后自动增强
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            // 等待表单渲染完成
            setTimeout(() => {
                AIInputEnhancer.autoEnhance();
            }, 500);
        });
    } else {
        // 如果 DOM 已经加载完成，延迟执行以确保表单已渲染
        setTimeout(() => {
            AIInputEnhancer.autoEnhance();
        }, 500);
    }

})();

