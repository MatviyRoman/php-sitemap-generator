
# PHP Sitemap Generator

## Overview

The PHP Sitemap Generator is a script designed to recursively crawl a website, collect URLs, and store them in a database. It can also generate an XML sitemap from the collected URLs, which is essential for SEO optimization.

This script handles various tasks such as filtering out excluded patterns, avoiding duplicate entries, checking page statuses, and supporting dynamic database creation and updates.

## Features

* **Automatic Database Setup:** Creates a database and table if they do not exist.
* **Asynchronous Page Loading:** Uses cURL multi-handle for efficient web scraping.
* **Page Status Validation:** Ensures only active pages (status 200) are stored in the database.
* **Exclude Patterns:** Skips specific pages based on configurable patterns.
* **Recursive Crawling:** Traverses internal links on the website.
* **XML Sitemap Generation:** Automatically creates a sitemap.xml file with valid URLs from the database.

## Prerequisites

* PHP 7.4 or higher
* PDO extension enabled for database interaction
* A valid `package.json` file containing database credentials:

```json
{
  "database": {
    "host": "localhost",
    "port": "3306",
    "db": "sitemap_db",
    "username": "root",
    "password": ""
  }
}
```

## Installation

1. Clone the repository or download the script.
2. Place the script files in your project directory.
3. Ensure the `package.json` file is configured with the correct database credentials.
4. Verify PHP is installed and properly configured on your system.

## Usage

1. Open the script and set the `$baseUrl` variable to the URL of your website:
   ```php
   $baseUrl = 'https://example.com';
   ```
2. Run the script via CLI or a web server.
3. The script will:
   * Create the necessary database and table.
   * Crawl the website starting from the base URL.
   * Save valid URLs to the database.
   * Generate an XML sitemap file (`sitemap.xml`) in the script's directory.

## Configuration

### Excluded Patterns

You can modify the `$excludePatterns` array to exclude specific URL patterns during the crawl:

```php
$excludePatterns = [
    '/cart/',
    '/compare/',
    '/profile/',
    '/download/',
    '/search/'
];
```

### Database Structure

The script creates the following database table:

```sql
CREATE TABLE sitemap_urls (
    id INT AUTO_INCREMENT PRIMARY KEY,
    url VARCHAR(255) NOT NULL,
    lastmod DATE NOT NULL,
    changefreq VARCHAR(20) DEFAULT 'weekly',
    priority DECIMAL(2,1) DEFAULT 0.8,
    status INT NOT NULL
);
```

## Output

* **Database:** All crawled URLs are stored with metadata such as `lastmod`, `changefreq`, `priority`, and `status`.
* **XML Sitemap:** The `sitemap.xml` file contains all valid URLs ready for submission to search engines.

## Error Handling

* If the `package.json` file is missing or incorrectly formatted, the script will terminate with an error.
* If a URL is inaccessible or returns a non-200 status code, it will not be saved in the database.

## License

This project is open-source and available under the [MIT License](https://roman.matviy.pp.ua).

## Contributions

Contributions are welcome! Feel free to submit issues or pull requests to improve the script.

## Contact

For any inquiries or support, contact [roman@matviy.pp.ua](mailto:roman@matviy.pp.ua) or site [https://roman.matviy.pp.ua](https://roman.matviy.pp.ua) .
