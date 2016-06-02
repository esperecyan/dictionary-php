<?php
namespace esperecyan\dictionary_php\parser;

use esperecyan\dictionary_php\{Dictionary, validator\AnswerValidator, exception\SyntaxException};

class CatchfeelingParser extends AbstractParser
{
    /**
     * 行を解析し、textフィールドとdescriptionフィールドに対応する文字列を取り出します。
     * @param Dictionary $dictionary
     * @param string $line
     */
    protected function parseLine(Dictionary $dictionary, string $line)
    {
        // コメントの分離
        $textAndDescription = preg_split('#[\\t 　]*//#u', $line, 2);
        
        if (str_replace(["\t", ' ', '　'], '', $textAndDescription[0]) === '') {
            throw new SyntaxException(_('空行 (スペース、コメントのみの行) があります。'));
        } else {
            $fieldsAsMultiDimensionalArray['text'] = [(new AnswerValidator())->isRegExp($textAndDescription[0])
                ? trim($textAndDescription[0], '/') // 正規表現文字列扱いを抑止
                : $textAndDescription[0]];
            
            if (isset($textAndDescription[1])) {
                // コメントが存在すれば
                $description = trim($textAndDescription[1], " 　\t");
                if ($description !== '') {
                    $fieldsAsMultiDimensionalArray['description'] = [$description];
                }
            }

            try {
                $dictionary->addWord($fieldsAsMultiDimensionalArray);
            } catch (SyntaxException $e) {
                $this->logInconvertibleError($line, $e);
            }
        }
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
            $this->parseLine($dictionary, $line);
        }
        
        $this->wholeText .= implode('', array_column(array_column($dictionary->getWords(), 'text'), 0));
        if ($this->wholeText === '') {
            throw new SyntaxException(_('制御文字や空白文字のみで構成された辞書は変換できません。'));
        }
        
        $regard = $this->generateRegard();
        if ($regard) {
            $metaFields['@regard'] = $this->generateRegard();
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
