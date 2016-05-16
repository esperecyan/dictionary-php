<?php
namespace esperecyan\dictionary_php\validator;

abstract class AbstractFieldValidator implements \Psr\Log\LoggerAwareInterface
{
    use \Psr\Log\LoggerAwareTrait;
    
    /**
     * 入力を矯正して返します。
     * @param string $input
     * @return string 矯正の結果フィールドの削除が生じるときは空文字列を返します。
     */
    abstract public function correct(string $input): string;
}
