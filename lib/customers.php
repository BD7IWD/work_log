<?php
require_once __DIR__ . '/db.php';

/**
 * 把各种分隔符统一成英文逗号，去空、去重、限数量、限长度
 * @param string $raw 用户提交的客户串（可能是 "创维，茂佳" / "创维、茂佳" / "创维,茂佳"）
 * @return string 规范化后的 "创维,茂佳"
 */
function customer_normalize($raw)
{
    $raw = (string)$raw;
    // 统一分隔符
    $raw = str_replace(array('，', '、', ';', '；', '|', '／', '/', '　', ' '), ',', $raw);
    $parts = explode(',', $raw);

    $out = array();
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p === '') {
            continue;
        }
        $p = mb_substr($p, 0, 20, 'UTF-8');   // 单个客户名最长 20 字
        if (!in_array($p, $out, true)) {
            $out[] = $p;
        }
        if (count($out) >= (defined('CUSTOMER_MAX_PER_RECORD') ? CUSTOMER_MAX_PER_RECORD : 12)) {
            break;
        }
    }

    return mb_substr(implode(',', $out), 0, 120, 'UTF-8');
}

/**
 * 把存储串拆成客户名数组（用于列表/详情渲染多个 chip）
 */
function customer_list($str)
{
    $str = (string)$str;
    if (trim($str) === '') {
        return array();
    }
    $raw = str_replace(array('，', '、', ';', '；', '|', '/'), ',', $str);
    $out = array();
    foreach (explode(',', $raw) as $p) {
        $p = trim($p);
        if ($p !== '' && !in_array($p, $out, true)) {
            $out[] = $p;
        }
    }
    return $out;
}

/**
 * 候选客户：预置项 + 历史记录里出现过的（自动学习）
 */
function customer_options()
{
    $preset = isset($GLOBALS['CUSTOMER_PRESET']) && is_array($GLOBALS['CUSTOMER_PRESET'])
        ? $GLOBALS['CUSTOMER_PRESET']
        : array();

    $used = array();
    try {
        $st = db()->query("SELECT DISTINCT customer FROM records WHERE customer <> '' ORDER BY customer");
        foreach ($st->fetchAll() as $row) {
            foreach (customer_list($row['customer']) as $name) {
                if (!in_array($name, $used, true)) {
                    $used[] = $name;
                }
            }
        }
    } catch (Exception $e) {
        // 旧库还没加 customer 字段时不致命，退回只用预置项
    }

    // 预置项在前，历史项在后
    $all = array();
    foreach (array_merge($preset, $used) as $name) {
        $name = trim((string)$name);
        if ($name !== '' && !in_array($name, $all, true)) {
            $all[] = $name;
        }
    }
    return $all;
}
