@extends('layouts.admin')

@section('title', '编辑 Provider')

@section('content')
<div class="page-header">
    <h1>编辑 Provider</h1>
    <div class="actions">
        <a href="{{ admin_route('system/ai-providers') }}" class="btn btn-secondary">返回列表</a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ admin_route('system/ai-providers') }}/{{ $provider->id }}">
            @csrf
            @method('PUT')
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>名称 <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required value="{{ $provider->name }}">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>标识 <span class="text-danger">*</span></label>
                        <input type="text" name="slug" class="form-control" required value="{{ $provider->slug }}" readonly>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>驱动类 <span class="text-danger">*</span></label>
                        <input type="text" name="driver" class="form-control" required value="{{ $provider->driver }}">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>API URL <span class="text-danger">*</span></label>
                        <input type="text" name="base_url" class="form-control" required value="{{ $provider->base_url }}">
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label>API Key（留空则不修改）</label>
                <input type="password" name="api_key" class="form-control" placeholder="留空则保持原值">
            </div>

            <div class="form-group">
                <label>模型列表 (JSON)</label>
                <textarea name="models" class="form-control" rows="3">{{ json_encode($provider->models ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</textarea>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label>是否默认</label>
                        <select name="is_default" class="form-control">
                            <option value="0" {{ $provider->is_default == 0 ? 'selected' : '' }}>否</option>
                            <option value="1" {{ $provider->is_default == 1 ? 'selected' : '' }}>是</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>状态</label>
                        <select name="status" class="form-control">
                            <option value="1" {{ $provider->status == 1 ? 'selected' : '' }}>启用</option>
                            <option value="0" {{ $provider->status == 0 ? 'selected' : '' }}>禁用</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>排序</label>
                        <input type="number" name="sort" class="form-control" value="{{ $provider->sort }}">
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">保存</button>
            <a href="{{ admin_route('system/ai-providers') }}" class="btn btn-secondary">取消</a>
        </form>
    </div>
</div>
@endsection
