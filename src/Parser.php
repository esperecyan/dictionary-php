<?php
namespace esperecyan\dictionary_php;

use esperecyan\dictionary_php\exception\SyntaxException;

class Parser extends log\AbstractLoggerAware
{
    /** @var int SplTempFileObject::read で一度に読み込むバイト数。 */
    const READ_LENGTH = 2 ** 20;
    
    /** @var string|null */
    protected $filename = null;
    
    /** @var string|null */
    protected $title = null;
    
    /** @var string */
    protected $from;
    
    /**
     * @param string|null $from
     * @param string|null $filename
     * @param string|null $title
     */
    public function __construct(string $from = null, string $filename = null, string $title = null)
    {
        parent::__construct();

        $this->filename = $filename;
        $this->title = $title;
        
        if (is_null($from)
            || !in_array($from, ['キャッチフィーリング', 'きゃっちま', 'Inteligenceω クイズ', 'Inteligenceω しりとり', 'ピクトセンス', '汎用辞書'])) {
            switch ($this->filename ? pathinfo($this->filename, PATHINFO_EXTENSION) : 'csv') {
                case 'cfq':
                    $this->from = 'キャッチフィーリング';
                    break;
                case 'dat':
                    $this->from = 'きゃっちま';
                    break;
                case 'txt':
                    $this->from = 'Inteligenceω';
                    break;
                default:
                    $this->from = '汎用辞書';
            }
        } else {
            $this->from = $from;
        }
    }
    
    /**
     * SplFileInfo の中身を取得します。
     * @param \SplFileInfo $file
     * @return string
     */
    public function getBinary(\SplFileInfo $file): string
    {
        if (!($file instanceof \SplFileObject)) {
            $file = $file->openFile();
        } else {
            $file->rewind();
        }
        if ($file instanceof \SplTempFileObject) {
            $binary = '';
            while ($file->valid()) {
                $binary .= $file->fread(self::READ_LENGTH);
            }
        } else {
            if (!($file instanceof \SplFileObject)) {
                $file = $file->openFile();
            }
            $binary = $file->fread($file->getSize());
        }
        return $binary;
    }
    
    /**
     * @param \SplFileInfo $file
     * @param bool|null $header CSVにヘッダ行が存在すれば真、存在しなければ偽、不明ならnull。
     * @throws SyntaxException
     * @return Dictionary
     */
    public function parse(\SplFileInfo $file, $header = null): Dictionary
    {
        if ($this->from === '汎用辞書') {
            $parser = new parser\GenericDictionaryParser($header);
        } else {
            $binary = $this->getBinary($file);

            $finfo = new fileinfo\Finfo(FILEINFO_MIME_TYPE);
            if (!in_array($finfo->buffer($binary), ['text/csv', 'text/plain'])) {
                throw new SyntaxException(sprintf(_('%sの辞書は通常のテキストファイルでなければなりません。'), $this->from));
            }
            
            if ($file instanceof \SplTempFileObject) {
                $file->ftruncate(0);
            } else {
                $file = new \SplTempFileObject();
            }
            
            if ($this->from === 'ピクトセンス') {
                $file->fwrite((new parser\GenericDictionaryParser())->correctEncoding($binary));
            } else {
                if (!mb_check_encoding($binary, 'Windows-31J')) {
                    throw new SyntaxException(sprintf(_('%sの辞書の符号化方式 (文字コード) は Shift_JIS でなければなりません。'), $this->from));
                }
                $file->fwrite(mb_convert_encoding($binary, 'UTF-8', 'Windows-31J'));
            }
            $file->rewind();
            
            switch ($this->from) {
                case 'キャッチフィーリング':
                    $parser = new parser\CatchfeelingParser();
                    break;
                case 'きゃっちま':
                    $parser = new parser\CatchmParser();
                    break;
                case 'Inteligenceω クイズ':
                case 'Inteligenceω しりとり':
                case 'Inteligenceω':
                    $parser = new parser\InteligenceoParser($this->from);
                    break;
                case 'ピクトセンス':
                    $parser = new parser\PictsenseParser();
                    break;
            }
        }
        
        $parser->setLogger($this->logger);
        
        return $parser->parse($file, $this->filename, $this->title, $this->from);
    }
}
