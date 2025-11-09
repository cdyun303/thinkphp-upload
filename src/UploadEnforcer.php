<?php
/**
 * UploadEnforcer.php
 * @author cdyun(121625706@qq.com)
 * @date 2025/11/9 14:02
 */

declare (strict_types=1);

namespace Cdyun\ThinkphpUpload;

use Cdyun\ThinkphpUpload\driver\Local;
use Cdyun\ThinkphpUpload\driver\Oss;
use Cdyun\ThinkphpUpload\util\UseTool;
use think\facade\Filesystem;

/**
 *
 * @mixin Local
 * @mixin Oss
 * */
class UploadEnforcer
{
    /**
     * 驱动类型
     */
    protected string $type;

    public function __construct(string $type = '')
    {
        $this->type = $type ?: Filesystem::getDefaultDriver();
    }


    /**
     * 动态调用
     * @param $method
     * @param $arguments
     * @return mixed
     * @throws \Exception
     */
    public function __call($method, $arguments)
    {
        // 获取指定驱动的配置
        $config = UseTool::getConfig('stores.' . $this->type, []);
        $class = match ($this->type) {
            'local' => new Local($config),
            'oss' => new Oss($config),
            default => throw new \Exception('暂不支持此驱动'),
        };
        return $class->{$method}(...$arguments);
    }
}