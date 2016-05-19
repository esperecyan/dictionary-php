<?php
namespace esperecyan\dictionary_php;

use esperecyan\url\URLSearchParams;
use esperecyan\html_filter\Filter as HTMLFilter;
use League\CommonMark\CommonMarkConverter;
use esperecyan\dictionary_php\{exception\SyntaxException, internal\Word};

/**
 * 1つの辞書を表します。
 */
class Dictionary implements \Psr\Log\LoggerAwareInterface
{
    use \Psr\Log\LoggerAwareTrait;
    
    /** @var int generateTempDirectory() で作成するランダムなディレクトリ名の長さ。  */
    const TEMP_DIRECTORY_NAME_LENGTH = 32;
    
    /** @var Word[] お題の一覧。 */
    protected $words = [];
    
    /** @var \SplFileInfo|null 画像・音声・動画ファイルのアーカイブ。 */
    protected $archive = null;
    
    /** @var string[] 画像・音声・動画ファイル名の一覧。 */
    protected $filenames = [];
    
    /** @var \FilesystemIterator|null 画像・音声・動画ファイルのアーカイブを展開したファイルの一覧。 */
    protected $files = null;
    
    /**
     * @param \SplFileInfo|null $archive
     */
    public function __construct(\SplFileInfo $archive = null)
    {
        $this->archive = $archive;
        $this->filenames = $this->getFilenamesFromArchive();
    }
    
    /**
     * お題の一覧を取得します。
     * @see https://github.com/esperecyan/dictionary-php#stringstringfloaturlsearchparams-esperecyandictionary_phpdictionarygetjsonable
     * @return (string|string[]|float|URLSearchParams)[][][]
     */
    public function getJsonable(): array
    {
        $words = [];
        foreach ($this->words as $word) {
            $fieldsAsMultiDimensionalArray = [];
            foreach ($word->getFieldsAsMultiDimensionalArray() as $fieldName => $fields) {
                if ($fieldName[0] === '@') {
                    continue;
                }
                foreach ($fields as &$field) {
                    switch ($fieldName) {
                        case 'image-source':
                        case 'audio-source':
                        case 'video-source':
                        case 'description':
                            $field = [
                                'lml' => $field,
                                'html' => (new HTMLFilter())->filter((new CommonMarkConverter())->convertToHtml($field)),
                            ];
                            break;
                        case 'weight':
                            $field = (float)$field;
                            break;
                        case 'specifics':
                            $field = new URLSearchParams($field);
                            break;
                    }
                }
                $fieldsAsMultiDimensionalArray[$fieldName] = $fields;
            }
            $words[] = $fieldsAsMultiDimensionalArray;
        }
        return $words;
    }
    
    /**
     * メタフィールドの一覧を取得します。
     * @see https://github.com/esperecyan/dictionary-php#stringstring-esperecyandictionary_phpdictionarygetmetadata
     * @return (string|string[])[]
     */
    public function getMetadata(): array
    {
        $metadata = [];
        if (isset($this->words[0])) {
            foreach ($this->words[0]->getFieldsAsMultiDimensionalArray() as $fieldName => $fields) {
                if ($fieldName[0] !== '@') {
                    continue;
                }
                switch ($fieldName) {
                    case '@summary':
                        $field = [
                            'lml' => $fields[0],
                            'html' => (new HTMLFilter())->filter((new CommonMarkConverter())->convertToHtml($fields[0])),
                        ];
                        break;
                    default:
                        $field = $fields[0];
                }
                $metadata[$fieldName] = $field;
            }
        }
        return $metadata;
    }
    
    /**
     * 辞書に同梱されるファイルを返します。
     * @return \FilesystemIterator|null
     */
    public function getFiles()
    {
        if (!$this->files && $this->archive) {
            $tempDirectoryPath = $this->generateTempDirectory();
            $archive = new \ZipArchive();
            $archive->open($this->archive->getRealPath());
            $archive->extractTo($tempDirectoryPath);
            $archive->close();
            $this->files = new \FilesystemIterator($tempDirectoryPath);
        }
        return $this->files;
    }
    
    /**
     * スクリプト終了時に自動的に削除されるディレクトリを作成し、そのパスを返します。
     * @return string
     */
    protected function generateTempDirectory(): string
    {
        $path = sys_get_temp_dir() . '/' . bin2hex(random_bytes(self::TEMP_DIRECTORY_NAME_LENGTH));
        
        mkdir($path);
        
        register_shutdown_function(function () use ($path) {
            if (!file_exists($path)) {
                return;
            }

            foreach (new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            ) as $file) {
                if ($file->isDir()) {
                    rmdir($file->getPathname());
                } else {
                    unlink($file->getPathname());
                }
            }
            rmdir($path);
        });
        
        return $path;
    }
    
    /**
     * 辞書に結び付けられたアーカイブからファイル名の一覧を取得します。
     * @return string[]
     */
    protected function getFilenamesFromArchive():array
    {
        if ($this->archive) {
            $archive = new \ZipArchive();
            $archive->open($this->archive->getRealPath());
            for ($i = 0, $l = $archive->numFiles; $i < $l; $i++) {
                $name = $archive->getNameIndex($i);
                if (is_string($name)) {
                    $filenames[] = $name;
                }
            }
            $archive->close();
        }
        return $filenames ?? [];
    }

    /**
     * 辞書に結び付けられた画像・音声・動画ファイルのアーカイブを返します。
     * @return \SplFileInfo|null
     */
    public function getArchive()
    {
        return $this->archive;
    }
    
    /**
     * 1つ目のお題レコードに対して、メタフィールドを設定します。
     * @param string[][] $metaFieldsAsMultiDimensionalArray 同名のフィールドがすでにある場合、既存の同名フィールドはすべて削除されます。メタフィールドか否かの確認は行いません。
     * @throws \BadMethodCallException お題が1つも追加されていない場合。
     */
    public function setMetaFields(array $metaFieldsAsMultiDimensionalArray)
    {
        if ($this->words) {
            $word = new Word($this->filenames);
            $word->setFieldsAsMultiDimensionalArray(
                array_merge($this->words[0]->getFieldsAsMultiDimensionalArray(), $metaFieldsAsMultiDimensionalArray)
            );
            array_splice($this->words, 0, 1, [$word]);
        } else {
            throw new \BadMethodCallException();
        }
    }
    
    /**
     * お題を追加します。
     * @param Word $word 2レコード目以降にメタフィールドが含まれるか否かのチェックは行いません。
     */
    public function addWord(Word $word)
    {
        $this->words[] = $word;
    }
    
    /**
     * キーにフィールド名、値に同名フィールド値の配列を持つ配列から、お題を追加します。
     * @param array $fieldsAsMultiDimensionalArray
     * @return Word
     */
    public function addWordAsMultiDimensionalArray(array $fieldsAsMultiDimensionalArray): Word
    {
        $word = new Word($this->filenames);
        if ($this->logger) {
            $word->setLogger($this->logger);
        }
        $word->setFieldsAsMultiDimensionalArray(array_filter($fieldsAsMultiDimensionalArray, function ($fieldName) {
            $valid = true;
            if ($this->isMetaField($fieldName) && $this->words) {
                // メタフィールド、かつ2レコード目以降なら
                if ($this->logger) {
                    $this->logger->error(sprintf(_('メタフィールド%sの内容は、最初のレコードにのみ記述可能です。'), $fieldName));
                }
                $valid = false;
            }
            return $valid;
        }, ARRAY_FILTER_USE_KEY));
        $this->addWord($word);
        return $word;
    }
    
    /**
     * メタフィールドであれば真を返します。
     * @param string $fieldName
     * @throws SyntaxException 空文字列であったとき。
     * @return bool
     */
    protected function isMetaField(string $fieldName): bool
    {
        if ($fieldName === '') {
            throw new SyntaxException(_('フィールド名は空文字列であってはなりません。'));
        }
        if (preg_match('/[\\p{C}\\p{Z}]/u', $fieldName) === 1 && $this->logger) {
            $this->logger->notice(_('フィールド名に制御文字、または空白文字が含まれています。'));
        }
        return $fieldName[0] === '@';
    }
    
    /**
     * 辞書のタイトルを取得します。
     * @return string タイトルが存在しない場合は空文字列。
     */
    public function getTitle(): string
    {
        if (isset($this->words[0])) {
            $fieldsAsMultiDimensionalArray = $this->words[0]->getFieldsAsMultiDimensionalArray();
            if (isset($fieldsAsMultiDimensionalArray['@title'][0])) {
                $title = $fieldsAsMultiDimensionalArray['@title'][0];
            }
        }
        return $title ?? '';
    }
    
    /**
     * お題の一覧を取得します。
     * @return Word[]
     */
    public function getWords(): array
    {
        return $this->words;
    }
}
