    <!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $errorCode ?? '错误' }} - {{ $errorTitle ?? '错误' }}</title>
    @include('components.vendor.bootstrap-css')
    @include('components.vendor.bootstrap-icons')
    @include('errors.styles', [
        'gradient' => $gradient ?? 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
        'errorColor' => $errorColor ?? '#667eea',
        'textColor' => $textColor ?? 'white',
        'codeAnimation' => $codeAnimation ?? '',
        'iconAnimation' => $iconAnimation ?? '',
    ])
</head>
<body>
    <div class="error-container">
        <div class="error-icon">
            <i class="{{ $errorIcon ?? 'bi bi-exclamation-circle' }}"></i>
        </div>
        <h1 class="error-code">{{ $errorCode ?? '错误' }}</h1>
        <h2 class="error-title">{{ $errorTitle ?? '发生错误' }}</h2>
        <p class="error-message">
            {!! $errorMessage ?? '抱歉，发生了未知错误。' !!}
        </p>

        @if(isset($customDetails) && $customDetails)
            {!! $customDetails !!}
        @elseif(isset($errorDetails) && $errorDetails)
        <div class="error-details">
            <div class="error-details-title">
                <i class="bi bi-info-circle"></i> {{ $errorDetailsTitle ?? '详细信息' }}
            </div>
            <div class="error-details-text">
                {!! $errorDetails !!}
            </div>
        </div>
        @endif

        @if(isset($buttons) && is_array($buttons) && count($buttons) > 0)
        <div>
            @foreach($buttons as $button)
                <a href="{{ $button['url'] ?? '#' }}" 
                   class="{{ $button['class'] ?? 'btn-home' }}"
                   @if(isset($button['onclick'])) onclick="{{ $button['onclick'] }}" @endif>
                    @if(isset($button['icon']))
                    <i class="{{ $button['icon'] }}"></i>
                    @endif
                    {{ $button['text'] ?? '按钮' }}
                </a>
            @endforeach
        </div>
        @endif
    </div>
</body>
</html>

