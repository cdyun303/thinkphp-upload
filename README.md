Thinkphp Upload插件
=====

### 安装

```
composer require cdyun/thinkphp-upload
```

### 例子

```PHP
use Cdyun\ThinkphpUpload\UploadEnforcer;

$upload = new UploadEnforcer();
// 默认上传
$result = $upload->move();
// 指定上传路径
$result = $upload->path('uploads')->move();
// 指定文件名
$result = $upload->name('file.txt')->move();
// 自定义验证
$result = $upload->validate(['fileSize' => 1024])->move();
// 多个配置
$result = $upload->path('uploads')->validate(['fileSize' => 1024])->name('file.txt')->move();

```

##### path() - 支持设置路径；
##### name() - 支持设置文件名；
##### validate() - 支持自定义验证，参考TP官方的上传验证；
##### move() - 文件上传，大文件支持分片；
##### steam() - 文件流上传；
##### delete() - 文件删除；
##### sign() - 文件签名；
### 配置文件
- （TP框架配置文件）config/filesystem.php
```PHP
<?php

return [
    // 默认磁盘
    'default' => 'oss',
    // 磁盘列表
    'disks'   => [
        // 本地上传
        'local'  => [
            // 磁盘类型
            'type'       => 'local',
            // 磁盘路径
            'root'       => app()->getRootPath() . 'public/bucket',
            // 磁盘路径对应的外部URL路径
            'url'        => '/bucket',
            // 可见性
            'visibility' => 'public',
        ],
        // 阿里云存储
        'oss' => [
            // 磁盘类型，不要修改直接使用Local驱动
            'type'       => 'local',
            // 磁盘路径，改为存储桶
            'root'       => 'tzhapp2',
            // 磁盘路径对应的外部URL路径，改为存储桶的域名，结尾不要带斜杠
            'url'        => '',
            // 可见性
            'visibility' => 'public',
        ],
        // 更多的磁盘配置信息
    ],
];
```

- （cdyun插件配置文件）config/cdyun.php
```PHP
<?php

return [
    // 上传配置
    'upload' => [
        //上传文件大小100*1024KB
        'fileSize' => 204800,
        //上传图片大小
        'imgSize' => 1024,
        //上传文件后缀类型
        'fileExt' => 'gif,jpg,jpeg,png,mp4,doc,docx,txt,pdf,xls,xlsx,ppt,pptx,mp3,wma,wav,zip',
        //上传图片类型
        'imgExt' => 'gif,jpg,jpeg,png',
        //上传路径,默认为files
        'path' => 'files',
        //驱动模式配置信息
        'stores' => [
            //本地上传配置
            'local' => [],
            //七牛云上传配置
            'qiniu' => [],
            //oss上传配置
            'oss' => [
                'aki' => '',
                'aks' => '',
                'endpoint' => '',
                'region' => "",
            ],
            //cos上传配置
            'cos' => [],
        ]
    ]
];
```
