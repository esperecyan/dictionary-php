<?php
namespace esperecyan\dictionary_php\validator;

/**
 * image、audio、videoフィールド、src属性値、およびアーカイブ中のファイル名の矯正。
 */
class FileLocationValidator extends AbstractFieldValidator
{
    /** @var string 不正な入力値の矯正後に付加するWebサービス識別子。 */
    const DEFAULT_WEB_SERVICE_IDENTIFIER = 'local';
    
    /** @var string|null */
    protected $fieldName = null;
    
    /** @var string[] */
    protected $filenames = [];
    
    /**
     * @param string|null $fieldName
     * @param string[] $filenames CSVファイルと同梱されているファイルの一覧。文字列キーが存在すれば、変更前の名前を表します。
     * @throws \DomainException フィールド名がimage、audio、videoのいずれでもないとき。
     */
    public function __construct(string $fieldName = null, array $filenames = [])
    {
        parent::__construct();
        
        if (!is_null($fieldName)) {
            if (isset(FilenameValidator::EXTENSIONS[$fieldName])) {
                $this->fieldName = $fieldName;
            } else {
                throw new \DomainException();
            }
        }
        $this->filenames = array_change_key_case($filenames);
    }
    
    /**
     * 入力値がファイル所在として妥当であれば真を返します。
     * @see https://github.com/esperecyan/dictionary/blob/master/dictionary.md#file-location
     * @param string $input
     * @return bool
     */
    public function validate(string $input): bool
    {
        return in_array($input, $this->filenames) || $this->validateWebServiceIdentifierAndFilename($input);
    }
    
    /**
     * Webサービス識別子、ソリダス、妥当なファイル名の並びになっていれば真を返します。
     * @see https://github.com/esperecyan/dictionary/blob/master/dictionary.md#file-location
     * @param string $input
     * @return bool
     */
    protected function validateWebServiceIdentifierAndFilename(string $input): bool
    {
        return \Normalizer::isNormalized($input)
            && preg_match(
                '/^[0-9a-z-]+\\/(?!((?i)CON|PRN|AUX|NUL|(LPT|COM)[1-9]|\\p{Z}.*|.*\\p{Z})\\.)[^\\p{C}"*.\\/:<>?\\\\|]+\\.(' . implode(
                    '|',
                    $this->fieldName
                        ? FilenameValidator::EXTENSIONS[$this->fieldName]
                        : call_user_func_array('array_merge', FilenameValidator::EXTENSIONS)
                ) . ')$/u',
                $input
            ) === 1;
    }
    
    /**
     * 入力値が妥当なファイル名であれば真を返します。
     * @see https://github.com/esperecyan/dictionary/blob/master/dictionary.md#valid-filename
     * @param string $input
     * @return bool
     */
    protected function validateFilename(string $input): bool
    {
        return \Normalizer::isNormalized($input)
            && preg_match(
                '/^(?!(CON|PRN|AUX|NUL|(LPT|COM)[1-9]|\\p{Z}.*|.*\\p{Z})\\.)[^\\p{C}"*.\\/:<>?\\\\|]+\\.(' . implode(
                    '|',
                    $this->fieldName
                        ? FilenameValidator::EXTENSIONS[$this->fieldName]
                        : call_user_func_array('array_merge', FilenameValidator::EXTENSIONS)
                ) . ')$/ui',
                $input
            ) === 1;
    }
    
    /**
     * パスのファイル名部分を返します。
     * @param string $input
     * @return string ファイル名が取得できなかった場合は空文字列を返します。
     */
    public function getBasename(string $input): string
    {
        preg_match('/([^\\/\\\\]*?)\\s*$/u', $input, $matches);
        return $matches[1] ?? '';
    }
    
    /** @var int 全角形が存在するASCII符号位置を対応する全角形の符号位置にするときの加数。 */
    const BETWEEN_HALF_AND_FULL = 0xFEE0;
    
    /**
     * 入力を妥当なファイル名に変換します。
     * @param string $filename NFC適用済みのファイル名。
     * @return string
     */
    protected function convertToValidFilename(string $filename): string
    {
        $fullstopIndex = mb_strrpos($filename, '.', 0, 'UTF-8');
        
        return $this->convertToValidFilenameWithoutExtension(
            is_int($fullstopIndex) ? mb_substr($filename, 0, $fullstopIndex, 'UTF-8') : $filename
        ) . '.' . $this->convertToValidExtension(
            is_int($fullstopIndex) ? mb_substr($filename, $fullstopIndex + 1, null, 'UTF-8') : ''
        );
    }
    
    /**
     * 入力を妥当な拡張子を除くファイル名に変換します。
     * @param string $filenameWithoutExtension NFC適用済みの拡張子を除くファイル名。
     * @return string 制御文字、および空白文字のみで構成されていた場合、ランダムな文字列生成します。
     */
    public function convertToValidFilenameWithoutExtension(string $filenameWithoutExtension): string
    {
        /** @var string 制御文字、先頭末尾の空白を取り除いた文字列。 */
        $trimed = preg_replace('/^\\p{Z}+|\\p{C}+|\\p{Z}+$/u', '', $filenameWithoutExtension);
        
        return $trimed === ''
            ? (new FilenameValidator())->generateRandomFilename()
            : preg_replace_callback(
                '/^(CON|PRN|AUX|CLOCK\\$|NUL|(COM|LPT)[1-9])$|["*.\\/:<>?\\\\|]+/i',
                function (array $matches): string {
                    $breakIterator = \IntlCodePointBreakIterator::createCodePointInstance();
                    $breakIterator->setText($matches[0]);
                    $fullWidthChars = '';
                    foreach ($breakIterator as $index) {
                        if ($index > 0) {
                            $fullWidthChars .= \IntlChar::chr(
                                $breakIterator->getLastCodePoint() + self::BETWEEN_HALF_AND_FULL
                            );
                        }
                    }
                    return $fullWidthChars;
                },
                $trimed
            );
    }
    
    /**
     * 入力を妥当な拡張子に変換します。
     * @param string $extension
     * @return string 誤った拡張子だった場合、妥当な拡張子の1つを返します。
     */
    protected function convertToValidExtension(string $extension): string
    {
        return (in_array($extension, FilenameValidator::EXTENSIONS[$this->fieldName])
            ? $extension
            : FilenameValidator::EXTENSIONS[$this->fieldName][0]);
    }

    public function correct(string $input): string
    {
        if (in_array($input, $this->filenames)) {
            $output = $input;
        } elseif (in_array(strtolower($input), $this->filenames)) {
                $output = $basename;
        } elseif (isset($this->filenames[strtolower($input)])) {
            $output = $this->filenames[strtolower($input)];
        } else {
            $basename = $this->getBasename($input);
            if ($this->validate($basename)) {
                $output = $basename;
                $this->logger->error(sprintf(_('「%s」はファイル所在の規則に合致しません。'), $input));
            } elseif (isset($this->filenames[strtolower($basename)])) {
                $output = $this->filenames[strtolower($basename)];
                $this->logger->error(sprintf(_('「%s」はファイル所在の規則に合致しません。'), $input));
            } elseif ($this->validate($input)) {
                $output = $input;
            } elseif ($this->validate($input)) {
                $output = $basename;
            } else {
                $normalized = \Normalizer::normalize($basename);
                $output = self::DEFAULT_WEB_SERVICE_IDENTIFIER . '/'
                    . ($this->validate($normalized) ? $normalized : $this->convertToValidFilename($normalized));
                $this->logger->error(sprintf(_('「%s」はファイル所在の規則に合致しません。'), $input));
            }
        }
        
        return $output;
    }
}
