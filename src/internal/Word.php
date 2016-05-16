<?php
namespace esperecyan\dictionary_php\internal;

use esperecyan\dictionary_php\validator;
use esperecyan\dictionary_php\exception\SyntaxException;

/**
 * ある辞書における1つのお題 (レコード、行) を表します。
 */
class Word implements \Psr\Log\LoggerAwareInterface
{
    use \Psr\Log\LoggerAwareTrait;
    
    /** @var string[][] */
    protected $fieldsAsMultiDimensionalArray = [];
    
    /** @var string[] */
    protected $filenames = [];
    
    /**
     * @param string[] $filenames ファイル所在の検証に使用するファイル名のリスト。
     */
    public function __construct(array $filenames = [])
    {
        $this->filenames = $filenames;
    }
    
    /**
     * キーにフィールド名、値に同名フィールド値の配列を持つ配列から、各フィールドを設定します。
     * @param string[][] $fieldsAsMultiDimensionalArray フィールド名はバリデート済み、かつフィールド値が空文字列であってはなりません。
     * @throws \BadMethodCallException すでに設定済みの場合。
     * @throws SyntaxException 妥当なtextフィールドが存在しない場合。
     *      typeフィールドにselectionが指定されているとき、answerフィールド、optionフィールドが規則に合致しない場合。
     */
    public function setFieldsAsMultiDimensionalArray(array $fieldsAsMultiDimensionalArray)
    {
        if ($this->fieldsAsMultiDimensionalArray) {
            throw new \BadMethodCallException();
        }
        
        foreach ($fieldsAsMultiDimensionalArray as $fieldName => $fields) {
            foreach ($fields as $field) {
                $validatedField = $this->correctField($fieldName, $field);
                if ($validatedField !== '') {
                    $this->fieldsAsMultiDimensionalArray[$fieldName][] = $validatedField;
                }
            }
        }
        
        if (!isset($this->fieldsAsMultiDimensionalArray['text'][0])) {
            // textフィールドが存在しない場合
            throw new SyntaxException(_('textフィールドは必須です。'));
        }
        
        if (isset($this->fieldsAsMultiDimensionalArray['type'][0])
            && $this->fieldsAsMultiDimensionalArray['type'][0] === 'selection') {
            // typeフィールドにselectionが指定されている場合
            if (!isset($this->fieldsAsMultiDimensionalArray['option'][0])) {
                throw new SyntaxException(
                    _('typeフィールドに「selection」が指定されている場合、optionフィールドは必須です。')
                );
            }
            if (isset($this->fieldsAsMultiDimensionalArray['answer'])) {
                // 選択問題なら
                foreach ($this->fieldsAsMultiDimensionalArray['answer'] as $answer) {
                    if (!in_array($answer, $this->fieldsAsMultiDimensionalArray['option'])) {
                        throw new SyntaxException(sprintf(_('「%s」はoptionフィールドのいずれの値にも一致しません。'), $answer));
                    }
                }
            }
        }
        
        $answerValidator = new validator\AnswerValidator();
        if (isset($this->fieldsAsMultiDimensionalArray['answer'][0])
            && $answerValidator->isRegExp($this->fieldsAsMultiDimensionalArray['answer'][0])) {
            // 1個目のanswerフィールドが正規表現文字列だった場合
            if ($this->logger) {
                $this->logger->error(_('1個目のanswerフィールドは、正規表現文字列であってはなりません。'));
            }
            foreach ($this->fieldsAsMultiDimensionalArray['answer'] as $index => $answer) {
                if (!$answerValidator->isRegExp($answer)) {
                    $noRegExpIndex = $index;
                    break;
                }
            }
            if (isset($noRegExpIndex)) {
                array_unshift(
                    $this->fieldsAsMultiDimensionalArray['answer'],
                    array_splice($this->fieldsAsMultiDimensionalArray['answer'], $noRegExpIndex)[0]
                );
            } else {
                unset($this->fieldsAsMultiDimensionalArray['answer']);
            }
        }
        
        if (!isset($this->fieldsAsMultiDimensionalArray['answer'][0])
            && ($this->fieldsAsMultiDimensionalArray['type'][0] ?? null) !== 'selection') {
            // answerフィールドが存在せず、typeフィールドがselectionでない場合
            $text = $this->correctField('answer', $this->fieldsAsMultiDimensionalArray['text'][0]);
            if ($text === '') {
                throw new SyntaxException(sprintf(
                    _('「%s」は解答文字列の規則に合致しません。'),
                    $this->fieldsAsMultiDimensionalArray['text'][0]
                ));
            } else {
                $this->fieldsAsMultiDimensionalArray['text'][0] = $text;
            }
        }
    }
    
    /**
     * 各フィールドを取得します。
     * @throws \BadMethodCallException フィールドが未設定の場合。
     * @return string[][]
     */
    public function getFieldsAsMultiDimensionalArray(): array
    {
        if (!$this->fieldsAsMultiDimensionalArray) {
            throw new \BadMethodCallException();
        }
        return $this->fieldsAsMultiDimensionalArray;
    }

    /**
     * フィールド単体に対するバリデートを行います。
     * @param string $fieldName フィールド名。未知のフィールド名が指定された場合はそのまま返します。
     * @param string $field フィールド値。
     * @return string 矯正したフィールド値を返します。矯正の結果フィールドの削除が生じるときは空文字列を返します。
     */
    protected function correctField(string $fieldName, string $field): string
    {
        switch ($fieldName) {
            case 'image':
            case 'audio':
            case 'video':
                $validator = new validator\FileLocationValidator($fieldName, $this->filenames);
                if ($this->logger) {
                    $validator->setLogger($this->logger);
                }
                $output = $validator->correct($field);
                break;

            case 'image-source':
            case 'audio-source':
            case 'video-source':
                $validator = new validator\LightweightMarkupValidator(true);
                if ($this->logger) {
                    $validator->setLogger($this->logger);
                }
                $output = $validator->correct($field);
                break;

            case 'answer':
            case 'option':
                $validator = new validator\AnswerValidator();
                if ($this->logger) {
                    $validator->setLogger($this->logger);
                }
                $output = $validator->correct($field);
                break;

            case 'description':
            case '@summary':
                $validator = new validator\LightweightMarkupValidator(false, $this->filenames);
                if ($this->logger) {
                    $validator->setLogger($this->logger);
                }
                $output = $validator->correct($field);
                break;

            case 'weight':
                $validator = new validator\NumberValidator(true);
                if ($this->logger) {
                    $validator->setLogger($this->logger);
                }
                $number = $validator->correct($field);
                if (bccomp($number, '0', validator\NumberValidator::SCALE) === 1) {
                    $output = $number;
                } else {
                    if ($this->logger) {
                        $this->logger->error(sprintf(_('「%s」は0より大きい実数として扱えません。'), $field));
                    }
                    $output = '';
                }
                break;

            case 'specifics':
                $validator = new validator\SpecificsValidator();
                if ($this->logger) {
                    $validator->setLogger($this->logger);
                }
                $output = $validator->correct($field);
                break;

            case '@regard':
                if (preg_match('/^\\[.{3,}]$/u', $field) === 1
                    && (new validator\AnswerValidator())->validateRegexp("/$field/")) {
                    $output = $field;
                } else {
                    if ($this->logger) {
                        $this->logger->error(sprintf(_('「%s」は妥当な正規表現文字クラスではありません。'), $field));
                    }
                    $output = '';
                }
                break;
            
            default:
                $output = $field;
        }
        
        return $output;
    }
}
