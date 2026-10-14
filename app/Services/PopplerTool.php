<?php

/**
 * Run Poppler CLI tools from a project folder (no system PATH / Apache restart).
 *
 * Drop binaries under: collarup-clone/tools/poppler/bin/
 * Or set POPPLER_BIN_DIR in .env to that folder's absolute path.
 */
class PopplerTool
{
    private static $resolvedBinDir;

    public static function binDir()
    {
        if (self::$resolvedBinDir !== null) {
            return self::$resolvedBinDir;
        }

        $candidates = [];
        $fromEnv = trim((string) env('POPPLER_BIN_DIR', ''));
        if ($fromEnv !== '') {
            $candidates[] = $fromEnv;
        }
        $candidates[] = BASE_PATH . '/tools/poppler/bin';
        $candidates[] = BASE_PATH . '/tools/poppler/Library/bin';
        foreach (glob(BASE_PATH . '/poppler-*/Library/bin', GLOB_ONLYDIR) ?: [] as $bundled) {
            $candidates[] = $bundled;
        }

        foreach ($candidates as $dir) {
            $dir = self::normalizeDir($dir);
            if ($dir !== '' && is_dir($dir) && self::executablePath($dir, 'pdftotext') !== '') {
                self::$resolvedBinDir = $dir;

                return self::$resolvedBinDir;
            }
        }

        self::$resolvedBinDir = '';

        return '';
    }

    public static function isAvailable()
    {
        return self::binDir() !== '';
    }

    public static function hasSplitTools()
    {
        $dir = self::binDir();
        if ($dir === '') {
            return false;
        }

        return self::executablePath($dir, 'pdfseparate') !== ''
            && self::executablePath($dir, 'pdfunite') !== '';
    }

    /**
     * @return string|null stdout
     */
    public static function run($tool, $arguments)
    {
        if (!function_exists('shell_exec')) {
            return null;
        }

        $exe = self::executablePath(self::binDir(), $tool);
        if ($exe === '') {
            $exe = $tool;
            if (PHP_OS_FAMILY === 'Windows' && stripos($exe, '.exe') === false) {
                $exe .= '.exe';
            }
        }

        $stderr = PHP_OS_FAMILY === 'Windows' ? ' 2>NUL' : ' 2>/dev/null';

        return @shell_exec(escapeshellarg($exe) . ' ' . $arguments . $stderr);
    }

    public static function executablePath($dir, $name)
    {
        if ($dir === '') {
            return '';
        }
        $dir = self::normalizeDir($dir);
        $candidates = [$name];
        if (PHP_OS_FAMILY === 'Windows') {
            $candidates[] = $name . '.exe';
        }
        foreach ($candidates as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_file($path)) {
                return $path;
            }
        }

        return '';
    }

    private static function normalizeDir($dir)
    {
        $dir = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $dir);

        return rtrim($dir, DIRECTORY_SEPARATOR);
    }
}
