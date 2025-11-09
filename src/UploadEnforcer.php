<?php
/**
 * UploadEnforcer.php
 * @author cdyun(121625706@qq.com)
 * @date 2025/11/9 14:02
 */

declare (strict_types=1);

namespace Cdyun\ThinkphpUpload;

class UploadEnforcer
{
    /**
     * 获取配置config
     * @param string|null $name - 名称
     * @param $default - 默认值
     * @return mixed
     * @author cdyun(121625706@qq.com)
     */
    public function getConfig(?string $name = null, $default = null): mixed
    {
        if (!is_null($name)) {
            return config('cdyun.upload.' . $name, $default);
        }
        return config('cdyun.upload');
    }
}