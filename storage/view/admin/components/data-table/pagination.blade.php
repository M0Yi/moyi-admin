{{-- 分页 --}}
@if($showPagination ?? true)
    <div id="{{ $tableId }}_pagination" class="d-flex justify-content-between align-items-center mt-4" style="display: none;">
        <div class="d-flex align-items-center gap-3">
            <div id="{{ $tableId }}_pageInfo" class="text-muted"></div>
            {{-- 分页尺寸选择器 --}}
            @php
                $pageSizeOptionsArray = $pageSizeOptions ?? [10, 15, 20, 50, 100];
                $defaultPageSizeValue = $defaultPageSize ?? 15;
            @endphp
            <div class="d-flex align-items-center gap-2">
                <label for="{{ $tableId }}_pageSizeSelect" class="text-muted mb-0 small">每页显示：</label>
                <select id="{{ $tableId }}_pageSizeSelect" class="form-select form-select-sm" style="width: 80px; padding-right: 1.75rem;">
                    @foreach($pageSizeOptionsArray as $size)
                        <option value="{{ $size }}" {{ $size == $defaultPageSizeValue ? 'selected' : '' }}>
                            {{ $size }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="d-flex align-items-center gap-3">
        <nav>
            <ul class="pagination mb-0" id="{{ $tableId }}_pageLinks"></ul>
        </nav>
            {{-- 页码跳转输入框 --}}
            <div class="d-flex align-items-center gap-2" id="{{ $tableId }}_pageJump" style="display: none;">
                <label for="{{ $tableId }}_pageInput" class="text-muted mb-0 small">跳转到：</label>
                <input type="number" 
                       id="{{ $tableId }}_pageInput" 
                       class="form-control form-control-sm" 
                       style="width: 70px;" 
                       min="1" 
                       placeholder="页码"
                       onkeypress="if(event.key === 'Enter') { jumpToPage_{{ $tableId }}(); }">
                <button type="button" 
                        class="btn btn-sm btn-outline-secondary" 
                        onclick="jumpToPage_{{ $tableId }}()">
                    跳转
                </button>
            </div>
        </div>
    </div>
@endif

