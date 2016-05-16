<?php
namespace esperecyan\dictionary_php\serializer;

use esperecyan\dictionary_php\validator\FileLocationValidator;

abstract class AbstractSerializer
{
    /** @var string 辞書のタイトルが存在しなかった場合の拡張子を除くファイル名。 */
    const DEFAULT_FILENAME = 'dictionary';
    
    /**
     * filenameパラメータをともなうcontent-dispositionヘッダを送信します。
     * @param \esperecyan\dictionary_php\internal\Dictionary $dictionary
     * @param string $extension ピリオドを除く拡張子。
     */
    protected function setFilenameParameter(
        \esperecyan\dictionary_php\internal\Dictionary $dictionary,
        string $extension
    ) {
        $title = $dictionary->getTitle();
        header('content-disposition: attachment; filename*=utf-8\'\'' . rawurlencode(
            $title !== ''
                ? (new FileLocationValidator())->convertToValidFilenameWithoutExtension($title)
                : self::DEFAULT_FILENAME
        ) . ".$extension");
    }
    
    /**
     * mb_output_handlerが利用されている場合に、出力される符号化方式を設定します。
     * @param string $encoding mbstringで指定可能な符号化方式を表す文字列。大文字小文字を区別しません。
     * @see http://jp2.php.net/manual/ja/mbstring.supported-encodings.php
     */
    protected function setOutputEncoding($encoding = 'utf-8')
    {
        if (mb_strtolower(mb_http_output()) !== mb_strtolower($encoding)
            && in_array('mb_output_handler', ob_list_handlers())) {
            mb_http_output($encoding);
        }
    }
    
    /**
     * 辞書を直列化して出力します。
     * @param \esperecyan\dictionary_php\internal\Dictionary
     */
    abstract public function response(\esperecyan\dictionary_php\internal\Dictionary $dictionary);
}
