<?php
namespace esperecyan\dictionary_php\serializer;

use esperecyan\dictionary_php\{Dictionary, exception\EmptyOutputException};

class CatchfeelingSerializer extends AbstractSerializer
{
    /**
     * 一つのお題を表す配列を直列化します。
     * @param (string|string[]|float|URLSearchParams)[][] $word
     * @return string 末尾に改行 (CRLF) を含みます。直列化できないお題だった場合は空文字列を返します。
     */
    protected function serializeWord(array $word): string
    {
        if (!isset($word['type'][0]) || $word['type'][0] !== 'selection') {
            $answers = $this->getOrderedAnswers($word);
            if ($answers) {
                foreach ($answers as $answer) {
                    if (strpos($answer, '//') === false) {
                        $output = $answer;
                        break;
                    }
                }
                if (!isset($output)) {
                    $output = str_replace('/', '／', $answers[0]);
                }
                $output = strtolower($output);
                
                $comment = '';
                if (isset($word['answer'][0])) {
                    $shiftJISableText = $this->convertToShiftJISable($word['text'][0]);
                    if ($answers[0] !== $shiftJISableText && $this->isShiftJISable($shiftJISableText)) {
                        $comment .= "【${shiftJISableText}】";
                    }
                }
                if (isset($word['description'][0])) {
                    $shiftJISableDescription = $this->convertToShiftJISable($word['description'][0]['lml']);
                    if ($this->isShiftJISable($shiftJISableDescription)) {
                        $comment .= $shiftJISableDescription;
                    }
                }
                if ($comment !== '') {
                    $output .= "\t// " . str_replace("\n", ' ', $comment);
                }
                $output .= "\r\n";
            }
        }
        
        if (!isset($output)) {
            $output = '';
            $this->logUnserializableError('キャッチフィーリング', $word);
        }
        return $output;
    }
    
    /**
     * @param Dictionary $dictionary
     * @throws EmptyOutputException 該当の辞書形式に変換可能なお題が一つも存在しなかった。
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
            throw new EmptyOutputException(sprintf(_('%sの辞書形式に変換可能なお題が見つかりませんでした。'), 'キャッチフィーリング'));
        }
        
        return [
            'bytes' => mb_convert_encoding(implode('', $words), 'Windows-31J', 'UTF-8'),
            'type' => 'text/plain; charset=Shift_JIS',
            'name' => $this->getFilename($dictionary, 'cfq', ' [語数 ' . count($words) . ']'),
        ];
    }
}
