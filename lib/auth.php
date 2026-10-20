<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/func.php';

/** 当前登录用户（未登录返回 null） */
function current_user()
{
    static $cache = null;
    if (!isset($_SESSION)) {
        @session_start();
    }
    if (empty($_SESSION['uid'])) {
        return null;
    }
    if ($cache !== null) {
        return $cache;
    }
    $st = db()->prepare('SELECT id, username, display_name, role FROM users WHERE id = ? AND status = 1');
    $st->execute(array((int)$_SESSION['uid']));
    $u = $st->fetch();
    if (!$u) {
        $_SESSION = array();
        return null;
    }
    $cache = $u;
    return $u;
}

/** 强制登录；AJAX 请求返回 JSON 401 */
function require_login()
{
    $u = current_user();
    if ($u) {
        return $u;
    }
    // API 目录下的请求一律返回 JSON（前端 fetch / 第三方调用都不会被重定向打乱）
    $scriptName = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', $_SERVER['SCRIPT_NAME']) : '';
    $isApi = (strpos($scriptName, '/api/') !== false);

    if ($isApi
        || !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
        || strpos(input('ajax', ''), '1') !== false
        || strpos(isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '', 'application/json') !== false) {
        json_out(array('ok' => 0, 'msg' => '登录已过期，请重新登录', 'need_login' => 1), 401);
    }
    // 记住目标地址
    $back = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    redirect('login.php' . ($back ? '?back=' . urlencode($back) : ''));
}

/** 是否管理员 */
function is_admin($u = null)
{
    if ($u === null) {
        $u = current_user();
    }
    return $u && $u['role'] === 'admin';
}

/** 登录校验 */
function do_login($username, $password)
{
    $st = db()->prepare('SELECT * FROM users WHERE username = ? AND status = 1 LIMIT 1');
    $st->execute(array($username));
    $u = $st->fetch();
    if (!$u || !password_verify($password, $u['password_hash'])) {
        return array(false, '账号或密码不正确');
    }
    if (!isset($_SESSION)) {
        @session_start();
    }
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id'];
    $_SESSION['uname'] = $u['username'];
    return array(true, '');
}

/** 退出 */
function do_logout()
{
    if (!isset($_SESSION)) {
        @session_start();
    }
    $_SESSION = array();
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    @session_destroy();
}

/** 记录归属校验：只有记录人本人或管理员可改 */
function can_edit_record($rec, $u = null)
{
    if ($u === null) {
        $u = current_user();
    }
    if (!$u || !$rec) {
        return false;
    }
    return ((int)$rec['user_id'] === (int)$u['id']) || $u['role'] === 'admin';
}

// ===== 会话启动（统一入口）=====
if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    @session_set_cookie_params(0, APP_ROOT, '', $secure, true);
    @session_start();
}
