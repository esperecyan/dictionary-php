<?php
namespace esperecyan\dictionary_php\internal;

use esperecyan\dictionary_php\exception\SyntaxException;

/**
 * 1つの辞書を表します。
 */
class Dictionary implements \Psr\Log\LoggerAwareInterface
{
    use \Psr\Log\LoggerAwareTrait;
    
    /** @var Word[] お題の一覧。 */
    protected $words = [];
    
    /** @var \SplFileInfo|null 画像・音声・動画ファイルのアーカイブ。 */
    protected $archive = null;
    
    /** @var string[] 画像・音声・動画ファイル名の一覧。 */
    protected $filenames = [];
    
    /**
     * @param \SplFileInfo|null $archive
     */
    public function __construct(\SplFileInfo $archive = null)
    {
        $this->archive = $archive;
        $this->filenames = $this->getFilenamesFromArchive();
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
     * @param \esperecyan\dictionary_php\internal\Word $word 2レコード目以降にメタフィールドが含まれるか否かのチェックは行いません。
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
