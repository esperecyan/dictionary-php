<?php
namespace esperecyan\dictionary_api\fileinfo;

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
        return $info;
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
