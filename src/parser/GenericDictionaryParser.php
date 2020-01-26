<?php
namespace esperecyan\dictionary_php\parser;

use esperecyan\dictionary_php\{Dictionary, Parser, exception\SyntaxException};
use esperecyan\dictionary_php\fileinfo\Finfo;

class GenericDictionaryParser extends AbstractParser
{
    /**
     * アーカイブ全体の圧縮後の最大容量。
     * @var int
     */
    const MAX_COMPRESSED_ARCHIVE_SIZE = 2 * 2 ** 30;
    
    /**
     * 推奨されるアーカイブ全体の圧縮後の最大容量。
     * @var int
     */
    const MAX_RECOMMENDED_COMPRESSED_ARCHIVE_SIZE = 512 * 2 ** 20;
    
    /**
     * アーカイブ中のファイル数の上限。
     * @var int
     */
    const MAX_FILES = 10000;
    
    /**
     * 推奨されるアーカイブ中のファイル数の上限。
     * @var int
     */
    const MAX_RECOMMENDED_FILES = 2000;
    
    /** @var int generateTempDirectory() で作成するランダムなディレクトリ名の長さ。  */
    const TEMP_DIRECTORY_NAME_LENGTH = 32;
    
    /** @var string generateTempFile()、generateTempDirectory() で作成するファイル名、ディレクトリ名の接頭辞。  */
    const TEMP_FILE_OR_DIRECTORY_PREFIX = 'php';
    
    /**
     * mbstring拡張モジュールにおいて推奨される符号化方式の検出順序。
     * @var string
     * @see http://qiita.com/shoyan/items/d9f7c014ba8003e19b57#comment-958a3830a0a450f69071
     */
    const ENCODING_DETECTION_ORDER = 'US-ASCII,ISO-2022-JP,UTF-8,eucJP-win,Windows-31J';
    
    /** @var bool|null */
    protected $header;
    
    /** @var string[] */
    protected $filenames;

    /**
     * @param bool|null $header ヘッダ行が存在すれば真、存在しなければ偽、不明ならnull。
     * @param string[] $filenames 画像・音声・動画ファイルのアーカイブを展開したファイル名の一覧。
     */
    public function __construct(bool $header = null, array $filenames = [])
    {
        parent::__construct();
        $this->header = $header;
        $this->filenames = $filenames;
    }

    /**
     * 配列をCSVレコード風の文字列に変換して返します。
     * @param string[] $fields
     * @return string
     */
    public function convertToCSVRecord(array $fields): string
    {
        $csv = new \SplTempFileObject();
        $csv->fputcsv($fields);
        $csv->rewind();
        return rtrim($csv->fgets(), "\r\n");
    }
    
    /**
     * CSVの一レコードを表す2つの配列によってお題を追加します。
     * @param Dictionary $dictionary
     * @param string[] $fieldNames
     * @param string[] $fields
     * @param bool $first ヘッダ行を除く最初のレコードであれば真。
     * @throws SyntaxException
     */
    protected function addRecord(Dictionary $dictionary, array $fieldNames, array $fields, bool $first)
    {
        if (!in_array('text', $fieldNames)) {
            throw new SyntaxException(
                sprintf(_('ヘッダ行「%s」にフィールド名「text」が存在しません'), $this->convertToCSVRecord($fieldNames))
            );
        }
        
        if (count($fields) > count($fieldNames)) {
            throw new SyntaxException(
                sprintf(_('「%s」のフィールド数は、ヘッダ行のフィールド名の数を超えています。'), $this->convertToCSVRecord($fields))
            );
        }
        
        foreach ($fields as $i => $field) {
            if ($field !== '') {
                if ($fieldNames[$i][0] === '@') {
                    if ($first) {
                        $metaFields[$fieldNames[$i]] = $field;
                    } else {
                        $this->logger->error(sprintf(_('メタフィールド%sの内容は、最初のレコードにのみ記述可能です。'), $fieldNames[$i]));
                    }
                } else {
                    $fieldsAsMultiDimensionalArray[$fieldNames[$i]][] = $field;
                }
            }
        }
        
        if (!isset($fieldsAsMultiDimensionalArray['text'][0])) {
            throw new SyntaxException(
                sprintf(_('「%s」にはtextフィールドが存在しません。'), $this->convertToCSVRecord($fields))
            );
        }
        
        $dictionary->addWord($fieldsAsMultiDimensionalArray);
        if (isset($metaFields)) {
            $dictionary->setMetadata($metaFields);
        }
    }
    
    /**
     * ファイル名から辞書のタイトルを取得します。
     * @param string $filename
     * @return string
     */
    public function getTitleFromFilename(string $filename): string
    {
        return explode('.', $filename, 2)[0];
    }
    
    /**
     * 符号化方式をUTF-8に矯正します。
     * @param string $binary
     * @throws SyntaxException 符号化方式の検出に失敗した場合。
     * @return string
     */
    public function correctEncoding(string $binary): string
    {
        if (mb_check_encoding($binary, 'UTF-8')) {
            $fromEncoding = 'UTF-8';
        } else {
            $fromEncoding = mb_detect_encoding($binary, self::ENCODING_DETECTION_ORDER);
            if (!$fromEncoding) {
                throw new SyntaxException(_('CSVファイルの符号化方式 (文字コード) の検出に失敗しました。')
                    . _('CSVファイルの符号化方式 (文字コード) は UTF-8 でなければなりません。'));
            }
            $this->logger->error(_('CSVファイルの符号化方式 (文字コード) は UTF-8 でなければなりません。'));
        }
        return mb_convert_encoding($binary, 'UTF-8', $fromEncoding);
    }
    
    /**
     * スクリプト終了時に自動的に削除されるファイルを作成し、そのパスを返します。
     * @param string|\SplFileInfo|null $file ファイルに書き込む文字列を格納したSplFileInfo。
     * @return string
     */
    public function generateTempFile($file = null): string
    {
        $path = tempnam(sys_get_temp_dir(), self::TEMP_FILE_OR_DIRECTORY_PREFIX);
        if ($file) {
            file_put_contents($path, $file instanceof \SplFileInfo ? (new Parser())->getBinary($file) : $file);
        }
        register_shutdown_function(function () use ($path) {
            if (file_exists($path)) {
                unlink($path);
            }
        });
        return $path;
    }
    
    /**
     * スクリプト終了時に自動的に削除されるディレクトリを作成し、そのパスを返します。
     * @return string
     */
    public function generateTempDirectory(): string
    {
        $path = sys_get_temp_dir() . '/'
            . self::TEMP_FILE_OR_DIRECTORY_PREFIX . bin2hex(random_bytes(self::TEMP_DIRECTORY_NAME_LENGTH));
        
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
     * PHPがWindowsでコンパイルされていれば真を返します。
     * @return bool
     */
    public function isWindows(): bool
    {
        return strpos(PHP_OS, 'WIN') === 0;
    }
    
    /**
     * CSVファイルからお題を取得し、Dictionaryクラスに追加します。
     * @param Dictionary $dictionary
     * @param \SplFileInfo $csv
     * @param bool|null $header
     * @throws SyntaxException CSVファイルが壊れている場合。
     */
    protected function parseCSVFile(Dictionary $dictionary, \SplFileInfo $csv, bool $header = null)
    {
        if (!($csv instanceof \SplFileObject)) {
            $csv = $csv->openFile();
        }
        
        $binary = $this->correctEncoding((new Parser())->getBinary($csv));
        $temp = new \SplTempFileObject();
        $temp->fwrite($binary);
        
        $temp->setFlags(\SplFileObject::READ_AHEAD | \SplFileObject::SKIP_EMPTY | \SplFileObject::READ_CSV);
        
        if ($this->isWindows()) {
            $previousLocale = setlocale(LC_CTYPE, '0');
            setlocale(LC_CTYPE, '.1252');
        }
        
        try {
            $first = true;
            foreach ($temp as $i => $fields) {
                if (is_null($fields[0])) {
                    throw new SyntaxException(_('汎用辞書はCSVファイルかZIPファイルでなければなりません。')
                        . _('壊れたCSVファイル (ダブルクォートでエスケープされていないダブルクォートを含む等) の可能性があります。'));
                }
                
                // 改行をLFに統一し、水平タブと改行以外のC0制御文字を取り除く
                foreach ($fields as &$field) {
                    $field = preg_replace(
                        '/[\\x00-\\x08\\x11\\x7F]+/u',
                        '',
                        strtr($field, ["\r\n" => "\n", "\r" => "\n", "\n" => "\n"])
                    );
                }

                if ($i === 0) {
                    if (is_null($header)) {
                        $header = in_array('text', $fields);
                    }
                    if ($header) {
                        $fieldNames = array_map(function (string $fieldName): string {
                            return str_replace("\t", '', $fieldName);
                        }, $fields);
                        continue;
                    }
                }

                $this->addRecord(
                    $dictionary,
                    $fieldNames ?? array_merge(['text'], array_fill(0, count($fields), 'answer')),
                    $fields,
                    $first
                );
                $first = false;
            }
        } catch (\Throwable $e) {
            if (isset($previousLocale)) {
                setlocale(LC_CTYPE, $previousLocale);
            }
            throw $e;
        }
        if (isset($previousLocale)) {
            setlocale(LC_CTYPE, $previousLocale);
        }
    }
    
    /**
     * ZIPアーカイブを解析し、ファイルの矯正を行います。
     * @param SplFileInfo $file
     * @throws SyntaxException
     * @throws \LogicException 権限エラーなどでZIPファイルを開けなかった場合、または書き込めなかった場合。
     * @return \SplFileObject CSVファイル。
     */
    protected function parseArchive(\SplFileInfo $file): \SplFileInfo
    {
        $archive = new \ZipArchive();
        $result = $archive->open(
            $file instanceof \SplTempFileObject ? $this->generateTempFile($file) : $file->getRealPath(),
            \ZipArchive::CHECKCONS
        );
        if ($result !== true) {
            switch ($result) {
                case \ZipArchive::ER_INCONS:
                case \ZipArchive::ER_NOZIP:
                    throw new SyntaxException(_('妥当なZIPファイルではありません。'));
                default:
                    throw new \LogicException("ZIPファイルの解析に失敗しました。エラーコード: $result");
            }
        }
        
        $tempDirectoryPath = $this->generateTempDirectory();
        $archive->extractTo($tempDirectoryPath);
        $archive->close();
        $files = new \FilesystemIterator($tempDirectoryPath, \FilesystemIterator::KEY_AS_FILENAME);

        $finfo = new Finfo(FILEINFO_MIME_TYPE);
        $validator = new \esperecyan\dictionary_php\Validator();
        $validator->setLogger($this->logger);
        foreach ($files as $filename => $file) {
            if ($filename === 'dictionary.csv') {
                if (!in_array($finfo->file($file), ['text/plain', 'text/csv'])) {
                    throw new SyntaxException(_('「dictionary.csv」は通常のテキストファイルとして認識できません。'));
                }
                $csvFile = $file;
                continue;
            }
            
            $validator->correct($file, $filename);
        }

        if (empty($csvFile)) {
            throw new SyntaxException(_('「dictionary.csv」が見つかりません。'));
        }
        
        return $csvFile;
    }

    /**
     * @param \SplFileInfo $file
     * @param string|null $filename
     * @param string|null $title
     * @throws SyntaxException
     * @return Dictionary
     */
    public function parse(\SplFileInfo $file, string $filename = null, string $title = null): Dictionary
    {
        if ($file instanceof \SplTempFileObject) {
            $binary = (new Parser())->getBinary($file);
        }
        
        $byteFormatter = new \ScriptFUSION\Byte\ByteFormatter();
        $fileSize = isset($binary) ? strlen(bin2hex($binary)) / 2 : $file->getSize();
        if ($fileSize > self::MAX_COMPRESSED_ARCHIVE_SIZE) {
            throw new SyntaxException(sprintf(
                _('ファイルサイズは %1$s 以下にしてください: 現在 %2$s'),
                $byteFormatter->format(self::MAX_COMPRESSED_ARCHIVE_SIZE),
                $byteFormatter->format($fileSize)
            ));
        } elseif ($fileSize > self::MAX_RECOMMENDED_COMPRESSED_ARCHIVE_SIZE) {
            $this->logger->warning(sprintf(
                _('ファイルサイズは %1$s 以下にすべきです: 現在 %2$s'),
                $byteFormatter->format(self::MAX_RECOMMENDED_COMPRESSED_ARCHIVE_SIZE),
                $byteFormatter->format($fileSize)
            ));
        }
        
        $finfo = new Finfo(FILEINFO_MIME_TYPE);
        $type = isset($binary) ? $finfo->buffer($binary) : $finfo->file($file->getRealPath());
        switch ($type) {
            case 'application/zip':
                $this->header = true;
                $csvFile = $this->parseArchive($file);
                $csv = new \SplFileInfo($this->generateTempFile($csvFile));
                $files = new \FilesystemIterator($csvFile->getPath(), \FilesystemIterator::SKIP_DOTS);
                unlink($csvFile);
                break;
            
            case 'text/csv':
            case 'text/plain':
                $csv = $file;
                break;
                
            default:
                throw new SyntaxException(_('汎用辞書はCSVファイルかZIPファイルでなければなりません。'));
        }
        
        $filesCount = (isset($files) ? iterator_count($files) : count($this->filenames)) /* CSVファイル分を加算 */ + 1;
        if ($filesCount > self::MAX_FILES) {
            throw new SyntaxException(sprintf(
                _('アーカイブ中のファイル数は %1$s 個以下にしてください: 現在 %2$s 個'),
                self::MAX_FILES,
                $filesCount
            ));
        } elseif ($filesCount > self::MAX_RECOMMENDED_FILES) {
            $this->logger->warning(sprintf(
                _('アーカイブ中のファイル数は %1$s 個以下にすべきです: 現在 %2$s 個'),
                self::MAX_RECOMMENDED_FILES,
                $filesCount
            ));
        }
        
        $dictionary = new Dictionary($files ?? $this->filenames);
        $dictionary->setLogger($this->logger);
        $this->parseCSVFile($dictionary, $csv, $this->header);
        
        if (!$dictionary->getWords()) {
            throw new SyntaxException(_('CSVファイルが空です。'));
        }
        
        $metadata = $dictionary->getMetadata();
        if (!isset($metadata['@title'])) {
            if (!is_null($title)) {
                $metadata['@title'] = $title;
            } elseif (!is_null($filename)) {
                $titleFromFilename = $this->getTitleFromFilename($filename);
                if ($titleFromFilename !== '') {
                    $metadata['@title'] = $titleFromFilename;
                }
            }
            $dictionary->setMetadata($metadata);
        }
        
        return $dictionary;
    }
}
