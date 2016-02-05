<?php
namespace esperecyan\dictionary_api\validator;

use Psr\Log\LogLevel;

class SpecificsValidatorTest extends \PHPUnit_Framework_TestCase implements \Psr\Log\LoggerInterface
{
    use \esperecyan\dictionary_api\LogLevelLoggerTrait;
    
    /**
     * @param string $input
     * @param string $output
     * @param string[] $logLevels
     * @dataProvider specificsProvider
     */
    public function testCorrect(string $input, string $output, array $logLevels = [])
    {
        $validator = new SpecificsValidator();
        $validator->setLogger($this);
        $this->assertSame($output, $validator->correct($input));
        $this->assertEquals($logLevels, $this->logLevels);
    }
    
    public function specificsProvider(): array
    {
        return [
            [
                'start=5',
                'start=5',
            ],
            [
                'start=5&start=6',
                'start=5&start=6',
            ],
            [
                'start=5&test=𩸽',
                'start=5&test=%F0%A9%B8%BD',
            ],
            [
                'speed=invalid&repeat=2',
                'repeat=2',
                [LogLevel::ERROR, LogLevel::ERROR],
            ],
            [
                'speed=invalid',
                '',
                [LogLevel::ERROR, LogLevel::ERROR],
            ],
            [
                'no-pixelization=on&magnification=0.1&last-magnification=1&require-all-right=on&score=100&last-score=0',
                'no-pixelization=&magnification=0.1&last-magnification=1&require-all-right=&score=100',
                [LogLevel::ERROR],
            ],
            [
                'start=34&repeat=3&length=5&speed=2&volume=0.5',
                'start=34&repeat=3&length=5&speed=2&volume=0.5',
            ],
            [
                'no-random&bonus=1000',
                'no-random=&bonus=1000',
            ],
        ];
    }
}
