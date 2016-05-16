<?php
namespace esperecyan\dictionary_php\parser;

abstract class AbstractParser
{
    /** @var string 解答に使われるすべての文字列。 */
    protected $wholeText = '';
    
    const FREQUENT_REGARDS = [
        '[〜ぁ-ゖヷ-ヺー]',
        '[a-z]',
        '[0-9a-z]',
        '[一-龠]',
        '[〜ぁ-ゖヷ-ヺー一-龠]',
        '[0-9a-z〜ぁ-ゖヷ-ヺー一-龠]',
        '[0-9〜ぁ-ゖヷ-ヺー]',
        '[0-9a-z〜ぁ-ゖヷ-ヺー]',
    ];
    
    /**
     * @var string[] ゲーム中同一視される文字。
     * @see https://github.com/esperecyan/dictionary/blob/master/dictionary.md#equivalent 主に単語で答えるゲームにおける汎用的な辞書形式
     */
    const EQUIVALENT = [
        '~' => '〜',
        '\'' => '’',
        '"' => '”',
        '“' => '”',
    ];
    
    /**
     * $wholeText から @regard メタフィールドの内容を生成します。
     * @return string 既定値になる場合は空文字列を返します。
     */
    protected function generateRegard(): string
    {
        $unique = $this->eliminateDuplicateCharacters($this->unity($this->wholeText));
        foreach (self::FREQUENT_REGARDS as $i => $regard) {
            if (preg_match("/$regard/u", $unique) === 1) {
                return $i === 0 ? '' : $regard;
            }
        }
        return '[' . preg_quote($unique, '/') . ']';
    }
    
    /**
     * ASCII小文字化、カタカナをひらがな化などを行います。
     */
    protected function unity(string $input): string
    {
        return strtr(mb_convert_kana(strtolower($input), 'c'), self::EQUIVALENT);
    }
    
    /**
     * 重複する文字を取り除きます。
     */
    protected function eliminateDuplicateCharacters(string $input): string
    {
        return implode('', array_unique(preg_split('//u', $input, -1, PREG_SPLIT_NO_EMPTY)));
    }
    
    /**
     * 辞書の構文解析を行います。
     * @param \SplFileInfo $file
     * @param string|null $filename
     * @param string|null $title
     * @throws \esperecyan\dictionary_php\exception\SyntaxException 構文に問題がある時。
     * @return \esperecyan\dictionary_php\internal\Dictionary
     */
    abstract public function parse(
        \SplFileInfo $file,
        string $filename = null,
        string $title = null
    ): \esperecyan\dictionary_php\internal\Dictionary;
}
