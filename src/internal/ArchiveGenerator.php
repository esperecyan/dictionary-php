<?php
namespace esperecyan\dictionary_php\internal;

trait ArchiveGenerator
{
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
}
