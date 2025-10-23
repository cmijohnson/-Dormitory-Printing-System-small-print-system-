<?php
// logout.php
require_once 'config.php';

// 销毁所有会话变量
$_SESSION = array();

// 如果需要彻底销毁会话，请删除会话 cookie。
// 注意：这将销毁会话，而不仅仅是会话数据！
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
}

// 最后，销毁会话
session_destroy();

// 重定向到登录页面
header("location: login.php");
exit;
?>