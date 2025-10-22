<?php
// dashboard.php
require_once 'config.php';

// 检查用户是否已登录，如果没有，则重定向到登录页面
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("location: login.php");
    exit;
}

$user_id = $_SESSION['id'];
$username = htmlspecialchars($_SESSION['username']);
$message = ''; // 用于显示各种操作消息

// 获取用户已上传的文件
$uploaded_files = [];
$sql = "SELECT id, original_filename, upload_time, print_status, page_count, cost FROM " . TABLE_UPLOADED_FILES . " WHERE user_id = ? ORDER BY upload_time DESC";
if ($stmt = $mysqli->prepare($sql)) {
    $stmt->bind_param("i", $user_id);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $uploaded_files[] = $row;
        }
    } else {
        $message .= "<div class='alert error'>无法获取文件列表。</div>";
    }
    $stmt->close();
} else {
    $message .= "<div class='alert error'>数据库查询准备失败。</div>";
}

// 处理来自其他页面的消息
if (isset($_SESSION['message'])) {
    $message .= $_SESSION['message'];
    unset($_SESSION['message']); // 显示后清除
}

$mysqli->close();
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>仪表盘 - 打印管理系统</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h2>欢迎, <?php echo $username; ?>!</h2>
        <p class="text-center">在这里您可以上传文件并管理打印任务。</p>

        <div class="text-center" style="margin-bottom: 30px;">
            <a href="change_password.php" class="button secondary">修改密码</a>
            <a href="https://epay.cmiteam.cn" target="_blank" class="button secondary">支付跳转</a>
            <?php if (is_admin()): ?>
                <a href="admin_dashboard.php" class="button">管理员面板</a>
            <?php endif; ?>
            <a href="logout.php" class="button logout">退出登录</a>
        </div>

        <?php echo $message; // 显示各种操作消息 ?>

        <!-- 服务状态和说明 -->
        <div class="section">
            <h3>服务信息</h3>
            <p>打印服务状态:
                <?php if (SERVICE_STATUS == 'on'): ?>
                    <span class="status-active">运行中</span>
                <?php else: ?>
                    <span class="status-inactive">已暂停</span>
                <?php endif; ?>
            </p>
            <p>每页打印价格: <strong>¥<?php echo number_format(COST_PER_PAGE, 2); ?></strong></p>
            <p>服务说明: <?php echo htmlspecialchars(SERVICE_DESCRIPTION); ?></p>
        </div>

        <?php if (SERVICE_STATUS == 'on'): ?>
            <!-- 文件上传表单 -->
            <div class="section">
                <h3>上传文件进行打印</h3>
                <form action="upload.php" method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="file_to_upload">选择文件:</label>
                        <input type="file" name="file_to_upload" id="file_to_upload" required>
                    </div>
                    <div class="form-group">
                        <label for="printer_name_select">选择打印机:</label>
                        <select name="printer_name" id="printer_name_select" required>
                            <option value="">加载中...</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <input type="submit" value="上传文件">
                    </div>
                </form>
            </div>

            <!-- 打印队列信息 -->
            <div class="section">
                <h3>打印队列 (<span id="selected_printer_queue_name">请选择打印机</span>)</h3>
                <div id="print_queue_status">
                    <p class="alert info">正在加载打印队列...</p>
                </div>
            </div>
        <?php else: ?>
            <div class="alert info text-center">打印服务目前已暂停，请稍后再试。</div>
        <?php endif; ?>

        <!-- 已上传文件列表 -->
        <div class="section file-list">
            <h3>我的文件</h3>
            <?php if (!empty($uploaded_files)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>文件名</th>
                            <th>页数</th>
                            <th>费用</th>
                            <th>上传时间</th>
                            <th>打印状态</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($uploaded_files as $file): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($file['original_filename']); ?></td>
                                <td><?php echo $file['page_count']; ?></td>
                                <td>¥<?php echo number_format($file['cost'], 2); ?></td>
                                <td><?php echo $file['upload_time']; ?></td>
                                <td><span class="status-<?php echo $file['print_status']; ?>"><?php echo ucfirst($file['print_status']); ?></span></td>
                                <td>
                                    <?php if (SERVICE_STATUS == 'on' && ($file['print_status'] == 'pending' || $file['print_status'] == 'failed')): ?>
                                    <button class="print-button" data-file-id="<?php echo $file['id']; ?>" data-page-count="<?php echo $file['page_count']; ?>" data-cost="<?php echo $file['cost']; ?>" data-filename="<?php echo htmlspecialchars($file['original_filename']); ?>">打印</button>
                                    <?php else: ?>
                                    <button disabled>已处理</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>您还没有上传任何文件。</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- 支付模拟模态框 -->
    <div id="paymentModal" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h3>确认打印与支付</h3>
            <p>您即将打印文件: <strong id="modal-filename"></strong></p>
            <p>预估页数: <strong id="modal-page-count"></strong> 页</p>
            <p>费用: <strong id="modal-cost"></strong> 元 (每页 <?php echo number_format(COST_PER_PAGE, 2); ?> 元)</p>
            <p class="alert info">请确认您已支付费用。</p>
            <div class="modal-footer">
                <button class="button secondary" id="cancelPrint">取消</button>
                <button class="button" id="confirmPayment">确认已支付并打印</button>
            </div>
        </div>
    </div>

    <script>
        let selectedFileId = null;
        let selectedPrinterName = '';
        let currentPageCount = 0;

        document.addEventListener('DOMContentLoaded', function() {
            const printerSelect = document.getElementById('printer_name_select');
            const printQueueStatus = document.getElementById('print_queue_status');
            const selectedPrinterQueueName = document.getElementById('selected_printer_queue_name');

            // --- 动态加载打印机列表 (只加载管理员激活的打印机) ---
            async function loadPrinters() {
                if (printerSelect) { // 只有当服务开启时才会有打印机选择框
                    printerSelect.innerHTML = '<option value="">加载中...</option>';
                    try {
                        const response = await fetch('get_printers.php');
                        const printers = await response.json();
                        printerSelect.innerHTML = ''; // 清空加载中选项
                        if (printers.error) {
                            printerSelect.innerHTML = '<option value="">加载失败</option>';
                            console.error('获取打印机失败:', printers.error);
                            return;
                        }
                        if (printers.length === 0) {
                            printerSelect.innerHTML = '<option value="">无可用打印机</option>';
                        } else {
                            printers.forEach(printer => {
                                const option = document.createElement('option');
                                option.value = printer;
                                option.textContent = printer;
                                printerSelect.appendChild(option);
                            });
                            selectedPrinterName = printerSelect.value; // 默认选中第一个
                            selectedPrinterQueueName.textContent = selectedPrinterName;
                            loadPrintQueue(); // 加载默认打印机的队列
                        }
                    } catch (error) {
                        printerSelect.innerHTML = '<option value="">加载失败</option>';
                        console.error('获取打印机列表时发生错误:', error);
                    }
                }
            }

            // --- 动态加载打印队列 ---
            async function loadPrintQueue() {
                if (!selectedPrinterName) {
                    if (printQueueStatus) printQueueStatus.innerHTML = '<p class="alert info">请选择一个打印机。</p>';
                    return;
                }
                if (printQueueStatus) printQueueStatus.innerHTML = '<p class="alert info">正在加载打印队列...</p>';
                try {
                    const response = await fetch(`get_print_queue.php?printer=${encodeURIComponent(selectedPrinterName)}`);
                    const queue = await response.json();

                    if (queue.error) {
                        if (printQueueStatus) printQueueStatus.innerHTML = `<p class="alert error">获取打印队列失败: ${queue.error}</p>`;
                        console.error('获取打印队列失败:', queue.error);
                        return;
                    }

                    if (queue.length === 0) {
                        if (printQueueStatus) printQueueStatus.innerHTML = '<p class="alert success">当前打印队列为空。</p>';
                    } else {
                        let queueHtml = '<table><thead><tr><th>文档名</th><th>状态</th></tr></thead><tbody>';
                        queue.forEach(job => {
                            queueHtml += `<tr><td>${job.DocumentName}</td><td>${job.Status}</td></tr>`;
                        });
                        queueHtml += '</tbody></table>';
                        if (printQueueStatus) printQueueStatus.innerHTML = queueHtml;
                    }
                } catch (error) {
                    if (printQueueStatus) printQueueStatus.innerHTML = '<p class="alert error">获取打印队列时发生错误。</p>';
                    console.error('获取打印队列时发生错误:', error);
                }
            }

            // --- 打印机选择变化事件 ---
            if (printerSelect) {
                printerSelect.addEventListener('change', function() {
                    selectedPrinterName = this.value;
                    selectedPrinterQueueName.textContent = selectedPrinterName;
                    loadPrintQueue();
                });
            }


            // --- 支付模态框逻辑 ---
            const paymentModal = document.getElementById('paymentModal');
            if (paymentModal) {
                const closeButton = paymentModal.querySelector('.close-button');
                const confirmPaymentButton = document.getElementById('confirmPayment');
                const cancelPrintButton = document.getElementById('cancelPrint');
                const modalFilename = document.getElementById('modal-filename');
                const modalPageCount = document.getElementById('modal-page-count');
                const modalCost = document.getElementById('modal-cost');

                document.querySelectorAll('.print-button').forEach(button => {
                    button.addEventListener('click', function() {
                        selectedFileId = this.dataset.fileId;
                        currentPageCount = parseInt(this.dataset.pageCount);
                        const cost = parseFloat(this.dataset.cost);
                        const filename = this.dataset.filename;

                        modalFilename.textContent = filename;
                        modalPageCount.textContent = currentPageCount;
                        modalCost.textContent = cost.toFixed(2); // 格式化为两位小数

                        paymentModal.style.display = 'flex'; // 显示模态框
                    });
                });

                closeButton.addEventListener('click', () => {
                    paymentModal.style.display = 'none';
                });
                cancelPrintButton.addEventListener('click', () => {
                    paymentModal.style.display = 'none';
                });

                confirmPaymentButton.addEventListener('click', async () => {
                    paymentModal.style.display = 'none'; // 隐藏模态框

                    if (!selectedFileId || !selectedPrinterName) {
                        alert('请选择文件和打印机。');
                        return;
                    }

                    // 发送打印请求到 print_file.php
                    const formData = new FormData();
                    formData.append('file_id', selectedFileId);
                    formData.append('printer_name', selectedPrinterName);

                    try {
                        const response = await fetch('print_file.php', {
                            method: 'POST',
                            body: formData
                        });
                        // print_file.php 会设置 $_SESSION['message'] 并重定向
                        // 所以这里只需刷新页面即可看到消息
                        window.location.reload();
                    } catch (error) {
                        alert('打印请求发送失败: ' + error.message);
                        console.error('打印请求失败:', error);
                        window.location.reload(); // 失败也刷新一下，看看后端有没有错误消息
                    }
                });

                // 点击模态框外部关闭
                window.addEventListener('click', function(event) {
                    if (event.target == paymentModal) {
                        paymentModal.style.display = 'none';
                    }
                });
            }

            // --- 页面初始化加载 ---
            if (SERVICE_STATUS === 'on') { // 只有服务开启时才加载打印机和队列
                loadPrinters();
                // 每隔 5 秒刷新一次打印队列
                setInterval(loadPrintQueue, 5000);
            } else {
                if (printerSelect) printerSelect.innerHTML = '<option value="">服务已暂停</option>';
                if (printQueueStatus) printQueueStatus.innerHTML = '<p class="alert info">打印服务目前已暂停。</p>';
            }
        });

        // 从 PHP 获取服务状态
        const SERVICE_STATUS = "<?php echo SERVICE_STATUS; ?>";
    </script>
</body>
</html>