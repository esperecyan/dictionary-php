<?php
namespace esperecyan\dictionary_php\serializer;

use esperecyan\dictionary_php\Dictionary;

class GenericDictionarySerializerTest extends \PHPUnit_Framework_TestCase implements \Psr\Log\LoggerInterface
{
    use \esperecyan\dictionary_php\LogLevelLoggerTrait;
    use \esperecyan\dictionary_php\PreprocessingTrait;
    
    /**
     * @param string[][][] $fieldsAsMultiDimensionalArrays
     * @param string[] $metadata
     * @param string[] $files
     * @param string[] $expectedFile
     * @param string[] $logLevels
     * @dataProvider dictionaryProvider
     */
    public function testSerialize(
        array $fieldsAsMultiDimensionalArrays,
        array $metadata,
        array $files,
        array $expectedFile,
        array $logLevels
    ) {
        if ($files) {
            $tempDirectory = (new \esperecyan\dictionary_php\parser\GenericDictionaryParser())->generateTempDirectory();
            foreach ($files as $filename => $file) {
                file_put_contents("$tempDirectory/$filename", $file);
            }
        }
        
        $dictionary = new Dictionary(isset($tempDirectory) ? new \FilesystemIterator($tempDirectory) : null);
        foreach ($fieldsAsMultiDimensionalArrays as $fieldsAsMultiDimensionalArray) {
            $dictionary->addWord($fieldsAsMultiDimensionalArray);
        }
        $dictionary->setMetadata($metadata);
        
        $expectedFile['bytes'] = $this->stripIndentsAndToCRLF($expectedFile['bytes']);
        
        $serializer = new GenericDictionarySerializer();
        $serializer->setLogger($this);
        $file = $serializer->serialize($dictionary);
        if ($files) {
            $archive = $this->generateArchive($file['bytes']);
            
            $finfo = new \esperecyan\dictionary_php\fileinfo\Finfo(FILEINFO_MIME_TYPE);
            for ($i = 0, $l = $archive->numFiles; $i < $l; $i++) {
                $actualTypes[$archive->getNameIndex($i)] = $finfo->buffer($archive->getFromIndex($i));
            }
            $this->assertEquals(array_map(function (string $file) use ($finfo): string {
                return $finfo->buffer($file);
            }, $files + ['dictionary.csv' => $expectedFile['bytes']]), $actualTypes);
            
            $file['bytes'] = $archive->getFromName('dictionary.csv');
            $this->assertEquals($expectedFile, $file);
        } else {
            $this->assertEquals($expectedFile, $file);
        }
        
        $this->assertEquals($logLevels, $this->logLevels);
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
                [
                    '@title' => '恒星/惑星/衛星',
                    '@summary' => '恒星、惑星、衛星などのリスト。',
                ],
                [],
                [
                    'bytes' => 'text,image,answer,answer,description,@title,@summary
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
                    'type' => 'text/csv; charset=UTF-8; header=present',
                    'name' => '恒星／惑星／衛星.csv',
                ],
                [],
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
                [],
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
                [
                    'bytes' => 'text,image
                    ピン,png.png
                    ジェイフィフ,jfif.jpg
                    エスブイジー,svg.svg
                    ',
                    'type' => 'application/zip',
                    'name' => 'dictionary.zip',
                ],
                [],
            ],
        ];
    }
    
    /**
     * @expectedException \BadMethodCallException
     */
    public function testBadMethodCallException()
    {
        (new GenericDictionarySerializer())->serialize(new Dictionary());
    }
}
