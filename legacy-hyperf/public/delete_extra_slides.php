<?php
/**
 * 简单的轮播图删除页面
 * 只能通过Web访问，使用Hyperf的现有数据库连接
 */
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>删除多余轮播图</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        button { padding: 15px 30px; font-size: 18px; background: #ff5000; color: white; border: none; cursor: pointer; border-radius: 5px; }
        button:hover { background: #e64500; }
        #result { margin-top: 20px; padding: 15px; background: #f0f0f0; border-radius: 5px; }
        .success { background: #d4edda !important; color: #155724; }
        .error { background: #f8d7da !important; color: #721c24; }
        pre { white-space: pre-wrap; word-wrap: break-word; }
    </style>
</head>
<body>
    <h1>轮播图清理工具</h1>
    <p>此工具将删除ID > 1的所有轮播图，只保留ID为1的轮播图。</p>
    <button onclick="deleteSlides()">执行清理</button>
    <div id="result"></div>

    <script>
    function deleteSlides() {
        const resultDiv = document.getElementById('result');
        resultDiv.innerHTML = '<p>正在执行...</p>';

        // 获取CSRF token
        fetch('http://localhost:6501/api/v1/slides')
            .then(response => response.json())
            .then(data => {
                const slidesCount = data.data.items.length;
                resultDiv.innerHTML = `<p>当前有 ${slidesCount} 个轮播图</p>`;

                // 执行删除
                return fetch('http://localhost:6501/api/v1/slides/cleanup', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({})
                });
            })
            .then(response => response.json())
            .then(data => {
                resultDiv.className = data.code === 200 ? 'success' : 'error';
                resultDiv.innerHTML = '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
            })
            .catch(error => {
                resultDiv.className = 'error';
                resultDiv.innerHTML = '<p>错误: ' + error + '</p>';
            });
    }
    </script>
</body>
</html>
