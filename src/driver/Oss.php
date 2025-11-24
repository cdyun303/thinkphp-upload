<?php
/**
 * Oss.php
 * @author cdyun(121625706@qq.com)
 * @date 2025/11/9 14:12
 */

declare (strict_types=1);

namespace Cdyun\ThinkphpUpload\driver;

use AlibabaCloud\Oss\V2 as OssClient;
use AlibabaCloud\Oss\V2\Client;
use Cdyun\ThinkphpUpload\BaseUpload;
use GuzzleHttp\Psr7\LazyOpenStream;
use GuzzleHttp\Psr7\LimitStream;
use think\exception\ValidateException;
use think\facade\Filesystem;
use think\File;

class Oss extends BaseUpload
{
    /**
     * config参数校验
     */
    private const CONFIG_FIELDS = ['aki', 'aks', 'endpoint', 'region'];
    /**
     * 存储桶
     */
    protected string $bucketPath;
    private ?Client $client = null;

    /**
     * 文件上传（直接上传/分片上传）
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

        //生成文件名
        $name = $this->generateFileName($fileInfo['ext']);

        //  大于10MB采用分片上传
        if ($fileInfo['size'] > 10485760) {
            $flag = $this->partUpload($fileInfo['file'], $name);
        } else {
            $flag = $this->directUpload($fileInfo['file'], $name);
        }
        $this->fileInfo->realName = $file->getOriginalName();
        $this->fileInfo->fileName = $flag['key'];
        $this->fileInfo->originLink = $flag['url'];
        $this->fileInfo->signLink = $this->signUrl($flag['key']);
        return $this->fileInfo;
    }

    /**
     * 分片上传
     * @param $file - 文件对象
     * @param string $name - 文件名
     * @return array
     * @author cdyun(121625706@qq.com)
     */
    protected function partUpload($file, string $name): array
    {
        try {
            $key = $this->setFileKey($name); // 含路径的对象名称
            $bucket = $this->bucketPath;

            $client = $this->createClient();

            // 初始化分片上传任务
            $initResult = $client->initiateMultipartUpload(
                new OssClient\Models\InitiateMultipartUploadRequest(
                    bucket: $bucket,
                    key: $key
                )
            );

            // 定义大文件路径和分片大小
            $bigFileName = $file->getPathname();
            $partSize = 3 * 1024 * 1024; // 默认3MB
            $fileSize = filesize($bigFileName);
            $partsNum = intdiv($fileSize, $partSize) + intval(1);
            $parts = [];

            $i = 1;
            $file = new LazyOpenStream($bigFileName, 'rb');
            while ($i <= $partsNum) {
                // 上传单个分片
                $partResult = $client->uploadPart(
                    new OssClient\Models\UploadPartRequest(
                        bucket: $bucket,
                        key: $key,
                        partNumber: $i,
                        uploadId: $initResult->uploadId,
                        contentLength: null,
                        contentMd5: null,
                        trafficLimit: null,
                        requestPayer: null,
                        body: new LimitStream($file, $partSize, ($i - 1) * $partSize)
                    )
                );

                // 保存分片上传结果
                $part = new OssClient\Models\UploadPart(
                    partNumber: $i,
                    etag: $partResult->etag
                );

                $parts[] = $part;
                $i++;
            }

            // 完成分片上传任务
            $result = $client->completeMultipartUpload(
                new OssClient\Models\CompleteMultipartUploadRequest(
                    bucket: $bucket,
                    key: $key,
                    uploadId: $initResult->uploadId,
                    acl: null,
                    completeMultipartUpload: new OssClient\Models\CompleteMultipartUpload(
                        parts: $parts
                    )
                )
            );
            if ($result->statusCode != 200) {
                throw new \Exception('上传失败');
            }
            return [
                'url' => $this->processFileUrl($key),
                'key' => $key,
                'result' => $result,
            ];
        } catch (\Exception  $e) {
            throw new ValidateException($e->getMessage());
        }
    }

    /**
     * 根据路径path生成文件名key
     * @param string $name - 文件名
     * @return string
     * @author cdyun(121625706@qq.com)
     */
    protected function setFileKey(string $name): string
    {
        return $this->path ? $this->path . '/' . $name : $name;
    }

    /**
     * 创建客户端
     * @return Client
     */
    protected function createClient(): Client
    {
        if ($this->client === null) {
            $credentialsProvider = new OssClient\Credentials\StaticCredentialsProvider(
                $this->config['aki'],
                $this->config['aks']
            );
            $cfg = OssClient\Config::loadDefault();
            $cfg->setCredentialsProvider($credentialsProvider);
            $cfg->setEndpoint($this->config['endpoint']);
            $cfg->setRegion($this->config['region']);
            $this->client = new OssClient\Client($cfg);
        }

        return $this->client;
    }

    /**
     * 获取文件URL
     * @param string $key
     * @return string
     */
    protected function processFileUrl(string $key): string
    {
        return "{$this->getBucketUrl()}/{$key}";
    }

    /**
     * 获取存储桶bucket域名
     * @return string
     */
    public function getBucketUrl(): string
    {
        $driver = Filesystem::getDefaultDriver();
        $url = Filesystem::getDiskConfig($driver, 'url');
        return $url ?: "https://{$this->bucketPath}.{$this->config['endpoint']}";
    }

    /**
     * 直传文件
     * @param $file - 文件对象
     * @param string $name - 文件名
     * @return array
     * @author cdyun(121625706@qq.com)
     */
    protected function directUpload($file, string $name): array
    {
        try {
            // 含路径的对象名称
            $key = $this->setFileKey($name);

            $client = $this->createClient();

            $request = new OssClient\Models\PutObjectRequest(bucket: $this->bucketPath, key: $key);
            $request->body = OssClient\Utils::streamFor(fopen($file->getPathname(), 'r')); // 设置请求体为文件流

            $result = $client->putObject($request);
            if ($result->statusCode != 200) {
                throw new \Exception('上传失败');
            }
            return [
                'url' => $this->processFileUrl($key),
                'key' => $key,
                'result' => $result,
            ];
        } catch (\Exception  $e) {
            throw new ValidateException($e->getMessage());
        }
    }

    /**
     * 签名 URL
     * @param string $key - 含存储路径文件名
     * @param int $expires - 过期时间
     * @return string
     * @author cdyun(121625706@qq.com)
     */
    protected function signUrl(string $key, int $expires = 7200): string
    {
        $result = $this->sign($key, $expires);
        return $result['url'];
    }

    /**
     * 签名URL
     * @param string $key - 含存储路径文件名
     * @param int $expires - 过期时间
     * @return array
     * @author cdyun(121625706@qq.com)
     */
    public function sign(string $key, int $expires = 7200): array
    {
        try {
            $client = $this->createClient();
            $request = new OssClient\Models\GetObjectRequest(
                bucket: $this->bucketPath,
                key: $key
            );
            $result = $client->presign($request, ['expires' => $expires]);
            return [
                'url' => $result->url,
                'key' => $key,
                'result' => $result,
            ];
        } catch (\Exception  $e) {
            throw new ValidateException($e->getMessage());
        }
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
        try {
            $key = $this->setFileKey($key);  // 含路径的对象名称
            if (!is_string($fileContent)) {
                throw new \Exception('请使用字符串上传');
            }
            $client = $this->createClient();
            $request = new OssClient\Models\PutObjectRequest(bucket: $this->bucketPath, key: $key);
            $request->body = OssClient\Utils::streamFor($fileContent);

            $result = $client->putObject($request);
            if ($result->statusCode != 200) {
                throw new \Exception('上传失败');
            }
            $flag = [
                'url' => $this->processFileUrl($key),
                'key' => $key,
                'result' => $result,
            ];
            $this->fileInfo->fileName = $flag['key'];
            $this->fileInfo->originLink = $flag['url'];
            $this->fileInfo->signLink = $this->signUrl($flag['key']);
            return $this->fileInfo;
        } catch (\Exception  $e) {
            throw new ValidateException($e->getMessage());
        }
    }

    /**
     * 删除文件
     * @param string $key - 文件名
     * @return bool
     * @author cdyun(121625706@qq.com)
     */
    public function delete(string $key): bool
    {
        try {
            $client = $this->createClient();
            $request = new OssClient\Models\DeleteObjectRequest(bucket: $this->bucketPath, key: $key);

            $result = $client->deleteObject($request);

            //HTTP状态码，例如204表示删除成功
            if ($result->statusCode != 204) {
                throw new \Exception('删除失败');
            }
            return true;
        } catch (\Exception  $e) {
            throw new ValidateException($e->getMessage());
        }
    }

    /**
     * 初始化
     * @return void
     * @author cdyun(121625706@qq.com)
     */
    protected function initialize()
    {
        $this->validateConfig($this->config, self::CONFIG_FIELDS);
        $driver = Filesystem::getDefaultDriver();
        $this->bucketPath = Filesystem::getDiskConfig($driver, 'root');
    }

    /**
     * 验证配置
     * @param array $data
     * @param array $requiredFields
     * @param string $errorMsg
     * @return void
     * @author cdyun(121625706@qq.com)
     */
    protected function validateConfig(array $data, array $requiredFields, string $errorMsg = '缺少必要参数：')
    {
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                throw new ValidateException($errorMsg . $field);
            }
        }
    }
}