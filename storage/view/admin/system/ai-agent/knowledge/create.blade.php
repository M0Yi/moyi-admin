@extends('layouts.admin')

@section('title', '新增知识库文档')

@section('content')
<div class="page-header">
    <h1>新增知识库文档</h1>
    <div class="actions">
        <a href="{{ admin_route('system/ai-knowledge') }}" class="btn btn-secondary">返回列表</a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ admin_route('system/ai-knowledge') }}">
            @csrf
            <div class="form-group">
                <label>所属Agent <span class="text-danger">*</span></label>
                <input type="number" name="agent_id" class="form-control" required value="{{ $agent_id ?? '' }}">
            </div>
            <div class="form-group">
                <label>标题 <span class="text-danger">*</span></label>
                <input type="text" name="title" class="form-control" required>
            </div>
            <div class="form-group">
                <label>内容 <span class="text-danger">*</span></label>
                <textarea name="content" class="form-control" rows="8" required></textarea>
            </div>
            <div class="form-group">
                <label>分类ID</label>
                <input type="number" name="category_id" class="form-control">
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>状态</label>
                        <select name="status" class="form-control">
                            <option value="1">启用</option>
                            <option value="0">禁用</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>排序</label>
                        <input type="number" name="sort" class="form-control" value="0">
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">保存</button>
            <a href="{{ admin_route('system/ai-knowledge') }}" class="btn btn-secondary">取消</a>
        </form>
    </div>
</div>
@endsection
