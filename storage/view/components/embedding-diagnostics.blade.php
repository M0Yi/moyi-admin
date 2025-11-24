{{--
嵌入态诊断组件

参数说明：
- $isEmbedded (bool, 必填): 是否处于嵌入模式
- $normalizedUrl (string, 必填): 标准化地址
- $diagnostics (array, 可选): 诊断信息数组，包含：
  - channel: Iframe Channel
  - sec_fetch_dest: Sec-Fetch-Dest
  - query: Query 参数数组
- $id (string, 可选): 组件唯一标识，用于生成唯一的 DOM ID，默认为 'embedding-diagnostics'
- $showTitle (bool, 可选): 是否显示标题，默认为 true
--}}

@php
    $id = $id ?? 'embedding-diagnostics';
    $showTitle = $showTitle ?? true;
    $nestingLevelId = $id . '-nesting-level';
    $nestingHintId = $id . '-nesting-hint';
@endphp

<div class="card border-0 shadow-sm">
    <div class="card-body">
        @if($showTitle)
        <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
            <div>
                <h6 class="mb-1">嵌入态诊断</h6>
                <small class="text-muted">renderAdmin() 自动注入的上下文信息</small>
            </div>
            <span class="badge {{ $isEmbedded ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">
                {{ $isEmbedded ? 'Iframe / 内嵌模式' : 'Shell / 主框架模式' }}
            </span>
        </div>
        @endif
        <dl class="row mb-0 small">
            <dt class="col-sm-4 text-muted">标准化地址</dt>
            <dd class="col-sm-8 mb-2">
                <code class="d-inline-block text-truncate" style="max-width: 100%;">{{ $normalizedUrl }}</code>
            </dd>

            <dt class="col-sm-4 text-muted">嵌套层级</dt>
            <dd class="col-sm-8 mb-2">
                <span id="{{ $nestingLevelId }}" class="badge bg-secondary-subtle text-secondary">计算中...</span>
                <small class="text-muted d-block mt-1" id="{{ $nestingHintId }}"></small>
            </dd>

            <dt class="col-sm-4 text-muted">Iframe Channel</dt>
            <dd class="col-sm-8 mb-2">
                {{ $diagnostics['channel'] ?? '未携带（主框架模式）' }}
            </dd>

            <dt class="col-sm-4 text-muted">Sec-Fetch-Dest</dt>
            <dd class="col-sm-8 mb-2">
                {{ $diagnostics['sec_fetch_dest'] ?? '无' }}
            </dd>

            <dt class="col-sm-4 text-muted">Query 参数</dt>
            <dd class="col-sm-8 mb-0">
                @if(!empty($diagnostics['query']))
                    <ul class="list-unstyled mb-0">
                        @foreach($diagnostics['query'] as $key => $value)
                        <li class="text-break">
                            <span class="text-muted">{{ $key }}</span> =
                            <code>
                                @if(is_scalar($value))
                                    {{ $value }}
                                @else
                                    {{ json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}
                                @endif
                            </code>
                        </li>
                        @endforeach
                    </ul>
                @else
                    <span class="text-muted">无</span>
                @endif
            </dd>
        </dl>
    </div>
</div>

@push('admin_scripts')
<script>
(function() {
    'use strict';
    
    /**
     * 计算 iframe 嵌套层级（套娃深度）
     * @returns {number} 嵌套层级，0 表示在主框架中
     */
    function calculateNestingLevel() {
        if (window === window.top) {
            return 0; // 在主框架中
        }
        
        let level = 0;
        let currentWindow = window;
        const maxDepth = 20; // 防止无限循环
        
        try {
            while (currentWindow !== window.top && level < maxDepth) {
                try {
                    if (currentWindow.parent === currentWindow) {
                        // 已到达顶层
                        break;
                    }
                    currentWindow = currentWindow.parent;
                    level++;
                } catch (error) {
                    // 跨域限制，无法继续向上查找
                    break;
                }
            }
        } catch (error) {
            // 无法访问父窗口
        }
        
        return level;
    }

    /**
     * 显示嵌套层级信息
     */
    function displayNestingLevel(levelElId, hintElId) {
        const levelEl = document.getElementById(levelElId);
        const hintEl = document.getElementById(hintElId);
        
        if (!levelEl || !hintEl) {
            return;
        }
        
        const level = calculateNestingLevel();
        
        // 根据层级设置不同的样式和提示
        let badgeClass = 'bg-secondary-subtle text-secondary';
        let icon = '';
        let hint = '';
        
        if (level === 0) {
            badgeClass = 'bg-primary-subtle text-primary';
            icon = '🏠';
            hint = '当前在主框架中，不是 iframe';
        } else if (level === 1) {
            badgeClass = 'bg-info-subtle text-info';
            icon = '📦';
            hint = '第 1 层嵌套，正常的 iframe 模式';
        } else if (level === 2) {
            badgeClass = 'bg-warning-subtle text-warning';
            icon = '📦📦';
            hint = '第 2 层嵌套，开始套娃了！';
        } else if (level === 3) {
            badgeClass = 'bg-warning-subtle text-warning';
            icon = '📦📦📦';
            hint = '第 3 层嵌套，套娃进行中...';
        } else if (level >= 4 && level < 10) {
            badgeClass = 'bg-danger-subtle text-danger';
            icon = '📦'.repeat(Math.min(level, 5));
            hint = `第 ${level} 层嵌套，深度套娃！${level >= 5 ? '注意性能影响' : ''}`;
        } else {
            badgeClass = 'bg-dark text-white';
            icon = '📦'.repeat(5) + '...';
            hint = `第 ${level} 层嵌套，无限套娃模式！建议适可而止 😄`;
        }
        
        levelEl.className = `badge ${badgeClass}`;
        levelEl.textContent = `${icon} L${level}`;
        hintEl.textContent = hint;
    }

    // 页面加载时显示嵌套层级
    document.addEventListener('DOMContentLoaded', function() {
        displayNestingLevel('{{ $nestingLevelId }}', '{{ $nestingHintId }}');
    });
})();
</script>
@endpush

