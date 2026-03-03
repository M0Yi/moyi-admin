{{--
Toast Alpine.js 组件

功能：
- 显示 Toast 通知容器
- 支持多种类型（success、error、warning、info）
- 自动消失、手动关闭
- 多个 Toast 同时显示

使用方式：
1. 在布局文件中引入容器：
   @include('components.alpine.toast.container')

2. 在页面中使用：
   $toast.success('操作成功');
   $toast.error('操作失败');

依赖：
- components.plugin.alpinejs
- public/js/components/toast/index.js
--}}

@php
    $position = $position ?? 'top-end';
@endphp

{{-- Toast 容器：固定在页面右上角 --}}
<div
    x-data="toastContainer()"
    class="toast-container position-fixed p-3"
    :class="positionClass"
    style="z-index: 9999;"
>
    {{-- 遍历显示 Toast --}}
    <template x-for="item in toasts" :key="item.id">
        <div
            class="toast show align-items-center border-0 mb-2 shadow-sm"
            :class="[item.bgClass, item.show ? 'show' : '']"
            role="alert"
            x-show="item.show"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-x-50"
            x-transition:enter-end="opacity-100 translate-x-0"
            x-transition:leave="transition ease-in duration-300"
            x-transition:leave-start="opacity-100 translate-x-0"
            x-transition:leave-end="opacity-0 translate-x-50"
            @mouseenter="item.resetTimer()"
        >
            <div class="d-flex align-items-center">
                {{-- 图标 --}}
                <div class="toast-icon me-2">
                    <i class="bi" :class="item.iconClass"></i>
                </div>

                {{-- 消息内容 --}}
                <div class="toast-body flex-grow-1" x-text="item.message"></div>

                {{-- 关闭按钮 --}}
                @if(!isset($dismissible) || $dismissible)
                <button
                    type="button"
                    class="btn-close btn-close-white me-2 m-auto"
                    aria-label="Close"
                    @click="dismiss(item.id)"
                ></button>
                @endif
            </div>
        </div>
    </template>
</div>

<script>
document.addEventListener('alpine:init', function() {
    Alpine.data('toastContainer', function() {
        return {
            // 位置类名映射
            get positionClass() {
                const position = '{{ $position }}';
                const classes = {
                    'top-start': 'top-0 start-0',
                    'top-center': 'top-0 start-50 translate-middle-x',
                    'top-end': 'top-0 end-0',
                    'middle-start': 'top-50 start-0 translate-middle-y',
                    'middle-center': 'top-50 start-50 translate-middle',
                    'middle-end': 'top-50 end-0 translate-middle-y',
                    'bottom-start': 'bottom-0 start-0',
                    'bottom-center': 'bottom-0 start-50 translate-middle-x',
                    'bottom-end': 'bottom-0 end-0',
                };
                return classes[position] || 'top-0 end-0';
            },

            // Toast 列表
            get toasts() {
                return window.$toast?.toasts || [];
            },

            // 移除 Toast
            dismiss(id) {
                window.$toast?.remove(id);
            },

            // 初始化时同步 toasts
            init() {
                if (window.$toast) {
                    this.$watch('toasts', () => {});
                }
            }
        };
    });
});
</script>
