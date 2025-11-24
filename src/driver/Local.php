<?php
/**
 * Local.php
 * @author cdyun(121625706@qq.com)
 * @date 2025/11/9 14:12
 */

declare (strict_types=1);

namespace Cdyun\ThinkphpUpload\driver;

use Cdyun\ThinkphpUpload\BaseUpload;
use think\exception\ValidateException;
use think\facade\Filesystem;
use think\File;

class Local extends BaseUpload
{
    /**
     * 存储路径
     * */
    protected string $bucketPath;

    /**
     * 上传文件
     * @param File $file - 文件对象
     * @return \StdClass
     * @author cdyun(121625706@qq.com)
     */
    public function move(File $file): \StdClass
    {
        if (!$file) {
            throw new ValidateException('上传文件丢失');
        }
        $fileInfo = $this->prepareFiles($file);

        //验证上传文件
        $this->validateFileByConfig($fileInfo);

        //判断是否有自定义验证
        if (!empty($this->validateRule)) {
            $rule = $this->extractValidate($this->validateRule);
            validate(['file' => $rule])->check(['file' => $fileInfo['file']]);
        }

        //验证路径是否存在且可写
        $dir = $this->getBucketPath($this->path);
        if (!$this->checkBucketPath($dir)) {
            throw new ValidateException('无法写入：' . $dir);
        }

        //上传文件
        $fileName = Filesystem::putFileAs($this->path, $fileInfo['file'], $this->generateFileName($fileInfo['ext']));
        if (!$fileName) {
            throw new ValidateException('上传失败：');
        }
        $filePath = Filesystem::path($fileName);
        $uploadInfo = new File($filePath);
        $this->fileInfo->realName = $file->getOriginalName();
        $this->fileInfo->fileName = $uploadInfo->getFilename();
        $this->fileInfo->originLink = Filesystem::url($fileName);
        $this->fileInfo->signLink = Filesystem::url($fileName);
        return $this->fileInfo;
    }

    /**
     * 获取存储路径
     * @param $path
     * @param $root
     * @return string
     * @author cdyun(121625706@qq.com)
     */
    protected function getBucketPath($path, $root = null): string
    {
        if (!is_dir($this->bucketPath)) {
            throw new ValidateException('路径不存在：' . $this->bucketPath);
        }
        if (!is_writable($this->bucketPath)) {
            throw new ValidateException('文件不可写：' . $this->bucketPath);
        }
        if ($root === null) $root = $this->bucketPath;
        return str_replace('\\', '/', $root . '/' . $path);
    }

    /**
     * 检测存储路径是否存在，创建不存在的存储路径
     * @param $dir
     * @return bool
     * @author cdyun(121625706@qq.com)
     */
    protected function checkBucketPath($dir): bool
    {
        return is_dir($dir) == true || mkdir($dir, 0777, true) == true;
    }

    /**
     * 文件流上传
     * @param string $fileContent - 文件内容
     * @param string $key - 文件名
     * @return \StdClass
     * @author cdyun(121625706@qq.com)
     */
    public function stream(string $fileContent, string $key): \StdClass
    {
        //验证路径是否存在且可写
        $dir = $this->getBucketPath($this->path);
        if (!$this->checkBucketPath($dir)) {
            throw new ValidateException('无法写入：' . $dir);
        }
        $fileName = $dir . '/' . $key;
        file_put_contents($fileName, $fileContent);
        if (!$fileName) {
            throw new ValidateException('上传失败：');
        }
        $this->fileInfo->fileName = $key;
        $this->fileInfo->originLink = Filesystem::url('/' . $this->path . '/' . $key);
        $this->fileInfo->signLink = Filesystem::url('/' . $this->path . '/' . $key);
        return $this->fileInfo;
    }

    /**
     * 删除文件
     * @param string $filePath - 文件路径
     * @return true
     * @author cdyun(121625706@qq.com)
     */
    public function delete(string $filePath): bool
    {
        try {
            $driver = Filesystem::getDefaultDriver();
            // 获取存储外部URL路径
            $bucketUrl = Filesystem::getDiskConfig($driver, 'url');
            // 去除开头的URL路径
            $filePath = ltrim($filePath, $bucketUrl);
            // 获取文件绝对路径
            $filePath = Filesystem::path($filePath);
            if (!file_exists($filePath)) {
                throw new ValidateException('文件不存在');
            }
            unlink($filePath);
            return true;
        } catch (ValidateException $e) {
            throw new ValidateException($e->getMessage());
        }
    }

    /**
     * 签名
     * @param string $key - 文件名
     * @param int $expires - 有效期
     * @return array
     * @author cdyun(121625706@qq.com)
     */
    public function sign(string $key, int $expires = 7200): array
    {
        return [
            'url' => Filesystem::url($key),
            'key' => $key,
            'result' => 'Local本地存储不支持签名',
        ];
    }

    /**
     * 存储绝对路径
     * @return void
     * @author cdyun(121625706@qq.com)
     */
    protected function initialize()
    {
        $driver = Filesystem::getDefaultDriver();
        $this->bucketPath = Filesystem::getDiskConfig($driver, 'root');
    }
}