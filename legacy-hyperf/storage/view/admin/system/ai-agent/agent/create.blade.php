@extends('layouts.admin')

@section('title', '新增 AI Agent')

@section('content')
<div class="page-header">
    <h1>新增 AI Agent</h1>
    <div class="actions">
        <a href="{{ admin_route('system/ai-agents') }}" class="btn btn-secondary">返回列表</a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form id="agent-form" method="POST" action="{{ admin_route('system/ai-agents') }}">
            @csrf
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Agent名称 <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Agent标识 <span class="text-danger">*</span></label>
                        <input type="text" name="slug" class="form-control" required placeholder="例如：audit-agent">
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>类型 <span class="text-danger">*</span></label>
                        <select name="type" class="form-control" required>
                            @foreach($types as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Agent类名 <span class="text-danger">*</span></label>
                        <input type="text" name="class" class="form-control" required placeholder="例如：App\Service\AiAgent\AuditAgent">
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label>描述</label>
                <textarea name="description" class="form-control" rows="3"></textarea>
            </div>

            <div class="form-group">
                <label>图标</label>
                <input type="text" name="icon" class="form-control" placeholder="图标类名或URL">
            </div>

            <div class="form-group">
                <label>配置 (JSON)</label>
                <textarea name="config" class="form-control" rows="5" placeholder='{"key": "value"}'></textarea>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label>是否默认</label>
                        <select name="is_default" class="form-control">
                            <option value="0">否</option>
                            <option value="1">是</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>状态</label>
                        <select name="status" class="form-control">
                            <option value="1">启用</option>
                            <option value="0">禁用</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>排序</label>
                        <input type="number" name="sort" class="form-control" value="0">
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
