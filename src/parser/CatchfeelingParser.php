<?php
namespace esperecyan\dictionary_php\parser;

use esperecyan\dictionary_php\{Dictionary, internal\Word, exception\SyntaxException};

class CatchfeelingParser extends AbstractParser
{
    /**
     * 行を解析し、textフィールドとdescriptionフィールドに対応する文字列を取り出します。
     * @param string $line
     * @return Word|null
     */
    protected function parseLine(string $line)
    {
        // コメントの分離
        $textAndDescription = preg_split('#[\\t 　]*//#u', $line, 2);
        
        $text = (new \esperecyan\dictionary_php\validator\AnswerValidator())->isRegExp($textAndDescription[0])
            ? trim($textAndDescription[0], '/') // 正規表現文字列扱いを抑止
            : $textAndDescription[0];
        
        if (str_replace(["\t", ' ', '　'], '', $text) === '') {
            throw new SyntaxException(_('空行 (スペース、コメントのみの行) があります。'));
        } else {
            $fieldsAsMultiDimensionalArray['text'] = [$text];
            
            if (isset($textAndDescription[1])) {
                // コメントが存在すれば
                $description = trim($textAndDescription[1], " 　\t");
                if ($description !== '') {
                    $fieldsAsMultiDimensionalArray['description'] = [$description];
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
        return preg_match(
            '/^\\s*(.+?)(?:(?:\\s*(?:\\[.+?]))*|(?:\\s*(?:{.+?}))*)\\s*\\.cfq$/u',
            $filename,
            $matches
        ) === 1 ? $matches[1] : null;
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
            throw new SyntaxException(_('制御文字や空白文字のみで構成された辞書は変換できません。'));
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
