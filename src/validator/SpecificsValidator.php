<?php
namespace esperecyan\dictionary_php\validator;

/**
 * specificsフィールドの矯正。
 */
class SpecificsValidator extends AbstractFieldValidator
{
    /**
     * フィールドを解析し、同名の値の配列を値に持つ連想配列として返します。
     * @param string $urlencoded
     * @return string[][]
     */
    protected function parse(string $urlencoded): array
    {
        $specifics = [];
        foreach (new \esperecyan\url\URLSearchParams($urlencoded) as $name => $value) {
            $specifics[$name][] = $value;
        }
        return $specifics;
    }
    
    /**
     * 配列の連想配列を文字列へ直列化して返します。
     * @param string $nameValues
     * @return string
     */
    protected function serialize(array $nameValues): string
    {
        $params = new \esperecyan\url\URLSearchParams();
        foreach ($nameValues as $name => $values) {
            foreach ($values as $value) {
                $params->append($name, $value);
            }
        }
        return (string)$params;
    }
    
    public function correct(string $input): string
    {
        $nameValues = $this->parse($input);
        if ($nameValues) {
            foreach ($nameValues as $name => &$values) {
                foreach ($values as &$value) {
                    switch ($name) {
                        // 空文字列
                        case 'no-pixelization':
                        case 'require-all-right':
                        case 'no-random':
                            $value = '';
                            break;
                        
                        // 0より大きい実数
                        case 'magnification':
                        case 'last-magnification':
                        case 'length':
                        case 'speed':
                        case 'volume':
                            $validator = new NumberValidator(true);
                            $validator->setLogger($this->logger);
                            $number = $validator->correct($value);
                            if ($number !== '' && bccomp($number, '0', NumberValidator::SCALE) === 1) {
                                $value = $number;
                            } else {
                                $this->logger->error(sprintf(_('「%s」は0より大きい実数として扱えません。'), $value));
                                $value = null;
                            }
                            break;
                        
                        // 0以上の実数
                        case 'start':
                            $validator = new NumberValidator(true);
                            $validator->setLogger($this->logger);
                            $number = $validator->correct($value);
                            if ($number !== '' && bccomp($number, '0', NumberValidator::SCALE) >= 0) {
                                $value = $number;
                            } else {
                                $this->logger->error(sprintf(_('「%s」は0以上の実数として扱えません。'), $value));
                                $value = null;
                            }
                            break;
                        
                        // 1以上の整数
                        case 'repeat':
                        case 'score':
                        case 'last-score':
                            $validator = new NumberValidator();
                            $validator->setLogger($this->logger);
                            $number = $validator->correct($value);
                            if ($number !== '' && bccomp($number, '0') === 1) {
                                $value = $number;
                            } else {
                                $this->logger->error(sprintf(_('「%s」は1以上の整数として扱えません。'), $value));
                                $value = null;
                            }
                            break;
                        
                        // 整数
                        case 'bonus':
                            $validator = new NumberValidator();
                            $validator->setLogger($this->logger);
                            $number = $validator->correct($value);
                            if ($number !== '') {
                                $value = $number;
                            } else {
                                $this->logger->error(sprintf(_('「%s」は整数として扱えません。'), $value));
                                $value = null;
                            }
                            break;
                    }
                }
                $values = array_filter($values, function ($value) {
                    return !is_null($value);
                });
            }
            $output = $this->serialize($nameValues);
        } else {
            $this->logger->error(_('解析した結果空文字列になりました。'));
        }
        
        return $output ?? '';
    }
}
