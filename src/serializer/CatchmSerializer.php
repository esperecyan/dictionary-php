<?php
namespace esperecyan\dictionary_php\serializer;

use esperecyan\dictionary_php\{Dictionary, exception\EmptyOutputException};

class CatchmSerializer extends AbstractSerializer
{
    /**
     * スペースを除くASCII印字可能文字を、対応する全角形に置き換えます。
     * @param string $str
     * @return string
     */
    protected function convertToFullwidth(string $str): string
    {
        return preg_replace_callback('/[!-~]/u', function (array $matches): string {
            return \IntlChar::chr(\IntlChar::ord($matches[0]) - (\IntlChar::ord('!') - \IntlChar::ord('！')));
        }, $str);
    }
    
    /**
     * 一つのお題を表す配列を直列化します。
     * @param (string|string[]|float)[][] $word
     * @return string 末尾に改行 (CRLF) を含みます。直列化できないお題だった場合は空文字列を返します。
     */
    protected function serializeWord(array $word): string
    {
        if (!isset($word['type'][0]) || $word['type'][0] !== 'selection') {
            $answers = $this->getOrderedAnswers($word);
            if ($answers) {
                $output = implode(',', array_map((function (string $answer, int $key): string {
                    $lowercase = strtolower($answer);
                    if ($key === 0) {
                        $serializedAnswer[] = $this->convertToFullwidth($lowercase);
                    }
                    if ($key > 0 || $serializedAnswer[0] !== $lowercase) {
                        $serializedAnswer[] = str_replace([',', '/', ';', '['], ['，', '／', '；', '［'], $lowercase);
                    }
                    return implode(',', $serializedAnswer);
                }), $answers, array_keys($answers)));
                
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
                    $output .= "// " . str_replace("\n", ' ', $comment);
                }
                $output .= "\r\n";
            }
        }
        
        if (!isset($output)) {
            $output = '';
            $this->logUnserializableError('きゃっちま', $word);
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
            throw new EmptyOutputException(_('きゃっちまの辞書形式に変換可能なお題が見つかりませんでした。'));
        }
        
        return [
            'bytes' => mb_convert_encoding(
                $this->serializeMetadata($dictionary, '//', ['[', ']']) . implode('', $words),
                'Windows-31J',
                'UTF-8'
            ),
            'type' => 'text/plain; charset=Shift_JIS',
            'name' => $this->getFilename($dictionary, 'dat'),
        ];
    }
}
