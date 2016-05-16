<?php
namespace esperecyan\dictionary_php\serializer;

use esperecyan\dictionary_php\internal\Dictionary;

/**
 * @runTestsInSeparateProcesses
 */
class GenericDictionarySerializerTest extends \PHPUnit_Framework_TestCase
{
    use \esperecyan\dictionary_php\PreprocessingTrait;
    
    /**
     * キーを無視して配列が配列に含まれるか調べる。
     * @param array $subset
     * @param array $array
     */
    public function assertArraySubsetWithoutKey(array $subset, array $array)
    {
        foreach ($subset as $needle) {
            $this->assertContains($needle, $array);
        }
    }
    
    /**
     * @param string[][][] $fieldsAsMultiDimensionalArrays
     * @param string[] $files
     * @param string $outputFilename
     * @param string $csv
     * @dataProvider dictionaryProvider
     */
    public function testResponse(
        array $fieldsAsMultiDimensionalArrays,
        array $files,
        string $outputFilename,
        string $csv
    ) {
        if ($files) {
            $archive = $this->generateArchive();
            foreach ($files as $filename => $file) {
                $archive->addFromString($filename, $file);
            }
            $fileInfo = new \SplFileInfo($archive->filename);
            $archive->close();
        }
        
        $dictionary = new Dictionary($fileInfo ?? null);
        foreach ($fieldsAsMultiDimensionalArrays as $fieldsAsMultiDimensionalArray) {
            $dictionary->addWordAsMultiDimensionalArray($fieldsAsMultiDimensionalArray);
        }
        
        $serializer = new GenericDictionarySerializer();
        if ($files) {
            ob_start();
            $serializer->response($dictionary);
            $archive = $this->generateArchive(ob_get_clean());
            
            $finfo = new \esperecyan\dictionary_php\fileinfo\Finfo(FILEINFO_MIME_TYPE);
            for ($i = 0, $l = $archive->numFiles; $i < $l; $i++) {
                $actualTypes[$archive->getNameIndex($i)] = $finfo->buffer($archive->getFromIndex($i));
            }
            $this->assertEquals(array_map(function (string $file) use ($finfo): string {
                return $finfo->buffer($file);
            }, $files + ['dictionary.csv' => $csv]), $actualTypes);
            
            $this->assertSame($this->stripIndentsAndToCRLF($csv), $archive->getFromName('dictionary.csv'));
        } else {
            $this->expectOutputString($this->stripIndentsAndToCRLF($csv));
            $serializer->response($dictionary);
        }

        $this->assertSame(200, http_response_code() ?: 200);
        $this->assertArraySubsetWithoutKey([
            'content-type: '
                . ($files ? 'application/zip' : 'text/csv; charset=utf-8; header=present'),
            'content-disposition: attachment; filename*=utf-8\'\'' . rawurlencode($outputFilename),
        ], xdebug_get_headers());
    }
    
    public function dictionaryProvider(): array
    {
        return [
            [
                [
                    [
                        'text' => ['太陽'],
                        'image' => ['local/sun.png'],
                        'answer' => ['たいよう', 'おひさま'],
                        'description' => ['恒星。'],
                        '@title' => ['恒星/惑星/衛星'],
                        '@summary' => ['恒星、惑星、衛星などのリスト。'],
                    ],
                    [
                        'text' => ['地球'],
                        'image' => ['local/earth.png'],
                        'answer' => ['ちきゅう'],
                        'description' => ['惑星。'],
                    ],
                    [
                        'text' => ['カロン'],
                        'image' => ['local/charon.png'],
                        'description' => [$this->stripIndents(
                            '冥王星の衛星。

                            > カロンは1978年6月22日にアメリカの天文学者ジェームズ・クリスティーによって発見された。
                            > その後、冥王星が冥府の王プルートーの名に因むことから、
                            > この衛星はギリシア神話の冥府の川・アケローンの渡し守カローンにちなんで「カロン」と命名された。
                            > なおクリスティーは当初から一貫してCharonの「char」を
                            > 妻シャーリーン（Charlene） のニックネーム「シャー（Char）」と同じように発音していたため、
                            > これが英語圏で定着して「シャーロン」と呼ばれるようになった。
                            引用元: [カロン (衛星) - Wikipedia](https://ja.wikipedia.org/wiki/%E3%82%AB%E3%83%AD%E3%83%B3_(%E8%A1%9B%E6%98%9F))'
                        )],
                    ],
                ],
                [],
                '恒星／惑星／衛星.csv',
                'text,image,answer,answer,description,@title,@summary
                太陽,local/sun.png,たいよう,おひさま,恒星。,恒星/惑星/衛星,恒星、惑星、衛星などのリスト。
                地球,local/earth.png,ちきゅう,,惑星。,,
                カロン,local/charon.png,,,"冥王星の衛星。
                
                > カロンは1978年6月22日にアメリカの天文学者ジェームズ・クリスティーによって発見された。
                > その後、冥王星が冥府の王プルートーの名に因むことから、
                > この衛星はギリシア神話の冥府の川・アケローンの渡し守カローンにちなんで「カロン」と命名された。
                > なおクリスティーは当初から一貫してCharonの「char」を
                > 妻シャーリーン（Charlene） のニックネーム「シャー（Char）」と同じように発音していたため、
                > これが英語圏で定着して「シャーロン」と呼ばれるようになった。
                引用元: [カロン (衛星) - Wikipedia](https://ja.wikipedia.org/wiki/%E3%82%AB%E3%83%AD%E3%83%B3_(%E8%A1%9B%E6%98%9F))",,
                ',
                null,
                null,
            ],
            [
                [
                    [
                        'text' => ['ピン'],
                        'image' => ['png.png'],
                    ],
                    [
                        'text' => ['ジェイフィフ'],
                        'image' => ['jfif.jpg'],
                    ],
                    [
                        'text' => ['エスブイジー'],
                        'image' => ['svg.svg'],
                    ],
                ],
                (function (): array {
                    $image = imagecreatetruecolor(1000, 1000);
                    ob_start();
                    imagepng($image);
                    $files['png.png'] = ob_get_clean();
                    
                    ob_start();
                    imagejpeg($image);
                    $files['jfif.jpg'] = ob_get_clean();
                    imagedestroy($image);
                    
                    $files['svg.svg'] = '<?xml version="1.0" ?>
                        <svg xmlns="http://www.w3.org/2000/svg"><rect width="100" height="100" /></svg>';
                    
                    return $files;
                })(),
                'dictionary.zip',
                'text,image
                ピン,png.png
                ジェイフィフ,jfif.jpg
                エスブイジー,svg.svg
                ',
            ],
        ];
    }
    
    /**
     * @expectedException \BadMethodCallException
     */
    public function testBadMethodCallException()
    {
        (new GenericDictionarySerializer())->response(new Dictionary());
    }
}
