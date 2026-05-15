/**
 * PostgreSQL 测试插件 JavaScript
 */

(function($) {
    'use strict';

    // 全局配置
    const config = {
        apiBaseUrl: '/api/pgsql_tester',
        adminBaseUrl: '/admin/admin/pgsql_tester',
        csrfToken: document.querySelector('meta[name="csrf-token"]')?.content || ''
    };

    // 工具函数
    const utils = {
        /**
         * 显示加载状态
         */
        showLoading: function(message = '加载中...') {
            if (typeof toastr !== 'undefined') {
                toastr.info(message);
            } else {
                alert(message);
            }
        },

        /**
         * 隐藏加载状态
         */
        hideLoading: function() {
            // toastr 不需要手动隐藏
        },

        /**
         * 显示成功消息
         */
        showSuccess: function(message) {
            if (typeof toastr !== 'undefined') {
                toastr.success(message);
            } else {
                alert('成功: ' + message);
            }
        },

        /**
         * 显示错误消息
         */
        showError: function(message) {
            if (typeof toastr !== 'undefined') {
                toastr.error(message);
            } else {
                alert('错误: ' + message);
            }
        },

        /**
         * 显示警告消息
         */
        showWarning: function(message) {
            if (typeof toastr !== 'undefined') {
                toastr.warning(message);
            } else {
                alert('警告: ' + message);
            }
        },

        /**
         * 格式化响应时间
         */
        formatResponseTime: function(timeStr) {
            if (!timeStr) return '0ms';
            return timeStr.replace('ms', '') + 'ms';
        },

        /**
         * 格式化文件大小
         */
        formatFileSize: function(bytes) {
            if (!bytes) return '0 B';
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(1024));
            return Math.round(bytes / Math.pow(1024, i) * 100) / 100 + ' ' + sizes[i];
        },

        /**
         * 格式化数字
         */
        formatNumber: function(num) {
            return new Intl.NumberFormat().format(num);
        },

        /**
         * 深拷贝对象
         */
        deepClone: function(obj) {
            return JSON.parse(JSON.stringify(obj));
        },

        /**
         * 下载文件
         */
        downloadFile: function(content, filename, contentType = 'application/json') {
            const blob = new Blob([content], { type: contentType });
            const url = URL.createObjectURL(blob);

            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }
    };

    // API 调用函数
    const api = {
        /**
         * 通用 API 请求
         */
        request: function(endpoint, options = {}) {
            const defaultOptions = {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': config.csrfToken
                }
            };

            const finalOptions = Object.assign({}, defaultOptions, options);

            return fetch(config.apiBaseUrl + endpoint, finalOptions)
                .then(response => response.json())
                .then(data => {
                    if (data.code !== 200) {
                        throw new Error(data.message || data.msg || '请求失败');
                    }
                    return data.data;
                });
        },

        /**
         * 连接测试
         */
        testConnection: function() {
            return this.request('/connection');
        },

        /**
         * 查询测试
         */
        runQueryTest: function(query, params = []) {
            return this.request('/query', {
                method: 'POST',
                body: JSON.stringify({ query, params })
            });
        },

        /**
         * 性能测试
         */
        runPerformanceTest: function(iterations = 100, query = 'SELECT 1') {
            return this.request('/performance', {
                method: 'POST',
                body: JSON.stringify({ iterations, query })
            });
        },

        /**
         * 获取数据库信息
         */
        getDatabaseInfo: function() {
            return this.request('/info');
        },

        /**
         * 获取表信息
         */
        getTables: function() {
            return this.request('/tables');
        },

        /**
         * 获取扩展信息
         */
        getExtensions: function() {
            return this.request('/extensions');
        },

        /**
         * 获取统计信息
         */
        getStats: function() {
            return this.request('/stats');
        }
    };

    // UI 更新函数
    const ui = {
        /**
         * 更新统计卡片
         */
        updateStats: function(stats) {
            $('#connection-tests-count').text(utils.formatNumber(stats.connection_tests || 0));
            $('#query-tests-count').text(utils.formatNumber(stats.query_tests || 0));
            $('#performance-tests-count').text(utils.formatNumber(stats.performance_tests || 0));
            $('#avg-response-time').text((stats.avg_response_time || 0).toFixed(2) + 'ms');
            $('#last-test-time').text(stats.last_test_time || '暂无');
        },

        /**
         * 显示测试结果
         */
        showTestResult: function(title, data, type = 'success') {
            const $card = $('#result-card');
            const $title = $('#result-title');
            const $content = $('#result-content');

            $title.text(title);
            $content.text(JSON.stringify(data, null, 2));

            // 添加样式类
            $card.removeClass('result-success result-error result-warning')
                 .addClass('result-' + type);

            $card.show();
            $card[0].scrollIntoView({ behavior: 'smooth' });
        },

        /**
         * 显示进度条
         */
        showProgress: function(message, percentage = 0) {
            const $progressSection = $('#progress-section');
            const $progressBar = $('#test-progress');
            const $progressText = $('#progress-text');

            $progressSection.show();
            $progressBar.css('width', percentage + '%');
            $progressText.text(message);
        },

        /**
         * 隐藏进度条
         */
        hideProgress: function() {
            $('#progress-section').hide();
        },

        /**
         * 重置状态指示器
         */
        resetStatusIndicators: function() {
            $('.status-indicator').each(function() {
                $(this).removeClass('bg-success bg-danger bg-warning').addClass('bg-secondary');
                $(this).find('.status-text').text('未测试');
            });
        },

        /**
         * 更新状态指示器
         */
        updateStatusIndicator: function(id, status, text, value = null) {
            const $indicator = $('#' + id);
            const $icon = $indicator.find('.status-icon');
            const $text = $indicator.find('.status-text');
            const $value = $indicator.find('.status-value');

            $indicator.removeClass('bg-secondary bg-success bg-danger bg-warning');

            switch (status) {
                case 'success':
                    $indicator.addClass('bg-success');
                    $icon.html('<i class="bi bi-check-circle"></i>');
                    break;
                case 'error':
                    $indicator.addClass('bg-danger');
                    $icon.html('<i class="bi bi-x-circle"></i>');
                    break;
                case 'warning':
                    $indicator.addClass('bg-warning');
                    $icon.html('<i class="bi bi-exclamation-triangle"></i>');
                    break;
                default:
                    $indicator.addClass('bg-secondary');
                    $icon.html('<i class="bi bi-dash-circle"></i>');
            }

            $text.text(text);
            if ($value && $value.length) {
                $value.text(value);
            }
        },

        /**
         * 格式化测试结果显示
         */
        formatTestResult: function(data) {
            if (typeof data === 'object') {
                return JSON.stringify(data, null, 2);
            }
            return String(data);
        }
    };

    // 测试执行函数
    const tests = {
        /**
         * 执行连接测试
         */
        runConnectionTest: function(callback) {
            utils.showLoading('正在执行连接测试...');
            ui.showProgress('正在连接数据库...', 0);

            api.testConnection()
                .then(data => {
                    ui.showProgress('测试完成', 100);
                    setTimeout(() => ui.hideProgress(), 1000);

                    ui.showTestResult('连接测试结果', data, 'success');
                    utils.showSuccess('连接测试成功');

                    if (callback) callback(data);
                })
                .catch(error => {
                    ui.hideProgress();
                    ui.showTestResult('连接测试失败', { error: error.message }, 'error');
                    utils.showError('连接测试失败: ' + error.message);

                    if (callback) callback(null, error);
                })
                .finally(() => {
                    utils.hideLoading();
                });
        },

        /**
         * 执行查询测试
         */
        runQueryTest: function(query, params = [], callback) {
            if (!query) {
                utils.showError('请输入查询语句');
                return;
            }

            utils.showLoading('正在执行查询测试...');

            api.runQueryTest(query, params)
                .then(data => {
                    ui.showTestResult('查询测试结果', data, 'success');
                    utils.showSuccess('查询测试成功');

                    if (callback) callback(data);
                })
                .catch(error => {
                    ui.showTestResult('查询测试失败', { error: error.message }, 'error');
                    utils.showError('查询测试失败: ' + error.message);

                    if (callback) callback(null, error);
                })
                .finally(() => {
                    utils.hideLoading();
                });
        },

        /**
         * 执行性能测试
         */
        runPerformanceTest: function(iterations = 100, query = 'SELECT 1', callback) {
            utils.showLoading('正在执行性能测试...');

            api.runPerformanceTest(iterations, query)
                .then(data => {
                    ui.showTestResult('性能测试结果', data, 'success');
                    utils.showSuccess('性能测试完成');

                    if (callback) callback(data);
                })
                .catch(error => {
                    ui.showTestResult('性能测试失败', { error: error.message }, 'error');
                    utils.showError('性能测试失败: ' + error.message);

                    if (callback) callback(null, error);
                })
                .finally(() => {
                    utils.hideLoading();
                });
        }
    };

    // 导出到全局
    window.PgsqlTester = {
        config,
        utils,
        api,
        ui,
        tests,

        // 便捷方法
        testConnection: () => tests.runConnectionTest(),
        testQuery: (query, params) => tests.runQueryTest(query, params),
        testPerformance: (iterations, query) => tests.runPerformanceTest(iterations, query),
        getStats: () => api.getStats().then(stats => ui.updateStats(stats))
    };

    // 初始化
    $(document).ready(function() {
        console.log('PostgreSQL 测试插件 JavaScript 已加载');

        // 自动更新统计信息
        if ($('#connection-tests-count').length > 0) {
            window.PgsqlTester.getStats();
        }
    });

})(jQuery);
