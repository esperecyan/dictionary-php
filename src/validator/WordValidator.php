<?php
namespace esperecyan\dictionary_php\validator;

use esperecyan\url\URLSearchParams;
use esperecyan\html_filter\Filter as HTMLFilter;
use League\CommonMark\CommonMarkConverter;
use esperecyan\dictionary_php\exception\SyntaxException;

/**
 * 辞書における1つのお題 (レコード、行) のバリデートを行います。
 */
class WordValidator extends \esperecyan\dictionary_php\log\AbstractLoggerAware
{
    /** @var string[] ファイル所在の検証に使用するファイル名のリスト。 */
    protected $filenames = [];
    
    /**
     * @param string[] $filenames ファイル所在の検証に使用するファイル名のリスト。
     */
    public function __construct(array $filenames = [])
    {
        parent::__construct();
        
        $this->filenames = $filenames;
    }
    
    /**
     * キーにフィールド名、値にフィールド値を持つ配列から、辞書のメタフィールドを生成します。
     * @param string[] $metaFields フィールド名はバリデート済み、かつフィールド値が空文字列であってはなりません。
     * @return (string|string[])[]
     */
    public function parseMetadata(array $metaFields): array
    {
        $metadata = [];
        
        foreach ($metaFields as $fieldName => $field) {
            $validatedField = $this->parseField($fieldName, $field);
            if (!is_null($validatedField)) {
                $metadata[$fieldName] = $validatedField;
            }
        }
        
        return $metadata;
    }
    
    /**
     * キーにフィールド名、値に同名フィールド値の配列を持つ配列から、1つのお題を表す配列を生成します。
     * @param string[][] $fieldsAsMultiDimensionalArray フィールド名はバリデート済み、かつフィールド値が空文字列であってはなりません。
     * @throws SyntaxException 妥当なtextフィールドが存在しない場合。
     *      typeフィールドにselectionが指定されているとき、answerフィールド、optionフィールドが規則に合致しない場合。
     * @return (string|string[]|float|URLSearchParams)[][]
     */
    public function parse(array $fieldsAsMultiDimensionalArray): array
    {
        $word = [];
        
        foreach ($fieldsAsMultiDimensionalArray as $fieldName => $fields) {
            foreach ($fields as $field) {
                $validatedField = $this->parseField($fieldName, $field);
                if (!is_null($validatedField)) {
                    $word[$fieldName][] = $validatedField;
                }
            }
        }
        
        if (!isset($word['text'][0])) {
            // textフィールドが存在しない場合
            throw new SyntaxException(_('textフィールドは必須です。'));
        }
        
        if (isset($word['type'][0])
            && $word['type'][0] === 'selection') {
            // typeフィールドにselectionが指定されている場合
            if (!isset($word['option'][0])) {
                throw new SyntaxException(
                    _('typeフィールドに「selection」が指定されている場合、optionフィールドは必須です。')
                );
            }
            if (isset($word['answer'])) {
                // 選択問題なら
                foreach ($word['answer'] as $answer) {
                    if (!in_array($answer, $word['option'])) {
                        throw new SyntaxException(sprintf(_('「%s」はoptionフィールドのいずれの値にも一致しません。'), $answer));
                    }
                }
            }
        }
        
        $answerValidator = new AnswerValidator();
        if (isset($word['answer'][0])
            && $answerValidator->isRegExp($word['answer'][0])) {
            // 1個目のanswerフィールドが正規表現文字列だった場合
            $this->logger->error(_('1個目のanswerフィールドは、正規表現文字列であってはなりません。'));
            foreach ($word['answer'] as $index => $answer) {
                if (!$answerValidator->isRegExp($answer)) {
                    $noRegExpIndex = $index;
                    break;
                }
            }
            if (isset($noRegExpIndex)) {
                array_unshift(
                    $word['answer'],
                    array_splice($word['answer'], $noRegExpIndex)[0]
                );
            } else {
                unset($word['answer']);
            }
        }
        
        if (!isset($word['answer'][0])
            && ($word['type'][0] ?? null) !== 'selection') {
            // answerフィールドが存在せず、typeフィールドがselectionでない場合
            $text = $this->parseField('answer', $word['text'][0]);
            if (is_null($text)) {
                throw new SyntaxException(sprintf(
                    _('「%s」は解答文字列の規則に合致しません。'),
                    $word['text'][0]
                ));
            } else {
                $word['text'][0] = $text;
            }
        }
        
        return $word;
    }

    /**
     * フィールド単体の構文解析を行います。
     * @param string $fieldName フィールド名。未知のフィールド名が指定された場合はそのまま返します。
     * @param string $field フィールド値。
     * @return string|string[]|float|URLSearchParams|null 矯正したフィールド値を返します。矯正の結果フィールドの削除が生じるときは null を返します。
     */
    protected function parseField(string $fieldName, string $field)
    {
        switch ($fieldName) {
            case 'image':
            case 'audio':
            case 'video':
                $validator = new FileLocationValidator($fieldName, $this->filenames);
                $validator->setLogger($this->logger);
                $output = $validator->correct($field);
                break;

            case 'image-source':
            case 'audio-source':
            case 'video-source':
                $validator = new LightweightMarkupValidator(true);
                $validator->setLogger($this->logger);
                $output = $validator->correct($field);
                if ($output !== '') {
                    $output = [
                        'lml' => $output,
                        'html' => (new HTMLFilter())->filter((new CommonMarkConverter())->convertToHtml($output)),
                    ];
                }
                break;

            case 'answer':
            case 'option':
                $validator = new AnswerValidator();
                $validator->setLogger($this->logger);
                $output = $validator->correct($field);
                break;

            case 'description':
            case '@summary':
                $validator = new LightweightMarkupValidator(false, $this->filenames);
                $validator->setLogger($this->logger);
                $output = $validator->correct($field);
                if ($output !== '') {
                    $output = [
                        'lml' => $output,
                        'html' => (new HTMLFilter())->filter((new CommonMarkConverter())->convertToHtml($output)),
                    ];
                }
                break;

            case 'weight':
                $validator = new NumberValidator(true);
                $validator->setLogger($this->logger);
                $number = $validator->correct($field);
                if ($number > 0) {
                    $output = $number;
                } else {
                    $this->logger->error(sprintf(_('「%s」は0より大きい実数として扱えません。'), $field));
                    $output = '';
                }
                if ($output !== '') {
                    $output = (float)$output;
                }
                break;

            case 'specifics':
                $validator = new SpecificsValidator();
                $validator->setLogger($this->logger);
                $output = $validator->correct($field);
                if ($output !== '') {
                    $output = new URLSearchParams($output);
                }
                break;

            case '@regard':
                if (preg_match('/^\\[.{3,}]$/u', $field) === 1
                    && (new AnswerValidator())->validateRegexp("/$field/")) {
                    $output = $field;
                } else {
                    $this->logger->error(sprintf(_('「%s」は妥当な正規表現文字クラスではありません。'), $field));
                    $output = '';
                }
                break;
            
            default:
                $output = $field;
        }
        
        return $output === '' ? null : $output;
    }
}
