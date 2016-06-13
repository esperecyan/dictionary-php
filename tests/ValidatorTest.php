<?php
namespace esperecyan\dictionary_php;

use Psr\Log\LogLevel;

class ValidatorTest extends \PHPUnit_Framework_TestCase implements \Psr\Log\LoggerInterface
{
    use \esperecyan\dictionary_php\LogLevelLoggerTrait;
    
    /**
     * @param string|\SplFileInfo $file
     * @param string $filename
     * @param string[] $output
     * @param string[] $logLevels
     * @dataProvider fileProvider
     */
    public function testCorrect($file, string $filename, array $output, array $logLevels)
    {
        $validator = new Validator();
        $validator->setLogger($this);
        $actualOutput = $validator->correct($file, $filename);
        unset($actualOutput['bytes']);
        $this->assertEquals($output, $actualOutput);
        $this->assertEquals($logLevels, $this->logLevels);
    }

    public function fileProvider(): array
    {
        return [
            [
                file_get_contents(__DIR__ . '/resources/exif.jpg'),
                'test.jpeg',
                [
                    'type' => 'image/jpeg',
                    'name' => 'test.jpeg',
                ],
                [LogLevel::ERROR, LogLevel::WARNING, LogLevel::WARNING],
            ],
            [
                new \SplFileInfo(__DIR__ . '/resources/exif.jpg'),
                'test.jpg',
                [
                    'type' => 'image/jpeg',
                    'name' => 'test.jpg',
                ],
                [LogLevel::ERROR, LogLevel::WARNING, LogLevel::WARNING],
            ],
            [
                new \SplFileObject(__DIR__ . '/resources/exif.jpg'),
                'test.jpeg',
                [
                    'type' => 'image/jpeg',
                    'name' => 'test.jpeg',
                ],
                [LogLevel::ERROR, LogLevel::WARNING, LogLevel::WARNING],
            ],
            [
                '<?xml version="1.0" ?>
                <svg xmlns="http://www.w3.org/2000/svg">
                    <rect width="1000" height="1000" />
                </svg>',
                'test.svg',
                [
                    'type' => 'image/svg+xml; charset=UTF-8',
                    'name' => 'test.svg',
                ],
                [],
            ],
            [
                '<?xml version="1.0" ?>
                <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
                    <a xlink:href="https://example.com/">
                        <rect width="1000" height="1000" />
                    </a>
                </svg>',
                'test.svg',
                [
                    'type' => 'image/svg+xml; charset=UTF-8',
                    'name' => 'test.svg',
                ],
                [LogLevel::ERROR],
            ],
            [
                file_get_contents(__DIR__ . '/resources/mpeg1-audio-layer3.mp3'),
                'test.mp3',
                [
                    'type' => 'audio/mpeg',
                    'name' => 'test.mp3',
                ],
                [],
            ],
            [
                file_get_contents(__DIR__ . '/resources/mpeg4-aac.m4a'),
                'test.m4a',
                [
                    'type' => 'audio/mp4',
                    'name' => 'test.m4a',
                ],
                [],
            ],
            [
                file_get_contents(__DIR__ . '/resources/mpeg4-aac.m4a'),
                'test.mp4',
                [
                    'type' => 'video/mp4',
                    'name' => 'test.mp4',
                ],
                [],
            ],
        ];
    }
    
    /**
     * @param string|\SplFileInfo $file
     * @param string $filename
     * @expectedException \esperecyan\dictionary_php\exception\SyntaxException
     * @dataProvider invalidFileProvider
     */
    public function testSyntaxException($file, string $filename)
    {
        (new Validator())->correct($file, $filename);
    }
    
    public function invalidFileProvider(): array
    {
        return [
            [
                '<?xml version="1.0" ?>
                <svg>
                    ルート要素がSVG名前空間に属していない。
                </svg>
                ',
                'test.svg',
            ],
            [
                file_get_contents(__DIR__ . '/resources/dummy.zip'),
                'test.zip',
            ],
            [
                file_get_contents(__DIR__ . '/resources/exif.jpg'),
                'test.jpe',
            ],
            [
                file_get_contents(__DIR__ . '/resources/exif.jpg'),
                'test.png',
            ],
            [
                file_get_contents(__DIR__ . '/resources/exif.jpg'),
                '-test.jpg',
            ],
        ];
    }
}
