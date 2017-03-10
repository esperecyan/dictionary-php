<?php
namespace esperecyan\dictionary_php\validator;

use esperecyan\webidl\TypeError;
use esperecyan\url\URL;

/**
 * image、audio、videoフィールド、src属性値、およびアーカイブ中のファイル名の矯正。
 */
class FileLocationValidator extends AbstractFieldValidator
{
    /** @var string 不正な入力値の矯正後、tag URLとして出力する場合に付加する接頭辞。 */
    const DEFAULT_TAG_URL_PREFIX = 'tag:pokemori.jp,2016:local:';
    
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
        return in_array($input, $this->filenames)
            || $input !== '' &&$input === $this->correctAbsoluteURLWithAllowedScheme($input);
    }
    
    /**
     * 許可されたスキーム (https、tag、urn) を持つ絶対URL文字列として矯正します。
     * @see https://github.com/esperecyan/dictionary/blob/master/dictionary.md#file-location
     * @param string $input
     * @return string 矯正できなかった場合は空文字列を返します。
     */
    protected function correctAbsoluteURLWithAllowedScheme(string $input): string
    {
        try {
            $url = new URL($input);
        } catch (TypeError $exception) {
            return '';
        }
        if ($url->protocol === 'http:') {
            $url->protocol = 'https';
        }
        return in_array($url->protocol, ['https:', 'tag:', 'urn:']) ? $url : '';
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
    
    /**
     * tag URL構文のspecificルールにおいて、パーセント符号化が必要な文字をパーセント符号化します。
     * @link https://tools.ietf.org/html/rfc4151#section-2.1
     *      Tag Syntax and Examples | Tag Syntax and Rules | RFC 4151 — The ‘tag’ URI Scheme
     * @param string $specific
     * @return string
     */
    protected function percentEncodeSpecific(string $specific): string
    {
        return preg_replace_callback('/[^!$&-;=?-Z_a-z~]/u', function (array $matches): string {
            return rawurlencode($matches[0]);
        }, $specific);
    }

    public function correct(string $input): string
    {
        if ($this->validate($input)) {
            $output = $input;
        } elseif (isset($this->filenames[strtolower($input)])) {
            $output = $this->filenames[strtolower($input)];
        } elseif (in_array(strtolower($input), $this->filenames)) {
            $output = strtolower($input);
            $this->logger->error(sprintf(_('大文字小文字無視で合致するファイルが含まれているため、「%s」を小文字化しました。'), $input));
        } elseif ($url = $this->correctAbsoluteURLWithAllowedScheme($input)) {
            $output = $url;
            $this->logger->error(sprintf(_('URL「%s」を「%s」に修正しました。'), $input, $output));
        } else {
            $basename = $this->getBasename($input);
            if ($this->validate($basename)) {
                $output = $basename;
                $this->logger->error(sprintf(_('「%s」はファイル所在の規則に合致しません。'), $input));
            } elseif (isset($this->filenames[strtolower($basename)])) {
                $output = $this->filenames[strtolower($basename)];
                $this->logger->error(sprintf(_('「%s」はファイル所在の規則に合致しません。'), $input));
            } else {
                $output = static::DEFAULT_TAG_URL_PREFIX . $this->percentEncodeSpecific(
                    $basename === '' ? (new FilenameValidator())->generateRandomFilename() : $basename
                );
                $this->logger->error(sprintf(_('「%s」はファイル所在の規則に合致しません。'), $input));
            }
        }
        
        return $output;
    }
}
