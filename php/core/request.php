<?php
/**
 * 请求处理类
 * 封装常用的请求操作
 */
class Request
{
    /**
     * 检查请求方法
     * @param string|array $methods 允许的方法，如 'POST' 或 ['GET', 'POST']
     */
    public static function allowMethods($methods): void
    {
        if (is_string($methods)) {
            $methods = [$methods];
        }
        
        if (!in_array($_SERVER['REQUEST_METHOD'], $methods)) {
            Response::error('请求方法不允许', 405);
        }
    }

    /**
     * 获取 JSON 请求体
     * @return array
     */
    public static function getJson(): array
    {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        return is_array($data) ? $data : [];
    }

    /**
     * 获取 GET 参数
     * @param string $key     参数名
     * @param mixed  $default 默认值
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        return $_GET[$key] ?? $default;
    }

    /**
     * 获取请求参数（优先 JSON，其次 POST）
     * @param string $key     参数名
     * @param mixed  $default 默认值
     * @return mixed
     */
    public static function input(string $key, $default = null)
    {
        static $jsonData = null;
        
        if ($jsonData === null) {
            $jsonData = self::getJson();
        }
        
        return $jsonData[$key] ?? $_POST[$key] ?? $default;
    }
}