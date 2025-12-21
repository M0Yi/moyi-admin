@extends('admin.layouts.admin')

@section('title', '登录日志')

@php
    $loginLogSearchConfig = $searchConfig ?? [];
    $hasSearchConfig = !empty($loginLogSearchConfig['search_fields'] ?? []);
@endphp

@if (! ($isEmbedded ?? false))
@push('admin_sidebar')
    @include('admin.components.sidebar')
@endpush

@push('admin_navbar')
    @include('admin.components.navbar')
@endpush
@endif

@section('content')
<div class="container-fluid py-4">
    <div class="mb-3">
        <h6 class="mb-1 fw-bold">登录日志</h6>
        <small class="text-muted">管理员登录记录</small>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            @include('admin.components.data-table-with-columns', [
                'tableId' => 'loginLogTable',
                'storageKey' => 'loginLogTableColumns',
                'ajaxUrl' => admin_route('system/login-logs'),
                'searchFormId' => 'searchForm_loginLogTable',
                'searchPanelId' => 'searchPanel_loginLogTable',
                'searchConfig' => $loginLogSearchConfig,
                'showSearch' => $hasSearchConfig,
                'showPagination' => true,
                'columns' => [
                    ['index'=>0,'label'=>'ID','field'=>'id','type'=>'text','visible'=>true,'width'=>'50'],
                    ['index'=>1,'label'=>'用户名','field'=>'username','type'=>'text','visible'=>true],
                    ['index'=>2,'label'=>'所属站点','field'=>'site.name','type'=>'text','visible'=>true,'width'=>'150'],
                    ['index'=>3,'label'=>'后台入口','field'=>'admin_entry_path','type'=>'text','visible'=>true,'width'=>'150'],
                    ['index'=>4,'label'=>'IP','field'=>'ip','type'=>'text','visible'=>true,'width'=>'150'],
                    ['index'=>5,'label'=>'状态','field'=>'status','type'=>'custom','renderFunction'=>'renderStatus','visible'=>true,'width'=>'100'],
                    ['index'=>6,'label'=>'消息','field'=>'message','type'=>'text','visible'=>true],
                    ['index'=>7,'label'=>'时间','field'=>'created_at','type'=>'date','format'=>'Y-m-d H:i:s','visible'=>true,'width'=>'150'],
                    ['index'=>8,'label'=>'操作','type'=>'actions','actions'=>[
                    ['type'=>'link','href'=>admin_route('system/login-logs').'/{id}','icon'=>'bi-eye','variant'=>'info','title'=>'查看详情','attributes'=>[
                        'data-iframe-shell-trigger' => 'login-log-show-{id}',
                        'data-iframe-shell-src' => admin_route('system/login-logs') . '/{id}',
                        'data-iframe-shell-title' => '登录日志详情',
                        'data-iframe-shell-channel' => 'login-log',
                        'data-iframe-shell-hide-actions' => 'true'
                    ]],
                        ['type'=>'button','onclick'=>'deleteRow_loginLogTable({id})','icon'=>'bi-trash','variant'=>'danger','title'=>'删除']
                    ],'visible'=>true,'width'=>'120','class'=>'sticky-column','toggleable'=>false],
                ],
                'data'=>[],
                'emptyMessage'=>'暂无登录日志',
            ])
        </div>
    </div>
</div>
@endsection

@push('admin_scripts')
@include('components.admin-script', ['path' => '/js/components/refresh-parent-listener.js'])
<script>
function renderStatus(value) {
    if (value === 1 || value === '1') return '<span class="badge bg-success">成功</span>';
    return '<span class="badge bg-warning">失败</span>';
}

document.addEventListener('DOMContentLoaded', function () {
    window.deleteRow_loginLogTable = function(id) {
        if (!confirm('确定要删除这条记录吗？')) return;
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        fetch(`{{ admin_route("system/login-logs") }}/${id}`, {
            method: 'DELETE',
            headers: {'Content-Type':'application/json','X-CSRF-TOKEN':csrfToken}
        }).then(r=>r.json()).then(data=>{
            if (data.code===200) { alert('删除成功'); location.reload(); } else { alert(data.msg||'删除失败'); }
        }).catch(()=>alert('删除失败'));
    };

    // 批量删除功能已移除
});
</script>
@endpush


