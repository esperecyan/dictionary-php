<?php
namespace esperecyan\dictionary_php\validator;

/**
 * アーカイブ中のファイル名の矯正。
 */
class FilenameValidator extends AbstractFieldValidator
{
    /** @var string[][] */
    const EXTENSIONS = [
        'image' => ['png', 'jpg', 'jpeg', 'svg'],
        'audio' => ['mp4', 'm4a', 'mp3'],
        'video' => ['mp4'],
    ];
    
    /** @var int 自動的に生成される拡張子を除くファイル名の長さ。偶数。 */
    const AUTO_GENERATED_FILENAME_LENGTH = 8;
    
    /** @var int 拡張子を除くファイル名の長さの上限。 */
    const MAX_LENGTH = 26;
    
    /** @var string|null */
    protected $fieldName = null;
    
    /** @var string[] */
    protected $filenames;
    
    /**
     * @param string|null $fieldName
     * @param string[] $filenames すでに存在するファイル名の一覧。
     * @throws \DomainException フィールド名がimage、audio、videoのいずれでもないとき。
     */
    public function __construct(string $fieldName = null, array $filenames = [])
    {
        parent::__construct();
        
        if (!is_null($fieldName)) {
            if (isset(static::EXTENSIONS[$fieldName])) {
                $this->fieldName = $fieldName;
            } else {
                throw new \DomainException();
            }
        }
        
        $this->filenames = $filenames;
    }
    
    /**
     * 入力値がすでに存在するファイル名ではない、かつアーカイブ中のファイル名の規則に違反していなければ真を返します。
     * @see https://github.com/esperecyan/dictionary/blob/master/dictionary.md#user-content-with-image-audio-video
     * @param string $input
     * @return bool
     */
    public function validate(string $input): bool
    {
        return !$this->isDuplicate($input)
            && preg_match('/^(?!(con|prn|aux|nul|(lpt|com)[1-9])\\.)[_0-9a-z][-_0-9a-z]{0,25}\\.(' . implode(
                '|',
                $this->fieldName
                    ? static::EXTENSIONS[$this->fieldName]
                    : call_user_func_array('array_merge', static::EXTENSIONS)
            ) . ')$/u', $input);
    }
    
    /**
     * ランダムな英数字列を生成します。
     * @return string
     */
    public function generateRandomFilename(): string
    {
        return bin2hex(random_bytes(static::AUTO_GENERATED_FILENAME_LENGTH / 2));
    }
    
    /**
     * 入力値がすでに存在するファイル名であれば真を返します。
     * @param string $input
     * @return bool
     */
    protected function isDuplicate(string $input): bool
    {
        return in_array($input, $this->filenames);
    }
    
    /**
     * 入力を妥当なファイル名に変換します。
     * @param string $filename NFC適用済みのファイル名。
     * @return string
     */
    protected function convertToValidFilenameInArchives(string $filename): string
    {
        $fullstopIndex = mb_strrpos($filename, '.', 0, 'UTF-8');
        
        return $this->preventDuplicate($this->convertToValidFilenameWithoutExtensionInArchives(
            is_int($fullstopIndex) ? mb_substr($filename, 0, $fullstopIndex, 'UTF-8') : $filename
        ) . '.' . mb_substr($filename, $fullstopIndex + 1, null, 'UTF-8'));
    }
    
    /**
     * 入力をアーカイブ中で妥当な拡張子を除くファイル名に変換します。
     * @param string $filenameWithoutExtension NFC適用済みの拡張子を除くファイル名。
     * @return string 制御文字・空白文字のみで構成されていた場合、ランダムな文字列生成します。
     */
    protected function convertToValidFilenameWithoutExtensionInArchives(string $filenameWithoutExtension): string
    {
        $asciiString = $this->preventWindowsReserved(mb_substr(trim(preg_replace(
            '/[^0-9_a-z]+/u',
            '-',
            \Stringy\StaticStringy::dasherize(
                \esperecyan\dictionary_php\internal\Transliterator::translateUsingLatinAlphabet(
                    preg_replace('/^\\p{Z}+|\\p{C}+|\\p{Z}+$/u', '', $filenameWithoutExtension)
                )
            )
        ), '-'), 0, static::MAX_LENGTH));
        
        return $asciiString === '' ? $this->generateRandomFilename() : $asciiString;
    }
    
    /**
     * Windowsにおける予約名を回避します。
     * @param string $asciiFilenameWithoutExtension ASCII英数小文字、ハイフンマイナス、ローラインのみを含む文字列。
     * @return string 予約名だった場合、末尾へハイフンマイナスを追加した文字列を返します。
     */
    protected function preventWindowsReserved($asciiFilenameWithoutExtension)
    {
        return $asciiFilenameWithoutExtension . (in_array(
            $asciiFilenameWithoutExtension,
            ['con', 'prn', 'aux', 'nul',
                'com1', 'com2', 'com3', 'com4', 'com5', 'com6', 'com7', 'com8', 'com9',
                'lpt1', 'lpt2', 'lpt3', 'lpt4', 'lpt5', 'lpt6', 'lpt7', 'lpt8', 'lpt9']
        ) ? '-' : '');
    }
    
    /**
     * ファイル名の重複を回避します。
     * @param string $filename 拡張子を含むファイル名。
     * @return string 末尾へのハイフンマイナス追加による重複回避がうまくいかなかった場合、ランダムな文字列生成します。
     */
    protected function preventDuplicate($filename): string
    {
        list($filenameWithoutExtension, $extension) = explode('.', $filename);
        while ($this->isDuplicate("$filenameWithoutExtension.$extension")) {
            if (mb_strlen($filenameWithoutExtension, 'UTF-8') >= static::MAX_LENGTH) {
                return $this->generateRandomFilename() . ".$extension";
            }
            $filenameWithoutExtension .= '-';
        }
        return "$filenameWithoutExtension.$extension";
    }

    public function correct(string $input): string
    {
        if (!preg_match('/\\.(' . implode(
            '|',
            $this->fieldName
                ? static::EXTENSIONS[$this->fieldName]
                : call_user_func_array('array_merge', static::EXTENSIONS)
        ) . ')$/u', $input)) {
            throw new \DomainException();
        }
        
        if ($this->validate($input)) {
            $output = $input;
        } else {
            $normalized = \Normalizer::normalize($input);
            $output = $this->validate($normalized) ? $normalized : $this->convertToValidFilenameInArchives($normalized);
        }
        return $output;
    }
}
