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
$sql = "SELECT id, original_filename, upload_time, print_status, page_count, cost, error_message FROM " . TABLE_UPLOADED_FILES . " WHERE user_id = ? ORDER BY upload_time DESC";
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
                        <input type="submit" value="上传文件">
                    </div>
                </form>
            </div>

            <!-- 新增：用于控制打印队列显示的打印机选择器 -->
            <div class="section">
                <h3>选择打印机查看队列</h3>
                <div class="form-group">
                    <label for="queue_printer_select">选择打印机:</label>
                    <select name="queue_printer_select" id="queue_printer_select" required>
                        <option value="">加载中...</option>
                    </select>
                </div>
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
                                <td>
                                    <span class="status-<?php echo $file['print_status']; ?>"><?php echo ucfirst($file['print_status']); ?></span>
                                    <?php if (!empty($file['error_message'])): ?>
                                        <br><small style="color:red;" title="<?php echo htmlspecialchars($file['error_message']); ?>"> (错误详情)</small>
                                    <?php endif; ?>
                                </td>
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
            <div class="form-group">
                <label for="modal_printer_name_select">选择打印机:</label>
                <select name="modal_printer_name" id="modal_printer_name_select" required>
                    <option value="">加载中...</option>
                </select>
            </div>
            <p class="alert info">请确认您已支付费用。</p>
            <div class="modal-footer">
                <button class="button secondary" id="cancelPrint">取消</button>
                <button class="button" id="confirmPayment">确认已支付并打印</button>
            </div>
        </div>
    </div>

    <script>
        let selectedFileId = null;
        let selectedPrinterName = ''; // 这个变量将由 queue_printer_select 控制
        let currentPageCount = 0;

        document.addEventListener('DOMContentLoaded', function() {
            // 新增：用于控制队列显示的打印机选择器
            const queuePrinterSelect = document.getElementById('queue_printer_select');
            // 模态框内部的打印机选择器
            const modalPrinterSelect = document.getElementById('modal_printer_name_select');

            const printQueueStatus = document.getElementById('print_queue_status');
            const selectedPrinterQueueName = document.getElementById('selected_printer_queue_name');

            // --- 动态加载打印机列表 (只加载管理员激活的打印机) ---
            async function loadPrinters() {
                // 确保服务开启时才加载打印机
                if (SERVICE_STATUS !== 'on') {
                    if (queuePrinterSelect) queuePrinterSelect.innerHTML = '<option value="">服务已暂停</option>';
                    if (modalPrinterSelect) modalPrinterSelect.innerHTML = '<option value="">服务已暂停</option>';
                    return;
                }

                if (queuePrinterSelect) queuePrinterSelect.innerHTML = '<option value="">加载中...</option>';
                if (modalPrinterSelect) modalPrinterSelect.innerHTML = '<option value="">加载中...</option>';

                try {
                    const response = await fetch('get_printers.php');
                    const printers = await response.json();
                    
                    if (printers.error) {
                        const errorMessage = `<option value="">加载失败: ${printers.error}</option>`;
                        if (queuePrinterSelect) queuePrinterSelect.innerHTML = errorMessage;
                        if (modalPrinterSelect) modalPrinterSelect.innerHTML = errorMessage;
                        console.error('获取打印机失败:', printers.error);
                        return;
                    }

                    if (printers.length === 0) {
                        const noPrinterMessage = '<option value="">无可用打印机</option>';
                        if (queuePrinterSelect) queuePrinterSelect.innerHTML = noPrinterMessage;
                        if (modalPrinterSelect) modalPrinterSelect.innerHTML = noPrinterMessage;
                    } else {
                        let optionsHtml = '';
                        printers.forEach(printer => {
                            optionsHtml += `<option value="${printer}">${printer}</option>`;
                        });

                        if (queuePrinterSelect) {
                            queuePrinterSelect.innerHTML = optionsHtml;
                            selectedPrinterName = queuePrinterSelect.value; // 默认选中第一个
                            selectedPrinterQueueName.textContent = selectedPrinterName;
                            loadPrintQueue(); // 加载默认打印机的队列
                        }
                        if (modalPrinterSelect) {
                            modalPrinterSelect.innerHTML = optionsHtml;
                        }
                    }
                } catch (error) {
                    const errorMessage = '<option value="">加载失败</option>';
                    if (queuePrinterSelect) queuePrinterSelect.innerHTML = errorMessage;
                    if (modalPrinterSelect) modalPrinterSelect.innerHTML = errorMessage;
                    console.error('获取打印机列表时发生错误:', error);
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
                        // 打印 PowerShell 的原始输出进行调试
                        if (queue.powershell_raw_output) {
                            console.error('PowerShell原始输出:', queue.powershell_raw_output);
                            if (printQueueStatus) printQueueStatus.innerHTML += `<p class="alert error">PowerShell原始输出: <pre>${queue.powershell_raw_output}</pre></p>`;
                        }
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

            // --- 打印机选择变化事件 (新的队列选择器) ---
            if (queuePrinterSelect) {
                queuePrinterSelect.addEventListener('change', function() {
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

                        // 模态框打开时，确保模态框内的打印机选择器也加载了打印机
                        // 并且默认选中当前队列选择器选中的打印机
                        if (modalPrinterSelect && queuePrinterSelect && queuePrinterSelect.value) {
                            modalPrinterSelect.value = queuePrinterSelect.value;
                        }
                        
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
                    const printerToPrint = modalPrinterSelect.value; // 从模态框中的选择器获取
                    if (!printerToPrint) {
                        alert('请选择一个打印机。');
                        return;
                    }
                    paymentModal.style.display = 'none'; // 隐藏模态框

                    if (!selectedFileId) {
                        alert('请选择文件。');
                        return;
                    }

                    // 发送打印请求到 print_file.php
                    const formData = new FormData();
                    formData.append('file_id', selectedFileId);
                    formData.append('printer_name', printerToPrint); // 使用模态框中选择的打印机

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
            loadPrinters(); // 总是加载打印机，服务状态判断在 loadPrinters 内部处理
            // 每隔 5 秒刷新一次打印队列
            setInterval(loadPrintQueue, 5000);
        });

        // 从 PHP 获取服务状态
        const SERVICE_STATUS = "<?php echo SERVICE_STATUS; ?>";
    </script>
</body>
</html>