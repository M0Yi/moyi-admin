@extends('admin.layouts.admin')

@section('title', '编辑 AI Agent')

@section('content')
<div class="page-header">
    <h1>编辑 AI Agent</h1>
    <div class="actions">
        <a href="{{ admin_route('system/ai-agents') }}" class="btn btn-secondary">返回列表</a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form id="agent-form" method="POST" action="{{ admin_route('system/ai-agents') }}/{{ $agent->id }}">
            @csrf
            @method('PUT')
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Agent名称 <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required value="{{ $agent->name }}">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Agent标识 <span class="text-danger">*</span></label>
                        <input type="text" name="slug" class="form-control" required value="{{ $agent->slug }}" readonly>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>类型 <span class="text-danger">*</span></label>
                        <select name="type" class="form-control" required>
                            @foreach($types as $key => $label)
                            <option value="{{ $key }}" {{ $agent->type == $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Agent类名 <span class="text-danger">*</span></label>
                        <input type="text" name="class" class="form-control" required value="{{ $agent->class }}">
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label>描述</label>
                <textarea name="description" class="form-control" rows="3">{{ $agent->description }}</textarea>
            </div>

            <div class="form-group">
                <label>图标</label>
                <input type="text" name="icon" class="form-control" value="{{ $agent->icon }}">
            </div>

            <div class="form-group">
                <label>配置 (JSON)</label>
                <textarea name="config" class="form-control" rows="5" placeholder='{"key": "value"}'>{{ json_encode($agent->config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</textarea>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label>是否默认</label>
                        <select name="is_default" class="form-control">
                            <option value="0" {{ $agent->is_default == 0 ? 'selected' : '' }}>否</option>
                            <option value="1" {{ $agent->is_default == 1 ? 'selected' : '' }}>是</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>状态</label>
                        <select name="status" class="form-control">
                            <option value="1" {{ $agent->status == 1 ? 'selected' : '' }}>启用</option>
                            <option value="0" {{ $agent->status == 0 ? 'selected' : '' }}>禁用</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>排序</label>
                        <input type="number" name="sort" class="form-control" value="{{ $agent->sort }}">
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">保存</button>
                <a href="{{ admin_route('system/ai-agents') }}" class="btn btn-secondary">取消</a>
            </div>
        </form>
    </div>
</div>
@endsection
