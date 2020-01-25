<?php
namespace esperecyan\dictionary_php\internal;

use JpnForPhp\Transliterator\{Transliterator as JpnForPhpTransliterator, System\Hepburn};

/**
 * 漢字仮名交じり文の翻字。
 */
class Transliterator
{
    /** @var \Igo\Tagger */
    protected static $igo;
    
    /**
     * 文字列中のひらがな・カタカナ・漢字を、可能な限りヘボン式ローマ文字に変換し、文節と思われる部分にハイフンマイナスを挟みます。
     * @param string $kanjiAndKanaString
     * @return string
     */
    public static function translateUsingLatinAlphabet(string $kanjiAndKanaString): string
    {
        return static::deleteMarks(
            (new JpnForPhpTransliterator())->setSystem(new Hepburn())
                ->transliterate(preg_match('/[^ -~]/u', $kanjiAndKanaString)
                ? static::translateUsingKatakana($kanjiAndKanaString)
                : $kanjiAndKanaString)
        );
    }
    
    /**
     * 文字列のダイアクリティカルマークや囲みを外します。
     * @param string $input
     * @return string
     */
    protected static function deleteMarks(string $input): string
    {
        return preg_replace(
            '/\\p{M}+/u',
            '',
            \Normalizer::normalize($input, \Normalizer::FORM_KD)
        );
    }
    
    /**
     * 文字列中のひらがな・漢字を、可能な限りカタカナに変換し、文節と思われる部分にハイフンマイナスを挟みます。
     * @param string $kanjiAndKanaString
     * @return string
     */
    protected static function translateUsingKatakana(string $kanjiAndKanaString): string
    {
        if (!static::$igo) {
            static::$igo = new \Igo\Tagger();
        }

        return implode('-', array_map(function ($morpheme) {
            return isset($morpheme->feature[8]) && !in_array($morpheme->feature[1], ['数', 'アルファベット'])
                ? $morpheme->feature[8]
                : $morpheme->surface;
        }, static::$igo->parse($kanjiAndKanaString)));
    }
}
