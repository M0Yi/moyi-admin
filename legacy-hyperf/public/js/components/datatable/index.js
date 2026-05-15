/**
 * DataTable 数据表格组件
 * 
 * 功能特性：
 * - 数据渲染
 * - 分页
 * - 搜索筛选
 * - 排序
 * - 批量操作
 * - 自定义列渲染
 * 
 * 使用方法：
 * const table = DataTable.create({
 *     api: '/api/users',
 *     columns: [
 *         { title: 'ID', data: 'id' },
 *         { title: '用户名', data: 'username' },
 *         { 
 *             title: '操作',
 *             render: (row) => `<button onclick="edit(${row.id})">编辑</button>`
 *         }
 *     ],
 *     onLoad: (data) => { ... },
 *     onDelete: (ids) => { ... }
 * });
 */

(function(global) {
    'use strict';

    /**
     * DataTable 类
     */
    class DataTable {
        constructor(options) {
            this.options = {
                api: options.api || '',
                method: options.method || 'GET',
                columns: options.columns || [],
                pageSize: options.pageSize || 15,
                pageSizeOptions: options.pageSizeOptions || [10, 15, 30, 50, 100],
                showPagination: options.showPagination !== false,
                showSearch: options.showSearch !== false,
                showColumnToggle: options.showColumnToggle !== false,
                showBatchActions: options.showBatchActions !== false,
                primaryKey: options.primaryKey || 'id',
                onLoad: options.onLoad || null,
                onDelete: options.onDelete || null,
                onEdit: options.onEdit || null,
                onView: options.onView || null,
                ...options,
            };

            this.data = [];
            this.total = 0;
            this.currentPage = 1;
            this.pageSize = this.options.pageSize;
            this.keyword = '';
            this.loading = false;
            this.selectedIds = [];
            this.sortColumn = '';
            this.sortDirection = '';

            this.init();
        }

        /**
         * 初始化
         */
        init() {
            // 绑定全局方法
            this.refresh = this.refresh.bind(this);
            this.search = this.search.bind(this);
            this.changePage = this.changePage.bind(this);
            this.changePageSize = this.changePageSize.bind(this);
            this.handleSort = this.handleSort.bind(this);
            this.toggleSelectAll = this.toggleSelectAll.bind(this);
            this.toggleSelect = this.toggleSelect.bind(this);
            this.batchDelete = this.batchDelete.bind(this);
        }

        /**
         * 加载数据
         */
        async load() {
            if (this.loading) return;
            this.loading = true;

            try {
                const params = new URLSearchParams();
                params.append('page', this.currentPage);
                params.append('page_size', this.pageSize);
                
                if (this.keyword) {
                    params.append('keyword', this.keyword);
                }
                
                if (this.sortColumn) {
                    params.append('sort', this.sortColumn);
                    params.append('order', this.sortDirection);
                }

                // 添加额外参数
                if (this.options.params) {
                    Object.keys(this.options.params).forEach(key => {
                        params.append(key, this.options.params[key]);
                    });
                }

                const url = `${this.options.api}?${params.toString()}`;
                const data = await $http[this.options.method.toLowerCase()](url);
                
                this.data = data.data || [];
                this.total = data.total || 0;
                this.currentPage = data.current_page || this.currentPage;
                this.pageSize = data.page_size || this.pageSize;

                // 清除选择
                this.selectedIds = [];

                // 回调
                if (this.options.onLoad) {
                    this.options.onLoad(data);
                }

                return data;

            } catch (error) {
                console.error('加载数据失败:', error);
                throw error;
            } finally {
                this.loading = false;
            }
        }

        /**
         * 刷新数据
         */
        async refresh() {
            await this.load();
        }

        /**
         * 搜索
         */
        async search(keyword) {
            this.keyword = keyword;
            this.currentPage = 1;
            await this.load();
        }

        /**
         * 切换页面
         */
        async changePage(page) {
            if (page < 1 || page > this.totalPages) return;
            this.currentPage = page;
            await this.load();
        }

        /**
         * 切换每页数量
         */
        async changePageSize(size) {
            this.pageSize = size;
            this.currentPage = 1;
            await this.load();
        }

        /**
         * 排序
         */
        async handleSort(column) {
            if (this.sortColumn === column) {
                this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
            } else {
                this.sortColumn = column;
                this.sortDirection = 'asc';
            }
            await this.load();
        }

        /**
         * 全选/取消全选
         */
        toggleSelectAll(e) {
            if (e.target.checked) {
                this.selectedIds = this.data.map(row => row[this.options.primaryKey]);
            } else {
                this.selectedIds = [];
            }
        }

        /**
         * 选择/取消选择
         */
        toggleSelect(id) {
            const index = this.selectedIds.indexOf(id);
            if (index > -1) {
                this.selectedIds.splice(index, 1);
            } else {
                this.selectedIds.push(id);
            }
        }

        /**
         * 是否选中
         */
        isSelected(id) {
            return this.selectedIds.includes(id);
        }

        /**
         * 批量删除
         */
        async batchDelete() {
            if (this.selectedIds.length === 0) {
                $toast.warning('请选择要删除的记录');
                return;
            }

            const confirmed = await $confirm.danger(
                `确定要删除选中的 ${this.selectedIds.length} 条记录吗？`
            );

            if (!confirmed) return;

            try {
                await $http.delete(`${this.options.api}/batch`, {
                    data: { ids: this.selectedIds }
                });
                
                $toast.success('删除成功');
                await this.refresh();
                
                if (this.options.onDelete) {
                    this.options.onDelete(this.selectedIds);
                }

            } catch (error) {
                // 错误已由 $http 处理
            }
        }

        /**
         * 获取总页数
         */
        get totalPages() {
            return Math.ceil(this.total / this.pageSize);
        }

        /**
         * 获取分页范围
         */
        getPageRange() {
            const range = [];
            const totalPages = this.totalPages;
            const current = this.currentPage;
            const delta = 2;

            let start = Math.max(1, current - delta);
            let end = Math.min(totalPages, current + delta);

            if (end - start < delta * 2) {
                if (start === 1) {
                    end = Math.min(totalPages, start + delta * 2);
                } else {
                    end = totalPages;
                    start = Math.max(1, end - delta * 2);
                }
            }

            for (let i = start; i <= end; i++) {
                range.push(i);
            }

            return range;
        }

        /**
         * 获取选中数量
         */
        get selectedCount() {
            return this.selectedIds.length;
        }

        /**
         * 渲染列
         */
        renderCell(column, row) {
            if (column.render) {
                return column.render(row);
            }
            return row[column.data] ?? '';
        }

        /**
         * 渲染表格头部
         */
        renderHeader() {
            const columns = this.options.columns;
            let html = '<thead><tr>';
            
            // 批量操作列
            if (this.options.showBatchActions) {
                html += `
                    <th style="width: 40px;">
                        <input type="checkbox" @change="toggleSelectAll($event)" 
                            :checked="selectedIds.length === data.length && data.length > 0">
                    </th>
                `;
            }

            columns.forEach(column => {
                const sortable = column.sortable !== false;
                const sortIcon = sortable ? this.getSortIcon(column.data) : '';
                const cursor = sortable ? 'cursor: pointer;' : '';
                
                html += `
                    <th style="${cursor}" @click="${sortable ? `handleSort('${column.data}')` : ''}">
                        ${column.title} ${sortIcon}
                    </th>
                `;
            });

            html += '</tr></thead>';
            return html;
        }

        /**
         * 获取排序图标
         */
        getSortIcon(column) {
            if (this.sortColumn !== column) {
                return '<i class="bi bi-chevron-expand text-muted"></i>';
            }
            return this.sortDirection === 'asc' 
                ? '<i class="bi bi-chevron-up text-primary"></i>'
                : '<i class="bi bi-chevron-down text-primary"></i>';
        }

        /**
         * 渲染表格主体
         */
        renderBody() {
            let html = '<tbody>';
            
            if (this.data.length === 0) {
                html += `<tr><td colspan="${this.options.columns.length + (this.options.showBatchActions ? 1 : 0)}" class="text-center py-4">
                    <div class="text-muted">暂无数据</div>
                </td></tr>`;
            } else {
                this.data.forEach(row => {
                    const id = row[this.options.primaryKey];
                    const isSelected = this.isSelected(id);
                    
                    html += `<tr class="${isSelected ? 'table-active' : ''}">`;
                    
                    // 批量操作列
                    if (this.options.showBatchActions) {
                        html += `
                            <td>
                                <input type="checkbox" 
                                    :checked="isSelected(${id})"
                                    @change="toggleSelect(${id})">
                            </td>
                        `;
                    }

                    // 数据列
                    this.options.columns.forEach(column => {
                        html += `<td>${this.renderCell(column, row)}</td>`;
                    });

                    html += '</tr>';
                });
            }

            html += '</tbody>';
            return html;
        }

        /**
         * 渲染分页
         */
        renderPagination() {
            if (!this.options.showPagination) return '';

            return `
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <div class="text-muted">
                        共 ${this.total} 条记录，每页 
                        <select class="form-select form-select-sm d-inline-block w-auto" 
                            @change="changePageSize(parseInt($event.target.value))">
                            ${this.options.pageSizeOptions.map(size => 
                                `<option value="${size}" ${this.pageSize === size ? 'selected' : ''}>${size}</option>`
                            ).join('')}
                        </select>
                        条
                    </div>
                    <nav>
                        <ul class="pagination mb-0">
                            <li class="page-item ${this.currentPage === 1 ? 'disabled' : ''}">
                                <a class="page-link" href="#" @click.prevent="changePage(${this.currentPage - 1})">上一页</a>
                            </li>
                            ${this.getPageRange().map(page => `
                                <li class="page-item ${page === this.currentPage ? 'active' : ''}">
                                    <a class="page-link" href="#" @click.prevent="changePage(${page})">${page}</a>
                                </li>
                            `).join('')}
                            <li class="page-item ${this.currentPage === this.totalPages ? 'disabled' : ''}">
                                <a class="page-link" href="#" @click.prevent="changePage(${this.currentPage + 1})">下一页</a>
                            </li>
                        </ul>
                    </nav>
                </div>
            `;
        }

        /**
         * 渲染搜索框
         */
        renderSearch() {
            if (!this.options.showSearch) return '';

            return `
                <div class="row mb-3">
                    <div class="col-md-4">
                        <div class="input-group">
                            <input type="text" class="form-control" 
                                placeholder="搜索..." 
                                x-model="keyword"
                                @keyup.enter="search(keyword)">
                            <button class="btn btn-outline-primary" @click="search(keyword)">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
        }

        /**
         * 渲染批量操作栏
         */
        renderBatchActions() {
            if (!this.options.showBatchActions) return '';

            return `
                <div class="mb-2" x-show="selectedCount > 0" x-transition>
                    <span class="me-2">已选择 <strong x-text="selectedCount"></strong> 项</span>
                    <button class="btn btn-sm btn-danger" @click="batchDelete()">
                        <i class="bi bi-trash"></i> 批量删除
                    </button>
                </div>
            `;
        }

        /**
         * 获取 Alpine.js 数据对象
         */
        getAlpineData() {
            return {
                // 数据
                data: this.data,
                total: this.total,
                currentPage: this.currentPage,
                pageSize: this.pageSize,
                keyword: this.keyword,
                loading: this.loading,
                selectedIds: this.selectedIds,
                sortColumn: this.sortColumn,
                sortDirection: this.sortDirection,

                // 方法
                refresh: this.refresh,
                search: this.search,
                changePage: this.changePage,
                changePageSize: this.changePageSize,
                handleSort: this.handleSort,
                toggleSelectAll: this.toggleSelectAll,
                toggleSelect: this.toggleSelect,
                batchDelete: this.batchDelete,
                isSelected: this.isSelected.bind(this),
                renderCell: this.renderCell.bind(this),
                get selectedCount() {
                    return this.selectedIds.length;
                },
                get totalPages() {
                    return Math.ceil(this.total / this.pageSize);
                },
                getPageRange() {
                    return this.getPageRange();
                },
            };
        }

        /**
         * 获取模板
         */
        getTemplate() {
            return `
                ${this.renderSearch()}
                ${this.renderBatchActions()}
                
                <div class="table-responsive">
                    <table class="table table-hover table-bordered" x-data="tableData">
                        ${this.renderHeader()}
                        ${this.renderBody()}
                    </table>
                </div>
                
                ${this.renderPagination()}
            `;
        }

        /**
         * 挂载到容器
         */
        mount(selector) {
            const container = typeof selector === 'string' 
                ? document.querySelector(selector) 
                : selector;
            
            if (!container) {
                console.error('DataTable: 容器不存在');
                return;
            }

            // 渲染模板
            container.innerHTML = this.getTemplate();

            // 初始化 Alpine.js
            const tableData = this.getAlpineData();
            
            if (window.Alpine) {
                window.Alpine.data('tableData', () => tableData);
                window.Alpine.initTree(container);
            }

            // 加载数据
            this.load();

            return this;
        }

        /**
         * 销毁
         */
        destroy() {
            this.data = [];
            this.selectedIds = [];
        }
    }

    /**
     * 创建 DataTable 实例
     */
    DataTable.create = function(options) {
        return new DataTable(options);
    };

    // 暴露到全局
    global.DataTable = DataTable;

    // ES Module 导出支持
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = DataTable;
    }

})(typeof window !== 'undefined' ? window : this);
