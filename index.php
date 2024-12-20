<?php

class SitemapGenerator {
    private $pdo;
    private $excludePatterns;
    private $baseUrl;

    public function __construct($configPath, $excludePatterns, $baseUrl) {
        $this->excludePatterns = $excludePatterns;
        $this->baseUrl = $baseUrl;

        $config = $this->loadConfig($configPath);
        $this->initializeDatabase($config);
    }

    private function loadConfig($path) {
        if (!file_exists($path)) {
            throw new Exception('Configuration file not found.');
        }

        $content = file_get_contents($path);
        return json_decode($content, true);
    }

    private function initializeDatabase($config) {
        $dbHost = $config['database']['host'] ?? 'localhost';
        $dbPort = $config['database']['port'] ?? '3306';
        $dbName = $config['database']['db'] ?? 'database_name';
        $dbUsername = $config['database']['username'] ?? 'root';
        $dbPassword = $config['database']['password'] ?? '';

        // $dbHost = 'localhost';
        // $dbPort = '3306';
        // $dbName = 'sitemap';
        // $dbUsername = '';
        // $dbPassword = '';

        $dsn = "mysql:host=$dbHost;port=$dbPort;charset=utf8";
        $this->pdo = new PDO($dsn, $dbUsername, $dbPassword);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
        $this->pdo->exec("USE `$dbName`;");
        $this->createTable();
    }

    private function createTable() {
        $createTableSQL = "CREATE TABLE IF NOT EXISTS sitemap_urls (
            id INT AUTO_INCREMENT PRIMARY KEY,
            url VARCHAR(255) NOT NULL,
            lastmod DATE NOT NULL,
            changefreq VARCHAR(20) DEFAULT 'weekly',
            priority DECIMAL(2,1) DEFAULT 0.8,
            status INT NOT NULL
        );";
        $this->pdo->exec($createTableSQL);
    }

    public function crawlWebsite($url, $visited = []) {
        if (in_array($url, $visited)) {
            return $visited;
        }

        foreach ($this->excludePatterns as $pattern) {
            if (strpos($url, $pattern) !== false) {
                return $visited;
            }
        }

        $htmlResults = $this->asyncCurlRequests([$url]);

        if (!$htmlResults[$url]) {
            return $visited;
        }

        $html = $htmlResults[$url];
        $dom = new DOMDocument;
        @$dom->loadHTML($html);

        $xpath = new DOMXPath($dom);
        $noindexMeta = $xpath->query("//meta[@name='robots' and contains(@content, 'noindex')]");
        if ($noindexMeta->length > 0) {
            return $visited;
        }

        $visited[] = $url;
        $this->saveUrlToDatabase($url);

        $links = $dom->getElementsByTagName('a');
        $internalLinks = [];

        foreach ($links as $link) {
            $href = $link->getAttribute('href');

            if (strpos($href, '#') !== false) {
                continue;
            }

            $parsedBaseUrl = parse_url($this->baseUrl);
            $parsedHref = parse_url($href);

            if (empty($parsedHref['host']) || $parsedHref['host'] === $parsedBaseUrl['host']) {
                if (!isset($parsedHref['scheme'])) {
                    $href = rtrim($this->baseUrl, '/') . '/' . ltrim($href, '/');
                }
                $internalLinks[] = $href;
            }
        }

        foreach ($internalLinks as $href) {
            $visited = $this->crawlWebsite($href, $visited);
        }

        return $visited;
    }

    private function asyncCurlRequests($urls) {
        $multiHandle = curl_multi_init();
        $curlHandles = [];

        foreach ($urls as $url) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_multi_add_handle($multiHandle, $ch);
            $curlHandles[$url] = $ch;
        }

        $running = null;
        do {
            curl_multi_exec($multiHandle, $running);
            curl_multi_select($multiHandle);
        } while ($running > 0);

        $results = [];
        foreach ($curlHandles as $url => $ch) {
            $html = curl_multi_getcontent($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_multi_remove_handle($multiHandle, $ch);
            curl_close($ch);
            $results[$url] = ($httpCode == 200) ? $html : false;
        }

        curl_multi_close($multiHandle);
        return $results;
    }

    private function saveUrlToDatabase($url) {
        $status = $this->checkPageStatus($url);
        $lastmod = date('Y-m-d');
        $changefreq = 'weekly';
        $priority = 0.8;

        $stmt = $this->pdo->prepare("INSERT INTO sitemap_urls (url, lastmod, changefreq, priority, status)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE lastmod = VALUES(lastmod), changefreq = VALUES(changefreq), priority = VALUES(priority), status = VALUES(status)");
        $stmt->execute([$url, $lastmod, $changefreq, $priority, $status]);
    }

    private function checkPageStatus($url) {
        $headers = @get_headers($url);
        return strpos($headers[0], '200 OK') !== false ? 200 : 404;
    }

    public function generateSitemap() {
        $sitemap = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>');

        $stmt = $this->pdo->query("SELECT url, lastmod, changefreq, priority FROM sitemap_urls WHERE status = 200");
        $urls = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($urls as $urlData) {
            $urlElement = $sitemap->addChild('url');
            $urlElement->addChild('loc', htmlspecialchars($urlData['url']));
            $urlElement->addChild('lastmod', $urlData['lastmod']);
            $urlElement->addChild('changefreq', $urlData['changefreq']);
            $urlElement->addChild('priority', $urlData['priority']);
        }

        $sitemap->asXML('sitemap.xml');
        echo "Sitemap generated successfully!";
    }
}

// Використання класу
$excludePatterns = ['/cart/', '/compare/', '/profile/', '/download/', '/search/'];
$baseUrl = 'https://roman.matviy.pp.ua';
$sitemapGenerator = new SitemapGenerator('package.json', $excludePatterns, $baseUrl);
$sitemapGenerator->crawlWebsite($baseUrl);
$sitemapGenerator->generateSitemap();