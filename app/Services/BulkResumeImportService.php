<?php

/**
 * Expand uploaded resume files into one import unit per candidate (incl. compiled PDF split).
 */
class BulkResumeImportService
{
    /**
     * @return array<int, array{name:string,tmp_name:string,error:int,size:int,source_label?:string}>
     */
    public static function expandFiles(array $filesField, $splitCompiled = false)
    {
        $normalized = self::normalizeUploadField($filesField);
        $out = [];

        foreach ($normalized as $file) {
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if ($ext === 'pdf' && ($splitCompiled || self::pdfLikelyContainsMultipleCvs($file['tmp_name']))) {
                $pieces = self::splitCompiledPdf($file);
                if (count($pieces) > 1) {
                    foreach ($pieces as $piece) {
                        $out[] = $piece;
                    }
                    continue;
                }
            }
            $out[] = $file;
        }

        return $out;
    }

    public static function pdfLikelyContainsMultipleCvs($absolutePath)
    {
        $parser = new ResumeParserService();
        $text = $parser->extractTextOnly($absolutePath, 'pdf');
        if (strlen(trim($text)) < 200) {
            return false;
        }

        return self::countDistinctEmails($text) >= 2;
    }

    /**
     * @return array<int, array{name:string,tmp_name:string,error:int,size:int,source_label?:string}>
     */
    public static function splitCompiledPdf(array $sourceFile)
    {
        $path = $sourceFile['tmp_name'];
        if (!is_readable($path)) {
            return [$sourceFile];
        }

        $pageSplit = self::splitPdfByPages($path, $sourceFile['name']);
        if (count($pageSplit) > 1) {
            return $pageSplit;
        }

        $textSplit = self::splitPdfByText($path, $sourceFile['name']);
        if (count($textSplit) > 1) {
            return $textSplit;
        }

        return [$sourceFile];
    }

    private static function splitPdfByPages($pdfPath, $originalName)
    {
        if (!function_exists('shell_exec')) {
            return [];
        }
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tp_bulk_' . str_replace('-', '', uuid());
        if (!@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return [];
        }

        $pattern = $dir . DIRECTORY_SEPARATOR . 'page-%d.pdf';
        PopplerTool::run('pdfseparate', escapeshellarg($pdfPath) . ' ' . escapeshellarg($pattern));
        $pages = glob($dir . DIRECTORY_SEPARATOR . 'page-*.pdf');
        if (!$pages || count($pages) < 2) {
            self::rmDir($dir);

            return [];
        }
        natsort($pages);
        $pages = array_values($pages);

        $groups = [];
        $current = [];
        foreach ($pages as $idx => $pagePath) {
            $pageText = self::pdftotextPage($pagePath);
            if ($idx > 0 && self::looksLikeResumeStart($pageText) && !empty($current)) {
                $groups[] = $current;
                $current = [];
            }
            $current[] = $pagePath;
        }
        if (!empty($current)) {
            $groups[] = $current;
        }

        if (count($groups) < 2) {
            self::rmDir($dir);

            return [];
        }

        $base = pathinfo($originalName, PATHINFO_FILENAME);
        $result = [];
        foreach ($groups as $i => $group) {
            $merged = self::mergePdfPages($group, $dir);
            if (!$merged || !is_readable($merged)) {
                continue;
            }
            $result[] = [
                'name' => $base . '_cv_' . ($i + 1) . '.pdf',
                'tmp_name' => $merged,
                'error' => UPLOAD_ERR_OK,
                'size' => filesize($merged),
                'source_label' => $originalName,
            ];
        }

        return $result;
    }

    private static function splitPdfByText($pdfPath, $originalName)
    {
        $parser = new ResumeParserService();
        $text = $parser->extractTextOnly($pdfPath, 'pdf');
        $blocks = self::splitTextIntoBlocks($text);
        if (count($blocks) < 2) {
            return [];
        }

        $base = pathinfo($originalName, PATHINFO_FILENAME);
        $result = [];
        foreach ($blocks as $i => $block) {
            $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tp_cv_' . str_replace('-', '', uuid()) . '.txt';
            file_put_contents($tmp, $block);
            $result[] = [
                'name' => $base . '_cv_' . ($i + 1) . '.txt',
                'tmp_name' => $tmp,
                'error' => UPLOAD_ERR_OK,
                'size' => strlen($block),
                'source_label' => $originalName,
                'parsed_text' => $block,
            ];
        }

        return $result;
    }

    public static function splitTextIntoBlocks($text)
    {
        $text = sanitize_utf8_string(str_replace(["\r\n", "\r"], "\n", $text));
        if ($text === '') {
            return [];
        }

        $byFf = preg_split('/\f+/u', $text);
        $blocks = array_values(array_filter(array_map('trim', $byFf), function ($b) {
            return strlen($b) > 120;
        }));
        if (count($blocks) > 1) {
            return $blocks;
        }

        $parts = preg_split('/\n(?=(?:CURRICULUM\s+VITAE|RESUME|BIO-?DATA)\b)/i', $text);
        $blocks = array_values(array_filter(array_map('trim', $parts), function ($b) {
            return strlen($b) > 120;
        }));
        if (count($blocks) > 1) {
            return $blocks;
        }

        if (!preg_match_all('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $text, $matches, PREG_OFFSET_CAPTURE)) {
            return [$text];
        }
        $emails = $matches[0];
        if (count($emails) < 2) {
            return [$text];
        }

        $blocks = [];
        for ($i = 0; $i < count($emails); $i++) {
            $start = $emails[$i][1];
            if ($i > 0) {
                $start = max(0, $start - 500);
            }
            $end = ($i + 1 < count($emails)) ? $emails[$i + 1][1] : strlen($text);
            $chunk = trim(substr($text, $start, $end - $start));
            if (strlen($chunk) > 100) {
                $blocks[] = $chunk;
            }
        }

        return count($blocks) > 1 ? $blocks : [$text];
    }

    private static function looksLikeResumeStart($text)
    {
        $head = substr($text, 0, 1500);
        if (!preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $head)) {
            return false;
        }
        if (preg_match('/\b(?:curriculum vitae|resume|biodata|profile)\b/i', $head)) {
            return true;
        }
        if (preg_match('/^[A-Z][a-z]+(?:\s+[A-Z][a-z]+){1,3}\s*$/m', substr($head, 0, 250))) {
            return true;
        }

        return strlen(trim($head)) > 100;
    }

    private static function pdftotextPage($pagePath)
    {
        if (!function_exists('shell_exec')) {
            return '';
        }
        $out = PopplerTool::run('pdftotext', escapeshellarg($pagePath) . ' -');

        return sanitize_utf8_string($out ?? '');
    }

    private static function mergePdfPages(array $pagePaths, $workDir)
    {
        if (count($pagePaths) === 1) {
            return $pagePaths[0];
        }
        $out = $workDir . DIRECTORY_SEPARATOR . 'merged_' . str_replace('-', '', uuid()) . '.pdf';
        $args = array_map('escapeshellarg', $pagePaths);
        PopplerTool::run('pdfunite', implode(' ', $args) . ' ' . escapeshellarg($out));
        if (is_readable($out) && filesize($out) > 100) {
            return $out;
        }

        return $pagePaths[0];
    }

    private static function countDistinctEmails($text)
    {
        if (!preg_match_all('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $text, $m)) {
            return 0;
        }
        $emails = array_map('strtolower', $m[0]);

        return count(array_unique($emails));
    }

    private static function normalizeUploadField(array $files)
    {
        if (empty($files['name'])) {
            return [];
        }
        if (!is_array($files['name'])) {
            return [$files];
        }
        $out = [];
        foreach ($files['name'] as $i => $name) {
            if (!$name) {
                continue;
            }
            $out[] = [
                'name' => $name,
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i],
                'size' => $files['size'][$i],
            ];
        }

        return $out;
    }

    private static function rmDir($dir)
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (glob($dir . DIRECTORY_SEPARATOR . '*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($dir);
    }
}
