<?php
/**
 * Scan all PHP files in HSMS for referenced URLs, hrefs, redirects, and actions.
 * Identify any referenced page that does not exist on disk.
 */

declare(strict_types=1);

$root = realpath(__DIR__ . '/..');
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
$allPhpFiles = [];
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $allPhpFiles[] = $file->getPathname();
    }
}

$referencedPages = [];

foreach ($allPhpFiles as $filePath) {
    $content = file_get_contents($filePath);

    // Match base_url('path.php')
    preg_match_all('/base_url\([\'"]([^\'"]+)/', $content, $m1);
    foreach ($m1[1] as $ref) {
        $clean = strtok($ref, '?#');
        if (str_ends_with($clean, '.php')) {
            $referencedPages[$clean][] = $filePath;
        }
    }

    // Match href="...php"
    preg_match_all('/href=[\'"]([^\'"]+\.php)/', $content, $m2);
    foreach ($m2[1] as $ref) {
        $clean = strtok($ref, '?#');
        $clean = preg_replace('/^.*?HSMS\//', '', $clean);
        $clean = ltrim($clean, '/');
        if (str_ends_with($clean, '.php') && !str_starts_with($clean, 'http')) {
            $referencedPages[$clean][] = $filePath;
        }
    }

    // Match action="...php"
    preg_match_all('/action=[\'"]([^\'"]+\.php)/', $content, $m3);
    foreach ($m3[1] as $ref) {
        $clean = strtok($ref, '?#');
        $clean = preg_replace('/^.*?HSMS\//', '', $clean);
        $clean = ltrim($clean, '/');
        if (str_ends_with($clean, '.php') && !str_starts_with($clean, 'http')) {
            $referencedPages[$clean][] = $filePath;
        }
    }

    // Match redirect('...php')
    preg_match_all('/redirect(?:_with_message)?\([^\'"]*base_url\([\'"]([^\'"]+)/', $content, $m4);
    foreach ($m4[1] as $ref) {
        $clean = strtok($ref, '?#');
        if (str_ends_with($clean, '.php')) {
            $referencedPages[$clean][] = $filePath;
        }
    }
}

echo "Total unique internal PHP endpoints referenced: " . count($referencedPages) . "\n\n";

$missing = [];
$existing = [];

foreach ($referencedPages as $page => $sources) {
    $cleanPath = ltrim($page, '/');
    $cleanPath = preg_replace('/^HSMS\//', '', $cleanPath);
    $fullPath = $root . '/' . $cleanPath;

    if (!file_exists($fullPath)) {
        $missing[$cleanPath] = array_unique($sources);
    } else {
        $existing[$cleanPath] = count(array_unique($sources));
    }
}

if (empty($missing)) {
    echo ">>> ALL " . count($existing) . " REFERENCED PAGES EXIST ON DISK (0 MISSING)! <<<\n";
} else {
    echo ">>> FOUND " . count($missing) . " MISSING PAGES: <<<\n";
    foreach ($missing as $page => $sources) {
        echo "\n[MISSING] " . $page . "\n";
        echo "  Referenced in:\n";
        foreach (array_slice($sources, 0, 3) as $s) {
            echo "    - " . str_replace($root . DIRECTORY_SEPARATOR, '', $s) . "\n";
        }
    }
}
