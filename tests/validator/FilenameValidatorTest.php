<?php
namespace esperecyan\dictionary_php\validator;

class FilenameValidatorTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @param string $input
     * @param string $output
     * @param string|null $fieldName
     * @param string[] $filenames
     * @dataProvider filenameProvider
     */
    public function testValidate(string $input, string $output, string $fieldName = null, array $filenames = [])
    {
        $this->assertSame($input === $output, (new FilenameValidator($fieldName, $filenames))->validate($input));
    }
    
    /**
     * @param string $input
     * @param string $output
     * @param string|null $fieldName
     * @param string[] $filenames
     * @dataProvider filenameProvider
     */
    public function testCorrect(string $input, string $output, string $fieldName = null, array $filenames = [])
    {
        $validator = new FilenameValidator($fieldName, $filenames);
        $this->{$output[0] === '/' ? 'assertRegExp' : 'assertSame'}($output, $validator->correct($input));
    }
    
    public function filenameProvider(): array
    {
        return [
            [
                '太陽.png',
                'taiyo.png',
                'image',
            ],
            [
                'taiyō.png',
                'taiyo.png',
                'image',
            ],
            [
                'test/太陽.jpg',
                'test-taiyo.jpg',
                'image',
            ],
            [
                'four-seasons.mp4',
                'four-seasons.mp4',
                'audio',
            ],
            [
                'four-seasons_ver1.0.mp4',
                'four-seasons-ver1-0.mp4',
                'audio',
            ],
            [
                '.mp4',
                '/^[0-9a-f]{8}\\.mp4$/u',
                'audio',
            ],
            [
                '四季『春』.mp4',
                'shiki-haru.mp4',
                'audio',
            ],
            [
                'aux.mp4',
                'aux-.mp4',
                'audio',
            ],
            [
                'test--test.mp4',
                'test--test.mp4',
                'audio',
            ],
            [
                'test--TEST.mp4',
                'test-t-e-s-t.mp4',
                'audio',
            ],
            [
                'test--TEST.mp4',
                'test-t-e-s-t.mp4',
            ],
            [
                'test.png',
                'test.png',
            ],
            [
                '----test.png',
                'test.png',
            ],
            [
                '----aux.png',
                'aux-.png',
            ],
            [
                '-.png',
                '/^[0-9a-f]{8}\\.png$/u',
            ],
            [
                '---.png',
                '/^[0-9a-f]{8}\\.png$/u',
            ],
            [
                '+aux.png',
                'aux-.png',
            ],
            [
                '+テスト.png',
                'tesuto.png',
            ],
            [
                'テ ス ト.png',
                'te-su-to.png',
            ],
            [
                '.aux.png',
                'aux-.png',
            ],
            [
                'ａｕｘ.png',
                'aux-.png',
            ],
            [
                'ａｕｘ.png',
                'aux--.png',
                null,
                ['aux-.png'],
            ],
            [
                'aux.png',
                '/^[0-9a-f]{8}\\.png$/u',
                null,
                ['aux-.png', 'aux--.png', 'aux---.png', 'aux----.png', 'aux-----.png', 'aux------.png',
                    'aux-------.png', 'aux--------.png', 'aux---------.png',
                    'aux----------.png', 'aux-----------.png', 'aux------------.png',
                    'aux-------------.png', 'aux--------------.png', 'aux---------------.png',
                    'aux----------------.png', 'aux-----------------.png', 'aux------------------.png',
                    'aux-------------------.png', 'aux--------------------.png', 'aux---------------------.png',
                    'aux----------------------.png', 'aux-----------------------.png'],
            ],
            [
                'aux.png',
                'aux-----------------------.png',
                null,
                ['aux-.png', 'aux--.png', 'aux---.png', 'aux----.png', 'aux-----.png', 'aux------.png',
                    'aux-------.png', 'aux--------.png', 'aux---------.png',
                    'aux----------.png', 'aux-----------.png', 'aux------------.png',
                    'aux-------------.png', 'aux--------------.png', 'aux---------------.png',
                    'aux----------------.png', 'aux-----------------.png', 'aux------------------.png',
                    'aux-------------------.png', 'aux--------------------.png', 'aux---------------------.png',
                    'aux----------------------.png'],
            ],
            [
                'test.png',
                'test.png',
                null,
                ['test.jpg'],
            ],
            [
                '0.png',
                '0.png',
                null,
            ],
            [
                'test----------------------.png',
                'test.png',
                null,
                ['test----------------------.png'],
            ],
            [
                'test0000000000000000000000.png',
                '/^[0-9a-f]{8}\\.png$/u',
                null,
                ['test0000000000000000000000.png'],
            ],
        ];
    }
    
    /**
     * @param string $fieldName
     * @dataProvider invalidFieldNameProvider
     */
    public function testInvalidFieldName(string $fieldName)
    {
        $this->expectException(\DomainException::class);
        new FilenameValidator($fieldName);
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
    
    /**
     * @param string $filename
     * @param string|null $fieldName
     * @dataProvider invalidFilenameProvider
     */
    public function testInvalidFilename(string $filename, string $fieldName = null)
    {
        $this->expectException(\esperecyan\dictionary_php\exception\SyntaxException::class);
        (new FilenameValidator($fieldName))->correct($filename);
    }
    
    public function invalidFilenameProvider(): array
    {
        return [
            ['太陽.jpe'],
            ['four-seasons.mp4.ja'],
            ['test.png', 'audio'],
            [''],
            ['mp4'],
        ];
    }
}
