<?php
namespace esperecyan\dictionary_php\serializer;

use esperecyan\dictionary_php\{Dictionary, exception\SyntaxException};
use esperecyan\dictionary_php\parser\GenericDictionaryParser;

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
        foreach ($dictionary->getJsonable() as $word) {
            foreach ($word as $fieldName => $fields) {
                $fieldLengths[$fieldName] = max(count($fields), $fieldLengths[$fieldName] ?? 0);
            }
        }
        
        if (empty($fieldLengths)) {
            throw new \BadMethodCallException('空の辞書です。');
        }
        
        $fieldLengths += array_fill_keys(array_keys($dictionary->getMetadata()), 1);
        
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
     * 一つのお題を表す配列を、CSVのレコードに変換します。
     * @param (string|string[]|float|URLSearchParams)[][] $word
     * @param string[] $fieldNames
     * @param (string|string[])[] $metadata
     * @return string[]
     */
    protected function convertWordToRecord(array $word, array $fieldNames, array $metadata): array
    {
        $output = [];
        foreach ($fieldNames as $fieldName) {
            $field = isset($word[$fieldName][0])
                ? array_shift($word[$fieldName])
                : (isset($metadata[$fieldName]) ? $metadata[$fieldName] : '');
            if (is_array($field)) {
                $field = $field['lml'];
            }
            $output[] = $field;
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
        foreach ($dictionary->getJsonable() as $i => $word) {
            $this->putCSVRecord(
                $csv,
                $this->convertWordToRecord($word, $fieldNames, $i === 0 ? $dictionary->getMetadata() : [])
            );
        }
        $csv->rewind();
        return $csv;
    }
    
    /**
     * @param Dictionary $dictionary
     * @throws SyntaxException ZIPファイルについて、CSVファイルの追加により、許容される容量を超過したとき。
     * @return string[]
     */
    public function serialize(Dictionary $dictionary): array
    {
        $csv = (new \esperecyan\dictionary_php\Parser())->getBinary($this->getAsCSVFile($dictionary));
        $files = $dictionary->getFiles();
        if ($files) {
            $archive = $this->generateArchive();
            foreach ($files as $file) {
                $archive->addFile($file, $file->getFilename());
            }
            $archive->addFromString('dictionary.csv', $csv);
            $archiveFileInfo = new \SplFileInfo($archive->filename);
            $archive->close();
            
            if ($archiveFileInfo->getSize() > GenericDictionaryParser::MAX_COMPRESSED_ARCHIVE_SIZE) {
                throw new SyntaxException(
                    sprintf(_('出力される圧縮ファイルの容量が %s を超えました。'), GenericDictionaryParser::MAX_COMPRESSED_ARCHIVE_SIZE)
                );
            }
            
            return [
                'bytes' => (new \esperecyan\dictionary_php\Parser)->getBinary($archiveFileInfo),
                'type' => 'application/zip',
                'name' => $this->getFilename($dictionary, 'zip'),
            ];
        } else {
            return [
                'bytes' => $csv,
                'type' => 'text/csv; charset=UTF-8; header=present',
                'name' => $this->getFilename($dictionary, 'csv'),
            ];
        }
    }
}
