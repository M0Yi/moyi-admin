{{-- 数据表格 --}}
<div class="table-responsive">
    <table class="table table-hover align-middle" id="{{ $tableId }}" style="table-layout: auto;">
        <thead class="table-light">
            <tr>
                {{-- 批量删除复选框列（如果启用了批量删除） --}}
                @if($enableBatchDelete)
                    <th width="50" style="white-space: nowrap;">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="checkAll_{{ $tableId }}" 
                                   onclick="toggleCheckAll_{{ $tableId }}(this)" 
                                   title="全选/取消全选">
                        </div>
                    </th>
                @endif
                @foreach($columns as $column)
                    @php
                        // 构建表头样式：禁止换行 + 显示/隐藏
                        $thStyle = 'white-space: nowrap;';
                        if (!($column['visible'] ?? true)) {
                            $thStyle .= ' display: none;';
                        }
                        
                        // 是否支持排序
                        $sortable = $column['sortable'] ?? false;
                        $sortable = filter_var($sortable, FILTER_VALIDATE_BOOLEAN);
                        
                        // 排序样式类
                        $sortClass = '';
                        if ($sortable) {
                            $sortClass = 'sortable-column';
                        }
                    @endphp
                    <th
                        @if(isset($column['width'])) width="{{ $column['width'] }}" @endif
                        data-column="{{ $column['index'] }}"
                        data-field="{{ $column['field'] ?? '' }}"
                        @if($sortable) data-sortable="1" @endif
                        @if(isset($column['class'])) class="{{ $column['class'] }} {{ $sortClass }}" @elseif($sortable) class="{{ $sortClass }}" @endif
                        style="{{ $sortable ? 'cursor: pointer; ' : '' }}{{ $thStyle }}"
                        @if($sortable) onclick="if(typeof handleSort_{{$tableId}} === 'function') handleSort_{{$tableId}}(this)" @endif
                    >
                        <div class="d-flex align-items-center justify-content-between">
                            <span>{{ $column['label'] }}</span>
                            @if($sortable)
                                <span class="sort-icons ms-2">
                                    <i class="bi bi-caret-up-fill sort-asc"></i>
                                    <i class="bi bi-caret-down-fill sort-desc"></i>
                                </span>
                            @endif
                        </div>
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            {{-- 数据通过 AJAX 动态加载并渲染 --}}
            <tr>
                <td colspan="{{ count($columns) + ($enableBatchDelete ? 1 : 0) }}" class="text-center text-muted py-4">
                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                    {{ $emptyMessage ?? '加载中...' }}
                </td>
            </tr>
        </tbody>
    </table>
</div>

