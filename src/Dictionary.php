<?php
namespace esperecyan\dictionary_php;

use esperecyan\url\URLSearchParams;
use esperecyan\dictionary_php\exception\SyntaxException;

/**
 * 1つの辞書を表します。
 */
class Dictionary implements \Psr\Log\LoggerAwareInterface
{
    use \Psr\Log\LoggerAwareTrait {
        setLogger as traitSetLogger;
    }
    
    /** @var (string|string[]|float|URLSearchParams)[][][] お題の一覧。 */
    protected $words = [];
    
    /** @var (string|string[])[] メタフィールドの一覧。 */
    protected $metadata = [];
    
    /** @var \FilesystemIterator|null 画像・音声・動画ファイルのアーカイブを展開したファイルの一覧。 */
    protected $files = null;
    
    /** @var validator\WordValidator */
    protected $validator = null;
    
    /**
     * @param \FilesystemIterator|null $files
     */
    public function __construct(\FilesystemIterator $files = null)
    {
        $this->files = $files;
        if ($this->files) {
            $files->setFlags(\FilesystemIterator::KEY_AS_FILENAME
                | \FilesystemIterator::CURRENT_AS_FILEINFO
                | \FilesystemIterator::SKIP_DOTS);
            $filenames = array_keys(iterator_to_array($files));
        }
        $this->validator = new validator\WordValidator($filenames ?? []);
    }
    
    public function setLogger(\Psr\Log\LoggerInterface $logger)
    {
        $this->traitSetLogger($logger);
        $this->validator->setLogger($this->logger);
    }
    
    /**
     * お題の一覧を取得します。
     * @see https://github.com/esperecyan/dictionary-php#stringstringfloaturlsearchparams-esperecyandictionary_phpdictionarygetjsonable
     * @return (string|string[]|float|URLSearchParams)[][][]
     */
    public function getJsonable(): array
    {
        return $this->words;
    }
    
    /**
     * メタフィールドを設定します。
     * @param (string|string[])[]
     */
    public function setMetadata(array $metadata)
    {
        $this->metadata = $this->validator->parseMetadata(array_filter($metadata, function ($field): bool {
            // 文字列型以外の (明らかに構文解析済みである) フィールドは構文解析器にかけない
            return is_string($field);
        })) + $metadata;
    }
    
    /**
     * メタフィールドの一覧を取得します。
     * @see https://github.com/esperecyan/dictionary-php#stringstring-esperecyandictionary_phpdictionarygetmetadata
     * @return (string|string[])[]
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }
    
    /**
     * 辞書に同梱されるファイルを返します。
     * @return \FilesystemIterator|null
     */
    public function getFiles()
    {
        return $this->files;
    }
    
    /**
     * キーにフィールド名、値に同名フィールド値の配列を持つ配列から、お題を追加します。
     * @param string[][] $word メタフィールドが含まれるか否かのチェックは行いません。
     */
    public function addWord(array $word)
    {
        $this->words[] = $this->validator->parse($word);
    }
    
    /**
     * 辞書のタイトルを取得します。
     * @return string タイトルが存在しない場合は空文字列。
     */
    public function getTitle(): string
    {
        return $this->metadata['@title'] ?? '';
    }
}
