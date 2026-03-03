<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'App Name')</title>
    @stack('styles')
</head>
<body>
    @yield('content')
</body>
</html>
