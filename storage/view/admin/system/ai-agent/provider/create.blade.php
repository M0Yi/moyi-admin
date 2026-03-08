@extends('admin.layouts.admin')

@section('title', '新增 Provider')

@section('content')
<div class="page-header">
    <h1>新增 Provider</h1>
    <div class="actions">
        <a href="{{ admin_route('system/ai-providers') }}" class="btn btn-secondary">返回列表</a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ admin_route('system/ai-providers') }}">
            @csrf
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>名称 <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>标识 <span class="text-danger">*</span></label>
                        <input type="text" name="slug" class="form-control" required placeholder="例如：zhipu">
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>驱动类 <span class="text-danger">*</span></label>
                        <input type="text" name="driver" class="form-control" required placeholder="例如：App\Service\AiAgent\Provider\ZhipuProvider">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>API URL <span class="text-danger">*</span></label>
                        <input type="text" name="base_url" class="form-control" required>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label>API Key</label>
                <input type="password" name="api_key" class="form-control">
            </div>

            <div class="form-group">
                <label>模型列表 (JSON)</label>
                <textarea name="models" class="form-control" rows="3" placeholder='[{"id": "glm-4-flash", "name": "GLM-4-Flash"}]'></textarea>
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

            <button type="submit" class="btn btn-primary">保存</button>
            <a href="{{ admin_route('system/ai-providers') }}" class="btn btn-secondary">取消</a>
        </form>
    </div>
</div>
@endsection
