<?php
namespace esperecyan\dictionary_php\validator;

/**
 * text、answer、optionフィールドの矯正。
 */
class AnswerValidator extends AbstractFieldValidator
{
    /**
     * 先頭と末尾の文字がともにスラッシュである場合に真を返します。
     * @param string $input
     * @return bool
     */
    public function isRegExp(string $input): bool
    {
        return preg_match('/^\\/.*\\/$/u', $input) === 1;
    }

    /**
     * 入力値がanswerフィールドの規則に違反していなければ真を返します。
     * @param string $input
     * @return bool
     */
    protected function validate(string $input): bool
    {
        return \Normalizer::isNormalized($input, \Normalizer::FORM_KC)
            && ($this->isRegExp($input) ? $this->validateRegexp($input) : $this->validateCharacters($input));
    }
    
    /**
     * 入力が空文字列でなく、各文字がすべて妥当であれば真を返します。
     * @param string $input NFKC適用済みの文字列。
     * @return bool
     */
    protected function validateCharacters(string $input): bool
    {
        return preg_match('/^[^\\p{C}\\p{Z}\\p{M}]+$/u', $input) === 1;
    }
    
    /**
     * 妥当な正規表現文字列の可能性が高い場合は真を返します。
     * @param string $input NFKC適用済みの文字列。
     * @return bool
     */
    public function validateRegexp(string $input): bool
    {
        $output = true;
        set_error_handler(function (int $severity, string $message) use (&$output) {
            if (strpos($message, 'preg_replace(): ') === 0) {
                $output = false;
            } else {
                return false;
            }
        }, E_WARNING | E_DEPRECATED);
        preg_replace($input, '', '');
        restore_error_handler();
        return $output
            ? preg_match("/^\\/[^\\p{C}\\p{Z}\\p{M}A-Zァ-ヶ\u{10000}-\u{10FFFD}]+\\/$/u", $input) === 1
            : false;
    }
    
    /**
     * 入力を妥当な値に変換します。
     * @param string $input
     * @return string 変換できなかった場合は空文字列を返します。
     */
    protected function convertToValidCharacters(string $input): string
    {
        $converted = preg_replace(
            '/[\\p{C}\\p{Z}\\p{M}]+/u',
            '',
            \Normalizer::normalize(str_replace('～', '〜', $input), \Normalizer::FORM_KC)
        );
        if ($this->isRegExp($converted)) {
            $converted = trim($converted, '/');
        }
        return $converted;
    }
    
    /**
     * すべての文字がひらがな、およびひらがな化可能なカタカナなら真を返します。
     * @param string $input NFKC適用済みの文字列。
     * @return bool
     */
    protected function isHiraganaOrReplaceableKatakana(string $input): bool
    {
        return preg_match('/^[〜あ-ゖア-ヶー]+$/u', $input) === 1;
    }
    
    public function correct(string $input): string
    {
        if ($this->validate($input)) {
            if ($this->logger && $this->isRegExp($input)) {
                $this->logger->notice(_('正規表現は使わないようにするべきです。'));
            }
            $output = $input;
        } elseif ($this->isRegExp($input)) {
            if ($this->logger) {
                $this->logger->error(sprintf(_('「%s」は正規表現文字列の規則に合致しません。'), $input));
            }
            $output = '';
        } else {
            if ($this->logger) {
                $this->logger->error(sprintf(_('「%s」は解答文字列の規則に合致しません。'), $input));
            }
            $output = $this->convertToValidCharacters($input);
        }
        
        if ($this->logger && $output !== ''
            && !$this->isRegExp($input) && !$this->isHiraganaOrReplaceableKatakana($output)) {
            $this->logger->notice(_('日本語話者向けの辞書であれば、解答はひらがなかカタカナにすべきです: ') . $output);
        }
        
        return $output;
    }
}
