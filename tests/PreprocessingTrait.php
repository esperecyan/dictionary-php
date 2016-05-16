<?php
namespace esperecyan\dictionary_php;

trait PreprocessingTrait
{
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
        return preg_replace('/\\n +/u', "\r\n", $input);
    }
    
    /**
     * @param string $binary
     * @return \ZipArchive
     */
    protected function generateArchive(string $binary = ''): \ZipArchive
    {
        $path = tempnam(sys_get_temp_dir(), 'php');
        if ($binary !== '') {
            file_put_contents($path, $binary);
        }
        register_shutdown_function(function () use ($path) {
            if (file_exists($path)) {
                unlink($path);
            }
        });
        $archive = new \ZipArchive();
        $archive->open($path);
        return $archive;
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
