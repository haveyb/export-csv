<?php
declare(strict_types = 1);

namespace haveyb\ExportCsv;

use \PDO;


class ExportCsv
{
    private $mysqlConnect;
    private function __construct()
    {
        // 获取配置的mysql连接
        $this->mysqlConnect = require './MySQLConfig.php';
    }

    /**
     * 数据导出（csv文件）
     *
     * @param string $sql
     * @param array $head
     * @param string $fileName
     * @throws \Exception
     */
    public function exportCsv(string $sql, array $head, $sqlConnect = 'default', string $fileName = '')
    {
        $fileName = $fileName ?? date('Y-m-d H:i:s').rand(1000, 9999);
        try {
            $data = $this->objectDataToArray($this->getMySQLData($sql, $sqlConnect));
            $fileName = iconv('utf-8', 'gbk', $fileName);

            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment; filename='. $fileName.'.csv');
            header('Cache-Control:max-age=0');

            $output = fopen('php://output', 'w') or die('can‘t open php://output');
            // 处理表头
            if ($head) {
                $head = array_map(function ($value) {
                    return iconv('UTF-8', 'GBK', $value);
                }, $head);
                fputcsv($output, $head);
            }
            // 处理内容
            foreach ($data as $k => $item) {
                $item = array_map(function ($val) {
                    return mb_convert_encoding($val, 'GBK', 'UTF-8');
                }, $item);
                fputcsv($output, array_values($item));
            }
            fclose($output) or die('can‘t close php://output');
            exit();
        } catch (\Exception $exception) {
            die($exception->getMessage());
        }
    }

    /**
     * 返回指定SQL的应得数据
     *
     * @param $sql
     * @param $sqlConnect
     * @return \Generator
     * @throws \Exception
     */
    private function getMySQLData($sql, $sqlConnect)
    {
        // 保持长连接
        $pdo = $this->getMySQLConnect($sqlConnect);
        $stmt = $pdo->query($sql);
        // 使用 yield 节省内存
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            yield $row;
        }
    }

    /**
     * 获取MySQL的长连接
     *
     * @param $sqlConnect
     * @return PDO
     * @throws \Exception
     */
    private function getMySQLConnect($sqlConnect)
    {
        if (!in_array($sqlConnect, array_keys($this->mysqlConnect))) {
            throw new \Exception('mysql连接参数传递错误');
        }
        $mysqlConfig = $this->mysqlConnect[$sqlConnect];
        // 保持长连接
        return new PDO(...$mysqlConfig, [PDO::ATTR_PERSISTENT => true]);
    }

    /**
     * 将迭代器对象转换为数组
     *
     * @param $data
     * @return \Generator
     */
    private function objectDataToArray($data)
    {
        foreach ($data as $v) {
            yield $v;
        }
    }
}