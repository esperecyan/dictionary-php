<?php
namespace esperecyan\dictionary_api\parser;

use esperecyan\dictionary_api\internal\Dictionary;
use esperecyan\dictionary_api\internal\Word;
use esperecyan\dictionary_api\exception\SyntaxException;

class CatchmParser extends AbstractParser
{
    /**
     * 行を解析し、text、answer、descriptionフィールドに対応する文字列を取り出します。
     * @param string $line
     * @return Word|null
     */
    protected function parseLine(string $line)
    {
        // コメントの分離
        $textAndDescription = preg_split('#(/+|;+|\\[)#u', $line, 2, PREG_SPLIT_DELIM_CAPTURE);
        
        if ($textAndDescription[0] !== '' && str_replace(' ', '', $textAndDescription[0]) === '') {
            // 空行でなく、半角スペースのみで構成されている行なら
            throw new SyntaxException(_('半角スペースとコメントのみの行は作ることができません。'));
        }
        
        $answers = array_filter(array_map(function ($answer) {
            // 正規表現文字列扱いを抑止
            return (new \esperecyan\dictionary_api\validator\AnswerValidator())->isRegExp($answer)
                ? trim($answer, '/')
                : $answer;
        }, explode(',', rtrim($textAndDescription[0], ' '))));
        if ($answers) {
            if (count($answers) === 1) {
                $fieldsAsMultiDimensionalArray['text'] = $answers;
            } else {
                $fieldsAsMultiDimensionalArray = [
                    'text' => [$answers[0]],
                    'answer' => $answers,
                ];
            }

            if (isset($textAndDescription[1])) {
                $comment = trim(
                    $textAndDescription[1] === '[' ? rtrim($textAndDescription[2], ']') : $textAndDescription[2]
                );
                if ($comment !== '') {
                    $fieldsAsMultiDimensionalArray['description'][] = $comment;
                }
            }

            $word = new Word();
            try {
                $word->setFieldsAsMultiDimensionalArray($fieldsAsMultiDimensionalArray);
            } catch (SyntaxException $e) {
                $word = null;
            }

            if ($word) {
                $this->wholeText .= $word->getFieldsAsMultiDimensionalArray()['text'][0];
            }
        } else {
            $word = null;
        }
        
        return $word;
    }
    
    /**
     * ファイル名から辞書のタイトルを取得します。
     * @param string $filename
     * @return string|null
     */
    protected function getTitleFromFilename(string $filename)
    {
        return preg_match('/^\\s*(.+?)\\s*\\.dat$/u', $filename, $matches) === 1 ? $matches[1] : null;
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
            $word = $this->parseLine($line);
            if ($word) {
                $dictionary->addWord($word);
            }
        }
        
        if ($this->wholeText === '') {
            throw new SyntaxException(_('空の辞書です。'));
        }
        
        $metaFields['@regard'] = [$this->generateRegard()];
        if (!is_null($title)) {
            $metaFields['@title'] = [$title];
        } elseif (!is_null($filename)) {
            $titleFromFilename = $this->getTitleFromFilename($filename);
            if ($titleFromFilename) {
                $metaFields['@title'] = [$titleFromFilename];
            }
        }
        $dictionary->setMetaFields($metaFields);
        
        return $dictionary;
    }
}
