<?php
namespace esperecyan\dictionary_php\parser;

use esperecyan\dictionary_php\{Dictionary, exception\SyntaxException};

class PictsenseParser extends AbstractParser
{
    /** @var 辞書名の最大文字数。ただし補助文字は2文字分。 */
    const TITLE_MAX = 30;
    
    /** @var お題の最大文字数。 */
    const WORD_MAX = 32;
    
    /** @var お題の最大数。 */
    const WORDS_MAX = 500;
    
    /** @var お題の最小数。 */
    const WORDS_MIN = 5;
    
    /** @var 辞書全体の最大文字数。 */
    const DICTIONARY_CODE_POINTS_MAX = 5000;
    
    /**
     * 文字列の長さを取得します。ただし補助文字は2文字分。
     * @param string $codePoints
     * @return int
     */
    public function getLengthAs16BitCodeUnits(string $codePoints): int
    {
        return mb_strlen(bin2hex(mb_convert_encoding($codePoints, 'UTF-16LE', 'UTF-8')), 'UTF-8') / 4;
    }
    
    /**
     * すべての符号位置がピクトセンスで使用可能であれば真を返します。
     * @param string $line
     * @return bool
     */
    public function isHiraganaCodePoints(string $line): bool
    {
        return preg_match('/^[ぁ-んヴー]+$/u', $line) === 1;
    }
    
    /**
     * 行を解析します。
     * @param string $line
     * @throws SyntaxException ひらがな以外が含まれている。字数制限を超えている。
     * @return string
     */
    protected function parseLine(string $line): string
    {
        if (!$this->isHiraganaCodePoints($line)) {
            throw new SyntaxException(_('ピクトセンスで使用可能の文字はひらがな、「ヴ」「ー」のみです。ただし、「ゔ」「ゕ」「ゖ」「𛀁」も使用できません: ') . $line);
        }
        
        if (mb_strlen($line, 'UTF-8') > self::WORD_MAX) {
            throw new SyntaxException(sprintf(_('「%1$s」は%2$d文字を越えています。'), $line, self::WORDS_MAX));
        }

        return str_replace('ヴ', 'ゔ', $line);
    }
    
    /**
     * @param \SplFileInfo $file
     * @param string|null $filename
     * @param string|null $title
     * @throws SyntaxException お題の数、辞書全体の文字数が制限範囲外であるとき。
     * @return Dictionary
     */
    public function parse(\SplFileInfo $file, string $filename = null, string $title = null): Dictionary
    {
        $dictionary = new Dictionary();
        $words = [];
        
        if (!($file instanceof \SplFileObject)) {
            $file = $file->openFile();
        } else {
            $file->rewind();
        }
        $file->setFlags(\SplFileObject::DROP_NEW_LINE | \SplFileObject::READ_AHEAD | \SplFileObject::SKIP_EMPTY);
        foreach ($file as $line) {
            $word = $this->parseLine($line);
            if (!in_array($word, $words)) {
                $dictionary->addWord(['text' => [str_replace('ヴ', 'ゔ', $line)]]);
                $words[] = $word;
            }
        }
        
        $wordsLength = count($words);
        if ($wordsLength < self::WORDS_MIN) {
            throw new SyntaxException(sprintf(_('お題が%1$d個しかありません。%2$d個以上必要です。'), $wordsLength, self::WORDS_MIN));
        }
        
        if ($wordsLength > self::WORDS_MAX) {
            throw new SyntaxException(sprintf(_('お題が%1$d個あります。%2$d個以内にする必要があります。'), $wordsLength, self::WORDS_MAX));
        }
        
        $dictionaryCodePoints = mb_strlen(implode('', $words), 'UTF-8');
        if ($dictionaryCodePoints > self::DICTIONARY_CODE_POINTS_MAX) {
            throw new SyntaxException(sprintf(
                _('辞書全体で%1$d文字あります。%2$d文字以内にする必要があります。'),
                $dictionaryCodePoints,
                self::DICTIONARY_CODE_POINTS_MAX
            ));
        }
        
        if (!is_null($title) || !is_null($filename)) {
            if (!is_null($title) && $title !== '') {
                $trimedTitle = preg_replace('/^[ 　\\t]+|[ 　\\t]+$/u', '', $title);
            }

            if (!is_null($filename) && (!isset($trimedTitle) || $trimedTitle === '')) {
                $trimedTitle = preg_replace(
                    '/^[ 　\\t]+|[ 　\\t]+$/u',
                    '',
                    (new GenericDictionaryParser())->getTitleFromFilename($filename)
                );
            }
            
            if (isset($trimedTitle) && $trimedTitle !== '') {
                $dictionary->setMetadata(['@title' => $trimedTitle]);
                $titleLength = $this->getLengthAs16BitCodeUnits($trimedTitle);
                if ($titleLength > self::TITLE_MAX) {
                    $this->logger->error(sprintf(
                        _('辞書名が%1$d文字 (補助文字は2文字扱い) あります。ピクトセンスにおける辞書名の最大文字数は%2$d文字です。'),
                        $titleLength,
                        self::TITLE_MAX
                    ));
                }
            } else {
                $this->logger->error(_('辞書名が空です。先頭末尾の空白は取り除かれます。'));
            }
        }
        
        return $dictionary;
    }
}
