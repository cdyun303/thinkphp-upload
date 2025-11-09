<?php
/**
 * BaseUpload.php
 * @author cdyun(121625706@qq.com)
 * @date 2025/11/9 17:12
 */

declare (strict_types=1);

namespace Cdyun\ThinkphpUpload;

use Cdyun\ThinkphpUpload\util\UseTool;
use think\exception\ValidateException;
use think\File;

abstract class BaseUpload
{

    /**
     * 文件信息
     */
    protected $fileInfo;

    /**
     * 驱动配置
     */
    protected array $config;


    /**
     * 验证配置
     */
    protected array $validateRule = [];

    /**
     * 保存路径
     * @var string
     */
    protected string $path = 'files';

    /**
     * 保存名称
     * @var string
     */
    protected string $fileName = '';

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->fileInfo = new \StdClass();
        $this->initialize();
    }

    /**
     * 初始化
     * @return mixed
     */
    abstract protected function initialize();

    /**
     * 上传文件路径
     * @param string $path
     * @return $this
     */
    public function to(string $path)
    {
        $this->path = $path;
        return $this;
    }

    /**
     * 自定义上传文件名
     * @param string $fileName
     * @return $this
     * @author cdyun(121625706@qq.com)
     */
    public function name(string $fileName)
    {
        $ext = pathinfo($fileName, PATHINFO_EXTENSION);
        $fileExt = UseTool::getConfig('fileExt', []);
        $fileExt = is_array($fileExt) ? $fileExt : explode(',', $fileExt);
        if (!in_array($ext, $fileExt)) {
            throw new ValidateException('自定义文件名类型[.' . $ext . ']属于不允许上传的文件类型');
        }
        $this->fileName = $fileName;
        return $this;
    }

    /**
     * 自定义验证器
     * @param array $validateRule - 验证规则
     * 参考官方的验证参数:['filesize' => '最大字节', 'fileExt' => '文件后缀', 'fileMime' => '文件类型', 'image' => 'image:width,height,type']
     * @return $this
     * @author cdyun(121625706@qq.com)
     */
    public function validate(array $validateRule)
    {
        if (!empty($validateRule)) {
            $this->validateRule = $validateRule;
        }
        return $this;
    }

    /**
     * 文件上传
     * @param string $fileField - 文件字段名
     * @return \StdClass
     * @author cdyun(121625706@qq.com)
     */
    abstract public function move(string $fileField = 'file'): \StdClass;

    /**
     * 文件流上传
     * @param string $fileContent - 文件流内容
     * @param string $key - 自定义文件名
     * @return \StdClass
     * @author cdyun(121625706@qq.com)
     */
    abstract public function stream(string $fileContent, string $key): \StdClass;

    /**
     * 删除文件
     * @param string $filePath - 文件路径
     * @return bool
     * @author cdyun(121625706@qq.com)
     */
    abstract public function delete(string $filePath): bool;

    /**
     * 签名
     * @param string $key - 文件名
     * @param int $expires - 签名有效期
     * @return array
     * @author cdyun(121625706@qq.com)
     */
    abstract public function sign(string $key, int $expires): array;

    /**
     * 预处理文件
     * @param File $file
     * @return array
     * @author cdyun(121625706@qq.com)
     */
    protected function prepareFiles(File $file): array
    {
        $ext = $file->extension();
        $mine = $file->getMime();
        $size = $file->getSize();
        return [
            'file' => $file,
            'ext' => $ext,
            'mine' => $mine,
            'size' => $size
        ];
    }

    /**
     * 通过全局配置验证上传文件
     * @param array $fileInfo
     * @return bool
     * @author cdyun(121625706@qq.com)
     */
    protected function validateFileByConfig(array $fileInfo): bool
    {
        $fileSize = UseTool::getConfig('fileSize', 0);
        $imgSize = UseTool::getConfig('imgSize', 0);

        $fileExt = UseTool::getConfig('fileExt', []);
        $fileExt = is_array($fileExt) ? $fileExt : explode(',', $fileExt);

        $imgExt = UseTool::getConfig('imgExt', []);
        $imgExt = is_array($imgExt) ? $imgExt : explode(',', $imgExt);

        //验证类型
        if (!in_array($fileInfo['ext'], $fileExt)) {
            throw new ValidateException('允许的上传文件类型没有' . $fileInfo['ext']);
        }
        //验证大小
        if ($fileInfo['size'] > $fileSize * 1024) {
            throw new ValidateException('上传文件不能超过' . $fileSize . 'kb');
        }
        //判断是否图片,是图片验证图片大小
        if (in_array($fileInfo['ext'], $imgExt)) {
            if ($fileInfo['size'] > $imgSize * 1024) {
                throw new ValidateException('图片大小不超过' . $imgSize . 'kb');
            }
        }
        return true;
    }

    /**
     * 构建验证规则
     * @param array $rule
     * @return string
     * @author cdyun(121625706@qq.com)
     */
    protected function extractValidate(array $rule): string
    {
        $flag = [];
        if (isset($rule['fileSize'])) {
            $flag[] = 'filesize:' . $rule['fileSize'];
        }
        if (isset($rule['fileExt'])) {
            $flag[] = 'fileExt:' . $rule['fileExt'];
        }
        if (isset($rule['fileMime'])) {
            $flag[] = 'fileMime:' . $rule['fileMime'];
        }
        if (isset($rule['image'])) {
            $flag[] = 'image:' . $rule['image'];
        }
        if (empty($flag)) {
            throw new ValidateException('自定义验证规则错误，仅支持文件大小、文件类型、文件后缀和验证图像');
        }
        return implode('|', $flag);
    }

    /**
     * 设置文件名
     * @param $ext - 文件后缀
     * @return string
     */
    protected function generateFileName(string $ext): string
    {
        return $this->fileName ?: date("YmdHis") . '_' . rand(10000, 99999) . '.' . $ext;
    }
}