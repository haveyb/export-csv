1、说明

PHP 在大数据导出。尤其是上五十万以后，就会显得尤为吃内存，使用常规的导出方法，可能会导致内存溢出，直接整个服务都挂掉。

其实 PHP 从 5.5 开始，就已经有了迭代器 yield，使用 yield 逐行读取并写入，可以大量的节省内存，不用再担心导出百万千万条数据，内存溢出了。

实现上已关闭 PDO 缓冲查询（MYSQL_ATTR_USE_BUFFERED_QUERY=false），保证数据真正流式从 MySQL 读出，而不是先全表载入内存再 yield。

导出文件采用 UTF-8 编码并在文件头写入 BOM（\xEF\xBB\xBF），Excel/WPS 可正确识别、中文不乱码，且支持全字符集（生僻字、emoji 等均不丢失）。

2、配置 

配置 MySQL 连接参数，在 src/MySQLConfig.php 下（DSN 已带 charset=utf8mb4，避免中文乱码）。

默认使用 default 下标下的配置参数连接，可以在调用时传递指定的连接。

也可以在 new ExportCsv() 时直接传入配置：传入字符串视为配置文件路径，传入数组则直接使用该配置（无需 MySQLConfig.php 文件）。


3、使用

```php
$csv = new ExportCsv();

$sql = 'select admin_id,user_name,age from admin';

$head = ['用户ID', '用户名', '年龄'];

$csv->exportCsv($sql, $head, 'test_1', '后台用户表');
```

效果示例：

![](src/example.png)

注意：

在导出数据到表格时，第一个字段名尽量不要设置为 'ID' 这两个字母，因为这将会使 wps 报一个 ’wps表格已经监测到...是SYLK文件，但是不能将其加载‘的错误，不妨试一试。