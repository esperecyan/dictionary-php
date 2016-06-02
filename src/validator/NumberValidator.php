<?php
namespace esperecyan\dictionary_php\validator;

/**
 * 整数、実数の矯正。
 */
class NumberValidator extends AbstractFieldValidator
{
    /** @var string 整数を表す正規表現。 */
    const INTEGER_REGEXP = '/^(0|-?[1-9][0-9]*)$/u';
    
    /** @var string 実数を表す正規表現。 */
    const REAL_NUMBER_REGEXP = '/^((0|-?[1-9][0-9]*)|-?(0|[1-9][0-9]*)\\.[0-9]*[1-9])$/u';
    
    /** @var bool */
    protected $realNumber;
    
    /**
     * @param bool $realNumber 実数の矯正であれば真を指定。
     */
    public function __construct(bool $realNumber = false)
    {
        parent::__construct();
        
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
    
    /**
     * 浮動小数点数を文字列に直列化します。
     * @param string|float $float
     * @return string
     */
    public function serializeFloat($float): string
    {
        return rtrim(rtrim(sprintf('%F', $float), '0'), '.');
    }
    
    public function correct(string $input): string
    {
        if ($this->validate($input)) {
            $output = $input;
        } else {
            $this->logger->error(sprintf(
                $this->realNumber ? _('「%s」は実数の規則に合致しません。') : _('「%s」は整数の規則に合致しません。'),
                $input
            ));
            
            $output = $this->realNumber
                ? $this->serializeFloat($input)
                : sprintf('%d', stripos($input, 'E') !== false ? (float)$input : $input);
            
            if ($output === '-0') {
                $output = '0';
            }
        }
        
        return $output;
    }
}
