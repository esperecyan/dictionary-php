<?php
namespace esperecyan\dictionary_php\serializer;

use esperecyan\url\URLSearchParams;
use esperecyan\dictionary_php\{Dictionary, parser\InteligenceoParser, exception\EmptyOutputException};

class InteligenceoSerializer extends AbstractSerializer
{
    use \esperecyan\dictionary_php\internal\ArchiveGenerator;
    
    /** @var string[] キーがひらがな、値が母音の対応表。「っ」「ん」「ゐ」「ゑ」は含みません。 */
    const VOWEL_CORRESPONDENCE_TABLE = [
        'ぁ' => 'あ', 'あ' => 'あ', 'ぃ' => 'い', 'い' => 'い', 'ぅ' => 'う', 'う' => 'う', 'ぇ' => 'え', 'え' => 'え', 'ぉ' => 'お', 'お' => 'お',
        'か' => 'あ', 'が' => 'あ', 'き' => 'い', 'ぎ' => 'い', 'く' => 'う', 'ぐ' => 'う', 'け' => 'え', 'げ' => 'え', 'こ' => 'お', 'ご' => 'お',
        'さ' => 'あ', 'ざ' => 'あ', 'し' => 'い', 'じ' => 'い', 'す' => 'う', 'ず' => 'う', 'せ' => 'え', 'ぜ' => 'え', 'そ' => 'お', 'ぞ' => 'お',
        'た' => 'あ', 'だ' => 'あ', 'ち' => 'い', 'ぢ' => 'い', 'つ' => 'う', 'づ' => 'う', 'て' => 'え', 'で' => 'え', 'と' => 'お', 'ど' => 'お',
        'な' => 'あ', 'に' => 'い', 'ぬ' => 'う', 'ね' => 'え', 'の' => 'お',
        'は' => 'あ', 'ば' => 'あ', 'ぱ' => 'あ', 'ひ' => 'い', 'び' => 'い', 'ぴ' => 'い', 'ふ' => 'う', 'ぶ' => 'う', 'ぷ' => 'う', 'へ' => 'え', 'べ' => 'え', 'ぺ' => 'え', 'ほ' => 'お', 'ぼ' => 'お', 'ぽ' => 'お',
        'ま' => 'あ', 'み' => 'い', 'む' => 'う', 'め' => 'え', 'も' => 'お',
        'ゃ' => 'あ', 'や' => 'あ', 'ゅ' => 'う', 'ゆ' => 'う', 'ょ' => 'お', 'よ' => 'お',
        'ら' => 'あ', 'り' => 'い', 'る' => 'う', 'れ' => 'え', 'ろ' => 'お',
        'ゎ' => 'あ', 'わ' => 'あ', 'を' => 'お',
    ];
    
    /** @var string 辞書の種類。 */
    protected $type;
    
    /** @var bool */
    protected $textFileOnly;
    
    /**
     * @param string $type
     * @param bool $textFileOnly ZIPファイルの代わりにクイズファイルのみを返すときに真に設定します。
     * @throws Exception \DomainException $typeが「Inteligenceω しりとり」「Inteligenceω クイズ」のいずれにも一致しない場合。
     */
    public function __construct(string $type, $textFileOnly = false)
    {
        if ($type !== 'Inteligenceω しりとり' && $type !== 'Inteligenceω クイズ') {
            throw new \DomainException();
        }
        parent::__construct();
        $this->type = $type;
        $this->textFileOnly = $textFileOnly;
    }
    
    /**
     * 解答内のShift_JISに存在しないひらがな、カタカナなどを、Shift_JISに直列化可能なひらがなに置き換えます。
     * また、「ゐ」「ゑ」は「い」「え」に置き換えます。波ダッシュ「〜」は長音符「ー」に置換します。
     * 末尾の「んー」「ゎー」「ーー」についても処理します。
     * @param string $str
     * @return string
     */
    protected function convertToShiftJISableInAnswerWithoutKatakana(string $str): string
    {
        return preg_replace_callback(
            '/(?:ゎー|(?<no_replace_a>(?<sokuon_or_n>[んっ])ー*)ー|(?<no_replace_b>(?<previous>[ぁ-を])ー*)ーー)$/u',
            function (array $matches): string {
                if ($matches[0] === 'ゎー') {
                    $end = 'ゎあ';
                } elseif ($matches['sokuon_or_n']) {
                    $end = $matches['no_replace_a'] . $matches['sokuon_or_n'];
                } else {
                    $end = $matches['no_replace_b'] . self::VOWEL_CORRESPONDENCE_TABLE[$matches['previous']] . 'ー';
                }
                return $end;
            },
            str_replace(
                ['～', 'ゐ', 'ゑ', 'ヴぁ', 'ヴぃ', 'ヴぇ', 'ヴぉ', 'ヴ', 'ヵ', 'ヶ'],
                ['ー', 'い', 'え', 'ば'  , 'び'  , 'べ'  , 'ぼ'  , 'ぶ', 'か', 'け'],
                $this->convertToShiftJISableInAnswer($str)
            )
        );
    }
    
    /**
     * 一つのお題を表す配列をしりとり形式で直列化します。
     * @param (string|string[]|float)[][] $word
     * @return string 末尾に改行 (CRLF) を含みます。直列化できないお題だった場合は空文字列を返します。
     */
    protected function serializeWordAsShiritori(array $word): string
    {
        if (!isset($word['type'][0]) || $word['type'][0] !== 'selection') {
            $answers = preg_filter(
                '/^[ぁ-わを][ぁ-わをんー]*$/u',
                '$0',
                $this->getAnswers($word, [$this, 'convertToShiftJISableInAnswerWithoutKatakana'])
            );
            if ($answers) {
                array_unshift(
                    $answers,
                    str_replace([',', "\n"], ['，', ' '], $this->convertToShiftJISable($word['text'][0]))
                );
                if (isset($word['weight'][0])) {
                    $answers[] = round(1 / $word['weight'][0]);
                }
                if (isset($word['description'][0])) {
                    $answers[] = '@' . str_replace(
                        [',', "\n"],
                        ['，', ' '],
                        $this->convertToShiftJISable($word['description'][0]['lml'])
                    );
                }
                $output = implode(',', $answers) . "\r\n";
            }
        }
        
        if (!isset($output)) {
            $output = '';
            $this->logUnserializableError('Inteligenceω しりとり', $word);
        }
        return $output;
    }
    
    /**
     * クイズ形式における、カンマ区切りの一つのフィールドを直列化します。
     * @param string $field
     */
    protected function serializeQuizField(string $field): string
    {
        return str_replace(['\\', ',', '=', "\n"], ['\\\\', '，', '＝', '\\n'], $this->convertToShiftJISable($field));
    }
    
    /**
     * 一つのお題を表す配列から問題行に直列化します。
     * @param (string|string[]|float)[][] $word
     * @param string $directoryName
     * @param string[] $filenames
     * @return string 末尾に改行 (CRLF) を含みます。
     */
    protected function serializeQuestionLine(array $word, string $directoryName, array $filenames): string
    {
        $line = ['Q'];
        
        if (isset($word['image'][0])) {
            $line[] = 2;
            $fileLocation = $word['image'][0];
        } elseif (isset($word['audio'][0])) {
            $line[] = 1;
            $fileLocation = $word['audio'][0];
        } else {
            $line[] = 0;
        }

        $line[] = isset($word['question'][0]) ? $this->serializeQuizField($word['question'][0]) : '';
        
        if (isset($fileLocation)) {
            $line[] = in_array($fileLocation, $filenames)
                ? "./$directoryName/$fileLocation"
                : str_replace(',', '%2C', $fileLocation);
        }
        
        if (isset($word['specifics'][0])) {
            $specifics = new URLSearchParams($word['specifics'][0]);
            if ($specifics->has('start')) {
                $line[] = 'start=' . round($specifics->get('start') * InteligenceoParser::SECONDS_TO_MILISECONDS);
            }
            if ($specifics->has('repeat')) {
                $line[] = 'repeat=' . $specifics->get('repeat');
            }
            if ($specifics->has('length')) {
                $line[] = 'length=' . round($specifics->get('length') * InteligenceoParser::SECONDS_TO_MILISECONDS);
            }
            if ($specifics->has('speed')) {
                $line[] = 'speed=' . round($specifics->get('speed') * InteligenceoParser::DECIMAL_TO_PERCENT);
            }
            if ($specifics->has('magnification')) {
                $magnification = round($specifics->get('magnification'));
                if ($magnification > 0) {
                    $line[] = "zoom_start=$magnification";
                    if ($specifics->has('last-magnification')) {
                        $lastMagnification = round($specifics->get('last-magnification'));
                        if ($lastMagnification > 0) {
                            $line[] = "zoom_end=$lastMagnification";
                        }
                    }
                }
            }
            if ($specifics->has('pixelization')) {
                $line[] = 'mozaic=1';
            }
            if ($specifics->has('score')) {
                $line[] = 'score=' . $specifics->get('score');
                if ($specifics->has('last-score')) {
                    $line[] = 'finalscore=' . $specifics->get('last-score');
                }
            }
        }
        
        return implode(',', $line) . "\r\n";
    }
    
    /**
     * 解答内のShift_JISに存在しないひらがな、カタカナなどを、Shift_JISに直列化可能なひらがな・カタカナに出来る限り置き換えます。
     * @param string $str
     * @return string
     */
    protected function convertToShiftJISableInAnswerWithKatakana(string $str): string
    {
        return str_replace(
            ['\\'  , ',' , '=' ,'〜', 'ゔ', 'ヷ'  , 'ヸ'  , 'ヹ'  , 'ゕ', 'ゖ', 'ㇰ', 'ㇱ', 'ㇲ', 'ㇳ', 'ㇴ', 'ㇵ', 'ㇶ', 'ㇷ', 'ㇸ', 'ㇹ', 'ㇻ', 'ㇼ', 'ㇽ', 'ㇾ', 'ㇿ', 'ㇺ', "\u{1B000}", "\u{1B001}"],
            ['\\\\', '，', '＝', '～', 'ヴ', 'ヴァ', 'ヴィ', 'ヴェ', 'ヵ', 'ヶ', 'ク', 'シ', 'ス', 'ト', 'ヌ', 'ハ', 'ヒ', 'フ', 'ヘ', 'ホ', 'ラ', 'リ', 'ル', 'レ', 'ロ', 'ム', 'エ'       , 'え'       ],
            $str
        );
    }
    
    /**
     * 一つのお題を表す配列から解答行に直列化します。
     * @param (string|string[]|float)[][] $word
     * @return string 末尾に改行 (CRLF) を含みます。
     */
    protected function serializeAnswerLine(array $word): string
    {
        $line = ['A'];
        
        $specifics = new URLSearchParams($word['specifics'][0] ?? '');
        
        if (isset($word['option'][0])) {
            if (isset($word['type'][0]) && $word['type'][0] === 'selection' && empty($word['answer'])) {
                $line[] = 2;
            } elseif ($specifics->has('require-all-right')) {
                $line[] = 3;
            } else {
                $line[] = 1;
            }
            
            $answers = isset($word['answer'][0]) ? $word['answer'] : $word['text'];
            foreach ($word['option'] as $i => $option) {
                $line[] = $this->serializeQuizField($option);
                if ($line[1] === 2) {
                    $line[] = $i + 1;
                } elseif (in_array($option, $answers)) {
                    $line[] = '\\seikai';
                }
            }
            
            if ($specifics->has('no-random')) {
                $line[] = '\\norandom';
            }
        } else {
            $line[] = 0;
            
            $answers = $this->getAnswers($word, [$this, 'convertToShiftJISableInAnswerWithKatakana']);
            if (!$answers) {
                $this->logUnserializableError('Inteligenceω クイズ', $word);
                return '';
            }
            
            $bonuses = $specifics->getAll('bonus');
            foreach ($answers as $i => $answer) {
                $line[] = $answer;
                if (!empty($bonuses[$i])) {
                    $line[] = "\\bonus=$bonuses[$i]";
                }
            }
        }
        
        $explain[] = $word['text'][0];
        if (isset($word['description'][0])) {
            $explain[] = $word['description'][0]['lml'];
        }
        $line[] = '\\explain=' . $this->serializeQuizField(implode("\n\n", $explain));
        
        return implode(',', $line) . "\r\n";
    }
    
    /**
     * 一つのお題を表す配列をクイズ形式で直列化します。
     * @param (string|string[]|float)[][] $word
     * @param string $directoryName
     * @param string[] $filenames
     * @return string 末尾に改行 (CRLF) を含みます。直列化できないお題だった場合は空文字列を返します。
     */
    protected function serializeWordAsQuiz(array $word, string $directoryName, array $filenames): string
    {
        if (isset($word['question'][0])
            || isset($word['image'][0]) || isset($word['audio'][0]) || isset($word['option'][0])) {
            $answerLine = $this->serializeAnswerLine($word);
            if ($answerLine !== '') {
                $output = $this->serializeQuestionLine($word, $directoryName, $filenames) . $answerLine;
            }
        }
        return $output ?? '';
    }
    
    /**
     * @param Dictionary $dictionary
     * @throws \BadMethodCallException $this->textFileOnly が偽、かつ「画像・音声・動画ファイルを含む場合のファイル形式」をCSVファイルのみで構文解析していた場合。
     * @throws EmptyOutputException 該当の辞書形式に変換可能なお題が一つも存在しなかった。
     * @return string[]
     */
    public function serialize(Dictionary $dictionary): array
    {
        $directoryName = (new \esperecyan\dictionary_php\validator\FilenameValidator())
            ->convertToValidFilenameWithoutExtensionInArchives($dictionary->getTitle());
        $filenames = $dictionary->getFilenames();
        
        foreach ($dictionary->getWords() as $word) {
            $serialized = $this->type === 'Inteligenceω しりとり'
                ? $this->serializeWordAsShiritori($word)
                : $this->serializeWordAsQuiz($word, $directoryName, $filenames);
            if ($serialized !== '') {
                $words[] = $serialized;
            }
        }
        
        if (empty($words)) {
            throw new EmptyOutputException(sprintf(_('%sの辞書形式に変換可能なお題が見つかりませんでした。'), $this->type));
        }
        
        $previousSubstituteCharacter = mb_substitute_character();
        mb_substitute_character(\IntlChar::ord(self::SUBSTITUTE_CHARACTER));
        $bytes = mb_convert_encoding(
            $this->serializeMetadata($dictionary, '%') . implode('', $words),
            'Windows-31J',
            'UTF-8'
        );
        mb_substitute_character($previousSubstituteCharacter);
        
        $files = $dictionary->getFiles();
        if (!$files && !$this->textFileOnly && $filenames) {
            throw new \BadMethodCallException();
        } elseif ($this->type === 'Inteligenceω クイズ' && $files && !$this->textFileOnly) {
            $archive = $this->generateArchive();
            foreach ($files as $file) {
                $archive->addFile($file, "$directoryName/" . $file->getFilename());
            }
            $archive->addFromString("$directoryName.txt", $bytes);
            $archivePath = $archive->filename;
            $archive->close();
            
            return [
                'bytes' => file_get_contents($archivePath),
                'type' => 'application/zip',
                'name' => $this->getFilename($dictionary, 'zip'),
            ];
        } else {
            return [
                'bytes' => $bytes,
                'type' => 'text/plain; charset=Shift_JIS',
                'name' => $this->getFilename($dictionary, 'txt'),
            ];
        }
    }
}
