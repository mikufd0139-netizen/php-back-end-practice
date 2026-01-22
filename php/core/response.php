<?php
/**
 * 统一响应类
 * 所有接口都通过这个类返回数据，保证格式一致
 */
class Response
{
    /**
     * 成功响应
     * @param mixed  $data    返回的数据
     * @param string $message 提示信息
     * @param int    $code    HTTP 状态码
     */
    public static function success($data = null, string $message = '操作成功', int $code = 200): void
    {
        self::output($code, true, $message, $data);
    }

    /**
     * 失败响应
     * @param string $message 错误信息
     * @param int    $code    HTTP 状态码
     * @param mixed  $data    附加数据（可选）
     */
    public static function error(string $message = '操作失败', int $code = 400, $data = null): void
    {
        self::output($code, false, $message, $data);
    }

    /**
     * 输出 JSON 响应
     */
    private static function output(int $code, bool $success, string $message, $data): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        
        $response = [
            'success' => $success,
            'message' => $message
        ];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }
}