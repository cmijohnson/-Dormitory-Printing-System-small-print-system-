<?php
// admin_printers.php
require_once 'config.php';

// 检查是否是管理员
if (!is_admin()) {
    header("location: login.php");
    exit;
}

$message = '';

// 处理添加打印机
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_printer') {
    $printer_name_to_add = trim($_POST['printer_name']);
    if (!empty($printer_name_to_add)) {
        $sql = "INSERT INTO " . TABLE_PRINTERS . " (printer_name, is_active) VALUES (?, 1)";
        if ($stmt = $mysqli->prepare($sql)) {
            $stmt->bind_param("s", $printer_name_to_add);
            if ($stmt->execute()) {
                $message = "<div class='alert success'>打印机 '" . htmlspecialchars($printer_name_to_add) . "' 添加成功。</div>";
            } else {
                if ($mysqli->errno == 1062) { // Duplicate entry error
                    $message = "<div class='alert error'>打印机 '" . htmlspecialchars($printer_name_to_add) . "' 已存在。</div>";
                } else {
                    $message = "<div class='alert error'>添加打印机失败: " . $mysqli->error . "</div>";
                }
            }
            $stmt->close();
        } else {
            $message = "<div class='alert error'>数据库操作准备失败。</div>";
        }
    } else {
        $message = "<div class='alert error'>打印机名称不能为空。</div>";
    }
}

// 处理激活/禁用/删除打印机
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['printer_id']) && isset($_POST['action'])) {
    $printer_id_to_manage = (int)$_POST['printer_id'];
    $action = $_POST['action'];

    if ($action === 'toggle_active') {
        // 获取当前状态
        $current_status = 0;
        $sql_select = "SELECT is_active FROM " . TABLE_PRINTERS . " WHERE id = ?";
        if ($stmt_select = $mysqli->prepare($sql_select)) {
            $stmt_select->bind_param("i", $printer_id_to_manage);
            $stmt_select->execute();
            $stmt_select->bind_result($current_status);
            $stmt_select->fetch();
            $stmt_select->close();
        }

        $new_status = ($current_status == 1) ? 0 : 1;
        $sql = "UPDATE " . TABLE_PRINTERS . " SET is_active = ? WHERE id = ?";
        if ($stmt = $mysqli->prepare($sql)) {
            $stmt->bind_param("ii", $new_status, $printer_id_to_manage);
            if ($stmt->execute()) {
                $message = "<div class='alert success'>打印机状态更新成功。</div>";
            } else {
                $message = "<div class='alert error'>更新打印机状态失败。</div>";
            }
            $stmt->close();
        } else {
            $message = "<div class='alert error'>数据库操作准备失败。</div>";
        }
    } elseif ($action === 'delete') {
        $sql = "DELETE FROM " . TABLE_PRINTERS . " WHERE id = ?";
        if ($stmt = $mysqli->prepare($sql)) {
            $stmt->bind_param("i", $printer_id_to_manage);
            if ($stmt->execute()) {
                $message = "<div class='alert success'>打印机删除成功。</div>";
            } else {
                $message = "<div class='alert error'>删除打印机失败。</div>";
            }
            $stmt->close();
        } else {
            $message = "<div class='alert error'>数据库操作准备失败。</div>";
        }
    }
}

// 获取所有已配置的打印机列表
$configured_printers = [];
$sql = "SELECT id, printer_name, is_active, added_at FROM " . TABLE_PRINTERS . " ORDER BY printer_name ASC";
if ($result = $mysqli->query($sql)) {
    while ($row = $result->fetch_assoc()) {
        $configured_printers[] = $row;
    }
    $result->free();
} else {
    $message .= "<div class='alert error'>无法获取已配置打印机列表。</div>";
}

$mysqli->close();
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>打印机管理 - 打印管理系统</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h2>打印机管理</h2>
        <p class="text-center">管理用户可用的打印机列表。</p>

        <div class="admin-nav">
            <a href="admin_dashboard.php" <?php if(basename($_SERVER['PHP_SELF']) == 'admin_dashboard.php') echo 'class="active"'; ?>>总览</a>
            <a href="admin_users.php" <?php if(basename($_SERVER['PHP_SELF']) == 'admin_users.php') echo 'class="active"'; ?>>用户管理</a>
            <a href="admin_printers.php" <?php if(basename($_SERVER['PHP_SELF']) == 'admin_printers.php') echo 'class="active"'; ?>>打印机管理</a>
            <a href="admin_print_jobs.php" <?php if(basename($_SERVER['PHP_SELF']) == 'admin_print_jobs.php') echo 'class="active"'; ?>>打印记录</a>
            <a href="admin_settings.php" <?php if(basename($_SERVER['PHP_SELF']) == 'admin_settings.php') echo 'class="active"'; ?>>系统设置</a>
            <a href="dashboard.php" class="button secondary">返回用户面板</a>
            <a href="logout.php" class="button logout">退出登录</a>
        </div>

        <?php echo $message; ?>

        <!-- 添加打印机 -->
        <div class="section">
            <h3>添加新打印机</h3>
            <form action="admin_printers.php" method="post">
                <input type="hidden" name="action" value="add_printer">
                <div class="form-group">
                    <label for="printer_name">打印机名称 (与Windows系统中的名称一致):</label>
                    <input type="text" name="printer_name" id="printer_name" required>
                </div>
                <div class="form-group">
                    <input type="submit" value="添加打印机">
                    <button type="button" class="button secondary" id="discoverPrintersButton">从服务器发现打印机</button>
                </div>
                <div id="discovered_printers_list" style="margin-top:15px;"></div>
            </form>
        </div>

        <!-- 已配置打印机列表 -->
        <div class="section">
            <h3>已配置打印机</h3>
            <?php if (!empty($configured_printers)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>打印机名称</th>
                            <th>状态</th>
                            <th>添加时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($configured_printers as $printer): ?>
                            <tr>
                                <td><?php echo $printer['id']; ?></td>
                                <td><?php echo htmlspecialchars($printer['printer_name']); ?></td>
                                <td>
                                    <?php if ($printer['is_active']): ?>
                                        <span class="status-active">激活</span>
                                    <?php else: ?>
                                        <span class="status-inactive">禁用</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $printer['added_at']; ?></td>
                                <td>
                                    <form action="admin_printers.php" method="post" style="display:inline-block;">
                                        <input type="hidden" name="printer_id" value="<?php echo $printer['id']; ?>">
                                        <input type="hidden" name="action" value="toggle_active">
                                        <?php if ($printer['is_active']): ?>
                                            <button type="submit" class="button warning">禁用</button>
                                        <?php else: ?>
                                            <button type="submit" class="button success">激活</button>
                                        <?php endif; ?>
                                    </form>
                                    <form action="admin_printers.php" method="post" style="display:inline-block; margin-left: 5px;" onsubmit="return confirm('确定要删除此打印机吗？此操作不可逆。');">
                                        <input type="hidden" name="printer_id" value="<?php echo $printer['id']; ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <button type="submit" class="button danger">删除</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>目前没有配置任何打印机。</p>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const discoverButton = document.getElementById('discoverPrintersButton');
            const discoveredListDiv = document.getElementById('discovered_printers_list');

            discoverButton.addEventListener('click', async function() {
                discoveredListDiv.innerHTML = '<p class="alert info">正在从服务器发现打印机...</p>';
                try {
                    const response = await fetch('get_server_printers.php');
                    const printers = await response.json();

                    if (printers.error) {
                        discoveredListDiv.innerHTML = `<p class="alert error">发现打印机失败: ${printers.error}</p>`;
                        console.error('发现打印机失败:', printers.error);
                        return;
                    }

                    if (printers.length === 0) {
                        discoveredListDiv.innerHTML = '<p class="alert info">服务器上没有发现任何打印机。</p>';
                    } else {
                        let listHtml = '<h4>发现的服务器打印机:</h4><ul>';
                        printers.forEach(printer => {
                            listHtml += `<li>${printer} <button class="button success button-small add-discovered-printer" data-printer-name="${printer}">添加到配置</button></li>`;
                        });
                        listHtml += '</ul>';
                        discoveredListDiv.innerHTML = listHtml;

                        // 为新添加的按钮绑定事件
                        document.querySelectorAll('.add-discovered-printer').forEach(button => {
                            button.addEventListener('click', async function() {
                                const printerName = this.dataset.printerName;
                                const formData = new FormData();
                                formData.append('action', 'add_printer');
                                formData.append('printer_name', printerName);

                                try {
                                    const addResponse = await fetch('admin_printers.php', {
                                        method: 'POST',
                                        body: formData
                                    });
                                    // 重新加载页面以显示更新后的列表和消息
                                    window.location.reload();
                                } catch (error) {
                                    alert('添加打印机请求失败: ' + error.message);
                                    console.error('添加打印机请求失败:', error);
                                }
                            });
                        });
                    }
                } catch (error) {
                    discoveredListDiv.innerHTML = '<p class="alert error">发现打印机时发生错误。</p>';
                    console.error('发现打印机时发生错误:', error);
                }
            });
        });
    </script>
</body>
</html>