@extends('layouts.admin')

@section('title', 'Provider 详情')

@section('content')
<div class="page-header">
    <h1>Provider 详情</h1>
    <div class="actions">
        <a href="{{ admin_route('system/ai-providers') }}" class="btn btn-secondary">返回列表</a>
        <a href="{{ admin_route('system/ai-providers') }}/{{ $provider->id }}/edit" class="btn btn-primary">编辑</a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <table class="table table-bordered">
            <tr>
                <th style="width: 150px;">ID</th>
                <td>{{ $provider->id }}</td>
            </tr>
            <tr>
                <th>名称</th>
                <td>{{ $provider->name }}</td>
            </tr>
            <tr>
                <th>标识</th>
                <td><code>{{ $provider->slug }}</code></td>
            </tr>
            <tr>
                <th>驱动类</th>
                <td><code>{{ $provider->driver }}</code></td>
            </tr>
            <tr>
                <th>API URL</th>
                <td><small>{{ $provider->base_url }}</small></td>
            </tr>
            <tr>
                <th>API Key</th>
                <td>{{ $provider->api_key ? '******' : '-' }}</td>
            </tr>
            <tr>
                <th>模型列表</th>
                <td>
                    @if($provider->models)
                    <pre>{{ json_encode($provider->models, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                    @else
                    -
                    @endif
                </td>
            </tr>
            <tr>
                <th>默认</th>
                <td>
                    @if($provider->is_default == 1)
                    <span class="badge badge-warning">默认</span>
                    @else
                    否
                    @endif
                </td>
            </tr>
            <tr>
                <th>状态</th>
                <td>
                    @if($provider->status == 1)
                    <span class="badge badge-success">启用</span>
                    @else
                    <span class="badge badge-danger">禁用</span>
                    @endif
                </td>
            </tr>
            <tr>
                <th>排序</th>
                <td>{{ $provider->sort }}</td>
            </tr>
            <tr>
                <th>创建时间</th>
                <td>{{ $provider->created_at }}</td>
            </tr>
            <tr>
                <th>更新时间</th>
                <td>{{ $provider->updated_at }}</td>
            </tr>
        </table>
    </div>
</div>
@endsection
