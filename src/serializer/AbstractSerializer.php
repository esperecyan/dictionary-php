<?php
namespace esperecyan\dictionary_php\serializer;

use esperecyan\dictionary_php\Dictionary;
use esperecyan\dictionary_php\validator\FileLocationValidator;

abstract class AbstractSerializer implements \Psr\Log\LoggerAwareInterface
{
    use \Psr\Log\LoggerAwareTrait;
    use \esperecyan\dictionary_php\internal\ArchiveGenerator;
    
    /** @var string 辞書のタイトルが存在しなかった場合の拡張子を除くファイル名。 */
    const DEFAULT_FILENAME = 'dictionary';
    
    /**
     * ファイル名を取得します。
     * @param Dictionary $dictionary
     * @param string $extension ピリオドを除く拡張子。
     */
    protected function getFilename(Dictionary $dictionary, string $extension)
    {
        $title = $dictionary->getTitle();
        return ($title !== ''
            ? (new FileLocationValidator())->convertToValidFilenameWithoutExtension($title)
            : self::DEFAULT_FILENAME) . ".$extension";
    }
    
    /**
     * 辞書を直列化したデータを返します。
     * @param Dictionary
     */
    abstract public function serialize(Dictionary $dictionary): array;
}
