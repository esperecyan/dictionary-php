<?php
namespace esperecyan\dictionary_php\validator;

use esperecyan\url\URLSearchParams;

/**
 * specificsフィールドの矯正。
 */
class SpecificsValidator extends AbstractFieldValidator
{
    /** @var string[] キーが依存元、名前が依存先。 */
    const DEPENDENCES = [
        'last-magnification' => 'magnification',
        'last-score' => 'score',
    ];
    
    /**
     * フィールドを解析し、同名の値の配列を値に持つ連想配列として返します。
     * @param string $urlencoded
     * @return string[][]
     */
    protected function parse(string $urlencoded): array
    {
        $specifics = [];
        foreach (new URLSearchParams($urlencoded) as $name => $value) {
            $specifics[$name][] = $value;
        }
        return $specifics;
    }
    
    public function correct(string $input): string
    {
        $nameValues = $this->parse($input);
        if ($nameValues) {
            $params = new URLSearchParams();
            foreach ($nameValues as $name => $values) {
                foreach ($values as $value) {
                    switch ($name) {
                        // 空文字列
                        case 'pixelization':
                        case 'require-all-right':
                        case 'no-random':
                            $params->append($name, '');
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
                            if ($number !== '' && $number > 0) {
                                $params->append($name, $number);
                            } else {
                                $this->logger->error(sprintf(_('「%s」は0より大きい実数として扱えません。'), $value));
                            }
                            break;
                        
                        // 0以上の実数
                        case 'start':
                            $validator = new NumberValidator(true);
                            $validator->setLogger($this->logger);
                            $number = $validator->correct($value);
                            if ($number !== '' && $number >= 0) {
                                $params->append($name, $number);
                            } else {
                                $this->logger->error(sprintf(_('「%s」は0以上の実数として扱えません。'), $value));
                            }
                            break;
                        
                        // 1以上の整数
                        case 'repeat':
                        case 'score':
                        case 'last-score':
                            $validator = new NumberValidator();
                            $validator->setLogger($this->logger);
                            $number = $validator->correct($value);
                            if ($number !== '' && $number >= 1) {
                                $params->append($name, $number);
                            } else {
                                $this->logger->error(sprintf(_('「%s」は1以上の整数として扱えません。'), $value));
                            }
                            break;
                        
                        // 整数
                        case 'bonus':
                            $validator = new NumberValidator();
                            $validator->setLogger($this->logger);
                            $number = $validator->correct($value);
                            if ($number !== '') {
                                $params->append($name, $number);
                            } else {
                                $this->logger->error(sprintf(_('「%s」は整数として扱えません。'), $value));
                            }
                            break;
                        
                        default:
                            $params->append($name, $value);
                    }
                }
            }
            
            foreach (self::DEPENDENCES as $source => $destination) {
                if ($params->has($source) && !$params->has($destination)) {
                    $params->delete($source);
                    // TRANRATORS: %1$s、%2$s はapplication/x-www-form-urlencoded形式の名前部分
                    $this->logger->error(sprintf(_('「%1$s」を指定するときは、「%2$s」も指定しておく必要があります。'), $source, $destination));
                }
            }
            
            $output = (string)$params;
        } else {
            $this->logger->error(_('解析した結果空文字列になりました。'));
        }
        
        return $output ?? '';
    }
}
