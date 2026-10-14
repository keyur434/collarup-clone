<?php

class ResumeParserService
{
    /**
     * @return array{first_name?:string,last_name?:string,email?:string,phone?:string,years_experience?:float,current_company?:string,skills?:string[]}
     */
    public function extractTextOnly($absolutePath, $extension)
    {
        return $this->extractText($absolutePath, strtolower($extension));
    }

    public function parseFile($absolutePath, $extension)
    {
        $text = $this->extractText($absolutePath, strtolower($extension));
        if ($text === '') {
            return [];
        }
        return $this->parseHeuristic($text);
    }

    public function parseText($text)
    {
        $text = sanitize_utf8_string(trim((string) $text));
        if ($text === '') {
            return [];
        }

        return $this->parseHeuristic($text);
    }

    private function extractText($path, $ext)
    {
        if ($ext === 'pdf') {
            $text = $this->extractPdfText($path);
            if ($text !== '') {
                return $text;
            }
        }
        if ($ext === 'docx') {
            return $this->extractDocxText($path);
        }
        if ($ext === 'doc') {
            return $this->extractBinaryText($path);
        }
        return '';
    }

    private function extractPdfText($path)
    {
        $bin = @file_get_contents($path);
        if ($bin === false) {
            return '';
        }
        if (preg_match_all('/\((?:\\\\.|[^\\\\)])*\)/s', $bin, $m)) {
            $chunks = [];
            foreach ($m[0] as $part) {
                $part = trim($part, '()');
                $part = str_replace(['\\(', '\\)', '\\n', '\\r'], ['(', ')', "\n", ''], $part);
                if (strlen($part) > 2 && preg_match('/[a-zA-Z]/', $part)) {
                    $chunks[] = $part;
                }
            }
            $text = implode(' ', $chunks);
            if (strlen($text) > 80) {
                return $this->normalizeWhitespace($text);
            }
        }
        if (function_exists('shell_exec')) {
            $escaped = escapeshellarg($path);
            $out = PopplerTool::run('pdftotext', $escaped . ' -');
            if (!empty($out) && strlen(trim($out)) > 40) {
                return $this->normalizeWhitespace($out);
            }
        }
        return $this->normalizeWhitespace($this->extractBinaryText($path));
    }

    private function extractDocxText($path)
    {
        if (!class_exists('ZipArchive')) {
            return '';
        }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return '';
        }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if ($xml === false) {
            return '';
        }
        $xml = preg_replace('/<w:tab[^>]*\/>/', "\t", $xml);
        $xml = preg_replace('/<\/w:p>/', "\n", $xml);
        $text = strip_tags($xml);
        return $this->normalizeWhitespace($text);
    }

    private function extractBinaryText($path)
    {
        $bin = @file_get_contents($path);
        if ($bin === false) {
            return '';
        }
        $text = preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', ' ', $bin);
        return $this->normalizeWhitespace($text);
    }

    private function normalizeWhitespace($text)
    {
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return sanitize_utf8_string(trim($text));
    }

    private function parseHeuristic($text)
    {
        $out = [];
        if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $text, $em)) {
            $out['email'] = strtolower($em[0]);
        }
        if (preg_match('/(?:\+91[\s-]?)?[6-9]\d{9}/', preg_replace('/\s+/', '', $text), $ph)) {
            $out['phone'] = preg_replace('/\D/', '', $ph[0]);
            if (strlen($out['phone']) > 10) {
                $out['phone'] = substr($out['phone'], -10);
            }
        }
        $lines = preg_split('/\r\n|\r|\n/', $text);
        foreach (array_slice($lines, 0, 8) as $line) {
            $line = trim($line);
            if (strlen($line) < 3 || strlen($line) > 60) {
                continue;
            }
            if (strpos($line, '@') !== false) {
                continue;
            }
            if (preg_match('/^(resume|curriculum|cv|profile)$/i', $line)) {
                continue;
            }
            if (preg_match('/^[A-Z][a-z]+(?:\s+[A-Z][a-z]+){0,3}$/', $line)) {
                $parts = preg_split('/\s+/', $line);
                $out['first_name'] = $parts[0];
                $out['last_name'] = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : '';
                break;
            }
        }
        if (preg_match('/(\d+(?:\.\d+)?)\s*(?:\+)?\s*years?\s+(?:of\s+)?experience/i', $text, $yx)) {
            $out['years_experience'] = (float) $yx[1];
        }
        if (preg_match('/(?:current(?:ly)?|present)\s*(?:at|@|,)?\s*([A-Z][A-Za-z0-9 &.,\-]{2,60})/i', $text, $co)) {
            $out['current_company'] = trim($co[1], " .,");
        }
        return $out;
    }

    public function applyToCandidate($candidateId, array $parsed)
    {
        $update = [];
        foreach (['first_name', 'last_name', 'email', 'phone', 'current_company'] as $k) {
            if (!empty($parsed[$k])) {
                $update[$k] = $parsed[$k];
            }
        }
        if (isset($parsed['years_experience'])) {
            $update['years_experience'] = $parsed['years_experience'];
        }
        if (!empty($update)) {
            $update['updated_at'] = date('Y-m-d H:i:s');
            db()->update('candidates', $update, 'id = :id', ['id' => $candidateId]);
        }
    }

    public function downloadResumeUrl($url, $destPath)
    {
        $url = trim($url);
        if ($url === '' || !preg_match('#^https?://#i', $url)) {
            throw new RuntimeException('Invalid resume URL.');
        }
        $ctx = stream_context_create([
            'http' => ['timeout' => 30, 'follow_location' => 1],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $data = @file_get_contents($url, false, $ctx);
        if ($data === false || strlen($data) < 100) {
            throw new RuntimeException('Could not download resume from URL.');
        }
        if (!file_put_contents($destPath, $data)) {
            throw new RuntimeException('Could not save downloaded resume.');
        }
    }
}
