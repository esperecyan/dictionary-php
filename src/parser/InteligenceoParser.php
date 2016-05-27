<?php
namespace esperecyan\dictionary_php\parser;

use esperecyan\dictionary_php\{Dictionary, exception\SyntaxException};
use esperecyan\url\URLSearchParams;

class InteligenceoParser extends AbstractParser
{
    /** @var int 秒をミリ秒に変換するときの乗数。 */
    const SECONDS_TO_MILISECONDS = 1000;
    
    /** @var int 比率を百分率に変換するときの乗数。 */
    const DECIMAL_TO_PERCENT = 1000;
    
    /** @var string|null 辞書の種類。 */
    protected $type = null;
    
    /** @var int レベルをweightフィールド値に変換する際の、小数点以下の最大桁数。 */
    const SCALE = 6;
    
    public function __construct($type = null)
    {
        if ($type === 'Inteligenceω しりとり' || $type === 'Inteligenceω クイズ') {
            $this->type = $type;
        }
    }
    
    
    /**
     * Inteligenceωにおいて数値として扱われる文字列であれば真を返します。
     * @param string $input
     * @return bool
     */
    protected function isNumeric(string $input): bool
    {
        return is_numeric(trim($input));
    }
    
    /**
     * Inteligenceωにおいて数値として扱われる文字列を整数に変換します。
     * @param string $input
     * @return int
     */
    protected function convertToInt(string $input): int
    {
        return (int)(float)$input;
    }
    
    /**
     * しりとり辞書の行を解析します。
     * プレフィックス、サフィックスの組み合わせの結果がすべて空文字列になる時はnullを返します。
     * @param Dictionary $dictionary
     * @param string $line
     * @throws SyntaxException 2つ目のフィールド数値の場合。読み方にひらがな以外が含まれている場合。読み方が設定されていない場合。
     */
    protected function parseShiritoriLine(Dictionary $dictionary, string $line)
    {
        $fields = explode(',', $line);
        
        if (isset($fields[1]) && $this->isNumeric($fields[1])) {
            throw new SyntaxException(sprintf(_('2列目に数値「%s」は含められません。'), $fields[1]));
        }
        
        $fieldsAsMultiDimensionalArray['text'][] = array_shift($fields);
        
        $answerPattern = [];
        $mode = '|';
        foreach ($fields as $field) {
            if ($this->isNumeric($field)) {
                // レベル
                $int = $this->convertToInt($field);
                if ($int > 0) {
                    $weight = rtrim(bcdiv('1', $int, self::SCALE), '0.');
                    if ($weight !== '') {
                        $fieldsAsMultiDimensionalArray['weight'][0] = $weight;
                    }
                }
                continue;
            }
            
            if ($field !== '' && $field[0] === '@') {
                // 解説
                $fieldsAsMultiDimensionalArray['description'][0] = substr($field, 1);
                continue;
            }
            
            if (in_array($field, ['[', '|', ']'])) {
                // モード変更
                $mode = $field;
                continue;
            }
            
            if (preg_match('/^[ぁ-んー]*$/u', $field) !== 1) {
                throw new SyntaxException(sprintf(_('「%s」には、ひらがな以外が含まれています。'), $field[1]));
            }
            
            $answerPattern[$mode][] = $field;
        }
        
        if (empty($answerPattern['|'])) {
            throw new SyntaxException(sprintf(_('行「%s」には読み方が設定されていません。'), $line));
        }
        
        foreach ($answerPattern['|'] as $infix) {
            // 接頭辞の付加
            if (isset($answerPattern['['])) {
                foreach ($answerPattern['['] as $prefix) {
                    $answersWithPrefix[] = $prefix . $infix;
                }
            } else {
                $answersWithPrefix[] = $infix;
            }
            
            // 接尾辞の付加
            if (isset($answerPattern[']'])) {
                foreach ($answerPattern[']'] as $suffix) {
                    foreach ($answersWithPrefix as $answer) {
                        $answersWithPrefixAndSuffix[] = $answer . $suffix;
                    }
                }
            } else {
                $answersWithPrefixAndSuffix = $answersWithPrefix;
            }
        }
        
        // 重複、空行の削除
        $fieldsAsMultiDimensionalArray['answer'] = array_filter(array_unique($answersWithPrefixAndSuffix));
        
        if ($fieldsAsMultiDimensionalArray['answer']) {
            if ($fieldsAsMultiDimensionalArray['text'][0] === '') {
                $fieldsAsMultiDimensionalArray['text'][0] = $fieldsAsMultiDimensionalArray['answer'][0];
            }

            $dictionary->addWord($fieldsAsMultiDimensionalArray);
            $words = $dictionary->getWords();
            $this->wholeText .= implode('', $words[count($words) - 1]['answer']);
        }
    }
    
    /** @var string|null */
    protected $question = null;
    
    /** @var string|null */
    protected $answers = null;
    
    /**
     * 問題文、または解説文の「\n」を改行に置き換えます。
     * @param string $question
     * @return string
     */
    protected function parseQuestionSentence(string $question): string
    {
        return preg_replace('/(?<!\\\\)((?:\\\\\\\\)*)\\\\n/u', "$1\n", $question);
    }
    
    /**
     * Q&Aを解析します。
     * @param Dictionary $dictioanry
     * @param string $question
     * @param string $answer
     */
    protected function parseQuizLines(Dictionary $dictioanry, string $question, string $answer)
    {
        $specifics = new URLSearchParams();

        $questionFields = explode(',', $question, 5);
        $answerFields = explode(',', $answer);
        
        if (!$this->isNumeric($questionFields[1])) {
            throw new SyntaxException(sprintf(_('出題の種類「%s」は数値として認識できません。'), $questionFields[1]));
        } elseif (!$this->isNumeric($answerFields[1])) {
            throw new SyntaxException(sprintf(_('解答の種類「%s」は数値として認識できません。'), $answerFields[1]));
        }
        
        /** @var int 出題の種類。 */
        $questionType = $this->convertToInt($questionFields[1]);
        switch ($questionType) {
            case 1:
                // 音声ファイルを再生
            case 2:
                // 画像ファイルを表示
                if (empty($questionFields[3])) {
                    throw new SyntaxException(_('ファイルが指定されていません。'));
                }
                // ファイル名
                $fieldsAsMultiDimensionalArray[$questionType === 1 ? 'audio' : 'image'][] = $questionFields[3];
                // 問題オプション
                if (isset($questionFields[4])) {
                    foreach (new URLSearchParams(str_replace(',', '&', $questionFields[4])) as $name => $value) {
                        if (!$this->isNumeric($value)) {
                            throw new SyntaxException(sprintf(_('問題オプション %1s の値「%2s」は数値として認識できません。'), $name, $value));
                        }
                        $int = $this->convertToInt($value);
                        switch ($name) {
                            case 'start':
                            case 'media_start':
                                $specifics->set('start', $int / self::SECONDS_TO_MILISECONDS);
                                break;
                            case 'repeat':
                                $specifics->set('repeat', $int);
                                break;
                            case 'length':
                                $specifics->set('length', $int / self::SECONDS_TO_MILISECONDS);
                                break;
                            case 'speed':
                                $specifics->set('speed', $int / self::DECIMAL_TO_PERCENT);
                                break;
                            case 'zoom_start':
                                $specifics->set('magnification', $int);
                                break;
                            case 'zoom_end':
                                $specifics->set('last-magnification', $int);
                                break;
                            case 'mozaic':
                                if ($int === 1) {
                                    $specifics->set('no-pixelization', '');
                                } else {
                                    $specifics->delete('no-pixelization');
                                }
                                break;
                            case 'score':
                                $specifics->set('score', $int);
                                break;
                            case 'finalscore':
                                $specifics->set('last-score', $int);
                                break;
                        }
                    }
                }
                break;
            
            case 3:
                // Wikipediaクイズ
            case 4:
                // アンサイクロペディアクイズ
                return;
        }
        
        // 問題文
        if (isset($questionFields[2])) {
            $fieldsAsMultiDimensionalArray['question'][] = $this->parseQuestionSentence($questionFields[2]);
        }
        
        /** @var int 解答の種類。 */
        $answerType = $this->convertToInt($answerFields[1]);
        if (in_array($answerType, [1, 2, 3])) {
            $fieldsAsMultiDimensionalArray['type'][] = 'selection';
            if ($answerType === 3) {
                $specifics->set('require-all-right', '');
            }
        } else {
            $answerType = 0;
        }
        
        $mode = '|';
        $answerValidator = new \esperecyan\dictionary_php\validator\AnswerValidator();
        foreach (array_slice($answerFields, 2) as $i => $field) {
            if (isset($field[0]) && $field[0] === '\\' && isset($field[1]) && $field[1] !== '\\') {
                // 解答オプション
                if ($field === '\\norandom') {
                    // 選択肢をシャッフルしない
                    if (in_array($answerType, [1, 3])) {
                        $specifics->set('no-random', '');
                    }
                } elseif ($field === '\\seikai') {
                    // 直前の選択肢を正解扱いに
                    if (in_array($answerType, [1, 3])) {
                        if (empty($fieldsAsMultiDimensionalArray['option'])) {
                            throw new SyntaxException(_('\\seikai の前には選択肢が必要です。'));
                        }
                        $fieldsAsMultiDimensionalArray['answer'][] = end($fieldsAsMultiDimensionalArray['option']);
                    }
                } elseif (preg_match('/^\\\\explain=(.+)$/u', $field, $matches) === 1) {
                    // 解説
                    $fieldsAsMultiDimensionalArray['description'][] = $this->parseQuestionSentence($matches[1]);
                } elseif ($answerType === 0 && preg_match('/^\\\\bonus=(.*)$/u', $field, $matches) === 1) {
                    // ボーナスポイント
                    if (!$this->isNumeric($matches[1])) {
                        throw new SyntaxException(sprintf(_('解答オプション \\bonus の値「%s」は数値として認識できません。'), $matches[1]));
                    }
                    if (empty($answerPattenAndBonuses['|'])) {
                        throw new SyntaxException(sprintf(_('解答オプション「%s」の前には解答本体が必要です。'), $field));
                    }
                    $bonus = $this->convertToInt($matches[1]);
                    if ($bonus !== 0) {
                        $answerPattenAndBonuses['|'][count($answerPattenAndBonuses['|']) - 1]['bonus'] = $bonus;
                    }
                }
                continue;
            }
        
            switch ($answerType) {
                case 0:
                    // 記述形式
                    if ($field === '[[' || $field === '||') {
                        // 正規表現
                        if (empty($answerPattenAndBonuses['|'])) {
                            throw new SyntaxException(sprintf(_('「%s」の前には解答本体が必要です。'), $field));
                        }
                        $end = count($answerPattenAndBonuses['|']) - 1;
                        $answerPattenAndBonuses['|'][$end]['regexp'] = ($field === '||' ? '.*' : '')
                            . preg_quote($answerPattenAndBonuses['|'][$end]['body'], '/') . '.*';
                    } elseif (in_array($field, ['[', '|', ']'])) {
                        // モード変更
                        $mode = $field;
                    } elseif ($mode === '|') {
                        $answerPattenAndBonuses['|'][]['body'] = $field;
                    } else {
                        $answerPattenAndBonuses[$mode][] = $field;
                    }
                    break;

                case 1:
                    // 選択形式
                case 3:
                    // 全選択形式
                    $fieldsAsMultiDimensionalArray['option'][]
                        = $answerValidator->isRegExp($field) ? trim($field, '/') : $field;
                    break;

                case 2:
                    // 並べ替え形式
                    if ($i % 2 === 0) {
                        $numbersAndOptions[] = [$answerValidator->isRegExp($field) ? trim($field, '/') : $field];
                    } else {
                        $number = $this->isNumeric($field) ? $this->convertToInt($field) : 0;
                        if ($number > 0) {
                            array_unshift($numbersAndOptions[count($numbersAndOptions) - 1], $number);
                        } else {
                            // 順番が0以下であれば選択肢自体を削除
                            array_pop($numbersAndOptions);
                        }
                    }
                    break;
            }
        }
        
        switch ($answerType) {
            case 0:
                // 記述形式
                $noRegExpAnswerExisted = false;
                foreach ($answerPattenAndBonuses['|'] as $infix) {
                    // 接頭辞の付加
                    if (isset($answerPattenAndBonuses['['])) {
                        foreach ($answerPattenAndBonuses['['] as $prefix) {
                            $tmpInfix = $infix;
                            $tmpInfix['body'] = $prefix . $tmpInfix['body'];
                            if (isset($tmpInfix['regexp'])) {
                                $tmpInfix['regexp'] = preg_quote($prefix, '/') . $tmpInfix['regexp'];
                            }
                            $answersAndBonusesWithPrefix[] = $tmpInfix;
                        }
                    } else {
                        $answersAndBonusesWithPrefix[] = $infix;
                    }

                    // 接尾辞の付加
                    if (isset($answerPattenAndBonuses[']'])) {
                        foreach ($answerPattenAndBonuses[']'] as $suffix) {
                            foreach ($answersAndBonusesWithPrefix as $answer) {
                                $answer['body'] .= $suffix;
                                if (isset($answer['regexp'])) {
                                    $answer['regexp'] .= preg_quote($prefix, '/');
                                }
                                $answersAndBonusesWithPrefixAndSuffix[] = $answer;
                            }
                        }
                    } else {
                        $answersAndBonusesWithPrefixAndSuffix = $answersAndBonusesWithPrefix;
                    }
                    unset($answersAndBonusesWithPrefix);
                    
                    foreach ($answersAndBonusesWithPrefixAndSuffix as &$answer) {
                        if ($answerValidator->isRegExp($answer['body'])) {
                            $answer['body'] = trim($answer['body'], '/');
                            if ($answer['body'] === '') {
                                unset($answer['body']);
                            }
                        }
                        
                        if (isset($answer['regexp']) || isset($answer['body'])) {
                            $fieldsAsMultiDimensionalArray['answer'][]
                                = isset($answer['regexp']) ? "/$answer[regexp]/" : $answer['body'];
                            if (isset($answer['regexp'])) {
                                if (!isset($noRegExpAnswerAndBonus)) {
                                    $noRegExpAnswerAndBonus = [
                                        'answer' => $answer['body'],
                                        'bonus' => isset($answer['bonus']) ? (string)$answer['bonus'] : '',
                                    ];
                                }
                            } else {
                                $noRegExpAnswerExisted = true;
                            }
                            $bonuses[] = isset($answer['bonus']) ? (string)$answer['bonus'] : '';
                        }
                    }
                    unset($answersAndBonusesWithPrefixAndSuffix);
                }
                    
                if (!$noRegExpAnswerExisted) {
                    // answerフィールドがすべて正規表現なら
                    if (isset($noRegExpAnswerAndBonus)) {
                        array_unshift($fieldsAsMultiDimensionalArray['answer'], $noRegExpAnswerAndBonus['answer']);
                        array_unshift($bonuses, $noRegExpAnswerAndBonus['bonus']);
                    } else {
                        return;
                    }
                }

                // specificsフィールドのbonusの設定
                if (isset($bonuses)) {
                    foreach (array_reverse($bonuses, true) as $i => $bonus) {
                        if ($bonus) {
                            $lastBonusPosition = $i;
                            break;
                        }
                    }
                }
                if (isset($lastBonusPosition)) {
                    foreach (array_slice($bonuses, 0, $lastBonusPosition + 1) as $bonus) {
                        $specifics->append('bonus', $bonus);
                    }
                }
                
                $fieldsAsMultiDimensionalArray['text'][] = $fieldsAsMultiDimensionalArray['answer'][0];
                if (count($fieldsAsMultiDimensionalArray['answer']) === 1) {
                    unset($fieldsAsMultiDimensionalArray['answer']);
                }
                break;

            case 1:
                // 選択形式
            case 3:
                // 全選択形式
                if (empty($fieldsAsMultiDimensionalArray['answer'])) {
                    throw new SyntaxException(_('\\seikai が設定されていません。'));
                }
                $fieldsAsMultiDimensionalArray['text'][] = count($fieldsAsMultiDimensionalArray['answer']) === 1
                    ? $fieldsAsMultiDimensionalArray['answer'][0]
                    : '「' . implode('」' . ($answerType === 1 ? 'か' : 'と') . '「', $fieldsAsMultiDimensionalArray['answer']) . '」';
                break;
                
            case 2:
                // 並べ替え形式
                if (isset($numbersAndOptions)) {
                    if (count(end($numbersAndOptions)) === 1) {
                        // 順番が指定されていない選択肢を削除
                        array_pop($numbersAndOptions);
                    }
                    sort($numbersAndOptions);
                    $fieldsAsMultiDimensionalArray['option'] = array_column($numbersAndOptions, 1);
                
                    $fieldsAsMultiDimensionalArray['text'][] = implode(' → ', $fieldsAsMultiDimensionalArray['option']);
                }
                break;
        }
        
        $encoded = (string)$specifics;
        if ($encoded !== '') {
            $fieldsAsMultiDimensionalArray['specifics'][] = $encoded;
        }
        
        try {
            $dictioanry->addWord($fieldsAsMultiDimensionalArray);
            if ($answerType === 0) {
                // 記述形式
                $words = $dictioanry->getWords();
                $word = $words[count($words) - 1];
                if (isset($word['answer'])) {
                    foreach ($word['answer'] as $answer) {
                        $this->wholeText .= $answerValidator->isRegExp($answer)
                            ? preg_replace('#^/|\\.\\*|/$#u', '', $answer)
                            : $answer;
                    }
                } else {
                    $this->wholeText .= $word['text'][0];
                }
            }
        } catch (SyntaxException $e) {
        }
    }
    
    /**
     * 各回答形式で共通のオプションを解析します。
     * @param URLSearchParams $params
     * @param string $field
     * @return bool 解答オプションであれば真。
     */
    protected function parseAnswerOption(URLSearchParams $params, string $field): bool
    {
        if ($field === '\\norandom') {
            $params->set('no-random', '');
        } elseif (preg_match('/^\\explain=(.+)$', $field, $matches) === 1) {
            $params->set('description', $matches[1]);
        }
    }
    
    /**
     * 問題行を解析します。
     * @param Dictionary $dictionary
     * @param string $question
     * @throws SyntaxException すでに未解析の問題行が存在していた場合。
     */
    protected function parseQuestionLine(Dictionary $dictionary, string $question = null)
    {
        if ($this->answers) {
            // 未解析の解答が存在する場合
            $this->parseQuizLines($dictionary, $this->question, $this->answers);
            $this->question = null;
            $this->answers = null;
        } elseif ($this->question) {
            // 未解析の問題が存在する場合
            throw new SyntaxException($question ? _('「Q,」で始まる行が連続しています。') : _('辞書は「A,」で始まる行で終わらせなければなりません。'));
        }
        $this->question = $question;
    }
    
    /**
     * 問題行を解析します。
     * @param string $answer
     * @throws SyntaxException 未解析の問題行が存在していない場合。
     */
    protected function parseAnswerLine(string $answer)
    {
        if (is_null($this->question)) {
            throw new SyntaxException(_('辞書は「Q,」で始めなければなりません。'));
        } elseif ($this->answers) {
            $this->answers .= ",$answer";
        } else {
            $this->answers = $answer;
        }
    }
    
    /**
     * 行を解析します。
     * @param Dictionary $dictionary
     * @param string|null ファイル終端ならnull。
     * @throws SyntaxException クイズ辞書で、コメント、問題、解答のいずれでもない行があれば
     * @return Word|null
     */
    protected function parseLine(Dictionary $dictionary, string $line = null)
    {
        if (is_null($line)) {
            if ($this->type === 'Inteligenceω クイズ') {
                $output = $this->parseQuestionLine($dictionary, null);
            }
        } elseif ($line[0] !== '%') {
            // 空行でなければ
            if (is_null($this->type)) {
                // 辞書の種類が与えられてなければ
                $this->type = stripos($line, 'Q,') === 0 ? 'Inteligenceω クイズ' : 'Inteligenceω しりとり';
            }
            
            if ($this->type === 'Inteligenceω しりとり') {
                // しりとり辞書なら
                $output = $this->parseShiritoriLine($dictionary, $line);
            } elseif (stripos($line, 'Q,') === 0) {
                // 問題行なら
                $output = $this->parseQuestionLine($dictionary, $line);
            } elseif (stripos($line, 'A,') === 0) {
                // 解答行なら
                $this->parseAnswerLine($line);
            } else {
                throw new SyntaxException(_('壊れた行が含まれています:') . "\n" . $line);
            }
        }
        
        return $output ?? null;
    }
    
    /**
     * ファイル名から辞書のタイトルを取得します。
     * @param string $filename
     * @return string|null
     */
    protected function getTitleFromFilename(string $filename)
    {
        $withoutExtension = pathinfo($filename, PATHINFO_FILENAME);
        return $withoutExtension !== '' ? $withoutExtension : $filename;
    }

    public function parse(\SplFileInfo $file, string $filename = null, string $title = null): Dictionary
    {
        $dictionary = new Dictionary();
        
        if (!($file instanceof \SplFileObject)) {
            $file = $file->openFile();
        } else {
            $file->rewind();
        }
        $file->setFlags(\SplFileObject::DROP_NEW_LINE | \SplFileObject::READ_AHEAD | \SplFileObject::SKIP_EMPTY);
        foreach ($file as $line) {
            $this->parseLine($dictionary, $line);
        }
        $this->parseLine($dictionary);
        
        if (!$dictionary->getWords()) {
            throw new SyntaxException(_('正常に変換可能な行が見つかりませんでした。'));
        }
        
        if ($this->wholeText !== '') {
            $regard = $this->generateRegard();
            if ($regard) {
                $metaFields['@regard'] = $this->generateRegard();
            }
        }
        if (!is_null($title)) {
            $metaFields['@title'] = $title;
        } elseif (!is_null($filename)) {
            $titleFromFilename = $this->getTitleFromFilename($filename);
            if ($titleFromFilename) {
                $metaFields['@title'] = $titleFromFilename;
            }
        }
        if (isset($metaFields)) {
            $dictionary->setMetadata($metaFields);
        }
        
        return $dictionary;
    }
}
