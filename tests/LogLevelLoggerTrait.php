<?php
namespace esperecyan\dictionary_php;

trait LogLevelLoggerTrait
{
    use \Psr\Log\LoggerTrait;

    /** @var string[] */
    protected $logLevels = [];

    public function log($level, $message, array $context = [])
    {
        $this->logLevels[] = $level;
    }
}
