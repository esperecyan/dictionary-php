<?php
namespace esperecyan\dictionary_api\validator;

/**
 * 整数、実数の矯正。
 */
class NumberValidator extends AbstractFieldValidator
{
    /** @var string 整数を表す正規表現。 */
    const INTEGER_REGEXP = '/^(0|-?[1-9][0-9]*)$/u';
    
    /** @var string 実数を表す正規表現。 */
    const REAL_NUMBER_REGEXP = '/^((0|-?[1-9][0-9]*)|-?(0|[1-9][0-9]*)\\.[0-9]*[1-9])$/u';
    
    /** @var int 当クラスで対応する小数点以下の最大桁数。 */
    const SCALE = 100;
    
    /** @var bool */
    protected $realNumber;
    
    /**
     * @param bool $realNumber 実数の矯正であれば真を指定。
     */
    public function __construct(bool $realNumber = false)
    {
        $this->realNumber = $realNumber;
    }

    /**
     * 入力値が整数、または実数の規則に合致していれば真を返します。
     * @param string $input
     * @return bool
     */
    protected function validate(string $input): bool
    {
        return preg_match($this->realNumber ? self::REAL_NUMBER_REGEXP : self::INTEGER_REGEXP, $input) === 1;
    }
    
    public function correct(string $input): string
    {
        if ($this->validate($input)) {
            $output = $input;
        } else {
            if ($this->logger) {
                $this->logger->error(sprintf($this->realNumber
                    ? _('「%s」は実数の規則に合致しません。')
                    : _('「%s」は整数の規則に合致しません。'), $input));
            }
            
            if (stripos($input, 'E') !== false) {
                $input = (float)$input;
            }
            
            $output = $this->realNumber
                ? rtrim(rtrim(bcadd($input, '0', self::SCALE), '0'), '.')
                : bcadd($input, '0', 0);
            
            if ($output === '-0') {
                $output = '0';
            }
        }
        
        return $output;
    }
}
