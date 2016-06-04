<?php
namespace esperecyan\dictionary_php\serializer;

use esperecyan\dictionary_php\{Dictionary, parser\PictsenseParser, exception\SyntaxException};

class PictsenseSerializer extends AbstractSerializer
{
    protected function convertToShiftJISableInAnswer(string $str): string
    {
        return str_replace(['～', 'ヵ', 'ヶ'], ['ー', 'か', 'け'], parent::convertToShiftJISableInAnswer($str));
    }
    
    /**
     * 一つのお題を表す配列を直列化します。
     * @param (string|string[]|float|URLSearchParams)[][] $word
     * @return string 末尾に改行は含みません。直列化できないお題だった場合は空文字列を返します。
     */
    protected function serializeWord(array $word): string
    {
        if (!isset($word['type'][0]) || $word['type'][0] !== 'selection') {
            $answers = $this->getOrderedAnswers($word);
            if ($answers) {
                $pictsenseParser = new PictsenseParser();
                foreach ($answers as $answer) {
                    if (!$pictsenseParser->isHiraganaCodePoints($answer)) {
                        break;
                    }
                    if (mb_strlen($answer, 'UTF-8') <= PictsenseParser::WORD_MAX) {
                        $output = $answer;
                        break;
                    }
                }
            }
        }
        
        if (!isset($output)) {
            $output = '';
            $this->logUnserializableError('ピクトセンス', $word);
        }
        return $output;
    }
    
    /**
     * @param Dictionary $dictionary
     * @throws SyntaxException 該当の辞書形式に変換可能なお題が一つも存在しなかった。
     * @return string[]
     */
    public function serialize(Dictionary $dictionary): array
    {
        foreach ($dictionary->getWords() as $word) {
            $serialized = $this->serializeWord($word);
            if ($serialized !== '') {
                $words[] = $serialized;
            }
        }
        
        if (empty($words)) {
            throw new SyntaxException(sprintf(_('%sの辞書形式に変換可能なお題が見つかりませんでした。'), 'ピクトセンス'));
        }
        
        $wordsLength = count($words);
        if ($wordsLength > PictsenseParser::WORDS_MAX) {
            $this->logger->critical(
                sprintf(_('お題が%1$d個あります。%2$d個以内でなければピクトセンスに辞書登録することはできません。'), $wordsLength, PictsenseParser::WORDS_MAX)
            );
        } elseif ($wordsLength < PictsenseParser::WORDS_MIN) {
            $this->logger->critical(sprintf(
                _('お題が%1$d個しかありません。%2$d個以上でなければピクトセンスに辞書登録することはできません。'),
                $wordsLength,
                PictsenseParser::WORDS_MIN
            ));
        }
        
        $dictioanryCodePoints = mb_strlen(implode('', $words), 'UTF-8');
        if ($dictioanryCodePoints > PictsenseParser::DICTIONARY_CODE_POINTS_MAX) {
            $this->logger->critical(sprintf(
                _('辞書全体で%1$d文字あります。%2$d文字以内でなければピクトセンスに辞書登録することはできません。'),
                $dictioanryCodePoints,
                PictsenseParser::DICTIONARY_CODE_POINTS_MAX
            ));
        }
        
        $name = $this->getFilename($dictionary, 'csv');
        $nameLength = (new PictsenseParser())->getLengthAs16BitCodeUnits(str_replace('.csv', '', $name));
        if ($nameLength > PictsenseParser::TITLE_MAX) {
            $this->logger->error(sprintf(
                _('辞書名が%1$d文字 (補助文字は2文字扱い) あります。ピクトセンスに辞書登録する場合、%2$d文字より後の部分は切り詰められます。'),
                $dictioanryCodePoints,
                PictsenseParser::TITLE_MAX
            ));
        }
        
        return [
            'bytes' => implode("\r\n", $words) . "\r\n",
            'type' => 'text/csv; charset=UTF-8; header=absent',
            'name' => $name,
        ];
    }
}
