@php
    $columnClass = $columnClass ?? 'col-md-4';
    $iconWrapperClass = $iconWrapperClass ?? 'bg-primary bg-opacity-10 text-primary';
    $iconClass = $iconClass ?? 'bi bi-info-circle';
    $title = $title ?? '示例标题';
    $description = $description ?? '';
    $buttonHtml = $buttonHtml ?? '';
    $code = $code ?? '';
@endphp

<div class="{{ $columnClass }}">
    <div class="card border-0 shadow-sm h-100 code-example-card">
        <div class="card-body">
            <div class="d-flex align-items-center gap-2 mb-3">
                <div class="{{ $iconWrapperClass }} rounded-circle d-flex align-items-center justify-content-center"
                     style="width: 36px; height: 36px;">
                    <i class="{{ $iconClass }}"></i>
                </div>
                <h6 class="mb-0">{{ $title }}</h6>
            </div>
            @if($description !== '')
                <p class="small text-muted mb-3">
                    {{ $description }}
                </p>
            @endif

            @if($buttonHtml !== '')
                <div class="mb-3">
                    {!! $buttonHtml !!}
                </div>
            @endif

            @if($code !== '')
                <div class="bg-dark rounded-3 p-3">
                    <pre class="text-white small mb-0"
                         style="font-family: SFMono-Regular, Consolas, 'Liberation Mono', Menlo, monospace; font-size: 0.75rem; line-height: 1.5; white-space: pre-wrap; word-wrap: break-word;"><code>{{ $code }}</code></pre>
                </div>
            @endif
        </div>
    </div>
</div>

