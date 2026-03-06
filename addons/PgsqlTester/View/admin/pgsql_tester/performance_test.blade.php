@extends('layouts.admin')

@section('title', 'PostgreSQL 性能测试')

@push('styles')
<link rel="stylesheet" href="/css/pgsql_tester.css">
@endpush

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="bi bi-graph-up"></i> PostgreSQL 性能测试
                    </h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-success btn-sm" onclick="runTest()">
                            <i class="bi bi-play"></i> 开始测试
                        </button>
                        <button type="button" class="btn btn-tool" data-card-widget="collapse">
                            <i class="fas fa-minus"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <!-- 测试配置 -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title">测试配置</h5>
                                </div>
                                <div class="card-body">
                                    <form id="performance-form">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="iterations">迭代次数</label>
                                                    <input type="number" class="form-control" id="iterations" name="iterations" value="100" min="1" max="10000">
                                                    <small class="form-text text-muted">每次测试执行的次数</small>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="query">测试查询</label>
                                                    <input type="text" class="form-control" id="query" name="query" value="SELECT 1">
                                                    <small class="form-text text-muted">用于性能测试的SQL查询</small>
                                                </div>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 测试进度 -->
                    <div class="row mb-4" id="progress-section" style="display: none;">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body">
                                    <div class="progress">
                                        <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%" id="test-progress"></div>
                                    </div>
                                    <div class="mt-2">
                                        <small class="text-muted" id="progress-text">准备开始测试...</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 测试结果 -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card" id="result-card" style="display: none;">
                                <div class="card-header">
                                    <h5 class="card-title" id="result-title">性能测试结果</h5>
                                    <div class="card-tools">
                                        <button type="button" class="btn btn-tool" onclick="exportResult()">
                                            <i class="bi bi-download"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <!-- 性能指标 -->
                                    <div class="row mb-4" id="performance-metrics">
                                        <div class="col-md-3">
                                            <div class="info-box">
                                                <span class="info-box-icon bg-primary">
                                                    <i class="bi bi-hash"></i>
                                                </span>
                                                <div class="info-box-content">
                                                    <span class="info-box-text">总迭代次数</span>
                                                    <span class="info-box-number" id="total-iterations">0</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="info-box">
                                                <span class="info-box-icon bg-success">
                                                    <i class="bi bi-clock"></i>
                                                </span>
                                                <div class="info-box-content">
                                                    <span class="info-box-text">平均响应时间</span>
                                                    <span class="info-box-number" id="avg-time">0ms</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="info-box">
                                                <span class="info-box-icon bg-warning">
                                                    <i class="bi bi-trophy"></i>
                                                </span>
                                                <div class="info-box-content">
                                                    <span class="info-box-text">最快响应</span>
                                                    <span class="info-box-number" id="min-time">0ms</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="info-box">
                                                <span class="info-box-icon bg-info">
                                                    <i class="bi bi-speedometer2"></i>
                                                </span>
                                                <div class="info-box-content">
                                                    <span class="info-box-text">QPS</span>
                                                    <span class="info-box-number" id="qps">0</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- 详细结果 -->
                                    <div class="row">
                                        <div class="col-12">
                                            <pre id="result-details" class="bg-light p-3 rounded" style="max-height: 400px; overflow-y: auto;"></pre>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="/js/pgsql_tester.js"></script>
<script>
$(document).ready(function() {
    console.log('PostgreSQL 性能测试页面已加载');
});

let currentTestResult = null;

// 执行测试
function runTest() {
    const formData = new FormData(document.getElementById('performance-form'));
    const iterations = formData.get('iterations');
    const query = formData.get('query');

    if (!iterations || iterations < 1) {
        toastr.error('请输入有效的迭代次数');
        return;
    }

    if (!query || query.trim() === '') {
        toastr.error('请输入测试查询');
        return;
    }

    // 显示进度条
    $('#progress-section').show();
    $('#progress-text').text('正在执行性能测试...');
    $('#test-progress').css('width', '0%');

    // 重置结果显示
    $('#result-card').hide();

    fetch('/api/pgsql_tester/performance', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
        },
        body: JSON.stringify({
            iterations: parseInt(iterations),
            query: query.trim()
        })
    })
    .then(response => {
        $('#test-progress').css('width', '50%');
        $('#progress-text').text('正在处理结果...');
        return response.json();
    })
    .then(data => {
        $('#test-progress').css('width', '100%');
        $('#progress-text').text('测试完成');

        setTimeout(() => {
            $('#progress-section').hide();
        }, 1000);

        if (data.code === 200) {
            currentTestResult = data.data;
            displayResult(data.data);
            toastr.success('性能测试完成');
        } else {
            toastr.error(data.message || '性能测试失败');
        }
    })
    .catch(error => {
        $('#progress-section').hide();
        toastr.error('性能测试异常: ' + error.message);
    });
}

// 显示测试结果
function displayResult(data) {
    $('#result-card').show();
    $('#result-title').text('性能测试结果 - ' + new Date().toLocaleString());

    // 更新性能指标
    $('#total-iterations').text(data.iterations || 0);
    $('#avg-time').text(data.average_time || '0ms');
    $('#min-time').text(data.min_time || '0ms');
    $('#qps').text(data.qps || 0);

    // 显示详细结果
    $('#result-details').text(JSON.stringify(data, null, 2));

    // 滚动到结果区域
    $('#result-card')[0].scrollIntoView({ behavior: 'smooth' });
}

// 导出结果
function exportResult() {
    if (!currentTestResult) {
        toastr.warning('没有可导出的测试结果');
        return;
    }

    const exportData = {
        timestamp: new Date().toISOString(),
        test_result: currentTestResult,
        configuration: {
            iterations: $('#iterations').val(),
            query: $('#query').val()
        }
    };

    const blob = new Blob([JSON.stringify(exportData, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);

    const a = document.createElement('a');
    a.href = url;
    a.download = `pgsql_performance_test_${Date.now()}.json`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);

    toastr.success('测试结果已导出');
}
</script>
@endpush
