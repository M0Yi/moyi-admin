/**
 * JavaScript 工具库统一入口
 * 
 * 本文件汇总所有工具函数，统一暴露到全局
 * 
 * 包含模块：
 * - request.js  : HTTP 请求封装
 * - helper.js   : 通用工具函数
 * 
 * 全局暴露：
 * - $http    : HTTP 请求工具
 * - $helper  : 通用工具函数
 */

// 工具已通过独立文件加载
// 此文件仅作为索引和文档说明

/**
 * 使用示例：
 * 
 * // HTTP 请求
 * const users = await $http.get('/api/users');
 * await $http.post('/api/users', { name: '张三' });
 * await $http.put('/api/users/1', { name: '李四' });
 * await $http.delete('/api/users/1');
 * 
 * // 文件上传
 * const result = await $http.upload('/api/upload', fileElement.files[0]);
 * 
 * // 文件下载
 * $http.download('/api/export', { ids: [1, 2, 3] });
 * 
 * // ========== 工具函数 ==========
 * 
 * // 验证
 * $helper.isMobile('13800138000');  // true
 * $helper.isEmail('test@test.com'); // true
 * 
 * // 日期
 * $helper.formatDate(new Date(), 'Y-m-d H:i:s'); // '2024-01-01 12:00:00'
 * $helper.relativeTime('2024-01-01 12:00:00');   // 'X 天前'
 * 
 * // 数字
 * $helper.formatNumber(1234567);   // '1,234,567'
 * $helper.formatFileSize(1024);     // '1 KB'
 * 
 * // 存储
 * $helper.setStorage('token', 'xxx', 3600);  // 1小时后过期
 * $helper.getStorage('token');                // 获取
 * 
 * // DOM
 * $helper.debounce(fn, 300);  // 防抖
 * $helper.throttle(fn, 300);  // 节流
 * $helper.copyToClipboard('text');  // 复制到剪贴板
 */
