<?php
/**
 * 数据验证类
 * 封装常用的验证规则
 */
class Validator
{
    /**
     * 验证用户名
     * @param string $username
     * @return string|true 验证通过返回 true，失败返回错误信息
     */
    public static function username(string $username)
    {
        $username = trim($username);
        
        if (empty($username)) {
            return '用户名不能为空';
        }
        
        $len = mb_strlen($username);
        if ($len < 3 || $len > 50) {
            return '用户名长度必须在3-50个字符之间';
        }
        
        // 只允许字母、数字、下划线、中文
        if (!preg_match('/^[\w\x{4e00}-\x{9fa5}]+$/u', $username)) {
            return '用户名只能包含字母、数字、下划线和中文';
        }
        
        return true;
    }

    /**
     * 验证密码
     * @param string $password
     * @return string|true
     */
    public static function password(string $password)
    {
        if (empty($password)) {
            return '密码不能为空';
        }
        
        if (strlen($password) < 6) {
            return '密码长度不能少于6位';
        }
        
        if (strlen($password) > 20) {
            return '密码长度不能超过20位';
        }
        
        return true;
    }

    /**
     * 验证邮箱
     * @param string|null $email
     * @return string|true
     */
    public static function email(?string $email)
    {
        if (empty($email)) {
            return true; // 邮箱选填，为空时跳过验证
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return '邮箱格式不正确';
        }
        
        return true;
    }

    /**
     * 验证手机号
     * @param string|null $phone
     * @return string|true
     */
    public static function phone(?string $phone)
    {
        if (empty($phone)) {
            return true; // 手机号选填，为空时跳过验证
        }
        
        if (!preg_match('/^1[3-9]\d{9}$/', $phone)) {
            return '手机号格式不正确';
        }
        
        return true;
    }

    /**
     * 批量验证
     * @param array $rules 规则数组，格式：['字段名' => ['值', '验证方法']]
     * @return true 全部通过返回 true，否则直接输出错误并终止
     */
    public static function check(array $rules): bool
    {
        foreach ($rules as $field => $config) {
            [$value, $method] = $config;
            
            if (method_exists(self::class, $method)) {
                $result = self::$method($value);
                if ($result !== true) {
                    Response::error($result, 400);
                }
            }
        }
        
        return true;
    }
}