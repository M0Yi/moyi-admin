@extends('admin.layouts.admin')

@section('title', '知识库文档详情')

@section('content')
<div class="page-header">
    <h1>知识库文档详情</h1>
    <div class="actions">
        <a href="{{ admin_route('system/ai-knowledge') }}" class="btn btn-secondary">返回列表</a>
        <a href="{{ admin_route('system/ai-knowledge') }}/{{ $knowledge->id }}/edit" class="btn btn-primary">编辑</a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <table class="table table-bordered">
            <tr>
                <th style="width: 150px;">ID</th>
                <td>{{ $knowledge->id }}</td>
            </tr>
            <tr>
                <th>所属Agent</th>
                <td>{{ $knowledge->agent_id }}</td>
            </tr>
            <tr>
                <th>标题</th>
                <td>{{ $knowledge->title }}</td>
            </tr>
            <tr>
                <th>关键词</th>
                <td>
                    @if($knowledge->keywords)
                    @foreach($knowledge->keywords as $kw)
                    <span class="badge badge-info">{{ $kw }}</span>
                    @endforeach
                    @else
                    -
                    @endif
                </td>
            </tr>
            <tr>
                <th>状态</th>
                <td>
                    @if($knowledge->status == 1)
                    <span class="badge badge-success">启用</span>
                    @else
                    <span class="badge badge-danger">禁用</span>
                    @endif
                </td>
            </tr>
            <tr>
                <th>排序</th>
                <td>{{ $knowledge->sort }}</td>
            </tr>
            <tr>
                <th>创建时间</th>
                <td>{{ $knowledge->created_at }}</td>
            </tr>
        </table>

        <h5>内容</h5>
        <div class="bg-light p-3 rounded">
            {{ $knowledge->content }}
        </div>
    </div>
</div>
@endsection
