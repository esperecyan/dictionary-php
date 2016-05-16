<?php
namespace esperecyan\dictionary_php\validator;

class FileLocationValidatorTest extends \PHPUnit_Framework_TestCase implements \Psr\Log\LoggerInterface
{
    use \esperecyan\dictionary_php\LogLevelLoggerTrait;
    
    /**
     * @param string $fieldName
     * @param string[] $filenames
     * @param string $input
     * @param string|null $output
     * @dataProvider filenameProvider
     */
    public function testValidate(string $fieldName, array $filenames, string $input, string $output = null)
    {
        $this->assertSame($input === $output, (new FileLocationValidator($fieldName, $filenames))->validate($input));
    }
    
    /**
     * @param string $fieldName
     * @param string[] $filenames
     * @param string $input
     * @param string $output
     * @dataProvider filenameProvider
     */
    public function testCorrect(string $fieldName, array $filenames, string $input, string $output)
    {
        $validator = new FileLocationValidator($fieldName, $filenames);
        $validator->setLogger($this);
        $this->{$output[0] === '/' ? 'assertRegExp' : 'assertSame'}($output, $validator->correct($input));
        $this->assertEquals($input !== $output ? [\Psr\Log\LogLevel::ERROR] : [], $this->logLevels);
    }
    
    public function filenameProvider(): array
    {
        return [
            [
                'image',
                [],
                'test/太陽.png',
                'test/太陽.png',
            ],
            [
                'image',
                [],
                'test/太陽.jpg',
                'test/太陽.jpg',
            ],
            [
                'image',
                [],
                'test/太陽.jpeg',
                'test/太陽.jpeg',
            ],
            [
                'image',
                [],
                'test/太陽.svg',
                'test/太陽.svg',
            ],
            [
                'image',
                [],
                'test/太陽.jpe',
                'local/太陽.png',
            ],
            [
                'audio',
                [],
                'four-seasons.mp4',
                'local/four-seasons.mp4',
            ],
            [
                'audio',
                [],
                'C:\\Users\\山田太郎\\Desktop\\曲\\four-seasons.mp4',
                'local/four-seasons.mp4',
            ],
            [
                'audio',
                [],
                'four-seasons',
                'local/four-seasons.mp4',
            ],
            [
                'audio',
                ['four-seasons.mp4'],
                'four-seasons',
                'local/four-seasons.mp4',
            ],
            [
                'audio',
                ['four-seasons.mp4'],
                'four-seasons.mp4',
                'four-seasons.mp4',
            ],
            [
                'audio',
                [],
                'four-seasons_ver1.0.mp4',
                'local/four-seasons_ver1．0.mp4',
            ],
            [
                'audio',
                [],
                'four-seasons.mp4.ja',
                'local/four-seasons．mp4.mp4',
            ],
            [
                'audio',
                [],
                '.mp4',
                '/^local\\/[0-9a-f]{8}\\.mp4$/u',
            ],
            [
                'audio',
                [],
                'C:\\Users\\山田太郎\\Desktop\\曲\\四季『春』.mp4',
                'local/四季『春』.mp4',
            ],
        ];
    }
    
    /**
     * @param string $fieldName
     * @expectedException \DomainException
     * @dataProvider invalidFieldNameProvider
     */
    public function testInvalidFieldName(string $fieldName)
    {
        new FileLocationValidator($fieldName);
    }
    
    public function invalidFieldNameProvider(): array
    {
        return [
            ['text'],
            //['image'],
            //['audio'],
            //['video'],
            ['image-source'],
            ['audio-source'],
            ['video-source'],
            ['answer'],
            ['description'],
            ['weight'],
            ['specifics'],
            ['question'],
            ['option'],
            ['type'],
            ['@title'],
            ['@summary'],
            ['@regard'],
            ['@image'],
            [' image'],
            ['image '],
            [' '],
            [''],
        ];
    }
}
