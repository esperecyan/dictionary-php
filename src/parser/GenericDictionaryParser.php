<?php
namespace esperecyan\dictionary_php\parser;

use esperecyan\dictionary_php\{Dictionary, internal\Word, exception\SyntaxException};
use ScriptFUSION\Byte\ByteFormatter;
use esperecyan\dictionary_php\fileinfo\Finfo;

class GenericDictionaryParser extends AbstractParser implements
    \Psr\Log\LoggerAwareInterface,
    \Psr\Log\LoggerInterface
{
    use \Psr\Log\LoggerAwareTrait;
    use \Psr\Log\LoggerTrait;
    
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
    
    /** @var (string|array)[] */
    protected $logs = [];

    public function log($level, $message, array $context = [])
    {
        $this->logs[] = [
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];
        
        if ($this->logger) {
            $this->logger->log($level, $message, $context);
        }
    }

    /**
     * 配列をCSVレコード風の文字列に変換して返します。
     * @param string[] $fields
     * @return string
     */
    protected function convertToCSVRecord($fields): string
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
     * @throws SyntaxException
     * @return Word
     */
    protected function addRecord(Dictionary $dictionary, array $fieldNames, array $fields): Word
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
                $fieldsAsMultiDimensionalArray[$fieldNames[$i]][] = $field;
            }
        }
        
        if (!isset($fieldsAsMultiDimensionalArray['text'][0])) {
            throw new SyntaxException(
                sprintf(_('「%s」にはtextフィールドが存在しません。'), $this->convertToCSVRecord($fields))
            );
        }
        
        return $dictionary->addWordAsMultiDimensionalArray($fieldsAsMultiDimensionalArray);
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
     * @param string $binary
     * @throws SyntaxException 符号化方式の検出に失敗した場合。
     * @return \SplTempFileObject
     */
    protected function correctEncoding(string $binary): \SplTempFileObject
    {
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
        
        $file = new \SplTempFileObject();
        $file->fwrite(mb_convert_encoding($binary, 'UTF-8', $fromEncoding));
        $file->rewind();
        return $file;
    }
    
    /**
     * スクリプト終了時に自動的に削除されるファイルを作成し、そのパスを返します。
     * @param \SplTempFileObject|null $file ファイルに書き込む文字列を格納したSplTempFileObject。
     * @return string
     */
    protected function generateTempFile(\SplTempFileObject $file = null): string
    {
        $path = tempnam(sys_get_temp_dir(), 'php');
        if ($file) {
            file_put_contents($path, (new \esperecyan\dictionary_php\Parser())->getBinary($file));
        }
        register_shutdown_function(function () use ($path) {
            if (file_exists($path)) {
                unlink($path);
            }
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
        
        $message = sprintf(_('「%1s」の容量は %2s です。'), $filename, $byteFormatter->format($size));
        
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
    protected function isWindows():bool
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
    protected function parseCSVFile(Dictionary $dictionary, \SplFileInfo $csv, $header = null)
    {
        if (!($csv instanceof \SplFileObject)) {
            $csv = $csv->openFile();
        } else {
            $csv->rewind();
        }
        $csv->setFlags(\SplFileObject::READ_AHEAD | \SplFileObject::SKIP_EMPTY | \SplFileObject::READ_CSV);
        
        if ($this->isWindows()) {
            $previousLocale = setlocale(LC_CTYPE, '0');
            setlocale(LC_CTYPE, '.1252');
        }
        
        try {
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
                    $fields
                );
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
     * @return \SplTempFileObject CSVファイル。
     */
    protected function parseArchive(\SplFileInfo $file): \SplTempFileObject
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

        $binary = $archive->getFromName('dictionary.csv');
        $archive->deleteName('dictionary.csv');
        if (!$binary) {
            throw new SyntaxException(_('「dictionary.csv」が見つかりません。'));
        }

        $finfo = new Finfo(FILEINFO_MIME_TYPE);
        if (!in_array($finfo->buffer($binary), ['text/plain', 'text/csv'])) {
            throw new SyntaxException(_('「dictionary.csv」は通常のテキストファイルとして認識できません。'));
        }

        $csvFile = $this->correctEncoding($binary);

        for ($i = 0, $l = $archive->numFiles; $i < $l; $i++) {
            $name = $archive->getNameIndex($i);
            if (!is_string($name)) {
                continue;
            }
            if (!$filenameValidator->validateArchivedFilename($name)) {
                throw new SyntaxException(sprintf(_('「%s」は妥当なファイル名ではありません。'), $name));
            }

            $binary = $archive->getFromIndex($i);
            $type = $finfo->buffer($binary);

            if (empty(self::VALID_EXTENSIONS[$type])) {
                throw new SyntaxException(sprintf(_('「%s」は妥当な画像、音声、動画ファイルではありません。'), $name));
            }

            $extension = explode('.', $name, 2)[1];
            if ($type === 'video/mp4' && $extension === 'm4a') {
                $type = 'audio/mp4';
            } elseif (!in_array(
                $extension,
                $type === 'video/mp4'
                    ? array_merge(self::VALID_EXTENSIONS['audio/mp4'], self::VALID_EXTENSIONS['video/mp4'])
                    : self::VALID_EXTENSIONS[$type]
            )) {
                throw new SyntaxException(sprintf(_('「%s」の拡張子は次のいずれかにしなければなりません:'), $name)
                    . ' ' . implode(', ', self::VALID_EXTENSIONS[$type]));
            }

            $topLevelType = explode('/', $type)[0];

            $this->checkFileSize(strlen(bin2hex($binary)) / 2, $topLevelType, $name);

            if ($topLevelType === 'image') {
                $validator = new \esperecyan\dictionary_php\validator\ImageValidator($type, $name);
                $validator->setLogger($this);
                $archive->addFromString($name, $validator->correct($binary));
            }

            set_time_limit(ini_get('max_execution_time'));
        }
        
        set_error_handler(function (int $severity, string $message) {
            if (strpos('ZipArchive::close(): Renaming temporary file failed: ', $message) === 0) {
                throw new \LogicException('アーカイブファイルへの書き込みに失敗しました。');
            } else {
                return false;
            }
        }, E_WARNING);
        $archive->close();
        
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
            $binary = (new \esperecyan\dictionary_php\Parser())->getBinary($file);
        }
        
        $byteFormatter = new ByteFormatter();
        $fileSize = isset($binary) ? strlen(bin2hex($binary)) / 2 : $file->getSize();
        if ($fileSize > self::MAX_COMPRESSED_ARCHIVE_SIZE) {
            throw new SyntaxException(sprintf(
                _('ファイルサイズは %1s 以下にしてください: 現在 %2s'),
                $byteFormatter->format(self::MAX_COMPRESSED_ARCHIVE_SIZE),
                $byteFormatter->format($fileSize)
            ));
        } elseif ($fileSize > self::MAX_RECOMMENDED_COMPRESSED_ARCHIVE_SIZE) {
            $this->notice(sprintf(
                _('ファイルサイズは %1s 以下にすべきです: 現在 %2s'),
                $byteFormatter->format(self::MAX_RECOMMENDED_COMPRESSED_ARCHIVE_SIZE),
                $byteFormatter->format($fileSize)
            ));
        }
        
        $finfo = new Finfo(FILEINFO_MIME_TYPE);
        $type = isset($binary) ? $finfo->buffer($binary) : $finfo->file($file->getRealPath());
        switch ($type) {
            case 'application/zip':
                $header = true;
                $csv = $this->parseArchive($file);
                break;
            
            case 'text/csv':
            case 'text/plain':
                $csv = $file;
                break;
                
            default:
                throw new SyntaxException(_('汎用辞書はCSVファイルかZIPファイルでなければなりません。'));
        }
        
        $dictionary = new Dictionary($type === 'application/zip' ? $file : null);
        $dictionary->setLogger($this);
        $this->parseCSVFile($dictionary, $csv, $header);
        
        if (!$dictionary->getWords()) {
            throw new SyntaxException(_('CSVファイルが空です。'));
        }
        
        if (empty($dictionary->getWords()[0]->getFieldsAsMultiDimensionalArray()['@title'][0])) {
            if (!is_null($title)) {
                $metaFields['@title'][] = $title;
            } elseif (!is_null($filename)) {
                $titleFromFilename = $this->getTitleFromFilename($filename);
                if ($titleFromFilename !== '') {
                    $metaFields['@title'][] = $titleFromFilename;
                }
            }
            if (isset($metaFields)) {
                $dictionary->setMetaFields($metaFields);
            }
        }
        
        return $dictionary;
    }
}
