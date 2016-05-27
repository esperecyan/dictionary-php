<?php
namespace esperecyan\dictionary_php\log;

abstract class AbstractLoggerAware implements \Psr\Log\LoggerAwareInterface
{
    use \Psr\Log\LoggerAwareTrait;
    
    public function __construct()
    {
        $this->setLogger(new \Psr\Log\NullLogger());
    }
}
