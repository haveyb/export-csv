<?php
declare(strict_types=1);

namespace haveyb\ExportCsv;

use PDO;

class ExportCsv
{
    /** @var array */
    private $mysqlConfig;

    /**
     * @param string|array|null $config 配置文件路径、配置数组，或留空使用默认 src/MySQLConfig.php
     */
    public function __construct($config = null)
    {
        if (is_array($config)) {
            $this->mysqlConfig = $config;
            return;
        }

        $path = $config ?? __DIR__ . '/MySQLConfig.php';
        if (!is_file($path)) {
            throw new \InvalidArgumentException("MySQL 配置文件不存在：{$path}");
        }

        $this->mysqlConfig = require $path;
        if (!is_array($this->mysqlConfig)) {
            throw new \RuntimeException('MySQL 配置文件必须返回一个数组');
        }
    }

    /**
     * 数据导出（csv 文件，流式省内存，UTF-8 + BOM 编码）
     *
     * @param string $sql        查询 SQL
     * @param array  $head       表头（如 ['用户ID', '用户名']）
     * @param string $sqlConnect MySQLConfig 中的连接下标，默认 default
     * @param string $fileName   下载文件名（不含扩展名），留空自动生成
     */
    public function exportCsv(string $sql, array $head, string $sqlConnect = 'default', string $fileName = ''): void
    {
        $fileName = $fileName ?: (date('Ymd_His') . '_' . mt_rand(1000, 9999));
        $fileName = $this->sanitizeFileName($fileName);

        set_time_limit(0);

        try {
            $output = fopen('php://output', 'w');
            if ($output === false) {
                throw new \RuntimeException('无法打开 php://output 输出流');
            }

            // 通知浏览器下载 CSV（UTF-8 编码，配合 BOM 让 Excel 正确识别）
            header('Content-Type: text/csv; charset=UTF-8');
            header(
                'Content-Disposition: attachment; filename="' . $fileName . '.csv";'
                . " filename*=UTF-8''" . rawurlencode($fileName) . '.csv'
            );
            header('Cache-Control: max-age=0');

            // 写入 UTF-8 BOM，Excel/WPS 才能正确识别 UTF-8 而不乱码
            fwrite($output, "\xEF\xBB\xBF");

            // 表头
            if ($head) {
                fputcsv($output, array_map([$this, 'normalizeCell'], $head));
            }

            // 内容：逐行流式读取（yield），不会一次性把全表读进内存
            foreach ($this->getMySQLData($sql, $sqlConnect) as $row) {
                fputcsv($output, array_map([$this, 'normalizeCell'], array_values($row)));
            }

            fclose($output);
            exit;
        } catch (\Throwable $e) {
            if (isset($output) && is_resource($output)) {
                fclose($output);
            }
            http_response_code(500);
            header('Content-Type: text/plain; charset=UTF-8');
            echo '导出失败：' . $e->getMessage();
            exit;
        }
    }

    /**
     * 逐行读取 MySQL 数据，使用 yield 节省内存
     *
     * @return \Generator
     */
    private function getMySQLData(string $sql, string $sqlConnect): \Generator
    {
        $pdo = $this->getMySQLConnect($sqlConnect);
        $stmt = $pdo->query($sql);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            yield $row;
        }
    }

    /**
     * 获取 MySQL 连接
     * - 关闭缓冲查询，让 yield 真正生效（否则全表仍会先读进内存）
     * - 开启异常模式，SQL 错误时明确抛出而非静默 fatal
     *
     * @return PDO
     */
    private function getMySQLConnect(string $sqlConnect): PDO
    {
        if (!array_key_exists($sqlConnect, $this->mysqlConfig)) {
            throw new \InvalidArgumentException("mysql 连接参数「{$sqlConnect}」不存在");
        }

        $cfg = $this->mysqlConfig[$sqlConnect];
        $options = [
            PDO::ATTR_PERSISTENT               => true,
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false,
            PDO::ATTR_ERRMODE                  => PDO::ERRMODE_EXCEPTION,
        ];

        return new PDO($cfg['dsn'], $cfg['user'], $cfg['password'], $options);
    }

    /**
     * 单元格归一化为字符串（保持 UTF-8，仅处理 null / 数字等非字符串类型）
     */
    private function normalizeCell($value): string
    {
        if ($value === null) {
            return '';
        }

        return (string) $value;
    }

    /**
     * 过滤文件名中的非法字符（Windows / HTTP header 不允许）
     */
    private function sanitizeFileName(string $name): string
    {
        return preg_replace('/[\/\\\\:\*\?\"<>\|]/', '_', $name);
    }
}
