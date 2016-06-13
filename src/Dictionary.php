<?php
namespace esperecyan\dictionary_php;

/**
 * 1つの辞書を表します。
 */
class Dictionary extends log\AbstractLoggerAware
{
    /** @var (string|string[]|float)[][][] お題の一覧。 */
    protected $words = [];
    
    /** @var (string|string[])[] メタフィールドの一覧。 */
    protected $metadata = [];
    
    /** @var \FilesystemIterator|null 画像・音声・動画ファイルのアーカイブを展開したファイルの一覧。 */
    protected $files = null;
    
    /** @var string[] 画像・音声・動画ファイルのアーカイブを展開したファイル名の一覧。 */
    protected $filenames = [];
    
    /** @var validator\WordValidator */
    protected $validator = null;
    
    /**
     * @param \FilesystemIterator|string[] $files
     */
    public function __construct($files = [])
    {
        parent::__construct();
        
        if ($files) {
            if (is_array($files)) {
                $this->filenames = $files;
            } else {
                $this->files = $files;
                if ($this->files) {
                    $files->setFlags(\FilesystemIterator::KEY_AS_FILENAME
                        | \FilesystemIterator::CURRENT_AS_FILEINFO
                        | \FilesystemIterator::SKIP_DOTS);
                    $this->filenames = array_keys(iterator_to_array($files));
                }
            }
        }
        $this->validator = new validator\WordValidator($this->filenames);
    }
    
    public function setLogger(\Psr\Log\LoggerInterface $logger)
    {
        parent::setLogger($logger);
        if ($this->validator) {
            $this->validator->setLogger($this->logger);
        }
    }
    
    /**
     * お題の一覧を取得します。
     * @see https://github.com/esperecyan/dictionary-php#stringstringfloat-esperecyandictionary_phpdictionarygetwords
     * @return (string|string[]|float)[][][]
     */
    public function getWords(): array
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
     * 辞書に同梱されるファイル名を返します。
     * @internal
     * @return string[]
     */
    public function getFilenames(): array
    {
        return $this->filenames;
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
