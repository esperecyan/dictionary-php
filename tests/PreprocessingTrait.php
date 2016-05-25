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
}
