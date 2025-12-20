/**
 * AI 服务类
 * 统一封装不同 AI 提供商的接口调用
 * 当前支持：智谱AI (ZhipuAI)
 * 
 * @example
 * // 使用示例
 * const aiService = new AIService();
 * 
 * // 对话补全
 * const response = await aiService.chatCompletions({
 *   model: 'glm-z1-flash',
 *   messages: [
 *     { role: 'user', content: '你好' }
 *   ]
 * });
 * 
 * // 异步对话补全
 * const taskId = await aiService.chatCompletionsAsync({
 *   model: 'glm-z1-flash',
 *   messages: [{ role: 'user', content: '你好' }]
 * });
 * 
 * // 查询异步结果
 * const result = await aiService.getAsyncResult(taskId);
 * 
 * // 图像生成
 * const image = await aiService.imageGeneration({
 *   model: 'cogview-3-flash',
 *   prompt: '一只可爱的小猫'
 * });
 * 
 * // 视频生成（异步）
 * const videoTaskId = await aiService.videoGenerationAsync({
 *   model: 'cogvideox-flash',
 *   prompt: '一只可爱的小猫在玩耍'
 * });
 */
(function() {
    'use strict';

    /**
     * AI 服务基类
     */
    class AIService {
        constructor(config = null) {
            // 从全局配置获取，如果没有则使用传入的配置
            this.config = config || window.AI_CONFIG || {};
            
            if (!this.config.token) {
                console.warn('[AIService] AI Token 未配置，请先在站点设置中配置 AI Token');
            }
            
            if (!this.config.base_url) {
                console.warn('[AIService] AI Base URL 未配置');
            }
        }

        /**
         * 获取请求头
         * @returns {Object}
         */
        getHeaders() {
            return {
                'Authorization': `Bearer ${this.config.token || ''}`,
                'Content-Type': 'application/json',
            };
        }

        /**
         * 发送请求
         * @param {string} url - 请求URL
         * @param {Object} options - 请求选项
         * @returns {Promise<Object>}
         */
        async request(url, options = {}) {
            const defaultOptions = {
                method: 'GET',
                headers: this.getHeaders(),
            };

            const mergedOptions = {
                ...defaultOptions,
                ...options,
                headers: {
                    ...defaultOptions.headers,
                    ...(options.headers || {}),
                },
            };

            try {
                const response = await fetch(url, mergedOptions);
                
                if (!response.ok) {
                    const errorData = await response.json().catch(() => ({}));
                    throw new Error(errorData.error?.message || `HTTP ${response.status}: ${response.statusText}`);
                }

                return await response.json();
            } catch (error) {
                console.error('[AIService] 请求失败:', error);
                throw error;
            }
        }

        /**
         * 流式请求（用于流式输出）
         * @param {string} url - 请求URL
         * @param {Object} options - 请求选项
         * @param {Function} onChunk - 接收数据块的回调函数 (chunk) => {}
         * @returns {Promise<void>}
         */
        async streamRequest(url, options = {}, onChunk = null) {
            const defaultOptions = {
                method: 'POST',
                headers: this.getHeaders(),
            };

            const mergedOptions = {
                ...defaultOptions,
                ...options,
                headers: {
                    ...defaultOptions.headers,
                    ...(options.headers || {}),
                },
            };

            try {
                const response = await fetch(url, mergedOptions);
                
                if (!response.ok) {
                    // 尝试读取错误信息
                    const errorText = await response.text().catch(() => '');
                    let errorData = {};
                    try {
                        errorData = JSON.parse(errorText);
                    } catch (e) {
                        // 忽略解析错误
                    }
                    throw new Error(errorData.error?.message || `HTTP ${response.status}: ${response.statusText}`);
                }

                if (!response.body) {
                    throw new Error('响应体不可读');
                }

                const reader = response.body.getReader();
                const decoder = new TextDecoder();
                let buffer = '';

                while (true) {
                    const { done, value } = await reader.read();
                    
                    if (done) {
                        // 处理剩余的 buffer
                        if (buffer.trim()) {
                            const trimmedLine = buffer.trim();
                            if (trimmedLine.startsWith('data: ')) {
                                const dataStr = trimmedLine.substring(6);
                                if (dataStr !== '[DONE]') {
                                    try {
                                        const data = JSON.parse(dataStr);
                                        if (onChunk && typeof onChunk === 'function') {
                                            onChunk(data);
                                        }
                                    } catch (e) {
                                        console.warn('[AIService] 解析流式数据失败:', e, dataStr);
                                    }
                                }
                            }
                        }
                        break;
                    }

                    buffer += decoder.decode(value, { stream: true });
                    const lines = buffer.split('\n');
                    buffer = lines.pop() || '';

                    for (const line of lines) {
                        const trimmedLine = line.trim();
                        if (!trimmedLine) {
                            continue;
                        }

                        // 处理 SSE 格式：data: {...}
                        if (trimmedLine.startsWith('data: ')) {
                            const dataStr = trimmedLine.substring(6);
                            if (dataStr === '[DONE]') {
                                return;
                            }

                            try {
                                const data = JSON.parse(dataStr);
                                if (onChunk && typeof onChunk === 'function') {
                                    onChunk(data);
                                }
                            } catch (e) {
                                console.warn('[AIService] 解析流式数据失败:', e, dataStr);
                            }
                        }
                    }
                }
            } catch (error) {
                console.error('[AIService] 流式请求失败:', error);
                throw error;
            }
        }

        /**
         * 对话补全（同步）
         * @param {Object} params - 请求参数
         * @param {string} params.model - 模型名称
         * @param {Array} params.messages - 消息列表
         * @param {number} [params.temperature] - 温度参数 (0-2)
         * @param {number} [params.max_tokens] - 最大token数
         * @param {boolean} [params.stream] - 是否流式输出
         * @param {Array} [params.stop] - 停止词列表
         * @param {Object} [params.response_format] - 响应格式
         * @param {string} [params.request_id] - 请求ID
         * @param {string} [params.user_id] - 用户ID
         * @param {Object} [params.thinking] - 思考配置（仅 GLM-4.5 及以上模型支持）
         * @param {string} [params.thinking.type] - 是否开启思维链：'enabled'（默认）或 'disabled'
         * @param {Function} [params.onChunk] - 流式输出时的回调函数（仅当 stream=true 时有效）
         * @returns {Promise<Object>}
         */
        async chatCompletions(params) {
            const url = `${this.config.base_url}/chat/completions`;
            
            const payload = {
                model: params.model || this.config.text_model || 'glm-z1-flash',
                messages: params.messages || [],
                ...(params.temperature !== undefined && { temperature: params.temperature }),
                ...(params.max_tokens !== undefined && { max_tokens: params.max_tokens }),
                ...(params.stream !== undefined && { stream: params.stream }),
                ...(params.stop && { stop: params.stop }),
                ...(params.response_format && { response_format: params.response_format }),
                ...(params.request_id && { request_id: params.request_id }),
                ...(params.user_id && { user_id: params.user_id }),
                ...(params.thinking && { thinking: params.thinking }),
            };

            // 记录请求体（用于调试）
            if (params.stream) {
                console.log('[AIService] 流式请求体', JSON.stringify(payload, null, 2));
            }

            // 如果是流式输出，使用流式请求
            if (params.stream && params.onChunk) {
                await this.streamRequest(url, {
                    method: 'POST',
                    headers: this.getHeaders(),
                    body: JSON.stringify(payload),
                }, params.onChunk);
                return { stream: true }; // 流式请求不返回完整响应
            }

            return await this.request(url, {
                method: 'POST',
                body: JSON.stringify(payload),
            });
        }

        /**
         * 对话补全（异步）
         * @param {Object} params - 请求参数（同 chatCompletions）
         * @returns {Promise<string>} 返回任务ID
         */
        async chatCompletionsAsync(params) {
            const url = `${this.config.base_url}/chat/completions/async`;
            
            const payload = {
                model: params.model || this.config.text_model || 'glm-z1-flash',
                messages: params.messages || [],
                ...(params.temperature !== undefined && { temperature: params.temperature }),
                ...(params.max_tokens !== undefined && { max_tokens: params.max_tokens }),
                ...(params.stop && { stop: params.stop }),
                ...(params.response_format && { response_format: params.response_format }),
                ...(params.request_id && { request_id: params.request_id }),
                ...(params.user_id && { user_id: params.user_id }),
            };

            const response = await this.request(url, {
                method: 'POST',
                body: JSON.stringify(payload),
            });

            return response.task_id;
        }

        /**
         * 查询异步结果
         * @param {string} taskId - 任务ID
         * @returns {Promise<Object>}
         */
        async getAsyncResult(taskId) {
            const url = `${this.config.base_url}/async-result/${taskId}`;
            
            return await this.request(url, {
                method: 'GET',
            });
        }

        /**
         * 图像生成
         * @param {Object} params - 请求参数
         * @param {string} params.model - 模型名称（默认：cogview-3-flash）
         * @param {string} params.prompt - 提示词
         * @param {string} [params.size] - 图片尺寸（如：1024x1024）
         * @param {string} [params.quality] - 图片质量
         * @param {number} [params.n] - 生成图片数量
         * @param {string} [params.style] - 图片风格
         * @param {string} [params.user_id] - 用户ID
         * @returns {Promise<Object>}
         */
        async imageGeneration(params) {
            const url = `${this.config.base_url}/images/generations`;
            
            const payload = {
                model: params.model || this.config.image_model || 'cogview-3-flash',
                prompt: params.prompt,
                ...(params.size && { size: params.size }),
                ...(params.quality && { quality: params.quality }),
                ...(params.n !== undefined && { n: params.n }),
                ...(params.style && { style: params.style }),
                ...(params.user_id && { user_id: params.user_id }),
            };

            return await this.request(url, {
                method: 'POST',
                body: JSON.stringify(payload),
            });
        }

        /**
         * 视频生成（异步）
         * @param {Object} params - 请求参数
         * @param {string} params.model - 模型名称（默认：cogvideox-flash）
         * @param {string} params.prompt - 提示词
         * @param {number} [params.duration] - 视频时长（秒）
         * @param {string} [params.resolution] - 视频分辨率
         * @param {string} [params.user_id] - 用户ID
         * @returns {Promise<string>} 返回任务ID
         */
        async videoGenerationAsync(params) {
            const url = `${this.config.base_url}/video/generations/async`;
            
            const payload = {
                model: params.model || this.config.video_model || 'cogvideox-flash',
                prompt: params.prompt,
                ...(params.duration !== undefined && { duration: params.duration }),
                ...(params.resolution && { resolution: params.resolution }),
                ...(params.user_id && { user_id: params.user_id }),
            };

            const response = await this.request(url, {
                method: 'POST',
                body: JSON.stringify(payload),
            });

            return response.task_id;
        }

        /**
         * 轮询异步结果（带重试机制）
         * @param {string} taskId - 任务ID
         * @param {Object} options - 轮询选项
         * @param {number} [options.interval=2000] - 轮询间隔（毫秒）
         * @param {number} [options.maxAttempts=60] - 最大尝试次数
         * @param {Function} [options.onProgress] - 进度回调函数
         * @returns {Promise<Object>}
         */
        async pollAsyncResult(taskId, options = {}) {
            const {
                interval = 2000,
                maxAttempts = 60,
                onProgress = null,
            } = options;

            let attempts = 0;

            while (attempts < maxAttempts) {
                const result = await this.getAsyncResult(taskId);

                // 检查任务状态
                if (result.status === 'success' || result.status === 'completed') {
                    return result;
                }

                if (result.status === 'failed' || result.status === 'error') {
                    throw new Error(result.error?.message || '任务执行失败');
                }

                // 调用进度回调
                if (onProgress && typeof onProgress === 'function') {
                    onProgress(result, attempts);
                }

                // 等待后重试
                await new Promise(resolve => setTimeout(resolve, interval));
                attempts++;
            }

            throw new Error('轮询超时：任务未在预期时间内完成');
        }
    }

    /**
     * 智谱AI 服务类（继承自 AIService）
     * 可以在这里添加智谱AI特有的方法或覆盖父类方法
     */
    class ZhipuAIService extends AIService {
        constructor(config = null) {
            super(config);
            this.provider = 'zhipu';
        }

        /**
         * 验证配置
         * @returns {boolean}
         */
        validateConfig() {
            if (!this.config.token) {
                throw new Error('智谱AI Token 未配置');
            }
            if (!this.config.base_url) {
                throw new Error('智谱AI Base URL 未配置');
            }
            return true;
        }
    }

    /**
     * AI 服务工厂类
     * 根据配置自动选择对应的服务提供商
     */
    class AIServiceFactory {
        /**
         * 创建 AI 服务实例
         * @param {Object} [config] - 配置对象（可选，默认使用 window.AI_CONFIG）
         * @returns {AIService}
         */
        static create(config = null) {
            const aiConfig = config || window.AI_CONFIG || {};
            const provider = aiConfig.provider || 'zhipu';

            switch (provider) {
                case 'zhipu':
                    return new ZhipuAIService(aiConfig);
                default:
                    console.warn(`[AIServiceFactory] 未知的 AI 提供商: ${provider}，使用默认的智谱AI`);
                    return new ZhipuAIService(aiConfig);
            }
        }
    }

    // 导出到全局
    window.AIService = AIService;
    window.ZhipuAIService = ZhipuAIService;
    window.AIServiceFactory = AIServiceFactory;

    // 创建默认实例（方便直接使用）
    window.aiService = AIServiceFactory.create();

})();

