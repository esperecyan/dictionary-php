<?php
namespace esperecyan\dictionary_api\serializer;

use esperecyan\dictionary_api\internal\Dictionary;
use esperecyan\dictionary_api\parser\GenericDictionaryParser;

class GenericDictionarySerializer extends AbstractSerializer
{
    /** @var 既知の列の規定の並び方。 */
    const COLUMN_POSITIONS = ['text', 'image', 'image-source', 'audio', 'audio-source', 'video', 'video-source',
        'answer', 'description', 'weight', 'specifics', 'question', 'option', 'type', '@title', '@summary', '@regard'];
    
    /**
     * 列の位置の比較に使うインデックスを返します。
     * @param string $fieldName
     * @return int
     */
    protected function getColumnPosition(string $fieldName): int
    {
        $position = array_search($fieldName, self::COLUMN_POSITIONS, true);
        return is_int($position) ? $position : PHP_INT_MAX;
    }
    
    /**
     * ヘッダ行に用いるフィールド名の一覧を生成します。
     * @param Dictionary $dictionary
     * @throws \BadMethodCallException 辞書にお題が1つもなかった場合。
     * @return string[]
     */
    protected function getFieldNames(Dictionary $dictionary): array
    {
        // 各フィールド名について、全レコード中の最大数をそれぞれ取得
        foreach ($dictionary->getWords() as $word) {
            foreach ($word->getFieldsAsMultiDimensionalArray() as $fieldName => $fields) {
                $fieldLengths[$fieldName] = max(count($fields), $fieldLengths[$fieldName] ?? 0);
            }
        }
        
        if (empty($fieldLengths)) {
            throw new \BadMethodCallException('空の辞書です。');
        }
        
        uksort($fieldLengths, function (string $a, string $b): int {
            return $this->getColumnPosition($a) <=> $this->getColumnPosition($b);
        });
        
        // ヘッダを生成
        return array_merge(...array_map(function (string $fieldName, int $length): array {
            return array_fill(0, $length, $fieldName);
        }, array_keys($fieldLengths), $fieldLengths));
    }
    
    /**
     * フィールドの配列をCSVの行として書き出します。
     * @param \SplFileObject $file
     * @param string[] $fields
     */
    protected function putCSVRecord(\SplFileObject $file, array $fields)
    {
        $file->fputcsv(array_map(function (string $field): string {
            return preg_replace(
                '/[\\x00-\\x09\\x11\\x7F]+/u',
                '',
                strtr($field, ["\r\n" => "\r\n", "\r" => "\r\n", "\n" => "\r\n"])
            );
        }, $fields));
        $file->fseek(-1, SEEK_CUR);
        $file->fwrite("\r\n");
    }

    /**
     * WordをCSVのレコードに変換します。
     * @param \esperecyan\dictionary_api\internal\Word $word
     * @param string[] $fieldNames
     * @return string[]
     */
    protected function convertWordToRecord(\esperecyan\dictionary_api\internal\Word $word, array $fieldNames): array
    {
        $output = [];
        $fieldsAsMultiDimensionalArray = $word->getFieldsAsMultiDimensionalArray();
        foreach ($fieldNames as $fieldName) {
            $output[] = isset($fieldsAsMultiDimensionalArray[$fieldName][0])
                ? array_shift($fieldsAsMultiDimensionalArray[$fieldName])
                : '';
        }
        return $output;
    }
    
    /**
     * 辞書をCSVファイルとして取得します。
     * @param Dictionary $dictionary
     * @return \SplTempFileObject
     */
    protected function getAsCSVFile(Dictionary $dictionary): \SplTempFileObject
    {
        $fieldNames = $this->getFieldNames($dictionary);
        $csv = new \SplTempFileObject();
        $this->putCSVRecord($csv, $fieldNames);
        foreach ($dictionary->getWords() as $word) {
            $this->putCSVRecord($csv, $this->convertWordToRecord($word, $fieldNames));
        }
        $csv->rewind();
        return $csv;
    }
    
    /**
     * content-typeヘッダを出力して、辞書を書き出します。
     * @param Dictionary $dictionary
     * @throws SyntaxException ZIPファイルについて、CSVファイルの追加により、許容される容量を超過したとき。
     */
    public function response(\esperecyan\dictionary_api\internal\Dictionary $dictionary)
    {
        $csv = (new \esperecyan\dictionary_api\Parser())->getBinary($this->getAsCSVFile($dictionary));
        $archiveFileInfo = $dictionary->getArchive();
        if ($archiveFileInfo) {
            $archive = new \ZipArchive();
            $archive->open($archiveFileInfo->getRealPath());
            $archive->addFromString('dictionary.csv', $csv);
            $archive->close();
            
            if ($archiveFileInfo->getSize() > GenericDictionaryParser::MAX_COMPRESSED_ARCHIVE_SIZE) {
                throw new SyntaxException(
                    sprintf(_('出力される圧縮ファイルの容量が %s を超えました。'), GenericDictionaryParser::MAX_COMPRESSED_ARCHIVE_SIZE)
                );
            }
            
            header('content-type: application/zip');
            $this->setFilenameParameter($dictionary, 'zip');
            $archiveFileInfo->openFile()->fpassthru();
        } else {
            header('content-type: text/csv; charset=utf-8; header=present');
            $this->setFilenameParameter($dictionary, 'csv');
            $this->setOutputEncoding();
            echo $csv;
        }
    }
}
