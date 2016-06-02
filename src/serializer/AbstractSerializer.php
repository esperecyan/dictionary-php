<?php
namespace esperecyan\dictionary_php\serializer;

use esperecyan\dictionary_php\{
    Dictionary,
    validator\AnswerValidator,
    validator\FileLocationValidator,
    log\AbstractLoggerAware
};

abstract class AbstractSerializer extends AbstractLoggerAware
{
    use \esperecyan\dictionary_php\internal\ArchiveGenerator;
    
    /** @var string 辞書のタイトルが存在しなかった場合の拡張子を除くファイル名。 */
    const DEFAULT_FILENAME = 'dictionary';
    
    /** @var string 辞書のタイトルを囲む括弧の既定値。 */
    const DEFAULT_BRACKETS = ['【', '】'];
    
    /** @var string Shift_JISに存在しない文字を調べる際に利用する置換文字。 */
    const SUBSTITUTE_CHARACTER = '〓';
    
    /**
     * ファイル名を取得します。
     * @param Dictionary $dictionary
     * @param string $extension ピリオドを除く拡張子。
     * @param string $suffix ファイル名の末尾に追加する文字列。
     */
    protected function getFilename(Dictionary $dictionary, string $extension, string $suffix = '')
    {
        return (new FileLocationValidator())->convertToValidFilenameWithoutExtension(
            ($dictionary->getMetadata()['@title'] ?? self::DEFAULT_FILENAME) . $suffix
        ) . ".$extension";
    }
    
    /**
     * 辞書自体のタイトルと説明文を直列化します。Shift_JISに存在しない符号位置が含まれているタイトル、または説明文は存在しないものとして扱います。
     * @param Dictionary $dictionary
     * @param string $lineCommentString 行コメント文字。
     * @param string[]|null $brackets 開き括弧と閉じ括弧の配列。指定されていればタイトルをこの括弧で囲み、タイトル先頭の行コメント文字を省略します。
     * @return string 改行はCRLFを使用します。辞書にタイトルか説明文のいずれかが存在すれば、末尾に改行を2つ付けます。どちらも無ければ空文字列を返します。
     */
    protected function serializeMetadata(
        Dictionary $dictionary,
        string $lineCommentString = '//',
        array $brackets = null
    ):string {
        $metadata = $dictionary->getMetadata();
        
        if (isset($metadata['@title'])) {
            $shiftJisableTitle = $this->convertToShiftJISable($metadata['@title']);
            if ($this->isShiftJISable($shiftJisableTitle)) {
                $prefix = ($brackets ? '' : "$lineCommentString ") . ($brackets[0] ?? self::DEFAULT_BRACKETS[0]);
                $serialized[] = $prefix . str_replace(
                    "\n",
                    "\r\n$lineCommentString "
                        . str_repeat(' ', max(0, mb_strwidth($prefix) - mb_strwidth("$lineCommentString "))),
                    $shiftJisableTitle
                ) . ($brackets[1] ?? self::DEFAULT_BRACKETS[1]);
            }
        }
        
        if (isset($metadata['@summary'])) {
            $shiftJisableSummary = $this->convertToShiftJISable($metadata['@summary']['lml']);
            if ($this->isShiftJISable($shiftJisableSummary)) {
                $serialized[] = "$lineCommentString " . str_replace(
                    "\n",
                    "\r\n$lineCommentString ",
                    $shiftJisableSummary
                );
            }
        }
        
        return isset($serialized) ? implode("\r\n", $serialized) . "\r\n\r\n" : '';
    }
    
    /**
     * 指定された辞書形式に直列化できないお題があったことを、「error」レベルで記録します。
     * @param string $to 辞書形式。
     * @param (string|string[]|float|URLSearchParams)[][] $word
     */
    protected function logUnserializableError(string $to, array $word)
    {
        $this->logger->error(
            sprintf(_('以下のお題は%sの辞書形式に変換できません。'), $to) . "\n"
                . (new \esperecyan\dictionary_php\parser\GenericDictionaryParser())->convertToCSVRecord(
                    isset($word['answer'][0]) ? $word['answer'] : $word['text']
                )
        );
    }
    
    /**
     * 一つのお題を表す配列から、以下の優先順位でお題を並べ替えて返します。
     * 1. ひらがな・カタカナ
     * 2. ひらがな・カタカナ + ASCII数字
     * 3. ひらがな・カタカナ + ASCII英数字
     * 4. ASCII英字
     * 5. ASCII英数字
     * 6. その他
     * @param (string|string[]|float|URLSearchParams)[][] $word
     * @return string[] Shift_JISに直列化不能な解答しか存在しなければ、空の配列を返します。
     */
    protected function getOrderedAnswers(array $word)
    {
        $answers = $this->getAnswers($word);
        if ($answers) {
            foreach ($answers as $answer) {
                preg_match('/^(?:([～ぁ-ゔァ-ヴー]+)|([～ぁ-ゔァ-ヴー0-9]+)|([～ぁ-ゔァ-ヴー0-9a-z]+)|([a-z]+)|([0-9a-z]+)|(.+))$/iu', $answer, $matches);
                $orders[] = count($matches) - 1;
            }
            array_multisort($orders, array_keys($answers), $answers);
        }
        return $answers;
    }
    
    /**
     * すべての符号位置がShift_JISに存在すれば真を返します。
     * @param string $str
     * @retun bool
     */
    protected function isShiftJISable(string $str): bool
    {
        $previousSubstituteCharacter = mb_substitute_character();
        mb_substitute_character(\IntlChar::ord(self::SUBSTITUTE_CHARACTER));
        
        $codePoints = preg_split(
            '//u',
            str_replace(self::SUBSTITUTE_CHARACTER, '', $str),
            -1,
            PREG_SPLIT_NO_EMPTY
        );
        mb_convert_variables('Windows-31J', 'UTF-8', $codePoints);
        
        mb_substitute_character($previousSubstituteCharacter);
        
        return !in_array(mb_convert_encoding(self::SUBSTITUTE_CHARACTER, 'Windows-31J', 'UTF-8'), $codePoints);
    }
    
    /**
     * 一つのお題を表す配列から、正規表現を除いた解答をShift_JISに直列化可能に文字列に変換して返します。
     * @param (string|string[]|float|URLSearchParams)[][] $word
     * @param callable|null $convert 変換器。
     * @return string[] 選択肢必須の問題、またはShift_JISに直列化不能な解答しか存在しなければ、空の配列を返します。
     */
    protected function getAnswers(array $word, callable $convert = null): array
    {
        $answerValidator = new AnswerValidator();
        
        foreach (isset($word['answer'][0]) ? $word['answer'] : $word['text'] as $answer) {
            if (!$answerValidator->isRegExp($answer)) {
                $shiftJISable = $convert
                    ? call_user_func($convert, $answer)
                    : $this->convertToShiftJISableInAnswer($answer);
                if ($this->isShiftJISable($shiftJISable)) {
                    $answers[] = $shiftJISable;
                }
            }
        }
        
        return $answers ?? [];
    }
    
    /**
     * Shift_JISに存在しないひらがな、カタカナなどを、Shift_JISに直列化可能な文字に置き換えます。
     * @param string $str
     * @return string
     */
    protected function convertToShiftJISable(string $str): string
    {
        return str_replace(
            ['〜', 'ゔ'  , 'ヷ'  , 'ヸ'  , 'ヹ'  , 'ゕ', 'ゖ', 'ㇰ', 'ㇱ', 'ㇲ', 'ㇳ', 'ㇴ', 'ㇵ', 'ㇶ', 'ㇷ', 'ㇸ', 'ㇹ', 'ㇻ', 'ㇼ', 'ㇽ', 'ㇾ', 'ㇿ', 'ㇺ', "\u{1B000}", "\u{1B001}"],
            ['～', 'う゛', 'ワ゛', 'ヰ゛', 'ヱ゛', 'ヵ', 'ヶ', 'ク', 'シ', 'ス', 'ト', 'ヌ', 'ハ', 'ヒ', 'フ', 'ヘ', 'ホ', 'ラ', 'リ', 'ル', 'レ', 'ロ', 'ム', 'エ'       , 'え'       ],
            $str
        );
    }
    
    /**
     * 解答内のShift_JISに存在しないひらがな、カタカナなどを、Shift_JISに直列化可能なひらがなに出来る限り置き換えます。
     * @param string $str
     * @return string
     */
    protected function convertToShiftJISableInAnswer(string $str): string
    {
        return str_replace(
            ['〜', 'ゔ', 'ヷ'  , 'ヸ'  , 'ヹ'  , 'ゕ', 'ゖ', 'ㇰ', 'ㇱ', 'ㇲ', 'ㇳ', 'ㇴ', 'ㇵ', 'ㇶ', 'ㇷ', 'ㇸ', 'ㇹ', 'ㇻ', 'ㇼ', 'ㇽ', 'ㇾ', 'ㇿ', 'ㇺ', "\u{1B000}", "\u{1B001}"],
            ['～', 'ヴ', 'ヴぁ', 'ヴぃ', 'ヴぇ', 'ヵ', 'ヶ', 'く', 'し', 'す', 'と', 'ぬ', 'は', 'ひ', 'ふ', 'へ', 'ほ', 'ら', 'り', 'る', 'れ', 'ろ', 'む', 'え'       , 'え'       ],
            mb_convert_kana($str, 'c', 'UTF-8')
        );
    }
    
    /**
     * 辞書を直列化したデータを返します。
     * @param Dictionary
     * @return string[]
     */
    abstract public function serialize(Dictionary $dictionary): array;
}
