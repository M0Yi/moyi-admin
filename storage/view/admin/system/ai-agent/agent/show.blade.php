@extends('layouts.admin')

@section('title', 'Agent 详情')

@section('content')
<div class="page-header">
    <h1>Agent 详情</h1>
    <div class="actions">
        <a href="{{ admin_route('system/ai-agents') }}" class="btn btn-secondary">返回列表</a>
        <a href="{{ admin_route('system/ai-agents') }}/{{ $agent->id }}/edit" class="btn btn-primary">编辑</a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <table class="table table-bordered">
            <tr>
                <th style="width: 150px;">ID</th>
                <td>{{ $agent->id }}</td>
            </tr>
            <tr>
                <th>名称</th>
                <td>{{ $agent->name }}</td>
            </tr>
            <tr>
                <th>标识</th>
                <td><code>{{ $agent->slug }}</code></td>
            </tr>
            <tr>
                <th>类型</th>
                <td>{{ $agent->type }}</td>
            </tr>
            <tr>
                <th>类名</th>
                <td><code>{{ $agent->class }}</code></td>
            </tr>
            <tr>
                <th>描述</th>
                <td>{{ $agent->description ?? '-' }}</td>
            </tr>
            <tr>
                <th>图标</th>
                <td>{{ $agent->icon ?? '-' }}</td>
            </tr>
            <tr>
                <th>配置</th>
                <td><pre>{{ json_encode($agent->config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></td>
            </tr>
            <tr>
                <th>状态</th>
                <td>
                    @if($agent->status == 1)
                    <span class="badge badge-success">启用</span>
                    @else
                    <span class="badge badge-danger">禁用</span>
                    @endif
                </td>
            </tr>
            <tr>
                <th>默认</th>
                <td>
                    @if($agent->is_default == 1)
                    <span class="badge badge-warning">默认</span>
                    @else
                    否
                    @endif
                </td>
            </tr>
            <tr>
                <th>排序</th>
                <td>{{ $agent->sort }}</td>
            </tr>
            <tr>
                <th>创建时间</th>
                <td>{{ $agent->created_at }}</td>
            </tr>
            <tr>
                <th>更新时间</th>
                <td>{{ $agent->updated_at }}</td>
            </tr>
        </table>
    </div>
</div>
@endsection
