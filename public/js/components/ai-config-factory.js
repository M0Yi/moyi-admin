/**
 * AI 配置工厂类
 * 统一管理 AI 配置，提供配置验证和参数构建功能
 */
(function() {
    'use strict';

    /**
     * AI 配置工厂类
     */
    class AIConfigFactory {
        /**
         * 检查 AI 配置是否有效
         * @param {Object} [aiConfig] - 可选的 AI 配置（默认使用 window.AI_CONFIG）
         * @returns {boolean}
         */
        static isValid(aiConfig = null) {
            const config = aiConfig || window.AI_CONFIG;
            
            // 检查配置是否存在
            if (!config || typeof config !== 'object') {
                return false;
            }

            // 检查 token 是否存在且不为空
            const token = config.token || '';
            if (!token || token.trim() === '') {
                return false;
            }

            // 检查 provider 是否存在
            if (!config.provider || config.provider.trim() === '') {
                return false;
            }

            return true;
        }

        /**
         * 获取有效的 AI 配置
         * @param {Object} [customConfig] - 自定义配置（可选）
         * @returns {Object|null} 返回有效配置或 null
         */
        static getValidConfig(customConfig = null) {
            const config = customConfig || window.AI_CONFIG;
            
            if (!this.isValid(config)) {
                return null;
            }

            return config;
        }

        /**
         * 检查是否为智谱AI
         * @param {Object} [aiConfig] - 可选的 AI 配置（默认使用 window.AI_CONFIG）
         * @returns {boolean}
         */
        static isZhipuAI(aiConfig = null) {
            const config = aiConfig || window.AI_CONFIG;
            return config && config.provider === 'zhipu';
        }

        /**
         * 构建思考参数（仅智谱AI支持）
         * @param {Object} config - AI 输入增强组件的配置
         * @param {Object} [aiConfig] - 可选的 AI 配置（默认使用 window.AI_CONFIG）
         * @returns {Object|null} 返回思考参数或 null
         */
        static buildThinkingParams(config, aiConfig = null) {
            const aiConfigObj = aiConfig || window.AI_CONFIG;
            
            // 只有智谱AI才支持思考参数
            if (!this.isZhipuAI(aiConfigObj)) {
                return null;
            }

            // 智谱AI 默认开启思考，所以无论是否启用思考模式，都需要传递 thinking 参数
            // 如果 config.thinking 为 true，传递 enabled；否则传递 disabled
            return {
                type: config.thinking ? 'enabled' : 'disabled' // enabled: 开启思维链, disabled: 关闭思维链
            };
        }

        /**
         * 获取模型名称
         * @param {Object} config - AI 输入增强组件的配置
         * @param {Object} [aiConfig] - 可选的 AI 配置（默认使用 window.AI_CONFIG）
         * @returns {string}
         */
        static getModel(config, aiConfig = null) {
            // 如果指定了模型，直接使用
            if (config.aiModel) {
                return config.aiModel;
            }

            const aiConfigObj = aiConfig || window.AI_CONFIG;

            // 如果启用思考模式且为智谱AI，使用支持思考的模型
            if (config.thinking && this.isZhipuAI(aiConfigObj)) {
                // 智谱AI 支持思考的模型：glm-4.5, glm-4.5-flash, glm-4.6, glm-4.6-flash 等
                // 默认使用 glm-4.5-flash（免费且支持思考）
                return 'glm-4.5-flash';
            }

            // 使用配置中的文本模型
            return aiConfigObj?.text_model || 'glm-z1-flash';
        }

        /**
         * 获取 AI 服务实例
         * @param {Object} [customConfig] - 自定义配置（可选）
         * @returns {AIService|null} 返回 AI 服务实例或 null
         */
        static getAIService(customConfig = null) {
            // 检查配置是否有效
            const validConfig = this.getValidConfig(customConfig);
            if (!validConfig) {
                return null;
            }

            // 如果提供了自定义配置，使用自定义配置创建服务
            if (customConfig) {
                return window.AIServiceFactory.create(customConfig);
            }

            // 使用全局默认服务
            return window.aiService || null;
        }

        /**
         * 检查并获取 AI 服务（带错误提示）
         * @param {Object} [customConfig] - 自定义配置（可选）
         * @returns {AIService|null} 返回 AI 服务实例或 null
         */
        static getAIServiceOrError(customConfig = null) {
            const service = this.getAIService(customConfig);
            
            if (!service) {
                const config = customConfig || window.AI_CONFIG;
                if (!config || typeof config !== 'object') {
                    throw new Error('AI 配置未初始化，请检查 window.AI_CONFIG 是否正确设置');
                }
                
                const token = config.token || '';
                if (!token || token.trim() === '') {
                    throw new Error('AI Token 未配置，请先在站点设置中配置 AI Token');
                }

                if (!config.provider || config.provider.trim() === '') {
                    throw new Error('AI 提供商未配置，请先在站点设置中配置 AI 提供商');
                }
            }

            return service;
        }
    }

    // 导出到全局
    window.AIConfigFactory = AIConfigFactory;

})();

