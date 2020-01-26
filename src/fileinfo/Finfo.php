<?php
namespace esperecyan\dictionary_php\fileinfo;

use esperecyan\dictionary_php\parser\GenericDictionaryParser;

/**
 * Fileinfo拡張モジュールで「audio/mp4」を「video/mp4」と区別できるようにします。
 */
class Finfo extends \finfo
{
    protected function correctMP4MimeType(): string
    {
        $caller = debug_backtrace(0, 2)[1];
        $info = call_user_func_array([$this, "parent::$caller[function]"], $caller['args']);
        $file = $caller['args'][0];
        return $info === 'video/mp4'
            && (new \getID3())->analyze(
                $caller['function'] === 'buffer' ? (new GenericDictionaryParser())->generateTempFile($file) : $file
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
        return $this->correctMP4MimeType();
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
        return $this->correctMP4MimeType();
    }
}
