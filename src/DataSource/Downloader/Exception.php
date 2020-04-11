<?php


namespace App\DataSource\Downloader;
use App;


class Exception extends App\Exception
{
    public static function notDownloaded(string $format, string $msg): self
    {
        return new self(__FUNCTION__, self::msg(sprintf('%s file not downloaded', strtolower($format)), $msg));
    }
    public static function notCsv(): self
    {
        return new self(__FUNCTION__, self::msg(
            'CSV file not downloaded',
            'Content-Type is not text/csv. Probably download link is changed'));
    }

    public static function dirNotCreated(string $downloadDir): self
    {
        return new self(__FUNCTION__, self::msg(
            sprintf('Directory "%s" was not created', $downloadDir)
        ));
    }
}
