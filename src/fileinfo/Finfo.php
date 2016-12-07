<?php
namespace esperecyan\dictionary_php\fileinfo;

use esperecyan\dictionary_php\parser\GenericDictionaryParser;

/**
 * Fileinfo拡張モジュールに "`\r\n`\r\n" のような文字列を与えた際に発生するエラー「Notice: Array to string conversion」を抑制します。
 */
class Finfo extends \finfo
{
    protected function suppressErrorAndCallMethod(): string
    {
        $caller = debug_backtrace(0, 2)[1];
        set_error_handler(function (int $severity, string $message) {
            if ($message !== 'Array to string conversion') {
                return false;
            }
        }, E_NOTICE);
        $info = call_user_func_array([$this, "parent::$caller[function]"], $caller['args']);
        restore_error_handler();
        return $this->correctMP4MimeType($info, $caller['args'][0], $caller['function'] === 'buffer');
    }
    
    protected function correctMP4MimeType(string $info, string $file, bool $binary): string
    {
        return $info === 'video/mp4'
            && (new \getID3())->analyze(
                $binary ? (new GenericDictionaryParser())->generateTempFile($file) : $file
            )['mime_type'] === 'audio/mp4'
            ? 'audio/mp4'
            : $info;
    }
    
    /**
     * Return information about a file.
     * @param string $string
     * @param int|null $options
     * @param resource|null $context
     * @return string
     */
    public function buffer($string, $options = null, $context = null): string
    {
        return $this->suppressErrorAndCallMethod();
    }
    
    /**
     * Return information about a file.
     * @param string $filename
     * @param int|null $options
     * @param resource|null $context
     * @return string
     */
    public function file($filename, $options = null, $context = null): string
    {
        return $this->suppressErrorAndCallMethod();
    }
}
