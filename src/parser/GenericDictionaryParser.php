<?php
namespace esperecyan\dictionary_php\parser;

use esperecyan\dictionary_php\{Dictionary, Parser, exception\SyntaxException};
use ScriptFUSION\Byte\ByteFormatter;
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
     * 画像ファイルの最大容量。
     * @var int
     */
    const MAX_IMAGE_SIZE = 2 ** 20;
    
    /**
     * 推奨される画像ファイルの最大容量。
     * @var int
     */
    const MAX_RECOMMENDED_IMAGE_SIZE = 100 * 2 ** 10;
    
    /**
     * 音声ファイルの最大容量。
     * @var int
     */
    const MAX_AUDIO_SIZE = 16 * 2 ** 20;
    
    /**
     * 推奨される音声ファイルの最大容量。
     * @var int
     */
    const MAX_RECOMMENDED_AUDIO_SIZE = 2 ** 20;
    
    /**
     * 動画ファイルの最大容量。
     * @var int
     */
    const MAX_VIDEO_SIZE = 16 * 2 ** 20;
    
    /**
     * 推奨される動画ファイルの最大容量。
     * @var int
     */
    const MAX_RECOMMENDED_VIDEO_SIZE = 2 ** 20;
    
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
    
    /** @var string[][] 有効な拡張子。 */
    const VALID_EXTENSIONS = [
        'image/png' => ['png'],
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/svg+xml' => ['svg'],
        'audio/mp4' => ['mp4', 'm4a'],
        'audio/mpeg' => ['mp3'],
        'video/mp4' => ['mp4'],
    ];

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
    protected function getTitleFromFilename(string $filename): string
    {
        return explode('.', $filename, 2)[0];
    }
    
    /**
     * 符号化方式をUTF-8に矯正します。
     * @param \SplFileObject $file
     * @throws SyntaxException 符号化方式の検出に失敗した場合。
     */
    protected function correctEncoding(\SplFileObject $file)
    {
        $binary = (new Parser())->getBinary($file);
        
        if (mb_check_encoding($binary, 'UTF-8')) {
            $fromEncoding = 'UTF-8';
        } else {
            $fromEncoding = mb_detect_encoding($binary, self::ENCODING_DETECTION_ORDER);
            if (!$fromEncoding) {
                throw new SyntaxException(_('CSVファイルの符号化方式 (文字コード) の検出に失敗しました。')
                    . _('CSVファイルの符号化方式 (文字コード) は UTF-8 でなければなりません。'));
            }
            $this->error(_('CSVファイルの符号化方式 (文字コード) は UTF-8 でなければなりません。'));
        }
        
        $file->ftruncate(0);
        $file->fwrite(mb_convert_encoding($binary, 'UTF-8', $fromEncoding));
        $file->rewind();
        return $file;
    }
    
    /**
     * スクリプト終了時に自動的に削除されるファイルを作成し、そのパスを返します。
     * @param \SplTempFileObject|null $file ファイルに書き込む文字列を格納したSplFileInfo。
     * @return string
     */
    protected function generateTempFile(\SplFileInfo $file = null): string
    {
        $path = tempnam(sys_get_temp_dir(), self::TEMP_FILE_OR_DIRECTORY_PREFIX);
        if ($file) {
            file_put_contents($path, (new Parser())->getBinary($file));
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
     * ファイルサイズをチェックします。
     * @param int $size
     * @param string $topLevelType
     * @param string $filename
     * @throws SyntaxException
     */
    protected function checkFileSize(int $size, string $topLevelType, string $filename)
    {
        $byteFormatter = new ByteFormatter();
        
        $message = sprintf(_('「%1$s」の容量は %2$s です。'), $filename, $byteFormatter->format($size));
        
        switch ($topLevelType) {
            case 'image':
                if ($size > self::MAX_IMAGE_SIZE) {
                    throw new SyntaxException(sprintf(
                        _('画像ファイルの容量は %s 以下にしてください。'),
                        $byteFormatter->format(self::MAX_IMAGE_SIZE)
                    ) . $message);
                } elseif ($size > self::MAX_RECOMMENDED_IMAGE_SIZE) {
                    $this->notice(sprintf(
                        _('画像ファイルの容量は %s 以下にすべきです。'),
                        $byteFormatter->format(self::MAX_RECOMMENDED_IMAGE_SIZE)
                    ) . $message);
                }
                break;
                
            case 'audio':
                if ($size > self::MAX_AUDIO_SIZE) {
                    throw new SyntaxException(sprintf(
                        _('音声ファイルの容量は %s 以下にしてください。'),
                        $byteFormatter->format(self::MAX_AUDIO_SIZE)
                    ) . $message);
                } elseif ($size > self::MAX_RECOMMENDED_AUDIO_SIZE) {
                    $this->notice(sprintf(
                        _('音声ファイルの容量は %s 以下にすべきです。'),
                        $byteFormatter->format(self::MAX_RECOMMENDED_AUDIO_SIZE)
                    ) . $message);
                }
                break;
                
            case 'video':
                if ($size > self::MAX_VIDEO_SIZE) {
                    throw new SyntaxException(sprintf(
                        _('動画ファイルの容量は %s 以下にしてください。'),
                        $byteFormatter->format(self::MAX_VIDEO_SIZE)
                    ) . $message);
                } elseif ($size > self::MAX_RECOMMENDED_VIDEO_SIZE) {
                    $this->notice(sprintf(
                        _('動画ファイルの容量は %s 以下にすべきです。'),
                        $byteFormatter->format(self::MAX_RECOMMENDED_VIDEO_SIZE)
                    ) . $message);
                }
                break;
        }
    }
    
    /**
     * PHPがWindowsでコンパイルされていれば真を返します。
     * @return bool
     */
    protected function isWindows(): bool
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
        
        $this->correctEncoding($csv);
        
        $csv->setFlags(\SplFileObject::READ_AHEAD | \SplFileObject::SKIP_EMPTY | \SplFileObject::READ_CSV);
        
        if ($this->isWindows()) {
            $previousLocale = setlocale(LC_CTYPE, '0');
            setlocale(LC_CTYPE, '.1252');
        }
        
        try {
            $first = true;
            foreach ($csv as $i => $fields) {
                if (is_null($fields[0])) {
                    throw new SyntaxException(_('汎用辞書はCSVファイルかZIPファイルでなければなりません。')
                        . _('壊れたCSVファイル (ダブルクォートでエスケープされていないダブルクォートを含む等) の可能性があります。'));
                }
                
                // 改行をLFに統一し、改行以外のC0制御文字を取り除く
                foreach ($fields as &$field) {
                    $field = preg_replace(
                        '/[\\x00-\\x09\\x11\\x7F]+/u',
                        '',
                        strtr($field, ["\r\n" => "\n", "\r" => "\n", "\n" => "\n"])
                    );
                }

                if ($i === 0) {
                    if (is_null($header)) {
                        $header = in_array('text', $fields);
                    }
                    if ($header) {
                        $fieldNames = $fields;
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
        $filenameValidator = new \esperecyan\dictionary_php\validator\FileLocationValidator();
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

        foreach ($files as $filename => $file) {
            if ($filename === 'dictionary.csv') {
                if (!in_array($finfo->file($file), ['text/plain', 'text/csv'])) {
                    throw new SyntaxException(_('「dictionary.csv」は通常のテキストファイルとして認識できません。'));
                }
                $csvFile = $file;
                continue;
            }
            
            if (!$filenameValidator->validateArchivedFilename($filename)) {
                throw new SyntaxException(sprintf(_('「%s」は妥当なファイル名ではありません。'), $filename));
            }

            $type = $finfo->file($file);

            if (empty(self::VALID_EXTENSIONS[$type])) {
                throw new SyntaxException(sprintf(_('「%s」は妥当な画像、音声、動画ファイルではありません。'), $filename));
            }

            if ($type === 'video/mp4' && $file->getExtension() === 'm4a') {
                $type = 'audio/mp4';
            } elseif (!in_array(
                $file->getExtension(),
                $type === 'video/mp4'
                    ? array_merge(self::VALID_EXTENSIONS['audio/mp4'], self::VALID_EXTENSIONS['video/mp4'])
                    : self::VALID_EXTENSIONS[$type]
            )) {
                throw new SyntaxException(sprintf(_('「%s」の拡張子は次のいずれかにしなければなりません:'), $filename)
                    . ' ' . implode(', ', self::VALID_EXTENSIONS[$type]));
            }

            $topLevelType = explode('/', $type)[0];

            $this->checkFileSize($file->getSize(), $topLevelType, $filename);

            if ($topLevelType === 'image') {
                $validator = new \esperecyan\dictionary_php\validator\ImageValidator($type, $filename);
                $validator->setLogger($this->logger);
                $file = $file->openFile();
                $binary = (new Parser())->getBinary($file);
                $file->ftruncate(0);
                $file->fwrite($validator->correct($binary));
            }
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
     * @param bool|null $header ヘッダ行が存在すれば真、存在しなければ偽、不明ならnull。
     * @throws SyntaxException
     * @return Dictionary
     */
    public function parse(
        \SplFileInfo $file,
        string $filename = null,
        string $title = null,
        bool $header = null
    ): Dictionary {
        if ($file instanceof \SplTempFileObject) {
            $binary = (new Parser())->getBinary($file);
        }
        
        $byteFormatter = new ByteFormatter();
        $fileSize = isset($binary) ? strlen(bin2hex($binary)) / 2 : $file->getSize();
        if ($fileSize > self::MAX_COMPRESSED_ARCHIVE_SIZE) {
            throw new SyntaxException(sprintf(
                _('ファイルサイズは %1$s 以下にしてください: 現在 %2$s'),
                $byteFormatter->format(self::MAX_COMPRESSED_ARCHIVE_SIZE),
                $byteFormatter->format($fileSize)
            ));
        } elseif ($fileSize > self::MAX_RECOMMENDED_COMPRESSED_ARCHIVE_SIZE) {
            $this->notice(sprintf(
                _('ファイルサイズは %1$s 以下にすべきです: 現在 %2$s'),
                $byteFormatter->format(self::MAX_RECOMMENDED_COMPRESSED_ARCHIVE_SIZE),
                $byteFormatter->format($fileSize)
            ));
        }
        
        $finfo = new Finfo(FILEINFO_MIME_TYPE);
        $type = isset($binary) ? $finfo->buffer($binary) : $finfo->file($file->getRealPath());
        switch ($type) {
            case 'application/zip':
                $header = true;
                $csvFile = $this->parseArchive($file);
                $csv = new \SplFileInfo($this->generateTempFile($csvFile));
                $files = new \FilesystemIterator($csvFile->getPath());
                unlink($csvFile);
                break;
            
            case 'text/csv':
            case 'text/plain':
                $csv = $file;
                break;
                
            default:
                throw new SyntaxException(_('汎用辞書はCSVファイルかZIPファイルでなければなりません。'));
        }
        
        $dictionary = new Dictionary($files ?? null);
        $dictionary->setLogger($this->logger);
        $this->parseCSVFile($dictionary, $csv, $header);
        
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
