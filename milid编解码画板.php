<?php
/**
 * 画板系统 - 简化版本
 * 只包含文件头和像素数据，无加密无防伪
 */
session_start();

define('BOARD_WIDTH', 1920);
define('BOARD_HEIGHT', 1080);
define('BOARD_SIZE', BOARD_WIDTH * BOARD_HEIGHT);

$presetColors = [
    '#000000', '#FFFFFF', '#FF0000', '#00FF00', '#0000FF',
    '#FFFF00', '#FF00FF', '#00FFFF', '#FFA500', '#808080',
    '#800000', '#008000', '#000080', '#808000', '#800080',
    '#008080', '#C0C0C0', '#FFC0CB', '#FFD700', '#4B0082'
];

// 仅处理导入错误的显示
$error = isset($_SESSION['import_error']) ? $_SESSION['import_error'] : null;
unset($_SESSION['import_error']);

$importedData = isset($_SESSION['imported_data']) ? $_SESSION['imported_data'] : 'null';
unset($_SESSION['imported_data']);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>画板</title>
    <style>
        * { margin: 0; padding: 0; }
        body { 
            font-family: Arial; 
            background: #1a1a1a; 
            color: white;
            height: 100vh;
            overflow: hidden;
        }
        #app {
            display: flex;
            height: 100vh;
        }
        #canvasContainer {
            flex: 1;
            background: #000;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
        }
        #mainCanvas {
            background: white;
            cursor: crosshair;
        }
        #sidebar {
            width: 60px;
            background: #2d2d2d;
            border-left: 1px solid #444;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 10px 0;
            gap: 10px;
        }
        .menu-btn {
            width: 44px;
            height: 44px;
            background: #444;
            border: none;
            border-radius: 4px;
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }
        .menu-btn:hover {
            background: #555;
        }
        .menu-btn.active {
            background: #007bff;
        }
        .submenu {
            position: absolute;
            right: 70px;
            top: 10px;
            background: #2d2d2d;
            border-radius: 6px;
            padding: 15px;
            min-width: 200px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            display: none;
            z-index: 100;
            max-height: 80vh;
            overflow-y: auto;
        }
        .submenu.active {
            display: block;
        }
        .section {
            margin-bottom: 15px;
        }
        .section-title {
            font-size: 12px;
            color: #aaa;
            margin-bottom: 8px;
        }
        .color-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 6px;
            margin-bottom: 15px;
        }
        .color-item {
            width: 30px;
            height: 30px;
            border-radius: 4px;
            cursor: pointer;
            border: 2px solid transparent;
        }
        .color-item:hover {
            border-color: #fff;
        }
        .slider-container {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        input[type="range"] {
            flex: 1;
        }
        .preview {
            width: 40px;
            height: 40px;
            border-radius: 4px;
            border: 2px solid #555;
        }
        #colorInput {
            width: 100%;
            padding: 5px;
            border: 1px solid #555;
            background: #333;
            color: white;
            border-radius: 4px;
        }
        .file-btn {
            width: 100%;
            padding: 8px;
            margin: 5px 0;
            background: #444;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .file-btn:hover {
            background: #555;
        }
        .file-btn:disabled {
            background: #333;
            color: #777;
            cursor: not-allowed;
        }
        #status {
            position: fixed;
            bottom: 10px;
            left: 10px;
            background: rgba(0,0,0,0.7);
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
        }
        #progressLog {
            font-size: 11px;
            color: #aaa;
            background: #222;
            padding: 8px;
            border-radius: 4px;
            max-height: 150px;
            overflow-y: auto;
            margin-top: 10px;
            display: none;
        }
        .log-entry {
            margin: 2px 0;
            font-family: monospace;
        }
        .log-success { color: #4CAF50; }
        .log-error { color: #f44336; }
        .log-info { color: #2196F3; }
        .log-warning { color: #ff9800; }
        progress {
            width: 100%;
            height: 8px;
            border-radius: 4px;
            background: #333;
        }
        progress::-webkit-progress-bar {
            background: #333;
            border-radius: 4px;
        }
        progress::-webkit-progress-value {
            background: #007bff;
            border-radius: 4px;
        }
        .error-message {
            background: #f44336;
            color: white;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div id="app">
        <div id="canvasContainer">
            <canvas id="mainCanvas"></canvas>
        </div>
        
        <div id="sidebar">
            <button class="menu-btn" onclick="toggleMenu('brush')" title="画笔设置">🖌️</button>
            <button class="menu-btn" onclick="toggleMenu('color')" title="颜色">🎨</button>
            <button class="menu-btn" onclick="toggleMenu('file')" title="文件">📁</button>
            <button class="menu-btn" onclick="clearCanvas()" title="清空">🗑️</button>
        </div>
        
        <div id="brushMenu" class="submenu">
            <div class="section">
                <div class="section-title">工具</div>
                <div style="display: flex; gap: 10px;">
                    <button class="file-btn" onclick="setTool('brush')" id="btnBrush">画笔</button>
                    <button class="file-btn" onclick="setTool('eraser')" id="btnEraser">橡皮</button>
                </div>
            </div>
            <div class="section">
                <div class="section-title">粗细: <span id="brushSizeValue">5</span>px</div>
                <div class="slider-container">
                    <input type="range" id="brushSize" min="1" max="50" value="5">
                </div>
            </div>
        </div>
        
        <div id="colorMenu" class="submenu">
            <div class="section">
                <div class="section-title">预设颜色</div>
                <div class="color-grid">
                    <?php foreach ($presetColors as $color): ?>
                        <div class="color-item" style="background:<?php echo $color; ?>" 
                             onclick="setColor('<?php echo $color; ?>')"></div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="section">
                <div class="section-title">自定义</div>
                <input type="color" id="colorPicker" value="#000000" style="width:100%;height:40px;margin-bottom:10px;">
                <input type="text" id="colorInput" placeholder="#RRGGBB" onchange="applyColorInput()">
            </div>
            <div class="section">
                <div class="section-title">当前颜色</div>
                <div id="colorPreview" class="preview" style="background:#000000"></div>
            </div>
        </div>
        
        <div id="fileMenu" class="submenu">
            <button class="file-btn" onclick="exportCanvas()" id="exportBtn">导出 (.milib)</button>
            
            <!-- 文件导入选择 -->
            <input type="file" id="importFileInput" accept=".milib" style="display:none;">
            <button class="file-btn" onclick="importCanvas()" id="importBtn">导入 (.milib)</button>
            
            <!-- 导出进度显示 -->
            <div id="exportProgress" style="margin-top: 15px; display: none;">
                <div class="section-title">导出进度</div>
                <progress id="exportProgressBar" value="0" max="100"></progress>
                <div id="progressLog"></div>
            </div>
        </div>
        
        <div id="status">就绪</div>
    </div>

    <?php if ($error): ?>
    <script>
        // 显示导入错误
        document.addEventListener('DOMContentLoaded', function() {
            alert('导入错误: <?php echo addslashes($error); ?>');
        });
    </script>
    <?php endif; ?>

    <script>
        // ============================ 核心画板逻辑 ============================
        const canvas = document.getElementById('mainCanvas');
        const ctx = canvas.getContext('2d');
        
        // 画笔连贯性变量
        let lastX = 0;
        let lastY = 0;
        let isDrawing = false;
        let isExporting = false;
        let isImporting = false;
        
        // 计算Canvas尺寸
        function resizeCanvas() {
            const container = document.getElementById('canvasContainer');
            const containerWidth = container.clientWidth;
            const containerHeight = container.clientHeight;
            
            const scale = Math.min(containerWidth / 1920, containerHeight / 1080);
            
            canvas.width = 1920;
            canvas.height = 1080;
            canvas.style.width = (1920 * scale) + 'px';
            canvas.style.height = (1080 * scale) + 'px';
            
            return scale;
        }
        
        let canvasScale = resizeCanvas();
        
        // 画板数据
        let boardData = new Array(1920 * 1080).fill('#FFFFFF');
        let currentTool = 'brush';
        let brushSize = 5;
        let currentColor = '#000000';
        
        // 日志系统
        const log = {
            entries: [],
            maxEntries: 15,
            
            add: function(type, message) {
                const timestamp = new Date().toLocaleTimeString();
                const entry = {
                    time: timestamp,
                    type: type,
                    message: message
                };
                
                this.entries.unshift(entry);
                if (this.entries.length > this.maxEntries) {
                    this.entries.pop();
                }
                
                this.updateDisplay();
            },
            
            updateDisplay: function() {
                const logDiv = document.getElementById('progressLog');
                if (!logDiv) return;
                
                logDiv.innerHTML = '';
                this.entries.forEach(entry => {
                    const div = document.createElement('div');
                    div.className = `log-entry log-${entry.type}`;
                    div.textContent = `[${entry.time}] ${entry.message}`;
                    logDiv.appendChild(div);
                });
                
                logDiv.scrollTop = 0;
            },
            
            clear: function() {
                this.entries = [];
                this.updateDisplay();
            },
            
            showError: function(message) {
                this.add('error', message);
                alert('错误: ' + message);
            }
        };
        
        // 初始化画板
        function initCanvas() {
            ctx.fillStyle = '#FFFFFF';
            ctx.fillRect(0, 0, 1920, 1080);
            
            <?php if ($importedData != 'null'): ?>
            try {
                const imported = <?php echo $importedData; ?>;
                if (imported && imported.length === 1920 * 1080) {
                    boardData = imported;
                    redrawCanvas();
                    log.add('success', '成功导入文件数据');
                }
            } catch (e) {
                console.error('加载导入数据失败:', e);
                log.showError('导入失败: ' + e.message);
            }
            <?php endif; ?>
            
            updateStatus('就绪');
        }
        
        // 重绘画板
        function redrawCanvas() {
            const imageData = ctx.getImageData(0, 0, 1920, 1080);
            const data = imageData.data;
            
            for (let i = 0; i < boardData.length; i++) {
                const color = boardData[i];
                const rgb = hexToRgb(color);
                const idx = i * 4;
                
                data[idx] = rgb.r;
                data[idx + 1] = rgb.g;
                data[idx + 2] = rgb.b;
                data[idx + 3] = 255;
            }
            
            ctx.putImageData(imageData, 0, 0);
        }
        
        // 工具函数
        function hexToRgb(hex) {
            // 处理简写形式 #FFF
            if (hex.length === 4) {
                const r = hex[1];
                const g = hex[2];
                const b = hex[3];
                hex = '#' + r + r + g + g + b + b;
            }
            
            const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
            return result ? {
                r: parseInt(result[1], 16),
                g: parseInt(result[2], 16),
                b: parseInt(result[3], 16)
            } : { r: 0, g: 0, b: 0 };
        }
        
        function rgbToHex(r, g, b) {
            return "#" + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1);
        }
        
        // 获取Canvas坐标
        function getCanvasCoords(e) {
            const rect = canvas.getBoundingClientRect();
            const scaleX = 1920 / rect.width;
            const scaleY = 1080 / rect.height;
            
            const x = Math.floor((e.clientX - rect.left) * scaleX);
            const y = Math.floor((e.clientY - rect.top) * scaleY);
            
            return { x, y };
        }
        
        // 绘制点 - 修复连贯性问题
        function drawLine(x0, y0, x1, y1) {
            const color = currentTool === 'eraser' ? '#FFFFFF' : currentColor;
            const radius = brushSize;
            
            // 计算两点之间的直线距离
            const dx = Math.abs(x1 - x0);
            const dy = Math.abs(y1 - y0);
            const sx = (x0 < x1) ? 1 : -1;
            const sy = (y0 < y1) ? 1 : -1;
            let err = dx - dy;
            
            while (true) {
                // 绘制当前点（圆形笔刷）
                for (let dy2 = -radius; dy2 <= radius; dy2++) {
                    for (let dx2 = -radius; dx2 <= radius; dx2++) {
                        const px = x0 + dx2;
                        const py = y0 + dy2;
                        
                        if (px >= 0 && px < 1920 && py >= 0 && py < 1080) {
                            const distance = Math.sqrt(dx2 * dx2 + dy2 * dy2);
                            
                            if (distance <= radius) {
                                const index = py * 1920 + px;
                                boardData[index] = color;
                                
                                ctx.fillStyle = color;
                                ctx.fillRect(px, py, 1, 1);
                            }
                        }
                    }
                }
                
                if (x0 === x1 && y0 === y1) break;
                const e2 = 2 * err;
                if (e2 > -dy) {
                    err -= dy;
                    x0 += sx;
                }
                if (e2 < dx) {
                    err += dx;
                    y0 += sy;
                }
            }
        }
        
        // 状态更新
        function updateStatus(text) {
            document.getElementById('status').textContent = text;
        }
        
        // 清空画板
        function clearCanvas() {
            if (confirm('确定要清空画板吗？')) {
                boardData.fill('#FFFFFF');
                ctx.fillStyle = '#FFFFFF';
                ctx.fillRect(0, 0, 1920, 1080);
                updateStatus('画板已清空');
                log.add('info', '清空画板');
            }
        }
        
        // 设置工具
        function setTool(tool) {
            currentTool = tool;
            document.getElementById('btnBrush').style.background = tool === 'brush' ? '#007bff' : '#444';
            document.getElementById('btnEraser').style.background = tool === 'eraser' ? '#007bff' : '#444';
            updateStatus(tool === 'brush' ? '画笔模式' : '橡皮擦模式');
        }
        
        // 设置颜色
        function setColor(color) {
            currentColor = color;
            document.getElementById('colorPicker').value = color;
            document.getElementById('colorPreview').style.backgroundColor = color;
            updateStatus('颜色: ' + color);
        }
        
        // 应用颜色输入
        function applyColorInput() {
            const input = document.getElementById('colorInput').value.trim();
            if (/^#([0-9A-F]{3}){1,2}$/i.test(input)) {
                setColor(input.toUpperCase());
            } else if (/^([0-9A-F]{6})$/i.test(input)) {
                setColor('#' + input.toUpperCase());
            } else {
                alert('请输入有效的十六进制颜色，如 #FF0000 或 FF0000');
            }
        }
        
        // 切换菜单
        function toggleMenu(menuId) {
            const menus = ['brushMenu', 'colorMenu', 'fileMenu'];
            menus.forEach(id => {
                const menu = document.getElementById(id);
                menu.classList.remove('active');
            });
            
            const activeMenu = document.getElementById(menuId + 'Menu');
            activeMenu.classList.toggle('active');
            
            // 如果是文件菜单，显示/隐藏导出进度
            if (menuId === 'file') {
                const progressDiv = document.getElementById('exportProgress');
                progressDiv.style.display = 'block';
            }
        }
        
        // 纯前端导出画板（无加密，无防伪）
        async function exportCanvas() {
            if (isExporting) return;
            
            isExporting = true;
            const exportBtn = document.getElementById('exportBtn');
            const progressBar = document.getElementById('exportProgressBar');
            const progressDiv = document.getElementById('exportProgress');
            
            // 更新UI状态
            exportBtn.disabled = true;
            exportBtn.textContent = '导出中...';
            progressDiv.style.display = 'block';
            progressBar.value = 0;
            log.clear();
            
            const startTime = Date.now();
            log.add('info', '开始导出画板...');
            
            try {
                // 1. 准备数据
                log.add('info', '准备数据...');
                progressBar.value = 10;
                
                const version = 1;
                const timestamp = Math.floor(Date.now() / 1000);
                const date = new Date(timestamp * 1000);
                const dateStr = `${date.getFullYear()}.${(date.getMonth()+1).toString().padStart(2, '0')}.${date.getDate().toString().padStart(2, '0')}.${date.getHours().toString().padStart(2, '0')}.${date.getMinutes().toString().padStart(2, '0')}.${date.getSeconds().toString().padStart(2, '0')}`;
                
                log.add('info', `文件时间戳: ${dateStr}`);
                log.add('info', `文件版本: ${version}`);
                
                // 2. 创建文件头
                log.add('info', '创建文件头...');
                progressBar.value = 20;
                
                // 文件头：版本(1字节) + 时间戳(4字节) + 宽度(2字节) + 高度(2字节) + 保留(7字节)
                const header = new ArrayBuffer(16);
                const headerView = new DataView(header);
                headerView.setUint8(0, version); // 版本
                headerView.setUint32(1, timestamp, false); // 时间戳（大端）
                headerView.setUint16(5, 1920, false); // 宽度
                headerView.setUint16(7, 1080, false); // 高度
                // 后7字节保留为0
                
                // 3. 创建像素数据
                log.add('info', '生成像素数据...');
                progressBar.value = 30;
                
                const totalPixels = 1920 * 1080;
                const expectedFileSize = 16 + (totalPixels * 3); // 文件头16字节 + 每个像素3字节
                log.add('info', `总像素数: ${totalPixels.toLocaleString()}`);
                log.add('info', `预计文件大小: ${(expectedFileSize / 1024 / 1024).toFixed(2)} MB`);
                
                const pixelData = new Uint8Array(totalPixels * 3);
                
                // 分块处理，避免阻塞
                const chunkSize = 50000;
                for (let i = 0; i < totalPixels; i += chunkSize) {
                    const end = Math.min(i + chunkSize, totalPixels);
                    for (let j = i; j < end; j++) {
                        const color = boardData[j];
                        const rgb = hexToRgb(color);
                        const offset = j * 3;
                        pixelData[offset] = rgb.r;
                        pixelData[offset + 1] = rgb.g;
                        pixelData[offset + 2] = rgb.b;
                    }
                    
                    // 更新进度
                    const progress = 30 + Math.floor((i / totalPixels) * 40);
                    progressBar.value = progress;
                    
                    // 定期让出主线程避免阻塞
                    if (i % (chunkSize * 10) === 0) {
                        await new Promise(resolve => setTimeout(resolve, 0));
                    }
                }
                
                // 4. 合并文件头和像素数据
                log.add('info', '合并数据...');
                progressBar.value = 70;
                
                const fileData = new Uint8Array(header.byteLength + pixelData.byteLength);
                fileData.set(new Uint8Array(header), 0);
                fileData.set(pixelData, header.byteLength);
                
                log.add('info', `实际文件大小: ${fileData.length} 字节`);
                
                // 5. 验证文件大小
                if (fileData.length !== expectedFileSize) {
                    throw new Error(`文件大小不匹配: 预期 ${expectedFileSize} 字节，实际 ${fileData.length} 字节`);
                }
                
                // 6. 创建Blob并下载
                log.add('info', '创建下载文件...');
                progressBar.value = 90;
                
                const blob = new Blob([fileData], { type: 'application/octet-stream' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `canvas_${dateStr}.milib`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
                
                // 完成
                const endTime = Date.now();
                const duration = ((endTime - startTime) / 1000).toFixed(2);
                
                progressBar.value = 100;
                log.add('success', `导出完成！用时: ${duration}秒`);
                log.add('info', `文件已保存为: canvas_${dateStr}.milib`);
                
                updateStatus(`导出完成 (${duration}s)`);
                
            } catch (error) {
                log.add('error', `导出失败: ${error.message}`);
                console.error('导出错误:', error);
                log.showError(`导出失败: ${error.message}`);
                updateStatus('导出失败');
            } finally {
                // 恢复UI状态
                exportBtn.disabled = false;
                exportBtn.textContent = '导出 (.milib)';
                isExporting = false;
            }
        }
        
        // 纯前端导入画板
        async function importCanvas() {
            if (isImporting) return;
            
            const importBtn = document.getElementById('importBtn');
            const importInput = document.getElementById('importFileInput');
            
            // 创建一个Promise包装的文件选择
            const file = await new Promise(resolve => {
                importInput.onchange = (e) => resolve(e.target.files[0]);
                importInput.click();
            });
            
            if (!file) return;
            
            isImporting = true;
            importBtn.disabled = true;
            importBtn.textContent = '导入中...';
            
            const startTime = Date.now();
            log.clear();
            log.add('info', `开始导入文件: ${file.name}`);
            log.add('info', `文件大小: ${(file.size / 1024 / 1024).toFixed(2)} MB`);
            
            try {
                // 读取文件
                const arrayBuffer = await file.arrayBuffer();
                
                log.add('info', `读取完成，文件大小: ${arrayBuffer.byteLength} 字节`);
                
                // 检查最小文件大小
                const minFileSize = 16; // 至少要有文件头
                if (arrayBuffer.byteLength < minFileSize) {
                    throw new Error(`文件过小，至少需要 ${minFileSize} 字节的文件头`);
                }
                
                // 解析文件头
                const headerView = new DataView(arrayBuffer, 0, 16);
                const version = headerView.getUint8(0);
                const timestamp = headerView.getUint32(1, false);
                const width = headerView.getUint16(5, false);
                const height = headerView.getUint16(7, false);
                
                const date = new Date(timestamp * 1000);
                const dateStr = `${date.getFullYear()}.${(date.getMonth()+1).toString().padStart(2, '0')}.${date.getDate().toString().padStart(2, '0')}.${date.getHours().toString().padStart(2, '0')}.${date.getMinutes().toString().padStart(2, '0')}.${date.getSeconds().toString().padStart(2, '0')}`;
                
                log.add('info', `文件版本: ${version}`);
                log.add('info', `创建时间: ${dateStr}`);
                log.add('info', `画板尺寸: ${width}x${height}`);
                
                // 验证版本号
                if (version !== 1) {
                    throw new Error(`不支持的文件版本: ${version}，仅支持版本1`);
                }
                
                // 验证尺寸
                if (width !== 1920 || height !== 1080) {
                    throw new Error(`画板尺寸不匹配，应为1920x1080，实际为${width}x${height}`);
                }
                
                // 计算预期文件大小
                const totalPixels = 1920 * 1080;
                const expectedFileSize = 16 + (totalPixels * 3); // 文件头16字节 + 每个像素3字节
                
                log.add('info', `预期文件大小: ${expectedFileSize} 字节`);
                
                // 验证文件大小
                if (arrayBuffer.byteLength !== expectedFileSize) {
                    throw new Error(`文件大小不匹配: 预期 ${expectedFileSize} 字节，实际 ${arrayBuffer.byteLength} 字节。文件可能已损坏或不完整。`);
                }
                
                // 解析像素数据
                log.add('info', '解析像素数据...');
                
                const data = new Uint8Array(arrayBuffer);
                const newBoardData = new Array(totalPixels);
                
                // 分块处理
                const chunkSize = 50000;
                for (let i = 0; i < totalPixels; i += chunkSize) {
                    const end = Math.min(i + chunkSize, totalPixels);
                    for (let j = i; j < end; j++) {
                        const offset = 16 + (j * 3);
                        
                        // 检查数据边界
                        if (offset + 2 >= data.length) {
                            throw new Error(`像素数据越界: 像素 ${j}，偏移量 ${offset}，数据长度 ${data.length}`);
                        }
                        
                        const r = data[offset];
                        const g = data[offset + 1];
                        const b = data[offset + 2];
                        
                        // 验证RGB值在有效范围内
                        if (r < 0 || r > 255 || g < 0 || g > 255 || b < 0 || b > 255) {
                            throw new Error(`无效的像素数据: 像素 ${j}，RGB(${r},${g},${b})`);
                        }
                        
                        newBoardData[j] = rgbToHex(r, g, b);
                    }
                    
                    // 定期让出主线程
                    if (i % (chunkSize * 10) === 0) {
                        await new Promise(resolve => setTimeout(resolve, 0));
                    }
                }
                
                // 更新画板
                boardData = newBoardData;
                redrawCanvas();
                
                const endTime = Date.now();
                const duration = ((endTime - startTime) / 1000).toFixed(2);
                
                log.add('success', `导入完成！用时: ${duration}秒`);
                log.add('success', `成功导入 ${totalPixels.toLocaleString()} 个像素`);
                updateStatus(`导入完成 (${duration}s)`);
                
                // 显示成功提示
                alert(`导入成功！\n文件版本: ${version}\n创建时间: ${dateStr}\n像素数量: ${totalPixels.toLocaleString()}`);
                
            } catch (error) {
                log.add('error', `导入失败: ${error.message}`);
                console.error('导入错误详情:', error);
                log.showError(`导入失败: ${error.message}`);
                updateStatus('导入失败');
                
                // 显示详细错误信息
                const errorDetails = `错误详情:\n${error.message}\n\n文件: ${file.name}\n大小: ${file.size} 字节\n时间: ${new Date().toLocaleString()}`;
                console.error('导入错误:', errorDetails);
            } finally {
                // 恢复UI状态
                importBtn.disabled = false;
                importBtn.textContent = '导入 (.milib)';
                isImporting = false;
                
                // 重置文件输入
                importInput.value = '';
            }
        }
        
        // 事件监听
        canvas.addEventListener('mousedown', (e) => {
            isDrawing = true;
            const coords = getCanvasCoords(e);
            lastX = coords.x;
            lastY = coords.y;
            
            // 绘制起始点
            const color = currentTool === 'eraser' ? '#FFFFFF' : currentColor;
            const radius = brushSize;
            
            for (let dy = -radius; dy <= radius; dy++) {
                for (let dx = -radius; dx <= radius; dx++) {
                    const px = lastX + dx;
                    const py = lastY + dy;
                    
                    if (px >= 0 && px < 1920 && py >= 0 && py < 1080) {
                        const distance = Math.sqrt(dx * dx + dy * dy);
                        
                        if (distance <= radius) {
                            const index = py * 1920 + px;
                            boardData[index] = color;
                            
                            ctx.fillStyle = color;
                            ctx.fillRect(px, py, 1, 1);
                        }
                    }
                }
            }
            
            updateStatus('绘制中...');
        });
        
        canvas.addEventListener('mousemove', (e) => {
            if (!isDrawing) return;
            
            const coords = getCanvasCoords(e);
            const currentX = coords.x;
            const currentY = coords.y;
            
            // 绘制线条连接上一次点和当前点
            drawLine(lastX, lastY, currentX, currentY);
            
            lastX = currentX;
            lastY = currentY;
        });
        
        canvas.addEventListener('mouseup', () => {
            isDrawing = false;
            updateStatus('就绪');
        });
        
        canvas.addEventListener('mouseleave', () => {
            isDrawing = false;
            updateStatus('就绪');
        });
        
        // 触摸支持
        canvas.addEventListener('touchstart', (e) => {
            e.preventDefault();
            isDrawing = true;
            const touch = e.touches[0];
            const coords = getCanvasCoords(touch);
            lastX = coords.x;
            lastY = coords.y;
            
            // 绘制起始点
            const color = currentTool === 'eraser' ? '#FFFFFF' : currentColor;
            const radius = brushSize;
            
            for (let dy = -radius; dy <= radius; dy++) {
                for (let dx = -radius; dx <= radius; dx++) {
                    const px = lastX + dx;
                    const py = lastY + dy;
                    
                    if (px >= 0 && px < 1920 && py >= 0 && py < 1080) {
                        const distance = Math.sqrt(dx * dx + dy * dy);
                        
                        if (distance <= radius) {
                            const index = py * 1920 + px;
                            boardData[index] = color;
                            
                            ctx.fillStyle = color;
                            ctx.fillRect(px, py, 1, 1);
                        }
                    }
                }
            }
        });
        
        canvas.addEventListener('touchmove', (e) => {
            e.preventDefault();
            if (!isDrawing) return;
            
            const touch = e.touches[0];
            const coords = getCanvasCoords(touch);
            const currentX = coords.x;
            const currentY = coords.y;
            
            drawLine(lastX, lastY, currentX, currentY);
            
            lastX = currentX;
            lastY = currentY;
        });
        
        canvas.addEventListener('touchend', (e) => {
            e.preventDefault();
            isDrawing = false;
        });
        
        // 控件监听
        document.getElementById('brushSize').addEventListener('input', (e) => {
            brushSize = parseInt(e.target.value);
            document.getElementById('brushSizeValue').textContent = brushSize;
            updateStatus('笔刷大小: ' + brushSize + 'px');
        });
        
        document.getElementById('colorPicker').addEventListener('input', (e) => {
            setColor(e.target.value);
        });
        
        // 滚轮调节笔刷大小
        canvas.addEventListener('wheel', (e) => {
            e.preventDefault();
            brushSize += e.deltaY > 0 ? -1 : 1;
            brushSize = Math.max(1, Math.min(50, brushSize));
            
            document.getElementById('brushSize').value = brushSize;
            document.getElementById('brushSizeValue').textContent = brushSize;
            updateStatus('笔刷大小: ' + brushSize + 'px');
        });
        
        // 窗口调整
        window.addEventListener('resize', () => {
            canvasScale = resizeCanvas();
            redrawCanvas();
        });
        
        // 关闭菜单当点击其他地方
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.menu-btn') && !e.target.closest('.submenu')) {
                const menus = ['brushMenu', 'colorMenu', 'fileMenu'];
                menus.forEach(id => {
                    document.getElementById(id).classList.remove('active');
                });
            }
        });
        
        // 初始化
        window.addEventListener('load', () => {
            initCanvas();
            setTool('brush');
            
            // 检查是否有本地存储的数据
            try {
                const saved = localStorage.getItem('canvas_backup');
                if (saved && confirm('检测到未保存的画板数据，是否恢复？')) {
                    boardData = JSON.parse(saved);
                    redrawCanvas();
                    updateStatus('已恢复上次的画板');
                    log.add('info', '从本地存储恢复画板');
                }
            } catch (e) {
                // 忽略错误
            }
            
            // 自动保存到本地存储
            setInterval(() => {
                try {
                    localStorage.setItem('canvas_backup', JSON.stringify(boardData));
                } catch (e) {
                    console.warn('本地存储失败');
                }
            }, 30000);
        });
    </script>
</body>
</html>