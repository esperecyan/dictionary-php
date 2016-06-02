<?php
namespace esperecyan\dictionary_php;

trait PreprocessingTrait
{
    use internal\ArchiveGenerator;
    
    /**
     * @param string $input
     * @return string
     */
    protected function stripIndents(string $input): string
    {
        return preg_replace('/^ +/um', '', $input);
    }
    
    /**
     * @param string $input
     * @return string
     */
    protected function stripIndentsAndToCRLF(string $input): string
    {
        return preg_replace('/\\n */u', "\r\n", $input);
    }
    
    /**
     * @param string $input
     * @return \SplTempFileObject
     */
    protected function generateTempFileObject(string $input): \SplTempFileObject
    {
        $temp = new \SplTempFileObject();
        $temp->fwrite($input);
        return $temp;
    }
    
    /**
     * @param string[][][] $fieldsAsMultiDimensionalArrays
     * @param string[] $metadata
     * @param string[] $files
     * @return Dictionary
     */
    protected function generateDictionary(
        array $fieldsAsMultiDimensionalArrays,
        array $metadata,
        array $files
    ): Dictionary {
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
        
        return $dictionary;
    }
}
